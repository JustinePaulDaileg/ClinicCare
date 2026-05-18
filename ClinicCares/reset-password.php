<?php
// forgot-password.php
require_once __DIR__ . '/includes/session.php';
if (isLoggedIn()) redirect(SITE_URL . '/' . $_SESSION['role'] . '/dashboard.php');

$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $user = db()->fetchOne("SELECT * FROM users WHERE email=? AND is_active=1", [$email]);
        if ($user) {
            $token   = generateToken();
            $expires = date('Y-m-d H:i:s', time() + 3600);
            db()->execute("UPDATE users SET reset_token=?, reset_expires=? WHERE id=?", [$token, $expires, $user['id']]);
            require_once __DIR__ . '/includes/mailer.php';
            $user['reset_token'] = $token;
            sendPasswordReset($user);
        }
        // Always show success (don't reveal whether email exists)
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — ClinicCare</title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/main.css">
</head>
<body style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#0f2156,#1e40af);">
<div style="width:100%;max-width:420px;margin:20px;background:#fff;border-radius:16px;padding:40px;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
    <div style="text-align:center;margin-bottom:28px;">
        <div style="font-size:40px;margin-bottom:8px;">🔑</div>
        <h2 style="font-family:var(--font-display);font-size:24px;font-weight:700;margin-bottom:6px;">Forgot Password</h2>
        <p style="color:var(--text-muted);font-size:14px;">Enter your email to receive a reset link</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            ✅ If that email exists in our system, you'll receive a reset link shortly. Check your inbox and spam folder.
        </div>
        <a href="index.php" class="btn btn-primary btn-block" style="margin-top:16px;">← Back to Login</a>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="alert alert-danger">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="you@example.com" required autofocus
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg">Send Reset Link</button>
        </form>
        <div style="text-align:center;margin-top:16px;">
            <a href="index.php" style="color:var(--primary);font-size:13px;text-decoration:none;">← Back to Login</a>
        </div>
    <?php endif; ?>
</div>
</body>
</html>