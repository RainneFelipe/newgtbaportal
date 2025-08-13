<?php
require_once '../includes/auth_check.php';

// Only allow admin access
if (!checkRole(['admin'])) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';
require_once '../classes/User.php';

$page_title = 'School Years Management - Admin Panel';
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
            case 'create':
                try {
                    // If this is set as active, deactivate others
                    if ($_POST['is_active'] == '1') {
                        $stmt = $conn->prepare("UPDATE school_years SET is_active = 0");
                        $stmt->execute();
                    }
                    
                    $stmt = $conn->prepare("INSERT INTO school_years (year_name, year_label, start_date, end_date, is_active, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([
                        $_POST['year_name'],
                        $_POST['year_name'], // Use same value for year_label
                        $_POST['start_date'],
                        $_POST['end_date'],
                        $_POST['is_active'],
                        $_SESSION['user_id']
                    ]);
                    
                    $school_year_id = $conn->lastInsertId();
                    
                    // Log the action
                    $user->logAudit($_SESSION['user_id'], 'School Year Created: ' . $_POST['year_name'], 'school_years', $school_year_id);
                    
                    $message = 'School year created successfully!';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Error creating school year: ' . $e->getMessage();
                    $messageType = 'error';
                }
                break;
                
            case 'set_active':
                try {
                    // Deactivate all others first
                    $stmt = $conn->prepare("UPDATE school_years SET is_active = 0");
                    $stmt->execute();
                    
                    // Activate the selected one
                    $stmt = $conn->prepare("UPDATE school_years SET is_active = 1 WHERE id = ?");
                    $stmt->execute([$_POST['year_id']]);
                    
                    // Log the action
                    $stmt = $conn->prepare("SELECT year_label FROM school_years WHERE id = ?");
                    $stmt->execute([$_POST['year_id']]);
                    $year = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $user->logAudit($_SESSION['user_id'], 'Active School Year Changed: ' . ($year['year_label'] ?? 'Unknown'), 'school_years', $_POST['year_id']);
                    
                    $message = 'Active school year updated successfully!';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Error updating active school year: ' . $e->getMessage();
                    $messageType = 'error';
                }
                break;
                
            case 'update':
                try {
                    $stmt = $conn->prepare("UPDATE school_years SET year_name = ?, year_label = ?, start_date = ?, end_date = ? WHERE id = ?");
                    $stmt->execute([
                        $_POST['year_name'],
                        $_POST['year_name'], // Use same value for year_label
                        $_POST['start_date'],
                        $_POST['end_date'],
                        $_POST['year_id']
                    ]);
                    
                    // Log the action
                    $user->logAudit($_SESSION['user_id'], 'School Year Updated: ' . $_POST['year_name'], 'school_years', $_POST['year_id']);
                    
                    $message = 'School year updated successfully!';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Error updating school year: ' . $e->getMessage();
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Get all school years
$stmt = $conn->prepare("SELECT sy.*, 
                       (SELECT COUNT(*) FROM student_enrollments se WHERE se.school_year_id = sy.id) as enrollment_count
                       FROM school_years sy 
                       ORDER BY sy.start_date DESC");
$stmt->execute();
$school_years = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get the active school year
$active_year = null;
foreach ($school_years as $year) {
    if ($year['is_active'] == 1) {
        $active_year = $year;
        break;
    }
}

ob_start();
?>

<div class="admin-header">
    <div class="header-content">
        <div class="header-text">
            <h1><i class="icon">📚</i> School Years Management</h1>
            <p class="subtitle">Manage academic years and set active periods for student enrollment</p>
        </div>
        <div class="header-stats">
            <div class="stat-card">
                <span class="stat-number"><?php echo count($school_years); ?></span>
                <span class="stat-label">Total Years</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $active_year ? ($active_year['enrollment_count'] ?? 0) : 0; ?></span>
                <span class="stat-label">Active Enrollments</span>
            </div>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<!-- Active School Year Summary -->
<?php if ($active_year): ?>
<div class="active-year-banner">
    <div class="banner-content">
        <div class="active-year-info">
            <div class="active-indicator">
                <span class="pulse-dot"></span>
                <span class="active-text">CURRENTLY ACTIVE</span>
            </div>
            <h2 class="year-title"><?php echo htmlspecialchars($active_year['year_label'] ?? ''); ?></h2>
            <div class="year-meta">
                <div class="meta-item">
                    <i class="icon">📅</i>
                    <span><?php echo date('M j, Y', strtotime($active_year['start_date'] ?? '')); ?> - <?php echo date('M j, Y', strtotime($active_year['end_date'] ?? '')); ?></span>
                </div>
                <div class="meta-item">
                    <i class="icon">👥</i>
                    <span><?php echo $active_year['enrollment_count'] ?? 0; ?> students enrolled</span>
                </div>
            </div>
        </div>
        <div class="banner-visual">
            <div class="academic-calendar">
                <div class="calendar-icon">📋</div>
                <div class="progress-ring">
                    <?php
                    $start = strtotime($active_year['start_date'] ?? '');
                    $end = strtotime($active_year['end_date'] ?? '');
                    $now = time();
                    $progress = max(0, min(100, (($now - $start) / ($end - $start)) * 100));
                    ?>
                    <div class="progress-text"><?php echo round($progress); ?>%</div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<div class="no-active-banner">
    <div class="warning-content">
        <div class="warning-icon">⚠️</div>
        <div class="warning-text">
            <h3>No Active School Year</h3>
            <p>Please set an active school year to manage student enrollments and academic records.</p>
            <button class="btn btn-primary" onclick="document.getElementById('year_name').focus()">Create First School Year</button>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="content-grid">
    <!-- Create New School Year -->
    <div class="card create-card">
        <div class="card-header">
            <h3><i class="icon">➕</i> Create New School Year</h3>
            <p class="card-description">Add a new academic year to the system</p>
        </div>
        <div class="card-body">
            <form method="POST" class="create-form">
                <input type="hidden" name="action" value="create">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="year_name" class="required">School Year</label>
                        <input type="text" id="year_name" name="year_name" placeholder="e.g., 2024-2025" required 
                               pattern="^\d{4}-\d{4}$" title="Format: YYYY-YYYY">
                        <div class="input-help">Format: YYYY-YYYY (e.g., 2024-2025)</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="is_active">Status</label>
                        <div class="custom-select">
                            <select id="is_active" name="is_active">
                                <option value="0">Inactive</option>
                                <option value="1">Set as Active Year</option>
                            </select>
                            <div class="select-arrow">▼</div>
                        </div>
                        <div class="input-help">Only one school year can be active at a time</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="start_date" class="required">Start Date</label>
                        <input type="date" id="start_date" name="start_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date" class="required">End Date</label>
                        <input type="date" id="end_date" name="end_date" required>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="reset" class="btn btn-secondary">Clear Form</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="icon">💾</i> Create School Year
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- School Years List -->
    <div class="card list-card">
        <div class="card-header">
            <div class="header-left">
                <h3><i class="icon">📋</i> All School Years</h3>
                <p class="card-description">Manage existing academic years</p>
            </div>
            <div class="header-actions">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search school years..." onkeyup="filterTable()">
                    <i class="search-icon">🔍</i>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="modern-table" id="schoolYearsTable">
                    <thead>
                        <tr>
                            <th>School Year</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Enrollments</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($school_years as $year): ?>
                        <tr class="<?php echo ($year['is_active'] ?? false) ? 'active-row' : ''; ?>" data-year="<?php echo htmlspecialchars($year['year_label'] ?? ''); ?>">
                            <td>
                                <div class="year-info">
                                    <strong class="year-name"><?php echo htmlspecialchars($year['year_label'] ?? ''); ?></strong>
                                    <?php if ($year['is_active'] ?? false): ?>
                                        <span class="active-badge">
                                            <span class="badge-dot"></span>
                                            ACTIVE
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="duration-info">
                                    <div class="date-range">
                                        <span class="start-date"><?php echo date('M j, Y', strtotime($year['start_date'] ?? '')); ?></span>
                                        <span class="date-separator">→</span>
                                        <span class="end-date"><?php echo date('M j, Y', strtotime($year['end_date'] ?? '')); ?></span>
                                    </div>
                                    <?php
                                    $start = strtotime($year['start_date'] ?? '');
                                    $end = strtotime($year['end_date'] ?? '');
                                    $days = ceil(($end - $start) / (60 * 60 * 24));
                                    ?>
                                    <small class="duration-days"><?php echo $days; ?> days</small>
                                </div>
                            </td>
                            <td>
                                <?php
                                $today = date('Y-m-d');
                                $status = 'upcoming';
                                $statusClass = 'status-upcoming';
                                $statusIcon = '⏳';
                                
                                if ($today >= ($year['start_date'] ?? '') && $today <= ($year['end_date'] ?? '')) {
                                    $status = 'current';
                                    $statusClass = 'status-current';
                                    $statusIcon = '📚';
                                } elseif ($today > ($year['end_date'] ?? '')) {
                                    $status = 'completed';
                                    $statusClass = 'status-completed';
                                    $statusIcon = '✅';
                                }
                                ?>
                                <div class="status-container">
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <i class="status-icon"><?php echo $statusIcon; ?></i>
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div class="enrollment-info">
                                    <span class="enrollment-count"><?php echo $year['enrollment_count'] ?? 0; ?></span>
                                    <span class="enrollment-label">students</span>
                                </div>
                            </td>
                            <td>
                                <div class="date-info">
                                    <span class="created-date"><?php echo date('M j, Y', strtotime($year['created_at'] ?? '')); ?></span>
                                    <small class="created-time"><?php echo date('g:i A', strtotime($year['created_at'] ?? '')); ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <?php if (!($year['is_active'] ?? false)): ?>
                                        <button type="button" 
                                                class="btn btn-sm btn-success action-btn" 
                                                onclick="setActiveYear(<?php echo $year['id'] ?? ''; ?>, '<?php echo htmlspecialchars($year['year_label'] ?? ''); ?>')"
                                                title="Set as Active Year">
                                            <i class="btn-icon">⭐</i>
                                            <span class="btn-text">Activate</span>
                                        </button>
                                    <?php else: ?>
                                        <span class="current-badge">Current Active</span>
                                    <?php endif; ?>
                                    
                                    <button type="button" 
                                            class="btn btn-sm btn-secondary action-btn" 
                                            onclick="editYear(<?php echo htmlspecialchars(json_encode($year)); ?>)" 
                                            title="Edit School Year">
                                        <i class="btn-icon">✏️</i>
                                        <span class="btn-text">Edit</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (empty($school_years)): ?>
            <div class="empty-state">
                <div class="empty-icon">📅</div>
                <h3>No School Years Found</h3>
                <p>Create your first school year to get started with the academic management system.</p>
                <button class="btn btn-primary" onclick="document.getElementById('year_name').focus()">Create First School Year</button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit School Year</h3>
            <span class="close" onclick="closeEditModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="year_id" id="edit_year_id">
                
                <div class="form-group">
                    <label for="edit_year_name">School Year</label>
                    <input type="text" id="edit_year_name" name="year_name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_start_date">Start Date</label>
                    <input type="date" id="edit_start_date" name="start_date" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_end_date">End Date</label>
                    <input type="date" id="edit_end_date" name="end_date" required>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update School Year</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Enhanced Admin Header */
.admin-header {
    background: linear-gradient(135deg, var(--white) 0%, var(--light-blue) 100%);
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
}

.header-text h1 {
    color: var(--dark-blue);
    font-size: 2.25rem;
    font-weight: 700;
    margin: 0 0 0.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.header-text .subtitle {
    color: var(--gray);
    font-size: 1.1rem;
    margin: 0;
}

.header-stats {
    display: flex;
    gap: 1.5rem;
}

.stat-card {
    background: var(--white);
    padding: 1.25rem;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 2px 12px rgba(0,0,0,0.1);
    min-width: 120px;
}

.stat-number {
    display: block;
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary-blue);
}

.stat-label {
    display: block;
    font-size: 0.875rem;
    color: var(--gray);
    margin-top: 0.25rem;
}

/* Active Year Banner */
.active-year-banner {
    background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
    color: white;
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}

.active-year-banner::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 200px;
    height: 200px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
    transform: translate(50%, -50%);
}

.banner-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    z-index: 1;
}

.active-indicator {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.pulse-dot {
    width: 8px;
    height: 8px;
    background: #4ade80;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.7; transform: scale(1.2); }
}

.active-text {
    font-size: 0.875rem;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.year-title {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0 0 1rem 0;
}

.year-meta {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.1rem;
}

.banner-visual {
    display: flex;
    align-items: center;
    gap: 2rem;
}

.academic-calendar {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.calendar-icon {
    font-size: 3rem;
    opacity: 0.8;
}

.progress-ring {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.875rem;
}

/* No Active Banner */
.no-active-banner {
    background: linear-gradient(135deg, #fff3cd 0%, #fef7e0 100%);
    border: 2px solid #ffeaa7;
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.warning-content {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.warning-icon {
    font-size: 3rem;
}

.warning-text h3 {
    color: #856404;
    margin: 0 0 0.5rem 0;
}

.warning-text p {
    color: #856404;
    margin: 0 0 1rem 0;
}

/* Content Grid */
.content-grid {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

/* Enhanced Cards */
.card {
    background: var(--white);
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    overflow: hidden;
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
    transform: translateY(-2px);
}

.card-header {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid var(--border-gray);
    background: linear-gradient(135deg, var(--light-blue) 0%, var(--white) 100%);
}

.card-header h3 {
    color: var(--dark-blue);
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0 0 0.25rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.card-description {
    color: var(--gray);
    font-size: 0.9rem;
    margin: 0;
}

.header-left, .header-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-body {
    padding: 2rem;
    overflow: visible;
}

.list-card {
    min-height: 400px;
}

.create-card {
    margin-bottom: 2rem;
}

/* Enhanced Forms */
.create-form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.create-card .form-row {
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-group label {
    font-weight: 600;
    color: var(--black);
    font-size: 0.9rem;
}

.form-group label.required::after {
    content: ' *';
    color: #dc3545;
}

.form-group input,
.form-group select {
    padding: 0.875rem 1rem;
    border: 2px solid var(--border-gray);
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: var(--white);
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 3px rgba(135, 206, 235, 0.1);
}

.custom-select {
    position: relative;
}

.custom-select select {
    appearance: none;
    width: 100%;
    padding-right: 2.5rem;
}

.select-arrow {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    color: var(--gray);
    font-size: 0.75rem;
}

.input-help {
    font-size: 0.8rem;
    color: var(--gray);
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 1rem;
}

/* Search Box */
.search-box {
    position: relative;
    width: 250px;
}

.search-box input {
    width: 100%;
    padding: 0.5rem 1rem 0.5rem 2.5rem;
    border: 2px solid var(--border-gray);
    border-radius: 25px;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.search-box input:focus {
    outline: none;
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 3px rgba(135, 206, 235, 0.1);
}

.search-icon {
    position: absolute;
    left: 0.875rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.875rem;
    color: var(--gray);
}

/* Modern Table */
.table-container {
    overflow-x: auto;
    margin: -1rem;
    padding: 1rem;
}

.modern-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.95rem;
    min-width: 800px;
}

.modern-table th {
    background: var(--light-gray);
    color: var(--black);
    font-weight: 600;
    padding: 1rem;
    text-align: left;
    border-bottom: 2px solid var(--border-gray);
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.modern-table td {
    padding: 1.25rem 1rem;
    border-bottom: 1px solid var(--border-gray);
    vertical-align: middle;
}

.modern-table tr:hover {
    background: var(--light-blue);
}

.active-row {
    background: linear-gradient(135deg, #e8f5e8 0%, #f0f9f0 100%);
    border-left: 4px solid #28a745;
}

.active-row:hover {
    background: linear-gradient(135deg, #d4f4d4 0%, #e8f5e8 100%);
}

/* Table Content Styling */
.year-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.year-name {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark-blue);
}

.active-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    background: #28a745;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-dot {
    width: 6px;
    height: 6px;
    background: white;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

.duration-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.date-range {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 500;
}

.date-separator {
    color: var(--primary-blue);
    font-weight: 600;
}

.duration-days {
    color: var(--gray);
    font-size: 0.8rem;
}

.status-container {
    display: flex;
    align-items: center;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.25px;
}

.status-upcoming { 
    background-color: #e3f2fd; 
    color: #1565c0; 
}

.status-current { 
    background-color: #e8f5e8; 
    color: #2e7d32; 
}

.status-completed { 
    background-color: #fff3e0; 
    color: #ef6c00; 
}

.enrollment-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}

.enrollment-count {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--primary-blue);
}

.enrollment-label {
    font-size: 0.8rem;
    color: var(--gray);
}

.date-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.created-date {
    font-weight: 500;
}

.created-time {
    color: var(--gray);
    font-size: 0.8rem;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.action-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-success:hover {
    background: #218838;
}

.btn-secondary {
    background: var(--light-gray);
    color: var(--black);
}

.btn-secondary:hover {
    background: var(--border-gray);
}

.current-badge {
    background: var(--light-blue);
    color: var(--dark-blue);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--gray);
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.empty-state h3 {
    margin: 0 0 0.5rem 0;
    color: var(--black);
}

.empty-state p {
    margin: 0 0 1.5rem 0;
}

/* Enhanced Modal */
.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.6);
    display: flex;
    justify-content: center;
    align-items: center;
    backdrop-filter: blur(4px);
}

.modal-content {
    background: white;
    border-radius: 16px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px) scale(0.9);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-header {
    padding: 2rem 2rem 1rem 2rem;
    border-bottom: 1px solid var(--border-gray);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    color: var(--dark-blue);
    font-size: 1.5rem;
}

.close {
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--gray);
    transition: color 0.3s ease;
    padding: 0.5rem;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.close:hover {
    color: var(--black);
    background: var(--light-gray);
}

.modal-body {
    padding: 2rem;
}

.modal-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 2rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-gray);
}

/* Responsive Design */
@media (max-width: 1200px) {
    .content-grid {
        gap: 1.5rem;
    }
    
    .header-content {
        flex-direction: column;
        align-items: flex-start;
        gap: 1.5rem;
    }
    
    .header-stats {
        width: 100%;
        justify-content: space-between;
    }
}

@media (max-width: 768px) {
    .banner-content {
        flex-direction: column;
        align-items: flex-start;
        gap: 1.5rem;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .create-card .form-row {
        grid-template-columns: 1fr;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .search-box {
        width: 100%;
    }
    
    .action-buttons {
        flex-direction: column;
        width: 100%;
    }
    
    .action-btn {
        width: 100%;
        justify-content: center;
    }
    
    .modern-table {
        min-width: 600px;
    }
    
    .card-body {
        padding: 1.5rem;
    }
}
</style>

<script>
// Enhanced JavaScript for School Years Management

function editYear(year) {
    document.getElementById('edit_year_id').value = year.id;
    document.getElementById('edit_year_name').value = year.year_name || year.year_label;
    document.getElementById('edit_start_date').value = year.start_date;
    document.getElementById('edit_end_date').value = year.end_date;
    document.getElementById('editModal').style.display = 'flex';
    
    // Focus first input for better UX
    setTimeout(() => {
        document.getElementById('edit_year_name').focus();
    }, 100);
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function setActiveYear(yearId, yearName) {
    if (confirm(`Set "${yearName}" as the active school year?\n\nThis will deactivate the current active year and make this year available for new enrollments.`)) {
        // Create and submit form
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'set_active';
        
        const yearIdInput = document.createElement('input');
        yearIdInput.type = 'hidden';
        yearIdInput.name = 'year_id';
        yearIdInput.value = yearId;
        
        form.appendChild(actionInput);
        form.appendChild(yearIdInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function filterTable() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toLowerCase();
    const table = document.getElementById('schoolYearsTable');
    const rows = table.getElementsByTagName('tr');
    
    let visibleRows = 0;
    
    for (let i = 1; i < rows.length; i++) { // Start from 1 to skip header
        const row = rows[i];
        const yearData = row.getAttribute('data-year');
        const cells = row.getElementsByTagName('td');
        let found = false;
        
        if (yearData && yearData.toLowerCase().includes(filter)) {
            found = true;
        } else {
            for (let j = 0; j < cells.length; j++) {
                if (cells[j].textContent.toLowerCase().includes(filter)) {
                    found = true;
                    break;
                }
            }
        }
        
        if (found) {
            row.style.display = '';
            visibleRows++;
        } else {
            row.style.display = 'none';
        }
    }
    
    // Show/hide empty state message
    const emptyState = document.querySelector('.empty-state');
    if (emptyState) {
        emptyState.style.display = visibleRows === 0 && filter !== '' ? 'block' : 'none';
    }
}

// Form validation
document.addEventListener('DOMContentLoaded', function() {
    const createForm = document.querySelector('.create-form');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const yearNameInput = document.getElementById('year_name');
    
    // Auto-generate year name when dates are selected
    function updateYearName() {
        const startDate = new Date(startDateInput.value);
        const endDate = new Date(endDateInput.value);
        
        if (startDate && endDate && !yearNameInput.value) {
            const startYear = startDate.getFullYear();
            const endYear = endDate.getFullYear();
            yearNameInput.value = `${startYear}-${endYear}`;
        }
    }
    
    if (startDateInput && endDateInput) {
        startDateInput.addEventListener('change', updateYearName);
        endDateInput.addEventListener('change', updateYearName);
        
        // Validate date range
        function validateDates() {
            const startDate = new Date(startDateInput.value);
            const endDate = new Date(endDateInput.value);
            
            if (startDate && endDate) {
                if (startDate >= endDate) {
                    endDateInput.setCustomValidity('End date must be after start date');
                } else {
                    endDateInput.setCustomValidity('');
                }
            }
        }
        
        startDateInput.addEventListener('change', validateDates);
        endDateInput.addEventListener('change', validateDates);
    }
    
    // Form submission with loading state
    if (createForm) {
        createForm.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="icon">⏳</i> Creating...';
            
            // Re-enable button after a delay in case of errors
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }, 3000);
        });
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditModal();
    }
});

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editModal');
    if (event.target === modal) {
        closeEditModal();
    }
}

// Smooth scrolling for focus actions
function scrollToForm() {
    document.getElementById('year_name').scrollIntoView({ 
        behavior: 'smooth', 
        block: 'center' 
    });
}

// Enhanced table interactions
document.addEventListener('DOMContentLoaded', function() {
    const tableRows = document.querySelectorAll('.modern-table tbody tr');
    
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.01)';
            this.style.zIndex = '1';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
            this.style.zIndex = 'auto';
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
