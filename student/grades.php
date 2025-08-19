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
    
    // Create variable for user ID to avoid bindParam issues
    $user_id = $_SESSION['user_id'];
    
    // Get student information first
    $student_query = "SELECT st.id as student_id, st.current_grade_level_id, st.current_school_year_id, 
                             st.first_name, st.last_name, gl.grade_name, sy.year_label
                      FROM students st 
                      LEFT JOIN grade_levels gl ON st.current_grade_level_id = gl.id
                      LEFT JOIN school_years sy ON st.current_school_year_id = sy.id
                      WHERE st.user_id = :user_id AND st.is_active = 1";
    $student_stmt = $db->prepare($student_query);
    $student_stmt->bindParam(':user_id', $user_id);
    $student_stmt->execute();
    $student_info = $student_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student_info) {
        throw new Exception("Student information not found. Please contact the registrar to ensure your account is properly set up.");
    }
    
    if (!$student_info['current_grade_level_id'] || !$student_info['current_school_year_id']) {
        throw new Exception("Student grade level or school year not assigned. Please contact the registrar.");
    }
    
    // Get all subjects from curriculum for the student's current grade level and school year
    // First try with exact school year match, then fall back to any school year for the grade level
    $curriculum_query = "SELECT c.*, s.subject_name, s.subject_code, sy.year_label,
                                c.grade_level_id, c.school_year_id
                         FROM curriculum c
                         JOIN subjects s ON c.subject_id = s.id
                         LEFT JOIN school_years sy ON c.school_year_id = sy.id
                         WHERE c.grade_level_id = :grade_level_id 
                         AND (c.school_year_id = :school_year_id OR c.school_year_id IN (
                             SELECT id FROM school_years WHERE is_active = 1 OR is_current = 1
                         ))
                         AND s.is_active = 1
                         ORDER BY c.school_year_id DESC, c.order_sequence ASC, s.subject_name ASC";
    
    $curriculum_stmt = $db->prepare($curriculum_query);
    $grade_level_id = $student_info['current_grade_level_id'];
    $school_year_id = $student_info['current_school_year_id'];
    $curriculum_stmt->bindParam(':grade_level_id', $grade_level_id);
    $curriculum_stmt->bindParam(':school_year_id', $school_year_id);
    $curriculum_stmt->execute();
    
    $curriculum_subjects = $curriculum_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug logging
    error_log("Student curriculum debug - Grade Level ID: $grade_level_id, School Year ID: $school_year_id");
    error_log("Student curriculum debug - Found " . count($curriculum_subjects) . " subjects");
    
    // If no subjects found for current school year, try to get subjects from any active school year
    if (empty($curriculum_subjects)) {
        $fallback_query = "SELECT c.*, s.subject_name, s.subject_code, sy.year_label,
                                  c.grade_level_id, c.school_year_id
                           FROM curriculum c
                           JOIN subjects s ON c.subject_id = s.id
                           LEFT JOIN school_years sy ON c.school_year_id = sy.id
                           WHERE c.grade_level_id = :grade_level_id 
                           AND s.is_active = 1
                           ORDER BY c.school_year_id DESC, c.order_sequence ASC, s.subject_name ASC";
        
        $fallback_stmt = $db->prepare($fallback_query);
        $fallback_stmt->bindParam(':grade_level_id', $grade_level_id);
        $fallback_stmt->execute();
        
        $curriculum_subjects = $fallback_stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Student curriculum debug - Fallback found " . count($curriculum_subjects) . " subjects");
    }
    
    // Ensure curriculum_subjects is always an array
    if (!is_array($curriculum_subjects)) {
        $curriculum_subjects = [];
    }
    
    // Get existing grades for these subjects - Fix the teacher join to use user_id
    $grades_query = "SELECT sg.*, u.username as teacher_username, 
                            CONCAT(COALESCE(t.first_name, 'Unknown'), ' ', COALESCE(t.last_name, 'Teacher')) as teacher_name
                     FROM student_grades sg
                     LEFT JOIN users u ON sg.teacher_id = u.id
                     LEFT JOIN teachers t ON u.id = t.user_id
                     WHERE sg.student_id = :student_id
                     AND sg.school_year_id = :school_year_id";
    
    $grades_stmt = $db->prepare($grades_query);
    $student_id = $student_info['student_id'];
    $grades_stmt->bindParam(':student_id', $student_id);
    $grades_stmt->bindParam(':school_year_id', $school_year_id);
    $grades_stmt->execute();
    
    $existing_grades = $grades_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ensure existing_grades is always an array
    if (!is_array($existing_grades)) {
        $existing_grades = [];
    }
    
    // Create a lookup array for existing grades by subject_id
    $grades_lookup = [];
    foreach ($existing_grades as $grade) {
        $grades_lookup[$grade['subject_id']] = $grade;
    }
    
    // Combine curriculum subjects with their grades (if any)
    $subjects_with_grades = [];
    if (!empty($curriculum_subjects)) {
        foreach ($curriculum_subjects as $subject) {
            $subject_data = [
                'subject_id' => $subject['subject_id'],
                'subject_code' => $subject['subject_code'],
                'subject_name' => $subject['subject_name'],
                'year_label' => $subject['year_label'] ?? $student_info['year_label'] ?? 'Current Year',
                'is_required' => $subject['is_required'] ?? 1,
                'final_grade' => null,
                'remarks' => null,
                'teacher_name' => null,
                'teacher_comments' => null
            ];
            
            // If grade exists for this subject, add grade info
            if (isset($grades_lookup[$subject['subject_id']])) {
                $grade_data = $grades_lookup[$subject['subject_id']];
                $subject_data['final_grade'] = $grade_data['final_grade'];
                $subject_data['remarks'] = $grade_data['remarks'];
                $subject_data['teacher_name'] = $grade_data['teacher_name'];
                $subject_data['teacher_comments'] = $grade_data['teacher_comments'];
            }
            
            $subjects_with_grades[] = $subject_data;
        }
    }
    
} catch (Exception $e) {
    $error_message = "Unable to load grades information: " . $e->getMessage();
    error_log("Student grades error for user_id " . ($user_id ?? $_SESSION['user_id'] ?? 'unknown') . ": " . $e->getMessage());
    
    // Additional debugging info
    if (isset($_SESSION['user_id'])) {
        error_log("Student grades debug - User ID: " . $_SESSION['user_id']);
        error_log("Student grades debug - Role: " . ($_SESSION['role'] ?? 'unknown'));
    }
}

ob_start();
?>

<div class="welcome-section">
    <h1 class="welcome-title">My Grades</h1>
    <p class="welcome-subtitle">View your academic performance and final grades</p>
</div>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <h4>⚠️ Unable to Load Grades</h4>
        <p><?php echo htmlspecialchars($error_message); ?></p>
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="debug-info" style="background: #f8f9fa; padding: 1rem; border-radius: 5px; margin-top: 1rem; font-size: 0.9rem;">
                <strong>Debug Information:</strong><br>
                User ID: <?php echo htmlspecialchars($_SESSION['user_id'] ?? 'Not set'); ?><br>
                Username: <?php echo htmlspecialchars($_SESSION['username'] ?? 'Not set'); ?><br>
                Role: <?php echo htmlspecialchars($_SESSION['role'] ?? 'Not set'); ?><br>
                <?php if (isset($student_info)): ?>
                    Student ID: <?php echo htmlspecialchars($student_info['student_id'] ?? 'Not found'); ?><br>
                    Grade Level ID: <?php echo htmlspecialchars($student_info['current_grade_level_id'] ?? 'Not set'); ?><br>
                    School Year ID: <?php echo htmlspecialchars($student_info['current_school_year_id'] ?? 'Not set'); ?><br>
                <?php endif; ?>
            </div>
            
            <div style="margin-top: 1rem;">
                <p><strong>Possible Solutions:</strong></p>
                <ul>
                    <li>Contact the registrar to verify your student information is complete</li>
                    <li>Ensure you are assigned to a grade level and school year</li>
                    <li>Check if the curriculum has been set up for your grade level</li>
                    <li>Try logging out and logging back in</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
<?php elseif (empty($subjects_with_grades)): ?>
    <div class="alert alert-warning">
        <h4>📚 No Curriculum Subjects Found</h4>
        <p>No subjects have been assigned to your current grade level for this school year. This might be because:</p>
        <ul>
            <li>The curriculum has not been set up for your grade level in the current school year</li>
            <li>Your grade level assignment needs to be updated</li>
            <li>The curriculum is set up for a different school year</li>
        </ul>
        
        <?php if (isset($student_info)): ?>
            <div style="background: #f8f9fa; padding: 1rem; border-radius: 5px; margin-top: 1rem; font-size: 0.9rem;">
                <strong>Current Student Information:</strong><br>
                Grade Level ID: <?php echo htmlspecialchars($student_info['current_grade_level_id'] ?? 'Not set'); ?> 
                (<?php echo htmlspecialchars($student_info['grade_name'] ?? 'Unknown Grade'); ?>)<br>
                School Year ID: <?php echo htmlspecialchars($student_info['current_school_year_id'] ?? 'Not set'); ?>
                (<?php echo htmlspecialchars($student_info['year_label'] ?? 'Unknown Year'); ?>)<br>
                Student Name: <?php echo htmlspecialchars(($student_info['first_name'] ?? '') . ' ' . ($student_info['last_name'] ?? '')); ?>
            </div>
        <?php endif; ?>
        
        <p style="margin-top: 1rem;"><strong>Please contact:</strong></p>
        <ul>
            <li>The registrar to verify your enrollment information</li>
            <li>The principal or curriculum coordinator to set up subjects for your grade level</li>
        </ul>
    </div>
<?php else: ?>
    <div style="background: var(--white); border-radius: 15px; padding: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
        <div style="margin-bottom: 1.5rem;">
            <h3 style="color: var(--dark-blue); margin-bottom: 0.5rem;">📊 Academic Performance</h3>
            <p style="color: var(--gray); margin: 0;">
                School Year: <strong><?php echo htmlspecialchars($subjects_with_grades[0]['year_label'] ?? 'Current'); ?></strong>
            </p>
        </div>
        
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: var(--light-blue); border-bottom: 2px solid var(--primary-blue);">
                        <th style="padding: 1rem; text-align: left; color: var(--black); font-weight: 600;">Subject Code</th>
                        <th style="padding: 1rem; text-align: left; color: var(--black); font-weight: 600;">Subject Name</th>
                        <th style="padding: 1rem; text-align: center; color: var(--black); font-weight: 600;">Required</th>
                        <th style="padding: 1rem; text-align: left; color: var(--black); font-weight: 600;">Teacher</th>
                        <th style="padding: 1rem; text-align: center; color: var(--black); font-weight: 600;">Final Grade</th>
                        <th style="padding: 1rem; text-align: center; color: var(--black); font-weight: 600;">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $graded_count = 0;
                    $total_grades_sum = 0;
                    ?>
                    <?php foreach ($subjects_with_grades as $subject): ?>
                        <tr style="border-bottom: 1px solid var(--border-gray);">
                            <td style="padding: 1rem; color: var(--black); font-weight: 500;">
                                <?php echo htmlspecialchars($subject['subject_code']); ?>
                                <?php if ($subject['is_required']): ?>
                                    <span style="color: var(--danger); font-size: 0.8rem; margin-left: 0.5rem;">*</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 1rem; color: var(--black);">
                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                            </td>
                            <td style="padding: 1rem; text-align: center;">
                                <?php if ($subject['is_required']): ?>
                                    <span style="color: var(--danger); font-weight: 600;">Required</span>
                                <?php else: ?>
                                    <span style="color: var(--gray);">Elective</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 1rem; color: var(--black);">
                                <?php if ($subject['teacher_name'] && $subject['teacher_name'] !== 'Unknown Teacher'): ?>
                                    <?php echo htmlspecialchars($subject['teacher_name']); ?>
                                <?php else: ?>
                                    <span style="color: var(--gray); font-style: italic;">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 1rem; text-align: center; font-weight: 600; font-size: 1.1rem;">
                                <?php if ($subject['final_grade']): ?>
                                    <?php 
                                    $grade_value = floatval($subject['final_grade']);
                                    $grade_color = $grade_value >= 75 ? 'var(--success)' : 'var(--danger)';
                                    $graded_count++;
                                    $total_grades_sum += $grade_value;
                                    ?>
                                    <span style="color: <?php echo $grade_color; ?>;">
                                        <?php echo number_format($grade_value, 2); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--gray); background: var(--light-gray); padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.9rem;">
                                        Not yet graded
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 1rem; text-align: center;">
                                <?php if ($subject['remarks']): ?>
                                    <?php 
                                    $remarks_color = in_array($subject['remarks'], ['Passed']) ? 'var(--success)' : 'var(--danger)';
                                    ?>
                                    <span style="color: <?php echo $remarks_color; ?>; font-weight: 500;">
                                        <?php echo htmlspecialchars($subject['remarks']); ?>
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
            <?php $gpa = $total_grades_sum / $graded_count; ?>
            <div style="margin-top: 2rem; padding: 1.5rem; background: var(--light-blue); border-radius: 10px;">
                <h4 style="color: var(--dark-blue); margin-bottom: 0.5rem;">📈 Academic Summary</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div>
                        <p style="color: var(--black); margin: 0.25rem 0;">
                            <strong>Total Subjects:</strong> <?php echo count($subjects_with_grades); ?>
                        </p>
                        <p style="color: var(--black); margin: 0.25rem 0;">
                            <strong>Graded Subjects:</strong> <?php echo $graded_count; ?>
                        </p>
                    </div>
                    <div>
                        <p style="color: var(--black); margin: 0.25rem 0;">
                            <strong>Pending Grades:</strong> <?php echo count($subjects_with_grades) - $graded_count; ?>
                        </p>
                        <p style="color: var(--black); margin: 0.25rem 0;">
                            <strong>General Weighted Average (GWA):</strong> 
                            <span style="font-size: 1.2rem; font-weight: 600; color: <?php echo $gpa >= 75 ? 'var(--success)' : 'var(--danger)'; ?>;">
                                <?php echo number_format($gpa, 2); ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div style="margin-top: 2rem; padding: 1.5rem; background: var(--warning-light); border: 1px solid var(--warning); border-radius: 10px;">
                <h4 style="color: var(--warning-dark); margin-bottom: 0.5rem;">📋 Waiting for Grades</h4>
                <p style="color: var(--black); margin: 0.5rem 0;">
                    You have <strong><?php echo count($subjects_with_grades); ?> subjects</strong> in your curriculum, but no grades have been recorded yet.
                </p>
                <p style="color: var(--gray); margin: 0; font-size: 0.9rem;">
                    Grades will appear here once your teachers input them. Contact your teachers if you have questions about upcoming assessments.
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
