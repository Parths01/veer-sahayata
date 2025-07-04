<?php
session_start();
require_once '../config/database.php';
require_once '../config/pension_rates.php';
requireLogin();
requireAdmin();

header('Content-Type: application/json');

if (!isset($_GET['service_type']) || !isset($_GET['rank']) || !isset($_GET['pension_type'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'Missing required parameters: service_type, rank, pension_type'
    ]);
    exit();
}

$serviceType = $_GET['service_type'];
$rank = $_GET['rank'];
$pensionType = $_GET['pension_type'];
$loanDeduction = floatval($_GET['loan_deduction'] ?? 0);

try {
    // Get base pension amount
    $baseAmount = getPensionAmountByRank($serviceType, $rank, $pensionType);
    
    if ($baseAmount == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Pension rate not found for this rank and service type'
        ]);
        exit();
    }
    
    // Calculate net pension with deductions
    $calculation = calculateNetPension($baseAmount, $loanDeduction);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'service_type' => $serviceType,
            'rank' => $rank,
            'pension_type' => $pensionType,
            'monthly_amount' => $calculation['gross_amount'],
            'cgst' => $calculation['cgst'],
            'sgst' => $calculation['sgst'],
            'professional_tax' => $calculation['professional_tax'],
            'loan_deduction' => $calculation['loan_deduction'],
            'total_deductions' => $calculation['total_deductions'],
            'net_amount' => $calculation['net_amount']
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error calculating pension: ' . $e->getMessage()
    ]);
}
?>
