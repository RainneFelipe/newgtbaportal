<?php
require_once '../includes/auth_check.php';

// Check if user is a principal
if (!checkRole('principal')) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$page_title = 'Announcements Management - GTBA Portal';
$base_url = '../';

// Function to handle file uploads
function handleFileUpload($files, $announcement_id, $db) {
    $upload_dir = '../uploads/announcements/';
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $max_file_size = 5 * 1024 * 1024; // 5MB
    
    $uploaded_files = [];
    
    if (!empty($files['attachment']['name'][0])) {
        for ($i = 0; $i < count($files['attachment']['name']); $i++) {
            if ($files['attachment']['error'][$i] === UPLOAD_ERR_OK) {
                $file_tmp = $files['attachment']['tmp_name'][$i];
                $file_name = $files['attachment']['name'][$i];
                $file_size = $files['attachment']['size'][$i];
                $file_type = $files['attachment']['type'][$i];
                
                // Validate file type
                if (!in_array($file_type, $allowed_types)) {
                    continue;
                }
                
                // Validate file size
                if ($file_size > $max_file_size) {
                    continue;
                }
                
                // Generate unique filename
                $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                $unique_filename = 'announcement_' . $announcement_id . '_' . time() . '_' . $i . '.' . $file_extension;
                $file_path = $upload_dir . $unique_filename;
                
                // Move uploaded file
                if (move_uploaded_file($file_tmp, $file_path)) {
                    // Save to database
                    $query = "INSERT INTO announcement_attachments (announcement_id, filename, original_filename, file_path, file_size, mime_type) 
                              VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$announcement_id, $unique_filename, $file_name, $file_path, $file_size, $file_type]);
                    
                    $uploaded_files[] = $unique_filename;
                }
            }
        }
    }
    
    return $uploaded_files;
}

try {
    $database = new Database();
    $db = $database->connect();
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_announcement':
                    $title = trim($_POST['title']);
                    $content = trim($_POST['content']);
                    $announcement_type = $_POST['announcement_type'];
                    $priority = $_POST['priority'];
                    $target_audience = $_POST['target_audience'];
                    $is_published = isset($_POST['is_published']) ? 1 : 0;
                    $publish_date = $_POST['publish_date'] ?: date('Y-m-d');
                    $expiry_date = $_POST['expiry_date'] ?: null;
                    
                    if ($title && $content && $announcement_type && $priority && $target_audience) {
                        // Start transaction
                        $db->beginTransaction();
                        
                        try {
                            $query = "INSERT INTO announcements (title, content, announcement_type, priority, target_audience, 
                                      is_published, publish_date, expiry_date, created_by, created_at) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                            $stmt = $db->prepare($query);
                            if ($stmt->execute([$title, $content, $announcement_type, $priority, $target_audience, 
                                              $is_published, $publish_date, $expiry_date, $_SESSION['user_id']])) {
                                
                                $announcement_id = $db->lastInsertId();
                                
                                // Handle file uploads
                                $uploaded_files = handleFileUpload($_FILES, $announcement_id, $db);
                                
                                // Update attachment count
                                if (!empty($uploaded_files)) {
                                    $update_query = "UPDATE announcements SET has_attachments = 1, attachment_count = ? WHERE id = ?";
                                    $update_stmt = $db->prepare($update_query);
                                    $update_stmt->execute([count($uploaded_files), $announcement_id]);
                                }
                                
                                $db->commit();
                                $success_message = "Announcement created successfully!";
                                if (!empty($uploaded_files)) {
                                    $success_message .= " " . count($uploaded_files) . " image(s) uploaded.";
                                }
                            } else {
                                $db->rollback();
                                $error_message = "Failed to create announcement.";
                            }
                        } catch (Exception $e) {
                            $db->rollback();
                            $error_message = "Error creating announcement: " . $e->getMessage();
                        }
                    } else {
                        $error_message = "Please fill in all required fields.";
                    }
                    break;
                    
                case 'update_announcement':
                    $announcement_id = $_POST['announcement_id'];
                    $title = trim($_POST['title']);
                    $content = trim($_POST['content']);
                    $announcement_type = $_POST['announcement_type'];
                    $priority = $_POST['priority'];
                    $target_audience = $_POST['target_audience'];
                    $is_published = isset($_POST['is_published']) ? 1 : 0;
                    $publish_date = $_POST['publish_date'];
                    $expiry_date = $_POST['expiry_date'] ?: null;
                    
                    if ($announcement_id && $title && $content && $announcement_type && $priority && $target_audience) {
                        // Start transaction
                        $db->beginTransaction();
                        
                        try {
                            $query = "UPDATE announcements SET title = ?, content = ?, announcement_type = ?, priority = ?, 
                                      target_audience = ?, is_published = ?, publish_date = ?, expiry_date = ?, updated_at = NOW()
                                      WHERE id = ?";
                            $stmt = $db->prepare($query);
                            if ($stmt->execute([$title, $content, $announcement_type, $priority, $target_audience, 
                                              $is_published, $publish_date, $expiry_date, $announcement_id])) {
                                
                                // Handle new file uploads
                                $uploaded_files = handleFileUpload($_FILES, $announcement_id, $db);
                                
                                // Update attachment count if new files were uploaded
                                if (!empty($uploaded_files)) {
                                    // Get current attachment count
                                    $count_query = "SELECT COUNT(*) as count FROM announcement_attachments WHERE announcement_id = ?";
                                    $count_stmt = $db->prepare($count_query);
                                    $count_stmt->execute([$announcement_id]);
                                    $total_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                                    
                                    $update_query = "UPDATE announcements SET has_attachments = 1, attachment_count = ? WHERE id = ?";
                                    $update_stmt = $db->prepare($update_query);
                                    $update_stmt->execute([$total_count, $announcement_id]);
                                }
                                
                                $db->commit();
                                $success_message = "Announcement updated successfully!";
                                if (!empty($uploaded_files)) {
                                    $success_message .= " " . count($uploaded_files) . " new image(s) uploaded.";
                                }
                            } else {
                                $db->rollback();
                                $error_message = "Failed to update announcement.";
                            }
                        } catch (Exception $e) {
                            $db->rollback();
                            $error_message = "Error updating announcement: " . $e->getMessage();
                        }
                    } else {
                        $error_message = "Please fill in all required fields.";
                    }
                    break;
                    
                case 'delete_announcement':
                    $announcement_id = $_POST['announcement_id'];
                    if ($announcement_id) {
                        // Start transaction
                        $db->beginTransaction();
                        
                        try {
                            // Delete associated files first
                            $file_query = "SELECT file_path FROM announcement_attachments WHERE announcement_id = ?";
                            $file_stmt = $db->prepare($file_query);
                            $file_stmt->execute([$announcement_id]);
                            $files = $file_stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            // Delete physical files
                            foreach ($files as $file) {
                                if (file_exists($file['file_path'])) {
                                    unlink($file['file_path']);
                                }
                            }
                            
                            // Delete attachment records (will be handled by CASCADE)
                            // Delete announcement
                            $query = "DELETE FROM announcements WHERE id = ?";
                            $stmt = $db->prepare($query);
                            if ($stmt->execute([$announcement_id])) {
                                $db->commit();
                                $success_message = "Announcement deleted successfully!";
                            } else {
                                $db->rollback();
                                $error_message = "Failed to delete announcement.";
                            }
                        } catch (Exception $e) {
                            $db->rollback();
                            $error_message = "Error deleting announcement: " . $e->getMessage();
                        }
                    }
                    break;
                    
                case 'delete_attachment':
                    $attachment_id = $_POST['attachment_id'];
                    $announcement_id = $_POST['announcement_id'];
                    if ($attachment_id && $announcement_id) {
                        // Get file info
                        $file_query = "SELECT file_path FROM announcement_attachments WHERE id = ?";
                        $file_stmt = $db->prepare($file_query);
                        $file_stmt->execute([$attachment_id]);
                        $file_info = $file_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($file_info) {
                            // Delete physical file
                            if (file_exists($file_info['file_path'])) {
                                unlink($file_info['file_path']);
                            }
                            
                            // Delete database record
                            $delete_query = "DELETE FROM announcement_attachments WHERE id = ?";
                            $delete_stmt = $db->prepare($delete_query);
                            if ($delete_stmt->execute([$attachment_id])) {
                                // Update attachment count
                                $count_query = "SELECT COUNT(*) as count FROM announcement_attachments WHERE announcement_id = ?";
                                $count_stmt = $db->prepare($count_query);
                                $count_stmt->execute([$announcement_id]);
                                $total_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                                
                                $update_query = "UPDATE announcements SET has_attachments = ?, attachment_count = ? WHERE id = ?";
                                $update_stmt = $db->prepare($update_query);
                                $update_stmt->execute([$total_count > 0 ? 1 : 0, $total_count, $announcement_id]);
                                
                                $success_message = "Attachment deleted successfully!";
                            } else {
                                $error_message = "Failed to delete attachment.";
                            }
                        }
                    }
                    break;
                    
                case 'toggle_publish':
                    $announcement_id = $_POST['announcement_id'];
                    $is_published = $_POST['is_published'];
                    if ($announcement_id !== null) {
                        $query = "UPDATE announcements SET is_published = ?, updated_at = NOW() WHERE id = ?";
                        $stmt = $db->prepare($query);
                        if ($stmt->execute([$is_published, $announcement_id])) {
                            $success_message = $is_published ? "Announcement published successfully!" : "Announcement unpublished successfully!";
                        } else {
                            $error_message = "Failed to update announcement status.";
                        }
                    }
                    break;
            }
        }
    }
    
    // Get filter parameters
    $type_filter = $_GET['type_filter'] ?? '';
    $priority_filter = $_GET['priority_filter'] ?? '';
    $audience_filter = $_GET['audience_filter'] ?? '';
    $status_filter = $_GET['status_filter'] ?? '';
    
    // Get announcements with creator info
    $query = "SELECT a.*, u.username as created_by_username 
              FROM announcements a
              LEFT JOIN users u ON a.created_by = u.id
              WHERE 1=1";
    
    $params = [];
    
    if ($type_filter) {
        $query .= " AND a.announcement_type = ?";
        $params[] = $type_filter;
    }
    
    if ($priority_filter) {
        $query .= " AND a.priority = ?";
        $params[] = $priority_filter;
    }
    
    if ($audience_filter) {
        $query .= " AND a.target_audience = ?";
        $params[] = $audience_filter;
    }
    
    if ($status_filter === 'published') {
        $query .= " AND a.is_published = 1";
    } elseif ($status_filter === 'draft') {
        $query .= " AND a.is_published = 0";
    } elseif ($status_filter === 'expired') {
        $query .= " AND a.expiry_date < CURDATE()";
    } elseif ($status_filter === 'active') {
        $query .= " AND a.is_published = 1 AND (a.expiry_date IS NULL OR a.expiry_date >= CURDATE())";
    }
    
    $query .= " ORDER BY a.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get announcement types
    $announcement_types = [
        'General' => 'General',
        'Academic' => 'Academic',
        'Event' => 'Event',
        'Emergency' => 'Emergency',
        'Maintenance' => 'Maintenance',
        'Holiday' => 'Holiday'
    ];
    
    // Get priority levels
    $priority_levels = [
        'Low' => 'Low',
        'Normal' => 'Normal',
        'High' => 'High',
        'Urgent' => 'Urgent'
    ];
    
    // Get target audiences
    $target_audiences = [
        'All' => 'All',
        'Students' => 'Students',
        'Teachers' => 'Teachers',
        'Staff' => 'Staff'
    ];
    
} catch (Exception $e) {
    $error_message = "Unable to load announcements data.";
    error_log("Announcements error: " . $e->getMessage());
}

ob_start();
?>

<div class="page-header-modern">
    <div class="page-header-content">
        <div class="page-title-section">
            <div class="page-title-wrapper">
                <h1 class="page-title">
                    <span class="page-icon">📢</span>
                    Announcements Management
                </h1>
                <nav class="page-breadcrumb">
                    <span class="breadcrumb-item">Principal</span>
                    <span class="breadcrumb-separator">›</span>
                    <span class="breadcrumb-item current">Announcements</span>
                </nav>
            </div>
            <p class="page-description">Create, manage, and publish school announcements to keep your community informed</p>
        </div>
        <div class="page-actions">
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($announcements); ?></div>
                    <div class="stat-label">Total</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count(array_filter($announcements, function($a) { return $a['is_published']; })); ?></div>
                    <div class="stat-label">Published</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count(array_filter($announcements, function($a) { return !$a['is_published']; })); ?></div>
                    <div class="stat-label">Drafts</div>
                </div>
            </div>
            <button type="button" class="btn-modern btn-primary" data-modal-target="add-announcement-modal">
                <i class="fas fa-plus"></i>
                Create Announcement
            </button>
        </div>
    </div>
</div>

<?php if (isset($success_message)): ?>
    <div class="alert-modern alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo $success_message; ?>
    </div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div class="alert-modern alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo $error_message; ?>
    </div>
<?php endif; ?>

<!-- Filters -->
<div class="filters-modern">
    <div class="filters-header">
        <h3 class="filters-title">
            <i class="fas fa-filter"></i>
            Filter Announcements
        </h3>
        <button type="button" class="btn-modern btn-ghost btn-sm" onclick="clearFilters()">
            <i class="fas fa-times"></i>
            Clear Filters
        </button>
    </div>
    <div class="filters-content">
        <form method="GET" class="filters-form-modern">
            <div class="filters-row">
                <div class="filter-group-modern">
                    <label class="filter-label" for="type_filter">
                        <i class="fas fa-tag"></i>
                        Type
                    </label>
                    <div class="select-wrapper">
                        <select name="type_filter" id="type_filter" class="select-modern">
                            <option value="">All Types</option>
                            <?php foreach ($announcement_types as $type): ?>
                                <option value="<?php echo $type; ?>" <?php echo $type_filter === $type ? 'selected' : ''; ?>>
                                    <?php echo $type; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down select-arrow"></i>
                    </div>
                </div>
                
                <div class="filter-group-modern">
                    <label class="filter-label" for="priority_filter">
                        <i class="fas fa-exclamation-triangle"></i>
                        Priority
                    </label>
                    <div class="select-wrapper">
                        <select name="priority_filter" id="priority_filter" class="select-modern">
                            <option value="">All Priorities</option>
                            <?php foreach ($priority_levels as $priority): ?>
                                <option value="<?php echo $priority; ?>" <?php echo $priority_filter === $priority ? 'selected' : ''; ?>>
                                    <?php echo $priority; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down select-arrow"></i>
                    </div>
                </div>
                
                <div class="filter-group-modern">
                    <label class="filter-label" for="audience_filter">
                        <i class="fas fa-users"></i>
                        Audience
                    </label>
                    <div class="select-wrapper">
                        <select name="audience_filter" id="audience_filter" class="select-modern">
                            <option value="">All Audiences</option>
                            <?php foreach ($target_audiences as $audience): ?>
                                <option value="<?php echo $audience; ?>" <?php echo $audience_filter === $audience ? 'selected' : ''; ?>>
                                    <?php echo $audience; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down select-arrow"></i>
                    </div>
                </div>
                
                <div class="filter-group-modern">
                    <label class="filter-label" for="status_filter">
                        <i class="fas fa-circle"></i>
                        Status
                    </label>
                    <div class="select-wrapper">
                        <select name="status_filter" id="status_filter" class="select-modern">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>Published</option>
                            <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                        </select>
                        <i class="fas fa-chevron-down select-arrow"></i>
                    </div>
                </div>
            </div>
            
            <div class="filters-actions">
                <button type="submit" class="btn-modern btn-primary">
                    <i class="fas fa-search"></i>
                    Apply Filters
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Announcements List -->
<div class="content-wrapper">
    <div class="view-controls">
        <div class="view-info">
            <h3>Announcements</h3>
            <div class="results-count">
                Showing <?php echo count($announcements); ?> announcement<?php echo count($announcements) !== 1 ? 's' : ''; ?>
                <?php if ($type_filter || $priority_filter || $audience_filter || $status_filter): ?>
                    (filtered)
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if (empty($announcements)): ?>
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-megaphone"></i>
            </div>
            <h3 class="empty-title">No announcements found</h3>
            <p class="empty-description">
                <?php if ($type_filter || $priority_filter || $audience_filter || $status_filter): ?>
                    No announcements match your current filters. Try adjusting your search criteria.
                <?php else: ?>
                    Get started by creating your first announcement to keep your school community informed.
                <?php endif; ?>
            </p>
            <?php if (!($type_filter || $priority_filter || $audience_filter || $status_filter)): ?>
                <button type="button" class="btn-modern btn-primary" data-modal-target="add-announcement-modal">
                    <i class="fas fa-plus"></i>
                    Create Your First Announcement
                </button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="announcements-grid-modern">
            <?php foreach ($announcements as $announcement): ?>
                <div class="announcement-card-modern">
                    <div class="card-header-modern">
                        <div class="announcement-meta-top">
                            <div class="announcement-badges-modern">
                                <span class="type-badge-modern type-<?php echo strtolower($announcement['announcement_type']); ?>">
                                    <i class="fas fa-tag"></i>
                                    <?php echo htmlspecialchars($announcement['announcement_type']); ?>
                                </span>
                                <span class="priority-badge-modern priority-<?php echo strtolower($announcement['priority']); ?>">
                                    <?php 
                                    $priority_icons = [
                                        'low' => 'fa-arrow-down',
                                        'normal' => 'fa-minus',
                                        'high' => 'fa-arrow-up',
                                        'urgent' => 'fa-exclamation'
                                    ];
                                    $icon = $priority_icons[strtolower($announcement['priority'])] ?? 'fa-minus';
                                    ?>
                                    <i class="fas <?php echo $icon; ?>"></i>
                                    <?php echo htmlspecialchars($announcement['priority']); ?>
                                </span>
                                <span class="audience-badge-modern">
                                    <i class="fas fa-users"></i>
                                    <?php echo htmlspecialchars($announcement['target_audience']); ?>
                                </span>
                            </div>
                            <div class="status-indicator-modern status-<?php echo $announcement['is_published'] ? 'published' : 'draft'; ?>">
                                <?php 
                                if ($announcement['expiry_date'] && $announcement['expiry_date'] < date('Y-m-d')) {
                                    echo '<i class="fas fa-clock"></i> Expired';
                                } else {
                                    echo $announcement['is_published'] ? '<i class="fas fa-eye"></i> Published' : '<i class="fas fa-eye-slash"></i> Draft';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body-modern">
                        <h3 class="announcement-title-modern">
                            <?php echo htmlspecialchars($announcement['title']); ?>
                        </h3>
                        <div class="announcement-content-modern">
                            <?php 
                            $preview_text = strip_tags($announcement['content']);
                            $preview_length = 180;
                            if (strlen($preview_text) > $preview_length) {
                                echo nl2br(htmlspecialchars(substr($preview_text, 0, $preview_length))) . '...';
                            } else {
                                echo nl2br(htmlspecialchars($preview_text));
                            }
                            ?>
                        </div>
                        
                        <?php if (strlen($announcement['content']) > $preview_length): ?>
                            <button type="button" class="read-more-btn" onclick="showFullContent(<?php echo $announcement['id']; ?>)">
                                <i class="fas fa-expand-alt"></i>
                                Read Full Content
                            </button>
                        <?php endif; ?>
                        
                        <div class="announcement-meta-bottom">
                            <div class="meta-info-modern">
                                <div class="meta-item">
                                    <i class="fas fa-user"></i>
                                    <span>By <?php echo htmlspecialchars($announcement['created_by_username']); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-calendar-plus"></i>
                                    <span>Created <?php echo date('M j, Y', strtotime($announcement['created_at'])); ?></span>
                                </div>
                                <?php if ($announcement['publish_date']): ?>
                                    <div class="meta-item">
                                        <i class="fas fa-calendar-check"></i>
                                        <span>Publish <?php echo date('M j, Y', strtotime($announcement['publish_date'])); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($announcement['expiry_date']): ?>
                                    <div class="meta-item">
                                        <i class="fas fa-calendar-times"></i>
                                        <span>Expires <?php echo date('M j, Y', strtotime($announcement['expiry_date'])); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-footer-modern">
                        <div class="publish-controls">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle_publish">
                                <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                <input type="hidden" name="is_published" value="<?php echo $announcement['is_published'] ? 0 : 1; ?>">
                                <button type="submit" class="btn-modern btn-sm <?php echo $announcement['is_published'] ? 'btn-warning' : 'btn-success'; ?>">
                                    <i class="fas <?php echo $announcement['is_published'] ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                                    <?php echo $announcement['is_published'] ? 'Unpublish' : 'Publish'; ?>
                                </button>
                            </form>
                        </div>
                        <div class="card-actions-modern">
                            <button type="button" class="btn-modern btn-ghost btn-sm" 
                                    onclick="editAnnouncement(<?php echo htmlspecialchars(json_encode($announcement)); ?>)"
                                    title="Edit announcement">
                                <i class="fas fa-edit"></i>
                                Edit
                            </button>
                            <button type="button" class="btn-modern btn-danger btn-sm" 
                                    onclick="confirmDelete(<?php echo $announcement['id']; ?>, '<?php echo htmlspecialchars($announcement['title']); ?>')"
                                    title="Delete announcement">
                                <i class="fas fa-trash"></i>
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Add Announcement Modal -->
<div id="add-announcement-modal" class="modal-modern">
    <div class="modal-overlay"></div>
    <div class="modal-container large">
        <div class="modal-header-modern">
            <div class="modal-title-section">
                <h3 class="modal-title">
                    <i class="fas fa-megaphone"></i>
                    Create New Announcement
                </h3>
                <p class="modal-subtitle">Share important information with your school community</p>
            </div>
            <button type="button" class="modal-close-modern" data-modal-close>
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body-modern">
            <form method="POST" id="add-announcement-form" class="form-modern" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create_announcement">
                
                <div class="form-sections">
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="fas fa-info-circle"></i>
                            Basic Information
                        </h4>
                        <div class="form-grid-modern">
                            <div class="form-group-modern full-width">
                                <label for="title" class="form-label-modern">
                                    <span class="label-text">Title</span>
                                    <span class="label-required">*</span>
                                </label>
                                <input type="text" id="title" name="title" class="form-control-modern" required 
                                       placeholder="Enter announcement title">
                            </div>
                            
                            <div class="form-group-modern">
                                <label for="announcement_type" class="form-label-modern">
                                    <span class="label-text">Type</span>
                                    <span class="label-required">*</span>
                                </label>
                                <div class="select-wrapper-modern">
                                    <select id="announcement_type" name="announcement_type" class="form-control-modern" required>
                                        <option value="">Select announcement type</option>
                                        <?php foreach ($announcement_types as $type): ?>
                                            <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                </div>
                            </div>
                            
                            <div class="form-group-modern">
                                <label for="priority" class="form-label-modern">
                                    <span class="label-text">Priority</span>
                                    <span class="label-required">*</span>
                                </label>
                                <div class="select-wrapper-modern">
                                    <select id="priority" name="priority" class="form-control-modern" required>
                                        <option value="">Select priority level</option>
                                        <?php foreach ($priority_levels as $priority): ?>
                                            <option value="<?php echo $priority; ?>"><?php echo $priority; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                   
                                </div>
                            </div>
                            
                            <div class="form-group-modern">
                                <label for="target_audience" class="form-label-modern">
                                    <span class="label-text">Target Audience</span>
                                    <span class="label-required">*</span>
                                </label>
                                <div class="select-wrapper-modern">
                                    <select id="target_audience" name="target_audience" class="form-control-modern" required>
                                        <option value="">Select target audience</option>
                                        <?php foreach ($target_audiences as $audience): ?>
                                            <option value="<?php echo $audience; ?>"><?php echo $audience; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="fas fa-calendar-alt"></i>
                            Schedule & Timing
                        </h4>
                        <div class="form-grid-modern">
                            <div class="form-group-modern">
                                <label for="publish_date" class="form-label-modern">
                                    <span class="label-text">Publish Date</span>
                                </label>
                                <input type="date" id="publish_date" name="publish_date" class="form-control-modern" 
                                       value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="form-group-modern">
                                <label for="expiry_date" class="form-label-modern">
                                    <span class="label-text">Expiry Date</span>
                                </label>
                                <input type="date" id="expiry_date" name="expiry_date" class="form-control-modern">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="fas fa-edit"></i>
                            Content
                        </h4>
                        <div class="form-group-modern full-width">
                            <label for="content" class="form-label-modern">
                                <span class="label-text">Announcement Content</span>
                                <span class="label-required">*</span>
                            </label>
                            <textarea id="content" name="content" rows="8" class="form-control-modern" required 
                                      placeholder="Write your announcement content here..."></textarea>
                            <div class="form-help">Write a clear and informative announcement for your intended audience.</div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="fas fa-image"></i>
                            Attachments (Optional)
                        </h4>
                        <div class="form-group-modern full-width">
                            <label for="attachments" class="form-label-modern">
                                <span class="label-text">Upload Images</span>
                            </label>
                            <div class="file-upload-area" id="file-upload-area">
                                <input type="file" id="attachments" name="attachment[]" class="file-input" 
                                       accept="image/*" multiple style="display: none;">
                                <div class="file-upload-content">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Click to select images or drag and drop</p>
                                    <p class="file-help">Supported formats: JPG, PNG, GIF (Max 5MB each)</p>
                                </div>
                            </div>
                            <div id="file-preview" class="file-preview"></div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="fas fa-cog"></i>
                            Publishing Options
                        </h4>
                        <div class="form-group-modern full-width">
                            <label class="checkbox-label-modern">
                                <input type="checkbox" name="is_published" checked class="checkbox-modern">
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Publish immediately</span>
                            </label>
                            <div class="form-help">If unchecked, the announcement will be saved as a draft.</div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer-modern">
                    <button type="button" class="btn-modern btn-ghost" data-modal-close>
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                    <button type="submit" class="btn-modern btn-primary">
                        <i class="fas fa-paper-plane"></i>
                        Create Announcement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Announcement Modal -->
<div id="edit-announcement-modal" class="modal-modern">
    <div class="modal-overlay"></div>
    <div class="modal-container large">
        <div class="modal-header-modern">
            <div class="modal-title-section">
                <h3 class="modal-title">
                    <i class="fas fa-edit"></i>
                    Edit Announcement
                </h3>
                <p class="modal-subtitle">Update announcement details and content</p>
            </div>
            <button type="button" class="modal-close-modern" data-modal-close>
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body-modern">
            <form method="POST" id="edit-announcement-form" class="form-modern" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_announcement">
                <input type="hidden" name="announcement_id" id="edit_announcement_id">
                
                <div class="form-sections">
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="fas fa-info-circle"></i>
                            Basic Information
                        </h4>
                        <div class="form-grid-modern">
                            <div class="form-group-modern full-width">
                                <label for="edit_title" class="form-label-modern">
                                    <span class="label-text">Title</span>
                                    <span class="label-required">*</span>
                                </label>
                                <input type="text" id="edit_title" name="title" class="form-control-modern" required>
                            </div>
                            
                            <div class="form-group-modern">
                                <label for="edit_announcement_type" class="form-label-modern">
                                    <span class="label-text">Type</span>
                                    <span class="label-required">*</span>
                                </label>
                                <div class="select-wrapper-modern">
                                    <select id="edit_announcement_type" name="announcement_type" class="form-control-modern" required>
                                        <option value="">Select announcement type</option>
                                        <?php foreach ($announcement_types as $type): ?>
                                            <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                  
                                </div>
                            </div>
                            
                            <div class="form-group-modern">
                                <label for="edit_priority" class="form-label-modern">
                                    <span class="label-text">Priority</span>
                                    <span class="label-required">*</span>
                                </label>
                                <div class="select-wrapper-modern">
                                    <select id="edit_priority" name="priority" class="form-control-modern" required>
                                        <option value="">Select priority level</option>
                                        <?php foreach ($priority_levels as $priority): ?>
                                            <option value="<?php echo $priority; ?>"><?php echo $priority; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                 
                                </div>
                            </div>
                            
                            <div class="form-group-modern">
                                <label for="edit_target_audience" class="form-label-modern">
                                    <span class="label-text">Target Audience</span>
                                    <span class="label-required">*</span>
                                </label>
                                <div class="select-wrapper-modern">
                                    <select id="edit_target_audience" name="target_audience" class="form-control-modern" required>
                                        <option value="">Select target audience</option>
                                        <?php foreach ($target_audiences as $audience): ?>
                                            <option value="<?php echo $audience; ?>"><?php echo $audience; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="fas fa-calendar-alt"></i>
                            Schedule & Timing
                        </h4>
                        <div class="form-grid-modern">
                            <div class="form-group-modern">
                                <label for="edit_publish_date" class="form-label-modern">
                                    <span class="label-text">Publish Date</span>
                                </label>
                                <input type="date" id="edit_publish_date" name="publish_date" class="form-control-modern">
                            </div>
                            
                            <div class="form-group-modern">
                                <label for="edit_expiry_date" class="form-label-modern">
                                    <span class="label-text">Expiry Date</span>
                                </label>
                                <input type="date" id="edit_expiry_date" name="expiry_date" class="form-control-modern">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="fas fa-edit"></i>
                            Content
                        </h4>
                        <div class="form-group-modern full-width">
                            <label for="edit_content" class="form-label-modern">
                                <span class="label-text">Announcement Content</span>
                                <span class="label-required">*</span>
                            </label>
                            <textarea id="edit_content" name="content" rows="8" class="form-control-modern" required></textarea>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="fas fa-image"></i>
                            Attachments
                        </h4>
                        <div class="form-group-modern full-width">
                            <div id="existing-attachments" class="existing-attachments"></div>
                            <label for="edit_attachments" class="form-label-modern">
                                <span class="label-text">Add New Images</span>
                            </label>
                            <div class="file-upload-area" id="edit-file-upload-area">
                                <input type="file" id="edit_attachments" name="attachment[]" class="file-input" 
                                       accept="image/*" multiple style="display: none;">
                                <div class="file-upload-content">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Click to select images or drag and drop</p>
                                    <p class="file-help">Supported formats: JPG, PNG, GIF (Max 5MB each)</p>
                                </div>
                            </div>
                            <div id="edit-file-preview" class="file-preview"></div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="fas fa-cog"></i>
                            Publishing Options
                        </h4>
                        <div class="form-group-modern full-width">
                            <label class="checkbox-label-modern">
                                <input type="checkbox" id="edit_is_published" name="is_published" class="checkbox-modern">
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Published</span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer-modern">
                    <button type="button" class="btn-modern btn-ghost" data-modal-close>
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                    <button type="submit" class="btn-modern btn-primary">
                        <i class="fas fa-save"></i>
                        Update Announcement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-announcement-modal" class="modal-modern">
    <div class="modal-overlay"></div>
    <div class="modal-container">
        <div class="modal-header-modern">
            <div class="modal-title-section">
                <h3 class="modal-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    Confirm Deletion
                </h3>
                <p class="modal-subtitle">This action cannot be undone</p>
            </div>
            <button type="button" class="modal-close-modern" data-modal-close>
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body-modern">
            <div class="delete-confirmation">
                <div class="delete-icon">
                    <i class="fas fa-trash-alt"></i>
                </div>
                <p class="delete-message">Are you sure you want to delete the announcement <strong id="delete-announcement-title"></strong>?</p>
                <p class="delete-warning">This action will permanently remove the announcement and cannot be undone.</p>
            </div>
            
            <form method="POST" id="delete-announcement-form">
                <input type="hidden" name="action" value="delete_announcement">
                <input type="hidden" name="announcement_id" id="delete_announcement_id">
                
                <div class="modal-footer-modern">
                    <button type="button" class="btn-modern btn-ghost" data-modal-close>
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                    <button type="submit" class="btn-modern btn-danger">
                        <i class="fas fa-trash"></i>
                        Delete Announcement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Full Content Modal -->
<div id="full-content-modal" class="modal-modern">
    <div class="modal-overlay"></div>
    <div class="modal-container large">
        <div class="modal-header-modern">
            <div class="modal-title-section">
                <h3 class="modal-title" id="full-content-title">
                    <i class="fas fa-eye"></i>
                </h3>
            </div>
            <button type="button" class="modal-close-modern" data-modal-close>
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body-modern">
            <div class="content-viewer" id="full-content-body"></div>
        </div>
        <div class="modal-footer-modern">
            <button type="button" class="btn-modern btn-ghost" data-modal-close>
                <i class="fas fa-times"></i>
                Close
            </button>
        </div>
    </div>
</div>

<script>
// Modern Modal and Announcement Management
document.addEventListener('DOMContentLoaded', function() {
    initializeModals();
    initializeFilters();
    initializeFormValidation();
});

function initializeModals() {
    // Modal open/close functionality
    const modalTriggers = document.querySelectorAll('[data-modal-target]');
    const modalCloses = document.querySelectorAll('.modal-close-modern, [data-modal-close]');
    const modals = document.querySelectorAll('.modal-modern');
    
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function() {
            const targetModal = document.getElementById(this.dataset.modalTarget);
            if (targetModal) {
                openModal(targetModal);
            }
        });
    });
    
    modalCloses.forEach(close => {
        close.addEventListener('click', function() {
            const modal = this.closest('.modal-modern');
            if (modal) {
                closeModal(modal);
            }
        });
    });
    
    modals.forEach(modal => {
        const overlay = modal.querySelector('.modal-overlay');
        if (overlay) {
            overlay.addEventListener('click', function() {
                closeModal(modal);
            });
        }
    });

    // Close modal on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.modal-modern.active');
            if (openModal) {
                closeModal(openModal);
            }
        }
    });
}

function openModal(modal) {
    modal.classList.add('active');
    document.body.classList.add('modal-open');
    document.body.style.overflow = 'hidden';
    
    // Focus first input
    setTimeout(() => {
        const firstInput = modal.querySelector('input:not([type="hidden"]), select, textarea');
        if (firstInput) {
            firstInput.focus();
        }
    }, 100);
}

function closeModal(modal) {
    modal.classList.remove('active');
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
    
    // Reset form if it exists
    const form = modal.querySelector('form');
    if (form) {
        form.reset();
    }
}

function initializeFilters() {
    const filterForm = document.querySelector('.filters-form-modern');
    if (filterForm) {
        // Auto-submit on filter change
        const filterSelects = filterForm.querySelectorAll('select');
        filterSelects.forEach(select => {
            select.addEventListener('change', function() {
                // Add a small delay to allow user to make multiple selections
                clearTimeout(this.filterTimeout);
                this.filterTimeout = setTimeout(() => {
                    filterForm.submit();
                }, 300);
            });
        });
    }
}

function initializeFormValidation() {
    const forms = document.querySelectorAll('.form-modern');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
    });
}

function validateForm(form) {
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            showFieldError(field, 'This field is required');
            isValid = false;
        } else {
            clearFieldError(field);
        }
    });
    
    return isValid;
}

function showFieldError(field, message) {
    clearFieldError(field);
    field.classList.add('error');
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.textContent = message;
    
    field.parentNode.appendChild(errorDiv);
}

function clearFieldError(field) {
    field.classList.remove('error');
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
}

function editAnnouncement(announcement) {
    document.getElementById('edit_announcement_id').value = announcement.id;
    document.getElementById('edit_title').value = announcement.title;
    document.getElementById('edit_announcement_type').value = announcement.announcement_type;
    document.getElementById('edit_priority').value = announcement.priority;
    document.getElementById('edit_target_audience').value = announcement.target_audience;
    document.getElementById('edit_publish_date').value = announcement.publish_date;
    document.getElementById('edit_expiry_date').value = announcement.expiry_date || '';
    document.getElementById('edit_content').value = announcement.content;
    document.getElementById('edit_is_published').checked = announcement.is_published == 1;
    
    const editModal = document.getElementById('edit-announcement-modal');
    if (editModal) {
        openModal(editModal);
    }
}

function confirmDelete(announcementId, title) {
    document.getElementById('delete_announcement_id').value = announcementId;
    document.getElementById('delete-announcement-title').textContent = title;
    
    const deleteModal = document.getElementById('delete-announcement-modal');
    if (deleteModal) {
        openModal(deleteModal);
    }
}

function showFullContent(announcementId) {
    // Find the announcement in the global data
    const announcements = <?php echo json_encode($announcements); ?>;
    const announcement = announcements.find(a => a.id == announcementId);
    
    if (announcement) {
        document.getElementById('full-content-title').textContent = announcement.title;
        document.getElementById('full-content-body').innerHTML = announcement.content.replace(/\n/g, '<br>');
        
        const contentModal = document.getElementById('full-content-modal');
        if (contentModal) {
            openModal(contentModal);
        }
    }
}

function clearFilters() {
    const filterSelects = document.querySelectorAll('.select-modern');
    filterSelects.forEach(select => {
        select.value = '';
    });
    
    // Redirect to clear filters
    window.location.href = 'announcements.php';
}

// Show success/error messages with animation
function showAlert(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert-modern alert-${type} alert-floating`;
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        ${message}
    `;
    
    document.body.appendChild(alertDiv);
    
    // Animate in
    setTimeout(() => {
        alertDiv.classList.add('show');
    }, 100);
    
    // Remove after 5 seconds
    setTimeout(() => {
        alertDiv.classList.remove('show');
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.parentNode.removeChild(alertDiv);
            }
        }, 300);
    }, 5000);
}

// Enhanced read more functionality
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('read-more-btn')) {
        e.preventDefault();
        const card = e.target.closest('.announcement-card-modern');
        const content = card.querySelector('.announcement-content-modern');
        
        if (content.classList.contains('expanded')) {
            content.classList.remove('expanded');
            e.target.innerHTML = '<i class="fas fa-expand-alt"></i> Read Full Content';
        } else {
            content.classList.add('expanded');
            e.target.innerHTML = '<i class="fas fa-compress-alt"></i> Show Less';
        }
    }
});

// File Upload and Preview Functionality
function initializeFileUpload() {
    // Initialize for add modal
    const fileInput = document.getElementById('attachments');
    const fileUploadArea = document.getElementById('file-upload-area');
    const filePreview = document.getElementById('file-preview');
    
    if (fileInput && fileUploadArea && filePreview) {
        setupFileUpload(fileInput, fileUploadArea, filePreview, 'attachments');
    }

    // Initialize for edit modal
    const editFileInput = document.getElementById('edit_attachments');
    const editFileUploadArea = document.getElementById('edit-file-upload-area');
    const editFilePreview = document.getElementById('edit-file-preview');
    
    if (editFileInput && editFileUploadArea && editFilePreview) {
        setupFileUpload(editFileInput, editFileUploadArea, editFilePreview, 'edit_attachments');
    }
}

function setupFileUpload(fileInput, fileUploadArea, filePreview, inputId) {
    // Click to upload
    fileUploadArea.addEventListener('click', function() {
        fileInput.click();
    });

    // Drag and drop functionality
    fileUploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        fileUploadArea.classList.add('drag-over');
    });

    fileUploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        fileUploadArea.classList.remove('drag-over');
    });

    fileUploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        fileUploadArea.classList.remove('drag-over');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            // Filter only image files
            const imageFiles = Array.from(files).filter(file => file.type.startsWith('image/'));
            if (imageFiles.length > 0) {
                handleFileSelection(imageFiles, filePreview, inputId);
                // Update the file input with the dropped files
                const dt = new DataTransfer();
                imageFiles.forEach(file => dt.items.add(file));
                fileInput.files = dt.files;
            }
        }
    });

    // File input change event
    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            handleFileSelection(Array.from(this.files), filePreview, inputId);
        }
    });
}

function handleFileSelection(files, previewContainer, inputId) {
    previewContainer.innerHTML = '';
    
    if (files.length === 0) {
        previewContainer.style.display = 'none';
        return;
    }

    // Create preview header
    const previewHeader = document.createElement('div');
    previewHeader.className = 'preview-header';
    previewHeader.innerHTML = `
        <h5><i class="fas fa-images"></i> Selected Images (${files.length})</h5>
        <button type="button" class="btn-clear-files" onclick="clearFileSelection('${previewContainer.id}', '${inputId}')">
            <i class="fas fa-times"></i> Clear All
        </button>
    `;
    previewContainer.appendChild(previewHeader);

    // Create preview grid
    const previewGrid = document.createElement('div');
    previewGrid.className = 'preview-grid';
    previewContainer.appendChild(previewGrid);

    files.forEach((file, index) => {
        if (file.type.startsWith('image/')) {
            createImagePreview(file, index, previewGrid, inputId);
        }
    });

    previewContainer.style.display = 'block';
}

function createImagePreview(file, index, container, inputId) {
    const reader = new FileReader();
    
    reader.onload = function(e) {
        const previewItem = document.createElement('div');
        previewItem.className = 'preview-item';
        previewItem.dataset.index = index;
        previewItem.dataset.inputId = inputId;
        
        const fileSizeKB = Math.round(file.size / 1024);
        const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);
        
        previewItem.innerHTML = `
            <div class="preview-image-container">
                <img src="${e.target.result}" alt="${file.name}" class="preview-image">
                <div class="preview-overlay">
                    <button type="button" class="preview-remove" onclick="removePreviewItem(${index}, '${inputId}')" title="Remove image">
                        <i class="fas fa-times"></i>
                    </button>
                    <button type="button" class="preview-zoom" onclick="zoomPreviewImage('${e.target.result}', '${file.name}')" title="View full size">
                        <i class="fas fa-search-plus"></i>
                    </button>
                </div>
            </div>
            <div class="preview-info">
                <div class="preview-filename" title="${file.name}">${file.name}</div>
                <div class="preview-filesize">${fileSizeKB > 1024 ? fileSizeMB + ' MB' : fileSizeKB + ' KB'}</div>
                ${file.size > 5 * 1024 * 1024 ? '<div class="preview-warning"><i class="fas fa-exclamation-triangle"></i> File too large (max 5MB)</div>' : ''}
            </div>
        `;
        
        container.appendChild(previewItem);
    };
    
    reader.readAsDataURL(file);
}

function removePreviewItem(index, inputId) {
    const previewItem = document.querySelector(`.preview-item[data-index="${index}"][data-input-id="${inputId}"]`);
    if (previewItem) {
        previewItem.remove();
        
        // Update file input to remove the file
        const fileInput = document.getElementById(inputId);
        if (fileInput && fileInput.files.length > 0) {
            const dt = new DataTransfer();
            Array.from(fileInput.files).forEach((file, i) => {
                if (i !== index) {
                    dt.items.add(file);
                }
            });
            fileInput.files = dt.files;
            
            // Update preview count
            const previewContainer = previewItem.closest('.file-preview');
            const remainingItems = previewContainer.querySelectorAll('.preview-item');
            const previewHeader = previewContainer.querySelector('.preview-header h5');
            if (previewHeader) {
                previewHeader.innerHTML = `<i class="fas fa-images"></i> Selected Images (${remainingItems.length})`;
            }
            
            // Hide preview if no files left
            if (remainingItems.length === 0) {
                clearFileSelection(previewContainer.id, inputId);
            }
        }
    }
}

function clearFileSelection(previewContainerId, inputId) {
    const previewContainer = document.getElementById(previewContainerId);
    const fileInput = document.getElementById(inputId);
    
    if (fileInput) {
        fileInput.value = '';
    }
    
    previewContainer.innerHTML = '';
    previewContainer.style.display = 'none';
}

function zoomPreviewImage(imageSrc, imageName) {
    // Create or show image lightbox
    let lightbox = document.getElementById('previewLightbox');
    if (!lightbox) {
        lightbox = document.createElement('div');
        lightbox.id = 'previewLightbox';
        lightbox.className = 'preview-lightbox';
        lightbox.innerHTML = `
            <div class="lightbox-content">
                <span class="lightbox-close" onclick="closePreviewLightbox()">&times;</span>
                <img class="lightbox-image" id="lightboxPreviewImage">
                <div class="lightbox-caption" id="lightboxPreviewCaption"></div>
            </div>
        `;
        document.body.appendChild(lightbox);
    }
    
    const lightboxImage = document.getElementById('lightboxPreviewImage');
    const lightboxCaption = document.getElementById('lightboxPreviewCaption');
    
    lightboxImage.src = imageSrc;
    lightboxCaption.textContent = imageName;
    lightbox.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closePreviewLightbox() {
    const lightbox = document.getElementById('previewLightbox');
    if (lightbox) {
        lightbox.style.display = 'none';
        document.body.style.overflow = '';
    }
}

// Initialize file upload when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeModals();
    initializeFilters();
    initializeFormValidation();
    initializeFileUpload();
});
</script>

<style>
/* Modern Announcements Management Styles */

/* Page Header Modern */
.page-header-modern {
    background: linear-gradient(135deg, var(--white) 0%, #f8fafb 100%);
    border-radius: 20px;
    padding: 0;
    margin-bottom: 2rem;
    box-shadow: 0 8px 32px rgba(46, 134, 171, 0.12);
    overflow: hidden;
    border: 1px solid rgba(46, 134, 171, 0.08);
}

.page-header-content {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 2.5rem 3rem;
    background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
    color: var(--white);
    position: relative;
}

.page-header-content::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
    opacity: 0.3;
}

.page-title-section {
    position: relative;
    z-index: 1;
}

.page-title-wrapper {
    margin-bottom: 1rem;
}

.page-title {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    color: var(--white);
}

.page-icon {
    font-size: 2rem;
    opacity: 0.9;
}

.page-breadcrumb {
    font-size: 0.95rem;
    opacity: 0.8;
}

.breadcrumb-item.current {
    color: var(--white);
    font-weight: 600;
}

.breadcrumb-separator {
    margin: 0 0.5rem;
    opacity: 0.6;
}

.page-description {
    font-size: 1.2rem;
    opacity: 0.9;
    font-weight: 400;
    margin: 0;
    line-height: 1.5;
}

.page-actions {
    display: flex;
    align-items: center;
    gap: 2rem;
    position: relative;
    z-index: 1;
}

.stats-cards {
    display: flex;
    gap: 1.5rem;
}

.stat-card {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    padding: 1.25rem;
    text-align: center;
    border: 1px solid rgba(255, 255, 255, 0.2);
    min-width: 80px;
}

.stat-value {
    font-size: 1.75rem;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.8rem;
    opacity: 0.8;
}

/* Modern Buttons */
.btn-modern {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 10px;
    font-size: 0.95rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
    font-family: inherit;
}

.btn-modern.btn-primary {
    background: linear-gradient(135deg, var(--white) 0%, #f8f9fa 100%);
    color: var(--primary-blue);
    border: 2px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn-modern.btn-primary:hover {
    background: rgba(255, 255, 255, 0.95);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
}

.btn-modern.btn-ghost {
    background: transparent;
    color: var(--gray);
    border: 2px solid var(--border-gray);
}

.btn-modern.btn-ghost:hover {
    background: var(--light-gray);
    border-color: var(--primary-blue);
    color: var(--primary-blue);
}

.btn-modern.btn-success {
    background: linear-gradient(135deg, var(--success) 0%, #27ae60 100%);
    color: var(--white);
}

.btn-modern.btn-warning {
    background: linear-gradient(135deg, var(--warning) 0%, #e67e22 100%);
    color: var(--white);
}

.btn-modern.btn-danger {
    background: linear-gradient(135deg, var(--danger) 0%, #c0392b 100%);
    color: var(--white);
}

.btn-modern.btn-danger:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
}

.btn-modern.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.85rem;
}

/* Alert Modern */
.alert-modern {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    font-weight: 500;
    border: 1px solid transparent;
}

.alert-success {
    background: linear-gradient(135deg, #e8f5e8 0%, #f1f8e9 100%);
    color: #2e7d32;
    border-color: #c8e6c9;
}

.alert-error {
    background: linear-gradient(135deg, #ffebee 0%, #fce4ec 100%);
    color: #c62828;
    border-color: #ffcdd2;
}

.alert-floating {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10000;
    opacity: 0;
    transform: translateX(100%);
    transition: all 0.3s ease;
    max-width: 400px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
}

.alert-floating.show {
    opacity: 1;
    transform: translateX(0);
}

/* Filters Modern */
.filters-modern {
    background: var(--white);
    border-radius: 16px;
    box-shadow: 0 4px 16px rgba(46, 134, 171, 0.08);
    margin-bottom: 2rem;
    overflow: hidden;
    border: 1px solid rgba(46, 134, 171, 0.08);
}

.filters-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, var(--light-blue) 0%, rgba(232, 244, 248, 0.7) 100%);
    border-bottom: 1px solid var(--border-gray);
}

.filters-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--dark-blue);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.filters-content {
    padding: 2rem;
}

.filters-form-modern {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.filters-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.filter-group-modern {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-label {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--black);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-label i {
    color: var(--primary-blue);
    width: 16px;
}

.select-wrapper {
    position: relative;
}

.select-modern {
    width: 100%;
    padding: 0.75rem 2.5rem 0.75rem 1rem;
    border: 2px solid var(--border-gray);
    border-radius: 10px;
    font-size: 0.95rem;
    background: var(--white);
    color: var(--black);
    appearance: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.select-modern:focus {
    outline: none;
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 3px rgba(46, 134, 171, 0.1);
}

.select-arrow {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray);
    pointer-events: none;
    font-size: 0.8rem;
}

.filters-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

/* Content Wrapper */
.content-wrapper {
    background: var(--white);
    border-radius: 16px;
    box-shadow: 0 4px 16px rgba(46, 134, 171, 0.08);
    overflow: hidden;
    border: 1px solid rgba(46, 134, 171, 0.08);
}

/* View Controls */
.view-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, var(--light-blue) 0%, rgba(232, 244, 248, 0.7) 100%);
    border-bottom: 1px solid var(--border-gray);
}

.view-info h3 {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--dark-blue);
    margin: 0 0 0.25rem 0;
}

.results-count {
    font-size: 0.9rem;
    color: var(--gray);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--gray);
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 1.5rem;
    opacity: 0.6;
}

.empty-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--dark-blue);
    margin-bottom: 1rem;
}

.empty-description {
    font-size: 1.1rem;
    line-height: 1.6;
    margin-bottom: 2rem;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

/* Announcements Grid */
.announcements-grid-modern {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 2rem;
    padding: 2rem;
}

.announcement-card-modern {
    background: var(--white);
    border-radius: 16px;
    box-shadow: 0 4px 16px rgba(46, 134, 171, 0.08);
    overflow: hidden;
    transition: all 0.3s ease;
    border: 1px solid rgba(46, 134, 171, 0.08);
    display: flex;
    flex-direction: column;
}

.announcement-card-modern:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(46, 134, 171, 0.15);
}

.card-header-modern {
    padding: 1.5rem;
    background: linear-gradient(135deg, var(--light-blue) 0%, rgba(232, 244, 248, 0.7) 100%);
    border-bottom: 1px solid var(--border-gray);
}

.announcement-meta-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
}

.announcement-badges-modern {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.type-badge-modern,
.priority-badge-modern,
.audience-badge-modern {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.type-general { background: #e3f2fd; color: #1565c0; }
.type-academic { background: #e8f5e8; color: #2e7d32; }
.type-event { background: #f3e5f5; color: #7b1fa2; }
.type-emergency { background: #ffebee; color: #c62828; }
.type-maintenance { background: #fff3e0; color: #ef6c00; }
.type-holiday { background: #e0f2f1; color: #00695c; }

.priority-low { background: #e8f5e8; color: #2e7d32; }
.priority-normal { background: #e3f2fd; color: #1565c0; }
.priority-high { background: #fff3e0; color: #ef6c00; }
.priority-urgent { background: #ffebee; color: #c62828; }

.audience-badge-modern {
    background: rgba(46, 134, 171, 0.1);
    color: var(--primary-blue);
}

.status-indicator-modern {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-published {
    background: #e8f5e8;
    color: #2e7d32;
}

.status-draft {
    background: #fff3e0;
    color: #ef6c00;
}

.card-body-modern {
    padding: 1.5rem;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.announcement-title-modern {
    color: var(--dark-blue);
    font-size: 1.3rem;
    font-weight: 700;
    margin-bottom: 1rem;
    line-height: 1.3;
}

.announcement-content-modern {
    color: var(--black);
    line-height: 1.6;
    margin-bottom: 1rem;
    flex: 1;
    overflow: hidden;
    transition: all 0.3s ease;
}

.announcement-content-modern.expanded {
    max-height: none;
}

.read-more-btn {
    background: none;
    border: none;
    color: var(--primary-blue);
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    padding: 0.5rem 0;
    text-align: left;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: color 0.2s ease;
}

.read-more-btn:hover {
    color: var(--dark-blue);
}

.announcement-meta-bottom {
    margin-top: auto;
    padding-top: 1rem;
    border-top: 1px solid var(--border-gray);
}

.meta-info-modern {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: var(--gray);
}

.meta-item i {
    color: var(--primary-blue);
    width: 16px;
    font-size: 0.8rem;
}

.card-footer-modern {
    padding: 1rem 1.5rem;
    background: var(--light-gray);
    border-top: 1px solid var(--border-gray);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

.publish-controls {
    flex-shrink: 0;
}

.card-actions-modern {
    display: flex;
    gap: 0.5rem;
}

/* Modal Styles */
.modal-modern {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.modal-modern.active {
    opacity: 1;
    visibility: visible;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
}

.modal-container {
    background: var(--white);
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    transform: translateY(50px);
    transition: transform 0.3s ease;
}

.modal-modern.active .modal-container {
    transform: translateY(0);
}

.modal-container.large {
    max-width: 800px;
}

.modal-header-modern {
    padding: 2rem 2.5rem 1rem;
    border-bottom: 1px solid var(--border-gray);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    position: relative;
}

.modal-title-section {
    flex: 1;
}

.modal-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--dark-blue);
    margin: 0 0 0.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.modal-subtitle {
    color: var(--gray);
    font-size: 1rem;
    margin: 0;
}

.modal-close-modern {
    background: var(--light-gray);
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: var(--gray);
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.modal-close-modern:hover {
    background: var(--border-gray);
    color: var(--black);
}

.modal-body-modern {
    padding: 2rem 2.5rem;
}

.modal-footer-modern {
    padding: 1rem 2.5rem 2rem;
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    border-top: 1px solid var(--border-gray);
    background: var(--light-gray);
}

/* Form Styles */
.form-modern {
    width: 100%;
}

.form-sections {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.form-section {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.section-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark-blue);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--border-gray);
}

.section-title i {
    color: var(--primary-blue);
}

.form-grid-modern {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.form-group-modern {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-group-modern.full-width {
    grid-column: 1 / -1;
}

.form-label-modern {
    font-weight: 600;
    color: var(--black);
    font-size: 0.95rem;
}

.label-text {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.label-required {
    color: var(--danger);
    font-weight: 700;
}

.form-control-modern {
    width: 100%;
    padding: 0.875rem 1rem;
    border: 2px solid var(--border-gray);
    border-radius: 10px;
    font-size: 0.95rem;
    background: var(--white);
    color: var(--black);
    transition: all 0.2s ease;
    font-family: inherit;
}

.form-control-modern:focus {
    outline: none;
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 3px rgba(46, 134, 171, 0.1);
}

.form-control-modern.error {
    border-color: var(--danger);
    box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
}

.select-wrapper-modern {
    position: relative;
}

.select-arrow-modern {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray);
    pointer-events: none;
    font-size: 0.8rem;
}

.form-help {
    font-size: 0.85rem;
    color: var(--gray);
    margin-top: 0.25rem;
}

.field-error {
    color: var(--danger);
    font-size: 0.85rem;
    margin-top: 0.25rem;
}

.checkbox-label-modern {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    padding: 0.5rem 0;
}

.checkbox-modern {
    display: none;
}

.checkbox-custom {
    width: 20px;
    height: 20px;
    border: 2px solid var(--border-gray);
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    position: relative;
}

.checkbox-modern:checked + .checkbox-custom {
    background: var(--primary-blue);
    border-color: var(--primary-blue);
}

.checkbox-modern:checked + .checkbox-custom::after {
    content: '✓';
    color: var(--white);
    font-size: 0.8rem;
    font-weight: bold;
}

.checkbox-text {
    font-weight: 500;
    color: var(--black);
}

/* Delete Confirmation Styles */
.delete-confirmation {
    text-align: center;
    padding: 1rem 0 2rem;
}

.delete-icon {
    font-size: 4rem;
    color: var(--danger);
    margin-bottom: 1.5rem;
    opacity: 0.8;
}

.delete-message {
    font-size: 1.1rem;
    color: var(--black);
    margin-bottom: 1rem;
    line-height: 1.5;
}

.delete-warning {
    font-size: 0.95rem;
    color: var(--gray);
    margin-bottom: 0;
}

/* Content Viewer */
.content-viewer {
    background: var(--light-gray);
    border-radius: 12px;
    padding: 2rem;
    line-height: 1.7;
    color: var(--black);
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid var(--border-gray);
}

.content-viewer::-webkit-scrollbar {
    width: 6px;
}

.content-viewer::-webkit-scrollbar-track {
    background: var(--border-gray);
    border-radius: 3px;
}

.content-viewer::-webkit-scrollbar-thumb {
    background: var(--primary-blue);
    border-radius: 3px;
}

/* File Upload and Preview Styles */
.file-upload-area {
    border: 2px dashed #d1d9e0;
    border-radius: 12px;
    padding: 2rem;
    text-align: center;
    background: #f8fafb;
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
}

.file-upload-area:hover {
    border-color: var(--primary-blue);
    background: #f0f7ff;
}

.file-upload-area.drag-over {
    border-color: var(--primary-blue);
    background: var(--light-blue);
    transform: scale(1.02);
}

.file-upload-content {
    pointer-events: none;
}

.file-upload-content i {
    font-size: 3rem;
    color: var(--primary-blue);
    margin-bottom: 1rem;
    display: block;
}

.file-upload-content p {
    margin: 0.5rem 0;
    color: var(--dark-blue);
    font-weight: 600;
}

.file-help {
    font-size: 0.875rem;
    color: var(--gray);
    font-weight: 400;
}

.file-input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

/* File Preview Styles */
.file-preview {
    margin-top: 1.5rem;
    display: none;
}

.preview-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding: 1rem;
    background: var(--light-blue);
    border-radius: 8px;
}

.preview-header h5 {
    margin: 0;
    color: var(--dark-blue);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-clear-files {
    background: var(--white);
    border: 1px solid #dc3545;
    color: #dc3545;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-clear-files:hover {
    background: #dc3545;
    color: white;
}

.preview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
}

.preview-item {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.preview-item:hover {
    transform: translateY(-5px);
}

.preview-image-container {
    position: relative;
    width: 100%;
    height: 150px;
    overflow: hidden;
}

.preview-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.preview-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.preview-item:hover .preview-overlay {
    opacity: 1;
}

.preview-remove,
.preview-zoom {
    background: rgba(255,255,255,0.9);
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1rem;
}

.preview-remove {
    color: #dc3545;
}

.preview-remove:hover {
    background: #dc3545;
    color: white;
}

.preview-zoom {
    color: var(--primary-blue);
}

.preview-zoom:hover {
    background: var(--primary-blue);
    color: white;
}

.preview-info {
    padding: 1rem;
}

.preview-filename {
    font-weight: 600;
    color: var(--dark-blue);
    margin-bottom: 0.5rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: 0.875rem;
}

.preview-filesize {
    color: var(--gray);
    font-size: 0.75rem;
    margin-bottom: 0.25rem;
}

.preview-warning {
    color: #dc3545;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    margin-top: 0.5rem;
}

/* Preview Lightbox */
.preview-lightbox {
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

.lightbox-content {
    position: relative;
    max-width: 90%;
    max-height: 90%;
    text-align: center;
}

.lightbox-image {
    max-width: 100%;
    max-height: 80vh;
    object-fit: contain;
    border-radius: 8px;
}

.lightbox-close {
    position: absolute;
    top: -50px;
    right: 0;
    color: white;
    font-size: 40px;
    font-weight: bold;
    cursor: pointer;
    z-index: 2001;
    transition: opacity 0.3s ease;
}

.lightbox-close:hover {
    opacity: 0.7;
}

.lightbox-caption {
    color: white;
    margin-top: 1rem;
    font-size: 1.1rem;
    font-weight: 600;
}

/* Loading States */
.btn-modern.loading {
    opacity: 0.7;
    pointer-events: none;
    position: relative;
}

.btn-modern.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 16px;
    height: 16px;
    border: 2px solid transparent;
    border-top: 2px solid currentColor;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: translate(-50%, -50%) rotate(0deg); }
    100% { transform: translate(-50%, -50%) rotate(360deg); }
}

/* Focus States for Accessibility */
.btn-modern:focus,
.form-control-modern:focus,
.select-modern:focus {
    outline: 2px solid var(--primary-blue);
    outline-offset: 2px;
}

.modal-close-modern:focus {
    outline: 2px solid var(--primary-blue);
    outline-offset: 2px;
}

/* Print Styles */
@media print {
    .page-header-modern,
    .filters-modern,
    .view-controls,
    .card-footer-modern,
    .modal-modern {
        display: none !important;
    }
    
    .announcements-grid-modern {
        display: block;
    }
    
    .announcement-card-modern {
        break-inside: avoid;
        margin-bottom: 2rem;
        box-shadow: none;
        border: 1px solid var(--border-gray);
    }
}

/* High Contrast Mode Support */
@media (prefers-contrast: high) {
    .announcement-card-modern {
        border: 2px solid var(--black);
    }
    
    .btn-modern {
        border: 2px solid currentColor;
    }
    
    .form-control-modern {
        border: 2px solid var(--black);
    }
}

/* Reduced Motion Support */
@media (prefers-reduced-motion: reduce) {
    .announcement-card-modern,
    .btn-modern,
    .modal-modern,
    .modal-container {
        transition: none;
    }
    
    .announcement-card-modern:hover {
        transform: none;
    }
}

/* Dark Mode Support (Future Enhancement) */
@media (prefers-color-scheme: dark) {
    /* Will be implemented when dark mode is added */
}

/* Responsive Design */
@media (max-width: 768px) {
    .page-header-content {
        flex-direction: column;
        gap: 2rem;
        align-items: stretch;
        padding: 2rem;
    }

    .page-actions {
        flex-direction: column;
        gap: 1rem;
    }

    .stats-cards {
        justify-content: space-around;
    }

    .filters-row {
        grid-template-columns: 1fr;
    }

    .announcements-grid-modern {
        grid-template-columns: 1fr;
        padding: 1rem;
    }

    .announcement-meta-top {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }

    .card-footer-modern {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }

    .modal-container {
        width: 95%;
        max-height: 95vh;
    }

    .modal-header-modern,
    .modal-body-modern,
    .modal-footer-modern {
        padding-left: 1.5rem;
        padding-right: 1.5rem;
    }

    .form-grid-modern {
        grid-template-columns: 1fr;
    }

    .modal-footer-modern {
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .page-title {
        font-size: 2rem;
    }

    .announcements-grid-modern {
        padding: 0.5rem;
        gap: 1rem;
    }

    .announcement-card-modern {
        border-radius: 12px;
    }

    .modal-title {
        font-size: 1.5rem;
    }
}
</style>

<?php
$content = ob_get_clean();
include '../includes/header.php';
echo $content;
include '../includes/footer.php';
?>
