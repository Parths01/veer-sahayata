<?php
session_start();
require_once '../config/database.php';
require_once '../config/loan_rates.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$loanId = $_GET['id'] ?? '';
$basic = $_GET['basic'] ?? false;

if (!$loanId) {
    echo json_encode(['success' => false, 'message' => 'Loan ID is required']);
    exit;
}

try {
    // Get loan details with user information
    $stmt = $pdo->prepare("
        SELECT l.*, 
               u.service_number, u.full_name, u.email, u.phone, u.address,
               u.service_type, u.rank_designation, u.date_of_birth,
               u.date_of_joining, u.date_of_retirement, u.photo
        FROM loans l 
        JOIN users u ON l.user_id = u.id 
        WHERE l.id = ?
    ");
    $stmt->execute([$loanId]);
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$loan) {
        echo json_encode(['success' => false, 'message' => 'Loan not found']);
        exit;
    }
    
    // If basic info only requested (for reject/disburse modals)
    if ($basic) {
        echo json_encode([
            'success' => true,
            'loan' => [
                'full_name' => $loan['full_name'],
                'loan_type' => getLoanTypeDisplayName($loan['loan_type']),
                'loan_amount' => number_format($loan['loan_amount'], 0),
                'emi' => number_format($loan['emi'], 0)
            ]
        ]);
        exit;
    }
    
    // Calculate loan eligibility and rates for display
    $maxAmount = getMaxLoanAmount($loan['service_type'], $loan['rank_designation'], $loan['loan_type']);
    $loanTerms = getLoanTerms($loan['loan_type']);
    
    // Calculate years of service if joining date is available
    $yearsOfService = 'N/A';
    if ($loan['date_of_joining']) {
        $joiningDate = new DateTime($loan['date_of_joining']);
        $currentDate = new DateTime();
        $yearsOfService = $currentDate->diff($joiningDate)->y;
    }
    
    // Format dates
    $createdDate = date('d/m/Y H:i', strtotime($loan['created_at']));
    $approvedDate = $loan['approved_date'] ? date('d/m/Y H:i', strtotime($loan['approved_date'])) : 'N/A';
    $disbursementDate = $loan['disbursement_date'] ? date('d/m/Y H:i', strtotime($loan['disbursement_date'])) : 'N/A';
    $rejectedDate = $loan['rejected_date'] ? date('d/m/Y H:i', strtotime($loan['rejected_date'])) : 'N/A';
    
    // Generate status badge
    $statusClass = '';
    switch ($loan['status']) {
        case 'approved': $statusClass = 'bg-success'; break;
        case 'disbursed': $statusClass = 'bg-info'; break;
        case 'rejected': $statusClass = 'bg-danger'; break;
        default: $statusClass = 'bg-warning text-dark';
    }
    
    // Build HTML content for the view modal
    $html = '
    <div data-loan-id="' . $loan['id'] . '">
        <div class="row">
            <div class="col-md-6">
                <h6 class="border-bottom pb-2 mb-3"><i class="fas fa-user"></i> Applicant Information</h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>Full Name:</strong></td>
                        <td>' . htmlspecialchars($loan['full_name']) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Service Number:</strong></td>
                        <td>' . htmlspecialchars($loan['service_number']) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Service Type:</strong></td>
                        <td>
                            <span class="badge bg-' . ($loan['service_type'] == 'Army' ? 'success' : ($loan['service_type'] == 'Navy' ? 'primary' : 'info')) . '">
                                ' . $loan['service_type'] . '
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Rank:</strong></td>
                        <td>' . htmlspecialchars($loan['rank_designation']) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Years of Service:</strong></td>
                        <td>' . $yearsOfService . ' years</td>
                    </tr>
                    <tr>
                        <td><strong>Email:</strong></td>
                        <td>' . htmlspecialchars($loan['email']) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Phone:</strong></td>
                        <td>' . htmlspecialchars($loan['phone']) . '</td>
                    </tr>
                </table>
            </div>
            
            <div class="col-md-6">
                <h6 class="border-bottom pb-2 mb-3"><i class="fas fa-money-check-alt"></i> Loan Information</h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>Loan Type:</strong></td>
                        <td>' . getLoanTypeDisplayName($loan['loan_type']) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Loan Amount:</strong></td>
                        <td class="text-primary"><strong>₹' . number_format($loan['loan_amount'], 0) . '</strong></td>
                    </tr>
                    <tr>
                        <td><strong>Tenure:</strong></td>
                        <td>' . $loan['tenure_years'] . ' years</td>
                    </tr>
                    <tr>
                        <td><strong>Interest Rate:</strong></td>
                        <td>' . $loan['interest_rate'] . '%</td>
                    </tr>
                    <tr>
                        <td><strong>Monthly EMI:</strong></td>
                        <td class="text-success"><strong>₹' . number_format($loan['emi'], 0) . '</strong></td>
                    </tr>
                    ' . ($loan['processing_fee'] > 0 ? '
                    <tr>
                        <td><strong>Processing Fee:</strong></td>
                        <td>₹' . number_format($loan['processing_fee'], 0) . '</td>
                    </tr>
                    ' : '') . '
                    <tr>
                        <td><strong>Purpose:</strong></td>
                        <td>' . htmlspecialchars($loan['purpose'] ?: 'Not specified') . '</td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-md-6">
                <h6 class="border-bottom pb-2 mb-3"><i class="fas fa-clock"></i> Timeline</h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>Application Date:</strong></td>
                        <td>' . $createdDate . '</td>
                    </tr>
                    <tr>
                        <td><strong>Approved Date:</strong></td>
                        <td>' . $approvedDate . '</td>
                    </tr>
                    <tr>
                        <td><strong>Disbursement Date:</strong></td>
                        <td>' . $disbursementDate . '</td>
                    </tr>
                    ' . ($loan['status'] == 'rejected' ? '
                    <tr>
                        <td><strong>Rejected Date:</strong></td>
                        <td>' . $rejectedDate . '</td>
                    </tr>
                    ' : '') . '
                </table>
            </div>
            
            <div class="col-md-6">
                <h6 class="border-bottom pb-2 mb-3"><i class="fas fa-info-circle"></i> Status & Eligibility</h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>Current Status:</strong></td>
                        <td><span class="badge ' . $statusClass . '">' . ucfirst($loan['status']) . '</span></td>
                    </tr>
                    <tr>
                        <td><strong>Max Eligible Amount:</strong></td>
                        <td>₹' . number_format($maxAmount, 0) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Max Tenure:</strong></td>
                        <td>' . $loanTerms['max_tenure'] . ' years</td>
                    </tr>
                    <tr>
                        <td><strong>Min Interest Rate:</strong></td>
                        <td>' . $loanTerms['interest_rate'] . '%</td>
                    </tr>
                    ' . ($loan['status'] == 'disbursed' && $loan['disbursed_amount'] ? '
                    <tr>
                        <td><strong>Disbursed Amount:</strong></td>
                        <td class="text-info"><strong>₹' . number_format($loan['disbursed_amount'], 0) . '</strong></td>
                    </tr>
                    ' : '') . '
                </table>
            </div>
        </div>
        
        ' . ($loan['rejection_reason'] ? '
        <div class="row mt-3">
            <div class="col-12">
                <h6 class="border-bottom pb-2 mb-3 text-danger"><i class="fas fa-times-circle"></i> Rejection Details</h6>
                <div class="alert alert-danger">
                    <strong>Reason:</strong><br>
                    ' . nl2br(htmlspecialchars($loan['rejection_reason'])) . '
                </div>
            </div>
        </div>
        ' : '') . '
        
        <div class="row mt-3">
            <div class="col-12">
                <h6 class="border-bottom pb-2 mb-3"><i class="fas fa-calculator"></i> Loan Calculation Breakdown</h6>
                <div class="alert alert-info">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Principal Amount:</strong><br>
                            ₹' . number_format($loan['loan_amount'], 0) . '
                        </div>
                        <div class="col-md-3">
                            <strong>Total Interest:</strong><br>
                            ₹' . number_format(($loan['emi'] * $loan['tenure_years'] * 12) - $loan['loan_amount'], 0) . '
                        </div>
                        <div class="col-md-3">
                            <strong>Total Repayment:</strong><br>
                            ₹' . number_format($loan['emi'] * $loan['tenure_years'] * 12, 0) . '
                        </div>
                        <div class="col-md-3">
                            <strong>Monthly EMI:</strong><br>
                            ₹' . number_format($loan['emi'], 0) . '
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';
    
    echo json_encode([
        'success' => true,
        'html' => $html,
        'loan' => $loan
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

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
