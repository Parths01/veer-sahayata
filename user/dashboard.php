<?php
session_start();
require_once '../config/database.php';
requireLogin();

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get news
$news = $pdo->query("SELECT * FROM news WHERE status = 'published' ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Get pending verifications
$pending_verifications = $pdo->prepare("SELECT * FROM verifications WHERE user_id = ? AND status = 'pending' AND due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
$pending_verifications->execute([$_SESSION['user_id']]);
$verifications = $pending_verifications->fetchAll();

// Check if dependents are added
$dependents_count = $pdo->prepare("SELECT COUNT(*) as count FROM dependents WHERE user_id = ?");
$dependents_count->execute([$_SESSION['user_id']]);
$has_dependents = $dependents_count->fetch()['count'] > 0;

// Get recent loan updates
$recent_loans = $pdo->prepare("SELECT * FROM loans WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
$recent_loans->execute([$_SESSION['user_id']]);
$loans = $recent_loans->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Veer Sahayata</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/user_header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/user_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Welcome, <?php echo $user['full_name']; ?></h1>
                    <div>
                        <span class="badge bg-primary"><?php echo $user['service_type']; ?></span>
                        <span class="badge bg-secondary"><?php echo $user['rank_designation']; ?></span>
                    </div>
                </div>

                <!-- Alert Section -->
                <?php if (!empty($verifications)): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Verification Required!</strong> You have pending verifications due within 30 days.
                    <a href="verification.php" class="alert-link">Complete Now</a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (!$has_dependents): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="fas fa-info-circle"></i>
                    <strong>Add Dependents!</strong> You haven't added any dependents yet.
                    <a href="profile.php#dependents" class="alert-link">Add Dependents</a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Quick Stats -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-money-bill-wave fa-2x mb-2"></i>
                                <h5>Pension Status</h5>
                                <p class="mb-0">View Details</p>
                            </div>
                            <div class="card-footer">
                                <a href="pension.php" class="text-white text-decoration-none">
                                    <small>Go to Pension Management <i class="fas fa-arrow-right"></i></small>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-chart-line fa-2x mb-2"></i>
                                <h5>Active Loans</h5>
                                <p class="mb-0"><?php echo count($loans); ?> Loans</p>
                            </div>
                            <div class="card-footer">
                                <a href="loans.php" class="text-white text-decoration-none">
                                    <small>Go to Loan Tracker <i class="fas fa-arrow-right"></i></small>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-heartbeat fa-2x mb-2"></i>
                                <h5>Health Records</h5>
                                <p class="mb-0">Track Health</p>
                            </div>
                            <div class="card-footer">
                                <a href="health.php" class="text-white text-decoration-none">
                                    <small>Go to Health Records <i class="fas fa-arrow-right"></i></small>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <i class="fas fa-graduation-cap fa-2x mb-2"></i>
                                <h5>Education</h5>
                                <p class="mb-0">Find Colleges</p>
                            </div>
                            <div class="card-footer">
                                <a href="colleges.php" class="text-white text-decoration-none">
                                    <small>Go to Colleges <i class="fas fa-arrow-right"></i></small>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- News and Updates -->
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-newspaper"></i> Latest News & Updates</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($news)): ?>
                                    <?php foreach($news as $article): ?>
                                    <div class="news-item mb-3 pb-3 border-bottom">
                                        <h6><?php echo $article['title']; ?></h6>
                                        <p class="text-muted small mb-2">
                                            <i class="fas fa-clock"></i> 
                                            <?php echo date('F j, Y', strtotime($article['created_at'])); ?>
                                        </p>
                                        <p><?php echo substr($article['content'], 0, 150) . '...'; ?></p>
                                        <a href="#" class="btn btn-sm btn-outline-primary">Read More</a>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted">No news updates available at the moment.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <!-- Verification Status -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6><i class="fas fa-check-circle"></i> Verification Status</h6>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($verifications)): ?>
                                    <?php foreach($verifications as $verification): ?>
                                    <div class="alert alert-warning py-2">
                                        <small>
                                            <strong><?php echo ucfirst($verification['verification_type']); ?> Verification</strong><br>
                                            Due: <?php echo date('d/m/Y', strtotime($verification['due_date'])); ?>
                                        </small>
                                    </div>
                                    <?php endforeach; ?>
                                    <a href="verification.php" class="btn btn-warning btn-sm w-100">Complete Verification</a>
                                <?php else: ?>
                                    <div class="text-center">
                                        <i class="fas fa-check-circle text-success fa-2x mb-2"></i>
                                        <p class="small text-muted mb-0">All verifications up to date</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Recent Loan Updates -->
                        <div class="card">
                            <div class="card-header">
                                <h6><i class="fas fa-money-bill-wave"></i> Recent Loan Updates</h6>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($loans)): ?>
                                    <?php foreach($loans as $loan): ?>
                                    <div class="loan-item mb-2 pb-2 border-bottom">
                                        <small>
                                            <strong><?php echo $loan['loan_type']; ?></strong><br>
                                            Amount: ₹<?php echo number_format($loan['loan_amount']); ?><br>
                                            Status: <span class="badge bg-<?php echo $loan['status'] == 'active' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($loan['status']); ?>
                                            </span>
                                        </small>
                                    </div>
                                    <?php endforeach; ?>
                                    <a href="loans.php" class="btn btn-primary btn-sm w-100 mt-2">View All Loans</a>
                                <?php else: ?>
                                    <div class="text-center">
                                        <i class="fas fa-plus-circle text-muted fa-2x mb-2"></i>
                                        <p class="small text-muted mb-2">No active loans</p>
                                        <a href="loans.php" class="btn btn-outline-primary btn-sm">Apply for Loan</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
