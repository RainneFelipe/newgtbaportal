<?php
// Get user profile picture if user is logged in
$user_profile_picture = null;
if (isset($_SESSION['user_id'])) {
    try {
        require_once (isset($base_url) ? $base_url : '../') . 'config/database.php';
        $database = new Database();
        $db = $database->connect();
        
        $query = "SELECT profile_picture FROM users WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && !empty($result['profile_picture'])) {
            $user_profile_picture = $result['profile_picture'];
        }
    } catch (Exception $e) {
        // Silently fail - will use placeholder
        $user_profile_picture = null;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'GTBA Portal'; ?></title>
    <link rel="stylesheet" href="<?php echo $base_url ?? '../'; ?>assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="<?php echo $base_url ?? '../'; ?>assets/images/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Sidebar -->
    <?php if (isset($_SESSION['role'])): ?>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <img src="<?php echo $base_url ?? '../'; ?>assets/images/school-logo.png" alt="GTBA Logo" class="sidebar-logo-img">
                <div class="sidebar-logo-text">
                    <span class="logo-main">GTBA</span>
                    <span class="logo-sub">Portal</span>
                </div>
            </div>
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="sidebar-user">
            <div class="user-avatar">
                <?php if ($user_profile_picture && file_exists((isset($base_url) ? $base_url : '../') . $user_profile_picture)): ?>
                    <img src="<?php echo $base_url ?? '../'; ?><?php echo htmlspecialchars($user_profile_picture); ?>" alt="Profile Picture" class="user-avatar-img">
                <?php else: ?>
                    <i class="fas fa-user"></i>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <span class="user-role"><?php echo htmlspecialchars($_SESSION['role_display']); ?></span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <ul class="sidebar-menu">
                <?php if ($_SESSION['role'] === 'student'): ?>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>student/dashboard.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
                            <i class="fas fa-home"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>student/grades.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'grades.php') ? 'active' : ''; ?>">
                            <i class="fas fa-chart-line"></i>
                            <span>My Grades</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>student/schedule.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'schedule.php') ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Schedule</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>student/tuition.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'tuition.php') ? 'active' : ''; ?>">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Tuition & Fees</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>student/section.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'section.php') ? 'active' : ''; ?>">
                            <i class="fas fa-users"></i>
                            <span>My Section</span>
                        </a>
                    </li>

                <?php elseif ($_SESSION['role'] === 'teacher'): ?>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>teacher/dashboard.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
                            <i class="fas fa-home"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>teacher/sections.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'sections.php') ? 'active' : ''; ?>">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <span>My Classes</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>teacher/grades.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'grades.php') ? 'active' : ''; ?>">
                            <i class="fas fa-clipboard-check"></i>
                            <span>Manage Grades</span>
                        </a>
                    </li>

                <?php elseif ($_SESSION['role'] === 'admin'): ?>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>admin/dashboard.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="menu-section">
                        <span class="section-title">User Management</span>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>admin/users.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'users.php') ? 'active' : ''; ?>">
                            <i class="fas fa-users-cog"></i>
                            <span>Manage Users</span>
                        </a>
                    </li>
                    <li class="menu-section">
                        <span class="section-title">Academic Management</span>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>admin/school_years.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'school_years.php') ? 'active' : ''; ?>">
                            <i class="fas fa-calendar"></i>
                            <span>School Years</span>
                        </a>
                    </li>
                    <li class="menu-section">
                        <span class="section-title">System</span>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>admin/audit_logs.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'audit_logs.php') ? 'active' : ''; ?>">
                            <i class="fas fa-history"></i>
                            <span>Audit Logs</span>
                        </a>
                    </li>

                <?php elseif ($_SESSION['role'] === 'finance'): ?>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>finance/dashboard.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
                            <i class="fas fa-home"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="menu-section">
                        <span class="section-title">Financial Management</span>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>finance/tuition.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'tuition.php') ? 'active' : ''; ?>">
                            <i class="fas fa-money-bill"></i>
                            <span>Tuition Management</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>finance/payment_terms.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'payment_terms.php') ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Payment Terms</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>finance/payments.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'payments.php') ? 'active' : ''; ?>">
                            <i class="fas fa-credit-card"></i>
                            <span>Payment Methods</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>finance/reports.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'reports.php') ? 'active' : ''; ?>">
                            <i class="fas fa-chart-line"></i>
                            <span>Financial Reports</span>
                        </a>
                    </li>

                <?php elseif ($_SESSION['role'] === 'registrar'): ?>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>registrar/dashboard.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
                            <i class="fas fa-home"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>registrar/student_registration.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'student_registration.php') ? 'active' : ''; ?>">
                            <i class="fas fa-user-plus"></i>
                            <span>Student Registration</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>registrar/student_records.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'student_records.php') ? 'active' : ''; ?>">
                            <i class="fas fa-users"></i>
                            <span>Student Records</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>registrar/student_promotion.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'student_promotion.php') ? 'active' : ''; ?>">
                            <i class="fas fa-graduation-cap"></i>
                            <span>Student Promotion</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>registrar/enrollment_reports.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'enrollment_reports.php') ? 'active' : ''; ?>">
                            <i class="fas fa-chart-line"></i>
                            <span>Enrollment Reports</span>
                        </a>
                    </li>

                <?php elseif ($_SESSION['role'] === 'principal'): ?>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>principal/dashboard.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
                            <i class="fas fa-home"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>principal/sections.php" class="menu-link">
                            <i class="fas fa-layer-group"></i>
                            <span>Section Management</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>principal/schedules.php" class="menu-link">
                            <i class="fas fa-calendar-week"></i>
                            <span>Class Schedules</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>principal/subject_curriculum.php" class="menu-link">
                            <i class="fas fa-book-open"></i>
                            <span>Curriculum & Subjects</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>principal/announcements.php" class="menu-link">
                            <i class="fas fa-bullhorn"></i>
                            <span>Announcements</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>

        <div class="sidebar-footer">
            <a href="<?php echo $base_url ?? '../'; ?>profile/edit.php" class="profile-btn">
                <i class="fas fa-user-edit"></i>
                <span>Edit Profile</span>
            </a>
            <a href="<?php echo $base_url ?? '../'; ?>auth/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <?php endif; ?>

    <!-- Main Header -->
    <header class="header">
        <div class="header-content">
            <?php if (isset($_SESSION['role'])): ?>
            <button class="sidebar-trigger" id="sidebarTrigger">
                <i class="fas fa-bars"></i>
            </button>
            <?php endif; ?>
            
            <div class="header-logo">
                <img src="<?php echo $base_url ?? '../'; ?>assets/images/school-logo.png" alt="GTBA Logo" class="logo-img">
                <div class="logo-text">
                    GTBA Portal
                    <span class="logo-subtitle">Golden Treasure Baptist Academy</span>
                </div>
            </div>

            <div class="header-actions">
                <?php if (isset($_SESSION['role'])): ?>
                <div class="user-profile">
                    <span class="welcome-text">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Main Content Wrapper -->
    <div class="main-wrapper" id="mainWrapper">
        <main class="main-content">
            <div class="container">
