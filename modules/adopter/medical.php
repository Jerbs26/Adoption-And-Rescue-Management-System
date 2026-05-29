<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
session_start_once();
require_login(BASE_URL . '/login.php');
require_role('adopter');

$user       = current_user();
$activePage = 'medical';
$pageTitle  = 'Medical Records';

$stmt = db()->prepare("
    SELECT
        mr.id,
        mr.record_type,
        mr.description,
        mr.record_date,
        mr.next_due_date,
        mr.vet_name,
        mr.clinic_name,
        p.id    AS pet_id,
        p.name  AS pet_name,
        p.type  AS pet_type,
        p.breed AS pet_breed
    FROM medical_records mr
    JOIN pets p
        ON p.id = mr.pet_id
    JOIN adoption_applications aa
        ON aa.pet_id     = p.id
        AND aa.adopter_id = :uid
        AND aa.status     = 'Approved'
    ORDER BY p.name ASC, mr.record_date DESC
");
$stmt->execute([':uid' => $user['id']]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$by_pet = [];
foreach ($records as $rec) {
    $pid = (int)$rec['pet_id'];
    $by_pet[$pid]['info'] = [
        'name'  => $rec['pet_name'],
        'type'  => $rec['pet_type'],
        'breed' => $rec['pet_breed'],
    ];
    $by_pet[$pid]['records'][] = $rec;
}

/** Null-safe date formatter */
function fmt_date_med(?string $val): string {
    if (empty($val)) return '—';
    $ts = strtotime($val);
    return ($ts !== false) ? date('M j, Y', $ts) : '—';
}

include __DIR__ . '/../../includes/dash-head.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<style>
/* Medical table responsive wrapper */
.med-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.med-table { width: 100%; border-collapse: collapse; min-width: 560px; }
.med-table thead tr { background: var(--muted); text-align: left; }
.med-table th {
    padding: .65rem 1rem; font-size: .78rem; text-transform: uppercase;
    letter-spacing: .06em; color: var(--muted-fg); white-space: nowrap;
}
.med-table td { padding: .75rem 1rem; border-bottom: 1px solid var(--border); vertical-align: middle; }
.med-table tbody tr:last-child td { border-bottom: none; }
.med-table tbody tr:hover { background: hsla(35,55%,92%,.35); }
/* Pet section header */
.med-pet-header {
    padding: 1rem 1.25rem; border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: .75rem; flex-wrap: wrap;
}
.med-pet-icon {
    width: 38px; height: 38px; border-radius: 50%;
    background: hsl(145 28% 38%/.12); color: var(--primary);
    display: grid; place-items: center; font-size: 1rem; flex-shrink: 0;
}
</style>

<div class="main-content">
<?php include __DIR__ . '/../../includes/topbar.php'; ?>
<div class="main-body">

<div class="page-header">
    <h1 style="font-size:1.5rem;font-weight:800;margin:0 0 .25rem">Medical Records</h1>
    <p class="muted" style="margin:0;font-size:.875rem">Health history for your adopted pets.</p>
</div>

<?php if (empty($by_pet)): ?>
<div class="empty-state">
    <i class="fa-solid fa-notes-medical"></i>
    <h3>No medical records found</h3>
    <p class="muted">Records will appear here once your adoption is approved and the admin logs health entries for your pet.</p>
</div>

<?php else: ?>
    <?php foreach ($by_pet as $pet_id => $data):
        $info = $data['info'];
        $recs = $data['records'];
    ?>
    <div class="card" style="margin-bottom:1.75rem">

        <!-- Pet header -->
        <div class="med-pet-header">
            <div class="med-pet-icon"><i class="fa-solid fa-paw"></i></div>
            <div style="flex:1;min-width:0">
                <strong style="font-size:1rem;font-family:'Fraunces',serif"><?= e($info['name']) ?></strong>
                <span class="muted small" style="margin-left:.5rem">
                    <?= e($info['type']) ?>
                    <?php if (!empty($info['breed'])): ?>
                        &middot; <?= e($info['breed']) ?>
                    <?php endif; ?>
                </span>
            </div>
            <span class="badge badge-success">Adopted</span>
        </div>

        <!-- Records table -->
        <div class="med-table-wrap">
            <table class="med-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Vet / Clinic</th>
                        <th>Date</th>
                        <th>Next Due</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recs as $rec): ?>
                <tr>
                    <td><span class="badge badge-info"><?= e($rec['record_type']) ?></span></td>
                    <td><?= e($rec['description']) ?></td>
                    <td class="muted small"><?php
                        $vet    = trim($rec['vet_name']    ?? '');
                        $clinic = trim($rec['clinic_name'] ?? '');
                        if ($vet && $clinic)  echo e($vet) . ' &middot; ' . e($clinic);
                        elseif ($vet)         echo e($vet);
                        elseif ($clinic)      echo e($clinic);
                        else                  echo '—';
                    ?></td>
                    <td style="white-space:nowrap"><?= fmt_date_med($rec['record_date']) ?></td>
                    <td style="white-space:nowrap"><?php
                        $due_str = fmt_date_med($rec['next_due_date']);
                        if ($due_str === '—') {
                            echo '—';
                        } else {
                            $overdue = !empty($rec['next_due_date']) && strtotime($rec['next_due_date']) < time();
                            $color   = $overdue ? 'var(--danger)' : 'var(--success)';
                            $icon    = $overdue ? 'fa-circle-exclamation' : 'fa-circle-check';
                            echo "<span style='color:{$color}'>"
                                . "<i class='fa-solid {$icon}' style='font-size:.7rem;margin-right:.3rem'></i>"
                                . e($due_str)
                                . "</span>";
                        }
                    ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
    <?php endforeach; ?>
<?php endif; ?>

</div>
</div>
<?php include __DIR__ . '/../../includes/dash-foot.php'; ?>