## 9. Initial data loading

Load in this order so each step can be reconciled before the next depends on it. Contract Schedule 3 requires record counts to reconcile one hundred percent (every source record imported or listed on an agreed exceptions report) and aggregate values within 0.1 percent.

### 9.1 Reference data

Portfolios (MAIIC core, FInES, Mega Farm and the derived agricultural portfolios), sector types, product groups and financial periods must exist before loan book rows can be attributed. `php artisan ifrs9:segment-portfolios --dry-run` shows how existing loan book rows would be assigned to portfolios from product groups.

### 9.2 Loan book snapshots

From October 2026 the loan book of each month is built from the E-Banker pack (section 9.8), not imported. A file import remains for a month outside the packs or a restored database: Customer & Loan Data, Loan Book, Import (custom mapping, matched columns or the E-Banker Loan Book Report). Every import screen offers a sample file in the exact format and a column guide, and Check file runs the server-side check alone; any error refuses the whole file with a row-by-row list. After each period:

1. Compare the Imports page counts (records and failed records) with the source row count.
2. Compare the Loan Book page totals for the period with the core banking control total for gross loans.
3. Download the failed rows file, if any, and agree the exceptions with MAIIC.

### 9.3 Collateral

Import the collateral register for the same periods (Collateral Management, Register, Import), then allocate collateral to exposures. Reconcile allocated value against the register total.

### 9.4 Historical ECL

If MAIIC's existing database is restored (chapter 10.3), historical ECL results arrive with it. Otherwise run the ECL calculation for each period in order after PD, LGD and FLI setup, and compare the resulting stage totals with the reference IFRS 9 calculation agreed for UAT.

### 9.5 EIR extracts (the route used before October 2026)

Until October 2026 the EIR data came from three extract scripts. Section 9.8 replaced them; this section is kept for a database restored from that period. Under EIR & Revenue Recognition:

1. EIR Rules, Accounting Rules: review and approve the seeded fee rules that MAIIC's accounting owner agrees with.
2. Schedule Intake: load Extract A (contract master), then original schedules or generate them (`php artisan eir:generate-schedules --dry-run` first), then Extract B (transactions and remaining schedules), then Extract C (GL interest).
3. EIR Rules, Fee Classification: classify and independently review the fee lines.
4. EIR Data, Schedule Review: approve the version 1 schedules.
5. EIR Calculations, Blockers: every contract should read READY or carry a named blocker to resolve.
6. EIR Calculations, Calculations: calculate, then approve and lock with a second user.

Trial balances are uploaded one month at a time on EIR & Revenue Recognition, EIR Data, Trial Balances (an `.xls` or `.xlsx` file, up to 50 MB; uploading needs `eir.govern`). The gates check that the month is known, that debits equal credits and that the lines add up to the report's grand total, and record the file's hash; the screen lists the months held by basis and every load with its gates. A folder of monthly files is loaded from the command line:

```bash
php artisan eir:import-trial-balances "/data/maiic/trial-balances" \
  --afs="/data/maiic/AFS Final TB and Initial TB Mapped to E-Banker TB for MAIIC for December 2025.xlsx" \
  --sheet="Final E-Banker TB Dec 2025" --period=2025-12-01 --user=<id>
```

The command takes the `Trial Balance_*.xls` files of the folder, lands each through the same gates into the landing zone (query `TB_01`), derives `gl_trial_balance_lines` (the GL side of the reconciliation) for the months it holds, prints one line per file with the GL line count and the total, quarantines any file whose grand total does not tie, and warns if `--afs` is omitted because December income would then read as zero (the standalone December file is post-closing).

### 9.6 Known data hazards

- Extract A typed Excel dates have day and month swapped; text dates are correct. Verify first repayment and value dates before approving generated schedules.
- The Extract C delivered on 26 August 2026 stacks three runs (7, 25 and 41). Load run 41 only, otherwise 2025 is triple-counted.
- Trial balance profit and loss balances are cumulative year to date and reset each January; the application derives monthly movements itself and must not be fed pre-differenced figures.
- Extract A carries every facility twice; the importer merges the pairs and rejects any whose stated terms disagree, naming the field.
- Fees now come from three sources: the LOS charges in the E-Banker pack, the fee postings on the ledger (`GL_04`, tied to a loan by its batch; lines that cannot be tied are held with the reason) and the fee columns of the take-on workbook MAIIC returned on 13 October 2026. The governed fee source precedence makes a fee found in two sources count once. Every fee lands as a PENDING line until a person reviews it on Fee Classification.
- Dates in packs 1 to 8 are `m/d/yyyy`, as the export tool wrote them; each manifest declares its format and the gates read it (an ISO-date check only warns).

### 9.7 Reconciliation evidence

Keep the Imports page exception files, the E-Banker Loads list with each load's gates, the Blockers export, the EIR Data GL tab, the Trial Balances load list and the bootstrap's verify table (or `php artisan eir:baselines --json`) as the migration reconciliation report required by Schedule 1 deliverable 2.

### 9.8 Loading from E-Banker: the landing zone and the bootstrap (from 8 October 2026)

From October 2026 the extract scripts of 9.5 are no longer the source. The system is loaded from the E-Banker tables themselves, pulled by the versioned queries under `docs/bootstrap/queries/` and committed as files under `docs/bootstrap/`. Each pack has a `manifest.json` that names every file with its query id, row count and SHA-256, the accepted exceptions, and the files received but deliberately not landed (`not_landed`, each with its reason). `docs/bootstrap/README.md` lists every folder; specification v4 section 6 describes the design; this section is the procedure.

**What is committed.** Packs 1 and 2 (7 and 8 October 2026) are listed by the top-level `docs/bootstrap/manifest.json`. Packs 3 to 9 have their own manifests: the folders `ebanker-pack-2026-10-09`, `-12-p5`, `-12-p6`, `-13-p7`, `-13-p8` and `-14`, and pack 4 as `packs/ebanker-pack-2026-10-12.zip` (143 MB of CSV files, 12.4 MB zipped). Packs 5 to 9 carry query ids outside the families the engines read, so they are reference data: landing them moves no engine figure. Beside the packs are the take-on workbooks (`takeon/`: the workbook as received on 11 September 2026 and the mapping workbook with the fees, returned by MAIIC on 13 October 2026), the 20 monthly trial balances (January 2025 to August 2026) with the December 2025 AFS bridge, the World Bank macro snapshot (`macro/worldbank-MWI.json`) and the golden numbers (`golden.json`).

**Byte-exact files.** `docs/bootstrap/.gitattributes` holds three lines, `*.csv -text`, `*.json -text` and `*.sql -text`. The repository's root `.gitattributes` says `* text=auto`, under which Git would store these files with LF and give CRLF back only on a checkout with `core.autocrlf=true`. The manifests hash the files as MAIIC sent them (most with CRLF line endings), so on a Linux server, or any clone without autocrlf, every pack would have been quarantined at its SHA-256 gate. With `-text` Git converts nothing in either direction, so every clone gets the committed bytes and the hashes hold on any platform. The workbooks and the zip are binary and are never converted. The repository does not use Git LFS; the files are ordinary Git objects. Do not open and re-save any of these files: a replacement is a new file with a new manifest entry, and the file it replaces moves to a `superseded/` folder beside it.

**One command on a clean database.**

```bash
php -d memory_limit=4096M artisan eir:bootstrap --fresh --with-client-inputs --build --run-engines --verify
```

The same run is the "Bootstrap the system (first install)" job on Administration & support, Support & Maintenance, Maintenance, Install and update tab. The screen adds `--force-wipe`, asks for the word BOOTSTRAP to be typed, and only the `admin` role can start it.

| Option | Effect |
|---|---|
| `--fresh` | Run `migrate:fresh` first; refused if `loan_books` holds rows, unless `--force-wipe` is also passed |
| `--force-wipe` | Allow `--fresh` on a database that holds data |
| `--with-client-inputs` | Land the committed packs, the take-on workbooks and the trial balances (steps 3 and 4) |
| `--build` | Build the loan books, the take-on population, the contract inputs and the version 1 schedules (step 5) |
| `--run-engines` | Run the engine chain end to end, synchronously (step 6) |
| `--verify` | Check the baselines and the golden numbers; exit non-zero on any FAIL (step 7) |
| `--from=2024-07`, `--to=` | The first and last period built and run; `--to` defaults to the last month-end in the packs (September 2026) |
| `--user=1` | The user the loads are recorded against |
| `--inputs=docs/bootstrap` | The folder of committed inputs |
| `--live-macro` | Fetch the World Bank series live; without it they come from the committed snapshot, so every install reproduces the same results |

The bootstrap runs the queue synchronously, so it needs no worker. In order:

| Step | What it does | Command on its own |
|---|---|---|
| 1 | `migrate:fresh` (only with `--fresh`) | `php artisan migrate:fresh --force` |
| 2 | `migrate`, then these seeders, each skipped with a warning if it fails: permissions, roles, admin permissions, support ticket permissions, the administrator user, the Governance Centre defaults, the staging thresholds, the qualitative SICR factors, the E-Banker query register, the macro series, the MAIIC transition profiles, the User and Administrator Manual content, the fee rulebook (unapproved), the GL account scope, the scenario sets, the sector types, the credit loss definitions and the transition profile definitions and options. A "Loans" portfolio is added if there is none | `php artisan db:seed --class=<Seeder> --force` |
| 3 | Lands the packs through their gates (file present, SHA-256, row count, query in the register, dates in the manifest's format, key present and unique): packs 1 and 2 by the top-level manifest, then every later pack folder or zip in the order of its name (`landLaterPacks`; the pack 4 zip is first unzipped into `storage/app/bootstrap-packs`). A pack already landed (the same hash) is recognised and not loaded twice. A quarantined pack stops the bootstrap | `php artisan eir:land-pack <folder> --user=1` |
| 3b | Lands the 20 monthly trial balances through the trial balance gates with the AFS bridge of 10 September 2026, and derives the GL lines; a quarantined file stops the bootstrap | `php artisan eir:import-trial-balances docs/bootstrap/trial-balances/monthly --afs=<bridge> --user=1` |
| 4 | Lands the take-on workbook as received and the mapping workbook with the fee columns, each value with its sheet and cell | `php artisan eir:land-takeon --user=1` |
| 5 | Builds: the loan books for every month from `--from` to `--to` by the method in force (B, derived from the ledger, by default), approved under the bootstrap label; the take-on population (proposed, then approved under the label), with the take-on EIR check and the take-on fees landed PENDING; the contract master and the scheme settings (`SM_01`, with `DD_10` where it lacks a field); the PLR series; the fees from the LOS charges and the GL fee postings (`GL_04`) with the fee source precedence and the fee GL gate; the fee rulebook sweep, which only suggests; the interest posted, the cash movements and the drawdowns from the ledger; the spreads; the version 1 schedules, approved under the label; and the restructure candidates and rate moves, detected only | `php artisan eir:build-loan-books 2024-07 2026-09 --bootstrap`; `php artisan eir:land-takeon --build`; `php artisan eir:load-schemes`; `php artisan eir:build-gl-fees --user=1`; `php artisan eir:derive-spreads --user=1`; `php artisan eir:restructure-candidates`; the rest inside the bootstrap |
| 6 | Engines, synchronously and in dependency order: the macro series (`macro:import-worldbank --offline`, or live with `--live-macro`); staging for every period; the EIR solved and locked under the label; the Mega Farm schemes split into their own portfolio; for every period the PD and LGD by segment, the forward-looking route, the discounted ECL on the post-FLI PD and the Mega Farm programme ECL; the revenue roll-forward for every period; for the last period the first scenario set (approved under the label), the correlation sweep (candidate fits only, none approved), the route and the ECL again under that set, the scenario sensitivity and the stress runs; every IFRS 9 report rendered (in its own process); the compliance register; the GL reconciliation. A step that cannot run is reported as NOT RUN with the reason and the chain goes on | `php artisan eir:stage 2024-07 2026-09 --user=1`; `php artisan ifrs9:segment-portfolios`; `php artisan ifrs9:segment-parameters <YYYY-MM> --user=1`; `php artisan fli:apply <YYYY-MM> --user=1`; `php artisan ifrs9:segment-ecl <YYYY-MM> --prune-stale`; `php artisan megafarm:ecl <YYYY-MM> --user=1`; `php artisan eir:run-revenue <YYYY-MM> --user=1`; `php artisan fli:correlate <YYYY-MM>`; `php artisan ifrs9:smoke-reports <YYYY-MM>` |
| 7 | Verifies (`BaselineService::checks()`) and exits non-zero on any FAIL; see below | `php artisan eir:baselines` |

**What verify checks.** The acceptance ties: the interest posted January 2025 to July 2026 from the ledger (types 303 and 120), 5,293,988,207.06; the derived carrying amount against the stored Loan Book Report on every account-month of the in-scope loan GLs (no difference over 2 cents); the Mega Farm ledger against the report (reported, not a test); the year-end batch contras on 4215 (32,956,675.55) and 4216 (-21,733,262.70); the loan GLs at 31 December 2025 against the trial balance, with 1050201 and 1050202 off by the accepted exceptions of specification section 3.5. Then one inventory row per manifest: each committed file's SHA-256 recomputed (inside the zip for pack 4), each extract landed in a LANDED load with exactly its manifest rows, no CSV in a pack folder missing from its manifest, and the trial balances and take-on workbooks landed. Then the golden numbers of `docs/bootstrap/golden.json`, each recomputed from the database by `BaselineService::goldenActuals()` and compared to the cent for amounts and exactly for counts: the take-on worksheet tie (`CV_10`), the pre-migration ledger against the 1 January 2024 GL openings (`CV_04a` and `CV_14`), the loan book of December 2025 and September 2026 (MAIIC's book and the Mega Farm programme apart), the ledger write-offs, the ECL undiscounted, discounted and by stage, the Mega Farm ECL, the EIR contract counts, the revenue by year and the RBM loss list. A figure with no committed value fails, so a golden is never taken from the run it tests.

**Status at 14 October 2026.** `golden.json` as committed holds five values only (phase 1: the take-on worksheet tie and the pre-migration ledger), set on 14 October 2026; the engine outputs are added from the first full bootstrap run. Until then every other golden line reads "no golden value committed" and counts as a FAIL, so `--verify` exits non-zero even on a correct install. Every acceptance tie, every inventory row and the five committed goldens must PASS.

**Time and memory.** The run ends with "Bootstrap finished in N s". No timing for the full run with packs 3 to 9 is recorded yet; the loan book build reads the ledger once per build and takes about 4 seconds a month. The bootstrap lifts PHP's time limit after every step (the web controllers it calls lower it to 300 seconds), so it is never stopped part-way by a time limit, but it needs 4 GB of memory: run it with `php -d memory_limit=4096M`, as above. Anything the bootstrap approves (a load, a build, a schedule, an EIR lock, the first scenario set) carries the label "System Bootstrap (automated data-readiness, not a MAIIC approval)". It never approves the fee rulebook, a fee classification, a forward-looking fit, a restructure or a rate move: MAIIC reviewers do, and until then a contract whose fees are unclassified stays blocked at the fee gate, which EIR Calculations, Blockers shows.

**Seed what the bootstrap does not.** With `--fresh` every table is dropped, including what `db:seed` wrote in chapter 3.6, and step 2 does not reseed the countries, currencies, timezones, settings, branches, legal types, chart of accounts or the ticket history. After a `--fresh` bootstrap run each of these once (they insert without a check, so never twice):

```bash
php artisan db:seed --class=CountriesTableSeeder --force
php artisan db:seed --class=CurrenciesTableSeeder --force
php artisan db:seed --class=TimezonesTableSeeder --force
php artisan db:seed --class=SettingsTableSeeder --force
php artisan db:seed --class=BranchesTableSeeder --force
php artisan db:seed --class=LegalTypesTableSeeder --force
php artisan db:seed --class=ChartOfAccountsTableSeeder --force
php artisan db:seed --class=MaiicCurrencySeeder --force
php artisan db:seed --class=TicketSeeder --force
```

Do not run plain `db:seed` after the bootstrap: it stops at `UsersTableSeeder` on the existing administrator.

**Every month, by the feed.** MAIIC ICT runs the versioned queries (Customer & Loan Data, Imports & Feeds, E-Banker Loads, "Download the queries"; the Query Register tab lists them) and delivers the CSV files with `manifest.json`: uploaded as a zip with "Load a pack" on the same screen (route 1; "Check only" runs the gates without landing), written to the feed folder (route 2) or posted to the API (route 3). The gates run and the pack is landed or quarantined with the file and row named. Then a build is proposed for the month ("Propose a build", Loan Book Builds) and a second person approves it; the differences from the previous build are shown first, and a locked period is never restated. The month-end follows in 9.9.

**The EIR at any date.** `php artisan eir:as-at <YYYY-MM-DD> [--contract=<id>] [--json]`, or EIR Calculations, EIR as at a Date: a month-end reads the locked roll-forward row; a date inside a month accrues the EIR on the actual days from the prior month-end less the cash received; a date after the last loaded posting is refused with that date named.

**Interim scripts.** The Python scripts under `docs/build-files/` (`demo_load_loan_books.py`, `demo_contract_master.py`, `demo_schemes.py`, `demo_stage.py`) were the interim load of the 8 October demo and are kept as a record; the bootstrap replaces them.

### 9.9 The month-end run, in order (from 12 October 2026)

Section 9.8 describes the one-off bootstrap. Every month-end after that follows the steps below, in this order, for the month being closed (September 2026 in the examples). Each step can be run from its screen or by the command shown; a step that lacks its input refuses with the reason named rather than writing a partial result. Anything that changes a governed figure (a loan book build, a fit, a scenario set, a qualitative trigger, an overlay, a write-off, a restructure) is proposed by one person and approved by another. Run the console commands with `php -d memory_limit=4096M artisan ...` when they read the whole ledger.

| # | Step | Screen | Command |
|---|---|---|---|
| 1 | Land the E-Banker pack (row counts and SHA-256 in the manifest) | Customer & Loan Data, Imports & Feeds, E-Banker Loads | `php artisan eir:land-pack <folder> --user=<id>` (add `--dry-run` to run the gates only; route 2: the scheduler polls the feed folder at 06:30 each day) |
| 2 | Build the month's loan book; a second person approves | Imports & Feeds, Loan Book Builds | `php artisan eir:build-loan-books 2026-09 --user=<id>`, then `php artisan eir:build-loan-books --approve=<build id> --user=<approver id>` |
| 3 | Trial balance and any change to the prime lending rate | EIR & Revenue Recognition, EIR Data, Trial Balances; EIR Data, Reference Rates | `php artisan eir:import-trial-balances <folder> --user=<id>`; `php artisan eir:import-reference-rates <file> --user=<id>` |
| 4 | Raise or close qualitative SICR triggers for the month (watch-list, restructuring, drought, covenant breach...) | IFRS 9 Model Setup, Staging & SICR Rules, SICR Trigger Alerts | (screen only, maker-checker) |
| 5 | Stage the book: days past due against the governed thresholds, the missed-instalment trigger on the instalment chart in force, then the approved qualitative triggers | Staging & SICR Rules | `php artisan eir:stage 2026-09 --user=<id>` |
| 6 | PD and LGD by segment on the governed basis (Governance Centre, PD & LGD segments); the Mega Farm programme is kept out | PD Model; LGD Model | `php artisan ifrs9:segment-parameters 2026-09 --user=<id>` |
| 7 | Scenario set for the period (quarterly, or when the outlook changes): start it from a profile, set its weights and shocks, propose, approve | Forward-Looking Information: Scenarios & Overlays, Scenario Profiles (start a set); Macro Statistics, Scenario Assumptions (weights and shocks); Scenarios & Overlays, Scenario Sets (propose, approve) | (screens, maker-checker) |
| 8 | Forward-looking model: the model version in force is applied to every loan. Recalibrate it only once a year or after an event (correlation sweep, then propose and approve a fit, which publishes a new version) | Forward-Looking Information, FLI Month-End, Overview; Forward-Looking Model, Model Versions and Regression | `php artisan fli:apply 2026-09 --user=<id>`; recalibration: `php artisan fli:correlate 2026-09` |
| 9 | Expected credit loss for every portfolio on the post-FLI PD, discounted at the EIR (the booked allowance), then the forward-looking record of the month (the ECL under each scenario, checked against the booked ECL); a second person signs it off | ECL Processing, ECL Calculation; Forward-Looking Information, FLI Month-End, Overview | `php artisan ifrs9:segment-ecl 2026-09 --pd=pd_post_fli --discounting=discounted`; `php artisan fli:month-end 2026-09 --close --user=<id>` |
| 10 | The month's cash from the ledger and the GL fee postings tied to their loans (with the fee GL gate), then the EIR revenue roll-forward | EIR & Revenue Recognition, EIR Rules, Fee Classification (the held GL fee lines); EIR Calculations, Calculations | `php artisan eir:load-ledger-cash --user=<id>`; `php artisan eir:build-gl-fees --user=<id>`; `php artisan eir:run-revenue 2026-09 --user=<id>` |
| 11 | Mega Farm programme | ECL Processing, Mega Farm Programme | `php artisan megafarm:ecl 2026-09 --user=<id>` |
| 12 | Registers: classify the restructure candidates and rate moves the run found, and propose any write-off; a second person approves each | EIR Data, Restructures & Resets; EIR Data, Write-offs | (screens, maker-checker) |
| 13 | Review: the Workspace close checklist and its "Awaiting approval" queue, the dashboard (ECL build-up), the Reports hub (IFRS 9 note, RBM return, reconciliations) | Workspace; Dashboard; Reports | (screens) |
| 14 | Management sign-off and the period lock, with the reason recorded: no later build, write-off or writer restates a locked month | Customer & Loan Data, Imports & Feeds, Periods Built (the lock button; needs `eir.govern`) | `php artisan eir:lock-period 2026-09 --user=<id> --reason="September 2026 close"` (`--unlock` with a reason reopens it) |

**One job for the calculations.** After the build is approved, a model version is in force and the scenario set is approved, Administration & support, Support & Maintenance, Maintenance, Month-end tab, "Run the month-end calculations" runs `php artisan maiic:task month-end 2026-09 --user=<id>`. Its eleven steps, in order:

1. Clients for new loan-book customers (`clients:link-loan-book`).
2. Scheme settings and the over-sanction flag (`eir:load-schemes`).
3. Programme portfolios, the Mega Farm schemes kept apart from the MAIIC book (`ifrs9:segment-portfolios`).
4. Restructure candidates and rate moves, detection only; nothing is confirmed (`eir:restructure-candidates`).
5. Stage the book (`eir:stage 2026-09`).
6. PD and LGD by segment (`ifrs9:segment-parameters 2026-09`).
7. Forward-looking route, projection, ECL and the month record (`fli:month-end 2026-09 --all`: it checks that the book, the model version, the scenario set and the overlays are ready and stops if a person still has to act; then applies the route if it is not applied, projects the scenarios, runs `ifrs9:segment-ecl 2026-09 --pd=pd_post_fli --discounting=discounted` and records the month).
8. Actual cash from the E-Banker ledger (`eir:load-ledger-cash`).
9. GL fee postings tied to their loans, and the fee GL gate (`eir:build-gl-fees`).
10. EIR revenue (`eir:run-revenue 2026-09`).
11. Mega Farm programme (`megafarm:ecl 2026-09`); optional: when the month has no Mega Farm loans it is skipped, not failed.

`--user` is passed to every step that takes it. The job prints each step and stops at the first that fails, naming it; the steps before it are done, so correct what it names and run the job again. It never approves anything: a second person then signs the month off on Forward-Looking Information, FLI Month-End, Overview. `php artisan fli:month-end 2026-09` prints where each forward-looking step stands.

**Order matters.** A new PD run (step 6) clears the post-FLI PD of the month, because that PD was built on the old one; steps 8 and 9 must follow it. Re-staging a month (step 5) likewise requires steps 6, 8 and 9 again. The ECL run (step 9) refuses when any loan with exposure has no stage, PD or LGD, and names the loans.

**The scheduler.** Run `php artisan schedule:run` every minute (cron, or Windows Task Scheduler on a workstation; chapter 7.2). It polls the E-Banker feed folder each morning and imports the World Bank macro series on the second day of each month. It does not run the month-end.

**Before a month is presented.** The forward-looking result rests on a short history (the PLR against the Stage 3 share, about two years of monthly data); the segment PDs on small portfolios take the pooled book's PD under the governed thin-segment rule, recorded per cell; sectors coded 4290 by E-Banker are held apart as an unverified default code; write-offs follow specification v4 section 3.8 (status H, the type 300 batches debiting GL 1064 and the 29 June 2026 transfer to GL 1064).
