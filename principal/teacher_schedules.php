<?php
require_once '../includes/auth_check.php';

// Check if user is a principal
if (!checkRole('principal')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';
require_once '../classes/User.php';

$page_title = 'Teacher Schedule Management - GTBA Portal';
$base_url = '../';

// Initialize variables
$schedules = [];
$teachers = [];
$subjects = [];
$sections = [];
$error_message = null;
$success_message = null;
$days_of_week = [
    'Monday' => 'Monday',
    'Tuesday' => 'Tuesday', 
    'Wednesday' => 'Wednesday',
    'Thursday' => 'Thursday',
    'Friday' => 'Friday',
    'Saturday' => 'Saturday'
];

try {
    $database = new Database();
    $db = $database->connect();
    $user = new User($db);
    
    // Get current school year
    $current_year_query = "SELECT * FROM school_years WHERE is_active = 1 LIMIT 1";
    $current_year_stmt = $db->prepare($current_year_query);
    $current_year_stmt->execute();
    $current_year = $current_year_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current_year) {
        throw new Exception("No active school year found. Please set an active school year first.");
    }
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_schedule':
                    $teacher_id = $_POST['teacher_id'];
                    $schedule_type = $_POST['schedule_type']; // 'subject' or 'activity'
                    $subject_id = isset($_POST['subject_id']) && $_POST['subject_id'] !== '' ? $_POST['subject_id'] : null;
                    $activity_name = isset($_POST['activity_name']) ? trim($_POST['activity_name']) : null;
                    $section_id = isset($_POST['section_id']) && $_POST['section_id'] !== '' ? $_POST['section_id'] : null;
                    $day_of_week = $_POST['day_of_week'];
                    $start_time = $_POST['start_time'];
                    $end_time = $_POST['end_time'];
                    $room = trim($_POST['room']);
                    $notes = trim($_POST['notes']);
                    
                    // Validate based on schedule type
                    $is_valid = false;
                    if ($schedule_type === 'subject' && $subject_id) {
                        $is_valid = true;
                        $activity_name = null; // Clear activity name for subject-based schedule
                    } elseif ($schedule_type === 'activity' && $activity_name) {
                        $is_valid = true;
                        $subject_id = null; // Clear subject_id for activity-based schedule
                    }
                    
                    if ($teacher_id && $is_valid && $day_of_week && $start_time && $end_time) {
                        // Check for time conflicts with this teacher
                        $conflict_query = "SELECT COUNT(*) as conflicts FROM class_schedules 
                                          WHERE teacher_id = ? AND day_of_week = ? 
                                          AND is_active = 1
                                          AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?))";
                        $conflict_stmt = $db->prepare($conflict_query);
                        $conflict_stmt->execute([$teacher_id, $day_of_week, $start_time, $start_time, $end_time, $end_time]);
                        $conflicts = $conflict_stmt->fetch(PDO::FETCH_ASSOC)['conflicts'];
                        
                        if ($conflicts > 0) {
                            $error_message = "Time conflict detected! This teacher already has a schedule at this time.";
                        } else {
                            $query = "INSERT INTO class_schedules (teacher_id, subject_id, activity_name, section_id, school_year_id, day_of_week, start_time, end_time, room_id, notes, created_by, created_at) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                            $stmt = $db->prepare($query);
                            if ($stmt->execute([$teacher_id, $subject_id, $activity_name, $section_id, $current_year['id'], $day_of_week, $start_time, $end_time, $room, $notes, $_SESSION['user_id']])) {
                                $user->logAudit($_SESSION['user_id'], 'CREATE', 'class_schedules', null, 
                                    "Created teacher schedule for " . ($schedule_type === 'subject' ? 'subject' : 'activity') . 
                                    " on {$day_of_week} {$start_time}-{$end_time}");
                                $success_message = $schedule_type === 'subject' ? "Subject schedule created successfully!" : "Activity schedule created successfully!";
                            } else {
                                $error_message = "Failed to create schedule.";
                            }
                        }
                    } else {
                        $error_message = "Please fill in all required fields.";
                    }
                    break;
                    
                case 'update_schedule':
                    $schedule_id = $_POST['schedule_id'];
                    $teacher_id = $_POST['teacher_id'];
                    $schedule_type = $_POST['schedule_type'];
                    $subject_id = isset($_POST['subject_id']) && $_POST['subject_id'] !== '' ? $_POST['subject_id'] : null;
                    $activity_name = isset($_POST['activity_name']) ? trim($_POST['activity_name']) : null;
                    $section_id = isset($_POST['section_id']) && $_POST['section_id'] !== '' ? $_POST['section_id'] : null;
                    $day_of_week = $_POST['day_of_week'];
                    $start_time = $_POST['start_time'];
                    $end_time = $_POST['end_time'];
                    $room = trim($_POST['room']);
                    $notes = trim($_POST['notes']);
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    
                    // Validate based on schedule type
                    $is_valid = false;
                    if ($schedule_type === 'subject' && $subject_id) {
                        $is_valid = true;
                        $activity_name = null;
                    } elseif ($schedule_type === 'activity' && $activity_name) {
                        $is_valid = true;
                        $subject_id = null;
                    }
                    
                    if ($schedule_id && $teacher_id && $is_valid && $day_of_week && $start_time && $end_time) {
                        // Check for time conflicts (excluding current schedule)
                        $conflict_query = "SELECT COUNT(*) as conflicts FROM class_schedules 
                                          WHERE teacher_id = ? AND day_of_week = ? 
                                          AND is_active = 1 AND id != ?
                                          AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?))";
                        $conflict_stmt = $db->prepare($conflict_query);
                        $conflict_stmt->execute([$teacher_id, $day_of_week, $schedule_id, $start_time, $start_time, $end_time, $end_time]);
                        $conflicts = $conflict_stmt->fetch(PDO::FETCH_ASSOC)['conflicts'];
                        
                        if ($conflicts > 0) {
                            $error_message = "Time conflict detected! This teacher already has a schedule at this time.";
                        } else {
                            $query = "UPDATE class_schedules SET teacher_id = ?, subject_id = ?, activity_name = ?, section_id = ?, 
                                      day_of_week = ?, start_time = ?, end_time = ?, room_id = ?, notes = ?, is_active = ?, updated_at = NOW() 
                                      WHERE id = ?";
                            $stmt = $db->prepare($query);
                            if ($stmt->execute([$teacher_id, $subject_id, $activity_name, $section_id, $day_of_week, $start_time, $end_time, $room, $notes, $is_active, $schedule_id])) {
                                $user->logAudit($_SESSION['user_id'], 'UPDATE', 'class_schedules', $schedule_id, 
                                    "Updated teacher schedule");
                                $success_message = "Schedule updated successfully!";
                            } else {
                                $error_message = "Failed to update schedule.";
                            }
                        }
                    } else {
                        $error_message = "Please fill in all required fields.";
                    }
                    break;
                    
                case 'delete_schedule':
                    $schedule_id = $_POST['schedule_id'];
                    if ($schedule_id) {
                        $query = "UPDATE class_schedules SET is_active = 0, updated_at = NOW() WHERE id = ?";
                        $stmt = $db->prepare($query);
                        if ($stmt->execute([$schedule_id])) {
                            $user->logAudit($_SESSION['user_id'], 'DELETE', 'class_schedules', $schedule_id, 
                                "Deactivated teacher schedule");
                            $success_message = "Schedule deleted successfully!";
                        } else {
                            $error_message = "Failed to delete schedule.";
                        }
                    }
                    break;
            }
        }
    }
    
    // Get all active teachers
    $teachers_query = "SELECT t.user_id as id, u.username, u.email, t.first_name, t.last_name, t.employee_id,
                              CONCAT(t.first_name, ' ', t.last_name) as full_name
                       FROM teachers t
                       JOIN users u ON t.user_id = u.id
                       WHERE u.is_active = 1 AND t.is_active = 1
                       ORDER BY t.last_name, t.first_name";
    $teachers_stmt = $db->prepare($teachers_query);
    $teachers_stmt->execute();
    $teachers = $teachers_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all active subjects
    $subjects_query = "SELECT * FROM subjects WHERE is_active = 1 ORDER BY subject_name";
    $subjects_stmt = $db->prepare($subjects_query);
    $subjects_stmt->execute();
    $subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all active sections for current school year
    $sections_query = "SELECT s.*, gl.grade_name 
                       FROM sections s 
                       JOIN grade_levels gl ON s.grade_level_id = gl.id 
                       WHERE s.is_active = 1 AND s.school_year_id = ? 
                       ORDER BY gl.grade_order, s.section_name";
    $sections_stmt = $db->prepare($sections_query);
    $sections_stmt->execute([$current_year['id']]);
    $sections = $sections_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all teacher schedules for current school year from class_schedules table
    $schedules_query = "SELECT cs.*, 
                               CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
                               u.username as teacher_username,
                               t.employee_id,
                               s.subject_name,
                               s.subject_code,
                               sec.section_name,
                               gl.grade_name,
                               cs.activity_name
                        FROM class_schedules cs
                        LEFT JOIN teachers t ON cs.teacher_id = t.user_id
                        LEFT JOIN users u ON t.user_id = u.id
                        LEFT JOIN subjects s ON cs.subject_id = s.id
                        LEFT JOIN sections sec ON cs.section_id = sec.id
                        LEFT JOIN grade_levels gl ON sec.grade_level_id = gl.id
                        WHERE cs.school_year_id = ? AND cs.is_active = 1 AND cs.teacher_id IS NOT NULL
                        ORDER BY t.last_name, t.first_name, 
                                 FIELD(cs.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'),
                                 cs.start_time";
    $schedules_stmt = $db->prepare($schedules_query);
    $schedules_stmt->execute([$current_year['id']]);
    $schedules = $schedules_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Handle AJAX requests for getting teacher schedules by teacher_id
    if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_teacher_schedule' && isset($_GET['teacher_id'])) {
        $teacher_id = $_GET['teacher_id'];
        $teacher_schedules_query = "SELECT cs.*, s.subject_name, s.subject_code, cs.activity_name,
                                           sec.section_name, gl.grade_name
                                   FROM class_schedules cs
                                   LEFT JOIN subjects s ON cs.subject_id = s.id
                                   LEFT JOIN sections sec ON cs.section_id = sec.id
                                   LEFT JOIN grade_levels gl ON sec.grade_level_id = gl.id
                                   WHERE cs.teacher_id = ? AND cs.school_year_id = ? AND cs.is_active = 1
                                   ORDER BY FIELD(cs.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'),
                                           cs.start_time";
        $teacher_schedules_stmt = $db->prepare($teacher_schedules_query);
        $teacher_schedules_stmt->execute([$teacher_id, $current_year['id']]);
        $teacher_schedules = $teacher_schedules_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode($teacher_schedules);
        exit();
    }
    
    // Handle AJAX requests for getting specific schedule data for editing
    if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_schedule_data' && isset($_GET['id'])) {
        $schedule_id = $_GET['id'];
        $schedule_query = "SELECT * FROM class_schedules WHERE id = ? AND is_active = 1";
        $schedule_stmt = $db->prepare($schedule_query);
        $schedule_stmt->execute([$schedule_id]);
        $schedule_data = $schedule_stmt->fetch(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        if ($schedule_data) {
            echo json_encode($schedule_data);
        } else {
            echo json_encode(['error' => 'Schedule not found']);
        }
        exit();
    }
    
} catch (Exception $e) {
    $error_message = "An error occurred: " . $e->getMessage();
    error_log("Teacher schedules error: " . $e->getMessage());
}

// Store flash messages in session
if (isset($success_message)) {
    $_SESSION['success'] = $success_message;
}
if (isset($error_message)) {
    $_SESSION['error'] = $error_message;
}

// Redirect to prevent form resubmission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Location: teacher_schedules.php');
    exit();
}

// Get flash messages from session
if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}

ob_start();
?>

<div class="welcome-section">
    <h1 class="welcome-title">Teacher Schedule Management</h1>
    <p class="welcome-subtitle">Manage individual teacher schedules and time allocations</p>
    
    <?php if ($current_year): ?>
        <div class="current-year-info">
            <span class="year-badge">Current School Year: <?php echo htmlspecialchars($current_year['year_label']); ?></span>
        </div>
    <?php endif; ?>
</div>

<?php if ($error_message): ?>
    <div class="message error">
        <i class="fas fa-exclamation-triangle"></i>
        <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<?php if ($success_message): ?>
    <div class="message success">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($success_message); ?>
    </div>
<?php endif; ?>

<!-- Control Panel -->
<div class="control-panel">
    <div class="panel-left">
        <button class="btn btn-primary" onclick="openModal('createScheduleModal')">
            <i class="fas fa-plus"></i> Add Teacher Schedule
        </button>
        <div class="teacher-filter">
            <label for="teacherFilter">Filter by Teacher:</label>
            <select id="teacherFilter" onchange="filterByTeacher()">
                <option value="">All Teachers</option>
                <?php foreach ($teachers as $teacher): ?>
                    <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['full_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="panel-right">
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Search schedules..." onkeyup="searchSchedules()">
            <i class="fas fa-search"></i>
        </div>
    </div>
</div>

<!-- Teacher Schedules Table -->
<div class="table-container">
    <table class="data-table" id="schedulesTable">
        <thead>
            <tr>
                <th>Teacher</th>
                <th>Day</th>
                <th>Time</th>
                <th>Type</th>
                <th>Subject/Activity</th>
                <th>Section</th>
                <th>Room</th>
                <th>Notes</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($schedules)): ?>
                <tr>
                    <td colspan="9" class="no-data">No teacher schedules found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($schedules as $schedule): ?>
                    <tr data-teacher-id="<?php echo $schedule['teacher_id']; ?>">
                        <td>
                            <div class="teacher-info">
                                <strong><?php echo htmlspecialchars($schedule['teacher_name']); ?></strong>
                                <small>@<?php echo htmlspecialchars($schedule['teacher_username']); ?></small>
                            </div>
                        </td>
                        <td><span class="day-badge"><?php echo htmlspecialchars($schedule['day_of_week']); ?></span></td>
                        <td>
                            <div class="time-slot">
                                <?php echo date('g:i A', strtotime($schedule['start_time'])); ?> - 
                                <?php echo date('g:i A', strtotime($schedule['end_time'])); ?>
                            </div>
                        </td>
                        <td>
                            <span class="schedule-type <?php echo $schedule['subject_id'] ? 'subject' : 'activity'; ?>">
                                <?php echo $schedule['subject_id'] ? 'Subject' : 'Activity'; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($schedule['subject_id']): ?>
                                <span class="subject-name"><?php echo htmlspecialchars($schedule['subject_name']); ?></span>
                            <?php else: ?>
                                <span class="activity-name"><?php echo htmlspecialchars($schedule['activity_name']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($schedule['section_id']): ?>
                                <span class="section-info">
                                    <?php echo htmlspecialchars($schedule['grade_name'] . ' - ' . $schedule['section_name']); ?>
                                </span>
                            <?php else: ?>
                                <span class="no-section">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($schedule['room'] ?: 'N/A'); ?></td>
                        <td>
                            <?php if ($schedule['notes']): ?>
                                <span class="notes-preview" title="<?php echo htmlspecialchars($schedule['notes']); ?>">
                                    <?php echo htmlspecialchars(substr($schedule['notes'], 0, 30)) . (strlen($schedule['notes']) > 30 ? '...' : ''); ?>
                                </span>
                            <?php else: ?>
                                <span class="no-notes">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-sm btn-outline" onclick="editSchedule(<?php echo $schedule['id']; ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteSchedule(<?php echo $schedule['id']; ?>)" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Create Schedule Modal -->
<div id="createScheduleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add Teacher Schedule</h3>
            <button class="modal-close" onclick="closeModal('createScheduleModal')">&times;</button>
        </div>
        <form method="POST" class="modal-form">
            <input type="hidden" name="action" value="create_schedule">
            
            <div class="form-grid">
                <div class="form-group">
                    <label for="create_teacher_id">Teacher <span class="required">*</span></label>
                    <select name="teacher_id" id="create_teacher_id" required>
                        <option value="">Select Teacher</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>">
                                <?php echo htmlspecialchars($teacher['full_name'] . ' (@' . $teacher['username'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="create_schedule_type">Schedule Type <span class="required">*</span></label>
                    <select name="schedule_type" id="create_schedule_type" onchange="toggleScheduleFields('create')" required>
                        <option value="">Select Type</option>
                        <option value="subject">Subject Class</option>
                        <option value="activity">Activity/Meeting</option>
                    </select>
                </div>
                
                <div class="form-group" id="create_subject_field" style="display: none;">
                    <label for="create_subject_id">Subject</label>
                    <select name="subject_id" id="create_subject_id">
                        <option value="">Select Subject</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['subject_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" id="create_activity_field" style="display: none;">
                    <label for="create_activity_name">Activity Name</label>
                    <input type="text" name="activity_name" id="create_activity_name" placeholder="e.g., Faculty Meeting, Planning Session">
                </div>
                
                <div class="form-group">
                    <label for="create_section_id">Section (Optional)</label>
                    <select name="section_id" id="create_section_id">
                        <option value="">No specific section</option>
                        <?php foreach ($sections as $section): ?>
                            <option value="<?php echo $section['id']; ?>">
                                <?php echo htmlspecialchars($section['grade_name'] . ' - ' . $section['section_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="create_day_of_week">Day of Week <span class="required">*</span></label>
                    <select name="day_of_week" id="create_day_of_week" required>
                        <option value="">Select Day</option>
                        <?php foreach ($days_of_week as $key => $day): ?>
                            <option value="<?php echo $key; ?>"><?php echo $day; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="create_start_time">Start Time <span class="required">*</span></label>
                    <input type="time" name="start_time" id="create_start_time" required>
                </div>
                
                <div class="form-group">
                    <label for="create_end_time">End Time <span class="required">*</span></label>
                    <input type="time" name="end_time" id="create_end_time" required>
                </div>
                
                <div class="form-group">
                    <label for="create_room">Room/Location</label>
                    <input type="text" name="room" id="create_room" placeholder="e.g., Room 101, Library, Office">
                </div>
                
                <div class="form-group form-group-full">
                    <label for="create_notes">Notes</label>
                    <textarea name="notes" id="create_notes" rows="3" placeholder="Additional notes or instructions..."></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createScheduleModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Schedule</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Schedule Modal -->
<div id="editScheduleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Teacher Schedule</h3>
            <button class="modal-close" onclick="closeModal('editScheduleModal')">&times;</button>
        </div>
        <form method="POST" class="modal-form">
            <input type="hidden" name="action" value="update_schedule">
            <input type="hidden" name="schedule_id" id="edit_schedule_id">
            
            <div class="form-grid">
                <div class="form-group">
                    <label for="edit_teacher_id">Teacher <span class="required">*</span></label>
                    <select name="teacher_id" id="edit_teacher_id" required>
                        <option value="">Select Teacher</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>">
                                <?php echo htmlspecialchars($teacher['full_name'] . ' (@' . $teacher['username'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_schedule_type">Schedule Type <span class="required">*</span></label>
                    <select name="schedule_type" id="edit_schedule_type" onchange="toggleScheduleFields('edit')" required>
                        <option value="">Select Type</option>
                        <option value="subject">Subject Class</option>
                        <option value="activity">Activity/Meeting</option>
                    </select>
                </div>
                
                <div class="form-group" id="edit_subject_field" style="display: none;">
                    <label for="edit_subject_id">Subject</label>
                    <select name="subject_id" id="edit_subject_id">
                        <option value="">Select Subject</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['subject_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" id="edit_activity_field" style="display: none;">
                    <label for="edit_activity_name">Activity Name</label>
                    <input type="text" name="activity_name" id="edit_activity_name" placeholder="e.g., Faculty Meeting, Planning Session">
                </div>
                
                <div class="form-group">
                    <label for="edit_section_id">Section (Optional)</label>
                    <select name="section_id" id="edit_section_id">
                        <option value="">No specific section</option>
                        <?php foreach ($sections as $section): ?>
                            <option value="<?php echo $section['id']; ?>">
                                <?php echo htmlspecialchars($section['grade_name'] . ' - ' . $section['section_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_day_of_week">Day of Week <span class="required">*</span></label>
                    <select name="day_of_week" id="edit_day_of_week" required>
                        <option value="">Select Day</option>
                        <?php foreach ($days_of_week as $key => $day): ?>
                            <option value="<?php echo $key; ?>"><?php echo $day; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_start_time">Start Time <span class="required">*</span></label>
                    <input type="time" name="start_time" id="edit_start_time" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_end_time">End Time <span class="required">*</span></label>
                    <input type="time" name="end_time" id="edit_end_time" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_room">Room/Location</label>
                    <input type="text" name="room" id="edit_room" placeholder="e.g., Room 101, Library, Office">
                </div>
                
                <div class="form-group form-group-full">
                    <label for="edit_notes">Notes</label>
                    <textarea name="notes" id="edit_notes" rows="3" placeholder="Additional notes or instructions..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_active" id="edit_is_active" checked>
                        Schedule is active
                    </label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editScheduleModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Schedule</button>
            </div>
        </form>
    </div>
</div>

<script>
// Global variables to store schedule data
let allSchedules = [];

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Store original table data for filtering
    const table = document.getElementById('schedulesTable');
    const rows = table.querySelectorAll('tbody tr:not(.no-data)');
    allSchedules = Array.from(rows);
});

// Modal functions
function openModal(modalId) {
    document.getElementById(modalId).classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
    document.body.style.overflow = 'auto';
    
    // Reset form if closing create modal
    if (modalId === 'createScheduleModal') {
        document.querySelector('#createScheduleModal form').reset();
        toggleScheduleFields('create');
    }
}

// Toggle subject/activity fields based on schedule type
function toggleScheduleFields(prefix) {
    const scheduleType = document.getElementById(prefix + '_schedule_type').value;
    const subjectField = document.getElementById(prefix + '_subject_field');
    const activityField = document.getElementById(prefix + '_activity_field');
    const subjectSelect = document.getElementById(prefix + '_subject_id');
    const activityInput = document.getElementById(prefix + '_activity_name');
    
    if (scheduleType === 'subject') {
        subjectField.style.display = 'block';
        activityField.style.display = 'none';
        subjectSelect.required = true;
        activityInput.required = false;
        activityInput.value = '';
    } else if (scheduleType === 'activity') {
        subjectField.style.display = 'none';
        activityField.style.display = 'block';
        subjectSelect.required = false;
        activityInput.required = true;
        subjectSelect.value = '';
    } else {
        subjectField.style.display = 'none';
        activityField.style.display = 'none';
        subjectSelect.required = false;
        activityInput.required = false;
        subjectSelect.value = '';
        activityInput.value = '';
    }
}

// Edit schedule function
function editSchedule(scheduleId) {
    // Find the schedule data from the table row
    const row = document.querySelector(`tr:has(button[onclick="editSchedule(${scheduleId})"])`);
    if (!row) return;
    
    // Get schedule data via AJAX
    fetch(`teacher_schedules.php?ajax=get_schedule_data&id=${scheduleId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('Error loading schedule data: ' + data.error);
                return;
            }
            
            // Populate the edit form
            document.getElementById('edit_schedule_id').value = data.id;
            document.getElementById('edit_teacher_id').value = data.teacher_id;
            document.getElementById('edit_schedule_type').value = data.subject_id ? 'subject' : 'activity';
            document.getElementById('edit_subject_id').value = data.subject_id || '';
            document.getElementById('edit_activity_name').value = data.activity_name || '';
            document.getElementById('edit_section_id').value = data.section_id || '';
            document.getElementById('edit_day_of_week').value = data.day_of_week;
            document.getElementById('edit_start_time').value = data.start_time;
            document.getElementById('edit_end_time').value = data.end_time;
            document.getElementById('edit_room').value = data.room || '';
            document.getElementById('edit_notes').value = data.notes || '';
            document.getElementById('edit_is_active').checked = data.is_active == 1;
            
            // Toggle fields based on schedule type
            toggleScheduleFields('edit');
            
            // Open the modal
            openModal('editScheduleModal');
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading schedule data');
        });
}

// Delete schedule function
function deleteSchedule(scheduleId) {
    if (confirm('Are you sure you want to delete this teacher schedule? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_schedule';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'schedule_id';
        idInput.value = scheduleId;
        
        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Filter schedules by teacher
function filterByTeacher() {
    const teacherId = document.getElementById('teacherFilter').value;
    const tbody = document.querySelector('#schedulesTable tbody');
    
    // Clear current table body
    tbody.innerHTML = '';
    
    if (teacherId === '') {
        // Show all schedules
        allSchedules.forEach(row => tbody.appendChild(row.cloneNode(true)));
    } else {
        // Show only schedules for selected teacher
        const filteredSchedules = allSchedules.filter(row => 
            row.dataset.teacherId === teacherId
        );
        
        if (filteredSchedules.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="no-data">No schedules found for selected teacher.</td></tr>';
        } else {
            filteredSchedules.forEach(row => tbody.appendChild(row.cloneNode(true)));
        }
    }
}

// Search schedules
function searchSchedules() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const tbody = document.querySelector('#schedulesTable tbody');
    
    if (searchTerm === '') {
        // If search is empty, show filtered results based on teacher filter
        filterByTeacher();
        return;
    }
    
    // Clear current table body
    tbody.innerHTML = '';
    
    // Get currently visible schedules (based on teacher filter)
    const teacherId = document.getElementById('teacherFilter').value;
    let schedulesToSearch = allSchedules;
    
    if (teacherId !== '') {
        schedulesToSearch = allSchedules.filter(row => 
            row.dataset.teacherId === teacherId
        );
    }
    
    // Filter by search term
    const filteredSchedules = schedulesToSearch.filter(row => {
        const text = row.textContent.toLowerCase();
        return text.includes(searchTerm);
    });
    
    if (filteredSchedules.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="no-data">No schedules found matching your search.</td></tr>';
    } else {
        filteredSchedules.forEach(row => tbody.appendChild(row.cloneNode(true)));
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        closeModal(e.target.id);
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.modal.show');
        modals.forEach(modal => closeModal(modal.id));
    }
});
</script>

<style>
/* Teacher Schedule Management Specific Styles */
.welcome-section {
    background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
    color: white;
    padding: 2rem;
    border-radius: 15px;
    margin-bottom: 2rem;
    text-align: center;
}

.welcome-section .welcome-title {
    color: white;
    margin: 0 0 1rem 0;
}

.welcome-section .welcome-subtitle {
    color: white;
    opacity: 0.9;
}

.current-year-info {
    margin-top: 1rem;
}

.year-badge {
    background: rgba(255, 255, 255, 0.2);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
}

.control-panel {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    gap: 1rem;
}

.panel-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.teacher-filter {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.teacher-filter label {
    font-weight: 600;
    color: var(--dark-blue);
}

.teacher-filter select {
    padding: 0.5rem;
    border: 2px solid var(--border-gray);
    border-radius: 8px;
    min-width: 200px;
}

.panel-right {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.search-box {
    position: relative;
}

.search-box input {
    padding: 0.5rem 2.5rem 0.5rem 1rem;
    border: 2px solid var(--border-gray);
    border-radius: 8px;
    width: 250px;
}

.search-box i {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray);
}

.table-container {
    background: var(--white);
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    overflow: hidden;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    background: var(--light-blue);
    color: var(--dark-blue);
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid var(--border-gray);
}

.data-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border-gray);
    vertical-align: middle;
}

.data-table tbody tr:hover {
    background: var(--light-gray);
}

.teacher-info strong {
    display: block;
    color: var(--dark-blue);
    font-weight: 600;
}

.teacher-info small {
    color: var(--gray);
    font-size: 0.85rem;
}

.day-badge {
    background: var(--primary-blue);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.85rem;
    font-weight: 600;
}

.time-slot {
    font-family: monospace;
    font-weight: 600;
    color: var(--dark-blue);
}

.schedule-type {
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
}

.schedule-type.subject {
    background: #e8f5e8;
    color: #2e7d32;
}

.schedule-type.activity {
    background: #fff3e0;
    color: #ef6c00;
}

.subject-name {
    color: var(--dark-blue);
    font-weight: 600;
}

.activity-name {
    color: #ef6c00;
    font-weight: 600;
}

.section-info {
    background: var(--light-blue);
    padding: 0.25rem 0.5rem;
    border-radius: 8px;
    font-size: 0.85rem;
    color: var(--dark-blue);
}

.no-section, .no-notes {
    color: var(--gray);
    font-style: italic;
}

.notes-preview {
    color: var(--gray);
    font-size: 0.9rem;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.8rem;
}

.no-data {
    text-align: center;
    color: var(--gray);
    font-style: italic;
    padding: 3rem;
}

/* Modal Styles */
.modal {
    display: flex;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    align-items: center;
    justify-content: center;
    visibility: hidden;
    opacity: 0;
    transition: visibility 0s, opacity 0.3s ease;
}

.modal.show {
    visibility: visible;
    opacity: 1;
}

.modal-content {
    background: white;
    margin: 2rem;
    padding: 0;
    border-radius: 15px;
    width: 90%;
    max-width: 800px;
    max-height: 90vh;
    overflow: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideIn 0.3s ease;
    display: flex;
    flex-direction: column;
}

.modal-header {
    background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
    color: white;
    padding: 1.5rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    flex-shrink: 0;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.4rem;
    font-weight: 600;
}

.modal-close {
    background: none;
    border: none;
    color: white;
    font-size: 1.8rem;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background 0.3s ease;
}

.modal-close:hover {
    background: rgba(255, 255, 255, 0.2);
}

.modal-form {
    padding: 2rem;
    flex: 1;
    overflow-y: auto;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group-full {
    grid-column: 1 / -1;
}

/* Subject and Activity fields should span full width when visible */
#create_subject_field,
#create_activity_field,
#edit_subject_field,
#edit_activity_field {
    grid-column: 1 / -1;
}

.form-group label {
    font-weight: 600;
    color: var(--dark-blue);
    margin-bottom: 0.5rem;
}

.required {
    color: var(--danger);
}

.form-group input,
.form-group select,
.form-group textarea {
    padding: 0.75rem;
    border: 2px solid var(--border-gray);
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: var(--primary-blue);
    outline: none;
}

.checkbox-label {
    flex-direction: row !important;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
    width: auto;
}

.modal-footer {
    background: var(--light-gray);
    padding: 1rem 2rem;
    border-top: 1px solid var(--border-gray);
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    flex-shrink: 0;
    margin-top: auto;
}

@keyframes slideIn {
    from { 
        opacity: 0;
        transform: translateY(-50px) scale(0.9);
    }
    to { 
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Responsive Design */
@media (max-width: 1200px) {
    .control-panel {
        flex-direction: column;
        align-items: stretch;
    }
    
    .panel-left,
    .panel-right {
        justify-content: center;
    }
}

@media (max-width: 768px) {
    .control-panel {
        gap: 1rem;
    }
    
    .panel-left {
        flex-direction: column;
        align-items: stretch;
    }
    
    .teacher-filter {
        flex-direction: column;
        align-items: stretch;
    }
    
    .teacher-filter select {
        min-width: auto;
    }
    
    .search-box input {
        width: 100%;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .modal-content {
        margin: 1rem;
        width: calc(100% - 2rem);
        max-height: calc(100vh - 2rem);
    }
    
    .modal-form {
        padding: 1.5rem;
    }
    
    .modal-footer {
        padding: 1rem 1.5rem;
    }
    
    .data-table {
        font-size: 0.9rem;
    }
    
    .data-table th,
    .data-table td {
        padding: 0.75rem 0.5rem;
    }
    
    .action-buttons {
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .data-table th:nth-child(n+6),
    .data-table td:nth-child(n+6) {
        display: none;
    }
    
    .teacher-info small {
        display: none;
    }
}
</style>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>