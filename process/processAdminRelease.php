<?php
session_start();
include('../config.php');

// Kick out non-admins
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php"); 
    exit();
}

$booking_id = intval($_POST['booking_id']);
$resolution = $_POST['resolution'] ?? 'release'; // 'release' or 'refund'

// Grab the notes from the modal (Fallback to a default message if empty)
$admin_notes = isset($_POST['admin_notes']) ? trim($_POST['admin_notes']) : 'Resolved by system admin.';
$admin_notes = mysqli_real_escape_string($conn, $admin_notes);

// Get booking details to know WHO to notify
$bk_query = $conn->query("SELECT client_id, provider_id FROM bookings WHERE id = $booking_id");
if ($bk_query && $bk_query->num_rows > 0) {
    $bk = $bk_query->fetch_assoc();
} else {
    die("Booking not found.");
}

if ($resolution === 'refund') {
    // Update Booking to Cancelled/Refunded
    $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled', payment_status = 'refunded' WHERE id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $stmt->close();

    // Notify the Client (They got their money back)
    $title_client = "Dispute Won: Refund Processed";
    $msg_client = "Admin has processed a refund for booking #$booking_id. Admin Notes: " . $admin_notes;
    $notif_c = $conn->prepare("INSERT INTO notifications (user_id, TYPE, reference_id, title, message) VALUES (?, 'refund', ?, ?, ?)");
    $notif_c->bind_param("iiss", $bk['client_id'], $booking_id, $title_client, $msg_client);
    $notif_c->execute();
    $notif_c->close();

    //  Notify the Provider (They lost the dispute)
    $title_provider = "Dispute Lost: Payment Refunded to Client";
    $msg_provider = "The dispute for booking #$booking_id was resolved in favor of the client. Admin Notes: " . $admin_notes;
    $notif_p = $conn->prepare("INSERT INTO notifications (user_id, TYPE, reference_id, title, message) VALUES (?, 'dispute', ?, ?, ?)");
    $notif_p->bind_param("iiss", $bk['provider_id'], $booking_id, $title_provider, $msg_provider);
    $notif_p->execute();
    $notif_p->close();

} else {
    // Update Booking to Completed and set payment_status to 'paid' 
    // This ensures your dashboards and UI recognize the payment as settled.
    $stmt = $conn->prepare("UPDATE bookings SET status = 'completed', payment_status = 'released' WHERE id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $stmt->close();

    // Notify the Provider (They got paid)
    $title_provider = "Dispute Won: Payment Released";
    $msg_provider = "Admin has released your payment for booking #$booking_id. Admin Notes: " . $admin_notes;
    $notif_p = $conn->prepare("INSERT INTO notifications (user_id, TYPE, reference_id, title, message) VALUES (?, 'payment_released', ?, ?, ?)");
    $notif_p->bind_param("iiss", $bk['provider_id'], $booking_id, $title_provider, $msg_provider);
    $notif_p->execute();
    $notif_p->close();

    // Notify the Client (They lost the dispute)
    $title_client = "Dispute Lost: Funds Released to Provider";
    $msg_client = "The dispute for booking #$booking_id was resolved in favor of the provider. Admin Notes: " . $admin_notes;
    $notif_c = $conn->prepare("INSERT INTO notifications (user_id, TYPE, reference_id, title, message) VALUES (?, 'dispute', ?, ?, ?)");
    $notif_c->bind_param("iiss", $bk['client_id'], $booking_id, $title_client, $msg_client);
    $notif_c->execute();
    $notif_c->close();
}

// Redirect back to dashboard with a success message
header("Location: ../adminDashboard.php?msg=success");
exit();
?>