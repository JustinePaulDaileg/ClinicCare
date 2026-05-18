<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('admin');

// AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'record_payment') {
        $id     = (int)$_POST['id'];
        $amount = (float)$_POST['amount'];
        $method = sanitize($_POST['payment_method'] ?? 'cash');
        $ref    = sanitize($_POST['reference'] ?? '');

        $bill = db()->fetchOne("SELECT * FROM billing WHERE id=?", [$id]);
        if (!$bill) { echo json_encode(['success'=>false,'error'=>'Invoice not found']); exit; }

        $newPaid    = $bill['amount_paid'] + $amount;
        $newBalance = max(0, $bill['total'] - $newPaid);
        $status     = $newBalance <= 0 ? 'paid' : 'partial';

        db()->execute(
            "UPDATE billing SET amount_paid=?,balance=?,status=?,payment_method=?,payment_reference=?,payment_date=NOW() WHERE id=?",
            [$newPaid, $newBalance, $status, $method, $ref, $id]
        );

        $patient = db()->fetchOne("SELECT u.id as user_id FROM billing b JOIN patients p ON b.patient_id=p.id JOIN users u ON p.user_id=u.id WHERE b.id=?", [$id]);
        if ($patient) {
            createNotification($patient['user_id'], 'Payment Recorded', "Payment of " . formatCurrency($amount) . " received. Status: $status", 'billing', '/patient/billing.php');
        }
        echo json_encode(['success'=>true]);
        exit;
    }

    if ($action === 'delete') {
        db()->execute("DELETE FROM billing WHERE id=?", [(int)$_POST['id']]);
        echo json_encode(['success'=>true]);
        exit;
    }

    if ($action === 'add_invoice') {
        $patientId = (int)$_POST['patient_id'];
        $apptId    = $_POST['appointment_id'] ? (int)$_POST['appointment_id'] : null;
        $doctorId  = $_POST['doctor_id'] ? (int)$_POST['doctor_id'] : null;
        $desc      = sanitize($_POST['description']);
        $amount    = (float)$_POST['amount'];
        $notes     = sanitize($_POST['notes'] ?? '');
        $due       = $_POST['due_date'] ?? date('Y-m-d', strtotime('+7 days'));

        $inv = generateInvoiceNumber();
        $billingId = db()->insert(
            "INSERT INTO billing (invoice_number,patient_id,appointment_id,doctor_id,subtotal,total,balance,status,due_date,notes) VALUES (?,?,?,?,?,?,?,?,?,?)",
            [$inv, $patientId, $apptId, $doctorId, $amount, $amount, $amount, 'pending', $due, $notes]
        );
        db()->insert(
            "INSERT INTO billing_items (billing_id,description,quantity,unit_price,total) VALUES (?,?,1,?,?)",
            [$billingId, $desc, $amount, $amount]
        );
        echo json_encode(['success'=>true, 'invoice'=>$inv]);
        exit;
    }
}

$search        = sanitize($_GET['q'] ?? '');
$statusFilter  = sanitize($_GET['status'] ?? '');
$doctorFilter  = (int)($_GET['doctor'] ?? 0);
$methodFilter  = sanitize($_GET['method'] ?? '');
$dateFrom      = sanitize($_GET['date_from'] ?? '');
$dateTo        = sanitize($_GET['date_to'] ?? '');
$overdueOnly   = isset($_GET['overdue']) && $_GET['overdue'] === '1';
$page          = max(1, (int)($_GET['page'] ?? 1));
$offset        = ($page - 1) * ITEMS_PER_PAGE;

$where = '1=1'; $params = [];
if ($search) {
    $where .= " AND (b.invoice_number LIKE ? OR up.first_name LIKE ? OR up.last_name LIKE ? OR ud.first_name LIKE ? OR ud.last_name LIKE ?)";
    $s = "%$search%"; $params = [$s,$s,$s,$s,$s];
}
if ($statusFilter) { $where .= " AND b.status=?";           $params[] = $statusFilter; }
if ($doctorFilter) { $where .= " AND b.doctor_id=?";        $params[] = $doctorFilter; }
if ($methodFilter) { $where .= " AND b.payment_method=?";   $params[] = $methodFilter; }
if ($dateFrom)     { $where .= " AND b.created_at>=?";      $params[] = $dateFrom.' 00:00:00'; }
if ($dateTo)       { $where .= " AND b.created_at<=?";      $params[] = $dateTo.' 23:59:59'; }
if ($overdueOnly)  { $where .= " AND b.due_date < CURDATE() AND b.status IN ('pending','partial')"; }

$baseQ = "FROM billing b
    JOIN patients p ON b.patient_id=p.id
    JOIN users up ON p.user_id=up.id
    LEFT JOIN doctors d ON b.doctor_id=d.id
    LEFT JOIN users ud ON d.user_id=ud.id
    WHERE $where";

$total = db()->fetchOne("SELECT COUNT(*) as c $baseQ", $params)['c'];
$bills = db()->fetchAll(
    "SELECT b.*, CONCAT(up.first_name,' ',up.last_name) as patient_name,
     CONCAT(ud.first_name,' ',ud.last_name) as doctor_name
     $baseQ ORDER BY b.created_at DESC LIMIT ? OFFSET ?",
    array_merge($params, [ITEMS_PER_PAGE, $offset])
);
$totalPages = ceil($total / ITEMS_PER_PAGE);

// Summary stats (filtered)
$filteredStats = db()->fetchOne(
    "SELECT SUM(b.total) as total_billed, SUM(b.amount_paid) as total_collected,
     SUM(b.balance) as total_outstanding,
     COUNT(CASE WHEN b.status='pending' THEN 1 END) as pending_count
     $baseQ", $params
);

// Global summary (unfiltered)
$summaryStats = db()->fetchOne("SELECT
    SUM(total) as total_billed, SUM(amount_paid) as total_collected,
    SUM(balance) as total_outstanding,
    COUNT(CASE WHEN status='pending' THEN 1 END) as pending_count
    FROM billing");

$patients    = db()->fetchAll("SELECT p.id, CONCAT(u.first_name,' ',u.last_name) as name FROM patients p JOIN users u ON p.user_id=u.id ORDER BY u.first_name");
$doctorsList = db()->fetchAll("SELECT d.id, CONCAT(u.first_name,' ',u.last_name) as name FROM doctors d JOIN users u ON d.user_id=u.id ORDER BY u.first_name");

$activeFilters = (int)($search!=='') + (int)($statusFilter!=='') + (int)($doctorFilter>0) + (int)($methodFilter!=='') + (int)($dateFrom!=='') + (int)($dateTo!=='') + (int)$overdueOnly;

$pageTitle = 'Billing & Payments';
$activeNav = 'billing';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-between align-center">
    <div>
        <h1>Billing & Payments</h1>
        <p>Manage invoices, payments, and financial records</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('addInvoiceModal')">➕ New Invoice</button>
</div>

<!-- Summary Cards -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
    <div class="stat-card blue">
        <div class="stat-icon blue">💰</div>
        <div class="stat-value"><?= formatCurrency($summaryStats['total_billed'] ?? 0) ?></div>
        <div class="stat-label">Total Billed</div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon green">✅</div>
        <div class="stat-value"><?= formatCurrency($summaryStats['total_collected'] ?? 0) ?></div>
        <div class="stat-label">Total Collected</div>
    </div>
    <div class="stat-card amber">
        <div class="stat-icon amber">⏳</div>
        <div class="stat-value"><?= formatCurrency($summaryStats['total_outstanding'] ?? 0) ?></div>
        <div class="stat-label">Outstanding</div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon red">📋</div>
        <div class="stat-value"><?= number_format($summaryStats['pending_count'] ?? 0) ?></div>
        <div class="stat-label">Pending Invoices</div>
    </div>
</div>

<!-- Filters -->
<div class="search-bar">
    <div class="search-input-wrap">
        <span class="search-icon">🔍</span>
        <input type="text" id="bSearch" placeholder="Search invoice #, patient, or doctor…"
               value="<?= htmlspecialchars($search) ?>" onkeyup="debounceBF()">
    </div>
    <select class="form-select" style="width:145px;" id="bStatus" onchange="applyBF()">
        <option value="">All Status</option>
        <?php foreach (['pending','partial','paid','cancelled','refunded'] as $s): ?>
            <option value="<?=$s?>" <?= $statusFilter===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-select" style="width:175px;" id="bDoctor" onchange="applyBF()">
        <option value="">All Doctors</option>
        <?php foreach ($doctorsList as $doc): ?>
            <option value="<?=$doc['id']?>" <?= $doctorFilter===$doc['id']?'selected':'' ?>>
                Dr. <?= htmlspecialchars($doc['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <select class="form-select" style="width:150px;" id="bMethod" onchange="applyBF()">
        <option value="">All Methods</option>
        <?php foreach (['cash','gcash','paymaya','paymongo','card','bank_transfer','insurance'] as $m): ?>
            <option value="<?=$m?>" <?= $methodFilter===$m?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$m)) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="date" class="form-control" style="width:148px;" id="bDateFrom"
           value="<?= htmlspecialchars($dateFrom) ?>" onchange="applyBF()" title="Invoice from date">
    <input type="date" class="form-control" style="width:148px;" id="bDateTo"
           value="<?= htmlspecialchars($dateTo) ?>" onchange="applyBF()" title="Invoice to date">
    <label style="display:flex;align-items:center;gap:6px;font-size:13px;white-space:nowrap;cursor:pointer;">
        <input type="checkbox" id="bOverdue" onchange="applyBF()" <?= $overdueOnly?'checked':'' ?>>
        ⚠️ Overdue only
    </label>
    <?php if ($activeFilters > 0): ?>
    <a href="billing.php" class="btn btn-secondary btn-sm" style="white-space:nowrap;">
        ✕ Clear <span class="badge badge-danger" style="margin-left:4px;"><?= $activeFilters ?></span>
    </a>
    <?php endif; ?>
</div>

<?php if ($activeFilters > 0): ?>
<div style="margin:-10px 0 14px;font-size:13px;color:var(--text-muted);">
    <?php $parts = [];
    if ($search)        $parts[] = 'Keyword: <strong>'.htmlspecialchars($search).'</strong>';
    if ($statusFilter)  $parts[] = 'Status: <strong>'.ucfirst($statusFilter).'</strong>';
    if ($doctorFilter) {
        $dn = db()->fetchOne("SELECT CONCAT(u.first_name,' ',u.last_name) as name FROM doctors d JOIN users u ON d.user_id=u.id WHERE d.id=?",[$doctorFilter]);
        if ($dn) $parts[] = 'Doctor: <strong>Dr. '.htmlspecialchars($dn['name']).'</strong>';
    }
    if ($methodFilter)  $parts[] = 'Method: <strong>'.ucfirst(str_replace('_',' ',$methodFilter)).'</strong>';
    if ($dateFrom)      $parts[] = 'From: <strong>'.htmlspecialchars($dateFrom).'</strong>';
    if ($dateTo)        $parts[] = 'To: <strong>'.htmlspecialchars($dateTo).'</strong>';
    if ($overdueOnly)   $parts[] = '<strong style="color:var(--danger);">Overdue only</strong>';
    echo 'Filters: '.implode(' &middot; ', $parts).' &mdash; '.$total.' result'.($total!==1?'s':'');
    if ($activeFilters > 0): ?>
    &nbsp;|&nbsp;
    Filtered totals:
    Billed <strong><?= formatCurrency($filteredStats['total_billed']??0) ?></strong> &middot;
    Collected <strong style="color:var(--success);"><?= formatCurrency($filteredStats['total_collected']??0) ?></strong> &middot;
    Outstanding <strong style="color:var(--danger);"><?= formatCurrency($filteredStats['total_outstanding']??0) ?></strong>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title">Invoices (<?= $total ?>)</span>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Invoice #</th><th>Patient</th><th>Doctor</th><th>Total</th>
                    <th>Paid</th><th>Balance</th><th>Method</th><th>Status</th><th>Due Date</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bills as $b): ?>
                <?php $isOverdue = $b['due_date'] && strtotime($b['due_date']) < time() && in_array($b['status'],['pending','partial']); ?>
                <tr <?= $isOverdue ? 'style="background:rgba(var(--danger-rgb,220,53,69),0.04);"' : '' ?>>
                    <td>
                        <span style="font-family:monospace;font-weight:600;color:var(--primary);"><?= htmlspecialchars($b['invoice_number']) ?></span>
                        <?php if ($isOverdue): ?>
                        <div style="font-size:11px;color:var(--danger);font-weight:600;">⚠️ Overdue</div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($b['patient_name']) ?></td>
                    <td style="font-size:13px;">
                        <?= $b['doctor_name'] ? 'Dr. '.htmlspecialchars($b['doctor_name']) : '—' ?>
                    </td>
                    <td style="font-weight:600;"><?= formatCurrency($b['total']) ?></td>
                    <td style="color:var(--success);font-weight:600;"><?= formatCurrency($b['amount_paid']) ?></td>
                    <td style="color:<?= $b['balance']>0?'var(--danger)':'var(--success)' ?>;font-weight:600;"><?= formatCurrency($b['balance']) ?></td>
                    <td style="font-size:12px;">
                        <?php if ($b['payment_method']): ?>
                            <span class="badge badge-secondary"><?= ucfirst(str_replace('_',' ',$b['payment_method'])) ?></span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><span class="badge <?= getStatusBadge($b['status']) ?>"><?= ucfirst($b['status']) ?></span></td>
                    <td>
                        <?php if ($b['due_date']): ?>
                            <span style="<?= $isOverdue?'color:var(--danger);font-weight:600;':'' ?>">
                                <?= formatDate($b['due_date']) ?>
                            </span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex gap-8">
                            <a href="view-invoice.php?id=<?= $b['id'] ?>" class="btn btn-secondary btn-sm" target="_blank">👁️</a>
                            <?php if (in_array($b['status'],['pending','partial'])): ?>
                            <button class="btn btn-success btn-sm" onclick='openPayModal(<?= htmlspecialchars(json_encode($b)) ?>)'>💳 Pay</button>
                            <?php endif; ?>
                            <button class="btn btn-danger btn-sm" onclick="delBill(<?=$b['id']?>)">🗑️</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($bills)): ?>
                <tr>
                    <td colspan="10" style="text-align:center;padding:48px;color:var(--text-muted);">
                        <div style="font-size:32px;margin-bottom:8px;">🧾</div>
                        <div style="font-weight:600;margin-bottom:4px;">No invoices found</div>
                        <div style="font-size:13px;">Try adjusting your search or filters</div>
                        <?php if ($activeFilters > 0): ?>
                        <a href="billing.php" class="btn btn-secondary btn-sm" style="margin-top:12px;">Clear all filters</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-footer d-flex justify-between align-center">
        <span style="font-size:13px;color:var(--text-muted);">Page <?=$page?> of <?=$totalPages?> &mdash; <?=$total?> invoices</span>
        <div class="d-flex gap-8">
            <?php for ($p=1;$p<=$totalPages;$p++): ?>
                <a href="?page=<?=$p?>&q=<?=urlencode($search)?>&status=<?=$statusFilter?>&doctor=<?=$doctorFilter?>&method=<?=$methodFilter?>&date_from=<?=$dateFrom?>&date_to=<?=$dateTo?><?=$overdueOnly?'&overdue=1':''?>"
                   class="btn btn-sm <?=$p===$page?'btn-primary':'btn-secondary' ?>"><?=$p?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Payment Modal -->
<div class="modal-overlay" id="payModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Record Payment</h3>
            <button class="modal-close" onclick="closeModal('payModal')">✕</button>
        </div>
        <div class="modal-body">
            <div id="payInvoiceInfo" style="background:var(--surface-2);padding:14px;border-radius:8px;margin-bottom:16px;font-size:14px;"></div>
            <form id="payForm">
                <input type="hidden" name="action" value="record_payment">
                <input type="hidden" name="id" id="payId">
                <div class="form-group">
                    <label class="form-label">Payment Amount (₱) *</label>
                    <input type="number" name="amount" id="payAmount" class="form-control" min="1" step="0.01" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" class="form-select">
                        <option value="cash">Cash</option>
                        <option value="gcash">GCash</option>
                        <option value="paymaya">PayMaya</option>
                        <option value="paymongo">PayMongo (Online)</option>
                        <option value="card">Credit/Debit Card</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="insurance">Insurance</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Reference Number</label>
                    <input type="text" name="reference" class="form-control" placeholder="Transaction ref (optional)">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('payModal')">Cancel</button>
            <button class="btn btn-success" onclick="savePayment()">✅ Record Payment</button>
        </div>
    </div>
</div>

<!-- Add Invoice Modal -->
<div class="modal-overlay" id="addInvoiceModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">New Invoice</h3>
            <button class="modal-close" onclick="closeModal('addInvoiceModal')">✕</button>
        </div>
        <div class="modal-body">
            <form id="invoiceForm">
                <input type="hidden" name="action" value="add_invoice">
                <div class="form-group">
                    <label class="form-label">Patient *</label>
                    <select name="patient_id" class="form-select" required>
                        <option value="">Select patient</option>
                        <?php foreach ($patients as $pt): ?>
                            <option value="<?=$pt['id']?>"><?= htmlspecialchars($pt['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Description *</label>
                    <input type="text" name="description" class="form-control" placeholder="e.g. Consultation Fee" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Amount (₱) *</label>
                    <input type="number" name="amount" class="form-control" min="1" step="0.01" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Due Date</label>
                    <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
                <input type="hidden" name="appointment_id" value="">
                <input type="hidden" name="doctor_id" value="">
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('addInvoiceModal')">Cancel</button>
            <button class="btn btn-primary" onclick="saveInvoice()">💾 Create Invoice</button>
        </div>
    </div>
</div>

<script>
var _bfTimer;
function debounceBF() {
    clearTimeout(_bfTimer);
    _bfTimer = setTimeout(applyBF, 400);
}
function applyBF() {
    const params = new URLSearchParams();
    const q  = document.getElementById('bSearch').value.trim();
    const st = document.getElementById('bStatus').value;
    const dr = document.getElementById('bDoctor').value;
    const mt = document.getElementById('bMethod').value;
    const df = document.getElementById('bDateFrom').value;
    const dt = document.getElementById('bDateTo').value;
    const ov = document.getElementById('bOverdue').checked;
    if (q)  params.set('q', q);
    if (st) params.set('status', st);
    if (dr) params.set('doctor', dr);
    if (mt) params.set('method', mt);
    if (df) params.set('date_from', df);
    if (dt) params.set('date_to', dt);
    if (ov) params.set('overdue', '1');
    window.location.href = 'billing.php?' + params.toString();
}
function openPayModal(b) {
    document.getElementById('payId').value = b.id;
    document.getElementById('payAmount').value = b.balance;
    document.getElementById('payInvoiceInfo').innerHTML = `
        <strong>${b.invoice_number}</strong> &mdash; ${b.patient_name || ''}<br>
        Total: <strong>&#8369;${parseFloat(b.total).toFixed(2)}</strong> &nbsp;|&nbsp;
        Paid: <strong style="color:var(--success)">&#8369;${parseFloat(b.amount_paid).toFixed(2)}</strong> &nbsp;|&nbsp;
        Balance: <strong style="color:var(--danger)">&#8369;${parseFloat(b.balance).toFixed(2)}</strong>
    `;
    openModal('payModal');
}
function savePayment() {
    fetch('', {method:'POST', body: new FormData(document.getElementById('payForm'))})
        .then(r=>r.json()).then(res=>{
            if(res.success){showToast('Payment recorded!','success');closeModal('payModal');location.reload();}
            else showToast(res.error||'Error','danger');
        });
}
function saveInvoice() {
    fetch('', {method:'POST', body: new FormData(document.getElementById('invoiceForm'))})
        .then(r=>r.json()).then(res=>{
            if(res.success){showToast('Invoice created: '+res.invoice,'success');closeModal('addInvoiceModal');location.reload();}
            else showToast(res.error||'Error','danger');
        });
}
function delBill(id) {
    confirmAction('Delete this invoice?', () => {
        const d = new FormData(); d.set('action','delete'); d.set('id',id);
        fetch('',{method:'POST',body:d}).then(r=>r.json()).then(res=>{
            if(res.success){showToast('Deleted','success');location.reload();}
        });
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
