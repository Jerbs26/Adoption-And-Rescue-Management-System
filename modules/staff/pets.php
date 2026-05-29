<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
session_start_once();
require_login(BASE_URL . '/login.php');
require_role('staff', 'rescue_org');

$user       = current_user();
$activePage = 'pets';
$pageTitle  = 'Manage Pets';
$isRescueOrg = $user['role'] === 'rescue_org';

$search  = clean($_GET['q'] ?? '');
$statusF = clean($_GET['status'] ?? 'All');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $petId  = (int)($_POST['pet_id'] ?? 0);

    if ($action === 'delete_pet' && $petId) {
        // rescue_org can only delete their own pets
        $check = $isRescueOrg
            ? db()->prepare("SELECT id FROM pets WHERE id = ? AND added_by = ?")->execute([$petId, $user['id']])
            : true;
        if ($check) {
            $whereOwn = $isRescueOrg ? " AND added_by = {$user['id']}" : '';
            db()->prepare("DELETE FROM pets WHERE id = ?$whereOwn")->execute([$petId]);
            log_activity('delete_pet', 'pet', $petId, "Pet deleted");
            flash('success', 'Pet deleted successfully.');
        }
    }

    if ($action === 'update_status' && $petId) {
        $newStatus = clean($_POST['status'] ?? '');
        if (in_array($newStatus, ['Available', 'Pending', 'Adopted', 'Rescued', 'In Treatment'])) {
            $whereOwn = $isRescueOrg ? " AND added_by = {$user['id']}" : '';
            db()->prepare("UPDATE pets SET status = ? WHERE id = ?$whereOwn")->execute([$newStatus, $petId]);
            log_activity('update_pet_status', 'pet', $petId, "Status changed to: $newStatus");
            flash('success', 'Pet status updated.');
        }
    }

    $qs = http_build_query(array_filter(['q' => $search, 'status' => $statusF !== 'All' ? $statusF : '']));
    redirect(BASE_URL . '/modules/staff/pets.php' . ($qs ? '?' . $qs : ''));
}

$where  = ['1=1'];
$params = [];

// rescue_org sees only their own pets
if ($isRescueOrg) {
    $where[]  = 'added_by = ?';
    $params[] = $user['id'];
}

if ($search !== '') {
    $where[]  = '(name LIKE ? OR breed LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($statusF !== 'All') {
    $where[]  = 'status = ?';
    $params[] = $statusF;
}

$whereSQL = implode(' AND ', $where);
$stmt = db()->prepare("SELECT * FROM pets WHERE $whereSQL ORDER BY created_at DESC");
$stmt->execute($params);
$pets = $stmt->fetchAll();

include __DIR__ . '/../../includes/dash-head.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<div class="main-content">
<?php include __DIR__ . '/../../includes/topbar.php'; ?>
<div class="main-body">

<?php $flash = get_flash('success'); if ($flash): ?>
<div class="alert alert-success" data-auto-dismiss><i class="fa-solid fa-check"></i><?= e($flash) ?></div>
<?php endif; ?>

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem">
  <h1>Manage Pets</h1>
  <a href="<?= BASE_URL ?>/modules/staff/add-pet.php" class="btn btn-primary">
    <i class="fa-solid fa-plus"></i> Add Pet
  </a>
</div>

<form method="get" style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1.5rem">
  <input name="q" type="text" placeholder="Search name or breed..."
         value="<?= e($search) ?>" style="flex:1;min-width:200px">
  <select name="status" onchange="this.form.submit()">
    <?php foreach (['All', 'Available', 'Pending', 'Adopted', 'Rescued', 'In Treatment'] as $s): ?>
    <option <?= $statusF === $s ? 'selected' : '' ?>><?= e($s) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-secondary" type="submit">Search</button>
</form>

<div class="card">
  <table class="pets-table">
    <thead>
      <tr>
        <th>Photo</th>
        <th>Name</th>
        <th>Breed / Type</th>
        <th>Status</th>
        <th style="text-align:center">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($pets as $pet): ?>
    <tr>
      <td>
        <img src="<?= e(pet_image_url($pet['primary_image'])) ?>"
             style="width:48px;height:40px;object-fit:cover;border-radius:.4rem" alt="">
      </td>
      <td style="font-weight:600"><?= e($pet['name']) ?></td>
      <td class="muted"><?= e($pet['breed']) ?> &middot; <?= e($pet['type']) ?></td>
      <td>
        <form method="post" style="margin:0">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_status">
          <input type="hidden" name="pet_id" value="<?= $pet['id'] ?>">
          <select name="status" onchange="this.form.submit()">
            <?php foreach (['Available', 'Pending', 'Adopted', 'Rescued', 'In Treatment'] as $s): ?>
            <option <?= $pet['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </td>
      <td>
        <div class="row-actions">
          <a href="<?= BASE_URL ?>/modules/staff/edit-pet.php?id=<?= $pet['id'] ?>"
             class="btn-action btn-edit" title="Edit pet">
            <i class="fa-solid fa-pen-to-square"></i> Edit
          </a>
          <button type="button"
                  class="btn-action btn-delete"
                  title="Delete pet"
                  onclick="confirmDelete(<?= $pet['id'] ?>, '<?= e(addslashes($pet['name'])) ?>')">
            <i class="fa-solid fa-trash"></i> Delete
          </button>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$pets): ?>
    <tr>
      <td colspan="5" style="padding:2rem;text-align:center" class="muted">
        <?= $isRescueOrg ? 'No pets added by your organization yet.' : 'No pets found.' ?>
      </td>
    </tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

</div>
</div>

<style>
.pets-table { width:100%;border-collapse:collapse; }
.pets-table thead tr { background:var(--muted); }
.pets-table th {
  padding:.75rem 1rem;text-align:left;font-size:.78rem;font-weight:700;
  text-transform:uppercase;letter-spacing:.04em;color:var(--muted-fg);
}
.pets-table tbody tr { border-bottom:1px solid var(--border);transition:background .15s; }
.pets-table tbody tr:last-child { border-bottom:none; }
.pets-table tbody tr:hover { background:var(--muted); }
.pets-table td { padding:.8rem 1rem;font-size:.88rem;vertical-align:middle; }
</style>

<!-- Delete confirmation modal -->
<div id="delete-modal" class="modal-backdrop" style="display:none" role="dialog" aria-modal="true" aria-labelledby="modal-title">
  <div class="modal-box">
    <div class="modal-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
    <h2 id="modal-title">Delete Pet?</h2>
    <p id="modal-msg" class="muted"></p>
    <div class="modal-actions">
      <button class="btn btn-secondary modal-cancel-btn" onclick="closeModal()">Cancel</button>
      <form method="post" style="margin:0" id="delete-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_pet">
        <input type="hidden" name="pet_id" id="delete-pet-id" value="">
        <button type="submit" class="btn btn-danger">Yes, Delete</button>
      </form>
    </div>
  </div>
</div>

<style>
.row-actions { display:flex;gap:.5rem;align-items:center;justify-content:center; }
.btn-action {
  display:inline-flex;align-items:center;gap:.35rem;
  padding:.35rem .75rem;border-radius:.4rem;font-size:.8rem;font-weight:600;
  cursor:pointer;border:1px solid transparent;text-decoration:none;
  transition:background .15s,border-color .15s,color .15s;white-space:nowrap;
}
.btn-edit { background:var(--muted);border-color:var(--border);color:var(--fg); }
.btn-edit:hover { background:var(--border);color:var(--fg); }
.btn-delete { background:transparent;border-color:#e5c5c5;color:#c0392b; }
.btn-delete:hover { background:#fdf0f0;border-color:#c0392b; }
.modal-backdrop {
  position:fixed;inset:0;background:rgba(0,0,0,.45);
  display:flex;align-items:center;justify-content:center;z-index:9999;
  animation:fadeIn .15s ease;
}
@keyframes fadeIn { from{opacity:0} to{opacity:1} }
.modal-box {
  background:#fff;border-radius:.8rem;padding:2rem 2.25rem;
  max-width:400px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,.18);
  text-align:center;animation:slideUp .18s ease;
}
@keyframes slideUp { from{transform:translateY(12px);opacity:0} to{transform:translateY(0);opacity:1} }
.modal-icon { font-size:2rem;color:#e67e22;margin-bottom:.75rem; }
.modal-box h2 { margin:0 0 .5rem;font-size:1.2rem; }
.modal-box p { margin:0 0 1.5rem;font-size:.9rem; }
.modal-actions { display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;align-items:center; }
.modal-cancel-btn { padding:.5rem 1.25rem;border-radius:.4rem;font-size:.88rem;font-weight:600;line-height:1.5; }
.btn-danger {
  display:inline-flex;align-items:center;gap:.4rem;
  padding:.5rem 1.25rem;border-radius:.4rem;font-size:.88rem;font-weight:600;line-height:1.5;
  background:#c0392b;color:#fff;border:none;cursor:pointer;transition:background .15s;
}
.btn-danger:hover { background:#a93226; }
</style>

<script>
function confirmDelete(id, name) {
  document.getElementById('delete-pet-id').value = id;
  document.getElementById('modal-msg').textContent =
    'Are you sure you want to delete "' + name + '"? This action cannot be undone.';
  document.getElementById('delete-modal').style.display = 'flex';
}
function closeModal() {
  document.getElementById('delete-modal').style.display = 'none';
}
document.getElementById('delete-modal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeModal();
});
</script>

<?php include __DIR__ . '/../../includes/dash-foot.php'; ?>