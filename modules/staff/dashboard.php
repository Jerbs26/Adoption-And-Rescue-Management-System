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

    <div class="card" style="overflow:hidden">
      <div class="dash-panel-header">
        <span class="dash-panel-title">Recent Applications</span>
        <a href="<?= BASE_URL ?>/modules/staff/applications.php" class="dash-panel-link">View all</a>
      </div>
      <div style="overflow-x:auto">
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
                <td>
                  <div style="font-weight:600"><?= e((string)($app['applicant_name'] ?? '—')) ?></div>
                  <div class="muted small">#<?= (int)$app['id'] ?></div>
                </td>
                <td>
                  <div><?= e((string)($app['pet_name'] ?? '—')) ?></div>
                  <div class="muted small"><?= e((string)($app['species'] ?? '')) ?></div>
                </td>
                <td><?= status_badge($app['status']) ?></td>
                <td class="muted small"><?= $dateStr ?></td>
                <td>
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
.section-label { font-size:.68rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted-fg);margin-bottom:.75rem; }
.stats-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.875rem;margin-bottom:1.75rem; }
.stat-card { background:var(--card);border:1px solid var(--border);border-radius:var(--radius,10px);padding:1.1rem 1.25rem;display:flex;flex-direction:column;gap:.65rem; }
.stat-icon { width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.88rem; }
.stat-icon--green  { background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0; }
.stat-icon--orange { background:#fff7ed;color:#ea580c;border:1px solid #fed7aa; }
.stat-icon--blue   { background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe; }
.stat-icon--violet { background:#f5f3ff;color:#7c3aed;border:1px solid #ddd6fe; }
.stat-icon--rose   { background:#fff1f2;color:#e11d48;border:1px solid #fecdd3; }
.stat-icon--teal   { background:#f0fdfa;color:#0d9488;border:1px solid #99f6e4; }
.stat-card--link { cursor:pointer;transition:box-shadow .18s,transform .18s; }
.stat-card--link:hover { box-shadow:0 4px 18px rgba(0,0,0,.10);transform:translateY(-2px); }
.stat-num { font-size:1.9rem;font-weight:700;letter-spacing:-.5px;line-height:1; }
.stat-label { font-size:.78rem;font-weight:500;color:var(--muted-fg);margin-top:.1rem; }
.dash-lower-grid { display:grid;grid-template-columns:1fr 320px;gap:.875rem; }
@media(max-width:900px){ .dash-lower-grid { grid-template-columns:1fr; } }
.dash-panel-header { display:flex;align-items:center;justify-content:space-between;padding:.85rem 1.1rem;border-bottom:1px solid var(--border); }
.dash-panel-title { font-size:.875rem;font-weight:600; }
.dash-panel-link { font-size:.75rem;font-weight:500;color:var(--primary);text-decoration:none; }
.dash-panel-link:hover { opacity:.75; }
.dash-apps-table { width:100%;border-collapse:collapse; }
.dash-apps-table thead th { font-size:.68rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted-fg);padding:.65rem 1rem;text-align:left;background:var(--muted);border-bottom:1px solid var(--border); }
.dash-apps-table tbody tr { border-bottom:1px solid var(--border);transition:background .15s; }
.dash-apps-table tbody tr:last-child { border-bottom:none; }
.dash-apps-table tbody tr:hover { background:var(--muted); }
.dash-apps-table td { padding:.75rem 1rem;font-size:.84rem;vertical-align:middle; }
.dash-rate-row { display:flex;align-items:center;gap:1rem;padding:1rem 1.1rem;border-bottom:1px solid var(--border); }
.dash-rate-ring { position:relative;width:60px;height:60px;flex-shrink:0; }
.dash-rate-pct { position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:.68rem;font-weight:700;color:var(--text); }
.dash-inventory-list { list-style:none;padding:0;margin:0; }
.dash-inventory-item { display:flex;align-items:center;gap:.65rem;padding:.7rem 1.1rem;border-bottom:1px solid var(--border);font-size:.84rem; }
.dash-inventory-item:last-child { border-bottom:none; }
.dash-inventory-dot { width:8px;height:8px;border-radius:50%;flex-shrink:0; }
.dash-inventory-label { flex:1; }
.dash-inventory-val { font-weight:700; }
</style>

<?php include __DIR__ . '/../../includes/dash-foot.php'; ?>