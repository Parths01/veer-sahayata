<?php
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Hospital ID is required']);
    exit;
}

$hospitalId = $_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM hospitals WHERE id = ?");
    $stmt->execute([$hospitalId]);
    $hospital = $stmt->fetch();

    if (!$hospital) {
        echo json_encode(['success' => false, 'message' => 'Hospital not found']);
        exit;
    }

    // Format the edit form HTML
    $html = '
    <input type="hidden" name="action" value="edit_hospital">
    <input type="hidden" name="hospital_id" value="' . $hospital['id'] . '">
    
    <div class="row">
        <div class="col-md-8">
            <div class="mb-3">
                <label for="edit_hospital_name" class="form-label">Hospital Name *</label>
                <input type="text" class="form-control" id="edit_hospital_name" name="hospital_name" 
                       value="' . htmlspecialchars($hospital['hospital_name']) . '" required>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <label for="edit_hospital_code" class="form-label">Hospital Code *</label>
                <input type="text" class="form-control" id="edit_hospital_code" name="hospital_code" 
                       value="' . htmlspecialchars($hospital['hospital_code']) . '" required>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-4">
            <div class="mb-3">
                <label for="edit_hospital_type" class="form-label">Hospital Type *</label>
                <select class="form-select" id="edit_hospital_type" name="hospital_type" required>
                    <option value="">Select Type</option>
                    <option value="Government"' . ($hospital['hospital_type'] == 'Government' ? ' selected' : '') . '>Government</option>
                    <option value="Private"' . ($hospital['hospital_type'] == 'Private' ? ' selected' : '') . '>Private</option>
                    <option value="Armed Forces"' . ($hospital['hospital_type'] == 'Armed Forces' ? ' selected' : '') . '>Armed Forces</option>
                </select>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <label for="edit_established_year" class="form-label">Established Year</label>
                <input type="number" class="form-control" id="edit_established_year" name="established_year" 
                       value="' . htmlspecialchars($hospital['established_year']) . '" 
                       min="1800" max="' . date('Y') . '">
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <label for="edit_bed_capacity" class="form-label">Bed Capacity</label>
                <input type="number" class="form-control" id="edit_bed_capacity" name="bed_capacity" 
                       value="' . htmlspecialchars($hospital['bed_capacity']) . '" min="1">
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label for="edit_district" class="form-label">District</label>
                <input type="text" class="form-control" id="edit_district" name="district" 
                       value="' . htmlspecialchars($hospital['district']) . '">
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <label for="edit_state" class="form-label">State</label>
                <input type="text" class="form-control" id="edit_state" name="state" 
                       value="' . htmlspecialchars($hospital['state']) . '">
            </div>
        </div>
    </div>
    
    <div class="mb-3">
        <label for="edit_address" class="form-label">Full Address</label>
        <textarea class="form-control" id="edit_address" name="address" rows="3">' . htmlspecialchars($hospital['address']) . '</textarea>
    </div>
    
    <div class="row">
        <div class="col-md-4">
            <div class="mb-3">
                <label for="edit_contact_number" class="form-label">Contact Number</label>
                <input type="tel" class="form-control" id="edit_contact_number" name="contact_number" 
                       value="' . htmlspecialchars($hospital['contact_number']) . '">
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <label for="edit_email" class="form-label">Email Address</label>
                <input type="email" class="form-control" id="edit_email" name="email" 
                       value="' . htmlspecialchars($hospital['email']) . '">
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <label for="edit_website" class="form-label">Website URL</label>
                <input type="url" class="form-control" id="edit_website" name="website" 
                       value="' . htmlspecialchars($hospital['website']) . '">
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label for="edit_medical_director" class="form-label">Medical Director</label>
                <input type="text" class="form-control" id="edit_medical_director" name="medical_director" 
                       value="' . htmlspecialchars($hospital['medical_director']) . '">
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <label for="edit_insurance_accepted" class="form-label">Insurance Accepted</label>
                <input type="text" class="form-control" id="edit_insurance_accepted" name="insurance_accepted" 
                       value="' . htmlspecialchars($hospital['insurance_accepted']) . '" 
                       placeholder="e.g., CGHS, ECHS, Private Insurance">
            </div>
        </div>
    </div>
    
    <div class="mb-3">
        <label for="edit_specialties" class="form-label">Medical Specialties</label>
        <textarea class="form-control" id="edit_specialties" name="specialties" rows="2" 
                  placeholder="e.g., Cardiology, Neurology, Orthopedics">' . htmlspecialchars($hospital['specialties']) . '</textarea>
    </div>
    
    <div class="row">
        <div class="col-md-4">
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="edit_emergency_services" name="emergency_services" value="1"' . ($hospital['emergency_services'] ? ' checked' : '') . '>
                    <label class="form-check-label" for="edit_emergency_services">
                        Emergency Services Available
                    </label>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="edit_veterans_facility" name="veterans_facility" value="1"' . ($hospital['veterans_facility'] ? ' checked' : '') . '>
                    <label class="form-check-label" for="edit_veterans_facility">
                        Veterans Facility
                    </label>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <label for="edit_status" class="form-label">Status</label>
                <select class="form-select" id="edit_status" name="status">
                    <option value="active"' . ($hospital['status'] == 'active' ? ' selected' : '') . '>Active</option>
                    <option value="inactive"' . ($hospital['status'] == 'inactive' ? ' selected' : '') . '>Inactive</option>
                </select>
            </div>
        </div>
    </div>';

    echo json_encode(['success' => true, 'html' => $html]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
