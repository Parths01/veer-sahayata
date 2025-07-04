<?php
session_start();
require_once '../config/database.php';
requireLogin();

// Only admin can upload photos for users
if (!isAdmin()) {
    $_SESSION['error'] = 'Only administrators can change profile photos';
    header('Location: ../user/profile.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_photo'])) {
    $userId = $_POST['user_id'] ?? $_SESSION['user_id']; // Admin can specify user_id
    $file = $_FILES['profile_photo'];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = 'File upload error';
        header('Location: ../user/profile.php');
        exit();
    }
    
    // Check file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        $_SESSION['error'] = 'Only JPG, PNG and GIF images are allowed';
        header('Location: ../user/profile.php');
        exit();
    }
    
    // Check file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        $_SESSION['error'] = 'File size must be less than 5MB';
        header('Location: ../user/profile.php');
        exit();
    }
    
    // Create upload directory if it doesn't exist
    $uploadDir = '../uploads/photos/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'user_' . $userId . '_' . time() . '.' . $extension;
    $uploadPath = $uploadDir . $filename;
    
    // Get old photo to delete
    $stmt = $pdo->prepare("SELECT photo FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $oldPhoto = $stmt->fetchColumn();
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        // Update database
        $stmt = $pdo->prepare("UPDATE users SET photo = ? WHERE id = ?");
        if ($stmt->execute([$filename, $userId])) {
            // Delete old photo if exists
            if ($oldPhoto && $oldPhoto !== 'default-avatar.png' && file_exists($uploadDir . $oldPhoto)) {
                unlink($uploadDir . $oldPhoto);
            }
            
            $_SESSION['success'] = 'Profile photo updated successfully';
        } else {
            $_SESSION['error'] = 'Failed to update database';
        }
    } else {
        $_SESSION['error'] = 'Failed to upload file';
    }
} else {
    $_SESSION['error'] = 'No file selected';
}

header('Location: ../user/profile.php');
exit();
?>
