<?php
require_once '../includes/auth_check.php';

// Check if user is a registrar
if (!checkRole('registrar')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$page_title = 'Registrar Dashboard - GTBA Portal';
$base_url = '../';

try {
    $database = new Database();
    $db = $database->connect();
    
    // Get total students count
    $query = "SELECT COUNT(*) as total_students FROM students WHERE enrollment_status = 'Enrolled'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $total_students = $stmt->fetch(PDO::FETCH_ASSOC)['total_students'];
    
    // Get current school year
    $query = "SELECT * FROM school_years WHERE is_active = 1 LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $current_year = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get enrollment statistics by grade level
    $query = "SELECT gl.grade_name, COUNT(s.id) as student_count 
              FROM grade_levels gl 
              LEFT JOIN students s ON gl.id = s.current_grade_level_id AND s.enrollment_status = 'Enrolled'
              WHERE gl.is_active = 1
              GROUP BY gl.id, gl.grade_name, gl.grade_order
              ORDER BY gl.grade_order";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $grade_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent enrollments
    $query = "SELECT s.*, gl.grade_name, sec.section_name 
              FROM students s 
              LEFT JOIN grade_levels gl ON s.current_grade_level_id = gl.id
              LEFT JOIN sections sec ON s.current_section_id = sec.id
              ORDER BY s.created_at DESC 
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $recent_enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent announcements
    $query = "SELECT a.*, u.username as created_by_username 
              FROM announcements a 
              LEFT JOIN users u ON a.created_by = u.id
              WHERE a.is_published = 1 AND (a.target_audience = 'All' OR a.target_audience = 'Staff')
              ORDER BY a.created_at DESC 
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $recent_announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Unable to load dashboard data.";
    error_log("Registrar dashboard error: " . $e->getMessage());
}

ob_start();
?>

<div class="welcome-section">
    <h1 class="welcome-title">Registrar Dashboard</h1>
    <p class="welcome-subtitle">Golden Treasure Baptist Academy - Student Registration Management</p>
    
    <div class="user-info">
        <h4>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h4>
        <p><strong>Role:</strong> <?php echo htmlspecialchars($_SESSION['role_display']); ?></p>
        <?php if ($current_year): ?>
            <p><strong>Current School Year:</strong> <?php echo htmlspecialchars($current_year['year_label']); ?></p>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <?php echo $error_message; ?>
    </div>
<?php else: ?>
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="stat-header">
                <h3>Total Students</h3>
                <div class="stat-icon">👥</div>
            </div>
            <div class="stat-value"><?php echo number_format($total_students); ?></div>
            <div class="stat-label">Currently Enrolled</div>
        </div>
        
        <div class="stat-card success">
            <div class="stat-header">
                <h3>Grade Levels</h3>
                <div class="stat-icon">📚</div>
            </div>
            <div class="stat-value"><?php echo count($grade_stats); ?></div>
            <div class="stat-label">Available Levels</div>
        </div>
        
        <div class="stat-card info">
            <div class="stat-header">
                <h3>School Year</h3>
                <div class="stat-icon">📅</div>
            </div>
            <div class="stat-value"><?php echo $current_year ? htmlspecialchars($current_year['year_label']) : 'Not Set'; ?></div>
            <div class="stat-label">Current Period</div>
        </div>
    </div>

    <!-- Main Dashboard Content -->
    <div class="dashboard-grid">
        <!-- Quick Actions -->
        <div class="dashboard-card">
            <div class="card-icon">👤</div>
            <h3 class="card-title">Register New Student</h3>
            <p class="card-description">Create new student accounts and input complete student information including LRN, guardian details, and academic placement.</p>
            <a href="student_registration.php" class="card-link">Register Student</a>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon">📋</div>
            <h3 class="card-title">Student Records</h3>
            <p class="card-description">View, search, and manage all student records. Update student information and enrollment status.</p>
            <a href="student_records.php" class="card-link">Manage Records</a>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon">🎓</div>
            <h3 class="card-title">Student Promotion</h3>
            <p class="card-description">Promote students to the next grade level for the new school year. Bulk promotion and re-enrollment management.</p>
            <a href="student_promotion.php" class="card-link">Promote Students</a>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon">📊</div>
            <h3 class="card-title">Enrollment Reports</h3>
            <p class="card-description">Generate enrollment reports by grade level, section, and school year. Track enrollment trends and statistics.</p>
            <a href="enrollment_reports.php" class="card-link">View Reports</a>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon">📚</div>
            <h3 class="card-title">Student Grade History</h3>
            <p class="card-description">View complete academic records and grade history for any student. Track student progress across school years.</p>
            <a href="student_grade_history.php" class="card-link">View Grade History</a>
        </div>
    </div>

    <!-- Recent Activity Section -->
    <div class="recent-activity-section">
        <div class="activity-cards-grid">
            <!-- Enrollment Statistics -->
            <div class="activity-card">
                <div class="card-header">
                    <h3>Enrollment by Grade Level</h3>
                    <div class="card-icon">📊</div>
                </div>
                <div class="card-body">
                    <?php if (!empty($grade_stats)): ?>
                        <div class="grade-stats-list">
                            <?php foreach ($grade_stats as $stat): ?>
                                <div class="grade-stat-item">
                                    <div class="grade-info">
                                        <span class="grade-name"><?php echo htmlspecialchars($stat['grade_name']); ?></span>
                                        <span class="student-count"><?php echo $stat['student_count']; ?> students</span>
                                    </div>
                                    <div class="grade-progress">
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $total_students > 0 ? ($stat['student_count'] / $total_students * 100) : 0; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="no-data">No enrollment data available.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Enrollments -->
            <div class="activity-card">
                <div class="card-header">
                    <h3>Recent Enrollments</h3>
                    <div class="card-icon">👥</div>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_enrollments)): ?>
                        <div class="recent-enrollments-list">
                            <?php foreach ($recent_enrollments as $enrollment): ?>
                                <div class="enrollment-item">
                                    <div class="student-info">
                                        <div class="student-name">
                                            <?php echo htmlspecialchars($enrollment['first_name'] . ' ' . $enrollment['last_name']); ?>
                                        </div>
                                        <div class="student-details">
                                            <?php if ($enrollment['grade_name']): ?>
                                                <span class="grade"><?php echo htmlspecialchars($enrollment['grade_name']); ?></span>
                                            <?php endif; ?>
                                            <?php if ($enrollment['section_name']): ?>
                                                <span class="section"><?php echo htmlspecialchars($enrollment['section_name']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="enrollment-status">
                                        <span class="status-badge status-<?php echo strtolower($enrollment['enrollment_status']); ?>">
                                            <?php echo htmlspecialchars($enrollment['enrollment_status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="card-footer">
                            <a href="student_records.php" class="btn btn-sm btn-outline">View All Records</a>
                        </div>
                    <?php else: ?>
                        <p class="no-data">No recent enrollments.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Recent Announcements -->
<?php if (!empty($recent_announcements)): ?>
<div class="recent-announcements-section">
    <h2 class="section-title">Recent Announcements</h2>
    <div class="announcements-list">
        <?php foreach ($recent_announcements as $announcement): ?>
            <div class="announcement-item clickable-announcement" 
                 data-id="<?php echo $announcement['id']; ?>"
                 data-title="<?php echo htmlspecialchars($announcement['title']); ?>"
                 data-content="<?php echo htmlspecialchars($announcement['content']); ?>"
                 data-type="<?php echo htmlspecialchars($announcement['announcement_type']); ?>"
                 data-priority="<?php echo htmlspecialchars($announcement['priority']); ?>"
                 data-author="<?php echo htmlspecialchars($announcement['created_by_username']); ?>"
                 data-date="<?php echo date('M j, Y', strtotime($announcement['created_at'])); ?>"
                 style="cursor: pointer;">
                <div class="announcement-info">
                    <div class="announcement-title">
                        <?php echo htmlspecialchars($announcement['title']); ?>
                    </div>
                    <div class="announcement-details">
                        <span class="type"><?php echo htmlspecialchars($announcement['announcement_type']); ?></span>
                        <span class="author">by <?php echo htmlspecialchars($announcement['created_by_username']); ?></span>
                        <span class="date"><?php echo date('M j, Y', strtotime($announcement['created_at'])); ?></span>
                    </div>
                </div>
                <div class="announcement-status">
                    <span class="priority-badge priority-<?php echo strtolower($announcement['priority']); ?>">
                        <?php echo htmlspecialchars($announcement['priority']); ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="announcements-footer">
        <a href="../shared/announcements.php" class="btn btn-outline">View All Announcements</a>
    </div>
</div>
<?php endif; ?>

<style>
/* Registrar Dashboard Specific Styles */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--white);
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    border-left: 5px solid var(--primary-blue);
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-card.primary { border-left-color: var(--primary-blue); }
.stat-card.success { border-left-color: var(--success); }
.stat-card.warning { border-left-color: var(--warning); }
.stat-card.info { border-left-color: #17a2b8; }

.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.stat-header h3 {
    color: var(--black);
    font-size: 1rem;
    font-weight: 600;
    margin: 0;
}

.stat-icon {
    font-size: 1.5rem;
    opacity: 0.8;
}

.stat-value {
    font-size: 2.5rem;
    font-weight: bold;
    color: var(--dark-blue);
    margin-bottom: 0.5rem;
}

.stat-label {
    color: var(--gray);
    font-size: 0.9rem;
}

.recent-activity-section {
    margin-top: 2rem;
}

.activity-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 2rem;
}

.activity-card {
    background: var(--white);
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    overflow: hidden;
}

.activity-card .card-header {
    background: var(--light-blue);
    padding: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--border-gray);
}

.activity-card .card-header h3 {
    color: var(--dark-blue);
    margin: 0;
    font-weight: 600;
}

.activity-card .card-body {
    padding: 1.5rem;
}

.grade-stats-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.grade-stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: var(--light-gray);
    border-radius: 8px;
}

.grade-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.grade-name {
    font-weight: 600;
    color: var(--black);
}

.student-count {
    font-size: 0.85rem;
    color: var(--gray);
}

.grade-progress {
    width: 100px;
}

.progress-bar {
    width: 100%;
    height: 6px;
    background: var(--border-gray);
    border-radius: 3px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: var(--primary-blue);
    border-radius: 3px;
    transition: width 0.3s ease;
}

.recent-enrollments-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.enrollment-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: var(--light-gray);
    border-radius: 8px;
}

.student-info {
    flex: 1;
}

.student-name {
    font-weight: 600;
    color: var(--black);
    margin-bottom: 0.25rem;
}

.student-details {
    display: flex;
    gap: 1rem;
    font-size: 0.85rem;
    color: var(--gray);
}

.grade, .section {
    background: var(--white);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
}

.enrollment-status {
    flex-shrink: 0;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-enrolled { background: #e8f5e8; color: #2e7d32; }
.status-pending { background: #fff3e0; color: #ef6c00; }
.status-transferred { background: #e3f2fd; color: #1565c0; }

.card-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-gray);
    background: var(--light-gray);
}

.btn-outline {
    background: transparent;
    border: 2px solid var(--primary-blue);
    color: var(--primary-blue);
}

.btn-outline:hover {
    background: var(--primary-blue);
    color: var(--white);
}

.no-data {
    text-align: center;
    color: var(--gray);
    font-style: italic;
    padding: 2rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .activity-cards-grid {
        grid-template-columns: 1fr;
    }
    
    .enrollment-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .student-details {
        flex-direction: column;
        gap: 0.5rem;
    }
}

/* Recent Announcements Styles */
.recent-announcements-section {
    background: var(--white);
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.section-title {
    color: var(--dark-blue);
    margin-bottom: 1.5rem;
    font-size: 1.5rem;
    font-weight: 700;
}

.announcements-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.announcement-item {
    background: var(--white);
    border: 1px solid var(--border-gray);
    border-radius: 10px;
    padding: 1.5rem;
    transition: all 0.3s ease;
    border-left: 4px solid var(--primary-blue);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.announcement-item:hover {
    background: var(--light-blue);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.announcement-info {
    flex: 1;
}

.announcement-title {
    font-weight: 600;
    color: var(--dark-blue);
    margin-bottom: 0.5rem;
    font-size: 1.1rem;
}

.announcement-details {
    display: flex;
    gap: 1rem;
    font-size: 0.9rem;
    color: var(--gray);
    flex-wrap: wrap;
}

.announcement-status {
    display: flex;
    align-items: center;
}

.priority-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.priority-low { background: #e8f5e8; color: #2e7d32; }
.priority-normal { background: #e3f2fd; color: #1565c0; }
.priority-high { background: #fff3e0; color: #ef6c00; }
.priority-urgent { background: #ffebee; color: #c62828; }

.announcements-footer {
    text-align: center;
    margin-top: 1.5rem;
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
    background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
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
    background: var(--light-gray);
    padding: 1rem 2rem;
    border-top: 1px solid var(--border-gray);
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
