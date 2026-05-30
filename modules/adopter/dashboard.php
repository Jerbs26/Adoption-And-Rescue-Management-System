<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
session_start_once();
require_login(BASE_URL . '/login.php');
require_role('adopter');

$user       = current_user();
$activePage = 'dashboard';
$pageTitle  = 'My Dashboard';

// Applications (last 5)
try {
    $appsStmt = db()->prepare("
      SELECT a.*, p.name AS pet_name, p.breed, p.primary_image, p.type
      FROM adoption_applications a
      JOIN pets p ON p.id = a.pet_id
      WHERE a.adopter_id = ?
      ORDER BY a.created_at DESC
      LIMIT 5
    ");
    $appsStmt->execute([$user['id']]);
    $apps = $appsStmt->fetchAll() ?: [];
} catch (Exception $e) {
    $apps = [];
}

// Stats
try {
    $statsStmt = db()->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(status = 'Pending')  AS pending,
            SUM(status = 'Approved') AS approved,
            SUM(status = 'Rejected') AS rejected
        FROM adoption_applications
        WHERE adopter_id = ?
    ");
    $statsStmt->execute([$user['id']]);
    $myStats = $statsStmt->fetch() ?: ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
} catch (Exception $e) {
    $myStats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
}

// Available pets count
try {
    $avail = (int)db()->query("SELECT COUNT(*) FROM pets WHERE status = 'Available'")->fetchColumn();
} catch (Exception $e) {
    $avail = 0;
}

// Pet recommendations (3 random available pets not already applied to)
try {
    $recs = db()->prepare("
      SELECT p.id, p.name, p.breed, p.type, p.primary_image, p.age_label
      FROM pets p
      WHERE p.status = 'Available'
        AND p.id NOT IN (
          SELECT pet_id FROM adoption_applications WHERE adopter_id = ?
        )
      ORDER BY RAND()
      LIMIT 3
    ");
    $recs->execute([$user['id']]);
    $recs = $recs->fetchAll();
} catch (Exception $e) {
    $recs = [];
}

// Announcements (last 3)
try {
    $anns = db()->query("SELECT * FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 3")->fetchAll();
} catch (Exception $e) {
    $anns = [];
}

// Profile completion
$profileSteps = [
    'Basic info & contact' => false,
    'Upload valid ID'      => false,
];
try {
    $profileStmt = db()->prepare("
        SELECT ap.*, u.phone AS user_phone, u.full_name
        FROM adopter_profiles ap
        JOIN users u ON u.id = ap.user_id
        WHERE ap.user_id = ?
    ");
    $profileStmt->execute([$user['id']]);
    $profile = $profileStmt->fetch() ?: [];

    $phoneForCheck = !empty($profile['user_phone']) ? $profile['user_phone']
                  : (!empty($profile['phone']) ? $profile['phone'] : '');

    $profileSteps = [
        'Basic info & contact' => !empty($phoneForCheck) && !empty($profile['address']),
        'Upload valid ID' => (!empty($profile['id_document']) && ($profile['id_status'] ?? '') === 'verified'),
    ];
} catch (Exception $e) {
    // leave all steps false
}
$profileDone     = count(array_filter($profileSteps));
$profileTotal    = count($profileSteps);
$profilePct      = $profileTotal > 0 ? (int)round($profileDone / $profileTotal * 100) : 0;
$profileComplete = ($profilePct === 100);

$fullName  = $user['full_name'] ?? '';
$firstName = e($fullName !== '' ? explode(' ', $fullName)[0] : 'there');

include __DIR__ . '/../../includes/dash-head.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<style>
/* Reset & Tokens */
.ad-wrap *, .ad-wrap *::before, .ad-wrap *::after { box-sizing: border-box; }
.ad-wrap {
  --g:       #2d5f44;
  --g2:      #3a7d5a;
  --g-lt:    #eef7f2;
  --g-border:#c5dfd0;
  --amb:     #d97706;
  --amb-lt:  #fff8ed;
  --blu:     #4169e1;
  --blu-lt:  #eef3fc;
  --org:     #e8722a;
  --org-lt:  #fff4ed;
  --red-lt:  #feeeee;
  --red:     #c0392b;
  --bg:      #f5f3ef;
  --card:    #ffffff;
  --border:  rgba(0,0,0,.08);
  --text1:   #1a2820;
  --text2:   #556b5e;
  --text3:   #9aab9f;
  --radius:  16px;
  --radius-sm: 10px;
  --shadow:      0 1px 3px rgba(0,0,0,.06), 0 4px 16px rgba(0,0,0,.06);
  --shadow-hover:0 4px 24px rgba(0,0,0,.12);

  background: var(--bg);
  min-height: 100vh;
  color: var(--text1);
}

/* Layout */
.ad-body {
  padding: 1.5rem 2rem 3rem;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

/* Flash */
.ad-flash {
  display: flex; align-items: center; gap: .6rem;
  background: var(--g-lt); border: 1px solid var(--g-border);
  color: var(--g); border-radius: var(--radius-sm);
  padding: .75rem 1rem; font-size: .85rem; font-weight: 600;
}

/* Profile gate banner */
.profile-gate-banner {
  background: hsl(38 95% 93%); border: 1.5px solid hsl(38 80% 75%);
  border-radius: var(--radius); padding: 1rem 1.4rem;
  display: flex; align-items: flex-start; gap: .85rem; flex-wrap: wrap;
  font-size: .84rem; color: hsl(38 70% 28%); font-weight: 500;
}
.profile-gate-banner i { font-size: 1.2rem; flex-shrink: 0; color: hsl(38 80% 40%); margin-top: .1rem; }
.profile-gate-banner a { color: hsl(38 70% 28%); font-weight: 700; text-decoration: underline; }

/* Hero */
.ad-hero {
  background: linear-gradient(135deg, #213f2e 0%, var(--g2) 100%);
  border-radius: var(--radius);
  padding: 1.75rem 1.5rem;
  display: flex; align-items: center; justify-content: space-between; gap: 1rem;
  position: relative; overflow: hidden; flex-wrap: wrap;
}
.ad-hero::after {
  content: ''; position: absolute; right: -40px; top: -40px;
  width: 220px; height: 220px; border-radius: 50%;
  background: rgba(255,255,255,.04); pointer-events: none;
}
.ad-hero::before {
  content: ''; position: absolute; right: 60px; bottom: -60px;
  width: 160px; height: 160px; border-radius: 50%;
  background: rgba(255,255,255,.03); pointer-events: none;
}
.hero-copy h1 { color: #fff; font-size: clamp(1.15rem, 3vw, 1.55rem); font-weight: 800; margin: 0 0 .25rem; letter-spacing: -.02em; }
.hero-copy p  { color: rgba(255,255,255,.6); font-size: .85rem; margin: 0; }
.hero-actions { display: flex; align-items: center; gap: .75rem; flex-shrink: 0; flex-wrap: wrap; }

.btn-ghost-white {
  background: rgba(255,255,255,.12); color: rgba(255,255,255,.85);
  border: 1px solid rgba(255,255,255,.2);
  font-size: .78rem; font-weight: 600; padding: .5rem 1rem;
  border-radius: 50px; text-decoration: none; white-space: nowrap;
  transition: background .15s;
}
.btn-ghost-white:hover { background: rgba(255,255,255,.2); }
.btn-white {
  background: #fff; color: var(--g);
  font-size: .82rem; font-weight: 700; padding: .55rem 1.25rem;
  border-radius: 50px; text-decoration: none; white-space: nowrap;
  box-shadow: 0 2px 12px rgba(0,0,0,.18);
  display: flex; align-items: center; gap: .4rem;
  transition: box-shadow .15s, transform .15s;
}
.btn-white:hover { box-shadow: 0 4px 20px rgba(0,0,0,.25); transform: translateY(-1px); }
.btn-white.locked, .btn-find.locked {
  opacity: .45; pointer-events: none; cursor: not-allowed; filter: grayscale(.4);
}

/* Stats Row */
.stat-row {
  display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;
}
.stat-card {
  background: var(--card); border: 1px solid var(--border);
  border-radius: var(--radius); padding: 1.1rem 1.2rem;
  display: flex; flex-direction: column; align-items: flex-start; gap: .75rem;
  box-shadow: var(--shadow);
  transition: transform .15s, box-shadow .15s;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-hover); }
.stat-icon {
  width: 42px; height: 42px; border-radius: 11px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1rem; flex-shrink: 0;
}
.si-blue  { background: var(--blu-lt); color: var(--blu); }
.si-amb   { background: var(--amb-lt); color: var(--amb); }
.si-grn   { background: var(--g-lt);   color: var(--g2); }
.si-teal  { background: #e0f4ef;       color: #0d9488; }
.stat-num { font-size: 1.65rem; font-weight: 900; color: var(--text1); line-height: 1; }
.stat-lbl { font-size: .72rem; color: var(--text2); margin-top: .15rem; font-weight: 500; }

.main-grid {
  display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;
}

.bottom-grid {
  display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.25rem;
}

.cc {
  background: var(--card); border: 1px solid var(--border);
  border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow);
}
.cc-head {
  padding: .85rem 1.2rem; border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
}
.cc-title {
  font-size: .82rem; font-weight: 700; color: var(--text1);
  display: flex; align-items: center; gap: .45rem;
}
.cc-title i { color: var(--g2); font-size: .95rem; }
.cc-link {
  font-size: .72rem; color: var(--g2); font-weight: 600;
  border: 1px solid var(--g-border); border-radius: 50px;
  padding: .2rem .7rem; text-decoration: none;
  transition: background .15s; white-space: nowrap;
}
.cc-link:hover { background: var(--g-lt); }
.cc-empty {
  padding: 2.5rem 1rem; text-align: center;
  color: var(--text3); font-size: .85rem;
}
.cc-empty i { font-size: 1.75rem; display: block; margin-bottom: .6rem; opacity: .3; }
.cc-empty a { color: var(--g2); font-weight: 700; text-decoration: none; }

.ns-list { padding: .5rem .8rem; }
.ns-item {
  display: flex; align-items: flex-start; gap: .75rem;
  padding: .7rem .4rem; border-bottom: 1px solid var(--border);
}
.ns-item:last-child { border-bottom: none; }
.ns-dot {
  width: 34px; height: 34px; border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
  font-size: .95rem; flex-shrink: 0;
}
.dot-grn  { background: var(--g-lt);   color: var(--g2); }
.dot-amb  { background: var(--amb-lt); color: var(--amb); }
.dot-blu  { background: var(--blu-lt); color: var(--blu); }
.ns-content { flex: 1; min-width: 0; }
.ns-title { font-size: .82rem; font-weight: 600; color: var(--text1); margin-bottom: .12rem; }
.ns-sub   { font-size: .72rem; color: var(--text2); line-height: 1.45; }
.ns-btn {
  margin-top: .3rem; display: inline-block;
  font-size: .7rem; color: var(--g2); font-weight: 600;
  border: 1px solid var(--g-border); border-radius: 50px;
  padding: .18rem .65rem; text-decoration: none;
  transition: background .15s;
}
.ns-btn:hover { background: var(--g-lt); }

/* Timeline */
.tl-list { padding: .6rem 1.2rem; }
.tl-item {
  display: flex; gap: .75rem; padding: .55rem 0; position: relative;
}
.tl-item:not(:last-child)::after {
  content: ''; position: absolute; left: 11px; top: 28px; bottom: -6px;
  width: 1px; background: var(--border);
}
.tl-icon {
  width: 24px; height: 24px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: .7rem; flex-shrink: 0; z-index: 1;
}
.tl-approved  { background: var(--g-lt);   color: var(--g2); }
.tl-pending   { background: var(--amb-lt); color: var(--amb); }
.tl-submitted { background: var(--blu-lt); color: var(--blu); }
.tl-rejected  { background: var(--red-lt); color: var(--red); }
.tl-title { font-size: .78rem; font-weight: 600; color: var(--text1); }
.tl-sub   { font-size: .7rem;  color: var(--text2); margin-top: .08rem; }
.tl-date  { font-size: .67rem; color: var(--text3); margin-top: .06rem; }
.tl-body  { flex: 1; min-width: 0; }

/* Recommendations */
.rec-list { padding: .6rem 1rem; display: flex; flex-direction: column; gap: .5rem; }
.rec-item { display: flex; align-items: center; gap: .65rem; }
.rec-thumb {
  width: 38px; height: 38px; border-radius: 9px;
  background: var(--bg); display: flex; align-items: center;
  justify-content: center; font-size: 1.1rem; flex-shrink: 0; overflow: hidden;
}
.rec-thumb img { width: 100%; height: 100%; object-fit: cover; }
.rec-name  { font-size: .8rem; font-weight: 600; color: var(--text1); }
.rec-breed { font-size: .7rem; color: var(--text2); }
.rec-badge {
  margin-left: auto; font-size: .65rem; font-weight: 700;
  padding: .15rem .55rem; border-radius: 50px; white-space: nowrap;
}
.badge-cat    { background: var(--blu-lt); color: var(--blu); }
.badge-dog    { background: var(--g-lt);   color: var(--g2); }
.badge-rabbit { background: var(--amb-lt); color: var(--amb); }
.badge-other  { background: #f0f0f0; color: #666; }

/* Profile Completion */
.profile-inner { padding: .85rem 1.2rem; }
.prog-header {
  display: flex; justify-content: space-between;
  font-size: .72rem; color: var(--text2); margin-bottom: .5rem; font-weight: 500;
}
.prog-track {
  height: 7px; background: var(--bg); border-radius: 50px;
  overflow: hidden; margin-bottom: .85rem;
}
.prog-fill {
  height: 100%; background: linear-gradient(90deg, var(--g), var(--g2));
  border-radius: 50px; transition: width .5s ease;
}
.prog-steps { display: flex; flex-direction: column; gap: .35rem; }
.prog-step  { display: flex; align-items: center; gap: .5rem; font-size: .73rem; }
.step-done  { color: var(--g2); }
.step-todo  { color: var(--text3); }
.step-icon-done { color: var(--g2); }
.step-icon-todo { color: var(--border); }

/* Announcements */
.ann-list { padding: .65rem .85rem; display: flex; flex-direction: column; gap: .5rem; }
.ann-item {
  display: flex; gap: .75rem; align-items: flex-start;
  background: var(--bg); border: 1px solid rgba(0,0,0,.07);
  border-radius: 11px; padding: .75rem .9rem;
  transition: box-shadow .15s;
}
.ann-item:hover { box-shadow: 0 2px 10px rgba(0,0,0,.07); }
.ann-dot {
  width: 8px; height: 8px; border-radius: 50%;
  background: var(--org); flex-shrink: 0; margin-top: .35rem;
}
.ann-content { flex: 1; min-width: 0; }
.ann-title {
  font-size: .8rem; font-weight: 700; color: var(--text1);
  margin-bottom: .2rem; line-height: 1.35;
}
.ann-body {
  font-size: .72rem; color: var(--text2); line-height: 1.55;
  word-break: break-word;
}
.ann-date {
  font-size: .63rem; color: var(--text3);
  margin-top: .3rem; display: flex; align-items: center; gap: .3rem;
}
.ann-date::before {
  content: ''; display: inline-block;
  width: 10px; height: 1px; background: var(--text3); opacity: .5;
}

/* Find Banner */
.find-banner {
  background: var(--card); border: 1px solid var(--border);
  border-radius: var(--radius); padding: 1.4rem 1.75rem;
  display: flex; align-items: center; justify-content: space-between;
  gap: 1rem; box-shadow: var(--shadow); flex-wrap: wrap;
}
.find-banner-left { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
.find-banner-icon {
  width: 50px; height: 50px; border-radius: 13px;
  background: var(--g-lt); color: var(--g2);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.2rem; flex-shrink: 0;
}
.find-title { font-weight: 800; font-size: .95rem; color: var(--text1); margin-bottom: .18rem; }
.find-sub   { font-size: .82rem; color: var(--text2); }
.btn-find {
  background: var(--org); color: #fff; font-weight: 700; font-size: .83rem;
  padding: .65rem 1.4rem; border-radius: 50px; text-decoration: none;
  white-space: nowrap; box-shadow: 0 2px 12px rgba(232,114,42,.3);
  display: flex; align-items: center; gap: .4rem;
  transition: opacity .15s, transform .15s;
}
.btn-find:hover { opacity: .9; transform: translateY(-1px); }

/* Responsive */
@media (max-width: 1100px) {
  .stat-row    { grid-template-columns: repeat(2, 1fr); }
  .bottom-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 700px) {
  .ad-body     { padding: 1rem 1rem 2rem; }
  .main-grid   { grid-template-columns: 1fr; }
  .bottom-grid { grid-template-columns: 1fr; }
  .stat-row    { grid-template-columns: repeat(2, 1fr); }
  .hero-actions{ width: 100%; }
  .find-banner { padding: 1rem 1.25rem; }
}
@media (max-width: 420px) {
  .stat-row    { grid-template-columns: 1fr; }
  .hero-actions { flex-direction: column; align-items: stretch; }
  .btn-ghost-white, .btn-white { text-align: center; justify-content: center; }
}

/* Mobile topbar fallback — shows hamburger when topbar.php doesn't supply one */
.ad-mobile-bar {
  display: none;
  align-items: center;
  padding: .6rem 1rem;
  background: var(--card);
  border-bottom: 1px solid var(--border);
  position: sticky;
  top: 0;
  z-index: 100;
}
@media (max-width: 900px) {
  .ad-mobile-bar { display: flex; }
}

</style>

<div class="main-content ad-wrap">
<div class="ad-mobile-bar">
  <button class="sidebar-toggle" id="sidebarToggle"
          type="button"
          aria-label="Open navigation"
          aria-expanded="false"
          aria-controls="appSidebar">
    <i class="fa-solid fa-bars" aria-hidden="true"></i>
  </button>
</div>
<div class="ad-body">

  <?php $flash = get_flash('success'); if ($flash): ?>
  <div class="ad-flash">
    <i class="fa-solid fa-circle-check"></i>
    <?= e($flash) ?>
  </div>
  <?php endif; ?>

  <?php if (!$profileComplete): ?>
  <div class="profile-gate-banner">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <div>
      Your profile is <strong><?= $profilePct ?>% complete</strong>.
      You must complete all steps and have your ID verified before you can apply to adopt a pet.
      <a href="<?= BASE_URL ?>/modules/adopter/profile.php">Complete your profile →</a>
    </div>
  </div>
  <?php endif; ?>

  <!-- HERO -->
  <div class="ad-hero">
    <div class="hero-copy">
      <h1>Welcome back, <?= $firstName ?>!</h1>
      <p>Here's a look at your adoption journey so far.</p>
    </div>
    <div class="hero-actions">
      <a href="<?= BASE_URL ?>/modules/adopter/applications.php" class="btn-ghost-white">
        <i class="fa-solid fa-file-lines"></i> My Applications
      </a>
      <a href="<?= BASE_URL ?>/modules/adopter/find-pet.php"
          class="btn-white<?= !$profileComplete ? ' locked' : '' ?>"
          <?= !$profileComplete ? 'title="Complete your profile to browse pets" aria-disabled="true"' : '' ?>>
        <i class="fa-solid fa-paw"></i> Browse Pets
      </a>
    </div>
  </div>

  <!-- STATS -->
  <div class="stat-row">
    <div class="stat-card">
      <div class="stat-icon si-blue"><i class="fa-solid fa-file-lines"></i></div>
      <div>
        <div class="stat-num"><?= (int)$myStats['total'] ?></div>
        <div class="stat-lbl">Applications</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon si-amb"><i class="fa-solid fa-clock"></i></div>
      <div>
        <div class="stat-num"><?= (int)$myStats['pending'] ?></div>
        <div class="stat-lbl">Pending</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon si-grn"><i class="fa-solid fa-circle-check"></i></div>
      <div>
        <div class="stat-num"><?= (int)$myStats['approved'] ?></div>
        <div class="stat-lbl">Approved</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon si-teal"><i class="fa-solid fa-paw"></i></div>
      <div>
        <div class="stat-num"><?= $avail ?></div>
        <div class="stat-lbl">Pets Available</div>
      </div>
    </div>
  </div>

  <!-- MAIN 2-COL GRID -->
  <div class="main-grid">

    <!-- Next Steps -->
    <div class="cc">
      <div class="cc-head">
        <div class="cc-title"><i class="fa-solid fa-circle-check"></i> Next Steps</div>
      </div>
      <div class="ns-list">

        <?php if ((int)$myStats['approved'] > 0): ?>
        <div class="ns-item">
          <div class="ns-dot dot-grn"><i class="fa-solid fa-calendar"></i></div>
          <div class="ns-content">
            <div class="ns-title">Schedule your meet &amp; greet</div>
            <div class="ns-sub">You have an approved application — contact the shelter to arrange a visit.</div>
            <a href="<?= BASE_URL ?>/modules/adopter/applications.php" class="ns-btn">View approved</a>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($profilePct < 100): ?>
        <div class="ns-item">
          <div class="ns-dot dot-amb"><i class="fa-solid fa-user"></i></div>
          <div class="ns-content">
            <div class="ns-title">Complete your adopter profile (<?= $profilePct ?>%)</div>
            <div class="ns-sub">A complete profile increases your approval chances significantly.</div>
            <a href="<?= BASE_URL ?>/modules/adopter/profile.php" class="ns-btn">Edit profile</a>
          </div>
        </div>
        <?php endif; ?>

        <div class="ns-item">
          <div class="ns-dot dot-blu"><i class="fa-solid fa-magnifying-glass"></i></div>
          <div class="ns-content">
            <div class="ns-title"><?= $avail ?> pets available right now</div>
            <div class="ns-sub">
              <?= $profileComplete
                ? 'Browse the latest listings before they\'re gone.'
                : 'Complete your profile (100%) to unlock pet browsing and adoption applications.' ?>
            </div>
            <?php if ($profileComplete): ?>
            <a href="<?= BASE_URL ?>/modules/adopter/find-pet.php" class="ns-btn">Browse pets</a>
            <?php else: ?>
            <a href="<?= BASE_URL ?>/modules/adopter/profile.php" class="ns-btn">Complete profile</a>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div>

    <!-- Activity Timeline -->
    <div class="cc">
      <div class="cc-head">
        <div class="cc-title"><i class="fa-solid fa-rotate-left"></i> Activity Timeline</div>
        <a href="<?= BASE_URL ?>/modules/adopter/applications.php" class="cc-link">View all</a>
      </div>

      <?php if ($apps): ?>
      <div class="tl-list">
        <?php
        $statusClass = ['Approved' => 'tl-approved', 'Pending' => 'tl-pending', 'Rejected' => 'tl-rejected'];
        $statusIcon  = ['Approved' => 'fa-check', 'Pending' => 'fa-clock', 'Rejected' => 'fa-xmark'];
        foreach ($apps as $app):
          $sc = $statusClass[$app['status']] ?? 'tl-submitted';
          $si = $statusIcon[$app['status']]  ?? 'fa-file';
        ?>
        <div class="tl-item">
          <div class="tl-icon <?= $sc ?>"><i class="fa-solid <?= $si ?>"></i></div>
          <div class="tl-body">
            <div class="tl-title">Application <?= strtolower(e($app['status'])) ?> — <?= e($app['pet_name']) ?></div>
            <div class="tl-sub"><?= e($app['breed']) ?> · <?= e($app['type']) ?></div>
            <div class="tl-date"><?= !empty($app['created_at']) ? date('M j, Y', strtotime($app['created_at'])) : '—' ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="cc-empty">
        <i class="fa-solid fa-file-circle-check"></i>
        No applications yet.<br>
        <a href="<?= BASE_URL ?>/modules/adopter/find-pet.php">Find a pet to adopt</a>
      </div>
      <?php endif; ?>
    </div>

  </div>

  <!-- BOTTOM 3-COL GRID -->
  <div class="bottom-grid">

    <!-- Pet Recommendations -->
    <div class="cc">
      <div class="cc-head">
        <div class="cc-title"><i class="fa-solid fa-heart"></i> You Might Like</div>
        <a href="<?= BASE_URL ?>/modules/adopter/find-pet.php" class="cc-link">See all</a>
      </div>
      <?php if ($recs): ?>
      <div class="rec-list">
        <?php
        $typeBadge = ['Cat' => 'badge-cat', 'Dog' => 'badge-dog', 'Rabbit' => 'badge-rabbit'];
        foreach ($recs as $rec):
          $badge  = $typeBadge[$rec['type']] ?? 'badge-other';
          $age = !empty($rec['age_label']) ? e($rec['age_label']) : '?';
        ?>
        <div class="rec-item">
          <div class="rec-thumb">
            <?php if (!empty($rec['primary_image'])): ?>
              <img src="<?= e(pet_image_url($rec['primary_image'])) ?>" alt="<?= e($rec['name']) ?>">
            <?php else: ?>
              <i class="fa-solid fa-paw"></i>
            <?php endif; ?>
          </div>
          <div style="min-width:0">
            <div class="rec-name"><?= e($rec['name']) ?></div>
            <div class="rec-breed"><?= e($rec['breed']) ?> · <?= $age ?></div>
          </div>
          <span class="rec-badge <?= $badge ?>"><?= e($rec['type']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="cc-empty"><i class="fa-solid fa-paw"></i>No new pets yet.</div>
      <?php endif; ?>
    </div>

    <!-- Profile Completion -->
    <div class="cc">
      <div class="cc-head">
        <div class="cc-title"><i class="fa-solid fa-user-check"></i> Profile Completion</div>
      </div>
      <div class="profile-inner">
        <div class="prog-header">
          <span><?= $profilePct ?>% complete</span>
          <span><?= $profileDone ?> of <?= $profileTotal ?> done</span>
        </div>
        <div class="prog-track">
          <div class="prog-fill" style="width:<?= $profilePct ?>%"></div>
        </div>
        <div class="prog-steps">
          <?php foreach ($profileSteps as $label => $done): ?>
          <div class="prog-step <?= $done ? 'step-done' : 'step-todo' ?>">
            <i class="fa-solid <?= $done ? 'fa-check step-icon-done' : 'fa-circle step-icon-todo' ?>"></i>
            <?= e($label) ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Announcements -->
    <div class="cc">
      <div class="cc-head">
        <div class="cc-title"><i class="fa-solid fa-bullhorn"></i> Announcements</div>
      </div>
      <?php if ($anns): ?>
      <div class="ann-list">
        <?php foreach ($anns as $ann): ?>
        <div class="ann-item">
          <div class="ann-dot"></div>
          <div class="ann-content">
            <div class="ann-title"><?= e($ann['title']) ?></div>
            <div class="ann-body"><?= e($ann['body'] ?? $ann['content'] ?? '') ?></div>
            <div class="ann-date"><?= !empty($ann['created_at']) ? date('M j, Y', strtotime($ann['created_at'])) : '—' ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="cc-empty"><i class="fa-solid fa-bullhorn"></i>No announcements yet.</div>
      <?php endif; ?>
    </div>

  </div>

  <!-- FIND BANNER -->
  <div class="find-banner">
    <div class="find-banner-left">
      <div class="find-banner-icon"><i class="fa-solid fa-house"></i></div>
      <div>
        <div class="find-title">Give a pet a forever home</div>
        <div class="find-sub"><?= $avail ?> adorable pets are waiting to meet you.</div>
      </div>
    </div>
    <a class="btn-find<?= !$profileComplete ? ' locked' : '' ?>"
        href="<?= BASE_URL ?>/modules/adopter/find-pet.php"
        <?= !$profileComplete ? 'title="Complete your profile to browse pets" aria-disabled="true"' : '' ?>>
      <i class="fa-solid fa-paw"></i> Browse Available Pets
    </a>
  </div>

</div>
</div>
<?php include __DIR__ . '/../../includes/dash-foot.php'; ?>