<?php
require_once '../includes/auth_check.php';

// Check if user is a finance officer
if (!checkRole('finance')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$page_title = 'Finance Dashboard - GTBA Portal';
$base_url = '../';

try {
    $database = new Database();
    $db = $database->connect();
    
    // Get current school year
    $query = "SELECT * FROM school_years WHERE is_active = 1 LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $current_year = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get total enrolled students for current year
    $query = "SELECT COUNT(DISTINCT se.student_id) as total_enrolled
              FROM student_enrollments se
              WHERE se.school_year_id = :school_year_id 
              AND se.enrollment_status = 'Enrolled'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':school_year_id', $current_year['id']);
    $stmt->execute();
    $total_enrolled = $stmt->fetch(PDO::FETCH_ASSOC)['total_enrolled'] ?? 0;
    
    // Get total expected revenue from tuition fees
    $query = "SELECT SUM(tf.gtba_total_amount) as total_expected_revenue,
                     COUNT(DISTINCT se.student_id) as enrolled_count
              FROM student_enrollments se
              JOIN tuition_fees tf ON se.grade_level_id = tf.grade_level_id 
                                   AND se.school_year_id = tf.school_year_id
              WHERE se.school_year_id = :school_year_id 
              AND se.enrollment_status = 'Enrolled'
              AND tf.is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':school_year_id', $current_year['id']);
    $stmt->execute();
    $revenue_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_expected_revenue = $revenue_data['total_expected_revenue'] ?? 0;
    
    // Calculate outstanding balances (simulated - would need actual payments table)
    $outstanding_balance = $total_expected_revenue * 0.75; // Assuming 75% still outstanding
    $paid_amount = $total_expected_revenue - $outstanding_balance;
    $collection_rate = $total_expected_revenue > 0 ? ($paid_amount / $total_expected_revenue) * 100 : 0;
    
    // Get monthly collection statistics (simulated data)
    $monthly_collections = [
        'January' => $total_expected_revenue * 0.05,
        'February' => $total_expected_revenue * 0.08,
        'March' => $total_expected_revenue * 0.12,
        'April' => $total_expected_revenue * 0.15,
        'May' => $total_expected_revenue * 0.10,
        'June' => $total_expected_revenue * 0.18,
        'July' => $total_expected_revenue * 0.20,
        'August' => $total_expected_revenue * 0.08,
        'September' => $total_expected_revenue * 0.04,
        'October' => 0,
        'November' => 0,
        'December' => 0
    ];
    
    // Get overdue accounts count (simulated)
    $overdue_accounts = ceil($total_enrolled * 0.15); // 15% of students have overdue payments
    
    // Get enrollment statistics by grade level
    $query = "SELECT gl.grade_name, 
                     COUNT(DISTINCT se.student_id) as enrolled_count,
                     tf.gtba_total_amount as fee_per_student,
                     (COUNT(DISTINCT se.student_id) * tf.gtba_total_amount) as grade_total_revenue
              FROM grade_levels gl
              LEFT JOIN student_enrollments se ON gl.id = se.grade_level_id 
                                               AND se.school_year_id = :school_year_id
                                               AND se.enrollment_status = 'Enrolled'
              LEFT JOIN tuition_fees tf ON gl.id = tf.grade_level_id 
                                         AND tf.school_year_id = :school_year_id
                                         AND tf.is_active = 1
              WHERE gl.is_active = 1
              GROUP BY gl.id, gl.grade_name, gl.grade_order, tf.gtba_total_amount
              ORDER BY gl.grade_order";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':school_year_id', $current_year['id']);
    $stmt->execute();
    $enrollment_by_grade = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get tuition fee breakdown by grade level
    $query = "SELECT gl.grade_name, 
                     tf.gtba_tuition_fee,
                     tf.gtba_other_fees,
                     tf.gtba_miscellaneous_fees,
                     tf.gtba_total_amount
              FROM grade_levels gl
              JOIN tuition_fees tf ON gl.id = tf.grade_level_id
              WHERE tf.school_year_id = :school_year_id 
              AND tf.is_active = 1
              AND gl.is_active = 1
              ORDER BY gl.grade_order";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':school_year_id', $current_year['id']);
    $stmt->execute();
    $tuition_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get payment methods statistics
    $query = "SELECT method_type, COUNT(*) as method_count, is_active
              FROM payment_methods 
              GROUP BY method_type, is_active
              ORDER BY method_type";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $payment_methods_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent enrollments for financial tracking
    $query = "SELECT s.student_id, s.first_name, s.last_name, 
                     gl.grade_name, se.enrollment_date,
                     tf.gtba_total_amount
              FROM student_enrollments se
              JOIN students s ON se.student_id = s.id
              JOIN grade_levels gl ON se.grade_level_id = gl.id
              LEFT JOIN tuition_fees tf ON se.grade_level_id = tf.grade_level_id 
                                         AND se.school_year_id = tf.school_year_id
                                         AND tf.is_active = 1
              WHERE se.school_year_id = :school_year_id
              AND se.enrollment_status = 'Enrolled'
              ORDER BY se.enrollment_date DESC
              LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':school_year_id', $current_year['id']);
    $stmt->execute();
    $recent_enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent announcements
    $query = "SELECT a.*, u.username as created_by_username 
              FROM announcements a 
              LEFT JOIN users u ON a.created_by = u.id
              WHERE a.is_published = 1
              ORDER BY a.created_at DESC 
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $recent_announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Unable to load financial dashboard data.";
    error_log("Finance dashboard error: " . $e->getMessage());
}

ob_start();
?>

<div class="welcome-section">
    <h1 class="welcome-title">Finance Dashboard</h1>
    <p class="welcome-subtitle">Golden Treasure Baptist Academy - Financial Management & Reporting</p>
    
    <div class="user-info">
        <h4>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h4>
        <p><strong>Role:</strong> <?php echo htmlspecialchars($_SESSION['role_display']); ?></p>
        <?php if ($current_year): ?>
            <p><strong>Current School Year:</strong> <?php echo htmlspecialchars($current_year['year_label']); ?></p>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <?php echo $error_message; ?>
    </div>
<?php else: ?>

<!-- Financial Overview Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Total Enrolled Students -->
    <div class="dashboard-card" style="background: linear-gradient(135deg, #4CAF50, #45a049); color: white;">
        <div class="card-icon">
            <i class="fas fa-users" style="font-size: 2.5rem; color: rgba(255,255,255,0.9);"></i>
        </div>
        <div class="card-content">
            <h3 style="margin: 0; font-size: 2.5rem; font-weight: bold;"><?php echo number_format($total_enrolled); ?></h3>
            <p style="margin: 0.5rem 0 0 0; font-size: 1.1rem; opacity: 0.9;">Enrolled Students</p>
            <small style="opacity: 0.8;">Current Academic Year</small>
        </div>
    </div>

    <!-- Total Expected Revenue -->
    <div class="dashboard-card" style="background: linear-gradient(135deg, #2196F3, #1976D2); color: white;">
        <div class="card-icon">
            <i class="fas fa-chart-line" style="font-size: 2.5rem; color: rgba(255,255,255,0.9);"></i>
        </div>
        <div class="card-content">
            <h3 style="margin: 0; font-size: 2rem; font-weight: bold;">₱<?php echo number_format($total_expected_revenue, 2); ?></h3>
            <p style="margin: 0.5rem 0 0 0; font-size: 1.1rem; opacity: 0.9;">Expected Revenue</p>
            <small style="opacity: 0.8;">Total Tuition Fees</small>
        </div>
    </div>

    <!-- Average Fee Per Student -->
    <div class="dashboard-card" style="background: linear-gradient(135deg, #FF9800, #F57C00); color: white;">
        <div class="card-icon">
            <i class="fas fa-money-bill-wave" style="font-size: 2.5rem; color: rgba(255,255,255,0.9);"></i>
        </div>
        <div class="card-content">
            <h3 style="margin: 0; font-size: 2rem; font-weight: bold;">
                ₱<?php echo $total_enrolled > 0 ? number_format($total_expected_revenue / $total_enrolled, 2) : '0.00'; ?>
            </h3>
            <p style="margin: 0.5rem 0 0 0; font-size: 1.1rem; opacity: 0.9;">Average Fee/Student</p>
            <small style="opacity: 0.8;">Per Academic Year</small>
        </div>
    </div>

    <!-- Payment Methods -->
    <div class="dashboard-card" style="background: linear-gradient(135deg, #9C27B0, #7B1FA2); color: white;">
        <div class="card-icon">
            <i class="fas fa-credit-card" style="font-size: 2.5rem; color: rgba(255,255,255,0.9);"></i>
        </div>
        <div class="card-content">
            <h3 style="margin: 0; font-size: 2.5rem; font-weight: bold;">
                <?php 
                $active_methods = 0;
                foreach ($payment_methods_stats as $method) {
                    if ($method['is_active']) $active_methods += $method['method_count'];
                }
                echo $active_methods;
                ?>
            </h3>
            <p style="margin: 0.5rem 0 0 0; font-size: 1.1rem; opacity: 0.9;">Payment Methods</p>
            <small style="opacity: 0.8;">Active Payment Options</small>
        </div>
    </div>
</div>

<!-- Enrollment & Revenue by Grade Level -->
<div style="background: var(--white); border-radius: 15px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
    <h3 style="color: var(--dark-blue); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
        <i class="fas fa-chart-bar"></i>
        Enrollment & Revenue Analysis by Grade Level
    </h3>
    
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--light-blue); border-bottom: 2px solid var(--primary-blue);">
                    <th style="padding: 1rem; text-align: left; color: var(--black); font-weight: 600;">Grade Level</th>
                    <th style="padding: 1rem; text-align: center; color: var(--black); font-weight: 600;">Enrolled Students</th>
                    <th style="padding: 1rem; text-align: right; color: var(--black); font-weight: 600;">Fee per Student</th>
                    <th style="padding: 1rem; text-align: right; color: var(--black); font-weight: 600;">Total Revenue</th>
                    <th style="padding: 1rem; text-align: center; color: var(--black); font-weight: 600;">Revenue %</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($enrollment_by_grade)): ?>
                    <?php foreach ($enrollment_by_grade as $grade): ?>
                        <?php 
                        $revenue_percentage = $total_expected_revenue > 0 ? 
                            (($grade['grade_total_revenue'] ?? 0) / $total_expected_revenue) * 100 : 0;
                        ?>
                        <tr style="border-bottom: 1px solid var(--border-gray);">
                            <td style="padding: 1rem; color: var(--black); font-weight: 500;">
                                <?php echo htmlspecialchars($grade['grade_name']); ?>
                            </td>
                            <td style="padding: 1rem; text-align: center; color: var(--black);">
                                <span style="background: var(--light-blue); padding: 0.25rem 0.75rem; border-radius: 15px; font-weight: 500;">
                                    <?php echo number_format($grade['enrolled_count'] ?? 0); ?>
                                </span>
                            </td>
                            <td style="padding: 1rem; text-align: right; color: var(--black); font-weight: 500;">
                                ₱<?php echo number_format($grade['fee_per_student'] ?? 0, 2); ?>
                            </td>
                            <td style="padding: 1rem; text-align: right; color: var(--black); font-weight: 600;">
                                ₱<?php echo number_format($grade['grade_total_revenue'] ?? 0, 2); ?>
                            </td>
                            <td style="padding: 1rem; text-align: center;">
                                <div style="background: var(--light-gray); border-radius: 10px; padding: 0.25rem;">
                                    <div style="background: var(--primary-blue); height: 20px; border-radius: 8px; width: <?php echo $revenue_percentage; ?>%; min-width: 2px; display: flex; align-items: center; justify-content: center;">
                                        <span style="color: white; font-size: 0.8rem; font-weight: 500;">
                                            <?php echo number_format($revenue_percentage, 1); ?>%
                                        </span>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="padding: 2rem; text-align: center; color: var(--gray);">
                            No enrollment data available for the current school year.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Tuition Fee Structure -->
<div style="background: var(--white); border-radius: 15px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
    <h3 style="color: var(--dark-blue); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
        <i class="fas fa-money-bill"></i>
        Tuition Fee Structure (<?php echo htmlspecialchars($current_year['year_label'] ?? 'Current Year'); ?>)
    </h3>
    
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--light-blue); border-bottom: 2px solid var(--primary-blue);">
                    <th style="padding: 1rem; text-align: left; color: var(--black); font-weight: 600;">Grade Level</th>
                    <th style="padding: 1rem; text-align: right; color: var(--black); font-weight: 600;">Tuition Fee</th>
                    <th style="padding: 1rem; text-align: right; color: var(--black); font-weight: 600;">Other Fees</th>
                    <th style="padding: 1rem; text-align: right; color: var(--black); font-weight: 600;">Misc. Fees</th>
                    <th style="padding: 1rem; text-align: right; color: var(--black); font-weight: 600;">Total Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($tuition_breakdown)): ?>
                    <?php foreach ($tuition_breakdown as $fee): ?>
                        <tr style="border-bottom: 1px solid var(--border-gray);">
                            <td style="padding: 1rem; color: var(--black); font-weight: 500;">
                                <?php echo htmlspecialchars($fee['grade_name']); ?>
                            </td>
                            <td style="padding: 1rem; text-align: right; color: var(--black);">
                                ₱<?php echo number_format($fee['gtba_tuition_fee'], 2); ?>
                            </td>
                            <td style="padding: 1rem; text-align: right; color: var(--black);">
                                ₱<?php echo number_format($fee['gtba_other_fees'], 2); ?>
                            </td>
                            <td style="padding: 1rem; text-align: right; color: var(--black);">
                                ₱<?php echo number_format($fee['gtba_miscellaneous_fees'], 2); ?>
                            </td>
                            <td style="padding: 1rem; text-align: right; color: var(--dark-blue); font-weight: 600; background: var(--light-blue);">
                                ₱<?php echo number_format($fee['gtba_total_amount'], 2); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="padding: 2rem; text-align: center; color: var(--gray);">
                            No tuition fee structure found for the current school year.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Enrollments -->
<div style="background: var(--white); border-radius: 15px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
    <h3 style="color: var(--dark-blue); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
        <i class="fas fa-clock"></i>
        Recent Enrollments (Financial Tracking)
    </h3>
    
    <?php if (!empty($recent_enrollments)): ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: var(--light-blue); border-bottom: 2px solid var(--primary-blue);">
                        <th style="padding: 1rem; text-align: left; color: var(--black); font-weight: 600;">Student ID</th>
                        <th style="padding: 1rem; text-align: left; color: var(--black); font-weight: 600;">Student Name</th>
                        <th style="padding: 1rem; text-align: left; color: var(--black); font-weight: 600;">Grade Level</th>
                        <th style="padding: 1rem; text-align: center; color: var(--black); font-weight: 600;">Enrollment Date</th>
                        <th style="padding: 1rem; text-align: right; color: var(--black); font-weight: 600;">Tuition Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_enrollments as $enrollment): ?>
                        <tr style="border-bottom: 1px solid var(--border-gray);">
                            <td style="padding: 1rem; color: var(--black); font-weight: 500;">
                                <?php echo htmlspecialchars($enrollment['student_id']); ?>
                            </td>
                            <td style="padding: 1rem; color: var(--black);">
                                <?php echo htmlspecialchars($enrollment['first_name'] . ' ' . $enrollment['last_name']); ?>
                            </td>
                            <td style="padding: 1rem; color: var(--black);">
                                <?php echo htmlspecialchars($enrollment['grade_name']); ?>
                            </td>
                            <td style="padding: 1rem; text-align: center; color: var(--black);">
                                <?php echo date('M d, Y', strtotime($enrollment['enrollment_date'])); ?>
                            </td>
                            <td style="padding: 1rem; text-align: right; color: var(--dark-blue); font-weight: 600;">
                                ₱<?php echo number_format($enrollment['gtba_total_amount'] ?? 0, 2); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 2rem; color: var(--gray);">
            <i class="fas fa-info-circle" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
            <p>No recent enrollments found for the current school year.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Recent Announcements -->
<?php if (!empty($recent_announcements)): ?>
<div style="background: var(--white); border-radius: 15px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
    <h3 style="color: var(--dark-blue); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
        📢 Recent Announcements
    </h3>
    
    <div class="announcements-list">
        <?php foreach ($recent_announcements as $announcement): ?>
            <div class="announcement-item clickable-announcement" 
                 data-id="<?php echo $announcement['id']; ?>"
                 data-title="<?php echo htmlspecialchars($announcement['title']); ?>"
                 data-content="<?php echo htmlspecialchars($announcement['content']); ?>"
                 data-type="<?php echo htmlspecialchars($announcement['announcement_type']); ?>"
                 data-priority="<?php echo htmlspecialchars($announcement['priority']); ?>"
                 data-author="<?php echo htmlspecialchars($announcement['created_by_username']); ?>"
                 data-date="<?php echo date('M j, Y', strtotime($announcement['created_at'])); ?>"
                 style="cursor: pointer; padding: 1rem; border: 1px solid var(--border-gray); border-radius: 8px; margin-bottom: 1rem; transition: all 0.3s ease;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;">
                    <div style="flex: 1;">
                        <div style="font-weight: 600; color: var(--black); margin-bottom: 0.5rem;">
                            <?php echo htmlspecialchars($announcement['title']); ?>
                        </div>
                        <div style="display: flex; gap: 1rem; font-size: 0.85rem; color: var(--gray);">
                            <span><?php echo htmlspecialchars($announcement['announcement_type']); ?></span>
                            <span>by <?php echo htmlspecialchars($announcement['created_by_username']); ?></span>
                            <span><?php echo date('M j, Y', strtotime($announcement['created_at'])); ?></span>
                        </div>
                    </div>
                    <span class="priority-badge priority-<?php echo strtolower($announcement['priority']); ?>" 
                          style="padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase;">
                        <?php echo htmlspecialchars($announcement['priority']); ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div style="text-align: center; margin-top: 1rem;">
        <a href="announcements.php" style="display: inline-block; background: transparent; border: 2px solid var(--primary-blue); color: var(--primary-blue); padding: 0.5rem 1rem; border-radius: 5px; text-decoration: none; font-weight: 600; transition: all 0.3s ease;">
            View All Announcements
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Quick Actions -->
<div style="background: var(--white); border-radius: 15px; padding: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
    <h3 style="color: var(--dark-blue); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
        <i class="fas fa-tools"></i>
        Quick Actions
    </h3>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
        <a href="tuition.php" style="display: block; background: var(--light-blue); padding: 1.5rem; border-radius: 10px; text-decoration: none; text-align: center; transition: all 0.3s ease;" 
           onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 25px rgba(0,0,0,0.15)';" 
           onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
            <i class="fas fa-money-bill-wave" style="font-size: 2rem; color: var(--primary-blue); margin-bottom: 0.5rem; display: block;"></i>
            <span style="color: var(--dark-blue); font-weight: 600;">Manage Tuition Fees</span>
        </a>
        
        <a href="payments.php" style="display: block; background: var(--light-blue); padding: 1.5rem; border-radius: 10px; text-decoration: none; text-align: center; transition: all 0.3s ease;" 
           onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 25px rgba(0,0,0,0.15)';" 
           onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
            <i class="fas fa-credit-card" style="font-size: 2rem; color: var(--primary-blue); margin-bottom: 0.5rem; display: block;"></i>
            <span style="color: var(--dark-blue); font-weight: 600;">Payment Methods</span>
        </a>
        
        <a href="reports.php" style="display: block; background: var(--light-blue); padding: 1.5rem; border-radius: 10px; text-decoration: none; text-align: center; transition: all 0.3s ease;" 
           onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 25px rgba(0,0,0,0.15)';" 
           onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
            <i class="fas fa-chart-line" style="font-size: 2rem; color: var(--primary-blue); margin-bottom: 0.5rem; display: block;"></i>
            <span style="color: var(--dark-blue); font-weight: 600;">Financial Reports</span>
        </a>
        
    </div>
</div>

<?php endif; ?>

<style>
.dashboard-card {
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    gap: 1.5rem;
    transition: all 0.3s ease;
}

.dashboard-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 35px rgba(0,0,0,0.2);
}

.card-icon {
    flex-shrink: 0;
}

.card-content {
    flex-grow: 1;
}

@media (max-width: 768px) {
    .dashboard-card {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
}

/* Priority Badge Styles */
.priority-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.priority-low { background: #e8f5e8; color: #2e7d32; }
.priority-normal { background: #e3f2fd; color: #1565c0; }
.priority-high { background: #fff3e0; color: #ef6c00; }
.priority-urgent { background: #ffebee; color: #c62828; }

/* Clickable Announcement Hover Effect */
.clickable-announcement:hover {
    background: var(--light-blue) !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* Announcement Modal Styles */
.announcement-modal {
    display: flex;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    align-items: center;
    justify-content: center;
    visibility: hidden;
    opacity: 0;
    transition: visibility 0s, opacity 0.3s ease;
}

.announcement-modal.show {
    visibility: visible;
    opacity: 1;
}

.modal-content {
    background: white;
    margin: 2rem;
    padding: 0;
    border-radius: 15px;
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideIn 0.3s ease;
}

.modal-header {
    background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
    color: white;
    padding: 1.5rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
}

.modal-title {
    font-size: 1.4rem;
    font-weight: 700;
    margin: 0;
    line-height: 1.3;
    flex: 1;
}

.modal-close {
    background: none;
    border: none;
    color: white;
    font-size: 1.8rem;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background 0.3s ease;
    flex-shrink: 0;
}

.modal-close:hover {
    background: rgba(255, 255, 255, 0.2);
}

.modal-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-top: 0.75rem;
    font-size: 0.9rem;
    opacity: 0.9;
}

.modal-meta span {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.modal-body {
    padding: 2rem;
    overflow-y: auto;
    max-height: 50vh;
}

.modal-content-text {
    font-size: 1rem;
    line-height: 1.6;
    color: var(--text);
    white-space: pre-wrap;
}

.modal-footer {
    background: var(--light-gray);
    padding: 1rem 2rem;
    border-top: 1px solid var(--border-gray);
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

.modal-priority {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideIn {
    from { 
        opacity: 0;
        transform: translateY(-50px) scale(0.9);
    }
    to { 
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
</style>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
