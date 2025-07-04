<?php
session_start();
require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'submit_verification':
                $result = submitVerificationRequest($_POST);
                break;
            case 'upload_document':
                $result = uploadDocument($_FILES, $_POST);
                break;
            case 'resubmit_verification':
                $result = resubmitVerification($_POST);
                break;
        }
    }
}

// Get user's basic information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get verification status
$stmt = $pdo->prepare("
    SELECT * FROM user_verifications 
    WHERE user_id = ? 
    ORDER BY submitted_at DESC 
    LIMIT 1
");
$stmt->execute([$user_id]);
$verification = $stmt->fetch();

// Get all verification attempts
$stmt = $pdo->prepare("
    SELECT * FROM user_verifications 
    WHERE user_id = ? 
    ORDER BY submitted_at DESC
");
$stmt->execute([$user_id]);
$verificationHistory = $stmt->fetchAll();

// Get uploaded documents
$stmt = $pdo->prepare("
    SELECT * FROM verification_documents 
    WHERE user_id = ? 
    ORDER BY document_type ASC, uploaded_at DESC
");
$stmt->execute([$user_id]);
$documents = $stmt->fetchAll();

// Get required document types
$requiredDocuments = [
    'identity_proof' => 'Identity Proof (Aadhar/PAN/Passport)',
    'service_certificate' => 'Service/Discharge Certificate',
    'address_proof' => 'Address Proof',
    'bank_details' => 'Bank Account Details',
    'photograph' => 'Recent Passport Size Photograph'
];

// Check if user has uploaded all required documents
$uploadedDocTypes = array_column($documents, 'document_type');
$missingDocuments = array_diff(array_keys($requiredDocuments), $uploadedDocTypes);
$verificationComplete = empty($missingDocuments);

// Functions
function submitVerificationRequest($data) {
    global $pdo, $user_id;
    try {
        // Check if there's already a pending verification
        $stmt = $pdo->prepare("
            SELECT id FROM user_verifications 
            WHERE user_id = ? AND status IN ('pending', 'under_review')
        ");
        $stmt->execute([$user_id]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'You already have a pending verification request'];
        }

        $stmt = $pdo->prepare("
            INSERT INTO user_verifications (
                user_id, verification_type, supporting_details, 
                declaration_accepted, status, submitted_at
            ) VALUES (?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([
            $user_id,
            $data['verification_type'],
            $data['supporting_details'],
            isset($data['declaration']) ? 1 : 0
        ]);
        
        return ['success' => true, 'message' => 'Verification request submitted successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function uploadDocument($files, $data) {
    global $pdo, $user_id;
    try {
        if (!isset($files['document']) || $files['document']['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'No file uploaded or upload error'];
        }

        $file = $files['document'];
        $allowedTypes = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($fileExtension, $allowedTypes)) {
            return ['success' => false, 'message' => 'Invalid file type. Allowed: PDF, JPG, PNG, DOC, DOCX'];
        }

        if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
            return ['success' => false, 'message' => 'File size must be less than 5MB'];
        }

        // Create upload directory if it doesn't exist
        $uploadDir = '../uploads/verification_documents/' . $user_id . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename
        $fileName = uniqid() . '_' . time() . '.' . $fileExtension;
        $filePath = $uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            // Save to database
            $stmt = $pdo->prepare("
                INSERT INTO verification_documents (
                    user_id, document_type, document_name, file_path, 
                    file_size, file_type, uploaded_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                $data['document_type'],
                $file['name'],
                $filePath,
                $file['size'],
                $file['type']
            ]);
            
            return ['success' => true, 'message' => 'Document uploaded successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to upload file'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function resubmitVerification($data) {
    global $pdo, $user_id;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_verifications (
                user_id, verification_type, supporting_details, 
                declaration_accepted, status, submitted_at, resubmission_notes
            ) VALUES (?, ?, ?, ?, 'pending', NOW(), ?)
        ");
        $stmt->execute([
            $user_id,
            $data['verification_type'],
            $data['supporting_details'],
            isset($data['declaration']) ? 1 : 0,
            $data['resubmission_notes']
        ]);
        
        return ['success' => true, 'message' => 'Verification resubmitted successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Check if user has uploaded all required documents
$uploadedDocTypes = array_column($documents, 'document_type');
$missingDocuments = array_diff(array_keys($requiredDocuments), $uploadedDocTypes);
$verificationComplete = empty($missingDocuments);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Verification - Veer Sahayata</title>
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
            transform: translateY(-2px);
        }
        .status-badge {
            font-size: 0.9em;
            padding: 8px 16px;
            border-radius: 20px;
        }
        .status-pending {
            background: linear-gradient(135deg, #ffa726 0%, #ff9800 100%);
            color: white;
        }
        .status-approved {
            background: linear-gradient(135deg, #66bb6a 0%, #4caf50 100%);
            color: white;
        }
        .status-rejected {
            background: linear-gradient(135deg, #ef5350 0%, #f44336 100%);
            color: white;
        }
        .status-under_review {
            background: linear-gradient(135deg, #42a5f5 0%, #2196f3 100%);
            color: white;
        }
        .document-item {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .document-item.uploaded {
            border-color: #28a745;
            background: #f8fff9;
        }
        .document-item.missing {
            border-color: #dc3545;
            background: #fff5f5;
        }
        .upload-progress {
            height: 4px;
            border-radius: 2px;
            background: #e9ecef;
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #4caf50, #8bc34a);
            transition: width 0.3s;
        }
        .steps-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
        }
        .step-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-weight: bold;
        }
        .step-item.completed {
            background: rgba(40, 167, 69, 0.3);
        }
        .step-item.completed .step-number {
            background: #28a745;
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
                    <h1 class="h2"><i class="fas fa-shield-alt text-primary"></i> Document Verification</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <?php if (!$verification || $verification['status'] == 'rejected'): ?>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#submitVerificationModal">
                                <i class="fas fa-paper-plane"></i> Submit for Verification
                            </button>
                            <?php endif; ?>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
                                <i class="fas fa-upload"></i> Upload Document
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

                <!-- Verification Status Overview -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="verification-card card">
                            <div class="card-header bg-primary text-white">
                                <h5><i class="fas fa-info-circle"></i> Verification Status Overview</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <?php if ($verification): ?>
                                        <h6>Current Status: 
                                            <span class="status-badge status-<?php echo $verification['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $verification['status'])); ?>
                                            </span>
                                        </h6>
                                        <p class="mb-2">
                                            <strong>Submitted:</strong> <?php echo date('d M Y, h:i A', strtotime($verification['submitted_at'])); ?>
                                        </p>
                                        <p class="mb-2">
                                            <strong>Verification Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $verification['verification_type'])); ?>
                                        </p>
                                        
                                        <?php if ($verification['status'] == 'rejected' && $verification['admin_remarks']): ?>
                                        <div class="alert alert-danger mt-3">
                                            <strong>Rejection Reason:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($verification['admin_remarks'])); ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($verification['status'] == 'approved'): ?>
                                        <div class="alert alert-success mt-3">
                                            <i class="fas fa-check-circle"></i> <strong>Congratulations!</strong> 
                                            Your documents have been verified successfully.
                                            <?php if ($verification['verified_at']): ?>
                                            <br><small>Verified on: <?php echo date('d M Y, h:i A', strtotime($verification['verified_at'])); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php else: ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle"></i> 
                                            <strong>Verification Not Started</strong><br>
                                            Please upload all required documents and submit for verification.
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-center">
                                            <div class="upload-progress mb-3">
                                                <div class="progress-bar" style="width: <?php echo round((count($uploadedDocTypes) / count($requiredDocuments)) * 100); ?>%"></div>
                                            </div>
                                            <h6><?php echo count($uploadedDocTypes); ?> of <?php echo count($requiredDocuments); ?> Documents Uploaded</h6>
                                            <small class="text-muted">
                                                <?php echo round((count($uploadedDocTypes) / count($requiredDocuments)) * 100); ?>% Complete
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Verification Steps -->
                <div class="steps-container mb-4">
                    <h5><i class="fas fa-list-ol"></i> Verification Process</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="step-item <?php echo count($uploadedDocTypes) > 0 ? 'completed' : ''; ?>">
                                <div class="step-number">1</div>
                                <div>
                                    <h6>Upload Documents</h6>
                                    <small>Upload all required documents</small>
                                </div>
                            </div>
                            <div class="step-item <?php echo $verificationComplete ? 'completed' : ''; ?>">
                                <div class="step-number">2</div>
                                <div>
                                    <h6>Complete Upload</h6>
                                    <small>Ensure all documents are uploaded</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="step-item <?php echo $verification ? 'completed' : ''; ?>">
                                <div class="step-number">3</div>
                                <div>
                                    <h6>Submit for Review</h6>
                                    <small>Submit documents for admin verification</small>
                                </div>
                            </div>
                            <div class="step-item <?php echo ($verification && $verification['status'] == 'approved') ? 'completed' : ''; ?>">
                                <div class="step-number">4</div>
                                <div>
                                    <h6>Verification Complete</h6>
                                    <small>Admin reviews and approves documents</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
            margin-bottom: 20px;
        }
        .verification-card:hover {
            transform: translateY(-2px);
        }
        .status-badge {
            font-size: 0.9em;
            padding: 8px 16px;
            border-radius: 20px;
        }
        .status-pending {
            background: linear-gradient(135deg, #ffa726 0%, #ff9800 100%);
            color: white;
        }
        .status-approved {
            background: linear-gradient(135deg, #66bb6a 0%, #4caf50 100%);
            color: white;
        }
        .status-rejected {
            background: linear-gradient(135deg, #ef5350 0%, #f44336 100%);
            color: white;
        }
        .status-under_review {
            background: linear-gradient(135deg, #42a5f5 0%, #2196f3 100%);
            color: white;
        }
        .document-item {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .document-item.uploaded {
            border-color: #28a745;
            background: #f8fff9;
        }
        .document-item.missing {
            border-color: #dc3545;
            background: #fff5f5;
        }
        .upload-progress {
            height: 4px;
            border-radius: 2px;
            background: #e9ecef;
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #4caf50, #8bc34a);
            transition: width 0.3s;
        }
        .verification-timeline {
            position: relative;
            padding-left: 30px;
        }
        .verification-timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 30px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -23px;
            top: 8px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #fff;
            border: 3px solid #dee2e6;
        }
        .timeline-item.completed::before {
            border-color: #28a745;
            background: #28a745;
        }
        .steps-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
        }
        .step-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-weight: bold;
        }
        .step-item.completed {
            background: rgba(40, 167, 69, 0.3);
        }
        .step-item.completed .step-number {
            background: #28a745;
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
                    <h1 class="h2"><i class="fas fa-shield-alt text-primary"></i> Document Verification</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <?php if (!$verification || $verification['status'] == 'rejected'): ?>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#submitVerificationModal">
                                <i class="fas fa-paper-plane"></i> Submit for Verification
                            </button>
                            <?php endif; ?>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
                                <i class="fas fa-upload"></i> Upload Document
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

                <!-- Verification Status Overview -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="verification-card card">
                            <div class="card-header bg-primary text-white">
                                <h5><i class="fas fa-info-circle"></i> Verification Status Overview</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <?php if ($verification): ?>
                                        <h6>Current Status: 
                                            <span class="status-badge status-<?php echo $verification['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $verification['status'])); ?>
                                            </span>
                                        </h6>
                                        <p class="mb-2">
                                            <strong>Submitted:</strong> <?php echo date('d M Y, h:i A', strtotime($verification['submitted_at'])); ?>
                                        </p>
                                        <p class="mb-2">
                                            <strong>Verification Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $verification['verification_type'])); ?>
                                        </p>
                                        
                                        <?php if ($verification['status'] == 'rejected' && $verification['admin_remarks']): ?>
                                        <div class="alert alert-danger mt-3">
                                            <strong>Rejection Reason:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($verification['admin_remarks'])); ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($verification['status'] == 'approved'): ?>
                                        <div class="alert alert-success mt-3">
                                            <i class="fas fa-check-circle"></i> <strong>Congratulations!</strong> 
                                            Your documents have been verified successfully.
                                            <?php if ($verification['verified_at']): ?>
                                            <br><small>Verified on: <?php echo date('d M Y, h:i A', strtotime($verification['verified_at'])); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php else: ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle"></i> 
                                            <strong>Verification Not Started</strong><br>
                                            Please upload all required documents and submit for verification.
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-center">
                                            <div class="upload-progress mb-3">
                                                <div class="progress-bar" style="width: <?php echo round((count($uploadedDocTypes) / count($requiredDocuments)) * 100); ?>%"></div>
                                            </div>
                                            <h6><?php echo count($uploadedDocTypes); ?> of <?php echo count($requiredDocuments); ?> Documents Uploaded</h6>
                                            <small class="text-muted">
                                                <?php echo round((count($uploadedDocTypes) / count($requiredDocuments)) * 100); ?>% Complete
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Verification Steps -->
                <div class="steps-container mb-4">
                    <h5><i class="fas fa-list-ol"></i> Verification Process</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="step-item <?php echo count($uploadedDocTypes) > 0 ? 'completed' : ''; ?>">
                                <div class="step-number">1</div>
                                <div>
                                    <h6>Upload Documents</h6>
                                    <small>Upload all required documents</small>
                                </div>
                            </div>
                            <div class="step-item <?php echo $verification && $verification['status'] != 'draft' ? 'completed' : ''; ?>">
                                <div class="step-number">2</div>
                                <div>
                                    <h6>Submit for Review</h6>
                                    <small>Submit verification request</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="step-item <?php echo $verification && in_array($verification['status'], ['under_review', 'approved']) ? 'completed' : ''; ?>">
                                <div class="step-number">3</div>
                                <div>
                                    <h6>Admin Review</h6>
                                    <small>Documents under review</small>
                                </div>
                            </div>
                            <div class="step-item <?php echo $verification && $verification['status'] == 'approved' ? 'completed' : ''; ?>">
                                <div class="step-number">4</div>
                                <div>
                                    <h6>Verification Complete</h6>
                                    <small>Account verified and active</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Document Upload Section -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="verification-card card">
                            <div class="card-header bg-success text-white">
                                <h5><i class="fas fa-file-upload"></i> Required Documents</h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($requiredDocuments as $docType => $docName): ?>
                                <div class="document-item <?php echo in_array($docType, $uploadedDocTypes) ? 'uploaded' : 'missing'; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">
                                                <?php if (in_array($docType, $uploadedDocTypes)): ?>
                                                <i class="fas fa-check-circle text-success"></i>
                                                <?php else: ?>
                                                <i class="fas fa-times-circle text-danger"></i>
                                                <?php endif; ?>
                                                <?php echo $docName; ?>
                                            </h6>
                                            
                                            <?php 
                                            $docInfo = array_filter($documents, function($doc) use ($docType) {
                                                return $doc['document_type'] == $docType;
                                            });
                                            $docInfo = reset($docInfo);
                                            ?>
                                            
                                            <?php if ($docInfo): ?>
                                            <small class="text-muted">
                                                Uploaded: <?php echo date('d M Y', strtotime($docInfo['uploaded_at'])); ?> |
                                                Size: <?php echo round($docInfo['file_size'] / 1024, 1); ?> KB |
                                                <a href="<?php echo $docInfo['file_path']; ?>" target="_blank" class="text-primary">
                                                    <i class="fas fa-external-link-alt"></i> View
                                                </a>
                                            </small>
                                            <?php else: ?>
                                            <small class="text-danger">Document not uploaded</small>
                                            <?php endif; ?>
                                        </div>
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="openUploadModal('<?php echo $docType; ?>', '<?php echo $docName; ?>')">
                                            <i class="fas fa-<?php echo in_array($docType, $uploadedDocTypes) ? 'sync-alt' : 'upload'; ?>"></i>
                                            <?php echo in_array($docType, $uploadedDocTypes) ? 'Replace' : 'Upload'; ?>
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                
                                <?php if ($verificationComplete): ?>
                                <div class="alert alert-success mt-3">
                                    <i class="fas fa-check-circle"></i> 
                                    <strong>All documents uploaded!</strong> You can now submit for verification.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Verification History -->
                    <div class="col-lg-4">
                        <div class="verification-card card">
                            <div class="card-header bg-info text-white">
                                <h5><i class="fas fa-history"></i> Verification History</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($verificationHistory)): ?>
                                <div class="verification-timeline">
                                    <?php foreach ($verificationHistory as $history): ?>
                                    <div class="timeline-item <?php echo $history['status'] == 'approved' ? 'completed' : ''; ?>">
                                        <h6 class="mb-1">
                                            <span class="status-badge status-<?php echo $history['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $history['status'])); ?>
                                            </span>
                                        </h6>
                                        <small class="text-muted">
                                            <?php echo date('d M Y, h:i A', strtotime($history['submitted_at'])); ?>
                                        </small>
                                        <p class="mb-1 mt-2">
                                            <strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $history['verification_type'])); ?>
                                        </p>
                                        <?php if ($history['admin_remarks']): ?>
                                        <small class="text-muted">
                                            <strong>Remarks:</strong> <?php echo htmlspecialchars($history['admin_remarks']); ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-history fa-2x text-muted mb-2"></i>
                                    <p class="text-muted">No verification history</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Submit Verification Modal -->
    <div class="modal fade" id="submitVerificationModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-paper-plane"></i> 
                        <?php echo $verification && $verification['status'] == 'rejected' ? 'Resubmit' : 'Submit'; ?> 
                        for Verification
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="<?php echo $verification && $verification['status'] == 'rejected' ? 'resubmit_verification' : 'submit_verification'; ?>">
                        
                        <div class="mb-3">
                            <label for="verification_type" class="form-label">Verification Type *</label>
                            <select class="form-select" id="verification_type" name="verification_type" required>
                                <option value="">Select Verification Type</option>
                                <option value="new_registration">New Registration</option>
                                <option value="document_update">Document Update</option>
                                <option value="profile_verification">Profile Verification</option>
                                <option value="service_verification">Service Record Verification</option>
                                <option value="pension_verification">Pension Eligibility Verification</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="supporting_details" class="form-label">Supporting Details</label>
                            <textarea class="form-control" id="supporting_details" name="supporting_details" rows="4"
                                      placeholder="Provide any additional details or explanations for your verification request..."></textarea>
                        </div>
                        
                        <?php if ($verification && $verification['status'] == 'rejected'): ?>
                        <div class="mb-3">
                            <label for="resubmission_notes" class="form-label">Resubmission Notes</label>
                            <textarea class="form-control" id="resubmission_notes" name="resubmission_notes" rows="3"
                                      placeholder="Explain what changes you have made to address the rejection reasons..."></textarea>
                        </div>
                        <?php endif; ?>
                        
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> Important Notes:</h6>
                            <ul class="mb-0">
                                <li>Ensure all required documents are uploaded before submitting</li>
                                <li>Documents should be clear, legible, and in acceptable formats (PDF, JPG, PNG)</li>
                                <li>Verification process typically takes 3-5 working days</li>
                                <li>You will be notified of any status changes via email/SMS</li>
                            </ul>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="declaration" name="declaration" required>
                            <label class="form-check-label" for="declaration">
                                <strong>I declare that:</strong><br>
                                • All information provided is true and accurate<br>
                                • All uploaded documents are genuine and belong to me<br>
                                • I understand that providing false information may lead to account suspension<br>
                                • I consent to verification of my documents with relevant authorities
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Submit for Verification
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Upload Document Modal -->
    <div class="modal fade" id="uploadDocumentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-upload"></i> Upload Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="upload_document">
                        
                        <div class="mb-3">
                            <label for="document_type" class="form-label">Document Type *</label>
                            <select class="form-select" id="document_type" name="document_type" required>
                                <option value="">Select Document Type</option>
                                <?php foreach ($requiredDocuments as $docType => $docName): ?>
                                <option value="<?php echo $docType; ?>"><?php echo $docName; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="document" class="form-label">Choose File *</label>
                            <input type="file" class="form-control" id="document" name="document" required
                                   accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                            <div class="form-text">
                                Accepted formats: PDF, JPG, PNG, DOC, DOCX (Max size: 5MB)
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <strong>Important:</strong> Ensure the document is clear, legible, and contains all necessary information.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-upload"></i> Upload Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openUploadModal(docType, docName) {
            document.getElementById('document_type').value = docType;
            const modal = new bootstrap.Modal(document.getElementById('uploadDocumentModal'));
            modal.show();
        }

        // File size validation
        document.getElementById('document')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file && file.size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB');
                e.target.value = '';
            }
        });

        // Auto-hide success messages
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-success');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Progress animation
        window.addEventListener('load', function() {
            const progressBar = document.querySelector('.progress-bar');
            if (progressBar) {
                const width = progressBar.style.width;
                progressBar.style.width = '0%';
                setTimeout(() => {
                    progressBar.style.width = width;
                }, 500);
            }
        });
    </script>
</body>
</html>
