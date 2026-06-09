<?php
session_start();
include('../config.php');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'service_provider') 
{
    die("Unauthorized access.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") 
{  
    $provider_profile_id = $_SESSION['provider_profile_id'];
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    
    // grab the pricing data
    $price_type = mysqli_real_escape_string($conn, $_POST['price_type']);
    $price_min = $_POST['price_min']; 
    
    // only grab the max price if it is a range and the field is not empty
    $price_max = ($price_type === 'range' && !empty($_POST['price_max'])) ? $_POST['price_max'] : NULL;

    $stmt = $conn->prepare("INSERT INTO services (provider_profile_id, title, category, description, price_type, price_min, price_max) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssdd", $provider_profile_id, $title, $category, $description, $price_type, $price_min, $price_max);

    if ($stmt->execute()) {
        
        $new_service_id = $conn->insert_id; 

        // handle portfolio uploads if there are any
        if (isset($_FILES['portfolio_images']) && !empty($_FILES['portfolio_images']['name'][0])) {
            
            $upload_dir = '../uploads/portfolio/'; 
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true); 
            }
            
            $img_stmt = $conn->prepare("INSERT INTO service_portfolio (service_id, image_path) VALUES (?, ?)");

            $file_count = count($_FILES['portfolio_images']['name']);
            
            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['portfolio_images']['error'][$i] === 0) {
                    
                    $file_tmp = $_FILES['portfolio_images']['tmp_name'][$i];
                    $file_name = $_FILES['portfolio_images']['name'][$i];
                    
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
                    
                    if (in_array($file_ext, $allowed_extensions)) {
                        $new_file_name = uniqid('port_') . '.' . $file_ext;
                        $destination = $upload_dir . $new_file_name;

                        // move the file and save to database
                        if (move_uploaded_file($file_tmp, $destination)) {
                            $img_stmt->bind_param("is", $new_service_id, $destination);
                            $img_stmt->execute();
                        }
                    }
                }
            }
            $img_stmt->close();
        }

        header("Location: ../index.php?msg=service_added");
        exit();
    } else {
        die("Error adding service: " . $stmt->error);
    }

    $stmt->close();
}
?>