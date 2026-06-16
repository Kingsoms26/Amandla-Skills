<?php
session_start();
include('../config.php');
include('../dbHelper.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header("Location: ../login.php"); exit();
}

$booking_id  = intval($_POST['booking_id']);
$provider_id = intval($_POST['provider_id']);
$reason      = trim($_POST['reason']);
$client_id   = $_SESSION['user_id'];

$stmt = $conn->prepare("
    UPDATE bookings 
    SET status = 'disputed', payment_status = 'disputed'
    WHERE id = ? AND client_id = ?
");
$stmt->bind_param("ii", $booking_id, $client_id);
$stmt->execute();
$stmt->close();

$admin_row = $conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetch_assoc();
$admin_id  = $admin_row['id'] ?? null;

// Notify admin
if ($admin_id) {
    $title   = "Dispute Raised";
    $message = "A dispute has been raised on booking #$booking_id. Reason: " . substr($reason, 0, 100) . ". Please review in the Payments tab.";
    $notif   = $conn->prepare("INSERT INTO notifications (user_id, TYPE, reference_id, title, message) VALUES (?, 'dispute', ?, ?, ?)");
    $notif->bind_param("iiss", $admin_id, $booking_id, $title, $message);
    $notif->execute();
    $notif->close();
}

header("Location: ../clientDashboard.php");
exit();