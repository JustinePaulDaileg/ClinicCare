<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('patient');

$patient   = db()->fetchOne("SELECT p.* FROM patients p WHERE p.user_id=?", [$_SESSION['user_id']]);
$patientId = $patient['id'];

$search       = sanitize($_GET['q'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');
$doctorFilter = (int)($_GET['doctor'] ?? 0);
$dateFrom     = sanitize($_GET['date_from'] ?? '');
$dateTo       = sanitize($_GET['date_to'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($page - 1) * ITEMS_PER_PAGE;

$where  = "pr.patient_id=?";
$params = [$patientId];

if ($search) {
    $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR d.specialization LIKE ? OR pr.prescription_number LIKE ?)";
    $s = "%$search%"; $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
}
if ($statusFilter) { $where .= " AND pr.status=?";        $params[] = $statusFilter; }
if ($doctorFilter) { $where .= " AND pr.doctor_id=?";     $params[] = $doctorFilter; }
if ($dateFrom)     { $where .= " AND pr.issue_date>=?";   $params[] = $dateFrom; }
if ($dateTo)       { $where .= " AND pr.issue_date<=?";   $params[] = $dateTo; }

$baseQ = "FROM prescriptions pr
    JOIN doctors d ON pr.doctor_id=d.id
    JOIN users u ON d.user_id=u.id
    WHERE $where";

$total = db()->fetchOne("SELECT COUNT(*) as c $baseQ", $params)['c'];
$prescriptions = db()->fetchAll(
    "SELECT pr.*, CONCAT(u.first_name,' ',u.last_name) as doctor_name, d.specialization
     $baseQ ORDER BY pr.created_at DESC LIMIT ? OFFSET ?",
    array_merge($params, [ITEMS_PER_PAGE, $offset])
);
$totalPages = ceil($total / ITEMS_PER_PAGE);

// Doctor list for filter dropdown (only this patient's doctors)
$myDoctors = db()->fetchAll(
    "SELECT DISTINCT d.id, CONCAT(u.first_name,' ',u.last_name) as name
     FROM prescriptions pr
     JOIN doctors d ON pr.doctor_id=d.id
     JOIN users u ON d.user_id=u.id
     WHERE pr.patient_id=? ORDER BY u.first_name",
    [$patientId]
);

$activeFilters = (int)($search!=='') + (int)($statusFilter!=='') + (int)($doctorFilter>0) + (int)($dateFrom!=='') + (int)($dateTo!=='');

$pageTitle = 'My Prescriptions';
$activeNav = 'prescriptions';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>My Prescriptions</h1>
    <p>View and print your medical prescriptions</p>
</div>

<!-- Search & Filter Bar -->
<div class="search-bar">
    <div class="search-input-wrap">
        <span class="search-icon">🔍</span>
        <input type="text" id="rxSearch" placeholder="Search Rx number or doctor name…"
               value="<?= htmlspecialchars($search) ?>" onkeyup="debounceRx()">
    </div>
    <select class="form-select" style="width:160px;" id="rxStatus" onchange="applyRxF()">
        <option value="">All Prescriptions</option>
        <option value="active"    <?= $statusFilter==='active'?'selected':''    ?>>Active</option>
        <option value="completed" <?= $statusFilter==='completed'?'selected':'' ?>>Completed</option>
        <option value="cancelled" <?= $statusFilter==='cancelled'?'selected':'' ?>>Cancelled</option>
    </select>
    <?php if (!empty($myDoctors)): ?>
    <select class="form-select" style="width:175px;" id="rxDoctor" onchange="applyRxF()">
        <option value="">All Doctors</option>
        <?php foreach ($myDoctors as $doc): ?>
            <option value="<?=$doc['id']?>" <?= $doctorFilter===$doc['id']?'selected':'' ?>>
                Dr. <?= htmlspecialchars($doc['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <input type="date" class="form-control" style="width:148px;" id="rxDateFrom"
           value="<?= htmlspecialchars($dateFrom) ?>" onchange="applyRxF()" title="Issued from">
    <input type="date" class="form-control" style="width:148px;" id="rxDateTo"
           value="<?= htmlspecialchars($dateTo) ?>" onchange="applyRxF()" title="Issued to">
    <?php if ($activeFilters > 0): ?>
    <a href="prescriptions.php" class="btn btn-secondary btn-sm" style="white-space:nowrap;">
        ✕ Clear <span class="badge badge-danger" style="margin-left:4px;"><?= $activeFilters ?></span>
    </a>
    <?php endif; ?>
</div>

<?php if ($activeFilters > 0): ?>
<div style="margin:-10px 0 14px;font-size:13px;color:var(--text-muted);">
    <?php $parts = [];
    if ($search)        $parts[] = 'Keyword: <strong>'.htmlspecialchars($search).'</strong>';
    if ($statusFilter)  $parts[] = 'Status: <strong>'.ucfirst($statusFilter).'</strong>';
    if ($doctorFilter) {
        $dn = array_filter($myDoctors, fn($d) => $d['id'] == $doctorFilter);
        if ($dn) $parts[] = 'Doctor: <strong>Dr. '.htmlspecialchars(reset($dn)['name']).'</strong>';
    }
    if ($dateFrom) $parts[] = 'From: <strong>'.htmlspecialchars($dateFrom).'</strong>';
    if ($dateTo)   $parts[] = 'To: <strong>'.htmlspecialchars($dateTo).'</strong>';
    echo implode(' &middot; ', $parts).' &mdash; '.$total.' result'.($total!==1?'s':''); ?>
</div>
<?php endif; ?>

<?php if (empty($prescriptions)): ?>
<div class="card">
    <div class="card-body" style="text-align:center;padding:60px;">
        <div style="font-size:56px;margin-bottom:16px;">💊</div>
        <h3 style="font-family:var(--font-display);margin-bottom:8px;">No prescriptions found</h3>
        <p style="color:var(--text-muted);">
            <?= $activeFilters > 0 ? 'Try adjusting your search or filters.' : 'Your prescriptions from doctors will appear here.' ?>
        </p>
        <?php if ($activeFilters > 0): ?>
        <a href="prescriptions.php" class="btn btn-secondary" style="margin-top:12px;">Clear all filters</a>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>

<div style="display:flex;flex-direction:column;gap:14px;">
    <?php foreach ($prescriptions as $rx): ?>
    <?php
        $items     = db()->fetchAll("SELECT * FROM prescription_items WHERE prescription_id=?", [$rx['id']]);
        $isExpired = $rx['valid_until'] && strtotime($rx['valid_until']) < time();
    ?>
    <div class="card">
        <div style="padding:18px 20px;display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap;">
            <!-- Icon -->
            <div style="width:52px;height:52px;background:<?= $rx['status']==='active'&&!$isExpired?'#eff6ff':'var(--surface-2)' ?>;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0;">💊</div>

            <!-- Info -->
            <div style="flex:1;min-width:200px;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;flex-wrap:wrap;">
                    <span style="font-family:monospace;font-weight:700;font-size:15px;color:var(--primary);"><?= htmlspecialchars($rx['prescription_number']) ?></span>
                    <?php if ($isExpired && $rx['status']==='active'): ?>
                        <span class="badge badge-danger">Expired</span>
                    <?php else: ?>
                        <span class="badge <?= getStatusBadge($rx['status']) ?>"><?= ucfirst($rx['status']) ?></span>
                    <?php endif; ?>
                </div>
                <div style="font-size:13px;color:var(--text-secondary);">
                    Dr. <?= htmlspecialchars($rx['doctor_name']) ?> · <?= htmlspecialchars($rx['specialization']) ?>
                </div>
                <div style="font-size:13px;color:var(--text-muted);margin-top:2px;">
                    Issued: <?= formatDate($rx['issue_date']) ?>
                    <?php if ($rx['valid_until']): ?>
                        · Valid until: <span style="<?= $isExpired?'color:var(--danger);font-weight:600;':'' ?>"><?= formatDate($rx['valid_until']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($rx['notes']): ?>
                <div style="font-size:13px;color:var(--text-muted);margin-top:4px;font-style:italic;">
                    📝 <?= htmlspecialchars($rx['notes']) ?>
                </div>
                <?php endif; ?>

                <!-- Medications -->
                <?php if (!empty($items)): ?>
                <div style="margin-top:10px;">
                    <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">Medications</div>
                    <div style="display:flex;flex-wrap:wrap;gap:6px;">
                        <?php foreach ($items as $item): ?>
                        <span style="background:var(--surface-2);border:1px solid var(--border);border-radius:6px;padding:4px 10px;font-size:12px;font-weight:500;">
                            <?= htmlspecialchars($item['medication_name']) ?>
                            <?php if ($item['dosage']): ?><span style="color:var(--text-muted);"> <?= htmlspecialchars($item['dosage']) ?></span><?php endif; ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div style="display:flex;gap:8px;flex-shrink:0;">
                <a href="/cliniccares/doctor/print-prescriptions.php?id=<?= $rx['id'] ?>" target="_blank" class="btn btn-primary btn-sm">🖨️ Print Rx</a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($totalPages > 1): ?>
<div style="display:flex;justify-content:center;gap:8px;margin-top:20px;">
    <?php
    $qStr = http_build_query(array_filter([
        'q'          => $search,
        'status'     => $statusFilter,
        'doctor'     => $doctorFilter ?: '',
        'date_from'  => $dateFrom,
        'date_to'    => $dateTo,
    ]));
    for ($p=1;$p<=$totalPages;$p++): ?>
        <a href="?<?=$qStr?>&page=<?=$p?>" class="btn btn-sm <?=$p===$page?'btn-primary':'btn-secondary'?>"><?=$p?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<script>
var _rxT;
function debounceRx() { clearTimeout(_rxT); _rxT = setTimeout(applyRxF, 400); }
function applyRxF() {
    const p = new URLSearchParams();
    const q  = document.getElementById('rxSearch').value.trim();
    const st = document.getElementById('rxStatus').value;
    const dr = document.getElementById('rxDoctor') ? document.getElementById('rxDoctor').value : '';
    const df = document.getElementById('rxDateFrom').value;
    const dt = document.getElementById('rxDateTo').value;
    if (q)  p.set('q', q);
    if (st) p.set('status', st);
    if (dr) p.set('doctor', dr);
    if (df) p.set('date_from', df);
    if (dt) p.set('date_to', dt);
    window.location.href = 'prescriptions.php?' + p.toString();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
