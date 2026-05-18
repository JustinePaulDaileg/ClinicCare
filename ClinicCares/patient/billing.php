<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('patient');

$patient   = db()->fetchOne("SELECT p.* FROM patients p WHERE p.user_id=?", [$_SESSION['user_id']]);
$patientId = $patient['id'];

$search       = sanitize($_GET['q'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');
$methodFilter = sanitize($_GET['method'] ?? '');
$doctorFilter = (int)($_GET['doctor'] ?? 0);
$dateFrom     = sanitize($_GET['date_from'] ?? '');
$dateTo       = sanitize($_GET['date_to'] ?? '');
$overdueOnly  = isset($_GET['overdue']) && $_GET['overdue'] === '1';
$page         = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($page - 1) * ITEMS_PER_PAGE;

$where  = "b.patient_id=?";
$params = [$patientId];

if ($search) {
    $where .= " AND (b.invoice_number LIKE ? OR ud.first_name LIKE ? OR ud.last_name LIKE ?)";
    $s = "%$search%"; $params[] = $s; $params[] = $s; $params[] = $s;
}
if ($statusFilter)  { $where .= " AND b.status=?";            $params[] = $statusFilter; }
if ($methodFilter)  { $where .= " AND b.payment_method=?";    $params[] = $methodFilter; }
if ($doctorFilter)  { $where .= " AND b.doctor_id=?";         $params[] = $doctorFilter; }
if ($dateFrom)      { $where .= " AND b.created_at>=?";       $params[] = $dateFrom.' 00:00:00'; }
if ($dateTo)        { $where .= " AND b.created_at<=?";       $params[] = $dateTo.' 23:59:59'; }
if ($overdueOnly)   { $where .= " AND b.due_date < CURDATE() AND b.status IN ('pending','partial')"; }

$baseQ = "FROM billing b
    LEFT JOIN doctors d ON b.doctor_id=d.id
    LEFT JOIN users ud ON d.user_id=ud.id
    WHERE $where";

$total = db()->fetchOne("SELECT COUNT(*) as c $baseQ", $params)['c'];
$bills = db()->fetchAll(
    "SELECT b.*, CONCAT(ud.first_name,' ',ud.last_name) as doctor_name
     $baseQ ORDER BY b.created_at DESC LIMIT ? OFFSET ?",
    array_merge($params, [ITEMS_PER_PAGE, $offset])
);
$totalPages = ceil($total / ITEMS_PER_PAGE);

// Global totals (always shown unfiltered)
$totals = db()->fetchOne(
    "SELECT COALESCE(SUM(total),0) as billed, COALESCE(SUM(amount_paid),0) as paid, COALESCE(SUM(balance),0) as owed
     FROM billing WHERE patient_id=?",
    [$patientId]
);

// Filtered totals
$filteredTotals = db()->fetchOne(
    "SELECT COALESCE(SUM(b.total),0) as billed, COALESCE(SUM(b.amount_paid),0) as paid, COALESCE(SUM(b.balance),0) as owed
     $baseQ", $params
);

// Doctors this patient has billed with
$myDoctors = db()->fetchAll(
    "SELECT DISTINCT d.id, CONCAT(u.first_name,' ',u.last_name) as name
     FROM billing b JOIN doctors d ON b.doctor_id=d.id JOIN users u ON d.user_id=u.id
     WHERE b.patient_id=? ORDER BY u.first_name",
    [$patientId]
);

$activeFilters = (int)($search!=='') + (int)($statusFilter!=='') + (int)($methodFilter!=='') + (int)($doctorFilter>0) + (int)($dateFrom!=='') + (int)($dateTo!=='') + (int)$overdueOnly;

$pageTitle = 'My Billing';
$activeNav = 'billing';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Billing & Payments</h1>
    <p>View your invoices and payment history</p>
</div>

<!-- Summary Cards -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px;">
    <div class="stat-card blue">
        <div class="stat-icon blue">💰</div>
        <div class="stat-value"><?= formatCurrency($totals['billed']) ?></div>
        <div class="stat-label">Total Billed</div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon green">✅</div>
        <div class="stat-value"><?= formatCurrency($totals['paid']) ?></div>
        <div class="stat-label">Total Paid</div>
    </div>
    <div class="stat-card <?= $totals['owed']>0?'amber':'green' ?>">
        <div class="stat-icon <?= $totals['owed']>0?'amber':'green' ?>">⏳</div>
        <div class="stat-value"><?= formatCurrency($totals['owed']) ?></div>
        <div class="stat-label">Outstanding Balance</div>
    </div>
</div>

<!-- Search & Filter Bar -->
<div class="search-bar">
    <div class="search-input-wrap">
        <span class="search-icon">🔍</span>
        <input type="text" id="bSearch" placeholder="Search invoice # or doctor name…"
               value="<?= htmlspecialchars($search) ?>" onkeyup="debounceBF()">
    </div>
    <select class="form-select" style="width:150px;" id="bStatus" onchange="applyBF()">
        <option value="">All Invoices</option>
        <?php foreach (['pending','partial','paid','cancelled'] as $s): ?>
            <option value="<?=$s?>" <?= $statusFilter===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
    </select>
    <?php if (!empty($myDoctors)): ?>
    <select class="form-select" style="width:175px;" id="bDoctor" onchange="applyBF()">
        <option value="">All Doctors</option>
        <?php foreach ($myDoctors as $doc): ?>
            <option value="<?=$doc['id']?>" <?= $doctorFilter===$doc['id']?'selected':'' ?>>
                Dr. <?= htmlspecialchars($doc['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <select class="form-select" style="width:155px;" id="bMethod" onchange="applyBF()">
        <option value="">All Methods</option>
        <?php foreach (['cash','gcash','paymaya','paymongo','card','bank_transfer','insurance'] as $m): ?>
            <option value="<?=$m?>" <?= $methodFilter===$m?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$m)) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="date" class="form-control" style="width:148px;" id="bDateFrom"
           value="<?= htmlspecialchars($dateFrom) ?>" onchange="applyBF()" title="From date">
    <input type="date" class="form-control" style="width:148px;" id="bDateTo"
           value="<?= htmlspecialchars($dateTo) ?>" onchange="applyBF()" title="To date">
    <label style="display:flex;align-items:center;gap:6px;font-size:13px;white-space:nowrap;cursor:pointer;">
        <input type="checkbox" id="bOverdue" onchange="applyBF()" <?= $overdueOnly?'checked':'' ?>>
        ⚠️ Overdue
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
        $dn = array_filter($myDoctors, fn($d) => $d['id'] == $doctorFilter);
        if ($dn) $parts[] = 'Doctor: <strong>Dr. '.htmlspecialchars(reset($dn)['name']).'</strong>';
    }
    if ($methodFilter)  $parts[] = 'Method: <strong>'.ucfirst(str_replace('_',' ',$methodFilter)).'</strong>';
    if ($dateFrom)      $parts[] = 'From: <strong>'.htmlspecialchars($dateFrom).'</strong>';
    if ($dateTo)        $parts[] = 'To: <strong>'.htmlspecialchars($dateTo).'</strong>';
    if ($overdueOnly)   $parts[] = '<strong style="color:var(--danger);">Overdue only</strong>';
    echo implode(' &middot; ', $parts).' &mdash; '.$total.' result'.($total!==1?'s':''); ?>
    &nbsp;|&nbsp;
    Filtered: Billed <strong><?= formatCurrency($filteredTotals['billed']) ?></strong> &middot;
    Paid <strong style="color:var(--success);"><?= formatCurrency($filteredTotals['paid']) ?></strong> &middot;
    Owed <strong style="color:var(--danger);"><?= formatCurrency($filteredTotals['owed']) ?></strong>
</div>
<?php endif; ?>

<?php if (empty($bills)): ?>
<div class="card">
    <div class="card-body" style="text-align:center;padding:60px;">
        <div style="font-size:56px;margin-bottom:16px;">💳</div>
        <h3 style="font-family:var(--font-display);margin-bottom:8px;">No invoices found</h3>
        <p style="color:var(--text-muted);">
            <?= $activeFilters > 0 ? 'Try adjusting your search or filters.' : 'Your billing history will appear here after appointments.' ?>
        </p>
        <?php if ($activeFilters > 0): ?>
        <a href="billing.php" class="btn btn-secondary" style="margin-top:12px;">Clear all filters</a>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:12px;">
    <?php foreach ($bills as $b): ?>
    <?php $isOverdue = $b['due_date'] && strtotime($b['due_date']) < time() && in_array($b['status'],['pending','partial']); ?>
    <div class="card" <?= $isOverdue ? 'style="border-left:3px solid var(--danger);"' : '' ?>>
        <div style="padding:16px 20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
            <div style="flex:1;min-width:200px;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;flex-wrap:wrap;">
                    <span style="font-family:monospace;font-weight:700;font-size:15px;color:var(--primary);"><?= htmlspecialchars($b['invoice_number']) ?></span>
                    <span class="badge <?= getStatusBadge($b['status']) ?>"><?= ucfirst($b['status']) ?></span>
                    <?php if ($isOverdue): ?><span class="badge badge-danger">⚠️ Overdue</span><?php endif; ?>
                </div>
                <div style="font-size:13px;color:var(--text-secondary);">
                    <?= $b['doctor_name'] ? 'Dr. '.htmlspecialchars($b['doctor_name']).' · ' : '' ?>
                    Issued: <?= formatDate($b['created_at']) ?>
                    <?php if ($b['due_date']): ?>
                        · Due: <span style="<?= $isOverdue?'color:var(--danger);font-weight:600;':'' ?>"><?= formatDate($b['due_date']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($b['payment_method'] && $b['payment_date']): ?>
                <div style="font-size:12px;color:var(--success);margin-top:2px;">
                    ✅ Paid via <?= ucfirst(str_replace('_',' ',$b['payment_method'])) ?> on <?= formatDate($b['payment_date']) ?>
                    <?php if ($b['payment_reference']): ?>
                        · Ref: <code style="font-size:11px;"><?= htmlspecialchars($b['payment_reference']) ?></code>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <div style="text-align:right;flex-shrink:0;">
                <div style="font-size:20px;font-weight:800;color:var(--text-primary);"><?= formatCurrency($b['total']) ?></div>
                <?php if ($b['balance'] > 0): ?>
                <div style="font-size:13px;color:var(--danger);font-weight:600;">Balance: <?= formatCurrency($b['balance']) ?></div>
                <?php elseif ($b['status']==='paid'): ?>
                <div style="font-size:13px;color:var(--success);font-weight:600;">Fully paid ✓</div>
                <?php endif; ?>
            </div>

            <div style="display:flex;gap:8px;flex-shrink:0;">
                <a href="/cliniccares/patient/view-invoice.php?id=<?=$b['id']?>" class="btn btn-secondary btn-sm" target="_blank">🧾 View Invoice</a>
                <?php if (in_array($b['status'], ['pending','partial'])): ?>
                <button class="btn btn-primary btn-sm" onclick="openPaymongoModal(<?=$b['id']?>, '<?=htmlspecialchars($b['invoice_number'])?>', <?=$b['balance']?>)">
                    💳 Pay Online
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($b['status'] === 'pending' || $b['status'] === 'partial'): ?>
        <div style="padding:10px 20px;background:var(--warning-light);border-top:1px solid #fde68a;font-size:13px;color:var(--warning);display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
            <span>⚠️ Payment of <?= formatCurrency($b['balance']) ?> is due.</span>
            <button class="btn btn-primary btn-sm" onclick="openPaymongoModal(<?=$b['id']?>, '<?=htmlspecialchars($b['invoice_number'])?>', <?=$b['balance']?>)">
                💳 Pay Now via PayMongo
            </button>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($totalPages > 1): ?>
<div style="display:flex;justify-content:center;gap:8px;margin-top:20px;">
    <?php
    $qStr = http_build_query(array_filter([
        'q'         => $search,
        'status'    => $statusFilter,
        'method'    => $methodFilter,
        'doctor'    => $doctorFilter ?: '',
        'date_from' => $dateFrom,
        'date_to'   => $dateTo,
        'overdue'   => $overdueOnly ? '1' : '',
    ]));
    for ($p=1;$p<=$totalPages;$p++): ?>
        <a href="?<?=$qStr?>&page=<?=$p?>" class="btn btn-sm <?=$p===$page?'btn-primary':'btn-secondary'?>"><?=$p?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- ── PayMongo Payment Modal ──────────────────────────────────────────── -->
<div id="paymongoModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="max-width:440px;width:94%;background:var(--surface);border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.3);">
        <div style="padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
            <h3 style="margin:0;font-size:18px;">💳 Pay Online via PayMongo</h3>
            <button onclick="closePaymongoModal()" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--text-muted);">✕</button>
        </div>
        <div style="padding:24px;">
            <div id="pmInvoiceInfo" style="background:var(--surface-2);border-radius:10px;padding:14px 16px;margin-bottom:18px;font-size:14px;line-height:1.7;"></div>
            <div style="margin-bottom:18px;">
                <div style="font-size:11px;color:var(--text-muted);margin-bottom:8px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;">Accepted Payment Methods</div>
                <div style="display:flex;flex-wrap:wrap;gap:8px;">
                    <span style="background:#00b4d8;color:#fff;border-radius:6px;padding:4px 10px;font-size:12px;font-weight:600;">GCash</span>
                    <span style="background:#7B2D8B;color:#fff;border-radius:6px;padding:4px 10px;font-size:12px;font-weight:600;">Maya</span>
                    <span style="background:#1a56db;color:#fff;border-radius:6px;padding:4px 10px;font-size:12px;font-weight:600;">Credit/Debit Card</span>
                    <span style="background:#22c55e;color:#fff;border-radius:6px;padding:4px 10px;font-size:12px;font-weight:600;">BancNet</span>
                </div>
            </div>
            <div style="background:#fef3c7;border:1px solid #fde68a;border-radius:8px;padding:10px 14px;font-size:12px;color:#92400e;margin-bottom:18px;">
                🧪 <strong>Test / Demo Mode</strong> — No real money charged. Use test card <code>4343434343434345</code>, any future date, any CVV.
            </div>
            <div id="pmError" style="display:none;background:#fee2e2;color:#991b1b;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:14px;"></div>
        </div>
        <div style="padding:16px 24px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end;">
            <button class="btn btn-secondary" onclick="closePaymongoModal()">Cancel</button>
            <button class="btn btn-primary" id="pmPayBtn" onclick="launchPaymongo()" style="min-width:170px;">
                <span id="pmBtnText">🔗 Generate Payment Link</span>
            </button>
        </div>
    </div>
</div>

<!-- ── PayMongo Status Modal ──────────────────────────────────────────── -->
<div id="pmStatusModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:10000;align-items:center;justify-content:center;">
    <div style="background:var(--surface);border-radius:16px;padding:32px 40px;text-align:center;max-width:360px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.4);">
        <div id="pmStatusIcon" style="font-size:52px;margin-bottom:12px;">⏳</div>
        <h3 id="pmStatusTitle" style="margin:0 0 8px;font-size:18px;">Waiting for payment…</h3>
        <p id="pmStatusMsg" style="color:var(--text-muted);font-size:13px;margin:0 0 20px;line-height:1.5;"></p>
        <div style="display:flex;gap:10px;justify-content:center;">
            <button class="btn btn-primary btn-sm" id="pmCheckBtn" onclick="checkPaymongoStatus()">🔄 Check Status</button>
            <button class="btn btn-secondary btn-sm" onclick="closePmStatus()">Close</button>
        </div>
    </div>
</div>

<script>
var _pmBillingId=null,_pmCheckoutUrl='',_pmPollTimer=null;

function openPaymongoModal(billingId,invoiceNum,balance){
    _pmBillingId=billingId; _pmCheckoutUrl='';
    document.getElementById('pmInvoiceInfo').innerHTML=
        '<strong>Invoice:</strong> '+invoiceNum+'<br>'+
        '<strong>Amount Due:</strong> <span style="font-size:20px;font-weight:800;color:var(--primary);">₱'+
        Number(balance).toLocaleString('en-PH',{minimumFractionDigits:2})+'</span>';
    document.getElementById('pmError').style.display='none';
    document.getElementById('pmBtnText').textContent='🔗 Generate Payment Link';
    document.getElementById('pmPayBtn').disabled=false;
    const m=document.getElementById('paymongoModal');
    m.style.display='flex'; document.body.style.overflow='hidden';
}
function closePaymongoModal(){
    document.getElementById('paymongoModal').style.display='none';
    document.body.style.overflow='';
}
function launchPaymongo(){
    if(!_pmBillingId) return;
    const btn=document.getElementById('pmPayBtn');
    btn.disabled=true;
    document.getElementById('pmBtnText').textContent='⏳ Creating link…';
    document.getElementById('pmError').style.display='none';
    const fd=new FormData();
    fd.append('action','create_payment');
    fd.append('billing_id',_pmBillingId);
    fetch('/api/paymongo.php',{method:'POST',body:fd})
        .then(r=>r.json())
        .then(data=>{
            if(data.success){
                _pmCheckoutUrl=data.checkout_url;
                window.open(data.checkout_url,'_blank');
                closePaymongoModal();
                openPmStatusModal(!!data.reused);
            } else {
                document.getElementById('pmError').textContent=data.error||'Failed to create payment link.';
                document.getElementById('pmError').style.display='block';
                btn.disabled=false;
                document.getElementById('pmBtnText').textContent='🔗 Generate Payment Link';
            }
        })
        .catch(()=>{
            document.getElementById('pmError').textContent='Network error. Please try again.';
            document.getElementById('pmError').style.display='block';
            btn.disabled=false;
            document.getElementById('pmBtnText').textContent='🔗 Generate Payment Link';
        });
}
function openPmStatusModal(reused){
    document.getElementById('pmStatusIcon').textContent='⏳';
    document.getElementById('pmStatusTitle').textContent='Waiting for Payment…';
    document.getElementById('pmStatusMsg').textContent=reused
        ?'Your payment window is open. Complete payment then click Check Status.'
        :'A payment tab was opened. Finish the payment there, then click Check Status.';
    document.getElementById('pmStatusModal').style.display='flex';
    document.body.style.overflow='hidden';
    _pmPollTimer=setInterval(checkPaymongoStatus,8000);
}
function closePmStatus(){
    clearInterval(_pmPollTimer);
    document.getElementById('pmStatusModal').style.display='none';
    document.body.style.overflow='';
}
function checkPaymongoStatus(){
    if(!_pmBillingId) return;
    document.getElementById('pmCheckBtn').textContent='⏳ Checking…';
    document.getElementById('pmCheckBtn').disabled=true;
    const fd=new FormData();
    fd.append('action','check_status');
    fd.append('billing_id',_pmBillingId);
    fetch('/api/paymongo.php',{method:'POST',body:fd})
        .then(r=>r.json())
        .then(data=>{
            document.getElementById('pmCheckBtn').textContent='🔄 Check Status';
            document.getElementById('pmCheckBtn').disabled=false;
            if(data.success && data.payment_status==='paid'){
                clearInterval(_pmPollTimer);
                document.getElementById('pmStatusIcon').textContent='✅';
                document.getElementById('pmStatusTitle').textContent='Payment Confirmed!';
                document.getElementById('pmStatusMsg').textContent='Your payment was successful. Refreshing…';
                setTimeout(()=>location.reload(),2500);
            } else {
                const st=data.pm_status||data.payment_status||'pending';
                document.getElementById('pmStatusMsg').textContent='Status: '+st+'. If you completed payment, please wait and check again.';
            }
        })
        .catch(()=>{
            document.getElementById('pmCheckBtn').textContent='🔄 Check Status';
            document.getElementById('pmCheckBtn').disabled=false;
        });
}
// Handle return from PayMongo checkout page
(function(){
    const p=new URLSearchParams(window.location.search);
    if(p.get('paymongo_status')==='success' && p.get('billing_id')){
        _pmBillingId=p.get('billing_id');
        openPmStatusModal(false);
        checkPaymongoStatus();
    }
})();

var _bT;
function debounceBF() { clearTimeout(_bT); _bT = setTimeout(applyBF, 400); }
function applyBF() {
    const p = new URLSearchParams();
    const q  = document.getElementById('bSearch').value.trim();
    const st = document.getElementById('bStatus').value;
    const dr = document.getElementById('bDoctor') ? document.getElementById('bDoctor').value : '';
    const mt = document.getElementById('bMethod').value;
    const df = document.getElementById('bDateFrom').value;
    const dt = document.getElementById('bDateTo').value;
    const ov = document.getElementById('bOverdue').checked;
    if (q)  p.set('q', q);
    if (st) p.set('status', st);
    if (dr) p.set('doctor', dr);
    if (mt) p.set('method', mt);
    if (df) p.set('date_from', df);
    if (dt) p.set('date_to', dt);
    if (ov) p.set('overdue', '1');
    window.location.href = 'billing.php?' + p.toString();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
