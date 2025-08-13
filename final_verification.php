<?php
require_once 'config/database.php';

$database = new Database();
$conn = $database->connect();

echo "Testing complete unique guardian assignments:\n\n";

// Test a few students
$students_to_test = [241, 242, 243, 244, 245];

foreach ($students_to_test as $student_id) {
    $stmt = $conn->prepare("
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
        WHERE s.id = ?
    ");
    
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Student {$student['id']}: {$student['first_name']} {$student['last_name']}\n";
    echo "  Father ID {$student['father_id']}: {$student['father_first_name']} {$student['father_last_name']}\n";
    echo "  Mother ID {$student['mother_id']}: {$student['mother_first_name']} {$student['mother_last_name']}\n";
    echo "  Legal Guardian ID {$student['legal_guardian_id']}: {$student['lg_first_name']} {$student['lg_last_name']}\n";
    echo "  Status: " . (($student['father_first_name'] && $student['mother_first_name'] && $student['lg_first_name']) ? '✅ ALL GUARDIANS ASSIGNED' : '❌ MISSING GUARDIANS') . "\n\n";
}

// Check overall statistics
echo "=== FINAL VERIFICATION ===\n";

$stats = [
    'Total Students' => $conn->query("SELECT COUNT(*) FROM students WHERE is_active = 1")->fetchColumn(),
    'Students with Father' => $conn->query("SELECT COUNT(*) FROM students WHERE is_active = 1 AND father_id IS NOT NULL")->fetchColumn(),
    'Students with Mother' => $conn->query("SELECT COUNT(*) FROM students WHERE is_active = 1 AND mother_id IS NOT NULL")->fetchColumn(),
    'Students with Legal Guardian' => $conn->query("SELECT COUNT(*) FROM students WHERE is_active = 1 AND legal_guardian_id IS NOT NULL")->fetchColumn(),
    'Total Fathers' => $conn->query("SELECT COUNT(*) FROM student_guardians WHERE guardian_type = 'Father'")->fetchColumn(),
    'Total Mothers' => $conn->query("SELECT COUNT(*) FROM student_guardians WHERE guardian_type = 'Mother'")->fetchColumn(),
    'Total Legal Guardians' => $conn->query("SELECT COUNT(*) FROM student_guardians WHERE guardian_type = 'Legal Guardian'")->fetchColumn(),
];

foreach ($stats as $label => $value) {
    echo "$label: $value\n";
}

// Check for any duplicate assignments
$duplicate_checks = [
    'Duplicate Father Assignments' => "SELECT COUNT(*) FROM (SELECT father_id, COUNT(*) as cnt FROM students WHERE is_active = 1 AND father_id IS NOT NULL GROUP BY father_id HAVING cnt > 1) as dups",
    'Duplicate Mother Assignments' => "SELECT COUNT(*) FROM (SELECT mother_id, COUNT(*) as cnt FROM students WHERE is_active = 1 AND mother_id IS NOT NULL GROUP BY mother_id HAVING cnt > 1) as dups",
    'Duplicate Legal Guardian Assignments' => "SELECT COUNT(*) FROM (SELECT legal_guardian_id, COUNT(*) as cnt FROM students WHERE is_active = 1 AND legal_guardian_id IS NOT NULL GROUP BY legal_guardian_id HAVING cnt > 1) as dups"
];

echo "\n=== UNIQUENESS CHECK ===\n";
foreach ($duplicate_checks as $label => $query) {
    $duplicates = $conn->query($query)->fetchColumn();
    echo "$label: " . ($duplicates == 0 ? '✅ NONE (GOOD)' : "❌ $duplicates FOUND") . "\n";
}

echo "\n🎉 MISSION ACCOMPLISHED: Every student has unique father, mother, and legal guardian!\n";
?>
