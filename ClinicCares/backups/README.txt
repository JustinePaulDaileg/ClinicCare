This folder stores ClinicCares database backup files (.sql.zip).

IMPORTANT:
- This folder is protected from web access via .htaccess (auto-created at runtime).
- Backups are rotated automatically; only the last 30 are kept.
- Do NOT commit real backup files to version control.
- The cron script (cron/backup_cron.php) runs daily at 02:00 by default.

Cron setup (add to crontab with `crontab -e`):
  * * * * * php /var/www/html/cliniccares/cron/backup_cron.php >> /var/log/cliniccares_backup.log 2>&1
