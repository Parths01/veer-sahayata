<?php
session_start();
require_once '../config/database.php';
requireLogin();
requireAdmin();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_hospital':
                $result = addHospital($_POST);
                break;
            case 'edit_hospital':
                $result = editHospital($_POST);
                break;
            case 'delete_hospital':
                $result = deleteHospital($_POST['hospital_id']);
                break;
            case 'toggle_status':
                $result = toggleHospitalStatus($_POST['hospital_id']);
                break;
        }
    }
}

// Get hospitals with pagination and filters
$status = $_GET['status'] ?? '';
$hospital_type = $_GET['hospital_type'] ?? '';
$district = $_GET['district'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$sql = "SELECT * FROM hospitals WHERE 1=1";
$params = [];

if ($status) {
    $sql .= " AND status = ?";
    $params[] = $status;
}

if ($hospital_type) {
    $sql .= " AND hospital_type = ?";
    $params[] = $hospital_type;
}

if ($district) {
    $sql .= " AND district = ?";
    $params[] = $district;
}

if ($search) {
    $sql .= " AND (hospital_name LIKE ? OR hospital_code LIKE ? OR address LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

$sql .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$hospitals = $stmt->fetchAll();

// Get total count for pagination
$countSql = str_replace("SELECT *", "SELECT COUNT(*)", explode("ORDER BY", $sql)[0]);
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalHospitals = $stmt->fetchColumn();
$totalPages = ceil($totalHospitals / $limit);

// Get hospital statistics
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM hospitals")->fetchColumn(),
    'active' => $pdo->query("SELECT COUNT(*) FROM hospitals WHERE status = 'active'")->fetchColumn(),
    'inactive' => $pdo->query("SELECT COUNT(*) FROM hospitals WHERE status = 'inactive'")->fetchColumn(),
    'government' => $pdo->query("SELECT COUNT(*) FROM hospitals WHERE hospital_type = 'Government'")->fetchColumn(),
    'private' => $pdo->query("SELECT COUNT(*) FROM hospitals WHERE hospital_type = 'Private'")->fetchColumn(),
    'armed_forces' => $pdo->query("SELECT COUNT(*) FROM hospitals WHERE hospital_type = 'Armed Forces'")->fetchColumn()
];

// Get unique districts for filter
$districtStmt = $pdo->query("SELECT DISTINCT district FROM hospitals WHERE district IS NOT NULL AND district != '' ORDER BY district");
$districts = $districtStmt->fetchAll(PDO::FETCH_COLUMN);

// Functions
function addHospital($data) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO hospitals (hospital_name, hospital_code, hospital_type, 
                                 district, state, address, contact_number, email, 
                                 website, medical_director, established_year, 
                                 bed_capacity, specialties, emergency_services, 
                                 veterans_facility, insurance_accepted, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['hospital_name'],
            $data['hospital_code'],
            $data['hospital_type'],
            $data['district'],
            $data['state'],
            $data['address'],
            $data['contact_number'],
            $data['email'],
            $data['website'],
            $data['medical_director'],
            $data['established_year'],
            $data['bed_capacity'],
            $data['specialties'],
            isset($data['emergency_services']) ? 1 : 0,
            isset($data['veterans_facility']) ? 1 : 0,
            $data['insurance_accepted'],
            $data['status']
        ]);
        
        return ['success' => true, 'message' => 'Hospital added successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function editHospital($data) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            UPDATE hospitals 
            SET hospital_name = ?, hospital_code = ?, hospital_type = ?, 
                district = ?, state = ?, address = ?, contact_number = ?, email = ?, 
                website = ?, medical_director = ?, established_year = ?, 
                bed_capacity = ?, specialties = ?, emergency_services = ?, 
                veterans_facility = ?, insurance_accepted = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $data['hospital_name'],
            $data['hospital_code'],
            $data['hospital_type'],
            $data['district'],
            $data['state'],
            $data['address'],
            $data['contact_number'],
            $data['email'],
            $data['website'],
            $data['medical_director'],
            $data['established_year'],
            $data['bed_capacity'],
            $data['specialties'],
            isset($data['emergency_services']) ? 1 : 0,
            isset($data['veterans_facility']) ? 1 : 0,
            $data['insurance_accepted'],
            $data['status'],
            $data['hospital_id']
        ]);
        
        return ['success' => true, 'message' => 'Hospital updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function deleteHospital($hospitalId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("DELETE FROM hospitals WHERE id = ?");
        $stmt->execute([$hospitalId]);
        
        return ['success' => true, 'message' => 'Hospital deleted successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function toggleHospitalStatus($hospitalId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            UPDATE hospitals 
            SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END 
            WHERE id = ?
        ");
        $stmt->execute([$hospitalId]);
        
        return ['success' => true, 'message' => 'Hospital status updated successfully'];
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
    <title>Hospitals Management - Veer Sahayata Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .hospital-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
            margin-bottom: 20px;
        }
        .hospital-card:hover {
            transform: translateY(-2px);
        }
        .status-badge {
            font-size: 0.85em;
            padding: 6px 12px;
            border-radius: 20px;
        }
        .hospital-type-government {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
        }
        .hospital-type-private {
            background: linear-gradient(135deg, #dc3545 0%, #a71e2a 100%);
            color: white;
        }
        .hospital-type-armed-forces {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            color: white;
        }
        .hospital-actions {
            white-space: nowrap;
        }
        .hospital-info {
            font-size: 0.9em;
            color: #666;
        }
        .veteran-badge {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: black;
            font-size: 0.75em;
            padding: 2px 8px;
            border-radius: 12px;
        }
        .emergency-badge {
            background: linear-gradient(135deg, #dc3545 0%, #a71e2a 100%);
            color: white;
            font-size: 0.75em;
            padding: 2px 8px;
            border-radius: 12px;
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
                    <h1 class="h2"><i class="fas fa-hospital text-primary"></i> Hospitals Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHospitalModal">
                                <i class="fas fa-plus"></i> Add New Hospital
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

                <!-- Hospital Statistics -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h4><?php echo $stats['total']; ?></h4>
                                <p class="mb-0">Total Hospitals</p>
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
                                <h4><?php echo $stats['armed_forces']; ?></h4>
                                <p class="mb-0">Armed Forces</p>
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
                                <label for="hospital_type" class="form-label">Hospital Type</label>
                                <select class="form-select" id="hospital_type" name="hospital_type">
                                    <option value="">All Types</option>
                                    <option value="Government" <?php echo $hospital_type == 'Government' ? 'selected' : ''; ?>>Government</option>
                                    <option value="Private" <?php echo $hospital_type == 'Private' ? 'selected' : ''; ?>>Private</option>
                                    <option value="Armed Forces" <?php echo $hospital_type == 'Armed Forces' ? 'selected' : ''; ?>>Armed Forces</option>
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
                                <a href="hospitals.php" class="btn btn-secondary">
                                    <i class="fas fa-undo"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Hospitals Table -->
                <div class="card">
                    <div class="card-header">
                        <h5>Registered Hospitals (<?php echo $totalHospitals; ?> total)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($hospitals)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Hospital Details</th>
                                        <th>Type</th>
                                        <th>Location</th>
                                        <th>Contact</th>
                                        <th>Capacity</th>
                                        <th>Services</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($hospitals as $hospital): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($hospital['hospital_name']); ?></strong><br>
                                                <small class="text-muted">Code: <?php echo htmlspecialchars($hospital['hospital_code']); ?></small><br>
                                                <?php if ($hospital['medical_director']): ?>
                                                <small class="hospital-info">Director: <?php echo htmlspecialchars($hospital['medical_director']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge status-badge hospital-type-<?php echo strtolower(str_replace(' ', '-', $hospital['hospital_type'])); ?>">
                                                <?php echo $hospital['hospital_type']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="hospital-info">
                                                <?php if ($hospital['district']): ?>
                                                    <?php echo htmlspecialchars($hospital['district']); ?><br>
                                                <?php endif; ?>
                                                <?php if ($hospital['state']): ?>
                                                    <?php echo htmlspecialchars($hospital['state']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="hospital-info">
                                                <?php if ($hospital['contact_number']): ?>
                                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($hospital['contact_number']); ?><br>
                                                <?php endif; ?>
                                                <?php if ($hospital['email']): ?>
                                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($hospital['email']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="hospital-info">
                                                <?php if ($hospital['bed_capacity']): ?>
                                                    <i class="fas fa-bed"></i> <?php echo $hospital['bed_capacity']; ?> beds<br>
                                                <?php endif; ?>
                                                <?php if ($hospital['established_year']): ?>
                                                    <small>Est. <?php echo $hospital['established_year']; ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <?php if ($hospital['emergency_services']): ?>
                                                    <span class="emergency-badge">Emergency</span><br>
                                                <?php endif; ?>
                                                <?php if ($hospital['veterans_facility']): ?>
                                                    <span class="veteran-badge">Veterans</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $hospital['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($hospital['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="hospital-actions">
                                                <button class="btn btn-sm btn-info" onclick="viewHospital('<?php echo $hospital['id']; ?>')" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-warning" onclick="editHospital('<?php echo $hospital['id']; ?>')" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-<?php echo $hospital['status'] == 'active' ? 'secondary' : 'success'; ?>" 
                                                        onclick="toggleStatus('<?php echo $hospital['id']; ?>')" 
                                                        title="<?php echo $hospital['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                    <i class="fas fa-<?php echo $hospital['status'] == 'active' ? 'pause' : 'play'; ?>"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="deleteHospital('<?php echo $hospital['id']; ?>')" title="Delete">
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
                        <nav aria-label="Hospitals pagination" class="mt-3">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status); ?>&hospital_type=<?php echo urlencode($hospital_type); ?>&district=<?php echo urlencode($district); ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                                </li>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&hospital_type=<?php echo urlencode($hospital_type); ?>&district=<?php echo urlencode($district); ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status); ?>&hospital_type=<?php echo urlencode($hospital_type); ?>&district=<?php echo urlencode($district); ?>&search=<?php echo urlencode($search); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>

                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-hospital fa-3x text-muted mb-3"></i>
                            <h5>No Hospitals Found</h5>
                            <p class="text-muted">
                                <?php echo $search ? 'No hospitals match your search criteria.' : 'No hospitals have been registered yet.'; ?>
                            </p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHospitalModal">
                                <i class="fas fa-plus"></i> Add First Hospital
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Hospital Modal -->
    <div class="modal fade" id="addHospitalModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Hospital</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_hospital">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="hospital_name" class="form-label">Hospital Name *</label>
                                    <input type="text" class="form-control" id="hospital_name" name="hospital_name" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="hospital_code" class="form-label">Hospital Code *</label>
                                    <input type="text" class="form-control" id="hospital_code" name="hospital_code" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="hospital_type" class="form-label">Hospital Type *</label>
                                    <select class="form-select" id="hospital_type" name="hospital_type" required>
                                        <option value="">Select Type</option>
                                        <option value="Government">Government</option>
                                        <option value="Private">Private</option>
                                        <option value="Armed Forces">Armed Forces</option>
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
                                    <label for="bed_capacity" class="form-label">Bed Capacity</label>
                                    <input type="number" class="form-control" id="bed_capacity" name="bed_capacity" min="1">
                                </div>
                            </div>
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
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="medical_director" class="form-label">Medical Director</label>
                                    <input type="text" class="form-control" id="medical_director" name="medical_director">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="insurance_accepted" class="form-label">Insurance Accepted</label>
                                    <input type="text" class="form-control" id="insurance_accepted" name="insurance_accepted" 
                                           placeholder="e.g., CGHS, ECHS, Private Insurance">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="specialties" class="form-label">Medical Specialties</label>
                            <textarea class="form-control" id="specialties" name="specialties" rows="2" 
                                      placeholder="e.g., Cardiology, Neurology, Orthopedics"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="emergency_services" name="emergency_services" value="1">
                                        <label class="form-check-label" for="emergency_services">
                                            Emergency Services Available
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="veterans_facility" name="veterans_facility" value="1">
                                        <label class="form-check-label" for="veterans_facility">
                                            Veterans Facility
                                        </label>
                                    </div>
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
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add Hospital
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Hospital Modal -->
    <div class="modal fade" id="editHospitalModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Hospital</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editHospitalForm">
                    <div class="modal-body" id="editHospitalContent">
                        <!-- Content will be loaded dynamically -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save"></i> Update Hospital
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Hospital Modal -->
    <div class="modal fade" id="viewHospitalModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-eye"></i> Hospital Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewHospitalContent">
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
        function viewHospital(hospitalId) {
            // Load hospital details
            fetch('../api/get_hospital_details.php?id=' + hospitalId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('viewHospitalContent').innerHTML = data.html;
                        new bootstrap.Modal(document.getElementById('viewHospitalModal')).show();
                    } else {
                        alert('Error loading hospital details');
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        }

        function editHospital(hospitalId) {
            // Load hospital edit form
            fetch('../api/get_edit_hospital_form.php?id=' + hospitalId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('editHospitalContent').innerHTML = data.html;
                        new bootstrap.Modal(document.getElementById('editHospitalModal')).show();
                    } else {
                        alert('Error loading hospital details');
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        }

        function toggleStatus(hospitalId) {
            if (confirm('Are you sure you want to change the status of this hospital?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="hospital_id" value="${hospitalId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteHospital(hospitalId) {
            if (confirm('Are you sure you want to delete this hospital? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_hospital">
                    <input type="hidden" name="hospital_id" value="${hospitalId}">
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
