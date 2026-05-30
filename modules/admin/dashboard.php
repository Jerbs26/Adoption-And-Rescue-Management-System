<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
session_start_once();
require_login(BASE_URL . '/login.php');
require_role('admin');

$user       = current_user();
$activePage = 'dashboard';
$pageTitle  = 'Dashboard';

// ── Account stats ──────────────────────────────────────────────
$totalAdopters = (int)db()->query("SELECT COUNT(*) FROM users WHERE role = 'adopter'")->fetchColumn();

try {
    $totalRescueOrgs = (int)db()->query("SELECT COUNT(*) FROM users WHERE role IN ('rescue_org','staff')")->fetchColumn();
} catch (PDOException $e) { $totalRescueOrgs = 0; }

try {
    $pendingRescueApprovals = (int)db()->query("SELECT COUNT(*) FROM users WHERE role IN ('rescue_org','staff') AND is_active = 0")->fetchColumn();
} catch (PDOException $e) { $pendingRescueApprovals = 0; }

try {
    $pendingIdVerifications = (int)db()->query("SELECT COUNT(*) FROM adopter_profiles WHERE id_status = 'pending'")->fetchColumn();
} catch (PDOException $e) { $pendingIdVerifications = 0; }

try {
    $verifiedIds = (int)db()->query("SELECT COUNT(*) FROM adopter_profiles WHERE id_status = 'verified'")->fetchColumn();
} catch (PDOException $e) { $verifiedIds = 0; }

try {
    $recentJoined = (int)db()->query("SELECT COUNT(*) FROM users WHERE role IN ('adopter','rescue_org','staff') AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
} catch (PDOException $e) { $recentJoined = 0; }

// ── Account Management table data ──────────────────────────────
$_existingCols = [];
try {
    foreach (db()->query("DESCRIBE users")->fetchAll(PDO::FETCH_ASSOC) as $_col) {
        $_existingCols[] = strtolower($_col['Field']);
    }
} catch (PDOException $e) {}

$_rescueCols       = ['shelter_name', 'sec_registration_no', 'address', 'phone', 'organization_name', 'organization_type', 'organization_address'];
$_selectRescueCols = array_filter($_rescueCols, fn($c) => in_array(strtolower($c), $_existingCols, true));

$_baseSelect  = "id, full_name, email, role, is_verified, is_active, created_at";
$_extraSelect = !empty($_selectRescueCols) ? ', ' . implode(', ', $_selectRescueCols) : '';
$_accountsSQL = "
    SELECT {$_baseSelect}{$_extraSelect}
    FROM users
    WHERE role IN ('adopter', 'rescue_org', 'staff')
    ORDER BY created_at DESC
";

try {
    $allAccounts = db()->query($_accountsSQL)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $allAccounts = []; }

foreach ($allAccounts as &$_row) {
    foreach ($_rescueCols as $_rc) {
        if (!array_key_exists($_rc, $_row)) $_row[$_rc] = null;
    }
}
unset($_row);

include __DIR__ . '/../../includes/dash-head.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<style>
  /* Google Font import */
  @import url('https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap');

  /* Design tokens  */
  :root {
    --border      : #e3e6ea;
    --border-light: #edf0f3;

    --text-primary  : #111827;
    --text-secondary: #6b7280;
    --text-tertiary : #9ca3af;

    --accent-green : #16a34a;
    --accent-orange: #ea580c;
    --accent-blue  : #2563eb;
    --accent-violet: #7c3aed;
    --accent-rose  : #e11d48;

    --green-bg  : #f0fdf4;
    --orange-bg : #fff7ed;
    --blue-bg   : #eff6ff;
    --violet-bg : #f5f3ff;
    --rose-bg   : #fff1f2;

    --green-border  : #bbf7d0;
    --orange-border : #fed7aa;
    --blue-border   : #bfdbfe;
    --violet-border : #ddd6fe;
    --rose-border   : #fecdd3;

    --radius-sm: 6px;
    --radius-md: 10px;
    --radius-lg: 14px;

    --shadow-sm: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
    --shadow-md: 0 4px 12px rgba(0,0,0,.07), 0 1px 3px rgba(0,0,0,.04);
    --shadow-lg: 0 8px 24px rgba(0,0,0,.09), 0 2px 6px rgba(0,0,0,.05);

    --font-sans: 'DM Sans', sans-serif;
    --font-mono: 'DM Mono', monospace;

    --transition: 160ms cubic-bezier(.4,0,.2,1);
  }

  /* Card colors scoped to dashboard components */
  .stat-card, .panel {
    --bg-card    : #ffffff;
    --bg-card-alt: #fafbfc;
  }

  .main-content {
    font-family: var(--font-sans);
    min-height : 100vh;
    height     : 100%;
    color      : var(--text-primary);
    -webkit-font-smoothing: antialiased;
    display    : flex;
    flex-direction: column;
  }

  /* Page wrapper */
  .main-body {
    padding: 1.25rem 1.75rem 2rem;
    max-width: 100%;
    width: 100%;
    box-sizing: border-box;
  }

  /* Page header */
  .page-header {
    display        : flex;
    align-items    : center;
    justify-content: space-between;
    margin-bottom  : 20px;
  }


  .page-header-meta {
    display    : flex;
    align-items: center;
    gap        : 10px;
  }

  .badge-date {
    font-family: var(--font-mono);
    font-size  : .72rem;
    color      : var(--text-secondary);
    background : var(--bg-card);
    border     : 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding    : 5px 10px;
    letter-spacing: .02em;
  }

  /* Section label */
  .section-label {
    font-size    : .68rem;
    font-weight  : 600;
    letter-spacing: .08em;
    text-transform: uppercase;
    color        : var(--text-tertiary);
    margin-bottom: 12px;
  }

  /* Stats grid */
  .stats-grid {
    display              : grid;
    grid-template-columns: repeat(5, 1fr);
    gap                  : 12px;
    margin-bottom        : 20px;
  }

  /* Stat card */
  .stat-card {
    background   : var(--bg-card);
    border       : 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding      : 20px 22px;
    display      : flex;
    flex-direction: column;
    gap          : 16px;
    box-shadow   : var(--shadow-sm);
    transition   : box-shadow var(--transition), transform var(--transition);
    cursor       : default;
    position     : relative;
    overflow     : hidden;
  }

  .stat-card::after {
    content : '';
    position: absolute;
    inset   : 0;
    border-radius: var(--radius-lg);
    opacity : 0;
    transition: opacity var(--transition);
    pointer-events: none;
    background: linear-gradient(135deg, rgba(255,255,255,.06) 0%, transparent 60%);
  }

  .stat-card:hover {
    box-shadow: var(--shadow-md);
    transform : translateY(-1px);
  }

  .stat-card:hover::after { opacity: 1; }

  /* Icon badge */
  .stat-icon {
    width          : 42px;
    height         : 42px;
    border-radius  : var(--radius-sm);
    display        : flex;
    align-items    : center;
    justify-content: center;
    flex-shrink    : 0;
    font-size      : 1rem;
    border         : 1px solid transparent;
  }

  .stat-icon.green  { background: var(--green-bg);  color: var(--accent-green);  border-color: var(--green-border);  }
  .stat-icon.orange { background: var(--orange-bg); color: var(--accent-orange); border-color: var(--orange-border); }
  .stat-icon.blue   { background: var(--blue-bg);   color: var(--accent-blue);   border-color: var(--blue-border);   }
  .stat-icon.violet { background: var(--violet-bg); color: var(--accent-violet); border-color: var(--violet-border); }
  .stat-icon.rose   { background: var(--rose-bg);   color: var(--accent-rose);   border-color: var(--rose-border);   }

  .stat-body { display: flex; flex-direction: column; gap: 2px; }

  .stat-num {
    font-size    : 2.1rem;
    font-weight  : 700;
    letter-spacing: -.5px;
    line-height  : 1;
    color        : var(--text-primary);
  }

  .stat-label {
    font-size : .82rem;
    font-weight: 500;
    color     : var(--text-secondary);
    margin-top: 4px;
  }

  /* Adoption rate bar */
  .stat-bar-wrap {
    margin-top: 2px;
  }

  .stat-bar-track {
    height       : 4px;
    background   : var(--border-light);
    border-radius: 99px;
    overflow     : hidden;
  }

  .stat-bar-fill {
    height        : 100%;
    border-radius : 99px;
    background    : var(--accent-green);
    transition    : width .6s cubic-bezier(.4,0,.2,1);
  }

  .stat-bar-pct {
    font-family: var(--font-mono);
    font-size  : .7rem;
    color      : var(--text-tertiary);
    margin-top : 5px;
  }

  /* Lower row */
  .lower-grid {
    display              : grid;
    grid-template-columns: 1fr 280px;
    gap                  : 12px;
  }

  @media (max-width: 1100px) {
    .stats-grid { grid-template-columns: repeat(3, 1fr); }
  }
  @media (max-width: 900px) {
    .lower-grid { grid-template-columns: 1fr; }
    .main-body  { padding: 20px 18px 40px; }
    .stats-grid { grid-template-columns: repeat(3, 1fr); }
  }
  @media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .main-body  { padding: 16px 14px 32px; }
  }
  @media (max-width: 540px) {
    .main-body  { padding: 12px 10px 28px; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
    .stat-card  { padding: 14px 16px; }
    .stat-num   { font-size: 1.6rem; }
    .page-header { flex-direction: column; align-items: flex-start; gap: 8px; }
    .accounts-section { overflow-x: auto; -webkit-overflow-scrolling: touch; }
  }
  @media (max-width: 360px) {
    .stats-grid { grid-template-columns: 1fr; }
  }

  /* Panel card */
  .panel {
    background   : var(--bg-card);
    border       : 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow   : var(--shadow-sm);
    overflow     : hidden;
  }

  .panel-header {
    padding        : 12px 16px;
    border-bottom  : 1px solid var(--border-light);
    display        : flex;
    align-items    : center;
    justify-content: space-between;
  }

  .panel-title {
    font-size  : .875rem;
    font-weight: 600;
    color      : var(--text-primary);
  }

  .panel-action {
    font-size  : .75rem;
    font-weight: 500;
    color      : var(--accent-blue);
    text-decoration: none;
    transition : opacity var(--transition);
  }

  .panel-action:hover { opacity: .75; }

  /* Applications table — !important to beat styles.css overrides */
  .apps-table {
    width           : 100% !important;
    border-collapse : collapse !important;
    table-layout    : fixed !important;
  }

  .apps-table thead th {
    font-size     : .75rem !important;
    font-weight   : 600 !important;
    letter-spacing: .04em !important;
    text-transform: uppercase !important;
    color         : var(--text-secondary) !important;
    padding       : 8px 12px !important;
    text-align    : left !important;
    background    : var(--bg-card-alt) !important;
    border-bottom : 1px solid var(--border-light) !important;
    overflow      : hidden !important;
    text-overflow : ellipsis !important;
    white-space   : nowrap !important;
  }

  .apps-table tbody tr {
    border-bottom: 1px solid var(--border-light) !important;
  }

  .apps-table tbody tr:last-child { border-bottom: none !important; }
  .apps-table tbody tr:hover      { background: var(--bg-card-alt) !important; }

  .apps-table td {
    padding       : 8px 12px !important;
    font-size     : .88rem !important;
    color         : var(--text-primary) !important;
    vertical-align: middle !important;
    overflow      : hidden !important;
    text-overflow : ellipsis !important;
  }

  .apps-table .td-meta {
    color      : var(--text-secondary) !important;
    font-size  : .78rem !important;
    margin-top : 1px !important;
    line-height: 1.3 !important;
  }

  .apps-table .app-id {
    font-family: var(--font-mono) !important;
    font-size  : .7rem !important;
    color      : var(--text-tertiary) !important;
    margin-top : 0 !important;
    line-height: 1.3 !important;
  }

  /* Status badge */
  .status-badge {
    display      : inline-flex;
    align-items  : center;
    gap          : 5px;
    font-size    : .72rem;
    font-weight  : 600;
    padding      : 3px 9px;
    border-radius: 99px;
    letter-spacing: .02em;
    white-space  : nowrap;
  }

  .status-badge::before {
    content      : '';
    width        : 6px;
    height       : 6px;
    border-radius: 50%;
    flex-shrink  : 0;
  }

  .status-badge.pending  { background: var(--orange-bg); color: var(--accent-orange); border: 1px solid var(--orange-border); }
  .status-badge.pending::before  { background: var(--accent-orange); }
  .status-badge.approved { background: var(--green-bg);  color: var(--accent-green);  border: 1px solid var(--green-border);  }
  .status-badge.approved::before { background: var(--accent-green); }
  .status-badge.rejected { background: var(--rose-bg);   color: var(--accent-rose);   border: 1px solid var(--rose-border);   }
  .status-badge.rejected::before { background: var(--accent-rose); }
  .status-badge.review   { background: var(--blue-bg);   color: var(--accent-blue);   border: 1px solid var(--blue-border);   }
  .status-badge.review::before   { background: var(--accent-blue); }

  /* Table empty state */
  .apps-empty {
    text-align : center;
    padding    : 36px 22px;
    color      : var(--text-tertiary);
    font-size  : .82rem;
  }

  /* Summary panel */
  .summary-list { padding: 0; }

  .summary-item {
    display        : flex;
    align-items    : center;
    justify-content: space-between;
    padding        : 10px 16px;
    border-bottom  : 1px solid var(--border-light);
    transition     : background var(--transition);
  }

  .summary-item:last-child { border-bottom: none; }
  .summary-item:hover      { background: var(--bg-card-alt); }

  .summary-item-left {
    display    : flex;
    align-items: center;
    gap        : 11px;
  }

  .summary-dot {
    width        : 8px;
    height       : 8px;
    border-radius: 50%;
    flex-shrink  : 0;
  }

  .summary-item-label {
    font-size  : .83rem;
    font-weight: 500;
    color      : var(--text-primary);
  }

  .summary-item-value {
    font-family: var(--font-mono);
    font-size  : .83rem;
    font-weight: 500;
    color      : var(--text-secondary);
  }

  /* Divider */
  .divider {
    height      : 1px;
    background  : var(--border-light);
    margin      : 4px 0;
  }

  /* Progress ring (for adoption rate in summary) */
  .rate-row {
    padding  : 18px 22px;
    display  : flex;
    align-items: center;
    gap      : 14px;
    border-bottom: 1px solid var(--border-light);
  }

  .rate-ring-wrap { position: relative; flex-shrink: 0; }

  .rate-ring-label {
    position  : absolute;
    inset     : 0;
    display   : flex;
    align-items: center;
    justify-content: center;
    font-family: var(--font-mono);
    font-size  : .68rem;
    font-weight: 500;
    color      : var(--text-primary);
  }

  .rate-desc { font-size: .8rem; color: var(--text-secondary); line-height: 1.45; }
  .rate-desc strong { color: var(--text-primary); font-weight: 600; display: block; margin-bottom: 2px; }

  /* Action buttons in table */
  .btn-verify {
    display        : inline-flex;
    align-items    : center;
    gap            : 5px;
    font-size      : .72rem;
    font-weight    : 600;
    padding        : 4px 10px;
    border-radius  : var(--radius-sm);
    background     : var(--orange-bg);
    color          : var(--accent-orange);
    border         : 1px solid var(--orange-border);
    text-decoration: none;
    white-space    : nowrap;
    transition     : opacity var(--transition);
  }

  .btn-verify:hover { opacity: .75; }

  .btn-view {
    display        : inline-flex;
    align-items    : center;
    gap            : 5px;
    font-size      : .72rem;
    font-weight    : 500;
    padding        : 4px 10px;
    border-radius  : var(--radius-sm);
    background     : var(--bg-card-alt);
    color          : var(--text-secondary);
    border         : 1px solid var(--border);
    text-decoration: none;
    transition     : opacity var(--transition);
  }

  .btn-view:hover { opacity: .75; }

  /* Call-to-action full-width button */
  .btn-action-full {
    display        : flex;
    align-items    : center;
    justify-content: center;
    gap            : 8px;
    width          : 100%;
    padding        : 10px 16px;
    border-radius  : var(--radius-md);
    background     : var(--orange-bg);
    color          : var(--accent-orange);
    border         : 1px solid var(--orange-border);
    font-size      : .82rem;
    font-weight    : 600;
    text-decoration: none;
    transition     : background var(--transition), box-shadow var(--transition);
  }

  .btn-action-full:hover {
    background : #fff0e5;
    box-shadow : var(--shadow-sm);
  }

  /* Fade-in animation */
  @keyframes fadeUp {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0);   }
  }

  .stat-card { animation: fadeUp .35s ease both; }
  .stat-card:nth-child(1) { animation-delay: .04s; }
  .stat-card:nth-child(2) { animation-delay: .08s; }
  .stat-card:nth-child(3) { animation-delay: .12s; }
  .stat-card:nth-child(4) { animation-delay: .16s; }
  .stat-card:nth-child(5) { animation-delay: .20s; }

  .panel { animation: fadeUp .35s .24s ease both; }

  /* ── Accounts Management ── */
  .accounts-section {
    margin-top: 20px;
    flex: 1;
    display: flex;
    flex-direction: column;
  }

  .accounts-section .tab-content.active {
    display: flex;
    flex-direction: column;
    flex: 1;
  }

  .accounts-section .apps-table {
    flex: 1;
  }

  /* Tab bar */
  .tab-bar {
    display   : flex;
    gap       : 4px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 0;
    padding   : 0 16px;
    background: var(--bg-card-alt);
  }

  .tab-btn {
    display       : inline-flex;
    align-items   : center;
    gap           : 8px;
    padding       : 12px 20px;
    font-size     : .875rem;
    font-weight   : 600;
    color         : var(--text-secondary);
    background    : transparent;
    border        : none;
    border-bottom : 2px solid transparent;
    margin-bottom : -1px;
    cursor        : pointer;
    transition    : color var(--transition), border-color var(--transition);
    white-space   : nowrap;
  }

  .tab-btn:hover { color: var(--text-primary); }

  .tab-btn.active {
    color        : var(--accent-blue);
    border-bottom-color: var(--accent-blue);
  }

  .tab-badge {
    font-size    : .72rem;
    font-weight  : 700;
    padding      : 2px 8px;
    border-radius: 99px;
    background   : var(--border-light);
    color        : var(--text-secondary);
    min-width    : 22px;
    text-align   : center;
  }

  .tab-btn.active .tab-badge {
    background: var(--blue-bg);
    color     : var(--accent-blue);
  }

  /* Tab content */
  .tab-content { display: none; }
  .tab-content.active { display: block; }

  /* Role pill */
  .role-pill {
    display      : inline-flex;
    align-items  : center;
    gap          : 4px;
    font-size    : .78rem;
    font-weight  : 600;
    padding      : 4px 12px;
    border-radius: 99px;
    white-space  : nowrap;
    letter-spacing: .01em;
  }

  .role-pill.adopter {
    background: var(--violet-bg);
    color     : var(--accent-violet);
    border    : 1px solid var(--violet-border);
  }

  .role-pill.rescue {
    background: var(--blue-bg);
    color     : var(--accent-blue);
    border    : 1px solid var(--blue-border);
  }

  /* Verified badge */
  .verified-badge {
    display      : inline-flex;
    align-items  : center;
    gap          : 5px;
    font-size    : .78rem;
    font-weight  : 600;
    padding      : 4px 12px;
    border-radius: 99px;
  }

  .verified-badge.yes {
    background: var(--green-bg);
    color     : var(--accent-green);
    border    : 1px solid var(--green-border);
  }

  .verified-badge.no {
    background: var(--orange-bg);
    color     : var(--accent-orange);
    border    : 1px solid var(--orange-border);
  }

  /* Rescue detail block inside table */
  .rescue-details {
    font-size : .74rem;
    color     : var(--text-secondary);
    margin-top: 3px;
    line-height: 1.5;
  }

  .rescue-details span {
    display    : inline-block;
    margin-right: 10px;
  }

  .rescue-details i {
    margin-right: 3px;
    opacity    : .6;
  }

  @media (max-width: 640px) {
    .accounts-section.panel {
      overflow : visible !important;
      border   : none !important;
      box-shadow: none !important;
      background: transparent !important;
      padding  : 0 !important;
    }

    .accounts-section .tab-content.active {
      padding: 6px 0 0 !important;
    }
  }

  /* ── Mobile: accounts table becomes cards ── */
  @media (max-width: 640px) {
    .tab-bar {
      overflow-x   : auto;
      -webkit-overflow-scrolling: touch;
      scrollbar-width: none;
      padding      : 0 8px;
    }
    .tab-bar::-webkit-scrollbar { display: none; }

    .tab-btn {
      padding  : 8px 12px;
      font-size: .78rem;
      flex-shrink: 0;
    }

    /* Hide the real <thead> */
    .apps-table thead { display: none !important; }

    /* Reset table to block layout */
    .apps-table,
    .apps-table tbody,
    .apps-table tr,
    .apps-table td {
      display     : block !important;
      width       : 100% !important;
      table-layout: auto !important;
    }

    /* Each row becomes a compact card */
    .apps-table tbody tr {
      background   : var(--bg-card) !important;
      border       : 1px solid var(--border-light) !important;
      border-radius: var(--radius-md) !important;
      margin       : 6px 0 !important;
      padding      : 12px 14px 6px !important;
      box-shadow   : var(--shadow-sm) !important;
      width        : 100% !important;
    }

    .apps-table tbody tr:hover {
      background: var(--bg-card-alt) !important;
    }

    /* Each cell: label + value side by side */
    .apps-table td {
      padding        : 7px 0 !important;
      font-size      : .9rem !important;
      overflow       : visible !important;
      text-overflow  : unset !important;
      white-space    : normal !important;
      display        : flex !important;
      align-items    : center !important;
      gap            : 10px !important;
      border-bottom  : 1px solid var(--border-light) !important;
    }

    .apps-table td:last-child {
      border-bottom: none !important;
    }

    /* data-label pseudo-label */
    .apps-table td::before {
      content      : attr(data-label);
      font-size    : .7rem !important;
      font-weight  : 700 !important;
      letter-spacing: .06em !important;
      text-transform: uppercase !important;
      color        : var(--text-tertiary) !important;
      min-width    : 68px !important;
      max-width    : 68px !important;
      flex-shrink  : 0 !important;
    }

    /* Name/email first cell: stacked, no label */
    .apps-table td:first-child {
      flex-direction : column !important;
      align-items    : flex-start !important;
      gap            : 2px !important;
      padding-bottom : 10px !important;
    }

    .apps-table td:first-child::before {
      display: none !important;
    }

    .apps-table td:first-child > div:first-child {
      font-weight: 700 !important;
      font-size  : 1rem !important;
    }

    .apps-table .td-meta {
      font-size: .82rem !important;
    }

    .apps-table .app-id {
      font-size: .75rem !important;
    }

    /* Keep pills readable */
    .role-pill,
    .verified-badge {
      font-size: .78rem !important;
      padding  : 4px 11px !important;
    }
  }
</style>

<div class="main-content">
<?php include __DIR__ . '/../../includes/topbar.php'; ?>
  <div class="main-body">

    <!-- Page header -->
    <div class="page-header">
      <h1>Admin Dashboard</h1>
      <div class="page-header-meta">
      </div>
    </div>

    <!-- Stat cards — account management focused -->
    <p class="section-label">Account Overview</p>
    <div class="stats-grid">

      <div class="stat-card">
        <div class="stat-icon violet"><i class="fa-solid fa-users"></i></div>
        <div class="stat-body">
          <div class="stat-num"><?= $totalAdopters ?></div>
          <div class="stat-label">Registered Adopters</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon blue"><i class="fa-solid fa-house-chimney-medical"></i></div>
        <div class="stat-body">
          <div class="stat-num"><?= $totalRescueOrgs ?></div>
          <div class="stat-label">Rescue Organizations</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon orange"><i class="fa-solid fa-clock"></i></div>
        <div class="stat-body">
          <div class="stat-num"><?= $pendingRescueApprovals ?></div>
          <div class="stat-label">Pending Org Approvals</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon orange"><i class="fa-solid fa-id-card"></i></div>
        <div class="stat-body">
          <div class="stat-num"><?= $pendingIdVerifications ?></div>
          <div class="stat-label">Pending ID Reviews</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon green"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-body">
          <div class="stat-num"><?= $verifiedIds ?></div>
          <div class="stat-label">Verified IDs</div>
        </div>
      </div>

    </div><!-- /.stats-grid -->

    <!-- Quick actions row for pending items -->
    <?php if ($pendingRescueApprovals > 0 || $pendingIdVerifications > 0): ?>
    <div class="quick-actions-row" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;">
      <?php if ($pendingRescueApprovals > 0): ?>
      <a href="<?= BASE_URL ?>/modules/admin/users.php?tab=rescue_orgs" class="btn-action-full" style="flex:0 1 auto;padding:9px 18px;width:auto;">
        <i class="fa-solid fa-house-chimney-medical"></i>
        Review <?= $pendingRescueApprovals ?> Pending Org<?= $pendingRescueApprovals > 1 ? 's' : '' ?>
      </a>
      <?php endif; ?>
      <?php if ($pendingIdVerifications > 0): ?>
      <a href="<?= BASE_URL ?>/modules/admin/users.php?tab=adopters&aid=pending" class="btn-action-full" style="flex:0 1 auto;padding:9px 18px;width:auto;background:var(--violet-bg);color:var(--accent-violet);border-color:var(--violet-border);">
        <i class="fa-solid fa-id-card"></i>
        Review <?= $pendingIdVerifications ?> Pending ID<?= $pendingIdVerifications > 1 ? 's' : '' ?>
      </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ── Accounts Management ── -->
    <?php
      $allAccountsList   = $allAccounts;
      $adopterAccounts   = array_values(array_filter($allAccountsList, fn($u) => $u['role'] === 'adopter'));
      $rescueOrgAccounts = array_values(array_filter($allAccountsList, fn($u) => in_array($u['role'], ['rescue_org','staff'], true)));
      $totalAllAccounts  = count($allAccountsList);
      $totalAdopterCount = count($adopterAccounts);
      $totalRescueCount  = count($rescueOrgAccounts);
    ?>

    <p class="section-label">Accounts Management</p>

    <div class="panel accounts-section" style="animation-delay:.20s;">

      <!-- Tab bar -->
      <div class="panel-header" style="padding:0; border-bottom:none;">
        <div class="tab-bar" role="tablist" aria-label="Account filters">
          <button class="tab-btn active"
                  role="tab" aria-selected="true"
                  aria-controls="tab-all" id="btn-all"
                  onclick="switchTab('all')">
            All Accounts
            <span class="tab-badge"><?= $totalAllAccounts ?></span>
          </button>
          <button class="tab-btn"
                  role="tab" aria-selected="false"
                  aria-controls="tab-adopters" id="btn-adopters"
                  onclick="switchTab('adopters')">
            Adopters
            <span class="tab-badge"><?= $totalAdopterCount ?></span>
          </button>
          <button class="tab-btn"
                  role="tab" aria-selected="false"
                  aria-controls="tab-rescue" id="btn-rescue"
                  onclick="switchTab('rescue')">
            Rescue Organizations
            <span class="tab-badge"><?= $totalRescueCount ?></span>
          </button>
        </div>
      </div>

      <div id="tab-all"      class="tab-content active" role="tabpanel" aria-labelledby="btn-all">
        <?php renderAccountsTable($allAccountsList); ?>
      </div>
      <div id="tab-adopters" class="tab-content"        role="tabpanel" aria-labelledby="btn-adopters">
        <?php renderAccountsTable($adopterAccounts); ?>
      </div>
      <div id="tab-rescue"   class="tab-content"        role="tabpanel" aria-labelledby="btn-rescue">
        <?php renderAccountsTable($rescueOrgAccounts, true); ?>
      </div>

    </div><!-- /.panel accounts-section -->

  </div><!-- /.main-body -->
</div><!-- /.main-content -->

<?php
/**
 * Renders the accounts table for a given array of user rows.
 * Rescue orgs get extra detail columns (Shelter Name, SEC Reg No., Address).
 *
 * @param array $rows  Rows from the users query
 */
function renderAccountsTable(array $rows, bool $showRescueCols = false): void
{
    if (empty($rows)) { ?>
        <p class="apps-empty">No accounts found.</p>
    <?php return; }

    $hasRescue = $showRescueCols;
    ?>
    <table class="apps-table">
      <thead>
        <tr>
          <?php if ($hasRescue): ?>
            <th style="width:18% !important">Name / Email</th>
            <th style="width:10% !important">Role</th>
            <th style="width:14% !important">Shelter Name</th>
            <th style="width:13% !important">SEC Reg No.</th>
            <th style="width:18% !important">Address / Phone</th>
            <th style="width:11% !important">Verified</th>
            <th style="width:16% !important">Joined</th>
          <?php else: ?>
            <th style="width:30% !important">Name / Email</th>
            <th style="width:18% !important">Role</th>
            <th style="width:22% !important">Verified</th>
            <th style="width:30% !important">Joined</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $u):
          $isRescue   = in_array($u['role'], ['rescue_org', 'staff'], true);
          $rolePill   = $isRescue ? 'rescue' : 'adopter';
          $roleLabel  = $isRescue ? 'Rescue Org' : 'Adopter';
          // Rescue orgs: active = approved by admin; Adopters: is_verified = email confirmed
          if ($isRescue) { // rescue_org or staff
              $verified = !empty($u['is_active']) ? 'yes' : 'no';
              $verLabel = $verified === 'yes' ? 'Active' : 'Pending Approval';
          } else {
              $verified = !empty($u['is_verified']) ? 'yes' : 'no';
              $verLabel = $verified === 'yes' ? 'Verified' : 'Unverified';
          }

          $joinedStr  = '';
          if (!empty($u['created_at'])) {
              try {
                  $dt        = new DateTimeImmutable($u['created_at']);
                  $joinedStr = $dt->format('d M Y');
              } catch (Exception) {
                  $joinedStr = htmlspecialchars((string)$u['created_at'], ENT_QUOTES, 'UTF-8');
              }
          }
        ?>
          <tr>
            <td>
              <div><?= htmlspecialchars((string)($u['full_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></div>
              <div class="td-meta"><?= htmlspecialchars((string)($u['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
              <div class="app-id">#<?= (int)$u['id'] ?></div>
            </td>
            <td data-label="Role">
              <span class="role-pill <?= $rolePill ?>">
                <?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?>
              </span>
            </td>
            <?php if ($hasRescue): ?>
              <td data-label="Shelter">
                <?php
                  $_sname = (!empty($u['shelter_name']) ? $u['shelter_name'] : null)
                         ?? (!empty($u['organization_name']) ? $u['organization_name'] : null);
                  if ($isRescue && $_sname): ?>
                  <?= htmlspecialchars((string)$_sname, ENT_QUOTES, 'UTF-8') ?>
                <?php else: ?>
                  <span style="color:var(--text-tertiary);">—</span>
                <?php endif; ?>
              </td>
              <td data-label="SEC Reg">
                <?php if ($isRescue && !empty($u['sec_registration_no'])): ?>
                  <span style="font-family:var(--font-mono);font-size:.75rem;">
                    <?= htmlspecialchars((string)$u['sec_registration_no'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                <?php else: ?>
                  <span style="color:var(--text-tertiary);">—</span>
                <?php endif; ?>
              </td>
              <td data-label="Address">
                <?php if ($isRescue): ?>
                  <?php
                  $_addr = (!empty($u['address']) ? $u['address'] : null)
                        ?? (!empty($u['organization_address']) ? $u['organization_address'] : null);
                  if (!empty($_addr)): ?>
                    <div class="rescue-details">
                      <i class="fa-solid fa-location-dot"></i>
                      <?= htmlspecialchars((string)$_addr, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($u['phone'])): ?>
                    <div class="rescue-details">
                      <i class="fa-solid fa-phone"></i>
                      <?= htmlspecialchars((string)$u['phone'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                  <?php endif; ?>
                  <?php if (empty($_addr) && empty($u['phone'])): ?>
                    <span style="color:var(--text-tertiary);">—</span>
                  <?php endif; ?>
                <?php else: ?>
                  <span style="color:var(--text-tertiary);">—</span>
                <?php endif; ?>
              </td>
            <?php endif; ?>
            <td data-label="Status">
              <span class="verified-badge <?= $verified ?>">
                <?php if ($verified === 'yes'): ?>
                  <i class="fa-solid fa-circle-check"></i>
                <?php else: ?>
                  <i class="fa-solid fa-circle-exclamation"></i>
                <?php endif; ?>
                <?= $verLabel ?>
              </span>
            </td>
            <td data-label="Joined" style="white-space:nowrap"><?= $joinedStr ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php
}
?>

<script>

function switchTab(tabKey) {
  var tabIds  = ['all', 'adopters', 'rescue'];
  var btnIds  = ['btn-all', 'btn-adopters', 'btn-rescue'];

  tabIds.forEach(function(id) {
    var content = document.getElementById('tab-' + id);
    var btn     = document.getElementById('btn-' + id);
    if (!content || !btn) return;

    var isActive = (id === tabKey);
    content.classList.toggle('active', isActive);
    btn.classList.toggle('active', isActive);
    btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
  });
}
</script>

<?php include __DIR__ . '/../../includes/dash-foot.php'; ?>