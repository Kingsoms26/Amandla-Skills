<?php
session_start();
include('../config.php');

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $action = $_POST['action'];
    $target_user_id = intval($_POST['target_user_id']);
    
    // Prevent an admin from accidentally deleting or changing their own role from this panel
    if ($target_user_id === $_SESSION['user_id']) {
        die("Security Error: You cannot edit or delete an admin account from this panel");
    }

    if ($action === 'update') {
        $new_name = mysqli_real_escape_string($conn, trim($_POST['name']));
        $new_role = $_POST['role'];
        $new_status = $_POST['account_status'];

        // Validate inputs against ENUMs to prevent SQL errors
        $valid_roles = ['client', 'provider', 'admin'];
        $valid_statuses = ['active', 'suspended', 'banned'];
        
        if (in_array($new_role, $valid_roles) && in_array($new_status, $valid_statuses)) {
            $stmt = $conn->prepare("UPDATE users SET name = ?, role = ?, account_status = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("sssi", $new_name, $new_role, $new_status, $target_user_id);
                $stmt->execute();
                $stmt->close();
            }
        }
        
    } elseif ($action === 'delete') {
        
        // deletes the user and all their associated data
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $target_user_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Bounce back to the dashboard with the success message
    header("Location: ../adminDashboard.php?msg=success");
    exit();
}
?>