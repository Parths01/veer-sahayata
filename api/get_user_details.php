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

    // Get user documents
    $stmt = $pdo->prepare("SELECT * FROM documents WHERE user_id = ? ORDER BY uploaded_at DESC");
    $stmt->execute([$userId]);
    $documents = $stmt->fetchAll();

    // Get user dependents
    $stmt = $pdo->prepare("SELECT * FROM dependents WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $dependents = $stmt->fetchAll();

    // Get user loans
    $stmt = $pdo->prepare("SELECT * FROM loans WHERE user_id = ? ORDER BY applied_date DESC LIMIT 5");
    $stmt->execute([$userId]);
    $loans = $stmt->fetchAll();

    // Get user pension records
    $stmt = $pdo->prepare("SELECT * FROM pension WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
    $stmt->execute([$userId]);
    $pension = $stmt->fetchAll();

    $html = '
    <div class="row">
        <div class="col-md-4 text-center">
            <img src="../uploads/photos/' . ($user['photo'] ?: 'default-avatar.png') . '" 
                 alt="Profile Photo" class="profile-img mb-3">
            <h5>' . htmlspecialchars($user['full_name']) . '</h5>
            <p class="text-muted">' . htmlspecialchars($user['rank_designation']) . '</p>
            <span class="badge bg-' . ($user['service_type'] == 'Army' ? 'success' : ($user['service_type'] == 'Navy' ? 'primary' : 'info')) . '">
                ' . htmlspecialchars($user['service_type']) . '
            </span>
        </div>
        <div class="col-md-8">
            <table class="table table-borderless">
                <tr>
                    <td><strong>Service Number:</strong></td>
                    <td>' . htmlspecialchars($user['service_number']) . '</td>
                </tr>
                <tr>
                    <td><strong>Username:</strong></td>
                    <td>' . htmlspecialchars($user['username']) . '</td>
                </tr>
                <tr>
                    <td><strong>Date of Birth:</strong></td>
                    <td>' . ($user['date_of_birth'] ? date('d/m/Y', strtotime($user['date_of_birth'])) : 'Not provided') . '</td>
                </tr>
                <tr>
                    <td><strong>Date of Joining:</strong></td>
                    <td>' . ($user['date_of_joining'] ? date('d/m/Y', strtotime($user['date_of_joining'])) : 'Not provided') . '</td>
                </tr>
                <tr>
                    <td><strong>Date of Retirement:</strong></td>
                    <td>' . ($user['date_of_retirement'] ? date('d/m/Y', strtotime($user['date_of_retirement'])) : 'Active Service') . '</td>
                </tr>
                <tr>
                    <td><strong>Phone:</strong></td>
                    <td>' . htmlspecialchars($user['phone']) . '</td>
                </tr>
                <tr>
                    <td><strong>Email:</strong></td>
                    <td>' . htmlspecialchars($user['email']) . '</td>
                </tr>
                <tr>
                    <td><strong>Status:</strong></td>
                    <td>
                        <span class="badge bg-' . ($user['status'] == 'active' ? 'success' : 'secondary') . '">
                            ' . ucfirst($user['status']) . '
                        </span>
                    </td>
                </tr>
                <tr>
                    <td><strong>Created:</strong></td>
                    <td>' . date('d/m/Y H:i', strtotime($user['created_at'])) . '</td>
                </tr>
            </table>
            
            ' . ($user['address'] ? '<div class="mt-3"><strong>Address:</strong><br>' . nl2br(htmlspecialchars($user['address'])) . '</div>' : '') . '
        </div>
    </div>

    <hr>

    <!-- Documents Section -->
    <div class="row mt-4">
        <div class="col-md-6">
            <h6>Documents (' . count($documents) . ')</h6>
            ' . (count($documents) > 0 ? 
                '<div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Uploaded</th>
                            </tr>
                        </thead>
                        <tbody>' : 
                '<p class="text-muted">No documents uploaded</p>') . '';

    foreach ($documents as $doc) {
        $html .= '
                            <tr>
                                <td>' . ucwords(str_replace('_', ' ', $doc['document_type'])) . '</td>
                                <td>
                                    <span class="badge bg-' . ($doc['status'] == 'approved' ? 'success' : ($doc['status'] == 'rejected' ? 'danger' : 'warning')) . '">
                                        ' . ucfirst($doc['status']) . '
                                    </span>
                                </td>
                                <td>' . date('d/m/Y', strtotime($doc['uploaded_at'])) . '</td>
                            </tr>';
    }

    if (count($documents) > 0) {
        $html .= '
                        </tbody>
                    </table>
                </div>';
    }

    $html .= '
        </div>
        <div class="col-md-6">
            <h6>Dependents (' . count($dependents) . ')</h6>
            ' . (count($dependents) > 0 ? 
                '<div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Relationship</th>
                                <th>Age</th>
                            </tr>
                        </thead>
                        <tbody>' : 
                '<p class="text-muted">No dependents added</p>') . '';

    foreach ($dependents as $dependent) {
        $age = $dependent['date_of_birth'] ? 
            (date('Y') - date('Y', strtotime($dependent['date_of_birth']))) : 'N/A';
        
        $html .= '
                            <tr>
                                <td>' . htmlspecialchars($dependent['name']) . '</td>
                                <td>' . ucfirst($dependent['relationship']) . '</td>
                                <td>' . $age . '</td>
                            </tr>';
    }

    if (count($dependents) > 0) {
        $html .= '
                        </tbody>
                    </table>
                </div>';
    }

    $html .= '
        </div>
    </div>';

    // Financial Summary
    if (count($loans) > 0 || count($pension) > 0) {
        $html .= '
        <hr>
        <div class="row mt-4">
            <div class="col-md-6">
                <h6>Recent Loans (' . count($loans) . ')</h6>';
        
        if (count($loans) > 0) {
            $html .= '
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>';
            
            foreach ($loans as $loan) {
                $html .= '
                            <tr>
                                <td>' . htmlspecialchars($loan['loan_type']) . '</td>
                                <td>₹' . number_format($loan['loan_amount'], 2) . '</td>
                                <td>
                                    <span class="badge bg-' . ($loan['status'] == 'active' ? 'success' : ($loan['status'] == 'pending_approval' ? 'warning' : 'secondary')) . '">
                                        ' . ucfirst(str_replace('_', ' ', $loan['status'])) . '
                                    </span>
                                </td>
                            </tr>';
            }
            
            $html .= '
                        </tbody>
                    </table>
                </div>';
        } else {
            $html .= '<p class="text-muted">No loans applied</p>';
        }

        $html .= '
            </div>
            <div class="col-md-6">
                <h6>Pension Records (' . count($pension) . ')</h6>';
        
        if (count($pension) > 0) {
            $html .= '
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>';
            
            foreach ($pension as $p) {
                $html .= '
                            <tr>
                                <td>' . htmlspecialchars($p['pension_type']) . '</td>
                                <td>₹' . number_format($p['monthly_amount'], 2) . '</td>
                                <td>
                                    <span class="badge bg-' . ($p['status'] == 'disbursed' ? 'success' : ($p['status'] == 'approved' ? 'warning' : 'secondary')) . '">
                                        ' . ucfirst($p['status']) . '
                                    </span>
                                </td>
                            </tr>';
            }
            
            $html .= '
                        </tbody>
                    </table>
                </div>';
        } else {
            $html .= '<p class="text-muted">No pension records</p>';
        }

        $html .= '
            </div>
        </div>';
    }

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
