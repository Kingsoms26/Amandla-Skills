<?php
include('config.php');
include('lang.php');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$lang = $_SESSION['lang'] ?? 'en';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | Amandla Skills</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="index.css?v=1.2">
    <link rel="stylesheet" href="shared.css">
    <style>
        
    </style>
</head>
<body class="bg-light d-flex flex-column min-vh-100">

    <?php include('navBar.php'); ?>

    <!-- Hero -->
    <section class="about-hero text-center shadow-sm">
        <div class="container">
            <h1 class="display-5 fw-bolder mb-3">About Amandla Skills</h1>
            <p class="lead opacity-75 mx-auto" style="max-width: 640px;">
                Bridging the gap between local talent and those who need it — making it easier than ever to put yourself out there.
            </p>
        </div>
    </section>

    <!-- Mission -->
    <section class="container my-5">
        <div class="row g-4 align-items-stretch">

            <div class="col-lg-7">
                <div class="card shadow-sm border-0 rounded-4 h-100">
                    <div class="card-body p-4 p-md-5">
                        <h2 class="fw-bold mb-3">Empowering <span class="highlight-text">South African</span> Skills</h2>
                        <p class="text-secondary mb-3" style="line-height: 1.75;">
                            Amandla Skills was built with a clear purpose: to provide a professional stage for every skilled individual in our community. Whether you are a plumber, a developer, or a creative — your talent deserves to be seen.
                        </p>
                        <p class="text-secondary mb-0" style="line-height: 1.75;">
                            We believe that putting yourself out there shouldn't be a hurdle. Our platform is designed to be the simplest way for you to showcase your work and connect directly with clients who need you.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card shadow-sm border-0 rounded-4 h-100 text-white" style="background: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%);">
                    <div class="card-body p-4 p-md-5 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center mb-3">
                                <div class="fw-light fw-bolder rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:48px; height:48px; background:rgba(255,255,255,0.15); font-size:1.6rem;">A.S</div>
                                <div>
                                    <h5 class="fw-bold mb-0">Our Origin</h5>
                                    <small class="opacity-75">Established 2026</small>
                                </div>
                            </div>
                            <p class="mb-0" style="opacity:0.85; line-height:1.7;">
                                Created by <strong>Somila Chabula</strong>, Amandla Skills is the result of a vision to digitise the local skills economy and empower small businesses through technology.
                            </p>
                        </div>
                        <div class="mt-4 pt-3 border-top border-white border-opacity-25 d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-uppercase fw-bold opacity-50" style="font-size:0.7rem; letter-spacing:0.08em;">Powered By</small>
                                <div class="fw-bold">Chabula Capital</div>
                            </div>
                            <a href="index.php" class="btn btn-light fw-bold rounded-pill px-4" style="color:#7c3aed;">Explore Skills</a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- Pillars -->
    <section class="bg-white border-top border-bottom py-5">
        <div class="container">
            <div class="row g-4 text-center">
                <div class="col-md-4">
                    <div class="fs-1 mb-3">&#10024;</div>
                    <h5 class="fw-bold">Simple</h5>
                    <p class="text-muted mb-0">An intuitive interface that removes the complexity of marketing yourself online.</p>
                </div>
                <div class="col-md-4">
                    <div class="fs-1 mb-3">&#9989;</div>
                    <h5 class="fw-bold">Trusted</h5>
                    <p class="text-muted mb-0">Every verified provider has passed a personal interview with our support team before earning a badge.</p>
                </div>
                <div class="col-md-4">
                    <div class="fs-1 mb-3">🇿🇦</div>
                    <h5 class="fw-bold">Local</h5>
                    <p class="text-muted mb-0">Focused entirely on uplifting South African service providers and clients alike.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Verification Tiers -->
    <section class="container my-5" id="verification">

        <div class="text-center mb-5">
            <span class="badge rounded-pill px-3 py-2 mb-3 fw-semibold" style="background-color: rgba(124,58,237,0.1); color:#7c3aed; font-size:0.8rem;">Provider Trust System</span>
            <h2 class="fw-bold">How We Verify Providers</h2>
            <p class="text-muted mx-auto" style="max-width:560px;">Every badge on Amandla Skills is earned, not bought. Our three-tier system ensures clients always know exactly who they're working with.</p>
        </div>

        <div class="row g-4 align-items-stretch mb-4">

            <!-- Verified -->
            <div class="col-lg-4">
                <div class="tier-card tier-verified">
                    <div class="d-flex align-items-center mb-3">
                        <span class="badge rounded-pill px-3 py-2 me-2 fw-bold" style="background-color: rgba(16,185,129,0.1); color:#059669; font-size:0.85rem;">✅ Verified</span>
                        <small class="text-muted fw-semibold">Entry Tier</small>
                    </div>
                    <p class="text-muted small mb-3">For new providers who have demonstrated reliability and passed our quality interview.</p>
                    <ul class="tier-req-list">
                        <li><span style="color:#059669;">&#x2713;</span> 5 completed jobs on platform</li>
                        <li><span style="color:#059669;">&#x2713;</span> 4.0 or higher average rating</li>
                        <li><span style="color:#059669;">&#x2713;</span> Pass interview with support team</li>
                        <li><span style="color:#059669;">&#x2713;</span> Valid SA ID or passport on file</li>
                    </ul>
                </div>
            </div>

            <!-- Verified Pro -->
            <div class="col-lg-4">
                <div class="tier-card tier-verified-pro">
                    <div class="d-flex align-items-center mb-3">
                        <span class="badge rounded-pill px-3 py-2 me-2 fw-bold" style="background-color: rgba(124,58,237,0.1); color:#7c3aed; font-size:0.85rem;">🏅 Verified Pro</span>
                        <small class="text-muted fw-semibold">Pro Tier</small>
                    </div>
                    <p class="text-muted small mb-3">For established providers with a consistent track record of quality work on the platform.</p>
                    <ul class="tier-req-list">
                        <li><span style="color:#7c3aed;">&#x2713;</span> 20 completed jobs on platform</li>
                        <li><span style="color:#7c3aed;">&#x2713;</span> 4.0 or higher average rating</li>
                        <li><span style="color:#7c3aed;">&#x2713;</span> Pass interview with support team</li>
                        <li><span style="color:#7c3aed;">&#x2713;</span> Valid SA ID or passport on file</li>
                    </ul>
                </div>
            </div>

            <!-- Top Pro -->
            <div class="col-lg-4">
                <div class="tier-card tier-top-pro">
                    <div class="d-flex align-items-center mb-3">
                        <span class="badge rounded-pill px-3 py-2 me-2 fw-bold" style="background-color: rgba(245,158,11,0.15); color:#b45309; font-size:0.85rem;">🔷 Top Pro</span>
                        <small class="text-muted fw-semibold">Elite Tier</small>
                    </div>
                    <p class="text-muted small mb-3">Reserved for CIPC-registered businesses and sole traders with an outstanding platform record.</p>
                    <ul class="tier-req-list">
                        <li><span style="color:#b45309;">&#x2713;</span> 30 completed jobs on platform</li>
                        <li><span style="color:#b45309;">&#x2713;</span> 4.0 or higher average rating</li>
                        <li><span style="color:#b45309;">&#x2713;</span> Pass interview with support team</li>
                        <li><span style="color:#b45309;">&#x2713;</span> CIPC registered company or sole trader</li>
                        <li><span style="color:#b45309;">&#x2713;</span> CIPC registration number on file</li>
                    </ul>
                </div>
            </div>

        </div>

        <!-- Universal Requirements -->
        <div class="card border-0 rounded-4 shadow-sm text-white" style="background: linear-gradient(135deg, #1e0a3c 0%, #3b0764 100%);">
            <div class="card-body p-4 p-md-5">
                <div class="row align-items-center g-4">
                    <div class="col-md-4">
                        <small class="text-uppercase fw-bold opacity-50" style="font-size:0.7rem; letter-spacing:0.1em;">Applies to All Tiers</small>
                        <h4 class="fw-bold mt-1 mb-1">Universal Requirements</h4>
                        <p class="small mb-0" style="opacity:0.6;">Every provider must meet these baseline standards, no exceptions.</p>
                    </div>
                    <div class="col-md-8">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <div class="d-flex align-items-center gap-3 rounded-3 p-3" style="background:rgba(255,255,255,0.07);">
                                    <span class="fs-4">1</span>
                                    <span class="small fw-semibold" style="opacity:0.9;">Personal interview with support team</span>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="d-flex align-items-center gap-3 rounded-3 p-3" style="background:rgba(255,255,255,0.07);">
                                    <span class="fs-4">2</span>
                                    <span class="small fw-semibold" style="opacity:0.9;">Minimum 4.0 average rating</span>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="d-flex align-items-center gap-3 rounded-3 p-3" style="background:rgba(255,255,255,0.07);">
                                    <span class="fs-4">3</span>
                                    <span class="small fw-semibold" style="opacity:0.9;">Valid SA ID or passport verified</span>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="d-flex align-items-center gap-3 rounded-3 p-3" style="background:rgba(255,255,255,0.07);">
                                    <span class="fs-4">4</span>
                                    <span class="small fw-semibold" style="opacity:0.9;">No unresolved complaints</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </section>

    <!-- How Verification Works -->
    <section class="bg-white border-top border-bottom py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h4 class="fw-bold">How the Verification Process Works</h4>
                <p class="text-muted mb-0">Fill out the form in your provider dashboard and we handle the rest.</p>
            </div>
            <div class="row g-3 justify-content-center">
                <?php
                $steps = [
                    ['num' => '1', 'title' => 'Fill Out Form', 'desc' => 'Submit your details and target tier from your provider dashboard.'],
                    ['num' => '2', 'title' => 'Support Contacts You', 'desc' => 'Our team reaches out via WhatsApp to schedule your call.'],
                    ['num' => '3', 'title' => 'Interview Call', 'desc' => 'A quick call with support — mandatory for every tier.'],
                    ['num' => '4', 'title' => 'Admin Review', 'desc' => 'We verify your stats, documents, and interview outcome.'],
                    ['num' => '5', 'title' => 'Badge Assigned', 'desc' => 'Your tier badge appears on your profile and in search results.'],
                ];
                foreach ($steps as $step):
                ?>
                <div class="col-sm-6 col-md-4 col-lg-2">
                    <div class="card border-0 rounded-4 shadow-sm h-100 text-center p-3">
                        <div class="mx-auto mb-3 rounded-circle d-flex align-items-center justify-content-center fw-bold text-white" style="width:40px; height:40px; background-color:#7c3aed; font-size:0.9rem;">
                            <?php echo $step['num']; ?>
                        </div>
                        <h6 class="fw-bold mb-1" style="font-size:0.875rem;"><?php echo $step['title']; ?></h6>
                        <p class="text-muted mb-0" style="font-size:0.8rem; line-height:1.5;"><?php echo $step['desc']; ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="container my-5">
        <div class="card border-0 rounded-4 shadow-sm text-white text-center p-5" style="background: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%);">
            <h3 class="fw-bold mb-2">Ready to Get Verified?</h3>
            <p class="mb-4 opacity-75 mx-auto" style="max-width:480px;">Complete your profile, log your first jobs, and apply for verification directly from your provider dashboard when you hit the criteria.</p>
            <div class="d-flex gap-3 justify-content-center flex-wrap">
                <a href="register.php" class="btn btn-light fw-bold rounded-pill px-4" style="color:#7c3aed;">Get Started</a>
                <a href="index.php" class="btn fw-bold rounded-pill px-4" style="border: 1.5px solid rgba(255,255,255,0.5); color:#fff;">Browse Skills</a>
            </div>
        </div>
    </section>

    <?php include('footer.php'); ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script></body>
</html>