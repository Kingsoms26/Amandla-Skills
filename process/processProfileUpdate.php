<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include('../config.php');
include('../dbHelper.php'); 

// Security check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['user_role'] ?? 'client';
    
    // determine which dashboard to send them back to
    $redirect_page = ($user_role === 'service_provider') ? '../providerDashboard.php' : '../clientDashboard.php';

    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
        $upload_dir = '../uploads/profiles/'; 
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true); 
        }
        
        $file_name = $_FILES['profile_pic']['name'];
        $file_tmp = $_FILES['profile_pic']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($file_ext, $allowed)) {
            $new_file_name = 'user_' . $user_id . '_' . time() . '.' . $file_ext;
            $destination = $upload_dir . $new_file_name;

            $final_destination = compressAndUpload($file_tmp, $destination, 400);

            if ($final_destination) {
                
                $pic_stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
                if ($pic_stmt) {
                    $pic_stmt->bind_param("si", $final_destination, $user_id);
                    $pic_stmt->execute();
                    $pic_stmt->close();
                    
                    header("Location: " . $redirect_page . "?msg=photo_updated");
                    exit();
                } else {
                    die("Database error: " . $conn->error);
                }
            } else {
                die("Error: The image could not be compressed or saved.");
            }
        } else {
            die("Error: Invalid file type. Only JPG, PNG, and WEBP are allowed.");
        }
    } else {
        $error_code = $_FILES['profile_pic']['error'] ?? 'Unknown';
        die("Error uploading file. Error Code: " . $error_code);
    }

    header("Location: " . $redirect_page);
    exit();
}
?>