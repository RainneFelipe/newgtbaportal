<?php
require_once '../includes/auth_check.php';

// Only allow admin access
if (!checkRole(['admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

require_once '../config/database.php';

header('Content-Type: application/json');

$database = new Database();
$conn = $database->connect();

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['error' => 'User ID is required']);
    exit();
}

try {
    // Get user information
    $sql = "SELECT u.*, r.name as role, r.display_name as role_display
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.id 
            WHERE u.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['error' => 'User not found']);
        exit();
    }
    
    // Format dates
    $user['created_at'] = date('F j, Y g:i A', strtotime($user['created_at']));
    $user['archived_at'] = $user['archived_at'] ? date('F j, Y g:i A', strtotime($user['archived_at'])) : 'Not archived';
    
    $response = [
        'user' => $user
    ];
    
    // Get student information if user is a student
    if ($user['role'] === 'student') {
        $sql = "SELECT s.*, 
                       CONCAT(s.first_name, ' ', IFNULL(CONCAT(s.middle_name, ' '), ''), s.last_name) as full_name,
                       gl.grade_name,
                       sec.section_name
                FROM students s
                LEFT JOIN grade_levels gl ON s.current_grade_level_id = gl.id
                LEFT JOIN sections sec ON s.current_section_id = sec.id
                WHERE s.user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$user_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($student) {
            $response['student'] = $student;
        }
    }
    
    // Get teacher information if user is a teacher
    if ($user['role'] === 'teacher') {
        $sql = "SELECT t.*,
                       CONCAT(t.first_name, ' ', IFNULL(CONCAT(t.middle_name, ' '), ''), t.last_name) as full_name
                FROM teachers t
                WHERE t.user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$user_id]);
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($teacher) {
            $response['teacher'] = $teacher;
            
            // Get assigned sections
            $sql = "SELECT s.section_name, gl.grade_name, sy.year_label,
                           st.is_primary
                    FROM section_teachers st
                    JOIN sections s ON st.section_id = s.id
                    JOIN grade_levels gl ON s.grade_level_id = gl.id
                    JOIN school_years sy ON s.school_year_id = sy.id
                    WHERE st.teacher_id = ?
                    ORDER BY sy.is_active DESC, gl.grade_order";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$teacher['id']]);
            $response['teacher']['sections'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // Get recent audit logs for this user
    $sql = "SELECT action, created_at, ip_address
            FROM audit_logs 
            WHERE record_id = ? AND table_name = 'users'
            ORDER BY created_at DESC 
            LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id]);
    $audit_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format audit log dates
    foreach ($audit_logs as &$log) {
        $log['created_at'] = date('F j, Y g:i A', strtotime($log['created_at']));
    }
    
    $response['audit_logs'] = $audit_logs;
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
