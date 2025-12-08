<?php
require_once '../includes/auth_check.php';

// Check if user is a student
if (!checkRole('student')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';
require_once '../classes/User.php';

try {
    $database = new Database();
    $db = $database->connect();
    $user = new User($db);
    
    // Get student information
    $student_info = $user->getStudentInfo($_SESSION['user_id']);
    
    // Get recent announcements with attachment information
    $query = "SELECT a.*, u.username as created_by_username 
              FROM announcements a 
              LEFT JOIN users u ON a.created_by = u.id
              WHERE a.is_published = 1 AND (a.target_audience = 'All' OR a.target_audience = 'Students')
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
    
} catch (Exception $e) {
    $error_message = "Unable to load student information.";
    error_log("Student dashboard error: " . $e->getMessage());
}

$page_title = 'Student Dashboard - GTBA Portal';
$base_url = '../';

ob_start();
?>

<div class="welcome-section">
    <h1 class="welcome-title">Welcome Back!</h1>
    <p class="welcome-subtitle">Your GTBA Student Portal Dashboard</p>
</div>

<!-- LRN Submission Alert -->
<?php if (isset($student_info) && empty($student_info['lrn'])): ?>
<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3); animation: pulse 2s infinite;">
    <div style="display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap;">
        <div style="font-size: 3rem;">📝</div>
        <div style="flex: 1; min-width: 250px;">
            <h3 style="color: white; margin: 0 0 0.5rem 0; font-size: 1.3rem;">Action Required: Submit Your LRN</h3>
            <p style="color: rgba(255,255,255,0.9); margin: 0; line-height: 1.5;">
                Your Learner Reference Number (LRN) is required for your student records. 
                Please submit your 12-digit LRN to complete your registration.
            </p>
        </div>
        <div>
            <a href="submit_lrn.php" style="display: inline-block; padding: 0.75rem 1.5rem; background: white; color: #667eea; text-decoration: none; border-radius: 8px; font-weight: 600; box-shadow: 0 4px 12px rgba(0,0,0,0.15); transition: all 0.3s ease;">
                <i class="fas fa-id-card"></i> Submit LRN Now
            </a>
        </div>
    </div>
</div>

<style>
@keyframes pulse {
    0%, 100% {
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
    }
    50% {
        box-shadow: 0 5px 25px rgba(102, 126, 234, 0.5);
    }
}
</style>
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
    <div class="announcements-footer">
        <a href="../shared/announcements.php" class="btn btn-outline">View All Announcements</a>
    </div>
</div>
<?php endif; ?>

<div class="dashboard-grid">
    <div class="dashboard-card">
        <div class="card-icon">📊</div>
        <h3 class="card-title">My Grades</h3>
        <p class="card-description">View your academic performance for current and previous school years.</p>
        <a href="grade_history.php" class="card-link">View Grades</a>
    </div>
    
    <div class="dashboard-card">
        <div class="card-icon">📅</div>
        <h3 class="card-title">Class Schedule</h3>
        <p class="card-description">View your weekly class schedule and room assignments.</p>
        <a href="schedule.php" class="card-link">View Schedule</a>
    </div>
    
    <div class="dashboard-card">
        <div class="card-icon">💰</div>
        <h3 class="card-title">Tuition & Other Fees</h3>
        <p class="card-description">Check tuition fees and available payment methods.</p>
        <a href="tuition.php" class="card-link">View Tuition</a>
    </div>
    
    <div class="dashboard-card">
        <div class="card-icon">💳</div>
        <h3 class="card-title">Submit Payment</h3>
        <p class="card-description">Submit payment proofs for verification by finance office.</p>
        <a href="payments.php" class="card-link">Submit Payment</a>
    </div>
    
    <div class="dashboard-card">
        <div class="card-icon">👥</div>
        <h3 class="card-title">My Section</h3>
        <p class="card-description">View information about your class section and classmates.</p>
        <a href="section.php" class="card-link">View Section</a>
    </div>
    
    <div class="dashboard-card">
        <div class="card-icon">📢</div>
        <h3 class="card-title">Announcements</h3>
        <p class="card-description">Stay updated with the latest school announcements and news.</p>
        <a href="../shared/announcements.php" class="card-link">View Announcements</a>
    </div>
    
    <div class="dashboard-card">
        <div class="card-icon">👤</div>
        <h3 class="card-title">My Profile</h3>
        <p class="card-description">View and update your personal information and contact details.</p>
        <a href="../profile/edit.php" class="card-link">Edit Profile</a>
    </div>
</div>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger mt-4">
        <?php echo $error_message; ?>
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

<style>
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

.btn {
    background: var(--primary-blue);
    color: white;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-block;
}

.btn:hover {
    background: var(--dark-blue);
    transform: translateY(-2px);
}

.btn-outline {
    background: white;
    color: var(--primary-blue);
    border: 2px solid var(--primary-blue);
}

.btn-outline:hover {
    background: var(--primary-blue);
    color: white;
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
</style>

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

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
