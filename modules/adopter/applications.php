<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
session_start_once();
require_login(BASE_URL . '/login.php');
require_role('adopter');

$user       = current_user();
$activePage = 'applications';
$pageTitle  = 'My Applications';

// ID Verification Gate 
$profileStmt = db()->prepare("SELECT id_status FROM adopter_profiles WHERE user_id = ?");
$profileStmt->execute([$user['id']]);
$profileRow  = $profileStmt->fetch();
$idStatus    = $profileRow['id_status'] ?? 'none';
$idVerified  = ($idStatus === 'verified');

// Fetch applications 
$appsStmt = db()->prepare("
    SELECT a.*, p.name AS pet_name, p.breed, p.type, p.primary_image, p.status AS pet_status
    FROM adoption_applications a
    JOIN pets p ON p.id = a.pet_id
    WHERE a.adopter_id = ?
    ORDER BY a.created_at DESC
");
$appsStmt->execute([$user['id']]);
$apps = $appsStmt->fetchAll();

include __DIR__ . '/../../includes/dash-head.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
<?php include __DIR__ . '/../../includes/topbar.php'; ?>
<div class="main-body">

  <?php $flash = get_flash('success'); if ($flash): ?>
  <div class="alert alert-success" data-auto-dismiss><i class="fa-solid fa-check"></i><?= e($flash) ?></div>
  <?php endif; ?>
  <?php $ferr = get_flash('error'); if ($ferr): ?>
  <div class="alert alert-danger" data-auto-dismiss><i class="fa-solid fa-circle-exclamation"></i><?= e($ferr) ?></div>
  <?php endif; ?>

  <div class="page-header row-between flex-wrap" style="gap:1rem">
    <div>
      <h1 style="font-size:1.5rem;font-weight:800;margin:0 0 .25rem">My Applications</h1>
      <p class="muted" style="margin:0;font-size:.875rem">Track all your adoption applications here.</p>
    </div>
    <?php if ($idVerified): ?>
    <a href="<?= BASE_URL ?>/modules/adopter/find-pet.php" class="btn btn-primary">
      <i class="fa-solid fa-plus"></i> Apply for a Pet
    </a>
    <?php else: ?>
    <a href="<?= BASE_URL ?>/modules/adopter/profile.php" class="btn btn-secondary">
      <i class="fa-solid fa-id-card"></i>
      <?= $idStatus === 'none' ? 'Upload Your ID' : 'View ID Status' ?>
    </a>
    <?php endif; ?>
  </div>

  <!-- ID Gate Banner -->
  <?php if (!$idVerified):
    $bannerCls  = 'is-' . $idStatus;
    $bannerIcon = match($idStatus) {
      'pending'  => 'fa-clock',
      'rejected' => 'fa-triangle-exclamation',
      default    => 'fa-id-card',
    };
    $bannerTitle = match($idStatus) {
      'pending'  => 'ID Under Review — Applications Locked',
      'rejected' => 'ID Rejected — Please Re-upload',
      default    => 'ID Verification Required',
    };
    $bannerText = match($idStatus) {
      'pending'  => 'Your ID is being reviewed. Once verified by an admin, you will be able to submit new adoption applications.',
      'rejected' => 'Your ID was rejected. Upload a new valid ID on your profile page to re-submit for verification.',
      default    => 'You must upload and have a valid government-issued ID verified before you can apply to adopt a pet.',
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

  <!-- Applications table -->
  <?php if ($apps): ?>
  <div class="card">
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Pet</th>
            <th>Status</th>
            <th>Submitted</th>
            <th>Reviewed On</th>
            <th>Notes</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($apps as $app): ?>
        <tr>
          <td>
            <div class="cell-flex">
              <img src="<?= e(pet_image_url($app['primary_image'])) ?>" class="avatar" alt="<?= e($app['pet_name']) ?>">
              <div>
                <div class="fw-800">
                  <a href="<?= BASE_URL ?>/pages/pet-details.php?id=<?= (int)$app['pet_id'] ?>"
                      style="color:var(--primary)"><?= e($app['pet_name']) ?></a>
                </div>
                <div class="muted xs"><?= e($app['breed']) ?> &middot; <?= e($app['type']) ?></div>
              </div>
            </div>
          </td>
          <td><?= status_badge($app['status']) ?></td>
          <td class="muted small"><?= date('M j, Y', strtotime($app['created_at'])) ?></td>
          <td class="muted small">
            <?= $app['reviewed_at'] ? date('M j, Y', strtotime($app['reviewed_at'])) : '&mdash;' ?>
          </td>
          <td class="muted small" style="max-width:200px">
            <?= $app['reviewer_notes']
                  ? e(mb_strimwidth($app['reviewer_notes'], 0, 60, '…'))
                  : '&mdash;' ?>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php else: ?>
  <div class="empty-state">
    <i class="fa-solid fa-file-lines"></i>
    <h3>No applications yet</h3>
    <p class="muted">
      <?= $idVerified
          ? 'Start by browsing available pets and submitting an adoption request.'
          : 'Get your ID verified first, then you can apply to adopt.' ?>
    </p>
    <?php if ($idVerified): ?>
    <a href="<?= BASE_URL ?>/modules/adopter/find-pet.php" class="btn btn-primary" style="margin-top:1rem">
      Browse Pets
    </a>
    <?php else: ?>
    <a href="<?= BASE_URL ?>/modules/adopter/profile.php" class="btn btn-secondary" style="margin-top:1rem">
      <i class="fa-solid fa-id-card"></i> Upload Your ID
    </a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>
</div>
<?php include __DIR__ . '/../../includes/dash-foot.php'; ?>