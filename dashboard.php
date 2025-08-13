<?php
require_once '../includes/auth_check.php';

// Allow access for multiple roles
if (!checkRole(['admin', 'teacher', 'student', 'finance', 'registrar', 'principal'])) {
    header('Location: ../index.php');
    exit();
}

$page_title = 'Dashboard - GTBA Portal';
$base_url = '../';

ob_start();
?>

<div class="welcome-section">
    <h1 class="welcome-title">Welcome to GTBA Portal</h1>
    <p class="welcome-subtitle">Golden Treasure Baptist Academy Management System</p>
    
    <div class="student-info">
        <h4>User Information</h4>
        <p><strong>Username:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?></p>
        <p><strong>Role:</strong> <?php echo htmlspecialchars($_SESSION['role_display']); ?></p>
        <p><strong>Access Level:</strong> <?php echo htmlspecialchars($_SESSION['role']); ?></p>
    </div>
</div>

<div class="dashboard-grid">
    <?php if ($_SESSION['role'] === 'student'): ?>
        <div class="dashboard-card">
            <div class="card-icon">📊</div>
            <h3 class="card-title">Student Portal</h3>
            <p class="card-description">Access your grades, schedule, and tuition information.</p>
            <a href="student/dashboard.php" class="card-link">Go to Student Portal</a>
        </div>
    <?php endif; ?>
    
    <?php if ($_SESSION['role'] === 'teacher'): ?>
        <div class="dashboard-card">
            <div class="card-icon">👨‍🏫</div>
            <h3 class="card-title">Teacher Portal</h3>
            <p class="card-description">Manage grades and view your assigned sections.</p>
            <a href="teacher/dashboard.php" class="card-link">Go to Teacher Portal</a>
        </div>
    <?php endif; ?>
    
    <?php if ($_SESSION['role'] === 'admin'): ?>
        <div class="dashboard-card">
            <div class="card-icon">⚙️</div>
            <h3 class="card-title">Admin Panel</h3>
            <p class="card-description">System administration and user management.</p>
            <a href="admin/dashboard.php" class="card-link">Go to Admin Panel</a>
        </div>
    <?php endif; ?>
    
    <?php if ($_SESSION['role'] === 'finance'): ?>
        <div class="dashboard-card">
            <div class="card-icon">💰</div>
            <h3 class="card-title">Finance Module</h3>
            <p class="card-description">Manage tuition fees and payment methods.</p>
            <a href="finance/dashboard.php" class="card-link">Go to Finance Module</a>
        </div>
    <?php endif; ?>
    
    <?php if ($_SESSION['role'] === 'registrar'): ?>
        <div class="dashboard-card">
            <div class="card-icon">📝</div>
            <h3 class="card-title">Registrar Module</h3>
            <p class="card-description">Student registration and enrollment management.</p>
            <a href="registrar/dashboard.php" class="card-link">Go to Registrar Module</a>
        </div>
    <?php endif; ?>
    
    <?php if ($_SESSION['role'] === 'principal'): ?>
        <div class="dashboard-card">
            <div class="card-icon">🏫</div>
            <h3 class="card-title">Principal Module</h3>
            <p class="card-description">School management and administrative oversight.</p>
            <a href="principal/dashboard.php" class="card-link">Go to Principal Module</a>
        </div>
    <?php endif; ?>
    
    <div class="dashboard-card">
        <div class="card-icon">📢</div>
        <h3 class="card-title">Announcements</h3>
        <p class="card-description">View the latest school announcements and updates.</p>
        <a href="announcements.php" class="card-link">View Announcements</a>
    </div>
    
    <div class="dashboard-card">
        <div class="card-icon">👤</div>
        <h3 class="card-title">Profile</h3>
        <p class="card-description">View and manage your account information.</p>
        <a href="profile.php" class="card-link">View Profile</a>
    </div>
</div>

<?php
$content = ob_get_clean();
include 'includes/header.php';
echo $content;
include 'includes/footer.php';
?>
