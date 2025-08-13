<?php
require_once 'config/database.php';

$database = new Database();
$conn = $database->connect();

$students = $conn->query("SELECT COUNT(*) FROM students WHERE is_active = 1")->fetchColumn();
$fathers = $conn->query("SELECT COUNT(*) FROM student_guardians WHERE guardian_type = 'Father'")->fetchColumn();
$mothers = $conn->query("SELECT COUNT(*) FROM student_guardians WHERE guardian_type = 'Mother'")->fetchColumn();
$lg = $conn->query("SELECT COUNT(*) FROM student_guardians WHERE guardian_type = 'Legal Guardian'")->fetchColumn();

echo "Current counts:\n";
echo "Students: $students\n";
echo "Fathers: $fathers\n";
echo "Mothers: $mothers\n";
echo "Legal Guardians: $lg\n\n";

echo "For 1:1 unique mapping, we need:\n";
echo "Fathers needed: " . ($students - $fathers) . " additional\n";
echo "Mothers needed: " . ($students - $mothers) . " additional\n";
echo "Legal Guardians needed: " . ($students - $lg) . " additional\n";
?>
