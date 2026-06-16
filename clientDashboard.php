<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
include('lang.php');
include('config.php');
include('dbHelper.php');
include('checkAccess.php');

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// fetch data from db
$client_profile = getClientProfile($conn, $user_id);
$all_bookings = getClientBookings($conn, $user_id); 
$favorites = getClientFavorites($conn, $user_id);

// clients past review on the db
$my_reviews = [];
$reviewed_bookings = [];

$rev_stmt = $conn->prepare("
    SELECT r.*, u.name as provider_name, u.profile_pic as provider_pic 
    FROM reviews r 
    JOIN users u ON r.provider_id = u.id 
    WHERE r.client_id = ? 
    ORDER BY r.created_at DESC
");
$rev_stmt->bind_param("i", $user_id);
$rev_stmt->execute();
$rev_res = $rev_stmt->get_result();
while($r = $rev_res->fetch_assoc()) {
    $my_reviews[] = $r;
    $reviewed_bookings[] = $r['booking_id'];
}
$rev_stmt->close();

// service booking data
$active_bookings = [];
$past_bookings = [];
$unpaid_count = 0;
$reviews_due = 0;

if (!empty($all_bookings)) {
    foreach ($all_bookings as $booking) {
        $status = strtolower($booking['status']);
        $pay_status = strtolower($booking['payment_status'] ?? 'unpaid');

        // Count bookings where client needs to pay (quote was approved)
        if ($status === 'quote_accepted') {
            $unpaid_count++;
        }

        if (in_array($status, ['completed', 'declined', 'cancelled'])) {
            $past_bookings[] = $booking;
            if ($status === 'completed' && !in_array($booking['booking_id'], $reviewed_bookings)) {
                $reviews_due++;
            }
        } else {
            $active_bookings[] = $booking;
        }
    }
}

// notification feature
$all_notifications = [];
try { 
    $all_notifications = getUserNotifications($conn, $user_id); 
} catch (Exception $e) { }


$all_notifications = getUserNotifications($conn, $user_id); 

$notifications = $all_notifications; 
$unread_notifications_count = count(array_filter($notifications, function($n) { return !$n['is_read']; }));

$history_notifications = array_filter($all_notifications, function($n) { 
    return $n['is_read']; 
});
$history_notifications = array_values($history_notifications);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard | Amandla Skills</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">    
    <link rel="stylesheet" href="index.css">
    <link rel="stylesheet" href="dashboard.css">
</head>


<body class="bg-light d-flex flex-column min-vh-100">
    
    <header class="container-fluid py-1 px-1 border-bottom bg-white">
        <?php include('navBar.php'); ?>
        
    </header>

    <main class="container my-5 flex-grow-1">
        
        <div class="card shadow-sm border-0 rounded-4 mb-4 overflow-hidden">
            <div class="card-body p-4 p-md-5 d-flex align-items-center flex-column flex-md-row">
                <div class="profile-pic-large me-md-4 mb-3 mb-md-0 d-flex align-items-center justify-content-center overflow-hidden shadow-sm" style="width: 100px; height: 100px; font-size: 2.5rem; background-color: #f8f9fa; border-radius: 50%;">
                    <?php if (!empty($client_profile['profile_pic'])): ?>
                        <img src="<?php echo htmlspecialchars($client_profile['profile_pic']); ?>" alt="Profile Picture" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <span>👤</span>
                    <?php endif; ?>
                </div>
                <div class="flex-grow-1 text-center text-md-start">
                    <h3 class="fw-bold mb-1"><?php echo $translations[$lang]['welcome']; ?> <?php echo htmlspecialchars(explode(' ', trim($client_profile['name'] ?? 'Client'))[0]); ?></h3>
                </div>
                <div class="mt-3 mt-md-0 ms-md-auto d-flex flex-wrap gap-2 justify-content-center">
                    <button class="btn btn-outline-secondary fw-bold px-4 rounded-pill" data-bs-toggle="modal" data-bs-target="#editProfileModal"><?php echo $translations[$lang]['photo'] ?? 'Update Photo'; ?></button>
                    <button class="btn btn-outline-secondary fw-bold px-4 rounded-pill" data-bs-toggle="modal" data-bs-target="#accountSettingsModal"><?php echo $translations[$lang]['acc_settings'] ?? 'Account Settings'; ?></button>
                    <a href="index.php" class="btn text-white fw-bold px-4 rounded-pill shadow-sm" style="background-color: #6f42c1;">Find a Service</a>
                </div>
            </div>
        </div>

        <!-- tabs that consist of ongoing jobs, payments due and reviews due -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card dashboard-stat-card shadow-sm border-0 rounded-4 bg-white h-100" onclick="switchTab('#ongoing-tab')">
                    <div class="card-body p-4 d-flex align-items-center">
                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center me-3" style="width:50px;height:50px;font-size:1.5rem;">&#x1F4C5;</div>
                        <div>
                            <h4 class="fw-bold mb-0"><?php echo count($active_bookings); ?></h4>
                            <span class="text-muted small"><?php echo $translations[$lang]['active_jobs']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card dashboard-stat-card shadow-sm border-0 rounded-4 bg-white h-100" onclick="switchTab('#ongoing-tab')">
                    <div class="card-body p-4 d-flex align-items-center">
                        <div class="rounded-circle bg-light-subtle text-light-emphasis bg-opacity-10 d-flex align-items-center justify-content-center me-3" style="width:50px;height:50px;font-size:1.5rem;">&#x1F4B3;</div>
                        <div>
                            <h4 class="fw-bold mb-0"><?php echo $unpaid_count; ?></h4>
                            <span class="text-muted small"><?php echo $translations[$lang]['payments_due']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card dashboard-stat-card shadow-sm border-0 rounded-4 bg-white h-100" onclick="switchTab('#past-tab')">
                    <div class="card-body p-4 d-flex align-items-center">
                        <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width:50px;height:50px;font-size:1.5rem;background:rgba(124,58,237,.1);">&#x2B50;</div>
                        <div>
                            <h4 class="fw-bold mb-0"><?php echo $reviews_due; ?></h4>
                            <span class="text-muted small"><?php echo $translations[$lang]['reviews_due']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ul class="nav nav-pills mb-4 gap-2" id="clientTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active rounded-pill px-4" id="ongoing-tab" data-bs-toggle="pill" data-bs-target="#ongoing" type="button" role="tab"><?php echo $translations[$lang]['active_jobs']; ?> <?php if(count($active_bookings) > 0) echo '<span class="badge bg-light-subtle text-light-emphasis ms-1">'.count($active_bookings).'</span>'; ?></button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill px-4" id="past-tab" data-bs-toggle="pill" data-bs-target="#past" type="button" role="tab"><?php echo $translations[$lang]['past_jobs']; ?></button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill px-4" id="reviews-tab" data-bs-toggle="pill" data-bs-target="#reviews" type="button" role="tab"><?php echo $translations[$lang]['reviews']; ?></button>
            </li>
        </ul>

        <div class="row g-4">
            
            <div class="col-lg-8">
                <div class="tab-content" id="clientTabsContent">
                    
                    <div class="tab-pane fade show active" id="ongoing" role="tabpanel">
                        <div class="card shadow-sm border-0 rounded-4 mb-4">
                            <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4">
                                <h5 class="fw-bold mb-0"><?php echo $translations[$lang]['active_jobs']; ?></h5>
                            </div>
                            <div class="card-body p-4 pt-0">
                                <?php if (empty($active_bookings)): ?>
                                    <div class="text-center py-4 border rounded-4 bg-light">
                                        <p class="text-muted small mb-0">You have not hired anyone for an active job.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($active_bookings as $booking): ?>
                                            <?php $bstatus = strtolower($booking['status']); ?>
                                            <div class="list-group-item px-0 py-3 border-bottom border-light">
                                                <div class="row align-items-center">
                                                    <div class="col-md-6 mb-2 mb-md-0">
                                                        <h6 class="fw-bold mb-1 text-truncate"><?php echo htmlspecialchars($booking['work_description']); ?></h6>
                                                        <small class="text-muted d-block">Provider: <strong><?php echo htmlspecialchars($booking['provider_name']); ?></strong></small>
                                                        <small class="text-muted d-block">Date: <?php echo date('M d, Y', strtotime($booking['service_date'])); ?></small>
                                                    </div>

                                                    <div class="col-md-6 text-md-end">

                                                        <?php if (in_array($booking['status'], ['pending', 'quote_submitted', 'pending'])): ?>
                                                            <form action="process/processCancelBooking.php" method="POST" class="d-inline ms-2" onsubmit="return confirm('Are you sure you want to cancel this booking request?');">
                                                                <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                                                <input type="hidden" name="provider_id" value="<?php echo $booking['provider_id']; ?>">
                                                                <button type="submit" class="btn btn-outline-danger btn-sm fw-bold rounded-pill">
                                                                    Cancel Booking
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>

                                                        <?php if ($bstatus === 'pending'): ?>
                                                            <span class="badge bg-warning text-dark rounded-pill mb-2">Requested</span>
                                                            <div class="fw-bold text-muted small fst-italic">Awaiting Provider Response</div>

                                                        <?php elseif ($bstatus === 'accepted'): ?>
                                                            <span class="badge bg-success text-white rounded-pill mb-2"></span>Accepted</span>
                                                            <div class="fw-bold text-success small">Provider is scheduled — awaiting quote</div>

                                                        <?php elseif ($bstatus === 'quote_submitted'): ?>
                                                            <span class="badge bg-primary text-white rounded-pill mb-2">Quote Received</span>
                                                            <div class="fw-bold small text-dark mb-2">R <?php echo number_format($booking['quoted_price'], 2); ?></div>
                                                            <button class="btn btn-sm text-white fw-bold rounded-pill px-3 shadow-sm"
                                                                    style="background-color: #6f42c1;"
                                                                    data-bs-toggle="modal" data-bs-target="#quoteResponseModal<?php echo $booking['booking_id']; ?>">
                                                                Review Quote
                                                            </button>

                                                        <?php elseif ($bstatus === 'quote_accepted'): ?>
                                                            <span class="badge bg-info text-dark rounded-pill mb-2"> Quote Approved</span>
                                                            <div class="text-muted small mb-2">R <?php echo number_format($booking['quoted_price'], 2); ?> agreed</div>
                                                            <a href="paymentDetails.php?booking_id=<?php echo $booking['booking_id']; ?>" 
                                                                class="btn btn-sm fw-bold rounded-pill px-4 py-2 shadow-sm text-white"
                                                                style="background-color: #7c3aed;">
                                                                Pay Now
                                                            </a>

                                                        <?php elseif ($bstatus === 'payment_held'): ?>
                                                            <span class="badge bg-success text-white rounded-pill mb-2"> Payment Secured</span>
                                                            <div class="text-muted small">Provider is working on your job</div>

                                                        <?php elseif ($bstatus === 'pending_review'): ?>
                                                            <span class="badge bg-warning text-dark rounded-pill mb-2"> Review Required</span>
                                                            <div class="text-muted small mb-2">Provider marked job complete</div>
                                                            <div class="d-flex gap-2 justify-content-md-end flex-wrap">
                                                                <button class="btn btn-sm btn-success fw-bold rounded-pill px-3"
                                                                        data-bs-toggle="modal" data-bs-target="#confirmModal<?php echo $booking['booking_id']; ?>">
                                                                     Satisfied
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-danger fw-bold rounded-pill px-3"
                                                                        data-bs-toggle="modal" data-bs-target="#disputeModal<?php echo $booking['booking_id']; ?>">
                                                                     Dispute
                                                                </button>
                                                            </div>

                                                        <?php elseif ($bstatus === 'disputed'): ?>
                                                            <span class="badge bg-danger text-white rounded-pill mb-2">
                                                                Dispute Active
                                                            </span>
                                                            <div class="fw-bold text-danger small mb-1">Under Admin Review</div>
                                                            <div class="text-muted small fst-italic" style="max-width: 200px; margin-left: auto;">
                                                                Funds are frozen. We will notify you once this is resolved.
                                                            </div>

                                                        <?php elseif ($bstatus === 'completed' && strtolower($booking['payment_status'] ?? '') === 'pending'): ?>
                                                            <span class="badge bg-info text-dark rounded-pill mb-2">Job Completed</span>
                                                            <div class="fw-bold fs-5 text-dark mb-2">R <?php echo number_format($booking['final_price'], 2); ?></div>
                                                            <button class="btn btn-sm btn-light fw-bold rounded-pill px-3 shadow-sm"
                                                                    data-bs-toggle="modal" data-bs-target="#paymentModal<?php echo $booking['booking_id']; ?>">Log Payment</button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Quote Review Modal -->
                                            <?php if ($bstatus === 'quote_submitted'): ?>
                                            <div class="modal fade" id="quoteResponseModal<?php echo $booking['booking_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content border-0 shadow">
                                                        <div class="modal-header border-bottom-0">
                                                            <h5 class="modal-title fw-bold">Review Quote</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body pt-0">
                                                            <p class="small text-muted mb-3">Quote from <strong><?php echo htmlspecialchars($booking['provider_name']); ?></strong>:</p>
                                                            <div class="bg-light p-3 rounded-3 border mb-4">
                                                                <div class="fw-bold fs-4 mb-1" style="color:#7c3aed;">R <?php echo number_format($booking['quoted_price'], 2); ?></div>
                                                                <p class="small text-secondary mb-0"><?php echo nl2br(htmlspecialchars($booking['quote_description'] ?? 'No description provided.')); ?></p>
                                                            </div>
                                                            <div class="d-flex gap-2">
                                                                <form action="process/processQuoteResponse.php" method="POST" class="flex-grow-1">
                                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                                                    <input type="hidden" name="provider_id" value="<?php echo $booking['provider_id']; ?>">
                                                                    <input type="hidden" name="action" value="reject">
                                                                    <button type="submit" class="btn btn-outline-danger fw-bold rounded-pill w-100" onclick="return confirm('Reject this quote? The provider can submit a new one.');">Reject</button>
                                                                </form>
                                                                <form action="process/processQuoteResponse.php" method="POST" class="flex-grow-1">
                                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                                                    <input type="hidden" name="provider_id" value="<?php echo $booking['provider_id']; ?>">
                                                                    <input type="hidden" name="action" value="accept">
                                                                    <button type="submit" class="btn text-white fw-bold rounded-pill w-100 shadow-sm" style="background-color:#7c3aed;">Accept Quote</button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                            <!-- Pay Now Modal -->
                                            <?php if ($bstatus === 'quote_accepted'): ?>
                                            <div class="modal fade" id="payNowModal<?php echo $booking['booking_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content border-0 shadow">
                                                        <div class="modal-header border-bottom-0">
                                                            <h5 class="modal-title fw-bold">Secure Payment</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body pt-0">
                                                            <div class="bg-light p-3 rounded-3 border mb-4">
                                                                <small class="text-muted d-block mb-1">Amount to pay</small>
                                                                <div class="fw-bold fs-3" style="color:#7c3aed;">R <?php echo number_format($booking['quoted_price'], 2); ?></div>
                                                                <small class="text-muted">Funds will be held securely until you confirm the job is done.</small>
                                                            </div>
                                                            
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                            <!-- Confirm Satisfaction Modal -->
                                            <?php if ($bstatus === 'pending_review'): ?>
                                            <div class="modal fade" id="confirmModal<?php echo $booking['booking_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content border-0 shadow">
                                                        <div class="modal-header border-bottom-0">
                                                            <h5 class="modal-title fw-bold">Confirm Job Completion</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body pt-0">
                                                            <p class="small text-muted mb-4">Confirming means you are satisfied with the work done by <strong><?php echo htmlspecialchars($booking['provider_name']); ?></strong>. Funds will be released to them immediately.</p>
                                                            <form action="process/processJobConfirm.php" method="POST">
                                                                <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                                                <input type="hidden" name="provider_id" value="<?php echo $booking['provider_id']; ?>">
                                                                <button type="submit" class="btn btn-success w-100 fw-bold rounded-pill">Yes, Release Payment</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Dispute Modal -->
                                            <div class="modal fade" id="disputeModal<?php echo $booking['booking_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content border-0 shadow">
                                                        <div class="modal-header border-bottom-0">
                                                            <h5 class="modal-title fw-bold text-danger">Raise a Dispute</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body pt-0">
                                                            <p class="small text-muted mb-4">Funds will be frozen and an admin will review the case. Only raise a dispute if you genuinely have an issue with the work.</p>
                                                            <form action="process/processBookingDispute.php" method="POST">
                                                                <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                                                <input type="hidden" name="provider_id" value="<?php echo $booking['provider_id']; ?>">
                                                                <div class="mb-4">
                                                                    <label class="form-label small fw-bold text-muted text-uppercase">Describe the Issue</label>
                                                                    <textarea class="form-control border-secondary-subtle" name="reason" rows="3" required placeholder="What went wrong? Be specific."></textarea>
                                                                </div>
                                                                <button type="submit" class="btn btn-danger w-100 fw-bold rounded-pill">Submit Dispute</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Past Jobs Tab -->
                    <div class="tab-pane fade" id="past" role="tabpanel">
                        <div class="card shadow-sm border-0 rounded-4">
                            <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4">
                                <h5 class="fw-bold mb-0"><?php echo $translations[$lang]['past_hires']; ?></h5>
                            </div>
                            <div class="card-body p-4 pt-0">
                                <?php if (empty($past_bookings)): ?>
                                    <div class="text-center py-4 border rounded-4 bg-light">
                                        <p class="text-muted small mb-0">You haven't had any completed jobs yet.</p>
                                    </div>
                                <?php else: ?>
                                    
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($past_bookings as $booking): ?>
                                            <div class="list-group-item px-0 py-3 border-bottom border-light">
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <div class="d-flex align-items-center">
                                                        <div class="profile-pic-large me-3 flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-size: 1.2rem; background-color: #e9ecef; border-radius: 50%; overflow: hidden;">
                                                            <?php
                                                                $provider_avatar = getUserProfilePicture($conn, $booking['provider_id']);
                                                            ?>
                                                            <?php if (!empty($provider_avatar)): ?>
                                                                <img src="<?php echo htmlspecialchars($provider_avatar); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; display: block; border-radius: 50%;">
                                                            <?php else: ?>
                                                                <span>👤</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($booking['provider_name']); ?></h6>
                                                            <small class="text-muted d-block text-truncate" style="max-width: 200px;"><?php echo htmlspecialchars($booking['work_description']); ?></small>
                                                            
                                                            <?php if (strtolower($booking['status']) === 'declined'): ?>
                                                                <span class="badge bg-light-subtle text-light-emphasis bg-opacity-10 rounded-pill mt-1 px-2">Declined</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="text-end">
                                                        <a href="profile.php?id=<?php echo $booking['provider_id']; ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3 me-2">Re-book</a>
                                                        
                                                        <?php if (strtolower($booking['status']) === 'completed' && !in_array($booking['booking_id'], $reviewed_bookings)): ?>                                                            <button class="btn btn-sm text-white rounded-pill px-3" style="background-color: #6f42c1;" data-bs-toggle="modal" data-bs-target="#reviewModal<?php echo $booking['booking_id']; ?>">Leave Review</button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <?php if (strtolower($booking['status']) === 'completed' && !in_array($booking['booking_id'], $reviewed_bookings)): ?>
                                                <div class="modal fade" id="reviewModal<?php echo $booking['booking_id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog modal-dialog-centered">
                                                        <div class="modal-content border-0 shadow">
                                                            <div class="modal-header border-bottom-0">
                                                                <h5 class="modal-title fw-bold">Rate <?php echo htmlspecialchars($booking['provider_name']); ?></h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body text-center pt-0">
                                                                <p class="text-muted small mb-4">How was your experience with: <strong>"<?php echo htmlspecialchars($booking['work_description']); ?>"</strong>?</p>
                                                                
                                                                <form action="process/processReview.php" method="POST">
                                                                    <input type="hidden" name="provider_id" value="<?php echo $booking['provider_id']; ?>">
                                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                                                    
                                                                    <div class="star-rating mb-3">
                                                                        <input type="radio" id="star5_<?php echo $booking['booking_id']; ?>" name="rating" value="5" required />
                                                                        <label for="star5_<?php echo $booking['booking_id']; ?>">★</label>
                                                                        
                                                                        <input type="radio" id="star4_<?php echo $booking['booking_id']; ?>" name="rating" value="4" />
                                                                        <label for="star4_<?php echo $booking['booking_id']; ?>">★</label>
                                                                        
                                                                        <input type="radio" id="star3_<?php echo $booking['booking_id']; ?>" name="rating" value="3" />
                                                                        <label for="star3_<?php echo $booking['booking_id']; ?>">★</label>
                                                                        
                                                                        <input type="radio" id="star2_<?php echo $booking['booking_id']; ?>" name="rating" value="2" />
                                                                        <label for="star2_<?php echo $booking['booking_id']; ?>">★</label>
                                                                        
                                                                        <input type="radio" id="star1_<?php echo $booking['booking_id']; ?>" name="rating" value="1" />
                                                                        <label for="star1_<?php echo $booking['booking_id']; ?>">★</label>
                                                                    </div>

                                                                    <textarea class="form-control border-secondary-subtle bg-light mb-3" name="comment_text" rows="3" placeholder="How was the quality of work? Would you recommend them?" required></textarea>
                                                                    <button type="submit" class="btn text-white w-100 fw-bold py-2 rounded-pill" style="background-color: #6f42c1;">Submit Review</button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Reviews Tab -->
                    <div class="tab-pane fade" id="reviews" role="tabpanel">
                        <div class="card shadow-sm border-0 rounded-4">
                            <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4">
                                <h5 class="fw-bold mb-0"><?php echo $translations[$lang]['reviews']; ?></h5>
                            </div>
                            <div class="card-body p-4 pt-0">
                                
                                <?php if (empty($my_reviews)): ?>
                                    <div class="text-center py-4 border rounded-4 bg-light">
                                        <p class="text-muted small mb-0">You haven't left any reviews yet.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($my_reviews as $rev): ?>
                                            <div class="list-group-item px-0 py-3 border-0 mb-2">
                                                <div class="d-flex align-items-start">
                                                    <div class="profile-pic-large me-3 flex-shrink-0 d-flex align-items-center justify-content-center overflow-hidden bg-light shadow-sm" style="width: 45px; height: 45px; font-size: 1.2rem; border-radius: 50%;">
                                                        <?php
                                                            $provider_avatar = getUserProfilePicture($conn, $rev['provider_id']);
                                                        ?>
                                                        <?php if (!empty($provider_avatar)): ?>
                                                            <img src="<?php echo htmlspecialchars($provider_avatar); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
                                                        <?php else: ?>
                                                            <span>👤</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="w-100">
                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                            <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($rev['provider_name']); ?></h6>
                                                            <small class="text-muted"><?php echo date('M d, Y', strtotime($rev['created_at'])); ?></small>
                                                        </div>
                                                        <div class="text-warning small mb-2" style="letter-spacing: 1px;">
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

            <!-- this is the rightbar -->
            <div class="col-lg-4">

            <?php include('notificationModal.php'); ?>

                <!-- notification section -->
                <div class="card shadow-sm border-0 rounded-4 mb-4">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0">Notifications</h6>
                        <?php if ($unread_notifications_count > 0): ?>
                            <span class="badge bg-light-subtle text-light-emphasis rounded-circle"><?php echo $unread_notifications_count; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-4 pt-2">
                        <?php if(empty($all_notifications)): ?>
                            <p class="small text-muted mb-3">You have no new notifications.</p>
                        <?php else: 
                            $displayNotif = null;
                            foreach($all_notifications as $n) {
                                if(!$n['is_read']) { $displayNotif = $n; break; }
                            }
                            if(!$displayNotif) $displayNotif = $all_notifications[0];
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

                <!-- favourite provider -->
                <div class="card shadow-sm border-0 rounded-4 mb-4">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4">
                        <h6 class="fw-bold mb-0">Saved Providers</h6>
                    </div>
                    <div class="card-body p-4">
                        <?php if (empty($favorites)): ?>
                            <div class="text-center py-3 text-muted">
                                <p class="small mb-0">You have no saved providers.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($favorites as $fav): ?>
                                <?php $fav_pic = getUserProfilePicture($conn, $fav['provider_id']); ?>
                                <div class="d-flex align-items-center justify-content-between mb-3 pb-3 border-bottom border-light">
                                    <div class="d-flex align-items-center text-truncate pe-2">
                                        <div class="rounded-circle bg-purple-light text-purple d-flex align-items-center justify-content-center me-2 flex-shrink-0" style="width: 35px; height: 35px; background-color: rgba(124, 58, 237, 0.1); overflow:hidden;">
                                            <?php if (!empty($fav_pic)): ?>
                                                <img src="<?php echo htmlspecialchars($fav_pic); ?>" alt="Provider" style="width: 100%; height: 100%; object-fit: cover; display: block;">
                                            <?php else: ?>
                                                👤
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <h6 class="fw-bold mb-0 text-truncate" style="font-size: 0.9rem;"><?php echo htmlspecialchars($fav['provider_name']); ?></h6>
                                            <small class="text-muted" style="font-size: 0.75rem;">Verified Provider</small>
                                        </div>
                                    </div>
                                    <a href="profile.php?id=<?php echo $fav['provider_id']; ?>" class="btn btn-sm btn-outline-secondary py-1 px-2 flex-shrink-0" style="font-size: 0.75rem;">View</a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- quick actions section -->
                <div class="card shadow-sm border-0 rounded-4" style="background-color:rgba(124,58,237,.05);">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3">Quick Actions</h6>
                        <a href="index.php" class="btn w-100 rounded-pill fw-bold py-2 text-white shadow-sm mb-2"
                        style="background-color:#7c3aed;">
                            <?php echo $translations[$lang]['find_service']; ?>
                        </a>
                        <?php if ($reviews_due > 0): ?>
                        <button class="btn w-100 rounded-pill fw-bold py-2 btn-outline-secondary"
                                onclick="switchTab('#past-tab')">
                            Leave a Review (<?php echo $reviews_due; ?>)
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- profile photo feature -->
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
                        <button type="submit" class="btn text-white w-100 fw-bold py-2 rounded-pill" style="background-color: #6f42c1;">Upload Photo</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    

    <!-- account settings -->
    <?php
    $acct_name = $client_profile['name'] ?? $_SESSION['name'] ?? 'Your Account';
    $acct_email = $_SESSION['email'] ?? '';
    $acct_pic = $client_profile['profile_pic'] ?? null;
    $acct_initials = '';

    if (!empty($acct_name) && $acct_name !== 'Your Account') {
        $parts = explode(' ', trim($acct_name));
        $acct_initials = strtoupper(substr($parts[0],0,1) . (isset($parts[1]) ? substr($parts[1],0,1) : ''));
    }
    ?>

    <!-- start of html account settings feature -->
    <div class="modal fade" id="accountSettingsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 430px;">
            <div class="modal-content shadow">

                <div class="modal-header border-0 pb-0 px-4 pt-4">
                    <h6 class="modal-title fw-bold mb-0">Account Settings</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="asm-profile-bar mt-3">
                    <div class="asm-avatar">
                        <?php if (!empty($acct_pic)): ?>
                            <img src="<?php echo htmlspecialchars($acct_pic); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                        <?php else: ?>
                            <?php if (!empty($acct_initials)): ?>
                                <?php echo htmlspecialchars($acct_initials); ?>
                            <?php else: ?>
                                <img src="profilePlaceholder.png" alt="Profile" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p class="mb-0 fw-semibold" style="font-size:14px;">
                            <?php echo htmlspecialchars($acct_name); ?>
                        </p>
                        <p class="mb-0 text-muted" style="font-size:12px;">
                            <?php echo htmlspecialchars($acct_email); ?> &middot; Client
                        </p>
                    </div>
                </div>

                <div class="modal-body px-4 pt-3 pb-0">
                    <form action="process/processAccountUpdate.php" method="POST">

                        <p class="asm-section-label">Profile</p>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted mb-1">Full Name</label>
                            <input type="text" name="full_name" class="form-control"
                                value="<?php echo htmlspecialchars($acct_name !== 'Your Account' ? $acct_name : ''); ?>"
                                placeholder="Your full name">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted mb-1">Email Address</label>
                            <input type="email" name="email" class="form-control"
                                value="<?php echo htmlspecialchars($acct_email); ?>" required>
                        </div>

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

                        <div class="asm-footer">
                            <button type="button" class="btn btn-outline-secondary fw-bold px-4 rounded-pill"
                                    data-bs-dismiss="modal">Cancel</button>
                            <button type="submit"
                                    class="btn text-white fw-bold px-4 rounded-pill shadow-sm"
                                    style="background-color: #7c3aed;">Save Changes</button>
                        </div>

                    </form>
                </div>

            </div>
        </div>
    </div>

    <footer class="container-fluid py-1 px-1 border-bottom bg-white mt-auto">
        <?php include('footer.php'); ?>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>    
    <script src="dashboard.js"></script>
</body>
</html>