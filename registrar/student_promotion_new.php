<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth_check.php';
require_once '../config/database.php';
require_once '../classes/User.php';

// Check if user is registrar
if ($_SESSION['role'] !== 'registrar') {
    header('Location: ../index.php');
    exit();
}

$database = new Database();
$conn = $database->connect();
$user = new User($conn);

$page_title = "Student Promotion & Re-enrollment";
$base_url = "../";

$message = '';
$messageType = '';

// Handle promotion actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'bulk_promote':
                    $current_year_id = $_POST['current_year_id'];
                    $new_year_id = $_POST['new_year_id'];
                    $selected_students = $_POST['selected_students'] ?? [];
                    
                    if (empty($selected_students)) {
                        throw new Exception("Please select at least one student to promote.");
                    }
                    
                    if (empty($new_year_id)) {
                        throw new Exception("Please select a target school year for promotion.");
                    }
                    
                    $conn->beginTransaction();
                    
                    $promoted_count = 0;
                    $promotion_details = [];
                    
                    foreach ($selected_students as $student_data) {
                        list($student_id, $current_grade_id, $new_grade_id) = explode('|', $student_data);
                        
                        // Get student name for logging
                        $student_name_query = "SELECT CONCAT(first_name, ' ', last_name) as full_name FROM students WHERE id = ?";
                        $student_name_stmt = $conn->prepare($student_name_query);
                        $student_name_stmt->execute([$student_id]);
                        $student_name = $student_name_stmt->fetchColumn();
                        
                        // Update student's current information
                        $update_query = "UPDATE students SET 
                                        current_grade_level_id = ?,
                                        current_school_year_id = ?,
                                        current_section_id = NULL,
                                        student_type = 'Continuing',
                                        enrollment_status = 'Enrolled',
                                        updated_at = NOW()
                                        WHERE id = ?";
                        $update_stmt = $conn->prepare($update_query);
                        $update_stmt->execute([$new_grade_id, $new_year_id, $student_id]);
                        
                        // Create new enrollment record for the new school year
                        $enrollment_query = "INSERT INTO student_enrollments 
                                           (student_id, school_year_id, grade_level_id, section_id, 
                                            enrollment_date, enrollment_status, created_by)
                                           VALUES (?, ?, ?, NULL, CURDATE(), 'Enrolled', ?)";
                        $enrollment_stmt = $conn->prepare($enrollment_query);
                        $enrollment_stmt->execute([$student_id, $new_year_id, $new_grade_id, $_SESSION['user_id']]);
                        
                        $promotion_details[] = $student_name;
                        $promoted_count++;
                    }
                    
                    // Audit log
                    try {
                        $audit_query = "INSERT INTO audit_logs (user_id, action, table_name, record_id, details, ip_address) 
                                       VALUES (?, 'bulk_promote', 'students', NULL, ?, ?)";
                        $audit_stmt = $conn->prepare($audit_query);
                        $audit_details = json_encode([
                            'promoted_count' => $promoted_count,
                            'target_year_id' => $new_year_id,
                            'students' => $promotion_details
                        ]);
                        $audit_stmt->execute([$_SESSION['user_id'], $audit_details, $_SERVER['REMOTE_ADDR']]);
                    } catch (Exception $audit_e) {
                        error_log("Audit log error: " . $audit_e->getMessage());
                    }
                    
                    $conn->commit();
                    $message = "Successfully promoted {$promoted_count} student(s) to the new school year!";
                    $messageType = 'success';
                    break;
                    
                case 'individual_promote':
                    $student_id = $_POST['student_id'];
                    $new_grade_id = $_POST['new_grade_id'];
                    $new_year_id = $_POST['new_year_id'];
                    $new_section_id = $_POST['new_section_id'] ?? null;
                    
                    $conn->beginTransaction();
                    
                    // Update student's current information
                    $update_query = "UPDATE students SET 
                                    current_grade_level_id = ?,
                                    current_school_year_id = ?,
                                    current_section_id = ?,
                                    student_type = 'Continuing',
                                    enrollment_status = 'Enrolled',
                                    updated_at = NOW()
                                    WHERE id = ?";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->execute([$new_grade_id, $new_year_id, $new_section_id, $student_id]);
                    
                    // Create new enrollment record
                    $enrollment_query = "INSERT INTO student_enrollments 
                                       (student_id, school_year_id, grade_level_id, section_id, 
                                        enrollment_date, enrollment_status, created_by)
                                       VALUES (?, ?, ?, ?, CURDATE(), 'Enrolled', ?)";
                    $enrollment_stmt = $conn->prepare($enrollment_query);
                    $enrollment_stmt->execute([$student_id, $new_year_id, $new_grade_id, $new_section_id, $_SESSION['user_id']]);
                    
                    $conn->commit();
                    $message = "Student promoted successfully!";
                    $messageType = 'success';
                    break;
            }
        }
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $message = "Error: " . $e->getMessage();
        $messageType = 'error';
    }
}

try {
    // Get current and available school years
    $years_query = "SELECT * FROM school_years ORDER BY is_active DESC, start_date DESC";
    $years_stmt = $conn->prepare($years_query);
    $years_stmt->execute();
    $school_years = $years_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get current active year
    $current_year = null;
    foreach ($school_years as $year) {
        if ($year['is_active']) {
            $current_year = $year;
            break;
        }
    }
    
    // Get grade levels for promotion mapping
    $grades_query = "SELECT * FROM grade_levels WHERE is_active = 1 ORDER BY grade_order";
    $grades_stmt = $conn->prepare($grades_query);
    $grades_stmt->execute();
    $grade_levels = $grades_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get students from previous year that need promotion
    $promotion_filter = $_GET['promotion_year'] ?? '';
    $grade_filter = $_GET['grade_filter'] ?? '';
    
    $students = [];
    if ($promotion_filter) {
        $where_conditions = ["s.enrollment_status = 'Enrolled'", "s.current_school_year_id = ?"];
        $params = [$promotion_filter];
        
        if ($grade_filter) {
            $where_conditions[] = "s.current_grade_level_id = ?";
            $params[] = $grade_filter;
        }
        
        $students_query = "SELECT s.*, 
                          CONCAT(s.first_name, ' ', IFNULL(CONCAT(s.middle_name, ' '), ''), s.last_name) as full_name,
                          gl.grade_name, gl.grade_order, gl.id as current_grade_id,
                          sy.year_label,
                          sec.section_name
                          FROM students s
                          LEFT JOIN grade_levels gl ON s.current_grade_level_id = gl.id
                          LEFT JOIN school_years sy ON s.current_school_year_id = sy.id
                          LEFT JOIN sections sec ON s.current_section_id = sec.id
                          WHERE " . implode(' AND ', $where_conditions) . "
                          ORDER BY gl.grade_order, s.last_name, s.first_name";
        
        $students_stmt = $conn->prepare($students_query);
        $students_stmt->execute($params);
        $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get promotion statistics
    $stats = [];
    if ($promotion_filter) {
        $stats_query = "SELECT 
                        gl.grade_name,
                        COUNT(s.id) as student_count
                        FROM students s
                        JOIN grade_levels gl ON s.current_grade_level_id = gl.id
                        WHERE s.enrollment_status = 'Enrolled' 
                        AND s.current_school_year_id = ?
                        GROUP BY gl.id, gl.grade_name
                        ORDER BY gl.grade_order";
        $stats_stmt = $conn->prepare($stats_query);
        $stats_stmt->execute([$promotion_filter]);
        $stats = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    $error_message = "Error loading promotion data: " . $e->getMessage();
    error_log("Student promotion error: " . $e->getMessage());
}

include '../includes/header.php';
?>

<div class="promotion-app">
    <!-- Hero Header -->
    <div class="hero-header">
        <div class="hero-content">
            <div class="hero-icon">
                <div class="icon-wrapper">
                    <i class="fas fa-graduation-cap"></i>
                </div>
            </div>
            <div class="hero-text">
                <h1>Student Promotion Center</h1>
                <p>Streamline the process of promoting students to the next academic year</p>
                <?php if ($current_year): ?>
                    <div class="active-year">
                        <i class="fas fa-calendar-check"></i>
                        <span>Active School Year: <strong><?php echo htmlspecialchars($current_year['year_label']); ?></strong></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="hero-actions">
            <a href="student_records.php" class="hero-btn secondary">
                <i class="fas fa-users"></i>
                <span>Student Records</span>
            </a>
            <a href="enrollment_reports.php" class="hero-btn primary">
                <i class="fas fa-chart-line"></i>
                <span>Reports</span>
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($message): ?>
        <div class="alert-notification <?php echo $messageType; ?>">
            <div class="alert-icon">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
            </div>
            <div class="alert-content">
                <strong><?php echo $messageType === 'success' ? 'Success!' : 'Error!'; ?></strong>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
            <button class="alert-dismiss" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="promotion-content">
        
        <!-- Step 1: Filter Students -->
        <div class="promotion-step">
            <div class="step-header">
                <div class="step-number">1</div>
                <div class="step-title">
                    <h2>Select Students</h2>
                    <p>Choose the school year and grade level to filter students</p>
                </div>
            </div>
            
            <div class="step-content">
                <form method="GET" class="filter-form">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label for="promotion_year">
                                <i class="fas fa-calendar"></i>
                                From School Year
                            </label>
                            <select id="promotion_year" name="promotion_year" onchange="this.form.submit()" class="modern-select">
                                <option value="">Select school year...</option>
                                <?php foreach ($school_years as $year): ?>
                                    <option value="<?php echo $year['id']; ?>" <?php echo $promotion_filter == $year['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($year['year_label']); ?>
                                        <?php echo $year['is_active'] ? ' (Current)' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="grade_filter">
                                <i class="fas fa-layer-group"></i>
                                Grade Level
                            </label>
                            <select id="grade_filter" name="grade_filter" onchange="this.form.submit()" class="modern-select">
                                <option value="">All grades...</option>
                                <?php foreach ($grade_levels as $grade): ?>
                                    <option value="<?php echo $grade['id']; ?>" <?php echo $grade_filter == $grade['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($grade['grade_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-actions">
                            <a href="student_promotion.php" class="reset-btn">
                                <i class="fas fa-undo"></i>
                                Reset
                            </a>
                        </div>
                    </div>
                    
                    <?php if (!empty($stats)): ?>
                        <div class="grade-stats">
                            <h4>Current Enrollment Summary</h4>
                            <div class="stats-grid">
                                <?php foreach ($stats as $stat): ?>
                                    <div class="stat-card">
                                        <div class="stat-number"><?php echo $stat['student_count']; ?></div>
                                        <div class="stat-label"><?php echo htmlspecialchars($stat['grade_name']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if (!empty($students)): ?>
            <!-- Step 2: Promote Students -->
            <div class="promotion-step">
                <div class="step-header">
                    <div class="step-number">2</div>
                    <div class="step-title">
                        <h2>Promote Students</h2>
                        <p>Select students and choose target school year for promotion</p>
                    </div>
                </div>
                
                <div class="step-content">
                    <form method="POST" id="promotionForm" class="promotion-form">
                        <input type="hidden" name="action" value="bulk_promote">
                        <input type="hidden" name="current_year_id" value="<?php echo $promotion_filter; ?>">
                        
                        <!-- Promotion Controls -->
                        <div class="promotion-controls">
                            <div class="control-left">
                                <div class="form-group">
                                    <label for="new_year_id">
                                        <i class="fas fa-calendar-plus"></i>
                                        Promote to School Year
                                    </label>
                                    <select id="new_year_id" name="new_year_id" required class="modern-select">
                                        <option value="">Select target year...</option>
                                        <?php foreach ($school_years as $year): ?>
                                            <option value="<?php echo $year['id']; ?>" <?php echo $year['is_active'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($year['year_label']); ?>
                                                <?php echo $year['is_active'] ? ' (Current)' : ''; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="control-right">
                                <div class="selection-actions">
                                    <button type="button" onclick="selectAll()" class="select-btn">
                                        <i class="fas fa-check-square"></i>
                                        Select All
                                    </button>
                                    <button type="button" onclick="clearAll()" class="select-btn">
                                        <i class="fas fa-square"></i>
                                        Clear All
                                    </button>
                                </div>
                                <div class="selected-info">
                                    <span class="selected-count">0</span> students selected
                                </div>
                            </div>
                        </div>

                        <!-- Students Cards -->
                        <div class="students-container">
                            <div class="students-header">
                                <h3>Students Available for Promotion</h3>
                                <span class="total-count"><?php echo count($students); ?> students found</span>
                            </div>
                            
                            <div class="students-grid">
                                <?php foreach ($students as $student): ?>
                                    <?php
                                    // Determine next grade level
                                    $next_grade = null;
                                    foreach ($grade_levels as $i => $grade) {
                                        if ($grade['id'] == $student['current_grade_id']) {
                                            if (isset($grade_levels[$i + 1])) {
                                                $next_grade = $grade_levels[$i + 1];
                                            }
                                            break;
                                        }
                                    }
                                    ?>
                                    <div class="student-card <?php echo $next_grade ? 'promotable' : 'graduating'; ?>" 
                                         data-student-id="<?php echo $student['id']; ?>">
                                        
                                        <?php if ($next_grade): ?>
                                            <div class="card-checkbox">
                                                <input type="checkbox" 
                                                       id="student_<?php echo $student['id']; ?>"
                                                       name="selected_students[]" 
                                                       value="<?php echo $student['id'] . '|' . $student['current_grade_id'] . '|' . $next_grade['id']; ?>"
                                                       class="student-checkbox">
                                                <label for="student_<?php echo $student['id']; ?>"></label>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="card-content">
                                            <div class="student-info">
                                                <div class="student-avatar">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                                <div class="student-details">
                                                    <h4 class="student-name"><?php echo htmlspecialchars($student['full_name']); ?></h4>
                                                    <div class="student-meta">
                                                        <span class="meta-item">
                                                            <i class="fas fa-id-card"></i>
                                                            <?php echo htmlspecialchars($student['student_id']); ?>
                                                        </span>
                                                        <span class="meta-item">
                                                            <i class="fas fa-barcode"></i>
                                                            <?php echo htmlspecialchars($student['lrn']); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="promotion-info">
                                                <div class="current-grade">
                                                    <span class="grade-label">Current</span>
                                                    <span class="grade-badge current"><?php echo htmlspecialchars($student['grade_name']); ?></span>
                                                    <?php if ($student['section_name']): ?>
                                                        <span class="section-info"><?php echo htmlspecialchars($student['section_name']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="promotion-arrow">
                                                    <i class="fas fa-arrow-right"></i>
                                                </div>
                                                
                                                <div class="next-grade">
                                                    <?php if ($next_grade): ?>
                                                        <span class="grade-label">Promote to</span>
                                                        <span class="grade-badge next"><?php echo htmlspecialchars($next_grade['grade_name']); ?></span>
                                                        <span class="section-info">Section TBA</span>
                                                    <?php else: ?>
                                                        <span class="graduation-status">
                                                            <i class="fas fa-graduation-cap"></i>
                                                            Ready for Graduation
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="card-status">
                                            <?php if ($next_grade): ?>
                                                <span class="status-badge ready">
                                                    <i class="fas fa-check"></i>
                                                    Ready for Promotion
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge graduating">
                                                    <i class="fas fa-graduation-cap"></i>
                                                    Graduating Student
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Promotion Action -->
                        <div class="promotion-action">
                            <button type="submit" class="promote-btn" disabled>
                                <i class="fas fa-graduation-cap"></i>
                                <span>Promote Selected Students</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        <?php elseif ($promotion_filter): ?>
            <!-- No Students Found -->
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-search"></i>
                </div>
                <h3>No Students Found</h3>
                <p>No students found matching the selected criteria.</p>
                <div class="empty-actions">
                    <a href="student_promotion.php" class="btn-secondary">
                        <i class="fas fa-filter"></i>
                        Adjust Filters
                    </a>
                    <a href="student_records.php" class="btn-primary">
                        <i class="fas fa-users"></i>
                        View All Students
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Welcome State -->
            <div class="welcome-state">
                <div class="welcome-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h3>Welcome to Student Promotion Center</h3>
                <p>Select a school year and grade level from the filters above to begin promoting students.</p>
                <div class="welcome-features">
                    <div class="feature-item">
                        <i class="fas fa-filter"></i>
                        <span>Filter by year and grade</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-users"></i>
                        <span>Bulk student promotion</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-arrow-up"></i>
                        <span>Automatic grade progression</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
    </div>
</div>

<style>
/* Modern Promotion App Styles */
:root {
    --primary: #3b82f6;
    --primary-dark: #2563eb;
    --primary-light: #93c5fd;
    --secondary: #64748b;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --info: #06b6d4;
    
    --gray-50: #f8fafc;
    --gray-100: #f1f5f9;
    --gray-200: #e2e8f0;
    --gray-300: #cbd5e1;
    --gray-400: #94a3b8;
    --gray-500: #64748b;
    --gray-600: #475569;
    --gray-700: #334155;
    --gray-800: #1e293b;
    --gray-900: #0f172a;
    
    --white: #ffffff;
    --border-radius: 16px;
    --border-radius-lg: 24px;
    --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
    --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
    --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
    --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
}

.promotion-app {
    min-height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 2rem 1rem;
}

/* Hero Header */
.hero-header {
    max-width: 1200px;
    margin: 0 auto 2rem;
    background: var(--white);
    border-radius: var(--border-radius-lg);
    padding: 3rem 2rem;
    box-shadow: var(--shadow-xl);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 2rem;
}

.hero-content {
    display: flex;
    align-items: center;
    gap: 2rem;
}

.hero-icon .icon-wrapper {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-radius: var(--border-radius);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
    font-size: 2rem;
    box-shadow: var(--shadow-lg);
}

.hero-text h1 {
    margin: 0 0 0.5rem 0;
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--gray-900);
    line-height: 1.2;
}

.hero-text p {
    margin: 0 0 1rem 0;
    color: var(--gray-600);
    font-size: 1.125rem;
    line-height: 1.6;
}

.active-year {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--success);
    color: var(--white);
    padding: 0.75rem 1.25rem;
    border-radius: 50px;
    font-size: 0.875rem;
    font-weight: 600;
    box-shadow: var(--shadow);
}

.hero-actions {
    display: flex;
    gap: 1rem;
}

.hero-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: 50px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
    box-shadow: var(--shadow);
}

.hero-btn.primary {
    background: var(--primary);
    color: var(--white);
}

.hero-btn.primary:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.hero-btn.secondary {
    background: var(--gray-100);
    color: var(--gray-700);
}

.hero-btn.secondary:hover {
    background: var(--gray-200);
    transform: translateY(-2px);
}

/* Alert Notification */
.alert-notification {
    max-width: 1200px;
    margin: 0 auto 2rem;
    padding: 1rem 1.5rem;
    border-radius: var(--border-radius);
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: var(--shadow);
}

.alert-notification.success {
    background: var(--success);
    color: var(--white);
}

.alert-notification.error {
    background: var(--danger);
    color: var(--white);
}

.alert-icon {
    font-size: 1.25rem;
}

.alert-content {
    flex: 1;
}

.alert-content strong {
    display: block;
    margin-bottom: 0.25rem;
}

.alert-dismiss {
    background: none;
    border: none;
    color: inherit;
    font-size: 1.125rem;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 50%;
    transition: background-color 0.2s;
}

.alert-dismiss:hover {
    background: rgba(255, 255, 255, 0.2);
}

/* Main Content */
.promotion-content {
    max-width: 1200px;
    margin: 0 auto;
}

/* Promotion Steps */
.promotion-step {
    background: var(--white);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-lg);
    margin-bottom: 2rem;
    overflow: hidden;
}

.step-header {
    background: linear-gradient(135deg, var(--gray-50), var(--gray-100));
    padding: 2rem;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    border-bottom: 1px solid var(--gray-200);
}

.step-number {
    width: 48px;
    height: 48px;
    background: var(--primary);
    color: var(--white);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    font-weight: 700;
    box-shadow: var(--shadow);
}

.step-title h2 {
    margin: 0 0 0.5rem 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gray-900);
}

.step-title p {
    margin: 0;
    color: var(--gray-600);
    font-size: 1rem;
}

.step-content {
    padding: 2rem;
}

/* Filter Form */
.filter-grid {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: 1.5rem;
    align-items: end;
    margin-bottom: 2rem;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-group label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: var(--gray-700);
    font-size: 0.875rem;
}

.modern-select {
    padding: 0.75rem 1rem;
    border: 2px solid var(--gray-200);
    border-radius: var(--border-radius);
    font-size: 1rem;
    background: var(--white);
    color: var(--gray-900);
    transition: all 0.3s ease;
}

.modern-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.reset-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    background: var(--gray-100);
    color: var(--gray-700);
    border-radius: var(--border-radius);
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s;
}

.reset-btn:hover {
    background: var(--gray-200);
}

/* Grade Stats */
.grade-stats {
    background: var(--gray-50);
    padding: 1.5rem;
    border-radius: var(--border-radius);
    border: 1px solid var(--gray-200);
}

.grade-stats h4 {
    margin: 0 0 1rem 0;
    color: var(--gray-900);
    font-size: 1.125rem;
    font-weight: 600;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 1rem;
}

.stat-card {
    background: var(--white);
    padding: 1rem;
    border-radius: var(--border-radius);
    text-align: center;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--gray-200);
}

.stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.875rem;
    color: var(--gray-600);
    font-weight: 500;
}

/* Promotion Controls */
.promotion-controls {
    display: flex;
    justify-content: space-between;
    align-items: end;
    gap: 2rem;
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: var(--gray-50);
    border-radius: var(--border-radius);
    border: 1px solid var(--gray-200);
}

.control-left .form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.control-left label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: var(--gray-700);
    font-size: 0.875rem;
}

.control-right {
    display: flex;
    flex-direction: column;
    align-items: end;
    gap: 1rem;
}

.selection-actions {
    display: flex;
    gap: 0.5rem;
}

.select-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: var(--gray-100);
    color: var(--gray-700);
    border: none;
    border-radius: var(--border-radius);
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.select-btn:hover {
    background: var(--gray-200);
}

.selected-info {
    color: var(--gray-600);
    font-size: 0.875rem;
    font-weight: 500;
}

/* Students Container */
.students-container {
    margin-bottom: 2rem;
}

.students-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--gray-200);
}

.students-header h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--gray-900);
}

.total-count {
    color: var(--gray-600);
    font-size: 0.875rem;
    font-weight: 500;
}

/* Students Grid */
.students-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 1.5rem;
}

.student-card {
    background: var(--white);
    border: 2px solid var(--gray-200);
    border-radius: var(--border-radius);
    padding: 1.5rem;
    transition: all 0.3s ease;
    position: relative;
    cursor: pointer;
}

.student-card:hover {
    border-color: var(--primary-light);
    box-shadow: var(--shadow-lg);
    transform: translateY(-2px);
}

.student-card.selected {
    border-color: var(--primary);
    background: rgba(59, 130, 246, 0.02);
}

.student-card.graduating {
    border-color: var(--warning);
    background: rgba(245, 158, 11, 0.02);
}

.card-checkbox {
    position: absolute;
    top: 1rem;
    right: 1rem;
}

.card-checkbox input[type="checkbox"] {
    width: 20px;
    height: 20px;
    accent-color: var(--primary);
}

.student-info {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.student-avatar {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--primary-light), var(--primary));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
    font-size: 1.25rem;
}

.student-details {
    flex: 1;
}

.student-name {
    margin: 0 0 0.5rem 0;
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--gray-900);
}

.student-meta {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--gray-600);
    font-size: 0.75rem;
}

.promotion-info {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--gray-50);
    border-radius: var(--border-radius);
    margin-bottom: 1rem;
}

.current-grade, .next-grade {
    text-align: center;
}

.grade-label {
    display: block;
    font-size: 0.75rem;
    color: var(--gray-500);
    font-weight: 500;
    margin-bottom: 0.25rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.grade-badge {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.875rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.grade-badge.current {
    background: var(--secondary);
    color: var(--white);
}

.grade-badge.next {
    background: var(--success);
    color: var(--white);
}

.section-info {
    font-size: 0.75rem;
    color: var(--gray-500);
    font-weight: 500;
}

.promotion-arrow {
    color: var(--primary);
    font-size: 1.25rem;
}

.graduation-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--warning);
    font-size: 0.875rem;
    font-weight: 600;
}

.card-status {
    text-align: center;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.status-badge.ready {
    background: var(--success);
    color: var(--white);
}

.status-badge.graduating {
    background: var(--warning);
    color: var(--white);
}

/* Promotion Action */
.promotion-action {
    text-align: center;
    padding: 2rem;
    background: var(--gray-50);
    border-radius: var(--border-radius);
    border: 1px solid var(--gray-200);
}

.promote-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 2rem;
    background: var(--primary);
    color: var(--white);
    border: none;
    border-radius: 50px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: var(--shadow-lg);
}

.promote-btn:hover:not(:disabled) {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: var(--shadow-xl);
}

.promote-btn:disabled {
    background: var(--gray-300);
    color: var(--gray-500);
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* Empty and Welcome States */
.empty-state, .welcome-state {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--white);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow);
}

.empty-icon, .welcome-icon {
    width: 80px;
    height: 80px;
    background: var(--gray-100);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    color: var(--gray-400);
    font-size: 2rem;
}

.empty-state h3, .welcome-state h3 {
    margin: 0 0 1rem 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gray-900);
}

.empty-state p, .welcome-state p {
    margin: 0 0 2rem 0;
    color: var(--gray-600);
    font-size: 1rem;
    line-height: 1.6;
}

.empty-actions {
    display: flex;
    justify-content: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.btn-primary, .btn-secondary {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: var(--shadow);
}

.btn-primary {
    background: var(--primary);
    color: var(--white);
}

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
}

.btn-secondary {
    background: var(--gray-100);
    color: var(--gray-700);
}

.btn-secondary:hover {
    background: var(--gray-200);
}

.welcome-features {
    display: flex;
    justify-content: center;
    gap: 2rem;
    flex-wrap: wrap;
    margin-top: 2rem;
}

.feature-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--gray-600);
    font-size: 0.875rem;
    font-weight: 500;
}

.feature-item i {
    color: var(--primary);
}

/* Responsive Design */
@media (max-width: 768px) {
    .promotion-app {
        padding: 1rem 0.5rem;
    }
    
    .hero-header {
        flex-direction: column;
        text-align: center;
        padding: 2rem 1rem;
    }
    
    .hero-text h1 {
        font-size: 2rem;
    }
    
    .filter-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .promotion-controls {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }
    
    .control-right {
        align-items: stretch;
    }
    
    .students-grid {
        grid-template-columns: 1fr;
    }
    
    .promotion-info {
        grid-template-columns: 1fr;
        gap: 0.5rem;
        text-align: center;
    }
    
    .promotion-arrow {
        transform: rotate(90deg);
    }
    
    .welcome-features {
        flex-direction: column;
        align-items: center;
        gap: 1rem;
    }
}

/* Animation */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.student-card {
    animation: fadeIn 0.3s ease-out;
}

.alert-notification {
    animation: fadeIn 0.5s ease-out;
}

/* Focus and accessibility */
.modern-select:focus,
.student-checkbox:focus,
.promote-btn:focus,
.select-btn:focus {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

/* Loading state */
.promote-btn:disabled {
    position: relative;
}

.promote-btn:disabled::after {
    content: '';
    position: absolute;
    left: 50%;
    top: 50%;
    margin: -10px 0 0 -10px;
    width: 20px;
    height: 20px;
    border: 2px solid transparent;
    border-top-color: var(--gray-500);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
</style>

<script>
// Enhanced JavaScript for the promotion system
document.addEventListener('DOMContentLoaded', function() {
    initializePromotionSystem();
});

function initializePromotionSystem() {
    setupStudentCardInteractions();
    setupFormValidation();
    setupSelectionControls();
    updatePromoteButtonState();
}

function setupStudentCardInteractions() {
    const studentCards = document.querySelectorAll('.student-card');
    
    studentCards.forEach(card => {
        const checkbox = card.querySelector('.student-checkbox');
        
        if (checkbox) {
            // Click card to toggle checkbox
            card.addEventListener('click', function(e) {
                if (e.target.type !== 'checkbox') {
                    checkbox.checked = !checkbox.checked;
                    checkbox.dispatchEvent(new Event('change'));
                }
            });
            
            // Update card appearance based on selection
            checkbox.addEventListener('change', function() {
                card.classList.toggle('selected', this.checked);
                updateSelectedCount();
                updatePromoteButtonState();
            });
        }
    });
}

function setupSelectionControls() {
    // Select All functionality
    window.selectAll = function() {
        const checkboxes = document.querySelectorAll('.student-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = true;
            cb.dispatchEvent(new Event('change'));
        });
    };
    
    // Clear All functionality
    window.clearAll = function() {
        const checkboxes = document.querySelectorAll('.student-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = false;
            cb.dispatchEvent(new Event('change'));
        });
    };
}

function updateSelectedCount() {
    const selectedCount = document.querySelectorAll('.student-checkbox:checked').length;
    const countElements = document.querySelectorAll('.selected-count');
    
    countElements.forEach(element => {
        element.textContent = selectedCount;
    });
}

function updatePromoteButtonState() {
    const promoteBtn = document.querySelector('.promote-btn');
    const selectedCount = document.querySelectorAll('.student-checkbox:checked').length;
    const targetYear = document.getElementById('new_year_id')?.value;
    
    if (promoteBtn) {
        const isEnabled = selectedCount > 0 && targetYear;
        promoteBtn.disabled = !isEnabled;
        
        if (isEnabled) {
            promoteBtn.innerHTML = `
                <i class="fas fa-graduation-cap"></i>
                <span>Promote ${selectedCount} Student${selectedCount !== 1 ? 's' : ''}</span>
            `;
        } else {
            promoteBtn.innerHTML = `
                <i class="fas fa-graduation-cap"></i>
                <span>Select Students to Promote</span>
            `;
        }
    }
}

function setupFormValidation() {
    const form = document.getElementById('promotionForm');
    const targetYearSelect = document.getElementById('new_year_id');
    
    if (targetYearSelect) {
        targetYearSelect.addEventListener('change', updatePromoteButtonState);
    }
    
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const selectedCount = document.querySelectorAll('.student-checkbox:checked').length;
            const targetYear = document.getElementById('new_year_id')?.value;
            const targetYearText = document.getElementById('new_year_id')?.selectedOptions[0]?.text || '';
            
            if (selectedCount === 0) {
                showNotification('Please select at least one student to promote.', 'error');
                return false;
            }
            
            if (!targetYear) {
                showNotification('Please select a target school year for promotion.', 'error');
                return false;
            }
            
            // Enhanced confirmation dialog
            const confirmed = confirm(`
Promotion Confirmation

Students to promote: ${selectedCount}
Target school year: ${targetYearText}

This action will:
• Update student grade levels
• Create new enrollment records
• Mark students as "Continuing"
• Reset section assignments

Are you sure you want to proceed?
            `.trim());
            
            if (confirmed) {
                setLoadingState(true);
                showNotification('Processing student promotions...', 'info');
                this.submit();
            }
        });
    }
}

function setLoadingState(loading) {
    const promoteBtn = document.querySelector('.promote-btn');
    
    if (promoteBtn) {
        if (loading) {
            promoteBtn.disabled = true;
            promoteBtn.innerHTML = `
                <i class="fas fa-spinner fa-spin"></i>
                <span>Processing...</span>
            `;
        } else {
            updatePromoteButtonState();
        }
    }
}

function showNotification(message, type) {
    // Remove existing notifications
    const existing = document.querySelector('.alert-notification');
    if (existing) {
        existing.remove();
    }
    
    // Create new notification
    const notification = document.createElement('div');
    notification.className = `alert-notification ${type}`;
    notification.innerHTML = `
        <div class="alert-icon">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'}"></i>
        </div>
        <div class="alert-content">
            <span>${message}</span>
        </div>
        <button class="alert-dismiss" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Insert after hero header
    const heroHeader = document.querySelector('.hero-header');
    if (heroHeader && heroHeader.nextSibling) {
        heroHeader.parentNode.insertBefore(notification, heroHeader.nextSibling);
    } else {
        document.querySelector('.promotion-app').appendChild(notification);
    }
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

// Smooth scroll functionality
function scrollToStudents() {
    const studentsContainer = document.querySelector('.students-container');
    if (studentsContainer) {
        studentsContainer.scrollIntoView({ 
            behavior: 'smooth', 
            block: 'start' 
        });
    }
}

// Auto-scroll to students if they are loaded
if (document.querySelector('.students-grid')) {
    setTimeout(scrollToStudents, 500);
}
</script>

<?php include '../includes/footer.php'; ?>
