<?php
session_start();
include('../config.php');
include('../dbHelper.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_id'], $_POST['reason'])) {
    $review_id = (int) $_POST['review_id'];
    $reason    = trim($_POST['reason']);

    // fetch client id for notification
    $fetch = $conn->prepare("SELECT client_id FROM reviews WHERE id = ?");
    $fetch->bind_param("i", $review_id);
    $fetch->execute();
    $row = $fetch->get_result()->fetch_assoc();

    if ($row) {
        // delete the review
        $del = $conn->prepare("DELETE FROM reviews WHERE id = ?");
        $del->bind_param("i", $review_id);
        $del->execute();

        // notify the client
        $title   = "Your review was removed";
        $message = "An administrator removed one of your reviews. Reason: " . $reason;

        $notif = $conn->prepare("INSERT INTO notifications (user_id, type, reference_id, title, message, is_read, created_at) VALUES (?, 'admin_action', ?, ?, ?, 0, NOW())");
        $notif->bind_param("iiss", $row['client_id'], $review_id, $title, $message);
        $notif->execute();
    }
}

header("Location: ../adminDashboard.php?msg=success");
exit();