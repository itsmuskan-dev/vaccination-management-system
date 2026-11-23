<?php
// admin/stock.php - Admin saare hospitals ka vaccine stock ek jagah dekhta hai
// Low stock (< 10) aur khatam stock rang se nazar aata hai.
// Admin kisi hospital ki kisi vaccine ki booking pause/resume bhi kar sakta hai:
// pause ho to parent us hospital mein us vaccine ki booking nahi kar sakta.
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

define('LOW_STOCK', 10);   // is se kam doses hon to low stock

// ---------- Booking pause / resume ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Session expired. Please try again.');
        redirect('admin/stock.php');
    }

    $hospitalId = (int)($_POST['hospital_id'] ?? 0);
    $vaccineId  = (int)($_POST['vaccine_id'] ?? 0);

    $stmt = $pdo->prepare('SELECT id FROM hospitals WHERE id = ?');
    $stmt->execute([$hospitalId]);
    $hospitalOk = (bool)$stmt->fetch();
    $stmt = $pdo->prepare('SELECT vaccine_name FROM vaccines WHERE id = ?');
    $stmt->execute([$vaccineId]);
    $vaccineName = $stmt->fetchColumn();

    if (!$hospitalOk || $vaccineName === false) {
        set_flash('danger', 'Hospital or vaccine not found.');
    } else {
        // Row na ho to quantity 0 ke saath bana kar pause kar do, ho to pause ulta kar do
        $pdo->prepare(
            'INSERT INTO vaccine_stock (hospital_id, vaccine_id, quantity, booking_paused)
             VALUES (?, ?, 0, 1)
             ON DUPLICATE KEY UPDATE booking_paused = 1 - booking_paused'
        )->execute([$hospitalId, $vaccineId]);

        $stmt = $pdo->prepare('SELECT booking_paused FROM vaccine_stock WHERE hospital_id = ? AND vaccine_id = ?');
        $stmt->execute([$hospitalId, $vaccineId]);
        $paused = (int)$stmt->fetchColumn() === 1;
        set_flash('success', 'Bookings for ' . $vaccineName . ' at this hospital are now ' . ($paused ? 'PAUSED.' : 'OPEN.'));
    }
    redirect('admin/stock.php');
}

// ---------- Data: hospitals, vaccines aur stock ka map ----------
$hospitals = $pdo->query('SELECT id, hospital_name, location FROM hospitals ORDER BY hospital_name')->fetchAll();
$vaccines  = $pdo->query('SELECT id, vaccine_name FROM vaccines ORDER BY recommended_age_days ASC, id ASC')->fetchAll();

$stockMap = [];   // $stockMap[hospital_id][vaccine_id] = ['quantity' => ..., 'booking_paused' => ...]
foreach ($pdo->query('SELECT hospital_id, vaccine_id, quantity, booking_paused FROM vaccine_stock')->fetchAll() as $s) {
    $stockMap[(int)$s['hospital_id']][(int)$s['vaccine_id']] = $s;
}
$hospitalNames = [];
foreach ($hospitals as $h) {
    $hospitalNames[(int)$h['id']] = $h['hospital_name'];
}

// Pehle sirf masla wali rows: low stock, khatam stock, ya paused
$alerts = [];
foreach ($stockMap as $hid => $byVaccine) {
    foreach ($byVaccine as $vid => $s) {
        if ((int)$s['quantity'] < LOW_STOCK || (int)$s['booking_paused'] === 1) {
            $alerts[] = ['hospital_id' => $hid, 'vaccine_id' => $vid] + $s;
        }
    }
}
$vaccineNames = [];
foreach ($vaccines as $v) {
    $vaccineNames[(int)$v['id']] = $v['vaccine_name'];
}

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
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="mb-4">
  <h4 class="mb-1">Vaccine Stock</h4>
  <p class="text-muted mb-0">Stock of every vaccine at every hospital. Less than <?= LOW_STOCK ?> doses is low stock. When stock is 0 or bookings are paused, parents cannot book that vaccine at that hospital.</p>
</div>

<!-- ============ Pehle alerts: low / out / paused ============ -->
<div class="card mb-4">
  <div class="card-body">
    <h5 class="mb-3"><i class="bi bi-exclamation-triangle me-2 text-warning"></i>Needs attention (<?= count($alerts) ?>)</h5>
    <?php if (!$alerts): ?>
      <p class="text-muted mb-0">All hospitals have enough stock. Nothing to worry about.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover table-stack mb-0">
          <thead>
            <tr><th>Hospital</th><th>Vaccine</th><th>Doses left</th><th>Status</th><th class="text-end">Booking</th></tr>
          </thead>
          <tbody>
            <?php foreach ($alerts as $a): ?>
              <tr>
                <td data-label="Hospital" class="fw-semibold"><?= e($hospitalNames[$a['hospital_id']] ?? '-') ?></td>
                <td data-label="Vaccine"><?= e($vaccineNames[$a['vaccine_id']] ?? '-') ?></td>
                <td data-label="Doses left" class="fw-semibold"><?= (int)$a['quantity'] ?></td>
                <td data-label="Status">
                  <?= stock_badge($a['quantity']) ?>
                  <?php if ((int)$a['booking_paused'] === 1): ?>
                    <span class="badge rounded-pill text-bg-dark">Paused</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <form method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="hospital_id" value="<?= (int)$a['hospital_id'] ?>">
                    <input type="hidden" name="vaccine_id" value="<?= (int)$a['vaccine_id'] ?>">
                    <button type="submit" class="btn btn-sm <?= (int)$a['booking_paused'] === 1 ? 'btn-outline-success' : 'btn-outline-danger' ?>">
                      <i class="bi bi-<?= (int)$a['booking_paused'] === 1 ? 'play-circle' : 'pause-circle' ?> me-1"></i><?= (int)$a['booking_paused'] === 1 ? 'Resume booking' : 'Pause booking' ?>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ============ Poora overview: hospital ke hisaab se ============ -->
<?php foreach ($hospitals as $h): ?>
  <div class="card mb-4">
    <div class="card-body">
      <h5 class="mb-3"><i class="bi bi-hospital me-2 text-primary"></i><?= e($h['hospital_name']) ?> <small class="text-muted fw-normal"><?= e($h['location']) ?></small></h5>
      <div class="table-responsive">
        <table class="table table-hover table-stack mb-0">
          <thead>
            <tr><th>Vaccine</th><th>Doses left</th><th>Status</th><th class="text-end">Booking</th></tr>
          </thead>
          <tbody>
            <?php foreach ($vaccines as $v): ?>
              <?php
                $s       = $stockMap[(int)$h['id']][(int)$v['id']] ?? null;
                $qty     = $s === null ? null : (int)$s['quantity'];
                $paused  = $s !== null && (int)$s['booking_paused'] === 1;
                $rowCls  = $qty === 0 ? 'table-danger' : ($qty !== null && $qty < LOW_STOCK ? 'table-warning' : '');
              ?>
              <tr class="<?= $rowCls ?>">
                <td data-label="Vaccine"><?= e($v['vaccine_name']) ?></td>
                <td data-label="Doses left" class="fw-semibold"><?= $qty === null ? '-' : $qty ?></td>
                <td data-label="Status">
                  <?= stock_badge($qty) ?>
                  <?php if ($paused): ?>
                    <span class="badge rounded-pill text-bg-dark">Paused</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <form method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="hospital_id" value="<?= (int)$h['id'] ?>">
                    <input type="hidden" name="vaccine_id" value="<?= (int)$v['id'] ?>">
                    <button type="submit" class="btn btn-sm <?= $paused ? 'btn-outline-success' : 'btn-outline-danger' ?>">
                      <i class="bi bi-<?= $paused ? 'play-circle' : 'pause-circle' ?> me-1"></i><?= $paused ? 'Resume booking' : 'Pause booking' ?>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
