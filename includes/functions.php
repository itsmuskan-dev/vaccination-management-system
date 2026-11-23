<?php
// includes/functions.php - chhote helper functions jo baar baar kaam aayenge

// e() = escape. User ka likha hua text screen par dikhane se pehle yahan se guzaro.
// Is se XSS attack (<script> inject karna) nahi hota.
function e($text)
{
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

// Dusre page par bhej do
function redirect($path)
{
    header('Location: ' . BASE_URL . '/' . ltrim($path, '/'));
    exit;
}

// Flash message: ek baar dikhne wala message (e.g. "Hospital added successfully")
// type: success / danger / warning / info  (Bootstrap alert ke colors)
function set_flash($type, $message)
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash()
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);   // dikhane ke baad hata do
        return $flash;
    }
    return null;
}

// Login status check (Day 3 mein login banate waqt session mein ye cheezein set hongi)
function is_logged_in()
{
    return isset($_SESSION['user_id']);
}

function current_role()
{
    return $_SESSION['role'] ?? null;
}

// Date ko khoobsurat format mein: 2026-10-20 -> 20 Oct 2026
function format_date($date)
{
    return $date ? date('d M Y', strtotime($date)) : '-';
}

// Status ke hisaab se rangeen badge (Bootstrap badge)
function status_badge($status)
{
    $map = [
        'pending'        => ['warning', 'Pending'],
        'approved'       => ['success', 'Approved'],
        'rejected'       => ['danger',  'Rejected'],
        'vaccinated'     => ['success', 'Vaccinated'],
        'not_vaccinated' => ['secondary', 'Not Vaccinated'],
        'available'      => ['success', 'Available'],
        'unavailable'    => ['danger',  'Unavailable'],
        'active'         => ['success', 'Active'],
        'disabled'       => ['danger',  'Disabled'],
        'verified'       => ['success', 'Verified'],
    ];
    [$color, $label] = $map[$status] ?? ['secondary', ucfirst($status)];
    return '<span class="badge rounded-pill text-bg-' . $color . '">' . $label . '</span>';
}

// ---------- CSRF protection ----------
// Har form mein ek secret token hota hai. Koi dusri website hamare form ko chori se
// submit nahi kar sakti kyunki use ye token pata nahi hota.
function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Form ke andar csrf_field() ko echo karo (short echo tag ke saath)
function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf()
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// ---------- Asset versioning ----------
// CSS/JS ke link ke aakhir mein file ki last-modified time lag jati hai (?v=123456).
// File badalte hi link badal jata hai, to browser purani cached file use nahi karta.
function asset($path)
{
    $path = ltrim($path, '/');
    $file = __DIR__ . '/../assets/' . $path;
    $version = file_exists($file) ? filemtime($file) : time();
    return BASE_URL . '/assets/' . $path . '?v=' . $version;
}

// ---------- Age helpers (vaccines + children) ----------

// Din ko sab se munasib unit mein todo: 42 din -> [6, 'weeks'], 270 -> [9, 'months']
function split_age_days($days)
{
    $days = (int)$days;
    if ($days > 0) {
        if ($days % 365 === 0) return [intdiv($days, 365), 'years'];
        if ($days % 30 === 0)  return [intdiv($days, 30),  'months'];
        if ($days % 7 === 0)   return [intdiv($days, 7),   'weeks'];
    }
    return [$days, 'days'];
}

// 0 -> "At birth", 42 -> "6 weeks", 270 -> "9 months"
function age_label($days)
{
    if ((int)$days === 0) return 'At birth';
    [$value, $unit] = split_age_days($days);
    if ($value === 1) $unit = rtrim($unit, 's');   // "1 week" (week, weeks nahi)
    return $value . ' ' . $unit;
}

// Bache ki umar DOB se: "1 yr 2 mo", "3 mo", "12 days"
function child_age($dob)
{
    $diff = (new DateTime($dob))->diff(new DateTime('today'));
    if ($diff->y >= 1) return $diff->y . ' yr' . ($diff->m ? ' ' . $diff->m . ' mo' : '');
    if ($diff->m >= 1) return $diff->m . ' mo';
    return $diff->d . ' days';
}

// "2026-10-20" jaisi date sahi hai ya nahi (filters mein URL se aayi date ko parakhne ke liye)
function is_valid_date($date)
{
    $d = DateTime::createFromFormat('Y-m-d', (string)$date);
    return $d && $d->format('Y-m-d') === $date;
}

// CSV/Excel mein "=cmd..." jaisa text formula ban kar chal sakta hai (CSV injection).
// Aise text ke shuru mein ' laga dete hain taake wo sirf text rahe.
function csv_safe($value)
{
    $value = (string)$value;
    return (isset($value[0]) && in_array($value[0], ['=', '+', '-', '@'], true)) ? "'" . $value : $value;
}

// ---------- Hospital helper ----------
// Login hue hospital user ka hospital record laata hai. Na mile to logout.
// Hospital pages mein: $hospital = require_hospital($pdo);
function require_hospital(PDO $pdo)
{
    $stmt = $pdo->prepare('SELECT * FROM hospitals WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $hospital = $stmt->fetch();
    if (!$hospital) {
        redirect('logout.php');
    }
    return $hospital;
}

// Har page ke <head> ke andar: Bootstrap, Icons, Font, aur hamari style.css
function head_assets()
{
    echo '<link rel="icon" type="image/svg+xml" href="' . BASE_URL . '/assets/img/logo.svg">' . "\n  ";
    echo '<meta name="theme-color" content="#27256e">' . "\n  ";
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">' . "\n  ";
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">' . "\n  ";
    echo '<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">' . "\n  ";
    echo '<link href="' . asset('css/style.css') . '" rel="stylesheet">' . "\n";
}

// Har page ke aakhir mein: Bootstrap JS aur hamari script.js
function foot_scripts()
{
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>' . "\n";
    echo '<script src="' . asset('js/script.js') . '"></script>' . "\n";
}

// ---------- Pagination (lambi list ko pages mein todna) ----------
// $p = paginate($total, 15, $_GET['page'] ?? 1);   phir query mein LIMIT/OFFSET (sirf (int) kiye hue number)
function paginate($total, $perPage, $page)
{
    $perPage = max(1, (int)$perPage);
    $pages   = max(1, (int)ceil($total / $perPage));
    $page    = max(1, min((int)$page, $pages));
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage, 'per_page' => $perPage, 'total' => (int)$total];
}

// Neeche ke page numbers. $params = filters jo agle page par bhi rehne chahiyen.
function pagination_html(array $p, array $params = [])
{
    if ($p['pages'] <= 1) {
        return '';
    }
    $link = function ($page) use ($params) {
        return '?' . e(http_build_query(array_merge($params, ['page' => $page])));
    };
    $html  = '<nav aria-label="Pages"><ul class="pagination pagination-sm justify-content-center flex-wrap mb-0 mt-3">';
    $html .= '<li class="page-item ' . ($p['page'] <= 1 ? 'disabled' : '') . '"><a class="page-link" href="' . $link($p['page'] - 1) . '">Previous</a></li>';

    $from = max(1, $p['page'] - 2);
    $to   = min($p['pages'], $p['page'] + 2);
    if ($from > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $link(1) . '">1</a></li>';
        if ($from > 2) { $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
    }
    for ($i = $from; $i <= $to; $i++) {
        $html .= '<li class="page-item ' . ($i === $p['page'] ? 'active' : '') . '"><a class="page-link" href="' . $link($i) . '">' . $i . '</a></li>';
    }
    if ($to < $p['pages']) {
        if ($to < $p['pages'] - 1) { $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
        $html .= '<li class="page-item"><a class="page-link" href="' . $link($p['pages']) . '">' . $p['pages'] . '</a></li>';
    }
    $html .= '<li class="page-item ' . ($p['page'] >= $p['pages'] ? 'disabled' : '') . '"><a class="page-link" href="' . $link($p['page'] + 1) . '">Next</a></li>';
    return $html . '</ul></nav>';
}

// ---------- Certificate number (jaali nahi ban sakta) ----------
// Number = bache ki id + secret se bana hua chhota signature. Secret ke baghair koi sahi number nahi bana sakta.
function certificate_number($childId)
{
    $id  = (int)$childId;
    $sig = strtoupper(substr(hash_hmac('sha256', 'cert:' . $id, CERT_SECRET), 0, 8));
    return 'VX-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT) . '-' . $sig;
}

// Number sahi ho to bache ki id, warna 0
function certificate_child_id($number)
{
    $number = strtoupper(trim((string)$number));
    if (!preg_match('/^VX-(\d{5})-([A-F0-9]{8})$/', $number, $m)) {
        return 0;
    }
    $id = (int)$m[1];
    return hash_equals(certificate_number($id), $number) ? $id : 0;
}

// "Hamza Ali Khan" -> "Hamza A. K."  (public page par poora naam nahi dikhate)
function mask_name($name)
{
    $parts = preg_split('/\s+/', trim((string)$name));
    $first = array_shift($parts);
    $rest  = '';
    foreach ($parts as $part) {
        $rest .= ' ' . mb_strtoupper(mb_substr($part, 0, 1)) . '.';
    }
    return $first . $rest;
}


// ---------- Toast notifications ----------
// Flash message (saved, deleted, error wagera) ko saaf, band hone wale toast ki shakal mein dikhata hai.
// Layout mein render_flash($flash) ko echo karo ($flash = get_flash())
function render_flash($flash)
{
    if (!$flash) {
        return '';
    }
    $types = [
        'success' => ['bi-check-circle-fill',         'Success'],
        'danger'  => ['bi-x-octagon-fill',            'Error'],
        'warning' => ['bi-exclamation-triangle-fill', 'Attention'],
        'info'    => ['bi-info-circle-fill',          'Info'],
    ];
    $type = isset($types[$flash['type']]) ? $flash['type'] : 'info';
    [$icon, $title] = $types[$type];
    // Chhota waqt: success jaldi chala jaye, error thoda ziyada rahe taake parh sakein
    $delays = ['success' => 1800, 'info' => 2200, 'warning' => 2800, 'danger' => 3200];
    $ms     = $delays[$type];

    return '<div class="toast-container toast-pos position-fixed end-0 p-3">'
         . '<div class="toast toast-vax toast-' . $type . '" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="' . $ms . '" style="--dur:' . ($ms / 1000) . 's">'
         .   '<div class="d-flex align-items-start gap-3 p-3">'
         .     '<i class="bi ' . $icon . ' toast-ico"></i>'
         .     '<div class="flex-fill"><div class="fw-semibold">' . $title . '</div>'
         .     '<div class="small text-muted">' . e($flash['message']) . '</div></div>'
         .     '<button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>'
         .   '</div>'
         .   '<div class="bar"></div>'
         . '</div></div>';
}


// ---------- Notifications (ghanti) ----------
// Kisi user ko paigham bhejna. $link project ke andar ka raasta hota hai (jaise 'parent/my_bookings.php').
function notify(PDO $pdo, $userId, $message, $link = null)
{
    if (!$userId) {
        return;
    }
    $pdo->prepare('INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)')
        ->execute([(int)$userId, mb_substr($message, 0, 255), $link]);
}

// Admin ne appointment approve/reject ki: parent ko, aur approve par hospital ko bhi khabar
function notify_booking_decision(PDO $pdo, $bookingId, $status)
{
    $stmt = $pdo->prepare(
        'SELECT b.parent_id, b.appointment_date, b.appointment_time, c.name AS child_name, v.vaccine_name, h.user_id AS hospital_user
         FROM bookings b
         JOIN children c ON c.id = b.child_id
         JOIN vaccines v ON v.id = b.vaccine_id
         JOIN hospitals h ON h.id = b.hospital_id
         WHERE b.id = ?'
    );
    $stmt->execute([$bookingId]);
    $b = $stmt->fetch();
    if (!$b) {
        return;
    }
    $what = $b['child_name'] . ' (' . $b['vaccine_name'] . ')';
    $when = format_date($b['appointment_date']) . ', ' . date('h:i A', strtotime($b['appointment_time']));
    if ($status === 'approved') {
        notify($pdo, $b['parent_id'], 'Your appointment for ' . $what . ' on ' . $when . ' is confirmed. Open your visit token.', 'parent/token.php?id=' . (int)$bookingId);
        notify($pdo, $b['hospital_user'], 'New appointment: ' . $what . ' on ' . $when . '.', 'hospital/appointments.php?view=upcoming');
    } else {
        notify($pdo, $b['parent_id'], 'Your appointment request for ' . $what . ' was not approved. You can book another slot.', 'parent/book_hospital.php');
    }
}

// Hospital (ya admin) ne vaccination likhi: parent ko khabar
function notify_vaccination_result(PDO $pdo, $bookingId, $result)
{
    $stmt = $pdo->prepare(
        'SELECT b.parent_id, b.child_id, c.name AS child_name, v.vaccine_name
         FROM bookings b JOIN children c ON c.id = b.child_id JOIN vaccines v ON v.id = b.vaccine_id
         WHERE b.id = ?'
    );
    $stmt->execute([$bookingId]);
    $b = $stmt->fetch();
    if (!$b) {
        return;
    }
    if ($result === 'vaccinated') {
        notify($pdo, $b['parent_id'], $b['child_name'] . ' has received ' . $b['vaccine_name'] . '. The record and certificate are updated.', 'parent/reports.php?child=' . (int)$b['child_id']);
    } else {
        notify($pdo, $b['parent_id'], $b['child_name'] . ' was marked as not vaccinated for ' . $b['vaccine_name'] . '. Please book a new appointment.', 'parent/book_hospital.php?child=' . (int)$b['child_id']);
    }
}

// Admin ne parent ka darj kiya hua purana record verify/reject kiya
function notify_past_record(PDO $pdo, $recordId, $status)
{
    $stmt = $pdo->prepare(
        'SELECT c.parent_id, c.id AS child_id, c.name AS child_name, v.vaccine_name
         FROM past_vaccinations p JOIN children c ON c.id = p.child_id JOIN vaccines v ON v.id = p.vaccine_id
         WHERE p.id = ?'
    );
    $stmt->execute([$recordId]);
    $r = $stmt->fetch();
    if (!$r) {
        return;
    }
    $text = $status === 'verified'
        ? 'The earlier vaccination you added for ' . $r['child_name'] . ' (' . $r['vaccine_name'] . ') was verified. It now appears on the certificate.'
        : 'The earlier vaccination you added for ' . $r['child_name'] . ' (' . $r['vaccine_name'] . ') could not be verified.';
    notify($pdo, $r['parent_id'], $text, 'parent/past_vaccinations.php?child=' . (int)$r['child_id']);
}

// "5 min ago", "2 hours ago", "3 days ago"
function time_ago($datetime)
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     { return 'just now'; }
    if ($diff < 3600)   { return floor($diff / 60) . ' min ago'; }
    if ($diff < 86400)  { $h = (int)floor($diff / 3600);  return $h . ' hour' . ($h > 1 ? 's' : '') . ' ago'; }
    if ($diff < 604800) { $d = (int)floor($diff / 86400); return $d . ' day'  . ($d > 1 ? 's' : '') . ' ago'; }
    return date('d M Y', strtotime($datetime));
}

// Visit token ka code: TK-00047-4FB1 (aakhri 4 akshar secret se, taake koi andaza na laga sake)
function token_code($bookingId)
{
    return 'TK-' . str_pad((string)(int)$bookingId, 5, '0', STR_PAD_LEFT) . '-'
         . strtoupper(substr(hash_hmac('sha256', 'token' . (int)$bookingId, CERT_SECRET), 0, 4));
}

// Token number: har booking ka apna alag number (booking id se), kabhi do bookings ka same nahi hota
function token_number(PDO $pdo, array $b)
{
    return str_pad((string)(int)$b['id'], 3, '0', STR_PAD_LEFT);
}
