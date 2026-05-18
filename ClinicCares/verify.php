<?php
// verify.php
require_once __DIR__ . '/includes/session.php';
$token = sanitize($_GET['token'] ?? '');
$msg = ''; $type = 'danger';
if ($token) {
    $user = db()->fetchOne("SELECT id FROM users WHERE verification_token = ? AND email_verified = 0", [$token]);
    if ($user) {
        db()->execute("UPDATE users SET email_verified=1, is_active=1, verification_token=NULL WHERE id=?", [$user['id']]);
        $msg = 'Email verified! You can now log in.'; $type = 'success';
    } else { $msg = 'Invalid or expired verification link.'; }
} else { $msg = 'No token provided.'; }
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Verify — ClinicCare</title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/main.css"></head>
<body style="display:flex;align-items:center;justify-content:center;min-height:100vh;background:var(--bg);">
<div style="text-align:center;max-width:400px;padding:40px;">
  <div style="font-size:60px;margin-bottom:16px;"><?= $type==='success'?'✅':'❌' ?></div>
  <h2 style="font-family:var(--font-display);margin-bottom:12px;">Email Verification</h2>
  <div class="alert alert-<?= $type ?>"><?= htmlspecialchars($msg) ?></div>
  <a href="<?= SITE_URL ?>/index.php" class="btn btn-primary">Go to Login</a>
</div>
</body></html>