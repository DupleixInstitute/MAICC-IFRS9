# MAIIC EIR & Revenue Recognition Engine — Consolidated Technical Specification

**Version:** 2.2 (trial-balance corpus) · **Date:** 2026-08-19 (original consolidation 2026-08-05)
**Status:** Living spec — reconciles the two parallel design tracks into one authoritative document for build + client/auditor collaboration.
**Repo:** `MAICC-IFRS9` (github.com/DupleixInstitute/MAICC-IFRS9) — Laravel 10 + Vue 3/Inertia + Tailwind
**Owner (build):** Kundai Muriwo · **Reviewer:** Dr T. Kumwenda (MAIIC CFO) · **Auditor of record:** Deloitte · **Engagement lead:** Edward Mazibuko (Dupleix Institute)

> **2026-08-19 delivery update.** MAIIC delivered the **full monthly trial-balance corpus (19 months, Jan 2025 → Jul 2026), the signed 2025 AFS, and the AFS↔E-Banker mapping workbook** on 2026-08-19, in response to the 2026-08-18 data-request email. This is the first delivery containing an **income statement** — all loan-interest and fee GL accounts are now in hand. See **§3.4** for the corpus, the engine-critical **cumulative-YTD rule**, the December pre-closing TB, the two-chart-of-accounts mapping, and the audit-adjustment bridge. Open item **#18 is closed**; items **#20–#23** are new.

> **2026-08-18 status refresh.** Doors 1, 2 & 3 (§6, §7) and the GL-reconciliation core of §8 are now built and pushed — see §12 for the updated gap table and the reasoning behind each status change. This work landed on branch **`eir_revenue_recognition`** (pushed, not yet merged to `master`) — everything marked ✅ below is committed and code-inspected, but the automated test suite has **not** been independently run against this clone (no `vendor/` installed here), so treat test-file presence as evidence the coverage exists, not as a confirmed green run.

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

| Door | Job | IFRS 9 ref | State in system today (2026-08-18) |
|---|---|---|---|
| **1 — Measurement** | Amortised cost is *constructed*: `closing = opening + EIR×opening − cash` | §5.4.1, B5.4.1 | ✅ **Built** — `EirRevenueService`: `closing = opening + interest + unwind − cash`, replacing tape-imported `carrying_amount` |
| **2 — Revenue** | `interest = EIR × gross` (Stage 1–2) / `× net` (Stage 3) | §5.4.1 | ✅ **Built** — `EirRevenueService` branches exactly on stage: gross basis for 1–2, net-of-`ecl_allowance` for 3 |
| **3 — Impairment** | ECL = PV of shortfalls discounted at original EIR | §5.5.17(b), B5.5.44 | ✅ **Built** — `EclDiscountRateService` resolves `eir_effective_annual` per contract/period; `CalculateDiscountingJob` discounts at it. The hardcoded `?? 0.10` fallback is removed — a payment with no resolvable locked rate is **skipped and counted**, not defaulted |

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
| **A — Facility master** | one row / facility | `CUSTOMER_ID, CUSTOMER_NAME, LOAN_ACCOUNT_NUMBER, SUB_ACCOUNT_NO, GL_ACCOUNT_CODE/TITLE, PORTFOLIO, PRODUCT_TYPE, CURRENCY, LOAN_START_DATE, SANCTIONED_AMOUNT, PRINCIPAL_DISBURSED, …` + (per 22 Jul request) contractual rate/basis, frequency, tenor, first-repayment/maturity/closure/restructure dates, grace, arrangement fee, legal fees, opening amortised cost at 01 Jan 2025 | ✅ Ingestion **built** (`ContractMasterImportService`) → `contract_eir` intake. **Data gap confirmed, not resolved:** the delivered file (36 cols, 362 rows / 181 accounts, MAIIC+FinES both present) carries **no fee fields at all** — no arrangement/legal/origination fee column anywhere, despite the agreed scope requiring them (open item #10) |
| **B — Repayment / cash-flow txns** | one row / cash flow | `RUN_ID, CUSTOMER_ID, LOAN_ACCOUNT_NUMBER, GL_POSTING_REF, TRANSACTION_DATE, TRANSACTION_TYPE, PRINCIPAL/INTEREST/FEE_COMPONENT, TOTAL_AMOUNT, SCHEDULED_ACTUAL_FLAG, BALANCE_AFTER_TRANSACTION` | **Routing built** (Phase 2.6): `Scheduled` → `contract_cashflow_schedule`; `Actual` → `eir_actual_transactions`; non-zero `FEE_COMPONENT` → `PENDING` `contract_fees`. **Data gap confirmed:** 539 of 2,478 delivered rows (22%) carry a `ROW_NOTE` stating the interest/principal split is *"ESTIMATED from amortization formula… not a stored split"* — not raw system data. `DR_CR_INDICATOR` is still absent from the delivered file (open item #9) |
| **C — GL interest postings** | one row / loan / period | `RUN_ID, LOAN_ACCOUNT_NUMBER, GL_ACCOUNT_CODE, PERIOD_TYPE/YEAR/MONTH, INTEREST_INCOME_POSTED, TRANSACTION_COUNT, POSTING_REFERENCES, ROW_NOTE (nets TRANTYPE 303/120/308/309/310/311 per loan per month), GENERATED_ON` | ✅ Ingestion **built** (`GlInterestImportService` → `GlInterestPosting`) → feeds `EirGlReconciliationService` (§8). **Data gap confirmed, not resolved:** the delivered file is only **28 rows covering 3 loan accounts** — far short of the ~181-account book — and carries `INTEREST_INCOME_POSTED` only, no fee/commission income column (open item #9) |

**The two field-level gaps flagged during the 22–23 Jul review** (fee/commission GL lines in Extract C; `DR_CR_INDICATOR` in Extract B) were accepted by Barry as workable but **re-verified against the actually-delivered files on 2026-08-18 and confirmed still open** — see open items #9/#10. Separately, a **Trial Balance data request** (Oct–Dec 2025, GL code/title/debit/credit) was fulfilled by Barry on 2026-08-12 (forwarded internally 2026-08-18): five raw VGCBS report exports (`Rpt_General_Ledger_Detail`) plus three **inconsistently-formatted** `rpt_Trial_Balance` layouts ("TB Format 1/4/14" — three different merged-cell/multi-row-header shapes for the same request), none parseable as delivered. **This was superseded on 2026-08-19** by a complete, consistently-formatted 19-month trial-balance corpus plus the audited AFS bridge — see **§3.4**, which is now the primary GL-side data path. The Oct–Dec `Rpt_General_Ledger_Detail` exports remain useful as the *transaction-grain* spool beneath the TB balances.

### 3.3 Vocabulary reconciliation — v1 spec ↔ implemented code

Anyone reading the OneDrive v1 spec must use this map; the implemented (right-hand) names are authoritative.

| OneDrive v1 spec name | Implemented name | Notes |
|---|---|---|
| `eir_facilities` | **`contract_eir`** | one row per contract; the solved EIR + origination facts + audit snapshot |
| `loan_cash_flows` | **`contract_cashflow_schedule`** (promise) + **`eir_actual_transactions`** (actuals) | v1 conflated schedule and actuals into one table; the build splits them |
| `gl_interest_postings` | **`GlInterestPosting`** (✅ built 2026-08-18) | fed by Extract C via `GlInterestImportService`; reconciliation source (§8) |
| `eir_runs` | folded into `contract_eir` (`solver_iterations`, `solver_residual`, `input_snapshot`, `locked_at/by`) | no separate run table; each solve is stored on the contract with its snapshot |
| `eir_schedule_lines` | **`eir_amortisation`** | the monthly roll-forward = MAIIC's "Table 2" — ✅ now populated by `RunEirRevenueJob` |
| `eir_reconciliations` | *(report, not a table yet — `EirGlReconciliationService` computes it live)* | ✅ GL-vs-EIR variance built (§8); **proposed journal entries and the Deloitte export are still not built** — see §12 |
| `EirSolverService` | **`CalculateEirService`** (+ `EirReadinessService`, `EirContractInputService`) | built |
| `EirReconciliationService` | ✅ **`EirGlReconciliationService`** (built 2026-08-18) | per-loan/period EIR-vs-GL variance with a base-effect/rate-effect/unexplained bridge decomposition — more rigorous than originally scoped |
| `EirRevenueExportService` | *(not built)* | Phase 5 report page exists (`Pages/Eir/Calculations.vue` etc.); the **Deloitte-workbook Excel export is still not built** — **remaining scope**, and its true shape needs re-scoping (see §8) |
| `EirRunController` / `EirFacilityController` / `EirImportController` | **`EirIntakeController`**, **`EirCalculationController`** (run + lock), **`EirCoverageController`**, **`EirDataController`**, **`EirReconciliationController`** (+ `EirAccountingRuleController`, `EirFeeClassificationController`) | ✅ run/lock/reconciliation controllers built 2026-08-18; report-export routes still remaining |
| `maiic:run-eir {period}` | `RunEirRevenue` (console command) built; not yet scheduled | unified month-end command exists — **cron/schedule wiring is the remaining piece** (§10), blocked on Barry's extract cadence becoming a fixed date rather than ad hoc |

---

### 3.4 Path C — the monthly trial-balance corpus *(delivered 2026-08-19 — supersedes open item #18)*

Following the 2026-08-18 data-request email, MAIIC delivered on **2026-08-19** the full monthly trial-balance run plus the audited year-end bridge. This closes the largest remaining data gap: **the income statement, which no earlier delivery contained at all.**

| Artefact | Contents | Status |
|---|---|---|
| **19 monthly trial balances** | `Trial Balance_{DD Month YYYY}.xls`, **Jan 2025 → Jul 2026**, one file per month, all in a single consistent `rpt_Trial_Balance_Malawi` layout (GL Title / Debit / Credit; period stamped in row 1) | ✅ **Parseable as delivered** — supersedes open item #18; the three inconsistent `TB Format 1/4/14` shapes are no longer the delivery format |
| **Signed 2025 AFS** | `MAIIC  FS. 2025- Signed.pdf` | ✅ Received |
| **AFS ↔ E-Banker bridge** | `AFS Final TB and Initial TB Mapped to E-Banker TB for MAIIC for December 2025.xlsx` — 4 sheets: `FINAL AFS TB as of 19th Mar 26` (initial / final / journal adjustments), `Initial TB Submitted to Auditor`, `TB December 2025` (QuickBooks↔E-Banker code map), `Final E-Banker TB Dec 2025` | ✅ Received — and carries the December P&L (§3.4.2) |

**All 19 TBs balance exactly** (Σ Dr = Σ Cr, difference 0.00 on every file) and every file is period-stamped.

#### 3.4.1 ⚠️ The P&L figures are **cumulative year-to-date**, not monthly — engine-critical

Verified across all 19 files. Income and expense balances accumulate through each financial year and **reset every January**:

| GL | Jan 2025 | Nov 2025 | Dec 2025 | Jan 2026 | Jul 2026 |
|---|---:|---:|---:|---:|---:|
| `4873` Arrangement Fees | 14,500,000 | 274,681,000 | *(closed)* | 90,500,000 | 330,207,379 |
| `4871` Legal Fees | 8,908,500 | 141,519,610 | *(closed)* | 49,814,000 | 205,508,541 |
| `4216` Interest — MAIIC Industrial | 51,993,819 | 1,361,118,063 | *(closed)* | 167,288,651 | 1,016,619,979 |

> **Rule the engine must implement:** `monthly movement = TB(month N) − TB(month N−1)`, with **January taken as the January TB itself** (no subtraction across the year boundary). Treating these balances as monthly figures overstates income by roughly an order of magnitude. This applies to every 4xxx/5xxx/6xxx account. Balance-sheet accounts (1xxx/2xxx/3xxx) are point-in-time and must **not** be differenced.

#### 3.4.2 The December 2025 P&L exists — in the AFS workbook, not the monthly file

The standalone `Trial Balance_31 December 2025.xls` is a **post-closing** TB: 121 GL lines, **zero 4xxx/5xxx/6xxx accounts**, P&L already rolled into `3200 Retained Earnings`. It is the only month of the 19 without an income statement — exactly the roll-up the 2026-08-18 email asked MAIIC to avoid.

The **pre-closing** December TB is nonetheless already in hand — sheet **`Final E-Banker TB Dec 2025`** inside the AFS bridge workbook:

| | Standalone monthly file | AFS-workbook sheet |
|---|---:|---:|
| GL lines | 121 | **191** |
| P&L accounts | 0 | **21 × 4xxx, 13 × 5xxx, 36 × 6xxx** |
| Σ Dr = Σ Cr | 117,370,478,433.36 | **122,650,199,563.49** |

**No re-request is needed for December.** The engine's December source is the AFS-workbook sheet, not the standalone monthly file.

#### 3.4.3 Income-statement GL codes the engine must read

Now known and stable across the corpus:

| Code | Account | Door |
|---|---|---|
| `4215` / `4216` | Interest On MAIIC Agricultural / Industrial Loans | 2 |
| `4218` / `4219` | Interest On FInES Agricultural / Industrial Loans | 2 |
| `4201` / `42019` | Interest On MAIIC Term Loan *(appears from Nov 2025)* | 2 |
| `4221` `4222` `4223` `4224` `4260` `4262` | Mega Farm loan interest (fertiliser, seed, working capital, equipment, irrigation, pesticides) | 2 |
| **`4871`** | **Legal Fees** | **EIR input** |
| **`4873`** | **Arrangement Fees** | **EIR input** |
| `4874` / `4875` | Insurance Fees / PCG Guarantee Fees | EIR input (integrality per B5.4.1) |
| `4205` `4211` `4213` | Investment income (T-bill, USD call, coupon settle) — **out of EIR scope** | — |
| `6242` | Impairment of Financial Asset | 3 |
| `6340` | Interest Expense | — |

**This closes the substance of open item #9's fee half** — fee and commission GL lines *do* exist and are now in hand at TB grain. What remains missing is the **customer-level** split of those fees; Extract C still carries `INTEREST_INCOME_POSTED` only.

#### 3.4.4 Two charts of accounts — QuickBooks (AFS) vs E-Banker (core banking)

MAIIC keeps its statutory accounts in **QuickBooks** and its loan book in **E-Banker**. The codes differ and must be mapped, never assumed equal:

| Concept | E-Banker (monthly TBs) | QuickBooks (AFS TB) |
|---|---|---|
| Term-loan interest | `42019` / `4201 · Interest On MAIIC Term Loan` | `4206 · Interest on Term Loans-Maiic` — MK2,128,427,032.36 |
| Fee income | flat `4871`, `4873` | nested `4870 · Services Income:4871 · Legal fees` |

The `TB December 2025` sheet (24 columns, carrying `QB Short Code` and `QuickBooks GL Code and Description` against each E-Banker line) **is the mapping table** and should be ingested as reference data rather than re-derived.

#### 3.4.5 Audit adjustments — build to the final column

The bridge records **32 adjusted accounts, MK13.80bn gross**. The initial TB submitted to audit does **not** equal the AFS. Material items touching this engine:

| Account | Adjustment (MK) |
|---|---:|
| `2059` Mega farm Loans ECL | −3,457,375,678 |
| `1066` ECL Megafarm Loans | +2,274,400,361 |
| `6242` Impairment of Financial Asset | −2,174,499,665 |
| `1060` Mega Farm Loans Interest — Fertiliser | +2,012,035,874 |
| `1320` Interest Suspense → `4206` Interest on Term Loans | −21,865,867 / +21,865,867 |

> **Reconciliation target = the `Final tie to AFS` column**, not `Initial submitted to Auditors`.

#### 3.4.6 Confirmations and residual gaps from this delivery

- ✅ **The Dec-2025 assessment workbook ties to the audited GL.** §3.1's stated *MK171.1m legal + MK311.3m arrangement* matches the AFS TB exactly (`4871` = 171,128,135.00; `4873` = 311,342,792.00). The workbook is GL-tied as claimed.
- ✅ **The previously-undated `TB Extracts.xls` is identified** — byte-identical to `Trial Balance_31 December 2025.xls` (post-closing December). The "which month is this?" question is closed.
- ⚠️ **Correction to a figure circulated on 2026-08-18:** the December TB total is **MK117,370,478,433.36**, not MK234,740,956,866.72. The larger figure double-counts the file's own `Grand Total` row. **Any summation of these TB files must exclude that row.**
- ❌ **`1050103` (Quasi Equity Investment) and `1050203` (FInES Investment Loan) appear in 0 of 19 trial balances.** Nine Extract-A accounts are mapped to GL codes that do not exist in the ledger. Confirmed across nineteen months — a mapping/classification question, not a spool filter (open item #20).
- 🟡 **`1050401` MAIIC Term Loan appears in only 9 of 19 months** — consistent with a product launched late-2025, but worth one line of confirmation (open item #21).
- 🟡 **Period-stamp convention:** files named `31 December 2025` carry the stamp `2025-12-01`. Read as a *period label*, not an as-at date — to be confirmed in writing (open item #22).
- ❌ **Fee coverage at customer grain remains the binding gap.** Extract B carries **MK20,700,000 of fees on one account** against **MK311,342,792** of audited arrangement fees — **6.6% coverage**. The GL now proves the fees exist; the customer-level split still does not (open items #9/#10 remain open on this point).

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

**Phase 3 orchestration — ✅ built 2026-08-18:** `CalculateEirJob` batches independently (a blocked contract doesn't stop the batch) via `EirCalculationService`; `EirCalculationController` runs the lock flow (`whereNull('locked_at')` queue → lock action stamping `locked_at`). **Not independently confirmed:** whether the full fixture suite (§ appendix) passes — still blocked on Phase 0 sign-offs (conventions memo; keyman-insurance ruling; Nascomex IAS 32 memo), since several fixtures depend on those rulings to have a defined expected answer.

---

## 6. Impairment rewiring (Door 3) *(✅ built — Phase 4, 2026-08-18)*

Discounted ECL formula, implemented in `EclDiscountRateService` + rewritten `CalculateDiscountingJob`:

`ecl_value = PD_prorated × LGD × EAD × 1/(1+EIR)^t` — EIR = original (fixed) or current (floating, B5.5.44).

- **Built:** `CalculateDiscountingJob` now resolves the rate via `EclDiscountRateService->resolve()` (keyed on `contract_id` + reporting period) instead of the old `getInterestRateFromLoanBook()` fallback chain. The hardcoded `?? 0.10` default is **gone** — the only remaining reference is a dead, commented-out line. Where no locked rate resolves, the payment is explicitly **excluded and counted** with a named reason rather than discounted at an assumed rate (code comment: *"Discounting a stage-3 recovery at an assumed rate produces an allowance whose basis cannot be explained"*) — this is stricter than the spec asked for, not looser.
- **Not yet independently confirmed:** whether `ExpectedCreditLossController::calculateECL` itself (the headline ECL figure, not just the discounting job) reads `contract_eir` — worth Kundai confirming directly, since this section's original scope named that controller specifically.
- **Not yet done:** collateral discounting (`CollateralController` — the orphaned `EIR` column); Stage-1 PD pro-rating; keeping `ecl_value_undiscounted` in parallel for the transition-impact disclosure. Test coverage added: `EclDiscountRateServiceTest.php` (129 lines).

---

## 7. Revenue engine (Doors 1 & 2 — the contractual deliverable) *(✅ built — Phase 5, 2026-08-18)*

`RunEirRevenueJob` (queued, per period, batches all locked contracts or a named subset) → `EirRevenueService`, writing `eir_amortisation` (= Table 2, generated). **Verified directly in code:**

- Stages 1–2: `interest = monthlyRate × opening`. Stage 3: `× (opening − ecl_allowance)` (`$basis = $stage === 3 ? 'NET' : 'GROSS'`) — confirmed matching IFRS 9 §5.4.1(b) exactly.
- Roll-forward implemented as `closing = opening + interest + unwind − cash`, opening of period N = closing of period N−1 (Door 1, the amortised-cost construction).
- The job tracks and audit-logs, per run: contracts `CREATED`/`RECALCULATED`/`UNCHANGED`/`BLOCKED` (with named reasons per blocked contract), rows still using `cash_source = DERIVED`, and `unclassified_cash` — a genuinely audit-conscious summary, not just a pass/fail.
- Recalculation supersedes later periods whose opening balance depended on a closing figure this run replaced, and records how many rows were superseded.
- **Not yet independently confirmed against this text:** cure-detection (stage reverting from 3), modification gain/loss on schedule versioning, and rate-reset handling — these are described in the service but weren't traced line-by-line; worth Kundai confirming against this list specifically.
- **Report page:** `Pages/Eir/Calculations.vue` (+ `Coverage.vue`, `Data.vue`) exist; whether they present interest income by stage / gross-net split / unwind / suspended interest exactly as Table 2 requires wasn't verified from the UI side.
- Test coverage added: `EirRevenueServiceTest.php` (234 lines), `EirCalculationWorkflowTest.php` (140 lines).

---

## 8. Reconciliation, audit pack & exports *(🟡 partial — Phase 6, 2026-08-18)*

The report that directly answers the audit question — **does the engine's EIR-basis income reconcile to what the GL actually posted?**

- **GL reconciliation — ✅ built.** `EirGlReconciliationService` (`app/Services/Eir/EirGlReconciliationService.php`) does per-loan, per-period EIR-vs-GL variance, and goes further than originally scoped: it decomposes each variance into a **base effect / rate effect / unexplained residual** (rather than a bare number an auditor would have to take on faith), applies a governed tolerance floor (`WITHIN_TOLERANCE` vs `VARIANCE`), and rolls up a period summary. Screen-viewable today at `/eir-reconciliation` (`EirReconciliationController`). **Data caveat — materially improved 2026-08-19 but not yet closed.** Extract C still covers only 3 of ~181 accounts at *customer* grain (§3.2). However the 19-month TB corpus (§3.4) now supplies complete **GL-grain** income by month, including the fee accounts (`4871`, `4873`) that no earlier delivery contained. That enables a **control-total reconciliation today** — engine EIR income vs GL totals per account per month — even while the per-loan tie-out stays thin. **Two rules apply when wiring this:** P&L balances are **cumulative YTD** and must be differenced (§3.4.1), and December 2025 must be sourced from the AFS workbook's pre-closing sheet, not the post-closing monthly file (§3.4.2).
- **No download/export exists yet on this report.** `EirReconciliationController@index` renders the Inertia page only — no Excel or PDF action, unlike the other 30 IFRS 9 hub reports which already support both via `Ifrs9ReportExport` (Excel) and `Barryvdh\DomPDF` (`?download=pdf` on `Ifrs9ReportsController`). **This is the concrete near-term ask: wire the same two download branches onto `EirReconciliationController`, reusing the existing exporter rather than building a new one** — see the 2026-08-18 addendum below.
- **Proposed adjusting journals — ❌ not built.** No DR/CR proposal exists anywhere in the real `contract_eir` schema (a `proposed_journal_dr/cr/amount` field shape exists only on the abandoned, untracked Aug-4 scratch schema — not connected to anything). Blocked on a decision from Dr Thom: which GL account absorbs the EIR-vs-contractual true-up. Once decided, `EirAccountingRule` (already built for fee classification, with `gl_account_ref` + maker/checker fields) is the pattern to extend rather than a new design.
- **Interest-income impairment kept distinct from principal ECL** (Note 21 — Mega Farm MK2.1bn) — **not independently verified** whether `eir_amortisation`/the reconciliation carries `interest_income_impairment` yet; worth Kundai confirming.
- **Auto-generated CIR-vs-EIR materiality report** — not verified as a distinct artefact; the reconciliation bridge above may already substantially cover this, pending review.
- **Methodology note** (Deloitte's *Requirement | Practice | Evidence* format) + governance/sign-off page — ❌ not built.
- **Excel export in "Deloitte's two-sheet layout" — this description is now known to be wrong and needs re-scoping, not just building.** The actual Deloitte reference file (`26100.04 Interest Income - EIR Vs Contractual rate...xlsx`, opened and inspected 2026-08-18) is a `Summary` tab **plus 23 separate worked tenor-bucket tabs (2yr–26yr) plus four audit-software tie-out tabs** (`Tickmarks`, `RNotes`, `TextXRef`, `NumXRef` — generated by Deloitte's own audit tooling, not reproducible or appropriate for MAIIC/Dupleix to hand back). Replicating the full file isn't the real ask. **Open decision, before any export-format code is written:** confirm with Kundai (and likely Dr Thom/Deloitte) that the actual deliverable is the `Summary`-tab shape — the per-loan EIR-vs-contractual comparison — not the 23-tab worked-example structure, which was Deloitte's own workpaper for a different client engagement.

> **2026-08-18 addendum — path to an auditor-downloadable report today, without waiting on the Deloitte-format scoping decision above.** The reconciliation *data* is already correct and live (`EirGlReconciliationService`); what's missing is purely the download action. Recommended sequence:
> 1. Add `?download=xlsx` / `?download=pdf` branches to `EirReconciliationController@index`, mirroring `Ifrs9ReportsController`'s existing pattern exactly — feed the same `rows`/`bridge`/`summary` payload already computed for the Inertia page through `Ifrs9ReportExport` and `Pdf::loadView()`. This is genuinely small (the exporter is generic and already used by 30 other reports) and needs no new design.
> 2. Tighten access before any external party gets a login: the route currently sits behind `permission:settings` — a broad, unrelated permission — rather than the dedicated `view-eir`/`export-eir` permissions §9 already proposes (open item #12). An auditor account should only be able to reach this report, not "settings."
> 3. Stamp each export with the run/lock timestamp it was generated from (reuse the `AuditLoggerService::log` pattern `RunEirRevenueJob` already uses), so a PDF an auditor has on file doesn't silently disagree with the system after a later recalculation.
> 4. Once the Summary-tab scope is agreed, add a second, purpose-built export matching that exact layout — same underlying data, different formatting — rather than retrofitting the generic hub-report layout to look like Deloitte's workpaper.
> This gets MAIIC to "auditors can download a correct, point-in-time EIR reconciliation" in the near term, and defers the harder Deloitte-format-matching question to when it's actually been scoped rather than guessed at.

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

Source: verified commit history on branch **`eir_revenue_recognition`** (pushed, not yet merged to `master`): `b50f708 → 4027641 → 4c4fc42 → 2dfa5b7 → [Extract A/C intake, 2026-08-12] → e497db7 (2026-08-18, "Add comprehensive tests for EIR services and intake processes")`. Status below is from direct code inspection on 2026-08-18, not from `docs/Development_of_EIR.md`'s own build log, which is itself now behind (last touched 2026-08-05) — worth a refresh in its own right.

| Phase | Scope | Status |
|---|---|---|
| **0 — Decisions & scope** | Conventions memo; ECL time conventions; IAS 32 classifications; staging rebuttal; scope letter; data request | 🟡 **Still pending Dr Thom sign-offs** — unchanged since 2026-08-05; now also the blocker for the new journal-entry GL-account decision (§8) |
| **1 — Schema** | 7 core tables + Eloquent models; reversible migration | ✅ **Complete** |
| **2.1 — Schedule generator** | Annuity engine, moratoria, any frequency; `eir:generate-schedules` | ✅ Complete |
| **2.2 — Mapped file reader** | Dynamic header→field mapping, transforms; analyze/read | ✅ Complete |
| **2.2b — Intake UI** | Upload→map→result Vue flow; audit-logged import | ✅ Complete |
| **2.3 — Schedule/fee imports** | Per-contract validation; Σprincipal↔drawn ≤1%; signed fees | ✅ Complete |
| **2.4 — Staging config** | `StagingClassifier` + seeder; legacy-ladder equivalence | ✅ Complete |
| **2.5 — Fee classification** | Accounting rules + maker/checker; only reviewed-integral reach solver | ✅ Complete |
| **2.6 — Extract B routing** | Scheduled/Actual split; `ProcessEirImportJob` | ✅ Code complete — migration-applied status not re-checked this pass |
| **2.7 — Extract A/C intake** *(new since 2026-08-05)* | `ContractMasterImportService`, `GlInterestImportService` → `contract_eir`/`GlInterestPosting` | ✅ **Built 2026-08-12** — closes the "Extract A/C ingestion" gap the v1 spec originally flagged as entirely unbuilt |
| **2.8 — Trial-balance corpus ingestion** *(new, 2026-08-19)* | Parse the 19 monthly `rpt_Trial_Balance_Malawi` files → GL-grain monthly income; apply the cumulative-YTD differencing rule; source December from the AFS pre-closing sheet; ingest the QuickBooks↔E-Banker map | 🔲 **Not started** — data is in hand and parseable (§3.4); this is now the highest-value unblocked build item, since it turns §8's reconciliation from 3-account-thin into a full control-total tie-out |
| **3.1 — Pure solver + readiness gate** | `CalculateEirService`, `EirReadinessService`, `EirContractInputService`; ACADES golden passes | ✅ Complete |
| **3.x — Solver orchestration** | `CalculateEirJob`, batch, persistence + **locking UI** | ✅ **Built 2026-08-18** — `CalculateEirJob` batches per-contract; `EirCalculationController` runs the lock flow. Full fixture-suite pass **not independently confirmed** — several fixtures need Phase 0 rulings to have a defined expected answer |
| **4 — Impairment rewire (Door 3)** | Discount ECL at EIR; Stage-1 PD pro-rating; kill `?? 0.10` | ✅ **Built 2026-08-18** (see §6) — the `0.10` fallback is verifiably gone; Stage-1 PD pro-rating **not yet done** (confirmed absent by direct grep) |
| **5 — Revenue engine (Doors 1&2)** | `RunEirRevenueJob` → `eir_amortisation`; report page (Table 2) | ✅ **Built 2026-08-18** (see §7) — core formula verified correct in code; cure/modification/rate-reset handling and the report page's exact field set not line-by-line verified |
| **6 — Audit pack + reconciliation** | GL recon (Extract C), materiality report, methodology note, **Deloitte Excel export**, Reports-Hub wiring | 🟡 **Partial** (see §8) — GL reconciliation with variance-bridge decomposition is built and screen-viewable; **no download/export action exists on it yet** (the concrete near-term gap); proposed journal entries, methodology note, and the Deloitte-format export are all still ❌ not built, and the export's target format needs re-scoping (it is not "2 sheets") before it's built |
| **7 — Accuracy upgrades** | Actuals import → `cash_source=IMPORTED`; CCF model | 🔲 **Not started** — unchanged |

**Items in the v1 OneDrive spec originally flagged as entirely unbuilt — now resolved:** Extract A ingestion → `contract_eir` ✅; Extract C ingestion → `gl_interest_postings`/`GlInterestPosting` ✅. **Still genuinely unbuilt:** the Deloitte-format Excel export (scope needs re-confirming, see §8); the `reports.{revenue-recognition,eir-reconciliation,eir-export}` Reports-Hub download routes specifically (the pages exist, the export actions don't); the unified `maiic:run-eir {period}` command is built (`RunEirRevenue`) but not yet scheduled; the Annual-Report disclosure outputs (§11) not verified this pass.

**Test files added 2026-08-18:** `EclDiscountRateServiceTest.php`, `EirRevenueServiceTest.php`, `EirGlReconciliationServiceTest.php`, `EirCoverageServiceTest.php`, `EirCalculationWorkflowTest.php`, `EirIntakeStatusTest.php` — substantial in size (100–240 lines each) and exercising real scenarios by inspection, but **the suite has not been run from this clone** (no `vendor/` installed) — treat as "coverage exists," not "confirmed passing," until someone runs it where the dependencies are installed.

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
| 9 | **PARTIALLY ADVANCED 2026-08-19.** The *fee* half is answered at **GL grain**: `4871` Legal and `4873` Arrangement Fees now arrive monthly in the TB corpus (§3.4.3). **Still open at customer grain** — Extract C remains `INTEREST_INCOME_POSTED` only, 28 rows / 3 accounts, and `DR_CR_INDICATOR` is still absent from Extract B. Quantified gap: Extract B carries MK20.7m of fees on 1 account vs MK311.3m audited — **6.6% coverage** | Per-loan reconciliation coverage | Kundai / Barry |
| 10 | **RESOLVED 2026-08-18 (confirms a gap, doesn't close it):** all 36 columns of the delivered Extract A sampled — comprehensive on dates/status/amounts, but **carries no fee field at all** (no arrangement/legal/origination fee column), despite the agreed scope requiring it. *2026-08-19: the GL now proves these fees exist (§3.4.3); the per-facility allocation is what is still missing* | Extract A fee ingestion | Barry |
| 11 | Governed materiality threshold for reconciliation (proposed 100 bps, pending MAIIC/Deloitte) | Phase 6 | Dr Thom / Deloitte |
| 12 | Which role(s) the `view/run/export-eir` permissions sit under; sidebar placement — **now also the access-control question for auditor report downloads (§8)**, currently gated by the unrelated `permission:settings` | Phase 9 UI, auditor export access | Wadzanai / Kundai |
| 13 | Note 15 comparatives once EIR live — restate vs transition note | Disclosure | Dr Thom / Deloitte |
| 14 | Apply migration `2026_08_04_000000_add_extract_b_audit_fields` before using Extract B intake — status not re-checked this pass | Extract B intake | Kundai |
| 15 | Confidentiality: extracts carry real, unanonymised MAIIC/FinES names + balances — same care as all client data; do not reuse in training/course material | — | All |
| 16 | *(new, 2026-08-18)* Which GL account absorbs the EIR-vs-contractual true-up — required before proposed journal entries (§8) can be built at all | Phase 6 journal proposals | Dr Thom |
| 17 | *(new, 2026-08-18)* Deloitte export scope: confirm the real deliverable is the `Summary`-tab shape, not the full 23-tab worked-example + audit-tie-out structure of the reference file — see §8 | Phase 6 export build | Kundai / Dr Thom / Deloitte |
| 18 | ✅ **CLOSED 2026-08-19.** Superseded by the 19-month trial-balance corpus (Jan 2025 → Jul 2026) in one consistent `rpt_Trial_Balance_Malawi` layout, all balancing and period-stamped — plus the signed AFS and the AFS↔E-Banker bridge. See §3.4. No fourth parser and no re-request needed | — | *closed* |
| 19 | *(new, 2026-08-18)* `EirReconciliationController` has no Excel/PDF download action — the data is correct and live but not exportable; see §8 addendum for the concrete fix (reuse `Ifrs9ReportExport`/`Pdf::loadView` pattern already used by the other 30 hub reports) | Auditor-facing deliverable | Kundai |
| 20 | *(new, 2026-08-19)* **GL codes `1050103` (Quasi Equity Investment) and `1050203` (FInES Investment Loan) appear in none of the 19 trial balances**, yet 9 Extract-A accounts are mapped to them. Either the loan book's GL mapping is wrong for those 9, or they sit outside the TB entirely. Confirmed across 19 months — not a spool filter | Loan-book↔GL completeness; §8 reconciliation scope | Kundai / Barry / Dr Thom |
| 21 | *(new, 2026-08-19)* `1050401` MAIIC Term Loan present in only 9 of 19 monthly TBs — consistent with a product launched late-2025, but confirm rather than assume | Reconciliation coverage per product | Kundai / Barry |
| 22 | *(new, 2026-08-19)* **Period-stamp convention**: TB files named `31 December 2025` carry the internal stamp `2025-12-01`. Confirm in writing that this is a period *label* (month beginning) and not an as-at-1st balance — a month of movement rides on the answer | Every TB-derived figure | Kundai / Barry |
| 23 | *(new, 2026-08-19)* **Which December TB is authoritative for the engine** — the post-closing monthly file (121 lines, no P&L) or the AFS workbook's pre-closing `Final E-Banker TB Dec 2025` sheet (191 lines, full P&L). Spec §3.4.2 assumes the latter; confirm with Dr Thom so the year-end tie-out is agreed before build | Phase 2.8 ingestion; year-end reconciliation | Dr Thom / Kundai |

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

**Trial-balance control totals (verified 2026-08-19 — use these as ingestion regression fixtures):**

| Fixture | Value |
|---|---:|
| `Trial Balance_31 December 2025.xls` — Σ Dr = Σ Cr (post-closing, 121 GL lines) | **117,370,478,433.36** |
| `Final E-Banker TB Dec 2025` sheet — Σ Dr = Σ Cr (pre-closing, 191 GL lines) | **122,650,199,563.49** |
| Dec-2025 `4871` Legal Fees (audited; ties to the assessment workbook) | **171,128,135.00** |
| Dec-2025 `4873` Arrangement Fees (audited; ties to the assessment workbook) | **311,342,792.00** |
| Audit adjustments in the AFS bridge — accounts / gross value | **32 / 13,803,498,285.61** |
| Extract B fee coverage vs audited arrangement fees | 20,700,000 / 311,342,792 = **6.6%** |

> ⚠️ **Do not sum a TB file naively.** Each file carries its own `Grand Total :` row; including it doubles the answer (117.37bn → 234.74bn). The 234,740,956,866.72 figure that circulated on 2026-08-18 is this error, not a different trial balance.

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
| **19 monthly Trial Balances (Jan 2025 → Jul 2026)** — primary GL-side source (§3.4) | `2. Documents from clients\New Doc Received 19 Aug\Dupleix 2026\Trial Balance_{DD Month YYYY}.xls` |
| **Signed 2025 AFS** | `2. Documents from clients\New Doc Received 19 Aug\MAIIC  FS. 2025- Signed.pdf` |
| **AFS ↔ E-Banker bridge** — audit adjustments, QuickBooks↔E-Banker map, **and the pre-closing December TB** (§3.4.2 / §3.4.4 / §3.4.5) | `2. Documents from clients\New Doc Received 19 Aug\AFS Final TB and Initial TB Mapped to E-Banker TB for MAIIC for December 2025.xlsx` |
| Oct–Dec 2025 Trial Balance *(raw, three inconsistent formats — superseded 2026-08-19, see closed item #18; the `Rpt_General_Ledger_Detail` files remain the transaction-grain spool)* | `2. Documents from clients\Database extracts\Fw Request October November December 2025 Trial Balance Data for Reconciliation.msg` + `Database extracts\TB Extracts\` (GL Code detail files + `TB Format {1,4,14}.xls`) |
| Script Preparation (reviewed / original) | `2. Documents from clients\Script Preparation_Dupleix_REVIEWED.xlsx` / `…_Dupleix.xlsx` |
| Deloitte yearly template (NCBA FY2024) — **the Summary tab is the export target (§8); the 23 tenor-bucket tabs + 4 tie-out tabs are not** | `2. Documents from clients\26100.04 Interest Income - EIR Vs Contractual rate(2025-05-28 11.36.03).xlsx` |
| Deloitte monthly template *(different client — do not reproduce its figures)* | `2. Documents from clients\Assessment of EIR monthly basis.xlsx` |
| MAIIC's own Dec-2025 EIR workbook | `2. Documents from clients\FW MAIIC EIR Assessment as of 31st December 2025.msg` (attachment) — *moved 2026-08-18 from `1. Engagement Contracting\Emails Received\` for consistency with the rest of the client-data trail* |
| Annual Reports 2020–2025 | `2. Documents from clients\Annual Reports\MAIIC Annual Report {2020…2025}.pdf` (2020 corrupted) |
| This spec | repo `docs\MAIIC_EIR_Revenue_Recognition_Engine_Spec.md` (branch `eir_revenue_recognition`) **and** OneDrive `3. Project Execution\specs\` — **re-sync the OneDrive copy after this 2026-08-18 update** |
| Build plan / build log | repo `docs\EIR_Build.md` / `docs\Development_of_EIR.md` — **`Development_of_EIR.md` is now stale (last touched 2026-08-05); worth Kundai refreshing alongside this spec** |
| Repo | `c:\xampp\htdocs\MAICC-IFRS9` (`github.com/DupleixInstitute/MAICC-IFRS9`) — this update is on branch `eir_revenue_recognition`, not yet merged to `master` |
