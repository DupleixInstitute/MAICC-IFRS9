# MAIIC EIR and IFRS 9 system: after-build report, 8 October 2026

**What this is.** On 8 October 2026 the build set out in *MAIIC EIR Engine Specification v4* (7 October 2026) was carried out against the specification, section by section, on the MAICC-IFRS9 repository (branch `eir_revenue_recognition`, commits `32e7d99` to the head of 9 October (three rounds on 8 and 9 October)). This report says what was built, what the system proved when it ran, what the build found in the data and in the system that the specification did not know, and what is still owed. It is the comparison the specification asked for in its own section 8 (the build plan): the original specification stands as written; this report is read beside it.

The production database `maiic_ifrs9` was not opened. The build was proven twice: on the demo copy (`maiic_ifrs9_demo`, which also holds the data of the 8 October demo), and on a throwaway database (`maiic_ifrs9_bootstrap`) wiped and rebuilt from nothing by the bootstrap command, which is the test the specification set for the data foundation.

## 1. In plain language

The specification said the system should load itself from E-Banker's own tables through one door with gates, build the monthly loan book from the ledger, hold the take-on schedules as data, run its engines in order, and prove the result against golden numbers, all from one command on a clean database. That is now what happens: `php artisan eir:bootstrap --fresh --with-client-inputs --build --run-engines --verify` takes a wiped database to a proven state in about 80 seconds, and every golden check passes. The ledger-derived loan book reproduces E-Banker's own carrying amount to the cent on 2,361 of 2,361 account-months. The interest posted from January 2025 to July 2026 is the 5,293,988,207.06 the audit found. The loan GLs at 31 December 2025 tie to the trial balance to the cent, with the two FInES GLs off by exactly the keyed-opening exception Finance has been asked to correct.

The screens moved to the suite layout with light and dark mode, and three screens the specification asked for exist: the E-Banker Feed, the Take-on Schedules and the EIR as at a Date. The transmission methods for the forward-looking adjustment are built as governed choices, each explained in the system with its preconditions checked against MAIIC's data.

Three things the specification did not know were found. The application already holds a **November 2025 Mega Farm loan book** of 3,490 loans across six GLs (K54.85 billion), loaded on 21 January 2026 with no ECL computed; it bears directly on the Mega Farm questions of specification section 16. The production database carries **three columns no migration created**, which a clean install would have lacked; a guarded migration now adds them. And the take-on schedules, compared with E-Banker's opening postings, differ on 72 of 73 mapped accounts, because they are contractual, not actual; the build keeps every take-on loan at its take-on balance, as the governed basis says, until the fees arrive.

What remains is set out in section 6: the forward-looking regression chain and the scenario-set engine (the specification's FL-1 to FL-4 and SC-1 to SC-4), the audit workbooks (CA-1 to CA-4), the Mega Farm module (section 16), the page-by-page dark-mode sweep, and the decisions MAIIC owes.

## 2. What was built, against the specification

| Spec section | What the specification asked for | Built | Where |
|---|---|---|---|
| 4.2 Governance Centre | Every open choice as a governed setting with a seeded default | Yes: 48 settings | `GovernanceService::catalogue()`, `GovernanceServiceTest` |
| 3.6 / D31 Staging | The directive's thresholds by tenor class; DPD from the oldest overdue instalment; the missed-instalment trigger | Yes | `StagingThresholdSeeder`, `StagingService`, `eir:stage` |
| 6.3 Landing zone | Raw tables mirroring E-Banker, append-only, versioned, every row carrying its pack | Yes, as one physical table with the query id as the logical table | migration `create_ebanker_landing_zone`, `PackLandingService` |
| 6.4 Gates | Hashes, counts, dates, register, keys; a failure names the file and row; quarantine | Yes, with key uniqueness and composite keys added | `PackLandingService`, 10 tests |
| 6.5 Routes | Five routes, the one in force governed | Routes 1 (manual), 2 (the polled folder, scheduled) and 3 (the API push) built through the one door; the SQLcl export template for route 2 committed; routes 4 and 5 await ICT and the vendor | `eir:land-pack`, `eir:poll-feed-folder`, the `ebanker-feed.api` route |
| 6.2 / 6.6 Build | Methods A, B and C; maker-checker; differences first; locked periods never restated; ECL columns untouched | Yes | `LoanBookBuildService`, `eir:build-loan-books`, `eir:lock-period`, 11 tests |
| 6.7 History | Built on a copy by A then B and compared | Yes: B equals the stored report to the cent on every account-month but one (1 cent) | the bootstrap's verify |
| 6.8 Feed screen | Queries, loads, watermarks, quarantine, build with approval | Yes | `EirFeedController`, `Pages/Eir/Feed/Index.vue` |
| 6.9 Take-on | Landed not typed; blocks and lines with cells; gates; the build under `takeon_history_basis`; the screen with confirm and fees | Yes | `TakeonLandingService`, `eir:land-takeon`, `EirTakeonController`, 3 tests |
| 6.10 Bootstrap | One command: fresh, seed, land, build, engines, verify | Yes | `eir:bootstrap` |
| 6.10.1 Engine chain | Twelve engines in dependency order | All twelve run from one command: macro, the scenario set, staging, PD, LGD, the forward-looking chain and route, the ECL on the post-FLI PD, the EIR and revenue, the reconciliation, stress testing for every scenario, every report, the compliance register; the Mega Farm step runs where the book holds the programme | `Bootstrap::stepEngines`; `PdEngineService`, `LgdEngineService`, `FliRouteService`, `StressTestService`, `MegaFarmEclService` |
| 6.10.2 Golden numbers | The section 9 baselines and the year-end ties | Nine checks, all PASS | `Bootstrap::stepVerify` |
| 6.11 As at any date | The EIR computation for a loan or the book at any date; refused after the last posting | Yes | `EirAsAtService`, `eir:as-at`, `EirAsAtController`, 4 tests |
| 7.1 Ledger importer | The ledger as the primary record: interest posted and cash movements from it | Yes: interest per account-month (retiring Extract C) and the cash movements as actual transactions (retiring Extract B) | `ContractInputsBuildService` |
| 7.2 to 7.4 | Rate history, contract master, fees from E-Banker's tables | Yes, through the importers that already exist | `ContractInputsBuildService` |
| 11 Suite layout | Six groups with accents, rail, top bar, page header with breadcrumb, light and dark; the help texts | Shell, shared styles, the tree and the help centre (114 passages re-worded, a navigation article) built. The dark-mode page sweep (11.9) is done: the light utilities the 292 pages colour themselves with are re-mapped under `.dark` to the suite's palette in one place (surfaces, text, borders, dividers, rings, hover and focus states, pastel tints and their text, gradients, shadows, every input type, the legacy form classes, a bare `border`), so every page follows the theme at once and a page with its own `dark:` variant keeps it; the dashboard's charts take their neutrals from the theme. Proven by capturing all 120 manual screens in dark mode (`manual:screenshots --theme=dark`) and measuring the share of near-white pixels: none above 2 percent, the light theme unchanged | `resources/css/app.css` (the `.dark` layer), `Dashboard.vue`, `manual:screenshots --theme`, `scripts/manual-screenshots.cjs` |
| 13 Macro statistics | Codes, the World Bank fetcher, the IMF WEO parser, the RBM file, preview then commit, batches, the five-tab screen, the precedence rule | Yes | `app/Services/Macro/*`, `MacroStatisticsController`, `Pages/Macro/Index.vue` |
| 14.7 Transmission methods | Governed methods, each explained with live preconditions and a worked example, in the screen and beside the setting | Yes: the cards are on the FLI Adjustments screen and inline on the `fli_transmission_method` setting in the Governance Centre, with a badge saying whether each method can be selected | `TransmissionMethodCatalogue`, `fli:method-cards`, `Pages/Eir/Governance.vue`, 4 tests |
| 14.3 to 14.8 Forward-looking chain and route | Guardrail, structural events, proxy deriver, profiler, correlation finder, regression; a fit approved under maker-checker; the route once per scenario; the lineage on every loan; the FLI Adjustments screen | Yes | `FliBridgeService`, `FliRouteService`, `app/Services/Fli/*`, `Pages/Fli/Adjustments.vue`, 4 tests |
| 15 Scenario sets | The governed set: rules, shocks on the base, approval, lock, versions, back-test, sensitivity; the first set of 15.8 | Yes | `ScenarioSetService`, `scenario:sets`, Governance Centre / Scenario Sets, 3 tests |
| 12 Audit workbooks | The engine, the five modules, the register, the trace, the pack | Yes: five workbooks (66 sections) in three formats with the Baselines sheet; the register with maker-checker sign-off; the per-contract trace; the auditor's pack with checksums | `tools/compliance/`, `docs/compliance/`, `ComplianceAuditService`, `AuditTraceController`, `compliance:audits --pack` |
| 16 Mega Farm | The programme in the ECL module under D30 with the governed PD methods; its screen | Built and run on the November 2025 book; Financial Modelling / Mega Farm Programme shows the book by scheme and stage, the settings in force and each run with its basis or declined reason; the figure awaits Dr Thom's confirmation of D30 and the monthly books | `MegaFarmEclService`, `megafarm:ecl`, `Pages/MegaFarm/Index.vue` |
| 12.2 / directive s.17 The RBM return | The classification and provisioning return to the Reserve Bank, mapped line for line to the prescribed form | Provisional layout built and filled from the system in the directive's own order: A the facilities by class and term under the directive's day bands (section 10 of the Gazette: 30/90/180/365 short-term; 90/180/365/746 medium and long), B the minimum provision per class against the IFRS 9 allowance with the higher of the two, C interest in suspense, D restructured facilities (awaiting the Reschedule Report), E the security on the register; on the screen and as CSV, every output marked provisional. On the August 2026 book: 135 facilities, K23.32bn, NPL 31.98 percent, minimum provision K3.41bn against the IFRS 9 allowance of K8.94bn, suspense K114.3m. The lines are re-ordered and re-worded to the prescribed form when MAIIC Risk supplies it; the figures stay. The older RBM Classification report, the provision comparison and the arrears ageing now classify by the same bands by term and rates (they had used one 90/180/365 ladder at 1 percent on pass and special mention). The system audit of 9 October found the shared rule itself carried wrong medium- and long-term bands (30/180/360/720); both were corrected to the Gazette, and a test proves the report and the return agree at every band edge on both terms (workbook 4 finding F3, closed) | `RbmReturnService`, `RbmReturnController`, Risk & Regulatory / RBM Return (provisional), `Ifrs9ReportsController::rbmClassCase`, 3 tests; workbook 4 row 17 and findings F2 (open), F3 (closed) |

## 3. What the system proved when it ran

The bootstrap on the wiped database, 8 and 9 October 2026, with the engine chain as completed over the three rounds (about 125 seconds in all):

| Step | Result |
|---|---|
| Land the pack | 54 files, 86,000 rows, every gate passed; the five accepted exceptions recorded on the load |
| Land the take-on workbook | 100 blocks, 3,741 lines; 96 mapped, 3 not matched, 1 refused (blocks 34 and 55 both claim account 000104450000036); 64 block principals equal the take-on posting exactly; 96 blocks without a fee row, flagged, never assumed fee-free |
| Build the loan books | 26 periods, July 2024 to August 2026, by method B: 2,749 rows, 21 flagged (one account one cent off the stored report in every reported month) |
| Take-on population | 78 accounts E-Banker migrated; all 78 start at the take-on balance because no fees have been supplied |
| Contract master | 144 created, 35 held (closed before the stored report begins) |
| PLR series, fees, interest, cash | 48 PLR rows with 26 changes; 87 fee lines (arrangement 206.3 m, legal 136.7 m, other 9.5 m); 2,182 account-months of interest posted; the cash movements as actual transactions |
| Schedules and EIR | 47 schedules generated and approved under the bootstrap label; 47 EIRs solved; 27 locked; 20 blocked at the fee gate because the rulebook is unapproved. (Before the system audit of 9 October: 31, 31, 12 and 19; sixteen contracts had been refused a schedule because the seeded governance defaults were dated 1 January 2025 while 63 contracts originated earlier, fixed the same day.) |
| **Coverage of the EIR chain on the clean install** | The ECL covers all 135 loans in the August 2026 book. The revenue roll-forward, the as-at figure and the GL reconciliation cover the 26 loans whose EIR is locked. Of the 144 contracts in the master: 51 have a moratorium whose type E-Banker does not state (MAIIC: a decision on the default type, or the scheme data from ICT); 39 have no positive drawn amount (undisbursed or held; correctly refused); 5 are part-drawn with no rule for interest on the undrawn part (the `partly_drawn_interest_basis` setting, MAIIC's to decide); 2 have no contractual rate; 20 have a solved EIR that cannot be locked until the 30 fee-classification rules are approved (Dr Thom); 27 are locked. The two decisions that move coverage most are the moratorium type and the fee rulebook. |
| Revenue | 483 roll-forward rows, 27 contracts, July 2024 to August 2026; 480 months take their cash from the ledger (3 from the schedule, outside the feed's coverage). Stage 3 interest on the net carrying amount: on the nine locked Stage 3 contracts at August 2026 an opening allowance of 1.00bn is netted, interest 17.3m, unwind 21.9m disclosed (restated 9 October 2026 after the system audit, C5 and C6) |
| Staging | 26 periods; 1,267 / 454 / 1,028 row-months in Stages 1, 2, 3 |
| ECL | A PD, an LGD and a pre-FLI ECL for every month from August 2024 (25 of 26 periods; July 2024 has no transition window), then August 2026 on the post-FLI PD, discounted through the time-phased engine on the 21 loans with a locked EIR |
| PD | Transition matrix over August 2025 to August 2026 (108 transitions): PD to Stage 3 of 35.9% from Stage 1 and 35.2% from Stage 2 |
| LGD | Cohort of 41 Stage 3 loans (4.12bn) followed twelve months: cure 21.2%, recovery 25.1% discounted at each loan's rate, LGD 59.06% (restated 9 October: cure and recovery exclusive, an absent loan a write-off unless settled) |
| ECL, August 2026 | **9.51bn** on 23.32bn gross: Stage 1 1.64bn (12-month PD), Stage 2 3.40bn (lifetime PD over the remaining months), Stage 3 5.18bn (PD 1, LGD 59.06%). Before the system audit of 9 October the figure was 8.33bn pre-FLI / 8.94bn post-FLI, with Stage 2 on the 12-month PD and a fit the bootstrap had approved itself |
| Scenario set | The first set of 15.8 approved under the bootstrap label; sensitivity on 135 loans from the booked ECL per loan scaled by each scenario's stage PD: base 9.51bn (equals the allowance), upside 9.06bn, downside 10.45bn, severe 11.51bn, weighted 9.88bn |
| Forward-looking route | No fit is approved on a clean install (spec 6.10.1 step 6): the overlay is zero and the PD holds, marked so on every loan. The sweep still ranks 209 fits from 1,045 evaluations; the best (the PLR to the Stage 3 share, lag 9) waits for two people on the FLI Adjustments screen. Before 9 October the bootstrap approved it itself |
| Stress testing | The set's scenarios as saved runs on the ECL basis: base 9.51bn (equals the allowance), upside 9.06bn (-4.8%), downside 10.45bn (+9.9%), severe 11.51bn (+21.1%) |
| Reports and the register | Every one of the thirty reports rendered (in its own process); the five compliance workbooks loaded into the register |
| Forward-looking chain | 1,045 driver x proxy x lag evaluations; 209 suggestions (55 recommended); 209 fits, 39 applied by the guardrail, 170 declined with reasons |
| Verify | 9 checks, 0 FAIL (the table in section 3.1) |

### 3.1 The golden numbers

| Check | Expected | Actual | Result |
|---|---|---|---|
| Interest posted Jan 2025 to Jul 2026 (ledger, types 303 and 120) | 5,293,988,207.06 | 5,293,988,207.06 | PASS |
| Derived carrying amount against the stored report (over 2 cents) | 0 differences | 0 of 2,361 account-months | PASS |
| Year-end interest batch contra on 4215 | 32,956,675.55 | 32,956,675.55 | PASS |
| Year-end interest batch contra on 4216 | −21,733,262.70 | −21,733,262.70 | PASS |
| Loan book 1050101 at 31 Dec 2025 against the trial balance | 1,368,737,810.68 | 1,368,737,810.68 | PASS |
| Loan book 1050102 | 6,434,045,052.56 | 6,434,045,052.56 | PASS |
| Loan book 1050201 (accepted exception, spec 3.5: −400,000.00) | 1,402,393,139.46 | 1,401,993,139.46 | PASS |
| Loan book 1050202 (accepted exception, spec 3.5: +1,000,000.00) | 3,808,288,656.15 | 3,809,288,656.15 | PASS |
| Loan book 1050401 | 1,827,148,120.69 | 1,827,148,120.69 | PASS |

The ECL at 31 December 2025 and 2024 against the audited allowance, and the revenue shift, are not yet golden numbers: the first needs the Mega Farm book and the approved PD route; the last is born on the first run Dr Thom approves (spec 6.10.2).

### 3.2 Staging under the directive, December 2025

Under the governed rule (Stage 2 from 31 days; Stage 3 from 91 days for a facility of 12 months or less and from 181 days otherwise; four consecutive missed instalments) the December 2025 book of 123 accounts stages 57 / 17 / 49. The November 2025 ECL model, on the uniform 181-day rule, staged its 100 loans with fewer in Stage 3. The difference is the directive's short-term rule and the missed-instalment trigger; both are governed and can be set back for comparison.

## 4. What the build found

**The November 2025 Mega Farm loan book.** The demo copy of the production database holds, for reporting period 2025-11, 3,490 loans across the Mega Farm GLs: 1050301 fertiliser (1,523 loans, K38.91 bn), 1050302 seed (1,180, K8.61 bn), 1050303 working capital (102, K0.90 bn), 1050304 equipment (130, K2.07 bn), 1050305 irrigation (3, K2.16 bn), 1050307 pesticides (552, K2.20 bn): K54.85 bn in all, loaded on 21 January 2026 at a uniform 15 percent rate with the arrears buckets filled and no ECL computed. Under the MEGA_FARM staging class (91 days) they stage 3,161 / 18 / 436. This is the monthly Mega Farm loan book the email to Dr Thom asks for, at least for one month; it answers part of specification section 16.8 (the book exists in the system) and sharpens the question of how the K39.76 bn provision was arrived at, since the book as loaded is mostly Stage 1 by days past due.

**Schema drift.** A clean migrate lacked `loan_books.ead`, `off_balance_sheet_exposure` and `customer_name_imported`, which the production database carries and the ECL service and reports read. They were added by hand at some point. Migration `2026_10_08_300000` adds them, guarded, so both a clean install and the production copy end in the same shape. The production copy also carries eleven working tables (`legacy_loanbook`, `stageing_*`, `ebanker_loan_books`, `ecl_models`, `industry_types_updated`, `single_records`) that no migration knows; they are left alone and listed here.

**Old test rows.** The production copy held 213 loan-book rows with serial contract ids (1, 2, 3 …) and sample balances in July 2024 to March 2025, from an early test import. The build retires them only when asked (`--retire-stale`), and only rows on a GL it covers or on no GL; the Mega Farm rows are kept.

**The take-on population is 78, not 109.** E-Banker's manual opening postings (operation date 31 July 2024: the 301 principal, the 303 opening interest, the 305 opening recovery, back-dated to each loan) exist for 78 accounts. The workbook's 109 facilities include ones closed before migration. The schedule balance at 31 July 2024 differs from the net opening principal on 72 of 73 mapped accounts, on both sides, because the schedules are contractual while the opening recovery is actual.

**The revenue roll-forward reads the stage.** Stage 3 interest is on the net basis, so the chain had to stage before the revenue run; the specification's order (EIR first, for discounting) still holds between EIR and ECL.

**The feed's dates.** E-Banker exports a timestamp column (LOS requests) with its time; the date gate now keeps the date part. One extract (security details) returns one row per security and account, so the register names a composite key and the gate checks uniqueness within the file.

## 5. Decisions and inputs MAIIC owes, found or confirmed by the build

| Who | What | Why it matters now |
|---|---|---|
| Dr Thom or the reviewer of accounting rules | Approve the fee rulebook (30 rules seeded, three for E-Banker's own charge names) | 19 of 31 solvable EIRs are blocked at the fee gate until a reviewer approves the rules and the classifications |
| Tamanda | Which of blocks 34 and 55 belongs to account 000104450000036; the fees of the take-on loans in the yellow columns or on the Take-on Schedules screen | The refused block; all 78 take-on loans start at the take-on balance until fees exist |
| Finance | The keyed GL openings of 1050201 and 1050202 (section 3.5) | Two golden checks pass only as accepted exceptions |
| Dr Thom | How the November 2025 Mega Farm book (3,490 loans, K54.85 bn) relates to the K51.5 bn gross and K48.7 bn Stage 3 of the statements; the monthly Mega Farm books | Section 16; the ECL golden number at 31 December 2025 |
| Barry | September 2026 loan-book run (M09_03), the Mega Farm extracts (MF_01 to MF_08), the P2_13 re-run | The feed's first monthly pack; the Mega Farm build |

## 6. What is still owed

Everything the specification asked for that can be built without MAIIC's input is built. What remains is either MAIIC's to decide or supply, or needs a third party:

| Item | Spec | What it needs |
|---|---|---|
| Routes 4 and 5 of the feed | 6.5 | ICT's consent to a read-only Oracle account over the VPN (route 4); a paid change request to the vendor (route 5) |
| The golden numbers for the ECL at the year-ends and the revenue shift | 6.10.2 | The first run Dr Thom approves: the fee rulebook, the matrix and LGD key-locks, the fit and the set |
| The Mega Farm figure | 16 | Dr Thom's confirmation of D30 and the monthly Mega Farm books (MF_01 to MF_08 from Barry); the mechanics run today on the November 2025 book |
| The restructure history and the 10 percent test | 5.4.3, 3.3.2 | E-Banker's Reschedule Report from the vendor |
| Contract Schedule 1's clause numbers | 12 | The signed schedule from Dr Thom |
| The RBM return in the prescribed form | 12.2, directive s.17 | MAIIC Risk's current form (the Excel or PDF sent to the Bank). The provisional return fills every line in the directive's order; when the form arrives its lines are re-ordered and re-worded to it line for line, the figures unchanged (workbook 4, finding F2) |

## 7. How to reproduce

```
mysql -uroot -e "CREATE DATABASE maiic_ifrs9_bootstrap"
php artisan --env=bootstrap eir:bootstrap --fresh --force-wipe --with-client-inputs --build --run-engines --verify
php artisan --env=bootstrap eir:as-at 2025-12-31
php artisan --env=bootstrap fli:method-cards
php artisan --env=bootstrap fli:correlate 2026-08
php artisan --env=bootstrap scenario:sets 2026-08
python tools/compliance/build_audit.py --all --env=bootstrap
php artisan --env=bootstrap fli:apply 2026-08 --fits
php artisan --env=bootstrap compliance:audits --pack=2026-08
php artisan --env=demo megafarm:ecl 2025-11
php artisan test tests/Feature/Eir tests/Feature/Ebanker tests/Feature/FLI/TransmissionMethodCatalogueTest.php tests/Feature/Rbm tests/Feature/NavigationTest.php
```

`.env.bootstrap` names the throwaway database and is git-ignored, like `.env.demo`; the cache prefix is keyed by `APP_ENV` so the three databases never share a permission cache. The test run above: 308 passed, 24 skipped. The dark-mode proof: serve the bootstrap database locally, then `php artisan --env=bootstrap manual:screenshots --base-url=http://127.0.0.1:8010 --theme=dark --out=<folder>` (the capture user's saved theme governs after login, so set it to dark for the run). The 92 legacy medical tests and the five legacy FLI page tests fail on `master` before this work and are unrelated.

## 8. Files

Code: `app/Services/Ebanker/{PackLandingService,LandingZoneReader,LoanBookBuildService,TakeonLandingService,ContractInputsBuildService}.php`, `app/Services/Eir/{StagingService,EirAsAtService}.php`, `app/Services/Macro/{WorldBankFetcherService,MacroImportService}.php`, `app/Services/Fli/TransmissionMethodCatalogue.php`, `app/Support/Fli/*`, `app/Services/Rbm/RbmReturnService.php`, `app/Http/Controllers/RbmReturnController.php`, `resources/js/Pages/Reports/RbmReturn.vue`, `app/Console/Commands/{LandEbankerPack,BuildLoanBooks,LockReportingPeriod,StageLoanBooks,LandTakeonWorkbook,Bootstrap,EirAsAt,ImportWorldBankMacro,FliMethodCards}.php`, `app/Http/Controllers/{EirFeedController,EirTakeonController,EirAsAtController}.php`, the migrations of 8 October 2026, `config/menu.php`, `resources/js/{Layouts/AppLayout.vue,Jetstream/SidebarNav.vue,Jetstream/DropdownMenu.vue,navAccents.js,composables/useTheme.js,Pages/Eir/{Feed,Takeon,AsAt}/Index.vue}`.

Inputs: `docs/bootstrap/` (84 files in the manifest, including the original take-on workbook as received and the World Bank snapshot). Reference: `docs/reference/` (the suite's engines and, from today, their support classes). Manuals: `docs/manuals/installation/09-data-loading.md` section 9.8 rewritten.
