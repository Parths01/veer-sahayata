<?php
session_start();
require_once '../config/database.php';
requireLogin();
requireAdmin();

if (!isset($_GET['id'])) {
    header('Location: pension_management.php');
    exit();
}

$pensionId = $_GET['id'];

// Get pension details with user information
$stmt = $pdo->prepare("
    SELECT p.*, u.service_number, u.full_name, u.service_type, u.rank_designation, 
           u.phone, u.email, u.address, u.date_of_retirement, u.date_of_birth 
    FROM pension p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.id = ? AND p.status IN ('approved', 'disbursed')
");
$stmt->execute([$pensionId]);
$pension = $stmt->fetch();

if (!$pension) {
    header('Location: pension_management.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pension Certificate - <?php echo htmlspecialchars($pension['full_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            .page-break { page-break-before: always; }
        }
        
        .certificate {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px;
            border: 3px solid #0d6efd;
            border-radius: 10px;
        }
        
        .header-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .certificate-title {
            text-align: center;
            color: #0d6efd;
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 30px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .seal {
            width: 100px;
            height: 100px;
            border: 2px solid #0d6efd;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            background: #f8f9fa;
        }
        
        .signature-section {
            margin-top: 60px;
            display: flex;
            justify-content: space-between;
        }
        
        .signature-box {
            text-align: center;
            width: 200px;
        }
        
        .signature-line {
            border-bottom: 1px solid #000;
            margin-bottom: 5px;
            height: 50px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="no-print mb-3">
            <div class="d-flex justify-content-between align-items-center">
                <h4>Pension Certificate</h4>
                <div>
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print"></i> Print Certificate
                    </button>
                    <a href="pension_management.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <div class="certificate">
            <div class="header-logo">
                <div class="seal">
                    <strong>GOVT<br>OF<br>INDIA</strong>
                </div>
                <h5>VEER SAHAYATA</h5>
                <p class="mb-0">Ministry of Defence, Government of India</p>
            </div>

            <div class="certificate-title">
                Pension Certificate
            </div>

            <div class="row mb-4">
                <div class="col-12">
                    <p class="lead text-center">
                        This is to certify that <strong><?php echo htmlspecialchars($pension['full_name']); ?></strong> 
                        has been granted a <strong><?php echo ucfirst(str_replace('_', ' ', $pension['pension_type'])); ?></strong> 
                        under the Veer Sahayata scheme.
                    </p>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>Service Number:</strong></td>
                            <td><?php echo htmlspecialchars($pension['service_number']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Rank/Designation:</strong></td>
                            <td><?php echo htmlspecialchars($pension['rank_designation']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Service Type:</strong></td>
                            <td><?php echo htmlspecialchars($pension['service_type']); ?></td>
                        </tr>
                        <?php if ($pension['date_of_retirement']): ?>
                        <tr>
                            <td><strong>Date of Retirement:</strong></td>
                            <td><?php echo date('d F, Y', strtotime($pension['date_of_retirement'])); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>Monthly Pension:</strong></td>
                            <td class="text-primary fw-bold">₹<?php echo number_format($pension['monthly_amount'], 2); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Net Amount:</strong></td>
                            <td class="text-success fw-bold">₹<?php echo number_format($pension['net_amount'], 2); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Status:</strong></td>
                            <td>
                                <span class="badge bg-<?php echo $pension['status'] == 'disbursed' ? 'success' : 'info'; ?>">
                                    <?php echo ucfirst($pension['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Effective Date:</strong></td>
                            <td><?php echo date('d F, Y', strtotime($pension['created_at'])); ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <?php if ($pension['status'] == 'disbursed' && $pension['disbursement_date']): ?>
            <div class="row mt-3">
                <div class="col-12">
                    <div class="alert alert-success">
                        <strong>Disbursement Information:</strong><br>
                        Amount: ₹<?php echo number_format($pension['last_disbursed_amount'], 2); ?><br>
                        Date: <?php echo date('d F, Y', strtotime($pension['disbursement_date'])); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="row mt-4">
                <div class="col-12">
                    <p class="small text-muted">
                        <strong>Terms and Conditions:</strong><br>
                        1. This pension is subject to the rules and regulations of the Indian Armed Forces.<br>
                        2. Any change in personal details must be reported immediately.<br>
                        3. This certificate is valid for official purposes and should be preserved carefully.<br>
                        4. For any queries, contact the Veer Sahayata helpline.
                    </p>
                </div>
            </div>

            <div class="signature-section">
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <p class="small mb-0">
                        <strong>Pensioner's Signature</strong><br>
                        <?php echo htmlspecialchars($pension['full_name']); ?>
                    </p>
                </div>
                
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <p class="small mb-0">
                        <strong>Authorized Officer</strong><br>
                        Veer Sahayata Administration<br>
                        Date: <?php echo date('d F, Y'); ?>
                    </p>
                </div>
            </div>

            <div class="text-center mt-4">
                <p class="small text-muted">
                    Certificate ID: VS-PENSION-<?php echo str_pad($pension['id'], 6, '0', STR_PAD_LEFT); ?><br>
                    Generated on: <?php echo date('d F, Y H:i A'); ?>
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html>
