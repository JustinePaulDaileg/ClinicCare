<?php
// Header layout component
// Usage: include this at top of dashboard pages
// Required: $pageTitle, $activeNav (optional)

$user = db()->fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
$notifications = getUnreadNotifications($_SESSION['user_id']);
$notifCount = count($notifications);
$initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));

$role = $_SESSION['role'];

// Build nav items per role
$navItems = [];
if ($role === 'admin') {
    $navItems = [
        'dashboard'    => ['icon' => '', 'label' => 'Dashboard',    'url' => '/cliniccares/admin/dashboard.php'],
        'users'        => ['icon' => '', 'label' => 'Users',         'url' => '/cliniccares/admin/users.php'],
        'doctors'      => ['icon' => '', 'label' => 'Doctors',      'url' => '/cliniccares/admin/doctors.php'],
        'patients'     => ['icon' => '', 'label' => 'Patients',    'url' => '/cliniccares/admin/patients.php'],
        'appointments' => ['icon' => '', 'label' => 'Appointments',  'url' => '/cliniccares/admin/appointments.php'],
        'billing'      => ['icon' => '', 'label' => 'Billing',       'url' => '/cliniccares/admin/billing.php'],
        'reports'      => ['icon' => '', 'label' => 'Reports',       'url' => '/cliniccares/admin/reports.php'],
        'backup'       => ['icon' => '', 'label' => 'Backup',        'url' => '/cliniccares/admin/backup.php'],
        'find'         => ['icon' => '', 'label' => 'Find Clinic',   'url' => '/cliniccares/find-clinic.php'],
    ];
} elseif ($role === 'doctor') {
    $navItems = [
        'dashboard'    => ['icon' => '', 'label' => 'Dashboard',    'url' => '/cliniccares/doctor/dashboard.php'],
        'appointments' => ['icon' => '', 'label' => 'Appointments',  'url' => '/cliniccares/doctor/appointments.php'],
        'patients'     => ['icon' => '', 'label' => 'My Patients', 'url' => '/cliniccares/doctor/patients.php'],
        'prescriptions'=> ['icon' => '', 'label' => 'Prescriptions', 'url' => '/cliniccares/doctor/prescriptions.php'],
        'records'      => ['icon' => '', 'label' => 'Medical Records','url' => '/cliniccares/doctor/records.php'],
        'schedule'     => ['icon' => '', 'label' => 'My Schedule',   'url' => '/cliniccares/doctor/schedule.php'],
        'find'         => ['icon' => '', 'label' => 'Find Clinic',   'url' => '/cliniccares/find-clinic.php'],
    ];
} else {
    $navItems = [
        'dashboard'    => ['icon' => '', 'label' => 'Dashboard',    'url' => '/cliniccares/patient/dashboard.php'],
        'appointments' => ['icon' => '', 'label' => 'Appointments',  'url' => '/cliniccares/patient/appointments.php'],
        'book'         => ['icon' => '', 'label' => 'Book Appointment','url' => '/cliniccares/patient/book.php'],
        'prescriptions'=> ['icon' => '', 'label' => 'Prescriptions', 'url' => '/cliniccares/patient/prescriptions.php'],
        'records'      => ['icon' => '', 'label' => 'Health Records', 'url' => '/cliniccares/patient/records.php'],
        'billing'      => ['icon' => '', 'label' => 'Billing',       'url' => '/cliniccares/patient/billing.php'],
        'find'         => ['icon' => '', 'label' => 'Find Clinic',   'url' => '/cliniccares/find-clinic.php'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> — ClinicCare</title>
  <link rel="stylesheet" href="/cliniccares/assets/css/main.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <link rel="icon" type="image/png" href="/cliniccares/assets/img/ClinicCares.png">
</head>
<body>
<div class="app-wrapper">
  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <a href="/cliniccares/<?= $role ?>/dashboard.php" class="sidebar-brand">
    
      <h2>ClinicCare</h2>
      <span>Health Management System</span>
    </a>

    <div class="sidebar-user">
      <?php if (!empty($user['avatar'])): ?>
        <img src="<?= htmlspecialchars($user['avatar']) ?>?v=<?= time() ?>" alt="Avatar"
             style="width:40px;height:40px;border-radius:50%;object-fit:cover;flex-shrink:0;border:2px solid rgba(255,255,255,0.3);">
      <?php else: ?>
      <div class="sidebar-avatar"><?= $initials ?></div>
      <?php endif; ?>
      <div class="sidebar-user-info">
        <div class="name"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
        <div class="role"><?= ucfirst($role) ?></div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-title">Menu</div>
      <?php foreach ($navItems as $key => $item): ?>
        <a href="<?= $item['url'] ?>" class="nav-link <?= ($activeNav ?? '') === $key ? 'active' : '' ?>">
          <span class="icon"><?= $item['icon'] ?></span>
          <span><?= $item['label'] ?></span>
        </a>
      <?php endforeach; ?>

      <div class="nav-section-title" style="margin-top:16px;">Account</div>
      <a href="/cliniccares/profile.php" class="nav-link <?= ($activeNav ?? '') === 'profile' ? 'active' : '' ?>">
        <span class="icon">⚙️</span> Settings & Profile
      </a>
    </nav>

    <div class="sidebar-footer">
      <a href="/cliniccares/logout.php" class="nav-link">
        <span class="icon">🚪</span> Logout
      </a>
    </div>
  </aside>

  <!-- Sidebar mobile overlay -->
  <div id="sidebarOverlay" style="position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:99;display:none;" onclick="document.getElementById('sidebar').classList.remove('mobile-open');this.style.display='none';"></div>

  <!-- Main content -->
  <div class="main-content">
    <!-- Topbar -->
    <header class="topbar">
      <button id="sidebarToggle" class="btn-icon" style="display:none;" aria-label="Toggle sidebar">☰</button>
      <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></div>

      <div class="topbar-actions">
        <!-- Notifications -->
        <div style="position:relative;">
          <button class="btn-icon" id="notifBtn" aria-label="Notifications">
            🔔
            <?php if ($notifCount > 0): ?>
              <span class="notif-dot"></span>
            <?php endif; ?>
          </button>
          <div class="notif-dropdown" id="notifDropdown">
            <div class="notif-header">
              Notifications
              <?php if ($notifCount > 0): ?>
                <span class="badge badge-danger" style="margin-left:6px;"><?= $notifCount ?></span>
              <?php endif; ?>
            </div>
            <div class="notif-list">
              <?php if (empty($notifications)): ?>
                <div style="padding:24px;text-align:center;color:var(--text-muted);font-size:14px;">
                  No new notifications
                </div>
              <?php else: ?>
                <?php foreach ($notifications as $n): ?>
                  <div class="notif-item unread" data-id="<?= $n['id'] ?>" data-link="<?= htmlspecialchars($n['link']) ?>">
                    <div class="notif-icon">
                      <?= match($n['type']) {
                        'appointment' => '📅',
                        'billing'     => '💳',
                        'prescription'=> '💊',
                        'reminder'    => '⏰',
                        default       => '🔔'
                      } ?>
                    </div>
                    <div class="notif-text">
                      <div class="notif-title"><?= htmlspecialchars($n['title']) ?></div>
                      <div class="notif-msg"><?= htmlspecialchars(substr($n['message'], 0, 80)) ?></div>
                      <div class="notif-time"><?= date('M d, h:i A', strtotime($n['created_at'])) ?></div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <div class="notif-footer">
              <a href="/cliniccares/notifications.php" style="font-size:13px;color:var(--primary);text-decoration:none;font-weight:600;">View all notifications</a>
            </div>
          </div>
        </div>

        <!-- User menu -->
        <?php if (!empty($user['avatar'])): ?>
          <img src="<?= htmlspecialchars($user['avatar']) ?>?v=<?= time() ?>" alt="Avatar"
               style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0;border:2px solid var(--primary);">
        <?php else: ?>
        <div class="avatar"><?= $initials ?></div>
        <?php endif; ?>

        <!-- Logout -->
        <a href="/cliniccares/logout.php" class="btn-icon topbar-logout" aria-label="Logout" title="Logout">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
            <polyline points="16 17 21 12 16 7"/>
            <line x1="21" y1="12" x2="9" y2="12"/>
          </svg>
          <span style="font-size:12px;font-weight:600;margin-left:2px;">Logout</span>
        </a>
      </div>
    </header>
    <!-- Page content starts here -->
    <div class="page-content">