<?php
session_start();
include('lang.php');
include('config.php');
include('dbHelper.php');

// Ensure user is logged in and is a service provider
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'service_provider') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// 1. Fetch current profile data
$profile_stmt = $conn->prepare("SELECT id, display_name, phone_number, verification_tier, verification_status FROM provider_profiles WHERE user_id = ?");
$profile_stmt->bind_param("i", $user_id);
$profile_stmt->execute();
$provider_profile = $profile_stmt->get_result()->fetch_assoc();
$profile_stmt->close();

// 2. Fetch Job Count (Completed Jobs)
$job_stmt = $conn->prepare("SELECT COUNT(*) as job_count FROM bookings WHERE provider_id = ? AND status = 'completed'");
$job_stmt->bind_param("i", $user_id);
$job_stmt->execute();
$job_data = $job_stmt->get_result()->fetch_assoc();
$completed_jobs = $job_data['job_count'] ?? 0;
$job_stmt->close();

// 3. Fetch Average Rating
$rating_stmt = $conn->prepare("SELECT AVG(rating) as avg_rating FROM reviews WHERE provider_id = ?");
$rating_stmt->bind_param("i", $user_id);
$rating_stmt->execute();
$rating_data = $rating_stmt->get_result()->fetch_assoc();
$avg_rating = round($rating_data['avg_rating'] ?? 0, 1);
$rating_stmt->close();

// 4. Check for 30-day rejection rule
$can_apply = true;
$rejection_reason = '';
if ($provider_profile['verification_status'] === 'rejected') {
    $rej_stmt = $conn->prepare("SELECT reviewed_at, admin_notes FROM verification_requests WHERE provider_id = ? AND status = 'rejected' ORDER BY reviewed_at DESC LIMIT 1");
    $rej_stmt->bind_param("i", $user_id);
    $rej_stmt->execute();
    $rej_data = $rej_stmt->get_result()->fetch_assoc();
    $rej_stmt->close();

    if ($rej_data && !empty($rej_data['reviewed_at'])) {
        $rejected_date = strtotime($rej_data['reviewed_at']);
        $days_since = floor((time() - $rejected_date) / (60 * 60 * 24));
        if ($days_since < 30) {
            $can_apply = false;
            $days_left = 30 - $days_since;
            $rejection_reason = $rej_data['admin_notes'];
        }
    }
}

// 5. Handle Form Submission
// 5. Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && $can_apply && !in_array($provider_profile['verification_status'], ['pending', 'interview_scheduled'])) {
    $full_name = htmlspecialchars($_POST['full_name'] ?? '');
    $email = htmlspecialchars($_POST['email'] ?? '');
    $phone = htmlspecialchars($_POST['phone'] ?? '');
    $whatsapp = htmlspecialchars($_POST['whatsapp'] ?? '');
    $target_tier = htmlspecialchars($_POST['target_tier'] ?? 'verified');
    $message = htmlspecialchars($_POST['message'] ?? '');
    
    $cipc_number = NULL;
    $cipc_document_path = NULL;
    $id_document_path = NULL;
    
    $upload_dir = 'uploads/verifications/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    // 1. Handle Mandatory ID Upload
    if (!isset($_FILES['id_document']) || $_FILES['id_document']['error'] !== UPLOAD_ERR_OK) {
        $error_msg = "Please upload a valid ID or Passport document.";
    } else {
        $id_ext = strtolower(pathinfo($_FILES['id_document']['name'], PATHINFO_EXTENSION));
        $id_document_path = $upload_dir . 'id_' . $user_id . '_' . time() . '.' . $id_ext;
        move_uploaded_file($_FILES['id_document']['tmp_name'], $id_document_path);
    }

    // 2. Handle CIPC Upload (if Top Pro)
    if (empty($error_msg) && $target_tier === 'top_pro') {
        $cipc_number = htmlspecialchars($_POST['cipc_number'] ?? '');
        if (empty($cipc_number) || !isset($_FILES['cipc_document']) || $_FILES['cipc_document']['error'] !== UPLOAD_ERR_OK) {
            $error_msg = "Top Pro requires both a CIPC number and the COR 14.3 PDF document.";
        } else {
            $cipc_document_path = $upload_dir . 'cipc_' . $user_id . '_' . time() . '.pdf';
            move_uploaded_file($_FILES['cipc_document']['tmp_name'], $cipc_document_path);
        }
    }

    // 3. Single Execution Block
    if (empty($error_msg)) {
        $ins_stmt = $conn->prepare("INSERT INTO verification_requests (provider_id, full_name, email, phone, whatsapp, cipc_number, cipc_document_path, id_document_path, target_tier, message, status, submitted_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
        $ins_stmt->bind_param("isssssssss", $user_id, $full_name, $email, $phone, $whatsapp, $cipc_number, $cipc_document_path, $id_document_path, $target_tier, $message);
        
        if ($ins_stmt->execute()) {
            $upd_stmt = $conn->prepare("UPDATE provider_profiles SET verification_status = 'pending' WHERE user_id = ?");
            $upd_stmt->bind_param("i", $user_id);
            $upd_stmt->execute();
            $upd_stmt->close();
            
            $provider_profile['verification_status'] = 'pending';
            $success_msg = "Your verification application has been submitted!";
        } else {
            $error_msg = "Database error: " . $conn->error;
        }
        $ins_stmt->close();
    }
}

// Determine eligibility highlights
$eligible_tier = 'none';
if ($avg_rating >= 4.0) {
    if ($completed_jobs >= 30) $eligible_tier = 'top_pro';
    elseif ($completed_jobs >= 20) $eligible_tier = 'verified_pro';
    elseif ($completed_jobs >= 5) $eligible_tier = 'verified';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Get Verified | Amandla Skills</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">    <link rel="stylesheet" href="index.css">
<link rel="stylesheet" href="shared.css">
<style>
        
    </style>
</head>
<body class="bg-light d-flex flex-column min-vh-100">
    
    <header class="container-fluid py-1 px-1 border-bottom bg-white">
        <?php include('navBar.php'); ?>
    </header>

    <main class="container my-5 flex-grow-1" style="max-width: 900px;">
        <div class="mb-4">
            <a href="providerDashboard.php" class="text-decoration-none text-muted fw-bold small">← Back to Dashboard</a>
        </div>

        <div class="card shadow-sm border-0 rounded-4 mb-4 overflow-hidden">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-0">
                    <div class="rounded-circle text-purple d-inline-flex align-items-center justify-content-center flex-shrink-0 mb-3" style="width: 60px; height: 60px; font-size: 1.8rem; background-color: rgba(124, 58, 237, 0.1);">🛡️</div>
                    <h2 class="fw-bold text-dark mb-2">Provider Verification Application</h2>
                    <p class="text-muted mb-0">Stand out to clients by earning a trust badge on your profile.</p>
                </div>
            </div>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success border-0 rounded-4 shadow-sm mb-4 fw-bold text-center py-3">
                🎉 <?php echo $success_msg; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger border-0 rounded-4 shadow-sm mb-4 fw-bold text-center py-3">
                ⚠️ <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <?php if (in_array($provider_profile['verification_status'], ['pending', 'interview_scheduled', 'approved'])): ?>
            <div class="card shadow-sm border-0 rounded-4 p-5 text-center">
                <?php if ($provider_profile['verification_status'] === 'approved'): ?>
                    <div class="display-1 mb-3">🏅</div>
                    <h4 class="fw-bold text-dark">You are already verified!</h4>
                    <p class="text-muted mb-0">Your account holds the <strong><?php echo str_replace('_', ' ', ucwords($provider_profile['verification_tier'])); ?></strong> badge.</p>
                <?php elseif ($provider_profile['verification_status'] === 'interview_scheduled'): ?>
                    <div class="display-1 mb-3">📅</div>
                    <h4 class="fw-bold text-dark">Interview Scheduled</h4>
                    <p class="text-muted mb-0">Please check your email for the meeting link and details.</p>
                <?php else: ?>
                    <div class="display-1 mb-3">⏳</div>
                    <h4 class="fw-bold text-dark">Application Under Review</h4>
                    <p class="text-muted mb-0">Your application is currently being reviewed by the Amandla Skills support team.</p>
                <?php endif; ?>
            </div>

        <?php elseif (!$can_apply): ?>
            <div class="card shadow-sm border-0 rounded-4 p-5 text-center" style="border-top: 4px solid #dc3545 !important;">
                <div class="display-1 mb-3">🛑</div>
                <h4 class="fw-bold text-danger">Application Unsuccessful</h4>
                <p class="text-muted mb-4">Unfortunately, your previous application was not approved.</p>
                <div class="bg-light p-3 rounded-4 text-start mb-4 border border-light">
                    <span class="d-block small text-muted text-uppercase fw-bold mb-1">Admin Notes:</span>
                    <span class="fw-medium text-dark">"<?php echo htmlspecialchars($rejection_reason); ?>"</span>
                </div>
                <p class="fw-bold text-dark mb-0">You can reapply in <span style="color: #7c3aed;"><?php echo $days_left; ?> days</span>.</p>
            </div>

        <?php else: ?>
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div class="mb-3 mb-md-0">
                        <h6 class="fw-bold mb-1 text-dark">Your Current Stats</h6>
                        <small class="text-muted">System minimums require a 4.0 average rating to apply.</small>
                    </div>
                    <div class="d-flex gap-4">
                        <div class="text-center px-3 border-end">
                            <div class="text-muted small text-uppercase fw-bold mb-1" style="font-size: 0.7rem;">Completed Jobs</div>
                            <div class="fs-4 fw-bold <?php echo ($completed_jobs >= 5) ? 'text-success' : 'text-danger'; ?>"><?php echo $completed_jobs; ?></div>
                        </div>
                        <div class="text-center px-3">
                            <div class="text-muted small text-uppercase fw-bold mb-1" style="font-size: 0.7rem;">Avg Rating</div>
                            <div class="fs-4 fw-bold <?php echo ($avg_rating >= 4.0) ? 'text-success' : 'text-danger'; ?>">⭐ <?php echo number_format($avg_rating, 1); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <form action="" method="POST" enctype="multipart/form-data" id="verificationForm">
                
                <h5 class="fw-bold mb-3 ms-1 text-dark">1. Select Target Tier</h5>
                <div class="row g-3 mb-4">
                    <!-- Tier 1 -->
                    <div class="col-md-4">
                        <label class="w-100 h-100">
                            <input type="radio" name="target_tier" value="verified" class="tier-radio" <?php echo ($eligible_tier === 'verified' || $eligible_tier === 'none') ? 'checked' : ''; ?>>
                            <div class="card h-100 tier-card shadow-sm rounded-4 p-4 <?php echo ($eligible_tier === 'verified') ? 'eligible' : ''; ?>">
                                <?php if ($eligible_tier === 'verified'): ?><span class="eligible-badge">You Qualify</span><?php endif; ?>
                                <h6 class="fw-bold mb-1 text-dark">Verified</h6>
                                <p class="small text-muted mb-3" style="font-size: 0.75rem;">The standard trust mark.</p>
                                <ul class="feature-list mb-0">
                                    <li>5+ completed jobs</li>
                                    <li>4.0+ average rating</li>
                                    <li>Support interview</li>
                                    <li>Valid ID on file</li>
                                </ul>
                            </div>
                        </label>
                    </div>

                    <!-- Tier 2 -->
                    <div class="col-md-4">
                        <label class="w-100 h-100">
                            <input type="radio" name="target_tier" value="verified_pro" class="tier-radio" <?php echo ($eligible_tier === 'verified_pro') ? 'checked' : ''; ?>>
                            <div class="card h-100 tier-card shadow-sm rounded-4 p-4 <?php echo ($eligible_tier === 'verified_pro') ? 'eligible' : ''; ?>">
                                <?php if ($eligible_tier === 'verified_pro'): ?><span class="eligible-badge">You Qualify</span><?php endif; ?>
                                <h6 class="fw-bold mb-1" style="color: #7c3aed;">Verified Pro</h6>
                                <p class="small text-muted mb-3" style="font-size: 0.75rem;">For experienced providers.</p>
                                <ul class="feature-list mb-0">
                                    <li>20+ completed jobs</li>
                                    <li>4.0+ average rating</li>
                                    <li>Support interview</li>
                                    <li>Valid ID on file</li>
                                </ul>
                            </div>
                        </label>
                    </div>

                    <!-- Tier 3 -->
                    <div class="col-md-4">
                        <label class="w-100 h-100">
                            <input type="radio" name="target_tier" value="top_pro" class="tier-radio" <?php echo ($eligible_tier === 'top_pro') ? 'checked' : ''; ?>>
                            <div class="card h-100 tier-card shadow-sm rounded-4 p-4 <?php echo ($eligible_tier === 'top_pro') ? 'eligible' : ''; ?>">
                                <?php if ($eligible_tier === 'top_pro'): ?><span class="eligible-badge">You Qualify</span><?php endif; ?>
                                <h6 class="fw-bold mb-1" style="color: #d97706;">Top Pro</h6>
                                <p class="small text-muted mb-3" style="font-size: 0.75rem;">The elite business tier.</p>
                                <ul class="feature-list mb-0">
                                    <li>30+ completed jobs</li>
                                    <li>4.0+ average rating</li>
                                    <li>Support interview</li>
                                    <li>Registered CIPC Entity</li>
                                </ul>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="card shadow-sm border-0 rounded-4 mb-4">
                    <div class="card-body p-4 p-md-5">
                        <h5 class="fw-bold mb-4 text-dark">2. Applicant Details</h5>
                        
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted text-uppercase" style="font-size: 0.75rem;">Full Legal Name</label>
                                <input type="text" name="full_name" class="form-control border-secondary-subtle bg-light" value="<?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted text-uppercase" style="font-size: 0.75rem;">Email Address</label>
                                <input type="email" name="email" class="form-control border-secondary-subtle bg-light" value="<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted text-uppercase" style="font-size: 0.75rem;">Primary Phone Number</label>
                                <input type="text" name="phone" class="form-control border-secondary-subtle" value="<?php echo htmlspecialchars($provider_profile['phone_number'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted text-uppercase" style="font-size: 0.75rem;">WhatsApp Number</label>
                                <input type="text" name="whatsapp" class="form-control border-secondary-subtle" required placeholder="For interview scheduling">
                            </div>

                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted text-uppercase" style="font-size: 0.75rem;">Upload Identity Document (ID/Passport)</label>
                                <input type="file" name="id_document" class="form-control border-secondary-subtle bg-light" accept="application/pdf,image/jpeg,image/png" required>
                                <small class="text-muted d-block mt-1" style="font-size: 0.7rem;">Required for all tiers.</small>
                            </div>

                            <!-- CIPC Fields -->
                            <div class="col-12" id="cipcContainer" style="display: none;">
                                <div class="bg-warning bg-opacity-10 p-4 rounded-4 border border-warning border-opacity-25">
                                    <div class="d-flex align-items-center gap-2 mb-3">
                                        <span class="fs-5">🏢</span>
                                        <h6 class="fw-bold text-dark mb-0">Top Pro Requirements</h6>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-dark text-uppercase mb-1" style="font-size: 0.7rem;">CIPC Number</label>
                                            <input type="text" name="cipc_number" id="cipcInput" class="form-control border-warning bg-white" placeholder="e.g. 2023/123456/07">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-dark text-uppercase mb-1" style="font-size: 0.7rem;">Upload COR 14.3</label>
                                            <input type="file" name="cipc_document" id="cipcDocInput" class="form-control border-warning bg-white" accept="application/pdf">
                                            <small class="text-muted d-block mt-1" style="font-size: 0.7rem;">Valid PDF document only.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted text-uppercase" style="font-size: 0.75rem;">Message to Support (Optional)</label>
                                <textarea name="message" class="form-control border-secondary-subtle" rows="3" placeholder="Any specific times you prefer for the interview..."></textarea>
                            </div>
                        </div>

                        <div class="mt-5 text-end border-top pt-4">
                            <?php if ($avg_rating < 4.0 || $completed_jobs < 5): ?>
                                <div class="text-danger small fw-bold mb-2">You do not meet the minimum requirements to apply yet.</div>
                            <?php endif; ?>
                            <button type="submit" class="btn text-white px-5 py-2 rounded-pill shadow-sm fw-bold" style="background-color: #7c3aed;" <?php echo ($avg_rating < 4.0 || $completed_jobs < 5) ? 'disabled' : ''; ?>>
                                Submit Application
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>

    </main>

    <footer class="container-fluid py-1 px-1 border-bottom bg-white mt-auto">
        <?php include('footer.php'); ?>
    </footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const radios = document.querySelectorAll('.tier-radio');
            const cards = document.querySelectorAll('.tier-card');
            const cipcContainer = document.getElementById('cipcContainer');
            const cipcInput = document.getElementById('cipcInput');
            const cipcDocInput = document.getElementById('cipcDocInput');

            function updateUI() {
                let selectedValue = '';
                radios.forEach((radio, index) => {
                    if (radio.checked) {
                        cards[index].classList.add('selected');
                        selectedValue = radio.value;
                    } else {
                        cards[index].classList.remove('selected');
                    }
                });

                if (selectedValue === 'top_pro') {
                    cipcContainer.style.display = 'block';
                    cipcInput.required = true;
                    cipcDocInput.required = true;
                } else {
                    cipcContainer.style.display = 'none';
                    cipcInput.required = false;
                    cipcDocInput.required = false;
                    cipcInput.value = ''; 
                    cipcDocInput.value = ''; 
                }
            }

            cards.forEach((card, index) => {
                card.addEventListener('click', () => {
                    radios[index].checked = true;
                    updateUI();
                });
            });

            updateUI();
        });
    </script>
</body>
</html>