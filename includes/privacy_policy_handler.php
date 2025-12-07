<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// Handle privacy policy acceptance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_privacy_policy'])) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    
    try {
        $database = new Database();
        $conn = $database->connect();
        
        $user_id = $_SESSION['user_id'];
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        
        // Update user's privacy policy acceptance
        $stmt = $conn->prepare(
            "UPDATE users 
             SET privacy_policy_accepted = 1, 
                 privacy_policy_accepted_at = NOW(),
                 privacy_policy_ip_address = ?
             WHERE id = ?"
        );
        
        $stmt->execute([$ip_address, $user_id]);
        
        // Update session
        $_SESSION['privacy_policy_accepted'] = 1;
        
        echo json_encode(['success' => true, 'message' => 'Privacy policy accepted']);
        exit();
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error accepting privacy policy']);
        exit();
    }
}

// Handle privacy policy rejection (logout)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_privacy_policy'])) {
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Session terminated']);
    exit();
}
