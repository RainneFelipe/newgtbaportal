<?php
require_once '../includes/auth_check.php';

// Check if user is a principal
if (!checkRole('principal')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$page_title = 'Class Schedules - GTBA Portal';
$base_url = '../';

// Initialize variables to prevent undefined variable errors
$schedules = [];
$sections = [];
$subjects = [];
$teachers = [];
$error_message = null;
$success_message = null;
$days_of_week = [
    'Monday' => 'Monday',
    'Tuesday' => 'Tuesday',
    'Wednesday' => 'Wednesday',
    'Thursday' => 'Thursday',
    'Friday' => 'Friday',
    'Saturday' => 'Saturday',
    'Sunday' => 'Sunday'
];

try {
    $database = new Database();
    $db = $database->connect();
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_schedule':
                    $section_id = $_POST['section_id'];
                    $schedule_type = $_POST['schedule_type']; // 'subject' or 'activity'
                    $subject_id = isset($_POST['subject_id']) ? $_POST['subject_id'] : null;
                    $activity_name = isset($_POST['activity_name']) ? trim($_POST['activity_name']) : null;
                    $teacher_id = isset($_POST['teacher_id']) && $_POST['teacher_id'] !== '' ? $_POST['teacher_id'] : null;
                    $day_of_week = $_POST['day_of_week'];
                    $start_time = $_POST['start_time'];
                    $end_time = $_POST['end_time'];
                    $room = trim($_POST['room']);
                    
                    // Validate based on schedule type
                    $is_valid = false;
                    if ($schedule_type === 'subject' && $subject_id) {
                        $is_valid = true;
                        $activity_name = null; // Clear activity name for subject-based schedule
                    } elseif ($schedule_type === 'activity' && $activity_name) {
                        $is_valid = true;
                        $subject_id = null; // Clear subject_id for activity-based schedule
                    }
                    
                    if ($section_id && $is_valid && $day_of_week && $start_time && $end_time) {
                        // Check for time conflicts
                        $conflict_query = "SELECT COUNT(*) as conflicts FROM class_schedules 
                                          WHERE section_id = ? AND day_of_week = ? 
                                          AND is_active = 1
                                          AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?))";
                        $conflict_stmt = $db->prepare($conflict_query);
                        $conflict_stmt->execute([$section_id, $day_of_week, $start_time, $start_time, $end_time, $end_time]);
                        $conflicts = $conflict_stmt->fetch(PDO::FETCH_ASSOC)['conflicts'];
                        
                        if ($conflicts > 0) {
                            $error_message = "Time conflict detected! Another activity/subject is already scheduled for this section at this time.";
                        } else {
                            $query = "INSERT INTO class_schedules (section_id, subject_id, activity_name, teacher_id, school_year_id, day_of_week, start_time, end_time, room, created_by, created_at) 
                                      VALUES (?, ?, ?, ?, (SELECT school_year_id FROM sections WHERE id = ?), ?, ?, ?, ?, ?, NOW())";
                            $stmt = $db->prepare($query);
                            if ($stmt->execute([$section_id, $subject_id, $activity_name, $teacher_id, $section_id, $day_of_week, $start_time, $end_time, $room, $_SESSION['user_id']])) {
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
                    $section_id = $_POST['section_id'];
                    $schedule_type = $_POST['schedule_type']; // 'subject' or 'activity'
                    $subject_id = isset($_POST['subject_id']) ? $_POST['subject_id'] : null;
                    $activity_name = isset($_POST['activity_name']) ? trim($_POST['activity_name']) : null;
                    $teacher_id = isset($_POST['teacher_id']) && $_POST['teacher_id'] !== '' ? $_POST['teacher_id'] : null;
                    $day_of_week = $_POST['day_of_week'];
                    $start_time = $_POST['start_time'];
                    $end_time = $_POST['end_time'];
                    $room = trim($_POST['room']);
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    
                    // Validate based on schedule type
                    $is_valid = false;
                    if ($schedule_type === 'subject' && $subject_id) {
                        $is_valid = true;
                        $activity_name = null; // Clear activity name for subject-based schedule
                    } elseif ($schedule_type === 'activity' && $activity_name) {
                        $is_valid = true;
                        $subject_id = null; // Clear subject_id for activity-based schedule
                    }
                    
                    if ($schedule_id && $section_id && $is_valid && $day_of_week && $start_time && $end_time) {
                        // Check for time conflicts (excluding current schedule)
                        $conflict_query = "SELECT COUNT(*) as conflicts FROM class_schedules 
                                          WHERE section_id = ? AND day_of_week = ? 
                                          AND is_active = 1 AND id != ?
                                          AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?))";
                        $conflict_stmt = $db->prepare($conflict_query);
                        $conflict_stmt->execute([$section_id, $day_of_week, $schedule_id, $start_time, $start_time, $end_time, $end_time]);
                        $conflicts = $conflict_stmt->fetch(PDO::FETCH_ASSOC)['conflicts'];
                        
                        if ($conflicts > 0) {
                            $error_message = "Time conflict detected! Another activity/subject is already scheduled for this section at this time.";
                        } else {
                            $query = "UPDATE class_schedules SET section_id = ?, subject_id = ?, activity_name = ?, teacher_id = ?, 
                                      school_year_id = (SELECT school_year_id FROM sections WHERE id = ?),
                                      day_of_week = ?, start_time = ?, end_time = ?, room = ?, 
                                      is_active = ?, updated_at = NOW()
                                      WHERE id = ?";
                            $stmt = $db->prepare($query);
                            if ($stmt->execute([$section_id, $subject_id, $activity_name, $teacher_id, $section_id, $day_of_week, $start_time, $end_time, $room, $is_active, $schedule_id])) {
                                $success_message = $schedule_type === 'subject' ? "Subject schedule updated successfully!" : "Activity schedule updated successfully!";
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
                            $success_message = "Class schedule deleted successfully!";
                        } else {
                            $error_message = "Failed to delete class schedule.";
                        }
                    }
                    break;
            }
        }
    }
    
    // Get filter parameters
    $section_filter = $_GET['section_filter'] ?? '';
    $teacher_filter = $_GET['teacher_filter'] ?? '';
    $day_filter = $_GET['day_filter'] ?? '';
    $subject_filter = $_GET['subject_filter'] ?? '';
    
    // Get schedules with related data
    $query = "SELECT cs.*, 
              CONCAT(s.section_name, ' - ', gl.grade_name) as section_info,
              subj.subject_name, subj.subject_code,
              CASE 
                WHEN cs.activity_name IS NOT NULL THEN cs.activity_name
                WHEN cs.subject_id IS NOT NULL THEN CONCAT(IFNULL(subj.subject_code, ''), ' - ', subj.subject_name)
                ELSE 'Unknown Activity'
              END as activity_display,
              CASE 
                WHEN cs.activity_name IS NOT NULL THEN 'activity'
                WHEN cs.subject_id IS NOT NULL THEN 'subject'
                ELSE 'unknown'
              END as schedule_type,
              CASE 
                WHEN cs.teacher_id IS NOT NULL THEN CONCAT(t.first_name, ' ', t.last_name)
                ELSE NULL
              END as teacher_name,
              sy.year_label
              FROM class_schedules cs
              LEFT JOIN sections s ON cs.section_id = s.id
              LEFT JOIN grade_levels gl ON s.grade_level_id = gl.id
              LEFT JOIN subjects subj ON cs.subject_id = subj.id
              LEFT JOIN teachers t ON cs.teacher_id = t.user_id
              LEFT JOIN school_years sy ON s.school_year_id = sy.id
              WHERE cs.is_active = 1";
    
    $params = [];
    
    if ($section_filter) {
        $query .= " AND cs.section_id = ?";
        $params[] = $section_filter;
    }
    
    if ($teacher_filter) {
        $query .= " AND cs.teacher_id = ?";
        $params[] = $teacher_filter;
    }
    
    if ($day_filter) {
        $query .= " AND cs.day_of_week = ?";
        $params[] = $day_filter;
    }
    
    if ($subject_filter) {
        $query .= " AND cs.subject_id = ?";
        $params[] = $subject_filter;
    }
    
    $query .= " ORDER BY cs.day_of_week, cs.start_time, s.section_name";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get sections for filters and forms (active sections only)
    $query = "SELECT s.*, CONCAT(s.section_name, ' - ', gl.grade_name, ' (', sy.year_label, ')') as section_display
              FROM sections s
              LEFT JOIN grade_levels gl ON s.grade_level_id = gl.id
              LEFT JOIN school_years sy ON s.school_year_id = sy.id
              WHERE s.is_active = 1
              ORDER BY gl.grade_order, s.section_name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get subjects for filters and forms
    $query = "SELECT * FROM subjects WHERE is_active = 1 ORDER BY subject_name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get teachers for filters and forms (from teachers table)
    $query = "SELECT t.user_id as id, t.first_name, t.last_name, u.email 
              FROM teachers t
              JOIN users u ON t.user_id = u.id 
              JOIN roles r ON u.role_id = r.id 
              WHERE r.name = 'teacher' AND u.is_active = 1 AND t.is_active = 1
              ORDER BY t.first_name, t.last_name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Days of the week
    $days_of_week = [
        'Monday' => 'Monday',
        'Tuesday' => 'Tuesday',
        'Wednesday' => 'Wednesday',
        'Thursday' => 'Thursday',
        'Friday' => 'Friday',
        'Saturday' => 'Saturday',
        'Sunday' => 'Sunday'
    ];
    
} catch (Exception $e) {
    $error_message = "Unable to load schedules data.";
    error_log("Schedules error: " . $e->getMessage());
    error_log("Schedules error file: " . $e->getFile() . " line: " . $e->getLine());
    error_log("Schedules error trace: " . $e->getTraceAsString());
    
    // Initialize variables with empty arrays to prevent undefined variable errors
    $schedules = [];
    $sections = [];
    $subjects = [];
    $teachers = [];
    $section_filter = '';
    $teacher_filter = '';
    $day_filter = '';
    $subject_filter = '';
    $days_of_week = [
        'Monday' => 'Monday',
        'Tuesday' => 'Tuesday',
        'Wednesday' => 'Wednesday',
        'Thursday' => 'Thursday',
        'Friday' => 'Friday',
        'Saturday' => 'Saturday',
        'Sunday' => 'Sunday'
    ];
}

ob_start();
?>

<div class="page-header">
    <div class="page-header-content">
        <div class="page-title-section">
            <h1 class="page-title">
                <i class="fas fa-calendar-week page-icon"></i>
                Class Schedules
            </h1>
            <p class="page-subtitle">Manage class schedules, assign subjects to sections, and set time slots</p>
        </div>
        <div class="page-header-actions">
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-value"><?php echo is_array($schedules) ? count($schedules) : 0; ?></div>
                    <div class="stat-label">Total Schedules</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo is_array($sections) ? count($sections) : 0; ?></div>
                    <div class="stat-label">Active Sections</div>
                </div>
            </div>
            <button type="button" class="btn btn-primary btn-add" data-modal-target="add-schedule-modal">
                <i class="fas fa-plus"></i>
                <span>Add Schedule</span>
            </button>
        </div>
    </div>
</div>

<?php if (isset($success_message)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <span><?php echo $success_message; ?></span>
    </div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle"></i>
        <span><?php echo $error_message; ?></span>
    </div>
<?php endif; ?>

<!-- Filters Section -->
<div class="filters-card">
    <div class="filters-header">
        <h3><i class="fas fa-filter"></i> Filter Schedules</h3>
        <button type="button" class="btn btn-outline btn-sm" onclick="clearFilters()">
            <i class="fas fa-times"></i> Clear All
        </button>
    </div>
    <form method="GET" class="filters-form">
        <div class="filters-grid">
            <div class="filter-group">
                <label for="section_filter" class="filter-label">
                    <i class="fas fa-layer-group"></i>
                    Section
                </label>
                <select name="section_filter" id="section_filter" class="filter-select">
                    <option value="">All Sections</option>
                    <?php foreach ($sections as $section): ?>
                        <option value="<?php echo $section['id']; ?>" <?php echo $section_filter == $section['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($section['section_display']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="subject_filter" class="filter-label">
                    <i class="fas fa-book"></i>
                    Subject
                </label>
                <select name="subject_filter" id="subject_filter" class="filter-select">
                    <option value="">All Subjects</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?php echo $subject['id']; ?>" <?php echo $subject_filter == $subject['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($subject['subject_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="teacher_filter" class="filter-label">
                    <i class="fas fa-chalkboard-teacher"></i>
                    Teacher
                </label>
                <select name="teacher_filter" id="teacher_filter" class="filter-select">
                    <option value="">All Teachers</option>
                    <?php foreach ($teachers as $teacher): ?>
                        <option value="<?php echo $teacher['id']; ?>" <?php echo $teacher_filter == $teacher['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="day_filter" class="filter-label">
                    <i class="fas fa-calendar-day"></i>
                    Day
                </label>
                <select name="day_filter" id="day_filter" class="filter-select">
                    <option value="">All Days</option>
                    <?php foreach ($days_of_week as $day): ?>
                        <option value="<?php echo $day; ?>" <?php echo $day_filter === $day ? 'selected' : ''; ?>>
                            <?php echo $day; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="filters-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Apply Filters
            </button>
            <a href="schedules.php" class="btn btn-outline">
                <i class="fas fa-refresh"></i> Reset
            </a>
        </div>
    </form>
</div>

<!-- Schedules Table -->
<div class="table-card">
    <div class="table-header">
        <h3><i class="fas fa-table"></i> Schedule List</h3>
        <div class="table-info">
            Showing <?php echo is_array($schedules) ? count($schedules) : 0; ?> schedule<?php echo (is_array($schedules) ? count($schedules) : 0) !== 1 ? 's' : ''; ?>
        </div>
    </div>
    
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Section</th>
                    <th>Activity/Subject</th>
                    <th>Teacher</th>
                    <th>Day</th>
                    <th>Time</th>
                    <th>Room</th>
                    <th>Duration</th>
                    <th class="actions-column">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($schedules)): ?>
                    <tr>
                        <td colspan="8" class="no-data">
                            <div class="no-data-content">
                                <i class="fas fa-calendar-times"></i>
                                <h4>No schedules found</h4>
                                <p>No class schedules match your current filters.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($schedules as $schedule): ?>
                        <tr class="table-row">
                            <td>
                                <div class="section-cell">
                                    <div class="section-name"><?php echo htmlspecialchars($schedule['section_info']); ?></div>
                                    <div class="section-year"><?php echo htmlspecialchars($schedule['year_label']); ?></div>
                                </div>
                            </td>
                            <td>
                                <div class="activity-cell">
                                    <div class="activity-name"><?php echo htmlspecialchars($schedule['activity_display']); ?></div>
                                    <div class="activity-type">
                                        <span class="type-badge type-<?php echo $schedule['schedule_type']; ?>">
                                            <?php if ($schedule['schedule_type'] === 'activity'): ?>
                                                <i class="fas fa-play-circle"></i> Activity
                                            <?php elseif ($schedule['schedule_type'] === 'subject'): ?>
                                                <i class="fas fa-book"></i> Subject
                                            <?php else: ?>
                                                <i class="fas fa-question-circle"></i> Unknown
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="teacher-cell">
                                    <?php if ($schedule['teacher_name']): ?>
                                        <i class="fas fa-user-tie"></i>
                                        <span><?php echo htmlspecialchars($schedule['teacher_name']); ?></span>
                                    <?php else: ?>
                                        <span class="no-teacher">
                                            <i class="fas fa-user-slash"></i>
                                            No teacher assigned
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="day-badge day-<?php echo strtolower($schedule['day_of_week']); ?>">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?php echo htmlspecialchars($schedule['day_of_week']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="time-cell">
                                    <div class="time-range">
                                        <span class="time-start"><?php echo date('g:i A', strtotime($schedule['start_time'])); ?></span>
                                        <span class="time-separator">—</span>
                                        <span class="time-end"><?php echo date('g:i A', strtotime($schedule['end_time'])); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="room-cell">
                                    <?php if ($schedule['room']): ?>
                                        <span class="room-number">
                                            <i class="fas fa-door-open"></i>
                                            <?php echo htmlspecialchars($schedule['room']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="room-empty">
                                            <i class="fas fa-minus-circle"></i>
                                            Not Set
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="duration-cell">
                                    <i class="fas fa-clock"></i>
                                    <?php 
                                    $start = new DateTime($schedule['start_time']);
                                    $end = new DateTime($schedule['end_time']);
                                    $duration = $start->diff($end);
                                    echo $duration->format('%h:%I');
                                    ?>
                                </div>
                            </td>
                            <td class="actions-cell">
                                <div class="action-buttons">
                                    <button type="button" class="btn btn-sm btn-outline" 
                                            onclick="editSchedule(<?php echo htmlspecialchars(json_encode($schedule)); ?>)"
                                            title="Edit Schedule">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" 
                                            onclick="confirmDelete(<?php echo $schedule['id']; ?>, '<?php echo htmlspecialchars($schedule['section_info'] . ' - ' . $schedule['subject_name']); ?>')"
                                            title="Delete Schedule">
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
</div>

<!-- Add Schedule Modal -->
<div id="add-schedule-modal" class="schedule-modal-overlay">
    <div class="schedule-modal-container">
        <div class="schedule-modal-content">
            <div class="schedule-modal-header">
                <div class="schedule-modal-title-section">
                    <h3 class="schedule-modal-title">
                        <svg class="schedule-modal-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                            <line x1="12" y1="14" x2="12" y2="18"/>
                            <line x1="10" y1="16" x2="14" y2="16"/>
                        </svg>
                        Add Class Schedule
                    </h3>
                    <p class="schedule-modal-subtitle">Create a new class schedule for subjects or activities</p>
                </div>
                <button type="button" class="schedule-modal-close" data-modal-close>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            
            <div class="schedule-modal-body">
                <form method="POST" id="add-schedule-form" class="schedule-modal-form">
                    <input type="hidden" name="action" value="create_schedule">
                    
                    <!-- Basic Information -->
                    <div class="schedule-form-section">
                        <div class="schedule-form-section-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                                <path d="M2 17l10 5 10-5"/>
                                <path d="M2 12l10 5 10-5"/>
                            </svg>
                            Basic Information
                        </div>
                        <div class="schedule-form-grid">
                            <div class="schedule-form-group">
                                <label for="section_id" class="schedule-form-label required">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                        <circle cx="9" cy="7" r="4"/>
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                    </svg>
                                    Section
                                </label>
                                <select id="section_id" name="section_id" class="schedule-form-select" required>
                                    <option value="">Select Section</option>
                                    <?php foreach ($sections as $section): ?>
                                        <option value="<?php echo $section['id']; ?>">
                                            <?php echo htmlspecialchars($section['section_display']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="schedule-form-group">
                                <label for="schedule_type" class="schedule-form-label required">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M9 11H5a2 2 0 0 0-2 2v3c0 1.1.9 2 2 2h4m6-6h4a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2h-4m-6-6V9a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m-6 0h6"/>
                                    </svg>
                                    Schedule Type
                                </label>
                                <select id="schedule_type" name="schedule_type" class="schedule-form-select" required onchange="toggleScheduleType()">
                                    <option value="">Select Type</option>
                                    <option value="subject">Subject-based</option>
                                    <option value="activity">Activity-based</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Subject/Activity Selection -->
                    <div class="schedule-form-section" id="subject_group" style="display: none;">
                        <div class="schedule-form-section-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                                <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                            </svg>
                            Subject Details
                        </div>
                        <div class="schedule-form-grid">
                            <div class="schedule-form-group">
                                <label for="subject_id" class="schedule-form-label">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                                        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                                    </svg>
                                    Subject
                                </label>
                                <select id="subject_id" name="subject_id" class="schedule-form-select">
                                    <option value="">Select Subject</option>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?php echo $subject['id']; ?>">
                                            <?php echo htmlspecialchars($subject['subject_name']); ?>
                                            <?php if ($subject['subject_code']): ?>
                                                (<?php echo htmlspecialchars($subject['subject_code']); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="schedule-form-group">
                                <label for="teacher_id" class="schedule-form-label">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                        <circle cx="12" cy="7" r="4"/>
                                    </svg>
                                    Teacher
                                </label>
                                <select id="teacher_id" name="teacher_id" class="schedule-form-select">
                                    <option value="">Select Teacher (Optional)</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <option value="<?php echo $teacher['id']; ?>">
                                            <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="schedule-form-help">Teacher assignment is optional</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="schedule-form-section" id="activity_group" style="display: none;">
                        <div class="schedule-form-section-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="3"/>
                                <path d="M12 1v6m0 6v6m11-7h-6m-6 0H1"/>
                            </svg>
                            Activity Details
                        </div>
                        <div class="schedule-form-grid">
                            <div class="schedule-form-group">
                                <label for="activity_name" class="schedule-form-label">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="3"/>
                                        <path d="M12 1v6m0 6v6m11-7h-6m-6 0H1"/>
                                    </svg>
                                    Activity Name
                                </label>
                                <input type="text" id="activity_name" name="activity_name" class="schedule-form-input" placeholder="e.g. Recess, Lunch Break, Assembly">
                                <small class="schedule-form-help">Enter any activity name (will not be saved as a subject)</small>
                            </div>
                            
                            <div class="schedule-form-group">
                                <label for="teacher_id_activity" class="schedule-form-label">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                        <circle cx="12" cy="7" r="4"/>
                                    </svg>
                                    Teacher
                                </label>
                                <select id="teacher_id_activity" name="teacher_id" class="schedule-form-select">
                                    <option value="">Select Teacher (Optional)</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <option value="<?php echo $teacher['id']; ?>">
                                            <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="schedule-form-help">Teacher assignment is optional for activities</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Schedule Details -->
                    <div class="schedule-form-section">
                        <div class="schedule-form-section-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12,6 12,12 16,14"/>
                            </svg>
                            Time & Location
                        </div>
                        <div class="schedule-form-grid schedule-form-grid-4">
                            <div class="schedule-form-group">
                                <label for="day_of_week" class="schedule-form-label required">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                        <line x1="16" y1="2" x2="16" y2="6"/>
                                        <line x1="8" y1="2" x2="8" y2="6"/>
                                        <line x1="3" y1="10" x2="21" y2="10"/>
                                    </svg>
                                    Day of Week
                                </label>
                                <select id="day_of_week" name="day_of_week" class="schedule-form-select" required>
                                    <option value="">Select Day</option>
                                    <?php foreach ($days_of_week as $day): ?>
                                        <option value="<?php echo $day; ?>"><?php echo $day; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="schedule-form-group">
                                <label for="start_time" class="schedule-form-label required">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/>
                                        <polyline points="12,6 12,12 16,14"/>
                                    </svg>
                                    Start Time
                                </label>
                                <input type="time" id="start_time" name="start_time" class="schedule-form-input" required>
                            </div>
                            
                            <div class="schedule-form-group">
                                <label for="end_time" class="schedule-form-label required">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/>
                                        <polyline points="12,6 12,12 8,10"/>
                                    </svg>
                                    End Time
                                </label>
                                <input type="time" id="end_time" name="end_time" class="schedule-form-input" required>
                            </div>
                            
                            <div class="schedule-form-group">
                                <label for="room" class="schedule-form-label">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                        <polyline points="9,22 9,12 15,12 15,22"/>
                                    </svg>
                                    Room
                                </label>
                                <input type="text" id="room" name="room" class="schedule-form-input" placeholder="e.g. 101, A-205, Cafeteria">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="schedule-form-actions">
                        <button type="button" class="schedule-btn schedule-btn-secondary" data-modal-close>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"/>
                                <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                            Cancel
                        </button>
                        <button type="submit" class="schedule-btn schedule-btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                                <polyline points="17,21 17,13 7,13 7,21"/>
                                <polyline points="7,3 7,8 15,8"/>
                            </svg>
                            Create Schedule
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Schedule Modal -->
<div id="edit-schedule-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Class Schedule</h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="edit-schedule-form">
                <input type="hidden" name="action" value="update_schedule">
                <input type="hidden" name="schedule_id" id="edit_schedule_id">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_section_id" class="required">Section</label>
                        <select id="edit_section_id" name="section_id" required>
                            <option value="">Select Section</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?php echo $section['id']; ?>">
                                    <?php echo htmlspecialchars($section['section_display']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_schedule_type" class="required">Schedule Type</label>
                        <select id="edit_schedule_type" name="schedule_type" required onchange="toggleEditScheduleType()">
                            <option value="">Select Type</option>
                            <option value="subject">Subject-based</option>
                            <option value="activity">Activity-based</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="edit_subject_group" style="display: none;">
                        <label for="edit_subject_id">Subject</label>
                        <select id="edit_subject_id" name="subject_id">
                            <option value="">Select Subject</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo $subject['id']; ?>">
                                    <?php echo htmlspecialchars($subject['subject_name']); ?>
                                    <?php if ($subject['subject_code']): ?>
                                        (<?php echo htmlspecialchars($subject['subject_code']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group" id="edit_activity_group" style="display: none;">
                        <label for="edit_activity_name">Activity Name</label>
                        <input type="text" id="edit_activity_name" name="activity_name" placeholder="e.g. Recess, Lunch Break, Assembly">
                        <small class="form-help">Enter any activity name (will not be saved as a subject)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_teacher_id">Teacher</label>
                        <select id="edit_teacher_id" name="teacher_id">
                            <option value="">Select Teacher (Optional)</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>">
                                    <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-help">Teacher is optional for activities like recess</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_day_of_week" class="required">Day of Week</label>
                        <select id="edit_day_of_week" name="day_of_week" required>
                            <option value="">Select Day</option>
                            <?php foreach ($days_of_week as $day): ?>
                                <option value="<?php echo $day; ?>"><?php echo $day; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_start_time" class="required">Start Time</label>
                        <input type="time" id="edit_start_time" name="start_time" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_end_time" class="required">End Time</label>
                        <input type="time" id="edit_end_time" name="end_time" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_room">Room</label>
                        <input type="text" id="edit_room" name="room" placeholder="e.g. 101, A-205, Cafeteria">
                    </div>
                </div>
                
                <div class="form-group checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="edit_is_active" name="is_active" checked>
                        <span class="checkmark"></span>
                        Active Schedule
                    </label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Schedule
                    </button>
                    <button type="button" class="btn btn-secondary" data-modal-close>
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-schedule-modal" class="modal">
    <div class="modal-content modal-sm">
        <div class="modal-header">
            <h3><i class="fas fa-exclamation-triangle text-danger"></i> Confirm Deletion</h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="delete-confirmation">
                <p>Are you sure you want to delete the schedule for:</p>
                <div class="delete-item">
                    <strong id="delete-schedule-name"></strong>
                </div>
                <div class="warning-text">
                    <i class="fas fa-info-circle"></i>
                    This action cannot be undone.
                </div>
            </div>
            
            <form method="POST" id="delete-schedule-form">
                <input type="hidden" name="action" value="delete_schedule">
                <input type="hidden" name="schedule_id" id="delete_schedule_id">
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete Schedule
                    </button>
                    <button type="button" class="btn btn-secondary" data-modal-close>
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Enhanced Modal functionality for Schedule Modal
document.addEventListener('DOMContentLoaded', function() {
    // Initialize modal functionality
    initializeScheduleModal();
    
    // Initialize form validation
    initializeFormValidation();
});

function initializeScheduleModal() {
    // Modal triggers (buttons that open modals)
    const modalTriggers = document.querySelectorAll('[data-modal-target]');
    const scheduleModalCloses = document.querySelectorAll('.schedule-modal-close, [data-modal-close]');
    const scheduleModals = document.querySelectorAll('.schedule-modal-overlay');
    const regularModals = document.querySelectorAll('.modal:not(.schedule-modal-overlay)');
    
    // Handle modal triggers
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            const targetModalId = this.dataset.modalTarget;
            const targetModal = document.getElementById(targetModalId);
            
            if (targetModal) {
                if (targetModal.classList.contains('schedule-modal-overlay')) {
                    openScheduleModal(targetModal);
                } else {
                    openRegularModal(targetModal);
                }
            }
        });
    });
    
    // Handle close buttons for schedule modals
    scheduleModalCloses.forEach(closeBtn => {
        closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const modal = this.closest('.schedule-modal-overlay');
            if (modal) {
                closeScheduleModal(modal);
            }
        });
    });
    
    // Handle overlay clicks for schedule modals
    scheduleModals.forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeScheduleModal(this);
            }
        });
    });
    
    // Handle regular modal functionality (for edit/delete modals)
    const regularModalCloses = document.querySelectorAll('.modal-close');
    
    regularModalCloses.forEach(close => {
        close.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal && !modal.classList.contains('schedule-modal-overlay')) {
                closeRegularModal(modal);
            }
        });
    });
    
    regularModals.forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeRegularModal(this);
            }
        });
    });
    
    // Escape key handling
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            // Close schedule modals first
            const openScheduleModal = document.querySelector('.schedule-modal-overlay.show');
            if (openScheduleModal) {
                closeScheduleModal(openScheduleModal);
                return;
            }
            
            // Then close regular modals
            const openRegularModal = document.querySelector('.modal.show');
            if (openRegularModal) {
                closeRegularModal(openRegularModal);
            }
        }
    });
}

function openScheduleModal(modal) {
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    // Focus first input after animation
    setTimeout(() => {
        const firstInput = modal.querySelector('select, input');
        if (firstInput) {
            firstInput.focus();
        }
    }, 300);
}

function closeScheduleModal(modal) {
    modal.classList.remove('show');
    document.body.style.overflow = '';
    
    // Reset form if it exists
    const form = modal.querySelector('form');
    if (form) {
        form.reset();
        
        // Hide conditional sections
        const subjectGroup = document.getElementById('subject_group');
        const activityGroup = document.getElementById('activity_group');
        if (subjectGroup) subjectGroup.style.display = 'none';
        if (activityGroup) activityGroup.style.display = 'none';
    }
}

function openRegularModal(modal) {
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeRegularModal(modal) {
    modal.classList.remove('show');
    document.body.style.overflow = '';
}

function initializeFormValidation() {
    const form = document.getElementById('add-schedule-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!validateScheduleForm(this)) {
                e.preventDefault();
            }
        });
    }
}

function validateScheduleForm(form) {
    const scheduleType = form.querySelector('#schedule_type').value;
    const subjectId = form.querySelector('#subject_id').value;
    const activityName = form.querySelector('#activity_name').value;
    const startTime = form.querySelector('#start_time').value;
    const endTime = form.querySelector('#end_time').value;
    
    // Clear previous errors
    clearFormErrors(form);
    
    let isValid = true;
    
    // Validate schedule type specific fields
    if (scheduleType === 'subject' && !subjectId) {
        showFieldError(form.querySelector('#subject_id'), 'Please select a subject');
        isValid = false;
    } else if (scheduleType === 'activity' && !activityName.trim()) {
        showFieldError(form.querySelector('#activity_name'), 'Please enter an activity name');
        isValid = false;
    }
    
    // Validate time
    if (startTime && endTime && startTime >= endTime) {
        showFieldError(form.querySelector('#end_time'), 'End time must be after start time');
        isValid = false;
    }
    
    return isValid;
}

function showFieldError(field, message) {
    field.style.borderColor = '#e53e3e';
    
    // Remove existing error message
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
    
    // Add new error message
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.style.color = '#e53e3e';
    errorDiv.style.fontSize = '12px';
    errorDiv.style.marginTop = '4px';
    errorDiv.textContent = message;
    field.parentNode.appendChild(errorDiv);
}

function clearFormErrors(form) {
    // Reset border colors
    const fields = form.querySelectorAll('input, select');
    fields.forEach(field => {
        field.style.borderColor = '#e2e8f0';
    });
    
    // Remove error messages
    const errors = form.querySelectorAll('.field-error');
    errors.forEach(error => error.remove());
}

function editSchedule(schedule) {
    document.getElementById('edit_schedule_id').value = schedule.id;
    document.getElementById('edit_section_id').value = schedule.section_id;
    
    // Set schedule type based on whether it has subject_id or activity_name
    if (schedule.activity_name && !schedule.subject_id) {
        document.getElementById('edit_schedule_type').value = 'activity';
        document.getElementById('edit_activity_name').value = schedule.activity_name;
        document.getElementById('edit_subject_id').value = '';
        toggleEditScheduleType();
    } else if (schedule.subject_id) {
        document.getElementById('edit_schedule_type').value = 'subject';
        document.getElementById('edit_subject_id').value = schedule.subject_id;
        document.getElementById('edit_activity_name').value = '';
        toggleEditScheduleType();
    }
    
    document.getElementById('edit_teacher_id').value = schedule.teacher_id || '';
    document.getElementById('edit_day_of_week').value = schedule.day_of_week;
    document.getElementById('edit_start_time').value = schedule.start_time;
    document.getElementById('edit_end_time').value = schedule.end_time;
    document.getElementById('edit_room').value = schedule.room || '';
    document.getElementById('edit_is_active').checked = schedule.is_active == 1;
    
    document.getElementById('edit-schedule-modal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function toggleScheduleType() {
    const scheduleType = document.getElementById('schedule_type').value;
    const subjectGroup = document.getElementById('subject_group');
    const activityGroup = document.getElementById('activity_group');
    const subjectSelect = document.getElementById('subject_id');
    const activityInput = document.getElementById('activity_name');
    
    if (scheduleType === 'subject') {
        subjectGroup.style.display = 'block';
        activityGroup.style.display = 'none';
        subjectSelect.required = true;
        activityInput.required = false;
        activityInput.value = '';
        
        // Add animation class
        subjectGroup.classList.add('schedule-section-show');
    } else if (scheduleType === 'activity') {
        subjectGroup.style.display = 'none';
        activityGroup.style.display = 'block';
        subjectSelect.required = false;
        activityInput.required = true;
        subjectSelect.value = '';
        
        // Add animation class
        activityGroup.classList.add('schedule-section-show');
    } else {
        subjectGroup.style.display = 'none';
        activityGroup.style.display = 'none';
        subjectSelect.required = false;
        activityInput.required = false;
        
        // Remove animation classes
        subjectGroup.classList.remove('schedule-section-show');
        activityGroup.classList.remove('schedule-section-show');
    }
}

function toggleEditScheduleType() {
    const scheduleType = document.getElementById('edit_schedule_type').value;
    const subjectGroup = document.getElementById('edit_subject_group');
    const activityGroup = document.getElementById('edit_activity_group');
    const subjectSelect = document.getElementById('edit_subject_id');
    const activityInput = document.getElementById('edit_activity_name');
    
    if (scheduleType === 'subject') {
        subjectGroup.style.display = 'block';
        activityGroup.style.display = 'none';
        subjectSelect.required = true;
        activityInput.required = false;
        activityInput.value = '';
    } else if (scheduleType === 'activity') {
        subjectGroup.style.display = 'none';
        activityGroup.style.display = 'block';
        subjectSelect.required = false;
        activityInput.required = true;
        subjectSelect.value = '';
    } else {
        subjectGroup.style.display = 'none';
        activityGroup.style.display = 'none';
        subjectSelect.required = false;
        activityInput.required = false;
    }
}

function confirmDelete(scheduleId, scheduleName) {
    document.getElementById('delete_schedule_id').value = scheduleId;
    document.getElementById('delete-schedule-name').textContent = scheduleName;
    document.getElementById('delete-schedule-modal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function clearFilters() {
    const filterSelects = document.querySelectorAll('.filter-select');
    filterSelects.forEach(select => {
        select.value = '';
    });
    
    // Submit the form to clear filters
    document.querySelector('.filters-form').submit();
}
</script>

<style>
/* Professional Schedule Management Styles */

/* Page Header */
.page-header {
    background: linear-gradient(135deg, var(--white) 0%, #f8fafb 100%);
    border-radius: 20px;
    padding: 0;
    margin-bottom: 2rem;
    box-shadow: 0 8px 32px rgba(46, 134, 171, 0.12);
    overflow: hidden;
    border: 1px solid rgba(46, 134, 171, 0.08);
}

.page-header-content {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 2.5rem 3rem;
    background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
    color: var(--white);
    position: relative;
}

.page-header-content::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
    opacity: 0.3;
}

.page-title-section {
    position: relative;
    z-index: 1;
}

.page-title {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    color: var(--white);
}

.page-icon {
    font-size: 2rem;
    opacity: 0.9;
}

.page-subtitle {
    font-size: 1.2rem;
    opacity: 0.9;
    font-weight: 400;
}

.page-header-actions {
    display: flex;
    align-items: center;
    gap: 2rem;
    position: relative;
    z-index: 1;
}

.stats-cards {
    display: flex;
    gap: 1.5rem;
}

.stat-card {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    padding: 1.25rem;
    text-align: center;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.stat-value {
    font-size: 1.75rem;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.8rem;
    opacity: 0.8;
}

.btn-add {
    background: var(--white);
    color: var(--primary-blue);
    border: 2px solid rgba(255, 255, 255, 0.3);
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn-add:hover {
    background: rgba(255, 255, 255, 0.95);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
}

/* Filters Card */
.filters-card {
    background: var(--white);
    border-radius: 16px;
    box-shadow: 0 4px 16px rgba(46, 134, 171, 0.08);
    margin-bottom: 2rem;
    overflow: hidden;
    border: 1px solid rgba(46, 134, 171, 0.08);
}

.filters-header {
    background: linear-gradient(135deg, var(--light-blue) 0%, rgba(232, 244, 248, 0.7) 100%);
    padding: 1.5rem 2rem;
    border-bottom: 1px solid var(--border-gray);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.filters-header h3 {
    color: var(--dark-blue);
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.filters-form {
    padding: 2rem;
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-label {
    font-weight: 600;
    color: var(--black);
    margin-bottom: 0.75rem;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-label i {
    color: var(--primary-blue);
    width: 16px;
}

.filter-select {
    padding: 0.875rem 1rem;
    border: 2px solid var(--border-gray);
    border-radius: 10px;
    font-size: 0.95rem;
    background: var(--white);
    color: var(--black);
    transition: all 0.3s ease;
    font-family: inherit;
}

.filter-select:focus {
    outline: none;
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 4px rgba(46, 134, 171, 0.1);
}

.filters-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

/* Table Card */
.table-card {
    background: var(--white);
    border-radius: 16px;
    box-shadow: 0 4px 16px rgba(46, 134, 171, 0.08);
    overflow: hidden;
    border: 1px solid rgba(46, 134, 171, 0.08);
}

.table-header {
    background: linear-gradient(135deg, var(--light-blue) 0%, rgba(232, 244, 248, 0.7) 100%);
    padding: 1.5rem 2rem;
    border-bottom: 1px solid var(--border-gray);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.table-header h3 {
    color: var(--dark-blue);
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.table-info {
    color: var(--gray);
    font-size: 0.9rem;
    font-weight: 500;
}

.table-container {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.data-table thead th {
    background: var(--light-gray);
    color: var(--black);
    font-weight: 600;
    padding: 1.25rem 1rem;
    text-align: left;
    border-bottom: 2px solid var(--border-gray);
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.data-table tbody td {
    padding: 1.25rem 1rem;
    border-bottom: 1px solid var(--border-gray);
    vertical-align: middle;
}

.table-row:hover {
    background: rgba(46, 134, 171, 0.02);
}

.actions-column {
    width: 120px;
    text-align: center;
}

/* Table Cell Styles */
.section-cell, .subject-cell, .activity-cell {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.section-name, .subject-name, .activity-name {
    font-weight: 600;
    color: var(--black);
}

.section-year, .subject-code, .activity-type {
    font-size: 0.8rem;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.type-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
}

.type-badge.type-subject {
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    color: #1976d2;
    border: 1px solid #90caf9;
}

.type-badge.type-activity {
    background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);
    color: #7b1fa2;
    border: 1px solid #ce93d8;
}

.type-badge.type-unknown {
    background: linear-gradient(135deg, #fafafa 0%, #eeeeee 100%);
    color: #616161;
    border: 1px solid #bdbdbd;
}

.teacher-cell {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--black);
    font-weight: 500;
}

.teacher-cell i {
    color: var(--primary-blue);
    font-size: 0.9rem;
}

.no-teacher {
    color: var(--gray);
    font-style: italic;
    font-size: 0.9rem;
}

.no-teacher i {
    color: var(--gray) !important;
}

.day-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.day-monday { background: #e3f2fd; color: #1565c0; }
.day-tuesday { background: #e8f5e8; color: #2e7d32; }
.day-wednesday { background: #fff3e0; color: #ef6c00; }
.day-thursday { background: #fce4ec; color: #c2185b; }
.day-friday { background: #f3e5f5; color: #7b1fa2; }
.day-saturday { background: #ffebee; color: #c62828; }
.day-sunday { background: #e0f2f1; color: #00695c; }

.time-cell {
    font-family: 'Courier New', monospace;
}

.time-range {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: var(--black);
}

.time-separator {
    color: var(--gray);
}

.room-cell {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.room-number {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--black);
    font-weight: 500;
}

.room-number i {
    color: var(--primary-blue);
}

.room-empty {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--gray);
    font-style: italic;
}

.room-empty i {
    color: var(--gray);
}

.duration-cell {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: var(--black);
    font-family: 'Courier New', monospace;
}

.duration-cell i {
    color: var(--primary-blue);
}

.actions-cell {
    text-align: center;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
}

/* No Data State */
.no-data {
    text-align: center;
    padding: 3rem;
}

.no-data-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    color: var(--gray);
}

.no-data-content i {
    font-size: 3rem;
    opacity: 0.5;
}

.no-data-content h4 {
    margin: 0;
    color: var(--black);
}

.no-data-content p {
    margin: 0;
    font-style: italic;
}

/* Modal Enhancements */
.modal {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    padding: 1rem;
    box-sizing: border-box;
    align-items: center;
    justify-content: center;
}

.modal.show {
    display: flex;
}

.modal-content {
    background: var(--white);
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    width: 100%;
    max-width: 800px;
    max-height: calc(100vh - 2rem);
    overflow-y: auto;
    position: relative;
    margin: 0;
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(-20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.modal-sm {
    max-width: 500px;
}

.modal-header {
    background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
    color: var(--white);
    padding: 2rem 2.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
}

.modal-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
    opacity: 0.3;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    position: relative;
    z-index: 1;
}

.modal-close {
    background: none;
    border: none;
    color: var(--white);
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 50%;
    transition: all 0.2s ease;
    position: relative;
    z-index: 1;
}

.modal-close:hover {
    background: rgba(255, 255, 255, 0.1);
    transform: scale(1.1);
}

.modal-body {
    padding: 2.5rem;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    position: relative;
}

.form-group label {
    font-weight: 600;
    color: var(--black);
    margin-bottom: 0.75rem;
    font-size: 0.95rem;
}

.form-group label.required::after {
    content: " *";
    color: var(--danger);
    font-weight: bold;
}

.form-group input,
.form-group select {
    padding: 1rem;
    border: 2px solid var(--border-gray);
    border-radius: 10px;
    font-size: 0.95rem;
    background: var(--white);
    color: var(--black);
    transition: all 0.3s ease;
    font-family: inherit;
    position: relative;
    z-index: 1;
}

.form-group select {
    z-index: 10;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 4px rgba(46, 134, 171, 0.1);
    z-index: 20;
}

.form-help {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.8rem;
    color: var(--gray);
    font-style: italic;
}

/* Prevent dropdown overlaps */
.form-grid#subject_group,
.form-grid#activity_group {
    margin-top: 1rem;
    margin-bottom: 1rem;
    border-top: 1px solid var(--border-gray);
    padding-top: 1rem;
}

.form-grid#subject_group[style*="grid"],
.form-grid#activity_group[style*="grid"] {
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.checkbox-group {
    margin: 1.5rem 0;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    font-weight: 500;
    color: var(--black);
}

.checkbox-label input[type="checkbox"] {
    position: relative;
    width: 20px;
    height: 20px;
    margin: 0;
}

.checkmark {
    position: relative;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border-gray);
}

/* Delete Modal */
.delete-confirmation {
    text-align: center;
    margin-bottom: 2rem;
}

.delete-confirmation p {
    color: var(--gray);
    margin-bottom: 1rem;
}

.delete-item {
    background: var(--light-gray);
    padding: 1rem;
    border-radius: 8px;
    margin: 1rem 0;
    font-size: 1.1rem;
}

.warning-text {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    color: var(--warning);
    font-weight: 500;
    margin-top: 1rem;
    font-size: 0.9rem;
}

.text-danger {
    color: var(--danger);
}

/* Button Enhancements */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 10px;
    font-size: 0.9rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
    white-space: nowrap;
    position: relative;
    overflow: hidden;
}

.btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.btn:hover::before {
    left: 100%;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
    color: var(--white);
}

.btn-secondary {
    background: var(--gray);
    color: var(--white);
}

.btn-outline {
    background: var(--white);
    color: var(--primary-blue);
    border: 2px solid var(--primary-blue);
}

.btn-outline:hover {
    background: var(--primary-blue);
    color: var(--white);
}

.btn-danger {
    background: linear-gradient(135deg, var(--danger), #c82333);
    color: var(--white);
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.8rem;
}

/* Alert Enhancements */
.alert {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    font-weight: 500;
    border-left: 4px solid;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.alert-success {
    background: linear-gradient(135deg, #d5f4e6, #f0f9f6);
    color: #0f5132;
    border-left-color: var(--success);
}

.alert-danger {
    background: linear-gradient(135deg, #f8d7da, #fdf2f4);
    color: #842029;
    border-left-color: var(--danger);
}

.alert i {
    font-size: 1.25rem;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .page-header-content {
        flex-direction: column;
        gap: 2rem;
        text-align: center;
    }
    
    .filters-grid {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    }
    
    .form-grid {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    }
}

@media (max-width: 768px) {
    .page-header-content {
        padding: 2rem;
    }
    
    .page-title {
        font-size: 2rem;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .stats-cards {
        flex-direction: column;
        gap: 1rem;
        width: 100%;
    }
    
    .filters-form {
        padding: 1.5rem;
    }
    
    .filters-grid {
        grid-template-columns: 1fr;
    }
    
    .filters-actions {
        flex-direction: column;
    }
    
    .table-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .modal-content {
        margin: 1rem;
        width: calc(100% - 2rem);
        max-height: calc(100vh - 2rem);
    }
    
    .modal-body {
        padding: 1.5rem;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .action-buttons {
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .data-table {
        font-size: 0.8rem;
    }
    
    .data-table thead th,
    .data-table tbody td {
        padding: 0.75rem 0.5rem;
    }
}

@media (max-width: 480px) {
    .page-header-content {
        padding: 1.5rem;
    }
    
    .page-title {
        font-size: 1.75rem;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
    
    .data-table thead th,
    .data-table tbody td {
        padding: 0.5rem 0.25rem;
        font-size: 0.75rem;
    }
}

/* ====================================
   SCHEDULE MODAL STYLES (INDEPENDENT)
   ==================================== */

/* Modal Overlay */
.schedule-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.65);
    backdrop-filter: blur(8px);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    box-sizing: border-box;
    opacity: 0;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.schedule-modal-overlay.show {
    display: flex;
    opacity: 1;
}

/* Modal Container */
.schedule-modal-container {
    position: relative;
    width: 100%;
    max-width: 900px;
    max-height: 95vh;
    display: flex;
    align-items: center;
    justify-content: center;
    transform: scale(0.9) translateY(20px);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.schedule-modal-overlay.show .schedule-modal-container {
    transform: scale(1) translateY(0);
}

/* Modal Content */
.schedule-modal-content {
    background: #ffffff;
    border-radius: 24px;
    box-shadow: 
        0 25px 80px rgba(0, 0, 0, 0.25),
        0 0 0 1px rgba(255, 255, 255, 0.2);
    overflow: hidden;
    width: 100%;
    max-height: 95vh;
    display: flex;
    flex-direction: column;
    position: relative;
}

/* Modal Header */
.schedule-modal-header {
    background: linear-gradient(135deg, #2E86AB 0%, #1F4E79 100%);
    color: white;
    padding: 32px 40px;
    position: relative;
    overflow: hidden;
}

.schedule-modal-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: 
        radial-gradient(circle at 20% 30%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(255, 255, 255, 0.05) 0%, transparent 50%);
    pointer-events: none;
}

.schedule-modal-title-section {
    position: relative;
    z-index: 2;
    flex: 1;
}

.schedule-modal-title {
    font-size: 28px;
    font-weight: 700;
    margin: 0 0 8px 0;
    display: flex;
    align-items: center;
    gap: 12px;
    line-height: 1.2;
}

.schedule-modal-icon {
    flex-shrink: 0;
}

.schedule-modal-subtitle {
    font-size: 16px;
    margin: 0;
    opacity: 0.9;
    font-weight: 400;
    line-height: 1.4;
}

.schedule-modal-close {
    position: absolute;
    top: 32px;
    right: 40px;
    background: rgba(255, 255, 255, 0.1);
    border: 2px solid rgba(255, 255, 255, 0.2);
    color: white;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    z-index: 3;
}

.schedule-modal-close:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.4);
    transform: scale(1.05);
}

/* Modal Body */
.schedule-modal-body {
    padding: 40px;
    overflow-y: auto;
    flex: 1;
    background: #fafbfc;
}

/* Form Styles */
.schedule-modal-form {
    display: flex;
    flex-direction: column;
    gap: 32px;
}

.schedule-form-section {
    background: white;
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
    border: 1px solid #e8eaed;
    transition: all 0.3s ease;
}

.schedule-form-section:hover {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    border-color: #2E86AB;
}

.schedule-form-section-title {
    font-size: 18px;
    font-weight: 600;
    color: #1a202c;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 12px;
    border-bottom: 2px solid #f1f3f4;
}

.schedule-form-section-title svg {
    color: #2E86AB;
    flex-shrink: 0;
}

/* Form Grid */
.schedule-form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 24px;
}

.schedule-form-grid-4 {
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
}

/* Form Groups */
.schedule-form-group {
    display: flex;
    flex-direction: column;
}

.schedule-form-label {
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 8px;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    line-height: 1.4;
}

.schedule-form-label.required::after {
    content: "*";
    color: #e53e3e;
    font-weight: 700;
    margin-left: 4px;
}

.schedule-form-label svg {
    color: #718096;
    flex-shrink: 0;
}

/* Form Controls */
.schedule-form-input,
.schedule-form-select {
    padding: 16px 18px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 15px;
    font-family: inherit;
    background: white;
    color: #2d3748;
    transition: all 0.3s ease;
    width: 100%;
    box-sizing: border-box;
}

.schedule-form-input:focus,
.schedule-form-select:focus {
    outline: none;
    border-color: #2E86AB;
    box-shadow: 
        0 0 0 4px rgba(46, 134, 171, 0.1),
        0 4px 12px rgba(46, 134, 171, 0.15);
    background: #ffffff;
}

.schedule-form-input::placeholder {
    color: #a0aec0;
    font-style: italic;
}

.schedule-form-help {
    margin-top: 6px;
    font-size: 12px;
    color: #718096;
    font-style: italic;
    line-height: 1.4;
}

/* Form Actions */
.schedule-form-actions {
    display: flex;
    gap: 16px;
    justify-content: flex-end;
    padding-top: 24px;
    border-top: 1px solid #e2e8f0;
    margin-top: 8px;
}

/* Schedule Buttons */
.schedule-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 16px 28px;
    border: none;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    position: relative;
    overflow: hidden;
    min-width: 140px;
    justify-content: center;
}

.schedule-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    transition: left 0.6s ease;
}

.schedule-btn:hover::before {
    left: 100%;
}

.schedule-btn-primary {
    background: linear-gradient(135deg, #2E86AB 0%, #1F4E79 100%);
    color: white;
    box-shadow: 0 4px 16px rgba(46, 134, 171, 0.3);
}

.schedule-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(46, 134, 171, 0.4);
}

.schedule-btn-secondary {
    background: #f7fafc;
    color: #4a5568;
    border: 2px solid #e2e8f0;
}

.schedule-btn-secondary:hover {
    background: #edf2f7;
    border-color: #cbd5e0;
    transform: translateY(-1px);
}

/* Section Animations */
.schedule-form-section[style*="none"] {
    display: none !important;
}

.schedule-form-section:not([style*="none"]) {
    animation: scheduleSlideIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.schedule-section-show {
    animation: scheduleSlideIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes scheduleSlideIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .schedule-modal-overlay {
        padding: 16px;
    }
    
    .schedule-modal-header {
        padding: 24px 28px;
    }
    
    .schedule-modal-title {
        font-size: 24px;
    }
    
    .schedule-modal-subtitle {
        font-size: 14px;
    }
    
    .schedule-modal-close {
        top: 24px;
        right: 28px;
        width: 40px;
        height: 40px;
    }
    
    .schedule-modal-body {
        padding: 28px 24px;
    }
    
    .schedule-form-section {
        padding: 20px;
    }
    
    .schedule-form-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .schedule-form-grid-4 {
        grid-template-columns: 1fr;
    }
    
    .schedule-form-actions {
        flex-direction: column-reverse;
    }
    
    .schedule-btn {
        width: 100%;
    }
}

@media (max-width: 480px) {
    .schedule-modal-header {
        padding: 20px;
    }
    
    .schedule-modal-title {
        font-size: 20px;
        flex-direction: column;
        gap: 8px;
        text-align: center;
    }
    
    .schedule-modal-close {
        top: 20px;
        right: 20px;
        width: 36px;
        height: 36px;
    }
    
    .schedule-modal-body {
        padding: 20px 16px;
    }
    
    .schedule-form-section {
        padding: 16px;
    }
    
    .schedule-form-section-title {
        font-size: 16px;
        flex-direction: column;
        gap: 6px;
        text-align: center;
    }
}
</style>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
