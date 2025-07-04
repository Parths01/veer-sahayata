<?php
session_start();
require_once '../config/database.php';
requireLogin();

// Get user pension data
$stmt = $pdo->prepare("SELECT * FROM pension WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$pension_records = $stmt->fetchAll();

// Get user data for verification
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Check for pending selfie verification
$stmt = $pdo->prepare("SELECT * FROM verifications WHERE user_id = ? AND verification_type = 'selfie' AND status = 'pending' ORDER BY due_date ASC LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$pending_selfie = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pension Management - Veer Sahayata</title>
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
                    <h1 class="h2">Pension Management</h1>
                    <div class="btn-group">
                        <button class="btn btn-outline-primary" onclick="printElement('pension-details')">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#selfieModal">
                            <i class="fas fa-camera"></i> 6-Month Verification
                        </button>
                    </div>
                </div>

                <!-- Selfie Verification Alert -->
                <?php if ($pending_selfie): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-camera"></i>
                    <strong>Selfie Verification Required!</strong> 
                    Your 6-month selfie verification is due by <?php echo date('d/m/Y', strtotime($pending_selfie['due_date'])); ?>.
                    <button class="btn btn-sm btn-warning ms-2" data-bs-toggle="modal" data-bs-target="#selfieModal">
                        Complete Now
                    </button>
                </div>
                <?php endif; ?>

                <div id="pension-details">
                    <!-- Current Pension Status -->
                    <?php if (!empty($pension_records)): ?>
                        <?php $latest_pension = $pension_records[0]; ?>
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card bg-primary text-white">
                                    <div class="card-body">
                                        <h5>Current Status</h5>
                                        <h3><?php echo ucfirst($latest_pension['status']); ?></h3>
                                        <small>As of <?php echo date('d/m/Y', strtotime($latest_pension['created_at'])); ?></small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <h5>Monthly Pension</h5>
                                        <h3>₹<?php echo number_format($latest_pension['monthly_amount'], 2); ?></h3>
                                        <small>Base Amount</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-info text-white">
                                    <div class="card-body">
                                        <h5>Last Disbursed</h5>
                                        <h3>₹<?php echo number_format($latest_pension['net_amount'], 2); ?></h3>
                                        <small>Net Amount</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pension Breakdown -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5>Latest Pension Breakdown</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr>
                                                <td><strong>Pension Type:</strong></td>
                                                <td><?php echo $latest_pension['pension_type']; ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Base Amount:</strong></td>
                                                <td>₹<?php echo number_format($latest_pension['monthly_amount'], 2); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>CGST:</strong></td>
                                                <td>₹<?php echo number_format($latest_pension['cgst'], 2); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>SGST:</strong></td>
                                                <td>₹<?php echo number_format($latest_pension['sgst'], 2); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Loan Deduction:</strong></td>
                                                <td>₹<?php echo number_format($latest_pension['loan_deduction'], 2); ?></td>
                                            </tr>
                                            <tr class="table-success">
                                                <td><strong>Net Amount:</strong></td>
                                                <td><strong>₹<?php echo number_format($latest_pension['net_amount'], 2); ?></strong></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <h6>Last Disbursement Date</h6>
                                                <h4 class="text-primary"><?php echo date('d F, Y', strtotime($latest_pension['disbursement_date'])); ?></h4>
                                                <p class="text-muted">Next disbursement expected on<br>
                                                <strong><?php echo date('d F, Y', strtotime($latest_pension['disbursement_date'] . ' +1 month')); ?></strong></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle fa-3x mb-3"></i>
                            <h5>No Pension Records Found</h5>
                            <p>Your pension records are being processed. Please contact the administration for more information.</p>
                        </div>
                    <?php endif; ?>

                    <!-- Pension History -->
                    <?php if (count($pension_records) > 1): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5>Pension History</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Pension Type</th>
                                            <th>Base Amount</th>
                                            <th>Deductions</th>
                                            <th>Net Amount</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pension_records as $record): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($record['disbursement_date'])); ?></td>
                                            <td><?php echo $record['pension_type']; ?></td>
                                            <td>₹<?php echo number_format($record['monthly_amount'], 2); ?></td>
                                            <td>₹<?php echo number_format($record['cgst'] + $record['sgst'] + $record['loan_deduction'], 2); ?></td>
                                            <td>₹<?php echo number_format($record['net_amount'], 2); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $record['status'] == 'disbursed' ? 'success' : ($record['status'] == 'approved' ? 'warning' : 'secondary'); ?>">
                                                    <?php echo ucfirst($record['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Selfie Verification Modal -->
    <div class="modal fade" id="selfieModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">6-Month Selfie Verification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Your Profile Photo</h6>
                            <img src="../uploads/photos/<?php echo $user['photo']; ?>" alt="Profile Photo" class="img-fluid rounded" style="max-height: 300px;">
                        </div>
                        <div class="col-md-6">
                            <h6>Take Current Selfie</h6>
                            <div class="text-center">
                                <video id="video" width="300" height="225" autoplay class="border rounded"></video>
                                <canvas id="canvas" width="300" height="225" style="display: none;"></canvas>
                                <br>
                                <button type="button" class="btn btn-primary mt-2" onclick="startCamera()">
                                    <i class="fas fa-camera"></i> Start Camera
                                </button>
                                <button type="button" class="btn btn-success mt-2" onclick="capturePhoto()" disabled id="captureBtn">
                                    <i class="fas fa-camera-retro"></i> Capture
                                </button>
                            </div>
                            <div id="capturedPhoto" class="mt-3" style="display: none;">
                                <h6>Captured Photo</h6>
                                <img id="capturedImg" class="img-fluid rounded" style="max-height: 200px;">
                                <br>
                                <button type="button" class="btn btn-success mt-2" onclick="submitVerification()">
                                    <i class="fas fa-check"></i> Submit Verification
                                </button>
                                <button type="button" class="btn btn-secondary mt-2" onclick="retakePhoto()">
                                    <i class="fas fa-redo"></i> Retake
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let video = document.getElementById('video');
        let canvas = document.getElementById('canvas');
        let captureBtn = document.getElementById('captureBtn');
        let stream;

        function startCamera() {
            // Check if browser supports getUserMedia
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('Camera access not supported in this browser. Please use a modern browser like Chrome, Firefox, or Safari.');
                return;
            }
            
            navigator.mediaDevices.getUserMedia({ 
                video: { 
                    width: { ideal: 300 }, 
                    height: { ideal: 225 },
                    facingMode: 'user' // Front camera for selfie
                } 
            })
                .then(function(s) {
                    stream = s;
                    video.srcObject = stream;
                    video.play();
                    captureBtn.disabled = false;
                })
                .catch(function(error) {
                    console.error('Camera error:', error);
                    let errorMessage = 'Unable to access camera. ';
                    
                    if (error.name === 'NotAllowedError') {
                        errorMessage += 'Please allow camera access and try again.';
                    } else if (error.name === 'NotFoundError') {
                        errorMessage += 'No camera found on this device.';
                    } else if (error.name === 'NotSupportedError') {
                        errorMessage += 'Camera not supported in this browser.';
                    } else {
                        errorMessage += error.message;
                    }
                    
                    alert(errorMessage);
                });
        }

        function capturePhoto() {
            let context = canvas.getContext('2d');
            context.drawImage(video, 0, 0, 300, 225);
            
            let dataURL = canvas.toDataURL('image/jpeg');
            document.getElementById('capturedImg').src = dataURL;
            document.getElementById('capturedPhoto').style.display = 'block';
            
            // Stop camera
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }
            video.style.display = 'none';
        }

        function retakePhoto() {
            document.getElementById('capturedPhoto').style.display = 'none';
            video.style.display = 'block';
            startCamera();
        }

        function submitVerification() {
            let dataURL = canvas.toDataURL('image/jpeg');
            
            // Here you would send the photo to the server for verification
            fetch('../api/submit_verification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    verification_type: 'selfie',
                    photo_data: dataURL
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Verification submitted successfully!');
                    location.reload();
                } else {
                    alert('Error submitting verification: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        }

        // Auto-start camera when modal opens
        document.getElementById('selfieModal').addEventListener('shown.bs.modal', function () {
            startCamera();
        });

        // Stop camera when modal closes
        document.getElementById('selfieModal').addEventListener('hidden.bs.modal', function () {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }
        });
    </script>
</body>
</html>
