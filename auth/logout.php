<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    // User session cleanup can be added here if needed
}

// Destroy session
session_destroy();

// Redirect to login page
header('Location: ../index.php');
exit();
?>
