<?php
session_start();

// 1. Turn on error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2. Include config (assuming this file is in the same folder as config.php)
include('config.php');

// 3. Capture the booking ID from PayFast's return URL
$booking_id = intval($_GET['booking_id'] ?? 0);

if ($booking_id === 0) {
    die("<div style='font-family:sans-serif; padding:20px;'><h3>Error: No Booking ID received.</h3><p>PayFast didn't send the booking ID back to the website.</p></div>");
}

// 4. Fetch the booking data directly
$fetch = $conn->prepare("SELECT client_id, provider_id, quoted_price FROM bookings WHERE id = ?");
$fetch->bind_param("i", $booking_id);
$fetch->execute();
$booking = $fetch->get_result()->fetch_assoc();
$fetch->close();

if (!$booking) {
    die("<div style='font-family:sans-serif; padding:20px;'><h3>Error: Booking Not Found.</h3><p>Could not find booking ID #$booking_id in the database.</p></div>");
}

$client_id = $booking['client_id'];
$provider_id = $booking['provider_id'];

// 5. Update Bookings Table
$stmt = $conn->prepare("
    UPDATE bookings 
    SET status = 'payment_held',
        payment_status = 'held',
        paid_at = NOW(),
        release_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)
    WHERE id = ?
");
$stmt->bind_param("i", $booking_id);

if (!$stmt->execute()) {
    die("<div style='font-family:sans-serif; padding:20px;'><h3>Database Update Failed</h3><p>Error: " . $stmt->error . "</p></div>");
}
$stmt->close();

// 6. Update Notifications Table
$title   = "Funds Secured via PayFast";
$message = "Payment of R" . number_format($booking['quoted_price'], 2) . " has been secured in escrow. You may now begin work.";

$notif = $conn->prepare("INSERT INTO notifications (user_id, type, reference_id, title, message) VALUES (?, 'payment', ?, ?, ?)");
if ($notif) {
    $notif->bind_param("iiss", $provider_id, $booking_id, $title, $message);
    $notif->execute();
    $notif->close();
}

// 7. Success! Redirect to Dashboard
header("Location: clientDashboard.php?msg=payment_success");
exit();
?>