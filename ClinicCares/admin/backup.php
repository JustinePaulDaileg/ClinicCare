<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('admin');

require_once __DIR__ . '/../includes/backup.php';

$pageTitle = 'Backup & Restore';
$activeNav = 'backup';

$manager = new BackupManager();
$message = '';
$msgType = '';

// ── Load backup time setting ──────────────────────────────────────────────────
$db = Database::getInstance();
// auto-create system_settings table if not exists
try {
    $db->getConnection()->exec("CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value VARCHAR(255) NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->getConnection()->exec("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('backup_time','02:00')");
} catch (Throwable $e) {}

$settingRow  = $db->fetchOne("SELECT setting_value FROM system_settings WHERE setting_key='backup_time'");
$backupTime  = $settingRow ? $settingRow['setting_value'] : '02:00';

// ── Handle POST actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token. Please refresh and try again.';
        $msgType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create_backup') {
            $result  = $manager->createBackup('manual');
            $message = $result['message'];
            $msgType = $result['success'] ? 'success' : 'danger';
            if ($result['success']) {
                logActivity($_SESSION['user_id'], 'backup_create', 'Manual backup: ' . $result['file']);
            }
        }

        elseif ($action === 'restore_backup') {
            $filename = $_POST['filename'] ?? '';
            if (!$filename) {
                $message = 'No backup file selected.';
                $msgType = 'danger';
            } else {
                $result  = $manager->restoreBackup($filename, $_SESSION['user_id']);
                $message = $result['message'];
                $msgType = $result['success'] ? 'success' : 'danger';
            }
        }

        elseif ($action === 'save_backup_time') {
            $newTime = $_POST['backup_time'] ?? '02:00';
            if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $newTime)) {
                $db->getConnection()->exec(
                    "INSERT INTO system_settings (setting_key, setting_value) VALUES ('backup_time', " .
                    $db->getConnection()->quote($newTime) .
                    ") ON DUPLICATE KEY UPDATE setting_value=" .
                    $db->getConnection()->quote($newTime)
                );
                $backupTime = $newTime;
                $message = 'Auto-backup time updated to ' . $newTime;
                $msgType = 'success';
                logActivity($_SESSION['user_id'], 'settings_update', 'Backup time set to ' . $newTime);
            } else {
                $message = 'Invalid time format. Use HH:MM (e.g. 02:00).';
                $msgType = 'danger';
            }
        }

        elseif ($action === 'delete_backup') {
            $filename = $_POST['filename'] ?? '';
            if ($filename && $manager->deleteBackup($filename)) {
                $message = "Backup deleted: $filename";
                $msgType = 'success';
                logActivity($_SESSION['user_id'], 'backup_delete', "Deleted backup: $filename");
            } else {
                $message = 'Failed to delete backup file.';
                $msgType = 'danger';
            }
        }
    }
}

// ── Handle download (GET) ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['download'])) {
    $filename = basename($_GET['download']);
    logActivity($_SESSION['user_id'], 'backup_download', "Downloaded backup: $filename");
    $manager->downloadBackup($filename);
    exit;
}

// ── Data ──────────────────────────────────────────────────────────────────────
$backups    = $manager->listBackups();
$backupLogs = $manager->getBackupLogs(30);
$nextBackup = BACKUP_SCHEDULE;

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1>🗄️ Backup &amp; Restore</h1>
  <p>Manage database backups. Daily auto-backup runs at <strong><?= htmlspecialchars($nextBackup) ?></strong>.</p>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $msgType ?>" style="margin-bottom:20px;">
  <?= $msgType === 'success' ? '✅' : '❌' ?> <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px;">
  <div class="stat-card blue">
    <div class="stat-icon blue">🗂️</div>
    <div class="stat-value"><?= count($backups) ?></div>
    <div class="stat-label">Total Backups</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon green">📦</div>
    <div class="stat-value"><?= $backups ? $backups[0]['size_fmt'] : '—' ?></div>
    <div class="stat-label">Latest Backup Size</div>
  </div>
  <div class="stat-card teal">
    <div class="stat-icon teal">📅</div>
    <div class="stat-value"><?= $backups ? date('M d', strtotime($backups[0]['datetime'])) : 'Never' ?></div>
    <div class="stat-label">Last Backup Date</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;">

  <!-- Left: Backup List -->
  <div class="card">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
      <span class="card-title">📋 Available Backups</span>
      <form method="POST" id="createBackupForm" style="margin:0;">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action"     value="create_backup">
        <button type="button" class="btn btn-success" style="font-size:13px;padding:7px 14px;" onclick="confirmCreate()">
          + Create Backup Now
        </button>
      </form>
    </div>
    <div class="card-body" style="padding:0;">
      <?php if (empty($backups)): ?>
      <div style="text-align:center;padding:48px 20px;color:var(--text-muted);">
        <div style="font-size:48px;margin-bottom:12px;">🗄️</div>
        <p>No backups yet. Create your first backup!</p>
      </div>
      <?php else: ?>
      <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
          <thead>
            <tr style="background:var(--surface-2);">
              <th style="padding:12px 16px;text-align:left;font-size:12px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Filename</th>
              <th style="padding:12px 16px;text-align:left;font-size:12px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Date &amp; Time</th>
              <th style="padding:12px 16px;text-align:left;font-size:12px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Size</th>
              <th style="padding:12px 16px;text-align:center;font-size:12px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($backups as $i => $bk): ?>
            <tr style="border-top:1px solid var(--border);<?= $i === 0 ? 'background:var(--surface-2);' : '' ?>">
              <td style="padding:12px 16px;">
                <div style="display:flex;align-items:center;gap:8px;">
                  <span style="font-size:18px;">📁</span>
                  <span style="font-size:12px;color:var(--text-primary);word-break:break-all;"><?= htmlspecialchars($bk['filename']) ?></span>
                  <?php if ($i === 0): ?>
                    <span class="badge badge-success">Latest</span>
                  <?php endif; ?>
                </div>
              </td>
              <td style="padding:12px 16px;font-size:13px;color:var(--text-secondary);white-space:nowrap;"><?= htmlspecialchars($bk['datetime']) ?></td>
              <td style="padding:12px 16px;font-size:13px;color:var(--text-secondary);white-space:nowrap;"><?= htmlspecialchars($bk['size_fmt']) ?></td>
              <td style="padding:12px 16px;text-align:center;">
                <div style="display:flex;gap:6px;justify-content:center;">
                  <a href="?download=<?= urlencode($bk['filename']) ?>"
                     class="btn btn-outline" title="Download"
                     style="font-size:12px;padding:5px 10px;">⬇️ Download</a>
                  <button type="button" class="btn btn-warning" title="Restore"
                          style="font-size:12px;padding:5px 10px;"
                          onclick="confirmRestore('<?= htmlspecialchars(addslashes($bk['filename'])) ?>')">
                    ↩️ Restore
                  </button>
                  <button type="button" class="btn btn-danger" title="Delete"
                          style="font-size:12px;padding:5px 10px;"
                          onclick="confirmDelete('<?= htmlspecialchars(addslashes($bk['filename'])) ?>')">
                    🗑️
                  </button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Right column -->
  <div style="display:flex;flex-direction:column;gap:16px;">

    <!-- Schedule Info -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">⏰ Backup Schedule</span>
      </div>
      <div class="card-body">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
          <tr style="border-bottom:1px solid var(--border);">
            <td style="padding:8px 0;color:var(--text-muted);">Frequency</td>
            <td style="padding:8px 0;font-weight:600;">Daily</td>
          </tr>
          <tr style="border-bottom:1px solid var(--border);">
            <td style="padding:8px 0;color:var(--text-muted);">Auto-backup after</td>
            <td style="padding:8px 0;font-weight:600;color:var(--primary);"><?= htmlspecialchars($backupTime) ?></td>
          </tr>
          <tr style="border-bottom:1px solid var(--border);">
            <td style="padding:8px 0;color:var(--text-muted);">Retention</td>
            <td style="padding:8px 0;font-weight:600;"><?= BACKUP_MAX_FILES ?> files</td>
          </tr>
          <tr style="border-bottom:1px solid var(--border);">
            <td style="padding:8px 0;color:var(--text-muted);">Format</td>
            <td style="padding:8px 0;font-weight:600;">SQL + ZIP</td>
          </tr>
          <tr>
            <td style="padding:8px 0;color:var(--text-muted);">Method</td>
            <td style="padding:8px 0;font-weight:600;">Auto on first login</td>
          </tr>
        </table>

        <hr style="border:none;border-top:1px solid var(--border);margin:16px 0;">

        <!-- Time picker -->
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="action"     value="save_backup_time">
          <label style="font-size:13px;font-weight:600;color:var(--text-primary);display:block;margin-bottom:8px;">
            ⚙️ Set Auto-backup Time
          </label>
          <div style="display:flex;gap:8px;align-items:center;">
            <input type="time" name="backup_time" value="<?= htmlspecialchars($backupTime) ?>"
                   style="flex:1;padding:8px 10px;border:1.5px solid var(--border);border-radius:8px;
                          font-size:14px;background:var(--surface);color:var(--text-primary);">
            <button type="submit" class="btn btn-primary" style="padding:8px 14px;font-size:13px;">
              Save
            </button>
          </div>
          <p style="font-size:11px;color:var(--text-muted);margin-top:6px;">
            Backup runs automatically on the first login after this time each day.
          </p>
        </form>
      </div>
    </div>

    <!-- Recent Activity Log -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">🕓 Recent Activity</span>
      </div>
      <div style="max-height:320px;overflow-y:auto;">
        <?php if (empty($backupLogs)): ?>
        <div style="text-align:center;padding:24px;color:var(--text-muted);font-size:13px;">No log entries yet.</div>
        <?php else: ?>
        <?php foreach ($backupLogs as $log): ?>
        <div style="padding:10px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:flex-start;">
          <div>
            <span class="badge badge-<?= $log['status'] === 'success' ? 'success' : 'danger' ?>" style="margin-right:6px;">
              <?= ucfirst($log['status']) ?>
            </span>
            <span style="font-size:12px;color:var(--text-muted);"><?= ucfirst($log['triggered_by']) ?></span>
            <div style="font-size:11px;color:var(--text-secondary);margin-top:3px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                 title="<?= htmlspecialchars($log['filename']) ?>">
              <?= htmlspecialchars($log['filename'] ?: $log['notes']) ?>
            </div>
          </div>
          <span style="font-size:11px;color:var(--text-muted);white-space:nowrap;"><?= date('M d, H:i', strtotime($log['created_at'])) ?></span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /right col -->
</div>

<!-- Hidden forms -->
<form method="POST" id="restoreForm" style="display:none;">
  <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
  <input type="hidden" name="action"     value="restore_backup">
  <input type="hidden" name="filename"   id="restoreFilename">
</form>

<form method="POST" id="deleteForm" style="display:none;">
  <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
  <input type="hidden" name="action"     value="delete_backup">
  <input type="hidden" name="filename"   id="deleteFilename">
</form>

<script>
function confirmCreate() {
  if (confirm('Create a full database backup now?\n\nThis may take a few seconds.')) {
    document.getElementById('createBackupForm').submit();
  }
}
function confirmRestore(filename) {
  if (confirm(
    '⚠️  RESTORE DATABASE from:\n"' + filename + '"\n\n' +
    'This will OVERWRITE the current database.\n' +
    'All changes after this backup will be LOST.\n\n' +
    'Are you sure you want to continue?'
  )) {
    document.getElementById('restoreFilename').value = filename;
    document.getElementById('restoreForm').submit();
  }
}
function confirmDelete(filename) {
  if (confirm('Delete this backup?\n"' + filename + '"\n\nThis cannot be undone.')) {
    document.getElementById('deleteFilename').value = filename;
    document.getElementById('deleteForm').submit();
  }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
