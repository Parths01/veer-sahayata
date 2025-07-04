<?php
session_start();
require_once '../config/database.php';
requireLogin();
requireAdmin();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_scheme':
                $result = addScheme($_POST);
                break;
            case 'edit_scheme':
                $result = editScheme($_POST);
                break;
            case 'delete_scheme':
                $result = deleteScheme($_POST['scheme_id']);
                break;
            case 'toggle_status':
                $result = toggleSchemeStatus($_POST['scheme_id']);
                break;
        }
    }
}

// Get schemes with pagination and filters
$status = $_GET['status'] ?? '';
$scheme_type = $_GET['scheme_type'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$sql = "SELECT * FROM schemes WHERE 1=1";
$params = [];

if ($status) {
    $sql .= " AND status = ?";
    $params[] = $status;
}

if ($scheme_type) {
    $sql .= " AND scheme_type = ?";
    $params[] = $scheme_type;
}

if ($search) {
    $sql .= " AND (scheme_name LIKE ? OR description LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm]);
}

$sql .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$schemes = $stmt->fetchAll();

// Get total count for pagination
$countSql = str_replace("SELECT *", "SELECT COUNT(*)", explode("ORDER BY", $sql)[0]);
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalSchemes = $stmt->fetchColumn();
$totalPages = ceil($totalSchemes / $limit);

// Get scheme statistics
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM schemes")->fetchColumn(),
    'active' => $pdo->query("SELECT COUNT(*) FROM schemes WHERE status = 'active'")->fetchColumn(),
    'inactive' => $pdo->query("SELECT COUNT(*) FROM schemes WHERE status = 'inactive'")->fetchColumn(),
    'central' => $pdo->query("SELECT COUNT(*) FROM schemes WHERE scheme_type = 'Central'")->fetchColumn(),
    'state' => $pdo->query("SELECT COUNT(*) FROM schemes WHERE scheme_type = 'State'")->fetchColumn()
];

// Functions
function addScheme($data) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO schemes (scheme_name, scheme_type, description, eligibility_criteria, 
                               application_process, documents_required, benefits, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['scheme_name'],
            $data['scheme_type'],
            $data['description'],
            $data['eligibility_criteria'],
            $data['application_process'],
            $data['documents_required'],
            $data['benefits'],
            $data['status']
        ]);
        
        return ['success' => true, 'message' => 'Scheme added successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function editScheme($data) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            UPDATE schemes 
            SET scheme_name = ?, scheme_type = ?, description = ?, eligibility_criteria = ?, 
                application_process = ?, documents_required = ?, benefits = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $data['scheme_name'],
            $data['scheme_type'],
            $data['description'],
            $data['eligibility_criteria'],
            $data['application_process'],
            $data['documents_required'],
            $data['benefits'],
            $data['status'],
            $data['scheme_id']
        ]);
        
        return ['success' => true, 'message' => 'Scheme updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function deleteScheme($schemeId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("DELETE FROM schemes WHERE id = ?");
        $stmt->execute([$schemeId]);
        
        return ['success' => true, 'message' => 'Scheme deleted successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function toggleSchemeStatus($schemeId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            UPDATE schemes 
            SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END 
            WHERE id = ?
        ");
        $stmt->execute([$schemeId]);
        
        return ['success' => true, 'message' => 'Scheme status updated successfully'];
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
    <title>Schemes Management - Veer Sahayata Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .scheme-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
            margin-bottom: 20px;
        }
        .scheme-card:hover {
            transform: translateY(-2px);
        }
        .status-badge {
            font-size: 0.85em;
            padding: 6px 12px;
            border-radius: 20px;
        }
        .scheme-type-central {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
        }
        .scheme-type-state {
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
        .scheme-actions {
            white-space: nowrap;
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
                    <h1 class="h2"><i class="fas fa-clipboard-list text-primary"></i> Schemes Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSchemeModal">
                                <i class="fas fa-plus"></i> Add New Scheme
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

                <!-- Scheme Statistics -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h4><?php echo $stats['total']; ?></h4>
                                <p class="mb-0">Total Schemes</p>
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
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h4><?php echo $stats['central']; ?></h4>
                                <p class="mb-0">Central Schemes</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-dark">
                            <div class="card-body text-center">
                                <h4><?php echo $stats['state']; ?></h4>
                                <p class="mb-0">State Schemes</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search by scheme name or description...">
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="scheme_type" class="form-label">Scheme Type</label>
                                <select class="form-select" id="scheme_type" name="scheme_type">
                                    <option value="">All Types</option>
                                    <option value="Central" <?php echo $scheme_type == 'Central' ? 'selected' : ''; ?>>Central</option>
                                    <option value="State" <?php echo $scheme_type == 'State' ? 'selected' : ''; ?>>State</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                                <a href="schemes.php" class="btn btn-secondary">
                                    <i class="fas fa-undo"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Schemes Table -->
                <div class="card">
                    <div class="card-header">
                        <h5>Government Schemes (<?php echo $totalSchemes; ?> total)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($schemes)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Scheme Name</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($schemes as $scheme): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($scheme['scheme_name']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge status-badge scheme-type-<?php echo strtolower($scheme['scheme_type']); ?>">
                                                <?php echo $scheme['scheme_type']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="truncate-text">
                                                <?php echo htmlspecialchars(substr($scheme['description'], 0, 100)); ?>
                                                <?php if (strlen($scheme['description']) > 100): ?>...<?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $scheme['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($scheme['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small>
                                                <?php echo date('d/m/Y', strtotime($scheme['created_at'])); ?><br>
                                                <?php echo date('H:i', strtotime($scheme['created_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="scheme-actions">
                                                <button class="btn btn-sm btn-info" onclick="viewScheme('<?php echo $scheme['id']; ?>')" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-warning" onclick="editScheme('<?php echo $scheme['id']; ?>')" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-<?php echo $scheme['status'] == 'active' ? 'secondary' : 'success'; ?>" 
                                                        onclick="toggleStatus('<?php echo $scheme['id']; ?>')" 
                                                        title="<?php echo $scheme['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                    <i class="fas fa-<?php echo $scheme['status'] == 'active' ? 'pause' : 'play'; ?>"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="deleteScheme('<?php echo $scheme['id']; ?>')" title="Delete">
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
                        <nav aria-label="Schemes pagination" class="mt-3">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status); ?>&scheme_type=<?php echo urlencode($scheme_type); ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                                </li>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&scheme_type=<?php echo urlencode($scheme_type); ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status); ?>&scheme_type=<?php echo urlencode($scheme_type); ?>&search=<?php echo urlencode($search); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>

                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                            <h5>No Schemes Found</h5>
                            <p class="text-muted">
                                <?php echo $search ? 'No schemes match your search criteria.' : 'No schemes have been added yet.'; ?>
                            </p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSchemeModal">
                                <i class="fas fa-plus"></i> Add First Scheme
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Scheme Modal -->
    <div class="modal fade" id="addSchemeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Scheme</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_scheme">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="scheme_name" class="form-label">Scheme Name *</label>
                                    <input type="text" class="form-control" id="scheme_name" name="scheme_name" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="scheme_type" class="form-label">Scheme Type *</label>
                                    <select class="form-select" id="scheme_type" name="scheme_type" required>
                                        <option value="">Select Type</option>
                                        <option value="Central">Central</option>
                                        <option value="State">State</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description *</label>
                            <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="eligibility_criteria" class="form-label">Eligibility Criteria</label>
                            <textarea class="form-control" id="eligibility_criteria" name="eligibility_criteria" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="application_process" class="form-label">Application Process</label>
                            <textarea class="form-control" id="application_process" name="application_process" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="documents_required" class="form-label">Documents Required</label>
                            <textarea class="form-control" id="documents_required" name="documents_required" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="benefits" class="form-label">Benefits</label>
                            <textarea class="form-control" id="benefits" name="benefits" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add Scheme
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Scheme Modal -->
    <div class="modal fade" id="editSchemeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Scheme</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editSchemeForm">
                    <div class="modal-body" id="editSchemeContent">
                        <!-- Content will be loaded dynamically -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save"></i> Update Scheme
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Scheme Modal -->
    <div class="modal fade" id="viewSchemeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-eye"></i> Scheme Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewSchemeContent">
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
        function viewScheme(schemeId) {
            // Load scheme details (you would need to create an API endpoint for this)
            fetch('../api/get_scheme_details.php?id=' + schemeId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('viewSchemeContent').innerHTML = data.html;
                        new bootstrap.Modal(document.getElementById('viewSchemeModal')).show();
                    } else {
                        alert('Error loading scheme details');
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        }

        function editScheme(schemeId) {
            // Load scheme edit form (you would need to create an API endpoint for this)
            fetch('../api/get_edit_scheme_form.php?id=' + schemeId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('editSchemeContent').innerHTML = data.html;
                        new bootstrap.Modal(document.getElementById('editSchemeModal')).show();
                    } else {
                        alert('Error loading scheme details');
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        }

        function toggleStatus(schemeId) {
            if (confirm('Are you sure you want to change the status of this scheme?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="scheme_id" value="${schemeId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteScheme(schemeId) {
            if (confirm('Are you sure you want to delete this scheme? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_scheme">
                    <input type="hidden" name="scheme_id" value="${schemeId}">
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
