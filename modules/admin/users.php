<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
session_start_once();
require_login(BASE_URL . '/login.php');
require_role('admin');

$user       = current_user();
$activePage = 'users';
$pageTitle  = 'Manage Users';

$activeTab = clean($_GET['tab'] ?? 'users');
$validTabs = ['users', 'adopters', 'rescue_orgs'];
if (!in_array($activeTab, $validTabs, true)) {
    $activeTab = 'users';
}

// POST handler 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action   = $_POST['action']  ?? '';
    $targetId = (int)($_POST['user_id'] ?? 0);

    // Block / Unblock adopter (adopters tab)
    if ($action === 'block_adopter' && $targetId) {
        db()->prepare("UPDATE users SET is_active = 0 WHERE id = ? AND role = 'adopter'")->execute([$targetId]);
        try {
            db()->prepare(
                "INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)"
            )->execute([
                $targetId,
                'Account Suspended',
                'Your adopter account has been suspended by an administrator. Please contact support.',
                BASE_URL . '/contact.php',
            ]);
        } catch (Throwable) { /* non-fatal */ }
        log_activity('block_adopter', 'user', $targetId, 'Admin blocked adopter #' . $targetId);
        flash('success', 'Adopter account blocked.');
        redirect(BASE_URL . '/modules/admin/users.php?tab=adopters');
    }

    if ($action === 'unblock_adopter' && $targetId) {
        db()->prepare("UPDATE users SET is_active = 1 WHERE id = ? AND role = 'adopter'")->execute([$targetId]);
        try {
            db()->prepare(
                "INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)"
            )->execute([
                $targetId,
                'Account Restored',
                'Your adopter account has been reactivated. Welcome back!',
                BASE_URL . '/modules/adopter/profile.php',
            ]);
        } catch (Throwable) { /* non-fatal */ }
        log_activity('unblock_adopter', 'user', $targetId, 'Admin unblocked adopter #' . $targetId);
        flash('success', 'Adopter account unblocked.');
        redirect(BASE_URL . '/modules/admin/users.php?tab=adopters');
    }

    // Approve rescue org
    if ($action === 'approve_rescue' && $targetId) {
        // Detect which optional name columns exist so we never crash on unmigrated schema.
        // Covers both 'rescue_org' (new) and legacy 'staff' role values.
        try {
            $_approveCols    = db()->query("DESCRIBE users")->fetchAll(PDO::FETCH_COLUMN);
            $_approveExtras  = '';
            if (in_array('shelter_name',      $_approveCols, true)) $_approveExtras .= ', shelter_name';
            if (in_array('organization_name', $_approveCols, true)) $_approveExtras .= ', organization_name';

            $orgStmt = db()->prepare(
                "SELECT full_name, email{$_approveExtras}
                 FROM users
                 WHERE id = ? AND role IN ('rescue_org','staff')"
            );
            $orgStmt->execute([$targetId]);
            $orgRow = $orgStmt->fetch();
        } catch (PDOException $_ae) {
            error_log('approve_rescue fetch error: ' . $_ae->getMessage());
            $orgRow = false;
        }

        // Resolve the best available org/shelter name
        $orgName = null;
        if ($orgRow) {
            $orgName = (!empty($orgRow['shelter_name'])      ? $orgRow['shelter_name']      : null)
                    ?? (!empty($orgRow['organization_name']) ? $orgRow['organization_name'] : null)
                    ?? 'Your Organization';
        }

        if ($orgRow) {
            // Activate the account
            db()->prepare("UPDATE users SET is_active = 1 WHERE id = ?")->execute([$targetId]);

            // In-app notification for the org user
            try {
                db()->prepare(
                    "INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)"
                )->execute([
                    $targetId,
                    'Account Activated 🎉',
                    'Your rescue organization account has been approved and activated! You can now log in.',
                    BASE_URL . '/login.php',
                ]);
            } catch (Throwable) { /* non-fatal */ }

            // Send activation email — notifies the rescue org contact person
            try {
                send_activation_email(
                    $orgRow['email'],
                    $orgRow['full_name'],
                    $orgName
                );
            } catch (Throwable $_me) {
                error_log('Activation email failed: ' . $_me->getMessage());
                // Non-fatal — account is still activated even if email fails
            }

            log_activity('approve_rescue', 'user', $targetId, 'Admin approved rescue org: ' . $orgName);
            flash('success', 'Rescue organization approved and activation email sent.');
        } else {
            flash('error', 'Organization not found.');
        }
        redirect(BASE_URL . '/modules/admin/users.php?tab=rescue_orgs');
    }

    // Reject (delete) rescue org application
    if ($action === 'reject_rescue' && $targetId) {
        $orgStmt = db()->prepare("SELECT full_name, email FROM users WHERE id = ? AND role = 'rescue_org'");
        $orgStmt->execute([$targetId]);
        $orgRow = $orgStmt->fetch();

        if ($orgRow) {
            db()->prepare("DELETE FROM users WHERE id = ?")->execute([$targetId]);
            log_activity('reject_rescue', 'user', $targetId, 'Admin rejected rescue org application for user #' . $targetId);
            flash('success', 'Rescue organization application rejected and removed.');
        } else {
            flash('error', 'Organization not found.');
        }
        redirect(BASE_URL . '/modules/admin/users.php?tab=rescue_orgs');
    }

    // Toggle active / delete 
    if ($action === 'toggle' && $targetId) {
        $cur = db()->prepare("SELECT is_active FROM users WHERE id = ?");
        $cur->execute([$targetId]);
        $cur = $cur->fetchColumn();
        db()->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$cur ? 0 : 1, $targetId]);
        flash('success', 'User status updated.');
        redirect(BASE_URL . '/modules/admin/users.php?tab=users');
    }

    if ($action === 'delete' && $targetId && $targetId !== (int)$user['id']) {
        // Determine which tab to redirect back to
        $deleteStmt = db()->prepare("SELECT role FROM users WHERE id = ?");
        $deleteStmt->execute([$targetId]);
        $deletedUser = $deleteStmt->fetch();
        $redirectTab = 'users';
        if ($deletedUser) {
            if ($deletedUser['role'] === 'adopter') $redirectTab = 'adopters';
            elseif (in_array($deletedUser['role'], ['rescue_org','staff'], true)) $redirectTab = 'rescue_orgs';
        }
        db()->prepare("DELETE FROM users WHERE id = ?")->execute([$targetId]);
        flash('success', 'User deleted.');
        redirect(BASE_URL . '/modules/admin/users.php?tab=' . $redirectTab);
    }

    // Verify ID 
    if ($action === 'verify_id' && $targetId) {
        db()->prepare("
            UPDATE adopter_profiles
            SET id_verified = 1,
                id_status   = 'verified',
                id_reject_reason = NULL,
                updated_at  = NOW()
            WHERE user_id = ?
        ")->execute([$targetId]);

        // Notify the adopter
        db()->prepare("
            INSERT INTO notifications (user_id, title, message, link)
            VALUES (?, 'ID Verified ✅', 'Your valid ID has been verified. You can now apply for adoption!', ?)
        ")->execute([$targetId, BASE_URL . '/modules/adopter/profile.php']);

        log_activity('verify_id', 'user', $targetId, 'Admin verified ID for user #' . $targetId);
        flash('success', 'ID verified successfully.');
        redirect(BASE_URL . '/modules/admin/users.php?tab=adopters');
    }

    // Reject ID 
    if ($action === 'reject_id' && $targetId) {
        $reason = clean($_POST['reject_reason'] ?? '');
        if (!$reason) {
            flash('error', 'Please provide a rejection reason.');
            redirect(BASE_URL . '/modules/admin/users.php?tab=adopters');
        }
        db()->prepare("
            UPDATE adopter_profiles
            SET id_verified      = 0,
                id_status        = 'rejected',
                id_reject_reason = ?,
                updated_at       = NOW()
            WHERE user_id = ?
        ")->execute([$reason, $targetId]);

        // Notify the adopter
        db()->prepare("
            INSERT INTO notifications (user_id, title, message, link)
            VALUES (?, 'ID Rejected ', ?, ?)
        ")->execute([
            $targetId,
            'Your ID submission was rejected: ' . $reason . '. Please upload a new ID.',
            BASE_URL . '/modules/adopter/profile.php'
        ]);

        log_activity('reject_id', 'user', $targetId, 'Admin rejected ID: ' . $reason);
        flash('success', 'ID rejected and user notified.');
        redirect(BASE_URL . '/modules/admin/users.php?tab=adopters');
    }
}

// Fetch users list
$search = clean($_GET['q'] ?? '');

// Show ALL roles: admin, staff, adopter, rescue_org
$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = '(full_name LIKE ? OR email LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSQL = implode(' AND ', $where);
$stmt = db()->prepare("SELECT * FROM users WHERE $whereSQL ORDER BY created_at DESC");
$stmt->execute($params);
$users = $stmt->fetchAll();

// Fetch rescue org accounts — covers 'rescue_org' (new) AND legacy 'staff' role.
// Build SELECT dynamically so it never crashes on unmigrated schemas.
$_descCols   = db()->query("DESCRIBE users")->fetchAll(PDO::FETCH_COLUMN);
$_wantedCols = [
    'phone', 'shelter_name', 'organization_name',
    'organization_type', 'address', 'organization_address',
    'organization_website',
];
$_safeCols = array_filter($_wantedCols, fn($c) => in_array($c, $_descCols, true));
$_roSelect = 'id, full_name, email, role, is_active, created_at'
           . (!empty($_safeCols) ? ', ' . implode(', ', $_safeCols) : '');

try {
    $rescueOrgs = db()->query("
        SELECT {$_roSelect}
        FROM users
        WHERE role IN ('rescue_org', 'staff')
        ORDER BY is_active DESC, created_at DESC
    ")->fetchAll();
} catch (PDOException $_re) {
    error_log('rescueOrgs query error: ' . $_re->getMessage());
    $rescueOrgs = [];
}

// Normalise every row — all optional keys always exist, resolve alias columns
foreach ($rescueOrgs as &$_ro) {
    $_ro['shelter_name']         = (!empty($_ro['shelter_name'])         ? $_ro['shelter_name']         : null)
                                 ?? (!empty($_ro['organization_name'])    ? $_ro['organization_name']    : null)
                                 ?? null;
    $_ro['organization_type']    = $_ro['organization_type']    ?? null;
    $_ro['address']              = (!empty($_ro['address'])              ? $_ro['address']              : null)
                                 ?? (!empty($_ro['organization_address']) ? $_ro['organization_address'] : null)
                                 ?? null;
    $_ro['organization_website'] = $_ro['organization_website'] ?? null;
    $_ro['phone']                = $_ro['phone']                ?? null;
}
unset($_ro);

$pendingRescueCount = count(array_filter($rescueOrgs, fn($r) => !(int)$r['is_active']));

// Pre-fetch all adopter ID statuses in one query to avoid N+1 in the users table loop
$idStatusMap = [];
$idRows = db()->query("SELECT user_id, id_status FROM adopter_profiles")->fetchAll();
foreach ($idRows as $ir) {
    $idStatusMap[$ir['user_id']] = $ir['id_status'];
}

$idUploadUrl = BASE_URL . '/view-id.php?file=';

// Fetch adopters list with their profile data (for Adopters tab)
$adopterSearch = clean($_GET['aq'] ?? '');
$adopterStatus = clean($_GET['astatus'] ?? '');    // 'active', 'inactive', '' = all
$adopterIdSt   = clean($_GET['aid'] ?? '');        // 'pending','verified','rejected','none','' = all

$aWhere  = ["u.role = 'adopter'"];
$aParams = [];

if ($adopterSearch !== '') {
    $aWhere[]  = '(u.full_name LIKE ? OR u.email LIKE ?)';
    $aParams[] = "%$adopterSearch%";
    $aParams[] = "%$adopterSearch%";
}
if ($adopterStatus === 'active')   { $aWhere[] = 'u.is_active = 1'; }
if ($adopterStatus === 'inactive') { $aWhere[] = 'u.is_active = 0'; }
if ($adopterIdSt !== '') {
    if ($adopterIdSt === 'none') {
        $aWhere[] = 'ap.user_id IS NULL';
    } else {
        $aWhere[]  = 'ap.id_status = ?';
        $aParams[] = $adopterIdSt;
    }
}

$aWhereSQL = implode(' AND ', $aWhere);

try {
    $adopterStmt = db()->prepare("
        SELECT u.id, u.full_name, u.email, u.is_active, u.created_at,
               ap.id_status, ap.id_type, ap.id_uploaded_at,
               ap.id_verified, ap.id_reject_reason, ap.id_document
        FROM users u
        LEFT JOIN adopter_profiles ap ON ap.user_id = u.id
        WHERE $aWhereSQL
        ORDER BY u.created_at DESC
    ");
    $adopterStmt->execute($aParams);
    $adopters = $adopterStmt->fetchAll();
} catch (PDOException $_ae) {
    error_log('adopters fetch error: ' . $_ae->getMessage());
    $adopters = [];
}

$totalAdopters    = count($adopters);
$activeAdopters   = count(array_filter($adopters, fn($a) => (int)$a['is_active'] === 1));
$inactiveAdopters = $totalAdopters - $activeAdopters;
$verifiedIds      = count(array_filter($adopters, fn($a) => ($a['id_status'] ?? '') === 'verified'));
$pendingAdopterId = count(array_filter($adopters, fn($a) => ($a['id_status'] ?? '') === 'pending'));

include __DIR__ . '/../../includes/dash-head.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<style>
/* Layout maximization */
.main-body {
  max-width: 100% !important;
  padding: 2rem 2.5rem 3rem;
}
/* Tabs  */
.admin-tabs {
  display: flex; gap: .25rem; border-bottom: 2px solid var(--border);
  margin-bottom: 1.5rem; overflow-x: auto; -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
}
.admin-tabs::-webkit-scrollbar { display: none; }
.admin-tab {
  padding: .6rem 1.2rem; font-size: .87rem; font-weight: 700;
  cursor: pointer; text-decoration: none;
  color: var(--muted-fg); border-bottom: 3px solid transparent;
  margin-bottom: -2px; border-radius: 6px 6px 0 0;
  transition: color .15s, border-color .15s;
  display: inline-flex; align-items: center; gap: .4rem;
  white-space: nowrap; flex-shrink: 0;
}
.admin-tab:hover  { color: var(--primary); }
.admin-tab.active { color: var(--primary); border-bottom-color: var(--primary); }
.tab-badge {
  background: var(--danger); color: #fff;
  border-radius: 999px; font-size: .68rem; font-weight: 800;
  padding: .05rem .45rem; min-width: 18px; text-align: center;
}

/* Action cell */
.action-cell {
  display: flex; gap: .5rem; align-items: center; flex-wrap: wrap;
}

/* ID review card */
.id-review-grid {
  display: flex; flex-direction: column; gap: 1rem;
}
.id-review-card {
  border: 1.5px solid var(--border); border-radius: 14px;
  padding: .85rem 1.1rem; background: var(--card);
  display: flex; align-items: center; gap: .9rem; flex-wrap: wrap;
}
.id-review-card.is-pending  { border-left: 4px solid hsl(38 85% 55%); }
.id-review-card.is-verified { border-left: 4px solid var(--success); }
.id-review-card.is-rejected { border-left: 4px solid var(--danger); }

.id-rv__avatar {
  width: 44px; height: 44px; border-radius: 50%;
  background: var(--primary); color: #fff;
  display: grid; place-items: center; font-weight: 800;
  font-size: 1.1rem; flex-shrink: 0;
}
.id-rv__info { flex: 1; min-width: 160px; }
.id-rv__name { font-weight: 800; font-size: .95rem; }
.id-rv__meta { font-size: .78rem; color: var(--muted-fg); margin-top: .1rem; }

.id-status-pill {
  display: inline-flex; align-items: center; gap: .35rem;
  padding: .3rem .75rem; border-radius: 999px;
  font-size: .74rem; font-weight: 700; white-space: nowrap;
}
.pill-pending  { background: hsl(38 95% 90%);  color: hsl(38 80% 35%); }
.pill-verified { background: hsl(145 55% 88%); color: hsl(145 42% 27%); }
.pill-rejected { background: hsl(0 80% 93%);   color: hsl(0 60% 40%); }

.id-rv__actions { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }

/* Reject modal */
.modal-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.45); z-index: 9999;
  align-items: center; justify-content: center; padding: 1rem;
}
.modal-overlay.open { display: flex; }
.modal-box {
  background: #fff; border-radius: 16px;
  padding: 2rem; width: 100%; max-width: 440px;
  box-shadow: 0 24px 64px rgba(0,0,0,.18);
  max-height: 90vh; overflow-y: auto; -webkit-overflow-scrolling: touch;
}
.modal-box h3 { margin: 0 0 1rem; font-size: 1.1rem; }
.modal-box textarea {
  width: 100%; resize: vertical; min-height: 90px;
  border: 1.5px solid var(--border); border-radius: 10px;
  padding: .7rem; font: inherit; font-size: 16px; /* prevent iOS zoom */
  margin-bottom: 1rem; outline: none; box-sizing: border-box;
}
.modal-box textarea:focus { border-color: var(--primary); }
.modal-actions { display: flex; gap: .6rem; justify-content: flex-end; }

/* ── Responsive ── */
@media (max-width: 768px) {
  .main-body { padding: 1.25rem 1.1rem 2.5rem; }
  .stat-cards-grid { grid-template-columns: repeat(2, 1fr) !important; }
}
@media (max-width: 640px) {
  .main-body { padding: 1rem .85rem 2rem; }

  /* ── Table → card transform (mirrors dashboard.php) ── */
  .users-table-wrap table thead { display: none !important; }

  .users-table-wrap table,
  .users-table-wrap tbody,
  .users-table-wrap tr,
  .users-table-wrap td {
    display: block !important;
    width: 100% !important;
    table-layout: auto !important;
  }

  /* Each row = a card */
  .users-table-wrap tbody tr {
    background: var(--card) !important;
    border: 1px solid var(--border) !important;
    border-radius: 12px !important;
    margin: 0 0 .75rem !important;
    padding: 12px 14px 6px !important;
    box-shadow: 0 1px 3px rgba(0,0,0,.06) !important;
    box-sizing: border-box !important;
  }

  /* Each cell: label (::before) + value side-by-side */
  .users-table-wrap td {
    padding: 7px 0 !important;
    font-size: .88rem !important;
    overflow: visible !important;
    text-overflow: unset !important;
    white-space: normal !important;
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
    border-bottom: 1px solid var(--border) !important;
    box-sizing: border-box !important;
  }

  .users-table-wrap td:last-child { border-bottom: none !important; }

  /* data-label pseudo */
  .users-table-wrap td::before {
    content: attr(data-label);
    font-size: .7rem !important;
    font-weight: 700 !important;
    letter-spacing: .06em !important;
    text-transform: uppercase !important;
    color: var(--muted-fg) !important;
    min-width: 72px !important;
    max-width: 72px !important;
    flex-shrink: 0 !important;
  }

  /* First cell (name/email): stacked, no label */
  .users-table-wrap td:first-child {
    flex-direction: column !important;
    align-items: flex-start !important;
    gap: 2px !important;
    padding-bottom: 10px !important;
  }
  .users-table-wrap td:first-child::before { display: none !important; }
  .users-table-wrap td:first-child .td-name { font-weight: 700 !important; font-size: 1rem !important; }
  .users-table-wrap td:first-child .td-meta { font-size: .8rem !important; color: var(--muted-fg) !important; }

  /* Last cell (actions): full-width buttons */
  .users-table-wrap td:last-child::before { display: none !important; }
  .users-table-wrap .action-cell { flex-direction: column !important; flex-wrap: wrap !important; width: 100% !important; }
  .users-table-wrap .action-cell form { width: 100% !important; min-width: 0 !important; }
  .users-table-wrap .action-cell .btn { width: 100% !important; justify-content: center !important; }

  /* Rescue org / id-review cards */
  .id-review-card { flex-direction: column; }
  .id-rv__actions { width: 100%; }
  .id-rv__actions form { flex: 1 1 auto; min-width: 0; }
  .id-rv__actions .btn { width: 100%; justify-content: center; }
  .id-status-pill { align-self: flex-start; }

  /* Modal */
  .modal-overlay { padding: 0; align-items: flex-end; }
  .modal-box { border-radius: 16px 16px 0 0; max-height: 90vh; }
  .modal-actions { flex-direction: column-reverse; }
  .modal-actions .btn { width: 100%; justify-content: center; }
}
</style>

<div class="main-content">
<?php include __DIR__ . '/../../includes/topbar.php'; ?>
<div class="main-body">

<?php $flash = get_flash('success'); if ($flash): ?>
<div class="alert alert-success" data-auto-dismiss><i class="fa-solid fa-check"></i><?= e($flash) ?></div>
<?php endif; ?>
<?php $ferr = get_flash('error'); if ($ferr): ?>
<div class="alert alert-danger" data-auto-dismiss><i class="fa-solid fa-circle-exclamation"></i><?= e($ferr) ?></div>
<?php endif; ?>

<div class="page-header">
  <h1>Manage Users</h1>
</div>

<!-- Tabs -->
<div class="admin-tabs">
  <a class="admin-tab <?= $activeTab === 'users' ? 'active' : '' ?>"
      href="?tab=users">
    <i class="fa-solid fa-users"></i> All Users
  </a>
  <a class="admin-tab <?= $activeTab === 'adopters' ? 'active' : '' ?>"
      href="?tab=adopters">
    <i class="fa-solid fa-heart"></i> Adopters
    <?php if ($pendingAdopterId > 0): ?>
      <span class="tab-badge"><?= $pendingAdopterId ?></span>
    <?php endif; ?>
  </a>
  <a class="admin-tab <?= $activeTab === 'rescue_orgs' ? 'active' : '' ?>"
      href="?tab=rescue_orgs">
    <i class="fa-solid fa-house-chimney-medical"></i> Rescue Organizations
    <?php if ($pendingRescueCount > 0): ?>
      <span class="tab-badge"><?= $pendingRescueCount ?></span>
    <?php endif; ?>
  </a>
</div>

<?php if ($activeTab === 'users'): ?>

<form method="get" style="margin-bottom:1.5rem">
  <input type="hidden" name="tab" value="users">
  <div style="display:flex;gap:.75rem;flex-wrap:wrap">
    <input name="q" type="text" placeholder="Search name or email…"
            value="<?= e($search) ?>" style="flex:1;min-width:0;box-sizing:border-box">
    <button class="btn btn-secondary" type="submit" style="white-space:nowrap">Search</button>
  </div>
</form>

<!-- All Users table -->
<div class="card users-table-wrap">
  <table style="width:100%;border-collapse:collapse">
    <thead>
      <tr style="background:var(--muted);text-align:left">
        <th style="padding:.75rem 1rem">Name</th>
        <th style="padding:.75rem 1rem">Email</th>
        <th style="padding:.75rem 1rem">Role</th>
        <th style="padding:.75rem 1rem">Status</th>
        <th style="padding:.75rem 1rem">ID Status</th>
        <th style="padding:.75rem 1rem">Joined</th>
        <th style="padding:.75rem 1rem;text-align:center">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($users as $u):
      $idSt  = $idStatusMap[$u['id']] ?? 'none';
      $idBadges = [
        'none'     => '<span class="badge badge-muted">No ID</span>',
        'pending'  => '<span class="badge badge-warning">Pending</span>',
        'verified' => '<span class="badge badge-success">Verified</span>',
        'rejected' => '<span class="badge badge-danger">Rejected</span>',
      ];
      $isRescue     = in_array($u['role'], ['rescue_org','staff'], true);
      $_shelterName = (!empty($u['shelter_name'])      ? $u['shelter_name']      : null)
                   ?? (!empty($u['organization_name']) ? $u['organization_name'] : null)
                   ?? null;
      $roleLabel = match($u['role']) {
        'admin'      => '<span class="badge" style="background:#fef2f2;color:#b91c1c;border:1px solid #fecaca">Admin</span>',
        'staff'      => '<span class="badge" style="background:#f5f3ff;color:#6d28d9;border:1px solid #ddd6fe">Staff</span>',
        'rescue_org' => '<span class="badge" style="background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe">Rescue Org</span>',
        'adopter'    => '<span class="badge" style="background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0">Adopter</span>',
        default      => '<span class="badge badge-muted">' . e($u['role']) . '</span>',
      };
    ?>
    <tr style="border-bottom:1px solid var(--border)">
      <td style="padding:.75rem 1rem">
        <div class="td-name"><?= e($u['full_name']) ?></div>
        <?php if ($isRescue && $_shelterName): ?>
          <div class="td-meta"><?= e($_shelterName) ?></div>
        <?php endif; ?>
      </td>
      <td style="padding:.75rem 1rem" data-label="Email"><?= e($u['email']) ?></td>
      <td style="padding:.75rem 1rem" data-label="Role"><?= $roleLabel ?></td>
      <td style="padding:.75rem 1rem" data-label="Status">
        <?= $u['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-warning">Pending</span>' ?>
      </td>
      <td style="padding:.75rem 1rem" data-label="ID">
        <?= in_array($u['role'], ['adopter'], true) ? ($idBadges[$idSt] ?? $idBadges['none']) : '<span class="muted small">—</span>' ?>
      </td>
      <td style="padding:.75rem 1rem" data-label="Joined">
        <?= !empty($u['created_at']) ? date('M j, Y', strtotime($u['created_at'])) : '—' ?>
      </td>
      <td style="padding:.75rem 1rem" data-label="Actions"><div class="action-cell" style="justify-content:center">
        <?php if ((int)$u['id'] === (int)$user['id']): ?>
        <span class="muted small">You</span>
        <?php elseif ($u['role'] === 'admin'): ?>
        <span class="muted small">—</span>
        <?php else: ?>
        <form method="post" style="margin:0">
          <?= csrf_field() ?>
          <input type="hidden" name="action"  value="toggle">
          <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
          <button class="btn btn-sm btn-secondary" type="submit">
            <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
          </button>
        </form>
        <form method="post" style="margin:0">
          <?= csrf_field() ?>
          <input type="hidden" name="action"  value="delete">
          <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
          <button class="btn btn-sm btn-danger" type="submit"
                  data-confirm="Delete this user?">
            <i class="fa-solid fa-trash"></i> Delete
          </button>
        </form>
        <?php endif; ?>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$users): ?>
    <tr><td colspan="7" style="padding:2rem;text-align:center" class="muted">No users found.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php endif; /* tab=users */ ?>

<?php if ($activeTab === 'adopters'): ?>

<!-- Summary stats — dashboard style -->
<p class="section-label" style="font-size:.68rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--muted-fg);margin-bottom:.75rem">Adopter Overview</p>
<div class="stat-cards-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:.75rem;margin-bottom:1.5rem">

  <div class="stat-card" style="background:var(--card);border:1px solid var(--border);border-radius:14px;padding:1.1rem 1.2rem;display:flex;flex-direction:column;gap:.9rem;box-shadow:0 1px 3px rgba(0,0,0,.06)">
    <div style="width:38px;height:38px;border-radius:8px;background:#f5f3ff;color:#7c3aed;border:1px solid #ddd6fe;display:flex;align-items:center;justify-content:center;font-size:.95rem">
      <i class="fa-solid fa-users"></i>
    </div>
    <div>
      <div style="font-size:1.9rem;font-weight:700;line-height:1;color:var(--text-primary, #111827)"><?= $totalAdopters ?></div>
      <div style="font-size:.82rem;font-weight:500;color:var(--muted-fg);margin-top:.25rem">Total Adopters</div>
    </div>
  </div>

  <div class="stat-card" style="background:var(--card);border:1px solid var(--border);border-radius:14px;padding:1.1rem 1.2rem;display:flex;flex-direction:column;gap:.9rem;box-shadow:0 1px 3px rgba(0,0,0,.06)">
    <div style="width:38px;height:38px;border-radius:8px;background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;display:flex;align-items:center;justify-content:center;font-size:.95rem">
      <i class="fa-solid fa-circle-check"></i>
    </div>
    <div>
      <div style="font-size:1.9rem;font-weight:700;line-height:1;color:var(--text-primary, #111827)"><?= $activeAdopters ?></div>
      <div style="font-size:.82rem;font-weight:500;color:var(--muted-fg);margin-top:.25rem">Active</div>
    </div>
  </div>

  <div class="stat-card" style="background:var(--card);border:1px solid var(--border);border-radius:14px;padding:1.1rem 1.2rem;display:flex;flex-direction:column;gap:.9rem;box-shadow:0 1px 3px rgba(0,0,0,.06)">
    <div style="width:38px;height:38px;border-radius:8px;background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;display:flex;align-items:center;justify-content:center;font-size:.95rem">
      <i class="fa-solid fa-id-card-clip"></i>
    </div>
    <div>
      <div style="font-size:1.9rem;font-weight:700;line-height:1;color:var(--text-primary, #111827)"><?= $verifiedIds ?></div>
      <div style="font-size:.82rem;font-weight:500;color:var(--muted-fg);margin-top:.25rem">Verified IDs</div>
    </div>
  </div>

  <div class="stat-card" style="background:var(--card);border:1px solid var(--border);border-radius:14px;padding:1.1rem 1.2rem;display:flex;flex-direction:column;gap:.9rem;box-shadow:0 1px 3px rgba(0,0,0,.06)">
    <div style="width:38px;height:38px;border-radius:8px;background:#fff7ed;color:#ea580c;border:1px solid #fed7aa;display:flex;align-items:center;justify-content:center;font-size:.95rem">
      <i class="fa-solid fa-clock"></i>
    </div>
    <div>
      <div style="font-size:1.9rem;font-weight:700;line-height:1;color:var(--text-primary, #111827)"><?= $pendingAdopterId ?></div>
      <div style="font-size:.82rem;font-weight:500;color:var(--muted-fg);margin-top:.25rem">Pending ID Review</div>
    </div>
  </div>

</div>

<!-- Filters -->
<form method="get" style="margin-bottom:1.5rem">
  <input type="hidden" name="tab" value="adopters">
  <div style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
    <div style="flex:2;min-width:180px">
      <label style="font-size:.78rem;font-weight:700;display:block;margin-bottom:.3rem">Search</label>
      <input name="aq" type="text" placeholder="Name or email…" value="<?= e($adopterSearch) ?>" style="width:100%;box-sizing:border-box">
    </div>
    <div style="flex:1;min-width:130px">
      <label style="font-size:.78rem;font-weight:700;display:block;margin-bottom:.3rem">Account Status</label>
      <select name="astatus" style="width:100%;box-sizing:border-box">
        <option value=""       <?= $adopterStatus === ''         ? 'selected' : '' ?>>All</option>
        <option value="active" <?= $adopterStatus === 'active'   ? 'selected' : '' ?>>Active</option>
        <option value="inactive" <?= $adopterStatus === 'inactive' ? 'selected' : '' ?>>Blocked</option>
      </select>
    </div>
    <div style="flex:1;min-width:130px">
      <label style="font-size:.78rem;font-weight:700;display:block;margin-bottom:.3rem">ID Status</label>
      <select name="aid" style="width:100%;box-sizing:border-box">
        <option value=""         <?= $adopterIdSt === ''         ? 'selected' : '' ?>>All</option>
        <option value="pending"  <?= $adopterIdSt === 'pending'  ? 'selected' : '' ?>>Pending</option>
        <option value="verified" <?= $adopterIdSt === 'verified' ? 'selected' : '' ?>>Verified</option>
        <option value="rejected" <?= $adopterIdSt === 'rejected' ? 'selected' : '' ?>>Rejected</option>
        <option value="none"     <?= $adopterIdSt === 'none'     ? 'selected' : '' ?>>No ID</option>
      </select>
    </div>
    <div style="display:flex;gap:.5rem;flex-shrink:0">
      <button class="btn btn-secondary" type="submit">Filter</button>
      <a class="btn btn-ghost" href="?tab=adopters">Reset</a>
    </div>
  </div>
</form>

<?php if (empty($adopters)): ?>
<div class="empty-state">
  <i class="fa-solid fa-heart"></i>
  <h3>No adopters found</h3>
  <p class="muted">No adopter accounts match your current filters.</p>
</div>
<?php else: ?>

<!-- Adopters table -->
<div class="card users-table-wrap">
  <table style="width:100%;border-collapse:collapse">
    <thead>
      <tr style="background:var(--muted);text-align:left">
        <th style="padding:.75rem 1rem">Adopter</th>
        <th style="padding:.75rem 1rem">Email</th>
        <th style="padding:.75rem 1rem">Account</th>
        <th style="padding:.75rem 1rem">ID Status</th>
        <th style="padding:.75rem 1rem">ID Type</th>
        <th style="padding:.75rem 1rem">Joined</th>
        <th style="padding:.75rem 1rem;text-align:center">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($adopters as $a):
      $aIdSt     = $a['id_status'] ?? null;
      $aIsActive = (int)$a['is_active'] === 1;

      $aIdBadge = match($aIdSt) {
        'pending'  => '<span class="badge badge-warning"><i class="fa-solid fa-clock" style="font-size:.65rem"></i> Pending</span>',
        'verified' => '<span class="badge badge-success"><i class="fa-solid fa-circle-check" style="font-size:.65rem"></i> Verified</span>',
        'rejected' => '<span class="badge badge-danger"><i class="fa-solid fa-circle-xmark" style="font-size:.65rem"></i> Rejected</span>',
        default    => '<span class="badge badge-muted">No ID</span>',
      };
      $aAccBadge = $aIsActive
        ? '<span class="badge badge-success">Active</span>'
        : '<span class="badge badge-warning">Blocked</span>';
    ?>
    <tr style="border-bottom:1px solid var(--border)">

      <!-- Name + avatar initial -->
      <td style="padding:.75rem 1rem">
        <div style="display:flex;align-items:center;gap:.65rem">
          <div style="width:34px;height:34px;border-radius:50%;background:var(--primary);color:#fff;
                      display:grid;place-items:center;font-weight:800;font-size:.85rem;flex-shrink:0">
            <?= strtoupper(mb_substr($a['full_name'], 0, 1)) ?>
          </div>
          <span class="td-name" style="font-weight:600"><?= e($a['full_name']) ?></span>
        </div>
      </td>

      <td style="padding:.75rem 1rem" data-label="Email"><?= e($a['email']) ?></td>

      <td style="padding:.75rem 1rem" data-label="Account"><?= $aAccBadge ?></td>

      <td style="padding:.75rem 1rem" data-label="ID">
        <?= $aIdBadge ?>
        <?php if ($aIdSt === 'rejected' && !empty($a['id_reject_reason'])): ?>
          <div style="font-size:.72rem;color:hsl(0 55% 40%);margin-top:.2rem;max-width:180px">
            <?= e($a['id_reject_reason']) ?>
          </div>
        <?php endif; ?>
      </td>

      <td style="padding:.75rem 1rem" data-label="ID Type">
        <?= !empty($a['id_type']) ? e($a['id_type']) : '<span class="muted small">—</span>' ?>
      </td>

      <td style="padding:.75rem 1rem" data-label="Joined">
        <?= !empty($a['created_at']) ? date('M j, Y', strtotime($a['created_at'])) : '—' ?>
      </td>

      <!-- Actions -->
      <td style="padding:.75rem 1rem" data-label="Actions">
        <div class="action-cell" style="justify-content:center">

          <!-- Eye icon: only show if there is an uploaded ID document -->
          <?php if (!empty($a['id_document'])): ?>
          <?php
            $aExt   = strtolower(pathinfo($a['id_document'], PATHINFO_EXTENSION));
            $aIsImg = in_array($aExt, ['jpg','jpeg','png','webp']);
          ?>
          <a href="<?= e($idUploadUrl . urlencode($a['id_document'])) ?>" target="_blank"
             class="btn btn-sm btn-secondary" title="View submitted ID">
            <i class="fa-solid fa-eye"></i> View
          </a>
          <?php endif; ?>

          <?php if ($aIdSt === 'verified'): ?>
            <!-- ID is verified: Eye + Delete only -->
            <form method="post" style="margin:0">
              <?= csrf_field() ?>
              <input type="hidden" name="action"  value="delete">
              <input type="hidden" name="user_id" value="<?= (int)$a['id'] ?>">
              <button class="btn btn-sm btn-danger" type="submit"
                      data-confirm="Permanently delete this adopter? This cannot be undone.">
                <i class="fa-solid fa-trash"></i> Delete
              </button>
            </form>

          <?php else: ?>
            <!-- ID is pending or rejected: Reject ID + Verify ID + Delete -->
            <?php if ($aIdSt === 'pending' || $aIdSt === 'rejected'): ?>
            <button class="btn btn-sm btn-danger"
                    onclick="openRejectModal(<?= (int)$a['id'] ?>)"
                    title="Reject ID">
              <i class="fa-solid fa-xmark"></i> Reject ID
            </button>

            <form method="post" style="margin:0">
              <?= csrf_field() ?>
              <input type="hidden" name="action"  value="verify_id">
              <input type="hidden" name="user_id" value="<?= (int)$a['id'] ?>">
              <button class="btn btn-sm btn-success" type="submit"
                      data-confirm="Mark this ID as verified?"
                      title="Verify ID">
                <i class="fa-solid fa-id-card-clip"></i> Verify ID
              </button>
            </form>
            <?php endif; ?>

            <!-- Delete -->
            <form method="post" style="margin:0">
              <?= csrf_field() ?>
              <input type="hidden" name="action"  value="delete">
              <input type="hidden" name="user_id" value="<?= (int)$a['id'] ?>">
              <button class="btn btn-sm btn-danger" type="submit"
                      data-confirm="Permanently delete this adopter? This cannot be undone.">
                <i class="fa-solid fa-trash"></i> Delete
              </button>
            </form>

          <?php endif; /* verified vs not */ ?>

        </div>
      </td>

    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php endif; /* empty adopters check */ ?>
<?php endif; /* tab=adopters */ ?>

<?php if ($activeTab === 'rescue_orgs'): ?>

<?php if (empty($rescueOrgs)): ?>
<div class="empty-state">
  <i class="fa-solid fa-house-chimney-medical"></i>
  <h3>No rescue organizations yet</h3>
  <p class="muted">When rescue organizations register, they will appear here for review.</p>
</div>
<?php else: ?>

<div class="id-review-grid">
<?php foreach ($rescueOrgs as $org):
  $isPending = !(int)$org['is_active'];
  $cardCls   = $isPending ? 'is-pending' : 'is-verified';
  $pillCls   = $isPending ? 'pill-pending' : 'pill-verified';
  $pillIcon  = $isPending ? 'fa-clock' : 'fa-circle-check';
  $pillLabel = $isPending ? 'Pending Approval' : 'Active';
?>
<div class="id-review-card <?= $cardCls ?>">

  <!-- Avatar -->
  <div class="id-rv__avatar" style="background:var(--primary)">
    <i class="fa-solid fa-house-chimney-medical" style="font-size:.9rem"></i>
  </div>

  <!-- Info -->
  <div class="id-rv__info" style="flex:1;min-width:200px">
    <div class="id-rv__name"><?= e($org['shelter_name'] ?? '—') ?></div>
    <div class="id-rv__meta">
      <strong><?= e($org['full_name']) ?></strong> &middot; <?= e($org['email']) ?>
      <?php if (!empty($org['phone'])): ?>
        &middot; <?= e($org['phone']) ?>
      <?php endif; ?>
    </div>
    <?php if (!empty($org['organization_type'])): ?>
    <div class="id-rv__meta" style="margin-top:.2rem">
      <i class="fa-solid fa-tag" style="opacity:.55"></i>
      <?= e($org['organization_type']) ?>
      <?php if (!empty($org['address'])): ?>
        &middot; <i class="fa-solid fa-location-dot" style="opacity:.55"></i>
        <?= e($org['address']) ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($org['organization_website'])): ?>
    <div class="id-rv__meta" style="margin-top:.2rem">
      <i class="fa-solid fa-globe" style="opacity:.55"></i>
      <a href="<?= e($org['organization_website']) ?>" target="_blank"
         style="color:var(--primary)"><?= e($org['organization_website']) ?></a>
    </div>
    <?php endif; ?>
    <div class="id-rv__meta" style="margin-top:.25rem">
      Registered <?= !empty($org['created_at']) ? date('M j, Y', strtotime($org['created_at'])) : '—' ?>
    </div>
  </div>

  <!-- Status pill -->
  <span class="id-status-pill <?= $pillCls ?>">
    <i class="fa-solid <?= $pillIcon ?>"></i>
    <?= $pillLabel ?>
  </span>

  <!-- Actions -->
  <div class="id-rv__actions">

    <?php if ($isPending): ?>
    <!-- Approve -->
    <form method="post" style="margin:0">
      <?= csrf_field() ?>
      <input type="hidden" name="action"  value="approve_rescue">
      <input type="hidden" name="user_id" value="<?= (int)$org['id'] ?>">
      <button class="btn btn-sm btn-success" type="submit"
              data-confirm="Approve and activate this rescue organization?">
        <i class="fa-solid fa-check"></i> Approve
      </button>
    </form>

    <!-- Reject (delete application) -->
    <form method="post" style="margin:0">
      <?= csrf_field() ?>
      <input type="hidden" name="action"  value="reject_rescue">
      <input type="hidden" name="user_id" value="<?= (int)$org['id'] ?>">
      <button class="btn btn-sm btn-danger" type="submit"
              data-confirm="Reject and permanently delete this application? This cannot be undone.">
        <i class="fa-solid fa-xmark"></i> Reject
      </button>
    </form>

    <?php else: ?>
    <!-- Delete active org -->
    <form method="post" style="margin:0">
      <?= csrf_field() ?>
      <input type="hidden" name="action"  value="delete">
      <input type="hidden" name="user_id" value="<?= (int)$org['id'] ?>">
      <button class="btn btn-sm btn-danger" type="submit"
              data-confirm="Permanently delete this rescue organization? This cannot be undone.">
        <i class="fa-solid fa-trash"></i> Delete
      </button>
    </form>
    <?php endif; ?>

  </div>

</div>
<?php endforeach; ?>
</div>

<?php endif; /* empty check */ ?>
<?php endif; /* tab=rescue_orgs */ ?>

</div>
</div>

<!-- Reject Reason Modal -->
<div class="modal-overlay" id="rejectModal">
  <div class="modal-box">
    <h3><i class="fa-solid fa-circle-xmark" style="color:var(--danger);margin-right:.4rem"></i>Reject ID Submission</h3>
    <form method="post" id="rejectForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action"  value="reject_id">
      <input type="hidden" name="user_id" id="rejectUserId" value="">
      <label style="font-size:.82rem;font-weight:700;display:block;margin-bottom:.4rem">
        Reason for rejection <span style="color:var(--danger)">*</span>
      </label>
      <textarea name="reject_reason" placeholder="e.g. ID is blurry, expired, or unreadable…" required></textarea>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">Cancel</button>
        <button type="submit" class="btn btn-danger"><i class="fa-solid fa-xmark"></i> Reject</button>
      </div>
    </form>
  </div>
</div>

<script>
function openRejectModal(userId) {
  document.getElementById('rejectUserId').value = userId;
  document.getElementById('rejectModal').classList.add('open');
}
function closeRejectModal() {
  document.getElementById('rejectModal').classList.remove('open');
  document.getElementById('rejectForm').querySelector('textarea').value = '';
}
// Close on backdrop click
document.getElementById('rejectModal').addEventListener('click', function(e) {
  if (e.target === this) closeRejectModal();
});
</script>

<?php include __DIR__ . '/../../includes/dash-foot.php'; ?>