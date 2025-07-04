<?php
session_start();
require_once '../config/database.php';
require_once '../config/pension_rates.php';
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
        SELECT p.*, u.full_name, u.service_number, u.service_type, u.rank_designation 
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
    
    // Get all ranks for the user's service type
    $ranks = getRanksByServiceType($pension['service_type']);
    
    ob_start();
    ?>
    <form method="POST" id="editPensionForm">
        <input type="hidden" name="action" value="update_pension">
        <input type="hidden" name="pension_id" value="<?php echo $pension['id']; ?>">
        <input type="hidden" id="edit_service_type" value="<?php echo $pension['service_type']; ?>">
        
        <div class="mb-3">
            <label class="form-label">Pensioner</label>
            <input type="text" class="form-control" value="<?php echo $pension['full_name'] . ' (' . $pension['service_number'] . ')'; ?>" readonly>
            <small class="text-muted"><?php echo $pension['service_type']; ?> - <?php echo $pension['rank_designation']; ?></small>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="edit_pension_type" class="form-label">Pension Type *</label>
                    <select class="form-select" id="edit_pension_type" name="pension_type" required onchange="calculatePensionAmount()">
                        <option value="service_pension" <?php echo $pension['pension_type'] == 'service_pension' ? 'selected' : ''; ?>>Service Pension</option>
                        <option value="family_pension" <?php echo $pension['pension_type'] == 'family_pension' ? 'selected' : ''; ?>>Family Pension</option>
                        <option value="disability_pension" <?php echo $pension['pension_type'] == 'disability_pension' ? 'selected' : ''; ?>>Disability Pension</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="edit_rank" class="form-label">Rank/Designation</label>
                    <select class="form-select" id="edit_rank" name="rank" onchange="calculatePensionAmount()">
                        <?php foreach ($ranks as $rank): ?>
                        <option value="<?php echo $rank; ?>" <?php echo $pension['rank_designation'] == $rank ? 'selected' : ''; ?>>
                            <?php echo $rank; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="edit_monthly_amount" class="form-label">Monthly Amount *</label>
                    <div class="input-group">
                        <span class="input-group-text">₹</span>
                        <input type="number" class="form-control" id="edit_monthly_amount" name="monthly_amount" 
                               value="<?php echo $pension['monthly_amount']; ?>" step="0.01" required readonly>
                        <button type="button" class="btn btn-outline-secondary" onclick="calculatePensionAmount()" title="Auto-calculate based on rank">
                            <i class="fas fa-calculator"></i>
                        </button>
                    </div>
                    <small class="text-muted">Amount will be auto-calculated based on rank and pension type</small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="edit_loan_deduction" class="form-label">Loan Deduction</label>
                    <div class="input-group">
                        <span class="input-group-text">₹</span>
                        <input type="number" class="form-control" id="edit_loan_deduction" name="loan_deduction" 
                               value="<?php echo $pension['loan_deduction']; ?>" step="0.01" onchange="calculatePensionAmount()">
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-4">
                <div class="mb-3">
                    <label for="edit_cgst" class="form-label">CGST (9%)</label>
                    <div class="input-group">
                        <span class="input-group-text">₹</span>
                        <input type="number" class="form-control" id="edit_cgst" name="cgst" 
                               value="<?php echo $pension['cgst']; ?>" step="0.01" readonly>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="mb-3">
                    <label for="edit_sgst" class="form-label">SGST (9%)</label>
                    <div class="input-group">
                        <span class="input-group-text">₹</span>
                        <input type="number" class="form-control" id="edit_sgst" name="sgst" 
                               value="<?php echo $pension['sgst']; ?>" step="0.01" readonly>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="mb-3">
                    <label for="edit_professional_tax" class="form-label">Professional Tax</label>
                    <div class="input-group">
                        <span class="input-group-text">₹</span>
                        <input type="number" class="form-control" id="edit_professional_tax" 
                               value="200" step="0.01" readonly>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mb-3">
            <label for="edit_net_amount" class="form-label">Net Amount *</label>
            <div class="input-group">
                <span class="input-group-text">₹</span>
                <input type="number" class="form-control" id="edit_net_amount" name="net_amount" 
                       value="<?php echo $pension['net_amount']; ?>" step="0.01" required readonly>
            </div>
            <small class="text-success">This is the final amount to be disbursed</small>
        </div>
        
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <strong>Auto-calculation:</strong> Monthly amount, taxes, and net amount are automatically calculated based on rank and pension type according to government rates.
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Update Pension
            </button>
        </div>
    </form>
    
    <script>
        function calculatePensionAmount() {
            const serviceType = document.getElementById('edit_service_type').value;
            const rank = document.getElementById('edit_rank').value;
            const pensionType = document.getElementById('edit_pension_type').value;
            const loanDeduction = parseFloat(document.getElementById('edit_loan_deduction').value || 0);
            
            if (!serviceType || !rank || !pensionType) {
                return;
            }
            
            fetch(`../api/calculate_pension_amount.php?service_type=${encodeURIComponent(serviceType)}&rank=${encodeURIComponent(rank)}&pension_type=${encodeURIComponent(pensionType)}&loan_deduction=${loanDeduction}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('edit_monthly_amount').value = data.data.monthly_amount.toFixed(2);
                        document.getElementById('edit_cgst').value = data.data.cgst.toFixed(2);
                        document.getElementById('edit_sgst').value = data.data.sgst.toFixed(2);
                        document.getElementById('edit_net_amount').value = data.data.net_amount.toFixed(2);
                    } else {
                        alert('Error calculating pension: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        }
        
        // Calculate on page load
        calculatePensionAmount();
        
        // Handle form submission
        document.getElementById('editPensionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('pension_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // Close modal and reload page
                const modal = bootstrap.Modal.getInstance(document.getElementById('editPensionModal'));
                modal.hide();
                window.location.reload();
            })
            .catch(error => {
                alert('Error updating pension: ' + error.message);
            });
        });
    </script>
    <?php
    
    $html = ob_get_clean();
    echo json_encode(['success' => true, 'html' => $html]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
