<div class="modal fade" id="serviceModal<?php echo $modal_srv['service_id']; ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content border-0 shadow-lg">
            
            <div class="modal-header sticky-modal-header d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="modal-title fw-bold mb-0"><?php echo htmlspecialchars($modal_srv['title']); ?></h4>
                    <small class="text-muted"><?php echo htmlspecialchars($modal_srv['category']); ?> • By <?php echo htmlspecialchars($modal_provider_name); ?></small>
                </div>
                
                <div class="d-flex align-items-center gap-2">
                    <a href="process/processFavorite.php?provider_id=<?php echo $modal_srv['user_id']; ?>" style="text-decoration: none;">
                        <button class="btn <?php echo $is_favourited ? 'btn-danger' : 'btn-outline-danger'; ?> d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; border-radius: 50%;">
                            &hearts;
                        </button>
                    </a>
                    <button type="button" class="btn-close ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>

            <div class="modal-body p-3 pb-3">
                
                <ul class="nav nav-tabs mb-4" id="modalTab<?php echo $modal_srv['service_id']; ?>" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-bold" data-bs-toggle="tab" data-bs-target="#details<?php echo $modal_srv['service_id']; ?>" type="button" role="tab">Service Details</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold text-purple" data-bs-toggle="tab" data-bs-target="#book<?php echo $modal_srv['service_id']; ?>" type="button" role="tab" style="color: #6f42c1;">Book Provider</button>
                    </li>
                </ul>

                <div class="tab-content mb-5">
                    
                    <div class="tab-pane fade show active" id="details<?php echo $modal_srv['service_id']; ?>" role="tabpanel">
                        <div id="portfolioCarousel<?php echo $modal_srv['service_id']; ?>" class="carousel slide mb-4 rounded overflow-hidden shadow-sm" data-bs-ride="carousel">
                            <div class="carousel-inner">
                                <?php if (!empty($modal_portfolio_images)): ?>
                                    <?php foreach ($modal_portfolio_images as $index => $img_path): ?>
                                        <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                            <img src="<?php echo htmlspecialchars($img_path); ?>" class="d-block w-100" style="height: 400px; object-fit: cover;" alt="Portfolio Image">
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="carousel-item active">
                                        <div style="height: 400px; background-color: #e9ecef; display:flex; align-items:center; justify-content:center;">
                                            <span class="text-muted">No Portfolio Images Uploaded</span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (count($modal_portfolio_images) > 1): ?>
                                <button class="carousel-control-prev" type="button" data-bs-target="#portfolioCarousel<?php echo $modal_srv['service_id']; ?>" data-bs-slide="prev"><span class="carousel-control-prev-icon" aria-hidden="true"></span></button>
                                <button class="carousel-control-next" type="button" data-bs-target="#portfolioCarousel<?php echo $modal_srv['service_id']; ?>" data-bs-slide="next"><span class="carousel-control-next-icon" aria-hidden="true"></span></button>
                            <?php endif; ?>
                        </div>

                        <!-- Price Estimate Block -->
                        <div class="mb-4">
                            <h5 class="fw-bold">Price Estimate</h5>
                            <div class="fs-5 fw-bold" style="color: #7c3aed;">
                                <?php 
                                    // 1. Check if it's a fixed price OR if the max price is 0/empty
                                    if ($modal_srv['price_type'] === 'fixed' || empty($modal_srv['price_max']) || $modal_srv['price_max'] == 0) {
                                        echo 'R ' . number_format($modal_srv['price_min'], 2);
                                    } else {
                                        // 2. Only show range if price_max is actually greater than 0
                                        echo 'R ' . number_format($modal_srv['price_min'], 2) . ' - R ' . number_format($modal_srv['price_max'], 2);
                                    }
                                ?>
                            </div>
                        </div>

                        <h5 class="fw-bold">About this service</h5>
                        <p><?php echo nl2br(htmlspecialchars($modal_srv['description'])); ?></p>
                        
                        <hr>
                        
                        <h5 class="fw-bold mt-4">Provider Details</h5>
                        <div class="d-flex align-items-center mt-3">
                            <div class="profile-pic-large me-3 overflow-hidden bg-light d-flex align-items-center justify-content-center shadow-sm" style="width: 60px; height: 60px; border-radius: 50%; font-size: 1.5rem;">
                                <?php if (!empty($modal_profile_pic)): ?>
                                    <img src="<?php echo htmlspecialchars($modal_profile_pic); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <span class="text-secondary">👤</span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <a href="profile.php?id=<?php echo $modal_srv['user_id']; ?>" class="text-decoration-none provider-name-link">
                                    <h5 class="mb-1 fw-bold"><?php echo htmlspecialchars($modal_provider_name); ?></h5>
                                </a>
                                
                                <div class="d-flex flex-wrap gap-1 mt-1 mb-1">
                                    <?php if (!empty($modal_srv['category'])): ?>
                                        <span class="badge rounded-pill bg-light text-dark border border-secondary-subtle fw-normal px-3 py-1">
                                            🏷️ <?php echo htmlspecialchars($modal_srv['category']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Dynamic Verification Badge-->
                                <div class="mt-2">
                                    <?php 
                                    include_once('verificationBadge.php'); 
                                    $tier_to_show = isset($modal_srv['verification_tier']) ? $modal_srv['verification_tier'] : (isset($provider['verification_tier']) ? $provider['verification_tier'] : 'none');
                                    echo getVerificationBadge($tier_to_show, 'small'); 
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2 text-muted small">
                        <span class="fw-semibold">Service Location:</span> 
                        <span class="text-dark"><?php echo htmlspecialchars($row['service_location'] ?? 'Location not specified'); ?></span>
                    </div>
                    
                    <div class="tab-pane fade" id="book<?php echo $modal_srv['service_id']; ?>" role="tabpanel">
                        <div class="bg-light p-4 rounded-4 border border-light">
                            <h5 class="fw-bold mb-3">Request a Booking</h5>
                            <p class="small text-muted mb-4">Fill out the details below. The provider will review your request and send back a final quote.</p>
                            
                            <form action="process/processBooking.php" method="POST">
                                <input type="hidden" name="provider_id" value="<?php echo $modal_srv['user_id']; ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Preferred Date & Time</label>
                                    <input type="text" class="form-control border-secondary-subtle bg-white" name="service_date" id="datePicker<?php echo $modal_srv['service_id']; ?>" placeholder="Select Date & Time..." required readonly>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label small fw-bold text-muted text-uppercase">Your Phone Number</label>
                                        <input type="tel" class="form-control border-secondary-subtle phone-input-box" name="client_phone" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold text-muted text-uppercase">Street Address</label>
                                        <input type="text" class="form-control border-secondary-subtle" name="street_address" placeholder="e.g. 123 Jan Smuts Ave" required>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label small fw-bold text-muted text-uppercase">City / Suburb</label>
                                            <input type="text" class="form-control border-secondary-subtle" name="suburb" placeholder="e.g. Rosebank" required>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label small fw-bold text-muted text-uppercase">Province</label>
                                            <select name="province" class="form-select border-secondary-subtle" required>
                                                <option value="" selected disabled>Select...</option>
                                                <option value="Gauteng">Gauteng</option>
                                                <option value="Western Cape">Western Cape</option>
                                                <option value="KwaZulu-Natal">KwaZulu-Natal</option>
                                                <option value="Eastern Cape">Eastern Cape</option>
                                                <option value="Free State">Free State</option>
                                                <option value="Limpopo">Limpopo</option>
                                                <option value="Mpumalanga">Mpumalanga</option>
                                                <option value="North West">North West</option>
                                                <option value="Northern Cape">Northern Cape</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Job Description</label>
                                    <textarea class="form-control border-secondary-subtle" name="work_description" rows="4" placeholder="Describe exactly what you need done..." required></textarea>
                                </div>

                                <?php if(isset($_SESSION['user_id'])): ?>
                                    <button type="submit" class="btn text-white w-100 fw-bold py-2 rounded-pill shadow-sm" style="background-color: #6f42c1;">Send Booking Request</button>
                                <?php else: ?>
                                    <a href="login.php" class="btn btn-outline-secondary w-100 fw-bold py-2 rounded-pill">Log in to Book</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
    <div>
        
    </div>
</div>