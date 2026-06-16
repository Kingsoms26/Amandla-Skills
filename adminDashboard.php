<?php
session_start();
include('lang.php');
include('config.php');
include('dbHelper.php');
include('checkAccess.php');

// Kick out anyone who isn't an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$admin_name_full = trim($_SESSION['name'] ?? 'Administrator');
$admin_name = explode(' ', $admin_name_full)[0];
$admin_pic = $_SESSION['profile_pic'] ?? '';


// this function tries to find the best phone contact for a user based on their role and available data
function getBestPhoneContact($conn, $user_id, $role) {
    if ($role === 'provider') {
        $stmt = $conn->prepare("SELECT phone_number FROM provider_profiles WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) return $row['phone_number'];
    } elseif ($role === 'client') {
        $stmt = $conn->prepare("SELECT client_phone FROM bookings WHERE client_id = ? AND client_phone IS NOT NULL ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) return $row['client_phone'];
    }
    return 'No phone on file';
}

// fetch an overview of the users, bookings, and disputes for the admin dashboard stats
$user_counts = ['client' => 0, 'provider' => 0, 'admin' => 0];
$res = $conn->query("SELECT role, COUNT(id) as count FROM users GROUP BY role");
while($row = $res->fetch_assoc()) {
    $user_counts[$row['role']] = $row['count'];
}
$total_users = array_sum($user_counts);

$booking_res = $conn->query("SELECT COUNT(id) as count FROM bookings");
$total_bookings = $booking_res->fetch_assoc()['count'] ?? 0;

$dispute_res = $conn->query("SELECT COUNT(id) as count FROM reports WHERE resolution_status != 'resolved'");
$open_disputes = $dispute_res->fetch_assoc()['count'] ?? 0;


// number of users by role for the overview tab
$all_users = [];
try {
    $users_res = $conn->query("SELECT id, name, email, role, account_status, created_at, profile_pic FROM users ORDER BY created_at DESC");
    if($users_res) {
        while($u = $users_res->fetch_assoc()) {
            $all_users[] = $u;
        }
    }
} catch(Exception $e) {
    $users_res = $conn->query("SELECT id, name, email, role, created_at, profile_pic FROM users ORDER BY created_at DESC");
    while($u = $users_res->fetch_assoc()) {
        $u['account_status'] = 'active'; 
        $all_users[] = $u;
    }
}

// fetch disputes with reporter/reported user details for the disputes management tab
$all_disputes = [];
$disp_stmt = $conn->query("
    SELECT r.*, 
           u1.name as reporter_name, u1.email as reporter_email, u1.role as reporter_role, u1.profile_pic as reporter_pic,
           u2.name as reported_name, u2.email as reported_email, u2.role as reported_role, u2.profile_pic as reported_pic
    FROM reports r 
    JOIN users u1 ON r.reporter_id = u1.id 
    JOIN users u2 ON r.reported_id = u2.id 
    ORDER BY r.created_at DESC
");
if ($disp_stmt) {
    while($d = $disp_stmt->fetch_assoc()) {
        $d['reporter_phone'] = getBestPhoneContact($conn, $d['reporter_id'], $d['reporter_role']);
        $d['reported_phone'] = getBestPhoneContact($conn, $d['reported_id'], $d['reported_role']);
        $all_disputes[] = $d;
    }
}

// fetch pending verification requests for the verifications management tab
$unverified_providers = [];
$unv_stmt = $conn->query("
    SELECT vr.*, pp.interview_completed, pp.verification_tier as current_tier, 
           (SELECT COUNT(*) FROM bookings WHERE provider_id = vr.provider_id AND status = 'completed') as job_count,
           (SELECT AVG(rating) FROM reviews WHERE provider_id = vr.provider_id) as avg_rating,
           u.profile_pic
    FROM verification_requests vr
    JOIN provider_profiles pp ON vr.provider_id = pp.user_id
    JOIN users u ON pp.user_id = u.id
    WHERE vr.status IN ('pending', 'interview_scheduled')
    ORDER BY vr.submitted_at ASC
");
if ($unv_stmt) {
    while($p = $unv_stmt->fetch_assoc()) {
        $unverified_providers[] = $p;
    }
}
$pending_verifications_count = count($unverified_providers);


// notifications for the admin (new disputes, pending verifications)
$all_notifications = [];
try { 
    $all_notifications = getUserNotifications($conn, $admin_id); 
} catch (Exception $e) { }

$notifications = array_filter($all_notifications, function($n) { 
    return !$n['is_read']; 
});
$notifications = array_values($notifications); 
$unread_notifications_count = count($notifications);

// fetch recent reviews and services for the content moderation tab
$all_platform_reviews = [];
$rev_stmt = $conn->query("
    SELECT r.*, u1.name as client_name, u2.name as provider_name 
    FROM reviews r 
    JOIN users u1 ON r.client_id = u1.id 
    JOIN users u2 ON r.provider_id = u2.id 
    ORDER BY r.created_at DESC
");
if ($rev_stmt) {
    while($r = $rev_stmt->fetch_assoc()) { $all_platform_reviews[] = $r; }
} else {
    error_log("Reviews query failed: " . $conn->error);
}

$all_platform_services = [];
$serv_stmt = $conn->query("
    SELECT s.*, u.name as provider_name, u.id as user_id 
    FROM services s 
    JOIN provider_profiles pp ON s.provider_profile_id = pp.id 
    JOIN users u ON pp.user_id = u.id 
    ORDER BY s.id DESC
");
if ($serv_stmt) {
    while($s = $serv_stmt->fetch_assoc()) { $all_platform_services[] = $s; }
} else {
    error_log("Services query failed: " . $conn->error);
}

$disp_pay_stmt = $conn->query("
    SELECT b.id, b.quoted_price, b.status, b.work_description, b.created_at, b.client_id, b.provider_id,
        uc.name as client_name, uc.email as client_email, uc.profile_pic as client_pic,
        up.name as provider_name, up.email as provider_email, up.profile_pic as provider_pic
    FROM bookings b
    JOIN users uc ON b.client_id = uc.id
    JOIN users up ON b.provider_id = up.id
    WHERE b.status = 'disputed'
    ORDER BY b.created_at DESC
");
$disputed_payments = [];
if ($disp_pay_stmt) {
    while($dp = $disp_pay_stmt->fetch_assoc()) {
        $dp['client_phone'] = getBestPhoneContact($conn, $dp['client_id'], 'client');
        $dp['provider_phone'] = getBestPhoneContact($conn, $dp['provider_id'], 'provider');
        $disputed_payments[] = $dp;
    }
}

// fetch all platform bookings and calculate financial traffic
$all_platform_bookings = [];
$total_transacted = 0;
$completed_jobs_count = 0;

$bookings_stmt = $conn->query("
    SELECT b.*, 
           uc.name as client_name, uc.email as client_email, 
           up.name as provider_name, up.email as provider_email
    FROM bookings b
    JOIN users uc ON b.client_id = uc.id
    JOIN users up ON b.provider_id = up.id
    ORDER BY b.created_at DESC
");

if ($bookings_stmt) {
    while($b = $bookings_stmt->fetch_assoc()) {
        $all_platform_bookings[] = $b;
        
        // Calculate transacted money and success traffic (only completed/released jobs)
        $status = strtolower($b['status']);
        $payment_status = strtolower($b['payment_status'] ?? '');
        
        if ($status === 'completed' || $payment_status === 'released') {
            // Fallback to final_price if quoted_price isn't set
            $total_transacted += floatval($b['quoted_price'] ?? $b['final_price'] ?? 0);
            $completed_jobs_count++;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Amandla Skills</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">    <link rel="stylesheet" href="LandingPage.css">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        
    </style>
</head>
<body class="bg-light d-flex flex-column min-vh-100">
    
    <header class="container-fluid py-1 px-1 border-bottom bg-white">
        <?php include('navBar.php'); ?>
    </header>

    <main class="container my-5 flex-grow-1">
        
        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'success'): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 rounded-4" role="alert">
                <strong>Success!</strong> The action was completed successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 rounded-4 mb-4 overflow-hidden">
            <div class="card-body p-4 p-md-5 d-flex align-items-center flex-column flex-md-row">
                <div class="profile-pic-large me-md-4 mb-3 mb-md-0 d-flex align-items-center justify-content-center overflow-hidden shadow-sm" style="width: 100px; height: 100px; font-size: 2.5rem; background-color: #f8f9fa; border-radius: 50%;">
                    <?php if (!empty($admin_pic)): ?>
                        <img src="<?php echo htmlspecialchars($admin_pic); ?>" alt="Profile Picture" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <span>&#x1F464;</span>
                    <?php endif; ?>
                </div>
                <div class="flex-grow-1 text-center text-md-start">
                    <div class="d-flex align-items-center justify-content-center justify-content-md-start mb-1">
                        <h3 class="fw-bold mb-0 me-2"><?php echo $translations[$lang]['welcome'] . ' ' . htmlspecialchars($admin_name); ?></h3>
                        <span class="badge rounded-pill text-white" style="background-color: #6f42c1;">System Admin</span>
                    </div>
                    <p class="text-muted mb-0">Here is the status of the Amandla Skills platform.</p>
                </div>
                <div class="mt-3 mt-md-0 ms-md-auto d-flex flex-wrap gap-2 justify-content-center">
                    <button class="btn btn-outline-secondary fw-bold px-4 rounded-pill" data-bs-toggle="modal" data-bs-target="#editProfileModal"><?php echo $translations[$lang]['photo']; ?></button>
                    <button class="btn btn-outline-secondary fw-bold px-4 rounded-pill" data-bs-toggle="modal" data-bs-target="#accountSettingsModal"><?php echo $translations[$lang]['acc_settings']; ?></button>
                    <a href="index.php" class="btn text-white fw-bold px-4 rounded-pill shadow-sm" style="background-color: #6f42c1;"><?php echo $translations[$lang]['view_site']; ?></a>
                </div>
            </div>
        </div>

        <!-- Dashboard overview -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card dashboard-stat-card shadow-sm border-0 rounded-4 bg-white h-100" onclick="switchTab('#overview-tab')">
                    <div class="card-body p-4 d-flex align-items-center">
                        <div>
                            <h4 class="fw-bold mb-0"><?php echo number_format($total_users); ?></h4>
                            <span class="text-muted small">Total Registered Users</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card dashboard-stat-card shadow-sm border-0 rounded-4 bg-white h-100" onclick="switchTab('#overview-tab')">
                    <div class="card-body p-4 d-flex align-items-center">
                        <div>
                            <h4 class="fw-bold mb-0"><?php echo number_format($total_bookings); ?></h4>
                            <span class="text-muted small">Total Platform Bookings</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card dashboard-stat-card shadow-sm border-0 rounded-4 bg-white h-100" onclick="switchTab('#disputes-tab')">
                    <div class="card-body p-4 d-flex align-items-center">
                        <div>
                            <h4 class="fw-bold mb-0"><?php echo $open_disputes; ?></h4>
                            <span class="text-muted small">Open Disputes</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs for different admin functions: platform overview, user management, etc. -->
        <ul class="nav nav-pills mb-4 gap-2" id="adminTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active rounded-pill px-4" id="overview-tab" data-bs-toggle="pill" data-bs-target="#overview" type="button" role="tab">Platform Overview</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill px-4" id="users-tab" data-bs-toggle="pill" data-bs-target="#users" type="button" role="tab">User Management</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill px-4" id="all-bookings-tab" data-bs-toggle="pill" data-bs-target="#all-bookings" type="button" role="tab">
                    All Bookings
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill px-4" id="disputes-tab" data-bs-toggle="pill" data-bs-target="#disputes" type="button" role="tab">Disputes <?php if($open_disputes > 0) echo '<span class="badge bg-danger ms-1">'.$open_disputes.'</span>'; ?></button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill px-4" id="verifications-tab" data-bs-toggle="pill" data-bs-target="#verifications" type="button" role="tab">Verifications <?php if($pending_verifications_count > 0) echo '<span class="badge bg-warning text-dark ms-1">'.$pending_verifications_count.'</span>'; ?></button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill px-4" id="content-tab" data-bs-toggle="pill" data-bs-target="#content" type="button" role="tab">Content Moderation</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill px-4" id="payments-tab" data-bs-toggle="pill" data-bs-target="#payments" type="button" role="tab">
                    Payments
                    <?php
                    $held_count_res = $conn->query("SELECT COUNT(id) as c FROM bookings WHERE payment_status IN ('held','disputed')");
                    $held_count = $held_count_res ? $held_count_res->fetch_assoc()['c'] : 0;
                    if ($held_count > 0) echo '<span class="badge ms-1" style="background-color:#7c3aed;">' . $held_count . '</span>';
                    ?>
                </button>
            </li>
        </ul>

        <div class="row g-4">
            
            <div class="col-lg-8">
                <div class="tab-content" id="adminTabsContent">
                    
                    <!-- platform overview tab  -->
                    <div class="tab-pane fade show active" id="overview" role="tabpanel">
                        <div class="card shadow-sm border-0 rounded-4 mb-4">
                            <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4">
                                <h5 class="fw-bold mb-0">Platform Overview</h5>
                            </div>
                            <div class="card-body p-4 pt-0">
                                <div class="list-group list-group-flush">
                                    <div class="list-group-item px-0 py-3 border-bottom border-light d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <span class="fw-bold">Client Accounts</span>
                                        </div>
                                        <span class="badge bg-secondary rounded-pill fs-6"><?php echo $user_counts['client'] ?? 0; ?></span>
                                    </div>
                                    <div class="list-group-item px-0 py-3 border-bottom border-light d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <span class="fw-bold">Service Providers</span>
                                        </div>
                                        <span class="badge bg-primary rounded-pill fs-6"><?php echo $user_counts['provider'] ?? 0; ?></span>
                                    </div>
                                    <div class="list-group-item px-0 py-3 border-light d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <span class="fw-bold">Administrators</span>
                                        </div>
                                        <span class="badge rounded-pill fs-6" style="background-color: #6f42c1;"><?php echo $user_counts['admin'] ?? 0; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- users tab  -->
                    <div class="tab-pane fade" id="users" role="tabpanel">
                        <div class="card shadow-sm border-0 rounded-4">
                            <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <h5 class="fw-bold mb-0">All Registered Users</h5>
                                <input type="text" id="userSearch" class="form-control border-secondary-subtle rounded-pill w-auto px-3 py-1 small" placeholder="Search users...">
                            </div>
                            <div class="card-body p-0 pt-2">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0" id="usersTable">
                                        <thead class="table-light text-muted small text-uppercase border-top border-bottom">
                                            <tr>
                                                <th class="ps-4 border-0">User</th>
                                                <th class="border-0">Role</th>
                                                <th class="border-0">Status</th>
                                                <th class="text-end pe-4 border-0">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($all_users as $u): ?>
                                                <tr class="user-row border-light">
                                                    <td class="ps-4 py-3 border-light">
                                                        <div class="d-flex align-items-center">
                                                            <div class="rounded-circle bg-secondary bg-opacity-10 d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 40px; height: 40px; font-size:1.2rem;">
                                                                <?php if(!empty($u['profile_pic'])): ?>
                                                                    <img src="<?php echo htmlspecialchars($u['profile_pic']); ?>" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">
                                                                <?php else: ?>
                                                                    <span>👤</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div>
                                                                <div class="fw-bold user-name mb-0 text-dark" style="font-size: 0.95rem;"><?php echo htmlspecialchars($u['name']); ?></div>
                                                                <small class="user-email text-muted d-block text-truncate" style="max-width: 200px;"><?php echo htmlspecialchars($u['email']); ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="border-light">
                                                        <?php 
                                                            $badge_color = 'bg-secondary';
                                                            if($u['role'] == 'admin') $badge_color = 'bg-dark';
                                                            if($u['role'] == 'provider') $badge_color = 'bg-primary';
                                                        ?>
                                                        <span class="badge <?php echo $badge_color; ?> bg-opacity-10 text-dark rounded-pill px-2 text-capitalize border" style="font-size: 0.75rem;"><?php echo $u['role']; ?></span>
                                                    </td>
                                                    <td class="border-light">
                                                        <?php 
                                                            $status_color = 'text-success';
                                                            $status_dot = 'bg-success';
                                                            if($u['account_status'] == 'banned') { $status_color = 'text-danger'; $status_dot = 'bg-danger'; }
                                                            if($u['account_status'] == 'suspended') { $status_color = 'text-warning'; $status_dot = 'bg-warning'; }
                                                        ?>
                                                        <div class="d-flex align-items-center <?php echo $status_color; ?> fw-bold small text-capitalize">
                                                             <?php echo $u['account_status']; ?>
                                                        </div>
                                                    </td>
                                                    <td class="text-end pe-4 border-light">
                                                        <button class="btn btn-sm btn-outline-secondary fw-bold px-3 rounded-pill" data-bs-toggle="modal" data-bs-target="#editUserModal<?php echo $u['id']; ?>">Manage</button>
                                                    </td>
                                                </tr>

                                                <div class="modal fade" id="editUserModal<?php echo $u['id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog modal-dialog-centered">
                                                        <div class="modal-content border-0 shadow-lg rounded-4">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title fw-bold">Manage Account: <?php echo htmlspecialchars($u['name']); ?></h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <form action="process/processAdminUser.php" method="POST">  
                                                                    <input type="hidden" name="action" value="update">
                                                                    <input type="hidden" name="target_user_id" value="<?php echo $u['id']; ?>">
                                                                    <input type="hidden" name="name" value="<?php echo htmlspecialchars($u['name']); ?>">

                                                                    <div class="mb-4">
                                                                        <label class="form-label small fw-bold text-muted text-uppercase">User Role</label>
                                                                        <select class="form-select border-secondary-subtle" name="role" required>
                                                                            <option value="client" <?php if($u['role']=='client') echo 'selected'; ?>>Client</option>
                                                                            <option value="provider" <?php if($u['role']=='provider') echo 'selected'; ?>>Service Provider</option>
                                                                            <option value="admin" <?php if($u['role']=='admin') echo 'selected'; ?>>Administrator</option>
                                                                        </select>
                                                                        <div class="form-text">Convert users between client/provider/admin.</div>
                                                                    </div>

                                                                    <div class="mb-4">
                                                                        <label class="form-label small fw-bold text-muted text-uppercase">Account Status</label>
                                                                        <select class="form-select border-secondary-subtle" name="account_status" required>
                                                                            <option value="active" <?php if($u['account_status']=='active') echo 'selected'; ?>>Active</option>
                                                                            <option value="suspended" <?php if($u['account_status']=='suspended') echo 'selected'; ?>>Suspended</option>
                                                                            <option value="banned" <?php if($u['account_status']=='banned') echo 'selected'; ?>>Banned</option>
                                                                        </select>
                                                                        <div class="form-text">Changing this status will immediately update the user's access level.</div>
                                                                    </div>

                                                                    <div class="d-flex justify-content-between">
                                                                        <button type="submit" name="action" value="delete" class="btn btn-outline-danger rounded-pill px-4" 
                                                                                onclick="return confirm('WARNING: This will permanently delete the user and all their data.');">
                                                                            Delete User
                                                                        </button>
                                                                        <button type="submit" class="btn text-white rounded-pill px-4" style="background-color: #7c3aed;">
                                                                            Apply Status Change
                                                                        </button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- all bookings tab -->
                     <div class="tab-pane fade" id="all-bookings" role="tabpanel">
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="card shadow-sm border-0 rounded-4 bg-purple-light" style="background-color: rgba(124, 58, 237, 0.05);">
                                    <div class="card-body p-4 d-flex align-items-center">
                                        <div class="rounded-circle bg-white text-purple d-flex align-items-center justify-content-center me-3 shadow-sm" style="width: 50px; height: 50px; font-size: 1.5rem; color: #7c3aed;">&#x1F4B5;</div>
                                        <div>
                                            <h4 class="fw-bold mb-0 text-dark">R <?php echo number_format($total_transacted, 2); ?></h4>
                                            <span class="text-muted small">Total Transacted Volume</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card shadow-sm border-0 rounded-4 bg-success bg-opacity-10">
                                    <div class="card-body p-4 d-flex align-items-center">
                                        <div class="rounded-circle bg-white text-success d-flex align-items-center justify-content-center me-3 shadow-sm" style="width: 50px; height: 50px; font-size: 1.5rem;">&#x1F4C8;</div>
                                        <div>
                                            <h4 class="fw-bold mb-0 text-dark"><?php echo $completed_jobs_count; ?> <span class="fs-6 text-muted fw-normal">/ <?php echo count($all_platform_bookings); ?></span></h4>
                                            <span class="text-muted small">Completed Jobs vs Total Requests</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm border-0 rounded-4">
                            <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <h5 class="fw-bold mb-0">Platform Booking History</h5>
                                <input type="text" id="bookingSearch" class="form-control border-secondary-subtle rounded-pill w-auto px-3 py-1 small" placeholder="Search bookings...">
                            </div>
                            <div class="card-body p-0 pt-2">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0" id="bookingsTable">
                                        <thead class="table-light text-muted small text-uppercase border-top border-bottom">
                                            <tr>
                                                <th class="ps-4 border-0">Job Details</th>
                                                <th class="border-0">Client & Provider</th>
                                                <th class="border-0">Amount</th>
                                                <th class="border-0">Status</th>
                                                <th class="text-end pe-4 border-0">Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($all_platform_bookings)): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center py-5 text-muted">
                                                        <div class="fs-1 mb-2">📋</div>
                                                        No bookings found on the platform.
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($all_platform_bookings as $b): ?>
                                                    <tr class="booking-row border-light">
                                                        <td class="ps-4 py-3 border-light">
                                                            <div class="fw-bold text-dark booking-desc text-truncate" style="max-width: 250px; font-size: 0.95rem;"><?php echo htmlspecialchars($b['work_description']); ?></div>
                                                            <small class="text-muted">ID: #<?php echo $b['id']; ?></small>
                                                        </td>
                                                        <td class="border-light">
                                                            <div class="small mb-1 booking-client text-truncate" style="max-width: 150px;"><strong>C:</strong> <?php echo htmlspecialchars($b['client_name']); ?></div>
                                                            <div class="small booking-provider text-truncate" style="max-width: 150px;"><strong>P:</strong> <?php echo htmlspecialchars($b['provider_name']); ?></div>
                                                        </td>
                                                        <td class="border-light fw-bold" style="color: #7c3aed;">
                                                            R <?php echo number_format($b['quoted_price'] ?? $b['final_price'] ?? 0, 2); ?>
                                                        </td>
                                                        <td class="border-light">
                                                            <?php 
                                                            $b_status = strtolower($b['status']);
                                                            $badge_class = 'bg-secondary';
                                                            if ($b_status === 'completed') $badge_class = 'bg-success';
                                                            elseif ($b_status === 'accepted') $badge_class = 'bg-primary';
                                                            elseif ($b_status === 'cancelled' || $b_status === 'declined') $badge_class = 'bg-danger';
                                                            elseif ($b_status === 'pending') $badge_class = 'bg-warning text-dark';
                                                            ?>
                                                            <span class="badge <?php echo $badge_class; ?> bg-opacity-10 text-dark border rounded-pill px-2 text-capitalize" style="font-size: 0.75rem;"><?php echo $b['status']; ?></span>
                                                        </td>
                                                        <td class="text-end pe-4 border-light small text-muted">
                                                            <?php echo date('M d, Y', strtotime($b['created_at'])); ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- disputes tab -->
                    <div class="tab-pane fade" id="disputes" role="tabpanel">
                        <div class="card shadow-sm border-0 rounded-4">
                            <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4">
                                <h5 class="fw-bold mb-0">Platform Disputes</h5>
                            </div>
                            <div class="card-body p-4 pt-0">
                                <?php if (empty($all_disputes)): ?>
                                    <div class="text-center py-4 border rounded-4 bg-light">
                                        <div class="fs-1 mb-2">🎉</div>
                                        <p class="text-muted small mb-0">No disputes logged on the platform!</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush" id="disputesList">
                                        <?php foreach ($all_disputes as $dispute): ?>
                                            <?php $is_open = ($dispute['resolution_status'] !== 'resolved'); ?>
                                            
                                            <div class="list-group-item px-3 py-3 border-bottom interactive-list-item dispute-item rounded mb-2 border <?php echo $is_open ? 'dispute-unresolved shadow-sm' : 'border-light'; ?>" 
                                                data-bs-toggle="modal" data-bs-target="#generalDisputeModal<?php echo $dispute['id']; ?>">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="fw-bold mb-1 <?php echo $is_open ? 'text-dark' : 'text-secondary'; ?>">Issue: <?php echo htmlspecialchars($dispute['reason']); ?></h6>
                                                        <small class="text-muted">Between <strong><?php echo htmlspecialchars($dispute['reporter_name']); ?></strong> and <strong><?php echo htmlspecialchars($dispute['reported_name']); ?></strong></small>
                                                    </div>
                                                    <div class="text-end">
                                                        <?php if ($is_open): ?>
                                                            <span class="badge bg-danger rounded-pill px-3 shadow-sm text-capitalize"><?php echo $dispute['resolution_status']; ?></span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success rounded-pill px-3 text-capitalize shadow-sm">Resolved</span>
                                                        <?php endif; ?>
                                                        <div class="small text-muted mt-1"><?php echo date('M d, Y', strtotime($dispute['created_at'])); ?></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="modal fade" id="generalDisputeModal<?php echo $dispute['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                                    <div class="modal-content border-0 shadow-lg rounded-4">
                                                        <div class="modal-header border-bottom-0 pb-0">
                                                            <h5 class="modal-title fw-bold <?php echo $is_open ? 'text-danger' : 'text-success'; ?>">
                                                                Review Case #<?php echo $dispute['id']; ?>
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body p-4 pt-3">
                                                            <div class="row g-3 mb-4">
                                                                <div class="col-md-6 border-end">
                                                                    <h6 class="text-muted small text-uppercase fw-bold mb-3">Reporting User</h6>
                                                                    <div class="fw-bold fs-5"><?php echo htmlspecialchars($dispute['reporter_name']); ?></div>
                                                                    <span class="badge bg-secondary bg-opacity-10 text-dark rounded-pill border mb-3"><?php echo htmlspecialchars($dispute['reporter_role']); ?></span>
                                                                    <div class="d-flex flex-column gap-2">
                                                                        <a href="mailto:<?php echo htmlspecialchars($dispute['reporter_email']); ?>" class="btn btn-sm btn-outline-secondary rounded-pill text-start">✉️ <?php echo htmlspecialchars($dispute['reporter_email']); ?></a>
                                                                        <a href="tel:<?php echo htmlspecialchars($dispute['reporter_phone']); ?>" class="btn btn-sm btn-outline-secondary rounded-pill text-start">📞 <?php echo htmlspecialchars($dispute['reporter_phone']); ?></a>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6 ps-md-4">
                                                                    <h6 class="text-muted small text-uppercase fw-bold mb-3">Reported Party</h6>
                                                                    <div class="fw-bold fs-5"><?php echo htmlspecialchars($dispute['reported_name']); ?></div>
                                                                    <span class="badge bg-secondary bg-opacity-10 text-dark rounded-pill border mb-3"><?php echo htmlspecialchars($dispute['reported_role']); ?></span>
                                                                    <div class="d-flex flex-column gap-2">
                                                                        <a href="mailto:<?php echo htmlspecialchars($dispute['reported_email']); ?>" class="btn btn-sm btn-outline-secondary rounded-pill text-start">✉️ <?php echo htmlspecialchars($dispute['reported_email']); ?></a>
                                                                        <a href="tel:<?php echo htmlspecialchars($dispute['reported_phone']); ?>" class="btn btn-sm btn-outline-secondary rounded-pill text-start">📞 <?php echo htmlspecialchars($dispute['reported_phone']); ?></a>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="bg-light p-4 rounded-4 border border-light mb-4">
                                                                <h6 class="fw-bold mb-2">Complaint: "<?php echo htmlspecialchars($dispute['reason']); ?>"</h6>
                                                                <p class="text-secondary small mb-0" style="line-height: 1.6;">"<?php echo nl2br(htmlspecialchars($dispute['complaint_text'])); ?>"</p>
                                                            </div>

                                                            <?php if ($is_open): ?>
                                                                <form action="process/processDispute.php" method="POST" class="bg-white border rounded-4 p-4 shadow-sm">
                                                                    <h6 class="fw-bold mb-3">Admin Resolution</h6>
                                                                    <input type="hidden" name="report_id" value="<?php echo $dispute['id']; ?>">
                                                                    <input type="hidden" name="action" value="resolve">
                                                                    <div class="mb-4">
                                                                        <textarea name="admin_notes" class="form-control border-secondary-subtle" rows="3" placeholder="Detail action taken..." required></textarea>
                                                                    </div>
                                                                    <div class="text-end">
                                                                        <button type="submit" class="btn text-white fw-bold rounded-pill px-4 shadow-sm" style="background-color: #6f42c1;">Close Case</button>
                                                                    </div>
                                                                </form>
                                                            <?php else: ?>
                                                                <div class="bg-success bg-opacity-10 p-4 rounded-4 border border-success border-opacity-25">
                                                                    <h6 class="fw-bold text-success mb-2">✓ Case Closed</h6>
                                                                    <p class="small text-muted mb-0"><?php echo nl2br(htmlspecialchars($dispute['admin_notes'])); ?></p>
                                                                </div>
                                                            <?php endif; ?>
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

                    <!-- verification tab -->
                    <div class="tab-pane fade" id="verifications" role="tabpanel">
                        <div class="card shadow-sm border-0 rounded-4">
                            <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4">
                                <h5 class="fw-bold mb-0">Pending Tier Verifications</h5>
                            </div>
                            <div class="card-body p-0 pt-2">
                                <?php if (empty($unverified_providers)): ?>
                                    <div class="text-center py-5">
                                        <h5 class="fw-bold text-muted">The queue is clear!</h5>
                                        <p class="text-muted small">No providers are currently waiting for verification.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0" id="verifiacitionsTable">
                                            <thead class="table-light text-muted small text-uppercase border-top border-bottom">
                                                <tr>
                                                    <th class="ps-4 border-0">Provider Info</th>
                                                    <th class="border-0">Requested Tier</th>
                                                    <th class="border-0">Stats</th>
                                                    <th class="border-0">Submitted</th>
                                                    <th class="text-end pe-4 border-0">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($unverified_providers as $req): ?>
                                                    <tr class="border-light verif-row">
                                                        <td class="ps-4 py-3 border-light">
                                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($req['full_name']); ?></div>
                                                            <small class="text-muted"><?php echo htmlspecialchars($req['email']); ?></small>
                                                        </td>
                                                        <td class="border-light">
                                                            <?php 
                                                            include_once('verificationBadge.php');
                                                            echo getVerificationBadge($req['target_tier'], 'small'); 
                                                            ?>
                                                        </td>
                                                        <td class="border-light">
                                                            <small class="d-block fw-bold <?php echo ($req['job_count'] >= 5) ? 'text-success' : 'text-danger'; ?>"><?php echo $req['job_count']; ?> Jobs</small>
                                                            <small class="fw-bold text-warning">&#x2B50; <?php echo number_format($req['avg_rating'], 1); ?></small>
                                                        </td>
                                                        <td class="border-light small text-muted">
                                                            <?php echo date('M d, Y', strtotime($req['submitted_at'])); ?>
                                                        </td>
                                                        <td class="text-end pe-4 border-light">
                                                            <button class="btn btn-sm btn-outline-secondary fw-bold px-3 rounded-pill" data-bs-toggle="modal" data-bs-target="#reviewModal<?php echo $req['id']; ?>">Review</button>
                                                        </td>
                                                    </tr>

                                                    <!-- Review Modal -->
                                                    <div class="modal fade" id="reviewModal<?php echo $req['id']; ?>" tabindex="-1">
                                                        <div class="modal-dialog modal-lg modal-dialog-centered">
                                                            <div class="modal-content border-0 shadow-lg rounded-4">
                                                                <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                                                                    <h5 class="modal-title fw-bold">Review Application: <?php echo htmlspecialchars($req['full_name']); ?></h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <div class="modal-body p-4">
                                                                    
                                                                    <div class="row g-4 mb-4">
                                                                        <div class="col-md-6 border-end">
                                                                            <h6 class="text-muted small text-uppercase fw-bold mb-3">Contact Details</h6>
                                                                            <div class="mb-2"><strong>Phone:</strong> <?php echo htmlspecialchars($req['phone']); ?></div>
                                                                            <div class="mb-2"><strong>WhatsApp:</strong> <?php echo htmlspecialchars($req['whatsapp']); ?></div>
                                                                            <div class="mb-2"><strong>Message:</strong> <span class="text-muted fst-italic">"<?php echo htmlspecialchars($req['message'] ?: 'No message'); ?>"</span></div>
                                                                        </div>
                                                                        <div class="col-md-6">
                                                                            <h6 class="text-muted small text-uppercase fw-bold mb-3">Business Data</h6>
                                                                            <div class="mb-2"><strong>Target Tier:</strong> <?php echo ucwords(str_replace('_', ' ', $req['target_tier'])); ?></div>
                                                                            <div class="mb-2"><strong>Jobs:</strong> <?php echo $req['job_count']; ?></div>
                                                                            <div class="mb-3"><strong>Rating:</strong> <?php echo number_format($req['avg_rating'], 1); ?></div>
                                                                            
                                                                            <?php if ($req['target_tier'] === 'top_pro'): ?>
                                                                                <div class="bg-warning bg-opacity-10 p-3 rounded-3 border border-warning">
                                                                                    <div class="fw-bold text-dark small mb-1">CIPC Number:</div>
                                                                                    <div class="mb-2 text-dark font-monospace"><?php echo htmlspecialchars($req['cipc_number']); ?></div>
                                                                                    <?php if (!empty($req['cipc_document_path'])): ?>
                                                                                        <a href="<?php echo htmlspecialchars($req['cipc_document_path']); ?>" target="_blank" class="btn btn-sm btn-dark w-100 fw-bold rounded-pill shadow-sm">📄 View COR 14.3 PDF</a>
                                                                                    <?php else: ?>
                                                                                        <div class="text-danger small fw-bold">No document uploaded!</div>
                                                                                    <?php endif; ?>
                                                                                </div>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>

                                                                    <!-- Interview Toggle -->
                                                                    <div class="bg-light p-3 rounded-3 border mb-4 d-flex justify-content-between align-items-center">
                                                                        <div>
                                                                            <div class="fw-bold mb-1">Mandatory Interview Status</div>
                                                                            <small class="text-muted">A support agent must interview the provider before approval.</small>
                                                                        </div>
                                                                        <form action="process/processAdminVerification.php" method="POST" class="m-0">
                                                                            <input type="hidden" name="action" value="toggle_interview">
                                                                            <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                                                            <input type="hidden" name="provider_id" value="<?php echo $req['provider_id']; ?>">
                                                                            <?php if ($req['interview_completed']): ?>
                                                                                <input type="hidden" name="interview_status" value="0">
                                                                                <button type="submit" class="btn btn-success fw-bold rounded-pill px-4">✓ Completed (Undo)</button>
                                                                            <?php else: ?>
                                                                                <input type="hidden" name="interview_status" value="1">
                                                                                <button type="submit" class="btn btn-outline-secondary fw-bold rounded-pill px-4">Mark as Completed</button>
                                                                            <?php endif; ?>
                                                                        </form>
                                                                    </div>

                                                                    <hr>

                                                                    <!-- Resolution Actions -->
                                                                    <div class="row g-3">
                                                                        <div class="col-md-6 border-end pe-md-4">
                                                                            <form action="process/processAdminVerification.php" method="POST">
                                                                                <h6 class="fw-bold text-success mb-3">Approve Provider</h6>
                                                                                <input type="hidden" name="action" value="approve">
                                                                                <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                                                                <input type="hidden" name="provider_id" value="<?php echo $req['provider_id']; ?>">
                                                                                
                                                                                <label class="form-label small fw-bold text-muted text-uppercase">Assign Final Tier</label>
                                                                                <select name="approved_tier" class="form-select border-secondary-subtle mb-3" <?php echo !$req['interview_completed'] ? 'disabled' : ''; ?>>
                                                                                    <option value="verified" <?php echo $req['target_tier'] == 'verified' ? 'selected' : ''; ?>>Verified</option>
                                                                                    <option value="verified_pro" <?php echo $req['target_tier'] == 'verified_pro' ? 'selected' : ''; ?>>Verified Pro</option>
                                                                                    <option value="top_pro" <?php echo $req['target_tier'] == 'top_pro' ? 'selected' : ''; ?>>Top Pro</option>
                                                                                </select>
                                                                                
                                                                                <button type="submit" class="btn btn-success w-100 fw-bold rounded-pill" <?php echo !$req['interview_completed'] ? 'disabled' : ''; ?>>
                                                                                    Approve Application
                                                                                </button>
                                                                                <?php if (!$req['interview_completed']): ?>
                                                                                    <small class="text-danger d-block mt-2 fw-bold text-center">Interview must be completed first.</small>
                                                                                <?php endif; ?>
                                                                            </form>
                                                                        </div>
                                                                        <div class="col-md-6 ps-md-4">
                                                                            <form action="process/processAdminVerification.php" method="POST">
                                                                                <h6 class="fw-bold text-danger mb-3">Reject Application</h6>
                                                                                <input type="hidden" name="action" value="reject">
                                                                                <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                                                                <input type="hidden" name="provider_id" value="<?php echo $req['provider_id']; ?>">
                                                                                
                                                                                <label class="form-label small fw-bold text-muted text-uppercase">Rejection Reason</label>
                                                                                <textarea name="admin_notes" class="form-control border-secondary-subtle mb-3" rows="2" placeholder="Tell the provider why they were rejected..." required></textarea>
                                                                                
                                                                                <button type="submit" class="btn btn-outline-danger w-100 fw-bold rounded-pill" onclick="return confirm('Are you sure you want to reject this application?');">
                                                                                    Reject Application
                                                                                </button>
                                                                            </form>
                                                                        </div>
                                                                    </div>

                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- content moderation tab -->
                    <div class="tab-pane fade" id="content" role="tabpanel">

                        <div class="card shadow-sm border-0 rounded-4 mb-4">
                            <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4">
                                <h5 class="fw-bold mb-0">Platform Reviews</h5>
                            </div>
                            <div class="card-body p-0 pt-2">
                                <?php if (empty($all_platform_reviews)): ?>
                                    <div class="text-center py-4 border rounded-4 bg-light mx-4 mb-4">
                                        <p class="text-muted small mb-0">No reviews on the platform yet.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0" id="reviewsTable">
                                            <thead class="table-light text-muted small text-uppercase border-top border-bottom">
                                                <tr>
                                                    <th class="ps-4 border-0">Client</th>
                                                    <th class="border-0">Provider</th>
                                                    <th class="border-0">Rating</th>
                                                    <th class="border-0">Comment</th>
                                                    <th class="border-0">Date</th>
                                                    <th class="text-end pe-4 border-0">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($all_platform_reviews as $rev): ?>
                                                    <tr class="border-light review-row">
                                                        <td class="ps-4 py-3 border-light fw-bold" style="font-size:0.9rem;"><?php echo htmlspecialchars($rev['client_name']); ?></td>
                                                        <td class="border-light text-muted small"><?php echo htmlspecialchars($rev['provider_name']); ?></td>
                                                        <td class="border-light">
                                                            <span class="badge bg-light text-dark rounded-pill px-2">&#x2B50; <?php echo $rev['rating']; ?>/5</span>
                                                        </td>
                                                        <td class="border-light">
                                                            <small class="text-muted" style="max-width:200px; display:block; overflow:hidden; white-space:nowrap; text-overflow:ellipsis;">
                                                                "<?php echo htmlspecialchars($rev['comment_text']); ?>"
                                                            </small>
                                                        </td>
                                                        <td class="border-light small text-muted"><?php echo date('M d, Y', strtotime($rev['created_at'])); ?></td>
                                                        <td class="text-end pe-4 border-light">
                                                            <button class="btn btn-sm btn-outline-danger fw-bold px-3 rounded-pill"
                                                                data-bs-toggle="modal" data-bs-target="#deleteReviewModal<?php echo $rev['id']; ?>">Delete</button>
                                                        </td>
                                                    </tr>

                                                    <div class="modal fade" id="deleteReviewModal<?php echo $rev['id']; ?>" tabindex="-1">
                                                        <div class="modal-dialog modal-dialog-centered">
                                                            <div class="modal-content border-0 shadow-lg rounded-4">
                                                                <div class="modal-header border-bottom-0 pb-0">
                                                                    <h5 class="modal-title fw-bold text-danger">Delete Review</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <div class="modal-body pt-3">
                                                                    <div class="bg-light p-3 rounded-4 border mb-4">
                                                                        <div class="small text-muted mb-1">Review by <strong><?php echo htmlspecialchars($rev['client_name']); ?></strong> on <strong><?php echo htmlspecialchars($rev['provider_name']); ?></strong></div>
                                                                        <div class="small text-dark">"<?php echo htmlspecialchars($rev['comment_text']); ?>"</div>
                                                                    </div>
                                                                    <form action="process/processAdminDeleteReview.php" method="POST">
                                                                        <input type="hidden" name="review_id" value="<?php echo $rev['id']; ?>">
                                                                        <div class="mb-4">
                                                                            <label class="form-label small fw-bold text-muted text-uppercase">Reason for Deletion</label>
                                                                            <textarea name="reason" class="form-control border-secondary-subtle" rows="3"
                                                                                placeholder="State why this review is being removed. This will be sent to the user." required></textarea>
                                                                        </div>
                                                                        <div class="d-flex justify-content-end gap-2 border-top pt-3">
                                                                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                                                            <button type="submit" class="btn btn-danger fw-bold rounded-pill px-4">Confirm Delete</button>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card shadow-sm border-0 rounded-4">
                            <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4">
                                <h5 class="fw-bold mb-0">Provider Services</h5>
                            </div>
                            <div class="card-body p-0 pt-2">
                                <?php if (empty($all_platform_services)): ?>
                                    <div class="text-center py-4 border rounded-4 bg-light mx-4 mb-4">
                                        <p class="text-muted small mb-0">No services listed on the platform yet.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0" id="servicesTable">
                                            <thead class="table-light text-muted small text-uppercase border-top border-bottom">
                                                <tr>
                                                    <th class="ps-4 border-0">Provider</th>
                                                    <th class="border-0">Service Title</th>
                                                    <th class="border-0">Category</th>
                                                    <th class="border-0">Price</th>
                                                    <th class="border-0">Date Listed</th>
                                                    <th class="text-end pe-4 border-0">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($all_platform_services as $svc): ?>
                                                    <tr class="border-light service-row">
                                                        <td class="ps-4 py-3 border-light fw-bold" style="font-size:0.9rem;"><?php echo htmlspecialchars($svc['provider_name']); ?></td>
                                                        <td class="border-light">
                                                            <span class="text-dark small fw-bold"><?php echo htmlspecialchars($svc['title']); ?></span>
                                                        </td>
                                                        <td class="border-light">
                                                            <span class="badge bg-primary bg-opacity-10 text-dark border rounded-pill small px-2 text-capitalize"><?php echo htmlspecialchars($svc['category']); ?></span>
                                                        </td>
                                                        <td class="border-light small text-muted">
                                                            <?php
                                                                if ($svc['price_type'] === 'fixed') {
                                                                    echo 'R' . number_format($svc['price_min'], 2);
                                                                } else {
                                                                    echo 'R' . number_format($svc['price_min'], 2) . ' – R' . number_format($svc['price_max'], 2);
                                                                }
                                                            ?>
                                                        </td>
                                                        <td class="border-light small text-muted"><?php echo date('M d, Y', strtotime($svc['created_at'])); ?></td>
                                                        <td class="text-end pe-4 border-light">
                                                            <button class="btn btn-sm btn-outline-danger fw-bold px-3 rounded-pill"
                                                                data-bs-toggle="modal" data-bs-target="#deleteServiceModal<?php echo $svc['id']; ?>">Delete</button>
                                                        </td>
                                                    </tr>

                                                    <div class="modal fade" id="deleteServiceModal<?php echo $svc['id']; ?>" tabindex="-1">
                                                        <div class="modal-dialog modal-dialog-centered">
                                                            <div class="modal-content border-0 shadow-lg rounded-4">
                                                                <div class="modal-header border-bottom-0 pb-0">
                                                                    <h5 class="modal-title fw-bold text-danger">Delete Service</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <div class="modal-body pt-3">
                                                                    <div class="bg-light p-3 rounded-4 border mb-4">
                                                                        <div class="small text-muted mb-1">Service by <strong><?php echo htmlspecialchars($svc['provider_name']); ?></strong></div>
                                                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($svc['title']); ?></div>
                                                                        <div class="small text-muted text-capitalize mt-1"><?php echo htmlspecialchars($svc['category']); ?></div>
                                                                    </div>
                                                                    <form action="process/processAdminDeleteService.php" method="POST">
                                                                        <input type="hidden" name="service_id" value="<?php echo $svc['id']; ?>">
                                                                        <input type="hidden" name="provider_user_id" value="<?php echo $svc['user_id']; ?>">
                                                                        <div class="mb-4">
                                                                            <label class="form-label small fw-bold text-muted text-uppercase">Reason for Deletion</label>
                                                                            <textarea name="reason" class="form-control border-secondary-subtle" rows="3"
                                                                                placeholder="State why this service is being removed. This will be sent to the provider." required></textarea>
                                                                        </div>
                                                                        <div class="d-flex justify-content-end gap-2 border-top pt-3">
                                                                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                                                            <button type="submit" class="btn btn-danger fw-bold rounded-pill px-4">Confirm Delete</button>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                    <!-- end of content moderation section -->

                    <!-- payments tab -->
                    <div class="tab-pane fade" id="payments" role="tabpanel">

                        <div class="card shadow-sm border-0 rounded-4 mb-4">
                            <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4">
                                <h5 class="fw-bold mb-0">Funds Currently Held</h5>
                            </div>
                            <div class="card-body p-4 pt-0">
                                <?php
                                $held_stmt = $conn->query("
                                    SELECT b.id, b.quoted_price, b.payment_status, b.status, b.paid_at, b.work_description,
                                        uc.name as client_name, up.name as provider_name
                                    FROM bookings b
                                    JOIN users uc ON b.client_id = uc.id
                                    JOIN users up ON b.provider_id = up.id
                                    WHERE b.payment_status = 'held'
                                    ORDER BY b.paid_at DESC
                                ");
                                $held_payments = $held_stmt ? $held_stmt->fetch_all(MYSQLI_ASSOC) : [];
                                ?>
                                <?php if (empty($held_payments)): ?>
                                    <div class="text-center py-4 border rounded-4 bg-light">
                                        <p class="text-muted small mb-0">No funds currently held.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-borderless align-middle mb-0" id="heldTable">
                                            <thead class="border-bottom">
                                                <tr class="small text-muted text-uppercase">
                                                    <th>Job</th>
                                                    <th>Client / Provider</th>
                                                    <th>Amount</th>
                                                    <th>Status</th>
                                                    <th class="text-end">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($held_payments as $p): ?>
                                                    <tr class="border-bottom border-light held-row">
                                                        <td>
                                                            <div class="fw-bold text-truncate" style="max-width:200px;font-size:0.85rem;"><?php echo htmlspecialchars($p['work_description']); ?></div>
                                                        </td>
                                                        <td class="small">
                                                            C: <?php echo htmlspecialchars($p['client_name']); ?><br>
                                                            P: <?php echo htmlspecialchars($p['provider_name']); ?>
                                                        </td>
                                                        <td class="fw-bold text-purple" style="color:#7c3aed;">R <?php echo number_format($p['quoted_price'], 2); ?></td>
                                                        <td><span class="badge rounded-pill bg-secondary bg-opacity-10 text-dark"><?php echo ucfirst($p['status']); ?></span></td>
                                                        <td class="text-end">
                                                            <form action="process/processAdminRelease.php" method="POST" class="d-inline">
                                                                <input type="hidden" name="booking_id" value="<?php echo $p['id']; ?>">
                                                                <input type="hidden" name="resolution" value="release">
                                                                <button type="submit" class="btn btn-sm text-white fw-bold rounded-pill px-3" style="background-color:#7c3aed;" onclick="return confirm('Release funds to provider?');">
                                                                    Release
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card shadow-sm border-0 rounded-4">
                            <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4">
                                <h5 class="fw-bold mb-0">Payment Disputes</h5>
                            </div>
                            <div class="card-body p-4 pt-0">
                                <?php if (empty($disputed_payments)): ?>
                                    <div class="text-center py-4 border rounded-4 bg-light">
                                        <p class="text-muted small mb-0">No payment disputes open.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush" id="payDisputesList">
                                        <?php foreach ($disputed_payments as $dp): ?>
                                            <div class="list-group-item px-3 py-3 mb-2 border rounded-4 pay-dispute-item shadow-sm interactive-list-item dispute-unresolved" 
                                                data-bs-toggle="modal" data-bs-target="#paymentDisputeModal<?php echo $dp['id']; ?>">
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <div>
                                                        <h6 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($dp['work_description']); ?></h6>
                                                        <small class="text-muted">
                                                            Client: <strong><?php echo htmlspecialchars($dp['client_name']); ?></strong> &nbsp;·&nbsp;
                                                            Provider: <strong><?php echo htmlspecialchars($dp['provider_name']); ?></strong>
                                                        </small>
                                                    </div>
                                                    <div class="text-end">
                                                        <div class="fw-bold" style="color:#7c3aed;">R <?php echo number_format($dp['quoted_price'], 2); ?></div>
                                                        <span class="badge bg-danger rounded-pill px-2 small shadow-sm">Disputed</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="modal fade" id="paymentDisputeModal<?php echo $dp['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                                    <div class="modal-content border-0 shadow-lg rounded-4">
                                                        <div class="modal-header border-bottom-0 pb-0">
                                                            <h5 class="modal-title fw-bold text-danger">Review Payment Dispute #<?php echo $dp['id']; ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body p-4 pt-3">
                                                            <div class="row g-3 mb-4">
                                                                <div class="col-md-6 border-end">
                                                                    <h6 class="text-muted small text-uppercase fw-bold mb-3">Client (Payer)</h6>
                                                                    <div class="fw-bold fs-5 mb-2"><?php echo htmlspecialchars($dp['client_name']); ?></div>
                                                                    <a href="mailto:<?php echo htmlspecialchars($dp['client_email']); ?>" class="btn btn-sm btn-outline-secondary rounded-pill w-100 text-start mb-2">✉️ <?php echo htmlspecialchars($dp['client_email']); ?></a>
                                                                    <a href="tel:<?php echo htmlspecialchars($dp['client_phone']); ?>" class="btn btn-sm btn-outline-secondary rounded-pill w-100 text-start">📞 <?php echo htmlspecialchars($dp['client_phone']); ?></a>
                                                                </div>
                                                                <div class="col-md-6 ps-md-4">
                                                                    <h6 class="text-muted small text-uppercase fw-bold mb-3">Provider (Payee)</h6>
                                                                    <div class="fw-bold fs-5 mb-2"><?php echo htmlspecialchars($dp['provider_name']); ?></div>
                                                                    <a href="mailto:<?php echo htmlspecialchars($dp['provider_email']); ?>" class="btn btn-sm btn-outline-secondary rounded-pill w-100 text-start mb-2">✉️ <?php echo htmlspecialchars($dp['provider_email']); ?></a>
                                                                    <a href="tel:<?php echo htmlspecialchars($dp['provider_phone']); ?>" class="btn btn-sm btn-outline-secondary rounded-pill w-100 text-start">📞 <?php echo htmlspecialchars($dp['provider_phone']); ?></a>
                                                                </div>
                                                            </div>

                                                            <div class="bg-light p-4 rounded-4 border mb-4">
                                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                                    <h6 class="fw-bold mb-0">Job Description</h6>
                                                                    <span class="fw-bold fs-5" style="color: #7c3aed;">Escrow: R <?php echo number_format($dp['quoted_price'], 2); ?></span>
                                                                </div>
                                                                <p class="text-secondary small mb-0">"<?php echo nl2br(htmlspecialchars($dp['work_description'])); ?>"</p>
                                                            </div>

                                                            <form action="process/processAdminRelease.php" method="POST" class="w-100 bg-white border rounded-4 p-4 shadow-sm">
                                                                <h6 class="fw-bold mb-3">Admin Resolution Entry</h6>
                                                                <input type="hidden" name="booking_id" value="<?php echo $dp['id']; ?>">
                                                                <div class="mb-4">
                                                                    <label class="form-label small fw-bold text-muted text-uppercase">Admin Notes</label>
                                                                    <textarea name="admin_notes" class="form-control border-secondary-subtle" rows="3" placeholder="Detail your findings..." required></textarea>
                                                                </div>
                                                                <div class="d-flex justify-content-between">
                                                                    <button type="submit" name="resolution" value="refund" class="btn btn-outline-danger fw-bold rounded-pill px-4">Refund Client</button>
                                                                    <button type="submit" name="resolution" value="release" class="btn text-white fw-bold rounded-pill px-4" style="background-color: #6f42c1;">Release to Provider</button>
                                                                </div>
                                                            </form>
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

                </div>
            </div>

            <!-- right sidebar-->
            <div class="col-lg-4">
                
                <!-- notification section -->
                <div class="card shadow-sm border-0 rounded-4 mb-4">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0">Notifications</h6>
                        <?php if ($unread_notifications_count > 0): ?>
                            <span class="badge bg-light-subtle rounded-circle"><?php echo $unread_notifications_count; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-4 pt-2">
                        <?php if(empty($notifications)): ?>
                            <p class="small text-muted mb-3">You have no new notifications.</p>
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

                <!-- quick actions that allow admin to send a broadcast and view the live site -->
                <div class="card shadow-sm border-0 rounded-4 mb-4" style="background-color: rgba(124, 58, 237, 0.05);">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3">Quick Actions</h6>
                        <button class="btn w-100 rounded-pill fw-bold py-2 mb-2 text-white shadow-sm"
                                style="background-color: #7c3aed;"
                                data-bs-toggle="modal" data-bs-target="#broadcastModal">
                             Send Broadcast
                        </button>
                        <a href="index.php" class="btn w-100 rounded-pill fw-bold py-2 btn-outline-secondary">
                             View Live Site
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- notifications modal -->
    <?php include('notificationModal.php'); ?>

    <!-- this allows user to upload a profile picture -->
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

    <div class="modal fade" id="broadcastModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">Platform Broadcast</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <p class="small text-muted mb-4">This message will be sent to <strong>every</strong> registered user on Amandla Skills.</p>
                    <form action="process/processBroadcast.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted text-uppercase">Announcement Title</label>
                            <input type="text" name="title" class="form-control border-secondary-subtle" placeholder="e.g. Scheduled Maintenance" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-muted text-uppercase">Message Content</label>
                            <textarea name="message" class="form-control border-secondary-subtle" rows="4" placeholder="Type your announcement here..." required></textarea>
                        </div>
                        <button type="submit" class="btn text-white w-100 fw-bold py-2 rounded-pill shadow-sm" style="background-color: #6f42c1;">
                            Send to All Users
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <footer class="container-fluid py-1 px-1 border-bottom bg-white mt-auto">
        <?php include('footer.php'); ?>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>

    <!-- account settings, allows user to change selected information related to their account -->
    <?php
    $acct_name    = $_SESSION['name']        ?? 'Administrator';
    $acct_email   = $_SESSION['email']       ?? '';
    $acct_pic     = $_SESSION['profile_pic'] ?? null;
    $acct_initials = '';
    if (!empty($acct_name)) {
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

                <!-- profile pic -->
                <div class="asm-profile-bar mt-3">
                    <div class="asm-avatar">
                        <?php if (!empty($acct_pic)): ?>
                            <img src="<?php echo htmlspecialchars($acct_pic); ?>"
                                style="width:100%; height:100%; object-fit:cover; border-radius:50%;">
                        <?php else: ?>
                            <img src="profilePlaceholder.png" alt="Profile" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">
                        <?php endif; ?>
                    </div>
                    <div>
                        <p class="mb-0 fw-semibold" style="font-size:14px;">
                            <?php echo htmlspecialchars($acct_name); ?>
                        </p>
                        <p class="mb-0" style="font-size:12px;">
                            <span class="badge rounded-pill text-white" style="background:#7c3aed; font-size:10px;">System Admin</span>
                        </p>
                    </div>
                </div>

                <div class="modal-body px-4 pt-3 pb-0">
                    <form action="process/processAccountUpdate.php" method="POST">

                        <p class="asm-section-label">Profile</p>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted mb-1">Display Name</label>
                            <input type="text" name="full_name" class="form-control"
                                value="<?php echo htmlspecialchars($acct_name); ?>"
                                placeholder="Your display name">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted mb-1 d-flex justify-content-between">
                                Email Address
                                <span class="text-muted fw-normal" style="font-size:10px;">Read-only. Contact dev to change</span>
                            </label>
                            <input type="email" class="form-control" 
                                value="<?php echo htmlspecialchars($acct_email); ?>"
                                readonly>
                            <!-- Not submitted — intentionally excluded from POST -->
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
                            <button type="button" class="btn btn-outline-secondary fw-bold px-4 rounded-pill" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn text-white fw-bold px-4 rounded-pill shadow-sm" style="background-color: #7c3aed;">Save Changes</button>
                        </div>

                    </form>
                </div>

            </div>
        </div>
    </div>

    <script src="dashboard.js"></script>
</body>
</html>