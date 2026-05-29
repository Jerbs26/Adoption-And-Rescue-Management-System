<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
session_start_once();
require_login(BASE_URL . '/login.php');
require_role('adopter');

$user       = current_user();
$activePage = 'find-pet';
$pageTitle  = 'Find a Pet';

// ID Verification Gate 
$profileStmt = db()->prepare("SELECT id_status FROM adopter_profiles WHERE user_id = ?");
$profileStmt->execute([$user['id']]);
$profileRow  = $profileStmt->fetch();
$idStatus    = $profileRow['id_status'] ?? 'none';
$idVerified  = ($idStatus === 'verified');

// Filters & pagination 
$search    = clean($_GET['q']      ?? '');
$typeF     = clean($_GET['type']   ?? 'All');
$genderF   = clean($_GET['gender'] ?? 'All');
$sizeF     = clean($_GET['size']   ?? 'All');
$availOnly = isset($_GET['available']);
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 9;

$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = '(name LIKE ? OR breed LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($typeF !== 'All') {
    $where[]  = 'type = ?';
    $params[] = $typeF;
}
if ($genderF !== 'All') {
    $where[]  = 'gender = ?';
    $params[] = $genderF;
}
if ($sizeF !== 'All') {
    $where[]  = 'size = ?';
    $params[] = $sizeF;
}
if ($availOnly) {
    $where[] = "status = 'Available'";
}

$whereSQL = implode(' AND ', $where);

$totalStmt = db()->prepare("SELECT COUNT(*) FROM pets WHERE $whereSQL");
$totalStmt->execute($params);
$total  = (int)$totalStmt->fetchColumn();
$pag    = paginate($total, $perPage, $page);
$offset = ($page - 1) * $perPage;

$stmt = db()->prepare("SELECT * FROM pets WHERE $whereSQL ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$pets = $stmt->fetchAll();

include __DIR__ . '/../../includes/dash-head.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<style>
/* Find-pet page local overrides */
.fp-wrap {
  --warm-bg: #fdf8f3; --card-bg: #ffffff; --green: #3a7d5a;
  --green-dark: #2d5f44; --orange: #e8722a;
  --text-dark: #1e2d26; --text-mid: #5a6b62; --text-light: #9aab9f;
  --fp-border: #e8e0d6; --fp-radius: 14px;
  background: var(--warm-bg); min-height: 100vh;
}
.fp-header { margin-bottom: 1.5rem; }
.fp-header h1 { font-size: clamp(1.3rem, 3vw, 1.6rem); font-weight: 800; color: var(--text-dark); margin: 0 0 .25rem; }
.fp-header p  { color: var(--text-mid); font-size: .9rem; margin: 0; }

/* Filters */
.fp-filters {
  background: var(--card-bg); border: 1px solid var(--fp-border);
  border-radius: var(--fp-radius); padding: 1rem 1.25rem;
  display: flex; align-items: center; gap: .75rem; flex-wrap: wrap;
  margin-bottom: 1.25rem;
}
.fp-search-wrap { flex: 1; min-width: 180px; position: relative; }
.fp-search-wrap i { position: absolute; left: .85rem; top: 50%; transform: translateY(-50%); color: var(--text-light); font-size: .85rem; pointer-events: none; }
.fp-search-wrap input { width: 100%; border: 1px solid var(--fp-border); border-radius: 50px; padding: .55rem 1rem .55rem 2.2rem; font-size: .85rem; color: var(--text-dark); background: #faf7f4; outline: none; transition: border-color .15s; }
.fp-search-wrap input:focus { border-color: var(--green); }
.fp-filters select { border: 1px solid var(--fp-border); border-radius: 50px; padding: .5rem 1rem; font-size: .83rem; color: var(--text-mid); background: #faf7f4; outline: none; cursor: pointer; max-width: 140px; }
.fp-filters select:focus { border-color: var(--green); }
.fp-toggle { display: flex; align-items: center; gap: .4rem; font-size: .83rem; color: var(--text-mid); cursor: pointer; white-space: nowrap; }
.fp-toggle input { accent-color: var(--green); cursor: pointer; }
.fp-btn-search { background: var(--green); color: #fff; border: none; border-radius: 50px; padding: .55rem 1.25rem; font-size: .83rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: .4rem; transition: background .15s; white-space: nowrap; }
.fp-btn-search:hover { background: var(--green-dark); }

/* Meta */
.fp-meta { font-size: .82rem; color: var(--text-light); margin-bottom: 1rem; }
.fp-meta strong { color: var(--text-dark); }

/* Pet grid */
.fp-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.1rem;
  margin-bottom: 1.5rem;
}
@media (max-width: 900px) { .fp-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 520px) { .fp-grid { grid-template-columns: 1fr; } }

/* Pet card */
.fp-card { background: var(--card-bg); border: 1px solid var(--fp-border); border-radius: var(--fp-radius); overflow: hidden; display: flex; flex-direction: column; transition: transform .15s, box-shadow .15s; }
.fp-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.08); }
.fp-card__img { width: 100%; height: 200px; overflow: hidden; background: #f0ede8; }
.fp-card__img img { width: 100%; height: 200px; object-fit: cover; object-position: center top; display: block; transition: transform .3s; }
@media (min-width: 640px) { .fp-card__img, .fp-card__img img { height: 220px; } }
.fp-card:hover .fp-card__img img { transform: scale(1.04); }
.fp-card__body { padding: 1rem 1.1rem; flex: 1; display: flex; flex-direction: column; }
.fp-card__top { display: flex; align-items: center; justify-content: space-between; margin-bottom: .25rem; gap: .5rem; }
.fp-card__name { font-weight: 800; font-size: .95rem; color: var(--text-dark); }
.fp-card__meta { font-size: .76rem; color: var(--text-light); margin-bottom: .85rem; flex: 1; }
.fp-card__btn {
  display: block; width: 100%; background: var(--green); color: #fff;
  text-align: center; text-decoration: none; font-weight: 700; font-size: .83rem;
  padding: .6rem; border-radius: 50px; transition: background .15s;
}
.fp-card__btn:hover { background: var(--green-dark); }
.fp-card__btn.is-locked { background: #aaa; pointer-events: none; cursor: not-allowed; }

/* Empty / pagination */
.fp-empty { text-align: center; padding: 4rem 1rem; color: var(--text-light); }
.fp-empty i { font-size: 2.5rem; display: block; margin-bottom: .75rem; opacity: .25; }
.fp-empty h3 { color: var(--text-mid); font-size: 1rem; margin-bottom: .4rem; }
.fp-pagination { display: flex; align-items: center; justify-content: center; gap: .4rem; margin-bottom: 1rem; flex-wrap: wrap; }
.fp-pagination a, .fp-pagination span { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 8px; font-size: .83rem; font-weight: 600; text-decoration: none; border: 1px solid var(--fp-border); color: var(--text-mid); background: var(--card-bg); }
.fp-pagination a:hover { border-color: var(--green); color: var(--green); }
.fp-pagination span.current { background: var(--green); color: #fff; border-color: var(--green); }
</style>

<div class="main-content fp-wrap">
<?php include __DIR__ . '/../../includes/topbar.php'; ?>
<div class="main-body">

  <div class="fp-header">
    <h1 style="font-size:1.5rem;font-weight:800;margin:0 0 .25rem"><i class="fa-solid fa-search" style="color:#3a7d5a;font-size:1.2rem;margin-right:.4rem"></i> Find a Pet</h1>
    <p style="color:var(--text-mid);font-size:.875rem;margin:0">Filter by type, search by name or breed, and find the friend you have been waiting for.</p>
  </div>

  <!-- ID Verification Gate Banner -->
  <?php if (!$idVerified):
    $bannerCls   = 'is-' . $idStatus;
    $bannerIcon  = match($idStatus) {
      'pending'  => 'fa-clock',
      'rejected' => 'fa-triangle-exclamation',
      default    => 'fa-id-card',
    };
    $bannerTitle = match($idStatus) {
      'pending'  => 'ID Under Review',
      'rejected' => 'ID Submission Rejected',
      default    => 'Valid ID Required',
    };
    $bannerText  = match($idStatus) {
      'pending'  => 'Your ID is currently being reviewed. You can browse pets, but you will not be able to submit an adoption application until your ID is verified.',
      'rejected' => 'Your previous ID submission was rejected. Please go to your profile, upload a new valid ID, and wait for admin approval before applying.',
      default    => 'To protect the animals in our care, all adopters must submit a government-issued ID for verification before applying. Please upload your ID on your profile page.',
    };
  ?>
  <div class="id-gate-banner <?= $bannerCls ?>">
    <div class="id-gate-banner__icon"><i class="fa-solid <?= $bannerIcon ?>"></i></div>
    <div class="id-gate-banner__body">
      <div class="id-gate-banner__title"><?= $bannerTitle ?></div>
      <div class="id-gate-banner__text"><?= $bannerText ?></div>
    </div>
    <a href="<?= BASE_URL ?>/modules/adopter/profile.php" class="btn btn-sm btn-secondary"
        style="flex-shrink:0;align-self:center">
      <i class="fa-solid fa-id-card"></i>
      <?= $idStatus === 'none' ? 'Upload ID' : 'View Profile' ?>
    </a>
  </div>
  <?php endif; ?>

  <!-- Filters -->
  <form method="get" action="">
    <div class="fp-filters">
      <div class="fp-search-wrap">
        <i class="fa-solid fa-search"></i>
        <input name="q" type="text" placeholder="Search by name or breed…"
                value="<?= e($search) ?>" aria-label="Search pets">
      </div>
      <select name="type" aria-label="Pet type" onchange="this.form.submit()">
        <?php foreach (['All','Dog','Cat'] as $t): ?>
        <option <?= $typeF === $t ? 'selected' : '' ?>><?= e($t) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="gender" aria-label="Gender" onchange="this.form.submit()">
        <?php foreach (['All','Male','Female'] as $g): ?>
        <option <?= $genderF === $g ? 'selected' : '' ?>><?= e($g) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="size" aria-label="Size" onchange="this.form.submit()">
        <?php foreach (['All','Small','Medium','Large'] as $s): ?>
        <option <?= $sizeF === $s ? 'selected' : '' ?>><?= e($s) ?></option>
        <?php endforeach; ?>
      </select>
      <label class="fp-toggle">
        <input type="checkbox" name="available" <?= $availOnly ? 'checked' : '' ?> onchange="this.form.submit()">
        Available only
      </label>
      <button type="submit" class="fp-btn-search">
        <i class="fa-solid fa-search"></i> Search
      </button>
    </div>
  </form>

  <p class="fp-meta">
    <strong><?= $total ?></strong> <?= $total === 1 ? 'pet' : 'pets' ?> found
    <?php if ($search): ?> for "<strong><?= e($search) ?></strong>"<?php endif; ?>
    <?php if ($typeF !== 'All' || $genderF !== 'All' || $sizeF !== 'All' || $availOnly || $search): ?>
      &mdash; <a href="<?= BASE_URL ?>/modules/adopter/find-pet.php" style="color:#3a7d5a;font-weight:700;text-decoration:none">Clear filters</a>
    <?php endif; ?>
  </p>

  <?php if ($pets): ?>
  <div class="fp-grid">
    <?php foreach ($pets as $pet): ?>
    <div class="fp-card">
      <div class="fp-card__img">
        <img src="<?= e(pet_image_url($pet['primary_image'])) ?>"
              alt="<?= e($pet['name']) ?>" loading="lazy">
      </div>
      <div class="fp-card__body">
        <div class="fp-card__top">
          <div class="fp-card__name"><?= e($pet['name']) ?></div>
          <?= status_badge($pet['status']) ?>
        </div>
        <div class="fp-card__meta">
          <?= e($pet['breed']) ?> &middot; <?= e($pet['age_label']) ?> &middot; <?= e($pet['gender']) ?>
        </div>
        <?php if ($idVerified): ?>
        <a class="fp-card__btn" href="<?= BASE_URL ?>/pet-details.php?id=<?= (int)$pet['id'] ?>">
          <i class="fa-solid fa-paw"></i> Meet <?= e($pet['name']) ?>
        </a>
        <?php else: ?>
        <span class="fp-card__btn is-locked">
          <i class="fa-solid fa-lock"></i> ID Required
        </span>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ($pag['pages'] > 1): ?>
  <div class="fp-pagination">
    <?php if ($page > 1): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">&lsaquo;</a>
    <?php endif; ?>
    <?php for ($i = 1; $i <= $pag['pages']; $i++): ?>
      <?php if ($i === $page): ?>
        <span class="current"><?= $i ?></span>
      <?php else: ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $pag['pages']): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">&rsaquo;</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php else: ?>
  <div class="fp-empty">
    <i class="fa-solid fa-paw"></i>
    <h3>No matches found</h3>
    <p>Try adjusting your filters or <a href="<?= BASE_URL ?>/modules/adopter/find-pet.php" style="color:#3a7d5a">clear all filters</a>.</p>
  </div>
  <?php endif; ?>

</div>
</div>

<?php include __DIR__ . '/../../includes/dash-foot.php'; ?>