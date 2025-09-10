<?php
require_once '../includes/auth_check.php';

// Check if user is a teacher
if (!checkRole('teacher')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$page_title = 'Manage Grades - GTBA Portal';
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
    
    // Get sections where this teacher is assigned (current school year only)
    $query = "SELECT sec.id, sec.section_name, gl.grade_name, sy.year_label,
                     MAX(st.is_primary) as is_primary, gl.id as grade_level_id, gl.grade_order
              FROM section_teachers st
              LEFT JOIN sections sec ON st.section_id = sec.id
              LEFT JOIN grade_levels gl ON sec.grade_level_id = gl.id
              LEFT JOIN school_years sy ON sec.school_year_id = sy.id
              WHERE st.teacher_id = :teacher_id AND st.is_active = 1 AND sy.is_active = 1
              GROUP BY sec.id, gl.grade_order
              ORDER BY gl.grade_order, sec.section_name";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':teacher_id', $_SESSION['user_id']);
    $stmt->execute();
    
    $teacher_sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all subjects available for teacher's assigned grade levels (current school year only)
    $query = "SELECT DISTINCT s.id, s.subject_code, s.subject_name, c.grade_level_id,
                     gl.grade_name, gl.grade_order
              FROM section_teachers st
              LEFT JOIN sections sec ON st.section_id = sec.id
              LEFT JOIN grade_levels gl ON sec.grade_level_id = gl.id
              LEFT JOIN school_years sy ON sec.school_year_id = sy.id
              LEFT JOIN curriculum c ON gl.id = c.grade_level_id AND c.school_year_id = sy.id
              LEFT JOIN subjects s ON c.subject_id = s.id
              WHERE st.teacher_id = :teacher_id AND st.is_active = 1 AND sy.is_active = 1 AND s.is_active = 1
              ORDER BY gl.grade_order, s.subject_name";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':teacher_id', $_SESSION['user_id']);
    $stmt->execute();
    
    $teacher_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Handle form submissions for grade entry/updates
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'save_grades') {
            $section_id = (int)$_POST['section_id'];
            $subject_id = (int)$_POST['subject_id'];
            $grades = $_POST['grades'] ?? [];
            
            try {
                $db->beginTransaction();
                
                foreach ($grades as $student_id => $grade_data) {
                    $student_id = (int)$student_id;
                    $final_grade = $grade_data['final_grade'] ?? null;
                    $remarks = $grade_data['remarks'] ?? '';
                    
                    if ($final_grade !== null && $final_grade !== '') {
                        // Check if grade record exists (removed teacher_id filter)
                        $check_query = "SELECT id, teacher_id FROM student_grades 
                                       WHERE student_id = ? AND subject_id = ? AND school_year_id = ?";
                        $check_stmt = $db->prepare($check_query);
                        $check_stmt->execute([$student_id, $subject_id, $current_year['id']]);
                        
                        if ($existing = $check_stmt->fetch()) {
                            // Update existing grade (keep original teacher_id but update recorded_by)
                            $update_query = "UPDATE student_grades 
                                           SET final_grade = ?, remarks = ?, recorded_by = ?, updated_at = NOW()
                                           WHERE id = ?";
                            $update_stmt = $db->prepare($update_query);
                            $update_stmt->execute([$final_grade, $remarks, $_SESSION['user_id'], $existing['id']]);
                        } else {
                            // Insert new grade
                            $insert_query = "INSERT INTO student_grades (student_id, subject_id, teacher_id, school_year_id, final_grade, remarks, date_recorded, recorded_by)
                                           VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?)";
                            $insert_stmt = $db->prepare($insert_query);
                            $insert_stmt->execute([$student_id, $subject_id, $_SESSION['user_id'], $current_year['id'], $final_grade, $remarks, $_SESSION['user_id']]);
                        }
                    }
                }
                
                $db->commit();
                $success_message = "Grades saved successfully!";
                
            } catch (Exception $e) {
                $db->rollback();
                $error_message = "Error saving grades: " . $e->getMessage();
            }
        }
    }
    
    // Get selected section and subject for grade management
    $selected_section_id = isset($_GET['section_id']) ? (int)$_GET['section_id'] : null;
    $selected_subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : null;
    
    $students_with_grades = [];
    $selected_section = null;
    $selected_subject = null;
    
    if ($selected_section_id && $selected_subject_id) {
        // Verify teacher has access to this section and that the subject is available for the section's grade level
        $verify_query = "SELECT DISTINCT st.section_id, s.id as subject_id, s.subject_name, s.subject_code, 
                                sec.section_name, gl.grade_name, gl.id as grade_level_id
                        FROM section_teachers st
                        LEFT JOIN sections sec ON st.section_id = sec.id
                        LEFT JOIN grade_levels gl ON sec.grade_level_id = gl.id
                        LEFT JOIN curriculum c ON gl.id = c.grade_level_id
                        LEFT JOIN subjects s ON c.subject_id = s.id
                        WHERE st.teacher_id = ? AND st.section_id = ? AND s.id = ? AND st.is_active = 1 AND s.is_active = 1";
        
        $verify_stmt = $db->prepare($verify_query);
        $verify_stmt->execute([$_SESSION['user_id'], $selected_section_id, $selected_subject_id]);
        
        if ($verify_result = $verify_stmt->fetch()) {
            $selected_section = $verify_result;
            $selected_subject = $verify_result;
            
            // Get students and their grades (removed teacher_id filter to allow any teacher to see all grades)
            $query = "SELECT st.id as student_id, st.first_name, st.last_name, st.middle_name, st.student_id as student_number,
                             sg.final_grade, sg.remarks, sg.id as grade_id, sg.teacher_id as original_teacher_id,
                             u.username as original_teacher_name
                      FROM students st
                      LEFT JOIN student_grades sg ON st.id = sg.student_id 
                                                   AND sg.subject_id = ? 
                                                   AND sg.school_year_id = ?
                      LEFT JOIN users u ON sg.teacher_id = u.id
                      WHERE st.current_section_id = ? 
                      ORDER BY st.last_name, st.first_name";
            
            $stmt = $db->prepare($query);
            $stmt->execute([$selected_subject_id, $current_year['id'], $selected_section_id]);
            
            $students_with_grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
} catch (Exception $e) {
    $error_message = "Unable to load grades data: " . $e->getMessage();
    error_log("Teacher grades error: " . $e->getMessage());
}

ob_start();
?>

<div class="page-header">
    <h1 class="page-title">Manage Grades</h1>
    <p class="page-subtitle">Enter and manage student grades for your subjects</p>
</div>

<?php if (isset($success_message)): ?>
    <div class="alert alert-success">
        <?php echo $success_message; ?>
    </div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <?php echo $error_message; ?>
    </div>
<?php endif; ?>

<!-- Section and Subject Selection -->
<div class="selection-card">
    <h2 class="card-title">Select Section and Subject</h2>
    
    <?php if (empty($teacher_subjects)): ?>
        <div class="empty-state">
            <div class="empty-icon">📚</div>
            <h3>No subjects available</h3>
            <p>You don't have any sections assigned yet, or no subjects are configured for your assigned grade levels. Please contact the principal's office.</p>
        </div>
    <?php else: ?>
        <form method="GET" class="selection-form">
            <div class="form-grid">
                <div class="form-group">
                    <label for="section_id" class="form-label">Select Section:</label>
                    <select name="section_id" id="section_id" class="form-control" required>
                        <option value="">Choose a section...</option>
                        <?php foreach ($teacher_sections as $section): ?>
                            <option value="<?php echo $section['id']; ?>" 
                                    data-grade-level="<?php echo $section['grade_level_id']; ?>"
                                    <?php echo ($selected_section_id == $section['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($section['grade_name'] . ' - ' . $section['section_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="subject_id" class="form-label">Select Subject:</label>
                    <select name="subject_id" id="subject_id" class="form-control" required>
                        <option value="">Choose a subject...</option>
                        <?php 
                        // Group subjects by grade level for dynamic filtering
                        foreach ($teacher_subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>" 
                                    data-grade-level-id="<?php echo $subject['grade_level_id']; ?>"
                                    <?php echo ($selected_subject_id == $subject['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Load Students</button>
                </div>
            </div>
            
            <div class="form-help">
                <p><strong>Note:</strong> As a teacher assigned to sections, you can manage grades for ALL subjects taught in your assigned sections, not just the subjects you personally teach. You can also view and edit grades entered by other teachers.</p>
            </div>
        </form>
    <?php endif; ?>
</div>

<!-- Grade Management -->
<?php if (!empty($students_with_grades) && $selected_section && $selected_subject): ?>
<div class="grades-card">
    <div class="card-header">
        <h2 class="card-title">
            Grade Entry: <?php echo htmlspecialchars($selected_subject['subject_code'] . ' - ' . $selected_subject['subject_name']); ?>
        </h2>
        <div class="section-info">
            <span class="section-badge">
                <?php echo htmlspecialchars($selected_section['grade_name'] . ' - ' . $selected_section['section_name']); ?>
            </span>
        </div>
    </div>
    
    <form method="POST" class="grades-form">
        <input type="hidden" name="action" value="save_grades">
        <input type="hidden" name="section_id" value="<?php echo $selected_section_id; ?>">
        <input type="hidden" name="subject_id" value="<?php echo $selected_subject_id; ?>">
        
        <div class="grades-table-container">
            <table class="grades-table">
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Final Grade</th>
                        <th>Remarks</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students_with_grades as $student): ?>
                        <tr>
                            <td class="student-id"><?php echo htmlspecialchars($student['student_number']); ?></td>
                            <td class="student-name">
                                <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                <?php if ($student['middle_name']): ?>
                                    <br><small><?php echo htmlspecialchars($student['middle_name']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="grade-input">
                                <input type="number" 
                                       name="grades[<?php echo $student['student_id']; ?>][final_grade]" 
                                       value="<?php echo htmlspecialchars($student['final_grade'] ?? ''); ?>"
                                       min="60" 
                                       max="100" 
                                       step="0.01"
                                       class="form-control grade-field"
                                       placeholder="Enter grade">
                            </td>
                            <td class="remarks-input">
                                <select name="grades[<?php echo $student['student_id']; ?>][remarks]" 
                                        class="form-control remarks-field">
                                    <option value="">Auto</option>
                                    <option value="Passed" <?php echo ($student['remarks'] == 'Passed') ? 'selected' : ''; ?>>Passed</option>
                                    <option value="Failed" <?php echo ($student['remarks'] == 'Failed') ? 'selected' : ''; ?>>Failed</option>
                                    <option value="Incomplete" <?php echo ($student['remarks'] == 'Incomplete') ? 'selected' : ''; ?>>Incomplete</option>
                                    <option value="Dropped" <?php echo ($student['remarks'] == 'Dropped') ? 'selected' : ''; ?>>Dropped</option>
                                </select>
                            </td>
                            <td class="grade-status">
                                <?php if ($student['grade_id']): ?>
                                    <span class="status-badge saved">Saved</span>
                                <?php else: ?>
                                    <span class="status-badge pending">Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-save"></i>
                Save All Grades
            </button>
            <button type="button" class="btn btn-outline" onclick="calculateGradeStats()">
                <i class="fas fa-chart-bar"></i>
                Calculate Statistics
            </button>
        </div>
    </form>
    
    <!-- Grade Statistics -->
    <div id="grade-stats" class="grade-stats" style="display: none;">
        <h3>Grade Statistics</h3>
        <div class="stats-grid">
            <div class="stat-item">
                <span class="stat-label">Total Students:</span>
                <span class="stat-value" id="total-students">0</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Average Grade:</span>
                <span class="stat-value" id="average-grade">0</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Passing Rate:</span>
                <span class="stat-value" id="passing-rate">0%</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Highest Grade:</span>
                <span class="stat-value" id="highest-grade">0</span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Quick Actions -->
<div class="quick-actions">
    <h2 class="section-title">Quick Actions</h2>
    <div class="action-buttons">
        <a href="sections.php" class="btn btn-outline">
            <i class="fas fa-chalkboard-teacher"></i>
            View My Classes
        </a>
        <a href="dashboard.php" class="btn btn-outline">
            <i class="fas fa-home"></i>
            Teacher Dashboard
        </a>
        <a href="../principal/announcements.php" class="btn btn-outline">
            <i class="fas fa-bullhorn"></i>
            School Announcements
        </a>
    </div>
</div>

<style>
/* Teacher Grades Styles */
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

.selection-card, .grades-card, .quick-actions {
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

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.section-badge {
    background: #3b82f6;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 500;
}

.selection-form {
    margin-top: 1rem;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: 1rem;
    align-items: end;
}

.form-help {
    margin-top: 1rem;
    padding: 1rem;
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    border-radius: 8px;
    color: #0c4a6e;
}

.form-help p {
    margin: 0;
    font-size: 0.9rem;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-label {
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 0.5rem;
}

.form-control {
    padding: 0.75rem;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.2s ease;
}

.form-control:focus {
    outline: none;
    border-color: #3b82f6;
}

.grades-table-container {
    overflow-x: auto;
    margin-bottom: 2rem;
}

.grades-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

.grades-table th,
.grades-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.grades-table th {
    background: #f9fafb;
    font-weight: 600;
    color: #1f2937;
    position: sticky;
    top: 0;
    z-index: 10;
}

.grades-table tbody tr:hover {
    background: #f9fafb;
}

.grade-field, .remarks-field {
    padding: 0.5rem;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.9rem;
}

.grade-field {
    width: 100px;
    text-align: center;
}

.remarks-field {
    width: 120px;
}

.grade-field:focus, .remarks-field:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-badge.saved {
    background: #dcfce7;
    color: #166534;
}

.status-badge.pending {
    background: #fef3c7;
    color: #92400e;
}

.original-teacher {
    text-align: center;
    font-size: 0.8rem;
    color: #6b7280;
}

.text-muted {
    color: #6b7280 !important;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    align-items: center;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 2px solid #e5e7eb;
}

.grade-stats {
    margin-top: 2rem;
    padding: 1.5rem;
    background: #f9fafb;
    border-radius: 8px;
}

.grade-stats h3 {
    color: #1f2937;
    margin-bottom: 1rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
}

.stat-item {
    display: flex;
    flex-direction: column;
    text-align: center;
    padding: 1rem;
    background: white;
    border-radius: 6px;
}

.stat-label {
    font-size: 0.8rem;
    color: #6b7280;
    margin-bottom: 0.5rem;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
}

.section-title {
    font-size: 1.5rem;
    color: #1f2937;
    margin-bottom: 1.5rem;
    font-weight: 600;
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 0.5rem;
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

.btn-lg {
    padding: 1rem 2rem;
    font-size: 1.1rem;
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

.alert-success {
    background: #f0fdf4;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.alert-danger {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}

/* Responsive Design */
@media (max-width: 768px) {
    .page-header {
        padding: 1.5rem;
    }
    
    .page-title {
        font-size: 2rem;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .grades-table {
        font-size: 0.875rem;
    }
    
    .grades-table th,
    .grades-table td {
        padding: 0.75rem 0.5rem;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .action-buttons {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<script>
// Dynamic subject filtering based on section selection
document.addEventListener('DOMContentLoaded', function() {
    const sectionSelect = document.getElementById('section_id');
    const subjectSelect = document.getElementById('subject_id');
    
    console.log('DOM loaded, elements found:', sectionSelect !== null, subjectSelect !== null);
    
    if (sectionSelect && subjectSelect) {
        // Store all subject options for filtering
        const allSubjectOptions = Array.from(subjectSelect.querySelectorAll('option[data-grade-level-id]'));
        console.log('Total subject options found:', allSubjectOptions.length);
        
        // Log all subjects and their grade levels
        allSubjectOptions.forEach(option => {
            console.log('Subject:', option.textContent, 'Grade Level ID:', option.getAttribute('data-grade-level-id'));
        });
        
        sectionSelect.addEventListener('change', function() {
            const selectedSectionOption = this.options[this.selectedIndex];
            const selectedGradeLevelId = selectedSectionOption ? selectedSectionOption.getAttribute('data-grade-level') : '';
            
            console.log('Section changed to:', selectedSectionOption ? selectedSectionOption.textContent : 'none');
            console.log('Selected Grade Level ID:', selectedGradeLevelId);
            
            // Clear current subject selection
            subjectSelect.value = '';
            
            // Remove all subject options except the default one
            const defaultOption = subjectSelect.querySelector('option[value=""]');
            subjectSelect.innerHTML = '';
            if (defaultOption) {
                subjectSelect.appendChild(defaultOption);
            }
            
            if (selectedGradeLevelId) {
                // Add back only subjects for the selected grade level
                let foundSubjects = 0;
                allSubjectOptions.forEach(option => {
                    const optionGradeLevelId = option.getAttribute('data-grade-level-id');
                    console.log('Comparing:', optionGradeLevelId, '===', selectedGradeLevelId);
                    if (optionGradeLevelId === selectedGradeLevelId) {
                        subjectSelect.appendChild(option.cloneNode(true));
                        foundSubjects++;
                        console.log('Added subject:', option.textContent);
                    }
                });
                
                console.log('Total subjects added:', foundSubjects);
                
                // Enable subject select
                subjectSelect.disabled = false;
            } else {
                // If no section selected, disable subject select
                subjectSelect.disabled = true;
            }
        });
        
        // Initialize on page load
        if (sectionSelect.value) {
            console.log('Triggering change on page load for section:', sectionSelect.value);
            sectionSelect.dispatchEvent(new Event('change'));
        } else {
            // Initially disable subject select if no section is selected
            subjectSelect.disabled = true;
        }
    }
    else {
        console.error('Could not find section or subject select elements');
    }
    
    // Auto-calculate remarks based on grade
    const gradeFields = document.querySelectorAll('.grade-field');
    gradeFields.forEach(field => {
        field.addEventListener('blur', function() {
            const grade = parseFloat(this.value);
            const row = this.closest('tr');
            const remarksSelect = row.querySelector('.remarks-field');
            
            if (grade && remarksSelect.value === '') {
                if (grade >= 75) {
                    remarksSelect.value = 'Passed';
                } else {
                    remarksSelect.value = 'Failed';
                }
            }
        });
    });
});

// Calculate grade statistics
function calculateGradeStats() {
    const gradeFields = document.querySelectorAll('.grade-field');
    const grades = [];
    
    gradeFields.forEach(field => {
        const grade = parseFloat(field.value);
        if (!isNaN(grade) && grade > 0) {
            grades.push(grade);
        }
    });
    
    if (grades.length === 0) {
        alert('No grades entered yet.');
        return;
    }
    
    const totalStudents = gradeFields.length;
    const enteredGrades = grades.length;
    const average = grades.reduce((sum, grade) => sum + grade, 0) / grades.length;
    const passingGrades = grades.filter(grade => grade >= 75).length;
    const passingRate = (passingGrades / enteredGrades) * 100;
    const highest = Math.max(...grades);
    
    // Update stats display
    document.getElementById('total-students').textContent = `${enteredGrades}/${totalStudents}`;
    document.getElementById('average-grade').textContent = average.toFixed(2);
    document.getElementById('passing-rate').textContent = passingRate.toFixed(1) + '%';
    document.getElementById('highest-grade').textContent = highest.toFixed(2);
    
    // Show stats
    document.getElementById('grade-stats').style.display = 'block';
}

// Form validation
document.querySelector('.grades-form')?.addEventListener('submit', function(e) {
    const gradeFields = document.querySelectorAll('.grade-field');
    let hasGrades = false;
    
    gradeFields.forEach(field => {
        if (field.value.trim() !== '') {
            hasGrades = true;
        }
    });
    
    if (!hasGrades) {
        e.preventDefault();
        alert('Please enter at least one grade before saving.');
        return false;
    }
});
</script>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
