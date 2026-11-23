<?php
// contact_submit.php - Landing page ke contact form ka kaam (koi page nahi dikhta)
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/db.php';

$back = 'index.php#contact';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
    set_flash('danger', 'Session expired. Please try again.');
    redirect($back);
}

// ---------- Spam jaal (honeypot): insaan ko ye khana nazar nahi aata, bots bhar dete hain ----------
if (trim($_POST['website'] ?? '') !== '') {
    set_flash('success', 'Thank you! We have received your message.');   // bot ko pata na chale
    redirect($back);
}

$subjects = ['General question', 'Hospital registration', 'Technical problem', 'Feedback'];

$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

// Galti hone par form ki values wapas bhar dene ke liye
$_SESSION['contact_old'] = ['name' => $name, 'email' => $email, 'subject' => $subject, 'message' => $message];

$errors = [];
if (mb_strlen($name) < 2 || mb_strlen($name) > 80)                   { $errors[] = 'Please enter your name.'; }
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) { $errors[] = 'Please enter a valid email.'; }
if (!in_array($subject, $subjects, true))                             { $errors[] = 'Please choose a subject.'; }
if (mb_strlen($message) < 10)                                         { $errors[] = 'Your message is too short (at least 10 characters).'; }
if (mb_strlen($message) > 2000)                                       { $errors[] = 'Your message is too long (maximum 2000 characters).'; }

if ($errors) {
    set_flash('danger', $errors[0]);
    redirect($back);
}

// ---------- Rate limit: 30 second mein ek, aur ek IP se ek ghante mein 5 se ziyada nahi ----------
if (isset($_SESSION['last_contact']) && time() - $_SESSION['last_contact'] < 30) {
    set_flash('warning', 'Please wait a few seconds before sending another message.');
    redirect($back);
}
$ip   = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
$stmt = $pdo->prepare('SELECT COUNT(*) FROM contact_messages WHERE ip = ? AND created_at >= ?');
$stmt->execute([$ip, date('Y-m-d H:i:s', strtotime('-1 hour'))]);
if ((int)$stmt->fetchColumn() >= 5) {
    set_flash('warning', 'You have sent several messages recently. Please try again later.');
    redirect($back);
}

$stmt = $pdo->prepare('INSERT INTO contact_messages (name, email, subject, message, ip) VALUES (?, ?, ?, ?, ?)');
$stmt->execute([$name, $email, $subject, $message, $ip]);

unset($_SESSION['contact_old']);
$_SESSION['last_contact'] = time();
set_flash('success', 'Thank you, ' . $name . '! We have received your message.');
redirect($back);
