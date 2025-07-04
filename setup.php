<?php
/**
 * Veer Sahayata - Installation Verification & Setup
 * This script helps verify that the database is properly set up
 * and provides quick access to login information.
 */

require_once 'config/database.php';

echo "<!DOCTYPE html>";
echo "<html lang='en'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Veer Sahayata - Setup Verification</title>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>";
echo "</head>";
echo "<body class='bg-light'>";
echo "<div class='container mt-5'>";
echo "<div class='row justify-content-center'>";
echo "<div class='col-md-8'>";

echo "<div class='card shadow'>";
echo "<div class='card-header bg-primary text-white'>";
echo "<h2 class='mb-0'><i class='fas fa-cog'></i> Veer Sahayata - Setup Verification</h2>";
echo "</div>";
echo "<div class='card-body'>";

try {
    echo "<div class='alert alert-success'>";
    echo "<i class='fas fa-check-circle'></i> <strong>Database Connection Successful!</strong>";
    echo "</div>";
    
    // Check if admin user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch();
    
    if (!$admin) {
        echo "<div class='alert alert-warning'>";
        echo "<i class='fas fa-exclamation-triangle'></i> <strong>Admin user not found!</strong>";
        echo "<p>Please import the complete database schema first:</p>";
        echo "<code>database/veer_sahayata_complete.sql</code>";
        echo "</div>";
    } else {
        echo "<div class='alert alert-success'>";
        echo "<i class='fas fa-user-shield'></i> <strong>Admin user verified!</strong>";
        echo "</div>";
    }
    
    // Database statistics
    echo "<h4 class='mt-4'>Database Statistics</h4>";
    echo "<div class='row'>";
    
    $tables = ['users', 'news', 'colleges', 'hospitals', 'schemes', 'settings'];
    foreach ($tables as $table) {
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "<div class='col-md-4 mb-3'>";
            echo "<div class='card text-center'>";
            echo "<div class='card-body'>";
            echo "<h5 class='card-title text-capitalize'>$table</h5>";
            echo "<p class='card-text display-6'>$count</p>";
            echo "</div>";
            echo "</div>";
            echo "</div>";
        } catch (PDOException $e) {
            echo "<div class='col-md-4 mb-3'>";
            echo "<div class='card text-center border-danger'>";
            echo "<div class='card-body'>";
            echo "<h5 class='card-title text-capitalize text-danger'>$table</h5>";
            echo "<p class='card-text text-danger'>Missing</p>";
            echo "</div>";
            echo "</div>";
            echo "</div>";
        }
    }
    echo "</div>";
    
    // Login Information
    echo "<h4 class='mt-4'>Login Information</h4>";
    echo "<div class='row'>";
    echo "<div class='col-md-6'>";
    echo "<div class='card border-primary'>";
    echo "<div class='card-header bg-primary text-white'>";
    echo "<strong>Admin Access</strong>";
    echo "</div>";
    echo "<div class='card-body'>";
    echo "<p><strong>URL:</strong> <a href='admin/' target='_blank'>admin/</a></p>";
    echo "<p><strong>Username:</strong> <code>admin</code></p>";
    echo "<p><strong>Password:</strong> <code>password</code></p>";
    echo "<div class='alert alert-warning alert-sm'>";
    echo "<small><i class='fas fa-exclamation-triangle'></i> Change password immediately after first login!</small>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    
    echo "<div class='col-md-6'>";
    echo "<div class='card border-info'>";
    echo "<div class='card-header bg-info text-white'>";
    echo "<strong>User Access</strong>";
    echo "</div>";
    echo "<div class='card-body'>";
    echo "<p><strong>URL:</strong> <a href='user/' target='_blank'>user/</a></p>";
    echo "<p><strong>Registration:</strong> <a href='register.php' target='_blank'>Create Account</a></p>";
    echo "<p>New users can register and will need admin approval for verification.</p>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    
    // Quick Actions
    echo "<h4 class='mt-4'>Quick Actions</h4>";
    echo "<div class='d-grid gap-2 d-md-flex justify-content-md-start'>";
    echo "<a href='login.php' class='btn btn-primary'><i class='fas fa-sign-in-alt'></i> Go to Login</a>";
    echo "<a href='admin/' class='btn btn-secondary'><i class='fas fa-tachometer-alt'></i> Admin Panel</a>";
    echo "<a href='user/' class='btn btn-info'><i class='fas fa-user'></i> User Panel</a>";
    echo "<a href='/' class='btn btn-outline-primary'><i class='fas fa-home'></i> Home Page</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>";
    echo "<i class='fas fa-times-circle'></i> <strong>Database Error:</strong> " . htmlspecialchars($e->getMessage());
    echo "<hr>";
    echo "<p><strong>Common Solutions:</strong></p>";
    echo "<ul>";
    echo "<li>Ensure XAMPP/MySQL is running</li>";
    echo "<li>Check database configuration in <code>config/database.php</code></li>";
    echo "<li>Import the database schema: <code>database/veer_sahayata_complete.sql</code></li>";
    echo "</ul>";
    echo "</div>";
}

echo "</div>"; // card-body
echo "</div>"; // card

// Installation Instructions
echo "<div class='card mt-4'>";
echo "<div class='card-header bg-secondary text-white'>";
echo "<h5 class='mb-0'><i class='fas fa-book'></i> Installation Instructions</h5>";
echo "</div>";
echo "<div class='card-body'>";
echo "<h6>If you haven't imported the database yet:</h6>";
echo "<ol>";
echo "<li>Open phpMyAdmin: <a href='http://localhost/phpmyadmin' target='_blank'>http://localhost/phpmyadmin</a></li>";
echo "<li>Create a database named <code>veer_sahayata</code></li>";
echo "<li>Import the file: <code>database/veer_sahayata_complete.sql</code></li>";
echo "<li>Refresh this page to verify the installation</li>";
echo "</ol>";

echo "<h6 class='mt-3'>File Permissions:</h6>";
echo "<p>Ensure these directories are writable:</p>";
echo "<ul>";
echo "<li><code>uploads/</code> - For user file uploads</li>";
echo "<li><code>backups/</code> - For database backups</li>";
echo "<li><code>logs/</code> - For application logs</li>";
echo "</ul>";
echo "</div>";
echo "</div>";

echo "</div>"; // col
echo "</div>"; // row
echo "</div>"; // container

// Font Awesome for icons
echo "<script src='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js'></script>";
echo "</body>";
echo "</html>";
?>
