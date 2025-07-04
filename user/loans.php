<?php
session_start();
require_once '../config/database.php';
requireLogin();

// Get user loans
$stmt = $pdo->prepare("SELECT * FROM loans WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$loans = $stmt->fetchAll();

// Get business suggestions based on loan amounts
$business_suggestions = [];
if (!empty($loans)) {
    $max_loan = max(array_column($loans, 'loan_amount'));
    $stmt = $pdo->prepare("SELECT * FROM business_suggestions WHERE loan_amount_min <= ? AND loan_amount_max >= ? ORDER BY investment_required ASC LIMIT 5");
    $stmt->execute([$max_loan, $max_loan]);
    $business_suggestions = $stmt->fetchAll();
}

// Get user documents for loan requirements
$stmt = $pdo->prepare("SELECT * FROM documents WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_documents = $stmt->fetchAll();

$required_docs = ['aadhar', 'pan', 'service_record'];
$missing_docs = [];
$existing_doc_types = array_column($user_documents, 'document_type');

foreach ($required_docs as $doc) {
    if (!in_array($doc, $existing_doc_types)) {
        $missing_docs[] = $doc;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Tracker - Veer Sahayata</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/user_header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/user_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Loan Tracker</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#applyLoanModal">
                        <i class="fas fa-plus"></i> Apply for New Loan
                    </button>
                </div>

                <!-- Missing Documents Alert -->
                <?php if (!empty($missing_docs)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Missing Documents!</strong> 
                    You need to upload the following documents for loan processing: 
                    <?php echo implode(', ', array_map('ucfirst', $missing_docs)); ?>
                    <a href="profile.php#documents" class="btn btn-sm btn-warning ms-2">Upload Now</a>
                </div>
                <?php endif; ?>

                <!-- Loan Summary Cards -->
                <div class="row mb-4">
                    <?php
                    $active_loans = array_filter($loans, function($loan) { return $loan['status'] == 'active'; });
                    $total_outstanding = array_sum(array_column($active_loans, 'outstanding_balance'));
                    $total_emi = array_sum(array_column($active_loans, 'monthly_emi'));
                    ?>
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5>Active Loans</h5>
                                <h3><?php echo count($active_loans); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <h5>Total Outstanding</h5>
                                <h3>₹<?php echo number_format($total_outstanding, 2); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h5>Monthly EMI</h5>
                                <h3>₹<?php echo number_format($total_emi, 2); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5>Next Due Date</h5>
                                <h3><?php 
                                    $next_due = null;
                                    foreach ($active_loans as $loan) {
                                        if ($loan['due_date'] && (!$next_due || $loan['due_date'] < $next_due)) {
                                            $next_due = $loan['due_date'];
                                        }
                                    }
                                    echo $next_due ? date('d/m/Y', strtotime($next_due)) : 'N/A';
                                ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Current Loans -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Current Loans</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($loans)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Loan Type</th>
                                        <th>Loan Amount</th>
                                        <th>Outstanding</th>
                                        <th>Monthly EMI</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($loans as $loan): ?>
                                    <tr>
                                        <td><?php echo $loan['loan_type']; ?></td>
                                        <td>₹<?php echo number_format($loan['loan_amount'], 2); ?></td>
                                        <td>₹<?php echo number_format($loan['outstanding_balance'], 2); ?></td>
                                        <td>₹<?php echo number_format($loan['monthly_emi'], 2); ?></td>
                                        <td><?php echo $loan['due_date'] ? date('d/m/Y', strtotime($loan['due_date'])) : 'N/A'; ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $loan['status'] == 'active' ? 'success' : 
                                                    ($loan['status'] == 'pending_approval' ? 'warning' : 'secondary'); 
                                            ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $loan['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewLoanDetails(<?php echo $loan['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($loan['status'] == 'pending_approval'): ?>
                                            <button class="btn btn-sm btn-primary" onclick="uploadAdditionalDocs(<?php echo $loan['id']; ?>)">
                                                <i class="fas fa-upload"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                            <h5>No Loans Found</h5>
                            <p class="text-muted">You haven't applied for any loans yet.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#applyLoanModal">
                                Apply for Your First Loan
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Business Suggestions -->
                <?php if (!empty($business_suggestions)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-lightbulb"></i> Business Ideas Based on Your Loan Capacity</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($business_suggestions as $suggestion): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card border-left-primary">
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo $suggestion['business_idea']; ?></h6>
                                        <p class="card-text small"><?php echo $suggestion['description']; ?></p>
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted">Investment: ₹<?php echo number_format($suggestion['investment_required']); ?></small>
                                            </div>
                                            <div class="col-6">
                                                <span class="badge bg-<?php 
                                                    echo $suggestion['risk_level'] == 'low' ? 'success' : 
                                                        ($suggestion['risk_level'] == 'medium' ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo ucfirst($suggestion['risk_level']); ?> Risk
                                                </span>
                                            </div>
                                        </div>
                                        <small class="text-success"><?php echo $suggestion['expected_returns']; ?></small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Apply Loan Modal -->
    <div class="modal fade" id="applyLoanModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Apply for New Loan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="../api/apply_loan.php" method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="loan_type" class="form-label">Loan Type</label>
                                    <select class="form-select" id="loan_type" name="loan_type" required>
                                        <option value="">Select Loan Type</option>
                                        <option value="Personal Loan">Personal Loan</option>
                                        <option value="Home Loan">Home Loan</option>
                                        <option value="Vehicle Loan">Vehicle Loan</option>
                                        <option value="Education Loan">Education Loan</option>
                                        <option value="Business Loan">Business Loan</option>
                                        <option value="Emergency Loan">Emergency Loan</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="loan_amount" class="form-label">Loan Amount (₹)</label>
                                    <input type="number" class="form-control" id="loan_amount" name="loan_amount" required min="10000" max="5000000">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="interest_rate" class="form-label">Expected Interest Rate (%)</label>
                                    <input type="number" class="form-control" id="interest_rate" name="interest_rate" step="0.1" min="1" max="20">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="tenure" class="form-label">Tenure (Months)</label>
                                    <select class="form-select" id="tenure" name="tenure" required>
                                        <option value="">Select Tenure</option>
                                        <option value="12">12 Months</option>
                                        <option value="24">24 Months</option>
                                        <option value="36">36 Months</option>
                                        <option value="48">48 Months</option>
                                        <option value="60">60 Months</option>
                                        <option value="120">120 Months</option>
                                        <option value="240">240 Months</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="purpose" class="form-label">Purpose of Loan</label>
                            <textarea class="form-control" id="purpose" name="purpose" rows="3" required placeholder="Describe the purpose of this loan..."></textarea>
                        </div>
                        
                        <div class="alert alert-info">
                            <strong>Required Documents:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Aadhar Card</li>
                                <li>PAN Card</li>
                                <li>Service Records</li>
                                <li>Salary Slips (if applicable)</li>
                                <li>Bank Statements</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Application</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Loan Details Modal -->
    <div class="modal fade" id="loanDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Loan Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="loanDetailsContent">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewLoanDetails(loanId) {
            fetch('../api/get_loan_details.php?id=' + loanId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('loanDetailsContent').innerHTML = data.html;
                        new bootstrap.Modal(document.getElementById('loanDetailsModal')).show();
                    } else {
                        alert('Error loading loan details');
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        }

        function uploadAdditionalDocs(loanId) {
            // Redirect to profile page with loan context
            window.location.href = 'profile.php#documents&loan_id=' + loanId;
        }

        // Calculate EMI on loan amount change
        document.getElementById('loan_amount').addEventListener('input', calculateEMI);
        document.getElementById('interest_rate').addEventListener('input', calculateEMI);
        document.getElementById('tenure').addEventListener('change', calculateEMI);

        function calculateEMI() {
            const amount = parseFloat(document.getElementById('loan_amount').value) || 0;
            const rate = parseFloat(document.getElementById('interest_rate').value) || 10;
            const tenure = parseInt(document.getElementById('tenure').value) || 12;

            if (amount > 0 && rate > 0 && tenure > 0) {
                const monthlyRate = rate / 100 / 12;
                const emi = (amount * monthlyRate * Math.pow(1 + monthlyRate, tenure)) / 
                           (Math.pow(1 + monthlyRate, tenure) - 1);
                
                const emiDisplay = document.getElementById('emiDisplay');
                if (!emiDisplay) {
                    const emiDiv = document.createElement('div');
                    emiDiv.id = 'emiDisplay';
                    emiDiv.className = 'alert alert-success mt-2';
                    emiDiv.innerHTML = '<strong>Estimated Monthly EMI: ₹' + emi.toFixed(2) + '</strong>';
                    document.getElementById('tenure').parentNode.appendChild(emiDiv);
                } else {
                    emiDisplay.innerHTML = '<strong>Estimated Monthly EMI: ₹' + emi.toFixed(2) + '</strong>';
                }
            }
        }
    </script>
</body>
</html>
