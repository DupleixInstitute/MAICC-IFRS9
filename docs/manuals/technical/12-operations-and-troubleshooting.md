## 12. Operations and Troubleshooting

### 12.1 Logging

Application logs go to `storage/logs/laravel.log` (daily rotation when `LOG_CHANNEL=daily`). Import jobs log per-row failures at warning level and their exception file path at info level. The queue worker's own output should be captured by the service manager (see the Installation Guide). Audit events are in the database (chapter 8.5), not in the log files.

### 12.2 Performance notes

- OPcache must be enabled; it was found disabled on a development server and cost 1.4 seconds per request.
- The Inertia shared props were slimmed to about 8 KB per response; the user's permissions are sent as a flat list and the menu and notification list are lazy.
- The ECL summary and dashboard use single grouped queries and composite indexes on `expected_credit_loss` and `loan_books`; the ECL page renders in about 90 milliseconds on the development data.
- Settings are cached for sixty seconds in the middleware; clear with `php artisan cache:clear` after editing them outside the UI.
- Vite builds are cached by hash; `npm run build` after every front-end change on the server.

### 12.3 Deployment

`scripts/deploy.sh` is run from the production checkout by the GitHub deploy workflow after a fast-forward pull of master. It takes a lock, puts the application into maintenance mode, installs Composer dependencies without dev packages, builds assets, runs migrations with `--force`, rebuilds the config, route and view caches, restarts queue workers, brings the application up and optionally hits a health check URL. Manual deployment follows the same steps by hand. Compiled assets under `public/build` are not committed, so a build is always part of a release.

### 12.4 Routine operations

| Task | How |
|---|---|
| Clear caches after configuration changes | `php artisan config:clear && php artisan cache:clear && php artisan view:clear` |
| Put the site into maintenance during a long import | `php artisan down --secret=<token>` then `php artisan up` |
| Inspect failed jobs | `php artisan queue:failed`, retry with `queue:retry all` |
| Restart workers after a deploy | `php artisan queue:restart` |
| Refresh manual screenshots after UI changes | `php artisan manual:screenshots` with the app running locally |
| Regenerate the schema appendix of this manual | Nothing to do; it is read live |

### 12.5 Common failures and fixes

| Symptom | Cause | Fix |
|---|---|---|
| Menu item returns 403 for the administrator | Permission missing from the database | Run `MaiicAdminPermissionsSeeder`, `TicketsPermissionsSeeder` and sync the admin role to all permissions |
| Error "permission does not exist" on a typed URL | Legacy route whose permission was removed | Expected until ticket #009 deletes the route; not reachable from the menu |
| "An ECL calculation is already running" | Another run holds the period lock | Wait for it to finish; if a worker died, clear the lock row for the period |
| Import stays `pending` | No queue worker running or `QUEUE_CONNECTION=sync` in a web context | Start the worker service; check `.env` |
| Import shows failed rows | See the exception CSV from the Imports page | Fix the source rows and re-import; EIR intake dedups on external ids so a re-run does not duplicate |
| EIR contract stays BLOCKED | Readiness issue | Read the blocker code on the Coverage and Blockers page (chapter 6.6) and resolve the data or approval it names |
| GL reconciliation shows NOT_CALCULATED for a posting | No locked EIR or no revenue run for that month | Lock the EIR, run `eir:run-revenue` for the month |
| Trial balance file rejected | Grand total does not tie or period stamp missing | Check the file's grand total row; pass `--period` for the AFS sheet |
| PDF download blank or missing glyphs | DomPDF font cache or memory limit | Ensure `storage/fonts` is writable and `memory_limit` is at least 1G |
| Login CAPTCHA image does not render | GD extension missing | Enable `gd` in php.ini and restart PHP |
| Session expired modal appears on every post | Cookie not sent because `SESSION_SECURE_COOKIE=true` on http | Serve over https or set the flag to false |
| Dashboard empty with "No data available" | No reporting period has ECL calculated | Run the ECL calculation for a period |
| Screenshot command fails to log in | CAPTCHA enabled without `MANUAL_SHOT_CAPTCHA`, or not local environment | Set the bypass code in a local `.env` only |

### 12.6 Known inconsistencies to be aware of

- Three stage columns are read by different parts of the system (chapter 3.3).
- The reports hub and dashboard offer only periods with calculated ECL; stress testing and the extract reports list all loan book periods.
- The ECL reconciliation report cannot populate its written-off row because the feed hardcodes the flag.
- The docker supervisor worker timeout is shorter than several job timeouts.
- `EirRevenueService` has no ACT/360 branch in its year-fraction helper while the solver does.
- Rule approval and schedule approval have no maker and checker gate, unlike fee review and EIR locking.

These are recorded so that support engineers do not rediscover them; each is a candidate ticket.
