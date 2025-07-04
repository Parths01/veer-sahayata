<?php
/**
 * Automated Backup Cron Script
 * 
 * This script should be run via cron job for automated backups
 * Example crontab entry:
 * 0 2 * * * php /path/to/veer-sahayata/scripts/backup_cron.php
 */

// Set up error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/backup_cron.log');

// Include database configuration
require_once __DIR__ . '/../config/database.php';

// Database configuration
$db_host = 'localhost';
$db_name = 'veer_sahayata';
$db_user = 'root';
$db_pass = '';

// Backup directory
$backup_dir = __DIR__ . '/../backups/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Log directory
$log_dir = __DIR__ . '/../logs/';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

try {
    // Get backup settings
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'backup_%'");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    $schedule_enabled = $settings['backup_schedule_enabled'] ?? 0;
    $schedule_type = $settings['backup_schedule_type'] ?? 'weekly';
    $backup_type = $settings['backup_schedule_backup_type'] ?? 'full';
    $retention_days = $settings['backup_retention_days'] ?? 30;
    $max_files = $settings['backup_max_files'] ?? 10;
    
    if (!$schedule_enabled) {
        logMessage("Automated backup is disabled");
        exit(0);
    }
    
    // Check if we should run backup based on schedule
    if (!shouldRunBackup($schedule_type)) {
        logMessage("Backup not scheduled to run now");
        exit(0);
    }
    
    logMessage("Starting automated backup (Type: $backup_type, Schedule: $schedule_type)");
    
    // Create backup filename
    $timestamp = date('Y-m-d_H-i-s');
    $backup_filename = "veer_sahayata_auto_{$backup_type}_{$timestamp}.sql.gz";
    $backup_path = $backup_dir . $backup_filename;
    
    // Create backup
    if (createDatabaseBackup($backup_path, $backup_type, true, true)) {
        // Log successful backup
        $file_size = filesize($backup_path);
        $stmt = $pdo->prepare("INSERT INTO backup_logs (filename, backup_type, file_size, created_by, created_at) VALUES (?, ?, ?, 1, NOW())");
        $stmt->execute([$backup_filename, $backup_type, $file_size]);
        
        logMessage("Backup created successfully: $backup_filename (Size: " . formatBytes($file_size) . ")");
        
        // Clean up old backups
        cleanupOldBackups($backup_dir, $retention_days, $max_files);
        
    } else {
        logMessage("ERROR: Failed to create backup");
        exit(1);
    }
    
} catch (Exception $e) {
    logMessage("ERROR: " . $e->getMessage());
    exit(1);
}

function shouldRunBackup($schedule_type) {
    $last_backup_file = __DIR__ . '/../logs/last_backup.txt';
    $last_backup_time = 0;
    
    if (file_exists($last_backup_file)) {
        $last_backup_time = (int)file_get_contents($last_backup_file);
    }
    
    $current_time = time();
    $time_diff = $current_time - $last_backup_time;
    
    switch ($schedule_type) {
        case 'daily':
            $should_run = $time_diff >= 86400; // 24 hours
            break;
        case 'weekly':
            $should_run = $time_diff >= 604800; // 7 days
            break;
        case 'monthly':
            $should_run = $time_diff >= 2592000; // 30 days
            break;
        default:
            $should_run = false;
    }
    
    if ($should_run) {
        file_put_contents($last_backup_file, $current_time);
    }
    
    return $should_run;
}

function createDatabaseBackup($backup_path, $backup_type = 'full', $include_data = true, $compress = true) {
    global $db_host, $db_name, $db_user, $db_pass, $pdo;
    
    try {
        $sql_dump = "-- Veer Sahayata Automated Database Backup\n";
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
            $tables = ['users', 'settings', 'news', 'colleges', 'hospitals', 'documents', 'dependents', 'backup_logs'];
        }
        
        foreach ($tables as $table) {
            // Check if table exists
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
        }
        
        $sql_dump .= "SET FOREIGN_KEY_CHECKS=1;\n";
        
        // Write to file
        if ($compress) {
            $gz = gzopen($backup_path, 'w9');
            gzwrite($gz, $sql_dump);
            gzclose($gz);
        } else {
            file_put_contents($backup_path, $sql_dump);
        }
        
        return true;
    } catch (Exception $e) {
        logMessage("Backup creation error: " . $e->getMessage());
        return false;
    }
}

function cleanupOldBackups($backup_dir, $retention_days, $max_files) {
    global $pdo;
    
    try {
        $files = glob($backup_dir . "veer_sahayata_auto_*.sql*");
        
        // Sort by modification time (oldest first)
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        $cutoff_time = time() - ($retention_days * 86400);
        $deleted_count = 0;
        
        foreach ($files as $file) {
            $should_delete = false;
            
            // Delete if older than retention period
            if (filemtime($file) < $cutoff_time) {
                $should_delete = true;
            }
            
            // Delete if exceeding max files limit
            if (count($files) - $deleted_count > $max_files) {
                $should_delete = true;
            }
            
            if ($should_delete) {
                $filename = basename($file);
                
                if (unlink($file)) {
                    // Update backup log
                    $stmt = $pdo->prepare("UPDATE backup_logs SET deleted_at = NOW() WHERE filename = ?");
                    $stmt->execute([$filename]);
                    
                    logMessage("Deleted old backup: $filename");
                    $deleted_count++;
                } else {
                    logMessage("Failed to delete: $filename");
                }
            }
        }
        
        if ($deleted_count > 0) {
            logMessage("Cleanup completed: $deleted_count old backups deleted");
        }
        
    } catch (Exception $e) {
        logMessage("Cleanup error: " . $e->getMessage());
    }
}

function logMessage($message) {
    $log_file = __DIR__ . '/../logs/backup_cron.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message\n";
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    echo $log_entry; // Also output to console for cron logging
}

function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $size >= 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}
?>
