<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
session_start_once();
require_login(BASE_URL . '/login.php');
require_role('staff', 'rescue_org');

$user        = current_user();
$activePage  = 'medical';
$pageTitle   = 'Medical Records';
$errors      = [];
$isRescueOrg = $user['role'] === 'rescue_org';

// rescue_org only sees their own pets in the dropdown
if ($isRescueOrg) {
    $petsStmt = db()->prepare("SELECT id, name, type FROM pets WHERE added_by = ? ORDER BY name");
    $petsStmt->execute([$user['id']]);
    $pets = $petsStmt->fetchAll();
} else {
    $pets = db()->query("SELECT id, name, type FROM pets ORDER BY name")->fetchAll();
}

// Build a safe list of pet IDs this user owns (for security checks)
$ownedPetIds = array_column($pets, 'id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $pet_id      = (int)($_POST['pet_id'] ?? 0);
        $record_type = clean($_POST['record_type'] ?? '');
        $description = clean($_POST['description'] ?? '');
        $record_date = clean($_POST['record_date'] ?? '');
        $vet_name    = clean($_POST['vet_name'] ?? '');
        $clinic_name = clean($_POST['clinic_name'] ?? '');
        $next_due    = clean($_POST['next_due_date'] ?? '') ?: null;

        // rescue_org cannot add records for pets they don't own
        if ($isRescueOrg && !in_array($pet_id, $ownedPetIds)) {
            $errors[] = 'You can only add records for your own pets.';
        } elseif (!$pet_id || !$record_type || !$description || !$record_date) {
            $errors[] = 'Pet, type, description, and date are required.';
        } else {
            db()->prepare("
                INSERT INTO medical_records
                    (pet_id, record_type, description, record_date, vet_name, clinic_name, next_due_date)
                VALUES (?,?,?,?,?,?,?)
            ")->execute([$pet_id, $record_type, $description, $record_date, $vet_name, $clinic_name, $next_due]);
            log_activity('add_medical_record', 'pet', $pet_id, "Added $record_type record");
            flash('success', 'Medical record added.');
            redirect(BASE_URL . '/modules/staff/medical.php');
        }

    } elseif ($action === 'delete') {
        $id = (int)($_POST['record_id'] ?? 0);
        if ($id) {
            // rescue_org can only delete records for their own pets
            if ($isRescueOrg) {
                $check = db()->prepare("
                    SELECT mr.id FROM medical_records mr
                    JOIN pets p ON p.id = mr.pet_id
                    WHERE mr.id = ? AND p.added_by = ?
                ");
                $check->execute([$id, $user['id']]);
                if (!$check->fetch()) {
                    redirect(BASE_URL . '/modules/staff/medical.php');
                }
            }
            db()->prepare("DELETE FROM medical_records WHERE id = ?")->execute([$id]);
            flash('success', 'Record deleted.');
        }
        redirect(BASE_URL . '/modules/staff/medical.php');
    }
}

// rescue_org sees only records for their own pets
if ($isRescueOrg) {
    $recStmt = db()->prepare("
        SELECT mr.*, p.name AS pet_name
        FROM medical_records mr
        JOIN pets p ON p.id = mr.pet_id
        WHERE p.added_by = ?
        ORDER BY mr.record_date DESC
    ");
    $recStmt->execute([$user['id']]);
    $records = $recStmt->fetchAll();
} else {
    $records = db()->query("
        SELECT mr.*, p.name AS pet_name
        FROM medical_records mr
        JOIN pets p ON p.id = mr.pet_id
        ORDER BY mr.record_date DESC
    ")->fetchAll();
}

include __DIR__ . '/../../includes/dash-head.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../../includes/topbar.php'; ?>
<div class="main-body">

<?php $flash = get_flash('success'); if ($flash): ?>
<div class="alert alert-success" data-auto-dismiss><i class="fa-solid fa-check"></i><?= e($flash) ?></div>
<?php endif; ?>
<?php if ($errors): ?>
<div class="alert alert-danger">
  <i class="fa-solid fa-circle-exclamation"></i><?= e($errors[0]) ?>
</div>
<?php endif; ?>

<div class="page-header">
  <h1>Medical Records</h1>
</div>

<!-- Add Record form -->
<div class="card card-body" style="margin-bottom:1.5rem">
  <h3 style="font-size:1rem;font-weight:600;margin-bottom:1rem">Add Record</h3>
  <?php if ($isRescueOrg && empty($pets)): ?>
    <p class="muted">You have no pets yet. <a href="<?= BASE_URL ?>/modules/staff/add-pet.php">Add a pet first</a>.</p>
  <?php else: ?>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <div class="grid grid-2">
      <div class="field">
        <label>Pet <span class="req">*</span></label>
        <select name="pet_id" required>
          <option value="">Select pet...</option>
          <?php foreach ($pets as $p): ?>
          <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['type']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Record Type <span class="req">*</span></label>
        <select name="record_type" required>
          <?php foreach (['Vaccination', 'Check-up', 'Surgery', 'Deworming', 'Treatment', 'Other'] as $t): ?>
          <option value="<?= e($t) ?>"><?= e($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Description <span class="req">*</span></label>
        <input name="description" type="text" placeholder="e.g. Rabies vaccine administered" required>
      </div>
      <div class="field">
        <label>Record Date <span class="req">*</span></label>
        <input name="record_date" type="date" required>
      </div>
      <div class="field">
        <label>Vet Name</label>
        <input name="vet_name" type="text" placeholder="Dr. Smith">
      </div>
      <div class="field">
        <label>Clinic Name</label>
        <input name="clinic_name" type="text" placeholder="City Animal Clinic">
      </div>
      <div class="field">
        <label>Next Due Date</label>
        <input name="next_due_date" type="date">
      </div>
    </div>
    <button class="btn btn-primary" type="submit">
      <i class="fa-solid fa-plus"></i> Add Record
    </button>
  </form>
  <?php endif; ?>
</div>

<!-- Records table -->
<div class="card">
  <table class="med-table">
    <thead>
      <tr>
        <th>Pet</th><th>Type</th><th>Description</th>
        <th>Vet</th><th>Date</th><th>Next Due</th><th>Action</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($records as $rec): ?>
    <tr>
      <td style="font-weight:600"><?= e($rec['pet_name']) ?></td>
      <td><span class="badge badge-info"><?= e($rec['record_type']) ?></span></td>
      <td><?= e($rec['description']) ?></td>
      <td class="muted"><?= e($rec['vet_name'] ?: '—') ?></td>
      <td class="muted small">
        <?php $ts = !empty($rec['record_date']) ? strtotime($rec['record_date']) : false;
          echo $ts !== false ? date('M j, Y', $ts) : '—'; ?>
      </td>
      <td class="muted small">
        <?php $ts2 = !empty($rec['next_due_date']) ? strtotime($rec['next_due_date']) : false;
          echo $ts2 !== false ? date('M j, Y', $ts2) : '—'; ?>
      </td>
      <td>
        <form method="post" style="margin:0">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="record_id" value="<?= $rec['id'] ?>">
          <button class="btn btn-sm btn-ghost" data-confirm="Delete this record?"
                  style="color:var(--danger)">Delete</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$records): ?>
    <tr>
      <td colspan="7" style="padding:2rem;text-align:center" class="muted">
        <?= $isRescueOrg ? 'No medical records for your pets yet.' : 'No records yet.' ?>
      </td>
    </tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

</div>
</div>

<style>
.med-table { width:100%;border-collapse:collapse; }
.med-table thead tr { background:var(--muted); }
.med-table th {
  padding:.75rem 1rem;text-align:left;font-size:.78rem;font-weight:700;
  text-transform:uppercase;letter-spacing:.04em;color:var(--muted-fg);
}
.med-table tbody tr { border-bottom:1px solid var(--border);transition:background .15s; }
.med-table tbody tr:last-child { border-bottom:none; }
.med-table tbody tr:hover { background:var(--muted); }
.med-table td { padding:.8rem 1rem;font-size:.88rem;vertical-align:middle; }
</style>

<?php include __DIR__ . '/../../includes/dash-foot.php'; ?>