import sys
sys.path.insert(0, r'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/inst')
from edhelp import *
rep('10-backup-and-recovery.md', [
("## 10. Backup and Recovery", "## 10. Backup and recovery"),
("| Uploaded files | `storage/app` (including `storage/app/public`) | Manual figures, failed import files, temporary uploads |",
 "| Uploaded and received files | `storage/app` (including `storage/app/public`) | Manual figures, failed import files, the E-Banker packs uploaded on screen (`ebanker-packs`) or posted to the API (`ebanker-inbox`), the trial balance and take-on uploads, auditor packs and the Maintenance job logs (`command-runs`). `storage/app/bootstrap-packs` (pack 4 unzipped by the bootstrap, 143 MB) need not be kept: the bootstrap recreates it from the committed zip |"),
("| Logs (optional) | `storage/logs` | For investigations |",
 "| Logs (optional) | `storage/logs` | For investigations |\n| E-Banker feed folder | `EBANKER_FEED_FOLDER` (route 2) | MAIIC's own share; back it up with MAIIC's file servers. Every pack landed from it is also held in the database |"),
("tar -czf \"$DEST/files-$STAMP.tar.gz\" -C /var/www/maiic-ifrs9 .env storage/app public/manual/screenshots",
 "tar -czf \"$DEST/files-$STAMP.tar.gz\" -C /var/www/maiic-ifrs9 --exclude=storage/app/bootstrap-packs \\\n  .env storage/app public/manual/screenshots"),
("cd /var/www/maiic-ifrs9\nphp artisan migrate --force\nphp artisan db:seed --class=TicketSeeder --force\nphp artisan db:seed --class=HelpContentSeeder --force\nphp artisan db:seed --class=HelpAdminContentSeeder --force\nphp artisan db:seed --class=MaiicAdminPermissionsSeeder --force\nphp artisan cache:clear\n```\n\n`migrate` applies any migrations newer than the dump; the seeders are idempotent and only add what is missing.",
 "cd /var/www/maiic-ifrs9\nphp artisan maiic:task update\nphp artisan db:seed --class=HelpContentSeeder --force\nphp artisan db:seed --class=HelpAdminContentSeeder --force\nphp artisan db:seed --class=TicketSeeder --force\nphp artisan cache:clear\n```\n\n`maiic:task update` applies any migrations newer than the dump, clears the cached permission list, then seeds the admin module permissions, the support ticket permissions, the Governance Centre defaults and the qualitative SICR factors (chapter 11.6). These seeders, and the ticket seeder, only add what is missing. The two manual seeders leave a manual that already has content as it is (they only rename chapters whose titles changed); to bring the shipped text up to date use chapter 11.6."),
("Twice a year, restore the newest backup onto a clean virtual machine following chapters 3, 6 and 10.3, sign in, open the dashboard for the latest period and export one report.",
 "Twice a year, restore the newest backup onto a clean virtual machine following chapters 3, 6 and 10.3, sign in, open the dashboard for the latest period, export one report and run `php artisan maiic:task health-check` (or Maintenance, \"Check the system\")."),
])
