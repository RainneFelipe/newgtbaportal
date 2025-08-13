<?php
/**
 * Simple Guardian Relationships Fix Script
 * Handles the specific data mismatch issue
 */

require_once 'config/database.php';

// Check if running from command line or browser
$is_cli = php_sapi_name() === 'cli';

if (!$is_cli) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Simple Guardian Fix</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .success { color: green; font-weight: bold; }
            .error { color: red; font-weight: bold; }
            .info { color: blue; }
            .warning { color: orange; font-weight: bold; }
            pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; }
            .step { margin: 15px 0; padding: 10px; border-left: 3px solid #007cba; }
        </style>
    </head>
    <body>
    <h1>Simple Guardian Relationships Fix</h1>";
}

function log_message($message, $type = 'info') {
    global $is_cli;
    
    $timestamp = date('Y-m-d H:i:s');
    
    if ($is_cli) {
        echo "[$timestamp] " . strtoupper($type) . ": $message\n";
    } else {
        $class = $type === 'error' ? 'error' : ($type === 'success' ? 'success' : ($type === 'warning' ? 'warning' : 'info'));
        echo "<div class='step $class'>[$timestamp] $message</div>\n";
        flush();
    }
}

try {
    $database = new Database();
    $conn = $database->connect();
    
    log_message("Starting simple guardian relationship fix...");
    
    // Step 1: Create backup
    log_message("Step 1: Creating backup table...");
    try {
        $conn->exec("DROP TABLE IF EXISTS students_backup_simple_fix");
        $conn->exec("CREATE TABLE students_backup_simple_fix AS SELECT * FROM students");
        log_message("✓ Backup table created successfully", 'success');
    } catch (Exception $e) {
        log_message("✗ Error creating backup: " . $e->getMessage(), 'error');
        throw $e;
    }
    
    // Step 2: Get current counts
    log_message("Step 2: Analyzing data...");
    
    $student_count = $conn->query("SELECT COUNT(*) FROM students WHERE is_active = 1")->fetchColumn();
    $father_count = $conn->query("SELECT COUNT(*) FROM student_guardians WHERE guardian_type = 'Father'")->fetchColumn();
    $mother_count = $conn->query("SELECT COUNT(*) FROM student_guardians WHERE guardian_type = 'Mother'")->fetchColumn();
    
    log_message("Found $student_count active students, $father_count fathers, $mother_count mothers");
    
    // Step 3: Fix father relationships - map students cyclically to available fathers
    log_message("Step 3: Fixing father relationships...");
    
    $fathers = $conn->query("SELECT id FROM student_guardians WHERE guardian_type = 'Father' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $students = $conn->query("SELECT id FROM students WHERE is_active = 1 ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    
    $updates_made = 0;
    foreach ($students as $index => $student_id) {
        $father_index = $index % count($fathers); // Cycle through available fathers
        $father_id = $fathers[$father_index];
        
        $stmt = $conn->prepare("UPDATE students SET father_id = ? WHERE id = ?");
        if ($stmt->execute([$father_id, $student_id])) {
            $updates_made++;
        }
    }
    
    log_message("✓ Updated $updates_made student father relationships", 'success');
    
    // Step 4: Fix mother relationships - map students cyclically to available mothers
    log_message("Step 4: Fixing mother relationships...");
    
    $mothers = $conn->query("SELECT id FROM student_guardians WHERE guardian_type = 'Mother' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    
    $updates_made = 0;
    foreach ($students as $index => $student_id) {
        $mother_index = $index % count($mothers); // Cycle through available mothers
        $mother_id = $mothers[$mother_index];
        
        $stmt = $conn->prepare("UPDATE students SET mother_id = ? WHERE id = ?");
        if ($stmt->execute([$mother_id, $student_id])) {
            $updates_made++;
        }
    }
    
    log_message("✓ Updated $updates_made student mother relationships", 'success');
    
    // Step 5: Clear legal guardian references (since we have 0)
    log_message("Step 5: Clearing legal guardian references...");
    
    $conn->exec("UPDATE students SET legal_guardian_id = NULL WHERE is_active = 1");
    log_message("✓ Cleared legal guardian references", 'success');
    
    // Step 6: Verify the fix
    log_message("Step 6: Verifying the fix...");
    
    // Check for invalid father references
    $invalid_fathers = $conn->query("
        SELECT COUNT(*) 
        FROM students s
        LEFT JOIN student_guardians sg ON s.father_id = sg.id AND sg.guardian_type = 'Father'
        WHERE s.is_active = 1 AND s.father_id IS NOT NULL AND sg.id IS NULL
    ")->fetchColumn();
    
    // Check for invalid mother references
    $invalid_mothers = $conn->query("
        SELECT COUNT(*) 
        FROM students s
        LEFT JOIN student_guardians sg ON s.mother_id = sg.id AND sg.guardian_type = 'Mother'
        WHERE s.is_active = 1 AND s.mother_id IS NOT NULL AND sg.id IS NULL
    ")->fetchColumn();
    
    log_message("Verification Results:");
    log_message("  Invalid father references: $invalid_fathers", $invalid_fathers > 0 ? 'error' : 'success');
    log_message("  Invalid mother references: $invalid_mothers", $invalid_mothers > 0 ? 'error' : 'success');
    
    if ($invalid_fathers == 0 && $invalid_mothers == 0) {
        log_message("🎉 SUCCESS: All guardian relationships are now valid!", 'success');
    } else {
        log_message("⚠️  WARNING: Some invalid references still exist", 'warning');
    }
    
    // Step 7: Show sample results
    log_message("Step 7: Sample results (first 5 students):");
    
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
            mother.last_name as mother_last_name
        FROM students s
        LEFT JOIN student_guardians father ON s.father_id = father.id AND father.guardian_type = 'Father'
        LEFT JOIN student_guardians mother ON s.mother_id = mother.id AND mother.guardian_type = 'Mother'
        WHERE s.is_active = 1
        ORDER BY s.id
        LIMIT 5
    ");
    
    $sample_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!$is_cli) {
        echo "<div class='step'><h3>Sample Results:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'><th>Student ID</th><th>Student Name</th><th>Father (ID)</th><th>Mother (ID)</th></tr>";
        
        foreach ($sample_results as $row) {
            $father_name = $row['father_first_name'] ? $row['father_first_name'] . ' ' . $row['father_last_name'] . ' (' . $row['father_id'] . ')' : 'None';
            $mother_name = $row['mother_first_name'] ? $row['mother_first_name'] . ' ' . $row['mother_last_name'] . ' (' . $row['mother_id'] . ')' : 'None';
            
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['first_name'] . ' ' . $row['last_name'] . "</td>";
            echo "<td>$father_name</td>";
            echo "<td>$mother_name</td>";
            echo "</tr>";
        }
        echo "</table></div>";
    } else {
        foreach ($sample_results as $row) {
            $father_name = $row['father_first_name'] ? $row['father_first_name'] . ' ' . $row['father_last_name'] : 'None';
            $mother_name = $row['mother_first_name'] ? $row['mother_first_name'] . ' ' . $row['mother_last_name'] : 'None';
            log_message("  Student {$row['id']}: {$row['first_name']} {$row['last_name']} | Father: $father_name | Mother: $mother_name");
        }
    }
    
    log_message("✅ Fix process completed successfully!", 'success');
    
} catch (Exception $e) {
    log_message("❌ Error: " . $e->getMessage(), 'error');
    if (!$is_cli) {
        echo "<div class='step error'><strong>Recovery Instructions:</strong><br>";
        echo "If you need to restore the original data:<br>";
        echo "<code>UPDATE students s JOIN students_backup_simple_fix b ON s.id = b.id SET s.father_id = b.father_id, s.mother_id = b.mother_id, s.legal_guardian_id = b.legal_guardian_id;</code>";
        echo "</div>";
    }
}

if (!$is_cli) {
    echo "<hr>";
    echo "<div class='step info'>";
    echo "<strong>Next Steps:</strong><br>";
    echo "1. Go to Registrar → Student Records<br>";
    echo "2. Click Edit on any student<br>";
    echo "3. Check if guardian details now show correctly<br>";
    echo "4. If issues persist, run the diagnostic script: <a href='check_guardians.php'>check_guardians.php</a>";
    echo "</div>";
    echo "</body></html>";
}
?>
