<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('doctor');

$doctor = db()->fetchOne("SELECT d.* FROM doctors d WHERE d.user_id=?", [$_SESSION['user_id']]);
$doctorId = $doctor['id'];

// ─── AJAX handlers ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'save_prescription') {
        $patientId = (int)$_POST['patient_id'];
        $apptId    = $_POST['appointment_id'] ? (int)$_POST['appointment_id'] : null;
        $valid     = $_POST['valid_until'] ?: null;
        $notes     = sanitize($_POST['notes'] ?? '');
        $rxNum     = generatePrescriptionNumber();
        $today     = date('Y-m-d');

        $rxId = db()->insert(
            "INSERT INTO prescriptions (patient_id,doctor_id,appointment_id,prescription_number,issue_date,valid_until,notes,status) VALUES (?,?,?,?,?,?,?,'active')",
            [$patientId, $doctorId, $apptId, $rxNum, $today, $valid, $notes]
        );

        $meds = json_decode($_POST['medications'] ?? '[]', true);
        foreach ($meds as $med) {
            if (!empty($med['name'])) {
                db()->insert(
                    "INSERT INTO prescription_items (prescription_id,medication_name,dosage,frequency,duration,instructions,quantity) VALUES (?,?,?,?,?,?,?)",
                    [$rxId, sanitize($med['name']), sanitize($med['dosage']??''), sanitize($med['frequency']??''), sanitize($med['duration']??''), sanitize($med['instructions']??''), (int)($med['quantity']??1)]
                );
            }
        }

        $patient = db()->fetchOne("SELECT u.id as user_id FROM patients p JOIN users u ON p.user_id=u.id WHERE p.id=?", [$patientId]);
        if ($patient) {
            createNotification($patient['user_id'], 'New Prescription', "Dr. issued prescription $rxNum", 'prescription', '/patient/prescriptions.php');
        }

        echo json_encode(['success'=>true, 'rx_number'=>$rxNum, 'rx_id'=>$rxId]);
        exit;
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $rx = db()->fetchOne("SELECT doctor_id FROM prescriptions WHERE id=?", [$id]);
        if ($rx && $rx['doctor_id'] == $doctorId) {
            db()->execute("DELETE FROM prescriptions WHERE id=?", [$id]);
        }
        echo json_encode(['success'=>true]);
        exit;
    }

    if ($action === 'update_status') {
        $id     = (int)$_POST['id'];
        $status = in_array($_POST['status'],['active','completed','cancelled'])?$_POST['status']:'active';
        db()->execute("UPDATE prescriptions SET status=? WHERE id=? AND doctor_id=?", [$status, $id, $doctorId]);
        echo json_encode(['success'=>true]);
        exit;
    }
}

// ─── Filters ──────────────────────────────────────────────────────────────────
$search       = sanitize($_GET['q'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');
$dateFrom     = sanitize($_GET['date_from'] ?? '');
$dateTo       = sanitize($_GET['date_to'] ?? '');
$patientFilter= (int)($_GET['patient_id'] ?? 0);
$page         = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($page - 1) * ITEMS_PER_PAGE;

$where  = "pr.doctor_id=?";
$params = [$doctorId];

if ($search) {
    $where .= " AND (up.first_name LIKE ? OR up.last_name LIKE ? OR pr.prescription_number LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s]);
}
if ($statusFilter) {
    $where .= " AND pr.status=?";
    $params[] = $statusFilter;
}
if ($dateFrom) {
    $where .= " AND pr.issue_date >= ?";
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where .= " AND pr.issue_date <= ?";
    $params[] = $dateTo;
}
if ($patientFilter) {
    $where .= " AND pr.patient_id=?";
    $params[] = $patientFilter;
}

$baseQ         = "FROM prescriptions pr JOIN patients p ON pr.patient_id=p.id JOIN users up ON p.user_id=up.id WHERE $where";
$total         = db()->fetchOne("SELECT COUNT(*) as c $baseQ", $params)['c'];
$prescriptions = db()->fetchAll(
    "SELECT pr.*, CONCAT(up.first_name,' ',up.last_name) as patient_name $baseQ ORDER BY pr.created_at DESC LIMIT ? OFFSET ?",
    array_merge($params, [ITEMS_PER_PAGE, $offset])
);
$totalPages = ceil($total / ITEMS_PER_PAGE);

$myPatients = db()->fetchAll("
    SELECT DISTINCT p.id, CONCAT(u.first_name,' ',u.last_name) as name
    FROM appointments a JOIN patients p ON a.patient_id=p.id JOIN users u ON p.user_id=u.id
    WHERE a.doctor_id=? ORDER BY u.first_name", [$doctorId]);

$hasFilters = $search || $statusFilter || $dateFrom || $dateTo || $patientFilter;

$qStr = http_build_query(array_filter([
    'q'          => $search,
    'status'     => $statusFilter,
    'date_from'  => $dateFrom,
    'date_to'    => $dateTo,
    'patient_id' => $patientFilter ?: '',
]));

$pageTitle = 'Prescriptions';
$activeNav = 'prescriptions';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-between align-center">
    <div>
        <h1>Prescriptions</h1>
        <p>Create and manage patient prescriptions</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('rxModal')">💊 New Prescription</button>
</div>

<!-- Search & Filter Panel -->
<div class="card" style="margin-bottom:20px;padding:16px 20px;">
    <!-- Row 1: text search + status + patient -->
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
        <div class="search-input-wrap" style="flex:1;min-width:220px;">
            <span class="search-icon">🔍</span>
            <input type="text" id="rxSearch" placeholder="Search patient name or Rx number..." value="<?= htmlspecialchars($search) ?>" onkeyup="debounceApplyRx()">
        </div>

        <select class="form-select" id="rxStatus" style="width:140px;" onchange="applyRxF()">
            <option value="">All Status</option>
            <option value="active"    <?= $statusFilter==='active'?'selected':'' ?>>✅ Active</option>
            <option value="completed" <?= $statusFilter==='completed'?'selected':'' ?>>✔️ Completed</option>
            <option value="cancelled" <?= $statusFilter==='cancelled'?'selected':'' ?>>❌ Cancelled</option>
        </select>

        <select class="form-select" id="rxPatient" style="width:190px;" onchange="applyRxF()">
            <option value="">All Patients</option>
            <?php foreach ($myPatients as $pt): ?>
                <option value="<?= $pt['id'] ?>" <?= $patientFilter==$pt['id']?'selected':'' ?>><?= htmlspecialchars($pt['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Row 2: date range -->
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <span style="font-size:13px;color:var(--text-muted);white-space:nowrap;">📅 Issue date:</span>

        <input type="date" id="rxDateFrom" class="form-control" style="width:160px;padding:9px 10px;font-size:13px;" value="<?= htmlspecialchars($dateFrom) ?>" onchange="applyRxF()">
        <span style="font-size:13px;color:var(--text-muted);">to</span>
        <input type="date" id="rxDateTo" class="form-control" style="width:160px;padding:9px 10px;font-size:13px;" value="<?= htmlspecialchars($dateTo) ?>" onchange="applyRxF()">

        <!-- Quick presets -->
        <div style="display:flex;gap:6px;">
            <button class="btn btn-secondary btn-sm" onclick="rxPreset('today')">Today</button>
            <button class="btn btn-secondary btn-sm" onclick="rxPreset('week')">7d</button>
            <button class="btn btn-secondary btn-sm" onclick="rxPreset('month')">30d</button>
            <button class="btn btn-secondary btn-sm" onclick="rxPreset('year')">1yr</button>
        </div>

        <?php if ($hasFilters): ?>
        <a href="?" class="btn btn-secondary btn-sm" style="white-space:nowrap;">✕ Clear all</a>
        <?php endif; ?>
    </div>

    <!-- Active filter badges -->
    <?php if ($hasFilters): ?>
    <div style="margin-top:10px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <span style="font-size:12px;color:var(--text-muted);">Active filters:</span>
        <?php if ($search): ?>
            <span style="background:var(--primary-light,#ede9fe);color:var(--primary);padding:2px 10px;border-radius:20px;font-size:12px;">🔍 "<?= htmlspecialchars($search) ?>"</span>
        <?php endif; ?>
        <?php if ($statusFilter): ?>
            <span style="background:var(--primary-light,#ede9fe);color:var(--primary);padding:2px 10px;border-radius:20px;font-size:12px;">📌 <?= ucfirst(htmlspecialchars($statusFilter)) ?></span>
        <?php endif; ?>
        <?php if ($dateFrom || $dateTo): ?>
            <span style="background:var(--primary-light,#ede9fe);color:var(--primary);padding:2px 10px;border-radius:20px;font-size:12px;">
                📅 <?= $dateFrom?htmlspecialchars($dateFrom):'…' ?> → <?= $dateTo?htmlspecialchars($dateTo):'…' ?>
            </span>
        <?php endif; ?>
        <?php if ($patientFilter): ?>
            <?php $pName = ''; foreach($myPatients as $pt){if($pt['id']==$patientFilter){$pName=$pt['name'];break;}} ?>
            <span style="background:var(--primary-light,#ede9fe);color:var(--primary);padding:2px 10px;border-radius:20px;font-size:12px;">👤 <?= htmlspecialchars($pName) ?></span>
        <?php endif; ?>
        <span style="font-size:12px;color:var(--text-muted);">— <?= $total ?> prescription<?= $total!=1?'s':'' ?> found</span>
    </div>
    <?php endif; ?>
</div>

<!-- Prescriptions Table -->
<div class="card">
    <div class="card-header">
        <span class="card-title">
            <?= $hasFilters ? "Filtered Results ($total)" : "All Prescriptions ($total)" ?>
        </span>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Rx Number</th>
                    <th>Patient</th>
                    <th>Issued</th>
                    <th>Valid Until</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($prescriptions as $rx): ?>
                <tr>
                    <td>
                        <span style="font-family:monospace;font-weight:700;color:var(--primary);"><?= htmlspecialchars($rx['prescription_number']) ?></span>
                    </td>
                    <td>
                        <a href="?patient_id=<?= $rx['patient_id'] ?>&<?= $qStr ?>" style="color:inherit;text-decoration:none;" title="Filter by this patient">
                            <?= htmlspecialchars($rx['patient_name']) ?>
                        </a>
                    </td>
                    <td><?= formatDate($rx['issue_date']) ?></td>
                    <td>
                        <?php if ($rx['valid_until']): ?>
                            <span style="<?= strtotime($rx['valid_until'])<time()&&$rx['status']==='active'?'color:var(--danger);font-weight:600;':'' ?>">
                                <?= formatDate($rx['valid_until']) ?>
                            </span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td>
                        <select class="form-select" style="width:120px;padding:4px 8px;font-size:12px;" onchange="updateRxStatus(<?=$rx['id']?>,this.value)">
                            <option value="active"    <?=$rx['status']==='active'?'selected':''?>>Active</option>
                            <option value="completed" <?=$rx['status']==='completed'?'selected':''?>>Completed</option>
                            <option value="cancelled" <?=$rx['status']==='cancelled'?'selected':''?>>Cancelled</option>
                        </select>
                    </td>
                    <td>
                        <div class="d-flex gap-8">
                            <a href="/cliniccares/doctor/print-prescriptions.php?id=<?=$rx['id']?>" target="_blank" class="btn btn-secondary btn-sm">🖨️ Print</a>
                            <button class="btn btn-danger btn-sm" onclick="delRx(<?=$rx['id']?>)">🗑️</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($prescriptions)): ?>
                <tr>
                    <td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted);">
                        <?= $hasFilters ? '😔 No prescriptions match your filters. <a href="?" style="color:var(--primary);">Clear filters</a>' : 'No prescriptions found' ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="card-footer d-flex justify-between align-center">
        <span style="font-size:13px;color:var(--text-muted);">Page <?=$page?> of <?=$totalPages?></span>
        <div class="d-flex gap-8">
            <?php for ($p=1;$p<=$totalPages;$p++): ?>
                <a href="?page=<?=$p?>&<?=$qStr?>" class="btn btn-sm <?=$p===$page?'btn-primary':'btn-secondary'?>"><?=$p?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- New Prescription Modal -->
<div class="modal-overlay" id="rxModal">
    <div class="modal" style="max-width:680px;">
        <div class="modal-header">
            <h3 class="modal-title">💊 New Prescription</h3>
            <button class="modal-close" onclick="closeModal('rxModal')">✕</button>
        </div>
        <div class="modal-body">
            <form id="rxForm">
                <input type="hidden" name="action" value="save_prescription">
                <input type="hidden" name="appointment_id" value="">

                <div class="form-group">
                    <label class="form-label">Patient *</label>
                    <select name="patient_id" class="form-select" required>
                        <option value="">Select patient</option>
                        <?php foreach ($myPatients as $pt): ?>
                            <option value="<?=$pt['id']?>"><?= htmlspecialchars($pt['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Issue Date</label>
                        <input type="date" name="issue_date" class="form-control" value="<?= date('Y-m-d') ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Valid Until</label>
                        <input type="date" name="valid_until" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Prescription Notes / Diagnosis</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Diagnosis or general notes..."></textarea>
                </div>

                <div style="margin-bottom:12px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                        <label class="form-label" style="margin:0;">Medications</label>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="addMedRow()">➕ Add Medication</button>
                    </div>
                    <div id="medsList"></div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('rxModal')">Cancel</button>
            <button class="btn btn-primary" onclick="savePrescription()">💾 Save Prescription</button>
        </div>
    </div>
</div>

<script>
// ── Filter logic ─────────────────────────────────────────────────────────────
let _rxTimer;
function debounceApplyRx() {
    clearTimeout(_rxTimer);
    _rxTimer = setTimeout(applyRxF, 400);
}

function applyRxF() {
    const q         = document.getElementById('rxSearch').value;
    const status    = document.getElementById('rxStatus').value;
    const patient   = document.getElementById('rxPatient').value;
    const dateFrom  = document.getElementById('rxDateFrom').value;
    const dateTo    = document.getElementById('rxDateTo').value;
    const params    = new URLSearchParams();
    if (q)         params.set('q', q);
    if (status)    params.set('status', status);
    if (patient)   params.set('patient_id', patient);
    if (dateFrom)  params.set('date_from', dateFrom);
    if (dateTo)    params.set('date_to', dateTo);
    window.location.href = '?' + params.toString();
}

function rxPreset(range) {
    const today = new Date();
    const fmt   = d => d.toISOString().split('T')[0];
    if (range === 'today') {
        document.getElementById('rxDateFrom').value = fmt(today);
        document.getElementById('rxDateTo').value   = fmt(today);
    } else if (range === 'week') {
        const d = new Date(today); d.setDate(today.getDate() - 7);
        document.getElementById('rxDateFrom').value = fmt(d);
        document.getElementById('rxDateTo').value   = fmt(today);
    } else if (range === 'month') {
        const d = new Date(today); d.setDate(today.getDate() - 30);
        document.getElementById('rxDateFrom').value = fmt(d);
        document.getElementById('rxDateTo').value   = fmt(today);
    } else if (range === 'year') {
        const d = new Date(today); d.setFullYear(today.getFullYear() - 1);
        document.getElementById('rxDateFrom').value = fmt(d);
        document.getElementById('rxDateTo').value   = fmt(today);
    }
    applyRxF();
}

// ── Prescription CRUD ─────────────────────────────────────────────────────────
let medCount = 0;
function addMedRow(data) {
    const d = data || {};
    const id = ++medCount;
    const row = document.createElement('div');
    row.id = 'med-'+id;
    row.style.cssText = 'background:var(--surface-2);border:1px solid var(--border);border-radius:8px;padding:14px;margin-bottom:10px;position:relative;';
    row.innerHTML = `
        <button type="button" onclick="document.getElementById('med-${id}').remove()" style="position:absolute;top:8px;right:8px;border:none;background:#fee2e2;color:#b91c1c;width:24px;height:24px;border-radius:6px;cursor:pointer;font-size:12px;">✕</button>
        <div class="grid-2" style="margin-bottom:8px;">
            <div class="form-group" style="margin:0;">
                <label class="form-label">Medication Name *</label>
                <input type="text" class="form-control med-name" placeholder="e.g. Amoxicillin" value="${d.name||''}">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Dosage</label>
                <input type="text" class="form-control med-dosage" placeholder="e.g. 500mg" value="${d.dosage||''}">
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 60px;gap:8px;margin-bottom:8px;">
            <div class="form-group" style="margin:0;">
                <label class="form-label">Frequency</label>
                <select class="form-select med-freq">
                    <option ${d.frequency==='Once daily'?'selected':''}>Once daily</option>
                    <option ${d.frequency==='Twice daily'?'selected':''}>Twice daily</option>
                    <option ${d.frequency==='Three times daily'?'selected':''}>Three times daily</option>
                    <option ${d.frequency==='Four times daily'?'selected':''}>Four times daily</option>
                    <option ${d.frequency==='As needed'?'selected':''}>As needed</option>
                    <option ${d.frequency==='Every 8 hours'?'selected':''}>Every 8 hours</option>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Duration</label>
                <input type="text" class="form-control med-dur" placeholder="e.g. 7 days" value="${d.duration||''}">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Qty</label>
                <input type="number" class="form-control med-qty" min="1" value="${d.quantity||1}" style="padding:10px 8px;">
            </div>
        </div>
        <div class="form-group" style="margin:0;">
            <label class="form-label">Instructions</label>
            <input type="text" class="form-control med-instr" placeholder="e.g. Take with food" value="${d.instructions||''}">
        </div>
    `;
    document.getElementById('medsList').appendChild(row);
}

function collectMeds() {
    const rows = document.querySelectorAll('[id^="med-"]');
    return Array.from(rows).map(row => ({
        name: row.querySelector('.med-name')?.value || '',
        dosage: row.querySelector('.med-dosage')?.value || '',
        frequency: row.querySelector('.med-freq')?.value || '',
        duration: row.querySelector('.med-dur')?.value || '',
        quantity: row.querySelector('.med-qty')?.value || 1,
        instructions: row.querySelector('.med-instr')?.value || '',
    })).filter(m => m.name.trim());
}

function savePrescription() {
    const meds = collectMeds();
    if (!meds.length) { showToast('Add at least one medication','warning'); return; }
    const patientId = document.querySelector('[name=patient_id]').value;
    if (!patientId) { showToast('Select a patient','warning'); return; }

    const fd = new FormData(document.getElementById('rxForm'));
    fd.set('medications', JSON.stringify(meds));

    fetch('', {method:'POST', body:fd}).then(r=>r.json()).then(res=>{
        if (res.success) {
            showToast('Prescription ' + res.rx_number + ' created!', 'success');
            closeModal('rxModal');
            location.reload();
        } else showToast(res.error||'Error','danger');
    });
}

function updateRxStatus(id, status) {
    const d = new FormData(); d.set('action','update_status'); d.set('id',id); d.set('status',status);
    fetch('',{method:'POST',body:d}).then(r=>r.json()).then(res=>{
        if(res.success) showToast('Status updated','success');
    });
}

function delRx(id) {
    confirmAction('Delete this prescription?', () => {
        const d = new FormData(); d.set('action','delete'); d.set('id',id);
        fetch('',{method:'POST',body:d}).then(r=>r.json()).then(res=>{
            if(res.success){showToast('Deleted','success');location.reload();}
        });
    });
}

addMedRow();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
