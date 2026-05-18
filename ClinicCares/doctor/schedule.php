<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('doctor');

$doctor   = db()->fetchOne("SELECT d.*, u.first_name, u.last_name FROM doctors d JOIN users u ON d.user_id=u.id WHERE d.user_id=?", [$_SESSION['user_id']]);
$doctorId = $doctor['id'];

// Save schedule settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'update_schedule') {
        $days     = isset($_POST['available_days']) ? implode(',', array_map('sanitize', $_POST['available_days'])) : '';
        $start    = $_POST['start_time'] ?? '08:00';
        $end      = $_POST['end_time'] ?? '17:00';
        $duration = max(15, min(120, (int)($_POST['slot_duration'] ?? 30)));
        $fee      = max(0, (float)($_POST['consultation_fee'] ?? 0));
        $bio      = sanitize($_POST['bio'] ?? '');

        db()->execute(
            "UPDATE doctors SET available_days=?,start_time=?,end_time=?,slot_duration=?,consultation_fee=?,bio=? WHERE id=?",
            [$days, $start.':00', $end.':00', $duration, $fee, $bio, $doctorId]
        );
        echo json_encode(['success'=>true]);
        exit;
    }

    if ($action === 'add_override') {
        $date      = $_POST['override_date'] ?? '';
        $avail     = isset($_POST['is_available']) ? 1 : 0;
        $startT    = $_POST['override_start'] ?: null;
        $endT      = $_POST['override_end'] ?: null;
        $reason    = sanitize($_POST['reason'] ?? '');

        // Upsert
        $existing = db()->fetchOne("SELECT id FROM schedule_overrides WHERE doctor_id=? AND override_date=?", [$doctorId,$date]);
        if ($existing) {
            db()->execute("UPDATE schedule_overrides SET is_available=?,start_time=?,end_time=?,reason=? WHERE id=?",
                [$avail, $startT, $endT, $reason, $existing['id']]);
        } else {
            db()->insert("INSERT INTO schedule_overrides (doctor_id,override_date,is_available,start_time,end_time,reason) VALUES (?,?,?,?,?,?)",
                [$doctorId,$date,$avail,$startT,$endT,$reason]);
        }
        echo json_encode(['success'=>true]);
        exit;
    }

    if ($action === 'delete_override') {
        $id = (int)$_POST['id'];
        db()->execute("DELETE FROM schedule_overrides WHERE id=? AND doctor_id=?", [$id,$doctorId]);
        echo json_encode(['success'=>true]);
        exit;
    }

    if ($action === 'update_clinic_location') {
        $clinic_name    = sanitize($_POST['clinic_name']    ?? '');
        $clinic_address = sanitize($_POST['clinic_address'] ?? '');
        $clinic_city    = sanitize($_POST['clinic_city']    ?? '');
        $clinic_phone   = sanitize($_POST['clinic_phone']   ?? '');
        $clinic_hours   = sanitize($_POST['clinic_hours']   ?? '');
        $accepts_walkin = isset($_POST['accepts_walkin']) ? 1 : 0;
        $telemedicine   = isset($_POST['telemedicine'])   ? 1 : 0;
        $lat = (float)($_POST['clinic_lat'] ?? 0);
        $lng = (float)($_POST['clinic_lng'] ?? 0);

        if (!$lat || !$lng) {
            echo json_encode(['success'=>false,'error'=>'Please pin your clinic on the map.']);
            exit;
        }

        db()->execute(
            "UPDATE doctors SET clinic_name=?,clinic_address=?,clinic_city=?,clinic_phone=?,
             clinic_hours=?,accepts_walkin=?,telemedicine=?,clinic_lat=?,clinic_lng=? WHERE id=?",
            [$clinic_name,$clinic_address,$clinic_city,$clinic_phone,
             $clinic_hours,$accepts_walkin,$telemedicine,$lat,$lng,$doctorId]
        );
        echo json_encode(['success'=>true]);
        exit;
    }
}

// Reload doctor after potential update
$doctor = db()->fetchOne("SELECT d.*, u.first_name, u.last_name FROM doctors d JOIN users u ON d.user_id=u.id WHERE d.user_id=?", [$_SESSION['user_id']]);

// Upcoming overrides
$overrides = db()->fetchAll(
    "SELECT * FROM schedule_overrides WHERE doctor_id=? AND override_date >= CURDATE() ORDER BY override_date ASC LIMIT 20",
    [$doctorId]
);

// This week's appointments count per day
$weekAppts = [];
for ($i = 0; $i < 7; $i++) {
    $d = date('Y-m-d', strtotime("+$i days"));
    $weekAppts[$d] = db()->fetchOne("SELECT COUNT(*) as c FROM appointments WHERE doctor_id=? AND appointment_date=? AND status NOT IN ('cancelled')", [$doctorId,$d])['c'];
}

$allDays  = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$workDays = explode(',', $doctor['available_days'] ?? 'Monday,Tuesday,Wednesday,Thursday,Friday');

$pageTitle = 'My Schedule';
$activeNav = 'schedule';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Schedule Management</h1>
    <p>Configure your availability and working hours</p>
</div>

<div class="grid-2" style="grid-template-columns:1fr 360px;gap:20px;">
    <!-- Schedule Settings -->
    <div>
        <div class="card mb-16">
            <div class="card-header"><span class="card-title">⚙️ Working Hours & Availability</span></div>
            <div class="card-body">
                <form id="scheduleForm">
                    <div class="form-group">
                        <label class="form-label">Available Days</label>
                        <div style="display:flex;flex-wrap:wrap;gap:8px;">
                            <?php foreach ($allDays as $day): ?>
                            <label style="display:flex;align-items:center;gap:6px;background:<?= in_array($day,$workDays)?'var(--primary)':'var(--surface-2)' ?>;color:<?= in_array($day,$workDays)?'#fff':'var(--text-secondary)' ?>;padding:8px 14px;border-radius:8px;cursor:pointer;border:1.5px solid <?= in_array($day,$workDays)?'var(--primary)':'var(--border)' ?>;transition:all 0.2s;font-size:13px;font-weight:500;" id="day-label-<?= $day ?>">
                                <input type="checkbox" name="available_days[]" value="<?= $day ?>" <?= in_array($day,$workDays)?'checked':'' ?>
                                    style="display:none;" onchange="toggleDayLabel(this,'<?= $day ?>')">
                                <?= $day ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Start Time</label>
                            <input type="time" name="start_time" class="form-control" value="<?= substr($doctor['start_time'],0,5) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">End Time</label>
                            <input type="time" name="end_time" class="form-control" value="<?= substr($doctor['end_time'],0,5) ?>">
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Slot Duration (minutes)</label>
                            <select name="slot_duration" class="form-select">
                                <?php foreach ([15,20,30,45,60] as $d): ?>
                                    <option value="<?=$d?>" <?= $doctor['slot_duration']==$d?'selected':'' ?>><?=$d?> min</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Consultation Fee (₱)</label>
                            <input type="number" name="consultation_fee" class="form-control" min="0" step="50" value="<?= $doctor['consultation_fee'] ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Bio / Professional Summary</label>
                        <textarea name="bio" class="form-control" rows="3"><?= htmlspecialchars($doctor['bio']??'') ?></textarea>
                    </div>
                </form>
            </div>
            <div class="card-footer d-flex justify-between align-center">
                <span style="font-size:13px;color:var(--text-muted);">Changes apply to future bookings</span>
                <button class="btn btn-primary" onclick="saveSchedule()">💾 Save Schedule</button>
            </div>
        </div>

        <!-- This Week's View -->
        <div class="card">
            <div class="card-header"><span class="card-title">📅 This Week's Appointments</span></div>
            <div class="card-body" style="padding:0;">
                <?php foreach ($weekAppts as $date => $count):
                    $dayName = date('l', strtotime($date));
                    $isOff   = !in_array($dayName, $workDays);
                    $override = db()->fetchOne("SELECT * FROM schedule_overrides WHERE doctor_id=? AND override_date=?", [$doctorId,$date]);
                    $isOverrideOff = $override && !$override['is_available'];
                ?>
                <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;">
                    <div style="width:42px;text-align:center;flex-shrink:0;">
                        <div style="font-size:11px;text-transform:uppercase;color:var(--text-muted);font-weight:700;"><?= date('D',strtotime($date)) ?></div>
                        <div style="font-size:18px;font-weight:800;color:<?= $date===date('Y-m-d')?'var(--primary)':'var(--text-primary)' ?>;"><?= date('d',strtotime($date)) ?></div>
                    </div>
                    <div style="flex:1;">
                        <?php if ($isOff || $isOverrideOff): ?>
                        <div style="font-size:13px;color:var(--text-muted);font-style:italic;">
                            <?= $override && $override['reason'] ? '🔴 '.$override['reason'] : '—  Day off' ?>
                        </div>
                        <?php else: ?>
                        <div style="font-size:13px;font-weight:600;"><?= $count ?> appointment<?= $count!=1?'s':'' ?></div>
                        <div style="font-size:12px;color:var(--text-muted);"><?= formatTime($doctor['start_time']) ?> – <?= formatTime($doctor['end_time']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <?php if ($date === date('Y-m-d')): ?>
                        <span class="badge badge-primary">Today</span>
                        <?php elseif ($isOff || $isOverrideOff): ?>
                        <span class="badge badge-secondary">Closed</span>
                        <?php else: ?>
                        <span class="badge badge-success">Open</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Right: Override / Day Off -->
    <div style="display:flex;flex-direction:column;gap:16px;">
        <div class="card">
            <div class="card-header"><span class="card-title">📌 Add Day Override</span></div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Date</label>
                    <input type="date" id="ovDate" class="form-control" min="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;">
                        <input type="checkbox" id="ovAvail" onchange="toggleOverrideFields()" style="width:16px;height:16px;">
                        Available this day (override with custom hours)
                    </label>
                </div>
                <div id="ovHours" style="display:none;">
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Start Time</label>
                            <input type="time" id="ovStart" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">End Time</label>
                            <input type="time" id="ovEnd" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Reason</label>
                    <input type="text" id="ovReason" class="form-control" placeholder="e.g. Medical conference, Holiday...">
                </div>
                <button class="btn btn-primary btn-block" onclick="addOverride()">➕ Add Override</button>
            </div>
        </div>

        <!-- Upcoming overrides -->
        <div class="card">
            <div class="card-header"><span class="card-title">📋 Upcoming Overrides</span></div>
            <?php if (empty($overrides)): ?>
            <div style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px;">No schedule overrides set</div>
            <?php else: ?>
            <div>
                <?php foreach ($overrides as $ov): ?>
                <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:8px;">
                    <div>
                        <div style="font-size:13px;font-weight:600;"><?= formatDate($ov['override_date']) ?></div>
                        <div style="font-size:12px;color:var(--text-muted);">
                            <?= $ov['is_available'] ? '✅ Available '.($ov['start_time']?formatTime($ov['start_time']).' – '.formatTime($ov['end_time']):'') : '🔴 Day off' ?>
                            <?= $ov['reason'] ? ' — '.$ov['reason'] : '' ?>
                        </div>
                    </div>
                    <button class="btn btn-danger btn-sm" onclick="delOverride(<?=$ov['id']?>)">✕</button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleDayLabel(cb, day) {
    const lbl = document.getElementById('day-label-'+day);
    if (cb.checked) {
        lbl.style.background='var(--primary)'; lbl.style.color='#fff'; lbl.style.borderColor='var(--primary)';
    } else {
        lbl.style.background='var(--surface-2)'; lbl.style.color='var(--text-secondary)'; lbl.style.borderColor='var(--border)';
    }
}

function saveSchedule() {
    const form = document.getElementById('scheduleForm');
    const fd = new FormData(form);
    fd.set('action','update_schedule');
    // Ensure unchecked days are not sent (FormData handles checkboxes correctly)
    fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(res=>{
        if(res.success) showToast('Schedule saved!','success');
        else showToast('Error saving','danger');
    });
}

function toggleOverrideFields() {
    const avail = document.getElementById('ovAvail').checked;
    document.getElementById('ovHours').style.display = avail ? 'block' : 'none';
}

function addOverride() {
    const date   = document.getElementById('ovDate').value;
    const avail  = document.getElementById('ovAvail').checked;
    const start  = document.getElementById('ovStart').value;
    const end    = document.getElementById('ovEnd').value;
    const reason = document.getElementById('ovReason').value;
    if (!date) { showToast('Select a date','warning'); return; }

    const fd = new FormData();
    fd.set('action','add_override'); fd.set('override_date',date);
    if (avail) { fd.set('is_available','1'); fd.set('override_start',start); fd.set('override_end',end); }
    fd.set('reason',reason);
    fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(res=>{
        if(res.success){showToast('Override added!','success');location.reload();}
        else showToast('Error','danger');
    });
}

function delOverride(id) {
    confirmAction('Remove this schedule override?',()=>{
        const fd=new FormData(); fd.set('action','delete_override'); fd.set('id',id);
        fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(res=>{
            if(res.success){showToast('Removed','success');location.reload();}
        });
    });
}
</script>


<!-- ═══════════════════════════════════════════════════════════════
     CLINIC LOCATION CARD
════════════════════════════════════════════════════════════════ -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <span class="card-title">📍 My Clinic Location</span>
        <span style="font-size:12px;color:var(--text-muted);">Shown to patients in "Find Nearest Clinic"</span>
    </div>
    <div class="card-body">

        <!-- Info fields -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
            <div class="form-group" style="margin:0;">
                <label class="form-label">Clinic / Practice Name</label>
                <input type="text" id="clName" class="form-control"
                    placeholder="e.g. Reyes Family Clinic"
                    value="<?= htmlspecialchars($doctor['clinic_name'] ?? '') ?>">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">City / Municipality</label>
                <input type="text" id="clCity" class="form-control"
                    placeholder="e.g. Quezon City"
                    value="<?= htmlspecialchars($doctor['clinic_city'] ?? '') ?>">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Street Address</label>
                <input type="text" id="clAddress" class="form-control"
                    placeholder="e.g. 45 Taft Avenue"
                    value="<?= htmlspecialchars($doctor['clinic_address'] ?? '') ?>">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Clinic Phone</label>
                <input type="text" id="clPhone" class="form-control"
                    placeholder="e.g. 09XX-XXX-XXXX"
                    value="<?= htmlspecialchars($doctor['clinic_phone'] ?? '') ?>">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Clinic Hours</label>
                <input type="text" id="clHours" class="form-control"
                    placeholder="e.g. Mon-Fri 8am-5pm"
                    value="<?= htmlspecialchars($doctor['clinic_hours'] ?? '') ?>">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Options</label>
                <div style="display:flex;gap:20px;margin-top:8px;">
                    <label style="display:flex;align-items:center;gap:7px;cursor:pointer;font-size:13px;font-weight:500;">
                        <input type="checkbox" id="clWalkin"
                            <?= !empty($doctor['accepts_walkin']) ? 'checked' : '' ?>>
                        🚶 Walk-ins accepted
                    </label>
                    <label style="display:flex;align-items:center;gap:7px;cursor:pointer;font-size:13px;font-weight:500;">
                        <input type="checkbox" id="clTele"
                            <?= !empty($doctor['telemedicine']) ? 'checked' : '' ?>>
                        📱 Telemedicine
                    </label>
                </div>
            </div>
        </div>

        <!-- Map picker -->
        <div style="margin-bottom:16px;">
            <label class="form-label">📌 Pin Your Clinic on the Map</label>
            <p style="font-size:12px;color:var(--text-muted);margin-bottom:10px;">
                Search for your address, then click or drag the pin to your exact location.
            </p>
            <div style="display:flex;gap:8px;margin-bottom:10px;">
                <input type="text" id="mapSearch" class="form-control"
                    placeholder="Search address or landmark…" style="flex:1;">
                <button class="btn btn-secondary btn-sm" onclick="clSearchAddress()">🔍 Search</button>
                <button class="btn btn-secondary btn-sm" onclick="clLocateMe()">📡 My Location</button>
            </div>
            <div id="clMap" style="height:340px;border-radius:10px;border:1.5px solid var(--border);overflow:hidden;"></div>
            <div style="display:flex;gap:12px;margin-top:10px;align-items:flex-end;">
                <div style="flex:1;">
                    <label class="form-label" style="font-size:11px;margin-bottom:3px;">Latitude</label>
                    <input type="text" id="clLat" class="form-control" readonly
                        value="<?= htmlspecialchars($doctor['clinic_lat'] ?? '') ?>"
                        placeholder="Click map to set"
                        style="font-size:12px;color:var(--text-muted);background:var(--surface-2);">
                </div>
                <div style="flex:1;">
                    <label class="form-label" style="font-size:11px;margin-bottom:3px;">Longitude</label>
                    <input type="text" id="clLng" class="form-control" readonly
                        value="<?= htmlspecialchars($doctor['clinic_lng'] ?? '') ?>"
                        placeholder="Click map to set"
                        style="font-size:12px;color:var(--text-muted);background:var(--surface-2);">
                </div>
                <div>
                    <?php if (!empty($doctor['clinic_lat'])): ?>
                    <span style="display:inline-block;font-size:12px;font-weight:600;padding:8px 14px;border-radius:8px;background:#dcfce7;color:#15803d;" id="clPinStatus">✅ Location set</span>
                    <?php else: ?>
                    <span style="display:inline-block;font-size:12px;font-weight:600;padding:8px 14px;border-radius:8px;background:#fef9c3;color:#a16207;" id="clPinStatus">⚠️ Not pinned yet</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <button class="btn btn-primary" onclick="clSave()">💾 Save Clinic Location</button>
    </div>
</div>

<script>
(function () {
    let clMap, clMarker;
    const initLat = <?= json_encode(!empty($doctor['clinic_lat']) ? (float)$doctor['clinic_lat'] : null) ?>;
    const initLng = <?= json_encode(!empty($doctor['clinic_lng']) ? (float)$doctor['clinic_lng'] : null) ?>;

    document.addEventListener('DOMContentLoaded', () => {
        const center = (initLat && initLng) ? [initLat, initLng] : [14.5995, 120.9842];
        const zoom   = (initLat && initLng) ? 16 : 12;

        clMap = L.map('clMap', { center, zoom });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19,
        }).addTo(clMap);

        if (initLat && initLng) clDropPin(initLat, initLng, false);

        clMap.on('click', e => clDropPin(e.latlng.lat, e.latlng.lng, true));

        document.getElementById('mapSearch').addEventListener('keydown', e => {
            if (e.key === 'Enter') clSearchAddress();
        });
    });

    function clDropPin(lat, lng, flyTo) {
        if (clMarker) clMarker.remove();
        clMarker = L.marker([lat, lng], { draggable: true })
            .addTo(clMap)
            .bindPopup('<b style="font-family:sans-serif;font-size:13px;">📍 Your clinic</b>')
            .openPopup();
        clMarker.on('dragend', e => {
            const p = e.target.getLatLng();
            clSetCoords(p.lat, p.lng);
        });
        clSetCoords(lat, lng);
        if (flyTo) clMap.setView([lat, lng], Math.max(clMap.getZoom(), 16));
    }

    function clSetCoords(lat, lng) {
        document.getElementById('clLat').value = lat.toFixed(7);
        document.getElementById('clLng').value = lng.toFixed(7);
        const s = document.getElementById('clPinStatus');
        s.textContent = '✅ Location pinned';
        s.style.background = '#dcfce7';
        s.style.color = '#15803d';
        clReverseGeocode(lat, lng);
    }

    function clReverseGeocode(lat, lng) {
        const addrEl  = document.getElementById('clAddress');
        const cityEl  = document.getElementById('clCity');
        const nameEl  = document.getElementById('clName');
        const searchEl = document.getElementById('mapSearch');

        // Show loading state on fields
        addrEl.placeholder = 'Looking up address…';

        fetch(
            'https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lng + '&zoom=18&addressdetails=1',
            { headers: { 'Accept-Language': 'en' } }
        )
        .then(r => r.json())
        .then(data => {
            if (!data || !data.address) return;
            const a = data.address;

            // Build street address from available parts
            const road   = a.road || a.pedestrian || a.footway || a.path || '';
            const houseNo = a.house_number || '';
            const street = [houseNo, road].filter(Boolean).join(' ');

            // Neighbourhood / suburb / district fallbacks
            const suburb = a.suburb || a.neighbourhood || a.quarter || a.hamlet || '';

            // City: prefer city > town > municipality > province
            const city = a.city || a.town || a.municipality || a.county || a.province || a.state || '';

            // Fill address field — only overwrite if currently empty or was a placeholder
            if (!addrEl.value || addrEl.value === addrEl.dataset.lastAuto) {
                const fullStreet = [street, suburb].filter(Boolean).join(', ');
                addrEl.value = fullStreet;
                addrEl.dataset.lastAuto = fullStreet;
            }

            // Fill city field — only overwrite if currently empty
            if (!cityEl.value || cityEl.value === cityEl.dataset.lastAuto) {
                cityEl.value = city;
                cityEl.dataset.lastAuto = city;
            }

            // Update search bar to show where we are
            searchEl.value = data.display_name
                ? data.display_name.split(',').slice(0, 3).join(', ')
                : '';

            addrEl.placeholder = 'e.g. 45 Taft Avenue';
        })
        .catch(() => {
            addrEl.placeholder = 'e.g. 45 Taft Avenue';
        });
    }

    window.clSearchAddress = function () {
        const q = document.getElementById('mapSearch').value.trim();
        if (!q) return;
        fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&addressdetails=1&q=' + encodeURIComponent(q), {
            headers: { 'Accept-Language': 'en' }
        })
        .then(r => r.json())
        .then(data => {
            if (!data.length) { showToast('Address not found — try a different search.', 'warning'); return; }
            const item = data[0];
            const lat  = parseFloat(item.lat), lng = parseFloat(item.lon);

            // Drop pin (this triggers clSetCoords → clReverseGeocode, but we
            // can fill fields immediately from the forward-geocode response)
            clDropPin(lat, lng, true);

            // Fill fields directly from search result's addressdetails
            if (item.address) {
                const a      = item.address;
                const road   = a.road || a.pedestrian || a.footway || '';
                const houseNo = a.house_number || '';
                const suburb = a.suburb || a.neighbourhood || a.quarter || '';
                const city   = a.city || a.town || a.municipality || a.county || a.province || '';

                const addrEl = document.getElementById('clAddress');
                const cityEl = document.getElementById('clCity');

                const fullStreet = [houseNo, road, suburb].filter(Boolean).join(', ');
                if (fullStreet) { addrEl.value = fullStreet; addrEl.dataset.lastAuto = fullStreet; }
                if (city)       { cityEl.value = city;       cityEl.dataset.lastAuto = city; }
            }

            showToast('Found: ' + item.display_name.split(',').slice(0, 3).join(', '), 'success');
        })
        .catch(() => showToast('Search failed. Check your internet connection.', 'danger'));
    };

    window.clLocateMe = function () {
        if (!navigator.geolocation) { showToast('Geolocation not supported by your browser.', 'warning'); return; }
        navigator.geolocation.getCurrentPosition(
            pos => { clDropPin(pos.coords.latitude, pos.coords.longitude, true); showToast('Location detected!', 'success'); },
            ()  => showToast('Could not get your location.', 'warning'),
            { timeout: 8000 }
        );
    };

    window.clSave = function () {
        const lat = document.getElementById('clLat').value;
        const lng = document.getElementById('clLng').value;
        if (!lat || !lng) { showToast('Please pin your clinic on the map first.', 'warning'); return; }

        const fd = new FormData();
        fd.set('action',         'update_clinic_location');
        fd.set('clinic_name',    document.getElementById('clName').value);
        fd.set('clinic_address', document.getElementById('clAddress').value);
        fd.set('clinic_city',    document.getElementById('clCity').value);
        fd.set('clinic_phone',   document.getElementById('clPhone').value);
        fd.set('clinic_hours',   document.getElementById('clHours').value);
        fd.set('clinic_lat',     lat);
        fd.set('clinic_lng',     lng);
        if (document.getElementById('clWalkin').checked) fd.set('accepts_walkin', '1');
        if (document.getElementById('clTele').checked)   fd.set('telemedicine',   '1');

        fetch('', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) showToast('✅ Clinic location saved! You now appear in Find Clinic.', 'success');
                else showToast(res.error || 'Error saving.', 'danger');
            })
            .catch(() => showToast('Network error — please try again.', 'danger'));
    };
}());
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>