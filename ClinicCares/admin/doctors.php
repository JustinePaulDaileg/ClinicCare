<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('admin');

// AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'save') {
        $id        = (int)($_POST['id'] ?? 0);
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName  = sanitize($_POST['last_name']  ?? '');
        $email     = strtolower(trim($_POST['email'] ?? ''));
        $phone     = sanitize($_POST['phone'] ?? '');
        $spec      = sanitize($_POST['specialization'] ?? '');
        $license   = sanitize($_POST['license_number'] ?? '');
        $dept      = sanitize($_POST['department'] ?? '');
        $fee       = (float)($_POST['consultation_fee'] ?? 500);
        $bio       = sanitize($_POST['bio'] ?? '');

        if ($id) {
            $doc = db()->fetchOne("SELECT user_id FROM doctors WHERE id=?", [$id]);
            if ($doc) {
                db()->execute("UPDATE users SET first_name=?,last_name=?,phone=? WHERE id=?",
                    [$firstName,$lastName,$phone,$doc['user_id']]);
                db()->execute("UPDATE doctors SET specialization=?,license_number=?,department=?,consultation_fee=?,bio=? WHERE id=?",
                    [$spec,$license,$dept,$fee,$bio,$id]);
                if (!empty($_POST['password'])) {
                    db()->execute("UPDATE users SET password=? WHERE id=?", [hashPassword($_POST['password']),$doc['user_id']]);
                }
            }
        } else {
            if (db()->fetchOne("SELECT id FROM users WHERE email=?", [$email])) {
                echo json_encode(['success'=>false,'error'=>'Email already exists']); exit;
            }
            $pw = $_POST['password'] ?? '';
            if (!$pw) { echo json_encode(['success'=>false,'error'=>'Password required']); exit; }
            $userId = db()->insert(
                "INSERT INTO users (email,password,role,first_name,last_name,phone,is_active,email_verified) VALUES (?,?,?,?,?,?,1,1)",
                [$email,hashPassword($pw),'doctor',$firstName,$lastName,$phone]
            );
            db()->insert("INSERT INTO doctors (user_id,specialization,license_number,department,consultation_fee,bio) VALUES (?,?,?,?,?,?)",
                [$userId,$spec,$license,$dept,$fee,$bio]);
        }
        echo json_encode(['success'=>true]);
        exit;
    }

    if ($action === 'delete') {
        $id  = (int)$_POST['id'];
        $doc = db()->fetchOne("SELECT user_id FROM doctors WHERE id=?", [$id]);
        if ($doc) db()->execute("DELETE FROM users WHERE id=?", [$doc['user_id']]);
        echo json_encode(['success'=>true]);
        exit;
    }
}

$search     = sanitize($_GET['q'] ?? '');
$deptFilter = sanitize($_GET['dept'] ?? '');
$specFilter = sanitize($_GET['spec'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');
$sortBy     = in_array($_GET['sort'] ?? '', ['name','fee_asc','fee_desc','appts']) ? $_GET['sort'] : 'name';
$page       = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($page - 1) * ITEMS_PER_PAGE;

$where = '1=1'; $params = [];
if ($search) {
    $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR d.specialization LIKE ? OR d.license_number LIKE ? OR u.email LIKE ?)";
    $s = "%$search%"; $params = [$s,$s,$s,$s,$s];
}
if ($deptFilter) { $where .= " AND d.department=?"; $params[] = $deptFilter; }
if ($specFilter) { $where .= " AND d.specialization LIKE ?"; $params[] = "%$specFilter%"; }
if ($statusFilter !== '') { $where .= " AND u.is_active=?"; $params[] = (int)$statusFilter; }

$orderMap = [
    'name'     => 'u.first_name ASC',
    'fee_asc'  => 'd.consultation_fee ASC',
    'fee_desc' => 'd.consultation_fee DESC',
    'appts'    => 'total_appts DESC',
];
$orderSQL = $orderMap[$sortBy];

$baseQ = "FROM doctors d JOIN users u ON d.user_id=u.id WHERE $where";
$total   = db()->fetchOne("SELECT COUNT(*) as c $baseQ", $params)['c'];
$doctors = db()->fetchAll(
    "SELECT d.*, u.first_name, u.last_name, u.email, u.phone, u.is_active,
            (SELECT COUNT(*) FROM appointments a WHERE a.doctor_id=d.id) as total_appts,
            (SELECT COUNT(*) FROM appointments a WHERE a.doctor_id=d.id AND a.appointment_date=CURDATE()) as today_appts
     $baseQ ORDER BY $orderSQL LIMIT ? OFFSET ?",
    array_merge($params, [ITEMS_PER_PAGE, $offset])
);
$totalPages = ceil($total / ITEMS_PER_PAGE);

// Distinct departments for filter dropdown
$departments = db()->fetchAll("SELECT DISTINCT department FROM doctors WHERE department IS NOT NULL AND department!='' ORDER BY department");

$activeFilters = (int)($search !== '') + (int)($deptFilter !== '') + (int)($specFilter !== '') + (int)($statusFilter !== '');

$pageTitle = 'Doctors';
$activeNav = 'doctors';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-between align-center">
    <div>
        <h1>Doctor Management</h1>
        <p>Manage clinic doctors and their profiles (<?= $total ?> total)</p>
    </div>
    <button class="btn btn-primary" onclick="openDoctorModal(0)">➕ Add Doctor</button>
</div>

<div class="search-bar">
    <div class="search-input-wrap">
        <span class="search-icon">🔍</span>
        <input type="text" id="dSearch" placeholder="Search name, specialization, license, email…"
               value="<?= htmlspecialchars($search) ?>" onkeyup="debounceDF()">
    </div>
    <?php if (!empty($departments)): ?>
    <select class="form-select" id="fDept" style="width:150px;" onchange="applyDF()">
        <option value="">All Departments</option>
        <?php foreach ($departments as $dep): ?>
            <option value="<?= htmlspecialchars($dep['department']) ?>" <?= $deptFilter===$dep['department']?'selected':'' ?>>
                <?= htmlspecialchars($dep['department']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <select class="form-select" id="fStatus" style="width:140px;" onchange="applyDF()">
        <option value="">All Status</option>
        <option value="1" <?= $statusFilter==='1'?'selected':'' ?>>Active</option>
        <option value="0" <?= $statusFilter==='0'?'selected':'' ?>>Inactive</option>
    </select>
    <select class="form-select" id="fSort" style="width:160px;" onchange="applyDF()">
        <option value="name"     <?= $sortBy==='name'?'selected':'' ?>>Name A–Z</option>
        <option value="fee_asc"  <?= $sortBy==='fee_asc'?'selected':'' ?>>Fee: Low to High</option>
        <option value="fee_desc" <?= $sortBy==='fee_desc'?'selected':'' ?>>Fee: High to Low</option>
        <option value="appts"    <?= $sortBy==='appts'?'selected':'' ?>>Most Appointments</option>
    </select>
    <?php if ($activeFilters > 0): ?>
    <a href="doctors.php" class="btn btn-secondary btn-sm" style="white-space:nowrap;">
        ✕ Clear <span class="badge badge-danger" style="margin-left:4px;"><?= $activeFilters ?></span>
    </a>
    <?php endif; ?>
</div>

<?php if ($activeFilters > 0): ?>
<div style="margin:-10px 0 14px;font-size:13px;color:var(--text-muted);">
    <?php $parts = [];
    if ($search)          $parts[] = 'Keyword: <strong>'.htmlspecialchars($search).'</strong>';
    if ($deptFilter)      $parts[] = 'Dept: <strong>'.htmlspecialchars($deptFilter).'</strong>';
    if ($specFilter)      $parts[] = 'Spec: <strong>'.htmlspecialchars($specFilter).'</strong>';
    if ($statusFilter!=='') $parts[] = 'Status: <strong>'.($statusFilter==='1'?'Active':'Inactive').'</strong>';
    echo 'Filters: '.implode(' &middot; ', $parts).' &mdash; '.$total.' result'.($total!==1?'s':''); ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Doctor</th><th>Specialization</th><th>License #</th>
                    <th>Dept</th><th>Fee</th><th>Today</th><th>Total Appts</th>
                    <th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($doctors as $doc): ?>
                <tr>
                    <td>
                        <div class="d-flex align-center gap-8">
                            <div class="avatar" style="width:36px;height:36px;background:linear-gradient(135deg,var(--primary),var(--secondary-light));">
                                <?= strtoupper(substr($doc['first_name'],0,1).substr($doc['last_name'],0,1)) ?>
                            </div>
                            <div>
                                <div style="font-weight:600;">Dr. <?= htmlspecialchars($doc['first_name'].' '.$doc['last_name']) ?></div>
                                <div style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($doc['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($doc['specialization']) ?></td>
                    <td style="font-family:monospace;font-size:13px;"><?= htmlspecialchars($doc['license_number']) ?></td>
                    <td><?= htmlspecialchars($doc['department']??'—') ?></td>
                    <td style="font-weight:600;color:var(--primary);"><?= formatCurrency($doc['consultation_fee']) ?></td>
                    <td>
                        <span class="badge <?= $doc['today_appts']>0?'badge-info':'badge-secondary' ?>"><?= $doc['today_appts'] ?> today</span>
                    </td>
                    <td><?= number_format($doc['total_appts']) ?></td>
                    <td><span class="badge <?= $doc['is_active']?'badge-success':'badge-secondary' ?>"><?= $doc['is_active']?'Active':'Inactive' ?></span></td>
                    <td>
                        <div class="d-flex gap-8">
                            <button class="btn btn-secondary btn-sm" onclick='openDoctorModal(<?= htmlspecialchars(json_encode($doc)) ?>)'>✏️</button>
                            <button class="btn btn-danger btn-sm" onclick="delDoc(<?=$doc['id']?>, 'Dr. <?= htmlspecialchars($doc['last_name']) ?>')">🗑️</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($doctors)): ?>
                <tr>
                    <td colspan="9" style="text-align:center;padding:48px;color:var(--text-muted);">
                        <div style="font-size:32px;margin-bottom:8px;">🔍</div>
                        <div style="font-weight:600;margin-bottom:4px;">No doctors found</div>
                        <div style="font-size:13px;">Try adjusting your search or filters</div>
                        <?php if ($activeFilters > 0): ?>
                        <a href="doctors.php" class="btn btn-secondary btn-sm" style="margin-top:12px;">Clear all filters</a>
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
                <a href="?page=<?=$p?>&q=<?=urlencode($search)?>&dept=<?=urlencode($deptFilter)?>&status=<?=$statusFilter?>&sort=<?=$sortBy?>"
                   class="btn btn-sm <?=$p===$page?'btn-primary':'btn-secondary'?>"><?=$p?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Doctor Modal -->
<div class="modal-overlay" id="doctorModal">
    <div class="modal" style="max-width:640px;">
        <div class="modal-header">
            <h3 class="modal-title" id="docModalTitle">Add Doctor</h3>
            <button class="modal-close" onclick="closeModal('doctorModal')">✕</button>
        </div>
        <div class="modal-body">
            <form id="docForm">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="docId" value="0">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" id="dFN" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" id="dLN" class="form-control" required>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" id="dEmail" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" id="dPhone" class="form-control">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Specialization *</label>
                        <input type="text" name="specialization" id="dSpec" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">License Number *</label>
                        <input type="text" name="license_number" id="dLicense" class="form-control" required>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <input type="text" name="department" id="dDept" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Consultation Fee (₱)</label>
                        <input type="number" name="consultation_fee" id="dFee" class="form-control" min="0" step="50" value="500">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Bio</label>
                    <textarea name="bio" id="dBio" class="form-control" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Password <span id="dPwNote" style="font-weight:400;color:var(--text-muted);">(leave blank to keep)</span></label>
                    <input type="password" name="password" id="dPw" class="form-control" placeholder="••••••••">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('doctorModal')">Cancel</button>
            <button class="btn btn-primary" onclick="saveDoc()">💾 Save Doctor</button>
        </div>
    </div>
</div>

<script>
var _dfTimer;
function debounceDF() {
    clearTimeout(_dfTimer);
    _dfTimer = setTimeout(applyDF, 400);
}
function applyDF() {
    const params = new URLSearchParams();
    const q  = document.getElementById('dSearch').value.trim();
    const dp = document.getElementById('fDept') ? document.getElementById('fDept').value : '';
    const st = document.getElementById('fStatus').value;
    const s  = document.getElementById('fSort').value;
    if (q)         params.set('q', q);
    if (dp)        params.set('dept', dp);
    if (st !== '') params.set('status', st);
    if (s !== 'name') params.set('sort', s);
    window.location.href = 'doctors.php?' + params.toString();
}

function openDoctorModal(data) {
    const isNew = !data || typeof data !== 'object';
    document.getElementById('docModalTitle').textContent = isNew ? 'Add Doctor' : 'Edit Doctor';
    document.getElementById('dPwNote').style.display = isNew ? 'none' : 'inline';
    document.getElementById('docId').value    = isNew ? 0 : data.id;
    document.getElementById('dFN').value      = isNew ? '' : data.first_name;
    document.getElementById('dLN').value      = isNew ? '' : data.last_name;
    document.getElementById('dEmail').value   = isNew ? '' : data.email;
    document.getElementById('dPhone').value   = isNew ? '' : (data.phone || '');
    document.getElementById('dSpec').value    = isNew ? '' : data.specialization;
    document.getElementById('dLicense').value = isNew ? '' : data.license_number;
    document.getElementById('dDept').value    = isNew ? '' : (data.department || '');
    document.getElementById('dFee').value     = isNew ? 500 : data.consultation_fee;
    document.getElementById('dBio').value     = isNew ? '' : (data.bio || '');
    document.getElementById('dPw').value      = '';
    document.getElementById('dEmail').disabled = !isNew;
    openModal('doctorModal');
}
function saveDoc() {
    const fd = new FormData(document.getElementById('docForm'));
    fetch('', {method:'POST', body:fd}).then(r=>r.json()).then(res => {
        if (res.success) { showToast('Doctor saved!', 'success'); closeModal('doctorModal'); location.reload(); }
        else showToast(res.error || 'Error', 'danger');
    });
}
function delDoc(id, name) {
    confirmAction(`Delete ${name}? All associated records will be removed.`, () => {
        const fd = new FormData(); fd.set('action','delete'); fd.set('id',id);
        fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(res=>{
            if(res.success){showToast('Doctor deleted','success');location.reload();}
        });
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
