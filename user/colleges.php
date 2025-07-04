<?php
session_start();
require_once '../config/database.php';
requireLogin();

// Get colleges with filters
$state_filter = $_GET['state'] ?? '';
$search = $_GET['search'] ?? '';

$sql = "SELECT * FROM colleges WHERE 1=1";
$params = [];

if ($state_filter) {
    $sql .= " AND state = ?";
    $params[] = $state_filter;
}

if ($search) {
    $sql .= " AND (college_name LIKE ? OR courses_offered LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY state, college_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$colleges = $stmt->fetchAll();

// Get unique states for filter
$states = $pdo->query("SELECT DISTINCT state FROM colleges ORDER BY state")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Defence Quota Colleges - Veer Sahayata</title>
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
                    <h1 class="h2">Defence Quota Colleges</h1>
                </div>

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search Colleges</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search by college name or courses...">
                            </div>
                            <div class="col-md-4">
                                <label for="state" class="form-label">Filter by State</label>
                                <select class="form-select" id="state" name="state">
                                    <option value="">All States</option>
                                    <?php foreach ($states as $state): ?>
                                    <option value="<?php echo $state; ?>" <?php echo $state_filter == $state ? 'selected' : ''; ?>>
                                        <?php echo $state; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <a href="colleges.php" class="btn btn-secondary">
                                    <i class="fas fa-undo"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Results Summary -->
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Found <?php echo count($colleges); ?> colleges with defence quota
                    <?php if ($state_filter): ?>
                        in <?php echo $state_filter; ?>
                    <?php endif; ?>
                    <?php if ($search): ?>
                        matching "<?php echo htmlspecialchars($search); ?>"
                    <?php endif; ?>
                </div>

                <!-- Colleges List -->
                <?php if (!empty($colleges)): ?>
                    <div class="row">
                        <?php foreach ($colleges as $college): ?>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0"><?php echo $college['college_name']; ?></h6>
                                    <small><?php echo $college['district']; ?>, <?php echo $college['state']; ?></small>
                                </div>
                                <div class="card-body">
                                    <div class="mb-2">
                                        <strong>Courses Offered:</strong>
                                        <p class="small"><?php echo $college['courses_offered']; ?></p>
                                    </div>
                                    
                                    <div class="row mb-2">
                                        <div class="col-6">
                                            <strong>Defence Quota Seats:</strong><br>
                                            <span class="badge bg-success"><?php echo $college['defence_quota_seats']; ?> seats</span>
                                        </div>
                                        <div class="col-6">
                                            <strong>Contact:</strong><br>
                                            <small><?php echo $college['contact_number']; ?></small>
                                        </div>
                                    </div>

                                    <?php if ($college['scholarship_info']): ?>
                                    <div class="mb-2">
                                        <strong>Scholarship Info:</strong>
                                        <p class="small text-success"><?php echo $college['scholarship_info']; ?></p>
                                    </div>
                                    <?php endif; ?>

                                    <?php if ($college['admission_process']): ?>
                                    <div class="mb-2">
                                        <strong>Admission Process:</strong>
                                        <p class="small"><?php echo substr($college['admission_process'], 0, 100) . '...'; ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer">
                                    <div class="btn-group w-100">
                                        <button class="btn btn-outline-primary btn-sm" onclick="viewCollegeDetails(<?php echo $college['id']; ?>)">
                                            <i class="fas fa-info-circle"></i> Details
                                        </button>
                                        <?php if ($college['website']): ?>
                                        <a href="<?php echo $college['website']; ?>" target="_blank" class="btn btn-outline-success btn-sm">
                                            <i class="fas fa-external-link-alt"></i> Website
                                        </a>
                                        <?php endif; ?>
                                        <button class="btn btn-primary btn-sm" onclick="getDirections('<?php echo urlencode($college['address']); ?>')">
                                            <i class="fas fa-map-marker-alt"></i> Directions
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h5>No Colleges Found</h5>
                        <p class="text-muted">Try adjusting your search criteria or browse all colleges.</p>
                        <a href="colleges.php" class="btn btn-primary">View All Colleges</a>
                    </div>
                <?php endif; ?>

                <!-- Important Information -->
                <div class="card mt-4">
                    <div class="card-header bg-warning text-dark">
                        <h5><i class="fas fa-exclamation-triangle"></i> Important Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Defence Quota Eligibility:</h6>
                                <ul class="small">
                                    <li>Children of serving/retired defence personnel</li>
                                    <li>War widows and their children</li>
                                    <li>Disabled defence personnel and their children</li>
                                    <li>Ex-servicemen and their dependents</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Required Documents:</h6>
                                <ul class="small">
                                    <li>Service certificate/discharge certificate</li>
                                    <li>Identity certificate from unit/record office</li>
                                    <li>Birth certificate of the candidate</li>
                                    <li>Academic transcripts and certificates</li>
                                </ul>
                            </div>
                        </div>
                        <div class="alert alert-info mt-3">
                            <strong>Note:</strong> Admission procedures and eligibility criteria may vary by institution. 
                            Please contact the college directly for the most up-to-date information.
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- College Details Modal -->
    <div class="modal fade" id="collegeDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">College Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="collegeDetailsContent">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewCollegeDetails(collegeId) {
            fetch('../api/get_college_details.php?id=' + collegeId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('collegeDetailsContent').innerHTML = data.html;
                        new bootstrap.Modal(document.getElementById('collegeDetailsModal')).show();
                    } else {
                        alert('Error loading college details');
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        }

        function getDirections(address) {
            // Open Google Maps with directions
            const mapsUrl = `https://www.google.com/maps/search/?api=1&query=${address}`;
            window.open(mapsUrl, '_blank');
        }
    </script>
</body>
</html>
