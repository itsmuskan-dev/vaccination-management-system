<?php
// index.php - Landing page (Home). Saara data database se asli aata hai (jhoote numbers nahi).
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/schedule.php';

// "6 weeks to 14 weeks" ko saaf likhne ke liye: same unit ho to "6 to 14 weeks"
function range_label($min, $max)
{
    if ($min === $max) { return age_label($min); }
    [$v1, $u1] = split_age_days($min);
    [$v2, $u2] = split_age_days($max);
    return ($u1 === $u2 && $min > 0) ? $v1 . ' to ' . $v2 . ' ' . $u1 : age_label($min) . ' to ' . age_label($max);
}
// Pehle 4 naam, baqi "and N more"
function names_text(array $names)
{
    $shown = array_slice($names, 0, 4);
    $text  = implode(', ', $shown);
    return count($names) > 4 ? $text . ' and ' . (count($names) - 4) . ' more' : $text;
}

// ---------- Asli ginti ----------
$partnerHospitals = (int)$pdo->query("SELECT COUNT(*) FROM hospitals h LEFT JOIN users u ON u.id = h.user_id WHERE h.user_id IS NULL OR u.status = 'active'")->fetchColumn();
$citiesCovered    = (int)$pdo->query("SELECT COUNT(DISTINCT LOWER(h.location)) FROM hospitals h LEFT JOIN users u ON u.id = h.user_id WHERE h.user_id IS NULL OR u.status = 'active'")->fetchColumn();
$families         = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'parent' AND status = 'active'")->fetchColumn();
$doses            = (int)$pdo->query("SELECT (SELECT COUNT(*) FROM bookings WHERE approval_status = 'approved' AND vaccination_status = 'vaccinated') + (SELECT COUNT(*) FROM past_vaccinations WHERE status = 'verified')")->fetchColumn();

$stats = [
    [$partnerHospitals, 'Partner hospitals'],
    [$citiesCovered,    'Cities covered'],
    [$families,         'Families registered'],
    [$doses,            'Doses recorded'],
];

// Progress bars ke asli percentage (jab data na ho to 0)
$row = $pdo->query("SELECT COALESCE(SUM(approval_status = 'approved'), 0) AS ap, COALESCE(SUM(approval_status = 'rejected'), 0) AS rj FROM bookings")->fetch();
$approvedPct = ($row['ap'] + $row['rj']) > 0 ? (int)round($row['ap'] * 100 / ($row['ap'] + $row['rj'])) : 0;

$stmt = $pdo->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(vaccination_status = 'vaccinated'), 0) AS done FROM bookings WHERE approval_status = 'approved' AND appointment_date <= ?");
$stmt->execute([date('Y-m-d')]);
$v = $stmt->fetch();
$visitPct = $v['total'] > 0 ? (int)round($v['done'] * 100 / $v['total']) : 0;

$totalChildren = (int)$pdo->query('SELECT COUNT(*) FROM children')->fetchColumn();
$withRecord    = (int)$pdo->query(
    "SELECT COUNT(*) FROM children c WHERE
       EXISTS (SELECT 1 FROM bookings b WHERE b.child_id = c.id AND b.approval_status = 'approved' AND b.vaccination_status = 'vaccinated')
    OR EXISTS (SELECT 1 FROM past_vaccinations p WHERE p.child_id = c.id AND p.status = 'verified')"
)->fetchColumn();
$recordPct = $totalChildren > 0 ? (int)round($withRecord * 100 / $totalChildren) : 0;

$bars = [
    ['Booking requests approved',          $approvedPct],
    ['Approved visits completed',          $visitPct],
    ['Children with a vaccine on record',  $recordPct],
];

// ---------- Vaccines (marquee + "what we cover") ----------
$vaccines = $pdo->query('SELECT vaccine_name, recommended_age_days FROM vaccines ORDER BY recommended_age_days ASC, id ASC')->fetchAll();
$vaccineNames = array_column($vaccines, 'vaccine_name');
$maxDays = $vaccines ? (int)max(array_column($vaccines, 'recommended_age_days')) : 0;

$stageDefs = [
    ['Newborn protection',   'bi-heart-pulse', '',      'Protection that starts on day one.',                       fn($d) => $d === 0],
    ['Infant immunization',  'bi-capsule',     'coral', 'The first series that guards your baby in early months.',  fn($d) => $d > 0 && $d <= 180],
    ['Toddler boosters',     'bi-emoji-smile', 'teal',  'Boosters that keep protection strong as your child grows.', fn($d) => $d > 180],
];
$stages = [];
foreach ($stageDefs as [$title, $icon, $tone, $text, $test]) {
    $names = []; $days = [];
    foreach ($vaccines as $vac) {
        if ($test((int)$vac['recommended_age_days'])) { $names[] = $vac['vaccine_name']; $days[] = (int)$vac['recommended_age_days']; }
    }
    if ($names) {
        $stages[] = ['title' => $title, 'icon' => $icon, 'tone' => $tone, 'text' => $text,
                     'range' => range_label(min($days), max($days)), 'names' => $names];
    }
}

// ---------- Hospitals (sirf manzoor shuda) ----------
$hospitals = $pdo->query(
    "SELECT h.hospital_name, h.address, h.location, h.phone, h.latitude, h.longitude
     FROM hospitals h LEFT JOIN users u ON u.id = h.user_id
     WHERE h.user_id IS NULL OR u.status = 'active'
     ORDER BY h.location, h.hospital_name LIMIT 60"
)->fetchAll();
// Jin hospitals ke coordinates nahi, unko shehar ke markaz ke coordinates de do (andaza)
$cityCenters = [
    'karachi' => [24.8607, 67.0011], 'lahore' => [31.5204, 74.3587], 'islamabad' => [33.6844, 73.0479],
    'rawalpindi' => [33.5651, 73.0169], 'faisalabad' => [31.4504, 73.1350], 'multan' => [30.1575, 71.5249],
    'peshawar' => [34.0151, 71.5249], 'quetta' => [30.1798, 66.9750], 'hyderabad' => [25.3960, 68.3578],
    'sukkur' => [27.7052, 68.8574], 'gujranwala' => [32.1877, 74.1945], 'sialkot' => [32.4945, 74.5229],
    'bahawalpur' => [29.3956, 71.6836], 'sargodha' => [32.0836, 72.6711], 'abbottabad' => [34.1688, 73.2215],
    'mardan' => [34.1986, 72.0404], 'larkana' => [27.5570, 68.2264], 'nawabshah' => [26.2442, 68.4100],
];
foreach ($hospitals as &$h) {
    $h['approx'] = 0;
    if ($h['latitude'] === null || $h['longitude'] === null) {
        $key = mb_strtolower(trim($h['location']));
        foreach ($cityCenters as $city => $ll) {
            if (strpos($key, $city) !== false) { [$h['latitude'], $h['longitude']] = $ll; $h['approx'] = 1; break; }
        }
    }
}
unset($h);
$cityCounts = [];
foreach ($hospitals as $h) {
    $label = mb_convert_case(trim($h['location']), MB_CASE_TITLE);
    $cityCounts[$label] = ($cityCounts[$label] ?? 0) + 1;
}
ksort($cityCounts);
$bookUrl = (is_logged_in() && current_role() === 'parent') ? BASE_URL . '/parent/book_hospital.php' : BASE_URL . '/register.php?role=parent';

// ---------- Login wale ke liye hero ka personal card (guest ko sample dikhta hai) ----------
$me = null;
if (is_logged_in()) {
    $uid  = (int)$_SESSION['user_id'];
    $role = current_role();
    if ($role === 'parent') {
        $stmt = $pdo->prepare('SELECT * FROM children WHERE parent_id = ? ORDER BY dob DESC, id DESC');
        $stmt->execute([$uid]);
        $kids = $stmt->fetchAll();
        if (!$kids) {
            $me = ['type' => 'parent', 'empty' => true];
        } else {
            $vacAll = $pdo->query('SELECT id, vaccine_name, recommended_age, recommended_age_days, availability FROM vaccines ORDER BY recommended_age_days ASC, id ASC')->fetchAll();
            $stmt = $pdo->prepare("SELECT child_id, vaccine_id, approval_status, vaccination_status, appointment_date FROM bookings WHERE parent_id = ? AND approval_status <> 'rejected'");
            $stmt->execute([$uid]);
            $byChild = [];
            foreach ($stmt->fetchAll() as $bk) { $byChild[$bk['child_id']][] = $bk; }
            foreach (past_records_by_child($pdo, array_column($kids, 'id')) as $cid => $list) {
                foreach ($list as $pr) { $byChild[$cid][] = $pr; }
            }
            // Jis bache ko sab se ziyada tawajjo chahiye (due/overdue sab se ziyada) wohi dikhao
            $best = null; $bestScore = -1;
            foreach ($kids as $k) {
                $rows  = vaccine_schedule($k['dob'], $vacAll, $byChild[$k['id']] ?? []);
                $score = count(array_filter($rows, fn($r) => in_array($r['status'], ['due', 'overdue'], true)));
                if ($score > $bestScore) { $bestScore = $score; $best = ['child' => $k, 'rows' => $rows]; }
            }
            $pending = array_values(array_filter($best['rows'], fn($r) => $r['status'] !== 'done'));
            $me = ['type' => 'parent', 'empty' => false, 'child' => $best['child'],
                   'done' => count($best['rows']) - count($pending), 'total' => count($best['rows']),
                   'next' => array_slice($pending, 0, 3), 'more' => count($kids) - 1];
        }
    } elseif ($role === 'hospital') {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(b.appointment_date = ?), 0) AS today,
                    COALESCE(SUM(b.appointment_date <= ? AND b.vaccination_status = 'pending'), 0) AS needs
             FROM bookings b JOIN hospitals h ON h.id = b.hospital_id
             WHERE h.user_id = ? AND b.approval_status = 'approved'"
        );
        $stmt->execute([date('Y-m-d'), date('Y-m-d'), $uid]);
        $me = ['type' => 'hospital'] + $stmt->fetch();
    } elseif ($role === 'admin') {
        $me = ['type' => 'admin',
            'requests'  => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE approval_status = 'pending'")->fetchColumn(),
            'approvals' => (int)$pdo->query("SELECT COUNT(*) FROM hospital_suggestions WHERE status = 'pending'")->fetchColumn()
                         + (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn(),
            'messages'  => (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'")->fetchColumn()];
    }
}

$faqs = [
    ['What is VaxCare?',                     'VaxCare is an online service that helps parents keep their children\'s vaccinations on schedule, book appointments at hospitals and keep a complete digital record.'],
    ['How do I know which vaccine is due?',  'After adding your child, your dashboard shows a schedule built from your child\'s date of birth, with each vaccine marked as due, upcoming, booked or completed.'],
    ['Is it free to use?',                   'Yes. Creating an account and using the schedule, reminders and certificate is free for parents.'],
    ['Is my booking confirmed instantly?',   'No. Every request is first reviewed by the VaxCare team. Once approved, it appears as confirmed on your dashboard and the hospital receives the appointment.'],
    ['What if my child has already had some vaccines?', 'You can add earlier vaccinations to your child\'s record. After they are verified they also appear on the certificate.'],
    ['Can I cancel or change an appointment?', 'You can cancel a request while it is still pending. After it has been approved, please contact the hospital.'],
];

// Contact form: galti ke baad purani values wapas, ya login ho to naam/email pehle se bhara hua
$contactOld = $_SESSION['contact_old'] ?? [];
unset($_SESSION['contact_old']);
$contactName  = $contactOld['name'] ?? (is_logged_in() ? ($_SESSION['name'] ?? '') : '');
$contactEmail = $contactOld['email'] ?? '';
if ($contactEmail === '' && is_logged_in()) {
    $q = $pdo->prepare('SELECT email FROM users WHERE id = ?');
    $q->execute([$_SESSION['user_id']]);
    $contactEmail = (string)$q->fetchColumn();
}
$contactSubject = $contactOld['subject'] ?? '';
$contactMessage = $contactOld['message'] ?? '';

$pageTitle       = 'Home';
$metaDescription = 'Keep every child\'s vaccinations on schedule. See which vaccines are due, find a trusted hospital, book an appointment and download a verifiable certificate with VaxCare.';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ============ HERO ============ -->
<section class="lp-hero">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <div class="lp-eyebrow fade-in-up"><?= is_logged_in() ? 'Welcome back, ' . e(explode(' ', trim($_SESSION['name'] ?? ''))[0]) : 'Child immunization made simple' ?></div>
        <h1 class="fade-in-up">Every vaccine on time.<br><strong>Every child protected.</strong></h1>
        <p class="lead fade-in-up">Track your child's vaccination schedule, find a trusted hospital and keep a complete digital record, all in one place.</p>

        <div class="d-flex flex-wrap gap-2 my-4 fade-in-up">
          <?php if (is_logged_in()): ?>
            <a href="<?= BASE_URL ?>/<?= e(current_role()) ?>/dashboard.php" class="btn btn-primary btn-lg btn-pill px-4">Go to Dashboard</a>
          <?php else: ?>
            <a href="<?= BASE_URL ?>/register.php?role=parent" class="btn btn-primary btn-lg btn-pill px-4"><i class="bi bi-calendar-check me-2"></i>Get started free</a>
          <?php endif; ?>
          <a href="#hospitals" class="btn btn-outline-primary btn-lg btn-pill px-4"><i class="bi bi-geo-alt me-2"></i>Find a hospital</a>
        </div>

        <div class="lp-trust fade-in-up">
          <span><i class="bi bi-check-circle-fill"></i>Trusted hospitals</span>
          <span><i class="bi bi-check-circle-fill"></i>Personal schedule</span>
          <span><i class="bi bi-check-circle-fill"></i>Free to join</span>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="lp-hero-art" data-reveal>
          <svg class="lp-hero-svg" viewBox="0 0 480 440" role="img" aria-label="A shield with a medical cross protecting a child's health">
            <defs>
              <linearGradient id="lpShield" x1="0.1" y1="0" x2="0.9" y2="1"><stop offset="0" stop-color="#6a66f0"/><stop offset="1" stop-color="#1f1e5a"/></linearGradient>
              <linearGradient id="lpShield2" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#9d9aff" stop-opacity=".5"/><stop offset="1" stop-color="#27256e" stop-opacity="0"/></linearGradient>
              <linearGradient id="lpOrb" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ecebff"/><stop offset="1" stop-color="#cfcdff"/></linearGradient>
            </defs>
            <circle cx="240" cy="225" r="200" fill="url(#lpOrb)"/>
            <circle cx="240" cy="225" r="150" fill="#fff" opacity=".55"/>
            <path d="M240 58 L368 104 V222 C368 300 314 356 240 396 C166 356 112 300 112 222 V104 Z" fill="url(#lpShield)"/>
            <path d="M240 58 L368 104 V222 C368 300 314 356 240 396 Z" fill="#000" opacity=".12"/>
            <path d="M240 78 L350 118 V222 C350 288 304 338 240 374 C176 338 130 288 130 222 V118 Z" fill="url(#lpShield2)"/>
            <rect x="214" y="150" width="52" height="150" rx="14" fill="#fff"/>
            <rect x="165" y="199" width="150" height="52" rx="14" fill="#fff"/>
            <polyline points="150,225 200,225 218,190 240,262 258,215 272,225 330,225" fill="none" stroke="#ff6b5e" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="376" cy="112" r="34" fill="#ff6b5e"/>
            <path d="M376 97v30M361 112h30" stroke="#fff" stroke-width="7" stroke-linecap="round"/>
            <circle cx="92" cy="306" r="22" fill="#2a9df4"/>
            <circle cx="414" cy="304" r="9" fill="#4b47d1"/>
            <circle cx="84" cy="136" r="14" fill="none" stroke="#ff6b5e" stroke-width="5"/>
          </svg>

          <svg class="lp-badge-spin" viewBox="0 0 120 120" aria-hidden="true">
            <defs><path id="lpCircle" d="M60,60 m-44,0 a44,44 0 1,1 88,0 a44,44 0 1,1 -88,0"/></defs>
            <circle cx="60" cy="60" r="58" fill="#27256e"/>
            <text font-size="11" font-weight="700" fill="#fff"><textPath href="#lpCircle" textLength="268">SAFE FROM THE START *</textPath></text>
            <circle cx="60" cy="60" r="14" fill="#ff6b5e"/>
            <path d="M60 52v16M52 60h16" stroke="#fff" stroke-width="4" stroke-linecap="round"/>
          </svg>

          <div class="lp-float a"><span class="dot"><i class="bi bi-journal-check"></i></span>Complete record</div>
          <?php if ($me && $me['type'] === 'parent' && !$me['empty']): $ch = $me['child']; ?>
            <!-- Parent ke bache ka asli record -->
            <div class="lp-float b lp-me">
              <div class="small text-muted">Your child</div>
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold"><?= e($ch['name']) ?></span><small class="text-muted"><?= e(child_age($ch['dob'])) ?> old</small>
              </div>
              <div class="d-flex justify-content-between small mb-1"><span class="text-muted">Progress</span><span><?= (int)$me['done'] ?> of <?= (int)$me['total'] ?> done</span></div>
              <div class="progress mb-2"><div class="progress-bar bg-success" style="width: <?= $me['total'] ? (int)round($me['done'] * 100 / $me['total']) : 0 ?>%"></div></div>
              <?php foreach ($me['next'] as $r): ?>
                <div class="row-i"><span><?= e($r['vaccine']['vaccine_name']) ?></span><?= schedule_badge($r['status']) ?></div>
              <?php endforeach; ?>
              <?php if (!$me['next']): ?><div class="row-i"><i class="bi bi-check-circle-fill"></i>All vaccines are up to date</div><?php endif; ?>
              <a href="<?= BASE_URL ?>/parent/dashboard.php" class="small fw-semibold text-decoration-none d-block mt-2">
                Open dashboard<?= $me['more'] > 0 ? ' (+' . (int)$me['more'] . ' more child' . ($me['more'] > 1 ? 'ren' : '') . ')' : '' ?> <i class="bi bi-arrow-right"></i>
              </a>
            </div>
          <?php elseif ($me && $me['type'] === 'parent'): ?>
            <div class="lp-float b lp-me">
              <div class="fw-semibold mb-1">Add your first child</div>
              <div class="small text-muted mb-2">Your vaccination schedule appears here as soon as you add a child.</div>
              <a href="<?= BASE_URL ?>/parent/child_form.php" class="btn btn-sm btn-primary btn-pill px-3">Add child</a>
            </div>
          <?php elseif ($me && $me['type'] === 'hospital'): ?>
            <div class="lp-float b lp-me">
              <div class="fw-semibold mb-1">Your hospital today</div>
              <div class="row-i"><span>Appointments today</span><strong><?= (int)$me['today'] ?></strong></div>
              <div class="row-i"><span>Waiting for status update</span><strong><?= (int)$me['needs'] ?></strong></div>
              <a href="<?= BASE_URL ?>/hospital/appointments.php" class="small fw-semibold text-decoration-none d-block mt-2">Open appointments <i class="bi bi-arrow-right"></i></a>
            </div>
          <?php elseif ($me && $me['type'] === 'admin'): ?>
            <div class="lp-float b lp-me">
              <div class="fw-semibold mb-1">Waiting for you</div>
              <div class="row-i"><span>Appointment requests</span><strong><?= (int)$me['requests'] ?></strong></div>
              <div class="row-i"><span>Hospital approvals</span><strong><?= (int)$me['approvals'] ?></strong></div>
              <div class="row-i"><span>New messages</span><strong><?= (int)$me['messages'] ?></strong></div>
              <a href="<?= BASE_URL ?>/admin/dashboard.php" class="small fw-semibold text-decoration-none d-block mt-2">Open dashboard <i class="bi bi-arrow-right"></i></a>
            </div>
          <?php else: ?>
            <div class="lp-float b">
              <div class="fw-semibold mb-1">Track every vaccine</div>
              <div class="row-i"><i class="bi bi-check-circle-fill"></i>Add your child</div>
              <div class="row-i"><i class="bi bi-check-circle-fill"></i>Book a hospital slot</div>
              <div class="row-i"><i class="bi bi-check-circle-fill"></i>Keep the digital record</div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ MARQUEE ============ -->
<?php if ($vaccineNames): ?>
<div class="lp-marquee" aria-hidden="true">
  <div class="track">
    <?php for ($r = 0; $r < 2; $r++): ?>
      <?php foreach ($vaccineNames as $name): ?><span class="item"><?= e($name) ?></span><?php endforeach; ?>
    <?php endfor; ?>
  </div>
</div>
<?php endif; ?>

<!-- ============ ABOUT ============ -->
<section class="lp-sec lp-white" id="about">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6" data-reveal>
        <div class="lp-about-art">
          <div class="ca"><i class="bi bi-hospital"></i></div>
          <div class="cb"><i class="bi bi-shield-check"></i><div class="fw-semibold">Safe from<br>the start</div></div>
        </div>
      </div>
      <div class="col-lg-6" data-reveal>
        <div class="lp-eyebrow">About VaxCare</div>
        <h2 class="lp-h2">A simpler, smarter way to <strong>protect your child</strong></h2>
        <p class="lp-lead">Keeping up with childhood vaccinations should not mean searching for clinics, chasing paper cards or guessing what is due next. VaxCare brings the whole journey together, from the first dose at birth to the last booster.</p>
        <p class="lp-lead">Add your child once, choose a hospital that suits your family and keep every record safe, ready to print or share whenever you need it.</p>
        <?php foreach ($bars as [$label, $pct]): ?>
          <div class="lp-progress">
            <div class="top"><span><?= e($label) ?></span><span><?= (int)$pct ?>%</span></div>
            <div class="bar"><span style="width: <?= (int)$pct ?>%"></span></div>
          </div>
        <?php endforeach; ?>
        <a href="<?= BASE_URL ?>/register.php?role=parent" class="btn btn-primary btn-pill px-4 mt-2">Create your free account <i class="bi bi-arrow-right ms-1"></i></a>
      </div>
    </div>

    <div class="lp-statrow">
      <?php foreach ($stats as [$value, $label]): ?>
        <div class="lp-stat" data-reveal>
          <div class="n"><span data-count="<?= $value ?>"><?= $value ?></span></div>
          <div class="l"><?= e($label) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============ VACCINES + CTA (gehra navy) ============ -->
<section class="lp-dark" id="vaccines">
  <div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-5" data-reveal>
      <div>
        <div class="lp-eyebrow">What we cover</div>
        <h2 class="lp-h2 mb-0">Routine vaccines from birth to <strong><?= e(age_label($maxDays)) ?></strong></h2>
      </div>
      <a href="#how" class="btn btn-outline-light btn-pill px-4">See how it works</a>
    </div>

    <div class="row g-4">
      <?php foreach ($stages as $st): ?>
        <div class="col-md-6 col-lg-4" data-reveal>
          <div class="lp-cover-card <?= e($st['tone']) ?>">
            <div class="hd"><i class="bi <?= e($st['icon']) ?>"></i></div>
            <div class="bd">
              <div class="age"><?= e($st['range']) ?></div>
              <h5><?= e($st['title']) ?></h5>
              <p><?= e($st['text']) ?> Includes <?= e(names_text($st['names'])) ?>.</p>
              <a href="<?= BASE_URL ?>/register.php?role=parent" class="more">Learn more <i class="bi bi-arrow-right"></i></a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="lp-cta" data-reveal>
      <h2>Give your child the <strong>healthiest start</strong></h2>
      <p class="mb-4 opacity-75">Free to join. Set up your child's schedule in a few minutes.</p>
      <?php if (is_logged_in()): ?>
        <a href="<?= BASE_URL ?>/<?= e(current_role()) ?>/dashboard.php" class="btn btn-coral btn-lg btn-pill px-5">Go to Dashboard</a>
      <?php else: ?>
        <a href="<?= BASE_URL ?>/register.php?role=parent" class="btn btn-coral btn-lg btn-pill px-5">Create your free account</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="lp-wave" aria-hidden="true">
    <svg viewBox="0 0 1440 90" preserveAspectRatio="none"><path d="M0,50 C240,95 480,5 760,40 C1020,72 1240,84 1440,28 L1440,90 L0,90 Z" fill="#ffffff"/></svg>
  </div>
</section>

<!-- ============ HOW IT WORKS ============ -->
<section class="lp-sec lp-white" id="how">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-4" data-reveal>
        <div class="lp-eyebrow">How it works</div>
        <h2 class="lp-h2">Three simple steps to a <strong>protected child</strong></h2>
        <p class="lp-lead">From signing up to a complete record, everything happens in one place, on your phone or computer.</p>
      </div>
      <div class="col-lg-8">
        <div class="row g-3">
          <?php foreach ([['bi-person-plus', 'Create an account', 'Sign up and add your child\'s date of birth. Your personal schedule is ready instantly.'],
                          ['bi-calendar2-check', 'Book a visit', 'Choose the vaccine, a hospital near you, and a date and time that works for your family.'],
                          ['bi-journal-check', 'Keep the record', 'After the visit, the dose is added to your child\'s record, ready to view or print.']] as $i => [$icon, $title, $text]): ?>
            <div class="col-md-4" data-reveal>
              <div class="lp-step">
                <div class="ic"><i class="bi <?= $icon ?>"></i></div>
                <div class="no">STEP 0<?= $i + 1 ?></div>
                <h5><?= e($title) ?></h5>
                <p><?= e($text) ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ SPOTLIGHT 1: reminders ============ -->
<section class="lp-sec lp-soft spot">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6" data-reveal>
        <div class="spot-eyebrow">Smart reminders</div>
        <h3>Always know what is due next</h3>
        <p class="text-muted">VaxCare turns your child's date of birth into a clear vaccination schedule and reminds you before every appointment.</p>
        <ul class="check-list">
          <li><i class="bi bi-check-circle-fill"></i>Due, overdue and upcoming vaccines at a glance</li>
          <li><i class="bi bi-check-circle-fill"></i>Reminders for appointments in the next 7 days</li>
          <li><i class="bi bi-check-circle-fill"></i>Add vaccines your child already received</li>
        </ul>
        <a href="<?= BASE_URL ?>/register.php?role=parent" class="btn btn-primary btn-pill px-4">Get started free</a>
      </div>
      <div class="col-lg-6" data-reveal>
        <div class="mock-window">
          <div class="mock-bar"><span></span><span></span><span></span></div>
          <div class="mock-body">
            <div class="mock-alert">
              <i class="bi bi-bell-fill"></i>
              <div><strong>Reminder:</strong> you have 1 appointment in the next 7 days.<br>
                <small class="text-muted">Sample Child: Pentavalent 2, <strong>Tomorrow</strong> (10:00 AM)</small></div>
            </div>
            <div class="d-flex justify-content-between small mb-1"><span class="text-muted">Progress</span><span>6 of 10 done</span></div>
            <div class="progress mb-3" style="height:8px;"><div class="progress-bar bg-success" style="width:60%"></div></div>
            <div class="hero-row"><span>Pentavalent 2<br><small class="text-muted">Appointment tomorrow</small></span><span class="badge rounded-pill text-bg-info">Booked</span></div>
            <div class="hero-row"><span>Pneumococcal 1<br><small class="text-muted">Due since 2 weeks ago</small></span><span class="badge rounded-pill text-bg-warning">Due now</span></div>
            <div class="hero-row"><span>Measles 1<br><small class="text-muted">Due in 3 months</small></span><span class="badge rounded-pill text-bg-secondary">Upcoming</span></div>
          </div>
        </div>
        <div class="sample-note">Sample preview</div>
      </div>
    </div>
  </div>
</section>

<!-- ============ SPOTLIGHT 2: certificate ============ -->
<section class="lp-sec lp-white spot">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6 order-lg-2" data-reveal>
        <div class="spot-eyebrow">Digital certificate</div>
        <h3>A certificate you can print and verify</h3>
        <p class="text-muted">Download a professional certificate with every completed vaccine. Anyone can confirm that it is genuine using its certificate number.</p>
        <ul class="check-list">
          <li><i class="bi bi-check-circle-fill"></i>Print it or save it as a PDF</li>
          <li><i class="bi bi-check-circle-fill"></i>Unique certificate number that cannot be forged</li>
          <li><i class="bi bi-check-circle-fill"></i>Free public page to verify a certificate</li>
        </ul>
        <a href="<?= BASE_URL ?>/verify.php" class="btn btn-outline-primary btn-pill px-4"><i class="bi bi-patch-check me-2"></i>Verify a certificate</a>
      </div>
      <div class="col-lg-6 order-lg-1" data-reveal>
        <div class="mock-cert">
          <img src="<?= BASE_URL ?>/assets/img/logo.svg" alt="" width="42" height="42">
          <div class="t">Vaccination Certificate</div>
          <div class="text-muted small mt-2">This is to certify that</div>
          <div class="nm">Sample Child</div>
          <div class="table-responsive">
            <table class="table table-sm table-bordered mb-3">
              <thead class="table-light"><tr><th>Vaccine</th><th>Date</th><th>Hospital</th></tr></thead>
              <tbody>
                <tr><td>BCG</td><td>12 Jan 2026</td><td>City Hospital</td></tr>
                <tr><td>Pentavalent 1</td><td>03 Mar 2026</td><td>Care Clinic</td></tr>
                <tr><td>Pentavalent 2</td><td>14 Apr 2026</td><td>City Hospital</td></tr>
              </tbody>
            </table>
          </div>
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 small text-muted">
            <span>No. VX-00000-XXXXXXXX</span>
            <span class="stamp"><i class="bi bi-patch-check-fill"></i>Verified</span>
          </div>
        </div>
        <div class="sample-note">Sample preview</div>
      </div>
    </div>
  </div>
</section>

<!-- ============ WHY CHOOSE ============ -->
<section class="lp-sec lp-soft">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6" data-reveal>
        <div class="lp-eyebrow">Why choose VaxCare</div>
        <h2 class="lp-h2">Exceptional care for <strong>every child</strong></h2>
        <p class="lp-lead mb-3">We make routine vaccination easier for busy families, so no vaccine is missed and no record is lost.</p>
        <?php foreach ([['bi-hospital', 'Trusted hospitals', 'Book at hospitals that have been verified by our team.'],
                        ['bi-calendar2-week', 'Convenient scheduling', 'Pick a hospital, a date and a time that fit around your day.'],
                        ['bi-journal-medical', 'Complete records', 'Every dose is kept in one place and can be printed whenever you need it.'],
                        ['bi-bell', 'Clear due dates', 'See what is due, what is booked and what is done at a glance.']] as [$icon, $title, $text]): ?>
          <div class="lp-why-item">
            <div class="ic"><i class="bi <?= $icon ?>"></i></div>
            <div><h6><?= e($title) ?></h6><p><?= e($text) ?></p></div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="col-lg-6" data-reveal>
        <div class="lp-why-art" aria-hidden="true">
          <div class="dots"></div>
          <div class="c1"><i class="bi bi-heart-pulse"></i></div>
          <div class="c2"><i class="bi bi-people"></i></div>
          <div class="c3"><i class="bi bi-plus-lg"></i></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ HOSPITALS ============ -->
<section class="lp-sec lp-white" id="hospitals">
  <div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4" data-reveal>
      <div>
        <div class="lp-eyebrow">Hospitals</div>
        <h2 class="lp-h2 mb-1">Find a hospital <strong>close to you</strong></h2>
        <p class="lp-lead mb-0">Browse the hospitals that are verified and ready to take bookings.</p>
      </div>
    </div>

    <?php if (!$hospitals): ?>
      <div class="card"><div class="card-body p-5 text-center text-muted">Hospitals will appear here as soon as they are approved.</div></div>
    <?php else: ?>
      <div class="near-panel mb-4" id="nearPanel" data-reveal>
        <div class="near-ico"><i class="bi bi-geo-alt-fill"></i></div>
        <div class="near-txt">
          <div class="near-title" id="nearTitle">Find hospitals near you</div>
          <div class="near-sub" id="nearSub">Share your location and we will list the closest verified hospitals first, with distance and directions.</div>
        </div>
        <div class="near-ctrl">
          <div class="near-radius d-none" id="nearRadius" role="group" aria-label="Distance">
            <?php foreach ([5 => '5 km', 10 => '10 km', 25 => '25 km', 0 => 'Any'] as $km => $lbl): ?>
              <button type="button" class="<?= $km === 0 ? 'active' : '' ?>" data-km="<?= $km ?>"><?= $lbl ?></button>
            <?php endforeach; ?>
          </div>
          <button type="button" class="btn btn-light btn-pill px-4 fw-semibold" id="hospNearMe">
            <span class="spinner-border spinner-border-sm me-2 d-none" aria-hidden="true"></span><i class="bi bi-crosshair me-2"></i><span class="lbl">Use my location</span>
          </button>
        </div>
      </div>

      <div class="d-flex flex-wrap align-items-center gap-2 mb-4" data-reveal>
        <button type="button" class="lp-chip active" data-hosp-city="all">All areas<span class="n"><?= count($hospitals) ?></span></button>
        <?php foreach ($cityCounts as $city => $count): ?>
          <button type="button" class="lp-chip" data-hosp-city="<?= e(mb_strtolower($city)) ?>"><?= e($city) ?><span class="n"><?= (int)$count ?></span></button>
        <?php endforeach; ?>
        <div class="ms-lg-auto" style="min-width: 220px;">
          <input type="search" id="hospSearch" class="form-control form-control-sm rounded-pill px-3" placeholder="Search hospital or area..." aria-label="Search hospitals">
        </div>
      </div>


      <div class="row g-3" id="hospGrid">
        <?php foreach ($hospitals as $h): ?>
          <div class="col-md-6 col-xl-4 lp-hosp-item" data-city="<?= e(mb_strtolower(trim($h['location']))) ?>"
               data-lat="<?= e((string)$h['latitude']) ?>" data-lng="<?= e((string)$h['longitude']) ?>" data-approx="<?= (int)$h['approx'] ?>">
            <div class="lp-hosp">
              <div class="near-tags d-none"><span class="near-best d-none"><i class="bi bi-star-fill me-1"></i>Nearest</span><span class="near-dist"></span></div>
              <h6><?= e($h['hospital_name']) ?></h6>
              <div class="meta"><i class="bi bi-geo-alt-fill"></i><?= e($h['location']) ?></div>
              <div class="meta"><i class="bi bi-signpost-2"></i><?= e($h['address']) ?></div>
              <?php if (!empty($h['phone'])): ?><div class="meta"><i class="bi bi-telephone-fill"></i><?= e($h['phone']) ?></div><?php endif; ?>
              <div class="acts">
                <a href="<?= e($bookUrl) ?>" class="btn btn-sm btn-primary btn-pill px-3">Book vaccine</a>
                <a href="https://www.google.com/maps/search/?api=1&amp;query=<?= e(rawurlencode($h['hospital_name'] . ', ' . $h['address'] . ', ' . $h['location'])) ?>"
                   target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary btn-pill px-3 hosp-dir"
                   data-dest="<?= e($h['hospital_name'] . ', ' . $h['address'] . ', ' . $h['location']) ?>"><i class="bi bi-compass me-1"></i>Directions</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="text-center mt-3 d-md-none" id="hospMoreWrap"><button type="button" class="btn btn-outline-primary btn-pill px-4" id="hospMore">Show all hospitals</button></div>
      <div id="hospEmpty" class="text-center text-muted py-4 d-none">No hospital matches your search. Try a bigger distance or another area.</div>
    <?php endif; ?>
  </div>
</section>

<!-- ============ CONTACT ============ -->
<section class="lp-sec lp-soft" id="contact">
  <div class="container">
    <div class="text-center mx-auto mb-5" style="max-width: 640px;" data-reveal>
      <div class="lp-eyebrow">Contact</div>
      <h2 class="lp-h2">Get in <strong>touch</strong></h2>
      <p class="lp-lead mb-0">Questions about VaxCare, hospital registration or your account? Send us a message.</p>
    </div>

    <div class="card contact-wrap" data-reveal>
      <div class="row g-0">
        <div class="col-lg-5 contact-panel">
          <h3>Contact information</h3>
          <p>Fill in the form and our team will get back to you by email.</p>
          <div class="contact-item"><span class="ci"><i class="bi bi-envelope"></i></span>
            <div><small>Email</small><a href="mailto:support@vaxcare.com">support@vaxcare.com</a></div></div>
          <div class="contact-item"><span class="ci"><i class="bi bi-telephone"></i></span>
            <div><small>Phone</small><a href="tel:+923000000000">+92 300 0000000</a></div></div>
          <div class="contact-item"><span class="ci"><i class="bi bi-geo-alt"></i></span>
            <div><small>Address</small>Karachi, Pakistan</div></div>
          <div class="contact-item"><span class="ci"><i class="bi bi-clock"></i></span>
            <div><small>Working hours</small>Monday to Saturday, 9 AM to 6 PM</div></div>
        </div>

        <div class="col-lg-7 p-4 p-md-5">
          <h4 class="mb-1">Send us a message</h4>
          <p class="text-muted small mb-4">All fields are required.</p>

          <form method="post" action="<?= BASE_URL ?>/contact_submit.php" class="needs-validation" novalidate>
            <?= csrf_field() ?>
            <!-- Spam jaal: insaan ko nazar nahi aata -->
            <div class="d-none" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

            <div class="row g-3">
              <div class="col-md-6">
                <label for="c_name" class="form-label">Your name</label>
                <input type="text" class="form-control" id="c_name" name="name" value="<?= e($contactName) ?>" minlength="2" maxlength="80" required>
                <div class="invalid-feedback">Please enter your name.</div>
              </div>
              <div class="col-md-6">
                <label for="c_email" class="form-label">Email</label>
                <input type="email" class="form-control" id="c_email" name="email" value="<?= e($contactEmail) ?>" maxlength="120" required>
                <div class="invalid-feedback">Please enter a valid email.</div>
              </div>
              <div class="col-12">
                <label for="c_subject" class="form-label">Subject</label>
                <select class="form-select" id="c_subject" name="subject" required>
                  <option value="">Choose a subject</option>
                  <?php foreach (['General question', 'Hospital registration', 'Technical problem', 'Feedback'] as $sub): ?>
                    <option value="<?= $sub ?>" <?= $contactSubject === $sub ? 'selected' : '' ?>><?= $sub ?></option>
                  <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">Please choose a subject.</div>
              </div>
              <div class="col-12">
                <label for="c_message" class="form-label">Message</label>
                <textarea class="form-control" id="c_message" name="message" rows="5" minlength="10" maxlength="2000"
                          data-counter="#msgCount" placeholder="How can we help you?" required><?= e($contactMessage) ?></textarea>
                <div class="d-flex justify-content-between">
                  <div class="invalid-feedback">Please write at least 10 characters.</div>
                  <small class="text-muted ms-auto" id="msgCount">0 / 2000</small>
                </div>
              </div>
            </div>
            <button type="submit" class="btn btn-primary btn-pill px-4 mt-3"><i class="bi bi-send me-2"></i>Send message</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ VALUES ============ -->
<section class="lp-sec lp-white">
  <div class="container">
    <div class="mb-5" data-reveal>
      <div class="lp-eyebrow">VaxCare values</div>
      <h2 class="lp-h2 mb-0">Our core <strong>values</strong></h2>
    </div>
    <div class="row g-4">
      <?php foreach ([['v1', 'bi-shield-check', 'Safety first', 'We follow the national immunization schedule and work with verified hospitals so every dose is given with care.'],
                      ['v2', 'bi-chat-heart', 'Clear communication', 'You always know where your child stands: what is due, what is booked and what is already done.'],
                      ['v3', 'bi-people', 'Care for every family', 'Simple, free and easy to use on any phone, so every family can keep their child protected.']] as [$cls, $icon, $title, $text]): ?>
        <div class="col-md-4" data-reveal>
          <div class="lp-value <?= $cls ?>">
            <i class="bi <?= $icon ?> big"></i>
            <h5><?= e($title) ?></h5>
            <p><?= e($text) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============ FAQ ============ -->
<section class="lp-sec lp-soft" id="faq">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-5" data-reveal>
        <div class="lp-eyebrow">FAQ</div>
        <h2 class="lp-h2">Questions parents <strong>often ask</strong></h2>
        <p class="lp-lead">Can't find what you are looking for? Reach out and we will be happy to help.</p>
        <div class="lp-help">
          <div class="ic"><i class="bi bi-headset"></i></div>
          <div><div class="fw-semibold">We're here to help</div>
            <a href="#contact" class="small text-decoration-none">Send us a message</a></div>
        </div>
      </div>
      <div class="col-lg-7" data-reveal>
        <div class="accordion" id="faqList">
          <?php foreach ($faqs as $i => [$q, $a]): ?>
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button <?= $i === 0 ? '' : 'collapsed' ?>" type="button"
                        data-bs-toggle="collapse" data-bs-target="#faq<?= $i ?>"
                        aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>" aria-controls="faq<?= $i ?>">
                  <?= e($q) ?>
                </button>
              </h2>
              <div id="faq<?= $i ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>" data-bs-parent="#faqList">
                <div class="accordion-body text-muted"><?= e($a) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
