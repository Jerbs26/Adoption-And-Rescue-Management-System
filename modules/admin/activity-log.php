<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
session_start_once();
require_login(BASE_URL . '/login.php');
require_role('admin');

$user       = current_user();
$activePage = 'activity';
$pageTitle  = 'Activity Log';

$logs = db()->query("
    SELECT al.*, u.full_name
    FROM activity_log al
    LEFT JOIN users u ON u.id = al.user_id
    ORDER BY al.created_at DESC
    LIMIT 100
")->fetchAll();

include __DIR__ . '/../../includes/dash-head.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<style>
.main-body {
  max-width: 100% !important;
  padding: 2rem 2.5rem 3rem;
}
.card {
  border-radius: 12px;
  overflow: hidden;
}
.card table thead tr th {
  font-size: .72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
}
.card table tbody tr:hover {
  background: var(--muted, #f5f5f5);
}
</style>

<div class="main-content">
<?php include __DIR__ . '/../../includes/topbar.php'; ?>
<div class="main-body">

<div class="page-header"><h1>Activity Log</h1></div>

<div class="card">
  <table style="width:100%;border-collapse:collapse">
    <thead>
      <tr style="background:var(--muted);text-align:left">
        <th style="padding:.75rem 1rem">User</th>
        <th style="padding:.75rem 1rem">Action</th>
        <th style="padding:.75rem 1rem">Detail</th>
        <th style="padding:.75rem 1rem">IP</th>
        <th style="padding:.75rem 1rem">Date</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($logs as $log): ?>
    <tr style="border-bottom:1px solid var(--border)">
      <td style="padding:.75rem 1rem"><?= e($log['full_name'] ?? 'System') ?></td>
      <td style="padding:.75rem 1rem"><span class="badge badge-info"><?= e($log['action']) ?></span></td>
      <td style="padding:.75rem 1rem"><?= e($log['detail'] ?? '—') ?></td>
      <td style="padding:.75rem 1rem"><?= e($log['ip_address'] ?? '—') ?></td>
      <td style="padding:.75rem 1rem"><?= !empty($log['created_at']) ? date('M j, Y H:i', strtotime($log['created_at'])) : '—' ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$logs): ?>
    <tr><td colspan="5" style="padding:2rem;text-align:center" class="muted">No activity yet.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

</div>
</div>
<?php include __DIR__ . '/../../includes/dash-foot.php'; ?>