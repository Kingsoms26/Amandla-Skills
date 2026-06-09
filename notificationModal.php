
<div class="modal fade" id="notificationsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold"><?php echo $translations[$_SESSION['lang']]['notification'] ?? 'Notifications'; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body p-0 mt-2">
                <?php 
                // 1. Separate the notifications into two groups
                $unread = array_filter($notifications, function($n) { return !$n['is_read']; });
                $read = array_filter($notifications, function($n) { return $n['is_read']; });
                ?>

                <?php if (empty($notifications)): ?>
                    <div class="p-5 text-center text-muted">
                        <div class="fs-1 mb-2">🎉</div>
                        <h6 class="fw-bold"><?php echo $translations[$_SESSION['lang']]['notiEmpty'] ?? 'You\'re all caught up'; ?></h6>
                    </div>
                <?php else: ?>
                    
                    <div class="list-group list-group-flush">
                        
                        <?php if (!empty($unread)): ?>
                            <div class="bg-light px-4 py-2 small fw-bold text-uppercase text-muted border-bottom border-top" style="letter-spacing: 1px; font-size: 0.65rem;">
                                New
                            </div>
                            <?php foreach($unread as $notif): ?>
                                <a href="markRead.php?id=<?php echo $notif['id']; ?>&type=<?php echo urlencode($notif['TYPE'] ?? $notif['type'] ?? ''); ?>&ref=<?php echo intval($notif['reference_id']); ?>"
                                class="list-group-item list-group-item-action border-bottom border-light py-3 px-4 text-decoration-none notification-unread"
                                style="background-color: rgba(111, 66, 193, 0.03); border-left: 4px solid #6f42c1 !important;">
                                    <div class="d-flex align-items-start">
                                        <?php $meta = getNotifMeta($notif['type'] ?? $notif['TYPE']); ?>
                                        <div class="rounded-circle d-flex align-items-center justify-content-center me-3 mt-1"
                                            style="width:40px;height:40px;font-size:1.2rem;background-color:<?php echo $meta['color']; ?>18; color:<?php echo $meta['color']; ?>;">
                                            <?php echo $meta['icon']; ?>
                                        </div>

                                        <div class="w-100">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <h6 class="fw-bold mb-0" style="color:#6f42c1;">
                                                    <?php echo htmlspecialchars($notif['title']); ?>
                                                </h6>
                                                <small class="text-muted" style="font-size:0.75rem;"><?php echo date('M d', strtotime($notif['created_at'])); ?></small>
                                            </div>
                                            <p class="mb-0 small text-dark" style="line-height:1.4;"><?php echo htmlspecialchars($notif['message']); ?></p>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if (!empty($read)): ?>
                            <div class="bg-light px-4 py-2 small fw-bold text-uppercase text-muted border-bottom border-top" style="letter-spacing: 1px; font-size: 0.65rem;">
                                Earlier
                            </div>
                            <?php foreach($read as $notif): ?>
                                <a href="markRead.php?id=<?php echo $notif['id']; ?>&type=<?php echo urlencode($notif['TYPE'] ?? $notif['type'] ?? ''); ?>&ref=<?php echo intval($notif['reference_id']); ?>"
                                class="list-group-item list-group-item-action border-bottom border-light py-3 px-4 bg-white opacity-75 text-decoration-none">
                                    <div class="d-flex align-items-start">
                                        <?php $meta = getNotifMeta($notif['type'] ?? $notif['TYPE']); ?>
                                        <div class="rounded-circle d-flex align-items-center justify-content-center me-3 mt-1"
                                            style="width:40px;height:40px;font-size:1.2rem;background-color:<?php echo $meta['color']; ?>18; color:<?php echo $meta['color']; ?>;">
                                            <?php echo $meta['icon']; ?>
                                        </div>
                                        <div class="w-100">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <h6 class="fw-bold mb-0 text-dark">
                                                    <?php echo htmlspecialchars($notif['title']); ?>
                                                </h6>
                                                <small class="text-muted" style="font-size:0.75rem;"><?php echo date('M d', strtotime($notif['created_at'])); ?></small>
                                            </div>
                                            <p class="mb-0 small text-secondary" style="line-height:1.4;"><?php echo htmlspecialchars($notif['message']); ?></p>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>

                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($unread)): ?>
                <div class="modal-footer border-top-0 bg-white justify-content-center py-3">
                    <form action="markRead.php" method="POST" class="m-0 w-100 px-2">
                        <button type="submit" class="btn text-white w-100 fw-bold py-2 rounded-pill shadow-sm" style="background-color: #6f42c1 !important; color: #ffffff !important; border: none;">
                            <?php echo $translations[$_SESSION['lang']]['allRead'] ?? 'Mark All as Read'; ?>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>