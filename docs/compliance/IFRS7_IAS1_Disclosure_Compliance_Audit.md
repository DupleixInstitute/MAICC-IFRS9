# IFRS 7 and IAS 1 - presentation and disclosure - compliance audit

IAS 1 Presentation of Financial Statements (1.82(a)) and IFRS 7 Financial Instruments: Disclosures (7.20, 7.34(c), 7.35A to 7.35M) - the lines and disclosures the system produces.

Malawi Agricultural and Industrial Investment Company (MAIIC) - IFRS 9 EIR and ECL system - build of 14 October 2026 (branch eir_revenue_recognition, commit abe140d) against specification v4. As of 14 October 2026: the report catalogue (29 reports in the Reports hub, rendered by ifrs9:smoke-reports), the IFRS 9 note (/ifrs9-reports/fs-disclosure), the EIR as at a date, the RBM Return, the scenario sets and the stress runs, at commit abe140d; figures from the fresh bootstrap maiic_ifrs9_results2 of 13 October 2026 (before commit 216aee2), read with SELECTs only. Reviewer: Deloitte. The Excel workbook of the same name is the working copy (drop-down statuses, hyperlinked contents, reviewer sign-off column, a live Baselines sheet); this document is its printable twin.

Source: IAS 1.82(a) (interest revenue calculated using the effective interest method presented separately); IFRS 7.20 (income, expense, gains and losses), 7.34(c) (concentrations of risk), 7.35A to 7.35M (credit risk: practices, inputs and assumptions, the allowance reconciliation 7.35H, changes in the gross carrying amount 7.35I, collateral 7.35K, write-off 7.35L, exposure by grade 7.35M). Spec v4 sections 6.11, 12, 15.7.

Generated 2026-10-14 08:30:26; the Baselines were read from the database `maiic_ifrs9_results2`.

## Status summary

| Status | Meaning | Sections |
|---|---|---|
| **Done** | Implemented in the system and visible in an approved output; the test in column 11 fails if the behaviour changes. | 4 |
| **Partially done** | The mechanics are in place, but a parameter, an input or a piece of evidence still differs from the requirement. | 7 |
| **Outstanding** | Required for MAIIC and not built. | 1 |
| **Not applicable, documented** | Does not apply to MAIIC by a recorded decision or fact (reason and reference given); revisit if the facts change. | 0 |
| **Evidence needed from MAIIC** | The system is ready; MAIIC must supply a document, minute or dataset before the row can be closed. | 0 |
| **Total** | | 12 |

## Contents

| Ref | Section | Status | Governance setting | Test |
|---|---|---|---|---|
| **IAS 1 PRESENTATION** | | | | |
| IAS 1.82(a) | Interest revenue at the effective interest method as its own line | Partially done | stage3_interest_basis | Tests\Feature\Eir\EirAsAtServiceTest::test_the_book_rolls_up_by_product_and_gl |
| **IFRS 7 INCOME AND EXPENSE** | | | | |
| 7.20(b) | Total interest revenue (effective interest method) | Partially done | stage3_interest_basis | Tests\Feature\Eir\EirAsAtServiceTest::test_the_book_rolls_up_by_product_and_gl |
| 7.20A | Gain or loss on derecognition of amortised-cost assets | Outstanding |  |  |
| **IFRS 7 CREDIT RISK** | | | | |
| 7.35F | Credit risk management practices | Partially done | dpd_basis; stage3_missed_instalments; stage_cure_months; pd_segmentation_basis | Tests\Feature\Eir\StagingThresholdSeederTest |
| 7.35G | Inputs, assumptions and estimation techniques; sensitivity | Done | scenario_weighting_method; fli_transmission_method | Tests\Feature\FLI\FliRouteServiceTest::test_the_ecl_on_the_post_fli_pd_is_the_weighted_ecl_across_scenarios; Tests\Feature\StressTestingReconciliationTest::test_driver_run_matches_hand_computed_ecl |
| 7.35H | Reconciliation of the loss allowance | Partially done |  | Tests\Unit\EclMovementAttributionTest::test_every_column_ties; Tests\Unit\EclMovementAttributionTest::test_new_derecognised_and_written_off |
| 7.35I | Changes in the gross carrying amount that contributed to changes in the allowance | Partially done | loan_book_build_method | Tests\Feature\Ebanker\LoanBookBuildServiceTest::test_method_b_derives_the_carrying_amount_from_the_ledger_and_keeps_the_stored_figure_beside_it |
| 7.35K | Collateral and other credit enhancements | Partially done |  | Tests\Feature\Imports\ImportValidationTest::test_a_collateral_file_with_bad_values_lists_each_problem_by_row |
| 7.35L | Written-off assets still subject to enforcement | Partially done |  |  |
| 7.35M | Credit risk exposure by credit-risk rating grade and stage | Done | dpd_basis | Tests\Feature\Eir\StagingServiceTest::test_the_directive_thresholds_by_tenor_class_and_the_bucket_fallback; Tests\Feature\Rbm\RbmReturnServiceTest::test_the_rbm_classification_report_agrees_with_the_return_on_every_loan |
| **IFRS 7 CONCENTRATIONS** | | | | |
| 7.34(c) | Concentrations of risk | Done |  | ifrs9:smoke-reports (renders every hub report; no PHPUnit test of this report) |
| **THE PACK** | | | | |
| 12.5 | The auditor's pack | Done |  | Tests\Feature\AuditFindingM16ScreensTest::test_the_auditor_pack_screen_lists_the_packs_with_their_manifest_counts_and_hands_one_out |

## Baselines - the acceptance ties read from the system

Generated 2026-10-14 08:30:26 from maiic_ifrs9_results2.

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

### F1. Collateral for the active accounts and the LGD link (7.35K) - Open

- **What was found:** The collateral register is empty on the fresh bootstrap and the LGD reads no collateral (the request to Credit of 7 October covered 31 active accounts with no security).
- **Impact:** The collateral disclosure and the LGD rest on nothing yet.
- **Recommended action:** Credit supplies the security and valuations; Dupleix adds collateral to the LGD.
- **Owner:** Credit / Dupleix

### F2. No write-off policy in the system (7.35F, 7.35L) - Open

- **What was found:** The write-off classifier and register exist (220a5a6), but MAIIC's policy and the enforcement status of written-off accounts are not held.
- **Impact:** The practices disclosure and 7.35L are incomplete.
- **Recommended action:** Finance supplies the policy and the enforcement list; the help centre carries the policy.
- **Owner:** Finance

### F3. Write-offs are nil in the note and the reconciliations (7.35H, 7.35I) - Open

- **What was found:** Ifrs9NoteService marks no loan written off; EclReconciliationService hard-codes 0; LoanBookReconciliationService filters a status text that never occurs. 22 ledger write-offs of 60,778,466.86 are classified on the fresh bootstrap.
- **Impact:** The allowance and gross reconciliations misstate write-offs as derecognitions or omit them.
- **Recommended action:** Read write-offs from the classifier and approved register entries in all three.
- **Owner:** Dupleix

### F4. EIR interest covers the locked loans only (IAS 1.82(a), 7.20(b)) - Open

- **What was found:** 14 of 144 contracts carry a locked EIR on the fresh bootstrap; the 2024 and 2025 revenue shift for the book (P9) is not computed.
- **Impact:** The EIR interest line is not yet the book's.
- **Recommended action:** Lock the remaining EIRs after the fee reviews; run P9.
- **Owner:** MAIIC reviewers / Dupleix

## Section-by-section audit

### IAS 1 PRESENTATION

**IAS 1.82(a). Interest revenue at the effective interest method as its own line** - *Partially done*

- **What it requires:** The statement of profit or loss presents interest revenue calculated using the effective interest method separately.
- **What the engine does:** The EIR as at a date gives the EIR interest, the contractual interest posted and the difference for any period to any date, by product, by GL and in total, with CSV, Excel and PDF; the GL reconciliation ties the contractual interest to the ledger. It covers only the loans with a locked EIR: 14 of 144 contracts on the fresh bootstrap (13 with roll-forward rows from July 2024 to September 2026). The revenue shift for 2024 and 2025 across the book (spec P9) is not yet computed.
- **General comment:** Fresh bootstrap maiic_ifrs9_results2 (13 October 2026, before commit 216aee2). Until 14 October 2026 this row was marked Done.
- **Compliance comment:** Partially done: the line is produced for the loans with a locked EIR; the book's figure needs the remaining EIRs locked (fee reviews) and the P9 run.
- **Where to see it:** EIR Calculations, EIR as at a Date: /eir-as-at; GL Reconciliation: /eir-reconciliation
- **Governance setting:** `stage3_interest_basis`
- **Test that proves it:** `Tests\Feature\Eir\EirAsAtServiceTest::test_the_book_rolls_up_by_product_and_gl`
- **Reviewer sign-off:** ____________________   Date: ____________

### IFRS 7 INCOME AND EXPENSE

**7.20(b). Total interest revenue (effective interest method)** - *Partially done*

- **What it requires:** Disclose total interest revenue calculated using the effective interest method for financial assets at amortised cost.
- **What the engine does:** As IAS 1.82(a): the book view's EIR interest year to date, for the loans with a locked EIR only.
- **General comment:** Until 14 October 2026 this row was marked Done.
- **Compliance comment:** Partially done, as IAS 1.82(a).
- **Where to see it:** /eir-as-at
- **Governance setting:** `stage3_interest_basis`
- **Test that proves it:** `Tests\Feature\Eir\EirAsAtServiceTest::test_the_book_rolls_up_by_product_and_gl`
- **Reviewer sign-off:** ____________________   Date: ____________

**7.20A. Gain or loss on derecognition of amortised-cost assets** - *Outstanding*

- **What it requires:** Disclose the gain or loss on derecognition and the reasons.
- **What the engine does:** Modification, the 10 percent test and derecognition on modification are not built (IFRS 9 EIR workbook, 5.4.3 and 3.3.2). The reschedule history (RS_02) landed on 12 October 2026.
- **Compliance comment:** Outstanding (Dupleix, with the restructure register).
- **Where to see it:** 
- **Reviewer sign-off:** ____________________   Date: ____________

### IFRS 7 CREDIT RISK

**7.35F. Credit risk management practices** - *Partially done*

- **What it requires:** Explain how the entity determines a significant increase in credit risk and default, how instruments were grouped, and the write-off policy.
- **What the engine does:** The staging thresholds by facility class with their rebuttal basis, the missed-instalment trigger, the cure period, the SICR groups, the PD and LGD segmentation and the 61 catalogue settings are in the system with their rationale, and the IFRS 9 note writes its policy text from them. The write-off policy is MAIIC's to state; the write-off classifier and register exist (220a5a6).
- **General comment:** Until 14 October 2026 the row said 48 governed settings.
- **Compliance comment:** Partially done: the write-off policy is evidence awaited.
- **Where to see it:** Governance: Governance Centre /eir-governance; Settings, Staging basis: /setting?tab=staging; Reports: IFRS 9 note (FS disclosure) /ifrs9-reports/fs-disclosure
- **Governance setting:** `dpd_basis; stage3_missed_instalments; stage_cure_months; pd_segmentation_basis`
- **Test that proves it:** `Tests\Feature\Eir\StagingThresholdSeederTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**7.35G. Inputs, assumptions and estimation techniques; sensitivity** - *Done*

- **What it requires:** Explain the basis of inputs and assumptions, how forward-looking information was incorporated, and changes from the previous period.
- **What the engine does:** The scenario set stores its sensitivity (the ECL under each scenario, weighted) and the stress runs save the ECL under each scenario; the transmission method cards explain the forward-looking route; the IFRS 9 note writes its assumptions from the governed settings. The hub's Sensitivity entry now redirects to Stress Testing.
- **General comment:** Until 14 October 2026 the test cited was the scenario set's approval test.
- **Compliance comment:** Compliant.
- **Where to see it:** Forward-Looking Information, Scenario Sets: /scenario-sets; Stress Testing: /stress-testing; Reports: IFRS 9 note (FS disclosure) /ifrs9-reports/fs-disclosure
- **Governance setting:** `scenario_weighting_method; fli_transmission_method`
- **Test that proves it:** `Tests\Feature\FLI\FliRouteServiceTest::test_the_ecl_on_the_post_fli_pd_is_the_weighted_ecl_across_scenarios; Tests\Feature\StressTestingReconciliationTest::test_driver_run_matches_hand_computed_ecl`
- **Reviewer sign-off:** ____________________   Date: ____________

**7.35H. Reconciliation of the loss allowance** - *Partially done*

- **What it requires:** Reconcile the opening to the closing loss allowance by class, showing changes from staging transfers, originations, derecognitions, write-offs and remeasurement.
- **What the engine does:** The IFRS 9 note reconciles opening to closing by stage with transfers, new assets, derecognitions and remeasurement, and refuses a download unless every column ties (EclMovementAttribution). Write-offs are always nil: the note marks no loan as written off (Ifrs9NoteService, written_off false) and its text says so; the ECL Reconciliation export hard-codes 0 (EclReconciliationService), although 22 ledger write-offs of 60,778,466.86 are classified on the fresh bootstrap. The allowance it reconciles is the undiscounted ecl_value.
- **General comment:** Fresh bootstrap maiic_ifrs9_results2 (13 October 2026, before commit 216aee2). Until 14 October 2026 this row was marked Done.
- **Compliance comment:** Partially done: write-offs must come from the classifier and register, and the allowance from the discounted figure.
- **Where to see it:** Reports: IFRS 9 note (FS disclosure) /ifrs9-reports/fs-disclosure; ECL Reconciliation: /ifrs9-reports/ecl-reconciliation and /report/ecl-reconciliation
- **Test that proves it:** `Tests\Unit\EclMovementAttributionTest::test_every_column_ties; Tests\Unit\EclMovementAttributionTest::test_new_derecognised_and_written_off`
- **Reviewer sign-off:** ____________________   Date: ____________

**7.35I. Changes in the gross carrying amount that contributed to changes in the allowance** - *Partially done*

- **What it requires:** Explain how significant changes in the gross carrying amount contributed to changes in the loss allowance.
- **What the engine does:** The loan book of every month from July 2024 holds the gross carrying amount derived from the ledger with its provenance; the IFRS 9 note's gross table and the Gross Movement report render. The Loan Book Reconciliation's write-off line filters contract_status like '%write%' (LoanBookReconciliationService), but the statuses are single letters (A, C, D, F on the fresh bootstrap), so the line is always nil.
- **General comment:** Fresh bootstrap maiic_ifrs9_results2 (13 October 2026, before commit 216aee2). Until 14 October 2026 this row was marked Done.
- **Compliance comment:** Partially done: the write-off line must read the classified write-offs.
- **Where to see it:** Reports: IFRS 9 note (FS disclosure) /ifrs9-reports/fs-disclosure; Gross Movement /ifrs9-reports/gross-movement; Loan Book Reconciliation /report/loan-book-reconciliation; Disbursements /report/disbursement-report
- **Governance setting:** `loan_book_build_method`
- **Test that proves it:** `Tests\Feature\Ebanker\LoanBookBuildServiceTest::test_method_b_derives_the_carrying_amount_from_the_ledger_and_keeps_the_stored_figure_beside_it`
- **Reviewer sign-off:** ____________________   Date: ____________

**7.35K. Collateral and other credit enhancements** - *Partially done*

- **What it requires:** Disclose the effect of collateral on the amount of the loss allowance and the nature of the collateral held.
- **What the engine does:** The E-Banker security register (P2_11, 185 rows) is landed and the collateral screens exist with a checked import; on the fresh bootstrap collateral_registers and collateral_allocations are empty, and the LGD engine reads no collateral, so its effect on the allowance cannot be shown.
- **General comment:** Until 14 October 2026 this row was Evidence needed; the LGD link is also Dupleix's.
- **Compliance comment:** Partially done: Credit supplies the security and valuations; Dupleix links collateral to the LGD.
- **Where to see it:** Collateral Management, Collateral Register: /collateral/register; Reports: LGD and collateral /ifrs9-reports/lgd-collateral
- **Test that proves it:** `Tests\Feature\Imports\ImportValidationTest::test_a_collateral_file_with_bad_values_lists_each_problem_by_row`
- **Reviewer sign-off:** ____________________   Date: ____________

**7.35L. Written-off assets still subject to enforcement** - *Partially done*

- **What it requires:** Disclose the contractual amount outstanding on assets written off in the period that are still subject to enforcement activity.
- **What the engine does:** Ledger write-offs are classified (22 of 60,778,466.86 on the fresh bootstrap) and the register records any other; neither carries a 'still under enforcement' field, and no disclosure line is produced.
- **General comment:** Fresh bootstrap maiic_ifrs9_results2 (13 October 2026, before commit 216aee2). Until 14 October 2026 this row said no write-off had been posted since the take-on.
- **Compliance comment:** Partially done: Finance states which written-off accounts are under enforcement; Dupleix adds the field and the line.
- **Where to see it:** EIR Data, Write-offs: /eir-write-offs
- **Reviewer sign-off:** ____________________   Date: ____________

**7.35M. Credit risk exposure by credit-risk rating grade and stage** - *Done*

- **What it requires:** Disclose the gross carrying amount by credit risk rating grade, separately for 12-month and lifetime ECL (Stage 1, 2, 3).
- **What the engine does:** The IFRS 9 note and the RBM Classification report present the book by stage and by RBM class (days past due by term). The internal grades are not populated (internal_grade_code is null on every main-book loan on the fresh bootstrap; 0 internal grade profiles), so the grade used is the days-past-due class, which IFRS 7 B8I allows where past-due information is the basis of the SICR assessment.
- **General comment:** Fresh bootstrap maiic_ifrs9_results2 (13 October 2026, before commit 216aee2).
- **Compliance comment:** Compliant on the days-past-due basis.
- **Where to see it:** Reports: IFRS 9 note (FS disclosure) /ifrs9-reports/fs-disclosure; RBM Classification /ifrs9-reports/rbm-classification; Stage Allocation /ifrs9-reports/stage-allocation
- **Governance setting:** `dpd_basis`
- **Test that proves it:** `Tests\Feature\Eir\StagingServiceTest::test_the_directive_thresholds_by_tenor_class_and_the_bucket_fallback; Tests\Feature\Rbm\RbmReturnServiceTest::test_the_rbm_classification_report_agrees_with_the_return_on_every_loan`
- **Reviewer sign-off:** ____________________   Date: ____________

### IFRS 7 CONCENTRATIONS

**7.34(c). Concentrations of risk** - *Done*

- **What it requires:** Disclose concentrations of risk if not otherwise apparent.
- **What the engine does:** The Concentration and large exposures report gives single-name and portfolio concentration (HHI) by sector, product and borrower; the sector and product-group ECL reports render.
- **General comment:** Until 14 October 2026 this row was referenced 7.35N (the provision-matrix paragraph) and cited an unrelated test.
- **Compliance comment:** Compliant.
- **Where to see it:** Reports: Concentration and large exposures /ifrs9-reports/concentration; Sector ECL /ifrs9-reports/sector-ecl
- **Test that proves it:** `ifrs9:smoke-reports (renders every hub report; no PHPUnit test of this report)`
- **Reviewer sign-off:** ____________________   Date: ____________

### THE PACK

**12.5. The auditor's pack** - *Done*

- **What it requires:** The disclosures and the figures behind them handed to the auditor as one archive with a manifest.
- **What the engine does:** compliance:audits --pack={period} bundles the workbooks, the EIR as at the period end, the baselines and the ECL by stage with a SHA-256 per file in manifest.json; the Auditor Pack screen lists the packs with their manifest counts and hands one out.
- **General comment:** Until 14 October 2026 the test cited was unrelated.
- **Compliance comment:** Compliant.
- **Where to see it:** Governance, Audit: Auditor Pack /auditor-pack; compliance:audits --pack
- **Test that proves it:** `Tests\Feature\AuditFindingM16ScreensTest::test_the_auditor_pack_screen_lists_the_packs_with_their_manifest_counts_and_hands_one_out`
- **Reviewer sign-off:** ____________________   Date: ____________
