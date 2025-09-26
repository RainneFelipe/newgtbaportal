<?php
// Get user profile picture if user is logged in
$user_profile_picture = null;
$payment_reminders = [];
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

        // Check for payment reminders if user is a student
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
            // Get student ID and current grade level
            $student_query = "SELECT s.id, s.current_grade_level_id, gl.grade_name 
                             FROM students s
                             LEFT JOIN grade_levels gl ON s.current_grade_level_id = gl.id
                             WHERE s.user_id = ?";
            $student_stmt = $db->prepare($student_query);
            $student_stmt->execute([$_SESSION['user_id']]);
            $student_info = $student_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($student_info && $student_info['current_grade_level_id']) {
                $student_id = $student_info['id'];
                $current_grade_id = $student_info['current_grade_level_id'];
                
                // Get student's preferred payment terms
                $prefs_query = "SELECT spp.payment_term_id, pt.*, sy.year_label 
                               FROM student_payment_preferences spp
                               JOIN payment_terms pt ON spp.payment_term_id = pt.id
                               JOIN school_years sy ON pt.school_year_id = sy.id
                               WHERE spp.student_id = ? 
                               AND sy.is_active = 1 
                               AND pt.is_active = 1
                               AND pt.grade_level_id = ?";
                $prefs_stmt = $db->prepare($prefs_query);
                $prefs_stmt->execute([$student_id, $current_grade_id]);
                $preferred_terms = $prefs_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // If no preferences set, get all available terms for this grade (fallback)
                if (empty($preferred_terms)) {
                    $terms_query = "SELECT pt.*, sy.year_label 
                                   FROM payment_terms pt
                                   JOIN school_years sy ON pt.school_year_id = sy.id
                                   WHERE sy.is_active = 1 
                                   AND pt.is_active = 1 
                                   AND pt.grade_level_id = ?";
                    $terms_stmt = $db->prepare($terms_query);
                    $terms_stmt->execute([$current_grade_id]);
                    $fallback_terms = $terms_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Add a reminder to set preferences if none are set
                    if (!empty($fallback_terms)) {
                        $payment_reminders[] = [
                            'type' => 'preferences',
                            'message' => "Please set your payment preferences to receive targeted reminders",
                            'term_id' => 0,
                            'term_name' => 'Preferences',
                            'action_url' => (isset($base_url) ? $base_url : '../') . 'student/payment_preferences.php'
                        ];
                        
                        // Also process payment reminders for fallback terms
                        $preferred_terms = $fallback_terms;
                    }
                }
                
                // Process reminders for preferred terms (or fallback terms)
                if (!empty($preferred_terms)) {
                    foreach ($preferred_terms as $term) {
                        // Check if student has submitted payments for this term
                        $payment_check = "SELECT payment_type, verification_status, installment_number, payment_date
                                         FROM student_payments 
                                         WHERE student_id = ? AND payment_term_id = ?";
                        $payment_stmt = $db->prepare($payment_check);
                        $payment_stmt->execute([$student_id, $term['id']]);
                        $existing_payments = $payment_stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        $has_full_payment = false;
                        $has_down_payment = false;
                        $submitted_installments = 0;
                        $paid_installments = [];
                        
                        foreach ($existing_payments as $payment) {
                            if ($payment['verification_status'] !== 'rejected') {
                                if ($payment['payment_type'] === 'full_payment') {
                                    $has_full_payment = true;
                                } elseif ($payment['payment_type'] === 'down_payment') {
                                    $has_down_payment = true;
                                } elseif ($payment['payment_type'] === 'monthly_installment') {
                                    $submitted_installments++;
                                    if ($payment['installment_number']) {
                                        $paid_installments[] = (int)$payment['installment_number'];
                                    }
                                }
                            }
                        }
                        
                        // Generate reminders based on payment term type
                        if ($term['term_type'] === 'full_payment' && !$has_full_payment) {
                            $payment_reminders[] = [
                                'type' => 'full_payment',
                                'message' => "Please submit your full payment for {$term['term_name']} ({$term['year_label']})",
                                'term_id' => $term['id'],
                                'term_name' => $term['term_name']
                            ];
                        } elseif ($term['term_type'] === 'installment') {
                            // If student has made installment payments, consider down payment as effectively made
                            $has_effective_down_payment = $has_down_payment || $submitted_installments > 0;
                            
                            if (!$has_effective_down_payment && !$has_full_payment) {
                                $payment_reminders[] = [
                                    'type' => 'down_payment',
                                    'message' => "Please submit your down payment for {$term['term_name']} ({$term['year_label']})",
                                    'term_id' => $term['id'],
                                    'term_name' => $term['term_name']
                                ];
                            } elseif ($has_effective_down_payment && !$has_full_payment) {
                                $remaining_installments = $term['number_of_installments'] - $submitted_installments;
                                
                                // Only show reminders if there are still installments remaining
                                if ($remaining_installments > 0) {
                                    // Determine which installment should be due based on months since down payment
                                    $down_payment_query = "SELECT MIN(payment_date) as down_payment_date 
                                                          FROM student_payments 
                                                          WHERE student_id = ? 
                                                          AND payment_term_id = ? 
                                                          AND payment_type = 'down_payment'
                                                          AND verification_status != 'rejected'";
                                    $down_payment_stmt = $db->prepare($down_payment_query);
                                    $down_payment_stmt->execute([$student_id, $term['id']]);
                                    $down_payment_result = $down_payment_stmt->fetch(PDO::FETCH_ASSOC);
                                    
                                    if ($down_payment_result['down_payment_date']) {
                                        $down_payment_date = new DateTime($down_payment_result['down_payment_date']);
                                        $current_date = new DateTime();
                                        
                                        // Calculate months since down payment
                                        $months_since_down = $down_payment_date->diff($current_date)->m + 
                                                           ($down_payment_date->diff($current_date)->y * 12);
                                        
                                        // The installment number that should be due this month
                                        $expected_installment = $months_since_down + 1; // First installment is 1 month after down payment
                                        
                                        // Check if this expected installment has been paid or submitted
                                        $installment_paid = in_array($expected_installment, $paid_installments);
                                        
                                        // Check if there's a pending payment for this installment
                                        $pending_installment_query = "SELECT COUNT(*) as count
                                                                     FROM student_payments 
                                                                     WHERE student_id = ? 
                                                                     AND payment_term_id = ? 
                                                                     AND payment_type = 'monthly_installment'
                                                                     AND installment_number = ?
                                                                     AND verification_status = 'pending'";
                                        $pending_stmt = $db->prepare($pending_installment_query);
                                        $pending_stmt->execute([$student_id, $term['id'], $expected_installment]);
                                        $pending_result = $pending_stmt->fetch(PDO::FETCH_ASSOC);
                                        $has_pending = $pending_result['count'] > 0;
                                        
                                        // Show reminder only if:
                                        // 1. Expected installment hasn't been paid/submitted yet AND
                                        // 2. Expected installment number is within the total number of installments AND
                                        // 3. We're past the first month after down payment
                                        if (!$installment_paid && !$has_pending && 
                                            $expected_installment <= $term['number_of_installments'] && 
                                            $months_since_down >= 1) {
                                            
                                            $payment_reminders[] = [
                                                'type' => 'installment',
                                                'message' => "Monthly installment #{$expected_installment} is due for {$term['term_name']} ({$term['year_label']})",
                                                'term_id' => $term['id'],
                                                'term_name' => $term['term_name'],
                                                'remaining' => $remaining_installments,
                                                'next_installment' => $expected_installment
                                            ];
                                        }
                                    }
                                }
                                // If $remaining_installments <= 0, all installments are paid, no reminder needed
                            }
                        }
                    }
                }
            }
        }
        
    } catch (Exception $e) {
        // Silently fail - will use placeholder
        $user_profile_picture = null;
        $payment_reminders = [];
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
                        <a href="<?php echo $base_url ?? '../'; ?>student/grade_history.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'grade_history.php') ? 'active' : ''; ?>">
                            <i class="fas fa-history"></i>
                            <span>Grade History</span>
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
                            <span>Tuition & Other Fees</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>student/payments.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'payments.php') ? 'active' : ''; ?>">
                            <i class="fas fa-credit-card"></i>
                            <span>Submit Payment Proof</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>student/payment_preferences.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'payment_preferences.php') ? 'active' : ''; ?>">
                            <i class="fas fa-cog"></i>
                            <span>Payment Preferences</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>student/section.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'section.php') ? 'active' : ''; ?>">
                            <i class="fas fa-users"></i>
                            <span>My Section</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>shared/announcements.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'announcements.php') ? 'active' : ''; ?>">
                            <i class="fas fa-bullhorn"></i>
                            <span>Announcements</span>
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

                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>teacher/schedule.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'schedule.php') ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-week"></i>
                            <span>My Schedule</span>
                        </a>
                    </li>

                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>shared/announcements.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'announcements.php') ? 'active' : ''; ?>">
                            <i class="fas fa-bullhorn"></i>
                            <span>Announcements</span>
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
                    <li class="menu-section">
                        <span class="section-title">Communication</span>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>shared/announcements.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'announcements.php') ? 'active' : ''; ?>">
                            <i class="fas fa-bullhorn"></i>
                            <span>Announcements</span>
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
                        <a href="<?php echo $base_url ?? '../'; ?>finance/payment_verification.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'payment_verification.php') ? 'active' : ''; ?>">
                            <i class="fas fa-check-circle"></i>
                            <span>Payment Verification</span>
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
                    <li class="menu-section">
                        <span class="section-title">Communication</span>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>shared/announcements.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'announcements.php') ? 'active' : ''; ?>">
                            <i class="fas fa-bullhorn"></i>
                            <span>Announcements</span>
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
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>registrar/student_grade_history.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'student_grade_history.php') ? 'active' : ''; ?>">
                            <i class="fas fa-history"></i>
                            <span>Grade History</span>
                        </a>
                    </li>

                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>shared/announcements.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'announcements.php') ? 'active' : ''; ?>">
                            <i class="fas fa-bullhorn"></i>
                            <span>Announcements</span>
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
                        <a href="<?php echo $base_url ?? '../'; ?>principal/sections.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'sections.php') ? 'active' : ''; ?>">
                            <i class="fas fa-layer-group"></i>
                            <span>Section Management</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>principal/schedules.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'schedules.php') ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-week"></i>
                            <span>Class Schedules</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>principal/teacher_schedules.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'teacher_schedules.php') ? 'active' : ''; ?>">
                            <i class="fas fa-user-clock"></i>
                            <span>Teacher Schedules</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>principal/schedule_archive.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'schedule_archive.php') ? 'active' : ''; ?>">
                            <i class="fas fa-archive"></i>
                            <span>Schedule Archive</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>principal/subject_curriculum.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'subject_curriculum.php') ? 'active' : ''; ?>">
                            <i class="fas fa-book-open"></i>
                            <span>Curriculum & Subjects</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>principal/announcements.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'announcements.php') ? 'active' : ''; ?>">
                            <i class="fas fa-bullhorn"></i>
                            <span>Manage Announcements</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="<?php echo $base_url ?? '../'; ?>shared/announcements.php" class="menu-link <?php echo (basename($_SERVER['PHP_SELF']) == 'announcements.php') ? 'active' : ''; ?>">
                            <i class="fas fa-eye"></i>
                            <span>View All Announcements</span>
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

    <!-- Payment Reminders for Students -->
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'student' && !empty($payment_reminders)): ?>
    <div class="payment-reminders-banner">
        <div class="payment-reminders-container">
            <div class="reminders-header">
                <i class="fas fa-exclamation-triangle"></i>
                <span class="reminders-title">Payment Reminders</span>
                <button class="reminders-close" onclick="hidePaymentReminders()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="reminders-content">
                <?php foreach ($payment_reminders as $index => $reminder): ?>
                    <div class="reminder-item <?php echo $reminder['type']; ?>-reminder">
                        <div class="reminder-icon">
                            <?php if ($reminder['type'] === 'full_payment'): ?>
                                <i class="fas fa-money-bill-wave"></i>
                            <?php elseif ($reminder['type'] === 'down_payment'): ?>
                                <i class="fas fa-hand-holding-usd"></i>
                            <?php elseif ($reminder['type'] === 'preferences'): ?>
                                <i class="fas fa-cog"></i>
                            <?php else: ?>
                                <i class="fas fa-calendar-check"></i>
                            <?php endif; ?>
                        </div>
                        <div class="reminder-content">
                            <span class="reminder-message"><?php echo htmlspecialchars($reminder['message']); ?></span>
                            <a href="<?php echo isset($reminder['action_url']) ? $reminder['action_url'] : ($base_url ?? '../') . 'student/payments.php'; ?>" class="reminder-action-btn">
                                <?php echo $reminder['type'] === 'preferences' ? 'Set Preferences' : 'Submit Payment'; ?>
                            </a>
                        </div>
                    </div>
                    <?php if ($index < count($payment_reminders) - 1): ?>
                        <div class="reminder-separator"></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <style>
    /* Payment Reminders Styles */
    .payment-reminders-banner {
        background: linear-gradient(135deg, #ff6b35, #f7931e);
        color: white;
        padding: 0;
        margin: 0;
        box-shadow: 0 2px 10px rgba(255, 107, 53, 0.3);
        border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        animation: slideDown 0.5s ease-out;
    }

    .payment-reminders-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0;
    }

    .reminders-header {
        background: rgba(0, 0, 0, 0.1);
        padding: 0.75rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .reminders-title {
        font-weight: 600;
        font-size: 1rem;
        margin-left: 0.5rem;
    }

    .reminders-close {
        background: none;
        border: none;
        color: white;
        font-size: 1.2rem;
        cursor: pointer;
        padding: 0.25rem;
        border-radius: 4px;
        transition: background 0.3s ease;
    }

    .reminders-close:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    .reminders-content {
        padding: 1rem 1.5rem;
    }

    .reminder-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.75rem 0;
    }

    .reminder-icon {
        font-size: 1.2rem;
        opacity: 0.9;
        min-width: 24px;
        text-align: center;
    }

    .reminder-content {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .reminder-message {
        flex: 1;
        font-size: 0.95rem;
        line-height: 1.4;
    }

    .reminder-action-btn {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 600;
        transition: all 0.3s ease;
        white-space: nowrap;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .reminder-action-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    .reminder-separator {
        height: 1px;
        background: rgba(255, 255, 255, 0.2);
        margin: 0.5rem 0;
    }

    @keyframes slideDown {
        from {
            transform: translateY(-100%);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .payment-reminders-banner.fade-out {
        animation: slideUp 0.3s ease-in forwards;
    }

    @keyframes slideUp {
        from {
            transform: translateY(0);
            opacity: 1;
            max-height: 200px;
        }
        to {
            transform: translateY(-100%);
            opacity: 0;
            max-height: 0;
        }
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .reminders-header,
        .reminders-content {
            padding-left: 1rem;
            padding-right: 1rem;
        }
        
        .reminder-content {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
        
        .reminder-message {
            font-size: 0.9rem;
        }
        
        .reminder-action-btn {
            align-self: flex-end;
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
        }
    }

    /* Adjust main wrapper margin when reminders are shown */
    .main-wrapper {
        transition: margin-top 0.3s ease;
    }
    </style>

    <script>
    function hidePaymentReminders() {
        const banner = document.querySelector('.payment-reminders-banner');
        if (banner) {
            banner.classList.add('fade-out');
            setTimeout(() => {
                banner.style.display = 'none';
                // Store in localStorage to remember dismissal
                localStorage.setItem('paymentRemindersDismissed', Date.now().toString());
            }, 300);
        }
    }

    // Check if reminders were recently dismissed
    document.addEventListener('DOMContentLoaded', function() {
        const dismissed = localStorage.getItem('paymentRemindersDismissed');
        if (dismissed) {
            const dismissedTime = parseInt(dismissed);
            const now = Date.now();
            const secondsSinceDismissed = (now - dismissedTime) / 1000;
            
            // Hide reminders if dismissed less than 5 seconds ago
            if (secondsSinceDismissed < 5) {
                const banner = document.querySelector('.payment-reminders-banner');
                if (banner) {
                    banner.style.display = 'none';
                }
            } else {
                // Clear old dismissal
                localStorage.removeItem('paymentRemindersDismissed');
            }
        }
    });
    </script>
    <?php endif; ?>

    <!-- Main Content Wrapper -->
    <div class="main-wrapper" id="mainWrapper">
        <main class="main-content">
            <div class="container">
