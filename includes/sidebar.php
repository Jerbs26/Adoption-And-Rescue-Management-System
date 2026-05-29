<?php

$user = current_user();
$role = $user['role'];

$initials = strtoupper(substr($user['full_name'], 0, 1));

$unread = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$unread->execute([$user['id']]);
$unreadCount = (int)$unread->fetchColumn();
?>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<aside class="sidebar" id="appSidebar" aria-label="Sidebar navigation">
  <div class="sidebar-brand">
    <div class="brand__icon"><i class="fa-solid fa-paw"></i></div>
    <span><?= APP_NAME ?></span>
  </div>

  <nav class="sidebar-nav" aria-label="Dashboard navigation">

    <?php if ($role === 'admin'): ?>
      <!-- ───────── ADMIN ───────── -->
      <div class="sidebar-section">Overview</div>
      <a href="<?= BASE_URL ?>/modules/admin/dashboard.php" class="<?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
        <i class="fa-solid fa-chart-pie"></i> Dashboard
      </a>

      <div class="sidebar-section">Management</div>
      <a href="<?= BASE_URL ?>/modules/admin/users.php" class="<?= ($activePage ?? '') === 'users' ? 'active' : '' ?>">
        <i class="fa-solid fa-users"></i> Users
      </a>
<a href="<?= BASE_URL ?>/modules/admin/announcements.php" class="<?= ($activePage ?? '') === 'announcements' ? 'active' : '' ?>">
        <i class="fa-solid fa-bullhorn"></i> Announcements
      </a>

      <div class="sidebar-section">Reports</div>
      <a href="<?= BASE_URL ?>/modules/admin/reports.php" class="<?= ($activePage ?? '') === 'reports' ? 'active' : '' ?>">
        <i class="fa-solid fa-chart-bar"></i> Reports
      </a>
      <a href="<?= BASE_URL ?>/modules/admin/activity-log.php" class="<?= ($activePage ?? '') === 'activity' ? 'active' : '' ?>">
        <i class="fa-solid fa-list-check"></i> Activity Log
      </a>

    <?php elseif ($role === 'staff'): ?>
      <!-- ───────── STAFF (Rescue Organization) ───────── -->
      <div class="sidebar-section">Overview</div>
      <a href="<?= BASE_URL ?>/modules/staff/dashboard.php" class="<?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
        <i class="fa-solid fa-gauge"></i> Dashboard
      </a>

      <div class="sidebar-section">Pets</div>
      <a href="<?= BASE_URL ?>/modules/staff/pets.php" class="<?= ($activePage ?? '') === 'pets' ? 'active' : '' ?>">
        <i class="fa-solid fa-paw"></i> Manage Pets
      </a>
      <a href="<?= BASE_URL ?>/modules/staff/add-pet.php" class="<?= ($activePage ?? '') === 'add-pet' ? 'active' : '' ?>">
        <i class="fa-solid fa-plus"></i> Add Pet
      </a>
      <a href="<?= BASE_URL ?>/modules/staff/medical.php" class="<?= ($activePage ?? '') === 'medical' ? 'active' : '' ?>">
        <i class="fa-solid fa-syringe"></i> Medical Records
      </a>

      <div class="sidebar-section">Adoptions</div>
      <a href="<?= BASE_URL ?>/modules/staff/applications.php" class="<?= ($activePage ?? '') === 'applications' ? 'active' : '' ?>">
        <i class="fa-solid fa-file-lines"></i> Applications
      </a>

      <div class="sidebar-section">Reports</div>
      <a href="<?= BASE_URL ?>/modules/staff/animal-report.php" class="<?= ($activePage ?? '') === 'animal-reports' ? 'active' : '' ?>">
        <i class="fa-solid fa-triangle-exclamation"></i> Animal Reports
        <?php
        // Show badge for pending/new reports needing action
        $pendingReports = db()->query("SELECT COUNT(*) FROM animal_reports WHERE status IN ('Pending','New')")->fetchColumn();
        if ($pendingReports > 0):
        ?>
          <span style="margin-left:auto;background:var(--warning,#f59e0b);color:#fff;border-radius:999px;padding:0 6px;font-size:.7rem;line-height:1.6"><?= (int)$pendingReports ?></span>
        <?php endif; ?>
      </a>

    <?php elseif ($role === 'rescue_org'): ?>
      <!-- ───────── RESCUE ORG ───────── -->
      <div class="sidebar-section">Overview</div>
      <a href="<?= BASE_URL ?>/modules/staff/dashboard.php" class="<?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
        <i class="fa-solid fa-gauge"></i> Dashboard
      </a>

      <div class="sidebar-section">Pets</div>
      <a href="<?= BASE_URL ?>/modules/staff/pets.php" class="<?= ($activePage ?? '') === 'pets' ? 'active' : '' ?>">
        <i class="fa-solid fa-paw"></i> Manage Pets
      </a>
      <a href="<?= BASE_URL ?>/modules/staff/add-pet.php" class="<?= ($activePage ?? '') === 'add-pet' ? 'active' : '' ?>">
        <i class="fa-solid fa-plus"></i> Add Pet
      </a>
      <a href="<?= BASE_URL ?>/modules/staff/medical.php" class="<?= ($activePage ?? '') === 'medical' ? 'active' : '' ?>">
        <i class="fa-solid fa-syringe"></i> Medical Records
      </a>

      <div class="sidebar-section">Adoptions</div>
      <a href="<?= BASE_URL ?>/modules/staff/applications.php" class="<?= ($activePage ?? '') === 'applications' ? 'active' : '' ?>">
        <i class="fa-solid fa-file-lines"></i> Applications
      </a>

      <div class="sidebar-section">Reports</div>
      <a href="<?= BASE_URL ?>/modules/staff/animal-report.php" class="<?= ($activePage ?? '') === 'animal-reports' ? 'active' : '' ?>">
        <i class="fa-solid fa-triangle-exclamation"></i> Animal Reports
        <?php
        $pendingReports = 0;
        try {
            $pendingReports = (int)db()->query("SELECT COUNT(*) FROM animal_reports WHERE status IN ('Pending','New')")->fetchColumn();
        } catch (Throwable) {}
        if ($pendingReports > 0):
        ?>
          <span style="margin-left:auto;background:var(--warning,#f59e0b);color:#fff;border-radius:999px;padding:0 6px;font-size:.7rem;line-height:1.6"><?= $pendingReports ?></span>
        <?php endif; ?>
      </a>

    <?php else: // adopter ?>
      <!-- ───────── ADOPTER ───────── -->
      <div class="sidebar-section">My Account</div>
      <a href="<?= BASE_URL ?>/modules/adopter/dashboard.php" class="<?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
        <i class="fa-solid fa-gauge"></i> Dashboard
      </a>
      <a href="<?= BASE_URL ?>/modules/adopter/profile.php" class="<?= ($activePage ?? '') === 'profile' ? 'active' : '' ?>">
        <i class="fa-solid fa-circle-user"></i> My Profile
      </a>
      <a href="<?= BASE_URL ?>/modules/adopter/applications.php" class="<?= ($activePage ?? '') === 'applications' ? 'active' : '' ?>">
        <i class="fa-solid fa-file-lines"></i> My Applications
      </a>
      <a href="<?= BASE_URL ?>/modules/adopter/medical.php" class="<?= ($activePage ?? '') === 'medical' ? 'active' : '' ?>">
        <i class="fa-solid fa-notes-medical"></i> Medical Records
      </a>
      <a href="<?= BASE_URL ?>/modules/adopter/notifications.php" class="<?= ($activePage ?? '') === 'notifications' ? 'active' : '' ?>">
        <i class="fa-solid fa-bell"></i> Notifications
        <?php if ($unreadCount): ?>
          <span style="margin-left:auto;background:var(--danger);color:#fff;border-radius:999px;padding:0 6px;font-size:.7rem;line-height:1.6"><?= $unreadCount ?></span>
        <?php endif; ?>
      </a>

      <a href="<?= BASE_URL ?>/modules/adopter/animal-report.php" class="<?= ($activePage ?? '') === 'animal-report' ? 'active' : '' ?>">
        <i class="fa-solid fa-triangle-exclamation"></i> Report an Animal
      </a>

      <div class="sidebar-section">Browse</div>
      <a href="<?= BASE_URL ?>/modules/adopter/find-pet.php" class="<?= ($activePage ?? '') === 'find-pet' ? 'active' : '' ?>">
        <i class="fa-solid fa-magnifying-glass"></i> Find a Pet
      </a>
    <?php endif; ?>

    <!-- ───────── ACCOUNT (all roles) ───────── -->
    <div class="sidebar-section">Account</div>
    <a href="<?= BASE_URL ?>/logout.php">
      <i class="fa-solid fa-right-from-bracket"></i> Logout
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="s-avatar"><?= e($initials) ?></div>
      <div>
        <div class="name"><?= e($user['full_name']) ?></div>
        <?php
        $roleLabels = [
            'admin'      => 'Admin',
            'staff'      => 'Staff',
            'adopter'    => 'Adopter',
            'rescue_org' => 'Rescue Org',
        ];
        $roleLabel = $roleLabels[$user['role']] ?? ucfirst($user['role']);
        ?>
        <div class="role-label"><?= e($roleLabel) ?></div>
      </div>
    </div>
  </div>
</aside>