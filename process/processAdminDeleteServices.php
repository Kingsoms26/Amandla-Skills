<?php
session_start();
include('../config.php');
include('dbHelper.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['service_id'], $_POST['provider_user_id'], $_POST['reason'])) {
    $service_id      = (int) $_POST['service_id'];
    $provider_user_id = (int) $_POST['provider_user_id'];
    $reason          = trim($_POST['reason']);

    // fetch the service title before deleting for a clear notification message
    $fetch = $conn->prepare("SELECT title FROM services WHERE id = ?");
    $fetch->bind_param("i", $service_id);
    $fetch->execute();
    $svc = $fetch->get_result()->fetch_assoc();

    if ($svc) {
        // delete portfolio images first due to foreign key constraints
        $del_portfolio = $conn->prepare("DELETE FROM service_portfolio WHERE service_id = ?");
        $del_portfolio->bind_param("i", $service_id);
        $del_portfolio->execute();

        // delete the service
        $del = $conn->prepare("DELETE FROM services WHERE id = ?");
        $del->bind_param("i", $service_id);
        $del->execute();

        // notify the provider
        $title   = "Your service listing was removed";
        $message = "An administrator removed your service \"" . $svc['title'] . "\". Reason: " . $reason;

        $notif = $conn->prepare("INSERT INTO notifications (user_id, type, reference_id, title, message, is_read, created_at) VALUES (?, 'admin_action', ?, ?, ?, 0, NOW())");
        $notif->bind_param("iiss", $provider_user_id, $service_id, $title, $message);
        $notif->execute();
    }
}

header("Location: ../adminDashboard.php?msg=success");
exit();