## 12. Verification and troubleshooting

### 12.1 Post-installation verification

| Check | Command or action | Expected |
|---|---|---|
| Application boots | `php artisan --version` | Laravel 10.x |
| Migrations current | `php artisan migrate:status \| grep -c Pending` | 0 |
| Database reachable | `php artisan tinker --execute="echo DB::table('users')->count();"` | 1 or more |
| Queue worker alive | `systemctl status maiic-queue` | active (running) |
| Scheduler wired | `php artisan schedule:list` | Lists the five scheduled commands of chapter 7.2 |
| Bootstrap inputs byte-exact | `git check-attr text -- docs/bootstrap/manifest.json` | `text: unset` |
| Data ties and golden numbers | `php -d memory_limit=4096M artisan eir:baselines` (add `--json` for a file) | Every acceptance tie and inventory row PASS; see chapter 9.8 for the golden lines not yet committed |
| Whole system | `php artisan maiic:task health-check`, or Maintenance, Data and checks, "Check the system" | The environment, the migration status, the golden numbers and every report with its PDF; the failed checks are named at the end |
| TLS and headers | `curl -sI https://host/login` | 200 with security headers |
| CAPTCHA | `curl -s -o /dev/null -w '%{http_code}' https://host/captcha` | 200 |
| Login | Browser | MAIIC branded login page, code image visible |
| Documentation | Administration & support, Support & Maintenance, System Documentation | Four documents, each downloads a PDF |
| Import round trip | Upload a small CSV | Status reaches completed |

### 12.2 Common problems

| Symptom | Likely cause | Fix |
|---|---|---|
| HTTP 500 on first load | Missing `APP_KEY`, unreadable `storage` or `bootstrap/cache` | `php artisan key:generate`; `chmod -R 775 storage bootstrap/cache`; check `storage/logs/laravel.log` |
| Blank page after deployment | Assets not built or config cache stale | `npm run build`; `php artisan config:clear && php artisan view:clear` |
| 403 on a menu item, even for admin (Support Tickets on a fresh install, for example) | Permission rows missing, or a stale cached permission list | `php artisan maiic:task update` (it clears the permission cache, then seeds the admin and ticket permissions); if it persists, sync the admin role (chapter 10.3) |
| "There is no permission named ..." | A legacy route was typed by hand | Expected until ticket #009; not reachable from the menu |
| Import stays pending | Worker not running, or `QUEUE_CONNECTION` not `database` | Start the service; set the variable and cache the config |
| A loan book import fails with a Pusher or broadcasting error | `BROADCAST_DRIVER=pusher` (the `.env.example` value) without Pusher keys | Set `BROADCAST_DRIVER=log` and cache the config |
| A job ran twice | A second queue worker and `retry_after` of 90 seconds (chapter 7.1) | Run one worker |
| Login loops back to the form | `SESSION_SECURE_COOKIE=true` while serving over http | Serve over https or set it to false |
| Security code image missing | `gd` not loaded | Enable the extension, restart PHP-FPM |
| PDF download empty or errors | `memory_limit` too low or `storage/fonts` not writable | Set 1G; `chmod 775 storage/fonts` |
| Mixed content warnings | `APP_URL` still http behind TLS | Set `APP_URL=https://...` and `FORCE_HTTPS=true` |
| Mail not sent | SMTP settings or relay firewall | Test with the tinker command in chapter 5.4; check the relay allows the server |
| Dump import fails on a foreign key | Orphan rows in legacy tables | Import with `SET FOREIGN_KEY_CHECKS=0` as in chapter 10.3 |
| Dump import fails with "packet too large" | `max_allowed_packet` default | Raise it (chapter 7.4) or pass `--max_allowed_packet=256M` |
| Composer "Permission denied" on a temp zip (Windows) | Antivirus scanning | Rerun `composer install` |
| Dashboard says no data available | No period has ECL calculated | Run the bootstrap (chapter 9.8) or an ECL calculation |
| "Bootstrap stopped: The committed pack ... did not pass its gates", or a pack quarantined on its SHA-256 | The files under `docs/bootstrap` are not the committed bytes: line endings converted (a clone without `docs/bootstrap/.gitattributes`) or a file re-saved | Check `git check-attr text -- docs/bootstrap/manifest.json` reads `unset` and `git status docs/bootstrap` is clean; restore with `git checkout -- docs/bootstrap` |
| "The database holds user data; pass --force-wipe to wipe it." | `eir:bootstrap --fresh` on a database whose `loan_books` has rows | Only for a deliberate rebuild: add `--force-wipe`, after a backup |
| `eir:bootstrap --verify` exits non-zero with lines reading "no golden value committed" | `golden.json` does not yet hold the engine outputs (chapter 9.8, status) | Expected until Dupleix commits them; every other line must PASS |
| "Allowed memory size ... exhausted" in the bootstrap or a month-end command | PHP's 1 GB limit; these read the whole E-Banker ledger | Run with `php -d memory_limit=4096M artisan ...` |
| Settings, Currencies or the country lists are empty after the bootstrap | `--fresh` dropped what `db:seed` wrote and the bootstrap does not reseed it | Run the seeders listed in chapter 9.8 once |
| The month-end job stops at the forward-looking step with "That step needs a person" | The loan book build, the model version, the scenario set or an overlay is not approved | Approve it on its screen and run the job again; the steps before it are done |
| Write-offs, EIR as at a Date or the RBM return ends in a 504 | The web server gave up before the request's own 1,800 seconds | `fastcgi_read_timeout 1800` (Nginx) or `ProxyTimeout 1800` (Apache), chapter 6 |
| A Maintenance job fails at once on Linux, with no step output | The screen starts the job with the PHP binary the web request runs under (`PHP_BINARY`), which under PHP-FPM can be the `php-fpm` binary rather than the CLI | Run the same command on the console (each job names its command) and report it to Dupleix |
| "No feed folder: set EBANKER_FEED_FOLDER or pass the folder. Nothing landed." | Route 2 not configured | Set `EBANKER_FEED_FOLDER` (chapter 5.1) or ignore it if packs arrive by route 1 |
| The export's post to `/api/ebanker-feed/pack` gets 401 | `EBANKER_FEED_TOKEN` empty or not the token the export sends | Set the same token on both sides and cache the config |
| Screenshot command cannot sign in | CAPTCHA on without the bypass code, or not local | Set `MANUAL_SHOT_CAPTCHA` in a local `.env` only, with `APP_ENV=local` |
| Screenshot command says "No password" although `MANUAL_SHOT_PASSWORD` is set | A cached configuration hides `.env` from the command | `php artisan config:clear`, or pass `--password` |

### 12.3 Where to look

| Source | Content |
|---|---|
| `storage/logs/laravel.log` | Application errors with stack traces |
| `storage/logs/queue-worker.log` | Worker output and job failures |
| `storage/app/command-runs` | The output of every job started from the Maintenance screen (also shown on the screen) |
| Customer & Loan Data, Imports & Feeds, E-Banker Loads | Every pack load with its gates, and the file and row of any quarantine |
| Web server error log | PHP-FPM connection and timeout errors |
| Administration & support, Governance, Audit, Audit Trail | Who changed what, including settings, imports, loads and period locks |
| Administration & support, Support & Maintenance, Support Tickets | The agreed record of issues and resolutions with Dupleix |

### 12.4 Getting help

Log the issue in Support Tickets with the steps, the time, the log excerpt and the affected period, and notify Dupleix. During the warranty period support is included; afterwards it is charged under Schedule 8 with the response targets in chapter 11.4.
