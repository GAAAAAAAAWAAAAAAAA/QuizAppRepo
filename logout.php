<?php
session_start();


// Check if user is guest and remove from database if needed
if (isset($_SESSION['is_guest']) && $_SESSION['is_guest'] === true && isset($_SESSION['user_id'])) {
    include 'db_connect.php';
    
    $user_id = $_SESSION['user_id'];
    
    // Delete guest user from database
    $sql = "DELETE FROM users WHERE user_id = ? AND is_guest = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    $conn->close();
}

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to the dashboard
header("Location: dashboard.php");
exit();
?>