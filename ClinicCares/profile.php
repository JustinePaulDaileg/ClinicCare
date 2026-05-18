<?php
require_once __DIR__ . '/includes/session.php';
requireLogin();

$user = db()->fetchOne("SELECT * FROM users WHERE id=?", [$_SESSION['user_id']]);

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_avatar') {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo   = finfo_open(FILEINFO_MIME_TYPE);
            $mime    = finfo_file($finfo, $_FILES['avatar']['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, $allowed)) {
                $error = 'Only JPG, PNG, GIF, or WEBP images are allowed.';
            } elseif ($_FILES['avatar']['size'] > 5 * 1024 * 1024) {
                $error = 'Image must be under 5 MB.';
            } else {
                $ext     = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                $dir     = __DIR__ . '/uploads/avatars/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);

                // Remove old avatar if it exists
                if (!empty($user['avatar'])) {
                    $old = $dir . basename($user['avatar']);
                    if (file_exists($old)) unlink($old);
                }

                $filename = 'avatar_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['avatar']['tmp_name'], $dir . $filename);
                $avatarPath = '/uploads/avatars/' . $filename;
                db()->execute("UPDATE users SET avatar=? WHERE id=?", [$avatarPath, $_SESSION['user_id']]);
                $success = 'Profile picture updated!';
                $user = db()->fetchOne("SELECT * FROM users WHERE id=?", [$_SESSION['user_id']]);
            }
        } elseif (isset($_POST['remove_avatar'])) {
            if (!empty($user['avatar'])) {
                $old = __DIR__ . '/uploads/avatars/' . basename($user['avatar']);
                if (file_exists($old)) unlink($old);
            }
            db()->execute("UPDATE users SET avatar=NULL WHERE id=?", [$_SESSION['user_id']]);
            $success = 'Profile picture removed.';
            $user = db()->fetchOne("SELECT * FROM users WHERE id=?", [$_SESSION['user_id']]);
        }
    }

    if ($action === 'update_profile') {
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName  = sanitize($_POST['last_name']  ?? '');
        $phone     = sanitize($_POST['phone']       ?? '');

        if (!$firstName || !$lastName) {
            $error = 'Name fields are required.';
        } else {
            db()->execute(
                "UPDATE users SET first_name=?,last_name=?,phone=? WHERE id=?",
                [$firstName, $lastName, $phone, $_SESSION['user_id']]
            );
            $_SESSION['name'] = "$firstName $lastName";
            $success = 'Profile updated successfully.';
            $user = db()->fetchOne("SELECT * FROM users WHERE id=?", [$_SESSION['user_id']]);
        }
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!verifyPassword($current, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            db()->execute("UPDATE users SET password=? WHERE id=?", [hashPassword($new), $_SESSION['user_id']]);
            logActivity($_SESSION['user_id'], 'PASSWORD_CHANGE', 'User changed their password');
            $success = 'Password changed successfully.';
        }
    }

    // Patient profile update
    if ($action === 'update_patient' && $_SESSION['role'] === 'patient') {
        $dob     = $_POST['date_of_birth'] ?? null;
        $gender  = in_array($_POST['gender']??'',['male','female','other']) ? $_POST['gender'] : null;
        $blood   = sanitize($_POST['blood_type'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $city    = sanitize($_POST['city'] ?? '');
        $emName  = sanitize($_POST['emergency_contact_name'] ?? '');
        $emPhone = sanitize($_POST['emergency_contact_phone'] ?? '');
        $allerg  = sanitize($_POST['allergies'] ?? '');
        $insP    = sanitize($_POST['insurance_provider'] ?? '');
        $insN    = sanitize($_POST['insurance_number'] ?? '');

        db()->execute(
            "UPDATE patients SET date_of_birth=?,gender=?,blood_type=?,address=?,city=?,emergency_contact_name=?,emergency_contact_phone=?,allergies=?,insurance_provider=?,insurance_number=? WHERE user_id=?",
            [$dob,$gender,$blood?:null,$address,$city,$emName,$emPhone,$allerg,$insP,$insN,$_SESSION['user_id']]
        );

        // Geocode the address so Find Clinic can use home location
        if ($address || $city) {
            $geoQuery = urlencode(trim("$address, $city, Philippines"));
            $geoUrl   = "https://nominatim.openstreetmap.org/search?q={$geoQuery}&format=json&limit=1";
            $ctx = stream_context_create(['http' => [
                'header'  => "User-Agent: ClinicCares/1.0\r\n",
                'timeout' => 5,
            ]]);
            $geoJson = @file_get_contents($geoUrl, false, $ctx);
            if ($geoJson) {
                $geoData = json_decode($geoJson, true);
                if (!empty($geoData[0])) {
                    $homeLat = (float)$geoData[0]['lat'];
                    $homeLng = (float)$geoData[0]['lon'];
                    db()->execute("UPDATE patients SET lat=?,lng=? WHERE user_id=?",
                        [$homeLat, $homeLng, $_SESSION['user_id']]);
                }
            }
        }

        $success = 'Patient profile updated.';
    }
}

// Extra profile data
$patientProfile = null;
$doctorProfile  = null;
if ($user['role'] === 'patient') {
    $patientProfile = db()->fetchOne("SELECT * FROM patients WHERE user_id=?", [$_SESSION['user_id']]);
}
if ($user['role'] === 'doctor') {
    $doctorProfile = db()->fetchOne("SELECT * FROM doctors WHERE user_id=?", [$_SESSION['user_id']]);
}

$pageTitle = 'Settings & Profile';
$activeNav = 'profile';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Settings & Profile</h1>
    <p>Manage your account information and security settings</p>
</div>

<?php if ($success): ?>
<div class="alert alert-success" data-auto-dismiss="5000">✅ <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger">⚠️ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Profile Picture -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header"><span class="card-title">🖼️ Profile Picture</span></div>
    <div class="card-body">
        <div style="display:flex;align-items:center;gap:24px;flex-wrap:wrap;">
            <div id="avatarPreviewWrap" style="flex-shrink:0;">
                <?php if (!empty($user['avatar'])): ?>
                    <img id="avatarPreview" src="<?= htmlspecialchars($user['avatar']) ?>?v=<?= time() ?>"
                         alt="Profile Picture"
                         style="width:90px;height:90px;border-radius:50%;object-fit:cover;border:3px solid var(--primary);box-shadow:0 2px 8px rgba(0,0,0,0.15);">
                <?php else: ?>
                    <div id="avatarPreview" class="sidebar-avatar" style="width:90px;height:90px;font-size:32px;font-weight:700;">
                        <?= strtoupper(substr($user['first_name'],0,1).substr($user['last_name'],0,1)) ?>
                    </div>
                <?php endif; ?>
            </div>
            <div style="flex:1;min-width:200px;">
                <form method="POST" enctype="multipart/form-data" id="avatarForm">
                    <input type="hidden" name="action" value="update_avatar">
                    <div class="form-group" style="margin-bottom:10px;">
                        <label class="form-label">Upload New Picture</label>
                        <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/gif,image/webp"
                               class="form-control" style="padding:6px;"
                               onchange="previewAvatar(this)">
                        <div class="form-text">JPG, PNG, GIF or WEBP · Max 5 MB</div>
                    </div>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <button type="submit" class="btn btn-primary btn-sm">📸 Save Picture</button>
                        <?php if (!empty($user['avatar'])): ?>
                        <button type="submit" name="remove_avatar" value="1" class="btn btn-danger btn-sm"
                                onclick="return confirm('Remove your profile picture?')">🗑️ Remove</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function previewAvatar(input) {
    if (!input.files || !input.files[0]) return;
    var reader = new FileReader();
    reader.onload = function(e) {
        var wrap = document.getElementById('avatarPreviewWrap');
        wrap.innerHTML = '<img id="avatarPreview" src="' + e.target.result + '" alt="Preview" style="width:90px;height:90px;border-radius:50%;object-fit:cover;border:3px solid var(--primary);box-shadow:0 2px 8px rgba(0,0,0,0.15);">';
    };
    reader.readAsDataURL(input.files[0]);
}
</script>

<div class="grid-2" style="grid-template-columns:1fr 1fr;gap:20px;align-items:start;">

    <!-- Personal Info -->
    <div class="card">
        <div class="card-header"><span class="card-title">👤 Personal Information</span></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled style="background:var(--surface-2);color:var(--text-muted);">
                    <div class="form-text">Email cannot be changed. Contact admin if needed.</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']??'') ?>" placeholder="09xxxxxxxxx">
                </div>
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <input type="text" class="form-control" value="<?= ucfirst($user['role']) ?>" disabled style="background:var(--surface-2);color:var(--text-muted);">
                </div>
                <div class="form-group">
                    <label class="form-label">Account Status</label>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span class="badge <?= $user['email_verified']?'badge-success':'badge-warning' ?>"><?= $user['email_verified']?'✓ Email Verified':'⚠ Not Verified' ?></span>
                        <span class="badge <?= $user['is_active']?'badge-success':'badge-secondary' ?>"><?= $user['is_active']?'Active':'Inactive' ?></span>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">💾 Save Changes</button>
            </form>
        </div>
    </div>

    <!-- Change Password -->
    <div style="display:flex;flex-direction:column;gap:16px;">
        <div class="card">
            <div class="card-header"><span class="card-title">🔒 Change Password</span></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">New Password (min 8 characters)</label>
                        <input type="password" name="new_password" class="form-control" required minlength="8">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-warning">🔑 Change Password</button>
                </form>
            </div>
        </div>

        <!-- Two-Factor Authentication -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">🔐 Two-Factor Authentication</span>
                <?php if (!empty($user['two_factor_enabled'])): ?>
                    <span class="badge badge-success" style="margin-left:auto;">Enabled</span>
                <?php else: ?>
                    <span class="badge badge-secondary" style="margin-left:auto;">Disabled</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!empty($user['two_factor_enabled'])): ?>
                    <p style="color:var(--text-secondary);font-size:13px;margin-bottom:14px;">
                        ✅ Your account is protected with Google Authenticator. Every login requires a 6-digit code from your phone.
                    </p>
                    <a href="<?= SITE_URL ?>/2fa-setup.php" class="btn btn-danger btn-sm">⚙️ Manage / Disable 2FA</a>
                <?php else: ?>
                    <p style="color:var(--text-secondary);font-size:13px;margin-bottom:14px;">
                        Protect your account with an authenticator app. You'll need to enter a code each time you log in.
                    </p>
                    <a href="<?= SITE_URL ?>/2fa-setup.php" class="btn btn-primary btn-sm">🔐 Enable 2FA</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Doctor profile info (read-only) -->
        <?php if ($doctorProfile): ?>
        <div class="card">
            <div class="card-header"><span class="card-title">👨‍⚕️ Doctor Profile</span></div>
            <div class="card-body">
                <div style="display:flex;flex-direction:column;gap:8px;font-size:13px;">
                    <?php
                    $docFields = [
                        ['Specialization', $doctorProfile['specialization']],
                        ['License #',      $doctorProfile['license_number']],
                        ['Department',     $doctorProfile['department'] ?: '—'],
                        ['Consultation Fee', formatCurrency($doctorProfile['consultation_fee'])],
                        ['Slot Duration',  $doctorProfile['slot_duration'].' minutes'],
                    ];
                    foreach ($docFields as [$lbl,$val]):
                    ?>
                    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);">
                        <span style="color:var(--text-muted);"><?= $lbl ?></span>
                        <span style="font-weight:600;"><?= htmlspecialchars($val) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:10px;">
                    <a href="/cliniccares/doctor/schedule.php" class="btn btn-secondary btn-sm">⚙️ Manage Schedule</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Patient Extended Profile -->
<?php if ($patientProfile): ?>
<div class="card mt-16">
    <div class="card-header"><span class="card-title">🏥 Medical Profile</span></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="update_patient">
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
                <div class="form-group">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control" value="<?= htmlspecialchars($patientProfile['date_of_birth']??'') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">Select</option>
                        <?php foreach (['male','female','other'] as $g): ?>
                            <option value="<?=$g?>" <?= ($patientProfile['gender']??'')===$g?'selected':'' ?>><?= ucfirst($g) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Blood Type</label>
                    <select name="blood_type" class="form-select">
                        <option value="">Unknown</option>
                        <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
                            <option value="<?=$bt?>" <?= ($patientProfile['blood_type']??'')===$bt?'selected':'' ?>><?=$bt?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="grid-column:span 2;">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($patientProfile['address']??'') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">City</label>
                    <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($patientProfile['city']??'') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Emergency Contact Name</label>
                    <input type="text" name="emergency_contact_name" class="form-control" value="<?= htmlspecialchars($patientProfile['emergency_contact_name']??'') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Emergency Contact Phone</label>
                    <input type="tel" name="emergency_contact_phone" class="form-control" value="<?= htmlspecialchars($patientProfile['emergency_contact_phone']??'') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Known Allergies</label>
                    <input type="text" name="allergies" class="form-control" placeholder="e.g. Penicillin, Sulfa..." value="<?= htmlspecialchars($patientProfile['allergies']??'') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Insurance Provider</label>
                    <input type="text" name="insurance_provider" class="form-control" value="<?= htmlspecialchars($patientProfile['insurance_provider']??'') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Insurance Number</label>
                    <input type="text" name="insurance_number" class="form-control" value="<?= htmlspecialchars($patientProfile['insurance_number']??'') ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">💾 Save Medical Profile</button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>