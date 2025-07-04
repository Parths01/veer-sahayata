<?php
session_start();
require_once '../config/database.php';
requireLogin();
requireAdmin();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'approve_document':
                $result = approveDocument($_POST['document_id'], $_POST['remarks'] ?? '');
                break;
            case 'reject_document':
                $result = rejectDocument($_POST['document_id'], $_POST['remarks']);
                break;
            case 'bulk_action':
                $result = bulkDocumentAction($_POST['documents'], $_POST['bulk_action'], $_POST['bulk_remarks'] ?? '');
                break;
        }
    }
}

// Get documents with pagination and filters
$status = $_GET['status'] ?? '';
$document_type = $_GET['document_type'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$sql = "SELECT d.*, u.service_number, u.full_name, u.service_type 
        FROM documents d 
        JOIN users u ON d.user_id = u.id 
        WHERE 1=1";
$params = [];

if ($status) {
    $sql .= " AND d.status = ?";
    $params[] = $status;
}

if ($document_type) {
    $sql .= " AND d.document_type = ?";
    $params[] = $document_type;
}

if ($search) {
    $sql .= " AND (u.service_number LIKE ? OR u.full_name LIKE ? OR d.document_type LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

$sql .= " ORDER BY d.uploaded_at DESC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$documents = $stmt->fetchAll();

// Get total count for pagination
$countSql = str_replace("SELECT d.*, u.service_number, u.full_name, u.service_type", "SELECT COUNT(*)", explode("ORDER BY", $sql)[0]);
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalDocuments = $stmt->fetchColumn();
$totalPages = ceil($totalDocuments / $limit);

// Get document statistics
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM documents WHERE status = 'pending'")->fetchColumn(),
    'approved' => $pdo->query("SELECT COUNT(*) FROM documents WHERE status = 'approved'")->fetchColumn(),
    'rejected' => $pdo->query("SELECT COUNT(*) FROM documents WHERE status = 'rejected'")->fetchColumn()
];

// Functions
function approveDocument($documentId, $remarks = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("UPDATE documents SET status = 'approved', admin_remarks = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
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
        
        $stmt = $pdo->prepare("UPDATE documents SET status = 'rejected', admin_remarks = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
        $stmt->execute([$remarks, $_SESSION['user_id'], $documentId]);
        
        return ['success' => true, 'message' => 'Document rejected successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function bulkDocumentAction($documentIds, $action, $remarks = '') {
    global $pdo;
    try {
        if (empty($documentIds)) {
            throw new Exception("No documents selected");
        }
        
        if ($action == 'reject' && empty($remarks)) {
            throw new Exception("Rejection reason is required for bulk rejection");
        }
        
        $placeholders = str_repeat('?,', count($documentIds) - 1) . '?';
        $params = array_merge([$action, $remarks, $_SESSION['user_id']], $documentIds);
        
        $stmt = $pdo->prepare("UPDATE documents SET status = ?, admin_remarks = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id IN ($placeholders)");
        $stmt->execute($params);
        
        $count = count($documentIds);
        return ['success' => true, 'message' => "$count documents $action successfully"];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Get document types for filter
$documentTypes = $pdo->query("SELECT DISTINCT document_type FROM documents ORDER BY document_type")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Management - Veer Sahayata Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .document-thumbnail {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        .status-badge {
            font-size: 0.8em;
        }
        .document-actions {
            white-space: nowrap;
        }
        .bulk-actions {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: none;
        }
        .document-preview {
            max-width: 100%;
            max-height: 500px;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Document Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-sm btn-outline-secondary" onclick="toggleBulkActions()">
                                <i class="fas fa-tasks"></i> Bulk Actions
                            </button>
                            <button class="btn btn-sm btn-outline-primary" onclick="refreshPage()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
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

                <!-- Document Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h4><?php echo $stats['total']; ?></h4>
                                <p>Total Documents</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <h4><?php echo $stats['pending']; ?></h4>
                                <p>Pending Review</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h4><?php echo $stats['approved']; ?></h4>
                                <p>Approved</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <h4><?php echo $stats['rejected']; ?></h4>
                                <p>Rejected</p>
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
                                       placeholder="Search by service number, name, or document type...">
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $status == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo $status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="document_type" class="form-label">Document Type</label>
                                <select class="form-select" id="document_type" name="document_type">
                                    <option value="">All Types</option>
                                    <?php foreach ($documentTypes as $type): ?>
                                    <option value="<?php echo $type; ?>" <?php echo $document_type == $type ? 'selected' : ''; ?>>
                                        <?php echo ucfirst(str_replace('_', ' ', $type)); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                                <a href="document.php" class="btn btn-secondary">
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
                                <label class="form-label">Selected Documents</label>
                                <input type="text" class="form-control" id="selectedCount" readonly value="0 selected">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Action</label>
                                <select class="form-select" name="bulk_action" id="bulkActionSelect" required>
                                    <option value="">Choose Action</option>
                                    <option value="approved">Approve All</option>
                                    <option value="rejected">Reject All</option>
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

                <!-- Documents Table -->
                <div class="card">
                    <div class="card-header">
                        <h5>Documents List (<?php echo $totalDocuments; ?> total)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($documents)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>
                                            <input type="checkbox" id="selectAll" onchange="toggleAllDocuments()">
                                        </th>
                                        <th>Document</th>
                                        <th>User Details</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Uploaded</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($documents as $doc): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="document-checkbox" value="<?php echo $doc['id']; ?>" 
                                                   onchange="updateSelectedCount()">
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php 
                                                $fileExtension = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
                                                $isImage = in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif']);
                                                ?>
                                                
                                                <?php if ($isImage): ?>
                                                <img src="../<?php echo $doc['file_path']; ?>" 
                                                     alt="Document" class="document-thumbnail me-2"
                                                     onclick="previewDocument('<?php echo $doc['id']; ?>')">
                                                <?php else: ?>
                                                <div class="document-thumbnail me-2 d-flex align-items-center justify-content-center bg-light border">
                                                    <i class="fas fa-file-<?php echo $fileExtension == 'pdf' ? 'pdf' : 'alt'; ?> fa-2x text-muted"></i>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <div>
                                                    <strong><?php echo basename($doc['file_path']); ?></strong><br>
                                                    <small class="text-muted">
                                                        <?php 
                                                        $fullPath = '../' . $doc['file_path'];
                                                        echo file_exists($fullPath) ? formatFileSize(filesize($fullPath)) : 'File not found';
                                                        ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo $doc['full_name']; ?></strong><br>
                                                <small class="text-muted">
                                                    <?php echo $doc['service_number']; ?><br>
                                                    <span class="badge bg-<?php 
                                                        echo $doc['service_type'] == 'Army' ? 'success' : 
                                                            ($doc['service_type'] == 'Navy' ? 'primary' : 'info'); 
                                                    ?> badge-sm">
                                                        <?php echo $doc['service_type']; ?>
                                                    </span>
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo ucfirst(str_replace('_', ' ', $doc['document_type'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge status-badge bg-<?php 
                                                echo $doc['status'] == 'approved' ? 'success' : 
                                                    ($doc['status'] == 'rejected' ? 'danger' : 'warning'); 
                                            ?>">
                                                <?php echo ucfirst($doc['status']); ?>
                                            </span>
                                            <?php if (isset($doc['admin_remarks']) && !empty($doc['admin_remarks'])): ?>
                                            <br><small class="text-muted" title="<?php echo htmlspecialchars($doc['admin_remarks']); ?>">
                                                <i class="fas fa-comment"></i> Has remarks
                                            </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small>
                                                <?php echo date('d/m/Y', strtotime($doc['uploaded_at'])); ?><br>
                                                <?php echo date('H:i', strtotime($doc['uploaded_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="document-actions">
                                                <button class="btn btn-sm btn-info" onclick="previewDocument('<?php echo $doc['id']; ?>')" title="Preview">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <a href="../<?php echo $doc['file_path']; ?>" class="btn btn-sm btn-secondary" 
                                                   download title="Download">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <?php if ($doc['status'] == 'pending'): ?>
                                                <button class="btn btn-sm btn-success" onclick="approveDocument('<?php echo $doc['id']; ?>')" title="Approve">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="rejectDocument('<?php echo $doc['id']; ?>')" title="Reject">
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
                        <nav aria-label="Documents pagination" class="mt-3">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status); ?>&document_type=<?php echo urlencode($document_type); ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                                </li>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&document_type=<?php echo urlencode($document_type); ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status); ?>&document_type=<?php echo urlencode($document_type); ?>&search=<?php echo urlencode($search); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>

                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                            <h5>No Documents Found</h5>
                            <p class="text-muted">
                                <?php echo $search ? 'No documents match your search criteria.' : 'No documents have been uploaded yet.'; ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Document Preview Modal -->
    <div class="modal fade" id="documentPreviewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Document Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="documentPreviewContent">
                    <!-- Content will be loaded dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="downloadBtn">
                        <i class="fas fa-download"></i> Download
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Approve Document Modal -->
    <div class="modal fade" id="approveDocumentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Approve Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="approve_document">
                        <input type="hidden" name="document_id" id="approveDocumentId">
                        
                        <div class="mb-3">
                            <label for="approveRemarks" class="form-label">Approval Remarks (Optional)</label>
                            <textarea class="form-control" id="approveRemarks" name="remarks" rows="3" 
                                      placeholder="Add any comments about this approval..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check"></i> Approve Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Document Modal -->
    <div class="modal fade" id="rejectDocumentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reject_document">
                        <input type="hidden" name="document_id" id="rejectDocumentId">
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            Please provide a reason for rejecting this document. This will help the user understand what needs to be corrected.
                        </div>
                        
                        <div class="mb-3">
                            <label for="rejectRemarks" class="form-label">Rejection Reason *</label>
                            <textarea class="form-control" id="rejectRemarks" name="remarks" rows="4" required
                                      placeholder="Please specify the reason for rejection..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times"></i> Reject Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <script>
        let selectedDocuments = [];

        function toggleBulkActions() {
            const panel = document.getElementById('bulkActionsPanel');
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        }

        function toggleAllDocuments() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.document-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.document-checkbox:checked');
            selectedDocuments = Array.from(checkboxes).map(cb => cb.value);
            
            document.getElementById('selectedCount').value = selectedDocuments.length + ' selected';
            
            // Update hidden input for bulk action form
            const existingInputs = document.querySelectorAll('input[name="documents[]"]');
            existingInputs.forEach(input => input.remove());
            
            selectedDocuments.forEach(docId => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'documents[]';
                input.value = docId;
                document.getElementById('bulkActionForm').appendChild(input);
            });
        }

        function previewDocument(documentId) {
            fetch('../api/get_document_details.php?id=' + documentId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('documentPreviewContent').innerHTML = data.html;
                        document.getElementById('downloadBtn').onclick = () => {
                            window.open(data.file_path, '_blank');
                        };
                        new bootstrap.Modal(document.getElementById('documentPreviewModal')).show();
                    } else {
                        alert('Error loading document preview');
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        }

        function approveDocument(documentId) {
            document.getElementById('approveDocumentId').value = documentId;
            new bootstrap.Modal(document.getElementById('approveDocumentModal')).show();
        }

        function rejectDocument(documentId) {
            document.getElementById('rejectDocumentId').value = documentId;
            new bootstrap.Modal(document.getElementById('rejectDocumentModal')).show();
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

<?php
function formatFileSize($size) {
    $units = array('B', 'KB', 'MB', 'GB');
    $power = $size > 0 ? floor(log($size, 1024)) : 0;
    return number_format($size / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
}
?>
