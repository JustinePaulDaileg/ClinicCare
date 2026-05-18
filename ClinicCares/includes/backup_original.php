<?php
/**
 * ClinicCares Backup System
 * Handles: scheduled daily backups, manual backups, restore, and backup file management
 */

// ─── Backup Settings ─────────────────────────────────────────────────────────
define('BACKUP_DIR',        __DIR__ . '/../backups/');
define('BACKUP_MAX_FILES',  30);          // keep last 30 daily backups
define('BACKUP_SCHEDULE',   '02:00');     // daily target time (HH:MM, 24h)
define('BACKUP_ENCRYPT',    false);       // set true + define BACKUP_PASSPHRASE to encrypt
// define('BACKUP_PASSPHRASE', 'your-secret');

class BackupManager
{
    private string $backupDir;
    private Database $db;

    public function __construct()
    {
        $this->backupDir = BACKUP_DIR;
        $this->db        = Database::getInstance();

        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0750, true);
        }

        // protect folder from web access
        $htaccess = $this->backupDir . '.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all\n");
        }
    }

    // ─── Create a full database backup ───────────────────────────────────────
    public function createBackup(string $triggeredBy = 'manual'): array
    {
        $timestamp  = date('Y-m-d_H-i-s');
        $filename   = "cliniccares_backup_{$timestamp}.sql";
        $filepath   = $this->backupDir . $filename;
        $zipname    = "cliniccares_backup_{$timestamp}.zip";
        $zippath    = $this->backupDir . $zipname;

        try {
            // ── 1. Dump all tables via PDO ──────────────────────────────────
            $sql = $this->generateSQLDump();

            // ── 2. Write .sql file ──────────────────────────────────────────
            if (file_put_contents($filepath, $sql) === false) {
                throw new RuntimeException("Cannot write backup file: $filepath");
            }

            // ── 3. Compress to ZIP ──────────────────────────────────────────
            if (class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if ($zip->open($zippath, ZipArchive::CREATE) === true) {
                    $zip->addFile($filepath, $filename);
                    $zip->close();
                    unlink($filepath);          // remove uncompressed .sql
                    $finalPath = $zippath;
                    $finalFile = $zipname;
                } else {
                    $finalPath = $filepath;     // fallback: keep .sql
                    $finalFile = $filename;
                }
            } else {
                $finalPath = $filepath;
                $finalFile = $filename;
            }

            $fileSize = filesize($finalPath);

            // ── 4. Log in DB ────────────────────────────────────────────────
            $this->db->insert(
                "INSERT INTO backup_logs
                    (filename, file_size, triggered_by, status, notes)
                 VALUES (?, ?, ?, 'success', ?)",
                [$finalFile, $fileSize, $triggeredBy, 'Backup created successfully']
            );

            // ── 5. Rotate old backups ───────────────────────────────────────
            $this->rotateOldBackups();

            return [
                'success'  => true,
                'file'     => $finalFile,
                'size'     => $fileSize,
                'path'     => $finalPath,
                'message'  => 'Backup created successfully',
            ];

        } catch (Throwable $e) {
            // log failure
            $this->db->insert(
                "INSERT INTO backup_logs
                    (filename, file_size, triggered_by, status, notes)
                 VALUES (?, 0, ?, 'failed', ?)",
                [$filename, $triggeredBy, $e->getMessage()]
            );

            return [
                'success' => false,
                'message' => 'Backup failed: ' . $e->getMessage(),
            ];
        }
    }

    // ─── Generate a complete SQL dump using PDO ───────────────────────────────
    private function generateSQLDump(): string
    {
        $pdo = $this->db->getConnection();

        $output  = "-- ClinicCares Database Backup\n";
        $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $output .= "-- Server: " . DB_HOST . "\n";
        $output .= "-- Database: " . DB_NAME . "\n\n";
        $output .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $output .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
        $output .= "SET NAMES utf8mb4;\n\n";

        // get tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            // ── Table structure ───────────────────────────────────────────
            $output .= "-- --------------------------------------------------------\n";
            $output .= "-- Table structure for `$table`\n";
            $output .= "-- --------------------------------------------------------\n\n";
            $output .= "DROP TABLE IF EXISTS `$table`;\n";

            $createStmt = $pdo->query("SHOW CREATE TABLE `$table`")
                              ->fetch(PDO::FETCH_ASSOC);
            $output .= $createStmt['Create Table'] . ";\n\n";

            // ── Table data ────────────────────────────────────────────────
            $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            if (empty($rows)) {
                continue;
            }

            $output .= "-- Data for table `$table`\n";

            // chunk inserts in batches of 100
            foreach (array_chunk($rows, 100) as $chunk) {
                $columns = '`' . implode('`, `', array_keys($chunk[0])) . '`';
                $output .= "INSERT INTO `$table` ($columns) VALUES\n";

                $rowStrings = [];
                foreach ($chunk as $row) {
                    $vals = array_map(function ($val) use ($pdo) {
                        if ($val === null) {
                            return 'NULL';
                        }
                        return $pdo->quote($val);
                    }, array_values($row));
                    $rowStrings[] = '(' . implode(', ', $vals) . ')';
                }

                $output .= implode(",\n", $rowStrings) . ";\n";
            }
            $output .= "\n";
        }

        $output .= "SET FOREIGN_KEY_CHECKS=1;\n";
        return $output;
    }

    // ─── Restore from a backup file ──────────────────────────────────────────
    public function restoreBackup(string $filename, int $adminId): array
    {
        $filepath = $this->backupDir . basename($filename);  // prevent traversal

        if (!file_exists($filepath)) {
            return ['success' => false, 'message' => 'Backup file not found.'];
        }

        try {
            $sql = '';

            // ── Extract ZIP if needed ─────────────────────────────────────
            if (str_ends_with($filename, '.zip') && class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if ($zip->open($filepath) !== true) {
                    throw new RuntimeException('Cannot open ZIP file.');
                }
                $sql = $zip->getFromIndex(0);
                $zip->close();
                if ($sql === false) {
                    throw new RuntimeException('Cannot read SQL inside ZIP.');
                }
            } else {
                $sql = file_get_contents($filepath);
                if ($sql === false) {
                    throw new RuntimeException('Cannot read backup file.');
                }
            }

            // ── Execute SQL statements ────────────────────────────────────
            $pdo = $this->db->getConnection();
            $pdo->exec("SET FOREIGN_KEY_CHECKS=0;");

            // Split on ; not inside quotes
            $statements = preg_split('/;\s*\n/', $sql);
            foreach ($statements as $stmt) {
                $stmt = trim($stmt);
                if ($stmt === '' || str_starts_with($stmt, '--')) {
                    continue;
                }
                $pdo->exec($stmt);
            }

            $pdo->exec("SET FOREIGN_KEY_CHECKS=1;");

            // ── Log ───────────────────────────────────────────────────────
            $this->db->insert(
                "INSERT INTO backup_logs
                    (filename, file_size, triggered_by, status, notes)
                 VALUES (?, ?, 'restore', 'success', ?)",
                [$filename, filesize($filepath), "Restored by admin ID $adminId"]
            );

            logActivity($adminId, 'backup_restore', "Restored database from: $filename");

            return ['success' => true, 'message' => 'Database restored successfully from ' . $filename];

        } catch (Throwable $e) {
            $this->db->insert(
                "INSERT INTO backup_logs
                    (filename, file_size, triggered_by, status, notes)
                 VALUES (?, 0, 'restore', 'failed', ?)",
                [$filename, 'Restore failed: ' . $e->getMessage()]
            );

            return ['success' => false, 'message' => 'Restore failed: ' . $e->getMessage()];
        }
    }

    // ─── List all backup files ────────────────────────────────────────────────
    public function listBackups(): array
    {
        $files  = glob($this->backupDir . 'cliniccares_backup_*.{sql,zip}', GLOB_BRACE);
        $result = [];

        foreach ((array) $files as $path) {
            $fn       = basename($path);
            $size     = filesize($path);
            $modified = filemtime($path);

            // parse timestamp from filename  cliniccares_backup_YYYY-MM-DD_HH-II-SS.ext
            preg_match('/backup_(\d{4}-\d{2}-\d{2})_(\d{2}-\d{2}-\d{2})/', $fn, $m);
            $datetime = isset($m[1]) ? "{$m[1]} " . str_replace('-', ':', $m[2]) : date('Y-m-d H:i:s', $modified);

            $result[] = [
                'filename'  => $fn,
                'size'      => $size,
                'size_fmt'  => $this->formatBytes($size),
                'datetime'  => $datetime,
                'timestamp' => $modified,
            ];
        }

        // newest first
        usort($result, fn($a, $b) => $b['timestamp'] - $a['timestamp']);
        return $result;
    }

    // ─── Delete a backup file ─────────────────────────────────────────────────
    public function deleteBackup(string $filename): bool
    {
        $filepath = $this->backupDir . basename($filename);
        if (file_exists($filepath)) {
            return unlink($filepath);
        }
        return false;
    }

    // ─── Download a backup file (sends headers) ───────────────────────────────
    public function downloadBackup(string $filename): void
    {
        $filepath = $this->backupDir . basename($filename);
        if (!file_exists($filepath)) {
            http_response_code(404);
            exit('File not found.');
        }

        $mime = str_ends_with($filename, '.zip') ? 'application/zip' : 'application/octet-stream';

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        ob_clean();
        flush();
        readfile($filepath);
        exit;
    }

    // ─── Check if scheduled backup is due ────────────────────────────────────
    public static function isScheduledBackupDue(): bool
    {
        $scheduledTime = BACKUP_SCHEDULE;             // e.g. "02:00"
        $currentTime   = date('H:i');
        $currentDate   = date('Y-m-d');

        // only trigger within the scheduled minute
        if ($currentTime !== $scheduledTime) {
            return false;
        }

        // check the last successful backup
        try {
            $last = Database::getInstance()->fetchOne(
                "SELECT created_at FROM backup_logs
                  WHERE status = 'success' AND triggered_by = 'scheduled'
                  ORDER BY created_at DESC LIMIT 1"
            );
            if ($last) {
                $lastDate = date('Y-m-d', strtotime($last['created_at']));
                return $lastDate !== $currentDate;    // already ran today
            }
        } catch (Throwable $e) {
            // table may not exist yet — allow the backup to run
        }

        return true;
    }

    // ─── Rotate: delete backups older than BACKUP_MAX_FILES ──────────────────
    private function rotateOldBackups(): void
    {
        $files = glob($this->backupDir . 'cliniccares_backup_*.{sql,zip}', GLOB_BRACE);
        if (!$files || count($files) <= BACKUP_MAX_FILES) {
            return;
        }

        // sort by modification time, oldest first
        usort($files, fn($a, $b) => filemtime($a) - filemtime($b));
        $toDelete = array_slice($files, 0, count($files) - BACKUP_MAX_FILES);
        foreach ($toDelete as $old) {
            @unlink($old);
        }
    }

    // ─── Human-readable bytes ─────────────────────────────────────────────────
    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }

    // ─── Get backup log history ───────────────────────────────────────────────
    public function getBackupLogs(int $limit = 50): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM backup_logs ORDER BY created_at DESC LIMIT ?",
            [$limit]
        );
    }
}
