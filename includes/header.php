<?php
// includes/header.php - har public/parent/hospital page ka upar wala hissa
// Istemal:
//   $pageTitle = 'Login';
//   require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

$pageTitle = $pageTitle ?? 'Home';
$currentPage = basename($_SERVER['PHP_SELF']);   // active link ke liye
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
  <meta name="description" content="<?= e($metaDescription ?? 'VaxCare helps parents track their children\'s vaccinations, book hospital appointments and download a verifiable vaccination certificate.') ?>">
  <meta property="og:title" content="<?= e($pageTitle) ?> | <?= APP_NAME ?>">
  <meta property="og:description" content="<?= e($metaDescription ?? 'Never miss a vaccination again.') ?>">
  <meta property="og:type" content="website">

  <!-- Bootstrap 5 + Icons + Google Font -->
  <?php head_assets(); ?>

  <!-- Hamara apna CSS (Bootstrap ke baad, taake override kar sake) -->
</head>
<body>
<a href="#main" class="visually-hidden-focusable btn btn-primary position-fixed m-2" style="z-index:2000">Skip to main content</a>

<!-- Upar ki patli patti: rabta ki maloomat (sirf bade screen par) -->
<div class="lp-topbar d-none d-lg-block">
  <div class="container d-flex justify-content-between align-items-center">
    <div>
      <span><i class="bi bi-telephone"></i><a href="tel:+923000000000">+92 300 0000000</a></span><span class="sep">|</span>
      <span><i class="bi bi-envelope"></i><a href="mailto:support@vaxcare.com">support@vaxcare.com</a></span>
    </div>
    <div>
      <span><i class="bi bi-geo-alt"></i>Karachi, Pakistan</span><span class="sep">|</span>
      <span><i class="bi bi-clock"></i>Mon to Sat, 9 AM to 6 PM</span>
    </div>
  </div>
</div>

<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top site-navbar">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_URL ?>/index.php">
      <img src="<?= BASE_URL ?>/assets/img/logo.svg" alt="Logo" width="38" height="38">
      <span class="brand-text"><?= APP_NAME ?></span>
    </a>

    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse"
            data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav mx-lg-auto align-items-lg-center gap-lg-1">
        <li class="nav-item"><a class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/index.php#about">About</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/index.php#vaccines">Vaccines</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/index.php#hospitals">Hospitals</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/index.php#faq">FAQ</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/index.php#contact">Contact</a></li>
      </ul>

      <ul class="navbar-nav align-items-lg-center gap-lg-2 mt-3 mt-lg-0">
        <?php if (is_logged_in()): ?>
          <li class="nav-item">
            <a class="nav-link" href="<?= BASE_URL ?>/<?= e(current_role()) ?>/dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
          </li>
          <li class="nav-item">
            <a class="btn btn-signup" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
          </li>
        <?php else: ?>
          <li class="nav-item">
            <a class="nav-link <?= $currentPage === 'login.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/login.php">Log in</a>
          </li>
          <li class="nav-item mt-2 mt-lg-0">
            <a class="btn btn-signup" href="<?= BASE_URL ?>/register.php?role=parent">Sign up</a>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<main class="site-main" id="main">
<?= render_flash($flash) ?>
