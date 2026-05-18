<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('doctor');

$doctor = db()->fetchOne("SELECT d.* FROM doctors d WHERE d.user_id=?", [$_SESSION['user_id']]);
$doctorId = $doctor['id'];

// AJAX: update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'update_status') {
        $id     = (int)$_POST['id'];
        $status = in_array($_POST['status'],['pending','confirmed','completed','cancelled','no_show'])?$_POST['status']:'pending';
        $appt   = db()->fetchOne("SELECT * FROM appointments WHERE id=? AND doctor_id=?", [$id, $doctorId]);
        if (!$appt) { echo json_encode(['success'=>false,'error'=>'Not found']); exit; }

        db()->execute("UPDATE appointments SET status=? WHERE id=?", [$status, $id]);

        // Notify patient
        $pu = db()->fetchOne("SELECT u.id FROM patients p JOIN users u ON p.user_id=u.id WHERE p.id=?", [$appt['patient_id']]);
        if ($pu) createNotification($pu['id'], 'Appointment Update', "Your appointment on ".formatDate($appt['appointment_date'])." has been $status.", 'appointment', '/patient/appointments.php');

        // If confirmed, send email
        if ($status === 'confirmed') {
            require_once __DIR__.'/../includes/mailer.php';
            $patientUser = db()->fetchOne("SELECT u.*, p.id as pid FROM users u JOIN patients p ON p.user_id=u.id WHERE p.id=?", [$appt['patient_id']]);
            $docUser     = db()->fetchOne("SELECT * FROM users WHERE id=?", [$_SESSION['user_id']]);
            if ($patientUser && $docUser) sendAppointmentConfirmation($appt, $patientUser, $docUser);
        }
        echo json_encode(['success'=>true]);
        exit;
    }

    if ($action === 'add_notes') {
        $id    = (int)$_POST['id'];
        $notes = sanitize($_POST['notes'] ?? '');
        db()->execute("UPDATE appointments SET notes=? WHERE id=? AND doctor_id=?", [$notes, $id, $doctorId]);
        echo json_encode(['success'=>true]);
        exit;
    }
}

$search      = sanitize($_GET['q'] ?? '');
$statusF     = sanitize($_GET['status'] ?? '');
$dateF       = sanitize($_GET['date'] ?? '');
$page        = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($page - 1) * ITEMS_PER_PAGE;

$where  = "a.doctor_id=?"; $params = [$doctorId];
if ($search) { $where .= " AND (up.first_name LIKE ? OR up.last_name LIKE ?)"; $s="%$search%"; $params=array_merge($params,[$s,$s]); }
if ($statusF) { $where .= " AND a.status=?"; $params[]=$statusF; }
if ($dateF)   { $where .= " AND a.appointment_date=?"; $params[]=$dateF; }

$baseQ = "FROM appointments a JOIN patients p ON a.patient_id=p.id JOIN users up ON p.user_id=up.id WHERE $where";
$total = db()->fetchOne("SELECT COUNT(*) as c $baseQ", $params)['c'];
$appointments = db()->fetchAll(
    "SELECT a.*, CONCAT(up.first_name,' ',up.last_name) as patient_name,
            up.phone as patient_phone, p.date_of_birth, p.gender, p.blood_type, p.id as pid $baseQ
     ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT ? OFFSET ?",
    array_merge($params, [ITEMS_PER_PAGE, $offset])
);
$totalPages = ceil($total / ITEMS_PER_PAGE);

// Pending count for badge
$pendingCount = db()->fetchOne("SELECT COUNT(*) as c FROM appointments WHERE doctor_id=? AND status='pending'", [$doctorId])['c'];

$pageTitle = 'My Appointments';
$activeNav = 'appointments';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-between align-center">
    <div>
        <h1>Appointments</h1>
        <p>Manage your patient appointments <?php if ($pendingCount): ?> — <span style="color:var(--warning);font-weight:600;"><?= $pendingCount ?> pending review</span><?php endif; ?></p>
    </div>
</div>

<!-- Filters -->
<div class="search-bar">
    <div class="search-input-wrap">
        <span class="search-icon">🔍</span>
        <input type="text" id="aSearch" placeholder="Search patient name..." value="<?= htmlspecialchars($search) ?>" onkeyup="applyAF()">
    </div>
    <select class="form-select" style="width:150px;" id="aStatus" onchange="applyAF()">
        <option value="">All Status</option>
        <?php foreach (['pending','confirmed','completed','cancelled','no_show'] as $s): ?>
            <option value="<?=$s?>" <?= $statusF===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="date" class="form-control" style="width:160px;" id="aDate" value="<?= htmlspecialchars($dateF) ?>" onchange="applyAF()">
    <button class="btn btn-secondary btn-sm" onclick="document.getElementById('aDate').value='<?= date('Y-m-d') ?>';applyAF()">Today</button>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Appointments (<?= $total ?>)</span>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Patient</th><th>Date</th><th>Time</th><th>Type</th><th>Reason</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($appointments as $a): ?>
                <tr>
                    <td>
                        <div style="font-weight:600;"><?= htmlspecialchars($a['patient_name']) ?></div>
                        <div style="font-size:12px;color:var(--text-muted);">
                            <?= $a['gender'] ? ucfirst($a['gender']) : '' ?>
                            <?php if ($a['date_of_birth']): ?>
                                · <?= date('Y') - date('Y', strtotime($a['date_of_birth'])) ?> yrs
                            <?php endif; ?>
                            <?= $a['blood_type'] ? ' · '.$a['blood_type'] : '' ?>
                        </div>
                        <?php if ($a['patient_phone']): ?>
                        <div style="font-size:12px;color:var(--text-muted);">📞 <?= htmlspecialchars($a['patient_phone']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= formatDate($a['appointment_date']) ?></td>
                    <td><?= formatTime($a['appointment_time']) ?> – <?= formatTime($a['end_time']) ?></td>
                    <td><span class="badge badge-info"><?= ucfirst(str_replace('_',' ',$a['type'])) ?></span></td>
                    <td style="max-width:180px;font-size:13px;"><?= htmlspecialchars(substr($a['reason']??'—',0,60)) ?></td>
                    <td>
                        <select class="form-select" style="width:130px;padding:4px 8px;font-size:12px;" onchange="updateStatus(<?=$a['id']?>,this.value)">
                            <?php foreach (['pending','confirmed','completed','cancelled','no_show'] as $s): ?>
                                <option value="<?=$s?>" <?=$a['status']===$s?'selected':''?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <div class="d-flex gap-8">
                            <a href="/cliniccares/doctor/view-patient.php?appt=<?=$a['id']?>" class="btn btn-primary btn-sm">👁️ View</a>
                            <button class="btn btn-secondary btn-sm" onclick='openNotesModal(<?= htmlspecialchars(json_encode(["id"=>$a['id'],"notes"=>$a['notes']])) ?>)'>📝 Notes</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($appointments)): ?>
                <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted);">No appointments found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-footer d-flex justify-between align-center">
        <span style="font-size:13px;color:var(--text-muted);">Page <?=$page?> of <?=$totalPages?></span>
        <div class="d-flex gap-8">
            <?php for ($p=1;$p<=$totalPages;$p++): ?>
                <a href="?page=<?=$p?>&q=<?=urlencode($search)?>&status=<?=$statusF?>&date=<?=$dateF?>" class="btn btn-sm <?=$p===$page?'btn-primary':'btn-secondary'?>"><?=$p?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Notes Modal -->
<div class="modal-overlay" id="notesModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">📝 Appointment Notes</h3>
            <button class="modal-close" onclick="closeModal('notesModal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="notesApptId">
            <div class="form-group">
                <label class="form-label">Internal Notes (visible to doctors/admin only)</label>
                <textarea id="notesText" class="form-control" rows="5" placeholder="Add notes about this appointment..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('notesModal')">Cancel</button>
            <button class="btn btn-primary" onclick="saveNotes()">💾 Save Notes</button>
        </div>
    </div>
</div>

<script>
function applyAF() {
    const q  = document.getElementById('aSearch').value;
    const st = document.getElementById('aStatus').value;
    const dt = document.getElementById('aDate').value;
    window.location.href = `?q=${encodeURIComponent(q)}&status=${st}&date=${dt}`;
}

function updateStatus(id, status) {
    const fd = new FormData();
    fd.set('action','update_status'); fd.set('id',id); fd.set('status',status);
    fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(res=>{
        if (res.success) showToast('Status updated','success');
        else showToast(res.error||'Error','danger');
    });
}

function openNotesModal(data) {
    document.getElementById('notesApptId').value = data.id;
    document.getElementById('notesText').value   = data.notes || '';
    openModal('notesModal');
}

function saveNotes() {
    const fd = new FormData();
    fd.set('action','add_notes');
    fd.set('id', document.getElementById('notesApptId').value);
    fd.set('notes', document.getElementById('notesText').value);
    fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(res=>{
        if (res.success) { showToast('Notes saved','success'); closeModal('notesModal'); }
        else showToast('Error saving notes','danger');
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>