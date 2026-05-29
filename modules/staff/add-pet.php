<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
session_start_once();
require_login(BASE_URL . '/login.php');
require_role('staff', 'rescue_org');

$user = current_user();
$activePage = 'add-pet';
$pageTitle = 'Add Pet';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name            = clean($_POST['name'] ?? '');
    $type            = clean($_POST['type'] ?? '');
    $breed           = clean($_POST['breed'] ?? '');
    $age_label       = clean($_POST['age_label'] ?? '');
    $gender          = clean($_POST['gender'] ?? '');
    $size            = clean($_POST['size'] ?? '');
    $color           = clean($_POST['color'] ?? '');
    $weight_kg       = ($_POST['weight_kg'] ?? '') !== '' ? (float)$_POST['weight_kg'] : null;
    $description     = clean($_POST['description'] ?? '');
    $status          = clean($_POST['status'] ?? 'Available');
    $rescue_date     = clean($_POST['rescue_date'] ?? '') ?: null;
    $rescue_location = clean($_POST['rescue_location'] ?? '');
    $is_vaccinated   = isset($_POST['is_vaccinated'])   ? 1 : 0;
    $is_neutered     = isset($_POST['is_neutered'])     ? 1 : 0;
    $is_microchipped = isset($_POST['is_microchipped']) ? 1 : 0;
    $good_with_kids  = isset($_POST['good_with_kids'])  ? 1 : 0;
    $good_with_pets  = isset($_POST['good_with_pets'])  ? 1 : 0;

    if (!$name)  $errors[] = 'Pet name is required.';
    if (!$type)  $errors[] = 'Pet type is required.';
    if (!$breed) $errors[] = 'Breed is required.';

    $primary_image = null;
    if (!empty($_FILES['primary_image']['name'])) {
        $file = $_FILES['primary_image'];
        if ($file['size'] > MAX_FILE_SIZE) {
            $errors[] = 'Image must be under 5MB.';
        } elseif (!in_array($file['type'], ALLOWED_IMAGE_TYPES)) {
            $errors[] = 'Only JPG, PNG, WebP, or GIF allowed.';
        } else {
            $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid('pet_') . '.' . $ext;
            $dest     = UPLOAD_DIR . $filename;
            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $primary_image = $filename;
            } else {
                $errors[] = 'Failed to upload image.';
            }
        }
    }

    if (!$errors) {
        // Always save added_by so rescue_org data is properly scoped
        $added_by = (int)$user['id'];

        db()->prepare("
            INSERT INTO pets
                (name, type, breed, age_label, gender, size, color, weight_kg, description,
                 status, rescue_date, rescue_location, is_vaccinated, is_neutered, is_microchipped,
                 good_with_kids, good_with_pets, primary_image, added_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ")->execute([
            $name, $type, $breed, $age_label, $gender, $size, $color, $weight_kg, $description,
            $status, $rescue_date, $rescue_location, $is_vaccinated, $is_neutered, $is_microchipped,
            $good_with_kids, $good_with_pets, $primary_image, $added_by,
        ]);
        log_activity('add_pet', 'pet', (int)db()->lastInsertId(), "Added pet: $name");
        flash('success', 'Pet added successfully!');
        redirect(BASE_URL . '/modules/staff/pets.php');
    }
}

include __DIR__ . '/../../includes/dash-head.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../../includes/topbar.php'; ?>
<div class="main-body">

<?php if ($errors): ?>
<div class="alert alert-danger">
  <i class="fa-solid fa-circle-exclamation"></i>
  <ul style="margin:.25rem 0 0 1rem;padding:0">
    <?php foreach ($errors as $err): ?>
    <li><?= e($err) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="page-header"><h1>Add New Pet</h1></div>

<div class="card card-body">
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="grid grid-2">
      <div class="field">
        <label>Pet Name <span class="req">*</span></label>
        <input name="name" type="text" placeholder="e.g. Buddy" required>
      </div>
      <div class="field">
        <label>Type <span class="req">*</span></label>
        <select name="type" required>
          <option value="">Select type...</option>
          <?php foreach (['Dog', 'Cat'] as $t): ?>
          <option><?= e($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Breed <span class="req">*</span></label>
        <input name="breed" type="text" placeholder="e.g. Labrador Mix" required>
      </div>
      <div class="field">
        <label>Age</label>
        <input name="age_label" type="text" placeholder="e.g. 2 years, 6 months">
      </div>
      <div class="field">
        <label>Gender</label>
        <select name="gender">
          <option>Male</option>
          <option>Female</option>
        </select>
      </div>
      <div class="field">
        <label>Size</label>
        <select name="size">
          <?php foreach (['Small', 'Medium', 'Large', 'Extra Large'] as $s): ?>
          <option><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Color</label>
        <input name="color" type="text" placeholder="e.g. Brown and White">
      </div>
      <div class="field">
        <label>Weight (kg)</label>
        <input name="weight_kg" type="number" step="0.1" min="0" placeholder="e.g. 5.5">
      </div>
      <div class="field">
        <label>Rescue Date</label>
        <input name="rescue_date" type="date">
      </div>
      <div class="field">
        <label>Rescue Location</label>
        <input name="rescue_location" type="text" placeholder="Where was this pet found?">
      </div>
      <div class="field">
        <label>Status</label>
        <select name="status">
          <?php foreach (['Available', 'Rescued', 'In Treatment', 'Pending'] as $s): ?>
          <option><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Primary Photo</label>
        <input name="primary_image" type="file" accept="image/*">
      </div>
    </div>

    <div class="field">
      <label>Description</label>
      <textarea name="description" rows="4"
        placeholder="Tell us about this pet's personality, story, and needs..."></textarea>
    </div>

    <div class="checkbox-row">
      <label class="checkbox-label">
        <input type="checkbox" name="is_vaccinated"> Vaccinated
      </label>
      <label class="checkbox-label">
        <input type="checkbox" name="is_neutered"> Neutered / Spayed
      </label>
      <label class="checkbox-label">
        <input type="checkbox" name="is_microchipped"> Microchipped
      </label>
      <label class="checkbox-label">
        <input type="checkbox" name="good_with_kids"> Good with Kids
      </label>
      <label class="checkbox-label">
        <input type="checkbox" name="good_with_pets"> Good with Pets
      </label>
    </div>

    <div style="display:flex;gap:.75rem">
      <button class="btn btn-primary" type="submit">
        <i class="fa-solid fa-plus"></i> Add Pet
      </button>
      <a href="<?= BASE_URL ?>/modules/staff/pets.php" class="btn btn-ghost">Cancel</a>
    </div>
  </form>
</div>

</div>
</div>

<style>
.checkbox-row { display:flex;gap:1.5rem;flex-wrap:wrap;margin-bottom:1.5rem; }
.checkbox-label { display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.88rem; }
</style>

<?php include __DIR__ . '/../../includes/dash-foot.php'; ?>