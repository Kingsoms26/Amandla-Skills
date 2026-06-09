<?php
session_start();
include('../config.php');
include('../dbHelper.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'service_provider') {
    header("Location: ../login.php"); exit();
}

$booking_id = intval($_POST['booking_id']);
$client_id  = intval($_POST['client_id']);

$stmt = $conn->prepare("
    UPDATE bookings 
    SET status = 'pending_review'
    WHERE id = ? AND provider_id = ?
");
$stmt->bind_param("ii", $booking_id, $_SESSION['user_id']);
$stmt->execute();
$stmt->close();

// Notify client
$title   = "Job Marked Complete";
$message = "Your provider has marked booking #$booking_id as complete. Please confirm or raise a dispute within 24 hours.";
$notif   = $conn->prepare("INSERT INTO notifications (user_id, TYPE, reference_id, title, message) VALUES (?, 'job_complete', ?, ?, ?)");
$notif->bind_param("iiss", $client_id, $booking_id, $title, $message);
$notif->execute();
$notif->close();

header("Location: ../providerDashboard.php");
exit();