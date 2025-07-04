<?php
session_start();
require_once '../config/database.php';
require_once '../config/pension_rates.php';
requireLogin();
requireAdmin();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_pension':
                $result = updatePension($_POST);
                break;
            case 'approve_pension':
                $result = approvePension($_POST['pension_id']);
                break;
            case 'disburse_pension':
                $result = disbursePension($_POST);
                break;
            case 'bulk_action':
                $result = bulkPensionAction($_POST['pension_ids'], $_POST['bulk_action']);
                break;
        }
    }
}

// Get pension records with pagination and filters
$status = $_GET['status'] ?? '';
$pension_type = $_GET['pension_type'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$sql = "SELECT p.*, u.service_number, u.full_name, u.service_type, u.rank_designation 
        FROM pension p 
        JOIN users u ON p.user_id = u.id 
        WHERE 1=1";
$params = [];

if ($status) {
    $sql .= " AND p.status = ?";
    $params[] = $status;
}

if ($pension_type) {
    $sql .= " AND p.pension_type = ?";
    $params[] = $pension_type;
}

if ($search) {
    $sql .= " AND (u.service_number LIKE ? OR u.full_name LIKE ? OR p.pension_type LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

$sql .= " ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pensions = $stmt->fetchAll();

// Get total count for pagination
$countSql = str_replace("SELECT p.*, u.service_number, u.full_name, u.service_type, u.rank_designation", "SELECT COUNT(*)", explode("ORDER BY", $sql)[0]);
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalPensions = $stmt->fetchColumn();
$totalPages = ceil($totalPensions / $limit);

// Get pension statistics
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM pension")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM pension WHERE status = 'pending'")->fetchColumn(),
    'approved' => $pdo->query("SELECT COUNT(*) FROM pension WHERE status = 'approved'")->fetchColumn(),
    'disbursed' => $pdo->query("SELECT COUNT(*) FROM pension WHERE status = 'disbursed'")->fetchColumn(),
    'total_amount' => $pdo->query("SELECT SUM(monthly_amount) FROM pension WHERE status = 'approved' OR status = 'disbursed'")->fetchColumn() ?: 0,
    'disbursed_amount' => $pdo->query("SELECT SUM(last_disbursed_amount) FROM pension WHERE status = 'disbursed'")->fetchColumn() ?: 0
];

// Get pension types for filter
$pensionTypes = $pdo->query("SELECT DISTINCT pension_type FROM pension ORDER BY pension_type")->fetchAll(PDO::FETCH_COLUMN);

// Functions
function logPensionHistory($pensionId, $action, $oldStatus = null, $newStatus = null, $oldAmount = null, $newAmount = null, $comments = null) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO pension_history (pension_id, action, old_status, new_status, old_amount, new_amount, comments, admin_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$pensionId, $action, $oldStatus, $newStatus, $oldAmount, $newAmount, $comments, $_SESSION['user_id']]);
    } catch (Exception $e) {
        // History table might not exist yet, silently continue
    }
}

function updatePension($data) {
    global $pdo;
    try {
        $pensionId = $data['pension_id'];
        
        // Get current pension details for history
        $stmt = $pdo->prepare("SELECT * FROM pension WHERE id = ?");
        $stmt->execute([$pensionId]);
        $currentPension = $stmt->fetch();
        
        $stmt = $pdo->prepare("UPDATE pension SET pension_type = ?, monthly_amount = ?, cgst = ?, sgst = ?, loan_deduction = ?, net_amount = ? WHERE id = ?");
        $stmt->execute([
            $data['pension_type'],
            $data['monthly_amount'],
            $data['cgst'] ?: 0,
            $data['sgst'] ?: 0,
            $data['loan_deduction'] ?: 0,
            $data['net_amount'],
            $pensionId
        ]);
        
        // Log history
        logPensionHistory($pensionId, 'update_details', null, null, $currentPension['monthly_amount'], $data['monthly_amount'], 'Pension details updated');
        
        return ['success' => true, 'message' => 'Pension updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function approvePension($pensionId) {
    global $pdo;
    try {
        // Get current status for history
        $stmt = $pdo->prepare("SELECT status FROM pension WHERE id = ?");
        $stmt->execute([$pensionId]);
        $currentStatus = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("UPDATE pension SET status = 'approved' WHERE id = ?");
        $stmt->execute([$pensionId]);
        
        // Log history
        logPensionHistory($pensionId, 'approve', $currentStatus, 'approved', null, null, 'Pension approved by admin');
        
        return ['success' => true, 'message' => 'Pension approved successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function disbursePension($data) {
    global $pdo;
    try {
        $pensionId = $data['pension_id'];
        $disbursedAmount = $data['disbursed_amount'];
        
        // Get current status for history
        $stmt = $pdo->prepare("SELECT status FROM pension WHERE id = ?");
        $stmt->execute([$pensionId]);
        $currentStatus = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("UPDATE pension SET status = 'disbursed', last_disbursed_amount = ?, disbursement_date = NOW() WHERE id = ?");
        $stmt->execute([$disbursedAmount, $pensionId]);
        
        // Log history
        logPensionHistory($pensionId, 'disburse', $currentStatus, 'disbursed', null, $disbursedAmount, 'Pension disbursed to beneficiary');
        
        return ['success' => true, 'message' => 'Pension disbursed successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function bulkPensionAction($pensionIds, $action) {
    global $pdo;
    try {
        if (empty($pensionIds)) {
            throw new Exception("No pensions selected");
        }
        
        $placeholders = str_repeat('?,', count($pensionIds) - 1) . '?';
        
        if ($action == 'approve') {
            $stmt = $pdo->prepare("UPDATE pension SET status = 'approved' WHERE id IN ($placeholders)");
            $stmt->execute($pensionIds);
            
            // Log history for each pension
            foreach ($pensionIds as $pensionId) {
                logPensionHistory($pensionId, 'bulk_approve', 'pending', 'approved', null, null, 'Bulk approval action');
            }
        } else if ($action == 'pending') {
            $stmt = $pdo->prepare("UPDATE pension SET status = 'pending' WHERE id IN ($placeholders)");
            $stmt->execute($pensionIds);
            
            // Log history for each pension
            foreach ($pensionIds as $pensionId) {
                logPensionHistory($pensionId, 'bulk_pending', null, 'pending', null, null, 'Bulk status change to pending');
            }
        } else if ($action == 'delete') {
            $stmt = $pdo->prepare("DELETE FROM pension WHERE id IN ($placeholders)");
            $stmt->execute($pensionIds);
            
            // History will be automatically deleted due to foreign key constraint
        }
        
        $count = count($pensionIds);
        return ['success' => true, 'message' => "$count pensions updated successfully"];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pension Management - Veer Sahayata Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .pension-card {
            transition: transform 0.2s;
        }
        .pension-card:hover {
            transform: translateY(-2px);
        }
        .status-badge {
            font-size: 0.8em;
        }
        .amount-highlight {
            font-size: 1.2em;
            font-weight: bold;
        }
        .bulk-actions {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: none;
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
                    <h1 class="h2">Pension Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="assign_pension.php" class="btn btn-sm btn-success">
                                <i class="fas fa-plus"></i> Assign Pension
                            </a>
                            <a href="pension_rates.php" class="btn btn-sm btn-outline-info">
                                <i class="fas fa-table"></i> View Rates
                            </a>
                            <button class="btn btn-sm btn-outline-secondary" onclick="toggleBulkActions()">
                                <i class="fas fa-tasks"></i> Bulk Actions
                            </button>
                            <button class="btn btn-sm btn-outline-primary" onclick="exportPensions()">
                                <i class="fas fa-file-excel"></i> Export
                            </button>
                            <button class="btn btn-sm btn-outline-success" onclick="refreshPage()">
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

                <!-- Pension Statistics -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h4><?php echo $stats['total']; ?></h4>
                                <p class="mb-0">Total Pensions</p>
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
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h4><?php echo $stats['approved']; ?></h4>
                                <p class="mb-0">Approved</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h4><?php echo $stats['disbursed']; ?></h4>
                                <p class="mb-0">Disbursed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-secondary text-white">
                            <div class="card-body text-center">
                                <h4>₹<?php echo number_format($stats['total_amount'], 0); ?></h4>
                                <p class="mb-0 small">Total Amount</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-dark text-white">
                            <div class="card-body text-center">
                                <h4>₹<?php echo number_format($stats['disbursed_amount'], 0); ?></h4>
                                <p class="mb-0 small">Disbursed</p>
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
                                       placeholder="Search by service number, name, or pension type...">
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $status == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="disbursed" <?php echo $status == 'disbursed' ? 'selected' : ''; ?>>Disbursed</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="pension_type" class="form-label">Pension Type</label>
                                <select class="form-select" id="pension_type" name="pension_type">
                                    <option value="">All Types</option>
                                    <?php foreach ($pensionTypes as $type): ?>
                                    <option value="<?php echo $type; ?>" <?php echo $pension_type == $type ? 'selected' : ''; ?>>
                                        <?php echo ucfirst(str_replace('_', ' ', $type)); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                                <a href="pension_management.php" class="btn btn-secondary">
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
                                <label class="form-label">Selected Pensions</label>
                                <input type="text" class="form-control" id="selectedCount" readonly value="0 selected">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Action</label>
                                <select class="form-select" name="bulk_action" required>
                                    <option value="">Choose Action</option>
                                    <option value="approve">Approve All</option>
                                    <option value="pending">Mark as Pending</option>
                                    <option value="delete">Delete</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary" onclick="return confirmBulkAction()">Apply Action</button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Pensions Table -->
                <div class="card">
                    <div class="card-header">
                        <h5>Pension Records (<?php echo $totalPensions; ?> total)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($pensions)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>
                                            <input type="checkbox" id="selectAll" onchange="toggleAllPensions()">
                                        </th>
                                        <th>Pensioner Details</th>
                                        <th>Pension Info</th>
                                        <th>Amount Details</th>
                                        <th>Status</th>
                                        <th>Last Action</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pensions as $pension): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="pension-checkbox" value="<?php echo $pension['id']; ?>" 
                                                   onchange="updateSelectedCount()">
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo $pension['full_name']; ?></strong><br>
                                                <small class="text-muted">
                                                    <?php echo $pension['service_number']; ?><br>
                                                    <?php echo $pension['rank_designation']; ?><br>
                                                    <span class="badge bg-<?php 
                                                        echo $pension['service_type'] == 'Army' ? 'success' : 
                                                            ($pension['service_type'] == 'Navy' ? 'primary' : 'info'); 
                                                    ?> badge-sm">
                                                        <?php echo $pension['service_type']; ?>
                                                    </span>
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo ucfirst(str_replace('_', ' ', $pension['pension_type'])); ?></strong><br>
                                                <small class="text-muted">
                                                    Created: <?php echo date('d/m/Y', strtotime($pension['created_at'])); ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <span class="amount-highlight text-primary">₹<?php echo number_format($pension['monthly_amount'], 2); ?></span><br>
                                                <small class="text-muted">
                                                    CGST: ₹<?php echo number_format($pension['cgst'], 2); ?><br>
                                                    SGST: ₹<?php echo number_format($pension['sgst'], 2); ?><br>
                                                    Loan Deduction: ₹<?php echo number_format($pension['loan_deduction'], 2); ?><br>
                                                    <strong>Net: ₹<?php echo number_format($pension['net_amount'], 2); ?></strong>
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge status-badge bg-<?php 
                                                echo $pension['status'] == 'approved' ? 'success' : 
                                                    ($pension['status'] == 'disbursed' ? 'info' : 'warning'); 
                                            ?>">
                                                <?php echo ucfirst($pension['status']); ?>
                                            </span>
                                            <?php if ($pension['status'] == 'disbursed' && $pension['disbursement_date']): ?>
                                            <br><small class="text-muted">
                                                Disbursed: <?php echo date('d/m/Y', strtotime($pension['disbursement_date'])); ?>
                                            </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small>
                                                <?php if ($pension['last_disbursed_amount']): ?>
                                                Last: ₹<?php echo number_format($pension['last_disbursed_amount'], 2); ?><br>
                                                <?php endif; ?>
                                                <?php echo date('d/m/Y H:i', strtotime($pension['created_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group-vertical btn-group-sm" role="group">
                                                <button class="btn btn-outline-info" onclick="viewPension(<?php echo $pension['id']; ?>)" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-warning" onclick="editPension(<?php echo $pension['id']; ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($pension['status'] == 'pending'): ?>
                                                <button class="btn btn-outline-success" onclick="approvePension(<?php echo $pension['id']; ?>)" title="Approve">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <?php endif; ?>
                                                <?php if ($pension['status'] == 'approved'): ?>
                                                <button class="btn btn-outline-primary" onclick="disbursePension(<?php echo $pension['id']; ?>)" title="Disburse">
                                                    <i class="fas fa-money-bill-wave"></i>
                                                </button>
                                                <?php endif; ?>
                                                <?php if ($pension['status'] == 'approved' || $pension['status'] == 'disbursed'): ?>
                                                <a href="pension_certificate.php?id=<?php echo $pension['id']; ?>" class="btn btn-outline-secondary" title="Print Certificate" target="_blank">
                                                    <i class="fas fa-print"></i>
                                                </a>
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
                        <nav aria-label="Pensions pagination" class="mt-3">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status); ?>&pension_type=<?php echo urlencode($pension_type); ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                                </li>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&pension_type=<?php echo urlencode($pension_type); ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status); ?>&pension_type=<?php echo urlencode($pension_type); ?>&search=<?php echo urlencode($search); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>

                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-money-check-alt fa-3x text-muted mb-3"></i>
                            <h5>No Pension Records Found</h5>
                            <p class="text-muted">
                                <?php echo $search ? 'No pension records match your search criteria.' : 'No pension records have been created yet.'; ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- View Pension Modal -->
    <div class="modal fade" id="viewPensionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Pension Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="pensionDetailsContent">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Pension Modal -->
    <div class="modal fade" id="editPensionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Pension</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="editPensionContent">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <!-- Disburse Pension Modal -->
    <div class="modal fade" id="disbursePensionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Disburse Pension</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="disburse_pension">
                        <input type="hidden" name="pension_id" id="disbursePensionId">
                        
                        <div class="mb-3">
                            <label for="disbursedAmount" class="form-label">Disbursed Amount *</label>
                            <input type="number" class="form-control" id="disbursedAmount" name="disbursed_amount" 
                                   step="0.01" required>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            This will mark the pension as disbursed and record the disbursement date.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-money-bill-wave"></i> Disburse Pension
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <script>
        let selectedPensions = [];

        function toggleBulkActions() {
            const panel = document.getElementById('bulkActionsPanel');
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        }

        function toggleAllPensions() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.pension-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.pension-checkbox:checked');
            selectedPensions = Array.from(checkboxes).map(cb => cb.value);
            
            document.getElementById('selectedCount').value = selectedPensions.length + ' selected';
            
            // Update hidden inputs for bulk action form
            const existingInputs = document.querySelectorAll('input[name="pension_ids[]"]');
            existingInputs.forEach(input => input.remove());
            
            selectedPensions.forEach(pensionId => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'pension_ids[]';
                input.value = pensionId;
                document.getElementById('bulkActionForm').appendChild(input);
            });
        }

        function viewPension(pensionId) {
            fetch('../api/get_pension_details_enhanced.php?id=' + pensionId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('pensionDetailsContent').innerHTML = data.html;
                        new bootstrap.Modal(document.getElementById('viewPensionModal')).show();
                    } else {
                        alert('Error loading pension details: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        }

        function editPension(pensionId) {
            fetch('../api/get_edit_pension_form.php?id=' + pensionId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('editPensionContent').innerHTML = data.html;
                        new bootstrap.Modal(document.getElementById('editPensionModal')).show();
                    } else {
                        alert('Error loading pension edit form');
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        }

        function approvePension(pensionId) {
            if (confirm('Are you sure you want to approve this pension?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="approve_pension">
                    <input type="hidden" name="pension_id" value="${pensionId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function disbursePension(pensionId) {
            document.getElementById('disbursePensionId').value = pensionId;
            new bootstrap.Modal(document.getElementById('disbursePensionModal')).show();
        }

        function exportPensions() {
            const params = new URLSearchParams(window.location.search);
            window.location.href = '../api/export_pensions.php?' + params.toString();
        }

        function refreshPage() {
            window.location.reload();
        }

        function confirmBulkAction() {
            const action = document.querySelector('select[name="bulk_action"]').value;
            const count = selectedPensions.length;
            
            if (count === 0) {
                alert('Please select at least one pension record');
                return false;
            }
            
            let message = '';
            if (action === 'delete') {
                message = `Are you sure you want to DELETE ${count} pension record(s)? This action cannot be undone.`;
            } else if (action === 'approve') {
                message = `Are you sure you want to APPROVE ${count} pension record(s)?`;
            } else if (action === 'pending') {
                message = `Are you sure you want to mark ${count} pension record(s) as PENDING?`;
            }
            
            return confirm(message);
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
