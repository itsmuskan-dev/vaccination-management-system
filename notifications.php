<?php
// notifications.php - Parent aur hospital ke saare paigham (ghanti ka "View all")
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/config/db.php';

$role = current_role();
if (!in_array($role, ['parent', 'hospital'], true)) {
    redirect('admin/dashboard.php');                  // admin ke liye ghanti nahi (uske paas sidebar ke badge hain)
}
$userId = (int)$_SESSION['user_id'];

// ---------- Sab parha hua mark karna / parhe hue hatana ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Session expired. Please try again.');
    } elseif (($_POST['action'] ?? '') === 'read_all') {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$userId]);
        set_flash('success', 'All notifications marked as read.');
    } elseif (($_POST['action'] ?? '') === 'clear_read') {
        $pdo->prepare('DELETE FROM notifications WHERE user_id = ? AND is_read = 1')->execute([$userId]);
        set_flash('success', 'Read notifications cleared.');
    }
    redirect('notifications.php');
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ?');
$stmt->execute([$userId]);
$p = paginate((int)$stmt->fetchColumn(), 10, $_GET['page'] ?? 1);

// LIMIT/OFFSET: sirf (int) kiye hue numbers
$stmt = $pdo->prepare(
    'SELECT id, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY id DESC'
    . ' LIMIT ' . (int)$p['per_page'] . ' OFFSET ' . (int)$p['offset']
);
$stmt->execute([$userId]);
$items = $stmt->fetchAll();

$pageTitle  = 'Notifications';
$activeMenu = 'notifications.php';
require_once __DIR__ . "/includes/{$role}_header.php";      // $role upar whitelist ho chuka hai (parent / hospital)
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h4 class="mb-1">Notifications</h4>
    <p class="text-muted mb-0">Updates about your appointments and requests.</p>
  </div>
  <?php if ($items): ?>
    <div class="d-flex gap-2">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read_all">
        <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-check2-all me-1"></i>Mark all as read</button></form>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="clear_read">
        <button type="submit" class="btn btn-outline-secondary btn-sm" data-confirm="Remove all notifications you have already read?"><i class="bi bi-trash me-1"></i>Clear read</button></form>
    </div>
  <?php endif; ?>
</div>

<?php if (!$items): ?>
  <div class="card"><div class="card-body p-5 text-center text-muted">
    <div class="icon-box mx-auto mb-3"><i class="bi bi-bell"></i></div>
    You have no notifications yet.
  </div></div>
<?php else: ?>
  <div class="card">
    <div class="list-group list-group-flush">
      <?php foreach ($items as $n): ?>
        <a href="<?= BASE_URL ?>/notification_open.php?id=<?= (int)$n['id'] ?>"
           class="list-group-item list-group-item-action d-flex gap-3 align-items-start <?= $n['is_read'] ? '' : 'notif-unread' ?>">
          <span class="icon-box flex-shrink-0" style="width:42px;height:42px;font-size:1.1rem;"><i class="bi bi-bell"></i></span>
          <span class="flex-fill">
            <span class="d-block <?= $n['is_read'] ? '' : 'fw-semibold' ?>"><?= e($n['message']) ?></span>
            <small class="text-muted"><?= e(time_ago($n['created_at'])) ?></small>
          </span>
          <?php if (!$n['is_read']): ?><span class="badge rounded-pill text-bg-warning align-self-center">New</span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?= pagination_html($p) ?>
<?php endif; ?>

<?php require_once __DIR__ . "/includes/{$role}_footer.php"; ?>
