<?php
session_start();
include('../config.php');

// Make sure they are logged in as a provider
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'service_provider') {
    die("Unauthorized access.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $action = $_POST['action']; 
    $service_id = intval($_POST['service_id']);
    $user_id = $_SESSION['user_id'];

    // Ensure this provider actually owns the service they are trying to edit
    $verify_stmt = $conn->prepare("
        SELECT s.id 
        FROM services s 
        JOIN provider_profiles p ON s.provider_profile_id = p.id 
        WHERE s.id = ? AND p.user_id = ?
    ");
    $verify_stmt->bind_param("ii", $service_id, $user_id);
    $verify_stmt->execute();
    $ownership_check = $verify_stmt->get_result();
    
    if ($ownership_check->num_rows === 0) {
        die("Security Error: You do not have permission to modify this service.");
    }
    $verify_stmt->close();

    // UPDATE ACTION
    if ($action === 'update') {
        // Update text fields
        $title = mysqli_real_escape_string($conn, trim($_POST['title']));
        $category = mysqli_real_escape_string($conn, trim($_POST['category']));
        $description = mysqli_real_escape_string($conn, trim($_POST['description']));

        $update_stmt = $conn->prepare("UPDATE services SET title = ?, category = ?, description = ? WHERE id = ?");
        if ($update_stmt) {
            $update_stmt->bind_param("sssi", $title, $category, $description, $service_id);
            $update_stmt->execute();
            $update_stmt->close();
        }

        // Process Image Deletions (if the user checked any boxes)
        if (isset($_POST['delete_images']) && is_array($_POST['delete_images'])) {
            foreach ($_POST['delete_images'] as $img_path) {
                // Delete from database
                $del_img_stmt = $conn->prepare("DELETE FROM service_portfolio WHERE service_id = ? AND image_path = ?");
                $del_img_stmt->bind_param("is", $service_id, $img_path);
                $del_img_stmt->execute();
                $del_img_stmt->close();

                // Delete file from server storage
                if (file_exists($img_path)) {
                    unlink($img_path);
                }
            }
        }

        // Process New Image Uploads (if the user selected new files)
        if (isset($_FILES['new_images']) && !empty($_FILES['new_images']['name'][0])) {
            $upload_dir = '../uploads/portfolio/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_count = count($_FILES['new_images']['name']);
            for ($i = 0; $i < $file_count; $i++) {
                $tmp_name = $_FILES['new_images']['tmp_name'][$i];
                if ($tmp_name != "") {
                    $file_name = $_FILES['new_images']['name'][$i];
                    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                    
                    if (in_array($ext, $allowed)) {
                        $new_filename = 'port_' . $user_id . '_' . time() . '_' . $i . '.' . $ext;
                        $destination = $upload_dir . $new_filename;

                        if (move_uploaded_file($tmp_name, $destination)) {
                            // Insert new image into database
                            $img_stmt = $conn->prepare("INSERT INTO service_portfolio (service_id, image_path) VALUES (?, ?)");
                            $img_stmt->bind_param("is", $service_id, $destination);
                            $img_stmt->execute();
                            $img_stmt->close();
                        }
                    }
                }
            }
        }

    // DELETE ACTION
    } elseif ($action === 'delete') {
        // Find and delete all portfolio images from server
        $img_stmt = $conn->prepare("SELECT image_path FROM service_portfolio WHERE service_id = ?");
        $img_stmt->bind_param("i", $service_id);
        $img_stmt->execute();
        $res = $img_stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            if (file_exists($row['image_path'])) {
                unlink($row['image_path']);
            }
        }
        $img_stmt->close();

        // Delete the service image records from database
        $del_img_stmt = $conn->prepare("DELETE FROM service_portfolio WHERE service_id = ?");
        $del_img_stmt->bind_param("i", $service_id);
        $del_img_stmt->execute();
        $del_img_stmt->close();

        // Delete the service itself
        $del_stmt = $conn->prepare("DELETE FROM services WHERE id = ?");
        $del_stmt->bind_param("i", $service_id);
        $del_stmt->execute();
        $del_stmt->close();
    }

    header("Location: ../profile.php?id=" . $user_id . "&msg=success");
    exit();
}
?>