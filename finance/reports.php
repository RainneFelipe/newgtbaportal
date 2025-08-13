<?php
require_once '../includes/auth_check.php';

// Check if user is a finance officer
if (!checkRole('finance')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$page_title = 'Financial Reports - GTBA Portal';
$base_url = '../';

try {
    $database = new Database();
    $db = $database->connect();
    
    // Get current school year
    $query = "SELECT * FROM school_years WHERE is_active = 1 LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $current_year = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get all school years for dropdown
    $query = "SELECT * FROM school_years ORDER BY start_date DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $school_years = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get selected school year (default to current)
    $selected_year_id = $_GET['year_id'] ?? $current_year['id'] ?? null;
    
    // Get comprehensive enrollment and revenue data
    $query = "SELECT 
                gl.grade_name,
                gl.grade_order,
                COUNT(DISTINCT se.student_id) as enrolled_count,
                tf.gtba_tuition_fee,
                tf.gtba_other_fees,
                tf.gtba_miscellaneous_fees,
                tf.gtba_total_amount as fee_per_student,
                (COUNT(DISTINCT se.student_id) * tf.gtba_total_amount) as total_revenue,
                COUNT(DISTINCT CASE WHEN se.enrollment_status = 'Enrolled' THEN se.student_id END) as currently_enrolled,
                COUNT(DISTINCT CASE WHEN se.enrollment_status = 'Dropped' THEN se.student_id END) as dropped_count,
                COUNT(DISTINCT CASE WHEN se.enrollment_status = 'Transferred' THEN se.student_id END) as transferred_count
              FROM grade_levels gl
              LEFT JOIN student_enrollments se ON gl.id = se.grade_level_id 
                                               AND se.school_year_id = :school_year_id
              LEFT JOIN tuition_fees tf ON gl.id = tf.grade_level_id 
                                         AND tf.school_year_id = :school_year_id
                                         AND tf.is_active = 1
              WHERE gl.is_active = 1
              GROUP BY gl.id, gl.grade_name, gl.grade_order, tf.gtba_tuition_fee, tf.gtba_other_fees, tf.gtba_miscellaneous_fees, tf.gtba_total_amount
              ORDER BY gl.grade_order";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':school_year_id', $selected_year_id);
    $stmt->execute();
    $detailed_report = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals
    $total_enrolled = array_sum(array_column($detailed_report, 'enrolled_count'));
    $total_currently_enrolled = array_sum(array_column($detailed_report, 'currently_enrolled'));
    $total_dropped = array_sum(array_column($detailed_report, 'dropped_count'));
    $total_transferred = array_sum(array_column($detailed_report, 'transferred_count'));
    $total_expected_revenue = array_sum(array_column($detailed_report, 'total_revenue'));
    
    // Get enrollment trends by month
    $query = "SELECT 
                MONTH(se.enrollment_date) as month,
                MONTHNAME(se.enrollment_date) as month_name,
                COUNT(*) as enrollments,
                SUM(tf.gtba_total_amount) as month_revenue
              FROM student_enrollments se
              JOIN tuition_fees tf ON se.grade_level_id = tf.grade_level_id 
                                   AND se.school_year_id = tf.school_year_id
              WHERE se.school_year_id = :school_year_id
              AND tf.is_active = 1
              GROUP BY MONTH(se.enrollment_date), MONTHNAME(se.enrollment_date)
              ORDER BY MONTH(se.enrollment_date)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':school_year_id', $selected_year_id);
    $stmt->execute();
    $enrollment_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get fee structure comparison across grade levels
    $query = "SELECT 
                gl.grade_name,
                tf.gtba_tuition_fee,
                tf.gtba_other_fees,
                tf.gtba_miscellaneous_fees,
                tf.gtba_total_amount,
                ROUND((tf.gtba_tuition_fee / tf.gtba_total_amount) * 100, 1) as tuition_percentage,
                ROUND((tf.gtba_other_fees / tf.gtba_total_amount) * 100, 1) as other_fees_percentage,
                ROUND((tf.gtba_miscellaneous_fees / tf.gtba_total_amount) * 100, 1) as misc_fees_percentage
              FROM grade_levels gl
              JOIN tuition_fees tf ON gl.id = tf.grade_level_id
              WHERE tf.school_year_id = :school_year_id 
              AND tf.is_active = 1
              AND gl.is_active = 1
              ORDER BY gl.grade_order";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':school_year_id', $selected_year_id);
    $stmt->execute();
    $fee_analysis = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Unable to load financial reports data.";
    error_log("Financial reports error: " . $e->getMessage());
}

ob_start();
?>

<div class="welcome-section">
    <h1 class="welcome-title">Financial Reports</h1>
    <p class="welcome-subtitle">Comprehensive financial analysis and reporting for GTBA</p>
</div>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
    </div>
<?php else: ?>

<!-- Report Controls -->
<div style="background: var(--white); border-radius: 15px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
    <h3 style="color: var(--dark-blue); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
        <i class="fas fa-filter"></i>
        Report Filters & Export
    </h3>
    
    <div style="display: flex; align-items: center; gap: 2rem; flex-wrap: wrap; justify-content: space-between;">
        <form method="GET" style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
            <label style="color: var(--black); font-weight: 500;">School Year:</label>
            <select name="year_id" onchange="this.form.submit()" style="padding: 0.5rem 1rem; border: 2px solid var(--border-gray); border-radius: 8px; background: var(--white); color: var(--black); font-size: 1rem;">
                <?php foreach ($school_years as $year): ?>
                    <option value="<?php echo $year['id']; ?>" <?php echo ($year['id'] == $selected_year_id) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($year['year_label']); ?>
                        <?php if ($year['is_active']): ?>
                            (Current)
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        
        <div style="display: flex; gap: 1rem;">
            <button onclick="window.print()" style="background: var(--primary-blue); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; transition: all 0.3s ease;">
                <i class="fas fa-print"></i> Print Report
            </button>
            <button onclick="exportToCSV()" style="background: #4CAF50; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; transition: all 0.3s ease;">
                <i class="fas fa-download"></i> Export CSV
            </button>
        </div>
    </div>
</div>

<!-- Executive Summary -->
<div style="background: var(--white); border-radius: 15px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
    <h3 style="color: var(--dark-blue); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
        <i class="fas fa-chart-line"></i>
        Executive Summary
        <?php if (!empty($school_years)): ?>
            <span style="color: var(--gray); font-size: 1rem; font-weight: normal;">
                (<?php 
                $selected_year = array_filter($school_years, function($year) use ($selected_year_id) {
                    return $year['id'] == $selected_year_id;
                });
                echo htmlspecialchars(current($selected_year)['year_label'] ?? '');
                ?>)
            </span>
        <?php endif; ?>
    </h3>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
        <div style="background: linear-gradient(135deg, #4CAF50, #45a049); color: white; padding: 1.5rem; border-radius: 10px; text-align: center;">
            <h4 style="margin: 0; font-size: 2.5rem; font-weight: bold;"><?php echo number_format($total_currently_enrolled); ?></h4>
            <p style="margin: 0.5rem 0 0 0; opacity: 0.9;">Currently Enrolled</p>
        </div>
        
        <div style="background: linear-gradient(135deg, #2196F3, #1976D2); color: white; padding: 1.5rem; border-radius: 10px; text-align: center;">
            <h4 style="margin: 0; font-size: 1.8rem; font-weight: bold;">₱<?php echo number_format($total_expected_revenue, 2); ?></h4>
            <p style="margin: 0.5rem 0 0 0; opacity: 0.9;">Expected Revenue</p>
        </div>
        
        <div style="background: linear-gradient(135deg, #FF9800, #F57C00); color: white; padding: 1.5rem; border-radius: 10px; text-align: center;">
            <h4 style="margin: 0; font-size: 1.8rem; font-weight: bold;">
                ₱<?php echo $total_currently_enrolled > 0 ? number_format($total_expected_revenue / $total_currently_enrolled, 2) : '0.00'; ?>
            </h4>
            <p style="margin: 0.5rem 0 0 0; opacity: 0.9;">Average Fee/Student</p>
        </div>
        
        <div style="background: linear-gradient(135deg, #9C27B0, #7B1FA2); color: white; padding: 1.5rem; border-radius: 10px; text-align: center;">
            <h4 style="margin: 0; font-size: 2.5rem; font-weight: bold;">
                <?php echo $total_enrolled > 0 ? number_format(($total_currently_enrolled / $total_enrolled) * 100, 1) : '0'; ?>%
            </h4>
            <p style="margin: 0.5rem 0 0 0; opacity: 0.9;">Retention Rate</p>
        </div>
    </div>
</div>

<!-- Detailed Grade Level Report -->
<div style="background: var(--white); border-radius: 15px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
    <h3 style="color: var(--dark-blue); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
        <i class="fas fa-table"></i>
        Detailed Grade Level Analysis
    </h3>
    
    <?php if (!empty($detailed_report)): ?>
        <div style="overflow-x: auto;">
            <table id="detailedReportTable" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: var(--light-blue); border-bottom: 2px solid var(--primary-blue);">
                        <th style="padding: 1rem; text-align: left; color: var(--black); font-weight: 600;">Grade Level</th>
                        <th style="padding: 1rem; text-align: center; color: var(--black); font-weight: 600;">Total Enrolled</th>
                        <th style="padding: 1rem; text-align: center; color: var(--black); font-weight: 600;">Currently Active</th>
                        <th style="padding: 1rem; text-align: center; color: var(--black); font-weight: 600;">Dropped</th>
                        <th style="padding: 1rem; text-align: center; color: var(--black); font-weight: 600;">Transferred</th>
                        <th style="padding: 1rem; text-align: right; color: var(--black); font-weight: 600;">Fee per Student</th>
                        <th style="padding: 1rem; text-align: right; color: var(--black); font-weight: 600;">Total Revenue</th>
                        <th style="padding: 1rem; text-align: center; color: var(--black); font-weight: 600;">Revenue %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detailed_report as $grade): ?>
                        <?php 
                        $revenue_percentage = $total_expected_revenue > 0 ? 
                            (($grade['total_revenue'] ?? 0) / $total_expected_revenue) * 100 : 0;
                        $retention_rate = $grade['enrolled_count'] > 0 ? 
                            ($grade['currently_enrolled'] / $grade['enrolled_count']) * 100 : 0;
                        ?>
                        <tr style="border-bottom: 1px solid var(--border-gray);">
                            <td style="padding: 1rem; color: var(--black); font-weight: 500;">
                                <?php echo htmlspecialchars($grade['grade_name']); ?>
                            </td>
                            <td style="padding: 1rem; text-align: center; color: var(--black);">
                                <?php echo number_format($grade['enrolled_count']); ?>
                            </td>
                            <td style="padding: 1rem; text-align: center;">
                                <span style="background: #4CAF50; color: white; padding: 0.25rem 0.75rem; border-radius: 15px; font-weight: 500;">
                                    <?php echo number_format($grade['currently_enrolled']); ?>
                                </span>
                                <?php if ($retention_rate < 100 && $grade['enrolled_count'] > 0): ?>
                                    <br><small style="color: var(--gray);">(<?php echo number_format($retention_rate, 1); ?>%)</small>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 1rem; text-align: center; color: var(--black);">
                                <?php if ($grade['dropped_count'] > 0): ?>
                                    <span style="background: #f44336; color: white; padding: 0.25rem 0.75rem; border-radius: 15px; font-weight: 500;">
                                        <?php echo number_format($grade['dropped_count']); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--gray);">0</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 1rem; text-align: center; color: var(--black);">
                                <?php if ($grade['transferred_count'] > 0): ?>
                                    <span style="background: #FF9800; color: white; padding: 0.25rem 0.75rem; border-radius: 15px; font-weight: 500;">
                                        <?php echo number_format($grade['transferred_count']); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--gray);">0</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 1rem; text-align: right; color: var(--black); font-weight: 500;">
                                ₱<?php echo number_format($grade['fee_per_student'] ?? 0, 2); ?>
                            </td>
                            <td style="padding: 1rem; text-align: right; color: var(--dark-blue); font-weight: 600;">
                                ₱<?php echo number_format($grade['total_revenue'] ?? 0, 2); ?>
                            </td>
                            <td style="padding: 1rem; text-align: center;">
                                <div style="background: var(--light-gray); border-radius: 10px; padding: 0.25rem; width: 60px; margin: 0 auto;">
                                    <div style="background: var(--primary-blue); height: 15px; border-radius: 8px; width: <?php echo $revenue_percentage; ?>%; min-width: 2px;"></div>
                                </div>
                                <small style="color: var(--gray); margin-top: 0.25rem; display: block;">
                                    <?php echo number_format($revenue_percentage, 1); ?>%
                                </small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <!-- Totals Row -->
                    <tr style="background: var(--light-blue); border-top: 2px solid var(--primary-blue); font-weight: 600;">
                        <td style="padding: 1rem; color: var(--dark-blue);">TOTALS</td>
                        <td style="padding: 1rem; text-align: center; color: var(--dark-blue);">
                            <?php echo number_format($total_enrolled); ?>
                        </td>
                        <td style="padding: 1rem; text-align: center; color: var(--dark-blue);">
                            <?php echo number_format($total_currently_enrolled); ?>
                        </td>
                        <td style="padding: 1rem; text-align: center; color: var(--dark-blue);">
                            <?php echo number_format($total_dropped); ?>
                        </td>
                        <td style="padding: 1rem; text-align: center; color: var(--dark-blue);">
                            <?php echo number_format($total_transferred); ?>
                        </td>
                        <td style="padding: 1rem; text-align: right; color: var(--dark-blue);">
                            -
                        </td>
                        <td style="padding: 1rem; text-align: right; color: var(--dark-blue); font-size: 1.1rem;">
                            ₱<?php echo number_format($total_expected_revenue, 2); ?>
                        </td>
                        <td style="padding: 1rem; text-align: center; color: var(--dark-blue);">
                            100%
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 2rem; color: var(--gray);">
            <i class="fas fa-info-circle" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
            <p>No data available for the selected school year.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Fee Structure Analysis -->
<?php if (!empty($fee_analysis)): ?>
<div style="background: var(--white); border-radius: 15px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
    <h3 style="color: var(--dark-blue); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
        <i class="fas fa-pie-chart"></i>
        Fee Structure Analysis
    </h3>
    
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--light-blue); border-bottom: 2px solid var(--primary-blue);">
                    <th style="padding: 1rem; text-align: left; color: var(--black); font-weight: 600;">Grade Level</th>
                    <th style="padding: 1rem; text-align: right; color: var(--black); font-weight: 600;">Tuition Fee</th>
                    <th style="padding: 1rem; text-align: right; color: var(--black); font-weight: 600;">Other Fees</th>
                    <th style="padding: 1rem; text-align: right; color: var(--black); font-weight: 600;">Misc. Fees</th>
                    <th style="padding: 1rem; text-align: right; color: var(--black); font-weight: 600;">Total</th>
                    <th style="padding: 1rem; text-align: center; color: var(--black); font-weight: 600;">Fee Composition</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fee_analysis as $fee): ?>
                    <tr style="border-bottom: 1px solid var(--border-gray);">
                        <td style="padding: 1rem; color: var(--black); font-weight: 500;">
                            <?php echo htmlspecialchars($fee['grade_name']); ?>
                        </td>
                        <td style="padding: 1rem; text-align: right; color: var(--black);">
                            ₱<?php echo number_format($fee['gtba_tuition_fee'], 2); ?>
                            <br><small style="color: var(--gray);">(<?php echo $fee['tuition_percentage']; ?>%)</small>
                        </td>
                        <td style="padding: 1rem; text-align: right; color: var(--black);">
                            ₱<?php echo number_format($fee['gtba_other_fees'], 2); ?>
                            <br><small style="color: var(--gray);">(<?php echo $fee['other_fees_percentage']; ?>%)</small>
                        </td>
                        <td style="padding: 1rem; text-align: right; color: var(--black);">
                            ₱<?php echo number_format($fee['gtba_miscellaneous_fees'], 2); ?>
                            <br><small style="color: var(--gray);">(<?php echo $fee['misc_fees_percentage']; ?>%)</small>
                        </td>
                        <td style="padding: 1rem; text-align: right; color: var(--dark-blue); font-weight: 600;">
                            ₱<?php echo number_format($fee['gtba_total_amount'], 2); ?>
                        </td>
                        <td style="padding: 1rem; text-align: center;">
                            <div style="display: flex; height: 20px; border-radius: 10px; overflow: hidden; width: 150px; margin: 0 auto;">
                                <div style="background: #4CAF50; width: <?php echo $fee['tuition_percentage']; ?>%;" title="Tuition: <?php echo $fee['tuition_percentage']; ?>%"></div>
                                <div style="background: #2196F3; width: <?php echo $fee['other_fees_percentage']; ?>%;" title="Other: <?php echo $fee['other_fees_percentage']; ?>%"></div>
                                <div style="background: #FF9800; width: <?php echo $fee['misc_fees_percentage']; ?>%;" title="Misc: <?php echo $fee['misc_fees_percentage']; ?>%"></div>
                            </div>
                            <div style="display: flex; justify-content: center; gap: 1rem; margin-top: 0.5rem; font-size: 0.8rem;">
                                <span style="color: #4CAF50;">●</span>
                                <span style="color: #2196F3;">●</span>
                                <span style="color: #FF9800;">●</span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div style="margin-top: 1rem; padding: 1rem; background: var(--light-blue); border-radius: 8px;">
        <p style="margin: 0; color: var(--black); font-size: 0.9rem;">
            <strong>Legend:</strong>
            <span style="color: #4CAF50; margin-left: 1rem;">● Tuition Fee</span>
            <span style="color: #2196F3; margin-left: 1rem;">● Other Fees</span>
            <span style="color: #FF9800; margin-left: 1rem;">● Miscellaneous Fees</span>
        </p>
    </div>
</div>
<?php endif; ?>

<!-- Enrollment Trends -->
<?php if (!empty($enrollment_trends)): ?>
<div style="background: var(--white); border-radius: 15px; padding: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
    <h3 style="color: var(--dark-blue); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
        <i class="fas fa-calendar-check"></i>
        Enrollment Trends by Month
    </h3>
    
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--light-blue); border-bottom: 2px solid var(--primary-blue);">
                    <th style="padding: 1rem; text-align: left; color: var(--black); font-weight: 600;">Month</th>
                    <th style="padding: 1rem; text-align: center; color: var(--black); font-weight: 600;">Enrollments</th>
                    <th style="padding: 1rem; text-align: right; color: var(--black); font-weight: 600;">Revenue Generated</th>
                    <th style="padding: 1rem; text-align: center; color: var(--black); font-weight: 600;">Trend</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $max_enrollments = max(array_column($enrollment_trends, 'enrollments'));
                foreach ($enrollment_trends as $trend): 
                    $trend_percentage = $max_enrollments > 0 ? ($trend['enrollments'] / $max_enrollments) * 100 : 0;
                ?>
                    <tr style="border-bottom: 1px solid var(--border-gray);">
                        <td style="padding: 1rem; color: var(--black); font-weight: 500;">
                            <?php echo htmlspecialchars($trend['month_name']); ?>
                        </td>
                        <td style="padding: 1rem; text-align: center; color: var(--black);">
                            <span style="background: var(--primary-blue); color: white; padding: 0.25rem 0.75rem; border-radius: 15px; font-weight: 500;">
                                <?php echo number_format($trend['enrollments']); ?>
                            </span>
                        </td>
                        <td style="padding: 1rem; text-align: right; color: var(--dark-blue); font-weight: 600;">
                            ₱<?php echo number_format($trend['month_revenue'], 2); ?>
                        </td>
                        <td style="padding: 1rem; text-align: center;">
                            <div style="background: var(--light-gray); border-radius: 10px; padding: 0.25rem; width: 100px; margin: 0 auto;">
                                <div style="background: var(--primary-blue); height: 15px; border-radius: 8px; width: <?php echo $trend_percentage; ?>%; min-width: 2px;"></div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<div style="margin-top: 2rem; text-align: center;">
    <a href="dashboard.php" class="btn btn-primary">Back to Finance Dashboard</a>
</div>

<script>
function exportToCSV() {
    const table = document.getElementById('detailedReportTable');
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            let cellText = cols[j].innerText.replace(/\n/g, ' ').replace(/,/g, ';');
            row.push('"' + cellText + '"');
        }
        
        csv.push(row.join(','));
    }
    
    const csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
    const downloadLink = document.createElement('a');
    
    downloadLink.download = 'GTBA_Financial_Report_' + new Date().toISOString().slice(0, 10) + '.csv';
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

// Print styles
const printStyles = `
    @media print {
        body * { visibility: hidden; }
        .welcome-section, .welcome-section * { visibility: visible; }
        [style*="background: var(--white)"], [style*="background: var(--white)"] * { visibility: visible; }
        .sidebar { display: none !important; }
        .main-content { margin-left: 0 !important; padding: 0 !important; }
        @page { margin: 1cm; }
    }
`;

const styleSheet = document.createElement('style');
styleSheet.innerHTML = printStyles;
document.head.appendChild(styleSheet);
</script>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
