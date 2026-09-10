## 12. Verification and Troubleshooting

### 12.1 Post-installation verification

| Check | Command or action | Expected |
|---|---|---|
| Application boots | `php artisan --version` | Laravel 10.x |
| Migrations current | `php artisan migrate:status \| grep -c Pending` | 0 |
| Database reachable | `php artisan tinker --execute="echo DB::table('users')->count();"` | 1 or more |
| Queue worker alive | `systemctl status maiic-queue` | active (running) |
| Scheduler wired | `php artisan schedule:list` | Lists the scheduled commands |
| TLS and headers | `curl -sI https://host/login` | 200 with security headers |
| CAPTCHA | `curl -s -o /dev/null -w '%{http_code}' https://host/captcha` | 200 |
| Login | Browser | MAIIC branded login page, code image visible |
| Documentation | System Documentation menu | Four documents, each downloads a PDF |
| Import round trip | Upload a small CSV | Status reaches completed |

### 12.2 Common problems

| Symptom | Likely cause | Fix |
|---|---|---|
| HTTP 500 on first load | Missing `APP_KEY`, unreadable `storage` or `bootstrap/cache` | `php artisan key:generate`; `chmod -R 775 storage bootstrap/cache`; check `storage/logs/laravel.log` |
| Blank page after deployment | Assets not built or config cache stale | `npm run build`; `php artisan config:clear && php artisan view:clear` |
| 403 on a menu item, even for admin | Permission rows missing | Run `MaiicAdminPermissionsSeeder`, `TicketsPermissionsSeeder`, sync the admin role (chapter 10.3) |
| "There is no permission named ..." | A legacy route was typed by hand | Expected until ticket #009; not reachable from the menu |
| Import stays pending | Worker not running, or `QUEUE_CONNECTION=sync` | Start the service; set the variable and cache the config |
| Login loops back to the form | `SESSION_SECURE_COOKIE=true` while serving over http | Serve over https or set it to false |
| Security code image missing | `gd` not loaded | Enable the extension, restart PHP-FPM |
| PDF download empty or errors | `memory_limit` too low or `storage/fonts` not writable | Set 1G; `chmod 775 storage/fonts` |
| Mixed content warnings | `APP_URL` still http behind TLS | Set `APP_URL=https://...` and `FORCE_HTTPS=true` |
| Mail not sent | SMTP settings or relay firewall | Test with the tinker command in chapter 5.4; check the relay allows the server |
| Dump import fails on a foreign key | Orphan rows in legacy tables | Import with `SET FOREIGN_KEY_CHECKS=0` as in chapter 10.3 |
| Dump import fails with "packet too large" | `max_allowed_packet` default | Raise it (chapter 7.4) or pass `--max_allowed_packet=256M` |
| Composer "Permission denied" on a temp zip (Windows) | Antivirus scanning | Rerun `composer install` |
| Dashboard says no data available | No period has ECL calculated | Run an ECL calculation |
| Screenshot command cannot sign in | CAPTCHA on without the bypass code, or not local | Set `MANUAL_SHOT_CAPTCHA` in a local `.env` only |

### 12.3 Where to look

| Source | Content |
|---|---|
| `storage/logs/laravel.log` | Application errors with stack traces |
| `storage/logs/queue-worker.log` | Worker output and job failures |
| Web server error log | PHP-FPM connection and timeout errors |
| Administration, Audit Trail | Who changed what, including settings and imports |
| Administration, Support Tickets | The agreed record of issues and resolutions with Dupleix |

### 12.4 Getting help

Log the issue in Support Tickets with the steps, the time, the log excerpt and the affected period, and notify Dupleix. During the warranty period support is included; afterwards it is charged under Schedule 8 with the response targets in chapter 11.4.
