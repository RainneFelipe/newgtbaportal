<?php
require_once '../includes/auth_check.php';

// Only allow admin access
if (!checkRole(['admin'])) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$page_title = 'Audit Logs - Admin Panel';
$base_url = '../';

$database = new Database();
$conn = $database->connect();

// Get filter parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 25;
$offset = ($page - 1) * $limit;

$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$user_filter = $_GET['user'] ?? '';
$action_filter = $_GET['action'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($date_from)) {
    $where_conditions[] = "al.created_at >= ?";
    $params[] = $date_from . ' 00:00:00';
}

if (!empty($date_to)) {
    $where_conditions[] = "al.created_at <= ?";
    $params[] = $date_to . ' 23:59:59';
}

if (!empty($user_filter)) {
    $where_conditions[] = "(u.username LIKE ? OR u.email LIKE ?)";
    $search_param = '%' . $user_filter . '%';
    $params = array_merge($params, [$search_param, $search_param]);
}

if (!empty($action_filter)) {
    $where_conditions[] = "al.action LIKE ?";
    $params[] = '%' . $action_filter . '%';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total 
              FROM audit_logs al 
              LEFT JOIN users u ON al.user_id = u.id 
              LEFT JOIN roles r ON u.role_id = r.id
              $where_clause";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total_logs = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_logs / $limit);

// Get audit logs with proper joins
$sql = "SELECT al.*, u.username, u.email, r.display_name as role_display, r.name as role_name
        FROM audit_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        LEFT JOIN roles r ON u.role_id = r.id
        $where_clause 
        ORDER BY al.created_at DESC 
        LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$audit_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$stats_sql = "SELECT 
                COUNT(*) as total_logs,
                COUNT(DISTINCT al.user_id) as unique_users,
                COUNT(CASE WHEN al.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as logs_24h,
                COUNT(CASE WHEN al.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as logs_7d
              FROM audit_logs al";
$stmt = $conn->prepare($stats_sql);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get most common actions
$actions_sql = "SELECT action, COUNT(*) as count 
                FROM audit_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                GROUP BY action 
                ORDER BY count DESC 
                LIMIT 5";
$stmt = $conn->prepare($actions_sql);
$stmt->execute();
$common_actions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent activity per day for the last 7 days
$activity_sql = "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as count
                 FROM audit_logs 
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                 GROUP BY DATE(created_at)
                 ORDER BY date DESC";
$stmt = $conn->prepare($activity_sql);
$stmt->execute();
$daily_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="audit-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1 class="page-title">
                <span class="title-icon">🔍</span>
                Audit Logs
            </h1>
            <p class="page-subtitle">Monitor system activities and user actions</p>
        </div>
        <div class="header-actions">
            <div class="quick-stats">
                <span class="stat-item">
                    <strong><?php echo number_format($total_logs); ?></strong> Total Logs
                </span>
                <span class="stat-item">
                    <strong><?php echo $stats['logs_24h']; ?></strong> Last 24h
                </span>
            </div>
        </div>
    </div>

    <!-- Statistics Dashboard -->
    <div class="stats-dashboard">
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-header">
                    <span class="stat-icon">📊</span>
                    <h3>Total Logs</h3>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_logs']); ?></div>
                <div class="stat-label">All time records</div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-header">
                    <span class="stat-icon">👥</span>
                    <h3>Active Users</h3>
                </div>
                <div class="stat-value"><?php echo $stats['unique_users']; ?></div>
                <div class="stat-label">Unique users tracked</div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-header">
                    <span class="stat-icon">📅</span>
                    <h3>Today's Activity</h3>
                </div>
                <div class="stat-value"><?php echo $stats['logs_24h']; ?></div>
                <div class="stat-label">Last 24 hours</div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-header">
                    <span class="stat-icon">📈</span>
                    <h3>Weekly Activity</h3>
                </div>
                <div class="stat-value"><?php echo $stats['logs_7d']; ?></div>
                <div class="stat-label">Last 7 days</div>
            </div>
        </div>
    </div>

    <!-- Activity Overview -->
    <div class="activity-overview">
        <div class="overview-grid">
            <!-- Common Actions -->
            <?php if (!empty($common_actions)): ?>
            <div class="overview-card">
                <div class="card-header">
                    <h3><span class="icon">🎯</span> Top Actions (30 days)</h3>
                </div>
                <div class="card-body">
                    <div class="actions-list">
                        <?php foreach ($common_actions as $action): ?>
                            <div class="action-item">
                                <div class="action-info">
                                    <span class="action-name"><?php echo htmlspecialchars($action['action']); ?></span>
                                    <div class="action-bar">
                                        <div class="action-progress" style="width: <?php echo min(100, ($action['count'] / $common_actions[0]['count']) * 100); ?>%"></div>
                                    </div>
                                </div>
                                <span class="action-count"><?php echo $action['count']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Daily Activity -->
            <?php if (!empty($daily_activity)): ?>
            <div class="overview-card">
                <div class="card-header">
                    <h3><span class="icon">📊</span> Daily Activity</h3>
                </div>
                <div class="card-body">
                    <div class="activity-chart">
                        <?php 
                        $max_count = max(array_column($daily_activity, 'count'));
                        foreach ($daily_activity as $day): 
                        ?>
                            <div class="chart-item">
                                <div class="chart-bar">
                                    <div class="bar-fill" style="height: <?php echo $max_count > 0 ? ($day['count'] / $max_count) * 100 : 0; ?>%"></div>
                                </div>
                                <div class="chart-label">
                                    <span class="date"><?php echo date('M j', strtotime($day['date'])); ?></span>
                                    <span class="count"><?php echo $day['count']; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="filters-section">
        <div class="card">
            <div class="card-header">
                <h3><span class="icon">🔍</span> Filter Logs</h3>
                <button type="button" class="toggle-filters" onclick="toggleFilters()">
                    <span class="toggle-text">Show Filters</span>
                    <span class="toggle-icon">▼</span>
                </button>
            </div>
            <div class="card-body filters-body" style="display: none;">
                <form method="GET" class="filter-form">
                    <div class="filter-grid">
                        <div class="form-group">
                            <label for="date_from">From Date</label>
                            <input type="date" id="date_from" name="date_from" 
                                   value="<?php echo htmlspecialchars($date_from); ?>" 
                                   class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="date_to">To Date</label>
                            <input type="date" id="date_to" name="date_to" 
                                   value="<?php echo htmlspecialchars($date_to); ?>" 
                                   class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="user">User</label>
                            <input type="text" id="user" name="user" 
                                   placeholder="Search by username or email..." 
                                   value="<?php echo htmlspecialchars($user_filter); ?>" 
                                   class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="action">Action</label>
                            <input type="text" id="action" name="action" 
                                   placeholder="Search by action..." 
                                   value="<?php echo htmlspecialchars($action_filter); ?>" 
                                   class="form-control">
                        </div>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <span class="btn-icon">🔍</span> Apply Filters
                        </button>
                        <a href="audit_logs.php" class="btn btn-secondary">
                            <span class="btn-icon">🗑️</span> Clear All
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Audit Logs Table -->
    <div class="logs-section">
        <div class="card">
            <div class="card-header">
                <h3><span class="icon">📋</span> Audit Trail</h3>
                <div class="header-info">
                    <span class="results-info">
                        Showing <?php echo min($offset + 1, $total_logs); ?>-<?php echo min($offset + $limit, $total_logs); ?> 
                        of <?php echo number_format($total_logs); ?> logs
                    </span>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($audit_logs)): ?>
                <div class="table-responsive">
                    <table class="audit-table">
                        <thead>
                            <tr>
                                <th class="col-datetime">Date & Time</th>
                                <th class="col-user">User</th>
                                <th class="col-role">Role</th>
                                <th class="col-action">Action</th>
                                <th class="col-details">Details</th>
                                <th class="col-ip">IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($audit_logs as $log): ?>
                                <tr class="log-row">
                                    <td class="datetime-cell">
                                        <div class="datetime-wrapper">
                                            <span class="date"><?php echo date('M j, Y', strtotime($log['created_at'])); ?></span>
                                            <span class="time"><?php echo date('g:i A', strtotime($log['created_at'])); ?></span>
                                        </div>
                                    </td>
                                    <td class="user-cell">
                                        <?php if ($log['username']): ?>
                                            <div class="user-info">
                                                <span class="username"><?php echo htmlspecialchars($log['username']); ?></span>
                                                <span class="email"><?php echo htmlspecialchars($log['email'] ?? ''); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="system-user">System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="role-cell">
                                        <?php if ($log['role_display']): ?>
                                            <span class="role-badge role-<?php echo $log['role_name'] ?? 'unknown'; ?>">
                                                <?php echo htmlspecialchars($log['role_display']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="role-badge role-system">System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="action-cell">
                                        <span class="action-text <?php echo getActionClass($log['action']); ?>">
                                            <?php echo htmlspecialchars($log['action']); ?>
                                        </span>
                                    </td>
                                    <td class="details-cell">
                                        <div class="details-content">
                                            <?php 
                                            $details = $log['details'] ?? '';
                                            if (strlen($details) > 50) {
                                                echo '<span class="details-preview">' . htmlspecialchars(substr($details, 0, 50)) . '...</span>';
                                                echo '<span class="details-full" style="display: none;">' . htmlspecialchars($details) . '</span>';
                                                echo '<button type="button" class="details-toggle" onclick="toggleDetails(this)">Show More</button>';
                                            } else {
                                                echo htmlspecialchars($details ?: 'No details');
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    <td class="ip-cell">
                                        <code class="ip-address"><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></code>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📋</div>
                    <h3>No audit logs found</h3>
                    <p>Try adjusting your filter criteria or check back later.</p>
                </div>
                <?php endif; ?>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-wrapper">
                        <nav class="pagination">
                            <?php
                            $url_params = http_build_query([
                                'date_from' => $date_from,
                                'date_to' => $date_to,
                                'user' => $user_filter,
                                'action' => $action_filter
                            ]);
                            ?>
                            
                            <?php if ($page > 1): ?>
                                <a href="?page=1&<?php echo $url_params; ?>" class="pagination-btn first">
                                    <span class="btn-icon">⏮️</span> First
                                </a>
                                <a href="?page=<?php echo ($page - 1); ?>&<?php echo $url_params; ?>" class="pagination-btn prev">
                                    <span class="btn-icon">◀️</span> Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <a href="?page=<?php echo $i; ?>&<?php echo $url_params; ?>" 
                                   class="pagination-btn page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo ($page + 1); ?>&<?php echo $url_params; ?>" class="pagination-btn next">
                                    Next <span class="btn-icon">▶️</span>
                                </a>
                                <a href="?page=<?php echo $total_pages; ?>&<?php echo $url_params; ?>" class="pagination-btn last">
                                    Last <span class="btn-icon">⏭️</span>
                                </a>
                            <?php endif; ?>
                        </nav>
                        
                        <div class="pagination-info">
                            Page <?php echo $page; ?> of <?php echo $total_pages; ?> 
                            (<?php echo number_format($total_logs); ?> total logs)
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
function getActionClass($action) {
    $action_lower = strtolower($action);
    
    if (strpos($action_lower, 'login') !== false) return 'action-login';
    if (strpos($action_lower, 'logout') !== false) return 'action-logout';
    if (strpos($action_lower, 'create') !== false) return 'action-create';
    if (strpos($action_lower, 'update') !== false) return 'action-update';
    if (strpos($action_lower, 'delete') !== false) return 'action-delete';
    if (strpos($action_lower, 'reset') !== false) return 'action-reset';
    
    return 'action-other';
}
?>

<script>
function toggleFilters() {
    const filtersBody = document.querySelector('.filters-body');
    const toggleBtn = document.querySelector('.toggle-filters');
    const toggleText = toggleBtn.querySelector('.toggle-text');
    const toggleIcon = toggleBtn.querySelector('.toggle-icon');
    
    if (filtersBody.style.display === 'none') {
        filtersBody.style.display = 'block';
        toggleText.textContent = 'Hide Filters';
        toggleIcon.textContent = '▲';
    } else {
        filtersBody.style.display = 'none';
        toggleText.textContent = 'Show Filters';
        toggleIcon.textContent = '▼';
    }
}

function toggleDetails(button) {
    const detailsCell = button.parentNode;
    const preview = detailsCell.querySelector('.details-preview');
    const full = detailsCell.querySelector('.details-full');
    
    if (full.style.display === 'none') {
        preview.style.display = 'none';
        full.style.display = 'inline';
        button.textContent = 'Show Less';
    } else {
        preview.style.display = 'inline';
        full.style.display = 'none';
        button.textContent = 'Show More';
    }
}

// Show filters if any filter is active
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const hasFilters = urlParams.get('date_from') || urlParams.get('date_to') || 
                      urlParams.get('user') || urlParams.get('action');
    
    if (hasFilters) {
        toggleFilters();
    }
});
</script>

<style>
/* Audit Logs Specific Styles */
.audit-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 1rem;
}

/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
    border-radius: 12px;
    color: white;
}

.header-content {
    flex: 1;
}

.page-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 0 0 0.5rem 0;
    font-size: 2rem;
    font-weight: 600;
}

.title-icon {
    font-size: 2.5rem;
}

.page-subtitle {
    margin: 0;
    opacity: 0.9;
    font-size: 1.1rem;
}

.header-actions {
    display: flex;
    align-items: center;
}

.quick-stats {
    display: flex;
    gap: 1.5rem;
}

.stat-item {
    text-align: center;
    padding: 0.5rem;
    background: rgba(255,255,255,0.1);
    border-radius: 8px;
    min-width: 100px;
}

/* Stats Dashboard */
.stats-dashboard {
    margin-bottom: 2rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    border-left: 4px solid;
}

.stat-card.primary { border-left-color: var(--primary-blue); }
.stat-card.success { border-left-color: var(--success); }
.stat-card.warning { border-left-color: var(--warning); }
.stat-card.info { border-left-color: #17a2b8; }

.stat-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.stat-header h3 {
    margin: 0;
    font-size: 1rem;
    color: var(--gray);
    font-weight: 500;
}

.stat-icon {
    font-size: 1.5rem;
}

.stat-value {
    font-size: 2.5rem;
    font-weight: bold;
    color: var(--black);
    margin-bottom: 0.25rem;
}

.stat-label {
    color: var(--gray);
    font-size: 0.875rem;
}

/* Activity Overview */
.activity-overview {
    margin-bottom: 2rem;
}

.overview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 1.5rem;
}

.overview-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    overflow: hidden;
}

.overview-card .card-header {
    background: var(--light-blue);
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border-gray);
}

.overview-card .card-header h3 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--dark-blue);
}

.overview-card .card-body {
    padding: 1.5rem;
}

/* Actions List */
.actions-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.action-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: var(--light-gray);
    border-radius: 8px;
}

.action-info {
    flex: 1;
    margin-right: 1rem;
}

.action-name {
    font-weight: 600;
    display: block;
    margin-bottom: 0.5rem;
}

.action-bar {
    height: 6px;
    background: #e9ecef;
    border-radius: 3px;
    overflow: hidden;
}

.action-progress {
    height: 100%;
    background: var(--primary-blue);
    border-radius: 3px;
}

.action-count {
    font-weight: bold;
    color: var(--primary-blue);
    min-width: 50px;
    text-align: right;
}

/* Activity Chart */
.activity-chart {
    display: flex;
    gap: 0.5rem;
    align-items: end;
    height: 120px;
}

.chart-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}

.chart-bar {
    flex: 1;
    width: 100%;
    background: #f8f9fa;
    border-radius: 4px 4px 0 0;
    position: relative;
    min-height: 20px;
}

.bar-fill {
    position: absolute;
    bottom: 0;
    width: 100%;
    background: linear-gradient(to top, var(--primary-blue), var(--light-blue));
    border-radius: 4px 4px 0 0;
    min-height: 4px;
}

.chart-label {
    text-align: center;
    font-size: 0.75rem;
}

.chart-label .date {
    display: block;
    font-weight: 600;
    color: var(--black);
}

.chart-label .count {
    display: block;
    color: var(--gray);
}

/* Filters Section */
.filters-section {
    margin-bottom: 2rem;
}

.toggle-filters {
    background: none;
    border: 1px solid var(--border-gray);
    border-radius: 6px;
    padding: 0.5rem 1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--black);
}

.toggle-filters:hover {
    background: var(--light-gray);
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.filter-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-start;
}

/* Table Styles */
.logs-section .card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    overflow: hidden;
}

.table-responsive {
    overflow-x: auto;
}

.audit-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.audit-table th {
    background: var(--light-blue);
    color: var(--dark-blue);
    font-weight: 600;
    padding: 1rem 0.75rem;
    text-align: left;
    border-bottom: 2px solid var(--border-gray);
    white-space: nowrap;
}

.audit-table td {
    padding: 1rem 0.75rem;
    border-bottom: 1px solid #f1f3f4;
    vertical-align: top;
}

.log-row:hover {
    background: #fafbfc;
}

/* Column Widths */
.col-datetime { width: 140px; }
.col-user { width: 180px; }
.col-role { width: 120px; }
.col-action { width: 150px; }
.col-details { width: auto; min-width: 200px; }
.col-ip { width: 120px; }

/* Cell Styles */
.datetime-wrapper {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.datetime-wrapper .date {
    font-weight: 600;
    color: var(--black);
}

.datetime-wrapper .time {
    color: var(--gray);
    font-size: 0.8rem;
}

.user-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.username {
    font-weight: 600;
    color: var(--black);
}

.email {
    color: var(--gray);
    font-size: 0.8rem;
}

.system-user {
    color: var(--gray);
    font-style: italic;
}

/* Role Badges */
.role-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.role-student { background: #e3f2fd; color: #1565c0; }
.role-teacher { background: #f3e5f5; color: #7b1fa2; }
.role-admin { background: #ffebee; color: #c62828; }
.role-finance { background: #e8f5e8; color: #2e7d32; }
.role-registrar { background: #fff3e0; color: #ef6c00; }
.role-principal { background: #fce4ec; color: #ad1457; }
.role-system { background: #f5f5f5; color: #666; }
.role-unknown { background: #f8f9fa; color: #6c757d; }

/* Action Text */
.action-text {
    font-size: 0.875rem;
    font-weight: 600;
    text-transform: capitalize;
}

.action-login { color: #2e7d32; }
.action-logout { color: #ef6c00; }
.action-create { color: #1565c0; }
.action-update { color: #7b1fa2; }
.action-delete { color: #c62828; }
.action-reset { color: #ad1457; }
.action-other { color: #6c757d; }

/* Details Cell */
.details-content {
    max-width: 300px;
    word-wrap: break-word;
    line-height: 1.4;
}

.details-toggle {
    background: none;
    border: none;
    color: var(--primary-blue);
    cursor: pointer;
    font-size: 0.75rem;
    text-decoration: underline;
    margin-left: 0.5rem;
}

.details-toggle:hover {
    color: var(--dark-blue);
}

/* IP Address */
.ip-address {
    background: #f8f9fa;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: 0.75rem;
    color: #495057;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--gray);
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h3 {
    margin: 0 0 0.5rem 0;
    color: var(--black);
}

/* Pagination */
.pagination-wrapper {
    margin-top: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.pagination {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pagination-btn {
    padding: 0.75rem 1rem;
    border: 1px solid var(--border-gray);
    border-radius: 6px;
    text-decoration: none;
    color: var(--black);
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    background: white;
}

.pagination-btn:hover {
    background: var(--light-blue);
    border-color: var(--primary-blue);
    color: var(--dark-blue);
}

.pagination-btn.active {
    background: var(--primary-blue);
    color: white;
    border-color: var(--primary-blue);
}

.pagination-info {
    color: var(--gray);
    font-size: 0.875rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .audit-container {
        padding: 0.5rem;
    }
    
    .page-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .quick-stats {
        justify-content: center;
    }
    
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    }
    
    .overview-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-actions {
        flex-direction: column;
    }
    
    .audit-table {
        font-size: 0.75rem;
    }
    
    .audit-table th,
    .audit-table td {
        padding: 0.5rem 0.25rem;
    }
    
    .col-details {
        min-width: 150px;
    }
    
    .details-content {
        max-width: 150px;
    }
    
    .pagination-wrapper {
        flex-direction: column;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .page-title {
        font-size: 1.5rem;
    }
    
    .title-icon {
        font-size: 2rem;
    }
    
    .audit-table th,
    .audit-table td {
        padding: 0.25rem;
    }
    
    .pagination-btn {
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
    }
}
</style>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
