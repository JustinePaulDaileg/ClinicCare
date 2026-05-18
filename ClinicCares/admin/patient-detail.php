<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('admin');

$patientId = (int)($_GET['id'] ?? 0);
if (!$patientId) redirect(SITE_URL . '/admin/patients.php');

$patient = db()->fetchOne("
    SELECT p.*, u.first_name, u.last_name, u.email, u.phone,
           u.is_active, u.email_verified, u.created_at as registered_at, u.id as user_id
    FROM patients p
    JOIN users u ON p.user_id = u.id
    WHERE p.id = ?", [$patientId]);

if (!$patient) {
    redirect(SITE_URL . '/admin/patients.php');
}

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'toggle_active') {
        $current = $patient['is_active'];
        db()->execute("UPDATE users SET is_active=? WHERE id=?", [!$current, $patient['user_id']]);
        echo json_encode(['success' => true, 'active' => !$current]);
        exit;
    }

    if ($action === 'update_patient') {
        $dob     = $_POST['date_of_birth']          ?: null;
        $gender  = in_array($_POST['gender'] ?? '', ['male','female','other']) ? $_POST['gender'] : null;
        $blood   = sanitize($_POST['blood_type']    ?? '');
        $address = sanitize($_POST['address']       ?? '');
        $city    = sanitize($_POST['city']          ?? '');
        $emName  = sanitize($_POST['emergency_contact_name']  ?? '');
        $emPhone = sanitize($_POST['emergency_contact_phone'] ?? '');
        $allerg  = sanitize($_POST['allergies']     ?? '');
        $insP    = sanitize($_POST['insurance_provider'] ?? '');
        $insN    = sanitize($_POST['insurance_number']   ?? '');

        db()->execute(
            "UPDATE patients SET date_of_birth=?,gender=?,blood_type=?,address=?,city=?,
             emergency_contact_name=?,emergency_contact_phone=?,allergies=?,
             insurance_provider=?,insurance_number=? WHERE id=?",
            [$dob,$gender,$blood?:null,$address,$city,$emName,$emPhone,$allerg,$insP,$insN,$patientId]
        );
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'send_notification') {
        $title   = sanitize($_POST['title']   ?? '');
        $message = sanitize($_POST['message'] ?? '');
        if ($title && $message) {
            createNotification($patient['user_id'], $title, $message, 'system', '/cliniccare/patient/dashboard.php');
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Title and message required']);
        }
        exit;
    }
}

// Stats
$stats = [
    'total_appts'  => db()->fetchOne("SELECT COUNT(*) as c FROM appointments WHERE patient_id=?", [$patientId])['c'],
    'completed'    => db()->fetchOne("SELECT COUNT(*) as c FROM appointments WHERE patient_id=? AND status='completed'", [$patientId])['c'],
    'prescriptions'=> db()->fetchOne("SELECT COUNT(*) as c FROM prescriptions WHERE patient_id=?", [$patientId])['c'],
    'total_billed' => db()->fetchOne("SELECT COALESCE(SUM(total),0) as c FROM billing WHERE patient_id=?", [$patientId])['c'],
    'total_paid'   => db()->fetchOne("SELECT COALESCE(SUM(amount_paid),0) as c FROM billing WHERE patient_id=?", [$patientId])['c'],
    'balance'      => db()->fetchOne("SELECT COALESCE(SUM(balance),0) as c FROM billing WHERE patient_id=? AND status IN ('pending','partial')", [$patientId])['c'],
];

// Appointments
$appointments = db()->fetchAll("
    SELECT a.*, CONCAT(u.first_name,' ',u.last_name) as doctor_name, d.specialization
    FROM appointments a
    JOIN doctors d ON a.doctor_id=d.id JOIN users u ON d.user_id=u.id
    WHERE a.patient_id=?
    ORDER BY a.appointment_date DESC LIMIT 20", [$patientId]);

// Medical records
$records = db()->fetchAll("
    SELECT mr.*, CONCAT(u.first_name,' ',u.last_name) as doctor_name
    FROM medical_records mr
    JOIN doctors d ON mr.doctor_id=d.id JOIN users u ON d.user_id=u.id
    WHERE mr.patient_id=?
    ORDER BY mr.record_date DESC LIMIT 15", [$patientId]);

// Prescriptions
$prescriptions = db()->fetchAll("
    SELECT pr.*, CONCAT(u.first_name,' ',u.last_name) as doctor_name,
           COUNT(pi.id) as med_count
    FROM prescriptions pr
    JOIN doctors d ON pr.doctor_id=d.id JOIN users u ON d.user_id=u.id
    LEFT JOIN prescription_items pi ON pi.prescription_id=pr.id
    WHERE pr.patient_id=?
    GROUP BY pr.id
    ORDER BY pr.created_at DESC LIMIT 15", [$patientId]);

// Billing
$bills = db()->fetchAll("
    SELECT b.*, CONCAT(ud.first_name,' ',ud.last_name) as doctor_name
    FROM billing b
    LEFT JOIN doctors d ON b.doctor_id=d.id LEFT JOIN users ud ON d.user_id=ud.id
    WHERE b.patient_id=?
    ORDER BY b.created_at DESC LIMIT 15", [$patientId]);

$age = $patient['date_of_birth']
     ? date_diff(date_create($patient['date_of_birth']), date_create('today'))->y
     : null;

$pageTitle = 'Patient: ' . $patient['first_name'] . ' ' . $patient['last_name'];
$activeNav = 'patients';
include __DIR__ . '/../includes/header.php';
?>

<!-- Breadcrumb -->
<div style="margin-bottom:16px;">
    <a href="/cliniccares/admin/patients.php" class="btn btn-secondary btn-sm">← Back to Patients</a>
</div>

<!-- Patient Header -->
<div class="card mb-24">
    <div style="padding:22px 24px;display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap;">
        <div class="avatar" style="width:68px;height:68px;font-size:26px;border-radius:16px;flex-shrink:0;">
            <?= strtoupper(substr($patient['first_name'],0,1).substr($patient['last_name'],0,1)) ?>
        </div>
        <div style="flex:1;min-width:220px;">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px;">
                <h2 style="font-family:var(--font-display);font-size:22px;font-weight:700;">
                    <?= htmlspecialchars($patient['first_name'].' '.$patient['last_name']) ?>
                </h2>
                <span class="badge <?= $patient['is_active']?'badge-success':'badge-secondary' ?>">
                    <?= $patient['is_active']?'Active':'Inactive' ?>
                </span>
                <span class="badge <?= $patient['email_verified']?'badge-info':'badge-warning' ?>">
                    <?= $patient['email_verified']?'✓ Verified':'Unverified' ?>
                </span>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:14px;font-size:13px;color:var(--text-secondary);">
                <?php if ($age): ?><span>🎂 <?= $age ?> yrs old</span><?php endif; ?>
                <?php if ($patient['gender']): ?><span>⚧ <?= ucfirst($patient['gender']) ?></span><?php endif; ?>
                <?php if ($patient['blood_type']): ?><span>🩸 <?= htmlspecialchars($patient['blood_type']) ?></span><?php endif; ?>
                <span>✉️ <?= htmlspecialchars($patient['email']) ?></span>
                <?php if ($patient['phone']): ?><span>📞 <?= htmlspecialchars($patient['phone']) ?></span><?php endif; ?>
                <span>📅 Registered: <?= formatDate($patient['registered_at']) ?></span>
            </div>
            <?php if ($patient['allergies']): ?>
            <div style="margin-top:8px;display:inline-flex;align-items:center;gap:6px;background:#fef2f2;border:1px solid #fecaca;padding:6px 12px;border-radius:8px;font-size:13px;color:var(--danger);">
                ⚠️ <strong>Allergies:</strong> <?= htmlspecialchars($patient['allergies']) ?>
            </div>
            <?php endif; ?>
        </div>
        <div style="display:flex;gap:8px;flex-shrink:0;flex-wrap:wrap;">
            <button class="btn btn-secondary btn-sm" onclick="openModal('notifModal')">🔔 Notify</button>
            <button class="btn <?= $patient['is_active']?'btn-warning':'btn-success' ?> btn-sm" onclick="toggleActive()">
                <?= $patient['is_active']?'⛔ Deactivate':'✅ Activate' ?>
            </button>
        </div>
    </div>
</div>

<!-- Summary Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(6,1fr);margin-bottom:24px;">
    <div class="stat-card blue">
        <div class="stat-icon blue">📅</div>
        <div class="stat-value"><?= $stats['total_appts'] ?></div>
        <div class="stat-label">Total Appts</div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon green">✅</div>
        <div class="stat-value"><?= $stats['completed'] ?></div>
        <div class="stat-label">Completed</div>
    </div>
    <div class="stat-card teal">
        <div class="stat-icon teal">💊</div>
        <div class="stat-value"><?= $stats['prescriptions'] ?></div>
        <div class="stat-label">Prescriptions</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon blue">💰</div>
        <div class="stat-value" style="font-size:18px;"><?= formatCurrency($stats['total_billed']) ?></div>
        <div class="stat-label">Total Billed</div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon green">✅</div>
        <div class="stat-value" style="font-size:18px;"><?= formatCurrency($stats['total_paid']) ?></div>
        <div class="stat-label">Total Paid</div>
    </div>
    <div class="stat-card <?= $stats['balance']>0?'amber':'green' ?>">
        <div class="stat-icon <?= $stats['balance']>0?'amber':'green' ?>">⏳</div>
        <div class="stat-value" style="font-size:18px;"><?= formatCurrency($stats['balance']) ?></div>
        <div class="stat-label">Outstanding</div>
    </div>
</div>

<!-- Tabs -->
<div class="tabs tab-group" style="margin-bottom:20px;">
    <button class="tab-btn active" data-tab="pd-appts">📅 Appointments (<?= count($appointments) ?>)</button>
    <button class="tab-btn" data-tab="pd-records">📋 Medical Records (<?= count($records) ?>)</button>
    <button class="tab-btn" data-tab="pd-rx">💊 Prescriptions (<?= count($prescriptions) ?>)</button>
    <button class="tab-btn" data-tab="pd-billing">💳 Billing (<?= count($bills) ?>)</button>
    <button class="tab-btn" data-tab="pd-profile">👤 Profile</button>
</div>

<!-- Appointments Tab -->
<div class="tab-panel active" id="pd-appts">
    <div class="card">
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Doctor</th><th>Date</th><th>Time</th><th>Type</th><th>Reason</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($appointments as $a): ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;">Dr. <?= htmlspecialchars($a['doctor_name']) ?></div>
                            <div class="text-sm text-muted"><?= htmlspecialchars($a['specialization']) ?></div>
                        </td>
                        <td><?= formatDate($a['appointment_date']) ?></td>
                        <td><?= formatTime($a['appointment_time']) ?></td>
                        <td><span class="badge badge-info"><?= ucfirst(str_replace('_',' ',$a['type'])) ?></span></td>
                        <td style="font-size:13px;max-width:160px;"><?= htmlspecialchars(substr($a['reason']??'—',0,60)) ?></td>
                        <td><span class="badge <?= getStatusBadge($a['status']) ?>"><?= ucfirst(str_replace('_',' ',$a['status'])) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($appointments)): ?>
                    <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted);">No appointments found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Medical Records Tab -->
<div class="tab-panel" id="pd-records">
    <?php if (empty($records)): ?>
    <div class="card"><div class="card-body" style="text-align:center;padding:40px;color:var(--text-muted);">No medical records yet</div></div>
    <?php else: ?>
    <div class="timeline">
        <?php foreach ($records as $rec): ?>
        <div class="timeline-item">
            <div class="timeline-dot"></div>
            <div class="timeline-date"><?= formatDate($rec['record_date']) ?> — Dr. <?= htmlspecialchars($rec['doctor_name']) ?></div>
            <div class="timeline-content">
                <h4><?= htmlspecialchars($rec['diagnosis']) ?></h4>
                <?php if ($rec['symptoms']): ?><p><strong>Symptoms:</strong> <?= htmlspecialchars($rec['symptoms']) ?></p><?php endif; ?>
                <?php if ($rec['treatment']): ?><p><strong>Treatment:</strong> <?= htmlspecialchars($rec['treatment']) ?></p><?php endif; ?>
                <?php
                $vitals = array_filter([
                    'BP'=>$rec['vital_bp'], 'Temp'=>$rec['vital_temp']?$rec['vital_temp'].'°C':null,
                    'Pulse'=>$rec['vital_pulse']?$rec['vital_pulse'].' bpm':null,
                    'Weight'=>$rec['vital_weight'], 'Height'=>$rec['vital_height']
                ]);
                if ($vitals):
                ?>
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;">
                    <?php foreach ($vitals as $vl=>$vv): ?>
                    <span style="background:#eff6ff;color:var(--primary);padding:3px 8px;border-radius:6px;font-size:12px;font-weight:600;"><?=$vl?>: <?= htmlspecialchars($vv) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if ($rec['follow_up_date']): ?>
                <div style="margin-top:6px;"><span class="badge badge-info">Follow-up: <?= formatDate($rec['follow_up_date']) ?></span></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Prescriptions Tab -->
<div class="tab-panel" id="pd-rx">
    <div class="card">
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Rx Number</th><th>Doctor</th><th>Issued</th><th>Valid Until</th><th>Meds</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($prescriptions as $rx): ?>
                    <tr>
                        <td style="font-family:monospace;font-weight:700;color:var(--primary);"><?= htmlspecialchars($rx['prescription_number']) ?></td>
                        <td>Dr. <?= htmlspecialchars($rx['doctor_name']) ?></td>
                        <td><?= formatDate($rx['issue_date']) ?></td>
                        <td><?= $rx['valid_until']?formatDate($rx['valid_until']):'—' ?></td>
                        <td><?= $rx['med_count'] ?> med<?= $rx['med_count']!=1?'s':'' ?></td>
                        <td><span class="badge <?= getStatusBadge($rx['status']) ?>"><?= ucfirst($rx['status']) ?></span></td>
                        <td><a href="/cliniccares/doctor/print-prescriptions.php?id=<?= $rx['id'] ?>" target="_blank" class="btn btn-secondary btn-sm">🖨️</a></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($prescriptions)): ?>
                    <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-muted);">No prescriptions</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Billing Tab -->
<div class="tab-panel" id="pd-billing">
    <div class="card">
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Invoice #</th><th>Doctor</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($bills as $b): ?>
                    <tr>
                        <td style="font-family:monospace;font-weight:600;color:var(--primary);"><?= htmlspecialchars($b['invoice_number']) ?></td>
                        <td><?= $b['doctor_name']?'Dr. '.htmlspecialchars($b['doctor_name']):'—' ?></td>
                        <td><?= formatCurrency($b['total']) ?></td>
                        <td style="color:var(--success);"><?= formatCurrency($b['amount_paid']) ?></td>
                        <td style="color:<?=$b['balance']>0?'var(--danger)':'var(--success)'?>;font-weight:600;"><?= formatCurrency($b['balance']) ?></td>
                        <td><span class="badge <?= getStatusBadge($b['status']) ?>"><?= ucfirst($b['status']) ?></span></td>
                        <td><?= formatDate($b['created_at']) ?></td>
                        <td><a href="/cliniccares/admin/billing.php" class="btn btn-secondary btn-sm">Manage</a></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($bills)): ?>
                    <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-muted);">No billing records</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Profile Tab -->
<div class="tab-panel" id="pd-profile">
    <div class="card">
        <div class="card-header">
            <span class="card-title">👤 Patient Profile</span>
            <button class="btn btn-primary btn-sm" onclick="openModal('editProfileModal')">✏️ Edit Profile</button>
        </div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
                <?php
                $profileFields = [
                    ['Date of Birth',    $patient['date_of_birth']?formatDate($patient['date_of_birth']):'—'],
                    ['Gender',           $patient['gender']?ucfirst($patient['gender']):'—'],
                    ['Blood Type',       $patient['blood_type']?:'—'],
                    ['Phone',            $patient['phone']?:'—'],
                    ['Email',            $patient['email']],
                    ['Address',          ($patient['address']?$patient['address'].', ':'').($patient['city']?:'')],
                    ['Emergency Contact',$patient['emergency_contact_name']?:'—'],
                    ['Emergency Phone',  $patient['emergency_contact_phone']?:'—'],
                    ['Insurance',        $patient['insurance_provider']?:'—'],
                    ['Insurance #',      $patient['insurance_number']?:'—'],
                    ['Known Allergies',  $patient['allergies']?:'None known'],
                    ['Account Status',   $patient['is_active']?'Active':'Inactive'],
                ];
                foreach ($profileFields as [$label,$value]):
                ?>
                <div style="padding:10px 0;border-bottom:1px solid var(--border);">
                    <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;"><?= $label ?></div>
                    <div style="font-size:14px;<?= $label==='Known Allergies'&&$value!=='None known'?'color:var(--danger);font-weight:600;':'' ?>"><?= htmlspecialchars($value) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Edit Profile Modal -->
<div class="modal-overlay" id="editProfileModal">
    <div class="modal" style="max-width:640px;">
        <div class="modal-header">
            <h3 class="modal-title">✏️ Edit Patient Profile</h3>
            <button class="modal-close" onclick="closeModal('editProfileModal')">✕</button>
        </div>
        <div class="modal-body">
            <form id="editProfileForm">
                <input type="hidden" name="action" value="update_patient">
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-control" value="<?= htmlspecialchars($patient['date_of_birth']??'') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select">
                            <option value="">Select</option>
                            <?php foreach (['male','female','other'] as $g): ?>
                                <option value="<?=$g?>" <?= ($patient['gender']??'')===$g?'selected':'' ?>><?= ucfirst($g) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Blood Type</label>
                        <select name="blood_type" class="form-select">
                            <option value="">Unknown</option>
                            <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
                                <option value="<?=$bt?>" <?= ($patient['blood_type']??'')===$bt?'selected':'' ?>><?=$bt?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:span 2;">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($patient['address']??'') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">City</label>
                        <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($patient['city']??'') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Emergency Contact</label>
                        <input type="text" name="emergency_contact_name" class="form-control" value="<?= htmlspecialchars($patient['emergency_contact_name']??'') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Emergency Phone</label>
                        <input type="tel" name="emergency_contact_phone" class="form-control" value="<?= htmlspecialchars($patient['emergency_contact_phone']??'') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Known Allergies</label>
                        <input type="text" name="allergies" class="form-control" placeholder="e.g. Penicillin" value="<?= htmlspecialchars($patient['allergies']??'') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Insurance Provider</label>
                        <input type="text" name="insurance_provider" class="form-control" value="<?= htmlspecialchars($patient['insurance_provider']??'') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Insurance Number</label>
                        <input type="text" name="insurance_number" class="form-control" value="<?= htmlspecialchars($patient['insurance_number']??'') ?>">
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('editProfileModal')">Cancel</button>
            <button class="btn btn-primary" onclick="saveProfile()">💾 Save Changes</button>
        </div>
    </div>
</div>

<!-- Send Notification Modal -->
<div class="modal-overlay" id="notifModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">🔔 Send Notification</h3>
            <button class="modal-close" onclick="closeModal('notifModal')">✕</button>
        </div>
        <div class="modal-body">
            <div style="background:var(--surface-2);padding:10px 14px;border-radius:8px;margin-bottom:16px;font-size:13px;">
                Sending to: <strong><?= htmlspecialchars($patient['first_name'].' '.$patient['last_name']) ?></strong>
            </div>
            <form id="notifForm">
                <input type="hidden" name="action" value="send_notification">
                <div class="form-group">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-control" placeholder="Notification title..." required>
                </div>
                <div class="form-group">
                    <label class="form-label">Message *</label>
                    <textarea name="message" class="form-control" rows="3" placeholder="Notification message..." required></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('notifModal')">Cancel</button>
            <button class="btn btn-primary" onclick="sendNotif()">🔔 Send</button>
        </div>
    </div>
</div>

<script>
function toggleActive() {
    const fd = new FormData(); fd.set('action','toggle_active');
    fetch('', {method:'POST', body:fd}).then(r=>r.json()).then(res => {
        if (res.success) { showToast('Account status updated', 'success'); location.reload(); }
        else showToast('Error', 'danger');
    });
}

function saveProfile() {
    const fd = new FormData(document.getElementById('editProfileForm'));
    fetch('', {method:'POST', body:fd}).then(r=>r.json()).then(res => {
        if (res.success) { showToast('Profile updated!', 'success'); closeModal('editProfileModal'); location.reload(); }
        else showToast(res.error || 'Error', 'danger');
    });
}

function sendNotif() {
    const fd = new FormData(document.getElementById('notifForm'));
    fetch('', {method:'POST', body:fd}).then(r=>r.json()).then(res => {
        if (res.success) { showToast('Notification sent!', 'success'); closeModal('notifModal'); }
        else showToast(res.error || 'Error', 'danger');
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>