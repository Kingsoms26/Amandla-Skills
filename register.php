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

// Check if they got redirected back here because of an error
$error_message = "";
if (isset($_GET['error']) && $_GET['error'] == 'email_taken') {
    $error_message = "That email is already registered. Please log in.";
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Sign Up | Amandla Skills</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@26.9.2/build/css/intlTelInput.css">
        <link rel="stylesheet" href="footer.css">
        <link rel="stylesheet" href="index.css">
        <link rel="stylesheet" href="shared.css">
    </head>
    <body class="bg-light d-flex flex-column min-vh-100">

        <header class="container-fluid py-1 px-1 border-bottom bg-white">
            <?php include('navBar.php'); ?>
        </header>

        <main class="container my-auto py-5">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    
                    <div class="card shadow-sm border-0 p-4 rounded-3">
                        <div class="text-center mb-4">
                            <h3 class="fw-bold">Join <span class="text-purple">Amandla Skills</span></h3>
                            <p class="text-muted small">Create an account to connect with your community.</p>
                        </div>

                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger py-2 small text-center" role="alert">
                                <?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Form to Register to new users -->
                        <form action="process/processRegister.php" method="POST">
                            <div class="mb-3">
                                <label class="fw-bold small text-uppercase text-secondary mb-1">Full Name / Business Name</label>
                                <input type="text" name="name" class="form-control" placeholder="Full Name" required>
                            </div>

                            <div class="mb-3">
                                <label class="fw-bold small text-uppercase text-secondary mb-1">Email Address</label>
                                <input type="email" name="email" class="form-control" placeholder="name@example.com" required>
                            </div>

                            <div class="mb-3">
                                <label class="fw-bold small text-uppercase text-secondary mb-1">Password</label>
                                <input type="password" name="password" class="form-control" minlength="6" placeholder="Password" required>
                            </div>

                            <div class="mb-4">
                                <label class="fw-bold small text-uppercase text-secondary mb-2">Select: </label>
                                <select name="role" id="roleSelector" class="form-select border-secondary-subtle" required onchange="toggleProviderFields()">
                                    <option value="client" selected>Hire Service Providers (Client)</option>
                                    <option value="provider">Offer My Services (Provider)</option>
                                </select>
                            </div>

                            <!-- section to add service provider details -->
                            <div id="providerFields" class="d-none  p-2 mb-3">
                                <h6 class="fw-bold med text-uppercase text-secondary mb-2">Provider Details</h6>
                                
                                <div class="mb-3">
                                    <label class="fw-bold small text-uppercase text-secondary mb-1">Phone Number</label>
                                    <input type="tel" id="phoneInput" name="phone" class="form-control" placeholder="082 123 4567">
                                </div>

                                <div class="mb-2">
                                    <label class="fw-bold small text-uppercase text-secondary mb-1">Primary Service Area</label>
                                    <input type="text" name="service_location" id="locationInput" class="form-control" placeholder="e.g. Midrand, Gauteng">
                                </div>
                            </div>

                            <button class="btn btn-secondary text-white fw-bold w-100 py-2 mb-3" type="submit">Create Account</button>
                            
                            <div class="text-center">
                                <small class="text-muted">Already have an account? <a href="login.php" class="fw-bold text-decoration-none">Log in</a></small>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>

        <footer class="container-fluid py-1 px-1 border-bottom bg-white mt-auto">
            <?php include('footer.php'); ?>
        </footer>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
        
        <script>
            function toggleProviderFields() {
                const role = document.getElementById('roleSelector').value;
                const providerFields = document.getElementById('providerFields');
                const phoneInput = document.getElementById('phoneInput');
                const locationInput = document.getElementById('locationInput');

                if (role === 'provider') {
                    // Show the fields and make them mandatory
                    providerFields.classList.remove('d-none');
                    phoneInput.required = true;
                    locationInput.required = true;
                } else {
                    providerFields.classList.add('d-none');
                    phoneInput.required = false;
                    locationInput.required = false;
                }
            }

            document.addEventListener('DOMContentLoaded', toggleProviderFields);
        </script>
        <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@26.9.2/build/js/intlTelInput.min.js"></script>
        <script>
            const input = document.querySelector("#phoneInput");
            window.intlTelInput(input, 
            {
                initialCountry: "za",
                loadUtils: () => import("https://cdn.jsdelivr.net/npm/intl-tel-input@26.9.2/build/js/utils.js"),
            });
        </script>
    </body>
</html>