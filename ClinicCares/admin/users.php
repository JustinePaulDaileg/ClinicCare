<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('admin');

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'toggle_active') {
        $id = (int)$_POST['id'];
        $current = db()->fetchOne("SELECT is_active FROM users WHERE id=?", [$id])['is_active'];
        db()->execute("UPDATE users SET is_active=? WHERE id=?", [!$current, $id]);
        echo json_encode(['success' => true, 'active' => !$current]);
        exit;
    }
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id === $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'error' => 'Cannot delete yourself']);
            exit;
        }
        db()->execute("DELETE FROM users WHERE id=?", [$id]);
        echo json_encode(['success' => true]);
        exit;
    }
    if ($action === 'save') {
        $id        = (int)($_POST['id'] ?? 0);
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName  = sanitize($_POST['last_name'] ?? '');
        $email     = strtolower(trim($_POST['email'] ?? ''));
        $phone     = sanitize($_POST['phone'] ?? '');
        $role      = in_array($_POST['role'] ?? '', ['admin','doctor','patient']) ? $_POST['role'] : 'patient';
        $active    = isset($_POST['is_active']) ? 1 : 0;
        $verified  = isset($_POST['email_verified']) ? 1 : 0;

        if ($id) {
            db()->execute(
                "UPDATE users SET first_name=?,last_name=?,email=?,phone=?,role=?,is_active=?,email_verified=? WHERE id=?",
                [$firstName, $lastName, $email, $phone, $role, $active, $verified, $id]
            );
            if (!empty($_POST['password'])) {
                db()->execute("UPDATE users SET password=? WHERE id=?", [hashPassword($_POST['password']), $id]);
            }
        } else {
            if (!$_POST['password']) { echo json_encode(['success'=>false,'error'=>'Password required']); exit; }
            $existing = db()->fetchOne("SELECT id FROM users WHERE email=?", [$email]);
            if ($existing) { echo json_encode(['success'=>false,'error'=>'Email already exists']); exit; }
            db()->insert(
                "INSERT INTO users (first_name,last_name,email,phone,password,role,is_active,email_verified) VALUES (?,?,?,?,?,?,?,?)",
                [$firstName,$lastName,$email,$phone,hashPassword($_POST['password']),$role,$active,$verified]
            );
        }
        echo json_encode(['success' => true]);
        exit;
    }
}

// Filters
$search = sanitize($_GET['q'] ?? '');
$roleFilter = sanitize($_GET['role'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * ITEMS_PER_PAGE;

$where = '1=1';
$params = [];
if ($search) { $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)"; $s="%$search%"; $params=[$s,$s,$s]; }
if ($roleFilter) { $where .= " AND u.role=?"; $params[] = $roleFilter; }

$total = db()->fetchOne("SELECT COUNT(*) as c FROM users u WHERE $where", $params)['c'];
$users = db()->fetchAll("SELECT * FROM users u WHERE $where ORDER BY u.created_at DESC LIMIT ? OFFSET ?", array_merge($params, [ITEMS_PER_PAGE, $offset]));
$totalPages = ceil($total / ITEMS_PER_PAGE);

$pageTitle = 'User Management';
$activeNav = 'users';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-between align-center">
  <div>
    <h1>User Management</h1>
    <p>Manage all system users — admin, doctors, and patients</p>
  </div>
  <button class="btn btn-primary" onclick="openUserModal(0)">➕ Add User</button>
</div>

<!-- Filters -->
<div class="search-bar">
  <div class="search-input-wrap">
    <span class="search-icon">🔍</span>
    <input type="text" id="searchInput" placeholder="Search by name or email..." value="<?= htmlspecialchars($search) ?>" onkeyup="applyFilters()">
  </div>
  <select class="form-select" style="width:160px;" id="roleSelect" onchange="applyFilters()">
    <option value="">All Roles</option>
    <option value="admin" <?= $roleFilter==='admin'?'selected':'' ?>>Admin</option>
    <option value="doctor" <?= $roleFilter==='doctor'?'selected':'' ?>>Doctor</option>
    <option value="patient" <?= $roleFilter==='patient'?'selected':'' ?>>Patient</option>
  </select>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">All Users (<?= $total ?>)</span>
  </div>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>User</th><th>Email</th><th>Role</th><th>Phone</th>
          <th>Status</th><th>Verified</th><th>Joined</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td>
            <div class="d-flex align-center gap-8">
              <div class="avatar" style="width:34px;height:34px;font-size:13px;">
                <?= strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1)) ?>
              </div>
              <div>
                <div style="font-weight:600;"><?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?></div>
                <div class="text-muted text-sm">#<?= $u['id'] ?></div>
              </div>
            </div>
          </td>
          <td><?= htmlspecialchars($u['email']) ?></td>
          <td>
            <span class="badge <?= match($u['role']){
              'admin'=>'badge-danger','doctor'=>'badge-info',default=>'badge-secondary'} ?>">
              <?= ucfirst($u['role']) ?>
            </span>
          </td>
          <td><?= htmlspecialchars($u['phone'] ?: '—') ?></td>
          <td>
            <span class="badge <?= $u['is_active']?'badge-success':'badge-secondary' ?>">
              <?= $u['is_active']?'Active':'Inactive' ?>
            </span>
          </td>
          <td><?= $u['email_verified']?'<span class="badge badge-success">✓ Yes</span>':'<span class="badge badge-warning">⚠ No</span>' ?></td>
          <td><?= formatDate($u['created_at']) ?></td>
          <td>
            <div class="d-flex gap-8">
              <button class="btn btn-secondary btn-sm" onclick='openUserModal(<?= htmlspecialchars(json_encode($u)) ?>)'>✏️</button>
              <?php if ($u['id'] !== $_SESSION['user_id']): ?>
              <button class="btn btn-danger btn-sm" onclick="deleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['first_name']) ?>')">🗑️</button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($users)): ?>
        <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted);">No users found</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($totalPages > 1): ?>
  <div class="card-footer d-flex justify-between align-center">
    <span style="font-size:13px;color:var(--text-muted);">Showing <?= ($offset+1) ?>–<?= min($offset+ITEMS_PER_PAGE,$total) ?> of <?= $total ?></span>
    <div class="d-flex gap-8">
      <?php for ($p=1; $p<=$totalPages; $p++): ?>
        <a href="?page=<?=$p?>&q=<?=urlencode($search)?>&role=<?=$roleFilter?>" class="btn btn-sm <?= $p===$page?'btn-primary':'btn-secondary' ?>"><?=$p?></a>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- User Modal -->
<div class="modal-overlay" id="userModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title" id="modalTitle">Add User</h3>
      <button class="modal-close" onclick="closeModal('userModal')">✕</button>
    </div>
    <div class="modal-body">
      <form id="userForm">
        <input type="hidden" id="userId" name="id" value="0">
        <input type="hidden" name="action" value="save">
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">First Name *</label>
            <input type="text" name="first_name" id="uFirstName" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Last Name *</label>
            <input type="text" name="last_name" id="uLastName" class="form-control" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email *</label>
          <input type="email" name="email" id="uEmail" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Phone</label>
          <input type="tel" name="phone" id="uPhone" class="form-control">
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Role</label>
            <select name="role" id="uRole" class="form-select">
              <option value="patient">Patient</option>
              <option value="doctor">Doctor</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Password <span id="pwNote" style="font-weight:400;color:var(--text-muted);">(leave blank to keep)</span></label>
            <input type="password" name="password" id="uPassword" class="form-control" placeholder="••••••••">
          </div>
        </div>
        <div style="display:flex;gap:16px;">
          <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
            <input type="checkbox" name="is_active" id="uActive"> Active
          </label>
          <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
            <input type="checkbox" name="email_verified" id="uVerified"> Email Verified
          </label>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('userModal')">Cancel</button>
      <button class="btn btn-primary" onclick="saveUser()">💾 Save User</button>
    </div>
  </div>
</div>

<script>
function applyFilters() {
  const q = document.getElementById('searchInput').value;
  const role = document.getElementById('roleSelect').value;
  window.location.href = `?q=${encodeURIComponent(q)}&role=${role}`;
}

function openUserModal(data) {
  const isNew = !data || typeof data !== 'object';
  document.getElementById('modalTitle').textContent = isNew ? 'Add New User' : 'Edit User';
  document.getElementById('pwNote').style.display = isNew ? 'none' : 'inline';
  document.getElementById('userId').value = isNew ? 0 : data.id;
  document.getElementById('uFirstName').value = isNew ? '' : data.first_name;
  document.getElementById('uLastName').value = isNew ? '' : data.last_name;
  document.getElementById('uEmail').value = isNew ? '' : data.email;
  document.getElementById('uPhone').value = isNew ? '' : (data.phone || '');
  document.getElementById('uRole').value = isNew ? 'patient' : data.role;
  document.getElementById('uPassword').value = '';
  document.getElementById('uActive').checked = isNew ? true : !!parseInt(data.is_active);
  document.getElementById('uVerified').checked = isNew ? false : !!parseInt(data.email_verified);
  openModal('userModal');
}

function saveUser() {
  const form = document.getElementById('userForm');
  const data = new FormData(form);
  if (document.getElementById('uActive').checked) data.set('is_active','on');
  if (document.getElementById('uVerified').checked) data.set('email_verified','on');
  fetch('', { method: 'POST', body: data })
    .then(r => r.json())
    .then(res => {
      if (res.success) { showToast('User saved!', 'success'); closeModal('userModal'); location.reload(); }
      else showToast(res.error || 'Error saving user', 'danger');
    });
}

function deleteUser(id, name) {
  confirmAction(`Delete ${name}? This will also remove all associated data.`, () => {
    const data = new FormData();
    data.set('action','delete'); data.set('id', id);
    fetch('', { method:'POST', body:data })
      .then(r => r.json())
      .then(res => {
        if (res.success) { showToast('User deleted','success'); location.reload(); }
        else showToast(res.error||'Error','danger');
      });
  });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>