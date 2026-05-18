<?php
require_once __DIR__ . '/includes/session.php';
requireLogin();

$userId = $_SESSION['user_id'];

// Mark all read when page is visited
db()->execute("UPDATE notifications SET is_read=1 WHERE user_id=?", [$userId]);

// Filters
$typeFilter = sanitize($_GET['type'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($page - 1) * 20;

$where  = "user_id=?"; $params = [$userId];
if ($typeFilter) { $where .= " AND type=?"; $params[] = $typeFilter; }

$total  = db()->fetchOne("SELECT COUNT(*) as c FROM notifications WHERE $where", $params)['c'];
$notifs = db()->fetchAll(
    "SELECT * FROM notifications WHERE $where ORDER BY created_at DESC LIMIT 20 OFFSET ?",
    array_merge($params, [$offset])
);
$totalPages = ceil($total / 20);

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    header('Content-Type: application/json');
    $delId = (int)$_POST['delete_id'];
    if ($delId === -1) {
        // Delete all
        db()->execute("DELETE FROM notifications WHERE user_id=?", [$userId]);
    } else {
        db()->execute("DELETE FROM notifications WHERE id=? AND user_id=?", [$delId, $userId]);
    }
    echo json_encode(['success' => true]);
    exit;
}

$typeIcons = [
    'appointment'  => '📅',
    'billing'      => '💳',
    'prescription' => '💊',
    'reminder'     => '⏰',
    'system'       => '🔔',
];

$pageTitle = 'Notifications';
$activeNav = '';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex justify-between align-center">
    <div>
        <h1>Notifications</h1>
        <p><?= $total ?> total notification<?= $total != 1 ? 's' : '' ?></p>
    </div>
    <?php if ($total > 0): ?>
    <button class="btn btn-danger btn-sm" onclick="clearAll()">🗑️ Clear All</button>
    <?php endif; ?>
</div>

<!-- Type filter -->
<div class="search-bar" style="margin-bottom:20px;">
    <a href="?" class="btn <?= !$typeFilter?'btn-primary':'btn-secondary' ?> btn-sm">All</a>
    <?php foreach (array_keys($typeIcons) as $t): ?>
        <a href="?type=<?=$t?>" class="btn <?= $typeFilter===$t?'btn-primary':'btn-secondary' ?> btn-sm">
            <?= $typeIcons[$t] ?> <?= ucfirst($t) ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if (empty($notifs)): ?>
<div class="card">
    <div class="card-body" style="text-align:center;padding:60px;">
        <div style="font-size:56px;margin-bottom:16px;">🔔</div>
        <h3 style="font-family:var(--font-display);margin-bottom:8px;">No notifications</h3>
        <p style="color:var(--text-muted);">You're all caught up!</p>
    </div>
</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:8px;">
    <?php foreach ($notifs as $n): ?>
    <div id="notif-<?= $n['id'] ?>" style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px 16px;display:flex;align-items:flex-start;gap:12px;box-shadow:var(--shadow-sm);transition:all 0.2s;">
        <!-- Icon -->
        <div style="font-size:24px;flex-shrink:0;margin-top:2px;">
            <?= $typeIcons[$n['type']] ?? '🔔' ?>
        </div>

        <!-- Content -->
        <div style="flex:1;min-width:0;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px;flex-wrap:wrap;">
                <span style="font-weight:600;font-size:14px;"><?= htmlspecialchars($n['title']) ?></span>
                <span class="badge badge-<?= match($n['type']){
                    'appointment'=>'info','billing'=>'warning','prescription'=>'success',
                    'reminder'=>'secondary', default=>'secondary'
                } ?>" style="font-size:10px;"><?= ucfirst($n['type']) ?></span>
            </div>
            <div style="font-size:13px;color:var(--text-secondary);margin-bottom:4px;"><?= htmlspecialchars($n['message']) ?></div>
            <div style="font-size:11px;color:var(--text-muted);"><?= formatDateTime($n['created_at']) ?></div>
        </div>

        <!-- Actions -->
        <div style="display:flex;gap:8px;flex-shrink:0;align-items:flex-start;">
            <?php if ($n['link']): ?>
            <a href="<?= htmlspecialchars($n['link']) ?>" class="btn btn-primary btn-sm">View →</a>
            <?php endif; ?>
            <button class="btn btn-secondary btn-sm" onclick="deleteNotif(<?= $n['id'] ?>)" title="Delete">✕</button>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($totalPages > 1): ?>
<div style="display:flex;justify-content:center;gap:8px;margin-top:20px;">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <a href="?type=<?=$typeFilter?>&page=<?=$p?>" class="btn btn-sm <?= $p===$page?'btn-primary':'btn-secondary' ?>"><?= $p ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<script>
function deleteNotif(id) {
    const fd = new FormData(); fd.set('delete_id', id);
    fetch('', {method:'POST', body:fd}).then(r=>r.json()).then(res => {
        if (res.success) {
            const el = document.getElementById('notif-'+id);
            if (el) { el.style.opacity='0'; el.style.height=el.offsetHeight+'px'; setTimeout(()=>{el.style.height='0';el.style.padding='0';el.style.margin='0';el.style.overflow='hidden';setTimeout(()=>el.remove(),200);},200); }
        }
    });
}

function clearAll() {
    confirmAction('Clear all notifications?', () => {
        const fd = new FormData(); fd.set('delete_id', -1);
        fetch('', {method:'POST', body:fd}).then(r=>r.json()).then(res => {
            if (res.success) { showToast('All cleared', 'success'); location.reload(); }
        });
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>