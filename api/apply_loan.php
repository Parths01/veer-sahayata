<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    $user_id = $_SESSION['user_id'];
    $loan_type = $_POST['loan_type'];
    $loan_amount = floatval($_POST['loan_amount']);
    $interest_rate = floatval($_POST['interest_rate']) ?: 10.0;
    $tenure = intval($_POST['tenure']);
    $purpose = trim($_POST['purpose']);

    // Validate input
    if (empty($loan_type) || $loan_amount <= 0 || $tenure <= 0 || empty($purpose)) {
        throw new Exception('All fields are required and must be valid');
    }

    if ($loan_amount < 10000 || $loan_amount > 5000000) {
        throw new Exception('Loan amount must be between ₹10,000 and ₹50,00,000');
    }

    // Calculate EMI
    $monthly_rate = $interest_rate / 100 / 12;
    $monthly_emi = ($loan_amount * $monthly_rate * pow(1 + $monthly_rate, $tenure)) / 
                   (pow(1 + $monthly_rate, $tenure) - 1);

    // Set due date (first EMI due next month)
    $due_date = date('Y-m-d', strtotime('+1 month'));

    // Insert loan application
    $stmt = $pdo->prepare("INSERT INTO loans (user_id, loan_type, loan_amount, outstanding_balance, monthly_emi, due_date, interest_rate, status, purpose, applied_date) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending_approval', ?, CURDATE())");
    $stmt->execute([
        $user_id, 
        $loan_type, 
        $loan_amount, 
        $loan_amount, // Initially, outstanding balance equals loan amount
        $monthly_emi,
        $due_date,
        $interest_rate,
        $purpose
    ]);

    echo json_encode([
        'success' => true, 
        'message' => 'Loan application submitted successfully. You will be notified once it is reviewed.',
        'loan_id' => $pdo->lastInsertId(),
        'monthly_emi' => round($monthly_emi, 2)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>
