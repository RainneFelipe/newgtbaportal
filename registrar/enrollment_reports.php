<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth_check.php';
require_once '../config/database.php';

// Check if user is registrar
if ($_SESSION['role'] !== 'registrar') {
    header('Location: ../index.php');
    exit();
}

$database = new Database();
$conn = $database->connect();

$page_title = "Enrollment Reports";
$base_url = "../";

// Get filter parameters
$report_type = $_GET['report_type'] ?? 'overview';
$school_year_filter = $_GET['school_year_filter'] ?? '';
$grade_filter = $_GET['grade_filter'] ?? '';
$section_filter = $_GET['section_filter'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';

try {
    // Get current school year if no filter selected
    if (empty($school_year_filter)) {
        $current_year_stmt = $conn->prepare("SELECT id FROM school_years WHERE is_active = 1 LIMIT 1");
        $current_year_stmt->execute();
        $current_year = $current_year_stmt->fetch(PDO::FETCH_ASSOC);
        if ($current_year) {
            $school_year_filter = $current_year['id'];
        }
    }

    // Get all school years for filter
    $school_years_stmt = $conn->prepare("SELECT * FROM school_years ORDER BY is_active DESC, year_label DESC");
    $school_years_stmt->execute();
    $school_years = $school_years_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all grade levels for filter
    $grade_levels_stmt = $conn->prepare("SELECT * FROM grade_levels WHERE is_active = 1 ORDER BY grade_order");
    $grade_levels_stmt->execute();
    $grade_levels = $grade_levels_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get sections for filter (if grade is selected)
    $sections = [];
    if (!empty($grade_filter)) {
        $sections_stmt = $conn->prepare("SELECT s.* FROM sections s 
                                        WHERE s.grade_level_id = ? AND s.is_active = 1 
                                        ORDER BY s.section_name");
        $sections_stmt->execute([$grade_filter]);
        $sections = $sections_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Build WHERE conditions for reports
    $where_conditions = ["s.is_active = 1"];
    $params = [];

    if (!empty($school_year_filter)) {
        $where_conditions[] = "s.current_school_year_id = ?";
        $params[] = $school_year_filter;
    }

    if (!empty($grade_filter)) {
        $where_conditions[] = "s.current_grade_level_id = ?";
        $params[] = $grade_filter;
    }

    if (!empty($section_filter)) {
        $where_conditions[] = "s.current_section_id = ?";
        $params[] = $section_filter;
    }

    if (!empty($status_filter)) {
        $where_conditions[] = "s.enrollment_status = ?";
        $params[] = $status_filter;
    }

    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

    // Generate reports based on type
    $reports_data = [];

    switch ($report_type) {
        case 'overview':
            // Overall enrollment statistics
            $overview_sql = "SELECT 
                            COUNT(*) as total_students,
                            COUNT(CASE WHEN s.enrollment_status = 'Enrolled' THEN 1 END) as enrolled_students,
                            COUNT(CASE WHEN s.enrollment_status = 'Dropped' THEN 1 END) as dropped_students,
                            COUNT(CASE WHEN s.enrollment_status = 'Transferred' THEN 1 END) as transferred_students,
                            COUNT(CASE WHEN s.enrollment_status = 'Graduated' THEN 1 END) as graduated_students,
                            COUNT(CASE WHEN s.student_type = 'New' THEN 1 END) as new_students,
                            COUNT(CASE WHEN s.student_type = 'Transfer' THEN 1 END) as transfer_students,
                            COUNT(CASE WHEN s.student_type = 'Continuing' THEN 1 END) as continuing_students,
                            COUNT(CASE WHEN s.gender = 'Male' THEN 1 END) as male_students,
                            COUNT(CASE WHEN s.gender = 'Female' THEN 1 END) as female_students
                            FROM students s $where_clause";
            $overview_stmt = $conn->prepare($overview_sql);
            $overview_stmt->execute($params);
            $reports_data['overview'] = $overview_stmt->fetch(PDO::FETCH_ASSOC);
            break;

        case 'by_grade':
            // Enrollment by grade level
            $grade_sql = "SELECT 
                         gl.grade_name,
                         gl.level_type,
                         COUNT(s.id) as total_students,
                         COUNT(CASE WHEN s.enrollment_status = 'Enrolled' THEN 1 END) as enrolled_students,
                         COUNT(CASE WHEN s.gender = 'Male' THEN 1 END) as male_students,
                         COUNT(CASE WHEN s.gender = 'Female' THEN 1 END) as female_students
                         FROM grade_levels gl
                         LEFT JOIN students s ON gl.id = s.current_grade_level_id AND s.is_active = 1";
            
            if (!empty($school_year_filter)) {
                $grade_sql .= " AND s.current_school_year_id = ?";
            }
            if (!empty($status_filter)) {
                $grade_sql .= " AND s.enrollment_status = ?";
            }
            
            $grade_sql .= " WHERE gl.is_active = 1 
                           GROUP BY gl.id, gl.grade_name, gl.level_type, gl.grade_order 
                           ORDER BY gl.grade_order";
            
            $grade_params = [];
            if (!empty($school_year_filter)) $grade_params[] = $school_year_filter;
            if (!empty($status_filter)) $grade_params[] = $status_filter;
            
            $grade_stmt = $conn->prepare($grade_sql);
            $grade_stmt->execute($grade_params);
            $reports_data['by_grade'] = $grade_stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'by_section':
            // Enrollment by section
            $section_sql = "SELECT 
                           sec.section_name,
                           gl.grade_name,
                           COUNT(s.id) as current_enrollment,
                           COUNT(CASE WHEN s.gender = 'Male' THEN 1 END) as male_students,
                           COUNT(CASE WHEN s.gender = 'Female' THEN 1 END) as female_students
                           FROM sections sec
                           LEFT JOIN grade_levels gl ON sec.grade_level_id = gl.id
                           LEFT JOIN students s ON sec.id = s.current_section_id AND s.enrollment_status = 'Enrolled' AND s.is_active = 1";
            
            $section_params = [];
            if (!empty($school_year_filter)) {
                $section_sql .= " AND s.current_school_year_id = ?";
                $section_params[] = $school_year_filter;
            }
            if (!empty($grade_filter)) {
                $section_sql .= " AND sec.grade_level_id = ?";
                $section_params[] = $grade_filter;
            }
            
            $section_sql .= " WHERE sec.is_active = 1
                             GROUP BY sec.id, sec.section_name, gl.grade_name
                             ORDER BY gl.grade_order, sec.section_name";
            
            $section_stmt = $conn->prepare($section_sql);
            $section_stmt->execute($section_params);
            $reports_data['by_section'] = $section_stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'detailed':
            // Detailed student list
            $detailed_sql = "SELECT 
                            s.student_id,
                            s.lrn,
                            s.first_name,
                            s.last_name,
                            s.middle_name,
                            s.gender,
                            s.date_of_birth,
                            s.student_type,
                            s.enrollment_status,
                            gl.grade_name,
                            sec.section_name,
                            sy.year_label,
                            s.created_at as registration_date
                            FROM students s
                            LEFT JOIN grade_levels gl ON s.current_grade_level_id = gl.id
                            LEFT JOIN sections sec ON s.current_section_id = sec.id
                            LEFT JOIN school_years sy ON s.current_school_year_id = sy.id
                            $where_clause
                            ORDER BY gl.grade_order, sec.section_name, s.last_name, s.first_name";
            
            $detailed_stmt = $conn->prepare($detailed_sql);
            $detailed_stmt->execute($params);
            $reports_data['detailed'] = $detailed_stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
    }

    // Get selected school year info for display
    $selected_year = null;
    if (!empty($school_year_filter)) {
        foreach ($school_years as $year) {
            if ($year['id'] == $school_year_filter) {
                $selected_year = $year;
                break;
            }
        }
    }

} catch (Exception $e) {
    $error_message = "Error generating reports: " . $e->getMessage();
    error_log("Enrollment reports error: " . $e->getMessage());
}

include '../includes/header.php';
?>

<div class="main-wrapper">
    <div class="content-wrapper" style="max-width: 1400px; margin: 0 auto; padding: 2rem 1rem;">
        <div class="content-header">
            <div class="header-left">
                <h1><i class="fas fa-chart-bar"></i> Enrollment Reports</h1>
                <p>Generate comprehensive enrollment reports and statistics</p>
                <?php if ($selected_year): ?>
                    <div class="header-stats">
                        <div class="stat-item">
                            <i class="fas fa-calendar"></i>
                            <span>School Year: <strong><?php echo htmlspecialchars($selected_year['year_label']); ?></strong></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
           
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Report Filters -->
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label for="report_type">Report Type</label>
                    <select id="report_type" name="report_type">
                        <option value="overview" <?php echo $report_type === 'overview' ? 'selected' : ''; ?>>Overview Summary</option>
                        <option value="by_grade" <?php echo $report_type === 'by_grade' ? 'selected' : ''; ?>>By Grade Level</option>
                        <option value="by_section" <?php echo $report_type === 'by_section' ? 'selected' : ''; ?>>By Section</option>
                        <option value="detailed" <?php echo $report_type === 'detailed' ? 'selected' : ''; ?>>Detailed Student List</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="school_year_filter">School Year</label>
                    <select id="school_year_filter" name="school_year_filter">
                        <option value="">All School Years</option>
                        <?php foreach ($school_years as $year): ?>
                            <option value="<?php echo $year['id']; ?>" <?php echo $school_year_filter == $year['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($year['year_label']); ?>
                                <?php echo $year['is_active'] ? ' (Current)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="grade_filter">Grade Level</label>
                    <select id="grade_filter" name="grade_filter" onchange="loadSections()">
                        <option value="">All Grades</option>
                        <?php foreach ($grade_levels as $grade): ?>
                            <option value="<?php echo $grade['id']; ?>" <?php echo $grade_filter == $grade['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($grade['grade_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="section_filter">Section</label>
                    <select id="section_filter" name="section_filter">
                        <option value="">All Sections</option>
                        <?php foreach ($sections as $section): ?>
                            <option value="<?php echo $section['id']; ?>" <?php echo $section_filter == $section['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($section['section_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="status_filter">Enrollment Status</label>
                    <select id="status_filter" name="status_filter">
                        <option value="">All Status</option>
                        <option value="Enrolled" <?php echo $status_filter === 'Enrolled' ? 'selected' : ''; ?>>Enrolled</option>
                        <option value="Dropped" <?php echo $status_filter === 'Dropped' ? 'selected' : ''; ?>>Dropped</option>
                        <option value="Graduated" <?php echo $status_filter === 'Graduated' ? 'selected' : ''; ?>>Graduated</option>
                        <option value="Transferred" <?php echo $status_filter === 'Transferred' ? 'selected' : ''; ?>>Transferred</option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-chart-line"></i> Generate Report
                    </button>
                    <a href="enrollment_reports.php" class="btn btn-secondary">
                        <i class="fas fa-refresh"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Report Content -->
        <div class="report-content" id="reportContent">
            
            <?php if ($report_type === 'overview' && isset($reports_data['overview'])): ?>
                <!-- Overview Report -->
                <div class="report-section">
                    <h2 class="report-title">
                        <i class="fas fa-chart-pie"></i> Enrollment Overview
                        <?php if ($selected_year): ?>
                            - <?php echo htmlspecialchars($selected_year['year_label']); ?>
                        <?php endif; ?>
                    </h2>
                    
                    <div class="overview-stats">
                        <div class="stat-card primary">
                            <div class="stat-icon">👥</div>
                            <div class="stat-info">
                                <div class="stat-value"><?php echo number_format($reports_data['overview']['total_students']); ?></div>
                                <div class="stat-label">Total Students</div>
                            </div>
                        </div>
                        
                        <div class="stat-card success">
                            <div class="stat-icon">✅</div>
                            <div class="stat-info">
                                <div class="stat-value"><?php echo number_format($reports_data['overview']['enrolled_students']); ?></div>
                                <div class="stat-label">Currently Enrolled</div>
                            </div>
                        </div>
                        
                        <div class="stat-card warning">
                            <div class="stat-icon">⚠️</div>
                            <div class="stat-info">
                                <div class="stat-value"><?php echo number_format($reports_data['overview']['dropped_students']); ?></div>
                                <div class="stat-label">Dropped</div>
                            </div>
                        </div>
                        
                        <div class="stat-card info">
                            <div class="stat-icon">🔄</div>
                            <div class="stat-info">
                                <div class="stat-value"><?php echo number_format($reports_data['overview']['transferred_students']); ?></div>
                                <div class="stat-label">Transferred</div>
                            </div>
                        </div>
                    </div>

                    <div class="overview-charts">
                        <div class="chart-card">
                            <h3>Student Type Distribution</h3>
                            <div class="chart-data">
                                <div class="chart-item">
                                    <span class="chart-label">New Students</span>
                                    <span class="chart-value"><?php echo $reports_data['overview']['new_students']; ?></span>
                                    <div class="chart-bar">
                                        <div class="chart-fill" style="width: <?php echo $reports_data['overview']['total_students'] > 0 ? ($reports_data['overview']['new_students'] / $reports_data['overview']['total_students'] * 100) : 0; ?>%"></div>
                                    </div>
                                </div>
                                <div class="chart-item">
                                    <span class="chart-label">Transfer Students</span>
                                    <span class="chart-value"><?php echo $reports_data['overview']['transfer_students']; ?></span>
                                    <div class="chart-bar">
                                        <div class="chart-fill" style="width: <?php echo $reports_data['overview']['total_students'] > 0 ? ($reports_data['overview']['transfer_students'] / $reports_data['overview']['total_students'] * 100) : 0; ?>%"></div>
                                    </div>
                                </div>
                                <div class="chart-item">
                                    <span class="chart-label">Continuing Students</span>
                                    <span class="chart-value"><?php echo $reports_data['overview']['continuing_students']; ?></span>
                                    <div class="chart-bar">
                                        <div class="chart-fill" style="width: <?php echo $reports_data['overview']['total_students'] > 0 ? ($reports_data['overview']['continuing_students'] / $reports_data['overview']['total_students'] * 100) : 0; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="chart-card">
                            <h3>Gender Distribution</h3>
                            <div class="chart-data">
                                <div class="chart-item">
                                    <span class="chart-label">Male Students</span>
                                    <span class="chart-value"><?php echo $reports_data['overview']['male_students']; ?></span>
                                    <div class="chart-bar">
                                        <div class="chart-fill male" style="width: <?php echo $reports_data['overview']['total_students'] > 0 ? ($reports_data['overview']['male_students'] / $reports_data['overview']['total_students'] * 100) : 0; ?>%"></div>
                                    </div>
                                </div>
                                <div class="chart-item">
                                    <span class="chart-label">Female Students</span>
                                    <span class="chart-value"><?php echo $reports_data['overview']['female_students']; ?></span>
                                    <div class="chart-bar">
                                        <div class="chart-fill female" style="width: <?php echo $reports_data['overview']['total_students'] > 0 ? ($reports_data['overview']['female_students'] / $reports_data['overview']['total_students'] * 100) : 0; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($report_type === 'by_grade' && isset($reports_data['by_grade'])): ?>
                <!-- By Grade Report -->
                <div class="report-section">
                    <h2 class="report-title">
                        <i class="fas fa-layer-group"></i> Enrollment by Grade Level
                        <?php if ($selected_year): ?>
                            - <?php echo htmlspecialchars($selected_year['year_label']); ?>
                        <?php endif; ?>
                    </h2>
                    
                    <div class="table-container">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Grade Level</th>
                                    <th>Level Type</th>
                                    <th>Total Students</th>
                                    <th>Enrolled</th>
                                    <th>Male</th>
                                    <th>Female</th>
                                    <th>Enrollment Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_all = 0;
                                $total_enrolled = 0;
                                $total_male = 0;
                                $total_female = 0;
                                ?>
                                <?php foreach ($reports_data['by_grade'] as $grade): ?>
                                    <?php 
                                    $total_all += $grade['total_students'];
                                    $total_enrolled += $grade['enrolled_students'];
                                    $total_male += $grade['male_students'];
                                    $total_female += $grade['female_students'];
                                    $enrollment_rate = $grade['total_students'] > 0 ? ($grade['enrolled_students'] / $grade['total_students'] * 100) : 0;
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($grade['grade_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($grade['level_type']); ?></td>
                                        <td><?php echo number_format($grade['total_students']); ?></td>
                                        <td><?php echo number_format($grade['enrolled_students']); ?></td>
                                        <td><?php echo number_format($grade['male_students']); ?></td>
                                        <td><?php echo number_format($grade['female_students']); ?></td>
                                        <td>
                                            <div class="progress-container">
                                                <div class="progress-bar">
                                                    <div class="progress-fill" style="width: <?php echo $enrollment_rate; ?>%"></div>
                                                </div>
                                                <span class="progress-text"><?php echo round($enrollment_rate, 1); ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td colspan="2"><strong>TOTAL</strong></td>
                                    <td><strong><?php echo number_format($total_all); ?></strong></td>
                                    <td><strong><?php echo number_format($total_enrolled); ?></strong></td>
                                    <td><strong><?php echo number_format($total_male); ?></strong></td>
                                    <td><strong><?php echo number_format($total_female); ?></strong></td>
                                    <td><strong><?php echo $total_all > 0 ? round(($total_enrolled / $total_all * 100), 1) : 0; ?>%</strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($report_type === 'by_section' && isset($reports_data['by_section'])): ?>
                <!-- By Section Report -->
                <div class="report-section">
                    <h2 class="report-title">
                        <i class="fas fa-users-class"></i> Enrollment by Section
                        <?php if ($selected_year): ?>
                            - <?php echo htmlspecialchars($selected_year['year_label']); ?>
                        <?php endif; ?>
                    </h2>
                    
                    <div class="table-container">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Section Name</th>
                                    <th>Grade Level</th>
                                    <th>Current Enrollment</th>
                                    <th>Male</th>
                                    <th>Female</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reports_data['by_section'] as $section): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($section['section_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($section['grade_name']); ?></td>
                                        <td><?php echo number_format($section['current_enrollment']); ?></td>
                                        <td><?php echo number_format($section['male_students']); ?></td>
                                        <td><?php echo number_format($section['female_students']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($report_type === 'detailed' && isset($reports_data['detailed'])): ?>
                <!-- Detailed Student List -->
                <div class="report-section">
                    <h2 class="report-title">
                        <i class="fas fa-list-alt"></i> Detailed Student List
                        <?php if ($selected_year): ?>
                            - <?php echo htmlspecialchars($selected_year['year_label']); ?>
                        <?php endif; ?>
                    </h2>
                    
                    <div class="results-summary">
                        <span class="total-count"><?php echo count($reports_data['detailed']); ?> student(s) found</span>
                    </div>
                    
                    <div class="table-container">
                        <table class="report-table detailed">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>LRN</th>
                                    <th>Full Name</th>
                                    <th>Gender</th>
                                    <th>Age</th>
                                    <th>Grade</th>
                                    <th>Section</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Registration Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reports_data['detailed'] as $student): ?>
                                    <?php 
                                    $full_name = trim($student['first_name'] . ' ' . 
                                                    ($student['middle_name'] ? $student['middle_name'] . ' ' : '') . 
                                                    $student['last_name']);
                                    $age = date_diff(date_create($student['date_of_birth']), date_create('today'))->y;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                        <td><?php echo htmlspecialchars($student['lrn']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($full_name); ?></strong></td>
                                        <td><?php echo htmlspecialchars($student['gender']); ?></td>
                                        <td><?php echo $age; ?></td>
                                        <td><?php echo htmlspecialchars($student['grade_name'] ?: 'Not assigned'); ?></td>
                                        <td><?php echo htmlspecialchars($student['section_name'] ?: 'Not assigned'); ?></td>
                                        <td>
                                            <span class="type-badge type-<?php echo strtolower($student['student_type']); ?>">
                                                <?php echo htmlspecialchars($student['student_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($student['enrollment_status']); ?>">
                                                <?php echo htmlspecialchars($student['enrollment_status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($student['registration_date'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-chart-bar"></i>
                    <h3>Select Report Type</h3>
                    <p>Please select a report type and apply filters to generate enrollment reports.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Enrollment Reports Styles */
.filters-card {
    background: var(--white);
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    border: 1px solid var(--border-gray);
}

.filters-form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-group label {
    font-weight: 600;
    color: var(--dark-blue);
    font-size: 0.9rem;
}

.filter-group select {
    padding: 0.75rem;
    border: 2px solid var(--border-gray);
    border-radius: 8px;
    font-size: 0.9rem;
    transition: border-color 0.3s ease;
}

.filter-group select:focus {
    border-color: var(--primary-blue);
    outline: none;
}

.filter-actions {
    display: flex;
    gap: 1rem;
    grid-column: 1 / -1;
    justify-content: center;
    margin-top: 1rem;
}

.report-content {
    background: var(--white);
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    border: 1px solid var(--border-gray);
    overflow: hidden;
}

.report-section {
    padding: 2rem;
}

.report-title {
    color: var(--dark-blue);
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.5rem;
}

.overview-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
}

.stat-card {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    padding: 2rem;
    border-radius: 15px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border-left: 5px solid var(--primary-blue);
}

.stat-card.primary { border-left-color: var(--primary-blue); background: linear-gradient(135deg, #f8faff, #ffffff); }
.stat-card.success { border-left-color: var(--success); background: linear-gradient(135deg, #f0fff4, #ffffff); }
.stat-card.warning { border-left-color: var(--warning); background: linear-gradient(135deg, #fffbf0, #ffffff); }
.stat-card.info { border-left-color: #17a2b8; background: linear-gradient(135deg, #f0f9ff, #ffffff); }

.stat-icon {
    font-size: 2.5rem;
    opacity: 0.8;
}

.stat-value {
    font-size: 2.5rem;
    font-weight: bold;
    color: var(--dark-blue);
    margin-bottom: 0.5rem;
}

.stat-label {
    color: var(--gray);
    font-size: 0.9rem;
    font-weight: 600;
}

.overview-charts {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 2rem;
}

.chart-card {
    background: var(--light-gray);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid var(--border-gray);
}

.chart-card h3 {
    color: var(--dark-blue);
    margin-bottom: 1.5rem;
    text-align: center;
}

.chart-data {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.chart-item {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.chart-label {
    flex: 1;
    font-weight: 600;
    color: var(--black);
}

.chart-value {
    font-weight: bold;
    color: var(--dark-blue);
    min-width: 50px;
    text-align: right;
}

.chart-bar {
    flex: 2;
    height: 10px;
    background: var(--border-gray);
    border-radius: 5px;
    overflow: hidden;
}

.chart-fill {
    height: 100%;
    background: var(--primary-blue);
    transition: width 0.3s ease;
}

.chart-fill.male { background: linear-gradient(90deg, #4285f4, #1976d2); }
.chart-fill.female { background: linear-gradient(90deg, #ea4335, #d32f2f); }
.chart-fill.capacity { background: linear-gradient(90deg, #34a853, #2e7d32); }

.table-container {
    overflow-x: auto;
    border-radius: 12px;
    border: 1px solid var(--border-gray);
}

.report-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.report-table th {
    background: linear-gradient(135deg, var(--dark-blue), var(--primary-blue));
    color: var(--white);
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    border: none;
}

.report-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border-gray);
    vertical-align: middle;
}

.report-table tbody tr:hover {
    background: var(--light-blue);
}

.report-table.detailed th,
.report-table.detailed td {
    padding: 0.75rem 0.5rem;
    font-size: 0.85rem;
}

.total-row {
    background: var(--light-gray);
    font-weight: bold;
}

.progress-container {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.progress-bar {
    flex: 1;
    height: 8px;
    background: var(--border-gray);
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: var(--primary-blue);
    transition: width 0.3s ease;
}

.progress-text {
    font-weight: 600;
    color: var(--dark-blue);
    min-width: 50px;
    text-align: right;
}

.type-badge, .status-badge {
    padding: 0.4rem 0.8rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.type-new { background: linear-gradient(135deg, #3498DB, #5DADE2); color: white; }
.type-transfer { background: linear-gradient(135deg, #FF8F00, #FFB74D); color: white; }
.type-continuing { background: linear-gradient(135deg, #8E24AA, #AB47BC); color: white; }

.status-enrolled { background: linear-gradient(135deg, #27AE60, #2ECC71); color: white; }
.status-dropped { background: linear-gradient(135deg, #E74C3C, #C0392B); color: white; }
.status-graduated { background: linear-gradient(135deg, #3498DB, #2980B9); color: white; }
.status-transferred { background: linear-gradient(135deg, #F39C12, #E67E22); color: white; }

.text-warning { color: var(--warning); font-weight: 600; }
.text-success { color: var(--success); font-weight: 600; }

.results-summary {
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: var(--light-blue);
    border-radius: 8px;
    text-align: center;
}

.total-count {
    font-weight: 600;
    color: var(--dark-blue);
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--gray);
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h3 {
    color: var(--dark-blue);
    margin-bottom: 0.5rem;
}

@media (max-width: 768px) {
    .filters-form {
        grid-template-columns: 1fr;
    }
    
    .overview-stats {
        grid-template-columns: 1fr;
    }
    
    .overview-charts {
        grid-template-columns: 1fr;
    }
    
    .chart-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .filter-actions {
        flex-direction: column;
    }
    
    .header-actions {
        flex-direction: column;
        gap: 0.5rem;
    }
}

@media print {
    .filters-card,
    .header-actions,
    .filter-actions {
        display: none !important;
    }
    
    .report-content {
        box-shadow: none;
        border: none;
    }
}
</style>

<script>
function loadSections() {
    const gradeId = document.getElementById('grade_filter').value;
    const sectionSelect = document.getElementById('section_filter');
    
    // Clear current options except "All Sections"
    sectionSelect.innerHTML = '<option value="">All Sections</option>';
    
    if (gradeId) {
        // Fetch sections for the selected grade
        fetch(`get_sections.php?grade_id=${gradeId}`)
            .then(response => response.json())
            .then(sections => {
                sections.forEach(section => {
                    const option = document.createElement('option');
                    option.value = section.id;
                    option.textContent = section.section_name;
                    sectionSelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Error loading sections:', error);
            });
    }
}

function printReport() {
    window.print();
}

function exportReport() {
    const reportType = document.getElementById('report_type').value;
    const schoolYear = document.getElementById('school_year_filter').value;
    const grade = document.getElementById('grade_filter').value;
    const section = document.getElementById('section_filter').value;
    const status = document.getElementById('status_filter').value;
    
    const params = new URLSearchParams({
        report_type: reportType,
        school_year_filter: schoolYear,
        grade_filter: grade,
        section_filter: section,
        status_filter: status,
        export: 'csv'
    });
    
    window.open(`export_enrollment_report.php?${params.toString()}`, '_blank');
}
</script>

<?php include '../includes/footer.php'; ?>
