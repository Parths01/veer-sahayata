<?php
session_start();
require_once '../config/database.php';
requireLogin();
requireAdmin();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'approve_verification':
                $result = approveVerification($_POST['verification_id'], $_POST['remarks'] ?? '');
                break;
            case 'reject_verification':
                $result = rejectVerification($_POST['verification_id'], $_POST['remarks']);
                break;
            case 'bulk_action':
                $result = bulkVerificationAction($_POST['verifications'], $_POST['bulk_action'], $_POST['bulk_remarks'] ?? '');
                break;
            case 'approve_document':
                $result = approveDocument($_POST['document_id'], $_POST['remarks'] ?? '');
                break;
            case 'reject_document':
                $result = rejectDocument($_POST['document_id'], $_POST['remarks']);
                break;
        }
    }
}

// Get verifications with pagination and filters
$status = $_GET['status'] ?? '';
$verification_type = $_GET['verification_type'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$sql = "SELECT v.*, u.service_number, u.full_name, u.service_type,
               admin.full_name as verified_by_name
        FROM user_verifications v 
        JOIN users u ON v.user_id = u.id 
        LEFT JOIN users admin ON v.verified_by = admin.id
        WHERE 1=1";
$params = [];

if ($status) {
    $sql .= " AND v.status = ?";
    $params[] = $status;
}

if ($verification_type) {
    $sql .= " AND v.verification_type = ?";
    $params[] = $verification_type;
}

if ($search) {
    $sql .= " AND (u.service_number LIKE ? OR u.full_name LIKE ? OR v.verification_type LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

$sql .= " ORDER BY v.submitted_at DESC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$verifications = $stmt->fetchAll();

// Get total count for pagination
$countSql = str_replace("SELECT v.*, u.service_number, u.full_name, u.service_type, u.rank, admin.full_name as verified_by_name", "SELECT COUNT(*)", explode("ORDER BY", $sql)[0]);
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalVerifications = $stmt->fetchColumn();
$totalPages = ceil($totalVerifications / $limit);

// Get verification statistics
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM user_verifications")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM user_verifications WHERE status = 'pending'")->fetchColumn(),
    'under_review' => $pdo->query("SELECT COUNT(*) FROM user_verifications WHERE status = 'under_review'")->fetchColumn(),
    'approved' => $pdo->query("SELECT COUNT(*) FROM user_verifications WHERE status = 'approved'")->fetchColumn(),
    'rejected' => $pdo->query("SELECT COUNT(*) FROM user_verifications WHERE status = 'rejected'")->fetchColumn()
];

// Functions
function approveVerification($verificationId, $remarks = '') {
    global $pdo;
    try {
        $pdo->beginTransaction();
        
        // Update verification status
        $stmt = $pdo->prepare("UPDATE user_verifications SET status = 'approved', admin_remarks = ?, verified_at = NOW(), verified_by = ? WHERE id = ?");
        $stmt->execute([$remarks, $_SESSION['user_id'], $verificationId]);
        
        // Also approve all documents for this user
        $stmt = $pdo->prepare("
            UPDATE verification_documents 
            SET status = 'approved', reviewed_at = NOW(), reviewed_by = ? 
            WHERE user_id = (SELECT user_id FROM user_verifications WHERE id = ?)
            AND status = 'pending'
        ");
        $stmt->execute([$_SESSION['user_id'], $verificationId]);
        
        $pdo->commit();
        return ['success' => true, 'message' => 'Verification approved successfully'];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function rejectVerification($verificationId, $remarks) {
    global $pdo;
    try {
        if (empty($remarks)) {
            throw new Exception("Rejection reason is required");
        }
        
        $stmt = $pdo->prepare("UPDATE user_verifications SET status = 'rejected', admin_remarks = ?, verified_at = NOW(), verified_by = ? WHERE id = ?");
        $stmt->execute([$remarks, $_SESSION['user_id'], $verificationId]);
        
        return ['success' => true, 'message' => 'Verification rejected successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function bulkVerificationAction($verificationIds, $action, $remarks = '') {
    global $pdo;
    try {
        if (empty($verificationIds)) {
            throw new Exception("No verifications selected");
        }
        
        if ($action == 'rejected' && empty($remarks)) {
            throw new Exception("Rejection reason is required for bulk rejection");
        }
        
        $placeholders = str_repeat('?,', count($verificationIds) - 1) . '?';
        $params = array_merge([$action, $remarks, $_SESSION['user_id']], $verificationIds);
        
        $stmt = $pdo->prepare("UPDATE user_verifications SET status = ?, admin_remarks = ?, verified_at = NOW(), verified_by = ? WHERE id IN ($placeholders)");
        $stmt->execute($params);
        
        $count = count($verificationIds);
        return ['success' => true, 'message' => "$count verifications $action successfully"];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function approveDocument($documentId, $remarks = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("UPDATE verification_documents SET status = 'approved', admin_remarks = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
        $stmt->execute([$remarks, $_SESSION['user_id'], $documentId]);
        
        return ['success' => true, 'message' => 'Document approved successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function rejectDocument($documentId, $remarks) {
    global $pdo;
    try {
        if (empty($remarks)) {
            throw new Exception("Rejection reason is required");
        }
        
        $stmt = $pdo->prepare("UPDATE verification_documents SET status = 'rejected', admin_remarks = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
        $stmt->execute([$remarks, $_SESSION['user_id'], $documentId]);
        
        return ['success' => true, 'message' => 'Document rejected successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Get verification types for filter
$verificationTypes = [
    'new_registration' => 'New Registration',
    'document_update' => 'Document Update',
    'profile_verification' => 'Profile Verification',
    'service_verification' => 'Service Record Verification',
    'pension_verification' => 'Pension Eligibility Verification',
    'live_verification' => 'Live/Alive Verification'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Management - Veer Sahayata Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .verification-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
            margin-bottom: 20px;
        }
        .verification-card:hover {
            transform: translateY(-2px);
        }
        .status-badge {
            font-size: 0.8em;
            padding: 6px 12px;
            border-radius: 20px;
        }
        .verification-actions {
            white-space: nowrap;
        }
        .bulk-actions {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: none;
        }
        .document-thumbnail {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 6px;
        }
        .verification-details {
            font-size: 0.9em;
        }
        .timeline-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .priority-urgent { background: #dc3545; }
        .priority-high { background: #fd7e14; }
        .priority-normal { background: #28a745; }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-shield-alt text-primary"></i> Verification Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-sm btn-outline-secondary" onclick="toggleBulkActions()">
                                <i class="fas fa-tasks"></i> Bulk Actions
                            </button>
                            <button class="btn btn-sm btn-outline-primary" onclick="refreshPage()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                            <a href="documents.php" class="btn btn-sm btn-outline-info">
                                <i class="fas fa-file-alt"></i> Manage Documents
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Display Messages -->
                <?php if (isset($result)): ?>
                <div class="alert alert-<?php echo $result['success'] ? 'success' : 'danger'; ?> alert-dismissible fade show">
                    <?php echo $result['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Verification Statistics -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h4><?php echo $stats['total']; ?></h4>
                                <p class="mb-0">Total</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-warning text-dark">
                            <div class="card-body text-center">
                                <h4><?php echo $stats['pending']; ?></h4>
                                <p class="mb-0">Pending</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h4><?php echo $stats['under_review']; ?></h4>
                                <p class="mb-0">Under Review</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h4><?php echo $stats['approved']; ?></h4>
                                <p class="mb-0">Approved</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <h4><?php echo $stats['rejected']; ?></h4>
                                <p class="mb-0">Rejected</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-secondary text-white">
                            <div class="card-body text-center">
                                <h4><?php echo round(($stats['approved'] / max(1, $stats['total'])) * 100); ?>%</h4>
                                <p class="mb-0">Success Rate</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search by service number, name, or type...">
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="under_review" <?php echo $status == 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                                    <option value="approved" <?php echo $status == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo $status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="verification_type" class="form-label">Verification Type</label>
                                <select class="form-select" id="verification_type" name="verification_type">
                                    <option value="">All Types</option>
                                    <?php foreach ($verificationTypes as $type => $label): ?>
                                    <option value="<?php echo $type; ?>" <?php echo $verification_type == $type ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                                <a href="verifications.php" class="btn btn-secondary">
                                    <i class="fas fa-undo"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Bulk Actions Panel -->
                <div class="bulk-actions" id="bulkActionsPanel">
                    <form method="POST" id="bulkActionForm">
                        <input type="hidden" name="action" value="bulk_action">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">Selected Verifications</label>
                                <input type="text" class="form-control" id="selectedCount" readonly value="0 selected">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Action</label>
                                <select class="form-select" name="bulk_action" id="bulkActionSelect" required>
                                    <option value="">Choose Action</option>
                                    <option value="approved">Approve All</option>
                                    <option value="rejected">Reject All</option>
                                    <option value="under_review">Mark Under Review</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Remarks</label>
                                <input type="text" class="form-control" name="bulk_remarks" placeholder="Optional remarks">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary">Apply</button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Verifications Table -->
                <div class="card">
                    <div class="card-header">
                        <h5>Verification Requests (<?php echo $totalVerifications; ?> total)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($verifications)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>
                                            <input type="checkbox" id="selectAll" onchange="toggleAllVerifications()">
                                        </th>
                                        <th>User Details</th>
                                        <th>Verification Type</th>
                                        <th>Status</th>
                                        <th>Submitted</th>
                                        <th>Priority</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($verifications as $verification): ?>
                                    <?php
                                    // Calculate priority based on submission date and type
                                    $submissionDate = new DateTime($verification['submitted_at']);
                                    $now = new DateTime();
                                    $daysDiff = $now->diff($submissionDate)->days;
                                    
                                    if ($daysDiff > 7) $priority = 'urgent';
                                    elseif ($daysDiff > 3) $priority = 'high';
                                    else $priority = 'normal';
                                    ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="verification-checkbox" value="<?php echo $verification['id']; ?>" 
                                                   onchange="updateSelectedCount()">
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($verification['full_name']); ?></strong><br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($verification['service_number']); ?><br>
                                                    <span class="badge bg-<?php 
                                                        echo $verification['service_type'] == 'Army' ? 'success' : 
                                                            ($verification['service_type'] == 'Navy' ? 'primary' : 'info'); 
                                                    ?> badge-sm">
                                                        <?php echo htmlspecialchars($verification['rank'] ?? 'N/A'); ?> - <?php echo htmlspecialchars($verification['service_type']); ?>
                                                    </span>
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($verification['verification_type'] == 'live_verification'): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-heartbeat"></i> <?php echo $verificationTypes[$verification['verification_type']]; ?>
                                            </span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <?php echo $verificationTypes[$verification['verification_type']] ?? ucfirst(str_replace('_', ' ', $verification['verification_type'])); ?>
                                            </span>
                                            <?php endif; ?>
                                            <?php if ($verification['supporting_details']): ?>
                                            <br><small class="text-muted" title="<?php echo htmlspecialchars($verification['supporting_details']); ?>">
                                                <i class="fas fa-info-circle"></i> Has details
                                            </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge status-badge bg-<?php 
                                                echo $verification['status'] == 'approved' ? 'success' : 
                                                    ($verification['status'] == 'rejected' ? 'danger' : 
                                                        ($verification['status'] == 'under_review' ? 'info' : 'warning')); 
                                            ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $verification['status'])); ?>
                                            </span>
                                            <?php if ($verification['admin_remarks']): ?>
                                            <br><small class="text-muted" title="<?php echo htmlspecialchars($verification['admin_remarks']); ?>">
                                                <i class="fas fa-comment"></i> Has remarks
                                            </small>
                                            <?php endif; ?>
                                            <?php if ($verification['verified_by_name']): ?>
                                            <br><small class="text-muted">By: <?php echo htmlspecialchars($verification['verified_by_name']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small>
                                                <?php echo date('d/m/Y', strtotime($verification['submitted_at'])); ?><br>
                                                <?php echo date('H:i', strtotime($verification['submitted_at'])); ?>
                                                <?php if ($verification['verified_at']): ?>
                                                <br><span class="text-success">Reviewed: <?php echo date('d/m/Y', strtotime($verification['verified_at'])); ?></span>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="timeline-indicator priority-<?php echo $priority; ?>" title="<?php echo ucfirst($priority); ?> priority"></span>
                                            <small><?php echo $daysDiff; ?> day<?php echo $daysDiff != 1 ? 's' : ''; ?> ago</small>
                                        </td>
                                        <td>
                                            <div class="verification-actions">
                                                <button class="btn btn-sm btn-info" onclick="viewVerificationDetails('<?php echo $verification['id']; ?>')" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($verification['verification_type'] == 'live_verification'): ?>
                                                <button class="btn btn-sm btn-warning" onclick="viewLivePhoto('<?php echo $verification['user_id']; ?>')" title="View Live Photo">
                                                    <i class="fas fa-camera"></i>
                                                </button>
                                                <?php else: ?>
                                                <button class="btn btn-sm btn-secondary" onclick="viewUserDocuments('<?php echo $verification['user_id']; ?>')" title="View Documents">
                                                    <i class="fas fa-folder-open"></i>
                                                </button>
                                                <?php endif; ?>
                                                <?php if (in_array($verification['status'], ['pending', 'under_review'])): ?>
                                                <?php if ($verification['verification_type'] == 'live_verification'): ?>
                                                <button class="btn btn-sm btn-success" onclick="approveVerification('<?php echo $verification['id']; ?>')" title="Verify as ALIVE">
                                                    <i class="fas fa-heartbeat"></i>
                                                </button>
                                                <?php else: ?>
                                                <button class="btn btn-sm btn-success" onclick="approveVerification('<?php echo $verification['id']; ?>')" title="Approve">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-danger" onclick="rejectVerification('<?php echo $verification['id']; ?>')" title="Reject">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <nav aria-label="Verifications pagination" class="mt-3">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status); ?>&verification_type=<?php echo urlencode($verification_type); ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                                </li>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&verification_type=<?php echo urlencode($verification_type); ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status); ?>&verification_type=<?php echo urlencode($verification_type); ?>&search=<?php echo urlencode($search); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>

                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-shield-alt fa-3x text-muted mb-3"></i>
                            <h5>No Verification Requests Found</h5>
                            <p class="text-muted">
                                <?php echo $search ? 'No verifications match your search criteria.' : 'No verification requests have been submitted yet.'; ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Verification Details Modal -->
    <div class="modal fade" id="verificationDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Verification Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="verificationDetailsContent">
                    <!-- Content will be loaded dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- User Documents Modal -->
    <div class="modal fade" id="userDocumentsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">User Documents</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="userDocumentsContent">
                    <!-- Content will be loaded dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Approve Verification Modal -->
    <div class="modal fade" id="approveVerificationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Approve Verification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="approve_verification">
                        <input type="hidden" name="verification_id" id="approveVerificationId">
                        
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            Approving this verification will also approve all pending documents for this user.
                        </div>
                        
                        <div class="mb-3">
                            <label for="approveRemarks" class="form-label">Approval Remarks (Optional)</label>
                            <textarea class="form-control" id="approveRemarks" name="remarks" rows="3" 
                                      placeholder="Add any comments about this approval..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check"></i> Approve Verification
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Verification Modal -->
    <div class="modal fade" id="rejectVerificationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Verification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reject_verification">
                        <input type="hidden" name="verification_id" id="rejectVerificationId">
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            Please provide a detailed reason for rejecting this verification. This will help the user understand what needs to be corrected.
                        </div>
                        
                        <div class="mb-3">
                            <label for="rejectRemarks" class="form-label">Rejection Reason *</label>
                            <textarea class="form-control" id="rejectRemarks" name="remarks" rows="4" required
                                      placeholder="Please specify the detailed reason for rejection..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times"></i> Reject Verification
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedVerifications = [];

        function toggleBulkActions() {
            const panel = document.getElementById('bulkActionsPanel');
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        }

        function toggleAllVerifications() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.verification-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.verification-checkbox:checked');
            selectedVerifications = Array.from(checkboxes).map(cb => cb.value);
            
            document.getElementById('selectedCount').value = selectedVerifications.length + ' selected';
            
            // Update hidden input for bulk action form
            const existingInputs = document.querySelectorAll('input[name="verifications[]"]');
            existingInputs.forEach(input => input.remove());
            
            selectedVerifications.forEach(verificationId => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'verifications[]';
                input.value = verificationId;
                document.getElementById('bulkActionForm').appendChild(input);
            });
        }

        function viewVerificationDetails(verificationId) {
            fetch('../api/get_verification_details.php?id=' + verificationId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('verificationDetailsContent').innerHTML = data.html;
                        new bootstrap.Modal(document.getElementById('verificationDetailsModal')).show();
                    } else {
                        alert('Error loading verification details');
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        }

        function viewUserDocuments(userId) {
            fetch('../api/get_user_documents.php?user_id=' + userId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('userDocumentsContent').innerHTML = data.html;
                        new bootstrap.Modal(document.getElementById('userDocumentsModal')).show();
                    } else {
                        alert('Error loading user documents');
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        }

        function viewLivePhoto(userId) {
            // First get verification details, then show the modal
            fetch('../api/get_user_documents.php?user_id=' + userId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('userDocumentsContent').innerHTML = data.html;
                        new bootstrap.Modal(document.getElementById('userDocumentsModal')).show();
                    } else {
                        alert('Error loading live photo');
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        }

        function approveVerification(verificationId) {
            document.getElementById('approveVerificationId').value = verificationId;
            new bootstrap.Modal(document.getElementById('approveVerificationModal')).show();
        }

        function rejectVerification(verificationId) {
            document.getElementById('rejectVerificationId').value = verificationId;
            new bootstrap.Modal(document.getElementById('rejectVerificationModal')).show();
        }

        function refreshPage() {
            window.location.reload();
        }

        // Auto-hide success messages
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-success');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
