<?php
session_start();
require_once '../config/database.php';
require_once '../config/pension_rates.php';
requireLogin();
requireAdmin();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pension Rates - Veer Sahayata Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .rate-table {
            font-size: 0.9em;
        }
        .rank-header {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .service-section {
            margin-bottom: 2rem;
        }
        .amount-cell {
            text-align: right;
            font-weight: 500;
        }
        .print-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        @media print {
            .no-print { display: none !important; }
            .print-btn { display: none !important; }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom no-print">
                    <h1 class="h2">Pension Rates by Rank</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-sm btn-outline-primary" onclick="window.print()">
                                <i class="fas fa-print"></i> Print Rates
                            </button>
                            <a href="pension_management.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Back
                            </a>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info no-print">
                    <i class="fas fa-info-circle"></i>
                    <strong>Current Pension Rates:</strong> These rates are automatically applied when assigning pensions based on rank and service type. 
                    All amounts are in Indian Rupees (₹) per month.
                </div>

                <?php foreach (getAllServiceTypes() as $serviceType): ?>
                <div class="service-section">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="mb-0">
                                <i class="fas fa-<?php 
                                    echo $serviceType == 'Army' ? 'user-shield' : 
                                        ($serviceType == 'Navy' ? 'anchor' : 'plane'); 
                                ?>"></i>
                                <?php echo $serviceType; ?> Pension Rates
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped rate-table">
                                    <thead>
                                        <tr class="rank-header">
                                            <th style="width: 30%;">Rank/Designation</th>
                                            <th style="width: 25%;" class="text-center">Service Pension</th>
                                            <th style="width: 25%;" class="text-center">Family Pension</th>
                                            <th style="width: 20%;" class="text-center">Disability Pension</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $ranks = getRanksByServiceType($serviceType);
                                        $isOfficer = true;
                                        $currentCategory = '';
                                        
                                        foreach ($ranks as $rank): 
                                            // Determine category for section headers
                                            $newCategory = '';
                                            if ($serviceType == 'Army') {
                                                if (in_array($rank, ['Lieutenant', 'Captain', 'Major', 'Lieutenant Colonel', 'Colonel', 'Brigadier', 'Major General', 'Lieutenant General', 'General'])) {
                                                    $newCategory = 'Officers';
                                                } elseif (in_array($rank, ['Naib Subedar', 'Subedar', 'Subedar Major'])) {
                                                    $newCategory = 'Junior Commissioned Officers (JCOs)';
                                                } else {
                                                    $newCategory = 'Non-Commissioned Officers & Other Ranks';
                                                }
                                            } elseif ($serviceType == 'Navy') {
                                                if (in_array($rank, ['Sub Lieutenant', 'Lieutenant', 'Lieutenant Commander', 'Commander', 'Captain', 'Commodore', 'Rear Admiral', 'Vice Admiral', 'Admiral'])) {
                                                    $newCategory = 'Officers';
                                                } else {
                                                    $newCategory = 'Sailors';
                                                }
                                            } elseif ($serviceType == 'Air Force') {
                                                if (in_array($rank, ['Flying Officer', 'Flight Lieutenant', 'Squadron Leader', 'Wing Commander', 'Group Captain', 'Air Commodore', 'Air Vice Marshal', 'Air Marshal', 'Air Chief Marshal'])) {
                                                    $newCategory = 'Officers';
                                                } else {
                                                    $newCategory = 'Airmen';
                                                }
                                            }
                                            
                                            // Show category header
                                            if ($newCategory != $currentCategory) {
                                                $currentCategory = $newCategory;
                                                echo '<tr><td colspan="4" class="rank-header text-primary"><strong>' . $currentCategory . '</strong></td></tr>';
                                            }
                                            
                                            $servicePension = getPensionAmountByRank($serviceType, $rank, 'service_pension');
                                            $familyPension = getPensionAmountByRank($serviceType, $rank, 'family_pension');
                                            $disabilityPension = getPensionAmountByRank($serviceType, $rank, 'disability_pension');
                                        ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($rank); ?></strong></td>
                                            <td class="amount-cell">₹<?php echo number_format($servicePension, 0); ?></td>
                                            <td class="amount-cell">₹<?php echo number_format($familyPension, 0); ?></td>
                                            <td class="amount-cell">₹<?php echo number_format($disabilityPension, 0); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Tax Information -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-calculator"></i> Tax and Deduction Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Tax Rates</h6>
                                <ul class="list-unstyled">
                                    <li><strong>CGST:</strong> 9% of gross pension amount</li>
                                    <li><strong>SGST:</strong> 9% of gross pension amount</li>
                                    <li><strong>Professional Tax:</strong> ₹200 per month (fixed)</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Calculation Formula</h6>
                                <div class="bg-light p-3 rounded">
                                    <code>
                                        Net Pension = Gross Amount - CGST - SGST - Professional Tax - Loan Deductions
                                    </code>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <h6>Example Calculation (₹50,000 gross amount)</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <tr>
                                        <td>Gross Amount:</td>
                                        <td class="text-end">₹50,000.00</td>
                                    </tr>
                                    <tr>
                                        <td>CGST (9%):</td>
                                        <td class="text-end">₹4,500.00</td>
                                    </tr>
                                    <tr>
                                        <td>SGST (9%):</td>
                                        <td class="text-end">₹4,500.00</td>
                                    </tr>
                                    <tr>
                                        <td>Professional Tax:</td>
                                        <td class="text-end">₹200.00</td>
                                    </tr>
                                    <tr>
                                        <td>Loan Deduction:</td>
                                        <td class="text-end">₹0.00</td>
                                    </tr>
                                    <tr class="table-primary">
                                        <td><strong>Net Amount:</strong></td>
                                        <td class="text-end"><strong>₹40,800.00</strong></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 no-print">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Note:</strong> These rates are subject to government policies and may be updated. 
                        Last updated: July 2025. Contact system administrator for rate modifications.
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Print Button -->
    <button class="btn btn-primary print-btn" onclick="window.print()" title="Print Pension Rates">
        <i class="fas fa-print"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
