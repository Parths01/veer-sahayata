<?php
session_start();
require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'submit_live_verification':
                $result = submitLiveVerification($_POST);
                break;
            case 'upload_live_photo':
                $result = uploadLivePhoto($_FILES, $_POST);
                break;
        }
    }
}

// Get user's basic information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get live verification status
$stmt = $pdo->prepare("
    SELECT * FROM user_verifications 
    WHERE user_id = ? AND verification_type = 'live_verification'
    ORDER BY submitted_at DESC 
    LIMIT 1
");
$stmt->execute([$user_id]);
$liveVerification = $stmt->fetch();

// Get uploaded live photo
$stmt = $pdo->prepare("
    SELECT * FROM verification_documents 
    WHERE user_id = ? AND document_type = 'live_photo'
    ORDER BY uploaded_at DESC
    LIMIT 1
");
$stmt->execute([$user_id]);
$livePhoto = $stmt->fetch();

// Functions
function submitLiveVerification($data) {
    global $pdo, $user_id;
    try {
        // Check if there's already a pending verification
        $stmt = $pdo->prepare("
            SELECT id FROM user_verifications 
            WHERE user_id = ? AND verification_type = 'live_verification' AND status IN ('pending', 'under_review')
        ");
        $stmt->execute([$user_id]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'You already have a pending live verification request'];
        }

        $stmt = $pdo->prepare("
            INSERT INTO user_verifications (
                user_id, verification_type, supporting_details, 
                declaration_accepted, status, submitted_at
            ) VALUES (?, 'live_verification', ?, ?, 'pending', NOW())
        ");
        $stmt->execute([
            $user_id,
            $data['supporting_details'] ?? '',
            isset($data['declaration']) ? 1 : 0
        ]);
        
        return ['success' => true, 'message' => 'Live verification request submitted successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function uploadLivePhoto($files, $data) {
    global $pdo, $user_id;
    try {
        if (!isset($files['live_photo']) || $files['live_photo']['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'No file uploaded or upload error'];
        }

        $file = $files['live_photo'];
        $allowedTypes = ['jpg', 'jpeg', 'png'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($fileExtension, $allowedTypes)) {
            return ['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG'];
        }

        if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
            return ['success' => false, 'message' => 'File size must be less than 5MB'];
        }

        // Create upload directory if it doesn't exist
        $uploadDir = '../uploads/live_verification/' . $user_id . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename
        $fileName = 'live_photo_' . time() . '.' . $fileExtension;
        $filePath = $uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            // Delete old photo if exists
            $stmt = $pdo->prepare("SELECT file_path FROM verification_documents WHERE user_id = ? AND document_type = 'live_photo'");
            $stmt->execute([$user_id]);
            $oldPhoto = $stmt->fetch();
            if ($oldPhoto && file_exists('../' . $oldPhoto['file_path'])) {
                unlink('../' . $oldPhoto['file_path']);
            }
            
            // Delete old record and insert new one
            $stmt = $pdo->prepare("DELETE FROM verification_documents WHERE user_id = ? AND document_type = 'live_photo'");
            $stmt->execute([$user_id]);
            
            // Save to database
            $stmt = $pdo->prepare("
                INSERT INTO verification_documents (
                    user_id, document_type, document_name, file_path, 
                    file_size, file_type, uploaded_at
                ) VALUES (?, 'live_photo', ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                $file['name'],
                str_replace('../', '', $filePath),
                $file['size'],
                $file['type']
            ]);
            
            return ['success' => true, 'message' => 'Live photo uploaded successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to upload file'];
        }
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
    <title>Live Verification - Veer Sahayata</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .verification-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
            margin-bottom: 20px;
        }
        .verification-card:hover {
            transform: translateY(-3px);
        }
        .live-photo-container {
            position: relative;
            border: 3px dashed #28a745;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            background: linear-gradient(135deg, #f8fff9 0%, #e8f5e8 100%);
            margin-bottom: 20px;
        }
        .live-photo-preview {
            max-width: 300px;
            max-height: 400px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: transform 0.3s;
        }
        .live-photo-preview:hover {
            transform: scale(1.05);
        }
        .status-indicator {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .status-pending { background: #ffc107; }
        .status-approved { background: #28a745; }
        .status-rejected { background: #dc3545; }
        .status-under_review { background: #17a2b8; }
        
        .verification-process {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }
        .process-step {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
        .step-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 20px;
        }
        .process-step.completed {
            background: rgba(40, 167, 69, 0.3);
        }
        .process-step.completed .step-icon {
            background: #28a745;
        }
        .process-step.current {
            background: rgba(255, 193, 7, 0.3);
            border: 2px solid #ffc107;
        }
        .capture-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            color: white;
            padding: 15px 30px;
            border-radius: 25px;
            font-size: 18px;
            font-weight: bold;
            transition: all 0.3s;
        }
        .capture-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(40, 167, 69, 0.3);
            color: white;
        }
        .camera-container {
            position: relative;
            border-radius: 15px;
            overflow: hidden;
            background: #000;
        }
        .camera-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border: 3px solid #28a745;
            border-radius: 15px;
            pointer-events: none;
        }
        .face-guide {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 200px;
            height: 250px;
            border: 2px dashed rgba(255, 255, 255, 0.8);
            border-radius: 50% 50% 50% 50% / 60% 60% 40% 40%;
            pointer-events: none;
        }
        .instruction-banner {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        .verification-benefits {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }
        .benefit-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .benefit-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #28a745;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
    </style>
</head>
<body>
    <?php include '../includes/user_header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/user_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-user-check text-success"></i> Live Verification</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <?php if (!$liveVerification || $liveVerification['status'] == 'rejected'): ?>
                            <button class="capture-btn" data-bs-toggle="modal" data-bs-target="#capturePhotoModal">
                                <i class="fas fa-camera"></i> Capture Live Photo
                            </button>
                            <?php endif; ?>
                            
                            <?php if ($livePhoto && (!$liveVerification || $liveVerification['status'] == 'rejected')): ?>
                            <button class="btn btn-success ms-2" data-bs-toggle="modal" data-bs-target="#submitVerificationModal">
                                <i class="fas fa-paper-plane"></i> Submit for Verification
                            </button>
                            <?php endif; ?>
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

                <!-- Live Verification Status -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="verification-card card">
                            <div class="card-header bg-success text-white">
                                <h5><i class="fas fa-heartbeat"></i> Live Verification Status</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($liveVerification): ?>
                                <div class="row">
                                    <div class="col-md-8">
                                        <h6>Current Status: 
                                            <span class="badge bg-<?php 
                                                echo $liveVerification['status'] == 'approved' ? 'success' : 
                                                    ($liveVerification['status'] == 'rejected' ? 'danger' : 
                                                        ($liveVerification['status'] == 'under_review' ? 'info' : 'warning')); 
                                            ?> fs-6 px-3 py-2">
                                                <?php echo ucfirst(str_replace('_', ' ', $liveVerification['status'])); ?>
                                            </span>
                                        </h6>
                                        <p class="mb-2">
                                            <strong>Submitted:</strong> <?php echo date('d M Y, h:i A', strtotime($liveVerification['submitted_at'])); ?>
                                        </p>
                                        
                                        <?php if ($liveVerification['status'] == 'approved'): ?>
                                        <div class="alert alert-success">
                                            <i class="fas fa-check-circle"></i> <strong>Verified as ALIVE!</strong><br>
                                            Your live verification has been confirmed by our admin team.
                                            <?php if ($liveVerification['verified_at']): ?>
                                            <br><small>Verified on: <?php echo date('d M Y, h:i A', strtotime($liveVerification['verified_at'])); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <?php elseif ($liveVerification['status'] == 'rejected'): ?>
                                        <div class="alert alert-danger">
                                            <strong>Verification Failed</strong><br>
                                            <?php if ($liveVerification['admin_remarks']): ?>
                                            <strong>Reason:</strong> <?php echo nl2br(htmlspecialchars($liveVerification['admin_remarks'])); ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php elseif ($liveVerification['status'] == 'under_review'): ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-clock"></i> <strong>Under Review</strong><br>
                                            Our admin team is currently reviewing your live photo verification.
                                        </div>
                                        <?php else: ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-hourglass-half"></i> <strong>Pending Review</strong><br>
                                            Your live photo verification is waiting for admin review.
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-4">
                                        <?php if ($livePhoto): ?>
                                        <div class="live-photo-container position-relative">
                                            <div class="status-indicator status-<?php echo $liveVerification['status']; ?>">
                                                <?php 
                                                echo $liveVerification['status'] == 'approved' ? '<i class="fas fa-check"></i>' : 
                                                    ($liveVerification['status'] == 'rejected' ? '<i class="fas fa-times"></i>' : 
                                                        ($liveVerification['status'] == 'under_review' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-clock"></i>'));
                                                ?>
                                            </div>
                                            <img src="../<?php echo $livePhoto['file_path']; ?>" 
                                                 alt="Live Photo" 
                                                 class="live-photo-preview"
                                                 onclick="showPhotoModal('<?php echo $livePhoto['file_path']; ?>')">
                                            <p class="mt-2 mb-0">
                                                <small class="text-muted">
                                                    Uploaded: <?php echo date('d M Y, h:i A', strtotime($livePhoto['uploaded_at'])); ?>
                                                </small>
                                            </p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-warning text-center">
                                    <i class="fas fa-camera fa-3x mb-3"></i>
                                    <h5>Live Verification Required</h5>
                                    <p>Please capture and submit a live photo for verification to confirm you are alive and eligible for benefits.</p>
                                    <?php if ($livePhoto): ?>
                                    <p><strong>Photo uploaded!</strong> Click "Submit for Verification" to proceed.</p>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Verification Process Steps -->
                <div class="verification-process">
                    <h5><i class="fas fa-list-ol"></i> Live Verification Process</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="process-step <?php echo (!empty($livePhoto)) ? 'completed' : 'current'; ?>">
                                <div class="step-icon">
                                    <i class="fas fa-camera"></i>
                                </div>
                                <div>
                                    <h6>Step 1: Capture Live Photo</h6>
                                    <small>Take a clear, recent photo of yourself</small>
                                </div>
                            </div>
                            <div class="process-step <?php echo ($liveVerification && $liveVerification['status'] != 'draft') ? 'completed' : ((!empty($livePhoto) && empty($liveVerification)) ? 'current' : ''); ?>">
                                <div class="step-icon">
                                    <i class="fas fa-paper-plane"></i>
                                </div>
                                <div>
                                    <h6>Step 2: Submit for Verification</h6>
                                    <small>Submit your photo for admin review</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="process-step <?php echo ($liveVerification && in_array($liveVerification['status'], ['under_review', 'approved', 'rejected'])) ? 'completed' : (($liveVerification && $liveVerification['status'] == 'pending') ? 'current' : ''); ?>">
                                <div class="step-icon">
                                    <i class="fas fa-user-check"></i>
                                </div>
                                <div>
                                    <h6>Step 3: Admin Verification</h6>
                                    <small>Admin compares photo and verifies identity</small>
                                </div>
                            </div>
                            <div class="process-step <?php echo ($liveVerification && $liveVerification['status'] == 'approved') ? 'completed' : (($liveVerification && $liveVerification['status'] == 'under_review') ? 'current' : ''); ?>">
                                <div class="step-icon">
                                    <i class="fas fa-certificate"></i>
                                </div>
                                <div>
                                    <h6>Step 4: Verification Complete</h6>
                                    <small>Live status confirmed and benefits activated</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Why Live Verification is Important -->
                <div class="verification-benefits">
                    <h5><i class="fas fa-info-circle text-primary"></i> Why Live Verification?</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="benefit-item">
                                <div class="benefit-icon">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <div>
                                    <h6>Prevent Fraud</h6>
                                    <small>Ensures benefits go to genuine, living beneficiaries</small>
                                </div>
                            </div>
                            <div class="benefit-item">
                                <div class="benefit-icon">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div>
                                    <h6>Activate Benefits</h6>
                                    <small>Required for pension and other benefit disbursements</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="benefit-item">
                                <div class="benefit-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div>
                                    <h6>Quick Process</h6>
                                    <small>Usually verified within 24-48 hours</small>
                                </div>
                            </div>
                            <div class="benefit-item">
                                <div class="benefit-icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div>
                                    <h6>One-Time Requirement</h6>
                                    <small>Valid for 12 months once approved</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Capture Photo Modal -->
    <div class="modal fade" id="capturePhotoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-camera"></i> Capture Live Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="instruction-banner">
                        <h6><i class="fas fa-info-circle"></i> Live Photo Instructions</h6>
                        <p class="mb-0">Position your face within the guide outline and ensure good lighting</p>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Camera Capture</h6>
                            <div class="camera-container">
                                <video id="video" width="100%" height="300" autoplay style="border-radius: 15px; background: #000;"></video>
                                <div class="camera-overlay"></div>
                                <div class="face-guide"></div>
                            </div>
                            <div class="text-center mt-3">
                                <button type="button" class="btn btn-primary btn-lg" id="captureBtn">
                                    <i class="fas fa-camera"></i> Capture Photo
                                </button>
                                <br><small class="text-muted mt-2 d-block">Click when your face is properly aligned</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6>Captured Photo</h6>
                            <canvas id="canvas" width="320" height="300" style="border-radius: 10px; display: none;"></canvas>
                            <div id="photoPreview" style="min-height: 300px; border: 2px dashed #ccc; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: #f8f9fa;">
                                <div class="text-center text-muted">
                                    <i class="fas fa-camera fa-3x mb-3 text-muted"></i>
                                    <p>Photo will appear here</p>
                                </div>
                            </div>
                            <form method="POST" enctype="multipart/form-data" id="photoForm" style="display: none;">
                                <input type="hidden" name="action" value="upload_live_photo">
                                <input type="file" name="live_photo" id="photoFile" accept="image/*" style="display: none;">
                                <div class="mt-3">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="fas fa-upload"></i> Upload Live Photo
                                    </button>
                                    <button type="button" class="btn btn-secondary ms-2" id="retakeBtn">
                                        <i class="fas fa-redo"></i> Retake
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <h6><i class="fas fa-info-circle"></i> Important Instructions:</h6>
                        <ul class="mb-0">
                            <li>Ensure good lighting and clear visibility of your face</li>
                            <li>Look directly at the camera</li>
                            <li>Remove any face coverings (except for religious reasons)</li>
                            <li>The photo will be compared with your service records</li>
                            <li>This verification confirms you are alive and eligible for benefits</li>
                        </ul>
                    </div>
                    
                    <div class="mt-3">
                        <h6>Alternative: Upload Photo File</h6>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="upload_live_photo">
                            <div class="mb-3">
                                <input type="file" class="form-control" name="live_photo" accept="image/*" required>
                                <div class="form-text">Upload a clear, recent photo (JPG, PNG - Max 5MB)</div>
                            </div>
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="fas fa-upload"></i> Upload Photo File
                            </button>
                        </form>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Photo View Modal -->
    <div class="modal fade" id="photoViewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Live Photo Verification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalPhoto" src="" alt="Live Photo" style="max-width: 100%; border-radius: 10px;">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Submit Verification Modal -->
    <div class="modal fade" id="submitVerificationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Submit Live Verification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="submit_live_verification">
                        
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            You are about to submit your live photo for verification. This confirms you are alive and eligible for benefits.
                        </div>
                        
                        <div class="mb-3">
                            <label for="supporting_details" class="form-label">Additional Details (Optional)</label>
                            <textarea class="form-control" name="supporting_details" rows="3"
                                      placeholder="Any additional information you'd like to provide..."></textarea>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="declaration" required>
                            <label class="form-check-label">
                                <strong>I declare that:</strong><br>
                                • I am alive and the person in the submitted photo<br>
                                • The photo is recent and genuine<br>
                                • I understand this verification is for benefit eligibility<br>
                                • I consent to identity verification by authorities
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-paper-plane"></i> Submit for Verification
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let video = document.getElementById('video');
        let canvas = document.getElementById('canvas');
        let photoPreview = document.getElementById('photoPreview');
        let photoForm = document.getElementById('photoForm');
        let captureBtn = document.getElementById('captureBtn');
        let stream = null;

        // Start camera
        async function startCamera() {
            try {
                // Request high resolution camera if available
                const constraints = {
                    video: {
                        width: { ideal: 1280 },
                        height: { ideal: 720 },
                        facingMode: 'user'
                    }
                };
                
                stream = await navigator.mediaDevices.getUserMedia(constraints);
                video.srcObject = stream;
                
                // Enable capture button when video starts playing
                video.addEventListener('loadedmetadata', function() {
                    captureBtn.disabled = false;
                    captureBtn.innerHTML = '<i class="fas fa-camera"></i> Capture Photo';
                });
                
            } catch (err) {
                console.error('Error accessing camera:', err);
                
                // Show user-friendly error message
                let errorMessage = 'Camera access denied or not available. ';
                if (err.name === 'NotAllowedError') {
                    errorMessage += 'Please allow camera access and try again.';
                } else if (err.name === 'NotFoundError') {
                    errorMessage += 'No camera found on this device.';
                } else {
                    errorMessage += 'Please check your camera settings.';
                }
                
                document.getElementById('video').style.display = 'none';
                document.querySelector('.camera-container').innerHTML = 
                    '<div class="alert alert-danger text-center">' +
                    '<i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>' +
                    errorMessage +
                    '</div>';
            }
        }

        // Capture photo with enhanced quality
        document.getElementById('captureBtn').addEventListener('click', function() {
            if (!stream) {
                alert('Camera not ready. Please wait or refresh the page.');
                return;
            }
            
            let context = canvas.getContext('2d');
            
            // Set canvas size to match video
            canvas.width = video.videoWidth || 640;
            canvas.height = video.videoHeight || 480;
            
            // Draw the video frame to canvas
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            // Convert to blob with high quality
            canvas.toBlob(function(blob) {
                if (!blob) {
                    alert('Failed to capture photo. Please try again.');
                    return;
                }
                
                let url = URL.createObjectURL(blob);
                photoPreview.innerHTML = '<img src="' + url + '" style="max-width: 100%; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">';
                
                // Create file from blob
                let file = new File([blob], 'live_photo_' + Date.now() + '.jpg', { type: 'image/jpeg' });
                let dt = new DataTransfer();
                dt.items.add(file);
                document.getElementById('photoFile').files = dt.files;
                
                photoForm.style.display = 'block';
                
                // Add success feedback
                captureBtn.innerHTML = '<i class="fas fa-check"></i> Photo Captured!';
                captureBtn.classList.remove('btn-primary');
                captureBtn.classList.add('btn-success');
                
            }, 'image/jpeg', 0.9); // High quality JPEG
        });

        // Retake photo
        document.getElementById('retakeBtn').addEventListener('click', function() {
            photoPreview.innerHTML = '<div class="text-center text-muted">' +
                                   '<i class="fas fa-camera fa-3x mb-3 text-muted"></i>' +
                                   '<p>Photo will appear here</p></div>';
            photoForm.style.display = 'none';
            
            // Reset capture button
            captureBtn.innerHTML = '<i class="fas fa-camera"></i> Capture Photo';
            captureBtn.classList.remove('btn-success');
            captureBtn.classList.add('btn-primary');
        });

        // Show photo modal
        function showPhotoModal(imagePath) {
            document.getElementById('modalPhoto').src = '../' + imagePath;
            new bootstrap.Modal(document.getElementById('photoViewModal')).show();
        }

        // Initialize camera when modal opens
        document.getElementById('capturePhotoModal').addEventListener('shown.bs.modal', function() {
            captureBtn.disabled = true;
            captureBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Starting Camera...';
            startCamera();
        });

        // Stop camera when modal closes
        document.getElementById('capturePhotoModal').addEventListener('hidden.bs.modal', function() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
            
            // Reset UI
            photoPreview.innerHTML = '<div class="text-center text-muted">' +
                                   '<i class="fas fa-camera fa-3x mb-3 text-muted"></i>' +
                                   '<p>Photo will appear here</p></div>';
            photoForm.style.display = 'none';
            captureBtn.innerHTML = '<i class="fas fa-camera"></i> Capture Photo';
            captureBtn.classList.remove('btn-success');
            captureBtn.classList.add('btn-primary');
            captureBtn.disabled = false;
        });

        // Auto-hide success messages
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-success');
            alerts.forEach(alert => {
                if (alert.querySelector('.btn-close')) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            });
        }, 5000);

        // Add loading state to forms
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                }
            });
        });
    </script>
</body>
</html>
