## 5. IFRS 9 Engines

This chapter records what each engine computes and where the code is. Formulas are quoted from the code; where a behaviour is guarded by a lock or a closed state, the guard is named.

### 5.1 Exposure at default

Every report and engine uses one definition of EAD, held as a SQL constant in `app/Http/Controllers/Reports/Ifrs9ReportsController.php` and `Reports/StressTestingController.php`:

```
EAD = COALESCE(carrying_amount, 0) + COALESCE(commitments, 0) * COALESCE(facility_utilisation_rate, 1)
```

Undrawn commitments are converted with the facility utilisation rate as the credit conversion factor. No separate CCF model exists yet; one hundred percent utilisation is assumed where the rate is null.

### 5.2 Staging and SICR

Quantitative staging on import is done by `app/Services/Eir/StagingClassifier.php`. It reads the arrears bucket columns of a loan book row, takes the lower bound of the highest non-empty bucket as days past due, and applies thresholds from `staging_thresholds` for the facility class and tenor. The fallback ladder when no configuration exists is stage 2 from 31 days and stage 3 from 181 days. Buckets recognised are 1 to 30, 31 to 90, 91 to 180, 181 to 270 and 271 to 360 days, with both underscore and hyphen header spellings. A row with no arrears is stage 1. Future-dated threshold rules are inactive until their effective date; a long-tenor rule can rebut the 31-day trigger (for example stage 2 from 91 days) once activated, which is the RBM-basis rebuttal awaiting sign-off.

MAIIC's audited definition of default is four consecutive missed payments or 90 days past due, and its internal grade ladder maps 1 to 30 days to grade 3, 31 to 90 to grade 6, 91 to 180 to grade 8 and over 180 to grade 10.

Qualitative SICR is configured through `StageingRulesController`, `SicrGroupController`, `SicrItemController` and `SicrTriggerController`: alert items grouped into SICR groups, and triggers that move an exposure to stage 2 regardless of arrears. The result is written to `ifrs9stage_post_qualitative`.

### 5.3 Probability of default

Transition profiles (`TransitionProfileController`) define which tables and grading columns feed a matrix and whether it is count or balance based. `app/Services/TransitionMatrixService.php` builds the monthly matrix: for each grade or stage at the start of the period, the share of exposures (by count or balance) in each grade at the end. The matrix moves through draft, calculated, locked and applied states; locking makes it immutable and applying writes the 12-month PD to `loan_books.pd_prefli`. `app/Services/TransitionMatrixCummulativeService.php` chains monthly matrices into cumulative multi-period probabilities used for lifetime PD.

Internal grades (`InternalGradingController`) map 12-month PD to the A to G master scale: A up to 2 percent, B to 5, C to 10, D to 20, E to 40, F to 100, G default.

### 5.4 Loss given default

Monthly LGD (`LossGiveDefaultController`) computes, per stage, opening balances, recoveries, cures and closing balances over a period. Cure rate is the share of stage 3 exposure restored to performing; recovery rate is the share of defaulted exposure recovered. LGD is one minus the recovery share after collateral. The manual mode accepts user-entered rates. Cumulative LGD (`LossGivenDefaultCummulativeController`) aggregates across periods. Both lock once closed.

Recovery discounting: `app/Jobs/CalculateDiscountingJob.php` discounts the recovery payments of an LGD run at the contract's effective interest rate resolved by `app/Services/Ecl/EclDiscountRateService.php`. The former hardcoded 10 percent fallback has been removed: a payment with no resolvable locked EIR is skipped and counted, never defaulted. `app/Jobs/ProcessLGDPayments.php` builds the long-format payment tracking for stage 3 contracts in chunks of 500 with a two-hour timeout.

Agricultural credit risk mitigation (off-take agreements, warehouse receipts, group guarantees, anchor buyer cover) is modelled by `php artisan ifrs9:seed-agri-risk` and reported in the Credit Risk Mitigation report.

### 5.5 Forward-looking information

The forward-looking (FLI) engine was rebuilt on 13 October 2026 to the FLI module specification (`docs/MAIIC_FLI_Module_Specification_v1_2026-10-13.md`; its section 12 lists what is built). It moves each loan's PD for the economy through one governed chain, with the model applied monthly but recalibrated only when the Governance Centre cadence asks (`fli_model_cadence`, seeded "Once a year, and when an event breaks the relationship"). The code is in `app/Services/Fli/`, with the scenario sets in `app/Services/Scenario/ScenarioSetService.php` and the as-at reader in `app/Support/Fli/AsAtSeries.php`.

```
macro statistics -> macro_series bridge -> credit loss series -> correlation -> fits under the guardrail
-> fit approved (maker, checker) publishes a model version -> scenario set, scenarios and shocks approved
-> overlays reviewed -> fli:apply writes pd_post_fli -> segment ECL on pd_post_fli
-> month-end close: ECL per scenario, weighted against booked -> second-person sign-off
```

Bridge: `FliBridgeService::refresh()` fills the engines' input tables from MAIIC's own and is idempotent. `macro_series` is built from `macro_statistics_data`: an annual value is held at its December and the months between two observations are interpolated and labelled as such; the vintage is the publisher's release date (`macro_statistics_data.published_at`) where recorded, otherwise the date the bridge first held the row; the prime lending rate is added monthly from `reference_rate_series`. `credit_loss_series` receives the proxies derived from the staged loan books (`NPL_RATIO`, `STAGE3_SHARE` and `DEFAULT_RATE_12M`, for the book and per product group) and the external series entered on Credit Loss Data (`macro_credit_loss_data`), each as proxy `EXT_<code>` (or `EXT_<code>_P<portfolio>`) with `known_from` set to its release month. The bridge also writes `governed_parameters` from the Governance Centre, `regression_definitions` (the expected sign of each driver) and `structural_events`. Every engine then reads the series through `AsAtSeries`, which admits only what was knowable at the end of the period: an actual after its publication lag or release date, a forecast only from the date in its vintage (an undated forecast is never knowable for a past period), and an external credit-loss row only from its `known_from` month.

Correlation and fits: `php artisan fli:correlate {period}` (also run from Forward-Looking Model, Correlation Finder, and from Maintenance) refreshes the bridge, profiles every series (`FliSeriesProfiler`, `fli_series_distributions`), sweeps every driver against every proxy over the lag grid (`CorrelationFinder`, writing `analysis_runs` and `fli_suggestions`) and fits the candidates (`RegressionEngine`, writing `fli_relationships` and `fli_fits`). `Guardrail` applies a fit only when it has enough observations (`fli_min_observations`), the expected sign (enforced or advisory under `fli_expected_sign_test`), an R-squared at or above `fli_r2_cutoff` and a p-value within `fli_alpha`; otherwise the fit is declined with the reason. Nothing here touches a PD.

Model versions: on Forward-Looking Model, Regression, one person proposes an applied fit and a different person approves it (`FliRouteService::proposeFit()` and `approveFit()`, `fli_fits.approval_status`). Approval calls `FliModelRegistry::publishFromFit()`, which writes a `fli_model_versions` row under model `PD-FLI-BOOK` (`fli_models`) carrying the fit's driver, proxy, lag, slope, intercept and diagnostics, `calibrated_period` and `effective_from` (by default the fit's own month, never earlier than its calibration month). A version is never edited: a later version published for the same month marks the earlier one SUPERSEDED, and `retire()` marks one RETIRED with a reason. A model calibrated outside the system is proposed on Scenarios & Overlays, External Calculations (`proposeExternal()`, `source` = `external`, with its `evidence`) and published only when a second person approves it on Model Versions (`approveExternal()`). The version in force for a period is `FliModelRegistry::inForce()`: the latest PUBLISHED version whose `effective_from` is on or before the period. The month-end applies that version; it does not re-fit.

Scenario sets: `ScenarioSetService` governs one set per period in `governed_scenario_sets`, versioned through DRAFT, PROPOSED, APPROVED, LOCKED and SUPERSEDED, with its scenarios in `governed_scenarios` (weight, `is_base`, narrative, calibration note) and every non-base scenario expressed as shocks on the base path in `scenario_shocks` (kind `pct`, `abs`, `replace` or `mult` per statistic and forecast year). A set is refused unless it meets the Governance Centre rules (`scenario_minimum_count`, `scenario_weight_bounds`, the calibration note on a downside) and its weights sum to 100; a second person approves it; the lock refuses while an overlay of the period is still proposed. A set can be started from a Scenario Profile, and `php artisan scenario:sets {period}` lists, seeds, approves and locks from the console.

Overlays: `OverlayService` keeps the register `fli_overlays`: scope (book, product group or contract), a signed fraction on the PD, reason, evidence, owner, expiry period, proposer and a different approver. Under the D-F9 double-counting check an overlay whose `risk_driver` is the driver of the model version in force for its month cannot be approved unless `double_count_review` records why the model does not capture it. An overlay is in force from its period to its `expiry_period`.

The route: `FliRouteService::apply()`, run by `php artisan fli:apply {period}`, refuses a locked reporting period and reads the route (`fli_adjustment_route`: Regression, Manual overlay or Regression plus overlay), the weighting (`scenario_weighting_method`) and the transmission method (`fli_transmission_method`, `TransmissionMethodCatalogue`) in force. For each scenario of the approved set it evaluates the version in force, proxy = slope x driver(t - lag) + intercept: the base takes the driver actual `lag` months before the period, the twelve-month window takes the driver `lag` months before its end (an actual when that is on or before the period, otherwise the scenario's path), and the adjustment is the predicted window proxy over the predicted base, less one. A missing lagged actual or path value fails closed for the period. Each loan's PD under each scenario comes from the transmission method; under the seeded weighting ("Weight the ECL across scenarios") the post-FLI PD is the twelve-month PD whose stage PD equals the probability-weighted stage PD across scenarios, so the ECL the engine books on it equals the weighted ECL; the other option weights the scenario adjustments into one. Stage 3 is set to 100 percent. Each loan receives `pd_post_fli`, `fli_adj` (post over pre, less one), `fli_route`, `fli_method`, `fli_fit_id`, `fli_set_id`, `fli_model_version_id`, `fli_by_scenario` (each scenario's weight and PD) and `fli_overlay_ids`, and the run is logged as `FLI Route Applied`. With no version in force under the regression route the PD holds and the loan records why.

Projection and impact: `FliProjectionService::run()` (Forward-Looking Model, Adjusted Forecast, or `fli:month-end --project`) projects, per portfolio and scenario, the conditional, survival, marginal and cumulative PD of each future year into `ecl_pd_term_structures` with one `ecl_projection_runs` row (`methodology_version` = `fli-projection`); inside the horizon of `fli_forecast_horizon` each year takes the scenario path, after it the adjustment reverts in equal steps to nil. It is evidence for Stage 2 lifetime losses; the booked ECL still comes from the ECL engine. `FliImpactService::simulate()` (FLI Month-End, Impact Analysis) measures any set of the month, proposed or approved, against the booked ECL without writing anything.

Month-end close: `FliMonthEnd::steps()` defines the ten steps shown on FLI Month-End, Overview and followed by `fli:month-end`: loan book staged with a PD before FLI; economic data as at the month-end; credit-loss history; model version in force; scenario set approved; overlays reviewed; route applied under the version and set in force; scenario projection (optional); ECL calculated on the post-FLI PD after the route; month recorded and signed off. The ECL step runs `ifrs9:segment-ecl {period} --pd=pd_post_fli` (5.6). `FliCloseService::record()` refuses a month whose ECL is not on the post-FLI PD, was calculated before the route last ran, or mixes scenario sets; it measures each loan under each scenario as its booked ECL re-based from the booked stage PD to the scenario's stage PD (Stage 3 does not move), writes one `ecl_scenario_results` row per loan and scenario, and writes one `fli_run_snapshots` row with the pre-FLI ECL, model and overlay effects, booked and weighted ECL, their difference and `reconciles` (true when the difference is within the greater of one kwacha and one part in a million of the booked ECL), the totals by scenario, stage and portfolio, the inputs it ran under (model version, scenario set, overlays, PD segment runs, ECL runs, loan book loads, macro vintage) and the movement against the previous month-end. A new record of the same month supersedes the old one and its loan rows. `FliCloseService::signOff()` is the second person's approval: it refuses a record that does not reconcile, a sign-off by the person who recorded the month, and a movement above `fli_movement_commentary` without a commentary.

| Menu (Forward-Looking Information) | Tab | Code |
|---|---|---|
| FLI Month-End | Overview, Impact Analysis | `FliModuleController` (`fli.overview`, `fli.impact`), `FliMonthEnd`, `FliCloseService`, `FliImpactService` |
| Macro Statistics | Dashboard, Variables, Data Entry, Scenario Assumptions, Import / Export | `MacroStatisticsController` (`macro-statistics.index`) |
| Forward-Looking Model | Credit Loss Data, Correlation Finder, Regression, Model Versions, Adjusted Forecast, Weighted Forecast | `CreditLossDataController`, `FliCorrelationController`, `FliAdjustmentsController`, `FliModuleController::models()`, `ManualForecastController` (projection), `MacroForecastWeightedController` |
| Scenarios & Overlays | Scenario Profiles, Scenario Sets, Economic Scenarios, Manual Overlays, External Calculations, Calculation History | `ScenariosController`, `ScenarioSetController`, `FLI/ScenarioSetController`, `FliOverlayController`, `FLI/ExternalCalculationsController` |

Viewing needs a login, with Macro Statistics also needing `macro.view` (or `eir.view`) and External Calculations and Calculation History `reports.ifrs9`. Proposing, approving, applying the route, running a step from the Overview, signing off and retiring need `eir.govern`; a Macro Statistics commit or manual entry needs `macro.manage` (or `eir.govern`); the Correlation Finder run needs `eir.run`. Steps launched from the Overview run in the background through the Maintenance command runner (chapter 9.5) and are logged as `FLI Step Launched`. The older one-slope endpoints that wrote `fli_adj` onto the loans outside maker and checker were removed (system audit of 9 October 2026, finding M6); `regression_models` is still read by the stress test's macro mode (5.7) but plays no part in the FLI chain.

### 5.6 ECL calculation

`ExpectedCreditLossController::calculateECL()` runs per reporting period and scope (portfolio, sector or total). Per loan:

```
ECL = EAD x PD x LGD
```

with PD taken from the chosen column (`pd_prefli` or `pd_post_fli`) and LGD from the chosen LGD field. Results are written per loan to `loan_books` and summarised per stage into `expected_credit_loss` with the PD and LGD values used. A run takes a lock per period and scope; a second run while one is in flight is refused with the message that a calculation is already running. `php artisan ifrs9:recalculate-ecl {period}` exposes the same logic from the command line with options for level, portfolio, sector, PD and LGD columns and discounting.

The month-end runs the ECL by segment instead: `php artisan ifrs9:segment-ecl {from} [to] --pd=pd_post_fli` calls `calculateECL()` for every portfolio of the month, after first failing closed, with the loans named, when a loan with an exposure has no measured stage, no PD of the chosen kind or no LGD, or when the loans carry PDs from more than one PD segment run. Each portfolio's old rows for the month are removed in the same transaction, and `--prune-stale` removes the rows of portfolios that no longer hold a loan. This is the ECL step of the forward-looking month-end (5.5) and of `maiic:task month-end` (chapter 9.5).

Discounting: `app/Services/Ecl/EclDiscountingService.php` discounts the loss at the contract's locked original EIR (IFRS 9 paragraph 5.5.17(b)). There is no default rate: a contract without a locked EIR is returned unresolved and named, and every discounted row carries `ecl_discount_rate`, `ecl_discount_rate_source` and `ecl_discount_status`. Floating-rate facilities use the original rate as a proxy for the current rate until reset history exists, and the ECL list shows the basis (fixed original, floating original as proxy, calculated but not approved, or no EIR).

Time-phased engine: `app/Services/Ecl/TimePhasedEclService.php` projects a monthly marginal default and cash shortfall curve across the approved base, upside and downside scenarios and stores per-month PD, survival, EAD, LGD, discount factor and shortfall in `time_phased_ecl` behind the Projections screen (opened from ECL Processing, ECL Calculation). The conditional hazard is anchored to the twelve months the tape PD describes and run for the life of the exposure, so a five-year stage 2 lifetime compounds (a 12 percent annual PD becomes 47.2 percent over five years) and a horizon under a year carries less than the annual figure. Stage 3 exposure does not amortise; where a reviewed recovery plan exists in `ecl_recovery_cashflows`, the shortfall is spread across the plan's dates. Contracts the engine cannot resolve keep the undiscounted figure, so a stage total can mix methodologies until coverage is complete.

`tests/Feature/Ecl/EclGoldenNumberTest.php` pins the expected ECL for a fixed test population; `TimePhasedEclServiceTest.php` covers stage 1, stage 2 lifetime, stage 3 with and without a recovery plan and multi-scenario weighting.

### 5.7 Stress testing

`Reports/StressTestingController.php` is the single stress engine (ticket #003 retired the duplicate hub tile). It recomputes ECL loan by loan:

```
base   = EAD x PD x LGD
stress = EAD x LEAST(1, PD x multiplier[stage]) x LEAST(1, LGD + addon[stage])
```

PD multipliers default to 1 and are floored at 0.01; LGD add-ons are entered in percentage points and floored at zero. Results roll up by stage and by portfolio with base, stressed, delta and delta percent. Macro mode derives one uniform multiplier from a saved regression model or a manual slope and intercept: predicted base equals slope times base macro plus intercept, shocked macro equals base times one plus the shock percent, and the multiplier is one plus the ratio change, floored at 0.01. Scenarios can be saved with a result snapshot. `tests/Feature/StressTestingReconciliationTest.php` checks the driver arithmetic, the caps under extreme shocks, the macro derivation and the retired hub redirect.

### 5.8 Locks and closed states

| Object | Guard |
|---|---|
| Transition matrix, cumulative matrix | Locked matrices cannot be edited; create a new draft instead |
| LGD monthly and cumulative | Closed runs are immutable |
| ECL run | One run per period and scope at a time |
| Financial period | Closing a period prevents postings into it |
| EIR original rate | Locked after maker and checker approval; reopening is administrator-only with a stated reason (chapter 6) |
| FLI model version | Never edited once published; replaced from the same month as SUPERSEDED, or withdrawn as RETIRED with a reason, and the months it governed keep pointing at it |
| Scenario set | Approved by a second person; a LOCKED set is final and a change is a new version with a reason |
| FLI route | Refused for a locked reporting period |
| FLI month record | Signed off by a second person only when the weighted ECL reconciles to the booked ECL and any movement above the tolerance carries a commentary |
