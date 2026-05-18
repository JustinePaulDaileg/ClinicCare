<?php
/**
 * PayMongo API Integration Handler
 * Handles payment link creation, status checks, and webhook verification
 * 
 * Endpoints:
 *   POST action=create_payment  → creates a PayMongo payment link
 *   POST action=check_status    → polls payment status by billing_id
 *   POST action=cancel_payment  → cancels a pending payment link
 */

require_once __DIR__ . '/../includes/session.php';
requireLogin();

header('Content-Type: application/json');

// ── PayMongo credentials (set in includes/config.php) ─────────────────────────
$secretKey  = defined('PAYMONGO_SECRET_KEY')  ? PAYMONGO_SECRET_KEY  : '';
$publicKey  = defined('PAYMONGO_PUBLIC_KEY')  ? PAYMONGO_PUBLIC_KEY  : '';
$webhookKey = defined('PAYMONGO_WEBHOOK_KEY') ? PAYMONGO_WEBHOOK_KEY : '';

if (empty($secretKey)) {
    echo json_encode(['success' => false, 'error' => 'PayMongo is not configured. Add your keys to includes/config.php.']);
    exit;
}

$action = $_POST['action'] ?? '';

// ── Helper: call PayMongo REST API ─────────────────────────────────────────────
function paymongoRequest(string $method, string $endpoint, array $body = []): array {
    global $secretKey;

    $ch = curl_init('https://api.paymongo.com/v1' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($secretKey . ':'),
        ],
    ]);
    if ($body) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['data' => ['attributes' => $body]]));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => ['message' => 'Invalid JSON response from PayMongo']];
    }
    return $decoded;
}

// ══════════════════════════════════════════════════════════════════════════════
// ACTION: create_payment
// Creates a PayMongo payment link for a billing invoice
// ══════════════════════════════════════════════════════════════════════════════
if ($action === 'create_payment') {
    $billingId = (int)($_POST['billing_id'] ?? 0);
    if (!$billingId) {
        echo json_encode(['success' => false, 'error' => 'Invalid billing ID.']);
        exit;
    }

    // Fetch billing record (restrict patients to their own)
    if ($_SESSION['role'] === 'patient') {
        $patient = db()->fetchOne("SELECT p.id FROM patients p WHERE p.user_id=?", [$_SESSION['user_id']]);
        $bill = db()->fetchOne(
            "SELECT b.*, CONCAT(up.first_name,' ',up.last_name) as patient_name
             FROM billing b
             JOIN patients p ON b.patient_id=p.id
             JOIN users up ON p.user_id=up.id
             WHERE b.id=? AND b.patient_id=?",
            [$billingId, $patient['id']]
        );
    } else {
        $bill = db()->fetchOne(
            "SELECT b.*, CONCAT(up.first_name,' ',up.last_name) as patient_name
             FROM billing b
             JOIN patients p ON b.patient_id=p.id
             JOIN users up ON p.user_id=up.id
             WHERE b.id=?",
            [$billingId]
        );
    }

    if (!$bill) {
        echo json_encode(['success' => false, 'error' => 'Invoice not found.']);
        exit;
    }
    if ($bill['status'] === 'paid') {
        echo json_encode(['success' => false, 'error' => 'This invoice is already fully paid.']);
        exit;
    }
    if ($bill['balance'] <= 0) {
        echo json_encode(['success' => false, 'error' => 'No outstanding balance.']);
        exit;
    }

    // Check for an existing active payment link for this invoice
    $existing = db()->fetchOne(
        "SELECT * FROM paymongo_transactions WHERE billing_id=? AND status IN ('pending','awaiting_payment_method') ORDER BY created_at DESC LIMIT 1",
        [$billingId]
    );
    if ($existing && $existing['checkout_url']) {
        echo json_encode([
            'success'      => true,
            'checkout_url' => $existing['checkout_url'],
            'link_id'      => $existing['paymongo_link_id'],
            'reused'       => true,
        ]);
        exit;
    }

    // Amount in centavos (PayMongo requires integers)
    $amountCentavos = (int)round($bill['balance'] * 100);

    $description = 'ClinicCare Invoice ' . $bill['invoice_number'] . ' – ' . $bill['patient_name'];

    // Build return URLs (adjust SITE_URL accordingly)
    $returnUrl = rtrim(SITE_URL, '/') . '/patient/billing.php?paymongo_status=success&billing_id=' . $billingId;
    $cancelUrl = rtrim(SITE_URL, '/') . '/patient/billing.php?paymongo_status=cancelled&billing_id=' . $billingId;

    // Create PayMongo Payment Link
    $result = paymongoRequest('POST', '/links', [
        'amount'      => $amountCentavos,
        'currency'    => 'PHP',
        'description' => $description,
        'remarks'     => 'Invoice: ' . $bill['invoice_number'],
    ]);

    if (isset($result['errors']) || isset($result['error'])) {
        $errMsg = $result['errors'][0]['detail'] ?? $result['error']['message'] ?? 'PayMongo API error.';
        echo json_encode(['success' => false, 'error' => $errMsg]);
        exit;
    }

    $linkData     = $result['data'] ?? [];
    $linkId       = $linkData['id'] ?? '';
    $checkoutUrl  = $linkData['attributes']['checkout_url'] ?? '';
    $referenceNum = $linkData['attributes']['reference_number'] ?? '';

    if (!$checkoutUrl) {
        echo json_encode(['success' => false, 'error' => 'Failed to generate checkout URL.']);
        exit;
    }

    // Store transaction record
    db()->insert(
        "INSERT INTO paymongo_transactions
            (billing_id, paymongo_link_id, reference_number, amount, currency, status, checkout_url, description, created_by)
         VALUES (?,?,?,?,?,?,?,?,?)",
        [
            $billingId,
            $linkId,
            $referenceNum,
            $bill['balance'],
            'PHP',
            'pending',
            $checkoutUrl,
            $description,
            $_SESSION['user_id'],
        ]
    );

    echo json_encode([
        'success'      => true,
        'checkout_url' => $checkoutUrl,
        'link_id'      => $linkId,
        'reference'    => $referenceNum,
    ]);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// ACTION: check_status
// Polls PayMongo for latest payment link status and updates billing if paid
// ══════════════════════════════════════════════════════════════════════════════
if ($action === 'check_status') {
    $billingId = (int)($_POST['billing_id'] ?? 0);
    if (!$billingId) {
        echo json_encode(['success' => false, 'error' => 'Invalid billing ID.']);
        exit;
    }

    $txn = db()->fetchOne(
        "SELECT * FROM paymongo_transactions WHERE billing_id=? ORDER BY created_at DESC LIMIT 1",
        [$billingId]
    );

    if (!$txn) {
        echo json_encode(['success' => false, 'error' => 'No PayMongo transaction found for this invoice.']);
        exit;
    }

    // Fetch live status from PayMongo
    $result = paymongoRequest('GET', '/links/' . $txn['paymongo_link_id']);

    if (isset($result['errors']) || isset($result['error'])) {
        echo json_encode(['success' => false, 'error' => 'Could not fetch payment status.']);
        exit;
    }

    $attrs      = $result['data']['attributes'] ?? [];
    $pmStatus   = $attrs['status'] ?? $txn['status'];
    $payments   = $attrs['payments'] ?? [];

    // Map PayMongo statuses
    $isPaid = in_array($pmStatus, ['paid']);

    // Update transaction status
    db()->execute(
        "UPDATE paymongo_transactions SET status=?, updated_at=NOW() WHERE id=?",
        [$pmStatus, $txn['id']]
    );

    if ($isPaid) {
        // Find payment reference from PayMongo payments array
        $pmRef = '';
        if (!empty($payments)) {
            $pmRef = $payments[0]['id'] ?? '';
        }

        // Update billing record
        $bill = db()->fetchOne("SELECT * FROM billing WHERE id=?", [$billingId]);
        if ($bill && $bill['status'] !== 'paid') {
            $newPaid    = $bill['amount_paid'] + $txn['amount'];
            $newBalance = max(0, $bill['total'] - $newPaid);
            $newStatus  = $newBalance <= 0 ? 'paid' : 'partial';

            db()->execute(
                "UPDATE billing SET amount_paid=?,balance=?,status=?,payment_method='paymongo',payment_reference=?,payment_date=NOW() WHERE id=?",
                [$newPaid, $newBalance, $newStatus, $txn['reference_number'] ?: $pmRef, $billingId]
            );

            // Mark transaction as processed
            db()->execute(
                "UPDATE paymongo_transactions SET status='paid', paid_at=NOW() WHERE id=?",
                [$txn['id']]
            );

            // Notify patient
            $patientUser = db()->fetchOne(
                "SELECT u.id as user_id FROM billing b JOIN patients p ON b.patient_id=p.id JOIN users u ON p.user_id=u.id WHERE b.id=?",
                [$billingId]
            );
            if ($patientUser) {
                createNotification(
                    $patientUser['user_id'],
                    'Payment Successful',
                    'Your PayMongo payment of ' . formatCurrency($txn['amount']) . ' was confirmed. Invoice ' . $bill['invoice_number'] . ' is now ' . $newStatus . '.',
                    'billing',
                    '/patient/billing.php'
                );
            }
        }

        echo json_encode(['success' => true, 'payment_status' => 'paid', 'pm_status' => $pmStatus]);
    } else {
        echo json_encode(['success' => true, 'payment_status' => $pmStatus, 'pm_status' => $pmStatus]);
    }
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// ACTION: cancel_payment
// Cancels/expires an active PayMongo payment link
// ══════════════════════════════════════════════════════════════════════════════
if ($action === 'cancel_payment') {
    $billingId = (int)($_POST['billing_id'] ?? 0);
    $txn = db()->fetchOne(
        "SELECT * FROM paymongo_transactions WHERE billing_id=? AND status='pending' ORDER BY created_at DESC LIMIT 1",
        [$billingId]
    );

    if (!$txn) {
        echo json_encode(['success' => false, 'error' => 'No pending PayMongo transaction found.']);
        exit;
    }

    // PayMongo links can be deactivated
    $result = paymongoRequest('POST', '/links/' . $txn['paymongo_link_id'] . '/deactivate', []);

    db()->execute(
        "UPDATE paymongo_transactions SET status='cancelled', updated_at=NOW() WHERE id=?",
        [$txn['id']]
    );

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action.']);
