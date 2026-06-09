<?php
session_start();
include('../config.php');

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    die("Access Denied");
}

// handle the form submission for broadcasting a message to all users (admin only)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);

    // inserts one notification for every user ID in the users table
    $sql = "INSERT INTO notifications (user_id, type, title, message) 
            SELECT id, 'broadcast', '$title', '$message' FROM users";

    if ($conn->query($sql)) {
        header("Location: ../adminDashboard.php?msg=success");
    } else {
        die("Broadcast Failed: " . $conn->error);
    }
}
exit();