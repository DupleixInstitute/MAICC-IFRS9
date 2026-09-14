## 10. Backup and Recovery

Backups are MAIIC's responsibility under clauses 8.1 and 21.1 of the agreement: automated daily database and file backups retained for at least thirty days.

### 10.1 What to back up

| Item | Path | Why |
|---|---|---|
| Database | `maiic_ifrs9` | All loan book, model, ECL, EIR, audit, ticket and manual content |
| Environment file | `/var/www/maiic-ifrs9/.env` | Contains `APP_KEY`; encrypted columns cannot be read without it |
| Uploaded files | `storage/app` (including `storage/app/public`) | Manual figures, failed import files, temporary uploads |
| Manual screenshots | `public/manual/screenshots` | Committed in Git, but regenerate or back up if refreshed on the server |
| Logs (optional) | `storage/logs` | For investigations |

The application code itself is in Git and is restored by cloning; do not rely on server copies.

### 10.2 Nightly backup script

`/usr/local/bin/maiic-backup.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail
DEST=/var/backups/maiic-ifrs9
STAMP=$(date +%Y%m%d-%H%M)
mkdir -p "$DEST"
mysqldump --single-transaction --routines --triggers -u maiic -p"$DB_PASSWORD" maiic_ifrs9 \
  | gzip > "$DEST/db-$STAMP.sql.gz"
tar -czf "$DEST/files-$STAMP.tar.gz" -C /var/www/maiic-ifrs9 .env storage/app public/manual/screenshots
find "$DEST" -type f -mtime +30 -delete
```

Cron entry (root):

```
30 1 * * * DB_PASSWORD='...' /usr/local/bin/maiic-backup.sh >> /var/log/maiic-backup.log 2>&1
```

Copy the backup folder to a second machine or MAIIC's backup system nightly; a backup on the same disk as the database is not a backup.

### 10.3 Restoring a database dump

Both the nightly dump and phpMyAdmin exports from MAIIC restore the same way. Legacy data contains a few orphaned rows, so foreign key checks must be off during the load, and MariaDB's packet size must be raised first (chapter 7.4).

```bash
mysql -u root -e "DROP DATABASE IF EXISTS maiic_ifrs9; CREATE DATABASE maiic_ifrs9 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
( echo "SET FOREIGN_KEY_CHECKS=0;"; zcat db-20260910-0130.sql.gz; echo "SET FOREIGN_KEY_CHECKS=1;" ) \
  | mysql -u root --max_allowed_packet=256M --default-character-set=utf8mb4 maiic_ifrs9
cd /var/www/maiic-ifrs9
php artisan migrate --force
php artisan db:seed --class=TicketSeeder --force
php artisan db:seed --class=HelpContentSeeder --force
php artisan db:seed --class=HelpAdminContentSeeder --force
php artisan db:seed --class=MaiicAdminPermissionsSeeder --force
php artisan cache:clear
```

`migrate` applies any migrations newer than the dump; the seeders are idempotent and only add what is missing. If the dump predates the permission cleanup, also run `LegacyPermissionsCleanupSeeder` and grant the admin role all permissions:

```bash
php artisan tinker --execute='$r=\Spatie\Permission\Models\Role::findByName("admin"); $r->syncPermissions(\Spatie\Permission\Models\Permission::all());'
```

### 10.4 Restoring files

```bash
tar -xzf files-20260910-0130.tar.gz -C /var/www/maiic-ifrs9
sudo chown -R maiic:www-data /var/www/maiic-ifrs9/storage
php artisan config:cache
```

### 10.5 Disaster recovery test

Twice a year, restore the newest backup onto a clean virtual machine following chapters 3, 6 and 10.3, sign in, open the dashboard for the latest period and export one report. Record the time taken and any gaps in the ticketing module.
