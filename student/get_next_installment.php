<?php
require_once '../includes/auth_check.php';

// Check if user is a student
if (!checkRole('student')) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once '../config/database.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->connect();

// Get student information
$student_query = "SELECT id FROM students WHERE user_id = ?";
$student_stmt = $db->prepare($student_query);
$student_stmt->execute([$_SESSION['user_id']]);
$student_id = $student_stmt->fetchColumn();

if (!$student_id) {
    echo json_encode(['error' => 'Student not found']);
    exit();
}

$payment_term_id = $_GET['payment_term_id'] ?? null;

if (!$payment_term_id) {
    echo json_encode(['error' => 'Payment term ID required']);
    exit();
}

try {
    // Get the highest installment number already paid for this payment term
    $installment_query = "SELECT COALESCE(MAX(installment_number), 0) as last_installment
                         FROM student_payments
                         WHERE student_id = :student_id
                         AND payment_term_id = :payment_term_id
                         AND payment_type = 'monthly_installment'
                         AND verification_status != 'rejected'";
    $installment_stmt = $db->prepare($installment_query);
    $installment_stmt->bindParam(':student_id', $student_id);
    $installment_stmt->bindParam(':payment_term_id', $payment_term_id);
    $installment_stmt->execute();
    $last_installment = $installment_stmt->fetchColumn();
    
    echo json_encode([
        'next_installment' => $last_installment + 1,
        'last_installment' => $last_installment
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
?>
