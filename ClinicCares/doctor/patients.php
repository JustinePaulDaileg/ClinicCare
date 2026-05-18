<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('doctor');

$doctor   = db()->fetchOne("SELECT d.* FROM doctors d WHERE d.user_id=?", [$_SESSION['user_id']]);
$doctorId = $doctor['id'];

$search    = sanitize($_GET['q'] ?? '');
$dateFrom  = sanitize($_GET['date_from'] ?? '');
$dateTo    = sanitize($_GET['date_to'] ?? '');
$gender    = sanitize($_GET['gender'] ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));
$offset    = ($page - 1) * ITEMS_PER_PAGE;

$where  = "a.doctor_id=?";
$params = [$doctorId];

if ($search) {
    $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.phone LIKE ?)";
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s]);
}
if ($dateFrom) {
    $where .= " AND a.appointment_date >= ?";
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where .= " AND a.appointment_date <= ?";
    $params[] = $dateTo;
}
if ($gender) {
    $where .= " AND p.gender = ?";
    $params[] = $gender;
}

$baseQ = "FROM (SELECT DISTINCT p.id as pid FROM appointments a JOIN patients p ON a.patient_id=p.id WHERE $where) sub
    JOIN patients p ON p.id=sub.pid
    JOIN users u ON p.user_id=u.id";

$total    = db()->fetchOne("SELECT COUNT(*) as c $baseQ", $params)['c'];
$patients = db()->fetchAll(
    "SELECT p.*, u.first_name, u.last_name, u.email, u.phone,
            (SELECT COUNT(*) FROM appointments a2 WHERE a2.patient_id=p.id AND a2.doctor_id=?) as my_appts,
            (SELECT MAX(a2.appointment_date) FROM appointments a2 WHERE a2.patient_id=p.id AND a2.doctor_id=?) as last_visit
     $baseQ ORDER BY u.first_name LIMIT ? OFFSET ?",
    array_merge([$doctorId, $doctorId], $params, [ITEMS_PER_PAGE, $offset])
);
$totalPages = ceil($total / ITEMS_PER_PAGE);

// Build query string helper for pagination
$qStr = http_build_query(array_filter([
    'q'         => $search,
    'date_from' => $dateFrom,
    'date_to'   => $dateTo,
    'gender'    => $gender,
]));

$hasFilters = $search || $dateFrom || $dateTo || $gender;

$pageTitle = 'My Patients';
$activeNav = 'patients';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>My Patients</h1>
    <p>Patients you have treated or have appointments with</p>
</div>

<!-- Search & Filter Bar -->
<div class="card" style="margin-bottom:20px;padding:16px 20px;">
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <!-- Text search -->
        <div class="search-input-wrap" style="flex:1;min-width:220px;">
            <span class="search-icon">🔍</span>
            <input type="text" id="psSearch" placeholder="Search by name or phone..." value="<?= htmlspecialchars($search) ?>" onkeyup="debounceApply()">
        </div>

        <!-- Date From -->
        <div style="display:flex;align-items:center;gap:6px;">
            <label style="font-size:13px;color:var(--text-muted);white-space:nowrap;">Visit from</label>
            <input type="date" id="psDateFrom" class="form-control" style="width:150px;padding:9px 10px;font-size:13px;" value="<?= htmlspecialchars($dateFrom) ?>" onchange="applyPS()">
        </div>

        <!-- Date To -->
        <div style="display:flex;align-items:center;gap:6px;">
            <label style="font-size:13px;color:var(--text-muted);white-space:nowrap;">to</label>
            <input type="date" id="psDateTo" class="form-control" style="width:150px;padding:9px 10px;font-size:13px;" value="<?= htmlspecialchars($dateTo) ?>" onchange="applyPS()">
        </div>

        <!-- Gender filter -->
        <select class="form-select" id="psGender" style="width:130px;" onchange="applyPS()">
            <option value="">All Genders</option>
            <option value="male"   <?= $gender==='male'?'selected':'' ?>>Male</option>
            <option value="female" <?= $gender==='female'?'selected':'' ?>>Female</option>
            <option value="other"  <?= $gender==='other'?'selected':'' ?>>Other</option>
        </select>

        <!-- Quick date presets -->
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <button class="btn btn-secondary btn-sm" onclick="setPreset('today')" title="Today">Today</button>
            <button class="btn btn-secondary btn-sm" onclick="setPreset('week')" title="This week">7d</button>
            <button class="btn btn-secondary btn-sm" onclick="setPreset('month')" title="This month">30d</button>
            <button class="btn btn-secondary btn-sm" onclick="setPreset('year')" title="This year">1yr</button>
        </div>

        <?php if ($hasFilters): ?>
        <a href="?" class="btn btn-secondary btn-sm" style="white-space:nowrap;">✕ Clear</a>
        <?php endif; ?>
    </div>

    <?php if ($hasFilters): ?>
    <div style="margin-top:10px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <span style="font-size:12px;color:var(--text-muted);">Active filters:</span>
        <?php if ($search): ?>
            <span style="background:var(--primary-light,#ede9fe);color:var(--primary);padding:2px 10px;border-radius:20px;font-size:12px;">🔍 "<?= htmlspecialchars($search) ?>"</span>
        <?php endif; ?>
        <?php if ($dateFrom || $dateTo): ?>
            <span style="background:var(--primary-light,#ede9fe);color:var(--primary);padding:2px 10px;border-radius:20px;font-size:12px;">
                📅 <?= $dateFrom ? htmlspecialchars($dateFrom) : '…' ?> → <?= $dateTo ? htmlspecialchars($dateTo) : '…' ?>
            </span>
        <?php endif; ?>
        <?php if ($gender): ?>
            <span style="background:var(--primary-light,#ede9fe);color:var(--primary);padding:2px 10px;border-radius:20px;font-size:12px;">⚧ <?= ucfirst(htmlspecialchars($gender)) ?></span>
        <?php endif; ?>
        <span style="font-size:12px;color:var(--text-muted);">— <?= $total ?> patient<?= $total!=1?'s':'' ?> found</span>
    </div>
    <?php endif; ?>
</div>

<!-- Patient Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
    <?php foreach ($patients as $pt): ?>
    <div class="card" style="transition:all 0.2s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='var(--shadow)'" onmouseout="this.style.transform='';this.style.boxShadow='var(--shadow-sm)'">
        <div style="padding:16px 18px;">
            <div class="d-flex align-center gap-12" style="margin-bottom:12px;">
                <div class="avatar" style="width:44px;height:44px;font-size:17px;flex-shrink:0;">
                    <?= strtoupper(substr($pt['first_name'],0,1).substr($pt['last_name'],0,1)) ?>
                </div>
                <div>
                    <div style="font-weight:700;"><?= htmlspecialchars($pt['first_name'].' '.$pt['last_name']) ?></div>
                    <div style="font-size:12px;color:var(--text-muted);">
                        <?= $pt['gender']?ucfirst($pt['gender']):'—' ?>
                        <?php if ($pt['date_of_birth']): ?>
                            · <?= date('Y')-date('Y',strtotime($pt['date_of_birth'])) ?> yrs
                        <?php endif; ?>
                        <?= $pt['blood_type']?' · '.$pt['blood_type']:'' ?>
                    </div>
                </div>
            </div>

            <div style="display:flex;flex-direction:column;gap:4px;font-size:13px;margin-bottom:12px;">
                <?php if ($pt['phone']): ?>
                <div style="color:var(--text-secondary);">📞 <?= htmlspecialchars($pt['phone']) ?></div>
                <?php endif; ?>
                <?php if ($pt['allergies']): ?>
                <div style="color:var(--danger);font-weight:500;">⚠️ <?= htmlspecialchars($pt['allergies']) ?></div>
                <?php endif; ?>
                <div style="color:var(--text-muted);">🗓️ Last visit: <?= $pt['last_visit']?formatDate($pt['last_visit']):'Never' ?></div>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span class="badge badge-primary"><?= $pt['my_appts'] ?> appointment<?= $pt['my_appts']!=1?'s':'' ?></span>
                <a href="/cliniccares/doctor/records.php?patient=<?= $pt['id'] ?>" class="btn btn-secondary btn-sm">📋 Records</a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($patients)): ?>
    <div style="grid-column:1/-1;">
        <div class="card"><div class="card-body" style="text-align:center;padding:40px;color:var(--text-muted);">
            <?= $hasFilters ? '😔 No patients match your filters. <a href="?" style="color:var(--primary);">Clear filters</a>' : 'No patients found' ?>
        </div></div>
    </div>
    <?php endif; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div style="display:flex;justify-content:center;gap:8px;margin-top:20px;">
    <?php for ($p=1;$p<=$totalPages;$p++): ?>
        <a href="?page=<?=$p?>&<?=$qStr?>" class="btn btn-sm <?=$p===$page?'btn-primary':'btn-secondary'?>"><?=$p?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<script>
let _debTimer;
function debounceApply() {
    clearTimeout(_debTimer);
    _debTimer = setTimeout(applyPS, 400);
}

function applyPS() {
    const q        = document.getElementById('psSearch').value;
    const dateFrom = document.getElementById('psDateFrom').value;
    const dateTo   = document.getElementById('psDateTo').value;
    const gender   = document.getElementById('psGender').value;
    const params   = new URLSearchParams();
    if (q)        params.set('q', q);
    if (dateFrom) params.set('date_from', dateFrom);
    if (dateTo)   params.set('date_to', dateTo);
    if (gender)   params.set('gender', gender);
    window.location.href = '?' + params.toString();
}

function setPreset(range) {
    const today = new Date();
    const fmt   = d => d.toISOString().split('T')[0];
    let from    = today;
    if (range === 'today') {
        document.getElementById('psDateFrom').value = fmt(today);
        document.getElementById('psDateTo').value   = fmt(today);
    } else if (range === 'week') {
        const d = new Date(today); d.setDate(today.getDate() - 7);
        document.getElementById('psDateFrom').value = fmt(d);
        document.getElementById('psDateTo').value   = fmt(today);
    } else if (range === 'month') {
        const d = new Date(today); d.setDate(today.getDate() - 30);
        document.getElementById('psDateFrom').value = fmt(d);
        document.getElementById('psDateTo').value   = fmt(today);
    } else if (range === 'year') {
        const d = new Date(today); d.setFullYear(today.getFullYear() - 1);
        document.getElementById('psDateFrom').value = fmt(d);
        document.getElementById('psDateTo').value   = fmt(today);
    }
    applyPS();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
