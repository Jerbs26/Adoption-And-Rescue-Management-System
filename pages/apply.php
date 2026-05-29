<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';
session_start_once();
require_login(BASE_URL . '/pages/login.php');
require_role('adopter');

$user    = current_user();
$pet_id  = (int)($_GET['pet_id'] ?? 0);
$errors  = [];
$success = false;

// Load pet
$pet = null;
if ($pet_id) {
    $stmt = db()->prepare("SELECT * FROM pets WHERE id = ? AND status = 'Available'");
    $stmt->execute([$pet_id]);
    $pet = $stmt->fetch();
}

// Check existing application
if ($pet) {
    $existing = db()->prepare("SELECT id FROM adoption_applications WHERE pet_id = ? AND adopter_id = ? AND status NOT IN ('Rejected','Withdrawn')");
    $existing->execute([$pet_id, $user['id']]);
    $alreadyApplied = (bool)$existing->fetch();
} else {
    $alreadyApplied = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $applyPetId = (int)($_POST['pet_id'] ?? 0);
    $homeType   = clean($_POST['home_type'] ?? '');
    $hasPets    = isset($_POST['has_other_pets']) ? 1 : 0;
    $hasKids    = isset($_POST['has_children']) ? 1 : 0;
    $experience = clean($_POST['experience'] ?? '');
    $reason     = clean($_POST['reason'] ?? '');

    if (!$applyPetId) $errors[] = 'Please select a pet.';
    if (!$homeType)   $errors[] = 'Please select your home type.';
    if (!$experience) $errors[] = 'Please select your experience level.';
    if (strlen($reason) < 20) $errors[] = 'Please provide a more detailed reason (at least 20 characters).';

    if (!$errors) {
        $checkPet = db()->prepare("SELECT status FROM pets WHERE id = ?");
        $checkPet->execute([$applyPetId]);
        $checkPet = $checkPet->fetch();
        if (!$checkPet || $checkPet['status'] !== 'Available') {
            $errors[] = 'Sorry, this pet is no longer available.';
        } else {
            $ins = db()->prepare("INSERT INTO adoption_applications (pet_id, adopter_id, home_type, has_other_pets, has_children, experience, reason) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$applyPetId, $user['id'], $homeType, $hasPets, $hasKids, $experience, $reason]);
            $appId = (int)db()->lastInsertId();

            db()->prepare("UPDATE pets SET status = 'Pending' WHERE id = ?")->execute([$applyPetId]);

            $adminUsers = db()->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1")->fetchAll();
            foreach ($adminUsers as $au) {
                $notifStmt = db()->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
                $notifStmt->execute([$au['id'], 'New Adoption Application', $user['full_name'] . ' applied for pet #' . $applyPetId, BASE_URL . '/modules/admin/applications.php']);
            }

            log_activity('submit_application', 'application', $appId, "Applied for pet #$applyPetId");
            flash('success', 'Your adoption application has been submitted! We will review it and contact you within 2 business days.');
            redirect(BASE_URL . '/modules/adopter/dashboard.php');
        }
    }
}

$availPets = db()->query("SELECT id, name, breed FROM pets WHERE status = 'Available' ORDER BY name")->fetchAll();

$pageTitle = 'Adoption Application';
include __DIR__ . '/../includes/head.php';
?>

<section class="section-warm">
  <div class="container" style="padding:1.25rem 0 1rem">
    <h1 style="font-size:1.5rem;margin-bottom:.3rem">Adoption Application</h1>
    <p class="muted" style="max-width:34rem;font-size:.85rem;margin:0">
      <?= $pet ? 'You are applying to adopt <strong>' . e($pet['name']) . '</strong>. We will be in touch within 2 business days.' : 'Tell us about yourself and the pet you have in mind.' ?>
    </p>
  </div>
</section>

<section class="container" style="padding:2.5rem 0 4rem">

<?php if ($alreadyApplied): ?>
  <div class="form-card center">
    <i class="fa-solid fa-circle-info" style="font-size:2.5rem;color:var(--info);margin-bottom:1rem"></i>
    <h2>Already Applied</h2>
    <p class="muted">You already have an active application for <?= e($pet['name']) ?>. Track it in your dashboard.</p>
    <a class="btn btn-primary" href="<?= BASE_URL ?>/modules/adopter/dashboard.php">View My Applications</a>
  </div>
<?php else: ?>
  <?php if ($errors): ?>
  <div class="alert alert-danger" style="max-width:640px;margin:0 auto 1.5rem">
    <i class="fa-solid fa-circle-exclamation"></i>
    <ul style="margin:0;padding-left:1rem"><?php foreach($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
  </div>
  <?php endif; ?>

  <form class="form-card" method="post">
    <?= csrf_field() ?>
    <h3 style="margin-bottom:1.25rem">Application Form</h3>

    <div class="field">
      <label for="pet_id">Pet you are interested in <span class="req">*</span></label>
      <select id="pet_id" name="pet_id" required>
        <option value="">-- Select a pet --</option>
        <?php foreach ($availPets as $p): ?>
        <option value="<?= $p['id'] ?>" <?= ($pet && $pet['id'] == $p['id']) ? 'selected' : '' ?>>
          <?= e($p['name']) ?> (<?= e($p['breed']) ?>)
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="home_type">Type of home <span class="req">*</span></label>
      <select id="home_type" name="home_type" required>
        <option value="">-- Select --</option>
        <?php foreach (['House with yard','Apartment','Condo','Farm','Other'] as $ht): ?>
        <option><?= $ht ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="experience">Pet ownership experience <span class="req">*</span></label>
      <select id="experience" name="experience" required>
        <option value="">-- Select --</option>
        <option value="None">None — first time</option>
        <option value="Some">Some — had pets before</option>
        <option value="Experienced">Experienced — long-time owner</option>
      </select>
    </div>

    <div class="checkbox-field">
      <input type="checkbox" id="has_other_pets" name="has_other_pets" value="1">
      <label for="has_other_pets">I currently have other pets at home</label>
    </div>
    <div class="checkbox-field">
      <input type="checkbox" id="has_children" name="has_children" value="1">
      <label for="has_children">I have children (under 12) at home</label>
    </div>

    <div class="field">
      <label for="reason">Why would you be a great owner? <span class="req">*</span></label>
      <textarea id="reason" name="reason" rows="6" maxlength="2000" placeholder="Tell us about your lifestyle, daily routine, home environment, and why this pet is the right fit for you..."></textarea>
      <div class="field-hint">Minimum 20 characters. The more detail, the better!</div>
    </div>

    <button class="btn btn-primary btn-block" type="submit">
      <i class="fa-solid fa-paper-plane"></i> Submit Application
    </button>
  </form>
<?php endif; ?>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>