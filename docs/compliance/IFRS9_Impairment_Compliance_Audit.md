# IFRS 9 - impairment - compliance audit

IFRS 9 Financial Instruments - impairment (5.5 and B5.5): staging, significant increase in credit risk, 12-month and lifetime expected credit losses, forward-looking information, write-off.

Malawi Agricultural and Industrial Investment Company (MAIIC) - IFRS 9 EIR and ECL system - build of 14 October 2026 (branch eir_revenue_recognition, commit abe140d) against specification v4. As of 14 October 2026: the code at commit abe140d and the fresh bootstrap maiic_ifrs9_results2 of 13 October 2026 (made before commit 216aee2, so its discounting and Mega Farm figures predate those changes), read with SELECTs only; the Governance Centre's staging and forward-looking settings; the PHPUnit suite as written (not run for this audit). Main book at September 2026 on that bootstrap: 134 loans, gross 23.19bn, ECL 11.57bn undiscounted, 7.12bn discounted on the 62 loans the time-phased engine resolved (72 unresolved). Reviewer: Dr Thomson Kumwenda (CFO), then Deloitte. The Excel workbook of the same name is the working copy (drop-down statuses, hyperlinked contents, reviewer sign-off column, a live Baselines sheet); this document is its printable twin.

Source: IFRS 9 Financial Instruments as issued by the IASB, section 5.5 and B5.5.1 to B5.5.55; 5.4.4 and B5.4.9 (write-off). The Reserve Bank of Malawi Financial Services (Credit Risk Management for Development Finance Institutions) Directive 2018 (docs/regulatory). Specification: docs/MAIIC_EIR_Engine_Specification_v4_2026-10-07.md sections 3.6, 13, 14, 15, 16.

Generated 2026-10-14 08:30:38; the Baselines were read from the database `maiic_ifrs9_results2`.

## Status summary

| Status | Meaning | Sections |
|---|---|---|
| **Done** | Implemented in the system and visible in an approved output; the test in column 11 fails if the behaviour changes. | 9 |
| **Partially done** | The mechanics are in place, but a parameter, an input or a piece of evidence still differs from the requirement. | 3 |
| **Outstanding** | Required for MAIIC and not built. | 0 |
| **Not applicable, documented** | Does not apply to MAIIC by a recorded decision or fact (reason and reference given); revisit if the facts change. | 0 |
| **Evidence needed from MAIIC** | The system is ready; MAIIC must supply a document, minute or dataset before the row can be closed. | 2 |
| **Total** | | 14 |

## Contents

| Ref | Section | Status | Governance setting | Test |
|---|---|---|---|---|
| **5.5 RECOGNITION OF EXPECTED CREDIT LOSSES** | | | | |
| 5.5.1 | A loss allowance for expected credit losses | Done |  | Tests\Feature\Ecl\EclGoldenNumberTest::it_computes_golden_ecl_per_stage_for_prefli_collection_lgd; Tests\Feature\Ecl\TimePhasedEclServiceTest |
| 5.5.3 | Lifetime ECL on a significant increase in credit risk | Done | dpd_basis | Tests\Feature\Ecl\TimePhasedEclServiceTest::test_a_stage_two_lifetime_compounds_beyond_the_twelve_month_pd; Tests\Feature\Eir\StagingServiceTest::test_the_trigger_reads_the_chart_in_force_at_the_month_end_not_a_later_one |
| 5.5.5 | 12-month ECL where credit risk has not increased significantly | Done |  | Tests\Feature\Ecl\TimePhasedEclServiceTest::test_stage_one_builds_a_twelve_month_marginal_pd_curve_and_discounts_each_shortfall |
| 5.5.9 to 5.5.11 | Determining a significant increase in credit risk; the 30-days-past-due rebuttable presumption | Done | dpd_basis; staging_rebuttal (shown, not read) | Tests\Feature\Eir\StagingThresholdSeederTest::test_the_long_term_stage_2_rebuttal_is_inactive_until_signed; Tests\Feature\Eir\StagingServiceTest |
| B5.5.37 | Definition of default; the 90-days-past-due rebuttable presumption | Done | dpd_basis; stage3_missed_instalments | Tests\Feature\Eir\StagingServiceTest::test_the_directive_thresholds_by_tenor_class_and_the_bucket_fallback; Tests\Feature\Eir\StagingThresholdSeederTest::test_medium_and_long_term_facilities_are_stage_3_at_181_days_with_the_rebuttal_cited |
| **5.5 MEASUREMENT** | | | | |
| 5.5.17 | Measurement of expected credit losses | Partially done | scenario_weighting_method; fli_adjustment_route | Tests\Feature\FLI\FliRouteServiceTest::test_the_ecl_on_the_post_fli_pd_is_the_weighted_ecl_across_scenarios; Tests\Feature\Ecl\TimePhasedEclServiceTest::test_scenario_weights_combine_into_one_allowance |
| B5.5.41 to B5.5.43 | Probability-weighted outcomes | Done | scenario_minimum_count; scenario_weight_bounds; scenario_calibration_note | Tests\Feature\Scenario\ScenarioSetServiceTest::test_a_set_that_breaks_a_rule_is_refused_with_the_reason |
| B5.5.49 to B5.5.54 | Reasonable and supportable information; forward-looking information | Done | fli_expected_sign_test; fli_r2_cutoff; fli_min_observations; fli_alpha; macro_source_precedence | Tests\Feature\FLI\FliBridgeServiceTest; Tests\Feature\Macro\MacroImportPrecedenceTest |
| B5.5.52 (transmission) | How forward-looking information reaches the PD | Done | fli_transmission_method; fli_asset_correlation | Tests\Feature\FLI\TransmissionMethodCatalogueTest |
| B5.5.1 to B5.5.6, B5.5.28 to B5.5.30 | Collective assessment and segmentation | Done | pd_segmentation_basis; pd_segment_min_observations; pd_segment_thin_rule; lgd_segment_min_cohort | Tests\Feature\Pd\PdSegmentationTest::test_each_portfolio_gets_its_own_pd_or_its_parents_with_the_lineage_recorded; Tests\Feature\Ecl\MegaFarmProgrammeSeparationTest::test_the_programme_portfolio_is_kept_out_of_the_maiic_scope_and_a_blended_book_is_refused |
| B5.5.55 | Loss given default and collateral | Partially done | lgd_segment_min_cohort | Tests\Feature\Pd\PdAndLgdEngineTest::test_a_loan_closed_at_nil_is_not_a_cure_and_a_nil_start_balance_is_no_member |
| 5.5.17(b), B5.5.44 | Time value of money | Partially done |  | Tests\Feature\Ecl\EclDiscountRateServiceTest::test_an_unlocked_or_missing_eir_is_unresolved_never_defaulted; Tests\Feature\Ecl\EclGoldenNumberTest::discounted_mode_keeps_undiscounted_ecl_and_names_unresolved_contracts |
| **5.5 WRITE-OFF AND PRESENTATION** | | | | |
| 5.4.4, B5.4.9 | Write-off | Evidence needed from MAIIC |  | Tests\Feature\Eir\WriteOffTest::test_a_closure_credit_in_a_batch_that_debits_the_write_off_gl_is_a_write_off; Tests\Feature\Pd\PdSegmentationTest::test_a_nil_start_balance_is_no_observation_and_a_write_off_in_the_window_is_a_default |
| **THE MEGA FARM PROGRAMME** | | | | |
| 16 | The Mega Farm loans in the allowance | Evidence needed from MAIIC | mega_farms_scope; megafarm_pd_method; megafarm_scalar_ceiling | Tests\Feature\Ecl\MegaFarmProgrammeSeparationTest::test_the_programme_lgd_is_its_own_cohort_and_a_missing_cohort_declines |

## Baselines - the acceptance ties read from the system

Generated 2026-10-14 08:30:38 from maiic_ifrs9_results2.

| Tie | Expected | System figure | Result |
|---|---|---|---|
| Interest posted Jan 2025 to Jul 2026 (ledger, types 303 and 120) | 5,293,988,207.06 | 5,293,988,207.06 | PASS |
| Derived carrying amount against the stored report, in-scope loan GLs (over 2 cents) | 0 differences | 0 of 2495 account-months | PASS |
| Mega Farm: account-months whose MF_03 ledger differs from the Loan Book Report (reported, not a test) | reported | 77540 account-months | PASS |
| Year-end interest batch contra on 4215 | 32,956,675.55 | 32,956,675.55 | PASS |
| Year-end interest batch contra on 4216 | -21,733,262.70 | -21,733,262.70 | PASS |
| Loan book 1050101 at 31 Dec 2025 against the trial balance | 1,368,737,810.68 | 1,368,737,810.68 | PASS |
| Loan book 1050102 at 31 Dec 2025 against the trial balance | 6,434,045,052.56 | 6,434,045,052.56 | PASS |
| Loan book 1050201 at 31 Dec 2025 against the trial balance (accepted exception, spec 3.5: -400,000.00) | 1,402,393,139.46 | 1,401,993,139.46 | PASS |
| Loan book 1050202 at 31 Dec 2025 against the trial balance (accepted exception, spec 3.5: 1,000,000.00) | 3,808,288,656.15 | 3,809,288,656.15 | PASS |
| Loan book 1050401 at 31 Dec 2025 against the trial balance | 1,827,148,120.69 | 1,827,148,120.69 | PASS |

## Findings - items that need a decision or a fix

### F1. The PD, LGD and ECL are the bootstrap's, not MAIIC's (5.5.1, 5.5.17) - Open

- **What was found:** The PD and LGD engines run under the bootstrap label; no MAIIC reviewer has approved a segment run, a fit or the scenario set. On the fresh bootstrap the main-book ECL at September 2026 is 11.57bn undiscounted (7.12bn discounted on 62 loans, 72 unresolved). (Until 14 October 2026 this finding quoted 8.33bn, which the Contract Schedule 1 workbook gave as 8.94bn; neither is current.)
- **Impact:** The ECL is a system figure awaiting review.
- **Recommended action:** A reviewer approves the segment runs, a fit and the set; the month is re-run discounted and signed.
- **Owner:** Dr Thom / the reviewer

### F2. No fit is approved by a MAIIC reviewer (B5.5.49) - Open

- **What was found:** On the fresh bootstrap the guardrail applied 32 of 209 fits and declined 177; none is approved for the route.
- **Impact:** The forward-looking adjustment is the overlay at zero until then.
- **Recommended action:** A reviewer approves a fit on the Regression (FLI adjustments) screen; a second person reviews.
- **Owner:** Dr Thom / the reviewer

### F3. The Mega Farm provision basis is undocumented (16) - Open

- **What was found:** The 2025 statements carry K39.76bn against K48.7bn Stage 3. On the fresh bootstrap (staging before 216aee2) the December 2025 programme book of 3,692 loans (54.50bn) is 3,219 loans Stage 1 (47.25bn) and 471 Stage 3 (7.25bn).
- **Impact:** The ECL golden number at 31 December 2025 cannot be set.
- **Recommended action:** The CFO confirms D30 and the provisioning method; the next bootstrap on 216aee2 restages the programme on the chart in force.
- **Owner:** Dr Thom / Dupleix

### F4. The reports carry the undiscounted allowance (5.5.17(b)) - Open

- **What was found:** The discounted allowance is held in ecl_value_discounted; the IFRS 9 note, ECL reconciliation, RBM Return and other reports read ecl_value.
- **Impact:** The disclosed allowance is undiscounted.
- **Recommended action:** Point the note and the reports at the booked (discounted) allowance, with the undiscounted measure as a reconciling line.
- **Owner:** Dupleix

### F5. Collateral is not in the LGD (B5.5.55) - Open

- **What was found:** The LGD engine reads no collateral; the collateral register is empty on the fresh bootstrap.
- **Impact:** The cash flows from collateral are not reflected in the shortfall.
- **Recommended action:** Credit supplies the collateral and valuations; Dupleix adds the collateral recovery to the LGD.
- **Owner:** Credit / Dupleix

## Section-by-section audit

### 5.5 RECOGNITION OF EXPECTED CREDIT LOSSES

**5.5.1. A loss allowance for expected credit losses** - *Done*

- **What it requires:** Recognise a loss allowance for expected credit losses on a financial asset measured at amortised cost.
- **What the engine does:** ifrs9:segment-ecl computes the allowance per loan as EAD x PD x LGD on the post-FLI PD for every portfolio of a month and fails closed, naming the loans, when a stage, PD or LGD is missing or a portfolio mixes Mega Farm with MAIIC's own loans; the bootstrap and the month-end (fli:month-end) run it for every period. On the fresh bootstrap the main book carries an allowance in every month (11.57bn undiscounted at September 2026).
- **General comment:** Fresh bootstrap maiic_ifrs9_results2 (13 October 2026, before commit 216aee2). Until 14 October 2026 the row said the PD engine must run for a figure.
- **Compliance comment:** Compliant; the figures are system figures under the bootstrap label, not MAIIC-approved (finding F1).
- **Where to see it:** ECL Processing, ECL Calculation: /expected-credit-loss/list
- **Test that proves it:** `Tests\Feature\Ecl\EclGoldenNumberTest::it_computes_golden_ecl_per_stage_for_prefli_collection_lgd; Tests\Feature\Ecl\TimePhasedEclServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**5.5.3. Lifetime ECL on a significant increase in credit risk** - *Done*

- **What it requires:** At each reporting date, measure the loss allowance at lifetime ECL if the credit risk has increased significantly since initial recognition.
- **What the engine does:** StagingService stages every period: Stage 2 from 31 days past due in every class, the SICR flag from the qualitative groups; the ECL takes the lifetime PD for Stage 2, compounding beyond the 12-month PD. Since 216aee2 the days past due read E-Banker's instalment chart in force at each month-end.
- **General comment:** Spec v4 section 3.6, decision D31.
- **Compliance comment:** Compliant.
- **Where to see it:** Settings, Staging basis: /setting?tab=staging; IFRS 9 Model Setup, Staging & SICR Rules: /sicr-groups; ECL Processing, ECL Calculation: /expected-credit-loss/list
- **Governance setting:** `dpd_basis`
- **Test that proves it:** `Tests\Feature\Ecl\TimePhasedEclServiceTest::test_a_stage_two_lifetime_compounds_beyond_the_twelve_month_pd; Tests\Feature\Eir\StagingServiceTest::test_the_trigger_reads_the_chart_in_force_at_the_month_end_not_a_later_one`
- **Reviewer sign-off:** ____________________   Date: ____________

**5.5.5. 12-month ECL where credit risk has not increased significantly** - *Done*

- **What it requires:** Measure the loss allowance at 12-month ECL where the credit risk has not increased significantly since initial recognition.
- **What the engine does:** Stage 1 loans take the 12-month PD window; the staging writes the stage every period.
- **Compliance comment:** Compliant.
- **Where to see it:** ECL Processing, ECL Calculation: /expected-credit-loss/list
- **Test that proves it:** `Tests\Feature\Ecl\TimePhasedEclServiceTest::test_stage_one_builds_a_twelve_month_marginal_pd_curve_and_discounts_each_shortfall`
- **Reviewer sign-off:** ____________________   Date: ____________

**5.5.9 to 5.5.11. Determining a significant increase in credit risk; the 30-days-past-due rebuttable presumption** - *Done*

- **What it requires:** Assess the change in the risk of default since initial recognition using reasonable and supportable information; there is a rebuttable presumption that credit risk has increased significantly when payments are more than 30 days past due.
- **What the engine does:** Stage 2 from 31 days past due in every facility class, counted from the oldest overdue instalment, with the bucket ageing beside it. The rebuttal is recorded on the threshold rows (staging_thresholds.rebuttal_basis); the LONG_TERM row proposing Stage 2 at 91 days for medium- and long-term facilities only is seeded future-dated (2099) and inactive pending CFO sign-off. The threshold rows are seeded and read-only on screen (a change is a release, not a setting). The staging_rebuttal catalogue setting is shown on Settings but read by no engine.
- **General comment:** The O13 rebuttal paper of 8 October 2026.
- **Compliance comment:** Compliant; the presumption is applied and the rebuttal awaits the CFO.
- **Where to see it:** Settings, Staging basis: /setting?tab=staging; IFRS 9 Model Setup, Staging & SICR Rules: /sicr-groups; Governance: Governance Centre /eir-governance
- **Governance setting:** `dpd_basis; staging_rebuttal (shown, not read)`
- **Test that proves it:** `Tests\Feature\Eir\StagingThresholdSeederTest::test_the_long_term_stage_2_rebuttal_is_inactive_until_signed; Tests\Feature\Eir\StagingServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**B5.5.37. Definition of default; the 90-days-past-due rebuttable presumption** - *Done*

- **What it requires:** Apply a definition of default consistent with internal credit risk management; default occurs no later than 90 days past due unless reasonable and supportable information supports a more lagging criterion.
- **What the engine does:** Stage 3 from 91 days for a facility of 12 months or less and for the Mega Farm class, and from 181 days for a medium- or long-term facility, as the RBM directive classifies them; the four-consecutive-missed-instalments trigger lifts a loan to Stage 3 beside the day count, reading the chart in force and net cash (216aee2). The more lagging criterion rests on the directive, cited on each threshold row.
- **General comment:** Spec v4 section 3.6 compares the policy (90 days), the November 2025 model (181) and the directive (181/91).
- **Compliance comment:** Compliant; the rebuttal is documented on the threshold rows.
- **Where to see it:** Settings, Staging basis: /setting?tab=staging; IFRS 9 Model Setup, Staging & SICR Rules: /sicr-groups
- **Governance setting:** `dpd_basis; stage3_missed_instalments`
- **Test that proves it:** `Tests\Feature\Eir\StagingServiceTest::test_the_directive_thresholds_by_tenor_class_and_the_bucket_fallback; Tests\Feature\Eir\StagingThresholdSeederTest::test_medium_and_long_term_facilities_are_stage_3_at_181_days_with_the_rebuttal_cited`
- **Reviewer sign-off:** ____________________   Date: ____________

### 5.5 MEASUREMENT

**5.5.17. Measurement of expected credit losses** - *Partially done*

- **What it requires:** ECL is an unbiased, probability-weighted amount determined by evaluating a range of possible outcomes, the time value of money, and reasonable and supportable information about past events, current conditions and forecasts.
- **What the engine does:** The PD and LGD by segment, the forward-looking route once per scenario and the weighted ECL run for every period; discounting at the EIR is the month-end default since 216aee2. What is missing: no fit is approved by a MAIIC reviewer, so the route holds the PD (overlay at zero); the governed scenario set is approved only under the bootstrap label; the legacy synthetic scenario assumptions are still seeded APPROVED (EclScenarioAssumptionSeeder); and the reports read the undiscounted ecl_value (5.5.17(b) row).
- **General comment:** Fresh bootstrap maiic_ifrs9_results2 (13 October 2026, before commit 216aee2). Until 14 October 2026 the row said the PD engine had not run on the clean install.
- **Compliance comment:** Partially done: the mechanics are in place; an approved fit and set, and the discounted figure in the reports, are awaited.
- **Where to see it:** ECL Processing, ECL Calculation: /expected-credit-loss/list; Forward-Looking Information, Scenario Sets: /scenario-sets
- **Governance setting:** `scenario_weighting_method; fli_adjustment_route`
- **Test that proves it:** `Tests\Feature\FLI\FliRouteServiceTest::test_the_ecl_on_the_post_fli_pd_is_the_weighted_ecl_across_scenarios; Tests\Feature\Ecl\TimePhasedEclServiceTest::test_scenario_weights_combine_into_one_allowance`
- **Reviewer sign-off:** ____________________   Date: ____________

**B5.5.41 to B5.5.43. Probability-weighted outcomes** - *Done*

- **What it requires:** The estimate reflects the possibility that a credit loss occurs and the possibility that none occurs; a range of scenarios, at least two, weighted by their probabilities.
- **What the engine does:** The governed scenario set: three or more scenarios, weights summing to 100, the base at least 40 percent and no weight above 60, every scenario but the base a shock on the base path; the first set (Base 50, Upside 15, Downside 25, Severe 10) is seeded as a proposal for the CFO. On the fresh bootstrap one set (September 2026) is APPROVED under the bootstrap label.
- **General comment:** Fresh bootstrap maiic_ifrs9_results2 (13 October 2026, before commit 216aee2).
- **Compliance comment:** Compliant.
- **Where to see it:** Forward-Looking Information, Scenario Sets: /scenario-sets
- **Governance setting:** `scenario_minimum_count; scenario_weight_bounds; scenario_calibration_note`
- **Test that proves it:** `Tests\Feature\Scenario\ScenarioSetServiceTest::test_a_set_that_breaks_a_rule_is_refused_with_the_reason`
- **Reviewer sign-off:** ____________________   Date: ____________

**B5.5.49 to B5.5.54. Reasonable and supportable information; forward-looking information** - *Done*

- **What it requires:** Use information reasonably available without undue cost or effort, including forecasts of future economic conditions; a statistical relationship used must be supportable.
- **What the engine does:** Malawi macro series with their provenance (13 variables, 261 data points on the fresh bootstrap; none yet carries a release date, so revisions replace the earlier value); the correlation finder sweeps every driver against every credit-loss proxy over the lag grid under the guardrail (expected sign, R2 cutoff, p-value, minimum observations) and declines a failing fit with the reason. On the fresh bootstrap: 209 fits, 32 applied by the guardrail, 177 declined, none approved.
- **General comment:** Fresh bootstrap maiic_ifrs9_results2 (13 October 2026, before commit 216aee2). Until 14 October 2026 the row said 39 applied and 170 declined, from the 8 October install.
- **Compliance comment:** Compliant; an applied fit is a proposal under maker-checker.
- **Where to see it:** Forward-Looking Information: Overview /fli; Correlation Finder /fli-correlation; Regression /fli-adjustments; transmission methods /fli/transmission-methods; Macro Statistics: /macro-statistics
- **Governance setting:** `fli_expected_sign_test; fli_r2_cutoff; fli_min_observations; fli_alpha; macro_source_precedence`
- **Test that proves it:** `Tests\Feature\FLI\FliBridgeServiceTest; Tests\Feature\Macro\MacroImportPrecedenceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**B5.5.52 (transmission). How forward-looking information reaches the PD** - *Done*

- **What it requires:** The method of adjusting the PD for the forecast must be documented and supportable.
- **What the engine does:** Governed transmission methods, each with a card (what it does, the formula, its preconditions checked live against the data, a worked example on one loan); a method whose preconditions are not met cannot be selected; the scalar is seeded because the history cannot yet support the richer methods.
- **General comment:** Spec v4 section 14.7.
- **Compliance comment:** Compliant.
- **Where to see it:** Forward-Looking Information: Overview /fli; Correlation Finder /fli-correlation; Regression /fli-adjustments; transmission methods /fli/transmission-methods; Governance: Governance Centre /eir-governance
- **Governance setting:** `fli_transmission_method; fli_asset_correlation`
- **Test that proves it:** `Tests\Feature\FLI\TransmissionMethodCatalogueTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**B5.5.1 to B5.5.6, B5.5.28 to B5.5.30. Collective assessment and segmentation** - *Done*

- **What it requires:** Where information is not available at the instrument level, assess on a collective basis grouped by shared credit-risk characteristics.
- **What the engine does:** PdSegmentationService and LgdSegmentationService measure the PD and LGD per portfolio on the governed basis, a thin segment taking its parent's or the pooled figure under the governed rule, with the lineage on each loan; the Mega Farm programme is its own segment, kept out of MAIIC's PD, LGD and ECL, and a portfolio mixing the two is refused (216aee2). Both run for every period in the bootstrap and the month-end.
- **General comment:** Until 14 October 2026 this row was Partially done and said the transition matrices were not in the bootstrap chain.
- **Compliance comment:** Compliant.
- **Where to see it:** IFRS 9 Model Setup, PD Model and LGD Model; Reports: PD by portfolio /ifrs9-reports/sector-ecl
- **Governance setting:** `pd_segmentation_basis; pd_segment_min_observations; pd_segment_thin_rule; lgd_segment_min_cohort`
- **Test that proves it:** `Tests\Feature\Pd\PdSegmentationTest::test_each_portfolio_gets_its_own_pd_or_its_parents_with_the_lineage_recorded; Tests\Feature\Ecl\MegaFarmProgrammeSeparationTest::test_the_programme_portfolio_is_kept_out_of_the_maiic_scope_and_a_blended_book_is_refused`
- **Reviewer sign-off:** ____________________   Date: ____________

**B5.5.55. Loss given default and collateral** - *Partially done*

- **What it requires:** The cash shortfalls reflect the cash flows expected from collateral and other credit enhancements that are integral to the contractual terms.
- **What the engine does:** The LGD is the Stage 3 cohort workout per portfolio (cure, recovery discounted, write-off as loss; a written-off loan left at nil is no longer a cure since 216aee2). Collateral does not enter it: the engine reads no collateral, and on the fresh bootstrap collateral_registers and collateral_allocations are empty although the E-Banker security register (P2_11, 185 rows) is landed.
- **General comment:** Until 14 October 2026 this row was referenced B5.5.33 to B5.5.35 and marked Evidence needed; the collateral link is also Dupleix's to build.
- **Compliance comment:** Partially done: Credit supplies the collateral and valuations; Dupleix brings collateral into the LGD.
- **Where to see it:** IFRS 9 Model Setup, LGD Model; Collateral Management, Collateral Register: /collateral/register; Reports: LGD and collateral /ifrs9-reports/lgd-collateral
- **Governance setting:** `lgd_segment_min_cohort`
- **Test that proves it:** `Tests\Feature\Pd\PdAndLgdEngineTest::test_a_loan_closed_at_nil_is_not_a_cure_and_a_nil_start_balance_is_no_member`
- **Reviewer sign-off:** ____________________   Date: ____________

**5.5.17(b), B5.5.44. Time value of money** - *Partially done*

- **What it requires:** Discount the expected credit losses at the EIR determined at initial recognition (or an approximation).
- **What the engine does:** EclDiscountRateService takes the loan's locked EIR; an unlocked or missing EIR is unresolved, never defaulted, and a floating loan's rate is labelled a proxy; a Stage 3 loss at the reporting date needs no rate. Since 216aee2 the month-end and the bootstrap run discounted by default: ecl_value keeps the engine's measure and ecl_value_discounted holds the discounted allowance, shown on the ECL screen. But the IFRS 9 note, the ECL reconciliation, the RBM Return and the other reports still read ecl_value, so the figure they carry is undiscounted. On the fresh bootstrap at September 2026, 62 main-book loans are discounted (7.12bn) and 72 are unresolved.
- **General comment:** Fresh bootstrap maiic_ifrs9_results2 (13 October 2026, before commit 216aee2). Until 14 October 2026 the row said 'the contractual rate where none is locked', which the code never did.
- **Compliance comment:** Partially done: the discounted allowance is computed; the reports must carry it.
- **Where to see it:** ECL Processing, ECL Calculation: /expected-credit-loss/list
- **Test that proves it:** `Tests\Feature\Ecl\EclDiscountRateServiceTest::test_an_unlocked_or_missing_eir_is_unresolved_never_defaulted; Tests\Feature\Ecl\EclGoldenNumberTest::discounted_mode_keeps_undiscounted_ecl_and_names_unresolved_contracts`
- **Reviewer sign-off:** ____________________   Date: ____________

### 5.5 WRITE-OFF AND PRESENTATION

**5.4.4, B5.4.9. Write-off** - *Evidence needed from MAIIC*

- **What it requires:** Write off the gross carrying amount when there is no reasonable expectation of recovery.
- **What the engine does:** WriteOffClassifier (220a5a6) identifies ledger write-offs (type 300; a batch debiting GL 1064; ACCOUNT CLOSURE on a status-H account) for the loan-book build, the contract inputs and the EIR cash; the PD cohort counts a write-off as a default (216aee2); the register records a write-off not on the loan account under two-person approval. On the fresh bootstrap: 22 ledger write-offs of 60,778,466.86; the register is empty.
- **General comment:** Fresh bootstrap maiic_ifrs9_results2 (13 October 2026, before commit 216aee2). Until 14 October 2026 the row said no write-off had been posted since the take-on.
- **Compliance comment:** Evidence needed: MAIIC's write-off policy and Finance's confirmation of status H.
- **Where to see it:** EIR Data, Write-offs: /eir-write-offs
- **Test that proves it:** `Tests\Feature\Eir\WriteOffTest::test_a_closure_credit_in_a_batch_that_debits_the_write_off_gl_is_a_write_off; Tests\Feature\Pd\PdSegmentationTest::test_a_nil_start_balance_is_no_observation_and_a_write_off_in_the_window_is_a_default`
- **Reviewer sign-off:** ____________________   Date: ____________

### THE MEGA FARM PROGRAMME

**16. The Mega Farm loans in the allowance** - *Evidence needed from MAIIC*

- **What it requires:** Loans extended under the Government's Mega Farm programme (losses to the fund; MAIIC's 5 percent share) must be in the ECL on a documented basis.
- **What the engine does:** The programme's monthly books (MF_01 and MF_03) are landed: on the fresh bootstrap 3,692 loans on six of the programme's seven GLs (1050301 to 1050305 and 1050307) with 56.18bn gross at September 2026, 3,559 of them over 90 days past due. Since 216aee2 the programme is segmented as its own portfolio, kept out of MAIIC's PD, LGD and ECL, and provided by megafarm:ecl from its own LGD cohort under the governed PD method; MAIIC's share is read from the mega_farms_scope text (it falls back to 1.0 when the text names no share). Not built: the interest split, the maize receivable and the fund liability of spec 16.4 and 16.5 (they wait on D30).
- **General comment:** Fresh bootstrap maiic_ifrs9_results2 (13 October 2026, before commit 216aee2). Until 14 October 2026 the row said 3,490 loans (K54.85bn) from a November 2025 book.
- **Compliance comment:** Evidence needed: the CFO's confirmation of D30; how the K39.76bn provision of 2025 was arrived at.
- **Where to see it:** ECL Processing, Mega Farm Programme: /megafarm; Governance: Governance Centre /eir-governance
- **Governance setting:** `mega_farms_scope; megafarm_pd_method; megafarm_scalar_ceiling`
- **Test that proves it:** `Tests\Feature\Ecl\MegaFarmProgrammeSeparationTest::test_the_programme_lgd_is_its_own_cohort_and_a_missing_cohort_declines`
- **Reviewer sign-off:** ____________________   Date: ____________
