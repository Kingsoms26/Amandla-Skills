<?php
session_start();
include('../config.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $password_hashed = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // check if the email is already registered
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) { 
        header("Location: ../register.php?error=email_taken");
        exit();
    }
    $check_stmt->close();

    // Insert into the main USERS table
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $password_hashed, $role);
    
    if ($stmt->execute()) {
        $new_user_id = $conn->insert_id; 
        
        // If they are a provider, also create a profile for them within the provider table
        if ($role === 'provider') {
            $phone = isset($_POST['phone']) ? mysqli_real_escape_string($conn, $_POST['phone']) : '';
            $location = isset($_POST['service_location']) ? mysqli_real_escape_string($conn, $_POST['service_location']) : '';

            // Update the SQL to include the new columns
            $prof_stmt = $conn->prepare("INSERT INTO provider_profiles (user_id, display_name, phone_number, service_location) VALUES (?, ?, ?, ?)");
            
            $prof_stmt->bind_param("isss", $new_user_id, $name, $phone, $location);
            $prof_stmt->execute();
            
            $_SESSION['provider_profile_id'] = $conn->insert_id; 
            $prof_stmt->close();
        }

        // log them in by setting session variables
        $_SESSION['user_id'] = $new_user_id;
        $_SESSION['name'] = $name;
        if ($role === 'provider') {
            $_SESSION['user_role'] = 'service_provider'; 
        } else {
            $_SESSION['user_role'] = $role; // 'client' or 'admin'
        }
        
        // send them back to the homepage with a welcome message
        header("Location: ../index.php?msg=welcome");
        exit();
        
    } else {
        die("Database Error: " . $stmt->error);
    }
    $stmt->close();
}
?>