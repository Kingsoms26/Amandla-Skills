<?php
if (!function_exists('time_ago')) {
    function time_ago($datetime) {
        $diff = time() - strtotime($datetime);
        if ($diff < 60)     return 'Just now';
        if ($diff < 3600)   return floor($diff / 60) . 'm ago';
        if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('d M', strtotime($datetime));
    }
}

// generates URL based on notification type and user role
if (!function_exists('notif_url')) {
    function notif_url($id, $type, $reference_id, $role) {
        $base = 'markRead.php?id=' . (int)$id . '&type=' . urlencode($type) . '&ref=' . (int)$reference_id;
        return $base;
    }
}

// notification type and their corresponding icons and colors
$type_map = [
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

$nd_notifs = [];
$nd_unread = 0;
$nd_role   = $_SESSION['user_role'] ?? 'client';

// fetchs notifications for the logged-in user
if (isset($_SESSION['user_id'], $conn)) {
    $nd_uid  = $_SESSION['user_id'];
    $nd_stmt = $conn->prepare("SELECT id, type, title, message, is_read, created_at, reference_id FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 6");
    $nd_stmt->bind_param("i", $nd_uid);
    $nd_stmt->execute();
    $nd_notifs = $nd_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $nd_stmt->close();
    $nd_unread = array_sum(array_map(fn($n) => (int)!$n['is_read'], $nd_notifs));
}
?>

<div class="dropdown-menu notif-panel dropdown-menu-end p-0">

    <!-- Header -->
    <div class="notif-header">
        <span class="notif-heading"><?php echo $translations[$_SESSION['lang']]['notification'] ?? 'Notifications'; ?></span>
        <?php if ($nd_unread > 0): ?>
            <a href="markRead.php" class="notif-mark-read">
                <?php echo $translations[$_SESSION['lang']]['allRead'] ?? 'Mark All as Read'; ?>
            </a>
        <?php endif; ?>
    </div>

    <?php if (empty($nd_notifs)): ?>
        <div class="notif-empty">
            <div class="notif-empty-icon">🔔</div>
            <p><?php echo $translations[$_SESSION['lang']]['notiEmpty'] ?? 'You\'re all caught up'; ?></p>
        </div>
    <?php else: ?>
        <ul class="notif-list list-unstyled mb-0">
            <?php foreach ($nd_notifs as $n):
                $meta    = $type_map[$n['type']] ?? ['icon' => '🔔', 'color' => '#6c757d'];
                $unread  = !(bool)$n['is_read'];
                $url = notif_url($n['id'], $n['type'], $n['reference_id'], $nd_role);
            ?>
            <li class="notif-item <?php echo $unread ? 'notif-unread' : ''; ?>">
                <a href="<?php echo $url; ?>" class="notif-row text-decoration-none">

                    <!-- Icon bubble -->
                    <div class="notif-icon-wrap" style="background:<?php echo $meta['color']; ?>18; color:<?php echo $meta['color']; ?>;">
                        <?php echo $meta['icon']; ?>
                    </div>

                    <!-- Text -->
                    <div class="notif-text">
                        <div class="notif-title"><?php echo htmlspecialchars($n['title']); ?></div>
                        <div class="notif-preview"><?php echo htmlspecialchars(mb_strimwidth($n['message'], 0, 55, '…')); ?></div>
                        <div class="notif-time"><?php echo time_ago($n['created_at']); ?></div>
                    </div>

                    <!-- Unread dot -->
                    <?php if ($unread): ?>
                        <div class="notif-dot"></div>
                    <?php endif; ?>

                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <!-- footer -->
    <?php
        $notif_dashboard = 'index.php';
        if ($nd_role === 'admin')            
            $notif_dashboard = 'adminDashboard.php';
        elseif ($nd_role === 'service_provider') 
            $notif_dashboard = 'providerDashboard.php';
        else                                 
            $notif_dashboard = 'clientDashboard.php';
    ?>
    <a href="<?php echo $notif_dashboard; ?>?open=notifications" class="notif-footer">
        <?php echo $translations[$_SESSION['lang']]['allNoti'] ?? 'View All Notifications'; ?>
    </a>
</div>