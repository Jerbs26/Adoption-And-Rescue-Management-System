<?php

$user     = current_user();
$role     = $user['role'] ?? 'adopter';
$initials = strtoupper(mb_substr($user['full_name'] ?? 'U', 0, 1));

// Unread notification count
if (!isset($unreadCount)) {
    try {
        $unread = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $unread->execute([$user['id']]);
        $unreadCount = (int)$unread->fetchColumn();
    } catch (Throwable) {
        $unreadCount = 0;
    }
}

// Helper: active class
$ap = $activePage ?? '';
function _sa(string $key, string $ap): string {
    return $ap === $key ? ' active' : '';
}
?>
<div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

<aside class="sidebar" id="appSidebar"
       role="navigation"
       aria-label="Sidebar navigation">

  <!-- Brand -->
  <div class="sidebar-brand">
    <div class="brand__icon" aria-hidden="true"><i class="fa-solid fa-paw"></i></div>
    <span><?= e(APP_NAME) ?></span>

    <!-- Close button (mobile) -->
    <button class="sidebar-close" id="sidebarClose"
            aria-label="Close navigation"
            aria-controls="appSidebar">
      <i class="fa-solid fa-xmark" aria-hidden="true"></i>
    </button>
  </div>

  <nav class="sidebar-nav" aria-label="Dashboard navigation">

    <?php if ($role === 'admin'): ?>
      <!-- ─── ADMIN ─── -->
      <div class="sidebar-section">Overview</div>
      <a href="<?= BASE_URL ?>/modules/admin/dashboard.php"   class="<?= _sa('dashboard', $ap) ?>">
        <i class="fa-solid fa-chart-pie" aria-hidden="true"></i> Dashboard
      </a>

      <div class="sidebar-section">Management</div>
      <a href="<?= BASE_URL ?>/modules/admin/users.php"         class="<?= _sa('users', $ap) ?>">
        <i class="fa-solid fa-users" aria-hidden="true"></i> Users
      </a>
      <a href="<?= BASE_URL ?>/modules/admin/announcements.php" class="<?= _sa('announcements', $ap) ?>">
        <i class="fa-solid fa-bullhorn" aria-hidden="true"></i> Announcements
      </a>

      <div class="sidebar-section">Reports</div>
      <a href="<?= BASE_URL ?>/modules/admin/reports.php"       class="<?= _sa('reports', $ap) ?>">
        <i class="fa-solid fa-chart-bar" aria-hidden="true"></i> Reports
      </a>
      <a href="<?= BASE_URL ?>/modules/admin/activity-log.php"  class="<?= _sa('activity', $ap) ?>">
        <i class="fa-solid fa-list-check" aria-hidden="true"></i> Activity Log
      </a>

    <?php elseif (in_array($role, ['staff', 'rescue_org'])): ?>
      <!-- ─── STAFF / RESCUE ORG ─── -->
      <div class="sidebar-section">Overview</div>
      <a href="<?= BASE_URL ?>/modules/staff/dashboard.php"      class="<?= _sa('dashboard', $ap) ?>">
        <i class="fa-solid fa-gauge" aria-hidden="true"></i> Dashboard
      </a>

      <div class="sidebar-section">Pets</div>
      <a href="<?= BASE_URL ?>/modules/staff/pets.php"            class="<?= _sa('pets', $ap) ?>">
        <i class="fa-solid fa-paw" aria-hidden="true"></i> Manage Pets
      </a>
      <a href="<?= BASE_URL ?>/modules/staff/add-pet.php"         class="<?= _sa('add-pet', $ap) ?>">
        <i class="fa-solid fa-plus" aria-hidden="true"></i> Add Pet
      </a>
      <a href="<?= BASE_URL ?>/modules/staff/medical.php"         class="<?= _sa('medical', $ap) ?>">
        <i class="fa-solid fa-syringe" aria-hidden="true"></i> Medical Records
      </a>

      <div class="sidebar-section">Adoptions</div>
      <a href="<?= BASE_URL ?>/modules/staff/applications.php"    class="<?= _sa('applications', $ap) ?>">
        <i class="fa-solid fa-file-lines" aria-hidden="true"></i> Applications
      </a>

      <div class="sidebar-section">Reports</div>
      <a href="<?= BASE_URL ?>/modules/staff/animal-report.php"   class="<?= _sa('animal-reports', $ap) ?>">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        Animal Reports
        <?php
        $pendingReports = 0;
        try {
            $pendingReports = (int)db()->query(
                "SELECT COUNT(*) FROM animal_reports WHERE status IN ('Pending','New')"
            )->fetchColumn();
        } catch (Throwable) {}
        if ($pendingReports > 0):
        ?>
          <span class="sidebar-badge" aria-label="<?= $pendingReports ?> pending"><?= $pendingReports ?></span>
        <?php endif; ?>
      </a>

    <?php else: ?>
      <!-- ─── ADOPTER ─── -->
      <div class="sidebar-section">My Account</div>
      <a href="<?= BASE_URL ?>/modules/adopter/dashboard.php"    class="<?= _sa('dashboard', $ap) ?>">
        <i class="fa-solid fa-gauge" aria-hidden="true"></i> Dashboard
      </a>
      <a href="<?= BASE_URL ?>/modules/adopter/profile.php"      class="<?= _sa('profile', $ap) ?>">
        <i class="fa-solid fa-circle-user" aria-hidden="true"></i> My Profile
      </a>
      <a href="<?= BASE_URL ?>/modules/adopter/applications.php" class="<?= _sa('applications', $ap) ?>">
        <i class="fa-solid fa-file-lines" aria-hidden="true"></i> My Applications
      </a>
      <a href="<?= BASE_URL ?>/modules/adopter/medical.php"      class="<?= _sa('medical', $ap) ?>">
        <i class="fa-solid fa-notes-medical" aria-hidden="true"></i> Medical Records
      </a>
      <a href="<?= BASE_URL ?>/modules/adopter/notifications.php" class="<?= _sa('notifications', $ap) ?>">
        <i class="fa-solid fa-bell" aria-hidden="true"></i>
        Notifications
        <?php if ($unreadCount > 0): ?>
          <span class="sidebar-badge" aria-label="<?= $unreadCount ?> unread"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span>
        <?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>/modules/adopter/animal-report.php" class="<?= _sa('animal-report', $ap) ?>">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Report an Animal
      </a>

      <div class="sidebar-section">Browse</div>
      <a href="<?= BASE_URL ?>/modules/adopter/find-pet.php"     class="<?= _sa('find-pet', $ap) ?>">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Find a Pet
      </a>
    <?php endif; ?>

    <!-- ─── ACCOUNT (all roles) ─── -->
    <div class="sidebar-section">Account</div>
    <a href="<?= BASE_URL ?>/logout.php" class="sidebar-logout">
      <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Logout
    </a>

  </nav>

  <!-- Footer user chip -->
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="s-avatar" aria-hidden="true"><?= e($initials) ?></div>
      <div class="sidebar-user-info">
        <div class="name"><?= e($user['full_name'] ?? '') ?></div>
        <?php
        $roleLabels = [
            'admin'      => 'Admin',
            'staff'      => 'Staff',
            'adopter'    => 'Adopter',
            'rescue_org' => 'Rescue Org',
        ];
        ?>
        <div class="role-label"><?= e($roleLabels[$role] ?? ucfirst($role)) ?></div>
      </div>
    </div>
  </div>

</aside>