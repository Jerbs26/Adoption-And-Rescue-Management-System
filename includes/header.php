<?php

$user = current_user();
$currentFile = basename($_SERVER['PHP_SELF']);
if (!function_exists('nav_active')) {
    function nav_active(string $page): string {
        global $currentFile;
        return $currentFile === $page ? ' active' : '';
    }
}
?>
<header class="site-header">
  <div class="container site-header__inner">
    <a class="brand" href="<?= BASE_URL ?>/index.php" aria-label="<?= e(APP_NAME) ?> — Home">
      <span class="brand__icon" aria-hidden="true"><i class="fa-solid fa-paw"></i></span>
      <span><?= e(APP_NAME) ?></span>
    </a>

    <!-- Desktop nav -->
    <nav class="nav" aria-label="Primary navigation">
      <a href="<?= BASE_URL ?>/index.php"          class="<?= nav_active('index.php') ?>">Home</a>
      <a href="<?= BASE_URL ?>/pages/about.php"    class="<?= nav_active('about.php') ?>">About</a>
      <a href="<?= BASE_URL ?>/pages/pets.php"     class="<?= nav_active('pets.php') ?>">Adopt</a>

      <?php if ($user): ?>
        <?php if ($user['role'] === 'adopter'): ?>
          <a href="<?= BASE_URL ?>/modules/adopter/dashboard.php" class="<?= nav_active('dashboard.php') ?>">My Dashboard</a>
        <?php elseif ($user['role'] === 'admin'): ?>
          <a href="<?= BASE_URL ?>/modules/admin/dashboard.php">Admin Panel</a>
        <?php elseif (in_array($user['role'], ['staff', 'rescue_org'])): ?>
          <a href="<?= BASE_URL ?>/modules/staff/dashboard.php">Dashboard</a>
        <?php endif; ?>

        <div class="nav-user-menu">
          <button class="nav-user-btn" id="userMenuBtn"
                  aria-expanded="false"
                  aria-controls="userMenuDropdown"
                  aria-haspopup="true">
            <?php if (!empty($user['photo'])): ?>
              <img src="<?= e(pet_image_url($user['photo'])) ?>"
                   alt="<?= e($user['full_name']) ?>"
                   class="uavatar"
                   width="30" height="30">
            <?php else: ?>
              <span class="uavatar" aria-hidden="true">
                <?= e(strtoupper(mb_substr($user['full_name'], 0, 1))) ?>
              </span>
            <?php endif; ?>
            <span class="nav-username"><?= e($user['full_name']) ?></span>
            <i class="fa-solid fa-chevron-down" style="font-size:.7rem" aria-hidden="true"></i>
          </button>
          <div class="nav-dropdown" id="userMenuDropdown" role="menu" aria-label="User menu">
            <?php
            $profileBase = match($user['role']) {
                'admin'      => BASE_URL . '/modules/admin',
                'staff',
                'rescue_org' => BASE_URL . '/modules/staff',
                default      => BASE_URL . '/modules/adopter',
            };
            ?>
            <a href="<?= $profileBase ?>/profile.php" role="menuitem">
              <i class="fa-solid fa-user" aria-hidden="true"></i> Profile
            </a>
            <div class="sep" role="separator"></div>
            <a href="<?= BASE_URL ?>/logout.php" role="menuitem">
              <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Logout
            </a>
          </div>
        </div>

      <?php else: ?>
        <a href="<?= BASE_URL ?>/login.php"    class="btn btn-ghost btn-sm">Login</a>
        <a href="<?= BASE_URL ?>/register.php" class="btn btn-accent btn-sm">Register</a>
      <?php endif; ?>
    </nav>

    <!-- Hamburger (mobile only) -->
    <button class="nav-hamburger" id="navHamburger"
            aria-label="Open menu"
            aria-expanded="false"
            aria-controls="mobileNav">
      <i class="fa-solid fa-bars" aria-hidden="true"></i>
    </button>
  </div>

  <!-- Mobile nav drawer -->
  <nav class="mobile-nav" id="mobileNav"
       aria-label="Mobile navigation"
       aria-hidden="true">
    <a href="<?= BASE_URL ?>/index.php">Home</a>
    <a href="<?= BASE_URL ?>/pages/about.php">About</a>
    <a href="<?= BASE_URL ?>/pages/pets.php">Adopt</a>
    <?php if ($user): ?>
      <?php
      $dashLink = match($user['role']) {
          'admin'      => BASE_URL . '/modules/admin/dashboard.php',
          'staff',
          'rescue_org' => BASE_URL . '/modules/staff/dashboard.php',
          default      => BASE_URL . '/modules/adopter/dashboard.php',
      };
      $profLink = match($user['role']) {
          'admin'      => BASE_URL . '/modules/admin/profile.php',
          'staff',
          'rescue_org' => BASE_URL . '/modules/staff/profile.php',
          default      => BASE_URL . '/modules/adopter/profile.php',
      };
      ?>
      <a href="<?= $dashLink ?>">Dashboard</a>
      <a href="<?= $profLink ?>">Profile</a>
      <a href="<?= BASE_URL ?>/logout.php">Logout</a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/login.php">Login</a>
      <a href="<?= BASE_URL ?>/register.php">Register</a>
    <?php endif; ?>
  </nav>
</header>