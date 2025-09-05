<?php
require_once '../includes/auth_check.php';

// Only allow admin access
if (!checkRole(['admin'])) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$page_title = 'Admin Dashboard - GTBA Portal';
$base_url = '../';

// Get statistics for dashboard
$database = new Database();
$conn = $database->connect();

// Get user counts by role
$user_stats = [];
try {
    $stmt = $conn->prepare("SELECT role, COUNT(*) as count FROM users WHERE status = 'active' GROUP BY role");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $user_stats[$row['role']] = $row['count'];
    }
} catch (PDOException $e) {
    $user_stats = [];
}

// Get total students enrolled this year
$total_students = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT s.user_id) as count FROM students s 
                           JOIN student_enrollments se ON s.user_id = se.student_id 
                           JOIN school_years sy ON se.school_year_id = sy.id 
                           WHERE sy.is_active = 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_students = $result['count'];
} catch (PDOException $e) {
    $total_students = 0;
}

// Get recent audit logs
$recent_logs = [];
try {
    $stmt = $conn->prepare("SELECT al.*, u.username 
                           FROM audit_logs al 
                           LEFT JOIN users u ON al.user_id = u.id 
                           ORDER BY al.created_at DESC 
                           LIMIT 10");
    $stmt->execute();
    $recent_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_logs = [];
}

// Get recent announcements
$recent_announcements = [];
try {
    $stmt = $conn->prepare("SELECT a.*, u.username as created_by_username 
                           FROM announcements a 
                           LEFT JOIN users u ON a.created_by = u.id
                           WHERE a.is_published = 1
                           ORDER BY a.created_at DESC 
                           LIMIT 5");
    $stmt->execute();
    $recent_announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_announcements = [];
}

ob_start();
?>

<div class="welcome-section">
    <h1 class="welcome-title">Admin Dashboard</h1>
    <p class="welcome-subtitle">System Administration & User Management</p>
</div>



<!-- Admin Features Grid -->
<div class="admin-features">
    <h2 class="admin-section-title">Administrative Functions</h2>
    
    <div class="dashboard-grid">
        <!-- User Management -->
        <div class="dashboard-card">
            <div class="card-icon">👥</div>
            <h3 class="card-title">User Management</h3>
            <p class="card-description">Create, edit, and manage all user accounts across all roles.</p>
            <a href="users.php" class="card-link">Manage Users</a>
        </div>


        <!-- School Year Management -->
        <div class="dashboard-card">
            <div class="card-icon">📅</div>
            <h3 class="card-title">School Years</h3>
            <p class="card-description">Manage school years, set active periods, and academic calendars.</p>
            <a href="school_years.php" class="card-link">Manage School Years</a>
        </div>

        <!-- Audit Logs -->
        <div class="dashboard-card">
            <div class="card-icon">📊</div>
            <h3 class="card-title">Audit Logs</h3>
            <p class="card-description">View comprehensive system audit trail and user activities.</p>
            <a href="audit_logs.php" class="card-link">View Audit Logs</a>
        </div>

        <!-- System Settings -->
        <div class="dashboard-card">
            <div class="card-icon">⚙️</div>
            <h3 class="card-title">System Settings</h3>
            <p class="card-description">Configure system-wide settings and preferences.</p>
            <a href="settings.php" class="card-link">System Settings</a>
        </div>

        <!-- Reports -->
        <div class="dashboard-card">
            <div class="card-icon">📈</div>
            <h3 class="card-title">Reports</h3>
            <p class="card-description">Generate comprehensive reports on users, enrollment, and activities.</p>
            <a href="reports.php" class="card-link">Generate Reports</a>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<?php if (!empty($recent_logs)): ?>
<div class="recent-activity">
    <h2 class="admin-section-title">Recent System Activity</h2>
    <div class="activity-list">
        <?php foreach ($recent_logs as $log): ?>
            <div class="activity-item">
                <div class="activity-icon">📝</div>
                <div class="activity-content">
                    <p class="activity-action"><?php echo htmlspecialchars($log['action']); ?></p>
                    <p class="activity-details"><?php echo htmlspecialchars($log['details'] ?? ''); ?></p>
                    <span class="activity-meta">
                        by <?php echo htmlspecialchars($log['username'] ?? 'System'); ?> 
                        • <?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="text-center" style="margin-top: 1rem;">
        <a href="audit_logs.php" class="btn btn-outline">View All Activity</a>
    </div>
</div>
<?php endif; ?>

<!-- Recent Announcements -->
<?php if (!empty($recent_announcements)): ?>
<div class="recent-activity">
    <h2 class="admin-section-title">Recent Announcements</h2>
    <div class="activity-list">
        <?php foreach ($recent_announcements as $announcement): ?>
            <div class="activity-item clickable-announcement" 
                 data-id="<?php echo $announcement['id']; ?>"
                 data-title="<?php echo htmlspecialchars($announcement['title']); ?>"
                 data-content="<?php echo htmlspecialchars($announcement['content']); ?>"
                 data-type="<?php echo htmlspecialchars($announcement['announcement_type']); ?>"
                 data-priority="<?php echo htmlspecialchars($announcement['priority']); ?>"
                 data-author="<?php echo htmlspecialchars($announcement['created_by_username']); ?>"
                 data-date="<?php echo date('M j, Y', strtotime($announcement['created_at'])); ?>"
                 style="cursor: pointer;">
                <div class="activity-icon">📢</div>
                <div class="activity-content">
                    <p class="activity-action"><?php echo htmlspecialchars($announcement['title']); ?></p>
                    <p class="activity-details"><?php echo htmlspecialchars($announcement['announcement_type']); ?></p>
                    <span class="activity-meta">
                        by <?php echo htmlspecialchars($announcement['created_by_username']); ?> 
                        • <?php echo date('M j, Y g:i A', strtotime($announcement['created_at'])); ?>
                        • <span class="priority-badge priority-<?php echo strtolower($announcement['priority']); ?>">
                            <?php echo htmlspecialchars($announcement['priority']); ?>
                        </span>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="text-center" style="margin-top: 1rem;">
        <a href="../shared/announcements.php" class="btn btn-outline">View All Announcements</a>
    </div>
</div>
<?php endif; ?>

<!-- Quick Actions -->
<div class="quick-actions">
    <h2 class="admin-section-title">Quick Actions</h2>
    <div class="action-buttons">
        <a href="users.php?action=create" class="btn btn-primary">
            <span>➕</span> Create New User
        </a>
        <a href="students.php?action=create" class="btn btn-secondary">
            <span>🎓</span> Add Student
        </a>
        <a href="teachers.php?action=create" class="btn btn-secondary">
            <span>👨‍🏫</span> Add Teacher
        </a>
        <a href="school_years.php?action=create" class="btn btn-secondary">
            <span>📅</span> New School Year
        </a>
    </div>
</div>

<style>
/* Admin Dashboard Specific Styles */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 1rem;
    border-left: 4px solid var(--primary);
}

.stat-icon {
    font-size: 2rem;
}

.stat-content h3 {
    font-size: 2rem;
    color: var(--primary);
    margin: 0;
}

.stat-content p {
    margin: 0.25rem 0 0 0;
    color: var(--gray);
    font-size: 0.875rem;
}

.admin-features {
    margin-bottom: 2rem;
}

.admin-features .admin-section-title,
.recent-activity .admin-section-title,
.quick-actions .admin-section-title {
    color: var(--primary);
    margin-bottom: 1rem;
    font-size: 1.5rem;
    font-weight: 600;
}

.recent-activity {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.activity-list {
    max-height: 400px;
    overflow-y: auto;
}

.activity-item {
    display: flex;
    gap: 1rem;
    padding: 1rem 0;
    border-bottom: 1px solid #eee;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    font-size: 1.25rem;
    margin-top: 0.25rem;
}

.activity-content {
    flex: 1;
}

.activity-action {
    font-weight: 600;
    margin: 0 0 0.25rem 0;
    color: var(--text);
}

.activity-details {
    margin: 0 0 0.25rem 0;
    color: var(--gray);
    font-size: 0.875rem;
}

.activity-meta {
    font-size: 0.75rem;
    color: var(--gray);
}

.quick-actions {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.action-buttons {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.action-buttons .btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
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

.text-center {
    text-align: center;
}

/* Priority Badge Styles */
.priority-badge {
    padding: 0.15rem 0.5rem;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
}

.priority-low { background: #e8f5e8; color: #2e7d32; }
.priority-normal { background: #e3f2fd; color: #1565c0; }
.priority-high { background: #fff3e0; color: #ef6c00; }
.priority-urgent { background: #ffebee; color: #c62828; }

/* Clickable Announcement Hover Effect */
.clickable-announcement:hover {
    background: #f8f9fa;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* Announcement Modal Styles */
.announcement-modal {
    display: flex;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    align-items: center;
    justify-content: center;
    visibility: hidden;
    opacity: 0;
    transition: visibility 0s, opacity 0.3s ease;
}

.announcement-modal.show {
    visibility: visible;
    opacity: 1;
}

.modal-content {
    background: white;
    margin: 2rem;
    padding: 0;
    border-radius: 15px;
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideIn 0.3s ease;
}

.modal-header {
    background: linear-gradient(135deg, var(--primary), #1a73e8);
    color: white;
    padding: 1.5rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
}

.modal-title {
    font-size: 1.4rem;
    font-weight: 700;
    margin: 0;
    line-height: 1.3;
    flex: 1;
}

.modal-close {
    background: none;
    border: none;
    color: white;
    font-size: 1.8rem;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background 0.3s ease;
    flex-shrink: 0;
}

.modal-close:hover {
    background: rgba(255, 255, 255, 0.2);
}

.modal-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-top: 0.75rem;
    font-size: 0.9rem;
    opacity: 0.9;
}

.modal-meta span {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.modal-body {
    padding: 2rem;
    overflow-y: auto;
    max-height: 50vh;
}

.modal-content-text {
    font-size: 1rem;
    line-height: 1.6;
    color: var(--text);
    white-space: pre-wrap;
}

.modal-footer {
    background: #f8f9fa;
    padding: 1rem 2rem;
    border-top: 1px solid #dee2e6;
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

.modal-priority {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideIn {
    from { 
        opacity: 0;
        transform: translateY(-50px) scale(0.9);
    }
    to { 
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
</style>

<!-- Announcement Modal -->
<div id="announcementModal" class="announcement-modal">
    <div class="modal-content">
        <div class="modal-header">
            <div>
                <h3 class="modal-title" id="modalTitle"></h3>
                <div class="modal-meta">
                    <span>📅 <span id="modalDate"></span></span>
                    <span>👤 <span id="modalAuthor"></span></span>
                    <span>📝 <span id="modalType"></span></span>
                    <span class="modal-priority" id="modalPriority"></span>
                </div>
            </div>
            <button class="modal-close" onclick="closeAnnouncementModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="modal-content-text" id="modalContent"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeAnnouncementModal()">Close</button>
        </div>
    </div>
</div>

<script>
// Announcement Modal Functions
function openAnnouncementModal(announcement) {
    const modal = document.getElementById('announcementModal');
    const title = document.getElementById('modalTitle');
    const content = document.getElementById('modalContent');
    const date = document.getElementById('modalDate');
    const author = document.getElementById('modalAuthor');
    const type = document.getElementById('modalType');
    const priority = document.getElementById('modalPriority');
    
    title.textContent = announcement.title;
    content.textContent = announcement.content;
    date.textContent = announcement.date;
    author.textContent = announcement.author;
    type.textContent = announcement.type;
    priority.textContent = announcement.priority;
    
    // Set priority styling
    priority.className = 'modal-priority priority-' + announcement.priority.toLowerCase();
    
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeAnnouncementModal() {
    const modal = document.getElementById('announcementModal');
    modal.classList.remove('show');
    document.body.style.overflow = 'auto';
}

// Add click event listeners to announcement items
document.addEventListener('DOMContentLoaded', function() {
    const announcementItems = document.querySelectorAll('.clickable-announcement');
    
    announcementItems.forEach(item => {
        item.addEventListener('click', function() {
            const announcement = {
                id: this.dataset.id,
                title: this.dataset.title,
                content: this.dataset.content,
                type: this.dataset.type,
                priority: this.dataset.priority,
                author: this.dataset.author,
                date: this.dataset.date
            };
            
            openAnnouncementModal(announcement);
        });
    });
    
    // Close modal when clicking outside
    document.getElementById('announcementModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeAnnouncementModal();
        }
    });
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAnnouncementModal();
        }
    });
});
</script>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
