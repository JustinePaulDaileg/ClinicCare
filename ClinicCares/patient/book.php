<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('patient');

$patient = db()->fetchOne("SELECT p.* FROM patients p WHERE p.user_id=?", [$_SESSION['user_id']]);
$patientId = $patient['id'];

// Handle AJAX: get available slots
if (isset($_GET['slots'])) {
    header('Content-Type: application/json');
    $doctorId = (int)$_GET['doctor_id'];
    $date     = $_GET['date'] ?? '';
    if (!$doctorId || !$date) { echo json_encode(['slots'=>[]]); exit; }

    $doctor = db()->fetchOne("SELECT * FROM doctors WHERE id=?", [$doctorId]);
    if (!$doctor) { echo json_encode(['slots'=>[]]); exit; }

    // Check if doctor works on that weekday
    $dayName = date('l', strtotime($date));
    $workDays = explode(',', $doctor['available_days']);
    if (!in_array($dayName, $workDays)) { echo json_encode(['slots'=>[], 'day_off'=>true]); exit; }

    // Check overrides
    $override = db()->fetchOne("SELECT * FROM schedule_overrides WHERE doctor_id=? AND override_date=?", [$doctorId,$date]);
    if ($override && !$override['is_available']) { echo json_encode(['slots'=>[], 'day_off'=>true]); exit; }

    $startT = $override && $override['start_time'] ? $override['start_time'] : $doctor['start_time'];
    $endT   = $override && $override['end_time']   ? $override['end_time']   : $doctor['end_time'];
    $dur    = (int)$doctor['slot_duration'];

    // Get booked slots
    $booked = db()->fetchAll(
        "SELECT appointment_time FROM appointments WHERE doctor_id=? AND appointment_date=? AND status NOT IN ('cancelled')",
        [$doctorId, $date]
    );
    $bookedTimes = array_column($booked, 'appointment_time');

    // Generate slots
    $slots = [];
    $current = strtotime($startT);
    $end = strtotime($endT);
    while ($current + $dur*60 <= $end) {
        $timeStr = date('H:i:s', $current);
        $slots[] = [
            'time'   => $timeStr,
            'label'  => date('h:i A', $current),
            'taken'  => in_array($timeStr, $bookedTimes),
        ];
        $current += $dur * 60;
    }
    echo json_encode(['slots' => $slots]);
    exit;
}

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book'])) {
    header('Content-Type: application/json');
    $doctorId  = (int)$_POST['doctor_id'];
    $date      = $_POST['appointment_date'] ?? '';
    $time      = $_POST['appointment_time'] ?? '';
    $type      = in_array($_POST['type']??'',['consultation','follow_up','check_up','emergency'])?$_POST['type']:'consultation';
    $reason    = sanitize($_POST['reason'] ?? '');

    // Validation
    if (!$doctorId || !$date || !$time) {
        echo json_encode(['success'=>false,'error'=>'Missing required fields']); exit;
    }
    if (strtotime($date) < strtotime('today')) {
        echo json_encode(['success'=>false,'error'=>'Cannot book past dates']); exit;
    }

    // Check slot not already taken
    $existing = db()->fetchOne(
        "SELECT id FROM appointments WHERE doctor_id=? AND appointment_date=? AND appointment_time=? AND status NOT IN ('cancelled')",
        [$doctorId, $date, $time]
    );
    if ($existing) { echo json_encode(['success'=>false,'error'=>'This slot is no longer available']); exit; }

    $doctor = db()->fetchOne("SELECT * FROM doctors WHERE id=?", [$doctorId]);
    $dur = (int)($doctor['slot_duration'] ?? 30);
    $endTime = date('H:i:s', strtotime($time) + $dur*60);

    $apptId = db()->insert(
        "INSERT INTO appointments (patient_id,doctor_id,appointment_date,appointment_time,end_time,type,reason,status) VALUES (?,?,?,?,?,?,?,'pending')",
        [$patientId, $doctorId, $date, $time, $endTime, $type, $reason]
    );

    // Auto-generate invoice
    $inv = generateInvoiceNumber();
    $billingId = db()->insert(
        "INSERT INTO billing (invoice_number,patient_id,appointment_id,doctor_id,subtotal,total,balance,status,due_date) VALUES (?,?,?,?,?,?,?,?,?)",
        [$inv, $patientId, $apptId, $doctorId, $doctor['consultation_fee'], $doctor['consultation_fee'], $doctor['consultation_fee'], 'pending', date('Y-m-d',strtotime('+7 days'))]
    );
    db()->insert("INSERT INTO billing_items (billing_id,description,quantity,unit_price,total) VALUES (?,?,1,?,?)",
        [$billingId,'Consultation Fee',$doctor['consultation_fee'],$doctor['consultation_fee']]);

    // Notify doctor
    $docUser = db()->fetchOne("SELECT user_id FROM doctors WHERE id=?", [$doctorId]);
    createNotification($docUser['user_id'], 'New Appointment', "New appointment booked for ".formatDate($date)." at ".formatTime($time), 'appointment', '/doctor/appointments.php');

    // Notify patient
    createNotification($_SESSION['user_id'], 'Appointment Booked', "Your appointment on ".formatDate($date)." at ".formatTime($time)." is pending confirmation.", 'appointment', '/patient/appointments.php');

    logActivity($_SESSION['user_id'], 'BOOK_APPOINTMENT', "Appointment $apptId booked with doctor $doctorId on $date at $time");
    echo json_encode(['success'=>true, 'appt_id'=>$apptId]);
    exit;
}

// Doctors list
$doctors = db()->fetchAll("
    SELECT d.*, CONCAT(u.first_name,' ',u.last_name) as name
    FROM doctors d JOIN users u ON d.user_id=u.id
    WHERE u.is_active=1
    ORDER BY d.specialization, u.first_name");

// Group by specialization
$bySpec = [];
foreach ($doctors as $d) {
    $bySpec[$d['specialization']][] = $d;
}

$pageTitle = 'Book Appointment';
$activeNav = 'book';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Book an Appointment</h1>
    <p>Select a doctor and choose your preferred date and time</p>
</div>

<div style="display:grid;grid-template-columns:1fr 380px;gap:20px;" id="bookingLayout">
    <!-- Step 1: Doctor Selection -->
    <div>
        <div class="card mb-24" id="step1Card">
            <div class="card-header">
                <span class="card-title">Step 1: Choose a Doctor</span>
            </div>
            <div class="card-body">
                <div class="search-bar" style="margin-bottom:16px;">
                    <div class="search-input-wrap">
                        <span class="search-icon">🔍</span>
                        <input type="text" id="docSearch" placeholder="Search doctors..." oninput="filterDoctors(this.value)">
                    </div>
                    <select class="form-select" style="width:200px;" onchange="filterDoctors('', this.value)">
                        <option value="">All Specializations</option>
                        <?php foreach ($bySpec as $spec => $docs): ?>
                            <option value="<?= htmlspecialchars($spec) ?>"><?= htmlspecialchars($spec) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="doctorsList">
                    <?php foreach ($doctors as $doc): ?>
                    <div class="doctor-card" data-id="<?= $doc['id'] ?>" data-name="Dr. <?= htmlspecialchars($doc['name']) ?>"
                         data-spec="<?= htmlspecialchars($doc['specialization']) ?>"
                         data-fee="<?= $doc['consultation_fee'] ?>"
                         onclick="selectDoctor(this)"
                         style="border:1.5px solid var(--border);border-radius:10px;padding:14px 16px;margin-bottom:10px;cursor:pointer;display:flex;align-items:center;gap:14px;transition:all 0.2s;"
                         onmouseover="if(!this.classList.contains('selected'))this.style.borderColor='var(--primary)'"
                         onmouseout="if(!this.classList.contains('selected'))this.style.borderColor='var(--border)'">
                        <div class="avatar" style="width:48px;height:48px;font-size:18px;border-radius:12px;flex-shrink:0;">
                            <?= strtoupper(substr($doc['name'],0,2)) ?>
                        </div>
                        <div style="flex:1;">
                            <div style="font-weight:700;font-size:15px;">Dr. <?= htmlspecialchars($doc['name']) ?></div>
                            <div style="font-size:13px;color:var(--text-muted);"><?= htmlspecialchars($doc['specialization']) ?><?= $doc['department']?' · '.$doc['department']:'' ?></div>
                            <?php if ($doc['bio']): ?>
                            <div style="font-size:12px;color:var(--text-muted);margin-top:3px;"><?= htmlspecialchars(substr($doc['bio'],0,80)) ?>...</div>
                            <?php endif; ?>
                        </div>
                        <div style="text-align:right;flex-shrink:0;">
                            <div style="font-size:16px;font-weight:700;color:var(--primary);"><?= formatCurrency($doc['consultation_fee']) ?></div>
                            <div style="font-size:11px;color:var(--text-muted);">per consultation</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Step 2: Date & Time -->
        <div class="card mb-24" id="step2Card" style="opacity:0.4;pointer-events:none;">
            <div class="card-header">
                <span class="card-title">Step 2: Select Date & Time</span>
            </div>
            <div class="card-body">
                <div class="grid-2" style="margin-bottom:16px;">
                    <div class="form-group">
                        <label class="form-label">Appointment Date</label>
                        <input type="date" id="apptDate" class="form-control" min="<?= date('Y-m-d') ?>" onchange="loadSlots()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Appointment Type</label>
                        <select id="apptType" class="form-select">
                            <option value="consultation">Consultation</option>
                            <option value="follow_up">Follow-up</option>
                            <option value="check_up">Check-up</option>
                            <option value="emergency">Emergency</option>
                        </select>
                    </div>
                </div>
                <div id="slotsContainer">
                    <div style="color:var(--text-muted);font-size:13px;">Please select a date to view available time slots</div>
                </div>
            </div>
        </div>

        <!-- Step 3: Reason -->
        <div class="card" id="step3Card" style="opacity:0.4;pointer-events:none;">
            <div class="card-header">
                <span class="card-title">Step 3: Reason for Visit</span>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Reason / Chief Complaint</label>
                    <textarea id="apptReason" class="form-control" rows="3" placeholder="Briefly describe your symptoms or reason for visit..."></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Booking Summary -->
    <div>
        <div class="card" style="position:sticky;top:80px;">
            <div class="card-header"><span class="card-title">📋 Booking Summary</span></div>
            <div class="card-body" id="bookingSummary">
                <div style="text-align:center;padding:20px;color:var(--text-muted);">
                    <div style="font-size:48px;margin-bottom:12px;">📅</div>
                    <p style="font-size:13px;">Select a doctor and time slot to see your booking summary</p>
                </div>
            </div>
            <div class="card-footer">
                <button id="bookBtn" class="btn btn-primary btn-block btn-lg" disabled onclick="confirmBooking()">
                    Confirm Appointment
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let selectedDoctor = null;
let selectedSlot = null;

function selectDoctor(el) {
    document.querySelectorAll('.doctor-card').forEach(c => {
        c.classList.remove('selected');
        c.style.borderColor = 'var(--border)';
        c.style.background = '';
    });
    el.classList.add('selected');
    el.style.borderColor = 'var(--primary)';
    el.style.background = 'var(--primary-pale)';

    selectedDoctor = {
        id:   el.dataset.id,
        name: el.dataset.name,
        spec: el.dataset.spec,
        fee:  el.dataset.fee,
    };
    selectedSlot = null;

    // Enable step 2
    document.getElementById('step2Card').style.opacity = '1';
    document.getElementById('step2Card').style.pointerEvents = 'auto';
    document.getElementById('slotsContainer').innerHTML = '<div style="color:var(--text-muted);font-size:13px;">Select a date to see available time slots</div>';
    document.getElementById('apptDate').value = '';
    updateSummary();
}

function filterDoctors(search, spec) {
    const s = (search || document.getElementById('docSearch').value).toLowerCase();
    const sp = spec !== undefined ? spec : '';
    document.querySelectorAll('.doctor-card').forEach(c => {
        const name = c.dataset.name.toLowerCase();
        const cspec = c.dataset.spec.toLowerCase();
        const matchSearch = !s || name.includes(s) || cspec.includes(s);
        const matchSpec = !sp || cspec === sp.toLowerCase();
        c.style.display = matchSearch && matchSpec ? '' : 'none';
    });
}

function loadSlots() {
    const date = document.getElementById('apptDate').value;
    if (!date || !selectedDoctor) return;

    document.getElementById('slotsContainer').innerHTML = '<div class="spinner" style="margin:20px auto;"></div>';
    selectedSlot = null;
    updateSummary();

    fetch(`?slots=1&doctor_id=${selectedDoctor.id}&date=${date}`)
        .then(r => r.json())
        .then(data => {
            if (data.day_off) {
                document.getElementById('slotsContainer').innerHTML = '<div class="alert alert-warning">⚠️ The doctor is not available on this day. Please choose another date.</div>';
                return;
            }
            if (!data.slots || !data.slots.length) {
                document.getElementById('slotsContainer').innerHTML = '<div class="alert alert-info">No available slots on this date.</div>';
                return;
            }
            let html = '<div class="time-slots">';
            data.slots.forEach(slot => {
                html += `<div class="time-slot ${slot.taken?'taken':''}" data-time="${slot.time}" ${slot.taken?'':'onclick="selectSlot(this)"'}>
                    ${slot.label}${slot.taken?'<br><small>Taken</small>':''}
                </div>`;
            });
            html += '</div>';
            document.getElementById('slotsContainer').innerHTML = html;
        });
}

function selectSlot(el) {
    document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
    el.classList.add('selected');
    selectedSlot = el.dataset.time;

    // Enable step 3
    document.getElementById('step3Card').style.opacity = '1';
    document.getElementById('step3Card').style.pointerEvents = 'auto';
    updateSummary();
}

function updateSummary() {
    const date = document.getElementById('apptDate').value;
    const type = document.getElementById('apptType')?.value;
    const bookBtn = document.getElementById('bookBtn');

    if (!selectedDoctor) {
        document.getElementById('bookingSummary').innerHTML = `
            <div style="text-align:center;padding:20px;color:var(--text-muted);">
                <div style="font-size:48px;margin-bottom:12px;">📅</div>
                <p style="font-size:13px;">Select a doctor and time slot to see your booking summary</p>
            </div>`;
        bookBtn.disabled = true;
        return;
    }

    const canBook = selectedDoctor && date && selectedSlot;
    bookBtn.disabled = !canBook;

    document.getElementById('bookingSummary').innerHTML = `
        <div style="display:flex;flex-direction:column;gap:12px;">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px;background:var(--surface-2);border-radius:8px;">
                <span style="font-size:13px;color:var(--text-muted);">Doctor</span>
                <span style="font-weight:600;font-size:13px;">${selectedDoctor.name}</span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px;background:var(--surface-2);border-radius:8px;">
                <span style="font-size:13px;color:var(--text-muted);">Specialization</span>
                <span style="font-weight:600;font-size:13px;">${selectedDoctor.spec}</span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px;background:var(--surface-2);border-radius:8px;">
                <span style="font-size:13px;color:var(--text-muted);">Date</span>
                <span style="font-weight:600;font-size:13px;">${date ? new Date(date+'T00:00:00').toLocaleDateString('en-US',{month:'long',day:'numeric',year:'numeric'}) : '—'}</span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px;background:var(--surface-2);border-radius:8px;">
                <span style="font-size:13px;color:var(--text-muted);">Time</span>
                <span style="font-weight:600;font-size:13px;">${selectedSlot ? formatTime12(selectedSlot) : '—'}</span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px;background:var(--surface-2);border-radius:8px;">
                <span style="font-size:13px;color:var(--text-muted);">Type</span>
                <span style="font-weight:600;font-size:13px;">${type ? type.replace('_',' ').replace(/\b\w/g,c=>c.toUpperCase()) : '—'}</span>
            </div>
            <div style="border-top:2px solid var(--border);padding-top:12px;display:flex;justify-content:space-between;align-items:center;">
                <span style="font-size:14px;font-weight:600;">Consultation Fee</span>
                <span style="font-size:18px;font-weight:800;color:var(--primary);">₱${parseFloat(selectedDoctor.fee).toFixed(2)}</span>
            </div>
            <div style="font-size:11px;color:var(--text-muted);text-align:center;">Payment due within 7 days of appointment</div>
        </div>
    `;
}

function formatTime12(t) {
    if (!t) return '—';
    const [h, m] = t.split(':');
    const hour = parseInt(h);
    return (hour > 12 ? hour-12 : hour||12) + ':' + m + ' ' + (hour >= 12 ? 'PM' : 'AM');
}

function confirmBooking() {
    const date = document.getElementById('apptDate').value;
    const type = document.getElementById('apptType').value;
    const reason = document.getElementById('apptReason').value;

    if (!selectedDoctor || !date || !selectedSlot) { showToast('Please complete all steps', 'warning'); return; }

    const btn = document.getElementById('bookBtn');
    btn.disabled = true;
    btn.textContent = '⏳ Booking...';

    const fd = new FormData();
    fd.set('book', 1);
    fd.set('doctor_id', selectedDoctor.id);
    fd.set('appointment_date', date);
    fd.set('appointment_time', selectedSlot);
    fd.set('type', type);
    fd.set('reason', reason);

    fetch('', {method:'POST', body:fd}).then(r=>r.json()).then(res => {
        if (res.success) {
            showToast('Appointment booked successfully! Awaiting confirmation.', 'success');
            setTimeout(() => window.location.href = '/patient/appointments.php', 1500);
        } else {
            showToast(res.error || 'Booking failed', 'danger');
            btn.disabled = false;
            btn.textContent = 'Confirm Appointment';
        }
    });
}

// Update summary on type change
document.getElementById('apptType')?.addEventListener('change', updateSummary);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>