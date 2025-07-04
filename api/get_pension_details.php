<?php
session_start();
require_once '../config/database.php';
requireLogin();
requireAdmin();

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Pension ID required']);
    exit();
}

$pensionId = $_GET['id'];

try {
    // Get pension details with user information
    $stmt = $pdo->prepare("
        SELECT p.*, u.service_number, u.full_name, u.service_type, u.rank_designation, 
               u.phone, u.email, u.address, u.date_of_retirement 
        FROM pension p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$pensionId]);
    $pension = $stmt->fetch();
    
    if (!$pension) {
        echo json_encode(['success' => false, 'message' => 'Pension not found']);
        exit();
    }
    
    ob_start();
    ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-primary">Pensioner Information</h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>Name:</strong></td>
                        <td><?php echo $pension['full_name']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Service Number:</strong></td>
                        <td><?php echo $pension['service_number']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Rank/Designation:</strong></td>
                        <td><?php echo $pension['rank_designation']; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Service Type:</strong></td>
                        <td>
                            <span class="badge bg-<?php 
                                echo $pension['service_type'] == 'Army' ? 'success' : 
                                    ($pension['service_type'] == 'Navy' ? 'primary' : 'info'); 
                            ?>">
                                <?php echo $pension['service_type']; ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Date of Retirement:</strong></td>
                        <td><?php echo $pension['date_of_retirement'] ? date('d/m/Y', strtotime($pension['date_of_retirement'])) : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Contact:</strong></td>
                        <td>
                            <?php echo $pension['phone']; ?><br>
                            <?php echo $pension['email']; ?>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="text-primary">Pension Details</h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>Pension Type:</strong></td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $pension['pension_type'])); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Monthly Amount:</strong></td>
                        <td class="text-primary"><strong>₹<?php echo number_format($pension['monthly_amount'], 2); ?></strong></td>
                    </tr>
                    <tr>
                        <td><strong>CGST:</strong></td>
                        <td>₹<?php echo number_format($pension['cgst'], 2); ?></td>
                    </tr>
                    <tr>
                        <td><strong>SGST:</strong></td>
                        <td>₹<?php echo number_format($pension['sgst'], 2); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Loan Deduction:</strong></td>
                        <td>₹<?php echo number_format($pension['loan_deduction'], 2); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Net Amount:</strong></td>
                        <td class="text-success"><strong>₹<?php echo number_format($pension['net_amount'], 2); ?></strong></td>
                    </tr>
                    <tr>
                        <td><strong>Status:</strong></td>
                        <td>
                            <span class="badge bg-<?php 
                                echo $pension['status'] == 'approved' ? 'success' : 
                                    ($pension['status'] == 'disbursed' ? 'info' : 'warning'); 
                            ?>">
                                <?php echo ucfirst($pension['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php if ($pension['last_disbursed_amount']): ?>
                    <tr>
                        <td><strong>Last Disbursed:</strong></td>
                        <td>₹<?php echo number_format($pension['last_disbursed_amount'], 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($pension['disbursement_date']): ?>
                    <tr>
                        <td><strong>Disbursement Date:</strong></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($pension['disbursement_date'])); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        
        <hr>
        
        <div class="row">
            <div class="col-12">
                <h6 class="text-primary">Address</h6>
                <p><?php echo $pension['address'] ?: 'No address provided'; ?></p>
                
                <h6 class="text-primary">Record Information</h6>
                <p>
                    <strong>Created:</strong> <?php echo date('d/m/Y H:i', strtotime($pension['created_at'])); ?>
                </p>
            </div>
        </div>
    </div>
    <?php
    
    $html = ob_get_clean();
    echo json_encode(['success' => true, 'html' => $html]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
