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
    // Handle file upload
    if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed');
    }

    $file = $_FILES['document_file'];
    $document_type = $_POST['document_type'];
    $document_name = $_POST['document_name'];
    $user_id = $_SESSION['user_id'];

    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('Invalid file type. Only JPG, PNG, and PDF files are allowed.');
    }

    // Validate file size (5MB max)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('File size too large. Maximum size is 5MB.');
    }

    // Create upload directory if it doesn't exist
    $upload_dir = '../uploads/documents/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $file_name = $user_id . '_' . $document_type . '_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . $file_name;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        throw new Exception('Failed to save file');
    }

    // Check if document already exists for this user and type
    $stmt = $pdo->prepare("SELECT id FROM documents WHERE user_id = ? AND document_type = ?");
    $stmt->execute([$user_id, $document_type]);
    $existing_doc = $stmt->fetch();

    if ($existing_doc) {
        // Update existing document
        $stmt = $pdo->prepare("UPDATE documents SET document_name = ?, file_path = ?, status = 'pending', uploaded_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$document_name, $file_name, $existing_doc['id']]);
    } else {
        // Insert new document
        $stmt = $pdo->prepare("INSERT INTO documents (user_id, document_type, document_name, file_path, status) VALUES (?, ?, ?, ?, 'pending')");
        $stmt->execute([$user_id, $document_type, $document_name, $file_name]);
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Document uploaded successfully and is pending approval'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>
