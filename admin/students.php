<?php
require_once '../includes/auth_check.php';

// Only allow admin access
if (!checkRole(['admin'])) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';
require_once '../classes/User.php';

$page_title = 'Student Management - Admin Panel';
$base_url = '../';

$database = new Database();
$conn = $database->connect();
$user = new User($conn);

// Handle actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_student':
                try {
                    $conn->beginTransaction();
                    
                    // Create user account first
                    $stmt = $conn->prepare("INSERT INTO users (username, password, email, first_name, last_name, role, status, created_at) VALUES (?, ?, ?, ?, ?, 'student', 'active', NOW())");
                    $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt->execute([
                        $_POST['username'],
                        $hashedPassword,
                        $_POST['email'],
                        $_POST['first_name'],
                        $_POST['last_name']
                    ]);
                    
                    $user_id = $conn->lastInsertId();
                    
                    // Create student record
                    $stmt = $conn->prepare("INSERT INTO students (user_id, lrn, middle_name, gender, birth_date, birth_place, student_type, present_address, permanent_address, emergency_contact_name, emergency_contact_phone, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([
                        $user_id,
                        $_POST['lrn'],
                        $_POST['middle_name'],
                        $_POST['gender'],
                        $_POST['birth_date'],
                        $_POST['birth_place'],
                        $_POST['student_type'],
                        $_POST['present_address'],
                        $_POST['permanent_address'],
                        $_POST['emergency_contact_name'],
                        $_POST['emergency_contact_phone']
                    ]);
                    
                    // Log the action
                    $user->logAudit($_SESSION['user_id'], 'Student Created: ' . $_POST['first_name'] . ' ' . $_POST['last_name'] . ' (LRN: ' . $_POST['lrn'] . ')', 'students', $user_id);
                    
                    $conn->commit();
                    $message = 'Student created successfully!';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $conn->rollBack();
                    $message = 'Error creating student: ' . $e->getMessage();
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Get students with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$grade_filter = $_GET['grade'] ?? '';

$where_conditions = ["u.role = 'student'"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.username LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR s.lrn LIKE ?)";
    $search_param = '%' . $search . '%';
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(*) as total 
              FROM users u 
              LEFT JOIN students s ON u.id = s.user_id 
              $where_clause";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total_students = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_students / $limit);

// Get students
$sql = "SELECT u.*, s.*, 
               gl.level_name as current_grade,
               sec.section_name,
               sy.year_name as school_year
        FROM users u 
        LEFT JOIN students s ON u.id = s.user_id 
        LEFT JOIN student_enrollments se ON u.id = se.student_id 
        LEFT JOIN grade_levels gl ON se.grade_level_id = gl.id
        LEFT JOIN sections sec ON se.section_id = sec.id
        LEFT JOIN school_years sy ON se.school_year_id = sy.id AND sy.is_active = 1
        $where_clause 
        ORDER BY u.created_at DESC 
        LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get grade levels for dropdown
$stmt = $conn->prepare("SELECT * FROM grade_levels ORDER BY sort_order");
$stmt->execute();
$grade_levels = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="admin-header">
    <h1>Student Management</h1>
    <p class="subtitle">Create and manage student accounts with complete information</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<!-- Create Student Form -->
<div class="card">
    <div class="card-header">
        <h3>Create New Student Account</h3>
    </div>
    <div class="card-body">
        <form method="POST" class="student-form">
            <input type="hidden" name="action" value="create_student">
            
            <!-- Account Information -->
            <div class="form-section">
                <h4>Account Information</h4>
                <div class="form-grid">
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
                </div>
            </div>
            
            <!-- Personal Information -->
            <div class="form-section">
                <h4>Personal Information</h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="lrn">LRN (Learners Reference Number)</label>
                        <input type="text" id="lrn" name="lrn" maxlength="12" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="middle_name">Middle Name</label>
                        <input type="text" id="middle_name" name="middle_name">
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select id="gender" name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="birth_date">Birth Date</label>
                        <input type="date" id="birth_date" name="birth_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="birth_place">Birth Place</label>
                        <input type="text" id="birth_place" name="birth_place">
                    </div>
                    
                    <div class="form-group">
                        <label for="student_type">Student Type</label>
                        <select id="student_type" name="student_type" required>
                            <option value="">Select Type</option>
                            <option value="New">New Student</option>
                            <option value="Transfer">Transfer Student</option>
                            <option value="Continuing">Continuing Student</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Address Information -->
            <div class="form-section">
                <h4>Address Information</h4>
                <div class="form-grid">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="present_address">Present Address</label>
                        <textarea id="present_address" name="present_address" rows="3" required></textarea>
                    </div>
                    
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="permanent_address">Permanent Address</label>
                        <textarea id="permanent_address" name="permanent_address" rows="3"></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Emergency Contact -->
            <div class="form-section">
                <h4>Emergency Contact</h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="emergency_contact_name">Emergency Contact Name</label>
                        <input type="text" id="emergency_contact_name" name="emergency_contact_name">
                    </div>
                    
                    <div class="form-group">
                        <label for="emergency_contact_phone">Emergency Contact Phone</label>
                        <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone">
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create Student Account</button>
                <button type="reset" class="btn btn-secondary">Clear Form</button>
            </div>
        </form>
    </div>
</div>

<!-- Students List -->
<div class="card">
    <div class="card-header">
        <h3>Student List</h3>
    </div>
    <div class="card-body">
        <!-- Search Form -->
        <form method="GET" class="search-form">
            <div class="search-grid">
                <div class="form-group">
                    <input type="text" name="search" placeholder="Search students..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <button type="submit" class="btn btn-secondary">Search</button>
                <a href="students.php" class="btn btn-outline">Clear</a>
            </div>
        </form>
        
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>LRN</th>
                        <th>Student Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Grade Level</th>
                        <th>Section</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td>
                                <?php echo $student['lrn'] ? htmlspecialchars($student['lrn']) : '<em>Not set</em>'; ?>
                            </td>
                            <td>
                                <div class="student-name">
                                    <strong>
                                        <?php echo htmlspecialchars($student['first_name'] . ' ' . ($student['middle_name'] ? $student['middle_name'] . ' ' : '') . $student['last_name']); ?>
                                    </strong>
                                    <?php if ($student['student_type']): ?>
                                        <small class="student-type"><?php echo htmlspecialchars($student['student_type']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($student['username']); ?></td>
                            <td><?php echo htmlspecialchars($student['email'] ?? ''); ?></td>
                            <td>
                                <?php if ($student['current_grade']): ?>
                                    <span class="grade-badge"><?php echo htmlspecialchars($student['current_grade']); ?></span>
                                <?php else: ?>
                                    <em>Not enrolled</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $student['section_name'] ? htmlspecialchars($student['section_name']) : '<em>No section</em>'; ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $student['status']; ?>">
                                    <?php echo ucfirst($student['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($student['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button" class="btn btn-sm btn-info" onclick="viewStudent(<?php echo $student['id']; ?>)" title="View Details">👁️</button>
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="editStudent(<?php echo $student['id']; ?>)" title="Edit">✏️</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (empty($students)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🎓</div>
                    <h3>No students found</h3>
                    <p>Create your first student account or adjust your search criteria.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
                       class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.student-form {
    max-width: none;
}

.form-section {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid #eee;
}

.form-section:last-of-type {
    border-bottom: none;
    margin-bottom: 1rem;
}

.form-section h4 {
    color: var(--primary);
    margin-bottom: 1rem;
    font-size: 1.1rem;
    font-weight: 600;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.form-group textarea {
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
    font-family: inherit;
    resize: vertical;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-start;
    padding-top: 1rem;
    border-top: 1px solid #eee;
}

.search-grid {
    display: grid;
    grid-template-columns: 1fr auto auto;
    gap: 1rem;
    margin-bottom: 1.5rem;
    align-items: end;
}

.student-name {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.student-type {
    color: var(--gray);
    font-size: 0.875rem;
    font-style: italic;
}

.grade-badge {
    background-color: var(--primary);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.875rem;
    font-weight: 600;
}

.btn-info {
    background-color: #17a2b8;
    color: white;
    border: none;
}

.btn-info:hover {
    background-color: #138496;
}

.btn-outline {
    background: transparent;
    color: var(--primary);
    border: 1px solid var(--primary);
}

.btn-outline:hover {
    background: var(--primary);
    color: white;
}

.empty-state {
    text-align: center;
    padding: 3rem;
    color: var(--gray);
}

.empty-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.empty-state h3 {
    margin: 0 0 0.5rem 0;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .search-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
}
</style>

<script>
function viewStudent(studentId) {
    // Implement view student details functionality
    alert('View student details functionality will be implemented.');
}

function editStudent(studentId) {
    // Implement edit student functionality
    alert('Edit student functionality will be implemented.');
}

// Auto-generate username based on name
document.getElementById('first_name').addEventListener('blur', generateUsername);
document.getElementById('last_name').addEventListener('blur', generateUsername);

function generateUsername() {
    const firstName = document.getElementById('first_name').value.toLowerCase();
    const lastName = document.getElementById('last_name').value.toLowerCase();
    const usernameField = document.getElementById('username');
    
    if (firstName && lastName && !usernameField.value) {
        usernameField.value = firstName + '.' + lastName;
    }
}

// Copy present address to permanent address
document.getElementById('present_address').addEventListener('blur', function() {
    const permanentAddress = document.getElementById('permanent_address');
    if (this.value && !permanentAddress.value) {
        permanentAddress.value = this.value;
    }
});
</script>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
