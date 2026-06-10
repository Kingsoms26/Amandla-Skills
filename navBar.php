<?php
include('config.php');
include('lang.php');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
$lang = $_SESSION['lang'];

// Fetch user data once — used in both mobile and desktop sections
$profile_pic   = null;
$user_name     = '';
$dashboard_url = 'login.php';
if (isset($_SESSION['user_id'])) {
    $user_role     = $_SESSION['user_role'] ?? 'client';
    $dashboard_url = ($user_role === 'admin')
        ? 'adminDashboard.php'
        : (($user_role === 'service_provider') ? 'providerDashboard.php' : 'clientDashboard.php');
    $user_name     = $_SESSION['name'] ?? 'User';
    $nav_user_id   = $_SESSION['user_id'];
    $nav_stmt      = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
    $nav_stmt->bind_param("i", $nav_user_id);
    $nav_stmt->execute();
    $nav_user_data = $nav_stmt->get_result()->fetch_assoc();
    $profile_pic   = $nav_user_data['profile_pic'] ?? null;
    $nav_stmt->close();


    $bell_stmt  = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0");
    $bell_stmt->bind_param("i", $nav_user_id);
    $bell_stmt->execute();
    $bell_count = $bell_stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
    $bell_stmt->close();
}
$first_name = htmlspecialchars(explode(' ', trim($user_name))[0]);
$initial    = strtoupper(substr(trim($user_name), 0, 1));
?>

<link href="navBar.css" rel="stylesheet">

<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container-fluid">

        <!-- branding -->
        <a class="navbar-brand fw-bold" href="index.php">
            Amandla <span class="brand-accent">Skills</span>
        </a>

        <!-- avatar/login sits here so it's always visible -->
        <div class="d-flex d-lg-none align-items-center gap-2 ms-auto me-2">
            <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="login.php" class="btn btn-sm btn-purple-outline rounded-pill fw-semibold px-3">
                    <?php echo $translations[$lang]['login'] ?? 'Login'; ?>
                </a>
            <?php else: ?>

                <!-- Bell icon with notification count -->
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="dropdown">
                        <a href="#" class="nav-bell position-relative" data-bs-toggle="dropdown" data-bs-auto-close="outside" title="Notifications">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2M8 1.918l-.797.161A4 4 0 0 0 4 6c0 .628-.134 2.197-.459 3.742-.16.767-.376 1.566-.663 2.258h10.244c-.287-.692-.502-1.491-.663-2.258C12.134 8.197 12 6.628 12 6a4 4 0 0 0-3.203-3.92zM14.22 12c.223.447.481.801.78 1H1c.299-.199.557-.553.78-1C2.68 10.2 3 6.88 3 6a5 5 0 0 1 10 0c0 .88.32 4.2 1.22 6"/>
                            </svg>
                            <?php if ($bell_count > 0): ?>
                                <span class="bell-badge"><?php echo $bell_count > 9 ? '9+' : $bell_count; ?></span>
                            <?php endif; ?>
                        </a>
                        <?php include('notificationDropdown.php'); ?>
                    </div>
                <?php endif; ?>

                <!-- User dropdown -->
                <div class="dropdown">
                    <a href="#" class="user-toggle dropdown-toggle" data-bs-toggle="dropdown">
                        <?php if (!empty($profile_pic)): ?>
                            <img src="<?php echo htmlspecialchars($profile_pic); ?>"
                                 alt="Profile"
                                 class="nav-avatar"
                                 width="34" height="34">
                        <?php else: ?>
                            <div class="nav-avatar-placeholder"><?php echo $initial; ?></div>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end nav-dropdown mt-2">
                        <li><span class="dropdown-header"><?php echo $first_name; ?></span></li>
                        <?php if ($user_role === 'provider'): ?>
                            <li><a class="dropdown-item" href="profile.php"><?php echo $translations[$lang]['profile'] ?? 'Profile'; ?></a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="<?php echo $dashboard_url; ?>">Dashboard</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item item-danger" href="logout.php"><?php echo $translations[$lang]['logout'] ?? 'Logout'; ?></a></li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

        <!-- HAMBURGER -->
        <button class="navbar-toggler border-0" type="button"
                data-bs-toggle="collapse" data-bs-target="#navbarContent"
                aria-controls="navbarContent" aria-expanded="false">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarContent">

            <!-- Search — full width on mobile, capped on desktop -->
            <form class="search-form d-flex my-2 my-lg-0 mx-lg-4" role="search" action="index.php" method="GET">
                <?php if (isset($_GET['sort'])): ?>
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($_GET['sort']); ?>">
                <?php endif; ?>
                <div class="input-group">
                    <input class="form-control search-input rounded-start-pill ps-3 border-end-0"
                           type="search" name="q"
                           placeholder="<?php echo $translations[$lang]['search'] ?? 'Search services...'; ?>"
                           value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
                    <button class="btn search-btn rounded-end-pill" type="submit">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/>
                        </svg>
                    </button>
                </div>
            </form>

            <!-- Nav links -->
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link fw-semibold" href="about.php">
                        <?php echo $translations[$lang]['about_us'] ?? 'About Us'; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold" href="addServices.php">
                        <?php echo $translations[$lang]['list_your_services'] ?? 'List your services'; ?>
                    </a>
                </li>
            </ul>

            <div class="navbar-right d-flex align-items-center gap-3">

                <!-- inside collapse on ALL screen sizes -->
                <div class="dropdown">
                    <button class="btn btn-sm lang-btn dropdown-toggle rounded-pill" type="button" data-bs-toggle="dropdown">
                        <?php echo ($lang == 'en') ? 'EN' : 'XH'; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end nav-dropdown ms-3">
                        <li><a class="dropdown-item <?php echo ($lang == 'en') ? 'active' : ''; ?>" href="?lang=en">English</a></li>
                        <li><a class="dropdown-item <?php echo ($lang == 'xh') ? 'active' : ''; ?>" href="?lang=xh">IsiXhosa</a></li>
                    </ul>
                </div>
                
                <!-- Bell icon with notification count -->
                 <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="dropdown">
                        <a href="#" class="nav-bell position-relative" data-bs-toggle="dropdown" data-bs-auto-close="outside" title="Notifications">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2M8 1.918l-.797.161A4 4 0 0 0 4 6c0 .628-.134 2.197-.459 3.742-.16.767-.376 1.566-.663 2.258h10.244c-.287-.692-.502-1.491-.663-2.258C12.134 8.197 12 6.628 12 6a4 4 0 0 0-3.203-3.92zM14.22 12c.223.447.481.801.78 1H1c.299-.199.557-.553.78-1C2.68 10.2 3 6.88 3 6a5 5 0 0 1 10 0c0 .88.32 4.2 1.22 6"/>
                            </svg>
                            <?php if ($bell_count > 0): ?>
                                <span class="bell-badge"><?php echo $bell_count > 9 ? '9+' : $bell_count; ?></span>
                            <?php endif; ?>
                        </a>
                        <?php include('notificationDropdown.php'); ?>
                    </div>
                <?php endif; ?>
                
                <div class="d-none d-lg-flex">
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="login.php" class="btn btn-purple-outline rounded-pill fw-semibold px-3">
                            <?php echo $translations[$lang]['login'] ?? 'Login'; ?> 👤
                        </a>
                    <?php else: ?>
                        <div class="dropdown">
                            <a href="#" class="user-toggle dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                                <?php if (!empty($profile_pic)): ?>
                                    <img src="<?php echo htmlspecialchars($profile_pic); ?>"
                                         alt="Profile"
                                         class="nav-avatar"
                                         width="34" height="34">
                                <?php else: ?>
                                    <div class="nav-avatar-placeholder"><?php echo $initial; ?></div>
                                <?php endif; ?>
                                <span class="fw-semibold text-dark d-none d-xl-inline"><?php echo $first_name; ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end nav-dropdown mt-2">
                                <li><span class="dropdown-header"><?php echo $first_name; ?></span></li>
                                <li><a class="dropdown-item" href="<?php echo $dashboard_url; ?>">Dashboard</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item item-danger" href="logout.php"><?php echo $translations[$lang]['logout'] ?? 'Logout'; ?></a></li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div><!-- /collapse -->

    </div>
</nav>