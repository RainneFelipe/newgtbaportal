<?php
require_once '../includes/auth_check.php';

// Check if user is a student
if (!checkRole('student')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$page_title = 'My Grades - GTBA Portal';
$base_url = '../';

try {
    $database = new Database();
    $db = $database->connect();
    
    // Get student's grades
    $query = "SELECT sg.*, s.subject_name, s.subject_code, t.first_name as teacher_fname, 
                     t.last_name as teacher_lname, sy.year_label
              FROM student_grades sg
              LEFT JOIN subjects s ON sg.subject_id = s.id
              LEFT JOIN teachers t ON sg.teacher_id = t.id
              LEFT JOIN school_years sy ON sg.school_year_id = sy.id
              LEFT JOIN students st ON sg.student_id = st.id
              WHERE st.user_id = :user_id
              ORDER BY sy.year_label DESC, s.subject_name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    
    $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Unable to load grades information.";
    error_log("Student grades error: " . $e->getMessage());
}

ob_start();
?>

<div class="welcome-section">
    <h1 class="welcome-title">My Grades</h1>
    <p class="welcome-subtitle">View your academic performance and final grades</p>
</div>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <?php echo $error_message; ?>
    </div>
<?php elseif (empty($grades)): ?>
    <div class="alert alert-warning">
        No grades have been recorded yet. Grades will appear here once your teachers input them.
    </div>
<?php else: ?>
    <div style="background: var(--white); border-radius: 15px; padding: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: var(--light-blue); border-bottom: 2px solid var(--primary-blue);">
                        <th style="padding: 1rem; text-align: left; color: var(--black); font-weight: 600;">Subject Code</th>
                        <th style="padding: 1rem; text-align: left; color: var(--black); font-weight: 600;">Subject Name</th>
                        <th style="padding: 1rem; text-align: left; color: var(--black); font-weight: 600;">Teacher</th>
                        <th style="padding: 1rem; text-align: center; color: var(--black); font-weight: 600;">Final Grade</th>
                        <th style="padding: 1rem; text-align: center; color: var(--black); font-weight: 600;">Remarks</th>
                        <th style="padding: 1rem; text-align: left; color: var(--black); font-weight: 600;">School Year</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($grades as $grade): ?>
                        <tr style="border-bottom: 1px solid var(--border-gray);">
                            <td style="padding: 1rem; color: var(--black); font-weight: 500;">
                                <?php echo htmlspecialchars($grade['subject_code']); ?>
                            </td>
                            <td style="padding: 1rem; color: var(--black);">
                                <?php echo htmlspecialchars($grade['subject_name']); ?>
                            </td>
                            <td style="padding: 1rem; color: var(--black);">
                                <?php echo htmlspecialchars($grade['teacher_fname'] . ' ' . $grade['teacher_lname']); ?>
                            </td>
                            <td style="padding: 1rem; text-align: center; color: var(--black); font-weight: 600; font-size: 1.1rem;">
                                <?php if ($grade['final_grade']): ?>
                                    <?php 
                                    $grade_value = $grade['final_grade'];
                                    $grade_color = $grade_value >= 75 ? 'var(--success)' : 'var(--danger)';
                                    ?>
                                    <span style="color: <?php echo $grade_color; ?>;">
                                        <?php echo number_format($grade_value, 2); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--gray);">Not yet graded</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 1rem; text-align: center;">
                                <?php if ($grade['remarks']): ?>
                                    <?php 
                                    $remarks_color = in_array($grade['remarks'], ['Passed']) ? 'var(--success)' : 'var(--danger)';
                                    ?>
                                    <span style="color: <?php echo $remarks_color; ?>; font-weight: 500;">
                                        <?php echo htmlspecialchars($grade['remarks']); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--gray);">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 1rem; color: var(--black);">
                                <?php echo htmlspecialchars($grade['year_label']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php
        // Calculate GPA if there are graded subjects
        $graded_subjects = array_filter($grades, function($grade) {
            return !empty($grade['final_grade']);
        });
        
        if (!empty($graded_subjects)):
            $total_grades = array_sum(array_column($graded_subjects, 'final_grade'));
            $gpa = $total_grades / count($graded_subjects);
        ?>
            <div style="margin-top: 2rem; padding: 1.5rem; background: var(--light-blue); border-radius: 10px;">
                <h4 style="color: var(--dark-blue); margin-bottom: 0.5rem;">Academic Summary</h4>
                <p style="color: var(--black); margin: 0.25rem 0;">
                    <strong>Total Subjects with Grades:</strong> <?php echo count($graded_subjects); ?>
                </p>
                <p style="color: var(--black); margin: 0.25rem 0;">
                    <strong>General Weighted Average (GWA):</strong> 
                    <span style="font-size: 1.2rem; font-weight: 600; color: <?php echo $gpa >= 75 ? 'var(--success)' : 'var(--danger)'; ?>;">
                        <?php echo number_format($gpa, 2); ?>
                    </span>
                </p>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div style="margin-top: 2rem; text-align: center;">
    <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
</div>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
