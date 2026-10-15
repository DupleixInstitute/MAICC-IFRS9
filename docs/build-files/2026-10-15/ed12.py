p = 'docs/manuals/technical/12-operations-and-troubleshooting.md'
s = open(p, encoding='utf-8').read()


def rep(a, b):
    global s
    assert s.count(a) == 1, a[:70]
    s = s.replace(a, b)


rep("Application logs go to `storage/logs/laravel.log` (daily rotation when `LOG_CHANNEL=daily`). Import jobs log per-row failures at warning level and their exception file path at info level.",
    "Application logs go to `storage/logs/laravel.log`. The shipped `.env.example` sets `LOG_CHANNEL=stack`, which `config/logging.php` sends to that single file; set `LOG_CHANNEL=daily` for daily rotation. Queued jobs log their start, completion and failure; the rows an import refuses are listed on screen and in the import's exception file, not in the log.")

rep("- Vite builds are cached by hash; `npm run build` after every front-end change on the server.",
    "- Vite builds are cached by hash; `npm run build` after every front-end change on the server.\n"
    "- The loan-book build reads the landing-zone ledger once per build and slices it per month (about 4 seconds a month instead of 40). Whole-ledger readers leave the Mega Farm ledger (MF_03, some 300,000 postings) unread unless Mega Farm is asked for (`LandingZoneReader::ledgerByAccount`).\n"
    "- The screens that still read the whole ledger (Write-offs, EIR as at a Date, the RBM return, Restructures & Resets, Fee Classification and the E-Banker feed screen) raise PHP's limits for that request only, to 4 GB and 30 minutes (`app/Support/LedgerReadLimits.php`); the Maintenance and FLI background commands run with 4 GB (`CommandRunner`). `eir:bootstrap`, `ifrs9:segment-ecl` and `ifrs9:smoke-reports` lift the CLI time limit.")

rep("`scripts/deploy.sh` is run from the production checkout by the GitHub deploy workflow after a fast-forward pull of master. It takes a lock, puts the application into maintenance mode, installs Composer dependencies without dev packages, builds assets, runs migrations with `--force`, rebuilds the config, route and view caches, restarts queue workers, brings the application up and optionally hits a health check URL. Manual deployment follows the same steps by hand. Compiled assets under `public/build` are not committed, so a build is always part of a release.",
    "`.github/workflows/deploy.yml` runs on every push to `master` (or by hand). It first runs the CI workflow (`.github/workflows/ci.yml`), then connects to the server over SSH, fetches `master`, pulls it fast-forward only and runs `bash scripts/deploy.sh` in the production checkout. The script, in order:\n\n"
    "1. Takes the deploy lock with `flock` (`DEPLOY_LOCK_FILE`, default `/tmp/maiic-ifrs9-deploy.lock`) where `flock` exists, and stops if another deploy holds it.\n"
    "2. Runs `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress`.\n"
    "3. Runs `npm ci` and `npm run build`. Compiled assets under `public/build` are not committed, so a build is always part of a release. Both happen before the site goes down.\n"
    "4. Runs `php artisan down --retry=60`.\n"
    "5. Runs `php artisan migrate --force`, `optimize:clear`, `config:cache`, `route:cache`, `view:cache`, `storage:link --relative` (a failure of this one is ignored) and `queue:restart`.\n"
    "6. Runs `php artisan up` and, when `HEALTHCHECK_URL` is set, fetches it with `curl --fail` (30 second limit).\n\n"
    "An exit trap brings the application back up if a step fails after maintenance mode was entered. `PHP_BIN`, `COMPOSER_BIN` and `NPM_BIN` override the binaries. The script does not seed: run `php artisan maiic:task update` after a release that adds permissions or governed settings (12.4). Manual deployment follows the same steps by hand.")

rep("| Restart workers after a deploy | `php artisan queue:restart` |",
    "| Restart workers after a deploy | `php artisan queue:restart` |\n"
    "| Lock a month so that no build restates it, or unlock it | Customer & Loan Data, Imports & Feeds, Periods Built (a reason is recorded), or `php artisan eir:lock-period {period} --reason=\"...\" --user={id}`, with `--unlock` to unlock |\n"
    "| Refresh the World Bank series and land new packs from the feed folder | Maintenance, or `php artisan maiic:task refresh-data` |\n"
    "| Check the installation | Maintenance, or `php artisan maiic:task health-check` (system snapshot, migration status, `eir:baselines`, `ifrs9:smoke-reports`; every check runs and the failures are listed at the end) |")

rep("| Run the month-end calculations | Administration, Maintenance, Run the month-end calculations, or `php artisan maiic:task month-end {period} --user={id}` (chapter 9.5); a second person then signs the month off on Forward-Looking Information, FLI Month-End, Overview |",
    "| Run the month-end calculations | Administration & support, Support & Maintenance, Maintenance, Run the month-end calculations, or `php artisan maiic:task month-end {period} --user={id}` (chapter 9). The steps, in order: `clients:link-loan-book`, `eir:load-schemes`, `ifrs9:segment-portfolios`, `eir:restructure-candidates` (detection only), `eir:stage`, `ifrs9:segment-parameters`, `fli:month-end --all`, `eir:load-ledger-cash`, `eir:build-gl-fees`, `eir:run-revenue`, then `megafarm:ecl` (skipped when the month has no Mega Farm loans). It stops at the first step that fails. A second person then signs the month off on Forward-Looking Information, FLI Month-End, Overview |")

rep("| Apply a release's database updates and seeders | Administration, Maintenance, Update the system after a new release, or `php artisan maiic:task update` |",
    "| Apply a release's database updates and seeders | Maintenance, Update the system after a new release, or `php artisan maiic:task update`: `migrate --force`, `permission:cache-reset`, then the seeders `MaiicAdminPermissionsSeeder`, `TicketsPermissionsSeeder`, `GovernanceSettingsSeeder` and `SicrQualitativeFactorsSeeder` (the last two additive) |")

rep("| Menu item returns 403 for the administrator | Permission missing from the database | Run `MaiicAdminPermissionsSeeder`, `TicketsPermissionsSeeder` and sync the admin role to all permissions |",
    "| Menu item returns 403 for the administrator | Permission missing from the database, or a stale cached permission list | Run `php artisan maiic:task update` (it resets the permission cache and runs `MaiicAdminPermissionsSeeder` and `TicketsPermissionsSeeder`) |")

rep("| \"An ECL calculation is already running\" | Another run holds the period lock | Wait for it to finish; if a worker died, clear the lock row for the period |",
    "| \"An ECL calculation for ... is already running\" | Another request holds the MySQL named lock for that period, level and scope (`GET_LOCK` in `ExpectedCreditLossController`) | Wait for it to finish. A named lock is released when its database connection closes, so a request that died frees it by itself; there is no row to clear |")

rep("| Import shows failed rows | See the exception CSV from the Imports tab (Customer & Loan Data, Imports & Feeds) | Fix the source rows and re-import; EIR intake dedups on external ids so a re-run does not duplicate |",
    "| Import refused before anything is written | The file failed the import's check (`ImportSpecValidator`): a missing column, a wrong type or format, a value not allowed | The screen lists each problem by row with how to fix it. Download the sample file and the column guide from the import's panel, correct the file and use Check file before importing again |\n"
    "| Import shows failed rows | See the exception CSV from the Imports tab (Customer & Loan Data, Imports & Feeds) | Fix the source rows and re-import; EIR intake dedups on external ids so a re-run does not duplicate |\n"
    "| \"Reporting period ... is locked: ... cannot be written\" | The month is locked (`reporting_period_locks`); every writer checks `ReportingPeriodLock` | A locked month is never restated. If the restatement is deliberate, unlock it with a reason on Imports & Feeds, Periods Built or with `eir:lock-period {period} --unlock` |\n"
    "| Approval refused: \"the person who proposed (prepared, recorded) ... cannot approve it\" | Maker-checker: the person who prepared or proposed the item tried to approve it (a schedule, a write-off, a restructure or rate-reset classification, the staging thresholds, a governed setting, a qualitative SICR trigger, the FLI sign-off) | A second person approves |")

rep("| Sign-off refused | The weighted ECL does not reconcile to the booked ECL, the movement exceeds `fli_movement_commentary` without a commentary, or the signer recorded the month | Record the month again after the ECL run, add the commentary, or have a second person sign |",
    "| Sign-off refused | The weighted ECL does not reconcile to the booked ECL; the discounted allowance weighted over the scenarios does not tie to the one booked, or a loan has no per-scenario figures; the movement exceeds `fli_movement_commentary` without a commentary; some loans have no discounted allowance and no commentary says why; or the signer recorded the month (`FliCloseService::signOff`) | Run the ECL again and record the month again, add the commentary, or have a second person sign |")

rep("| Trial balance file rejected | Grand total does not tie or period stamp missing | Check the file's grand total row; pass `--period` for the AFS sheet |",
    "| Trial balance file rejected | A gate of `TrialBalanceLandingService` failed: the month is not known, debits do not equal credits, or the report's grand total does not tie | Check the file's period stamp and grand total row. On the command line `eir:import-trial-balances` takes `--afs`, `--sheet` and `--period` for the AFS bridge sheet |\n"
    "| E-Banker pack refused at the door | A gate in `PackGates` failed, for example the row counts or hashes against the manifest, or the fee GL gate (the pack's GL_04a movement on a fee GL differs from the trial balance by a kwacha or more) | The load on Imports & Feeds, E-Banker Loads lists each gate with its detail; correct or replace the pack |")

rep("- The ECL reconciliation report cannot populate its written-off row because the feed hardcodes the flag.\n", "")

rep("- `EirRevenueService` has no ACT/360 branch in its year-fraction helper while the solver does.\n- Rule approval and schedule approval have no maker and checker gate, unlike fee review and EIR locking.",
    "- `EirRevenueService` has no ACT/360 branch in its year-fraction helper (ACT/360 is counted as ACT/365) while the solver and the schedule generator have one.\n"
    "- Accounting rule approval has no maker and checker gate: the person who edits a rule may approve it, unlike schedule approval (`schedule_approval_control`), fee review, write-offs, restructures and EIR locking.")

open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
