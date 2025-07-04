<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$userId = $_GET['id'] ?? 0;

try {
    // Get user details
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'user'");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception('User not found');
    }

    $html = '
    <form method="POST" data-validate="true">
        <input type="hidden" name="action" value="update_user">
        <input type="hidden" name="user_id" value="' . $user['id'] . '">
        
        <!-- Basic Information -->
        <h6 class="text-primary mb-3">Basic Information</h6>
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Service Number</label>
                    <input type="text" class="form-control" value="' . htmlspecialchars($user['service_number']) . '" readonly>
                    <small class="text-muted">Service number cannot be changed</small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" value="' . htmlspecialchars($user['username']) . '" readonly>
                    <small class="text-muted">Username cannot be changed</small>
                </div>
            </div>
        </div>
        
        <div class="mb-3">
            <label for="edit_full_name" class="form-label">Full Name *</label>
            <input type="text" class="form-control" id="edit_full_name" name="full_name" 
                   value="' . htmlspecialchars($user['full_name']) . '" required>
        </div>

        <!-- Service Information -->
        <h6 class="text-primary mb-3 mt-4">Service Information</h6>
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Service Type</label>
                    <input type="text" class="form-control" value="' . htmlspecialchars($user['service_type']) . '" readonly>
                    <small class="text-muted">Service type cannot be changed</small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="edit_rank_designation" class="form-label">Rank/Designation</label>
                    <input type="text" class="form-control" id="edit_rank_designation" name="rank_designation" 
                           value="' . htmlspecialchars($user['rank_designation']) . '">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="mb-3">
                    <label for="edit_date_of_birth" class="form-label">Date of Birth</label>
                    <input type="date" class="form-control" id="edit_date_of_birth" name="date_of_birth" 
                           value="' . ($user['date_of_birth'] ?: '') . '">
                </div>
            </div>
            <div class="col-md-4">
                <div class="mb-3">
                    <label for="edit_date_of_joining" class="form-label">Date of Joining</label>
                    <input type="date" class="form-control" id="edit_date_of_joining" name="date_of_joining" 
                           value="' . ($user['date_of_joining'] ?: '') . '">
                </div>
            </div>
            <div class="col-md-4">
                <div class="mb-3">
                    <label for="edit_date_of_retirement" class="form-label">Date of Retirement</label>
                    <input type="date" class="form-control" id="edit_date_of_retirement" name="date_of_retirement" 
                           value="' . ($user['date_of_retirement'] ?: '') . '">
                </div>
            </div>
        </div>

        <!-- Contact Information -->
        <h6 class="text-primary mb-3 mt-4">Contact Information</h6>
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="edit_phone" class="form-label">Phone Number *</label>
                    <input type="tel" class="form-control" id="edit_phone" name="phone" 
                           value="' . htmlspecialchars($user['phone']) . '" required pattern="[0-9]{10}">
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="edit_email" class="form-label">Email Address *</label>
                    <input type="email" class="form-control" id="edit_email" name="email" 
                           value="' . htmlspecialchars($user['email']) . '" required>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label for="edit_address" class="form-label">Address</label>
            <textarea class="form-control" id="edit_address" name="address" rows="3">' . htmlspecialchars($user['address']) . '</textarea>
        </div>

        <div class="mb-3">
            <label for="edit_status" class="form-label">Status</label>
            <select class="form-select" id="edit_status" name="status">
                <option value="active"' . ($user['status'] == 'active' ? ' selected' : '') . '>Active</option>
                <option value="inactive"' . ($user['status'] == 'inactive' ? ' selected' : '') . '>Inactive</option>
                <option value="deceased"' . ($user['status'] == 'deceased' ? ' selected' : '') . '>Deceased</option>
            </select>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Update User</button>
        </div>
    </form>

    <script>
        // Phone number validation
        document.getElementById("edit_phone").addEventListener("input", function() {
            this.value = this.value.replace(/\D/g, "").slice(0, 10);
        });
    </script>';

    echo json_encode([
        'success' => true,
        'html' => $html
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
