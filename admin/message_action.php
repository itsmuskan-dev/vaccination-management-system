<?php
// admin/message_action.php - Paigham read mark karna ya delete karna (koi page nahi dikhta)
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

$qs = $_POST['qs'] ?? '';
if (!preg_match('/^[A-Za-z0-9=&_.%+\-]{0,200}$/', $qs)) { $qs = ''; }
$back = 'admin/messages.php' . ($qs !== '' ? '?' . $qs : '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
    set_flash('danger', 'Invalid request.');
    redirect($back);
}

$id     = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($action === 'read') {
    $pdo->prepare("UPDATE contact_messages SET status = 'read' WHERE id = ?")->execute([$id]);
    set_flash('success', 'Marked as read.');
} elseif ($action === 'delete') {
    $stmt = $pdo->prepare('DELETE FROM contact_messages WHERE id = ?');
    $stmt->execute([$id]);
    set_flash($stmt->rowCount() > 0 ? 'success' : 'warning', $stmt->rowCount() > 0 ? 'Message deleted.' : 'Message not found.');
} else {
    set_flash('danger', 'Invalid action.');
}
redirect($back);
