<?php
// admin/messages.php - Landing page ke contact form se aaye hue paigham
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

$tab = in_array($_GET['status'] ?? '', ['new', 'read'], true) ? $_GET['status'] : 'all';

$counts        = $pdo->query('SELECT status, COUNT(*) FROM contact_messages GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$counts['all'] = array_sum($counts);

$where  = $tab === 'all' ? '' : ' WHERE status = ?';
$params = $tab === 'all' ? [] : [$tab];

$stmt = $pdo->prepare('SELECT COUNT(*) FROM contact_messages' . $where);
$stmt->execute($params);
$p = paginate((int)$stmt->fetchColumn(), 8, $_GET['page'] ?? 1);

// LIMIT/OFFSET mein sirf (int) kiye hue numbers
$stmt = $pdo->prepare(
    'SELECT * FROM contact_messages' . $where
    . " ORDER BY (status = 'new') DESC, created_at DESC, id DESC"
    . ' LIMIT ' . (int)$p['per_page'] . ' OFFSET ' . (int)$p['offset']
);
$stmt->execute($params);
$messages = $stmt->fetchAll();

$pageTitle  = 'Messages';
$activeMenu = 'messages.php';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="mb-4">
  <h4 class="mb-1">Messages</h4>
  <p class="text-muted mb-0">Messages sent from the contact form on the website.</p>
</div>

<ul class="nav nav-pills mb-3 gap-2">
  <?php foreach (['all' => 'All', 'new' => 'New', 'read' => 'Read'] as $key => $label): ?>
    <li class="nav-item">
      <a class="nav-link <?= $tab === $key ? 'active' : '' ?>" href="?status=<?= $key ?>">
        <?= $label ?> <span class="badge text-bg-light ms-1"><?= (int)($counts[$key] ?? 0) ?></span>
      </a>
    </li>
  <?php endforeach; ?>
</ul>

<?php if (!$messages): ?>
  <div class="card"><div class="card-body p-5 text-center text-muted">
    <div class="icon-box mx-auto mb-3"><i class="bi bi-envelope-open"></i></div>
    No messages here.
  </div></div>
<?php endif; ?>

<div class="row g-3">
  <?php foreach ($messages as $m): ?>
    <div class="col-12">
      <div class="card <?= $m['status'] === 'new' ? 'border-start border-4 border-warning' : '' ?>">
        <div class="card-body">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
            <div class="min-w-0">
              <div class="fw-semibold"><?= e($m['name']) ?>
                <?php if ($m['status'] === 'new'): ?><span class="badge rounded-pill text-bg-warning ms-1">New</span><?php endif; ?>
              </div>
              <small class="text-muted"><i class="bi bi-envelope me-1"></i><?= e($m['email']) ?> &bull; <?= e(date('d M Y, h:i A', strtotime($m['created_at']))) ?></small>
            </div>
            <span class="badge rounded-pill text-bg-light border"><?= e($m['subject']) ?></span>
          </div>

          <p class="mb-3" style="white-space: pre-wrap;"><?= e($m['message']) ?></p>

          <div class="d-flex flex-wrap gap-2">
            <a href="mailto:<?= e($m['email']) ?>?subject=<?= e(rawurlencode('Re: ' . $m['subject'])) ?>" class="btn btn-sm btn-primary">
              <i class="bi bi-reply me-1"></i>Reply by email
            </a>
            <?php foreach ([['read', 'outline-secondary', 'bi-check2-all', 'Mark as read', $m['status'] === 'new'],
                            ['delete', 'outline-danger', 'bi-trash', 'Delete', true]] as [$act, $btn, $icon, $label, $show]): ?>
              <?php if ($show): ?>
                <form method="post" action="<?= BASE_URL ?>/admin/message_action.php" class="d-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                  <input type="hidden" name="action" value="<?= $act ?>">
                  <input type="hidden" name="qs" value="<?= e($_SERVER['QUERY_STRING'] ?? '') ?>">
                  <button type="submit" class="btn btn-sm btn-<?= $btn ?>" <?= $act === 'delete' ? 'data-confirm="Delete this message?"' : '' ?>>
                    <i class="bi <?= $icon ?> me-1"></i><?= $label ?>
                  </button>
                </form>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?= pagination_html($p, $tab === 'all' ? [] : ['status' => $tab]) ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
