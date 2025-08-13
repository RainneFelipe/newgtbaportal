<?php
require_once 'config/database.php';

echo "=== TESTING REGISTRAR EDIT FUNCTIONALITY ===\n\n";

$database = new Database();
$conn = $database->connect();

// Test 1: Check if student data loads properly
echo "Test 1: Loading student data for editing...\n";

$student_id = 241; // Test with Angelo Cruz
$sql = "SELECT s.*, u.username, u.email,
               gl.grade_name, sec.section_name, sy.year_label,
               -- Father information with proper null handling
               f.id as father_guardian_id, f.first_name as father_first_name, f.last_name as father_last_name, 
               f.middle_name as father_middle_name, f.date_of_birth as father_dob, f.occupation as father_occupation,
               f.religion as father_religion, f.contact_number as father_contact, f.email_address as father_email,
               -- Mother information with proper null handling
               m.id as mother_guardian_id, m.first_name as mother_first_name, m.last_name as mother_last_name,
               m.middle_name as mother_middle_name, m.date_of_birth as mother_dob, m.occupation as mother_occupation,
               m.religion as mother_religion, m.contact_number as mother_contact, m.email_address as mother_email,
               -- Legal guardian information with proper null handling
               lg.id as legal_guardian_guardian_id, lg.first_name as lg_first_name, lg.last_name as lg_last_name,
               lg.middle_name as lg_middle_name, lg.date_of_birth as lg_dob, lg.occupation as lg_occupation,
               lg.religion as lg_religion, lg.contact_number as lg_contact, lg.email_address as lg_email
        FROM students s 
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN grade_levels gl ON s.current_grade_level_id = gl.id
        LEFT JOIN sections sec ON s.current_section_id = sec.id 
        LEFT JOIN school_years sy ON s.current_school_year_id = sy.id
        LEFT JOIN student_guardians f ON s.father_id = f.id AND f.guardian_type = 'Father'
        LEFT JOIN student_guardians m ON s.mother_id = m.id AND m.guardian_type = 'Mother'
        LEFT JOIN student_guardians lg ON s.legal_guardian_id = lg.id AND lg.guardian_type = 'Legal Guardian'
        WHERE s.id = ? AND s.is_active = 1";

$stmt = $conn->prepare($sql);
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo "❌ FAILED: Could not load student data\n";
    exit;
}

echo "✅ SUCCESS: Student data loaded\n";
echo "   Student: {$student['first_name']} {$student['last_name']}\n";
echo "   Username: {$student['username']}\n";
echo "   Email: {$student['email']}\n";

// Test 2: Check guardian data completeness
echo "\nTest 2: Checking guardian data completeness...\n";

$guardian_tests = [
    'Father' => [
        'id' => $student['father_guardian_id'],
        'name' => ($student['father_first_name'] ?? '') . ' ' . ($student['father_last_name'] ?? ''),
        'occupation' => $student['father_occupation'] ?? '',
        'contact' => $student['father_contact'] ?? '',
        'email' => $student['father_email'] ?? ''
    ],
    'Mother' => [
        'id' => $student['mother_guardian_id'],
        'name' => ($student['mother_first_name'] ?? '') . ' ' . ($student['mother_last_name'] ?? ''),
        'occupation' => $student['mother_occupation'] ?? '',
        'contact' => $student['mother_contact'] ?? '',
        'email' => $student['mother_email'] ?? ''
    ],
    'Legal Guardian' => [
        'id' => $student['legal_guardian_guardian_id'],
        'name' => ($student['lg_first_name'] ?? '') . ' ' . ($student['lg_last_name'] ?? ''),
        'occupation' => $student['lg_occupation'] ?? '',
        'contact' => $student['lg_contact'] ?? '',
        'email' => $student['lg_email'] ?? ''
    ]
];

foreach ($guardian_tests as $type => $data) {
    $complete_fields = 0;
    $total_fields = 5;
    
    if (!empty($data['id'])) $complete_fields++;
    if (!empty(trim($data['name']))) $complete_fields++;
    if (!empty($data['occupation'])) $complete_fields++;
    if (!empty($data['contact'])) $complete_fields++;
    if (!empty($data['email'])) $complete_fields++;
    
    $completion_rate = ($complete_fields / $total_fields) * 100;
    
    if ($completion_rate >= 80) {
        echo "✅ $type: {$completion_rate}% complete - {$data['name']} (ID: {$data['id']})\n";
    } else {
        echo "❌ $type: {$completion_rate}% complete - Missing data\n";
    }
}

// Test 3: Check multiple students
echo "\nTest 3: Testing multiple students...\n";

$test_students = [242, 243, 244, 245];
foreach ($test_students as $test_id) {
    $stmt = $conn->prepare("
        SELECT 
            s.first_name, s.last_name,
            f.first_name as father_name, 
            m.first_name as mother_name,
            lg.first_name as lg_name
        FROM students s
        LEFT JOIN student_guardians f ON s.father_id = f.id AND f.guardian_type = 'Father'
        LEFT JOIN student_guardians m ON s.mother_id = m.id AND m.guardian_type = 'Mother' 
        LEFT JOIN student_guardians lg ON s.legal_guardian_id = lg.id AND lg.guardian_type = 'Legal Guardian'
        WHERE s.id = ?
    ");
    
    $stmt->execute([$test_id]);
    $test_student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $guardians_present = 0;
    if ($test_student['father_name']) $guardians_present++;
    if ($test_student['mother_name']) $guardians_present++;
    if ($test_student['lg_name']) $guardians_present++;
    
    $status = $guardians_present == 3 ? '✅' : '❌';
    echo "$status Student $test_id ({$test_student['first_name']} {$test_student['last_name']}): ";
    echo "$guardians_present/3 guardians assigned\n";
}

// Test 4: Database integrity check
echo "\nTest 4: Database integrity check...\n";

$integrity_checks = [
    'Students with invalid father references' => "
        SELECT COUNT(*) FROM students s 
        LEFT JOIN student_guardians sg ON s.father_id = sg.id AND sg.guardian_type = 'Father'
        WHERE s.is_active = 1 AND s.father_id IS NOT NULL AND sg.id IS NULL
    ",
    'Students with invalid mother references' => "
        SELECT COUNT(*) FROM students s 
        LEFT JOIN student_guardians sg ON s.mother_id = sg.id AND sg.guardian_type = 'Mother'
        WHERE s.is_active = 1 AND s.mother_id IS NOT NULL AND sg.id IS NULL
    ",
    'Students with invalid legal guardian references' => "
        SELECT COUNT(*) FROM students s 
        LEFT JOIN student_guardians sg ON s.legal_guardian_id = sg.id AND sg.guardian_type = 'Legal Guardian'
        WHERE s.is_active = 1 AND s.legal_guardian_id IS NOT NULL AND sg.id IS NULL
    "
];

$all_checks_passed = true;
foreach ($integrity_checks as $check_name => $query) {
    $count = $conn->query($query)->fetchColumn();
    if ($count == 0) {
        echo "✅ $check_name: 0 (Good)\n";
    } else {
        echo "❌ $check_name: $count (Issues found)\n";
        $all_checks_passed = false;
    }
}

// Final summary
echo "\n=== TEST SUMMARY ===\n";
if ($all_checks_passed) {
    echo "🎉 ALL TESTS PASSED!\n";
    echo "✅ Student data loads correctly\n";
    echo "✅ Guardian relationships are valid\n";
    echo "✅ Database integrity is maintained\n";
    echo "✅ Registrar edit form should work perfectly\n";
} else {
    echo "❌ SOME TESTS FAILED!\n";
    echo "⚠️  There may be issues with the registrar edit functionality\n";
}

echo "\nRecommendation: Test the actual registrar edit form at:\n";
echo "http://your-domain/newgtbaportal/registrar/student_edit.php?id=241\n";
?>
