## 9. Jobs, Commands and Scheduling

### 9.1 Queue configuration

`config/queue.php` defaults to the `QUEUE_CONNECTION` environment variable, which `.env.example` sets to `database`; without it Laravel falls back to `sync`, which runs jobs inline in the web request and is unsuitable for imports. All jobs use the `default` queue; no job names a queue, chain or batch. A worker must run continuously in production:

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=7200
```

The worker timeout must be at least as long as the longest job timeout below. The Docker supervisor configuration shipped in `docker/supervisord.conf` uses 500 seconds, which is shorter than several jobs; the Installation Guide gives a systemd unit with the correct value. `scripts/deploy.sh` calls `queue:restart` after each deployment so workers pick up new code.

### 9.2 Jobs

| Job | Purpose | Dispatched from | Timeout and tries |
|---|---|---|---|
| `ProcessLoanImportJob` | Parse the E-Banker grouped CSV and upsert `loan_books` in batches of 500 | `LoanImportService` | defaults |
| `ProcessEirImportJob` | Read a mapped EIR extract and route rows to the matching EIR service; writes the exception CSV and audit log | `EirIntakeController::import()` | 600 s |
| `CalculateEirJob` | Solve the EIR for a batch of contracts; a blocked contract does not stop the batch | `EirCalculationController::calculate()` | 600 s |
| `RunEirRevenueJob` | Monthly amortised-cost and interest revenue roll-forward over locked contracts | `eir:run-revenue --queue` | 600 s |
| `CalculateDiscountingJob` | Discount LGD recovery payments at the resolved EIR | `LossGiveDefaultController` | 3600 s, 3 tries |
| `ProcessLGDPayments` | Build payment tracking for stage 3 contracts of an LGD run in chunks of 500 | `LGDCalculationController` | 7200 s, 3 tries |
| `CalculateLGDJob`, `ProcessLGDChunkJob` | Chunked LGD computation; the master dispatch is currently commented out in the controller | (inactive) | defaults |
| `ProcessCampaign` | Legacy communication campaign sender | legacy controllers and scheduler | defaults |

Failed jobs land in `failed_jobs`; inspect with `php artisan queue:failed` and retry with `php artisan queue:retry {id}`.

The Maintenance console and the forward-looking Overview do not use the queue: they launch artisan commands as detached background processes (9.5), so they run without a queue worker.

### 9.3 Commands

| Command | Purpose |
|---|---|
| `maiic:task {task} [period] [--user=]` | Run one of the Maintenance console jobs (`update`, `month-end`, `refresh-data`, `health-check`): its steps in order, stopping at the first that fails (9.5) |
| `fli:month-end {period} [--all] [--project] [--close] [--user=]` | The forward-looking month-end: show the ten steps and where each stands, project the scenarios, record the month, or run every remaining automated step (9.5) |
| `fli:correlate {period} [--no-refresh] [--top=15] [--user=1]` | Refresh the FLI bridge, profile the series, sweep the correlations under the guardrail and fit the candidates (chapter 5.5) |
| `fli:apply {period} [--fits] [--propose=] [--approve=] [--bootstrap] [--user=] [--top=10]` | List the applied fits of a period, propose or approve one (approval publishes a model version), or apply the route in force to every loan |
| `fli:method-cards [--period=] [--json]` | Show each transmission method card with its preconditions checked against the data |
| `scenario:sets {period} [--seed] [--approve=] [--lock=] [--bootstrap] [--user=]` | List, seed, validate, approve and lock the governed scenario sets of a period, with their back-test and sensitivity |
| `ifrs9:segment-ecl {from} [to] [--pd=pd_post_fli] [--lgd=collection_lgd] [--discounting=undiscounted] [--prune-stale]` | Calculate the ECL of every portfolio of each month, failing closed on a missing stage, PD or LGD (chapter 5.6) |
| `ifrs9:segment-parameters {from} [to] [--pd] [--lgd] [--window=12] [--user=] [--dry-run]` | Measure and apply the PD and the LGD by segment on the governed basis, month by month |
| `eir:stage {from} [to] [--user=] [--dry-run]` | Stage the loan book of each period: days past due against the governed thresholds, then the missed-instalment trigger and the approved qualitative SICR triggers |
| `clients:link-loan-book [--user=]` | Create a client for every loan-book customer and link every loan to its client |
| `eir:load-ledger-cash [--to=] [--user=]` | Load the actual cash of every loan from the landed E-Banker ledger for the EIR revenue engine |
| `megafarm:ecl {period} [--user=] [--bootstrap]` | Run the Mega Farm programme through the ECL module |
| `macro:import-worldbank [--code=] [--country=] [--from=] [--to=] [--user=1] [--offline] [--snapshot]` | Import the World Bank macro series for Malawi with provenance; falls back to the committed snapshot |
| `eir:poll-feed-folder [folder] [--user=1] [--also-route-3]` | Land every new E-Banker pack found in the feed folder through the gates |
| `eir:baselines [--json]` | Run the acceptance ties and golden numbers against the database |
| `ifrs9:recalculate-ecl {period} [--level] [--portfolio] [--sector] [--pd] [--lgd] [--discounting]` | Run the ECL calculation from the command line with the same logic as the screen |
| `ifrs9:generate-2025 [--dry-run]` | Calibrate PD and LGD from the November 2025 basis and fill the 2025 months |
| `ifrs9:segment-portfolios [--dry-run] [--rollback]` | Derive portfolios from product groups and back-fill the sector tag |
| `ifrs9:seed-agri-risk [--dry-run]` | Model agricultural credit enhancements |
| `ifrs9:seed-report-fields [--dry-run]` | Derive empty report fields from trusted columns |
| `ifrs9:smoke-reports [period]` | Render every hub report and its PDF as a smoke test |
| `eir:generate-schedules [--dry-run]` | Draft version 1 schedules for contracts with Extract A terms |
| `eir:run-revenue {period} [--contract=*] [--queue] [--recalculate --reason=]` | Monthly EIR revenue run; recalculation requires a reason and voids later periods |
| `eir:import-trial-balances {directory} [--afs=] [--sheet=] [--period=]` | Import the monthly trial balances and the AFS pre-closing December sheet |
| `eir:make-test-data [--out] [--start] [--periods] [--load]` | Generate a synthetic loan book and Extracts A, B and C for end-to-end testing |
| `manual:screenshots [--base-url] [--email] [--password] [--only] [--edge]` | Capture the manual screenshots with headless Chromium |
| `permissions:reset` | Reset the permissions table from the seeders |
| `campaigns:process-recurring`, `campaigns:process-scheduled`, `reminders:loan-applications`, `expenses:process-recurring` | Legacy commands from the loan-origination era |
| `app:fix-script`, `app:playground`, `command:name` | Legacy stubs; `app:playground` calls an external API and should be deleted under ticket #009 |

### 9.4 Scheduler

`app/Console/Kernel.php` schedules two data feeds and the three legacy commands: `eir:poll-feed-folder --also-route-3` daily at 06:30 without overlapping (the E-Banker pack lands on the first working day after month-end), `macro:import-worldbank` on the second of each month at 07:00, and the recurring campaigns daily, scheduled campaigns every five minutes and loan application reminders every two minutes. These are the same two steps as the `refresh-data` job of 9.5. The month-end calculations are not scheduled: they need an approved loan book build, a model version in force and an approved scenario set, and are run by hand from Administration, Maintenance or with `maiic:task month-end`. The scheduler itself needs one cron entry on the server:

```
* * * * * cd /var/www/maiic-ifrs9 && php artisan schedule:run >> /dev/null 2>&1
```

When the monthly run is automated, add it here with `->monthlyOn(day, time)` after the extracts are confirmed loaded.

### 9.5 Maintenance jobs and the forward-looking month-end

Administration, Maintenance (`MaintenanceController`, route `maintenance.index`, gated by `permission:settings`; running a command needs the `admin` role) runs a fixed whitelist of artisan commands from the browser. The browser sends only a catalogue key and, for a month-end entry, a month the system holds a loan book for; the command line is built from a template on the server. `app/Services/Maintenance/CommandRunner.php` launches the command as a detached background process, writes its output to `storage/app/command-runs/{id}.log` with a `{id}.json` metadata file, and the page polls the log for live output. One command runs at a time; a second launch while one is running is refused. Each launch is logged in `audit_logs` as `Maintenance Command Launched`. The destructive bootstrap entry needs its confirmation phrase typed.

The consolidated jobs are `php artisan maiic:task {task}` (`app/Console/Commands/MaintenanceTask.php`). Each runs its steps in order through `Artisan::call` with the output streamed, and stops at the first step that fails, naming it; the steps before it stay done.

| Job | Steps, in order |
|---|---|
| `update` | `migrate --force`; `db:seed` with `MaiicAdminPermissionsSeeder`, `GovernanceSettingsSeeder` and `SicrQualitativeFactorsSeeder` (additive) |
| `month-end {period} [--user=]` | `clients:link-loan-book`; `eir:stage {period}`; `ifrs9:segment-parameters {period}`; `fli:month-end {period} --all`; `eir:load-ledger-cash`; `eir:run-revenue {period}`; `megafarm:ecl {period}`, which is optional: if it fails the job reports it as skipped (the month has no Mega Farm loans) and finishes |
| `refresh-data` | `macro:import-worldbank`; `eir:poll-feed-folder` |
| `health-check` | `about`; `migrate:status`; `eir:baselines`; `ifrs9:smoke-reports`. A failing check is recorded and the other checks still run; the job fails at the end naming them |

The Maintenance page also offers `eir:build-loan-books {period}` (the build is approved by a second person on Imports & Feeds, Loan Book Builds, before the calculations run) and `fli:correlate {period}` for the annual recalibration, which writes candidate fits only.

`php artisan fli:month-end {period}` (`app/Console/Commands/FliMonthEndCommand.php`) follows the ten steps of `FliMonthEnd` (chapter 5.5). With no option it prints each step, its state (done, todo, blocked, warn, optional) and where it stands. `--project` runs `FliProjectionService::run()`; `--close` runs `FliCloseService::record()` and prints booked against weighted ECL, whether they tie, the ECL per scenario and, when the movement exceeds the tolerance, that a commentary is needed. `--all` first checks the loan book, model version, scenario set and overlays steps and stops with the reason if any is blocked or waiting, because each then needs a person (an import or an approval); the economic data step is shown but does not stop it. It then applies the route unless it is already applied under the version and set in force, runs the projection, runs `ifrs9:segment-ecl {period} --pd=pd_post_fli`, and records the month. The command never approves anything: the fit or external model, the scenario set, the overlays and the sign-off stay with people, and the sign-off is made by a second person on Forward-Looking Information, FLI Month-End, Overview.

The Overview runs the same automated steps in the background through `CommandRunner`, each a fixed template in `FliModuleController`: `fli:correlate {period}`, `fli:apply {period}`, `fli:month-end {period} --project`, `ifrs9:segment-ecl {period} --pd=pd_post_fli`, `fli:month-end {period} --close` and `fli:month-end {period} --all`. Running a step needs `eir.govern`, and each launch is logged as `FLI Step Launched`. The Workspace close checklist item "Forward-looking month-end (route, record, sign-off)" is done when the Overview's last step, the month recorded and signed off, is done.
