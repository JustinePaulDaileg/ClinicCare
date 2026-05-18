<?php
require_once __DIR__ . '/includes/session.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(SITE_URL . '/' . $_SESSION['role'] . '/dashboard.php');
}

$error = '';
$msg = sanitize($_GET['msg'] ?? '');
$errMsg = sanitize($_GET['error'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } else {
        $user = db()->fetchOne("SELECT * FROM users WHERE email = ? AND is_active = 1", [$email]);
        

        if (!$user || !verifyPassword($password, $user['password'])) {
            $error = 'Invalid email or password.';
            logActivity(null, 'LOGIN_FAILED', "Failed login attempt for: $email", $_SERVER);
        } elseif (!$user['email_verified']) {
            $error = 'Please verify your email before logging in.';
        } elseif (!empty($user['two_factor_enabled']) && !empty($user['totp_secret'])) {
            // 2FA is enabled — set pending session and redirect to verify
            session_regenerate_id(true);
            $_SESSION['2fa_pending'] = true;
            $_SESSION['2fa_user_id'] = $user['id'];
            logActivity($user['id'], 'LOGIN_2FA_PENDING', '2FA challenge started');
            redirect(SITE_URL . '/2fa-verify.php');
        } else {
            // Successful login (no 2FA)
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['name']      = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['email']     = $user['email'];
            $_SESSION['last_activity'] = time();

            logActivity($user['id'], 'LOGIN', 'User logged in successfully');
            redirect(SITE_URL . '/' . $user['role'] . '/dashboard.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — ClinicCare</title>
  <link rel="stylesheet" href="/cliniccares/assets/css/main.css">
  <link rel="icon" type="image/png" href="/cliniccares/assets/img/ClinicCares.png">
</head>
<body>
<div class="auth-page">
  <!-- Left Panel -->
  <div class="auth-left">
    <div>
      <div class="auth-logo">ClinicCare</div>
      <p class="auth-tagline">Your complete online health records & appointment management system.</p>
      <div class="auth-features">
        <div class="auth-feature">
          <div class="auth-feature-icon">📅</div>
          <span>Book and manage appointments easily</span>
        </div>
        <div class="auth-feature">
          <div class="auth-feature-icon">📋</div>
          <span>Access your complete medical history</span>
        </div>
        <div class="auth-feature">
          <div class="auth-feature-icon">💊</div>
          <span>View and print prescriptions anytime</span>
        </div>
        <div class="auth-feature">
          <div class="auth-feature-icon">💳</div>
          <span>Track billing and payment status</span>
        </div>
        <div class="auth-feature">
          <div class="auth-feature-icon">🔒</div>
          <span>Secure, HIPAA-compliant health data</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Right Panel -->
  <div class="auth-right">
    <div class="auth-card">
        <div style="text-align:center;margin-bottom:28px;">
        
        <img src="/cliniccares/assets/img/ClinicCares.png" alt="ClinicCare Logo" style="width:80px;">
        
        <h2>Welcome back</h2>
        <p class="subtitle">Sign in to your ClinicCare account</p>
      </div>

      <?php if ($msg): ?>
        <div class="alert alert-info">ℹ️ <?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>
      <?php if ($errMsg): ?>
        <div class="alert alert-danger">⚠️ <?= htmlspecialchars($errMsg) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger">⚠️ <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" id="loginForm" onsubmit="return validateForm('loginForm')">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">

        <div class="form-group">
          <label class="form-label">Email Address</label>
          <div class="input-group-icon">
            <span class="icon">✉️</span>
            <input type="email" name="email" class="form-control" placeholder="you@example.com"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autocomplete="email">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" style="display:flex;justify-content:space-between;">
            Password
            <a href="/cliniccares/forgot-password.php" style="font-weight:400;color:var(--primary);text-transform:none;letter-spacing:0;font-size:13px;">Forgot password?</a>
          </label>
          <div class="input-group-icon">
            <span class="icon">🔒</span>
            <input type="password" name="password" id="passwordField" class="form-control" placeholder="••••••••" required autocomplete="current-password">
            <button type="button" onclick="togglePassword()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);border:none;background:none;cursor:pointer;font-size:16px;" id="pwToggle">👁️</button>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:8px;">
          Sign In
        </button>
      </form>

      <div class="auth-divider"><span>Don't have an account?</span></div>

      <a href="/cliniccares/register.php" class="btn btn-outline btn-block">Create Patient Account</a>

      <!-- Demo credentials -->
      <div style="margin-top:24px;padding:16px;background:var(--surface-2);border-radius:10px;border:1px solid var(--border);">
        <div style="font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px;">Demo Accounts</div>
        <div style="display:grid;gap:6px;">
          <?php
          $demos = [
            ['admin@cliniccare.com', 'Admin'],
            ['dr.reyes@cliniccare.com', 'Doctor'],
            ['juan.dela.cruz@email.com', 'Patient'],
          ];
          foreach ($demos as [$email, $label]):
          ?>
          <button onclick="fillDemo('<?= $email ?>')" style="display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border:1px solid var(--border);border-radius:6px;background:#fff;cursor:pointer;font-size:13px;width:100%;transition:all 0.15s;" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border)'">
            <span style="color:var(--text-secondary);"><?= $email ?></span>
            <span class="badge badge-primary"><?= $label ?></span>
          </button>
          <?php endforeach; ?>
          <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">Password for all: <code style="background:var(--border);padding:2px 6px;border-radius:4px;">password</code></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function togglePassword() {
  const f = document.getElementById('passwordField');
  const b = document.getElementById('pwToggle');
  f.type = f.type === 'password' ? 'text' : 'password';
  b.textContent = f.type === 'password' ? '👁️' : '🙈';
}
function fillDemo(email) {
  document.querySelector('[name=email]').value = email;
  document.querySelector('[name=password]').value = 'password';
}
// Simple form validate
function validateForm(id) {
  const form = document.getElementById(id);
  let ok = true;
  form.querySelectorAll('[required]').forEach(f => {
    if (!f.value.trim()) { f.classList.add('is-invalid'); ok = false; }
    else f.classList.remove('is-invalid');
  });
  return ok;
}
</script>
</body>
</html>