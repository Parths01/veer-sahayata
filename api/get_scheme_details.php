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

    // Generate HTML for scheme details
    $html = '
    <div class="scheme-details">
        <div class="row mb-3">
            <div class="col-md-8">
                <h6 class="text-primary">Scheme Name</h6>
                <p class="fw-bold">' . htmlspecialchars($scheme['scheme_name']) . '</p>
            </div>
            <div class="col-md-4">
                <h6 class="text-primary">Scheme Type</h6>
                <span class="badge bg-' . ($scheme['scheme_type'] == 'Central' ? 'primary' : 'success') . ' fs-6">
                    ' . htmlspecialchars($scheme['scheme_type']) . '
                </span>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-8">
                <h6 class="text-primary">Status</h6>
                <span class="badge bg-' . ($scheme['status'] == 'active' ? 'success' : 'secondary') . ' fs-6">
                    ' . ucfirst($scheme['status']) . '
                </span>
            </div>
            <div class="col-md-4">
                <h6 class="text-primary">Created Date</h6>
                <p>' . date('d F Y, H:i', strtotime($scheme['created_at'])) . '</p>
            </div>
        </div>

        <div class="mb-3">
            <h6 class="text-primary">Description</h6>
            <p class="text-justify">' . htmlspecialchars($scheme['description']) . '</p>
        </div>';

    if (!empty($scheme['eligibility_criteria'])) {
        $html .= '
        <div class="mb-3">
            <h6 class="text-primary">Eligibility Criteria</h6>
            <div class="border-start border-primary border-3 ps-3">
                <p class="text-justify">' . nl2br(htmlspecialchars($scheme['eligibility_criteria'])) . '</p>
            </div>
        </div>';
    }

    if (!empty($scheme['application_process'])) {
        $html .= '
        <div class="mb-3">
            <h6 class="text-primary">Application Process</h6>
            <div class="border-start border-success border-3 ps-3">
                <p class="text-justify">' . nl2br(htmlspecialchars($scheme['application_process'])) . '</p>
            </div>
        </div>';
    }

    if (!empty($scheme['documents_required'])) {
        $html .= '
        <div class="mb-3">
            <h6 class="text-primary">Documents Required</h6>
            <div class="border-start border-warning border-3 ps-3">
                <p class="text-justify">' . nl2br(htmlspecialchars($scheme['documents_required'])) . '</p>
            </div>
        </div>';
    }

    if (!empty($scheme['benefits'])) {
        $html .= '
        <div class="mb-3">
            <h6 class="text-primary">Benefits</h6>
            <div class="border-start border-info border-3 ps-3">
                <p class="text-justify">' . nl2br(htmlspecialchars($scheme['benefits'])) . '</p>
            </div>
        </div>';
    }

    $html .= '</div>';

    echo json_encode([
        'success' => true, 
        'html' => $html,
        'scheme' => $scheme
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
