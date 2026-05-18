<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('admin');

$year  = (int)($_GET['year'] ?? date('Y'));
$month = (int)($_GET['month'] ?? 0);

// Monthly appointment counts for chosen year
$monthlyAppts = [];
$monthlyRevenue = [];
for ($m = 1; $m <= 12; $m++) {
    $cnt = db()->fetchOne("SELECT COUNT(*) as c FROM appointments WHERE YEAR(appointment_date)=? AND MONTH(appointment_date)=?", [$year, $m])['c'];
    $rev = db()->fetchOne("SELECT COALESCE(SUM(amount_paid),0) as c FROM billing WHERE YEAR(payment_date)=? AND MONTH(payment_date)=?", [$year, $m])['c'];
    $monthlyAppts[]   = (int)$cnt;
    $monthlyRevenue[] = (float)$rev;
}

// Department/specialization breakdown
$bySpecialization = db()->fetchAll("
    SELECT d.specialization, COUNT(a.id) as total
    FROM appointments a
    JOIN doctors d ON a.doctor_id=d.id
    WHERE YEAR(a.appointment_date)=?
    GROUP BY d.specialization ORDER BY total DESC", [$year]);

// Appointment status breakdown for year
$statusBreak = db()->fetchAll("SELECT status, COUNT(*) as c FROM appointments WHERE YEAR(appointment_date)=? GROUP BY status", [$year]);

// Top patients by visits
$topPatients = db()->fetchAll("
    SELECT CONCAT(u.first_name,' ',u.last_name) as name, COUNT(a.id) as visits
    FROM appointments a JOIN patients p ON a.patient_id=p.id JOIN users u ON p.user_id=u.id
    WHERE YEAR(a.appointment_date)=?
    GROUP BY p.id ORDER BY visits DESC LIMIT 10", [$year]);

// Payment method breakdown
$payMethods = db()->fetchAll("SELECT payment_method, COUNT(*) as c, SUM(amount_paid) as total FROM billing WHERE payment_method IS NOT NULL AND YEAR(payment_date)=? GROUP BY payment_method", [$year]);

// Summary totals
$yearTotals = db()->fetchOne("
    SELECT
        (SELECT COUNT(*) FROM appointments WHERE YEAR(appointment_date)=?) as total_appts,
        (SELECT COUNT(*) FROM patients WHERE YEAR(created_at)=?) as new_patients,
        (SELECT COALESCE(SUM(amount_paid),0) FROM billing WHERE YEAR(payment_date)=?) as revenue,
        (SELECT COUNT(*) FROM prescriptions WHERE YEAR(created_at)=?) as prescriptions
", [$year, $year, $year, $year]);

$monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

$pageTitle = 'Reports & Analytics';
$activeNav = 'reports';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-between align-center">
    <div>
        <h1>Reports & Analytics</h1>
        <p>Comprehensive clinic performance and financial reports</p>
    </div>
    <div class="d-flex gap-8 align-center">
        <select class="form-select" style="width:120px;" onchange="window.location.href='?year='+this.value">
            <?php for ($y = date('Y'); $y >= date('Y')-4; $y--): ?>
                <option value="<?=$y?>" <?= $y===$year?'selected':'' ?>><?=$y?></option>
            <?php endfor; ?>
        </select>
        <button class="btn btn-secondary" onclick="window.print()">🖨️ Print Report</button>
    </div>
</div>

<!-- Year Summary Cards -->
<div class="stats-grid">
    <div class="stat-card blue">
        <div class="stat-icon blue">📅</div>
        <div class="stat-value"><?= number_format($yearTotals['total_appts']) ?></div>
        <div class="stat-label">Total Appointments <?=$year?></div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon green">🧑‍🤝‍🧑</div>
        <div class="stat-value"><?= number_format($yearTotals['new_patients']) ?></div>
        <div class="stat-label">New Patients <?=$year?></div>
    </div>
    <div class="stat-card teal">
        <div class="stat-icon teal">💰</div>
        <div class="stat-value"><?= formatCurrency($yearTotals['revenue']) ?></div>
        <div class="stat-label">Revenue <?=$year?></div>
    </div>
    <div class="stat-card amber">
        <div class="stat-icon amber">💊</div>
        <div class="stat-value"><?= number_format($yearTotals['prescriptions']) ?></div>
        <div class="stat-label">Prescriptions <?=$year?></div>
    </div>
</div>

<!-- Charts Row 1 -->
<div class="grid-2 mb-24">
    <div class="card">
        <div class="card-header"><span class="card-title"> Monthly Appointments (<?=$year?>)</span></div>
        <div class="card-body"><canvas id="monthlyApptChart" height="220"></canvas></div>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title"> Monthly Revenue (<?=$year?>)</span></div>
        <div class="card-body"><canvas id="monthlyRevChart" height="220"></canvas></div>
    </div>
</div>

<!-- Charts Row 2 -->
<div class="grid-2 mb-24" style="grid-template-columns:1fr 1fr;">
    <div class="card">
        <div class="card-header"><span class="card-title">By Specialization</span></div>
        <div class="card-body"><canvas id="specChart" height="240"></canvas></div>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title">Appointment Status</span></div>
        <div class="card-body"><canvas id="statusChart" height="240"></canvas></div>
    </div>
</div>

<!-- Tables Row -->
<div class="grid-2 mb-24">
    <!-- Top patients -->
    <div class="card">
        <div class="card-header"><span class="card-title">🏆 Most Frequent Patients</span></div>
        <div class="table-wrapper">
            <table>
                <thead><tr><th>#</th><th>Patient</th><th>Visits</th></tr></thead>
                <tbody>
                    <?php foreach ($topPatients as $i => $pt): ?>
                    <tr>
                        <td style="font-weight:700;color:var(--primary);"><?= $i+1 ?></td>
                        <td><?= htmlspecialchars($pt['name']) ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div style="flex:1;height:6px;background:var(--surface-3);border-radius:3px;overflow:hidden;">
                                    <div style="height:100%;background:var(--primary);width:<?= min(100, $pt['visits']/$topPatients[0]['visits']*100) ?>%;border-radius:3px;"></div>
                                </div>
                                <span style="font-weight:600;min-width:24px;"><?= $pt['visits'] ?></span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Payment Methods -->
    <div class="card">
        <div class="card-header"><span class="card-title">💳 Payment Methods</span></div>
        <div class="card-body">
            <canvas id="payMethodChart" height="200"></canvas>
            <table style="margin-top:16px;">
                <thead><tr><th>Method</th><th>Transactions</th><th>Total</th></tr></thead>
                <tbody>
                    <?php foreach ($payMethods as $pm): ?>
                    <tr>
                        <td style="text-transform:capitalize;"><?= htmlspecialchars(str_replace('_',' ',$pm['payment_method'])) ?></td>
                        <td><?= number_format($pm['c']) ?></td>
                        <td style="font-weight:600;color:var(--success);"><?= formatCurrency($pm['total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($payMethods)): ?>
                    <tr><td colspan="3" style="text-align:center;color:var(--text-muted);padding:20px;">No payment data</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const months = <?= json_encode($monthNames) ?>;
const blue = 'rgba(29,78,216,0.8)', blueLight = 'rgba(29,78,216,0.15)';
const green = 'rgba(22,163,74,0.8)', greenLight = 'rgba(22,163,74,0.1)';

new Chart(document.getElementById('monthlyApptChart'), {
    type: 'bar',
    data: {
        labels: months,
        datasets: [{
            label: 'Appointments',
            data: <?= json_encode($monthlyAppts) ?>,
            backgroundColor: blueLight, borderColor: blue, borderWidth: 2, borderRadius: 6
        }]
    },
    options: { responsive: true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,ticks:{stepSize:1}}} }
});

new Chart(document.getElementById('monthlyRevChart'), {
    type: 'line',
    data: {
        labels: months,
        datasets: [{
            label: 'Revenue (₱)',
            data: <?= json_encode($monthlyRevenue) ?>,
            fill: true, backgroundColor: greenLight, borderColor: green, borderWidth: 2, pointBackgroundColor: green, pointRadius: 4
        }]
    },
    options: { responsive: true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}} }
});

new Chart(document.getElementById('specChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($bySpecialization, 'specialization')) ?>,
        datasets: [{
            label: 'Appointments',
            data: <?= json_encode(array_column($bySpecialization, 'total')) ?>,
            backgroundColor: ['rgba(29,78,216,0.7)','rgba(15,118,110,0.7)','rgba(217,119,6,0.7)','rgba(220,38,38,0.7)','rgba(8,145,178,0.7)'],
            borderRadius: 6
        }]
    },
    options: { responsive: true, indexAxis: 'y', plugins:{legend:{display:false}} }
});

new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_map(function($s){ return ucfirst(str_replace('_',' ',$s['status'])); }, $statusBreak)) ?>,
        datasets: [{
            data: <?= json_encode(array_column($statusBreak, 'c')) ?>,
            backgroundColor: ['#fef9c3','#cffafe','#dcfce7','#fee2e2','#f1f5f9'],
            borderColor: ['#a16207','#0e7490','#15803d','#b91c1c','#64748b'],
            borderWidth: 2
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } }, cutout: '55%' }
});

new Chart(document.getElementById('payMethodChart'), {
    type: 'pie',
    data: {
        labels: <?= json_encode(array_map(function($p){ return ucfirst(str_replace('_',' ',$p['payment_method'])); }, $payMethods)) ?>,
        datasets: [{
            data: <?= json_encode(array_column($payMethods, 'total')) ?>,
            backgroundColor: ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4'],
            borderWidth: 2
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>