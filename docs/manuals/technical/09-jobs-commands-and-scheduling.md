## 9. Jobs, Commands and Scheduling

### 9.1 Queue configuration

`config/queue.php` defaults to the `QUEUE_CONNECTION` environment variable, which `.env.example` sets to `database`; without it Laravel falls back to `sync`, which runs jobs inline in the web request and is unsuitable for imports. All jobs use the `default` queue; no job names a queue, chain or batch. A worker must run continuously in production:

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=7200
```

The worker timeout must be at least as long as the longest job timeout below. The Docker supervisor configuration shipped in `docker/supervisord.conf` uses 500 seconds, which is shorter than several jobs; the Installation Guide gives a systemd unit with the correct value. `scripts/deploy.sh` calls `queue:restart` after each deployment so workers pick up new code.

`eir:bootstrap` sets the queue to `sync` for its own run, so a clean install needs no worker.

### 9.2 Jobs

| Job | Purpose | Dispatched from | Timeout and tries |
|---|---|---|---|
| `ProcessLoanImportJob` | Parse the E-Banker grouped CSV and upsert `loan_books` in batches of 500 | `LoanImportService` | defaults |
| `ProcessEirImportJob` | Read a mapped EIR extract and route rows to the matching EIR service; writes the exception CSV and audit log | `EirIntakeController::import()` | 600 s |
| `CalculateEirJob` | Solve the EIR for a batch of contracts; a blocked contract does not stop the batch | `EirCalculationController::calculate()` | 600 s |
| `RunEirRevenueJob` | Monthly amortised-cost and interest revenue roll-forward over locked contracts | `eir:run-revenue --queue`; run inline (not queued) by `eir:run-revenue` without `--queue` and by `EirReconciliationController` | 600 s |
| `CalculateDiscountingJob` | Discount LGD recovery payments at the resolved EIR | `LossGiveDefaultController` | 3600 s, 3 tries |
| `ProcessLGDPayments` | Build payment tracking for stage 3 contracts of an LGD run in chunks of 500 | `LGDCalculationController::store()` | 7200 s, 3 tries |
| `CalculateLGDJob`, `ProcessLGDChunkJob` | Chunked LGD computation; the master dispatch is currently commented out in the controller | (inactive) | defaults |
| `ProcessCampaign` | Legacy communication campaign sender | `CommunicationCampaignController` and `campaigns:process-scheduled` | defaults |

Failed jobs land in `failed_jobs`; inspect with `php artisan queue:failed` and retry with `php artisan queue:retry {id}`.

The Maintenance console and the forward-looking Overview do not use the queue: they launch artisan commands as detached background processes (9.5), so they run without a queue worker.

### 9.3 Commands

Every command below lives in `app/Console/Commands` and is loaded by `Kernel::commands()`. The signatures are as `php artisan help <name>` prints them; `--user=` is the id of the person the run is recorded against.

| Command | Purpose |
|---|---|
| `eir:bootstrap [--fresh] [--force-wipe] [--with-client-inputs] [--build] [--run-engines] [--verify] [--from=2024-07] [--to=] [--user=1] [--inputs=docs/bootstrap] [--live-macro]` | Clean install: seed, land the committed client inputs, build, run the engines and verify the golden numbers (9.6) |
| `maiic:task {task} [period] [--user=]` | Run one of the Maintenance console jobs (`update`, `month-end`, `refresh-data`, `health-check`): its steps in order, stopping at the first that fails (9.5) |
| `eir:land-pack {folder} [--route=ROUTE_1_MANUAL] [--user=] [--dry-run]` | Land an E-Banker pack (the CSV files and `manifest.json`) into the landing zone through its gates |
| `eir:poll-feed-folder [folder] [--user=1] [--also-route-3]` | Land every new pack found in the feed folder (route 2; default `EBANKER_FEED_FOLDER`) through the gates; `--also-route-3` also lands the packs the API posted into `storage/app/ebanker-inbox`. With no folder set it warns and lands nothing |
| `eir:land-takeon [--original=] [--mapping=] [--user=] [--build] [--approve-build=] [--dry-run]` | Land the take-on workbook through its gates (defaults: the committed copies under `docs/bootstrap/takeon`); `--build` proposes the take-on population build and `--approve-build=` approves it as a different person |
| `eir:import-trial-balances {directory} [--afs=] [--sheet="Final E-Banker TB Dec 2025"] [--period=2025-12-01] [--user=]` | Land the monthly trial balances (and the AFS pre-closing December sheet) in the landing zone and derive the GL side of the EIR reconciliation |
| `eir:build-loan-books [from] [to] [--method=] [--file=] [--user=] [--approve-by=] [--bootstrap] [--approve=] [--retire-stale] [--dry-run]` | Build `loan_books` for a range of months from the landing zone by method A (bootstrap), B (derive) or C (the printed report, `--file=`), under maker-checker: without `--approve-by` or `--bootstrap` the build is only proposed |
| `eir:lock-period {period} [--user=] --reason= [--unlock]` | Lock (or unlock) a reporting period so that no build restates it; the lock keeps a snapshot of the governance values in force. `--reason` is required |
| `eir:load-schemes` | Load the scheme settings from the landed scheme master (`SM_01`, then `DD_10` where it lacks a field) and flag contracts drawn above their sanction; fails when no scheme master is landed |
| `eir:restructure-candidates [--only=]` | Find candidate restructures in the reschedule history (`RS_02`) and record the rate moves of floating loans on the rate-reset register; detection only, nothing is confirmed. `--only=candidates` or `--only=resets` runs one half. Runs with 4 GB of memory and no time limit |
| `eir:build-gl-fees [--user=]` | Tie the GL fee postings (`GL_04`) to their loans, hold what cannot be tied with the reason, apply `fee_source_precedence` and check the fee GLs against the trial balance; every line lands PENDING for Fee Classification |
| `eir:load-ledger-cash [--to=] [--user=]` | Load the actual cash of every loan from the landed E-Banker ledger for the EIR revenue engine |
| `eir:import-disbursements {file} [--user=]` | Import the per-drawdown extract (one row per tranche) with ISO-date and positive-amount checks |
| `eir:import-reference-rates {file} [--index=PLR] [--user=]` | Import the reference-rate series (the Reserve Bank prime lending rate) with ISO-date and ordering checks |
| `eir:derive-spreads [--period=] [--index=PLR] [--user=]` | Derive each contract's spread over the reference rate from the loan books and flag any that drifts |
| `eir:generate-schedules [--dry-run] [--user=]` | Generate draft version 1 schedules from Extract A terms for a second person to review and approve |
| `eir:stage {from} [to] [--user=] [--dry-run]` | Stage the loan book of each period: days past due against the governed thresholds, then the missed-instalment trigger and the approved qualitative SICR triggers |
| `eir:run-revenue {period} --user= [--contract=*] [--queue] [--recalculate --reason=]` | Monthly EIR revenue run; `--user` is required (the run is refused without a real user), and recalculation requires a reason and voids later periods |
| `eir:as-at {date} [--contract=] [--json]` | Show the EIR computation for a loan, or the book, as at any date: amortised cost, gross, EIR and contractual interest to the date, and the difference |
| `eir:baselines [--json]` | Run the acceptance ties, the pack inventory and the golden numbers against the database (the same checks as `eir:bootstrap --verify`) |
| `eir:make-test-data [--out=] [--start=2024-01] [--periods=22] [--load]` | Generate a synthetic loan book and Extracts A, B and C for end-to-end testing |
| `clients:link-loan-book [--user=]` | Create a client for every loan-book customer and link every loan to its client |
| `ifrs9:segment-portfolios [--dry-run] [--rollback]` | Give each lending programme (E-Banker product group) its own portfolio and move its loans into it, so the Mega Farm schemes are kept apart from MAIIC's book; `--rollback` maps every loan back to portfolio 1 |
| `ifrs9:segment-parameters {from} [to] [--pd] [--lgd] [--window=12] [--user=] [--dry-run]` | Measure and apply the PD and the LGD by segment on the governed basis, month by month |
| `ifrs9:segment-ecl {from} [to] [--pd=pd_post_fli] [--lgd=collection_lgd] [--discounting=discounted] [--prune-stale]` | Calculate the ECL of every portfolio of each month, failing closed on a missing stage, PD or LGD and refusing a locked month; the Mega Farm programme's portfolio is left to `megafarm:ecl`. The default is the discounted allowance (IFRS 9 5.5.17(b)); `--discounting=undiscounted` gives the engine measure (chapter 5.6) |
| `ifrs9:recalculate-ecl {period} [--level=portfolio] [--portfolio=] [--sector=] [--pd=pd_prefli] [--lgd=collection_lgd] [--discounting=undiscounted]` | Run the ECL calculation for one portfolio or sector from the command line with the same logic as the screen |
| `megafarm:ecl {period} [--user=] [--bootstrap]` | Run the Mega Farm programme through the ECL module: the governed scope, PD method, scalar and MAIIC share |
| `fli:month-end {period} [--all] [--project] [--close] [--user=]` | The forward-looking month-end: show the ten steps and where each stands, project the scenarios, record the month, or run every remaining automated step (9.5) |
| `fli:correlate {period} [--no-refresh] [--top=15] [--user=1]` | Refresh the FLI bridge, profile the series, sweep the correlations under the guardrail and fit the candidates (chapter 5.5) |
| `fli:apply {period} [--fits] [--propose=] [--approve=] [--bootstrap] [--user=] [--top=10]` | List the applied fits of a period, propose or approve one (approval publishes a model version), or apply the route in force to every loan |
| `fli:method-cards [--period=] [--json]` | Show each transmission method card with its preconditions checked against the data |
| `scenario:sets {period} [--seed] [--approve=] [--lock=] [--bootstrap] [--user=]` | List, seed, validate, approve and lock the governed scenario sets of a period, with their back-test and sensitivity |
| `macro:import-worldbank [--code=] [--country=] [--from=] [--to=] [--user=1] [--offline] [--snapshot]` | Import the World Bank macro series for Malawi with provenance; `--offline` reads the committed snapshot `docs/bootstrap/macro/worldbank-MWI.json`, `--snapshot` rewrites it after a fetch, and a failed fetch falls back to it |
| `ifrs9:generate-2025 [--dry-run]` | Calibrate PD and LGD from the November 2025 basis, fill the 2025 gap months and run per-portfolio ECL for 2025 |
| `ifrs9:seed-agri-risk [--dry-run]` | Model agricultural credit enhancements |
| `ifrs9:seed-report-fields [--dry-run]` | Derive empty report fields from trusted columns |
| `ifrs9:smoke-reports [period=2025-11]` | Render every hub report and its PDF as a smoke test |
| `compliance:audits [--load] [--export-signed] [--pack=]` | Load the compliance register from the workbooks, export the signed state, or build the auditor's pack for a period |
| `manual:screenshots [--base-url=] [--email=] [--password=] [--out=] [--only=] [--edge=] [--theme=]` | Capture the manual screenshots with headless Chromium (default output `public/manual/screenshots`; `--theme` is light, dark or system) |
| `manual:reseed [manual=user] --force` | Replace the User or Administrator Manual (`user` or `admin`) with the shipped seed content; `--force` confirms that authored edits are discarded |
| `permissions:reset` | Reset the permissions table from the seeders |
| `campaigns:process-recurring`, `campaigns:process-scheduled`, `reminders:loan-applications`, `expenses:process-recurring` | Legacy commands from the loan-origination era |
| `app:fix-script`, `app:playground`, `command:name` | Legacy stubs; `app:playground` calls an external API and should be deleted under ticket #009 |

The console lifts PHP's time limit where a controller the command calls lowers it to 300 seconds for the web: `eir:bootstrap`, `ifrs9:segment-ecl` and `ifrs9:smoke-reports` call `set_time_limit(0)` after each step, so a long run is not stopped part-way.

### 9.4 Scheduler

`app/Console/Kernel.php` schedules two data feeds and the three legacy commands: `eir:poll-feed-folder --also-route-3` daily at 06:30 without overlapping (the E-Banker pack lands on the first working day after month-end), `macro:import-worldbank` on the second of each month at 07:00, and the recurring campaigns daily, scheduled campaigns every five minutes and loan application reminders every two minutes. These are the same two steps as the `refresh-data` job of 9.5. The month-end calculations are not scheduled: they need an approved loan book build, a model version in force and an approved scenario set, and are run by hand from Administration, Maintenance or with `maiic:task month-end`. The scheduler itself needs one cron entry on the server:

```
* * * * * cd /var/www/maiic-ifrs9 && php artisan schedule:run >> /dev/null 2>&1
```

When the monthly run is automated, add it here with `->monthlyOn(day, time)` after the extracts are confirmed loaded.

### 9.5 Maintenance jobs and the forward-looking month-end

Administration, Maintenance (`MaintenanceController`, route `maintenance.index`, gated by `permission:settings`; running a command needs the `admin` role) runs a fixed whitelist of artisan commands from the browser. The browser sends only a catalogue key and, for a month-end entry, a month the system holds a loan book for (a build may name a new month); the command line is built from a template on the server. `app/Services/Maintenance/CommandRunner.php` launches the command as a detached background process with `memory_limit=4096M` (the month-end steps, the loan book build and the baseline checks read the whole landing-zone ledger), writes its output to `storage/app/command-runs/{id}.log` with a `{id}.json` metadata file, and the page polls the log for live output. On Windows it runs `C:\xampp\php\php.exe` when that file exists, otherwise `PHP_BINARY`. One command runs at a time; a second launch while one is running is refused. Each launch is logged in `audit_logs` as `Maintenance Command Launched`. The destructive bootstrap entry (`eir:bootstrap --fresh --force-wipe --with-client-inputs --build --run-engines --verify --user={user}`) needs its confirmation phrase, `BOOTSTRAP`, typed.

The consolidated jobs are `php artisan maiic:task {task}` (`app/Console/Commands/MaintenanceTask.php`). Each runs its steps in order through `Artisan::call` with the output streamed, and stops at the first step that fails, naming it; the steps before it stay done. `MaintenanceTask::steps()` holds the order and is public so the order can be tested.

| Job | Steps, in order |
|---|---|
| `update` | `migrate --force`; `permission:cache-reset`; `db:seed` with `MaiicAdminPermissionsSeeder`, `TicketsPermissionsSeeder`, `GovernanceSettingsSeeder` and `SicrQualitativeFactorsSeeder` (additive) |
| `month-end {period} --user=` | 1 `clients:link-loan-book`; 2 `eir:load-schemes` (scheme settings and the over-sanction flag); 3 `ifrs9:segment-portfolios` (the Mega Farm schemes kept apart from MAIIC's book); 4 `eir:restructure-candidates` (detection only, nothing is confirmed); 5 `eir:stage {period}`; 6 `ifrs9:segment-parameters {period}`; 7 `fli:month-end {period} --all`; 8 `eir:load-ledger-cash`; 9 `eir:build-gl-fees` (the month's fee postings tied to their loans and the fee GL gate); 10 `eir:run-revenue {period}`; 11 `megafarm:ecl {period}`, which is optional: if it fails the job reports it as skipped (the month has no Mega Farm loans) and finishes. `--user` is passed to the steps that take it; without it `eir:run-revenue` refuses and the job stops at step 10, so the console always passes the signed-in user |
| `refresh-data` | `macro:import-worldbank`; `eir:poll-feed-folder` |
| `health-check` | `about`; `migrate:status`; `eir:baselines`; `ifrs9:smoke-reports`. A failing check is recorded and the other checks still run; the job fails at the end naming them |

The Maintenance page also offers `eir:build-loan-books {period}` (the build is approved by a second person on Imports & Feeds, Loan Book Builds, before the calculations run; a locked month is refused) and `fli:correlate {period}` for the annual recalibration, which writes candidate fits only.

`php artisan fli:month-end {period}` (`app/Console/Commands/FliMonthEndCommand.php`) follows the ten steps of `FliMonthEnd` (chapter 5.5). With no option it prints each step, its state (done, todo, blocked, warn, optional) and where it stands. `--project` runs `FliProjectionService::run()`; `--close` runs `FliCloseService::record()` and prints the booked against the weighted ECL, whether they tie, the ECL per scenario and, when the movement exceeds the tolerance, that a commentary is needed. The record carries the discounted allowance beside the engine measure, and a record whose discounted figures do not reconcile cannot be signed off. `--all` first checks the loan book, model version, scenario set and overlays steps and stops with the reason if any is blocked or waiting, because each then needs a person (an import or an approval); the economic data step is shown but does not stop it. It then applies the route unless it is already applied under the version and set in force, runs the projection, runs `ifrs9:segment-ecl {period} --pd=pd_post_fli --discounting=discounted`, and records the month. The command never approves anything: the fit or external model, the scenario set, the overlays and the sign-off stay with people, and the sign-off is made by a second person on Forward-Looking Information, FLI Month-End, Overview.

The Overview runs the same automated steps in the background through `CommandRunner`, each a fixed template in `FliModuleController`: `fli:correlate {period}`, `fli:apply {period} --user={user}`, `fli:month-end {period} --project --user={user}`, `ifrs9:segment-ecl {period} --pd=pd_post_fli` (discounted by default), `fli:month-end {period} --close --user={user}` and `fli:month-end {period} --all --user={user}`. Running a step needs `eir.govern`, and each launch is logged as `FLI Step Launched`. The Workspace close checklist item "Forward-looking month-end (route, record, sign-off)" is done when the Overview's last step, the month recorded and signed off, is done.

### 9.6 The bootstrap

`php artisan eir:bootstrap --fresh --with-client-inputs --build --run-engines --verify` (`app/Console/Commands/Bootstrap.php`) builds a clean install from the client files committed under `docs/bootstrap` and proves it against the golden numbers. Each option switches on one stage; every stage is idempotent, and anything the bootstrap approves is stamped with the automated bootstrap label, never as a MAIIC approval. It runs the queue as `sync` and lifts the time limit after every step.

| Step | What it does |
|---|---|
| 1 `--fresh` | `migrate:fresh`, refused when `loan_books` holds rows unless `--force-wipe` is also given |
| 2 seed (always) | `migrate`, then the seeders that exist of: `PermissionsTableSeeder`, `RolesTableSeeder`, `MaiicAdminPermissionsSeeder`, `TicketsPermissionsSeeder`, `UsersTableSeeder`, `GovernanceSettingsSeeder`, `StagingThresholdSeeder`, `SicrQualitativeFactorsSeeder`, `EbankerQuerySeeder`, `MacroSeriesSeeder`, `MaiicTransitionProfileSeeder`, `HelpContentSeeder`, `HelpAdminContentSeeder`, `EirAccountingRuleSeeder`, `GlAccountScopeSeeder`, `ScenarioSetSeeder`, `IndustryTypeSeeder`, `CreditLossDefinitionSeeder`, `TransitionProfileDefinitionSeeder`, `TransitionProfileOptionSeeder`; a "Loans" portfolio when none exists |
| 3 and 4 `--with-client-inputs` | Land the pack the top-level `manifest.json` lists (packs 1 and 2, `ebanker-pack-2026-10-07` and `-10-08`) through `PackLandingService` on route 1. `landLaterPacks()` then lands every later pack in the order of its folder name: each `docs/bootstrap/ebanker-pack-*/` folder with its own `manifest.json` and each `docs/bootstrap/packs/ebanker-pack-*.zip`, unzipped into `storage/app/bootstrap-packs/` through the same entry-name check as an uploaded pack. Today that is packs 3 to 9: `-10-09` (pack 3), `packs/ebanker-pack-2026-10-12.zip` (pack 4), `-10-12-p5`, `-10-12-p6`, `-10-13-p7`, `-10-13-p8` and `-10-14` (pack 9). A pack already landed (the same hash) is not loaded twice; a quarantined pack stops the bootstrap. Then the take-on workbook (`takeon/`, the copy as received and the mapping with the fees) through `TakeonLandingService`, and the monthly trial balances with the December 2025 AFS bridge through `TrialBalanceLandingService::landDirectory()` (a quarantined trial balance stops it) |
| 5 `--build` | From `--from` (default 2024-07) to `--to` (default the last month-end in the pack): the loan books (`LoanBookBuildService`, approved under the bootstrap label); the take-on population (proposed, then approved under the bootstrap label, with the take-on EIR check and the take-on fees landed PENDING); the contract master with the over-sanction flag; the scheme settings; the PLR series; the fees, the GL fee postings (`GlFeeBuildService`) and the fee rulebook sweep, which only suggests; the interest posted, cash movements and drawdowns from the ledger; `eir:derive-spreads`; the version 1 schedules, generated and approved under the bootstrap label; and `eir:restructure-candidates`, detection only |
| 6 `--run-engines` | The World Bank series from the committed snapshot (`macro:import-worldbank --offline`), or fetched live with `--live-macro`, in which case the results depend on the day it runs; staging of every period; the EIR solved and locked under the bootstrap label; `ifrs9:segment-portfolios`; for every period the PD and LGD by segment, the forward-looking route, `ifrs9:segment-ecl --pd=pd_post_fli --discounting=discounted --prune-stale` and the Mega Farm programme; `eir:run-revenue` for every period; then for the last period the first scenario set (seeded and approved under the bootstrap label), `fli:correlate --top=0`, the route, the ECL again under the set, the scenario sensitivity, the stress runs, `ifrs9:smoke-reports` in its own process, the compliance register and the GL reconciliation |
| 7 `--verify` | `BaselineService::checks()`, printed as a table; the command exits non-zero on any FAIL |

The verify checks are, in order: the contractual interest posted January 2025 to July 2026 from the ledger; the method B carrying amount against the stored report on the in-scope loan GLs; the Mega Farm ledger difference (reported, not asserted); the year-end interest batch contra on GLs 4215 and 4216; the loan book by GL at 31 December 2025 against the trial balance; the pack inventory; and the golden numbers. The pack inventory (`BaselineService::packInventory()`) gives one row per manifest: each committed file's SHA-256 recomputed (inside the zip for pack 4) and compared with the manifest, each extract landed in a LANDED load with exactly its manifest row count and that SHA-256, no CSV in a pack folder missing from its manifest, the trial balances and take-on workbooks landed, and the files deliberately not landed counted from each manifest's `not_landed` list. `docs/bootstrap/.gitattributes` keeps the committed bytes, so the hashes hold on any platform.

The golden numbers are recomputed by `BaselineService::goldenActuals()` and compared with the `values` of `docs/bootstrap/golden.json`, to the cent for amounts and exactly for counts: the take-on worksheet tie (`CV_10` against the take-on postings of 31 July 2024, by transaction type 301, 303 and 305) and the pre-migration ledger copy (`CV_04a`) against the GL opening balances of 1 January 2024 (`CV_14`); the loan book of MAIIC and of the Mega Farm programme at the golden periods 2025-12 and 2026-09, and the ledger write-offs (`WriteOffClassifier`); and the engine outputs (ECL undiscounted, discounted and by stage, the Mega Farm programme and MAIIC's share, the EIR contracts solved, locked and blocked, revenue by year, and the RBM loss list). A key with no committed value fails closed ("no golden value committed"), so a golden is never taken from the run it tests. The committed `golden.json` (set 14 October 2026) holds the five data-tie values only; the book and engine output values are to be added from the first full bootstrap run, and until then those rows fail.
