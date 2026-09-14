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

`app/Services/MacroForecastWeightedService.php` produces the probability-weighted forecast of each macro element from the scenario set weights (base, upside, downside). `app/Services/RegressionService.php` fits and applies regression models of historical credit loss against macro variables; the fitted coefficients are stored in `regression_models`. The post-FLI PD is written to `loan_books.pd_post_fli`. Management overlays (Economic Scenarios, External Calculations) let an approved adjustment be applied and recorded with its history.

### 5.6 ECL calculation

`ExpectedCreditLossController::calculateECL()` runs per reporting period and scope (portfolio, sector or total). Per loan:

```
ECL = EAD x PD x LGD
```

with PD taken from the chosen column (`pd_prefli` or `pd_post_fli`) and LGD from the chosen LGD field. Results are written per loan to `loan_books` and summarised per stage into `expected_credit_loss` with the PD and LGD values used. A run takes a lock per period and scope; a second run while one is in flight is refused with the message that a calculation is already running. `php artisan ifrs9:recalculate-ecl {period}` exposes the same logic from the command line with options for level, portfolio, sector, PD and LGD columns and discounting.

Discounting: `app/Services/Ecl/EclDiscountingService.php` discounts the loss at the contract's locked original EIR (IFRS 9 paragraph 5.5.17(b)). There is no default rate: a contract without a locked EIR is returned unresolved and named, and every discounted row carries `ecl_discount_rate`, `ecl_discount_rate_source` and `ecl_discount_status`. Floating-rate facilities use the original rate as a proxy for the current rate until reset history exists, and the ECL list shows the basis (fixed original, floating original as proxy, calculated but not approved, or no EIR).

Time-phased engine: `app/Services/Ecl/TimePhasedEclService.php` projects a monthly marginal default and cash shortfall curve across the approved base, upside and downside scenarios and stores per-month PD, survival, EAD, LGD, discount factor and shortfall in `time_phased_ecl` behind the Projections screen. The conditional hazard is anchored to the twelve months the tape PD describes and run for the life of the exposure, so a five-year stage 2 lifetime compounds (a 12 percent annual PD becomes 47.2 percent over five years) and a horizon under a year carries less than the annual figure. Stage 3 exposure does not amortise; where a reviewed recovery plan exists in `ecl_recovery_cashflows`, the shortfall is spread across the plan's dates. Contracts the engine cannot resolve keep the undiscounted figure, so a stage total can mix methodologies until coverage is complete.

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
