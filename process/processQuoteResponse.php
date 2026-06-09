<?php
session_start();
include('../config.php');
include('../dbHelper.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header("Location: ../login.php"); exit();
}

$booking_id  = intval($_POST['booking_id']);
$provider_id = intval($_POST['provider_id']);
$action      = $_POST['action']; // 'accept' or 'reject'
$client_id   = $_SESSION['user_id'];

if ($action === 'accept') {
    // Get quoted price to copy to final_price
    $row = $conn->query("SELECT quoted_price FROM bookings WHERE id = $booking_id")->fetch_assoc();
    
    $stmt = $conn->prepare("
        UPDATE bookings 
        SET status = 'quote_accepted', final_price = quoted_price 
        WHERE id = ? AND client_id = ?
    ");
    $stmt->bind_param("ii", $booking_id, $client_id);
    $stmt->execute();
    $stmt->close();

    $title   = "Quote Accepted";
    $message = "Your quote for booking #$booking_id has been accepted. Awaiting client payment.";

} else {
    $stmt = $conn->prepare("
        UPDATE bookings 
        SET status = 'accepted', quoted_price = NULL, quote_description = NULL
        WHERE id = ? AND client_id = ?
    ");
    $stmt->bind_param("ii", $booking_id, $client_id);
    $stmt->execute();
    $stmt->close();

    $title   = "Quote Rejected";
    $message = "Your quote for booking #$booking_id was rejected. Please submit a revised quote.";
}

// Notify provider
$notif = $conn->prepare("INSERT INTO notifications (user_id, TYPE, reference_id, title, message) VALUES (?, 'quote_response', ?, ?, ?)");
$notif->bind_param("iiss", $provider_id, $booking_id, $title, $message);
$notif->execute();
$notif->close();

header("Location: ../clientDashboard.php");
exit();