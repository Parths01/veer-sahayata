<?php
session_start();
require_once '../config/database.php';
requireLogin();
requireAdmin();

// Get dashboard statistics
$stats = [];

// Total users
$stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users WHERE role = 'user'");
$stats['total_users'] = $stmt->fetch()['total_users'];

// Pending documents
$stmt = $pdo->query("SELECT COUNT(*) as pending_docs FROM documents WHERE status = 'pending'");
$stats['pending_docs'] = $stmt->fetch()['pending_docs'];

// Active loans
$stmt = $pdo->query("SELECT COUNT(*) as active_loans FROM loans WHERE status = 'active'");
$stats['active_loans'] = $stmt->fetch()['active_loans'];

// Pending verifications
$stmt = $pdo->query("SELECT COUNT(*) as pending_verifications FROM verifications WHERE status = 'pending'");
$stats['pending_verifications'] = $stmt->fetch()['pending_verifications'];

// Recent activities
$recent_users = $pdo->query("SELECT * FROM users WHERE role = 'user' ORDER BY created_at DESC LIMIT 5")->fetchAll();
$recent_documents = $pdo->query("SELECT d.*, u.full_name FROM documents d JOIN users u ON d.user_id = u.id ORDER BY d.uploaded_at DESC LIMIT 5")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Veer Sahayata</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Admin Dashboard</h1>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo $stats['total_users']; ?></h4>
                                        <p>Total Users</p>
                                    </div>
                                    <i class="fas fa-users fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo $stats['pending_docs']; ?></h4>
                                        <p>Pending Documents</p>
                                    </div>
                                    <i class="fas fa-file-alt fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo $stats['active_loans']; ?></h4>
                                        <p>Active Loans</p>
                                    </div>
                                    <i class="fas fa-money-bill-wave fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo $stats['pending_verifications']; ?></h4>
                                        <p>Pending Verifications</p>
                                    </div>
                                    <i class="fas fa-check-circle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Recent User Registrations</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Service Number</th>
                                                <th>Name</th>
                                                <th>Service</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($recent_users as $user): ?>
                                            <tr>
                                                <td><?php echo $user['service_number']; ?></td>
                                                <td><?php echo $user['full_name']; ?></td>
                                                <td><?php echo $user['service_type']; ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Recent Document Uploads</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Document Type</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($recent_documents as $doc): ?>
                                            <tr>
                                                <td><?php echo $doc['full_name']; ?></td>
                                                <td><?php echo ucfirst($doc['document_type']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $doc['status'] == 'approved' ? 'success' : ($doc['status'] == 'rejected' ? 'danger' : 'warning'); ?>">
                                                        <?php echo ucfirst($doc['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('d/m/Y', strtotime($doc['uploaded_at'])); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5>Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <a href="users.php" class="btn btn-primary w-100">
                                            <i class="fas fa-user-plus"></i><br>
                                            Add New User
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="documents.php" class="btn btn-warning w-100">
                                            <i class="fas fa-file-check"></i><br>
                                            Review Documents
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="pension_management.php" class="btn btn-success w-100">
                                            <i class="fas fa-money-check"></i><br>
                                            Pension Management
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="reports.php" class="btn btn-info w-100">
                                            <i class="fas fa-chart-bar"></i><br>
                                            Generate Reports
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pension Management Widget -->
                    <?php include '../includes/pension_dashboard_widget.php'; ?>
                    
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
