<?php
session_start();
require_once '../config/database.php';
requireLogin();
requireAdmin();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_user':
                $result = createUser($_POST);
                break;
            case 'update_user':
                $result = updateUser($_POST);
                break;
            case 'delete_user':
                $result = deleteUser($_POST['user_id']);
                break;
        }
    }
}

// Get users with pagination and search
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$sql = "SELECT * FROM users WHERE role = 'user'";
$params = [];

if ($search) {
    $sql .= " AND (service_number LIKE ? OR full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
}

$sql .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get total count for pagination
$countSql = str_replace("SELECT *", "SELECT COUNT(*)", explode("ORDER BY", $sql)[0]);
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalUsers = $stmt->fetchColumn();
$totalPages = ceil($totalUsers / $limit);

// Functions
function createUser($data) {
    global $pdo;
    try {
        // Validate required fields
        $required = ['service_number', 'username', 'password', 'full_name', 'service_type', 'phone', 'email'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("$field is required");
            }
        }

        // Check if service number or username already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE service_number = ? OR username = ?");
        $stmt->execute([$data['service_number'], $data['username']]);
        if ($stmt->fetch()) {
            throw new Exception("Service number or username already exists");
        }

        // Hash password
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

        // Insert user
        $stmt = $pdo->prepare("INSERT INTO users (service_number, username, password, role, full_name, rank_designation, service_type, date_of_birth, date_of_joining, date_of_retirement, phone, email, address, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $data['service_number'],
            $data['username'],
            $hashedPassword,
            'user', // Explicitly set role as 'user'
            $data['full_name'],
            $data['rank_designation'] ?: null,
            $data['service_type'],
            $data['date_of_birth'] ?: null,
            $data['date_of_joining'] ?: null,
            $data['date_of_retirement'] ?: null,
            $data['phone'],
            $data['email'],
            $data['address'] ?: null,
            $data['status'] ?: 'active'
        ]);

        return ['success' => true, 'message' => 'User created successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function updateUser($data) {
    global $pdo;
    try {
        $userId = $data['user_id'];
        
        // Build update query dynamically
        $updateFields = [];
        $params = [];
        
        $allowedFields = ['full_name', 'rank_designation', 'date_of_birth', 'date_of_joining', 'date_of_retirement', 'phone', 'email', 'address', 'status'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateFields[] = "$field = ?";
                $params[] = $data[$field] ?: null;
            }
        }

        if (!empty($updateFields)) {
            $params[] = $userId;
            $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }

        return ['success' => true, 'message' => 'User updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function deleteUser($userId) {
    global $pdo;
    try {
        // Don't actually delete, just mark as inactive
        $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ? AND role = 'user'");
        $stmt->execute([$userId]);
        
        return ['success' => true, 'message' => 'User deactivated successfully'];
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
    <title>User Management - Veer Sahayata Admin</title>
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
                    <h1 class="h2">User Management</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                        <i class="fas fa-user-plus"></i> Create New User
                    </button>
                </div>

                <!-- Display Messages -->
                <?php if (isset($result)): ?>
                <div class="alert alert-<?php echo $result['success'] ? 'success' : 'danger'; ?> alert-dismissible fade show">
                    <?php echo $result['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Search and Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-8">
                                <label for="search" class="form-label">Search Users</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search by service number, name, email, or phone...">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <a href="users.php" class="btn btn-secondary">
                                    <i class="fas fa-undo"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Users Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h4><?php echo $totalUsers; ?></h4>
                                <p>Total Users</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <?php
                                $activeUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND status = 'active'")->fetchColumn();
                                ?>
                                <h4><?php echo $activeUsers; ?></h4>
                                <p>Active Users</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <?php
                                $pendingDocs = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM documents WHERE status = 'pending'")->fetchColumn();
                                ?>
                                <h4><?php echo $pendingDocs; ?></h4>
                                <p>Pending Documents</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <?php
                                $newUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
                                ?>
                                <h4><?php echo $newUsers; ?></h4>
                                <p>New This Month</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="card">
                    <div class="card-header">
                        <h5>Users List (<?php echo $totalUsers; ?> total)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($users)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Service Number</th>
                                        <th>Name</th>
                                        <th>Service Type</th>
                                        <th>Contact</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $user['service_number']; ?></strong><br>
                                            <small class="text-muted"><?php echo $user['username']; ?></small>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="../uploads/photos/<?php echo $user['photo'] ?: 'default-avatar.png'; ?>" 
                                                     alt="Photo" class="rounded-circle me-2" 
                                                     style="width: 40px; height: 40px; object-fit: cover;">
                                                <div>
                                                    <strong><?php echo $user['full_name']; ?></strong><br>
                                                    <small class="text-muted"><?php echo $user['rank_designation']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $user['service_type'] == 'Army' ? 'success' : 
                                                    ($user['service_type'] == 'Navy' ? 'primary' : 'info'); 
                                            ?>">
                                                <?php echo $user['service_type']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small>
                                                <i class="fas fa-phone"></i> <?php echo $user['phone']; ?><br>
                                                <i class="fas fa-envelope"></i> <?php echo $user['email']; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $user['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($user['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-info" onclick="viewUser(<?php echo $user['id']; ?>)" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-warning" onclick="editUser(<?php echo $user['id']; ?>)" title="Edit User">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $user['id']; ?>)" title="Deactivate User">
                                                    <i class="fas fa-user-times"></i>
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
                        <nav aria-label="Users pagination" class="mt-3">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                                </li>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>

                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h5>No Users Found</h5>
                            <p class="text-muted">
                                <?php echo $search ? 'No users match your search criteria.' : 'No users have been created yet.'; ?>
                            </p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                                Create First User
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="createUserForm" data-validate="true">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_user">
                        
                        <!-- Basic Information -->
                        <h6 class="text-primary mb-3">Basic Information</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="service_number" class="form-label">Service Number *</label>
                                    <input type="text" class="form-control" id="service_number" name="service_number" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username *</label>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password *</label>
                                    <input type="password" class="form-control" id="password" name="password" required minlength="6">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="full_name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" required>
                                </div>
                            </div>
                        </div>

                        <!-- Service Information -->
                        <h6 class="text-primary mb-3 mt-4">Service Information</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="service_type" class="form-label">Service Type *</label>
                                    <select class="form-select" id="service_type" name="service_type" required>
                                        <option value="">Select Service</option>
                                        <option value="Army">Army</option>
                                        <option value="Navy">Navy</option>
                                        <option value="Air Force">Air Force</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="rank_designation" class="form-label">Rank/Designation</label>
                                    <input type="text" class="form-control" id="rank_designation" name="rank_designation">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="date_of_birth" class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="date_of_joining" class="form-label">Date of Joining</label>
                                    <input type="date" class="form-control" id="date_of_joining" name="date_of_joining">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="date_of_retirement" class="form-label">Date of Retirement</label>
                                    <input type="date" class="form-control" id="date_of_retirement" name="date_of_retirement">
                                </div>
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <h6 class="text-primary mb-3 mt-4">Contact Information</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number *</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" required pattern="[0-9]{10}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"></textarea>
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
                        <button type="submit" class="btn btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- User Details Modal -->
    <div class="modal fade" id="userDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="userDetailsContent">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="editUserContent">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <script>
        function viewUser(userId) {
            fetch('../api/get_user_details.php?id=' + userId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('userDetailsContent').innerHTML = data.html;
                        new bootstrap.Modal(document.getElementById('userDetailsModal')).show();
                    } else {
                        alert('Error loading user details');
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        }

        function editUser(userId) {
            fetch('../api/get_edit_user_form.php?id=' + userId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('editUserContent').innerHTML = data.html;
                        new bootstrap.Modal(document.getElementById('editUserModal')).show();
                    } else {
                        alert('Error loading user edit form');
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        }

        function confirmDelete(userId) {
            if (confirm('Are you sure you want to deactivate this user? This action can be reversed later.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Auto-generate username from service number
        document.getElementById('service_number').addEventListener('input', function() {
            const serviceNumber = this.value;
            const username = serviceNumber.toLowerCase().replace(/[^a-z0-9]/g, '');
            document.getElementById('username').value = username;
        });

        // Phone number validation
        document.getElementById('phone').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').slice(0, 10);
        });
    </script>
</body>
</html>
