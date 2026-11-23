<?php
// hospital/stock.php - Hospital apna vaccine stock dekhta aur update karta hai
// Har vaccine ki baqi doses yahan dikhti hain. Dose lagte hi stock khud 1 kam ho jata hai
// (hospital/update_status.php). Quantity 10 se kam ho to "Low stock" ka alert banta hai.
require_once __DIR__ . '/../includes/auth.php';
require_role('hospital');
require_once __DIR__ . '/../config/db.php';

$hospital = require_hospital($pdo);
define('LOW_STOCK', 10);   // is se kam doses hon to low stock

// ---------- Stock update (form submit) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Session expired. Please try again.');
        redirect('hospital/stock.php');
    }

    $vaccineId = (int)($_POST['vaccine_id'] ?? 0);
    $quantity  = (int)($_POST['quantity'] ?? -1);

    $stmt = $pdo->prepare('SELECT vaccine_name FROM vaccines WHERE id = ?');
    $stmt->execute([$vaccineId]);
    $vaccineName = $stmt->fetchColumn();

    if ($vaccineName === false) {
        set_flash('danger', 'Vaccine not found.');
    } elseif ($quantity < 0 || $quantity > 100000) {
        set_flash('danger', 'Quantity must be between 0 and 100000.');
    } else {
        // Row pehle se ho to update, na ho to nayi bana do (unique key hospital+vaccine par hai)
        $pdo->prepare(
            'INSERT INTO vaccine_stock (hospital_id, vaccine_id, quantity)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)'
        )->execute([$hospital['id'], $vaccineId, $quantity]);
        set_flash('success', 'Stock updated for ' . $vaccineName . ': ' . $quantity . ' doses.');
    }
    redirect('hospital/stock.php');
}

// ---------- Apna stock: saari vaccines, saath mein apni stock row (ho to) ----------
$stmt = $pdo->prepare(
    'SELECT v.id AS vaccine_id, v.vaccine_name, v.recommended_age,
            s.quantity, s.booking_paused
     FROM vaccines v
     LEFT JOIN vaccine_stock s ON s.vaccine_id = v.id AND s.hospital_id = ?
     ORDER BY v.recommended_age_days ASC, v.id ASC'
);
$stmt->execute([$hospital['id']]);
$rows = $stmt->fetchAll();

$lowCount = 0;
foreach ($rows as $r) {
    if ($r['quantity'] !== null && (int)$r['quantity'] < LOW_STOCK) {
        $lowCount++;
    }
}

// Stock ki halat ke hisaab se badge
function stock_badge($quantity)
{
    if ($quantity === null) {
        return '<span class="badge rounded-pill text-bg-secondary">Not set</span>';
    }
    $q = (int)$quantity;
    if ($q <= 0) {
        return '<span class="badge rounded-pill text-bg-danger">Out of stock</span>';
    }
    if ($q < LOW_STOCK) {
        return '<span class="badge rounded-pill text-bg-warning">Low stock</span>';
    }
    return '<span class="badge rounded-pill text-bg-success">OK</span>';
}

$pageTitle  = 'Vaccine Stock';
$activeMenu = 'stock.php';
require_once __DIR__ . '/../includes/hospital_header.php';
?>

<div class="mb-4">
  <h4 class="mb-1">Vaccine Stock</h4>
  <p class="text-muted mb-0">Doses available at <strong><?= e($hospital['hospital_name']) ?></strong>. When you mark a child as vaccinated, one dose is removed from the stock automatically.</p>
</div>

<?php if ($lowCount > 0): ?>
  <div class="alert alert-warning d-flex align-items-center gap-2">
    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
    <div><strong>Low stock:</strong> <?= $lowCount ?> vaccine<?= $lowCount > 1 ? 's are' : ' is' ?> below <?= LOW_STOCK ?> doses. Please arrange more stock and update the quantity here.</div>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover table-stack mb-0">
        <thead>
          <tr>
            <th>Vaccine</th>
            <th>Doses left</th>
            <th>Status</th>
            <th style="min-width: 240px;">Update stock</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td data-label="Vaccine">
                <div class="fw-semibold"><?= e($r['vaccine_name']) ?></div>
                <small class="text-muted"><?= e($r['recommended_age']) ?></small>
                <?php if (!empty($r['booking_paused'])): ?>
                  <div><span class="badge rounded-pill text-bg-dark mt-1">Bookings paused by admin</span></div>
                <?php endif; ?>
              </td>
              <td data-label="Doses left" class="fw-semibold"><?= $r['quantity'] === null ? '-' : (int)$r['quantity'] ?></td>
              <td data-label="Status"><?= stock_badge($r['quantity']) ?></td>
              <td data-label="Update stock">
                <form method="post" class="d-flex gap-2">
                  <?= csrf_field() ?>
                  <input type="hidden" name="vaccine_id" value="<?= (int)$r['vaccine_id'] ?>">
                  <input type="number" class="form-control form-control-sm" name="quantity"
                         value="<?= $r['quantity'] === null ? 0 : (int)$r['quantity'] ?>"
                         min="0" max="100000" required aria-label="New quantity for <?= e($r['vaccine_name']) ?>">
                  <button type="submit" class="btn btn-sm btn-primary text-nowrap">
                    <i class="bi bi-check-lg me-1"></i>Save
                  </button>
                </form>
                <small class="text-muted">Type the new total number of doses you have.</small>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
            <tr><td colspan="4" class="text-center text-muted py-4">No vaccines in the system yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/hospital_footer.php'; ?>
