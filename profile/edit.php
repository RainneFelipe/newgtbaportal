<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../config/database.php';

// Set the page title and base URL based on user role
$page_title = "Edit Profile";
$base_url = '../';

try {
    $database = new Database();
    $db = $database->connect();
    
    $success_message = '';
    $error_message = '';
    
    // Get current user information including profile picture
    $query = "SELECT u.*, r.name as role_name, r.display_name as role_display 
              FROM users u 
              JOIN roles r ON u.role_id = r.id 
              WHERE u.id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get additional user information based on role
    $additional_info = [];
    if ($_SESSION['role'] === 'student') {
        $query = "SELECT s.student_id, s.lrn, s.first_name, s.last_name 
                  FROM students s WHERE s.user_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$_SESSION['user_id']]);
        $additional_info = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif ($_SESSION['role'] === 'teacher') {
        $query = "SELECT t.employee_id, t.first_name, t.last_name 
                  FROM teachers t WHERE t.user_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$_SESSION['user_id']]);
        $additional_info = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['update_profile'])) {
            try {
                $db->beginTransaction();
                
                // Handle profile picture upload
                if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    $max_size = 5 * 1024 * 1024; // 5MB
                    
                    if (in_array($_FILES['profile_picture']['type'], $allowed_types) && $_FILES['profile_picture']['size'] <= $max_size) {
                        $upload_dir = '../uploads/profiles/';
                        $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                        $new_filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
                        $upload_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                            // Delete old profile picture if exists
                            if ($user['profile_picture'] && file_exists('../' . $user['profile_picture'])) {
                                unlink('../' . $user['profile_picture']);
                            }
                            
                            // Update profile picture in database
                            $relative_path = 'uploads/profiles/' . $new_filename;
                            $query = "UPDATE users SET profile_picture = ? WHERE id = ?";
                            $stmt = $db->prepare($query);
                            $stmt->execute([$relative_path, $_SESSION['user_id']]);
                            
                            $user['profile_picture'] = $relative_path;
                        } else {
                            throw new Exception('Failed to upload profile picture');
                        }
                    } else {
                        throw new Exception('Invalid file type or size. Please upload JPG, PNG, or GIF under 5MB');
                    }
                }
                
                // Log the action (inside transaction)
                $log_query = "INSERT INTO audit_logs (user_id, action, table_name, record_id, details, ip_address, user_agent) 
                             VALUES (?, 'UPDATE_PROFILE', 'users', ?, ?, ?, ?)";
                $log_stmt = $db->prepare($log_query);
                $log_stmt->execute([
                    $_SESSION['user_id'],
                    $_SESSION['user_id'],
                    'Profile picture updated',
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
                
                $db->commit();
                $success_message = "Profile updated successfully!";
                
            } catch (Exception $e) {
                $db->rollBack();
                $error_message = $e->getMessage();
            }
        }
        
        // Handle password change
        if (isset($_POST['change_password'])) {
            try {
                $db->beginTransaction();
                
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                // Verify current password
                if (!password_verify($current_password, $user['password'])) {
                    throw new Exception('Current password is incorrect');
                }
                
                // Validate new password
                if (strlen($new_password) < 6) {
                    throw new Exception('New password must be at least 6 characters long');
                }
                
                if ($new_password !== $confirm_password) {
                    throw new Exception('New password and confirmation do not match');
                }
                
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $query = "UPDATE users SET password = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                
                // Log the action (inside transaction)
                $log_query = "INSERT INTO audit_logs (user_id, action, table_name, record_id, details, ip_address, user_agent) 
                             VALUES (?, 'CHANGE_PASSWORD', 'users', ?, ?, ?, ?)";
                $log_stmt = $db->prepare($log_query);
                $log_stmt->execute([
                    $_SESSION['user_id'],
                    $_SESSION['user_id'],
                    'Password changed',
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
                
                $db->commit();
                $success_message = "Password changed successfully!";
                
            } catch (Exception $e) {
                $db->rollBack();
                $error_message = $e->getMessage();
            }
        }
    }
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - GTBA Portal</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="<?php echo $base_url; ?>assets/images/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header-modern">
            <div class="page-header-content">
                <div class="page-title-section">
                    <div class="page-title-wrapper">
                        <h1 class="page-title">
                            <i class="fas fa-user-edit page-icon"></i>
                            Edit Profile
                        </h1>
                    </div>
                    <nav class="page-breadcrumb">
                        <span class="breadcrumb-item">Profile</span>
                        <i class="fas fa-chevron-right breadcrumb-separator"></i>
                        <span class="breadcrumb-item current">Edit Profile</span>
                    </nav>
                    <p class="page-description">
                        Update your profile information and change your password
                    </p>
                </div>
            </div>
        </div>

        <div class="content-wrapper">
            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="profile-container">
                <!-- Profile Information Card -->
                <div class="profile-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-id-card"></i>
                            Profile Information
                        </h2>
                    </div>
                    
                    <div class="card-body">
                        <!-- Profile Picture Section -->
                        <div class="profile-picture-section">
                            <div class="current-picture">
                                <?php if ($user['profile_picture'] && file_exists('../' . $user['profile_picture'])): ?>
                                    <img src="<?php echo $base_url . htmlspecialchars($user['profile_picture']); ?>" 
                                         alt="Profile Picture" class="profile-img">
                                <?php else: ?>
                                    <div class="default-avatar">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <form method="POST" enctype="multipart/form-data" class="profile-picture-form">
                                <div class="upload-section">
                                    <label for="profile_picture" class="upload-label">
                                        <i class="fas fa-camera"></i>
                                        Choose New Picture
                                    </label>
                                    <input type="file" id="profile_picture" name="profile_picture" 
                                           accept="image/jpeg,image/png,image/gif" class="file-input">
                                    <p class="upload-hint">JPG, PNG, or GIF. Max 5MB.</p>
                                </div>
                                <button type="submit" name="update_profile" class="btn btn-primary btn-sm">
                                    <i class="fas fa-upload"></i>
                                    Update Picture
                                </button>
                            </form>
                        </div>

                        <!-- User Information Display -->
                        <div class="user-info-grid">
                            <div class="info-group">
                                <label class="info-label">Display Name</label>
                                <div class="info-display">
                                    <?php 
                                    if (isset($additional_info['first_name']) && isset($additional_info['last_name'])) {
                                        echo htmlspecialchars($additional_info['first_name'] . ' ' . $additional_info['last_name']);
                                    } else {
                                        echo htmlspecialchars($user['username']);
                                    }
                                    ?>
                                </div>
                            </div>

                            <div class="info-group">
                                <label class="info-label">Username</label>
                                <div class="info-display">
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </div>
                            </div>

                            <div class="info-group">
                                <label class="info-label">User ID</label>
                                <div class="info-display">
                                    <?php echo htmlspecialchars($user['id']); ?>
                                </div>
                            </div>

                            <?php if (isset($additional_info['student_id'])): ?>
                                <div class="info-group">
                                    <label class="info-label">Student ID</label>
                                    <div class="info-display">
                                        <?php echo htmlspecialchars($additional_info['student_id']); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($additional_info['lrn'])): ?>
                                <div class="info-group">
                                    <label class="info-label">LRN</label>
                                    <div class="info-display">
                                        <?php echo htmlspecialchars($additional_info['lrn']); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($additional_info['employee_id'])): ?>
                                <div class="info-group">
                                    <label class="info-label">Employee ID</label>
                                    <div class="info-display">
                                        <?php echo htmlspecialchars($additional_info['employee_id']); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="info-group">
                                <label class="info-label">Email</label>
                                <div class="info-display">
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </div>
                            </div>

                            <div class="info-group">
                                <label class="info-label">Role</label>
                                <div class="info-display">
                                    <?php echo htmlspecialchars($user['role_display']); ?>
                                </div>
                            </div>

                            <div class="info-group">
                                <label class="info-label">Member Since</label>
                                <div class="info-display">
                                    <?php echo date('F j, Y', strtotime($user['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Change Password Card -->
                <div class="profile-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-lock"></i>
                            Change Password
                        </h2>
                    </div>
                    
                    <div class="card-body">
                        <form method="POST" class="password-form" autocomplete="off" id="change-password-form">
                            <!-- Hidden username field to help browser identify which account this is for -->
                            <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" autocomplete="username" style="position: absolute; left: -9999px; opacity: 0;" readonly>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <div class="password-input-wrapper">
                                        <input type="password" id="current_password" name="current_password" 
                                               class="form-control" required autocomplete="current-password"
                                               data-username="<?php echo htmlspecialchars($user['username']); ?>">
                                        <button type="button" class="password-toggle" onclick="togglePassword('current_password')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <div class="password-input-wrapper">
                                        <input type="password" id="new_password" name="new_password" 
                                               class="form-control" required minlength="6" autocomplete="new-password">
                                        <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-hint">Must be at least 6 characters long</div>
                                </div>

                                <div class="form-group">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <div class="password-input-wrapper">
                                        <input type="password" id="confirm_password" name="confirm_password" 
                                               class="form-control" required minlength="6" autocomplete="new-password">
                                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" name="change_password" class="btn btn-primary">
                                    <i class="fas fa-key"></i>
                                    Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>

    <script>
        // Prevent browser password manager from showing account selection
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('change-password-form');
            const currentPasswordField = document.getElementById('current_password');
            
            // Add event listener to prevent browser from showing password suggestions
            if (currentPasswordField) {
                currentPasswordField.addEventListener('focus', function() {
                    this.setAttribute('autocomplete', 'off');
                    this.setAttribute('data-form-type', 'password-change');
                });
            }
        });

        // Password visibility toggle
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const toggle = input.parentElement.querySelector('.password-toggle i');
            
            if (input.type === 'password') {
                input.type = 'text';
                toggle.classList.remove('fa-eye');
                toggle.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                toggle.classList.remove('fa-eye-slash');
                toggle.classList.add('fa-eye');
            }
        }

        // File upload preview
        document.getElementById('profile_picture').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const currentPicture = document.querySelector('.current-picture');
                    currentPicture.innerHTML = '<img src="' + e.target.result + '" alt="Profile Preview" class="profile-img">';
                };
                reader.readAsDataURL(file);
            }
        });

        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 300);
            });
        }, 5000);
    </script>

    <style>
        /* Profile Page Styles */
        .page-header-modern {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
            color: var(--white);
            padding: 2.5rem 0;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .page-header-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }

        .page-header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            position: relative;
            z-index: 1;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
        }

        .page-icon {
            margin-right: 1rem;
            color: var(--light-blue);
        }

        .page-breadcrumb {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            opacity: 0.9;
        }

        .breadcrumb-separator {
            margin: 0 0.5rem;
            font-size: 0.8rem;
        }

        .breadcrumb-item.current {
            color: var(--light-blue);
        }

        .page-description {
            font-size: 1.1rem;
            opacity: 0.95;
            margin: 0;
        }

        .profile-container {
            max-width: 800px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .profile-card {
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(46, 134, 171, 0.1);
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, var(--light-blue) 0%, #f0f8ff 100%);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid rgba(46, 134, 171, 0.1);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-blue);
            margin: 0;
            display: flex;
            align-items: center;
        }

        .card-title i {
            margin-right: 0.75rem;
            color: var(--primary-blue);
        }

        .card-body {
            padding: 2rem;
        }

        .profile-picture-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid #e9ecef;
        }

        .current-picture {
            margin-bottom: 1.5rem;
        }

        .profile-img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary-blue);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .default-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 3rem;
            border: 4px solid var(--primary-blue);
        }

        .upload-section {
            text-align: center;
            margin-bottom: 1rem;
        }

        .upload-label {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: var(--primary-blue);
            color: var(--white);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .upload-label:hover {
            background: var(--dark-blue);
            transform: translateY(-2px);
        }

        .file-input {
            display: none;
        }

        .upload-hint {
            font-size: 0.875rem;
            color: #6c757d;
            margin: 0.5rem 0 0 0;
        }

        .user-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .info-group {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid var(--primary-blue);
        }

        .info-label {
            font-weight: 600;
            color: var(--dark-blue);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
            display: block;
        }

        .info-display {
            font-size: 1rem;
            color: #333;
            font-weight: 500;
        }

        .password-form {
            max-width: 500px;
        }

        .form-grid {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark-blue);
            margin-bottom: 0.5rem;
        }

        .password-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .form-control {
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            flex: 1;
            padding-right: 3rem;
        }

        .form-control:focus {
            border-color: var(--primary-blue);
            outline: none;
            box-shadow: 0 0 0 3px rgba(135, 206, 235, 0.1);
        }

        .password-toggle {
            position: absolute;
            right: 0.75rem;
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 0.25rem;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: var(--primary-blue);
        }

        .form-hint {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        .form-actions {
            display: flex;
            justify-content: flex-start;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary-blue);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--dark-blue);
            transform: translateY(-2px);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.3s ease;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .page-header-content {
                padding: 0 1rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .profile-container {
                margin: 0 1rem;
            }

            .card-body {
                padding: 1.5rem;
            }

            .user-info-grid {
                grid-template-columns: 1fr;
            }

            .profile-picture-section {
                align-items: center;
            }
        }
    </style>
</body>
</html>
