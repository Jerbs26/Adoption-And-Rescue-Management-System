<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
session_start_once();
require_login(BASE_URL . '/login.php');
require_role('admin');

$user       = current_user();
$activePage = 'announcements';
$pageTitle  = 'Announcements';
$errors     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title  = clean($_POST['title'] ?? '');
        $body   = clean($_POST['body'] ?? '');
        if (!$title || !$body) {
            $errors[] = 'Title and body are required.';
        } else {
            db()->prepare("INSERT INTO announcements (title, body, is_active, created_at) VALUES (?, ?, 1, NOW())")
                ->execute([$title, $body]);
            flash('success', 'Announcement created.');
            redirect(BASE_URL . '/modules/admin/announcements.php');
        }
    } elseif ($action === 'toggle') {
        $id  = (int)($_POST['ann_id'] ?? 0);
        $cur = db()->prepare("SELECT is_active FROM announcements WHERE id = ?");
        $cur->execute([$id]);
        $cur = $cur->fetchColumn();
        db()->prepare("UPDATE announcements SET is_active = ? WHERE id = ?")->execute([$cur ? 0 : 1, $id]);
        flash('success', 'Announcement updated.');
        redirect(BASE_URL . '/modules/admin/announcements.php');
    } elseif ($action === 'delete') {
        $id = (int)($_POST['ann_id'] ?? 0);
        db()->prepare("DELETE FROM announcements WHERE id = ?")->execute([$id]);
        flash('success', 'Announcement deleted.');
        redirect(BASE_URL . '/modules/admin/announcements.php');
    }
}

$anns = db()->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetchAll();

include __DIR__ . '/../../includes/dash-head.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<style>
.main-body {
  max-width: 100% !important;
  padding: 2rem 2.5rem 3rem;
}
.card.card-body { border-radius: 12px; }

/* Responsive */
@media (max-width: 768px) { .main-body { padding: 1.25rem 1.1rem 2rem; } }
@media (max-width: 480px)  { .main-body { padding: 1rem .85rem 2rem; } }

/* ── Mobile card layout ── */
  /* Body text: truncated on desktop, full on mobile */
  .body-desktop { display: inline; }
  .body-mobile  { display: none; }

@media (max-width: 640px) {

  /* Dissolve the outer card wrapper */
  .card:not(.card-body) {
    overflow  : visible !important;
    border    : none !important;
    box-shadow: none !important;
    background: transparent !important;
    border-radius: 0 !important;
  }

  .card table thead { display: none !important; }

  .card table,
  .card table tbody,
  .card table tr,
  .card table td {
    display     : block !important;
    width       : 100% !important;
  }

  .card table tbody tr {
    background   : #fff !important;
    border       : 1px solid var(--border, #e5e7eb) !important;
    border-radius: 10px !important;
    margin-bottom: 10px !important;
    padding      : 12px 14px 6px !important;
    box-shadow   : 0 1px 4px rgba(0,0,0,.06) !important;
  }

  .card table td {
    padding      : 7px 0 !important;
    font-size    : .9rem !important;
    border-bottom: 1px solid var(--border, #f0f0f0) !important;
    display      : flex !important;
    align-items  : center !important;
    gap          : 10px !important;
    white-space  : normal !important;
  }

  .card table td:last-child {
    border-bottom: none !important;
  }

  /* Label from data-label */
  .card table td::before {
    content      : attr(data-label);
    font-size    : .68rem !important;
    font-weight  : 700 !important;
    letter-spacing: .07em !important;
    text-transform: uppercase !important;
    color        : #9ca3af !important;
    min-width    : 58px !important;
    max-width    : 58px !important;
    flex-shrink  : 0 !important;
  }

  /* Title cell — bold header, no label prefix */
  .card table td[data-label="Title"] {
    flex-direction: column !important;
    align-items   : flex-start !important;
    gap           : 0 !important;
    padding-bottom: 10px !important;
    font-weight   : 700 !important;
    font-size     : 1rem !important;
    border-bottom : 2px solid var(--border, #e5e7eb) !important;
  }

  .card table td[data-label="Title"]::before {
    display: none !important;
  }

  .body-desktop { display: none !important; }
  .body-mobile  { display: inline !important; }

  /* Body cell — wrap text */
  .card table td[data-label="Body"] {
    align-items: flex-start !important;
  }

  /* Actions cell — centered */
  .card table td[data-label="Actions"] {
    justify-content: center !important;
  }

  .card table td[data-label="Actions"]::before {
    display: none !important;
  }

  .card table td[data-label="Actions"] form { margin: 0; }
  .card table td[data-label="Actions"] button { width: 100%; justify-content: center; }
}

@media (max-width: 480px) {
  .card-body input[type="text"],
  .card-body textarea { font-size: 16px !important; }
}
</style>
<div class="main-content">
<?php include __DIR__ . '/../../includes/topbar.php'; ?>
<div class="main-body">

<?php $flash = get_flash('success'); if ($flash): ?>
<div class="alert alert-success" data-auto-dismiss><i class="fa-solid fa-check"></i><?= e($flash) ?></div>
<?php endif; ?>
<?php if ($errors): ?>
<div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i><?= e($errors[0]) ?></div>
<?php endif; ?>

<div class="page-header"><h1>Announcements</h1></div>

<div class="card card-body" style="margin-bottom:2rem">
  <h3>New Announcement</h3>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="field">
      <label>Title <span class="req">*</span></label>
      <input name="title" type="text" placeholder="Announcement title" required>
    </div>
    <div class="field">
      <label>Body <span class="req">*</span></label>
      <textarea name="body" rows="3" placeholder="Announcement content..." required></textarea>
    </div>
    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-plus"></i> Create</button>
  </form>
</div>

<div class="card">
  <table style="width:100%;border-collapse:collapse">
    <thead>
      <tr style="background:var(--muted);text-align:left">
        <th style="padding:.75rem 1rem">Title</th>
        <th style="padding:.75rem 1rem">Body</th>
        <th style="padding:.75rem 1rem">Status</th>
        <th style="padding:.75rem 1rem">Date</th>
        <th style="padding:.75rem 1rem;text-align:center">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($anns as $ann): ?>
    <tr style="border-bottom:1px solid var(--border)">
      <td style="padding:.75rem 1rem" data-label="Title"><?= e($ann['title']) ?></td>
      <td style="padding:.75rem 1rem" data-label="Body">
        <span class="body-desktop"><?= e(mb_strlen($ann['body']) > 60 ? mb_substr($ann['body'], 0, 60) . '…' : $ann['body']) ?></span>
        <span class="body-mobile"><?= e($ann['body']) ?></span>
      </td>
      <td style="padding:.75rem 1rem" data-label="Status"><?= $ann['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-muted">Inactive</span>' ?></td>
      <td style="padding:.75rem 1rem" data-label="Date"><?= date('M j, Y', strtotime($ann['created_at'])) ?></td>
      <td style="padding:.75rem 1rem;text-align:center" data-label="Actions">
        <form method="post" style="margin:0;display:inline-flex">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="ann_id" value="<?= $ann['id'] ?>">
          <button class="btn btn-sm btn-danger" data-confirm="Delete this announcement?">
            <i class="fa-solid fa-trash"></i> Delete
          </button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$anns): ?>
    <tr><td colspan="5" style="padding:2rem;text-align:center" class="muted">No announcements yet.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

</div>
</div>
<?php include __DIR__ . '/../../includes/dash-foot.php'; ?>