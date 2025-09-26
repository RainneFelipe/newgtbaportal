<?php
require_once '../includes/auth_check.php';

// Check if user is a principal
if (!checkRole('principal')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$page_title = 'Schedule Archive - GTBA Portal';
$base_url = '../';

// Initialize variables
$archived_schedules = [];
$school_years = [];
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
    
    // Get filter parameters
    $school_year_filter = $_GET['school_year_filter'] ?? '';
    $section_filter = $_GET['section_filter'] ?? '';
    $teacher_filter = $_GET['teacher_filter'] ?? '';
    $day_filter = $_GET['day_filter'] ?? '';
    $subject_filter = $_GET['subject_filter'] ?? '';
    
    // Get archived schedules (from non-active school years)
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
              sy.year_label, sy.id as school_year_id
              FROM class_schedules cs
              LEFT JOIN sections s ON cs.section_id = s.id
              LEFT JOIN grade_levels gl ON s.grade_level_id = gl.id
              LEFT JOIN subjects subj ON cs.subject_id = subj.id
              LEFT JOIN teachers t ON cs.teacher_id = t.user_id
              LEFT JOIN school_years sy ON cs.school_year_id = sy.id
              WHERE sy.is_active = 0 AND sy.is_current = 0";
    
    $params = [];
    
    if ($school_year_filter) {
        $query .= " AND cs.school_year_id = ?";
        $params[] = $school_year_filter;
    }
    
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
    
    $query .= " ORDER BY sy.year_label DESC, cs.day_of_week, cs.start_time, s.section_name";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $archived_schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get archived school years for filter dropdown
    $query = "SELECT DISTINCT sy.id, sy.year_label 
              FROM school_years sy 
              WHERE sy.is_active = 0 AND sy.is_current = 0
              AND EXISTS (SELECT 1 FROM class_schedules cs WHERE cs.school_year_id = sy.id)
              ORDER BY sy.year_label DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $school_years = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get sections from archived school years for filters
    $query = "SELECT s.id, CONCAT(s.section_name, ' - ', gl.grade_name, ' (', sy.year_label, ')') as section_display,
              sy.year_label, gl.grade_order, s.section_name
              FROM sections s
              LEFT JOIN grade_levels gl ON s.grade_level_id = gl.id
              LEFT JOIN school_years sy ON s.school_year_id = sy.id
              WHERE sy.is_active = 0 AND sy.is_current = 0
              AND EXISTS (SELECT 1 FROM class_schedules cs WHERE cs.section_id = s.id)
              GROUP BY s.id, sy.year_label, gl.grade_order, s.section_name
              ORDER BY sy.year_label DESC, gl.grade_order, s.section_name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get subjects for filters (active subjects only)
    $query = "SELECT * FROM subjects WHERE is_active = 1 ORDER BY subject_name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get teachers for filters (active teachers only)
    $query = "SELECT t.user_id as id, t.first_name, t.last_name, u.email 
              FROM teachers t
              JOIN users u ON t.user_id = u.id 
              JOIN roles r ON u.role_id = r.id 
              WHERE r.name = 'teacher' AND u.is_active = 1 AND t.is_active = 1
              ORDER BY t.first_name, t.last_name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Unable to load archived schedules data.";
    error_log("Schedule Archive error: " . $e->getMessage());
    error_log("Schedule Archive error file: " . $e->getFile() . " line: " . $e->getLine());
    error_log("Schedule Archive error trace: " . $e->getTraceAsString());
    
    // Initialize variables with empty arrays to prevent undefined variable errors
    $archived_schedules = [];
    $school_years = [];
    $sections = [];
    $subjects = [];
    $teachers = [];
    $school_year_filter = '';
    $section_filter = '';
    $teacher_filter = '';
    $day_filter = '';
    $subject_filter = '';
}

// Group schedules by school year
$schedules_by_year = [];
foreach ($archived_schedules as $schedule) {
    $year_label = $schedule['year_label'];
    if (!isset($schedules_by_year[$year_label])) {
        $schedules_by_year[$year_label] = [];
    }
    $schedules_by_year[$year_label][] = $schedule;
}

ob_start();
?>

<div class="page-header">
    <div class="page-header-content">
        <div class="page-title-section">
            <h1 class="page-title">
                <i class="fas fa-archive page-icon"></i>
                Schedule Archive
            </h1>
            <p class="page-subtitle">View class schedules from previous school years</p>
        </div>
        <div class="page-header-actions">
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($archived_schedules); ?></div>
                    <div class="stat-label">Archived Schedules</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($school_years); ?></div>
                    <div class="stat-label">Previous Years</div>
                </div>
            </div>
            <a href="schedules.php" class="btn btn-outline">
                <i class="fas fa-calendar-week"></i>
                <span>Current Schedules</span>
            </a>
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
        <h3><i class="fas fa-filter"></i> Filter Archived Schedules</h3>
        <button type="button" class="btn btn-outline btn-sm" onclick="clearFilters()">
            <i class="fas fa-times"></i> Clear All
        </button>
    </div>
    <form method="GET" class="filters-form">
        <div class="filters-grid">
            <div class="filter-group">
                <label for="school_year_filter" class="filter-label">
                    <i class="fas fa-calendar-alt"></i>
                    School Year
                </label>
                <select name="school_year_filter" id="school_year_filter" class="filter-select">
                    <option value="">All School Years</option>
                    <?php foreach ($school_years as $year): ?>
                        <option value="<?php echo $year['id']; ?>" <?php echo $school_year_filter == $year['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($year['year_label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
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
            <a href="schedule_archive.php" class="btn btn-outline">
                <i class="fas fa-refresh"></i> Reset
            </a>
        </div>
    </form>
</div>

<?php if (empty($archived_schedules)): ?>
    <!-- No Archived Schedules State -->
    <div class="empty-state-card">
        <div class="empty-state-content">
            <div class="empty-state-icon">
                <i class="fas fa-archive"></i>
            </div>
            <h3 class="empty-state-title">No Archived Schedules Found</h3>
            <p class="empty-state-text">
                <?php if (empty($school_years)): ?>
                    There are no schedules from previous school years in the system yet.
                <?php else: ?>
                    No archived schedules match your current filter criteria. Try adjusting your filters or clearing them to see all archived schedules.
                <?php endif; ?>
            </p>
            <?php if (!empty($school_years)): ?>
                <div class="empty-state-actions">
                    <button type="button" class="btn btn-outline" onclick="clearFilters()">
                        <i class="fas fa-filter"></i> Clear Filters
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <!-- Archived Schedules Display -->
    <?php foreach ($schedules_by_year as $year_label => $year_schedules): ?>
        <div class="year-archive-card">
            <div class="year-archive-header">
                <h3>
                    <i class="fas fa-calendar-alt"></i>
                    School Year: <?php echo htmlspecialchars($year_label); ?>
                </h3>
                <div class="year-stats">
                    <span class="schedule-count"><?php echo count($year_schedules); ?> schedule<?php echo count($year_schedules) !== 1 ? 's' : ''; ?></span>
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($year_schedules as $schedule): ?>
                            <tr class="table-row">
                                <td>
                                    <div class="section-cell">
                                        <div class="section-name"><?php echo htmlspecialchars($schedule['section_info']); ?></div>
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
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
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
/* Archive-specific styles */

/* Empty State */
.empty-state-card {
    background: var(--white);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(46, 134, 171, 0.12);
    padding: 4rem 2rem;
    text-align: center;
    border: 1px solid rgba(46, 134, 171, 0.08);
}

.empty-state-content {
    max-width: 500px;
    margin: 0 auto;
}

.empty-state-icon {
    width: 120px;
    height: 120px;
    margin: 0 auto 2rem;
    background: linear-gradient(135deg, var(--light-blue) 0%, rgba(232, 244, 248, 0.3) 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 3px solid rgba(46, 134, 171, 0.1);
}

.empty-state-icon i {
    font-size: 3rem;
    color: var(--primary-blue);
    opacity: 0.7;
}

.empty-state-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--black);
    margin-bottom: 1rem;
}

.empty-state-text {
    font-size: 1.1rem;
    color: var(--gray);
    line-height: 1.6;
    margin-bottom: 2rem;
}

.empty-state-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

/* Year Archive Cards */
.year-archive-card {
    background: var(--white);
    border-radius: 16px;
    box-shadow: 0 4px 16px rgba(46, 134, 171, 0.08);
    margin-bottom: 2rem;
    overflow: hidden;
    border: 1px solid rgba(46, 134, 171, 0.08);
}

.year-archive-header {
    background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
    color: var(--white);
    padding: 1.5rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
}

.year-archive-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
    opacity: 0.3;
}

.year-archive-header h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    position: relative;
    z-index: 1;
}

.year-stats {
    display: flex;
    align-items: center;
    gap: 1rem;
    position: relative;
    z-index: 1;
}

.schedule-count {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
    font-weight: 600;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

/* Inherit other styles from schedules.php */
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

/* Table styles (inherit from schedules.php) */
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

/* Cell styles */
.section-cell, .activity-cell {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.section-name, .activity-name {
    font-weight: 600;
    color: var(--black);
}

.activity-type {
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

/* Button styles */
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
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
    color: var(--white);
    border: 2px solid transparent;
    box-shadow: 0 4px 12px rgba(46, 134, 171, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, var(--dark-blue) 0%, var(--primary-blue) 100%);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(46, 134, 171, 0.4);
}

.btn-outline {
    background: var(--white);
    color: var(--primary-blue);
    border: 2px solid var(--primary-blue);
}

.btn-outline:hover {
    background: var(--primary-blue);
    color: var(--white);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(46, 134, 171, 0.3);
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.8rem;
}

/* Alert styles */
.alert {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    font-weight: 500;
}

.alert-success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    color: #155724;
    border-left: 4px solid #28a745;
}

.alert-danger {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    color: #721c24;
    border-left: 4px solid #dc3545;
}

.alert i {
    font-size: 1.2rem;
}
</style>

<?php
$content = ob_get_clean();
require_once '../includes/header.php';
echo $content;
require_once '../includes/footer.php';
?>