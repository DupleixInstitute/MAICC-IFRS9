## 14. Forward-looking adjustments: the regression, the correlation finder and the manual route

### 14.1 Background: why the economy enters the loss estimate

An expected credit loss is, for each loan, the probability that the borrower defaults (PD) times the share of the exposure that would be lost if they did (LGD) times the exposure at that moment (EAD), discounted at the loan's effective interest rate. Of the three, the PD is the one the economy moves most. MAIIC measures its PDs from its own history through the transition matrices: how often, over the years observed, a loan in a given grade moved to default. That history is the honest starting point, but it is an average over the years that happened to be in the window, good and bad together: a "through-the-cycle" figure. IFRS 9 asks for the probability that applies to the twelve months, or the lifetime, that is now ahead: a "point-in-time" figure that reflects where the economy is and where it is expected to go (5.5.17(c), B5.5.49). The step from the first to the second is the forward-looking adjustment.

The link between the economy and defaults is made with a **regression**: a statistical fit that says how much a credit-loss measure (the share of loans defaulting in a quarter, the NPL ratio, the roll rate from one grade to the next) has moved, historically, for a given move in an economic series (growth, inflation, the exchange rate, the harvest). Three things matter in practice. The relationship is usually **lagged**: a bad harvest shows up in defaults two or three quarters later, not in the same quarter. It is often in **changes** rather than levels: it is the fall in growth, not the level of growth, that moves defaults. And it must make **economic sense**: a fit that says defaults fall as unemployment rises is a statistical accident, however high its R-squared, and must not be used. The regression produces a predicted loss measure for each future period under an assumed path of the economy; the ratio of that prediction to the base period's prediction is the adjustment applied to every loan's PD.

Where the statistics are thin, which they are for an institution of MAIIC's size with two years of core-banking history, the standard allows judgement, provided it is reasonable, supportable and documented (B5.5.52): a management overlay. The sections that follow give both roads and the governance that makes either one auditable.

### 14.2 In plain language

A probability of default measured from MAIIC's own history says how often borrowers like this one have defaulted in the past. IFRS 9 asks for something more: the probability that applies to the economy that is coming, not the one that has been. The "forward-looking adjustment" is the step that turns the historical probability (the pre-FLI PD) into the one the expected credit loss is calculated on (the post-FLI PD). MAIIC's system already does this, and does it in the right order: the adjustment is worked out from a regression of credit losses on the economy, applied to each loan's PD, and the ECL reads the adjusted figure. What is missing is the discipline around it: a way to find which economic series actually explains MAIIC's losses, a test that the relationship makes economic sense before it is used, a way for Finance to apply an adjustment by judgement when the statistics cannot, and a record on each loan of where its adjustment came from. Decision D28 adds those four things and changes none of the arithmetic that already works.

### 14.3 How the adjustment works today

The chain, as the code runs it:

| Step | What happens | Where |
|---|---|---|
| 1 | A **regression** relates a credit-loss proxy (an observed default rate, an NPL ratio) to one or more macro series over a training window; the model stores its coefficients, R-squared and window, and can be marked approved | `RegressionService`, `regression_models` |
| 2 | For a reporting period and a scenario set, a **parameter record** names the macro statistic, the PD proxy, the base period's values and a single slope and intercept | `fli_reporting_periods_parameters` |
| 3 | For each **forecast window** (0, 1, 2 … periods ahead) the scenario-weighted macro value is entered; the predicted proxy is slope × value + intercept; the **adjustment** is the predicted value divided by the base window's predicted value, less one: a relative uplift on the base period | `fli_adj` |
| 4 | **Applied to loans**: Stage 3 is set to 100 percent; Stage 1 takes the 12-month window; Stage 2 takes the window nearest its remaining life; post-FLI PD = pre-FLI PD × (1 + adjustment), floored at 0 and capped at 100 percent | `ExternalCalculationsController::applyToLoans` |
| 5 | The **ECL** reads the post-FLI PD; the ECL recalculation is forbidden from writing it back, because the FLI engine owns it | `TimePhasedEclService`, `RecalculateEcl --pd=pd_post_fli` |

Steps 4 and 5 are sound and tested and are kept exactly as they are. The weaknesses are in steps 1 to 3:

- **One variable is applied even when several were trained.** The parameter record holds one slope and one intercept, so the model that reaches the loans is univariate whatever the approved model was.
- **No test of sense.** A model can be approved whose coefficient has the wrong sign (losses falling as unemployment rises) or whose R-squared is negligible; nothing stops it.
- **No lags, no transforms.** The economy of this quarter is regressed on the losses of this quarter; the link is usually lagged, and often in changes rather than levels.
- **Only one road.** The only way to a post-FLI PD is the regression. There is no screen on which Finance can say "for this quarter, plus fifteen percent on Stage 2 agriculture, because of the drought, approved by two people", which is what a small institution actually needs and what B5.5.52 contemplates.
- **No finder.** The analyst must already know which series to regress on.
- **No lineage on the loan.** The loan row holds the number, not which model, parameter set and scenario produced it.

### 14.4 The correlation finder

The finder answers the first question an analyst has: which economic series, at what lag, in what form, explains MAIIC's losses, and how well. It is the Dupleix suite's "auto-correlate", ported.

- **The sweep.** Every active macro series (section 13) against every credit-loss proxy MAIIC holds (observed default rate by product group, NPL ratio, the roll rates behind the transition matrices), across a governed lag grid (0, 3, 6, 9 and 12 months) and three transforms (level, change, change in logarithm). For each combination it computes a rank correlation first (Spearman, which is robust to the odd year) and a robust slope (Theil-Sen), then Pearson's r and a one-variable least-squares fit.
- **The ranking.** Each pair is scored on strength, sign agreement with what economics expects, overlap length and whether the series can be forecast (a series no source projects cannot drive a forward-looking adjustment, and is ranked down for it). One suggestion per pair at its best lag, with a plain-language reason: "Agriculture value added, lagged two quarters, change: Spearman −0.71 over 28 quarters; sign as expected; forecastable from the IMF".
- **Fail-closed.** A pair with too little overlap is rejected with the reason, never fabricated; a sweep with no proxy data produces no suggestions and says so.
- **Immutable runs.** Every sweep is an analysis run with the hash of the inputs it read, so a suggestion can be reproduced from the data vintage; a later sweep is a new run, not an edit.
- **The tests, governed.** Two thresholds from the Governance Centre decide what may go forward: the **expected sign** per pair (positive, negative or not stated) and an **R-squared cut-off**. A pair that fails either is shown in red with the reason, as the earlier Dupleix calculator did it; it may still be studied, but it cannot be approved as a model.

### 14.5 The regression, repaired

- **The model that is trained is the model that is applied.** The parameter record stores the approved model's id and its full coefficient vector; the predicted proxy uses every variable in it, each at its own lag and transform.
- **Approval needs the tests and two people.** A model may be approved only if it passes the sign and cut-off tests of 14.4, and approval is maker-checker: the person who trained it cannot approve it. An approved model is versioned; a retrained model is a new version and the old one stays attached to the periods that used it.
- **Back-test on the row.** Each period, the model's predicted proxy for the period just ended is compared with the realised proxy and the difference stored, so a model that has stopped working is visible before it is reused.

### 14.6 Three routes to the adjustment, one governed choice

A Governance Centre setting, `fli_adjustment_route`, says how the adjustment is produced for a period. All three routes write the same adjustment rows, so steps 4 and 5 of 14.3 do not change.

| Route | What it is | When it is the right one |
|---|---|---|
| **Regression** (seeded default) | The chain of 14.2 with the repairs of 14.5: the approved model, the scenario paths of section 15, the predicted proxy per window, the relative uplift | When an approved model exists and passes its back-test |
| **Manual overlay** | A screen on which a reviewer enters the adjustment per scenario and window and, optionally, per product group or stage, with a reason and an attachment; a second person approves; the overlay has an owner and an expiry date | When no model is approvable, or when an event the statistics cannot see (a drought, a devaluation, a policy change) has to be reflected now |
| **Regression plus overlay** | The regression result, then a manual adjustment on top; each shown separately on the loan and in the ECL | When the model is sound but judgement says it is not enough |

The manual overlay is a register, not a free field: every entry carries its scope, reason, evidence, owner, expiry, proposer and approver, and the ECL shows the overlay as its own line, which is how the auditor and the Board see what judgement added.

### 14.7 Lineage on the loan

Four columns are added to the loan-book row beside the adjustment and the post-FLI PD: the route used, the parameter record, the model version and the scenario set. The ECL report and the impairment audit workbook can then say, for every loan, which model, which overlay, which scenario and which approval produced its post-FLI PD. The scenario-weighted macro value of step 3, typed today, becomes computed and shown: the row says which scenarios, at which weights, gave the figure, and the three values behind it.

### 14.8 Where it lives, and the settings

Financial Modelling › Forward-Looking Model: **Correlation Finder** (new), **Regression Analysis** (repaired), **FLI Adjustments** (new: the route, the regression result, the overlay register, the apply-to-loans step with its counts), **Weighted Forecast** (now computed from the scenario set). The three settings in the Governance Centre (section 4.2): `fli_adjustment_route` (seeded: Regression), `fli_expected_sign_test` (seeded: Required), `fli_r2_cutoff` (seeded: 30 percent, a common starting threshold for annual macro data; MAIIC may tighten it). The finder's runs, the approved models and the overlays are cited in the impairment audit workbook's rows for B5.5.49 to B5.5.54.

### 14.9 Acceptance and order of work

1. The finder sweeps every series and proxy and ranks suggestions with reasons; a pair with fewer than the governed minimum of overlapping periods is rejected with the reason.
2. A model that fails the sign or cut-off test cannot be approved; approval needs a second person; the applied prediction uses every coefficient of the approved model.
3. Each route produces adjustment rows; the loans' post-FLI PDs equal pre-FLI × (1 + adjustment), floored and capped, Stage 3 at 100 percent, as today.
4. Every loan row names its route, parameter record, model version and scenario set; the ECL shows the overlay as its own line.
5. The existing ECL tests pass unchanged on the regression route.

FL-1 the finder (one day); FL-2 the regression repair with its tests (one day); FL-3 the FLI Adjustments screen, the overlay register and the route setting (one day); FL-4 lineage, the computed weighting, the back-test and the workbook rows (one day). FL-1 needs the macro series of section 13; the rest can follow P4b.

## 15. Economic scenarios: governance and incorporation

### 15.1 Background: what a scenario is, and why one is not enough

A scenario is a coherent story about the economy over the next few years, written down as a path for each macro series: growth, inflation, the exchange rate, interest rates, the harvest. The forward-looking adjustment of section 14 needs such a path to predict from; a single "most likely" path would be the natural choice, and it is the wrong one. Credit losses are not symmetrical: a year that is one notch worse than expected costs far more in defaults than a year one notch better saves, because borrowers who were already stretched tip into default while those who were comfortable merely become more comfortable. Calculating the loss on the single most likely path therefore understates the expected loss. IFRS 9 recognises this and asks for an unbiased, probability-weighted estimate over a range of possible outcomes (5.5.17(a), B5.5.42): the loss under each of several scenarios, each weighted by how likely it is, and the weighted average reported.

Scenarios for the ECL are not the same thing as stress tests. A stress test asks what happens under a severe but plausible shock, with no weight attached, to see whether capital would hold; its scenarios are deliberately harsh. ECL scenarios are a probability-weighted view of what is actually expected, in which the severe case carries a small weight and the base case a large one. The two should share their economics (the same base path, the same understanding of what a devaluation does to the book) but they answer different questions, and a system that uses one set for both will be wrong for one of them.

The weights are the part that attracts the most scrutiny, because a small change in a weight can move the provision materially and nothing in the data pins them down. Good practice therefore does three things: anchors each scenario's severity to something that has actually happened, so that "a downside" is not an abstraction but "2023 again"; sets the weights by a documented judgement that two accountable people approve; and tests afterwards whether the realised year fell inside the range the scenarios spanned, so that weights that were too optimistic are seen and revised. For Malawi the anchors are not hard to find: the 2023 devaluation of 44 percent, the 2016 drought, the 2012 float, and the 2021 to 2022 recovery each give a path that the institution's own loan book has already been through.

### 15.2 In plain language

The forward-looking adjustment depends on what the economy is assumed to do. IFRS 9 does not let that be one guess: it asks for an unbiased, probability-weighted view across a range of possible outcomes (5.5.17(a), B5.5.42). In practice that means a base view, a better one and a worse one, each with a story, a set of figures and a weight, agreed by the people accountable for it and kept once the period's numbers are struck. MAIIC's system holds scenarios today, but in two unconnected places, without a period, a version, a narrative, a source, an approval or a lock, and it uses them at the wrong point: it averages the economy and calculates one loss, where the standard asks for the loss under each economy, then the average. Decision D29 makes the scenario set a governed object and moves the weighting to where it belongs.

### 15.3 What good practice asks for

| Principle | Where it comes from | What it means for MAIIC |
|---|---|---|
| Unbiased and probability-weighted, over a range of outcomes | IFRS 9 5.5.17(a), B5.5.42 | At least a base, an upside and a downside, with weights that sum to 100; the ECL is the weighted average of the ECL under each scenario, not the ECL at the average economy |
| Reasonable and supportable, without undue cost or effort | B5.5.49 to B5.5.54 | Paths from published sources (the IMF World Economic Outlook, the World Bank, the Reserve Bank of Malawi), with the vintage recorded; judgement written down where the sources stop |
| Governed | The Basel Committee's guidance on credit risk and accounting for expected credit losses, principles 2 and 5; the auditors' expectation | Approved by two people, one from Finance and one from Risk; locked with the period's ECL; changed only by a new version with a reason; reviewed at least once a year |
| Severity calibrated to history | Common practice | The downside is anchored to what Malawi has lived through: the 2023 devaluation, the 2016 drought, the 2012 float; the set says which history each scenario is calibrated to |
| One house view | Basel guidance; audit consistency | The same scenario set drives the ECL, the sensitivity disclosures, the stress test and the budget; a different set for each is a finding |
| Disclosed | IFRS 7.35G | The narratives, the weights, the key paths per scenario, the ECL under each scenario and with 100 percent weight on each, and the sensitivity to the weights |
| Overlays governed, not hidden | Basel guidance; B5.5.52 | A management overlay sits on an approved set, with a reason, an owner, an expiry and a second approval, and is shown separately |
| Back-tested | Model-risk practice | Each period the previous set's base path is compared with what happened, and the predicted default rate with the realised; a miss outside the range triggers a review of the weights |

### 15.4 The scenario set, as a governed object

The two existing structures (`scenario_profiles` with its child scenarios, and `scenario_sets` with `scenario_probabilities`) become one, and a migration maps what is there.

**The set**, one per reporting period: period; name; version; status (draft, proposed, approved, locked); the narrative; the source vintage ("IMF WEO April 2026; World Bank 2025 actuals; RBM Monetary Policy Committee, March 2026"); proposed by; approved by (two people); locked at. A set is locked when the period's ECL is locked; a change after that is a new version with a reason, and the locked version stays attached to the period.

**The scenarios** under it, three or more: name; probability weight (the weights must sum to 100); the narrative; the calibration note that says which history the scenario is anchored to; and the **paths**: for each macro series and each horizon, the base scenario's path comes from the sources of section 13, and every other scenario is expressed as a governed **shock** on the base, by series and year offset, of one of four kinds: a percentage change, an absolute change, a replacement value, or a multiplier. A downside is therefore an auditable transformation of the base, not a second set of typed numbers; change the base and the shocked paths follow.

### 15.5 Weighting the loss, not the economy

A Governance Centre setting, `scenario_weighting_method`, with two options:

| Option | Meaning |
|---|---|
| **Weight the ECL across scenarios** (seeded) | The forward-looking chain of section 14 runs once per scenario; each loan carries a PD and an ECL under each scenario; the reported ECL is the probability-weighted sum. This captures non-linearity: a downside hurts more than an upside helps, and the average of the losses is higher than the loss at the average |
| Weight the macro path, one ECL | Today's method: the scenario-weighted macro value, one regression, one ECL. Kept so that past periods can be reproduced and the two methods reconciled when the switch is made |

Switching is a governed change with an effective date; the first period run under the new method shows both figures and the difference, which is itself a disclosure the auditors will want.

### 15.6 The rules, as settings

In the Governance Centre: the minimum number of scenarios (seeded 3); a floor on the base scenario's weight (seeded 40 percent) and a ceiling on any single weight (seeded 60 percent); whether a calibration note is required on every downside (seeded Required); the annual review month; and the rule that an overlay needs an approved set (seeded Required). Each is changed under maker-checker with a reason, like every other setting.

### 15.7 Overlays, back-tests and sensitivity

- **The overlay register** of section 14.5 is tied to the scenario set: an overlay names the set it sits on, and a set cannot be locked with an unapproved overlay against it. Each overlay is shown as its own line in the ECL and in the disclosure.
- **Back-test, every period**: for each macro series, the previous set's base path against the actual now known; for each proxy, the predicted default rate against the realised. The results are stored with the set, and a miss outside the scenario range raises a flag that the weights are to be reviewed before the next set is proposed.
- **Sensitivity, every period**: the ECL under each scenario; the ECL with 100 percent weight on each; the ECL with ten points moved from the base to the downside and to the upside. Stored with the set, so the IFRS 7.35G tables are a report from the system, not a spreadsheet beside it.

### 15.8 The first set, proposed

For MAIIC the first set is seeded as a proposal for Dr Thom, every scenario anchored to a year Malawi has lived through:

| Scenario | Weight | Anchored to |
|---|---|---|
| Base | 50 | The IMF World Economic Outlook path, World Bank actuals, the RBM's stated policy path |
| Upside | 15 | The 2021 to 2022 recovery: growth and the harvest above the base, inflation below |
| Downside | 25 | The 2023 devaluation year: the kwacha down by 44 percent, inflation and lending rates up |
| Severe | 10 | The 2016 drought together with a devaluation: the harvest fails and the currency falls |

The weights are a starting point and are his to set; the anchors are the answer to the question "why these".

### 15.9 Where it lives

Governance Centre › **Scenario Sets** (create, propose, approve, lock, versions, history, the back-test and sensitivity stored with each); Data Foundation › Macro Statistics › **Scenario Assumptions** (the base paths and the shocks); Financial Modelling › Forward-Looking Model › **FLI Adjustments** (the chain run per scenario); Report Hub › **IFRS 9 Disclosure** (the 7.35G tables) and the impairment audit workbook, which carries one row per principle of 15.3 citing the set in force.

### 15.10 Acceptance and order of work

1. A set cannot be proposed with weights that do not sum to 100, fewer scenarios than the minimum, or a downside without its calibration note; it cannot be approved by its proposer; it cannot be changed after lock except as a new version with a reason.
2. Every non-base path equals the base path transformed by its recorded shocks, and changing the base re-derives them.
3. Under the seeded weighting method, every loan carries a PD and an ECL per scenario and the reported ECL is the weighted sum; under the other method the figure equals today's.
4. The back-test and the sensitivity tables are produced for a locked period and agree with a hand calculation on a sample.
5. The disclosure report prints the narratives, weights, paths, per-scenario ECL and weight sensitivity of the set in force.

SC-1 the set, the scenarios, the shocks, the migration from the two old structures and the governance rules (two days); SC-2 the ECL per scenario and the weighting method (one day); SC-3 the overlay tie, the back-test, the sensitivity and the disclosure tables (one day); SC-4 the first set seeded as a proposal (half a day). SC-1 follows section 13; SC-2 follows FL-3.
