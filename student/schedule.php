<?php
require_once '../includes/auth_check.php';

// Check if user is a student
if (!checkRole('student')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$page_title = 'Class Schedule - GTBA Portal';
$base_url = '../';

try {
    $database = new Database();
    $db = $database->connect();
    
    // Get student's current section and schedule
    $query = "SELECT cs.*, 
                     CASE 
                         WHEN cs.activity_name IS NOT NULL THEN cs.activity_name
                         WHEN cs.subject_id IS NOT NULL THEN s.subject_name
                         ELSE 'Unknown Activity'
                     END as activity_display,
                     CASE 
                         WHEN cs.activity_name IS NOT NULL THEN 'activity'
                         WHEN cs.subject_id IS NOT NULL THEN 'subject'
                         ELSE 'unknown'
                     END as schedule_type,
                     s.subject_name, 
                     CASE 
                         WHEN cs.teacher_id IS NOT NULL THEN CONCAT(t.first_name, ' ', t.last_name)
                         ELSE NULL
                     END as teacher_name,
                     sec.section_name, gl.grade_name
              FROM students st
              LEFT JOIN sections sec ON st.current_section_id = sec.id
              LEFT JOIN grade_levels gl ON st.current_grade_level_id = gl.id
              LEFT JOIN class_schedules cs ON sec.id = cs.section_id AND st.current_school_year_id = cs.school_year_id
              LEFT JOIN subjects s ON cs.subject_id = s.id
              LEFT JOIN teachers t ON cs.teacher_id = t.user_id
              WHERE st.user_id = :user_id AND cs.is_active = 1
              ORDER BY 
                CASE cs.day_of_week 
                    WHEN 'Monday' THEN 1
                    WHEN 'Tuesday' THEN 2
                    WHEN 'Wednesday' THEN 3
                    WHEN 'Thursday' THEN 4
                    WHEN 'Friday' THEN 5
                    WHEN 'Saturday' THEN 6
                END,
                cs.start_time";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get student info for section details
    $query = "SELECT s.*, sec.section_name, gl.grade_name, sy.year_label
              FROM students s
              LEFT JOIN sections sec ON s.current_section_id = sec.id
              LEFT JOIN grade_levels gl ON s.current_grade_level_id = gl.id
              LEFT JOIN school_years sy ON s.current_school_year_id = sy.id
              WHERE s.user_id = :user_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    
    $student_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Unable to load schedule information.";
    error_log("Student schedule error: " . $e->getMessage());
}

// Group schedules by day
$schedule_by_day = [];
if (!empty($schedules)) {
    foreach ($schedules as $schedule) {
        if ($schedule['day_of_week']) {
            $schedule_by_day[$schedule['day_of_week']][] = $schedule;
        }
    }
}

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

ob_start();
?>

<div class="schedule-page">
    <!-- Header Section -->
    <div class="page-header">
        <div class="header-content">
            <div class="header-left">
                <h1 class="page-title" style="color: var(--white);">
                    <i class="fas fa-calendar-week"></i>
                    My Class Schedule
                </h1>
                <p class="page-subtitle" style="color: var(--white);">Weekly timetable for all subjects and activities</p>
            </div>
            <div class="header-right">
                <?php if (!empty($schedules)): ?>
                <div class="schedule-overview">
                    <div class="overview-stat">
                        <span class="stat-number"><?php echo count(array_unique(array_column($schedules, 'day_of_week'))); ?></span>
                        <span class="stat-label">Active Days</span>
                    </div>
                    <div class="overview-stat">
                        <span class="stat-number"><?php echo count($schedules); ?></span>
                        <span class="stat-label">Total Classes</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($student_info && $student_info['section_name']): ?>
        <div class="student-info">
            <div class="info-item">
                <i class="fas fa-users"></i>
                <span><?php echo htmlspecialchars($student_info['section_name']); ?></span>
            </div>
            <div class="info-item">
                <i class="fas fa-graduation-cap"></i>
                <span><?php echo htmlspecialchars($student_info['grade_name']); ?></span>
            </div>
            <div class="info-item">
                <i class="fas fa-calendar-alt"></i>
                <span><?php echo htmlspecialchars($student_info['year_label']); ?></span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Error/Warning Messages -->
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $error_message; ?></span>
        </div>
    <?php elseif (!$student_info || !$student_info['current_section_id']): ?>
        <div class="alert alert-warning">
            <i class="fas fa-info-circle"></i>
            <span>You are not yet assigned to a section. Please contact the registrar's office.</span>
        </div>
    <?php elseif (empty($schedules)): ?>
        <div class="alert alert-info">
            <i class="fas fa-calendar-times"></i>
            <span>No class schedule has been set up yet for your section. Please check back later.</span>
        </div>
    <?php else: ?>

    <!-- Weekly Schedule Grid -->
    <div class="schedule-container">
        <div class="schedule-header">
            <h2>Weekly Schedule</h2>
            <div class="schedule-controls">
                <div class="legend">
                    <div class="legend-item">
                        <div class="color-box subject"></div>
                        <span>Subjects</span>
                    </div>
                    <div class="legend-item">
                        <div class="color-box activity"></div>
                        <span>Activities</span>
                    </div>
                </div>
                <button onclick="window.print()" class="print-btn">
                    <i class="fas fa-print"></i>
                    Print
                </button>
            </div>
        </div>

        <div class="weekly-grid">
            <!-- Time Column -->
            <div class="time-column">
                <div class="time-header">Time</div>
                <?php
                // Generate time slots from 7:00 AM to 6:00 PM
                $start_hour = 7;
                $end_hour = 18;
                for ($hour = $start_hour; $hour <= $end_hour; $hour++) {
                    $time_12 = ($hour > 12) ? ($hour - 12) . ':00 PM' : (($hour == 12) ? '12:00 PM' : $hour . ':00 AM');
                    echo '<div class="time-slot">' . $time_12 . '</div>';
                }
                ?>
            </div>

            <!-- Days Columns -->
            <?php foreach ($days as $day): ?>
            <div class="day-column">
                <div class="day-header">
                    <span class="day-name"><?php echo $day; ?></span>
                </div>
                
                <div class="day-content">
                    <?php if (isset($schedule_by_day[$day]) && !empty($schedule_by_day[$day])): ?>
                        <?php 
                        // Sort classes by start time to handle overlaps better
                        $day_classes = $schedule_by_day[$day];
                        usort($day_classes, function($a, $b) {
                            return strtotime($a['start_time']) - strtotime($b['start_time']);
                        });
                        
                        // Pre-process all classes to determine overlaps
                        $processed_classes = [];
                        foreach ($day_classes as $index => $class) {
                            $start_time = strtotime($class['start_time']);
                            $end_time = strtotime($class['end_time']);
                            $start_hour = (int)date('H', $start_time);
                            $start_minute = (int)date('i', $start_time);
                            $duration_hours = ($end_time - $start_time) / 3600;
                            
                            // Calculate position (starting from 7 AM = 0)
                            $top_position = (($start_hour - 7) + ($start_minute / 60)) * 80; // 80px per hour
                            $height = $duration_hours * 80; // 80px per hour
                            $bottom_position = $top_position + $height;
                            
                            $processed_classes[$index] = [
                                'class' => $class,
                                'top' => $top_position,
                                'bottom' => $bottom_position,
                                'height' => $height,
                                'start_time' => $start_time,
                                'end_time' => $end_time,
                                'column' => 0,
                                'max_columns' => 1
                            ];
                        }
                        
                        // Determine overlapping groups
                        $overlap_groups = [];
                        foreach ($processed_classes as $i => $class1) {
                            $group_found = false;
                            
                            // Check if this class overlaps with any existing group
                            foreach ($overlap_groups as $group_id => &$group) {
                                $overlaps_with_group = false;
                                foreach ($group['classes'] as $class_in_group) {
                                    $class2 = $processed_classes[$class_in_group];
                                    // Check for overlap (including touching boundaries)
                                    if ($class1['top'] < $class2['bottom'] && $class1['bottom'] > $class2['top']) {
                                        $overlaps_with_group = true;
                                        break;
                                    }
                                }
                                
                                if ($overlaps_with_group) {
                                    $group['classes'][] = $i;
                                    $group_found = true;
                                    break;
                                }
                            }
                            
                            // If no group found, create new group
                            if (!$group_found) {
                                $overlap_groups[] = [
                                    'classes' => [$i],
                                    'columns' => 1
                                ];
                            }
                        }
                        
                        // Merge overlapping groups
                        $merged = true;
                        while ($merged) {
                            $merged = false;
                            for ($i = 0; $i < count($overlap_groups) - 1; $i++) {
                                for ($j = $i + 1; $j < count($overlap_groups); $j++) {
                                    $group1_classes = $overlap_groups[$i]['classes'];
                                    $group2_classes = $overlap_groups[$j]['classes'];
                                    
                                    // Check if any class in group1 overlaps with any class in group2
                                    $groups_overlap = false;
                                    foreach ($group1_classes as $class1_idx) {
                                        foreach ($group2_classes as $class2_idx) {
                                            $c1 = $processed_classes[$class1_idx];
                                            $c2 = $processed_classes[$class2_idx];
                                            if ($c1['top'] < $c2['bottom'] && $c1['bottom'] > $c2['top']) {
                                                $groups_overlap = true;
                                                break 2;
                                            }
                                        }
                                    }
                                    
                                    if ($groups_overlap) {
                                        $overlap_groups[$i]['classes'] = array_merge($group1_classes, $group2_classes);
                                        array_splice($overlap_groups, $j, 1);
                                        $merged = true;
                                        break 2;
                                    }
                                }
                            }
                        }
                        
                        // Assign columns within each overlap group
                        foreach ($overlap_groups as &$group) {
                            $group_classes = array_map(function($idx) use ($processed_classes) {
                                return $processed_classes[$idx];
                            }, $group['classes']);
                            
                            // Sort by start time within the group
                            usort($group['classes'], function($a, $b) use ($processed_classes) {
                                return $processed_classes[$a]['start_time'] - $processed_classes[$b]['start_time'];
                            });
                            
                            // Assign columns using interval scheduling
                            $columns = [];
                            foreach ($group['classes'] as $class_idx) {
                                $class = $processed_classes[$class_idx];
                                $assigned_column = 0;
                                
                                // Find first available column
                                while (true) {
                                    $can_use_column = true;
                                    if (isset($columns[$assigned_column])) {
                                        foreach ($columns[$assigned_column] as $existing_class_idx) {
                                            $existing_class = $processed_classes[$existing_class_idx];
                                            // Check if there's overlap
                                            if ($class['top'] < $existing_class['bottom'] && $class['bottom'] > $existing_class['top']) {
                                                $can_use_column = false;
                                                break;
                                            }
                                        }
                                    }
                                    
                                    if ($can_use_column) {
                                        if (!isset($columns[$assigned_column])) {
                                            $columns[$assigned_column] = [];
                                        }
                                        $columns[$assigned_column][] = $class_idx;
                                        $processed_classes[$class_idx]['column'] = $assigned_column;
                                        break;
                                    }
                                    $assigned_column++;
                                }
                            }
                            
                            $group['columns'] = count($columns);
                            
                            // Update max_columns for all classes in this group
                            foreach ($group['classes'] as $class_idx) {
                                $processed_classes[$class_idx]['max_columns'] = $group['columns'];
                            }
                        }
                        
                        foreach ($processed_classes as $index => $class_data): 
                            $class = $class_data['class'];
                            $schedule_type = $class['schedule_type'] ?? 'unknown';
                            
                            // Calculate width and left position for columns
                            $width = (100 / $class_data['max_columns']);
                            $left = ($class_data['column'] * $width);
                            ?>
                            <div class="class-block <?php echo $schedule_type; ?>" 
                                 style="top: <?php echo $class_data['top']; ?>px; 
                                        height: <?php echo max($class_data['height'], 70); ?>px;
                                        width: <?php echo $width; ?>%;
                                        left: <?php echo $left; ?>%;
                                        right: auto;"
                                 title="<?php echo htmlspecialchars($class['activity_display'] ?? 'Unknown'); ?>">
                                <div class="class-time">
                                    <?php echo date('g:i A', $class_data['start_time']); ?> - <?php echo date('g:i A', $class_data['end_time']); ?>
                                </div>
                                <div class="class-title">
                                    <?php echo htmlspecialchars($class['activity_display'] ?? 'Unknown'); ?>
                                </div>
                                
                                
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-classes">
                            <i class="fas fa-moon"></i>
                            <span>No classes</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Schedule Summary -->
    <?php
    $total_subjects = count(array_unique(array_column(array_filter($schedules, function($s) { return $s['schedule_type'] === 'subject'; }), 'subject_id')));
    $total_activities = count(array_filter($schedules, function($s) { return $s['schedule_type'] === 'activity'; }));
    $total_hours = 0;
    foreach ($schedules as $schedule) {
        if ($schedule['start_time'] && $schedule['end_time']) {
            $start = new DateTime($schedule['start_time']);
            $end = new DateTime($schedule['end_time']);
            $diff = $start->diff($end);
            $total_hours += $diff->h + ($diff->i / 60);
        }
    }
    ?>

    <div class="schedule-summary">
        <h3>Schedule Summary</h3>
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-icon subjects">
                    <i class="fas fa-book"></i>
                </div>
                <div class="summary-info">
                    <span class="summary-number"><?php echo $total_subjects; ?></span>
                    <span class="summary-label">Subjects</span>
                </div>
            </div>
            
            <div class="summary-card">
                <div class="summary-icon activities">
                    <i class="fas fa-star"></i>
                </div>
                <div class="summary-info">
                    <span class="summary-number"><?php echo $total_activities; ?></span>
                    <span class="summary-label">Activities</span>
                </div>
            </div>
            
            <div class="summary-card">
                <div class="summary-icon hours">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="summary-info">
                    <span class="summary-number"><?php echo number_format($total_hours, 1); ?></span>
                    <span class="summary-label">Hours/Week</span>
                </div>
            </div>
            
            <div class="summary-card">
                <div class="summary-icon days">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="summary-info">
                    <span class="summary-number"><?php echo count(array_unique(array_column($schedules, 'day_of_week'))); ?></span>
                    <span class="summary-label">Active Days</span>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>

    <!-- Action Buttons -->
    <div class="action-buttons">
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Back to Dashboard
        </a>
    </div>
</div>

<style>
:root {
    --primary-blue: #2e86ab;
    --dark-blue: #1a5f7a;
    --light-blue: #e8f4f8;
    --success-green: #27ae60;
    --warning-orange: #f39c12;
    --error-red: #e74c3c;
    --gray: #6c757d;
    --light-gray: #f8f9fa;
    --border-gray: #dee2e6;
    --white: #ffffff;
    --black: #2c3e50;
    --shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.schedule-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: 1rem;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* Header Styles */
.page-header {
    background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
    color: var(--white);
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow);
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.page-title {
    font-size: 2.2rem;
    font-weight: 700;
    margin: 0 0 0.5rem 0;
    display: flex;
    align-items: center;
    gap: 1rem;
    color: var(--white);
}

.page-title i {
    font-size: 2rem;
    color: var(--white);
}

.page-subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
    margin: 0;
    color: var(--white);
}

.schedule-overview {
    display: flex;
    gap: 2rem;
}

.overview-stat {
    text-align: center;
}

.stat-number {
    display: block;
    font-size: 2rem;
    font-weight: 700;
    line-height: 1;
    color: var(--white);
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.8;
    color: var(--white);
}

.student-info {
    display: flex;
    gap: 2rem;
    padding: 1rem 1.5rem;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    backdrop-filter: blur(10px);
}

.info-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 500;
    color: var(--white);
}

.info-item i {
    font-size: 0.9rem;
    opacity: 0.8;
    color: var(--white);
}

/* Alert Styles */
.alert {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    font-weight: 500;
}

.alert-error {
    background: rgba(231, 76, 60, 0.1);
    color: var(--error-red);
    border: 1px solid rgba(231, 76, 60, 0.2);
}

.alert-warning {
    background: rgba(243, 156, 18, 0.1);
    color: var(--warning-orange);
    border: 1px solid rgba(243, 156, 18, 0.2);
}

.alert-info {
    background: rgba(46, 134, 171, 0.1);
    color: var(--primary-blue);
    border: 1px solid rgba(46, 134, 171, 0.2);
}

/* Schedule Container */
.schedule-container {
    background: var(--white);
    border-radius: 16px;
    box-shadow: var(--shadow);
    overflow: hidden;
    margin-bottom: 2rem;
}

.schedule-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 2rem;
    background: var(--light-gray);
    border-bottom: 2px solid var(--border-gray);
}

.schedule-header h2 {
    margin: 0;
    color: var(--black);
    font-size: 1.5rem;
    font-weight: 600;
}

.schedule-controls {
    display: flex;
    align-items: center;
    gap: 2rem;
}

.legend {
    display: flex;
    gap: 1.5rem;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    color: var(--gray);
}

.color-box {
    width: 16px;
    height: 16px;
    border-radius: 4px;
}

.color-box.subject {
    background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
}

.color-box.activity {
    background: linear-gradient(135deg, #9b59b6, #8e44ad);
}

.print-btn {
    background: var(--primary-blue);
    color: var(--white);
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: background 0.3s ease;
}

.print-btn:hover {
    background: var(--dark-blue);
}

/* Weekly Grid */
.weekly-grid {
    display: grid;
    grid-template-columns: 100px repeat(5, 1fr);
    height: 880px; /* 11 hours * 80px */
    position: relative;
    gap: 0;
    border: 1px solid var(--border-gray);
}

.time-column {
    background: var(--light-gray);
    border-right: 2px solid var(--border-gray);
}

.time-header {
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: var(--black);
    background: var(--white);
    border-bottom: 1px solid var(--border-gray);
}

.time-slot {
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    color: var(--gray);
    border-bottom: 1px solid var(--border-gray);
    background: var(--light-gray);
}

.day-column {
    position: relative;
    border-right: 1px solid var(--border-gray);
}

.day-column:last-child {
    border-right: none;
}

.day-header {
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--primary-blue);
    color: var(--white);
    font-weight: 600;
    font-size: 1rem;
}

.day-content {
    position: relative;
    height: 800px; /* 10 hours * 80px (7 AM to 5 PM) */
    background: 
        repeating-linear-gradient(
            0deg,
            transparent,
            transparent 79px,
            var(--border-gray) 79px,
            var(--border-gray) 80px
        );
    overflow: visible;
}

.class-block {
    position: absolute;
    border-radius: 8px;
    padding: 6px 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    overflow: hidden;
    border-left: 4px solid;
    background: var(--white);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    cursor: pointer;
    min-height: 70px;
    z-index: 1;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    box-sizing: border-box;
    margin: 2px 2px;
}

.class-block:hover {
    transform: translateX(2px) scale(1.02);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
    z-index: 10;
    border-radius: 10px;
    overflow: visible;
}

.class-block.subject {
    border-left-color: var(--primary-blue);
    background: linear-gradient(135deg, #e3f2fd 0%, var(--white) 100%);
}

.class-block.activity {
    border-left-color: #9b59b6;
    background: linear-gradient(135deg, #f3e5f5 0%, var(--white) 100%);
}

.class-time {
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--gray);
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex-shrink: 0;
}

.class-title {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--black);
    line-height: 1.1;
    margin-bottom: 2px;
    word-wrap: break-word;
    overflow-wrap: break-word;
    hyphens: auto;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
    min-height: 0;
}

.class-teacher, .class-room {
    font-size: 0.6rem;
    color: var(--gray);
    display: flex;
    align-items: center;
    gap: 0.25rem;
    margin-bottom: 1px;
    word-wrap: break-word;
    overflow-wrap: break-word;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex-shrink: 0;
}

.class-teacher i, .class-room i {
    font-size: 0.65rem;
}

.no-classes {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    color: var(--gray);
    opacity: 0.6;
}

.no-classes i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    display: block;
}

.no-classes span {
    font-size: 0.9rem;
    font-style: italic;
}

/* Schedule Summary */
.schedule-summary {
    background: var(--white);
    border-radius: 16px;
    padding: 2rem;
    box-shadow: var(--shadow);
    margin-bottom: 2rem;
}

.schedule-summary h3 {
    margin: 0 0 1.5rem 0;
    color: var(--black);
    font-size: 1.4rem;
    font-weight: 600;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}

.summary-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    background: var(--light-gray);
    border-radius: 12px;
    transition: transform 0.2s ease;
}

.summary-card:hover {
    transform: translateY(-2px);
}

.summary-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
    font-size: 1.2rem;
}

.summary-icon.subjects {
    background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
}

.summary-icon.activities {
    background: linear-gradient(135deg, #9b59b6, #8e44ad);
}

.summary-icon.hours {
    background: linear-gradient(135deg, var(--success-green), #229954);
}

.summary-icon.days {
    background: linear-gradient(135deg, var(--warning-orange), #e67e22);
}

.summary-number {
    display: block;
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--black);
    line-height: 1;
}

.summary-label {
    font-size: 0.9rem;
    color: var(--gray);
    margin-top: 0.25rem;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    justify-content: center;
    gap: 1rem;
}

.btn {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 2rem;
    border-radius: 12px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.btn-secondary {
    background: var(--gray);
    color: var(--white);
}

.btn-secondary:hover {
    background: var(--black);
    transform: translateY(-2px);
}

/* Responsive Design */
@media (max-width: 1200px) {
    .weekly-grid {
        grid-template-columns: 80px repeat(5, 1fr);
    }
    
    .time-slot {
        font-size: 0.75rem;
        padding: 0 0.25rem;
    }
}

@media (max-width: 992px) {
    .header-content {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .student-info {
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .schedule-controls {
        flex-direction: column;
        gap: 1rem;
    }
    
    .weekly-grid {
        font-size: 0.85rem;
    }
}

@media (max-width: 768px) {
    .schedule-page {
        padding: 0.5rem;
    }
    
    .page-header {
        padding: 1.5rem;
    }
    
    .page-title {
        font-size: 1.8rem;
    }
    
    .weekly-grid {
        grid-template-columns: 60px repeat(5, 1fr);
        height: 640px;
        font-size: 0.75rem;
    }
    
    .day-content {
        height: 560px;
    }
    
    .time-slot, .day-header {
        height: 56px;
        font-size: 0.7rem;
    }
    
    .class-block {
        padding: 3px 5px;
        font-size: 0.65rem;
        min-height: 45px;
        overflow: hidden;
        margin: 1px 1px;
    }
    
    .class-block:hover {
        overflow: visible;
        transform: scale(1.05);
    }
    
    .class-time {
        font-size: 0.6rem;
        line-height: 1.0;
        margin-bottom: 1px;
    }
    
    .class-title {
        font-size: 0.65rem;
        line-height: 1.0;
        margin-bottom: 1px;
        -webkit-line-clamp: 1;
        max-height: 0.65rem;
    }
    
    .class-teacher, .class-room {
        font-size: 0.55rem;
        display: none; /* Hide on mobile for more space */
    }
    
    .summary-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .weekly-grid {
        grid-template-columns: 50px repeat(3, 1fr);
        overflow-x: auto;
    }
    
    .student-info {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .class-block {
        padding: 2px 4px;
        font-size: 0.6rem;
        min-height: 40px;
        margin: 1px 0.5px;
    }
    
    .class-time {
        font-size: 0.55rem;
        margin-bottom: 0;
    }
    
    .class-title {
        font-size: 0.6rem;
        line-height: 1.0;
        -webkit-line-clamp: 1;
        max-height: 0.6rem;
        margin-bottom: 0;
    }
    
    .summary-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
        align-items: center;
    }
    
    .btn {
        width: 100%;
        max-width: 300px;
        justify-content: center;
    }
}

/* Additional styles for different block heights */
.class-block[style*="height: 40px"], 
.class-block[style*="height: 50px"],
.class-block[style*="height: 60px"],
.class-block[style*="height: 70px"] {
    padding: 3px 5px;
}

.class-block[style*="height: 40px"] .class-title,
.class-block[style*="height: 50px"] .class-title,
.class-block[style*="height: 60px"] .class-title {
    font-size: 0.7rem;
    -webkit-line-clamp: 1;
    margin-bottom: 0;
}

.class-block[style*="height: 40px"] .class-time,
.class-block[style*="height: 50px"] .class-time,
.class-block[style*="height: 60px"] .class-time {
    font-size: 0.65rem;
    margin-bottom: 1px;
}

.class-block[style*="height: 40px"] .class-teacher,
.class-block[style*="height: 40px"] .class-room,
.class-block[style*="height: 50px"] .class-teacher,
.class-block[style*="height: 50px"] .class-room {
    display: none;
}

/* Ensure content doesn't overflow container */
.day-content {
    overflow: hidden;
    position: relative;
}

.class-block * {
    max-width: 100%;
    box-sizing: border-box;
}

/* Add subtle border for overlapping blocks */
.class-block[style*="width: 50%"],
.class-block[style*="width: 33.33"] {
    border-right: 1px solid rgba(0, 0, 0, 0.1);
}

.class-block[style*="left: 0%"] {
    border-radius: 8px 4px 4px 8px;
}

.class-block[style*="left: 50%"],
.class-block[style*="left: 33.33%"] {
    border-radius: 4px 8px 8px 4px;
}

.class-block[style*="left: 66.66%"] {
    border-radius: 4px 8px 8px 4px;
}

/* Print Styles */
@media print {
    .page-header,
    .action-buttons,
    .print-btn {
        display: none !important;
    }
    
    .schedule-container {
        box-shadow: none;
        border: 1px solid #000;
    }
    
    .weekly-grid {
        break-inside: avoid;
    }
    
    .class-block {
        break-inside: avoid;
        box-shadow: none;
        border: 1px solid #ccc;
    }
}
</style>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
