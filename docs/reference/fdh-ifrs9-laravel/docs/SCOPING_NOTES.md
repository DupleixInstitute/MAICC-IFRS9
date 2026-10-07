# FDH IFRS 9 Rebuild - Scoping-Phase Register (Design Tips and Decisions)

Purpose: a living register of every scoping-phase tip, decision, and idea captured
during the rebuild analysis, so that nothing is lost before the design docs and the
revised schema are finalised. Each item records the tip, its source/evidence, the
rationale, and where it will be applied in the new `FDH_IFRS9_Laravel` app.

Status legend: [CAPTURED] noted here; [IN-DESIGN] folded into a spec doc; [DONE] built/fixed.
Cross-refs: SYSTEM_DESIGN.md, FLI_AND_PD_METHODOLOGY.md, SICR_QUALITATIVE_WRITEOFFS.md,
LEGACY_SYSTEM_CORRECTIONS.md, data/2025-05/MANIFEST.md.

---

## A. Ingestion order and orchestration

| ID | Tip / decision | Source | Apply in new app | Status |
|----|----------------|--------|------------------|--------|
| A1 | **Exchange rates import FIRST.** Loan-book importers arrive in ORIGINAL transaction currencies; monthly rates come from Treasury; several importers query `exchange_rates_data`, so if FX is done last the already-imported rows never get a rate. | User process note; legacy `book-icon` per-currency manual apply in `import-add-edit-delete_exchange_rates.php:51-72`. | Deterministic order: (1) FX rates (auto-applied) -> (2) customer master -> (3) portfolios (convert original->reporting using the period rate; derive customer_id/business_unit from master) -> (4) ageing (BALM->arrears->bracket) -> (5) collateral (allocate by customer_id) -> (6) repayments -> parameters -> engines. | IN-DESIGN |
| A2 | **FX auto-applied, no manual `book-icon`.** The period's Treasury rates are a governed per-period input loaded first; the loan-snapshot load converts `amount_original x rate -> reporting` in one set-based step. Original currency stays on the row; rate resolved from period -> deterministic re-run. | Legacy `book-icon` UPDATE loan_book_backup per-currency; corrections B6. | `ImportPeriodOrchestrator` applies FX as step 1 output; no per-row manual push. | IN-DESIGN |
| A3 | **Front-end bulk period import + bootstrap orchestration.** One action imports all CSVs for a period folder (if bootstrap was not done), runs all engines, and stamps the import log. Same for bootstrap (specify `--period` or `--all`). Markers on import_log: `is_late`, `bulk`, `bootstrap`. Optional `--with-maintenance`. | User request A. | `ImportPeriodOrchestrator` service + `ifrs9:bootstrap` / `ifrs9:import-period` commands + a front-end Bulk Import screen. | CAPTURED |
| A4 | **Period-coded data subfolders** `data/{YYYY-MM}/` (+ `data/maintenance/`) so the bootstrap can add later months without collision. | User request. | Bootstrap scans period folders; maintenance folder holds reference/master CSVs. | DONE (data/2025-05, data/maintenance) |
| A5 | **ECL models saved to a SEPARATE location, SEPARATELY bootstrapped, and included in the bulk importer.** The workbooks are large (LOANS 104MB, EXCESS 139MB, OVERDRAFTS 2MB) so they must NOT sit in the main data folder; only compact FLI/PD-LGD extracts (+ the small OVERDRAFTS workbook) are versioned under `data/{period}/ecl_models/`. | User note; >50MB no-copy rule; Agent D. | `data/{period}/ecl_models/` holds extracts; a dedicated `ecl-models` import step (bootstrappable + part of the bulk importer) ingests the extracted FLI/PD-LGD values, not the raw 100MB books. | IN-DESIGN |

---

## B. Dashboards, reporting and menu (borrow from ZNBS / BBS)

| ID | Tip / decision | Source | Apply in new app | Status |
|----|----------------|--------|------------------|--------|
| B1 | **Optimise dashboards with pre-aggregated marts** materialised on the ECL run, so tabs read tiny summary tables, not the 266k-row fact each load. | User dashboard-optimisation request; FDH dashboards query loan_book_backup live per tab. | Star schema: `ecl_period_summary`, `ecl_segment_summary`, `ecl_bucket_summary`, `ecl_movement`, `ecl_top_exposures`, `ews_scorecard_summary`; covering indexes `(reporting_period, portfolio_group, stage)` etc.; period-partition the fact. | IN-DESIGN |
| B2 | **Additional necessary charts.** ECL roll-forward waterfall (IFRS 7.35H); stage-migration Sankey/heatmap; PD term structure; coverage heatmap (segment x stage); concentration Lorenz + single-obligor gauge vs RBM limit; DPD-bucket histogram; FLI sensitivity tornado; scenario ECL fan chart; LGD-by-collateral + realisation waterfall; NPL-formation and cure-rate trends; EWS RAG trend; vintage/cohort default curve. | User request. | Dashboard chart set, all sourced from the B1 marts. | CAPTURED |
| B3 | **Report Hub with Excel audit workbooks** - borrow from the ZNBS stress-testing app / BBS-ICAAP-Suite. Every reported figure is exportable to an auditable workbook (inputs -> intermediate -> result, traceable). | User request; BBS-ICAAP-Suite `WorkbookWriter`, `SoceWorkbook`, `ForecastCycleWorkbook`, `ReverseStressEvidenceExcelExporter`. | A Report Hub module + workbook-writer services (PhpSpreadsheet), one workbook per report, each figure traceable to its `ecl_run`/mart/fact. | CAPTURED |
| B4 | **App menu borrows from the ZNBS stress-testing app** for consistency across the Dupleix suite. | User request. | Menu/IA aligned with ZNBS/BBS structure (Data Foundation, Governance Centre, Calculators/Engines, Report Hub). | CAPTURED |
| B5 | **Data Foundation** concept borrowed from ZNBS. A single foundation area that holds the ingested, reconciled, period-stamped source data (loan book, master, ageing, collateral, repayments, FX) before the engines run. | User note. | A Data Foundation module/section grouping ingestion + reconciliation + the period register. | CAPTURED |

---

## C. FLI / PD / LGD methodology

| ID | Tip / decision | Source | Apply in new app | Status |
|----|----------------|--------|------------------|--------|
| C1 | **FLI adjustments and PD/LGD were handed over MANUAL** - keyed from external ECL-model Excel files (`ASS2-Macro Forecasts` for FLI; `ASS6`/`ASS3`/`ASS4` for PD/LGD). | User note; Agent B, Agent D. | Manual entry stays a first-class option; automated derivation is added alongside, not instead. | CAPTURED |
| C2 | **Harvest ideas from the commented-out calculator.** `reports/display_and_apply_regression_results_all_methods.php` builds a **9-method FLI-adjustment engine** (weighted_statistic, annual_diff/annual_change, x correlation R, pd_forecast = slope x macro + intercept, fli_adj_byPDs, FDH by-diff/by-change) -> `REPLACE INTO fli_adjustments(period0..period7)`. Its "Update Loan Book" step `calculator/update_loan_book_pds.php` is an **unfinished stub** (commented loop, fatal typo `pd_transitiyu5on_matrices`, periods hardcoded 202112/202210). Abandoned; `fli_adjustments` holds data for 202212 only. | Agent D; Index.htm:1002-1004 (menu commented out). | Rebuild the regression->FLI pipeline properly: dynamic periods, a working set-based loan-book PD push, ONE live table (not 4 stale ones), the 9 methods as governed, selectable adjustment methods. | CAPTURED |
| C3 | **Stale duplicate FLI tables** must collapse to one clean model. Legacy has `fli_forecasts` (skeleton, no PK, mutate-on-read), `fli_adjustments` (regression output, 202212 only), `fli_adj` (LIVE, 202505), `fli_results` (latest 202401). | Agent D DB reconciliation; corrections C9. | Effective-dated `fli_forecast_sets` + `fli_adjustments` with PKs/FKs; no mutate-on-read; no MAX-period carry-forward scan. | IN-DESIGN |
| C4 | **The FLI source sheet is `ASS2-Macro Forecasts`.** 4 key drivers (GDP Growth/IMF, Unemployment/World Bank, RBM Policy Rate/EIU, USD:Kwacha/EIU); 4 scenarios (Upside/Base/Downside1/Downside2) with probabilities **0.25/0.40/0.25/0.10**; probability-weighted forecast per driver; PD-effect **Factor** per business unit (Bank/CIB/GIO/PBB); portfolio allocation by macro factor. 202505 `fli_adj` factors tie out to ASS2 col-E to 4 dp (e.g. gdp x Bank = -0.6766 = E35). | Agent D extract + tie-out. | Seed + manual-entry screen mirrors ASS2; automated path can regenerate the same factors. Extracts in `data/2025-05/ecl_models/FLI_forecasts_from_ecl_model*.csv`. | DONE (extracts) |
| C5 | **PD/LGD default = cumulative stage-transition + LGD movements** (TNM/MAIIC balance-summation, M^n cumulative). **Average-of-periodic-averages** (FDH /5) is a SEPARATE, selectable method. Both governed; per `business_unit` segment (BANK/CIB/GIO/PBB). | User correction; FLI_AND_PD_METHODOLOGY section 4. | `pd.derivation.method` / `lgd.derivation.method` governed params; default = cumulative. | DONE (methodology doc) |
| C6 | **PD/LGD manual-entry route** via tidy long-format import (business_unit, year_offset, metric, value; plus the `avg`/5YrAvg rows). | User request; Agent B. | `PD_LGD_INPUTS.csv` importer + a manual grid keyed like the monthly inputs. Extract at `data/2025-05/PD_LGD_INPUTS.csv` (reconciled to ASS6). | DONE (CSV) |
| C7 | **Lag factors** must be part of the real-time simulated parameters (macro drivers can transmit to ECL with a lag). | User note. | Governed lag per driver in the FLI/regression module. | CAPTURED |
| C8 | **Dynamic N scenarios** (any N, names + forecasts, weights enforced to sum to 100%); weight OUTCOMES not inputs (Jensen). | User; MAIIC ScenarioSet. | Dynamic ScenarioSet with hard 100% enforcement; `ecl.scenario_method=probability_weighted_outcomes`. | IN-DESIGN |

---

## D. Data model and correctness principles

| ID | Tip / decision | Source | Apply in new app | Status |
|----|----------------|--------|------------------|--------|
| D1 | **No MEDIUMINT identifiers.** FDH `clients.customer_id` MEDIUMINT saturates at 16,777,215 (384,025 IDs overflow). | User; corrections A1. | `customer_ref VARCHAR(40)`, `customer_id BIGINT UNSIGNED` (principle 7). | IN-DESIGN |
| D2 | **No generated-column staging chains.** Staging on `loan_book_backup` is a 7-column STORED GENERATED chain; SICR overlay is dead (sicr_stageing_flag=0 for all rows). | Corrections B3/C1. | Explicit set-based staging engine writing auditable columns. | IN-DESIGN |
| D3 | **No mega-wide tables.** loan_book_backup ~203 cols; pd_inputs 276 cols; the 203-col schema even reserved room to import legacy ECL. | User; corrections. | Normalised facility x period grain; a `legacy_ecl_imports` table preserves the import-legacy-ECL capability. | IN-DESIGN |
| D4 | **PKs/FKs everywhere.** `fli_forecasts` has no PK (all id=0); several importers duplicate silently. | Agent D; corrections. | Surrogate PKs + natural unique keys + FKs on every table. | IN-DESIGN |
| D5 | **Internal-accounts diversion** must be preserved (penny-exact reconciliation depends on it). | Corrections D1. | Diversion step in the loan-book load; `internal_accounts` master (maintenance CSV). | DONE (maintenance CSV) |
| D6 | **BALM is authoritative for term-loan ageing** (BALM -> arrears -> dpd_bracket_imported -> staging). The LOAN_AGEING pipe path is dead. | Corrected earlier; corrections B2. | Ageing importer keys off the BALM file. | IN-DESIGN |
| D7 | **No hardcoded figures / fallbacks.** Fail closed and flag; frontend always from backend. | Standing constraint. | Enforced across services. | STANDING |

---

## E. Legacy corrections applied / catalogued (see LEGACY_SYSTEM_CORRECTIONS.md)

| ID | Tip / decision | Source | Status |
|----|----------------|--------|--------|
| E1 | **FX importer include-path + trailing-comma bug** fixed in legacy app. | Corrections B6. | DONE (legacy fix) |
| E2 | **internal_accounts importer column offset** (read row[5]/row[6] but header has Account Description at 5, Created at 6; phantom outstanding_amount) - CORRECTED to `account_description => row[5]`, phantom dropped. | Agent E; corrections B7. | DONE (legacy fix) |
| E3 | **regression_definitions importer** - CORRECTED two fatal parse errors (`if ($statistic_name '')`, unbalanced `in_array` parens), `$conn`->`$connect` (incl. `$GLOBALS`), `mysqli_num_rows` on a SQL string, `statistic_description`->`statistic_name`, and the import INSERT writing into `credit_loss_proxy_definitions` instead of `regression_definitions`. | Agent E + source review; corrections B7. | DONE (legacy fix) |
| E4 | **reporting_periods CSV importer** header is a copy of the 13-col regression header (unusable; periods are jTable-CRUD only). | Agent E; corrections B7. | CAPTURED (new app: profile-based) |
| E5 | Full catalogue (~24 defects: MEDIUMINT overflow, repayments column swap, EXCESS 14-vs-15, EIR proxy, weights not enforced, write-off->Paid, SICR write-only, FLI mutate-on-read, etc.). | LEGACY_SYSTEM_CORRECTIONS.md. | CATALOGUED |

---

## F. Assets produced in scoping (real May-2025 data)

- `data/2025-05/` : loan book, ACCOUNT_MASTER, BALM ageing, EXCESS (15-col w/ CUSTOMER_ID), collateral template, REPAYMENTS (3-col, Amount=DB interest_rate), EXCHANGE_RATES (9-col), PD_LGD_INPUTS.csv, BUSINESS_UNITS.csv (pending Agent G), `ecl_models/` FLI extracts. + MANIFEST.md.
- `data/maintenance/` : collateral_master, macro_scenarios, macro_statistics, industry_sector_codes, internal_accounts, significant_credit_risk_increase_master, regression_definitions, credit_loss_proxy_definitions, segmentation_column CSVs.
- `docs/references/` : evidence library (primary sources + method-to-source register).

---

## G. Open synthesis tasks (the deliverables these tips feed)

1. **REVISED normalized schema** (facility x period grain; marts; effective-dated FLI; legacy_ecl_imports) - awaiting Agent F (comprehensive inventory + process + manual + ECL chain) and Agent G (business units).
2. **Codify orchestration (A3/A5)** + FX-first ordering (A1/A2) into SYSTEM_DESIGN.md.
3. **Report Hub + Data Foundation + menu (B3/B4/B5)** into SYSTEM_DESIGN.md and MANUAL.md.
4. **Dashboard marts + chart set (B1/B2)** into SYSTEM_DESIGN.md.
5. **Automated regression->FLI pipeline (C2/C3)** - harvest the 9-method engine into FLI_AND_PD_METHODOLOGY.md as governed, selectable methods.
