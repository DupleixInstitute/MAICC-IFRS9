# IFRS 7 and IAS 1 - presentation and disclosure - compliance audit

IAS 1 Presentation of Financial Statements (1.82(a)) and IFRS 7 Financial Instruments: Disclosures (7.20, 7.35A to 7.35N) - the lines and disclosures the system produces.

Malawi Agricultural and Industrial Investment Company (MAIIC) - IFRS 9 EIR and ECL system - build of 8 October 2026 against specification v4. Prepared 9 October 2026 from the report catalogue (Report Hub, thirty reports, every one rendered by ifrs9:smoke-reports on the clean install), the EIR as at a date, the scenario sets and the stress runs. Reviewer: Deloitte. The Excel workbook of the same name is the working copy (drop-down statuses, hyperlinked contents, reviewer sign-off column, a live Baselines sheet); this document is its printable twin.

Source: IAS 1.82(a) (interest revenue calculated using the effective interest method presented separately); IFRS 7.20 (income, expense, gains and losses), 7.35A to 7.35N (credit risk: practices, quantitative and qualitative information, the allowance reconciliation 7.35H, collateral 7.35K, write-off 7.35L, sensitivity 7.35G). Spec v4 sections 6.11, 12, 15.7.

## Status summary

| Status | Meaning | Sections |
|---|---|---|
| **Done** | Implemented in the system and visible in an approved output; the test in column 11 fails if the behaviour changes. | 8 |
| **Partially done** | The mechanics are in place, but a parameter, an input or a piece of evidence still differs from the requirement. | 1 |
| **Outstanding** | Required for MAIIC and not built. | 1 |
| **Not applicable, documented** | Does not apply to MAIIC by a recorded decision or fact (reason and reference given); revisit if the facts change. | 0 |
| **Evidence needed from MAIIC** | The system is ready; MAIIC must supply a document, minute or dataset before the row can be closed. | 2 |
| **Total** | | 12 |

## Contents

| Ref | Section | Status | Governance setting | Test |
|---|---|---|---|---|
| **IAS 1 PRESENTATION** | | | | |
| IAS 1.82(a) | Interest revenue at the effective interest method as its own line | Done | stage3_interest_basis | Tests\Feature\Eir\EirAsAtServiceTest::test_the_book_rolls_up_by_product_and_gl |
| **IFRS 7 INCOME AND EXPENSE** | | | | |
| 7.20(b) | Total interest revenue and interest expense (effective interest method) | Done |  | Tests\Feature\Eir\EirAsAtServiceTest |
| 7.20A | Gain or loss on derecognition of amortised-cost assets | Outstanding |  |  |
| **IFRS 7 CREDIT RISK** | | | | |
| 7.35F | Credit risk management practices | Partially done | staging_rebuttal; dpd_basis; stage3_missed_instalments | Tests\Feature\Eir\StagingThresholdSeederTest |
| 7.35G | Inputs, assumptions and estimation techniques; sensitivity | Done | scenario_weighting_method; fli_transmission_method | Tests\Feature\Scenario\ScenarioSetServiceTest::test_approval_needs_a_second_person_and_a_lock_is_final |
| 7.35H | Reconciliation of the loss allowance | Done |  | Tests\Feature\Ecl\TimePhasedEclServiceTest |
| 7.35I | Changes in the gross carrying amount that contributed to changes in the allowance | Done | loan_book_build_method | Tests\Feature\Ebanker\LoanBookBuildServiceTest |
| 7.35K | Collateral and other credit enhancements | Evidence needed from MAIIC |  |  |
| 7.35L | Written-off assets still subject to enforcement | Evidence needed from MAIIC |  |  |
| 7.35M | Credit risk exposure by credit-risk rating grade and stage | Done | dpd_basis | Tests\Feature\Eir\StagingServiceTest |
| 7.35N | Concentrations of credit risk | Done |  | Tests\Feature\Eir\EirAsAtServiceTest |
| **THE PACK** | | | | |
| 12.5 | The auditor's pack | Done |  | Tests\Feature\Eir\EirAsAtServiceTest |

## Baselines - the acceptance ties read from the system

Generated 2026-10-08 21:18:10 from maiic_ifrs9_bootstrap.

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

### F1. Collateral evidence for 31 active accounts (7.35K) - Open

- **What was found:** The register carries no security against 31 active accounts (the request to Credit of 7 October).
- **Impact:** The collateral disclosure and the LGD rest on an incomplete register.
- **Recommended action:** Credit supplies the security and valuations; the register is reloaded by the feed.
- **Owner:** Credit

### F2. No write-off policy in the system (7.35F, 7.35L) - Open

- **What was found:** No write-off has been posted in E-Banker since the take-on and the policy is not held.
- **Impact:** The practices disclosure is incomplete.
- **Recommended action:** Finance supplies the policy; the help centre carries it.
- **Owner:** Finance

## Section-by-section audit

### IAS 1 PRESENTATION

**IAS 1.82(a). Interest revenue at the effective interest method as its own line** - *Done*

- **What it requires:** The statement of profit or loss presents interest revenue calculated using the effective interest method separately.
- **What the engine does:** The EIR as at a date gives the EIR interest, the contractual interest posted and the difference for the year to any date, by product, by GL and in total, with the CSV download; the GL reconciliation ties the contractual interest to the ledger.
- **General comment:** The revenue shift is the difference column.
- **Compliance comment:** Compliant: the line is produced; the year-end figure becomes a golden number on the first approved run.
- **Where to see it:** Report Hub, EIR as at a Date: /eir-as-at; GL Reconciliation: /eir-reconciliation
- **Governance setting:** `stage3_interest_basis`
- **Test that proves it:** `Tests\Feature\Eir\EirAsAtServiceTest::test_the_book_rolls_up_by_product_and_gl`
- **Reviewer sign-off:** ____________________   Date: ____________

### IFRS 7 INCOME AND EXPENSE

**7.20(b). Total interest revenue and interest expense (effective interest method)** - *Done*

- **What it requires:** Disclose total interest revenue calculated using the effective interest method for financial assets at amortised cost.
- **What the engine does:** As IAS 1.82(a): the book view's EIR interest year to date.
- **Compliance comment:** Compliant.
- **Where to see it:** /eir-as-at
- **Test that proves it:** `Tests\Feature\Eir\EirAsAtServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**7.20A. Gain or loss on derecognition of amortised-cost assets** - *Outstanding*

- **What it requires:** Disclose the gain or loss on derecognition and the reasons.
- **What the engine does:** The 10 percent test and derecognition are not built (IFRS 9 EIR workbook, 3.3.2).
- **Compliance comment:** Outstanding with the restructure history.
- **Where to see it:** 
- **Reviewer sign-off:** ____________________   Date: ____________

### IFRS 7 CREDIT RISK

**7.35F. Credit risk management practices** - *Partially done*

- **What it requires:** Explain how the entity determines a significant increase in credit risk and default, how instruments were grouped, and the write-off policy.
- **What the engine does:** The staging thresholds by facility class with their rebuttal basis, the missed-instalment trigger, the SICR groups and the 48 governed settings are in the system with their rationale; the write-off policy is MAIIC's to state.
- **Compliance comment:** Partially done: the write-off policy is evidence awaited.
- **Where to see it:** Governance Centre: /eir-governance; Staging & SICR Rules: /stageing-rules
- **Governance setting:** `staging_rebuttal; dpd_basis; stage3_missed_instalments`
- **Test that proves it:** `Tests\Feature\Eir\StagingThresholdSeederTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**7.35G. Inputs, assumptions and estimation techniques; sensitivity** - *Done*

- **What it requires:** Explain the basis of inputs and assumptions, how forward-looking information was incorporated, and changes from the previous period; sensitivity of the ECL to the inputs.
- **What the engine does:** The scenario set stores its sensitivity (the ECL under each scenario, weighted, ten points to the downside and to the upside) and its back-test; the stress runs save the ECL under each scenario; the method cards explain the transmission; the Sensitivity report renders.
- **Compliance comment:** Compliant.
- **Where to see it:** Governance Centre, Scenario Sets: /scenario-sets; Risk & Regulatory, Stress Testing: /stress-testing; Sensitivity: /ifrs9-reports/sensitivity
- **Governance setting:** `scenario_weighting_method; fli_transmission_method`
- **Test that proves it:** `Tests\Feature\Scenario\ScenarioSetServiceTest::test_approval_needs_a_second_person_and_a_lock_is_final`
- **Reviewer sign-off:** ____________________   Date: ____________

**7.35H. Reconciliation of the loss allowance** - *Done*

- **What it requires:** Reconcile the opening to the closing loss allowance by class, showing changes from staging transfers, originations, derecognitions and remeasurement.
- **What the engine does:** The ECL Reconciliation report and the provision comparison render for every period from the staged loan books.
- **Compliance comment:** Compliant.
- **Where to see it:** Report Hub, ECL Reconciliation: /reports/ecl-reconciliation
- **Test that proves it:** `Tests\Feature\Ecl\TimePhasedEclServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**7.35I. Changes in the gross carrying amount that contributed to changes in the allowance** - *Done*

- **What it requires:** Explain how significant changes in the gross carrying amount contributed to changes in the loss allowance.
- **What the engine does:** The loan book of every month from July 2024 holds the gross carrying amount derived from the ledger with its provenance; the Loan Book Reconciliation and the disbursements (vintage) report render.
- **Compliance comment:** Compliant.
- **Where to see it:** Report Hub, Loan Book Reconciliation: /reports/loan-book-reconciliation; Disbursements: /reports/disbursement-report
- **Governance setting:** `loan_book_build_method`
- **Test that proves it:** `Tests\Feature\Ebanker\LoanBookBuildServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**7.35K. Collateral and other credit enhancements** - *Evidence needed from MAIIC*

- **What it requires:** Disclose the effect of collateral on the amount of the loss allowance and the nature of the collateral held.
- **What the engine does:** The security register (P2_11) is landed and the collateral register screens exist; 31 active accounts carry no security on the register and the valuations are not held.
- **Compliance comment:** Evidence needed from Credit.
- **Where to see it:** Data Foundation, Collateral Register: /collateral/register
- **Reviewer sign-off:** ____________________   Date: ____________

**7.35L. Written-off assets still subject to enforcement** - *Evidence needed from MAIIC*

- **What it requires:** Disclose the contractual amount outstanding on written-off assets still under enforcement.
- **What the engine does:** No write-off has been posted since the take-on; the policy and any pre-migration write-offs are MAIIC's to supply.
- **Compliance comment:** Evidence needed.
- **Where to see it:** 
- **Reviewer sign-off:** ____________________   Date: ____________

**7.35M. Credit risk exposure by credit-risk rating grade and stage** - *Done*

- **What it requires:** Disclose the gross carrying amount by credit risk rating grade, separately for 12-month and lifetime ECL (Stage 1, 2, 3).
- **What the engine does:** The IFRS 9 disclosure report and the RBM classification report present the book by stage and by grade; the staging writes the stage every period under the governed thresholds.
- **Compliance comment:** Compliant.
- **Where to see it:** Risk & Regulatory, IFRS 9 Disclosure: /ifrs9-reports/fs-disclosure; RBM Classification: /ifrs9-reports/rbm-classification
- **Governance setting:** `dpd_basis`
- **Test that proves it:** `Tests\Feature\Eir\StagingServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**7.35N. Concentrations of credit risk** - *Done*

- **What it requires:** Disclose concentrations of credit risk.
- **What the engine does:** The Concentration report renders by sector, product and borrower.
- **Compliance comment:** Compliant.
- **Where to see it:** Risk & Regulatory, Concentration: /ifrs9-reports/concentration
- **Test that proves it:** `Tests\Feature\Eir\EirAsAtServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

### THE PACK

**12.5. The auditor's pack** - *Done*

- **What it requires:** The disclosures and the figures behind them handed to the auditor as one archive with a manifest.
- **What the engine does:** compliance:audits --pack={period} bundles the workbooks, the EIR as at the period end, the baselines and the ECL by stage with a SHA-256 per file in manifest.json.
- **Compliance comment:** Compliant.
- **Where to see it:** compliance:audits --pack
- **Test that proves it:** `Tests\Feature\Eir\EirAsAtServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________
