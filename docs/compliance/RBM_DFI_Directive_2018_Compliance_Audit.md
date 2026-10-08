# RBM Directive 2018 - credit risk management for DFIs - compliance audit

Financial Services (Credit Risk Management for Development Finance Institutions) Directive 2018, Reserve Bank of Malawi (Gazette 13 July 2018, No. 18A, pages 42 to 47).

Malawi Agricultural and Industrial Investment Company (MAIIC) - IFRS 9 EIR and ECL system - build of 8 October 2026 against specification v4. Prepared 9 October 2026 from the staging thresholds seeded per the directive, the staged loan books of July 2024 to August 2026, and the RBM Classification and IFRS 9 vs RBM reports. Reviewer: MAIIC Risk. The Excel workbook of the same name is the working copy (drop-down statuses, hyperlinked contents, reviewer sign-off column, a live Baselines sheet); this document is its printable twin.

Source: docs/regulatory/RBM Financial Services (Credit Risk Management for DFIs) Directive 2018 pp42-47.pdf. Spec v4 section 3.6, decision D31; the O13 rebuttal paper of 8 October 2026.

## Status summary

| Status | Meaning | Sections |
|---|---|---|
| **Done** | Implemented in the system and visible in an approved output; the test in column 11 fails if the behaviour changes. | 5 |
| **Partially done** | The mechanics are in place, but a parameter, an input or a piece of evidence still differs from the requirement. | 2 |
| **Outstanding** | Required for MAIIC and not built. | 0 |
| **Not applicable, documented** | Does not apply to MAIIC by a recorded decision or fact (reason and reference given); revisit if the facts change. | 0 |
| **Evidence needed from MAIIC** | The system is ready; MAIIC must supply a document, minute or dataset before the row can be closed. | 1 |
| **Total** | | 8 |

## Contents

| Ref | Section | Status | Governance setting | Test |
|---|---|---|---|---|
| **PART I - PRELIMINARY** | | | | |
| 2 | Definitions: short-, medium- and long-term facilities | Done | dpd_basis | Tests\Feature\Eir\StagingThresholdSeederTest |
| **PART III - CLASSIFICATION** | | | | |
| 9 | Classification of credit facilities | Done | dpd_basis | Tests\Feature\Eir\StagingServiceTest |
| 10 | Non-performing: substandard from 91 days (short-term) or 181 days (medium and long term) | Done | dpd_basis; staging_rebuttal | Tests\Feature\Eir\StagingServiceTest::test_the_directive_thresholds_by_tenor_class_and_the_bucket_fallback |
| 11 | Doubtful and loss | Partially done |  | Tests\Feature\Eir\StagingServiceTest |
| **PART IV - PROVISIONING** | | | | |
| 12 | Minimum provisions by class | Done |  | Tests\Feature\Ecl\TimePhasedEclServiceTest |
| 13 | Non-accrual of interest | Done | stage3_interest_basis | Tests\Feature\Eir\EirRevenueServiceTest |
| **PART V - RESTRUCTURING** | | | | |
| 15 | Restructured facilities | Evidence needed from MAIIC | rate_change_classification |  |
| **PART VI - REPORTING** | | | | |
| 17 | Returns to the Reserve Bank | Partially done | dpd_basis | Tests\Feature\Rbm\RbmReturnServiceTest |

## Baselines - the acceptance ties read from the system

Generated 2026-10-08 21:50:42 from maiic_ifrs9_bootstrap.

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

### F1. The restructured-loan register is not in the system (15) - Open

- **What was found:** The Reschedule Report and the register are awaited from the vendor; restructured facilities cannot keep their classification as section 15 requires.
- **Impact:** Classification of restructured facilities may be too favourable.
- **Recommended action:** Barry obtains the Reschedule Report; the register is loaded by the feed and the classification holds it.
- **Owner:** Barry Makumba

### F2. The prescribed return form is not in the repository (17) - Open

- **What was found:** The provisional return fills every line the directive asks for, in the directive's order; the Reserve Bank's own form (its schedule number, order and wording) has not been supplied.
- **Impact:** The return is still transcribed into the Bank's form by hand.
- **Recommended action:** MAIIC Risk supplies the current form (the Excel or PDF sent to the Bank); the provisional layout is re-ordered and re-worded to it line for line; the figures stay as they are.
- **Owner:** MAIIC Risk / Dupleix

### F3. The RBM Classification report's day bands differ from the directive's (9 to 11) - Open

- **What was found:** The older RBM Classification report classifies every facility on one ladder (90, 180, 365 days); the directive classifies by term (90/180/360 for short-term, 180/360/720 for medium and long). The provisional return applies the directive's bands.
- **Impact:** The two screens can show different classes for the same medium-term facility.
- **Recommended action:** Align the RBM Classification report to the directive's bands by term (or retire it in favour of the return).
- **Owner:** Dupleix

## Section-by-section audit

### PART I - PRELIMINARY

**2. Definitions: short-, medium- and long-term facilities** - *Done*

- **What it requires:** A short-term facility has a repayment period of not more than 12 months; medium-term more than 12 and up to 60; long-term more than 60.
- **What the engine does:** The staging thresholds are keyed by facility class and the tenor in months: DEFAULT with min_tenor 0 (short-term, 91 days) and min_tenor 13 (medium and long, 181 days), each row citing the directive's section 2; the loan book carries the tenor from the loan master.
- **Compliance comment:** Compliant.
- **Where to see it:** Governance Centre, Staging & SICR Rules: /stageing-rules
- **Governance setting:** `dpd_basis`
- **Test that proves it:** `Tests\Feature\Eir\StagingThresholdSeederTest`
- **Reviewer sign-off:** ____________________   Date: ____________

### PART III - CLASSIFICATION

**9. Classification of credit facilities** - *Done*

- **What it requires:** Classify every facility as pass, special mention, substandard, doubtful or loss by the days in arrears and the qualitative factors.
- **What the engine does:** The RBM Classification report classifies each loan from the days past due the build counts from the oldest overdue instalment, with the bucket ageing beside it; the IFRS 9 stage is mapped to the RBM class in the IFRS 9 vs RBM report.
- **Compliance comment:** Compliant.
- **Where to see it:** Risk & Regulatory, RBM Classification: /ifrs9-reports/rbm-classification; IFRS 9 vs RBM: /ifrs9-reports/ifrs9-vs-rbm
- **Governance setting:** `dpd_basis`
- **Test that proves it:** `Tests\Feature\Eir\StagingServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**10. Non-performing: substandard from 91 days (short-term) or 181 days (medium and long term)** - *Done*

- **What it requires:** A facility is non-performing when principal or interest is due and unpaid for 90 days or more on a short-term facility, or 180 days or more on a medium- or long-term facility.
- **What the engine does:** Stage 3 from 91 days for a facility of 12 months or less and from 181 days otherwise; the November 2025 model's uniform 181 days and the accounting policy's 90 days are compared in spec section 3.6 and the thresholds carry the comparison as their rebuttal basis.
- **General comment:** The LONG_TERM proposal (91 days for every class) is seeded future-dated and inactive pending CFO sign-off.
- **Compliance comment:** Compliant.
- **Where to see it:** Risk & Regulatory, RBM Classification: /ifrs9-reports/rbm-classification; IFRS 9 vs RBM: /ifrs9-reports/ifrs9-vs-rbm
- **Governance setting:** `dpd_basis; staging_rebuttal`
- **Test that proves it:** `Tests\Feature\Eir\StagingServiceTest::test_the_directive_thresholds_by_tenor_class_and_the_bucket_fallback`
- **Reviewer sign-off:** ____________________   Date: ____________

**11. Doubtful and loss** - *Partially done*

- **What it requires:** Doubtful from 181 (short-term) or 361 days (medium and long term); loss from 361 or 721 days, or where recovery is not expected.
- **What the engine does:** The RBM Classification report applies the day bands; the loan book's buckets end at 271 to 360 days and the build counts days from the overdue date without a ceiling, so the doubtful and loss bands are classified from the day count; the qualitative 'recovery not expected' flag is the write-off policy MAIIC has not stated.
- **Compliance comment:** Partially done: the day bands are applied; the qualitative loss criterion awaits the policy.
- **Where to see it:** Risk & Regulatory, RBM Classification: /ifrs9-reports/rbm-classification; IFRS 9 vs RBM: /ifrs9-reports/ifrs9-vs-rbm
- **Test that proves it:** `Tests\Feature\Eir\StagingServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

### PART IV - PROVISIONING

**12. Minimum provisions by class** - *Done*

- **What it requires:** Provide at least 0 percent on pass, 5 on special mention, 20 on substandard, 50 on doubtful and 100 on loss, net of eligible security.
- **What the engine does:** The IFRS 9 vs RBM report applies the five percentages to the classified book and sets the regulatory provision beside the IFRS 9 allowance; the higher of the two is the figure the regulatory return carries.
- **Compliance comment:** Compliant in the report; the eligible-security netting rests on the collateral register (IFRS 7 workbook F1).
- **Where to see it:** Risk & Regulatory, RBM Classification: /ifrs9-reports/rbm-classification; IFRS 9 vs RBM: /ifrs9-reports/ifrs9-vs-rbm
- **Test that proves it:** `Tests\Feature\Ecl\TimePhasedEclServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**13. Non-accrual of interest** - *Done*

- **What it requires:** Interest on a non-performing facility is not recognised as income; it is held in suspense until received.
- **What the engine does:** Stage 3 interest in the EIR roll-forward is on the net basis; the contractual interest E-Banker continues to post on non-performing accounts is shown beside it in the GL reconciliation, so the suspense the directive requires is the difference the system reports.
- **General comment:** The year-end re-rating of December 2025 (28 postings) is explained in spec section 3.4.
- **Compliance comment:** Compliant; the suspense is reported, and the posting practice is Finance's to align.
- **Where to see it:** Report Hub, GL Reconciliation: /eir-reconciliation
- **Governance setting:** `stage3_interest_basis`
- **Test that proves it:** `Tests\Feature\Eir\EirRevenueServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

### PART V - RESTRUCTURING

**15. Restructured facilities** - *Evidence needed from MAIIC*

- **What it requires:** A restructured facility keeps its classification until the borrower has performed under the new terms for a stated period.
- **What the engine does:** The restructured-loan register and E-Banker's Reschedule Report are awaited from the vendor; the loan's status history (P3_13) is landed.
- **Compliance comment:** Evidence needed from the vendor.
- **Where to see it:** Data Foundation, E-Banker Feed: /eir-feed
- **Governance setting:** `rate_change_classification`
- **Reviewer sign-off:** ____________________   Date: ____________

### PART VI - REPORTING

**17. Returns to the Reserve Bank** - *Partially done*

- **What it requires:** Submit the classification and provisioning return in the prescribed form and frequency.
- **What the engine does:** RbmReturnService fills the return in the directive's own order from the system: A the facilities by class and term under the directive's day bands, B the minimum provision per class against the IFRS 9 allowance with the higher of the two, C interest in suspense on non-performing facilities, D restructured facilities (awaiting the Reschedule Report), E the security on the register; on the screen with the CSV. The layout is provisional: the prescribed form is MAIIC Risk's and is not in the repository.
- **Compliance comment:** Partially done: every line is filled; the form's own order and wording are applied when Risk supplies it.
- **Where to see it:** Risk & Regulatory, RBM Return (provisional): /rbm-return; Risk & Regulatory, RBM Classification: /ifrs9-reports/rbm-classification; IFRS 9 vs RBM: /ifrs9-reports/ifrs9-vs-rbm
- **Governance setting:** `dpd_basis`
- **Test that proves it:** `Tests\Feature\Rbm\RbmReturnServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________
