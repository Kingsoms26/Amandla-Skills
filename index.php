<?php
session_start();
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}
$lang = $_SESSION['lang'];

include('config.php');
include('dbHelper.php');
include_once('verificationBadge.php');
include('checkAccess.php');

$user_favourites = [];
if (isset($_SESSION['user_id'])) {
    $fav_stmt = $conn->prepare("SELECT provider_id FROM favourites WHERE client_id = ?");
    $fav_stmt->bind_param("i", $_SESSION['user_id']);
    $fav_stmt->execute();
    $fav_result = $fav_stmt->get_result();
    while($f = $fav_result->fetch_assoc()){
        $user_favourites[] = $f['provider_id'];
    }
    $fav_stmt->close();
}
?>

<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Amandla Skills | South Africa's Skills Hub</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
        <link rel="stylesheet" href="index.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@26.9.2/build/css/intlTelInput.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
        <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/airbnb.css">
        <style>
            .iti { width: 100%; display: block; }
            .provider-name-link { transition: color 0.2s; color: #212529; }
            .provider-name-link:hover { color: #7c3aed; text-decoration: underline !important; }
            .flatpickr-calendar {
                z-index: 99999 !important;
            }
        </style>
    </head>
    <body class="bg-light d-flex flex-column min-vh-100">
        <header class="container-fluid py-1 px-1 border-bottom bg-white">
            <?php include('navBar.php'); ?>
        </header>

        <main class="flex-grow-1">
            <?php if (isset($_GET['msg']) && $_GET['msg'] == 'report_submitted'): ?>
                <div class="container mt-4">
                    <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert" style="background-color: #d1e7dd;">
                        <h5 class="alert-heading fw-bold mb-1">Report Successfully Submitted</h5>
                        <p class="mb-0 small">Thank you for letting us know. Our team is reviewing the issue. You can check your email or your <strong>Dashboard</strong> for future updates regarding this report.</p>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- search and sort feature -->
            <div class="container mt-4">
                <div class="d-flex justify-content-left align-items-center mb-2">
                    <?php
                    $current_sort = $_GET['sort'] ?? 'newest';
                    $search_query = trim($_GET['q'] ?? '');
                    $search_param = $search_query ? '&q=' . urlencode($search_query) : '';

                    $sort_labels = [
                        'newest' => 'Newest First',
                        'highest_rated' => 'Highest Rated',
                        'verified' => 'Verified Only'
                    ];
                    $active_label = $sort_labels[$current_sort] ?? 'Newest First';
                    ?>

                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle btn-sm fw-bold bg-white" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="text-muted fw-normal me-1">Sort:</span> <?php echo $active_label; ?>
                        </button>
                        <ul class="dropdown-menu shadow">
                            <li><a class="dropdown-item <?php echo $current_sort == 'highest_rated' ? 'active' : ''; ?>" href="?sort=highest_rated<?php echo $search_param; ?>"><?php echo $translations[$lang]['highest_rated'] ?? 'Highest Rated'; ?></a></li>
                            <li><a class="dropdown-item <?php echo $current_sort == 'newest' ? 'active' : ''; ?>" href="?sort=newest<?php echo $search_param; ?>"><?php echo $translations[$lang]['newest_first'] ?? 'Newest First'; ?></a></li>
                            <li><a class="dropdown-item <?php echo $current_sort == 'verified' ? 'active' : ''; ?>" href="?sort=verified<?php echo $search_param; ?>"><?php echo $translations[$lang]['verified_only'] ?? 'Verified Only'; ?></a></li>
                        </ul>
                    </div>

                    <?php if(!empty($search_query)): ?>
                        <div class="ms-3 d-flex align-items-center">
                            <span class="small text-muted me-2">Search:</span>
                            <span class="badge bg-white text-purple rounded-pill px-3 py-2 border shadow-sm" style="color: #6f42c1;">
                                "<?php echo htmlspecialchars($search_query); ?>"
                                <a href="index.php?sort=<?php echo $current_sort; ?>" class="text-decoration-none ms-2 fw-bold" style="color: #6f42c1;">✖</a>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>


            <!-- this is the main section where all the services are displayed -->
            <div class="container my-4">
                <div class="row g-4">

                    <?php
                    $all_services = getLandingPageServices($conn, $search_query, $current_sort);

                    if (!empty($all_services)) {
                        foreach ($all_services as $row) {
                            $portfolio_images = getServiceImages($conn, $row['service_id']);
                            $is_favourited = in_array($row['user_id'], $user_favourites);
                            $busy_dates_json = json_encode(getProviderBusyDates($conn, $row['user_id']));
                    ?>
                    
                    <div class="col-md-4">
                        <div class="card h-100 skill-card service-card shadow-sm border-0 position-relative" style="cursor: pointer;" onclick="openServiceModal(event, 'serviceModal<?php echo $row['service_id']; ?>')">
                            
                            <?php if (!empty($portfolio_images)): ?>
                                <img src="<?php echo htmlspecialchars($portfolio_images[0]); ?>" class="card-img-top service-card-img" alt="Service Image">
                            <?php else: ?>
                                <div class="card-img-top service-card-img bg-secondary bg-opacity-10 d-flex align-items-center justify-content-center">
                                    <span class="text-muted small">No Image Uploaded</span>
                                </div>
                            <?php endif; ?>

                            <div class="position-absolute m-3" style="top: 0; right: 0; z-index: 50;">
                                <div class="dropdown">
                                    <button class="btn btn-light rounded-circle shadow-sm p-0 d-flex align-items-center justify-content-center" 
                                            type="button" data-bs-toggle="dropdown" data-bs-boundary="window" aria-expanded="false" 
                                            style="width: 35px; height: 35px; line-height: 1;">
                                        <span class="text-dark fs-5" style="pointer-events: none;">&#8942;</span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow">
                                        <li><a class="dropdown-item text-danger" href="ReportPage.php?report_id=<?php echo $row['user_id']; ?>">Report User</a></li>
                                    </ul>
                                </div>
                            </div>

                            <div class="card-body d-flex flex-column p-4">
                                <h3 class="card-title pt-1 fw-bolder text-dark mb-3">
                                    <?php echo htmlspecialchars($row['title']); ?>
                                </h3>

                                <div class="fw-bold mb-0 mt-auto text-secondary pb-2 fs-6">
                                    <?php 
                                        $min_price = number_format($row['price_min'], 2);
                                        
                                        if (!empty($row['price_max']) && $row['price_max'] > 0) {
                                            $max_price = number_format($row['price_max'], 2);
                                            echo "R" . $min_price . " - R" . $max_price;
                                        } else {
                                            echo "R" . $min_price;
                                        }
                                    ?>
                                </div>

                                <div class="d-flex align-items-center mb-3">
                                    <div class="profile-pic-large me-2 overflow-hidden bg-light d-flex align-items-center justify-content-center shadow-sm" style="width: 45px; height: 45px; border-radius: 50%; font-size: 1.2rem;">
                                        <?php if (!empty($row['profile_pic'])): ?>
                                            <img src="<?php echo htmlspecialchars($row['profile_pic']); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <span class="text-secondary">&#128100;</span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <a href="profile.php?id=<?php echo $row['user_id']; ?>" class="text-decoration-none provider-name-link">
                                            <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($row['name']); ?></h6>
                                        </a>
                                        <div class="text-warning" style="font-size: 0.85rem;">
                                            <?php 
                                                $display_rating = isset($row['avg_rating']) && $row['avg_rating'] > 0 ? number_format($row['avg_rating'], 1) : 'No rating';
                                            ?>
                                            ★ <?php echo $display_rating; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-2 text-muted small">
                                    <span class="fw-semibold">Service Location:</span> 
                                    <span class="text-dark"><?php echo htmlspecialchars($row['service_location'] ?? 'Location not specified'); ?></span>
                                </div>

                                <p class="card-text text-secondary mb-4 flex-grow-1 service-desc-preview">
                                    <?php echo htmlspecialchars($row['description']); ?>
                                </p>

                                <div class="mb-4">
                                    <span class="badge rounded-pill bg-info-subtle text-info-emphasis me-1"><?php echo htmlspecialchars($row['category']); ?></span>
                                    <?php 
                                        $card_tier = isset($row['verification_tier']) ? $row['verification_tier'] : 'none';
                                        echo getVerificationBadge($card_tier, 'small'); 
                                    ?>
                                </div>

                                <a href="profile.php?id=<?php echo $row['user_id']; ?>" class="btn fw-bold w-100 btn-outline-secondary">
                                    <?php echo $translations[$lang]['view_profile'] ?? 'View Profile'; ?>
                                </a>
                            </div>
                        </div>
                    </div>

                    <?php 
                        $modal_srv = $row;
                        $modal_provider_name = $row['name'];
                        $modal_profile_pic = $row['profile_pic']; 
                        $modal_portfolio_images = $portfolio_images;
                        include('serviceModal.php'); 
                    ?>

                    <?php 
                        } 
                    } else {
                        echo "<div class='col-12 text-center mt-5 text-muted'>
                                <h5>No services found matching your search.</h5>
                                <a href='index.php' class='btn btn-outline-secondary rounded-pill mt-2'>Clear Search</a>
                              </div>";
                    }
                    ?>

                </div>
            </div>
        </main>

        <footer class="container-fluid py-1 px-1 border-bottom bg-white mt-auto">
            <?php include('footer.php'); ?>
        </footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
        <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@26.9.2/build/js/intlTelInput.min.js"></script>
        
       <script>
        // service card opener
        function openServiceModal(event, modalId) {
            if (event.target.closest('button') || event.target.closest('a') || event.target.closest('.dropdown')) {
                return; 
            }
            var modalElement = document.getElementById(modalId);
            var myModal = new bootstrap.Modal(modalElement);
            myModal.show();
        }

        // flatpickr initialization for the calendar inputs in the booking modal
        document.addEventListener('DOMContentLoaded', function() {
            flatpickr('input[id^="datePicker"]', {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                minDate: "today",
                time_24hr: true,
                static: true,
                monthSelectorType: "static",
                animate: true
            });
        });

        // phone inpur logic
        document.querySelectorAll(".phone-input-box").forEach(function(input) {
            window.intlTelInput(input, {
                initialCountry: "za",
                loadUtils: () => import("https://cdn.jsdelivr.net/npm/intl-tel-input@26.9.2/build/js/utils.js"),
            });
        });
        </script>
    </body>
</html>