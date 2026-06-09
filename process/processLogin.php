<?php
session_start();
include('../config.php'); 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    // search for the user by email
    $stmt = $conn->prepare("SELECT id, name, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        
        // verify the password 
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['name'] = $row['name'];
            if ($row['role'] === 'provider') {
                $_SESSION['user_role'] = 'service_provider'; 
            } else {
                $_SESSION['user_role'] = $row['role']; 
            }

            // if they are a provider, fetch their Profile ID so they can add services later
            if ($row['role'] === 'provider') {
                $prof_stmt = $conn->prepare("SELECT id FROM provider_profiles WHERE user_id = ?");
                $prof_stmt->bind_param("i", $row['id']);
                $prof_stmt->execute();
                $prof_res = $prof_stmt->get_result();
                
                if ($prof_row = $prof_res->fetch_assoc()) {
                    $_SESSION['provider_profile_id'] = $prof_row['id'];
                }
                $prof_stmt->close();
            }

            if (isset($_SESSION['redirect_url'])) {
                $url = $_SESSION['redirect_url'];
                unset($_SESSION['redirect_url']);
                header("Location: " . $url);
            } elseif ($row['role'] === 'admin') {
                header("Location: ../adminDashboard.php");
            } else {
                header("Location: ../index.php");
            }
            exit();

        } else {
            // wrong password
            header("Location: ../login.php?error=invalid_credentials");
            exit();
        }
    } else {
        // email not found
        header("Location: ../login.php?error=invalid_credentials");
        exit();
    }
    $stmt->close();
}
?>