<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../classes/User.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $_SESSION['error'] = 'Please enter both username and password.';
        header('Location: ../index.php');
        exit();
    }
    
    try {
        $database = new Database();
        $db = $database->connect();
        $user = new User($db);
        
        $login_result = $user->login($username, $password);
        
        if ($login_result) {
            // Set session variables
            $_SESSION['user_id'] = $login_result['id'];
            $_SESSION['username'] = $login_result['username'];
            $_SESSION['role'] = $login_result['role_name'];
            $_SESSION['role_display'] = $login_result['display_name'];
            $_SESSION['permissions'] = json_decode($login_result['permissions'], true);
            $_SESSION['is_logged_in'] = true;
            $_SESSION['privacy_policy_accepted'] = $login_result['privacy_policy_accepted'];
            
            // Redirect based on role
            switch ($login_result['role_name']) {
                case 'student':
                    header('Location: ../student/dashboard.php');
                    break;
                case 'teacher':
                    header('Location: ../teacher/dashboard.php');
                    break;
                case 'admin':
                    header('Location: ../admin/dashboard.php');
                    break;
                case 'finance':
                    header('Location: ../finance/dashboard.php');
                    break;
                case 'registrar':
                    header('Location: ../registrar/dashboard.php');
                    break;
                case 'principal':
                    header('Location: ../principal/dashboard.php');
                    break;
                default:
                    header('Location: ../dashboard.php');
                    break;
            }
            exit();
            
        } else {
            $_SESSION['error'] = 'Invalid username or password.';
            header('Location: ../index.php');
            exit();
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Login failed. Please try again.';
        error_log("Login error: " . $e->getMessage());
        header('Location: ../index.php');
        exit();
    }
} else {
    header('Location: ../index.php');
    exit();
}
?>
