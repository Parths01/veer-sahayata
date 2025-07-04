<?php
require_once '../config/database.php';
requireLogin();
requireAdmin();

$userId = $_GET['user_id'] ?? '';

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'User ID required']);
    exit;
}

try {
    // Get user details
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Get user's documents from verification_documents table
    $stmt = $pdo->prepare("
        SELECT * FROM verification_documents 
        WHERE user_id = ? 
        ORDER BY document_type ASC, uploaded_at DESC
    ");
    $stmt->execute([$userId]);
    $verificationDocuments = $stmt->fetchAll();

    // Get user's documents from regular documents table
    $stmt = $pdo->prepare("
        SELECT * FROM documents 
        WHERE user_id = ? 
        ORDER BY document_type ASC, uploaded_at DESC
    ");
    $stmt->execute([$userId]);
    $regularDocuments = $stmt->fetchAll();

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
        'aadhar' => 'Aadhar Card',
        'pan' => 'PAN Card',
        'driving_license' => 'Driving License',
        'electric_bill' => 'Electric Bill',
        'service_record' => 'Service Record',
        'other' => 'Other Document'
    ];

    // Generate HTML content
    ob_start();
    ?>
    <div class="row mb-3">
        <div class="col-12">
            <div class="alert alert-info">
                <h6 class="mb-2"><i class="fas fa-user"></i> <?php echo htmlspecialchars($user['full_name']); ?></h6>
                <p class="mb-0">
                    <strong>Service Number:</strong> <?php echo htmlspecialchars($user['service_number']); ?> |
                    <strong>Service Type:</strong> <?php echo htmlspecialchars($user['service_type']); ?> |
                    <strong>Rank:</strong> <?php echo htmlspecialchars($user['rank'] ?? 'N/A'); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Verification Documents -->
    <?php if (!empty($verificationDocuments)): ?>
    <div class="mb-4">
        <h6 class="text-primary"><i class="fas fa-shield-alt"></i> Verification Documents (<?php echo count($verificationDocuments); ?>)</h6>
        <div class="row">
            <?php foreach ($verificationDocuments as $doc): ?>
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card h-100">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="card-title mb-0">
                                <?php echo $documentTypes[$doc['document_type']] ?? ucfirst(str_replace('_', ' ', $doc['document_type'])); ?>
                            </h6>
                            <span class="badge bg-<?php 
                                echo $doc['status'] == 'approved' ? 'success' : 
                                    ($doc['status'] == 'rejected' ? 'danger' : 'warning'); 
                            ?>">
                                <?php echo ucfirst($doc['status']); ?>
                            </span>
                        </div>
                        
                        <small class="text-muted">
                            <?php echo basename($doc['document_name']); ?><br>
                            <i class="fas fa-calendar"></i> <?php echo date('d M Y, h:i A', strtotime($doc['uploaded_at'])); ?><br>
                            <i class="fas fa-hdd"></i> <?php echo formatFileSize($doc['file_size']); ?>
                        </small>
                        
                        <?php if ($doc['admin_remarks']): ?>
                        <div class="mt-2">
                            <small class="text-muted" title="<?php echo htmlspecialchars($doc['admin_remarks']); ?>">
                                <i class="fas fa-comment"></i> Has admin remarks
                            </small>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <div class="btn-group w-100" role="group">
                                <a href="../<?php echo $doc['file_path']; ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="../<?php echo $doc['file_path']; ?>" class="btn btn-sm btn-outline-secondary" download>
                                    <i class="fas fa-download"></i> Download
                                </a>
                                <?php if ($doc['status'] == 'pending'): ?>
                                <button class="btn btn-sm btn-success" onclick="approveDocument('<?php echo $doc['id']; ?>', 'verification')">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="rejectDocument('<?php echo $doc['id']; ?>', 'verification')">
                                    <i class="fas fa-times"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Regular Documents -->
    <?php if (!empty($regularDocuments)): ?>
    <div class="mb-4">
        <h6 class="text-secondary"><i class="fas fa-file-alt"></i> Other Documents (<?php echo count($regularDocuments); ?>)</h6>
        <div class="row">
            <?php foreach ($regularDocuments as $doc): ?>
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card h-100">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="card-title mb-0">
                                <?php echo $documentTypes[$doc['document_type']] ?? ucfirst(str_replace('_', ' ', $doc['document_type'])); ?>
                            </h6>
                            <span class="badge bg-<?php 
                                echo $doc['status'] == 'approved' ? 'success' : 
                                    ($doc['status'] == 'rejected' ? 'danger' : 'warning'); 
                            ?>">
                                <?php echo ucfirst($doc['status']); ?>
                            </span>
                        </div>
                        
                        <small class="text-muted">
                            <?php echo basename($doc['file_path']); ?><br>
                            <i class="fas fa-calendar"></i> <?php echo date('d M Y, h:i A', strtotime($doc['uploaded_at'])); ?>
                        </small>
                        
                        <?php if (isset($doc['admin_remarks']) && $doc['admin_remarks']): ?>
                        <div class="mt-2">
                            <small class="text-muted" title="<?php echo htmlspecialchars($doc['admin_remarks']); ?>">
                                <i class="fas fa-comment"></i> Has admin remarks
                            </small>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <div class="btn-group w-100" role="group">
                                <a href="../<?php echo $doc['file_path']; ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="../<?php echo $doc['file_path']; ?>" class="btn btn-sm btn-outline-secondary" download>
                                    <i class="fas fa-download"></i> Download
                                </a>
                                <?php if ($doc['status'] == 'pending'): ?>
                                <button class="btn btn-sm btn-success" onclick="approveDocument('<?php echo $doc['id']; ?>', 'regular')">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="rejectDocument('<?php echo $doc['id']; ?>', 'regular')">
                                    <i class="fas fa-times"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($verificationDocuments) && empty($regularDocuments)): ?>
    <div class="alert alert-warning text-center">
        <i class="fas fa-file-alt fa-3x mb-3"></i>
        <h5>No Documents Found</h5>
        <p class="mb-0">This user hasn't uploaded any documents yet.</p>
    </div>
    <?php endif; ?>

    <script>
        function approveDocument(documentId, type) {
            if (confirm('Are you sure you want to approve this document?')) {
                const formData = new FormData();
                formData.append('action', type === 'verification' ? 'approve_document' : 'approve_document');
                formData.append('document_id', documentId);
                
                const endpoint = type === 'verification' ? 'verifications.php' : 'documents.php';
                
                fetch('../admin/' + endpoint, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    alert('Document approved successfully');
                    location.reload();
                })
                .catch(error => {
                    alert('Error approving document');
                });
            }
        }

        function rejectDocument(documentId, type) {
            const reason = prompt('Please enter the reason for rejection:');
            if (reason && reason.trim()) {
                const formData = new FormData();
                formData.append('action', type === 'verification' ? 'reject_document' : 'reject_document');
                formData.append('document_id', documentId);
                formData.append('remarks', reason);
                
                const endpoint = type === 'verification' ? 'verifications.php' : 'documents.php';
                
                fetch('../admin/' + endpoint, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    alert('Document rejected successfully');
                    location.reload();
                })
                .catch(error => {
                    alert('Error rejecting document');
                });
            }
        }
    </script>
    <?php
    $html = ob_get_clean();

    echo json_encode(['success' => true, 'html' => $html]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function formatFileSize($size) {
    $units = array('B', 'KB', 'MB', 'GB');
    $power = $size > 0 ? floor(log($size, 1024)) : 0;
    return number_format($size / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
}
?>
