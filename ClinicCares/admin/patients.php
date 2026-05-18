<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('admin');

$search       = sanitize($_GET['q'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');
$bloodFilter  = sanitize($_GET['blood'] ?? '');
$genderFilter = sanitize($_GET['gender'] ?? '');
$sortBy       = in_array($_GET['sort'] ?? '', ['name','email','created','appts']) ? $_GET['sort'] : 'created';
$page         = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($page - 1) * ITEMS_PER_PAGE;

$where  = '1=1'; $params = [];
if ($search) {
    $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR p.insurance_number LIKE ? OR u.phone LIKE ?)";
    $s = "%$search%"; $params = [$s,$s,$s,$s,$s];
}
if ($statusFilter !== '') {
    $where .= " AND u.is_active=?";
    $params[] = (int)$statusFilter;
}
if ($bloodFilter) {
    $where .= " AND p.blood_type=?";
    $params[] = $bloodFilter;
}
if ($genderFilter) {
    $where .= " AND p.gender=?";
    $params[] = $genderFilter;
}

$orderMap = [
    'name'    => 'u.first_name ASC, u.last_name ASC',
    'email'   => 'u.email ASC',
    'created' => 'u.created_at DESC',
    'appts'   => 'total_appts DESC',
];
$orderSQL = $orderMap[$sortBy];

$baseQ = "FROM patients p JOIN users u ON p.user_id=u.id WHERE $where";
$total    = db()->fetchOne("SELECT COUNT(*) as c $baseQ", $params)['c'];
$patients = db()->fetchAll(
    "SELECT p.*, u.first_name, u.last_name, u.email, u.phone, u.is_active, u.created_at,
            (SELECT COUNT(*) FROM appointments a WHERE a.patient_id=p.id) as total_appts,
            (SELECT COUNT(*) FROM billing b WHERE b.patient_id=p.id AND b.status IN ('pending','partial')) as unpaid_bills
     $baseQ ORDER BY $orderSQL LIMIT ? OFFSET ?",
    array_merge($params, [ITEMS_PER_PAGE, $offset])
);
$totalPages = ceil($total / ITEMS_PER_PAGE);

$bloodTypes = db()->fetchAll("SELECT DISTINCT blood_type FROM patients WHERE blood_type IS NOT NULL AND blood_type!='' ORDER BY blood_type");
$activeFilters = (int)($search !== '') + (int)($statusFilter !== '') + (int)($bloodFilter !== '') + (int)($genderFilter !== '');

$pageTitle = 'Patients';
$activeNav = 'patients';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-between align-center">
    <div>
        <h1>Patient Management</h1>
        <p>View and manage all registered patients (<?= $total ?> total)</p>
    </div>
</div>

<div class="search-bar">
    <div class="search-input-wrap">
        <span class="search-icon">🔍</span>
        <input type="text" id="pSearch" placeholder="Search name, email, phone, insurance…"
               value="<?= htmlspecialchars($search) ?>" onkeyup="debounceFilter()">
    </div>
    <select class="form-select" id="fStatus" style="width:140px;" onchange="applyFilters()">
        <option value="">All Status</option>
        <option value="1" <?= $statusFilter==='1'?'selected':'' ?>>Active</option>
        <option value="0" <?= $statusFilter==='0'?'selected':'' ?>>Inactive</option>
    </select>
    <select class="form-select" id="fGender" style="width:130px;" onchange="applyFilters()">
        <option value="">All Genders</option>
        <option value="male"   <?= $genderFilter==='male'?'selected':'' ?>>Male</option>
        <option value="female" <?= $genderFilter==='female'?'selected':'' ?>>Female</option>
        <option value="other"  <?= $genderFilter==='other'?'selected':'' ?>>Other</option>
    </select>
    <select class="form-select" id="fBlood" style="width:130px;" onchange="applyFilters()">
        <option value="">All Blood Types</option>
        <?php foreach ($bloodTypes as $bt): ?>
            <option value="<?= htmlspecialchars($bt['blood_type']) ?>" <?= $bloodFilter===$bt['blood_type']?'selected':'' ?>>
                <?= htmlspecialchars($bt['blood_type']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <select class="form-select" id="fSort" style="width:155px;" onchange="applyFilters()">
        <option value="created" <?= $sortBy==='created'?'selected':'' ?>>Newest First</option>
        <option value="name"    <?= $sortBy==='name'?'selected':'' ?>>Name A–Z</option>
        <option value="email"   <?= $sortBy==='email'?'selected':'' ?>>Email A–Z</option>
        <option value="appts"   <?= $sortBy==='appts'?'selected':'' ?>>Most Appointments</option>
    </select>
    <?php if ($activeFilters > 0): ?>
    <a href="patients.php" class="btn btn-secondary btn-sm" style="white-space:nowrap;">
        ✕ Clear <span class="badge badge-danger" style="margin-left:4px;"><?= $activeFilters ?></span>
    </a>
    <?php endif; ?>
</div>

<?php if ($activeFilters > 0 || $search): ?>
<div style="margin:-10px 0 14px;font-size:13px;color:var(--text-muted);">
    <?php $parts = [];
    if ($search)          $parts[] = 'Keyword: <strong>'.htmlspecialchars($search).'</strong>';
    if ($statusFilter!=='') $parts[] = 'Status: <strong>'.($statusFilter==='1'?'Active':'Inactive').'</strong>';
    if ($genderFilter)    $parts[] = 'Gender: <strong>'.ucfirst($genderFilter).'</strong>';
    if ($bloodFilter)     $parts[] = 'Blood Type: <strong>'.htmlspecialchars($bloodFilter).'</strong>';
    echo 'Filters: '.implode(' &middot; ', $parts).' &mdash; '.$total.' result'.($total!==1?'s':''); ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Patient</th><th>Contact</th><th>Blood Type</th><th>Insurance</th>
                    <th>Appointments</th><th>Unpaid Bills</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($patients as $pt): ?>
                <tr>
                    <td>
                        <div class="d-flex align-center gap-8">
                            <div class="avatar" style="width:34px;height:34px;font-size:12px;">
                                <?= strtoupper(substr($pt['first_name'],0,1).substr($pt['last_name'],0,1)) ?>
                            </div>
                            <div>
                                <div style="font-weight:600;"><?= htmlspecialchars($pt['first_name'].' '.$pt['last_name']) ?></div>
                                <div style="font-size:12px;color:var(--text-muted);">
                                    <?= $pt['gender']?ucfirst($pt['gender']):'—' ?>
                                    <?= $pt['date_of_birth']?' · '.(date('Y')-date('Y',strtotime($pt['date_of_birth']))).' yrs':'' ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div style="font-size:13px;"><?= htmlspecialchars($pt['email']) ?></div>
                        <div style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($pt['phone']??'—') ?></div>
                    </td>
                    <td>
                        <?php if ($pt['blood_type']): ?>
                            <span class="badge badge-info"><?= htmlspecialchars($pt['blood_type']) ?></span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td style="font-size:13px;">
                        <?= htmlspecialchars($pt['insurance_provider']??'—') ?>
                        <?php if ($pt['insurance_number']): ?>
                        <div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($pt['insurance_number']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="font-weight:600;color:var(--primary);"><?= $pt['total_appts'] ?></td>
                    <td>
                        <?php if ($pt['unpaid_bills'] > 0): ?>
                            <span class="badge badge-danger"><?= $pt['unpaid_bills'] ?> unpaid</span>
                        <?php else: ?>
                            <span class="badge badge-success">Clear</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= $pt['is_active']?'badge-success':'badge-secondary' ?>"><?= $pt['is_active']?'Active':'Inactive' ?></span></td>
                    <td>
                        <a href="/cliniccares/admin/patient-detail.php?id=<?= $pt['id'] ?>" class="btn btn-secondary btn-sm">👁️ View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($patients)): ?>
                <tr>
                    <td colspan="8" style="text-align:center;padding:48px;color:var(--text-muted);">
                        <div style="font-size:32px;margin-bottom:8px;">🔍</div>
                        <div style="font-weight:600;margin-bottom:4px;">No patients found</div>
                        <div style="font-size:13px;">Try adjusting your search or filters</div>
                        <?php if ($activeFilters > 0): ?>
                        <a href="patients.php" class="btn btn-secondary btn-sm" style="margin-top:12px;">Clear all filters</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-footer d-flex justify-between align-center">
        <span style="font-size:13px;color:var(--text-muted);">Showing <?= ($offset+1) ?>–<?= min($offset+ITEMS_PER_PAGE,$total) ?> of <?= $total ?></span>
        <div class="d-flex gap-8">
            <?php for ($p=1;$p<=$totalPages;$p++): ?>
                <a href="?page=<?=$p?>&q=<?=urlencode($search)?>&status=<?=$statusFilter?>&blood=<?=urlencode($bloodFilter)?>&gender=<?=$genderFilter?>&sort=<?=$sortBy?>"
                   class="btn btn-sm <?=$p===$page?'btn-primary':'btn-secondary'?>"><?=$p?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
var _debounce;
function debounceFilter() {
    clearTimeout(_debounce);
    _debounce = setTimeout(applyFilters, 400);
}
function applyFilters() {
    const params = new URLSearchParams();
    const q  = document.getElementById('pSearch').value.trim();
    const st = document.getElementById('fStatus').value;
    const g  = document.getElementById('fGender').value;
    const b  = document.getElementById('fBlood').value;
    const s  = document.getElementById('fSort').value;
    if (q)         params.set('q', q);
    if (st !== '') params.set('status', st);
    if (g)         params.set('gender', g);
    if (b)         params.set('blood', b);
    if (s !== 'created') params.set('sort', s);
    window.location.href = 'patients.php?' + params.toString();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
