<?php
require_once __DIR__ . '/includes/session.php';

// Must have a pending 2FA session (not fully logged in yet)
if (empty($_SESSION['2fa_pending']) || empty($_SESSION['2fa_user_id'])) {
    redirect(SITE_URL . '/index.php');
}

require_once __DIR__ . '/vendor/PHPGangsta/GoogleAuthenticator.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['totp_code'] ?? '');
    $userId = (int)$_SESSION['2fa_user_id'];

    $user = db()->fetchOne("SELECT * FROM users WHERE id = ? AND is_active = 1", [$userId]);

    if (!$user || !$user['totp_secret']) {
        $error = 'Invalid session. Please log in again.';
    } else {
        $ga = new PHPGangsta_GoogleAuthenticator();
        if ($ga->verifyCode($user['totp_secret'], $code, 1)) {
            // 2FA passed — complete login
            unset($_SESSION['2fa_pending'], $_SESSION['2fa_user_id']);

            session_regenerate_id(true);
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['role']          = $user['role'];
            $_SESSION['name']          = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['email']         = $user['email'];
            $_SESSION['last_activity'] = time();

            logActivity($user['id'], 'LOGIN_2FA', 'User completed 2FA login');
            redirect(SITE_URL . '/' . $user['role'] . '/dashboard.php');
        } else {
            $error = 'Invalid or expired code. Please try again.';
            logActivity($userId, 'LOGIN_2FA_FAILED', '2FA verification failed');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Two-Factor Authentication — ClinicCare</title>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/main.css">
  <link rel="icon" type="image/png" href="/cliniccares/assets/img/ClinicCares.png">
</head>
<body>
<div class="auth-page">
  <div class="auth-left">
    <div>
      <div class="auth-logo">ClinicCare</div>
      <p class="auth-tagline">Your account is protected with two-factor authentication.</p>
      <div class="auth-features">
        <div class="auth-feature">
          <div class="auth-feature-icon">🔐</div>
          <span>Open Google Authenticator on your phone</span>
        </div>
        <div class="auth-feature">
          <div class="auth-feature-icon">🔢</div>
          <span>Enter the 6-digit code shown for ClinicCare</span>
        </div>
        <div class="auth-feature">
          <div class="auth-feature-icon">⏱️</div>
          <span>Codes refresh every 30 seconds</span>
        </div>
      </div>
    </div>
  </div>
  <div class="auth-right">
    <div class="auth-card">
      <div style="text-align:center;margin-bottom:28px;">
        <img src="assets/img/ClinicCares.png" alt="ClinicCare Logo" style="width:80px;">
        <h2>Two-Factor Authentication</h2>
        <p class="subtitle">Enter the 6-digit code from your Google Authenticator app</p>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger">⚠️ <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="form-group">
          <label class="form-label">Authentication Code</label>
          <div class="input-group-icon">
            <span class="icon">🔢</span>
            <input type="text" name="totp_code" class="form-control"
                   placeholder="000000" maxlength="6" pattern="\d{6}"
                   inputmode="numeric" autocomplete="one-time-code"
                   autofocus required
                   style="font-size:24px;letter-spacing:8px;text-align:center;padding-left:12px;">
          </div>
          <div class="form-text" style="text-align:center;">Open Google Authenticator and enter the 6-digit code</div>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:8px;">
          ✅ Verify Code
        </button>
      </form>

      <div style="margin-top:20px;text-align:center;">
        <a href="<?= SITE_URL ?>/index.php" style="color:var(--text-muted);font-size:13px;">← Back to Login</a>
      </div>
    </div>
  </div>
</div>
</body>
</html>
