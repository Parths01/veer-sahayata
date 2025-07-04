<?php
session_start();
require_once '../config/database.php';
requireLogin();

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get user documents
$stmt = $pdo->prepare("SELECT * FROM documents WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$documents = $stmt->fetchAll();

// Get dependents
$stmt = $pdo->prepare("SELECT * FROM dependents WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$dependents = $stmt->fetchAll();

// Organize documents by type
$doc_types = ['aadhar', 'pan', 'driving_license', 'electric_bill', 'service_record'];
$user_docs = [];
foreach ($documents as $doc) {
    $user_docs[$doc['document_type']] = $doc;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Veer Sahayata</title>
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
                    <h1 class="h2">Profile Management</h1>
                    <button class="btn btn-primary" onclick="printElement('profile-content')">
                        <i class="fas fa-print"></i> Print Profile
                    </button>
                </div>

                <!-- Display Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div id="profile-content">
                    <!-- Personal Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5><i class="fas fa-user"></i> Personal Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 text-center">
                                    <?php 
                                    $photoPath = '../uploads/photos/' . ($user['photo'] ?: 'default-avatar.png');
                                    if (!file_exists($photoPath) || !$user['photo']) {
                                        $photoPath = 'data:image/svg+xml;base64,' . base64_encode('<svg width="150" height="150" xmlns="http://www.w3.org/2000/svg"><rect width="150" height="150" fill="#e9ecef"/><text x="50%" y="50%" text-anchor="middle" dy=".3em" fill="#6c757d" font-size="14">No Photo</text></svg>');
                                    }
                                    ?>
                                    <img src="<?php echo $photoPath; ?>" 
                                         alt="Profile Photo" class="profile-img mb-3">
                                    <br>
                                    <?php if (isAdmin()): ?>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#photoModal">
                                        <i class="fas fa-camera"></i> Change Photo
                                    </button>
                                    <?php else: ?>
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle"></i> Contact admin to change photo
                                    </small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-9">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <table class="table table-borderless">
                                                <tr>
                                                    <td><strong>Service Number:</strong></td>
                                                    <td><?php echo $user['service_number']; ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Full Name:</strong></td>
                                                    <td><?php echo $user['full_name']; ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Rank/Designation:</strong></td>
                                                    <td><?php echo $user['rank_designation']; ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Service Type:</strong></td>
                                                    <td><?php echo $user['service_type']; ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Date of Birth:</strong></td>
                                                    <td><?php echo $user['date_of_birth'] ? date('d/m/Y', strtotime($user['date_of_birth'])) : 'Not provided'; ?></td>
                                                </tr>
                                            </table>
                                        </div>
                                        <div class="col-md-6">
                                            <table class="table table-borderless">
                                                <tr>
                                                    <td><strong>Date of Joining:</strong></td>
                                                    <td><?php echo $user['date_of_joining'] ? date('d/m/Y', strtotime($user['date_of_joining'])) : 'Not provided'; ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Date of Retirement:</strong></td>
                                                    <td><?php echo $user['date_of_retirement'] ? date('d/m/Y', strtotime($user['date_of_retirement'])) : 'Active Service'; ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Phone:</strong></td>
                                                    <td><?php echo $user['phone']; ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Email:</strong></td>
                                                    <td><?php echo $user['email']; ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Status:</strong></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $user['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                            <?php echo ucfirst($user['status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12">
                                            <strong>Address:</strong><br>
                                            <?php echo $user['address'] ?: 'Not provided'; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Documents Section -->
                    <div class="card mb-4" id="documents">
                        <div class="card-header">
                            <h5><i class="fas fa-file-alt"></i> Documents</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Document Type</th>
                                            <th>Status</th>
                                            <th>Upload Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($doc_types as $doc_type): ?>
                                        <tr>
                                            <td>
                                                <i class="fas fa-id-card me-2"></i>
                                                <?php echo ucwords(str_replace('_', ' ', $doc_type)); ?>
                                            </td>
                                            <td>
                                                <?php if (isset($user_docs[$doc_type])): ?>
                                                    <i class="fas fa-check-circle text-success me-1"></i>
                                                    <span class="badge bg-<?php 
                                                        echo $user_docs[$doc_type]['status'] == 'approved' ? 'success' : 
                                                            ($user_docs[$doc_type]['status'] == 'rejected' ? 'danger' : 'warning'); 
                                                    ?>">
                                                        <?php echo ucfirst($user_docs[$doc_type]['status']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <i class="fas fa-times-circle text-danger me-1"></i>
                                                    <span class="text-danger">Not Uploaded</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo isset($user_docs[$doc_type]) ? 
                                                    date('d/m/Y', strtotime($user_docs[$doc_type]['uploaded_at'])) : 'N/A'; ?>
                                            </td>
                                            <td>
                                                <?php if (isset($user_docs[$doc_type])): ?>
                                                    <button class="btn btn-sm btn-info" onclick="viewDocument('<?php echo $user_docs[$doc_type]['file_path']; ?>')">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                    <button class="btn btn-sm btn-warning" onclick="replaceDocument('<?php echo $doc_type; ?>')">
                                                        <i class="fas fa-edit"></i> Replace
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-primary" onclick="uploadDocument('<?php echo $doc_type; ?>')">
                                                        <i class="fas fa-upload"></i> Upload
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Dependents Section -->
                    <div class="card" id="dependents">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="fas fa-users"></i> Dependents</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDependentModal">
                                <i class="fas fa-user-plus"></i> Add Dependent
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($dependents)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Photo</th>
                                            <th>Name</th>
                                            <th>Relationship</th>
                                            <th>Date of Birth</th>
                                            <th>Aadhar Number</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dependents as $dependent): ?>
                                        <tr>
                                            <td>
                                                <img src="../uploads/dependents/<?php echo $dependent['photo'] ?: 'default-avatar.png'; ?>" 
                                                     alt="Photo" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;">
                                            </td>
                                            <td><?php echo $dependent['name']; ?></td>
                                            <td><?php echo ucfirst($dependent['relationship']); ?></td>
                                            <td><?php echo $dependent['date_of_birth'] ? date('d/m/Y', strtotime($dependent['date_of_birth'])) : 'N/A'; ?></td>
                                            <td><?php echo $dependent['aadhar_number'] ? '****-****-' . substr($dependent['aadhar_number'], -4) : 'N/A'; ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick="editDependent(<?php echo $dependent['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="deleteDependent(<?php echo $dependent['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h5>No Dependents Added</h5>
                                <p class="text-muted">Add your family members as dependents to ensure they receive benefits.</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDependentModal">
                                    Add Your First Dependent
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Upload Document Modal -->
    <div class="modal fade" id="uploadDocModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="../api/upload_document.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" id="document_type" name="document_type">
                        <div class="mb-3">
                            <label for="document_name" class="form-label">Document Name</label>
                            <input type="text" class="form-control" id="document_name" name="document_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="document_file" class="form-label">Select File</label>
                            <input type="file" class="form-control" id="document_file" name="document_file" 
                                   accept=".pdf,.jpg,.jpeg,.png" required>
                            <small class="text-muted">Accepted formats: PDF, JPG, PNG (Max size: 5MB)</small>
                        </div>
                        <div class="upload-area" style="display: none;">
                            <div class="file-preview"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Upload Document</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Dependent Modal -->
    <div class="modal fade" id="addDependentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Dependent</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="../api/add_dependent.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="dep_name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="dep_name" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="relationship" class="form-label">Relationship</label>
                                    <select class="form-select" id="relationship" name="relationship" required>
                                        <option value="">Select Relationship</option>
                                        <option value="spouse">Spouse</option>
                                        <option value="child">Child</option>
                                        <option value="parent">Parent</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="dep_dob" class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" id="dep_dob" name="date_of_birth">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="dep_aadhar" class="form-label">Aadhar Number</label>
                                    <input type="text" class="form-control" id="dep_aadhar" name="aadhar_number" 
                                           pattern="[0-9]{12}" maxlength="12" placeholder="Enter 12-digit Aadhar number">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="dep_photo" class="form-label">Photo</label>
                            <input type="file" class="form-control" id="dep_photo" name="photo" accept="image/*">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Dependent</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Photo Change Modal -->
    <div class="modal fade" id="photoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Profile Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="../api/upload_photo.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="upload-area text-center p-4">
                            <i class="fas fa-camera fa-3x text-muted mb-3"></i>
                            <p>Click to select or drag and drop your photo</p>
                            <input type="file" name="profile_photo" accept="image/*" required style="display: none;">
                            <div class="file-preview mt-3"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Photo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function uploadDocument(docType) {
            document.getElementById('document_type').value = docType;
            document.getElementById('document_name').value = docType.replace('_', ' ').toUpperCase();
            new bootstrap.Modal(document.getElementById('uploadDocModal')).show();
        }

        function replaceDocument(docType) {
            uploadDocument(docType);
        }

        function viewDocument(filePath) {
            window.open('../uploads/documents/' + filePath, '_blank');
        }

        function editDependent(dependentId) {
            // Implementation for editing dependent
            console.log('Edit dependent:', dependentId);
        }

        function deleteDependent(dependentId) {
            if (confirm('Are you sure you want to delete this dependent?')) {
                fetch('../api/delete_dependent.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({id: dependentId})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error deleting dependent: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
            }
        }

        // Aadhar number validation
        document.getElementById('dep_aadhar').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });

        // Photo upload handling
        document.addEventListener('DOMContentLoaded', function() {
            const uploadArea = document.querySelector('.upload-area');
            const fileInput = document.querySelector('input[name="profile_photo"]');
            const filePreview = document.querySelector('.file-preview');

            if (uploadArea && fileInput) {
                // Click to upload
                uploadArea.addEventListener('click', function() {
                    fileInput.click();
                });

                // File selection handler
                fileInput.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        // Validate file type
                        if (!file.type.startsWith('image/')) {
                            alert('Please select an image file');
                            return;
                        }

                        // Validate file size (5MB)
                        if (file.size > 5 * 1024 * 1024) {
                            alert('File size must be less than 5MB');
                            return;
                        }

                        // Show preview
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            filePreview.innerHTML = '<img src="' + e.target.result + '" style="max-width: 200px; max-height: 200px; border-radius: 8px;">';
                        };
                        reader.readAsDataURL(file);
                    }
                });

                // Drag and drop handling
                uploadArea.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    this.classList.add('drag-over');
                });

                uploadArea.addEventListener('dragleave', function(e) {
                    e.preventDefault();
                    this.classList.remove('drag-over');
                });

                uploadArea.addEventListener('drop', function(e) {
                    e.preventDefault();
                    this.classList.remove('drag-over');
                    
                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        fileInput.files = files;
                        fileInput.dispatchEvent(new Event('change'));
                    }
                });
            }
        });
    </script>
</body>
</html>
