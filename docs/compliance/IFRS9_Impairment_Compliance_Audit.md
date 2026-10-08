# IFRS 9 - impairment - compliance audit

IFRS 9 Financial Instruments - impairment (5.5 and B5.5): staging, significant increase in credit risk, 12-month and lifetime expected credit losses, forward-looking information, write-off.

Malawi Agricultural and Industrial Investment Company (MAIIC) - IFRS 9 EIR and ECL system - build of 8 October 2026 against specification v4. Prepared 8 October 2026 from the build of specification v4: the staged loan books of July 2024 to August 2026 on the clean install, the Governance Centre's staging and forward-looking settings, the forward-looking chain (fli:correlate), the scenario set of 15.8, and the PHPUnit suite. The PD engine (transition matrices) has not run on the clean install, so the ECL figures there are nil; the rows below audit the mechanics and the settings. Reviewer: Dr Thomson Kumwenda (CFO), then Deloitte. The Excel workbook of the same name is the working copy (drop-down statuses, hyperlinked contents, reviewer sign-off column, a live Baselines sheet); this document is its printable twin.

Source: IFRS 9 Financial Instruments as issued by the IASB, section 5.5 and B5.5.1 to B5.5.55. The Reserve Bank of Malawi Financial Services (Credit Risk Management for Development Finance Institutions) Directive 2018 (docs/regulatory). Specification: docs/MAIIC_EIR_Engine_Specification_v4_2026-10-07.md sections 3.6, 13, 14, 15, 16.

## Status summary

| Status | Meaning | Sections |
|---|---|---|
| **Done** | Implemented in the system and visible in an approved output; the test in column 11 fails if the behaviour changes. | 9 |
| **Partially done** | The mechanics are in place, but a parameter, an input or a piece of evidence still differs from the requirement. | 2 |
| **Outstanding** | Required for MAIIC and not built. | 0 |
| **Not applicable, documented** | Does not apply to MAIIC by a recorded decision or fact (reason and reference given); revisit if the facts change. | 0 |
| **Evidence needed from MAIIC** | The system is ready; MAIIC must supply a document, minute or dataset before the row can be closed. | 3 |
| **Total** | | 14 |

## Contents

| Ref | Section | Status | Governance setting | Test |
|---|---|---|---|---|
| **5.5 RECOGNITION OF EXPECTED CREDIT LOSSES** | | | | |
| 5.5.1 | A loss allowance for expected credit losses | Done |  | Tests\Feature\Ecl\TimePhasedEclServiceTest; Tests\Feature\Ecl\EclDiscountRateServiceTest |
| 5.5.3 | Lifetime ECL on a significant increase in credit risk | Done | dpd_basis | Tests\Feature\Eir\StagingServiceTest |
| 5.5.5 | 12-month ECL where credit risk has not increased significantly | Done |  | Tests\Feature\Ecl\TimePhasedEclServiceTest |
| 5.5.9 to 5.5.11 | Determining a significant increase in credit risk; the 30-days-past-due rebuttable presumption | Done | staging_rebuttal; dpd_basis | Tests\Feature\Eir\StagingThresholdSeederTest; Tests\Feature\Eir\StagingServiceTest |
| B5.5.37 | Definition of default; the 90-days-past-due rebuttable presumption | Done | dpd_basis; stage3_missed_instalments; staging_rebuttal | Tests\Feature\Eir\StagingServiceTest::test_the_directive_thresholds_by_tenor_class_and_the_bucket_fallback |
| **5.5 MEASUREMENT** | | | | |
| 5.5.17 | Measurement of expected credit losses | Partially done | scenario_weighting_method; fli_adjustment_route | Tests\Feature\Scenario\ScenarioSetServiceTest; Tests\Feature\Ecl\EclDiscountRateServiceTest |
| B5.5.41 to B5.5.43 | Probability-weighted outcomes | Done | scenario_minimum_count; scenario_weight_bounds; scenario_calibration_note | Tests\Feature\Scenario\ScenarioSetServiceTest |
| B5.5.49 to B5.5.54 | Reasonable and supportable information; forward-looking information | Done | fli_expected_sign_test; fli_r2_cutoff; fli_min_observations; fli_alpha; macro_source_precedence | Tests\Feature\FLI\FliBridgeServiceTest |
| B5.5.52 (transmission) | How forward-looking information reaches the PD | Done | fli_transmission_method; fli_asset_correlation | Tests\Feature\FLI\TransmissionMethodCatalogueTest |
| B5.5.28 to B5.5.30 | Collective assessment and segmentation | Partially done | fli_transmission_method | Tests\Feature\FLI\FliBridgeServiceTest |
| B5.5.33 to B5.5.35 | Loss given default and collateral | Evidence needed from MAIIC |  |  |
| 5.5.17(b), B5.5.44 | Time value of money | Done |  | Tests\Feature\Ecl\EclDiscountRateServiceTest |
| **5.5 WRITE-OFF AND PRESENTATION** | | | | |
| 5.4.4, B5.4.9 | Write-off | Evidence needed from MAIIC |  |  |
| **THE MEGA FARM PROGRAMME** | | | | |
| 16 | The Mega Farm loans in the allowance | Evidence needed from MAIIC | mega_farms_scope; megafarm_pd_method; megafarm_scalar_ceiling |  |

## Baselines - the acceptance ties read from the system

Generated 2026-10-08 21:40:05 from maiic_ifrs9_bootstrap.

| Tie | Expected | System figure | Result |
|---|---|---|---|
| Interest posted Jan 2025 to Jul 2026 (ledger, types 303 and 120) | 5,293,988,207.06 | 5,293,988,207.06 | PASS |
| Derived carrying amount against the stored report (over 2 cents) | 0 differences | 0 of 2361 account-months | PASS |
| Year-end interest batch contra on 4215 | 32,956,675.55 | 32,956,675.55 | PASS |
| Year-end interest batch contra on 4216 | -21,733,262.70 | -21,733,262.70 | PASS |
| Loan book 1050101 at 31 Dec 2025 against the trial balance | 1,368,737,810.68 | 1,368,737,810.68 | PASS |
| Loan book 1050102 at 31 Dec 2025 against the trial balance | 6,434,045,052.56 | 6,434,045,052.56 | PASS |
| Loan book 1050201 at 31 Dec 2025 against the trial balance (accepted exception, spec 3.5: -400,000.00) | 1,402,393,139.46 | 1,401,993,139.46 | PASS |
| Loan book 1050202 at 31 Dec 2025 against the trial balance (accepted exception, spec 3.5: 1,000,000.00) | 3,808,288,656.15 | 3,809,288,656.15 | PASS |
| Loan book 1050401 at 31 Dec 2025 against the trial balance | 1,827,148,120.69 | 1,827,148,120.69 | PASS |

## Findings - items that need a decision or a fix

### F1. The PD and LGD of the clean install are the bootstrap's, not MAIIC's (5.5.17) - Open

- **What was found:** PdEngineService and LgdEngineService run the transition matrix and the cohort workout under the bootstrap label; no MAIIC reviewer has key-locked a matrix or an LGD calculation in the system.
- **Impact:** The ECL of 8.33bn at August 2026 is a system figure awaiting review.
- **Recommended action:** A reviewer key-locks the matrix and the LGD on their screens; the ECL is then re-run under the approved scenario set.
- **Owner:** Dr Thom / the reviewer

### F2. No fit is approved by a MAIIC reviewer (B5.5.49) - Open

- **What was found:** The chain applied 39 fits by the guardrail; approval of a fit for use in the FLI route is a maker-checker decision not yet taken.
- **Impact:** The forward-looking adjustment is the manual overlay at zero until then.
- **Recommended action:** A reviewer approves a fit on the FLI Adjustments screen; a second person reviews.
- **Owner:** Dr Thom / the reviewer

### F3. The Mega Farm provision basis is undocumented in the system (16) - Open

- **What was found:** The 2025 statements carry K39.76 bn against K48.7 bn Stage 3; the November 2025 book in the system is mostly Stage 1 by days past due.
- **Impact:** The ECL golden number at 31 December 2025 cannot be set.
- **Recommended action:** The CFO confirms D30 and the provisioning method; the monthly Mega Farm books are loaded by the feed (MF_01 to MF_08).
- **Owner:** Dr Thom / Barry

## Section-by-section audit

### 5.5 RECOGNITION OF EXPECTED CREDIT LOSSES

**5.5.1. A loss allowance for expected credit losses** - *Done*

- **What it requires:** Recognise a loss allowance for expected credit losses on a financial asset measured at amortised cost.
- **What the engine does:** TimePhasedEclService computes the allowance per loan as EAD x PD x LGD, discounted at the EIR, for the period; ifrs9:recalculate-ecl runs it per portfolio; the bootstrap runs it for the last period.
- **Compliance comment:** Compliant in mechanics; the PD engine must run for a figure.
- **Where to see it:** Financial Modelling, ECL Calculation: /expected-credit-loss
- **Test that proves it:** `Tests\Feature\Ecl\TimePhasedEclServiceTest; Tests\Feature\Ecl\EclDiscountRateServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**5.5.3. Lifetime ECL on a significant increase in credit risk** - *Done*

- **What it requires:** At each reporting date, measure the loss allowance at lifetime ECL if the credit risk has increased significantly since initial recognition.
- **What the engine does:** StagingService stages every period: Stage 2 from 31 days past due in every class (the directive), the SICR flag from the qualitative groups, and the ECL takes the lifetime window for Stage 2.
- **General comment:** Spec v4 section 3.6, decision D31.
- **Compliance comment:** Compliant.
- **Where to see it:** Governance Centre, Staging & SICR Rules: /stageing-rules; Data Foundation, Loan Book
- **Governance setting:** `dpd_basis`
- **Test that proves it:** `Tests\Feature\Eir\StagingServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**5.5.5. 12-month ECL where credit risk has not increased significantly** - *Done*

- **What it requires:** Measure the loss allowance at an amount equal to 12-month ECL where the credit risk has not increased significantly since initial recognition.
- **What the engine does:** Stage 1 loans take the 12-month PD window in the ECL service; the staging writes the stage every period.
- **Compliance comment:** Compliant.
- **Where to see it:** Financial Modelling, ECL Calculation: /expected-credit-loss
- **Test that proves it:** `Tests\Feature\Ecl\TimePhasedEclServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**5.5.9 to 5.5.11. Determining a significant increase in credit risk; the 30-days-past-due rebuttable presumption** - *Done*

- **What it requires:** Assess the change in the risk of default since initial recognition using reasonable and supportable information; there is a rebuttable presumption that credit risk has increased significantly when payments are more than 30 days past due.
- **What the engine does:** Stage 2 from 31 days past due in every facility class; days counted from the oldest overdue instalment as the directive counts, with the bucket ageing beside it; a rebuttal of the presumption is a governed setting (staging_rebuttal) whose seeded value cites the directive; the LONG_TERM rule proposing 91 days is seeded future-dated and inactive pending CFO sign-off.
- **General comment:** The O13 rebuttal paper of 8 October.
- **Compliance comment:** Compliant; the presumption is applied, the rebuttal is governed.
- **Where to see it:** Governance Centre, Staging & SICR Rules: /stageing-rules; Data Foundation, Loan Book; Governance Centre: /eir-governance
- **Governance setting:** `staging_rebuttal; dpd_basis`
- **Test that proves it:** `Tests\Feature\Eir\StagingThresholdSeederTest; Tests\Feature\Eir\StagingServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**B5.5.37. Definition of default; the 90-days-past-due rebuttable presumption** - *Done*

- **What it requires:** Apply a definition of default consistent with the one used for internal credit risk management; there is a rebuttable presumption that default occurs no later than 90 days past due unless reasonable and supportable information supports a more lagging criterion.
- **What the engine does:** Stage 3 from 91 days for a facility of 12 months or less and for the Mega Farm class, and from 181 days for a medium- or long-term facility, as the RBM directive classifies them; the accounting policy's four-consecutive-missed-instalments trigger lifts a loan to Stage 3 beside the day count. The more lagging criterion rests on the directive, cited on each threshold row.
- **General comment:** Spec v4 section 3.6 compares the policy (90 days), the November 2025 model (181) and the directive (181/91).
- **Compliance comment:** Compliant; the rebuttal is documented on the threshold rows.
- **Where to see it:** Governance Centre, Staging & SICR Rules: /stageing-rules; Data Foundation, Loan Book
- **Governance setting:** `dpd_basis; stage3_missed_instalments; staging_rebuttal`
- **Test that proves it:** `Tests\Feature\Eir\StagingServiceTest::test_the_directive_thresholds_by_tenor_class_and_the_bucket_fallback`
- **Reviewer sign-off:** ____________________   Date: ____________

### 5.5 MEASUREMENT

**5.5.17. Measurement of expected credit losses** - *Partially done*

- **What it requires:** ECL is an unbiased, probability-weighted amount determined by evaluating a range of possible outcomes, the time value of money, and reasonable and supportable information about past events, current conditions and forecasts of future economic conditions.
- **What the engine does:** Probability weighting across scenarios is built as the governed weighting method (the ECL under each scenario, weighted; sensitivity stored with the set); discounting is at the EIR; the forward-looking chain produces fits from the macro series. The PD engine has not run on the clean install and no fit is approved by a MAIIC reviewer, so the weighted ECL is not yet produced for a period.
- **General comment:** Spec v4 sections 14, 15.
- **Compliance comment:** Partially done: the mechanics are in place; the PD run and an approved fit are awaited.
- **Where to see it:** Financial Modelling, ECL Calculation: /expected-credit-loss; Governance Centre, Scenario Sets: /scenario-sets
- **Governance setting:** `scenario_weighting_method; fli_adjustment_route`
- **Test that proves it:** `Tests\Feature\Scenario\ScenarioSetServiceTest; Tests\Feature\Ecl\EclDiscountRateServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**B5.5.41 to B5.5.43. Probability-weighted outcomes** - *Done*

- **What it requires:** The estimate reflects the possibility that a credit loss occurs and the possibility that none occurs; a range of scenarios, at least two, weighted by their probabilities.
- **What the engine does:** The governed scenario set: three or more scenarios, weights summing to 100, the base at least 40 percent and no single weight above 60, every scenario but the base a shock on the base path; the first set (Base 50, Upside 15, Downside 25, Severe 10) is seeded as a proposal for the CFO, each anchored to a year Malawi has lived through.
- **General comment:** Spec v4 section 15.
- **Compliance comment:** Compliant.
- **Where to see it:** Governance Centre, Scenario Sets: /scenario-sets
- **Governance setting:** `scenario_minimum_count; scenario_weight_bounds; scenario_calibration_note`
- **Test that proves it:** `Tests\Feature\Scenario\ScenarioSetServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**B5.5.49 to B5.5.54. Reasonable and supportable information; forward-looking information** - *Done*

- **What it requires:** Use information that is reasonably available without undue cost or effort, including forecasts of future economic conditions; where a statistical relationship is used it must be supportable.
- **What the engine does:** Eleven Malawi macro series from the World Bank with their provenance (source, address, fetched-at, who) and a committed snapshot; the forward-looking chain sweeps every driver against every credit-loss proxy over the lag grid under the guardrail (expected sign, R2 cutoff, p-value, minimum observations) and declines a fit that fails with the reason; the structural-events register (the 2023 devaluation, COVID, the policy-rate cycle, the E-Banker take-on) is read by the diagnostics.
- **General comment:** On the clean install: 209 fits, 39 applied by the guardrail, 170 declined.
- **Compliance comment:** Compliant; an applied fit is a proposal under maker-checker.
- **Where to see it:** Financial Modelling, Forward-Looking Model: /regression; fli:correlate; fli:method-cards; Data Foundation, Macro Statistics
- **Governance setting:** `fli_expected_sign_test; fli_r2_cutoff; fli_min_observations; fli_alpha; macro_source_precedence`
- **Test that proves it:** `Tests\Feature\FLI\FliBridgeServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**B5.5.52 (transmission). How forward-looking information reaches the PD** - *Done*

- **What it requires:** The method of adjusting the PD for the forecast must be documented and supportable.
- **What the engine does:** Five governed transmission methods, each with a card: what it does, the formula, what it implies, its preconditions checked live against the data with the deciding figure, and a worked example on one loan; a method whose preconditions are not met cannot be selected; the scalar is seeded because the history cannot yet support the richer methods.
- **General comment:** Spec v4 section 14.7.
- **Compliance comment:** Compliant.
- **Where to see it:** Financial Modelling, Forward-Looking Model: /regression; fli:correlate; fli:method-cards; Governance Centre, the cards inline on the setting: /eir-governance; fli:method-cards
- **Governance setting:** `fli_transmission_method; fli_asset_correlation`
- **Test that proves it:** `Tests\Feature\FLI\TransmissionMethodCatalogueTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**B5.5.28 to B5.5.30. Collective assessment and segmentation** - *Partially done*

- **What it requires:** Where information is not available at the instrument level, assess on a collective basis grouped by shared credit-risk characteristics.
- **What the engine does:** Credit-loss proxies are produced for the book and per product group (NPL ratio, Stage 3 share, 12-month default rate), and the segment-specific scalar is a governed method; the PD transition matrices by grade run from their screen and are not yet in the bootstrap chain.
- **Compliance comment:** Partially done.
- **Where to see it:** Financial Modelling, Forward-Looking Model: /regression; fli:correlate; fli:method-cards; Financial Modelling, PD Model
- **Governance setting:** `fli_transmission_method`
- **Test that proves it:** `Tests\Feature\FLI\FliBridgeServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**B5.5.33 to B5.5.35. Loss given default and collateral** - *Evidence needed from MAIIC*

- **What it requires:** The cash shortfalls reflect the cash flows expected from collateral and other credit enhancements that are part of the contractual terms.
- **What the engine does:** LGD runs from the LGD screens (monthly and cumulative); the security register (P2_11, 185 rows) is landed and 31 active accounts carry no security on it (the request to Credit of 7 October).
- **Compliance comment:** Evidence needed: the collateral for the 31 accounts; the valuations.
- **Where to see it:** Financial Modelling, LGD Model; Data Foundation, Collateral Register
- **Reviewer sign-off:** ____________________   Date: ____________

**5.5.17(b), B5.5.44. Time value of money** - *Done*

- **What it requires:** Discount the expected credit losses at the EIR determined at initial recognition.
- **What the engine does:** EclDiscountRateService takes the locked EIR of the loan (the contractual rate where none is locked, and says so on the row); the bootstrap solves and locks the EIR before the ECL for this reason.
- **Compliance comment:** Compliant.
- **Where to see it:** Financial Modelling, ECL Calculation: /expected-credit-loss
- **Test that proves it:** `Tests\Feature\Ecl\EclDiscountRateServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

### 5.5 WRITE-OFF AND PRESENTATION

**5.4.4, B5.4.9. Write-off** - *Evidence needed from MAIIC*

- **What it requires:** Write off the gross carrying amount when there is no reasonable expectation of recovery.
- **What the engine does:** No write-off has been posted in E-Banker since the take-on (DD_16i returned no rows); the policy is MAIIC's to state.
- **Compliance comment:** Evidence needed: the write-off policy.
- **Where to see it:** Financial Modelling, ECL Calculation: /expected-credit-loss
- **Reviewer sign-off:** ____________________   Date: ____________

### THE MEGA FARM PROGRAMME

**16. The Mega Farm loans in the allowance** - *Evidence needed from MAIIC*

- **What it requires:** Loans extended under the Government's Mega Farm programme (losses to the fund; MAIIC's 5 percent share) must be in the ECL on a documented basis.
- **What the engine does:** The system holds a November 2025 Mega Farm loan book of 3,490 loans across six GLs (K54.85 bn), found by the build, staged under the MEGA_FARM class (91 days); the programme's treatment (decision D30: out of the EIR engine, in the ECL module, 5 percent share on net) and the PD method (seeded: the agricultural-sector PD scaled to the programme) are governed settings awaiting the CFO's confirmation.
- **General comment:** Spec v4 section 16; the O22 paper of 8 October.
- **Compliance comment:** Evidence needed: the CFO's confirmation of D30; how the K39.76 bn provision of 2025 was arrived at; the monthly Mega Farm books.
- **Where to see it:** Financial Modelling, Mega Farm Programme: /megafarm; Governance Centre: /eir-governance
- **Governance setting:** `mega_farms_scope; megafarm_pd_method; megafarm_scalar_ceiling`
- **Reviewer sign-off:** ____________________   Date: ____________
