<?php
// parent/appointment_ics.php?id=5 - Appointment ko phone/computer ke calendar mein jodne wali file (.ics)
require_once __DIR__ . '/../includes/auth.php';
require_role('parent');
require_once __DIR__ . '/../config/db.php';

$id = (int)($_GET['id'] ?? 0);

// "AND b.parent_id = ?": sirf apni, aur sirf approved appointment
$stmt = $pdo->prepare(
    "SELECT b.id, b.appointment_date, b.appointment_time, c.name AS child_name, v.vaccine_name,
            h.hospital_name, h.address, h.location
     FROM bookings b
     JOIN children  c ON c.id = b.child_id
     JOIN vaccines  v ON v.id = b.vaccine_id
     JOIN hospitals h ON h.id = b.hospital_id
     WHERE b.id = ? AND b.parent_id = ? AND b.approval_status = 'approved'"
);
$stmt->execute([$id, (int)$_SESSION['user_id']]);
$b = $stmt->fetch();

if (!$b) {
    set_flash('danger', 'Appointment not found.');
    redirect('parent/my_bookings.php');
}

// Calendar file ke khaas akshar (backslash, ; , aur nayi line) ko escape karna zaroori hai
function ics_escape($text)
{
    return str_replace(["\\", ';', ',', "\r\n", "\n", "\r"], ["\\\\", '\\;', '\\,', '\\n', '\\n', '\\n'], (string)$text);
}

$start = new DateTime($b['appointment_date'] . ' ' . $b['appointment_time']);
$end   = (clone $start)->modify('+30 minutes');

$lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//' . APP_NAME . '//Appointments//EN',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'BEGIN:VEVENT',
    'UID:booking-' . (int)$b['id'] . '@' . strtolower(APP_NAME),
    'DTSTAMP:' . gmdate('Ymd\THis\Z'),
    'DTSTART:' . $start->format('Ymd\THis'),          // local waqt (jahan calendar khule wahan ka)
    'DTEND:' . $end->format('Ymd\THis'),
    'SUMMARY:' . ics_escape('Vaccination: ' . $b['child_name'] . ' - ' . $b['vaccine_name']),
    'LOCATION:' . ics_escape($b['hospital_name'] . ', ' . $b['address'] . ', ' . $b['location']),
    'DESCRIPTION:' . ics_escape('Vaccination appointment booked through ' . APP_NAME . '. Please arrive 10 minutes early.'),
    'BEGIN:VALARM',
    'TRIGGER:-P1D',                                     // ek din pehle yaad dilao
    'ACTION:DISPLAY',
    'DESCRIPTION:Vaccination appointment tomorrow',
    'END:VALARM',
    'END:VEVENT',
    'END:VCALENDAR',
];

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="vaccination-appointment-' . (int)$b['id'] . '.ics"');
echo implode("\r\n", $lines) . "\r\n";
