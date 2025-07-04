<?php
// Pension Dashboard Widget
// Include this in your admin dashboard to show pension overview

require_once '../config/database.php';

// Get pension statistics
$pensionStats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM pension")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM pension WHERE status = 'pending'")->fetchColumn(),
    'approved' => $pdo->query("SELECT COUNT(*) FROM pension WHERE status = 'approved'")->fetchColumn(),
    'disbursed' => $pdo->query("SELECT COUNT(*) FROM pension WHERE status = 'disbursed'")->fetchColumn(),
    'total_amount' => $pdo->query("SELECT SUM(monthly_amount) FROM pension WHERE status IN ('approved', 'disbursed')")->fetchColumn() ?: 0,
    'monthly_disbursement' => $pdo->query("SELECT SUM(last_disbursed_amount) FROM pension WHERE status = 'disbursed' AND MONTH(disbursement_date) = MONTH(CURRENT_DATE()) AND YEAR(disbursement_date) = YEAR(CURRENT_DATE())")->fetchColumn() ?: 0
];

// Get recent pension activities
$recentPensions = $pdo->query("
    SELECT p.*, u.full_name, u.service_number 
    FROM pension p 
    JOIN users u ON p.user_id = u.id 
    ORDER BY p.created_at DESC 
    LIMIT 5
")->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <h5 class="text-primary">
            <i class="fas fa-money-check-alt"></i> Pension Management Overview
        </h5>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body text-center">
                <i class="fas fa-users fa-2x mb-2"></i>
                <h4><?php echo $pensionStats['total']; ?></h4>
                <p class="mb-0">Total Pensioners</p>
            </div>
            <div class="card-footer">
                <a href="pension_management.php" class="text-white text-decoration-none">
                    <small>View All <i class="fas fa-arrow-right"></i></small>
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-warning text-dark h-100">
            <div class="card-body text-center">
                <i class="fas fa-clock fa-2x mb-2"></i>
                <h4><?php echo $pensionStats['pending']; ?></h4>
                <p class="mb-0">Pending Approval</p>
            </div>
            <div class="card-footer">
                <a href="pension_management.php?status=pending" class="text-dark text-decoration-none">
                    <small>Review <i class="fas fa-arrow-right"></i></small>
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-success text-white h-100">
            <div class="card-body text-center">
                <i class="fas fa-check-circle fa-2x mb-2"></i>
                <h4><?php echo $pensionStats['approved']; ?></h4>
                <p class="mb-0">Ready to Disburse</p>
            </div>
            <div class="card-footer">
                <a href="pension_management.php?status=approved" class="text-white text-decoration-none">
                    <small>Disburse <i class="fas fa-arrow-right"></i></small>
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-info text-white h-100">
            <div class="card-body text-center">
                <i class="fas fa-money-bill-wave fa-2x mb-2"></i>
                <h4>₹<?php echo number_format($pensionStats['monthly_disbursement']/1000, 0); ?>K</h4>
                <p class="mb-0">This Month</p>
            </div>
            <div class="card-footer">
                <a href="pension_management.php?status=disbursed" class="text-white text-decoration-none">
                    <small>View Details <i class="fas fa-arrow-right"></i></small>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Recent Pension Applications</h6>
                <a href="pension_management.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (!empty($recentPensions)): ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Pensioner</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPensions as $pension): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($pension['full_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($pension['service_number']); ?></small>
                                </td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $pension['pension_type'])); ?></td>
                                <td>₹<?php echo number_format($pension['monthly_amount'], 0); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $pension['status'] == 'approved' ? 'success' : 
                                            ($pension['status'] == 'disbursed' ? 'info' : 'warning'); 
                                    ?> badge-sm">
                                        <?php echo ucfirst($pension['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($pension['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-3">
                    <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                    <p class="text-muted mb-0">No pension applications yet</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Financial Summary</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Total Committed Amount:</span>
                        <strong>₹<?php echo number_format($pensionStats['total_amount'], 0); ?></strong>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>This Month Disbursed:</span>
                        <strong class="text-success">₹<?php echo number_format($pensionStats['monthly_disbursement'], 0); ?></strong>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Average Pension:</span>
                        <strong>₹<?php echo $pensionStats['total'] > 0 ? number_format($pensionStats['total_amount'] / $pensionStats['total'], 0) : '0'; ?></strong>
                    </div>
                </div>
                <hr>
                <div class="text-center">
                    <a href="pension_management.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-cog"></i> Manage Pensions
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
