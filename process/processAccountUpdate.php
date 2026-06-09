<?php
session_start();
include('../config.php');

// authentication Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id   = $_SESSION['user_id'];
    $email     = mysqli_real_escape_string($conn, trim($_POST['email']));
    $full_name = mysqli_real_escape_string($conn, trim($_POST['full_name'] ?? ''));
    $new_pass  = $_POST['new_password'];
    $user_role = $_SESSION['user_role'];

    $redirect = ($user_role === 'service_provider') ? '../providerDashboard.php' :
                (($user_role === 'admin') ? '../adminDashboard.php' : '../clientDashboard.php');

    // update Core User Data
    if (!empty($full_name)) {
        if (!empty($email)) {
            $update_user = $conn->prepare("UPDATE users SET email = ?, name = ? WHERE id = ?");
            $update_user->bind_param("ssi", $email, $full_name, $user_id);
        } else {
            // admin path: name only, email not submitted
            $update_user = $conn->prepare("UPDATE users SET name = ? WHERE id = ?");
            $update_user->bind_param("si", $full_name, $user_id);
        }
        if ($update_user->execute()) {
            if (!empty($email)) $_SESSION['email'] = $email;
            $_SESSION['name'] = $full_name;
        }
        $update_user->close();
    }

    // update service provider specific data
    if ($user_role === 'service_provider') {
        $phone = isset($_POST['phone']) ? mysqli_real_escape_string($conn, trim($_POST['phone'])) : '';
        $service_location = isset($_POST['service_location']) ? mysqli_real_escape_string($conn, trim($_POST['service_location'])) : '';
        
        $update_provider = $conn->prepare("UPDATE provider_profiles SET phone_number = ?, service_location = ? WHERE user_id = ?");
        $update_provider->bind_param("ssi", $phone, $service_location, $user_id);
        $update_provider->execute();
        $update_provider->close();
    }

    // secure password update (If provided)
    if (!empty($new_pass)) {
        if (strlen($new_pass) < 6) {
            header("Location: $redirect?msg=err_pass_short");
            exit();
        }
        $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
        $update_pass = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update_pass->bind_param("si", $hashed_pass, $user_id);
        $update_pass->execute();
        $update_pass->close();
    }

    header("Location: $redirect?msg=account_updated");
    exit();
}
?>