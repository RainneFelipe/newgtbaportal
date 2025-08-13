<?php
require_once 'config/database.php';

$database = new Database();
$conn = $database->connect();

echo "Testing guardian relationships for student 241:\n";

$sql = "SELECT s.*, 
               f.first_name as father_first_name, f.last_name as father_last_name,
               m.first_name as mother_first_name, m.last_name as mother_last_name
        FROM students s 
        LEFT JOIN student_guardians f ON s.father_id = f.id AND f.guardian_type = 'Father'
        LEFT JOIN student_guardians m ON s.mother_id = m.id AND m.guardian_type = 'Mother'
        WHERE s.id = 241";

$stmt = $conn->prepare($sql);
$stmt->execute();
$student = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Student: {$student['first_name']} {$student['last_name']}\n";
echo "Father ID: {$student['father_id']} -> {$student['father_first_name']} {$student['father_last_name']}\n";
echo "Mother ID: {$student['mother_id']} -> {$student['mother_first_name']} {$student['mother_last_name']}\n";

if ($student['father_first_name'] && $student['mother_first_name']) {
    echo "✅ SUCCESS: Guardian data is now properly linked!\n";
} else {
    echo "❌ FAILED: Guardian data still missing\n";
}
?>
