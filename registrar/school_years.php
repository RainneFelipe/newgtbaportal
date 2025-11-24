<?php
require_once '../includes/auth_check.php';

// Only allow registrar access
if (!checkRole(['registrar'])) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';
require_once '../classes/User.php';

$page_title = 'School Year Management - GTBA Portal';
$base_url = '../';

$database = new Database();
$conn = $database->connect();
$user = new User($conn);

// Handle set active action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_active'])) {
    try {
        $conn->beginTransaction();
        
        // Deactivate all school years
        $stmt = $conn->prepare("UPDATE school_years SET is_active = 0");
        $stmt->execute();
        
        // Activate the selected one
        $stmt = $conn->prepare("UPDATE school_years SET is_active = 1 WHERE id = ?");
        $stmt->execute([$_POST['year_id']]);
        
        // Log the action
        $stmt = $conn->prepare("SELECT year_label FROM school_years WHERE id = ?");
        $stmt->execute([$_POST['year_id']]);
        $year = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $user->logAudit($_SESSION['user_id'], 'Active School Year Changed to: ' . ($year['year_label'] ?? 'Unknown'), 'school_years', $_POST['year_id']);
        
        $conn->commit();
        $_SESSION['success'] = 'Active school year updated successfully!';
        header('Location: school_years.php');
        exit();
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = 'Error updating active school year: ' . $e->getMessage();
        header('Location: school_years.php');
        exit();
    }
}

// Get all school years
$stmt = $conn->prepare("
    SELECT sy.*, 
           (SELECT COUNT(*) FROM students s WHERE s.current_school_year_id = sy.id) as student_count,
           (SELECT COUNT(*) FROM sections sec WHERE sec.school_year_id = sy.id) as section_count
    FROM school_years sy 
    ORDER BY sy.start_date DESC
");
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

<div class="page-header">
    <h1>School Year Management</h1>
    <p>Set the active school year for student enrollment and records</p>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="message success">
        <?php 
        echo htmlspecialchars($_SESSION['success']); 
        unset($_SESSION['success']);
        ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="message error">
        <?php 
        echo htmlspecialchars($_SESSION['error']); 
        unset($_SESSION['error']);
        ?>
    </div>
<?php endif; ?>

<?php if ($active_year): ?>
<div class="active-year-card">
    <div class="active-year-badge">
        <span class="badge-pulse"></span>
        CURRENTLY ACTIVE
    </div>
    <h2><?php echo htmlspecialchars($active_year['year_label']); ?></h2>
    <div class="year-details">
        <div class="detail-item">
            <i class="fas fa-calendar"></i>
            <span><?php echo date('M j, Y', strtotime($active_year['start_date'])); ?> - <?php echo date('M j, Y', strtotime($active_year['end_date'])); ?></span>
        </div>
        <div class="detail-item">
            <i class="fas fa-users"></i>
            <span><?php echo $active_year['student_count']; ?> Students</span>
        </div>
        <div class="detail-item">
            <i class="fas fa-school"></i>
            <span><?php echo $active_year['section_count']; ?> Sections</span>
        </div>
    </div>
</div>
<?php else: ?>
<div class="message warning">
    <strong>No Active School Year</strong><br>
    Please select a school year to set as active.
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>All School Years</h3>
        <p>Select which school year should be active</p>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>School Year</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Students</th>
                        <th>Sections</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($school_years)): ?>
                        <tr>
                            <td colspan="7" class="text-center">
                                No school years found. Please contact the administrator to create school years.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($school_years as $year): ?>
                            <tr class="<?php echo $year['is_active'] ? 'active-row' : ''; ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($year['year_label']); ?></strong>
                                    <?php if ($year['is_active']): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($year['start_date'])); ?></td>
                                <td><?php echo date('M j, Y', strtotime($year['end_date'])); ?></td>
                                <td>
                                    <?php
                                    $today = date('Y-m-d');
                                    if ($today < $year['start_date']) {
                                        echo '<span class="badge badge-info">Upcoming</span>';
                                    } elseif ($today > $year['end_date']) {
                                        echo '<span class="badge badge-secondary">Completed</span>';
                                    } else {
                                        echo '<span class="badge badge-primary">Current Period</span>';
                                    }
                                    ?>
                                </td>
                                <td class="text-center"><?php echo $year['student_count']; ?></td>
                                <td class="text-center"><?php echo $year['section_count']; ?></td>
                                <td>
                                    <?php if (!$year['is_active']): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to set <?php echo htmlspecialchars($year['year_label']); ?> as the active school year?');">
                                            <input type="hidden" name="year_id" value="<?php echo $year['id']; ?>">
                                            <button type="submit" name="set_active" class="btn btn-primary btn-sm">
                                                <i class="fas fa-check"></i> Set Active
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">Currently Active</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.page-header {
    margin-bottom: 2rem;
}

.page-header h1 {
    color: var(--dark-blue);
    margin: 0 0 0.5rem 0;
}

.page-header p {
    color: var(--gray);
    margin: 0;
}

.active-year-card {
    background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
    color: white;
    padding: 2rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    box-shadow: 0 4px 15px rgba(46, 134, 171, 0.3);
}

.active-year-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(255, 255, 255, 0.2);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-bottom: 1rem;
}

.badge-pulse {
    width: 8px;
    height: 8px;
    background: #4ade80;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.active-year-card h2 {
    margin: 0 0 1rem 0;
    font-size: 2rem;
}

.year-details {
    display: flex;
    gap: 2rem;
    flex-wrap: wrap;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.detail-item i {
    font-size: 1.2rem;
}

.card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.card-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e0e0e0;
}

.card-header h3 {
    margin: 0 0 0.25rem 0;
    color: var(--dark-blue);
}

.card-header p {
    margin: 0;
    color: var(--gray);
    font-size: 0.9rem;
}

.card-body {
    padding: 1.5rem;
}

.table-responsive {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    background: var(--light-blue);
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--dark-blue);
    border-bottom: 2px solid var(--primary-blue);
}

.data-table td {
    padding: 1rem;
    border-bottom: 1px solid #e0e0e0;
}

.data-table tbody tr:hover {
    background: #f8f9fa;
}

.active-row {
    background: #e8f5e9 !important;
}

.active-row:hover {
    background: #c8e6c9 !important;
}

.text-center {
    text-align: center;
}

.text-muted {
    color: var(--gray);
}

.badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-left: 0.5rem;
}

.badge-success {
    background: #28a745;
    color: white;
}

.badge-info {
    background: #17a2b8;
    color: white;
}

.badge-primary {
    background: var(--primary-blue);
    color: white;
}

.badge-secondary {
    background: #6c757d;
    color: white;
}

.btn {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
}

.btn-primary {
    background: var(--primary-blue);
    color: white;
}

.btn-primary:hover {
    background: var(--dark-blue);
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(46, 134, 171, 0.3);
}

.message {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
}

.message.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.message.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.message.warning {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

@media (max-width: 768px) {
    .year-details {
        flex-direction: column;
        gap: 1rem;
    }
    
    .data-table {
        font-size: 0.875rem;
    }
    
    .data-table th,
    .data-table td {
        padding: 0.75rem 0.5rem;
    }
}
</style>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
