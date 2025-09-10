<?php
require_once '../includes/auth_check.php';

// Check if user is a registrar
if (!checkRole('registrar')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$page_title = 'Student Grade History - GTBA Portal';
$base_url = '../';

try {
    $database = new Database();
    $db = $database->connect();
    
    // Get search parameters
    $search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
    $selected_student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
    $selected_school_year = isset($_GET['school_year']) ? (int)$_GET['school_year'] : null;
    
    // Get all school years for filter
    $years_query = "SELECT * FROM school_years ORDER BY start_date DESC";
    $years_stmt = $db->prepare($years_query);
    $years_stmt->execute();
    $school_years = $years_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get students based on search
    $students = [];
    if ($search_query) {
        $student_search_query = "SELECT s.id, s.student_id as student_number, s.first_name, s.last_name, s.middle_name,
                                        gl.grade_name, sec.section_name, s.enrollment_status
                                 FROM students s
                                 LEFT JOIN grade_levels gl ON s.current_grade_level_id = gl.id
                                 LEFT JOIN sections sec ON s.current_section_id = sec.id
                                 WHERE (s.first_name LIKE :search OR s.last_name LIKE :search 
                                        OR s.middle_name LIKE :search OR s.student_id LIKE :search
                                        OR CONCAT(s.first_name, ' ', s.last_name) LIKE :search)
                                 ORDER BY s.last_name, s.first_name
                                 LIMIT 50";
        
        $search_param = "%$search_query%";
        $student_stmt = $db->prepare($student_search_query);
        $student_stmt->bindParam(':search', $search_param);
        $student_stmt->execute();
        $students = $student_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get selected student details and grade history
    $student_info = null;
    $grade_history = [];
    $grade_summary_by_year = [];
    
    if ($selected_student_id) {
        // Get student information
        $student_query = "SELECT s.*, gl.grade_name as current_grade, sec.section_name as current_section,
                                 sy.year_label as current_year
                          FROM students s
                          LEFT JOIN grade_levels gl ON s.current_grade_level_id = gl.id
                          LEFT JOIN sections sec ON s.current_section_id = sec.id
                          LEFT JOIN school_years sy ON s.current_school_year_id = sy.id
                          WHERE s.id = :student_id";
        
        $student_stmt = $db->prepare($student_query);
        $student_stmt->bindParam(':student_id', $selected_student_id);
        $student_stmt->execute();
        $student_info = $student_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($student_info) {
            // Get grade history - improved query to handle missing curriculum entries
            $history_query = "SELECT sg.*, sub.subject_code, sub.subject_name, sy.year_label,
                                     COALESCE(gl.grade_name, 
                                              CASE 
                                                  WHEN sub.subject_code LIKE 'N-%' THEN 'Nursery'
                                                  WHEN sub.subject_code LIKE 'K-%' THEN 'Kindergarten'
                                                  WHEN sub.subject_code LIKE 'G1-%' THEN 'Grade 1'
                                                  WHEN sub.subject_code LIKE 'G2-%' THEN 'Grade 2'
                                                  WHEN sub.subject_code LIKE 'G3-%' THEN 'Grade 3'
                                                  WHEN sub.subject_code LIKE 'G4-%' THEN 'Grade 4'
                                                  WHEN sub.subject_code LIKE 'G5-%' THEN 'Grade 5'
                                                  WHEN sub.subject_code LIKE 'G6-%' THEN 'Grade 6'
                                                  WHEN sub.subject_code LIKE 'G7-%' THEN 'Grade 7'
                                                  WHEN sub.subject_code LIKE 'G8-%' THEN 'Grade 8'
                                                  WHEN sub.subject_code LIKE 'G9-%' THEN 'Grade 9'
                                                  WHEN sub.subject_code LIKE 'G10-%' THEN 'Grade 10'
                                                  ELSE 'Unknown Grade'
                                              END
                                     ) as grade_name, 
                                     CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
                                     COALESCE(c.is_required, 1) as is_required
                              FROM student_grades sg
                              JOIN subjects sub ON sg.subject_id = sub.id
                              JOIN school_years sy ON sg.school_year_id = sy.id
                              LEFT JOIN teachers t ON sg.teacher_id = t.user_id
                              LEFT JOIN curriculum c ON c.subject_id = sub.id AND c.school_year_id = sy.id
                              LEFT JOIN grade_levels gl ON c.grade_level_id = gl.id
                              WHERE sg.student_id = :student_id";
            
            if ($selected_school_year) {
                $history_query .= " AND sg.school_year_id = :school_year";
            }
            
            $history_query .= " ORDER BY sy.start_date DESC, sub.subject_name";
            
            $history_stmt = $db->prepare($history_query);
            $history_stmt->bindParam(':student_id', $selected_student_id);
            if ($selected_school_year) {
                $history_stmt->bindParam(':school_year', $selected_school_year);
            }
            $history_stmt->execute();
            $grade_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate summary by school year
            foreach ($grade_history as $grade) {
                $year_id = $grade['school_year_id'];
                $year_label = $grade['year_label'];
                
                if (!isset($grade_summary_by_year[$year_id])) {
                    $grade_summary_by_year[$year_id] = [
                        'year_label' => $year_label,
                        'grade_name' => $grade['grade_name'],
                        'total_subjects' => 0,
                        'graded_subjects' => 0,
                        'passed_subjects' => 0,
                        'failed_subjects' => 0,
                        'total_grade_points' => 0,
                        'gwa' => 0
                    ];
                }
                
                $grade_summary_by_year[$year_id]['total_subjects']++;
                
                if ($grade['final_grade'] !== null) {
                    $grade_summary_by_year[$year_id]['graded_subjects']++;
                    $grade_summary_by_year[$year_id]['total_grade_points'] += $grade['final_grade'];
                    
                    if ($grade['remarks'] === 'Passed') {
                        $grade_summary_by_year[$year_id]['passed_subjects']++;
                    } else {
                        $grade_summary_by_year[$year_id]['failed_subjects']++;
                    }
                }
            }
            
            // Calculate GWA for each year
            foreach ($grade_summary_by_year as $year_id => &$summary) {
                if ($summary['graded_subjects'] > 0) {
                    $summary['gwa'] = round($summary['total_grade_points'] / $summary['graded_subjects'], 2);
                }
            }
        }
    }
    
} catch (Exception $e) {
    $error_message = "Unable to load grade history: " . $e->getMessage();
    error_log("Registrar grade history error: " . $e->getMessage());
}

ob_start();
?>

<div class="welcome-section">
    <h1 class="welcome-title">📚 Student Grade History</h1>
    <p class="welcome-subtitle">View and manage student academic records and grade history</p>
</div>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<!-- Search Section -->
<div class="search-section">
    <div class="search-card">
        <h3>🔍 Search Students</h3>
        <form method="GET" class="search-form">
            <div class="search-input-group">
                <input type="text" 
                       name="search" 
                       class="search-input" 
                       placeholder="Search by student name or ID..." 
                       value="<?php echo htmlspecialchars($search_query); ?>"
                       required>
                <button type="submit" class="search-btn">Search</button>
            </div>
            <?php if ($selected_student_id): ?>
                <input type="hidden" name="student_id" value="<?php echo $selected_student_id; ?>">
            <?php endif; ?>
            <?php if ($selected_school_year): ?>
                <input type="hidden" name="school_year" value="<?php echo $selected_school_year; ?>">
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Student Search Results -->
<?php if ($search_query && !empty($students)): ?>
<div class="students-section">
    <h3>👥 Search Results (<?php echo count($students); ?> found)</h3>
    <div class="students-grid">
        <?php foreach ($students as $student): ?>
            <div class="student-card <?php echo $selected_student_id == $student['id'] ? 'selected' : ''; ?>">
                <div class="student-info">
                    <div class="student-name">
                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                    </div>
                    <div class="student-details">
                        <span class="student-id">ID: <?php echo htmlspecialchars($student['student_number']); ?></span>
                        <?php if ($student['grade_name']): ?>
                            <span class="grade"><?php echo htmlspecialchars($student['grade_name']); ?></span>
                        <?php endif; ?>
                        <?php if ($student['section_name']): ?>
                            <span class="section"><?php echo htmlspecialchars($student['section_name']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="enrollment-status">
                        <span class="status-badge status-<?php echo strtolower($student['enrollment_status']); ?>">
                            <?php echo htmlspecialchars($student['enrollment_status']); ?>
                        </span>
                    </div>
                </div>
                <div class="student-actions">
                    <a href="?search=<?php echo urlencode($search_query); ?>&student_id=<?php echo $student['id']; ?>" 
                       class="btn btn-sm btn-primary">View Grades</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php elseif ($search_query && empty($students)): ?>
<div class="no-results">
    <h3>😔 No Students Found</h3>
    <p>No students match your search criteria. Please try a different search term.</p>
</div>
<?php endif; ?>

<!-- Selected Student Grade History -->
<?php if ($student_info): ?>
<div class="student-profile-section">
    <div class="student-profile-card">
        <div class="profile-header">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($student_info['first_name'], 0, 1) . substr($student_info['last_name'], 0, 1)); ?>
            </div>
            <div class="profile-info">
                <h2 class="profile-name">
                    <?php echo htmlspecialchars($student_info['first_name'] . ' ' . $student_info['middle_name'] . ' ' . $student_info['last_name']); ?>
                </h2>
                <div class="profile-details">
                    <span class="detail-item">
                        <strong>Student ID:</strong> <?php echo htmlspecialchars($student_info['student_id']); ?>
                    </span>
                    <?php if ($student_info['lrn']): ?>
                        <span class="detail-item">
                            <strong>LRN:</strong> <?php echo htmlspecialchars($student_info['lrn']); ?>
                        </span>
                    <?php endif; ?>
                    <span class="detail-item">
                        <strong>Current Grade:</strong> <?php echo htmlspecialchars($student_info['current_grade'] ?? 'Not assigned'); ?>
                    </span>
                    <span class="detail-item">
                        <strong>Section:</strong> <?php echo htmlspecialchars($student_info['current_section'] ?? 'Not assigned'); ?>
                    </span>
                    <span class="detail-item">
                        <strong>Status:</strong> 
                        <span class="status-badge status-<?php echo strtolower($student_info['enrollment_status']); ?>">
                            <?php echo htmlspecialchars($student_info['enrollment_status']); ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- School Year Filter -->
        <div class="year-filter">
            <form method="GET" class="filter-form">
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                <input type="hidden" name="student_id" value="<?php echo $selected_student_id; ?>">
                <div class="filter-group">
                    <label for="school_year">Filter by School Year:</label>
                    <select name="school_year" id="school_year" onchange="this.form.submit()">
                        <option value="">All Years</option>
                        <?php foreach ($school_years as $year): ?>
                            <option value="<?php echo $year['id']; ?>" 
                                    <?php echo $selected_school_year == $year['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($year['year_label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Grade Summary by Year -->
<?php if (!empty($grade_summary_by_year)): ?>
<div class="grade-summary-section">
    <h3>📊 Academic Summary by School Year</h3>
    <div class="summary-cards">
        <?php foreach ($grade_summary_by_year as $summary): ?>
            <div class="summary-card">
                <div class="summary-header">
                    <h4><?php echo htmlspecialchars($summary['year_label']); ?></h4>
                    <?php if ($summary['grade_name']): ?>
                        <span class="grade-badge"><?php echo htmlspecialchars($summary['grade_name']); ?></span>
                    <?php endif; ?>
                </div>
                <div class="summary-stats">
                    <div class="stat-item">
                        <span class="stat-label">Total Subjects:</span>
                        <span class="stat-value"><?php echo $summary['total_subjects']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Graded:</span>
                        <span class="stat-value"><?php echo $summary['graded_subjects']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Passed:</span>
                        <span class="stat-value success"><?php echo $summary['passed_subjects']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Failed:</span>
                        <span class="stat-value danger"><?php echo $summary['failed_subjects']; ?></span>
                    </div>
                    <div class="stat-item gwa">
                        <span class="stat-label">GWA:</span>
                        <span class="stat-value <?php echo $summary['gwa'] >= 75 ? 'success' : 'danger'; ?>">
                            <?php echo number_format($summary['gwa'], 2); ?>
                        </span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Detailed Grade History -->
<?php if (!empty($grade_history)): ?>
<div class="grade-history-section">
    <div class="section-header">
        <h3>📋 Detailed Grade History</h3>
        <div class="grade-count">
            <?php echo count($grade_history); ?> subject<?php echo count($grade_history) != 1 ? 's' : ''; ?> found
        </div>
    </div>
    
    <div class="grades-table-container">
        <table class="grades-table">
            <thead>
                <tr>
                    <th>School Year</th>
                    <th>Grade Level</th>
                    <th>Subject Code</th>
                    <th>Subject Name</th>
                    <th>Required</th>
                    <th>Teacher</th>
                    <th>Final Grade</th>
                    <th>Remarks</th>
                    <th>Date Recorded</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $current_year = '';
                foreach ($grade_history as $grade): 
                    $year_changed = $current_year !== $grade['year_label'];
                    $current_year = $grade['year_label'];
                ?>
                    <tr class="<?php echo $year_changed ? 'year-separator' : ''; ?>">
                        <td class="year-cell <?php echo $year_changed ? 'year-header' : ''; ?>">
                            <?php if ($year_changed): ?>
                                <?php echo htmlspecialchars($grade['year_label']); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($grade['grade_name'] ?? 'N/A'); ?></td>
                        <td class="subject-code"><?php echo htmlspecialchars($grade['subject_code']); ?></td>
                        <td class="subject-name"><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                        <td class="required-cell">
                            <?php if ($grade['is_required']): ?>
                                <span class="required-badge">Required</span>
                            <?php else: ?>
                                <span class="elective-badge">Elective</span>
                            <?php endif; ?>
                        </td>
                        <td class="teacher-name">
                            <?php echo $grade['teacher_name'] ? htmlspecialchars($grade['teacher_name']) : '<span class="no-data">Not assigned</span>'; ?>
                        </td>
                        <td class="grade-cell">
                            <?php if ($grade['final_grade'] !== null): ?>
                                <?php 
                                $grade_value = floatval($grade['final_grade']);
                                $grade_class = $grade_value >= 75 ? 'passing' : 'failing';
                                ?>
                                <span class="grade-value <?php echo $grade_class; ?>">
                                    <?php echo number_format($grade_value, 2); ?>
                                </span>
                            <?php else: ?>
                                <span class="no-grade">Not graded</span>
                            <?php endif; ?>
                        </td>
                        <td class="remarks-cell">
                            <?php if ($grade['remarks']): ?>
                                <?php 
                                $remarks_class = in_array($grade['remarks'], ['Passed']) ? 'passed' : 'failed';
                                ?>
                                <span class="remarks-badge <?php echo $remarks_class; ?>">
                                    <?php echo htmlspecialchars($grade['remarks']); ?>
                                </span>
                            <?php else: ?>
                                <span class="no-data">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="date-cell">
                            <?php echo date('M j, Y', strtotime($grade['date_recorded'])); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php elseif ($selected_student_id): ?>
<div class="no-grades">
    <h3>📝 No Grade History Found</h3>
    <p>This student doesn't have any recorded grades yet.</p>
</div>
<?php endif; ?>
<?php endif; ?>

<style>
/* Search Section */
.search-section {
    margin-bottom: 2rem;
}

.search-card {
    background: var(--white);
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.search-card h3 {
    color: var(--dark-blue);
    margin-bottom: 1.5rem;
    font-weight: 600;
}

.search-input-group {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.search-input {
    flex: 1;
    padding: 0.75rem 1rem;
    border: 2px solid var(--border-gray);
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

.search-input:focus {
    outline: none;
    border-color: var(--primary-blue);
}

.search-btn {
    background: var(--primary-blue);
    color: var(--white);
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.3s ease;
}

.search-btn:hover {
    background: var(--dark-blue);
}

/* Students Section */
.students-section {
    margin-bottom: 2rem;
}

.students-section h3 {
    color: var(--dark-blue);
    margin-bottom: 1.5rem;
    font-weight: 600;
}

.students-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1rem;
}

.student-card {
    background: var(--white);
    border: 2px solid var(--border-gray);
    border-radius: 10px;
    padding: 1.5rem;
    transition: all 0.3s ease;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.student-card:hover {
    border-color: var(--primary-blue);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.student-card.selected {
    border-color: var(--primary-blue);
    background: var(--light-blue);
}

.student-info {
    flex: 1;
}

.student-name {
    font-weight: 600;
    color: var(--dark-blue);
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
}

.student-details {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
    color: var(--gray);
}

.student-id, .grade, .section {
    background: var(--light-gray);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
}

.student-actions {
    margin-left: 1rem;
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
}

/* Student Profile Section */
.student-profile-section {
    margin-bottom: 2rem;
}

.student-profile-card {
    background: var(--white);
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.profile-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.profile-avatar {
    width: 80px;
    height: 80px;
    background: var(--primary-blue);
    color: var(--white);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    font-weight: bold;
}

.profile-info {
    flex: 1;
}

.profile-name {
    color: var(--dark-blue);
    margin-bottom: 1rem;
    font-size: 1.8rem;
    font-weight: 700;
}

.profile-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.75rem;
}

.detail-item {
    color: var(--text);
    font-size: 0.95rem;
}

.detail-item strong {
    color: var(--dark-blue);
}

/* Year Filter */
.year-filter {
    border-top: 1px solid var(--border-gray);
    padding-top: 1.5rem;
}

.filter-form {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-group label {
    font-weight: 600;
    color: var(--dark-blue);
}

.filter-group select {
    padding: 0.5rem;
    border: 1px solid var(--border-gray);
    border-radius: 5px;
    background: var(--white);
}

/* Grade Summary Section */
.grade-summary-section {
    margin-bottom: 2rem;
}

.grade-summary-section h3 {
    color: var(--dark-blue);
    margin-bottom: 1.5rem;
    font-weight: 600;
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.summary-card {
    background: var(--white);
    border: 1px solid var(--border-gray);
    border-radius: 10px;
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.summary-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--border-gray);
}

.summary-header h4 {
    color: var(--dark-blue);
    margin: 0;
    font-weight: 600;
}

.grade-badge {
    background: var(--primary-blue);
    color: var(--white);
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 600;
}

.summary-stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem;
    background: var(--light-gray);
    border-radius: 5px;
}

.stat-item.gwa {
    grid-column: 1 / -1;
    background: var(--light-blue);
    border: 1px solid var(--primary-blue);
}

.stat-label {
    font-weight: 500;
    color: var(--black);
}

.stat-value {
    font-weight: 600;
    color: var(--dark-blue);
}

.stat-value.success {
    color: var(--success);
}

.stat-value.danger {
    color: var(--danger);
}

/* Grade History Section */
.grade-history-section {
    background: var(--white);
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.section-header h3 {
    color: var(--dark-blue);
    margin: 0;
    font-weight: 600;
}

.grade-count {
    color: var(--gray);
    font-size: 0.9rem;
}

.grades-table-container {
    overflow-x: auto;
}

.grades-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.grades-table th {
    background: var(--light-blue);
    color: var(--dark-blue);
    padding: 1rem 0.75rem;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid var(--primary-blue);
    white-space: nowrap;
}

.grades-table td {
    padding: 0.75rem;
    border-bottom: 1px solid var(--border-gray);
    vertical-align: middle;
}

.grades-table tr.year-separator {
    border-top: 2px solid var(--primary-blue);
}

.year-cell.year-header {
    background: var(--light-blue);
    font-weight: 600;
    color: var(--dark-blue);
}

.subject-code {
    font-family: monospace;
    font-weight: 600;
    color: var(--dark-blue);
}

.subject-name {
    font-weight: 500;
    color: var(--black);
}

.required-badge, .elective-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.required-badge {
    background: var(--danger-light);
    color: var(--danger);
}

.elective-badge {
    background: var(--light-gray);
    color: var(--gray);
}

.grade-value {
    font-weight: 600;
    font-size: 1.1rem;
}

.grade-value.passing {
    color: var(--success);
}

.grade-value.failing {
    color: var(--danger);
}

.no-grade {
    color: var(--gray);
    font-style: italic;
}

.remarks-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
}

.remarks-badge.passed {
    background: var(--success-light);
    color: var(--success);
}

.remarks-badge.failed {
    background: var(--danger-light);
    color: var(--danger);
}

.date-cell {
    color: var(--gray);
    font-size: 0.9rem;
}

.no-data {
    color: var(--gray);
    font-style: italic;
}

/* Status Badges */
.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-enrolled {
    background: #e8f5e8;
    color: #2e7d32;
}

.status-pending {
    background: #fff3e0;
    color: #ef6c00;
}

.status-transferred {
    background: #e3f2fd;
    color: #1565c0;
}

.status-graduated {
    background: #f3e5f5;
    color: #7b1fa2;
}

/* No Results */
.no-results, .no-grades {
    text-align: center;
    padding: 3rem 2rem;
    background: var(--white);
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.no-results h3, .no-grades h3 {
    color: var(--dark-blue);
    margin-bottom: 1rem;
}

.no-results p, .no-grades p {
    color: var(--gray);
}

/* Responsive Design */
@media (max-width: 768px) {
    .search-input-group {
        flex-direction: column;
    }
    
    .students-grid {
        grid-template-columns: 1fr;
    }
    
    .student-card {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .profile-header {
        flex-direction: column;
        text-align: center;
    }
    
    .profile-details {
        grid-template-columns: 1fr;
    }
    
    .summary-cards {
        grid-template-columns: 1fr;
    }
    
    .summary-stats {
        grid-template-columns: 1fr;
    }
    
    .grades-table-container {
        font-size: 0.85rem;
    }
    
    .grades-table th,
    .grades-table td {
        padding: 0.5rem 0.25rem;
    }
}
</style>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
