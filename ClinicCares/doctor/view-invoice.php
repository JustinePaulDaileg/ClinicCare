<?php
require_once __DIR__ . '/../includes/session.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
$bill = db()->fetchOne("
    SELECT b.*,
           CONCAT(up.first_name,' ',up.last_name) as patient_name,
           up.email as patient_email, up.phone as patient_phone,
           pat.address, pat.city,
           CONCAT(ud.first_name,' ',ud.last_name) as doctor_name,
           d.specialization
    FROM billing b
    JOIN patients pat ON b.patient_id=pat.id JOIN users up ON pat.user_id=up.id
    LEFT JOIN doctors d ON b.doctor_id=d.id LEFT JOIN users ud ON d.user_id=ud.id
    WHERE b.id=?", [$id]);

if (!$bill) die('<div style="padding:40px;text-align:center;"><h2>Invoice not found</h2></div>');

// Access control
if ($_SESSION['role'] === 'patient') {
    $pt = db()->fetchOne("SELECT id FROM patients WHERE user_id=?", [$_SESSION['user_id']]);
    if (!$pt || $pt['id'] != $bill['patient_id']) die('<div style="padding:40px;text-align:center;"><h2>Access denied</h2></div>');
}

$items = db()->fetchAll("SELECT * FROM billing_items WHERE billing_id=?", [$id]);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice <?= htmlspecialchars($bill['invoice_number']) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f4f8; padding: 20px; color: #1e293b; font-size: 14px; }
        .invoice { max-width: 720px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.1); }
        .inv-header { background: linear-gradient(135deg, #1e40af, #1d4ed8); color: #fff; padding: 30px 32px; }
        .inv-header-row { display: flex; justify-content: space-between; align-items: flex-start; }
        .clinic-name { font-size: 24px; font-weight: 800; margin-bottom: 4px; }
        .invoice-label { font-size: 28px; font-weight: 900; opacity: 0.3; letter-spacing: 2px; }
        .inv-number { font-size: 18px; font-weight: 700; }
        .inv-status { display: inline-block; padding: 4px 14px; border-radius: 20px; font-size: 13px; font-weight: 700; margin-top: 6px; text-transform: uppercase; }
        .status-paid { background: #dcfce7; color: #15803d; }
        .status-pending { background: #fef9c3; color: #a16207; }
        .status-partial { background: #fed7aa; color: #c2410c; }
        .status-cancelled { background: #f1f5f9; color: #64748b; }
        .inv-parties { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; padding: 24px 32px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
        .party-label { font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 6px; }
        .party-name { font-size: 16px; font-weight: 700; }
        .party-detail { font-size: 13px; color: #64748b; margin-top: 2px; }
        .inv-body { padding: 24px 32px; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items-table th { background: #1d4ed8; color: #fff; padding: 10px 12px; text-align: left; font-size: 12px; text-transform: uppercase; }
        .items-table th:first-child { border-radius: 6px 0 0 6px; }
        .items-table th:last-child { border-radius: 0 6px 6px 0; text-align: right; }
        .items-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        .items-table td:last-child { text-align: right; font-weight: 600; }
        .items-table tr:last-child td { border-bottom: none; }
        .totals { display: flex; justify-content: flex-end; }
        .totals-box { width: 280px; }
        .total-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; }
        .total-row.grand { font-size: 18px; font-weight: 800; border-top: 2px solid #1d4ed8; padding-top: 10px; margin-top: 4px; color: #1d4ed8; }
        .total-row.paid-row { color: #16a34a; font-weight: 600; }
        .total-row.balance-row { color: #dc2626; font-weight: 700; }
        .payment-info { margin-top: 20px; padding: 14px 16px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; font-size: 13px; color: #166534; }
        .inv-footer { padding: 16px 32px; background: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center; font-size: 12px; color: #94a3b8; }
        .actions { position: fixed; top: 20px; right: 20px; display: flex; gap: 8px; }
        .btn-p { background: #1d4ed8; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; }
        .btn-b { background: #fff; color: #64748b; border: 1px solid #e2e8f0; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-block; }
        @media print { .actions { display: none; } body { background: #fff; padding: 0; } .invoice { box-shadow: none; border-radius: 0; max-width: 100%; } }
    </style>
</head>
<body>
<div class="actions">
    <a href="javascript:history.back()" class="btn-b">← Back</a>
    <button onclick="window.print()" class="btn-p">🖨️ Print</button>
</div>

<div class="invoice">
    <div class="inv-header">
        <div class="inv-header-row">
            <div>
                <div class="clinic-name">🏥 ClinicCare</div>
                <div style="font-size:13px;opacity:0.75;"><?= SITE_ADDRESS ?></div>
                <div style="font-size:13px;opacity:0.75;"><?= SITE_PHONE ?></div>
            </div>
            <div style="text-align:right;">
                <div class="invoice-label">INVOICE</div>
                <div class="inv-number"><?= htmlspecialchars($bill['invoice_number']) ?></div>
                <div class="inv-status status-<?= $bill['status'] ?>"><?= ucfirst($bill['status']) ?></div>
            </div>
        </div>
    </div>

    <div class="inv-parties">
        <div>
            <div class="party-label">Bill To</div>
            <div class="party-name"><?= htmlspecialchars($bill['patient_name']) ?></div>
            <div class="party-detail">📧 <?= htmlspecialchars($bill['patient_email'] ?? '') ?></div>
            <div class="party-detail">📞 <?= htmlspecialchars($bill['patient_phone'] ?? '') ?></div>
            <?php if ($bill['address']): ?>
            <div class="party-detail">📍 <?= htmlspecialchars($bill['address']) ?><?= $bill['city']?', '.htmlspecialchars($bill['city']):'' ?></div>
            <?php endif; ?>
        </div>
        <div>
            <div class="party-label">Invoice Details</div>
            <div class="party-detail"><strong>Date Issued:</strong> <?= formatDate($bill['created_at']) ?></div>
            <?php if ($bill['due_date']): ?>
            <div class="party-detail"><strong>Due Date:</strong> <?= formatDate($bill['due_date']) ?></div>
            <?php endif; ?>
            <?php if ($bill['doctor_name']): ?>
            <div class="party-detail"><strong>Doctor:</strong> Dr. <?= htmlspecialchars($bill['doctor_name']) ?></div>
            <div class="party-detail"><strong>Dept:</strong> <?= htmlspecialchars($bill['specialization'] ?? '') ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="inv-body">
        <table class="items-table">
            <thead>
                <tr><th>Description</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['description']) ?></td>
                    <td><?= $item['quantity'] ?></td>
                    <td><?= formatCurrency($item['unit_price']) ?></td>
                    <td><?= formatCurrency($item['total']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($items)): ?>
                <tr><td colspan="4" style="color:#94a3b8;text-align:center;padding:20px;">No line items</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="totals">
            <div class="totals-box">
                <div class="total-row"><span>Subtotal</span><span><?= formatCurrency($bill['subtotal']) ?></span></div>
                <?php if ($bill['discount'] > 0): ?>
                <div class="total-row" style="color:#16a34a;"><span>Discount</span><span>-<?= formatCurrency($bill['discount']) ?></span></div>
                <?php endif; ?>
                <?php if ($bill['tax'] > 0): ?>
                <div class="total-row"><span>Tax</span><span><?= formatCurrency($bill['tax']) ?></span></div>
                <?php endif; ?>
                <div class="total-row grand"><span>Total</span><span><?= formatCurrency($bill['total']) ?></span></div>
                <?php if ($bill['amount_paid'] > 0): ?>
                <div class="total-row paid-row"><span>Amount Paid</span><span>-<?= formatCurrency($bill['amount_paid']) ?></span></div>
                <?php endif; ?>
                <?php if ($bill['balance'] > 0): ?>
                <div class="total-row balance-row"><span>Balance Due</span><span><?= formatCurrency($bill['balance']) ?></span></div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($bill['payment_method'] && $bill['status'] === 'paid'): ?>
        <div class="payment-info">
            ✅ <strong>Payment Received</strong> — <?= ucfirst(str_replace('_',' ',$bill['payment_method'])) ?> on <?= formatDate($bill['payment_date']) ?>
            <?php if ($bill['payment_reference']): ?> · Ref: <?= htmlspecialchars($bill['payment_reference']) ?><?php endif; ?>
        </div>
        <?php elseif (in_array($bill['status'],['pending','partial'])): ?>
        <div style="padding:14px 16px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;font-size:13px;color:#9a3412;margin-top:16px;">
            ⚠️ <strong>Payment Due</strong> — Balance of <?= formatCurrency($bill['balance']) ?> is due by <?= $bill['due_date']?formatDate($bill['due_date']):'ASAP' ?>.
            Please pay at the clinic cashier or contact us.
        </div>
        <?php endif; ?>

        <?php if ($bill['notes']): ?>
        <div style="margin-top:16px;padding:12px;background:#f8fafc;border-radius:8px;font-size:13px;color:#64748b;">
            📝 <strong>Notes:</strong> <?= nl2br(htmlspecialchars($bill['notes'])) ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="inv-footer">
        Thank you for trusting ClinicCare for your healthcare needs.<br>
        For inquiries, contact <?= SITE_EMAIL ?> | <?= SITE_PHONE ?><br>
        Generated on <?= date('F j, Y \a\t h:i A') ?>
    </div>
</div>
</body>
</html>