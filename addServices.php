<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include('lang.php');
include('config.php');
include('checkAccess.php');

$lang = $_SESSION['lang'] ?? 'en';

// if not logged in, send to login
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = 'addServices.php';
    header("Location: login.php?error=login_required");
    exit();
}

// if a user is logged in as a client, show them a message about how to become a provider instead of the add service form
if ($_SESSION['user_role'] === 'client') {
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Become a Provider | Amandla Skills</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="index.css">
    <link rel="stylesheet" href="shared.css">
</head>
<body class="bg-light d-flex flex-column min-vh-100">

    <header class="container-fluid py-1 px-1 border-bottom bg-white">
        <?php include('navBar.php'); ?>
    </header>

    <!-- client users see this message that will allow them to request to be a service provider -->
    <main class="container my-auto py-5">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5">

                <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5">

                    <div class="text-center mb-4">
                        <h4 class="fw-bold mb-2">Want to list your services?</h4>
                        <p class="text-muted small mb-0">
                            Your account is currently set up as a <strong>client</strong>.
                            To post services and receive bookings, you need a
                            <strong>Service Provider</strong> account.
                        </p>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex align-items-start gap-3 mb-2">
                            <div>
                                <div class="step-num">1</div>
                            </div>
                            <div class="step-line w-100">
                                <p class="fw-semibold mb-0" style="font-size:14px;">Contact support</p>
                                <p class="text-muted mb-0" style="font-size:12px;">Send us a message via email or WhatsApp letting us know you'd like to become a provider.</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-start gap-3 mb-2">
                            <div>
                                <div class="step-num">2</div>
                            </div>
                            <div class="step-line w-100">
                                <p class="fw-semibold mb-0" style="font-size:14px;">We upgrade your account</p>
                                <p class="text-muted mb-0" style="font-size:12px;">Our team will switch your existing account to a Service Provider — no new signup needed.</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-start gap-3">
                            <div>
                                <div class="step-num">3</div>
                            </div>
                            <div class="pt-1 w-100">
                                <p class="fw-semibold mb-0" style="font-size:14px;">Start listing</p>
                                <p class="text-muted mb-0" style="font-size:12px;">Log back in and you'll have full access to post services and manage bookings.</p>
                            </div>
                        </div>
                    </div>

                    <p class="text-muted small fw-semibold text-uppercase mb-2" style="letter-spacing:.06em;">Contact Support</p>

                    <div class="d-flex flex-column gap-2 mb-4">
                        <a href="mailto:support@amandlaskills.co.za" class="contact-card">
                            <div>
                                <p class="fw-bold mb-0" style="font-size:13px;">Email Us</p>
                                <p class="text-muted mb-0" style="font-size:12px;">support@amandlaskills.co.za</p>
                            </div>
                        </a>

                        <a href="https://wa.me/27123456789?text=Hi%2C%20I%20would%20like%20to%20become%20a%20service%20provider%20on%20Amandla%20Skills.%20My%20account%20email%20is%20<?php echo urlencode($_SESSION['email'] ?? ''); ?>" 
                           target="_blank" class="contact-card">
                            <div>
                                <p class="fw-bold mb-0" style="font-size:13px;">WhatsApp</p>
                                <p class="text-muted mb-0" style="font-size:12px;">Message us directly — fastest response</p>
                            </div>
                        </a>
                    </div>

                    <a href="<?php echo isset($_SESSION['user_id']) ? 'clientDashboard.php' : 'index.php'; ?>"
                       class="btn btn-outline-secondary fw-bold rounded-pill w-100">
                        Back to Dashboard
                    </a>

                </div>

            </div>
        </div>
    </main>

    <footer class="container-fluid py-1 px-1 border-bottom bg-white mt-auto">
        <?php include('footer.php'); ?>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
    exit();
}

//--------------------------------------------------------------------------------------------------------------
// only service providers reach this point
if ($_SESSION['user_role'] !== 'service_provider') {
    $_SESSION['redirect_url'] = 'addServices.php';
    header("Location: login.php?error=login_required");
    exit();
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post a Service | Amandla Skills</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="footer.css">
    <link rel="stylesheet" href="index.css">
    <style>
        .asf-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #f0eef9;
            box-shadow: 0 4px 24px rgba(0,0,0,.06);
            overflow: hidden;
        }
        .asf-section-label {
            font-size: 10px; font-weight: 800;
            text-transform: uppercase; letter-spacing: .09em;
            color: #adb5bd;
            display: flex; align-items: center; gap: 8px;
            margin: 0 0 10px;
        }
        .asf-section-label::after {
            content: ''; flex: 1; height: 1px; background: #f5f3ff;
        }
        .form-control:focus, .form-select:focus {
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, .1);
        }

        /* Pill price toggle */
        .price-toggle {
            display: flex;
            background: #f5f3ff;
            border-radius: 50px;
            padding: 3px;
            gap: 2px;
            width: fit-content;
            margin-bottom: 12px;
        }
        .price-toggle .ptab {
            padding: 6px 18px;
            border-radius: 50px;
            font-size: 12px; font-weight: 700;
            border: none; background: transparent;
            color: #7c3aed; cursor: pointer;
            transition: all .15s;
        }
        .price-toggle .ptab.active {
            background: #7c3aed; color: #fff;
            box-shadow: 0 2px 6px rgba(124,58,237,.25);
        }

        /* Drop zone */
        .drop-zone {
            border: 2px dashed #e0d9fb;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: border-color .15s, background .15s;
            background: #faf9ff;
        }
        .drop-zone:hover {
            border-color: #7c3aed;
            background: #f5f3ff;
        }

        /* Submit */
        .btn-publish {
            background-color: #7c3aed;
            color: #fff !important;
            border: none;
            font-weight: 700;
            transition: all .2s;
            box-shadow: 0 4px 12px rgba(124,58,237,.25);
        }
        .btn-publish:hover {
            background-color: #6d28d9;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(124,58,237,.35);
        }

        .char-counter { font-size: 11px; color: #adb5bd; text-align: right; margin-top: 3px; }
        .char-counter.warn { color: #e85d04; }
    </style>
</head>
<body class="bg-light d-flex flex-column min-vh-100">

    <header class="container-fluid py-1 px-1 border-bottom bg-white">
        <?php include('navBar.php'); ?>
    </header>

    <main class="container my-auto py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">

                <div class="asf-card">

                    <!-- header -->
                    <div class="p-4 pb-3 text-center border-bottom" style="border-color: #f5f3ff !important;">
                        <h4 class="fw-bold mb-1">List Your <span style="color:#7c3aed">Skills</span></h4>
                        <p class="text-muted small mb-0">Create a card that community members will see on the home page.</p>
                    </div>

                    <div class="p-4">
                        <form action="process/processAddServices.php" method="POST" enctype="multipart/form-data">

                            <!-- service information form -->
                            <p class="asf-section-label">Service Info</p>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted mb-1">Service Title</label>
                                <input type="text" name="title" class="form-control"
                                       placeholder="e.g., Emergency Solar Geyser Repair" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted mb-1">Category</label>
                                <select name="category" class="form-select" required>
                                    <option value="" disabled selected>Select a category...</option>
                                    <option value="Plumbing">Plumbing</option>
                                    <option value="Electrical">Electrical</option>
                                    <option value="Carpentry & Handyman">Carpentry & Handyman</option>
                                    <option value="Cleaning & Domestic">Cleaning & Domestic</option>
                                    <option value="Mechanic & Auto">Mechanic & Auto</option>
                                    <option value="Gardening & Landscaping">Gardening & Landscaping</option>
                                    <option value="Painting & Decorating">Painting & Decorating</option>
                                    <option value="Catering & Baking">Catering & Baking</option>
                                    <option value="Beauty & Hair">Beauty & Hair</option>
                                    <option value="Tutoring & Education">Tutoring & Education</option>
                                    <option value="IT & Web Support">IT & Web Support</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <!-- description -->
                            <p class="asf-section-label mt-4">Description</p>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted mb-1">What do you offer?</label>
                                <textarea name="description" class="form-control" rows="3"
                                          placeholder="Describe what you offer and why clients should choose you..."
                                          maxlength="300" oninput="asfCount(this)" required></textarea>
                                <p class="char-counter" id="asfCharOut">0 / 300</p>
                            </div>

                            <!-- pricing options: fixed and a range-->
                            <p class="asf-section-label mt-4">Pricing</p>

                            <div class="price-toggle mb-3">
                                <button type="button" class="ptab active" onclick="asfPrice('fixed', this)">Fixed Amount</button>
                                <button type="button" class="ptab" onclick="asfPrice('range', this)">Price Range</button>
                            </div>
                            <input type="hidden" name="price_type" id="priceTypeInput" value="fixed">

                            <div class="input-group">
                                <span class="input-group-text fw-bold" style="background:#f5f3ff;border-color:#e0d9fb;color:#7c3aed;">R</span>
                                <input type="number" name="price_min" class="form-control"
                                       placeholder="Amount (e.g. 500)" required>
                                <span class="input-group-text d-none" id="toLabel" style="background:#fff;">to</span>
                                <input type="number" name="price_max" id="maxPriceInput"
                                       class="form-control d-none" placeholder="Max">
                            </div>

                            <!-- portfolio handling of pictures -->
                            <p class="asf-section-label mt-4">
                                Portfolio Photos
                                <span class="text-muted fw-normal" style="text-transform:none;letter-spacing:0;font-size:10px;">optional</span>
                            </p>

                            <div class="drop-zone mb-1" onclick="document.getElementById('portfolio_images').click()">
                                <div style="font-size:1.5rem;margin-bottom:6px;">🖼️</div>
                                <p class="small text-muted mb-1 fw-semibold">Click to upload photos of your work</p>
                                <p class="text-muted mb-0" style="font-size:11px;">PNG, JPG or WEBP · Hold CTRL to select multiple</p>
                                <input type="file" id="portfolio_images" name="portfolio_images[]"
                                       accept="image/*" multiple style="display:none"
                                       onchange="asfFiles(this)">
                            </div>
                            <p id="asfFileNames" class="mb-0 mt-1" style="font-size:11px;color:#7c3aed;display:none;"></p>

                            <button type="submit" class="btn btn-publish w-100 rounded-pill fw-bold py-2 mt-4">
                                Publish Service Card
                            </button>

                        </form>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <footer class="container-fluid py-1 px-1 border-bottom bg-white mt-auto">
        <?php include('footer.php'); ?>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // character count for the description textarea
        function asfCount(el) {
            const n = el.value.length;
            const out = document.getElementById('asfCharOut');
            out.textContent = n + ' / 300';
            out.classList.toggle('warn', n > 250);
        }

        // toggle between fixed price and price range, showing/hiding the max price input and to label as needed
        function asfPrice(type, btn) {
            document.querySelectorAll('.price-toggle .ptab').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('priceTypeInput').value = type;
            const max = document.getElementById('maxPriceInput');
            const lbl = document.getElementById('toLabel');
            if (type === 'range') {
                max.classList.remove('d-none');
                lbl.classList.remove('d-none');
                max.required = true;
            } else {
                max.classList.add('d-none');
                lbl.classList.add('d-none');
                max.required = false;
                max.value = '';
            }
        }

        // Show selected file names that the user has chosen for upload
        function asfFiles(input) {
            const p = document.getElementById('asfFileNames');
            if (input.files.length) {
                p.style.display = 'block';
                p.textContent = input.files.length + ' file' + (input.files.length > 1 ? 's' : '') + ' selected: '
                    + Array.from(input.files).map(f => f.name).join(', ');
            } else {
                p.style.display = 'none';
            }
        }
    </script>
</body>
</html>