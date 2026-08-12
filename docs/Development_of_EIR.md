# Development of EIR

**The build log for the EIR module.** Companion to [EIR_Build.md](EIR_Build.md) (the plan); this file records what was actually built, when, why, and how each piece is used. One section per phase, appended as work lands. Keep it current — this document is part of the audit pack (Deloitte's first question is "show me what you built and why").

---

## Phase 1 — Schema (2026-07-27) ✅ COMPLETE

**What shipped:** one migration + seven Eloquent models. No existing table altered, no existing calculation touched — Phase 1 is empty scaffolding that Phases 2–6 fill and consume. Migrated and verified against the dev database (all seven tables created; `migrate:rollback` removes them cleanly).

### Files

| File | Purpose |
|---|---|
| `database/migrations/2026_07_27_000000_create_eir_tables.php` | Creates all seven tables |
| `app/Models/ContractEir.php` | The solved EIR per contract |
| `app/Models/ContractCashflowSchedule.php` | The contractual promise, versioned |
| `app/Models/ContractFee.php` | Itemised signed fee lines |
| `app/Models/EirAmortisation.php` | Monthly roll-forward (MAIIC's "Table 2") |
| `app/Models/RateResetEvent.php` | Floating-rate reset log |
| `app/Models/ImportMapping.php` | Dynamic column-mapping templates |
| `app/Models/StagingThreshold.php` | DPD staging rules as configurable data |

### The tables and how they are used

**`contract_eir` — one row per contract; the answer.**
Keyed by `contract_id`, *not* a column on `loan_books`: the loan book is a monthly snapshot (new rows every month) while the EIR is a permanent property of the deal, locked at origination. Monthly rows JOIN to it. Holds: what the instrument is (`instrument_type` — the filter that stops the engine computing an EIR for equity rows; `rate_type` — FinES fixed vs "reference + markup" floating), the origination facts (dates, amounts, moratorium, payment frequency), the answer three ways (`eir_period` solved IRR, `eir_nominal_annual` = ×n, `eir_effective_annual` = compounded — always labelled, never conflated: 39.47% nominal ≠ 47.46% effective), and the audit trail (`rate_source`, `input_snapshot` JSON of the exact cash-flow vector solved, solver residual/iterations, `locked_at/by`).
*Consumed by:* Phase 4 ECL discounting (JOIN for the rate), Phase 5 revenue accrual, Phase 6 coverage report (exposure by `rate_source` — Dr Thom's materiality bridge), Deloitte reperformance (re-solve from `input_snapshot`, match).

**`contract_cashflow_schedule` — the contractual promise.**
One row per instalment (due date, principal, interest, fee). Loaded **once per contract**, not monthly. `schedule_version` is append-only: a restructure adds version 2; version 1 is never overwritten because modification accounting (IFRS 9 §5.4.3) discounts the *new* schedule at the *old* EIR and books the difference.
*Consumed by:* the Phase 3 solver (its primary input — no schedule, no EIR), Phase 5 opening balances (PV of remaining schedule), Phase 6 reconciliation (PV vs tape `carrying_amount`).

**`contract_fees` — the fee lines, itemised and signed.**
Amount is signed (real contracts have netting lines — NyamNyam's legal cost −1.99m). `basis` records fees-on-approved vs interest-on-drawn (the partially-drawn edge case behind the assessment's biggest gaps). `integral` records the B5.4.1 judgement — inside the EIR or period income. `gl_account_ref` supports the GL tie-out (the control that made Dr Thom's assessment credible: MK171.1m + MK311.3m reconciled to the ledger).
*Consumed by:* the solver's t=0 anchor (`drawn − integral fees`), the Phase 6 GL reconciliation.

**`eir_amortisation` — the monthly roll-forward.**
One row per contract per month: opening gross → interest accrued (`interest_basis` GROSS for Stages 1–2, NET for Stage 3, with `unwind_amount` split out) → cash received (`cash_source` DERIVED until the Phase 7 actuals import) → `modification_gain_loss` → closing gross. This table **is** MAIIC's Table 2, generated; its closing column is the constructed amortised cost (Door 1). The model exposes `suspendedInterest()` for the Stage-3 gross-vs-net disclosure.
*Consumed by:* the interest-income report by stage, the carrying-amount reconciliation, next month's opening.

**`rate_reset_events` — the floating-rate log.**
Commercial contracts are "reference + markup, subject to variation at MAIIC's option" (B5.4.5). Each variation is one row: old rate, new rate, which schedule version was regenerated, who recorded it. Without this an EIR change on a floating loan is unexplainable to an auditor.

**`import_mappings` — the dynamic column mapper.**
Per import type, saved mappings of *client* file headers → *our* fields with transforms (date formats, percent vs decimal, separators). Upload any shape Ebanker can produce, map once, template reused forever. Replaces the legacy hardcoded `case` mapper and exact-header rejections. `ImportMapping::templateFor($type)` returns the ready mapping array.

**`staging_thresholds` — the DPD waterfall as data.**
The rules currently hardcoded in `LoanBooksImport::classifyIFRS9Stage()` become rows: facility class, tenor floor, Stage-2/Stage-3 DPD, and — critically — `rebuttal_basis`, storing the RBM-directive justification *with* the rule (a rebuttal of the 30-DPD presumption, B5.5.37, is only legitimate with documented evidence — 2025 review Finding 1). `StagingThreshold::forFacility()` resolves the governing row (most specific class, latest effective date, DEFAULT fallback).

### Design decisions recorded

1. **No FK from the EIR tables to `loan_books`.** `contract_id` is a plain indexed string. Schedule files and loan tapes arrive in either order; a hard constraint would fail whichever import lands first. Integrity is enforced softly: Phase 2 import validation + Phase 6 orphan-check report.
2. **EIR never lives on the snapshot table.** One fact, one row, joined everywhere — prevents a bad monthly import from silently rewriting a locked rate's history.
3. **Everything the solver consumed is stored (`input_snapshot`).** The audit bar is "reperform from stored data and match" — not "trust the run".
4. **Enums encode the fallback hierarchies** (`rate_source`, `schedule_source`, `cash_source`) so every number carries its own provenance into reports.
5. **`down()` drops in reverse dependency order**; migration is fully reversible.

### Verification

- `php -l` clean on all 8 files
- `php artisan migrate --path=...` → DONE (1,518ms)
- Schema check: all 7 tables present in dev DB

---

## Phase 2 — Intake layer ✅ COMPLETE (2026-07-27)

Four components, build order: **generator → mapping engine → schedule/fee imports → staging config** (generator first because it is pure logic, unit-testable against the ten sample contracts with zero client data).

### 2.1 Tier-2 schedule generator (2026-07-27) ✅ COMPLETE

**Files:**
- `app/Services/Eir/ScheduleGeneratorService.php` — pure annuity engine, no DB
- `app/Console/Commands/GenerateContractSchedules.php` — `php artisan eir:generate-schedules {--period=} {--frequency=12} {--dry-run}`
- `tests/Unit/Eir/ScheduleGeneratorServiceTest.php` — 7 tests / 45 assertions, all passing

**What the service does:** given terms (principal, nominal annual rate, payments/year ∈ {1,2,4,6,12}, instalment count, start date, moratorium months) it produces a dated schedule of principal/interest rows. Level-annuity instalment `P·r/(1−(1+r)^−n)`; straight-line when rate = 0; capital+interest moratorium capitalises interest monthly at `annual_rate/12` onto the balance (BERL/Anchor pattern); final instalment closes out rounding drift so the balance amortises to exactly zero; month-end dates clamp (Jan 31 → Feb 28) rather than overflow.

**Conventions used (provisional, pending the Phase 0 conventions memo — documented in the class docblock):** period rate = nominal annual / payments-per-year; monthly capitalisation during moratoria.

**What the command does:** for the chosen period (default latest), finds every loan-book contract with **no** schedule rows, derives terms from the tape (`disbursed` → `principal_balance` → `carrying_amount`; `interest_rate` percent-normalised; term = months create_date → due_date), refuses EQUITY_EXCLUDED instruments, and writes version-1 rows flagged `GENERATED` plus a `contract_eir` stub with `schedule_source = GENERATED`. Contracts missing rate/dates/amount are skipped **and named** — they stay PROXY tier; no fabricated schedules. Prints the coverage strip (contracts with schedules / total) — the audit-pack number. Imported schedules always win: any contract with existing rows is never touched.

**Test fixtures (from the sample offer letters):**
| Test | Proves |
|---|---|
| ACADES: 8 quarterly instalments reproduce MK17,099,839.71 (±rate-rounding) from the implied 30.166% nominal | Annuity math + quarterly frequency against a real contract |
| BERL: 6-month moratorium capitalises to `P·(1+10%/12)⁶`, 48 rows retire it exactly | Moratorium capitalisation |
| EcoGen: 3-month holiday, first due 2025-05-29 | Holiday + date arithmetic |
| Invariants: interest = rate × opening balance every row; balance → 0.00 | Amortisation correctness |
| Zero-rate → straight-line; month-end clamping; invalid-terms rejection | Edge cases |

**Verified end-to-end:** smoke test on the dev DB (2 synthetic loans seeded → command generated 24 + 48 correct rows, principal sums exact to the kwacha, `contract_eir` stubs created → all smoke data removed; DB left at zero rows).

**Note for the conventions memo (open item #1):** the ACADES offer letter's instalment implies 30.166% nominal against a quoted "32.1% daily basis payable quarterly" — the generator reproduces the instalment from the implied rate; the accrual-convention question stays open for Dr Thom.

### 2.2 Dynamic mapping engine — backend (2026-07-27) ✅ SERVICE COMPLETE · UI PENDING

**Files:**
- `app/Services/Imports/MappedFileReader.php` — the mapping reader
- `tests/Unit/Eir/MappedFileReaderTest.php` — 9 tests / 21 assertions, all passing

**What the service does:**
- `analyze($path, $importType)` — for the mapping UI: detected headers (normalised, BOM-stripped), 5-row preview, saved-template matches via `ImportMapping::templateFor()`, unmapped headers, missing required fields, and the required/optional field lists per type
- `read($path, $importType, ?$mapping, $transforms)` — full read returning rows keyed by *target* fields; explicit mapping array for the UI flow, saved template for the recurring flow
- Formats: CSV (delimiter auto-detected: `, ; tab |`) and XLSX/XLS/ODS via PhpSpreadsheet (already in vendor)
- Transforms per column: `date` / `date:d/m/Y` (plus Excel serial dates), `number` (shared cleaning: thousands separators, NBSP/zero-width garbage, `-` placeholders, accounting negatives `(500)` → −500 — promoted from `LoanBooksImport::cleanNumber` and extended), `percent` (32.10 → 0.3210), `text`
- Required fields per type declared in `MappedFileReader::REQUIRED_FIELDS` (schedule: contract_id, due_date, principal_due, interest_due; fees: contract_id, fee_type, amount)

**Rules enforced (tested):** unmapped **required** fields block the read with a named list; unmapped extra columns are ignored but returned in the report (never silent); blank lines and BOM headers produce no ghost rows; signed fee amounts survive (NyamNyam netting-line fixture).

### 2.2b Mapping + intake UI (2026-07-27) ✅ COMPLETE

**Files:**
- `app/Http/Controllers/EirIntakeController.php` — thin controller: `index` (page + coverage + templates), `analyze` (JSON: headers/preview/template matches), `saveTemplate`, `import` (audit-logged via `AuditLoggerService`)
- `resources/js/Pages/Eir/Intake.vue` — three-step page: upload → map columns (side-by-side with sample values, required-field chips, transform pickers, save-template toggle) → result (loaded/held/rejected cards, named per-contract reasons, fee totals-by-type for the GL eyeball, coverage strip)
- Routes: `eir-intake.{index,analyze,save-template,import}` (verified in `route:list`); menu leaf "EIR Schedule Intake" under Customer & Loan Data
- Frontend compiled clean (`vite build` → `Intake-*.js/css`). Route names resolve at runtime via the `@routes` Blade directive (`window.Ziggy`) — the stale generated `resources/js/ziggy.js` is unused legacy.

### 2.3 Schedule + fee import services (2026-07-27) ✅ COMPLETE

**Files:**
- `app/Services/Eir/ScheduleImportService.php` — per-contract (never per-file) validation: contract on tape or **held** for retry; no missing/duplicate due dates; **Σ principal reconciles to drawn within 1%** (catches truncated exports); version-1 schedules never overwritten (restructure/correction flows are separate); writes rows + `contract_eir` stub (`schedule_source = IMPORTED`); returns the coverage strip
- `app/Services/Eir/FeeImportService.php` — signed amounts (netting lines counted + reported), unknown fee types → `other` + named report, totals by type for the GL tie, chunked inserts
- `tests/Feature/Eir/EirIntakeServicesTest.php` — 9 tests / 30 assertions on in-memory sqlite (EclGoldenNumberTest isolation pattern), incl. "one bad contract does not sink the file" and the NyamNyam signed-fee fixture

### 2.4 Staging config (2026-07-27) ✅ COMPLETE

**Files:**
- `app/Services/Eir/StagingClassifier.php` — bucket → DPD lower bound → configured thresholds; **triple fallback proven**: seeded DEFAULT rule, empty table, and no table at all each reproduce the legacy ladder exactly
- `database/seeders/StagingThresholdSeeder.php` — DEFAULT (31/181 = current behaviour, active) + LONG_TERM (91/181, min tenor 36m, full RBM rebuttal text, **future-dated 2099 = inactive until Dr Thom signs** open item #5); seeded on dev
- `StagingThreshold::forFacility()` now ignores future-dated rules
- `LoanBooksImport::classifyIFRS9Stage()` delegates to the classifier — same public signature, zero behaviour change (proven by test)
- `tests/Feature/Eir/StagingClassifierTest.php` — 5 tests / 34 assertions incl. legacy-ladder equivalence, future-dated inactivity, and the activated 90-day rebuttal keeping a long-tenor 31–90 DPD facility in Stage 1

### Phase 2 incident log — phpunit.xml database hazard (2026-07-27)

Running the **full** test suite revealed that phpunit.xml's sqlite override was **commented out**, so scaffold tests using `RefreshDatabase` (e.g. the leftover `VitalsTest`) ran `migrate:fresh` against the `.env` MySQL database and wiped it. Impact was minimal (the dev DB held only the 2 seeded threshold rows; full schema re-created by the migration run; thresholds re-seeded) — but the hazard was real. **Fix:** the `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` overrides in phpunit.xml are now active with a warning comment. All EIR tests build their own isolated schema and are unaffected. Note for the team: the ~116 failing scaffold tests (Vitals etc.) pre-date this work and reference routes/factories that don't exist in this app; candidates for deletion.

**Phase 2 totals: 30 tests / 130 assertions across generator, reader, import services, classifier — all passing.**

---

## Phase 2.5 — EIR accounting rules and fee/cost classification (2026-08-03) ✅ COMPLETE

Fee intake no longer assumes that every imported line is integral to EIR. `contract_fees.integral` is nullable and imports enter `PENDING`; approved accounting rules can suggest treatment but cannot approve it. Two existing-design Inertia pages provide the controlled workflow:

- **EIR Accounting Rules** — matching by fee type, description, GL and cash direction; proposed treatment, rationale, priority, approval and status.
- **EIR Fee & Cost Classification** — filtering, rule suggestions, bulk classification with a required reason, and independent maker/checker review.

`contract_fees` now retains descriptions, transaction dates, direction (`RECEIVED`/`PAID`), currency, source identifiers and classification/review timestamps. `eir_fee_classification_events` is the append-only decision history. Only explicitly integral and `REVIEWED` records are exposed by `ContractFee::integral()` to the future solver.

Focused intake/reader verification: 17 tests, 55 assertions passing; production frontend build passes.

## Phase 2.6 — MAIIC Extract B routing (2026-08-04) ✅ CODE COMPLETE · MIGRATION PENDING

The existing EIR intake now has a dedicated **Extract B (scheduled + actual)** type. `app/Imports/ExtractBImport.php` owns the default header aliases, following the established import-class convention; mappings saved from the intake screen override those defaults. It reuses loans already loaded into `loan_books`: `LOAN_ACCOUNT_NUMBER` matches `contract_id`, and `CUSTOMER_ID` is an independent ownership check. Unknown accounts are held; customer conflicts are rejected.

EIR uploads follow the application's established import route: the controller validates the mapping, stores the upload, creates an `imports` record in `pending`, and dispatches `ProcessEirImportJob`. The worker moves the record through `processing` to `completed`/`failed`, records processed/success/exception counts, deletes its temporary upload, and writes held/rejected contracts to the standard downloadable `failed_imports` CSV location. Users follow progress in the existing Import History page.

Rows are separated before persistence: `Scheduled` rows go through the existing full-schedule validation into `contract_cashflow_schedule`; `Actual` rows are retained in `eir_actual_transactions`; and non-zero actual `FEE_COMPONENT` values create `PENDING` `contract_fees` records for maker/checker classification. Source run/posting references are retained and protected against duplicate import. A partial 2025 schedule that does not reconcile to drawn principal is rejected rather than misrepresented as an original lifetime schedule.

Verification: focused mapping/intake suite passes (20 tests / 68 assertions) and the production frontend build passes. Migration `2026_08_04_000000_add_extract_b_audit_fields.php` could not be applied locally on 2026-08-04 because MySQL was not running; **applied 2026-08-12**.

## Phase 2.65 — Canonical contract identifier and mixed-type dates (2026-08-12) ✅ COMPLETE

Profiling the delivered extracts against the loan tape exposed the reason every Extract A/B/C row was being held with "loan account is not present in the imported loan book": E-Banker (VGCBS) pads account numbers to 15 characters with leading zeros, and MAIIC's monthly loan tape carries the same account unpadded. No account on the 3,609-row tape begins with a zero, so the unpadded form is unambiguously canonical. `App\Support\ContractId` owns that normalisation and `MappedFileReader` applies it to `contract_id` regardless of the transform the operator selected — a mis-mapped identifier holds the whole file, so it is not left to the mapping screen.

The same profiling showed the extracts are mixed-type in their date columns: Extract B carries `Actual` rows as `dd-mm-yyyy` strings and `Scheduled` rows as Excel serials, and Extract A mixes serials, `dd-mm-yyyy` and `yyyy-mm-dd` within one column. A declared format is therefore a hint: it is trusted only when it round-trips, and otherwise falls back to flexible parsing, because a silent null drops the row at validation with a misleading reason.

## Phase 2.7 — Contract master (Extract A) and GL interest postings (Extract C) (2026-08-12) ✅ COMPLETE

**Vocabulary.** The intake types are now named for what they contain rather than for the vendor's delivery letter, with the letter retained in the operator's label for provenance: `contract_master` (Extract A), `schedule`, `fees`, `contract_transactions` (Extract B, previously `extract_b`), `gl_interest` (Extract C). `ExtractBImport`/`ExtractBImportService` were renamed to `ContractTransactionImport`/`ContractTransactionImportService`. The old dropdown offered "Repayment schedule" and "Schedule Import (Extract B)" side by side, which are different things; no `import_type` was persisted anywhere and no saved mapping templates existed, so the rename cost nothing. **`source_system` values were deliberately not renamed** — `MAIIC_EXTRACT_B` and its siblings are stored lineage naming the vendor file an auditor will ask for.

**Extract A → `contract_eir`.** A facility master, not a monthly snapshot: one row per contract, upserted on every delivery. Only origination attributes unreachable through a stable `loan_books`/client join were added (contractual rate and basis, tenor, first-repayment/maturity/closure/restructure dates, currency, sub-account, GL account, opening amortised cost) plus `terms_source_system`/`terms_source_reference`/`terms_imported_at` lineage. Three refusals define the service:

- **a locked contract is never rewritten.** Once an EIR is solved and locked, its origination terms are the audited basis of that rate; a re-delivered file that disagrees is skipped with the differing fields named, rather than silently invalidating the rate and its `input_snapshot`;
- **`instrument_type` is never set from the file.** Amortised loan vs preference share vs excluded equity is an IAS 32/IFRS 9 judgement (the Nascomex memo), not a product code;
- **an unrecognised repayment frequency is recorded as an assumption, never passed off as a fact.** `contract_eir.payments_per_year` keeps its `DEFAULT 12` — monthly is right for most of the book and a sensible starting point for a hand-created contract — but a new `frequency_source` column (`STATED` | `ASSUMED`, following the existing `rate_source`/`schedule_source` convention) records which it was. The contract is still created, and the gap is also named in a new `incomplete` result bucket that reaches the downloadable exception file.

**Why the frequency needed its own provenance column.** A facility whose Extract A row said "Monthly" and one whose frequency column was blank both stored `12`, and both passed `EirReadinessService`'s `in_array(..., [1,2,4,6,12])` check. The loud failure is annualisation: ACADES at 8.6217% per quarter publishes as 34.49% nominal / 39.21% effective on `×4`, but as roughly 103% / 170% on `×12` — absurd enough that someone catches it. The quiet failure is schedule generation, and it is the dangerous one: a quarterly facility with no imported schedule receives a Tier-2 schedule built at `annual/12` on monthly intervals, so the schedule and the frequency are internally consistent and the solver returns a perfectly plausible EIR that is not the one the contract implies. Nothing in the resulting numbers looks wrong.

Three changes close it: readiness blocks `FREQUENCY_ASSUMED` by name even though the stored value is a legal frequency; `eir:generate-schedules` now takes each contract's own frequency where a source stated one, falling back to the run-wide `--frequency` option and reporting how many contracts needed it; and the contract master import sets `STATED` only when the file actually said so, so a sparse re-delivery cannot demote a frequency an earlier richer file established. The limit is worth stating: this separates stated from assumed **going forward**. `contract_eir` was empty when the column was added so there is nothing to backfill, but the column records provenance rather than recovering it — a frequency that arrives without a source still needs confirming against the offer letter.

Origination fees on the master row (arrangement, legal) route to `contract_fees` as `PENDING` through the existing `FeeImportService`, so maker/checker classification still gates solver readiness. Cash direction is left unset: the extract sign convention is an open item and a guess would flow into the solver's net initial investment. Sparse re-deliveries cannot blank a term an earlier richer file supplied — absent and zero stay distinguishable throughout.

**Extract C → `gl_interest_postings`** (new table, one row per loan per period). Records what the ledger posted, never what should have been posted: signs are stored as delivered and the negative-row count and per-period totals are surfaced at import, because flipping a sign to "look right" would move a real misstatement into the noise. An identical figure for a period already loaded is a duplicate delivery; a different figure is a GL restatement — applied, but named individually in the exception file, since the period may already have been reconciled and reviewed. Two unique keys: the natural key (contract, year, month, GL account) against double counting, and (source system, external id) against re-import under a corrected natural key.

`ProcessEirImportJob` now writes `incomplete` and `restatements` to the exception CSV alongside `held`/`skipped`, but counts only `held`/`skipped` as `failed_records` — a notice about a row that loaded should not read as a failed import.

Verification: focused EIR suite passes (68 tests / 275 assertions, up from 54/203) and migration `2026_08_12_000000_add_contract_master_and_gl_interest.php` applied locally.

## Phase 2.8 — Delivered Extract A/C profiled against the reader (2026-08-12) ✅ COMPLETE

Both delivered files were run through the real `MappedFileReader`. Extract A has **36 columns over 362 facilities**; Extract C has 14. Every required field resolves for both types and no file-level read error occurs, but the profiling closed two open items and found four mapping defects.

**Open item 10 — answered: Extract A does not carry origination fees.** There is no arrangement-fee or legal-fee column among the 36. The fee routing built into `ContractMasterImportService` is correct but will never fire from this file; origination fees continue to come from the Dec-2025 assessment workbook through the existing `fees` intake. **Extract A alone can therefore never make a contract solver-ready** — fee classification still gates readiness and Extract A supplies nothing to classify.

**Open item 9 — confirmed: Extract C carries no fee or commission GL lines.** Its 14 columns hold `interest_income_posted` only. The Phase 6 reconciliation can tie EIR interest to GL interest; it has no GL counterpart for fee amortisation. That limitation must be stated in the audit pack rather than presented as an unexplained difference.

**Directly relevant to open item 1 (conventions memo):** `day_count_basis` is **not uniform** — 336 facilities on 365 and 26 on 360. The book is not on one convention, so a single signed-off day count is a decision to override the source rather than to record it. `compounding` is uniformly `Compound`. Neither has a column in `contract_eir` yet; recording what the source states, separately from what the memo directs the engine to use, is a decision for that memo.

**`rate_basis` holds Fixed/Variable — 204 variable against 158 fixed.** The majority of the book is floating, which makes the unbuilt floating-rate reset behaviour (`rate_reset_events`) materially more significant than "schema exists" suggests. The column was initially aliased to the free-text `rate_basis` field, which would have left `rate_type` on its `FIXED` default for all 204.

**25 of 362 rows carry a blank `repayment_frequency`** (Monthly 320, Half-Yearly 10, Quarterly 4, Yearly 3). Seven per cent of the book would have silently inherited the monthly default — the case `frequency_source` was added for, now observed rather than hypothesised.

**`opening_balance` is absent or assumed for 151 of 362 rows**, per the delivered `balance_data_note`: 92 "Assumed zero — closed before as-of date", 48 "N/A — loan opened after 01-Jan-2025", 11 "DATA GAP — needs manual verification". It is deliberately **not** aliased to `opening_amortised_cost`: a balance is not an amortised cost, the two differ by exactly the unamortised fee spread this engine exists to compute, and 42% of the column is unusable regardless.

**Mapping defects found and fixed:** `tenor` arrives as `2y 0m 0d` / `0y 60m 0d` and grace periods as `3 M` / `0 Y`, none of which parse numerically — a plain cast read a 24-month tenor as `2`. `ContractMasterImport::monthsFromDuration()` now parses by unit. `contractual_maturity_date` and `actual_closure_date` did not match the guessed `MATURITY_DATE`/`CLOSURE_DATE` aliases. The file carries principal and interest grace **separately**; only principal grace is aliased, because a full moratorium is a different cash-flow shape and an accounting judgement.

Also noted, not yet acted on: `account_status` uses undocumented codes `H` (42) and `F` (6) alongside Active/Closed/Dormant; `num_instalments` is 0 for 106 rows; `disbursement_tranches` carries multi-drawdown history as a packed string, which is the CCF input Phase 7 anticipates.

Verification: focused EIR suite 73 tests / 307 assertions. Unmapped Extract A columns fell from 21 to 18 — the remainder are fields with no destination in `contract_eir` and are reported to the operator rather than dropped silently.

### Phase 2.8b — Dry run of both extracts through the full import path (2026-08-12)

Both files were then run through `MappedFileReader` → import service → the live `loan_books` join, inside a transaction that was rolled back. Nothing was written. Three findings, all needing MAIIC/VGCBS input.

**Every facility appears twice in Extract A** — 362 rows for 181 accounts. 139 pairs are byte-identical; **42 disagree, all on `repayment_frequency`**, and 17 of those disagree between two real values rather than against a blank: Monthly vs Half-Yearly ×10, Monthly vs Quarterly ×4, Monthly vs Yearly ×3. The importer originally took the first row, which resolves a Monthly-vs-Yearly pair — a twelvefold error in the annualised rate — by file order. It now merges instead: a blank never overrides a stated value, and two different stated values reject the facility with the field and both values named. **Question for Barry: why is every row duplicated, and which frequency is authoritative?**

**74 of 181 facilities are not on the loan tape.** By `account_status`, the off-tape set is 46 Closed, **29 Active**, 1 Dormant. The closed ones are expected — a current tape carries live loans — but they still need EIR history for the comparative period, and the present rule holds them out of `contract_eir` permanently. The 29 active ones are a genuine data gap: facilities the core-banking master calls active that never appear in the monthly tape. **The hold rule may be backwards for the master specifically:** Extract A *is* the facility master, so refusing it for absence from a monthly snapshot inverts the source of truth. The documented design is soft integrity plus a Phase 6 orphan report, which argues for create-and-flag rather than hold. Decision required.

**Extract C covers three loan accounts.** 28 rows over 12 periods of 2025, 3 distinct accounts, MK268,493,417.82 in total posted interest, `period_type` = `MONTH`. It is a sample, not the book — the Phase 6 GL reconciliation cannot run on the portfolio until a full Extract C is delivered. Extract A is itself a subset too: 181 facilities against 3,609 distinct contracts on the tape.

Confirmed in passing: zero origination-fee lines routed (no fee columns exist), and of the 89 facilities that would be created, 65 are floating and 24 fixed.

### Phase 2.8c — Stated conventions captured, mapping screen profiles the file (2026-08-12)

Three Extract A columns now have destinations, taking mapping coverage from 18 to 21 of 36.

`source_day_count_basis` and `source_compounding` carry the `source_` prefix deliberately. IFRS 9 does not prescribe a day count; it requires the rate that exactly discounts estimated future cash flows through expected life to the gross carrying amount (§5.4.1, Appendix A). The day count decides how interest accrues *between* dates, so 365 versus 360 moves each period's interest by about 1.4% — and the delivered file shows the book is not on one convention (365 ×336, 360 ×26). The conventions memo is therefore choosing whether to **override** the source for 26 facilities, not merely to record one. Keeping stated separate from applied is what lets that override be explained instead of silently absorbed, and leaves the unprefixed names free for the approved convention.

`disbursement_tranches` is the one that changes numbers rather than disclosure. The solver anchors its cash-flow vector on a single drawdown at `origination_date`, but 155 distinct tranche strings appear across 314 facilities: a loan drawn in stages has several negative flows at different dates, so its true EIR differs from what a single-drawdown model returns. The raw history is captured now so the affected facilities can be identified; consuming it is a change to the solver input, not to this import, and it is also the Phase 7 CCF input.

**The mapping screen now profiles the whole file** rather than showing its first three rows. Each column reports every distinct value with a row count, plus a blank count — the Excel autofilter view. The old sample was actively misleading: `portfolio` read "FInES · FInES · FInES" and hid that a second portfolio existed, `as_of_date` read "45658 · 45658 · 45658" and hid that it was an Excel serial, and a constant column looked identical to a two-valued one. The facts that decide a mapping are categorical, and none were visible in three rows.

On the delivered Extract A this immediately surfaces, before any import runs: `loan_account_number` distinct = 181 with every value ×2 (the duplication), `repayment_frequency` blank ×25, `day_count_basis` split 336/26, `account_status` carrying undocumented `H` ×42 and `F` ×6. Three values are shown per column with the distinct and blank counts carrying the rest; profiling scans up to 5,000 rows and stops collecting new values at 200 distinct, so a key column reports its cardinality without the response carrying every value.

**`portfolio` and `product_type` are stored on `contract_eir`, not joined** — taking coverage to 22 of 36. The standing rule on this table is to reach master data by join, and `loan_books` does carry the same taxonomy (`funding_source` holds MAIIC/FInES, `product_group` holds the product names). Two things make this the exception. The join only reaches facilities on the current tape, and 74 of the 181 delivered facilities are absent from it — 46 of them closed, which are precisely the ones the comparative period must report on. And portfolio is fixed at origination: a loan does not move between the MAIIC and FInES funding lines, so unlike a stage or a balance it has no as-of problem and cannot go stale. It drives the Note 22–24 concentration disclosure, the per-portfolio roll-up of the GL reconciliation, and the below-market question on the FInES concessionary line. `product_type` closes a latent gap at the same time: the reader already offered that column on the mapping screen but nothing persisted it, so an operator's mapping was silently discarded.

**Still open:** `DR_CR_INDICATOR` in Extract B has not been re-verified the same way.

## Phase 3 — The solver 🟡 IN PROGRESS

### Phase 3.1 — Rules, pure solver and readiness gate (2026-08-03) ✅ COMPLETE

- `EirAccountingRuleSeeder` installs six **draft, unapproved** starting rules (arrangement, direct origination legal cost, monitoring, anniversary, penalty/default and general legal expenditure). Accounting approval remains a user action.
- `CalculateEirService` is a database-independent periodic IRR solver: Newton–Raphson with bisection fallback, explicit periodic/nominal/effective results, residual/iteration audit output and the complete input snapshot. Invalid frequency, cash-flow shape and non-convergence are rejected.
- `EirReadinessService` returns `READY` or `BLOCKED` with named reasons. It checks instrument scope, lock state, origination date, drawn amount, frequency, original schedule, schedule dates, principal reconciliation, unresolved fee classifications, reviewed integral cash direction and positive initial net investment.
- `EirContractInputService` is the read-only boundary between stored contract data and the solver. It refuses blocked contracts, loads original schedule version 1 in due-date order, assigns payment periods, includes only reviewed integral fee/cost lines, calculates `drawn − received + paid`, and returns a complete immutable-style input snapshot. `EirContractNotReadyException` carries the named readiness issues to the future job/UI.
- ACADES golden result passes (quarterly EIR approximately 8.6217%, nominal approximately 34.49%, effective approximately 39.21%). Solver, readiness and intake suite: 16 tests / 49 assertions passing.

Phase 3 is not complete: database orchestration (`CalculateEirJob`), batch processing, persistence/locking UI and remaining client fixtures are still pending.

Planned: `CalculateEirJob` — Newton-Raphson in payment-period units, anchor = drawn − integral fees; refuses EQUITY_EXCLUDED; flags FinES below-market; fixture suite must pass (ACADES golden numbers: quarterly 8.6217% / nominal 34.49% / effective 39.21% on net proceeds 95.99m; Malasha ≈ 10.29%; NyamNyam ≈ 34.92%; MMC refused; Nascomex classification test).

**Blocked on Phase 0 sign-offs:** conventions memo (day count; the ACADES instalment-implied 30.17% vs quoted 32.1% discrepancy), keyman-insurance integral ruling, Nascomex IAS 32 memo.

---

## Phase 4 — Impairment rewiring 🔲 NOT STARTED

Planned: discount factor into `ExpectedCreditLossController::calculateECL` at original (fixed) / current (floating) EIR; Stage-1 PD pro-rating; kill the `?? 0.10` fallback in `CalculateDiscountingJob`; use stored EIR in collateral discounting; parallel `ecl_value_undiscounted` for one period; golden-number test updated.

---

## Phase 5 — Revenue engine 🔲 NOT STARTED

Planned: `RunEirRevenueJob` writing `eir_amortisation`; gross/net accrual by stage; unwind; cure detection (stage 3 → lower, period-over-period); modification gains/losses; rate-reset schedule regeneration; report page (= Table 2).

---

## Phase 6 — Audit pack 🔲 NOT STARTED

Planned: auto-generated CIR-vs-EIR materiality report each period; reconciliations gross by stage and facility; fee-amortisation-to-GL tie; methodology note (three-column format) + governance page; limitations register kept current.

---

## Phase 7 — Accuracy upgrades 🔲 NOT STARTED

Planned: actuals (transaction ledger) import → `cash_source = IMPORTED` + derived-vs-actual reconciliation; CCF model when drawdown history exists.

---

## Open items (blocking or pending decisions)

| # | Item | Blocks | Owner |
|---|---|---|---|
| 1 | Conventions memo signed (solve period, day count, annualisation labels) | Phase 3 | Dr Thom / Kundai |
| 2 | ECL time conventions (discount horizon t per stage; Stage-1 PD pro-rating basis) | Phase 4 | Dr Thom / Kundai |
| 3 | Nascomex pref-share IAS 32 classification memo | Phase 3 fixture | Dr Thom |
| 4 | Keyman insurance: integral fee or not | Phase 3 fixture | Dr Thom |
| 5 | Staging rebuttal sign-off (90-day long-tenor backstop, RBM basis) | Phase 2 config seed | Dr Thom |
| 6 | Data request sent (assessment workbook xlsx; schedules/terms; ledger flagged phase 2) | Phase 2 first loads | Tamanda |
| 7 | Low-credit-risk book scope line in writing | Engagement scope | MAIIC / Dupleix |
| 8 | ACADES basis discrepancy (solved +4.3pp uplift vs assessment max 2.79%) investigated | Phase 6 materiality report credibility | Kundai / Dr Thom |
