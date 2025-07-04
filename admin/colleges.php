<?php
session_start();
require_once '../config/database.php';
requireLogin();
requireAdmin();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_college':
                $result = addCollege($_POST);
                break;
            case 'edit_college':
                $result = editCollege($_POST);
                break;
            case 'delete_college':
                $result = deleteCollege($_POST['college_id']);
                break;
            case 'toggle_status':
                $result = toggleCollegeStatus($_POST['college_id']);
                break;
        }
    }
}

// Get colleges with pagination and filters
$status = $_GET['status'] ?? '';
$college_type = $_GET['college_type'] ?? '';
$district = $_GET['district'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$sql = "SELECT * FROM colleges WHERE 1=1";
$params = [];

if ($status) {
    $sql .= " AND status = ?";
    $params[] = $status;
}

if ($college_type) {
    $sql .= " AND college_type = ?";
    $params[] = $college_type;
}

if ($district) {
    $sql .= " AND district = ?";
    $params[] = $district;
}

if ($search) {
    $sql .= " AND (college_name LIKE ? OR college_code LIKE ? OR address LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

$sql .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$colleges = $stmt->fetchAll();

// Get total count for pagination
$countSql = str_replace("SELECT *", "SELECT COUNT(*)", explode("ORDER BY", $sql)[0]);
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalColleges = $stmt->fetchColumn();
$totalPages = ceil($totalColleges / $limit);

// Get college statistics
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM colleges")->fetchColumn(),
    'active' => $pdo->query("SELECT COUNT(*) FROM colleges WHERE status = 'active'")->fetchColumn(),
    'inactive' => $pdo->query("SELECT COUNT(*) FROM colleges WHERE status = 'inactive'")->fetchColumn(),
    'government' => $pdo->query("SELECT COUNT(*) FROM colleges WHERE college_type = 'Government'")->fetchColumn(),
    'private' => $pdo->query("SELECT COUNT(*) FROM colleges WHERE college_type = 'Private'")->fetchColumn(),
    'aided' => $pdo->query("SELECT COUNT(*) FROM colleges WHERE college_type = 'Aided'")->fetchColumn()
];

// Get unique districts for filter
$districtStmt = $pdo->query("SELECT DISTINCT district FROM colleges WHERE district IS NOT NULL AND district != '' ORDER BY district");
$districts = $districtStmt->fetchAll(PDO::FETCH_COLUMN);

// Functions
function addCollege($data) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO colleges (college_name, college_code, college_type, university_name, 
                                district, state, address, contact_number, email, 
                                website, principal_name, established_year, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['college_name'],
            $data['college_code'],
            $data['college_type'],
            $data['university_name'],
            $data['district'],
            $data['state'],
            $data['address'],
            $data['contact_number'],
            $data['email'],
            $data['website'],
            $data['principal_name'],
            $data['established_year'],
            $data['status']
        ]);
        
        return ['success' => true, 'message' => 'College added successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function editCollege($data) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            UPDATE colleges 
            SET college_name = ?, college_code = ?, college_type = ?, university_name = ?, 
                district = ?, state = ?, address = ?, contact_number = ?, email = ?, 
                website = ?, principal_name = ?, established_year = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $data['college_name'],
            $data['college_code'],
            $data['college_type'],
            $data['university_name'],
            $data['district'],
            $data['state'],
            $data['address'],
            $data['contact_number'],
            $data['email'],
            $data['website'],
            $data['principal_name'],
            $data['established_year'],
            $data['status'],
            $data['college_id']
        ]);
        
        return ['success' => true, 'message' => 'College updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function deleteCollege($collegeId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("DELETE FROM colleges WHERE id = ?");
        $stmt->execute([$collegeId]);
        
        return ['success' => true, 'message' => 'College deleted successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function toggleCollegeStatus($collegeId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            UPDATE colleges 
            SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END 
            WHERE id = ?
        ");
        $stmt->execute([$collegeId]);
        
        return ['success' => true, 'message' => 'College status updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Colleges Management - Veer Sahayata Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .college-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
            margin-bottom: 20px;
        }
        .college-card:hover {
            transform: translateY(-2px);
        }
        .status-badge {
            font-size: 0.85em;
            padding: 6px 12px;
            border-radius: 20px;
        }
        .college-type-government {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
        }
        .college-type-private {
            background: linear-gradient(135deg, #dc3545 0%, #a71e2a 100%);
            color: white;
        }
        .college-type-aided {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            color: white;
        }
        .truncate-text {
            max-height: 4.5em;
            overflow: hidden;
            position: relative;
        }
        .truncate-text::after {
            content: "...";
            position: absolute;
            bottom: 0;
            right: 0;
            background: white;
            padding-left: 20px;
        }
        .college-actions {
            white-space: nowrap;
        }
        .college-info {
            font-size: 0.9em;
            color: #666;
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
                    <h1 class="h2"><i class="fas fa-university text-primary"></i> Colleges Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCollegeModal">
                                <i class="fas fa-plus"></i> Add New College
                            </button>
                            <button class="btn btn-outline-secondary" onclick="refreshPage()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Display Messages -->
                <?php if (isset($result)): ?>
                <div class="alert alert-<?php echo $result['success'] ? 'success' : 'danger'; ?> alert-dismissible fade show">
                    <?php echo $result['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- College Statistics -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h4><?php echo $stats['total']; ?></h4>
                                <p class="mb-0">Total Colleges</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h4><?php echo $stats['active']; ?></h4>
                                <p class="mb-0">Active</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-secondary text-white">
                            <div class="card-body text-center">
                                <h4><?php echo $stats['inactive']; ?></h4>
                                <p class="mb-0">Inactive</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h4><?php echo $stats['government']; ?></h4>
                                <p class="mb-0">Government</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <h4><?php echo $stats['private']; ?></h4>
                                <p class="mb-0">Private</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-warning text-dark">
                            <div class="card-body text-center">
                                <h4><?php echo $stats['aided']; ?></h4>
                                <p class="mb-0">Aided</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search by name, code, or address...">
                            </div>
                            <div class="col-md-2">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="college_type" class="form-label">College Type</label>
                                <select class="form-select" id="college_type" name="college_type">
                                    <option value="">All Types</option>
                                    <option value="Government" <?php echo $college_type == 'Government' ? 'selected' : ''; ?>>Government</option>
                                    <option value="Private" <?php echo $college_type == 'Private' ? 'selected' : ''; ?>>Private</option>
                                    <option value="Aided" <?php echo $college_type == 'Aided' ? 'selected' : ''; ?>>Aided</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="district" class="form-label">District</label>
                                <select class="form-select" id="district" name="district">
                                    <option value="">All Districts</option>
                                    <?php foreach ($districts as $dist): ?>
                                    <option value="<?php echo htmlspecialchars($dist); ?>" <?php echo $district == $dist ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dist); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                                <a href="colleges.php" class="btn btn-secondary">
                                    <i class="fas fa-undo"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Colleges Table -->
                <div class="card">
                    <div class="card-header">
                        <h5>Registered Colleges (<?php echo $totalColleges; ?> total)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($colleges)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>College Details</th>
                                        <th>Type</th>
                                        <th>Location</th>
                                        <th>Contact</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($colleges as $college): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($college['college_name']); ?></strong><br>
                                                <small class="text-muted">Code: <?php echo htmlspecialchars($college['college_code']); ?></small><br>
                                                <?php if ($college['university_name']): ?>
                                                <small class="college-info"><?php echo htmlspecialchars($college['university_name']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge status-badge college-type-<?php echo strtolower($college['college_type']); ?>">
                                                <?php echo $college['college_type']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="college-info">
                                                <?php if ($college['district']): ?>
                                                    <?php echo htmlspecialchars($college['district']); ?><br>
                                                <?php endif; ?>
                                                <?php if ($college['state']): ?>
                                                    <?php echo htmlspecialchars($college['state']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="college-info">
                                                <?php if ($college['contact_number']): ?>
                                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($college['contact_number']); ?><br>
                                                <?php endif; ?>
                                                <?php if ($college['email']): ?>
                                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($college['email']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $college['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($college['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small>
                                                <?php echo date('d/m/Y', strtotime($college['created_at'])); ?><br>
                                                <?php echo date('H:i', strtotime($college['created_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="college-actions">
                                                <button class="btn btn-sm btn-info" onclick="viewCollege('<?php echo $college['id']; ?>')" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-warning" onclick="editCollege('<?php echo $college['id']; ?>')" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-<?php echo $college['status'] == 'active' ? 'secondary' : 'success'; ?>" 
                                                        onclick="toggleStatus('<?php echo $college['id']; ?>')" 
                                                        title="<?php echo $college['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                    <i class="fas fa-<?php echo $college['status'] == 'active' ? 'pause' : 'play'; ?>"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="deleteCollege('<?php echo $college['id']; ?>')" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <nav aria-label="Colleges pagination" class="mt-3">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status); ?>&college_type=<?php echo urlencode($college_type); ?>&district=<?php echo urlencode($district); ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                                </li>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&college_type=<?php echo urlencode($college_type); ?>&district=<?php echo urlencode($district); ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status); ?>&college_type=<?php echo urlencode($college_type); ?>&district=<?php echo urlencode($district); ?>&search=<?php echo urlencode($search); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>

                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-university fa-3x text-muted mb-3"></i>
                            <h5>No Colleges Found</h5>
                            <p class="text-muted">
                                <?php echo $search ? 'No colleges match your search criteria.' : 'No colleges have been registered yet.'; ?>
                            </p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCollegeModal">
                                <i class="fas fa-plus"></i> Add First College
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add College Modal -->
    <div class="modal fade" id="addCollegeModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add New College</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_college">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="college_name" class="form-label">College Name *</label>
                                    <input type="text" class="form-control" id="college_name" name="college_name" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="college_code" class="form-label">College Code *</label>
                                    <input type="text" class="form-control" id="college_code" name="college_code" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="college_type" class="form-label">College Type *</label>
                                    <select class="form-select" id="college_type" name="college_type" required>
                                        <option value="">Select Type</option>
                                        <option value="Government">Government</option>
                                        <option value="Private">Private</option>
                                        <option value="Aided">Aided</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="established_year" class="form-label">Established Year</label>
                                    <input type="number" class="form-control" id="established_year" name="established_year" 
                                           min="1800" max="<?php echo date('Y'); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="university_name" class="form-label">University/Board Name</label>
                            <input type="text" class="form-control" id="university_name" name="university_name">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="district" class="form-label">District</label>
                                    <input type="text" class="form-control" id="district" name="district">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="state" class="form-label">State</label>
                                    <input type="text" class="form-control" id="state" name="state">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Full Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="contact_number" class="form-label">Contact Number</label>
                                    <input type="tel" class="form-control" id="contact_number" name="contact_number">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="website" class="form-label">Website URL</label>
                                    <input type="url" class="form-control" id="website" name="website">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="principal_name" class="form-label">Principal Name</label>
                            <input type="text" class="form-control" id="principal_name" name="principal_name">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add College
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit College Modal -->
    <div class="modal fade" id="editCollegeModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit College</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editCollegeForm">
                    <div class="modal-body" id="editCollegeContent">
                        <!-- Content will be loaded dynamically -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save"></i> Update College
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View College Modal -->
    <div class="modal fade" id="viewCollegeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-eye"></i> College Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewCollegeContent">
                    <!-- Content will be loaded dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewCollege(collegeId) {
            // Load college details
            fetch('../api/get_college_details.php?id=' + collegeId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('viewCollegeContent').innerHTML = data.html;
                        new bootstrap.Modal(document.getElementById('viewCollegeModal')).show();
                    } else {
                        alert('Error loading college details');
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        }

        function editCollege(collegeId) {
            // Load college edit form
            fetch('../api/get_edit_college_form.php?id=' + collegeId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('editCollegeContent').innerHTML = data.html;
                        new bootstrap.Modal(document.getElementById('editCollegeModal')).show();
                    } else {
                        alert('Error loading college details');
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        }

        function toggleStatus(collegeId) {
            if (confirm('Are you sure you want to change the status of this college?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="college_id" value="${collegeId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteCollege(collegeId) {
            if (confirm('Are you sure you want to delete this college? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_college">
                    <input type="hidden" name="college_id" value="${collegeId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function refreshPage() {
            window.location.reload();
        }

        // Auto-hide success messages
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-success');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
