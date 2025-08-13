<?php
require_once '../includes/auth_check.php';

// Check if user is a teacher
if (!checkRole('teacher')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$page_title = 'Teacher Dashboard - GTBA Portal';
$base_url = '../';

try {
    $database = new Database();
    $db = $database->connect();
    
    // Get teacher information
    $query = "SELECT t.*, u.username, u.email, u.created_at as user_created_at
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
    
    // Get assigned sections
    $query = "SELECT sec.*, gl.grade_name, gl.grade_order, sy.year_label, st.is_primary,
                     COUNT(s.id) as student_count
              FROM section_teachers st
              LEFT JOIN sections sec ON st.section_id = sec.id
              LEFT JOIN grade_levels gl ON sec.grade_level_id = gl.id
              LEFT JOIN school_years sy ON sec.school_year_id = sy.id
              LEFT JOIN students s ON sec.id = s.current_section_id
              WHERE st.teacher_id = :teacher_id AND st.is_active = 1
              GROUP BY sec.id, st.is_primary, gl.grade_order
              ORDER BY st.is_primary DESC, gl.grade_order, sec.section_name";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':teacher_id', $_SESSION['user_id']);
    $stmt->execute();
    
    $assigned_sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get today's schedule
    $today = date('l'); // Get current day name (Monday, Tuesday, etc.)
    $query = "SELECT cs.*, s.subject_name, s.subject_code, sec.section_name, 
                     gl.grade_name, cs.activity_name
              FROM class_schedules cs
              LEFT JOIN subjects s ON cs.subject_id = s.id
              LEFT JOIN sections sec ON cs.section_id = sec.id
              LEFT JOIN grade_levels gl ON sec.grade_level_id = gl.id
              WHERE cs.teacher_id = :teacher_id 
              AND cs.day_of_week = :today 
              AND cs.is_active = 1
              ORDER BY cs.start_time";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':teacher_id', $_SESSION['user_id']);
    $stmt->bindParam(':today', $today);
    $stmt->execute();
    
    $today_schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $total_sections = count($assigned_sections);
    $primary_sections = count(array_filter($assigned_sections, function($section) { 
        return $section['is_primary'] == 1; 
    }));
    $total_students = array_sum(array_column($assigned_sections, 'student_count'));
    
    // Get subjects that are actually scheduled for teacher's assigned sections
    $query = "SELECT DISTINCT s.id as subject_id, s.subject_name, s.subject_code, 
                     gl.grade_name, gl.id as grade_level_id, gl.grade_order, 
                     sec.id as section_id, sec.section_name
              FROM section_teachers st
              LEFT JOIN sections sec ON st.section_id = sec.id
              LEFT JOIN grade_levels gl ON sec.grade_level_id = gl.id
              LEFT JOIN class_schedules cs ON sec.id = cs.section_id AND cs.is_active = 1
              LEFT JOIN subjects s ON cs.subject_id = s.id
              WHERE st.teacher_id = :teacher_id AND st.is_active = 1 AND s.is_active = 1 AND s.id IS NOT NULL
              ORDER BY gl.grade_order, sec.section_name, s.subject_name";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':teacher_id', $_SESSION['user_id']);
    $stmt->execute();
    
    $subjects_available = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get grade completion statistics for each section
    $grade_stats = [];
    foreach ($assigned_sections as $section) {
        $query = "SELECT s.id as subject_id, s.subject_name, s.subject_code,
                         COUNT(DISTINCT st.id) as total_students,
                         COUNT(DISTINCT sg.student_id) as graded_students
                  FROM curriculum c
                  LEFT JOIN subjects s ON c.subject_id = s.id
                  LEFT JOIN students st ON st.current_section_id = :section_id
                  LEFT JOIN student_grades sg ON sg.student_id = st.id AND sg.subject_id = s.id 
                                               AND sg.teacher_id = :teacher_id AND sg.school_year_id = :school_year_id
                  WHERE c.grade_level_id = :grade_level_id AND s.is_active = 1
                  GROUP BY s.id
                  ORDER BY s.subject_name";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':section_id', $section['id']);
        $stmt->bindParam(':teacher_id', $_SESSION['user_id']);
        $stmt->bindParam(':school_year_id', $current_year['id']);
        $stmt->bindParam(':grade_level_id', $section['grade_level_id']);
        $stmt->execute();
        
        $grade_stats[$section['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get recent announcements with attachment information
    $query = "SELECT a.*, u.username as created_by_username 
              FROM announcements a 
              LEFT JOIN users u ON a.created_by = u.id
              WHERE a.is_published = 1 AND (a.target_audience = 'All' OR a.target_audience = 'Teachers')
              ORDER BY a.created_at DESC 
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $recent_announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get attachments for each announcement
    foreach ($recent_announcements as &$announcement) {
        $attachment_query = "SELECT id, filename, original_filename, file_path, mime_type, file_size 
                            FROM announcement_attachments 
                            WHERE announcement_id = ? 
                            ORDER BY created_at";
        $attachment_stmt = $db->prepare($attachment_query);
        $attachment_stmt->execute([$announcement['id']]);
        $announcement['attachments'] = $attachment_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    $error_message = "Unable to load teacher dashboard data: " . $e->getMessage();
    error_log("Teacher dashboard error: " . $e->getMessage());
}

ob_start();
?>

<div class="welcome-section">
    <h1 class="welcome-title">Teacher Dashboard</h1>
    <p class="welcome-subtitle">Welcome back, <?php echo isset($teacher_info) ? htmlspecialchars($teacher_info['first_name'] . ' ' . $teacher_info['last_name']) : 'Teacher'; ?></p>
    
    <?php if (isset($teacher_info)): ?>
        <div class="teacher-info">
            <h4>Teacher Information</h4>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Employee ID:</span>
                    <span class="info-value"><?php echo htmlspecialchars($teacher_info['employee_id']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Specialization:</span>
                    <span class="info-value"><?php echo htmlspecialchars($teacher_info['specialization'] ?? 'Not specified'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Employment Status:</span>
                    <span class="info-value"><?php echo htmlspecialchars($teacher_info['employment_status']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo htmlspecialchars($teacher_info['email']); ?></span>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <?php echo $error_message; ?>
    </div>
<?php else: ?>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card primary">
        <div class="stat-icon">📚</div>
        <div class="stat-content">
            <h3><?php echo $total_sections; ?></h3>
            <p>Assigned Sections</p>
        </div>
    </div>
    
    <div class="stat-card success">
        <div class="stat-icon">👨‍🏫</div>
        <div class="stat-content">
            <h3><?php echo $primary_sections; ?></h3>
            <p>Primary/Adviser Sections</p>
        </div>
    </div>
    
    <div class="stat-card warning">
        <div class="stat-icon">👥</div>
        <div class="stat-content">
            <h3><?php echo $total_students; ?></h3>
            <p>Total Students</p>
        </div>
    </div>
    
    <div class="stat-card info">
        <div class="stat-icon">📖</div>
        <div class="stat-content">
            <h3><?php echo count($subjects_available); ?></h3>
            <p>Subjects Available to Manage</p>
        </div>
    </div>
</div>

<!-- Today's Schedule -->
<?php if (!empty($today_schedule)): ?>
<div class="schedule-section">
    <h2 class="section-title">Today's Schedule (<?php echo $today; ?>)</h2>
    <div class="schedule-list">
        <?php foreach ($today_schedule as $schedule): ?>
            <div class="schedule-item">
                <div class="schedule-time">
                    <span class="start-time"><?php echo date('g:i A', strtotime($schedule['start_time'])); ?></span>
                    <span class="end-time"><?php echo date('g:i A', strtotime($schedule['end_time'])); ?></span>
                </div>
                <div class="schedule-details">
                    <h4 class="schedule-title">
                        <?php echo $schedule['activity_name'] ? htmlspecialchars($schedule['activity_name']) : htmlspecialchars($schedule['subject_name']); ?>
                    </h4>
                    <p class="schedule-meta">
                        <span class="section-info"><?php echo htmlspecialchars($schedule['grade_name'] . ' - ' . $schedule['section_name']); ?></span>
                        <?php if ($schedule['room']): ?>
                            <span class="room-info">Room: <?php echo htmlspecialchars($schedule['room']); ?></span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="schedule-type">
                    <?php if ($schedule['activity_name']): ?>
                        <span class="activity-badge">Activity</span>
                    <?php else: ?>
                        <span class="subject-badge"><?php echo htmlspecialchars($schedule['subject_code']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php else: ?>
<div class="schedule-section">
    <h2 class="section-title">Today's Schedule (<?php echo $today; ?>)</h2>
    <div class="empty-state">
        <div class="empty-icon">📅</div>
        <h3>No classes scheduled for today</h3>
        <p>Enjoy your day off or check your weekly schedule for upcoming classes.</p>
    </div>
</div>
<?php endif; ?>

<!-- Assigned Sections -->
<?php if (!empty($assigned_sections)): ?>
<div class="sections-overview">
    <h2 class="section-title">Your Assigned Sections</h2>
    <div class="sections-grid">
        <?php foreach ($assigned_sections as $section): ?>
            <div class="section-card <?php echo $section['is_primary'] ? 'primary-section' : ''; ?>">
                <div class="section-header">
                    <h3 class="section-name"><?php echo htmlspecialchars($section['section_name']); ?></h3>
                    <?php if ($section['is_primary']): ?>
                        <span class="adviser-badge">Class Adviser</span>
                    <?php endif; ?>
                </div>
                <div class="section-info">
                    <p class="grade-level"><?php echo htmlspecialchars($section['grade_name']); ?></p>
                    <p class="student-count">
                        <?php echo $section['student_count']; ?> student<?php echo $section['student_count'] != 1 ? 's' : ''; ?>
                        <?php if ($section['current_enrollment']): ?>
                            / <?php echo $section['current_enrollment']; ?> enrolled
                        <?php endif; ?>
                    </p>
                    <p class="school-year"><?php echo htmlspecialchars($section['year_label']); ?></p>
                </div>
                
                <!-- Grade Management Progress -->
                <?php if (isset($grade_stats[$section['id']]) && !empty($grade_stats[$section['id']])): ?>
                    <div class="grade-progress">
                        <h4 class="progress-title">Grade Management Progress</h4>
                        <div class="subject-progress-list">
                            <?php foreach ($grade_stats[$section['id']] as $subject_stat): ?>
                                <div class="subject-progress-item">
                                    <div class="subject-info">
                                        <span class="subject-code"><?php echo htmlspecialchars($subject_stat['subject_code']); ?></span>
                                        <span class="subject-name"><?php echo htmlspecialchars($subject_stat['subject_name']); ?></span>
                                    </div>
                                    <div class="progress-info">
                                        <?php 
                                        $completion = $subject_stat['total_students'] > 0 ? 
                                                     round(($subject_stat['graded_students'] / $subject_stat['total_students']) * 100) : 0;
                                        $status_class = $completion >= 100 ? 'complete' : ($completion >= 50 ? 'partial' : 'pending');
                                        ?>
                                        <div class="progress-bar">
                                            <div class="progress-fill <?php echo $status_class; ?>" 
                                                 style="width: <?php echo $completion; ?>%"></div>
                                        </div>
                                        <span class="progress-text"><?php echo $subject_stat['graded_students']; ?>/<?php echo $subject_stat['total_students']; ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="section-actions">
                    <a href="sections.php?section_id=<?php echo $section['id']; ?>" class="btn btn-primary btn-sm">View Details</a>
                    <a href="grades.php?section_id=<?php echo $section['id']; ?>" class="btn btn-outline btn-sm">Manage Grades</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Grade Management Overview -->
<?php if (!empty($subjects_available)): ?>
<div class="subjects-section">
    <h2 class="section-title">Grade Management Overview</h2>
    <p class="section-subtitle">As a teacher assigned to sections, you can manage grades for all subjects in your assigned sections. You have full CRUD (Create, Read, Update, Delete) access to student grades.</p>
    
    <div class="grade-management-summary">
        <div class="management-capabilities">
            <h3>Your Grade Management Capabilities:</h3>
            <ul class="capabilities-list">
                <li><i class="fas fa-plus-circle"></i> <strong>Create:</strong> Add new grades for students in your sections</li>
                <li><i class="fas fa-eye"></i> <strong>Read:</strong> View all existing grades for your assigned sections</li>
                <li><i class="fas fa-edit"></i> <strong>Update:</strong> Modify existing grades and remarks</li>
                <li><i class="fas fa-trash-alt"></i> <strong>Delete:</strong> Remove grade entries when necessary</li>
            </ul>
        </div>
    </div>
    
    <div class="subjects-by-section">
        <?php 
        $grouped_subjects = [];
        foreach ($subjects_available as $subject) {
            $section_key = $subject['section_name'] . ' (' . $subject['grade_name'] . ')';
            if (!isset($grouped_subjects[$section_key])) {
                $grouped_subjects[$section_key] = [
                    'section_id' => $subject['section_id'],
                    'subjects' => []
                ];
            }
            $grouped_subjects[$section_key]['subjects'][] = $subject;
        }
        
        foreach ($grouped_subjects as $section_name => $section_data): ?>
            <div class="section-subjects-group">
                <div class="section-subjects-header">
                    <h4 class="section-subjects-title"><?php echo htmlspecialchars($section_name); ?></h4>
                    <a href="grades.php?section_id=<?php echo $section_data['section_id']; ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-clipboard-check"></i> Manage Grades
                    </a>
                </div>
                <div class="subjects-in-section">
                    <?php foreach ($section_data['subjects'] as $subject): ?>
                        <div class="subject-management-item">
                            <div class="subject-details">
                                <span class="subject-code"><?php echo htmlspecialchars($subject['subject_code']); ?></span>
                                <span class="subject-name"><?php echo htmlspecialchars($subject['subject_name']); ?></span>
                            </div>
                            <div class="subject-actions">
                                <a href="grades.php?section_id=<?php echo $section_data['section_id']; ?>&subject_id=<?php echo $subject['subject_id']; ?>" 
                                   class="btn btn-outline btn-xs">
                                    <i class="fas fa-pencil-alt"></i> Grade
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Recent Announcements -->
<?php if (!empty($recent_announcements)): ?>
<div class="recent-announcements-section">
    <h2 class="section-title">Recent Announcements</h2>
    <div class="announcements-list">
        <?php foreach ($recent_announcements as $announcement): ?>
            <div class="announcement-item clickable-announcement" 
                 data-id="<?php echo $announcement['id']; ?>"
                 data-title="<?php echo htmlspecialchars($announcement['title']); ?>"
                 data-content="<?php echo htmlspecialchars($announcement['content']); ?>"
                 data-type="<?php echo htmlspecialchars($announcement['announcement_type']); ?>"
                 data-priority="<?php echo htmlspecialchars($announcement['priority']); ?>"
                 data-author="<?php echo htmlspecialchars($announcement['created_by_username']); ?>"
                 data-date="<?php echo date('M j, Y', strtotime($announcement['created_at'])); ?>"
                 data-attachments="<?php echo htmlspecialchars(json_encode($announcement['attachments'])); ?>"
                 style="cursor: pointer;">
                <div class="announcement-info">
                    <div class="announcement-title">
                        <?php echo htmlspecialchars($announcement['title']); ?>
                    </div>
                    <div class="announcement-details">
                        <span class="type"><?php echo htmlspecialchars($announcement['announcement_type']); ?></span>
                        <span class="author">by <?php echo htmlspecialchars($announcement['created_by_username']); ?></span>
                        <span class="date"><?php echo date('M j, Y', strtotime($announcement['created_at'])); ?></span>
                    </div>
                </div>
                <div class="announcement-status">
                    <span class="priority-badge priority-<?php echo strtolower($announcement['priority']); ?>">
                        <?php echo htmlspecialchars($announcement['priority']); ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="announcements-footer">
        <a href="../principal/announcements.php" class="btn btn-outline">View All Announcements</a>
    </div>
</div>
<?php endif; ?>

<!-- Quick Actions -->
<div class="quick-actions">
    <h2 class="section-title">Quick Actions</h2>
    <div class="action-buttons">
        <a href="sections.php" class="btn btn-outline">
            <i class="fas fa-chalkboard-teacher"></i>
            Manage Classes
        </a>
        <a href="grades.php" class="btn btn-outline">
            <i class="fas fa-clipboard-check"></i>
            Manage Grades
        </a>
        <a href="schedule.php" class="btn btn-outline">
            <i class="fas fa-calendar-alt"></i>
            View Full Schedule
        </a>
        <a href="../principal/announcements.php" class="btn btn-outline">
            <i class="fas fa-bullhorn"></i>
            School Announcements
        </a>
    </div>
</div>

<?php endif; ?>

<style>
/* Teacher Dashboard Specific Styles */
.welcome-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    border-radius: 15px;
    margin-bottom: 2rem;
    text-align: center;
}

.welcome-title {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
    font-weight: 700;
}

.welcome-subtitle {
    font-size: 1.2rem;
    opacity: 0.9;
    margin-bottom: 1.5rem;
}

.teacher-info {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    padding: 1.5rem;
    margin-top: 1rem;
}

.teacher-info h4 {
    margin-bottom: 1rem;
    font-size: 1.3rem;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.info-label {
    font-size: 0.9rem;
    opacity: 0.8;
    font-weight: 500;
}

.info-value {
    font-size: 1rem;
    font-weight: 600;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: transform 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.stat-card.primary { border-left: 4px solid #3b82f6; }
.stat-card.success { border-left: 4px solid #10b981; }
.stat-card.warning { border-left: 4px solid #f59e0b; }
.stat-card.info { border-left: 4px solid #8b5cf6; }

.stat-icon {
    font-size: 2.5rem;
}

.stat-content h3 {
    font-size: 2rem;
    color: #1f2937;
    margin: 0;
    font-weight: 700;
}

.stat-content p {
    margin: 0;
    color: #6b7280;
    font-size: 0.9rem;
    font-weight: 500;
}

.schedule-section, .sections-overview, .subjects-section, .quick-actions {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.section-title {
    font-size: 1.5rem;
    color: #1f2937;
    margin-bottom: 1.5rem;
    font-weight: 600;
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 0.5rem;
}

.section-subtitle {
    color: #6b7280;
    margin-bottom: 1.5rem;
    font-style: italic;
}

.grade-group {
    margin-bottom: 1.5rem;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 1rem;
}

.grade-header {
    color: #3b82f6;
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #e5e7eb;
}

.subjects-in-grade {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 0.5rem;
}

.schedule-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.schedule-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    transition: border-color 0.2s ease;
}

.schedule-item:hover {
    border-color: #3b82f6;
}

.schedule-time {
    display: flex;
    flex-direction: column;
    min-width: 100px;
    text-align: center;
}

.start-time, .end-time {
    font-weight: 600;
    color: #1f2937;
}

.end-time {
    font-size: 0.9rem;
    color: #6b7280;
}

.schedule-details {
    flex: 1;
}

.schedule-title {
    font-size: 1.1rem;
    color: #1f2937;
    margin: 0 0 0.5rem 0;
    font-weight: 600;
}

.schedule-meta {
    margin: 0;
    color: #6b7280;
    font-size: 0.9rem;
}

.section-info, .room-info {
    display: inline-block;
    margin-right: 1rem;
}

.schedule-type {
    display: flex;
    align-items: center;
}

.activity-badge, .subject-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.activity-badge {
    background: #fef3c7;
    color: #92400e;
}

.subject-badge {
    background: #dbeafe;
    color: #1e40af;
}

.sections-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1rem;
}

.section-card {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 1.5rem;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.section-card:hover {
    border-color: #3b82f6;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
}

.section-card.primary-section {
    border-color: #10b981;
    background: linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 100%);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.section-name {
    font-size: 1.2rem;
    color: #1f2937;
    margin: 0;
    font-weight: 600;
}

.adviser-badge {
    background: #10b981;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
}

.section-info p {
    margin: 0.25rem 0;
    color: #6b7280;
}

.grade-level {
    font-weight: 600;
    color: #1f2937 !important;
}

.section-actions {
    margin-top: 1rem;
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.grade-progress {
    margin: 1rem 0;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
}

.progress-title {
    font-size: 0.9rem;
    color: #374151;
    margin-bottom: 0.75rem;
    font-weight: 600;
}

.subject-progress-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.subject-progress-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem;
    background: white;
    border-radius: 4px;
    border: 1px solid #e5e7eb;
}

.subject-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    flex: 1;
}

.subject-info .subject-code {
    font-size: 0.75rem;
    font-weight: 600;
    color: #3b82f6;
}

.subject-info .subject-name {
    font-size: 0.8rem;
    color: #374151;
}

.progress-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 100px;
}

.progress-bar {
    width: 60px;
    height: 6px;
    background: #e5e7eb;
    border-radius: 3px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.3s ease;
}

.progress-fill.complete {
    background: #10b981;
}

.progress-fill.partial {
    background: #f59e0b;
}

.progress-fill.pending {
    background: #ef4444;
}

.progress-text {
    font-size: 0.75rem;
    color: #6b7280;
    font-weight: 500;
}

.grade-management-summary {
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
}

.management-capabilities h3 {
    color: #0c4a6e;
    margin-bottom: 1rem;
    font-size: 1.1rem;
}

.capabilities-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 0.75rem;
}

.capabilities-list li {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
    background: rgba(255, 255, 255, 0.7);
    border-radius: 6px;
    border: 1px solid rgba(14, 165, 233, 0.2);
}

.capabilities-list i {
    color: #0ea5e9;
    font-size: 1rem;
}

.subjects-by-section {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.section-subjects-group {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 1.5rem;
    background: #fafafa;
}

.section-subjects-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #e5e7eb;
}

.section-subjects-title {
    color: #1f2937;
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0;
}

.subjects-in-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 0.75rem;
}

.subject-management-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    transition: border-color 0.2s ease;
}

.subject-management-item:hover {
    border-color: #3b82f6;
}

.subject-details {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.subject-details .subject-code {
    font-weight: 600;
    color: #3b82f6;
    font-size: 0.85rem;
}

.subject-details .subject-name {
    color: #374151;
    font-size: 0.9rem;
}

.subject-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-xs {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    border-radius: 4px;
}

.subjects-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 0.75rem;
}

.subject-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
}

.subject-code {
    font-weight: 600;
    color: #3b82f6;
    min-width: 80px;
}

.subject-name {
    color: #1f2937;
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

.btn-primary {
    background: #3b82f6;
    color: white;
}

.btn-primary:hover {
    background: #2563eb;
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

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
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

/* Responsive Design */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
    
    .schedule-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    .schedule-time {
        flex-direction: row;
        justify-content: space-between;
        width: 100%;
        min-width: auto;
    }
    
    .action-buttons {
        grid-template-columns: 1fr;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
}

/* Recent Announcements Styles */
.recent-announcements-section {
    background: var(--white);
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.announcements-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.announcement-item {
    background: var(--white);
    border: 1px solid var(--border-gray);
    border-radius: 10px;
    padding: 1.5rem;
    transition: all 0.3s ease;
    border-left: 4px solid var(--primary-blue);
}

.announcement-item:hover {
    background: var(--light-blue);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.announcement-info {
    flex: 1;
}

.announcement-title {
    font-weight: 600;
    color: var(--dark-blue);
    margin-bottom: 0.5rem;
    font-size: 1.1rem;
}

.announcement-details {
    display: flex;
    gap: 1rem;
    font-size: 0.9rem;
    color: var(--gray);
    flex-wrap: wrap;
}

.announcement-status {
    display: flex;
    align-items: center;
}

.priority-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.priority-low { background: #e8f5e8; color: #2e7d32; }
.priority-normal { background: #e3f2fd; color: #1565c0; }
.priority-high { background: #fff3e0; color: #ef6c00; }
.priority-urgent { background: #ffebee; color: #c62828; }

.announcements-footer {
    text-align: center;
    margin-top: 1.5rem;
}

/* Announcement Modal Styles */
.announcement-modal {
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

.announcement-modal.show {
    visibility: visible;
    opacity: 1;
}

.modal-content {
    background: white;
    margin: 2rem;
    padding: 0;
    border-radius: 15px;
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideIn 0.3s ease;
}

.modal-header {
    background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
    color: white;
    padding: 1.5rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
}

.modal-title {
    font-size: 1.4rem;
    font-weight: 700;
    margin: 0;
    line-height: 1.3;
    flex: 1;
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
    flex-shrink: 0;
}

.modal-close:hover {
    background: rgba(255, 255, 255, 0.2);
}

.modal-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-top: 0.75rem;
    font-size: 0.9rem;
    opacity: 0.9;
}

.modal-meta span {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.modal-body {
    padding: 2rem;
    overflow-y: auto;
    max-height: 50vh;
}

.modal-content-text {
    font-size: 1rem;
    line-height: 1.6;
    color: var(--text);
    white-space: pre-wrap;
}

.modal-attachments {
    margin-top: 1.5rem;
}

.modal-attachments-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark-blue);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-attachments-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.modal-attachment-item {
    position: relative;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
    cursor: pointer;
}

.modal-attachment-item:hover {
    transform: scale(1.02);
}

.modal-attachment-image {
    width: 100%;
    height: 150px;
    object-fit: cover;
    display: block;
}

.modal-attachment-info {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(transparent, rgba(0,0,0,0.8));
    color: white;
    padding: 1rem 0.75rem 0.75rem;
    font-size: 0.8rem;
}

.modal-attachment-name {
    font-weight: 600;
    margin-bottom: 0.25rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.modal-attachment-size {
    opacity: 0.8;
}

/* Image lightbox */
.image-lightbox {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.9);
    align-items: center;
    justify-content: center;
}

.image-lightbox.show {
    display: flex;
}

.lightbox-content {
    max-width: 90%;
    max-height: 90%;
    object-fit: contain;
    border-radius: 8px;
}

.lightbox-close {
    position: absolute;
    top: 20px;
    right: 30px;
    color: white;
    font-size: 40px;
    font-weight: bold;
    cursor: pointer;
    z-index: 2001;
}

.lightbox-close:hover {
    opacity: 0.7;
}

.modal-footer {
    background: var(--light-gray);
    padding: 1rem 2rem;
    border-top: 1px solid var(--border-gray);
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

.modal-priority {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
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
</style>

<!-- Announcement Modal -->
<div id="announcementModal" class="announcement-modal">
    <div class="modal-content">
        <div class="modal-header">
            <div>
                <h3 class="modal-title" id="modalTitle"></h3>
                <div class="modal-meta">
                    <span>📅 <span id="modalDate"></span></span>
                    <span>👤 <span id="modalAuthor"></span></span>
                    <span>📝 <span id="modalType"></span></span>
                    <span class="modal-priority" id="modalPriority"></span>
                </div>
            </div>
            <button class="modal-close" onclick="closeAnnouncementModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="modal-content-text" id="modalContent"></div>
            <div class="modal-attachments" id="modalAttachments"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeAnnouncementModal()">Close</button>
        </div>
    </div>
</div>

<script>
// Announcement Modal Functions
function openAnnouncementModal(announcement) {
    const modal = document.getElementById('announcementModal');
    const title = document.getElementById('modalTitle');
    const content = document.getElementById('modalContent');
    const date = document.getElementById('modalDate');
    const author = document.getElementById('modalAuthor');
    const type = document.getElementById('modalType');
    const priority = document.getElementById('modalPriority');
    const attachments = document.getElementById('modalAttachments');
    
    title.textContent = announcement.title;
    content.textContent = announcement.content;
    date.textContent = announcement.date;
    author.textContent = announcement.author;
    type.textContent = announcement.type;
    priority.textContent = announcement.priority;
    
    // Set priority styling
    priority.className = 'modal-priority priority-' + announcement.priority.toLowerCase();
    
    // Display attachments if any
    if (announcement.attachments && announcement.attachments.length > 0) {
        let attachmentsHtml = `
            <div class="modal-attachments-title">
                <i class="fas fa-images"></i>
                Attachments (${announcement.attachments.length})
            </div>
            <div class="modal-attachments-grid">
        `;
        
        announcement.attachments.forEach(attachment => {
            const fileSizeKB = Math.round(attachment.file_size / 1024);
            attachmentsHtml += `
                <div class="modal-attachment-item" onclick="openImageLightbox('${attachment.file_path}', '${attachment.original_filename}')">
                    <img src="${attachment.file_path}" alt="${attachment.original_filename}" class="modal-attachment-image" loading="lazy">
                    <div class="modal-attachment-info">
                        <div class="modal-attachment-name">${attachment.original_filename}</div>
                        <div class="modal-attachment-size">${fileSizeKB} KB</div>
                    </div>
                </div>
            `;
        });
        
        attachmentsHtml += '</div>';
        attachments.innerHTML = attachmentsHtml;
        attachments.style.display = 'block';
    } else {
        attachments.innerHTML = '';
        attachments.style.display = 'none';
    }
    
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function openImageLightbox(imagePath, imageName) {
    // Create lightbox if it doesn't exist
    let lightbox = document.getElementById('imageLightbox');
    if (!lightbox) {
        lightbox = document.createElement('div');
        lightbox.id = 'imageLightbox';
        lightbox.className = 'image-lightbox';
        lightbox.innerHTML = `
            <span class="lightbox-close" onclick="closeImageLightbox()">&times;</span>
            <img class="lightbox-content" id="lightboxImage">
        `;
        document.body.appendChild(lightbox);
    }
    
    const lightboxImage = document.getElementById('lightboxImage');
    lightboxImage.src = imagePath;
    lightboxImage.alt = imageName;
    lightbox.classList.add('show');
}

function closeImageLightbox() {
    const lightbox = document.getElementById('imageLightbox');
    if (lightbox) {
        lightbox.classList.remove('show');
    }
}

function closeAnnouncementModal() {
    const modal = document.getElementById('announcementModal');
    modal.classList.remove('show');
    document.body.style.overflow = 'auto';
}

// Add click event listeners to announcement items
document.addEventListener('DOMContentLoaded', function() {
    const announcementItems = document.querySelectorAll('.clickable-announcement');
    
    announcementItems.forEach(item => {
        item.addEventListener('click', function() {
            let attachments = [];
            try {
                attachments = JSON.parse(this.dataset.attachments || '[]');
            } catch (e) {
                console.warn('Error parsing attachments:', e);
                attachments = [];
            }
            
            const announcement = {
                id: this.dataset.id,
                title: this.dataset.title,
                content: this.dataset.content,
                type: this.dataset.type,
                priority: this.dataset.priority,
                author: this.dataset.author,
                date: this.dataset.date,
                attachments: attachments
            };
            
            openAnnouncementModal(announcement);
        });
    });
    
    // Close modal when clicking outside
    document.getElementById('announcementModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeAnnouncementModal();
        }
    });
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAnnouncementModal();
            closeImageLightbox();
        }
    });
    
    // Close lightbox when clicking outside image
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('image-lightbox')) {
            closeImageLightbox();
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
