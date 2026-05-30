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

<div class="page-header pets-page-header">
  <h1>Manage Pets</h1>
  <a href="<?= BASE_URL ?>/modules/staff/add-pet.php" class="btn btn-primary">
    <i class="fa-solid fa-plus"></i> Add Pet
  </a>
</div>

<form method="get" class="pets-search-form">
  <div class="pets-search-row1">
    <input name="q" type="text" placeholder="Search name or breed..."
           value="<?= e($search) ?>">
  </div>
  <div class="pets-search-row2">
    <select name="status" onchange="this.form.submit()" class="pets-filter-select">
      <?php foreach (['All', 'Available', 'Pending', 'Adopted', 'Rescued', 'In Treatment'] as $s): ?>
      <option <?= $statusF === $s ? 'selected' : '' ?>><?= e($s) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-secondary" type="submit">
      <i class="fa-solid fa-magnifying-glass"></i> Search
    </button>
  </div>
</form>

<div class="card pets-table-wrap">
  <table class="pets-table">
    <thead>
      <tr>
        <th>Pet</th>
        <th>Status</th>
        <th style="text-align:center">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($pets as $pet): ?>
    <tr>
      <td>
        <div style="display:flex;align-items:center;gap:.75rem">
          <img src="<?= e(pet_image_url($pet['primary_image'])) ?>"
               style="width:48px;height:40px;object-fit:cover;border-radius:.4rem;flex-shrink:0" alt="">
          <div>
            <div class="td-name" style="font-weight:600"><?= e($pet['name']) ?></div>
            <div class="td-meta" style="font-size:.8rem;color:var(--muted-fg)"><?= e($pet['breed']) ?> &middot; <?= e($pet['type']) ?></div>
          </div>
        </div>
      </td>
      <td data-label="Status">
        <form method="post" style="margin:0">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_status">
          <input type="hidden" name="pet_id" value="<?= $pet['id'] ?>">
          <select name="status" class="status-select" onchange="this.form.submit()">
            <?php foreach (['Available', 'Pending', 'Adopted', 'Rescued', 'In Treatment'] as $s): ?>
            <option <?= $pet['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </td>
      <td data-label="Actions">
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
      <td colspan="3" style="padding:2rem;text-align:center" class="muted">
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
/* ── Layout ── */
.main-body { max-width:100% !important; padding:2rem 2.5rem 3rem; }
@media (max-width:768px) { .main-body { padding:1.25rem 1.1rem 2.5rem; } }
@media (max-width:640px) { .main-body { padding:1rem .85rem 2rem; } }

/* ── Page header ── */
.pets-page-header {
  display:flex !important;
  flex-direction:row !important;
  align-items:center !important;
  justify-content:space-between !important;
  flex-wrap:nowrap !important;
  gap:.75rem !important;
  margin-bottom:1.25rem;
  text-align:left !important;
}
.pets-page-header h1 { margin:0; text-align:left !important; }

/* ── Search form ── */
.pets-search-form { margin-bottom:1.5rem; display:flex; flex-direction:column; gap:.6rem; }
.pets-search-row1 { display:flex; }
.pets-search-row1 input { flex:1; width:100%; }
.pets-search-row2 { display:flex; gap:.6rem; }
.pets-filter-select { flex:1; min-width:0; }
.pets-search-row2 .btn { white-space:nowrap; flex-shrink:0; }

/* ── Table (desktop) ── */
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

/* ── Status select (in-table) — styled to match system ── */
.status-select {
  appearance:auto;
  padding:.35rem .65rem;
  border:1px solid var(--border);
  border-radius:.5rem;
  background:var(--card);
  color:var(--fg);
  font:inherit;
  font-size:.82rem;
  font-weight:600;
  cursor:pointer;
  transition:border-color .15s;
  width:100%;
}
.status-select:focus { outline:none; border-color:var(--primary); }

/* ── Mobile card transform ── */
@media (max-width:640px) {

  /* Dissolve outer card border */
  .pets-table-wrap.card {
    overflow:visible !important;
    border:none !important;
    box-shadow:none !important;
    background:transparent !important;
    border-radius:0 !important;
  }

  /* Hide thead */
  .pets-table-wrap thead { display:none !important; }

  /* Stack table elements */
  .pets-table-wrap table,
  .pets-table-wrap tbody,
  .pets-table-wrap tr,
  .pets-table-wrap td {
    display:block !important;
    width:100% !important;
    table-layout:auto !important;
  }

  /* Each row = card */
  .pets-table-wrap tbody tr {
    background:var(--card) !important;
    border:1px solid var(--border) !important;
    border-radius:14px !important;
    margin:0 0 1rem !important;
    padding:0 0 10px !important;
    box-shadow:0 2px 8px rgba(0,0,0,.07) !important;
    overflow:hidden !important;
  }

  /* Each cell: label + value */
  .pets-table-wrap td {
    padding:8px 14px !important;
    font-size:.88rem !important;
    overflow:visible !important;
    text-overflow:unset !important;
    white-space:normal !important;
    display:flex !important;
    align-items:center !important;
    gap:10px !important;
    border-bottom:1px solid var(--border) !important;
  }

  .pets-table-wrap td:last-child { border-bottom:none !important; }

  /* data-label pseudo */
  .pets-table-wrap td::before {
    content:attr(data-label);
    font-size:.68rem !important;
    font-weight:700 !important;
    letter-spacing:.07em !important;
    text-transform:uppercase !important;
    color:var(--muted-fg) !important;
    min-width:62px !important;
    max-width:62px !important;
    flex-shrink:0 !important;
  }

  /* First cell — full-bleed photo + name/breed below */
  .pets-table-wrap td:first-child {
    flex-direction:column !important;
    align-items:flex-start !important;
    gap:10px !important;
    padding:0 0 10px !important;
    border-bottom:2px solid var(--border) !important;
  }
  .pets-table-wrap td:first-child::before { display:none !important; }

  /* Inner flex wrapper → stack vertically */
  .pets-table-wrap td:first-child > div {
    flex-direction:column !important;
    align-items:flex-start !important;
    gap:0 !important;
    width:100% !important;
  }

  /* Full-bleed photo */
  .pets-table-wrap td:first-child img {
    width:100% !important;
    height:210px !important;
    border-radius:0 !important;
    object-fit:cover !important;
    object-position:center center !important;
    display:block !important;
  }

  /* Name + breed text padding */
  .pets-table-wrap td:first-child .td-name {
    font-weight:700 !important;
    font-size:1.05rem !important;
    padding:10px 14px 2px !important;
  }
  .pets-table-wrap td:first-child .td-meta {
    padding:0 14px 4px !important;
    font-size:.82rem !important;
  }

  /* Status cell */
  .pets-table-wrap td[data-label="Status"] { align-items:center !important; }
  .pets-table-wrap td[data-label="Status"] form { flex:1; min-width:0; }
  .pets-table-wrap .status-select { width:100% !important; font-size:16px !important; }

  /* Actions cell — full-width stacked buttons */
  .pets-table-wrap td:last-child::before { display:none !important; }
  .pets-table-wrap td[data-label="Actions"] {
    flex-direction:column !important;
    gap:.5rem !important;
    padding-top:10px !important;
  }
  .pets-table-wrap .row-actions {
    flex-direction:column !important;
    width:100% !important;
    gap:.5rem !important;
  }
  .pets-table-wrap .row-actions a,
  .pets-table-wrap .row-actions button {
    width:100% !important;
    justify-content:center !important;
    padding:.6rem 1rem !important;
    font-size:.88rem !important;
    border-radius:.5rem !important;
  }

  /* iOS zoom prevention */
  .pets-search-form input,
  .pets-search-form select,
  .pets-filter-select { font-size:16px !important; }
}
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
.btn-delete { background:#c0392b;border-color:#c0392b;color:#fff; }
.btn-delete:hover { background:#a93226;border-color:#a93226;color:#fff; }
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