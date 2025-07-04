<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // Check if user exists and is active
    $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR service_number = ?) AND status = 'active'");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['service_number'] = $user['service_number'];
        $_SESSION['full_name'] = $user['full_name'];
        
        // Redirect based on role
        if ($user['role'] == 'admin') {
            header('Location: ../admin/dashboard.php');
        } else {
            header('Location: ../user/dashboard.php');
        }
        exit();
    } else {
        $_SESSION['error'] = 'Invalid username/password or account is inactive';
        header('Location: ../login.php');
        exit();
    }
} else {
    header('Location: ../login.php');
    exit();
}
?>
