<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$documentId = $_GET['id'] ?? 0;

try {
    // Get document details with user information
    $stmt = $pdo->prepare("
        SELECT d.*, u.service_number, u.full_name, u.service_type, u.phone, u.email,
               admin.full_name as reviewed_by_name
        FROM documents d 
        JOIN users u ON d.user_id = u.id 
        LEFT JOIN users admin ON d.reviewed_by = admin.id
        WHERE d.id = ?
    ");
    $stmt->execute([$documentId]);
    $document = $stmt->fetch();

    if (!$document) {
        throw new Exception('Document not found');
    }

    // Check if file exists
    $filePath = '../' . $document['file_path'];
    $fileExists = file_exists($filePath);
    $fileSize = $fileExists ? filesize($filePath) : 0;
    $fileExtension = strtolower(pathinfo($document['file_path'], PATHINFO_EXTENSION));
    $isImage = in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif']);

    $html = '
    <div class="row">
        <div class="col-md-8">
            <div class="document-preview-container">
                ' . ($isImage && $fileExists ? 
                    '<img src="../' . $document['file_path'] . '" class="document-preview img-fluid" alt="Document Preview">' :
                    '<div class="text-center p-5 bg-light">
                        <i class="fas fa-file-' . ($fileExtension == 'pdf' ? 'pdf' : 'alt') . ' fa-5x text-muted"></i>
                        <h5 class="mt-3">' . basename($document['file_path']) . '</h5>
                        <p class="text-muted">Preview not available for this file type</p>
                    </div>'
                ) . '
            </div>
        </div>
        <div class="col-md-4">
            <h6 class="text-primary">Document Information</h6>
            <table class="table table-sm">
                <tr><td><strong>Type:</strong></td><td>' . ucfirst(str_replace('_', ' ', $document['document_type'])) . '</td></tr>
                <tr><td><strong>Name:</strong></td><td>' . ($document['document_name'] ?: 'N/A') . '</td></tr>
                <tr><td><strong>File:</strong></td><td>' . basename($document['file_path']) . '</td></tr>
                <tr><td><strong>Size:</strong></td><td>' . formatFileSize($fileSize) . '</td></tr>
                <tr><td><strong>Status:</strong></td><td>
                    <span class="badge bg-' . ($document['status'] == 'approved' ? 'success' : ($document['status'] == 'rejected' ? 'danger' : 'warning')) . '">
                        ' . ucfirst($document['status']) . '
                    </span>
                </td></tr>
                <tr><td><strong>Uploaded:</strong></td><td>' . date('d/m/Y H:i', strtotime($document['uploaded_at'])) . '</td></tr>
                ' . ($document['reviewed_at'] ? 
                    '<tr><td><strong>Reviewed:</strong></td><td>' . date('d/m/Y H:i', strtotime($document['reviewed_at'])) . '</td></tr>' : ''
                ) . '
                ' . ($document['reviewed_by_name'] ? 
                    '<tr><td><strong>Reviewed By:</strong></td><td>' . $document['reviewed_by_name'] . '</td></tr>' : ''
                ) . '
            </table>
            
            <h6 class="text-primary mt-4">User Information</h6>
            <table class="table table-sm">
                <tr><td><strong>Name:</strong></td><td>' . $document['full_name'] . '</td></tr>
                <tr><td><strong>Service No:</strong></td><td>' . $document['service_number'] . '</td></tr>
                <tr><td><strong>Service:</strong></td><td>' . $document['service_type'] . '</td></tr>
                <tr><td><strong>Phone:</strong></td><td>' . $document['phone'] . '</td></tr>
                <tr><td><strong>Email:</strong></td><td>' . $document['email'] . '</td></tr>
            </table>
            
            ' . ($document['admin_remarks'] ? 
                '<h6 class="text-primary mt-4">Admin Remarks</h6>
                <div class="alert alert-info">
                    <small>' . nl2br(htmlspecialchars($document['admin_remarks'])) . '</small>
                </div>' : ''
            ) . '
        </div>
    </div>';

    echo json_encode([
        'success' => true, 
        'html' => $html,
        'file_path' => $document['file_path'],
        'document' => $document
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function formatFileSize($size) {
    $units = array('B', 'KB', 'MB', 'GB');
    $power = $size > 0 ? floor(log($size, 1024)) : 0;
    return number_format($size / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
}
?>
