# MAIIC EIR & Revenue Recognition Engine — Consolidated Technical Specification

**Version:** 2.0 (consolidated) · **Date:** 2026-08-05
**Status:** Living spec — reconciles the two parallel design tracks into one authoritative document for build + client/auditor collaboration.
**Repo:** `MAICC-IFRS9` (github.com/DupleixInstitute/MAICC-IFRS9) — Laravel 10 + Vue 3/Inertia + Tailwind
**Owner (build):** Kundai Muriwo · **Reviewer:** Dr T. Kumwenda (MAIIC CFO) · **Auditor of record:** Deloitte · **Engagement lead:** Edward Mazibuko (Dupleix Institute)

**This document supersedes and consolidates:**
1. `MAIIC_EIR_Revenue_Recognition_Engine_Spec.md` v1 (OneDrive `3. Project Execution/specs/`, 2026-08-04) — the extract-driven design + disclosure study. *(preserved as `..._v1_2026-08-04.md`)*
2. `docs/EIR_Build.md` (repo) — Kundai's implementation plan ("the three doors", contract-keyed schema).
3. `docs/Development_of_EIR.md` (repo) — the running build log (source of the §12 status).
4. Claude session transcript, 2026-08-04 section (OneDrive `1. Engagement Contracting/Claude Session/`) — Barry's extract delivery + the 5-step commitment.

> **Why a consolidation was needed.** Two designs were drafted in parallel by two people for the same deliverable and they diverged in vocabulary and primary data path — the OneDrive v1 spec is **extract-driven** (`eir_facilities`, `loan_cash_flows`, `gl_interest_postings`, `EirSolverService`); the code actually built is **contract/offer-letter-keyed** (`contract_eir`, `contract_cashflow_schedule`, `contract_fees`, `CalculateEirService`). The code is the ground truth, so this spec is written on the implemented architecture and folds the v1 spec's extra requirements (Extract A/C ingestion, GL reconciliation, the Deloitte 2-sheet export, the Reports-Hub wiring, the `maiic:run-eir` command, and the Annual-Report disclosure mapping) in as explicitly-tracked remaining scope. §3.3 maps the two vocabularies so neither document confuses a reader.

---

## 1. Purpose

Build an **EIR (Effective Interest Rate) and Revenue Recognition engine** inside the existing `MAICC-IFRS9` codebase, beside its working ECL/IFRS 9 stage-classification module. Per MAIIC's written requirement (19 May 2026) and Dupleix's written commitment (21 Jul 2026), the engine must:

1. Compute the **true EIR** (internal rate of return of the fee-adjusted cash flows) per facility — not a proxy.
2. Recognise interest income on the **amortised-cost basis** each period — for Stage 3 facilities, on the **net** carrying amount (IFRS 9 §5.4.1(b)).
3. **Construct amortised cost** as a roll-forward (`closing = opening + EIR×opening − cash`), replacing the tape-imported `carrying_amount`.
4. **Reconcile** EIR-basis income against what MAIIC's GL actually posted (contractual basis) → per-loan, per-period difference / misstatement schedule.
5. Produce **proposed adjusting journal entries** (DR/CR) closing the gap — proposed only; never auto-posted to E-Banker.
6. **Export** an audit pack in the Deloitte reference-workbook layout, re-performable without reformatting.
7. **Rewire impairment (Door 3)** so ECL discounts expected shortfalls at the original EIR, replacing the current undiscounted `PD×LGD×EAD`.
8. **Run routinely** at any month-end, matching Barry's extract cadence.

This is a **new module beside** the ECL engine in the same repo — sharing its stage classification, portfolio/client model, and import-mapping infrastructure — not a new system.

### 1.1 The "three doors" — why EIR appears three times in IFRS 9

| Door | Job | IFRS 9 ref | State in system today |
|---|---|---|---|
| **1 — Measurement** | Amortised cost is *constructed*: `closing = opening + EIR×opening − cash` | §5.4.1, B5.4.1 | Absent — `carrying_amount` imported from tape |
| **2 — Revenue** | `interest = EIR × gross` (Stage 1–2) / `× net` (Stage 3) | §5.4.1 | Absent — no interest-revenue code exists |
| **3 — Impairment** | ECL = PV of shortfalls discounted at original EIR | §5.5.17(b), B5.5.44 | Present but wrong: undiscounted `PD×LGD×EAD`; LGD job falls back to contractual rate or hardcoded 10% |

---

## 2. Background: why this exists

Deloitte (MAIIC's auditor), across the 2022, 2023 and 2025 audit cycles, has repeatedly found that MAIIC's ECL/interest-income model does something its documentation says it doesn't. The live finding driving this build: MAIIC recognises interest on a **contractual** basis, but IFRS 9 requires the **effective interest** basis — and Deloitte's own worked reference (NCBA Bank Tanzania FY2024 methodology, forwarded 21 Jul 2026) shows the two bases materially diverge once arrangement/legal fees fold into the yield.

MAIIC's own anchor example: a facility of **MK1bn at 30% p.a. with MK46.16m of fees**. Contractual method → MK169.8m interest for the period + MK46.16m fee income on disbursement day. EIR → the fee spreads into the yield, MK216.0m of interest, no day-one fee income. Same cash, same lifetime profit — different *timing*, and therefore a different profit figure in every reporting period until maturity.

**Dr Thom's Dec-2025 materiality assessment** (CIR ≈ EIR, mean gap 0.84%, max 2.79%) legitimately defends the *discount-rate proxy* (Door 3) for 2025. It says nothing about Door 2 (revenue timing — MK311m of arrangement fees must amortise over loan lives, not book on day 1) or Door 1. The May-2026 requirement demands the engine regardless; the assessment becomes an auto-generated report (§8), "re-earned every year" solved by code.

Because MAIIC's book is small (~96 accounts/month), Deloitte's usual sample-and-extrapolate approach is unnecessary and *weaker* evidence than what's achievable here. **The engine re-performs the full per-loan, monthly calculation for the entire book.**

---

## 3. Scope, data sources, and the two data paths

The engine is designed around **"an instrument with a classification and a cash-flow profile"**, not "a loan". Of the ten sampled offer letters: 8 are amortised-cost loans, 1 is equity (the engine must **refuse** it), 1 is a preference share (classification-dependent, likely in).

### 3.1 Path A — offer-letter / assessment-workbook driven *(implemented primary path)*

The lowest-risk path, unit-testable against the ten sample contracts with zero dependency on a clean core-banking feed (Ebanker cannot extract EIR — stated in Dr Thom's assessment). Inputs:
- The **Dec-2025 EIR assessment workbook** — per-facility fees for ~36 facilities, GL-tied (MK171.1m legal + MK311.3m arrangement).
- The **ten sample offer letters** — origination terms, fees, moratoria, frequencies.
- Where no schedule exists, the **Tier-2 schedule generator** produces one from tape terms (flagged `GENERATED`).

### 3.2 Path B — Extracts A/B/C from VGCBS *(delivered 2026-08-03; partially wired)*

Three extracts, VGCBS-sourced (E-Banker core banking, 1,282-table schema), MAIIC + FinES portfolios 2025–2026, join on `CUSTOMER_ID` + `LOAN_ACCOUNT_NUMBER`. Delivered by Barry Makumba (Head of ICT) 2026-08-03 17:52.

| Extract | Grain | Contents (abridged) | Status |
|---|---|---|---|
| **A — Facility master** | one row / facility | `CUSTOMER_ID, CUSTOMER_NAME, LOAN_ACCOUNT_NUMBER, SUB_ACCOUNT_NO, GL_ACCOUNT_CODE/TITLE, PORTFOLIO, PRODUCT_TYPE, CURRENCY, LOAN_START_DATE, SANCTIONED_AMOUNT, PRINCIPAL_DISBURSED, …` + (per 22 Jul request) contractual rate/basis, frequency, tenor, first-repayment/maturity/closure/restructure dates, grace, arrangement fee, legal fees, opening amortised cost at 01 Jan 2025 | Ingestion **not yet built** → `contract_eir` intake |
| **B — Repayment / cash-flow txns** | one row / cash flow | `RUN_ID, CUSTOMER_ID, LOAN_ACCOUNT_NUMBER, GL_POSTING_REF, TRANSACTION_DATE, TRANSACTION_TYPE, PRINCIPAL/INTEREST/FEE_COMPONENT, TOTAL_AMOUNT, SCHEDULED_ACTUAL_FLAG, BALANCE_AFTER_TRANSACTION` | **Routing built** (Phase 2.6): `Scheduled` → `contract_cashflow_schedule`; `Actual` → `eir_actual_transactions`; non-zero `FEE_COMPONENT` → `PENDING` `contract_fees` |
| **C — GL interest postings** | one row / loan / period | `RUN_ID, LOAN_ACCOUNT_NUMBER, GL_ACCOUNT_CODE, PERIOD_TYPE/YEAR/MONTH, INTEREST_INCOME_POSTED, TRANSACTION_COUNT, POSTING_REFERENCES, ROW_NOTE (nets TRANTYPE 303/120/308/309/310/311 per loan per month), GENERATED_ON` | Ingestion **not yet built** → GL-reconciliation source (§8) |

**Two field-level gaps flagged during the 22–23 Jul review** (fee/commission GL lines in Extract C; `DR_CR_INDICATOR` in Extract B) were accepted by Barry as workable — **re-verify against the delivered files before the solver runs on Path B** (open item).

### 3.3 Vocabulary reconciliation — v1 spec ↔ implemented code

Anyone reading the OneDrive v1 spec must use this map; the implemented (right-hand) names are authoritative.

| OneDrive v1 spec name | Implemented name | Notes |
|---|---|---|
| `eir_facilities` | **`contract_eir`** | one row per contract; the solved EIR + origination facts + audit snapshot |
| `loan_cash_flows` | **`contract_cashflow_schedule`** (promise) + **`eir_actual_transactions`** (actuals) | v1 conflated schedule and actuals into one table; the build splits them |
| `gl_interest_postings` | *(table not yet created)* | Extract C reconciliation source — **remaining scope** (§8) |
| `eir_runs` | folded into `contract_eir` (`solver_iterations`, `solver_residual`, `input_snapshot`, `locked_at/by`) | no separate run table; each solve is stored on the contract with its snapshot |
| `eir_schedule_lines` | **`eir_amortisation`** | the monthly roll-forward = MAIIC's "Table 2" |
| `eir_reconciliations` | *(report, not a table yet)* | Phase 6 audit pack — **remaining scope** (§8) |
| `EirSolverService` | **`CalculateEirService`** (+ `EirReadinessService`, `EirContractInputService`) | built |
| `EirReconciliationService` | *(not built)* | Phase 6 — **remaining scope** |
| `EirRevenueExportService` | *(not built)* | Phase 5 report + Deloitte Excel export — **remaining scope** |
| `EirRunController` / `EirFacilityController` / `EirImportController` | **`EirIntakeController`** (+ `EirAccountingRuleController`, `EirFeeClassificationController`) | intake built; run/facility/report controllers remaining |
| `maiic:run-eir {period}` | *(not built)*; `eir:generate-schedules` exists | unified month-end command — **remaining scope** (§10) |

---

## 4. Architecture & data model *(implemented — `contract_eir` schema)*

Follow the existing convention: monthly-snapshot tables for reporting, transaction-grain tables for evidence. **Do not extend `LoanBook`** — it is the ECL module's stable, tested table; the EIR module *reads* its live stage column and *writes* to its own tables. **No FK from EIR tables to `loan_books`** (`contract_id` is a plain indexed string) — schedule files and loan tapes arrive in either order; integrity is enforced by import validation + a Phase 6 orphan-check report.

Migration `2026_07_27_000000_create_eir_tables.php` (+ `2026_08_03` fee-classification strengthening, `2026_08_04` Extract-B audit fields).

| Table | Grain | Purpose |
|---|---|---|
| **`contract_eir`** | one row / contract | The solved EIR + origination facts. `instrument_type`(AMORTISED_LOAN/PREF_SHARE/EQUITY_EXCLUDED), `rate_type`(FIXED/FLOATING), `reference_rate_at_origination`, `markup`, `fee_spread`, `origination_date`, `approved_amount`, `drawn_amount`, `moratorium_months`, `eir_period`, `payments_per_year`, `eir_nominal_annual`, `eir_effective_annual`, `rate_source`(SOLVED_EIR/CONTRACTUAL_PROXY), `schedule_source`(IMPORTED/GENERATED), `below_market_flag`, and the solver audit trail (`solver_iterations`, `solver_residual`, `input_snapshot` JSON, `locked_at/by`) |
| **`contract_cashflow_schedule`** | one row / instalment | The contractual promise, versioned. `schedule_version` append-only (restructure = version N+1; version 1 never overwritten — modification accounting needs both). Unique (contract_id, schedule_version, due_date) |
| **`contract_fees`** | one row / fee line | Arbitrary **signed** line items (netting lines exist). `fee_type`, `amount`, `basis`(ON_APPROVED/ON_DRAWN), `integral`(nullable — B5.4.1 judgement), `gl_account_ref`, plus (Phase 2.5) description, transaction date, direction(RECEIVED/PAID), currency, source ids, `classification_status`, review timestamps |
| **`eir_amortisation`** | one row / contract / period | The roll-forward = MAIIC's "Table 2", generated. `opening_gross`, `interest_accrued`, `interest_basis`(GROSS/NET), `unwind_amount`, `cash_received`, `cash_source`(DERIVED/IMPORTED), `modification_gain_loss`, `closing_gross`, `ecl_allowance`. Unique (contract_id, reporting_period) |
| **`rate_reset_events`** | one row / reset | Floating-rate resets ("subject to variation at MAIIC's option", B5.4.5) |
| **`eir_actual_transactions`** | one row / txn | Extract B `Actual` rows (Phase 2.6); dedup-protected by run/posting refs |
| **`import_mappings`** | one row / (type, header) | Dynamic column-mapping templates with transforms |
| **`staging_thresholds`** | one row / class·band | DPD staging as configurable data + `rebuttal_basis` (RBM directive text) |
| **`eir_accounting_rules`** | one row / rule | Fee-treatment rules (match by type/description/GL/direction → proposed treatment); draft/approved/priority |
| **`eir_fee_classification_events`** | append-only | Maker/checker decision history for fee classification |

**Cross-module joins (v1 spec §12 asks + Notes 22–28):** to slice EIR reports by industry/region/related-party the way MAIIC already discloses, `contract_eir` joins back to the ECL module's `Client`/`LoanBook` records (which carry industry/region) rather than duplicating those fields. A `related_party_flag` (Note 28 — CDH Investment Bank) is required for the related-party interest-income cut. An `interest_income_impairment` concept distinct from principal ECL is required (Note 21 — Mega Farm MK2.1bn) — see §8.

**Ingestion** reuses the existing `GeneralImportTemplate` / `GeneralImportConfiguration` column-mapping framework (as `ProcessLoanImportJob` does for `LoanBook`) via the new `MappedFileReader` + `ProcessEirImportJob` — not a hand-rolled importer.

---

## 5. Computation — the solver (Door 2 input) *(implemented foundation)*

`App\Services\Eir\CalculateEirService` + `EirReadinessService` + `EirContractInputService`.

1. **Readiness gate** (`EirReadinessService`) returns `READY`/`BLOCKED` with **named** reasons: instrument scope, lock state, origination date, drawn amount, frequency, original schedule present, schedule dates, principal reconciliation, unresolved fee classifications, reviewed integral cash direction, positive initial net investment.
2. **Input assembly** (`EirContractInputService`, read-only boundary): loads schedule version 1 in due-date order, assigns payment periods, includes only **reviewed integral** fee/cost lines, computes `drawn − received + paid`, and returns a complete immutable input snapshot. Refuses blocked contracts (`EirContractNotReadyException` carries the named issues).
3. **Solve** (`CalculateEirService`, DB-independent): Newton–Raphson with bisection fallback, in **payment-period units**. Anchor: `t=0 outflow = drawn_amount − integral fees` (the offer letters' application-of-funds line — ACADES: 100m approved, 95.99m received, 4.01m fees). Returns periodic / nominal (`×n`) / effective (`(1+r)^n−1`) results — **always labelled, never conflated** — plus residual/iterations and the input snapshot.
4. **Instrument rules:** refuses `EQUITY_EXCLUDED`; flags FinES `below_market_flag` for the day-1 fair-value discussion; floating contracts store `fee_spread = EIR − (reference + markup)` (the locked component).
5. **Fallback hierarchy:** SOLVED_EIR → CONTRACTUAL_PROXY (defensible per the materiality assessment, disclosed) — coverage reported by exposure.
6. **Hard guards logged:** non-convergence; EIR < contractual − tolerance; EIR > 100%.
7. **Stage 3:** interest recognised on the **net** carrying amount, reading the stage from the ECL module's live output — the one place the two modules must talk.

**Remaining (Phase 3):** `CalculateEirJob` orchestration, batch processing, persistence + EIR **locking** UI, and the rest of the fixture suite (§ appendix). Blocked on Phase 0 sign-offs (conventions memo; keyman-insurance ruling; Nascomex IAS 32 memo).

---

## 6. Impairment rewiring (Door 3) *(remaining — Phase 4)*

Replace the undiscounted ECL with the discounted formula:

`ecl_value = PD_prorated × LGD × EAD × 1/(1+EIR)^t` — EIR = original (fixed) or current (floating, B5.5.44).

- One SQL join + expression in `ExpectedCreditLossController::calculateECL`; same substitution in `CalculateDiscountingJob` (kill the `?? 0.10` fallback), collateral discounting (`CollateralController` — populate and use the orphaned `EIR` column), and any stress-test/report controller that re-derives ECL inline.
- **Stage-1 PD pro-rating** `min(12m, remaining term)` in the same pass (closes Deloitte 2022 Finding 1).
- Keep `ecl_value_undiscounted` in parallel for one period → transition-impact disclosure.
- Update `EclGoldenNumberTest` for the discounted formula.

---

## 7. Revenue engine (Doors 1 & 2 — the contractual deliverable) *(remaining — Phase 5)*

`RunEirRevenueJob`, per period per contract, writing `eir_amortisation` (= Table 2, generated):

- Stages 1–2: `interest = eir_period × opening_gross`. Stage 3: `× (opening_gross − ecl_allowance)`; `unwind_amount` split out; suspended (gross−net) interest disclosed (IFRS 7.20(b)).
- **Cure:** stage < 3 this period, = 3 prior → revert to gross basis (period-over-period stage comparison).
- **Modification:** schedule version N+1 discounted at the **original** EIR → `modification_gain_loss` (§5.4.3); EIR unchanged.
- **Rate reset:** regenerate remaining floating schedule at the new reference; fee-spread amortisation continues undisturbed.
- `cash_received` from `lgd_payment_tracking_long` (DERIVED) until the Phase 7 actuals import.
- First period: opening = PV of remaining schedule at EIR (the constructed gross, Door 1).
- **Report page:** interest income by stage, gross/net split, unwind, suspended interest — this *is* Table 2.

---

## 8. Reconciliation, audit pack & exports *(remaining — Phase 6)*

The report that directly answers the audit question — **does the engine's EIR-basis income reconcile to what the GL actually posted?**

- **GL reconciliation (Extract C / `gl_interest_postings`).** Per facility per period: EIR-basis interest (`eir_amortisation`) vs GL-posted interest (Extract C, the actual ledger). Difference beyond a governed materiality threshold → **misstatement line**, per loan and rolled up per portfolio (MAIIC / FinES). *Requires the Extract C ingestion + reconciliation service noted in §3.3.* Build it the way `EclReconciliationService` / `LoanBookReconciliationService` already work (proven start/end-period movement pattern).
- **Proposed adjusting journals.** DR/CR entries truing up the GL to the EIR basis, in the Deloitte Summary-tab format. **Proposed only** — Finance posts manually in E-Banker (§14).
- **Interest-income impairment kept distinct from principal ECL** (Note 21 — Mega Farm MK2.1bn accrued-but-uncollected interest carries its own ECL). A Stage-3 facility's EIR interest on the net carrying amount can itself need impairing; `eir_amortisation`/the reconciliation must carry an `interest_income_impairment` column so this traces at the granularity MAIIC already reports.
- **Auto-generated CIR-vs-EIR materiality report** each period: per-facility difference, mean/max, partially-drawn outliers flagged — Dr Thom's assessment made permanent.
- **Reconciliations, gross, by stage and facility** (the 2%-net-gap lesson): constructed `closing_gross` vs tape `carrying_amount`; discounted vs undiscounted ECL; fee amortisation vs GL fee accounts; coverage by `rate_source`/`schedule_source`; solver health.
- **Methodology note** in Deloitte's three-column format (*Requirement | Practice | Evidence*) + governance/sign-off page.
- **Excel export** via `maatwebsite/excel` (already a dependency) in Deloitte's **two-sheet** layout: a **Yearly** sheet (contractual vs EIR, per representative period — kept for comparability with the auditor's working paper) and a **Monthly** sheet (the substantive full-book exercise — every facility, every month, EIR vs ledger, misstatement schedule, proposed journals).

---

## 9. Reports Hub, routing & UI

**Implemented (operational intake):** `EirIntakeController` (upload → map columns → result), `EirAccountingRuleController`, `EirFeeClassificationController`; Vue pages `Pages/Eir/{Intake,AccountingRules,FeeClassification}.vue`; routes `eir-intake.*`, EIR accounting-rule + fee-classification routes; menu leaf **"EIR Schedule Intake"** under *Customer & Loan Data*.

**Remaining (run + reports), following the repo's own conventions:**
- **New operational routes** `Route::prefix('eir')->name('eir.')->middleware(['auth'])`: run dashboard (`index`), manual `run` trigger (same job as the artisan command), per-facility contractual-vs-EIR schedule drill-down (`facilities.index/show`).
- **Extend the existing Reports Hub** (single shared `ReportsController`, `report` prefix — as `reports.ecl-reconciliation`/`reports.ecl-export` already do): `reports.revenue-recognition` (§7 income report), `reports.eir-reconciliation` (§8 GL reconciliation), `reports.eir-export` (§8 Excel workbook).
- **Vue pages** mirror `Pages/LoanBook/…` for the operational views; reconciliation/export views live under `Pages/Reports/…`.
- **Permissions** (`spatie/laravel-permission`): propose `view-eir`, `run-eir`, `export-eir`, gated via `$this->authorize()`. Which role/permission set they sit under is an open item.
- **Navigation/sidebar** final placement TBD with Wadzanai/Kundai.

---

## 10. Routine operation — the monthly cycle

Unified month-end command **`php artisan maiic:run-eir {period}`** (queued; matches Barry's cadence; historical runs remain queryable, never overwritten) drives the dependency-ordered cycle (this becomes the workspace checklist):

1. **Open period & intake** — loan tape (mapped); new contracts' schedules + fees; rate-reset notices; restructures (new versions); collateral; macro; (Phase 7) actuals ledger.
2. **Data-quality gate — nothing calculates until it passes** — Ebanker↔model reconciliation; equity filter; new `contract_id`s → solver queue; gaps logged, never silently defaulted.
3. **Onboard** — solve & lock EIRs (snapshot stored); GENERATED/PROXY tiers where data short; process rate resets.
4. **Staging (SICR)** — DPD waterfall from `staging_thresholds` (90-day long-tenor backstop, documented rebuttal) → pre-qualitative; qualitative SICR register → post-qualitative stage moves; cure/probation detected.
5. **Parameters** — PD (Stage 1 pro-rated 12-month, Stage 2 lifetime at remaining tenor, Stage 3 = 1.0); FLI correlation-health gate (≥60% else no adjustment, documented); LGD (type haircuts, collateral discounted at stored EIR, collection LGD with cures).
6. **ECL run** — discounted, per contract; aggregated by stage/portfolio/sector; stress on top.
7. **Revenue run** — roll-forward: accrue at EIR gross/net by stage, unwind, cure catch-ups, modification gains/losses; closing gross → next period's opening.
8. **Reconcile & review** — §8 reports; tolerance breaches go back to step 2, not into the accounts.
9. **Govern & lock** — sign-off per governance doc; audit-trail check; limitations register updated; period locked (immutable; restatements are new versioned runs).

> intake → quality gate → onboard/solve → stage → parameterise → measure loss → measure income → reconcile → lock

---

## 11. Disclosure requirements — studied against MAIIC's 2025 Annual Report

The engine's output must feed MAIIC's disclosures. Findings by note (read against `Annual Report 2025.pdf`, 2024 for comparison):

- **Note 3.1/3.8 (accounting policy, pp.56–65)** already state, word for word, that MAIIC uses the effective-interest method — full EIR/amortised-cost/POCI/credit-adjusted-EIR language. The gap here is in the **audited financial statements themselves**; closing it makes the FS true, not polish.
- **Note 15 "Interest income" (p.81)** — the number the engine must reproduce, currently showing the gap directly:
  ```
  Interest income on loans and advances    6,274,984  (2024: 1,476,240)
  Interest income from investments         4,861,419  (2024: 7,525,499)
  Loan arrangement fees                      213,964  (2024:   355,520)
  Legal fees                                  45,354  (2024:   101,412)
  Total interest income                   11,395,721  (2024: 9,458,671)
  ```
  Arrangement + legal fees are **separate line items** (contractual presentation). Under EIR they spread into the yield. **Live question for 5 Aug and beyond:** does Note 15's presentation change, and how are FY2025 comparatives handled — restate, or leave as reported with a transition note? Not Dupleix's call; raise with Dr Thom + Deloitte.
- **Note 21 / Note 9A — most financially material item this build touches:** MAIIC separately **impaired MK2.1bn of previously-recognised Mega Farm interest income** (against MK4.85bn profit). This is **distinct from principal ECL**; §8's reconciliation keeps it distinct (`interest_income_impairment`).
- **Notes 22–24 (risk mgmt, pp.83–95)** disclose concentration by **portfolio** (MAIIC 51% / Mega Farm 33% / FinES 16%), **region**, and **industry**. Extract A carries `PORTFOLIO` but not industry/region → join back to the ECL module's `Client`/`LoanBook` (§4).
- **Note 25.1 (categorisation, p.95)** — amortised-cost carrying amounts stated as approximating fair value; once the engine runs, sourced directly from `eir_amortisation` closing gross rather than approximated (a disclosure-quality upgrade).
- **Note 28 (related parties, p.99)** — interest income from **CDH Investment Bank** (shareholder) MK506,569 separately disclosed → `related_party_flag` (§4).
- **Note 4.1.3 (critical judgements, pp.66–67)** — the solver's plausibility ceiling + governed materiality threshold are exactly this kind of judgement; worth a Note-4 line once agreed with Deloitte.

### 11.1 Audit-finding traceability

| Finding | Closed by |
|---|---|
| Deloitte 2022 #1 — PD, no tenor pro-rating | Phase 4 Stage-1 PD pro-rating |
| 2022 #2 — uniform 60% haircut | Already closed (type-specific `collateral_types`) |
| 2022 #3 — recovery Stage-3-only | Already closed (collection LGD) |
| 2022 #4 — zero ECL on low-credit-risk book | Scope line in Phase 0 (in writing) |
| 2022 #5 — EIR ≈ CIR unassessed | **This entire build**; §8 materiality report makes it permanent |
| 2022 #6 — 100% CCF | Accepted-conservative; Phase 7 CCF model when data exists |
| 2022 #7 — no governance framework | Phase 6 methodology note + governance page |
| 2023 — all five repeated | Limitations-register discipline (appendix) |
| Dupleix 2025 #1 — 30-day trigger on long loans | `staging_thresholds` config + RBM-based rebuttal |
| 2025 #2 — VLOOKUP collateral | Already closed |
| 2025 #3 — no collection LGD | Already closed |
| 2025 #5 — broken FLI applied | Correlation-health gate (cycle step 5) |
| 2025 #7 — Ebanker data integrity | Cycle step 2 quality gate + reconciliations |
| 2% net gap hid gross errors | All reconciliations gross, by stage and facility |

---

## 12. Current build status *(the gap analysis — what's done vs missing)*

Source: `docs/Development_of_EIR.md` build log + verified commit history (`b50f708 → 4027641 → 4c4fc42 → 2dfa5b7`).

| Phase | Scope | Status |
|---|---|---|
| **0 — Decisions & scope** | Conventions memo; ECL time conventions; IAS 32 classifications; staging rebuttal; scope letter; data request | 🟡 **Pending Dr Thom sign-offs** — blocks Phase 3 completion + Phase 4 |
| **1 — Schema** | 7 core tables + Eloquent models; reversible migration | ✅ **Complete** (verified on dev DB) |
| **2.1 — Schedule generator** | Annuity engine, moratoria, any frequency; `eir:generate-schedules` | ✅ Complete (7 tests) |
| **2.2 — Mapped file reader** | Dynamic header→field mapping, transforms; analyze/read | ✅ Complete (9 tests) |
| **2.2b — Intake UI** | Upload→map→result Vue flow; audit-logged import | ✅ Complete |
| **2.3 — Schedule/fee imports** | Per-contract validation; Σprincipal↔drawn ≤1%; signed fees | ✅ Complete |
| **2.4 — Staging config** | `StagingClassifier` + seeder; legacy-ladder equivalence | ✅ Complete (5 tests) |
| **2.5 — Fee classification** | Accounting rules + maker/checker; only reviewed-integral reach solver | ✅ Complete (17 tests) |
| **2.6 — Extract B routing** | Scheduled/Actual split; `ProcessEirImportJob` | ✅ Code complete — ⚠️ **migration `2026_08_04` not applied** (MySQL was down) |
| **3.1 — Pure solver + readiness gate** | `CalculateEirService`, `EirReadinessService`, `EirContractInputService`; ACADES golden passes | ✅ Complete (16 tests) |
| **3.x — Solver orchestration** | `CalculateEirJob`, batch, persistence + **locking UI**, remaining fixtures | 🟡 **In progress** |
| **4 — Impairment rewire (Door 3)** | Discount ECL at EIR; Stage-1 PD pro-rating; kill `?? 0.10` | 🔲 **Not started** |
| **5 — Revenue engine (Doors 1&2)** | `RunEirRevenueJob` → `eir_amortisation`; report page (Table 2) | 🔲 **Not started** |
| **6 — Audit pack + reconciliation** | GL recon (Extract C), materiality report, methodology note, **Deloitte Excel export**, Reports-Hub wiring | 🔲 **Not started** |
| **7 — Accuracy upgrades** | Actuals import → `cash_source=IMPORTED`; CCF model | 🔲 **Not started** |

**Items in the v1 OneDrive spec still entirely unbuilt** (folded into the phases above): Extract A ingestion → `contract_eir`; Extract C ingestion + `gl_interest_postings` GL reconciliation; the Deloitte 2-sheet Excel export; the Reports-Hub `reports.{revenue-recognition,eir-reconciliation,eir-export}` routes + `EirRun`/`EirFacility` controllers; the unified `maiic:run-eir {period}` command; the Annual-Report disclosure outputs (§11).

**Test totals to date:** ~46 EIR tests passing across generator, reader, import services, classifier, solver, readiness.

> ⚠️ **DB hazard (do not repeat):** `phpunit.xml`'s sqlite override had been commented out, so a `RefreshDatabase` scaffold test once ran `migrate:fresh` against the live `.env` MySQL dev DB and wiped it. The override is now active. **Never run the full suite or any `RefreshDatabase` test against the live dev DB.** The ~116 pre-existing failing scaffold tests (Vitals, etc.) predate this work — candidates for deletion.

---

## 13. Open items (blocking / pending decision)

| # | Item | Blocks | Owner |
|---|---|---|---|
| 1 | Conventions memo signed (solve period, day count, nominal/effective labels; ACADES implied 30.17% vs quoted 32.1%) | Phase 3 | Dr Thom / Kundai |
| 2 | ECL time conventions (discount horizon *t* per stage; Stage-1 PD pro-rating basis) | Phase 4 | Dr Thom / Kundai |
| 3 | Nascomex pref-share IAS 32 classification memo | Phase 3 fixture | Dr Thom |
| 4 | Keyman insurance: integral fee or not (EcoGen) | Phase 3 fixture | Dr Thom |
| 5 | Staging rebuttal sign-off (90-day long-tenor backstop, RBM basis) | Phase 2 config activation | Dr Thom |
| 6 | Data request (assessment workbook xlsx; schedules/terms; ledger flagged phase 2) | Phase 2 first loads | Tamanda |
| 7 | Low-credit-risk book (cash/T-bills) scope line in writing | Engagement scope | MAIIC / Dupleix |
| 8 | ACADES basis discrepancy (+4.3pp uplift vs assessment max 2.79%) investigated — resolve, don't average away | Phase 6 materiality credibility | Kundai / Dr Thom |
| 9 | Re-verify the two accepted Extract gaps (fee/commission GL lines in C; `DR_CR_INDICATOR` in B) against delivered files | Path-B solver run | Kundai |
| 10 | Confirm Extract A carries the full origination-fee/date field set (only ~13 cols sampled) | Extract A ingestion | Kundai / Barry |
| 11 | Governed materiality threshold for reconciliation (proposed 100 bps, pending MAIIC/Deloitte) | Phase 6 | Dr Thom / Deloitte |
| 12 | Which role(s) the `view/run/export-eir` permissions sit under; sidebar placement | Phase 9 UI | Wadzanai / Kundai |
| 13 | Note 15 comparatives once EIR live — restate vs transition note | Disclosure | Dr Thom / Deloitte |
| 14 | Apply migration `2026_08_04_000000_add_extract_b_audit_fields` before using Extract B intake | Extract B intake | Kundai |
| 15 | Confidentiality: extracts carry real, unanonymised MAIIC/FinES names + balances — same care as all client data; do not reuse in training/course material | — | All |

---

## 14. What this spec does not cover

- **No automatic GL posting.** The engine *proposes* journals (§8); Finance posts manually in E-Banker. Scope boundary, not oversight.
- **No data-quality ruleset beyond the flagged gaps.** Full null/duplicate/orphan/date-gap profiling of A/B/C assumed, not designed here.
- **No back-book before 2025** (matches the extract start date); prior-year restatement is a separate ask.
- **No maker-checker/governance workflow** for approving a run's final output (the ECL module may have a reusable pattern — not checked).
- **No golden-vector acceptance-tolerance set beyond the §appendix fixtures** (Schedule 3 of the draft contract has blank tolerance fields — same open item).
- **No hosting/deployment decision** (Schedule 2 of the draft contract; 5 Aug meeting).
- **No UI wireframes/mockups** — §9 specifies routes/controllers/pages, not visual design.
- **No multi-currency EIR nuance beyond Extract A's `CURRENCY` field.**
- **No performance/scale plan** — not a concern at ~96 accounts/month.
- **Phase 2 (direct E-Banker/VGCBS feed) is a roadmap item, not designed.** Today's vendor-scripted-extract path (21 Jul ask → 3 Aug delivery, ~2 weeks) is the real efficiency question; a standing read-only feed (DB view / SFTP / API polled at month-end) is the alternative, dependent on VGCBS cooperation + the 5 Aug infrastructure decision. **Do not wait on it** — Path A is unblocked today.

---

## Appendix A — Golden numbers & fixtures

**Nominal vs effective (the workbook's "39.47%"):** monthly IRR 3.2892% → ×12 = **39.47% nominal** → (1.032892)¹²−1 = **47.46% effective**. Never quote one as the other.

**ACADES (solved from the offer letter):** 8 quarterly instalments of MK17,099,839.71; net proceeds MK95,990,000 (100m − 4.01m fees):

| Basis | Quarterly IRR | Nominal ×4 | Effective |
|---|---:|---:|---:|
| Contractual (P = 100m) | 7.5414% | 30.17% | 33.75% |
| **EIR (net = 95.99m)** | **8.6217%** | **34.49%** | **39.21%** |

Fee uplift ≈ +4.3pp nominal — **exceeds the assessment's stated max of 2.79%** (open item #8).

**Fixture suite (all must pass before Phase 4 ships):** ACADES (quarterly; golden numbers) · Anchor Processors (6-month cap+int moratorium; 66 instalments) · BERL/FinES (10% concessional; MLS levy; moratorium; `below_market_flag`) · EcoGen/FinES (4% arrangement; keyman-insurance judgement) · Mphunzitsi SACCO (scale MK47.7m fees; %+fixed legal) · Saile (floating ref+5%) · **MMC (engine must refuse — equity)** · Nascomex pref shares (IAS 32 test) · Malasha (reconcile to Dr Thom's 10.29%) · NyamNyam (34.92%; negative fee line).

## Appendix B — Contract realities the engine must handle

- **Fees are universal:** arrangement 1–4% (typically 3%) on *approved*; legal ~1–1.6% + fixed; small levies. No no-fee facilities. Signed/netting lines occur.
- **Fees on approved, interest on drawn** — partially-drawn facilities have the biggest CIR-vs-EIR gaps.
- **Two rate regimes:** commercial floating (reference 25.1–25.3% + 5–7.8% markup, "subject to variation") ; FinES 10% fixed concessional (below-market → day-1 FV flag).
- **Moratoria:** 3–6 months capital+interest, interest capitalising.
- **Frequencies:** monthly and quarterly both live (ACADES quarterly). Parameterise payments/year.

## Appendix C — Limitations register (seed — keep current in the audit pack)

| # | Limitation | Tier/flag | Owner | Closes when |
|---|---|---|---|---|
| 1 | Back-book schedules pending Ebanker/loan-admin export | `schedule_source = GENERATED` | Kundai / Tamanda | Schedule files received & imported |
| 2 | Cash received balance-derived (principal-only, month-granular) | `cash_source = DERIVED` | Kundai | Phase 7 actuals import |
| 3 | Stage-2 lifetime PD at integer tenor years (no interpolation) | — | Kundai | Curve interpolation added |
| 4 | No CCF model — 100% utilisation assumed | — | MAIIC | 12+ months drawdown data |
| 5 | Low-credit-risk book (cash/T-bills) outside engine | Scope letter | MAIIC / Dupleix | Scope decision + mini-module |
| 6 | ACADES-style basis discrepancy vs assessment DIFFERENCE column | — | Kundai / Dr Thom | Conventions memo signed |
| 7 | Keyman insurance integral-fee treatment undecided | — | Dr Thom | Phase 0 ruling |
| 8 | Nascomex pref-share classification memo pending | `instrument_type` | Dr Thom | IAS 32 memo signed |

## Appendix D — Data locations (OneDrive; adjust drive root per machine)

Root: `…\OneDrive\2026\Projects\MAIIC\`

| File | Path |
|---|---|
| Extract A/B/C | `2. Documents from clients\Database extracts\Extract {A,B,C}.xlsx` |
| Barry's delivery email | `…\Database extracts\RE Follow-Up Meeting … (extracts delivered 2026-08-03 1752).msg` |
| Script Preparation (reviewed / original) | `2. Documents from clients\Script Preparation_Dupleix_REVIEWED.xlsx` / `…_Dupleix.xlsx` |
| Deloitte yearly template (NCBA FY2024) | `2. Documents from clients\26100.04 Interest Income - EIR Vs Contractual rate(2025-05-28 11.36.03).xlsx` |
| Deloitte monthly template *(different client — do not reproduce its figures)* | `2. Documents from clients\Assessment of EIR monthly basis.xlsx` |
| MAIIC's own Dec-2025 EIR workbook | `1. Engagement Contracting\Emails Received\FW MAIIC EIR Assessment as of 31st December 2025.msg` (attachment) |
| Annual Reports 2020–2025 | `2. Documents from clients\Annual Reports\MAIIC Annual Report {2020…2025}.pdf` (2020 corrupted) |
| This spec | repo `docs\MAIIC_EIR_Revenue_Recognition_Engine_Spec.md` **and** OneDrive `3. Project Execution\specs\` |
| Build plan / build log | repo `docs\EIR_Build.md` / `docs\Development_of_EIR.md` |
| Repo | `c:\xampp\htdocs\MAICC-IFRS9` (`github.com/DupleixInstitute/MAICC-IFRS9`) |
