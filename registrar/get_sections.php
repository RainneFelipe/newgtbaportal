<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth_check.php';
require_once '../config/database.php';

// Check if user is registrar
if ($_SESSION['role'] !== 'registrar') {
    http_response_code(403);
    exit();
}

header('Content-Type: application/json');

$grade_id = $_GET['grade_id'] ?? '';

if (empty($grade_id)) {
    echo json_encode([]);
    exit();
}

try {
    $database = new Database();
    $conn = $database->connect();
    
    $stmt = $conn->prepare("SELECT id, section_name FROM sections 
                           WHERE grade_level_id = ? AND is_active = 1 
                           ORDER BY section_name");
    $stmt->execute([$grade_id]);
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($sections);
    
} catch (Exception $e) {
    error_log("Get sections error: " . $e->getMessage());
    echo json_encode([]);
}
?>
