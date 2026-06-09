<?php
session_start();
include('../config.php');

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $report_id = intval($_POST['report_id']);
    $action = $_POST['action'];
    
    if ($action === 'resolve') {
        $notes = mysqli_real_escape_string($conn, $_POST['admin_notes']);
        
        // Update the status to 'resolved' and save the admin's notes
        $stmt = $conn->prepare("UPDATE reports SET resolution_status = 'resolved', admin_notes = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $notes, $report_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    header("Location: ../adminDashboard.php?msg=success");
    exit();
}
?>