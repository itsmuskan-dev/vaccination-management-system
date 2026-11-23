<?php
// register.php - Parent ya Hospital ka naya account
// Admin register nahi hota (admin database mein pehle se maujood hai)
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/db.php';

// City ab free text nahi: isi list se dropdown banta hai aur server par yehi list check hoti hai
$cities = require __DIR__ . '/includes/cities.php';

if (is_logged_in()) {
    redirect(current_role() . '/dashboard.php');
}

$errors = [];
// Galti hone par form khali na ho jaye, isliye purani values yaad rakhte hain
// name = parent ka apna naam, ya hospital ka naam (hospital ke liye alag "contact person" nahi)
$old = ['role' => 'parent', 'name' => '', 'email' => '', 'phone' => '',
        'city' => '', 'address' => '', 'license_no' => ''];

// Landing page ke "Register as Parent / Hospital" buttons: ?role=hospital se sahi option pehle se chuna hua aata hai
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && in_array($_GET['role'] ?? '', ['parent', 'hospital'], true)) {
    $old['role'] = $_GET['role'];
}

// Admin ki bheji hui invitation link: ?claim=TOKEN. Hospital pehle se list mein hai, ab apna login bana kar usay apne account se jorta hai.
$claim      = null;
$claimToken = '';
$rawToken   = $_POST['claim'] ?? $_GET['claim'] ?? '';
if ($rawToken !== '') {
    if (preg_match('/^[a-f0-9]{32}$/', $rawToken)) {
        $c = $pdo->prepare('SELECT * FROM hospitals WHERE invite_token = ? AND user_id IS NULL');
        $c->execute([$rawToken]);
        $claim = $c->fetch() ?: null;
    }
    if (!$claim) {
        set_flash('warning', 'This invitation link is invalid or has already been used.');
        redirect('register.php?role=hospital');
    }
    $claimToken = $rawToken;
    $old['role'] = 'hospital';
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {                  // pehli baar kholne par maloomat pehle se bhari
        $old['name']       = $claim['hospital_name'];
        $old['city']       = $claim['location'];
        $old['address']    = $claim['address'];
        $old['phone']      = (string)$claim['phone'];
        $old['license_no'] = (string)$claim['license_no'];
        $old['email']      = (string)$claim['contact_email'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $key => $value) {
        $old[$key] = trim($_POST[$key] ?? '');
    }
    if ($claim) { $old['role'] = 'hospital'; }                    // invitation ho to role hamesha hospital
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $isHospital = ($old['role'] === 'hospital');

    // ---------- Validation (server side, yahi asli hifazat hai) ----------
    if (!verify_csrf()) {
        $errors[] = 'Session expired. Please try again.';
    }
    if (!in_array($old['role'], ['parent', 'hospital'], true)) {
        $errors[] = 'Please select a valid account type.';
    }
    $maxName = $isHospital ? 150 : 100;
    if (mb_strlen($old['name']) < 3 || mb_strlen($old['name']) > $maxName) {
        $errors[] = ($isHospital ? 'Hospital name' : 'Name') . ' must be between 3 and ' . $maxName . ' characters.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (!preg_match('/^[0-9+\-\s]{10,15}$/', $old['phone'])) {
        $errors[] = 'Please enter a valid phone number.';
    }
    if (!in_array($old['city'], $cities, true)) {
        $errors[] = 'Please select your city from the list.';
    }
    if (mb_strlen($old['address']) > 255) {
        $errors[] = 'Address can be at most 255 characters.';
    }
    if ($isHospital) {
        if ($old['address'] === '') {
            $errors[] = 'Hospital address is required.';
        }
        if (!preg_match('/^[A-Za-z0-9\-\/\s]{3,60}$/', $old['license_no'])) {
            $errors[] = 'Please enter a valid license / registration number (3 to 60 letters, numbers, - or /).';
        }
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    // Email pehle se register to nahi?
    if (!$errors) {
        $check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$old['email']]);
        if ($check->fetch()) {
            $errors[] = 'This email is already registered. Please login.';
        }
    }

    // Wohi naam + wohi shehar wala hospital pehle se list mein ho to naya nahi banta (duplicate se bachao)
    if (!$errors && $isHospital && !$claim) {
        $dup = $pdo->prepare('SELECT id FROM hospitals WHERE LOWER(hospital_name) = LOWER(?) AND LOWER(location) = LOWER(?)');
        $dup->execute([$old['name'], $old['city']]);
        if ($dup->fetch()) {
            $errors[] = 'A hospital with this name and city is already listed. If you manage it, please use the invitation link from our team or contact us.';
        }
    }

    // ---------- Sab theek hai to save karo ----------
    if (!$errors) {
        try {
            $pdo->beginTransaction();   // dono inserts ek saath hon, ya koi nahi

            $stmt = $pdo->prepare(
                'INSERT INTO users (name, email, password, phone, role, status, city, address)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $old['name'],                                   // hospital ke liye: hospital ka naam
                $old['email'],
                password_hash($password, PASSWORD_DEFAULT),     // password hash hokar save hota hai
                $old['phone'],
                $old['role'],
                ($isHospital && !$claim) ? 'pending' : 'active',             // hospital ko pehle admin manzoor karega, parent ko nahi
                $isHospital ? null : $old['city'],              // parent ka shehar/pata users mein, hospital ka hospitals mein
                $isHospital ? null : ($old['address'] === '' ? null : $old['address']),
            ]);
            $userId = $pdo->lastInsertId();

            if ($isHospital && $claim) {
                // Maujooda hospital ko is naye account se jodo (token sirf ek baar chalta hai)
                $h = $pdo->prepare(
                    'UPDATE hospitals SET user_id = ?, hospital_name = ?, address = ?, location = ?, phone = ?, license_no = ?, contact_email = ?, invite_token = NULL
                     WHERE id = ? AND user_id IS NULL AND invite_token = ?'
                );
                $h->execute([$userId, $old['name'], $old['address'], $old['city'], $old['phone'], $old['license_no'], $old['email'], $claim['id'], $claimToken]);
                if ($h->rowCount() !== 1) {
                    throw new PDOException('Invitation is no longer valid');
                }
            } elseif ($isHospital) {
                $h = $pdo->prepare(
                    'INSERT INTO hospitals (user_id, hospital_name, address, location, phone, license_no)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $h->execute([$userId, $old['name'], $old['address'], $old['city'], $old['phone'], $old['license_no']]);
            }

            $pdo->commit();
            set_flash('success', $claim
                ? 'Your hospital account is ready. Login to see the appointments waiting for you.'
                : ($isHospital
                    ? 'Registration received. You can login once our team verifies and approves your hospital.'
                    : 'Account created successfully. Please login.'));
            redirect('login.php');
        } catch (PDOException $ex) {
            $pdo->rollBack();   // kuch bhi fail ho to sab undo
            $errors[] = 'Something went wrong. Please try again.';
        }
    }
}

$pageTitle = 'Register';
require_once __DIR__ . '/includes/header.php';
$isH = ($old['role'] === 'hospital');
?>

<div class="auth-wrapper">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-11 col-xl-10">
        <div class="card auth-card auth-split fade-in-up">
          <div class="row g-0">

            <!-- Left: brand panel (sirf bade screen par) -->
            <div class="col-lg-4 auth-side d-none d-lg-flex">
              <img src="<?= BASE_URL ?>/assets/img/logo.svg" alt="" width="52" height="52">
              <h2>Join <?= APP_NAME ?> in a minute</h2>
              <p>One account to manage vaccinations the smart way.</p>
              <ul>
                <li><i class="bi bi-check-circle-fill"></i>Free for parents</li>
                <li><i class="bi bi-check-circle-fill"></i>Smart schedule from date of birth</li>
                <li><i class="bi bi-check-circle-fill"></i>Hospitals join after a quick verification</li>
              </ul>
            </div>

            <!-- Right: form -->
            <div class="col-lg-8 p-4 p-md-5">
              <div class="text-center mb-4">
                <div class="icon-box mb-3"><i class="bi bi-person-plus"></i></div>
                <h3 class="mb-1">Create your account</h3>
                <p class="text-muted mb-0">Join <?= APP_NAME ?> in less than a minute</p>
              </div>

              <?php if ($errors): ?>
                <div class="alert alert-danger">
                  <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>

              <form method="post" class="needs-validation" novalidate>
                <?= csrf_field() ?>

                <?php if ($claim): ?>
                <input type="hidden" name="role" value="hospital">
                <input type="hidden" name="claim" value="<?= e($claimToken) ?>">
                <div class="alert alert-info small mb-4">
                  <i class="bi bi-envelope-check me-1"></i>You have been invited to manage <strong><?= e($claim['hospital_name']) ?></strong> on <?= APP_NAME ?>.
                  Check the details below and create your login. Parents' appointments may already be waiting for you.
                </div>
                <?php else: ?>
                <!-- Account type -->
                <div class="mb-4">
                  <label class="form-label d-block">I am a</label>
                  <div class="btn-group w-100" role="group">
                    <input type="radio" class="btn-check" name="role" id="role_parent" value="parent" <?= !$isH ? 'checked' : '' ?>>
                    <label class="btn btn-outline-primary" for="role_parent"><i class="bi bi-people me-1"></i>Parent</label>

                    <input type="radio" class="btn-check" name="role" id="role_hospital" value="hospital" <?= $isH ? 'checked' : '' ?>>
                    <label class="btn btn-outline-primary" for="role_hospital"><i class="bi bi-hospital me-1"></i>Hospital</label>
                  </div>
                  <div class="form-text mt-2">
                    <span data-show-for="parent" class="<?= !$isH ? '' : 'd-none' ?>">
                      <i class="bi bi-check-circle-fill text-success me-1"></i>Parents can login right after registering. No approval needed.
                    </span>
                    <span data-show-for="hospital" class="<?= $isH ? '' : 'd-none' ?>">
                      <i class="bi bi-hourglass-split text-warning me-1"></i>Hospital accounts become active after our team verifies them.
                    </span>
                  </div>
                </div>
                <?php endif; ?>

                <div class="row g-3">
                  <div class="col-md-6">
                    <label for="name" class="form-label" data-text-parent="Your Full Name" data-text-hospital="Hospital Name"><?= $isH ? 'Hospital Name' : 'Your Full Name' ?></label>
                    <input type="text" class="form-control" id="name" name="name" value="<?= e($old['name']) ?>"
                           data-ph-parent="e.g. Ali Khan" data-ph-hospital="e.g. City Hospital"
                           placeholder="<?= $isH ? 'e.g. City Hospital' : 'e.g. Ali Khan' ?>"
                           minlength="3" required>
                    <div class="invalid-feedback">Please enter the name (min 3 characters).</div>
                  </div>
                  <div class="col-md-6">
                    <label for="phone" class="form-label" data-text-parent="Your Phone" data-text-hospital="Hospital Phone"><?= $isH ? 'Hospital Phone' : 'Your Phone' ?></label>
                    <input type="tel" class="form-control" id="phone" name="phone" value="<?= e($old['phone']) ?>"
                           pattern="[0-9+\-\s]{10,15}" placeholder="03001234567" required>
                    <div class="invalid-feedback">Please enter a valid phone number.</div>
                  </div>

                  <div class="col-12">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= e($old['email']) ?>" autocomplete="off" required>
                    <div class="invalid-feedback">Please enter a valid email.</div>
                  </div>

                  <!-- Sirf Hospital: license number (admin isi se verify karta hai) -->
                  <div class="col-12 <?= $isH ? '' : 'd-none' ?>" data-show-for="hospital">
                    <label for="license_no" class="form-label">License / Registration No.</label>
                    <input type="text" class="form-control" id="license_no" name="license_no" value="<?= e($old['license_no']) ?>"
                           data-required-for="hospital" maxlength="60" placeholder="e.g. KHI-H-0001" <?= $isH ? 'required' : '' ?>>
                    <div class="form-text">Our team checks this number before your hospital goes live.</div>
                    <div class="invalid-feedback">Please enter your license / registration number.</div>
                  </div>

                  <div class="col-md-5">
                    <label for="city" class="form-label">City</label>
                    <select class="form-select" id="city" name="city" required>
                      <option value="">Select your city</option>
                      <?php foreach ($cities as $cityOption): ?>
                        <option value="<?= e($cityOption) ?>" <?= $old['city'] === $cityOption ? 'selected' : '' ?>><?= e($cityOption) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Please select your city.</div>
                  </div>
                  <div class="col-md-7">
                    <label for="address" class="form-label" data-text-parent="Address (optional)" data-text-hospital="Address"><?= $isH ? 'Address' : 'Address (optional)' ?></label>
                    <input type="text" class="form-control" id="address" name="address" value="<?= e($old['address']) ?>"
                           data-required-for="hospital" maxlength="255" <?= $isH ? 'required' : '' ?>>
                    <div class="invalid-feedback">Hospital address is required.</div>
                  </div>

                  <div class="col-md-6">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                      <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" minlength="8" required>
                      <button class="btn btn-outline-secondary" type="button" data-toggle-password="#password" aria-label="Show password">
                        <i class="bi bi-eye"></i>
                      </button>
                      <div class="invalid-feedback">Minimum 8 characters.</div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
                    <div class="invalid-feedback">Passwords must match.</div>
                  </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 mt-4">Create Account</button>
              </form>

              <p class="text-center text-muted mt-4 mb-0">
                Already have an account? <a href="<?= BASE_URL ?>/login.php">Login</a>
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
