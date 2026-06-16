<?php
session_start();
include('../config.php');

// Admin only
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $admin_id = $_SESSION['user_id'];
    $request_id = intval($_POST['request_id']);
    $provider_id = intval($_POST['provider_id']);
    $action = $_POST['action'];

    if ($action === 'toggle_interview') {
        $status = $_POST['interview_status'] == '1' ? 1 : 0;
        
        $t_stmt = $conn->prepare("UPDATE provider_profiles SET interview_completed = ? WHERE user_id = ?");
        $t_stmt->bind_param("ii", $status, $provider_id);
        $t_stmt->execute();
        $t_stmt->close();
        
        $req_status = $status ? 'pending' : 'interview_scheduled';
        $conn->query("UPDATE verification_requests SET status = '$req_status' WHERE id = $request_id");
        
    } elseif ($action === 'approve') {
        $approved_tier = htmlspecialchars($_POST['approved_tier']);
        
        // Update Profile 
        $ap_stmt = $conn->prepare("UPDATE provider_profiles SET verification_tier = ?, verification_status = 'approved', is_verified_pro = 1, verification_approved_at = NOW() WHERE user_id = ?");
        $ap_stmt->bind_param("si", $approved_tier, $provider_id);
        $ap_stmt->execute();
        $ap_stmt->close();
        $conn->query("UPDATE verification_requests SET status = 'approved', reviewed_at = NOW(), reviewed_by = $admin_id WHERE id = $request_id");

        // Notify Provider
        $notif_title = "Account Verified!";
        $notif_msg = "Congratulations! You have been approved for the " . ucwords(str_replace('_', ' ', $approved_tier)) . " tier.";
        $n_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'system', ?, ?)");
        if ($n_stmt) {
            $n_stmt->bind_param("iss", $provider_id, $notif_title, $notif_msg);
            $n_stmt->execute();
            $n_stmt->close();
        }
        
    } elseif ($action === 'reject') {
        $admin_notes = htmlspecialchars($_POST['admin_notes']);
        
        $rej_stmt = $conn->prepare("UPDATE provider_profiles SET verification_status = 'rejected' WHERE user_id = ?");
        $rej_stmt->bind_param("i", $provider_id);
        $rej_stmt->execute();
        $rej_stmt->close();
        
        $r_stmt = $conn->prepare("UPDATE verification_requests SET status = 'rejected', admin_notes = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
        $r_stmt->bind_param("sii", $admin_notes, $admin_id, $request_id);
        $r_stmt->execute();
        $r_stmt->close();
    }
}

// Kick back to dashboard with the tab open
header("Location: ../adminDashboard.php?msg=success&open=verifications");
exit();
?>