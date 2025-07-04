<?php
session_start();
require_once '../config/database.php';
requireLogin();

// Get available schemes
$schemes = $pdo->query("SELECT * FROM schemes WHERE status = 'active' ORDER BY scheme_type, scheme_name")->fetchAll();

// Group schemes by type
$central_schemes = array_filter($schemes, function($scheme) { return $scheme['scheme_type'] == 'Central'; });
$state_schemes = array_filter($schemes, function($scheme) { return $scheme['scheme_type'] == 'State'; });
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Government Schemes - Veer Sahayata</title>
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
                    <h1 class="h2">Government Schemes</h1>
                    <button class="btn btn-primary" onclick="checkEligibility()">
                        <i class="fas fa-check-circle"></i> Check Eligibility
                    </button>
                </div>

                <!-- Central Government Schemes -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5><i class="fas fa-flag-usa"></i> Central Government Schemes</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($central_schemes)): ?>
                            <div class="row">
                                <?php foreach ($central_schemes as $scheme): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card border-primary">
                                        <div class="card-body">
                                            <h6 class="card-title text-primary"><?php echo $scheme['scheme_name']; ?></h6>
                                            <p class="card-text small"><?php echo substr($scheme['description'], 0, 150) . '...'; ?></p>
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewSchemeDetails(<?php echo $scheme['id']; ?>)">
                                                <i class="fas fa-info-circle"></i> View Details
                                            </button>
                                            <button class="btn btn-sm btn-primary" onclick="applyScheme(<?php echo $scheme['id']; ?>)">
                                                <i class="fas fa-external-link-alt"></i> Apply Now
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No central schemes available at the moment.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- State Government Schemes -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5><i class="fas fa-map-marker-alt"></i> State Government Schemes</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($state_schemes)): ?>
                            <div class="row">
                                <?php foreach ($state_schemes as $scheme): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card border-success">
                                        <div class="card-body">
                                            <h6 class="card-title text-success"><?php echo $scheme['scheme_name']; ?></h6>
                                            <p class="card-text small"><?php echo substr($scheme['description'], 0, 150) . '...'; ?></p>
                                            <button class="btn btn-sm btn-outline-success" onclick="viewSchemeDetails(<?php echo $scheme['id']; ?>)">
                                                <i class="fas fa-info-circle"></i> View Details
                                            </button>
                                            <button class="btn btn-sm btn-success" onclick="applyScheme(<?php echo $scheme['id']; ?>)">
                                                <i class="fas fa-external-link-alt"></i> Apply Now
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No state schemes available at the moment.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Eligibility Checker -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-user-check"></i> Eligibility Checker</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="eligibility-item p-3 border rounded mb-3">
                                    <i class="fas fa-medal fa-2x text-warning mb-2"></i>
                                    <h6>Service Record</h6>
                                    <p class="small text-muted">Check schemes based on your service history</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="eligibility-item p-3 border rounded mb-3">
                                    <i class="fas fa-users fa-2x text-info mb-2"></i>
                                    <h6>Family Status</h6>
                                    <p class="small text-muted">Benefits for dependents and family members</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="eligibility-item p-3 border rounded mb-3">
                                    <i class="fas fa-map-marked-alt fa-2x text-success mb-2"></i>
                                    <h6>Location Based</h6>
                                    <p class="small text-muted">State-specific schemes for your region</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Scheme Details Modal -->
    <div class="modal fade" id="schemeDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Scheme Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="schemeDetailsContent">
                    <!-- Content will be loaded dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="applySchemeBtn">Apply for Scheme</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewSchemeDetails(schemeId) {
            fetch('../api/get_scheme_details.php?id=' + schemeId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('schemeDetailsContent').innerHTML = data.html;
                        document.getElementById('applySchemeBtn').onclick = () => applyScheme(schemeId);
                        new bootstrap.Modal(document.getElementById('schemeDetailsModal')).show();
                    } else {
                        alert('Error loading scheme details');
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        }

        function applyScheme(schemeId) {
            // This would typically redirect to the official government portal
            alert('You will be redirected to the official government portal to apply for this scheme.');
            // window.open('https://government-portal.gov.in/scheme/' + schemeId, '_blank');
        }

        function checkEligibility() {
            alert('Eligibility checking feature will be implemented to analyze your profile and suggest eligible schemes.');
        }
    </script>
</body>
</html>
