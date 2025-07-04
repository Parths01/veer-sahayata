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

    // Format the college details HTML
    $html = '
    <div class="row">
        <div class="col-md-8">
            <h5 class="mb-3">' . htmlspecialchars($college['college_name']) . '</h5>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <strong>College Code:</strong><br>
                    <span class="text-muted">' . htmlspecialchars($college['college_code']) . '</span>
                </div>
                <div class="col-md-6">
                    <strong>College Type:</strong><br>
                    <span class="badge bg-' . (strtolower($college['college_type']) == 'government' ? 'primary' : (strtolower($college['college_type']) == 'private' ? 'danger' : 'warning')) . '">' . htmlspecialchars($college['college_type']) . '</span>
                </div>
            </div>
            
            ' . ($college['university_name'] ? '
            <div class="mb-3">
                <strong>University/Board:</strong><br>
                <span class="text-muted">' . htmlspecialchars($college['university_name']) . '</span>
            </div>
            ' : '') . '
            
            ' . ($college['established_year'] ? '
            <div class="mb-3">
                <strong>Established Year:</strong><br>
                <span class="text-muted">' . htmlspecialchars($college['established_year']) . '</span>
            </div>
            ' : '') . '
            
            ' . ($college['principal_name'] ? '
            <div class="mb-3">
                <strong>Principal:</strong><br>
                <span class="text-muted">' . htmlspecialchars($college['principal_name']) . '</span>
            </div>
            ' : '') . '
        </div>
        
        <div class="col-md-4">
            <div class="card bg-light">
                <div class="card-body">
                    <h6>Status</h6>
                    <span class="badge bg-' . ($college['status'] == 'active' ? 'success' : 'secondary') . ' mb-3">' . ucfirst($college['status']) . '</span>
                    
                    <h6>Created</h6>
                    <small class="text-muted">' . date('d M Y, H:i', strtotime($college['created_at'])) . '</small>
                    
                    ' . ($college['updated_at'] ? '
                    <h6 class="mt-2">Last Updated</h6>
                    <small class="text-muted">' . date('d M Y, H:i', strtotime($college['updated_at'])) . '</small>
                    ' : '') . '
                </div>
            </div>
        </div>
    </div>
    
    <hr class="my-4">
    
    <div class="row">
        <div class="col-md-6">
            <h6><i class="fas fa-map-marker-alt text-primary"></i> Location Details</h6>
            ' . ($college['address'] ? '
            <div class="mb-2">
                <strong>Address:</strong><br>
                <span class="text-muted">' . nl2br(htmlspecialchars($college['address'])) . '</span>
            </div>
            ' : '') . '
            
            ' . ($college['district'] ? '
            <div class="mb-2">
                <strong>District:</strong> <span class="text-muted">' . htmlspecialchars($college['district']) . '</span>
            </div>
            ' : '') . '
            
            ' . ($college['state'] ? '
            <div class="mb-2">
                <strong>State:</strong> <span class="text-muted">' . htmlspecialchars($college['state']) . '</span>
            </div>
            ' : '') . '
        </div>
        
        <div class="col-md-6">
            <h6><i class="fas fa-phone text-primary"></i> Contact Information</h6>
            ' . ($college['contact_number'] ? '
            <div class="mb-2">
                <strong>Phone:</strong> <span class="text-muted">' . htmlspecialchars($college['contact_number']) . '</span>
            </div>
            ' : '') . '
            
            ' . ($college['email'] ? '
            <div class="mb-2">
                <strong>Email:</strong> <span class="text-muted">' . htmlspecialchars($college['email']) . '</span>
            </div>
            ' : '') . '
            
            ' . ($college['website'] ? '
            <div class="mb-2">
                <strong>Website:</strong> <a href="' . htmlspecialchars($college['website']) . '" target="_blank" class="text-decoration-none">' . htmlspecialchars($college['website']) . ' <i class="fas fa-external-link-alt fa-sm"></i></a>
            </div>
            ' : '') . '
        </div>
    </div>';

    echo json_encode(['success' => true, 'html' => $html]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
