<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth_check.php';
require_once '../config/database.php';
require_once '../classes/User.php';

// Check if user is registrar
if ($_SESSION['role'] !== 'registrar') {
    header('Location: ../index.php');
    exit();
}

$database = new Database();
$conn = $database->connect();
$user = new User($conn);

$page_title = "Student Records";
$base_url = "../";

// Handle status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    try {
        $student_id = $_POST['student_id'];
        $new_status = $_POST['new_status'];
        
        // Check if status is "Transferred" and handle transfer details
        if ($new_status === 'Transferred') {
            $transferred_to_school = $_POST['transferred_to_school'] ?? null;
            $transfer_date = $_POST['transfer_date'] ?? null;
            $transfer_reason = $_POST['transfer_reason'] ?? null;
            
            // Update with transfer details and approval info
            $update_stmt = $conn->prepare(
                "UPDATE students 
                 SET enrollment_status = ?, 
                     transferred_to_school = ?, 
                     transfer_date = ?, 
                     transfer_reason = ?,
                     transfer_approved_by = ?,
                     transfer_approved_at = NOW()
                 WHERE id = ?"
            );
            $update_stmt->execute([
                $new_status, 
                $transferred_to_school, 
                $transfer_date, 
                $transfer_reason,
                $_SESSION['user_id'],
                $student_id
            ]);
            
            $audit_details = "Changed enrollment status to: $new_status";
            if ($transferred_to_school) {
                $audit_details .= " | Transferred to: $transferred_to_school";
            }
            if ($transfer_date) {
                $audit_details .= " | Transfer Date: $transfer_date";
            }
        } else {
            // Regular status update without transfer details
            $update_stmt = $conn->prepare("UPDATE students SET enrollment_status = ? WHERE id = ?");
            $update_stmt->execute([$new_status, $student_id]);
            
            $audit_details = "Changed enrollment status to: $new_status";
        }
        
        // Log the action
        $user->logAudit($_SESSION['user_id'], 'Student Status Change', 'students', $student_id, $audit_details);
        
        $_SESSION['success_message'] = 'Student status updated successfully!';
        header('Location: student_records.php');
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error updating status: ' . $e->getMessage();
    }
}

// Handle search and filters
$search = $_GET['search'] ?? '';
$grade_filter = $_GET['grade_filter'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';
$type_filter = $_GET['type_filter'] ?? '';
$school_year_filter = $_GET['school_year_filter'] ?? '';

// Pagination
$page = max(1, $_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$where_conditions = ["s.is_active = 1"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id LIKE ? OR s.lrn LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($grade_filter)) {
    $where_conditions[] = "s.current_grade_level_id = ?";
    $params[] = $grade_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "s.enrollment_status = ?";
    $params[] = $status_filter;
}

if (!empty($type_filter)) {
    $where_conditions[] = "s.student_type = ?";
    $params[] = $type_filter;
}

if (!empty($school_year_filter)) {
    $where_conditions[] = "s.current_school_year_id = ?";
    $params[] = $school_year_filter;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM students s $where_clause";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total_students = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_students / $limit);

// Get students
$sql = "SELECT s.*, 
               gl.grade_name,
               sec.section_name,
               sy.year_label,
               u.email,
               approver.username as approved_by_username,
               CONCAT(s.first_name, ' ', IFNULL(CONCAT(s.middle_name, ' '), ''), s.last_name) as full_name
        FROM students s 
        LEFT JOIN grade_levels gl ON s.current_grade_level_id = gl.id
        LEFT JOIN sections sec ON s.current_section_id = sec.id 
        LEFT JOIN school_years sy ON s.current_school_year_id = sy.id
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN users approver ON s.transfer_approved_by = approver.id
        $where_clause 
        ORDER BY s.created_at DESC 
        LIMIT $limit OFFSET $offset";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get grade levels for filter
$grade_stmt = $conn->prepare("SELECT * FROM grade_levels WHERE is_active = 1 ORDER BY grade_order");
$grade_stmt->execute();
$grade_levels = $grade_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get school years for filter
$school_year_stmt = $conn->prepare("SELECT * FROM school_years ORDER BY is_active DESC, year_label DESC");
$school_year_stmt->execute();
$school_years = $school_year_stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="main-wrapper">
    <div class="content-wrapper" style="max-width: 1400px; margin: 0 auto; padding: 2rem 1rem;">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; border-left: 4px solid #28a745;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; border-left: 4px solid #dc3545;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        <div class="content-header">
            <div class="header-left">
                <h1><i class="fas fa-users"></i> Student Records</h1>
                <p>Manage and view student information</p>
                <div class="header-stats">
                    <div class="stat-item">
                        <i class="fas fa-graduation-cap"></i>
                        <span>Total Students: <strong><?php echo $total_students; ?></strong></span>
                    </div>
                </div>
            </div>
            <div class="header-actions">
                <a href="student_registration.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Register New Student
                </a>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label for="search">Search Students</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, Student ID, or LRN">
                </div>
                
                <div class="filter-group">
                    <label for="grade_filter">Grade Level</label>
                    <select id="grade_filter" name="grade_filter">
                        <option value="">All Grades</option>
                        <?php foreach ($grade_levels as $grade): ?>
                            <option value="<?php echo $grade['id']; ?>" <?php echo $grade_filter == $grade['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($grade['grade_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="status_filter">Status</label>
                    <select id="status_filter" name="status_filter">
                        <option value="">All Status</option>
                        <option value="Pending Payment" <?php echo $status_filter === 'Pending Payment' ? 'selected' : ''; ?>>Pending Payment</option>
                        <option value="Enrolled" <?php echo $status_filter === 'Enrolled' ? 'selected' : ''; ?>>Enrolled</option>
                        <option value="Suspended" <?php echo $status_filter === 'Suspended' ? 'selected' : ''; ?>>Suspended</option>
                        <option value="Dropped" <?php echo $status_filter === 'Dropped' ? 'selected' : ''; ?>>Dropped</option>
                        <option value="Transferred" <?php echo $status_filter === 'Transferred' ? 'selected' : ''; ?>>Transferred</option>
                        <option value="Graduated" <?php echo $status_filter === 'Graduated' ? 'selected' : ''; ?>>Graduated</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="type_filter">Type</label>
                    <select id="type_filter" name="type_filter">
                        <option value="">All Types</option>
                        <option value="New" <?php echo $type_filter === 'New' ? 'selected' : ''; ?>>New</option>
                        <option value="Transfer" <?php echo $type_filter === 'Transfer' ? 'selected' : ''; ?>>Transfer</option>
                        <option value="Continuing" <?php echo $type_filter === 'Continuing' ? 'selected' : ''; ?>>Continuing</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="school_year_filter">School Year</label>
                    <select id="school_year_filter" name="school_year_filter">
                        <option value="">All School Years</option>
                        <?php foreach ($school_years as $year): ?>
                            <option value="<?php echo $year['id']; ?>" <?php echo $school_year_filter == $year['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($year['year_label']); ?>
                                <?php echo ($year['is_active']) ? ' (Current)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="student_records.php" class="btn btn-secondary">
                        <i class="fas fa-refresh"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Results Summary -->
        <div class="results-summary">
            <span class="total-count"><?php echo $total_students; ?> student(s) found</span>
            <?php if ($total_pages > 1): ?>
                <span class="page-info">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
            <?php endif; ?>
        </div>

        <!-- Students Table -->
        <div class="table-container">
            <?php if (empty($students)): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No students found</h3>
                    <p>No students match your search criteria.</p>
                    
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student Info</th>
                            <th>IDs</th>
                            <th>Grade & Section</th>
                            <th>School Year</th>
                            <th>Status</th>
                            <th>Type</th>
                            <th>Register Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td class="student-info">
                                    <div class="student-avatar">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="student-details">
                                        <strong><?php echo htmlspecialchars($student['full_name']); ?></strong>
                                        <div class="student-email"><?php echo htmlspecialchars($student['email'] ?? ''); ?></div>
                                        <div class="student-birth">Born: <?php echo date('M j, Y', strtotime($student['date_of_birth'])); ?></div>
                                    </div>
                                </td>
                                <td class="ids-info">
                                    <div class="id-item">
                                        <small>Student ID:</small>
                                        <strong><?php echo htmlspecialchars($student['student_id']); ?></strong>
                                    </div>
                                    <div class="id-item">
                                        <small>LRN:</small>
                                        <strong><?php echo htmlspecialchars($student['lrn'] ?? ''); ?></strong>
                                    </div>
                                </td>
                                <td class="academic-info">
                                    <?php if ($student['grade_name']): ?>
                                        <div class="grade-badge"><?php echo htmlspecialchars($student['grade_name']); ?></div>
                                        <?php if ($student['section_name']): ?>
                                            <div class="section-info"><?php echo htmlspecialchars($student['section_name']); ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="school-year-info">
                                    <?php if ($student['year_label']): ?>
                                        <span class="school-year-badge">
                                            <?php echo htmlspecialchars($student['year_label']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $student['enrollment_status'])); ?>">
                                        <?php echo htmlspecialchars($student['enrollment_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="type-badge type-<?php echo strtolower($student['student_type']); ?>">
                                        <?php echo htmlspecialchars($student['student_type']); ?>
                                    </span>
                                </td>
                                <td class="date-info-cell">
                                    <div class="date-info">
                                        <span class="date-text"><?php echo date('M j, Y', strtotime($student['created_at'])); ?></span>
                                        <span class="time-text"><?php echo date('g:i A', strtotime($student['created_at'])); ?></span>
                                    </div>
                                </td>
                                <td class="actions">
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-warning" 
                                                onclick='changeStatus(<?php echo $student['id']; ?>, "<?php echo htmlspecialchars($student['enrollment_status'], ENT_QUOTES); ?>", <?php echo htmlspecialchars(json_encode([
                                                    'transferred_to_school' => $student['transferred_to_school'],
                                                    'transfer_date' => $student['transfer_date'],
                                                    'transfer_reason' => $student['transfer_reason'],
                                                    'approved_by_username' => $student['approved_by_username'],
                                                    'transfer_approved_at' => $student['transfer_approved_at']
                                                ]), ENT_QUOTES); ?>)' 
                                                title="Change Status">
                                            <i class="fas fa-exchange-alt"></i>
                                        </button>
                                        <button class="btn btn-sm btn-primary" onclick="editStudent(<?php echo $student['id']; ?>)" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-secondary" onclick="printRecord(<?php echo $student['id']; ?>)" title="Print Record">
                                            <i class="fas fa-print"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-wrapper">
                <nav class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                           class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-link">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.filters-card {
    background: var(--white);
    border-radius: 20px;
    padding: 2.5rem;
    margin-bottom: 2.5rem;
    box-shadow: 0 8px 32px rgba(46, 134, 171, 0.12);
    border: 1px solid var(--border-gray);
}

.filters-form {
    display: grid;
    grid-template-columns: 2.5fr 1.2fr 1fr 1fr 1.2fr auto;
    gap: 1.5rem;
    align-items: end;
}

.filter-group label {
    display: block;
    margin-bottom: 0.75rem;
    font-weight: 600;
    color: var(--dark-blue);
    font-size: 0.95rem;
}

.filter-group input,
.filter-group select {
    width: 100%;
    padding: 1rem;
    border: 2px solid var(--border-gray);
    border-radius: 12px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    background: var(--white);
    color: var(--black);
}

.filter-group input:focus,
.filter-group select:focus {
    outline: none;
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 4px rgba(46, 134, 171, 0.1);
    transform: translateY(-1px);
}

.filter-actions {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.results-summary {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding: 1.25rem 1.5rem;
    background: var(--light-blue);
    border-radius: 15px;
    border-left: 4px solid var(--primary-blue);
}

.total-count {
    font-weight: 700;
    color: var(--dark-blue);
    font-size: 1.1rem;
}

.page-info {
    color: var(--gray);
    font-weight: 500;
}

.table-container {
    background: var(--white);
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(46, 134, 171, 0.12);
    border: 1px solid var(--border-gray);
    margin-bottom: 2rem;
    width: 100%;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
    table-layout: fixed; /* Fixed layout for consistent column widths */
}

/* Responsive column widths */
.data-table thead th:nth-child(1) { width: 20%; } /* Student Info */
.data-table thead th:nth-child(2) { width: 13%; } /* IDs */
.data-table thead th:nth-child(3) { width: 12%; } /* Grade & Section */
.data-table thead th:nth-child(4) { width: 10%; } /* School Year */
.data-table thead th:nth-child(5) { width: 14%; } /* Status - increased width */
.data-table thead th:nth-child(6) { width: 8%; }  /* Type */
.data-table thead th:nth-child(7) { width: 11%; } /* Registration Date */
.data-table thead th:nth-child(8) { width: 12%; } /* Actions */

.data-table thead th {
    background: linear-gradient(135deg, var(--dark-blue), var(--primary-blue));
    color: var(--white);
    padding: 1.5rem 1.25rem;
    text-align: left;
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
}

.data-table tbody tr {
    border-bottom: 1px solid var(--border-gray);
    transition: all 0.3s ease;
}

.data-table tbody tr:hover {
    background: var(--light-blue);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(46, 134, 171, 0.1);
}

.data-table tbody tr:last-child {
    border-bottom: none;
}

.data-table tbody td {
    padding: 1.2rem 0.8rem;
    vertical-align: middle;
    border: none;
    word-wrap: break-word;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.4;
}

.student-info {
    display: flex;
    align-items: flex-start;
    gap: 0.8rem;
}

.student-avatar {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, var(--primary-blue), var(--accent-blue));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.1rem;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(46, 134, 171, 0.3);
}

.student-details {
    flex: 1;
    overflow: hidden;
}

.student-details strong {
    display: block;
    margin-bottom: 0.4rem;
    color: var(--dark-blue);
    font-weight: 600;
    font-size: 0.95rem;
    line-height: 1.3;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.student-email,
.student-birth {
    font-size: 0.8rem;
    color: var(--gray);
    line-height: 1.3;
    margin-bottom: 0.2rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.student-email {
    color: var(--primary-blue);
    font-weight: 500;
}

.gender-indicator {
    color: var(--dark-blue);
    font-weight: 600;
    font-size: 0.75rem;
    margin-top: 0.2rem;
}

.ids-info {
    padding: 0.5rem;
}

.ids-info .id-item {
    margin-bottom: 0.6rem;
    padding: 0.4rem 0.6rem;
    background: var(--light-gray);
    border-radius: 6px;
    border-left: 3px solid var(--primary-blue);
}

.ids-info .id-item:last-child {
    margin-bottom: 0;
}

.ids-info small {
    display: block;
    color: var(--gray);
    font-size: 0.7rem;
    font-weight: 600;
    margin-bottom: 0.2rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.ids-info strong {
    color: var(--dark-blue);
    font-weight: 700;
    font-size: 0.8rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: block;
    line-height: 1.2;
}

.academic-info {
    text-align: center;
    padding: 0.5rem;
}

.grade-badge {
    background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
    color: white;
    padding: 0.4rem 0.8rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-bottom: 0.4rem;
    display: inline-block;
    box-shadow: 0 2px 8px rgba(46, 134, 171, 0.3);
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.section-info {
    font-size: 0.75rem;
    color: var(--gray);
    font-weight: 600;
    padding: 0.2rem 0.6rem;
    background: var(--light-gray);
    border-radius: 10px;
    display: inline-block;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    border: 1px solid var(--border-gray);
}

.status-badge {
    padding: 0.5rem 0.8rem;
    border-radius: 15px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.2px;
    display: inline-block;
    text-align: center;
    width: auto;
    min-width: 90px;
    max-width: 140px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.2;
}

.status-pending-payment { 
    background: linear-gradient(135deg, #F39C12, #E67E22); 
    color: white;
    box-shadow: 0 2px 8px rgba(243, 156, 18, 0.3);
}
.status-enrolled { 
    background: linear-gradient(135deg, #27AE60, #2ECC71); 
    color: white;
    box-shadow: 0 2px 8px rgba(39, 174, 96, 0.3);
}
.status-suspended { 
    background: linear-gradient(135deg, #9B59B6, #8E44AD); 
    color: white;
    box-shadow: 0 2px 8px rgba(155, 89, 182, 0.3);
}
.status-dropped { 
    background: linear-gradient(135deg, #E74C3C, #C0392B); 
    color: white;
    box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
}
.status-transferred { 
    background: linear-gradient(135deg, #34495E, #2C3E50); 
    color: white;
    box-shadow: 0 2px 8px rgba(52, 73, 94, 0.3);
}
.status-graduated { 
    background: linear-gradient(135deg, #3498DB, #2980B9); 
    color: white;
    box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
}

.type-badge {
    padding: 0.4rem 0.6rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    display: inline-block;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.type-new { 
    background: linear-gradient(135deg, #3498DB, #5DADE2); 
    color: white;
    box-shadow: 0 2px 6px rgba(52, 152, 219, 0.2);
}
.type-transfer { 
    background: linear-gradient(135deg, #FF8F00, #FFB74D); 
    color: white;
    box-shadow: 0 2px 6px rgba(255, 143, 0, 0.2);
}
.type-continuing { 
    background: linear-gradient(135deg, #8E24AA, #AB47BC); 
    color: white;
    box-shadow: 0 2px 6px rgba(142, 36, 170, 0.2);
}

.school-year-badge {
    background: linear-gradient(135deg, var(--dark-blue), var(--primary-blue));
    color: white;
    padding: 0.5rem 0.7rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-block;
    text-align: center;
    box-shadow: 0 3px 10px rgba(46, 134, 171, 0.3);
    text-transform: uppercase;
    letter-spacing: 0.2px;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.school-year-info {
    text-align: center;
    padding: 0.5rem;
}

.text-muted {
    color: var(--gray);
    font-style: italic;
    font-size: 0.9rem;
    padding: 0.5rem;
    background: var(--light-gray);
    border-radius: 8px;
    text-align: center;
}

.date-info-cell {
    text-align: center;
    padding: 0.5rem;
}

.date-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.2rem;
}

.date-text {
    color: var(--dark-blue);
    font-weight: 600;
    font-size: 0.8rem;
    line-height: 1.2;
}

.time-text {
    color: var(--gray);
    font-size: 0.7rem;
    font-weight: 500;
}

.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    align-items: center;
    padding: 0.5rem;
}

.btn-sm {
    padding: 0.5rem;
    border-radius: 8px;
    font-size: 0.85rem;
    width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.btn-sm:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
    color: white;
}

.btn-secondary {
    background: linear-gradient(135deg, var(--gray), #34495E);
    color: white;
}

.btn-warning {
    background: linear-gradient(135deg, #F39C12, #E67E22);
    color: white;
}

.empty-state {
    text-align: center;
    padding: 5rem 2rem;
    color: var(--gray);
}

.empty-state i {
    font-size: 5rem;
    margin-bottom: 1.5rem;
    color: var(--border-gray);
}

.empty-state h3 {
    color: var(--dark-blue);
    margin-bottom: 1rem;
    font-size: 1.5rem;
}

.pagination-wrapper {
    margin-top: 2rem;
    display: flex;
    justify-content: center;
}

.pagination {
    display: flex;
    gap: 0.5rem;
}

.page-link {
    padding: 0.75rem 1.25rem;
    border: 2px solid var(--border-gray);
    border-radius: 12px;
    text-decoration: none;
    color: var(--black);
    transition: all 0.3s ease;
    font-weight: 500;
}

.page-link:hover,
.page-link.active {
    background: var(--primary-blue);
    color: white;
    border-color: var(--primary-blue);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(46, 134, 171, 0.3);
}

/* Responsive Design */
@media (max-width: 1200px) {
    .filters-form {
        grid-template-columns: 2fr 1fr 1fr 1fr auto;
        gap: 1rem;
    }
    
    .filter-group:nth-child(5) {
        grid-column: 1 / -2;
    }
    
    .content-wrapper {
        padding: 1rem 0.5rem;
    }
    
    .data-table {
        font-size: 0.85rem;
    }
    
    .data-table tbody td {
        padding: 1rem 0.6rem;
    }
}

@media (max-width: 968px) {
    .filters-form {
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
    
    .filter-group:first-child {
        grid-column: 1 / -1;
    }
    
    .filter-actions {
        grid-column: 1 / -1;
        flex-direction: row;
        justify-content: center;
    }
    
    .data-table {
        font-size: 0.8rem;
    }
    
    .data-table tbody td {
        padding: 0.8rem 0.4rem;
    }
    
    .student-avatar {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
    
    .btn-sm {
        width: 30px;
        height: 30px;
        font-size: 0.8rem;
    }
}

@media (max-width: 768px) {
    .filters-card {
        padding: 1.5rem;
    }
    
    .filters-form {
        grid-template-columns: 1fr;
    }
    
    .results-summary {
        flex-direction: column;
        gap: 0.75rem;
        align-items: flex-start;
        text-align: left;
    }
    
    .content-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    .header-actions {
        width: 100%;
        justify-content: center;
    }
    
    /* Make table responsive without horizontal scroll */
    .data-table thead th:nth-child(1) { width: 26%; } /* Student Info - larger on mobile */
    .data-table thead th:nth-child(2) { width: 13%; } /* IDs */
    .data-table thead th:nth-child(3) { width: 11%; } /* Grade & Section */
    .data-table thead th:nth-child(4) { width: 9%; }  /* School Year */
    .data-table thead th:nth-child(5) { width: 13%; } /* Status - wider on mobile too */
    .data-table thead th:nth-child(6) { width: 7%; }  /* Type */
    .data-table thead th:nth-child(7) { width: 10%; } /* Registration Date */
    .data-table thead th:nth-child(8) { width: 11%; } /* Actions */
}

/* Content Header Enhancements */
.content-header {
    background: linear-gradient(135deg, var(--light-blue), var(--white));
    border-radius: 20px;
    padding: 2.5rem;
    margin-bottom: 2.5rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    box-shadow: 0 8px 32px rgba(46, 134, 171, 0.12);
    border: 1px solid var(--border-gray);
}

.header-left h1 {
    color: var(--dark-blue);
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.header-left h1 i {
    background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.header-left p {
    color: var(--gray);
    font-size: 1.2rem;
    margin-bottom: 1rem;
    font-weight: 500;
}

.header-stats {
    display: flex;
    gap: 1.5rem;
    margin-top: 1rem;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1.25rem;
    background: var(--white);
    border-radius: 15px;
    box-shadow: 0 4px 12px rgba(46, 134, 171, 0.1);
    border: 1px solid var(--border-gray);
}

.stat-item i {
    color: var(--primary-blue);
    font-size: 1.25rem;
}

.stat-item span {
    color: var(--dark-blue);
    font-weight: 500;
}

.stat-item strong {
    color: var(--primary-blue);
    font-weight: 700;
}

.header-actions {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 2rem;
    border: none;
    border-radius: 15px;
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
    white-space: nowrap;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.25);
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
    color: var(--white);
}

.btn-primary:hover {
    background: linear-gradient(135deg, var(--dark-blue), #0F3A5F);
}

.btn-secondary {
    background: linear-gradient(135deg, var(--gray), #34495E);
    color: var(--white);
}

.btn-secondary:hover {
    background: linear-gradient(135deg, #34495E, var(--black));
}

@media (max-width: 768px) {
    .filters-form {
        grid-template-columns: 1fr;
    }
    
    .filter-actions {
        justify-content: center;
    }
    
    .results-summary {
        flex-direction: column;
        gap: 0.5rem;
        align-items: flex-start;
    }
}
</style>

<!-- Change Status Modal -->
<div id="changeStatusModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 2.5rem; border-radius: 20px; width: 90%; max-width: 500px; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
        <h3 style="margin-top: 0; color: var(--dark-blue); font-size: 1.5rem; margin-bottom: 1.5rem;">
            <i class="fas fa-exchange-alt"></i> Change Student Status
        </h3>
        <form method="POST" id="changeStatusForm">
            <input type="hidden" name="change_status" value="1">
            <input type="hidden" name="student_id" id="change_student_id">
            
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.75rem; color: var(--dark-blue); font-weight: 600;">Current Status:</label>
                <div style="padding: 1rem; background: var(--light-blue); border-radius: 10px; font-weight: 600; color: var(--primary-blue);" id="current_status_display"></div>
            </div>
            
            <!-- Current Transfer Details (shown when already transferred) -->
            <div id="currentTransferDetailsSection" style="display: none; margin-bottom: 1.5rem; padding: 1.5rem; background: #fff3cd; border-radius: 12px; border-left: 4px solid #ffc107;">
                <h4 style="margin: 0 0 1rem 0; color: #856404; font-size: 1.1rem;">
                    <i class="fas fa-info-circle"></i> Current Transfer Information
                </h4>
                <div style="display: grid; gap: 0.75rem;">
                    <div>
                        <label style="display: block; font-weight: 600; color: #856404; font-size: 0.9rem; margin-bottom: 0.25rem;">Transferred To:</label>
                        <span id="current_transferred_to_school" style="color: #333; font-size: 0.95rem;">-</span>
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; color: #856404; font-size: 0.9rem; margin-bottom: 0.25rem;">Transfer Date:</label>
                        <span id="current_transfer_date" style="color: #333; font-size: 0.95rem;">-</span>
                    </div>
                    <div id="current_transfer_reason_container" style="display: none;">
                        <label style="display: block; font-weight: 600; color: #856404; font-size: 0.9rem; margin-bottom: 0.25rem;">Reason:</label>
                        <span id="current_transfer_reason" style="color: #333; font-size: 0.95rem;">-</span>
                    </div>
                    <div id="current_approved_by_container" style="display: none;">
                        <label style="display: block; font-weight: 600; color: #856404; font-size: 0.9rem; margin-bottom: 0.25rem;">Approved By:</label>
                        <span id="current_approved_by" style="color: #333; font-size: 0.95rem;">-</span>
                    </div>
                </div>
            </div>
            
            <div style="margin-bottom: 2rem;">
                <label style="display: block; margin-bottom: 0.75rem; color: var(--dark-blue); font-weight: 600;">New Status:</label>
                <select name="new_status" id="new_status" required onchange="toggleTransferFields()" style="width: 100%; padding: 1rem; border: 2px solid var(--border-gray); border-radius: 12px; font-size: 1rem;">
                    <option value="">Select New Status</option>
                    <option value="Pending Payment">Pending Payment</option>
                    <option value="Enrolled">Enrolled</option>
                    <option value="Suspended">Suspended</option>
                    <option value="Dropped">Dropped</option>
                    <option value="Transferred">Transferred</option>
                    <option value="Graduated">Graduated</option>
                </select>
            </div>
            
            <!-- New Transfer Details Section (shown when Transferred is selected) -->
            <div id="transferDetailsSection" style="display: none; margin-bottom: 2rem; padding: 1.5rem; background: var(--light-blue); border-radius: 12px; border-left: 4px solid var(--primary-blue);">
                <h4 style="margin: 0 0 1rem 0; color: var(--dark-blue); font-size: 1.1rem;">
                    <i class="fas fa-school"></i> Transfer Details
                </h4>
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--dark-blue); font-weight: 600;">Transferred To (School Name) *</label>
                    <input type="text" name="transferred_to_school" id="transferred_to_school" 
                           placeholder="Enter the name of the school"
                           style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-gray); border-radius: 8px; font-size: 0.95rem;">
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--dark-blue); font-weight: 600;">Transfer Date *</label>
                    <input type="date" name="transfer_date" id="transfer_date" 
                           value="<?php echo date('Y-m-d'); ?>"
                           max="<?php echo date('Y-m-d'); ?>"
                           style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-gray); border-radius: 8px; font-size: 0.95rem;">
                    <small style="color: var(--gray); font-size: 0.85rem; display: block; margin-top: 0.25rem;">
                        Date when the student officially transferred
                    </small>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--dark-blue); font-weight: 600;">Reason for Transfer (Optional)</label>
                    <textarea name="transfer_reason" id="transfer_reason" rows="3"
                              placeholder="Enter reason for transfer (e.g., family relocation, personal reasons)"
                              style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-gray); border-radius: 8px; font-size: 0.95rem; resize: vertical;"></textarea>
                </div>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" onclick="closeChangeStatusModal()" 
                        style="background: var(--gray); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 10px; cursor: pointer; font-weight: 600;">
                    Cancel
                </button>
                <button type="submit" 
                        style="background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue)); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 10px; cursor: pointer; font-weight: 600;">
                    Update Status
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleTransferFields() {
    const statusSelect = document.getElementById('new_status');
    const transferSection = document.getElementById('transferDetailsSection');
    const transferSchool = document.getElementById('transferred_to_school');
    const transferDate = document.getElementById('transfer_date');
    
    if (statusSelect.value === 'Transferred') {
        transferSection.style.display = 'block';
        transferSchool.required = true;
        transferDate.required = true;
    } else {
        transferSection.style.display = 'none';
        transferSchool.required = false;
        transferDate.required = false;
        transferSchool.value = '';
        transferDate.value = '<?php echo date('Y-m-d'); ?>';
        document.getElementById('transfer_reason').value = '';
    }
}

function changeStatus(studentId, currentStatus, transferDetails) {
    document.getElementById('change_student_id').value = studentId;
    document.getElementById('current_status_display').textContent = currentStatus;
    document.getElementById('new_status').value = '';
    
    // Reset new transfer fields
    document.getElementById('transferDetailsSection').style.display = 'none';
    document.getElementById('transferred_to_school').required = false;
    document.getElementById('transfer_date').required = false;
    document.getElementById('transferred_to_school').value = '';
    document.getElementById('transfer_date').value = '<?php echo date('Y-m-d'); ?>';
    document.getElementById('transfer_reason').value = '';
    
    // Show current transfer details if student is already transferred
    const currentTransferSection = document.getElementById('currentTransferDetailsSection');
    if (currentStatus === 'Transferred' && transferDetails) {
        currentTransferSection.style.display = 'block';
        
        document.getElementById('current_transferred_to_school').textContent = 
            transferDetails.transferred_to_school || 'Not specified';
        
        if (transferDetails.transfer_date) {
            const date = new Date(transferDetails.transfer_date);
            document.getElementById('current_transfer_date').textContent = 
                date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
        } else {
            document.getElementById('current_transfer_date').textContent = 'Not specified';
        }
        
        if (transferDetails.transfer_reason) {
            document.getElementById('current_transfer_reason_container').style.display = 'block';
            document.getElementById('current_transfer_reason').textContent = transferDetails.transfer_reason;
        } else {
            document.getElementById('current_transfer_reason_container').style.display = 'none';
        }
        
        if (transferDetails.approved_by_username) {
            document.getElementById('current_approved_by_container').style.display = 'block';
            document.getElementById('current_approved_by').textContent = transferDetails.approved_by_username;
        } else {
            document.getElementById('current_approved_by_container').style.display = 'none';
        }
    } else {
        currentTransferSection.style.display = 'none';
    }
    
    document.getElementById('changeStatusModal').style.display = 'flex';
}

function closeChangeStatusModal() {
    document.getElementById('changeStatusModal').style.display = 'none';
    document.getElementById('changeStatusForm').reset();
    document.getElementById('transferDetailsSection').style.display = 'none';
    document.getElementById('currentTransferDetailsSection').style.display = 'none';
}

// Close modal when clicking outside
document.getElementById('changeStatusModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeChangeStatusModal();
    }
});

function editStudent(studentId) {
    window.location.href = `student_edit.php?id=${studentId}`;
}

function printRecord(studentId) {
    window.open(`student_print.php?id=${studentId}`, '_blank');
}
</script>

<?php include '../includes/footer.php'; ?>
