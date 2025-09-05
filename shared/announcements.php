<?php
require_once '../includes/auth_check.php';

// Check if user is logged in (any role can access)
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$page_title = 'Announcements - GTBA Portal';
$base_url = '../';

try {
    $database = new Database();
    $db = $database->connect();
    
    // Get current user role for filtering if needed
    $user_role = $_SESSION['role'] ?? '';
    
    // Pagination setup
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = 10;
    $offset = ($page - 1) * $per_page;
    
    // Filter setup
    $type_filter = isset($_GET['type']) ? $_GET['type'] : '';
    $priority_filter = isset($_GET['priority']) ? $_GET['priority'] : '';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Build WHERE clause based on user role and filters
    $where_conditions = ["a.is_published = 1"];
    $params = [];
    
    // Filter by target audience based on user role
    switch ($user_role) {
        case 'student':
            $where_conditions[] = "(a.target_audience = 'All' OR a.target_audience = 'Students')";
            break;
        case 'teacher':
            $where_conditions[] = "(a.target_audience = 'All' OR a.target_audience = 'Teachers')";
            break;
        case 'admin':
        case 'finance':
        case 'registrar':
            $where_conditions[] = "(a.target_audience = 'All' OR a.target_audience = 'Staff')";
            break;
        case 'principal':
            // Principals can see all announcements
            break;
        default:
            $where_conditions[] = "a.target_audience = 'All'";
            break;
    }
    
    // Add filters
    if (!empty($type_filter)) {
        $where_conditions[] = "a.announcement_type = ?";
        $params[] = $type_filter;
    }
    
    if (!empty($priority_filter)) {
        $where_conditions[] = "a.priority = ?";
        $params[] = $priority_filter;
    }
    
    if (!empty($search)) {
        $where_conditions[] = "(a.title LIKE ? OR a.content LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) as total 
                    FROM announcements a 
                    LEFT JOIN users u ON a.created_by = u.id
                    WHERE $where_clause";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute($params);
    $total_announcements = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_announcements / $per_page);
    
    // Get announcements with attachments
    $query = "SELECT a.*, u.username as created_by_username
              FROM announcements a 
              LEFT JOIN users u ON a.created_by = u.id
              WHERE $where_clause
              ORDER BY a.is_pinned DESC, a.created_at DESC 
              LIMIT $per_page OFFSET $offset";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get attachments for each announcement
    foreach ($announcements as &$announcement) {
        $attachment_query = "SELECT id, filename, original_filename, file_path, mime_type, file_size 
                            FROM announcement_attachments 
                            WHERE announcement_id = ? 
                            ORDER BY created_at";
        $attachment_stmt = $db->prepare($attachment_query);
        $attachment_stmt->execute([$announcement['id']]);
        $announcement['attachments'] = $attachment_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    $error_message = "Unable to load announcements: " . $e->getMessage();
    $announcements = [];
    $total_pages = 1;
}

include '../includes/header.php';

ob_start();
?>

<div class="welcome-section">
    <h1 class="welcome-title">📢 School Announcements</h1>
    <p class="welcome-subtitle">Stay updated with the latest school news and important information</p>
</div>

<?php if (isset($error_message)): ?>
    <div style="padding: 1rem; background: var(--danger-light); border: 1px solid var(--danger); border-radius: 8px; margin-bottom: 2rem;">
        <p style="color: var(--danger-dark); margin: 0;">
            <strong>⚠️ Error:</strong> <?= htmlspecialchars($error_message) ?>
        </p>
    </div>
<?php endif; ?>

<!-- Filters and Search -->
<div style="background: var(--white); border-radius: 15px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
    <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
        <div>
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--dark-blue);">Search</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                   placeholder="Search announcements..." 
                   style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-gray); border-radius: 8px; font-size: 1rem;">
        </div>
        
        <div>
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--dark-blue);">Type</label>
            <select name="type" style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-gray); border-radius: 8px; font-size: 1rem;">
                <option value="">All Types</option>
                <option value="General" <?= $type_filter === 'General' ? 'selected' : '' ?>>General</option>
                <option value="Academic" <?= $type_filter === 'Academic' ? 'selected' : '' ?>>Academic</option>
                <option value="Event" <?= $type_filter === 'Event' ? 'selected' : '' ?>>Event</option>
                <option value="Emergency" <?= $type_filter === 'Emergency' ? 'selected' : '' ?>>Emergency</option>
                <option value="Holiday" <?= $type_filter === 'Holiday' ? 'selected' : '' ?>>Holiday</option>
                <option value="Maintenance" <?= $type_filter === 'Maintenance' ? 'selected' : '' ?>>Maintenance</option>
            </select>
        </div>
        
        <div>
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--dark-blue);">Priority</label>
            <select name="priority" style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-gray); border-radius: 8px; font-size: 1rem;">
                <option value="">All Priorities</option>
                <option value="Low" <?= $priority_filter === 'Low' ? 'selected' : '' ?>>Low</option>
                <option value="Normal" <?= $priority_filter === 'Normal' ? 'selected' : '' ?>>Normal</option>
                <option value="High" <?= $priority_filter === 'High' ? 'selected' : '' ?>>High</option>
                <option value="Urgent" <?= $priority_filter === 'Urgent' ? 'selected' : '' ?>>Urgent</option>
            </select>
        </div>
        
        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" style="padding: 0.75rem 1.5rem; background: var(--primary-blue); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                🔍 Filter
            </button>
            <a href="announcements.php" style="padding: 0.75rem 1rem; background: var(--gray); color: white; text-decoration: none; border-radius: 8px; font-weight: 600; display: inline-flex; align-items: center;">
                🔄
            </a>
        </div>
    </form>
</div>

<!-- Results Summary -->
<div style="margin-bottom: 1.5rem; padding: 1rem; background: var(--light-blue); border-radius: 8px;">
    <p style="margin: 0; color: var(--dark-blue); font-weight: 600;">
        📊 Showing <?= count($announcements) ?> of <?= $total_announcements ?> announcements
        <?php if (!empty($search) || !empty($type_filter) || !empty($priority_filter)): ?>
            (filtered)
        <?php endif; ?>
    </p>
</div>

<!-- Announcements List -->
<?php if (empty($announcements)): ?>
    <div style="padding: 3rem; background: var(--white); border-radius: 15px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
        <h3 style="color: var(--gray); margin-bottom: 1rem;">📭 No Announcements Found</h3>
        <p style="color: var(--gray); margin: 0;">
            <?php if (!empty($search) || !empty($type_filter) || !empty($priority_filter)): ?>
                Try adjusting your filters to see more announcements.
            <?php else: ?>
                There are no announcements available at this time.
            <?php endif; ?>
        </p>
    </div>
<?php else: ?>
    <div style="display: grid; gap: 1.5rem;">
        <?php foreach ($announcements as $announcement): ?>
            <div class="announcement-card" 
                 style="background: var(--white); border-radius: 15px; padding: 2rem; box-shadow: 0 5px 15px rgba(0,0,0,0.08); 
                        <?= $announcement['is_pinned'] ? 'border: 2px solid var(--warning); box-shadow: 0 5px 20px rgba(255,193,7,0.2);' : '' ?>">
                
                <!-- Header -->
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1.5rem; gap: 1rem;">
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem; flex-wrap: wrap;">
                            <h2 style="margin: 0; color: var(--dark-blue); font-size: 1.5rem;">
                                <?= $announcement['is_pinned'] ? '📌 ' : '' ?><?= htmlspecialchars($announcement['title']) ?>
                            </h2>
                        </div>
                        
                        <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; font-size: 0.9rem; color: var(--gray);">
                            <span>
                                👤 <?= htmlspecialchars($announcement['created_by_username']) ?>
                            </span>
                            <span>
                                📅 <?= date('M j, Y', strtotime($announcement['created_at'])) ?>
                            </span>
                            <span>
                                🎯 <?= htmlspecialchars($announcement['target_audience']) ?>
                            </span>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <span class="type-badge type-<?= strtolower($announcement['announcement_type']) ?>" 
                              style="padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; white-space: nowrap;
                                     background: var(--light-blue); color: var(--primary-blue);">
                            <?= htmlspecialchars($announcement['announcement_type']) ?>
                        </span>
                        
                        <span class="priority-badge priority-<?= strtolower($announcement['priority']) ?>" 
                              style="padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; white-space: nowrap;
                                     <?php
                                     $priority_colors = [
                                         'low' => 'background: var(--light-gray); color: var(--gray);',
                                         'normal' => 'background: var(--light-blue); color: var(--primary-blue);',
                                         'high' => 'background: var(--warning-light); color: var(--warning);',
                                         'urgent' => 'background: var(--danger-light); color: var(--danger);'
                                     ];
                                     echo $priority_colors[strtolower($announcement['priority'])] ?? $priority_colors['normal'];
                                     ?>">
                            <?= htmlspecialchars($announcement['priority']) ?>
                        </span>
                    </div>
                </div>
                
                <!-- Content -->
                <div style="margin-bottom: 1.5rem;">
                    <div style="color: var(--black); line-height: 1.6; font-size: 1.1rem;">
                        <?= nl2br(htmlspecialchars($announcement['content'])) ?>
                    </div>
                </div>
                
                <!-- Attachments -->
                <?php if (!empty($announcement['attachments'])): ?>
                    <div style="margin-bottom: 1.5rem; padding: 1rem; background: var(--light-gray); border-radius: 8px;">
                        <h4 style="color: var(--dark-blue); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            📎 Attachments (<?= count($announcement['attachments']) ?>)
                        </h4>
                        <div style="display: grid; gap: 0.5rem;">
                            <?php foreach ($announcement['attachments'] as $attachment): ?>
                                <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem; background: var(--white); border-radius: 6px; border: 1px solid var(--border-gray);">
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div style="padding: 0.5rem; background: var(--primary-blue); color: white; border-radius: 6px; font-size: 0.8rem; font-weight: 600;">
                                            <?= strtoupper(pathinfo($attachment['original_filename'], PATHINFO_EXTENSION)) ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; color: var(--black);">
                                                <?= htmlspecialchars($attachment['original_filename']) ?>
                                            </div>
                                            <div style="font-size: 0.9rem; color: var(--gray);">
                                                <?= number_format($attachment['file_size'] / 1024, 1) ?> KB
                                            </div>
                                        </div>
                                    </div>
                                    <a href="<?= htmlspecialchars($attachment['file_path']) ?>" 
                                       target="_blank"
                                       style="padding: 0.5rem 1rem; background: var(--success); color: white; text-decoration: none; border-radius: 6px; font-size: 0.9rem; font-weight: 600;">
                                        📥 Download
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Footer Info -->
                <div style="display: flex; justify-content: between; align-items: center; padding-top: 1rem; border-top: 1px solid var(--border-gray); font-size: 0.9rem; color: var(--gray);">
                    <div>
                        📅 Published: <?= date('M j, Y g:i A', strtotime($announcement['publish_date'])) ?>
                        <?php if ($announcement['expiry_date']): ?>
                            | ⏰ Expires: <?= date('M j, Y', strtotime($announcement['expiry_date'])) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div style="margin-top: 2rem; display: flex; justify-content: center; align-items: center; gap: 0.5rem;">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($type_filter) ? '&type=' . urlencode($type_filter) : '' ?><?= !empty($priority_filter) ? '&priority=' . urlencode($priority_filter) : '' ?>" 
                   style="padding: 0.75rem 1rem; background: var(--primary-blue); color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">
                    ← Previous
                </a>
            <?php endif; ?>
            
            <span style="padding: 0.75rem 1rem; color: var(--gray); font-weight: 600;">
                Page <?= $page ?> of <?= $total_pages ?>
            </span>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($type_filter) ? '&type=' . urlencode($type_filter) : '' ?><?= !empty($priority_filter) ? '&priority=' . urlencode($priority_filter) : '' ?>" 
                   style="padding: 0.75rem 1rem; background: var(--primary-blue); color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">
                    Next →
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<style>
.announcement-card:hover {
    transform: translateY(-2px);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    box-shadow: 0 8px 25px rgba(0,0,0,0.12) !important;
}

.type-badge.type-general {
    background: var(--light-blue) !important;
    color: var(--primary-blue) !important;
}

.type-badge.type-academic {
    background: var(--success-light) !important;
    color: var(--success) !important;
}

.type-badge.type-event {
    background: #e3f2fd !important;
    color: #1976d2 !important;
}

.type-badge.type-emergency {
    background: var(--danger-light) !important;
    color: var(--danger) !important;
}

.type-badge.type-holiday {
    background: #f3e5f5 !important;
    color: #7b1fa2 !important;
}

.type-badge.type-maintenance {
    background: var(--warning-light) !important;
    color: var(--warning) !important;
}

@media (max-width: 768px) {
    .announcement-card {
        padding: 1.5rem !important;
    }
    
    .announcement-card h2 {
        font-size: 1.25rem !important;
    }
}
</style>

<?php
$content = ob_get_clean();
echo $content;

include '../includes/footer.php';
?>
