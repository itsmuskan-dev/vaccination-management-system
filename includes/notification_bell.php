<?php
// includes/notification_bell.php - Topbar ki ghanti (parent aur hospital ke layout mein)
// $notifCount aur $notifList layout pehle se tayyar karta hai.
?>
<div class="dropdown">
  <button class="btn btn-light position-relative notif-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
    <i class="bi bi-bell fs-5"></i>
    <?php if ($notifCount > 0): ?>
      <span class="notif-dot"><?= $notifCount > 9 ? '9+' : (int)$notifCount ?></span>
    <?php endif; ?>
  </button>
  <div class="dropdown-menu dropdown-menu-end shadow border-0 p-0 notif-menu">
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
      <strong>Notifications</strong>
      <a href="<?= BASE_URL ?>/notifications.php" class="small text-decoration-none">View all</a>
    </div>
    <?php if (!$notifList): ?>
      <div class="p-4 text-center text-muted small">No notifications yet</div>
    <?php else: ?>
      <?php foreach ($notifList as $n): ?>
        <a class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>" href="<?= BASE_URL ?>/notification_open.php?id=<?= (int)$n['id'] ?>">
          <span class="d-block"><?= e($n['message']) ?></span>
          <small class="text-muted"><?= e(time_ago($n['created_at'])) ?></small>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
