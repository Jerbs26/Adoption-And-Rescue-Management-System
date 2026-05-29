<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';
session_start_once();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { redirect(BASE_URL . '/pages/pets.php'); }

$pet = db()->prepare("SELECT * FROM pets WHERE id = ?");
$pet->execute([$id]);
$pet = $pet->fetch();

$user      = current_user();
$activePage = 'find-pet';

if (!$pet) {
    $pageTitle = 'Pet Not Found';
    if ($user && $user['role'] === 'adopter') {
        include __DIR__ . '/includes/dash-head.php';
        include __DIR__ . '/includes/sidebar.php';
        echo '<div class="main-content"><div class="main-body" style="text-align:center;padding:4rem 1rem">
            <i class="fa-solid fa-paw" style="font-size:3rem;color:#9aab9f;margin-bottom:1rem;display:block"></i>
            <h1>We could not find that pet</h1>
            <p class="muted">The link may be old or the pet may have already been adopted.</p>
            <a class="btn btn-primary" href="' . BASE_URL . '/modules/adopter/find-pet.php"><i class="fa-solid fa-arrow-left"></i> Back to all pets</a>
        </div></div>';
        include __DIR__ . '/includes/dash-foot.php';
    } else {
        include __DIR__ . '/includes/head.php';
        include __DIR__ . '/includes/header.php';
        echo '<section class="container section center">
            <i class="fa-solid fa-paw" style="font-size:3rem;color:var(--muted-fg);margin-bottom:1rem"></i>
            <h1>We could not find that pet</h1>
            <p class="muted">The link may be old or the pet may have already been adopted.</p>
            <a class="btn btn-primary" href="' . BASE_URL . '/pages/pets.php"><i class="fa-solid fa-arrow-left"></i> Back to all pets</a>
        </section>';
        include __DIR__ . '/includes/footer.php';
    }
    exit;
}

// Fetch extra images
$images = db()->prepare("SELECT * FROM pet_images WHERE pet_id = ? ORDER BY sort_order");
$images->execute([$id]);
$images = $images->fetchAll();

// Fetch medical records
$records = db()->prepare("SELECT * FROM medical_records WHERE pet_id = ? ORDER BY record_date DESC LIMIT 5");
$records->execute([$id]);
$records = $records->fetchAll();

$pageTitle = $pet['name'];
$isAvail   = $pet['status'] === 'Available';

// Use dashboard layout for logged-in adopters, public layout for guests
$isDashboard = $user && $user['role'] === 'adopter';

if ($isDashboard) {
    include __DIR__ . '/includes/dash-head.php';
    include __DIR__ . '/includes/sidebar.php';
} else {
    include __DIR__ . '/includes/head.php';
    include __DIR__ . '/includes/header.php';
}

// Back link destination
$backLink = $isDashboard
    ? BASE_URL . '/modules/adopter/find-pet.php'
    : BASE_URL . '/pages/pets.php';
?>

<?php if ($isDashboard): ?>
<style>
.pd-wrap {
  --warm-bg: #fdf8f3;
  --card-bg: #ffffff;
  --green: #3a7d5a;
  --green-dark: #2d5f44;
  --green-light: #eef7f2;
  --orange: #e8722a;
  --text-dark: #1e2d26;
  --text-mid: #5a6b62;
  --text-light: #9aab9f;
  --border: #e8e0d6;
  --radius: 14px;
  background: var(--warm-bg);
  min-height: 100vh;
}
.pd-back {
  display: inline-flex; align-items: center; gap: .5rem;
  color: var(--text-mid); text-decoration: none; font-size: .85rem;
  font-weight: 600; margin-bottom: 1.5rem;
  transition: color .15s;
}
.pd-back:hover { color: var(--green); }
.pd-grid {
  display: grid; grid-template-columns: 1fr 1fr;
  gap: 2rem; align-items: start;
}
@media(max-width:768px){ .pd-grid { grid-template-columns: 1fr; } }
.pd-main-img {
  width: 100%; border-radius: var(--radius);
  object-fit: cover; max-height: 420px;
}
.pd-thumbs {
  display: flex; gap: .5rem; margin-top: .75rem; flex-wrap: wrap;
}
.pd-thumbs img {
  width: 70px; height: 58px; object-fit: cover;
  border-radius: 8px; cursor: pointer;
  border: 2px solid transparent; transition: border-color .15s;
}
.pd-thumbs img.active { border-color: var(--green); }
.pd-name-row {
  display: flex; align-items: center; gap: .75rem;
  margin-bottom: .25rem; flex-wrap: wrap;
}
.pd-name-row h1 {
  font-size: 2rem; font-weight: 900;
  color: var(--text-dark); margin: 0;
}
.pd-breed {
  color: var(--text-mid); font-size: 1rem; margin-bottom: 1.25rem;
}
.pd-facts {
  display: grid; grid-template-columns: 1fr 1fr;
  gap: .6rem; margin-bottom: 1.25rem;
}
.pd-fact {
  background: #f5f0ea; border-radius: 10px;
  padding: .7rem .9rem;
}
.pd-fact dt {
  font-size: .68rem; font-weight: 800; letter-spacing: .1em;
  text-transform: uppercase; color: var(--text-light);
  margin-bottom: .2rem;
}
.pd-fact dd {
  font-size: .92rem; font-weight: 700; color: var(--text-dark);
}
.pd-checks {
  display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1.5rem;
}
.pd-check {
  display: inline-flex; align-items: center; gap: .35rem;
  font-size: .78rem; font-weight: 600;
  padding: .35rem .8rem; border-radius: 50px;
  background: #e6f4ee; color: #2e7d52;
}
.pd-check.no { background: #fdecea; color: #b91c1c; }
.pd-check i { font-size: .75rem; }
.pd-about h2 {
  font-size: 1.1rem; font-weight: 800;
  color: var(--text-dark); margin-bottom: .5rem;
}
.pd-about p { font-size: .88rem; color: var(--text-mid); line-height: 1.7; }
.pd-actions { display: flex; gap: .75rem; margin-top: 1.75rem; flex-wrap: wrap; }
.pd-btn-adopt {
  background: var(--orange); color: #fff;
  border: none; border-radius: 50px;
  padding: .75rem 1.75rem; font-size: .9rem; font-weight: 700;
  text-decoration: none; cursor: pointer;
  display: inline-flex; align-items: center; gap: .5rem;
  box-shadow: 0 2px 10px rgba(232,114,42,.3);
  transition: opacity .15s, transform .15s;
}
.pd-btn-adopt:hover { opacity: .9; transform: translateY(-1px); }
.pd-btn-adopt:disabled {
  background: #d0ccc7; box-shadow: none; cursor: not-allowed; transform: none;
}
.pd-btn-back {
  background: transparent; color: var(--text-mid);
  border: 1px solid var(--border); border-radius: 50px;
  padding: .75rem 1.5rem; font-size: .9rem; font-weight: 600;
  text-decoration: none; display: inline-flex; align-items: center; gap: .5rem;
  transition: border-color .15s, color .15s;
}
.pd-btn-back:hover { border-color: var(--green); color: var(--green); }
.pd-records { margin-top: 2.5rem; }
.pd-records h2 {
  font-size: 1.1rem; font-weight: 800;
  color: var(--text-dark); margin-bottom: .9rem;
}
.pd-record {
  background: var(--card-bg); border: 1px solid var(--border);
  border-radius: 10px; padding: .9rem 1.1rem;
  margin-bottom: .6rem; display: flex;
  justify-content: space-between; align-items: flex-start; gap: 1rem;
}
.pd-record-type {
  font-size: .7rem; font-weight: 800; letter-spacing: .1em;
  text-transform: uppercase; color: var(--green);
  margin-bottom: .2rem;
}
.pd-record-desc { font-size: .88rem; font-weight: 700; color: var(--text-dark); }
.pd-record-vet { font-size: .78rem; color: var(--text-light); margin-top: .15rem; }
.pd-record-date { font-size: .78rem; color: var(--text-light); text-align: right; flex-shrink: 0; }
</style>

<div class="main-content pd-wrap">
<div class="main-body">

  <a class="pd-back" href="<?= $backLink ?>">
    <i class="fa-solid fa-arrow-left"></i> Back to all pets
  </a>

  <div class="pd-grid">
    <!-- Left: Images -->
    <div>
      <img src="<?= e(pet_image_url($pet['primary_image'])) ?>" alt="<?= e($pet['name']) ?>" class="pd-main-img" id="mainImg">
      <?php if ($images): ?>
      <div class="pd-thumbs">
        <img src="<?= e(pet_image_url($pet['primary_image'])) ?>" alt="Main" class="active" id="thumb-main">
        <?php foreach ($images as $img): ?>
        <img src="<?= e(pet_image_url($img['image_path'])) ?>" alt="<?= e($pet['name']) ?>">
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Right: Details -->
    <div>
      <div class="pd-name-row">
        <h1><?= e($pet['name']) ?></h1>
        <?= status_badge($pet['status']) ?>
      </div>
      <div class="pd-breed"><?= e($pet['breed']) ?></div>

      <dl class="pd-facts">
        <div class="pd-fact"><dt>Type</dt><dd><?= e($pet['type']) ?></dd></div>
        <div class="pd-fact"><dt>Age</dt><dd><?= e($pet['age_label']) ?></dd></div>
        <div class="pd-fact"><dt>Gender</dt><dd><?= e($pet['gender']) ?></dd></div>
        <div class="pd-fact"><dt>Size</dt><dd><?= e($pet['size']) ?></dd></div>
        <?php if ($pet['color']): ?>
        <div class="pd-fact"><dt>Color</dt><dd><?= e($pet['color']) ?></dd></div>
        <?php endif; ?>
        <?php if ($pet['weight_kg']): ?>
        <div class="pd-fact"><dt>Weight</dt><dd><?= e($pet['weight_kg']) ?> kg</dd></div>
        <?php endif; ?>
        <?php if ($pet['rescue_date']): ?>
        <div class="pd-fact"><dt>Rescued</dt><dd><?= date('M j, Y', strtotime($pet['rescue_date'])) ?></dd></div>
        <?php endif; ?>
        <?php if ($pet['rescue_location']): ?>
        <div class="pd-fact"><dt>Found at</dt><dd><?= e($pet['rescue_location']) ?></dd></div>
        <?php endif; ?>
      </dl>

      <div class="pd-checks">
        <span class="pd-check <?= $pet['is_vaccinated'] ? '' : 'no' ?>">
          <i class="fa-solid fa-<?= $pet['is_vaccinated'] ? 'check' : 'xmark' ?>"></i>
          <?= $pet['is_vaccinated'] ? 'Vaccinated' : 'Not vaccinated' ?>
        </span>
        <span class="pd-check <?= $pet['is_neutered'] ? '' : 'no' ?>">
          <i class="fa-solid fa-<?= $pet['is_neutered'] ? 'check' : 'xmark' ?>"></i>
          <?= $pet['is_neutered'] ? 'Neutered/Spayed' : 'Not neutered' ?>
        </span>
        <span class="pd-check <?= $pet['is_microchipped'] ? '' : 'no' ?>">
          <i class="fa-solid fa-<?= $pet['is_microchipped'] ? 'check' : 'xmark' ?>"></i>
          <?= $pet['is_microchipped'] ? 'Microchipped' : 'Not microchipped' ?>
        </span>
        <?php if ($pet['good_with_kids'] !== null): ?>
        <span class="pd-check <?= $pet['good_with_kids'] ? '' : 'no' ?>">
          <i class="fa-solid fa-<?= $pet['good_with_kids'] ? 'check' : 'xmark' ?>"></i>
          <?= $pet['good_with_kids'] ? 'Good with kids' : 'Not for young children' ?>
        </span>
        <?php endif; ?>
        <?php if ($pet['good_with_pets'] !== null): ?>
        <span class="pd-check <?= $pet['good_with_pets'] ? '' : 'no' ?>">
          <i class="fa-solid fa-<?= $pet['good_with_pets'] ? 'check' : 'xmark' ?>"></i>
          <?= $pet['good_with_pets'] ? 'Good with pets' : 'Prefers being only pet' ?>
        </span>
        <?php endif; ?>
      </div>

      <?php if ($pet['description']): ?>
      <div class="pd-about">
        <h2>About <?= e($pet['name']) ?></h2>
        <p><?= nl2br(e($pet['description'])) ?></p>
      </div>
      <?php endif; ?>

      <div class="pd-actions">
        <?php if ($isAvail): ?>
          <a class="pd-btn-adopt" href="<?= BASE_URL ?>/pages/apply.php?pet_id=<?= (int)$pet['id'] ?>">
            <i class="fa-solid fa-heart"></i> Adopt <?= e($pet['name']) ?>
          </a>
        <?php else: ?>
          <button class="pd-btn-adopt" disabled>
            <?= $pet['status'] === 'Adopted' ? 'Already Adopted' : 'Currently Not Available' ?>
          </button>
        <?php endif; ?>
        <a class="pd-btn-back" href="<?= $backLink ?>">
          <i class="fa-solid fa-arrow-left"></i> Back to List
        </a>
      </div>
    </div>
  </div>

  <?php if ($records): ?>
  <div class="pd-records">
    <h2>Medical &amp; Vaccination Records</h2>
    <?php foreach ($records as $rec): ?>
    <div class="pd-record">
      <div>
        <div class="pd-record-type"><?= e($rec['record_type']) ?></div>
        <div class="pd-record-desc"><?= e($rec['description']) ?></div>
        <?php if ($rec['vet_name']): ?>
        <div class="pd-record-vet">Vet: <?= e($rec['vet_name']) ?><?= $rec['clinic_name'] ? ' — ' . e($rec['clinic_name']) : '' ?></div>
        <?php endif; ?>
      </div>
      <div class="pd-record-date">
        <?= date('M j, Y', strtotime($rec['record_date'])) ?>
        <?php if ($rec['next_due_date']): ?>
        <br>Next: <?= date('M j, Y', strtotime($rec['next_due_date'])) ?>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>
</div>

<?php else: ?>

<!-- Public layout (guest / non-adopter) — unchanged -->
<section class="container" style="padding:2.5rem 0 4rem">
  <a class="back-link" href="<?= $backLink ?>"><i class="fa-solid fa-arrow-left"></i> Back to all pets</a>

  <div class="details">
    <div>
      <div class="details__img">
        <img src="<?= e(pet_image_url($pet['primary_image'])) ?>" alt="<?= e($pet['name']) ?>" id="mainImg">
      </div>
      <?php if ($images): ?>
      <div style="display:flex;gap:.5rem;margin-top:.75rem;flex-wrap:wrap">
        <img src="<?= e(pet_image_url($pet['primary_image'])) ?>" alt="Main" class="thumb-img active" style="width:72px;height:60px;object-fit:cover;border-radius:.5rem;cursor:pointer;border:2px solid var(--primary)">
        <?php foreach ($images as $img): ?>
        <img src="<?= e(pet_image_url($img['image_path'])) ?>" alt="<?= e($pet['name']) ?>" class="thumb-img" style="width:72px;height:60px;object-fit:cover;border-radius:.5rem;cursor:pointer;border:2px solid transparent">
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div>
      <div class="tag-row">
        <h1 style="margin:0"><?= e($pet['name']) ?></h1>
        <?= status_badge($pet['status']) ?>
      </div>
      <p class="muted" style="font-size:1.05rem;margin-top:.25rem"><?= e($pet['breed']) ?></p>

      <dl class="facts">
        <div class="fact"><dt>Type</dt><dd><?= e($pet['type']) ?></dd></div>
        <div class="fact"><dt>Age</dt><dd><?= e($pet['age_label']) ?></dd></div>
        <div class="fact"><dt>Gender</dt><dd><?= e($pet['gender']) ?></dd></div>
        <div class="fact"><dt>Size</dt><dd><?= e($pet['size']) ?></dd></div>
        <?php if ($pet['color']): ?>
        <div class="fact"><dt>Color</dt><dd><?= e($pet['color']) ?></dd></div>
        <?php endif; ?>
        <?php if ($pet['weight_kg']): ?>
        <div class="fact"><dt>Weight</dt><dd><?= e($pet['weight_kg']) ?> kg</dd></div>
        <?php endif; ?>
        <?php if ($pet['rescue_date']): ?>
        <div class="fact"><dt>Rescued</dt><dd><?= date('M j, Y', strtotime($pet['rescue_date'])) ?></dd></div>
        <?php endif; ?>
        <?php if ($pet['rescue_location']): ?>
        <div class="fact"><dt>Found at</dt><dd><?= e($pet['rescue_location']) ?></dd></div>
        <?php endif; ?>
      </dl>

      <div class="check-list" style="margin-top:1.25rem">
        <span class="check-item <?= $pet['is_vaccinated'] ? '' : 'no' ?>">
          <i class="fa-solid fa-<?= $pet['is_vaccinated'] ? 'check' : 'xmark' ?>"></i>
          <?= $pet['is_vaccinated'] ? 'Vaccinated' : 'Not vaccinated' ?>
        </span>
        <span class="check-item <?= $pet['is_neutered'] ? '' : 'no' ?>">
          <i class="fa-solid fa-<?= $pet['is_neutered'] ? 'check' : 'xmark' ?>"></i>
          <?= $pet['is_neutered'] ? 'Neutered/Spayed' : 'Not neutered' ?>
        </span>
        <span class="check-item <?= $pet['is_microchipped'] ? '' : 'no' ?>">
          <i class="fa-solid fa-<?= $pet['is_microchipped'] ? 'check' : 'xmark' ?>"></i>
          <?= $pet['is_microchipped'] ? 'Microchipped' : 'Not microchipped' ?>
        </span>
        <?php if ($pet['good_with_kids'] !== null): ?>
        <span class="check-item <?= $pet['good_with_kids'] ? '' : 'no' ?>">
          <i class="fa-solid fa-<?= $pet['good_with_kids'] ? 'check' : 'xmark' ?>"></i>
          <?= $pet['good_with_kids'] ? 'Good with kids' : 'Not for young children' ?>
        </span>
        <?php endif; ?>
        <?php if ($pet['good_with_pets'] !== null): ?>
        <span class="check-item <?= $pet['good_with_pets'] ? '' : 'no' ?>">
          <i class="fa-solid fa-<?= $pet['good_with_pets'] ? 'check' : 'xmark' ?>"></i>
          <?= $pet['good_with_pets'] ? 'Good with pets' : 'Prefers being only pet' ?>
        </span>
        <?php endif; ?>
      </div>

      <?php if ($pet['description']): ?>
      <h2 style="margin-top:1.75rem;font-size:1.2rem">About <?= e($pet['name']) ?></h2>
      <p class="muted"><?= nl2br(e($pet['description'])) ?></p>
      <?php endif; ?>

      <div style="margin-top:1.75rem">
        <?php if ($isAvail): ?>
          <?php if ($user): ?>
            <a class="btn btn-accent" href="<?= BASE_URL ?>/pages/apply.php?pet_id=<?= (int)$pet['id'] ?>">
              <i class="fa-solid fa-heart"></i> Adopt <?= e($pet['name']) ?>
            </a>
          <?php else: ?>
            <a class="btn btn-accent" href="<?= BASE_URL ?>/login.php?redirect=<?= urlencode('pages/apply.php?pet_id=' . (int)$pet['id']) ?>">
              <i class="fa-solid fa-heart"></i> Login to Adopt <?= e($pet['name']) ?>
            </a>
          <?php endif; ?>
        <?php else: ?>
          <button class="btn btn-secondary" disabled>
            <?= $pet['status'] === 'Adopted' ? 'Already Adopted' : 'Currently Not Available' ?>
          </button>
        <?php endif; ?>
        <a class="btn btn-ghost" href="<?= $backLink ?>" style="margin-left:.5rem">Back to List</a>
      </div>
    </div>
  </div>

  <?php if ($records): ?>
  <div style="margin-top:3rem">
    <h2 style="font-size:1.3rem">Medical &amp; Vaccination Records</h2>
    <?php foreach ($records as $rec): ?>
    <div class="record-card">
      <div class="row-between">
        <div>
          <div class="record-type"><?= e($rec['record_type']) ?></div>
          <div class="fw-800" style="margin-top:.2rem"><?= e($rec['description']) ?></div>
          <?php if ($rec['vet_name']): ?>
          <div class="muted small">Vet: <?= e($rec['vet_name']) ?><?= $rec['clinic_name'] ? ' &mdash; ' . e($rec['clinic_name']) : '' ?></div>
          <?php endif; ?>
        </div>
        <div class="muted small" style="text-align:right;flex-shrink:0">
          <?= date('M j, Y', strtotime($rec['record_date'])) ?>
          <?php if ($rec['next_due_date']): ?>
          <br>Next: <?= date('M j, Y', strtotime($rec['next_due_date'])) ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<?php endif; ?>

<script>
document.querySelectorAll('.pd-thumbs img, .thumb-img').forEach(function(img){
  img.addEventListener('click', function(){
    document.getElementById('mainImg').src = img.src;
    document.querySelectorAll('.pd-thumbs img, .thumb-img').forEach(function(t){
      t.style.borderColor = 'transparent';
      t.classList.remove('active');
    });
    img.style.borderColor = 'var(--green, var(--primary))';
    img.classList.add('active');
  });
});
</script>

<?php
if ($isDashboard) {
    include __DIR__ . '/includes/dash-foot.php';
} else {
    include __DIR__ . '/includes/footer.php';
}
?>