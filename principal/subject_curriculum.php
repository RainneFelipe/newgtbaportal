<?php
require_once '../includes/auth_check.php';

// Check if user is a principal
if (!checkRole('principal')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$page_title = 'Subject & Curriculum Management - GTBA Portal';
$base_url = '../';

$database = new Database();
$db = $database->connect();

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_subject':
                    $subject_code = trim($_POST['subject_code']);
                    $subject_name = trim($_POST['subject_name']);
                    $description = trim($_POST['description']);
                    
                    // Check if subject code already exists
                    $check_query = "SELECT COUNT(*) FROM subjects WHERE subject_code = ?";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->execute([$subject_code]);
                    
                    if ($check_stmt->fetchColumn() > 0) {
                        $message = "Subject code already exists!";
                        $messageType = 'error';
                    } else {
                        $query = "INSERT INTO subjects (subject_code, subject_name, description) VALUES (?, ?, ?)";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$subject_code, $subject_name, $description]);
                        $message = "Subject added successfully!";
                        $messageType = 'success';
                    }
                    break;
                    
                case 'edit_subject':
                    $subject_id = $_POST['subject_id'];
                    $subject_code = trim($_POST['subject_code']);
                    $subject_name = trim($_POST['subject_name']);
                    $description = trim($_POST['description']);
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    
                    // Check if subject code already exists for other subjects
                    $check_query = "SELECT COUNT(*) FROM subjects WHERE subject_code = ? AND id != ?";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->execute([$subject_code, $subject_id]);
                    
                    if ($check_stmt->fetchColumn() > 0) {
                        $message = "Subject code already exists!";
                        $messageType = 'error';
                    } else {
                        $query = "UPDATE subjects SET subject_code = ?, subject_name = ?, description = ?, is_active = ? WHERE id = ?";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$subject_code, $subject_name, $description, $is_active, $subject_id]);
                        $message = "Subject updated successfully!";
                        $messageType = 'success';
                    }
                    break;
                    
                case 'assign_curriculum':
                    $grade_level_id = $_POST['grade_level_id'];
                    $subject_id = $_POST['subject_id'];
                    $school_year_id = $_POST['school_year_id'];
                    $is_required = isset($_POST['is_required']) ? 1 : 0;
                    $order_sequence = $_POST['order_sequence'] ?: 1;
                    
                    // Check if curriculum assignment already exists
                    $check_query = "SELECT COUNT(*) FROM curriculum WHERE grade_level_id = ? AND subject_id = ? AND school_year_id = ?";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->execute([$grade_level_id, $subject_id, $school_year_id]);
                    
                    if ($check_stmt->fetchColumn() > 0) {
                        $message = "Subject is already assigned to this grade level for this school year!";
                        $messageType = 'error';
                    } else {
                        $query = "INSERT INTO curriculum (grade_level_id, subject_id, school_year_id, is_required, order_sequence, created_by) VALUES (?, ?, ?, ?, ?, ?)";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$grade_level_id, $subject_id, $school_year_id, $is_required, $order_sequence, $_SESSION['user_id']]);
                        $message = "Subject assigned to curriculum successfully!";
                        $messageType = 'success';
                    }
                    break;
                    
                case 'delete_subject':
                    $subject_id = $_POST['subject_id'];
                    
                    // Check if subject is being used in curriculum
                    $check_query = "SELECT COUNT(*) FROM curriculum WHERE subject_id = ?";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->execute([$subject_id]);
                    $curriculum_count = $check_stmt->fetchColumn();
                    
                    // Check if subject is being used in class schedules
                    $check_query = "SELECT COUNT(*) FROM class_schedules WHERE subject_id = ? AND is_active = 1";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->execute([$subject_id]);
                    $schedule_count = $check_stmt->fetchColumn();
                    
                    if ($curriculum_count > 0 || $schedule_count > 0) {
                        $message = "Cannot delete subject! It is currently assigned to " . 
                                  ($curriculum_count > 0 ? $curriculum_count . " curriculum(s)" : "") .
                                  ($curriculum_count > 0 && $schedule_count > 0 ? " and " : "") .
                                  ($schedule_count > 0 ? $schedule_count . " schedule(s)" : "") . 
                                  ". Please remove all assignments first or deactivate the subject instead.";
                        $messageType = 'error';
                    } else {
                        $query = "DELETE FROM subjects WHERE id = ?";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$subject_id]);
                        $message = "Subject deleted successfully!";
                        $messageType = 'success';
                    }
                    break;
                    
                case 'remove_curriculum':
                    $curriculum_id = $_POST['curriculum_id'];
                    $query = "DELETE FROM curriculum WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$curriculum_id]);
                    $message = "Subject removed from curriculum successfully!";
                    $messageType = 'success';
                    break;
                    
                case 'update_curriculum':
                    $curriculum_id = $_POST['curriculum_id'];
                    $is_required = isset($_POST['is_required']) ? 1 : 0;
                    $order_sequence = $_POST['order_sequence'] ?: 1;
                    
                    $query = "UPDATE curriculum SET is_required = ?, order_sequence = ? WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$is_required, $order_sequence, $curriculum_id]);
                    $message = "Curriculum assignment updated successfully!";
                    $messageType = 'success';
                    break;
            }
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Get statistics
try {
    $stats_query = "SELECT 
                    (SELECT COUNT(*) FROM subjects WHERE is_active = 1) as active_subjects,
                    (SELECT COUNT(*) FROM subjects WHERE is_active = 0) as inactive_subjects,
                    (SELECT COUNT(*) FROM curriculum) as total_assignments,
                    (SELECT COUNT(DISTINCT grade_level_id) FROM curriculum) as grades_with_subjects";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stats = ['active_subjects' => 0, 'inactive_subjects' => 0, 'total_assignments' => 0, 'grades_with_subjects' => 0];
}

// Get all subjects
$subjects_query = "SELECT * FROM subjects ORDER BY is_active DESC, subject_code ASC";
$subjects_stmt = $db->prepare($subjects_query);
$subjects_stmt->execute();
$subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all grade levels
$grades_query = "SELECT * FROM grade_levels WHERE is_active = 1 ORDER BY grade_order ASC";
$grades_stmt = $db->prepare($grades_query);
$grades_stmt->execute();
$grade_levels = $grades_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get school years
$years_query = "SELECT * FROM school_years ORDER BY is_active DESC, year_label DESC";
$years_stmt = $db->prepare($years_query);
$years_stmt->execute();
$school_years = $years_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get curriculum assignments with details
$curriculum_query = "SELECT c.*, s.subject_code, s.subject_name, gl.grade_name, sy.year_label, s.is_active as subject_active
                     FROM curriculum c
                     JOIN subjects s ON c.subject_id = s.id
                     JOIN grade_levels gl ON c.grade_level_id = gl.id
                     JOIN school_years sy ON c.school_year_id = sy.id
                     ORDER BY sy.is_active DESC, gl.grade_order ASC, c.order_sequence ASC";
$curriculum_stmt = $db->prepare($curriculum_query);
$curriculum_stmt->execute();
$curriculum_assignments = $curriculum_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get grade levels with their assigned subjects (for overview tab)
$grade_overview_query = "SELECT gl.*, sy.year_label, sy.is_active as sy_active
                        FROM grade_levels gl
                        CROSS JOIN school_years sy
                        WHERE gl.is_active = 1
                        ORDER BY sy.is_active DESC, sy.year_label DESC, gl.grade_order ASC";
$grade_overview_stmt = $db->prepare($grade_overview_query);
$grade_overview_stmt->execute();
$grade_overview = $grade_overview_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get subjects assigned to each grade level and school year
$subjects_by_grade_query = "SELECT c.*, s.subject_code, s.subject_name, s.is_active as subject_active,
                           gl.id as grade_id, gl.grade_name, sy.id as school_year_id, sy.year_label
                           FROM curriculum c
                           JOIN subjects s ON c.subject_id = s.id
                           JOIN grade_levels gl ON c.grade_level_id = gl.id
                           JOIN school_years sy ON c.school_year_id = sy.id
                           ORDER BY sy.is_active DESC, gl.grade_order ASC, c.order_sequence ASC";
$subjects_by_grade_stmt = $db->prepare($subjects_by_grade_query);
$subjects_by_grade_stmt->execute();
$subjects_by_grade = $subjects_by_grade_stmt->fetchAll(PDO::FETCH_ASSOC);

// Group subjects by grade and school year
$grouped_subjects = [];
foreach ($subjects_by_grade as $item) {
    $key = $item['school_year_id'] . '_' . $item['grade_id'];
    if (!isset($grouped_subjects[$key])) {
        $grouped_subjects[$key] = [
            'grade_name' => $item['grade_name'],
            'year_label' => $item['year_label'],
            'subjects' => []
        ];
    }
    $grouped_subjects[$key]['subjects'][] = $item;
}

ob_start();
?>

<div class="welcome-section">
    <h1 class="welcome-title">Subject & Curriculum Management</h1>
    <p class="welcome-subtitle">Manage subjects and curriculum assignments for different grade levels</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="stat-card" style="background: var(--white); border-radius: 15px; padding: 1.5rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08); text-align: center;">
        <h3 style="color: var(--primary-blue); font-size: 2rem; margin: 0;"><?php echo $stats['active_subjects']; ?></h3>
        <p style="color: var(--gray); margin: 0.5rem 0 0 0;">Active Subjects</p>
    </div>
    <div class="stat-card" style="background: var(--white); border-radius: 15px; padding: 1.5rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08); text-align: center;">
        <h3 style="color: var(--warning); font-size: 2rem; margin: 0;"><?php echo $stats['inactive_subjects']; ?></h3>
        <p style="color: var(--gray); margin: 0.5rem 0 0 0;">Inactive Subjects</p>
    </div>
    <div class="stat-card" style="background: var(--white); border-radius: 15px; padding: 1.5rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08); text-align: center;">
        <h3 style="color: var(--success); font-size: 2rem; margin: 0;"><?php echo $stats['total_assignments']; ?></h3>
        <p style="color: var(--gray); margin: 0.5rem 0 0 0;">Total Assignments</p>
    </div>
    <div class="stat-card" style="background: var(--white); border-radius: 15px; padding: 1.5rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08); text-align: center;">
        <h3 style="color: var(--primary-blue); font-size: 2rem; margin: 0;"><?php echo $stats['grades_with_subjects']; ?></h3>
        <p style="color: var(--gray); margin: 0.5rem 0 0 0;">Grades with Subjects</p>
    </div>
</div>

<!-- Tabs Navigation -->
<div class="tabs-container" style="margin-bottom: 2rem;">
    <div class="tabs" style="display: flex; background: var(--white); border-radius: 15px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
        <button class="tab-btn active" onclick="showTab('subjects')" style="flex: 1; padding: 1rem; border: none; background: var(--primary-blue); color: white; cursor: pointer; transition: all 0.3s;">
            Manage Subjects
        </button>
        <button class="tab-btn" onclick="showTab('curriculum')" style="flex: 1; padding: 1rem; border: none; background: var(--light-gray); color: var(--black); cursor: pointer; transition: all 0.3s;">
            Curriculum Assignments
        </button>
        <button class="tab-btn" onclick="showTab('assign')" style="flex: 1; padding: 1rem; border: none; background: var(--light-gray); color: var(--black); cursor: pointer; transition: all 0.3s;">
            Assign to Curriculum
        </button>
    </div>
</div>

<!-- Subjects Tab -->
<div id="subjects-tab" class="tab-content" style="background: var(--white); border-radius: 15px; padding: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <h2 style="margin: 0; color: var(--black);">Subjects Management</h2>
        <button onclick="showAddSubjectModal()" style="background: var(--primary-blue); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600;">
            + Add New Subject
        </button>
    </div>
    
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--light-blue); border-bottom: 2px solid var(--primary-blue);">
                    <th style="padding: 1rem; text-align: left; color: var(--black); font-weight: 600;">Subject Code</th>
                    <th style="padding: 1rem; text-align: left; color: var(--black); font-weight: 600;">Subject Name</th>
                    <th style="padding: 1rem; text-align: left; color: var(--black); font-weight: 600;">Description</th>
                    <th style="padding: 1rem; text-align: center; color: var(--black); font-weight: 600;">Status</th>
                    <th style="padding: 1rem; text-align: center; color: var(--black); font-weight: 600;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subjects as $subject): ?>
                <tr style="border-bottom: 1px solid var(--light-gray);">
                    <td style="padding: 1rem; color: var(--black); font-weight: 600;"><?php echo htmlspecialchars($subject['subject_code']); ?></td>
                    <td style="padding: 1rem; color: var(--black);"><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                    <td style="padding: 1rem; color: var(--gray); max-width: 200px; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($subject['description'] ?: 'No description'); ?></td>
                    <td style="padding: 1rem; text-align: center;">
                        <span style="padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.875rem; font-weight: 600; 
                                     background: <?php echo $subject['is_active'] ? 'var(--success)' : 'var(--warning)'; ?>; 
                                     color: white;">
                            <?php echo $subject['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td style="padding: 1rem; text-align: center;">
                        <button onclick="editSubject(<?php echo htmlspecialchars(json_encode($subject)); ?>)" 
                                style="background: var(--warning); color: white; border: none; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer; margin-right: 0.5rem;">
                            Edit
                        </button>
                        <button onclick="deleteSubject(<?php echo $subject['id']; ?>, '<?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']); ?>')" 
                                style="background: var(--danger); color: white; border: none; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer;">
                            Delete
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Curriculum Tab -->
<div id="curriculum-tab" class="tab-content" style="display: none; background: var(--white); border-radius: 15px; padding: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
    <h2 style="margin-bottom: 2rem; color: var(--black);">Current Curriculum Assignments</h2>
    
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--light-blue); border-bottom: 2px solid var(--primary-blue);">
                    <th style="padding: 1rem; text-align: left; color: var(--black); font-weight: 600;">School Year</th>
                    <th style="padding: 1rem; text-align: left; color: var(--black); font-weight: 600;">Grade Level</th>
                    <th style="padding: 1rem; text-align: left; color: var(--black); font-weight: 600;">Subject</th>
                    <th style="padding: 1rem; text-align: center; color: var(--black); font-weight: 600;">Required</th>
                    <th style="padding: 1rem; text-align: center; color: var(--black); font-weight: 600;">Order</th>
                    <th style="padding: 1rem; text-align: center; color: var(--black); font-weight: 600;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($curriculum_assignments as $assignment): ?>
                <tr style="border-bottom: 1px solid var(--light-gray); <?php echo !$assignment['subject_active'] ? 'opacity: 0.6;' : ''; ?>">
                    <td style="padding: 1rem; color: var(--black); font-weight: 600;"><?php echo htmlspecialchars($assignment['year_label']); ?></td>
                    <td style="padding: 1rem; color: var(--black);"><?php echo htmlspecialchars($assignment['grade_name']); ?></td>
                    <td style="padding: 1rem; color: var(--black);">
                        <strong><?php echo htmlspecialchars($assignment['subject_code']); ?></strong> - 
                        <?php echo htmlspecialchars($assignment['subject_name']); ?>
                        <?php if (!$assignment['subject_active']): ?>
                            <span style="color: var(--warning); font-size: 0.875rem;">(Inactive Subject)</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 1rem; text-align: center;">
                        <span style="padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.875rem; font-weight: 600; 
                                     background: <?php echo $assignment['is_required'] ? 'var(--success)' : 'var(--gray)'; ?>; 
                                     color: white;">
                            <?php echo $assignment['is_required'] ? 'Required' : 'Elective'; ?>
                        </span>
                    </td>
                    <td style="padding: 1rem; text-align: center; color: var(--black);"><?php echo $assignment['order_sequence']; ?></td>
                    <td style="padding: 1rem; text-align: center;">
                        <button onclick="editCurriculum(<?php echo htmlspecialchars(json_encode($assignment)); ?>)" 
                                style="background: var(--warning); color: white; border: none; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer; margin-right: 0.5rem;">
                            Edit
                        </button>
                        <button onclick="removeCurriculum(<?php echo $assignment['id']; ?>)" 
                                style="background: var(--danger); color: white; border: none; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer;">
                            Remove
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Assign Tab -->
<div id="assign-tab" class="tab-content" style="display: none; background: var(--white); border-radius: 15px; padding: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
    <h2 style="margin-bottom: 2rem; color: var(--black);">Assign Subject to Curriculum</h2>
    
    <form method="POST" style="max-width: 600px;">
        <input type="hidden" name="action" value="assign_curriculum">
        
        <div style="margin-bottom: 1.5rem;">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--black); font-weight: 600;">School Year:</label>
            <select name="school_year_id" required style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem;">
                <option value="">Select School Year</option>
                <?php foreach ($school_years as $year): ?>
                <option value="<?php echo $year['id']; ?>" <?php echo $year['is_active'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($year['year_label']); ?> 
                    <?php echo $year['is_active'] ? '(Active)' : ''; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div style="margin-bottom: 1.5rem;">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--black); font-weight: 600;">Grade Level:</label>
            <select name="grade_level_id" required style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem;">
                <option value="">Select Grade Level</option>
                <?php foreach ($grade_levels as $grade): ?>
                <option value="<?php echo $grade['id']; ?>"><?php echo htmlspecialchars($grade['grade_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div style="margin-bottom: 1.5rem;">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--black); font-weight: 600;">Subject:</label>
            <select name="subject_id" required style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem;">
                <option value="">Select Subject</option>
                <?php foreach ($subjects as $subject): ?>
                    <?php if ($subject['is_active']): ?>
                    <option value="<?php echo $subject['id']; ?>">
                        <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']); ?>
                    </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div style="margin-bottom: 1.5rem;">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--black); font-weight: 600;">Order Sequence:</label>
            <input type="number" name="order_sequence" min="1" value="1" required 
                   style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem;">
        </div>
        
        <div style="margin-bottom: 1.5rem;">
            <label style="display: flex; align-items: center; color: var(--black); font-weight: 600; cursor: pointer;">
                <input type="checkbox" name="is_required" checked style="margin-right: 0.5rem; transform: scale(1.2);">
                Required Subject
            </label>
        </div>
        
        <button type="submit" style="background: var(--primary-blue); color: white; border: none; padding: 1rem 2rem; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 1rem;">
            Assign to Curriculum
        </button>
    </form>
</div>

<!-- Add Subject Modal -->
<div id="addSubjectModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 15px; width: 90%; max-width: 500px;">
        <h3 style="margin-top: 0; color: var(--black);">Add New Subject</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_subject">
            
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--black); font-weight: 600;">Subject Code:</label>
                <input type="text" name="subject_code" required 
                       style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem;">
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--black); font-weight: 600;">Subject Name:</label>
                <input type="text" name="subject_name" required 
                       style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem;">
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--black); font-weight: 600;">Description:</label>
                <textarea name="description" rows="3" 
                          style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem; resize: vertical;"></textarea>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" onclick="closeAddSubjectModal()" 
                        style="background: var(--gray); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer;">
                    Cancel
                </button>
                <button type="submit" 
                        style="background: var(--primary-blue); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600;">
                    Add Subject
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Subject Modal -->
<div id="editSubjectModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 15px; width: 90%; max-width: 500px;">
        <h3 style="margin-top: 0; color: var(--black);">Edit Subject</h3>
        <form method="POST" id="editSubjectForm">
            <input type="hidden" name="action" value="edit_subject">
            <input type="hidden" name="subject_id" id="edit_subject_id">
            
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--black); font-weight: 600;">Subject Code:</label>
                <input type="text" name="subject_code" id="edit_subject_code" required 
                       style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem;">
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--black); font-weight: 600;">Subject Name:</label>
                <input type="text" name="subject_name" id="edit_subject_name" required 
                       style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem;">
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--black); font-weight: 600;">Description:</label>
                <textarea name="description" id="edit_subject_description" rows="3" 
                          style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem; resize: vertical;"></textarea>
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <label style="display: flex; align-items: center; color: var(--black); font-weight: 600; cursor: pointer;">
                    <input type="checkbox" name="is_active" id="edit_is_active" style="margin-right: 0.5rem; transform: scale(1.2);">
                    Active Subject
                </label>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" onclick="closeEditSubjectModal()" 
                        style="background: var(--gray); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer;">
                    Cancel
                </button>
                <button type="submit" 
                        style="background: var(--primary-blue); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600;">
                    Update Subject
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Curriculum Modal -->
<div id="editCurriculumModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 15px; width: 90%; max-width: 500px;">
        <h3 style="margin-top: 0; color: var(--black);">Edit Curriculum Assignment</h3>
        <form method="POST" id="editCurriculumForm">
            <input type="hidden" name="action" value="update_curriculum">
            <input type="hidden" name="curriculum_id" id="edit_curriculum_id">
            
            <div style="margin-bottom: 1.5rem;">
                <p><strong>Subject:</strong> <span id="curriculum_subject_info"></span></p>
                <p><strong>Grade Level:</strong> <span id="curriculum_grade_info"></span></p>
                <p><strong>School Year:</strong> <span id="curriculum_year_info"></span></p>
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--black); font-weight: 600;">Order Sequence:</label>
                <input type="number" name="order_sequence" id="edit_order_sequence" min="1" required 
                       style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem;">
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <label style="display: flex; align-items: center; color: var(--black); font-weight: 600; cursor: pointer;">
                    <input type="checkbox" name="is_required" id="edit_is_required" style="margin-right: 0.5rem; transform: scale(1.2);">
                    Required Subject
                </label>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" onclick="closeEditCurriculumModal()" 
                        style="background: var(--gray); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer;">
                    Cancel
                </button>
                <button type="submit" 
                        style="background: var(--primary-blue); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600;">
                    Update Assignment
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.style.display = 'none';
    });
    
    // Remove active class from all tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.style.background = 'var(--light-gray)';
        btn.style.color = 'var(--black)';
        btn.classList.remove('active');
    });
    
    // Show selected tab content
    document.getElementById(tabName + '-tab').style.display = 'block';
    
    // Add active class to clicked button
    event.target.style.background = 'var(--primary-blue)';
    event.target.style.color = 'white';
    event.target.classList.add('active');
}

function showAddSubjectModal() {
    document.getElementById('addSubjectModal').style.display = 'block';
}

function closeAddSubjectModal() {
    document.getElementById('addSubjectModal').style.display = 'none';
}

function editSubject(subject) {
    document.getElementById('edit_subject_id').value = subject.id;
    document.getElementById('edit_subject_code').value = subject.subject_code;
    document.getElementById('edit_subject_name').value = subject.subject_name;
    document.getElementById('edit_subject_description').value = subject.description || '';
    document.getElementById('edit_is_active').checked = subject.is_active == 1;
    document.getElementById('editSubjectModal').style.display = 'block';
}

function closeEditSubjectModal() {
    document.getElementById('editSubjectModal').style.display = 'none';
}

function editCurriculum(assignment) {
    document.getElementById('edit_curriculum_id').value = assignment.id;
    document.getElementById('edit_order_sequence').value = assignment.order_sequence;
    document.getElementById('edit_is_required').checked = assignment.is_required == 1;
    
    document.getElementById('curriculum_subject_info').textContent = assignment.subject_code + ' - ' + assignment.subject_name;
    document.getElementById('curriculum_grade_info').textContent = assignment.grade_name;
    document.getElementById('curriculum_year_info').textContent = assignment.year_label;
    
    document.getElementById('editCurriculumModal').style.display = 'block';
}

function closeEditCurriculumModal() {
    document.getElementById('editCurriculumModal').style.display = 'none';
}

function removeCurriculum(curriculumId) {
    if (confirm('Are you sure you want to remove this subject from the curriculum?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="remove_curriculum">
            <input type="hidden" name="curriculum_id" value="${curriculumId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteSubject(subjectId, subjectInfo) {
    if (confirm('Are you sure you want to delete "' + subjectInfo + '"?\n\nWARNING: This action cannot be undone. The subject will be permanently deleted if it\'s not currently assigned to any curriculum or schedule.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_subject">
            <input type="hidden" name="subject_id" value="${subjectId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modals when clicking outside
document.addEventListener('click', function(event) {
    const modals = ['addSubjectModal', 'editSubjectModal', 'editCurriculumModal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
});
</script>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
