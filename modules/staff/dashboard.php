<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
session_start_once();
require_login(BASE_URL . '/login.php');
require_role('staff', 'rescue_org');

$user        = current_user();
$activePage  = 'dashboard';
$pageTitle   = 'Dashboard';
$isRescueOrg = $user['role'] === 'rescue_org';

if ($isRescueOrg) {
    // Stats scoped to this rescue org's pets only
    $statsStmt = db()->prepare("
        SELECT
            COUNT(*) AS total_pets,
            SUM(status = 'Available')    AS available,
            SUM(status = 'Adopted')      AS adopted,
            SUM(status = 'Pending')      AS pending,
            SUM(status = 'Rescued')      AS rescued,
            SUM(status = 'In Treatment') AS in_treatment
        FROM pets WHERE added_by = ?
    ");
    $statsStmt->execute([$user['id']]);
    $stats = $statsStmt->fetch();

    $pendingAppsStmt = db()->prepare("
        SELECT COUNT(*) FROM adoption_applications aa
        JOIN pets p ON p.id = aa.pet_id
        WHERE p.added_by = ? AND aa.status = 'Pending'
    ");
    $pendingAppsStmt->execute([$user['id']]);
    $pendingApps = $pendingAppsStmt->fetchColumn();

    $underReviewStmt = db()->prepare("
        SELECT COUNT(*) FROM adoption_applications aa
        JOIN pets p ON p.id = aa.pet_id
        WHERE p.added_by = ? AND aa.status = 'Under Review'
    ");
    $underReviewStmt->execute([$user['id']]);
    $underReview = $underReviewStmt->fetchColumn();

    $recentAppsStmt = db()->prepare("
        SELECT aa.id, aa.status, aa.created_at,
               u.full_name AS applicant_name,
               p.name AS pet_name, p.type AS species
        FROM adoption_applications aa
        LEFT JOIN users u ON u.id = aa.adopter_id
        LEFT JOIN pets  p ON p.id = aa.pet_id
        WHERE p.added_by = ?
        ORDER BY aa.created_at DESC LIMIT 5
    ");
    $recentAppsStmt->execute([$user['id']]);
    $recentApps = $recentAppsStmt->fetchAll();

    $totalAnimalReports  = 0;
    $pendingAnimalReports = 0;
    try {
        // animal_reports are submitted by adopters and managed by all rescue orgs.
        // There is no per-org scoping column, so show the full count (same as staff).
        $totalAnimalReports   = (int)db()->query("SELECT COUNT(*) FROM animal_reports")->fetchColumn();
        $pendingAnimalReports = (int)db()->query("SELECT COUNT(*) FROM animal_reports WHERE status IN ('Pending','New')")->fetchColumn();
    } catch (Throwable) {}

} else {
    // Staff sees everything
    $stats = db()->query("
        SELECT
            COUNT(*) AS total_pets,
            SUM(status = 'Available')    AS available,
            SUM(status = 'Adopted')      AS adopted,
            SUM(status = 'Pending')      AS pending,
            SUM(status = 'Rescued')      AS rescued,
            SUM(status = 'In Treatment') AS in_treatment
        FROM pets
    ")->fetch();

    $pendingApps = db()->query("SELECT COUNT(*) FROM adoption_applications WHERE status = 'Pending'")->fetchColumn();
    $underReview = db()->query("SELECT COUNT(*) FROM adoption_applications WHERE status = 'Under Review'")->fetchColumn();

    $recentApps = db()->query("
        SELECT aa.id, aa.status, aa.created_at,
               u.full_name AS applicant_name,
               p.name AS pet_name, p.type AS species
        FROM adoption_applications aa
        LEFT JOIN users u ON u.id = aa.adopter_id
        LEFT JOIN pets  p ON p.id = aa.pet_id
        ORDER BY aa.created_at DESC LIMIT 5
    ")->fetchAll();

    $totalAnimalReports  = 0;
    $pendingAnimalReports = 0;
    try {
        $totalAnimalReports   = (int)db()->query("SELECT COUNT(*) FROM animal_reports")->fetchColumn();
        $pendingAnimalReports = (int)db()->query("SELECT COUNT(*) FROM animal_reports WHERE status IN ('Pending','New')")->fetchColumn();
    } catch (Throwable) {}
}

$adoptionRate = ((int)$stats['total_pets'] > 0)
    ? round(((int)$stats['adopted'] / (int)$stats['total_pets']) * 100, 1)
    : 0;

include __DIR__ . '/../../includes/dash-head.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<div class="main-content">
<?php include __DIR__ . '/../../includes/topbar.php'; ?>
<div class="main-body">

  <div class="page-header">
    <h1><?= $isRescueOrg ? 'Organization Dashboard' : 'Staff Dashboard' ?></h1>
    <?php if ($isRescueOrg): ?>
    <p class="muted" style="font-size:.85rem;margin-top:.25rem">
      Showing data for your organization only.
    </p>
    <?php endif; ?>
  </div>

  <p class="section-label">Overview</p>
  <div class="stats-grid">

    <div class="stat-card">
      <div class="stat-icon stat-icon--green"><i class="fa-solid fa-paw"></i></div>
      <div class="stat-num"><?= (int)$stats['total_pets'] ?></div>
      <div class="stat-label"><?= $isRescueOrg ? 'Your Pets' : 'Total Pets' ?></div>
    </div>

    <div class="stat-card">
      <div class="stat-icon stat-icon--orange"><i class="fa-solid fa-house"></i></div>
      <div class="stat-num"><?= (int)$stats['available'] ?></div>
      <div class="stat-label">Available</div>
    </div>

    <div class="stat-card">
      <div class="stat-icon stat-icon--blue"><i class="fa-solid fa-heart"></i></div>
      <div class="stat-num"><?= (int)$stats['adopted'] ?></div>
      <div class="stat-label">Adopted</div>
    </div>

    <div class="stat-card">
      <div class="stat-icon stat-icon--violet"><i class="fa-solid fa-file-lines"></i></div>
      <div class="stat-num"><?= (int)$pendingApps ?></div>
      <div class="stat-label">Pending Applications</div>
    </div>

    <div class="stat-card">
      <div class="stat-icon stat-icon--rose"><i class="fa-solid fa-syringe"></i></div>
      <div class="stat-num"><?= (int)$stats['in_treatment'] ?></div>
      <div class="stat-label">In Treatment</div>
    </div>

    <a class="stat-card stat-card--link" href="<?= BASE_URL ?>/modules/staff/animal-report.php"
       style="text-decoration:none;color:inherit">
      <div class="stat-icon stat-icon--teal"><i class="fa-solid fa-flag"></i></div>
      <div class="stat-num"><?= $totalAnimalReports ?></div>
      <div class="stat-label">
        Animal Reports
        <?php if ($pendingAnimalReports > 0): ?>
          <span style="display:inline-block;margin-left:.3rem;background:#e11d48;color:#fff;
                       font-size:.65rem;font-weight:800;padding:.1rem .4rem;border-radius:99px;
                       vertical-align:middle"><?= $pendingAnimalReports ?></span>
        <?php endif; ?>
      </div>
    </a>

  </div>

  <p class="section-label">Activity</p>
  <div class="dash-lower-grid">

    <div class="card" style="overflow:visible">
      <div class="dash-panel-header">
        <span class="dash-panel-title">Recent Applications</span>
        <a href="<?= BASE_URL ?>/modules/staff/applications.php" class="dash-panel-link">View all</a>
      </div>
      <div class="dash-apps-table-wrap">
        <table class="dash-apps-table">
          <thead>
            <tr>
              <th>Applicant</th><th>Pet</th><th>Status</th><th>Date</th><th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!empty($recentApps)): ?>
            <?php foreach ($recentApps as $app): ?>
              <?php
                $rawStatus  = strtolower(trim((string)$app['status']));
                $badgeClass = match(true) {
                    str_contains($rawStatus, 'approv')                                        => 'approved',
                    str_contains($rawStatus, 'reject') || str_contains($rawStatus, 'declin') => 'rejected',
                    str_contains($rawStatus, 'review') || str_contains($rawStatus, 'under')  => 'review',
                    str_contains($rawStatus, 'withdrawn')                                    => 'withdrawn',
                    default                                                                  => 'pending',
                };
                $dateStr = '';
                if (!empty($app['created_at'])) {
                    try { $dateStr = (new DateTimeImmutable($app['created_at']))->format('d M Y'); }
                    catch (Exception) { $dateStr = e($app['created_at']); }
                }
              ?>
              <tr>
                <td data-label="Applicant">
                  <div style="font-weight:600"><?= e((string)($app['applicant_name'] ?? '—')) ?></div>
                  <div class="muted small">#<?= (int)$app['id'] ?></div>
                </td>
                <td data-label="Pet">
                  <div><?= e((string)($app['pet_name'] ?? '—')) ?></div>
                  <div class="muted small"><?= e((string)($app['species'] ?? '')) ?></div>
                </td>
                <td data-label="Status"><?= status_badge($app['status']) ?></td>
                <td data-label="Date" class="muted small"><?= $dateStr ?></td>
                <td data-label="Action">
                  <?php if (in_array($badgeClass, ['pending', 'review'])): ?>
                    <a href="<?= BASE_URL ?>/modules/staff/applications.php?status=<?= urlencode($app['status']) ?>" class="btn btn-sm btn-secondary">
                      <i class="fa-solid fa-magnifying-glass"></i> Review
                    </a>
                  <?php else: ?>
                    <a href="<?= BASE_URL ?>/modules/staff/applications.php" class="btn btn-sm btn-ghost">View</a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="5" style="padding:2rem;text-align:center" class="muted">No applications yet.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card" style="overflow:hidden">
      <div class="dash-panel-header">
        <span class="dash-panel-title">Pet Inventory</span>
      </div>
      <div class="dash-rate-row">
        <?php
          $r    = 24;
          $circ = round(2 * M_PI * $r, 2);
          $dash = round($circ * min($adoptionRate, 100) / 100, 2);
        ?>
        <div class="dash-rate-ring">
          <svg width="60" height="60" viewBox="0 0 60 60" aria-hidden="true">
            <circle cx="30" cy="30" r="<?= $r ?>" fill="none" stroke="var(--border)" stroke-width="5"/>
            <circle cx="30" cy="30" r="<?= $r ?>" fill="none" stroke="var(--success)" stroke-width="5"
                    stroke-dasharray="<?= $dash ?> <?= $circ ?>"
                    stroke-dashoffset="<?= round($circ / 4, 2) ?>"
                    stroke-linecap="round"/>
          </svg>
          <div class="dash-rate-pct"><?= $adoptionRate ?>%</div>
        </div>
        <div>
          <strong>Adoption Rate</strong>
          <div class="muted small"><?= (int)$stats['adopted'] ?> of <?= (int)$stats['total_pets'] ?> pets adopted.</div>
        </div>
      </div>
      <ul class="dash-inventory-list">
        <li class="dash-inventory-item">
          <span class="dash-inventory-dot" style="background:var(--success)"></span>
          <span class="dash-inventory-label">Available</span>
          <span class="dash-inventory-val"><?= (int)$stats['available'] ?></span>
        </li>
        <li class="dash-inventory-item">
          <span class="dash-inventory-dot" style="background:var(--info)"></span>
          <span class="dash-inventory-label">Adopted</span>
          <span class="dash-inventory-val"><?= (int)$stats['adopted'] ?></span>
        </li>
        <li class="dash-inventory-item">
          <span class="dash-inventory-dot" style="background:var(--warning)"></span>
          <span class="dash-inventory-label">Pending</span>
          <span class="dash-inventory-val"><?= (int)$stats['pending'] ?></span>
        </li>
        <li class="dash-inventory-item">
          <span class="dash-inventory-dot" style="background:#0891b2"></span>
          <span class="dash-inventory-label">Rescued</span>
          <span class="dash-inventory-val"><?= (int)$stats['rescued'] ?></span>
        </li>
        <li class="dash-inventory-item" style="border-top:2px solid var(--border);margin-top:.25rem;padding-top:.75rem">
          <span class="dash-inventory-dot" style="background:var(--primary)"></span>
          <span class="dash-inventory-label">Pending Applications</span>
          <span class="dash-inventory-val"><?= (int)$pendingApps ?></span>
        </li>
        <li class="dash-inventory-item">
          <span class="dash-inventory-dot" style="background:var(--danger)"></span>
          <span class="dash-inventory-label">Under Review</span>
          <span class="dash-inventory-val"><?= (int)$underReview ?></span>
        </li>
      </ul>
    </div>

  </div>
</div>
</div>

<style>
/* ── Section label ─────────────────────────────────────────── */
.section-label {
  font-size: .68rem; font-weight: 700; letter-spacing: .08em;
  text-transform: uppercase; color: var(--muted-fg); margin-bottom: .75rem;
}

/* ── Stats grid ─────────────────────────────────────────────
   Desktop  : up to 3 equal columns
   Tablet   : 2 columns
   Mobile   : 2 columns (compact)
   XS       : single column
   ─────────────────────────────────────────────────────────── */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: .875rem;
  margin-bottom: 1.75rem;
}
@media (max-width: 900px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 480px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); gap: .6rem; }
}
@media (max-width: 340px) {
  .stats-grid { grid-template-columns: 1fr; }
}

/* ── Stat card ──────────────────────────────────────────────── */
.stat-card {
  background: var(--card); border: 1px solid var(--border);
  border-radius: var(--radius, 10px); padding: 1.1rem 1.25rem;
  display: flex; flex-direction: column; gap: .65rem;
}
@media (max-width: 480px) {
  .stat-card { padding: .85rem 1rem; gap: .5rem; }
}

.stat-icon {
  width: 36px; height: 36px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center; font-size: .88rem;
}
@media (max-width: 480px) {
  .stat-icon { width: 32px; height: 32px; font-size: .8rem; border-radius: 7px; }
}

.stat-icon--green  { background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0; }
.stat-icon--orange { background:#fff7ed;color:#ea580c;border:1px solid #fed7aa; }
.stat-icon--blue   { background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe; }
.stat-icon--violet { background:#f5f3ff;color:#7c3aed;border:1px solid #ddd6fe; }
.stat-icon--rose   { background:#fff1f2;color:#e11d48;border:1px solid #fecdd3; }
.stat-icon--teal   { background:#f0fdfa;color:#0d9488;border:1px solid #99f6e4; }

.stat-card--link { cursor:pointer;transition:box-shadow .18s,transform .18s; }
.stat-card--link:hover { box-shadow:0 4px 18px rgba(0,0,0,.10);transform:translateY(-2px); }

.stat-num {
  font-size: 1.9rem; font-weight: 700; letter-spacing: -.5px; line-height: 1;
}
@media (max-width: 480px) {
  .stat-num { font-size: 1.5rem; }
}

.stat-label {
  font-size: .78rem; font-weight: 500; color: var(--muted-fg); margin-top: .1rem;
}
@media (max-width: 480px) {
  .stat-label { font-size: .72rem; }
}

/* ── Lower activity grid ────────────────────────────────────
   Desktop  : table + sidebar (1fr 320px)
   Tablet+  : single column stack
   ─────────────────────────────────────────────────────────── */
.dash-lower-grid {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: .875rem;
}
@media (max-width: 1024px) {
  .dash-lower-grid { grid-template-columns: 1fr; }
}

/* ── Panel header ───────────────────────────────────────────── */
.dash-panel-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: .85rem 1.1rem; border-bottom: 1px solid var(--border);
  flex-wrap: wrap; gap: .35rem;
}
.dash-panel-title { font-size: .875rem; font-weight: 600; }
.dash-panel-link  { font-size: .75rem; font-weight: 500; color: var(--primary); text-decoration: none; }
.dash-panel-link:hover { opacity: .75; }

/* ── Recent applications table — Desktop ────────────────────── */
.dash-apps-table { width: 100%; border-collapse: collapse; }
.dash-apps-table thead th {
  font-size: .68rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
  color: var(--muted-fg); padding: .65rem 1rem; text-align: left;
  background: var(--muted); border-bottom: 1px solid var(--border);
}
.dash-apps-table tbody tr { border-bottom: 1px solid var(--border); transition: background .15s; }
.dash-apps-table tbody tr:last-child { border-bottom: none; }
.dash-apps-table tbody tr:hover { background: var(--muted); }
.dash-apps-table td { padding: .75rem 1rem; font-size: .84rem; vertical-align: middle; }

/* ── Mobile: table → card rows ──────────────────────────────── */
@media (max-width: 640px) {

  /* Wrapper: no clipping, no horizontal scroll */
  .dash-apps-table-wrap {
    overflow: visible !important;
    padding: .5rem .75rem .5rem;
  }

  /* Hide desktop thead */
  .dash-apps-table thead { display: none; }

  /* Table and tbody become block so rows stack */
  .dash-apps-table,
  .dash-apps-table tbody { display: block; width: 100%; }

  /* Each <tr> becomes a card */
  .dash-apps-table tbody tr {
    display: block;
    margin-bottom: .875rem;
    border: 1px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
    background: var(--card);
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
  }

  /* Every <td>: row with label + value */
  .dash-apps-table td {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: .6rem 1rem;
    font-size: .84rem;
    border-bottom: 1px solid var(--border);
    white-space: normal;
    box-sizing: border-box;
    width: 100%;
  }

  /* Label pseudo-element */
  .dash-apps-table td[data-label]::before {
    content: attr(data-label);
    font-size: .65rem;
    font-weight: 700;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: var(--muted-fg);
    min-width: 68px;
    flex-shrink: 0;
  }

  /* First cell — card header row */
  .dash-apps-table td:first-child {
    flex-direction: column;
    align-items: flex-start;
    gap: .1rem;
    background: var(--muted);
    padding: .75rem 1rem;
    border-bottom: 1.5px solid var(--border);
  }
  .dash-apps-table td:first-child::before { display: none; }

  /* Action cell — full-width button, no label, no bottom border */
  .dash-apps-table td:last-child {
    display: block;
    padding: .65rem .75rem .75rem;
    border-bottom: none;
  }
  .dash-apps-table td:last-child::before { display: none; }
  .dash-apps-table td:last-child .btn {
    display: flex;
    width: 100%;
    box-sizing: border-box;
    justify-content: center;
    align-items: center;
    text-align: center;
  }
}

/* ── Inventory panel ────────────────────────────────────────── */
.dash-rate-row {
  display: flex; align-items: center; gap: 1rem;
  padding: 1rem 1.1rem; border-bottom: 1px solid var(--border);
  flex-wrap: wrap;
}
.dash-rate-ring { position: relative; width: 60px; height: 60px; flex-shrink: 0; }
.dash-rate-pct  {
  position: absolute; inset: 0; display: flex; align-items: center;
  justify-content: center; font-size: .68rem; font-weight: 700; color: var(--text);
}
.dash-inventory-list  { list-style: none; padding: 0; margin: 0; }
.dash-inventory-item  {
  display: flex; align-items: center; gap: .65rem;
  padding: .7rem 1.1rem; border-bottom: 1px solid var(--border); font-size: .84rem;
}
.dash-inventory-item:last-child { border-bottom: none; }
.dash-inventory-dot   { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.dash-inventory-label { flex: 1; }
.dash-inventory-val   { font-weight: 700; }

/* On mobile the inventory card gets same full-width treatment */
@media (max-width: 640px) {
  .dash-inventory-item { padding: .65rem 1rem; font-size: .82rem; }
}

/* ── Page header ────────────────────────────────────────────── */
.page-header { margin-bottom: 1.25rem; }
@media (max-width: 480px) {
  .page-header h1 { font-size: 1.5rem; }
}

/* ── Mobile layout lock ── */
@media (max-width: 900px) {
  html, body {
    overflow-x: hidden !important;
    max-width: 100vw !important;
  }
  .dashboard-wrap {
    overflow-x: hidden !important;
    max-width: 100vw !important;
    width: 100vw !important;
  }
  .dashboard-wrap > .sidebar,
  .sidebar {
    flex: 0 0 0 !important;
    width: 0 !important;
    min-width: 0 !important;
    max-width: 0 !important;
    overflow: hidden !important;
    padding: 0 !important;
  }
  .sidebar {
    position: fixed !important;
    width: min(250px, 85vw) !important;
    left: 0 !important; top: 0 !important;
    height: 100% !important;
    overflow-y: auto !important;
    transform: translateX(-110%) !important;
    z-index: 150 !important;
  }
  .sidebar.open {
    transform: translateX(0) !important;
    width: min(250px, 85vw) !important;
  }
  .main-content {
    flex: 1 1 100% !important;
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    overflow-x: hidden !important;
    box-sizing: border-box !important;
  }
  .main-body {
    width: 100% !important;
    box-sizing: border-box !important;
    overflow-x: hidden !important;
  }
}

/* ── Outer card for Recent Applications: don't clip children ── */
@media (max-width: 640px) {
  .dash-lower-grid > .card:first-child {
    overflow: visible !important;
  }
  .dash-apps-table-wrap {
    overflow: visible !important;
  }
}

</style>


<script>
/* Prevent horizontal scroll on mobile without touching sidebar width/flex.
   Sidebar open/close is handled entirely by main.js (initSidebar).        */
(function(){
  function fixLayout(){
    var mc = document.querySelector('.main-content');
    var dw = document.querySelector('.dashboard-wrap');
    if(window.innerWidth <= 900){
      if(mc){ mc.style.setProperty('width','100%','important'); mc.style.setProperty('max-width','100%','important'); mc.style.setProperty('flex','1 1 auto','important'); }
      if(dw){ dw.style.setProperty('overflow-x','hidden','important'); }
      document.documentElement.style.setProperty('overflow-x','hidden','important');
      document.body.style.setProperty('overflow-x','hidden','important');
    } else {
      if(mc){ mc.style.removeProperty('width'); mc.style.removeProperty('max-width'); mc.style.removeProperty('flex'); }
    }
  }
  document.addEventListener('DOMContentLoaded', fixLayout);
  window.addEventListener('resize', fixLayout);
  fixLayout();
})();
</script>
<?php include __DIR__ . '/../../includes/dash-foot.php'; ?>