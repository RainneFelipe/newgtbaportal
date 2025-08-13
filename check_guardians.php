<?php
/**
 * Guardian Relationships Diagnostic Script
 * This script checks the current state of guardian relationships in the database
 */

require_once 'config/database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Guardian Relationships Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .success { color: green; font-weight: bold; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
    </style>
</head>
<body>
<h1>Guardian Relationships Diagnostic</h1>";

try {
    $database = new Database();
    $conn = $database->connect();
    
    // 1. Guardian Statistics
    echo "<div class='section'>
    <h2>1. Guardian Statistics</h2>";
    
    $stmt = $conn->query("
        SELECT guardian_type, COUNT(*) as count, MIN(id) as min_id, MAX(id) as max_id
        FROM student_guardians 
        GROUP BY guardian_type 
        ORDER BY guardian_type
    ");
    $guardian_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>
    <tr><th>Guardian Type</th><th>Count</th><th>Min ID</th><th>Max ID</th></tr>";
    
    foreach ($guardian_stats as $stat) {
        echo "<tr>
            <td>{$stat['guardian_type']}</td>
            <td>{$stat['count']}</td>
            <td>{$stat['min_id']}</td>
            <td>{$stat['max_id']}</td>
        </tr>";
    }
    echo "</table></div>";
    
    // 2. Student Statistics
    echo "<div class='section'>
    <h2>2. Student Statistics</h2>";
    
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
    
    echo "<table>
    <tr><th>Metric</th><th>Value</th></tr>
    <tr><td>Total Active Students</td><td>{$student_stats['total_students']}</td></tr>
    <tr><td>Students with Father</td><td>{$student_stats['students_with_father']}</td></tr>
    <tr><td>Students with Mother</td><td>{$student_stats['students_with_mother']}</td></tr>
    <tr><td>Students with Legal Guardian</td><td>{$student_stats['students_with_legal_guardian']}</td></tr>
    <tr><td>Father ID Range</td><td>{$student_stats['min_father_id']} - {$student_stats['max_father_id']}</td></tr>
    <tr><td>Mother ID Range</td><td>{$student_stats['min_mother_id']} - {$student_stats['max_mother_id']}</td></tr>
    </table></div>";
    
    // 3. Invalid References Check
    echo "<div class='section'>
    <h2>3. Invalid References Check</h2>";
    
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
    
    echo "<table>
    <tr><th>Reference Type</th><th>Invalid Count</th><th>Status</th></tr>";
    
    $father_status = $invalid_fathers > 0 ? "<span class='error'>INVALID</span>" : "<span class='success'>OK</span>";
    $mother_status = $invalid_mothers > 0 ? "<span class='error'>INVALID</span>" : "<span class='success'>OK</span>";
    $lg_status = $invalid_legal_guardians > 0 ? "<span class='error'>INVALID</span>" : "<span class='success'>OK</span>";
    
    echo "<tr><td>Father References</td><td>$invalid_fathers</td><td>$father_status</td></tr>
    <tr><td>Mother References</td><td>$invalid_mothers</td><td>$mother_status</td></tr>
    <tr><td>Legal Guardian References</td><td>$invalid_legal_guardians</td><td>$lg_status</td></tr>
    </table></div>";
    
    // 4. Sample Data with Issues
    if ($invalid_fathers > 0 || $invalid_mothers > 0 || $invalid_legal_guardians > 0) {
        echo "<div class='section'>
        <h2>4. Students with Invalid References (First 10)</h2>";
        
        $stmt = $conn->query("
            SELECT 
                s.id,
                s.first_name,
                s.last_name,
                s.father_id,
                CASE WHEN father.id IS NULL AND s.father_id IS NOT NULL THEN 'INVALID' ELSE 'OK' END as father_status,
                s.mother_id,
                CASE WHEN mother.id IS NULL AND s.mother_id IS NOT NULL THEN 'INVALID' ELSE 'OK' END as mother_status,
                s.legal_guardian_id,
                CASE WHEN lg.id IS NULL AND s.legal_guardian_id IS NOT NULL THEN 'INVALID' ELSE 'OK' END as lg_status
            FROM students s
            LEFT JOIN student_guardians father ON s.father_id = father.id AND father.guardian_type = 'Father'
            LEFT JOIN student_guardians mother ON s.mother_id = mother.id AND mother.guardian_type = 'Mother'
            LEFT JOIN student_guardians lg ON s.legal_guardian_id = lg.id AND lg.guardian_type = 'Legal Guardian'
            WHERE s.is_active = 1
            AND (
                (father.id IS NULL AND s.father_id IS NOT NULL) OR
                (mother.id IS NULL AND s.mother_id IS NOT NULL) OR
                (lg.id IS NULL AND s.legal_guardian_id IS NOT NULL)
            )
            ORDER BY s.id
            LIMIT 10
        ");
        
        $invalid_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table>
        <tr><th>ID</th><th>Student Name</th><th>Father ID</th><th>Father Status</th><th>Mother ID</th><th>Mother Status</th><th>Legal Guardian ID</th><th>LG Status</th></tr>";
        
        foreach ($invalid_students as $student) {
            $father_status_class = $student['father_status'] == 'INVALID' ? 'error' : 'success';
            $mother_status_class = $student['mother_status'] == 'INVALID' ? 'error' : 'success';
            $lg_status_class = $student['lg_status'] == 'INVALID' ? 'error' : 'success';
            
            echo "<tr>
                <td>{$student['id']}</td>
                <td>{$student['first_name']} {$student['last_name']}</td>
                <td>{$student['father_id']}</td>
                <td class='$father_status_class'>{$student['father_status']}</td>
                <td>{$student['mother_id']}</td>
                <td class='$mother_status_class'>{$student['mother_status']}</td>
                <td>{$student['legal_guardian_id']}</td>
                <td class='$lg_status_class'>{$student['lg_status']}</td>
            </tr>";
        }
        echo "</table></div>";
        
        echo "<div class='section'>
        <h2>5. Recommended Action</h2>
        <p class='warning'>⚠️ Invalid guardian relationships detected!</p>
        <p>To fix this issue, please run the <strong>fix_guardians.php</strong> script:</p>
        <p><a href='fix_guardians.php' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Run Fix Script</a></p>
        <p><em>Note: This will create a backup of your students table before making any changes.</em></p>
        </div>";
        
    } else {
        echo "<div class='section'>
        <h2>4. Status</h2>
        <p class='success'>✓ All guardian relationships are valid!</p>
        </div>";
    }
    
    // 5. Sample Valid Data
    echo "<div class='section'>
    <h2>5. Sample Student-Guardian Relationships (First 5 Students)</h2>";
    
    $stmt = $conn->query("
        SELECT 
            s.id,
            s.first_name,
            s.last_name,
            s.father_id,
            CONCAT(IFNULL(father.first_name, ''), ' ', IFNULL(father.last_name, '')) as father_name,
            s.mother_id,
            CONCAT(IFNULL(mother.first_name, ''), ' ', IFNULL(mother.last_name, '')) as mother_name,
            s.legal_guardian_id,
            CONCAT(IFNULL(lg.first_name, ''), ' ', IFNULL(lg.last_name, '')) as lg_name
        FROM students s
        LEFT JOIN student_guardians father ON s.father_id = father.id AND father.guardian_type = 'Father'
        LEFT JOIN student_guardians mother ON s.mother_id = mother.id AND mother.guardian_type = 'Mother'
        LEFT JOIN student_guardians lg ON s.legal_guardian_id = lg.id AND lg.guardian_type = 'Legal Guardian'
        WHERE s.is_active = 1
        ORDER BY s.id
        LIMIT 5
    ");
    
    $sample_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>
    <tr><th>Student ID</th><th>Student Name</th><th>Father (ID)</th><th>Mother (ID)</th><th>Legal Guardian (ID)</th></tr>";
    
    foreach ($sample_students as $student) {
        $father_display = trim($student['father_name']) ?: 'None';
        if ($student['father_id'] && trim($student['father_name'])) {
            $father_display .= " ({$student['father_id']})";
        }
        
        $mother_display = trim($student['mother_name']) ?: 'None';
        if ($student['mother_id'] && trim($student['mother_name'])) {
            $mother_display .= " ({$student['mother_id']})";
        }
        
        $lg_display = trim($student['lg_name']) ?: 'None';
        if ($student['legal_guardian_id'] && trim($student['lg_name'])) {
            $lg_display .= " ({$student['legal_guardian_id']})";
        }
        
        echo "<tr>
            <td>{$student['id']}</td>
            <td>{$student['first_name']} {$student['last_name']}</td>
            <td>$father_display</td>
            <td>$mother_display</td>
            <td>$lg_display</td>
        </tr>";
    }
    echo "</table></div>";
    
} catch (Exception $e) {
    echo "<div class='section'><p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p></div>";
}

echo "</body></html>";
?>
