<?php
// admin/hospital_invite.php?id=3 - Jis hospital ka login nahi, usay invitation link banana aur WhatsApp / email se bhejna
// Hospital link khol kar apna account banata hai, aur phir uski saari bookings uske dashboard par aa jati hain.
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM hospitals WHERE id = ?');
$stmt->execute([$id]);
$hospital = $stmt->fetch();

if (!$hospital) {
    set_flash('danger', 'Hospital not found.');
    redirect('admin/hospitals.php');
}
if ($hospital['user_id'] !== null) {
    set_flash('info', 'This hospital already has a login account.');
    redirect('admin/hospitals.php');
}

// Is hospital mein kitni bookings intezar kar rahi hain
$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(approval_status = 'approved' AND vaccination_status = 'pending'), 0) AS upcoming,
            COALESCE(SUM(approval_status = 'pending'), 0) AS requested
     FROM bookings WHERE hospital_id = ?"
);
$stmt->execute([$id]);
$counts = $stmt->fetch();

// ---------- Form: email / WhatsApp number save karna, aur link banana ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email  = trim($_POST['contact_email'] ?? '');
    $phone  = trim($_POST['phone'] ?? '');
    $action = $_POST['action'] ?? 'save';

    if (!verify_csrf()) {
        set_flash('danger', 'Session expired. Please try again.');
    } elseif ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120)) {
        set_flash('danger', 'Please enter a valid email or leave it empty.');
    } elseif ($phone !== '' && !preg_match('/^[0-9+\-\s]{10,15}$/', $phone)) {
        set_flash('danger', 'Please enter a valid WhatsApp / phone number (e.g. 0300 1234567).');
    } else {
        $pdo->prepare('UPDATE hospitals SET contact_email = ?, phone = ? WHERE id = ? AND user_id IS NULL')
            ->execute([$email === '' ? null : $email, $phone === '' ? null : $phone, $id]);

        // Pehli baar, ya admin ne naya link manga (purana khud band ho jata hai)
        if ($action === 'new' || empty($hospital['invite_token'])) {
            $token = bin2hex(random_bytes(16));
            $pdo->prepare('UPDATE hospitals SET invite_token = ? WHERE id = ? AND user_id IS NULL')->execute([$token, $id]);
            set_flash('success', $action === 'new' && !empty($hospital['invite_token'])
                ? 'New invitation link created. The old link no longer works.'
                : 'Invitation link created. Now send it on WhatsApp or by email.');
        } else {
            set_flash('success', 'Contact details saved.');
        }
    }
    redirect('admin/hospital_invite.php?id=' . $id);
}

// ---------- Link aur bhejne ke raaste ----------
$inviteUrl = $waUrl = $gmailUrl = $mailUrl = $message = null;
$isLocal   = false;
if (!empty($hospital['invite_token'])) {
    if (SITE_URL !== '') {
        $base = rtrim(SITE_URL, '/');
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $base   = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL;
    }
    $inviteUrl = $base . '/register.php?role=hospital&claim=' . $hospital['invite_token'];
    $isLocal   = (bool)preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?/#i', $inviteUrl);

    $waiting = (int)$counts['upcoming'] + (int)$counts['requested'];
    $subject = 'Invitation: join ' . APP_NAME . ' as a hospital';
    $message = "Assalam o Alaikum,\n\n"
             . APP_NAME . " would like to add " . $hospital['hospital_name'] . " as a partner hospital.\n"
             . ($waiting > 0
                 ? "Parents have already booked " . $waiting . " vaccination appointment(s) at your hospital.\n"
                 : "Parents can book vaccination appointments at your hospital through " . APP_NAME . ".\n")
             . "\nPlease create your hospital account using this link (it works only once):\n" . $inviteUrl
             . "\n\nAfter signing up you can see all appointments and record vaccinations from your dashboard.\n\nThank you,\n" . APP_NAME . " Team";

    // WhatsApp: 03xx... ko 923xx... banao. Number na ho to WhatsApp khud contact chunne ko kehta hai.
    $digits = preg_replace('/\D+/', '', (string)$hospital['phone']);
    if (strpos($digits, '00') === 0)     { $digits = substr($digits, 2); }
    elseif (strpos($digits, '0') === 0)  { $digits = '92' . substr($digits, 1); }
    $waUrl = 'https://wa.me/' . (strlen($digits) >= 11 ? $digits : '') . '?text=' . rawurlencode($message);

    // Email: Gmail (browser mein khulta hai, koi setup nahi) aur computer ki email app
    $to       = (string)$hospital['contact_email'];
    $gmailUrl = 'https://mail.google.com/mail/?view=cm&fs=1&to=' . rawurlencode($to) . '&su=' . rawurlencode($subject) . '&body=' . rawurlencode($message);
    $mailUrl  = 'mailto:' . rawurlencode($to) . '?subject=' . rawurlencode($subject) . '&body=' . rawurlencode($message);
}

$pageTitle  = 'Invite Hospital';
$activeMenu = 'hospitals.php';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<style>
  .inv-step { width: 28px; height: 28px; border-radius: 50%; background: var(--brand); color: #fff; display: inline-grid; place-items: center; font-size: .85rem; font-weight: 600; margin-right: .5rem; }
  .inv-send { display: flex; align-items: center; gap: .9rem; padding: .9rem 1rem; border-radius: 14px; border: 1px solid #e6e5fb; background: #fff; text-decoration: none; color: var(--text); transition: all .15s; height: 100%; }
  .inv-send:hover { border-color: var(--brand); box-shadow: var(--shadow); color: var(--text); }
  .inv-send .ic { width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; font-size: 1.35rem; flex-shrink: 0; }
  .inv-send small { color: var(--muted); display: block; font-size: .78rem; }
  .inv-wa .ic { background: #e7f8ee; color: #1da851; }
  .inv-gm .ic { background: #fdecea; color: #d93025; }
  .inv-ml .ic { background: var(--brand-light); color: var(--brand); }
  .inv-msg { white-space: pre-wrap; font-size: .84rem; background: #f7f7fd; border-radius: 12px; padding: 1rem; max-height: 220px; overflow: auto; }
</style>

<div class="mb-4">
  <a href="<?= BASE_URL ?>/admin/hospitals.php" class="text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Back to hospitals</a>
  <h4 class="mt-2 mb-0">Invite <?= e($hospital['hospital_name']) ?></h4>
  <p class="text-muted mb-0"><?= e($hospital['location']) ?> &bull; <?= e($hospital['phone'] ?: 'No phone') ?></p>
</div>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="card mb-4">
      <div class="card-body p-4">
        <h6 class="mb-3">Why invite this hospital?</h6>
        <p class="small text-muted">This hospital is in the list but has <strong>no login yet</strong>, so it cannot see appointments or record vaccinations.</p>
        <ul class="list-unstyled small mb-0">
          <li class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Appointments booked</span><strong><?= (int)$counts['total'] ?></strong></li>
          <li class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Confirmed, waiting</span><strong><?= (int)$counts['upcoming'] ?></strong></li>
          <li class="d-flex justify-content-between py-2"><span class="text-muted">Awaiting approval</span><strong><?= (int)$counts['requested'] ?></strong></li>
        </ul>
      </div>
    </div>

    <div class="card">
      <div class="card-body p-4">
        <h6 class="mb-3"><span class="inv-step">1</span>Hospital contact</h6>
        <form method="post" class="needs-validation" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$hospital['id'] ?>">
          <div class="mb-3">
            <label for="phone" class="form-label">WhatsApp number</label>
            <input type="tel" class="form-control" id="phone" name="phone" value="<?= e((string)$hospital['phone']) ?>"
                   pattern="[0-9+\-\s]{10,15}" placeholder="0300 1234567">
            <div class="invalid-feedback">Please enter a valid number.</div>
          </div>
          <div class="mb-3">
            <label for="contact_email" class="form-label">Email</label>
            <input type="email" class="form-control" id="contact_email" name="contact_email" value="<?= e((string)$hospital['contact_email']) ?>" maxlength="120" placeholder="info@hospital.com">
            <div class="invalid-feedback">Please enter a valid email.</div>
          </div>
          <?php if ($inviteUrl): ?>
            <button type="submit" name="action" value="save" class="btn btn-primary w-100 mb-2"><i class="bi bi-check-lg me-1"></i>Save contact</button>
            <button type="submit" name="action" value="new" class="btn btn-outline-secondary btn-sm w-100"
                    data-confirm="Create a new link? The old link will stop working."><i class="bi bi-arrow-repeat me-1"></i>Create a new link</button>
          <?php else: ?>
            <button type="submit" name="action" value="new" class="btn btn-primary w-100"><i class="bi bi-link-45deg me-1"></i>Create invitation link</button>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-body p-4">
        <?php if (!$inviteUrl): ?>
          <div class="text-center text-muted py-5">
            <i class="bi bi-envelope-paper display-5 d-block mb-3"></i>
            Add the hospital's WhatsApp number or email, then click <strong>Create invitation link</strong>.
          </div>
        <?php else: ?>
          <h6 class="mb-3"><span class="inv-step">2</span>Send the invitation</h6>

          <?php if ($isLocal): ?>
            <div class="alert alert-warning small">
              <i class="bi bi-exclamation-triangle me-1"></i>This link uses <strong>localhost</strong>, so it opens only on this computer.
              Once the website is online, set <code>SITE_URL</code> in <code>config/config.php</code> and the link will work on any phone.
            </div>
          <?php endif; ?>

          <div class="row g-3 mb-4">
            <div class="col-md-4">
              <a href="<?= e($waUrl) ?>" target="_blank" rel="noopener noreferrer" class="inv-send inv-wa">
                <span class="ic"><i class="bi bi-whatsapp"></i></span>
                <span><strong>WhatsApp</strong><small><?= $hospital['phone'] ? e($hospital['phone']) : 'Choose a contact' ?></small></span>
              </a>
            </div>
            <div class="col-md-4">
              <a href="<?= e($gmailUrl) ?>" target="_blank" rel="noopener noreferrer" class="inv-send inv-gm">
                <span class="ic"><i class="bi bi-google"></i></span>
                <span><strong>Gmail</strong><small><?= $hospital['contact_email'] ? e($hospital['contact_email']) : 'Add email in Gmail' ?></small></span>
              </a>
            </div>
            <div class="col-md-4">
              <a href="<?= e($mailUrl) ?>" class="inv-send inv-ml">
                <span class="ic"><i class="bi bi-envelope"></i></span>
                <span><strong>Email app</strong><small>Outlook, Mail etc.</small></span>
              </a>
            </div>
          </div>

          <label class="form-label small text-muted mb-1">Invitation link</label>
          <div class="input-group mb-3">
            <input type="text" class="form-control" id="inviteUrl" value="<?= e($inviteUrl) ?>" readonly>
            <a href="<?= e($inviteUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary" title="Open the link to test it"><i class="bi bi-box-arrow-up-right"></i></a>
            <button type="button" class="btn btn-primary" data-copy="#inviteUrl"><i class="bi bi-clipboard me-1"></i>Copy</button>
          </div>

          <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="form-label small text-muted mb-0">Message that will be sent</label>
            <button type="button" class="btn btn-link btn-sm p-0" data-copy="#inviteMsg"><i class="bi bi-clipboard me-1"></i>Copy message</button>
          </div>
          <div class="inv-msg mb-3" id="inviteMsg"><?= e($message) ?></div>

          <div class="alert alert-info small mb-0">
            <i class="bi bi-info-circle me-1"></i>The link works <strong>once</strong>. When the hospital creates its account, it is linked to this listing. It can log in right away and see every appointment on its dashboard.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
