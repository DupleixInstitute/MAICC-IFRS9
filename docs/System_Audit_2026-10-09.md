---
kicker: MAIIC | IFRS 9 AND EIR PLATFORM
title: System audit, 9 October 2026
subtitle: What the system proves, what is wrong in it, what was corrected during the audit, and the order in which the rest should be done
date: 9 October 2026
prepared: Prepared by Dupleix Institute (Edward Mazibuko, Director, Data Analytics) for MAIIC (Dr Thomson Kumwenda, CFO) and the Dupleix engagement file
status: Scope is the MAICC-IFRS9 system at commit f5b08fc on branch eir_revenue_recognition, audited against specification v4 of 7 October 2026, IFRS 9, the RBM Credit Risk Management for DFIs Directive 2018, and ordinary standards of security, data integrity and operability. Confidential to MAIIC and Dupleix.
---

## 1. Verdict

The system does what the After Build Report of 8 October says it does in the sense that every step named there runs from one command on a wiped database and produces the figures quoted. The audit went behind those figures, and the honest summary is this: **the data foundation is sound and proven to the cent; the impairment engine is not yet on an IFRS 9 basis; and the legacy modules the platform was forked from expose the whole loan book and its governed inputs without a login.** None of the three can be left as it is before MAIIC relies on a number from the system or puts it on a network.

In order of consequence:

1. **The ECL is a 12-month ECL for every stage.** Stage 2 loans (8.35bn of exposure, 36 percent of the book at August 2026) are provided at the 12-month PD, not the lifetime PD; the lifetime PD the engine computes is written to a column nothing reads. The allowance is also undiscounted in the bootstrap. The 8.94bn quoted in the After Build Report understates a lifetime, discounted ECL by an amount that cannot be stated without running the correct engine, but a Stage 2 loan with 36 months to run and a 10 percent annual PD is provided at 10 percent where IFRS 9 requires 27.
2. **Stage 3 interest is recognised on the gross amount**, not the net carrying amount, because the revenue engine reads an allowance column (`expected_loss_provision`) that the governed build never writes. The "net basis" setting is in force and the code path exists; it has no allowance to net. The test that covers it seeds the column by hand.
3. **Write-offs (type 300) and the Nascomex equity entries (type 401) are treated as cash received** by the loan-book build and the EIR roll-forward, against the specification's own list at section 3.3 (22.3m and 535.7m in the committed pack).
4. **180 web routes carry no authentication**, including the loan-book import, the collateral register, the transition-matrix and LGD writes into the loan book, the ECL recalculation and a one-click regression approval. One of them interpolates request input into a raw SQL statement. All of these are modules of the product the platform was forked from, not work of this engagement, but they are on the server MAIIC will run.
5. **The RBM classification bands built on 8 October were wrong for medium- and long-term facilities** (they put a 31-to-90-day facility in special mention at 5 percent where the Gazette says standard at 0, and used 360/720 where the Gazette says 365/746). This was Dupleix's error, found by this audit and corrected the same day at commit `f5b08fc`; the test now asserts every boundary in the Gazette.

The rest of this report gives the evidence, the full list of findings by severity, what was corrected during the audit, and the order in which the remainder should be done.

## 2. How the audit was done

Everything below rests on things that were run or read, not on the After Build Report's own claims.

| Proof | Result |
|---|---|
| Clean install: `eir:bootstrap --fresh --force-wipe --with-client-inputs --build --run-engines --verify` on a wiped throwaway database | Completed in 183 seconds; 9 of 9 golden checks pass (ledger interest 5,293,988,207.06; method B equals the stored report on 2,361 of 2,361 account-months; the December 2025 GL ties incl. the two accepted FInES exceptions; the 4215/4216 year-end legs). |
| Every IFRS 9 report rendered for August 2026 (`ifrs9:smoke-reports`, now all 30 methods) | All OK after the audit's own fixes (two reports had thrown a division error on a sector with no exposure; see section 6). |
| Full test suite | See section 7 for the counts; the suite cannot complete in one process under XAMPP's 300-second execution limit, which is itself a finding. |
| Dependency audit (`composer audit`, `npm audit --omit=dev`) | Composer: two medium advisories (symfony/routing CVE-2026-48784 and CVE-2026-45065), one low (symfony/polyfill-intl-idn), two abandoned packages (beyondcode/laravel-websockets, box/spout). npm: 33 advisories (1 critical, 19 high) led by TinyMCE XSS in the help editor and a yaml stack overflow. |
| Route inventory (`route:list` with controller middleware merged) and a read of every `$this->middleware` in the controllers | 180 of 724 web routes have no `auth` middleware. |
| Four independent code reviews, each read-only with file-and-line evidence: specification conformance (sections 3 to 16, every decision, the 48-setting catalogue), financial-engine correctness, security and access control, data integrity and operability | The reviews' findings were re-read against the code before inclusion; the Critical items in section 4 were each re-verified by hand. |
| The Gazette itself (`docs/regulatory`, pages 683 and 685) | Read in full for the classification bands and rates. |
| The bootstrapped database queried directly for coverage | 135 loans in the August 2026 book; 144 contracts in the EIR master; 31 schedules generated and 12 EIRs locked (section 5). |

## 3. What the system proves (and should keep)

- **The ledger is the record and the build reproduces it.** Method B derives every account-month's carrying amount from the landed ledger and equals E-Banker's own stored report to the cent on 2,361 of 2,361 account-months; the loan GLs tie to the December 2025 trial balance to the cent, with the two FInES exceptions carried as accepted exceptions rather than hidden.
- **Landing, gates and versioning.** Packs land with hashes, counts, dates, key uniqueness and query-id checks; rows are append-only and versioned with their pack; a failed gate quarantines the load.
- **Maker-checker is enforced server-side** on every path built in this engagement (loan-book build, governance settings, scenario sets, regression fits, compliance sign-off); the bootstrap's own approvals carry a label that is visibly not a person's.
- **The EIR solver** is sound: Newton with bisection fallback, bounded, residual re-checked; locked EIRs cannot be recomputed; reopening archives and voids downstream rows.
- **Staging** reads days past due from the oldest overdue instalment under the governed basis, applies the directive's Stage 3 thresholds by tenor class (91 days short-term, 181 otherwise), allocates receipts to instalments in due-date order for the four-instalment trigger, and writes the post-qualitative stage the ECL and FLI read.
- **Units are consistent where it matters most**: the facility utilisation rate is stored as a fraction and read as one by every EAD reader; PD is stored in percent in the matrix and divided into a fraction by both PD writers; Stage 3 PD is forced to 1 in every engine.
- **The forward-looking chain** fails closed (observations, sign, R-squared, p-value), declines with reasons, and the post-FLI PD is bounded and weighted by validated weights.
- **The suite shell and theme**: every one of 120 captured screens renders in dark mode (measured, not assumed) and the light theme is unchanged.
- **Governance of the feed token**: the API route compares the bearer token in constant time and refuses an empty configured token.

## 4. Findings

**Closure, 9 October 2026 (same day, after the audit's first issue).** The "doable" steps of section 9 (1 to 7) were done and committed on `eir_revenue_recognition`: `d58a8b0` (C7, C8), `ba117ff` (C1, C2, C3, H7, M3), `7d339a6` (C5, C6, M17), `bf616a7` (H1, M15), `d062a53` (C9, H3, M8, M9), `4a31816` (H4, H11, H9), `af95179` (H2, H8, H5, H6), with `4a98dca` (H10) and `f5b08fc` (C4) from earlier in the day. Each finding below carries **Closed** with its commit where that is so; the figures in section 8 are restated at the end. The open findings are those that need MAIIC (H12, H13's missing ties) or the remaining Medium and Low items.

Severity: **Critical** = a wrong number in a financial statement or a regulatory return, or an unauthenticated path to financial data; **High** = wrong under a realistic condition, or a control that can be bypassed; **Medium** = a fragile or undocumented assumption, or a gap against the specification that does not yet move a number; **Low** = cosmetic or latent. Each finding names the file so an engineer can go straight to it.

### 4.1 Critical

| # | Finding | Where | Effect and example | Fix |
|---|---|---|---|---|
| C1 | **Closed `ba117ff`.** Stage 2 allowance is a 12-month ECL; `lifetime_pd` is written and never read; the bootstrap books an undiscounted ECL | `ExpectedCreditLossController.php:375-383` (ecl = pd × lgd × EAD with the 12-month PD for every stage); `RecalculateEcl.php:24` defaults `--discounting=undiscounted` and `Bootstrap.php:315` accepts the default; `PdEngineService.php:71` writes `lifetime_pd` | Stage 2, 12m PD 10%, 36 months remaining, LGD 0.5, EAD 1,000,000: booked 50,000; lifetime 1−0.9³ = 27.1% gives 135,500 before discounting. Every report, the FS note and the RBM return read `ecl_value` | Stage 2 uses `lifetime_pd`; the bootstrap runs the time-phased engine (`--discounting=discounted`) once C2 and C3 are fixed; the FS note then carries lifetime, discounted figures |
| C2 | **Closed `ba117ff`.** `remaining_tenor` is months from the governed build and years from the legacy importer; readers disagree | Writes months: `LoanBookBuildService.php:549`. Writes years: `ProcessLoanImportJob.php`, `LoanBooksImport.php`. Reads months: `PdEngineService.php:70`. Reads years: `TransitionMatrixController.php:523,546` (lifetime PD = 1−(1−pd)^tenor), `TimePhasedEclService.php:169` (×12), `EclDiscountingService.php:81` | Under build B a 24-month Stage 2 loan: the Monthly Probability screen's lifetime PD is 1−0.9^24 = 92% against the engine's 19%; the discounting service discounts over 24 "years" | One unit (months), stated on the column, and every reader converted; a test per reader |
| C3 | **Closed `ba117ff`.** Stage 3 ECL in the time-phased engine is discounted from the wrong date and the recovery plan is netted before discounting | `TimePhasedEclService.php:69,105,114-115` | EAD 100,000, LGD 0.6, 24-month schedule, EIR 10%: 49,587 instead of 60,000; the covering test asserts the understated range | Loss at the reporting date, or EAD less the present value of each recovery from its own date; the LGD fed in is the undiscounted cohort LGD (`LgdEngineService.php:70`) and should be discounted |
| C4 | RBM classification bands wrong for medium- and long-term facilities (built 8 October) | `RbmReturnService.php:36`, `Ifrs9ReportsController.php` (RBM constant and `rbmClassCase`), `tests/Feature/Rbm/RbmReturnServiceTest.php` | A 36-month facility 60 days overdue on 10m: minimum provision 500,000 instead of 0; 361-365 days long-term Doubtful (50%) not Substandard (20%); 721-746 Loss not Doubtful | **Corrected at `f5b08fc`** to the Gazette's section 10 (short-term 30/90/180/365; medium and long 90/180/365/746); the test asserts every boundary on both terms; workbook 4 F3 records both corrections |
| C5 | **Closed `7d339a6`.** The revenue roll-forward deducts scheduled instalments as cash after the borrower's last receipt | `EirRevenueService.php:171-178`: "covered" is the contract's own first and last transaction; after the last receipt every month falls back to the schedule | Loan 1,000 at 1% a month, 100 a month schedule, last receipt March: by December the engine's gross is about 100 while the ledger shows about 1,100. This is the Stage 3 population | The window is the feed's coverage (`LandingZoneReader::lastLedgerDate()`), not the contract's; a month inside coverage with no receipt is zero cash |
| C6 | **Closed `7d339a6`.** Stage 3 "net carrying amount" interest never takes effect | `EirRevenueService.php:70` reads `loan_books.expected_loss_provision`; the governed build's column list (`LoanBookBuildService.php:45-53`) does not write it; only the legacy `LoanBookController.php:331` and test data do. The bootstrap also runs revenue before the ECL (`Bootstrap.php:226-258` then `:315`) | Gross 1,000, allowance 600, 1% a month: recognised 10, should be 4, on every Stage 3 loan (8.26bn of exposure) | Read `ecl_value` (the allowance the engine writes); run the ECL for the prior period before the revenue step, or carry the opening allowance on the roll-forward row |
| C7 | **Closed `d58a8b0`.** 180 web routes have no authentication | `routes/web.php` modules: `loss-given-default` (22 routes), `transition-matrix` (19), `loan_application` (18), `collateral` (13), `credit-loss-data` (11), `transition-matrix-cummulative` (10), `lgd-calculations` (9), `regression` (9), `file` (9), `expected-credit-loss` (5), `groups`, `scenarios`, `scenario-profiles`, `macro-forecast-weighted`, `macro-statistics` (legacy), `transition-profile`, `api/*` | `POST /transition-matrix/{id}/update-loan-book` rewrites the PDs that feed the ECL; `POST /expected-credit-loss/calculations` recomputes the allowance; `GET /api/collateral-registers` returns the whole collateral register as JSON with CORS open to any origin; `PATCH /regression/{model}/approve` approves a model with no user and no log. None needs a cookie | Wrap every legacy module in `auth` plus a permission; delete the dead `routes/api.php` routes (two name controller methods that do not exist); this is a day's work and the first thing to do before the server is reachable from the MAIIC network |
| C8 | **Closed `d58a8b0`.** Unauthenticated SQL interpolation | `TransitionProfileOptionController.php:66-96` (`POST /transition-profile/categories/reorder`): `categories.*.id` validated with `exists:` then interpolated into `DB::statement("... WHEN {$id} THEN ...")`. MySQL's numeric coercion lets `"5 OR 1=1"` pass an `exists` check against id 5 | Arbitrary SQL in the CASE expression from an unauthenticated request | Bind the ids; cast to int; authenticate the route (C7) |
| C9 | **Closed `d062a53`.** The bootstrap approves a regression fit under its own label and computes the ECL on it | `Bootstrap.php:298-309` proposes, approves and applies the best fit; spec 6.10.1 step 6 says the overlay is zero and the post-FLI PD marked "no adjustment: bootstrap" until two people approve a fit; the impairment workbook's own finding F2 states the spec's rule | The 8.94bn ECL (and the 10.24bn weighted sensitivity) in the After Build Report rests on a fit no MAIIC person approved; `FliRouteService.php:52` lets any non-null label bypass the different-person check | The bootstrap applies the route with the overlay at zero and marks the PD; fit approval is a screen action by two people; the label bypass is limited to the bootstrap's constant |

### 4.2 High

| # | Finding | Where | Effect | Fix |
|---|---|---|---|---|
| H1 | **Closed `bf616a7`.** Write-offs (300) and the Nascomex equity entries (401) are classed as cash receipts | `LoanBookBuildService.php:42` (`TYPE_RECEIPT` includes 401 and 300); `ContractInputsBuildService.php:176-180` | 22.3m of write-offs and 535.7m of equity entries in the committed pack flow into `repayments` and the EIR roll-forward's `cash_received`. Spec 3.3 lists 300 as write-off and 400/401 as equity. The method B tie to the stored report still passes because the stored report makes the same mistake; the tie proves reproduction, not correctness | A write-off reduces gross carrying through the allowance, not cash; 401 is excluded from loan cash; re-prove the ties with the exceptions stated |
| H2 | **Closed `af95179`.** Tranche facilities: later drawdowns never enter the EIR cash-flow vector | `EirContractInputService.php:81-82` uses `drawn_amount` at origination and the version-1 schedule; `DisbursementService::laterDrawdownFlows` is unused; `EirReadinessService.php:30,44-49` | Approved 100m, 60m drawn at origination and 40m six months later: the solver sees 60m out and 100m plus interest in; the EIR solves far above the contractual rate. Spec v4 counts 58 tranched facilities | Negative (disbursement) flows at their dates from P3_12; a test on a two-tranche facility |
| H3 | **Closed `d062a53`.** Scenario weighting is applied twice over two different sets | `FliRouteService.php:127-136` writes the probability-weighted PD across the governed set; `TimePhasedEclService.php:82-85` then weights again by `ecl_scenario_assumptions` (a seeded "controlled synthetic" set, BASE 60 / UPSIDE 20 / DOWNSIDE 20), which `Bootstrap.php:105` seeds as approved | Double-weighting whenever the time-phased engine runs; and two different per-scenario answers for the same period (the sensitivity's typed multipliers against the route's regression adjustments: 10.24bn against 8.94bn in the After Build Report) | One governed set; the ECL per scenario, weighted once, and the sensitivity reads the same per-scenario ECLs (spec 15.5) |
| H4 | **Closed `4a31816`.** The reporting-period lock is honoured only by the loan-book build | `LoanBookBuildService.php:616` is the sole reader of `reporting_period_locks`; `EirRevenueService::run(recalculate: true)` supersedes rows in a locked period; ECL, staging, PD, LGD and FLI write freely | A locked month can be restated by any engine; `EirAsAtService.php:66-69` claims the opposite | One lock check in a shared helper, called by every writer; a test per writer |
| H5 | **Closed `af95179`.** Reports and the FS note split by the pre-qualitative stage; the ECL was measured on the post-qualitative stage | `Ifrs9ReportsController.php:137,161,268,301,333,367,390,419,628`; `ExpectedCreditLossController.php:420`; `StressTestService.php:25-26,44` | A loan moved to Stage 3 by the four-instalment trigger (post-only) appears in "Stage 1" with PD 1.0 in "Closing ECL by Stage" and the impairment charge by stage | Every reader splits by `ifrs9stage_post_qualitative` |
| H6 | **Closed `af95179`.** The FS note's "gross carrying amount" is the EAD | `Ifrs9ReportsController.php:628-633` uses carrying + commitments × utilisation as gross carrying; NPL ratios at `:573` and `:1240` are on EAD | Carrying 60m, commitments 40m, utilisation 0.6: "gross carrying" 84m in the note | Gross carrying = carrying amount; EAD is its own column |
| H7 | **Closed `ba117ff`.** LGD cohort workout counts a written-off loan as fully recovered and double-counts a cured loan that also paid down; recoveries undiscounted; no collateral | `LgdEngineService.php:38,49-56,70` | Cohort of two 500s, one written off (absent from the end book): recovery 50 percent, LGD 0.5; the true recovery on the absent loan is 0 | Absent-from-book is a write-off unless the ledger shows settlement; cure and recovery are exclusive; discount at the EIR; collateral from the register |
| H8 | **Closed `af95179`.** 12-month PD from a short window is not annualised | `PdEngineService.php:36-38,65-67` | With books from October 2025 run for January 2026, the "12-month PD" is a three-month default rate | Annualise 1−(1−p)^(12/months) and record the window |
| H9 | **Closed `4a31816`.** The `admin` role bypasses maker-checker on governance approvals and EIR locks | `EirGovernanceController.php:67-70` (`adminOverride()` = has role admin), used at ten call sites; `EirCalculationService.php:108-111` | An administrator can propose and approve a governed setting alone, and lock an EIR they calculated | The override is a governed setting with its own approval, or removed; schedule approval (`ScheduleWorkflowService.php:275`) should also require a different person from the editor |
| H10 | Governance defaults were effective from 1 January 2025; 63 of 144 contracts originated before 2024 | `GovernanceSettingsSeeder.php` (`EFFECTIVE_FROM`); `GovernanceService::inForce()` resolves at the contract's origination date | 16 contracts on the clean install could not generate a schedule because "no approved value is in force for day_count" at origination | **Fixed (9 October, after the audit's first issue):** defaults seeded from inception; a migration backdates the untouched defaults on installed databases (49 rows on the demo copy, audit-logged); a test proves every catalogue setting resolves on MAIIC's earliest origination date (4 March 2020). On the clean install schedules rose from 31 to 47 and locked EIRs from 12 to 27; golden checks unchanged at 9 of 9 |
| H11 | **Closed `4a31816`.** Staging thresholds are resolved at today's date, not the period end, and sit outside maker-checker | `StagingThreshold.php:36-46` uses `now()`; `StagingService.php:113` silently defaults to [31,181] | A re-run of a past period uses today's thresholds, against D21; a missing row is not an error | Resolve at the period end; thresholds proposed and approved like every other setting; no silent default |
| H12 | Mega Farm ECL misreads decision D30 | `MegaFarmEclService.php:58,117-125`: `ecl_value = EAD × PD × LGD × 5%` | The loan allowance is booked at 5 percent of the programme loss. D30: the provision is the fund's; MAIIC's 5 percent is a share of interest, written down separately. Neither the fund-side provision nor the receivable write-down is produced; a later portfolio ECL run restores the loan to 100 percent (`ExpectedCreditLossController.php:375` has no GL exclusion) | Awaits Dr Thom's confirmation of D30 as stated in the After Build Report; until then the Mega Farm figure must not be quoted |
| H13 | The audit's own `--verify` covers 9 checks against 13 section-9 ties and 6 golden numbers | `BaselineService.php` | No 2024 year-end GL tie, no monthly GL tie, no interest-income YTD tie, no 28-posting count, no feed re-pull test, no collateral or take-on counts; the ECL golden numbers await the first approved run | Add the missing ties as the data for each is confirmed; the After Build Report's "9 checks, 0 FAIL" is accurate about what runs and should say so |
| H14 | The full test suite could not run in one process | Eleven legacy controllers call `ini_set('max_execution_time', 300)` for their own long requests (`ExpectedCreditLossController.php:287`, `LoanBookController.php:562`, `CollateralController.php:324`, the LGD controllers); once a test touches one, every test that follows shares a five-minute clock and the suite dies with "Maximum execution time exceeded" (three attempts, each from a different test) | A CI or release check that runs "the tests" silently runs part of them; the After Build Report's "308 passed" was a directory run, not the suite | **Fixed:** `tests/TestCase::setUp` resets the limit to unbounded before every test; the controllers should raise the limit only when it is lower (not lower it from unlimited) |

### 4.3 Medium

| # | Finding | Where |
|---|---|---|
| M1 | Trial balances bypass the landing zone (imported directly, no hash, no load row); GL_02 is landed but not read by the reconciliation | `Bootstrap.php:142-147`; `TrialBalanceImportService.php`; `EirGlReconciliationService.php` |
| M2 | Gates missing at the door: no account-in-master check, no balance-history-to-ledger tie, no month-end-row-per-account check, no numeric parse, query version recorded but not validated; quarantine keeps no rows; dates accepted in whatever format the manifest declares (the committed pack is m/d/yyyy, against D23) | `PackLandingService.php:65-129,145-148,288-299` |
| M3 | **Closed.** The manual-overlay register of spec 14.6 does not exist; the "Manual overlay" route reads the latest legacy `fli_adj` row | `FliRouteService.php:106-111` |
| M4 | The overlay-lock rule of 15.7 is an empty `if` block; `lock()` checks nothing | `ScenarioSetService.php:91-93,154-162` |
| M5 | The Scenario Sets screen cannot set weights, add scenarios or edit shocks; only the fixed first set can be seeded. The decision the spec reserves for the CFO has no screen | `ScenarioSetController.php`; `Pages/Governance/ScenarioSets.vue` |
| M6 | Two regression chains run in parallel: the new univariate path under maker-checker, and the legacy screen (`RegressionController.php:227-233`) with one-click approval, no test and no second person, still in the menu as "Regression Analysis" | |
| M7 | Take-on "recompute from origination" writes a label and nothing else: no EIR solve from the workbook schedule, no `TAKEON_WORKBOOK` schedule | `TakeonLandingService.php:179-228` |
| M8 | **Closed.** The bootstrap gives Governance-Centre-type approvals it is told never to give: fee classifications reviewed under the label, EIR locks by administrator override; and, inconsistently, leaves the 30 seeded fee rules unapproved, so 19 contracts stall at the fee gate and EIR coverage on the clean install is 12 of 135 loans | `Bootstrap.php:182-189,242`; `FeeRuleMatcher.php:72-77` |
| M9 | **Closed.** Sensitivity reads `coalesce(pd_prefli, pd_value, 12m_pd)` where `12m_pd` is percent from one writer and a fraction from another; and uses the stored `ead` column, which the build fills at 100 percent utilisation unlike every other EAD reader | `ScenarioSetService.php:252-257`; `LoanBookBuildService.php:555`; `SeedReportFields.php:81` |
| M10 | PD is assigned by the pre-qualitative stage and the matrix is pre-to-pre; the instalment trigger is outside the PD's default definition; loans that leave the book count as "Paid" in the denominator | `PdEngineService.php:67,69`; `MaiicTransitionProfileSeeder.php:22` |
| M11 | No cure or probation period: stage is recomputed from current DPD only; the prior stage is selected and never read | `StagingService.php:53-71` |
| M12 | Fourteen catalogue settings are read by nothing but the Governance Centre and the trace (`fli_expected_sign_test`, `macro_source_precedence`, `ebanker_feed_route` and others); the FLI thresholds live in a second store (`governed_parameters`) with different seed defaults that a direct write would bypass | `GovernanceService.php`; `GovernedValues.php:38-64`; `PollFeedFolder.php:37-41` |
| M13 | As-at date: CSV only (spec: Excel and PDF); part-month accrues on the EIR compounded rather than the section-3 convention; take-on basis not read | `EirAsAtService.php:71-75`; `EirAsAtController.php:76` |
| M14 | Zip extraction from the feed API and the poller relies on PHP's internal path sanitisation with no explicit check against `..` entries | `routes/web.php:1319`; `PollFeedFolder.php` |
| M15 | **Closed.** `COLLECTION_TYPES` still contains 'Interest' (spec 7.4 says corrected); reversal pairing (7.1) not found; over-sanction flag (7.3) not found; `RATE_BASIS` mapping still in the legacy Excel importer | `EirRevenueService.php:29`; `ContractMasterImport.php:71-75` |
| M16 | Help chapters still carry the old group names; Correlation Finder and Auditor Pack are CLI-only with no screen; `macro.view` / `macro.manage` permissions do not exist (macro commit uses `eir.govern`) | help seeders `07a`, `08`; `config/menu.php`; `MacroStatisticsController.php:26` |
| M17 | **Closed.** Engine chain in the bootstrap runs PD, LGD, FLI, ECL, stress and reports for the last period only, against "every period from the first full month" | `Bootstrap.php:263-367` |
| M18 | Section 16 beyond staging and PD is not built: the 15 percent interest split, the maize repayment and buyer receivable, the fund liability roll-forward, the Mega Farms report, seasonal segmentation; LGD borrowed from MAIIC's own book; scalar fallback hard-coded from the statements | `MegaFarmEclService.php:150-151,168-179` |

### 4.4 Low and hygiene

| # | Finding | Where |
|---|---|---|
| L1 | The repository carries a 715-file `corporate/` folder (a credit-scoring model with `.exe`, `.rar`, raw InnoDB `.ibd` and SQL dumps) and a medical ICD-10 XML under `storage/app`, both from the product's initial commit, both deployed to the client's server | `corporate/`; `storage/app/icd10cm_tabular_2021.xml` |
| L2 | Other-client strings outside `docs/reference`: `FDH` in method keys and worked examples the user sees (`AdjustmentMethods.php`, `TransmissionMethodCatalogue.php:181-183`, `GovernedValues.php:47,54`, `Guardrail.php:18`, `StressTestingController.php:14-15`); `ZNBS` in seeded support-ticket text (`TicketSeeder.php:234,253`); three committed session transcripts under `docs/sessions` naming other clients throughout; build scripts under `docs/build-files` with other-client paths. The compliance PDF footer was the worst instance and is fixed at `f5b08fc` | |
| L3 | Composer advisories (symfony/routing ×2 medium, polyfill-intl-idn low), two abandoned packages; npm 33 advisories (TinyMCE XSS in the help editor, yaml) | `composer.lock`, `package-lock.json` |
| L4 | `.env.example` and config: cache prefix was shared across the production, demo and bootstrap env files (fixed 8 October: keyed by `APP_ENV`); `SESSION_SECURE_COOKIE` unset by default; dead CSRF exclusion `file/upload` | `config/cache.php`; `config/session.php:171`; `VerifyCsrfToken.php:14` |
| L5 | `ScheduleWorkflowService.php:76` treats a stated rate above 1 as a percent, so a stated "1" (1 percent) becomes 100 percent; `CalculateEirService.php:88` 30/360 is 30E/360; `lifetime_pd` and `12m_pd` are decimal(8,2) so an 8-dp write is truncated; `StagingService.php:156` casts `TRANSAMT` without stripping commas | |
| L6 | Legacy test suites (92 "medical" tests and five FLI page tests) fail on `master` before this engagement and remain in the tree; test classes build their own sqlite schemas by hand, so the real migrations are exercised only by the bootstrap | `tests/` |

## 5. Coverage of the EIR chain on a clean install

The After Build Report quotes "31 schedules, 31 EIRs solved, 12 locked". The audit traced the other 113 contracts and the 19 unlocked EIRs on the freshly bootstrapped database:

| Reason the schedule was not generated | Contracts | Whose it is |
|---|---|---|
| A moratorium is stated but its type is not, on the contract or its scheme | 51 | MAIIC (a decision on the default moratorium type, or the scheme data) |
| Drawn amount not positive (with other reasons) | 39 | Data: undisbursed or held contracts; the readiness rule is right to refuse them |
| No approved value for `day_count` at the origination date | 16, now 0 | Dupleix (finding H10, fixed the same day; the 16 now generate) |
| Part-drawn and neither contract nor scheme says whether interest is on the drawn amount | 5 | MAIIC (`partly_drawn_interest_basis` is a catalogue setting nothing reads yet; M12) |
| Contractual rate missing or invalid | 2 | Data |
| **EIR solved but not locked: fee lines not independently reviewed** | **19, now 20** | The 30 seeded fee rules are unapproved by design (Dr Thom's rulebook approval is owed); see M8 for the bootstrap's inconsistency |

So on the clean install as first audited, the revenue roll-forward, the as-at service and the GL reconciliation covered 12 of 135 loans; after the H10 fix they cover 26 of 135 (27 locked EIRs), and the ECL covers all 135. The next two steps are MAIIC's: the moratorium type (51 contracts) and the fee rulebook (20 EIRs). These facts are now in the After Build Report's section 3 in those words.

## 6. Corrected during the audit

Each is committed with its evidence.

| Commit | What |
|---|---|
| after `e176d1d` | Governance defaults from inception (H10): seeder, backdating migration, test; schedules 31 to 47 and locked EIRs 12 to 27 on the clean install |
| `f5b08fc` | RBM bands to the Gazette (C4) in the return, the classification report, the provision comparison, the arrears ageing and the test; compliance PDF footer names MAIIC |
| `f324814` (8 October, found by the dark-mode sweep's capture of every screen) | Six ECL-over-EAD ratios divided by the string "0.00" (the sector and product-group ECL reports threw on a sector with no exposure); the report smoke now covers all 30 report methods; the licence page on a fresh database; the cache prefix keyed by environment (a permission cache filled by one database was served to another) |
| `742e7a7` (8 October) | The RBM Classification report put on one rule with the return (the rule itself then corrected at `f5b08fc`) |

## 7. Test suite

The whole suite was run in one process for the first time (it had died at five minutes on every earlier attempt; see H14, fixed). Result: **577 tests, 2,779 assertions, 6 minutes 33 seconds; 455 passed, 30 skipped, 71 errors, 21 failures.**

| Failing group | Count | Cause | Belongs to |
|---|---|---|---|
| Medical and inventory modules (patients, vitals, invoices, inventory purchases and sales, tariffs, SMS gateways, expense types, claim batches, appointments) | 79 | Models, factories and the `receptionist` role no longer exist; the tests are left over from the product the platform was forked from | The fork; retire the tests and the modules |
| Legacy FLI page tests (`Tests\Feature\FLI\ExternalCalculationsTest`) | 5 | 403 on the legacy external-calculations screen and a NOT NULL constraint in its parameters table; failing on `master` before this engagement | The legacy FLI chain (M6) |
| Jetstream scaffold (`RegistrationTest`, `ProfileInformationTest`, `DeleteAccountTest`), `DatabaseTest`, `LoanApplicationApprovalStageAssignedMailTest` | 8 | Features disabled or models removed (`App\Models\Role` not found; `users` table absent from the hand-built sqlite schema) | The fork |
| **Tests written in this engagement** (EIR, E-Banker, staging, PD/LGD, FLI chain and route, scenario sets, compliance, Mega Farm, RBM, navigation, help) | **0 failing** | | |

Two observations on the suite itself. First, the engagement's tests build their own sqlite schemas by hand, so the real MySQL migrations are exercised only by the bootstrap (which does run them from nothing, so they are proven, but a column drift between a test schema and a migration would not be caught by the tests). Second, several engine tests encode the behaviour the engine has rather than the behaviour IFRS 9 requires (C3's covering test asserts the understated range; C4's test asserted the wrong bands until today): a passing test is evidence of reproduction, not of correctness, and the fixes in section 9 must change the expectations, not just the code.

## 8. What this means for the After Build Report's figures

As first issued, the audit said the ECL, sensitivity, stress and revenue figures of the After Build Report should not be quoted. After the closures above the clean install (`af95179`, 9 October 2026, August 2026) reads:

| Figure | Before the audit | After steps 1 to 7 | Basis now |
|---|---|---|---|
| ECL, undiscounted | 8.94bn (12-month PD every stage; a fit the bootstrap approved; 300/401 as cash) | **9.51bn**: Stage 1 1.64bn, Stage 2 3.40bn on the lifetime PD, Stage 3 on a discounted workout LGD of 59.06 percent | IFRS 9 by stage; overlay at zero until two people approve a fit; a write-off is derecognition not cash |
| ECL, discounted | not run | 21 loans with a locked EIR discounted through the time-phased engine; the rest carry the undiscounted figure, flagged | the time-phased engine on the governed set written on the loan |
| Scenario sensitivity, weighted | 10.24bn beside an 8.94bn ECL | **9.88bn**; Base 9.51bn equals the allowance to the kwacha; Upside 9.06bn, Downside 10.45bn, Severe 11.51bn | the booked ECL per loan scaled by the scenario's stage PD; one weighting |
| Stress, base and severe | 8.94bn / 11.86bn (12-month PD, DPD-only stage) | **9.51bn / 11.51bn** | the booked ECL scaled by the stressed PD and LGD, by the measured stage |
| Stage 3 interest (nine locked Stage 3 contracts) | on gross | on the net carrying amount: allowance 1.00bn netted, interest 17.3m, unwind 21.9m disclosed | the opening allowance the engine wrote |
| Revenue roll-forward, schedule-derived months | most of the Stage 3 history | 3 of 483 | the ledger covers a month while the feed runs past it |
| EIR coverage | 12 of 135 loans locked | 27 locked (26 with an amortisation); 20 wait only on the fee rulebook; 32 facilities now solve with their tranches at their dates | |
| Golden ties | 9 of 9 | 9 of 9 | unchanged by any of this |

What still stands between these figures and a board paper is MAIIC's: the fee rulebook approval (20 EIRs, and the initial net investment of every loan with a financed fee), the moratorium type (51 schedules), D30, and the first approved run that births the golden ECL and revenue-shift numbers.

## 9. Order of work

Steps 1 to 7 below were done on 9 October 2026 (the commits are in section 4). Step 8 is what remains to build, step 9 the hygiene.

1. ~~Authenticate the legacy modules and bind the SQL (C7, C8)~~ done, `d58a8b0`.
2. ~~Stage 2 lifetime PD, one tenor unit, Stage 3 from the reporting date, discounted LGD (C1, C2, C3, H7)~~ done, `ba117ff`.
3. ~~Stage 3 allowance into the revenue engine; revenue after ECL; schedule fallback bounded by the feed (C5, C6)~~ done, `7d339a6`; the revenue-shift golden number is born on Dr Thom's first approved run.
4. ~~300 and 401 out of cash (H1)~~ done, `bf616a7`; the ties re-proved at 9 of 9.
5. ~~Bootstrap to the spec's rule on fits and approvals; one scenario weighting; the sensitivity from the per-scenario ECL (C9, H3, M8)~~ done, `d062a53`.
6. ~~Thresholds at the period end under maker-checker; the lock honoured by every writer; admin override governed (H11, H4, H9)~~ done, `4a31816` (thresholds are not yet proposed and approved like a setting; they are resolved at the period end with no silent default).
7. ~~Tranche drawdowns in the EIR vector; PD annualisation; post-stage splits; gross carrying in the note (H2, H8, H5, H6)~~ done, `af95179`.
8. **The remaining spec gaps** (M1, M2, M4 to M7, M10 to M14, M16, M18): the overlay register, the set editor, the gates at the door, the trial-balance landing, the legacy regression chain, the take-on recompute, cure and probation, the decorative settings, the as-at exports, section 16 once D30 is confirmed: two to three weeks, to be sequenced with MAIIC's decisions.
9. **Hygiene** (L1 to L6): remove `corporate/` and the ICD file, strip other-client strings, update dependencies, retire the legacy suites: a day.

**Decisions MAIIC owes that the audit makes more urgent:** D30 (Mega Farm basis), the fee rulebook approval (19 EIRs wait on it), the default moratorium type (51 schedules wait on it), the partly-drawn interest basis (5), the prescribed RBM return form, and the first approved run from which the ECL and revenue-shift golden numbers are born.

## 10. Files

Audit evidence: this report; the bootstrap log and the PHPUnit log in the engagement file; the four review reports (conformance, engine, security, operations) in the engagement file; the Gazette at `docs/regulatory`. Corrections: commits `742e7a7`, `f324814`, `f5b08fc` on `eir_revenue_recognition` (master fast-forwarded).
