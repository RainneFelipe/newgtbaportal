<?php
require_once '../includes/auth_check.php';

// Only allow admin access
if (!checkRole(['admin'])) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';
require_once '../classes/User.php';

$page_title = 'Archived Users - Admin Panel';
$base_url = '../';

$database = new Database();
$conn = $database->connect();
$user = new User($conn);

// Handle user actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'restore':
                // Restore archived user
                try {
                    $stmt = $conn->prepare("UPDATE users SET archived_at = NULL, is_active = 1 WHERE id = ?");
                    $stmt->execute([$_POST['user_id']]);
                    
                    // Get username for logging
                    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                    $stmt->execute([$_POST['user_id']]);
                    $username = $stmt->fetchColumn();
                    
                    // Log the action
                    $user->logAudit($_SESSION['user_id'], 'User Restored: ' . $username, 'users', $_POST['user_id']);
                    
                    $message = 'User restored successfully!';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Error restoring user: ' . $e->getMessage();
                    $messageType = 'error';
                }
                break;
                
            case 'delete_permanent':
                // Permanently delete user (use with caution)
                try {
                    // Get username for logging
                    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                    $stmt->execute([$_POST['user_id']]);
                    $username = $stmt->fetchColumn();
                    
                    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$_POST['user_id']]);
                    
                    // Log the action
                    $user->logAudit($_SESSION['user_id'], 'User Permanently Deleted: ' . $username, 'users', $_POST['user_id']);
                    
                    $message = 'User permanently deleted!';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Error deleting user: ' . $e->getMessage();
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Get archived users with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';

$where_conditions = [];
$params = [];

// Only show archived users
$where_conditions[] = "u.archived_at IS NOT NULL";

if (!empty($search)) {
    $where_conditions[] = "(u.username LIKE ? OR u.email LIKE ?)";
    $search_param = '%' . $search . '%';
    $params = array_merge($params, [$search_param, $search_param]);
}

if (!empty($role_filter)) {
    $where_conditions[] = "r.name = ?";
    $params[] = $role_filter;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM users u LEFT JOIN roles r ON u.role_id = r.id $where_clause";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_users / $limit);

// Get archived users
$sql = "SELECT u.*, r.name as role, r.display_name as role_display
        FROM users u 
        LEFT JOIN roles r ON u.role_id = r.id 
        $where_clause 
        ORDER BY u.archived_at DESC 
        LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$archived_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="admin-header">
    <h1>📦 Archived Users</h1>
    <p class="subtitle">View and manage archived user accounts</p>
    <a href="users.php" class="btn btn-secondary" style="margin-top: 1rem;">← Back to Active Users</a>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<!-- Search and Filter -->
<div class="card">
    <div class="card-header">
        <h3>Archived User List (<?php echo $total_users; ?> total)</h3>
    </div>
    <div class="card-body">
        <form method="GET" class="search-form">
            <div class="search-grid">
                <div class="form-group">
                    <input type="text" name="search" placeholder="Search archived users..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="form-group">
                    <select name="role">
                        <option value="">All Roles</option>
                        <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Student</option>
                        <option value="teacher" <?php echo $role_filter === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                        <option value="finance" <?php echo $role_filter === 'finance' ? 'selected' : ''; ?>>Finance</option>
                        <option value="registrar" <?php echo $role_filter === 'registrar' ? 'selected' : ''; ?>>Registrar</option>
                        <option value="principal" <?php echo $role_filter === 'principal' ? 'selected' : ''; ?>>Principal</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-secondary">Search</button>
            </div>
        </form>
        
        <?php if (empty($archived_users)): ?>
            <div class="empty-state">
                <p>No archived users found.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Created</th>
                            <th>Archived</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($archived_users as $archived_user): ?>
                            <tr>
                                <td><?php echo $archived_user['id']; ?></td>
                                <td><?php echo htmlspecialchars($archived_user['username'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($archived_user['email'] ?? ''); ?></td>
                                <td>
                                    <span class="role-badge role-<?php echo $archived_user['role'] ?? 'student'; ?>">
                                        <?php echo ucfirst($archived_user['role_display'] ?? $archived_user['role'] ?? 'student'); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($archived_user['created_at'])); ?></td>
                                <td><?php echo date('M j, Y', strtotime($archived_user['archived_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- View Details -->
                                        <button type="button" class="btn btn-sm btn-info" title="View Details" onclick="viewUserDetails(<?php echo $archived_user['id']; ?>)">👁️ View</button>
                                        
                                        <!-- Restore User -->
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Restore this user to active users?')">
                                            <input type="hidden" name="action" value="restore">
                                            <input type="hidden" name="user_id" value="<?php echo $archived_user['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-success" title="Restore User">♻️ Restore</button>
                                        </form>
                                        
                                        <!-- Permanently Delete -->
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('⚠️ WARNING: This will PERMANENTLY delete this user. This action cannot be undone! Are you absolutely sure?')">
                                            <input type="hidden" name="action" value="delete_permanent">
                                            <input type="hidden" name="user_id" value="<?php echo $archived_user['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Permanently Delete">🗑️ Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" 
                           class="<?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- User Details Modal -->
<div id="userDetailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>📋 User Details</h2>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body" id="userDetailsContent">
            <div class="loading">Loading user details...</div>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal()" class="btn btn-secondary">Close</button>
        </div>
    </div>
</div>

<style>
.admin-header {
    margin-bottom: 2rem;
}

.admin-header h1 {
    color: var(--primary);
    margin-bottom: 0.5rem;
}

.subtitle {
    color: var(--gray);
    margin: 0;
}

.alert {
    padding: 1rem;
    border-radius: 4px;
    margin-bottom: 1rem;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.card-header {
    padding: 1.5rem;
    border-bottom: 1px solid #eee;
}

.card-header h3 {
    margin: 0;
    color: var(--primary);
}

.card-body {
    padding: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group input,
.form-group select {
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
}

.search-form {
    margin-bottom: 1.5rem;
}

.search-grid {
    display: grid;
    grid-template-columns: 2fr 1fr auto;
    gap: 1rem;
    align-items: end;
}

.table-container {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.data-table th,
.data-table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.data-table th {
    background-color: #f8f9fa;
    font-weight: 600;
    color: var(--primary);
}

.role-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.875rem;
    font-weight: 600;
    text-transform: uppercase;
}

.role-student { background-color: #e3f2fd; color: #1565c0; }
.role-teacher { background-color: #f3e5f5; color: #7b1fa2; }
.role-admin { background-color: #ffebee; color: #c62828; }
.role-finance { background-color: #e8f5e8; color: #2e7d32; }
.role-registrar { background-color: #fff3e0; color: #ef6c00; }
.role-principal { background-color: #fce4ec; color: #ad1457; }

.action-buttons {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.btn-success {
    background-color: #28a745;
    color: white;
    border: none;
}

.btn-success:hover {
    background-color: #218838;
}

.btn-danger {
    background-color: #dc3545;
    color: white;
    border: none;
}

.btn-danger:hover {
    background-color: #c82333;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 1rem;
}

.pagination a {
    padding: 0.5rem 1rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-decoration: none;
    color: var(--primary);
}

.pagination a:hover,
.pagination a.active {
    background-color: var(--primary);
    color: white;
}

.empty-state {
    text-align: center;
    padding: 3rem;
    color: var(--gray);
}

.empty-state p {
    font-size: 1.2rem;
}

.btn-info {
    background-color: #17a2b8;
    color: white;
    border: none;
}

.btn-info:hover {
    background-color: #138496;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 0;
    border: 1px solid #888;
    border-radius: 8px;
    width: 80%;
    max-width: 800px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.modal-header {
    padding: 1.5rem;
    background-color: var(--primary);
    color: white;
    border-radius: 8px 8px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
    font-size: 1.5rem;
}

.modal-body {
    padding: 2rem;
    max-height: 60vh;
    overflow-y: auto;
}

.modal-footer {
    padding: 1rem 1.5rem;
    background-color: #f8f9fa;
    border-radius: 0 0 8px 8px;
    text-align: right;
}

.close {
    color: white;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover,
.close:focus {
    color: #ddd;
}

.loading {
    text-align: center;
    padding: 2rem;
    color: var(--gray);
}

.user-detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.detail-item {
    padding: 1rem;
    background-color: #f8f9fa;
    border-radius: 4px;
}

.detail-item label {
    font-weight: 600;
    color: var(--primary);
    display: block;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
}

.detail-item .value {
    color: #333;
    font-size: 1rem;
}

.detail-section {
    margin-bottom: 2rem;
}

.detail-section h3 {
    color: var(--primary);
    border-bottom: 2px solid var(--primary);
    padding-bottom: 0.5rem;
    margin-bottom: 1rem;
}

.audit-log-item {
    padding: 0.75rem;
    background-color: #f8f9fa;
    border-left: 3px solid var(--primary);
    margin-bottom: 0.5rem;
    border-radius: 4px;
}

.audit-log-item .timestamp {
    font-size: 0.875rem;
    color: var(--gray);
}

.audit-log-item .action {
    font-weight: 600;
    color: #333;
}
</style>

<script>
function viewUserDetails(userId) {
    const modal = document.getElementById('userDetailsModal');
    const content = document.getElementById('userDetailsContent');
    
    modal.style.display = 'block';
    content.innerHTML = '<div class="loading">Loading user details...</div>';
    
    // Fetch user details via AJAX
    fetch(`get_user_details.php?user_id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                content.innerHTML = `<div class="alert alert-error">${data.error}</div>`;
            } else {
                content.innerHTML = generateUserDetailsHTML(data);
            }
        })
        .catch(error => {
            content.innerHTML = '<div class="alert alert-error">Error loading user details.</div>';
        });
}

function closeModal() {
    document.getElementById('userDetailsModal').style.display = 'none';
}

function generateUserDetailsHTML(data) {
    let html = '<div class="detail-section">';
    html += '<h3>👤 User Information</h3>';
    html += '<div class="user-detail-grid">';
    html += `<div class="detail-item"><label>User ID:</label><div class="value">${data.user.id}</div></div>`;
    html += `<div class="detail-item"><label>Username:</label><div class="value">${data.user.username}</div></div>`;
    html += `<div class="detail-item"><label>Email:</label><div class="value">${data.user.email}</div></div>`;
    html += `<div class="detail-item"><label>Role:</label><div class="value"><span class="role-badge role-${data.user.role}">${data.user.role_display}</span></div></div>`;
    html += `<div class="detail-item"><label>Status:</label><div class="value">${data.user.is_active ? 'Active' : 'Inactive'}</div></div>`;
    html += `<div class="detail-item"><label>Created:</label><div class="value">${data.user.created_at}</div></div>`;
    html += `<div class="detail-item"><label>Archived:</label><div class="value">${data.user.archived_at}</div></div>`;
    html += '</div></div>';
    
    // Student-specific information
    if (data.student) {
        html += '<div class="detail-section">';
        html += '<h3>🎓 Student Information</h3>';
        html += '<div class="user-detail-grid">';
        html += `<div class="detail-item"><label>Student ID:</label><div class="value">${data.student.student_id || 'N/A'}</div></div>`;
        html += `<div class="detail-item"><label>LRN:</label><div class="value">${data.student.lrn || 'N/A'}</div></div>`;
        html += `<div class="detail-item"><label>Full Name:</label><div class="value">${data.student.full_name}</div></div>`;
        html += `<div class="detail-item"><label>Grade Level:</label><div class="value">${data.student.grade_name || 'N/A'}</div></div>`;
        html += `<div class="detail-item"><label>Section:</label><div class="value">${data.student.section_name || 'N/A'}</div></div>`;
        html += `<div class="detail-item"><label>Enrollment Status:</label><div class="value">${data.student.enrollment_status || 'N/A'}</div></div>`;
        html += '</div></div>';
    }
    
    // Teacher-specific information
    if (data.teacher) {
        html += '<div class="detail-section">';
        html += '<h3>👨‍🏫 Teacher Information</h3>';
        html += '<div class="user-detail-grid">';
        html += `<div class="detail-item"><label>Full Name:</label><div class="value">${data.teacher.full_name}</div></div>`;
        html += `<div class="detail-item"><label>Contact:</label><div class="value">${data.teacher.contact_number || 'N/A'}</div></div>`;
        html += `<div class="detail-item"><label>Address:</label><div class="value">${data.teacher.address || 'N/A'}</div></div>`;
        html += '</div>';
        
        // Assigned sections
        if (data.teacher.sections && data.teacher.sections.length > 0) {
            html += '<h4 style="margin-top: 1rem; color: var(--primary);">Assigned Sections:</h4>';
            html += '<div class="sections-list">';
            data.teacher.sections.forEach(section => {
                html += `<div class="detail-item" style="margin-bottom: 0.5rem;">`;
                html += `<strong>${section.grade_name} - ${section.section_name}</strong> (${section.year_label})`;
                if (section.is_primary) {
                    html += ' <span class="role-badge role-teacher" style="font-size: 0.75rem;">Primary</span>';
                }
                html += `</div>`;
            });
            html += '</div>';
        }
        html += '</div>';
    }
    
    // Audit logs
    if (data.audit_logs && data.audit_logs.length > 0) {
        html += '<div class="detail-section">';
        html += '<h3>📝 Recent Activity (Last 10)</h3>';
        data.audit_logs.forEach(log => {
            html += `<div class="audit-log-item">`;
            html += `<div class="action">${log.action}</div>`;
            html += `<div class="timestamp">${log.created_at}</div>`;
            html += `</div>`;
        });
        html += '</div>';
    }
    
    return html;
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('userDetailsModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}
</script>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
