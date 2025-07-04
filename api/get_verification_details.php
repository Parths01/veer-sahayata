<?php
require_once '../config/database.php';
requireLogin();
requireAdmin();

$verificationId = $_GET['id'] ?? '';

if (!$verificationId) {
    echo json_encode(['success' => false, 'message' => 'Verification ID required']);
    exit;
}

try {
    // Get verification details
    $stmt = $pdo->prepare("
        SELECT v.*, u.service_number, u.full_name, u.service_type, u.rank, u.email, u.phone,
               admin.full_name as verified_by_name
        FROM user_verifications v 
        JOIN users u ON v.user_id = u.id 
        LEFT JOIN users admin ON v.verified_by = admin.id
        WHERE v.id = ?
    ");
    $stmt->execute([$verificationId]);
    $verification = $stmt->fetch();

    if (!$verification) {
        echo json_encode(['success' => false, 'message' => 'Verification not found']);
        exit;
    }

    // Get user's documents
    $stmt = $pdo->prepare("
        SELECT * FROM verification_documents 
        WHERE user_id = ? 
        ORDER BY document_type ASC
    ");
    $stmt->execute([$verification['user_id']]);
    $documents = $stmt->fetchAll();

    // Document type labels
    $documentTypes = [
        'service_certificate' => 'Service/Discharge Certificate',
        'identity_proof' => 'Identity Proof (Aadhar/PAN/Passport)',
        'address_proof' => 'Address Proof',
        'bank_details' => 'Bank Account Details',
        'medical_certificate' => 'Medical Fitness Certificate',
        'nok_details' => 'Next of Kin Details',
        'pension_order' => 'Pension Order',
        'photograph' => 'Recent Passport Size Photograph',
        'live_photo' => 'Live Verification Photo'
    ];

    // Get live photo specifically for live verification
    $livePhoto = null;
    if ($verification['verification_type'] == 'live_verification') {
        $stmt = $pdo->prepare("
            SELECT * FROM verification_documents 
            WHERE user_id = ? AND document_type = 'live_photo'
            ORDER BY uploaded_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$verification['user_id']]);
        $livePhoto = $stmt->fetch();
    }

    // Get user's profile photo for comparison (if exists)
    $profilePhoto = null;
    $stmt = $pdo->prepare("
        SELECT * FROM verification_documents 
        WHERE user_id = ? AND document_type = 'photograph'
        ORDER BY uploaded_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$verification['user_id']]);
    $profilePhoto = $stmt->fetch();

    // Generate HTML content
    ob_start();
    ?>
    <div class="row">
        <div class="col-md-6">
            <h6 class="text-primary"><i class="fas fa-user"></i> User Information</h6>
            <table class="table table-sm">
                <tr>
                    <td><strong>Name:</strong></td>
                    <td><?php echo htmlspecialchars($verification['full_name']); ?></td>
                </tr>
                <tr>
                    <td><strong>Service Number:</strong></td>
                    <td><?php echo htmlspecialchars($verification['service_number']); ?></td>
                </tr>
                <tr>
                    <td><strong>Rank:</strong></td>
                    <td><?php echo htmlspecialchars($verification['rank'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td><strong>Service Type:</strong></td>
                    <td>
                        <span class="badge bg-<?php 
                            echo $verification['service_type'] == 'Army' ? 'success' : 
                                ($verification['service_type'] == 'Navy' ? 'primary' : 'info'); 
                        ?>">
                            <?php echo htmlspecialchars($verification['service_type']); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td><strong>Email:</strong></td>
                    <td><?php echo htmlspecialchars($verification['email']); ?></td>
                </tr>
                <tr>
                    <td><strong>Phone:</strong></td>
                    <td><?php echo htmlspecialchars($verification['phone']); ?></td>
                </tr>
            </table>
        </div>
        <div class="col-md-6">
            <h6 class="text-info"><i class="fas fa-shield-alt"></i> Verification Details</h6>
            <table class="table table-sm">
                <tr>
                    <td><strong>Type:</strong></td>
                    <td><?php echo ucfirst(str_replace('_', ' ', $verification['verification_type'])); ?></td>
                </tr>
                <tr>
                    <td><strong>Status:</strong></td>
                    <td>
                        <span class="badge bg-<?php 
                            echo $verification['status'] == 'approved' ? 'success' : 
                                ($verification['status'] == 'rejected' ? 'danger' : 
                                    ($verification['status'] == 'under_review' ? 'info' : 'warning')); 
                        ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $verification['status'])); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td><strong>Submitted:</strong></td>
                    <td><?php echo date('d M Y, h:i A', strtotime($verification['submitted_at'])); ?></td>
                </tr>
                <?php if ($verification['verified_at']): ?>
                <tr>
                    <td><strong>Reviewed:</strong></td>
                    <td><?php echo date('d M Y, h:i A', strtotime($verification['verified_at'])); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($verification['verified_by_name']): ?>
                <tr>
                    <td><strong>Reviewed By:</strong></td>
                    <td><?php echo htmlspecialchars($verification['verified_by_name']); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td><strong>Declaration:</strong></td>
                    <td>
                        <?php if ($verification['declaration_accepted']): ?>
                        <span class="text-success"><i class="fas fa-check-circle"></i> Accepted</span>
                        <?php else: ?>
                        <span class="text-danger"><i class="fas fa-times-circle"></i> Not Accepted</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <?php if ($verification['verification_type'] == 'live_verification' && $livePhoto): ?>
    <div class="mt-4">
        <h6 class="text-success"><i class="fas fa-camera"></i> Live Verification Photo Review</h6>
        <div class="row">
            <div class="col-md-6">
                <div class="card border-success">
                    <div class="card-header bg-success text-white text-center">
                        <h6 class="mb-0"><i class="fas fa-camera"></i> Submitted Live Photo</h6>
                    </div>
                    <div class="card-body text-center">
                        <img src="../<?php echo $livePhoto['file_path']; ?>" 
                             alt="Live Photo" 
                             class="img-fluid rounded"
                             style="max-height: 300px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                        <div class="mt-2">
                            <small class="text-muted">
                                Uploaded: <?php echo date('d M Y, h:i A', strtotime($livePhoto['uploaded_at'])); ?>
                            </small>
                        </div>
                        <div class="mt-2">
                            <a href="../<?php echo $livePhoto['file_path']; ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                <i class="fas fa-expand"></i> View Full Size
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <?php if ($profilePhoto): ?>
                <div class="card border-info">
                    <div class="card-header bg-info text-white text-center">
                        <h6 class="mb-0"><i class="fas fa-id-card"></i> Profile Photo (For Comparison)</h6>
                    </div>
                    <div class="card-body text-center">
                        <img src="../<?php echo $profilePhoto['file_path']; ?>" 
                             alt="Profile Photo" 
                             class="img-fluid rounded"
                             style="max-height: 300px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                        <div class="mt-2">
                            <small class="text-muted">
                                Uploaded: <?php echo date('d M Y, h:i A', strtotime($profilePhoto['uploaded_at'])); ?>
                            </small>
                        </div>
                        <div class="mt-2">
                            <a href="../<?php echo $profilePhoto['file_path']; ?>" class="btn btn-sm btn-outline-info" target="_blank">
                                <i class="fas fa-expand"></i> View Full Size
                            </a>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="card border-warning">
                    <div class="card-header bg-warning text-dark text-center">
                        <h6 class="mb-0"><i class="fas fa-exclamation-triangle"></i> No Profile Photo Available</h6>
                    </div>
                    <div class="card-body text-center">
                        <div class="text-muted py-5">
                            <i class="fas fa-user fa-4x mb-3"></i>
                            <p>No previous profile photo found for comparison</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="alert alert-info mt-3">
            <h6><i class="fas fa-info-circle"></i> Verification Instructions:</h6>
            <ul class="mb-0">
                <li><strong>Compare both photos</strong> to verify if it's the same person</li>
                <li><strong>Check for clear facial features</strong> and proper lighting</li>
                <li><strong>Verify the person appears alive</strong> and alert in the live photo</li>
                <li><strong>Look for any signs of manipulation</strong> or fake photos</li>
                <li>If unsure, request additional verification or reject with specific reasons</li>
            </ul>
        </div>
        
        <div class="row mt-3">
            <div class="col-md-6">
                <div class="d-grid">
                    <button class="btn btn-success btn-lg" onclick="approveVerification('<?php echo $verification['id']; ?>')">
                        <i class="fas fa-check-circle"></i> Verify as ALIVE
                    </button>
                </div>
            </div>
            <div class="col-md-6">
                <div class="d-grid">
                    <button class="btn btn-danger btn-lg" onclick="rejectVerification('<?php echo $verification['id']; ?>')">
                        <i class="fas fa-times-circle"></i> Reject Verification
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($verification['supporting_details']): ?>
    <div class="mt-3">
        <h6 class="text-warning"><i class="fas fa-info-circle"></i> Supporting Details</h6>
        <div class="bg-light p-3 rounded">
            <?php echo nl2br(htmlspecialchars($verification['supporting_details'])); ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($verification['resubmission_notes']): ?>
    <div class="mt-3">
        <h6 class="text-primary"><i class="fas fa-redo"></i> Resubmission Notes</h6>
        <div class="bg-info bg-opacity-10 p-3 rounded">
            <?php echo nl2br(htmlspecialchars($verification['resubmission_notes'])); ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($verification['admin_remarks']): ?>
    <div class="mt-3">
        <h6 class="text-danger"><i class="fas fa-comment"></i> Admin Remarks</h6>
        <div class="bg-danger bg-opacity-10 p-3 rounded">
            <?php echo nl2br(htmlspecialchars($verification['admin_remarks'])); ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="mt-4">
        <h6 class="text-success"><i class="fas fa-file-alt"></i> Uploaded Documents (<?php echo count($documents); ?>)</h6>
        <?php if (!empty($documents)): ?>
        <div class="row">
            <?php foreach ($documents as $doc): ?>
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-title mb-1">
                                    <?php echo $documentTypes[$doc['document_type']] ?? ucfirst(str_replace('_', ' ', $doc['document_type'])); ?>
                                </h6>
                                <small class="text-muted">
                                    <?php echo basename($doc['document_name']); ?><br>
                                    Uploaded: <?php echo date('d M Y', strtotime($doc['uploaded_at'])); ?>
                                </small>
                            </div>
                            <div>
                                <span class="badge bg-<?php 
                                    echo $doc['status'] == 'approved' ? 'success' : 
                                        ($doc['status'] == 'rejected' ? 'danger' : 'warning'); 
                                ?>">
                                    <?php echo ucfirst($doc['status']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="mt-2">
                            <a href="../<?php echo $doc['file_path']; ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a href="../<?php echo $doc['file_path']; ?>" class="btn btn-sm btn-outline-secondary" download>
                                <i class="fas fa-download"></i> Download
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> No documents uploaded yet.
        </div>
        <?php endif; ?>
    </div>
    <?php
    $html = ob_get_clean();

    echo json_encode(['success' => true, 'html' => $html]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
