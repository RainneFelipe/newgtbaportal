<?php
require_once '../includes/auth_check.php';

// Check if user is a teacher
if (!checkRole('teacher')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$page_title = 'My Schedule - GTBA Portal';
$base_url = '../';

try {
    $database = new Database();
    $db = $database->connect();
    
    // Get teacher information
    $query = "SELECT t.*, u.username
              FROM teachers t
              LEFT JOIN users u ON t.user_id = u.id
              WHERE t.user_id = :user_id AND t.is_active = 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    
    $teacher_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$teacher_info) {
        throw new Exception("Teacher information not found.");
    }
    
    // Get current school year
    $query = "SELECT * FROM school_years WHERE is_active = 1 LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $current_year = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get complete schedule for the teacher
    $query = "SELECT ts.*, s.subject_name, s.subject_code, sec.section_name, 
                     gl.grade_name, ts.activity_name
              FROM teacher_schedules ts
              LEFT JOIN subjects s ON ts.subject_id = s.id
              LEFT JOIN sections sec ON ts.section_id = sec.id
              LEFT JOIN grade_levels gl ON sec.grade_level_id = gl.id
              WHERE ts.teacher_id = :teacher_id AND ts.is_active = 1
              ORDER BY 
                CASE ts.day_of_week 
                    WHEN 'Monday' THEN 1
                    WHEN 'Tuesday' THEN 2
                    WHEN 'Wednesday' THEN 3
                    WHEN 'Thursday' THEN 4
                    WHEN 'Friday' THEN 5
                    WHEN 'Saturday' THEN 6
                END,
                ts.start_time";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':teacher_id', $_SESSION['user_id']);
    $stmt->execute();
    
    $all_schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group schedule by day
    $schedule_by_day = [];
    foreach ($all_schedule as $schedule) {
        $schedule_by_day[$schedule['day_of_week']][] = $schedule;
    }
    
    // Get today's classes
    $today = date('l'); // Get current day name (Monday, Tuesday, etc.)
    $today_classes = $schedule_by_day[$today] ?? [];
    
    // Get upcoming class (next class today)
    $current_time = date('H:i:s');
    $next_class = null;
    foreach ($today_classes as $class) {
        if ($class['start_time'] > $current_time) {
            $next_class = $class;
            break;
        }
    }
    
    // Get weekly statistics
    $total_hours = 0;
    $subjects_count = [];
    $sections_count = [];
    
    foreach ($all_schedule as $class) {
        $start = new DateTime($class['start_time']);
        $end = new DateTime($class['end_time']);
        $duration = $end->diff($start);
        $total_hours += $duration->h + ($duration->i / 60);
        
        if ($class['subject_id']) {
            $subjects_count[$class['subject_id']] = true;
        }
        $sections_count[$class['section_id']] = true;
    }
    
    $unique_subjects = count($subjects_count);
    $unique_sections = count($sections_count);
    
} catch (Exception $e) {
    $error_message = "Unable to load schedule data: " . $e->getMessage();
    error_log("Teacher schedule error: " . $e->getMessage());
}

$days_order = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$current_day = date('l');

ob_start();
?>

<div class="page-header">
    <h1 class="page-title">My Teaching Schedule</h1>
    <p class="page-subtitle">Your complete weekly class schedule</p>
    
    <?php if (isset($teacher_info)): ?>
        <div class="teacher-badge">
            <?php echo htmlspecialchars($teacher_info['first_name'] . ' ' . $teacher_info['last_name']); ?> - 
            <?php echo htmlspecialchars($teacher_info['specialization'] ?? 'Teacher'); ?>
        </div>
    <?php endif; ?>
</div>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <?php echo $error_message; ?>
    </div>
<?php else: ?>

<!-- Schedule Overview Cards -->
<div class="overview-cards">
    <div class="overview-card">
        <div class="card-icon">⏰</div>
        <div class="card-content">
            <h3><?php echo number_format($total_hours, 1); ?> hrs</h3>
            <p>Weekly Teaching Hours</p>
        </div>
    </div>
    
    <div class="overview-card">
        <div class="card-icon">📚</div>
        <div class="card-content">
            <h3><?php echo $unique_subjects; ?></h3>
            <p>Subjects Taught</p>
        </div>
    </div>
    
    <div class="overview-card">
        <div class="card-icon">🏫</div>
        <div class="card-content">
            <h3><?php echo $unique_sections; ?></h3>
            <p>Sections Handled</p>
        </div>
    </div>
    
    <div class="overview-card">
        <div class="card-icon">📅</div>
        <div class="card-content">
            <h3><?php echo count($all_schedule); ?></h3>
            <p>Total Classes</p>
        </div>
    </div>
</div>

<!-- Today's Highlight -->
<?php if (!empty($today_classes)): ?>
<div class="today-highlight">
    <h2 class="section-title">Today's Classes (<?php echo $today; ?>)</h2>
    
    <?php if ($next_class): ?>
        <div class="next-class-alert">
            <div class="alert-icon">🔔</div>
            <div class="alert-content">
                <h4>Next Class:</h4>
                <p>
                    <strong><?php echo $next_class['activity_name'] ? htmlspecialchars($next_class['activity_name']) : htmlspecialchars($next_class['subject_name']); ?></strong>
                    with <?php echo htmlspecialchars($next_class['grade_name'] . ' - ' . $next_class['section_name']); ?>
                    at <?php echo date('g:i A', strtotime($next_class['start_time'])); ?>
                    <?php if ($next_class['room']): ?>
                        in Room <?php echo htmlspecialchars($next_class['room']); ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="today-schedule">
        <?php foreach ($today_classes as $class): ?>
            <?php 
            $is_current = false;
            $is_past = false;
            if ($current_time >= $class['start_time'] && $current_time <= $class['end_time']) {
                $is_current = true;
            } elseif ($current_time > $class['end_time']) {
                $is_past = true;
            }
            ?>
            <div class="class-card <?php echo $is_current ? 'current' : ($is_past ? 'past' : 'upcoming'); ?>">
                <div class="class-time">
                    <span class="start-time"><?php echo date('g:i A', strtotime($class['start_time'])); ?></span>
                    <span class="end-time"><?php echo date('g:i A', strtotime($class['end_time'])); ?></span>
                </div>
                
                <div class="class-details">
                    <h4 class="class-title">
                        <?php echo $class['activity_name'] ? htmlspecialchars($class['activity_name']) : htmlspecialchars($class['subject_name']); ?>
                    </h4>
                    <p class="class-section"><?php echo htmlspecialchars($class['grade_name'] . ' - ' . $class['section_name']); ?></p>
                    <?php if ($class['room']): ?>
                        <p class="class-room">Room: <?php echo htmlspecialchars($class['room']); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="class-status">
                    <?php if ($is_current): ?>
                        <span class="status-badge current">Currently Teaching</span>
                    <?php elseif ($is_past): ?>
                        <span class="status-badge past">Completed</span>
                    <?php else: ?>
                        <span class="status-badge upcoming">Upcoming</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php else: ?>
<div class="today-highlight">
    <h2 class="section-title">Today's Classes (<?php echo $today; ?>)</h2>
    <div class="empty-state">
        <div class="empty-icon">🎉</div>
        <h3>No classes today!</h3>
        <p>Enjoy your day off or use this time for preparation and planning.</p>
    </div>
</div>
<?php endif; ?>

<!-- Weekly Schedule -->
<div class="weekly-schedule">
    <h2 class="section-title">Weekly Schedule</h2>
    
    <?php if (empty($all_schedule)): ?>
        <div class="empty-state">
            <div class="empty-icon">📅</div>
            <h3>No schedule available</h3>
            <p>You don't have any classes scheduled yet. Please contact the principal's office.</p>
        </div>
    <?php else: ?>
        <div class="schedule-grid">
            <?php foreach ($days_order as $day): ?>
                <div class="day-column <?php echo ($day === $current_day) ? 'current-day' : ''; ?>">
                    <h3 class="day-header"><?php echo $day; ?></h3>
                    
                    <?php if (isset($schedule_by_day[$day]) && !empty($schedule_by_day[$day])): ?>
                        <div class="day-classes">
                            <?php foreach ($schedule_by_day[$day] as $class): ?>
                                <div class="schedule-item">
                                    <div class="item-time">
                                        <?php echo date('g:i A', strtotime($class['start_time'])); ?><br>
                                        <small><?php echo date('g:i A', strtotime($class['end_time'])); ?></small>
                                    </div>
                                    
                                    <div class="item-content">
                                        <h5 class="item-title">
                                            <?php echo $class['activity_name'] ? htmlspecialchars($class['activity_name']) : htmlspecialchars($class['subject_name']); ?>
                                        </h5>
                                        <p class="item-section"><?php echo htmlspecialchars($class['grade_name'] . ' - ' . $class['section_name']); ?></p>
                                        <?php if ($class['room']): ?>
                                            <p class="item-room">Room: <?php echo htmlspecialchars($class['room']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="item-type">
                                        <?php if ($class['activity_name']): ?>
                                            <span class="type-badge activity">Activity</span>
                                        <?php else: ?>
                                            <span class="type-badge subject"><?php echo htmlspecialchars($class['subject_code']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-classes">
                            <div class="no-classes-icon">📴</div>
                            <p>No classes</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Quick Actions -->
<div class="quick-actions">
    <h2 class="section-title">Quick Actions</h2>
    <div class="action-buttons">
        <a href="sections.php" class="btn btn-outline">
            <i class="fas fa-chalkboard-teacher"></i>
            My Classes
        </a>
        <a href="grades.php" class="btn btn-outline">
            <i class="fas fa-clipboard-check"></i>
            Manage Grades
        </a>
        <a href="dashboard.php" class="btn btn-outline">
            <i class="fas fa-home"></i>
            Dashboard
        </a>
        <button onclick="window.print()" class="btn btn-outline">
            <i class="fas fa-print"></i>
            Print Schedule
        </button>
    </div>
</div>

<?php endif; ?>

<style>
/* Modern Teacher Schedule Styles */
.page-header {
    background: linear-gradient(135deg, #2E86AB 0%, #A23B72 50%, #F18701 100%);
    color: white;
    padding: 3rem 2rem;
    border-radius: 20px;
    margin-bottom: 2rem;
    text-align: center;
    box-shadow: 0 10px 40px rgba(46, 134, 171, 0.3);
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
    opacity: 0.3;
}

.page-header > * {
    position: relative;
    z-index: 1;
}

.page-title {
    font-size: 3rem;
    margin-bottom: 0.75rem;
    font-weight: 800;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
    letter-spacing: -0.02em;
}

.page-subtitle {
    font-size: 1.4rem;
    opacity: 0.95;
    margin-bottom: 1.5rem;
    font-weight: 300;
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

.teacher-badge {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    padding: 0.75rem 2rem;
    border-radius: 30px;
    font-size: 1.1rem;
    font-weight: 600;
    border: 2px solid rgba(255, 255, 255, 0.2);
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.overview-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
}

.overview-card {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
    display: flex;
    align-items: center;
    gap: 1.5rem;
    transition: all 0.3s ease;
    border: 1px solid rgba(46, 134, 171, 0.1);
    position: relative;
    overflow: hidden;
}

.overview-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(135deg, #2E86AB, #A23B72);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.overview-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
}

.overview-card:hover::before {
    opacity: 1;
}

.card-icon {
    font-size: 3rem;
    width: 70px;
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 16px;
    background: linear-gradient(135deg, #E8F4F8, #F0F9FF);
    color: #2E86AB;
    box-shadow: 0 4px 15px rgba(46, 134, 171, 0.2);
}

.card-content h3 {
    font-size: 2.5rem;
    color: #1F2937;
    margin: 0 0 0.25rem 0;
    font-weight: 800;
    line-height: 1;
}

.card-content p {
    margin: 0;
    color: #6B7280;
    font-size: 1rem;
    font-weight: 600;
    letter-spacing: 0.02em;
}

.today-highlight, .weekly-schedule, .quick-actions {
    background: white;
    border-radius: 20px;
    padding: 2.5rem;
    margin-bottom: 2.5rem;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(46, 134, 171, 0.1);
    position: relative;
}

.section-title {
    font-size: 1.8rem;
    color: #1F2937;
    margin-bottom: 2rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    position: relative;
    padding-bottom: 1rem;
}

.section-title::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 60px;
    height: 3px;
    background: linear-gradient(135deg, #2E86AB, #A23B72);
    border-radius: 2px;
}

.section-title i {
    color: #2E86AB;
    font-size: 1.5rem;
}

.next-class-alert {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    background: linear-gradient(135deg, #FEF3E2 0%, #FDE68A 100%);
    border: 2px solid #F59E0B;
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 20px rgba(245, 158, 11, 0.2);
    position: relative;
    overflow: hidden;
}

.next-class-alert::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, #F59E0B, #EAB308);
}

.alert-icon {
    font-size: 2.5rem;
    color: #D97706;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.alert-content h4 {
    color: #92400E;
    margin: 0 0 0.75rem 0;
    font-size: 1.3rem;
    font-weight: 700;
}

.alert-content p {
    color: #78350F;
    margin: 0;
    font-size: 1.1rem;
    font-weight: 500;
    line-height: 1.5;
}

.today-schedule {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.class-card {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    padding: 1.5rem;
    border: 2px solid #E5E7EB;
    border-radius: 16px;
    transition: all 0.3s ease;
    background: white;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    position: relative;
    overflow: hidden;
}

.class-card::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: #E5E7EB;
    transition: all 0.3s ease;
}

.class-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.class-card.current {
    border-color: #10B981;
    background: linear-gradient(135deg, #ECFDF5 0%, #F0FDF4 100%);
    box-shadow: 0 8px 25px rgba(16, 185, 129, 0.2);
}

.class-card.current::before {
    background: linear-gradient(135deg, #10B981, #059669);
}

.class-card.past {
    border-color: #9CA3AF;
    background: #F9FAFB;
    opacity: 0.8;
}

.class-card.past::before {
    background: #9CA3AF;
}

.class-card.upcoming {
    border-color: #3B82F6;
    background: linear-gradient(135deg, #DBEAFE 0%, #EFF6FF 100%);
}

.class-card.upcoming::before {
    background: linear-gradient(135deg, #3B82F6, #2563EB);
}

.class-time {
    display: flex;
    flex-direction: column;
    min-width: 120px;
    text-align: center;
    background: rgba(46, 134, 171, 0.05);
    padding: 1rem;
    border-radius: 12px;
    border: 1px solid rgba(46, 134, 171, 0.1);
}

.start-time {
    font-weight: 800;
    color: #1F2937;
    font-size: 1.3rem;
    margin-bottom: 0.25rem;
}

.end-time {
    font-size: 1rem;
    color: #6B7280;
    font-weight: 600;
    opacity: 0.8;
}

.class-details {
    flex: 1;
    padding-left: 0.5rem;
}

.class-title {
    font-size: 1.4rem;
    color: #1F2937;
    margin: 0 0 0.75rem 0;
    font-weight: 700;
    line-height: 1.3;
}

.class-section, .class-room {
    margin: 0.5rem 0;
    color: #6B7280;
    font-size: 1rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.class-section i, .class-room i {
    color: #2E86AB;
    width: 16px;
}

.status-badge {
    padding: 0.75rem 1.5rem;
    border-radius: 25px;
    font-size: 0.9rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    border: 2px solid transparent;
    transition: all 0.3s ease;
}

.status-badge:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
}

.status-badge.current {
    background: linear-gradient(135deg, #10B981, #059669);
    color: white;
    border-color: #10B981;
    animation: glow 2s infinite alternate;
}

@keyframes glow {
    from { box-shadow: 0 2px 10px rgba(16, 185, 129, 0.3); }
    to { box-shadow: 0 4px 20px rgba(16, 185, 129, 0.6); }
}

.status-badge.past {
    background: linear-gradient(135deg, #9CA3AF, #6B7280);
    color: white;
    border-color: #9CA3AF;
}

.status-badge.upcoming {
    background: linear-gradient(135deg, #3B82F6, #2563EB);
    color: white;
    border-color: #3B82F6;
}

.schedule-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
}

.day-column {
    border: 2px solid #E5E7EB;
    border-radius: 16px;
    overflow: hidden;
    background: white;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.day-column:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.day-column.current-day {
    border-color: #2E86AB;
    box-shadow: 0 8px 30px rgba(46, 134, 171, 0.3);
    transform: scale(1.02);
}

.day-header {
    background: linear-gradient(135deg, #F8FAFC, #F1F5F9);
    color: #1F2937;
    padding: 1.5rem;
    margin: 0;
    font-size: 1.4rem;
    font-weight: 700;
    text-align: center;
    border-bottom: 2px solid #E5E7EB;
    position: relative;
}

.day-column.current-day .day-header {
    background: linear-gradient(135deg, #2E86AB, #A23B72);
    color: white;
    border-bottom-color: #2E86AB;
}

.day-column.current-day .day-header::after {
    content: '• Today';
    display: block;
    font-size: 0.8rem;
    font-weight: 500;
    opacity: 0.9;
    margin-top: 0.25rem;
}

.day-classes {
    padding: 0.5rem;
}

.schedule-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.75rem;
    margin-bottom: 0.5rem;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: white;
    transition: border-color 0.2s ease;
}

.schedule-item:hover {
    border-color: #3b82f6;
}

.item-time {
    min-width: 70px;
    text-align: center;
    font-size: 0.8rem;
    font-weight: 600;
    color: #1f2937;
}

.item-content {
    flex: 1;
}

.item-title {
    font-size: 0.9rem;
    color: #1f2937;
    margin: 0 0 0.25rem 0;
    font-weight: 600;
}

.item-section, .item-room {
    font-size: 0.75rem;
    color: #6b7280;
    margin: 0.125rem 0;
}

.item-type {
    display: flex;
    align-items: center;
}

.type-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 500;
}

.type-badge.activity {
    background: #fef3c7;
    color: #92400e;
}

.type-badge.subject {
    background: #dbeafe;
    color: #1e40af;
}

.no-classes {
    text-align: center;
    padding: 2rem;
    color: #6b7280;
}

.no-classes-icon {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.action-buttons {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
}

.btn-outline {
    background: white;
    color: #3b82f6;
    border: 2px solid #3b82f6;
}

.btn-outline:hover {
    background: #3b82f6;
    color: white;
}

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.empty-state h3 {
    color: #1f2937;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: #6b7280;
}

.alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.alert-danger {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}

/* Print Styles */
@media print {
    .page-header {
        background: #f9fafb !important;
        color: #1f2937 !important;
    }
    
    .overview-cards, .quick-actions {
        display: none;
    }
    
    .schedule-grid {
        grid-template-columns: repeat(7, 1fr);
    }
    
    .btn {
        display: none;
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .page-header {
        padding: 1.5rem;
    }
    
    .page-title {
        font-size: 2rem;
    }
    
    .overview-cards {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .class-card {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    .class-time {
        flex-direction: row;
        justify-content: space-between;
        width: 100%;
        min-width: auto;
    }
    
    .schedule-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .overview-cards {
        grid-template-columns: 1fr;
    }
    
    .overview-card {
        flex-direction: column;
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
