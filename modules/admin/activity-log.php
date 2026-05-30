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
/* Responsive */
@media (max-width: 768px) { .main-body { padding: 1.25rem 1.1rem 2rem; } }
@media (max-width: 480px)  { .main-body { padding: 1rem .85rem 2rem; } }

/* ── Mobile card layout ── */
@media (max-width: 640px) {

  .card {
    overflow  : visible !important;
    border    : none !important;
    box-shadow: none !important;
    background: transparent !important;
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

  .card table tbody tr:hover {
    background: var(--muted, #f9f9f9) !important;
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
    min-width    : 60px !important;
    max-width    : 60px !important;
    flex-shrink  : 0 !important;
  }

  /* User cell — no label, stacked */
  .card table td:first-child {
    flex-direction: column !important;
    align-items   : flex-start !important;
    gap           : 0 !important;
    padding-bottom: 10px !important;
    font-weight   : 700 !important;
    font-size     : 1rem !important;
  }

  .card table td:first-child::before {
    display: none !important;
  }

  /* Detail cell wraps text */
  .card table td[data-label="Detail"] {
    align-items: flex-start !important;
  }
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
      <td style="padding:.75rem 1rem" data-label="Action"><span class="badge badge-info"><?= e($log['action']) ?></span></td>
      <td style="padding:.75rem 1rem" data-label="Detail"><?= e($log['detail'] ?? '—') ?></td>
      <td style="padding:.75rem 1rem" data-label="IP"><?= e($log['ip_address'] ?? '—') ?></td>
      <td style="padding:.75rem 1rem" data-label="Date"><?= !empty($log['created_at']) ? date('M j, Y H:i', strtotime($log['created_at'])) : '—' ?></td>
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