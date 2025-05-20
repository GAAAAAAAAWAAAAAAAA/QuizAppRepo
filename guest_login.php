<?php
session_start();
require_once 'db_connect.php';
include 'session_helper.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nickname = trim($_POST['nickname']);

    if (!empty($nickname)) {
        // Insert guest into DB
        $stmt = $conn->prepare("INSERT INTO users (username, is_guest) VALUES (?, 1)");
        $stmt->bind_param("s", $nickname);
        $stmt->execute();
        
        $user_id = $stmt->insert_id;
        
        // Set session
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $nickname;
        $_SESSION['is_guest'] = true;

        // Redirect to index.php
        header("Location: index.php");
        exit();
    }
}
?>