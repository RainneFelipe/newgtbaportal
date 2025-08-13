<?php
/**
 * Database Cleanup Script
 * Removes redundant backup tables created during guardian relationship fixes
 */

require_once 'config/database.php';

$backup_tables_to_remove = [
    'student_guardians_backup_unique',
    'students_backup_guardian_fix', 
    'students_backup_simple_fix',
    'students_backup_unique_guardians'
];

$is_cli = php_sapi_name() === 'cli';

if (!$is_cli) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Database Cleanup</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .success { color: green; font-weight: bold; }
            .error { color: red; font-weight: bold; }
            .warning { color: orange; font-weight: bold; }
            .info { color: blue; }
            .step { margin: 10px 0; padding: 10px; border-left: 3px solid #007cba; }
        </style>
    </head>
    <body>
    <h1>Database Cleanup - Remove Backup Tables</h1>";
}

function log_message($message, $type = 'info') {
    global $is_cli;
    
    if ($is_cli) {
        echo "[" . date('Y-m-d H:i:s') . "] " . strtoupper($type) . ": $message\n";
    } else {
        $class = $type === 'error' ? 'error' : ($type === 'success' ? 'success' : ($type === 'warning' ? 'warning' : 'info'));
        echo "<div class='step $class'>[" . date('Y-m-d H:i:s') . "] $message</div>\n";
    }
}

try {
    $database = new Database();
    $conn = $database->connect();
    
    log_message("Starting database cleanup process...");
    
    // Check which tables actually exist
    $existing_tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $tables_to_remove = array_intersect($backup_tables_to_remove, $existing_tables);
    
    if (empty($tables_to_remove)) {
        log_message("No backup tables found to remove.", 'info');
        return;
    }
    
    log_message("Found " . count($tables_to_remove) . " backup tables to remove:", 'warning');
    
    // Show what will be removed
    $total_size = 0;
    foreach ($tables_to_remove as $table) {
        $count = $conn->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        $size_query = "SELECT ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 3) AS size_mb 
                      FROM information_schema.TABLES 
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table'";
        $size = $conn->query($size_query)->fetchColumn() ?: 0;
        $total_size += $size;
        
        log_message("• $table ($count records, {$size} MB)", 'warning');
    }
    
    log_message("Total space to reclaim: {$total_size} MB", 'warning');
    
    // Add confirmation if running in browser
    if (!$is_cli) {
        echo "<div class='step warning'>";
        echo "<strong>⚠️ CONFIRMATION REQUIRED</strong><br>";
        echo "Are you sure you want to remove these backup tables?<br>";
        echo "<button onclick='proceedWithCleanup()' style='background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 5px; margin: 10px 5px;'>Yes, Remove Backup Tables</button>";
        echo "<button onclick='window.location.reload()' style='background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 5px; margin: 10px 5px;'>Cancel</button>";
        echo "</div>";
        
        // Add JavaScript for confirmation
        echo "<script>
        function proceedWithCleanup() {
            if (confirm('This will permanently delete the backup tables. Are you sure?')) {
                window.location.href = window.location.href + '&confirm=yes';
            }
        }
        </script>";
        
        // Check for confirmation parameter
        if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
            echo "</body></html>";
            exit;
        }
    }
    
    log_message("Proceeding with cleanup...", 'warning');
    
    // Remove the tables
    $removed_count = 0;
    foreach ($tables_to_remove as $table) {
        try {
            $conn->exec("DROP TABLE `$table`");
            log_message("✓ Removed table: $table", 'success');
            $removed_count++;
        } catch (Exception $e) {
            log_message("✗ Failed to remove $table: " . $e->getMessage(), 'error');
        }
    }
    
    if ($removed_count === count($tables_to_remove)) {
        log_message("🎉 Cleanup completed successfully! Removed $removed_count backup tables.", 'success');
        log_message("Reclaimed approximately {$total_size} MB of database space.", 'success');
    } else {
        log_message("⚠️ Partial cleanup: $removed_count/" . count($tables_to_remove) . " tables removed.", 'warning');
    }
    
    // Verify cleanup
    $remaining_backup_tables = array_intersect($backup_tables_to_remove, 
                                             $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN));
    
    if (empty($remaining_backup_tables)) {
        log_message("✅ Verification: No backup tables remain in database.", 'success');
    } else {
        log_message("⚠️ Some backup tables still exist: " . implode(', ', $remaining_backup_tables), 'warning');
    }
    
} catch (Exception $e) {
    log_message("❌ Error during cleanup: " . $e->getMessage(), 'error');
}

if (!$is_cli) {
    echo "<hr>";
    echo "<div class='step info'>";
    echo "<strong>Database Status:</strong><br>";
    echo "The database is now cleaned of redundant backup tables.<br>";
    echo "Your application should continue to work normally.";
    echo "</div>";
    echo "</body></html>";
}
?>
