<?php
require_once 'config/database.php';

$database = new Database();
$conn = $database->connect();

echo "Checking guardian distribution among students...\n\n";

// Check father distribution
echo "=== FATHER DISTRIBUTION ===\n";
$stmt = $conn->query("
    SELECT 
        sg.id,
        sg.first_name,
        sg.last_name,
        COUNT(s.id) as student_count,
        GROUP_CONCAT(CONCAT(s.first_name, ' ', s.last_name) SEPARATOR ', ') as students
    FROM student_guardians sg
    LEFT JOIN students s ON sg.id = s.father_id AND s.is_active = 1
    WHERE sg.guardian_type = 'Father'
    GROUP BY sg.id
    ORDER BY student_count DESC, sg.id
    LIMIT 10
");

$fathers = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($fathers as $father) {
    echo "Father {$father['id']}: {$father['first_name']} {$father['last_name']} -> {$father['student_count']} students";
    if ($father['student_count'] > 0) {
        $students_list = strlen($father['students']) > 50 ? substr($father['students'], 0, 50) . '...' : $father['students'];
        echo " ({$students_list})";
    }
    echo "\n";
}

echo "\n=== MOTHER DISTRIBUTION ===\n";
$stmt = $conn->query("
    SELECT 
        sg.id,
        sg.first_name,
        sg.last_name,
        COUNT(s.id) as student_count,
        GROUP_CONCAT(CONCAT(s.first_name, ' ', s.last_name) SEPARATOR ', ') as students
    FROM student_guardians sg
    LEFT JOIN students s ON sg.id = s.mother_id AND s.is_active = 1
    WHERE sg.guardian_type = 'Mother'
    GROUP BY sg.id
    ORDER BY student_count DESC, sg.id
    LIMIT 10
");

$mothers = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($mothers as $mother) {
    echo "Mother {$mother['id']}: {$mother['first_name']} {$mother['last_name']} -> {$mother['student_count']} students";
    if ($mother['student_count'] > 0) {
        $students_list = strlen($mother['students']) > 50 ? substr($mother['students'], 0, 50) . '...' : $mother['students'];
        echo " ({$students_list})";
    }
    echo "\n";
}

// Get overall statistics
echo "\n=== SUMMARY STATISTICS ===\n";
$stmt = $conn->query("SELECT COUNT(DISTINCT father_id) as unique_fathers FROM students WHERE is_active = 1 AND father_id IS NOT NULL");
$unique_fathers = $stmt->fetchColumn();

$stmt = $conn->query("SELECT COUNT(DISTINCT mother_id) as unique_mothers FROM students WHERE is_active = 1 AND mother_id IS NOT NULL");
$unique_mothers = $stmt->fetchColumn();

$stmt = $conn->query("SELECT COUNT(*) as total_students FROM students WHERE is_active = 1");
$total_students = $stmt->fetchColumn();

echo "Total Students: $total_students\n";
echo "Unique Fathers Used: $unique_fathers\n";
echo "Unique Mothers Used: $unique_mothers\n";
echo "Students per Father: " . round($total_students / $unique_fathers, 1) . " average\n";
echo "Students per Mother: " . round($total_students / $unique_mothers, 1) . " average\n";

// Check if any students share the same parents
echo "\n=== SIBLINGS CHECK ===\n";
$stmt = $conn->query("
    SELECT 
        father_id, 
        mother_id,
        COUNT(*) as sibling_count,
        GROUP_CONCAT(CONCAT(first_name, ' ', last_name) SEPARATOR ', ') as siblings
    FROM students 
    WHERE is_active = 1 AND father_id IS NOT NULL AND mother_id IS NOT NULL
    GROUP BY father_id, mother_id
    HAVING COUNT(*) > 1
    ORDER BY sibling_count DESC
    LIMIT 5
");

$sibling_groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (count($sibling_groups) > 0) {
    echo "Found " . count($sibling_groups) . " sibling groups:\n";
    foreach ($sibling_groups as $group) {
        echo "- {$group['sibling_count']} siblings with same parents: {$group['siblings']}\n";
    }
} else {
    echo "No siblings found (all students have unique parent combinations)\n";
}
?>
