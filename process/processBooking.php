<?php
session_start();
include('../config.php');

// must be logged in as a client to book!
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $client_id = $_SESSION['user_id'];
    $provider_id = intval($_POST['provider_id']);
    
    // formatting the datetime string for the database
    $service_date = date('Y-m-d H:i:s', strtotime($_POST['service_date']));
    
    $phone = mysqli_real_escape_string($conn, $_POST['client_phone']);
    
    $work_description = mysqli_real_escape_string($conn, $_POST['work_description']);
    $status = 'pending';

    $street   = $_POST['street_address'] ?? '';
    $suburb   = $_POST['suburb'] ?? '';
    $province = $_POST['province'] ?? '';
    
    $full_address_string = $street . ", " . $suburb . ", " . $province;
    $service_address = mysqli_real_escape_string($conn, trim($full_address_string));

    $stmt = $conn->prepare("INSERT INTO bookings (client_id, provider_id, service_date, status, work_description, client_phone, service_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisssss", $client_id, $provider_id, $service_date, $status, $work_description, $phone, $service_address);
    
    if ($stmt->execute()) {
        $booking_id = $conn->insert_id; 
        
        // inserts a Notification for the Provider
        $client_name = $_SESSION['name'] ?? 'A client';
        $notif_title = "New Booking Request!";
        $notif_message = "{$client_name} has requested a booking for " . date('M d', strtotime($service_date)) . ". Check your 'New Requests' tab to accept or decline.";
        
        $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, reference_id, title, message) VALUES (?, 'booking_request', ?, ?, ?)");
        $notif_stmt->bind_param("iiss", $provider_id, $booking_id, $notif_title, $notif_message);
        $notif_stmt->execute();
        $notif_stmt->close();
        
        // redirect client to their dashboard
        header("Location: ../clientDashboard.php?msg=booking_sent");
    } else {
        die("Database Error: " . $stmt->error);
    }
    
    $stmt->close();
} else {
    header("Location: ../index.php");
}
exit();
?>