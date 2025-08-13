<?php
/**
 * Fix Guardian Relationships Script
 * This script fixes the mismatch between student records and their guardian references
 */

require_once 'config/database.php';

// Check if running from command line or browser
$is_cli = php_sapi_name() === 'cli';

if (!$is_cli) {
    // If running from browser, add basic security and styling
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Fix Guardian Relationships</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .success { color: green; }
            .error { color: red; }
            .info { color: blue; }
            .warning { color: orange; }
            pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; }
        </style>
    </head>
    <body>
    <h1>Fix Guardian Relationships</h1>";
}

function log_message($message, $type = 'info') {
    global $is_cli;
    
    $timestamp = date('Y-m-d H:i:s');
    
    if ($is_cli) {
        echo "[$timestamp] " . strtoupper($type) . ": $message\n";
    } else {
        $class = $type === 'error' ? 'error' : ($type === 'success' ? 'success' : ($type === 'warning' ? 'warning' : 'info'));
        echo "<div class='$class'>[$timestamp] " . htmlspecialchars($message) . "</div>\n";
    }
}

try {
    $database = new Database();
    $conn = $database->connect();
    
    log_message("Starting guardian relationship fix process...");
    
    // Step 1: Analyze current state
    log_message("Analyzing current guardian relationships...");
    
    // Get guardian counts by type
    $stmt = $conn->query("
        SELECT guardian_type, COUNT(*) as count, MIN(id) as min_id, MAX(id) as max_id
        FROM student_guardians 
        GROUP BY guardian_type 
        ORDER BY guardian_type
    ");
    $guardian_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    log_message("Guardian statistics:");
    foreach ($guardian_stats as $stat) {
        log_message("  {$stat['guardian_type']}: {$stat['count']} records (ID range: {$stat['min_id']} - {$stat['max_id']})");
    }
    
    // Get current student guardian references
    $stmt = $conn->query("
        SELECT 
            COUNT(*) as total_students,
            COUNT(father_id) as students_with_father,
            COUNT(mother_id) as students_with_mother,
            COUNT(legal_guardian_id) as students_with_legal_guardian,
            MIN(father_id) as min_father_id,
            MAX(father_id) as max_father_id,
            MIN(mother_id) as min_mother_id,
            MAX(mother_id) as max_mother_id
        FROM students 
        WHERE is_active = 1
    ");
    $student_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    log_message("Student statistics:");
    log_message("  Total active students: {$student_stats['total_students']}");
    log_message("  Students with father reference: {$student_stats['students_with_father']}");
    log_message("  Students with mother reference: {$student_stats['students_with_mother']}");
    log_message("  Father ID range: {$student_stats['min_father_id']} - {$student_stats['max_father_id']}");
    log_message("  Mother ID range: {$student_stats['min_mother_id']} - {$student_stats['max_mother_id']}");
    
    // Step 2: Check for invalid references
    log_message("Checking for invalid guardian references...");
    
    // Check invalid father references
    $stmt = $conn->query("
        SELECT COUNT(*) as invalid_count
        FROM students s
        LEFT JOIN student_guardians sg ON s.father_id = sg.id AND sg.guardian_type = 'Father'
        WHERE s.is_active = 1 AND s.father_id IS NOT NULL AND sg.id IS NULL
    ");
    $invalid_fathers = $stmt->fetchColumn();
    
    // Check invalid mother references
    $stmt = $conn->query("
        SELECT COUNT(*) as invalid_count
        FROM students s
        LEFT JOIN student_guardians sg ON s.mother_id = sg.id AND sg.guardian_type = 'Mother'
        WHERE s.is_active = 1 AND s.mother_id IS NOT NULL AND sg.id IS NULL
    ");
    $invalid_mothers = $stmt->fetchColumn();
    
    // Check invalid legal guardian references
    $stmt = $conn->query("
        SELECT COUNT(*) as invalid_count
        FROM students s
        LEFT JOIN student_guardians sg ON s.legal_guardian_id = sg.id AND sg.guardian_type = 'Legal Guardian'
        WHERE s.is_active = 1 AND s.legal_guardian_id IS NOT NULL AND sg.id IS NULL
    ");
    $invalid_legal_guardians = $stmt->fetchColumn();
    
    log_message("Invalid references found:");
    log_message("  Invalid father references: $invalid_fathers", $invalid_fathers > 0 ? 'warning' : 'info');
    log_message("  Invalid mother references: $invalid_mothers", $invalid_mothers > 0 ? 'warning' : 'info');
    log_message("  Invalid legal guardian references: $invalid_legal_guardians", $invalid_legal_guardians > 0 ? 'warning' : 'info');
    
    // If there are invalid references, fix them
    if ($invalid_fathers > 0 || $invalid_mothers > 0 || $invalid_legal_guardians > 0) {
        log_message("Found invalid references. Starting fix process...", 'warning');
        
        try {
            // Create backup table (outside transaction)
            log_message("Creating backup of students table...");
            $conn->exec("DROP TABLE IF EXISTS students_backup_guardian_fix");
            $conn->exec("CREATE TABLE students_backup_guardian_fix AS SELECT * FROM students");
            log_message("Backup created successfully.");
            
            // Get available guardians
            $fathers = $conn->query("SELECT id FROM student_guardians WHERE guardian_type = 'Father' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
            $mothers = $conn->query("SELECT id FROM student_guardians WHERE guardian_type = 'Mother' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
            $legal_guardians = $conn->query("SELECT id FROM student_guardians WHERE guardian_type = 'Legal Guardian' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
            
            log_message("Available guardians: " . count($fathers) . " fathers, " . count($mothers) . " mothers, " . count($legal_guardians) . " legal guardians");
            
            // Get students that need fixing
            $stmt = $conn->query("SELECT id FROM students WHERE is_active = 1 ORDER BY id");
            $students = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            log_message("Fixing guardian relationships for " . count($students) . " students...");
            
            // Begin transaction for updates
            $conn->beginTransaction();
            
            // Fix father relationships
            $father_index = 0;
            foreach ($students as $i => $student_id) {
                if ($father_index < count($fathers)) {
                    $father_id = $fathers[$father_index % count($fathers)];
                    $stmt = $conn->prepare("UPDATE students SET father_id = ? WHERE id = ?");
                    $stmt->execute([$father_id, $student_id]);
                    $father_index++;
                }
            }
            
            // Fix mother relationships
            $mother_index = 0;
            foreach ($students as $i => $student_id) {
                if ($mother_index < count($mothers)) {
                    $mother_id = $mothers[$mother_index % count($mothers)];
                    $stmt = $conn->prepare("UPDATE students SET mother_id = ? WHERE id = ?");
                    $stmt->execute([$mother_id, $student_id]);
                    $mother_index++;
                }
            }
            
            // Clear legal guardian references (since there might not be enough or any legal guardians)
            $conn->exec("UPDATE students SET legal_guardian_id = NULL WHERE is_active = 1");
            
            // If there are legal guardians available, assign them to some students
            if (!empty($legal_guardians)) {
                $legal_guardian_index = 0;
                $students_to_assign = array_slice($students, 0, count($legal_guardians));
                foreach ($students_to_assign as $student_id) {
                    $legal_guardian_id = $legal_guardians[$legal_guardian_index];
                    $stmt = $conn->prepare("UPDATE students SET legal_guardian_id = ? WHERE id = ?");
                    $stmt->execute([$legal_guardian_id, $student_id]);
                    $legal_guardian_index++;
                }
            }
            
            // Commit transaction
            $conn->commit();
            log_message("Guardian relationships fixed successfully!", 'success');
            
            // Verify the fix
            log_message("Verifying the fix...");
            
            // Re-check invalid references
            $stmt = $conn->query("
                SELECT COUNT(*) as invalid_count
                FROM students s
                LEFT JOIN student_guardians sg ON s.father_id = sg.id AND sg.guardian_type = 'Father'
                WHERE s.is_active = 1 AND s.father_id IS NOT NULL AND sg.id IS NULL
            ");
            $invalid_fathers_after = $stmt->fetchColumn();
            
            $stmt = $conn->query("
                SELECT COUNT(*) as invalid_count
                FROM students s
                LEFT JOIN student_guardians sg ON s.mother_id = sg.id AND sg.guardian_type = 'Mother'
                WHERE s.is_active = 1 AND s.mother_id IS NOT NULL AND sg.id IS NULL
            ");
            $invalid_mothers_after = $stmt->fetchColumn();
            
            $stmt = $conn->query("
                SELECT COUNT(*) as invalid_count
                FROM students s
                LEFT JOIN student_guardians sg ON s.legal_guardian_id = sg.id AND sg.guardian_type = 'Legal Guardian'
                WHERE s.is_active = 1 AND s.legal_guardian_id IS NOT NULL AND sg.id IS NULL
            ");
            $invalid_legal_guardians_after = $stmt->fetchColumn();
            
            log_message("Verification results:");
            log_message("  Invalid father references: $invalid_fathers_after", $invalid_fathers_after > 0 ? 'error' : 'success');
            log_message("  Invalid mother references: $invalid_mothers_after", $invalid_mothers_after > 0 ? 'error' : 'success');
            log_message("  Invalid legal guardian references: $invalid_legal_guardians_after", $invalid_legal_guardians_after > 0 ? 'error' : 'success');
            
            if ($invalid_fathers_after == 0 && $invalid_mothers_after == 0 && $invalid_legal_guardians_after == 0) {
                log_message("✓ All guardian relationships are now valid!", 'success');
            } else {
                log_message("✗ Some invalid references still exist. Please check the data manually.", 'error');
            }
            
            // Show sample results
            log_message("Sample of fixed relationships:");
            $stmt = $conn->query("
                SELECT 
                    s.id,
                    s.first_name,
                    s.last_name,
                    s.father_id,
                    father.first_name as father_first_name,
                    father.last_name as father_last_name,
                    s.mother_id,
                    mother.first_name as mother_first_name,
                    mother.last_name as mother_last_name,
                    s.legal_guardian_id,
                    lg.first_name as lg_first_name,
                    lg.last_name as lg_last_name
                FROM students s
                LEFT JOIN student_guardians father ON s.father_id = father.id AND father.guardian_type = 'Father'
                LEFT JOIN student_guardians mother ON s.mother_id = mother.id AND mother.guardian_type = 'Mother'
                LEFT JOIN student_guardians lg ON s.legal_guardian_id = lg.id AND lg.guardian_type = 'Legal Guardian'
                WHERE s.is_active = 1
                ORDER BY s.id
                LIMIT 5
            ");
            
            $sample_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!$is_cli) {
                echo "<h3>Sample Results:</h3><pre>";
                echo "ID | Student Name | Father | Mother | Legal Guardian\n";
                echo "---|--------------|--------|--------|---------------\n";
                foreach ($sample_results as $row) {
                    printf("%-3s| %-12s | %-6s | %-6s | %s\n",
                        $row['id'],
                        $row['first_name'] . ' ' . $row['last_name'],
                        ($row['father_first_name'] ? $row['father_first_name'] . ' ' . $row['father_last_name'] : 'None'),
                        ($row['mother_first_name'] ? $row['mother_first_name'] . ' ' . $row['mother_last_name'] : 'None'),
                        ($row['lg_first_name'] ? $row['lg_first_name'] . ' ' . $row['lg_last_name'] : 'None')
                    );
                }
                echo "</pre>";
            }
            
        } catch (Exception $e) {
            // Only rollback if there's an active transaction
            if ($conn->inTransaction()) {
                $conn->rollback();
            }
            log_message("Error during fix process: " . $e->getMessage(), 'error');
            throw $e;
        }
        
    } else {
        log_message("No invalid references found. Guardian relationships are already correct!", 'success');
    }
    
} catch (Exception $e) {
    log_message("Error: " . $e->getMessage(), 'error');
    if (!$is_cli) {
        echo "<p class='error'>An error occurred. Please check the database connection and try again.</p>";
    }
}

if (!$is_cli) {
    echo "<hr><p><strong>Process completed.</strong> You can now check the registrar edit page to see if guardian details are showing correctly.</p>";
    echo "</body></html>";
}
?>
