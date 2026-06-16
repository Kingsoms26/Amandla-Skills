<?php
session_start();
include('lang.php');
include('config.php');
include('dbHelper.php');
include_once('verificationBadge.php');
include('checkAccess.php');

if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
$lang = $_SESSION['lang'];

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'service_provider') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// fetch data
$provider_profile = getProviderProfileData($conn, $user_id);
$all_bookings = getProviderBookings($conn, $user_id);

$notifications = [];
try { $notifications = getUserNotifications($conn, $user_id); } catch (Exception $e) { }

$reviews = [];
$total_reviews = 0;
$avg_rating = 0.0;
$sum_rating = 0;

$rev_stmt = $conn->prepare("
    SELECT r.rating, r.comment_text, r.created_at, u.name AS client_name, u.profile_pic AS client_pic
    FROM reviews r
    JOIN users u ON r.client_id = u.id
    WHERE r.provider_id = ?
    ORDER BY r.created_at DESC
");
$rev_stmt->bind_param("i", $user_id);
$rev_stmt->execute();
$rev_result = $rev_stmt->get_result();
while($r = $rev_result->fetch_assoc()) {
    $reviews[] = $r;
    $sum_rating += $r['rating'];
}
$rev_stmt->close();

$total_reviews = count($reviews);
if ($total_reviews > 0) {
    $avg_rating = round($sum_rating / $total_reviews, 1);
}

// booking data
$pending_requests = [];
$active_jobs = [];
$completed_jobs = [];
$total_earnings = 0;

if (!empty($all_bookings)) {
    foreach ($all_bookings as $booking) {
        $status = strtolower($booking['status']);
        
        if ($status === 'pending') {
            $pending_requests[] = $booking;
        } elseif (in_array($status, ['accepted', 'quote_submitted', 'payment_held', 'in_progress', 'pending_review'])) {
            $active_jobs[] = $booking;
        } 
        elseif (in_array($status, ['completed', 'cancelled'])) {
            $completed_jobs[] = $booking;
            if ($status === 'completed' && in_array(strtolower($booking['payment_status']), ['paid', 'released'])) {
                $total_earnings += floatval($booking['final_price']);
            }
        }
    }
}

$unread_notifs = count(array_filter($notifications, function($n) { return !$n['is_read']; }));

// calendar data preparation
$currentMonth = isset($_GET['month']) ? str_pad(intval($_GET['month']), 2, '0', STR_PAD_LEFT) : date('m');
$currentYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Calculate previous and next months for the buttons
$prevMonth = $currentMonth - 1;
$prevYear = $currentYear;
if ($prevMonth == 0) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $currentMonth + 1;
$nextYear = $currentYear;
if ($nextMonth == 13) {
    $nextMonth = 1;
    $nextYear++;
}
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
$firstDayOfMonth = date('w', strtotime("$currentYear-$currentMonth-01")); 

$jobDays = [];
$jobsByDay = []; 
foreach($active_jobs as $job) {
    if(date('m', strtotime($job['service_date'])) == $currentMonth) {
        $d = (int)date('d', strtotime($job['service_date']));
        $jobDays[] = $d;
        $jobsByDay[$d][] = $job; 
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Dashboard | Amandla Skills</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">    <link rel="stylesheet" href="index.css">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        
    </style>
</head>
<body class="bg-light d-flex flex-column min-vh-100">
    
    <header class="container-fluid py-1 px-1 border-bottom bg-white">
        <?php include('navBar.php'); ?>
    </header>

    <main class="container my-5 flex-grow-1">
        
        <div class="card shadow-sm border-0 rounded-4 mb-4 overflow-hidden">
            <div class="card-body p-4 p-md-5 d-flex align-items-center flex-column flex-md-row">
                
                <div class="profile-pic-large me-md-4 mb-3 mb-md-0 d-flex align-items-center justify-content-center overflow-hidden shadow-sm" style="width: 100px; height: 100px; font-size: 2.5rem; background-color: #f8f9fa; border-radius: 50%;">
                    <?php if (!empty($provider_profile['profile_pic'])): ?>
                        <img src="<?php echo htmlspecialchars($provider_profile['profile_pic']); ?>" alt="Profile Picture" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <span>&#x1F464;</span>
                    <?php endif; ?>
                </div>

                <div class="flex-grow-1 text-center text-md-start">
                    <h3 class="fw-bold mb-1"><?php echo $translations[$lang]['welcome']; ?> <?php echo htmlspecialchars(explode(' ', trim($provider_profile['name'] ?? 'Provider'))[0]); ?></h3>
                    <p class="text-muted mb-0"><?php echo $translations[$lang]['provider_dash_desc'] ?? 'Here is the status of your business and schedule.'; ?></p>
                </div>
                <div class="mt-3 mt-md-0 ms-md-auto d-flex flex-wrap gap-2 justify-content-center">
                    <button class="btn btn-outline-secondary fw-bold px-4 rounded-pill" data-bs-toggle="modal" data-bs-target="#editProfileModal"><?php echo $translations[$lang]['photo'] ?? 'Update Photo'; ?></button>
                    <button class="btn btn-outline-secondary fw-bold px-4 rounded-pill" data-bs-toggle="modal" data-bs-target="#accountSettingsModal"><?php echo $translations[$lang]['acc_settings'] ?? 'Account Settings'; ?></button>
                    <a href="profile.php?id=<?php echo $user_id; ?>" class="btn text-white fw-bold px-4 rounded-pill shadow-sm" style="background-color: #6f42c1;"><?php echo $translations[$lang]['manage_services'] ?? 'Manage Services'; ?></a>
                </div>
            </div>
        </div>

        <!-- Verification Status Module -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm border-0 rounded-4" style="background-color: #fcfaff; border: 1px solid rgba(124, 58, 237, 0.1) !important;">
                    <div class="card-body p-3 d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle text-purple d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px; font-size: 1.2rem; background-color: rgba(124, 58, 237, 0.1);">&#x1F6E1;</div>
                            <div>
                                <h6 class="fw-bold mb-0 text-dark">Trust & Verification</h6>
                                <?php if (!isset($provider_profile['verification_tier']) || $provider_profile['verification_tier'] === 'none'): ?>
                                    <small class="text-muted">You do not have a verification badge yet.</small>
                                <?php else: ?>
                                    <small class="text-muted">Your current standing on Amandla Skills.</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div>
                            <?php if (!isset($provider_profile['verification_tier']) || $provider_profile['verification_tier'] === 'none'): ?>
                                <?php if (isset($provider_profile['verification_status']) && $provider_profile['verification_status'] === 'pending'): ?>
                                    <span class="badge bg-warning text-dark rounded-pill px-3 py-2 border border-warning">Application Pending Review</span>
                                <?php else: ?>
                                    <a href="verificationApply.php" class="btn btn-sm text-white fw-bold px-4 rounded-pill shadow-sm" style="background-color: #7c3aed;">Apply for Badge</a>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php 
                                    include_once('verificationBadge.php'); 
                                    echo getVerificationBadge($provider_profile['verification_tier'], 'normal'); 
                                ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- shows metrics at a glance: new job requests, active jobs, earnings, reviews -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card dashboard-stat-card shadow-sm border-0 rounded-4 bg-white h-100" onclick="switchTab('#requests-tab')">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="rounded-circle bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 45px; height: 45px; font-size: 1.2rem;">&#x1F4E5;</div>
                        <div>
                            <h4 class="fw-bold mb-0"><?php echo count($pending_requests); ?></h4>
                            <span class="text-muted small"><?php echo $translations[$lang]['requests']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card dashboard-stat-card shadow-sm border-0 rounded-4 bg-white h-100" onclick="switchTab('#active-tab')">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 45px; height: 45px; font-size: 1.2rem;">&#x1F4C5;</div>
                        <div>
                            <h4 class="fw-bold mb-0"><?php echo count($active_jobs); ?></h4>
                            <span class="text-muted small"><?php echo $translations[$lang]['active_jobs']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card dashboard-stat-card shadow-sm border-0 rounded-4 bg-white h-100" onclick="switchTab('#history-tab')">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 45px; height: 45px; font-size: 1.2rem;">&#x1F4B5;</div>
                        <div>
                            <h4 class="fw-bold mb-0">R <?php echo number_format($total_earnings, 0); ?></h4>
                            <span class="text-muted small"><?php echo $translations[$lang]['earnings']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card dashboard-stat-card shadow-sm border-0 rounded-4 bg-white h-100" onclick="switchTab('#reviews-tab')">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="rounded-circle text-purple d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 45px; height: 45px; font-size: 1.2rem; background-color: rgba(124, 58, 237, 0.1);">&#x2B50;</div>
                        <div>
                            <h4 class="fw-bold mb-0"><?php echo number_format($avg_rating, 1); ?></h4>
                            <span class="text-muted small"><?php echo $translations[$lang]['rating']; ?> (<?php echo $total_reviews; ?>)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ul class="nav nav-pills mb-4 gap-2" id="providerTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active rounded-pill px-4" id="requests-tab" data-bs-toggle="pill" data-bs-target="#requests" type="button" role="tab">New Requests <?php if(count($pending_requests) > 0) echo '<span class="badge bg-light-subtle text-light-emphasis ms-1">'.count($pending_requests).'</span>'; ?></button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill px-4" id="active-tab" data-bs-toggle="pill" data-bs-target="#active" type="button" role="tab"><?php echo $translations[$lang]['active_jobs']; ?> <?php if(count($active_jobs) > 0) echo '<span class="badge bg-light-subtle text-light-emphasis ms-1">' . count($active_jobs) . '</span>'; ?></button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill px-4" id="history-tab" data-bs-toggle="pill" data-bs-target="#history" type="button" role="tab"><?php echo $translations[$lang]['past_jobs']; ?></button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill px-4" id="reviews-tab" data-bs-toggle="pill" data-bs-target="#reviews" type="button" role="tab"><?php echo $translations[$lang]['reviews']; ?></button>
            </li>
        </ul>

        <div class="row g-4">
            
            <div class="col-lg-8">
                <div class="tab-content" id="providerTabsContent">
                    
                <!-- Requests Tab -->
                    <div class="tab-pane fade show active" id="requests" role="tabpanel">
                        <div class="card shadow-sm border-0 rounded-4 mb-4">
                            <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4">
                                <h5 class="fw-bold mb-0"><?php echo $translations[$lang]['requests']; ?></h5>
                            </div>
                            <div class="card-body p-4 pt-0">
                                <?php if (empty($pending_requests)): ?>
                                    <div class="text-center py-4 border rounded-4 bg-light">
                                        <p class="text-muted small mb-0">You have no new booking requests right now.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($pending_requests as $req): ?>
                                            <div class="list-group-item px-0 py-3 border-bottom border-light">
                                                <div class="row align-items-center">
                                                    <div class="col-md-7 mb-3 mb-md-0">
                                                        <h6 class="fw-bold mb-1 text-truncate"><?php echo htmlspecialchars($req['work_description']); ?></h6>
                                                        <small class="text-muted d-block">Client: <strong><?php echo htmlspecialchars($req['client_name']); ?></strong></small>
                                                        <small class="text-muted d-block">Requested Date: <?php echo date('M d, Y', strtotime($req['service_date'])); ?></small>
                                                        <small class="text-secondary d-block mt-1">
                                                            Location: <strong><?php echo htmlspecialchars($req['client_address'] ?? 'Not specified'); ?></strong>
                                                        </small>
                                                    </div>

                                                    <div class="col-md-5 d-flex align-items-center gap-2 justify-content-md-end flex-wrap">
                                                        <button type="button"
                                                                class="btn btn-sm btn-outline-secondary fw-bold rounded-pill px-3"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#jobDetailsModal<?php echo $req['booking_id']; ?>">
                                                            View Details
                                                        </button>
                                                        <form action="process/processBookingAction.php" method="POST" class="m-0">
                                                            <input type="hidden" name="booking_id" value="<?php echo $req['booking_id']; ?>">
                                                            <input type="hidden" name="client_id"  value="<?php echo $req['client_id']; ?>">
                                                            <input type="hidden" name="action"     value="decline">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger fw-bold rounded-pill px-3">Decline</button>
                                                        </form>
                                                        <form action="process/processBookingAction.php" method="POST" class="m-0">
                                                            <input type="hidden" name="booking_id" value="<?php echo $req['booking_id']; ?>">
                                                            <input type="hidden" name="client_id"  value="<?php echo $req['client_id']; ?>">
                                                            <input type="hidden" name="action"     value="accept">
                                                            <button type="submit" class="btn btn-sm btn-success fw-bold rounded-pill px-4 shadow-sm">Accept</button>
                                                        </form>
                                                    </div>
                                                </div>

                                                <div class="modal fade" id="jobDetailsModal<?php echo $req['booking_id']; ?>" tabindex="-1" aria-hidden="true" style="backdrop-filter: blur(5px); background-color: rgba(0,0,0,0.2);">
                                                    <div class="modal-dialog modal-dialog-centered">
                                                        <div class="modal-content border-0 shadow-lg rounded-4">
                                                            
                                                            <div class="modal-header border-bottom-0 pb-0">
                                                                <h5 class="modal-title fw-bold text-dark">Full Job Details</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            
                                                            <div class="modal-body pt-3">
                                                                <div class="bg-light p-3 rounded-3 mb-4 border border-light">
                                                                    <div class="row g-3">
                                                                        <div class="col-6">
                                                                            <span class="d-block small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.7rem;">Date</span>
                                                                            <span class="fw-bold text-dark"><?php echo date('l, M d, Y', strtotime($req['service_date'])); ?></span>
                                                                        </div>
                                                                        <div class="col-6">
                                                                            <span class="d-block small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.7rem;">Time</span>
                                                                            <span class="fw-bold text-dark"><?php echo date('h:i A', strtotime($req['service_date'])); ?></span>
                                                                        </div>
                                                                        <div class="col-12 border-top pt-2 mt-2">
                                                                            <span class="d-block small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.7rem;">Location Address</span>
                                                                            <span class="fw-bold text-dark"> <?php echo htmlspecialchars($req['client_address'] ?? 'Address not specified'); ?></span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                
                                                                <h6 class="fw-bold text-dark mb-2">Job Description</h6>
                                                                <div class="p-3 bg-white border border-secondary-subtle rounded-3" style="max-height: 250px; overflow-y: auto;">
                                                                    <p class="mb-0 text-secondary" style="line-height: 1.6; white-space: pre-wrap;"><?php echo htmlspecialchars($req['work_description']); ?></p>
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="modal-footer border-top-0 pt-0">
                                                                <button type="button" class="btn btn-secondary rounded-pill fw-bold w-100 py-2" data-bs-dismiss="modal">Close</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Active Jobs Tab -->
                    <div class="tab-pane fade" id="active" role="tabpanel">
                        <div class="card shadow-sm border-0 rounded-4 mb-4">
                            <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4">
                                <h5 class="fw-bold mb-0"><?php echo $translations[$lang]['active_jobs']; ?></h5>
                            </div>
                            <div class="card-body p-4 pt-0">
                                <?php if (empty($active_jobs)): ?>
                                    <div class="text-center py-4 border rounded-4 bg-light">
                                        <p class="text-muted small mb-0">You have no active jobs scheduled.</p>
                                    </div>
                                <?php else: ?>

                                    <!-- Contact client info and job details -->
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($active_jobs as $job): ?>
                                            <?php $job_status = strtolower($job['status']); ?>
                                            <div class="list-group-item px-0 py-4 border-bottom border-light">
                                                <div class="row align-items-start">
                                                    <div class="col-md-7 mb-3 mb-md-0">
                                                        <h6 class="job-heading mb-1 text-truncate">
                                                            <?php echo htmlspecialchars($job['work_description']); ?>
                                                        </h6>
                                                        <small class="text-muted d-block mb-3">Client: <span class="text-dark fw-semibold"><?php echo htmlspecialchars($job['client_name'] ?? 'N/A'); ?></span></small>

                                                        <div class="client-info-card">
                                                            <div class="mb-2">

                                                            <!-- Contact row -->
                                                            <div class="d-flex align-items-center gap-2">
                                                                <div>
                                                                    <span class="label-mini">&#9990; Contact Number</span>
                                                                    <a href="tel:<?php echo htmlspecialchars($job['client_phone'] ?? ''); ?>"
                                                                    class="text-decoration-none fw-bold text-body" style="font-size: 0.875rem;">
                                                                        <?php echo htmlspecialchars($job['client_phone'] ?? 'No Number'); ?>
                                                                    </a>
                                                                </div>
                                                            </div>

                                                            <!-- Button row -->
                                                            <?php
                                                                $clean_wa_num = preg_replace('/[^0-9]/', '', $job['client_phone'] ?? '');
                                                                if (substr($clean_wa_num, 0, 1) === '0') {
                                                                    $clean_wa_num = '27' . substr($clean_wa_num, 1);
                                                                }
                                                                $wa_message = urlencode(
                                                                    "Hello " . ($job['client_name'] ?? '') .
                                                                    ", this is " . ($provider_profile['name'] ?? '') .
                                                                    " from Amandla Skills. I'm contacting you regarding your booking for: " .
                                                                    ($job['work_description'] ?? '')
                                                                );
                                                            ?>
                                                            <div class="d-flex align-items-center gap-2 flex-wrap mt-2">

                                                                <button type="button" class="btn btn-sm btn-outline-secondary fw-bold rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#activeJobModal<?php echo $job['booking_id']; ?>">
                                                                    View Details
                                                                </button>

                                                                <a href="https://wa.me/<?php echo $clean_wa_num; ?>?text=<?php echo $wa_message; ?>" target="_blank" class="btn btn-sm whatsapp-action rounded-pill px-3 fw-bold">
                                                                    WhatsApp
                                                                </a>

                                                                <button type="button" class="btn btn-sm btn-outline-danger fw-bold rounded-pill px-3 ms-auto" data-bs-toggle="modal" data-bs-target="#reportClientModal<?php echo $job['booking_id']; ?>">
                                                                    Report Client
                                                                </button>

                                                            </div>
                                                        </div>

                                                            <div class="modal fade" id="reportClientModal<?php echo $job['booking_id']; ?>" tabindex="-1" aria-hidden="true">
                                                                <div class="modal-dialog modal-dialog-centered">
                                                                    <form action="process/processReportClient.php" method="POST" class="modal-content border-0 shadow-lg rounded-4">
                                                                        <div class="modal-header border-bottom-0 pb-0">
                                                                            <h5 class="modal-title fw-bold">Report <?php echo htmlspecialchars($job['client_name'] ?? 'Client'); ?></h5>
                                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                        </div>
                                                                        <div class="modal-body pt-3">
                                                                            <input type="hidden" name="booking_id" value="<?php echo $job['booking_id']; ?>">
                                                                            <input type="hidden" name="reported_user_id" value="<?php echo $job['client_id']; ?>">
                                                                            
                                                                            <p class="small text-muted mb-3">Reporting this client will automatically cancel the booking and alert our admin team. This action cannot be undone.</p>
                                                                            
                                                                            <label class="form-label small fw-bold text-muted text-uppercase">Reason for reporting:</label>
                                                                            <textarea name="complaint_text" class="form-control border-secondary-subtle" rows="3" required placeholder="Please describe the issue in detail..."></textarea>
                                                                        </div>
                                                                        <div class="modal-footer border-0 pt-0">
                                                                            <button type="submit" class="btn btn-danger w-100 rounded-pill fw-bold py-2">Submit Report & Cancel Job</button>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </div>

                                                            <div class="d-flex align-items-start">
                                                                <span class="me-2">&#x1F4CD;</span>
                                                                <div>
                                                                    <span class="label-mini">Service Location</span>
                                                                    <span class="small text-secondary fw-medium"><?php echo htmlspecialchars($job['client_address'] ?? 'Address not set'); ?></span>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <small class="text-purple fw-bold d-block mt-2">
                                                            Date: <?php echo date('M d, Y', strtotime($job['service_date'])); ?>
                                                            <span class="ms-2">Time: <?php echo date('H:i', strtotime($job['service_date'])); ?></span>
                                                        </small>

                                                        <!-- Status indicator -->
                                                        <div class="mt-2">
                                                            <?php if ($job_status === 'accepted'): ?>
                                                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-3 py-1">Accepted — Ready to Quote</span>
                                                            <?php elseif ($job_status === 'quote_submitted'): ?>
                                                                <span class="badge bg-warning text-dark border border-warning border-opacity-25 rounded-pill px-3 py-1">Quote Sent — Awaiting Client Approval</span>
                                                                <?php if (!empty($job['quoted_price'])): ?>
                                                                    <small class="d-block text-muted mt-1">Quoted: <strong>R <?php echo number_format($job['quoted_price'], 2); ?></strong></small>
                                                                <?php endif; ?>
                                                            <?php elseif ($job_status === 'payment_held'): ?>
                                                                <div class="alert alert-success border-0 rounded-3 py-2 px-3 mb-0 d-flex align-items-center gap-2">
                                                                    <span class="fw-bold small">Funds Secured — You may begin work</span>
                                                                </div>
                                                            <?php elseif ($job_status === 'pending_review'): ?>
                                                                <span class="badge bg-info text-dark border rounded-pill px-3 py-1">Pending Client Confirmation (24hrs)</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-5 text-md-end d-flex flex-column justify-content-center">
                                                        <?php if ($job_status === 'accepted'): ?>
                                                            <button class="btn btn-sm text-white fw-bold rounded-pill px-4 py-2 shadow-sm mb-2"
                                                                    style="background-color: #6f42c1;"
                                                                    data-bs-toggle="modal" data-bs-target="#quoteModal<?php echo $job['booking_id']; ?>">
                                                                Submit Quote
                                                            </button>
                                                        <?php elseif ($job_status === 'payment_held'): ?>
                                                            <button class="btn btn-sm btn-success fw-bold rounded-pill px-4 py-2 shadow-sm mb-2"
                                                                    data-bs-toggle="modal" data-bs-target="#completeModal<?php echo $job['booking_id']; ?>">
                                                                Mark as Complete
                                                            </button>
                                                        <?php elseif ($job_status === 'quote_submitted'): ?>
                                                            <span class="text-muted small fst-italic">Waiting for client approval...</span>
                                                        <?php elseif ($job_status === 'pending_review'): ?>
                                                            <span class="text-muted small fst-italic">Waiting for client confirmation...</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Submit Quote Modal -->
                                            <?php if ($job_status === 'accepted'): ?>
                                            <div class="modal fade" id="quoteModal<?php echo $job['booking_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content border-0 shadow">
                                                        <div class="modal-header border-bottom-0">
                                                            <h5 class="modal-title fw-bold">Submit Quote</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body pt-0">
                                                            <p class="small text-muted mb-4">Submit your price for <strong>"<?php echo htmlspecialchars($job['work_description']); ?>"</strong>. The client will approve before any money moves.</p>
                                                            <form action="process/processQuote.php" method="POST">
                                                                <input type="hidden" name="booking_id" value="<?php echo $job['booking_id']; ?>">
                                                                <input type="hidden" name="client_id" value="<?php echo $job['client_id']; ?>">
                                                                <div class="mb-3">
                                                                    <label class="form-label small fw-bold text-muted text-uppercase">Quoted Amount (R)</label>
                                                                    <input type="number" step="0.01" min="0" class="form-control border-secondary-subtle" name="quoted_price" required placeholder="e.g. 850.00">
                                                                </div>
                                                                <div class="mb-4">
                                                                    <label class="form-label small fw-bold text-muted text-uppercase">Breakdown / Description</label>
                                                                    <textarea class="form-control border-secondary-subtle" name="quote_description" rows="3" required placeholder="What the job involves, materials needed, travel, etc."></textarea>
                                                                </div>
                                                                <button type="submit" class="btn text-white w-100 fw-bold rounded-pill shadow-sm" style="background-color: #6f42c1;">Send Quote to Client</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                            <!-- Mark Complete -->
                                            <?php if ($job_status === 'payment_held'): ?>
                                            <div class="modal fade" id="completeModal<?php echo $job['booking_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content border-0 shadow">
                                                        <div class="modal-header border-bottom-0">
                                                            <h5 class="modal-title fw-bold">Mark Job as Complete</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body pt-0">
                                                            <p class="small text-muted mb-4">You are marking <strong>"<?php echo htmlspecialchars($job['work_description']); ?>"</strong> as done. The client gets 24 hours to confirm before funds are released to you.</p>
                                                            <form action="process/processJobComplete.php" method="POST">
                                                                <input type="hidden" name="booking_id" value="<?php echo $job['booking_id']; ?>">
                                                                <input type="hidden" name="client_id" value="<?php echo $job['client_id']; ?>">
                                                                <button type="submit" class="btn btn-success w-100 fw-bold rounded-pill">Confirm — Job Is Done</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                            <!-- details  -->
                                            <div class="modal fade" id="activeJobModal<?php echo $job['booking_id']; ?>" tabindex="-1" aria-hidden="true" style="backdrop-filter: blur(5px); background-color: rgba(0,0,0,0.2);">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content border-0 shadow-lg rounded-4">
                                                        <div class="modal-header border-bottom-0 pb-0">
                                                            <h5 class="modal-title fw-bold text-dark">Job Details</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body pt-3">
                                                            <div class="bg-light p-3 rounded-3 mb-4 border border-light">
                                                                <div class="row g-3">
                                                                    <div class="col-6">
                                                                        <span class="d-block small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.7rem;">Date</span>
                                                                        <span class="fw-bold text-dark"><?php echo date('M d, Y', strtotime($job['service_date'])); ?></span>
                                                                    </div>
                                                                    <div class="col-6">
                                                                        <span class="d-block small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.7rem;">Time</span>
                                                                        <span class="fw-bold text-dark"><?php echo date('H:i', strtotime($job['service_date'])); ?></span>
                                                                    </div>
                                                                    <div class="col-12 border-top pt-2 mt-2">
                                                                        <span class="d-block small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.7rem;">Location Address</span>
                                                                        <span class="fw-bold text-dark"> <?php echo htmlspecialchars($job['client_address'] ?? 'Not set'); ?></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            
                                                            <h6 class="fw-bold text-dark mb-2">Full Description</h6>
                                                            <div class="p-3 bg-white border border-secondary-subtle rounded-3" style="max-height: 250px; overflow-y: auto;">
                                                                <p class="mb-0 text-secondary" style="line-height: 1.6; white-space: pre-wrap;"><?php echo htmlspecialchars($job['work_description']); ?></p>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer border-top-0 pt-0">
                                                            <button type="button" class="btn btn-secondary rounded-pill fw-bold w-100 py-2" data-bs-dismiss="modal">Close</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Past Jobs Tab -->
                    <div class="tab-pane fade" id="history" role="tabpanel">
                        <div class="card shadow-sm border-0 rounded-4">
                            <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4">
                                <h5 class="fw-bold mb-0"><?php echo $translations[$lang]['past_jobs']; ?></h5>
                            </div>
                            <div class="card-body p-4 pt-0">
                                <?php if (empty($completed_jobs)): ?>
                                    <div class="text-center py-4 border rounded-4 bg-light">
                                        <p class="text-muted small mb-0">You haven't completed any jobs yet.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-borderless align-middle mb-0">
                                            <thead class="border-bottom">
                                                <tr class="small text-muted text-uppercase">
                                                    <th>Job</th>
                                                    <th>Date</th>
                                                    <th>Earnings</th>
                                                    <th class="text-end">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($completed_jobs as $job): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="fw-bold text-truncate" style="max-width: 180px; font-size: 0.9rem;"><?php echo htmlspecialchars($job['work_description']); ?></div>
                                                            <small class="text-muted"><?php echo htmlspecialchars($job['client_name']); ?></small>
                                                        </td>
                                                        
                                                        <td class="text-muted small"><?php echo date('M d', strtotime($job['service_date'])); ?></td>
                                                        <td class="fw-bold" style="font-size: 0.9rem;">
                                                        <?php 
                                                            if (strtolower($job['status']) === 'cancelled') {
                                                                echo "<span class='text-muted'>R 0.00</span>";
                                                            } else {
                                                                echo "R " . number_format($job['final_price'], 2); 
                                                            }
                                                            ?>
                                                        </td>
                                                        <td class="text-end">
                                                            <?php if (strtolower($job['status']) === 'cancelled'): ?>
                                                                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 rounded-pill px-2">Cancelled</span>
                                                            <?php elseif(in_array(strtolower($job['payment_status']), ['released', 'paid'])): ?>
                                                                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2">Paid</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-2">Unpaid</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Reviews Tab -->
                    <div class="tab-pane fade" id="reviews" role="tabpanel">
                        <div class="card shadow-sm border-0 rounded-4">
                            <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4">
                                <h5 class="fw-bold mb-0"><?php echo $translations[$lang]['client_reviews']; ?></h5>
                            </div>
                            <div class="card-body p-4 pt-0">
                                
                                <div class="d-flex align-items-center mb-4 pb-4 border-bottom">
                                    <h1 class="display-4 fw-bold text-dark mb-0 me-3"><?php echo number_format($avg_rating, 1); ?></h1>
                                    <div>
                                        <div class="text-warning fs-5" style="letter-spacing: 2px;">
                                            <?php
                                            for($i=1; $i<=5; $i++) {
                                                echo ($i <= round($avg_rating)) ? '★' : '☆';
                                            }
                                            ?>
                                        </div>
                                        <span class="text-muted small">Based on <?php echo $total_reviews; ?> reviews</span>
                                    </div>
                                </div>

                                <?php if (empty($reviews)): ?>
                                    <div class="text-center py-4 border rounded-4 bg-light">
                                        <p class="text-muted small mb-0">You don't have any reviews yet.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($reviews as $rev): ?>
                                            <div class="list-group-item px-0 py-3 border-0 mb-2">
                                                <div class="d-flex align-items-start">
                                                    <div class="profile-pic-large me-3 flex-shrink-0 d-flex align-items-center justify-content-center overflow-hidden bg-light" style="width: 45px; height: 45px; font-size: 1.2rem; border-radius: 50%;">
                                                        <?php if (!empty($rev['client_pic'])): ?>
                                                            <img src="<?php echo htmlspecialchars($rev['client_pic']); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
                                                        <?php else: ?>
                                                            <span>👤</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="w-100">
                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                            <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($rev['client_name']); ?></h6>
                                                            <small class="text-muted"><?php echo date('M d, Y', strtotime($rev['created_at'])); ?></small>
                                                        </div>
                                                        <div class="text-warning small mb-2">
                                                            <?php
                                                            for($i=1; $i<=5; $i++) {
                                                                echo ($i <= $rev['rating']) ? '★' : '☆';
                                                            }
                                                            ?>
                                                        </div>
                                                        <p class="text-secondary small mb-0" style="line-height: 1.5;">
                                                            "<?php echo htmlspecialchars($rev['comment_text']); ?>"
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="col-lg-4">
                
            <!-- Calendar -->
                <div class="card shadow-sm border-0 rounded-4 mb-4">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0">
                            Schedule: <?php echo date('F Y', strtotime("$currentYear-$currentMonth-01")); ?>
                        </h6>
                        <div class="d-flex gap-2">
                            <a href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>#history" class="btn btn-sm btn-light border rounded-circle d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; text-decoration: none;">&lt;</a>
                            
                            <?php if ($currentMonth != date('m') || $currentYear != date('Y')): ?>
                                <a href="?month=<?php echo date('m'); ?>&year=<?php echo date('Y'); ?>#history" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.75rem;">Today</a>
                            <?php endif; ?>
                            
                            <a href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>#history" class="btn btn-sm btn-light border rounded-circle d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; text-decoration: none;">&gt;</a>
                        </div>
                    </div>
                    <div class="card-body p-4 pt-2">
                        <div class="calendar-grid mb-2">
                            <div class="calendar-day-header">S</div>
                            <div class="calendar-day-header">M</div>
                            <div class="calendar-day-header">T</div>
                            <div class="calendar-day-header">W</div>
                            <div class="calendar-day-header">T</div>
                            <div class="calendar-day-header">F</div>
                            <div class="calendar-day-header">S</div>
                            
                            <?php 
                            for ($i = 0; $i < $firstDayOfMonth; $i++) {
                                echo '<div class="calendar-day empty"></div>';
                            }

                            for ($day = 1; $day <= $daysInMonth; $day++) {
                                $classes = "calendar-day";
                                if ($day == date('d')) $classes .= " today";
                                
                                $hasJob = in_array($day, $jobDays);
                                if ($hasJob) $classes .= " has-job";
                                
                                // If day has a job, make it clickable to open a modal
                                if ($hasJob) {
                                    echo "<div class='$classes' data-bs-toggle='modal' data-bs-target='#dayModal$day' title='Click to view schedule'>$day</div>";
                                } else {
                                    echo "<div class='$classes'>$day</div>";
                                }
                            }
                            ?>
                        </div>
                        <div class="d-flex justify-content-between mt-3 px-2">
                            <small class="text-muted"><span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:#6f42c1;"></span>Booked</small>
                            <small class="text-muted"><span class="d-inline-block rounded-circle me-1 border border-2 border-purple" style="width:8px;height:8px;border-color:#6f42c1;"></span>Today</small>
                        </div>
                    </div>
                </div>

                <!-- Right sidebar -->
                <div class="card shadow-sm border-0 rounded-4 mb-4">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0"><?php echo $translations[$lang]['notification']; ?></h6>
                        <?php if ($unread_notifs > 0): ?>
                            <span class="badge rounded-circle"><?php echo $unread_notifs; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-4 pt-2">
                        <?php if(empty($notifications)): ?>
                            <p class="small text-muted mb-3"><?php echo $translations[$lang]['notiEmpty']; ?></p>
                        <?php else: 
                            $displayNotif = null;
                            foreach($notifications as $n) {
                                if(!$n['is_read']) { $displayNotif = $n; break; }
                            }
                            if(!$displayNotif) $displayNotif = $notifications[0];
                            $is_unread = !$displayNotif['is_read'];
                        ?>
                            <div class="d-flex align-items-start p-2 mb-3 rounded" style="<?php echo $is_unread ? 'background-color: rgba(111, 66, 193, 0.05); border-left: 4px solid #6f42c1;' : 'background-color: #f8f9fa; border: 1px solid #dee2e6;'; ?>">
                                <span class="me-2 fs-5"><?php echo $is_unread ? '&#x1F514;' : '&#x2714;'; ?></span>
                                <div class="text-truncate w-100">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="fw-bold mb-0" style="font-size: 0.9rem; <?php echo $is_unread ? 'color:#6f42c1;' : 'color:#495057;'; ?>">
                                            <?php echo htmlspecialchars($displayNotif['title']); ?>
                                        </h6>
                                    </div>
                                    <small class="text-muted d-block text-truncate" style="font-size: 0.8rem;">
                                        <?php echo htmlspecialchars($displayNotif['message']); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <button class="btn btn-link text-decoration-none w-100 small fw-bold p-0" style="color: #6f42c1; font-size: 0.85rem;" data-bs-toggle="modal" data-bs-target="#notificationsModal">Open All Notifications</button>
                    </div>
                </div>

                <div class="card shadow-sm border-0 rounded-4 bg-purple-light" style="background-color: rgba(111, 66, 193, 0.05);">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3">Quick Actions</h6>
                        <a href="profile.php?id=<?php echo $user_id; ?>" class="btn w-100 rounded-pill fw-bold py-2 btn-premium-purple">
                            Manage My Services
                        </a>                    
                    </div>
                </div>

            </div>
        </div>
    </main>

    <?php include('notificationModal.php'); ?>

    <div class="modal fade" id="editProfileModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">Update Profile Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="process/processProfileUpdate.php" method="POST" enctype="multipart/form-data">
                        <div class="mb-4 text-center">
                            <label class="form-label small fw-bold text-muted text-uppercase d-block mb-2">Select a New Picture</label>
                            <input class="form-control border-secondary-subtle" type="file" name="profile_pic" accept="image/png, image/jpeg, image/webp" required>
                        </div>
                        <button type="submit" class="btn text-white w-100 fw-bold py-2 rounded-pill" style="background-color: #7c3aed;">Upload Photo</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <footer class="container-fluid py-1 px-1 border-bottom bg-white mt-auto">
        <?php include('footer.php'); ?>
    </footer>

    <?php foreach ($jobsByDay as $dayNum => $dayJobs): ?>
        <div class="modal fade" id="dayModal<?php echo $dayNum; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header border-bottom-0 pb-0">
                        <h5 class="modal-title fw-bold">Schedule for <?php echo date('M', mktime(0, 0, 0, $currentMonth, 10)) . " " . $dayNum; ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="list-group list-group-flush">
                            <?php foreach ($dayJobs as $j): ?>
                                <div class="list-group-item px-0 py-3 border-bottom-0">
                                    <h6 class="fw-bold mb-1 text-purple"><?php echo htmlspecialchars($j['work_description']); ?></h6>
                                    <p class="small text-muted mb-2">Client: <strong><?php echo htmlspecialchars($j['client_name']); ?></strong></p>
                                    
                                    <div class="p-2 rounded-3 bg-light border-start border-4 border-purple mb-2">
                                        <small class="d-block mb-1"><?php echo htmlspecialchars($j['client_address'] ?? 'No Address'); ?></small>
                                        <small class="d-block"><?php echo htmlspecialchars($j['client_phone'] ?? 'No Phone'); ?></small>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <a href="tel:<?php echo $j['client_phone']; ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Call</a>
                                        <?php $wa = preg_replace('/[^0-9]/', '', $j['client_phone'] ?? ''); ?>
                                        <a href="https://wa.me/<?php echo $wa; ?>" target="_blank" class="btn btn-sm btn-success rounded-pill px-3">WhatsApp</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>

    <!-- account settings -->
    <?php
    $acct_name   = $provider_profile['name'] ?? $_SESSION['name'] ?? 'Your Account';
    $acct_email  = $_SESSION['email'] ?? '';
    $acct_pic    = $provider_profile['profile_pic'] ?? null;
    $acct_phone  = $provider_profile['phone_number'] ?? '';
    $acct_role   = match($_SESSION['user_role'] ?? '') {
        'service_provider' => 'Service Provider',
        'admin' => 'Administrator',
        default => 'Client',
    };
    $acct_initials = '';
    if (!empty($acct_name) && $acct_name !== 'Your Account') {
        $parts = explode(' ', trim($acct_name));
        $acct_initials = strtoupper(substr($parts[0],0,1) . (isset($parts[1]) ? substr($parts[1],0,1) : ''));
    }
    ?>

    <div class="modal fade" id="accountSettingsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 430px;">
            <div class="modal-content shadow">

                <div class="modal-header border-0 pb-0 px-4 pt-4">
                    <h6 class="modal-title fw-bold mb-0">Account Settings</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <!-- profile -->
                <div class="asm-profile-bar mt-3">
                    <div class="asm-avatar">
                        <?php if (!empty($acct_pic)): ?>
                            <img src="<?php echo htmlspecialchars($acct_pic); ?>"
                                style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                        <?php else: ?>
                            <?php echo htmlspecialchars($acct_initials ?: 'U'); ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p class="mb-0 fw-semibold" style="font-size:14px;">
                            <?php echo htmlspecialchars($acct_name); ?>
                        </p>
                        <p class="mb-0 text-muted" style="font-size:12px;">
                            <?php echo htmlspecialchars($acct_email); ?> &middot; <?php echo $acct_role; ?>
                        </p>
                    </div>
                </div>

                <div class="modal-body px-4 pt-3 pb-0">
                    <form action="process/processAccountUpdate.php" method="POST">

                        <p class="asm-section-label">Profile</p>

                        <!-- label changes based on role -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted mb-1">
                                <?php echo ($_SESSION['user_role'] === 'service_provider') ? 'Full Name / Business Name' : 'Full Name'; ?>
                            </label>
                            <input type="text" name="full_name" class="form-control"
                                value="<?php echo htmlspecialchars($acct_name !== 'Your Account' ? $acct_name : ''); ?>"
                                placeholder="Your name or business name">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted mb-1">Email Address</label>
                            <input type="email" name="email" class="form-control"
                                value="<?php echo htmlspecialchars($acct_email); ?>" required>
                        </div>

                        <?php if ($_SESSION['user_role'] === 'service_provider'): ?>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted mb-1">Contact Number</label>
                            <input type="text" name="phone" class="form-control"
                                value="<?php echo htmlspecialchars($acct_phone); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted mb-1">Service Location</label>
                            <input type="text" name="service_location" class="form-control"
                                value="<?php echo htmlspecialchars($provider_profile['service_location'] ?? ''); ?>" 
                                placeholder="e.g. Midrand, Gauteng">
                        </div>
                        <?php endif; ?>

                        <p class="asm-section-label mt-3">Change Password</p>

                        <div class="mb-2">
                            <label class="form-label small fw-semibold text-muted mb-1">New Password</label>
                            <div class="asm-pw-wrap">
                                <input type="password" name="new_password" id="asmPw"
                                    class="form-control pe-5" placeholder="Min. 6 characters"
                                    oninput="asmStrength(this.value)">
                                <button type="button" class="asm-eye-btn" onclick="asmToggle()">
                                    <svg width="15" height="15" viewBox="0 0 15 15" fill="none">
                                        <path d="M1 7.5S3.5 3 7.5 3 14 7.5 14 7.5 11.5 12 7.5 12 1 7.5 1 7.5Z" stroke="currentColor" stroke-width="1.2"/>
                                        <circle cx="7.5" cy="7.5" r="1.75" stroke="currentColor" stroke-width="1.2"/>
                                    </svg>
                                </button>
                            </div>
                            <div class="asm-strength-bars">
                                <div class="asm-bar" id="ab1"></div>
                                <div class="asm-bar" id="ab2"></div>
                                <div class="asm-bar" id="ab3"></div>
                                <div class="asm-bar" id="ab4"></div>
                            </div>
                            <p class="text-muted mt-1 mb-0" style="font-size:11px;">Leave blank to keep current password</p>
                        </div>

                        <!-- Footer -->
                        <div class="asm-footer">
                            <button type="button" 
                                    class="btn btn-outline-secondary fw-bold px-4 rounded-pill"
                                    data-bs-dismiss="modal">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="btn text-white fw-bold px-4 rounded-pill shadow-sm"
                                    style="background-color: #7c3aed;">
                                Save Changes
                            </button>
                        </div>

                    </form>
                </div>

            </div>
        </div>
    </div>
    <script src="dashboard.js"></script>
</body>
</html>