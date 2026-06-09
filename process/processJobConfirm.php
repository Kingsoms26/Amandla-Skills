<?php
session_start();
include('../config.php');
include('../dbHelper.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header("Location: ../login.php"); exit();
}

$booking_id  = intval($_POST['booking_id']);
$provider_id = intval($_POST['provider_id']);
$client_id   = $_SESSION['user_id'];

$stmt = $conn->prepare("
    UPDATE bookings 
    SET status = 'completed', payment_status = 'released'
    WHERE id = ? AND client_id = ?
");
$stmt->bind_param("ii", $booking_id, $client_id);
$stmt->execute();
$stmt->close();

// Notify provider
$title   = "Payment Released";
$message = "The client confirmed completion of booking #$booking_id. Your payment has been released.";
$notif   = $conn->prepare("INSERT INTO notifications (user_id, TYPE, reference_id, title, message) VALUES (?, 'payment_released', ?, ?, ?)");
$notif->bind_param("iiss", $provider_id, $booking_id, $title, $message);
$notif->execute();
$notif->close();

header("Location: ../clientDashboard.php");
exit();