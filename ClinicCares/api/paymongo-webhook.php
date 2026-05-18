<?php
/**
 * PayMongo Webhook Handler
 * 
 * Receives server-to-server events from PayMongo.
 * Register this URL in your PayMongo dashboard:
 *   https://yourdomain.com/api/paymongo-webhook.php
 * 
 * Events handled:
 *   payment.paid   → mark billing as paid
 *   link.payment.paid → (same, via payment link)
 */

require_once __DIR__ . '/../includes/config.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$rawBody   = file_get_contents('php://input');
$signature = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';

// ── Verify webhook signature ───────────────────────────────────────────────────
$webhookKey = defined('PAYMONGO_WEBHOOK_KEY') ? PAYMONGO_WEBHOOK_KEY : '';

if ($webhookKey && $signature) {
    // PayMongo sends: t=<timestamp>,te=<test_sig>,li=<live_sig>
    $parts     = [];
    foreach (explode(',', $signature) as $part) {
        [$k, $v]    = explode('=', $part, 2);
        $parts[$k] = $v;
    }
    $timestamp    = $parts['t']  ?? '';
    $testSig      = $parts['te'] ?? '';
    $liveSig      = $parts['li'] ?? '';

    $payload      = $timestamp . '.' . $rawBody;
    $computedSig  = hash_hmac('sha256', $payload, $webhookKey);

    if ($computedSig !== $testSig && $computedSig !== $liveSig) {
        http_response_code(401);
        exit('Invalid signature');
    }
}

$event = json_decode($rawBody, true);
if (!$event || empty($event['data'])) {
    http_response_code(400);
    exit('Bad payload');
}

$eventType = $event['data']['attributes']['type'] ?? '';
$resource  = $event['data']['attributes']['data'] ?? [];
$attrs     = $resource['attributes'] ?? [];

// Log webhook for debugging
db()->insert(
    "INSERT INTO paymongo_webhook_logs (event_type, payload, received_at) VALUES (?,?,NOW())",
    [$eventType, $rawBody]
);

// ── Handle payment.paid ────────────────────────────────────────────────────────
if (in_array($eventType, ['payment.paid', 'link.payment.paid'])) {
    $pmPaymentId  = $resource['id'] ?? '';
    $referenceNum = $attrs['external_reference_number']
                 ?? $attrs['billing']['name']
                 ?? '';

    // Try to match via reference_number stored in paymongo_transactions
    $txn = null;
    if ($referenceNum) {
        $txn = db()->fetchOne(
            "SELECT * FROM paymongo_transactions WHERE reference_number=? ORDER BY created_at DESC LIMIT 1",
            [$referenceNum]
        );
    }
    // Fallback: match via paymongo_link_id from the payment's source
    if (!$txn) {
        $linkId = $attrs['source']['id'] ?? '';
        if ($linkId) {
            $txn = db()->fetchOne(
                "SELECT * FROM paymongo_transactions WHERE paymongo_link_id=? ORDER BY created_at DESC LIMIT 1",
                [$linkId]
            );
        }
    }

    if ($txn && $txn['status'] !== 'paid') {
        $billingId = $txn['billing_id'];
        $bill      = db()->fetchOne("SELECT * FROM billing WHERE id=?", [$billingId]);

        if ($bill && $bill['status'] !== 'paid') {
            $paidAmount = ($attrs['amount'] ?? ($txn['amount'] * 100)) / 100;
            $newPaid    = $bill['amount_paid'] + $paidAmount;
            $newBalance = max(0, $bill['total'] - $newPaid);
            $newStatus  = $newBalance <= 0 ? 'paid' : 'partial';

            db()->execute(
                "UPDATE billing SET amount_paid=?,balance=?,status=?,payment_method='paymongo',payment_reference=?,payment_date=NOW() WHERE id=?",
                [$newPaid, $newBalance, $newStatus, $txn['reference_number'] ?: $pmPaymentId, $billingId]
            );

            db()->execute(
                "UPDATE paymongo_transactions SET status='paid', paymongo_payment_id=?, paid_at=NOW(), updated_at=NOW() WHERE id=?",
                [$pmPaymentId, $txn['id']]
            );

            // Notify patient
            $patientUser = db()->fetchOne(
                "SELECT u.id as user_id FROM billing b JOIN patients p ON b.patient_id=p.id JOIN users u ON p.user_id=u.id WHERE b.id=?",
                [$billingId]
            );
            if ($patientUser) {
                createNotification(
                    $patientUser['user_id'],
                    'Payment Confirmed',
                    'Your online payment of ₱' . number_format($paidAmount, 2) . ' for Invoice ' . $bill['invoice_number'] . ' has been confirmed.',
                    'billing',
                    '/patient/billing.php'
                );
            }
        }
    }
}

http_response_code(200);
echo json_encode(['received' => true]);
