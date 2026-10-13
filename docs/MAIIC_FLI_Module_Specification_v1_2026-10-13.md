---
kicker: MAIIC | IFRS 9 FORWARD-LOOKING INFORMATION
title: The forward-looking module, specification version 1
subtitle: How the economy enters MAIIC's expected credit loss: what is built, what is missing, the target design, the decisions, and the order of work
date: 13 October 2026
prepared: Prepared by Dupleix Institute for the Malawi Agricultural and Industrial Investment Corporation plc (MAIIC)
people: Project sponsor Dr Thomson Kumwenda, CFO | Engagement lead Edward Mazibuko CA(SA) | Build lead Kundai Muriwo
status: Draft for review. Builds on specification version 4, sections 13 (macro statistics), 14 (forward-looking adjustments) and 15 (economic scenarios), which stay in force; where this document decides something new, it says so and it governs for the forward-looking module.
---

## 0. Why this document, and how to read it

A proposal for a complete forward-looking information (FLI) module was put to the team on 13 October 2026. It describes the module a bank should have: macroeconomic data with its history of forecasts, governed scenarios, calibrated models, scenario-level ECL, discounting at the effective interest rate, a monthly close and an annual review, controls and audit workbooks.

The proposal is right in its principles. Most of what it asks for already exists in the MAIIC system, some of it under other names, and some of it is missing. This document does three things: it maps the proposal onto what is built (section 2), it records the decisions that close the gaps (section 3), and it sets the target design and the order of work (sections 4 to 9). Section 10 lists what MAIIC and Dr Thom are asked to decide.

One rule runs through all of it: **the module extends what exists; it does not build a second forward-looking process beside the first.** A parallel set of tables is how the legacy tools came about (now kept under Administration, Legacy FLI tools, and not used by the ECL).

## 1. What IFRS 9 asks for

| Requirement | Where | What it means for MAIIC |
|---|---|---|
| An unbiased, probability-weighted amount, from a range of possible outcomes | 5.5.17(a), B5.5.42 | More than one scenario where the loss responds non-linearly to the economy; the loss is weighted, not the economy |
| The time value of money | 5.5.17(b), B5.5.44 | Shortfalls discounted at the effective interest rate set at initial recognition; for a floating-rate asset, the current effective rate |
| Reasonable and supportable information about past events, current conditions and forecasts, without undue cost | 5.5.17(c), B5.5.49 to B5.5.54 | Only information available at the reporting date; forecasts may lean on long-run averages beyond the reasonable forecast horizon |
| 12-month ECL in Stage 1, lifetime in Stage 2 and 3 | 5.5.3, 5.5.5 | The forward-looking view enters the PD over the right window: one year for Stage 1, the remaining life for Stage 2 |
| Credit-impaired assets | Appendix A, B5.5.33 | Stage 3 is measured on the shortfall of expected cash flows; no further PD is applied on top of the default that has happened |

IFRS 9 does not prescribe a model, a number of scenarios, or a formula for the adjustment. Each is a choice MAIIC makes, supports with evidence, documents and keeps under review.

## 2. The proposal against what is built

| The proposal asks for | In the MAIIC system today | Status |
|---|---|---|
| Macroeconomic variables, observations, sources and import batches | Macro Statistics (`macro_statistics`, `macro_statistics_data`, `macro_source_import_batches`), World Bank fetch with an offline snapshot, IMF WEO and RBM policy-rate files, the bridge to monthly series (`macro_series`) | Built (spec v4 section 13) |
| Forecast vintages: a value as known on a date | Every FLI read goes through `AsAtSeries` (commit ac12c36): a period sees only what was knowable by its end, with a governed publication lag per series | Built in the engine; **gap:** the vintage is the load date, not the publisher's release date |
| Scenario sets, versioned, approved, immutable once published | `governed_scenario_sets`, `governed_scenarios`, `scenario_shocks`; proposed by one person, approved by another, locked with the period; rules (at least 3 scenarios, base at least 40 percent, no weight above 60 percent, a calibration note on every downside); back-test and sensitivity | Built (spec v4 section 15) |
| Model calibration with diagnostics | Correlation Finder (`fli_suggestions`, `analysis_runs`), Regression (`fli_relationships`, `fli_fits`) under the guardrail (sign, R-squared, p-value, minimum observations), maker-checker approval, method cards | Built; **gap:** no model registry: a fit is re-estimated and re-approved every month instead of once a year |
| Segment-specific models | PD and LGD by segment on a governed basis (portfolio, RBM sector, both), with a thin-segment rule (commit 67087b1) | Built for PD and LGD; **gap:** the FLI fit is book-level only |
| Separately approved expert overlays | Manual Overlays register (`fli_overlays`): scope, reason, evidence, owner, expiry, maker-checker, shown as its own line | Built; **gap:** no check that an overlay does not repeat a risk the model already carries |
| Scenario-level ECL, then weighted | The route weights the stage PD so that the booked ECL equals the scenario-weighted ECL (commit ac12c36) | Built while FLI moves the PD alone; **gap:** once LGD or EAD vary by scenario, the ECL must be computed per scenario and then weighted |
| A marginal PD term structure per scenario for lifetime ECL | Tables exist and are empty (`ecl_projection_runs`, `ecl_pd_term_structures`, `ecl_cashflow_projections`, `ecl_recovery_cashflows`); the Stage 2 lifetime PD is 1 - (1 - PD12)^(months/12) with one 12-month adjustment | **Gap** |
| FLI on LGD and EAD | FLI moves the PD only | **Gap** (and a decision, section 3) |
| ECL discounted at the EIR, separate from FLI | `EclDiscountingService` and `EclDiscountRateService`: the locked original EIR, no fallback rate, unresolved loans named | Built; **gap:** PLR-linked floating loans use the original EIR as a labelled proxy, not the current effective rate |
| A guided monthly close | Workspace close checklist; Maintenance, Month-end (the steps in order); Installation Guide 9.9 | Built; **gap:** no FLI readiness gate in the checklist |
| A run snapshot for reproducibility | Lineage on each loan (`fli_route`, `fli_method`, `fli_fit_id`, `fli_set_id`, `pd_segment_run_id`) and on each ECL row | Partly; **gap:** no single snapshot record per ECL run |
| ECL movement reconciliation | The IFRS 9 note for the annual financial statements (allowance and gross carrying amount by stage, the charge, the basis) | Built; **gap:** no line for the FLI movement on its own |
| Audit workbooks | The compliance workbooks, the auditor's pack, branded exports of every report | Partly; **gap:** the model calibration and scenario workbooks |

## 3. Decisions

Each decision has a number so the build, the tests and the screens can refer to it.

### D-F1. Three cadences, not one

| Activity | Cadence | Who |
|---|---|---|
| The FLI model: drivers, lags, coefficients, method | Once a year, and when an event breaks the relationship (a devaluation, a drought, a change of policy regime) | Risk proposes; a second reviewer validates; the CFO approves |
| The scenario set: paths and weights | Every quarter, and when the outlook moves materially; confirmed (not rebuilt) at each month-end | Finance or ALCO proposes; a second person approves |
| Applying the approved model and scenarios to the loans | Every month-end | The month-end run |

Today the regression is re-estimated and re-approved every month. That changes: the month-end **applies** the model version in force at the reporting date; it does not re-fit it. A month-end that finds no approved model or scenario set in force for its date stops with the reason; it never falls back to the pre-FLI PD for an official run.

### D-F2. The model registry

The fits stay the calibration evidence. An approved fit is promoted into a **model version** with an effective date, its coefficients, its diagnostics and its segment assignment. The route reads the version in force at the reporting date. A recalibration creates a new version; old versions are never edited, so a December 2025 result stays reproducible after a 2026 model is published.

Lifecycle: Draft, Calibrated, Validated, Approved, Published, Retired. Moving to Approved needs a second person; moving to Published sets the effective date.

### D-F3. Weight the loss, scenario by scenario

The ECL is computed once per scenario and then weighted: ECL = sum over scenarios of weight x ECL(scenario). Today the weighted stage PD reproduces this exactly because only the PD moves. As soon as the LGD, the EAD or the cash flows vary by scenario, the per-scenario computation is the only correct one. The per-scenario results are stored, so the sensitivity table and the booked allowance come from the same rows.

### D-F4. A lifetime term structure per scenario

For Stage 2 the engine builds, per scenario, a marginal PD for each future year of the remaining life, with the survival probability to that year, from the scenario path and the approved model; the path reverts to the long-run average beyond the reasonable forecast horizon (B5.5.50), with the horizon a governed setting (three years proposed). Lifetime ECL = sum over years of marginal PD x LGD x EAD x discount factor. Stage 1 uses the first year only. The tables built for this (`ecl_pd_term_structures`, `ecl_projection_runs`) are used rather than new ones.

### D-F5. Stage 3

Stage 3 is not given a PD. Its loss is the shortfall of expected recoveries, discounted at the EIR, where recoveries are modelled loan by loan; otherwise EAD x LGD at the reporting date. Scenarios may move the size and the timing of recoveries (the LGD and the time to realise collateral), never the fact of default. A collateral benefit counted in the LGD is not deducted again.

### D-F6. The discount rate

FLI never changes the effective interest rate or the contractual cash flows. A fixed-rate loan is discounted at its locked original EIR. A floating-rate loan linked to the prime lending rate is discounted at its **current** effective rate (B5.5.44): the original spread over the PLR in force at the reporting date, taken from the reference-rate series as at that date. Until that is built the original EIR stays as the labelled proxy it is today. The Malawi policy rate is a driver of credit risk in the scenarios; it is never the discount rate.

### D-F7. The transmission method while MAIIC's history is short

MAIIC has about two years of monthly loan-level history from E-Banker (July 2024 onwards). A logit model on MAIIC's own defaults is not yet supportable. The proposed order:

| Method | Use | Status |
|---|---|---|
| Credit-cycle (Vasicek single-factor) shift: the PD moves through a systematic factor Z estimated from an industry series, with the asset correlation governed | Default, once an industry series is held | Method card exists; needs the RBM or industry NPL series |
| Multiplicative scalar on a fitted proxy ratio (today's method) | Until the industry series is held, and as the challenger afterwards | In force |
| Logit model on grade-level default rates | When at least five years of grade-level history exist | Method card exists; preconditions not met |

Whichever method is in force, the guardrail adds a **stationarity check** (levels that trend together are fitted on their changes, or refused), because two series rising over two years will always look related.

### D-F8. FLI on LGD and EAD

Phase 1 moves the PD only. The LGD may be made scenario-sensitive where there is evidence: for agricultural lending, the price of the financed crop and the realisable value of warehouse-receipt collateral. The EAD follows the contractual repayment schedule and, for undrawn commitments, the governed utilisation; it is not stressed in phase 1. Each extension is a governed decision with its own method card.

### D-F9. Overlays and double counting

An overlay names the risk it covers (a driver, a sector, an event). The system refuses to approve an overlay whose stated risk is already a driver of the model version in force for the same segment, unless the reviewer records why the model does not capture it. An overlay has an expiry date; an expired overlay drops out of the next run.

### D-F10. Vintages from the publisher

Each macro observation keeps the date its publisher released it, not only the date it was loaded. A forecast revised in October never replaces the one that was available at a March close; both are kept, and the ECL run records which vintage it read. Where a release date is unknown, the governed publication lag of the series applies and the value is labelled as an estimate of availability.

### D-F11. A snapshot per ECL run

Each official ECL run records, in one row: the loan book build, the staging run, the segment PD/LGD run, the FLI model version, the scenario set and version, the overlays applied, the EIR lock, the discounting basis and the macro vintage. A reviewer of 31 October 2026 can then rebuild that month's figure with nothing revised later.

### D-F12. The month-end gate

The Workspace close checklist gains an FLI step that is done only when an approved model version and an approved scenario set are in force for the period and have been applied to every loan. The ECL run already refuses a loan without a stage, PD or LGD (commit 70b7333); it also refuses an official run without the FLI step done.

## 4. The screens

The forward-looking module moves out of IFRS 9 Model Setup into its own menu entry, **Forward-Looking Information**, with one tab row (the section-tab pattern used across the system):

| Tab | What it holds | Built from |
|---|---|---|
| Overview | The period's FLI readiness: model version in force, scenario set in force, vintage, overlays, the effect on the ECL, what is missing | New |
| Economic Indicators | The series, actuals and forecasts kept apart, source and release date per value, revisions compared, imports with how-to and samples | Macro Statistics |
| Scenario Workbench | Draft, compare and approve scenario sets: paths per series per year, weights, narrative, calibration note, trajectories charted against history | Scenario Sets |
| Model Calibration | The annual workbench: data selection, the Correlation Finder, the fits under the guardrail, out-of-time back-test, stability, the model version and its lifecycle | Correlation Finder and Regression |
| Overlays | The overlay register with the double-counting check | Manual Overlays |
| Impact Analysis | Proposed against approved: ECL per scenario, weighted ECL, by portfolio, stage, driver and overlay; drill to the loan | New, from the per-scenario results |
| Approval and History | Every model version, scenario set and overlay, who proposed, who approved, when, and what each month-end ran under | New, from the existing approval records |

Every figure on the Overview and Impact Analysis tabs comes from the same stored results as the ECL and the dashboard build-up panel, so a headline can never disagree with the detail.

## 5. The data model

The rule is reuse. New tables are added only where nothing exists.

| Purpose | Table | Change |
|---|---|---|
| Variables | `macro_statistics` | Keep |
| Observations and vintages | `macro_statistics_data`, `macro_series` | Add `published_at` (publisher's release date), `vintage` and `value_type` (actual, estimate, forecast) where missing |
| Sources and imports | `macro_source_import_batches` | Keep |
| Scenario sets, scenarios, paths | `governed_scenario_sets`, `governed_scenarios`, `scenario_shocks` | Keep; add the narrative and the trajectory per year where not held |
| Calibration evidence | `analysis_runs`, `fli_suggestions`, `fli_relationships`, `fli_fits` | Keep (duplicates are no longer written, commit b6e6baf) |
| Model registry | `fli_models`, `fli_model_versions` | **New:** model (target metric, segment, method), version (coefficients, lags, diagnostics, lifecycle status, effective date, approvals), linked to the fit it came from |
| Segment assignment | `fli_model_assignments` | **New:** segment to model version, effective date |
| Overlays | `fli_overlays` | Add the risk covered and the double-counting review |
| Term structures and cash flows | `ecl_projection_runs`, `ecl_pd_term_structures`, `ecl_cashflow_projections`, `ecl_recovery_cashflows` | Use (they exist, empty) |
| Per-scenario ECL | `ecl_scenario_results` | **New:** loan, run, scenario, stage, ECL undiscounted and discounted |
| Weighted ECL | `loan_books`, `expected_credit_loss` | Keep |
| Run snapshot | `ecl_run_snapshots` | **New** (D-F11) |

For a large book the per-scenario and term-structure tables are partitioned by reporting period, with the aggregates kept apart from the loan-level evidence.

## 6. The engine

The existing services are extended, not replaced:

| Service | Role in the module |
|---|---|
| `MacroImportService`, `AsAtSeries` | Observations and vintages as known at the reporting date (D-F10) |
| `ScenarioSetService` | Scenario sets, weights, approval, sensitivity (unchanged role) |
| `CorrelationFinder`, `RegressionEngine`, `Guardrail` | Calibration evidence, with the stationarity check (D-F7) |
| `FliModelRegistry` (new) | Promotes an approved fit to a model version; resolves the version in force for a segment and date (D-F2) |
| `FliProjectionService` (new) | Per scenario: the PD term structure, and the LGD where in scope (D-F4, D-F8) |
| `FliRouteService` | Writes the post-FLI parameters and the lineage to each loan |
| `EclDiscountRateService`, `EclDiscountingService` | The EIR basis, including the floating-rate current EIR (D-F6) |
| `TimePhasedEclService` | The per-scenario, per-year ECL, then the weighting (D-F3) |
| `EclBuildUpService`, the IFRS 9 note | The build-up and the movement reconciliation with an FLI line |

## 7. Controls

| Control | Action |
|---|---|
| Scenario weights do not total 100 percent, or break the governed rules | Publication refused |
| A forecast is missing for a period the horizon needs | Flagged; an official run is refused |
| No approved model version or scenario set in force for the reporting date | An official run is refused (D-F12) |
| A loan without a stage, PD, LGD or resolvable discount rate | Named and excluded from nothing silently; the run refuses (built) |
| A segment without a model assignment | Reported as unmapped exposure |
| A scenario PD outside 0 to 100 percent | Capped, and the cap reported; a model producing it is flagged for review |
| Monthly and annual units mixed in one series | Import refused |
| An overlay repeating a modelled driver | Approval refused without a recorded reason (D-F9) |
| ECL moves by more than a governed tolerance from the prior month | Commentary required before sign-off |
| A forecast revised after it was used | The old vintage kept; the revision becomes a new vintage |

## 8. Reports and workbooks

The Report Hub gains, under Annual report and audit and under Risk and analytics:

- The FLI scenario workbook: the paths, weights, narrative and approvals of the set in force.
- The model calibration workbook: the data, the coefficients, the diagnostics, the back-test and the validation sign-off of the model version in force.
- The FLI impact workbook: ECL per scenario and weighted, by portfolio and stage, with the driver attribution and the overlays.
- The FLI line in the IFRS 9 note's movement reconciliation.

Each carries the reporting date and the run identifier, so it can be reproduced from the run snapshot.

## 9. Order of work

| Phase | Content | Acceptance |
|---|---|---|
| 1. Cadence and registry | D-F1, D-F2, D-F12: the model version in force is applied monthly; no monthly re-fit; the month-end gate | A month-end with no model version in force refuses; re-running August with the same version reproduces the booked ECL to the kwacha |
| 2. Per-scenario ECL and term structure | D-F3, D-F4, D-F5: per-scenario results stored, Stage 2 lifetime by year with survival, Stage 3 on the shortfall basis | Weighted per-scenario ECL equals the booked ECL; the sensitivity table reads the same rows; a Stage 2 loan's lifetime ECL is shown year by year |
| 3. Discounting and vintages | D-F6, D-F10, D-F11: floating-rate current EIR, publisher release dates, the run snapshot | A PLR-linked loan's discount rate moves with the PLR as at the date; a March run re-read in October gives the March figure |
| 4. Screens | Section 4: the Forward-Looking Information menu, Overview, Impact Analysis, Approval and History | Every headline figure equals the stored results; one tab row; the Legacy FLI tools stay under Administration |
| 5. Method and LGD | D-F7, D-F8, D-F9: the credit-cycle method on an industry series, the stationarity check, scenario-sensitive LGD where evidenced, the overlay double-counting check | The method card's preconditions pass on MAIIC's data; an overlay on a modelled driver is refused without a reason |
| 6. Workbooks | Section 8 | Each workbook reproduces from the run snapshot |

Each phase lands with its tests, its screens, the user and administrator manuals updated, and the Installation Guide month-end run (section 9.9) amended where a step changes.

## 10. Asked of MAIIC and Dr Thom

| # | Question | Why it matters |
|---|---|---|
| 1 | Do you agree the three cadences of D-F1 (model yearly, scenarios quarterly, application monthly), and who owns each? | Today the fit is re-approved every month, which is neither necessary nor what auditors expect |
| 2 | Can the Reserve Bank's industry NPL series (or the DFI peer series) be obtained, monthly or quarterly, as far back as possible? | It is the series the credit-cycle method needs while MAIIC's own history is short |
| 3 | Who proposes and who approves the scenario set: Finance, Risk, ALCO? Are the current weights (50 / 15 / 25 / 10, with a severe scenario) the starting point? | Governance of the weights is the first question an auditor asks |
| 4 | Should the LGD of agricultural loans respond to the scenarios through crop prices and warehouse-receipt values? | Decides whether phase 5 includes a scenario-sensitive LGD |
| 5 | For PLR-linked floating loans, is the current effective rate (original spread over the PLR in force) the accepted discount basis? | D-F6; it changes the discounted ECL of 36 floating contracts |
| 6 | What month-on-month ECL movement should require commentary before sign-off? | Sets the tolerance in section 7 |
| 7 | Is a three-year reasonable forecast horizon, with reversion to the long-run average after it, acceptable? | Sets the horizon of D-F4 |

## 11. Where the current figures stand

For August 2026 on the demonstration database: ECL before FLI MWK 11.76 billion, forward-looking effect MWK (0.44) billion, manual overlays nil, ECL booked MWK 11.31 billion. The approved fit is the prime lending rate to the Stage 3 share at a nine-month lag (R-squared 0.74 on 26 monthly points), under the first scenario set. Every approval on that database is labelled "Demo, not a MAIIC approval". The fall in the ECL from the forward-looking effect follows the PLR's decline from a peak of 25.4 percent to 20.8 percent in August 2026; with 26 points and one driver, it is model output to be challenged, not yet a conclusion, which is the case for phases 1 and 5.

## 12. What is built (13 October 2026)

Built on branch eir_revenue_recognition and run end to end on the demonstration database:

| Decision | Built as | Proof |
|---|---|---|
| D-F1, D-F2 cadence and registry | `fli_models`, `fli_model_versions`; approving a fit publishes a version in force from its month; Model Versions screen; three Governance Centre settings (cadence, horizon, movement tolerance) | The eight approved fits of January to August 2026 became versions 1 to 8; a version governs later months without a new fit (test) |
| D-F3 per-scenario ECL | `ecl_scenario_results`, `FliCloseService` | August 2026: weighted ECL 11,314,579,806.37 against booked 11,314,579,806.97 |
| D-F4 term structure | `FliProjectionService` into `ecl_projection_runs` and `ecl_pd_term_structures`; Adjusted Forecast screen | Six portfolios, four scenarios, five to seven years |
| D-F7 external series | Credit Loss Data external records bridged as EXT_ measures, knowable from their release month | Tested with an entered series |
| D-F9 double counting | `risk_driver` and the review on overlays; approval refused on the model's own driver | Test |
| D-F10 vintages | `published_at` on macro and credit-loss rows; the as-at reader honours it | Existing as-at tests pass |
| D-F11 run snapshot | `fli_run_snapshots`, Calculation History | Each month recorded with its inputs and sign-off |
| D-F12 month-end gate | `FliMonthEnd` steps; the Overview and `fli:month-end`; Maintenance job `maiic:task month-end` | A full month-end from the console reproduced the booked August ECL |

Not built yet: the scenario-sensitive LGD (D-F8, awaiting question 4 of section 10), the current-EIR discounting of floating-rate loans (D-F6, awaiting question 5), the credit-cycle method on an industry series (D-F7, awaiting question 2) and the calibration and scenario workbooks (section 8).
