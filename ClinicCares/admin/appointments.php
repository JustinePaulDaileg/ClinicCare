<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('admin');

// AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'update_status') {
        $id = (int)$_POST['id'];
        $status = in_array($_POST['status'],['pending','confirmed','cancelled','completed','no_show']) ? $_POST['status'] : 'pending';
        db()->execute("UPDATE appointments SET status=? WHERE id=?", [$status, $id]);
        $appt = db()->fetchOne("SELECT a.*,p.user_id FROM appointments a JOIN patients p ON a.patient_id=p.id WHERE a.id=?", [$id]);
        if ($appt) createNotification($appt['user_id'], 'Appointment Updated', "Your appointment status changed to: $status", 'appointment', '/patient/appointments.php');
        echo json_encode(['success' => true]);
        exit;
    }
    if ($action === 'delete') {
        db()->execute("DELETE FROM appointments WHERE id=?", [(int)$_POST['id']]);
        echo json_encode(['success' => true]);
        exit;
    }
    if ($action === 'save') {
        $id        = (int)($_POST['id'] ?? 0);
        $patientId = (int)$_POST['patient_id'];
        $doctorId  = (int)$_POST['doctor_id'];
        $date      = $_POST['appointment_date'] ?? '';
        $time      = $_POST['appointment_time'] ?? '';
        $type      = in_array($_POST['type']??'',['consultation','follow_up','emergency','check_up'])?$_POST['type']:'consultation';
        $reason    = sanitize($_POST['reason'] ?? '');
        $status    = in_array($_POST['status']??'',['pending','confirmed','cancelled','completed','no_show'])?$_POST['status']:'pending';
        $notes     = sanitize($_POST['notes'] ?? '');

        $doctor  = db()->fetchOne("SELECT slot_duration FROM doctors WHERE id=?", [$doctorId]);
        $dur     = $doctor ? (int)$doctor['slot_duration'] : 30;
        $endTime = date('H:i:s', strtotime($time) + $dur * 60);

        if ($id) {
            db()->execute(
                "UPDATE appointments SET patient_id=?,doctor_id=?,appointment_date=?,appointment_time=?,end_time=?,type=?,reason=?,status=?,notes=? WHERE id=?",
                [$patientId,$doctorId,$date,$time,$endTime,$type,$reason,$status,$notes,$id]
            );
        } else {
            $id = db()->insert(
                "INSERT INTO appointments (patient_id,doctor_id,appointment_date,appointment_time,end_time,type,reason,status,notes) VALUES (?,?,?,?,?,?,?,?,?)",
                [$patientId,$doctorId,$date,$time,$endTime,$type,$reason,$status,$notes]
            );
            $doc = db()->fetchOne("SELECT consultation_fee, user_id FROM doctors WHERE id=?", [$doctorId]);
            if ($doc) {
                $inv = generateInvoiceNumber();
                $billingId = db()->insert(
                    "INSERT INTO billing (invoice_number,patient_id,appointment_id,doctor_id,subtotal,total,balance,status,due_date) VALUES (?,?,?,?,?,?,?,?,?)",
                    [$inv,$patientId,$id,$doctorId,$doc['consultation_fee'],$doc['consultation_fee'],$doc['consultation_fee'],'pending',date('Y-m-d',strtotime('+7 days'))]
                );
                db()->insert("INSERT INTO billing_items (billing_id,description,quantity,unit_price,total) VALUES (?,?,?,?,?)",
                    [$billingId,'Consultation Fee',1,$doc['consultation_fee'],$doc['consultation_fee']]);
            }
        }
        echo json_encode(['success' => true]);
        exit;
    }
}

$search       = sanitize($_GET['q'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');
$typeFilter   = sanitize($_GET['type'] ?? '');
$doctorFilter = (int)($_GET['doctor'] ?? 0);
$dateFrom     = sanitize($_GET['date_from'] ?? '');
$dateTo       = sanitize($_GET['date_to'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($page - 1) * ITEMS_PER_PAGE;

$where = '1=1'; $params = [];
if ($search) {
    $where .= " AND (up.first_name LIKE ? OR up.last_name LIKE ? OR ud.first_name LIKE ? OR ud.last_name LIKE ? OR a.reason LIKE ?)";
    $s = "%$search%"; $params = [$s,$s,$s,$s,$s];
}
if ($statusFilter) { $where .= " AND a.status=?";              $params[] = $statusFilter; }
if ($typeFilter)   { $where .= " AND a.type=?";                $params[] = $typeFilter; }
if ($doctorFilter) { $where .= " AND a.doctor_id=?";           $params[] = $doctorFilter; }
if ($dateFrom)     { $where .= " AND a.appointment_date>=?";   $params[] = $dateFrom; }
if ($dateTo)       { $where .= " AND a.appointment_date<=?";   $params[] = $dateTo; }

$baseQuery = "FROM appointments a
    JOIN patients p ON a.patient_id=p.id JOIN users up ON p.user_id=up.id
    JOIN doctors d ON a.doctor_id=d.id JOIN users ud ON d.user_id=ud.id
    WHERE $where";

$total = db()->fetchOne("SELECT COUNT(*) as c $baseQuery", $params)['c'];
$appointments = db()->fetchAll(
    "SELECT a.*, CONCAT(up.first_name,' ',up.last_name) as patient_name,
     CONCAT(ud.first_name,' ',ud.last_name) as doctor_name, d.specialization
     $baseQuery ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT ? OFFSET ?",
    array_merge($params, [ITEMS_PER_PAGE, $offset])
);
$totalPages = ceil($total / ITEMS_PER_PAGE);

$patients     = db()->fetchAll("SELECT p.id, CONCAT(u.first_name,' ',u.last_name) as name FROM patients p JOIN users u ON p.user_id=u.id ORDER BY u.first_name");
$doctorsList  = db()->fetchAll("SELECT d.id, CONCAT(u.first_name,' ',u.last_name) as name, d.specialization FROM doctors d JOIN users u ON d.user_id=u.id ORDER BY u.first_name");

$activeFilters = (int)($search!=='') + (int)($statusFilter!=='') + (int)($typeFilter!=='') + (int)($doctorFilter>0) + (int)($dateFrom!=='') + (int)($dateTo!=='');

$pageTitle = 'Appointments';
$activeNav = 'appointments';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-between align-center">
  <div>
    <h1>Appointment Management</h1>
    <p>View, schedule, and manage all clinic appointments (<?= $total ?> total)</p>
  </div>
  <button class="btn btn-primary" onclick="openApptModal(0)">➕ New Appointment</button>
</div>

<div class="search-bar">
  <div class="search-input-wrap">
    <span class="search-icon">🔍</span>
    <input type="text" placeholder="Search patient, doctor, or reason…"
           value="<?= htmlspecialchars($search) ?>" id="sSearch" onkeyup="debounceAF()">
  </div>
  <select class="form-select" style="width:150px;" id="sStatus" onchange="applyAF()">
    <option value="">All Status</option>
    <?php foreach (['pending','confirmed','completed','cancelled','no_show'] as $s): ?>
      <option value="<?=$s?>" <?= $statusFilter===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
    <?php endforeach; ?>
  </select>
  <select class="form-select" style="width:150px;" id="sType" onchange="applyAF()">
    <option value="">All Types</option>
    <?php foreach (['consultation','follow_up','check_up','emergency'] as $t): ?>
      <option value="<?=$t?>" <?= $typeFilter===$t?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$t)) ?></option>
    <?php endforeach; ?>
  </select>
  <select class="form-select" style="width:175px;" id="sDoctor" onchange="applyAF()">
    <option value="">All Doctors</option>
    <?php foreach ($doctorsList as $doc): ?>
      <option value="<?=$doc['id']?>" <?= $doctorFilter===$doc['id']?'selected':'' ?>>
        Dr. <?= htmlspecialchars($doc['name']) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <input type="date" class="form-control" style="width:150px;" id="sDateFrom"
         value="<?= htmlspecialchars($dateFrom) ?>" onchange="applyAF()" title="From date">
  <input type="date" class="form-control" style="width:150px;" id="sDateTo"
         value="<?= htmlspecialchars($dateTo) ?>" onchange="applyAF()" title="To date">
  <?php if ($activeFilters > 0): ?>
  <a href="appointments.php" class="btn btn-secondary btn-sm" style="white-space:nowrap;">
    ✕ Clear <span class="badge badge-danger" style="margin-left:4px;"><?= $activeFilters ?></span>
  </a>
  <?php endif; ?>
</div>

<?php if ($activeFilters > 0): ?>
<div style="margin:-10px 0 14px;font-size:13px;color:var(--text-muted);">
  <?php $parts = [];
  if ($search)        $parts[] = 'Keyword: <strong>'.htmlspecialchars($search).'</strong>';
  if ($statusFilter)  $parts[] = 'Status: <strong>'.ucfirst(str_replace('_',' ',$statusFilter)).'</strong>';
  if ($typeFilter)    $parts[] = 'Type: <strong>'.ucfirst(str_replace('_',' ',$typeFilter)).'</strong>';
  if ($doctorFilter) {
      $dn = db()->fetchOne("SELECT CONCAT(u.first_name,' ',u.last_name) as name FROM doctors d JOIN users u ON d.user_id=u.id WHERE d.id=?", [$doctorFilter]);
      if ($dn) $parts[] = 'Doctor: <strong>Dr. '.htmlspecialchars($dn['name']).'</strong>';
  }
  if ($dateFrom) $parts[] = 'From: <strong>'.htmlspecialchars($dateFrom).'</strong>';
  if ($dateTo)   $parts[] = 'To: <strong>'.htmlspecialchars($dateTo).'</strong>';
  echo 'Filters: '.implode(' &middot; ', $parts).' &mdash; '.$total.' result'.($total!==1?'s':''); ?>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <span class="card-title">Appointments (<?= $total ?>)</span>
    <a href="?export=1&q=<?=urlencode($search)?>&status=<?=$statusFilter?>&type=<?=$typeFilter?>&doctor=<?=$doctorFilter?>&date_from=<?=$dateFrom?>&date_to=<?=$dateTo?>"
       class="btn btn-secondary btn-sm">📤 Export</a>
  </div>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr><th>Patient</th><th>Doctor</th><th>Date</th><th>Time</th><th>Type</th><th>Reason</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($appointments as $a): ?>
        <tr>
          <td><?= htmlspecialchars($a['patient_name']) ?></td>
          <td>
            <div>Dr. <?= htmlspecialchars($a['doctor_name']) ?></div>
            <div class="text-muted text-sm"><?= htmlspecialchars($a['specialization']) ?></div>
          </td>
          <td><?= formatDate($a['appointment_date']) ?></td>
          <td><?= formatTime($a['appointment_time']) ?> – <?= formatTime($a['end_time']) ?></td>
          <td><span class="badge badge-info"><?= ucfirst(str_replace('_',' ',$a['type'])) ?></span></td>
          <td style="font-size:13px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
              title="<?= htmlspecialchars($a['reason']??'') ?>">
            <?= htmlspecialchars($a['reason'] ? (strlen($a['reason'])>30?substr($a['reason'],0,30).'…':$a['reason']) : '—') ?>
          </td>
          <td>
            <select class="form-select" style="width:130px;padding:4px 8px;font-size:12px;" onchange="updateStatus(<?=$a['id']?>,this.value)">
              <?php foreach (['pending','confirmed','completed','cancelled','no_show'] as $s): ?>
                <option value="<?=$s?>" <?= $a['status']===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td>
            <div class="d-flex gap-8">
              <button class="btn btn-secondary btn-sm" onclick='openApptModal(<?= htmlspecialchars(json_encode($a)) ?>)'>✏️</button>
              <button class="btn btn-danger btn-sm" onclick="delAppt(<?=$a['id']?>)">🗑️</button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($appointments)): ?>
        <tr>
          <td colspan="8" style="text-align:center;padding:48px;color:var(--text-muted);">
            <div style="font-size:32px;margin-bottom:8px;">📅</div>
            <div style="font-weight:600;margin-bottom:4px;">No appointments found</div>
            <div style="font-size:13px;">Try adjusting your search or filters</div>
            <?php if ($activeFilters > 0): ?>
            <a href="appointments.php" class="btn btn-secondary btn-sm" style="margin-top:12px;">Clear all filters</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($totalPages > 1): ?>
  <div class="card-footer d-flex justify-between align-center">
    <span style="font-size:13px;color:var(--text-muted);">Page <?=$page?> of <?=$totalPages?> &mdash; <?=$total?> total</span>
    <div class="d-flex gap-8">
      <?php for ($p=1;$p<=$totalPages;$p++): ?>
        <a href="?page=<?=$p?>&q=<?=urlencode($search)?>&status=<?=$statusFilter?>&type=<?=$typeFilter?>&doctor=<?=$doctorFilter?>&date_from=<?=$dateFrom?>&date_to=<?=$dateTo?>"
           class="btn btn-sm <?=$p===$page?'btn-primary':'btn-secondary' ?>"><?=$p?></a>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Appointment Modal -->
<div class="modal-overlay" id="apptModal">
  <div class="modal" style="max-width:600px;">
    <div class="modal-header">
      <h3 class="modal-title" id="apptModalTitle">New Appointment</h3>
      <button class="modal-close" onclick="closeModal('apptModal')">✕</button>
    </div>
    <div class="modal-body">
      <form id="apptForm">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="apptId" value="0">
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Patient *</label>
            <select name="patient_id" id="aPatient" class="form-select" required>
              <option value="">Select patient</option>
              <?php foreach ($patients as $pt): ?>
                <option value="<?=$pt['id']?>"><?= htmlspecialchars($pt['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Doctor *</label>
            <select name="doctor_id" id="aDoctor" class="form-select" required>
              <option value="">Select doctor</option>
              <?php foreach ($doctorsList as $doc): ?>
                <option value="<?=$doc['id']?>"><?= htmlspecialchars('Dr. '.$doc['name']) ?> (<?= htmlspecialchars($doc['specialization']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Date *</label>
            <input type="date" name="appointment_date" id="aDate" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Time *</label>
            <input type="time" name="appointment_time" id="aTime" class="form-control" required>
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Type</label>
            <select name="type" id="aType" class="form-select">
              <option value="consultation">Consultation</option>
              <option value="follow_up">Follow-up</option>
              <option value="check_up">Check-up</option>
              <option value="emergency">Emergency</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" id="aStatus" class="form-select">
              <option value="pending">Pending</option>
              <option value="confirmed">Confirmed</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Reason for Visit</label>
          <input type="text" name="reason" id="aReason" class="form-control" placeholder="Brief reason...">
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea name="notes" id="aNotes" class="form-control" rows="3" placeholder="Additional notes..."></textarea>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('apptModal')">Cancel</button>
      <button class="btn btn-primary" onclick="saveAppt()">💾 Save Appointment</button>
    </div>
  </div>
</div>

<script>
var _afTimer;
function debounceAF() {
    clearTimeout(_afTimer);
    _afTimer = setTimeout(applyAF, 400);
}
function applyAF() {
    const params = new URLSearchParams();
    const q  = document.getElementById('sSearch').value.trim();
    const st = document.getElementById('sStatus').value;
    const ty = document.getElementById('sType').value;
    const dr = document.getElementById('sDoctor').value;
    const df = document.getElementById('sDateFrom').value;
    const dt = document.getElementById('sDateTo').value;
    if (q)  params.set('q', q);
    if (st) params.set('status', st);
    if (ty) params.set('type', ty);
    if (dr) params.set('doctor', dr);
    if (df) params.set('date_from', df);
    if (dt) params.set('date_to', dt);
    window.location.href = 'appointments.php?' + params.toString();
}
function updateStatus(id, status) {
    const data = new FormData();
    data.set('action','update_status'); data.set('id',id); data.set('status',status);
    fetch('',{method:'POST',body:data}).then(r=>r.json()).then(res=>{
        if(res.success) showToast('Status updated','success');
        else showToast('Error','danger');
    });
}
function openApptModal(data) {
    const isNew = !data||typeof data!=='object';
    document.getElementById('apptModalTitle').textContent = isNew?'New Appointment':'Edit Appointment';
    document.getElementById('apptId').value    = isNew?0:data.id;
    document.getElementById('aPatient').value  = isNew?'':data.patient_id;
    document.getElementById('aDoctor').value   = isNew?'':data.doctor_id;
    document.getElementById('aDate').value     = isNew?'':data.appointment_date;
    document.getElementById('aTime').value     = isNew?'':data.appointment_time;
    document.getElementById('aType').value     = isNew?'consultation':data.type;
    document.getElementById('aStatus').value   = isNew?'pending':data.status;
    document.getElementById('aReason').value   = isNew?'':(data.reason||'');
    document.getElementById('aNotes').value    = isNew?'':(data.notes||'');
    openModal('apptModal');
}
function saveAppt() {
    fetch('',{method:'POST',body:new FormData(document.getElementById('apptForm'))}).then(r=>r.json()).then(res=>{
        if(res.success){showToast('Saved!','success');closeModal('apptModal');location.reload();}
        else showToast(res.error||'Error','danger');
    });
}
function delAppt(id) {
    confirmAction('Delete this appointment?',()=>{
        const d=new FormData();d.set('action','delete');d.set('id',id);
        fetch('',{method:'POST',body:d}).then(r=>r.json()).then(res=>{
            if(res.success){showToast('Deleted','success');location.reload();}
        });
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
