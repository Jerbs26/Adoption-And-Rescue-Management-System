<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
session_start_once();
require_login(BASE_URL . '/login.php');

$user       = current_user();
$activePage = 'notifications';
$pageTitle  = 'Notifications';

function strip_emoji(string $text): string {
    $text = preg_replace('/[\x{1F000}-\x{1FFFF}]/u', '', $text);
    $text = preg_replace('/[\x{2600}-\x{27FF}]/u', '', $text);
    $text = preg_replace('/[\x{FE00}-\x{FEFF}]/u', '', $text);
    $text = preg_replace('/[\x{1F300}-\x{1F9FF}]/u', '', $text);
    return trim($text);
}

// Mark all as read
db()->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$user['id']]);

// Fetch personal notifications
$notifStmt = db()->prepare("
    SELECT id, title, message AS body, created_at, 'notification' AS kind
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$notifStmt->execute([$user['id']]);
$notifs = $notifStmt->fetchAll();

// Fetch active announcements
$announcements = db()->query("
    SELECT id, title, body, created_at, 'announcement' AS kind
    FROM announcements
    WHERE is_active = 1
    ORDER BY created_at DESC
")->fetchAll();

// Merge and sort by created_at descending
$all = array_merge($notifs, $announcements);
usort($all, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));

include __DIR__ . '/../../includes/dash-head.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<style>
.notif-list { margin: 0; padding: 0; list-style: none; }
.notif-item {
  padding: 1rem 1.5rem; border-bottom: 1px solid var(--border);
  display: flex; align-items: flex-start; gap: 1rem;
}
@media (max-width: 480px) { .notif-item { padding: .85rem 1rem; gap: .75rem; } }
.notif-item:last-child { border-bottom: none; }
.notif-icon {
  width: 34px; height: 34px; border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
  font-size: .85rem; flex-shrink: 0; margin-top: .1rem;
}
.notif-icon--announcement { background: #fff4ed; color: #e8722a; }
.notif-icon--notification  { background: #eef7f2; color: #3a7d5a; }
.notif-body { flex: 1; min-width: 0; }
.notif-meta { display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem; }
.notif-kind-label { font-size: .68rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #e8722a; margin-bottom: .2rem; display: block; }
.notif-title { font-weight: 800; font-size: .9rem; word-break: break-word; }
.notif-text  { color: var(--muted-fg); font-size: .85rem; margin-top: .2rem; line-height: 1.5; word-break: break-word; }
.notif-date  { color: var(--muted-fg); font-size: .75rem; flex-shrink: 0; white-space: nowrap; }
</style>

<div class="main-content">
<?php include __DIR__ . '/../../includes/topbar.php'; ?>
<div class="main-body">

  <div class="page-header">
    <h1 style="font-size:1.5rem;font-weight:800;margin:0 0 .25rem">Notifications</h1>
    <p class="muted" style="margin:0;font-size:.875rem">Your activity updates and system announcements.</p>
  </div>

  <?php if ($all): ?>
  <div class="card">
    <ul class="notif-list">
    <?php foreach ($all as $item): ?>
      <li class="notif-item">

        <div class="notif-icon notif-icon--<?= $item['kind'] ?>">
          <?php if ($item['kind'] === 'announcement'): ?>
            <i class="fa-solid fa-bullhorn"></i>
          <?php else: ?>
            <i class="fa-solid fa-bell"></i>
          <?php endif; ?>
        </div>

        <div class="notif-body">
          <div class="notif-meta">
            <div style="flex:1;min-width:0">
              <?php if ($item['kind'] === 'announcement'): ?>
                <span class="notif-kind-label">Announcement</span>
              <?php endif; ?>
              <div class="notif-title"><?= e(strip_emoji($item['title'])) ?></div>
              <div class="notif-text"><?= e(strip_emoji($item['body'])) ?></div>
            </div>
            <div class="notif-date"><?= date('M j, Y', strtotime($item['created_at'])) ?></div>
          </div>
        </div>

      </li>
    <?php endforeach; ?>
    </ul>
  </div>
  <?php else: ?>
  <div class="empty-state">
    <i class="fa-solid fa-bell-slash"></i>
    <h3>Nothing here yet</h3>
    <p class="muted">You are all caught up.</p>
  </div>
  <?php endif; ?>

</div>
</div>
<?php include __DIR__ . '/../../includes/dash-foot.php'; ?>