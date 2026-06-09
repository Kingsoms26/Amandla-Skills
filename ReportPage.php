<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('lang.php');
include('config.php');

// Must be logged in to report someone
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI']; 
    header("Location: login.php?error=login_required_to_report");
    exit();
}

if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
$lang = $_SESSION['lang'] ?? 'en';

// Pulling the provider Name from the Database

$reported_name = "Unknown User";
$reported_user_id = null;

if (isset($_GET['report_id']) && is_numeric($_GET['report_id'])) {
    $reported_user_id = $_GET['report_id'];
    
    // Query the database to get their display name
    $stmt = $conn->prepare("SELECT display_name FROM provider_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $reported_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $reported_name = $row['display_name'];
    }
    $stmt->close();
} else {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Report User | Amandla Skills</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">        <link rel="stylesheet" href="ReportPage.css">
    </head>
    <body>
        <!-- NavBar -->
         <header class="container-fluid py-1 px-1 border-bottom bg-white">
            <?php include('navBar.php'); ?>
         </header>

        <main class="container col-md-5">
            <div class=" shadow rounded border-2 px-4 py-4 mt-5 mb-4">
                <h4 class="fw-bold mb-4">Report: <span class="text-purple"><?php echo htmlspecialchars($reported_name); ?></span></h4>

                <!-- Form that allows users to submit a report -->
                <form action="process/processReport.php" method="POST" enctype="multipart/form-data">
    
                    <input type="hidden" name="reported_user_id" value="<?php echo htmlspecialchars($reported_user_id); ?>">

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Reason: </label>

                        <select id="reason" name="reason" class="form-select border-secondary-subtle" required onchange="toggleOtherReason()">
                            <option value="" disabled selected>Select an option</option>
                            <option value="no-show">No show</option>
                            <option value="unresponsive">Unresponsive</option>
                            <option value="overcharging">Overcharging</option>
                            <option value="scam">Suspected Scam</option>
                            <option value="general">General Issue</option>
                            <option value="Other">Other</option>
                        </select>

                        <div id="otherReason" class="mt-2 d-none">
                            <input type="text" name="other_reason" id="otherReasonInput" class="form-control border-secondary-subtle" placeholder="Please specify your reason...">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Details: </label>
                        <textarea class="form-control border-secondary-subtle" name="details" rows="4" placeholder="Describe the issue in detail..." required></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase text-muted">Upload Evidence (Optional): </label>
                        <input class="form-control border-secondary-subtle" type="file" name="evidence[]" accept="image/*,.pdf" multiple>
                        <div class="form-text small text-muted">Screenshots of chats or invoices. You can select multiple files.</div>
                    </div>
                    
                    <div class="d-flex gap-2 pt-2">
                        <button class="submit btn text-white fw-bold w-100" type="submit">Submit Report</button>
                        <a href="index.php" class="btn btn-outline-secondary fw-bold w-100">Cancel</a>
                    </div>
                </form>
            </div>
        </main>

        <!-- Footer -->
         <footer class="container-fluid py-1 px-1 border-bottom bg-white">
            <?php include('footer.php'); ?>
         </footer>
         
         <script>
            function toggleOtherReason() {
                var selectBox = document.getElementById("reason");
                var otherDiv = document.getElementById("otherReason");
                var otherInput = document.getElementById("otherReasonInput");

                if (selectBox.value === "Other") 
                {
                    otherDiv.classList.remove("d-none"); 
                    otherInput.required = true;  
                } else {
                    otherDiv.classList.add("d-none"); 
                    otherInput.required = false;  
                    otherInput.value = "";  
                }
            }
         </script>

    </body>
</html>