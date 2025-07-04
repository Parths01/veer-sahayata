<?php
session_start();
require_once '../config/database.php';

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$message = '';
$message_type = '';

// Database configuration
$db_host = 'localhost';
$db_name = 'veer_sahayata';
$db_user = 'root';
$db_pass = 'Parth123';

// Backup directory
$backup_dir = '../backups/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_backup':
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
                    
                    $message = "Backup created successfully: {$backup_filename}";
                    $message_type = "success";
                } else {
                    $message = "Failed to create backup. Please check server configuration.";
                    $message_type = "danger";
                }
                break;
                
            case 'restore_backup':
                $backup_file = $_POST['backup_file'] ?? '';
                $confirm = $_POST['confirm_restore'] ?? '';
                
                if ($confirm === 'RESTORE') {
                    $backup_path = $backup_dir . $backup_file;
                    
                    if (file_exists($backup_path)) {
                        if (restoreDatabase($backup_path)) {
                            // Log restore
                            $stmt = $pdo->prepare("INSERT INTO backup_logs (filename, backup_type, action, created_by, created_at) VALUES (?, 'restore', 'restore', ?, NOW())");
                            $stmt->execute([$backup_file, $_SESSION['user_id']]);
                            
                            $message = "Database restored successfully from: {$backup_file}";
                            $message_type = "success";
                        } else {
                            $message = "Failed to restore database. Please check the backup file.";
                            $message_type = "danger";
                        }
                    } else {
                        $message = "Backup file not found.";
                        $message_type = "danger";
                    }
                } else {
                    $message = "Please type 'RESTORE' to confirm the restore operation.";
                    $message_type = "warning";
                }
                break;
                
            case 'delete_backup':
                $backup_file = $_POST['backup_file'] ?? '';
                $backup_path = $backup_dir . $backup_file;
                
                if (file_exists($backup_path)) {
                    if (unlink($backup_path)) {
                        // Log deletion
                        $stmt = $pdo->prepare("UPDATE backup_logs SET deleted_at = NOW() WHERE filename = ?");
                        $stmt->execute([$backup_file]);
                        
                        $message = "Backup deleted successfully: {$backup_file}";
                        $message_type = "success";
                    } else {
                        $message = "Failed to delete backup file.";
                        $message_type = "danger";
                    }
                } else {
                    $message = "Backup file not found.";
                    $message_type = "danger";
                }
                break;
                
            case 'schedule_backup':
                $schedule_type = $_POST['schedule_type'] ?? '';
                $backup_type = $_POST['scheduled_backup_type'] ?? 'full';
                $enabled = isset($_POST['schedule_enabled']) ? 1 : 0;
                
                // Update or insert scheduled backup settings
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                $stmt->execute(['backup_schedule_type', $schedule_type]);
                $stmt->execute(['backup_schedule_backup_type', $backup_type]);
                $stmt->execute(['backup_schedule_enabled', $enabled]);
                
                $message = "Backup schedule updated successfully!";
                $message_type = "success";
                break;
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Function to create database backup
function createDatabaseBackup($backup_path, $backup_type = 'full', $include_data = true, $compress = false) {
    global $db_host, $db_name, $db_user, $db_pass;
    
    try {
        $pdo_backup = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
        $pdo_backup->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $sql_dump = "-- Veer Sahayata Database Backup\n";
        $sql_dump .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
        $sql_dump .= "-- Backup Type: $backup_type\n\n";
        
        $sql_dump .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $sql_dump .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n";
        
        // Get tables to backup
        $tables = [];
        if ($backup_type === 'full') {
            $stmt = $pdo_backup->query("SHOW TABLES");
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }
        } else {
            // Essential tables for partial backup
            $tables = ['users', 'settings', 'news', 'colleges', 'hospitals', 'documents', 'dependents'];
        }
        
        foreach ($tables as $table) {
            // Get table structure
            $stmt = $pdo_backup->query("SHOW CREATE TABLE `$table`");
            $row = $stmt->fetch(PDO::FETCH_NUM);
            
            $sql_dump .= "-- Table structure for table `$table`\n";
            $sql_dump .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql_dump .= $row[1] . ";\n\n";
            
            if ($include_data) {
                // Get table data
                $stmt = $pdo_backup->query("SELECT * FROM `$table`");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($rows)) {
                    $sql_dump .= "-- Dumping data for table `$table`\n";
                    
                    $columns = array_keys($rows[0]);
                    $column_list = '`' . implode('`, `', $columns) . '`';
                    
                    foreach ($rows as $row) {
                        $values = array_map(function($value) use ($pdo_backup) {
                            return $value === null ? 'NULL' : $pdo_backup->quote($value);
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
        error_log("Backup error: " . $e->getMessage());
        return false;
    }
}

// Function to restore database
function restoreDatabase($backup_path) {
    global $db_host, $db_name, $db_user, $db_pass;
    
    try {
        $pdo_restore = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
        $pdo_restore->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Read backup file
        if (pathinfo($backup_path, PATHINFO_EXTENSION) === 'gz') {
            $sql_content = gzfile($backup_path);
            $sql_content = implode('', $sql_content);
        } else {
            $sql_content = file_get_contents($backup_path);
        }
        
        // Split SQL statements
        $statements = array_filter(
            array_map('trim', explode(';', $sql_content)),
            function($stmt) {
                return !empty($stmt) && !preg_match('/^--/', $stmt);
            }
        );
        
        // Execute statements
        foreach ($statements as $statement) {
            if (!empty(trim($statement))) {
                $pdo_restore->exec($statement);
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Restore error: " . $e->getMessage());
        return false;
    }
}

// Get existing backups
$backups = [];
if (is_dir($backup_dir)) {
    $files = glob($backup_dir . "*.sql*");
    foreach ($files as $file) {
        $filename = basename($file);
        $backups[] = [
            'filename' => $filename,
            'size' => filesize($file),
            'date' => date('Y-m-d H:i:s', filemtime($file)),
            'path' => $file
        ];
    }
    // Sort by date (newest first)
    usort($backups, function($a, $b) {
        return strcmp($b['date'], $a['date']);
    });
}

// Get backup logs
$backup_logs = [];
try {
    $stmt = $pdo->query("SELECT * FROM backup_logs ORDER BY created_at DESC LIMIT 50");
    $backup_logs = $stmt->fetchAll();
} catch (Exception $e) {
    // Table might not exist
}

// Get current settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'backup_%'");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    // Default values
}

$schedule_enabled = $settings['backup_schedule_enabled'] ?? 0;
$schedule_type = $settings['backup_schedule_type'] ?? 'weekly';
$schedule_backup_type = $settings['backup_schedule_backup_type'] ?? 'full';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup - Veer Sahayata Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-database text-primary me-2"></i>Database Backup & Restore</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-outline-secondary" onclick="window.print()">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>
                </div>

                <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'danger' ? 'exclamation-circle' : 'info-circle'); ?> me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Backup Tabs -->
                <div class="card">
                    <div class="card-body">
                        <ul class="nav nav-tabs" id="backupTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="create-tab" data-bs-toggle="tab" data-bs-target="#create" type="button" role="tab">
                                    <i class="fas fa-plus me-2"></i>Create Backup
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="manage-tab" data-bs-toggle="tab" data-bs-target="#manage" type="button" role="tab">
                                    <i class="fas fa-list me-2"></i>Manage Backups
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="restore-tab" data-bs-toggle="tab" data-bs-target="#restore" type="button" role="tab">
                                    <i class="fas fa-undo me-2"></i>Restore
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="schedule-tab" data-bs-toggle="tab" data-bs-target="#schedule" type="button" role="tab">
                                    <i class="fas fa-clock me-2"></i>Schedule
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs" type="button" role="tab">
                                    <i class="fas fa-history me-2"></i>Backup Logs
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content mt-4" id="backupTabContent">
                            <!-- Create Backup -->
                            <div class="tab-pane fade show active" id="create" role="tabpanel">
                                <h4 class="mb-4">Create New Backup</h4>
                                
                                <form method="POST" id="createBackupForm">
                                    <input type="hidden" name="action" value="create_backup">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Backup Options</h5>
                                                </div>
                                                <div class="card-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Backup Type</label>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="backup_type" id="full_backup" value="full" checked>
                                                            <label class="form-check-label" for="full_backup">
                                                                <strong>Full Backup</strong><br>
                                                                <small class="text-muted">All tables and data</small>
                                                            </label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="backup_type" id="partial_backup" value="partial">
                                                            <label class="form-check-label" for="partial_backup">
                                                                <strong>Partial Backup</strong><br>
                                                                <small class="text-muted">Essential tables only</small>
                                                            </label>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" id="include_data" name="include_data" checked>
                                                            <label class="form-check-label" for="include_data">
                                                                Include Data (not just structure)
                                                            </label>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" id="compress" name="compress">
                                                            <label class="form-check-label" for="compress">
                                                                Compress backup file (.gz)
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Backup Information</h5>
                                                </div>
                                                <div class="card-body">
                                                    <p><strong>Database:</strong> <?php echo htmlspecialchars($db_name); ?></p>
                                                    <p><strong>Server:</strong> <?php echo htmlspecialchars($db_host); ?></p>
                                                    <p><strong>Date:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                                                    <p><strong>Admin:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                                                    
                                                    <div class="alert alert-info">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        <strong>Note:</strong> Full backups may take longer for large databases.
                                                        Compressed backups save storage space but take longer to create.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="text-center mt-4">
                                        <button type="submit" class="btn btn-primary btn-lg" id="createBackupBtn">
                                            <i class="fas fa-database me-2"></i>Create Backup
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Manage Backups -->
                            <div class="tab-pane fade" id="manage" role="tabpanel">
                                <h4 class="mb-4">Existing Backups</h4>
                                
                                <?php if (empty($backups)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    No backups found. Create your first backup using the "Create Backup" tab.
                                </div>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Filename</th>
                                                <th>Size</th>
                                                <th>Created</th>
                                                <th>Type</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($backups as $backup): ?>
                                            <tr>
                                                <td>
                                                    <i class="fas fa-file-archive me-2 text-primary"></i>
                                                    <?php echo htmlspecialchars($backup['filename']); ?>
                                                </td>
                                                <td><?php echo formatBytes($backup['size']); ?></td>
                                                <td><?php echo $backup['date']; ?></td>
                                                <td>
                                                    <?php if (strpos($backup['filename'], '_full_') !== false): ?>
                                                        <span class="badge bg-success">Full</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-info">Partial</span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (pathinfo($backup['filename'], PATHINFO_EXTENSION) === 'gz'): ?>
                                                        <span class="badge bg-secondary">Compressed</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="../backups/<?php echo urlencode($backup['filename']); ?>" 
                                                           class="btn btn-outline-primary" download>
                                                            <i class="fas fa-download"></i> Download
                                                        </a>
                                                        <button type="button" class="btn btn-outline-danger" 
                                                                onclick="deleteBackup('<?php echo htmlspecialchars($backup['filename']); ?>')">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Restore Backup -->
                            <div class="tab-pane fade" id="restore" role="tabpanel">
                                <h4 class="mb-4">Restore Database</h4>
                                
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Warning:</strong> Restoring a backup will replace all current data. 
                                    This action cannot be undone. Make sure to create a backup before restoring.
                                </div>
                                
                                <?php if (!empty($backups)): ?>
                                <form method="POST" id="restoreForm">
                                    <input type="hidden" name="action" value="restore_backup">
                                    
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="mb-3">
                                                <label for="backup_file" class="form-label">Select Backup File</label>
                                                <select class="form-select" name="backup_file" id="backup_file" required>
                                                    <option value="">Choose a backup file...</option>
                                                    <?php foreach ($backups as $backup): ?>
                                                    <option value="<?php echo htmlspecialchars($backup['filename']); ?>">
                                                        <?php echo htmlspecialchars($backup['filename']); ?> 
                                                        (<?php echo $backup['date']; ?> - <?php echo formatBytes($backup['size']); ?>)
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="confirm_restore" class="form-label">Type "RESTORE" to confirm</label>
                                                <input type="text" class="form-control" name="confirm_restore" id="confirm_restore" 
                                                       placeholder="Type RESTORE to confirm" required>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-danger" id="restoreBtn" disabled>
                                                <i class="fas fa-undo me-2"></i>Restore Database
                                            </button>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <div class="card border-warning">
                                                <div class="card-header bg-warning text-dark">
                                                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Restore Process</h6>
                                                </div>
                                                <div class="card-body">
                                                    <ol class="mb-0">
                                                        <li>Select backup file</li>
                                                        <li>Type "RESTORE" to confirm</li>
                                                        <li>Click restore button</li>
                                                        <li>Wait for completion</li>
                                                    </ol>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                                <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    No backup files available for restore. Create a backup first.
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Schedule Backups -->
                            <div class="tab-pane fade" id="schedule" role="tabpanel">
                                <h4 class="mb-4">Automatic Backup Schedule</h4>
                                
                                <form method="POST">
                                    <input type="hidden" name="action" value="schedule_backup">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Schedule Settings</h5>
                                                </div>
                                                <div class="card-body">
                                                    <div class="mb-3">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" id="schedule_enabled" 
                                                                   name="schedule_enabled" <?php echo $schedule_enabled ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="schedule_enabled">
                                                                Enable Automatic Backups
                                                            </label>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="schedule_type" class="form-label">Backup Frequency</label>
                                                        <select class="form-select" name="schedule_type" id="schedule_type">
                                                            <option value="daily" <?php echo $schedule_type === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                                            <option value="weekly" <?php echo $schedule_type === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                                            <option value="monthly" <?php echo $schedule_type === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="scheduled_backup_type" class="form-label">Backup Type</label>
                                                        <select class="form-select" name="scheduled_backup_type" id="scheduled_backup_type">
                                                            <option value="full" <?php echo $schedule_backup_type === 'full' ? 'selected' : ''; ?>>Full Backup</option>
                                                            <option value="partial" <?php echo $schedule_backup_type === 'partial' ? 'selected' : ''; ?>>Partial Backup</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <strong>Note:</strong> Automatic backups require a cron job to be set up on your server.
                                                Add this line to your crontab:
                                                <br><br>
                                                <code>0 2 * * * php /path/to/veer-sahayata/scripts/backup_cron.php</code>
                                                <br><br>
                                                This will run backups daily at 2 AM. Adjust the schedule as needed.
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Save Schedule Settings
                                    </button>
                                </form>
                            </div>

                            <!-- Backup Logs -->
                            <div class="tab-pane fade" id="logs" role="tabpanel">
                                <h4 class="mb-4">Backup Activity Logs</h4>
                                
                                <?php if (empty($backup_logs)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    No backup logs available.
                                </div>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Date & Time</th>
                                                <th>Action</th>
                                                <th>Filename</th>
                                                <th>Type</th>
                                                <th>Size</th>
                                                <th>Created By</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($backup_logs as $log): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($log['action'] === 'restore'): ?>
                                                        <span class="badge bg-warning">Restore</span>
                                                    <?php elseif ($log['deleted_at']): ?>
                                                        <span class="badge bg-danger">Deleted</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Created</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($log['filename']); ?></td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo ucfirst($log['backup_type']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo $log['file_size'] ? formatBytes($log['file_size']) : '-'; ?>
                                                </td>
                                                <td>Admin #<?php echo $log['created_by']; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Delete Backup Modal -->
    <div class="modal fade" id="deleteBackupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Backup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this backup file?</p>
                    <p><strong id="deleteBackupName"></strong></p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This action cannot be undone.
                    </div>
                </div>
                <div class="modal-footer">
                    <form method="POST" id="deleteBackupForm">
                        <input type="hidden" name="action" value="delete_backup">
                        <input type="hidden" name="backup_file" id="deleteBackupFile">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Backup</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Create backup form handling
        document.getElementById('createBackupForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('createBackupBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating Backup...';
        });

        // Restore confirmation
        document.getElementById('confirm_restore').addEventListener('input', function() {
            const restoreBtn = document.getElementById('restoreBtn');
            const backupFile = document.getElementById('backup_file').value;
            
            if (this.value === 'RESTORE' && backupFile) {
                restoreBtn.disabled = false;
            } else {
                restoreBtn.disabled = true;
            }
        });

        document.getElementById('backup_file').addEventListener('change', function() {
            const confirmInput = document.getElementById('confirm_restore');
            const restoreBtn = document.getElementById('restoreBtn');
            
            if (this.value && confirmInput.value === 'RESTORE') {
                restoreBtn.disabled = false;
            } else {
                restoreBtn.disabled = true;
            }
        });

        // Restore form handling
        document.getElementById('restoreForm').addEventListener('submit', function(e) {
            if (!confirm('Are you absolutely sure you want to restore the database? This will replace ALL current data!')) {
                e.preventDefault();
                return;
            }
            
            const btn = document.getElementById('restoreBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Restoring...';
        });

        // Delete backup function
        function deleteBackup(filename) {
            document.getElementById('deleteBackupName').textContent = filename;
            document.getElementById('deleteBackupFile').value = filename;
            
            const modal = new bootstrap.Modal(document.getElementById('deleteBackupModal'));
            modal.show();
        }

        // Auto-refresh backup list every 30 seconds
        setInterval(function() {
            if (document.getElementById('manage-tab').classList.contains('active')) {
                location.reload();
            }
        }, 30000);

        // Progress simulation for backup creation
        function simulateProgress() {
            // This could be enhanced with actual progress tracking via AJAX
        }
    </script>
</body>
</html>

<?php
// Helper function to format file sizes
function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $size >= 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}
?>
