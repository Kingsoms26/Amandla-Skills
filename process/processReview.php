<?php
session_start();
include('../config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../clientDashboard.php");
    exit();
}

$client_id    = $_SESSION['user_id'];
$provider_id  = intval($_POST['provider_id']);
$booking_id   = intval($_POST['booking_id']);
$rating       = intval($_POST['rating']);
$comment_text = mysqli_real_escape_string($conn, $_POST['comment_text']);

// Validate rating range
if ($rating < 1 || $rating > 5) {
    header("Location: ../clientDashboard.php?msg=invalid_rating");
    exit();
}

// Verify this booking belongs to this client and is completed
$check = $conn->prepare("SELECT id FROM bookings WHERE id = ? AND client_id = ? AND status = 'completed'");
$check->bind_param("ii", $booking_id, $client_id);
$check->execute();
$check->store_result();

if ($check->num_rows === 0) {
    header("Location: ../clientDashboard.php?msg=invalid_booking");
    exit();
}
$check->close();

// Prevent duplicate review for the same booking
$dup = $conn->prepare("SELECT id FROM reviews WHERE booking_id = ? AND client_id = ?");
$dup->bind_param("ii", $booking_id, $client_id);
$dup->execute();
$dup->store_result();

if ($dup->num_rows > 0) {
    header("Location: ../clientDashboard.php?msg=already_reviewed");
    exit();
}
$dup->close();

// Insert the review
$stmt = $conn->prepare("INSERT INTO reviews (client_id, provider_id, booking_id, rating, comment_text) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("iiiis", $client_id, $provider_id, $booking_id, $rating, $comment_text);

if ($stmt->execute()) {
    header("Location: ../clientDashboard.php?msg=review_submitted");
} else {
    die("Error submitting review: " . $stmt->error);
}

$stmt->close();
exit();
?>