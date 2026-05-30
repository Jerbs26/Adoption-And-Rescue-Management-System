<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
session_start_once();
require_login(BASE_URL . '/login.php');
require_role('staff', 'rescue_org');

$user        = current_user();
$activePage  = 'applications';
$pageTitle   = 'Applications';
$isRescueOrg = $user['role'] === 'rescue_org';

$statusF = clean($_GET['status'] ?? 'All');
$where   = ['1=1'];
$params  = [];

// rescue_org only sees applications for their own pets
if ($isRescueOrg) {
    $where[]  = 'p.added_by = ?';
    $params[] = $user['id'];
}

if ($statusF !== 'All') {
    $where[]  = 'aa.status = ?';
    $params[] = $statusF;
}

$whereSQL = implode(' AND ', $where);
$stmt = db()->prepare("
    SELECT aa.*, u.full_name AS applicant_name, u.email AS applicant_email,
           u.phone AS applicant_phone, p.name AS pet_name, p.type AS pet_type
    FROM adoption_applications aa
    JOIN users u ON u.id = aa.adopter_id
    JOIN pets  p ON p.id = aa.pet_id
    WHERE $whereSQL
    ORDER BY aa.created_at DESC
");
$stmt->execute($params);
$apps = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $appId         = (int)($_POST['app_id'] ?? 0);
    $newStatus     = clean($_POST['status'] ?? '');
    $reviewerNotes = clean($_POST['reviewer_notes'] ?? '');

    $validStatuses = ['Pending', 'Under Review', 'Approved', 'Rejected', 'Withdrawn'];

    if ($appId && in_array($newStatus, $validStatuses)) {
        // rescue_org can only update applications for their own pets
        if ($isRescueOrg) {
            $ownerCheck = db()->prepare("
                SELECT aa.id FROM adoption_applications aa
                JOIN pets p ON p.id = aa.pet_id
                WHERE aa.id = ? AND p.added_by = ?
            ");
            $ownerCheck->execute([$appId, $user['id']]);
            if (!$ownerCheck->fetch()) {
                redirect(BASE_URL . '/modules/staff/applications.php');
            }
        }

        $setReviewedAt = in_array($newStatus, ['Under Review', 'Approved', 'Rejected'])
            ? ', reviewed_at = NOW(), reviewed_by = ' . (int)$user['id']
            : '';

        db()->prepare(
            "UPDATE adoption_applications
             SET status = ?, reviewer_notes = ?, updated_at = NOW(){$setReviewedAt}
             WHERE id = ?"
        )->execute([$newStatus, $reviewerNotes ?: null, $appId]);

        if ($newStatus === 'Approved') {
            $appData = db()->prepare(
                "SELECT pet_id, adopter_id AS user_id FROM adoption_applications WHERE id = ?"
            );
            $appData->execute([$appId]);
            $appData = $appData->fetch();
            if ($appData) {
                db()->prepare("UPDATE pets SET status = 'Adopted' WHERE id = ?")
                    ->execute([$appData['pet_id']]);
                db()->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)")
                    ->execute([
                        $appData['user_id'],
                        'Application Approved!',
                        'Congratulations! Your adoption application has been approved.',
                    ]);
            }
        } elseif ($newStatus === 'Rejected') {
            $appData = db()->prepare(
                "SELECT adopter_id AS user_id FROM adoption_applications WHERE id = ?"
            );
            $appData->execute([$appId]);
            $appData = $appData->fetch();
            if ($appData) {
                db()->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)")
                    ->execute([
                        $appData['user_id'],
                        'Application Update',
                        'Unfortunately, your adoption application was not approved at this time.',
                    ]);
            }
        }

        log_activity('update_application', 'application', $appId, "Status changed to: $newStatus");
        flash('success', 'Application updated.');
    }
    redirect(BASE_URL . '/modules/staff/applications.php');
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

<div class="page-header">
  <h1>Adoption Applications</h1>
</div>

<form method="get" style="margin-bottom:1.5rem">
  <select name="status" onchange="this.form.submit()" class="apps-filter-select">
    <?php foreach (['All', 'Pending', 'Under Review', 'Approved', 'Rejected', 'Withdrawn'] as $s): ?>
    <option <?= $statusF === $s ? 'selected' : '' ?>><?= e($s) ?></option>
    <?php endforeach; ?>
  </select>
</form>

<?php if (!$apps): ?>
<div class="card" style="padding:2rem;text-align:center" class="muted">
  <?= $isRescueOrg ? 'No applications for your pets yet.' : 'No applications found.' ?>
</div>
<?php else: ?>

<!-- ── Desktop table ── -->
<div class="card apps-table-wrap" style="overflow:hidden">
  <div style="overflow-x:auto">
  <table class="apps-table">
    <thead>
      <tr>
        <th>Applicant</th>
        <th>Pet</th>
        <th>Status</th>
        <th>Applied</th>
        <th>Form Details</th>
        <th>Update Status</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($apps as $app): ?>
    <tr class="app-main">
      <td>
        <div class="app-name"><?= e($app['applicant_name']) ?></div>
        <div class="muted small"><?= e($app['applicant_email']) ?></div>
        <?php if ($app['applicant_phone']): ?>
        <div class="muted small"><?= e($app['applicant_phone']) ?></div>
        <?php endif; ?>
      </td>
      <td>
        <div style="font-weight:600"><?= e($app['pet_name']) ?></div>
        <div class="muted small"><?= e($app['pet_type']) ?></div>
      </td>
      <td><?= status_badge($app['status']) ?></td>
      <td class="muted small"><?= date('M j, Y', strtotime($app['created_at'])) ?></td>
      <td>
        <button class="btn-view" id="btn-<?= $app['id'] ?>"
                onclick="toggleDetails(<?= $app['id'] ?>)">
          <i class="fa-solid fa-eye"></i> View
        </button>
      </td>
      <td>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
          <div class="update-form-inner">
            <div style="display:flex;gap:.5rem;align-items:center">
              <select name="status" style="flex:1;font-size:.82rem">
                <?php foreach (['Pending', 'Under Review', 'Approved', 'Rejected', 'Withdrawn'] as $s): ?>
                <option <?= $app['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-sm btn-primary" type="submit">Save</button>
            </div>
            <textarea name="reviewer_notes" rows="2"
              placeholder="Reviewer notes (optional)..."
              class="reviewer-notes"><?= e($app['reviewer_notes'] ?? '') ?></textarea>
          </div>
        </form>
      </td>
    </tr>
    <tr class="app-details" id="app-<?= $app['id'] ?>">
      <td colspan="6">
        <div class="details-grid">
          <div class="detail-box">
            <div class="detail-label">Home Type</div>
            <div class="detail-value"><?= e($app['home_type'] ?: '—') ?></div>
          </div>
          <div class="detail-box">
            <div class="detail-label">Experience</div>
            <div class="detail-value"><?= e($app['experience'] ?: '—') ?></div>
          </div>
          <div class="detail-box">
            <div class="detail-label">Other Pets at Home</div>
            <div class="detail-value <?= $app['has_other_pets'] ? 'val-yes' : '' ?>">
              <?= $app['has_other_pets'] ? 'Yes' : 'No' ?>
            </div>
          </div>
          <div class="detail-box">
            <div class="detail-label">Children at Home</div>
            <div class="detail-value <?= $app['has_children'] ? 'val-info' : '' ?>">
              <?= $app['has_children'] ? 'Yes' : 'No' ?>
            </div>
          </div>
        </div>
        <div class="reason-label">Why would they be a great owner?</div>
        <div class="reason-box"><?= e($app['reason'] ?: '—') ?></div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<!-- ── Mobile cards ── -->
<div class="apps-mobile-list">
<?php foreach ($apps as $app): ?>
<div class="app-card">

  <!-- Card header: applicant + pet + meta -->
  <div class="app-card__header">
    <div class="app-card__applicant">
      <div class="app-card__name"><?= e($app['applicant_name']) ?></div>
      <div class="app-card__email"><?= e($app['applicant_email']) ?></div>
      <?php if ($app['applicant_phone']): ?>
      <div class="app-card__phone"><?= e($app['applicant_phone']) ?></div>
      <?php endif; ?>
    </div>
    <div class="app-card__badge"><?= status_badge($app['status']) ?></div>
  </div>

  <!-- Pet info -->
  <div class="app-card__row">
    <span class="app-card__label">Pet</span>
    <span class="app-card__val">
      <strong><?= e($app['pet_name']) ?></strong>
      <span class="muted"> &middot; <?= e($app['pet_type']) ?></span>
    </span>
  </div>

  <!-- Date -->
  <div class="app-card__row">
    <span class="app-card__label">Applied</span>
    <span class="app-card__val"><?= date('M j, Y', strtotime($app['created_at'])) ?></span>
  </div>

  <!-- Update status form -->
  <div class="app-card__section">
    <div class="app-card__section-title">Update Status</div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
      <div class="app-card__status-row">
        <select name="status" class="app-card__select">
          <?php foreach (['Pending', 'Under Review', 'Approved', 'Rejected', 'Withdrawn'] as $s): ?>
          <option <?= $app['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-primary" type="submit">Save</button>
      </div>
      <textarea name="reviewer_notes" rows="2"
        placeholder="Reviewer notes (optional)..."
        class="app-card__notes"><?= e($app['reviewer_notes'] ?? '') ?></textarea>
    </form>
  </div>

  <!-- Toggle details -->
  <button class="app-card__toggle" onclick="toggleMobileDetails(this)">
    <i class="fa-solid fa-chevron-down"></i> View Form Details
  </button>

  <!-- Expandable details -->
  <div class="app-card__details" hidden>
    <div class="app-card__details-grid">
      <div class="detail-box">
        <div class="detail-label">Home Type</div>
        <div class="detail-value"><?= e($app['home_type'] ?: '—') ?></div>
      </div>
      <div class="detail-box">
        <div class="detail-label">Experience</div>
        <div class="detail-value"><?= e($app['experience'] ?: '—') ?></div>
      </div>
      <div class="detail-box">
        <div class="detail-label">Other Pets</div>
        <div class="detail-value <?= $app['has_other_pets'] ? 'val-yes' : '' ?>">
          <?= $app['has_other_pets'] ? 'Yes' : 'No' ?>
        </div>
      </div>
      <div class="detail-box">
        <div class="detail-label">Children</div>
        <div class="detail-value <?= $app['has_children'] ? 'val-info' : '' ?>">
          <?= $app['has_children'] ? 'Yes' : 'No' ?>
        </div>
      </div>
    </div>
    <div class="reason-label" style="margin-top:.75rem">Why a great owner?</div>
    <div class="reason-box"><?= e($app['reason'] ?: '—') ?></div>
  </div>

</div>
<?php endforeach; ?>
</div>

<?php endif; ?>

</div>
</div>

<style>
/* ── Layout ── */
.main-body { max-width:100% !important; padding:2rem 2.5rem 3rem; }
@media (max-width:768px) { .main-body { padding:1.25rem 1.1rem 2.5rem; } }
@media (max-width:640px) { .main-body { padding:1rem .85rem 2rem; } }

/* ── Filter select ── */
.apps-filter-select {
  padding:.45rem .75rem;border:1px solid var(--border);border-radius:.5rem;
  background:var(--card);color:var(--fg);font:inherit;font-size:.88rem;
  cursor:pointer;min-width:160px;
}

/* ── Desktop table (hidden on mobile) ── */
.apps-table-wrap { display:block; }
.apps-table { width:100%;border-collapse:collapse; }
.apps-table thead tr { background:var(--muted); }
.apps-table th {
  padding:.7rem 1rem;text-align:left;font-size:.78rem;font-weight:700;
  text-transform:uppercase;letter-spacing:.04em;color:var(--muted-fg);white-space:nowrap;
}
.apps-table tbody tr.app-main { border-bottom:1px solid var(--border);transition:background .15s; }
.apps-table tbody tr.app-main:hover { background:var(--muted); }
.apps-table td { padding:.8rem 1rem;font-size:.88rem;vertical-align:middle; }
.app-name { font-weight:700;font-size:.9rem; }
.btn-view {
  display:inline-flex;align-items:center;gap:.35rem;
  font-size:.78rem;font-weight:600;padding:.35rem .8rem;
  border:1.5px solid var(--border);border-radius:50px;
  background:transparent;cursor:pointer;white-space:nowrap;
  transition:background .15s,border-color .15s;
}
.btn-view:hover { background:var(--muted);border-color:var(--primary);color:var(--primary); }
.btn-view.active { background:var(--primary);color:#fff;border-color:var(--primary); }
.update-form-inner { display:flex;flex-direction:column;gap:.4rem;min-width:260px; }
.reviewer-notes {
  font-size:.78rem;padding:.35rem .5rem;
  border:1px solid var(--border);border-radius:6px;
  resize:vertical;width:100%;font-family:inherit;box-sizing:border-box;
}
.app-details { display:none;background:#faf9f7;border-bottom:2px solid var(--border); }
.app-details td { padding:1.25rem 1.5rem; }

/* ── Mobile cards (hidden on desktop) ── */
.apps-mobile-list { display:none; }

/* ── Shared detail styles ── */
.details-grid {
  display:grid;grid-template-columns:repeat(4,1fr);gap:.6rem 1rem;margin-bottom:1rem;
}
@media(max-width:800px){ .details-grid { grid-template-columns:repeat(2,1fr); } }
.detail-box {
  background:var(--card);border:1px solid var(--border);border-radius:10px;padding:.65rem .85rem;
}
.detail-label {
  font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;
  color:var(--muted-fg);margin-bottom:.25rem;
}
.detail-value { font-size:.88rem;font-weight:600;color:var(--fg); }
.detail-value.val-yes  { color:var(--success); }
.detail-value.val-info { color:var(--info); }
.reason-label {
  font-size:.72rem;font-weight:700;text-transform:uppercase;
  letter-spacing:.05em;color:var(--muted-fg);margin-bottom:.45rem;
}
.reason-box {
  background:var(--card);border:1px solid var(--border);border-radius:10px;
  padding:.85rem 1rem;font-size:.86rem;line-height:1.65;
  color:var(--fg);white-space:pre-wrap;word-break:break-word;
}

/* ════════════════════════════════════
   MOBILE  ≤ 640px
   ════════════════════════════════════ */
@media (max-width:640px) {

  /* Swap visibility */
  .apps-table-wrap { display:none !important; }
  .apps-mobile-list { display:flex;flex-direction:column;gap:1rem; }

  /* ── App card ── */
  .app-card {
    background:var(--card);border:1px solid var(--border);
    border-radius:14px;overflow:hidden;
    box-shadow:0 2px 8px rgba(0,0,0,.07);
  }

  /* Header strip */
  .app-card__header {
    display:flex;align-items:flex-start;justify-content:space-between;
    gap:.75rem;padding:14px 14px 10px;
    border-bottom:2px solid var(--border);
    background:var(--muted);
  }
  .app-card__name {
    font-weight:800;font-size:1rem;color:var(--fg);line-height:1.2;
  }
  .app-card__email, .app-card__phone {
    font-size:.78rem;color:var(--muted-fg);margin-top:2px;
  }
  .app-card__badge { flex-shrink:0;margin-top:2px; }

  /* Info rows */
  .app-card__row {
    display:flex;align-items:center;gap:10px;
    padding:9px 14px;border-bottom:1px solid var(--border);
    font-size:.88rem;
  }
  .app-card__label {
    font-size:.68rem;font-weight:700;text-transform:uppercase;
    letter-spacing:.07em;color:var(--muted-fg);
    min-width:56px;flex-shrink:0;
  }
  .app-card__val { flex:1;color:var(--fg); }

  /* Update status section */
  .app-card__section {
    padding:12px 14px;border-bottom:1px solid var(--border);
  }
  .app-card__section-title {
    font-size:.68rem;font-weight:700;text-transform:uppercase;
    letter-spacing:.07em;color:var(--muted-fg);margin-bottom:8px;
  }
  .app-card__status-row {
    display:flex;gap:.5rem;align-items:center;margin-bottom:.5rem;
  }
  .app-card__select {
    flex:1;min-width:0;padding:.45rem .65rem;
    border:1px solid var(--border);border-radius:.5rem;
    background:var(--card);color:var(--fg);
    font:inherit;font-size:16px;cursor:pointer;
  }
  .app-card__notes {
    width:100%;box-sizing:border-box;
    font:inherit;font-size:16px;
    padding:.45rem .65rem;
    border:1px solid var(--border);border-radius:.5rem;
    resize:vertical;background:var(--card);color:var(--fg);
  }
  .app-card__section .btn { white-space:nowrap;flex-shrink:0; }

  /* Toggle button */
  .app-card__toggle {
    display:flex;align-items:center;justify-content:center;gap:.4rem;
    width:100%;padding:10px 14px;
    background:transparent;border:none;border-bottom:1px solid var(--border);
    font:inherit;font-size:.82rem;font-weight:700;color:var(--primary);
    cursor:pointer;transition:background .15s;
  }
  .app-card__toggle:hover { background:var(--muted); }
  .app-card__toggle i { transition:transform .2s; }
  .app-card__toggle.open i { transform:rotate(180deg); }

  /* Details panel */
  .app-card__details { padding:12px 14px; }
  .app-card__details-grid {
    display:grid;grid-template-columns:repeat(2,1fr);gap:.5rem;margin-bottom:.75rem;
  }

  /* iOS zoom fix */
  .apps-filter-select { font-size:16px !important; }
}
</style>

<script>
// Desktop toggle
function toggleDetails(id) {
  const row = document.getElementById('app-' + id);
  const btn = document.getElementById('btn-' + id);
  const open = row.style.display === 'table-row';
  row.style.display = open ? 'none' : 'table-row';
  btn.classList.toggle('active', !open);
  btn.innerHTML = open
    ? '<i class="fa-solid fa-eye"></i> View'
    : '<i class="fa-solid fa-eye-slash"></i> Hide';
}

// Mobile toggle
function toggleMobileDetails(btn) {
  const card   = btn.closest('.app-card');
  const panel  = card.querySelector('.app-card__details');
  const isOpen = !panel.hidden;
  panel.hidden = isOpen;
  btn.classList.toggle('open', !isOpen);
  btn.innerHTML = isOpen
    ? '<i class="fa-solid fa-chevron-down"></i> View Form Details'
    : '<i class="fa-solid fa-chevron-up"></i> Hide Form Details';
}
</script>

<?php include __DIR__ . '/../../includes/dash-foot.php'; ?>