<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('patient');

$patientUser = db()->fetchOne("SELECT p.*, u.first_name, u.last_name, u.email, u.phone FROM patients p JOIN users u ON p.user_id=u.id WHERE p.user_id=?", [$_SESSION['user_id']]);
if (!$patientUser) redirect(SITE_URL . '/index.php?error=Patient+profile+not+found');
$patientId = $patientUser['id'];

// Upcoming appointments
$upcomingAppts = db()->fetchAll("
    SELECT a.*, CONCAT(u.first_name,' ',u.last_name) as doctor_name, d.specialization
    FROM appointments a
    JOIN doctors d ON a.doctor_id=d.id JOIN users u ON d.user_id=u.id
    WHERE a.patient_id=? AND a.appointment_date >= CURDATE() AND a.status NOT IN ('cancelled')
    ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 5", [$patientId]);

// Stats
$stats = [
    'upcoming'  => db()->fetchOne("SELECT COUNT(*) as c FROM appointments WHERE patient_id=? AND appointment_date>=CURDATE() AND status NOT IN ('cancelled')", [$patientId])['c'],
    'total'     => db()->fetchOne("SELECT COUNT(*) as c FROM appointments WHERE patient_id=?", [$patientId])['c'],
    'prescriptions' => db()->fetchOne("SELECT COUNT(*) as c FROM prescriptions WHERE patient_id=? AND status='active'", [$patientId])['c'],
    'unpaid'    => db()->fetchOne("SELECT COUNT(*) as c FROM billing WHERE patient_id=? AND status IN ('pending','partial')", [$patientId])['c'],
];

// Recent prescriptions
$recentRx = db()->fetchAll("
    SELECT pr.*, CONCAT(u.first_name,' ',u.last_name) as doctor_name
    FROM prescriptions pr JOIN doctors d ON pr.doctor_id=d.id JOIN users u ON d.user_id=u.id
    WHERE pr.patient_id=?
    ORDER BY pr.created_at DESC LIMIT 4", [$patientId]);

// Recent bills
$recentBills = db()->fetchAll("
    SELECT b.* FROM billing b WHERE b.patient_id=?
    ORDER BY b.created_at DESC LIMIT 4", [$patientId]);

// Latest medical record
$latestRecord = db()->fetchOne("
    SELECT mr.*, CONCAT(u.first_name,' ',u.last_name) as doctor_name
    FROM medical_records mr JOIN doctors d ON mr.doctor_id=d.id JOIN users u ON d.user_id=u.id
    WHERE mr.patient_id=?
    ORDER BY mr.record_date DESC LIMIT 1", [$patientId]);

$pageTitle = 'My Dashboard';
$activeNav = 'dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Hello, <?= htmlspecialchars($patientUser['first_name']) ?>! 👋</h1>
    <p>Here's a summary of your health activity</p>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card blue">
        <div class="stat-icon blue">📅</div>
        <div class="stat-value"><?= $stats['upcoming'] ?></div>
        <div class="stat-label">Upcoming Appointments</div>
    </div>
    <div class="stat-card teal">
        <div class="stat-icon teal">🗂️</div>
        <div class="stat-value"><?= $stats['total'] ?></div>
        <div class="stat-label">Total Visits</div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon green">💊</div>
        <div class="stat-value"><?= $stats['prescriptions'] ?></div>
        <div class="stat-label">Active Prescriptions</div>
    </div>
    <div class="stat-card <?= $stats['unpaid']>0?'amber':'green' ?>">
        <div class="stat-icon <?= $stats['unpaid']>0?'amber':'green' ?>">💳</div>
        <div class="stat-value"><?= $stats['unpaid'] ?></div>
        <div class="stat-label">Pending Bills</div>
    </div>
</div>

<!-- Quick Actions -->
<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px;">
    <a href="/cliniccares/patient/book.php" class="btn btn-primary btn-lg">Book Appointment</a>
    <a href="/cliniccares/patient/records.php" class="btn btn-secondary btn-lg">My Records</a>
    <a href="/cliniccares/patient/prescriptions.php" class="btn btn-secondary btn-lg">Prescriptions</a>
    <a href="/cliniccares/patient/billing.php" class="btn btn-secondary btn-lg">Billing</a>
</div>

<div class="grid-2 mb-24" style="grid-template-columns:1fr 380px;">
    <!-- Upcoming appointments -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">📅 Upcoming Appointments</span>
            <a href="/cliniccares/patient/appointments.php" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <?php if (empty($upcomingAppts)): ?>
        <div class="card-body" style="text-align:center;padding:40px;">
            <div style="font-size:48px;margin-bottom:12px;">📅</div>
            <h3 style="font-family:var(--font-display);margin-bottom:8px;">No upcoming appointments</h3>
            <p style="color:var(--text-muted);margin-bottom:16px;">Schedule your next visit with a doctor</p>
            <a href="/cliniccares/patient/book.php" class="btn btn-primary">Book an Appointment</a>
        </div>
        <?php else: ?>
        <div class="card-body" style="padding:0;">
            <?php foreach ($upcomingAppts as $a): ?>
            <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:flex-start;gap:14px;">
                <div style="width:50px;text-align:center;flex-shrink:0;background:var(--primary-pale);border-radius:8px;padding:8px 4px;">
                    <div style="font-size:18px;font-weight:800;color:var(--primary);line-height:1;"><?= date('d',strtotime($a['appointment_date'])) ?></div>
                    <div style="font-size:10px;color:var(--primary);text-transform:uppercase;font-weight:600;"><?= date('M',strtotime($a['appointment_date'])) ?></div>
                </div>
                <div style="flex:1;">
                    <div style="font-weight:600;">Dr. <?= htmlspecialchars($a['doctor_name']) ?></div>
                    <div style="font-size:13px;color:var(--text-muted);"><?= htmlspecialchars($a['specialization']) ?></div>
                    <div style="font-size:13px;margin-top:4px;">
                        <span>🕐 <?= formatTime($a['appointment_time']) ?></span>
                        <span style="margin-left:10px;">🏷️ <?= ucfirst(str_replace('_',' ',$a['type'])) ?></span>
                    </div>
                    <?php if ($a['reason']): ?>
                    <div style="font-size:12px;color:var(--text-muted);margin-top:3px;">📝 <?= htmlspecialchars($a['reason']) ?></div>
                    <?php endif; ?>
                </div>
                <span class="badge <?= getStatusBadge($a['status']) ?>"><?= ucfirst($a['status']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right side -->
    <div style="display:flex;flex-direction:column;gap:16px;">
        <!-- Health Summary -->
        <?php if ($latestRecord): ?>
        <div class="card">
            <div class="card-header"><span class="card-title">🩺 Last Visit Summary</span></div>
            <div class="card-body">
                <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px;"><?= formatDate($latestRecord['record_date']) ?> · Dr. <?= htmlspecialchars($latestRecord['doctor_name']) ?></div>
                <div style="font-size:14px;font-weight:600;margin-bottom:8px;"><?= htmlspecialchars($latestRecord['diagnosis']) ?></div>
                <?php if ($latestRecord['vital_bp'] || $latestRecord['vital_pulse']): ?>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:12px;">
                    <?php if ($latestRecord['vital_bp']): ?>
                    <div style="background:var(--surface-2);border-radius:8px;padding:10px;text-align:center;">
                        <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;">BP</div>
                        <div style="font-size:15px;font-weight:700;color:var(--primary);"><?= htmlspecialchars($latestRecord['vital_bp']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($latestRecord['vital_temp']): ?>
                    <div style="background:var(--surface-2);border-radius:8px;padding:10px;text-align:center;">
                        <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;">Temp</div>
                        <div style="font-size:15px;font-weight:700;color:var(--accent);"><?= htmlspecialchars($latestRecord['vital_temp']) ?>°C</div>
                    </div>
                    <?php endif; ?>
                    <?php if ($latestRecord['vital_pulse']): ?>
                    <div style="background:var(--surface-2);border-radius:8px;padding:10px;text-align:center;">
                        <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;">Pulse</div>
                        <div style="font-size:15px;font-weight:700;color:var(--danger);"><?= htmlspecialchars($latestRecord['vital_pulse']) ?> bpm</div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Prescriptions -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">💊 Active Prescriptions</span>
                <a href="/cliniccares/patient/prescriptions.php" class="btn btn-secondary btn-sm">View All</a>
            </div>
            <div>
                <?php if (empty($recentRx)): ?>
                <div style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px;">No prescriptions yet</div>
                <?php else: ?>
                <?php foreach ($recentRx as $rx): ?>
                <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-size:13px;font-weight:600;font-family:monospace;color:var(--primary);"><?= htmlspecialchars($rx['prescription_number']) ?></div>
                        <div style="font-size:12px;color:var(--text-muted);">Dr. <?= htmlspecialchars($rx['doctor_name']) ?> · <?= formatDate($rx['issue_date']) ?></div>
                    </div>
                    <div class="d-flex gap-8 align-center">
                        <span class="badge <?= getStatusBadge($rx['status']) ?>"><?= ucfirst($rx['status']) ?></span>
                        <a href="/cliniccares/doctor/print-prescriptions.php?id=<?= $rx['id'] ?>" target="_blank" class="btn btn-secondary btn-sm">🖨️</a>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pending Bills -->
        <?php if (!empty($recentBills)): ?>
        <div class="card">
            <div class="card-header">
                <span class="card-title">💳 Recent Bills</span>
                <a href="/cliniccares/patient/billing.php" class="btn btn-secondary btn-sm">View All</a>
            </div>
            <div>
                <?php foreach ($recentBills as $bill): ?>
                <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-size:13px;font-weight:600;font-family:monospace;"><?= htmlspecialchars($bill['invoice_number']) ?></div>
                        <div style="font-size:12px;color:var(--text-muted);"><?= formatDate($bill['created_at']) ?></div>
                    </div>
                    <div class="d-flex gap-8 align-center">
                        <span style="font-weight:700;color:<?= $bill['balance']>0?'var(--danger)':'var(--success)' ?>;"><?= formatCurrency($bill['total']) ?></span>
                        <span class="badge <?= getStatusBadge($bill['status']) ?>"><?= ucfirst($bill['status']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>