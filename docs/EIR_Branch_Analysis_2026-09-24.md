# MAICC-IFRS9 repository analysis: branch `eir_revenue_recognition`

Prepared 2026-09-24 from a read-only inspection of `c:\xampp\htdocs\MAICC-IFRS9`. Every claim below cites the file (and line where it matters) that was opened. Nothing in the repository was modified, no migration was run against any database, and the only execution was the EIR test suite, which `phpunit.xml` pins to an in-memory sqlite database.

Framework facts: Laravel 10 (`composer.json` line 25), PHP ^8.1 (local CLI is 8.2.12), `doctrine/dbal` ^3.7 (line 18), `maatwebsite/excel` ^3.1 (line 30), phpunit ^10 (line 50). Inertia + Vue 3 front end. `Schema::defaultStringLength(199)` is set in `app/Providers/AppServiceProvider.php` line 50, so every unsized `string()` column is VARCHAR(199).

---

## 1. Branch state

| Item | Value |
|---|---|
| Checked-out branch | `eir_revenue_recognition` (so files were read directly from the working tree) |
| Tracking | `origin/eir_revenue_recognition`, no ahead/behind shown by `git status -sb` (fully pushed) |
| HEAD | `a8c8926` 2026-08-20 09:53 +0200 |
| master HEAD | `13f21b6` 2026-08-07 17:53 +0200 |
| Merge base with master | `13f21b6` (master has not moved since the branch was cut) |
| Ahead of master | 27 commits; behind 0 |
| Diff vs master | 167 files changed, +13,437 / -2,133 |
| Uncommitted tracked changes | none |
| Untracked | `docs/training/` (README.md + 00-the-client.md, an IFRS 9 course for Kundai) and `storage/superseded-scratch-2026-08-04/` (6 abandoned migrations + 6 models: eir_facilities, loan_cash_flows, gl_interest_postings, eir_runs, eir_schedule_lines, eir_reconciliations, dated 4 Aug 2026) |

Other remote branches: `KundaiBranch`, `feature-sicr-tinashe`, `feature-tinashe-changes`, `feature/ifrs9-maiic-suite`, `master`.

Important context: the EIR foundation was already on `master` before this branch (migrations `2026_07_27`, `2026_08_03`, `2026_08_04`; `CalculateEirService`, `ScheduleGeneratorService`, `EirReadinessService`, `EirContractInputService`, `ScheduleImportService`, `FeeImportService`, `FeeRuleMatcher`, `StagingClassifier`, `ExtractBImportService`, the accounting-rule and fee-classification controllers and the Intake page). The branch adds Extract A/C intake, the calculation/lock workflow, the revenue roll-forward, GL reconciliation, coverage, help centre, CI, and a UI restyle.

Last 30 commits on `eir_revenue_recognition` (hash, date, message):

```
a8c8926 2026-08-20 docs(eir): spec v2.4 - correct the NAME-column claim; GL grain settled
2c49e5a 2026-08-19 docs(eir): spec v2.3 - GL spools tie to TB; interest has no customer grain
6609d4d 2026-08-19 docs(eir): spec v2.2 - trial-balance corpus, cumulative-YTD rule, AFS bridge
e497db7 2026-08-18 Add comprehensive tests for EIR services and intake processes
7e8599d 2026-08-13 Ticket #010: DB-driven manual authoring + single-source PDF with figures
8316044 2026-08-13 Ticket #007: screenshot-driven user manual, end to end
4a2714f 2026-08-12 Ticket #003: one stress engine; #004 complete (EIR pages restyled)
3edbd73 2026-08-12 Fix dead loan book filters (window.Inertia) + batch 4 staging/EIR tables
7b74be0 2026-08-12 Ticket #004 batch 3: design-system tables on the model-setup and data pages
3f2993d 2026-08-12 Loan book: BI-standard KPI tiles, stage filter, Status column replaced with ECL coverage
588b874 2026-08-12 Loan book opens on the latest period; #004 batch 2 reference-data pages
3cd8bcd 2026-08-12 Dashboard status chips + IFRS 9 loan book columns; drop Eswatini currency prefix
6595fae 2026-08-12 Ticket #004 rollout: design-system tables and icon actions on the core list pages
9629f34 2026-08-12 Merge branch 'eir_revenue_recognition' of https://github.com/DupleixInstitute/MAICC-IFRS9 into eir_revenue_recognition
97de895 2026-08-12 feat(imports): infer column type and pre-select the transform
bd13d6a 2026-08-12 feat(eir): store portfolio and product type; show three sample values
f1ed4ea 2026-08-12 feat(eir): capture stated conventions and profile the file on the mapping screen
954ce1a 2026-08-12 Remove credit-scoring era roles and permissions; fix blank labels in the Roles form
44c1c46 2026-08-12 fix(imports): detect spreadsheets by content, not by file extension
6e0b5d7 2026-08-12 fix(eir): merge duplicate contract-master rows instead of taking the first
331f1e5 2026-08-12 chore(tickets): backlog seeder honours per-item status and resolution; mark #005 delivered
abc3741 2026-08-12 fix(eir): correct Extract A mapping against the delivered file
1a18176 2026-08-12 feat(reports): branded Excel export for all 30 hub reports + PDF template branding (Ticket #005)
51180f6 2026-08-12 feat(eir): record whether a payment frequency was stated or assumed
273fb54 2026-08-12 ci: point the pipeline at master, not main
39d9026 2026-08-12 ci: GitHub Actions test/build pipeline and production deploy script
12b9fe0 2026-08-12 feat(eir): contract master (Extract A) and GL interest postings (Extract C) intake
13f21b6 2026-08-07 Fix black doughnut slice (undefined palette key) and colour-code the stage cards   <- merge base / master HEAD
6ad39f2 2026-08-07 Fix filter bar select padding so calendar icons no longer overlap the text
377b288 2026-08-07 Dashboard trend defaults, calendar icons, FontAwesome trim (bundle halved)
```

---

## 2. Data model

### 2.1 EIR migrations (in order)

**`database/migrations/2026_07_27_000000_create_eir_tables.php`** (on master). No foreign key from any EIR table to `loan_books`; `contract_id` is a plain indexed string by design (header comment lines 7-17).

| Table | Columns (type) | Keys |
|---|---|---|
| `contract_eir` | id; contract_id string; instrument_type enum(AMORTISED_LOAN, PREF_SHARE, EQUITY_EXCLUDED) default AMORTISED_LOAN; rate_type enum(FIXED, FLOATING) default FIXED; reference_rate_at_origination decimal(8,5) null; markup decimal(8,5) null; fee_spread decimal(8,5) null; origination_date date null; approved_amount decimal(20,2) null; drawn_amount decimal(20,2) null; moratorium_months unsignedTinyInteger default 0; payments_per_year unsignedTinyInteger default 12; eir_period decimal(10,7); eir_nominal_annual decimal(10,7); eir_effective_annual decimal(10,7); rate_source enum(SOLVED_EIR, CONTRACTUAL_PROXY) default CONTRACTUAL_PROXY; schedule_source enum(IMPORTED, GENERATED) null; below_market_flag bool default false; solver_iterations unsignedSmallInteger; solver_residual double; input_snapshot json; locked_at timestamp; locked_by FK users; timestamps | unique(contract_id) |
| `contract_cashflow_schedule` | id; contract_id string idx; schedule_version unsignedSmallInteger default 1; effective_from date null; due_date date; principal_due, interest_due, fee_due decimal(20,2) default 0; schedule_source enum(IMPORTED, GENERATED) default IMPORTED; timestamps | unique(contract_id, schedule_version, due_date) `uq_schedule_contract_version_date` |
| `contract_fees` | id; contract_id string idx; fee_type string(50); amount decimal(20,2) signed; basis enum(ON_APPROVED, ON_DRAWN) default ON_APPROVED; integral bool default true (later made nullable); gl_account_ref string(50); timestamps | (unique added 2026_08_03) |
| `eir_amortisation` | id; contract_id string; reporting_period string(7) YYYY-MM; opening_gross, interest_accrued decimal(20,2); interest_basis enum(GROSS, NET) default GROSS; unwind_amount, cash_received decimal(20,2); cash_source enum(DERIVED, IMPORTED) default DERIVED; modification_gain_loss, closing_gross, ecl_allowance decimal(20,2); timestamps | unique(contract_id, reporting_period) `uq_amort_contract_period`; index(reporting_period) |
| `rate_reset_events` | id; contract_id string idx; reset_date date; old_reference_rate, new_reference_rate decimal(8,5); new_schedule_version unsignedSmallInteger; recorded_by FK users; timestamps | none beyond index |
| `import_mappings` | id; import_type string(50); source_header string; target_field string; transform string(100) null; timestamps | unique(import_type, source_header) `uq_mapping_type_header` |
| `staging_thresholds` | id; facility_class string(50) default DEFAULT; min_tenor_months unsignedSmallInteger default 0; stage2_dpd, stage3_dpd unsignedSmallInteger; rebuttal_basis text; effective_from date; timestamps | none |

**`2026_08_03_000000_strengthen_eir_fee_classification.php`** (on master)

| Table | Change |
|---|---|
| `eir_accounting_rules` (new) | id; name; fee_type string(50) null; description_contains string null; gl_account_ref string(50) null; cashflow_direction enum(RECEIVED, PAID) null; proposed_integral bool; rationale text; priority unsignedSmallInteger default 100; active bool default true; created_by FK users; approved_by FK users; approved_at timestamp; timestamps; index(active, priority) |
| `contract_fees` (altered) | integral -> nullable default null (`->change()`, line 30, needs doctrine/dbal); adds description, transaction_date date, cashflow_direction enum(RECEIVED, PAID), currency string(3), source_system string(50), source_reference, external_transaction_id, classification_status enum(PENDING, CLASSIFIED, REVIEWED, REJECTED) default PENDING, classification_reason text, suggested_rule_id FK eir_accounting_rules, suggested_integral bool null, classified_by FK users, classified_at, reviewed_by FK users, reviewed_at; index(classification_status, contract_id); unique(source_system, external_transaction_id) `uq_fee_source_transaction` |
| `eir_fee_classification_events` (new) | id; contract_fee_id FK contract_fees; action enum(CLASSIFIED, REVIEWED, REJECTED, REOPENED); integral bool null; reason text; accounting_rule_id FK; performed_by FK users; timestamps |

**`2026_08_04_000000_add_extract_b_audit_fields.php`** (on master)

| Table | Change |
|---|---|
| `contract_cashflow_schedule` | adds source_system string(50), source_reference, external_transaction_id; unique(source_system, external_transaction_id) `uq_schedule_source_transaction` |
| `eir_actual_transactions` (new) | id; contract_id string idx; customer_id string null; sub_account_no string null; transaction_date date; transaction_type string(50); principal_component, interest_component, fee_component, total_amount decimal(20,2) default 0; balance_after_transaction decimal(20,2) null; source_system string(50); source_reference null; external_transaction_id string; row_note text; timestamps; unique(source_system, external_transaction_id) `uq_actual_source_transaction` |

**`2026_08_12_000000_add_contract_master_and_gl_interest.php`** (branch only)

| Table | Change |
|---|---|
| `contract_eir` | adds contractual_rate decimal(8,5) (stored as a fraction, comment lines 24-27); rate_basis string(40); tenor_months unsignedSmallInteger; first_repayment_date, maturity_date, closure_date, last_restructure_date date; currency string(3); sub_account_no string(60); gl_account_code string(60); opening_amortised_cost decimal(20,2); opening_amortised_cost_date date; terms_source_system string(40); terms_source_reference string(120); terms_imported_at timestamp |
| `gl_interest_postings` (new) | id; contract_id string(199) idx; gl_account_code string(60) null; period_type string(20) default MONTHLY; period_year unsignedSmallInteger; period_month unsignedTinyInteger; reporting_period date idx (first of month); interest_income_posted decimal(20,2) default 0; transaction_count unsignedInteger default 0; posting_references text; row_note text; generated_on date; source_system string(40); source_reference string(120); external_transaction_id string(191); timestamps; unique(contract_id, period_year, period_month, gl_account_code) `gl_interest_period_unique`; unique(source_system, external_transaction_id) `gl_interest_source_unique` |

**`2026_08_12_000001_add_frequency_source_to_contract_eir.php`** (branch): `contract_eir.frequency_source` enum(STATED, ASSUMED) default ASSUMED after payments_per_year. The header comment (lines 7-27) explains why: a blank frequency and a stated monthly one both stored 12.

**`2026_08_12_000002_add_source_conventions_to_contract_eir.php`** (branch): `source_day_count_basis` string(20), `source_compounding` string(20), `disbursement_tranches` text. The header (lines 7-26) states these are what E-Banker says, "not necessarily what the engine is directed to apply", that the delivered Extract A shows 336 facilities on 365 and 26 on 360, and that the solver "anchors the cash-flow vector on a single drawdown" so tranches are captured but not consumed.

**`2026_08_12_000003_add_portfolio_and_product_to_contract_eir.php`** (branch): `portfolio` string(60) null indexed, `product_type` string(120). Header explains 74 of 181 Extract A facilities are absent from loan_books (46 closed).

**`2026_08_13_100000_add_eir_calculation_workflow.php`** (branch): `contract_eir.calculation_status` string(20) default PENDING indexed; `solver_method` string(30); `calculation_error` text; `calculated_at` timestamp; `calculated_by` FK users.

**`2026_08_18_000000_create_eir_amortisation_history.php`** (branch): `eir_amortisation_history` = id; contract_id string(199); reporting_period string(7); the nine amortisation measures copied verbatim (opening_gross, interest_accrued, interest_basis enum, unwind_amount, cash_received, cash_source enum, modification_gain_loss, closing_gross, ecl_allowance); originally_created_at timestamp; superseded_at timestamp useCurrent; superseded_by FK users; supersession_reason string(500); timestamps; index(contract_id, reporting_period); index(superseded_at).

Consolidated final shape of `contract_eir` (59 attributes are in `$fillable` at `app/Models/ContractEir.php` lines 16-65): identity (contract_id, sub_account_no, gl_account_code, portfolio, product_type, currency), classification (instrument_type, rate_type, rate_basis, source_day_count_basis, source_compounding), pricing (reference_rate_at_origination, markup, contractual_rate, fee_spread), dates (origination_date, first_repayment_date, maturity_date, closure_date, last_restructure_date), amounts (approved_amount, drawn_amount, disbursement_tranches, opening_amortised_cost + date), profile (moratorium_months, payments_per_year, frequency_source, tenor_months), results (eir_period, eir_nominal_annual, eir_effective_annual, rate_source, schedule_source, below_market_flag), solver audit (solver_iterations, solver_residual, solver_method, input_snapshot), workflow (calculation_status, calculation_error, calculated_at/by, locked_at/by), lineage (terms_source_system, terms_source_reference, terms_imported_at).

### 2.2 Existing core tables the EIR engine touches

| Table | Migration | Relevant columns | How EIR uses it |
|---|---|---|---|
| `loan_books` | `2024_11_27_150358_create_loan_books_table.php` + later adds | contract_id, customer_id, customer_name, product_group, product_code, funding_source, reporting_year, reporting_month, reporting_period string(6) YYYYMM (changed to nullable string by `2025_01_28`), create_date, due_date, overdue_days, remaining_tenor, tenor, interest_rate decimal(8,2) **stored as a percentage**, principal_balance, approved_amount, disbursed, **repayments** decimal(65,4), carrying_amount, **commitments** decimal(16,4), facility_utilisation_rate decimal(5,2) default 1, expected_loss_provision, overdue_status, ifrs9_stage int default 0, ifrs9stage_pre_qualitative, sicr, ifrs9stage_post_qualitative, arrears_1_to_30 / 30_to_90 / 91_to_180 / 180_to_270; later: loan_portfolio_id, client_id, contract_status (`2025_01_28`), calculated_ifrs9_stage string idx (`2026_05_17_170000`), lgd_value, pd_value, ecl_value, 12m_pd, lifetime_pd, customer_lgd, collection_lgd, pd_prefli, fli_adj, pd_post_fli (`2025_06_27`, `2025_11_26`), credit_enhancement, cooperative (`2026_05_18_000004`). Unique (contract_id, reporting_period) since `2025_06_25`. | Presence gate for every import (held when absent); drawn amount fallback for schedule reconciliation; exposure (carrying_amount) for coverage; stage and `expected_loss_provision` for the revenue run; interest_rate/create_date/due_date for generated schedules |
| `contracts` | `2024_11_27_150359_create_contracts_table.php` | contract_id unique, customer_id, create_date, due_date, opening_score decimal(65,2), opening_score_period, closed_date, write_off_date, update_period | **Not used by EIR.** This is the credit-scoring era contract register (`app/Models/Contract.php`, `ContractsController`). Same word, different thing. |
| `financial_periods` | `2022_01_08` | name, start_date, end_date, closed, created_by_id, closed_by_id, branch_id | Not referenced by EIR code |
| `reporting_periods` | `2025_06_28` | reporting_year, reporting_month, period date, lgd/pd/ecl calculation stamps | Not referenced by EIR code |
| `period_workspace_tasks` | `2026_05_18_000002` | reporting_period string(10), task_key string(50), status enum(pending, done), completed_by, completed_at; unique(reporting_period, task_key) | No EIR task key found (grep) |
| `settings` | `2021_06_05` | setting_key unique, setting_value text, type, options, rules, category, order, displayed | Plain key/value; no effective dating or approval; not used by EIR |
| `imports` | `2024_11_28` + adds | status, records, rows_processed, failed_records, failed_file_path, name, started_at, completed_at | `ProcessEirImportJob` writes the lifecycle; `EirIntakeController::status` reads it |
| `general_import_templates` / `general_import_configurations` | `2024_12_16` | template_name, source_table_name, column positions and types | Not used by EIR (EIR uses `import_mappings`) despite the spec §4 saying it reuses them |
| `audit_logs` | (`app/Models/AuditLog.php`) | user_id, action, entity_type, entity_id, scope, reporting_period, rows_affected, old_values json, new_values json, meta json, ip_address, user_agent | Every EIR write action logs here via `AuditLoggerService::log` (`app/Services/AuditLoggerService.php` line 10) |
| `users` + spatie permissions | | `hasRole('admin')` used for maker-checker override | All EIR routes sit behind `permission:settings` |
| `discounted_payments` / `loss_given_default` | `2026_05_17_150000`, `2026_05_17_140000` | discount_rate_source enum(manual, loan_book), interest_rate, payment_type, discounting_days, discounted_amount | Door 3: `CalculateDiscountingJob` resolves rates through `EclDiscountRateService`, which reads locked `contract_eir` rows |

---

## 3. Models and relationships (`app/Models`)

| Model | Table | Relationships / helpers |
|---|---|---|
| `ContractEir` | contract_eir | `schedules()` hasMany ContractCashflowSchedule on contract_id; `fees()` hasMany ContractFee; `amortisation()` hasMany EirAmortisation; `rateResets()` hasMany RateResetEvent; `lockedBy()`, `calculatedBy()` belongsTo User; `currentScheduleVersion()` (max schedule_version or 1, line 130); `isInEirScope()` (not EQUITY_EXCLUDED, line 135); constants RATE_SOURCE_SOLVED / RATE_SOURCE_PROXY |
| `ContractCashflowSchedule` | contract_cashflow_schedule | `contractEir()` belongsTo; `scopeForVersion()`; `totalDue()` |
| `ContractFee` | contract_fees | `contractEir()`; `suggestedRule()` belongsTo EirAccountingRule; `scopeIntegral()` = integral true AND classification_status REVIEWED (line 54) |
| `EirAccountingRule` | eir_accounting_rules | `creator()`, `approver()` belongsTo User |
| `EirFeeClassificationEvent` | eir_fee_classification_events | none (append-only log) |
| `EirAmortisation` | eir_amortisation | `contractEir()`; `scopeForPeriod()`; `suspendedInterest()` (scales NET interest to gross basis, lines 50-65) |
| `EirAmortisationHistory` | eir_amortisation_history | `contractEir()`; `supersededBy()` belongsTo User |
| `GlInterestPosting` | gl_interest_postings | `contractEir()` belongsTo on contract_id |
| `RateResetEvent` | rate_reset_events | `contractEir()`; `recordedBy()`. **No code writes or reads this model** other than the relationship (grep across app/). |
| `ImportMapping` | import_mappings | `scopeForType()`; static `templateFor($type)` returns [source_header => target_field] |
| `StagingThreshold` | staging_thresholds | static `forFacility($class, $tenorMonths)` picks the governing row with `effective_from <= now()` (lines 36-46) |
| `Contract` | contracts | credit-scoring register; `loanBooks()`, `client()`. Unrelated to EIR. |
| `DiscountedPayment` | discounted_payments | `lossGivenDefault()`; static `calculateDiscountedAmount()` uses `pow(1+rate, days/365)` with days capped at 3650 (lines 74-87) |

There is **no Eloquent model for `eir_actual_transactions`**; every reader and writer uses `DB::table('eir_actual_transactions')` (`ContractTransactionImportService` lines 110-112, `EirRevenueService` lines 161, 173).

---

## 4. Services

All paths under `app/Services/Eir/` unless stated. Line numbers are from the working tree.

### 4.1 `CalculateEirService.php` (97 lines) - pure periodic IRR solver
- `calculate(float $initialNetInvestment, array $cashFlows, int $paymentsPerYear, float $guess = 0.02): array` (line 17). Solves the per-period rate r such that -initial + sum(amount_i / (1+r)^period_i) = 0 by Newton-Raphson (`newton()` line 60, max 100 iterations) and, if that fails, bisection (`bisection()` line 77, bracket doubled up to 1024, max 250 iterations). Returns eir_period, eir_nominal_annual = r x ppy (line 36), eir_effective_annual = (1+r)^ppy - 1 (line 37), iterations, residual, method, input_snapshot.
- Hardcoded: RATE_FLOOR = -0.999999 (line 11); default guess 0.02 (line 17); convergence tolerance max(1, initial) x 1e-9 (line 30) and 1e-10 inside the solvers (lines 65, 85); ppy must be one of 1, 2, 4, 6, 12 (line 48); receipts must be >= 0 and periods > 0 (line 53), so a second drawdown cannot be passed as a negative flow; periods are **integers/counts**, not dates (line 94), i.e. no day-count or XIRR behaviour.

### 4.2 `ScheduleGeneratorService.php` (120 lines) - Tier-2 annuity schedule
- `generate(array $terms): array` (line 41). Builds a level-annuity repayment schedule from principal, annual_rate, payments_per_year, n_payments, start_date, moratorium_months.
- Hardcoded: ALLOWED_FREQUENCIES [1,2,4,6,12] (line 24); **moratorium = capital plus interest holiday with interest capitalising monthly at annual_rate/12** (`pow(1 + annualRate/12, moratoriumMonths)`, line 68) - there is no principal-only (interest-serviced) grace mode; period rate = annual/ppy (line 73); interval months = intdiv(12, ppy) (line 76); level instalment formula (line 118); 2-dp rounding, last instalment absorbs drift (lines 84-88).

### 4.3 `EirReadinessService.php` (56 lines)
- `assess(string $contractId): array` (line 11) returns READY/BLOCKED with issue codes: CONTRACT_PROFILE_MISSING, EQUITY_EXCLUDED, EIR_LOCKED, ORIGINATION_DATE_MISSING, DRAWN_AMOUNT_MISSING, FREQUENCY_INVALID, FREQUENCY_ASSUMED (line 25, branch-only addition), ORIGINAL_SCHEDULE_MISSING, SCHEDULE_INVALID, SCHEDULE_DATE_INVALID, SCHEDULE_DATE_DUPLICATE, PRINCIPAL_NOT_RECONCILED, FEE_CLASSIFICATION_PENDING, FEE_DIRECTION_MISSING, INITIAL_NET_INVALID.
- Hardcoded: reads schedule_version 1 only (line 27); principal-vs-drawn tolerance max(1.0, drawn x 0.01) (line 35); initial net = drawn - received + paid (line 45).

### 4.4 `EirContractInputService.php` (113 lines)
- `assemble(string $contractId): array` (line 28). Converts a READY contract into solver input: schedule version 1 in due_date order (lines 36-41), **period index = row ordinal** (line 49, so the gap between origination and first instalment, or an uneven gap, is not represented), only REVIEWED + integral fees (lines 58-62), initial net = drawn - received + paid (line 80), snapshot with metadata.

### 4.5 `EirCalculationService.php` (121 lines) - orchestration and lock
- `calculate(string $contractId, ?int $userId = null): array` (line 19): assemble, solve, persist inside a transaction with `lockForUpdate`, sets rate_source SOLVED_EIR, calculation_status CALCULATED; on any Throwable writes BLOCKED + calculation_error (lines 63-70).
- `lock(string $contractId, int $reviewerId, bool $allowMakerCheckerOverride = false): ContractEir` (line 73): requires CALCULATED, a known maker, and reviewer != maker unless override (lines 78-86); sets LOCKED/locked_at/locked_by.
- `lockMany(iterable $contractIds, int $reviewerId, bool $override = false): array` (line 106).

### 4.6 `EirRevenueService.php` (249 lines) - monthly amortised-cost roll-forward
- `run(string $contractId, string $period, bool $recalculate = false, ?int $userId = null, ?string $reason = null): array` (line 31). For a locked contract and a YYYY-MM period: opening = prior period closing or `initialOpening()`; reads the loan-book snapshot for the period; stage from `calculated_ifrs9_stage`, then `ifrs9stage_post_qualitative`, then `ifrs9_stage` (line 136); allowance = `expected_loss_provision` (line 63); **monthly rate = (1 + eir_effective_annual)^(1/12) - 1 (line 64)**; basis NET when stage 3 (line 65); interest = monthly rate x (net or gross opening) (line 67); unwind = monthly rate x min(allowance, opening) when NET (line 68); cash from `cashReceived()`; closing = max(0, opening + interest + unwind - cash) (line 70); **modification_gain_loss written as 0 (line 81)**. Recalculation requires a reason (line 34) and supersedes this and every later period into history (`supersede()` line 212).
- `initialOpening()` (line 93): initial_net_investment from the snapshot else opening_amortised_cost; if the first run is after origination, PV of remaining snapshot cash flows at eir_effective_annual using `yearFraction()` with `source_day_count_basis` defaulting to 'ACT/365' (line 109).
- `yearFraction()` (line 116): 30/360 or 30E/360 (lines 119-122) else days/365 (line 124). This is the only place a day count appears in the revenue engine.
- `cashReceived()` (line 156): if `eir_actual_transactions` cover the month, sum `total_amount` for COLLECTION_TYPES ['Interest', 'Principal+Interest', 'Fee'] (line 22), never ADVANCE_TYPES ['Disbursement'] (line 25), other types reported as unclassified; otherwise `scheduledCash()` (line 189) sums schedule version 1 rows due in the month and labels DERIVED.
- `loanSnapshot()` (line 127) matches `REPLACE(SUBSTR(reporting_period,1,7),'-','')` against YYYYMM.
- Not implemented here: rate resets, restructures/modification, cure catch-up, days-in-month accrual, capitalisation of moratorium interest into the balance during a grace period.

### 4.7 `EirGlReconciliationService.php` (192 lines)
- `availablePeriods(): array` (line 42); `forPeriod(?string $period = null, ?string $portfolio = null): array` (line 54) returns rows, bridge, summary for one period: per posting, variance = EIR accrued - GL posted; base effect = contractual monthly x (opening - drawn) (line 118); rate effect = (effective monthly - contractual monthly) x opening (line 119); unexplained = remainder. Postings with no amortisation row are NO_CONTRACT / NOT_CALCULATED and excluded from the bridge (lines 96-105).
- Hardcoded: TOLERANCE_PERCENT 1.0 (line 36), TOLERANCE_FLOOR 1.0 (line 39); contractual monthly = contractual_rate / 12 (line 111); effective monthly = (1+EIR)^(1/12) - 1 (line 113). The base-effect model assumes the GL accrues flat contractual interest on the original drawn amount, not on the prior month-end balance x days/365.

### 4.8 `EirCoverageService.php` (298 lines)
- `profile(?string $period = null, ?string $portfolio = null): array` (line 58): portfolio-wide readiness in four bulk queries, states LOCKED / CALCULATED / READY / BLOCKED / OUT_OF_SCOPE, blockers weighted by carrying amount, sole-blocker counts, per-portfolio coverage. `availablePeriods()` (line 288).
- Deliberately **duplicates** the readiness checks (`assess()` line 98, mirrors `EirReadinessService`); a test asserts the two agree. Tolerance 1% repeated at line 128.

### 4.9 `ContractMasterImportService.php` (491 lines) - Extract A
- `import(array $rows): array` (line 68). Merges duplicate rows per facility (`mergeDuplicateRows()` line 196: blank never overrides, conflicting values reject the facility), holds accounts absent from loan_books (line 87), rejects customer mismatches (line 95), inserts or updates only changed TERM_FIELDS (line 44), never rewrites a locked contract (line 131), routes arrangement_fee / legal_fees to PENDING `contract_fees` via `FeeImportService` (`feeRows()` line 364). Reports created / updated / unchanged / held / skipped / incomplete / unknown_frequencies.
- Hardcoded: SOURCE 'MAIIC_EXTRACT_A' (line 41); `rate()` treats any value > 1 as a percentage (line 471); `rateType()` maps FIXED, FLOATING or VARIABLE (line 478); frequency_source set to STATED only when the file resolved a frequency (line 290); durations parsed by `ContractMasterImport::monthsFromDuration` ("2y 0m 0d", "3 M"); numeric compare tolerance 0.005 (line 347).

### 4.10 `ContractTransactionImportService.php` (117 lines) - Extract B
- `import(array $rows): array` (line 28). Dedups by GL_POSTING_REF or a composed key; holds unknown loans; rejects customer conflicts; `SCHEDULED` rows go to `ScheduleImportService` (version 1), `ACTUAL` rows to `eir_actual_transactions` (`insertActuals()` line 106), non-zero fee_component to PENDING fees as type 'other' (lines 87-92). SOURCE 'MAIIC_EXTRACT_B' (line 21).

### 4.11 `GlInterestImportService.php` (206 lines) - Extract C
- `import(array $rows): array` (line 39). Natural key contract|year|month|gl_account; file-internal duplicates skipped; unknown loans held; identical re-delivery = unchanged; different figure = restatement applied and named (lines 119-132); signs stored as delivered, negatives counted (line 87). SOURCE 'MAIIC_EXTRACT_C' (line 28); year range 1900-2999 (line 174).

### 4.12 `ScheduleImportService.php` (160 lines)
- `import(array $rows, float $principalTolerance = self::PRINCIPAL_TOLERANCE): array` (line 34): groups by contract, refuses if any schedule already exists (line 51-57, "use the restructure (v2) or audit-logged correction flow", which does not exist), holds contracts not on the tape, rejects missing/duplicate dates, requires sum(principal_due) within 1% of `loan_books.disbursed` (else principal_balance) (lines 84-96), inserts as version 1 IMPORTED, upserts a `contract_eir` stub with schedule_source IMPORTED (line 118).
- `coverage(): array` (line 142): contracts with any schedule vs contracts on the latest tape.
- Hardcoded: PRINCIPAL_TOLERANCE 0.01 (line 24); schedule_version always 1 (line 102).

### 4.13 `FeeImportService.php` (119 lines)
- `import(array $rows, ?FeeRuleMatcher $matcher = null): array` (line 30): normalises contract_id, skips zero amounts, maps unknown types to 'other' and reports them, dedups on (source_system, external_transaction_id), stores integral = null and classification_status PENDING with a rule suggestion only (lines 91-94). KNOWN_TYPES arrangement, legal, appraisal, default, levy, other (line 20); basis defaults ON_APPROVED (line 90).

### 4.14 `FeeRuleMatcher.php` (25 lines): `match(array $fee): ?EirAccountingRule` (line 9) - first active, approved rule (by priority) whose fee_type / gl_account_ref / cashflow_direction / description_contains all match.

### 4.15 `StagingClassifier.php` (121 lines): `classify(array $row, ?string $facilityClass = null, int $tenorMonths = 0): string` (line 45) and `daysPastDue(array $row): int` (line 65). DPD = lower bound of highest non-empty arrears bucket (BUCKETS line 25). Hardcoded fallback ladder stage 2 at 31 DPD, stage 3 at 181 DPD (lines 34-35) when `staging_thresholds` is empty or absent.

### 4.16 `app/Services/Imports/MappedFileReader.php` (677 lines)
- `analyze(string $path, string $importType): array` (line 107): headers, 5-row preview, per-column profile (3 most frequent values, distinct, blanks, inferred type, suggested transform), saved-template matches, missing required fields.
- `read(string $path, string $importType, ?array $mapping = null, array $transforms = []): array` (line 292): applies mapping + transforms; `contract_id` always canonicalised through `ContractId::normalise` (line 319).
- `cleanNumber()` (line 344) and `normalizeHeader()` (line 373) are public statics.
- Transforms (`applyTransform()` line 384): text, number / strip_commas, contract_id, percent (> 1 divided by 100, line 401), date, date:FORMAT (declared format is a hint; falls back to `Carbon::parse`, lines 420-455). Excel serials between 20000 and 80000 are dates (line 427).
- REQUIRED_FIELDS / OPTIONAL_FIELDS per import type (lines 55-100); `detectImportType()` (line 649) auto-detects only Extract B by its headers; `templateFor()` (line 633) = class aliases overlaid by saved `import_mappings`; file type sniffed by magic bytes (line 487).

### 4.17 `app/Support/ContractId.php` (78 lines): `normalise($value): ?string` (line 34) strips E-Banker zero padding, spreadsheet debris and scientific notation; `matches($a, $b): bool` (line 66).

### 4.18 `app/Services/Ecl/EclDiscountRateService.php` (162 lines) - Door 3
- `resolve(array $pairs, string $requestedSource, ?float $manualRate = null): array` (line 46): sources manual / eir / loan_book. `fromLockedEir()` (line 83) uses the locked eir_effective_annual and labels FLOATING facilities EIR_ORIGINAL_FLOATING_PROXY (line 107) because no reset history exists. `fromLoanBook()` (line 122) divides the tape percentage by 100 (line 147). No default rate; unresolved pairs are returned by name. Consumed by `app/Jobs/CalculateDiscountingJob.php` (line 35) and `LossGiveDefaultController` line 355; the old `?? 0.10` fallback survives only as a commented line (`CalculateDiscountingJob.php` line 319).

### 4.19 `app/Services/AuditLoggerService.php`: `log(string $action, string $entityType, ?int $entityId = null, array $data = []): void` (line 10) writes `audit_logs` with user, ip, user agent, old/new values, meta.

---

## 5. Controllers, routes, UI pages, console commands, jobs

### 5.1 Routes (`routes/web.php` lines 914-946)

| Route name | Method / path | Controller@action |
|---|---|---|
| eir-data.index | GET /eir-data | EirDataController@index |
| eir-reconciliation.index | GET /eir-reconciliation | EirReconciliationController@index |
| eir-coverage.index | GET /eir-coverage | EirCoverageController@index |
| eir-intake.index / status / analyze / save-template / import | GET /eir-intake, GET /eir-intake/imports/{import}/status, POST analyze, POST save-template, POST import | EirIntakeController |
| eir-accounting-rules.index / store / update / approve / toggle | GET, POST, PUT /{rule}, POST /{rule}/approve, POST /{rule}/toggle | EirAccountingRuleController |
| eir-fee-classification.index / classify / review | GET, POST classify, POST review | EirFeeClassificationController |
| eir-calculations.index / calculate / approve-all / approve | GET, POST calculate, POST approve-all, POST /{contractEir}/approve | EirCalculationController |

Every EIR controller constructor applies `middleware(['auth', 'permission:settings'])` (e.g. `EirCalculationController.php` line 17, `EirIntakeController.php` lines 42-43). No dedicated EIR permissions exist (`MaiicAdminPermissionsSeeder` has no eir entries).

### 5.2 Controllers (`app/Http/Controllers/`)

| Controller | What it does |
|---|---|
| `EirIntakeController` (203 lines) | `index` renders Eir/Intake with coverage, saved templates and the field spec; `analyze` returns JSON profile of an uploaded file (max 20 MB, csv/txt/xlsx/xls/ods, line 67); `saveTemplate` upserts `import_mappings`; `import` validates the mapping by reading the file, stores it under `temp-imports/`, creates an `imports` row named "EIR {type}: file" and dispatches `ProcessEirImportJob` (line 184); `status` returns job progress + the audit-log result + exception file URL |
| `EirCalculationController` (167 lines) | `index` lists contract_eir with status filter and search, approval summary (own / missing maker / eligible); `calculate` queues `CalculateEirJob` for up to 1000 ids (line 77); `approve` locks one; `approveAll` locks every CALCULATED unlocked contract via `lockMany`; admin role bypasses maker-checker (`adminOverride()` line 163) |
| `EirDataController` (118 lines) | Tabs contracts / cashflows / gl. The gl tab and `reconciliationSummary()` re-implement the 1% tolerance in SQL (lines 75-79, 101, 115) with MySQL-only `CONCAT`/`LPAD` (lines 68, 91) |
| `EirCoverageController` (57 lines) | Period/portfolio/issue filters, drill-down capped at 50 rows (DRILLDOWN_LIMIT line 13) |
| `EirReconciliationController` (39 lines) | Renders the reconciliation for a period/portfolio. **No download action.** |
| `EirAccountingRuleController` (70 lines) | CRUD + approve + toggle; editing resets approval (line 37) |
| `EirFeeClassificationController` (94 lines) | `classify` (maker) and `review` (checker, cannot review own unless admin, line 78); events appended |
| `ContractsController` (383 lines) | Legacy credit-scoring contract register; not part of EIR |

### 5.3 UI pages (`resources/js/Pages/Eir/`)

| Page | User sees |
|---|---|
| `Intake.vue` (626 lines) | Step 1 upload (import type select: Contract master (Extract A), Repayment schedule, Fees and transaction costs, Contract transactions (Extract B), GL interest postings (Extract C)), auto-detect banner, Extract B note ("Partial or truncated schedules are rejected"); Step 2 map columns (File column -> Maps to -> Transform: none/text, number, percent, date (auto), date dd/mm/yyyy, date mm/dd/yyyy), save template; Step 3 result tiles per type (contracts loaded, held, rejected, restatements, negative rows, totals by fee type), exception CSV download, status polling |
| `Calculations.vue` (234 lines) | Status filter, contract search, "Calculate selected", per-row Periodic EIR / Effective annual EIR / Evidence (solver method + iterations) / "Approve & lock", bulk approval panel with counts of own / missing-maker contracts |
| `Data.vue` (222 lines) | Summary tiles; tabs Contracts (portfolio/product, amounts, source coverage, EIR status), Cash Flows (version, due date, principal, interest, total, source), GL Postings (GL interest, EIR interest, EIR - GL, variance %, status); button "EIR Data Intake" (line 13) is the **only navigation link to the intake page** |
| `Reconciliation.vue` (208 lines) | Period and portfolio selectors, bridge tiles (GL posted, base effect, rate effect, unexplained), per-facility table with status |
| `Coverage.vue` (251 lines) | Coverage by count and by exposure, blockers ranked by exposure with sole-blocker counts, by-portfolio table, contract drill-down |
| `FeeClassification.vue` (27 lines, single-line template) | Work queue with filters, Classify modal (Integral / Non-integral, rule, reason), Review/approve modal |
| `AccountingRules.vue` (28 lines) | Rule register, create/edit form (type, description contains, GL account, cash direction, proposed treatment, rationale, priority, active), Approve |

Sidebar (`config/menu.php` lines 89-96) group "EIR & Revenue Recognition": Accounting Rules, EIR Data, Fee Classification, EIR Calculations, GL Reconciliation, Coverage & Blockers. The intake page is **not** in the sidebar. Help centre articles for EIR exist in `database/seeders/HelpContentSeeder.php` lines 143-157 (Accounting Rules, Schedule Intake, Fee Classification only). Screenshots `public/manual/screenshots/eir-fees.jpg`, `eir-intake.jpg`, `eir-rules.jpg`.

Screens that do **not** exist: running the revenue roll-forward (artisan only), viewing `eir_amortisation` rows per contract, recording a rate reset, loading a restructure/version 2 schedule, creating or editing a single contract profile, any governance/settings page for EIR conventions, any Excel/PDF export of EIR data.

### 5.4 Console commands (`app/Console/Commands/`)

| Signature | Class | Purpose |
|---|---|---|
| `eir:generate-schedules {--period=} {--frequency=12} {--dry-run}` | `GenerateContractSchedules` (208 lines) | Tier-2 GENERATED annuity schedules for tape contracts with no schedule; uses stated frequency from contract_eir else the option (lines 71-88); tape interest_rate > 1 treated as percent (line 168); moratorium_months forced to 0 (line 205) |
| `eir:run-revenue {period} {--contract=*} {--queue} {--recalculate} {--reason=}` | `RunEirRevenue` (56 lines) | Runs `RunEirRevenueJob` for YYYY-MM, synchronously unless --queue; --recalculate needs --reason |
| `eir:make-test-data {--out=} {--periods=10}` | `MakeEirTestData` (466 lines) | Synthetic 20-contract fixture set (Extract A/B/C + loan books); Extract A header list at lines 275-281 documents the delivered 36-column shape; GL interest posted as balance x rate / 12 (line 355) |

`app/Console/Kernel.php` schedules no EIR command (lines 25-30 list campaign and reminder commands only).

### 5.5 Jobs (`app/Jobs/`)

| Job | Behaviour |
|---|---|
| `ProcessEirImportJob` (131 lines) | Reads the stored file through `MappedFileReader`, dispatches to the five import services by type (lines 52-58), writes `failed_imports/eir_exception_{id}.csv` on the public disk with held / skipped / incomplete / restatements rows (line 117), updates `imports`, audit-logs the full result; timeout 600 s |
| `CalculateEirJob` (31 lines) | Calls `EirCalculationService::calculate` per contract; blocked contracts do not stop the batch |
| `RunEirRevenueJob` (88 lines) | For a period, runs every locked contract (or a list); tallies CREATED / RECALCULATED / UNCHANGED / BLOCKED, superseded rows, DERIVED cash rows, unclassified cash; audit-logs (blocked list capped at 200, line 21) |
| `CalculateDiscountingJob` (340 lines) | ECL discounting using `EclDiscountRateService` |

Queue: `.env.example` sets `QUEUE_CONNECTION=database` (needs a worker in production); the local `.env` is `sync`.

---

## 6. Import formats, mapping and validation

Import types and required / optional target fields (`MappedFileReader.php` lines 55-100):

| Type | Required | Optional |
|---|---|---|
| `contract_master` (Extract A) | contract_id | run_id, customer_id, portfolio, sub_account_no, gl_account_code, currency, product_type, origination_date, first_repayment_date, maturity_date, closure_date, last_restructure_date, approved_amount, drawn_amount, contractual_rate, rate_basis, rate_type, reference_rate_at_origination, source_day_count_basis, source_compounding, disbursement_tranches, markup, repayment_frequency, payments_per_year, tenor_months, moratorium_months, arrangement_fee, legal_fees, opening_amortised_cost, opening_amortised_cost_date |
| `schedule` | contract_id, due_date, principal_due, interest_due | fee_due |
| `fees` | contract_id, fee_type, amount | description, transaction_date, cashflow_direction, currency, source_system, source_reference, external_transaction_id, basis, gl_account_ref |
| `contract_transactions` (Extract B) | customer_id, contract_id, sub_account_no, transaction_date, transaction_type, principal_component, interest_component, total_amount, scheduled_actual_flag, gl_posting_ref | run_id, fee_component, balance_after_transaction, row_note |
| `gl_interest` (Extract C) | contract_id, period_year, period_month, interest_income_posted | run_id, gl_account_code, period_type, reporting_period, transaction_count, posting_references, row_note, generated_on |

Header aliases (source header -> target field), applied before any saved template:
- `app/Imports/ContractMasterImport.php` lines 22-115: RUN_ID, CUSTOMER_ID / CLIENT_ID, LOAN_ACCOUNT_NUMBER / ACCOUNT_NUMBER / CONTRACT_ID, SUB_ACCOUNT_NO, GL_ACCOUNT_CODE, CURRENCY, PRODUCT_TYPE, PORTFOLIO / FUNDING_SOURCE, LOAN_START_DATE / DISBURSEMENT_DATE / VALUE_DATE -> origination_date, FIRST_REPAYMENT_DATE, MATURITY_DATE / CONTRACTUAL_MATURITY_DATE / EXPIRY_DATE, CLOSURE_DATE / ACTUAL_CLOSURE_DATE, RESTRUCTURE_DATE -> last_restructure_date, SANCTIONED_AMOUNT / APPROVED_AMOUNT, PRINCIPAL_DISBURSED / DISBURSED_AMOUNT, INTEREST_RATE / CONTRACTUAL_RATE, **RATE_BASIS -> rate_type** (the delivered column holds Fixed/Variable, comment lines 68-71), INTEREST_BASIS / ACCRUAL_BASIS -> rate_basis, DAY_COUNT_BASIS / DAYCOUNT, COMPOUNDING, DISBURSEMENT_TRANCHES, REFERENCE_RATE, MARKUP / MARGIN, REPAYMENT_FREQUENCY / PAYMENT_FREQUENCY, PAYMENTS_PER_YEAR, TENOR_MONTHS / TENOR, GRACE_PERIOD_MONTHS / GRACE PERIOD / MORATORIUM_MONTHS / **PRINCIPAL_GRACE_PERIOD -> moratorium_months** (INTEREST_GRACE_PERIOD deliberately unmapped, lines 97-102), ARRANGEMENT_FEE, LEGAL_FEES / LEGAL_FEE, OPENING_AMORTISED_COST, OPENING_BALANCE_DATE. Frequency words (line 164): MONTHLY/M -> 12, QUARTERLY/Q -> 4, SEMI-ANNUAL/HALF-YEARLY/BI-ANNUAL -> 2, ANNUAL/YEARLY/Y -> 1, WEEKLY -> 52, FORTNIGHTLY -> 26 (52 and 26 are then rejected by readiness, which allows only 1, 2, 4, 6, 12).
- `app/Imports/ContractTransactionImport.php` lines 16-53: RUN_ID, CUSTOMER_ID, LOAN_ACCOUNT_NUMBER, SUB_ACCOUNT_NO, GL_POSTING_REF / POSTING_REFERENCE, TRANSACTION_DATE / VALUE_DATE, TRANSACTION_TYPE, PRINCIPAL_COMPONENT, INTEREST_COMPONENT, FEE_COMPONENT, TOTAL_AMOUNT, SCHEDULED_ACTUAL_FLAG, BALANCE_AFTER_TRANSACTION, ROW_NOTE / NOTES.
- `app/Imports/GlInterestImport.php` lines 22-52: RUN_ID, LOAN_ACCOUNT_NUMBER, GL_ACCOUNT_CODE / GL_ACCOUNT, PERIOD_TYPE, PERIOD_YEAR / YEAR, PERIOD_MONTH / MONTH, INTEREST_INCOME_POSTED / INTEREST_INCOME, TRANSACTION_COUNT, POSTING_REFERENCES, ROW_NOTE, GENERATED_ON.

Mapping persistence: `import_mappings` (import_type, source_header, target_field, transform) written by `EirIntakeController::saveTemplate`, read by `ImportMapping::templateFor`.

Validation, by layer:
1. Reader: unmapped required fields block the read with names (`MappedFileReader.php` line 303); unmapped headers reported; delimiter sniffed; BOM and NBSP cleaned; non-UTF-8 converted from Windows-1252.
2. Services: contract must exist in `loan_books` (held otherwise) in every EIR import; customer mismatch rejects (A, B); schedule principal within 1% of drawn; duplicate due dates reject; existing schedule never overwritten; fee dedup on source ids; GL natural-key dedup + restatement naming; Extract A duplicate-row merge with conflict rejection; unknown frequency reported not guessed.
3. Outcome: `imports` row + exception CSV + audit log; Intake page polls `/eir-intake/imports/{id}/status`.

Data realities recorded in the spec (docs/MAIIC_EIR_Revenue_Recognition_Engine_Spec.md §3.2): the delivered Extract A (36 columns, 362 rows / 181 accounts) carries no fee columns at all; 22% of Extract B rows carry a ROW_NOTE saying the principal/interest split is estimated; Extract C delivered was 28 rows for 3 accounts; DR_CR_INDICATOR absent.

Loan book (the E-Banker monthly Loan Book Report) is imported separately by `app/Imports/LoanBooksImport.php` (legacy and default header sets, arrears buckets mapped at lines 133-138). **The `repayments` column on `loan_books` is never written by that importer** (grep of app/ finds it only in `MakeEirTestData`, `LoanBook::$fillable`, and two report readers), so the running-total Repayments figure the planned engine wants to derive arrears from is not currently captured.

---

## 7. Tests

EIR test files:
- `tests/Unit/Eir/CalculateEirServiceTest.php` (4 tests: ACADES golden EIR, zero yield, invalid frequency, duplicate period)
- `tests/Unit/Eir/MappedFileReaderTest.php` (23 tests)
- `tests/Unit/Eir/ScheduleGeneratorServiceTest.php` (7 tests: ACADES quarterly instalment, BERL moratorium capitalises interest, EcoGen 3-month moratorium, invariants, zero rate, month-end dates, invalid terms)
- `tests/Unit/Support/ContractIdTest.php` (8 tests)
- `tests/Feature/Eir/EirCalculationWorkflowTest.php` (5), `EirCoverageServiceTest.php` (7), `EirGlReconciliationServiceTest.php` (6), `EirIntakeServicesTest.php` (31), `EirIntakeStatusTest.php` (2), `EirReadinessServiceTest.php` (7), `EirRevenueServiceTest.php` (12), `StagingClassifierTest.php` (5)
- `tests/Feature/Ecl/EclDiscountRateServiceTest.php` (8)

`phpunit.xml` forces `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `QUEUE_CONNECTION=sync` with a comment warning that the override was once disabled and a RefreshDatabase test wiped the dev database. `vendor/` is installed. CI (`.github/workflows/ci.yml` line 54) runs `php artisan test tests/Unit/Eir tests/Unit/Support tests/Feature/Eir`.

Command run (from `c:/xampp/htdocs/MAICC-IFRS9`):

```
php artisan test tests/Unit/Eir tests/Unit/Support tests/Feature/Eir tests/Feature/Ecl/EclDiscountRateServiceTest.php
```

Result (verbatim tail):

```
  Tests:    124 passed (512 assertions)
  Duration: 13.68s
```

Preceded by `WARN Your XML configuration validates against a deprecated schema. Migrate your XML configuration using "--migrate-configuration"!`. All 12 suites reported PASS; a re-run counted 124 tick marks. The spec's statement that the suite "has not been independently run against this clone" is therefore now superseded: it runs and passes on PHP 8.2.12 / sqlite. The wider application suite (the spec mentions about 116 pre-existing failing scaffold tests) was not run.

What the tests do not cover: `EirDataController` (MySQL-only SQL), any controller HTTP flow other than intake status, `GenerateContractSchedules`, `RunEirRevenueJob`/`RunEirRevenue` end to end, rate resets, restructures, exports.

---

## 8. Documentation already in the repo

| File | What it is |
|---|---|
| `docs/EIR_Build.md` (228 lines) | The plan: three doors, Phase 0 decisions (conventions memo, ECL time conventions, IAS 32 classifications, staging rebuttal, scope letter), schema, phases 0-7, the monthly operating cycle (§5), contract realities (§6), golden numbers, limitations register, acceptance bar |
| `docs/Development_of_EIR.md` (304 lines) | Build log. Phases 1, 2, 2.5-2.8 and 3.1 marked complete; **Phases 4, 5, 6, 7 still say "NOT STARTED"** although the branch built 4, 5 and part of 6 (the spec itself says this log is stale) |
| `docs/EIR_Existing_vs_Proposed_Build.md` (256 lines, 2026-08-07) | Maps the OneDrive v1 proposal (eir_facilities, loan_cash_flows, eir_runs, eir_schedule_lines) onto the existing model; §9 "items that must not be duplicated"; §10 decisions and blockers; §11 recommended order |
| `docs/MAIIC_EIR_Revenue_Recognition_Engine_Spec.md` (606 lines, v2.4, 2026-08-20) | Authoritative consolidated spec: data paths A-D (offer letters, Extracts A/B/C, 19-month trial-balance corpus, GL spools), architecture, solver, doors, reconciliation, UI, monthly cycle, disclosure notes, §12 gap table, §13 open items 1-26, Appendix A golden numbers (ACADES quarterly 8.6217%, nominal 34.49%, effective 39.21%; TB control totals), Appendix B contract realities, Appendix C limitations |
| `docs/MAIIC_EIR_Revenue_Recognition_Engine_Spec_Explained.md` (140 lines, v2.0) | Plain-language companion |
| `EIR_CALCULATION_ANALYSIS.md` (839 lines, repo root) | Narrative walk-through of the solver, revenue and workflow as built |
| `EIR_FEES_RULES_DETAILED_ANALYSIS.md` (607 lines, repo root) | Which EIR feeds ECL, how fee classification and rules flow |
| `scripts/generate_eir_explainer_documents.py` | Generator for explainer documents |
| `docs/training/` (untracked) | IFRS 9 course for Kundai; module 00 profiles MAIIC (mentions 10% loans, 13-month moratoria, about 96 accounts) |
| `database/seeders/HelpContentSeeder.php` lines 143-157 | In-app help for Accounting Rules, Schedule Intake, Fee Classification |
| `README.md` | No EIR mention |

Key spec positions worth carrying into the new specification: the GL cannot attribute interest or fees to a customer (E-Banker posts one aggregate journal per month per GL code), so per-loan interest must come from the loan module (open item #24, critical path); MAIIC misposts arrangement fees into GL 4871 Legal Fees (item #26); Extract C at full coverage is re-requested; the trial-balance corpus (Path C) ingestion is "the highest-value unblocked build item" and is not started.

---

## 9. Gap analysis against the planned engine

Legend: EXISTS = usable as built; PARTIAL = schema or partial logic present but the capability is not delivered end to end; MISSING = nothing in code.

| Capability | Status | Evidence |
|---|---|---|
| Fixed and floating (PLR-linked) loans | PARTIAL | `contract_eir.rate_type` FIXED/FLOATING (migration 2026_07_27 line 27) and `reference_rate_at_origination`, `markup`, `fee_spread` columns. Import maps RATE_BASIS Fixed/Variable to rate_type. Nothing processes a floating loan differently: solver, revenue and reconciliation all use the locked `eir_effective_annual`; `EclDiscountRateService.php` line 107 labels floating as a proxy for that reason |
| Moratorium type ("Principle Only" vs "Both") + grace months | PARTIAL, type MISSING | Only `moratorium_months` (unsignedTinyInteger). No moratorium_type column. `ContractMasterImport.php` line 103 maps PRINCIPAL_GRACE_PERIOD into it and leaves INTEREST_GRACE_PERIOD unmapped (lines 97-102). `ScheduleGeneratorService.php` lines 64-69 treat any moratorium as capital-plus-interest with monthly capitalisation at annual/12 (the "Both" shape), so a principal-only grace (interest serviced monthly) cannot be generated. The solver ignores moratorium entirely (it discounts whatever schedule rows exist). `GenerateContractSchedules.php` line 205 forces moratorium_months = 0 |
| Reference rate (PLR) series table with 26 changes since 2020 | MISSING | No table or model holds a dated prime-rate series (grep for prime / PLR / reference_rate finds only the per-contract `reference_rate_at_origination` column and the unused `rate_reset_events` table) |
| Scheme / interest policy field | PARTIAL | `instrument_type`, `rate_type`, free-text `rate_basis` string(40), `source_compounding`, `source_day_count_basis` (stated, not applied). No enumerated scheme or interest policy (simple vs compound, capitalise vs service) that the engine reads |
| Per-loan margin over prime | PARTIAL | `markup` decimal(8,5) captured from MARKUP/MARGIN (`ContractMasterImport.php` lines 85-86, `ContractMasterImportService.php` line 284). No consumer computes contractual rate = prime + margin; `contractual_rate` is imported as a flat figure |
| Tranche / partial disbursement | PARTIAL (captured only) | `disbursement_tranches` text (migration 2026_08_12_000002 line 34, comment lines 19-25 says the solver anchors on a single drawdown). `CalculateEirService::validate` line 53 rejects negative amounts, so later drawdowns cannot enter the cash-flow vector; `EirContractInputService` line 80 uses one `drawn_amount` |
| Arrears derived from the monthly Loan Book Report (running-total Repayments) | PARTIAL | `loan_books` has arrears buckets and `overdue_days`; `StagingClassifier` derives DPD from buckets. `loan_books.repayments` exists (migration 2024_11_27 line 39) but `LoanBooksImport.php` never populates it; no code computes expected-vs-received arrears from schedule minus cumulative repayments. Revenue cash comes from Extract B actuals or the schedule (`EirRevenueService.php` lines 156-195), not the loan book |
| Restructure lineage (parent/child contract, modification at original EIR, 10% test) | MISSING | `schedule_version` and the unique key exist (2026_07_27 line 72) and `ContractEir::currentScheduleVersion()`, but no writer ever creates version 2 (`ScheduleImportService.php` line 102, `GenerateContractSchedules.php` line 108 both hardcode 1) and `ScheduleImportService` line 55 refers to a "restructure (v2) flow" that does not exist. Readers use version 1 only (`EirContractInputService.php` line 38, `EirRevenueService.php` line 192). `modification_gain_loss` is written as 0 (`EirRevenueService.php` line 81). No parent/child contract link, no 10% test (grep for "10%" / derecogni finds only ECL report labels). `last_restructure_date` is stored only |
| Rate reset events (26 PLR changes) | MISSING | `rate_reset_events` table + `RateResetEvent` model exist but nothing writes or reads them (grep across app/). No re-estimation of cash flows at reset (IFRS 9 B5.4.5); revenue accrues at the locked EIR for every period (`EirRevenueService.php` line 64) |
| IRR solver (vs Excel RATE) | EXISTS | `CalculateEirService.php`: Newton-Raphson with bisection fallback, residual and iteration audit, ACADES golden test. Limit: integer period index (`EirContractInputService.php` line 49), so irregular timing (grace gap, uneven instalment dates) is not respected; not date-based (no XIRR) |
| Interest convention: prior month-end balance x annual rate x days in month / 365, with monthly capitalisation of moratorium interest | MISSING (different convention implemented) | Revenue accrues (1+EIR)^(1/12) - 1 on the opening balance per calendar month with no day count (`EirRevenueService.php` lines 64-67); the only ACT/365 or 30/360 logic is the one-off PV in `initialOpening()` (lines 109-124). GL bridge assumes contractual/12 on original drawn (`EirGlReconciliationService.php` lines 111, 118). Schedule generator capitalises at annual/12 during a Both-type moratorium only (`ScheduleGeneratorService.php` line 68). `source_day_count_basis` is stored per contract (336 on 365, 26 on 360 per the migration note) but described as stated-not-applied |
| GL reconciliation to interest posted per account | EXISTS (per contract x period) | `EirGlReconciliationService`, `EirReconciliationController`, `Reconciliation.vue`, Extract C intake with restatement handling. Gaps: tolerance 1% hardcoded (line 36); no export; no trial-balance / control-total tie-out (spec Phase 2.8 not started); no proposed journals; duplicate SQL implementation in `EirDataController` |
| Governance Centre (effective-dated, approver-gated, audit-logged settings) | MISSING as a facility | No settings model for EIR conventions; every convention is a code constant (day count, 1/12, tolerances, COLLECTION_TYPES, frequency list). Precedents that could be generalised: `staging_thresholds.effective_from` (dated, no approver, `StagingThreshold::forFacility` line 41); `eir_accounting_rules` approved_by/approved_at with maker-checker (no effective dating); `settings` key/value table (no dating, no approval); `AuditLoggerService` for the audit trail. Spec §14 explicitly says no maker-checker workflow exists for approving a run's final output |
| Single-contract manual entry | MISSING | No create/edit route or form for `contract_eir`; profiles arrive only through imports or the schedule stub |
| Bulk CSV/Excel import | EXISTS | Five import types, mapping templates, transforms, queued job, exception CSV, status polling (section 6) |
| Month-end-only run orchestration | PARTIAL | `RunEirRevenueJob` + `eir:run-revenue` run a whole period for all locked contracts; recalculation with reason and cascade supersession. Not scheduled (`Kernel.php`), no UI trigger, no period lock or close, no dependency-ordered pipeline (intake -> quality gate -> solve -> stage -> ECL -> revenue -> reconcile -> lock as in `EIR_Build.md` §5), no `period_workspace_tasks` entry for EIR. `RunEirRevenue.php` line 31 calls `auth()->id()` from the console (null user) |
| Reports / exports | PARTIAL | Screens exist (Data, Calculations, Reconciliation, Coverage). No Excel/PDF action on any EIR page (grep for download in Eir controllers: none) although the IFRS 9 hub has `app/Exports/Ifrs9ReportExport.php`; only the import exception CSV downloads. No Table 2 style revenue report by stage; no `eir_amortisation` viewer |
| Undrawn commitments | MISSING in EIR | `loan_books.commitments` and `facility_utilisation_rate` exist (2024_11_27 lines 41, 45); no EIR service, controller or job references commitments or undrawn (grep empty). `approved_amount` vs `drawn_amount` and fee `basis` ON_APPROVED/ON_DRAWN are stored but the solver only uses amounts (`EirContractInputService.php` lines 77-80) |
| Fees integral to the EIR netted off proceeds | EXISTS | Maker/checker classification, rule suggestions, `initialNet = drawn - received + paid` (`EirContractInputService.php` line 80). Limit: fees are treated as at origination regardless of `transaction_date` |
| Monthly amortised-cost roll-forward with history | EXISTS (with the convention caveat above) | `eir_amortisation` + `eir_amortisation_history`, Stage 3 net basis and unwind, DERIVED vs IMPORTED cash provenance |
| Door 3 (ECL discounted at EIR) | EXISTS | `EclDiscountRateService` wired into `CalculateDiscountingJob` and `LossGiveDefaultController` line 355; the 10% fallback is gone. Stage-1 PD pro-rating and collateral discounting at EIR not done (spec §6) |
| Maker-checker on EIR lock and fee classification | EXISTS | `EirCalculationService::lock` lines 78-86; `EirFeeClassificationController::review` line 78; admin override in both |

---

## 10. Risks and surprises

1. **Two readiness implementations and two reconciliation implementations.** `EirCoverageService::assess` (line 98) duplicates `EirReadinessService::assess` on purpose (comment lines 18-22) and `EirDataController` re-implements the reconciliation tolerance in raw SQL (lines 75-79, 101, 115) separately from `EirGlReconciliationService`. Any convention change (tolerance, stage source) must be made in three places.
2. **MySQL-only SQL in `EirDataController`** (`CONCAT`/`LPAD`, lines 68 and 91) is not exercised by the sqlite test suite, so that page is untested and would fail on any other driver.
3. **Dead schema:** `rate_reset_events` / `RateResetEvent`, `fee_spread`, `below_market_flag`, `basis` ON_APPROVED/ON_DRAWN, `disbursement_tranches`, `source_compounding`, `tenor_months`, `opening_amortised_cost` (only a fallback in `initialOpening`) are stored and never (or barely) consumed. `eir_actual_transactions` has no model.
4. **Moratorium semantics conflict.** The import alias treats `moratorium_months` as the principal grace (`ContractMasterImport.php` line 103) while the generator treats it as a full capital-plus-interest holiday with capitalisation (`ScheduleGeneratorService.php` lines 64-69). A principal-only grace facility would get an incorrect generated schedule.
5. **Solver timing model.** Cash flows are discounted by ordinal period, not by date. A 6-month moratorium followed by monthly instalments, imported as a schedule of instalments only, would be solved as if the first instalment fell one period after drawdown, overstating the EIR. Same for quarterly facilities with an irregular first period.
6. **Frequency list mismatch.** The Extract A mapper resolves WEEKLY -> 52 and FORTNIGHTLY -> 26 (`ContractMasterImport.php` lines 171-172), which the readiness gate, coverage and solver then reject (only 1, 2, 4, 6, 12).
7. **Stage and allowance source ambiguity.** `EirRevenueService::stage` (line 134) tries three loan-book columns in order; `ifrs9_stage` defaults to 0 so a tape without calculated stages blocks every contract. Allowance is read from `expected_loss_provision` (line 63) while the ECL module also writes `ecl_value` (migration 2025_06_27); which column is live for a given period is not asserted anywhere.
8. **`reporting_period` on `loan_books` is a free string** (originally string(6) YYYYMM, changed to nullable string in 2025_01_28). EIR code relies on `SUBSTR(reporting_period,1,7)` and a `REPLACE` trick (`EirRevenueService.php` line 130, `EirCoverageService.php` line 282, `ScheduleImportService.php` line 145) that only works if every row uses one of the two formats consistently.
9. **`ScheduleImportService` refuses a second load and points to a non-existent flow** (line 55). There is no way to correct an imported schedule except direct database work.
10. **Access control is a single broad permission** (`permission:settings`) on every EIR route; spec open item #12 flags this for auditor accounts.
11. **Untracked scratch schema in `storage/superseded-scratch-2026-08-04/`** (eir_facilities, eir_runs, eir_reconciliations with proposed_journal fields) is the abandoned v1 design; it is not loaded by Laravel but could confuse a reader or a future `git add -A`.
12. **`docs/Development_of_EIR.md` is stale** (Phases 4-7 "NOT STARTED"); the spec v2.4 §12 table is the accurate status.
13. **Migrations.** All EIR migrations ran on sqlite during the tests; on MySQL the only notable dependency is `->change()` in `2026_08_03` (doctrine/dbal is installed). Composite unique indexes stay under the InnoDB 3072-byte limit with the 199-char default. No migration is expected to fail, but `2026_08_04` was flagged in the spec (open item #14) as "apply before using Extract B intake", so verify `migrations` table state on the target database.
14. **Queue configuration.** Production `.env.example` uses the database queue; `CalculateEirJob`, `ProcessEirImportJob` and the queued revenue run need a running worker or they will sit pending.
15. **`RunEirRevenue` console command passes `auth()->id()`** (line 31), which is null from the CLI, so console runs and recalculations are recorded with no user; the history rows then have `superseded_by = null`.
16. **GL reconciliation bridge assumes the ledger accrues contractual/12 on the original drawn amount** (`EirGlReconciliationService.php` lines 111-119). If E-Banker actually accrues on the prior month-end balance x days/365 (the convention the planned engine states), most of the variance will land in "unexplained" rather than in the two named effects.
17. **`repayments` on `loan_books` is never populated** by the loan book importer (see section 6), which blocks the planned "arrears from running-total Repayments" derivation until the importer is extended.
18. **Fee timing ignored.** Integral fees are netted at t = 0 regardless of `transaction_date`; fees charged later in the life (or on tranches) would be mis-timed.
19. **Test count vs method count.** 124 tests passed while a grep of `test_` method names found 125; the difference is one method that phpunit does not treat as a test. Not material, noted for completeness.
