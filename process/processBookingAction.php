<?php
session_start();
include('../config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'service_provider') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $provider_id = $_SESSION['user_id'];
    $booking_id = intval($_POST['booking_id']);
    $action = $_POST['action']; 
    $client_id = intval($_POST['client_id']); 

    if ($action === 'accept') {
        $stmt = $conn->prepare("UPDATE bookings SET status = 'accepted' WHERE id = ? AND provider_id = ?");
        $stmt->bind_param("ii", $booking_id, $provider_id);
        $stmt->execute();
        
        $notif = $conn->prepare("INSERT INTO notifications (user_id, type, reference_id, title, message) VALUES (?, 'booking_update', ?, 'Booking Accepted!', 'A provider has accepted your job request and will be there on the scheduled date.')");
        $notif->bind_param("ii", $client_id, $booking_id);
        $notif->execute();
        
    } elseif ($action === 'decline') {
        $stmt = $conn->prepare("UPDATE bookings SET status = 'declined' WHERE id = ? AND provider_id = ?");
        $stmt->bind_param("ii", $booking_id, $provider_id);
        $stmt->execute();
        
        $notif = $conn->prepare("INSERT INTO notifications (user_id, type, reference_id, title, message) VALUES (?, 'booking_update', ?, 'Booking Declined', 'Unfortunately, the provider was unable to accept your job request.')");
        $notif->bind_param("ii", $client_id, $booking_id);
        $notif->execute();
        
    } elseif ($action === 'complete') {
        $final_price = floatval($_POST['final_price']);
        $stmt = $conn->prepare("UPDATE bookings SET status = 'completed', final_price = ? WHERE id = ? AND provider_id = ?");
        $stmt->bind_param("dii", $final_price, $booking_id, $provider_id);
        $stmt->execute();
        
        $notif = $conn->prepare("INSERT INTO notifications (user_id, type, reference_id, title, message) VALUES (?, 'payment_due', ?, 'Job Completed: Payment Required', 'Your provider has finished the job and set the final price to R " . number_format($final_price, 2) . ". Please log your payment.')");
        $notif->bind_param("ii", $client_id, $booking_id);
        $notif->execute();
    }

    header("Location: ../providerDashboard.php?msg=success");
    exit();
}
?>