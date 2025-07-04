<?php
session_start();
require_once '../config/database.php';
require_once '../config/pension_rates.php';
// require_once '../includes/pension_history.php'; // Removed because the file does not exist
requireLogin();
requireAdmin();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'assign_pension') {
        try {
            $userId = $_POST['user_id'];
            $pensionType = $_POST['pension_type'];
            $loanDeduction = floatval($_POST['loan_deduction'] ?? 0);
            
            // Get user details
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'user'");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception("User not found");
            }
            
            // Calculate pension amount based on rank
            $baseAmount = getPensionAmountByRank($user['service_type'], $user['rank_designation'], $pensionType);
            
            if ($baseAmount == 0) {
                throw new Exception("Pension rate not found for this rank and service type");
            }
            
            // Calculate net pension with deductions
            $calculation = calculateNetPension($baseAmount, $loanDeduction);
            
            // Check if pension already exists for this user
            $stmt = $pdo->prepare("SELECT id FROM pension WHERE user_id = ?");
            $stmt->execute([$userId]);
            if ($stmt->fetch()) {
                throw new Exception("Pension already assigned to this user");
            }
            
            // Insert new pension record
            $stmt = $pdo->prepare("
                INSERT INTO pension (user_id, pension_type, monthly_amount, cgst, sgst, loan_deduction, net_amount, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $userId,
                $pensionType,
                $calculation['gross_amount'],
                $calculation['cgst'],
                $calculation['sgst'],
                $calculation['loan_deduction'],
                $calculation['net_amount']
            ]);
            
            $pensionId = $pdo->lastInsertId();
            // Log history
            if (function_exists('logPensionHistory')) {
                logPensionHistory($pensionId, 'create', null, 'pending', null, $calculation['gross_amount'], 'Pension assigned by admin');
            }
            logPensionHistory($pensionId, 'create', null, 'pending', null, $calculation['gross_amount'], 'Pension assigned by admin');
            
            $successMessage = "Pension successfully assigned to " . $user['full_name'] . " (₹" . number_format($calculation['net_amount'], 2) . " net amount)";
            
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
        }
    }
}

// Get users without pension
$stmt = $pdo->query("
    SELECT u.* FROM users u 
    LEFT JOIN pension p ON u.id = p.user_id 
    WHERE u.role = 'user' AND p.id IS NULL 
    ORDER BY u.full_name
");
$usersWithoutPension = $stmt->fetchAll();

// Get pension statistics for assigned users
$stmt = $pdo->query("
    SELECT u.service_type, COUNT(*) as count 
    FROM users u 
    JOIN pension p ON u.id = p.user_id 
    GROUP BY u.service_type
");
$assignedStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Pension - Veer Sahayata Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Assign Pension</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="pension_management.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> Back to Management
                        </a>
                    </div>
                </div>

                <!-- Display Messages -->
                <?php if (isset($successMessage)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> <?php echo $successMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (isset($errorMessage)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $errorMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Assignment Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h4><?php echo count($usersWithoutPension); ?></h4>
                                <p class="mb-0">Pending Assignment</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h4><?php echo $assignedStats['Army'] ?? 0; ?></h4>
                                <p class="mb-0">Army Assigned</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h4><?php echo $assignedStats['Navy'] ?? 0; ?></h4>
                                <p class="mb-0">Navy Assigned</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-dark">
                            <div class="card-body text-center">
                                <h4><?php echo $assignedStats['Air Force'] ?? 0; ?></h4>
                                <p class="mb-0">Air Force Assigned</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Assignment Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-plus-circle"></i> Assign New Pension</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="assignPensionForm">
                            <input type="hidden" name="action" value="assign_pension">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="user_id" class="form-label">Select Personnel *</label>
                                        <select class="form-select" id="user_id" name="user_id" required onchange="updateUserDetails()">
                                            <option value="">Choose a personnel...</option>
                                            <?php foreach ($usersWithoutPension as $user): ?>
                                            <option value="<?php echo $user['id']; ?>" 
                                                    data-service-type="<?php echo $user['service_type']; ?>"
                                                    data-rank="<?php echo $user['rank_designation']; ?>"
                                                    data-service-number="<?php echo $user['service_number']; ?>">
                                                <?php echo $user['full_name']; ?> (<?php echo $user['service_number']; ?>) - <?php echo $user['rank_designation']; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="pension_type" class="form-label">Pension Type *</label>
                                        <select class="form-select" id="pension_type" name="pension_type" required onchange="calculatePension()">
                                            <option value="">Select pension type...</option>
                                            <option value="service_pension">Service Pension</option>
                                            <option value="family_pension">Family Pension</option>
                                            <option value="disability_pension">Disability Pension</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- User Details Display -->
                            <div id="userDetailsSection" style="display: none;">
                                <div class="alert alert-info">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>Service Type:</strong> <span id="displayServiceType"></span><br>
                                            <strong>Rank:</strong> <span id="displayRank"></span>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Service Number:</strong> <span id="displayServiceNumber"></span><br>
                                            <strong>Status:</strong> <span class="badge bg-warning">Pension Not Assigned</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="calculated_amount" class="form-label">Calculated Monthly Amount</label>
                                        <div class="input-group">
                                            <span class="input-group-text">₹</span>
                                            <input type="number" class="form-control" id="calculated_amount" readonly>
                                        </div>
                                        <small class="text-muted">Auto-calculated based on rank</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="loan_deduction" class="form-label">Loan Deduction (Optional)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">₹</span>
                                            <input type="number" class="form-control" id="loan_deduction" name="loan_deduction" 
                                                   step="0.01" min="0" onchange="calculatePension()">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="net_amount" class="form-label">Net Amount</label>
                                        <div class="input-group">
                                            <span class="input-group-text">₹</span>
                                            <input type="number" class="form-control" id="net_amount" readonly>
                                        </div>
                                        <small class="text-success">Final disbursement amount</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Tax Breakdown -->
                            <div id="taxBreakdown" style="display: none;">
                                <div class="row">
                                    <div class="col-md-3">
                                        <small class="text-muted">CGST (9%): ₹<span id="cgst_amount">0</span></small>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted">SGST (9%): ₹<span id="sgst_amount">0</span></small>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted">Professional Tax: ₹<span id="prof_tax">200</span></small>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted">Total Deductions: ₹<span id="total_deductions">0</span></small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary" id="assignButton" disabled>
                                    <i class="fas fa-check"></i> Assign Pension
                                </button>
                                <button type="reset" class="btn btn-secondary" onclick="resetForm()">
                                    <i class="fas fa-undo"></i> Reset
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Users Without Pension -->
                <div class="card">
                    <div class="card-header">
                        <h5>Personnel Without Pension Assignment (<?php echo count($usersWithoutPension); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($usersWithoutPension)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Service Number</th>
                                        <th>Service Type</th>
                                        <th>Rank</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usersWithoutPension as $user): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($user['full_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($user['service_number']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $user['service_type'] == 'Army' ? 'success' : 
                                                    ($user['service_type'] == 'Navy' ? 'primary' : 'info'); 
                                            ?>">
                                                <?php echo $user['service_type']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['rank_designation']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="selectUser(<?php echo $user['id']; ?>)">
                                                <i class="fas fa-plus"></i> Assign
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h5>All Personnel Have Pension Assigned</h5>
                            <p class="text-muted">Great! All eligible personnel have been assigned pensions.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateUserDetails() {
            const userSelect = document.getElementById('user_id');
            const selectedOption = userSelect.options[userSelect.selectedIndex];
            
            if (selectedOption.value) {
                document.getElementById('displayServiceType').textContent = selectedOption.dataset.serviceType;
                document.getElementById('displayRank').textContent = selectedOption.dataset.rank;
                document.getElementById('displayServiceNumber').textContent = selectedOption.dataset.serviceNumber;
                document.getElementById('userDetailsSection').style.display = 'block';
            } else {
                document.getElementById('userDetailsSection').style.display = 'none';
            }
            
            calculatePension();
        }
        
        function calculatePension() {
            const userSelect = document.getElementById('user_id');
            const pensionType = document.getElementById('pension_type').value;
            const loanDeduction = parseFloat(document.getElementById('loan_deduction').value || 0);
            const selectedOption = userSelect.options[userSelect.selectedIndex];
            
            if (!selectedOption.value || !pensionType) {
                resetCalculations();
                return;
            }
            
            const serviceType = selectedOption.dataset.serviceType;
            const rank = selectedOption.dataset.rank;
            
            fetch(`../api/calculate_pension_amount.php?service_type=${encodeURIComponent(serviceType)}&rank=${encodeURIComponent(rank)}&pension_type=${encodeURIComponent(pensionType)}&loan_deduction=${loanDeduction}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('calculated_amount').value = data.data.monthly_amount.toFixed(2);
                        document.getElementById('net_amount').value = data.data.net_amount.toFixed(2);
                        document.getElementById('cgst_amount').textContent = data.data.cgst.toFixed(2);
                        document.getElementById('sgst_amount').textContent = data.data.sgst.toFixed(2);
                        document.getElementById('total_deductions').textContent = data.data.total_deductions.toFixed(2);
                        document.getElementById('taxBreakdown').style.display = 'block';
                        document.getElementById('assignButton').disabled = false;
                    } else {
                        alert('Error calculating pension: ' + data.message);
                        resetCalculations();
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                    resetCalculations();
                });
        }
        
        function resetCalculations() {
            document.getElementById('calculated_amount').value = '';
            document.getElementById('net_amount').value = '';
            document.getElementById('cgst_amount').textContent = '0';
            document.getElementById('sgst_amount').textContent = '0';
            document.getElementById('total_deductions').textContent = '0';
            document.getElementById('taxBreakdown').style.display = 'none';
            document.getElementById('assignButton').disabled = true;
        }
        
        function selectUser(userId) {
            document.getElementById('user_id').value = userId;
            updateUserDetails();
            // Scroll to form
            document.getElementById('assignPensionForm').scrollIntoView({ behavior: 'smooth' });
        }
        
        function resetForm() {
            document.getElementById('assignPensionForm').reset();
            document.getElementById('userDetailsSection').style.display = 'none';
            resetCalculations();
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
