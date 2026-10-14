## 12. Operations and Troubleshooting

### 12.1 Logging

Application logs go to `storage/logs/laravel.log`. The shipped `.env.example` sets `LOG_CHANNEL=stack`, which `config/logging.php` sends to that single file; set `LOG_CHANNEL=daily` for daily rotation. Queued jobs log their start, completion and failure; the rows an import refuses are listed on screen and in the import's exception file, not in the log. The queue worker's own output should be captured by the service manager (see the Installation Guide). Audit events are in the database (chapter 8.5), not in the log files.

### 12.2 Performance notes

- OPcache must be enabled; it was found disabled on a development server and cost 1.4 seconds per request.
- The Inertia shared props were slimmed to about 8 KB per response; the user's permissions are sent as a flat list and the menu and notification list are lazy.
- The ECL summary and dashboard use single grouped queries and composite indexes on `expected_credit_loss` and `loan_books`; the ECL page renders in about 90 milliseconds on the development data.
- Settings are cached for sixty seconds in the middleware; clear with `php artisan cache:clear` after editing them outside the UI.
- Vite builds are cached by hash; `npm run build` after every front-end change on the server.
- The loan-book build reads the landing-zone ledger once per build and slices it per month (about 4 seconds a month instead of 40). Whole-ledger readers leave the Mega Farm ledger (MF_03, some 300,000 postings) unread unless Mega Farm is asked for (`LandingZoneReader::ledgerByAccount`).
- The screens that still read the whole ledger (Write-offs, EIR as at a Date, the RBM return, Restructures & Resets, Fee Classification and the E-Banker feed screen) raise PHP's limits for that request only, to 4 GB and 30 minutes (`app/Support/LedgerReadLimits.php`); the Maintenance and FLI background commands run with 4 GB (`CommandRunner`). `eir:bootstrap`, `ifrs9:segment-ecl` and `ifrs9:smoke-reports` lift the CLI time limit.

### 12.3 Deployment

`.github/workflows/deploy.yml` runs on every push to `master` (or by hand). It first runs the CI workflow (`.github/workflows/ci.yml`), then connects to the server over SSH, fetches `master`, pulls it fast-forward only and runs `bash scripts/deploy.sh` in the production checkout. The script, in order:

1. Takes the deploy lock with `flock` (`DEPLOY_LOCK_FILE`, default `/tmp/maiic-ifrs9-deploy.lock`) where `flock` exists, and stops if another deploy holds it.
2. Runs `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress`.
3. Runs `npm ci` and `npm run build`. Compiled assets under `public/build` are not committed, so a build is always part of a release. Both happen before the site goes down.
4. Runs `php artisan down --retry=60`.
5. Runs `php artisan migrate --force`, `optimize:clear`, `config:cache`, `route:cache`, `view:cache`, `storage:link --relative` (a failure of this one is ignored) and `queue:restart`.
6. Runs `php artisan up` and, when `HEALTHCHECK_URL` is set, fetches it with `curl --fail` (30 second limit).

An exit trap brings the application back up if a step fails after maintenance mode was entered. `PHP_BIN`, `COMPOSER_BIN` and `NPM_BIN` override the binaries. The script does not seed: run `php artisan maiic:task update` after a release that adds permissions or governed settings (12.4). Manual deployment follows the same steps by hand.

### 12.4 Routine operations

| Task | How |
|---|---|
| Clear caches after configuration changes | `php artisan config:clear && php artisan cache:clear && php artisan view:clear` |
| Put the site into maintenance during a long import | `php artisan down --secret=<token>` then `php artisan up` |
| Inspect failed jobs | `php artisan queue:failed`, retry with `queue:retry all` |
| Restart workers after a deploy | `php artisan queue:restart` |
| Lock a month so that no build restates it, or unlock it | Customer & Loan Data, Imports & Feeds, Periods Built (a reason is recorded), or `php artisan eir:lock-period {period} --reason="..." --user={id}`, with `--unlock` to unlock |
| Refresh the World Bank series and land new packs from the feed folder | Maintenance, or `php artisan maiic:task refresh-data` |
| Check the installation | Maintenance, or `php artisan maiic:task health-check` (system snapshot, migration status, `eir:baselines`, `ifrs9:smoke-reports`; every check runs and the failures are listed at the end) |
| Refresh manual screenshots after UI changes | `php artisan manual:screenshots` with the app running locally |
| Regenerate the schema appendix of this manual | Nothing to do; it is read live |
| Run the month-end calculations | Administration & support, Support & Maintenance, Maintenance, Run the month-end calculations, or `php artisan maiic:task month-end {period} --user={id}` (chapter 9). The steps, in order: `clients:link-loan-book`, `eir:load-schemes`, `ifrs9:segment-portfolios`, `eir:restructure-candidates` (detection only), `eir:stage`, `ifrs9:segment-parameters`, `fli:month-end --all`, `eir:load-ledger-cash`, `eir:build-gl-fees`, `eir:run-revenue`, then `megafarm:ecl` (skipped when the month has no Mega Farm loans). It stops at the first step that fails. A second person then signs the month off on Forward-Looking Information, FLI Month-End, Overview |
| Apply a release's database updates and seeders | Maintenance, Update the system after a new release, or `php artisan maiic:task update`: `migrate --force`, `permission:cache-reset`, then the seeders `MaiicAdminPermissionsSeeder`, `TicketsPermissionsSeeder`, `GovernanceSettingsSeeder` and `SicrQualitativeFactorsSeeder` (the last two additive) |

### 12.5 Common failures and fixes

| Symptom | Cause | Fix |
|---|---|---|
| Menu item returns 403 for the administrator | Permission missing from the database, or a stale cached permission list | Run `php artisan maiic:task update` (it resets the permission cache and runs `MaiicAdminPermissionsSeeder` and `TicketsPermissionsSeeder`) |
| Error "permission does not exist" on a typed URL | Legacy route whose permission was removed | Expected until ticket #009 deletes the route; not reachable from the menu |
| "An ECL calculation for ... is already running" | Another request holds the MySQL named lock for that period, level and scope (`GET_LOCK` in `ExpectedCreditLossController`) | Wait for it to finish. A named lock is released when its database connection closes, so a request that died frees it by itself; there is no row to clear |
| Import stays `pending` | No queue worker running or `QUEUE_CONNECTION=sync` in a web context | Start the worker service; check `.env` |
| Import refused before anything is written | The file failed the import's check (`ImportSpecValidator`): a missing column, a wrong type or format, a value not allowed | The screen lists each problem by row with how to fix it. Download the sample file and the column guide from the import's panel, correct the file and use Check file before importing again |
| Import shows failed rows | See the exception CSV from the Imports tab (Customer & Loan Data, Imports & Feeds) | Fix the source rows and re-import; EIR intake dedups on external ids so a re-run does not duplicate |
| "Reporting period ... is locked: ... cannot be written" | The month is locked (`reporting_period_locks`); every writer checks `ReportingPeriodLock` | A locked month is never restated. If the restatement is deliberate, unlock it with a reason on Imports & Feeds, Periods Built or with `eir:lock-period {period} --unlock` |
| Approval refused: "the person who proposed (prepared, recorded) ... cannot approve it" | Maker-checker: the person who prepared or proposed the item tried to approve it (a schedule, a write-off, a restructure or rate-reset classification, the staging thresholds, a governed setting, a qualitative SICR trigger, the FLI sign-off) | A second person approves |
| EIR contract stays BLOCKED | Readiness issue | Read the blocker code on EIR Calculations, Blockers (chapter 6.6) and resolve the data or approval it names |
| `fli:month-end --all` or the month-end job stops before the route | The loan book, model version, scenario set or overlays step needs a person | Do what the step names on its screen (publish a model version, approve the scenario set, review the overlays), then run it again |
| The forward-looking route fails for a month | No actual of the model's driver is known for the lagged base month, or a scenario has no path value for the year the window needs | Load the series on Macro Statistics or extend the set's path; the PD is not adjusted until then |
| The month cannot be recorded | The ECL is not on the post-FLI PD, was calculated before the route last ran, or the loans ran under more than one scenario set | Apply the route again if needed, run `ifrs9:segment-ecl {period} --pd=pd_post_fli`, then record |
| Sign-off refused | The weighted ECL does not reconcile to the booked ECL; the discounted allowance weighted over the scenarios does not tie to the one booked, or a loan has no per-scenario figures; the movement exceeds `fli_movement_commentary` without a commentary; some loans have no discounted allowance and no commentary says why; or the signer recorded the month (`FliCloseService::signOff`) | Run the ECL again and record the month again, add the commentary, or have a second person sign |
| "... is still running" on Maintenance or the FLI Overview | One background command runs at a time | Wait for it to finish; its output is under `storage/app/command-runs` |
| GL reconciliation shows NOT_CALCULATED for a posting | No locked EIR or no revenue run for that month | Lock the EIR, run `eir:run-revenue` for the month |
| Trial balance file rejected | A gate of `TrialBalanceLandingService` failed: the month is not known, debits do not equal credits, or the report's grand total does not tie | Check the file's period stamp and grand total row. On the command line `eir:import-trial-balances` takes `--afs`, `--sheet` and `--period` for the AFS bridge sheet |
| E-Banker pack refused at the door | A file failed its check against the manifest in `PackLandingService` (SHA-256, row count, query id), or a cross-file gate in `PackGates` failed: accounts in the master, balance history ties, month-end rows, or the fee GL gate (the pack's GL_04a movement on a fee GL differs from the trial balance by a kwacha or more) | The load on Imports & Feeds, E-Banker Loads lists each gate with its detail; correct or replace the pack |
| PDF download blank or missing glyphs | DomPDF font cache or memory limit | Ensure `storage/fonts` is writable and `memory_limit` is at least 1G |
| Login CAPTCHA image does not render | GD extension missing | Enable `gd` in php.ini and restart PHP |
| Session expired modal appears on every post | Cookie not sent because `SESSION_SECURE_COOKIE=true` on http | Serve over https or set the flag to false |
| Dashboard empty with "No data available" | No reporting period has ECL calculated | Run the ECL calculation for a period |
| Screenshot command fails to log in | CAPTCHA enabled without `MANUAL_SHOT_CAPTCHA`, or not local environment | Set the bypass code in a local `.env` only |

### 12.6 Known inconsistencies to be aware of

- Three stage columns are read by different parts of the system (chapter 3.3).
- The reports hub and dashboard offer only periods with calculated ECL; stress testing and the extract reports list all loan book periods.
- The docker supervisor worker timeout is shorter than several job timeouts.
- `EirRevenueService` has no ACT/360 branch in its year-fraction helper (ACT/360 is counted as ACT/365) while the solver and the schedule generator have one.
- Accounting rule approval has no maker and checker gate: the person who edits a rule may approve it, unlike schedule approval (`schedule_approval_control`), fee review, write-offs, restructures and EIR locking.

These are recorded so that support engineers do not rediscover them; each is a candidate ticket.
