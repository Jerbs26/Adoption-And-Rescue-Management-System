<?php  ?>
<footer class="site-footer">
  <div class="container site-footer__top">
    <div>
      <div class="brand">
        <span class="brand__icon" style="width:28px;height:28px;font-size:.8rem"><i class="fa-solid fa-paw"></i></span>
        <?= APP_NAME ?>
      </div>
      <p class="muted small" style="margin-top:.75rem;max-width:22rem">
        A small-town rescue matching loving animals with loving humans since 2014.
      </p>
    </div>
    <div>
      <h4>Quick Links</h4>
      <p class="muted small"><a href="<?= BASE_URL ?>/pages/pets.php">Adoptable Pets</a></p>
      <p class="muted small"><a href="<?= BASE_URL ?>/pages/about.php">About Us</a></p>
      <p class="muted small"><a href="<?= BASE_URL ?>/pages/apply.php">Apply to Adopt</a></p>
    </div>
    <div>
      <h4>Contact Us</h4>
      <p class="muted small"><i class="fa-solid fa-envelope"></i> hello@adoptly.local</p>
      <p class="muted small"><i class="fa-solid fa-phone"></i> +63 954 344 7943</p>
      <div class="socials" style="margin-top:.75rem">
        <a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
        <a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
        <a href="#" aria-label="Twitter/X"><i class="fa-brands fa-x-twitter"></i></a>
      </div>
    </div>
  </div>
  <div class="copy container">
    &copy; <?= date('Y') ?> <?= APP_NAME ?> &mdash; All rights reserved.
  </div>
</footer>
<script src="<?= BASE_URL ?>/public/js/main.js"></script>
</body>
</html>