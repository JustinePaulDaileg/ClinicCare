<?php
require_once __DIR__ . '/includes/session.php';
if (isLoggedIn()) redirect(SITE_URL . '/' . $_SESSION['role'] . '/dashboard.php');

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = sanitize($_POST['first_name'] ?? '');
    $lastName  = sanitize($_POST['last_name'] ?? '');
    $email     = strtolower(trim($_POST['email'] ?? ''));
    $phone     = sanitize($_POST['phone'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';
    $dob       = $_POST['dob'] ?? '';
    $gender    = $_POST['gender'] ?? '';

    if (!$firstName) $errors[] = 'First name is required.';
    if (!$lastName)  $errors[] = 'Last name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $existing = db()->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            $errors[] = 'An account with this email already exists.';
        } else {
            $token = generateToken();
            $userId = db()->insert(
                "INSERT INTO users (email, password, role, first_name, last_name, phone, is_active, email_verified, verification_token) VALUES (?,?,?,?,?,?,1,0,?)",
                [$email, hashPassword($password), 'patient', $firstName, $lastName, $phone, $token]
            );
            db()->insert(
                "INSERT INTO patients (user_id, date_of_birth, gender) VALUES (?,?,?)",
                [$userId, $dob ?: null, $gender ?: null]
            );

            // Send verification email
            require_once __DIR__ . '/includes/mailer.php';
            sendVerificationEmail(['email'=>$email,'first_name'=>$firstName,'last_name'=>$lastName,'verification_token'=>$token]);

            logActivity($userId, 'REGISTER', 'New patient registered');
            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register — ClinicCare</title>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/main.css">
</head>
<body>
<div class="auth-page">
  <div class="auth-left">
    <div>
      <div class="auth-logo">🏥 ClinicCare</div>
      <p class="auth-tagline">Join thousands of patients managing their health digitally.</p>
      <div class="auth-features" style="margin-top:32px;">
        <div class="auth-feature"><div class="auth-feature-icon">✅</div><span>Free patient registration</span></div>
        <div class="auth-feature"><div class="auth-feature-icon">🔐</div><span>Secure health record storage</span></div>
        <div class="auth-feature"><div class="auth-feature-icon">📱</div><span>Access from any device</span></div>
        <div class="auth-feature"><div class="auth-feature-icon">⚡</div><span>Instant appointment booking</span></div>
      </div>
    </div>
  </div>

  <div class="auth-right" style="overflow-y:auto;">
    <div class="auth-card" style="max-width:420px;">
      <div style="text-align:center;margin-bottom:24px;">
        <h2>Create Account</h2>
        <p class="subtitle">Register as a patient</p>
      </div>

      <?php if ($success): ?>
        <div class="alert alert-success">
          ✅ Account created! Please check your email to verify your account before logging in.
          <br><br><a href="index.php" class="btn btn-primary btn-sm">Go to Login</a>
        </div>
      <?php else: ?>
        <?php if ($errors): ?>
          <div class="alert alert-danger">
            <?php foreach ($errors as $e): ?>
              <div>⚠️ <?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="POST" id="regForm">
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">First Name *</label>
              <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Last Name *</label>
              <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Email Address *</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
          </div>

          <div class="form-group">
            <label class="form-label">Phone Number</label>
            <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="09xxxxxxxxx">
          </div>

          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Date of Birth</label>
              <input type="date" name="dob" class="form-control" value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Gender</label>
              <select name="gender" class="form-select">
                <option value="">Select</option>
                <option value="male" <?= ($_POST['gender']??'')=='male'?'selected':'' ?>>Male</option>
                <option value="female" <?= ($_POST['gender']??'')=='female'?'selected':'' ?>>Female</option>
                <option value="other" <?= ($_POST['gender']??'')=='other'?'selected':'' ?>>Other</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Password * (min 8 characters)</label>
            <input type="password" name="password" class="form-control" required minlength="8">
          </div>

          <div class="form-group">
            <label class="form-label">Confirm Password *</label>
            <input type="password" name="confirm_password" class="form-control" required>
          </div>

          <div style="margin-bottom:16px;">
            <label style="display:flex;align-items:flex-start;gap:8px;font-size:13px;cursor:pointer;">
              <input type="checkbox" required style="margin-top:3px;flex-shrink:0;">
              <span style="color:var(--text-secondary);">I agree to the <a href="#" style="color:var(--primary);">Terms of Service</a> and <a href="#" style="color:var(--primary);">Privacy Policy</a></span>
            </label>
          </div>

          <button type="submit" class="btn btn-primary btn-block btn-lg">Create Account</button>
        </form>

        <div class="auth-divider"><span>Already have an account?</span></div>
        <a href="index.php" class="btn btn-outline btn-block">Sign In</a>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>