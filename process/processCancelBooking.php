<?php
session_start();
include('../config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header("Location: ../login.php"); 
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id  = intval($_POST['booking_id']);
    $provider_id = intval($_POST['provider_id']);
    $client_id   = $_SESSION['user_id'];

    $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND client_id = ? AND status IN ('pending', 'quote_submitted')");
    $stmt->bind_param("ii", $booking_id, $client_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $title = "Booking Cancelled";
        $message = "The client has cancelled their booking request for Job #$booking_id.";
        
        $notif = $conn->prepare("INSERT INTO notifications (user_id, TYPE, reference_id, title, message) VALUES (?, 'cancellation', ?, ?, ?)");
        $notif->bind_param("iiss", $provider_id, $booking_id, $title, $message);
        $notif->execute();
        $notif->close();
    }
    $stmt->close();
}

header("Location: ../clientDashboard.php?msg=cancelled");
exit();
?>