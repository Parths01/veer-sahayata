<?php
session_start();
require_once '../config/database.php';
require_once '../config/loan_rates.php';
requireLogin();
requireAdmin();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_loan':
                $result = updateLoan($_POST);
                break;
            case 'approve_loan':
                $result = approveLoan($_POST['loan_id']);
                break;
            case 'disburse_loan':
                $result = disburseLoan($_POST);
                break;
            case 'reject_loan':
                $result = rejectLoan($_POST['loan_id'], $_POST['rejection_reason'] ?? '');
                break;
            case 'bulk_action':
                $result = bulkLoanAction($_POST['loan_ids'], $_POST['bulk_action']);
                break;
        }
    }
}

// Get loan records with pagination and filters
$status = $_GET['status'] ?? '';
$loan_type = $_GET['loan_type'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$sql = "SELECT l.*, u.service_number, u.full_name, u.service_type, u.rank_designation 
        FROM loans l 
        JOIN users u ON l.user_id = u.id 
        WHERE 1=1";
$params = [];

if ($status) {
    $sql .= " AND l.status = ?";
    $params[] = $status;
}

if ($loan_type) {
    $sql .= " AND l.loan_type = ?";
    $params[] = $loan_type;
}

if ($search) {
    $sql .= " AND (u.service_number LIKE ? OR u.full_name LIKE ? OR l.loan_type LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

$sql .= " ORDER BY l.created_at DESC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$loans = $stmt->fetchAll();

// Get total count for pagination
$countSql = str_replace("SELECT l.*, u.service_number, u.full_name, u.service_type, u.rank_designation", "SELECT COUNT(*)", explode("ORDER BY", $sql)[0]);
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalLoans = $stmt->fetchColumn();
$totalPages = ceil($totalLoans / $limit);

// Get loan statistics
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM loans")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM loans WHERE status IN ('pending', 'pending_approval')")->fetchColumn(),
    'approved' => $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'approved'")->fetchColumn(),
    'disbursed' => $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'disbursed'")->fetchColumn(),
    'rejected' => $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'rejected'")->fetchColumn(),
    'total_amount' => $pdo->query("SELECT SUM(loan_amount) FROM loans WHERE status IN ('approved', 'disbursed')")->fetchColumn() ?: 0,
    'disbursed_amount' => $pdo->query("SELECT SUM(disbursed_amount) FROM loans WHERE status = 'disbursed'")->fetchColumn() ?: 0
];

// Get loan types for filter
$loanTypes = $pdo->query("SELECT DISTINCT loan_type FROM loans ORDER BY loan_type")->fetchAll(PDO::FETCH_COLUMN);

// Functions for loan operations
function updateLoan($data) {
    global $pdo;
    try {
        $loanId = $data['loan_id'];
        
        $stmt = $pdo->prepare("UPDATE loans SET loan_type = ?, loan_amount = ?, tenure_years = ?, emi = ?, interest_rate = ?, processing_fee = ? WHERE id = ?");
        $stmt->execute([
            $data['loan_type'],
            $data['loan_amount'],
            $data['tenure_years'],
            $data['emi'],
            $data['interest_rate'],
            $data['processing_fee'] ?: 0,
            $loanId
        ]);
        
        return ['success' => true, 'message' => 'Loan updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function approveLoan($loanId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("UPDATE loans SET status = 'approved', approved_date = NOW() WHERE id = ? AND status IN ('pending', 'pending_approval')");
        $stmt->execute([$loanId]);
        
        return ['success' => true, 'message' => 'Loan approved successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function disburseLoan($data) {
    global $pdo;
    try {
        $loanId = $data['loan_id'];
        $disbursedAmount = $data['disbursed_amount'];
        
        $stmt = $pdo->prepare("UPDATE loans SET status = 'disbursed', disbursed_amount = ?, disbursement_date = NOW() WHERE id = ?");
        $stmt->execute([$disbursedAmount, $loanId]);
        
        return ['success' => true, 'message' => 'Loan disbursed successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function rejectLoan($loanId, $reason) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("UPDATE loans SET status = 'rejected', rejection_reason = ?, rejected_date = NOW() WHERE id = ?");
        $stmt->execute([$reason, $loanId]);
        
        return ['success' => true, 'message' => 'Loan rejected successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function bulkLoanAction($loanIds, $action) {
    global $pdo;
    try {
        if (empty($loanIds)) {
            throw new Exception("No loans selected");
        }
        
        $placeholders = str_repeat('?,', count($loanIds) - 1) . '?';
        
        if ($action == 'approve') {
            $stmt = $pdo->prepare("UPDATE loans SET status = 'approved', approved_date = NOW() WHERE id IN ($placeholders) AND status IN ('pending', 'pending_approval')");
            $stmt->execute($loanIds);
        } else if ($action == 'reject') {
            $stmt = $pdo->prepare("UPDATE loans SET status = 'rejected', rejected_date = NOW() WHERE id IN ($placeholders) AND status IN ('pending', 'pending_approval')");
            $stmt->execute($loanIds);
        } else if ($action == 'pending') {
            $stmt = $pdo->prepare("UPDATE loans SET status = 'pending' WHERE id IN ($placeholders)");
            $stmt->execute($loanIds);
        }
        
        $count = count($loanIds);
        return ['success' => true, 'message' => "$count loans updated successfully"];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Helper function for loan type display names
function getLoanTypeDisplayName($type) {
    $types = [
        'personal_loan' => 'Personal Loan',
        'home_loan' => 'Home Loan', 
        'vehicle_loan' => 'Vehicle Loan',
        'education_loan' => 'Education Loan',
        'medical_loan' => 'Medical Loan',
        'business_loan' => 'Business Loan'
    ];
    return $types[$type] ?? ucfirst(str_replace('_', ' ', $type));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Management - Veer Sahayata Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .loan-card {
            transition: transform 0.2s;
        }
        .loan-card:hover {
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
        .emi-display {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            padding: 8px 12px;
            border-radius: 15px;
            font-weight: bold;
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
                    <h1 class="h2">Loan Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="assign_loan.php" class="btn btn-sm btn-success">
                                <i class="fas fa-plus"></i> Assign Loan
                            </a>
                            <a href="loan_rates.php" class="btn btn-sm btn-outline-info">
                                <i class="fas fa-table"></i> View Rates
                            </a>
                            <button class="btn btn-sm btn-outline-secondary" onclick="toggleBulkActions()">
                                <i class="fas fa-tasks"></i> Bulk Actions
                            </button>
                            <button class="btn btn-sm btn-outline-primary" onclick="exportLoans()">
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

                <!-- Loan Statistics -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h4><?php echo $stats['total']; ?></h4>
                                <p class="mb-0">Total Loans</p>
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
                                <h4>₹<?php echo number_format($stats['total_amount']/100000, 1); ?>L</h4>
                                <p class="mb-0 small">Total Amount</p>
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
                                       placeholder="Search by service number, name, or loan type...">
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="pending_approval" <?php echo $status == 'pending_approval' ? 'selected' : ''; ?>>Pending Approval</option>
                                    <option value="approved" <?php echo $status == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="disbursed" <?php echo $status == 'disbursed' ? 'selected' : ''; ?>>Disbursed</option>
                                    <option value="rejected" <?php echo $status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="loan_type" class="form-label">Loan Type</label>
                                <select class="form-select" id="loan_type" name="loan_type">
                                    <option value="">All Types</option>
                                    <?php foreach ($loanTypes as $type): ?>
                                    <option value="<?php echo $type; ?>" <?php echo $loan_type == $type ? 'selected' : ''; ?>>
                                        <?php echo getLoanTypeDisplayName($type); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                                <a href="loan_management.php" class="btn btn-secondary">
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
                                <label class="form-label">Selected Loans</label>
                                <input type="text" class="form-control" id="selectedCount" readonly value="0 selected">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Action</label>
                                <select class="form-select" name="bulk_action" required>
                                    <option value="">Choose Action</option>
                                    <option value="approve">Approve All</option>
                                    <option value="reject">Reject All</option>
                                    <option value="pending">Mark as Pending</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary" onclick="return confirmBulkAction()">Apply Action</button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Loans Table -->
                <div class="card">
                    <div class="card-header">
                        <h5>Loan Records (<?php echo $totalLoans; ?> total)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($loans)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>
                                            <input type="checkbox" id="selectAll" onchange="toggleAllLoans()">
                                        </th>
                                        <th>Applicant Details</th>
                                        <th>Loan Info</th>
                                        <th>Amount & EMI</th>
                                        <th>Status</th>
                                        <th>Dates</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($loans as $loan): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="loan-checkbox" value="<?php echo $loan['id']; ?>" 
                                                   onchange="updateSelectedCount()">
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($loan['full_name']); ?></strong><br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($loan['service_number']); ?><br>
                                                    <?php echo htmlspecialchars($loan['rank_designation']); ?><br>
                                                    <span class="badge bg-<?php 
                                                        echo $loan['service_type'] == 'Army' ? 'success' : 
                                                            ($loan['service_type'] == 'Navy' ? 'primary' : 'info'); 
                                                    ?> badge-sm">
                                                        <?php echo $loan['service_type']; ?>
                                                    </span>
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo getLoanTypeDisplayName($loan['loan_type']); ?></strong><br>
                                                <small class="text-muted">
                                                    Tenure: <?php echo $loan['tenure_years']; ?> years<br>
                                                    Interest: <?php echo $loan['interest_rate']; ?>%
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <span class="amount-highlight text-primary">₹<?php echo number_format($loan['loan_amount'], 0); ?></span><br>
                                                <div class="emi-display">
                                                    EMI: ₹<?php echo number_format($loan['emi'], 0); ?>
                                                </div>
                                                <?php if ($loan['processing_fee'] > 0): ?>
                                                <small class="text-muted">
                                                    Fee: ₹<?php echo number_format($loan['processing_fee'], 0); ?>
                                                </small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge status-badge bg-<?php 
                                                echo $loan['status'] == 'approved' ? 'success' : 
                                                    ($loan['status'] == 'disbursed' ? 'info' : 
                                                        ($loan['status'] == 'rejected' ? 'danger' : 'warning')); 
                                            ?>">
                                                <?php echo $loan['status'] == 'pending_approval' ? 'Pending Approval' : ucfirst($loan['status']); ?>
                                            </span>
                                            <?php if ($loan['status'] == 'disbursed' && $loan['disbursed_amount']): ?>
                                            <br><small class="text-success">
                                                Disbursed: ₹<?php echo number_format($loan['disbursed_amount'], 0); ?>
                                            </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small>
                                                Applied: <?php echo date('d/m/Y', strtotime($loan['created_at'])); ?><br>
                                                <?php if ($loan['approved_date']): ?>
                                                Approved: <?php echo date('d/m/Y', strtotime($loan['approved_date'])); ?><br>
                                                <?php endif; ?>
                                                <?php if ($loan['disbursement_date']): ?>
                                                Disbursed: <?php echo date('d/m/Y', strtotime($loan['disbursement_date'])); ?>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group-vertical btn-group-sm" role="group">
                                                <button class="btn btn-outline-info" onclick="viewLoan(<?php echo $loan['id']; ?>)" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-warning" onclick="editLoan(<?php echo $loan['id']; ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($loan['status'] == 'pending' || $loan['status'] == 'pending_approval'): ?>
                                                <button class="btn btn-outline-success" onclick="approveLoan(<?php echo $loan['id']; ?>)" title="Approve">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" onclick="rejectLoan(<?php echo $loan['id']; ?>)" title="Reject">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                <?php endif; ?>
                                                <?php if ($loan['status'] == 'approved'): ?>
                                                <button class="btn btn-outline-primary" onclick="disburseLoan(<?php echo $loan['id']; ?>)" title="Disburse">
                                                    <i class="fas fa-money-bill-wave"></i>
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
                        <nav aria-label="Loans pagination" class="mt-3">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status); ?>&loan_type=<?php echo urlencode($loan_type); ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                                </li>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&loan_type=<?php echo urlencode($loan_type); ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status); ?>&loan_type=<?php echo urlencode($loan_type); ?>&search=<?php echo urlencode($search); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>

                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-money-check-alt fa-3x text-muted mb-3"></i>
                            <h5>No Loan Records Found</h5>
                            <p class="text-muted">
                                <?php echo $search ? 'No loan records match your search criteria.' : 'No loan applications have been submitted yet.'; ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- View Loan Details Modal -->
    <div class="modal fade" id="viewLoanModal" tabindex="-1" aria-labelledby="viewLoanModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewLoanModalLabel">
                        <i class="fas fa-eye"></i> Loan Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="viewLoanContent">
                    <!-- Content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning" onclick="editLoanFromView()">
                        <i class="fas fa-edit"></i> Edit Loan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Loan Modal -->
    <div class="modal fade" id="editLoanModal" tabindex="-1" aria-labelledby="editLoanModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editLoanModalLabel">
                        <i class="fas fa-edit"></i> Edit Loan
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="editLoanForm">
                    <div class="modal-body" id="editLoanContent">
                        <!-- Content will be loaded here -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Disburse Loan Modal -->
    <div class="modal fade" id="disburseLoanModal" tabindex="-1" aria-labelledby="disburseLoanModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="disburseLoanModalLabel">
                        <i class="fas fa-money-bill-wave"></i> Disburse Loan
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="disburseLoanForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="disburse_loan">
                        <input type="hidden" name="loan_id" id="disburse_loan_id">
                        
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> Loan Disbursement Details</h6>
                            <div id="disburse_loan_info"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="disbursed_amount" class="form-label">Disbursement Amount *</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" class="form-control" id="disbursed_amount" name="disbursed_amount" 
                                       step="0.01" min="1" required>
                            </div>
                            <small class="text-muted">Enter the actual amount to be disbursed</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="disbursement_notes" class="form-label">Disbursement Notes</label>
                            <textarea class="form-control" id="disbursement_notes" name="disbursement_notes" 
                                      rows="3" placeholder="Any additional notes for disbursement..."></textarea>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="confirm_disbursement" required>
                            <label class="form-check-label" for="confirm_disbursement">
                                I confirm that the disbursement details are correct and the amount will be transferred to the applicant's account.
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check"></i> Confirm Disbursement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Loan Modal -->
    <div class="modal fade" id="rejectLoanModal" tabindex="-1" aria-labelledby="rejectLoanModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="rejectLoanModalLabel">
                        <i class="fas fa-times-circle"></i> Reject Loan Application
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="rejectLoanForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reject_loan">
                        <input type="hidden" name="loan_id" id="reject_loan_id">
                        
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle"></i> Loan Rejection</h6>
                            <div id="reject_loan_info"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="rejection_reason" class="form-label">Rejection Reason *</label>
                            <select class="form-select" id="rejection_reason" name="rejection_reason" required onchange="toggleCustomReason()">
                                <option value="">Select rejection reason...</option>
                                <option value="Insufficient Documents">Insufficient Documents</option>
                                <option value="Ineligible Service Type">Ineligible Service Type</option>
                                <option value="Income Criteria Not Met">Income Criteria Not Met</option>
                                <option value="Existing Loan Default">Existing Loan Default</option>
                                <option value="Service Record Issues">Service Record Issues</option>
                                <option value="Incomplete Application">Incomplete Application</option>
                                <option value="Other">Other (Specify)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="custom_reason_div" style="display: none;">
                            <label for="custom_rejection_reason" class="form-label">Specify Reason *</label>
                            <textarea class="form-control" id="custom_rejection_reason" name="custom_rejection_reason" 
                                      rows="3" placeholder="Please provide detailed reason for rejection..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="rejection_notes" class="form-label">Additional Notes</label>
                            <textarea class="form-control" id="rejection_notes" name="rejection_notes" 
                                      rows="3" placeholder="Any additional notes or recommendations..."></textarea>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="confirm_rejection" required>
                            <label class="form-check-label" for="confirm_rejection">
                                I confirm that this loan application should be rejected and the applicant will be notified.
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times"></i> Confirm Rejection
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedLoans = [];

        function toggleBulkActions() {
            const panel = document.getElementById('bulkActionsPanel');
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        }

        function toggleAllLoans() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.loan-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.loan-checkbox:checked');
            selectedLoans = Array.from(checkboxes).map(cb => cb.value);
            
            document.getElementById('selectedCount').value = selectedLoans.length + ' selected';
            
            // Update hidden inputs for bulk action form
            const existingInputs = document.querySelectorAll('input[name="loan_ids[]"]');
            existingInputs.forEach(input => input.remove());
            
            selectedLoans.forEach(loanId => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'loan_ids[]';
                input.value = loanId;
                document.getElementById('bulkActionForm').appendChild(input);
            });
        }

        function confirmBulkAction() {
            const action = document.querySelector('select[name="bulk_action"]').value;
            const count = selectedLoans.length;
            
            if (count === 0) {
                alert('Please select at least one loan record');
                return false;
            }
            
            let message = '';
            if (action === 'approve') {
                message = `Are you sure you want to APPROVE ${count} loan(s)?`;
            } else if (action === 'reject') {
                message = `Are you sure you want to REJECT ${count} loan(s)?`;
            } else if (action === 'pending') {
                message = `Are you sure you want to mark ${count} loan(s) as PENDING?`;
            }
            
            return confirm(message);
        }

        function exportLoans() {
            const params = new URLSearchParams(window.location.search);
            window.location.href = '../api/export_loans.php?' + params.toString();
        }

        function refreshPage() {
            window.location.reload();
        }

        // Placeholder functions for loan actions
        function viewLoan(loanId) {
            fetch(`../api/get_loan_details.php?id=${loanId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('viewLoanContent').innerHTML = data.html;
                        const modal = new bootstrap.Modal(document.getElementById('viewLoanModal'));
                        modal.show();
                    } else {
                        alert('Error loading loan details: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        }

        function editLoan(loanId) {
            fetch(`../api/get_edit_loan_form.php?id=${loanId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('editLoanContent').innerHTML = data.html;
                        const modal = new bootstrap.Modal(document.getElementById('editLoanModal'));
                        modal.show();
                        
                        // Initialize calculation functions
                        if (typeof initializeLoanCalculations === 'function') {
                            initializeLoanCalculations();
                        }
                    } else {
                        alert('Error loading loan form: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        }

        function editLoanFromView() {
            const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewLoanModal'));
            viewModal.hide();
            
            // Get loan ID from the view modal
            const loanId = document.querySelector('#viewLoanContent [data-loan-id]')?.dataset.loanId;
            if (loanId) {
                setTimeout(() => editLoan(loanId), 300);
            }
        }

        function approveLoan(loanId) {
            if (confirm('Are you sure you want to approve this loan?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="approve_loan">
                    <input type="hidden" name="loan_id" value="${loanId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function rejectLoan(loanId) {
            // Load loan basic info for rejection modal
            fetch(`../api/get_loan_details.php?id=${loanId}&basic=1`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('reject_loan_id').value = loanId;
                        document.getElementById('reject_loan_info').innerHTML = `
                            <strong>Applicant:</strong> ${data.loan.full_name}<br>
                            <strong>Loan Type:</strong> ${data.loan.loan_type}<br>
                            <strong>Amount:</strong> ₹${data.loan.loan_amount}
                        `;
                        
                        const modal = new bootstrap.Modal(document.getElementById('rejectLoanModal'));
                        modal.show();
                    } else {
                        alert('Error loading loan details: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        }

        function disburseLoan(loanId) {
            // Load loan basic info for disbursement modal
            fetch(`../api/get_loan_details.php?id=${loanId}&basic=1`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('disburse_loan_id').value = loanId;
                        document.getElementById('disbursed_amount').value = data.loan.loan_amount;
                        document.getElementById('disburse_loan_info').innerHTML = `
                            <strong>Applicant:</strong> ${data.loan.full_name}<br>
                            <strong>Loan Type:</strong> ${data.loan.loan_type}<br>
                            <strong>Approved Amount:</strong> ₹${data.loan.loan_amount}<br>
                            <strong>EMI:</strong> ₹${data.loan.emi}
                        `;
                        
                        const modal = new bootstrap.Modal(document.getElementById('disburseLoanModal'));
                        modal.show();
                    } else {
                        alert('Error loading loan details: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        }

        function toggleCustomReason() {
            const reasonSelect = document.getElementById('rejection_reason');
            const customDiv = document.getElementById('custom_reason_div');
            const customTextarea = document.getElementById('custom_rejection_reason');
            
            if (reasonSelect.value === 'Other') {
                customDiv.style.display = 'block';
                customTextarea.required = true;
            } else {
                customDiv.style.display = 'none';
                customTextarea.required = false;
                customTextarea.value = '';
            }
        }

        // Handle form submissions
        document.getElementById('editLoanForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'update_loan');
            
            fetch('loan_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // Close modal and reload page
                bootstrap.Modal.getInstance(document.getElementById('editLoanModal')).hide();
                window.location.reload();
            })
            .catch(error => {
                alert('Error updating loan: ' + error.message);
            });
        });

        document.getElementById('rejectLoanForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            let reason = document.getElementById('rejection_reason').value;
            if (reason === 'Other') {
                reason = document.getElementById('custom_rejection_reason').value;
            }
            
            const notes = document.getElementById('rejection_notes').value;
            const finalReason = notes ? `${reason}\n\nAdditional Notes: ${notes}` : reason;
            
            const formData = new FormData();
            formData.append('action', 'reject_loan');
            formData.append('loan_id', document.getElementById('reject_loan_id').value);
            formData.append('rejection_reason', finalReason);
            
            fetch('loan_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // Close modal and reload page
                bootstrap.Modal.getInstance(document.getElementById('rejectLoanModal')).hide();
                window.location.reload();
            })
            .catch(error => {
                alert('Error rejecting loan: ' + error.message);
            });
        });

        document.getElementById('disburseLoanForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('loan_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // Close modal and reload page
                bootstrap.Modal.getInstance(document.getElementById('disburseLoanModal')).hide();
                window.location.reload();
            })
            .catch(error => {
                alert('Error disbursing loan: ' + error.message);
            });
        });

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
