<?php
require_once '../includes/auth_check.php';

// Check if user is a principal
if (!checkRole('principal')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$page_title = 'Section Management - GTBA Portal';
$base_url = '../';

try {
    $database = new Database();
    $db = $database->connect();
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_section':
                    $section_name = trim($_POST['section_name']);
                    $grade_level_id = $_POST['grade_level_id'];
                    $school_year_id = $_POST['school_year_id'];
                    $room_number = trim($_POST['room_number']);
                    $description = trim($_POST['description']);
                    $teacher_assignments = $_POST['teacher_assignments'] ?? [];
                    $primary_teacher = $_POST['primary_teacher'] ?? null;
                    
                    if ($section_name && $grade_level_id && $school_year_id) {
                        try {
                            $db->beginTransaction();
                            
                            // Create the section
                            $query = "INSERT INTO sections (section_name, grade_level_id, school_year_id, room_number, description, created_by, created_at) 
                                      VALUES (?, ?, ?, ?, ?, ?, NOW())";
                            $stmt = $db->prepare($query);
                            $stmt->execute([$section_name, $grade_level_id, $school_year_id, $room_number, $description, $_SESSION['user_id']]);
                            $section_id = $db->lastInsertId();
                            
                            // Process teacher assignments
                            if (!empty($teacher_assignments)) {
                                foreach ($teacher_assignments as $index => $assignment) {
                                    if (!empty($assignment['teacher_id'])) {
                                        $is_primary = ($primary_teacher == $index) ? 1 : 0;
                                        
                                        $query = "INSERT INTO section_teachers (section_id, teacher_id, is_primary, assigned_date, created_by, created_at) 
                                                  VALUES (?, ?, ?, CURDATE(), ?, NOW())";
                                        $stmt = $db->prepare($query);
                                        $stmt->execute([$section_id, $assignment['teacher_id'], $is_primary, $_SESSION['user_id']]);
                                    }
                                }
                            }
                            
                            $db->commit();
                            $success_message = "Section created successfully with teacher assignments!";
                        } catch (Exception $e) {
                            $db->rollBack();
                            $error_message = "Failed to create section: " . $e->getMessage();
                        }
                    } else {
                        $error_message = "Please fill in all required fields.";
                    }
                    break;
                    
                case 'update_section':
                    $section_id = $_POST['section_id'];
                    $section_name = trim($_POST['section_name']);
                    $grade_level_id = $_POST['grade_level_id'];
                    $school_year_id = $_POST['school_year_id'];
                    $room_number = trim($_POST['room_number']);
                    $description = trim($_POST['description']);
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    
                    if ($section_id && $section_name && $grade_level_id && $school_year_id) {
                        $query = "UPDATE sections SET section_name = ?, grade_level_id = ?, school_year_id = ?, 
                                  room_number = ?, description = ?, is_active = ?, updated_at = NOW()
                                  WHERE id = ?";
                        $stmt = $db->prepare($query);
                        if ($stmt->execute([$section_name, $grade_level_id, $school_year_id, $room_number, $description, $is_active, $section_id])) {
                            $success_message = "Section updated successfully!";
                        } else {
                            $error_message = "Failed to update section.";
                        }
                    } else {
                        $error_message = "Please fill in all required fields.";
                    }
                    break;
                    
                case 'delete_section':
                    $section_id = $_POST['section_id'];
                    if ($section_id) {
                        // Check if section has enrolled students
                        $check_query = "SELECT COUNT(*) as student_count 
                                       FROM students 
                                       WHERE current_section_id = ?";
                        $check_stmt = $db->prepare($check_query);
                        $check_stmt->execute([$section_id]);
                        $student_count = $check_stmt->fetch(PDO::FETCH_ASSOC)['student_count'];
                        
                        if ($student_count > 0) {
                            $error_message = "Cannot delete section with enrolled students. Please transfer students first.";
                        } else {
                            $query = "UPDATE sections SET is_active = 0, updated_at = NOW() WHERE id = ?";
                            $stmt = $db->prepare($query);
                            if ($stmt->execute([$section_id])) {
                                $success_message = "Section deactivated successfully!";
                            } else {
                                $error_message = "Failed to deactivate section.";
                            }
                        }
                    }
                    break;
            }
        }
    }
    
    // Initialize variables
    $sections = [];
    $grade_levels = [];
    $school_years = [];
    $teachers = [];
    
    // Get filter parameters
    $grade_filter = $_GET['grade_filter'] ?? '';
    $year_filter = $_GET['year_filter'] ?? '';
    $status_filter = $_GET['status_filter'] ?? '';
    
    // Get sections with related data
    $query = "SELECT s.*, gl.grade_name, sy.year_label, 
              COUNT(DISTINCT students.id) as enrolled_count,
              GROUP_CONCAT(
                DISTINCT CONCAT(t.first_name, ' ', t.last_name, 
                       CASE WHEN st.is_primary = 1 THEN ' (Primary)' ELSE '' END
                ) SEPARATOR ', '
              ) as teachers_names
              FROM sections s
              LEFT JOIN grade_levels gl ON s.grade_level_id = gl.id
              LEFT JOIN school_years sy ON s.school_year_id = sy.id
              LEFT JOIN section_teachers st ON s.id = st.section_id AND st.is_active = 1
              LEFT JOIN teachers t ON st.teacher_id = t.user_id AND t.is_active = 1
              LEFT JOIN students ON s.id = students.current_section_id
              WHERE 1=1";
    
    $params = [];
    
    if ($grade_filter) {
        $query .= " AND s.grade_level_id = ?";
        $params[] = $grade_filter;
    }
    
    if ($year_filter) {
        $query .= " AND s.school_year_id = ?";
        $params[] = $year_filter;
    }
    
    if ($status_filter === 'active') {
        $query .= " AND s.is_active = 1";
    } elseif ($status_filter === 'inactive') {
        $query .= " AND s.is_active = 0";
    }
    
    $query .= " GROUP BY s.id ORDER BY gl.grade_order, s.section_name";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get grade levels for filters and forms
    $query = "SELECT * FROM grade_levels WHERE is_active = 1 ORDER BY grade_order";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $grade_levels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get school years for filters and forms
    $query = "SELECT * FROM school_years ORDER BY start_date DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $school_years = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get teachers for assignment (from teachers table joined with users table)
    $query = "SELECT u.id, t.first_name, t.last_name, u.email 
              FROM users u 
              JOIN roles r ON u.role_id = r.id 
              JOIN teachers t ON u.id = t.user_id
              WHERE r.name = 'teacher' AND u.is_active = 1 AND t.is_active = 1
              ORDER BY t.first_name, t.last_name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Unable to load sections data.";
    error_log("Sections error: " . $e->getMessage());
}

ob_start();
?>

<div class="page-header-modern">
    <div class="page-title-section">
        <div class="page-title-wrapper">
            <h1 class="page-title">Section Management</h1>
            <div class="page-breadcrumb">
                <span class="breadcrumb-item">Principal</span>
                <span class="breadcrumb-separator">›</span>
                <span class="breadcrumb-item current">Sections</span>
            </div>
        </div>
        <p class="page-description">Manage class sections and monitor enrollment with real-time analytics</p>
    </div>
    <div class="page-actions">
        <button type="button" class="btn-modern btn-primary" data-modal-target="add-section-modal">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 5v14M5 12h14"/>
            </svg>
            Add New Section
        </button>
    </div>
</div>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon stat-icon-primary">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
                <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
            </svg>
        </div>
        <div class="stat-content">
            <div class="stat-number"><?php echo count($sections); ?></div>
            <div class="stat-label">Total Sections</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon stat-icon-success">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22,4 12,14.01 9,11.01"/>
            </svg>
        </div>
        <div class="stat-content">
            <div class="stat-number"><?php echo count(array_filter($sections, function($s) { return $s['is_active']; })); ?></div>
            <div class="stat-label">Active Sections</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon stat-icon-info">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
        </div>
        <div class="stat-content">
            <div class="stat-number"><?php echo array_sum(array_column($sections, 'enrolled_count')); ?></div>
            <div class="stat-label">Total Enrolled</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon stat-icon-warning">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                <path d="M2 17l10 5 10-5"/>
                <path d="M2 12l10 5 10-5"/>
            </svg>
        </div>
        <div class="stat-content">
            <div class="stat-number"><?php echo count($sections); ?></div>
            <div class="stat-label">Active Sections</div>
        </div>
    </div>
</div>

<?php if (isset($success_message)): ?>
    <div class="alert-modern alert-success">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
            <polyline points="22,4 12,14.01 9,11.01"/>
        </svg>
        <?php echo $success_message; ?>
    </div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div class="alert-modern alert-error">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <line x1="15" y1="9" x2="9" y2="15"/>
            <line x1="9" y1="9" x2="15" y2="15"/>
        </svg>
        <?php echo $error_message; ?>
    </div>
<?php endif; ?>

<!-- Advanced Filters -->
<div class="filters-modern">
    <div class="filters-header">
        <h3 class="filters-title">Filter & Search</h3>
        <div class="filters-toggle">
            <button type="button" id="filters-toggle-btn" class="btn-ghost">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="22,3 2,3 10,12.46 10,19 14,21 14,12.46"/>
                </svg>
                Filters
            </button>
        </div>
    </div>
    
    <div class="filters-content" id="filters-content">
        <form method="GET" class="filters-form-modern">
            <div class="filters-row">
                <div class="filter-group-modern">
                    <label for="grade_filter" class="filter-label">Grade Level</label>
                    <div class="select-wrapper">
                        <select name="grade_filter" id="grade_filter" class="select-modern">
                            <option value="">All Grades</option>
                            <?php foreach ($grade_levels as $grade): ?>
                                <option value="<?php echo $grade['id']; ?>" <?php echo $grade_filter == $grade['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($grade['grade_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <svg class="select-arrow" width="12" height="8" viewBox="0 0 12 8">
                            <path d="M1 1L6 6L11 1" stroke="currentColor" stroke-width="1.5" fill="none"/>
                        </svg>
                    </div>
                </div>
                
                <div class="filter-group-modern">
                    <label for="year_filter" class="filter-label">School Year</label>
                    <div class="select-wrapper">
                        <select name="year_filter" id="year_filter" class="select-modern">
                            <option value="">All Years</option>
                            <?php foreach ($school_years as $year): ?>
                                <option value="<?php echo $year['id']; ?>" <?php echo $year_filter == $year['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($year['year_label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <svg class="select-arrow" width="12" height="8" viewBox="0 0 12 8">
                            <path d="M1 1L6 6L11 1" stroke="currentColor" stroke-width="1.5" fill="none"/>
                        </svg>
                    </div>
                </div>
                
                <div class="filter-group-modern">
                    <label for="status_filter" class="filter-label">Status</label>
                    <div class="select-wrapper">
                        <select name="status_filter" id="status_filter" class="select-modern">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                        <svg class="select-arrow" width="12" height="8" viewBox="0 0 12 8">
                            <path d="M1 1L6 6L11 1" stroke="currentColor" stroke-width="1.5" fill="none"/>
                        </svg>
                    </div>
                </div>
            </div>
            
            <div class="filters-actions">
                <button type="submit" class="btn-modern btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="M21 21l-4.35-4.35"/>
                    </svg>
                    Apply Filters
                </button>
                <a href="sections.php" class="btn-modern btn-ghost">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 6h18M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6M8 6V4c0-1 1-2 2-2h4c0 1 1 2 2 2v2"/>
                    </svg>
                    Clear All
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Sections Grid/Table View -->
<div class="content-wrapper">
    <div class="view-controls">
        <div class="view-toggle">
            <button type="button" class="view-btn active" data-view="grid">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"/>
                    <rect x="14" y="3" width="7" height="7"/>
                    <rect x="14" y="14" width="7" height="7"/>
                    <rect x="3" y="14" width="7" height="7"/>
                </svg>
                Grid
            </button>
            <button type="button" class="view-btn" data-view="table">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>
                </svg>
                Table
            </button>
        </div>
        
        <div class="results-info">
            <?php $total_results = count($sections); ?>
            <span class="results-count"><?php echo $total_results; ?> section<?php echo $total_results !== 1 ? 's' : ''; ?> found</span>
        </div>
    </div>

    <!-- Grid View -->
    <div id="grid-view" class="sections-grid">
        <?php if (empty($sections)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                        <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
                        <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
                    </svg>
                </div>
                <div class="empty-state-content">
                    <h3>No sections found</h3>
                    <p>Create your first section to get started with managing class assignments.</p>
                    <button type="button" class="btn-modern btn-primary" data-modal-target="add-section-modal">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>
                        Add New Section
                    </button>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($sections as $section): ?>
                <div class="section-card">
                    <div class="section-card-header">
                        <div class="section-name">
                            <h3><?php echo htmlspecialchars($section['section_name']); ?></h3>
                            <div class="section-grade"><?php echo htmlspecialchars($section['grade_name']); ?></div>
                        </div>
                        <div class="section-status">
                            <span class="status-badge-modern status-<?php echo $section['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $section['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="section-card-body">
                        <?php if ($section['description']): ?>
                            <div class="section-description"><?php echo htmlspecialchars($section['description']); ?></div>
                        <?php endif; ?>
                        
                        <div class="section-details">
                            <div class="detail-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/>
                                </svg>
                                <span><?php echo htmlspecialchars($section['year_label']); ?></span>
                            </div>
                            
                            <div class="detail-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                                <span><?php echo $section['teachers_names'] ? htmlspecialchars($section['teachers_names']) : 'No Teachers Assigned'; ?></span>
                            </div>
                            
                            <?php if ($section['room_number']): ?>
                                <div class="detail-item">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M3 9v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9M3 9V7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2M3 9h18"/>
                                    </svg>
                                    <span>Room <?php echo htmlspecialchars($section['room_number']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="enrollment-progress">
                            <div class="enrollment-header">
                                <span class="enrollment-label">Current Enrollment</span>
                                <span class="enrollment-count"><?php echo $section['enrolled_count']; ?> students</span>
                            </div>
                            <div class="enrollment-note">
                                <small>Students currently enrolled in this section</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="section-card-actions">
                        <a href="section_manage.php?section_id=<?php echo $section['id']; ?>" class="btn-modern btn-primary btn-sm">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                            Manage
                        </a>
                        <button type="button" class="btn-modern btn-ghost btn-sm" 
                                onclick="editSection(<?php echo htmlspecialchars(json_encode($section)); ?>)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                            Edit
                        </button>
                        <?php if ($section['is_active']): ?>
                            <button type="button" class="btn-modern btn-danger btn-sm" 
                                    onclick="confirmDelete(<?php echo $section['id']; ?>, '<?php echo htmlspecialchars($section['section_name']); ?>')">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <line x1="15" y1="9" x2="9" y2="15"/>
                                    <line x1="9" y1="9" x2="15" y2="15"/>
                                </svg>
                                Deactivate
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Table View -->
    <div id="table-view" class="table-view" style="display: none;">
        <div class="table-container-modern">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th>Section</th>
                        <th>Grade</th>
                        <th>School Year</th>
                        <th>Teachers</th>
                        <th>Room</th>
                        <th>Enrollment</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sections)): ?>
                        <tr>
                            <td colspan="8" class="no-data-modern">
                                <div class="no-data-content">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                        <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
                                        <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
                                    </svg>
                                    <p>No sections found matching your criteria</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sections as $section): ?>
                            <tr class="table-row-modern">
                                <td class="section-cell">
                                    <div class="section-info-table">
                                        <div class="section-name-table"><?php echo htmlspecialchars($section['section_name']); ?></div>
                                        <?php if ($section['description']): ?>
                                            <div class="section-description-table"><?php echo htmlspecialchars($section['description']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($section['grade_name']); ?></td>
                                <td><?php echo htmlspecialchars($section['year_label']); ?></td>
                                <td><?php echo $section['teachers_names'] ? htmlspecialchars($section['teachers_names']) : '<span class="text-muted">No Teachers</span>'; ?></td>
                                <td><?php echo $section['room_number'] ? htmlspecialchars($section['room_number']) : '<span class="text-muted">Not Set</span>'; ?></td>
                                <td>
                                    <div class="enrollment-info-table">
                                        <span class="enrollment-text"><?php echo $section['enrolled_count']; ?> students</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge-modern status-<?php echo $section['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $section['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td class="actions-cell">
                                    <div class="actions-group">
                                        <a href="section_manage.php?section_id=<?php echo $section['id']; ?>" class="btn-modern btn-primary btn-sm" title="Manage Students & Teachers">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                                <circle cx="9" cy="7" r="4"/>
                                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                            </svg>
                                        </a>
                                        <button type="button" class="btn-modern btn-ghost btn-sm" 
                                                onclick="editSection(<?php echo htmlspecialchars(json_encode($section)); ?>)"
                                                title="Edit Section">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                            </svg>
                                        </button>
                                        <?php if ($section['is_active']): ?>
                                            <button type="button" class="btn-modern btn-danger btn-sm" 
                                                    onclick="confirmDelete(<?php echo $section['id']; ?>, '<?php echo htmlspecialchars($section['section_name']); ?>')"
                                                    title="Deactivate Section">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <circle cx="12" cy="12" r="10"/>
                                                    <line x1="15" y1="9" x2="9" y2="15"/>
                                                    <line x1="9" y1="9" x2="15" y2="15"/>
                                                </svg>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Section Modal -->
<div id="add-section-modal" class="modal-modern">
    <div class="modal-overlay"></div>
    <div class="modal-container">
        <div class="modal-header-modern">
            <div class="modal-title-section">
                <h3 class="modal-title">Add New Section</h3>
                <p class="modal-subtitle">Create a new class section for a specific grade level and school year</p>
            </div>
            <button type="button" class="modal-close-modern" data-modal-close>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        
        <div class="modal-body-modern">
            <form method="POST" id="add-section-form" class="form-modern">
                <input type="hidden" name="action" value="create_section">
                
                <div class="form-section">
                    <h4 class="form-section-title">Basic Information</h4>
                    <div class="form-grid-modern">
                        <div class="form-group-modern">
                            <label for="section_name" class="form-label">Section Name *</label>
                            <input type="text" id="section_name" name="section_name" class="form-input" required 
                                   placeholder="e.g. Section A, Charity, Hope">
                        </div>
                        
                        <div class="form-group-modern">
                            <label for="grade_level_id" class="form-label">Grade Level *</label>
                            <div class="select-wrapper">
                                <select id="grade_level_id" name="grade_level_id" class="form-select" required>
                                    <option value="">Select Grade Level</option>
                                    <?php foreach ($grade_levels as $grade): ?>
                                        <option value="<?php echo $grade['id']; ?>">
                                            <?php echo htmlspecialchars($grade['grade_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                
                                    <path d="M1 1L6 6L11 1" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                </svg>
                            </div>
                        </div>
                        
                        <div class="form-group-modern">
                            <label for="school_year_id" class="form-label">School Year *</label>
                            <div class="select-wrapper">
                                <select id="school_year_id" name="school_year_id" class="form-select" required>
                                    <option value="">Select School Year</option>
                                    <?php foreach ($school_years as $year): ?>
                                        <option value="<?php echo $year['id']; ?>" <?php echo $year['is_active'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($year['year_label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                               
                                    <path d="M1 1L6 6L11 1" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h4 class="form-section-title">Room Assignment</h4>
                    <div class="form-grid-modern">
                        <div class="form-group-modern">
                            <label for="room_number" class="form-label">Room Number</label>
                            <input type="text" id="room_number" name="room_number" class="form-input" 
                                   placeholder="e.g. Room 101, Lab A">
                            <div class="form-help">Optional - specify classroom location</div>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h4 class="form-section-title">Teacher Assignment</h4>
                    <div class="form-help" style="margin-bottom: 1rem;">Assign one or more teachers to this section. You can designate one as the primary teacher.</div>
                    
                    <div id="teacher-assignments-container">
                        <div class="teacher-assignment-row" data-assignment-index="0">
                            <div class="form-grid-modern" style="grid-template-columns: 1fr auto auto auto; align-items: end; gap: 0.75rem;">
                                <div class="form-group-modern">
                                    <label class="form-label">Teacher</label>
                                    <div class="select-wrapper">
                                        <select name="teacher_assignments[0][teacher_id]" class="form-select teacher-select">
                                            <option value="">Select Teacher</option>
                                            <?php foreach ($teachers as $teacher): ?>
                                                <option value="<?php echo $teacher['id']; ?>">
                                                    <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        
                                            <path d="M1 1L6 6L11 1" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                        </svg>
                                    </div>
                                </div>
                                
                                <div class="form-group-modern">
                                    <label class="form-label">Primary</label>
                                    <div class="checkbox-wrapper">
                                        <input type="radio" name="primary_teacher" value="0" class="primary-radio" id="primary_0">
                                        <label for="primary_0" class="checkbox-label-modern">
                                            <span class="radio-custom"></span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="form-group-modern">
                                    <button type="button" class="btn-modern btn-sm btn-primary add-teacher-btn">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M12 5v14M5 12h14"/>
                                        </svg>
                                        Add
                                    </button>
                                </div>
                                
                                <div class="form-group-modern">
                                    <button type="button" class="btn-modern btn-sm btn-ghost remove-teacher-btn" style="display: none;">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M18 6L6 18M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h4 class="form-section-title">Additional Details</h4>
                    <div class="form-group-modern form-group-full">
                        <label for="description" class="form-label">Description</label>
                        <textarea id="description" name="description" class="form-textarea" rows="3" 
                                  placeholder="Optional description or special notes about this section"></textarea>
                    </div>
                </div>
                
                <div class="form-actions-modern">
                    <button type="submit" class="btn-modern btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>
                        Create Section
                    </button>
                    <button type="button" class="btn-modern btn-ghost" data-modal-close>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Section Modal -->
<div id="edit-section-modal" class="modal-modern">
    <div class="modal-overlay"></div>
    <div class="modal-container">
        <div class="modal-header-modern">
            <div class="modal-title-section">
                <h3 class="modal-title">Edit Section</h3>
                <p class="modal-subtitle">Update section information and settings</p>
            </div>
            <button type="button" class="modal-close-modern" data-modal-close>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        
        <div class="modal-body-modern">
            <form method="POST" id="edit-section-form" class="form-modern">
                <input type="hidden" name="action" value="update_section">
                <input type="hidden" name="section_id" id="edit_section_id">
                
                <div class="form-section">
                    <h4 class="form-section-title">Basic Information</h4>
                    <div class="form-grid-modern">
                        <div class="form-group-modern">
                            <label for="edit_section_name" class="form-label">Section Name *</label>
                            <input type="text" id="edit_section_name" name="section_name" class="form-input" required>
                        </div>
                        
                        <div class="form-group-modern">
                            <label for="edit_grade_level_id" class="form-label">Grade Level *</label>
                            <div class="select-wrapper">
                                <select id="edit_grade_level_id" name="grade_level_id" class="form-select" required>
                                    <option value="">Select Grade Level</option>
                                    <?php foreach ($grade_levels as $grade): ?>
                                        <option value="<?php echo $grade['id']; ?>">
                                            <?php echo htmlspecialchars($grade['grade_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <svg class="select-arrow" width="12" height="8" viewBox="0 0 12 8">
                                    <path d="M1 1L6 6L11 1" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                </svg>
                            </div>
                        </div>
                        
                        <div class="form-group-modern">
                            <label for="edit_school_year_id" class="form-label">School Year *</label>
                            <div class="select-wrapper">
                                <select id="edit_school_year_id" name="school_year_id" class="form-select" required>
                                    <option value="">Select School Year</option>
                                    <?php foreach ($school_years as $year): ?>
                                        <option value="<?php echo $year['id']; ?>">
                                            <?php echo htmlspecialchars($year['year_label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <svg class="select-arrow" width="12" height="8" viewBox="0 0 12 8">
                                    <path d="M1 1L6 6L11 1" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h4 class="form-section-title">Room Assignment</h4>
                    <div class="form-grid-modern">
                        <div class="form-group-modern">
                            <label for="edit_room_number" class="form-label">Room Number</label>
                            <input type="text" id="edit_room_number" name="room_number" class="form-input">
                            <div class="form-help">Optional - specify classroom location</div>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h4 class="form-section-title">Additional Details</h4>
                    <div class="form-group-modern form-group-full">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea id="edit_description" name="description" class="form-textarea" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group-modern form-group-full">
                        <div class="checkbox-group-modern">
                            <label class="checkbox-label-modern">
                                <input type="checkbox" id="edit_is_active" name="is_active" checked class="checkbox-input">
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Active Section</span>
                            </label>
                            <div class="form-help">Deactivating will hide this section from active lists but preserve enrollment history</div>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions-modern">
                    <button type="submit" class="btn-modern btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                            <polyline points="17,21 17,13 7,13 7,21"/>
                            <polyline points="7,3 7,8 15,8"/>
                        </svg>
                        Update Section
                    </button>
                    <button type="button" class="btn-modern btn-ghost" data-modal-close>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-section-modal" class="modal-modern">
    <div class="modal-overlay"></div>
    <div class="modal-container modal-container-small">
        <div class="modal-header-modern">
            <div class="modal-title-section">
                <h3 class="modal-title">Confirm Deactivation</h3>
                <p class="modal-subtitle">This action will deactivate the selected section</p>
            </div>
            <button type="button" class="modal-close-modern" data-modal-close>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        
        <div class="modal-body-modern">
            <div class="confirmation-content">
                <div class="confirmation-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="15" y1="9" x2="9" y2="15"/>
                        <line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>
                </div>
                
                <div class="confirmation-text">
                    <p>Are you sure you want to deactivate section <strong id="delete-section-name"></strong>?</p>
                    <div class="warning-note">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                            <line x1="12" y1="9" x2="12" y2="13"/>
                            <line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                        This action will hide the section from active lists but preserve enrollment history and student records.
                    </div>
                </div>
            </div>
            
            <form method="POST" id="delete-section-form">
                <input type="hidden" name="action" value="delete_section">
                <input type="hidden" name="section_id" id="delete_section_id">
                
                <div class="form-actions-modern">
                    <button type="submit" class="btn-modern btn-danger">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="15" y1="9" x2="9" y2="15"/>
                            <line x1="9" y1="9" x2="15" y2="15"/>
                        </svg>
                        Deactivate Section
                    </button>
                    <button type="button" class="btn-modern btn-ghost" data-modal-close>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Modern Modal and View Management
document.addEventListener('DOMContentLoaded', function() {
    initializeModals();
    initializeViewToggle();
    initializeFilters();
    initializeTeacherAssignments();
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
    
    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.modal-modern.modal-open');
            if (openModal) {
                closeModal(openModal);
            }
        }
    });
}

function openModal(modal) {
    modal.classList.add('modal-open');
    document.body.style.overflow = 'hidden';
}

function closeModal(modal) {
    modal.classList.remove('modal-open');
    document.body.style.overflow = '';
}

function initializeViewToggle() {
    const viewBtns = document.querySelectorAll('.view-btn');
    const gridView = document.getElementById('grid-view');
    const tableView = document.getElementById('table-view');
    
    viewBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const view = this.dataset.view;
            
            // Update button states
            viewBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Toggle views
            if (view === 'grid') {
                gridView.style.display = 'grid';
                tableView.style.display = 'none';
            } else {
                gridView.style.display = 'none';
                tableView.style.display = 'block';
            }
            
            // Save preference
            localStorage.setItem('sectionsView', view);
        });
    });
    
    // Load saved view preference
    const savedView = localStorage.getItem('sectionsView') || 'grid';
    document.querySelector(`[data-view="${savedView}"]`).click();
}

function initializeFilters() {
    const filtersToggle = document.getElementById('filters-toggle-btn');
    const filtersContent = document.getElementById('filters-content');
    
    if (filtersToggle && filtersContent) {
        filtersToggle.addEventListener('click', function() {
            const isHidden = filtersContent.style.display === 'none';
            filtersContent.style.display = isHidden ? 'block' : 'none';
            
            const icon = this.querySelector('svg');
            if (icon) {
                icon.style.transform = isHidden ? 'rotate(180deg)' : 'rotate(0deg)';
            }
        });
        
        // Start with filters visible
        filtersContent.style.display = 'block';
    }
}

function editSection(section) {
    document.getElementById('edit_section_id').value = section.id;
    document.getElementById('edit_section_name').value = section.section_name;
    document.getElementById('edit_grade_level_id').value = section.grade_level_id;
    document.getElementById('edit_school_year_id').value = section.school_year_id;
    document.getElementById('edit_room_number').value = section.room_number || '';
    document.getElementById('edit_description').value = section.description || '';
    document.getElementById('edit_is_active').checked = section.is_active == 1;
    
    const modal = document.getElementById('edit-section-modal');
    openModal(modal);
}

function confirmDelete(sectionId, sectionName) {
    document.getElementById('delete_section_id').value = sectionId;
    document.getElementById('delete-section-name').textContent = sectionName;
    
    const modal = document.getElementById('delete-section-modal');
    openModal(modal);
}

// Progress bar animation
function animateProgressBars() {
    const progressBars = document.querySelectorAll('.progress-fill');
    progressBars.forEach(bar => {
        const percentage = bar.dataset.percentage;
        bar.style.width = '0%';
        setTimeout(() => {
            bar.style.width = percentage + '%';
        }, 100);
    });
}

// Teacher Assignment Management
function initializeTeacherAssignments() {
    let assignmentIndex = 1;
    
    // Add teacher assignment row
    document.addEventListener('click', function(e) {
        if (e.target.closest('.add-teacher-btn')) {
            e.preventDefault();
            addTeacherAssignmentRow(assignmentIndex);
            assignmentIndex++;
        }
    });
    
    // Remove teacher assignment row
    document.addEventListener('click', function(e) {
        if (e.target.closest('.remove-teacher-btn')) {
            e.preventDefault();
            const row = e.target.closest('.teacher-assignment-row');
            if (row) {
                row.remove();
                updateTeacherAssignmentControls();
            }
        }
    });
    
    // Update controls when teachers change
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('teacher-select')) {
            updateTeacherAssignmentControls();
        }
    });
    
    updateTeacherAssignmentControls();
}

function addTeacherAssignmentRow(index) {
    const container = document.getElementById('teacher-assignments-container');
    const teacherOptions = document.querySelector('.teacher-select').innerHTML;
    
    const newRow = document.createElement('div');
    newRow.className = 'teacher-assignment-row';
    newRow.setAttribute('data-assignment-index', index);
    
    newRow.innerHTML = `
        <div class="form-grid-modern" style="grid-template-columns: 1fr auto auto auto; align-items: end; gap: 0.75rem;">
            <div class="form-group-modern">
                <div class="select-wrapper">
                    <select name="teacher_assignments[${index}][teacher_id]" class="form-select teacher-select">
                        ${teacherOptions}
                    </select>
                    <svg class="select-arrow" width="12" height="8" viewBox="0 0 12 8">
                        <path d="M1 1L6 6L11 1" stroke="currentColor" stroke-width="1.5" fill="none"/>
                    </svg>
                </div>
            </div>
            
            <div class="form-group-modern">
                <div class="checkbox-wrapper">
                    <input type="radio" name="primary_teacher" value="${index}" class="primary-radio" id="primary_${index}">
                    <label for="primary_${index}" class="checkbox-label-modern">
                        <span class="radio-custom"></span>
                    </label>
                </div>
            </div>
            
            <div class="form-group-modern">
                <button type="button" class="btn-modern btn-sm btn-primary add-teacher-btn">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 5v14M5 12h14"/>
                    </svg>
                    Add
                </button>
            </div>
            
            <div class="form-group-modern">
                <button type="button" class="btn-modern btn-sm btn-ghost remove-teacher-btn">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6L6 18M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    `;
    
    container.appendChild(newRow);
    updateTeacherAssignmentControls();
}

function updateTeacherAssignmentControls() {
    const rows = document.querySelectorAll('.teacher-assignment-row');
    const hasMultipleRows = rows.length > 1;
    
    rows.forEach((row, index) => {
        const addBtn = row.querySelector('.add-teacher-btn');
        const removeBtn = row.querySelector('.remove-teacher-btn');
        const teacherSelect = row.querySelector('.teacher-select');
        const hasTeacher = teacherSelect.value !== '';
        
        // Show/hide buttons based on position and content
        if (index === rows.length - 1) {
            // Last row: show add button if teacher is selected
            addBtn.style.display = hasTeacher ? 'flex' : 'none';
        } else {
            // Not last row: hide add button
            addBtn.style.display = 'none';
        }
        
        // Show remove button if multiple rows and not the first row
        removeBtn.style.display = hasMultipleRows && index > 0 ? 'flex' : 'none';
    });
}

// Animate on page load
window.addEventListener('load', animateProgressBars);
</script>

<style>
/* Modern Section Management Styles */

/* Page Header Modern */
.page-header-modern {
    background: linear-gradient(135deg, var(--white) 0%, var(--light-blue) 100%);
    border-radius: 20px;
    padding: 2rem 2.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 20px var(--shadow-light);
    border: 1px solid var(--border-gray);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.page-title-section {
    flex: 1;
}

.page-title-wrapper {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 0.5rem;
}

.page-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--dark-blue);
    margin: 0;
    letter-spacing: -0.02em;
}

.page-breadcrumb {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    color: var(--gray);
}

.breadcrumb-item.current {
    color: var(--primary-blue);
    font-weight: 600;
}

.breadcrumb-separator {
    color: var(--border-gray);
}

.page-description {
    font-size: 1.1rem;
    color: var(--gray);
    margin: 0;
    line-height: 1.5;
}

.page-actions {
    display: flex;
    gap: 1rem;
}

/* Modern Buttons */
.btn-modern {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 10px;
    font-size: 0.95rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.btn-modern.btn-primary {
    background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
    color: var(--white);
    box-shadow: 0 4px 12px rgba(46, 134, 171, 0.3);
}

.btn-modern.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(46, 134, 171, 0.4);
}

.btn-modern.btn-ghost {
    background: transparent;
    color: var(--gray);
    border: 2px solid var(--border-gray);
}

.btn-modern.btn-ghost:hover {
    background: var(--light-gray);
    border-color: var(--primary-blue);
    color: var(--primary-blue);
}

.btn-modern.btn-danger {
    background: linear-gradient(135deg, var(--danger) 0%, #c0392b 100%);
    color: var(--white);
}

.btn-modern.btn-danger:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
}

.btn-modern.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.85rem;
}

/* Statistics Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--white);
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 2px 12px var(--shadow-light);
    border: 1px solid var(--border-gray);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px var(--shadow-medium);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-icon-primary {
    background: linear-gradient(135deg, var(--primary-blue), var(--accent-blue));
    color: var(--white);
}

.stat-icon-success {
    background: linear-gradient(135deg, var(--success), #2ecc71);
    color: var(--white);
}

.stat-icon-info {
    background: linear-gradient(135deg, var(--accent-blue), #3498db);
    color: var(--white);
}

.stat-icon-warning {
    background: linear-gradient(135deg, var(--warning), #e67e22);
    color: var(--white);
}

.stat-content {
    flex: 1;
}

.stat-number {
    font-size: 2rem;
    font-weight: 800;
    color: var(--dark-blue);
    line-height: 1;
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.9rem;
    color: var(--gray);
    font-weight: 500;
}

/* Alert Modern */
.alert-modern {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    font-weight: 500;
}

.alert-success {
    background: #e8f5e8;
    color: #2e7d32;
    border: 1px solid #c8e6c9;
}

.alert-error {
    background: #ffebee;
    color: #c62828;
    border: 1px solid #ffcdd2;
}

/* Filters Modern */
.filters-modern {
    background: var(--white);
    border-radius: 16px;
    box-shadow: 0 2px 12px var(--shadow-light);
    border: 1px solid var(--border-gray);
    margin-bottom: 2rem;
    overflow: hidden;
}

.filters-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 2rem 1rem;
    border-bottom: 1px solid var(--border-gray);
}

.filters-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--dark-blue);
    margin: 0;
}

.filters-content {
    padding: 1.5rem 2rem 2rem;
}

.filters-form-modern {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.filters-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.filter-group-modern {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-label {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--black);
}

.select-wrapper {
    position: relative;
}

.select-modern {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--border-gray);
    border-radius: 10px;
    font-size: 0.95rem;
    background: var(--white);
    color: var(--black);
    appearance: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.select-modern:focus {
    outline: none;
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 3px rgba(46, 134, 171, 0.1);
}

.select-arrow {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    color: var(--gray);
}

.filters-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-start;
}

/* Content Wrapper */
.content-wrapper {
    background: var(--white);
    border-radius: 16px;
    box-shadow: 0 2px 12px var(--shadow-light);
    border: 1px solid var(--border-gray);
    overflow: hidden;
}

/* View Controls */
.view-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 2rem;
    border-bottom: 1px solid var(--border-gray);
    background: var(--light-gray);
}

.view-toggle {
    display: flex;
    gap: 0.5rem;
    background: var(--white);
    border-radius: 10px;
    padding: 0.25rem;
    border: 1px solid var(--border-gray);
}

.view-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border: none;
    background: transparent;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--gray);
    cursor: pointer;
    transition: all 0.2s ease;
}

.view-btn.active {
    background: var(--primary-blue);
    color: var(--white);
}

.results-count {
    font-size: 0.9rem;
    color: var(--gray);
    font-weight: 500;
}

/* Sections Grid */
.sections-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    padding: 2rem;
}

.section-card {
    background: var(--white);
    border: 1px solid var(--border-gray);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.2s ease;
}

.section-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px var(--shadow-medium);
    border-color: var(--primary-blue);
}

.section-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 1.5rem 1.5rem 0;
}

.section-name h3 {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--dark-blue);
    margin: 0 0 0.25rem 0;
}

.section-grade {
    font-size: 0.85rem;
    color: var(--gray);
    font-weight: 500;
}

.status-badge-modern {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-active {
    background: #e8f5e8;
    color: #2e7d32;
}

.status-inactive {
    background: #ffebee;
    color: #c62828;
}

.section-card-body {
    padding: 1rem 1.5rem;
}

.section-description {
    font-size: 0.9rem;
    color: var(--gray);
    margin-bottom: 1rem;
    line-height: 1.4;
}

.section-details {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    color: var(--black);
}

.detail-item svg {
    color: var(--primary-blue);
    flex-shrink: 0;
}

.enrollment-progress {
    margin-top: 1rem;
}

.enrollment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.enrollment-label {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--black);
}

.enrollment-count {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--primary-blue);
}

.progress-bar {
    height: 8px;
    background: var(--border-gray);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 0.5rem;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary-blue), var(--accent-blue));
    border-radius: 4px;
    transition: width 0.5s ease;
}

.enrollment-percentage {
    font-size: 0.8rem;
    color: var(--gray);
    text-align: center;
}

.section-card-actions {
    display: flex;
    gap: 0.5rem;
    padding: 0 1.5rem 1.5rem;
}

/* Table View */
.table-view {
    padding: 2rem;
}

.table-container-modern {
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid var(--border-gray);
}

.table-modern {
    width: 100%;
    border-collapse: collapse;
    background: var(--white);
}

.table-modern thead {
    background: var(--light-gray);
}

.table-modern th {
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--black);
    border-bottom: 1px solid var(--border-gray);
    font-size: 0.9rem;
}

.table-modern td {
    padding: 1rem;
    border-bottom: 1px solid var(--border-gray);
    font-size: 0.9rem;
}

.table-row-modern:hover {
    background: var(--light-blue);
}

.section-info-table {
    display: flex;
    flex-direction: column;
}

.section-name-table {
    font-weight: 600;
    color: var(--dark-blue);
    margin-bottom: 0.25rem;
}

.section-description-table {
    font-size: 0.8rem;
    color: var(--gray);
}

.enrollment-info-table {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.enrollment-text {
    font-weight: 600;
    color: var(--black);
}

.capacity-bar-small {
    width: 80px;
    height: 4px;
    background: var(--border-gray);
    border-radius: 2px;
    overflow: hidden;
}

.capacity-fill-small {
    height: 100%;
    background: var(--primary-blue);
    border-radius: 2px;
}

.actions-cell {
    width: 160px;
}

.actions-group {
    display: flex;
    gap: 0.25rem;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--gray);
}

.empty-state-icon {
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state-content h3 {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--black);
    margin-bottom: 0.5rem;
}

.empty-state-content p {
    margin-bottom: 1.5rem;
}

.no-data-modern {
    text-align: center;
    padding: 3rem;
}

.no-data-content {
    color: var(--gray);
}

.no-data-content svg {
    margin-bottom: 1rem;
    opacity: 0.5;
}

/* Modal Modern */
.modal-modern {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1060;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.modal-modern.modal-open {
    display: flex;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
}

.modal-container {
    position: relative;
    background: var(--white);
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    width: 100%;
    max-width: 800px;
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.modal-container-small {
    max-width: 500px;
}

.modal-header-modern {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 2rem 2rem 1rem;
    border-bottom: 1px solid var(--border-gray);
}

.modal-title-section {
    flex: 1;
}

.modal-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--dark-blue);
    margin: 0 0 0.25rem 0;
}

.modal-subtitle {
    font-size: 0.9rem;
    color: var(--gray);
    margin: 0;
}

.modal-close-modern {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 8px;
    color: var(--gray);
    transition: all 0.2s ease;
}

.modal-close-modern:hover {
    background: var(--light-gray);
    color: var(--black);
}

.modal-body-modern {
    padding: 2rem;
    overflow-y: auto;
    flex: 1;
}

/* Form Modern */
.form-modern {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.form-section {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.form-section-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark-blue);
    margin: 0;
    border-bottom: 1px solid var(--border-gray);
    padding-bottom: 0.5rem;
}

.form-grid-modern {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.form-group-modern {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-group-full {
    grid-column: 1 / -1;
}

.form-label {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--black);
}

.form-input, .form-select, .form-textarea {
    padding: 0.75rem 1rem;
    border: 2px solid var(--border-gray);
    border-radius: 8px;
    font-size: 0.95rem;
    transition: all 0.2s ease;
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 3px rgba(46, 134, 171, 0.1);
}

.form-textarea {
    resize: vertical;
    min-height: 100px;
}

.form-help {
    font-size: 0.8rem;
    color: var(--gray);
}

.checkbox-group-modern {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.checkbox-label-modern {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    font-weight: 500;
}

.checkbox-input {
    margin: 0;
}

.checkbox-custom {
    width: 20px;
    height: 20px;
    border: 2px solid var(--border-gray);
    border-radius: 4px;
    position: relative;
    transition: all 0.2s ease;
}

.checkbox-input:checked + .checkbox-custom {
    background: var(--primary-blue);
    border-color: var(--primary-blue);
}

.checkbox-input:checked + .checkbox-custom::after {
    content: '✓';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 12px;
    font-weight: bold;
}

.checkbox-text {
    color: var(--black);
}

/* Teacher Assignment Styles */
.teacher-assignment-row {
    margin-bottom: 1rem;
    padding: 1rem;
    background: var(--light-gray);
    border-radius: 8px;
    border: 1px solid var(--border-gray);
}

.teacher-assignment-row:first-child {
    background: transparent;
    border: none;
    padding: 0;
}

.checkbox-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
}

.radio-custom {
    width: 20px;
    height: 20px;
    border: 2px solid var(--border-gray);
    border-radius: 50%;
    position: relative;
    transition: all 0.2s ease;
    background: var(--white);
}

.primary-radio {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.primary-radio:checked + .checkbox-label-modern .radio-custom {
    background: var(--primary-blue);
    border-color: var(--primary-blue);
}

.primary-radio:checked + .checkbox-label-modern .radio-custom::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 8px;
    height: 8px;
    background: white;
    border-radius: 50%;
}

.form-actions-modern {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    padding-top: 1rem;
    border-top: 1px solid var(--border-gray);
}

/* Confirmation Content */
.confirmation-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 1rem;
}

.confirmation-icon {
    color: var(--danger);
}

.confirmation-text p {
    font-size: 1.1rem;
    color: var(--black);
    margin-bottom: 1rem;
}

.warning-note {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 8px;
    padding: 1rem;
    font-size: 0.9rem;
    color: #856404;
    text-align: left;
}

.warning-note svg {
    color: var(--warning);
    flex-shrink: 0;
    margin-top: 0.1rem;
}

.text-muted {
    color: var(--gray);
    font-style: italic;
}

/* Responsive Design */
@media (max-width: 768px) {
    .page-header-modern {
        flex-direction: column;
        gap: 1rem;
        padding: 1.5rem;
    }
    
    .page-title {
        font-size: 2rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .filters-row {
        grid-template-columns: 1fr;
    }
    
    .sections-grid {
        grid-template-columns: 1fr;
        padding: 1rem;
    }
    
    .view-controls {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .modal-container {
        margin: 1rem;
        max-width: calc(100vw - 2rem);
    }
    
    .modal-body-modern {
        padding: 1rem;
    }
    
    .form-grid-modern {
        grid-template-columns: 1fr;
    }
    
    .form-actions-modern {
        flex-direction: column;
    }
    
    .table-view {
        padding: 1rem;
        overflow-x: auto;
    }
}

@media (max-width: 480px) {
    .page-title {
        font-size: 1.5rem;
    }
    
    .sections-grid {
        padding: 0.5rem;
    }
    
    .section-card-actions {
        flex-direction: column;
    }
}
</style>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
