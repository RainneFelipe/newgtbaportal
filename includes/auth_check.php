<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header('Location: ../index.php');
    exit();
}

// Check if user has the required role
function checkRole($required_roles) {
    if (!isset($_SESSION['role'])) {
        return false;
    }
    
    if (is_array($required_roles)) {
        return in_array($_SESSION['role'], $required_roles);
    } else {
        return $_SESSION['role'] === $required_roles;
    }
}

// Check if user has specific permission
function hasPermission($permission) {
    if (!isset($_SESSION['permissions']) || !is_array($_SESSION['permissions'])) {
        return false;
    }
    
    return isset($_SESSION['permissions'][$permission]) && $_SESSION['permissions'][$permission] === true;
}

// Get current user info
function getCurrentUser() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'role_display' => $_SESSION['role_display'] ?? null,
        'permissions' => $_SESSION['permissions'] ?? []
    ];
}
?>
