<?php
require_once '../includes/auth_check.php';

// Check if user is a principal
if (!checkRole('principal')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

// Get section ID from URL parameter
$section_id = $_GET['section_id'] ?? null;
if (!$section_id) {
    header('Location: sections.php');
    exit();
}

$page_title = 'Manage Section - GTBA Portal';
$base_url = '../';

try {
    $database = new Database();
    $db = $database->connect();
    
    // Initialize variables
    $section = null;
    $assigned_teachers = [];
    $available_teachers = [];
    $enrolled_students = [];
    $available_students = [];
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'assign_teacher':
                    $teacher_id = $_POST['teacher_id'];
                    $is_primary = isset($_POST['is_primary']) ? 1 : 0;
                    
                    if ($teacher_id) {
                        try {
                            $db->beginTransaction();
                            
                            // If setting as primary, check if teacher is already primary adviser in another section
                            if ($is_primary) {
                                $check_primary_query = "SELECT s.section_name, gl.grade_name 
                                                       FROM section_teachers st
                                                       JOIN sections s ON st.section_id = s.id
                                                       JOIN grade_levels gl ON s.grade_level_id = gl.id
                                                       WHERE st.teacher_id = ? AND st.is_primary = 1 AND st.is_active = 1
                                                       LIMIT 1";
                                $check_primary_stmt = $db->prepare($check_primary_query);
                                $check_primary_stmt->execute([$teacher_id]);
                                $existing_primary = $check_primary_stmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($existing_primary) {
                                    $db->rollBack();
                                    $error_message = "This teacher is already a primary adviser for " . 
                                                   htmlspecialchars($existing_primary['section_name']) . " (" . 
                                                   htmlspecialchars($existing_primary['grade_name']) . ")";
                                    break;
                                }
                                
                                // Remove primary status from other teachers in this section
                                $query = "UPDATE section_teachers SET is_primary = 0 WHERE section_id = ? AND is_active = 1";
                                $stmt = $db->prepare($query);
                                $stmt->execute([$section_id]);
                            }
                            
                            // Check if teacher is already assigned
                            $check_query = "SELECT id FROM section_teachers WHERE section_id = ? AND teacher_id = ? AND is_active = 1";
                            $check_stmt = $db->prepare($check_query);
                            $check_stmt->execute([$section_id, $teacher_id]);
                            
                            if ($check_stmt->fetch()) {
                                $db->rollBack();
                                $error_message = "Teacher is already assigned to this section.";
                            } else {
                                // Assign teacher to section
                                $query = "INSERT INTO section_teachers (section_id, teacher_id, is_primary, assigned_date, created_by, created_at) 
                                          VALUES (?, ?, ?, CURDATE(), ?, NOW())";
                                $stmt = $db->prepare($query);
                                $stmt->execute([$section_id, $teacher_id, $is_primary, $_SESSION['user_id']]);
                                $success_message = "Teacher assigned successfully!";
                                $db->commit();
                            }
                        } catch (Exception $e) {
                            $db->rollBack();
                            $error_message = "Failed to assign teacher: " . $e->getMessage();
                        }
                    }
                    break;
                    
                case 'remove_teacher':
                    $assignment_id = $_POST['assignment_id'];
                    
                    if ($assignment_id) {
                        $query = "UPDATE section_teachers SET is_active = 0, updated_at = NOW() WHERE id = ? AND section_id = ?";
                        $stmt = $db->prepare($query);
                        if ($stmt->execute([$assignment_id, $section_id])) {
                            $success_message = "Teacher removed from section successfully!";
                        } else {
                            $error_message = "Failed to remove teacher.";
                        }
                    }
                    break;
                    
                case 'set_primary_teacher':
                    $assignment_id = $_POST['assignment_id'];
                    
                    if ($assignment_id) {
                        try {
                            $db->beginTransaction();
                            
                            // Get teacher ID from assignment
                            $get_teacher_query = "SELECT teacher_id FROM section_teachers WHERE id = ?";
                            $get_teacher_stmt = $db->prepare($get_teacher_query);
                            $get_teacher_stmt->execute([$assignment_id]);
                            $assignment_data = $get_teacher_stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($assignment_data) {
                                // Check if teacher is already primary adviser in another section
                                $check_primary_query = "SELECT s.section_name, gl.grade_name 
                                                       FROM section_teachers st
                                                       JOIN sections s ON st.section_id = s.id
                                                       JOIN grade_levels gl ON s.grade_level_id = gl.id
                                                       WHERE st.teacher_id = ? AND st.is_primary = 1 
                                                       AND st.is_active = 1 AND st.section_id != ?
                                                       LIMIT 1";
                                $check_primary_stmt = $db->prepare($check_primary_query);
                                $check_primary_stmt->execute([$assignment_data['teacher_id'], $section_id]);
                                $existing_primary = $check_primary_stmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($existing_primary) {
                                    $db->rollBack();
                                    $error_message = "This teacher is already a primary adviser for " . 
                                                   htmlspecialchars($existing_primary['section_name']) . " (" . 
                                                   htmlspecialchars($existing_primary['grade_name']) . ")";
                                    break;
                                }
                                
                                // Remove primary status from all teachers in section
                                $query = "UPDATE section_teachers SET is_primary = 0 WHERE section_id = ? AND is_active = 1";
                                $stmt = $db->prepare($query);
                                $stmt->execute([$section_id]);
                                
                                // Set the selected teacher as primary
                                $query = "UPDATE section_teachers SET is_primary = 1, updated_at = NOW() WHERE id = ? AND section_id = ?";
                                $stmt = $db->prepare($query);
                                $stmt->execute([$assignment_id, $section_id]);
                                
                                $db->commit();
                                $success_message = "Primary teacher updated successfully!";
                            } else {
                                $db->rollBack();
                                $error_message = "Invalid assignment ID.";
                            }
                        } catch (Exception $e) {
                            $db->rollBack();
                            $error_message = "Failed to update primary teacher: " . $e->getMessage();
                        }
                    }
                    break;
                    
                case 'enroll_student':
                    $student_id = $_POST['student_id'];
                    
                    if ($student_id) {
                        try {
                            $db->beginTransaction();
                            
                            // Check if student is already enrolled in another section for the same school year
                            $check_query = "SELECT s.section_name, gl.grade_name 
                                          FROM students st 
                                          JOIN sections s ON st.current_section_id = s.id 
                                          JOIN grade_levels gl ON s.grade_level_id = gl.id 
                                          WHERE st.id = ? AND st.current_section_id IS NOT NULL
                                          AND s.school_year_id = (SELECT school_year_id FROM sections WHERE id = ?)";
                            $check_stmt = $db->prepare($check_query);
                            $check_stmt->execute([$student_id, $section_id]);
                            $existing_enrollment = $check_stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($existing_enrollment) {
                                $error_message = "Student is already enrolled in {$existing_enrollment['section_name']} ({$existing_enrollment['grade_name']}) for this school year.";
                            } else {
                                // Get section details for enrollment record
                                $section_query = "SELECT grade_level_id, school_year_id FROM sections WHERE id = ?";
                                $section_stmt = $db->prepare($section_query);
                                $section_stmt->execute([$section_id]);
                                $section_info = $section_stmt->fetch(PDO::FETCH_ASSOC);
                                
                                // Enroll student
                                $query = "UPDATE students SET current_section_id = ?, enrollment_status = 'Enrolled', updated_at = NOW() WHERE id = ?";
                                $stmt = $db->prepare($query);
                                $stmt->execute([$section_id, $student_id]);
                                
                                // Create enrollment record
                                $enrollment_query = "INSERT INTO student_enrollments (student_id, school_year_id, grade_level_id, section_id, enrollment_date, enrollment_status, created_by)
                                                   VALUES (?, ?, ?, ?, CURDATE(), 'Enrolled', ?)
                                                   ON DUPLICATE KEY UPDATE
                                                   section_id = VALUES(section_id), enrollment_date = CURDATE(), enrollment_status = 'Enrolled', updated_at = NOW()";
                                $enrollment_stmt = $db->prepare($enrollment_query);
                                $enrollment_stmt->execute([$student_id, $section_info['school_year_id'], $section_info['grade_level_id'], $section_id, $_SESSION['user_id']]);
                                
                                // Update section enrollment count
                                $update_count_query = "UPDATE sections SET current_enrollment = (
                                    SELECT COUNT(*) FROM students WHERE current_section_id = ?
                                ), updated_at = NOW() WHERE id = ?";
                                $stmt = $db->prepare($update_count_query);
                                $stmt->execute([$section_id, $section_id]);
                                
                                $success_message = "Student enrolled successfully!";
                            }
                            
                            $db->commit();
                        } catch (Exception $e) {
                            $db->rollBack();
                            $error_message = "Failed to enroll student: " . $e->getMessage();
                        }
                    }
                    break;
                    
                case 'remove_student':
                    $student_id = $_POST['student_id'];
                    
                    if ($student_id) {
                        try {
                            $db->beginTransaction();
                            
                            // Remove student from section (just set current_section_id to NULL)
                            $query = "UPDATE students SET current_section_id = NULL, updated_at = NOW() WHERE id = ? AND current_section_id = ?";
                            $stmt = $db->prepare($query);
                            $stmt->execute([$student_id, $section_id]);
                            
                            // Update section enrollment count
                            $update_count_query = "UPDATE sections SET current_enrollment = (
                                SELECT COUNT(*) FROM students WHERE current_section_id = ?
                            ), updated_at = NOW() WHERE id = ?";
                            $stmt = $db->prepare($update_count_query);
                            $stmt->execute([$section_id, $section_id]);
                            
                            $db->commit();
                            $success_message = "Student removed from section successfully!";
                        } catch (Exception $e) {
                            $db->rollBack();
                            $error_message = "Failed to remove student: " . $e->getMessage();
                        }
                    }
                    break;
                    
                case 'add_schedule':
                    $activity_name = trim($_POST['activity_name']);
                    $activity_type = $_POST['activity_type'];
                    $teacher_id = !empty($_POST['teacher_id']) ? $_POST['teacher_id'] : null;
                    $day_of_week = $_POST['day_of_week'];
                    $start_time = $_POST['start_time'];
                    $end_time = $_POST['end_time'];
                    $room = trim($_POST['room']);
                    $description = trim($_POST['description']);
                    
                    if ($activity_name && $day_of_week && $start_time && $end_time) {
                        // Check for time conflicts
                        $conflict_query = "SELECT 
                                            CASE 
                                                WHEN activity_name IS NOT NULL THEN activity_name
                                                WHEN subject_id IS NOT NULL THEN (SELECT subject_name FROM subjects WHERE id = subject_id)
                                                ELSE 'Unknown Activity'
                                            END as activity_name 
                                          FROM class_schedules 
                                          WHERE section_id = ? AND day_of_week = ? AND is_active = 1
                                          AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?))";
                        $conflict_stmt = $db->prepare($conflict_query);
                        $conflict_stmt->execute([$section_id, $day_of_week, $start_time, $start_time, $end_time, $end_time]);
                        $conflict = $conflict_stmt->fetch();
                        
                        if ($conflict) {
                            $error_message = "Time conflict with existing activity: " . $conflict['activity_name'];
                        } else {
                            // Get section's school year for the schedule
                            $section_query = "SELECT school_year_id FROM sections WHERE id = ?";
                            $section_stmt = $db->prepare($section_query);
                            $section_stmt->execute([$section_id]);
                            $section_data = $section_stmt->fetch(PDO::FETCH_ASSOC);
                            
                            $query = "INSERT INTO class_schedules (section_id, activity_name, teacher_id, school_year_id, day_of_week, start_time, end_time, room, created_by) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                            $stmt = $db->prepare($query);
                            if ($stmt->execute([$section_id, $activity_name, $teacher_id, $section_data['school_year_id'], $day_of_week, $start_time, $end_time, $room, $_SESSION['user_id']])) {
                                $success_message = "Schedule activity added successfully!";
                            } else {
                                $error_message = "Failed to add schedule activity.";
                            }
                        }
                    } else {
                        $error_message = "Please fill in all required fields.";
                    }
                    break;
                    
                case 'update_schedule':
                    $schedule_id = $_POST['schedule_id'];
                    $activity_name = trim($_POST['activity_name']);
                    $activity_type = $_POST['activity_type'];
                    $teacher_id = !empty($_POST['teacher_id']) ? $_POST['teacher_id'] : null;
                    $day_of_week = $_POST['day_of_week'];
                    $start_time = $_POST['start_time'];
                    $end_time = $_POST['end_time'];
                    $room = trim($_POST['room']);
                    $description = trim($_POST['description']);
                    
                    if ($schedule_id && $activity_name && $day_of_week && $start_time && $end_time) {
                        // Check for time conflicts (excluding current schedule)
                        $conflict_query = "SELECT 
                                            CASE 
                                                WHEN activity_name IS NOT NULL THEN activity_name
                                                WHEN subject_id IS NOT NULL THEN (SELECT subject_name FROM subjects WHERE id = subject_id)
                                                ELSE 'Unknown Activity'
                                            END as activity_name 
                                          FROM class_schedules 
                                          WHERE section_id = ? AND day_of_week = ? AND is_active = 1 AND id != ?
                                          AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?))";
                        $conflict_stmt = $db->prepare($conflict_query);
                        $conflict_stmt->execute([$section_id, $day_of_week, $schedule_id, $start_time, $start_time, $end_time, $end_time]);
                        $conflict = $conflict_stmt->fetch();
                        
                        if ($conflict) {
                            $error_message = "Time conflict with existing activity: " . $conflict['activity_name'];
                        } else {
                            $query = "UPDATE class_schedules SET activity_name = ?, teacher_id = ?, day_of_week = ?, start_time = ?, end_time = ?, room = ?, updated_at = NOW() 
                                      WHERE id = ? AND section_id = ?";
                            $stmt = $db->prepare($query);
                            if ($stmt->execute([$activity_name, $teacher_id, $day_of_week, $start_time, $end_time, $room, $schedule_id, $section_id])) {
                                $success_message = "Schedule activity updated successfully!";
                            } else {
                                $error_message = "Failed to update schedule activity.";
                            }
                        }
                    } else {
                        $error_message = "Please fill in all required fields.";
                    }
                    break;
                    
                case 'delete_schedule':
                    $schedule_id = $_POST['schedule_id'];
                    
                    if ($schedule_id) {
                        $query = "UPDATE class_schedules SET is_active = 0, updated_at = NOW() WHERE id = ? AND section_id = ?";
                        $stmt = $db->prepare($query);
                        if ($stmt->execute([$schedule_id, $section_id])) {
                            $success_message = "Schedule activity removed successfully!";
                        } else {
                            $error_message = "Failed to remove schedule activity.";
                        }
                    }
                    break;
            }
        }
    }
    
    // Get section information
    $query = "SELECT s.*, gl.grade_name, sy.year_label 
              FROM sections s
              LEFT JOIN grade_levels gl ON s.grade_level_id = gl.id
              LEFT JOIN school_years sy ON s.school_year_id = sy.id
              WHERE s.id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$section_id]);
    $section = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$section) {
        header('Location: sections.php');
        exit();
    }
    
    // Get assigned teachers
    $query = "SELECT st.id as assignment_id, st.is_primary, st.assigned_date, 
              u.id as user_id, t.first_name, t.last_name, t.specialization, u.email
              FROM section_teachers st
              JOIN users u ON st.teacher_id = u.id
              JOIN teachers t ON u.id = t.user_id
              WHERE st.section_id = ? AND st.is_active = 1
              ORDER BY st.is_primary DESC, t.first_name, t.last_name";
    $stmt = $db->prepare($query);
    $stmt->execute([$section_id]);
    $assigned_teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get available teachers (not assigned to this section)
    // Teachers who are primary advisers elsewhere can still be added as non-primary teachers
    $query = "SELECT u.id, t.first_name, t.last_name, t.specialization, u.email,
              (SELECT COUNT(*) FROM section_teachers 
               WHERE teacher_id = u.id AND is_primary = 1 AND is_active = 1) as is_primary_elsewhere
              FROM users u
              JOIN roles r ON u.role_id = r.id
              JOIN teachers t ON u.id = t.user_id
              WHERE r.name = 'teacher' AND u.is_active = 1 AND t.is_active = 1
              AND u.id NOT IN (
                  SELECT teacher_id FROM section_teachers 
                  WHERE section_id = ? AND is_active = 1
              )
              ORDER BY t.first_name, t.last_name";
    $stmt = $db->prepare($query);
    $stmt->execute([$section_id]);
    $available_teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get enrolled students with enrollment date
    $query = "SELECT st.id, st.student_id, st.lrn, st.first_name, st.last_name, st.middle_name,
              st.enrollment_status, gl.grade_name as grade_level,
              se.enrollment_date as date_enrolled
              FROM students st
              LEFT JOIN student_enrollments se ON st.id = se.student_id AND se.section_id = ?
              LEFT JOIN grade_levels gl ON st.current_grade_level_id = gl.id
              WHERE st.current_section_id = ?
              ORDER BY st.last_name, st.first_name";
    $stmt = $db->prepare($query);
    $stmt->execute([$section_id, $section_id]);
    $enrolled_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get available students (same grade level, not enrolled in any section for this school year, and fully paid/enrolled)
    $query = "SELECT st.id, st.student_id, st.lrn, st.first_name, st.last_name, st.middle_name,
              st.enrollment_status, gl.grade_name as grade_level
              FROM students st
              LEFT JOIN grade_levels gl ON st.current_grade_level_id = gl.id
              WHERE st.current_grade_level_id = ? 
              AND st.current_section_id IS NULL
              AND st.is_active = 1
              AND st.enrollment_status = 'Enrolled'
              ORDER BY st.last_name, st.first_name";
    $stmt = $db->prepare($query);
    $stmt->execute([$section['grade_level_id']]);
    $available_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get section schedules grouped by day
    $query = "SELECT cs.*, 
              CASE 
                  WHEN cs.activity_name IS NOT NULL THEN cs.activity_name
                  WHEN cs.subject_id IS NOT NULL THEN CONCAT((SELECT subject_code FROM subjects WHERE id = cs.subject_id), ' - ', (SELECT subject_name FROM subjects WHERE id = cs.subject_id))
                  ELSE 'Unknown Activity'
              END as activity_name,
              CASE 
                  WHEN cs.activity_name IS NOT NULL THEN 'Activity'
                  WHEN cs.subject_id IS NOT NULL THEN 'Subject'
                  ELSE 'Unknown'
              END as activity_type,
              CASE 
                  WHEN cs.teacher_id IS NOT NULL THEN CONCAT(t.first_name, ' ', t.last_name)
                  ELSE 'No Teacher Assigned'
              END as teacher_name
              FROM class_schedules cs
              LEFT JOIN teachers t ON cs.teacher_id = t.user_id
              WHERE cs.section_id = ? AND cs.is_active = 1
              ORDER BY 
                  FIELD(cs.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
                  cs.start_time";
    $stmt = $db->prepare($query);
    $stmt->execute([$section_id]);
    $all_schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group schedules by day
    $schedules_by_day = [];
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    foreach ($days as $day) {
        $schedules_by_day[$day] = array_filter($all_schedules, function($schedule) use ($day) {
            return $schedule['day_of_week'] === $day;
        });
    }
    
    // Get all teachers for schedule dropdown (including assigned and available)
    $query = "SELECT u.id, t.first_name, t.last_name, t.specialization
              FROM users u
              JOIN roles r ON u.role_id = r.id
              JOIN teachers t ON u.id = t.user_id
              WHERE r.name = 'teacher' AND u.is_active = 1 AND t.is_active = 1
              ORDER BY t.first_name, t.last_name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $all_teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Unable to load section data: " . $e->getMessage();
    error_log("Section manage error: " . $e->getMessage());
    
    // Initialize variables to prevent undefined variable errors
    if (!isset($section)) $section = null;
    if (!isset($assigned_teachers)) $assigned_teachers = [];
    if (!isset($available_teachers)) $available_teachers = [];
    if (!isset($enrolled_students)) $enrolled_students = [];
    if (!isset($available_students)) $available_students = [];
    if (!isset($schedules_by_day)) $schedules_by_day = [];
    if (!isset($all_teachers)) $all_teachers = [];
}

ob_start();
?>

<div class="page-header-modern">
    <div class="page-title-section">
        <div class="page-title-wrapper">
            <h1 class="page-title">Manage Section</h1>
            <div class="page-breadcrumb">
                <a href="sections.php" class="breadcrumb-item">Sections</a>
                <span class="breadcrumb-separator">›</span>
                <span class="breadcrumb-item current"><?php echo htmlspecialchars($section['section_name']); ?></span>
            </div>
        </div>
        <p class="page-description">Add or remove students and teachers for <?php echo htmlspecialchars($section['section_name']); ?> - <?php echo htmlspecialchars($section['grade_name']); ?> (<?php echo htmlspecialchars($section['year_label']); ?>)</p>
    </div>
    <div class="page-actions">
        <a href="sections.php" class="btn-modern btn-ghost">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Back to Sections
        </a>
    </div>
</div>

<!-- Section Info Card -->
<div class="section-info-card">
    <div class="section-info-header">
        <div class="section-info-title">
            <h2><?php echo htmlspecialchars($section['section_name']); ?></h2>
            <span class="section-badge"><?php echo htmlspecialchars($section['grade_name']); ?></span>
        </div>
        <div class="section-info-stats">
            <div class="stat-item">
                <div class="stat-number"><?php echo count($enrolled_students); ?></div>
                <div class="stat-label">Students</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo count($assigned_teachers); ?></div>
                <div class="stat-label">Teachers</div>
            </div>
        </div>
    </div>
    <?php if ($section['description']): ?>
        <div class="section-info-description">
            <p><?php echo htmlspecialchars($section['description']); ?></p>
        </div>
    <?php endif; ?>
</div>

<?php if (isset($success_message)): ?>
    <div class="alert-modern alert-success">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
            <polyline points="22,4 12,14.01 9,11.01"/>
        </svg>
        <?php echo $success_message; ?>
    </div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div class="alert-modern alert-error">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <line x1="15" y1="9" x2="9" y2="15"/>
            <line x1="9" y1="9" x2="15" y2="15"/>
        </svg>
        <?php echo $error_message; ?>
    </div>
<?php endif; ?>

<!-- Management Tabs -->
<div class="management-container">
    <div class="tab-navigation">
        <button type="button" class="tab-btn active" data-tab="teachers">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
            </svg>
            Teachers (<?php echo count($assigned_teachers); ?>)
        </button>
        <button type="button" class="tab-btn" data-tab="students">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            Students (<?php echo count($enrolled_students); ?>)
        </button>
    </div>

    <!-- Teachers Tab -->
    <div class="tab-content active" id="teachers-tab">
        <div class="section-management">
            <div class="management-section">
                <div class="section-header">
                    <h3>Assigned Teachers</h3>
                    <button type="button" class="btn-modern btn-primary" data-modal-target="add-teacher-modal">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>
                        Add Teacher
                    </button>
                </div>
                
                <div class="teachers-list">
                    <?php if (empty($assigned_teachers)): ?>
                        <div class="empty-state">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                            <h4>No Teachers Assigned</h4>
                            <p>Add teachers to this section to get started.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($assigned_teachers as $teacher): ?>
                            <div class="teacher-card">
                                <div class="teacher-info">
                                    <div class="teacher-avatar">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                            <circle cx="12" cy="7" r="4"/>
                                        </svg>
                                    </div>
                                    <div class="teacher-details">
                                        <div class="teacher-name">
                                            <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                            <?php if ($teacher['is_primary']): ?>
                                                <span class="primary-badge">Primary</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="teacher-meta">
                                            <span><?php echo htmlspecialchars($teacher['email']); ?></span>
                                        </div>
                                        <div class="teacher-assigned">
                                            Assigned: <?php echo date('M j, Y', strtotime($teacher['assigned_date'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="teacher-actions">
                                    <?php if (!$teacher['is_primary']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="set_primary_teacher">
                                            <input type="hidden" name="assignment_id" value="<?php echo $teacher['assignment_id']; ?>">
                                            <button type="submit" class="btn-modern btn-ghost btn-sm">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/>
                                                </svg>
                                                Set Primary
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Remove this teacher from the section?')">
                                        <input type="hidden" name="action" value="remove_teacher">
                                        <input type="hidden" name="assignment_id" value="<?php echo $teacher['assignment_id']; ?>">
                                        <button type="submit" class="btn-modern btn-danger btn-sm">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M18 6L6 18M6 6l12 12"/>
                                            </svg>
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Students Tab -->
    <div class="tab-content" id="students-tab">
        <div class="section-management">
            <div class="management-section">
                <div class="section-header">
                    <h3>Enrolled Students</h3>
                    <button type="button" class="btn-modern btn-primary" data-modal-target="add-student-modal">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>
                        Enroll Student
                    </button>
                </div>
                
                <div class="students-list">
                    <?php if (empty($enrolled_students)): ?>
                        <div class="empty-state">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                            <h4>No Students Enrolled</h4>
                            <p>Enroll students to this section to get started.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($enrolled_students as $student): ?>
                            <div class="student-card">
                                <div class="student-info">
                                    <div class="student-avatar">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                            <circle cx="9" cy="7" r="4"/>
                                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                        </svg>
                                    </div>
                                    <div class="student-details">
                                        <div class="student-name">
                                            <?php echo htmlspecialchars($student['first_name'] . ' ' . ($student['middle_name'] ? $student['middle_name'] . ' ' : '') . $student['last_name']); ?>
                                        </div>
                                        <div class="student-meta">
                                            <span>ID: <?php echo htmlspecialchars($student['student_id']); ?></span>
                                            <span>•</span>
                                            <span><?php echo htmlspecialchars($student['grade_level']); ?></span>
                                        </div>

                                    </div>
                                </div>
                                <div class="student-actions">
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Remove this student from the section?')">
                                        <input type="hidden" name="action" value="remove_student">
                                        <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                        <button type="submit" class="btn-modern btn-danger btn-sm">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M18 6L6 18M6 6l12 12"/>
                                            </svg>
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>



<!-- Add Teacher Modal -->
<div id="add-teacher-modal" class="modal-modern">
    <div class="modal-overlay"></div>
    <div class="modal-container">
        <div class="modal-header-modern">
            <div class="modal-title-section">
                <h3 class="modal-title">Add Teacher</h3>
                <p class="modal-subtitle">Assign a teacher to this section</p>
            </div>
            <button type="button" class="modal-close-modern" data-modal-close>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        
        <div class="modal-body-modern">
            <form method="POST" class="form-modern">
                <input type="hidden" name="action" value="assign_teacher">
                
                <div class="form-group-modern">
                    <label for="teacher_id" class="form-label">Select Teacher *</label>
                    <div class="select-wrapper">
                        <select id="teacher_id" name="teacher_id" class="form-select" required>
                            <option value="">Choose a teacher...</option>
                            <?php foreach ($available_teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>" 
                                        data-is-primary-elsewhere="<?php echo $teacher['is_primary_elsewhere']; ?>">
                                    <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <svg class="select-arrow" width="12" height="8" viewBox="0 0 12 8">
                            <path d="M1 1L6 6L11 1" stroke="currentColor" stroke-width="1.5" fill="none"/>
                        </svg>
                    </div>
                </div>
                
                <div class="checkbox-group-modern">
                    <label class="checkbox-label-modern" id="primary-checkbox-label">
                        <input type="checkbox" name="is_primary" id="is_primary_checkbox" class="checkbox-input" style="display: none;">
                        <span class="checkbox-custom"></span>
                        <span class="checkbox-text">Set as primary teacher for this section</span>
                    </label>
                </div>
                
                <div class="form-actions-modern">
                    <button type="submit" class="btn-modern btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>
                        Add Teacher
                    </button>
                    <button type="button" class="btn-modern btn-ghost" data-modal-close>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Student Modal -->
<div id="add-student-modal" class="modal-modern">
    <div class="modal-overlay"></div>
    <div class="modal-container">
        <div class="modal-header-modern">
            <div class="modal-title-section">
                <h3 class="modal-title">Enroll Student</h3>
                <p class="modal-subtitle">Add a student to this section</p>
            </div>
            <button type="button" class="modal-close-modern" data-modal-close>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        
        <div class="modal-body-modern">
            <form method="POST" class="form-modern">
                <input type="hidden" name="action" value="enroll_student">
                
                <div class="form-group-modern">
                    <label for="student_id" class="form-label">Select Student *</label>
                    <div class="select-wrapper">
                        <select id="student_id" name="student_id" class="form-select" required>
                            <option value="">Choose a student...</option>
                            <?php foreach ($available_students as $student): ?>
                                <option value="<?php echo $student['id']; ?>">
                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . ($student['middle_name'] ? $student['middle_name'] . ' ' : '') . $student['last_name'] . ' (ID: ' . $student['student_id'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <svg class="select-arrow" width="12" height="8" viewBox="0 0 12 8">
                            <path d="M1 1L6 6L11 1" stroke="currentColor" stroke-width="1.5" fill="none"/>
                        </svg>
                    </div>
                    <?php if (empty($available_students)): ?>
                        <div class="form-help">No available students found for this grade level.</div>
                    <?php endif; ?>
                </div>
                
                <div class="form-actions-modern">
                    <button type="submit" class="btn-modern btn-primary" <?php echo empty($available_students) ? 'disabled' : ''; ?>>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>
                        Enroll Student
                    </button>
                    <button type="button" class="btn-modern btn-ghost" data-modal-close>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Schedule Modal -->
<div id="add-schedule-modal" class="modal-modern">
    <div class="modal-overlay"></div>
    <div class="modal-container">
        <div class="modal-header-modern">
            <div class="modal-title-section">
                <h3 class="modal-title">Add Schedule Activity</h3>
                <p class="modal-subtitle">Add a new activity to the section schedule</p>
            </div>
            <button type="button" class="modal-close-modern" data-modal-close>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        
        <div class="modal-body-modern">
            <form method="POST" class="form-modern">
                <input type="hidden" name="action" value="add_schedule">
                
                <div class="form-grid-modern">
                    <div class="form-group-modern">
                        <label for="activity_name" class="form-label">Activity Name *</label>
                        <input type="text" id="activity_name" name="activity_name" class="form-input" required 
                               placeholder="e.g. Mathematics, Recess, Assembly">
                    </div>
                    
                    <div class="form-group-modern">
                        <label for="activity_type" class="form-label">Activity Type *</label>
                        <div class="select-wrapper">
                            <select id="activity_type" name="activity_type" class="form-select" required>
                                <option value="">Select Type</option>
                                <option value="Subject">Subject</option>
                                <option value="Break">Break</option>
                                <option value="Activity">Activity</option>
                                <option value="Assembly">Assembly</option>
                                <option value="Other">Other</option>
                            </select>
                            <svg class="select-arrow" width="12" height="8" viewBox="0 0 12 8">
                                <path d="M1 1L6 6L11 1" stroke="currentColor" stroke-width="1.5" fill="none"/>
                            </svg>
                        </div>
                    </div>
                    
                    <div class="form-group-modern">
                        <label for="teacher_id" class="form-label">Teacher (Optional)</label>
                        <div class="select-wrapper">
                            <select id="teacher_id" name="teacher_id" class="form-select">
                                <option value="">No Teacher Required</option>
                                <?php foreach ($all_teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>">
                                        <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <svg class="select-arrow" width="12" height="8" viewBox="0 0 12 8">
                                <path d="M1 1L6 6L11 1" stroke="currentColor" stroke-width="1.5" fill="none"/>
                            </svg>
                        </div>
                    </div>
                    
                    <div class="form-group-modern">
                        <label for="day_of_week" class="form-label">Day *</label>
                        <div class="select-wrapper">
                            <select id="day_of_week" name="day_of_week" class="form-select" required>
                                <option value="">Select Day</option>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                            </select>
                            <svg class="select-arrow" width="12" height="8" viewBox="0 0 12 8">
                                <path d="M1 1L6 6L11 1" stroke="currentColor" stroke-width="1.5" fill="none"/>
                            </svg>
                        </div>
                    </div>
                    
                    <div class="form-group-modern">
                        <label for="start_time" class="form-label">Start Time *</label>
                        <input type="time" id="start_time" name="start_time" class="form-input" required>
                    </div>
                    
                    <div class="form-group-modern">
                        <label for="end_time" class="form-label">End Time *</label>
                        <input type="time" id="end_time" name="end_time" class="form-input" required>
                    </div>
                    
                    <div class="form-group-modern">
                        <label for="room" class="form-label">Room/Location</label>
                        <input type="text" id="room" name="room" class="form-input" 
                               placeholder="e.g. Room 101, Playground, Auditorium">
                    </div>
                    
                    <div class="form-group-modern form-group-full">
                        <label for="description" class="form-label">Description</label>
                        <textarea id="description" name="description" class="form-textarea" rows="3" 
                                  placeholder="Optional notes about this activity"></textarea>
                    </div>
                </div>
                
                <div class="form-actions-modern">
                    <button type="submit" class="btn-modern btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>
                        Add Activity
                    </button>
                    <button type="button" class="btn-modern btn-ghost" data-modal-close>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Schedule Modal -->
<div id="edit-schedule-modal" class="modal-modern">
    <div class="modal-overlay"></div>
    <div class="modal-container">
        <div class="modal-header-modern">
            <div class="modal-title-section">
                <h3 class="modal-title">Edit Schedule Activity</h3>
                <p class="modal-subtitle">Update the schedule activity details</p>
            </div>
            <button type="button" class="modal-close-modern" data-modal-close>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        
        <div class="modal-body-modern">
            <form method="POST" class="form-modern">
                <input type="hidden" name="action" value="update_schedule">
                <input type="hidden" id="edit_schedule_id" name="schedule_id">
                
                <div class="form-grid-modern">
                    <div class="form-group-modern">
                        <label for="edit_activity_name" class="form-label">Activity Name *</label>
                        <input type="text" id="edit_activity_name" name="activity_name" class="form-input" required 
                               placeholder="e.g. Mathematics, Recess, Assembly">
                    </div>
                    
                    <div class="form-group-modern">
                        <label for="edit_activity_type" class="form-label">Activity Type *</label>
                        <div class="select-wrapper">
                            <select id="edit_activity_type" name="activity_type" class="form-select" required>
                                <option value="">Select Type</option>
                                <option value="Subject">Subject</option>
                                <option value="Break">Break</option>
                                <option value="Activity">Activity</option>
                                <option value="Assembly">Assembly</option>
                                <option value="Other">Other</option>
                            </select>
                            <svg class="select-arrow" width="12" height="8" viewBox="0 0 12 8">
                                <path d="M1 1L6 6L11 1" stroke="currentColor" stroke-width="1.5" fill="none"/>
                            </svg>
                        </div>
                    </div>
                    
                    <div class="form-group-modern">
                        <label for="edit_teacher_id" class="form-label">Teacher (Optional)</label>
                        <div class="select-wrapper">
                            <select id="edit_teacher_id" name="teacher_id" class="form-select">
                                <option value="">No Teacher Required</option>
                                <?php foreach ($all_teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>">
                                        <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <svg class="select-arrow" width="12" height="8" viewBox="0 0 12 8">
                                <path d="M1 1L6 6L11 1" stroke="currentColor" stroke-width="1.5" fill="none"/>
                            </svg>
                        </div>
                    </div>
                    
                    <div class="form-group-modern">
                        <label for="edit_day_of_week" class="form-label">Day *</label>
                        <div class="select-wrapper">
                            <select id="edit_day_of_week" name="day_of_week" class="form-select" required>
                                <option value="">Select Day</option>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                            </select>
                            <svg class="select-arrow" width="12" height="8" viewBox="0 0 12 8">
                                <path d="M1 1L6 6L11 1" stroke="currentColor" stroke-width="1.5" fill="none"/>
                            </svg>
                        </div>
                    </div>
                    
                    <div class="form-group-modern">
                        <label for="edit_start_time" class="form-label">Start Time *</label>
                        <input type="time" id="edit_start_time" name="start_time" class="form-input" required>
                    </div>
                    
                    <div class="form-group-modern">
                        <label for="edit_end_time" class="form-label">End Time *</label>
                        <input type="time" id="edit_end_time" name="end_time" class="form-input" required>
                    </div>
                    
                    <div class="form-group-modern">
                        <label for="edit_room" class="form-label">Room/Location</label>
                        <input type="text" id="edit_room" name="room" class="form-input" 
                               placeholder="e.g. Room 101, Playground, Auditorium">
                    </div>
                    
                    <div class="form-group-modern form-group-full">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea id="edit_description" name="description" class="form-textarea" rows="3" 
                                  placeholder="Optional notes about this activity"></textarea>
                    </div>
                </div>
                
                <div class="form-actions-modern">
                    <button type="submit" class="btn-modern btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                        Update Activity
                    </button>
                    <button type="button" class="btn-modern btn-ghost" data-modal-close>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Tab Navigation
document.addEventListener('DOMContentLoaded', function() {
    initializeModals();
    initializeTabs();
});

function initializeTabs() {
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const targetTab = this.dataset.tab;
            
            // Update button states
            tabBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Show target tab content
            tabContents.forEach(content => {
                content.classList.remove('active');
                if (content.id === targetTab + '-tab') {
                    content.classList.add('active');
                }
            });
        });
    });
}

function initializeModals() {
    // Modal open/close functionality
    const modalTriggers = document.querySelectorAll('[data-modal-target]');
    const modalCloses = document.querySelectorAll('.modal-close-modern, [data-modal-close]');
    const modals = document.querySelectorAll('.modal-modern');
    
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function() {
            const targetModal = document.getElementById(this.dataset.modalTarget);
            if (targetModal) {
                openModal(targetModal);
            }
        });
    });
    
    modalCloses.forEach(close => {
        close.addEventListener('click', function() {
            const modal = this.closest('.modal-modern');
            if (modal) {
                closeModal(modal);
            }
        });
    });
    
    modals.forEach(modal => {
        const overlay = modal.querySelector('.modal-overlay');
        if (overlay) {
            overlay.addEventListener('click', function() {
                closeModal(modal);
            });
        }
    });
    
    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.modal-modern.modal-open');
            if (openModal) {
                closeModal(openModal);
            }
        }
    });
}

function openModal(modal) {
    modal.classList.add('modal-open');
    document.body.style.overflow = 'hidden';
}

function closeModal(modal) {
    modal.classList.remove('modal-open');
    document.body.style.overflow = '';
    
    // Reset add teacher form if it's the add teacher modal
    if (modal.id === 'addTeacherModal') {
        const form = modal.querySelector('#addTeacherForm');
        if (form) {
            form.reset();
            resetPrimaryCheckbox();
        }
    }
}

function resetPrimaryCheckbox() {
    const checkbox = document.getElementById('is_primary_checkbox');
    
    if (checkbox) {
        checkbox.checked = false;
    }
    
    // Reset teacher dropdown to show all teachers
    const teacherSelect = document.getElementById('teacher_id');
    if (teacherSelect) {
        filterTeacherOptions(false);
    }
}

// Filter teacher options based on primary checkbox state
function filterTeacherOptions(isPrimaryChecked) {
    const teacherSelect = document.getElementById('teacher_id');
    if (!teacherSelect) return;
    
    const options = teacherSelect.querySelectorAll('option');
    const selectedValue = teacherSelect.value;
    
    options.forEach(option => {
        if (option.value === '') return; // Skip placeholder
        
        const isPrimaryElsewhere = option.getAttribute('data-is-primary-elsewhere') === '1';
        
        if (isPrimaryChecked && isPrimaryElsewhere) {
            // Hide teachers who are already primary elsewhere
            option.style.display = 'none';
            option.disabled = true;
            
            // Clear selection if this was selected
            if (option.value === selectedValue) {
                teacherSelect.value = '';
            }
        } else {
            // Show all teachers
            option.style.display = '';
            option.disabled = false;
        }
    });
}

// Handle primary checkbox change
document.addEventListener('DOMContentLoaded', function() {
    const checkbox = document.getElementById('is_primary_checkbox');
    
    if (checkbox) {
        checkbox.addEventListener('change', function() {
            filterTeacherOptions(this.checked);
        });
    }
});

// Handle teacher selection change
document.addEventListener('DOMContentLoaded', function() {
    const teacherSelect = document.getElementById('teacher_id');
    
    if (teacherSelect) {
        teacherSelect.addEventListener('change', function() {
            // No longer need to disable checkbox based on teacher selection
        });
    }
});

</script>

<style>
/* Section Management Styles */
:root {
    --primary-blue: #2e86ab;
    --dark-blue: #1c5f78;
    --accent-blue: #a8cef0;
    --light-blue: #f1f9ff;
    --success: #28a745;
    --danger: #dc3545;
    --warning: #ffc107;
    --white: #ffffff;
    --black: #2c3e50;
    --gray: #6c757d;
    --light-gray: #f8f9fa;
    --border-gray: #dee2e6;
    --shadow-light: rgba(0, 0, 0, 0.08);
    --shadow-medium: rgba(0, 0, 0, 0.15);
}

.page-header-modern {
    background: linear-gradient(135deg, var(--white) 0%, var(--light-blue) 100%);
    border-radius: 20px;
    padding: 2rem 2.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 20px var(--shadow-light);
    border: 1px solid var(--border-gray);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.page-title-section {
    flex: 1;
}

.page-title-wrapper {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 0.5rem;
}

.page-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--dark-blue);
    margin: 0;
    letter-spacing: -0.02em;
}

.page-breadcrumb {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    color: var(--gray);
}

.breadcrumb-item {
    color: var(--primary-blue);
    text-decoration: none;
    font-weight: 500;
}

.breadcrumb-item:hover {
    color: var(--dark-blue);
}

.breadcrumb-item.current {
    color: var(--gray);
    font-weight: 600;
}

.breadcrumb-separator {
    color: var(--border-gray);
}

.page-description {
    font-size: 1.1rem;
    color: var(--gray);
    margin: 0;
    line-height: 1.5;
}

.page-actions {
    display: flex;
    gap: 1rem;
}

/* Section Info Card */
.section-info-card {
    background: var(--white);
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 2px 12px var(--shadow-light);
    border: 1px solid var(--border-gray);
}

.section-info-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.section-info-title {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.section-info-title h2 {
    font-size: 2rem;
    font-weight: 700;
    color: var(--dark-blue);
    margin: 0;
}

.section-badge {
    background: linear-gradient(135deg, var(--primary-blue), var(--accent-blue));
    color: var(--white);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
}

.section-info-stats {
    display: flex;
    gap: 2rem;
}

.stat-item {
    text-align: center;
}

.stat-number {
    font-size: 2rem;
    font-weight: 800;
    color: var(--primary-blue);
    line-height: 1;
}

.stat-label {
    font-size: 0.9rem;
    color: var(--gray);
    font-weight: 500;
}

.section-info-description p {
    color: var(--gray);
    font-size: 1rem;
    line-height: 1.6;
    margin: 0;
}

/* Alert Modern */
.alert-modern {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    font-weight: 500;
}

.alert-success {
    background: #e8f5e8;
    color: #2e7d32;
    border: 1px solid #c8e6c9;
}

.alert-error {
    background: #ffebee;
    color: #c62828;
    border: 1px solid #ffcdd2;
}

/* Management Container */
.management-container {
    background: var(--white);
    border-radius: 16px;
    box-shadow: 0 2px 12px var(--shadow-light);
    border: 1px solid var(--border-gray);
    overflow: hidden;
}

/* Tab Navigation */
.tab-navigation {
    display: flex;
    background: var(--light-gray);
    border-bottom: 1px solid var(--border-gray);
}

.tab-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 1rem 1.5rem;
    border: none;
    background: transparent;
    font-size: 1rem;
    font-weight: 600;
    color: var(--gray);
    cursor: pointer;
    transition: all 0.2s ease;
    border-bottom: 3px solid transparent;
}

.tab-btn:hover {
    background: var(--white);
    color: var(--primary-blue);
}

.tab-btn.active {
    background: var(--white);
    color: var(--primary-blue);
    border-bottom-color: var(--primary-blue);
}

/* Tab Content */
.tab-content {
    display: none;
    padding: 2rem;
}

.tab-content.active {
    display: block;
}

/* Section Management */
.section-management {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.management-section {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-gray);
}

.section-header h3 {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--dark-blue);
    margin: 0;
}

/* Teachers/Students Lists */
.teachers-list, .students-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.teacher-card, .student-card {
    background: var(--light-gray);
    border: 1px solid var(--border-gray);
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.2s ease;
}

.teacher-card:hover, .student-card:hover {
    box-shadow: 0 4px 12px var(--shadow-light);
    border-color: var(--primary-blue);
}

.teacher-info, .student-info {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
}

.teacher-avatar, .student-avatar {
    width: 50px;
    height: 50px;
    background: var(--primary-blue);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
}

.teacher-details, .student-details {
    flex: 1;
}

.teacher-name, .student-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--dark-blue);
    margin-bottom: 0.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.primary-badge {
    background: var(--warning);
    color: var(--white);
    padding: 0.2rem 0.5rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.teacher-meta, .student-meta {
    font-size: 0.9rem;
    color: var(--gray);
    margin-bottom: 0.25rem;
}

.teacher-assigned, .student-enrolled {
    font-size: 0.8rem;
    color: var(--gray);
}

.teacher-actions, .student-actions {
    display: flex;
    gap: 0.5rem;
}

/* Buttons */
.btn-modern {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 10px;
    font-size: 0.95rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.btn-modern.btn-primary {
    background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
    color: var(--white);
    box-shadow: 0 4px 12px rgba(46, 134, 171, 0.3);
}

.btn-modern.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(46, 134, 171, 0.4);
}

.btn-modern.btn-ghost {
    background: transparent;
    color: var(--gray);
    border: 2px solid var(--border-gray);
}

.btn-modern.btn-ghost:hover {
    background: var(--light-gray);
    border-color: var(--primary-blue);
    color: var(--primary-blue);
}

.btn-modern.btn-danger {
    background: linear-gradient(135deg, var(--danger) 0%, #c0392b 100%);
    color: var(--white);
}

.btn-modern.btn-danger:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
}

.btn-modern.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.85rem;
}

.btn-modern:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
    box-shadow: none !important;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--gray);
    background: var(--light-gray);
    border-radius: 12px;
    border: 2px dashed var(--border-gray);
}

.empty-state svg {
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h4 {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--black);
    margin-bottom: 0.5rem;
}

.empty-state p {
    margin-bottom: 0;
}

/* Modal Modern */
.modal-modern {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.modal-modern.modal-open {
    display: flex;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
}

.modal-container {
    position: relative;
    background: var(--white);
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    width: 100%;
    max-width: 500px;
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.modal-header-modern {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 2rem 2rem 1rem;
    border-bottom: 1px solid var(--border-gray);
}

.modal-title-section {
    flex: 1;
}

.modal-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--dark-blue);
    margin: 0 0 0.25rem 0;
}

.modal-subtitle {
    font-size: 0.9rem;
    color: var(--gray);
    margin: 0;
}

.modal-close-modern {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 8px;
    color: var(--gray);
    transition: all 0.2s ease;
}

.modal-close-modern:hover {
    background: var(--light-gray);
    color: var(--black);
}

.modal-body-modern {
    padding: 2rem;
    overflow-y: auto;
    flex: 1;
}

/* Form Modern */
.form-modern {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.form-group-modern {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-label {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--black);
}

.form-select {
    padding: 0.75rem 1rem;
    border: 2px solid var(--border-gray);
    border-radius: 8px;
    font-size: 0.95rem;
    background: var(--white);
    color: var(--black);
    appearance: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.form-select:focus {
    outline: none;
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 3px rgba(46, 134, 171, 0.1);
}

.select-wrapper {
    position: relative;
}

.select-arrow {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    color: var(--gray);
}

.form-help {
    font-size: 0.8rem;
    color: var(--gray);
}

.checkbox-group-modern {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.checkbox-label-modern {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    font-weight: 500;
    transition: opacity 0.2s ease;
}

.checkbox-input {
    margin: 0;
}

.checkbox-input:disabled + .checkbox-custom {
    background-color: var(--light-gray);
    border-color: var(--border-gray);
    cursor: not-allowed;
}

.checkbox-custom {
    width: 20px;
    height: 20px;
    border: 2px solid var(--border-gray);
    border-radius: 4px;
    position: relative;
    transition: all 0.2s ease;
}

.checkbox-input:checked + .checkbox-custom {
    background: var(--primary-blue);
    border-color: var(--primary-blue);
}

.checkbox-input:disabled:checked + .checkbox-custom {
    background-color: var(--gray);
    border-color: var(--gray);
}

.checkbox-input:checked + .checkbox-custom::after {
    content: '✓';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 12px;
    font-weight: bold;
}

.checkbox-text {
    color: var(--black);
}

.form-help {
    font-size: 0.875rem;
    color: var(--gray);
    margin-top: 0.25rem;
}

.form-actions-modern {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    padding-top: 1rem;
    border-top: 1px solid var(--border-gray);
}

/* Responsive Design */
@media (max-width: 768px) {
    .page-header-modern {
        flex-direction: column;
        gap: 1rem;
        padding: 1.5rem;
    }
    
    .page-title {
        font-size: 2rem;
    }
    
    .section-info-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .tab-navigation {
        flex-direction: column;
    }
    
    .tab-content {
        padding: 1rem;
    }
    
    .teacher-card, .student-card {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .teacher-actions, .student-actions {
        width: 100%;
        justify-content: flex-end;
    }
    
    .section-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .modal-container {
        margin: 1rem;
        max-width: calc(100vw - 2rem);
    }
    
    .modal-body-modern {
        padding: 1rem;
    }
    
    .form-actions-modern {
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .page-title {
        font-size: 1.5rem;
    }
    
    .section-info-title {
        flex-direction: column;
        gap: 0.5rem;
        align-items: flex-start;
    }
    
    .section-info-stats {
        gap: 1rem;
    }
}
</style>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
