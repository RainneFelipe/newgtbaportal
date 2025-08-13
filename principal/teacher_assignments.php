<?php
require_once '../includes/auth_check.php';

// Check if user is a principal
if (!checkRole('principal')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$page_title = 'Teacher Assignments - GTBA Portal';
$base_url = '../';

try {
    $database = new Database();
    $db = $database->connect();
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'assign_teacher':
                    $section_id = $_POST['section_id'];
                    $teacher_id = $_POST['teacher_id'];
                    $is_primary = isset($_POST['is_primary']) ? 1 : 0;
                    
                    if ($section_id && $teacher_id) {
                        // Check if teacher is already assigned to this section
                        $check_query = "SELECT COUNT(*) as exists FROM section_teachers 
                                       WHERE section_id = ? AND teacher_id = ? AND is_active = 1";
                        $check_stmt = $db->prepare($check_query);
                        $check_stmt->execute([$section_id, $teacher_id]);
                        $exists = $check_stmt->fetch(PDO::FETCH_ASSOC)['exists'];
                        
                        if ($exists == 0) {
                            // If setting as primary, remove primary status from other teachers in the section
                            if ($is_primary) {
                                $update_query = "UPDATE section_teachers SET is_primary = 0 
                                               WHERE section_id = ? AND is_active = 1";
                                $update_stmt = $db->prepare($update_query);
                                $update_stmt->execute([$section_id]);
                            }
                            
                            // Insert the new assignment
                            $query = "INSERT INTO section_teachers (section_id, teacher_id, is_primary, created_by, created_at) 
                                      VALUES (?, ?, ?, ?, NOW())";
                            $stmt = $db->prepare($query);
                            if ($stmt->execute([$section_id, $teacher_id, $is_primary, $_SESSION['user_id']])) {
                                $success_message = "Teacher assigned successfully!";
                            } else {
                                $error_message = "Failed to assign teacher.";
                            }
                        } else {
                            $error_message = "Teacher is already assigned to this section.";
                        }
                    } else {
                        $error_message = "Please select both section and teacher.";
                    }
                    break;
                    
                case 'remove_assignment':
                    $assignment_id = $_POST['assignment_id'];
                    if ($assignment_id) {
                        $query = "UPDATE section_teachers SET is_active = 0, updated_at = NOW() WHERE id = ?";
                        $stmt = $db->prepare($query);
                        if ($stmt->execute([$assignment_id])) {
                            $success_message = "Teacher assignment removed successfully!";
                        } else {
                            $error_message = "Failed to remove teacher assignment.";
                        }
                    }
                    break;
                    
                case 'set_primary':
                    $assignment_id = $_POST['assignment_id'];
                    $section_id = $_POST['section_id'];
                    
                    if ($assignment_id && $section_id) {
                        // Remove primary status from all teachers in the section
                        $update_query = "UPDATE section_teachers SET is_primary = 0 WHERE section_id = ? AND is_active = 1";
                        $update_stmt = $db->prepare($update_query);
                        $update_stmt->execute([$section_id]);
                        
                        // Set new primary teacher
                        $query = "UPDATE section_teachers SET is_primary = 1, updated_at = NOW() WHERE id = ?";
                        $stmt = $db->prepare($query);
                        if ($stmt->execute([$assignment_id])) {
                            $success_message = "Primary teacher updated successfully!";
                        } else {
                            $error_message = "Failed to update primary teacher.";
                        }
                    }
                    break;
            }
        }
    }
    
    // Get filter parameters
    $section_filter = $_GET['section_filter'] ?? '';
    $teacher_filter = $_GET['teacher_filter'] ?? '';
    $grade_filter = $_GET['grade_filter'] ?? '';
    
    // Get teacher assignments with related data
    $query = "SELECT st.*, s.section_name, gl.grade_name, sy.year_label,
              CONCAT(u.first_name, ' ', u.last_name) as teacher_name, u.email as teacher_email
              FROM section_teachers st
              LEFT JOIN sections s ON st.section_id = s.id
              LEFT JOIN grade_levels gl ON s.grade_level_id = gl.id
              LEFT JOIN school_years sy ON s.school_year_id = sy.id
              LEFT JOIN users u ON st.teacher_id = u.id
              WHERE st.is_active = 1";
    
    $params = [];
    
    if ($section_filter) {
        $query .= " AND st.section_id = ?";
        $params[] = $section_filter;
    }
    
    if ($teacher_filter) {
        $query .= " AND st.teacher_id = ?";
        $params[] = $teacher_filter;
    }
    
    if ($grade_filter) {
        $query .= " AND s.grade_level_id = ?";
        $params[] = $grade_filter;
    }
    
    $query .= " ORDER BY gl.grade_order, s.section_name, st.is_primary DESC, u.first_name";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    
    // Get teachers for filters and forms (from users table with teacher role)
    $query = "SELECT u.id, u.first_name, u.last_name, u.email 
              FROM users u 
              JOIN roles r ON u.role_id = r.id 
              WHERE r.name = 'teacher' AND u.is_active = 1 
              ORDER BY u.first_name, u.last_name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get grade levels for filters
    $query = "SELECT * FROM grade_levels WHERE is_active = 1 ORDER BY grade_order";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $grade_levels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Unable to load teacher assignments data.";
    error_log("Teacher assignments error: " . $e->getMessage());
}

ob_start();
?>

<div class="page-header-modern">
    <div class="page-header-content">
        <div class="page-title-section">
            <div class="page-title-wrapper">
                <h1 class="page-title">
                    <span class="page-icon">👨‍🏫</span>
                    Teacher Assignments
                </h1>
                <nav class="page-breadcrumb">
                    <span class="breadcrumb-item">Principal</span>
                    <span class="breadcrumb-separator">›</span>
                    <span class="breadcrumb-item current">Teacher Assignments</span>
                </nav>
            </div>
            <p class="page-description">Assign teachers to sections and manage primary teacher designations</p>
        </div>
        <div class="page-actions">
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($assignments); ?></div>
                    <div class="stat-label">Total Assignments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count(array_filter($assignments, function($a) { return $a['is_primary']; })); ?></div>
                    <div class="stat-label">Primary Teachers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($teachers); ?></div>
                    <div class="stat-label">Available Teachers</div>
                </div>
            </div>
            <button type="button" class="btn-modern btn-primary" data-modal-target="assign-teacher-modal">
                <i class="fas fa-plus"></i>
                Assign Teacher
            </button>
        </div>
    </div>
</div>

<?php if (isset($success_message)): ?>
    <div class="alert-modern alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo $success_message; ?>
    </div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div class="alert-modern alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo $error_message; ?>
    </div>
<?php endif; ?>

<!-- Filters -->
<div class="filters-modern">
    <div class="filters-header">
        <h3 class="filters-title">
            <i class="fas fa-filter"></i>
            Filter Assignments
        </h3>
        <button type="button" class="btn-modern btn-ghost btn-sm" onclick="clearFilters()">
            <i class="fas fa-times"></i>
            Clear Filters
        </button>
    </div>
    <div class="filters-content">
        <form method="GET" class="filters-form-modern">
            <div class="filters-row">
                <div class="filter-group-modern">
                    <label class="filter-label" for="grade_filter">
                        <i class="fas fa-graduation-cap"></i>
                        Grade Level
                    </label>
                    <div class="select-wrapper">
                        <select name="grade_filter" id="grade_filter" class="select-modern">
                            <option value="">All Grades</option>
                            <?php foreach ($grade_levels as $grade): ?>
                                <option value="<?php echo $grade['id']; ?>" <?php echo $grade_filter == $grade['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($grade['grade_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down select-arrow"></i>
                    </div>
                </div>
                
                <div class="filter-group-modern">
                    <label class="filter-label" for="section_filter">
                        <i class="fas fa-users"></i>
                        Section
                    </label>
                    <div class="select-wrapper">
                        <select name="section_filter" id="section_filter" class="select-modern">
                            <option value="">All Sections</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?php echo $section['id']; ?>" <?php echo $section_filter == $section['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($section['section_display']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down select-arrow"></i>
                    </div>
                </div>
                
                <div class="filter-group-modern">
                    <label class="filter-label" for="teacher_filter">
                        <i class="fas fa-chalkboard-teacher"></i>
                        Teacher
                    </label>
                    <div class="select-wrapper">
                        <select name="teacher_filter" id="teacher_filter" class="select-modern">
                            <option value="">All Teachers</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>" <?php echo $teacher_filter == $teacher['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down select-arrow"></i>
                    </div>
                </div>
            </div>
            
            <div class="filters-actions">
                <button type="submit" class="btn-modern btn-primary">
                    <i class="fas fa-search"></i>
                    Apply Filters
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Teacher Assignments List -->
<div class="content-wrapper">
    <div class="view-controls">
        <div class="view-info">
            <h3>Teacher Assignments</h3>
            <div class="results-count">
                Showing <?php echo count($assignments); ?> assignment<?php echo count($assignments) !== 1 ? 's' : ''; ?>
                <?php if ($section_filter || $teacher_filter || $grade_filter): ?>
                    (filtered)
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if (empty($assignments)): ?>
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <h3 class="empty-title">No teacher assignments found</h3>
            <p class="empty-description">
                <?php if ($section_filter || $teacher_filter || $grade_filter): ?>
                    No assignments match your current filters. Try adjusting your search criteria.
                <?php else: ?>
                    Get started by assigning teachers to sections to organize your academic staff.
                <?php endif; ?>
            </p>
            <?php if (!($section_filter || $teacher_filter || $grade_filter)): ?>
                <button type="button" class="btn-modern btn-primary" data-modal-target="assign-teacher-modal">
                    <i class="fas fa-plus"></i>
                    Assign Your First Teacher
                </button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="assignments-table-container">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th>Section</th>
                        <th>Teacher</th>
                        <th>Role</th>
                        <th>Assigned Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assignments as $assignment): ?>
                        <tr>
                            <td>
                                <div class="section-info">
                                    <div class="section-name"><?php echo htmlspecialchars($assignment['section_name']); ?></div>
                                    <div class="section-details"><?php echo htmlspecialchars($assignment['grade_name'] . ' - ' . $assignment['year_label']); ?></div>
                                </div>
                            </td>
                            <td>
                                <div class="teacher-info">
                                    <div class="teacher-name"><?php echo htmlspecialchars($assignment['teacher_name']); ?></div>
                                    <div class="teacher-email"><?php echo htmlspecialchars($assignment['teacher_email']); ?></div>
                                </div>
                            </td>
                            <td>
                                <?php if ($assignment['is_primary']): ?>
                                    <span class="role-badge role-primary">
                                        <i class="fas fa-star"></i>
                                        Primary Teacher
                                    </span>
                                <?php else: ?>
                                    <span class="role-badge role-assistant">
                                        <i class="fas fa-user"></i>
                                        Assistant Teacher
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="date-info">
                                    <?php echo date('M j, Y', strtotime($assignment['assigned_date'])); ?>
                                </div>
                            </td>
                            <td class="actions-cell">
                                <div class="actions-group">
                                    <?php if (!$assignment['is_primary']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="set_primary">
                                            <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                            <input type="hidden" name="section_id" value="<?php echo $assignment['section_id']; ?>">
                                            <button type="submit" class="btn-modern btn-success btn-sm" title="Set as primary teacher">
                                                <i class="fas fa-star"></i>
                                                Set Primary
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <button type="button" class="btn-modern btn-danger btn-sm" 
                                            onclick="confirmRemoveAssignment(<?php echo $assignment['id']; ?>, '<?php echo htmlspecialchars($assignment['teacher_name']); ?>', '<?php echo htmlspecialchars($assignment['section_name']); ?>')"
                                            title="Remove assignment">
                                        <i class="fas fa-times"></i>
                                        Remove
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Assign Teacher Modal -->
<div id="assign-teacher-modal" class="modal-modern">
    <div class="modal-overlay"></div>
    <div class="modal-container">
        <div class="modal-header-modern">
            <div class="modal-title-section">
                <h3 class="modal-title">
                    <i class="fas fa-plus"></i>
                    Assign Teacher to Section
                </h3>
                <p class="modal-subtitle">Select a section and teacher to create a new assignment</p>
            </div>
            <button type="button" class="modal-close-modern" data-modal-close>
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body-modern">
            <form method="POST" id="assign-teacher-form" class="form-modern">
                <input type="hidden" name="action" value="assign_teacher">
                
                <div class="form-sections">
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="fas fa-info-circle"></i>
                            Assignment Details
                        </h4>
                        <div class="form-grid-modern">
                            <div class="form-group-modern">
                                <label for="section_id" class="form-label-modern">
                                    <span class="label-text">Section</span>
                                    <span class="label-required">*</span>
                                </label>
                                <div class="select-wrapper-modern">
                                    <select id="section_id" name="section_id" class="form-control-modern" required>
                                        <option value="">Select Section</option>
                                        <?php foreach ($sections as $section): ?>
                                            <option value="<?php echo $section['id']; ?>">
                                                <?php echo htmlspecialchars($section['section_display']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="fas fa-chevron-down select-arrow-modern"></i>
                                </div>
                            </div>
                            
                            <div class="form-group-modern">
                                <label for="teacher_id" class="form-label-modern">
                                    <span class="label-text">Teacher</span>
                                    <span class="label-required">*</span>
                                </label>
                                <div class="select-wrapper-modern">
                                    <select id="teacher_id" name="teacher_id" class="form-control-modern" required>
                                        <option value="">Select Teacher</option>
                                        <?php foreach ($teachers as $teacher): ?>
                                            <option value="<?php echo $teacher['id']; ?>">
                                                <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                                (<?php echo htmlspecialchars($teacher['email']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="fas fa-chevron-down select-arrow-modern"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group-modern full-width">
                            <label class="checkbox-label-modern">
                                <input type="checkbox" name="is_primary" class="checkbox-modern">
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Set as Primary Teacher</span>
                            </label>
                            <div class="form-help">Primary teachers are the main responsible teacher for the section. Only one primary teacher per section is allowed.</div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer-modern">
                    <button type="button" class="btn-modern btn-ghost" data-modal-close>
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                    <button type="submit" class="btn-modern btn-primary">
                        <i class="fas fa-check"></i>
                        Assign Teacher
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Remove Assignment Confirmation Modal -->
<div id="remove-assignment-modal" class="modal-modern">
    <div class="modal-overlay"></div>
    <div class="modal-container">
        <div class="modal-header-modern">
            <div class="modal-title-section">
                <h3 class="modal-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    Confirm Removal
                </h3>
                <p class="modal-subtitle">This action cannot be undone</p>
            </div>
            <button type="button" class="modal-close-modern" data-modal-close>
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body-modern">
            <div class="delete-confirmation">
                <div class="delete-icon">
                    <i class="fas fa-user-times"></i>
                </div>
                <p class="delete-message">Are you sure you want to remove <strong id="remove-teacher-name"></strong> from <strong id="remove-section-name"></strong>?</p>
                <p class="delete-warning">This will remove the teacher's assignment to this section but will not affect their teaching schedules.</p>
            </div>
            
            <form method="POST" id="remove-assignment-form">
                <input type="hidden" name="action" value="remove_assignment">
                <input type="hidden" name="assignment_id" id="remove_assignment_id">
                
                <div class="modal-footer-modern">
                    <button type="button" class="btn-modern btn-ghost" data-modal-close>
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                    <button type="submit" class="btn-modern btn-danger">
                        <i class="fas fa-trash"></i>
                        Remove Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Modern Modal and Assignment Management
document.addEventListener('DOMContentLoaded', function() {
    initializeModals();
    initializeFilters();
    initializeFormValidation();
});

function initializeModals() {
    // Modal open/close functionality
    const modalTriggers = document.querySelectorAll('[data-modal-target]');
    const modalCloses = document.querySelectorAll('.modal-close-modern, [data-modal-close]');
    const modals = document.querySelectorAll('.modal-modern');
    
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function() {
            const targetModal = document.getElementById(this.dataset.modalTarget);
            if (targetModal) {
                openModal(targetModal);
            }
        });
    });
    
    modalCloses.forEach(close => {
        close.addEventListener('click', function() {
            const modal = this.closest('.modal-modern');
            if (modal) {
                closeModal(modal);
            }
        });
    });
    
    modals.forEach(modal => {
        const overlay = modal.querySelector('.modal-overlay');
        if (overlay) {
            overlay.addEventListener('click', function() {
                closeModal(modal);
            });
        }
    });

    // Close modal on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.modal-modern.active');
            if (openModal) {
                closeModal(openModal);
            }
        }
    });
}

function openModal(modal) {
    modal.classList.add('active');
    document.body.classList.add('modal-open');
    document.body.style.overflow = 'hidden';
    
    // Focus first input
    setTimeout(() => {
        const firstInput = modal.querySelector('input:not([type="hidden"]), select, textarea');
        if (firstInput) {
            firstInput.focus();
        }
    }, 100);
}

function closeModal(modal) {
    modal.classList.remove('active');
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
    
    // Reset form if it exists
    const form = modal.querySelector('form');
    if (form) {
        form.reset();
    }
}

function initializeFilters() {
    const filterForm = document.querySelector('.filters-form-modern');
    if (filterForm) {
        // Auto-submit on filter change
        const filterSelects = filterForm.querySelectorAll('select');
        filterSelects.forEach(select => {
            select.addEventListener('change', function() {
                // Add a small delay to allow user to make multiple selections
                clearTimeout(this.filterTimeout);
                this.filterTimeout = setTimeout(() => {
                    filterForm.submit();
                }, 300);
            });
        });
    }
}

function initializeFormValidation() {
    const forms = document.querySelectorAll('.form-modern');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
    });
}

function validateForm(form) {
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            showFieldError(field, 'This field is required');
            isValid = false;
        } else {
            clearFieldError(field);
        }
    });
    
    return isValid;
}

function showFieldError(field, message) {
    clearFieldError(field);
    field.classList.add('error');
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.textContent = message;
    
    field.parentNode.appendChild(errorDiv);
}

function clearFieldError(field) {
    field.classList.remove('error');
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
}

function confirmRemoveAssignment(assignmentId, teacherName, sectionName) {
    document.getElementById('remove_assignment_id').value = assignmentId;
    document.getElementById('remove-teacher-name').textContent = teacherName;
    document.getElementById('remove-section-name').textContent = sectionName;
    
    const removeModal = document.getElementById('remove-assignment-modal');
    if (removeModal) {
        openModal(removeModal);
    }
}

function clearFilters() {
    const filterSelects = document.querySelectorAll('.select-modern');
    filterSelects.forEach(select => {
        select.value = '';
    });
    
    // Redirect to clear filters
    window.location.href = 'teacher_assignments.php';
}

// Show success/error messages with animation
function showAlert(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert-modern alert-${type} alert-floating`;
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        ${message}
    `;
    
    document.body.appendChild(alertDiv);
    
    // Animate in
    setTimeout(() => {
        alertDiv.classList.add('show');
    }, 100);
    
    // Remove after 5 seconds
    setTimeout(() => {
        alertDiv.classList.remove('show');
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.parentNode.removeChild(alertDiv);
            }
        }, 300);
    }, 5000);
}
</script>

<style>
/* Teacher Assignments Styles */

/* Inherit all the modern styles from announcements.php */
.page-header-modern,
.stats-cards,
.stat-card,
.btn-modern,
.alert-modern,
.filters-modern,
.filters-header,
.filters-title,
.filters-content,
.filters-form-modern,
.filters-row,
.filter-group-modern,
.filter-label,
.select-wrapper,
.select-modern,
.select-arrow,
.content-wrapper,
.view-controls,
.empty-state,
.empty-icon,
.empty-title,
.empty-description,
.modal-modern,
.modal-overlay,
.modal-container,
.modal-header-modern,
.modal-title-section,
.modal-title,
.modal-subtitle,
.modal-close-modern,
.modal-body-modern,
.modal-footer-modern,
.form-modern,
.form-sections,
.form-section,
.section-title,
.form-grid-modern,
.form-group-modern,
.form-label-modern,
.label-text,
.label-required,
.form-control-modern,
.select-wrapper-modern,
.select-arrow-modern,
.checkbox-label-modern,
.checkbox-modern,
.checkbox-custom,
.checkbox-text,
.form-help,
.delete-confirmation,
.delete-icon,
.delete-message,
.delete-warning {
    /* Use the same styles as defined in announcements.php */
}

/* Teacher Assignment Specific Styles */
.assignments-table-container {
    padding: 2rem;
}

.table-modern {
    width: 100%;
    border-collapse: collapse;
    background: var(--white);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(46, 134, 171, 0.08);
}

.table-modern thead {
    background: linear-gradient(135deg, var(--light-blue) 0%, rgba(232, 244, 248, 0.7) 100%);
}

.table-modern th {
    padding: 1.25rem;
    text-align: left;
    font-weight: 600;
    color: var(--dark-blue);
    border-bottom: 2px solid var(--border-gray);
    font-size: 0.95rem;
}

.table-modern td {
    padding: 1.25rem;
    border-bottom: 1px solid var(--border-gray);
    vertical-align: middle;
}

.table-modern tbody tr:hover {
    background: rgba(46, 134, 171, 0.02);
}

.section-info,
.teacher-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.section-name,
.teacher-name {
    font-weight: 600;
    color: var(--black);
    font-size: 0.95rem;
}

.section-details,
.teacher-email {
    font-size: 0.8rem;
    color: var(--gray);
}

.role-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.role-primary {
    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
    color: #856404;
    border: 1px solid #ffeaa7;
}

.role-assistant {
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    color: #1565c0;
    border: 1px solid #bbdefb;
}

.date-info {
    font-size: 0.9rem;
    color: var(--gray);
}

.actions-cell {
    width: 200px;
    text-align: center;
}

.actions-group {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    align-items: center;
}

/* Responsive Design */
@media (max-width: 768px) {
    .page-header-content {
        flex-direction: column;
        gap: 2rem;
        align-items: stretch;
        padding: 2rem;
    }

    .page-actions {
        flex-direction: column;
        gap: 1rem;
    }

    .stats-cards {
        justify-content: space-around;
    }

    .filters-row {
        grid-template-columns: 1fr;
    }

    .assignments-table-container {
        padding: 1rem;
        overflow-x: auto;
    }

    .table-modern {
        min-width: 700px;
    }

    .modal-container {
        width: 95%;
        max-height: 95vh;
    }

    .modal-header-modern,
    .modal-body-modern,
    .modal-footer-modern {
        padding-left: 1.5rem;
        padding-right: 1.5rem;
    }

    .form-grid-modern {
        grid-template-columns: 1fr;
    }

    .modal-footer-modern {
        flex-direction: column;
    }

    .actions-group {
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .page-title {
        font-size: 2rem;
    }

    .assignments-table-container {
        padding: 0.5rem;
    }

    .modal-title {
        font-size: 1.5rem;
    }
}
</style>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
