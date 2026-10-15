import sys
sys.path.insert(0, r'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/inst')
from edhelp import *
rep('05-environment-configuration.md', [
("## 5. Environment Configuration", "## 5. Environment configuration"),
("| `DB_HOST`, `DB_PORT` | `127.0.0.1`, `3306` |",
 "| `DB_HOST`, `DB_PORT` | `127.0.0.1`, `3306`. Always set both: the fallback in `config/database.php` is port `3308` and database `ifrs9_maiic` |"),
("| `FILESYSTEM_DISK` | `local` |", "| `FILESYSTEM_DRIVER` | `local` (this project's `config/filesystems.php` reads `FILESYSTEM_DRIVER`, not the newer `FILESYSTEM_DISK`) |"),
("| `CAPTCHA_ENABLED` | `true` |",
 "| `CAPTCHA_ENABLED` | `true`; `CAPTCHA_LENGTH` (default 5 characters) and `CAPTCHA_TTL` (default 300 seconds) are optional |\n| `HSTS_MAX_AGE`, `HSTS_INCLUDE_SUBDOMAINS`, `HSTS_PRELOAD` | Optional; defaults one year, `true`, `false` (`config/security.php`) |\n| `EBANKER_FEED_FOLDER` | The shared folder (or SFTP mount) MAIIC's scheduled E-Banker export writes packs to (route 2). The scheduler polls it at 06:30 each day; empty means nothing is polled |\n| `EBANKER_FEED_TOKEN` | A long random secret, given to MAIIC ICT, if the export posts packs to `/api/ebanker-feed/pack` (route 3) with `Authorization: Bearer <token>`. Empty refuses every post (401) |\n| `WORLDBANK_COUNTRY` | `MWI` (the default) |\n| `HELP_SEED_REFRESH` | Leave unset. Set it only for a one-off seeder run that refreshes the shipped manual text (chapter 11.6) |\n| `FLI_SIDECAR_URL` | Leave empty; an optional statistics service for the forward-looking model, not part of the MAIIC installation |"),
("| `MAIIC_BROWSER_PATH` | Leave empty |\n| `BROADCAST_DRIVER`, `MEMCACHED_HOST`, `REDIS_*`, `AWS_*`, `VITE_*` | Not needed; leave at the example values |",
 "| `MAIIC_BROWSER_PATH` | Leave empty |\n| `BROADCAST_DRIVER` | `log`. `.env.example` says `pusher`, which needs Pusher keys MAIIC does not have; the loan book import broadcasts its progress and would fail without them (`.env.bootstrap.example` and `.env.demo.example` use `log`) |\n| `MEMCACHED_HOST`, `REDIS_*`, `AWS_*`, `PUSHER_*`, `LARAVEL_WEBSOCKETS_PORT`, `VITE_*` | Not needed; leave at the example values |"),
("| Organisation currency (MWK) | Settings, Organisation, Currencies; seeded by `MaiicCurrencySeeder` |",
 "| Organisation currency (MWK) | Settings, Currencies; seeded by `MaiicCurrencySeeder` |"),
("| Financial periods | Administration, Financial Periods |\n| Users and roles | Administration, User Management and Roles and Permissions |",
 "| Financial periods | Administration & support, Access & Setup, Financial Periods |\n| Users and roles | Administration & support, Access & Setup, Users & Roles (Users; Roles & Permissions) |\n| Governed settings (staging thresholds, PD and LGD basis, schedule approval control and the other EIR and FLI settings) | Administration & support, Governance, Governance Centre; each change is proposed by one person and approved by another |"),
("Restart PHP-FPM after changes: `sudo systemctl restart php8.2-fpm`.",
 "Restart PHP-FPM after changes: `sudo systemctl restart php8.2-fpm`.\n\nThe long console jobs need more memory than the web pages. The Maintenance screen starts each job with `php -d memory_limit=4096M`; on the console pass the same when running the bootstrap or the month-end by hand (`php -d memory_limit=4096M artisan eir:bootstrap ...`), because they read the whole E-Banker ledger, Mega Farm included (about 300,000 postings). The CLI has no execution time limit, and the bootstrap, `ifrs9:segment-ecl` and `ifrs9:smoke-reports` lift the limit again after each step that lowers it."),
])
