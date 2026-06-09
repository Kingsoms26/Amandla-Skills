<?php
session_start();
include('../config.php');

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    if (empty($_POST['reported_user_id']) || empty($_POST['reason'])) 
    {
        die("Error: Form data is missing. Please go back and try again.");
    }
    $reporter_id = $_SESSION['user_id'];
    $reported_id = $_POST['reported_user_id'];
    if ($_POST['reason'] === 'Other') 
    {
        $custom_reason = $_POST['other_reason'];
        $reason = mysqli_real_escape_string($conn, "Other: " . $custom_reason);
    } else 
    {
        $reason = mysqli_real_escape_string($conn, $_POST['reason']);
    }
    $complaint_text = mysqli_real_escape_string($conn, $_POST['details']);
    $resolution_status = 'pending';

    $stmt = $conn->prepare("INSERT INTO reports (reporter_id, reported_id, reason, complaint_text, resolution_status) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $reporter_id, $reported_id, $reason, $complaint_text, $resolution_status);

    if ($stmt->execute()) {
        
        // fetch the ID of the newly created report for linking evidence
        $new_report_id = $conn->insert_id; 

        //Handles mutiple file uploads for evidence, if they exist
        if (isset($_FILES['evidence']) && !empty($_FILES['evidence']['name'][0])) {
            
            $upload_dir = 'uploads/reports/'; 
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true); 
            }
            
            // Prepare the SQL for inserting the evidence paths
            $ev_stmt = $conn->prepare("INSERT INTO report_evidence (report_id, file_path) VALUES (?, ?)");

            $file_count = count($_FILES['evidence']['name']);
            
            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['evidence']['error'][$i] === 0) {
                    
                    $file_tmp = $_FILES['evidence']['tmp_name'][$i];
                    $file_name = $_FILES['evidence']['name'][$i];
                    
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
                    
                    if (in_array($file_ext, $allowed_extensions)) {
                        $new_file_name = uniqid('evidence_') . '.' . $file_ext;
                        $destination = $upload_dir . $new_file_name;

                        // Move the file and save to database
                        if (move_uploaded_file($file_tmp, $destination)) {
                            $ev_stmt->bind_param("is", $new_report_id, $destination);
                            $ev_stmt->execute();
                        }
                    }
                }
            }
            $ev_stmt->close();
        }

        // Redirect back with a success message
        header("Location: ../index.php?msg=report_submitted");
        exit();
    } else {
        die("Database Error: " . $stmt->error);
    }
    
    $stmt->close();
} else {
    header("Location: ../index.php");
    exit();
}
?>