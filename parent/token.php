<?php
// parent/token.php?id=5 - Approved appointment ka visit token (hospital reception par dikhana hai)
require_once __DIR__ . '/../includes/auth.php';
require_role('parent');
require_once __DIR__ . '/../config/db.php';

$id       = (int)($_GET['id'] ?? 0);
$parentId = (int)$_SESSION['user_id'];

// Sirf apni aur approved booking ka token
$stmt = $pdo->prepare(
    "SELECT b.*, c.name AS child_name, v.vaccine_name, h.hospital_name, h.address, h.location, h.phone AS hospital_phone
     FROM bookings b
     JOIN children  c ON c.id = b.child_id
     JOIN vaccines  v ON v.id = b.vaccine_id
     JOIN hospitals h ON h.id = b.hospital_id
     WHERE b.id = ? AND b.parent_id = ? AND b.approval_status = 'approved'"
);
$stmt->execute([$id, $parentId]);
$b = $stmt->fetch();
if (!$b) {
    set_flash('warning', 'Token is available only for approved appointments.');
    redirect('parent/my_bookings.php');
}

$tokenNo  = token_number($pdo, $b);
$code     = token_code($b['id']);
$dateTxt  = format_date($b['appointment_date']);
$timeTxt  = date('h:i A', strtotime($b['appointment_time']));
$done     = $b['vaccination_status'] === 'vaccinated';
$missed   = $b['vaccination_status'] === 'not_vaccinated';
$isToday  = $b['appointment_date'] === date('Y-m-d');
$isPast   = $b['appointment_date'] < date('Y-m-d');
// Abhi kaun sa step chal raha hai
$current  = $done ? 5 : ($isToday ? 2 : 1);

$steps = [
    [1, 'bi-clock-history',        'Arrive on time',               'Reach the hospital at ' . $timeTxt . ' (about 10 minutes early). Bring your child\'s vaccination card and your CNIC / B-form.'],
    [2, 'bi-ticket-perforated',    'Show this token at the reception', 'Tell the reception desk your token number. They will tell you which doctor to see and which room to go to.'],
    [3, 'bi-heart-pulse',          'Get the vaccine',              'The doctor vaccinates your child and gives you a printed slip.'],
    [4, 'bi-file-earmark-check',   'Hand in the slip at the counter', 'Before you leave, submit the slip at the hospital counter. This is how the vaccination is verified in your record.'],
];

$pageTitle  = 'My Token';
$activeMenu = 'my_bookings.php';
require_once __DIR__ . '/../includes/parent_header.php';
?>

<style>
  .tk-card { background: #fff; border-radius: 18px; box-shadow: var(--shadow); border: 1px solid #ecebff; }
  .tk-ticket { position: relative; overflow: hidden; }
  .tk-ticket .tk-top { background: linear-gradient(135deg, var(--brand), #3f3bb5); color: #fff; text-align: center; padding: 1.6rem 1rem 2rem; }
  .tk-ticket .tk-label { letter-spacing: .18em; font-size: .75rem; opacity: .8; font-weight: 600; }
  .tk-ticket .tk-num { font-size: 5rem; font-weight: 800; line-height: 1; margin: .35rem 0; }
  .tk-ticket .tk-code { display: inline-block; background: rgba(255,255,255,.15); border-radius: 999px; padding: .25rem .9rem; font-size: .85rem; }
  .tk-ticket .tk-cut { height: 0; border-top: 2px dashed #dcdcf7; margin: 0 1.25rem; position: relative; }
  .tk-ticket .tk-cut::before, .tk-ticket .tk-cut::after { content: ''; position: absolute; top: -13px; width: 24px; height: 24px; border-radius: 50%; background: var(--bg); }
  .tk-ticket .tk-cut::before { left: -32px; } .tk-ticket .tk-cut::after { right: -32px; }
  .tk-info { padding: 1.25rem 1.5rem 1.5rem; }
  .tk-info .k { font-size: .8rem; color: var(--muted); }
  .tk-info .v { font-weight: 600; margin-bottom: .7rem; color: var(--text); }
  .tk-step { display: flex; gap: 1rem; padding: 1rem 1.1rem; border-radius: 14px; background: #f7f7fd; border: 1px solid transparent; margin-bottom: .8rem; }
  .tk-step .ic { width: 46px; height: 46px; flex-shrink: 0; border-radius: 12px; display: grid; place-items: center; background: var(--brand-light); color: var(--brand); font-size: 1.3rem; }
  .tk-step h6 { margin: 0 0 .2rem; font-weight: 600; }
  .tk-step p { margin: 0; font-size: .86rem; color: var(--muted); }
  .tk-step.current { background: #eeedff; border-color: var(--brand); }
  .tk-step.current .ic { background: var(--brand); color: #fff; }
  .tk-step.done .ic { background: #e6f8ee; color: #16794a; }
  .tk-here { background: var(--brand); color: #fff; font-size: .7rem; padding: .15rem .55rem; border-radius: 999px; margin-left: .4rem; vertical-align: middle; }
  .tk-alert { background: var(--brand-light); color: var(--brand); border-radius: 14px; padding: .9rem 1.1rem; }
  @media print {
    .admin-sidebar, .sidebar, .admin-topbar, .tk-noprint, nav, footer { display: none !important; }
    .tk-card { box-shadow: none; }
  }
</style>

<div class="tk-noprint mb-3">
  <a href="<?= BASE_URL ?>/parent/my_bookings.php?status=approved" class="text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Back to my bookings</a>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div>
    <h4 class="mb-1">Your visit token</h4>
    <p class="text-muted mb-0">Keep this page open or print it. Show the token at the hospital reception.</p>
  </div>
  <button type="button" class="btn btn-outline-primary tk-noprint" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
</div>

<div class="tk-alert mb-4">
  <?php if ($done): ?>
    <i class="bi bi-check-circle-fill me-1"></i>This vaccination is <strong>completed</strong> and saved in your child's record.
  <?php elseif ($missed): ?>
    <i class="bi bi-exclamation-circle me-1"></i>This visit was marked as <strong>not vaccinated</strong>. Please book a new appointment.
  <?php elseif ($isPast): ?>
    <i class="bi bi-hourglass-split me-1"></i>The appointment date has passed. The hospital has not updated the result yet.
  <?php else: ?>
    <i class="bi bi-info-circle me-1"></i>Go to the hospital on <strong><?= e($dateTxt) ?></strong> at <strong><?= e($timeTxt) ?></strong> and show this token at the reception.
  <?php endif; ?>
</div>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="tk-card tk-ticket">
      <div class="tk-top">
        <div class="tk-label">TOKEN NUMBER</div>
        <div class="tk-num"><?= e($tokenNo) ?></div>
        <div class="tk-code">Code: <strong><?= e($code) ?></strong></div>
      </div>
      <div class="tk-cut"></div>
      <div class="tk-info">
        <div class="k">Child</div>          <div class="v"><?= e($b['child_name']) ?></div>
        <div class="k">Vaccine</div>        <div class="v"><?= e($b['vaccine_name']) ?></div>
        <div class="k">Date and time</div>  <div class="v"><?= e($dateTxt) ?>, <?= e($timeTxt) ?></div>
        <div class="k">Hospital</div>
        <div class="v mb-0"><?= e($b['hospital_name']) ?></div>
        <div class="small text-muted mb-3"><?= e($b['address'] . ', ' . $b['location']) ?></div>
        <?php if (!empty($b['hospital_phone'])): ?>
          <div class="k">Hospital phone</div><div class="v"><?= e($b['hospital_phone']) ?></div>
        <?php endif; ?>
        <a class="btn btn-sm btn-outline-primary btn-pill tk-noprint" target="_blank" rel="noopener noreferrer"
           href="https://www.google.com/maps/search/?api=1&amp;query=<?= e(rawurlencode($b['hospital_name'] . ', ' . $b['address'] . ', ' . $b['location'])) ?>">
          <i class="bi bi-compass me-1"></i>Directions
        </a>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="tk-card p-4 h-100">
      <h5 class="mb-3">What to do, step by step</h5>
      <?php foreach ($steps as [$n, $icon, $title, $text]):
          $cls = $n < $current ? 'done' : ($n === $current ? 'current' : ''); ?>
        <div class="tk-step <?= $cls ?>">
          <div class="ic"><i class="bi <?= $cls === 'done' ? 'bi-check-lg' : $icon ?>"></i></div>
          <div>
            <h6>Step <?= $n ?>. <?= e($title) ?><?php if ($cls === 'current'): ?><span class="tk-here">You are here</span><?php endif; ?></h6>
            <p><?= e($text) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/parent_footer.php'; ?>
