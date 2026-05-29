<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
session_start_once();
require_login(BASE_URL . '/login.php');
require_role('adopter');

$user       = current_user();
$activePage = 'profile';
$pageTitle  = 'My Profile';

// Fetch full user row + adopter profile 
$userStmt = db()->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$user['id']]);
$userRow = $userStmt->fetch();

$profStmt = db()->prepare("SELECT * FROM adopter_profiles WHERE user_id = ?");
$profStmt->execute([$user['id']]);
$profile = $profStmt->fetch();

// Upload dir for valid IDs 
$projectRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');
$idUploadDir = $projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR
              . 'uploads'    . DIRECTORY_SEPARATOR . 'ids'    . DIRECTORY_SEPARATOR;
$idUploadUrl = BASE_URL . '/view-id.php?file=';

if (!is_dir($idUploadDir)) {
    if (!mkdir($idUploadDir, 0755, true)) {
        error_log('PET-ADOPTION: failed to create upload dir: ' . $idUploadDir);
    }
}

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = clean($_POST['action'] ?? '');

    // Update basic profile info 
    if ($action === 'update_profile') {
        $fullName = clean($_POST['full_name'] ?? '');
        $phone    = clean($_POST['phone']     ?? '');
        $address  = clean($_POST['address']   ?? '');

        if (!$fullName) {
            $errors[] = 'Full name is required.';
        } else {
            db()->prepare("UPDATE users SET full_name = ?, phone = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$fullName, $phone, $user['id']]);

            if ($profile) {
                db()->prepare("UPDATE adopter_profiles SET address = ?, phone = ?, updated_at = NOW() WHERE user_id = ?")
                    ->execute([$address, $phone, $user['id']]);
            } else {
                db()->prepare("INSERT INTO adopter_profiles (user_id, address, phone) VALUES (?, ?, ?)")
                    ->execute([$user['id'], $address, $phone]);
            }

            flash('success', 'Profile updated successfully.');
            redirect(BASE_URL . '/modules/adopter/profile.php');
        }
    }

    // Upload / Replace valid ID 
    if ($action === 'upload_id') {
        $idType = clean($_POST['id_type'] ?? '');
        $file   = $_FILES['id_file'] ?? null;

        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        $maxSize      = 5 * 1024 * 1024; // 5 MB

        if (!$idType) {
            $errors[] = 'Please select an ID type.';
        } elseif (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Please choose a file to upload.';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload failed (PHP error code ' . (int)$file['error'] . '). Please try again.';
        } elseif ($file['size'] > $maxSize) {
            $errors[] = 'File exceeds the 5 MB limit.';
        } elseif (!in_array(mime_content_type($file['tmp_name']), $allowedTypes, true)) {
            $errors[] = 'Only JPG, PNG, WEBP, or PDF files are accepted.';
        } elseif (!is_dir($idUploadDir)) {
            $errors[] = 'Upload folder is missing. Please contact the administrator.';
        } else {
            // Delete old file if exists
            if ($profile && !empty($profile['id_document'])) {
                $oldFile = $idUploadDir . basename($profile['id_document']);
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
            }

            $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'id_' . (int)$user['id'] . '_' . time() . '.' . $ext;
            $dest     = $idUploadDir . $filename;

            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                $errors[] = 'Could not save the file. Please ensure the folder is writable.';
                error_log('PET-ADOPTION move_uploaded_file failed: ' . $dest);
            } else {
                if ($profile) {
                    db()->prepare("
                        UPDATE adopter_profiles
                        SET id_document      = ?,
                            id_type          = ?,
                            id_uploaded_at   = NOW(),
                            id_verified      = 0,
                            id_status        = 'pending',
                            id_reject_reason = NULL,
                            updated_at       = NOW()
                        WHERE user_id = ?
                    ")->execute([$filename, $idType, $user['id']]);
                } else {
                    db()->prepare("
                        INSERT INTO adopter_profiles
                            (user_id, id_document, id_type, id_uploaded_at, id_verified, id_status)
                        VALUES (?, ?, ?, NOW(), 0, 'pending')
                    ")->execute([$user['id'], $filename, $idType]);
                }

                // Notify admin
                db()->prepare("
                    INSERT INTO notifications (user_id, title, message, link)
                    SELECT id, 'New ID Verification Request', ?, ?
                    FROM users WHERE role = 'admin' LIMIT 1
                ")->execute([
                    $userRow['full_name'] . ' submitted a valid ID for verification.',
                    BASE_URL . '/modules/admin/users.php?tab=id_verify'
                ]);

                log_activity('id_upload', 'user', $user['id'], 'Uploaded valid ID: ' . $idType);
                flash('success', 'ID uploaded successfully. Pending admin review.');
                redirect(BASE_URL . '/modules/adopter/profile.php');
            }
        }
    }

    // Remove valid ID 
    if ($action === 'remove_id') {
        if ($profile && !empty($profile['id_document'])) {
            if (($profile['id_status'] ?? '') === 'verified') {
                $errors[] = 'Verified IDs cannot be removed.';
            } else {
                $oldFile = $idUploadDir . basename($profile['id_document']);
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
                db()->prepare("
                    UPDATE adopter_profiles
                    SET id_document      = NULL,
                        id_type          = NULL,
                        id_uploaded_at   = NULL,
                        id_verified      = 0,
                        id_status        = 'none',
                        id_reject_reason = NULL,
                        updated_at       = NOW()
                    WHERE user_id = ?
                ")->execute([$user['id']]);

                log_activity('id_remove', 'user', $user['id'], 'Removed valid ID');
                flash('success', 'ID removed.');
                redirect(BASE_URL . '/modules/adopter/profile.php');
            }
        }
    }

    // Re-fetch after failed save
    $stmt2 = db()->prepare("SELECT * FROM users WHERE id = ?");
    $stmt2->execute([$user['id']]);
    $userRow = $stmt2->fetch();

    $stmt3 = db()->prepare("SELECT * FROM adopter_profiles WHERE user_id = ?");
    $stmt3->execute([$user['id']]);
    $profile = $stmt3->fetch();
}

// Helpers 
$idStatus       = $profile['id_status']        ?? 'none';
$idDocument     = $profile['id_document']      ?? null;
$idType         = $profile['id_type']          ?? '';
$idUploadedAt   = $profile['id_uploaded_at']   ?? null;
$idRejectReason = $profile['id_reject_reason'] ?? null;

$statusLabels = [
    'none'     => ['label' => 'Not Uploaded',   'cls' => 'id-status--none',     'icon' => 'fa-circle-xmark'],
    'pending'  => ['label' => 'Pending Review',  'cls' => 'id-status--pending',  'icon' => 'fa-clock'],
    'verified' => ['label' => 'Verified',         'cls' => 'id-status--verified', 'icon' => 'fa-circle-check'],
    'rejected' => ['label' => 'Rejected',         'cls' => 'id-status--rejected', 'icon' => 'fa-circle-xmark'],
];
$statusInfo = $statusLabels[$idStatus] ?? $statusLabels['none'];

$idTypes = [
    'NBI Clearance', 'PhilSys ID (National ID)', "Driver's License",
    'Passport', 'SSS ID', 'GSIS ID', 'PhilHealth ID', "Voter's ID",
    'PRC ID', 'Postal ID', 'Barangay ID', 'Company ID', 'School ID',
];

include __DIR__ . '/../../includes/dash-head.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
<?php include __DIR__ . '/../../includes/topbar.php'; ?>
<div class="main-body">

  <?php $flash = get_flash('success'); if ($flash): ?>
  <div class="alert alert-success" data-auto-dismiss>
    <i class="fa-solid fa-check"></i><?= e($flash) ?>
  </div>
  <?php endif; ?>

  <?php if ($errors): ?>
  <div class="alert alert-danger">
    <i class="fa-solid fa-circle-exclamation"></i><?= e($errors[0]) ?>
  </div>
  <?php endif; ?>

  <div class="page-header">
    <h1 style="font-size:1.5rem;font-weight:800;margin:0 0 .25rem">My Profile</h1>
    <p class="muted" style="margin:0;font-size:.875rem">Manage your personal information and ID verification.</p>
  </div>

  <div class="card id-card">

    <div class="id-card__header">
      <div style="flex:1;min-width:0">
        <div class="id-card__title">
          <i class="fa-solid fa-id-card" style="margin-right:.4rem"></i>Valid ID Verification
        </div>
        <div class="id-card__sub">
          Upload a government-issued ID so the admin can verify your eligibility to adopt.
        </div>
      </div>
      <span class="id-status-pill <?= $statusInfo['cls'] ?>">
        <i class="fa-solid <?= $statusInfo['icon'] ?>"></i>
        <?= $statusInfo['label'] ?>
      </span>
    </div>

    <?php if ($idStatus === 'rejected' && $idRejectReason): ?>
    <div class="reject-box">
      <i class="fa-solid fa-triangle-exclamation"></i>
      <div>
        <strong>Rejected:</strong> <?= e($idRejectReason) ?><br>
        <span style="font-size:.8rem;opacity:.8">
          Please upload a new, clearer ID to re-submit for review.
        </span>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($idDocument): ?>
    <!-- Existing file row -->
    <div class="id-file-row">
      <?php
        $ext   = strtolower(pathinfo($idDocument, PATHINFO_EXTENSION));
        $isImg = in_array($ext, ['jpg','jpeg','png','webp']);
      ?>
      <div class="id-file-row__icon <?= $isImg ? 'is-img' : '' ?>">
        <i class="fa-solid <?= $isImg ? 'fa-image' : 'fa-file-pdf' ?>"></i>
      </div>
      <div class="id-file-row__info">
        <div class="id-file-row__name"><?= e($idType) ?></div>
        <div class="id-file-row__meta">
          <?= $isImg ? strtoupper($ext) : 'PDF' ?>
          <?= $idUploadedAt ? ' &middot; Uploaded ' . date('M j, Y', strtotime($idUploadedAt)) : '' ?>
        </div>
      </div>
      <div class="id-file-row__actions">
        <a href="<?= e($idUploadUrl . urlencode($idDocument)) ?>"
            target="_blank" rel="noopener" class="btn btn-sm btn-secondary">
          <i class="fa-solid fa-eye"></i> View
        </a>
        <?php if ($idStatus !== 'verified'): ?>
        <form method="post" style="margin:0">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="remove_id">
          <button class="btn btn-sm btn-danger" type="submit"
                  data-confirm="Remove this ID? You will need to re-upload to apply for adoption.">
            <i class="fa-solid fa-trash"></i> Remove
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($idStatus !== 'verified'): ?>
    <!-- Upload / Replace form -->
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="upload_id">

      <div class="id-upload-grid">
        <div class="field" style="margin:0">
          <label class="form-label">ID Type <span class="text-danger">*</span></label>
          <select name="id_type" class="form-control" required>
            <option value="" disabled <?= !$idType ? 'selected' : '' ?>>Select ID type…</option>
            <?php foreach ($idTypes as $t): ?>
            <option value="<?= e($t) ?>" <?= $idType === $t ? 'selected' : '' ?>><?= e($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field" style="margin:0">
          <label class="form-label">Upload File <span class="text-danger">*</span></label>
          <input type="file" name="id_file" class="form-control"
                  accept=".jpg,.jpeg,.png,.webp,.pdf" required>
          <div class="id-file-hint">JPG, PNG, WEBP or PDF &middot; Max 5 MB</div>
        </div>
      </div>

      <button class="btn btn-primary" type="submit">
        <i class="fa-solid fa-upload"></i>
        <?= $idDocument ? 'Replace ID' : 'Upload ID' ?>
      </button>
    </form>

    <?php else: ?>
    <div class="alert alert-success" style="margin:0">
      <i class="fa-solid fa-circle-check"></i>
      Your ID has been verified. You are eligible to submit adoption applications.
    </div>
    <?php endif; ?>

  </div><!-- end ID card -->

  <div class="card">
    <div class="card-header">
      <h2 style="font-size:1rem;font-weight:800;margin:0">Personal Information</h2>
    </div>
    <div class="card-body">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_profile">

        <div class="profile-grid" style="margin-bottom:1rem">
          <div class="field" style="margin:0">
            <label class="form-label">Full Name <span class="text-danger">*</span></label>
            <input type="text" name="full_name" class="form-control"
                    value="<?= e($userRow['full_name'] ?? '') ?>" required>
          </div>
          <div class="field" style="margin:0">
            <label class="form-label">Email Address</label>
            <input type="email" class="form-control"
                    value="<?= e($userRow['email'] ?? '') ?>" disabled>
            <div class="id-file-hint">Email cannot be changed here.</div>
          </div>
          <div class="field" style="margin:0">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control"
                    value="<?= e($userRow['phone'] ?? '') ?>" placeholder="+63…">
          </div>
          <div class="field" style="margin:0">
            <label class="form-label">Address</label>
            <input type="text" name="address" class="form-control"
                    value="<?= e($profile['address'] ?? '') ?>" placeholder="City, Province">
          </div>
        </div>

        <button class="btn btn-primary" type="submit">
          <i class="fa-solid fa-floppy-disk"></i> Save Changes
        </button>
      </form>
    </div>
  </div>

</div>
</div>

<?php include __DIR__ . '/../../includes/dash-foot.php'; ?>