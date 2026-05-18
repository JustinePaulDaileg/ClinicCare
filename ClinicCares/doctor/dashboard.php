<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('doctor');

// Get doctor profile
$doctor = db()->fetchOne("SELECT d.*, u.first_name, u.last_name, u.email, u.phone FROM doctors d JOIN users u ON d.user_id=u.id WHERE d.user_id=?", [$_SESSION['user_id']]);
if (!$doctor) redirect(SITE_URL . '/index.php?error=Doctor+profile+not+found');

$doctorId = $doctor['id'];

// Today's appointments
$todayAppts = db()->fetchAll("
    SELECT a.*, CONCAT(u.first_name,' ',u.last_name) as patient_name, u.phone as patient_phone, p.date_of_birth, p.gender, p.blood_type
    FROM appointments a
    JOIN patients p ON a.patient_id=p.id
    JOIN users u ON p.user_id=u.id
    WHERE a.doctor_id=? AND a.appointment_date=CURDATE()
    ORDER BY a.appointment_time ASC", [$doctorId]);

// Stats
$stats = [
    'today'      => count($todayAppts),
    'week'       => db()->fetchOne("SELECT COUNT(*) as c FROM appointments WHERE doctor_id=? AND appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY)", [$doctorId])['c'],
    'patients'   => db()->fetchOne("SELECT COUNT(DISTINCT patient_id) as c FROM appointments WHERE doctor_id=?", [$doctorId])['c'],
    'pending'    => db()->fetchOne("SELECT COUNT(*) as c FROM appointments WHERE doctor_id=? AND status='pending'", [$doctorId])['c'],
];

// Upcoming appointments (next 7 days)
$upcomingAppts = db()->fetchAll("
    SELECT a.*, CONCAT(u.first_name,' ',u.last_name) as patient_name
    FROM appointments a
    JOIN patients p ON a.patient_id=p.id JOIN users u ON p.user_id=u.id
    WHERE a.doctor_id=? AND a.appointment_date > CURDATE() AND a.appointment_date <= DATE_ADD(CURDATE(),INTERVAL 7 DAY)
    ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 8", [$doctorId]);

// Recent prescriptions written
$recentRx = db()->fetchAll("
    SELECT pr.*, CONCAT(u.first_name,' ',u.last_name) as patient_name
    FROM prescriptions pr
    JOIN patients p ON pr.patient_id=p.id JOIN users u ON p.user_id=u.id
    WHERE pr.doctor_id=?
    ORDER BY pr.created_at DESC LIMIT 5", [$doctorId]);

$pageTitle = 'Doctor Dashboard';
$activeNav = 'dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Good <?= date('H')<12?'Morning':( date('H')<17?'Afternoon':'Evening') ?>, Dr. <?= htmlspecialchars($doctor['last_name']) ?>! 👋</h1>
    <p><?= date('l, F j, Y') ?> — <?= $stats['today'] ?> appointment<?= $stats['today']!=1?'s':'' ?> today</p>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card blue">
        <div class="stat-icon blue">📅</div>
        <div class="stat-value"><?= $stats['today'] ?></div>
        <div class="stat-label">Today's Appointments</div>
    </div>
    <div class="stat-card teal">
        <div class="stat-icon teal">🗓️</div>
        <div class="stat-value"><?= $stats['week'] ?></div>
        <div class="stat-label">This Week</div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon green">🧑‍🤝‍🧑</div>
        <div class="stat-value"><?= $stats['patients'] ?></div>
        <div class="stat-label">Total Patients</div>
    </div>
    <div class="stat-card amber">
        <div class="stat-icon amber">⏳</div>
        <div class="stat-value"><?= $stats['pending'] ?></div>
        <div class="stat-label">Pending Approvals</div>
    </div>
</div>

<div class="grid-2 mb-24" style="grid-template-columns:1fr 360px;">
    <!-- Today's schedule -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">📋 Today's Schedule</span>
            <a href="/cliniccares/doctor/appointments.php" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <?php if (empty($todayAppts)): ?>
        <div class="card-body" style="text-align:center;padding:40px;">
            <div style="font-size:48px;margin-bottom:12px;">🎉</div>
            <h3 style="font-family:var(--font-display);margin-bottom:8px;">No appointments today</h3>
            <p style="color:var(--text-muted);">Enjoy your free day!</p>
        </div>
        <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Time</th><th>Patient</th><th>Type</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($todayAppts as $a): ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;font-size:15px;"><?= formatTime($a['appointment_time']) ?></div>
                            <div class="text-muted text-sm">–<?= formatTime($a['end_time']) ?></div>
                        </td>
                        <td>
                            <div style="font-weight:600;"><?= htmlspecialchars($a['patient_name']) ?></div>
                            <div class="text-muted text-sm"><?= htmlspecialchars($a['gender']??'') ?><?= $a['date_of_birth']?' · '.date('Y')-date('Y',strtotime($a['date_of_birth'])).' yrs':'' ?></div>
                        </td>
                        <td><span class="badge badge-info"><?= ucfirst(str_replace('_',' ',$a['type'])) ?></span></td>
                        <td><span class="badge <?= getStatusBadge($a['status']) ?>"><?= ucfirst(str_replace('_',' ',$a['status'])) ?></span></td>
                        <td>
                            <div class="d-flex gap-8">
                                <a href="/cliniccares/doctor/view-patient.php?appt=<?= $a['id'] ?>" class="btn btn-primary btn-sm">Start →</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right sidebar -->
    <div style="display:flex;flex-direction:column;gap:16px;">
        <!-- Upcoming -->
        <div class="card">
            <div class="card-header"><span class="card-title">📆 Upcoming (7 days)</span></div>
            <div style="max-height:260px;overflow-y:auto;">
                <?php if (empty($upcomingAppts)): ?>
                <div style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px;">No upcoming appointments</div>
                <?php else: ?>
                <?php foreach ($upcomingAppts as $a): ?>
                <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;gap:10px;align-items:flex-start;">
                    <div style="width:44px;text-align:center;flex-shrink:0;">
                        <div style="font-size:18px;font-weight:700;color:var(--primary);line-height:1;"><?= date('d',strtotime($a['appointment_date'])) ?></div>
                        <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;"><?= date('M',strtotime($a['appointment_date'])) ?></div>
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($a['patient_name']) ?></div>
                        <div style="font-size:12px;color:var(--text-muted);"><?= formatTime($a['appointment_time']) ?> · <?= ucfirst($a['type']) ?></div>
                    </div>
                    <span class="badge <?= getStatusBadge($a['status']) ?>" style="font-size:10px;"><?= ucfirst($a['status']) ?></span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent prescriptions -->
        <div class="card">
            <div class="card-header"><span class="card-title">💊 Recent Prescriptions</span></div>
            <div style="padding:8px 0;">
                <?php if (empty($recentRx)): ?>
                <div style="padding:16px;text-align:center;color:var(--text-muted);font-size:13px;">No prescriptions yet</div>
                <?php else: ?>
                <?php foreach ($recentRx as $rx): ?>
                <div style="padding:10px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($rx['patient_name']) ?></div>
                        <div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($rx['prescription_number']) ?> · <?= formatDate($rx['issue_date']) ?></div>
                    </div>
                    <span class="badge <?= getStatusBadge($rx['status']) ?>"><?= ucfirst($rx['status']) ?></span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <a href="/cliniccares/doctor/prescriptions.php" class="btn btn-secondary btn-sm btn-block">View All Prescriptions</a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>