<?php
session_start();
include('../config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'service_provider') {
    die("Unauthorized.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $reporter_id = $_SESSION['user_id'];
    $reported_id = intval($_POST['reported_user_id']);
    $booking_id  = intval($_POST['booking_id']);
    $complaint   = mysqli_real_escape_string($conn, $_POST['complaint_text']);

    // Insert the report
    $stmt = $conn->prepare("INSERT INTO reports (reporter_id, reported_id, booking_id, reason, complaint_text, resolution_status) VALUES (?, ?, ?, 'Client Misconduct', ?, 'pending')");
    $stmt->bind_param("iiis", $reporter_id, $reported_id, $booking_id, $complaint);
    $stmt->execute();

    // Automatically Cancel the job
    $cancel_stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND provider_id = ?");
    $cancel_stmt->bind_param("ii", $booking_id, $reporter_id);
    $cancel_stmt->execute();

    header("Location: ../providerDashboard.php?msg=reported");
    exit();
}
?>