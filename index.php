<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If user is already logged in, redirect to appropriate dashboard
if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
    switch ($_SESSION['role']) {
        case 'student':
            header('Location: student/dashboard.php');
            break;
        case 'teacher':
            header('Location: teacher/dashboard.php');
            break;
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
        case 'finance':
            header('Location: finance/dashboard.php');
            break;
        case 'registrar':
            header('Location: registrar/dashboard.php');
            break;
        case 'principal':
            header('Location: principal/dashboard.php');
            break;
        default:
            header('Location: dashboard.php');
            break;
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GTBA Portal - Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-logo">
                <img src="assets/images/school-logo.png" alt="GTBA Logo">
            </div>
            <h1 class="login-title">Golden Treasure Baptist Academy Portal</h1>
            <p class="login-subtitle">Please Log into your account</p>
            
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo $_SESSION['success']; 
                    unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>
            
            <form action="auth/login_process.php" method="POST" id="loginForm">
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           class="form-control" 
                           required 
                           autocomplete="username"
                           placeholder="Enter your username">
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-control" 
                           required 
                           autocomplete="current-password"
                           placeholder="Enter your password">
                </div>
                
                <button type="submit" class="btn btn-primary" id="loginBtn">
                    <span id="loginText">Sign In</span>
                    <span id="loginLoading" class="loading d-none"></span>
                </button>
            </form>
            
            <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-gray);">
                <p style="color: var(--gray); font-size: 0.9rem;">
                    For assistance, please contact the school office.
                </p>
            </div>
        </div>
    </div>
    
    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            const text = document.getElementById('loginText');
            const loading = document.getElementById('loginLoading');
            
            btn.disabled = true;
            text.classList.add('d-none');
            loading.classList.remove('d-none');
        });
        
        // Auto-focus username field
        document.getElementById('username').focus();
    </script>
</body>
</html>
