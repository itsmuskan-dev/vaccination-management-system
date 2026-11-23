<?php
// notification_open.php?id=5 - Paigham par click: parha hua mark karo aur us ke link par bhejo
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/config/db.php';

$id     = (int)($_GET['id'] ?? 0);
$userId = (int)$_SESSION['user_id'];

// "AND user_id = ?": koi aur ka paigham nahi khul sakta
$stmt = $pdo->prepare('SELECT link FROM notifications WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $userId]);
$n = $stmt->fetch();

if ($n) {
    $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([$id, $userId]);
    // Sirf project ke andar ka saada raasta (bahar ki website par nahi bhejte)
    if ($n['link'] && preg_match('#^[A-Za-z0-9_/\-\.]+(\?[A-Za-z0-9=&_\-\.%]*)?$#', $n['link'])) {
        redirect($n['link']);
    }
}
redirect('notifications.php');
