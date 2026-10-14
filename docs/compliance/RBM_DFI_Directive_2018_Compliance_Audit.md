# RBM Directive 2018 - credit risk management for DFIs - compliance audit

Financial Services (Credit Risk Management for Development Finance Institutions) Directive 2018, Reserve Bank of Malawi (Gazette 13 July 2018, pages 680 to 685).

Malawi Agricultural and Industrial Investment Company (MAIIC) - IFRS 9 EIR and ECL system - build of 14 October 2026 (branch eir_revenue_recognition, commit abe140d) against specification v4. As of 14 October 2026: the code at commit abe140d (the uncommitted screen changes in the working tree do not alter any row) and the fresh bootstrap maiic_ifrs9_results2 of 13 October 2026, made before commit 216aee2, read with SELECTs only. Paragraph references re-keyed to the Gazette on 14 October 2026 (they cited 9, 11, 12, 15 and 17 for the wrong subjects). The Gazette's text layer misprints the Schedule's short-term rows, so the short-term bands are read from paragraph 10. Reviewer: MAIIC Risk. The Excel workbook of the same name is the working copy (drop-down statuses, hyperlinked contents, reviewer sign-off column, a live Baselines sheet); this document is its printable twin.

Source: docs/regulatory/RBM Financial Services (Credit Risk Management for DFIs) Directive 2018 pp42-47.pdf (Gazette 13 July 2018, pages 680 to 685). Spec v4 section 3.6, decision D31; the O13 rebuttal paper of 8 October 2026.

Generated 2026-10-14 08:30:44; the Baselines were read from the database `maiic_ifrs9_results2`.

## Status summary

| Status | Meaning | Sections |
|---|---|---|
| **Done** | Implemented in the system and visible in an approved output; the test in column 11 fails if the behaviour changes. | 4 |
| **Partially done** | The mechanics are in place, but a parameter, an input or a piece of evidence still differs from the requirement. | 5 |
| **Outstanding** | Required for MAIIC and not built. | 4 |
| **Not applicable, documented** | Does not apply to MAIIC by a recorded decision or fact (reason and reference given); revisit if the facts change. | 4 |
| **Evidence needed from MAIIC** | The system is ready; MAIIC must supply a document, minute or dataset before the row can be closed. | 2 |
| **Total** | | 19 |

## Contents

| Ref | Section | Status | Governance setting | Test |
|---|---|---|---|---|
| **PART I - PRELIMINARY** | | | | |
| 2 | Definitions: short-, medium- and long-term facilities | Done | dpd_basis | Tests\Feature\Eir\StagingThresholdSeederTest::test_short_term_facilities_are_stage_3_at_91_days_as_the_directive_classifies_them; Tests\Feature\Rbm\RbmReturnServiceTest::test_the_directive_bands_by_term |
| **PART III - REQUIREMENTS** | | | | |
| 4(b)(vi), 4(b)(x) | Board-approved written policies: write-offs of loss facilities; restructuring | Evidence needed from MAIIC |  | Tests\Feature\Eir\WriteOffTest::test_a_register_write_off_reaches_the_engines_only_when_a_different_person_approves_it |
| 5(1)(b) | Written valuation methods for collateral | Evidence needed from MAIIC |  |  |
| 10 | Classification of credit facilities | Done | dpd_basis | Tests\Feature\Rbm\RbmReturnServiceTest::test_the_directive_bands_by_term; Tests\Feature\Rbm\RbmReturnServiceTest::test_the_rbm_classification_report_agrees_with_the_return_on_every_loan |
| 11(1), 11(2) | Quarterly review of all facilities; prompt reclassification | Done | dpd_basis; stage3_missed_instalments | Tests\Feature\Rbm\RbmReturnServiceTest::test_the_return_fills_the_sections_from_the_book |
| 11(3) | Review each large exposure individually | Partially done |  |  |
| 12 | Write off loss facilities in the following quarter | Outstanding |  |  |
| 13(1) | Non-accrual: cease accrual, reverse uncollected interest, recognise interest only when received in cash | Partially done | stage3_interest_basis |  |
| 13(2) | Non-accrual regardless of collateral | Done |  | Tests\Feature\Rbm\RbmReturnServiceTest::test_the_directive_bands_by_term |
| 13(3) | Restore to accrual only when all arrears are paid | Outstanding | stage_cure_months (IFRS 9 stage only) |  |
| 14 and Schedule | Minimum provisions by class | Partially done |  | Tests\Feature\Rbm\RbmReturnServiceTest::test_the_return_fills_the_sections_from_the_book |
| 15(1) | Excess of the regulatory provision over IFRS to a loan loss reserve | Partially done |  |  |
| 15(2), 15(4) | The reserve is not capital; released only after recoveries or with the Registrar's approval | Not applicable, documented |  |  |
| 15(3) | Report loan loss reserves on the call report as prescribed | Partially done | dpd_basis | Tests\Feature\Rbm\RbmReturnServiceTest::test_the_return_fills_the_sections_from_the_book |
| 16(1) | No restructure, renegotiation, roll-over or modification without Board approval | Outstanding | rate_change_classification (not read yet); modification_threshold (not read yet) |  |
| 16(2) | Single borrower or group limit of 25 percent of core capital | Outstanding |  |  |
| 16(3) | No overdraft facilities | Not applicable, documented |  |  |
| 3 to 9 | Objectives; Board and executive duties; lending criteria; funding records; internal audit; independent risk function | Not applicable, documented |  |  |
| **PART IV - ENFORCEMENT** | | | | |
| 17, 18 | Monetary and administrative penalties; auditor qualification | Not applicable, documented |  |  |

## Baselines - the acceptance ties read from the system

Generated 2026-10-14 08:30:44 from maiic_ifrs9_results2.

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

### F1. No restructure register (16(1)) - Open

- **What was found:** E-Banker's reschedule history (RS_02a to RS_02g) landed on 12 October 2026 and is read only for the instalment chart in force; no register of restructures with their Board approval exists and section D of the Return is null. (Until 14 October 2026 this finding said Barry was to obtain the Reschedule Report; it has arrived.)
- **Impact:** Restructured facilities cannot be reported or shown to have Board approval; IFRS 9 modification (5.4.3) waits on the same register.
- **Recommended action:** Dupleix builds the register from RS_02 (candidates confirmed by Credit), records the Board approval reference and fills section D.
- **Owner:** Dupleix / Credit

### F2. The prescribed call report form is not in the repository (15(3)) - Open

- **What was found:** The provisional return fills every line in the directive's order; the Registrar's own form has not been supplied, the loan loss reserve has no line, and the headings cite wrong paragraphs (sections 9 to 11, 12, 15, 17).
- **Impact:** The return is still transcribed by hand and its citations mislead.
- **Recommended action:** MAIIC Risk supplies the current form; Dupleix re-orders and re-words the lines to it, adds the reserve line and corrects the citations to 10, 14, 13, 16(1) and 15(3).
- **Owner:** MAIIC Risk / Dupleix

### F3. The RBM Classification report's day bands differed from the directive's (10) - Closed

- **What was found:** The older report classified every facility on one ladder (90, 180, 365 days). On 9 October 2026 the report, the provision comparison and the arrears ageing were put on one rule with the RBM Return, and the medium- and long-term bands were corrected to the Gazette's paragraph 10 (90/180/365/746), with a test at every boundary on both terms.
- **Impact:** None remaining.
- **Recommended action:** Done.
- **Owner:** Dupleix

### F4. Loss-age write-off and restoration to accrual are not built (12, 13(3)) - Open

- **What was found:** No loss-since date exists, so the paragraph 12 write-off deadline cannot be shown (10 loss facilities of 1,511.0m at September 2026 on the fresh bootstrap); a facility returns to accrual when its days fall, not when its arrears are paid.
- **Impact:** The Return can carry loss facilities past the deadline and restore accrual too early.
- **Recommended action:** Dupleix adds the loss-since date with a due-for-write-off flag, and an arrears-nil condition before restoration.
- **Owner:** Dupleix

### F5. Interest in suspense is not the directive's measure (13(1)) - Open

- **What was found:** Section C is one period's posted contractual interest less EIR interest; GL 1320 is not read and nothing is on the cash basis.
- **Impact:** The suspense figure on the Return does not tie to the ledger.
- **Recommended action:** Compute the cumulative uncollected interest on non-performing facilities on the cash basis and tie it to GL 1320.
- **Owner:** Dupleix / Finance

### F6. Large exposures and the 25 percent limit need core capital (11(3), 16(2)) - Open

- **What was found:** The concentration report uses a typed threshold; core capital and related-borrower groups are not held.
- **Impact:** The 10 percent large-exposure review and the 25 percent limit cannot be shown.
- **Recommended action:** Finance supplies core capital quarterly; Dupleix adds the two tests and borrower grouping.
- **Owner:** Finance / Dupleix

### F7. Two bases for the minimum provision (14 and Schedule) - Open

- **What was found:** The Return applies the rates to the gross carrying amount, the Provision Comparison report to EAD; an unused helper holds wrong rates.
- **Impact:** Two screens disagree for one month.
- **Recommended action:** Put the Provision Comparison on the gross carrying amount and delete the unused helper.
- **Owner:** Dupleix

## Section-by-section audit

### PART I - PRELIMINARY

**2. Definitions: short-, medium- and long-term facilities** - *Done*

- **What it requires:** A short-term facility has a repayment period of not more than 12 months; medium-term not less than 12 and not more than 60 months; long-term more than 60 months.
- **What the engine does:** The staging thresholds are keyed by facility class and tenor in months (DEFAULT min_tenor 0: short-term; min_tenor 13: medium and long); the RBM Return and the RBM Classification report put a tenor of 12 months or less in short-term (RbmReturnService::classify, tenor <= 12).
- **General comment:** The Gazette's medium-term definition ('not less than 12 months') overlaps the short-term one at exactly 12 months; the system reads 12 months as short-term, the more prudent reading for days past due. No test pins tenor 12 against 13.
- **Compliance comment:** Compliant; the 12-month overlap is an interpretation MAIIC Risk should confirm.
- **Where to see it:** Settings, Staging basis: /setting?tab=staging; Reports: RBM Classification /ifrs9-reports/rbm-classification; IFRS 9 vs RBM /ifrs9-reports/ifrs9-vs-rbm
- **Governance setting:** `dpd_basis`
- **Test that proves it:** `Tests\Feature\Eir\StagingThresholdSeederTest::test_short_term_facilities_are_stage_3_at_91_days_as_the_directive_classifies_them; Tests\Feature\Rbm\RbmReturnServiceTest::test_the_directive_bands_by_term`
- **Reviewer sign-off:** ____________________   Date: ____________

### PART III - REQUIREMENTS

**4(b)(vi), 4(b)(x). Board-approved written policies: write-offs of loss facilities; restructuring** - *Evidence needed from MAIIC*

- **What it requires:** The Board adopts and ensures implementation of a written policy covering, among other things, write-offs of loss credit facilities and restructuring of credit facilities.
- **What the engine does:** The system holds the write-offs it can see: WriteOffClassifier identifies ledger write-offs (type 300; a batch whose other leg debits GL 1064; ACCOUNT CLOSURE on a status-H account) and the write-off register (eir-write-offs) records one not on the loan account, proposed by one person and approved by another. On the fresh bootstrap: 22 ledger write-offs of 60,778,466.86 and an empty register. No Board approval is recorded against either, and no restructuring policy is held.
- **General comment:** Built 13 October 2026 (commit 220a5a6).
- **Compliance comment:** Evidence needed: MAIIC's Board-approved write-off and restructuring policies.
- **Where to see it:** EIR & Revenue Recognition, EIR Data, Write-offs: /eir-write-offs
- **Test that proves it:** `Tests\Feature\Eir\WriteOffTest::test_a_register_write_off_reaches_the_engines_only_when_a_different_person_approves_it`
- **Reviewer sign-off:** ____________________   Date: ____________

**5(1)(b). Written valuation methods for collateral** - *Evidence needed from MAIIC*

- **What it requires:** An executive officer shall have written valuation methods for determining the fair value of collateral and their impact on capital and earnings.
- **What the engine does:** The E-Banker security register (P2_11, 185 rows) is landed; the collateral register and allocation screens exist with a checked import, but on the fresh bootstrap collateral_registers and collateral_allocations hold no rows, and the LGD engine does not read collateral.
- **Compliance comment:** Evidence needed from Credit: the valuation method and the valuations; the LGD link is Dupleix's (IFRS 9 impairment workbook B5.5.55).
- **Where to see it:** Collateral Management, Collateral Register: /collateral/register
- **Reviewer sign-off:** ____________________   Date: ____________

**10. Classification of credit facilities** - *Done*

- **What it requires:** Classify every facility as standard, special mention, substandard, doubtful or loss: short-term at 0-30 / 31-90 / 91-180 / 181-365 / over 365 days overdue; medium and long term at 0-90 / 91-180 / 181-365 / 366-746 / over 746 days.
- **What the engine does:** RbmReturnService::classify applies the paragraph 10 bands by term (BANDS short 30/90/180/365, long 90/180/365/746); the RBM Classification report, the provision comparison and the arrears ageing use the same rule. Days are counted from the oldest overdue instalment; since commit 216aee2 the count reads E-Banker's instalment chart in force at each month-end (InstalmentChartHistory).
- **General comment:** Paragraph 10 makes 31-90 days 'standard' for a medium- or long-term facility; IFRS 9 Stage 2 nevertheless starts at 31 days in every class (the B5.5.11 presumption). The LONG_TERM threshold row proposing Stage 2 at 91 days for medium- and long-term facilities only (short-term stays at 31) is seeded future-dated (2099) and inactive pending CFO sign-off.
- **Compliance comment:** Compliant.
- **Where to see it:** Reports: RBM Classification /ifrs9-reports/rbm-classification; IFRS 9 vs RBM /ifrs9-reports/ifrs9-vs-rbm; Reports: RBM Return (provisional) /rbm-return
- **Governance setting:** `dpd_basis`
- **Test that proves it:** `Tests\Feature\Rbm\RbmReturnServiceTest::test_the_directive_bands_by_term; Tests\Feature\Rbm\RbmReturnServiceTest::test_the_rbm_classification_report_agrees_with_the_return_on_every_loan`
- **Reviewer sign-off:** ____________________   Date: ____________

**11(1), 11(2). Quarterly review of all facilities; prompt reclassification** - *Done*

- **What it requires:** Review all credit facilities quarterly for classification and impairment; on a significant deterioration between reviews, promptly reclassify and make the provision.
- **What the engine does:** The loan book is built, staged and classified every month (eir:stage for each period; the RBM Return for any period), which is more frequent than the quarter the directive asks for.
- **General comment:** Paragraph 9(2) (the risk function's quarterly review) is a governance duty the monthly figures support.
- **Compliance comment:** Compliant (monthly).
- **Where to see it:** Reports: RBM Return (provisional) /rbm-return; Workspace: /workspace
- **Governance setting:** `dpd_basis; stage3_missed_instalments`
- **Test that proves it:** `Tests\Feature\Rbm\RbmReturnServiceTest::test_the_return_fills_the_sections_from_the_book`
- **Reviewer sign-off:** ____________________   Date: ____________

**11(3). Review each large exposure individually** - *Partially done*

- **What it requires:** Review each large exposure (paragraph 2: 10 percent or more of core capital, to a person or group of related parties) on an individual item basis.
- **What the engine does:** The Concentration and large exposures report lists single names over a threshold typed on the screen (default 1,000,000; Ifrs9ReportsController::concentration). The system holds neither core capital nor groups of related borrowers, so the 10 percent test is not applied.
- **Compliance comment:** Partially done: the list exists; the threshold is not the directive's.
- **Where to see it:** Reports: Concentration and large exposures /ifrs9-reports/concentration
- **Reviewer sign-off:** ____________________   Date: ____________

**12. Write off loss facilities in the following quarter** - *Outstanding*

- **What it requires:** Write off all facilities classified as loss from the books in the quarter following the one in which the loss classification was made.
- **What the engine does:** Not built: nothing records when a facility first became loss, so the age of a loss classification is unknown and no facility is flagged as due for write-off. The write-off register is not linked to the RBM Return. On the fresh bootstrap at September 2026, 10 medium- and long-term facilities of 1,511.0m are in the loss class.
- **General comment:** Figures from the fresh bootstrap maiic_ifrs9_results2 (13 October 2026, before commit 216aee2), main book (the six MAIIC and FInES loan GLs) at September 2026.
- **Compliance comment:** Outstanding (Dupleix): a loss-since date and a due-for-write-off flag on the Return.
- **Where to see it:** Reports: RBM Return (provisional) /rbm-return; /eir-write-offs
- **Reviewer sign-off:** ____________________   Date: ____________

**13(1). Non-accrual: cease accrual, reverse uncollected interest, recognise interest only when received in cash** - *Partially done*

- **What it requires:** Place all non-performing facilities on non-accrual: cease accruing interest in income, reverse uncollected interest previously accrued, and recognise interest only when received in cash.
- **What the engine does:** Section C of the RBM Return reports, for the non-performing facilities, one period's contractual interest posted by E-Banker less the EIR interest recognised on the net basis. That is not the directive's measure: it is not cumulative, it is not on the cash basis (interest received), and GL 1320 (interest in suspense) is read nowhere. Under IFRS 9 the EIR engine recognises Stage 3 interest on the net carrying amount (5.4.1(b)), which is not the directive's cash basis either.
- **Compliance comment:** Partially done: the gap between the posted and the recognised interest is shown; the directive's suspense balance is not computed or tied to GL 1320.
- **Where to see it:** Reports: RBM Return (provisional) /rbm-return (section C); EIR Calculations, GL Reconciliation: /eir-reconciliation
- **Governance setting:** `stage3_interest_basis`
- **Reviewer sign-off:** ____________________   Date: ____________

**13(2). Non-accrual regardless of collateral** - *Done*

- **What it requires:** A non-performing facility is placed on non-accrual whatever collateral is held against it.
- **What the engine does:** The classification reads only the days overdue and the term (RbmReturnService::classify takes no collateral), so security never keeps a facility out of the non-performing classes.
- **Compliance comment:** Compliant.
- **Where to see it:** Reports: RBM Return (provisional) /rbm-return
- **Test that proves it:** `Tests\Feature\Rbm\RbmReturnServiceTest::test_the_directive_bands_by_term`
- **Reviewer sign-off:** ____________________   Date: ____________

**13(3). Restore to accrual only when all arrears are paid** - *Outstanding*

- **What it requires:** Restore a facility to accrual only when the borrower has paid all past-due principal and interest.
- **What the engine does:** Not built: the Return reclassifies each period from the day count alone, so a facility leaves the non-performing classes as soon as its days overdue fall below the band, whether or not all arrears are paid. The IFRS 9 cure period (stage_cure_months) holds the stage, not the RBM accrual status.
- **Compliance comment:** Outstanding (Dupleix): hold a non-performing facility on non-accrual until its arrears are nil.
- **Where to see it:** Reports: RBM Return (provisional) /rbm-return
- **Governance setting:** `stage_cure_months (IFRS 9 stage only)`
- **Reviewer sign-off:** ____________________   Date: ____________

**14 and Schedule. Minimum provisions by class** - *Partially done*

- **What it requires:** Provide at least 0 percent on standard, 5 on special mention, 20 on substandard, 50 on doubtful and 100 on loss.
- **What the engine does:** The RBM Return (section B) and the RBM Classification report apply the five rates to the gross carrying amount. The Provision Comparison report applies the same rates to EAD (carrying amount plus undrawn commitments; Ifrs9ReportsController::provisionComparison), so two screens give different minimums for one month. An unused helper (Ifrs9ReportsController::rbmRate) still holds wrong rates (1, 1, 50 percent). The directive says nothing of netting security; the Return does not net it.
- **General comment:** Until 14 October 2026 this row was keyed '12' and said 'net of eligible security', which is not in the Gazette.
- **Compliance comment:** Partially done: the rates are right on the Return; the Provision Comparison must use the same base.
- **Where to see it:** Reports: RBM Return (provisional) /rbm-return (section B); Reports: RBM Classification /ifrs9-reports/rbm-classification; IFRS 9 vs RBM /ifrs9-reports/ifrs9-vs-rbm; Reports: Provision Comparison /ifrs9-reports/provision-comparison
- **Test that proves it:** `Tests\Feature\Rbm\RbmReturnServiceTest::test_the_return_fills_the_sections_from_the_book`
- **Reviewer sign-off:** ____________________   Date: ____________

**15(1). Excess of the regulatory provision over IFRS to a loan loss reserve** - *Partially done*

- **What it requires:** Where the directive's provisions exceed the IFRS impairment, the excess is an appropriation of retained earnings to a loan loss reserve.
- **What the engine does:** Section B's totals set the minimum provision against the IFRS 9 allowance and show the higher of the two; there is no line labelled loan loss reserve. On the fresh bootstrap at September 2026 the minimum is about 3.62bn against an IFRS 9 allowance of 11.57bn (undiscounted), so the reserve is nil in total, although the loss class alone carries a 1.51bn minimum against 0.86bn of allowance.
- **General comment:** Figures from the fresh bootstrap maiic_ifrs9_results2 (13 October 2026, before commit 216aee2), main book (the six MAIIC and FInES loan GLs) at September 2026.
- **Compliance comment:** Partially done: the comparison is there; the reserve line is not.
- **Where to see it:** Reports: RBM Return (provisional) /rbm-return (section B totals)
- **Reviewer sign-off:** ____________________   Date: ____________

**15(2), 15(4). The reserve is not capital; released only after recoveries or with the Registrar's approval** - *Not applicable, documented*

- **What it requires:** The loan loss reserve is not capital where no longer available for unidentified losses; it is credited back to retained earnings only after recoveries or with the Registrar's prior approval.
- **What the engine does:** An accounting and capital treatment in MAIIC's books, outside the ECL and EIR system.
- **General comment:** Finance applies it when it posts the reserve.
- **Compliance comment:** Not applicable, documented: Finance's posting, not a system output.
- **Where to see it:** 
- **Reviewer sign-off:** ____________________   Date: ____________

**15(3). Report loan loss reserves on the call report as prescribed** - *Partially done*

- **What it requires:** Report loan loss reserves separately on the call report in the form the Registrar prescribes.
- **What the engine does:** RbmReturnService fills a provisional return (A classification by class and term, B minimum provision against the IFRS 9 allowance, C interest in suspense, D restructured facilities, E security held) on the screen and as CSV, Excel and PDF. The prescribed call report form is not in the repository, the reserve is not a line of its own, section D is empty, and the section headings on the screen and the download cite the wrong paragraphs (RbmReturnController: 'sections 9 to 11', 'section 12', 'section 15', 'section 17'; they should be 10, 14 and 15, 16(1) and 15(3)).
- **General comment:** Until 14 October 2026 this row was keyed '17', which is the monetary penalties paragraph.
- **Compliance comment:** Partially done: the lines are filled; the prescribed form, the reserve line and the citations are owed.
- **Where to see it:** Reports: RBM Return (provisional) /rbm-return
- **Governance setting:** `dpd_basis`
- **Test that proves it:** `Tests\Feature\Rbm\RbmReturnServiceTest::test_the_return_fills_the_sections_from_the_book`
- **Reviewer sign-off:** ____________________   Date: ____________

**16(1). No restructure, renegotiation, roll-over or modification without Board approval** - *Outstanding*

- **What it requires:** An institution shall not restructure, renegotiate, roll over or modify the terms of a credit facility unless approved by the Board.
- **What the engine does:** Not built: there is no restructure register and no record of Board approval. E-Banker's reschedule history (RS_02a to RS_02g, landed 12 October 2026; 46,230 RS_02a rows on the fresh bootstrap) is read only to find the instalment chart in force for staging (InstalmentChartHistory); section D of the Return is null (RbmReturnService: 'awaited from the vendor', which is stale).
- **General comment:** Until 14 October 2026 this row was keyed '15' and waited on the vendor; the data has arrived and the register is Dupleix's build.
- **Compliance comment:** Outstanding (Dupleix): a restructure register from RS_02 with the Board approval reference, feeding section D.
- **Where to see it:** Reports: RBM Return (provisional) /rbm-return (section D); Imports & Feeds, E-Banker Loads: /eir-feed
- **Governance setting:** `rate_change_classification (not read yet); modification_threshold (not read yet)`
- **Reviewer sign-off:** ____________________   Date: ____________

**16(2). Single borrower or group limit of 25 percent of core capital** - *Outstanding*

- **What it requires:** Facilities to a single borrower or group of related borrowers shall not exceed 25 percent of core capital without the Registrar's prior approval.
- **What the engine does:** Not built: core capital is not held in the system and borrowers are not grouped; the concentration report lists names over a typed amount only.
- **Compliance comment:** Outstanding: Finance supplies core capital each quarter; Dupleix adds the 25 percent test and the related-borrower grouping.
- **Where to see it:** Reports: Concentration and large exposures /ifrs9-reports/concentration
- **Reviewer sign-off:** ____________________   Date: ____________

**16(3). No overdraft facilities** - *Not applicable, documented*

- **What it requires:** An institution shall not extend any overdraft credit facilities.
- **What the engine does:** A lending restriction; the in-scope loan GLs (1050101 to 1050103, 1050201, 1050202, 1050401) are term-loan GLs and the system holds no overdraft product.
- **Compliance comment:** Not applicable, documented: no overdraft product in the book.
- **Where to see it:** 
- **Reviewer sign-off:** ____________________   Date: ____________

**3 to 9. Objectives; Board and executive duties; lending criteria; funding records; internal audit; independent risk function** - *Not applicable, documented*

- **What it requires:** Board approval of facilities and policies, an analytical framework, MIS, a credit bureau report before lending, records of undisbursed lines, yearly internal audit of the portfolio, an independent risk function.
- **What the engine does:** Governance and lending-process duties of MAIIC; the system supplies the classified book, the reports and the audit trail that support them.
- **General comment:** Paragraphs 4(b)(vi), 4(b)(x) and 5(1)(b) are audited in their own rows.
- **Compliance comment:** Not applicable, documented: policy and process, outside the system.
- **Where to see it:** 
- **Reviewer sign-off:** ____________________   Date: ____________

### PART IV - ENFORCEMENT

**17, 18. Monetary and administrative penalties; auditor qualification** - *Not applicable, documented*

- **What it requires:** The Registrar may impose penalties; an institution that fails to comply has its financial statements qualified by the external auditor.
- **What the engine does:** Enforcement provisions; nothing for the system to do.
- **Compliance comment:** Not applicable, documented.
- **Where to see it:** 
- **Reviewer sign-off:** ____________________   Date: ____________
