<?php
// admin/hospital_form.php - Hospital Add (Create) aur Edit (Update), dono ek hi file se
//   hospital_form.php        -> naya hospital
//   hospital_form.php?id=3   -> hospital 3 ko edit
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

// City ab free text nahi: isi list se dropdown banta hai aur server par yehi list check hoti hai
$cities = require __DIR__ . '/../includes/cities.php';

$id     = (int)($_GET['id'] ?? 0);      // (int) se URL mein kuch aur likha ho to 0 ban jata hai
$isEdit = $id > 0;
$data   = ['hospital_name' => '', 'address' => '', 'location' => '', 'phone' => '', 'license_no' => '', 'contact_email' => '', 'latitude' => '', 'longitude' => ''];
$errors = [];
$userId = null;   // hospital ka login account (users table)

// Edit mode: pehle purana data nikalo
if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM hospitals WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        set_flash('danger', 'Hospital not found.');
        redirect('admin/hospitals.php');
    }
    $data   = array_map('strval', array_intersect_key($row, $data));
    $userId = $row['user_id'] ? (int)$row['user_id'] : null;
}

// Form submit hua
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($data as $key => $value) {
        $data[$key] = trim($_POST[$key] ?? '');
    }
    $password        = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if (!verify_csrf()) {
        $errors[] = 'Session expired. Please try again.';
    }
    if (mb_strlen($data['hospital_name']) < 3 || mb_strlen($data['hospital_name']) > 150) {
        $errors[] = 'Hospital name must be between 3 and 150 characters.';
    }
    if ($data['address'] === '') {
        $errors[] = 'Address is required.';
    }
    if (!in_array($data['location'], $cities, true)) {
        $errors[] = 'Please select your city from the list.';
    }
    if (!preg_match('/^[0-9+\-\s]{10,15}$/', $data['phone'])) {
        $errors[] = 'Please enter a valid phone number.';
    }
    if (!filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL) || strlen($data['contact_email']) > 120) {
        $errors[] = 'Please enter a valid contact email.';
    } else {
        // Email users table mein unique hai (yahi hospital ka login email hai)
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?' . ($userId ? ' AND id <> ?' : ''));
        $stmt->execute($userId ? [$data['contact_email'], $userId] : [$data['contact_email']]);
        if ($stmt->fetch()) {
            $errors[] = 'This email is already used by another account.';
        }
    }
    if (!preg_match('/^[A-Za-z0-9\-\/\s]{3,60}$/', $data['license_no'])) {
        $errors[] = 'License number is required (3 to 60 letters, numbers, - or /).';
    }
    // Naye hospital (ya bina account wale) ke liye password zaroori; edit par khali chhoro to purana rehta hai
    $needPassword = !$isEdit || !$userId;
    if ($needPassword || $password !== '') {
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        } elseif ($password !== $passwordConfirm) {
            $errors[] = 'Passwords do not match.';
        }
    }
    foreach (['latitude' => 90, 'longitude' => 180] as $k => $max) {
        if ($data[$k] !== '' && (!is_numeric($data[$k]) || abs((float)$data[$k]) > $max)) {
            $errors[] = ucfirst($k) . ' is not valid.';
        }
    }
    if (($data['latitude'] === '') !== ($data['longitude'] === '')) {
        $errors[] = 'Please enter both latitude and longitude, or leave both empty.';
    }

    if (!$errors) {
        $lat = $data['latitude']  === '' ? null : $data['latitude'];
        $lng = $data['longitude'] === '' ? null : $data['longitude'];
        try {
            $pdo->beginTransaction();

            // 1) users table: hospital ka login account
            if ($userId) {
                $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ? AND role = 'hospital'")
                    ->execute([$data['hospital_name'], $data['contact_email'], $data['phone'], $userId]);
                if ($password !== '') {
                    $pdo->prepare('UPDATE users SET password = ?, failed_logins = 0, locked_until = NULL WHERE id = ?')
                        ->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
                }
            } else {
                $pdo->prepare("INSERT INTO users (name, email, password, phone, role, status, city, address) VALUES (?, ?, ?, ?, 'hospital', 'active', ?, ?)")
                    ->execute([$data['hospital_name'], $data['contact_email'], password_hash($password, PASSWORD_DEFAULT), $data['phone'], $data['location'], $data['address']]);
                $userId = (int)$pdo->lastInsertId();
            }

            // 2) hospitals table
            if ($isEdit) {
                $pdo->prepare('UPDATE hospitals SET user_id = ?, hospital_name = ?, address = ?, location = ?, phone = ?, license_no = ?, contact_email = ?, latitude = ?, longitude = ? WHERE id = ?')
                    ->execute([$userId, $data['hospital_name'], $data['address'], $data['location'], $data['phone'], $data['license_no'], $data['contact_email'], $lat, $lng, $id]);
                set_flash('success', 'Hospital updated successfully.');
            } else {
                $pdo->prepare('INSERT INTO hospitals (user_id, hospital_name, address, location, phone, license_no, contact_email, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$userId, $data['hospital_name'], $data['address'], $data['location'], $data['phone'], $data['license_no'], $data['contact_email'], $lat, $lng]);
                set_flash('success', 'Hospital added. It can now log in with ' . $data['contact_email'] . '.');
            }
            $pdo->commit();
            redirect('admin/hospitals.php');
        } catch (PDOException $ex) {
            $pdo->rollBack();
            $errors[] = 'Could not save the hospital. Please try again.';
        }
    }
}

$pageTitle  = $isEdit ? 'Edit Hospital' : 'Add Hospital';
$activeMenu = 'hospitals.php';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="mb-4">
  <a href="<?= BASE_URL ?>/admin/hospitals.php" class="text-decoration-none small">
    <i class="bi bi-arrow-left me-1"></i>Back to hospitals
  </a>
  <h4 class="mt-2 mb-0"><?= e($pageTitle) ?></h4>
</div>

<div class="row">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-body p-4">

        <?php if ($errors): ?>
          <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
              <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="post" class="needs-validation" novalidate>
          <?= csrf_field() ?>
          <div class="row g-3">
            <div class="col-12">
              <label for="hospital_name" class="form-label">Hospital Name</label>
              <input type="text" class="form-control" id="hospital_name" name="hospital_name"
                     value="<?= e($data['hospital_name']) ?>" minlength="3" maxlength="150" required>
              <div class="invalid-feedback">Please enter the hospital name (min 3 characters).</div>
            </div>
            <div class="col-md-8">
              <label for="address" class="form-label">Address</label>
              <input type="text" class="form-control" id="address" name="address"
                     value="<?= e($data['address']) ?>" required>
              <div class="invalid-feedback">Address is required.</div>
            </div>
            <div class="col-md-4">
              <label for="location" class="form-label">City / Location</label>
              <select class="form-select" id="location" name="location" required>
                <option value="">Select city</option>
                <?php foreach ($cities as $cityOption): ?>
                  <option value="<?= e($cityOption) ?>" <?= $data['location'] === $cityOption ? 'selected' : '' ?>><?= e($cityOption) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback">Please select the city.</div>
            </div>
            <div class="col-md-6">
              <label for="phone" class="form-label">Phone</label>
              <input type="tel" class="form-control" id="phone" name="phone"
                     value="<?= e($data['phone']) ?>" pattern="[0-9+\-\s]{10,15}" required>
              <div class="invalid-feedback">Please enter a valid phone number.</div>
            </div>
            <div class="col-md-6">
              <label for="license_no" class="form-label">License / Registration No.</label>
              <input type="text" class="form-control" id="license_no" name="license_no"
                     value="<?= e($data['license_no']) ?>" pattern="[A-Za-z0-9\-\/\s]{3,60}" maxlength="60" required>
              <div class="invalid-feedback">License number is required.</div>
            </div>
            <div class="col-md-6">
              <label for="contact_email" class="form-label">Contact email <span class="text-muted small">(login email)</span></label>
              <input type="email" class="form-control" id="contact_email" name="contact_email"
                     value="<?= e($data['contact_email']) ?>" maxlength="120" required>
              <div class="invalid-feedback">Please enter a valid email.</div>
            </div>
            <?php $pwRequired = !$isEdit || !$userId; ?>
            <div class="col-md-6">
              <label for="password" class="form-label">Password<?php if (!$pwRequired): ?> <span class="text-muted small">(leave empty to keep current)</span><?php endif; ?></label>
              <input type="password" class="form-control" id="password" name="password" minlength="8" autocomplete="new-password" <?= $pwRequired ? 'required' : '' ?>>
              <div class="invalid-feedback">Password must be at least 8 characters.</div>
            </div>
            <div class="col-md-6">
              <label for="password_confirm" class="form-label">Confirm Password</label>
              <input type="password" class="form-control" id="password_confirm" name="password_confirm" minlength="8" autocomplete="new-password" <?= $pwRequired ? 'required' : '' ?>>
              <div class="invalid-feedback">Please confirm the password.</div>
            </div>
            <div class="col-md-5">
              <label for="latitude" class="form-label">Latitude <span class="text-muted small">(for "near me")</span></label>
              <input type="text" class="form-control" id="latitude" name="latitude" value="<?= e($data['latitude']) ?>" placeholder="24.8607">
            </div>
            <div class="col-md-5">
              <label for="longitude" class="form-label">Longitude</label>
              <input type="text" class="form-control" id="longitude" name="longitude" value="<?= e($data['longitude']) ?>" placeholder="67.0011">
            </div>
            <div class="col-md-2 d-flex align-items-end">
              <button type="button" class="btn btn-outline-secondary w-100" id="useMyLocation" title="Use my current location"><i class="bi bi-crosshair"></i><span class="d-md-none ms-1">Use my location</span></button>
            </div>
          </div>

          <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary px-4">
              <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Update Hospital' : 'Save Hospital' ?>
            </button>
            <a href="<?= BASE_URL ?>/admin/hospitals.php" class="btn btn-outline-secondary">Cancel</a>
          </div>
        </form>

      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('useMyLocation').addEventListener('click', function () {
  if (!navigator.geolocation) { alert('Location is not supported in this browser.'); return; }
  navigator.geolocation.getCurrentPosition(function (p) {
    document.getElementById('latitude').value  = p.coords.latitude.toFixed(7);
    document.getElementById('longitude').value = p.coords.longitude.toFixed(7);
  }, function () { alert('Could not get your location.'); });
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
