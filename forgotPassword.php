<?php
session_start();
include('config.php'); // Ensure this points to your database connection

$msg = "";
$msg_type = "";

// Process the form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    //  Basic validation
    if (empty($email) || empty($new_password) || empty($confirm_password)) {
        $msg = "Please fill in all fields.";
        $msg_type = "danger";
    } elseif ($new_password !== $confirm_password) {
        $msg = "Passwords do not match. Please try again.";
        $msg_type = "danger";
    } else {
        // Check if the user exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_stmt->bind_param("si", $hashed_password, $user['id']);
            
            if ($update_stmt->execute()) {
                $msg = "Password successfully reset! You can now log in.";
                $msg_type = "success";
            } else {
                $msg = "Something went wrong. Please try again.";
                $msg_type = "danger";
            }
            $update_stmt->close();
        } else {
            $msg = "No account found with that email address.";
            $msg_type = "warning";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Amandla Skills</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .password-toggle {
            cursor: pointer;
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center vh-100">

    <div class="container" style="max-width: 500px;">
        
        <div class="alert alert-warning text-center shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Demo Mode:</strong> This direct reset feature is for academic demonstration purposes only.
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-5 bg-white rounded">
                <h3 class="text-center mb-4 fw-bold text-dark">Reset Password</h3>

                <?php if (!empty($msg)): ?>
                    <div class="alert alert-<?php echo $msg_type; ?> text-center">
                        <?php echo $msg; ?>
                    </div>
                <?php endif; ?>

                <form action="forgotPassword.php" method="POST">
                    
                    <div class="mb-3">
                        <label for="email" class="form-label text-muted fw-bold small">ACCOUNT EMAIL</label>
                        <input type="email" class="form-control bg-light" id="email" name="email" required placeholder="Enter your email address">
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label text-muted fw-bold small">NEW PASSWORD</label>
                        <div class="input-group">
                            <input type="password" class="form-control bg-light" id="new_password" name="new_password" required placeholder="Enter new password">
                            <span class="input-group-text bg-white password-toggle" onclick="togglePassword('new_password', 'toggleIcon1')">
                                <i class="bi bi-eye-slash text-muted" id="toggleIcon1"></i>
                            </span>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label text-muted fw-bold small">CONFIRM PASSWORD</label>
                        <div class="input-group">
                            <input type="password" class="form-control bg-light" id="confirm_password" name="confirm_password" required placeholder="Confirm new password">
                            <span class="input-group-text bg-white password-toggle" onclick="togglePassword('confirm_password', 'toggleIcon2')">
                                <i class="bi bi-eye-slash text-muted" id="toggleIcon2"></i>
                            </span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2" style="background-color: #6f42c1; border-color: #6f42c1;">
                        Update Password
                    </button>
                    
                    <div class="text-center mt-3">
                        <a href="login.php" class="text-decoration-none text-muted small">Return to Login</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(iconId);

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('bi-eye-slash');
                toggleIcon.classList.add('bi-eye');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('bi-eye');
                toggleIcon.classList.add('bi-eye-slash');
            }
        }
    </script>
</body>
</html>