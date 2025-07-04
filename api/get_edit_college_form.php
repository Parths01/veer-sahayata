<?php
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'College ID is required']);
    exit;
}

$collegeId = $_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM colleges WHERE id = ?");
    $stmt->execute([$collegeId]);
    $college = $stmt->fetch();

    if (!$college) {
        echo json_encode(['success' => false, 'message' => 'College not found']);
        exit;
    }

    // Format the edit form HTML
    $html = '
    <input type="hidden" name="action" value="edit_college">
    <input type="hidden" name="college_id" value="' . $college['id'] . '">
    
    <div class="row">
        <div class="col-md-8">
            <div class="mb-3">
                <label for="edit_college_name" class="form-label">College Name *</label>
                <input type="text" class="form-control" id="edit_college_name" name="college_name" 
                       value="' . htmlspecialchars($college['college_name']) . '" required>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <label for="edit_college_code" class="form-label">College Code *</label>
                <input type="text" class="form-control" id="edit_college_code" name="college_code" 
                       value="' . htmlspecialchars($college['college_code']) . '" required>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-4">
            <div class="mb-3">
                <label for="edit_college_type" class="form-label">College Type *</label>
                <select class="form-select" id="edit_college_type" name="college_type" required>
                    <option value="">Select Type</option>
                    <option value="Government"' . ($college['college_type'] == 'Government' ? ' selected' : '') . '>Government</option>
                    <option value="Private"' . ($college['college_type'] == 'Private' ? ' selected' : '') . '>Private</option>
                    <option value="Aided"' . ($college['college_type'] == 'Aided' ? ' selected' : '') . '>Aided</option>
                </select>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <label for="edit_established_year" class="form-label">Established Year</label>
                <input type="number" class="form-control" id="edit_established_year" name="established_year" 
                       value="' . htmlspecialchars($college['established_year']) . '" 
                       min="1800" max="' . date('Y') . '">
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <label for="edit_status" class="form-label">Status</label>
                <select class="form-select" id="edit_status" name="status">
                    <option value="active"' . ($college['status'] == 'active' ? ' selected' : '') . '>Active</option>
                    <option value="inactive"' . ($college['status'] == 'inactive' ? ' selected' : '') . '>Inactive</option>
                </select>
            </div>
        </div>
    </div>
    
    <div class="mb-3">
        <label for="edit_university_name" class="form-label">University/Board Name</label>
        <input type="text" class="form-control" id="edit_university_name" name="university_name" 
               value="' . htmlspecialchars($college['university_name']) . '">
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label for="edit_district" class="form-label">District</label>
                <input type="text" class="form-control" id="edit_district" name="district" 
                       value="' . htmlspecialchars($college['district']) . '">
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <label for="edit_state" class="form-label">State</label>
                <input type="text" class="form-control" id="edit_state" name="state" 
                       value="' . htmlspecialchars($college['state']) . '">
            </div>
        </div>
    </div>
    
    <div class="mb-3">
        <label for="edit_address" class="form-label">Full Address</label>
        <textarea class="form-control" id="edit_address" name="address" rows="3">' . htmlspecialchars($college['address']) . '</textarea>
    </div>
    
    <div class="row">
        <div class="col-md-4">
            <div class="mb-3">
                <label for="edit_contact_number" class="form-label">Contact Number</label>
                <input type="tel" class="form-control" id="edit_contact_number" name="contact_number" 
                       value="' . htmlspecialchars($college['contact_number']) . '">
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <label for="edit_email" class="form-label">Email Address</label>
                <input type="email" class="form-control" id="edit_email" name="email" 
                       value="' . htmlspecialchars($college['email']) . '">
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <label for="edit_website" class="form-label">Website URL</label>
                <input type="url" class="form-control" id="edit_website" name="website" 
                       value="' . htmlspecialchars($college['website']) . '">
            </div>
        </div>
    </div>
    
    <div class="mb-3">
        <label for="edit_principal_name" class="form-label">Principal Name</label>
        <input type="text" class="form-control" id="edit_principal_name" name="principal_name" 
               value="' . htmlspecialchars($college['principal_name']) . '">
    </div>';

    echo json_encode(['success' => true, 'html' => $html]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
