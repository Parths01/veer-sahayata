<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Scheme ID is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM schemes WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $scheme = $stmt->fetch();

    if (!$scheme) {
        echo json_encode(['success' => false, 'message' => 'Scheme not found']);
        exit;
    }

    // Generate HTML for edit form
    $html = '
    <input type="hidden" name="action" value="edit_scheme">
    <input type="hidden" name="scheme_id" value="' . $scheme['id'] . '">
    
    <div class="row">
        <div class="col-md-8">
            <div class="mb-3">
                <label for="edit_scheme_name" class="form-label">Scheme Name *</label>
                <input type="text" class="form-control" id="edit_scheme_name" name="scheme_name" 
                       value="' . htmlspecialchars($scheme['scheme_name']) . '" required>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <label for="edit_scheme_type" class="form-label">Scheme Type *</label>
                <select class="form-select" id="edit_scheme_type" name="scheme_type" required>
                    <option value="">Select Type</option>
                    <option value="Central"' . ($scheme['scheme_type'] == 'Central' ? ' selected' : '') . '>Central</option>
                    <option value="State"' . ($scheme['scheme_type'] == 'State' ? ' selected' : '') . '>State</option>
                </select>
            </div>
        </div>
    </div>
    
    <div class="mb-3">
        <label for="edit_description" class="form-label">Description *</label>
        <textarea class="form-control" id="edit_description" name="description" rows="3" required>' . htmlspecialchars($scheme['description']) . '</textarea>
    </div>
    
    <div class="mb-3">
        <label for="edit_eligibility_criteria" class="form-label">Eligibility Criteria</label>
        <textarea class="form-control" id="edit_eligibility_criteria" name="eligibility_criteria" rows="3">' . htmlspecialchars($scheme['eligibility_criteria']) . '</textarea>
    </div>
    
    <div class="mb-3">
        <label for="edit_application_process" class="form-label">Application Process</label>
        <textarea class="form-control" id="edit_application_process" name="application_process" rows="3">' . htmlspecialchars($scheme['application_process']) . '</textarea>
    </div>
    
    <div class="mb-3">
        <label for="edit_documents_required" class="form-label">Documents Required</label>
        <textarea class="form-control" id="edit_documents_required" name="documents_required" rows="3">' . htmlspecialchars($scheme['documents_required']) . '</textarea>
    </div>
    
    <div class="mb-3">
        <label for="edit_benefits" class="form-label">Benefits</label>
        <textarea class="form-control" id="edit_benefits" name="benefits" rows="3">' . htmlspecialchars($scheme['benefits']) . '</textarea>
    </div>
    
    <div class="mb-3">
        <label for="edit_status" class="form-label">Status</label>
        <select class="form-select" id="edit_status" name="status">
            <option value="active"' . ($scheme['status'] == 'active' ? ' selected' : '') . '>Active</option>
            <option value="inactive"' . ($scheme['status'] == 'inactive' ? ' selected' : '') . '>Inactive</option>
        </select>
    </div>';

    echo json_encode([
        'success' => true, 
        'html' => $html,
        'scheme' => $scheme
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
