<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Handle different update types
if ($_POST['action']) {
    try {
        switch ($_POST['action']) {
            case 'update_notifications':
                $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
                $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
                $push_notifications = isset($_POST['push_notifications']) ? 1 : 0;
                
                $stmt = $pdo->prepare("UPDATE users SET email_notifications = ?, sms_notifications = ?, push_notifications = ? WHERE id = ?");
                $stmt->execute([$email_notifications, $sms_notifications, $push_notifications, $_SESSION['user_id']]);
                
                // Update specific notification preferences
                $notification_types = ['pension_updates', 'document_updates', 'verification_updates', 'news_updates', 'scheme_updates', 'reminder_updates'];
                foreach ($notification_types as $type) {
                    $value = isset($_POST[$type]) ? 1 : 0;
                    $stmt = $pdo->prepare("INSERT INTO user_preferences (user_id, preference_key, preference_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE preference_value = ?");
                    $stmt->execute([$_SESSION['user_id'], $type, $value, $value]);
                }
                
                echo json_encode(['success' => true, 'message' => 'Notification preferences updated successfully']);
                break;
                
            case 'toggle_2fa':
                $type = $_POST['type']; // 'sms_2fa' or 'email_2fa'
                $enabled = $_POST['enabled'] === 'true' ? 1 : 0;
                
                if (in_array($type, ['sms_2fa', 'email_2fa'])) {
                    $stmt = $pdo->prepare("UPDATE users SET $type = ? WHERE id = ?");
                    $stmt->execute([$enabled, $_SESSION['user_id']]);
                    
                    echo json_encode(['success' => true, 'message' => '2FA setting updated successfully']);
                } else {
                    echo json_encode(['error' => 'Invalid 2FA type']);
                }
                break;
                
            case 'update_profile_picture':
                if (isset($_FILES['profile_picture'])) {
                    $file = $_FILES['profile_picture'];
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    
                    if (in_array($file['type'], $allowed_types) && $file['size'] <= 5 * 1024 * 1024) { // 5MB limit
                        $upload_dir = '../uploads/profiles/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $new_filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
                        $upload_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                            $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                            $stmt->execute([$new_filename, $_SESSION['user_id']]);
                            
                            echo json_encode(['success' => true, 'message' => 'Profile picture updated successfully', 'filename' => $new_filename]);
                        } else {
                            echo json_encode(['error' => 'Failed to upload file']);
                        }
                    } else {
                        echo json_encode(['error' => 'Invalid file type or size too large']);
                    }
                } else {
                    echo json_encode(['error' => 'No file uploaded']);
                }
                break;
                
            case 'request_data_export':
                // Generate a data export request
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user_info = $stmt->fetch();
                
                $stmt = $pdo->prepare("SELECT * FROM documents WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $documents = $stmt->fetchAll();
                
                $stmt = $pdo->prepare("SELECT * FROM dependents WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $dependents = $stmt->fetchAll();
                
                $export_data = [
                    'user_info' => $user_info,
                    'documents' => $documents,
                    'dependents' => $dependents,
                    'export_date' => date('Y-m-d H:i:s')
                ];
                
                $export_filename = 'user_data_export_' . $_SESSION['user_id'] . '_' . date('Y-m-d') . '.json';
                $export_path = '../exports/' . $export_filename;
                
                if (!is_dir('../exports/')) {
                    mkdir('../exports/', 0755, true);
                }
                
                file_put_contents($export_path, json_encode($export_data, JSON_PRETTY_PRINT));
                
                echo json_encode(['success' => true, 'message' => 'Data export generated successfully', 'download_url' => '../exports/' . $export_filename]);
                break;
                
            default:
                echo json_encode(['error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'No action specified']);
}
?>
