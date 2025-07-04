<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_POST) {
    try {
        if (isset($_POST['update_profile'])) {
            // Update profile information
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
            $stmt->execute([
                $_POST['full_name'],
                $_POST['email'],
                $_POST['phone'],
                $_POST['address'],
                $_SESSION['user_id']
            ]);
            $success_message = "Profile updated successfully!";
        }
        
        if (isset($_POST['change_password'])) {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $current_hash = $stmt->fetch()['password'];
            
            if (password_verify($_POST['current_password'], $current_hash)) {
                if ($_POST['new_password'] === $_POST['confirm_password']) {
                    $new_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$new_hash, $_SESSION['user_id']]);
                    $success_message = "Password changed successfully!";
                } else {
                    $error_message = "New passwords do not match!";
                }
            } else {
                $error_message = "Current password is incorrect!";
            }
        }
        
        if (isset($_POST['update_notifications'])) {
            // Update notification preferences
            $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
            $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
            $push_notifications = isset($_POST['push_notifications']) ? 1 : 0;
            
            $stmt = $pdo->prepare("UPDATE users SET email_notifications = ?, sms_notifications = ?, push_notifications = ? WHERE id = ?");
            $stmt->execute([$email_notifications, $sms_notifications, $push_notifications, $_SESSION['user_id']]);
            $success_message = "Notification preferences updated successfully!";
        }
        
        if (isset($_POST['update_privacy'])) {
            // Update privacy settings
            $profile_visibility = $_POST['profile_visibility'];
            $data_sharing = isset($_POST['data_sharing']) ? 1 : 0;
            
            $stmt = $pdo->prepare("UPDATE users SET profile_visibility = ?, data_sharing = ? WHERE id = ?");
            $stmt->execute([$profile_visibility, $data_sharing, $_SESSION['user_id']]);
            $success_message = "Privacy settings updated successfully!";
        }
        
        // Refresh user data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
    } catch (Exception $e) {
        $error_message = "An error occurred: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Veer Sahayata</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/user_header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/user_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-cog text-primary me-2"></i>Account Settings</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-outline-secondary" onclick="window.print()">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>
                </div>

                <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Settings Navigation -->
                <div class="card settings-card mb-4">
                    <div class="card-body">
                        <ul class="nav nav-tabs settings-nav" id="settingsTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab">
                                    <i class="fas fa-user me-2"></i>Profile
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
                                <button class="nav-link" id="privacy-tab" data-bs-toggle="tab" data-bs-target="#privacy" type="button" role="tab">
                                    <i class="fas fa-lock me-2"></i>Privacy
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="account-tab" data-bs-toggle="tab" data-bs-target="#account" type="button" role="tab">
                                    <i class="fas fa-user-cog me-2"></i>Account
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content mt-4" id="settingsTabContent">
                            <!-- Profile Settings -->
                            <div class="tab-pane fade show active" id="profile" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h4 class="mb-4">Profile Information</h4>
                                        <form method="POST">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="full_name" class="form-label">Full Name</label>
                                                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="service_number" class="form-label">Service Number</label>
                                                        <input type="text" class="form-control" id="service_number" value="<?php echo htmlspecialchars($user['service_number']); ?>" readonly>
                                                        <small class="text-muted">Service number cannot be changed</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="email" class="form-label">Email Address</label>
                                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="phone" class="form-label">Phone Number</label>
                                                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="address" class="form-label">Address</label>
                                                <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address']); ?></textarea>
                                            </div>
                                            <button type="submit" name="update_profile" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i>Update Profile
                                            </button>
                                        </form>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <div class="mb-3">
                                                    <img src="../assets/images/default-avatar.png" class="profile-img" alt="Profile Picture">
                                                </div>
                                                <h6>Profile Picture</h6>
                                                <p class="text-muted small">Upload a new profile picture</p>
                                                <button class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-upload me-2"></i>Upload Photo
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Security Settings -->
                            <div class="tab-pane fade" id="security" role="tabpanel">
                                <h4 class="mb-4">Security Settings</h4>
                                
                                <!-- Change Password -->
                                <div class="card settings-card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-key me-2"></i>Change Password</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="current_password" class="form-label">Current Password</label>
                                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="new_password" class="form-label">New Password</label>
                                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                                        <small class="text-muted">Password must be at least 8 characters long</small>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="submit" name="change_password" class="btn btn-warning">
                                                <i class="fas fa-lock me-2"></i>Change Password
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <!-- Two-Factor Authentication -->
                                <div class="card settings-card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-mobile-alt me-2"></i>Two-Factor Authentication</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6>SMS Authentication</h6>
                                                <p class="text-muted mb-0">Receive verification codes via SMS</p>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="sms_2fa" <?php echo $user['sms_2fa'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="sms_2fa"></label>
                                            </div>
                                        </div>
                                        <hr>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6>Email Authentication</h6>
                                                <p class="text-muted mb-0">Receive verification codes via email</p>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="email_2fa" <?php echo $user['email_2fa'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="email_2fa"></label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Login Activity -->
                                <div class="card settings-card">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Login Activity</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm activity-table">
                                                <thead>
                                                    <tr>
                                                        <th>Date & Time</th>
                                                        <th>Device</th>
                                                        <th>Location</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td><?php echo date('M d, Y H:i'); ?></td>
                                                        <td>Windows PC</td>
                                                        <td>New Delhi, India</td>
                                                        <td><span class="badge bg-success">Current</span></td>
                                                    </tr>
                                                    <tr>
                                                        <td><?php echo date('M d, Y H:i', strtotime('-1 day')); ?></td>
                                                        <td>Mobile App</td>
                                                        <td>New Delhi, India</td>
                                                        <td><span class="badge bg-secondary">Success</span></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Notification Settings -->
                            <div class="tab-pane fade" id="notifications" role="tabpanel">
                                <h4 class="mb-4">Notification Preferences</h4>
                                <form method="POST">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Notification Channels</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" <?php echo $user['email_notifications'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="email_notifications">
                                                        <strong>Email Notifications</strong><br>
                                                        <small class="text-muted">Receive notifications via email</small>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="sms_notifications" name="sms_notifications" <?php echo $user['sms_notifications'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="sms_notifications">
                                                        <strong>SMS Notifications</strong><br>
                                                        <small class="text-muted">Receive notifications via SMS</small>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="push_notifications" name="push_notifications" <?php echo $user['push_notifications'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="push_notifications">
                                                        <strong>Push Notifications</strong><br>
                                                        <small class="text-muted">Receive push notifications on mobile devices</small>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Notification Types</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="pension_updates" checked>
                                                        <label class="form-check-label" for="pension_updates">
                                                            Pension Updates
                                                        </label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="document_updates" checked>
                                                        <label class="form-check-label" for="document_updates">
                                                            Document Status Updates
                                                        </label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="verification_updates" checked>
                                                        <label class="form-check-label" for="verification_updates">
                                                            Verification Updates
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="news_updates">
                                                        <label class="form-check-label" for="news_updates">
                                                            News & Announcements
                                                        </label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="scheme_updates">
                                                        <label class="form-check-label" for="scheme_updates">
                                                            New Schemes & Benefits
                                                        </label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="reminder_updates" checked>
                                                        <label class="form-check-label" for="reminder_updates">
                                                            Reminders & Deadlines
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" name="update_notifications" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Save Notification Preferences
                                    </button>
                                </form>
                            </div>

                            <!-- Privacy Settings -->
                            <div class="tab-pane fade" id="privacy" role="tabpanel">
                                <h4 class="mb-4">Privacy Settings</h4>
                                <form method="POST">
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h5 class="mb-0"><i class="fas fa-eye me-2"></i>Profile Visibility</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label for="profile_visibility" class="form-label">Who can see your profile?</label>
                                                <select class="form-select" id="profile_visibility" name="profile_visibility">
                                                    <option value="private" <?php echo $user['profile_visibility'] === 'private' ? 'selected' : ''; ?>>Only Me</option>
                                                    <option value="restricted" <?php echo $user['profile_visibility'] === 'restricted' ? 'selected' : ''; ?>>Defence Personnel Only</option>
                                                    <option value="public" <?php echo $user['profile_visibility'] === 'public' ? 'selected' : ''; ?>>Public</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h5 class="mb-0"><i class="fas fa-share-alt me-2"></i>Data Sharing</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="data_sharing" name="data_sharing" <?php echo $user['data_sharing'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="data_sharing">
                                                    <strong>Allow data sharing for research and analytics</strong><br>
                                                    <small class="text-muted">Help improve services by sharing anonymized data for research purposes</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h5 class="mb-0"><i class="fas fa-download me-2"></i>Data Export</h5>
                                        </div>
                                        <div class="card-body">
                                            <p class="text-muted">Download a copy of your data stored in our system.</p>
                                            <button type="button" class="btn btn-outline-primary">
                                                <i class="fas fa-download me-2"></i>Request Data Export
                                            </button>
                                        </div>
                                    </div>

                                    <button type="submit" name="update_privacy" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Save Privacy Settings
                                    </button>
                                </form>
                            </div>

                            <!-- Account Management -->
                            <div class="tab-pane fade" id="account" role="tabpanel">
                                <h4 class="mb-4">Account Management</h4>
                                
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Account Information</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p><strong>Account Created:</strong> <?php echo date('M d, Y', strtotime($user['created_at'])); ?></p>
                                                <p><strong>Last Login:</strong> <?php echo date('M d, Y H:i', strtotime($user['last_login'])); ?></p>
                                                <p><strong>Account Status:</strong> <span class="badge bg-success">Active</span></p>
                                            </div>
                                            <div class="col-md-6">
                                                <p><strong>Service Type:</strong> <?php echo ucfirst($user['service_type']); ?></p>
                                                <p><strong>Verification Status:</strong> 
                                                    <?php if (isset($user['is_verified']) && $user['is_verified']): ?>
                                                        <span class="badge bg-success">Verified</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Pending</span>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-users me-2"></i>Connected Accounts</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div>
                                                <i class="fab fa-google fa-lg me-2 text-danger"></i>
                                                <strong>Google Account</strong>
                                                <p class="text-muted mb-0 small">Not connected</p>
                                            </div>
                                            <button class="btn btn-outline-primary btn-sm">Connect</button>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <i class="fab fa-microsoft fa-lg me-2 text-primary"></i>
                                                <strong>Microsoft Account</strong>
                                                <p class="text-muted mb-0 small">Not connected</p>
                                            </div>
                                            <button class="btn btn-outline-primary btn-sm">Connect</button>
                                        </div>
                                    </div>
                                </div>

                                <div class="card settings-card border-danger danger-zone">
                                    <div class="card-header bg-danger text-white">
                                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Danger Zone</h5>
                                    </div>
                                    <div class="card-body">
                                        <h6>Delete Account</h6>
                                        <p class="text-muted">Once you delete your account, there is no going back. Please be certain.</p>
                                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                            <i class="fas fa-trash me-2"></i>Delete Account
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Delete Account Modal -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Warning!</strong> This action cannot be undone.
                    </div>
                    <p>Are you sure you want to delete your account? This will permanently remove:</p>
                    <ul>
                        <li>Your profile information</li>
                        <li>All uploaded documents</li>
                        <li>Application history</li>
                        <li>All personal data</li>
                    </ul>
                    <p>Type your service number to confirm:</p>
                    <input type="text" class="form-control" id="confirmServiceNumber" placeholder="Enter your service number">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete" disabled>Delete Account</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password strength validation
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const strength = calculatePasswordStrength(password);
            updatePasswordStrengthIndicator(strength);
        });

        function calculatePasswordStrength(password) {
            let strength = 0;
            if (password.length >= 8) strength += 25;
            if (password.match(/[a-z]/)) strength += 25;
            if (password.match(/[A-Z]/)) strength += 25;
            if (password.match(/[0-9]/)) strength += 25;
            if (password.match(/[^a-zA-Z0-9]/)) strength += 25;
            return Math.min(strength, 100);
        }

        function updatePasswordStrengthIndicator(strength) {
            let strengthIndicator = document.getElementById('password-strength');
            if (!strengthIndicator) {
                strengthIndicator = document.createElement('div');
                strengthIndicator.id = 'password-strength';
                strengthIndicator.className = 'password-strength';
                document.getElementById('new_password').parentNode.appendChild(strengthIndicator);
                
                const strengthBar = document.createElement('div');
                strengthBar.className = 'password-strength-bar';
                strengthIndicator.appendChild(strengthBar);
            }
            
            const bar = strengthIndicator.querySelector('.password-strength-bar');
            bar.className = 'password-strength-bar';
            
            if (strength < 25) bar.classList.add('strength-weak');
            else if (strength < 50) bar.classList.add('strength-fair');
            else if (strength < 75) bar.classList.add('strength-good');
            else bar.classList.add('strength-strong');
        }

        // Confirm password validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
            } else {
                this.setCustomValidity('');
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            }
        });

        // Delete account confirmation
        document.getElementById('confirmServiceNumber').addEventListener('input', function() {
            const serviceNumber = '<?php echo $user['service_number']; ?>';
            const confirmButton = document.getElementById('confirmDelete');
            
            if (this.value === serviceNumber) {
                confirmButton.disabled = false;
                confirmButton.classList.remove('btn-secondary');
                confirmButton.classList.add('btn-danger');
            } else {
                confirmButton.disabled = true;
                confirmButton.classList.add('btn-secondary');
                confirmButton.classList.remove('btn-danger');
            }
        });

        // Auto-save notification preferences
        document.querySelectorAll('#notifications input[type="checkbox"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                // Show loading indicator
                this.disabled = true;
                
                const formData = new FormData();
                formData.append('action', 'update_notifications');
                
                // Get all notification checkboxes
                document.querySelectorAll('#notifications input[type="checkbox"]').forEach(cb => {
                    if (cb.checked) {
                        formData.append(cb.name || cb.id, '1');
                    }
                });
                
                fetch('../api/update_settings.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Notification preferences updated successfully', 'success');
                    } else {
                        showToast('Failed to update preferences: ' + (data.error || 'Unknown error'), 'error');
                    }
                })
                .catch(error => {
                    showToast('Network error occurred', 'error');
                })
                .finally(() => {
                    this.disabled = false;
                });
            });
        });

        // 2FA toggle handlers
        document.querySelectorAll('#security input[type="checkbox"]').forEach(checkbox => {
            if (checkbox.id.includes('_2fa')) {
                checkbox.addEventListener('change', function() {
                    const formData = new FormData();
                    formData.append('action', 'toggle_2fa');
                    formData.append('type', this.id);
                    formData.append('enabled', this.checked);
                    
                    fetch('../api/update_settings.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast('2FA setting updated successfully', 'success');
                        } else {
                            showToast('Failed to update 2FA: ' + (data.error || 'Unknown error'), 'error');
                            this.checked = !this.checked; // Revert on failure
                        }
                    })
                    .catch(error => {
                        showToast('Network error occurred', 'error');
                        this.checked = !this.checked; // Revert on failure
                    });
                });
            }
        });

        // Data export handler
        document.querySelector('button[onclick*="Request Data Export"]')?.addEventListener('click', function() {
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating Export...';
            
            const formData = new FormData();
            formData.append('action', 'request_data_export');
            
            fetch('../api/update_settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Data export generated successfully', 'success');
                    // Create download link
                    const link = document.createElement('a');
                    link.href = data.download_url;
                    link.download = 'user_data_export.json';
                    link.click();
                } else {
                    showToast('Failed to generate export: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                showToast('Network error occurred', 'error');
            })
            .finally(() => {
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-download me-2"></i>Request Data Export';
            });
        });

        // Toast notification function
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toast-container') || createToastContainer();
            
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} border-0`;
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            
            toastContainer.appendChild(toast);
            
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            
            // Remove toast element after it's hidden
            toast.addEventListener('hidden.bs.toast', () => {
                toast.remove();
            });
        }

        function createToastContainer() {
            const container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
            return container;
        }

        // Auto-hide success alerts
        if (document.querySelector('.alert-success')) {
            setTimeout(() => {
                const alert = document.querySelector('.alert-success');
                if (alert) {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }
            }, 5000);
        }

        // Form validation on submit
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const requiredFields = this.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        field.classList.remove('is-invalid');
                        field.classList.add('is-valid');
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    showToast('Please fill in all required fields', 'error');
                }
            });
        });
    </script>
</body>
</html>
