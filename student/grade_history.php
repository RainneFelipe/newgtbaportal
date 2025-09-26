<?php
require_once '../includes/auth_check.php';
require_once '../config/database.php';

// Check if user is a student
if (!checkRole('student')) {
    header('Location: ../index.php');
    exit();
}

$student_id = $_SESSION['user_id'];

try {
    $database = new Database();
    $pdo = $database->connect();
} catch (Exception $e) {
    echo "Database connection error: " . $e->getMessage();
    exit();
}

// Get student information
try {
    $stmt = $pdo->prepare("
        SELECT s.*, u.username 
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.user_id = ?
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        throw new Exception("Student record not found");
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit();
}

// Get all school years where this student has grades (excluding current year)
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT sy.id, sy.year_label, sy.start_date, sy.end_date
        FROM student_grades sg
        JOIN school_years sy ON sg.school_year_id = sy.id
        WHERE sg.student_id = ? AND sy.is_current = 0
        ORDER BY sy.start_date DESC
    ");
    $stmt->execute([$student['id']]);
    $historical_years = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $historical_years = [];
}

// Get selected school year (default to first available)
$selected_year_id = $_GET['year'] ?? ($historical_years[0]['id'] ?? null);

$historical_grades = [];
$grade_summary = [];

if ($selected_year_id) {
    try {
        // Get grades for selected year
        $stmt = $pdo->prepare("
            SELECT 
                sg.*,
                sub.subject_code,
                sub.subject_name,
                sy.year_label,
                t.first_name as teacher_first_name,
                t.last_name as teacher_last_name
            FROM student_grades sg
            JOIN subjects sub ON sg.subject_id = sub.id
            JOIN school_years sy ON sg.school_year_id = sy.id
            LEFT JOIN teachers t ON sg.teacher_id = t.user_id
            WHERE sg.student_id = ? AND sg.school_year_id = ?
            ORDER BY sub.subject_code
        ");
        $stmt->execute([$student['id'], $selected_year_id]);
        $historical_grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get grade level information for the selected year
        $grade_level_info = null;
        if (!empty($historical_grades)) {
            // Get grade level from curriculum based on subjects
            $curriculum_stmt = $pdo->prepare("
                SELECT DISTINCT gl.grade_name 
                FROM curriculum c
                JOIN grade_levels gl ON c.grade_level_id = gl.id
                WHERE c.subject_id = ? AND c.school_year_id = ?
                LIMIT 1
            ");
            $curriculum_stmt->execute([$historical_grades[0]['subject_id'], $selected_year_id]);
            $grade_level_info = $curriculum_stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // Calculate grade summary
        if (!empty($historical_grades)) {
            $total_grades = 0;
            $total_subjects = 0;
            $passed_subjects = 0;
            
            foreach ($historical_grades as $grade) {
                if ($grade['final_grade'] !== null) {
                    $total_grades += $grade['final_grade'];
                    $total_subjects++;
                    if ($grade['remarks'] === 'Passed') {
                        $passed_subjects++;
                    }
                }
            }
            
            $grade_summary = [
                'average' => $total_subjects > 0 ? round($total_grades / $total_subjects, 2) : 0,
                'total_subjects' => $total_subjects,
                'passed_subjects' => $passed_subjects,
                'failed_subjects' => $total_subjects - $passed_subjects,
                'year_label' => $historical_grades[0]['year_label'] ?? '',
                'grade_name' => $grade_level_info['grade_name'] ?? 'Unknown Grade'
            ];
        }
        
    } catch (Exception $e) {
        $error_message = "Error loading grade history: " . $e->getMessage();
    }
}

include '../includes/header.php';

$page_title = 'Grade History - GTBA Portal';
$base_url = '../';

ob_start();
?>

<!-- Print Header - Only visible when printing -->
<div class="print-header">
    <h1>GOLDEN TREASURE BAPTIST ACADEMY</h1>
    <h2>STUDENT GRADE HISTORY REPORT</h2>
    <p>An Academic Report of Past Performance</p>
    <p>Generated on: <?= date('F d, Y') ?> at <?= date('g:i A') ?></p>
</div>

<!-- Print Student Info - Only visible when printing -->
<div class="print-student-info">
    <h3>STUDENT INFORMATION</h3>
    <p><strong>Student ID:</strong> <?= htmlspecialchars($student['student_id'] ?? 'N/A') ?></p>
    <p><strong>Name:</strong> <?= htmlspecialchars(trim(($student['first_name'] ?? '') . ' ' . ($student['middle_name'] ?? '') . ' ' . ($student['last_name'] ?? ''))) ?></p>
    <p><strong>LRN:</strong> <?= htmlspecialchars($student['lrn'] ?? 'N/A') ?></p>
    <p><strong>Username:</strong> <?= htmlspecialchars($student['username'] ?? 'N/A') ?></p>
</div>

<div class="welcome-section">
    <h1 class="welcome-title">📚 Grade History</h1>
    <p class="welcome-subtitle">View your academic performance from previous school years</p>
</div>

<?php if (isset($error_message)): ?>
    <div style="padding: 1rem; background: var(--danger-light); border: 1px solid var(--danger); border-radius: 8px; margin-bottom: 2rem;">
        <p style="color: var(--danger-dark); margin: 0;">
            <strong>⚠️ Error:</strong> <?= htmlspecialchars($error_message) ?>
        </p>
    </div>
<?php endif; ?>

<?php if (empty($historical_years)): ?>
    <div style="padding: 2rem; background: var(--light-blue); border: 1px solid var(--primary-blue); border-radius: 10px; text-align: center;">
        <h3 style="color: var(--primary-blue); margin-bottom: 1rem;">📋 No Grade History Available</h3>
        <p style="color: var(--black); margin-bottom: 1rem;">
            You don't have any grades from previous school years yet.
        </p>
        <p style="color: var(--gray); margin: 0; font-size: 0.9rem;">
            Your grade history will appear here once you complete a school year.
        </p>
        <div style="margin-top: 1.5rem;">
            <a href="grades.php" style="display: inline-block; padding: 0.75rem 1.5rem; background: var(--primary-blue); color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">
                📊 View Current Grades
            </a>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($historical_years as $year): ?>
        <?php
        // Get grades for this specific year
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    sg.*,
                    sub.subject_code,
                    sub.subject_name,
                    sy.year_label,
                    t.first_name as teacher_first_name,
                    t.last_name as teacher_last_name,
                    c.is_required
                FROM student_grades sg
                JOIN subjects sub ON sg.subject_id = sub.id
                JOIN school_years sy ON sg.school_year_id = sy.id
                LEFT JOIN teachers t ON sg.teacher_id = t.user_id
                LEFT JOIN curriculum c ON c.subject_id = sub.id AND c.school_year_id = sy.id
                WHERE sg.student_id = ? AND sg.school_year_id = ?
                ORDER BY sub.subject_code
            ");
            $stmt->execute([$student['id'], $year['id']]);
            $year_grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get grade level information for this year
            $grade_level_info = null;
            if (!empty($year_grades)) {
                $curriculum_stmt = $pdo->prepare("
                    SELECT DISTINCT gl.grade_name 
                    FROM curriculum c
                    JOIN grade_levels gl ON c.grade_level_id = gl.id
                    WHERE c.subject_id = ? AND c.school_year_id = ?
                    LIMIT 1
                ");
                $curriculum_stmt->execute([$year_grades[0]['subject_id'], $year['id']]);
                $grade_level_info = $curriculum_stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            // Calculate summary statistics
            $total_grades = 0;
            $graded_count = 0;
            $passed_count = 0;
            
            foreach ($year_grades as $grade) {
                if ($grade['final_grade'] !== null) {
                    $total_grades += $grade['final_grade'];
                    $graded_count++;
                    if ($grade['remarks'] === 'Passed') {
                        $passed_count++;
                    }
                }
            }
            
            $average = $graded_count > 0 ? $total_grades / $graded_count : 0;
            
        } catch (Exception $e) {
            $year_grades = [];
            $grade_level_info = null;
            $average = 0;
            $graded_count = 0;
            $passed_count = 0;
        }
        ?>
        
        <?php if (!empty($year_grades)): ?>
            <div class="grade-year-section" style="background: var(--white); border-radius: 15px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
                <div class="year-header" style="margin-bottom: 1.5rem;">
                    <h3 style="color: var(--dark-blue); margin-bottom: 0.5rem;">📊 Academic Performance</h3>
                    <p style="color: var(--gray); margin: 0;">
                        School Year: <strong><?= htmlspecialchars($year['year_label']) ?></strong>
                        <?php if ($grade_level_info): ?>
                            | Grade Level: <strong><?= htmlspecialchars($grade_level_info['grade_name']) ?></strong>
                        <?php endif; ?>
                    </p>
                </div>
                
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: var(--light-blue); border-bottom: 2px solid var(--primary-blue);">
                                <th style="padding: 1rem; text-align: center; color: var(--black); font-weight: 600;">Subject Code</th>
                                <th style="padding: 1rem; text-align: left; color: var(--black); font-weight: 600;">Subject Name</th>
                                <th style="padding: 1rem; text-align: left; color: var(--black); font-weight: 600;">Teacher</th>
                                <th style="padding: 1rem; text-align: center; color: var(--black); font-weight: 600;">Final Grade</th>
                                <th style="padding: 1rem; text-align: center; color: var(--black); font-weight: 600;">Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($year_grades as $grade): ?>
                                <tr style="border-bottom: 1px solid var(--border-gray);">
                                    <td style="padding: 1rem; color: var(--black); font-weight: 500; text-align: center;">
                                        <?= htmlspecialchars($grade['subject_code']) ?>
                                        <?php if ($grade['is_required']): ?>
                                            <span style="color: var(--danger); font-size: 0.8rem; margin-left: 0.5rem;">*</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 1rem; color: var(--black);">
                                        <?= htmlspecialchars($grade['subject_name']) ?>
                                    </td>
                                    <td style="padding: 1rem; color: var(--black);">
                                        <?php 
                                        if ($grade['teacher_first_name'] && $grade['teacher_last_name']) {
                                            echo htmlspecialchars($grade['teacher_first_name'] . ' ' . $grade['teacher_last_name']);
                                        } else {
                                            echo '<span style="color: var(--gray); font-style: italic;">Not assigned</span>';
                                        }
                                        ?>
                                    </td>
                                    <td style="padding: 1rem; text-align: center; font-weight: 600; font-size: 1.1rem;">
                                        <?php if ($grade['final_grade'] !== null): ?>
                                            <?php 
                                            $grade_value = floatval($grade['final_grade']);
                                            $grade_color = $grade_value >= 75 ? 'var(--success)' : 'var(--danger)';
                                            ?>
                                            <span style="color: <?= $grade_color ?>;">
                                                <?= number_format($grade_value, 2) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: var(--gray); background: var(--light-gray); padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.9rem;">
                                                Not yet graded
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 1rem; text-align: center;">
                                        <?php if ($grade['remarks']): ?>
                                            <?php 
                                            $remarks_color = in_array($grade['remarks'], ['Passed']) ? 'var(--success)' : 'var(--danger)';
                                            ?>
                                            <span style="color: <?= $remarks_color ?>; font-weight: 500;">
                                                <?= htmlspecialchars($grade['remarks']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: var(--gray);">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div style="margin-top: 1rem; padding: 1rem; background: var(--light-gray); border-radius: 8px; font-size: 0.9rem; color: var(--gray);">
                    <p style="margin: 0;"><strong>Legend:</strong> <span style="color: var(--danger);">*</span> Required subjects must be completed to advance to the next grade level.</p>
                </div>
                
                <?php if ($graded_count > 0): ?>
                    <div class="print-summary" style="margin-top: 2rem; padding: 1.5rem; background: var(--light-blue); border-radius: 10px;">
                        <h4 style="color: var(--dark-blue); margin-bottom: 0.5rem;">📈 Academic Summary</h4>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                            <div>
                                <p style="color: var(--black); margin: 0.25rem 0;">
                                    <strong>Total Subjects:</strong> <?= count($year_grades) ?>
                                </p>
                                <p style="color: var(--black); margin: 0.25rem 0;">
                                    <strong>Graded Subjects:</strong> <?= $graded_count ?>
                                </p>
                            </div>
                            <div>
                                <p style="color: var(--black); margin: 0.25rem 0;">
                                    <strong>Passed Subjects:</strong> <?= $passed_count ?>
                                </p>
                                <p style="color: var(--black); margin: 0.25rem 0;">
                                    <strong>General Weighted Average (GWA):</strong> 
                                    <span style="font-size: 1.2rem; font-weight: 600; color: <?= $average >= 75 ? 'var(--success)' : 'var(--danger)' ?>;">
                                        <?= number_format($average, 2) ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
    
    <div style="text-align: center; margin-top: 2rem;">
        <button onclick="window.print()" class="print-btn">
            🖨️ Print Grade History
        </button>
    </div>
    
    <!-- Print Footer - Only visible when printing -->
    <div class="print-footer">
        <p>© <?= date('Y') ?> Golden Treasure Baptist Academy. All rights reserved.</p>
        <p>Student Portal System</p>
    </div>
<?php endif; ?>

<style>
/* Screen styles */
.print-btn {
    padding: 0.75rem 1.5rem;
    background: var(--primary-blue);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    transition: all 0.3s ease;
}

.print-btn:hover {
    background: var(--dark-blue);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(46, 134, 171, 0.3);
}

/* Print-specific styles */
@media print {
    /* Hide non-essential elements */
    .sidebar, 
    .header, 
    .main-wrapper nav,
    .print-btn,
    .welcome-section,
    .sidebar-overlay,
    .payment-reminders-banner,
    button,
    .btn {
        display: none !important;
    }
    
    /* Reset page layout */
    body, html {
        background: white !important;
        color: black !important;
        font-family: Arial, sans-serif !important;
        font-size: 12px !important;
        line-height: 1.4 !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    
    .main-content {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        max-width: none !important;
    }
    
    .container {
        margin: 0 !important;
        padding: 15px !important;
        max-width: none !important;
        width: 100% !important;
    }
    
    /* Print header */
    .print-header {
        display: block !important;
        text-align: center;
        border-bottom: 2px solid #000;
        padding-bottom: 15px;
        margin-bottom: 20px;
    }
    
    .print-header h1 {
        font-size: 18px !important;
        font-weight: bold !important;
        margin: 0 0 5px 0 !important;
        color: black !important;
    }
    
    .print-header h2 {
        font-size: 16px !important;
        font-weight: bold !important;
        margin: 0 0 10px 0 !important;
        color: black !important;
    }
    
    .print-header p {
        font-size: 11px !important;
        margin: 2px 0 !important;
        color: black !important;
    }
    
    /* Student info section */
    .print-student-info {
        display: block !important;
        margin-bottom: 20px;
        border: 1px solid #000;
        padding: 10px;
    }
    
    .print-student-info h3 {
        font-size: 14px !important;
        font-weight: bold !important;
        margin: 0 0 8px 0 !important;
        color: black !important;
    }
    
    .print-student-info p {
        font-size: 11px !important;
        margin: 3px 0 !important;
        color: black !important;
    }
    
    /* Grade table styling */
    .grade-year-section {
        page-break-inside: avoid;
        margin-bottom: 25px;
    }
    
    .year-header {
        background: #f0f0f0 !important;
        border: 1px solid #000 !important;
        padding: 8px !important;
        margin-bottom: 10px;
        text-align: center;
    }
    
    .year-header h3 {
        font-size: 14px !important;
        font-weight: bold !important;
        margin: 0 !important;
        color: black !important;
    }
    
    /* Table styling */
    table {
        width: 100% !important;
        border-collapse: collapse !important;
        margin-bottom: 15px !important;
        font-size: 10px !important;
    }
    
    table th {
        background: #e0e0e0 !important;
        border: 1px solid #000 !important;
        padding: 6px 4px !important;
        font-weight: bold !important;
        font-size: 10px !important;
        text-align: center !important;
        color: black !important;
    }
    
    table td {
        border: 1px solid #000 !important;
        padding: 6px 4px !important;
        font-size: 10px !important;
        color: black !important;
        text-align: center !important;
        vertical-align: middle !important;
    }
    
    table td:nth-child(2) {
        text-align: left !important;
    }
    
    table td:nth-child(3) {
        text-align: left !important;
    }
    
    /* Summary section */
    .print-summary {
        border: 1px solid #000 !important;
        padding: 10px !important;
        margin-top: 15px !important;
        background: #f8f8f8 !important;
    }
    
    .print-summary h4 {
        font-size: 12px !important;
        font-weight: bold !important;
        margin: 0 0 8px 0 !important;
        color: black !important;
    }
    
    .print-summary p {
        font-size: 10px !important;
        margin: 3px 0 !important;
        color: black !important;
    }
    
    /* Page break controls */
    .page-break-before {
        page-break-before: always;
    }
    
    .page-break-after {
        page-break-after: always;
    }
    
    /* Footer */
    .print-footer {
        display: block !important;
        position: fixed;
        bottom: 10px;
        left: 0;
        right: 0;
        text-align: center;
        font-size: 9px !important;
        color: #666 !important;
        border-top: 1px solid #ccc;
        padding-top: 5px;
    }
    
    /* Hide screen-only elements */
    [style*="background: var(--white)"],
    [style*="background: var(--light-blue)"],
    [style*="box-shadow"],
    [style*="border-radius"] {
        background: white !important;
        box-shadow: none !important;
        border-radius: 0 !important;
    }
}

/* Regular styles for screen */
.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
}

.badge {
    font-size: 0.8em;
}

.table th {
    font-weight: 600;
    font-size: 0.9em;
}

.table td {
    vertical-align: middle;
}

/* Print header - hidden on screen */
.print-header {
    display: none;
}

.print-student-info {
    display: none;
}

.print-footer {
    display: none;
}
</style>

<?php include '../includes/footer.php'; ?>
