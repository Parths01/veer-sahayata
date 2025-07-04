<?php
session_start();
require_once '../config/database.php';
requireLogin();
requireAdmin();

// Get the same filters as the main page
$status = $_GET['status'] ?? '';
$pension_type = $_GET['pension_type'] ?? '';
$search = $_GET['search'] ?? '';

// Build the query
$sql = "SELECT p.*, u.service_number, u.full_name, u.service_type, u.rank_designation, u.phone, u.email 
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

$sql .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pensions = $stmt->fetchAll();

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="pensions_export_' . date('Y-m-d') . '.csv"');

// Create file pointer
$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, [
    'Service Number',
    'Full Name',
    'Rank/Designation',
    'Service Type',
    'Phone',
    'Email',
    'Pension Type',
    'Monthly Amount',
    'CGST',
    'SGST',
    'Loan Deduction',
    'Net Amount',
    'Status',
    'Last Disbursed Amount',
    'Disbursement Date',
    'Created Date'
]);

// Add data rows
foreach ($pensions as $pension) {
    fputcsv($output, [
        $pension['service_number'],
        $pension['full_name'],
        $pension['rank_designation'],
        $pension['service_type'],
        $pension['phone'],
        $pension['email'],
        ucfirst(str_replace('_', ' ', $pension['pension_type'])),
        $pension['monthly_amount'],
        $pension['cgst'],
        $pension['sgst'],
        $pension['loan_deduction'],
        $pension['net_amount'],
        ucfirst($pension['status']),
        $pension['last_disbursed_amount'] ?: 'N/A',
        $pension['disbursement_date'] ? date('d/m/Y', strtotime($pension['disbursement_date'])) : 'N/A',
        date('d/m/Y', strtotime($pension['created_at']))
    ]);
}

fclose($output);
exit();
?>
