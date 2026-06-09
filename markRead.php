<?php
session_start();
include('config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); 
    exit();
}

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['user_role'] ?? 'client';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update all unread notifications for this specific user
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Send them back to their respective dashboard
    $dash = ($role === 'admin') ? 'adminDashboard.php' : (($role === 'service_provider') ? 'providerDashboard.php' : 'clientDashboard.php');
    header("Location: " . $dash);
    exit();
}


$notif_id = intval($_GET['id'] ?? 0);
$type     = $_GET['type'] ?? '';
$ref_id   = intval($_GET['ref'] ?? 0);

if ($notif_id > 0) {
    // Specifically target the exact notification ID for this user
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notif_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

// Redirect based on notification type and role
switch ($type) {
    case 'quote':
        header("Location: clientDashboard.php");
        break;

    case 'quote_response':
        header("Location: providerDashboard.php");
        break;

    case 'payment':
        header("Location: providerDashboard.php");
        break;

    case 'job_complete':
        header("Location: clientDashboard.php");
        break;

    case 'payment_released':
        if ($role === 'service_provider') {
            header("Location: providerDashboard.php");
        } else {
            header("Location: clientDashboard.php");
        }
        break;

    case 'dispute':
        if ($role === 'admin') {
            header("Location: adminDashboard.php?open=disputes");
        } else {
            header("Location: clientDashboard.php");
        }
        break;

    case 'refund':
        header("Location: clientDashboard.php");
        break;

    case 'booking_request':
        header("Location: providerDashboard.php");
        break;

    case 'booking_update':
    case 'payment_due':
        header("Location: clientDashboard.php");
        break;

    case 'broadcast':
    case 'system':
    default:
        if ($role === 'admin') {
            header("Location: adminDashboard.php");
        } elseif ($role === 'service_provider') {
            header("Location: providerDashboard.php");
        } else {
            header("Location: clientDashboard.php");
        }
        break;
}
exit();
?>