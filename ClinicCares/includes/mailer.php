<?php
// Simple email helper using PHPMailer
// PHPMailer should be installed via Composer: composer require phpmailer/phpmailer
// Or manually place PHPMailer files in vendor/phpmailer/

require_once __DIR__ . '/config.php';

// Try to load PHPMailer
$phpmailerLoaded = false;
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    $phpmailerLoaded = class_exists('PHPMailer\PHPMailer\PHPMailer');
}

function sendEmail($to, $toName, $subject, $htmlBody, $plainText = '') {
    global $phpmailerLoaded;

    if (!$phpmailerLoaded) {
        // Fallback: use PHP mail() function
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8\r\n";
        $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">\r\n";
        return mail($to, $subject, $htmlBody, $headers);
    }

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $plainText ?: strip_tags($htmlBody);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email error: " . $e->getMessage());
        return false;
    }
}

function getEmailTemplate($title, $content, $btnText = '', $btnLink = '') {
    $btn = '';
    if ($btnText && $btnLink) {
        $btn = "<div style='text-align:center;margin:30px 0;'>
                    <a href='$btnLink' style='background:#2563eb;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:600;font-size:16px;'>$btnText</a>
                </div>";
    }
    return "
    <!DOCTYPE html>
    <html>
    <head><meta charset='UTF-8'><title>$title</title></head>
    <body style='margin:0;padding:0;background:#f1f5f9;font-family:Arial,sans-serif;'>
      <div style='max-width:600px;margin:40px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);'>
        <div style='background:linear-gradient(135deg,#1e40af,#2563eb);padding:32px;text-align:center;'>
          <h1 style='color:#fff;margin:0;font-size:28px;'>🏥 ClinicCare</h1>
          <p style='color:#bfdbfe;margin:8px 0 0;'>Online Health Records & Appointment System</p>
        </div>
        <div style='padding:32px;'>
          <h2 style='color:#1e293b;margin-top:0;'>$title</h2>
          $content
          $btn
        </div>
        <div style='background:#f8fafc;padding:20px;text-align:center;border-top:1px solid #e2e8f0;'>
          <p style='color:#94a3b8;margin:0;font-size:13px;'>
            © " . date('Y') . " ClinicCare | " . SITE_ADDRESS . "<br>
            This is an automated message. Please do not reply directly to this email.
          </p>
        </div>
      </div>
    </body>
    </html>";
}

function sendVerificationEmail($user) {
    $link = SITE_URL . '/verify.php?token=' . $user['verification_token'];
    $content = "<p style='color:#475569;'>Hello <strong>" . htmlspecialchars($user['first_name']) . "</strong>,</p>
                <p style='color:#475569;'>Thank you for registering with ClinicCare. Please verify your email address to activate your account.</p>
                <p style='color:#475569;'>This link will expire in 24 hours.</p>";
    $html = getEmailTemplate('Verify Your Email', $content, 'Verify Email Address', $link);
    return sendEmail($user['email'], $user['first_name'] . ' ' . $user['last_name'], 'Verify your ClinicCare account', $html);
}

function sendAppointmentConfirmation($appointment, $patient, $doctor) {
    $content = "<p style='color:#475569;'>Hello <strong>" . htmlspecialchars($patient['first_name']) . "</strong>,</p>
                <p style='color:#475569;'>Your appointment has been <strong style='color:#16a34a;'>confirmed</strong>.</p>
                <div style='background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:20px;margin:20px 0;'>
                    <p style='margin:4px 0;color:#166534;'><strong>Doctor:</strong> Dr. " . htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']) . "</p>
                    <p style='margin:4px 0;color:#166534;'><strong>Date:</strong> " . formatDate($appointment['appointment_date']) . "</p>
                    <p style='margin:4px 0;color:#166534;'><strong>Time:</strong> " . formatTime($appointment['appointment_time']) . "</p>
                    <p style='margin:4px 0;color:#166534;'><strong>Type:</strong> " . ucfirst($appointment['type']) . "</p>
                </div>
                <p style='color:#475569;'>Please arrive 15 minutes early. Contact us if you need to reschedule.</p>";
    $html = getEmailTemplate('Appointment Confirmed', $content, 'View Appointment', SITE_URL . '/patient/appointments.php');
    return sendEmail($patient['email'], $patient['first_name'] . ' ' . $patient['last_name'], 'Appointment Confirmation - ClinicCare', $html);
}

function sendPasswordReset($user) {
    $link = SITE_URL . '/reset-password.php?token=' . $user['reset_token'];
    $content = "<p style='color:#475569;'>Hello <strong>" . htmlspecialchars($user['first_name']) . "</strong>,</p>
                <p style='color:#475569;'>We received a request to reset your password. Click the button below to set a new password.</p>
                <p style='color:#ef4444;'><strong>This link expires in 1 hour.</strong> If you did not request this, please ignore this email.</p>";
    $html = getEmailTemplate('Reset Your Password', $content, 'Reset Password', $link);
    return sendEmail($user['email'], $user['first_name'] . ' ' . $user['last_name'], 'Password Reset - ClinicCare', $html);
}