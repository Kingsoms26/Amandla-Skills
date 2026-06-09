<?php
session_start();
include('../config.php');

// must be logged in to favorite something!
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (isset($_GET['provider_id'])) {
    $client_id = $_SESSION['user_id'];
    $provider_id = intval($_GET['provider_id']);

    // check if this favorite already exists in the database
    $check_stmt = $conn->prepare("SELECT * FROM favourites WHERE client_id = ? AND provider_id = ?");
    $check_stmt->bind_param("ii", $client_id, $provider_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        // if its already a favorite, remove it (unfavorite)
        $del_stmt = $conn->prepare("DELETE FROM favourites WHERE client_id = ? AND provider_id = ?");
        $del_stmt->bind_param("ii", $client_id, $provider_id);
        $del_stmt->execute();
        $del_stmt->close();
    } else {
        // It's not favorited yet
        $ins_stmt = $conn->prepare("INSERT INTO favourites (client_id, provider_id) VALUES (?, ?)");
        $ins_stmt->bind_param("ii", $client_id, $provider_id);
        $ins_stmt->execute();
        $ins_stmt->close();
    }
    $check_stmt->close();
}

// Send them back to where they came from
header("Location: ../index.php");
exit();
?>