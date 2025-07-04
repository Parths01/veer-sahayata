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

    // Format the hospital details HTML
    $html = '
    <div class="row">
        <div class="col-md-8">
            <h5 class="mb-3">' . htmlspecialchars($hospital['hospital_name']) . '</h5>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <strong>Hospital Code:</strong><br>
                    <span class="text-muted">' . htmlspecialchars($hospital['hospital_code']) . '</span>
                </div>
                <div class="col-md-6">
                    <strong>Hospital Type:</strong><br>
                    <span class="badge bg-' . (strtolower($hospital['hospital_type']) == 'government' ? 'primary' : (strtolower($hospital['hospital_type']) == 'private' ? 'danger' : 'warning')) . '">' . htmlspecialchars($hospital['hospital_type']) . '</span>
                </div>
            </div>
            
            ' . ($hospital['medical_director'] ? '
            <div class="mb-3">
                <strong>Medical Director:</strong><br>
                <span class="text-muted">' . htmlspecialchars($hospital['medical_director']) . '</span>
            </div>
            ' : '') . '
            
            <div class="row mb-3">
                ' . ($hospital['established_year'] ? '
                <div class="col-md-6">
                    <strong>Established Year:</strong><br>
                    <span class="text-muted">' . htmlspecialchars($hospital['established_year']) . '</span>
                </div>
                ' : '') . '
                ' . ($hospital['bed_capacity'] ? '
                <div class="col-md-6">
                    <strong>Bed Capacity:</strong><br>
                    <span class="text-muted">' . htmlspecialchars($hospital['bed_capacity']) . ' beds</span>
                </div>
                ' : '') . '
            </div>
            
            ' . ($hospital['specialties'] ? '
            <div class="mb-3">
                <strong>Medical Specialties:</strong><br>
                <span class="text-muted">' . nl2br(htmlspecialchars($hospital['specialties'])) . '</span>
            </div>
            ' : '') . '
            
            ' . ($hospital['insurance_accepted'] ? '
            <div class="mb-3">
                <strong>Insurance Accepted:</strong><br>
                <span class="text-muted">' . htmlspecialchars($hospital['insurance_accepted']) . '</span>
            </div>
            ' : '') . '
        </div>
        
        <div class="col-md-4">
            <div class="card bg-light">
                <div class="card-body">
                    <h6>Status</h6>
                    <span class="badge bg-' . ($hospital['status'] == 'active' ? 'success' : 'secondary') . ' mb-3">' . ucfirst($hospital['status']) . '</span>
                    
                    <div class="mb-3">
                        ' . ($hospital['emergency_services'] ? '<span class="badge bg-danger mb-1">Emergency Services</span><br>' : '') . '
                        ' . ($hospital['veterans_facility'] ? '<span class="badge bg-warning text-dark">Veterans Facility</span>' : '') . '
                    </div>
                    
                    <h6>Created</h6>
                    <small class="text-muted">' . date('d M Y, H:i', strtotime($hospital['created_at'])) . '</small>
                    
                    ' . ($hospital['updated_at'] ? '
                    <h6 class="mt-2">Last Updated</h6>
                    <small class="text-muted">' . date('d M Y, H:i', strtotime($hospital['updated_at'])) . '</small>
                    ' : '') . '
                </div>
            </div>
        </div>
    </div>
    
    <hr class="my-4">
    
    <div class="row">
        <div class="col-md-6">
            <h6><i class="fas fa-map-marker-alt text-primary"></i> Location Details</h6>
            ' . ($hospital['address'] ? '
            <div class="mb-2">
                <strong>Address:</strong><br>
                <span class="text-muted">' . nl2br(htmlspecialchars($hospital['address'])) . '</span>
            </div>
            ' : '') . '
            
            ' . ($hospital['district'] ? '
            <div class="mb-2">
                <strong>District:</strong> <span class="text-muted">' . htmlspecialchars($hospital['district']) . '</span>
            </div>
            ' : '') . '
            
            ' . ($hospital['state'] ? '
            <div class="mb-2">
                <strong>State:</strong> <span class="text-muted">' . htmlspecialchars($hospital['state']) . '</span>
            </div>
            ' : '') . '
        </div>
        
        <div class="col-md-6">
            <h6><i class="fas fa-phone text-primary"></i> Contact Information</h6>
            ' . ($hospital['contact_number'] ? '
            <div class="mb-2">
                <strong>Phone:</strong> <span class="text-muted">' . htmlspecialchars($hospital['contact_number']) . '</span>
            </div>
            ' : '') . '
            
            ' . ($hospital['email'] ? '
            <div class="mb-2">
                <strong>Email:</strong> <span class="text-muted">' . htmlspecialchars($hospital['email']) . '</span>
            </div>
            ' : '') . '
            
            ' . ($hospital['website'] ? '
            <div class="mb-2">
                <strong>Website:</strong> <a href="' . htmlspecialchars($hospital['website']) . '" target="_blank" class="text-decoration-none">' . htmlspecialchars($hospital['website']) . ' <i class="fas fa-external-link-alt fa-sm"></i></a>
            </div>
            ' : '') . '
        </div>
    </div>';

    echo json_encode(['success' => true, 'html' => $html]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
