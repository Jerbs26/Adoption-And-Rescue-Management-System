<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
session_start_once();
require_login(BASE_URL . '/login.php');
require_role('staff', 'rescue_org');

$user        = current_user();
$activePage  = 'pets';
$pageTitle   = 'Edit Pet';
$isRescueOrg = $user['role'] === 'rescue_org';

// Validate pet ID
$petId = (int)($_GET['id'] ?? 0);
if (!$petId) {
    flash('error', 'Invalid pet ID.');
    redirect(BASE_URL . '/modules/staff/pets.php');
}

// Fetch existing record — rescue_org can only edit their own pets
$stmt = db()->prepare("SELECT * FROM pets WHERE id = ?");
$stmt->execute([$petId]);
$pet = $stmt->fetch();

if (!$pet) {
    flash('error', 'Pet not found.');
    redirect(BASE_URL . '/modules/staff/pets.php');
}

// rescue_org cannot edit pets that don't belong to them
if ($isRescueOrg && (int)($pet['added_by'] ?? 0) !== (int)$user['id']) {
    flash('error', 'You can only edit your own pets.');
    redirect(BASE_URL . '/modules/staff/pets.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // Text / enum fields
    $name            = clean($_POST['name']             ?? '');
    $type            = clean($_POST['type']             ?? '');
    $breed           = clean($_POST['breed']            ?? '');
    $age_label       = clean($_POST['age_label']        ?? '');

    $gender          = clean($_POST['gender']           ?? '');
    $size            = clean($_POST['size']             ?? '');
    $color           = clean($_POST['color']            ?? '');
    $weight_kg       = (($_POST['weight_kg'] ?? '') !== '') ? (float)$_POST['weight_kg'] : null;
    $description     = clean($_POST['description']      ?? '');
    $status          = clean($_POST['status']           ?? '');
    $rescue_date     = clean($_POST['rescue_date']      ?? '');
    $rescue_location = clean($_POST['rescue_location']  ?? '');

    // Checkboxes 
    $is_vaccinated   = isset($_POST['is_vaccinated'])   ? 1 : 0;
    $is_neutered     = isset($_POST['is_neutered'])     ? 1 : 0;
    $is_microchipped = isset($_POST['is_microchipped']) ? 1 : 0;
    $good_with_kids  = isset($_POST['good_with_kids'])  ? 1 : 0;
    $good_with_pets  = isset($_POST['good_with_pets'])  ? 1 : 0;

    // Validation
    if ($name === '')      $errors[] = 'Name is required.';
    if ($type === '')      $errors[] = 'Type is required.';
    if ($breed === '')     $errors[] = 'Breed is required.';
    if ($age_label === '') $age_label = $pet['age_label']; // keep original if not submitted
    if (!in_array($gender, ['Male', 'Female', 'Unknown'])) $errors[] = 'Invalid gender.';
    if (!in_array($size, ['Small', 'Medium', 'Large', 'Extra Large'])) $errors[] = 'Invalid size.';
    if (!in_array($status, ['Available', 'Pending', 'Adopted', 'Rescued', 'In Treatment'])) $errors[] = 'Invalid status.';
    if ($rescue_date !== '' && !strtotime($rescue_date)) $errors[] = 'Invalid rescue date.';
    if ($weight_kg !== null && $weight_kg < 0) $errors[] = 'Weight cannot be negative.';

    // Photo upload (optional — keep existing if none uploaded)
    $primaryImage = $pet['primary_image'];
    if (!empty($_FILES['primary_image']['name'])) {
        $file     = $_FILES['primary_image'];
        $allowed  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $maxBytes = 5 * 1024 * 1024;

        if (!in_array($file['type'], $allowed)) {
            $errors[] = 'Photo must be JPEG, PNG, WebP, or GIF.';
        } elseif ($file['size'] > $maxBytes) {
            $errors[] = 'Photo must be smaller than 5 MB.';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Photo upload failed. Please try again.';
        } else {
            $ext       = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename  = 'pet_' . uniqid() . '.' . $ext;
            $uploadDir = __DIR__ . '/../../uploads/pets/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                $primaryImage = $filename;
            } else {
                $errors[] = 'Could not save the uploaded photo.';
            }
        }
    }

    if (empty($errors)) {
        $sql = "UPDATE pets SET
                    name=?, type=?, breed=?, age_label=?,
                    gender=?, size=?, color=?, weight_kg=?, description=?,
                    status=?, is_vaccinated=?, is_neutered=?, is_microchipped=?,
                    good_with_kids=?, good_with_pets=?,
                    rescue_date=?, rescue_location=?,
                    primary_image=?
                WHERE id=?";
        db()->prepare($sql)->execute([
            $name, $type, $breed, $age_label,
            $gender, $size, $color, $weight_kg, $description,
            $status, $is_vaccinated, $is_neutered, $is_microchipped,
            $good_with_kids, $good_with_pets,
            ($rescue_date !== '' ? $rescue_date : null), $rescue_location,
            $primaryImage, $petId,
        ]);
        log_activity('edit_pet', 'pet', $petId, "Pet updated: $name");
        flash('success', 'Pet updated successfully.');
        redirect(BASE_URL . '/modules/staff/pets.php');
    }

    // Re-populate $pet with submitted values on error
    $pet = array_merge($pet, [
        'name'            => $name,
        'type'            => $type,
        'breed'           => $breed,
        'age_label'       => $age_label,
        'gender'          => $gender,
        'size'            => $size,
        'color'           => $color,
        'weight_kg'       => $weight_kg,
        'description'     => $description,
        'status'          => $status,
        'is_vaccinated'   => $is_vaccinated,
        'is_neutered'     => $is_neutered,
        'is_microchipped' => $is_microchipped,
        'good_with_kids'  => $good_with_kids,
        'good_with_pets'  => $good_with_pets,
        'rescue_date'     => $rescue_date,
        'rescue_location' => $rescue_location,
    ]);
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

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem">
  <div>
    <a href="<?= BASE_URL ?>/modules/staff/pets.php" class="back-link">
      <i class="fa-solid fa-arrow-left"></i> Back to Pets
    </a>
    <h1 style="margin:.25rem 0 0">Edit Pet</h1>
  </div>
</div>

<form method="post" enctype="multipart/form-data" novalidate>
<?= csrf_field() ?>

<!-- PHOTO -->
<div class="card ep-card" style="margin-bottom:1.25rem">
  <div class="ep-section-title">Photo</div>
  <div style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap">
    <div class="photo-preview-wrap">
      <img id="photo-preview"
            src="<?= e(pet_image_url($pet['primary_image'])) ?>"
            alt="Pet photo" class="photo-preview">
      <label class="photo-upload-btn" for="primary_image" title="Change photo">
        <i class="fa-solid fa-camera"></i>
      </label>
    </div>
    <div>
      <input type="file" id="primary_image" name="primary_image"
              accept="image/jpeg,image/png,image/webp,image/gif"
              style="display:none" onchange="previewPhoto(this)">
      <label for="primary_image" class="btn btn-secondary" style="cursor:pointer">
        <i class="fa-solid fa-upload"></i> Choose Photo
      </label>
      <p class="muted" style="font-size:.8rem;margin:.5rem 0 0">
        JPEG, PNG, WebP, or GIF &middot; max 5 MB &middot; Leave blank to keep current photo
      </p>
    </div>
  </div>
</div>

<!-- BASIC INFO -->
<div class="card ep-card" style="margin-bottom:1.25rem">
  <div class="ep-section-title">Basic Information</div>
  <div class="form-grid">

    <div class="form-group">
      <label for="name">Name <span class="req">*</span></label>
      <input type="text" id="name" name="name"
              value="<?= e($pet['name']) ?>" required maxlength="80">
    </div>

    <div class="form-group">
      <label for="type">Type <span class="req">*</span></label>
      <select id="type" name="type" required>
        <?php foreach (['Dog','Cat','Rabbit','Bird','Other'] as $t): ?>
        <option <?= $pet['type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label for="breed">Breed <span class="req">*</span></label>
      <input type="text" id="breed" name="breed"
              value="<?= e($pet['breed']) ?>" required maxlength="100">
    </div>

    <div class="form-group">
      <label for="gender">Gender <span class="req">*</span></label>
      <select id="gender" name="gender" required>
        <?php foreach (['Male','Female','Unknown'] as $g): ?>
        <option <?= $pet['gender'] === $g ? 'selected' : '' ?>><?= e($g) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label for="size">Size <span class="req">*</span></label>
      <select id="size" name="size" required>
        <?php foreach (['Small','Medium','Large','Extra Large'] as $sz): ?>
        <option <?= $pet['size'] === $sz ? 'selected' : '' ?>><?= e($sz) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label for="status">Status <span class="req">*</span></label>
      <select id="status" name="status" required>
        <?php foreach (['Available','Pending','Adopted','Rescued','In Treatment'] as $s): ?>
        <option <?= $pet['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label for="color">Color</label>
      <input type="text" id="color" name="color"
              value="<?= e($pet['color'] ?? '') ?>" maxlength="80">
    </div>

    <div class="form-group">
      <label for="weight_kg">Weight (kg)</label>
      <input type="number" id="weight_kg" name="weight_kg" step="0.01" min="0"
              value="<?= e($pet['weight_kg'] ?? '') ?>">
    </div>

    <div class="form-group">
      <label for="age_label">Age Label <span class="req">*</span></label>
      <input type="text" id="age_label" name="age_label"
              value="<?= e($pet['age_label'] ?? '') ?>" required maxlength="40"
              placeholder="e.g. 2 years, Puppy, Senior">
    </div>


    <div class="form-group">
      <label for="rescue_date">Rescue Date</label>
      <input type="date" id="rescue_date" name="rescue_date"
              value="<?= e($pet['rescue_date'] ?? '') ?>">
    </div>

    <div class="form-group full-width">
      <label for="rescue_location">Rescue Location</label>
      <input type="text" id="rescue_location" name="rescue_location"
              value="<?= e($pet['rescue_location'] ?? '') ?>" maxlength="180">
    </div>

    <div class="form-group full-width">
      <label for="description">Description</label>
      <textarea id="description" name="description" rows="4" maxlength="2000"><?= e($pet['description'] ?? '') ?></textarea>
    </div>

  </div>
</div>

<!-- HEALTH -->
<div class="card ep-card" style="margin-bottom:1.25rem">
  <div class="ep-section-title">Health &amp; Traits</div>
  <div class="checkbox-grid">
    <label class="checkbox-item">
      <input type="checkbox" name="is_vaccinated" <?= $pet['is_vaccinated'] ? 'checked' : '' ?>>
      <span>Vaccinated</span>
    </label>
    <label class="checkbox-item">
      <input type="checkbox" name="is_neutered" <?= $pet['is_neutered'] ? 'checked' : '' ?>>
      <span>Neutered / Spayed</span>
    </label>
    <label class="checkbox-item">
      <input type="checkbox" name="is_microchipped" <?= $pet['is_microchipped'] ? 'checked' : '' ?>>
      <span>Microchipped</span>
    </label>
    <label class="checkbox-item">
      <input type="checkbox" name="good_with_kids" <?= $pet['good_with_kids'] ? 'checked' : '' ?>>
      <span>Good with Kids</span>
    </label>
    <label class="checkbox-item">
      <input type="checkbox" name="good_with_pets" <?= $pet['good_with_pets'] ? 'checked' : '' ?>>
      <span>Good with Pets</span>
    </label>
  </div>
</div>

<!-- ACTIONS -->
<div class="form-actions">
  <a href="<?= BASE_URL ?>/modules/staff/pets.php" class="btn btn-secondary">Cancel</a>
  <button type="submit" class="btn btn-primary">
    <i class="fa-solid fa-floppy-disk"></i> Save Changes
  </button>
</div>

</form>
</div>
</div>

<style>
.back-link {
  display:inline-flex;align-items:center;gap:.4rem;
  font-size:.85rem;color:var(--muted-fg);text-decoration:none;transition:color .15s;
}
.back-link:hover { color:var(--fg); }

.ep-card { padding:1.5rem; }
.ep-section-title {
  font-size:.75rem;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:var(--muted-fg);margin-bottom:1rem;
  padding-bottom:.5rem;border-bottom:1px solid var(--border);
}

/* Photo */
.photo-preview-wrap { position:relative;display:inline-block;flex-shrink:0; }
.photo-preview {
  width:96px;height:88px;object-fit:cover;
  border-radius:.6rem;border:2px solid var(--border);display:block;
}
.photo-upload-btn {
  position:absolute;bottom:-8px;right:-8px;
  width:28px;height:28px;border-radius:50%;
  background:var(--primary,#3a6b4a);color:#fff;
  display:flex;align-items:center;justify-content:center;
  font-size:.75rem;cursor:pointer;border:2px solid #fff;
}

/* Form grid */
.form-grid { display:grid;grid-template-columns:1fr 1fr;gap:1rem 1.25rem; }
.form-group { display:flex;flex-direction:column;gap:.3rem; }
.form-group.full-width { grid-column:1/-1; }
.form-group label {
  font-size:.78rem;font-weight:700;text-transform:uppercase;
  letter-spacing:.04em;color:var(--muted-fg);
}
.form-group input,
.form-group select,
.form-group textarea {
  padding:.5rem .7rem;border:1px solid var(--border);
  border-radius:.4rem;font-size:.9rem;background:var(--card,#fff);
  color:var(--fg);font-family:inherit;
  transition:border-color .15s,box-shadow .15s;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline:none;border-color:var(--primary,#3a6b4a);
  box-shadow:0 0 0 3px rgba(58,107,74,.12);
}
.form-group textarea { resize:vertical;min-height:80px; }
.req { color:#c0392b; }

/* Checkboxes */
.checkbox-grid { display:flex;flex-wrap:wrap;gap:.75rem; }
.checkbox-item {
  display:flex;align-items:center;gap:.5rem;
  padding:.55rem .9rem;border:1px solid var(--border);
  border-radius:.4rem;cursor:pointer;font-size:.88rem;
  transition:background .15s,border-color .15s;user-select:none;
}
.checkbox-item:hover { background:var(--muted); }
.checkbox-item input[type=checkbox] { accent-color:var(--primary,#3a6b4a);width:15px;height:15px; }
.checkbox-item span { display:flex;align-items:center;gap:.4rem; }

/* Actions */
.form-actions {
  display:flex;justify-content:flex-end;gap:.75rem;
  margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid var(--border);
}

/* Alert */
.alert-danger {
  background:#fdf0f0;border:1px solid #f5c6c6;color:#922;
  border-radius:.5rem;padding:.85rem 1rem;margin-bottom:1.25rem;font-size:.88rem;
}

@media(max-width:600px) {
  .form-grid { grid-template-columns:1fr; }
  .checkbox-grid { flex-direction:column; }
}
</style>

<script>
function previewPhoto(input) {
  if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function(e) {
      document.getElementById('photo-preview').src = e.target.result;
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>

<?php include __DIR__ . '/../../includes/dash-foot.php'; ?>