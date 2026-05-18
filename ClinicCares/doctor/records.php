<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('doctor');

$doctor   = db()->fetchOne("SELECT d.* FROM doctors d WHERE d.user_id=?", [$_SESSION['user_id']]);
$doctorId = $doctor['id'];

$apptId = (int)($_GET['appt'] ?? 0);

// If no appointment ID provided, show all medical records for this doctor
if (!$apptId) {
    $page      = max(1, (int)($_GET['page'] ?? 1));
    $search    = trim($_GET['search'] ?? '');
    $dateFrom  = $_GET['date_from'] ?? '';
    $dateTo    = $_GET['date_to'] ?? '';
    $offset    = ($page - 1) * ITEMS_PER_PAGE;

    // Build dynamic WHERE clause
    $where  = "WHERE mr.doctor_id=?";
    $params = [$doctorId];

    if ($search !== '') {
        $where   .= " AND (CONCAT(up.first_name,' ',up.last_name) LIKE ? OR mr.diagnosis LIKE ? OR mr.symptoms LIKE ?)";
        $like     = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if ($dateFrom !== '') {
        $where   .= " AND mr.record_date >= ?";
        $params[] = $dateFrom;
    }
    if ($dateTo !== '') {
        $where   .= " AND mr.record_date <= ?";
        $params[] = $dateTo;
    }

    $countParams = $params;
    $total = db()->fetchOne("
        SELECT COUNT(*) as c
        FROM medical_records mr
        JOIN appointments a ON mr.appointment_id = a.id
        JOIN patients p ON a.patient_id = p.id
        JOIN users up ON p.user_id = up.id
        $where", $countParams)['c'];

    $params[] = ITEMS_PER_PAGE;
    $params[] = $offset;

    $allRecords = db()->fetchAll("
        SELECT mr.*,
               CONCAT(up.first_name,' ',up.last_name) as patient_name,
               a.appointment_date, a.appointment_time, a.id as appt_id
        FROM medical_records mr
        JOIN appointments a ON mr.appointment_id = a.id
        JOIN patients p ON a.patient_id = p.id
        JOIN users up ON p.user_id = up.id
        $where
        ORDER BY mr.record_date DESC
        LIMIT ? OFFSET ?", $params);

    $totalPages  = ceil($total / ITEMS_PER_PAGE);
    $isFiltered  = ($search !== '' || $dateFrom !== '' || $dateTo !== '');

    $pageTitle = 'Medical Records';
    $activeNav = 'records';
    include __DIR__ . '/../includes/header.php';
    ?>
    <div class="page-header">
        <h1>Medical Records</h1>
        <p>All medical records you have created for your patients</p>
    </div>

    <!-- Search & Filter Bar -->
    <div class="card" style="margin-bottom:20px;">
        <div class="card-body" style="padding:16px 20px;">
            <form method="GET" action="" id="filterForm" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
                <!-- Live search -->
                <div style="flex:1;min-width:220px;">
                    <label style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:5px;">Search</label>
                    <input
                        type="text"
                        name="search"
                        id="searchInput"
                        class="form-control"
                        placeholder="Patient name, diagnosis, symptoms…"
                        value="<?= htmlspecialchars($search) ?>"
                        autocomplete="off"
                        style="margin:0;"
                    >
                </div>
                <!-- Date From -->
                <div style="min-width:150px;">
                    <label style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:5px;">From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>" style="margin:0;">
                </div>
                <!-- Date To -->
                <div style="min-width:150px;">
                    <label style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:5px;">To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>" style="margin:0;">
                </div>
                <!-- Buttons -->
                <div style="display:flex;gap:8px;align-items:center;">
                    <button type="submit" class="btn btn-primary">🔍 Search</button>
                    <?php if ($isFiltered): ?>
                        <a href="?" class="btn btn-secondary">✕ Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <?php if ($isFiltered): ?>
    <div style="margin-bottom:14px;font-size:13px;color:var(--text-muted);">
        <?= $total ?> result<?= $total !== 1 ? 's' : '' ?> found
        <?php if ($search): ?> for "<strong><?= htmlspecialchars($search) ?></strong>"<?php endif; ?>
        <?php if ($dateFrom || $dateTo): ?>
            — <?= $dateFrom ? date('M d, Y', strtotime($dateFrom)) : '…' ?> to <?= $dateTo ? date('M d, Y', strtotime($dateTo)) : 'today' ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($allRecords)): ?>
    <div class="card">
        <div class="card-body" style="text-align:center;padding:60px;">
            <div style="font-size:56px;margin-bottom:16px;"><?= $isFiltered ? '🔍' : '📋' ?></div>
            <h3 style="font-family:var(--font-display);margin-bottom:8px;">
                <?= $isFiltered ? 'No records match your search' : 'No medical records yet' ?>
            </h3>
            <p style="color:var(--text-muted);">
                <?= $isFiltered ? 'Try adjusting your filters or clearing the search.' : 'Medical records will appear here after you complete appointments.' ?>
            </p>
            <?php if ($isFiltered): ?>
                <a href="?" class="btn btn-secondary" style="margin-top:16px;">✕ Clear Filters</a>
            <?php else: ?>
                <a href="/cliniccares/doctor/appointments.php" class="btn btn-primary" style="margin-top:16px;">View Appointments</a>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-body" style="padding:0;">
            <table class="table" id="recordsTable">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Date</th>
                        <th>Diagnosis</th>
                        <th>Symptoms</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="recordsBody">
                <?php foreach ($allRecords as $rec): ?>
                    <tr data-search="<?= htmlspecialchars(strtolower($rec['patient_name'] . ' ' . ($rec['diagnosis'] ?? '') . ' ' . ($rec['symptoms'] ?? ''))) ?>">
                        <td>
                            <span style="font-weight:600;"><?= htmlspecialchars($rec['patient_name']) ?></span>
                        </td>
                        <td style="white-space:nowrap;"><?= date('M d, Y', strtotime($rec['record_date'])) ?></td>
                        <td><?= htmlspecialchars($rec['diagnosis'] ?? '—') ?></td>
                        <td style="color:var(--text-secondary);">
                            <?= htmlspecialchars(substr($rec['symptoms'] ?? '—', 0, 60)) ?><?= strlen($rec['symptoms'] ?? '') > 60 ? '…' : '' ?>
                        </td>
                        <td>
                            <a href="/cliniccares/doctor/records.php?appt=<?= $rec['appt_id'] ?>" class="btn btn-secondary btn-sm">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination (preserves filters) -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination" style="margin-top:16px;display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
        <?php
        $baseQuery = http_build_query(array_filter([
            'search'    => $search,
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
        ]));
        for ($i = 1; $i <= $totalPages; $i++):
        ?>
            <a href="?<?= $baseQuery ?>&page=<?= $i ?>"
               class="btn <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?> btn-sm"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <script>
    // Instant client-side search as the user types (debounced)
    (function () {
        var input   = document.getElementById('searchInput');
        var rows    = document.querySelectorAll('#recordsBody tr');
        var noMatch = null;
        var timer;

        if (!input || !rows.length) return;

        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                var q = input.value.trim().toLowerCase();
                var visible = 0;

                rows.forEach(function (row) {
                    var text = row.getAttribute('data-search') || '';
                    var show = q === '' || text.includes(q);
                    row.style.display = show ? '' : 'none';
                    if (show) visible++;
                });

                // Show/hide inline "no results" message
                if (noMatch) noMatch.remove();
                if (visible === 0 && q !== '') {
                    noMatch = document.createElement('tr');
                    noMatch.innerHTML = '<td colspan="5" style="text-align:center;padding:32px;color:var(--text-muted);">No records match "<strong>' + q + '</strong>"</td>';
                    document.getElementById('recordsBody').appendChild(noMatch);
                }
            }, 180);
        });
    })();
    </script>

    <?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$appt = db()->fetchOne("
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
$activeNav = 'records';
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
<div class="tabs" id="recordsTabs" style="margin-bottom:20px;">
    <button class="tab-btn active" onclick="switchTab('consultation', this)">🩺 Consultation</button>
    <button class="tab-btn" onclick="switchTab('history', this)">📋 Medical History (<?= count($medicalHistory) ?>)</button>
    <button class="tab-btn" onclick="switchTab('prescriptions', this)">💊 Prescriptions</button>
    <button class="tab-btn" onclick="switchTab('billing', this)">💳 Billing</button>
    <button class="tab-btn" onclick="switchTab('profile', this)">👤 Patient Profile</button>
</div>

<script>
function switchTab(tabId, btn) {
    // Deactivate all buttons in this tab bar
    document.querySelectorAll('#recordsTabs .tab-btn').forEach(function(b) {
        b.classList.remove('active');
    });
    btn.classList.add('active');

    // Hide all record tab panels
    ['consultation','history','prescriptions','billing','profile'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) { el.classList.remove('active'); el.style.display = 'none'; }
    });

    // Show the selected panel
    var target = document.getElementById(tabId);
    if (target) { target.classList.add('active'); target.style.display = 'block'; }
}

// On load: ensure only the active panel is visible
document.addEventListener('DOMContentLoaded', function() {
    ['history','prescriptions','billing','profile'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.style.display = 'none';
    });
    var active = document.getElementById('consultation');
    if (active) active.style.display = 'block';
});
</script>

<!-- Consultation Tab -->
<div class="tab-panel active" id="consultation" style="display:block;">
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
<div class="tab-panel" id="history" style="display:none;">
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
<div class="tab-panel" id="prescriptions" style="display:none;">
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
<div class="tab-panel" id="billing" style="display:none;">
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
<div class="tab-panel" id="profile" style="display:none;">
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