<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';
session_start_once();

$pageTitle = 'Adoptable Pets';

// Build query with filters
$search    = clean($_GET['q'] ?? '');
$typeF     = clean($_GET['type'] ?? 'All');
$genderF   = clean($_GET['gender'] ?? 'All');
$sizeF     = clean($_GET['size'] ?? 'All');
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
$total = (int)$totalStmt->fetchColumn();
$pag   = paginate($total, $perPage, $page);
$offset = ($page - 1) * $perPage;

$stmt = db()->prepare("SELECT * FROM pets WHERE $whereSQL ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$pets = $stmt->fetchAll();

include __DIR__ . '/../includes/head.php';
include __DIR__ . '/../includes/header.php';
?>

<section class="section-warm">
  <div class="container" style="padding:3.5rem 0">
    <h1>Meet our adoptables</h1>
    <p class="muted" style="max-width:32rem">Filter by type, search by name or breed, and find the friend you have been waiting for.</p>
  </div>
</section>

<section class="container" style="padding:2rem 0 4rem">
  <form method="get" action="">
    <div class="filters">
      <div class="search-wrap field" style="margin:0">
        <input name="q" type="text" placeholder="Search by name or breed..." value="<?= e($search) ?>" aria-label="Search pets">
      </div>
      <?php
        // Preserve other active filters as hidden inputs when the form submits via the search button
        if ($typeF !== 'All')   echo '<input type="hidden" name="type" value="' . e($typeF) . '">';
        if ($genderF !== 'All') echo '<input type="hidden" name="gender" value="' . e($genderF) . '">';
        if ($sizeF !== 'All')   echo '<input type="hidden" name="size" value="' . e($sizeF) . '">';
        if ($availOnly)         echo '<input type="hidden" name="available" value="1">';
      ?>
      <select name="type" aria-label="Pet type" onchange="this.form.submit()">
        <?php foreach (['All','Dog','Cat','Rabbit','Bird','Other'] as $t): ?>
        <option <?= $typeF === $t ? 'selected' : '' ?>><?= e($t) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="gender" aria-label="Gender" onchange="this.form.submit()">
        <?php foreach (['All','Male','Female'] as $g): ?>
        <option <?= $genderF === $g ? 'selected' : '' ?>><?= e($g) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="size" aria-label="Size" onchange="this.form.submit()">
        <?php foreach (['All','Small','Medium','Large','Extra Large'] as $s): ?>
        <option <?= $sizeF === $s ? 'selected' : '' ?>><?= e($s) ?></option>
        <?php endforeach; ?>
      </select>
      <label class="toggle-filter">
        <input type="checkbox" name="available" <?= $availOnly ? 'checked' : '' ?> onchange="this.form.submit()"> Available only
      </label>
    </div>
  </form>

  <p class="muted small" style="margin:0 0 1.25rem">
    <?= $total ?> <?= $total === 1 ? 'pet' : 'pets' ?> found
    <?= $search ? ' for "<strong>' . e($search) . '</strong>"' : '' ?>
  </p>

  <?php if ($pets): ?>
  <div class="grid grid-3">
    <?php foreach ($pets as $pet): ?>
    <article class="card pet-card">
      <div class="pet-card__img">
        <img src="<?= e(pet_image_url($pet['primary_image'])) ?>" alt="<?= e($pet['name']) ?>" loading="lazy">
      </div>
      <div class="pet-card__body">
        <div class="row-between">
          <h3><?= e($pet['name']) ?></h3>
          <?= status_badge($pet['status']) ?>
        </div>
        <p class="muted small"><?= e($pet['breed']) ?> &middot; <?= e($pet['age_label']) ?> &middot; <?= e($pet['gender']) ?></p>
        <a class="btn btn-primary btn-block" href="<?= BASE_URL ?>/pet-details.php?id=<?= (int)$pet['id'] ?>">Meet <?= e($pet['name']) ?></a>
      </div>
    </article>
    <?php endforeach; ?>
  </div>

  <?php if ($pag['pages'] > 1): ?>
  <div class="pagination">
    <?php if ($page > 1): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" aria-label="Previous">&lsaquo;</a>
    <?php endif; ?>
    <?php for ($i = 1; $i <= $pag['pages']; $i++): ?>
      <?php if ($i === $page): ?>
        <span class="current"><?= $i ?></span>
      <?php else: ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $pag['pages']): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" aria-label="Next">&rsaquo;</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php else: ?>
  <div class="empty-state">
    <i class="fa-solid fa-paw"></i>
    <h3>No matches found</h3>
    <p class="muted">Try adjusting your filters or <a href="pets.php" style="color:var(--primary)">clear all filters</a>.</p>
  </div>
  <?php endif; ?>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>