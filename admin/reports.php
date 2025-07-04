<?php
session_start();
require_once '../config/database.php';
requireLogin();
requireAdmin();

// Get date range filters
$dateFrom = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$dateTo = $_GET['date_to'] ?? date('Y-m-d'); // Today
$reportType = $_GET['report_type'] ?? 'overview';

// Validate date range
if (strtotime($dateFrom) > strtotime($dateTo)) {
    $temp = $dateFrom;
    $dateFrom = $dateTo;
    $dateTo = $temp;
}

// Generate reports based on type
function generateOverviewReport($pdo, $dateFrom, $dateTo) {
    $report = [];
    
    // Get new registrations
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$dateFrom, $dateTo]);
    $newRegistrations = $stmt->fetchColumn();
    
    // User Statistics
    $report['users'] = [
        'total' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'active' => $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn(),
        'new_registrations' => $newRegistrations,
        'by_service' => $pdo->query("SELECT service_type, COUNT(*) as count FROM users GROUP BY service_type")->fetchAll(PDO::FETCH_KEY_PAIR)
    ];
    
    // Get recent uploads
    $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE DATE(uploaded_at) BETWEEN ? AND ?");
    $stmt2->execute([$dateFrom, $dateTo]);
    $recentUploads = $stmt2->fetchColumn();
    
    // Document Statistics
    $report['documents'] = [];
    try {
        $report['documents'] = [
            'total' => $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn(),
            'pending' => $pdo->query("SELECT COUNT(*) FROM documents WHERE status = 'pending'")->fetchColumn(),
            'approved' => $pdo->query("SELECT COUNT(*) FROM documents WHERE status = 'approved'")->fetchColumn(),
            'rejected' => $pdo->query("SELECT COUNT(*) FROM documents WHERE status = 'rejected'")->fetchColumn(),
            'recent_uploads' => $recentUploads
        ];
    } catch (PDOException $e) {
        $report['documents'] = [
            'total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'recent_uploads' => 0
        ];
    }
    
    // Verification Statistics
    $report['verifications'] = [];
    try {
        $report['verifications'] = [
            'total' => $pdo->query("SELECT COUNT(*) FROM user_verifications")->fetchColumn(),
            'pending' => $pdo->query("SELECT COUNT(*) FROM user_verifications WHERE status = 'pending'")->fetchColumn(),
            'approved' => $pdo->query("SELECT COUNT(*) FROM user_verifications WHERE status = 'approved'")->fetchColumn(),
            'rejected' => $pdo->query("SELECT COUNT(*) FROM user_verifications WHERE status = 'rejected'")->fetchColumn()
        ];
    } catch (PDOException $e) {
        $report['verifications'] = [
            'total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0
        ];
    }
    
    // News Statistics
    $report['news'] = [
        'total' => $pdo->query("SELECT COUNT(*) FROM news")->fetchColumn(),
        'published' => $pdo->query("SELECT COUNT(*) FROM news WHERE status = 'published'")->fetchColumn(),
        'draft' => $pdo->query("SELECT COUNT(*) FROM news WHERE status = 'draft'")->fetchColumn()
    ];
    
    // Check if featured column exists before querying
    try {
        $report['news']['featured'] = $pdo->query("SELECT COUNT(*) FROM news WHERE featured = 1")->fetchColumn();
    } catch (PDOException $e) {
        // Column doesn't exist, set to 0
        $report['news']['featured'] = 0;
    }
    
    // Colleges and Hospitals
    $report['institutions'] = [];
    
    // Check if tables exist before querying
    try {
        $report['institutions']['colleges'] = $pdo->query("SELECT COUNT(*) FROM colleges WHERE status = 'active'")->fetchColumn();
    } catch (PDOException $e) {
        $report['institutions']['colleges'] = 0;
    }
    
    try {
        $report['institutions']['hospitals'] = $pdo->query("SELECT COUNT(*) FROM hospitals WHERE status = 'active'")->fetchColumn();
    } catch (PDOException $e) {
        $report['institutions']['hospitals'] = 0;
    }
    
    try {
        $report['institutions']['schemes'] = $pdo->query("SELECT COUNT(*) FROM schemes WHERE status = 'active'")->fetchColumn();
    } catch (PDOException $e) {
        $report['institutions']['schemes'] = 0;
    }
    
    return $report;
}

function generateUserReport($pdo, $dateFrom, $dateTo) {
    $report = [];
    
    // User registration trends
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM users 
        WHERE DATE(created_at) BETWEEN ? AND ? 
        GROUP BY DATE(created_at) 
        ORDER BY date DESC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $report['registration_trends'] = $stmt->fetchAll();
    
    // Service type distribution
    $report['service_distribution'] = $pdo->query("
        SELECT service_type, COUNT(*) as count, 
               ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM users)), 2) as percentage
        FROM users 
        GROUP BY service_type
    ")->fetchAll();
    
    // Status distribution
    $report['status_distribution'] = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM users 
        GROUP BY status
    ")->fetchAll();
    
    // Recent user activities
    $stmt = $pdo->prepare("
        SELECT u.full_name, u.service_number, u.service_type, u.created_at,
               (SELECT COUNT(*) FROM documents d WHERE d.user_id = u.id) as documents_count,
               (SELECT COUNT(*) FROM user_verifications v WHERE v.user_id = u.id) as verifications_count
        FROM users u 
        WHERE DATE(u.created_at) BETWEEN ? AND ?
        ORDER BY u.created_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $report['recent_users'] = $stmt->fetchAll();
    
    return $report;
}

function generateDocumentReport($pdo, $dateFrom, $dateTo) {
    $report = [];
    
    // Document upload trends
    try {
        $stmt = $pdo->prepare("
            SELECT DATE(uploaded_at) as date, COUNT(*) as count 
            FROM documents 
            WHERE DATE(uploaded_at) BETWEEN ? AND ? 
            GROUP BY DATE(uploaded_at) 
            ORDER BY date DESC
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $report['upload_trends'] = $stmt->fetchAll();
    } catch (PDOException $e) {
        $report['upload_trends'] = [];
    }
    
    // Document type distribution
    try {
        $report['type_distribution'] = $pdo->query("
            SELECT document_type, COUNT(*) as count 
            FROM documents 
            GROUP BY document_type 
            ORDER BY count DESC
        ")->fetchAll();
    } catch (PDOException $e) {
        $report['type_distribution'] = [];
    }
    
    // Document status breakdown
    try {
        $report['status_breakdown'] = $pdo->query("
            SELECT status, COUNT(*) as count,
                   ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM documents)), 2) as percentage
            FROM documents 
            GROUP BY status
        ")->fetchAll();
    } catch (PDOException $e) {
        $report['status_breakdown'] = [];
    }
    
    // Processing time analysis
    try {
        $report['processing_times'] = $pdo->query("
            SELECT document_type, 
                   AVG(DATEDIFF(reviewed_at, uploaded_at)) as avg_processing_days,
                   MIN(DATEDIFF(reviewed_at, uploaded_at)) as min_processing_days,
                   MAX(DATEDIFF(reviewed_at, uploaded_at)) as max_processing_days
            FROM documents 
            WHERE reviewed_at IS NOT NULL 
            GROUP BY document_type
        ")->fetchAll();
    } catch (PDOException $e) {
        $report['processing_times'] = [];
    }
    
    return $report;
}

function generateSystemReport($pdo, $dateFrom, $dateTo) {
    $report = [];
    
    // Database statistics
    // Database statistics  
    $report['database_stats'] = [];
    $tables = ['users', 'documents', 'user_verifications', 'news', 'colleges', 'hospitals', 'schemes'];
    foreach ($tables as $table) {
        try {
            $report['database_stats'][$table . '_table_size'] = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        } catch (PDOException $e) {
            $report['database_stats'][$table . '_table_size'] = 0;
        }
    }
    
    // Activity summary for the period
    try {
        $stmt = $pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM users WHERE DATE(created_at) BETWEEN ? AND ?) as new_users,
                (SELECT COUNT(*) FROM documents WHERE DATE(uploaded_at) BETWEEN ? AND ?) as new_documents,
                (SELECT COUNT(*) FROM user_verifications WHERE DATE(submitted_at) BETWEEN ? AND ?) as new_verifications,
                (SELECT COUNT(*) FROM news WHERE DATE(created_at) BETWEEN ? AND ?) as new_articles
        ");
        $stmt->execute([$dateFrom, $dateTo, $dateFrom, $dateTo, $dateFrom, $dateTo, $dateFrom, $dateTo]);
        $report['period_activity'] = $stmt->fetch();
    } catch (PDOException $e) {
        $report['period_activity'] = [
            'new_users' => 0, 'new_documents' => 0, 'new_verifications' => 0, 'new_articles' => 0
        ];
    }
    
    // System health indicators
    try {
        $report['health_indicators'] = [
            'pending_verifications' => $pdo->query("SELECT COUNT(*) FROM user_verifications WHERE status = 'pending'")->fetchColumn(),
            'pending_documents' => $pdo->query("SELECT COUNT(*) FROM documents WHERE status = 'pending'")->fetchColumn(),
            'inactive_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'inactive'")->fetchColumn(),
            'draft_articles' => $pdo->query("SELECT COUNT(*) FROM news WHERE status = 'draft'")->fetchColumn()
        ];
    } catch (PDOException $e) {
        $report['health_indicators'] = [
            'pending_verifications' => 0, 'pending_documents' => 0, 'inactive_users' => 0, 'draft_articles' => 0
        ];
    }
    
    return $report;
}

// Generate report based on selected type
$reportData = [];
switch ($reportType) {
    case 'users':
        $reportData = generateUserReport($pdo, $dateFrom, $dateTo);
        break;
    case 'documents':
        $reportData = generateDocumentReport($pdo, $dateFrom, $dateTo);
        break;
    case 'system':
        $reportData = generateSystemReport($pdo, $dateFrom, $dateTo);
        break;
    default:
        $reportData = generateOverviewReport($pdo, $dateFrom, $dateTo);
        break;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Veer Sahayata Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .report-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        .report-card:hover {
            transform: translateY(-2px);
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .stat-card.success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .stat-card.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .stat-card.info {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }
        .percentage-badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
        }
        .report-tabs .nav-link {
            border-radius: 8px;
            margin-right: 8px;
            border: none;
            background: #f8f9fa;
        }
        .report-tabs .nav-link.active {
            background: #007bff;
            color: white;
        }
        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-chart-bar text-primary"></i> Reports & Analytics</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-outline-primary" onclick="exportReport()">
                                <i class="fas fa-download"></i> Export
                            </button>
                            <button class="btn btn-outline-secondary" onclick="printReport()">
                                <i class="fas fa-print"></i> Print
                            </button>
                            <button class="btn btn-outline-secondary" onclick="refreshPage()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Date Range and Report Type Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="date_from" class="form-label">From Date</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" 
                                       value="<?php echo $dateFrom; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="date_to" class="form-label">To Date</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" 
                                       value="<?php echo $dateTo; ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="report_type" class="form-label">Report Type</label>
                                <select class="form-select" id="report_type" name="report_type">
                                    <option value="overview" <?php echo $reportType == 'overview' ? 'selected' : ''; ?>>System Overview</option>
                                    <option value="users" <?php echo $reportType == 'users' ? 'selected' : ''; ?>>User Analytics</option>
                                    <option value="documents" <?php echo $reportType == 'documents' ? 'selected' : ''; ?>>Document Reports</option>
                                    <option value="system" <?php echo $reportType == 'system' ? 'selected' : ''; ?>>System Statistics</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i> Generate
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Report Content -->
                <div id="reportContent">
                    <?php if ($reportType == 'overview'): ?>
                        <!-- Overview Report -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="stat-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h3><?php echo $reportData['users']['total']; ?></h3>
                                            <p class="mb-0">Total Users</p>
                                        </div>
                                        <i class="fas fa-users fa-2x opacity-75"></i>
                                    </div>
                                    <div class="mt-2">
                                        <span class="percentage-badge"><?php echo $reportData['users']['active']; ?> Active</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card success">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h3><?php echo $reportData['documents']['total']; ?></h3>
                                            <p class="mb-0">Documents</p>
                                        </div>
                                        <i class="fas fa-file-alt fa-2x opacity-75"></i>
                                    </div>
                                    <div class="mt-2">
                                        <span class="percentage-badge"><?php echo $reportData['documents']['pending']; ?> Pending</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card warning">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h3><?php echo $reportData['verifications']['total']; ?></h3>
                                            <p class="mb-0">Verifications</p>
                                        </div>
                                        <i class="fas fa-shield-alt fa-2x opacity-75"></i>
                                    </div>
                                    <div class="mt-2">
                                        <span class="percentage-badge"><?php echo $reportData['verifications']['approved']; ?> Approved</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card info">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h3><?php echo $reportData['news']['total']; ?></h3>
                                            <p class="mb-0">News Articles</p>
                                        </div>
                                        <i class="fas fa-newspaper fa-2x opacity-75"></i>
                                    </div>
                                    <div class="mt-2">
                                        <span class="percentage-badge"><?php echo $reportData['news']['published']; ?> Published</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Charts Row -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card report-card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-chart-pie"></i> User Distribution by Service</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="serviceChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card report-card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-chart-bar"></i> Document Status Overview</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="documentChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Institutions Summary -->
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="card report-card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-building"></i> Institutions Summary</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-md-4">
                                                <div class="border-end">
                                                    <h3 class="text-primary"><?php echo $reportData['institutions']['colleges']; ?></h3>
                                                    <p class="text-muted">Active Colleges</p>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="border-end">
                                                    <h3 class="text-success"><?php echo $reportData['institutions']['hospitals']; ?></h3>
                                                    <p class="text-muted">Active Hospitals</p>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <h3 class="text-warning"><?php echo $reportData['institutions']['schemes']; ?></h3>
                                                <p class="text-muted">Active Schemes</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php elseif ($reportType == 'users'): ?>
                        <!-- User Analytics Report -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card report-card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-chart-line"></i> Registration Trends</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <canvas id="registrationChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card report-card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-chart-pie"></i> Service Type Distribution</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Service Type</th>
                                                    <th>Count</th>
                                                    <th>Percentage</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($reportData['service_distribution'] as $service): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($service['service_type']); ?></td>
                                                    <td><?php echo $service['count']; ?></td>
                                                    <td><?php echo $service['percentage']; ?>%</td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Users Table -->
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="card report-card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-users"></i> Recent User Registrations</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Service Number</th>
                                                        <th>Service Type</th>
                                                        <th>Registration Date</th>
                                                        <th>Documents</th>
                                                        <th>Verifications</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($reportData['recent_users'] as $user): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($user['service_number']); ?></td>
                                                        <td>
                                                            <span class="badge bg-primary"><?php echo $user['service_type']; ?></span>
                                                        </td>
                                                        <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                                                        <td><?php echo $user['documents_count']; ?></td>
                                                        <td><?php echo $user['verifications_count']; ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php elseif ($reportType == 'documents'): ?>
                        <!-- Document Reports -->
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card report-card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-chart-bar"></i> Document Type Distribution</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Document Type</th>
                                                        <th>Count</th>
                                                        <th>Distribution</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($reportData['type_distribution'] as $type): ?>
                                                    <tr>
                                                        <td><?php echo ucfirst(str_replace('_', ' ', $type['document_type'])); ?></td>
                                                        <td><?php echo $type['count']; ?></td>
                                                        <td>
                                                            <div class="progress" style="height: 20px;">
                                                                <div class="progress-bar" style="width: <?php echo ($type['count'] / max(array_column($reportData['type_distribution'], 'count'))) * 100; ?>%">
                                                                    <?php echo $type['count']; ?>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card report-card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-chart-pie"></i> Status Breakdown</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php foreach ($reportData['status_breakdown'] as $status): ?>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between">
                                                <span><?php echo ucfirst($status['status']); ?></span>
                                                <span><?php echo $status['percentage']; ?>%</span>
                                            </div>
                                            <div class="progress mt-1">
                                                <div class="progress-bar bg-<?php echo $status['status'] == 'approved' ? 'success' : ($status['status'] == 'pending' ? 'warning' : 'danger'); ?>" 
                                                     style="width: <?php echo $status['percentage']; ?>%"></div>
                                            </div>
                                            <small class="text-muted"><?php echo $status['count']; ?> documents</small>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php elseif ($reportType == 'system'): ?>
                        <!-- System Statistics -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card report-card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-database"></i> Database Statistics</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <tbody>
                                                <tr>
                                                    <td><i class="fas fa-users text-primary"></i> Users</td>
                                                    <td class="text-end"><?php echo number_format($reportData['database_stats']['users_table_size']); ?></td>
                                                </tr>
                                                <tr>
                                                    <td><i class="fas fa-file-alt text-success"></i> Documents</td>
                                                    <td class="text-end"><?php echo number_format($reportData['database_stats']['documents_table_size']); ?></td>
                                                </tr>
                                                <tr>
                                                    <td><i class="fas fa-shield-alt text-warning"></i> Verifications</td>
                                                    <td class="text-end"><?php echo number_format($reportData['database_stats']['verifications_table_size']); ?></td>
                                                </tr>
                                                <tr>
                                                    <td><i class="fas fa-newspaper text-info"></i> News Articles</td>
                                                    <td class="text-end"><?php echo number_format($reportData['database_stats']['news_table_size']); ?></td>
                                                </tr>
                                                <tr>
                                                    <td><i class="fas fa-university text-primary"></i> Colleges</td>
                                                    <td class="text-end"><?php echo number_format($reportData['database_stats']['colleges_table_size']); ?></td>
                                                </tr>
                                                <tr>
                                                    <td><i class="fas fa-hospital text-danger"></i> Hospitals</td>
                                                    <td class="text-end"><?php echo number_format($reportData['database_stats']['hospitals_table_size']); ?></td>
                                                </tr>
                                                <tr>
                                                    <td><i class="fas fa-clipboard-list text-secondary"></i> Schemes</td>
                                                    <td class="text-end"><?php echo number_format($reportData['database_stats']['schemes_table_size']); ?></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card report-card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-heartbeat"></i> System Health</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-6 text-center mb-3">
                                                <h4 class="text-warning"><?php echo $reportData['health_indicators']['pending_verifications']; ?></h4>
                                                <small>Pending Verifications</small>
                                            </div>
                                            <div class="col-6 text-center mb-3">
                                                <h4 class="text-info"><?php echo $reportData['health_indicators']['pending_documents']; ?></h4>
                                                <small>Pending Documents</small>
                                            </div>
                                            <div class="col-6 text-center">
                                                <h4 class="text-secondary"><?php echo $reportData['health_indicators']['inactive_users']; ?></h4>
                                                <small>Inactive Users</small>
                                            </div>
                                            <div class="col-6 text-center">
                                                <h4 class="text-muted"><?php echo $reportData['health_indicators']['draft_articles']; ?></h4>
                                                <small>Draft Articles</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Report Generation Info -->
                <div class="card mt-4">
                    <div class="card-body text-center text-muted">
                        <small>
                            <i class="fas fa-info-circle"></i> 
                            Report generated on <?php echo date('d/m/Y H:i:s'); ?> 
                            | Period: <?php echo date('d/m/Y', strtotime($dateFrom)); ?> to <?php echo date('d/m/Y', strtotime($dateTo)); ?>
                            | Type: <?php echo ucfirst($reportType); ?>
                        </small>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Chart configurations
        <?php if ($reportType == 'overview'): ?>
        // Service Distribution Chart
        const serviceCtx = document.getElementById('serviceChart').getContext('2d');
        new Chart(serviceCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_keys($reportData['users']['by_service'])); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($reportData['users']['by_service'])); ?>,
                    backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Document Status Chart
        const docCtx = document.getElementById('documentChart').getContext('2d');
        new Chart(docCtx, {
            type: 'bar',
            data: {
                labels: ['Pending', 'Approved', 'Rejected'],
                datasets: [{
                    label: 'Documents',
                    data: [
                        <?php echo $reportData['documents']['pending']; ?>,
                        <?php echo $reportData['documents']['approved']; ?>,
                        <?php echo $reportData['documents']['rejected']; ?>
                    ],
                    backgroundColor: ['#FFC107', '#28A745', '#DC3545']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        <?php endif; ?>

        <?php if ($reportType == 'users' && !empty($reportData['registration_trends'])): ?>
        // Registration Trends Chart
        const regCtx = document.getElementById('registrationChart').getContext('2d');
        new Chart(regCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($reportData['registration_trends'], 'date')); ?>,
                datasets: [{
                    label: 'New Registrations',
                    data: <?php echo json_encode(array_column($reportData['registration_trends'], 'count')); ?>,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        <?php endif; ?>

        function exportReport() {
            window.print();
        }

        function printReport() {
            window.print();
        }

        function refreshPage() {
            window.location.reload();
        }

        // Set max date to today
        document.getElementById('date_to').max = new Date().toISOString().split('T')[0];
        document.getElementById('date_from').max = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>
