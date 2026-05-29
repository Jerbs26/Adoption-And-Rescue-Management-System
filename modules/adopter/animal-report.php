<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
session_start_once();
require_login(BASE_URL . '/login.php');
require_role('adopter');

$user       = current_user();
$activePage = 'animal-report';
$pageTitle  = 'Report an Animal';

// Upload directory for animal report photos
$projectRoot     = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');
$reportUploadDir = $projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR
                 . 'uploads'    . DIRECTORY_SEPARATOR . 'animal-reports' . DIRECTORY_SEPARATOR;
$reportUploadUrl = BASE_URL . '/public/uploads/animal-reports/';

if (!is_dir($reportUploadDir)) {
    if (!mkdir($reportUploadDir, 0755, true)) {
        error_log('PET-ADOPTION: failed to create animal-reports upload dir: ' . $reportUploadDir);
    }
}

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $location    = clean($_POST['location']     ?? '');
    $animalType  = clean($_POST['animal_type']  ?? '');
    $statusDesc  = clean($_POST['status_desc']  ?? '');
    $file        = $_FILES['proof_photo']       ?? null;

    $allowedAnimalTypes = ['Dog', 'Cat'];
    $allowedMimeTypes   = ['image/jpeg', 'image/png', 'image/webp'];
    $maxFileSize        = 5 * 1024 * 1024; // 5 MB

    // Validate location
    if ($location === '') {
        $errors[] = 'Exact location is required.';
    } elseif (mb_strlen($location) > 255) {
        $errors[] = 'Location must not exceed 255 characters.';
    }

    // Validate animal type
    if (!in_array($animalType, $allowedAnimalTypes, true)) {
        $errors[] = 'Please select a valid animal type (Dog or Cat).';
    }

    // Validate status description
    if ($statusDesc === '') {
        $errors[] = 'Description of status is required.';
    } elseif (mb_strlen($statusDesc) > 1000) {
        $errors[] = 'Description must not exceed 1000 characters.';
    }

    // Validate photo upload
    $filename = null;
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'A proof photo is required.';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Photo upload failed (error code ' . (int)$file['error'] . '). Please try again.';
    } elseif ($file['size'] > $maxFileSize) {
        $errors[] = 'Photo exceeds the 5 MB size limit.';
    } else {
        $detectedMime = mime_content_type($file['tmp_name']);
        if (!in_array($detectedMime, $allowedMimeTypes, true)) {
            $errors[] = 'Only JPG, PNG, or WEBP images are accepted.';
        }
    }

    if (empty($errors)) {
        // Save the uploaded photo
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'report_' . (int)$user['id'] . '_' . time() . '.' . $ext;
        $dest     = $reportUploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $errors[] = 'Could not save the photo. Please ensure the upload folder is writable.';
            error_log('PET-ADOPTION animal-report move_uploaded_file failed: ' . $dest);
            $filename = null;
        }
    }

    if (empty($errors) && $filename !== null) {
        $stmt = db()->prepare("
            INSERT INTO animal_reports
                (adopter_id, location, proof_photo, animal_type, status_desc, status)
            VALUES (?, ?, ?, ?, ?, 'Pending')
        ");
        $stmt->execute([
            $user['id'],
            $location,
            $filename,
            $animalType,
            $statusDesc,
        ]);

        $reportId = (int)db()->lastInsertId();

        log_activity('animal_report_submit', 'animal_report', $reportId,
            'Adopter submitted animal report: ' . $animalType . ' at ' . $location);

        flash('success', 'Your report has been submitted. Our team will review it shortly.');
        redirect(BASE_URL . '/modules/adopter/animal-report.php');
    }
}

// Fetch this adopter's past reports
$reportsStmt = db()->prepare("
    SELECT id, location, animal_type, status_desc, proof_photo, status, created_at
    FROM animal_reports
    WHERE adopter_id = ?
    ORDER BY created_at DESC
");
$reportsStmt->execute([$user['id']]);
$myReports = $reportsStmt->fetchAll();

include __DIR__ . '/../../includes/dash-head.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<style>
/* ── Layout ─────────────────────────────────────────────────────── */
.ar-layout { display: grid; grid-template-columns: 360px 1fr; gap: 2rem; align-items: start; }
@media (max-width: 900px) { .ar-layout { grid-template-columns: 1fr; } }

/* ── Form panel ──────────────────────────────────────────────────── */
.ar-panel {
    background: var(--card-bg, #fff);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    position: sticky;
    top: 1rem;
}
.ar-panel-header {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: .6rem;
}
.ar-panel-header i { font-size: 1rem; color: var(--accent, #b45309); }
.ar-panel-title { font-size: .9rem; font-weight: 700; margin: 0; letter-spacing: -.01em; }
.ar-panel-body { padding: 1.25rem; }
.ar-panel-body .field { margin-bottom: 1rem; }
.ar-panel-body .field:last-of-type { margin-bottom: 1.25rem; }
.ar-photo-hint { font-size: .75rem; color: var(--muted-fg); margin-top: .3rem; }

/* ── Right column ────────────────────────────────────────────────── */
.ar-right-col { display: flex; flex-direction: column; gap: 1rem; }
.ar-section-label {
    font-size: .7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--muted-fg);
    margin: 0 0 .75rem;
}

/* ── Table ───────────────────────────────────────────────────────── */
.ar-table-wrap {
    background: var(--card-bg, #fff);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
}
.ar-table { width: 100%; border-collapse: collapse; }
.ar-table thead tr { background: var(--muted); }
.ar-table th {
    padding: .65rem 1rem;
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: var(--muted-fg);
    white-space: nowrap;
    text-align: left;
}
.ar-table td {
    padding: .85rem 1rem;
    border-top: 1px solid var(--border);
    vertical-align: middle;
    font-size: .875rem;
}
.ar-table tbody tr:hover { background: hsla(35,40%,95%,.5); }

/* ── Thumbnail ───────────────────────────────────────────────────── */
.ar-thumb {
    width: 44px; height: 44px; object-fit: cover;
    border-radius: 8px; display: block; border: 1px solid var(--border);
}
.ar-thumb-placeholder {
    width: 44px; height: 44px; border-radius: 8px;
    background: var(--muted); display: flex; align-items: center;
    justify-content: center; color: var(--muted-fg); font-size: .85rem;
}

/* ── Animal type pill ────────────────────────────────────────────── */
.ar-type-pill {
    display: inline-flex; align-items: center; gap: .3rem;
    font-size: .78rem; font-weight: 600;
    padding: .2rem .6rem; border-radius: 99px;
    background: var(--muted); color: var(--muted-fg);
}

/* ── Empty state ─────────────────────────────────────────────────── */
.ar-empty {
    background: var(--card-bg, #fff);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 3rem 2rem;
    text-align: center;
    color: var(--muted-fg);
}
.ar-empty i { font-size: 2rem; margin-bottom: .75rem; display: block; opacity: .4; }
.ar-empty h3 { font-size: .95rem; font-weight: 700; color: var(--fg); margin: 0 0 .3rem; }
.ar-empty p { font-size: .85rem; margin: 0; }
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

    <div class="page-header" style="margin-bottom:1.5rem">
        <h1 style="font-size:1.5rem;font-weight:800;margin:0 0 .25rem">Report an Animal</h1>
        <p class="muted" style="margin:0;font-size:.875rem">Spotted a dog or cat that needs help? Fill in the form and our rescue team will be notified.</p>
    </div>

    <div class="ar-layout">

        <!-- Report Form -->
        <div class="ar-panel">
            <div class="ar-panel-header">
                <i class="fa-solid fa-paw"></i>
                <h2 class="ar-panel-title">New Animal Report</h2>
            </div>
            <div class="ar-panel-body">
                <form method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>

                    <div class="field">
                        <label class="form-label" for="ar_location">
                            Exact Location <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            id="ar_location"
                            name="location"
                            class="form-control"
                            placeholder="e.g. Corner of Rizal Ave and Mabini St, Makati City"
                            maxlength="255"
                            value="<?= e($_POST['location'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="field">
                        <label class="form-label" for="ar_animal_type">
                            Animal Type <span class="text-danger">*</span>
                        </label>
                        <select id="ar_animal_type" name="animal_type" class="form-control" required>
                            <option value="" disabled <?= empty($_POST['animal_type']) ? 'selected' : '' ?>>Select type...</option>
                            <?php foreach (['Dog', 'Cat'] as $t): ?>
                            <option value="<?= e($t) ?>" <?= (($_POST['animal_type'] ?? '') === $t) ? 'selected' : '' ?>>
                                <?= e($t) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label class="form-label" for="ar_proof_photo">
                            Proof Photo <span class="text-danger">*</span>
                        </label>
                        <input
                            type="file"
                            id="ar_proof_photo"
                            name="proof_photo"
                            class="form-control"
                            accept=".jpg,.jpeg,.png,.webp"
                            required
                        >
                        <div class="ar-photo-hint">JPG, PNG, or WEBP only. Max 5 MB.</div>
                    </div>

                    <div class="field">
                        <label class="form-label" for="ar_status_desc">
                            Description of Status <span class="text-danger">*</span>
                        </label>
                        <textarea
                            id="ar_status_desc"
                            name="status_desc"
                            class="form-control"
                            rows="4"
                            maxlength="1000"
                            placeholder="Describe the animal's condition, e.g. injured, abandoned, sick, or stray."
                            required
                        ><?= e($_POST['status_desc'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
                        <i class="fa-solid fa-paper-plane"></i> Submit Report
                    </button>
                </form>
            </div>
        </div>

        <!-- Past Reports -->
        <div class="ar-right-col">
            <div class="ar-panel-header" style="background:var(--card-bg,#fff);border:1px solid var(--border);border-radius:12px 12px 0 0;margin-bottom:0">
                <i class="fa-solid fa-list" style="font-size:1rem;color:var(--accent,#b45309)"></i>
                <h2 class="ar-panel-title">My Submitted Reports</h2>
            </div>

            <?php if ($myReports): ?>
            <div class="ar-table-wrap" style="border-radius:0 0 12px 12px;border-top:none">
                <table class="ar-table">
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>Animal</th>
                            <th>Location</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($myReports as $r): ?>
                    <tr>
                        <td>
                            <?php if ($r['proof_photo']): ?>
                            <img
                                src="<?= e($reportUploadUrl . rawurlencode($r['proof_photo'])) ?>"
                                alt="Report photo"
                                class="ar-thumb"
                            >
                            <?php else: ?>
                            <div class="ar-thumb-placeholder">
                                <i class="fa-solid fa-image"></i>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="ar-type-pill">
                                <i class="fa-solid <?= $r['animal_type'] === 'Cat' ? 'fa-cat' : 'fa-dog' ?>"></i>
                                <?= e($r['animal_type']) ?>
                            </span>
                        </td>
                        <td style="max-width:160px;word-break:break-word;color:var(--muted-fg);font-size:.85rem">
                            <?= e($r['location']) ?>
                        </td>
                        <td style="max-width:200px;color:var(--muted-fg);font-size:.85rem">
                            <?= e(mb_strimwidth($r['status_desc'], 0, 80, '…')) ?>
                        </td>
                        <td><?php
                            $badgeMap = [
                                'Pending'      => 'badge-warning',
                                'New'          => 'badge-info',
                                'Acknowledged' => 'badge-success',
                                'Rescued'      => 'badge-success',
                            ];
                            $cls = $badgeMap[$r['status']] ?? 'badge-muted';
                            echo '<span class="badge ' . $cls . '">' . e($r['status']) . '</span>';
                        ?></td>
                        <td style="white-space:nowrap;color:var(--muted-fg);font-size:.8rem">
                            <?= date('M j, Y', strtotime($r['created_at'])) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php else: ?>
            <div class="ar-empty">
                <i class="fa-solid fa-paw"></i>
                <h3>No reports yet</h3>
                <p>Use the form on the left to report a dog or cat that needs help.</p>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /.ar-layout -->

</div>
</div>
<?php include __DIR__ . '/../../includes/dash-foot.php'; ?>