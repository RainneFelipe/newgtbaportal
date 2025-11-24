<?php
require_once '../includes/auth_check.php';

// Check if user is a student
if (!checkRole('student')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$page_title = 'My Section - GTBA Portal';
$base_url = '../';

try {
    $database = new Database();
    $db = $database->connect();
    
    // Get student's section information
    $query = "SELECT s.*, sec.section_name, sec.current_enrollment, sec.room_number,
                     gl.grade_name, sy.year_label,
                     t.first_name as adviser_fname, t.last_name as adviser_lname
              FROM students s
              LEFT JOIN sections sec ON s.current_section_id = sec.id
              LEFT JOIN grade_levels gl ON s.current_grade_level_id = gl.id
              LEFT JOIN school_years sy ON s.current_school_year_id = sy.id
              LEFT JOIN section_teachers st ON sec.id = st.section_id AND st.is_primary = 1 AND st.is_active = 1
              LEFT JOIN teachers t ON st.teacher_id = t.user_id AND t.is_active = 1
              WHERE s.user_id = :user_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    
    $student_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get all teachers assigned to the section
    if ($student_info && $student_info['current_section_id']) {
        $query = "SELECT st.*, t.first_name, t.last_name, st.is_primary,
                         u.email as teacher_email
                  FROM section_teachers st
                  LEFT JOIN teachers t ON st.teacher_id = t.user_id
                  LEFT JOIN users u ON t.user_id = u.id
                  WHERE st.section_id = :section_id 
                  AND st.is_active = 1
                  AND t.is_active = 1
                  ORDER BY st.is_primary DESC, t.last_name, t.first_name";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':section_id', $student_info['current_section_id']);
        $stmt->execute();
        
        $section_teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get classmates (other students in the same section)
    if ($student_info && $student_info['current_section_id']) {
        $query = "SELECT s.first_name, s.last_name, s.middle_name, s.student_id
                  FROM students s
                  WHERE s.current_section_id = :section_id 
                  AND s.id != (SELECT id FROM students WHERE user_id = :user_id)
                  ORDER BY s.last_name, s.first_name";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':section_id', $student_info['current_section_id']);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        
        $classmates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    $error_message = "Unable to load section information.";
    error_log("Student section error: " . $e->getMessage());
}

ob_start();
?>

<div class="welcome-section">
    <h1 class="welcome-title">My Section</h1>
    <p class="welcome-subtitle">View information about your class section</p>
</div>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <?php echo $error_message; ?>
    </div>
<?php elseif (!$student_info || !$student_info['current_section_id']): ?>
    <div class="alert alert-warning">
        You are not yet assigned to a section. Please contact the registrar's office.
    </div>
<?php else: ?>
    <!-- Section Information -->
    <div style="background: var(--white); border-radius: 15px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
        <h3 style="color: var(--dark-blue); margin-bottom: 1.5rem;">Section Information</h3>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
            <div style="background: var(--light-blue); padding: 1.5rem; border-radius: 10px;">
                <h4 style="color: var(--dark-blue); margin-bottom: 1rem;">Basic Details</h4>
                <p style="margin: 0.5rem 0; color: var(--black);">
                    <strong>Section Name:</strong> <?php echo htmlspecialchars($student_info['section_name']); ?>
                </p>
                <p style="margin: 0.5rem 0; color: var(--black);">
                    <strong>Grade Level:</strong> <?php echo htmlspecialchars($student_info['grade_name']); ?>
                </p>
                <p style="margin: 0.5rem 0; color: var(--black);">
                    <strong>School Year:</strong> <?php echo htmlspecialchars($student_info['year_label']); ?>
                </p>
            </div>
            
            <div style="background: var(--light-blue); padding: 1.5rem; border-radius: 10px;">
                <h4 style="color: var(--dark-blue); margin-bottom: 1rem;">Section Stats</h4>
                <p style="margin: 0.5rem 0; color: var(--black);">
                    
                </p>
                <?php if ($student_info['room_number']): ?>
                <p style="margin: 0.5rem 0; color: var(--black);">
                    <strong>Room Number:</strong> <?php echo htmlspecialchars($student_info['room_number']); ?>
                </p>
                <?php endif; ?>
            </div>
            
            <?php if (isset($student_info['adviser_fname']) && $student_info['adviser_fname']): ?>
                <div style="background: var(--light-blue); padding: 1.5rem; border-radius: 10px;">
                    <h4 style="color: var(--dark-blue); margin-bottom: 1rem;">Section Adviser</h4>
                    <p style="margin: 0.5rem 0; color: var(--black); font-size: 1.1rem;">
                        <strong><?php echo htmlspecialchars($student_info['adviser_fname'] . ' ' . $student_info['adviser_lname']); ?></strong>
                    </p>
                    <p style="margin: 0.5rem 0; color: var(--gray); font-size: 0.9rem;">
                        Your section adviser is responsible for your academic guidance and class management.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Section Teachers -->
    <?php if (isset($section_teachers) && !empty($section_teachers)): ?>
        <div style="background: var(--white); border-radius: 15px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
            <h3 style="color: var(--dark-blue); margin-bottom: 1.5rem;">Section Teachers</h3>
            <p style="color: var(--gray); margin-bottom: 2rem;">Teachers assigned to your section.</p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                <?php foreach ($section_teachers as $teacher): ?>
                    <div style="background: var(--light-blue); padding: 1.5rem; border-radius: 10px; border-left: 4px solid <?php echo $teacher['is_primary'] ? 'var(--primary-blue)' : 'var(--success)'; ?>;">
                        <div style="display: flex; justify-content: between; align-items: flex-start; margin-bottom: 1rem;">
                            <div style="flex: 1;">
                                <div style="font-weight: 700; color: var(--dark-blue); font-size: 1.1rem; margin-bottom: 0.5rem;">
                                    <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                    <?php if ($teacher['is_primary']): ?>
                                        <span style="background: var(--primary-blue); color: var(--white); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; margin-left: 0.5rem;">
                                            PRIMARY
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($teacher['teacher_email']): ?>
                                    <div style="color: var(--gray); font-size: 0.85rem; margin-bottom: 0.5rem;">
                                        <i class="fas fa-envelope" style="margin-right: 0.5rem;"></i>
                                        <?php echo htmlspecialchars($teacher['teacher_email']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <div style="background: var(--white); border-radius: 15px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
            <h3 style="color: var(--dark-blue); margin-bottom: 1rem;">Section Teachers</h3>
            <div class="alert alert-warning">
                No teachers are currently assigned to your section, or teacher information is not yet available.
            </div>
        </div>
    <?php endif; ?>

    <!-- Classmates List -->
    <?php if (isset($classmates) && !empty($classmates)): ?>
        <div style="background: var(--white); border-radius: 15px; padding: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
            <h3 style="color: var(--dark-blue); margin-bottom: 1.5rem;">Classmates</h3>
            <p style="color: var(--gray); margin-bottom: 2rem;">Your section has <?php echo count($classmates); ?> other students.</p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                <?php foreach ($classmates as $classmate): ?>
                    <div style="background: var(--light-blue); padding: 1rem; border-radius: 8px; border-left: 4px solid var(--primary-blue);">
                        <div style="font-weight: 600; color: var(--dark-blue); margin-bottom: 0.25rem;">
                            <?php echo htmlspecialchars($classmate['first_name'] . ' ' . ($classmate['middle_name'] ? substr($classmate['middle_name'], 0, 1) . '. ' : '') . $classmate['last_name']); ?>
                        </div>
                        <div style="color: var(--gray); font-size: 0.9rem;">
                            
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <div style="background: var(--white); border-radius: 15px; padding: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
            <h3 style="color: var(--dark-blue); margin-bottom: 1rem;">Classmates</h3>
            <div class="alert alert-warning">
                No other students are currently enrolled in your section, or the classmate list is not yet available.
            </div>
        </div>
    <?php endif; ?>
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
