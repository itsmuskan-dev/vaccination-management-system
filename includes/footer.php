</main>

<footer class="site-footer lp-footer mt-0">
  <div class="container py-5">
    <div class="row g-4">
      <div class="col-lg-4">
        <div class="d-flex align-items-center gap-2 mb-3">
          <img src="<?= BASE_URL ?>/assets/img/logo.svg" alt="Logo" width="38" height="38">
          <span class="fs-5 fw-semibold text-white"><?= APP_NAME ?></span>
        </div>
        <p class="small mb-0">Helping families keep track of their children's vaccinations on schedule, with trusted hospitals and a complete digital record.</p>
      </div>
      <div class="col-6 col-lg-2 offset-lg-1">
        <h6>Explore</h6>
        <ul class="list-unstyled small">
          <li class="mb-2"><a href="<?= BASE_URL ?>/index.php#about">About us</a></li>
          <li class="mb-2"><a href="<?= BASE_URL ?>/index.php#vaccines">Vaccination schedule</a></li>
          <li class="mb-2"><a href="<?= BASE_URL ?>/index.php#hospitals">Find a hospital</a></li>
          <li class="mb-2"><a href="<?= BASE_URL ?>/index.php#faq">FAQ</a></li>
        </ul>
      </div>
      <div class="col-6 col-lg-2">
        <h6>For parents</h6>
        <ul class="list-unstyled small">
          <li class="mb-2"><a href="<?= BASE_URL ?>/register.php?role=parent">Create account</a></li>
          <li class="mb-2"><a href="<?= BASE_URL ?>/login.php">Log in</a></li>
          <li class="mb-2"><a href="<?= BASE_URL ?>/verify.php">Verify certificate</a></li>
          <li class="mb-2"><a href="<?= BASE_URL ?>/register.php?role=hospital">For hospitals</a></li>
        </ul>
      </div>
      <div class="col-lg-3">
        <h6>Contact</h6>
        <ul class="list-unstyled small">
          <li class="mb-2"><i class="bi bi-envelope me-2 text-danger"></i><a href="mailto:support@vaxcare.com">support@vaxcare.com</a></li>
          <li class="mb-2"><i class="bi bi-telephone me-2 text-danger"></i>+92 300 0000000</li>
          <li class="mb-2"><i class="bi bi-geo-alt me-2 text-danger"></i>Karachi, Pakistan</li>
          <li class="mb-2"><i class="bi bi-clock me-2 text-danger"></i>Mon to Sat, 9 AM to 6 PM</li>
        </ul>
      </div>
    </div>
  </div>
  <div class="bottom text-center small py-3">
    &copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.
  </div>
</footer>

<!-- Back to top button -->
<button id="backToTop" class="btn btn-primary rounded-circle shadow" aria-label="Back to top">
  <i class="bi bi-arrow-up"></i>
</button>

<?php foot_scripts(); ?>
</body>
</html>
