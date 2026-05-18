<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('doctor');

$doctor   = db()->fetchOne("SELECT d.* FROM doctors d WHERE d.user_id=?", [$_SESSION['user_id']]);
$doctorId = $doctor['id'];

$apptId = (int)($_GET['appt'] ?? 0);
$appt   = db()->fetchOne("
    SELECT a.*, CONCAT(up.first_name,' ',up.last_name) as patient_name,
           up.first_name, up.last_name, up.email, up.phone,
           p.id as patient_id, p.date_of_birth, p.gender, p.blood_type,
           p.address, p.city, p.allergies, p.emergency_contact_name,
           p.emergency_contact_phone, p.insurance_provider, p.insurance_number
    FROM appointments a
    JOIN patients p ON a.patient_id=p.id
    JOIN users up ON p.user_id=up.id
    WHERE a.id=? AND a.doctor_id=?", [$apptId, $doctorId]);

if (!$appt) {
    redirect(SITE_URL . '/doctor/appointments.php');
}

$patientId = $appt['patient_id'];

// Handle POST: save medical record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'save_record') {
        $diagnosis   = sanitize($_POST['diagnosis'] ?? '');
        $symptoms    = sanitize($_POST['symptoms'] ?? '');
        $treatment   = sanitize($_POST['treatment'] ?? '');
        $notes       = sanitize($_POST['notes'] ?? '');
        $vBP         = sanitize($_POST['vital_bp'] ?? '');
        $vTemp       = sanitize($_POST['vital_temp'] ?? '');
        $vPulse      = sanitize($_POST['vital_pulse'] ?? '');
        $vWeight     = sanitize($_POST['vital_weight'] ?? '');
        $vHeight     = sanitize($_POST['vital_height'] ?? '');
        $followUp    = $_POST['follow_up_date'] ?: null;
        $recordDate  = date('Y-m-d');

        if (!$diagnosis) { echo json_encode(['success'=>false,'error'=>'Diagnosis is required']); exit; }

        // Check if record exists for this appointment
        $existingRecord = db()->fetchOne("SELECT id FROM medical_records WHERE appointment_id=?", [$apptId]);

        if ($existingRecord) {
            db()->execute(
                "UPDATE medical_records SET diagnosis=?,symptoms=?,treatment=?,notes=?,vital_bp=?,vital_temp=?,vital_pulse=?,vital_weight=?,vital_height=?,follow_up_date=? WHERE id=?",
                [$diagnosis,$symptoms,$treatment,$notes,$vBP,$vTemp,$vPulse,$vWeight,$vHeight,$followUp,$existingRecord['id']]
            );
            $recordId = $existingRecord['id'];
        } else {
            $recordId = db()->insert(
                "INSERT INTO medical_records (patient_id,doctor_id,appointment_id,diagnosis,symptoms,treatment,notes,vital_bp,vital_temp,vital_pulse,vital_weight,vital_height,follow_up_date,record_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$patientId,$doctorId,$apptId,$diagnosis,$symptoms,$treatment,$notes,$vBP,$vTemp,$vPulse,$vWeight,$vHeight,$followUp,$recordDate]
            );
        }

        // Mark appointment completed
        db()->execute("UPDATE appointments SET status='completed' WHERE id=?", [$apptId]);

        // Notify patient
        $pu = db()->fetchOne("SELECT user_id FROM patients WHERE id=?", [$patientId]);
        createNotification($pu['user_id'], 'Medical Record Updated', 'Your medical record has been updated by Dr. '.ucfirst(db()->fetchOne("SELECT last_name FROM users WHERE id=?",[$_SESSION['user_id']])['last_name']), 'system', '/patient/records.php');

        echo json_encode(['success'=>true, 'record_id'=>$recordId]);
        exit;
    }
}

// Get existing medical records for this patient
$medicalHistory = db()->fetchAll("
    SELECT mr.*, CONCAT(u.first_name,' ',u.last_name) as doctor_name
    FROM medical_records mr
    JOIN doctors d ON mr.doctor_id=d.id
    JOIN users u ON d.user_id=u.id
    WHERE mr.patient_id=?
    ORDER BY mr.record_date DESC", [$patientId]);

// Existing record for this appointment
$currentRecord = db()->fetchOne("SELECT * FROM medical_records WHERE appointment_id=?", [$apptId]);

// Prescriptions
$prescriptions = db()->fetchAll("
    SELECT pr.*, COUNT(pi.id) as med_count
    FROM prescriptions pr
    LEFT JOIN prescription_items pi ON pi.prescription_id=pr.id
    WHERE pr.patient_id=?
    GROUP BY pr.id
    ORDER BY pr.created_at DESC LIMIT 10", [$patientId]);

// Bills
$bills = db()->fetchAll("SELECT * FROM billing WHERE patient_id=? ORDER BY created_at DESC LIMIT 5", [$patientId]);

$age = $appt['date_of_birth'] ? date_diff(date_create($appt['date_of_birth']), date_create('today'))->y : null;

$pageTitle = 'Patient: ' . $appt['patient_name'];
$activeNav = 'appointments';
include __DIR__ . '/../includes/header.php';
?>

<!-- Back -->
<div style="margin-bottom:16px;">
    <a href="/cliniccares/doctor/appointments.php" class="btn btn-secondary btn-sm">← Back to Appointments</a>
</div>

<!-- Patient Header Card -->
<div class="card mb-24">
    <div style="padding:20px 24px;display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap;">
        <div class="avatar" style="width:64px;height:64px;font-size:24px;border-radius:16px;flex-shrink:0;">
            <?= strtoupper(substr($appt['first_name'],0,1).substr($appt['last_name'],0,1)) ?>
        </div>
        <div style="flex:1;min-width:200px;">
            <h2 style="font-family:var(--font-display);font-size:22px;font-weight:700;margin-bottom:4px;"><?= htmlspecialchars($appt['patient_name']) ?></h2>
            <div style="display:flex;flex-wrap:wrap;gap:12px;font-size:13px;color:var(--text-secondary);margin-bottom:10px;">
                <?php if ($age): ?><span>🎂 <?= $age ?> years old</span><?php endif; ?>
                <?php if ($appt['gender']): ?><span>⚧ <?= ucfirst($appt['gender']) ?></span><?php endif; ?>
                <?php if ($appt['blood_type']): ?><span>🩸 <?= htmlspecialchars($appt['blood_type']) ?></span><?php endif; ?>
                <?php if ($appt['email']): ?><span>✉️ <?= htmlspecialchars($appt['email']) ?></span><?php endif; ?>
                <?php if ($appt['phone']): ?><span>📞 <?= htmlspecialchars($appt['phone']) ?></span><?php endif; ?>
            </div>
            <?php if ($appt['allergies']): ?>
            <div style="display:inline-flex;align-items:center;gap:6px;background:#fef2f2;border:1px solid #fecaca;padding:6px 12px;border-radius:8px;font-size:13px;color:var(--danger);">
                ⚠️ <strong>Allergies:</strong> <?= htmlspecialchars($appt['allergies']) ?>
            </div>
            <?php endif; ?>
        </div>
        <div style="text-align:right;flex-shrink:0;">
            <div style="background:var(--primary-pale);border:1px solid #bfdbfe;padding:12px 16px;border-radius:10px;">
                <div style="font-size:11px;color:var(--primary);font-weight:700;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">Current Appointment</div>
                <div style="font-size:15px;font-weight:700;"><?= formatDate($appt['appointment_date']) ?></div>
                <div style="font-size:13px;color:var(--text-muted);"><?= formatTime($appt['appointment_time']) ?> – <?= formatTime($appt['end_time']) ?></div>
                <div style="margin-top:6px;"><span class="badge <?= getStatusBadge($appt['status']) ?>"><?= ucfirst(str_replace('_',' ',$appt['status'])) ?></span></div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="tabs tab-group" style="margin-bottom:20px;">
    <button class="tab-btn active" data-tab="consultation">🩺 Consultation</button>
    <button class="tab-btn" data-tab="history">📋 Medical History (<?= count($medicalHistory) ?>)</button>
    <button class="tab-btn" data-tab="prescriptions">💊 Prescriptions</button>
    <button class="tab-btn" data-tab="billing">💳 Billing</button>
    <button class="tab-btn" data-tab="profile">👤 Patient Profile</button>
</div>

<!-- Consultation Tab -->
<div class="tab-panel active" id="consultation">
    <div class="grid-2" style="grid-template-columns:1fr 340px;gap:20px;">
        <!-- Record Form -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">📝 <?= $currentRecord ? 'Update' : 'Create' ?> Medical Record</span>
                <?php if ($currentRecord): ?>
                    <span class="badge badge-success">✓ Record exists</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form id="recordForm">
                    <!-- Vitals -->
                    <div style="margin-bottom:18px;">
                        <div style="font-size:13px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px;">Vital Signs</div>
                        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">
                            <div class="form-group" style="margin:0;">
                                <label class="form-label">Blood Pressure</label>
                                <input type="text" name="vital_bp" class="form-control" placeholder="120/80" value="<?= htmlspecialchars($currentRecord['vital_bp']??'') ?>">
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label class="form-label">Temperature (°C)</label>
                                <input type="text" name="vital_temp" class="form-control" placeholder="36.5" value="<?= htmlspecialchars($currentRecord['vital_temp']??'') ?>">
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label class="form-label">Pulse (bpm)</label>
                                <input type="text" name="vital_pulse" class="form-control" placeholder="72" value="<?= htmlspecialchars($currentRecord['vital_pulse']??'') ?>">
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label class="form-label">Weight</label>
                                <input type="text" name="vital_weight" class="form-control" placeholder="70kg" value="<?= htmlspecialchars($currentRecord['vital_weight']??'') ?>">
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label class="form-label">Height</label>
                                <input type="text" name="vital_height" class="form-control" placeholder="170cm" value="<?= htmlspecialchars($currentRecord['vital_height']??'') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Chief Complaint / Symptoms</label>
                        <textarea name="symptoms" class="form-control" rows="3" placeholder="Patient's reported symptoms..."><?= htmlspecialchars($currentRecord['symptoms']??'') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Diagnosis *</label>
                        <input type="text" name="diagnosis" class="form-control" placeholder="Primary diagnosis..." required value="<?= htmlspecialchars($currentRecord['diagnosis']??'') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Treatment Plan</label>
                        <textarea name="treatment" class="form-control" rows="3" placeholder="Recommended treatment..."><?= htmlspecialchars($currentRecord['treatment']??'') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Doctor's Notes</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Additional clinical notes..."><?= htmlspecialchars($currentRecord['notes']??'') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Follow-up Date</label>
                        <input type="date" name="follow_up_date" class="form-control" value="<?= htmlspecialchars($currentRecord['follow_up_date']??'') ?>" min="<?= date('Y-m-d') ?>">
                    </div>
                </form>
            </div>
            <div class="card-footer d-flex justify-between align-center">
                <span style="font-size:13px;color:var(--text-muted);">Saving will mark appointment as completed</span>
                <button class="btn btn-success" onclick="saveRecord()">✅ Save Record</button>
            </div>
        </div>

        <!-- Quick Actions Sidebar -->
        <div style="display:flex;flex-direction:column;gap:16px;">
            <!-- Appointment info -->
            <div class="card">
                <div class="card-header"><span class="card-title">📅 Appointment Info</span></div>
                <div class="card-body">
                    <div style="display:flex;flex-direction:column;gap:8px;font-size:13px;">
                        <?php
                        $fields = [
                            ['Reason', $appt['reason']??'—'],
                            ['Type', ucfirst(str_replace('_',' ',$appt['type']))],
                            ['Status', ucfirst(str_replace('_',' ',$appt['status']))],
                        ];
                        foreach ($fields as [$label,$val]):
                        ?>
                        <div style="display:flex;justify-content:space-between;">
                            <span style="color:var(--text-muted);"><?= $label ?></span>
                            <span style="font-weight:600;"><?= htmlspecialchars($val) ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php if ($appt['notes']): ?>
                        <div style="background:var(--surface-2);padding:8px 10px;border-radius:6px;margin-top:4px;">
                            <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:2px;">Notes</div>
                            <?= htmlspecialchars($appt['notes']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Prescription -->
            <div class="card">
                <div class="card-header"><span class="card-title">💊 Quick Actions</span></div>
                <div class="card-body" style="display:flex;flex-direction:column;gap:8px;">
                    <a href="/cliniccares/doctor/prescriptions.php" class="btn btn-primary btn-block">💊 Write Prescription</a>
                    <a href="/cliniccares/doctor/records.php?patient=<?= $patientId ?>" class="btn btn-secondary btn-block">📋 Full History</a>
                </div>
            </div>

            <!-- Last vitals comparison -->
            <?php if (count($medicalHistory) > 1): ?>
            <div class="card">
                <div class="card-header"><span class="card-title">📊 Previous Vitals</span></div>
                <div class="card-body">
                    <?php $prev = $medicalHistory[0]; ?>
                    <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px;"><?= formatDate($prev['record_date']) ?></div>
                    <?php
                    $pv = [
                        ['BP',$prev['vital_bp']],['Temp',$prev['vital_temp']?$prev['vital_temp'].'°C':null],
                        ['Pulse',$prev['vital_pulse']?$prev['vital_pulse'].' bpm':null],
                        ['Weight',$prev['vital_weight']],['Height',$prev['vital_height']],
                    ];
                    foreach ($pv as [$lbl,$val]):
                        if (!$val) continue;
                    ?>
                    <div style="display:flex;justify-content:space-between;font-size:13px;padding:4px 0;border-bottom:1px solid var(--border);">
                        <span style="color:var(--text-muted);"><?= $lbl ?></span>
                        <span style="font-weight:600;"><?= htmlspecialchars($val) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- History Tab -->
<div class="tab-panel" id="history">
    <?php if (empty($medicalHistory)): ?>
    <div class="card"><div class="card-body" style="text-align:center;padding:40px;color:var(--text-muted);">No medical records yet</div></div>
    <?php else: ?>
    <div class="timeline">
        <?php foreach ($medicalHistory as $rec): ?>
        <div class="timeline-item">
            <div class="timeline-dot"></div>
            <div class="timeline-date"><?= formatDate($rec['record_date']) ?> — <?= htmlspecialchars($rec['doctor_name']) ?></div>
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
                    <?php foreach ($vitals as $vl => $vv): ?>
                    <span style="background:#eff6ff;color:var(--primary);padding:3px 8px;border-radius:6px;font-size:12px;font-weight:600;"><?=$vl?>: <?= htmlspecialchars($vv) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Prescriptions Tab -->
<div class="tab-panel" id="prescriptions">
    <?php if (empty($prescriptions)): ?>
    <div class="card"><div class="card-body" style="text-align:center;padding:40px;color:var(--text-muted);">No prescriptions yet</div></div>
    <?php else: ?>
    <div class="card">
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Rx Number</th><th>Issued</th><th>Valid Until</th><th>Medications</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($prescriptions as $rx): ?>
                    <tr>
                        <td style="font-family:monospace;font-weight:700;color:var(--primary);"><?= htmlspecialchars($rx['prescription_number']) ?></td>
                        <td><?= formatDate($rx['issue_date']) ?></td>
                        <td><?= $rx['valid_until']?formatDate($rx['valid_until']):'—' ?></td>
                        <td><?= $rx['med_count'] ?> medication<?= $rx['med_count']!=1?'s':'' ?></td>
                        <td><span class="badge <?= getStatusBadge($rx['status']) ?>"><?= ucfirst($rx['status']) ?></span></td>
                        <td><a href="/cliniccares/doctor/print-prescriptions.php?id=<?=$rx['id']?>" target="_blank" class="btn btn-secondary btn-sm">🖨️ Print</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Billing Tab -->
<div class="tab-panel" id="billing">
    <?php if (empty($bills)): ?>
    <div class="card"><div class="card-body" style="text-align:center;padding:40px;color:var(--text-muted);">No billing records</div></div>
    <?php else: ?>
    <div class="card">
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Invoice #</th><th>Amount</th><th>Paid</th><th>Balance</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                    <?php foreach ($bills as $b): ?>
                    <tr>
                        <td style="font-family:monospace;font-weight:600;color:var(--primary);"><?= htmlspecialchars($b['invoice_number']) ?></td>
                        <td><?= formatCurrency($b['total']) ?></td>
                        <td style="color:var(--success);"><?= formatCurrency($b['amount_paid']) ?></td>
                        <td style="color:<?=$b['balance']>0?'var(--danger)':'var(--success)'?>;font-weight:600;"><?= formatCurrency($b['balance']) ?></td>
                        <td><span class="badge <?= getStatusBadge($b['status']) ?>"><?= ucfirst($b['status']) ?></span></td>
                        <td><?= formatDate($b['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Profile Tab -->
<div class="tab-panel" id="profile">
    <div class="card">
        <div class="card-header"><span class="card-title">👤 Patient Profile</span></div>
        <div class="card-body">
            <div class="grid-2">
                <?php
                $profileFields = [
                    ['Date of Birth', $appt['date_of_birth'] ? formatDate($appt['date_of_birth']) : '—'],
                    ['Gender', $appt['gender'] ? ucfirst($appt['gender']) : '—'],
                    ['Blood Type', $appt['blood_type'] ?: '—'],
                    ['Phone', $appt['phone'] ?: '—'],
                    ['Email', $appt['email'] ?: '—'],
                    ['Address', ($appt['address'] ? $appt['address'].', ' : '') . ($appt['city'] ?: '')],
                    ['Emergency Contact', $appt['emergency_contact_name'] ?: '—'],
                    ['Emergency Phone', $appt['emergency_contact_phone'] ?: '—'],
                    ['Insurance Provider', $appt['insurance_provider'] ?: '—'],
                    ['Insurance Number', $appt['insurance_number'] ?: '—'],
                    ['Known Allergies', $appt['allergies'] ?: 'None known'],
                ];
                foreach ($profileFields as [$label, $value]):
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

<script>
function saveRecord() {
    const fd = new FormData(document.getElementById('recordForm'));
    fd.set('action', 'save_record');
    if (!fd.get('diagnosis').trim()) { showToast('Diagnosis is required', 'warning'); return; }

    fetch('', {method:'POST', body:fd}).then(r=>r.json()).then(res=>{
        if (res.success) {
            showToast('Medical record saved! Appointment marked as completed.', 'success');
            setTimeout(()=>window.location.reload(), 1500);
        } else showToast(res.error||'Error saving record', 'danger');
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>