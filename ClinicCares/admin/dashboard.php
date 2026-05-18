<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('admin');

$pageTitle = 'Admin Dashboard';
$activeNav = 'dashboard';

// Stats
$stats = [
    'total_patients'     => db()->fetchOne("SELECT COUNT(*) as c FROM patients")['c'],
    'total_doctors'      => db()->fetchOne("SELECT COUNT(*) as c FROM doctors")['c'],
    'today_appointments' => db()->fetchOne("SELECT COUNT(*) as c FROM appointments WHERE appointment_date = CURDATE()")['c'],
    'pending_billing'    => db()->fetchOne("SELECT COUNT(*) as c FROM billing WHERE status = 'pending'")['c'],
    'monthly_revenue'    => db()->fetchOne("SELECT COALESCE(SUM(amount_paid),0) as c FROM billing WHERE MONTH(payment_date)=MONTH(CURDATE()) AND YEAR(payment_date)=YEAR(CURDATE())")['c'],
    'total_appointments' => db()->fetchOne("SELECT COUNT(*) as c FROM appointments WHERE MONTH(appointment_date)=MONTH(CURDATE())")['c'],
];

// Recent appointments
$recentAppointments = db()->fetchAll("
    SELECT a.*, 
           CONCAT(up.first_name,' ',up.last_name) as patient_name,
           CONCAT(ud.first_name,' ',ud.last_name) as doctor_name,
           d.specialization
    FROM appointments a
    JOIN patients p ON a.patient_id=p.id
    JOIN users up ON p.user_id=up.id
    JOIN doctors d ON a.doctor_id=d.id
    JOIN users ud ON d.user_id=ud.id
    ORDER BY a.created_at DESC LIMIT 10
");

// Chart data - appointments per day last 7 days
$chartDays = [];
$chartCounts = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartDays[] = date('M d', strtotime($date));
    $count = db()->fetchOne("SELECT COUNT(*) as c FROM appointments WHERE appointment_date=?", [$date])['c'];
    $chartCounts[] = (int)$count;
}

// Revenue per month last 6 months
$revenueLabels = [];
$revenueData = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $label = date('M Y', strtotime("-$i months"));
    $revenueLabels[] = $label;
    $rev = db()->fetchOne("SELECT COALESCE(SUM(amount_paid),0) as c FROM billing WHERE DATE_FORMAT(payment_date,'%Y-%m')=?", [$month])['c'];
    $revenueData[] = (float)$rev;
}

// Top doctors by appointments
$topDoctors = db()->fetchAll("
    SELECT CONCAT(u.first_name,' ',u.last_name) as name, d.specialization,
           COUNT(a.id) as appt_count
    FROM doctors d
    JOIN users u ON d.user_id=u.id
    LEFT JOIN appointments a ON a.doctor_id=d.id AND MONTH(a.appointment_date)=MONTH(CURDATE())
    GROUP BY d.id ORDER BY appt_count DESC LIMIT 5
");

// Appointment status breakdown
$statusBreakdown = db()->fetchAll("SELECT status, COUNT(*) as c FROM appointments GROUP BY status");
$statusLabels = array_column($statusBreakdown, 'status');
$statusCounts = array_column($statusBreakdown, 'c');

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1>Dashboard Overview</h1>
  <p>Welcome back, <?= htmlspecialchars($_SESSION['name']) ?>! Here's what's happening today.</p>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
  <div class="stat-card blue">
    <div class="stat-icon blue">🧑‍🤝‍🧑</div>
    <div class="stat-value"><?= number_format($stats['total_patients']) ?></div>
    <div class="stat-label">Total Patients</div>
  </div>
  <div class="stat-card teal">
    <div class="stat-icon teal">👨‍⚕️</div>
    <div class="stat-value"><?= number_format($stats['total_doctors']) ?></div>
    <div class="stat-label">Doctors</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon green">📅</div>
    <div class="stat-value"><?= number_format($stats['today_appointments']) ?></div>
    <div class="stat-label">Today's Appointments</div>
  </div>
  <div class="stat-card amber">
    <div class="stat-icon amber">💳</div>
    <div class="stat-value"><?= number_format($stats['pending_billing']) ?></div>
    <div class="stat-label">Pending Bills</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon green">💰</div>
    <div class="stat-value"><?= formatCurrency($stats['monthly_revenue']) ?></div>
    <div class="stat-label">Monthly Revenue</div>
  </div>
  <div class="stat-card blue">
    <div class="stat-icon blue">📊</div>
    <div class="stat-value"><?= number_format($stats['total_appointments']) ?></div>
    <div class="stat-label">Monthly Appointments</div>
  </div>
</div>

<!-- Charts Row -->
<div class="grid-2 mb-24">
  <div class="card">
    <div class="card-header">
      <span class="card-title">📈 Appointments (Last 7 Days)</span>
    </div>
    <div class="card-body">
      <canvas id="appointmentsChart" height="200"></canvas>
    </div>
  </div>
  <div class="card">
    <div class="card-header">
      <span class="card-title">💰 Revenue Trend</span>
    </div>
    <div class="card-body">
      <canvas id="revenueChart" height="200"></canvas>
    </div>
  </div>
</div>

<div class="grid-2 mb-24" style="grid-template-columns: 1fr 340px;">
  <!-- Recent Appointments -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">🕐 Recent Appointments</span>
      <a href="/cliniccares/admin/appointments.php" class="btn btn-secondary btn-sm">View All</a>
    </div>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>Patient</th>
            <th>Doctor</th>
            <th>Date & Time</th>
            <th>Type</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentAppointments as $a): ?>
          <tr data-searchable>
            <td>
              <div class="d-flex align-center gap-8">
                <div class="avatar" style="width:32px;height:32px;font-size:12px;"><?= strtoupper(substr($a['patient_name'],0,2)) ?></div>
                <?= htmlspecialchars($a['patient_name']) ?>
              </div>
            </td>
            <td>
              <div style="font-size:13px;">Dr. <?= htmlspecialchars($a['doctor_name']) ?></div>
              <div class="text-muted text-sm"><?= htmlspecialchars($a['specialization']) ?></div>
            </td>
            <td>
              <div><?= formatDate($a['appointment_date']) ?></div>
              <div class="text-muted text-sm"><?= formatTime($a['appointment_time']) ?></div>
            </td>
            <td><span class="badge badge-info"><?= ucfirst(str_replace('_',' ',$a['type'])) ?></span></td>
            <td><span class="badge <?= getStatusBadge($a['status']) ?>"><?= ucfirst(str_replace('_',' ',$a['status'])) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Right column -->
  <div style="display:flex;flex-direction:column;gap:16px;">
    <!-- Status Breakdown -->
    <div class="card">
      <div class="card-header"><span class="card-title">📊 Status Breakdown</span></div>
      <div class="card-body">
        <canvas id="statusChart" height="200"></canvas>
      </div>
    </div>

    <!-- Top Doctors -->
    <div class="card">
      <div class="card-header"><span class="card-title">🏆 Top Doctors</span></div>
      <div class="card-body" style="padding:12px 16px;">
        <?php foreach ($topDoctors as $i => $d): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border);">
          <div style="width:24px;height:24px;border-radius:50%;background:var(--primary);color:#fff;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;"><?= $i+1 ?></div>
          <div style="flex:1;">
            <div style="font-size:13px;font-weight:600;">Dr. <?= htmlspecialchars($d['name']) ?></div>
            <div class="text-muted text-sm"><?= htmlspecialchars($d['specialization']) ?></div>
          </div>
          <div style="font-size:13px;font-weight:600;color:var(--primary);"><?= $d['appt_count'] ?> appts</div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<script>
const chartOpts = { responsive: true, plugins: { legend: { display: false } } };

// Appointments chart
new Chart(document.getElementById('appointmentsChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($chartDays) ?>,
    datasets: [{
      label: 'Appointments',
      data: <?= json_encode($chartCounts) ?>,
      backgroundColor: 'rgba(29,78,216,0.15)',
      borderColor: 'rgba(29,78,216,0.8)',
      borderWidth: 2,
      borderRadius: 6,
    }]
  },
  options: { ...chartOpts, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

// Revenue chart
new Chart(document.getElementById('revenueChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($revenueLabels) ?>,
    datasets: [{
      label: 'Revenue (₱)',
      data: <?= json_encode($revenueData) ?>,
      fill: true,
      backgroundColor: 'rgba(22,163,74,0.1)',
      borderColor: '#16a34a',
      borderWidth: 2,
      pointBackgroundColor: '#16a34a',
      pointRadius: 4,
    }]
  },
  options: { ...chartOpts, plugins: { legend: { display: true } }, scales: { y: { beginAtZero: true } } }
});

// Status doughnut
new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_map('ucfirst', $statusLabels)) ?>,
    datasets: [{
      data: <?= json_encode($statusCounts) ?>,
      backgroundColor: ['#fef9c3','#cffafe','#dcfce7','#fee2e2','#f1f5f9'],
      borderColor: ['#a16207','#0e7490','#15803d','#b91c1c','#64748b'],
      borderWidth: 2,
    }]
  },
  options: { responsive: true, plugins: { legend: { position: 'bottom' } }, cutout: '65%' }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>