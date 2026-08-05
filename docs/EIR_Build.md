# EIR Build

**The plan for adding Effective Interest Rate measurement, revenue recognition, and discounted ECL to MAICC-IFRS9.**

Status: design approved for build · Owner: Kundai · Reviewer: Dr T. Kumwenda (MAIIC CFO) · Auditor of record: Deloitte
Origin: MAIIC EIR requirement (19 May 2026, confirmed in writing 20 May 2026) closing Deloitte's 2022 ECL audit Finding 5.

---

## 1. Why this exists

IFRS 9 uses the EIR in three distinct places ("the three doors"):

| Door | Job | IFRS 9 ref | State in this system today |
|---|---|---|---|
| 1 — Measurement | Amortised cost is *constructed*: `closing = opening + EIR×opening − cash` | §5.4.1, B5.4.1 | Absent — `carrying_amount` imported from tape |
| 2 — Revenue | `interest = EIR × gross` (Stages 1–2) / `× net` (Stage 3) | §5.4.1 | Absent — no interest revenue code exists |
| 3 — Impairment | ECL = PV of shortfalls discounted at original EIR | §5.5.17(b), B5.5.44 | Present but wrong: `ecl_value = PD×LGD×EAD` undiscounted (`ExpectedCreditLossController.php:342`); LGD job falls back to contractual rate or hardcoded 10% (`CalculateDiscountingJob.php:107`) |

Current state, proven by code search:
- The string "EIR" exists in exactly one place in app code: an orphaned `decimal` column on `collateral_allocations` — never written, never read ("column label, not a computation" — the NHFC trap).
- No interest revenue, unwinding, amortised-cost roll-forward, cure-with-catch-up, or POCI logic anywhere in `app/`.
- Payments are not imported; they are *inferred* from month-over-month balance movements (`ProcessLGDPayments.php`) — principal-only, month-granular, blind to interest cash and netting.
- The legacy plain-PHP repo (`c:\xampp\htdocs\ifrs9`) has a monthly remaining-schedule import with discounting (`monthly_input/repayments/`) but its "EIR" is a CSV header alias for the contractual rate (`map_table_columns.php`: `"Interest Rate" → EIR`), and its final ECL is imported from Excel, not computed.

**Dr Thom's Dec-2025 materiality assessment** (CIR ≈ EIR, mean gap 0.84%, max 2.79%) legitimately defends the *discount-rate proxy* (Door 3) for 2025. It says nothing about Door 2 (revenue timing — MK311m of arrangement fees must amortise over loan lives, not book on day 1) or Door 1. The May-2026 requirement demands the engine regardless. This module answers the requirement; the assessment becomes an auto-generated report (§7.6).

---

## 2. Decisions that precede code (Phase 0)

All to be settled **in writing with Dr Thom** before Phase 3:

1. **Conventions memo.** Solve period = the contract's payment frequency (monthly/quarterly — parameterised, never hard-coded). Report both annualisations, always labelled: nominal (`rate × n`) and effective (`(1+r)^n − 1`). Day-count convention chosen and stated. Evidence this matters: the workbook's "EIR of 39.47%" is monthly IRR 3.2892% × 12 — a *nominal* rate; the true effective is 47.46%. The ACADES offer letter's instalments imply 30.17% nominal against a quoted 32.1% "daily basis payable quarterly" — conventions are live in the client's own paper.
2. **ECL time conventions.** Discount horizon `t` per stage, AND Stage-1 PD pro-rated by days to maturity (closes Deloitte 2022 Finding 1 — currently the flat 1-year PD is applied regardless of remaining term).
3. **Classification judgements (IAS 32).** Nascomex redeemable cumulative preference shares (MK2.5bn): debt-like → in the EIR/ECL engine (consistent with AFS showing pref shares carrying ECL). Equity rows (MMC, NASCOMEX equity, Santhe, Natures Gift, Small Farm Cities, Kwalingana): fair value, **no EIR, no ECL** — engine must refuse them. Keyman insurance (EcoGen): integral fee or not — Dr Thom to rule.
4. **Staging rebuttal.** 90-day Stage-2 backstop for long-tenor facilities, rebutting the 30-DPD presumption (B5.5.37) on the basis of the RBM asset-classification directive + DFI cash-flow seasonality. Documented as evidence-based, never as ECL reduction.
5. **Scope letter.** Low-credit-risk book (cash, T-bills — Deloitte 2022 Finding 4, repeated 2023) explicitly in scope or out; if out, said in writing. Model governance doc ownership (2022 Finding 7).
6. **Open the limitations register** (§9) — in the audit pack from day one.

---

## 3. Schema (Phase 1)

New tables (Laravel migrations, patterns per existing `finance_sicr_*` migrations):

**`contract_eir`** — one row per contract; the EIR is a property of the contract, not the monthly snapshot. Monthly `loan_books` rows JOIN to it; never a column on `loan_books`.
- `contract_id` (unique), `instrument_type` enum(AMORTISED_LOAN, PREF_SHARE, EQUITY_EXCLUDED)
- `rate_type` enum(FIXED, FLOATING); `reference_rate_at_origination`, `markup`, `fee_spread` (the locked component of a floating EIR)
- `origination_date`, `approved_amount`, `drawn_amount`, `moratorium_months`
- `eir_period` (solved IRR in payment-period units), `payments_per_year`, `eir_nominal_annual`, `eir_effective_annual`
- `rate_source` enum(SOLVED_EIR, CONTRACTUAL_PROXY), `schedule_source` enum(IMPORTED, GENERATED), `below_market_flag` (FinES)
- Solver audit: `solver_iterations`, `solver_residual`, `input_snapshot` JSON (the exact cash-flow vector solved), `locked_at`, `locked_by`

**`contract_cashflow_schedule`** — the contractual promise; loaded once per contract, versioned.
- `contract_id`, `schedule_version`, `effective_from`, `due_date`, `principal_due`, `interest_due`, `fee_due`, `schedule_source`
- Unique (contract_id, schedule_version, due_date). Restructures append version N+1; version 1 is never overwritten (modification accounting needs both).

**`contract_fees`** — arbitrary signed line items.
- `contract_id`, `fee_type` (arrangement, legal, appraisal, default, levy, other), `amount` (signed — negative netting lines exist, e.g. NyamNyam legal cost −1.99m), `basis` enum(ON_APPROVED, ON_DRAWN), `integral` bool, `gl_account_ref`

**`eir_amortisation`** — the roll-forward / MAIIC's "Table 2", generated.
- `contract_id`, `reporting_period`, `opening_gross`, `interest_accrued`, `interest_basis` enum(GROSS, NET), `unwind_amount`, `cash_received`, `cash_source` enum(DERIVED, IMPORTED), `modification_gain_loss`, `closing_gross`, `ecl_allowance`
- Unique (contract_id, reporting_period)

**`rate_reset_events`** — floating-rate resets ("subject to variation at MAIIC's option").
- `contract_id`, `reset_date`, `old_reference_rate`, `new_reference_rate`, `new_schedule_version`

**`import_mappings`** — dynamic column mapping templates.
- `import_type`, `source_header`, `target_field`, `transform` (date format, percent/decimal, separators), reusable per file shape

**`staging_thresholds`** — DPD staging moves from code (`LoanBooksImport::classifyIFRS9Stage`) into config.
- `facility_class` / tenor band, `stage2_dpd`, `stage3_dpd`, `rebuttal_basis` (text: RBM directive ref), `effective_from`

---

## 4. Build phases

### Phase 0 — Decisions & scope *(§2 above; blocks Phase 3+)*
Also: data request to MAIIC (Tamanda, cc Dr Thom) — **the fee data largely already exists**: the Dec-2025 assessment workbook has per-facility fees for ~36 facilities, GL-tied (MK171.1m legal + MK311.3m arrangement). Ask for: the workbook in xlsx; original repayment schedules (or terms) per contract; ongoing monthly pack for new disbursements; transaction ledger flagged phase 2. Ebanker cannot extract EIR (stated in the assessment) — never assume a clean core-banking feed.

### Phase 1 — Migrations *(§3)*

### Phase 2 — Intake layer
1. **Dynamic mapping UI**: upload CSV/XLSX → map detected headers to target fields → template saved per import type. Replaces exact-header validation and the legacy hardcoded `case` mapper. Validation moves to *target* fields.
2. **Schedule + fee imports** (clone `LoanBooksImport` chunked pattern). First loads: the assessment workbook; the ten sample offer letters. Per-contract validation: Σ principal_due reconciles to drawn amount; reject the contract, not the file.
3. **Schedule generator (Tier 2)**: annuity from tape terms; supports moratoria (3–6 months, capital+interest capitalising) and any payment frequency; flagged GENERATED.
4. **Staging config UI** + migration of the DPD waterfall to `staging_thresholds`.

### Phase 3 — The solver (`CalculateEirJob`)
- **Implemented foundation (2026-08-03):** `CalculateEirService` (pure Newton/bisection solver), `EirReadinessService` (named blocking reasons), and `EirContractInputService` (reviewed contract data → audit-ready solver input). Job orchestration, persistence and locking remain next.
- **Fee classification gate (built 2026-08-03):** imports remain pending; approved rules suggest treatment; a maker classifies and a different reviewer approves. The solver consumes only `integral = true` and `classification_status = REVIEWED`, and refuses unresolved material lines.
- Newton-Raphson, bisection fallback, in payment-period units. Anchor: `t=0 outflow = drawn_amount − integral fees (fees charged on approved)` — the offer letters' application-of-funds line is the spec (ACADES: 100m approved, 95.99m received, 4.01m fees).
- Refuses `instrument_type = EQUITY_EXCLUDED`. Flags FinES (`below_market_flag`) for the day-1 fair-value discussion. Floating contracts: solve all-in EIR, store `fee_spread = EIR − (reference + markup)` — the spread is what's locked.
- Fallback hierarchy per contract: SOLVED_EIR → CONTRACTUAL_PROXY (defensible per the materiality assessment, disclosed) — coverage reported by exposure.
- Hard guards logged (pattern: the Stage-3 PD guard): non-convergence, EIR < contractual − tolerance, EIR > 100%.
- Trigger: new `contract_id` on import. Recalculation is permission-gated and audit-logged; EIRs are locked.
- **Gate to pass — the fixture suite (§8).**

### Phase 4 — Rewire impairment (Door 3)
- `ecl_value = PD_prorated × LGD × EAD × 1/(1+EIR)^t` — EIR = original (fixed) or current (floating, B5.5.44). One SQL join + expression in `calculateECL`; same substitution in `CalculateDiscountingJob` (kill `?? 0.10`), collateral discounting (`CollateralController` — populate and use the orphaned EIR column), stress testing and report controllers that re-derive ECL inline.
- Stage-1 PD pro-rating (`min(12m, remaining term)`) in the same pass.
- Keep `ecl_value_undiscounted` in parallel for one period → transition-impact disclosure.
- Update `EclGoldenNumberTest` for the discounted formula.

### Phase 5 — Revenue engine (Doors 1 & 2 — the contractual deliverable)
`RunEirRevenueJob`, per period per contract, writing `eir_amortisation`:
- Stages 1–2: `interest = eir_period × opening_gross`. Stage 3: `× (opening_gross − ecl_allowance)`; `unwind_amount` split out; suspended (gross−net) interest disclosed.
- Cure: stage < 3 this period, = 3 prior → revert to gross basis (period-over-period stage comparison, per `TransitionMatrixController` pattern).
- Modification: schedule version N+1 discounted at **original** EIR → `modification_gain_loss` (§5.4.3); EIR unchanged.
- Rate reset: regenerate remaining floating schedule at new reference; fee spread amortisation continues undisturbed.
- `cash_received` from `lgd_payment_tracking_long` (DERIVED) until Phase 7.
- First period: opening = PV of remaining schedule at EIR (the constructed gross).
- Report page: interest income by stage, gross/net split, unwind, suspended interest — this *is* Table 2, generated.

### Phase 6 — Audit pack
- **Auto-generated CIR-vs-EIR materiality report** each period: per-facility difference, mean/max, partially-drawn outliers flagged. Dr Thom's assessment, permanent and automatic ("re-earned every year" solved by code).
- **Reconciliations, gross, by stage and facility** (the 2%-net-gap lesson): constructed `closing_gross` vs tape `carrying_amount`; discounted vs undiscounted ECL; fee amortisation vs GL fee accounts; coverage by rate_source/schedule_source; solver health.
- **Methodology note** in Deloitte's three-column format (*Requirement | Practice | Evidence*) + governance/sign-off page (who runs, who approves recalc, who reviews).
- Limitations register current (§9).

### Phase 7 — Accuracy upgrades (non-blocking)
- Actuals import (`contract_id | value_date | amount | type`) → `cash_source = IMPORTED`; derived-vs-actual reconciliation report (free data-quality win — big gaps mean tape and ledger disagree).
- CCF model from own drawdown experience over time (Deloitte 2022 Finding 6: 100% utilisation accepted as conservative *until data exists*).

---

## 5. The monthly operating cycle (once built)

Dependency order; becomes the workspace checklist:

1. **Open period & intake** — loan book tape (mapped); new contracts' schedules + fees; rate-reset notices; restructure schedules (new versions); collateral register; macro data; (Phase 7) actuals ledger.
2. **Data-quality gate — nothing calculates until it passes** — Ebanker↔model reconciliation; equity filter; new contract_ids → solver queue; gaps logged, never silently defaulted.
3. **Onboard** — solve & lock EIRs (snapshot stored); GENERATED/PROXY tiers where data short; process rate resets.
4. **Staging (SICR)** — DPD waterfall from `staging_thresholds` (90-day long-tenor backstop, documented rebuttal) → pre-qualitative; qualitative SICR register applied → **post-qualitative stage actually moves**; cure/probation transitions detected.
5. **Parameters** — PD: grade curves; Stage 1 pro-rated 12-month, Stage 2 lifetime at remaining tenor, Stage 3 = 1.0. FLI: correlation-health gate (≥60% policy threshold, else **no adjustment**, judgement documented — the "turned down free money" rule, encoded). LGD: type-specific haircuts, collateral discounted at stored EIR, collection LGD with cures, consistent across stages.
6. **ECL run** — discounted, per contract; aggregated by stage/portfolio/sector; stress on top.
7. **Revenue run** — roll-forward: accrue at EIR gross/net by stage, unwind, cure catch-ups, modification gains/losses; closing gross → next period's opening.
8. **Reconcile & review** — §Phase 6 reports; tolerance breaches go back to step 2, not into the accounts.
9. **Govern & lock** — sign-off per governance doc; audit trail check; limitations register updated; period locked (immutable; restatements are new versioned runs).

> intake → quality gate → onboard/solve → stage → parameterise → measure loss → measure income → reconcile → lock

---

## 6. Contract realities the engine must handle (from the ten sampled offer letters)

- **Fees are universal**: arrangement 1–4% (typically 3%) on *approved*; legal ~1–1.6% + fixed; small levies (MLS MK11,000). No no-fee facilities exist. Signed lines occur.
- **Fees on approved, interest on drawn** — partially-drawn facilities have the biggest CIR-vs-EIR gaps (assessment max 2.79%); `commitments` + `facility_utilisation_rate` already on `loan_books`.
- **Two rate regimes**: commercial = floating (reference 25.1–25.3% + 5–7.8% markup, "subject to variation at MAIIC's option" — B5.4.5); FinES = 10% fixed concessional (below-market → day-1 FV flag).
- **Moratoria**: 3–6 months capital+interest, interest capitalising — changes every subsequent flow.
- **Frequencies**: monthly and quarterly both live (ACADES is quarterly). Parameterise payments/year.
- **Not everything is a loan**: design for "an instrument with a classification and a cash-flow profile". 8 of 10 sampled = amortised-cost loans; 1 equity (refuse); 1 pref share (classification-dependent, likely in).

---

## 7. Golden numbers & fixtures

Reference computations the solver must reproduce:

**Nominal vs effective (the workbook's 39.47%):**
monthly IRR 3.2892% → ×12 = **39.47% nominal** → (1.032892)¹² − 1 = **47.46% effective**. Never quote one as the other.

**ACADES (solved from the offer letter):** 8 quarterly instalments of MK17,099,839.71; net proceeds MK95,990,000 (100m − 4.01m fees):

| Basis | Quarterly IRR | Nominal ×4 | Effective |
|---|---:|---:|---:|
| Contractual (P = 100m) | 7.5414% | 30.17% | 33.75% |
| **EIR (net = 95.99m)** | **8.6217%** | **34.49%** | **39.21%** |

Fee uplift ≈ +4.3pp nominal — **exceeds the assessment's stated max of 2.79%**. Open reconciliation question for Dr Thom (different basis in his DIFFERENCE column, or convention drift); resolve and document, do not average away. Note also instalment-implied 30.17% vs quoted 32.1% "daily basis payable quarterly" — the conventions memo exists because of this.

**Fixture suite (all must pass before Phase 4 ships):**

| Fixture | Exercises |
|---|---|
| ACADES | Quarterly frequency; golden numbers above |
| Anchor Processors | 6-month capital+interest moratorium; 66 monthly instalments |
| BERL (FinES) | 10% concessional; MLS levy; 6-month moratorium; below_market_flag |
| EcoGen (FinES) | 4% arrangement fee; 3-month moratorium; keyman-insurance judgement |
| Mphunzitsi SACCO | Scale (MK47.7m fees); % + fixed legal fee |
| Saile | 30.1% (ref + 5%); floating components |
| MMC | **Engine must refuse** (equity) |
| Nascomex pref shares | Classification test (IAS 32 → likely in-engine) |
| Malasha (assessment) | Reconcile to Dr Thom's 10.29% |
| NyamNyam (assessment) | Reconcile to 34.92%; negative fee line |

---

## 8. Audit-finding traceability

| Finding | Closed by |
|---|---|
| Deloitte 2022 #1 — PD one-to-one mapping, no tenor pro-rating | Phase 4 Stage-1 PD pro-rating; weighted-average mapping noted for grade-profile review |
| 2022 #2 — uniform 60% haircut | Already closed in system (type-specific `collateral_types`) |
| 2022 #3 — recovery Stage-3-only | Already closed (collection LGD module) |
| 2022 #4 — zero ECL on low-credit-risk book | **Scope line in Phase 0** — in writing, in or out |
| 2022 #5 — EIR ≈ CIR unassessed | **This entire build**; §7.6 materiality report makes the assessment permanent |
| 2022 #6 — 100% CCF | Accepted-conservative; Phase 7 CCF model when data exists |
| 2022 #7 — no governance framework | Phase 6 methodology note + governance page |
| 2023 — all five repeated | The limitations-register discipline (§9): acknowledged-but-unfixed comes back |
| Dupleix 2025 #1 — 30-day trigger on long loans | `staging_thresholds` config + RBM-based documented rebuttal |
| 2025 #2 — VLOOKUP collateral | Already closed (loop allocation in `CollateralController`) |
| 2025 #3 — no collection LGD | Already closed (LGD module) |
| 2025 #5 — broken FLI applied | Correlation-health gate in monthly cycle step 5 |
| 2025 #7 — Ebanker data integrity | Monthly cycle step 2 quality gate + reconciliation reports |
| 2% net gap hid gross errors | All reconciliations gross, by stage and facility |

---

## 9. Limitations register (seed — keep current in the audit pack)

| # | Limitation | Tier/flag | Owner | Closes when |
|---|---|---|---|---|
| 1 | Back-book schedules pending Ebanker/loan-admin export | schedule_source = GENERATED | Kundai / Tamanda | Schedule files received & imported |
| 2 | Cash received is balance-derived (principal-only, month-granular) | cash_source = DERIVED | Kundai | Phase 7 actuals import |
| 3 | Stage-2 lifetime PD at integer tenor years (no interpolation) | — | Kundai | Curve interpolation added |
| 4 | No CCF model — 100% utilisation assumed | — | MAIIC | 12+ months drawdown data |
| 5 | Low-credit-risk book (cash/T-bills) outside engine | Scope letter | MAIIC / Dupleix | Scope decision + mini-module |
| 6 | ACADES-style basis discrepancy vs assessment DIFFERENCE column | — | Kundai / Dr Thom | Conventions memo signed |
| 7 | Keyman insurance integral-fee treatment undecided | — | Dr Thom | Phase 0 ruling |
| 8 | Nascomex pref-share classification memo pending | instrument_type | Dr Thom | IAS 32 memo signed |

---

## 10. Acceptance bar

Feed an offer letter in one end; get an EIR, an amortisation schedule, a discounted ECL, and a period's interest income out the other — such that a Deloitte reviewer, given the same offer letter and the stored input snapshot, reproduces every number without asking a question. The engine reproduces MAIIC's own hand-computed EIRs before it replaces them; the materiality assessment regenerates itself every period; every judgement is a stored, cited decision. *Build for Dr Thom, deliver to Margaret, reconcile with Tamanda, survive Deloitte.*
