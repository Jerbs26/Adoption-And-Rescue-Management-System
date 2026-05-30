<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
session_start_once();
require_login(BASE_URL . '/login.php');
require_role('staff', 'rescue_org');

$user       = current_user();
$activePage = 'animal-reports';   // matches sidebar nav key
$pageTitle  = 'Animal Reports';

$reportUploadUrl = BASE_URL . '/public/uploads/animal-reports/';

$errors  = [];
$success = '';

// ── Check table exists before doing anything ──────────────────────────────────
$tableExists = false;
try {
    db()->query("SELECT 1 FROM animal_reports LIMIT 1");
    $tableExists = true;
} catch (PDOException) {
    $tableExists = false;
}

// ── Handle status update ──────────────────────────────────────────────────────
if ($tableExists && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $reportId  = (int)($_POST['report_id'] ?? 0);
    $newStatus = clean($_POST['new_status'] ?? '');
    $allowed   = ['Pending', 'New', 'Acknowledged', 'Rescued'];

    if ($reportId <= 0) {
        $errors[] = 'Invalid report ID.';
    } elseif (!in_array($newStatus, $allowed, true)) {
        $errors[] = 'Invalid status value.';
    } else {
        // Confirm the report exists
        $check = db()->prepare("SELECT id FROM animal_reports WHERE id = ?");
        $check->execute([$reportId]);
        if (!$check->fetch()) {
            $errors[] = 'Report not found.';
        } else {
            $stmt = db()->prepare("UPDATE animal_reports SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $reportId]);

            log_activity(
                'animal_report_status_update',
                'animal_report',
                $reportId,
                'Staff updated animal report #' . $reportId . ' status to ' . $newStatus
            );

            flash('success', 'Report #' . $reportId . ' status updated to ' . $newStatus . '.');
            redirect(BASE_URL . '/modules/staff/animal-report.php');
        }
    }
}

// ── Filters, pagination, queries — only run if table exists ──────────────────
$filterStatus  = '';
$filterType    = '';
$search        = '';
$reports       = [];
$pagination    = ['pages' => 0, 'current' => 1];
$totalAll      = 0;
$totalPending  = 0;
$totalAck      = 0;
$totalRescued  = 0;
$validStatuses = ['Pending', 'New', 'Acknowledged', 'Rescued'];
$validTypes    = ['Dog', 'Cat'];

if ($tableExists) {

// ── Filters ───────────────────────────────────────────────────────────────────
$filterStatus = clean($_GET['status']      ?? '');
$filterType   = clean($_GET['animal_type'] ?? '');
$search       = clean($_GET['search']      ?? '');

if (!in_array($filterStatus, $validStatuses, true)) $filterStatus = '';
if (!in_array($filterType,   $validTypes,    true)) $filterType   = '';

// ── Pagination ────────────────────────────────────────────────────────────────
$perPage     = 15;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($currentPage - 1) * $perPage;

// ── Build query ───────────────────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($filterStatus !== '') {
    $where[]  = 'ar.status = ?';
    $params[] = $filterStatus;
}
if ($filterType !== '') {
    $where[]  = 'ar.animal_type = ?';
    $params[] = $filterType;
}
if ($search !== '') {
    $where[]  = '(ar.location LIKE ? OR ar.status_desc LIKE ? OR u.full_name LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereClause = implode(' AND ', $where);

// Total count
$countStmt = db()->prepare("
    SELECT COUNT(*)
    FROM animal_reports ar
    JOIN users u ON u.id = ar.adopter_id
    WHERE {$whereClause}
");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();

$pagination  = paginate($totalRecords, $perPage, $currentPage);

// Fetch reports
$listParams   = array_merge($params, [$perPage, $offset]);
$reportsStmt  = db()->prepare("
    SELECT
        ar.id,
        ar.location,
        ar.animal_type,
        ar.status_desc,
        ar.proof_photo,
        ar.status,
        ar.created_at,
        u.full_name  AS adopter_name,
        u.email      AS adopter_email
    FROM animal_reports ar
    JOIN users u ON u.id = ar.adopter_id
    WHERE {$whereClause}
    ORDER BY
        CASE ar.status
            WHEN 'Pending'      THEN 1
            WHEN 'New'          THEN 2
            WHEN 'Acknowledged' THEN 3
            WHEN 'Rescued'      THEN 4
            ELSE 5
        END,
        ar.created_at DESC
    LIMIT ? OFFSET ?
");
$reportsStmt->execute($listParams);
$reports = $reportsStmt->fetchAll();

// ── Summary counts ────────────────────────────────────────────────────────────
$summaryStmt = db()->query("
    SELECT status, COUNT(*) AS cnt
    FROM animal_reports
    GROUP BY status
");
$summary = [];
foreach ($summaryStmt->fetchAll() as $row) {
    $summary[$row['status']] = (int)$row['cnt'];
}
$totalAll     = array_sum($summary);
$totalPending = ($summary['Pending'] ?? 0) + ($summary['New'] ?? 0);
$totalAck     = $summary['Acknowledged'] ?? 0;
$totalRescued = $summary['Rescued']      ?? 0;

} // end if ($tableExists)

include __DIR__ . '/../../includes/dash-head.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<style>
/* ── Summary cards ── */
.ar-summary {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-bottom: 1.75rem;
}
@media (min-width: 640px) {
    .ar-summary { grid-template-columns: repeat(4, 1fr); }
}
.ar-stat {
    background: var(--card, #fff);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 1.1rem 1.25rem;
    display: flex;
    flex-direction: column;
    gap: .3rem;
}
.ar-stat__value {
    font-size: 1.8rem;
    font-weight: 800;
    line-height: 1;
    color: var(--fg);
}
.ar-stat__label {
    font-size: .75rem;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--muted-fg);
    font-weight: 600;
}
.ar-stat--pending  .ar-stat__value { color: var(--warning,  #b45309); }
.ar-stat--ack      .ar-stat__value { color: var(--success,  #166534); }
.ar-stat--rescued  .ar-stat__value { color: var(--info,     #1d4ed8); }

/* ── Filter bar ── */
.ar-filters {
    display: flex;
    flex-wrap: wrap;
    gap: .6rem;
    align-items: center;
    margin-bottom: 1.25rem;
}
.ar-filters .form-control {
    max-width: 180px;
    font-size: .85rem;
}
.ar-filters .form-control.search-input {
    max-width: 240px;
}

/* ── Table ── */
.ar-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.ar-table { width: 100%; border-collapse: collapse; min-width: 700px; }
.ar-table thead tr { background: var(--muted); }
.ar-table th {
    padding: .6rem 1rem;
    font-size: .75rem;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--muted-fg);
    white-space: nowrap;
    text-align: left;
}
.ar-table td {
    padding: .8rem 1rem;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
    font-size: .875rem;
}
.ar-table tbody tr:last-child td { border-bottom: none; }
.ar-table tbody tr:hover { background: hsla(35,55%,92%,.25); }

/* ── Pet hero cell (desktop) ── */
.td-pet-hero {
    display: flex !important;
    align-items: center;
    gap: .75rem;
}
.ar-thumb {
    width: 56px;
    height: 52px;
    object-fit: cover;
    border-radius: 8px;
    display: block;
    border: 1px solid var(--border);
    cursor: pointer;
    flex-shrink: 0;
    transition: opacity .15s;
}
.ar-thumb:hover { opacity: .8; }
.ar-thumb-placeholder {
    width: 56px; height: 52px;
    border-radius: 8px;
    background: var(--muted);
    display: flex; align-items: center; justify-content: center;
    color: var(--muted-fg); font-size: .85rem;
    flex-shrink: 0;
}
.ar-hero-info {
    display: flex;
    flex-direction: column;
    gap: .15rem;
}
.ar-hero-id { font-size: .78rem; }
.ar-hero-type { font-size: .88rem; font-weight: 600; }

/* ── Status form ── */
.ar-status-form {
    display: flex;
    align-items: center;
    gap: .4rem;
    flex-wrap: nowrap;
}
.ar-status-form select {
    font-size: .8rem;
    padding: .3rem .5rem;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: var(--card-bg, #fff);
    color: var(--fg);
    cursor: pointer;
}
.ar-status-form button {
    font-size: .75rem;
    padding: .3rem .65rem;
    white-space: nowrap;
}

/* ── Pagination ── */
.ar-pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: .4rem;
    padding: 1.25rem 0 .25rem;
    flex-wrap: wrap;
}
.ar-pagination a,
.ar-pagination span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 34px;
    height: 34px;
    padding: 0 .55rem;
    border-radius: 8px;
    font-size: .82rem;
    font-weight: 600;
    border: 1px solid var(--border);
    text-decoration: none;
    color: var(--fg);
    transition: background .15s, color .15s;
}
.ar-pagination a:hover           { background: var(--muted); }
.ar-pagination span.current      { background: var(--primary, #2d5a45); color: #fff; border-color: var(--primary, #2d5a45); }
.ar-pagination span.disabled     { color: var(--muted-fg); pointer-events: none; }

/* ── Photo modal ── */
.ar-modal-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.72);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}
.ar-modal-backdrop.open { display: flex; }
.ar-modal-img {
    max-width: min(90vw, 720px);
    max-height: 85vh;
    border-radius: 12px;
    box-shadow: 0 8px 40px rgba(0,0,0,.5);
    object-fit: contain;
}
.ar-modal-close {
    position: absolute;
    top: 1.25rem;
    right: 1.5rem;
    background: rgba(255,255,255,.15);
    border: none;
    color: #fff;
    font-size: 1.4rem;
    width: 40px; height: 40px;
    border-radius: 50%;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: background .15s;
}
.ar-modal-close:hover { background: rgba(255,255,255,.3); }

/* ── Responsive ── */
@media (max-width: 768px) {
    .ar-filters .form-control,
    .ar-filters .form-control.search-input {
        max-width: 100%;
        width: 100%;
    }
    .ar-filters button,
    .ar-filters a.btn {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 640px) {
    /* Table → card transform (matches users.php) */
    .ar-table-wrap table thead { display: none !important; }

    .ar-table-wrap table,
    .ar-table-wrap tbody,
    .ar-table-wrap tr,
    .ar-table-wrap td {
        display: block !important;
        width: 100% !important;
    }

    /* Each row = a card */
    .ar-table-wrap tbody tr {
        background: var(--card) !important;
        border: 1px solid var(--border) !important;
        border-radius: 12px !important;
        margin: 0 0 .75rem !important;
        padding: 12px 14px 6px !important;
        box-shadow: 0 1px 3px rgba(0,0,0,.06) !important;
    }

    /* Each cell: label (::before) + value side-by-side */
    .ar-table-wrap td {
        padding: 8px 14px !important;
        font-size: .88rem !important;
        white-space: normal !important;
        display: flex !important;
        align-items: flex-start !important;
        gap: 12px !important;
        border-bottom: 1px solid var(--border) !important;
        min-height: 0 !important;
    }
    .ar-table-wrap td:last-child { border-bottom: none !important; }

    /* data-label pseudo labels */
    .ar-table-wrap td::before {
        content: attr(data-label);
        font-size: .68rem !important;
        font-weight: 700 !important;
        letter-spacing: .06em !important;
        text-transform: uppercase !important;
        color: var(--muted-fg) !important;
        min-width: 96px !important;
        max-width: 96px !important;
        flex-shrink: 0 !important;
        padding-top: 2px !important;
    }

    /* Hero cell — full-bleed photo card (matches pets.php) */
    .ar-table-wrap td:first-child {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 0 !important;
        padding: 0 0 10px !important;
        border-bottom: 2px solid var(--border) !important;
    }
    .ar-table-wrap td:first-child::before { display: none !important; }

    /* Full-bleed photo */
    .ar-thumb {
        width: 100% !important;
        height: 200px !important;
        border-radius: 14px 14px 0 0 !important;
        object-fit: cover !important;
        object-position: center !important;
        border: none !important;
        flex-shrink: unset !important;
    }
    /* Placeholder full-bleed */
    .ar-thumb-placeholder--hero {
        width: 100% !important;
        height: 120px !important;
        border-radius: 14px 14px 0 0 !important;
        font-size: 2rem !important;
        flex-shrink: unset !important;
    }
    /* Hero info row below photo */
    .ar-hero-info {
        flex-direction: row !important;
        align-items: center !important;
        gap: .5rem !important;
        padding: 8px 14px 2px !important;
        width: 100% !important;
    }
    .ar-hero-type { font-size: 1rem !important; font-weight: 700 !important; }

    /* Status form: full-width on mobile */
    .ar-status-form { flex-direction: column !important; width: 100% !important; }
    .ar-status-form select { width: 100% !important; font-size: 1rem !important; }
    .ar-status-form button { width: 100% !important; justify-content: center !important; }

    /* Last td (actions): no label */
    .ar-table-wrap td.td-action::before { display: none !important; }
    .ar-table-wrap td.td-action { padding-top: 10px !important; }

    /* Modal */
    .ar-modal-backdrop { padding: 0; align-items: flex-end; }
    .ar-modal-img { max-width: 100vw; max-height: 85vh; border-radius: 12px 12px 0 0; }
}
</style>

<div class="main-content">
<?php include __DIR__ . '/../../includes/topbar.php'; ?>
<div class="main-body">

    <?php $flash = get_flash('success'); if ($flash): ?>
    <div class="alert alert-success" data-auto-dismiss>
        <i class="fa-solid fa-check"></i><?= e($flash) ?>
    </div>
    <?php endif; ?>

    <?php if ($errors): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-circle-exclamation"></i><?= e($errors[0]) ?>
    </div>
    <?php endif; ?>

    <!-- Page header -->
    <div class="page-header">
        <h1>Dog / Cat Reports</h1>
        <p class="muted">Review animal reports submitted by adopters and update their rescue status.</p>
    </div>

    <?php if (!$tableExists): ?>
    <div class="alert alert-warning">
        <i class="fa-solid fa-triangle-exclamation"></i>
        The <code>animal_reports</code> table does not exist in the database yet.
        Please run the database migration to create it, then return here.
    </div>
    <?php else: ?>
    <div class="ar-summary">
        <div class="ar-stat">
            <span class="ar-stat__value"><?= $totalAll ?></span>
            <span class="ar-stat__label">Total Reports</span>
        </div>
        <div class="ar-stat ar-stat--pending">
            <span class="ar-stat__value"><?= $totalPending ?></span>
            <span class="ar-stat__label">Needs Action</span>
        </div>
        <div class="ar-stat ar-stat--ack">
            <span class="ar-stat__value"><?= $totalAck ?></span>
            <span class="ar-stat__label">Acknowledged</span>
        </div>
        <div class="ar-stat ar-stat--rescued">
            <span class="ar-stat__value"><?= $totalRescued ?></span>
            <span class="ar-stat__label">Rescued</span>
        </div>
    </div>

    <!-- Filters -->
    <form method="get" class="ar-filters" id="filterForm">
        <input
            type="text"
            name="search"
            class="form-control search-input"
            placeholder="Search location, description, adopter…"
            value="<?= e($search) ?>"
        >
        <select name="status" class="form-control" onchange="document.getElementById('filterForm').submit()">
            <option value="">All Statuses</option>
            <?php foreach ($validStatuses as $s): ?>
            <option value="<?= e($s) ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="animal_type" class="form-control" onchange="document.getElementById('filterForm').submit()">
            <option value="">All Animals</option>
            <?php foreach ($validTypes as $t): ?>
            <option value="<?= e($t) ?>" <?= $filterType === $t ? 'selected' : '' ?>><?= e($t) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary" style="font-size:.85rem;padding:.4rem .9rem">
            <i class="fa-solid fa-magnifying-glass"></i> Search
        </button>
        <?php if ($search !== '' || $filterStatus !== '' || $filterType !== ''): ?>
        <a href="<?= BASE_URL ?>/modules/staff/animal-report.php" class="btn" style="font-size:.85rem;padding:.4rem .9rem">
            <i class="fa-solid fa-xmark"></i> Clear
        </a>
        <?php endif; ?>
    </form>

    <!-- Reports table -->
    <?php if ($reports): ?>
    <div class="card">
        <div class="ar-table-wrap">
            <table class="ar-table">
                <thead>
                    <tr>
                        <th>Pet</th>
                        <th>Location</th>
                        <th>Description</th>
                        <th>Reported By</th>
                        <th>Submitted</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($reports as $r): ?>
                <?php $icon = $r['animal_type'] === 'Dog' ? 'fa-dog' : 'fa-cat'; ?>
                <tr>
                    <td class="td-pet-hero">
                        <?php if ($r['proof_photo']): ?>
                        <img
                            src="<?= e($reportUploadUrl . rawurlencode($r['proof_photo'])) ?>"
                            alt="Report photo"
                            class="ar-thumb"
                            onclick="openPhotoModal(this.src)"
                            title="Click to enlarge"
                        >
                        <?php else: ?>
                        <div class="ar-thumb-placeholder ar-thumb-placeholder--hero">
                            <i class="fa-solid fa-image"></i>
                        </div>
                        <?php endif; ?>
                        <div class="ar-hero-info">
                            <span class="ar-hero-type">
                                <?= e($r['animal_type']) ?>
                            </span>
                        </div>
                    </td>
                    <td class="muted small" data-label="Location">
                        <?= e($r['location']) ?>
                    </td>
                    <td class="muted small" data-label="Description" style="max-width:200px">
                        <?= e(mb_strimwidth($r['status_desc'], 0, 80, '…')) ?>
                    </td>
                    <td data-label="Reported By">
                        <div style="font-size:.85rem;font-weight:600"><?= e($r['adopter_name']) ?></div>
                        <div class="muted small"><?= e($r['adopter_email']) ?></div>
                    </td>
                    <td class="muted small" data-label="Submitted">
                        <?= date('M j, Y', strtotime($r['created_at'])) ?><br>
                        <span style="font-size:.72rem"><?= date('g:i A', strtotime($r['created_at'])) ?></span>
                    </td>
                    <td class="td-action">
                        <form method="post" class="ar-status-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>">
                            <select name="new_status" aria-label="Update status for report #<?= (int)$r['id'] ?>">
                                <?php foreach ($validStatuses as $s): ?>
                                <option value="<?= e($s) ?>" <?= $r['status'] === $s ? 'selected' : '' ?>>
                                    <?= e($s) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-check"></i> Save
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($pagination['pages'] > 1): ?>
        <div class="ar-pagination">
            <?php
            // Build base query string without 'page'
            $qp = array_filter([
                'search'      => $search,
                'status'      => $filterStatus,
                'animal_type' => $filterType,
            ], fn($v) => $v !== '');

            function ar_page_url(int $p, array $qp): string {
                return BASE_URL . '/modules/staff/animal-report.php?' . http_build_query(array_merge($qp, ['page' => $p]));
            }

            $cur   = $pagination['current'];
            $pages = $pagination['pages'];

            // Prev
            if ($cur > 1): ?>
            <a href="<?= e(ar_page_url($cur - 1, $qp)) ?>">&lsaquo; Prev</a>
            <?php else: ?>
            <span class="disabled">&lsaquo; Prev</span>
            <?php endif; ?>

            <?php
            // Page numbers (show window of 5 around current)
            $start = max(1, $cur - 2);
            $end   = min($pages, $cur + 2);

            if ($start > 1): ?>
                <a href="<?= e(ar_page_url(1, $qp)) ?>">1</a>
                <?php if ($start > 2): ?><span class="disabled">…</span><?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start; $i <= $end; $i++): ?>
                <?php if ($i === $cur): ?>
                <span class="current"><?= $i ?></span>
                <?php else: ?>
                <a href="<?= e(ar_page_url($i, $qp)) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($end < $pages): ?>
                <?php if ($end < $pages - 1): ?><span class="disabled">…</span><?php endif; ?>
                <a href="<?= e(ar_page_url($pages, $qp)) ?>"><?= $pages ?></a>
            <?php endif; ?>

            <!-- Next -->
            <?php if ($cur < $pages): ?>
            <a href="<?= e(ar_page_url($cur + 1, $qp)) ?>">Next &rsaquo;</a>
            <?php else: ?>
            <span class="disabled">Next &rsaquo;</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div><!-- /.card -->

    <?php else: ?>
    <div class="empty-state">
        <i class="fa-solid fa-paw"></i>
        <h3>No reports found</h3>
        <p class="muted">
            <?php if ($search !== '' || $filterStatus !== '' || $filterType !== ''): ?>
                No reports match your current filters.
                <a href="<?= BASE_URL ?>/modules/staff/animal-report.php">Clear filters</a>
            <?php else: ?>
                No animal reports have been submitted by adopters yet.
            <?php endif; ?>
        </p>
    </div>
    <?php endif; ?>

    <?php endif; // $tableExists ?>

</div><!-- /.main-body -->
</div><!-- /.main-content -->

<!-- Photo modal -->
<div class="ar-modal-backdrop" id="photoModal" onclick="closePhotoModal(event)">
    <button class="ar-modal-close" onclick="closePhotoModal()" aria-label="Close photo">
        <i class="fa-solid fa-xmark"></i>
    </button>
    <img src="" alt="Report photo enlarged" class="ar-modal-img" id="modalImg">
</div>

<script>
function openPhotoModal(src) {
    document.getElementById('modalImg').src = src;
    document.getElementById('photoModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closePhotoModal(e) {
    if (e && e.target === document.getElementById('modalImg')) return; // clicking the image itself shouldn't close
    document.getElementById('photoModal').classList.remove('open');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closePhotoModal();
});
</script>

<?php include __DIR__ . '/../../includes/dash-foot.php'; ?>