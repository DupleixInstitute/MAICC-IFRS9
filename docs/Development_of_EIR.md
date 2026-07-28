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

## Phase 3 — The solver 🔲 NOT STARTED

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
