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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'general_settings':
                $site_name = $_POST['site_name'] ?? '';
                $site_description = $_POST['site_description'] ?? '';
                $admin_email = $_POST['admin_email'] ?? '';
                $contact_phone = $_POST['contact_phone'] ?? '';
                $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
                
                // Update or insert settings
                $settings = [
                    'site_name' => $site_name,
                    'site_description' => $site_description,
                    'admin_email' => $admin_email,
                    'contact_phone' => $contact_phone,
                    'maintenance_mode' => $maintenance_mode
                ];
                
                foreach ($settings as $key => $value) {
                    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                                         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                    $stmt->execute([$key, $value]);
                }
                
                $message = "General settings updated successfully!";
                $message_type = "success";
                break;
                
            case 'security_settings':
                $session_timeout = $_POST['session_timeout'] ?? 30;
                $password_min_length = $_POST['password_min_length'] ?? 8;
                $max_login_attempts = $_POST['max_login_attempts'] ?? 5;
                $require_email_verification = isset($_POST['require_email_verification']) ? 1 : 0;
                $two_factor_auth = isset($_POST['two_factor_auth']) ? 1 : 0;
                
                $security_settings = [
                    'session_timeout' => $session_timeout,
                    'password_min_length' => $password_min_length,
                    'max_login_attempts' => $max_login_attempts,
                    'require_email_verification' => $require_email_verification,
                    'two_factor_auth' => $two_factor_auth
                ];
                
                foreach ($security_settings as $key => $value) {
                    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                                         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                    $stmt->execute([$key, $value]);
                }
                
                $message = "Security settings updated successfully!";
                $message_type = "success";
                break;
                
            case 'notification_settings':
                $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
                $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
                $notification_frequency = $_POST['notification_frequency'] ?? 'daily';
                $admin_notifications = isset($_POST['admin_notifications']) ? 1 : 0;
                
                $notification_settings = [
                    'email_notifications' => $email_notifications,
                    'sms_notifications' => $sms_notifications,
                    'notification_frequency' => $notification_frequency,
                    'admin_notifications' => $admin_notifications
                ];
                
                foreach ($notification_settings as $key => $value) {
                    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                                         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                    $stmt->execute([$key, $value]);
                }
                
                $message = "Notification settings updated successfully!";
                $message_type = "success";
                break;
                
            case 'backup_database':
                // Simple backup functionality
                $backup_file = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
                $backup_path = '../database/backups/' . $backup_file;
                
                // Create backups directory if it doesn't exist
                if (!file_exists('../database/backups/')) {
                    mkdir('../database/backups/', 0755, true);
                }
                
                // Note: This is a simplified backup. In production, use mysqldump
                $message = "Backup functionality prepared. Use mysqldump for production backups.";
                $message_type = "info";
                break;
                
            case 'clear_cache':
                // Clear any cached data or temporary files
                $cache_dir = '../cache/';
                if (is_dir($cache_dir)) {
                    $files = glob($cache_dir . '*');
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            unlink($file);
                        }
                    }
                }
                
                $message = "Cache cleared successfully!";
                $message_type = "success";
                break;
        }
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Create settings table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    // Table might already exist
}

// Get current settings
function getSetting($pdo, $key, $default = '') {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

// Get system statistics
$stats = [];
try {
    $stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['active_users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
    $stats['total_documents'] = $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn() ?? 0;
    $stats['pending_verifications'] = $pdo->query("SELECT COUNT(*) FROM user_verifications WHERE status = 'pending'")->fetchColumn() ?? 0;
    $stats['database_size'] = "N/A"; // Would need specific query for actual size
} catch (PDOException $e) {
    $stats = ['total_users' => 0, 'active_users' => 0, 'total_documents' => 0, 'pending_verifications' => 0, 'database_size' => 'N/A'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Veer Sahayata Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .settings-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
            margin-bottom: 20px;
        }
        .settings-card:hover {
            transform: translateY(-2px);
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .stat-card.success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .stat-card.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .stat-card.info {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        .stat-card.danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
        }
        .nav-tabs .nav-link {
            border-radius: 8px;
            margin-right: 8px;
            border: none;
            background: #f8f9fa;
            color: #495057;
        }
        .nav-tabs .nav-link.active {
            background: #007bff;
            color: white;
        }
        .nav-tabs .nav-link:hover {
            background: #e9ecef;
            border-color: transparent;
        }
        .nav-tabs .nav-link.active:hover {
            background: #0056b3;
            color: white;
        }
        .form-control:focus, .form-select:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            border: none;
            border-radius: 8px;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
            transform: translateY(-1px);
        }
        .btn-outline-primary:hover {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            border-color: #007bff;
        }
        .btn-outline-warning:hover {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            border-color: #ffc107;
        }
        .settings-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
        }
        .sidebar {
            background-color: #f8f9fa;
        }
        .sidebar .nav-link {
            color: #495057;
            border-radius: 8px;
            margin: 2px 0;
            padding: 10px 15px;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: #007bff;
            color: white;
        }
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-cog text-primary"></i> System Settings</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-outline-secondary" onclick="location.reload()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- System Overview -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="d-flex align-items-center justify-content-center mb-2">
                                <i class="fas fa-users fa-2x me-3"></i>
                                <div>
                                    <h3 class="mb-0"><?php echo number_format($stats['total_users']); ?></h3>
                                    <small>Total Users</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card success text-center">
                            <div class="d-flex align-items-center justify-content-center mb-2">
                                <i class="fas fa-user-check fa-2x me-3"></i>
                                <div>
                                    <h3 class="mb-0"><?php echo number_format($stats['active_users']); ?></h3>
                                    <small>Active Users</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card info text-center">
                            <div class="d-flex align-items-center justify-content-center mb-2">
                                <i class="fas fa-file-alt fa-2x me-3"></i>
                                <div>
                                    <h3 class="mb-0"><?php echo number_format($stats['total_documents']); ?></h3>
                                    <small>Documents</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card warning text-center">
                            <div class="d-flex align-items-center justify-content-center mb-2">
                                <i class="fas fa-clock fa-2x me-3"></i>
                                <div>
                                    <h3 class="mb-0"><?php echo number_format($stats['pending_verifications']); ?></h3>
                                    <small>Pending Verifications</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Settings Tabs -->
                <div class="settings-card">
                    <div class="card-body">
                        <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                                    <i class="fas fa-globe me-2"></i>General
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                                    <i class="fas fa-shield-alt me-2"></i>Security
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab">
                                    <i class="fas fa-bell me-2"></i>Notifications
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab">
                                    <i class="fas fa-server me-2"></i>System
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content mt-4" id="settingsTabContent">
                            <!-- General Settings -->
                            <div class="tab-pane fade show active" id="general" role="tabpanel">
                                <form method="POST">
                                    <input type="hidden" name="action" value="general_settings">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="site_name" class="form-label">Site Name</label>
                                                <input type="text" class="form-control" id="site_name" name="site_name" 
                                                       value="<?php echo htmlspecialchars(getSetting($pdo, 'site_name', 'Veer Sahayata')); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="admin_email" class="form-label">Admin Email</label>
                                                <input type="email" class="form-control" id="admin_email" name="admin_email" 
                                                       value="<?php echo htmlspecialchars(getSetting($pdo, 'admin_email', 'admin@veersahayata.gov.in')); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="site_description" class="form-label">Site Description</label>
                                        <textarea class="form-control" id="site_description" name="site_description" rows="3"><?php echo htmlspecialchars(getSetting($pdo, 'site_description', 'Welfare portal for Indian Defence Personnel and their families')); ?></textarea>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="contact_phone" class="form-label">Contact Phone</label>
                                                <input type="tel" class="form-control" id="contact_phone" name="contact_phone" 
                                                       value="<?php echo htmlspecialchars(getSetting($pdo, 'contact_phone', '+91-11-1234-5678')); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <div class="form-check mt-4">
                                                    <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                                                           <?php echo getSetting($pdo, 'maintenance_mode', '0') ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="maintenance_mode">
                                                        Maintenance Mode
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Save General Settings
                                    </button>
                                </form>
                            </div>

                            <!-- Security Settings -->
                            <div class="tab-pane fade" id="security" role="tabpanel">
                                <form method="POST">
                                    <input type="hidden" name="action" value="security_settings">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="session_timeout" class="form-label">Session Timeout (minutes)</label>
                                                <input type="number" class="form-control" id="session_timeout" name="session_timeout" 
                                                       value="<?php echo htmlspecialchars(getSetting($pdo, 'session_timeout', '30')); ?>" min="5" max="1440">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="password_min_length" class="form-label">Minimum Password Length</label>
                                                <input type="number" class="form-control" id="password_min_length" name="password_min_length" 
                                                       value="<?php echo htmlspecialchars(getSetting($pdo, 'password_min_length', '8')); ?>" min="4" max="32">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="max_login_attempts" class="form-label">Max Login Attempts</label>
                                                <input type="number" class="form-control" id="max_login_attempts" name="max_login_attempts" 
                                                       value="<?php echo htmlspecialchars(getSetting($pdo, 'max_login_attempts', '5')); ?>" min="1" max="20">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mt-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="require_email_verification" name="require_email_verification" 
                                                           <?php echo getSetting($pdo, 'require_email_verification', '0') ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="require_email_verification">
                                                        Require Email Verification
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="two_factor_auth" name="two_factor_auth" 
                                                           <?php echo getSetting($pdo, 'two_factor_auth', '0') ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="two_factor_auth">
                                                        Two-Factor Authentication
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-shield-alt me-2"></i>Save Security Settings
                                    </button>
                                </form>
                            </div>

                            <!-- Notification Settings -->
                            <div class="tab-pane fade" id="notifications" role="tabpanel">
                                <form method="POST">
                                    <input type="hidden" name="action" value="notification_settings">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="notification_frequency" class="form-label">Notification Frequency</label>
                                                <select class="form-select" id="notification_frequency" name="notification_frequency">
                                                    <option value="immediate" <?php echo getSetting($pdo, 'notification_frequency', 'daily') === 'immediate' ? 'selected' : ''; ?>>Immediate</option>
                                                    <option value="hourly" <?php echo getSetting($pdo, 'notification_frequency', 'daily') === 'hourly' ? 'selected' : ''; ?>>Hourly</option>
                                                    <option value="daily" <?php echo getSetting($pdo, 'notification_frequency', 'daily') === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                                    <option value="weekly" <?php echo getSetting($pdo, 'notification_frequency', 'daily') === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mt-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" 
                                                           <?php echo getSetting($pdo, 'email_notifications', '1') ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="email_notifications">
                                                        Email Notifications
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="sms_notifications" name="sms_notifications" 
                                                           <?php echo getSetting($pdo, 'sms_notifications', '0') ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="sms_notifications">
                                                        SMS Notifications
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="admin_notifications" name="admin_notifications" 
                                                           <?php echo getSetting($pdo, 'admin_notifications', '1') ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="admin_notifications">
                                                        Admin Notifications
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-bell me-2"></i>Save Notification Settings
                                    </button>
                                </form>
                            </div>

                            <!-- System Settings -->
                            <div class="tab-pane fade" id="system" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="settings-card">
                                            <div class="card-header bg-primary text-white">
                                                <h5 class="mb-0"><i class="fas fa-database me-2"></i>Database Management</h5>
                                            </div>
                                            <div class="card-body">
                                                <p class="text-muted">Manage database backups and maintenance.</p>
                                                <form method="POST" class="mb-2">
                                                    <input type="hidden" name="action" value="backup_database">
                                                    <button type="submit" class="btn btn-outline-primary w-100">
                                                        <i class="fas fa-download me-2"></i>Create Backup
                                                    </button>
                                                </form>
                                                <small class="text-muted">Last backup: Never</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="settings-card">
                                            <div class="card-header bg-warning text-white">
                                                <h5 class="mb-0"><i class="fas fa-broom me-2"></i>Cache Management</h5>
                                            </div>
                                            <div class="card-body">
                                                <p class="text-muted">Clear system cache and temporary files.</p>
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="clear_cache">
                                                    <button type="submit" class="btn btn-outline-warning w-100">
                                                        <i class="fas fa-trash me-2"></i>Clear Cache
                                                    </button>
                                                </form>
                                                <small class="text-muted">Cache size: ~0 MB</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mt-4">
                                    <div class="col-12">
                                        <div class="settings-card">
                                            <div class="card-header bg-info text-white">
                                                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>System Information</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <table class="table table-sm table-hover">
                                                            <tr>
                                                                <td><strong>PHP Version:</strong></td>
                                                                <td><?php echo PHP_VERSION; ?></td>
                                                            </tr>
                                                            <tr>
                                                                <td><strong>Server Software:</strong></td>
                                                                <td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td>
                                                            </tr>
                                                            <tr>
                                                                <td><strong>Database Size:</strong></td>
                                                                <td><?php echo $stats['database_size']; ?></td>
                                                            </tr>
                                                        </table>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <table class="table table-sm table-hover">
                                                            <tr>
                                                                <td><strong>Memory Limit:</strong></td>
                                                                <td><?php echo ini_get('memory_limit'); ?></td>
                                                            </tr>
                                                            <tr>
                                                                <td><strong>Upload Max Size:</strong></td>
                                                                <td><?php echo ini_get('upload_max_filesize'); ?></td>
                                                            </tr>
                                                            <tr>
                                                                <td><strong>Execution Time:</strong></td>
                                                                <td><?php echo ini_get('max_execution_time'); ?>s</td>
                                                            </tr>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Form validation
        document.querySelectorAll('form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(function(field) {
                    if (!field.value.trim()) {
                        field.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                }
            });
        });
    </script>
</body>
</html>
