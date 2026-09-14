## 6. EIR and Revenue Recognition Engine

### 6.1 The three doors

IFRS 9 uses the effective interest rate in three places, and the engine serves all three:

| Door | What it does | Where |
|---|---|---|
| 1 Measurement | Constructs amortised cost as a roll-forward: closing = opening + interest + unwind minus cash, replacing the tape-imported carrying amount | `app/Services/Eir/EirRevenueService.php` |
| 2 Revenue | Recognises interest as EIR times gross carrying amount for stages 1 and 2 and times the net carrying amount (gross less ECL allowance) for stage 3, IFRS 9 paragraph 5.4.1(b) | `EirRevenueService.php` |
| 3 Impairment | Discounts expected shortfalls at the original EIR | `app/Services/Ecl/EclDiscountRateService.php`, `EclDiscountingService.php`, `CalculateDiscountingJob.php` |

The engine reads the live stage from `loan_books` and writes only to its own tables (chapter 3.9). The authoritative specification is `docs/MAIIC_EIR_Revenue_Recognition_Engine_Spec.md`; where the two differ, the code described here is what runs.

### 6.2 Pipeline

```
accounting rules -> intake (Extracts A, B, C, schedules, fees) -> fee classification (maker, checker)
-> schedule governance (draft to approved v1) -> readiness gate -> solver -> lock (maker, checker)
-> monthly revenue run -> GL reconciliation and trial balance movements -> coverage and blockers
```

Every EIR screen is gated by `permission:settings` in its controller constructor, with `hasRole('admin')` as the only finer control (used for the review override, the lock override and reopening). Dedicated EIR permissions are an open item in the specification.

### 6.3 Fee rulebook

`eir_accounting_rules` hold match conditions (fee type, GL account, cash flow direction, description contains) and a boolean proposed treatment `proposed_integral`. Fee types are arrangement, legal, appraisal, default, levy and other. `app/Services/Eir/FeeRuleMatcher.php` considers only active, approved rules, ordered by priority then id, and the first rule whose non-null conditions all match wins; exclusions are seeded above inclusions so a general "legal costs are integral" rule cannot swallow recovery litigation. A bare "legal" or "other" line matches nothing on purpose so a person decides. `sweepPending()` re-applies the approved rulebook to PENDING lines only and reports examined, matched, unmatched, changed and left-alone counts. Rules are seeded unapproved by `EirAccountingRuleSeeder`; editing a rule resets its approval. Rule approval has no maker and checker gate.

### 6.4 Fee classification

`app/Http/Controllers/EirFeeClassificationController.php` runs the maker and checker. Classify (manual mode with an explicit integral flag, or rule mode applying each line's own matched rule) moves PENDING lines to CLASSIFIED with the classifier's id and reason; in rule mode a line without a matched rule is refused rather than defaulted. Review moves CLASSIFIED to REVIEWED and refuses when the reviewer classified the line, unless the reviewer holds the admin role. Every decision is appended to `eir_fee_classification_events`. Only lines that are REVIEWED and integral enter the solver (`ContractFee::scopeIntegral`).

### 6.5 Schedule governance

Extract B carries remaining cash flows, not the original promise, so the original schedule is either imported or generated from Extract A terms by `app/Services/Eir/ScheduleGeneratorService.php` (level annuity, moratorium interest capitalising monthly, any of 1, 2, 4, 6 or 12 payments a year, final instalment closing rounding drift). `app/Services/Eir/ScheduleWorkflowService.php` writes generated rows as version 1 with source GENERATED and status DRAFT, compares them with the remaining schedule from the earliest remaining due date (principal and interest variance within one percent gives WITHIN_TOLERANCE, otherwise PRINCIPAL_VARIANCE or INTEREST_VARIANCE, or NO_REMAINING_DATA), and approves only a DRAFT with a review note when the comparison is outside tolerance. An APPROVED schedule cannot be regenerated. Only version 1 is written by any code today; restructures as version 2 and modification gains and losses are not yet implemented and `modification_gain_loss` is stored as zero.

### 6.6 Readiness gate

`app/Services/Eir/EirReadinessService.php` returns READY or BLOCKED with named issues. Blockers: `CONTRACT_PROFILE_MISSING`, `EQUITY_EXCLUDED`, `EIR_LOCKED`, `ORIGINATION_DATE_MISSING`, `DRAWN_AMOUNT_MISSING`, `FREQUENCY_INVALID`, `FREQUENCY_ASSUMED` (frequency not stated by a source), `SCHEDULE_NOT_APPROVED`, `ORIGINAL_SCHEDULE_MISSING`, `SCHEDULE_INVALID`, `SCHEDULE_DATE_INVALID`, `SCHEDULE_DATE_DUPLICATE`, `PRINCIPAL_NOT_RECONCILED` (scheduled principal more than one percent from the expected contractual principal, which for a generated schedule with a moratorium is the drawn amount compounded monthly through the moratorium), `FEE_CLASSIFICATION_PENDING`, `FEE_DIRECTION_MISSING` and `INITIAL_NET_INVALID`. The initial net investment is drawn amount minus integral fees received plus integral costs paid. `app/Services/Eir/EirContractInputService.php` turns a ready contract into the solver input (version 1 schedule in due-date order, reviewed integral fees only) and returns an immutable snapshot.

### 6.7 Solver

`app/Services/Eir/CalculateEirService.php` is a pure periodic internal rate of return solver with no database access. Newton-Raphson runs up to 100 iterations with a bisection fallback of up to 250 iterations; the result is accepted only if the residual net present value is within one part in a billion of the initial investment. Outputs are always labelled: `eir_period`, `eir_nominal_annual` (period rate times payments per year) and `eir_effective_annual` ((1 + period rate) to the power of payments per year, minus one). The date-sensitive entry point discounts on the actual contractual dates under the contract's day-count basis (ACT/365, ACT/360, 30/360, 30E/360) and solves the effective annual rate directly, so an irregular first period or a moratorium does not distort the rate; `EirCalculationService` chooses it whenever every cash flow carries a due date. Validation refuses a non-positive initial investment, an unsupported frequency, duplicate periods and negative receipts.

The ACADES golden case (eight quarterly instalments of MK 17,099,839.71 on net proceeds of MK 95,990,000) must return a quarterly rate near 8.6217 percent, nominal 34.49 percent and effective 39.21 percent (`tests/Unit/Eir/CalculateEirServiceTest.php`).

### 6.8 Calculation, lock and reopening

`app/Services/Eir/EirCalculationService.php` writes the result to `contract_eir` with status CALCULATED, the solver method, iterations and residual, and the input snapshot; a failure sets BLOCKED with the error. `app/Jobs/CalculateEirJob.php` processes a batch and a blocked contract does not stop the batch. Locking requires a CALCULATED result and a reviewer different from the calculator (admin override allowed); it sets LOCKED and is final. Reopening is administrator-only, demands a reason of at least ten characters, archives the locked result to `eir_calculation_history`, moves every amortisation row to `eir_amortisation_history`, marks the contract's ECL discounting as `STALE_EIR_REOPENED` and returns the contract to REOPENED for recalculation and fresh approval. Recalculating an unlocked contract archives the previous result with the reason "Recalculated before final approval".

### 6.9 Revenue run

`php artisan eir:run-revenue {period}` (queued with `--queue`) runs `app/Jobs/RunEirRevenueJob.php` over locked contracts. Per contract and month, `EirRevenueService` computes:

```
monthlyRate = (1 + eir_effective_annual)^(1/12) - 1
basis       = stage 3 ? NET : GROSS
interest    = monthlyRate x (basis NET ? max(0, opening - allowance) : opening)
unwind      = basis NET ? monthlyRate x min(allowance, opening) : 0
closing     = max(0, opening + interest + unwind - cash)
```

Opening is the prior month's closing, or for a late first run the present value of the remaining contractual flows at the locked rate. The stage and ECL allowance are read from the loan book row for the month. Cash received comes from imported actuals when the actuals window covers the month (collection types Interest, Principal+Interest and Fee; disbursements are never netted; unknown types are reported), otherwise from the version 1 schedule and marked DERIVED. Rerunning a period archives that period and every later one for the contract, because each opening is the prior closing; recalculation without a reason is refused.

### 6.10 GL reconciliation

`app/Services/Eir/EirGlReconciliationService.php` compares interest accrued by the engine with interest posted in `gl_interest_postings` per contract and month and decomposes the variance:

```
implied base      = posted / contractual monthly rate
base effect       = contractual monthly x (amortised opening - implied base)
rate effect       = (effective monthly - contractual monthly) x opening
impairment effect = accrued - effective monthly x opening
unexplained       = variance - base - rate - impairment
```

Rows within one percent (floor MK 1) are WITHIN_TOLERANCE; postings with no calculated counterpart are NOT_CALCULATED or NO_CONTRACT and stay out of the bridge as coverage gaps. The bridge totals GL matched and unmatched, the three effects, the unexplained residual and the net variance. On MAIIC's sample ledger the residual reconciles to zero and the impairment effect equals the stage 3 unwind to the cent. No download exists on this screen yet.

`app/Services/Eir/TrialBalanceMovementService.php` supplies control totals from the trial balances: movement equals this month's balance minus last month's, January taken whole, balance sheet accounts never differenced, a missing prior month refused, the pre-closing basis preferred for December. Scope comes from `gl_account_scope` so investment income codes and QuickBooks duplicates are excluded.

### 6.11 Coverage and blockers

`app/Services/Eir/EirCoverageService.php` profiles the whole book in four bulk queries and mirrors the readiness rules (a test asserts the two agree contract by contract). It reports contracts in scope, covered (locked), coverage percent by count and exposure, blockers ranked by exposure with sole-blocker counts, and coverage by portfolio. One documented divergence remains: coverage tests scheduled principal against the drawn amount without moratorium compounding.

### 6.12 Not yet implemented

Proposed adjusting journal entries, the auditor-format Excel export of the reconciliation, dedicated EIR permissions, schedule versions beyond one, modification accounting, rate-reset regeneration, an actuals import with `cash_source = IMPORTED` end to end, a CCF model, and scheduling of the monthly run. `app/Console/Kernel.php` schedules no EIR command today.
