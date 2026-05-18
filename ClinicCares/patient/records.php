<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('patient');

$patient   = db()->fetchOne("SELECT p.* FROM patients p WHERE p.user_id=?", [$_SESSION['user_id']]);
$patientId = $patient['id'];

$search       = sanitize($_GET['q'] ?? '');
$doctorFilter = (int)($_GET['doctor'] ?? 0);
$dateFrom     = sanitize($_GET['date_from'] ?? '');
$dateTo       = sanitize($_GET['date_to'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($page - 1) * ITEMS_PER_PAGE;

$where  = "mr.patient_id=?";
$params = [$patientId];

if ($search) {
    $where .= " AND (mr.diagnosis LIKE ? OR mr.symptoms LIKE ? OR mr.treatment LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $s = "%$search%"; $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
}
if ($doctorFilter) { $where .= " AND mr.doctor_id=?";   $params[] = $doctorFilter; }
if ($dateFrom)     { $where .= " AND mr.record_date>=?"; $params[] = $dateFrom; }
if ($dateTo)       { $where .= " AND mr.record_date<=?"; $params[] = $dateTo; }

$baseQ = "FROM medical_records mr
    JOIN doctors d ON mr.doctor_id=d.id
    JOIN users u ON d.user_id=u.id
    WHERE $where";

$total   = db()->fetchOne("SELECT COUNT(*) as c $baseQ", $params)['c'];
$records = db()->fetchAll(
    "SELECT mr.*, CONCAT(u.first_name,' ',u.last_name) as doctor_name, d.specialization
     $baseQ ORDER BY mr.record_date DESC LIMIT ? OFFSET ?",
    array_merge($params, [ITEMS_PER_PAGE, $offset])
);
$totalPages = ceil($total / ITEMS_PER_PAGE);

// Doctors this patient has records from
$myDoctors = db()->fetchAll(
    "SELECT DISTINCT d.id, CONCAT(u.first_name,' ',u.last_name) as name
     FROM medical_records mr
     JOIN doctors d ON mr.doctor_id=d.id
     JOIN users u ON d.user_id=u.id
     WHERE mr.patient_id=? ORDER BY u.first_name",
    [$patientId]
);

$activeFilters = (int)($search!=='') + (int)($doctorFilter>0) + (int)($dateFrom!=='') + (int)($dateTo!=='');

$pageTitle = 'Health Records';
$activeNav = 'records';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>My Health Records</h1>
    <p>Complete history of your medical consultations and diagnoses</p>
</div>

<!-- Search & Filter Bar -->
<div class="search-bar">
    <div class="search-input-wrap">
        <span class="search-icon">🔍</span>
        <input type="text" id="hrSearch" placeholder="Search diagnosis, symptoms, or treatment…"
               value="<?= htmlspecialchars($search) ?>" onkeyup="debounceHR()">
    </div>
    <?php if (!empty($myDoctors)): ?>
    <select class="form-select" style="width:190px;" id="hrDoctor" onchange="applyHRF()">
        <option value="">All Doctors</option>
        <?php foreach ($myDoctors as $doc): ?>
            <option value="<?=$doc['id']?>" <?= $doctorFilter===$doc['id']?'selected':'' ?>>
                Dr. <?= htmlspecialchars($doc['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <input type="date" class="form-control" style="width:148px;" id="hrDateFrom"
           value="<?= htmlspecialchars($dateFrom) ?>" onchange="applyHRF()" title="Records from date">
    <input type="date" class="form-control" style="width:148px;" id="hrDateTo"
           value="<?= htmlspecialchars($dateTo) ?>" onchange="applyHRF()" title="Records to date">
    <?php if ($activeFilters > 0): ?>
    <a href="records.php" class="btn btn-secondary btn-sm" style="white-space:nowrap;">
        ✕ Clear <span class="badge badge-danger" style="margin-left:4px;"><?= $activeFilters ?></span>
    </a>
    <?php endif; ?>
</div>

<?php if ($activeFilters > 0): ?>
<div style="margin:-10px 0 14px;font-size:13px;color:var(--text-muted);">
    <?php $parts = [];
    if ($search)        $parts[] = 'Keyword: <strong>'.htmlspecialchars($search).'</strong>';
    if ($doctorFilter) {
        $dn = array_filter($myDoctors, fn($d) => $d['id'] == $doctorFilter);
        if ($dn) $parts[] = 'Doctor: <strong>Dr. '.htmlspecialchars(reset($dn)['name']).'</strong>';
    }
    if ($dateFrom) $parts[] = 'From: <strong>'.htmlspecialchars($dateFrom).'</strong>';
    if ($dateTo)   $parts[] = 'To: <strong>'.htmlspecialchars($dateTo).'</strong>';
    echo implode(' &middot; ', $parts).' &mdash; '.$total.' record'.($total!==1?'s':''); ?>
</div>
<?php endif; ?>

<?php if (empty($records)): ?>
<div class="card">
    <div class="card-body" style="text-align:center;padding:60px;">
        <div style="font-size:56px;margin-bottom:16px;">📋</div>
        <h3 style="font-family:var(--font-display);margin-bottom:8px;">No health records found</h3>
        <p style="color:var(--text-muted);">
            <?= $activeFilters > 0 ? 'Try adjusting your search or filters.' : 'Your medical records will appear here after consultations.' ?>
        </p>
        <?php if ($activeFilters > 0): ?>
        <a href="records.php" class="btn btn-secondary" style="margin-top:12px;">Clear all filters</a>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<div class="timeline">
    <?php foreach ($records as $rec): ?>
    <div class="timeline-item">
        <div class="timeline-dot"></div>
        <div class="timeline-date"><?= formatDate($rec['record_date']) ?></div>
        <div class="timeline-content" style="padding:16px 18px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
                <div>
                    <h4 style="font-size:15px;margin-bottom:3px;">
                        <?= htmlspecialchars($rec['diagnosis']) ?>
                    </h4>
                    <div style="font-size:13px;color:var(--text-muted);">
                        Dr. <?= htmlspecialchars($rec['doctor_name']) ?> · <?= htmlspecialchars($rec['specialization']) ?>
                    </div>
                </div>
                <?php if ($rec['follow_up_date']): ?>
                <span class="badge badge-info">Follow-up: <?= formatDate($rec['follow_up_date']) ?></span>
                <?php endif; ?>
            </div>

            <?php if ($rec['symptoms']): ?>
            <div style="margin-bottom:8px;">
                <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;">Symptoms</div>
                <div style="font-size:13px;"><?= nl2br(htmlspecialchars($rec['symptoms'])) ?></div>
            </div>
            <?php endif; ?>

            <?php if ($rec['treatment']): ?>
            <div style="margin-bottom:8px;">
                <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;">Treatment</div>
                <div style="font-size:13px;"><?= nl2br(htmlspecialchars($rec['treatment'])) ?></div>
            </div>
            <?php endif; ?>

            <!-- Vitals -->
            <?php if ($rec['vital_bp'] || $rec['vital_temp'] || $rec['vital_pulse'] || $rec['vital_weight'] || $rec['vital_height']): ?>
            <div style="margin-top:10px;">
                <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">Vitals</div>
                <div style="display:flex;flex-wrap:wrap;gap:8px;">
                    <?php
                    $vitals = [
                        ['label'=>'BP',     'value'=>$rec['vital_bp'],                               'color'=>'var(--primary)'],
                        ['label'=>'Temp',   'value'=>$rec['vital_temp']?$rec['vital_temp'].'°C':null,'color'=>'var(--accent)'],
                        ['label'=>'Pulse',  'value'=>$rec['vital_pulse']?$rec['vital_pulse'].' bpm':null,'color'=>'var(--danger)'],
                        ['label'=>'Weight', 'value'=>$rec['vital_weight'],                           'color'=>'var(--secondary)'],
                        ['label'=>'Height', 'value'=>$rec['vital_height'],                           'color'=>'var(--info)'],
                    ];
                    foreach ($vitals as $v):
                        if (!$v['value']) continue;
                    ?>
                    <div style="background:var(--surface-2);border:1px solid var(--border);border-radius:8px;padding:8px 12px;text-align:center;min-width:80px;">
                        <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;font-weight:700;"><?= $v['label'] ?></div>
                        <div style="font-size:14px;font-weight:700;color:<?= $v['color'] ?>;margin-top:2px;"><?= htmlspecialchars($v['value']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($rec['notes']): ?>
            <div style="margin-top:10px;padding:10px 12px;background:var(--info-light);border-radius:6px;font-size:13px;color:var(--info);">
                💬 <?= nl2br(htmlspecialchars($rec['notes'])) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($totalPages > 1): ?>
<div style="display:flex;justify-content:center;gap:8px;margin-top:20px;">
    <?php
    $qStr = http_build_query(array_filter([
        'q'         => $search,
        'doctor'    => $doctorFilter ?: '',
        'date_from' => $dateFrom,
        'date_to'   => $dateTo,
    ]));
    for ($p=1;$p<=$totalPages;$p++): ?>
        <a href="?<?=$qStr?>&page=<?=$p?>" class="btn btn-sm <?=$p===$page?'btn-primary':'btn-secondary'?>"><?=$p?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<script>
var _hrT;
function debounceHR() { clearTimeout(_hrT); _hrT = setTimeout(applyHRF, 400); }
function applyHRF() {
    const p = new URLSearchParams();
    const q  = document.getElementById('hrSearch').value.trim();
    const dr = document.getElementById('hrDoctor') ? document.getElementById('hrDoctor').value : '';
    const df = document.getElementById('hrDateFrom').value;
    const dt = document.getElementById('hrDateTo').value;
    if (q)  p.set('q', q);
    if (dr) p.set('doctor', dr);
    if (df) p.set('date_from', df);
    if (dt) p.set('date_to', dt);
    window.location.href = 'records.php?' + p.toString();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
