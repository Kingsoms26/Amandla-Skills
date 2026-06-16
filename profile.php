<?php
session_start();
include('lang.php');
include('config.php');
include('dbHelper.php');
include_once('verificationBadge.php'); 

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}
$provider_id = intval($_GET['id']);

$stmt = $conn->prepare("
    SELECT u.name, u.email, u.profile_pic, u.created_at, 
           p.id as profile_id, p.display_name, p.phone_number, p.service_location, p.is_verified_pro, p.account_status, p.verification_tier 
    FROM users u 
    JOIN provider_profiles p ON u.id = p.user_id 
    WHERE u.id = ? AND u.role = 'provider'
");
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$profile_result = $stmt->get_result();

if ($profile_result->num_rows === 0) {
    header("Location: index.php");
    exit();
}
$provider = $profile_result->fetch_assoc();
$stmt->close();

$services = [];
$srv_stmt = $conn->prepare("
    SELECT s.id as service_id, s.title, s.description, s.category, p.user_id, s.price_min, s.price_max
    FROM services s 
    JOIN provider_profiles p ON s.provider_profile_id = p.id 
    WHERE p.user_id = ?
");
$srv_stmt->bind_param("i", $provider_id);
$srv_stmt->execute();
$srv_res = $srv_stmt->get_result();
while($s = $srv_res->fetch_assoc()) {
    $services[] = $s;
}
$srv_stmt->close();

$busy_dates_json = json_encode(getProviderBusyDates($conn, $provider_id));

// reviews and ratings for the specific provider
$reviews = [];
$sum_rating = 0;
$rev_stmt = $conn->prepare("
    SELECT r.rating, r.comment_text, r.created_at, u.name AS client_name, u.profile_pic AS client_pic
    FROM reviews r
    JOIN users u ON r.client_id = u.id
    WHERE r.provider_id = ?
    ORDER BY r.created_at DESC
");
$rev_stmt->bind_param("i", $provider_id);
$rev_stmt->execute();
$rev_res = $rev_stmt->get_result();
while($r = $rev_res->fetch_assoc()) {
    $reviews[] = $r;
    $sum_rating += $r['rating'];
}
$rev_stmt->close();

$total_reviews = count($reviews);
$avg_rating = ($total_reviews > 0) ? round($sum_rating / $total_reviews, 1) : 0;

$is_favourited = false;
if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'client') {
    $fav_stmt = $conn->prepare("SELECT 1 FROM favourites WHERE client_id = ? AND provider_id = ?");
    $fav_stmt->bind_param("ii", $_SESSION['user_id'], $provider_id);
    $fav_stmt->execute();
    if ($fav_stmt->get_result()->num_rows > 0) {
        $is_favourited = true;
    }
    $fav_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($provider['display_name'] ?? $provider['name']); ?> | Amandla Skills</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">    <link rel="stylesheet" href="index.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@26.9.2/build/css/intlTelInput.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/airbnb.css">
    <link rel="stylesheet" href="shared.css">
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
                <strong>Success!</strong> Your service was updated successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="mb-4">
            <a href="index.php" class="text-decoration-none text-muted fw-bold">Back to Marketplace</a>
        </div>

        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="hero-banner"></div>
            <div class="card-body px-4 px-md-5 pb-5 text-center text-md-start">
                <div class="d-flex flex-column flex-md-row align-items-center align-items-md-end justify-content-between">
                    
                    <div class="d-flex flex-column flex-md-row align-items-center">
                        <div class="profile-avatar-container shadow-sm me-md-4 mb-3 mb-md-0">
                            <?php if (!empty($provider['profile_pic'])): ?>
                                <img src="<?php echo htmlspecialchars($provider['profile_pic']); ?>" class="profile-avatar" alt="Profile">
                            <?php else: ?>
                                <div class="profile-avatar text-secondary">👤</div>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <h2 class="fw-bold mb-1">
                                <?php echo htmlspecialchars($provider['display_name'] ?? $provider['name']); ?>
                            </h2>
                            <div class="d-flex align-items-center justify-content-center justify-content-md-start gap-2 mb-2">
                                <div class="mb-4 mt-2">
                                    <?php 
                                        $profile_tier = isset($provider['verification_tier']) ? $provider['verification_tier'] : 'none';
                                        echo getVerificationBadge($profile_tier, 'normal'); 
                                    ?>
                                </div>
                            </div>
                            <div class="text-warning fs-5" style="letter-spacing: 1px;">
                                <?php
                                    for($i=1; $i<=5; $i++) {
                                        echo ($i <= round($avg_rating)) ? '★' : '☆';
                                    }
                                ?>
                                <span class="text-dark fw-bold ms-1" style="font-size: 1rem;"><?php echo $avg_rating; ?></span>
                                <span class="text-muted small fs-6 ms-1">(<?php echo $total_reviews; ?> reviews)</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 mt-md-0 d-flex gap-2">
                        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'client'): ?>
                            <a href="process/processFavourite.php?provider_id=<?php echo $provider_id; ?>" class="btn <?php echo $is_favourited ? 'btn-danger' : 'btn-outline-danger'; ?> rounded-pill fw-bold px-4 shadow-sm">
                                <?php echo $is_favourited ? '&#10084;&#65039; Saved' : '&#129293; Save Provider'; ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <h4 class="fw-bold mb-3">Services Offered</h4>
                <div class="row g-3 mb-5">
                    <?php if (empty($services)): ?>
                        <div class="col-12">
                            <div class="card border-0 bg-white rounded-4 p-4 text-center text-muted shadow-sm">
                                <p class="mb-0">This provider hasn't listed any services yet.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach($services as $srv): ?>
                            <?php $portfolio_images = getServiceImages($conn, $srv['service_id']); ?>
                            
                            <div class="col-md-6">
                                <div class="card service-card border border-light shadow-sm rounded-4 h-100 p-4 position-relative" onclick="openServiceModal(event, 'serviceModal<?php echo $srv['service_id']; ?>')">
                                    
                                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $provider_id): ?>
                                        <div class="position-absolute d-flex gap-2" style="top: 15px; right: 15px; z-index: 10;">
                                            <button class="btn btn-sm btn-light border rounded-circle shadow-sm d-flex align-items-center justify-content-center" data-bs-toggle="modal" data-bs-target="#editServiceModal<?php echo $srv['service_id']; ?>" style="width: 32px; height: 32px;" title="Edit Service">✏️</button>
                                            <form action="process/processServiceAction.php" method="POST" class="m-0" onsubmit="return confirm('Are you sure you want to permanently delete this service?');">
                                                <input type="hidden" name="service_id" value="<?php echo $srv['service_id']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn btn-sm btn-light border rounded-circle shadow-sm d-flex align-items-center justify-content-center text-danger" style="width: 32px; height: 32px;" title="Delete Service">🗑️</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>

                                    <h4 class="fw-bold mb-2 pe-5"><?php echo htmlspecialchars($srv['title']); ?></h4>
                                    <p class="small text-muted mb-4 line-clamp-3"><?php echo htmlspecialchars($srv['description']); ?></p>
                                    <div class="fw-bold mb-0 mt-auto text-secondary fs-6">
                                        <?php 
                                            $min_price = number_format($srv['price_min'], 2);
                                            if (!empty($srv['price_max']) && $srv['price_max'] > 0) {
                                                $max_price = number_format($srv['price_max'], 2);
                                                echo "R" . $min_price . " - R" . $max_price;
                                            } else {
                                                echo "R" . $min_price;
                                            }
                                        ?>
                                    </div>
                                    
                                    <div class="mb-3 mt-2">
                                        <span class="badge bg-light text-dark border w-auto align-self-start rounded-pill px-3 me-1"><?php echo htmlspecialchars($srv['category']); ?></span>
                                        <?php 
                                            $card_tier = isset($provider['verification_tier']) ? $provider['verification_tier'] : 'none';
                                            echo getVerificationBadge($card_tier, 'small'); 
                                        ?>
                                    </div>
                                </div>
                            </div>

                            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $provider_id): ?>
                                <div class="modal fade" id="editServiceModal<?php echo $srv['service_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content border-0 shadow-lg rounded-4">
                                            <div class="modal-header border-bottom-0 pb-0">
                                                <h5 class="modal-title fw-bold">Edit Service</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body pt-3">
                                                <form action="process/processServiceAction.php" method="POST" enctype="multipart/form-data">
                                                    <input type="hidden" name="service_id" value="<?php echo $srv['service_id']; ?>">
                                                    <input type="hidden" name="action" value="update">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-bold text-muted text-uppercase"><?php echo $translations[$lang]['service_title']; ?></label>
                                                        <input type="text" class="form-control border-secondary-subtle" name="title" value="<?php echo htmlspecialchars($srv['title']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-bold text-muted text-uppercase"><?php echo $translations[$lang]['service_category']; ?></label>
                                                        <input type="text" class="form-control border-secondary-subtle" name="category" value="<?php echo htmlspecialchars($srv['category']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-bold text-muted text-uppercase"><?php echo $translations[$lang]['service_description']; ?></label>
                                                        <textarea name="description" class="form-control border-secondary-subtle" rows="4" required><?php echo htmlspecialchars($srv['description']); ?></textarea>
                                                    </div>

                                                    <!-- Manage Portfolio Images -->
                                                    <div class="p-3 bg-light rounded-3 border mb-4">
                                                        <h6 class="fw-bold mb-3 small text-uppercase text-muted">Manage Images</h6>
                                                        
                                                        <!-- Existing Images -->
                                                        <?php if (!empty($portfolio_images)): ?>
                                                            <div class="mb-3">
                                                                <label class="small text-dark fw-bold mb-2">Check to Delete:</label>
                                                                <div class="d-flex flex-wrap gap-3">
                                                                    <?php foreach($portfolio_images as $img): ?>
                                                                        <div class="edit-img-container shadow-sm">
                                                                            <img src="<?php echo htmlspecialchars($img); ?>" alt="Portfolio image">
                                                                            <input type="checkbox" class="edit-img-checkbox" name="delete_images[]" value="<?php echo htmlspecialchars($img); ?>">
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>

                                                        <!-- Add New Images -->
                                                        <div>
                                                            <label class="small text-dark fw-bold mb-1">Add New Images (Optional)</label>
                                                            <input type="file" name="new_images[]" multiple accept="image/png, image/jpeg, image/webp" class="form-control border-secondary-subtle small">
                                                        </div>
                                                    </div>

                                                    <button type="submit" class="btn text-white w-100 fw-bold py-2 rounded-pill shadow-sm" style="background-color: #6f42c1;">Save Changes</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php 
                                $modal_srv = $srv;
                                $modal_provider_name = $provider['display_name'] ?? $provider['name'];
                                $modal_profile_pic = $provider['profile_pic'];
                                $modal_portfolio_images = $portfolio_images;
                                include('serviceModal.php'); 
                            ?>

                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <h4 class="fw-bold mb-3">Client Reviews</h4>
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-body p-4">
                        <?php if (empty($reviews)): ?>
                            <div class="text-center py-4 text-muted">
                                <p class="mb-0">No reviews yet for this provider.</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($reviews as $rev): ?>
                                    <div class="list-group-item px-0 py-3 border-bottom border-light <?php echo $rev === end($reviews) ? 'border-0' : ''; ?>">
                                        <div class="d-flex align-items-start">
                                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-3 flex-shrink-0 shadow-sm" style="width: 45px; height: 45px; font-size: 1.2rem;">
                                                <?php if (!empty($rev['client_pic'])): ?>
                                                    <img src="<?php echo htmlspecialchars($rev['client_pic']); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                                <?php else: ?>
                                                    <span>&#128100;</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="w-100">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($rev['client_name']); ?></h6>
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
                                                    "<?php echo nl2br(htmlspecialchars($rev['comment_text'])); ?>"
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

            <div class="col-lg-4">
                <div class="card shadow-sm border-0 rounded-4 sticky-top" style="top: 20px;">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4">
                        <h5 class="fw-bold mb-0">Contact & Info</h5>
                    </div>
                    <div class="card-body p-4">
                        
                        <div class="mb-4">
                            <h6 class="fw-bold text-muted small text-uppercase mb-2">Location</h6>
                            <div class="d-flex align-items-center text-dark">
                                <?php echo htmlspecialchars($provider['service_location'] ?? 'Not specified'); ?>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h6 class="fw-bold text-muted small text-uppercase mb-2">Service Provider Contact Details</h6>
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <div class="fs-6 fw-light me-2">Phone: </div> 
                                    <a href="tel:<?php echo htmlspecialchars($provider['phone_number']); ?>" class="text-decoration-none text-dark fw-bold">
                                        <?php echo htmlspecialchars($provider['phone_number'] ?? 'Not provided'); ?>
                                    </a>
                                </div>
                                <div class="d-flex align-items-center">
                                    <div class="fs-6 fw-light me-2">Email:</div> 
                                    <a href="mailto:<?php echo htmlspecialchars($provider['email']); ?>" class="text-decoration-none text-dark fw-bold text-truncate">
                                        <?php echo htmlspecialchars($provider['email']); ?>
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="bg-light p-3 rounded-3 border border-light text-center">
                                    <p class="small text-muted mb-2">Please log in to view contact details.</p>
                                    <a href="login.php" class="btn btn-sm btn-outline-secondary rounded-pill fw-bold px-4">Log In</a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <hr class="border-light my-4">
                        
                        <div class="text-center">
                            <p class="small text-muted mb-2">Found an issue with this profile?</p>
                            <a href="ReportPage.php?report_id=<?php echo $provider_id; ?>" class="text-danger fw-bold text-decoration-none small">Report User</a>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </main>

    <footer class="container-fluid py-1 px-1 border-bottom bg-white mt-auto">
        <?php include('footer.php'); ?>
    </footer>
    
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@26.9.2/build/js/intlTelInput.min.js"></script>
    
    <script>
    function openServiceModal(event, modalId) {
        // Prevent opening modal if clicking edit/delete buttons
        if (event.target.closest('button') || event.target.closest('a') || event.target.closest('form') || event.target.closest('.edit-img-checkbox') || event.target.closest('.modal')) {
            return; 
        }
        var myModal = new bootstrap.Modal(document.getElementById(modalId));
        myModal.show();
    }

    document.addEventListener('DOMContentLoaded', function() {
        const busyDates = <?php echo $busy_dates_json; ?>;
        document.querySelectorAll('input[id^="datePicker"]').forEach(function(picker) {
            flatpickr(picker, {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                minDate: "today",
                disable: busyDates,
                time_24hr: true
            });
        });

        document.querySelectorAll(".phone-input-box").forEach(function(input) {
            window.intlTelInput(input, {
                initialCountry: "za",
                loadUtils: () => import("https://cdn.jsdelivr.net/npm/intl-tel-input@26.9.2/build/js/utils.js"),
            });
        });
    });
    </script>
</body>
</html>