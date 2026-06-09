<?php
// function to grab all services for the Landing Page
function getLandingPageServices($conn, $search_query = null, $sort_option = 'newest') {
    $sql = "SELECT s.id as service_id, u.id as user_id, p.id as provider_profile_id, s.title, s.description, s.category, s.price_min, s.price_max,
                   u.name, u.profile_pic, p.verification_tier, p.service_location, COALESCE(AVG(r.rating), 0) as avg_rating
            FROM services s
            JOIN provider_profiles p ON s.provider_profile_id = p.id 
            JOIN users u ON p.user_id = u.id
            LEFT JOIN reviews r ON u.id = r.provider_id";

    $conditions = [];
    $params = [];
    $types = "";

    // verified Only
    if ($sort_option === 'verified') {
        $conditions[] = "p.verification_tier IN ('verified', 'verified_pro', 'top_pro')";
    }

    // search Input
    if (!empty($search_query)) {
        $search_term = "%" . $search_query . "%";
        $conditions[] = "(s.title LIKE ? OR s.description LIKE ? OR s.category LIKE ? OR u.name LIKE ?)";
        array_push($params, $search_term, $search_term, $search_term, $search_term);
        $types .= "ssss";
    }

    // Apply WHERE clauses if any exist
    if (count($conditions) > 0) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    // Grouping is strictly required when calculating AVG()
    $sql .= " GROUP BY s.id";

    // SORTING LOGIC
    if ($sort_option === 'highest_rated') {
        $sql .= " ORDER BY avg_rating DESC, s.id DESC";
    } else {
        $sql .= " ORDER BY s.id DESC"; 
    }

    // Execute the final query
    $stmt = $conn->prepare($sql);

    // Only bind parameters if there are any
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    
    $services = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $services[] = $row;
        }
    }
    
    if (isset($stmt)) $stmt->close();
    
    return $services;
}

// function to grab the portfolio pictures for 1 specific service
function getServiceImages($conn, $service_id) {
    $stmt = $conn->prepare("SELECT image_path FROM service_portfolio WHERE service_id = ?");
    $stmt->bind_param("i", $service_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $images = [];
    while($row = $result->fetch_assoc()) {
        $images[] = $row['image_path'];
    }
    $stmt->close();
    
    return $images;
}

// Client profile
// Get the Client's basic info
function getClientProfile($conn, $user_id) {
    $stmt = $conn->prepare("SELECT name, email, profile_pic FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $profile = $result->fetch_assoc();
    $stmt->close();
    return $profile;
}

// Get Bookings with dynamic pricing logic
function getClientBookings($conn, $user_id) {
    $sql = "SELECT 
                b.id AS booking_id, 
                b.service_date, 
                b.status AS status, 
                b.work_description,
                b.final_price,
                b.payment_status,
                b.quoted_price,
                b.quote_description,
                b.payment_reference,
                b.paid_at,
                b.release_at,
                b.region,
                u.name AS provider_name, 
                b.provider_id
            FROM bookings b
            LEFT JOIN users u ON b.provider_id = u.id
            WHERE b.client_id = ?
            ORDER BY b.service_date DESC";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $bookings = [];
    while($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
    $stmt->close();
    return $bookings;
}

// Get Client Favorites (Adjusted for Composite Primary Key)
function getClientFavorites($conn, $user_id) {
    // We only select the provider_id and join to get their name
    $sql = "SELECT f.provider_id, u.name AS provider_name, f.created_at
            FROM favourites f
            JOIN users u ON f.provider_id = u.id
            WHERE f.client_id = ? 
            ORDER BY f.created_at DESC LIMIT 5";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $favorites = [];
    while($row = $result->fetch_assoc()) {
        $favorites[] = $row;
    }
    $stmt->close();
    return $favorites;
}

// Provider profile
// Get Provider Profile (Combines Users and Provider_Profiles tables)
function getProviderProfileData($conn, $user_id) {
    // UPDATED: Added verification_tier and verification_status to the SELECT list
    $stmt = $conn->prepare("
        SELECT u.name, u.email, u.profile_pic, p.id as profile_id, p.display_name, p.phone_number, p.service_location, p.account_status, p.verification_tier, p.verification_status 
        FROM users u 
        LEFT JOIN provider_profiles p ON u.id = p.user_id 
        WHERE u.id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $profile = $result->fetch_assoc();
    $stmt->close();
    return $profile;
}

// Get Bookings for a Provider (Joins with Users to get the Client's name)
function getProviderBookings($conn, $provider_user_id) {
    $sql = "SELECT 
                b.id AS booking_id, 
                b.service_date, 
                b.status AS status, 
                b.work_description,
                b.final_price,
                b.payment_status,
                b.quoted_price,
                b.quote_description,
                b.payment_reference,
                b.paid_at,
                b.release_at,
                b.region,
                u.name AS client_name,
                u.profile_pic AS client_pic,
                b.client_phone AS client_phone,    
                b.service_address AS client_address, 
                b.client_id
            FROM bookings b
            JOIN users u ON b.client_id = u.id
            WHERE b.provider_id = ?
            ORDER BY b.service_date ASC";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $provider_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $bookings = [];
    while($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
    $stmt->close();
    return $bookings;
}

// user notifications for the notification feature
function getUserNotifications($conn, $user_id) {
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifs = [];
    while($row = $result->fetch_assoc()) {
        $notifs[] = $row;
    }
    $stmt->close();
    return $notifs;
}

// Bookings helper
function getProviderBusyDates($conn, $provider_id) {
    $stmt = $conn->prepare("SELECT DATE(service_date) as busy_date FROM bookings WHERE provider_id = ? AND status = 'accepted'");
    $stmt->bind_param("i", $provider_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $busy_dates = [];
    while ($row = $result->fetch_assoc()) {
        $busy_dates[] = $row['busy_date'];
    }
    $stmt->close();
    return $busy_dates;
}

// Fetch tags for a specific provider
function getProviderTags($conn, $provider_profile_id) {
    $stmt = $conn->prepare("SELECT skill_name FROM provider_tags WHERE provider_profile_id = ?");
    $stmt->bind_param("i", $provider_profile_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $tags = [];
    while($row = $result->fetch_assoc()) {
        $tags[] = $row['skill_name'];
    }
    $stmt->close();
    return $tags;
}

// image upload for profile picture update
function compressAndUpload($fileSource, $destinationPath, $targetWidth = 800) {
    if (!file_exists($fileSource)) return false;

    // 1. Get image info
    $info = getimagesize($fileSource);
    if (!$info) return false;
    $mime = $info['mime'];

    // create image resource
    if ($mime == 'image/jpeg' || $mime == 'image/jpg') {
        $image = imagecreatefromjpeg($fileSource);
        
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($fileSource);
            if ($exif && isset($exif['Orientation'])) {
                switch ($exif['Orientation']) {
                    case 3:
                        $image = imagerotate($image, 180, 0);
                        break;
                    case 6:
                        $image = imagerotate($image, -90, 0);
                        break;
                    case 8:
                        $image = imagerotate($image, 90, 0);
                        break;
                }
            }
        }
        
    } elseif ($mime == 'image/png') {
        $image = imagecreatefrompng($fileSource);
    } elseif ($mime == 'image/webp') {
        $image = imagecreatefromwebp($fileSource);
    } else {
        return false; 
    }

    // 3. Resize Logic
    $width = imagesx($image);
    $height = imagesy($image);

    if ($width > $targetWidth) {
        $ratio = $targetWidth / $width;
        $newWidth = $targetWidth;
        $newHeight = (int)($height * $ratio);
        
        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
        
        imagealphablending($resizedImage, false);
        imagesavealpha($resizedImage, true);

        imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        $image = $resizedImage;
    }

    if (function_exists('imagewebp')) {
        $destinationPath = preg_replace('/\.[^.]+$/', '.webp', $destinationPath);
        imagewebp($image, $destinationPath, 75);
    } else {
        $destinationPath = preg_replace('/\.[^.]+$/', '.jpg', $destinationPath);
        imagejpeg($image, $destinationPath, 75);
    }
    
    imagedestroy($image);
    return $destinationPath;
}

//this is notification type map
function getNotifMeta($type) {
    $map = [
    'booking_request'  => ['icon' => '&#x1F4CB;', 'color' => '#6f42c1'],
    'booking_update'   => ['icon' => '&#x1F504;', 'color' => '#0d6efd'],
    'payment_due'      => ['icon' => '&#x1F4B3;', 'color' => '#fd7e14'],
    'system'           => ['icon' => '&#x2699;&#xFE0F;', 'color' => '#6c757d'],
    'broadcast'        => ['icon' => '&#x1F4E2;', 'color' => '#198754'],
    'quote'            => ['icon' => '&#x1F4AC;', 'color' => '#7c3aed'],
    'quote_response'   => ['icon' => '&#x2705;', 'color' => '#7c3aed'],
    'payment'          => ['icon' => '&#x1F4B0;', 'color' => '#198754'],
    'job_complete'     => ['icon' => '&#x1F3C1;', 'color' => '#0d6efd'],
    'payment_released' => ['icon' => '&#x1F4B8;', 'color' => '#198754'],
    'dispute'          => ['icon' => '&#x26A0;&#xFE0F;', 'color' => '#dc3545'],
    'refund'           => ['icon' => '&#x21A9;&#xFE0F;', 'color' => '#fd7e14'],
];
    return $map[$type] ?? ['icon' => '🔔', 'color' => '#6c757d'];
}
?>