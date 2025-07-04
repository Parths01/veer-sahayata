<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    $user_id = $_SESSION['user_id'];
    $name = trim($_POST['name']);
    $relationship = $_POST['relationship'];
    $date_of_birth = $_POST['date_of_birth'] ?: null;
    $aadhar_number = $_POST['aadhar_number'] ?: null;

    // Validate input
    if (empty($name) || empty($relationship)) {
        throw new Exception('Name and relationship are required');
    }

    // Validate Aadhar number if provided
    if ($aadhar_number && !preg_match('/^\d{12}$/', $aadhar_number)) {
        throw new Exception('Invalid Aadhar number. Must be 12 digits.');
    }

    $photo_filename = null;

    // Handle photo upload if provided
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $photo = $_FILES['photo'];
        
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png'];
        if (!in_array($photo['type'], $allowed_types)) {
            throw new Exception('Invalid photo type. Only JPG and PNG files are allowed.');
        }

        // Validate file size (2MB max)
        if ($photo['size'] > 2 * 1024 * 1024) {
            throw new Exception('Photo size too large. Maximum size is 2MB.');
        }

        // Create upload directory if it doesn't exist
        $upload_dir = '../uploads/dependents/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Generate unique filename
        $file_extension = pathinfo($photo['name'], PATHINFO_EXTENSION);
        $photo_filename = $user_id . '_dep_' . time() . '.' . $file_extension;
        $photo_path = $upload_dir . $photo_filename;

        // Move uploaded file
        if (!move_uploaded_file($photo['tmp_name'], $photo_path)) {
            throw new Exception('Failed to save photo');
        }
    }

    // Insert dependent into database
    $stmt = $pdo->prepare("INSERT INTO dependents (user_id, name, relationship, date_of_birth, aadhar_number, photo) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $name, $relationship, $date_of_birth, $aadhar_number, $photo_filename]);

    echo json_encode([
        'success' => true, 
        'message' => 'Dependent added successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>
