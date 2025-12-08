<?php
require_once '../includes/auth_check.php';

// Check if user is a teacher
if (!checkRole('teacher')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$page_title = 'My Classes - GTBA Portal';
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
    
    // Get specific section if requested
    $selected_section_id = isset($_GET['section_id']) ? (int)$_GET['section_id'] : null;
    
    // Get assigned sections with detailed information
    $query = "SELECT sec.*, gl.grade_name, sy.year_label, st.is_primary, st.assigned_date,
                     COUNT(s.id) as student_count,
                     sec.current_enrollment, sec.room_number
              FROM section_teachers st
              LEFT JOIN sections sec ON st.section_id = sec.id
              LEFT JOIN grade_levels gl ON sec.grade_level_id = gl.id
              LEFT JOIN school_years sy ON sec.school_year_id = sy.id
              LEFT JOIN students s ON sec.id = s.current_section_id
              WHERE st.teacher_id = :teacher_id AND st.is_active = 1
              GROUP BY sec.id, st.is_primary, st.assigned_date
              ORDER BY st.is_primary DESC, gl.grade_order, sec.section_name";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':teacher_id', $_SESSION['user_id']);
    $stmt->execute();
    
    $assigned_sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get students for selected section or first section
    $students = [];
    $section_subjects = [];
    $selected_section = null;
    
    if ($selected_section_id) {
        // Verify teacher has access to this section
        $selected_section = array_filter($assigned_sections, function($section) use ($selected_section_id) {
            return $section['id'] == $selected_section_id;
        });
        $selected_section = reset($selected_section);
    } elseif (!empty($assigned_sections)) {
        $selected_section = $assigned_sections[0];
        $selected_section_id = $selected_section['id'];
    }
    
    if ($selected_section) {
        // Get students in the selected section
        $query = "SELECT s.*, u.email
                  FROM students s
                  LEFT JOIN users u ON s.user_id = u.id
                  WHERE s.current_section_id = :section_id 
                  ORDER BY s.last_name, s.first_name";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':section_id', $selected_section_id);
        $stmt->execute();
        
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
 // Get subjects for this section
        $query = "SELECT DISTINCT s.id as subject_id, s.subject_name, s.subject_code, 
                         t.first_name, t.last_name, cs.teacher_id
                  FROM class_schedules cs
                  LEFT JOIN subjects s ON cs.subject_id = s.id
                  LEFT JOIN teachers t ON cs.teacher_id = t.user_id
                  WHERE cs.section_id = :section_id AND cs.is_active = 1 AND s.is_active = 1
                  ORDER BY s.subject_name";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':section_id', $selected_section_id);
        $stmt->execute();
        
        $section_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        

    }
    
} catch (Exception $e) {
    $error_message = "Unable to load sections data: " . $e->getMessage();
    error_log("Teacher sections error: " . $e->getMessage());
}



ob_start();
?>

<div class="page-header">
    <h1 class="page-title">My Classes</h1>
    <p class="page-subtitle">Manage your assigned sections and view student information</p>
</div>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <?php echo $error_message; ?>
    </div>
<?php else: ?>

<!-- Sections Overview -->
<div class="sections-overview-card">
    <h2 class="card-title">Your Assigned Sections</h2>
    
    <?php if (empty($assigned_sections)): ?>
        <div class="empty-state">
            <div class="empty-icon">📚</div>
            <h3>No sections assigned</h3>
            <p>You don't have any sections assigned yet. Please contact the principal's office.</p>
        </div>
    <?php else: ?>
        <div class="sections-grid">
            <?php foreach ($assigned_sections as $section): ?>
                <div class="section-card <?php echo $section['is_primary'] ? 'primary-section' : ''; ?> <?php echo ($selected_section && $section['id'] == $selected_section['id']) ? 'selected' : ''; ?>">
                    <div class="section-header">
                        <h3 class="section-name"><?php echo htmlspecialchars($section['section_name']); ?></h3>
                        <?php if ($section['is_primary']): ?>
                            <span class="adviser-badge">Class Adviser</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="section-details">
                        <p class="grade-level"><?php echo htmlspecialchars($section['grade_name']); ?></p>
                        <p class="school-year"><?php echo htmlspecialchars($section['year_label']); ?></p>
                        <p class="student-count">
                            <?php echo $section['student_count']; ?> student<?php echo $section['student_count'] != 1 ? 's' : ''; ?> enrolled
                        </p>
                        <?php if ($section['room_number']): ?>
                            <p class="room-info">Room: <?php echo htmlspecialchars($section['room_number']); ?></p>
                        <?php endif; ?>
                        <p class="assigned-date">Assigned: <?php echo date('M j, Y', strtotime($section['assigned_date'])); ?></p>
                    </div>
                    
                    <div class="section-actions">
                        <a href="?section_id=<?php echo $section['id']; ?>" class="btn btn-primary btn-sm">
                            <?php echo ($selected_section && $section['id'] == $selected_section['id']) ? 'Currently Viewing' : 'View Details'; ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Section Details -->
<?php if ($selected_section): ?>
<div class="section-details-card">
    <div class="card-header">
        <h2 class="card-title">
            <?php echo htmlspecialchars($selected_section['section_name']); ?> - 
            <?php echo htmlspecialchars($selected_section['grade_name']); ?>
        </h2>
        <div class="section-meta">
            <span class="meta-item">
                <i class="fas fa-users"></i>
                <?php echo count($students); ?> Students
            </span>
            <?php if ($selected_section['room_number']): ?>
                <span class="meta-item">
                    <i class="fas fa-door-open"></i>
                    Room <?php echo htmlspecialchars($selected_section['room_number']); ?>
                </span>
            <?php endif; ?>
            <span class="meta-item">
                <i class="fas fa-calendar"></i>
                <?php echo htmlspecialchars($selected_section['year_label']); ?>
            </span>
        </div>
    </div>
    
    <!-- Section Navigation Tabs -->
    <div class="tab-navigation">
        <button class="tab-btn active" data-tab="students">Students</button>
        <button class="tab-btn" data-tab="subjects">Subjects</button>
    </div>
    
    <!-- Students Tab -->
    <div class="tab-content active" id="students-tab">
        <?php if (empty($students)): ?>
            <div class="empty-state">
                <div class="empty-icon">👥</div>
                <h3>No students enrolled</h3>
                <p>This section doesn't have any students enrolled yet.</p>
            </div>
        <?php else: ?>
            <div class="students-table-container">
                <table class="students-table">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>LRN</th>
                            <th>Student Type</th>
                            <th>Contact</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td class="student-id"><?php echo htmlspecialchars($student['student_id']); ?></td>
                                <td class="student-name">
                                    <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                    <?php if ($student['middle_name']): ?>
                                        <br><small><?php echo htmlspecialchars($student['middle_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($student['lrn'] ?? ''); ?></td>
                                <td>
                                    <span class="student-type-badge <?php echo strtolower($student['student_type']); ?>">
                                        <?php echo htmlspecialchars($student['student_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($student['email']): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($student['email']); ?>">
                                            <?php echo htmlspecialchars($student['email']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">No email</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="grades.php?student_id=<?php echo $student['id']; ?>" class="btn btn-outline btn-xs">View Grades</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Subjects Tab -->
    <div class="tab-content" id="subjects-tab">
        <?php if (empty($section_subjects)): ?>
            <div class="empty-state">
                <div class="empty-icon">📖</div>
                <h3>No subjects assigned</h3>
                <p>This section doesn't have any subjects assigned yet.</p>
            </div>
        <?php else: ?>
            <div class="subjects-grid">
                <?php foreach ($section_subjects as $subject): ?>
                    <div class="subject-card <?php echo $subject['teacher_id'] == $_SESSION['user_id'] ? 'my-subject' : ''; ?>">
                        <div class="subject-header">
                            <h4 class="subject-name"><?php echo htmlspecialchars($subject['subject_name']); ?></h4>
                            <span class="subject-code"><?php echo htmlspecialchars($subject['subject_code']); ?></span>
                        </div>
                        <div class="subject-teacher">
                            <?php if ($subject['teacher_id'] == $_SESSION['user_id']): ?>
                                <span class="my-subject-indicator">You teach this subject</span>
                            <?php else: ?>
                                Teacher: <?php echo htmlspecialchars($subject['first_name'] . ' ' . $subject['last_name']); ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($subject['teacher_id'] == $_SESSION['user_id']): ?>
                            <div class="subject-actions">
                                <a href="grades.php?section_id=<?php echo $selected_section_id; ?>&subject_id=<?php echo $subject['subject_id']; ?>" class="btn btn-primary btn-sm">Manage Grades</a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<style>
/* Teacher Sections Styles */
.page-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    border-radius: 15px;
    margin-bottom: 2rem;
    text-align: center;
}

.page-title {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
    font-weight: 700;
}

.page-subtitle {
    font-size: 1.2rem;
    opacity: 0.9;
    margin: 0;
}

.sections-overview-card, .section-details-card {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.card-title {
    font-size: 1.5rem;
    color: #1f2937;
    margin-bottom: 1.5rem;
    font-weight: 600;
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 0.5rem;
}

.sections-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1rem;
}

.section-card {
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 1.5rem;
    transition: all 0.2s ease;
    cursor: pointer;
}

.section-card:hover {
    border-color: #3b82f6;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
}

.section-card.primary-section {
    border-color: #10b981;
    background: linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 100%);
}

.section-card.selected {
    border-color: #3b82f6;
    background: linear-gradient(135deg, #dbeafe 0%, #eff6ff 100%);
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

.section-details p {
    margin: 0.25rem 0;
    color: #6b7280;
}

.grade-level {
    font-weight: 600;
    color: #1f2937 !important;
    font-size: 1.1rem;
}

.section-actions {
    margin-top: 1rem;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.section-meta {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #6b7280;
    font-size: 0.9rem;
}

.tab-navigation {
    display: flex;
    border-bottom: 2px solid #e5e7eb;
    margin-bottom: 2rem;
    gap: 0.5rem;
}

.tab-btn {
    padding: 0.75rem 1.5rem;
    background: none;
    border: none;
    color: #6b7280;
    font-weight: 500;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.2s ease;
}

.tab-btn.active, .tab-btn:hover {
    color: #3b82f6;
    border-bottom-color: #3b82f6;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.students-table-container {
    overflow-x: auto;
}

.students-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.students-table th,
.students-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.students-table th {
    background: #f9fafb;
    font-weight: 600;
    color: #1f2937;
}

.students-table tbody tr:hover {
    background: #f9fafb;
}

.student-type-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
}

.student-type-badge.continuing {
    background: #dbeafe;
    color: #1e40af;
}

.student-type-badge.transfer {
    background: #fef3c7;
    color: #92400e;
}

.student-type-badge.new {
    background: #dcfce7;
    color: #166534;
}



.subjects-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.subject-card {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 1.5rem;
    background: white;
}

.subject-card.my-subject {
    border-color: #10b981;
    background: #f0fdf4;
}

.subject-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.subject-name {
    font-size: 1.1rem;
    color: #1f2937;
    margin: 0;
    font-weight: 600;
}

.subject-code {
    background: #3b82f6;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
}

.subject-teacher {
    color: #6b7280;
    font-size: 0.9rem;
    margin-bottom: 1rem;
}

.my-subject-indicator {
    color: #10b981;
    font-weight: 500;
}

.subject-actions {
    margin-top: 1rem;
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

.btn-xs {
    padding: 0.25rem 0.75rem;
    font-size: 0.75rem;
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

.text-muted {
    color: #6b7280;
}

/* Responsive Design */
@media (max-width: 768px) {
    .page-header {
        padding: 1.5rem;
    }
    
    .page-title {
        font-size: 2rem;
    }
    
    .sections-grid {
        grid-template-columns: 1fr;
    }
    

    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .tab-navigation {
        flex-wrap: wrap;
    }
    
    .students-table {
        font-size: 0.875rem;
    }
    
    .students-table th,
    .students-table td {
        padding: 0.75rem 0.5rem;
    }
}
</style>

<script>
// Tab functionality
document.addEventListener('DOMContentLoaded', function() {
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            
            // Remove active class from all tabs and contents
            tabBtns.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            
            // Add active class to clicked tab and corresponding content
            this.classList.add('active');
            document.getElementById(targetTab + '-tab').classList.add('active');
        });
    });
});
</script>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
