<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$action = $_POST['action'] ?? '';

// Database configuration
$db_host = 'localhost';
$db_name = 'veer_sahayata';
$db_user = 'root';
$db_pass = '';

// Backup directory
$backup_dir = '../backups/';

try {
    switch ($action) {
        case 'create_backup_ajax':
            $backup_type = $_POST['backup_type'] ?? 'full';
            $include_data = isset($_POST['include_data']);
            $compress = isset($_POST['compress']);
            
            $timestamp = date('Y-m-d_H-i-s');
            $backup_filename = "veer_sahayata_backup_{$backup_type}_{$timestamp}.sql";
            
            if ($compress) {
                $backup_filename .= '.gz';
            }
            
            $backup_path = $backup_dir . $backup_filename;
            
            // Create backup
            if (createDatabaseBackup($backup_path, $backup_type, $include_data, $compress)) {
                // Log backup creation
                $stmt = $pdo->prepare("INSERT INTO backup_logs (filename, backup_type, file_size, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
                $file_size = filesize($backup_path);
                $stmt->execute([$backup_filename, $backup_type, $file_size, $_SESSION['user_id']]);
                
                echo json_encode([
                    'success' => true,
                    'message' => "Backup created successfully: {$backup_filename}",
                    'filename' => $backup_filename,
                    'size' => formatBytes($file_size)
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to create backup. Please check server configuration.'
                ]);
            }
            break;
            
        case 'get_backup_progress':
            // This would be used for real-time progress tracking
            // For now, we'll simulate progress
            $progress = $_POST['progress'] ?? 0;
            $progress = min($progress + rand(10, 30), 100);
            
            echo json_encode([
                'progress' => $progress,
                'status' => $progress < 100 ? 'Creating backup...' : 'Backup completed!'
            ]);
            break;
            
        case 'test_backup_system':
            // Test if backup system is properly configured
            $tests = [
                'backup_dir_writable' => is_writable($backup_dir),
                'mysqli_available' => extension_loaded('mysqli'),
                'pdo_mysql_available' => extension_loaded('pdo_mysql'),
                'gzip_available' => function_exists('gzopen'),
                'backup_logs_table' => false
            ];
            
            // Test if backup_logs table exists
            try {
                $stmt = $pdo->query("SELECT 1 FROM backup_logs LIMIT 1");
                $tests['backup_logs_table'] = true;
            } catch (Exception $e) {
                $tests['backup_logs_table'] = false;
            }
            
            $all_tests_passed = array_reduce($tests, function($carry, $test) {
                return $carry && $test;
            }, true);
            
            echo json_encode([
                'success' => $all_tests_passed,
                'tests' => $tests,
                'message' => $all_tests_passed ? 'All tests passed!' : 'Some tests failed. Check system configuration.'
            ]);
            break;
            
        case 'get_disk_space':
            $total_space = disk_total_space($backup_dir);
            $free_space = disk_free_space($backup_dir);
            $used_space = $total_space - $free_space;
            
            echo json_encode([
                'total' => formatBytes($total_space),
                'used' => formatBytes($used_space),
                'free' => formatBytes($free_space),
                'used_percentage' => round(($used_space / $total_space) * 100, 2)
            ]);
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

function createDatabaseBackup($backup_path, $backup_type = 'full', $include_data = true, $compress = false) {
    global $db_host, $db_name, $db_user, $db_pass, $pdo;
    
    try {
        $sql_dump = "-- Veer Sahayata Database Backup\n";
        $sql_dump .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
        $sql_dump .= "-- Backup Type: $backup_type\n\n";
        
        $sql_dump .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $sql_dump .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n";
        
        // Get tables to backup
        $tables = [];
        if ($backup_type === 'full') {
            $stmt = $pdo->query("SHOW TABLES");
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }
        } else {
            // Essential tables for partial backup
            $tables = ['users', 'settings', 'news', 'colleges', 'hospitals', 'documents', 'dependents'];
        }
        
        foreach ($tables as $table) {
            // Check if table exists
            try {
                $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
                $stmt->execute([$table]);
                if (!$stmt->fetch()) {
                    continue; // Skip non-existent tables
                }
                
                // Get table structure
                $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
                $row = $stmt->fetch(PDO::FETCH_NUM);
                
                $sql_dump .= "-- Table structure for table `$table`\n";
                $sql_dump .= "DROP TABLE IF EXISTS `$table`;\n";
                $sql_dump .= $row[1] . ";\n\n";
                
                if ($include_data) {
                    // Get table data
                    $stmt = $pdo->query("SELECT * FROM `$table`");
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($rows)) {
                        $sql_dump .= "-- Dumping data for table `$table`\n";
                        
                        $columns = array_keys($rows[0]);
                        $column_list = '`' . implode('`, `', $columns) . '`';
                        
                        foreach ($rows as $row) {
                            $values = array_map(function($value) use ($pdo) {
                                return $value === null ? 'NULL' : $pdo->quote($value);
                            }, array_values($row));
                            
                            $sql_dump .= "INSERT INTO `$table` ($column_list) VALUES (" . implode(', ', $values) . ");\n";
                        }
                        $sql_dump .= "\n";
                    }
                }
            } catch (Exception $e) {
                // Log table error but continue with other tables
                error_log("Error backing up table $table: " . $e->getMessage());
                continue;
            }
        }
        
        $sql_dump .= "SET FOREIGN_KEY_CHECKS=1;\n";
        
        // Write to file
        if ($compress) {
            $gz = gzopen($backup_path, 'w9');
            if ($gz === false) {
                throw new Exception("Cannot create compressed backup file");
            }
            gzwrite($gz, $sql_dump);
            gzclose($gz);
        } else {
            if (file_put_contents($backup_path, $sql_dump) === false) {
                throw new Exception("Cannot write backup file");
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Backup error: " . $e->getMessage());
        return false;
    }
}

function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $size >= 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}
?>
