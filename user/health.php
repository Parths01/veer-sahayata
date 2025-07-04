<?php
session_start();
require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_medical_record':
                $result = addMedicalRecord($_POST);
                break;
            case 'update_health_profile':
                $result = updateHealthProfile($_POST);
                break;
            case 'add_emergency_contact':
                $result = addEmergencyContact($_POST);
                break;
            case 'book_appointment':
                $result = bookAppointment($_POST);
                break;
        }
    }
}

// Get user's health profile
$stmt = $pdo->prepare("SELECT * FROM health_profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$healthProfile = $stmt->fetch();

// Get user's medical records
$stmt = $pdo->prepare("
    SELECT * FROM medical_records 
    WHERE user_id = ? 
    ORDER BY record_date DESC, created_at DESC
");
$stmt->execute([$user_id]);
$medicalRecords = $stmt->fetchAll();

// Get emergency contacts
$stmt = $pdo->prepare("SELECT * FROM emergency_contacts WHERE user_id = ? ORDER BY priority ASC");
$stmt->execute([$user_id]);
$emergencyContacts = $stmt->fetchAll();

// Get upcoming appointments
$stmt = $pdo->prepare("
    SELECT a.*, h.hospital_name, h.address as hospital_address 
    FROM appointments a 
    LEFT JOIN hospitals h ON a.hospital_id = h.id 
    WHERE a.user_id = ? AND a.appointment_date >= CURDATE() 
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
$stmt->execute([$user_id]);
$upcomingAppointments = $stmt->fetchAll();

// Get nearby ECHS hospitals
$stmt = $pdo->prepare("
    SELECT * FROM hospitals 
    WHERE hospital_type IN ('ECHS', 'Military', 'Government') 
    ORDER BY hospital_name ASC 
    LIMIT 10
");
$stmt->execute();
$echsHospitals = $stmt->fetchAll();

// Functions
function addMedicalRecord($data) {
    global $pdo, $user_id;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO medical_records (user_id, record_type, diagnosis, doctor_name, hospital_name, 
                                       medications, record_date, notes, document_path) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $data['record_type'],
            $data['diagnosis'],
            $data['doctor_name'],
            $data['hospital_name'],
            $data['medications'],
            $data['record_date'],
            $data['notes'],
            $data['document_path'] ?? null
        ]);
        
        return ['success' => true, 'message' => 'Medical record added successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function updateHealthProfile($data) {
    global $pdo, $user_id;
    try {
        // Check if profile exists
        $stmt = $pdo->prepare("SELECT id FROM health_profiles WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            $stmt = $pdo->prepare("
                UPDATE health_profiles SET blood_group = ?, height = ?, weight = ?, 
                       chronic_conditions = ?, allergies = ?, medications = ?, 
                       emergency_conditions = ?, fitness_level = ?, updated_at = NOW()
                WHERE user_id = ?
            ");
            $stmt->execute([
                $data['blood_group'],
                $data['height'],
                $data['weight'],
                $data['chronic_conditions'],
                $data['allergies'],
                $data['medications'],
                $data['emergency_conditions'],
                $data['fitness_level'],
                $user_id
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO health_profiles (user_id, blood_group, height, weight, 
                                           chronic_conditions, allergies, medications, 
                                           emergency_conditions, fitness_level, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                $data['blood_group'],
                $data['height'],
                $data['weight'],
                $data['chronic_conditions'],
                $data['allergies'],
                $data['medications'],
                $data['emergency_conditions'],
                $data['fitness_level']
            ]);
        }
        
        return ['success' => true, 'message' => 'Health profile updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function addEmergencyContact($data) {
    global $pdo, $user_id;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO emergency_contacts (user_id, name, relationship, phone, address, priority) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $data['name'],
            $data['relationship'],
            $data['phone'],
            $data['address'],
            $data['priority']
        ]);
        
        return ['success' => true, 'message' => 'Emergency contact added successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function bookAppointment($data) {
    global $pdo, $user_id;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO appointments (user_id, hospital_id, appointment_type, doctor_specialty, 
                                    appointment_date, appointment_time, reason, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $user_id,
            $data['hospital_id'],
            $data['appointment_type'],
            $data['doctor_specialty'],
            $data['appointment_date'],
            $data['appointment_time'],
            $data['reason']
        ]);
        
        return ['success' => true, 'message' => 'Appointment request submitted successfully'];
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
    <title>Health Management - Veer Sahayata</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .health-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
            margin-bottom: 20px;
        }
        .health-card:hover {
            transform: translateY(-3px);
        }
        .health-stat {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin-bottom: 15px;
        }
        .blood-group {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
            font-weight: bold;
            font-size: 1.5em;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
        }
        .appointment-card {
            border-left: 4px solid #28a745;
            background: #f8f9fa;
        }
        .medical-record {
            border-left: 4px solid #007bff;
            background: #f8f9fa;
            margin-bottom: 10px;
            padding: 15px;
            border-radius: 5px;
        }
        .echs-hospital {
            border: 2px solid #28a745;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            background: #f8fff9;
        }
        .emergency-contact {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
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
                    <h1 class="h2"><i class="fas fa-heartbeat text-danger"></i> Health Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bookAppointmentModal">
                                <i class="fas fa-calendar-plus"></i> Book Appointment
                            </button>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMedicalRecordModal">
                                <i class="fas fa-plus"></i> Add Medical Record
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

                <!-- Health Overview Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="health-stat">
                            <div class="blood-group">
                                <?php echo $healthProfile['blood_group'] ?? 'N/A'; ?>
                            </div>
                            <h6>Blood Group</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="health-stat">
                            <h4><?php echo count($medicalRecords); ?></h4>
                            <h6>Medical Records</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="health-stat">
                            <h4><?php echo count($upcomingAppointments); ?></h4>
                            <h6>Upcoming Appointments</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="health-stat">
                            <h4><?php echo count($echsHospitals); ?></h4>
                            <h6>ECHS Centers</h6>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Health Profile Section -->
                    <div class="col-lg-6">
                        <div class="health-card card">
                            <div class="card-header bg-primary text-white">
                                <h5><i class="fas fa-user-md"></i> Health Profile</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($healthProfile): ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Height:</strong> <?php echo $healthProfile['height'] ?? 'Not set'; ?> cm</p>
                                        <p><strong>Weight:</strong> <?php echo $healthProfile['weight'] ?? 'Not set'; ?> kg</p>
                                        <p><strong>BMI:</strong> 
                                            <?php 
                                            if ($healthProfile['height'] && $healthProfile['weight']) {
                                                $bmi = $healthProfile['weight'] / (($healthProfile['height']/100) * ($healthProfile['height']/100));
                                                echo number_format($bmi, 1);
                                                if ($bmi < 18.5) echo ' <span class="badge bg-warning">Underweight</span>';
                                                elseif ($bmi < 25) echo ' <span class="badge bg-success">Normal</span>';
                                                elseif ($bmi < 30) echo ' <span class="badge bg-warning">Overweight</span>';
                                                else echo ' <span class="badge bg-danger">Obese</span>';
                                            } else {
                                                echo 'Not calculated';
                                            }
                                            ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Fitness Level:</strong> 
                                            <span class="badge bg-<?php 
                                                echo $healthProfile['fitness_level'] == 'Excellent' ? 'success' : 
                                                    ($healthProfile['fitness_level'] == 'Good' ? 'primary' : 
                                                        ($healthProfile['fitness_level'] == 'Average' ? 'warning' : 'danger')); 
                                            ?>">
                                                <?php echo $healthProfile['fitness_level'] ?? 'Not set'; ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                                
                                <?php if ($healthProfile['chronic_conditions']): ?>
                                <div class="alert alert-warning">
                                    <strong>Chronic Conditions:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($healthProfile['chronic_conditions'])); ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($healthProfile['allergies']): ?>
                                <div class="alert alert-danger">
                                    <strong>Allergies:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($healthProfile['allergies'])); ?>
                                </div>
                                <?php endif; ?>
                                
                                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#updateHealthProfileModal">
                                    <i class="fas fa-edit"></i> Update Profile
                                </button>
                                <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-user-md fa-3x text-muted mb-3"></i>
                                    <h6>No Health Profile Created</h6>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#updateHealthProfileModal">
                                        <i class="fas fa-plus"></i> Create Health Profile
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Emergency Contacts -->
                        <div class="health-card card">
                            <div class="card-header bg-danger text-white">
                                <h5><i class="fas fa-phone-alt"></i> Emergency Contacts</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($emergencyContacts)): ?>
                                    <?php foreach ($emergencyContacts as $contact): ?>
                                    <div class="emergency-contact">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($contact['name']); ?></h6>
                                                <p class="mb-1">
                                                    <span class="badge bg-secondary"><?php echo ucfirst($contact['relationship']); ?></span>
                                                    <strong class="ms-2"><?php echo htmlspecialchars($contact['phone']); ?></strong>
                                                </p>
                                                <?php if ($contact['address']): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($contact['address']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-warning">Priority <?php echo $contact['priority']; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-phone-slash fa-2x text-muted mb-2"></i>
                                    <p class="text-muted">No emergency contacts added</p>
                                </div>
                                <?php endif; ?>
                                <button class="btn btn-outline-danger btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#addEmergencyContactModal">
                                    <i class="fas fa-plus"></i> Add Emergency Contact
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Medical Records & Appointments -->
                    <div class="col-lg-6">
                        <!-- Upcoming Appointments -->
                        <div class="health-card card">
                            <div class="card-header bg-success text-white">
                                <h5><i class="fas fa-calendar-check"></i> Upcoming Appointments</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($upcomingAppointments)): ?>
                                    <?php foreach ($upcomingAppointments as $appointment): ?>
                                    <div class="appointment-card card p-3 mb-2">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($appointment['hospital_name'] ?? 'Hospital'); ?></h6>
                                                <p class="mb-1">
                                                    <strong><?php echo date('d M Y', strtotime($appointment['appointment_date'])); ?></strong>
                                                    at <strong><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></strong>
                                                </p>
                                                <small class="text-muted">
                                                    <?php echo ucfirst($appointment['appointment_type']); ?> - 
                                                    <?php echo htmlspecialchars($appointment['doctor_specialty']); ?>
                                                </small>
                                            </div>
                                            <span class="badge bg-<?php 
                                                echo $appointment['status'] == 'confirmed' ? 'success' : 
                                                    ($appointment['status'] == 'pending' ? 'warning' : 'danger'); 
                                            ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-calendar-times fa-2x text-muted mb-2"></i>
                                    <p class="text-muted">No upcoming appointments</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Recent Medical Records -->
                        <div class="health-card card">
                            <div class="card-header bg-info text-white">
                                <h5><i class="fas fa-file-medical"></i> Recent Medical Records</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($medicalRecords)): ?>
                                    <?php foreach (array_slice($medicalRecords, 0, 5) as $record): ?>
                                    <div class="medical-record">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($record['diagnosis']); ?></h6>
                                                <p class="mb-1">
                                                    <strong>Dr. <?php echo htmlspecialchars($record['doctor_name']); ?></strong>
                                                    at <?php echo htmlspecialchars($record['hospital_name']); ?>
                                                </p>
                                                <small class="text-muted">
                                                    <?php echo date('d M Y', strtotime($record['record_date'])); ?>
                                                </small>
                                            </div>
                                            <span class="badge bg-info"><?php echo ucfirst($record['record_type']); ?></span>
                                        </div>
                                        <?php if ($record['medications']): ?>
                                        <div class="mt-2">
                                            <small><strong>Medications:</strong> <?php echo htmlspecialchars($record['medications']); ?></small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                    
                                    <?php if (count($medicalRecords) > 5): ?>
                                    <button class="btn btn-outline-info btn-sm mt-2" onclick="toggleAllRecords()">
                                        <i class="fas fa-eye"></i> View All Records
                                    </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-file-medical fa-2x text-muted mb-2"></i>
                                    <p class="text-muted">No medical records added</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ECHS Hospitals Section -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="health-card card">
                            <div class="card-header bg-success text-white">
                                <h5><i class="fas fa-hospital"></i> ECHS & Military Hospitals</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($echsHospitals as $hospital): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="echs-hospital">
                                            <h6 class="text-success"><?php echo htmlspecialchars($hospital['hospital_name']); ?></h6>
                                            <p class="mb-1">
                                                <span class="badge bg-success"><?php echo $hospital['hospital_type']; ?></span>
                                            </p>
                                            <small class="text-muted">
                                                <i class="fas fa-map-marker-alt"></i> 
                                                <?php echo htmlspecialchars($hospital['address']); ?>
                                            </small>
                                            <?php if (isset($hospital['phone']) && $hospital['phone']): ?>
                                            <br><small>
                                                <i class="fas fa-phone"></i> 
                                                <?php echo htmlspecialchars($hospital['phone']); ?>
                                            </small>
                                            <?php endif; ?>
                                            <div class="mt-2">
                                                <button class="btn btn-sm btn-outline-success" 
                                                        onclick="bookAppointmentAtHospital(<?php echo $hospital['id']; ?>, '<?php echo addslashes($hospital['hospital_name']); ?>')">
                                                    <i class="fas fa-calendar-plus"></i> Book Appointment
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Update Health Profile Modal -->
    <div class="modal fade" id="updateHealthProfileModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-md"></i> Update Health Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_health_profile">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="blood_group" class="form-label">Blood Group *</label>
                                    <select class="form-select" id="blood_group" name="blood_group" required>
                                        <option value="">Select Blood Group</option>
                                        <option value="A+" <?php echo ($healthProfile['blood_group'] ?? '') == 'A+' ? 'selected' : ''; ?>>A+</option>
                                        <option value="A-" <?php echo ($healthProfile['blood_group'] ?? '') == 'A-' ? 'selected' : ''; ?>>A-</option>
                                        <option value="B+" <?php echo ($healthProfile['blood_group'] ?? '') == 'B+' ? 'selected' : ''; ?>>B+</option>
                                        <option value="B-" <?php echo ($healthProfile['blood_group'] ?? '') == 'B-' ? 'selected' : ''; ?>>B-</option>
                                        <option value="AB+" <?php echo ($healthProfile['blood_group'] ?? '') == 'AB+' ? 'selected' : ''; ?>>AB+</option>
                                        <option value="AB-" <?php echo ($healthProfile['blood_group'] ?? '') == 'AB-' ? 'selected' : ''; ?>>AB-</option>
                                        <option value="O+" <?php echo ($healthProfile['blood_group'] ?? '') == 'O+' ? 'selected' : ''; ?>>O+</option>
                                        <option value="O-" <?php echo ($healthProfile['blood_group'] ?? '') == 'O-' ? 'selected' : ''; ?>>O-</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="height" class="form-label">Height (cm)</label>
                                    <input type="number" class="form-control" id="height" name="height" 
                                           value="<?php echo $healthProfile['height'] ?? ''; ?>" min="100" max="250">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="weight" class="form-label">Weight (kg)</label>
                                    <input type="number" class="form-control" id="weight" name="weight" 
                                           value="<?php echo $healthProfile['weight'] ?? ''; ?>" min="30" max="200" step="0.1">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="fitness_level" class="form-label">Fitness Level</label>
                            <select class="form-select" id="fitness_level" name="fitness_level">
                                <option value="">Select Fitness Level</option>
                                <option value="Excellent" <?php echo ($healthProfile['fitness_level'] ?? '') == 'Excellent' ? 'selected' : ''; ?>>Excellent</option>
                                <option value="Good" <?php echo ($healthProfile['fitness_level'] ?? '') == 'Good' ? 'selected' : ''; ?>>Good</option>
                                <option value="Average" <?php echo ($healthProfile['fitness_level'] ?? '') == 'Average' ? 'selected' : ''; ?>>Average</option>
                                <option value="Poor" <?php echo ($healthProfile['fitness_level'] ?? '') == 'Poor' ? 'selected' : ''; ?>>Poor</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="chronic_conditions" class="form-label">Chronic Conditions</label>
                            <textarea class="form-control" id="chronic_conditions" name="chronic_conditions" rows="3" 
                                      placeholder="List any chronic medical conditions..."><?php echo $healthProfile['chronic_conditions'] ?? ''; ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="allergies" class="form-label">Allergies</label>
                            <textarea class="form-control" id="allergies" name="allergies" rows="2" 
                                      placeholder="List any known allergies..."><?php echo $healthProfile['allergies'] ?? ''; ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="medications" class="form-label">Current Medications</label>
                            <textarea class="form-control" id="medications" name="medications" rows="3" 
                                      placeholder="List current medications and dosages..."><?php echo $healthProfile['medications'] ?? ''; ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="emergency_conditions" class="form-label">Emergency Medical Conditions</label>
                            <textarea class="form-control" id="emergency_conditions" name="emergency_conditions" rows="2" 
                                      placeholder="Critical conditions that emergency responders should know..."><?php echo $healthProfile['emergency_conditions'] ?? ''; ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Health Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Medical Record Modal -->
    <div class="modal fade" id="addMedicalRecordModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-medical"></i> Add Medical Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_medical_record">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="record_type" class="form-label">Record Type *</label>
                                    <select class="form-select" id="record_type" name="record_type" required>
                                        <option value="">Select Type</option>
                                        <option value="consultation">Consultation</option>
                                        <option value="diagnosis">Diagnosis</option>
                                        <option value="treatment">Treatment</option>
                                        <option value="surgery">Surgery</option>
                                        <option value="lab_test">Lab Test</option>
                                        <option value="vaccination">Vaccination</option>
                                        <option value="checkup">Health Checkup</option>
                                        <option value="emergency">Emergency Visit</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="record_date" class="form-label">Date *</label>
                                    <input type="date" class="form-control" id="record_date" name="record_date" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="diagnosis" class="form-label">Diagnosis/Condition *</label>
                            <input type="text" class="form-control" id="diagnosis" name="diagnosis" required
                                   placeholder="Enter diagnosis or medical condition">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="doctor_name" class="form-label">Doctor Name</label>
                                    <input type="text" class="form-control" id="doctor_name" name="doctor_name"
                                           placeholder="Dr. Full Name">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="hospital_name" class="form-label">Hospital/Clinic</label>
                                    <input type="text" class="form-control" id="hospital_name" name="hospital_name"
                                           placeholder="Hospital or clinic name">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="medications" class="form-label">Prescribed Medications</label>
                            <textarea class="form-control" id="medications" name="medications" rows="3"
                                      placeholder="List medications with dosages..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Additional Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"
                                      placeholder="Any additional notes or instructions..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Emergency Contact Modal -->
    <div class="modal fade" id="addEmergencyContactModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-phone-alt"></i> Add Emergency Contact</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_emergency_contact">
                        
                        <div class="mb-3">
                            <label for="contact_name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="contact_name" name="name" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="relationship" class="form-label">Relationship *</label>
                                    <select class="form-select" id="relationship" name="relationship" required>
                                        <option value="">Select Relationship</option>
                                        <option value="spouse">Spouse</option>
                                        <option value="parent">Parent</option>
                                        <option value="child">Child</option>
                                        <option value="sibling">Sibling</option>
                                        <option value="friend">Friend</option>
                                        <option value="colleague">Colleague</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="priority" class="form-label">Priority *</label>
                                    <select class="form-select" id="priority" name="priority" required>
                                        <option value="1">1 (Primary)</option>
                                        <option value="2">2 (Secondary)</option>
                                        <option value="3">3 (Tertiary)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="contact_phone" class="form-label">Phone Number *</label>
                            <input type="tel" class="form-control" id="contact_phone" name="phone" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="contact_address" class="form-label">Address</label>
                            <textarea class="form-control" id="contact_address" name="address" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Add Emergency Contact</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Book Appointment Modal -->
    <div class="modal fade" id="bookAppointmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-calendar-plus"></i> Book Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="book_appointment">
                        
                        <div class="mb-3">
                            <label for="hospital_id" class="form-label">Select Hospital *</label>
                            <select class="form-select" id="hospital_id" name="hospital_id" required>
                                <option value="">Choose Hospital</option>
                                <?php foreach ($echsHospitals as $hospital): ?>
                                <option value="<?php echo $hospital['id']; ?>">
                                    <?php echo htmlspecialchars($hospital['hospital_name']); ?> 
                                    (<?php echo $hospital['hospital_type']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="appointment_type" class="form-label">Appointment Type *</label>
                                    <select class="form-select" id="appointment_type" name="appointment_type" required>
                                        <option value="">Select Type</option>
                                        <option value="consultation">Consultation</option>
                                        <option value="followup">Follow-up</option>
                                        <option value="checkup">Health Checkup</option>
                                        <option value="emergency">Emergency</option>
                                        <option value="specialist">Specialist</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="doctor_specialty" class="form-label">Doctor Specialty</label>
                                    <select class="form-select" id="doctor_specialty" name="doctor_specialty">
                                        <option value="">Any</option>
                                        <option value="General Medicine">General Medicine</option>
                                        <option value="Cardiology">Cardiology</option>
                                        <option value="Orthopedics">Orthopedics</option>
                                        <option value="Dermatology">Dermatology</option>
                                        <option value="ENT">ENT</option>
                                        <option value="Ophthalmology">Ophthalmology</option>
                                        <option value="Neurology">Neurology</option>
                                        <option value="Psychiatry">Psychiatry</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="appointment_date" class="form-label">Preferred Date *</label>
                                    <input type="date" class="form-control" id="appointment_date" name="appointment_date" 
                                           min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="appointment_time" class="form-label">Preferred Time *</label>
                                    <select class="form-select" id="appointment_time" name="appointment_time" required>
                                        <option value="">Select Time</option>
                                        <option value="09:00">09:00 AM</option>
                                        <option value="10:00">10:00 AM</option>
                                        <option value="11:00">11:00 AM</option>
                                        <option value="12:00">12:00 PM</option>
                                        <option value="14:00">02:00 PM</option>
                                        <option value="15:00">03:00 PM</option>
                                        <option value="16:00">04:00 PM</option>
                                        <option value="17:00">05:00 PM</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="reason" class="form-label">Reason for Appointment</label>
                            <textarea class="form-control" id="reason" name="reason" rows="3"
                                      placeholder="Briefly describe the reason for your visit..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Book Appointment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function bookAppointmentAtHospital(hospitalId, hospitalName) {
            document.getElementById('hospital_id').value = hospitalId;
            const modal = new bootstrap.Modal(document.getElementById('bookAppointmentModal'));
            modal.show();
        }

        function toggleAllRecords() {
            // This would expand to show all medical records
            alert('Feature to view all medical records will be implemented');
        }

        // Auto-hide success messages
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-success');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // BMI Calculator
        function calculateBMI() {
            const height = document.getElementById('height').value;
            const weight = document.getElementById('weight').value;
            
            if (height && weight) {
                const bmi = weight / ((height/100) * (height/100));
                console.log('BMI calculated:', bmi.toFixed(1));
            }
        }

        document.getElementById('height')?.addEventListener('input', calculateBMI);
        document.getElementById('weight')?.addEventListener('input', calculateBMI);
    </script>
</body>
</html>
