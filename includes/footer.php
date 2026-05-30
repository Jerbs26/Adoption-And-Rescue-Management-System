<?php ?>
<footer class="site-footer">

  <div class="sf-inner">

    <!-- Quick Links -->
    <div class="sf-col">
      <h4 class="sf-heading">Quick Links</h4>
      <nav class="sf-links" aria-label="Footer navigation">
        <a href="<?= BASE_URL ?>/pages/pets.php">
          <i class="fa-solid fa-paw"></i> Adoptable Pets
        </a>
        <a href="<?= BASE_URL ?>/pages/about.php">
          <i class="fa-solid fa-circle-info"></i> About Us
        </a>
        <a href="<?= BASE_URL ?>/pages/apply.php">
          <i class="fa-solid fa-file-pen"></i> Apply to Adopt
        </a>
      </nav>
    </div>

    <!-- Contact -->
    <div class="sf-col">
      <h4 class="sf-heading">Contact Us</h4>
      <ul class="sf-contact">
        <li>
          <i class="fa-solid fa-envelope"></i>
          <a href="mailto:hello@adoptly.local">adoptly@gmail.com</a>
        </li>
        <li>
          <i class="fa-solid fa-phone"></i>
          <a href="tel:+639543447943">+63 954 344 7943</a>
        </li>
        <li class="sf-socials-li">
          <a href="https://www.instagram.com/adoptlydeutschland/" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
          <a href="https://www.facebook.com/adopt.clarabelle.li" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
          <a href="https://x.com/AdoptlyG14643" target="_blank" rel="noopener noreferrer" aria-label="Twitter / X"><i class="fa-brands fa-x-twitter"></i></a>
        </li>
      </ul>
    </div>

  </div>

  <div class="sf-copy">
    &copy; <?= date('Y') ?> <?= e(APP_NAME) ?> &mdash; All rights reserved.
  </div>

</footer>

<style>
/* ── Reset any parent container interference ── */
.site-footer {
  display: block !important;
  width: 100% !important;
  margin-top: 4rem;
  background: var(--sidebar-bg);
  border-top: none;
  color: var(--sidebar-fg);
  box-sizing: border-box;
}

/* Center the two columns using flex */
.sf-inner {
  display: flex !important;
  flex-direction: row;
  justify-content: center;
  align-items: flex-start;
  gap: 6rem;
  padding: 3rem 2rem 2.5rem;
  width: 100%;
  box-sizing: border-box;
}

.sf-col {
  flex: 0 0 auto;
  min-width: 160px;
}

.sf-heading {
  font-size: .72rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .1em;
  color: hsl(38 40% 60%);
  margin: 0 0 1.1rem;
}

.sf-links {
  display: flex;
  flex-direction: column;
  gap: .6rem;
}
.sf-links a {
  display: inline-flex;
  align-items: center;
  gap: .5rem;
  font-size: .9rem;
  color: hsl(38 40% 80%);
  text-decoration: none;
  transition: color .15s;
}
.sf-links a i { font-size: .75rem; opacity: .65; }
.sf-links a:hover { color: #fff; }

.sf-contact {
  display: flex;
  flex-direction: column;
  gap: .7rem;
  list-style: none;
  padding: 0;
  margin: 0;
}
.sf-contact li {
  display: flex;
  align-items: center;
  gap: .55rem;
  font-size: .9rem;
  color: hsl(38 40% 80%);
}
.sf-contact li > i { font-size: .8rem; opacity: .65; width: 14px; flex-shrink: 0; }
.sf-contact a { color: inherit; text-decoration: none; transition: color .15s; }
.sf-contact a:hover { color: #fff; }

.sf-socials-li {
  display: flex;
  gap: .45rem;
  margin-top: .3rem;
  align-items: center;
}
.sf-socials-li a {
  width: 36px; height: 36px;
  border-radius: 50%;
  background: hsl(0 0% 12%);
  border: 1px solid hsl(145 18% 38%);
  display: grid; place-items: center;
  color: var(--sidebar-fg);
  font-size: .88rem;
  text-decoration: none;
  transition: background .15s, border-color .15s, color .15s;
}
.sf-socials-li a:hover {
  background: var(--accent);
  border-color: var(--accent);
  color: #fff;
}

.sf-copy {
  border-top: 1px solid hsl(145 18% 28%);
  padding: 1rem 2rem;
  color: hsl(38 30% 52%);
  font-size: .78rem;
  text-align: center;
  width: 100%;
  box-sizing: border-box;
}

/* Tablet */
@media (max-width: 768px) {
  .sf-inner { gap: 4rem; padding: 2.5rem 2rem 2rem; }
}

/* Mobile */
@media (max-width: 480px) {
  .sf-inner {
    flex-direction: column !important;
    align-items: center;
    gap: 0;
    padding: 2rem 1.5rem 1.5rem;
  }
  .sf-col {
    width: 100%;
    max-width: 320px;
    padding: 1.25rem 0;
    border-bottom: 1px solid hsl(145 18% 28%);
  }
  .sf-col:last-child { border-bottom: none; padding-bottom: .5rem; }
  .sf-links { flex-direction: row; flex-wrap: wrap; gap: .5rem 1.25rem; }
  .sf-copy { font-size: .74rem; }
}
</style>

<script src="<?= BASE_URL ?>/public/js/main.js" defer></script>
</body>
</html>