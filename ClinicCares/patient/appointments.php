<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('patient');

$patient   = db()->fetchOne("SELECT p.* FROM patients p WHERE p.user_id=?", [$_SESSION['user_id']]);
$patientId = $patient['id'];

// Cancel appointment (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
    header('Content-Type: application/json');
    $id   = (int)$_POST['cancel_id'];
    $appt = db()->fetchOne("SELECT * FROM appointments WHERE id=? AND patient_id=?", [$id, $patientId]);
    if ($appt && in_array($appt['status'],['pending','confirmed'])) {
        if (strtotime($appt['appointment_date']) > time()) {
            db()->execute("UPDATE appointments SET status='cancelled' WHERE id=?", [$id]);
            echo json_encode(['success'=>true]);
        } else {
            echo json_encode(['success'=>false,'error'=>'Cannot cancel past appointments']);
        }
    } else {
        echo json_encode(['success'=>false,'error'=>'Cannot cancel this appointment']);
    }
    exit;
}

$search       = sanitize($_GET['q'] ?? '');
$filter       = sanitize($_GET['filter'] ?? 'upcoming');
$typeFilter   = sanitize($_GET['type'] ?? '');
$doctorFilter = (int)($_GET['doctor'] ?? 0);
$dateFrom     = sanitize($_GET['date_from'] ?? '');
$dateTo       = sanitize($_GET['date_to'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($page - 1) * ITEMS_PER_PAGE;

$where  = "a.patient_id=?";
$params = [$patientId];

// Tab filter
if ($filter === 'upcoming') {
    $where .= " AND a.appointment_date >= CURDATE() AND a.status NOT IN ('cancelled')";
} elseif ($filter === 'past') {
    $where .= " AND (a.appointment_date < CURDATE() OR a.status IN ('completed','cancelled'))";
} elseif ($filter === 'cancelled') {
    $where .= " AND a.status='cancelled'";
}

// Additional filters
if ($search) {
    $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR d.specialization LIKE ? OR a.reason LIKE ?)";
    $s = "%$search%"; $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
}
if ($typeFilter)   { $where .= " AND a.type=?";               $params[] = $typeFilter; }
if ($doctorFilter) { $where .= " AND a.doctor_id=?";          $params[] = $doctorFilter; }
if ($dateFrom)     { $where .= " AND a.appointment_date>=?";  $params[] = $dateFrom; }
if ($dateTo)       { $where .= " AND a.appointment_date<=?";  $params[] = $dateTo; }

$baseQ = "FROM appointments a
    JOIN doctors d ON a.doctor_id=d.id
    JOIN users u ON d.user_id=u.id
    WHERE $where";

$total = db()->fetchOne("SELECT COUNT(*) as c $baseQ", $params)['c'];
$appointments = db()->fetchAll(
    "SELECT a.*, CONCAT(u.first_name,' ',u.last_name) as doctor_name, d.specialization, d.consultation_fee
     $baseQ ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT ? OFFSET ?",
    array_merge($params, [ITEMS_PER_PAGE, $offset])
);
$totalPages = ceil($total / ITEMS_PER_PAGE);

// Tab counts
$upcomingCount  = db()->fetchOne("SELECT COUNT(*) as c FROM appointments WHERE patient_id=? AND appointment_date>=CURDATE() AND status NOT IN ('cancelled')", [$patientId])['c'];
$pastCount      = db()->fetchOne("SELECT COUNT(*) as c FROM appointments WHERE patient_id=? AND (appointment_date<CURDATE() OR status IN ('completed','cancelled'))", [$patientId])['c'];
$cancelledCount = db()->fetchOne("SELECT COUNT(*) as c FROM appointments WHERE patient_id=? AND status='cancelled'", [$patientId])['c'];

// Doctors this patient has appointments with
$myDoctors = db()->fetchAll(
    "SELECT DISTINCT d.id, CONCAT(u.first_name,' ',u.last_name) as name
     FROM appointments a JOIN doctors d ON a.doctor_id=d.id JOIN users u ON d.user_id=u.id
     WHERE a.patient_id=? ORDER BY u.first_name",
    [$patientId]
);

$activeFilters = (int)($search!=='') + (int)($typeFilter!=='') + (int)($doctorFilter>0) + (int)($dateFrom!=='') + (int)($dateTo!=='');

$pageTitle = 'My Appointments';
$activeNav = 'appointments';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-between align-center">
    <div>
        <h1>My Appointments</h1>
        <p>View and manage your appointment history</p>
    </div>
    <a href="/cliniccares/patient/book.php" class="btn btn-primary">➕ Book New</a>
</div>

<!-- Tab Filter -->
<div class="tabs tab-group" style="margin-bottom:20px;">
    <a href="?filter=upcoming<?= $search?"&q=".urlencode($search):'' ?>" class="tab-btn <?= $filter==='upcoming'?'active':'' ?>" style="text-decoration:none;">
        Upcoming <span class="badge badge-primary" style="margin-left:4px;"><?= $upcomingCount ?></span>
    </a>
    <a href="?filter=past<?= $search?"&q=".urlencode($search):'' ?>" class="tab-btn <?= $filter==='past'?'active':'' ?>" style="text-decoration:none;">
        Past / Completed <span class="badge badge-secondary" style="margin-left:4px;"><?= $pastCount ?></span>
    </a>
    <a href="?filter=cancelled<?= $search?"&q=".urlencode($search):'' ?>" class="tab-btn <?= $filter==='cancelled'?'active':'' ?>" style="text-decoration:none;">
        Cancelled <span class="badge badge-secondary" style="margin-left:4px;"><?= $cancelledCount ?></span>
    </a>
    <a href="?filter=all<?= $search?"&q=".urlencode($search):'' ?>" class="tab-btn <?= $filter==='all'?'active':'' ?>" style="text-decoration:none;">
        All
    </a>
</div>

<!-- Search & Filter Bar -->
<div class="search-bar">
    <div class="search-input-wrap">
        <span class="search-icon">🔍</span>
        <input type="text" id="apptSearch" placeholder="Search doctor, specialization, or reason…"
               value="<?= htmlspecialchars($search) ?>" onkeyup="debounceAF()">
    </div>
    <select class="form-select" style="width:155px;" id="apptType" onchange="applyAF()">
        <option value="">All Types</option>
        <?php foreach (['consultation','follow_up','check_up','emergency'] as $t): ?>
            <option value="<?=$t?>" <?= $typeFilter===$t?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$t)) ?></option>
        <?php endforeach; ?>
    </select>
    <?php if (!empty($myDoctors)): ?>
    <select class="form-select" style="width:185px;" id="apptDoctor" onchange="applyAF()">
        <option value="">All Doctors</option>
        <?php foreach ($myDoctors as $doc): ?>
            <option value="<?=$doc['id']?>" <?= $doctorFilter===$doc['id']?'selected':'' ?>>
                Dr. <?= htmlspecialchars($doc['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <input type="date" class="form-control" style="width:148px;" id="apptDateFrom"
           value="<?= htmlspecialchars($dateFrom) ?>" onchange="applyAF()" title="From date">
    <input type="date" class="form-control" style="width:148px;" id="apptDateTo"
           value="<?= htmlspecialchars($dateTo) ?>" onchange="applyAF()" title="To date">
    <?php if ($activeFilters > 0): ?>
    <a href="appointments.php?filter=<?=$filter?>" class="btn btn-secondary btn-sm" style="white-space:nowrap;">
        ✕ Clear <span class="badge badge-danger" style="margin-left:4px;"><?= $activeFilters ?></span>
    </a>
    <?php endif; ?>
</div>

<?php if ($activeFilters > 0): ?>
<div style="margin:-10px 0 14px;font-size:13px;color:var(--text-muted);">
    <?php $parts = [];
    if ($search)        $parts[] = 'Keyword: <strong>'.htmlspecialchars($search).'</strong>';
    if ($typeFilter)    $parts[] = 'Type: <strong>'.ucfirst(str_replace('_',' ',$typeFilter)).'</strong>';
    if ($doctorFilter) {
        $dn = array_filter($myDoctors, fn($d) => $d['id'] == $doctorFilter);
        if ($dn) $parts[] = 'Doctor: <strong>Dr. '.htmlspecialchars(reset($dn)['name']).'</strong>';
    }
    if ($dateFrom) $parts[] = 'From: <strong>'.htmlspecialchars($dateFrom).'</strong>';
    if ($dateTo)   $parts[] = 'To: <strong>'.htmlspecialchars($dateTo).'</strong>';
    echo implode(' &middot; ', $parts).' &mdash; '.$total.' result'.($total!==1?'s':''); ?>
</div>
<?php endif; ?>

<?php if (empty($appointments)): ?>
<div class="card">
    <div class="card-body" style="text-align:center;padding:60px;">
        <div style="font-size:56px;margin-bottom:16px;">📅</div>
        <h3 style="font-family:var(--font-display);margin-bottom:8px;">No appointments found</h3>
        <p style="color:var(--text-muted);margin-bottom:20px;">
            <?php if ($activeFilters > 0): ?>
                Try adjusting your search or filters.
            <?php elseif ($filter==='upcoming'): ?>
                You have no upcoming appointments.
            <?php else: ?>
                No appointments in this category.
            <?php endif; ?>
        </p>
        <?php if ($activeFilters > 0): ?>
        <a href="appointments.php?filter=<?=$filter?>" class="btn btn-secondary" style="margin-right:8px;">Clear filters</a>
        <?php endif; ?>
        <?php if ($filter === 'upcoming'): ?>
        <a href="/cliniccares/patient/book.php" class="btn btn-primary btn-lg">Book Your First Appointment</a>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:14px;">
    <?php foreach ($appointments as $a): ?>
    <div class="card" style="transition:box-shadow 0.2s;" onmouseover="this.style.boxShadow='var(--shadow)'" onmouseout="this.style.boxShadow='var(--shadow-sm)'">
        <div style="padding:18px 20px;display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap;">
            <!-- Date block -->
            <div style="width:56px;text-align:center;background:<?= $a['status']==='cancelled'?'var(--surface-2)':'var(--primary-pale)' ?>;border-radius:10px;padding:10px 6px;flex-shrink:0;">
                <div style="font-size:22px;font-weight:800;color:<?= $a['status']==='cancelled'?'var(--text-muted)':'var(--primary)' ?>;line-height:1;"><?= date('d',strtotime($a['appointment_date'])) ?></div>
                <div style="font-size:11px;color:<?= $a['status']==='cancelled'?'var(--text-muted)':'var(--primary)' ?>;text-transform:uppercase;font-weight:600;"><?= date('M',strtotime($a['appointment_date'])) ?></div>
                <div style="font-size:10px;color:var(--text-muted);"><?= date('Y',strtotime($a['appointment_date'])) ?></div>
            </div>

            <!-- Info -->
            <div style="flex:1;min-width:200px;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;flex-wrap:wrap;">
                    <span style="font-size:16px;font-weight:700;">Dr. <?= htmlspecialchars($a['doctor_name']) ?></span>
                    <span class="badge badge-info"><?= htmlspecialchars($a['specialization']) ?></span>
                    <span class="badge <?= getStatusBadge($a['status']) ?>"><?= ucfirst(str_replace('_',' ',$a['status'])) ?></span>
                </div>
                <div style="display:flex;gap:16px;font-size:13px;color:var(--text-secondary);flex-wrap:wrap;">
                    <span>🕐 <?= formatTime($a['appointment_time']) ?> – <?= formatTime($a['end_time']) ?></span>
                    <span>🏷️ <?= ucfirst(str_replace('_',' ',$a['type'])) ?></span>
                    <span>💰 <?= formatCurrency($a['consultation_fee']) ?></span>
                </div>
                <?php if ($a['reason']): ?>
                <div style="font-size:13px;color:var(--text-muted);margin-top:6px;">📝 <?= htmlspecialchars($a['reason']) ?></div>
                <?php endif; ?>
                <?php if ($a['notes']): ?>
                <div style="font-size:13px;background:var(--info-light);padding:8px 10px;border-radius:6px;margin-top:8px;color:var(--info);">
                    💬 <?= htmlspecialchars($a['notes']) ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div style="display:flex;gap:8px;flex-shrink:0;align-items:flex-start;flex-wrap:wrap;">
                <?php $canCancel = in_array($a['status'],['pending','confirmed']) && strtotime($a['appointment_date']) > time(); ?>
                <?php if ($canCancel): ?>
                <button class="btn btn-danger btn-sm" onclick="cancelAppt(<?=$a['id']?>)">✕ Cancel</button>
                <?php endif; ?>
                <?php if ($a['status'] === 'completed'): ?>
                <a href="/cliniccares/patient/records.php?appt=<?=$a['id']?>" class="btn btn-secondary btn-sm">📋 View Record</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($totalPages > 1): ?>
<div style="display:flex;justify-content:center;gap:8px;margin-top:20px;">
    <?php
    $qStr = http_build_query(array_filter([
        'filter'    => $filter,
        'q'         => $search,
        'type'      => $typeFilter,
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
var _afT;
function debounceAF() { clearTimeout(_afT); _afT = setTimeout(applyAF, 400); }
function applyAF() {
    const p = new URLSearchParams();
    p.set('filter', '<?= $filter ?>');
    const q  = document.getElementById('apptSearch').value.trim();
    const ty = document.getElementById('apptType').value;
    const dr = document.getElementById('apptDoctor') ? document.getElementById('apptDoctor').value : '';
    const df = document.getElementById('apptDateFrom').value;
    const dt = document.getElementById('apptDateTo').value;
    if (q)  p.set('q', q);
    if (ty) p.set('type', ty);
    if (dr) p.set('doctor', dr);
    if (df) p.set('date_from', df);
    if (dt) p.set('date_to', dt);
    window.location.href = 'appointments.php?' + p.toString();
}
function cancelAppt(id) {
    confirmAction('Cancel this appointment? This action cannot be undone.', () => {
        const fd = new FormData(); fd.set('cancel_id', id);
        fetch('', {method:'POST', body:fd}).then(r=>r.json()).then(res => {
            if (res.success) { showToast('Appointment cancelled', 'success'); location.reload(); }
            else showToast(res.error || 'Cannot cancel', 'danger');
        });
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
