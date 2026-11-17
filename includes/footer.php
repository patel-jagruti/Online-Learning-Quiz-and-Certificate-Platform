
</main><!-- /.main-content -->

<!-- ===== FOOTER ===== -->
<footer class="site-footer mt-auto py-4">
  <div class="container">
    <div class="row gy-3 align-items-center">

      <div class="col-md-4 text-center text-md-start">
        <a class="footer-brand fw-bold text-decoration-none d-inline-flex align-items-center gap-2"
           href="<?= APP_URL ?>/">
          <span>🎓</span><span class="text-white"><?= APP_NAME ?></span>
        </a>
        <p class="text-muted small mb-0 mt-1">
          <?= getSetting('site_description', 'The ultimate online learning &amp; quiz platform') ?>
        </p>
      </div>

      <div class="col-md-4 text-center">
        <ul class="list-inline mb-0">
          <li class="list-inline-item">
            <a href="<?= APP_URL ?>/" class="footer-link">Home</a>
          </li>
          <li class="list-inline-item">·
            <a href="<?= APP_URL ?>/courses/" class="footer-link">Courses</a>
          </li>
          <li class="list-inline-item">·
            <a href="<?= APP_URL ?>/quiz/" class="footer-link">Quizzes</a>
          </li>
          <li class="list-inline-item">·
            <a href="<?= APP_URL ?>/about.php" class="footer-link">About</a>
          </li>
        </ul>
      </div>

      <div class="col-md-4 text-center text-md-end">
        <p class="text-muted small mb-0">
          &copy; <?= date('Y') ?>
          <a href="<?= APP_URL ?>/" class="footer-link"><?= APP_NAME ?></a>.
          All rights reserved.
        </p>
      </div>

    </div>
  </div>
</footer>

<!-- Back-to-top button -->
<button id="backToTop" class="btn btn-primary btn-sm back-to-top" title="Back to top" aria-label="Back to top">
  <i class="fa fa-chevron-up"></i>
</button>

<!-- Bootstrap 5 JS (bundle with Popper) -->
<script src="<?= ASSET_URL ?>/vendor/bootstrap/js/bootstrap.bundle.min.js" defer></script>
<!-- App JS -->
<script src="<?= ASSET_URL ?>/js/main.js" defer></script>
<?php if (isset($extraJs)) echo $extraJs; ?>
</body>
</html>
