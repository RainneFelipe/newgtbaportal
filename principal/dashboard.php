<?php
require_once '../includes/auth_check.php';

// Check if user is a principal
if (!checkRole('principal')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$page_title = 'Principal Dashboard - GTBA Portal';
$base_url = '../';

try {
    $database = new Database();
    $db = $database->connect();
    
    // Get current school year
    $query = "SELECT * FROM school_years WHERE is_active = 1 LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $current_year = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get total sections count
    $query = "SELECT COUNT(*) as total_sections FROM sections WHERE is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $total_sections = $stmt->fetch(PDO::FETCH_ASSOC)['total_sections'];
    
    // Get total teachers count (from users table with teacher role)
    $query = "SELECT COUNT(*) as total_teachers 
              FROM users u 
              JOIN roles r ON u.role_id = r.id 
              WHERE r.name = 'teacher' AND u.is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $total_teachers = $stmt->fetch(PDO::FETCH_ASSOC)['total_teachers'];
    
    // Get total students enrolled (count students with current_section_id)
    $query = "SELECT COUNT(*) as total_students FROM students WHERE current_section_id IS NOT NULL";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $total_students = $stmt->fetch(PDO::FETCH_ASSOC)['total_students'];
    
    // Get subjects count
    $query = "SELECT COUNT(*) as total_subjects FROM subjects WHERE is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $total_subjects = $stmt->fetch(PDO::FETCH_ASSOC)['total_subjects'];
    
    // Get students by grade level (count students currently enrolled in sections for each grade)
    $query = "SELECT gl.grade_name, COUNT(s.id) as student_count 
              FROM grade_levels gl 
              LEFT JOIN sections sec ON gl.id = sec.grade_level_id AND sec.is_active = 1
              LEFT JOIN students s ON sec.id = s.current_section_id
              WHERE gl.is_active = 1
              GROUP BY gl.id, gl.grade_name, gl.grade_order
              ORDER BY gl.grade_order";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $students_by_grade = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent announcements with attachment information
    $query = "SELECT a.*, u.username as created_by_username 
              FROM announcements a 
              LEFT JOIN users u ON a.created_by = u.id
              WHERE a.is_published = 1
              ORDER BY a.created_at DESC 
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $recent_announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get attachments for each announcement
    foreach ($recent_announcements as &$announcement) {
        $attachment_query = "SELECT id, filename, original_filename, file_path, mime_type, file_size 
                            FROM announcement_attachments 
                            WHERE announcement_id = ? 
                            ORDER BY created_at";
        $attachment_stmt = $db->prepare($attachment_query);
        $attachment_stmt->execute([$announcement['id']]);
        $announcement['attachments'] = $attachment_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get class schedules count
    $query = "SELECT COUNT(*) as total_schedules FROM class_schedules WHERE is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $total_schedules = $stmt->fetch(PDO::FETCH_ASSOC)['total_schedules'];
    
} catch (Exception $e) {
    $error_message = "Unable to load dashboard data.";
    error_log("Principal dashboard error: " . $e->getMessage());
}

ob_start();
?>

<div class="welcome-section">
    <h1 class="welcome-title">Principal Dashboard</h1>
    <p class="welcome-subtitle">Golden Treasure Baptist Academy - Academic Management & Administration</p>
    
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
                <h3>Sections</h3>
                <div class="stat-icon">🏫</div>
            </div>
            <div class="stat-value"><?php echo number_format($total_sections); ?></div>
            <div class="stat-label">Active Sections</div>
        </div>
        
        <div class="stat-card info">
            <div class="stat-header">
                <h3>Teachers</h3>
                <div class="stat-icon">👨‍🏫</div>
            </div>
            <div class="stat-value"><?php echo number_format($total_teachers); ?></div>
            <div class="stat-label">Active Teachers</div>
        </div>
        
        <div class="stat-card warning">
            <div class="stat-header">
                <h3>Subjects</h3>
                <div class="stat-icon">📚</div>
            </div>
            <div class="stat-value"><?php echo number_format($total_subjects); ?></div>
            <div class="stat-label">Available Subjects</div>
        </div>
        
        <div class="stat-card secondary">
            <div class="stat-header">
                <h3>Class Schedules</h3>
                <div class="stat-icon">📅</div>
            </div>
            <div class="stat-value"><?php echo number_format($total_schedules); ?></div>
            <div class="stat-label">Active Schedules</div>
        </div>
        
        <div class="stat-card accent">
            <div class="stat-header">
                <h3>School Year</h3>
                <div class="stat-icon">🗓️</div>
            </div>
            <div class="stat-value"><?php echo $current_year ? htmlspecialchars($current_year['year_label']) : 'Not Set'; ?></div>
            <div class="stat-label">Current Period</div>
        </div>
    </div>

    <!-- Main Dashboard Content -->
    <div class="dashboard-grid">
        <!-- Academic Management -->
        <div class="dashboard-card">
            <div class="card-icon">🏫</div>
            <h3 class="card-title">Section Management</h3>
            <p class="card-description">Create and manage class sections for different grade levels and school years.</p>
            <a href="sections.php" class="card-link">Manage Sections</a>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon">📅</div>
            <h3 class="card-title">Class Schedules</h3>
            <p class="card-description">Create and manage class schedules, assign subjects to sections, and set time slots.</p>
            <a href="schedules.php" class="card-link">Manage Schedules</a>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon">📚</div>
            <h3 class="card-title">Subject & Curriculum Management</h3>
            <p class="card-description">Manage subjects and curriculum assignments for each grade level and school year.</p>
            <a href="subject_curriculum.php" class="card-link">Manage Curriculum</a>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon">👨‍🏫</div>
            <h3 class="card-title">Teacher Assignments</h3>
            <p class="card-description">Assign multiple teachers to sections and designate primary teachers for each class.</p>
            <a href="teacher_assignments.php" class="card-link">Manage Assignments</a>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon">📢</div>
            <h3 class="card-title">Announcements</h3>
            <p class="card-description">Create and publish school announcements for students, teachers, and staff.</p>
            <a href="announcements.php" class="card-link">Manage Announcements</a>
        </div>
        
        <div class="dashboard-card">
            <div class="card-icon">📊</div>
            <h3 class="card-title">Academic Reports</h3>
            <p class="card-description">Generate academic reports, enrollment statistics, and performance analytics.</p>
            <a href="reports.php" class="card-link">View Reports</a>
        </div>
    </div>

    <!-- Recent Activity Section -->
    <div class="recent-activity-section">
        <div class="activity-cards-grid">
            <!-- Students by Grade -->
            <div class="activity-card">
                <div class="card-header">
                    <h3>Students by Grade Level</h3>
                    <div class="card-icon">👥</div>
                </div>
                <div class="card-body">
                    <?php if (!empty($students_by_grade)): ?>
                        <div class="grade-sections-list">
                            <?php foreach ($students_by_grade as $grade): ?>
                                <div class="grade-section-item">
                                    <div class="grade-info">
                                        <span class="grade-name"><?php echo htmlspecialchars($grade['grade_name']); ?></span>
                                        <span class="section-count"><?php echo $grade['student_count']; ?> students</span>
                                    </div>
                                    <div class="grade-progress">
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $total_students > 0 ? ($grade['student_count'] / $total_students * 100) : 0; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="no-data">No student enrollment data available.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Announcements -->
            <div class="activity-card">
                <div class="card-header">
                    <h3>Recent Announcements</h3>
                    <div class="card-icon">📢</div>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_announcements)): ?>
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
                                     data-attachments="<?php echo htmlspecialchars(json_encode($announcement['attachments'])); ?>"
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
                        <div class="card-footer">
                            <a href="announcements.php" class="btn btn-sm btn-outline">View All Announcements</a>
                        </div>
                    <?php else: ?>
                        <p class="no-data">No recent announcements.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

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
            <div class="modal-attachments" id="modalAttachments"></div>
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
    const attachments = document.getElementById('modalAttachments');
    
    title.textContent = announcement.title;
    content.textContent = announcement.content;
    date.textContent = announcement.date;
    author.textContent = announcement.author;
    type.textContent = announcement.type;
    priority.textContent = announcement.priority;
    
    // Set priority styling
    priority.className = 'modal-priority priority-' + announcement.priority.toLowerCase();
    
    // Display attachments if any
    if (announcement.attachments && announcement.attachments.length > 0) {
        let attachmentsHtml = `
            <div class="modal-attachments-title">
                <i class="fas fa-images"></i>
                Attachments (${announcement.attachments.length})
            </div>
            <div class="modal-attachments-grid">
        `;
        
        announcement.attachments.forEach(attachment => {
            const fileSizeKB = Math.round(attachment.file_size / 1024);
            attachmentsHtml += `
                <div class="modal-attachment-item" onclick="openImageLightbox('${attachment.file_path}', '${attachment.original_filename}')">
                    <img src="${attachment.file_path}" alt="${attachment.original_filename}" class="modal-attachment-image" loading="lazy">
                    <div class="modal-attachment-info">
                        <div class="modal-attachment-name">${attachment.original_filename}</div>
                        <div class="modal-attachment-size">${fileSizeKB} KB</div>
                    </div>
                </div>
            `;
        });
        
        attachmentsHtml += '</div>';
        attachments.innerHTML = attachmentsHtml;
        attachments.style.display = 'block';
    } else {
        attachments.innerHTML = '';
        attachments.style.display = 'none';
    }
    
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function openImageLightbox(imagePath, imageName) {
    // Create lightbox if it doesn't exist
    let lightbox = document.getElementById('imageLightbox');
    if (!lightbox) {
        lightbox = document.createElement('div');
        lightbox.id = 'imageLightbox';
        lightbox.className = 'image-lightbox';
        lightbox.innerHTML = `
            <span class="lightbox-close" onclick="closeImageLightbox()">&times;</span>
            <img class="lightbox-content" id="lightboxImage">
        `;
        document.body.appendChild(lightbox);
    }
    
    const lightboxImage = document.getElementById('lightboxImage');
    lightboxImage.src = imagePath;
    lightboxImage.alt = imageName;
    lightbox.classList.add('show');
}

function closeImageLightbox() {
    const lightbox = document.getElementById('imageLightbox');
    if (lightbox) {
        lightbox.classList.remove('show');
    }
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
            let attachments = [];
            try {
                attachments = JSON.parse(this.dataset.attachments || '[]');
            } catch (e) {
                console.warn('Error parsing attachments:', e);
                attachments = [];
            }
            
            const announcement = {
                id: this.dataset.id,
                title: this.dataset.title,
                content: this.dataset.content,
                type: this.dataset.type,
                priority: this.dataset.priority,
                author: this.dataset.author,
                date: this.dataset.date,
                attachments: attachments
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
            closeImageLightbox();
        }
    });
    
    // Close lightbox when clicking outside image
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('image-lightbox')) {
            closeImageLightbox();
        }
    });
});
</script>

<style>
/* Principal Dashboard Specific Styles */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
.stat-card.secondary { border-left-color: #6c757d; }
.stat-card.accent { border-left-color: #e83e8c; }

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

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
    margin-bottom: 3rem;
}

.dashboard-card {
    background: var(--white);
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    border: 1px solid var(--border-gray);
}

.dashboard-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}

.dashboard-card .card-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    display: block;
}

.dashboard-card .card-title {
    color: var(--dark-blue);
    font-size: 1.4rem;
    font-weight: 700;
    margin-bottom: 1rem;
}

.dashboard-card .card-description {
    color: var(--gray);
    margin-bottom: 1.5rem;
    line-height: 1.6;
}

.dashboard-card .card-link {
    display: inline-block;
    background: var(--primary-blue);
    color: var(--white);
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: background 0.3s ease;
}

.dashboard-card .card-link:hover {
    background: var(--dark-blue);
    color: var(--white);
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

.grade-sections-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.grade-section-item {
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

.section-count {
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

.announcements-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.announcement-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: var(--light-gray);
    border-radius: 8px;
}

.announcement-info {
    flex: 1;
}

.announcement-title {
    font-weight: 600;
    color: var(--black);
    margin-bottom: 0.25rem;
}

.announcement-details {
    display: flex;
    gap: 1rem;
    font-size: 0.85rem;
    color: var(--gray);
}

.type, .author, .date {
    background: var(--white);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
}

.announcement-status {
    flex-shrink: 0;
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
@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    }
    
    .dashboard-grid {
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    .activity-cards-grid {
        grid-template-columns: 1fr;
    }
    
    .announcement-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .announcement-details {
        flex-direction: column;
        gap: 0.5rem;
    }
}

/* Announcement Item Hover Effect */
.clickable-announcement:hover {
    background: var(--light-blue);
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

.modal-attachments {
    margin-top: 1.5rem;
}

.modal-attachments-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark-blue);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-attachments-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.modal-attachment-item {
    position: relative;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
    cursor: pointer;
}

.modal-attachment-item:hover {
    transform: scale(1.02);
}

.modal-attachment-image {
    width: 100%;
    height: 150px;
    object-fit: cover;
    display: block;
}

.modal-attachment-info {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(transparent, rgba(0,0,0,0.8));
    color: white;
    padding: 1rem 0.75rem 0.75rem;
    font-size: 0.8rem;
}

.modal-attachment-name {
    font-weight: 600;
    margin-bottom: 0.25rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.modal-attachment-size {
    opacity: 0.8;
}

/* Image lightbox */
.image-lightbox {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.9);
    align-items: center;
    justify-content: center;
}

.image-lightbox.show {
    display: flex;
}

.lightbox-content {
    max-width: 90%;
    max-height: 90%;
    object-fit: contain;
    border-radius: 8px;
}

.lightbox-close {
    position: absolute;
    top: 20px;
    right: 30px;
    color: white;
    font-size: 40px;
    font-weight: bold;
    cursor: pointer;
    z-index: 2001;
}

.lightbox-close:hover {
    opacity: 0.7;
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

@media (max-width: 768px) {
    .modal-content {
        margin: 1rem;
        width: calc(100% - 2rem);
    }
    
    .modal-header {
        padding: 1rem 1.5rem;
    }
    
    .modal-title {
        font-size: 1.2rem;
    }
    
    .modal-body {
        padding: 1.5rem;
    }
    
    .modal-footer {
        padding: 1rem 1.5rem;
    }
}
</style>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
