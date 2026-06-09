<?php 
    session_start();
    include('lang.php');

    if (!isset($_SESSION['lang'])) {
        $_SESSION['lang'] = 'en';
    }
    if (isset($_GET['lang'])) {
        $_SESSION['lang'] = $_GET['lang'];
    }
    $lang = $_SESSION['lang'];

    $error_message = "";
    if (isset($_GET['error']) && $_GET['error'] == 'login_required') 
    {
        $error_message = "Please log in to access that page.";
    } 
    elseif (isset($_GET['error']) && $_GET['error'] == 'invalid_credentials') 
    {
        $error_message = "Invalid email or password. Please try again.";
    }
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Amandla Skills</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">    <link rel="stylesheet" href="footer.css"> 
    <link rel="stylesheet" href="index.css">
</head>
<body class="bg-light d-flex flex-column min-vh-100">

    <header class="container-fluid py-1 px-1 border-bottom bg-white">
        <?php include('navBar.php'); ?>
    </header>

    <main class="container my-auto py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                
                <div class="card shadow-sm border-0 p-4 rounded-3">
                    <div class="text-center mb-4">
                        <h3 class="fw-bold">Welcome back to <span class="text-purple">Amandla Skills</span></h3>
                        <p class="text-muted small">Enter your credentials to access your account</p>
                    </div>

                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-warning py-2 small text-center" role="alert">
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>

                    <!-- login form -->
                    <form action="process/processLogin.php" method="POST">
                        <div class="mb-3">
                            <label class="fw-bold small text-uppercase text-secondary mb-1">Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="name@example.com" required>
                        </div>

                        <div class="mb-4">
                            <label class="fw-bold small text-uppercase text-secondary mb-1">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                            <div class="text-end mt-1">
                                <a href="forgotPassword.php" class="text-purple small text-decoration-none">Forgot password?</a>
                            </div>
                        </div>

                        <button class="btn btn-outline-secondary text-grey fw-bold w-100 py-2 mb-3" type="submit">Log In</button>
                        
                        <div class="text-center">
                            <small class="text-muted">Don't have an account? <a href="register.php" class="text-purple fw-bold text-decoration-none">Sign Up</a></small>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </main>

    <footer class="container-fluid py-1 px-1 border-bottom bg-white mt-auto">
        <?php include('footer.php'); ?>
    </footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>