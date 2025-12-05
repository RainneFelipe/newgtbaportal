<?php
require_once '../includes/auth_check.php';

// Only allow admin access
if (!checkRole(['admin'])) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';
require_once '../classes/User.php';

$page_title = 'User Management - Admin Panel';
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
            case 'create':
                // Create new user
                try {
                    // Get role_id from role name
                    $stmt = $conn->prepare("SELECT id FROM roles WHERE name = ?");
                    $stmt->execute([$_POST['role']]);
                    $role = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$role) {
                        throw new Exception("Invalid role selected");
                    }
                    
                    $stmt = $conn->prepare("INSERT INTO users (username, password, email, role_id, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
                    $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt->execute([
                        $_POST['username'],
                        $hashedPassword,
                        $_POST['email'],
                        $role['id']
                    ]);
                    
                    $user_id = $conn->lastInsertId();
                    
                    // Log the action
                    $user->logAudit($_SESSION['user_id'], 'User Created: ' . $_POST['username'] . ' (' . $_POST['role'] . ')', 'users', $user_id);
                    
                    $message = 'User created successfully!';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Error creating user: ' . $e->getMessage();
                    $messageType = 'error';
                } catch (Exception $e) {
                    $message = 'Error: ' . $e->getMessage();
                    $messageType = 'error';
                }
                break;
                
            case 'update_status':
                // Update user status
                try {
                    $is_active = ($_POST['status'] === 'active') ? 1 : 0;
                    $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
                    $stmt->execute([$is_active, $_POST['user_id']]);
                    
                    // Log the action
                    $user->logAudit($_SESSION['user_id'], 'User Status Updated: ' . $_POST['status'], 'users', $_POST['user_id']);
                    
                    $message = 'User status updated successfully!';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Error updating user status: ' . $e->getMessage();
                    $messageType = 'error';
                }
                break;
                
            case 'reset_password':
                // Reset user password
                try {
                    $newPassword = 'password123'; // Default password
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashedPassword, $_POST['user_id']]);
                    
                    // Log the action
                    $user->logAudit($_SESSION['user_id'], 'Password Reset', 'users', $_POST['user_id']);
                    
                    $message = 'Password reset successfully! New password: password123';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Error resetting password: ' . $e->getMessage();
                    $messageType = 'error';
                }
                break;
                
            case 'archive':
                // Archive user
                try {
                    $stmt = $conn->prepare("UPDATE users SET archived_at = NOW(), is_active = 0 WHERE id = ?");
                    $stmt->execute([$_POST['user_id']]);
                    
                    // Get username for logging
                    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                    $stmt->execute([$_POST['user_id']]);
                    $username = $stmt->fetchColumn();
                    
                    // Log the action
                    $user->logAudit($_SESSION['user_id'], 'User Archived: ' . $username, 'users', $_POST['user_id']);
                    
                    $message = 'User archived successfully!';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Error archiving user: ' . $e->getMessage();
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Get all users with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where_conditions = [];
$params = [];

// Always exclude archived users
$where_conditions[] = "u.archived_at IS NULL";

if (!empty($search)) {
    $where_conditions[] = "(u.username LIKE ? OR u.email LIKE ?)";
    $search_param = '%' . $search . '%';
    $params = array_merge($params, [$search_param, $search_param]);
}

if (!empty($role_filter)) {
    $where_conditions[] = "r.name = ?";
    $params[] = $role_filter;
}

if (!empty($status_filter)) {
    if ($status_filter === 'active') {
        $where_conditions[] = "u.is_active = 1";
    } else {
        $where_conditions[] = "u.is_active = 0";
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM users u LEFT JOIN roles r ON u.role_id = r.id $where_clause";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_users / $limit);

// Get users
$sql = "SELECT u.*, r.name as role, r.display_name as role_display,
               CASE WHEN u.is_active = 1 THEN 'active' ELSE 'inactive' END as status
        FROM users u 
        LEFT JOIN roles r ON u.role_id = r.id 
        $where_clause 
        ORDER BY u.created_at DESC 
        LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="admin-header">
    <h1>User Management</h1>
    <p class="subtitle">Manage all user accounts across all roles</p>
    <a href="archived_users.php" class="btn btn-secondary" style="margin-top: 1rem;">📦 View Archived Users</a>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<!-- Create User Form -->
<div class="card">
    <div class="card-header">
        <h3>Create New User</h3>
    </div>
    <div class="card-body">
        <form method="POST" class="form-grid">
            <input type="hidden" name="action" value="create">
            
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="role">Role</label>
                <select id="role" name="role" required>
                    <option value="">Select Role</option>
                    <option value="teacher">Teacher</option>
                    <option value="admin">Administrator</option>
                    <option value="finance">Finance</option>
                    <option value="registrar">Registrar</option>
                    <option value="principal">Principal</option>
                </select>
            </div>
            
            <div class="form-group" style="grid-column: 1 / -1;">
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Search and Filter -->
<div class="card">
    <div class="card-header">
        <h3>User List</h3>
    </div>
    <div class="card-body">
        <form method="GET" class="search-form">
            <div class="search-grid">
                <div class="form-group">
                    <input type="text" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
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
                
                <div class="form-group">
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-secondary">Search</button>
            </div>
        </form>
        
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['username'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($user['email'] ?? ''); ?></td>
                            <td>
                                <span class="role-badge role-<?php echo $user['role'] ?? 'student'; ?>">
                                    <?php echo ucfirst($user['role_display'] ?? $user['role'] ?? 'student'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $user['status'] ?? 'active'; ?>">
                                    <?php echo ucfirst($user['status'] ?? 'active'); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <!-- Status Toggle -->
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <select name="status" onchange="this.form.submit()" class="status-select">
                                            <option value="active" <?php echo ($user['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo ($user['status'] ?? 'active') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                            <option value="suspended" <?php echo ($user['status'] ?? 'active') === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                        </select>
                                    </form>
                                    
                                    <!-- Reset Password -->
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Reset password for this user?')">
                                        <input type="hidden" name="action" value="reset_password">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-warning" title="Reset Password">🔑</button>
                                    </form>
                                    
                                    <!-- Archive User -->
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to archive this user? They will be moved to archived users.')">
                                        <input type="hidden" name="action" value="archive">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Archive User">🗄️</button>
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
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>" 
                       class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
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

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: var(--text);
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
    grid-template-columns: 2fr 1fr 1fr auto;
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

.role-badge,
.status-badge {
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

.status-active { background-color: #e8f5e8; color: #2e7d32; }
.status-inactive { background-color: #fff3e0; color: #ef6c00; }
.status-suspended { background-color: #ffebee; color: #c62828; }

.action-buttons {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.status-select {
    padding: 0.25rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 0.875rem;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.btn-warning {
    background-color: #ffc107;
    color: #212529;
    border: none;
}

.btn-warning:hover {
    background-color: #e0a800;
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
</style>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
