<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
session_start_once();
require_login(BASE_URL . '/login.php');
require_role('admin');

$user       = current_user();
$activePage = 'reports';
$pageTitle  = 'Reports & Analytics';

// Safe HTML escape fallback in case e() isn't globally defined
if (!function_exists('e')) {
    function e($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }
}

// Merge with safe defaults so a failed/empty query never causes fatal errors
$petStats = array_merge(
    ['total' => 0, 'available' => 0, 'adopted' => 0, 'pending' => 0,
     'in_treatment' => 0, 'dogs' => 0, 'cats' => 0, 'rabbits' => 0, 'birds' => 0, 'others' => 0],
    db()->query("
        SELECT
            COUNT(*) AS total,
            SUM(status = 'Available')    AS available,
            SUM(status = 'Adopted')      AS adopted,
            SUM(status = 'Pending')      AS pending,
            SUM(status = 'In Treatment') AS in_treatment,
            SUM(type = 'Dog')    AS dogs,
            SUM(type = 'Cat')    AS cats,
            SUM(type = 'Rabbit') AS rabbits,
            SUM(type = 'Bird')   AS birds,
            SUM(type = 'Other')  AS others
        FROM pets
    ")->fetch() ?: []
);

$userStats = array_merge(
    ['total' => 0, 'admins' => 0, 'adopters' => 0, 'active' => 0],
    db()->query("
        SELECT
            COUNT(*) AS total,
            SUM(role = 'admin')   AS admins,
            SUM(role = 'adopter') AS adopters,
            SUM(is_active = 1)    AS active
        FROM users
    ")->fetch() ?: []
);

$appStats = array_merge(
    ['total' => 0, 'pending' => 0, 'under_review' => 0, 'approved' => 0, 'rejected' => 0],
    db()->query("
        SELECT
            COUNT(*) AS total,
            SUM(status = 'Pending')      AS pending,
            SUM(status = 'Under Review') AS under_review,
            SUM(status = 'Approved')     AS approved,
            SUM(status = 'Rejected')     AS rejected
        FROM adoption_applications
    ")->fetch() ?: []
);

$recentActivity = db()->query("
    SELECT aa.id, aa.status, aa.created_at, aa.updated_at,
           u.full_name, p.name AS pet_name, p.type AS pet_type
    FROM adoption_applications aa
    JOIN users u ON u.id = aa.adopter_id
    JOIN pets p ON p.id = aa.pet_id
    ORDER BY aa.updated_at DESC
    LIMIT 15
")->fetchAll();

// Monthly adoptions for the last 6 months
try {
    $monthlyData = db()->query("
        SELECT DATE_FORMAT(updated_at, '%b %Y') AS month_label,
        DATE_FORMAT(updated_at, '%Y-%m') AS month_key,
        COUNT(*) AS count
        FROM adoption_applications
        WHERE status = 'Approved'
        AND updated_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month_key, month_label
        ORDER BY month_key ASC
    ")->fetchAll();
} catch (Exception $e) {
    $monthlyData = [];
}

// Cast to int before arithmetic to avoid null/string comparison errors
$adoptionRate = (int)$petStats['total'] > 0
    ? round(((int)$petStats['adopted'] / (int)$petStats['total']) * 100, 1)
    : 0;

$approvalRate = (int)$appStats['total'] > 0
    ? round(((int)$appStats['approved'] / (int)$appStats['total']) * 100, 1)
    : 0;

// Pre-compute SVG ring constants once — reused by both rate rings below
$_ringR  = 45;
$circ    = round(2 * M_PI * $_ringR, 2);
$offset1 = round($circ - (min(100, $adoptionRate) / 100) * $circ, 2);
$offset2 = round($circ - (min(100, $approvalRate) / 100) * $circ, 2);

include __DIR__ . '/../../includes/dash-head.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<style>
/* Report-specific tokens */
.rp-wrap {
  --grn:      #2d5f44;
  --grn2:     #3a7d5a;
  --grn-lt:   #eef7f2;
  --grn-bd:   #c5dfd0;
  --amb:      #d97706;
  --amb-lt:   #fff8ed;
  --blu:      #3b6fd4;
  --blu-lt:   #eef3fc;
  --red:      #c0392b;
  --red-lt:   #feeeee;
  --pur:      #7c3aed;
  --pur-lt:   #f3f0ff;
  --text1:    #1a2820;
  --text2:    #556b5e;
  --text3:    #9aab9f;
  --card:     #ffffff;
  --bg:       #f5f3ef;
  --border:   rgba(0,0,0,.08);
  --shadow:   0 1px 3px rgba(0,0,0,.06), 0 4px 16px rgba(0,0,0,.06);
  --shadow-h: 0 4px 24px rgba(0,0,0,.12);
  --radius:   16px;
  --radius-s: 10px;
  font-family: 'DM Sans', 'Inter', system-ui, sans-serif;
  color: var(--text1);
}

/* Page body */
.rp-body {
  max-width: 100%;
  margin: 0 auto;
  padding: 1.75rem 2.5rem 3rem;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

/* Page header */
.rp-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
}
.rp-header h1 {
  margin: 0 0 .2rem;
}
.rp-header p {
  font-size: .82rem;
  color: var(--text2);
  margin: 0;
}
.rp-timestamp {
  font-size: .75rem;
  color: var(--text3);
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 50px;
  padding: .3rem .85rem;
  white-space: nowrap;
}

/* KPI row */
.kpi-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
}
.kpi-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 1.25rem 1.3rem;
  box-shadow: var(--shadow);
  display: flex;
  flex-direction: column;
  gap: .5rem;
  transition: transform .15s, box-shadow .15s;
  position: relative;
  overflow: hidden;
}
.kpi-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  border-radius: var(--radius) var(--radius) 0 0;
}
.kpi-card.kpi-grn::before  { background: var(--grn2); }
.kpi-card.kpi-amb::before  { background: var(--amb); }
.kpi-card.kpi-blu::before  { background: var(--blu); }
.kpi-card.kpi-pur::before  { background: var(--pur); }
.kpi-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-h); }
.kpi-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.kpi-icon {
  width: 38px; height: 38px;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: .95rem;
}
.kpi-grn .kpi-icon { background: var(--grn-lt); color: var(--grn2); }
.kpi-amb .kpi-icon { background: var(--amb-lt); color: var(--amb); }
.kpi-blu .kpi-icon { background: var(--blu-lt); color: var(--blu); }
.kpi-pur .kpi-icon { background: var(--pur-lt); color: var(--pur); }
.kpi-badge {
  font-size: .68rem; font-weight: 700;
  padding: .15rem .55rem;
  border-radius: 50px;
}
.kpi-grn .kpi-badge { background: var(--grn-lt); color: var(--grn2); }
.kpi-amb .kpi-badge { background: var(--amb-lt); color: var(--amb); }
.kpi-blu .kpi-badge { background: var(--blu-lt); color: var(--blu); }
.kpi-pur .kpi-badge { background: var(--pur-lt); color: var(--pur); }
.kpi-num {
  font-size: 2.4rem;
  font-weight: 900;
  color: var(--text1);
  line-height: 1;
  letter-spacing: -.04em;
}
.kpi-label {
  font-size: .75rem;
  color: var(--text2);
  font-weight: 500;
}

/* 2-col main grid */
.main-grid {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 1.25rem;
}
.span2 { grid-column: span 2; }

/* Section card */
.sec-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  overflow: hidden;
}
.sec-head {
  padding: .9rem 1.25rem;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.sec-title {
  font-size: .82rem;
  font-weight: 800;
  color: var(--text1);
  display: flex;
  align-items: center;
  gap: .45rem;
}
.sec-title i { color: var(--grn2); }

/* Stat rows inside cards */
.stat-list { padding: .5rem .75rem; }
.stat-row-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: .5rem .5rem;
  border-bottom: 1px solid var(--border);
  gap: .5rem;
}
.stat-row-item:last-child { border-bottom: none; }
.stat-row-item .lbl {
  font-size: .8rem;
  color: var(--text2);
  display: flex;
  align-items: center;
  gap: .5rem;
  min-width: 100px;  /* fixed width — accommodates 'Under Review' */
  flex-shrink: 0;
}
.stat-row-item .lbl .dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}
.stat-row-item .val {
  font-size: .85rem;
  font-weight: 700;
  color: var(--text1);
  min-width: 1.5rem;
  text-align: right;
  flex-shrink: 0;
}
.stat-row-item .bar-wrap {
  flex: 1;                /* takes all remaining space */
  min-width: 60px;        /* always visible even when value is 0 */
  height: 5px;
  background: var(--bg);
  border-radius: 50px;
  overflow: hidden;
  margin: 0 .75rem;
}
.stat-row-item .bar-fill {
  height: 100%;
  border-radius: 50px;
  background: var(--grn2);
  min-width: 0;
}

/* Progress ring */
.rate-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 1.25rem 1rem;
  gap: .5rem;
}
.ring-container {
  position: relative;
  width: 110px; height: 110px;
}
.ring-container svg { transform: rotate(-90deg); }
.ring-bg   { fill: none; stroke: var(--bg); stroke-width: 10; }
.ring-fill { fill: none; stroke-width: 10; stroke-linecap: round;
              transition: stroke-dashoffset .8s ease; }
.ring-grn { stroke: var(--grn2); }
.ring-amb { stroke: var(--amb); }
.ring-label {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.ring-pct  { font-size: 1.4rem; font-weight: 900; color: var(--text1); line-height: 1; }
.ring-sub  { font-size: .62rem; color: var(--text2); font-weight: 600; text-transform: uppercase; letter-spacing: .05em; }
.rate-title { font-size: .78rem; font-weight: 700; color: var(--text1); text-align: center; }
.rate-desc  { font-size: .7rem; color: var(--text2); text-align: center; }
.rates-row  { display: grid; grid-template-columns: 1fr 1fr; }
.rates-row .rate-wrap:first-child { border-right: 1px solid var(--border); }

/* Bar chart */
.bar-chart-wrap { padding: 1rem 1.25rem 1.25rem; }
.bar-chart {
  display: flex;
  align-items: flex-end;
  gap: .5rem;
  height: 100px;
}
.bar-col {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  height: 100%;
  justify-content: flex-end;
}
.bar-val {
  font-size: .68rem; font-weight: 700; color: var(--text1);
  margin-bottom: .25rem; line-height: 1;
}
.bar-block {
  width: 100%;
  background: linear-gradient(180deg, var(--grn2), var(--grn));
  border-radius: 5px 5px 0 0;
  min-height: 4px;
  flex-shrink: 0;
}
.bar-labels {
  display: flex;
  gap: .5rem;
  margin-top: .4rem;
}
.bar-labels span {
  flex: 1;
  font-size: .6rem; color: var(--text3);
  text-align: center; white-space: nowrap;
  overflow: hidden; text-overflow: ellipsis;
}

/* Recent adoptions table */
.adoptions-table { width: 100%; border-collapse: collapse; }
.adoptions-table th {
  padding: .65rem 1.25rem;
  font-size: .72rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .05em;
  color: var(--text2);
  background: var(--bg);
  text-align: left;
  white-space: nowrap;
}
.adoptions-table td {
  padding: .75rem 1.25rem;
  font-size: .85rem;
  border-bottom: 1px solid var(--border);
  vertical-align: middle;
}
.adoptions-table tbody tr:last-child td { border-bottom: none; }
.adoptions-table tbody tr:hover td { background: #fafaf9; }
.adopter-name { font-weight: 700; color: var(--text1); }
.pet-pill {
  display: inline-flex; align-items: center; gap: .35rem;
  background: var(--grn-lt); color: var(--grn2);
  font-size: .75rem; font-weight: 600;
  padding: .2rem .65rem; border-radius: 50px;
}
.date-chip {
  font-size: .75rem; color: var(--text2);
}
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  font-size: .72rem;
  font-weight: 700;
  padding: .2rem .65rem;
  border-radius: 50px;
  white-space: nowrap;
}

/* Responsive */
@media(max-width: 1000px) {
  .kpi-row   { grid-template-columns: repeat(2, 1fr); }
  .main-grid { grid-template-columns: 1fr 1fr; }
  .span2     { grid-column: span 2; }
  .rp-body   { padding: 1.25rem 1.5rem 2.5rem; }
}
@media(max-width: 768px) {
  .rp-body   { padding: 1.1rem 1.1rem 2rem; }
  .kpi-num   { font-size: 1.9rem; }
  .main-grid { grid-template-columns: 1fr; }
  .span2     { grid-column: span 1; }
  .rp-header { flex-direction: column; align-items: flex-start; gap: .5rem; }
  .rp-timestamp { align-self: flex-start; }
}
@media(max-width: 640px) {
  .kpi-row   { grid-template-columns: repeat(2, 1fr); gap: .65rem; }
  .main-grid { grid-template-columns: 1fr; }
  .span2     { grid-column: span 1; }
  .rates-row { grid-template-columns: 1fr; }
  .rates-row .rate-wrap:first-child { border-right: none; border-bottom: 1px solid var(--border); }
  /* Adoptions table: card rows */
  .adoptions-table thead { display: none; }
  .adoptions-table tbody tr {
    display: flex;
    flex-wrap: wrap;
    gap: .3rem .5rem;
    padding: .85rem 1rem;
    border-bottom: 1px solid var(--border);
    align-items: center;
  }
  .adoptions-table tbody tr td { padding: 0; font-size: .82rem; border: none; vertical-align: middle; }
  .adoptions-table tbody tr td:first-child { display: none; }
}
@media(max-width: 480px) {
  .kpi-row   { gap: .5rem; }
  .kpi-card  { padding: .85rem 1rem; }
  .kpi-num   { font-size: 1.6rem; }
  .rp-body   { padding: .85rem .85rem 2rem; gap: 1rem; }
  .bar-chart { height: 80px; }
  .bar-labels span { font-size: .52rem; }
}
@media(max-width: 360px) {
  .kpi-row { grid-template-columns: 1fr; }
}
</style>

<div class="main-content rp-wrap">
<?php include __DIR__ . '/../../includes/topbar.php'; ?>
<div class="rp-body main-body">

  <!-- Page Header -->
  <div class="rp-header">
    <div>
      <h1>Reports &amp; Analytics</h1>
    </div>
  </div>

  <!-- KPI Row -->
  <div class="kpi-row">
    <div class="kpi-card kpi-grn">
      <div class="kpi-top">
        <div class="kpi-icon"><i class="fa-solid fa-paw"></i></div>
        <span class="kpi-badge">Pets</span>
      </div>
      <div class="kpi-num"><?= (int)$petStats['total'] ?></div>
      <div class="kpi-label">Total Pets Registered</div>
    </div>
    <div class="kpi-card kpi-amb">
      <div class="kpi-top">
        <div class="kpi-icon"><i class="fa-solid fa-users"></i></div>
        <span class="kpi-badge">Users</span>
      </div>
      <div class="kpi-num"><?= (int)$userStats['total'] ?></div>
      <div class="kpi-label">Registered Users</div>
    </div>
    <div class="kpi-card kpi-blu">
      <div class="kpi-top">
        <div class="kpi-icon"><i class="fa-solid fa-file-lines"></i></div>
        <span class="kpi-badge">Apps</span>
      </div>
      <div class="kpi-num"><?= (int)$appStats['total'] ?></div>
      <div class="kpi-label">Total Applications</div>
    </div>
    <div class="kpi-card kpi-pur">
      <div class="kpi-top">
        <div class="kpi-icon"><i class="fa-solid fa-heart"></i></div>
        <span class="kpi-badge">Adopted</span>
      </div>
      <div class="kpi-num"><?= (int)$appStats['approved'] ?></div>
      <div class="kpi-label">Successful Adoptions</div>
    </div>
  </div>

  <!-- Main Grid -->
  <div class="main-grid">

    <!-- Pets Breakdown -->
    <div class="sec-card">
      <div class="sec-head">
        <div class="sec-title"><i class="fa-solid fa-paw"></i> Pet Inventory</div>
      </div>
      <div class="stat-list">
        <?php
        $petRows = [
          ['Available',    $petStats['available'],    '#2d5f44', $petStats['total']],
          ['Adopted',      $petStats['adopted'],      '#7c3aed', $petStats['total']],
          ['Pending',      $petStats['pending'],      '#d97706', $petStats['total']],
          ['In Treatment', $petStats['in_treatment'], '#c0392b', $petStats['total']],
        ];
        foreach ($petRows as [$lbl, $val, $color, $total]):
          $pct = $total > 0 ? round($val / $total * 100) : 0;
        ?>
        <div class="stat-row-item">
          <span class="lbl"><span class="dot" style="background:<?= $color ?>"></span><?= $lbl ?></span>
          <div class="bar-wrap"><div class="bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>"></div></div>
          <span class="val"><?= (int)$val ?></span>
        </div>
        <?php endforeach; ?>
        <div style="height:.5rem"></div>
        <?php
        $typeRows = [
          ['Dogs',    $petStats['dogs'],    'fa-dog',    '#2d5f44'],
          ['Cats',    $petStats['cats'],    'fa-cat',    '#3b6fd4'],
        ];
        foreach ($typeRows as [$lbl, $val, $icon, $color]):
        ?>
        <div class="stat-row-item">
          <span class="lbl"><i class="fa-solid <?= $icon ?>" style="color:<?= $color ?>;width:14px;text-align:center"></i><?= $lbl ?></span>
          <span class="val"><?= (int)$val ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Applications Breakdown -->
    <div class="sec-card">
      <div class="sec-head">
        <div class="sec-title"><i class="fa-solid fa-file-lines"></i> Applications</div>
      </div>
      <div class="stat-list">
        <?php
        $appRows = [
          ['Pending',      $appStats['pending'],      '#d97706', $appStats['total']],
          ['Under Review', $appStats['under_review'], '#3b6fd4', $appStats['total']],
          ['Approved',     $appStats['approved'],     '#2d5f44', $appStats['total']],
          ['Rejected',     $appStats['rejected'],     '#c0392b', $appStats['total']],
        ];
        foreach ($appRows as [$lbl, $val, $color, $total]):
          $pct = $total > 0 ? round($val / $total * 100) : 0;
        ?>
        <div class="stat-row-item">
          <span class="lbl"><span class="dot" style="background:<?= $color ?>"></span><?= $lbl ?></span>
          <div class="bar-wrap"><div class="bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>"></div></div>
          <span class="val"><?= (int)$val ?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Rates -->
      <div style="border-top:1px solid var(--border)">
        <div class="rates-row">
          <div class="rate-wrap">
            <div class="ring-container">
              <svg width="110" height="110" viewBox="0 0 110 110">
                <circle class="ring-bg" cx="55" cy="55" r="45"/>
                <circle class="ring-fill ring-grn" cx="55" cy="55" r="45"
                  stroke-dasharray="<?= $circ ?>"
                  stroke-dashoffset="<?= $offset1 ?>"/>
              </svg>
              <div class="ring-label">
                <span class="ring-pct"><?= $adoptionRate ?>%</span>
                <span class="ring-sub">Rate</span>
              </div>
            </div>
            <div class="rate-title">Adoption Rate</div>
            <div class="rate-desc">of all registered pets</div>
          </div>
          <div class="rate-wrap">
            <div class="ring-container">
              <svg width="110" height="110" viewBox="0 0 110 110">
                <circle class="ring-bg" cx="55" cy="55" r="45"/>
                <circle class="ring-fill ring-amb" cx="55" cy="55" r="45"
                  stroke-dasharray="<?= $circ ?>"
                  stroke-dashoffset="<?= $offset2 ?>"/>
              </svg>
              <div class="ring-label">
                <span class="ring-pct"><?= $approvalRate ?>%</span>
                <span class="ring-sub">Rate</span>
              </div>
            </div>
            <div class="rate-title">Approval Rate</div>
            <div class="rate-desc">of all applications</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Users + Monthly Chart -->
    <div class="sec-card">
      <div class="sec-head">
        <div class="sec-title"><i class="fa-solid fa-users"></i> Users</div>
      </div>
      <div class="stat-list">
        <?php
        $userRows = [
          ['Total Users',  $userStats['total'],    '#1a2820'],
          ['Adopters',     $userStats['adopters'], '#2d5f44'],
          ['Admins',       $userStats['admins'],   '#3b6fd4'],
          ['Active',       $userStats['active'],   '#7c3aed'],
        ];
        foreach ($userRows as [$lbl, $val, $color]):
        ?>
        <div class="stat-row-item">
          <span class="lbl"><span class="dot" style="background:<?= $color ?>"></span><?= $lbl ?></span>
          <span class="val"><?= (int)$val ?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Monthly bar chart — always shown; empty state if no data -->
      <div style="border-top:1px solid var(--border)">
        <div style="padding:.75rem 1.25rem .25rem;font-size:.72rem;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.05em">
          Adoptions &mdash; Last 6 Months
        </div>
        <?php
          $monthMap = [];
          for ($i = 5; $i >= 0; $i--) {
            $key   = date('Y-m', strtotime("-$i months"));
            $label = date('M Y', strtotime("-$i months"));
            $monthMap[$key] = ['label' => $label, 'count' => 0];
          }
          foreach ($monthlyData as $m) {
            if (isset($monthMap[$m['month_key']])) {
              $monthMap[$m['month_key']]['count'] = (int)$m['count'];
            }
          }
          $counts = array_column($monthMap, 'count');
          $maxVal = max($counts ?: [1]);
          $hasAny = array_sum($counts) > 0;
          $capPct = 75;
        ?>
        <?php if ($hasAny): ?>
        <div class="bar-chart-wrap">
          <div class="bar-chart">
            <?php foreach ($monthMap as $row):
              $rawPct  = $maxVal > 0 ? ($row['count'] / $maxVal) * $capPct : 0;
              $h       = $row['count'] > 0 ? max(round($rawPct), 6) : 3;
              $isEmpty = $row['count'] === 0;
            ?>
            <div class="bar-col">
              <div class="bar-val"<?= $isEmpty ? ' style="visibility:hidden"' : '' ?>><?= $row['count'] ?></div>
              <div class="bar-block" style="height:<?= $h ?>%<?= $isEmpty ? ';background:rgba(0,0,0,.06)' : '' ?>"></div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="bar-labels">
            <?php foreach ($monthMap as $row): ?>
            <span><?= htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <?php else: ?>
        <div style="padding:1.5rem;text-align:center;font-size:.78rem;color:var(--text3)">
          <i class="fa-solid fa-chart-bar" style="opacity:.25;font-size:1.4rem;display:block;margin-bottom:.4rem"></i>
          No adoptions recorded in the last 6 months
        </div>
        <?php endif; ?>
      </div>
    </div>

  </div>

  <!-- Recent Activity — all application updates -->
  <div class="sec-card">
    <div class="sec-head">
      <div class="sec-title"><i class="fa-solid fa-clock-rotate-left"></i> Recent Activity</div>
      <span style="font-size:.72rem;color:var(--text3)">Latest 15 application updates</span>
    </div>
    <div style="overflow-x:auto">
      <table class="adoptions-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Adopter</th>
            <th>Pet</th>
            <th>Status</th>
            <th>Last Updated</th>
            <th>Submitted</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($recentActivity): ?>
        <?php
          $statusCfg = [
            'Pending'      => ['color' => '#d97706', 'bg' => '#fff8ed', 'icon' => 'fa-clock'],
            'Under Review' => ['color' => '#3b6fd4', 'bg' => '#eef3fc', 'icon' => 'fa-magnifying-glass'],
            'Approved'     => ['color' => '#2d5f44', 'bg' => '#eef7f2', 'icon' => 'fa-circle-check'],
            'Rejected'     => ['color' => '#c0392b', 'bg' => '#feeeee', 'icon' => 'fa-circle-xmark'],
          ];
        ?>
        <?php foreach ($recentActivity as $i => $a):
          $cfg = $statusCfg[$a['status']] ?? ['color' => '#9aab9f', 'bg' => '#f5f3ef', 'icon' => 'fa-circle'];
        ?>
        <tr>
          <td style="color:var(--text3);font-size:.75rem;width:2rem"><?= $i + 1 ?></td>
          <td><span class="adopter-name"><?= e($a['full_name']) ?></span></td>
          <td>
            <span class="pet-pill">
              <i class="fa-solid fa-paw" style="font-size:.65rem"></i>
              <?= e($a['pet_name']) ?>
            </span>
          </td>
          <td>
            <span class="status-badge" style="background:<?= $cfg['bg'] ?>;color:<?= $cfg['color'] ?>">
              <i class="fa-solid <?= $cfg['icon'] ?>" style="font-size:.65rem"></i>
              <?= e($a['status']) ?>
            </span>
          </td>
          <td><span class="date-chip"><?= !empty($a['updated_at']) ? date('M j, Y', strtotime($a['updated_at'])) : '—' ?></span></td>
          <td><span class="date-chip"><?= !empty($a['created_at']) ? date('M j, Y', strtotime($a['created_at'])) : '—' ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php else: ?>
        <tr>
          <td colspan="6" style="padding:2.5rem;text-align:center;color:var(--text3)">
            <i class="fa-solid fa-inbox" style="font-size:1.5rem;display:block;margin-bottom:.5rem;opacity:.3"></i>
            No applications yet.
          </td>
        </tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
</div>
<?php include __DIR__ . '/../../includes/dash-foot.php'; ?>