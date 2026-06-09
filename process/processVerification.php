<?php
session_start();
include('../config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $provider_id = intval($_POST['provider_id']);
    
    // update the database to verify the provider
    $stmt = $conn->prepare("UPDATE provider_profiles SET is_verified_pro = 1 WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $provider_id);
        $stmt->execute();
        $stmt->close();
        
        // send notification to provider about verification
        $notif_title = "Account Verified!";
        $notif_msg = "Congratulations! Your account has been approved by the Amandla Skills team. You now have the Verified Pro badge on your profile.";
        
        $n_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'system', ?, ?)");
        if ($n_stmt) {
            $n_stmt->bind_param("iss", $provider_id, $notif_title, $notif_msg);
            $n_stmt->execute();
            $n_stmt->close();
        }
    }
    
    header("Location: ../adminDashboard.php?msg=success");
    exit();
}
?>