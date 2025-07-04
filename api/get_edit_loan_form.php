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

if (!$loanId) {
    echo json_encode(['success' => false, 'message' => 'Loan ID is required']);
    exit;
}

try {
    // Get loan details with user information
    $stmt = $pdo->prepare("
        SELECT l.*, 
               u.service_number, u.full_name, u.service_type, u.rank_designation
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
    
    // Get max eligible amount for validation
    $maxAmount = getMaxLoanAmount($loan['service_type'], $loan['rank_designation'], $loan['loan_type']);
    $loanTerms = getLoanTerms($loan['loan_type']);
    
    // Generate the edit form HTML
    $html = '
    <input type="hidden" name="loan_id" value="' . $loan['id'] . '">
    
    <div class="row">
        <div class="col-md-6">
            <h6 class="border-bottom pb-2 mb-3"><i class="fas fa-user"></i> Applicant Information</h6>
            <div class="mb-3">
                <label class="form-label"><strong>Applicant:</strong></label>
                <div class="form-control-plaintext">' . htmlspecialchars($loan['full_name']) . '</div>
            </div>
            <div class="mb-3">
                <label class="form-label"><strong>Service Number:</strong></label>
                <div class="form-control-plaintext">' . htmlspecialchars($loan['service_number']) . '</div>
            </div>
            <div class="mb-3">
                <label class="form-label"><strong>Rank:</strong></label>
                <div class="form-control-plaintext">' . htmlspecialchars($loan['rank_designation']) . '</div>
            </div>
            <div class="mb-3">
                <label class="form-label"><strong>Max Eligible:</strong></label>
                <div class="form-control-plaintext text-info">₹' . number_format($maxAmount, 0) . '</div>
            </div>
        </div>
        
        <div class="col-md-6">
            <h6 class="border-bottom pb-2 mb-3"><i class="fas fa-edit"></i> Edit Loan Details</h6>
            
            <div class="mb-3">
                <label for="edit_loan_type" class="form-label">Loan Type *</label>
                <select class="form-select" id="edit_loan_type" name="loan_type" required onchange="updateLoanCalculation()">
                    <option value="personal_loan"' . ($loan['loan_type'] == 'personal_loan' ? ' selected' : '') . '>Personal Loan</option>
                    <option value="home_loan"' . ($loan['loan_type'] == 'home_loan' ? ' selected' : '') . '>Home Loan</option>
                    <option value="vehicle_loan"' . ($loan['loan_type'] == 'vehicle_loan' ? ' selected' : '') . '>Vehicle Loan</option>
                    <option value="education_loan"' . ($loan['loan_type'] == 'education_loan' ? ' selected' : '') . '>Education Loan</option>
                    <option value="medical_loan"' . ($loan['loan_type'] == 'medical_loan' ? ' selected' : '') . '>Medical Loan</option>
                    <option value="business_loan"' . ($loan['loan_type'] == 'business_loan' ? ' selected' : '') . '>Business Loan</option>
                </select>
            </div>
            
            <div class="mb-3">
                <label for="edit_loan_amount" class="form-label">Loan Amount *</label>
                <div class="input-group">
                    <span class="input-group-text">₹</span>
                    <input type="number" class="form-control" id="edit_loan_amount" name="loan_amount" 
                           value="' . $loan['loan_amount'] . '" min="1000" max="' . $maxAmount . '" 
                           step="1000" required onchange="updateLoanCalculation()">
                </div>
                <small class="text-muted">Maximum eligible: ₹' . number_format($maxAmount, 0) . '</small>
            </div>
            
            <div class="mb-3">
                <label for="edit_tenure_years" class="form-label">Tenure (Years) *</label>
                <select class="form-select" id="edit_tenure_years" name="tenure_years" required onchange="updateLoanCalculation()">
                    <option value="1"' . ($loan['tenure_years'] == 1 ? ' selected' : '') . '>1 Year</option>
                    <option value="2"' . ($loan['tenure_years'] == 2 ? ' selected' : '') . '>2 Years</option>
                    <option value="3"' . ($loan['tenure_years'] == 3 ? ' selected' : '') . '>3 Years</option>
                    <option value="5"' . ($loan['tenure_years'] == 5 ? ' selected' : '') . '>5 Years</option>
                    <option value="7"' . ($loan['tenure_years'] == 7 ? ' selected' : '') . '>7 Years</option>
                    <option value="10"' . ($loan['tenure_years'] == 10 ? ' selected' : '') . '>10 Years</option>
                    <option value="15"' . ($loan['tenure_years'] == 15 ? ' selected' : '') . '>15 Years</option>
                    <option value="20"' . ($loan['tenure_years'] == 20 ? ' selected' : '') . '>20 Years</option>
                </select>
            </div>
        </div>
    </div>
    
    <div class="row mt-3">
        <div class="col-md-6">
            <div class="mb-3">
                <label for="edit_interest_rate" class="form-label">Interest Rate (%) *</label>
                <div class="input-group">
                    <input type="number" class="form-control" id="edit_interest_rate" name="interest_rate" 
                           value="' . $loan['interest_rate'] . '" min="' . $loanTerms['interest_rate'] . '" 
                           max="20" step="0.1" required onchange="updateLoanCalculation()">
                    <span class="input-group-text">%</span>
                </div>
                <small class="text-muted">Minimum rate: ' . $loanTerms['interest_rate'] . '%</small>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="mb-3">
                <label for="edit_processing_fee" class="form-label">Processing Fee</label>
                <div class="input-group">
                    <span class="input-group-text">₹</span>
                    <input type="number" class="form-control" id="edit_processing_fee" name="processing_fee" 
                           value="' . $loan['processing_fee'] . '" min="0" step="100">
                </div>
                <small class="text-muted">Optional processing charges</small>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-12">
            <h6 class="border-bottom pb-2 mb-3"><i class="fas fa-calculator"></i> Calculated Values</h6>
            <div class="alert alert-info">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Monthly EMI:</strong><br>
                        <span class="h5 text-primary" id="calculated_emi">₹' . number_format($loan['emi'], 0) . '</span>
                    </div>
                    <div class="col-md-3">
                        <strong>Total Interest:</strong><br>
                        <span class="h6 text-warning" id="total_interest">₹' . number_format(($loan['emi'] * $loan['tenure_years'] * 12) - $loan['loan_amount'], 0) . '</span>
                    </div>
                    <div class="col-md-3">
                        <strong>Total Amount:</strong><br>
                        <span class="h6 text-danger" id="total_amount">₹' . number_format($loan['emi'] * $loan['tenure_years'] * 12, 0) . '</span>
                    </div>
                    <div class="col-md-3">
                        <strong>Status:</strong><br>
                        <span class="badge bg-warning">Pending Update</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <input type="hidden" id="calculated_emi_value" name="emi" value="' . $loan['emi'] . '">
    
    <script>
        function updateLoanCalculation() {
            const amount = parseFloat(document.getElementById("edit_loan_amount").value || 0);
            const rate = parseFloat(document.getElementById("edit_interest_rate").value || 0);
            const tenure = parseInt(document.getElementById("edit_tenure_years").value || 0);
            
            if (amount > 0 && rate > 0 && tenure > 0) {
                // Calculate EMI using standard formula
                const monthlyRate = rate / (12 * 100);
                const numPayments = tenure * 12;
                const emi = (amount * monthlyRate * Math.pow(1 + monthlyRate, numPayments)) / 
                           (Math.pow(1 + monthlyRate, numPayments) - 1);
                
                const totalAmount = emi * numPayments;
                const totalInterest = totalAmount - amount;
                
                // Update display
                document.getElementById("calculated_emi").textContent = "₹" + Math.round(emi).toLocaleString();
                document.getElementById("total_interest").textContent = "₹" + Math.round(totalInterest).toLocaleString();
                document.getElementById("total_amount").textContent = "₹" + Math.round(totalAmount).toLocaleString();
                document.getElementById("calculated_emi_value").value = Math.round(emi);
            }
        }
        
        // Initialize calculation functions
        function initializeLoanCalculations() {
            updateLoanCalculation();
        }
        
        // Auto-calculate on load
        setTimeout(updateLoanCalculation, 100);
    </script>';
    
    echo json_encode([
        'success' => true,
        'html' => $html,
        'loan' => $loan
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
