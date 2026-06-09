<?php
session_start();
include('../config.php');
include('../dbHelper.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'service_provider') {
    header("Location: ../login.php"); exit();
}

$booking_id    = intval($_POST['booking_id']);
$client_id     = intval($_POST['client_id']);
$quoted_price  = floatval($_POST['quoted_price']);
$quote_desc    = trim($_POST['quote_description']);

// Update booking
$stmt = $conn->prepare("
    UPDATE bookings 
    SET quoted_price = ?, quote_description = ?, status = 'quote_submitted'
    WHERE id = ? AND provider_id = ?
");
$stmt->bind_param("dsii", $quoted_price, $quote_desc, $booking_id, $_SESSION['user_id']);
$stmt->execute();
$stmt->close();

// Notify client
$title   = "Quote Received";
$message = "Your service provider has submitted a quote of R" . number_format($quoted_price, 2) . " for booking #$booking_id. Please review it in your dashboard.";
$notif   = $conn->prepare("INSERT INTO notifications (user_id, TYPE, reference_id, title, message) VALUES (?, 'quote', ?, ?, ?)");
$notif->bind_param("iiss", $client_id, $booking_id, $title, $message);
$notif->execute();
$notif->close();

header("Location: ../providerDashboard.php?msg=quote_sent");
exit();