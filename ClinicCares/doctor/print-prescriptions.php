<?php
require_once __DIR__ . '/../includes/session.php';
requireAnyRole(['doctor', 'admin', 'patient']);

$id = (int)($_GET['id'] ?? 0);
$rx = db()->fetchOne("
    SELECT pr.*,
           CONCAT(up.first_name,' ',up.last_name) as patient_name,
           up.phone as patient_phone, up.email as patient_email,
           p.date_of_birth, p.gender, p.blood_type, p.allergies,
           CONCAT(ud.first_name,' ',ud.last_name) as doctor_name,
           ud.phone as doctor_phone, ud.email as doctor_email,
           d.specialization, d.license_number, d.department,
           d.consultation_fee
    FROM prescriptions pr
    JOIN patients p ON pr.patient_id=p.id JOIN users up ON p.user_id=up.id
    JOIN doctors d ON pr.doctor_id=d.id JOIN users ud ON d.user_id=ud.id
    WHERE pr.id=?", [$id]);

if (!$rx) {
    die('<div style="padding:40px;text-align:center;font-family:sans-serif;"><h2>Prescription not found</h2><a href="javascript:history.back()">← Back</a></div>');
}

// Access control: patient can only see their own
if ($_SESSION['role'] === 'patient') {
    $patientUser = db()->fetchOne("SELECT p.id FROM patients p WHERE p.user_id=?", [$_SESSION['user_id']]);
    if (!$patientUser || $patientUser['id'] != $rx['patient_id']) {
        die('<div style="padding:40px;text-align:center;font-family:sans-serif;"><h2>Access denied</h2></div>');
    }
}

$items = db()->fetchAll("SELECT * FROM prescription_items WHERE prescription_id=?", [$id]);
$age = $rx['date_of_birth'] ? date_diff(date_create($rx['date_of_birth']), date_create('today'))->y : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescription <?= htmlspecialchars($rx['prescription_number']) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f4f8; padding: 20px; font-size: 14px; color: #1e293b; }
        .print-page { max-width: 780px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.1); }
        .rx-header { background: linear-gradient(135deg, #1e40af, #1d4ed8); color: #fff; padding: 28px 32px; display: flex; justify-content: space-between; align-items: flex-start; }
        .clinic-info h1 { font-size: 22px; font-weight: 800; margin-bottom: 4px; }
        .clinic-info p { font-size: 12px; opacity: 0.8; }
        .doctor-info { text-align: right; }
        .doctor-info h2 { font-size: 16px; font-weight: 700; }
        .doctor-info p { font-size: 12px; opacity: 0.8; margin-top: 2px; }
        .rx-meta { background: #eff6ff; padding: 16px 32px; display: flex; gap: 32px; border-bottom: 2px solid #dbeafe; flex-wrap: wrap; }
        .rx-meta-item { }
        .rx-meta-item .label { font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .rx-meta-item .value { font-size: 14px; font-weight: 600; color: #1e293b; margin-top: 2px; }
        .rx-body { padding: 28px 32px; }
        .section-title { font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; padding-bottom: 6px; border-bottom: 1px solid #e2e8f0; }
        .patient-info { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .patient-info-item .label { font-size: 11px; color: #94a3b8; text-transform: uppercase; }
        .patient-info-item .value { font-size: 14px; font-weight: 600; }
        .rx-symbol { font-size: 48px; font-weight: 900; color: #1d4ed8; line-height: 1; margin-bottom: 16px; font-style: italic; }
        .diagnosis-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; margin-bottom: 20px; font-size: 14px; color: #374151; }
        .med-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .med-table th { background: #1d4ed8; color: #fff; padding: 10px 12px; text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        .med-table th:first-child { border-radius: 6px 0 0 6px; }
        .med-table th:last-child { border-radius: 0 6px 6px 0; }
        .med-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; font-size: 13px; vertical-align: top; }
        .med-table tr:nth-child(even) td { background: #f8fafc; }
        .med-table tr:last-child td { border-bottom: none; }
        .med-number { width: 28px; height: 28px; background: #dbeafe; color: #1d4ed8; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; }
        .rx-footer { padding: 20px 32px; border-top: 2px solid #e2e8f0; display: flex; justify-content: space-between; align-items: flex-end; }
        .signature-area { text-align: center; min-width: 200px; }
        .signature-line { border-top: 1px solid #1e293b; padding-top: 6px; font-size: 13px; font-weight: 600; }
        .validity { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 12px 16px; font-size: 13px; color: #166534; }
        .watermark { position: fixed; top: 50%; left: 50%; transform: translate(-50%,-50%) rotate(-30deg); font-size: 80px; font-weight: 900; color: rgba(29,78,216,0.05); white-space: nowrap; pointer-events: none; z-index: 0; }
        .actions { position: fixed; top: 20px; right: 20px; display: flex; gap: 8px; }
        .btn-print { background: #1d4ed8; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; }
        .btn-back { background: #fff; color: #64748b; border: 1px solid #e2e8f0; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-block; }
        @media print {
            body { background: #fff; padding: 0; }
            .actions, .watermark { display: none; }
            .print-page { box-shadow: none; border-radius: 0; max-width: 100%; }
        }
    </style>
</head>
<body>
<div class="actions no-print">
    <a href="javascript:history.back()" class="btn-back">← Back</a>
    <button onclick="window.print()" class="btn-print">🖨️ Print</button>
</div>

<div class="watermark">ClinicCare</div>

<div class="print-page">
    <!-- Header -->
    <div class="rx-header">
        <div class="clinic-info">
            <h1>🏥 ClinicCare</h1>
            <p><?= SITE_ADDRESS ?></p>
            <p>📞 <?= SITE_PHONE ?> | ✉️ <?= SITE_EMAIL ?></p>
        </div>
        <div class="doctor-info">
            <h2>Dr. <?= htmlspecialchars($rx['doctor_name']) ?></h2>
            <p><?= htmlspecialchars($rx['specialization']) ?></p>
            <p>License: <?= htmlspecialchars($rx['license_number']) ?></p>
            <p><?= htmlspecialchars($rx['doctor_phone'] ?? '') ?></p>
        </div>
    </div>

    <!-- Meta -->
    <div class="rx-meta">
        <div class="rx-meta-item">
            <div class="label">Rx Number</div>
            <div class="value" style="font-family:monospace;color:#1d4ed8;"><?= htmlspecialchars($rx['prescription_number']) ?></div>
        </div>
        <div class="rx-meta-item">
            <div class="label">Issue Date</div>
            <div class="value"><?= formatDate($rx['issue_date']) ?></div>
        </div>
        <div class="rx-meta-item">
            <div class="label">Valid Until</div>
            <div class="value" style="color:<?= $rx['valid_until']&&strtotime($rx['valid_until'])<time()?'#dc2626':'#16a34a' ?>">
                <?= $rx['valid_until'] ? formatDate($rx['valid_until']) : 'Open' ?>
            </div>
        </div>
        <div class="rx-meta-item">
            <div class="label">Status</div>
            <div class="value" style="text-transform:capitalize;"><?= htmlspecialchars($rx['status']) ?></div>
        </div>
    </div>

    <div class="rx-body">
        <!-- Patient Info -->
        <div class="section-title">Patient Information</div>
        <div class="patient-info" style="margin-bottom:24px;">
            <div class="patient-info-item">
                <div class="label">Full Name</div>
                <div class="value"><?= htmlspecialchars($rx['patient_name']) ?></div>
            </div>
            <div class="patient-info-item">
                <div class="label">Age / Gender</div>
                <div class="value"><?= $age ? $age.' yrs' : '—' ?><?= $rx['gender'] ? ' / '.ucfirst($rx['gender']) : '' ?></div>
            </div>
            <div class="patient-info-item">
                <div class="label">Blood Type</div>
                <div class="value"><?= htmlspecialchars($rx['blood_type'] ?: '—') ?></div>
            </div>
            <div class="patient-info-item">
                <div class="label">Allergies</div>
                <div class="value" style="color:<?= $rx['allergies']?'#dc2626':'inherit' ?>">
                    <?= htmlspecialchars($rx['allergies'] ?: 'None known') ?>
                </div>
            </div>
        </div>

        <!-- Diagnosis -->
        <?php if ($rx['notes']): ?>
        <div class="section-title">Diagnosis / Notes</div>
        <div class="diagnosis-box"><?= nl2br(htmlspecialchars($rx['notes'])) ?></div>
        <?php endif; ?>

        <!-- Rx Symbol and Medications -->
        <div class="rx-symbol">℞</div>

        <div class="section-title">Prescribed Medications</div>
        <?php if (empty($items)): ?>
        <p style="color:#94a3b8;font-style:italic;">No medications listed.</p>
        <?php else: ?>
        <table class="med-table">
            <thead>
                <tr>
                    <th style="width:36px;">#</th>
                    <th>Medication</th>
                    <th>Dosage</th>
                    <th>Frequency</th>
                    <th>Duration</th>
                    <th>Qty</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $i => $item): ?>
                <tr>
                    <td><div class="med-number"><?= $i+1 ?></div></td>
                    <td>
                        <div style="font-weight:600;"><?= htmlspecialchars($item['medication_name']) ?></div>
                        <?php if ($item['instructions']): ?>
                        <div style="font-size:12px;color:#64748b;margin-top:2px;font-style:italic;"><?= htmlspecialchars($item['instructions']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($item['dosage'] ?: '—') ?></td>
                    <td><?= htmlspecialchars($item['frequency'] ?: '—') ?></td>
                    <td><?= htmlspecialchars($item['duration'] ?: '—') ?></td>
                    <td style="font-weight:600;"><?= $item['quantity'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="rx-footer">
        <div class="validity">
            <?php if ($rx['valid_until']): ?>
                <?php if (strtotime($rx['valid_until']) >= time()): ?>
                    ✅ Valid until <?= formatDate($rx['valid_until']) ?>
                <?php else: ?>
                    ⚠️ This prescription expired on <?= formatDate($rx['valid_until']) ?>
                <?php endif; ?>
            <?php else: ?>
                ℹ️ No expiration date set
            <?php endif; ?>
        </div>
        <div class="signature-area">
            <div style="height:48px;"></div>
            <div class="signature-line">Dr. <?= htmlspecialchars($rx['doctor_name']) ?></div>
            <div style="font-size:12px;color:#94a3b8;"><?= htmlspecialchars($rx['specialization']) ?></div>
            <div style="font-size:12px;color:#94a3b8;">License No. <?= htmlspecialchars($rx['license_number']) ?></div>
        </div>
    </div>

    <div style="padding:12px 32px;background:#f8fafc;text-align:center;font-size:11px;color:#94a3b8;border-top:1px solid #e2e8f0;">
        This prescription was generated by ClinicCare Health Management System on <?= date('F j, Y \a\t h:i A') ?>.
        This document is confidential and intended only for the named patient.
    </div>
</div>
</body>
</html>