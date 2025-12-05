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
                    $grade_level_id = $_POST['grade_level_id'];
                    $description = trim($_POST['description']);
                    
                    // Check if subject code already exists
                    $check_query = "SELECT COUNT(*) FROM subjects WHERE subject_code = ?";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->execute([$subject_code]);
                    
                    if ($check_stmt->fetchColumn() > 0) {
                        $message = "Subject code already exists!";
                        $messageType = 'error';
                    } else {
                        $query = "INSERT INTO subjects (subject_code, subject_name, grade_level_id, description) VALUES (?, ?, ?, ?)";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$subject_code, $subject_name, $grade_level_id, $description]);
                        $message = "Subject added successfully!";
                        $messageType = 'success';
                    }
                    break;
                    
                case 'edit_subject':
                    $subject_id = $_POST['subject_id'];
                    $subject_code = trim($_POST['subject_code']);
                    $subject_name = trim($_POST['subject_name']);
                    $grade_level_id = $_POST['grade_level_id'];
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
                        $query = "UPDATE subjects SET subject_code = ?, subject_name = ?, grade_level_id = ?, description = ?, is_active = ? WHERE id = ?";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$subject_code, $subject_name, $grade_level_id, $description, $is_active, $subject_id]);
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
                    
                case 'assign_all_subjects':
                    $grade_level_id = $_POST['grade_level_id'];
                    $school_year_id = $_POST['school_year_id'];
                    $is_required = isset($_POST['is_required']) ? 1 : 0;
                    
                    // Get grade level info to determine subject prefix
                    $grade_query = "SELECT grade_name FROM grade_levels WHERE id = ?";
                    $grade_stmt = $db->prepare($grade_query);
                    $grade_stmt->execute([$grade_level_id]);
                    $grade_info = $grade_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$grade_info) {
                        $message = "Invalid grade level selected!";
                        $messageType = 'error';
                        break;
                    }
                    
                    // Determine grade prefix
                    $grade_name = $grade_info['grade_name'];
                    $gradePrefix = '';
                    if (stripos($grade_name, 'nursery') !== false) {
                        $gradePrefix = 'NURS-%';
                    } else if (stripos($grade_name, 'kindergarten') !== false) {
                        $gradePrefix = 'K-%';
                    } else {
                        preg_match('/Grade\s+(\d+)/i', $grade_name, $matches);
                        if (!empty($matches[1])) {
                            $gradePrefix = 'G' . $matches[1] . '-%';
                        }
                    }
                    
                    if (empty($gradePrefix)) {
                        $message = "Could not determine subject prefix for " . htmlspecialchars($grade_name);
                        $messageType = 'error';
                        break;
                    }
                    
                    // Get all subjects matching the grade prefix
                    $subjects_query = "SELECT id FROM subjects WHERE subject_code LIKE ? AND is_active = 1 ORDER BY subject_code";
                    $subjects_stmt = $db->prepare($subjects_query);
                    $subjects_stmt->execute([$gradePrefix]);
                    $matching_subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (empty($matching_subjects)) {
                        $message = "No subjects found for " . htmlspecialchars($grade_name) . "! Please create subjects with prefix " . str_replace('%', '', $gradePrefix) . " first.";
                        $messageType = 'error';
                        break;
                    }
                    
                    // Begin transaction
                    $db->beginTransaction();
                    
                    try {
                        $assigned_count = 0;
                        $skipped_count = 0;
                        $order = 1;
                        
                        foreach ($matching_subjects as $subject) {
                            // Check if already assigned
                            $check_query = "SELECT COUNT(*) FROM curriculum WHERE grade_level_id = ? AND subject_id = ? AND school_year_id = ?";
                            $check_stmt = $db->prepare($check_query);
                            $check_stmt->execute([$grade_level_id, $subject['id'], $school_year_id]);
                            
                            if ($check_stmt->fetchColumn() > 0) {
                                $skipped_count++;
                                continue;
                            }
                            
                            // Insert curriculum assignment
                            $insert_query = "INSERT INTO curriculum (grade_level_id, subject_id, school_year_id, is_required, order_sequence, created_by) VALUES (?, ?, ?, ?, ?, ?)";
                            $insert_stmt = $db->prepare($insert_query);
                            $insert_stmt->execute([$grade_level_id, $subject['id'], $school_year_id, $is_required, $order, $_SESSION['user_id']]);
                            
                            $assigned_count++;
                            $order++;
                        }
                        
                        $db->commit();
                        
                        $message = "Successfully assigned $assigned_count subject(s) to " . htmlspecialchars($grade_name) . "!";
                        if ($skipped_count > 0) {
                            $message .= " ($skipped_count already assigned)";
                        }
                        $messageType = 'success';
                        
                    } catch (Exception $e) {
                        $db->rollback();
                        $message = "Error assigning subjects: " . $e->getMessage();
                        $messageType = 'error';
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

// Pagination setup for subjects
$subjects_page = isset($_GET['subjects_page']) ? (int)$_GET['subjects_page'] : 1;
$subjects_per_page = 10;
$subjects_offset = ($subjects_page - 1) * $subjects_per_page;

// Pagination setup for curriculum
$curriculum_page = isset($_GET['curriculum_page']) ? (int)$_GET['curriculum_page'] : 1;
$curriculum_per_page = 15;
$curriculum_offset = ($curriculum_page - 1) * $curriculum_per_page;

// Get total count of subjects for pagination
$subjects_count_query = "SELECT COUNT(*) as total FROM subjects";
$subjects_count_stmt = $db->prepare($subjects_count_query);
$subjects_count_stmt->execute();
$total_subjects = $subjects_count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$subjects_total_pages = ceil($total_subjects / $subjects_per_page);

// Get subjects with pagination
$subjects_query = "SELECT s.*, gl.grade_name 
                   FROM subjects s 
                   LEFT JOIN grade_levels gl ON s.grade_level_id = gl.id 
                   ORDER BY gl.grade_order ASC, s.subject_code ASC 
                   LIMIT $subjects_per_page OFFSET $subjects_offset";
$subjects_stmt = $db->prepare($subjects_query);
$subjects_stmt->execute();
$subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all subjects for dropdowns (not paginated)
$all_subjects_query = "SELECT s.*, gl.grade_name 
                       FROM subjects s 
                       LEFT JOIN grade_levels gl ON s.grade_level_id = gl.id 
                       WHERE s.is_active = 1 
                       ORDER BY gl.grade_order ASC, s.subject_code ASC";
$all_subjects_stmt = $db->prepare($all_subjects_query);
$all_subjects_stmt->execute();
$all_subjects = $all_subjects_stmt->fetchAll(PDO::FETCH_ASSOC);

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

// Get total count of curriculum assignments for pagination
$curriculum_count_query = "SELECT COUNT(*) as total FROM curriculum";
$curriculum_count_stmt = $db->prepare($curriculum_count_query);
$curriculum_count_stmt->execute();
$total_curriculum = $curriculum_count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$curriculum_total_pages = ceil($total_curriculum / $curriculum_per_page);

// Get curriculum assignments with details and pagination
$curriculum_query = "SELECT c.*, s.subject_code, s.subject_name, gl.grade_name, sy.year_label, s.is_active as subject_active
                     FROM curriculum c
                     JOIN subjects s ON c.subject_id = s.id
                     JOIN grade_levels gl ON c.grade_level_id = gl.id
                     JOIN school_years sy ON c.school_year_id = sy.id
                     ORDER BY sy.is_active DESC, gl.grade_order ASC, c.order_sequence ASC
                     LIMIT $curriculum_per_page OFFSET $curriculum_offset";
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
        <button class="tab-btn active" onclick="showTab('subjects', event)" style="flex: 1; padding: 1rem; border: none; background: var(--primary-blue); color: white; cursor: pointer; transition: all 0.3s;">
            Manage Subjects
        </button>
        <button class="tab-btn" onclick="showTab('curriculum', event)" style="flex: 1; padding: 1rem; border: none; background: var(--light-gray); color: var(--black); cursor: pointer; transition: all 0.3s;">
            Curriculum Assignments
        </button>
        <button class="tab-btn" onclick="showTab('assign', event)" style="flex: 1; padding: 1rem; border: none; background: var(--light-gray); color: var(--black); cursor: pointer; transition: all 0.3s;">
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
                    <th style="padding: 1rem; text-align: left; color: var(--black); font-weight: 600;">Grade Level</th>
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
                    <td style="padding: 1rem; color: var(--black);">
                        <?php if ($subject['grade_name']): ?>
                            <span style="background: var(--light-blue); color: var(--primary-blue); padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.875rem; font-weight: 600;">
                                <?php echo htmlspecialchars($subject['grade_name']); ?>
                            </span>
                        <?php else: ?>
                            <span style="color: var(--gray); font-style: italic;">Not set</span>
                        <?php endif; ?>
                    </td>
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
    
    <!-- Subjects Pagination -->
    <?php if ($subjects_total_pages > 1): ?>
        <div style="margin-top: 2rem; display: flex; justify-content: center; align-items: center; gap: 0.5rem;">
            <?php if ($subjects_page > 1): ?>
                <a href="?subjects_page=<?= $subjects_page - 1 ?><?= isset($_GET['curriculum_page']) ? '&curriculum_page=' . $_GET['curriculum_page'] : '' ?>#subjects-tab" 
                   style="padding: 0.75rem 1rem; background: var(--primary-blue); color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">
                    ← Previous
                </a>
            <?php endif; ?>
            
            <?php
            $start_page = max(1, $subjects_page - 2);
            $end_page = min($subjects_total_pages, $subjects_page + 2);
            ?>
            
            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?subjects_page=<?= $i ?><?= isset($_GET['curriculum_page']) ? '&curriculum_page=' . $_GET['curriculum_page'] : '' ?>#subjects-tab" 
                   style="padding: 0.75rem 1rem; background: <?= $i == $subjects_page ? 'var(--dark-blue)' : 'var(--light-gray)' ?>; 
                          color: <?= $i == $subjects_page ? 'white' : 'var(--black)' ?>; text-decoration: none; border-radius: 8px; font-weight: 600;">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($subjects_page < $subjects_total_pages): ?>
                <a href="?subjects_page=<?= $subjects_page + 1 ?><?= isset($_GET['curriculum_page']) ? '&curriculum_page=' . $_GET['curriculum_page'] : '' ?>#subjects-tab" 
                   style="padding: 0.75rem 1rem; background: var(--primary-blue); color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">
                    Next →
                </a>
            <?php endif; ?>
        </div>
        
        <div style="text-align: center; margin-top: 1rem; color: var(--gray); font-size: 0.9rem;">
            Showing <?= ($subjects_offset + 1) ?> to <?= min($subjects_offset + $subjects_per_page, $total_subjects) ?> of <?= $total_subjects ?> subjects
        </div>
    <?php endif; ?>
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
    
    <!-- Curriculum Pagination -->
    <?php if ($curriculum_total_pages > 1): ?>
        <div style="margin-top: 2rem; display: flex; justify-content: center; align-items: center; gap: 0.5rem;">
            <?php if ($curriculum_page > 1): ?>
                <a href="?curriculum_page=<?= $curriculum_page - 1 ?><?= isset($_GET['subjects_page']) ? '&subjects_page=' . $_GET['subjects_page'] : '' ?>#curriculum-tab" 
                   style="padding: 0.75rem 1rem; background: var(--primary-blue); color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">
                    ← Previous
                </a>
            <?php endif; ?>
            
            <?php
            $start_page = max(1, $curriculum_page - 2);
            $end_page = min($curriculum_total_pages, $curriculum_page + 2);
            ?>
            
            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?curriculum_page=<?= $i ?><?= isset($_GET['subjects_page']) ? '&subjects_page=' . $_GET['subjects_page'] : '' ?>#curriculum-tab" 
                   style="padding: 0.75rem 1rem; background: <?= $i == $curriculum_page ? 'var(--dark-blue)' : 'var(--light-gray)' ?>; 
                          color: <?= $i == $curriculum_page ? 'white' : 'var(--black)' ?>; text-decoration: none; border-radius: 8px; font-weight: 600;">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($curriculum_page < $curriculum_total_pages): ?>
                <a href="?curriculum_page=<?= $curriculum_page + 1 ?><?= isset($_GET['subjects_page']) ? '&subjects_page=' . $_GET['subjects_page'] : '' ?>#curriculum-tab" 
                   style="padding: 0.75rem 1rem; background: var(--primary-blue); color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">
                    Next →
                </a>
            <?php endif; ?>
        </div>
        
        <div style="text-align: center; margin-top: 1rem; color: var(--gray); font-size: 0.9rem;">
            Showing <?= ($curriculum_offset + 1) ?> to <?= min($curriculum_offset + $curriculum_per_page, $total_curriculum) ?> of <?= $total_curriculum ?> curriculum assignments
        </div>
    <?php endif; ?>
</div>

<!-- Assign Tab -->
<div id="assign-tab" class="tab-content" style="display: none; background: var(--white); border-radius: 15px; padding: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
    <h2 style="margin-bottom: 2rem; color: var(--black);">Assign Subject to Curriculum</h2>
    
    <!-- Tabs for Single or Bulk Assignment -->
    <div style="display: flex; gap: 1rem; margin-bottom: 2rem; border-bottom: 2px solid var(--light-gray);">
        <button onclick="showAssignMode('single')" id="single-assign-btn" class="assign-mode-btn" 
                style="padding: 1rem 2rem; border: none; background: transparent; color: var(--primary-blue); cursor: pointer; font-weight: 600; border-bottom: 3px solid var(--primary-blue);">
            Assign Single Subject
        </button>
        <button onclick="showAssignMode('bulk')" id="bulk-assign-btn" class="assign-mode-btn"
                style="padding: 1rem 2rem; border: none; background: transparent; color: var(--gray); cursor: pointer; font-weight: 600; border-bottom: 3px solid transparent;">
            Assign All Subjects
        </button>
    </div>
    
    <!-- Single Assignment Form -->
    <form method="POST" id="single-assign-form" style="max-width: 600px;">
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
            <select name="grade_level_id" id="assign_grade_level_id" required onchange="filterSubjectsByGrade()" style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem;">
                <option value="">Select Grade Level</option>
                <?php foreach ($grade_levels as $grade): ?>
                <option value="<?php echo $grade['id']; ?>">
                    <?php echo htmlspecialchars($grade['grade_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div style="margin-bottom: 1.5rem;">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--black); font-weight: 600;">Subject:</label>
            <select name="subject_id" id="assign_subject_id" required style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem;">
                <option value="">Select Grade Level First</option>
                <?php foreach ($all_subjects as $subject): ?>
                <option value="<?php echo $subject['id']; ?>" data-grade-level-id="<?php echo $subject['grade_level_id']; ?>" style="display: none;">
                    <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']); ?>
                </option>
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
    
    <!-- Bulk Assignment Form -->
    <form method="POST" id="bulk-assign-form" style="max-width: 600px; display: none;">
        <input type="hidden" name="action" value="assign_all_subjects">
        
        <div style="background: var(--light-blue); padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; border-left: 4px solid var(--primary-blue);">
            <p style="margin: 0; color: var(--black);">
                <strong>Bulk Assignment:</strong> This will assign all subjects matching the selected grade level to the curriculum at once. 
                Subjects are matched by their code prefix (e.g., G4- for Grade 4).
            </p>
        </div>
        
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
            <select name="grade_level_id" id="bulk_grade_level_id" required onchange="previewBulkSubjects()" style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem;">
                <option value="">Select Grade Level</option>
                <?php foreach ($grade_levels as $grade): ?>
                <option value="<?php echo $grade['id']; ?>" data-grade-name="<?php echo htmlspecialchars($grade['grade_name']); ?>">
                    <?php echo htmlspecialchars($grade['grade_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div id="bulk-preview" style="margin-bottom: 1.5rem; display: none;">
            <div style="background: var(--white); border: 2px solid var(--light-gray); border-radius: 8px; padding: 1rem;">
                <h4 style="margin: 0 0 1rem 0; color: var(--black);">Subjects to be assigned:</h4>
                <div id="bulk-preview-list" style="max-height: 200px; overflow-y: auto;"></div>
            </div>
        </div>
        
        <div style="margin-bottom: 1.5rem;">
            <label style="display: flex; align-items: center; color: var(--black); font-weight: 600; cursor: pointer;">
                <input type="checkbox" name="is_required" checked style="margin-right: 0.5rem; transform: scale(1.2);">
                Mark all as Required Subjects
            </label>
        </div>
        
        <button type="submit" id="bulk-submit-btn" disabled style="background: var(--gray); color: white; border: none; padding: 1rem 2rem; border-radius: 8px; cursor: not-allowed; font-weight: 600; font-size: 1rem;">
            Assign All Subjects
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
                <label style="display: block; margin-bottom: 0.5rem; color: var(--black); font-weight: 600;">Grade Level:</label>
                <select name="grade_level_id" required style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem;">
                    <option value="">Select Grade Level</option>
                    <?php foreach ($grade_levels as $grade): ?>
                    <option value="<?php echo $grade['id']; ?>"><?php echo htmlspecialchars($grade['grade_name']); ?></option>
                    <?php endforeach; ?>
                </select>
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
                <label style="display: block; margin-bottom: 0.5rem; color: var(--black); font-weight: 600;">Grade Level:</label>
                <select name="grade_level_id" id="edit_grade_level_id" required style="width: 100%; padding: 0.75rem; border: 2px solid var(--light-gray); border-radius: 8px; font-size: 1rem;">
                    <option value="">Select Grade Level</option>
                    <?php foreach ($grade_levels as $grade): ?>
                    <option value="<?php echo $grade['id']; ?>"><?php echo htmlspecialchars($grade['grade_name']); ?></option>
                    <?php endforeach; ?>
                </select>
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
// Store all subjects data for filtering
const allSubjectsData = <?php echo json_encode($all_subjects); ?>;

function showAssignMode(mode) {
    const singleForm = document.getElementById('single-assign-form');
    const bulkForm = document.getElementById('bulk-assign-form');
    const singleBtn = document.getElementById('single-assign-btn');
    const bulkBtn = document.getElementById('bulk-assign-btn');
    
    if (mode === 'single') {
        singleForm.style.display = 'block';
        bulkForm.style.display = 'none';
        singleBtn.style.color = 'var(--primary-blue)';
        singleBtn.style.borderBottomColor = 'var(--primary-blue)';
        bulkBtn.style.color = 'var(--gray)';
        bulkBtn.style.borderBottomColor = 'transparent';
    } else {
        singleForm.style.display = 'none';
        bulkForm.style.display = 'block';
        bulkBtn.style.color = 'var(--primary-blue)';
        bulkBtn.style.borderBottomColor = 'var(--primary-blue)';
        singleBtn.style.color = 'var(--gray)';
        singleBtn.style.borderBottomColor = 'transparent';
    }
}

function previewBulkSubjects() {
    const gradeSelect = document.getElementById('bulk_grade_level_id');
    const previewDiv = document.getElementById('bulk-preview');
    const previewList = document.getElementById('bulk-preview-list');
    const submitBtn = document.getElementById('bulk-submit-btn');
    
    if (!gradeSelect || !previewDiv || !previewList) return;
    
    const selectedGradeOption = gradeSelect.options[gradeSelect.selectedIndex];
    const selectedGradeName = selectedGradeOption ? selectedGradeOption.getAttribute('data-grade-name') : '';
    
    if (!selectedGradeName) {
        previewDiv.style.display = 'none';
        submitBtn.disabled = true;
        submitBtn.style.background = 'var(--gray)';
        submitBtn.style.cursor = 'not-allowed';
        return;
    }
    
    // Extract grade prefix
    let gradePrefix = '';
    if (selectedGradeName.toLowerCase().includes('nursery')) {
        gradePrefix = 'NURS';
    } else if (selectedGradeName.toLowerCase().includes('kindergarten')) {
        gradePrefix = 'K';
    } else {
        const gradeMatch = selectedGradeName.match(/Grade\s+(\d+)/i);
        if (gradeMatch) {
            gradePrefix = 'G' + gradeMatch[1];
        }
    }
    
    // Filter subjects
    const matchingSubjects = allSubjectsData.filter(subject => 
        subject.subject_code.toUpperCase().startsWith(gradePrefix + '-')
    );
    
    if (matchingSubjects.length === 0) {
        previewList.innerHTML = '<p style="margin: 0; color: var(--warning);">No subjects found for ' + selectedGradeName + '</p>';
        previewDiv.style.display = 'block';
        submitBtn.disabled = true;
        submitBtn.style.background = 'var(--gray)';
        submitBtn.style.cursor = 'not-allowed';
    } else {
        let html = '<ul style="margin: 0; padding-left: 1.5rem; color: var(--black);">';
        matchingSubjects.forEach(subject => {
            html += '<li style="margin-bottom: 0.5rem;"><strong>' + escapeHtml(subject.subject_code) + '</strong> - ' + escapeHtml(subject.subject_name) + '</li>';
        });
        html += '</ul>';
        html += '<p style="margin-top: 1rem; color: var(--success); font-weight: 600;">Total: ' + matchingSubjects.length + ' subject(s)</p>';
        
        previewList.innerHTML = html;
        previewDiv.style.display = 'block';
        submitBtn.disabled = false;
        submitBtn.style.background = 'var(--primary-blue)';
        submitBtn.style.cursor = 'pointer';
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function filterSubjectsByGrade() {
    const gradeSelect = document.getElementById('assign_grade_level_id');
    const subjectSelect = document.getElementById('assign_subject_id');
    
    if (!gradeSelect || !subjectSelect) return;
    
    const selectedGradeId = gradeSelect.value;
    
    // Reset subject select
    subjectSelect.value = '';
    
    if (!selectedGradeId) {
        // No grade selected, hide all subjects
        Array.from(subjectSelect.options).forEach(option => {
            if (option.value) {
                option.style.display = 'none';
            }
        });
        subjectSelect.options[0].textContent = 'Select Grade Level First';
        return;
    }
    
    // Show/hide subjects based on grade_level_id match
    let hasVisibleOptions = false;
    Array.from(subjectSelect.options).forEach(option => {
        if (option.value) {
            const optionGradeLevelId = option.getAttribute('data-grade-level-id');
            
            // Check if subject's grade_level_id matches selected grade
            if (optionGradeLevelId === selectedGradeId) {
                option.style.display = 'block';
                hasVisibleOptions = true;
            } else {
                option.style.display = 'none';
            }
        }
    });
    
    // Update first option based on whether subjects are available
    const firstOption = subjectSelect.options[0];
    if (hasVisibleOptions) {
        firstOption.textContent = 'Select Subject';
    } else {
        const selectedGradeName = gradeSelect.options[gradeSelect.selectedIndex].text;
        firstOption.textContent = 'No subjects available for ' + selectedGradeName;
    }
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
    document.getElementById('edit_grade_level_id').value = subject.grade_level_id || '';
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

// Handle URL anchors for tab switching
document.addEventListener('DOMContentLoaded', function() {
    const hash = window.location.hash;
    if (hash) {
        const tabName = hash.replace('#', '').replace('-tab', '');
        if (['subjects', 'curriculum', 'assign'].includes(tabName)) {
            // Find the corresponding tab button and click it
            const tabButtons = document.querySelectorAll('.tab-btn');
            tabButtons.forEach(btn => {
                let shouldClick = false;
                const btnText = btn.textContent.toLowerCase().trim();
                
                if (tabName === 'subjects' && btnText.includes('manage subjects')) {
                    shouldClick = true;
                } else if (tabName === 'curriculum' && btnText === 'curriculum assignments') {
                    shouldClick = true;
                } else if (tabName === 'assign' && btnText === 'assign to curriculum') {
                    shouldClick = true;
                }
                
                if (shouldClick) {
                    btn.click();
                }
            });
        }
    }
});

// Update showTab function to handle URL updates
function showTab(tabName, event) {
    // Hide all tab content
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
    
    // Update URL hash without page reload
    history.replaceState(null, null, '#' + tabName + '-tab');
}
</script>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
