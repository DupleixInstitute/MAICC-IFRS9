# RBM Directive 2018 - credit risk management for DFIs - compliance audit

Financial Services (Credit Risk Management for Development Finance Institutions) Directive 2018, Reserve Bank of Malawi (Gazette 13 July 2018, pages 680 to 685).

Malawi Agricultural and Industrial Investment Company (MAIIC) - IFRS 9 EIR and ECL system - build of 14 October 2026 (branch eir_revenue_recognition, commit abe140d) against specification v4. As of 14 October 2026: the code at commit abe140d (the uncommitted screen changes in the working tree do not alter any row) and the fresh bootstrap maiic_ifrs9_results2 of 13 October 2026, made before commit 216aee2, read with SELECTs only. Paragraph references re-keyed to the Gazette on 14 October 2026 (they cited 9, 11, 12, 15 and 17 for the wrong subjects). The Gazette's text layer misprints the Schedule's short-term rows, so the short-term bands are read from paragraph 10. Reviewer: MAIIC Risk. The Excel workbook of the same name is the working copy (drop-down statuses, hyperlinked contents, reviewer sign-off column, a live Baselines sheet); this document is its printable twin.

Source: docs/regulatory/RBM Financial Services (Credit Risk Management for DFIs) Directive 2018 pp42-47.pdf (Gazette 13 July 2018, pages 680 to 685). Spec v4 section 3.6, decision D31; the O13 rebuttal paper of 8 October 2026.

Generated 2026-10-14 10:44:41; the Baselines were read from the database `maiic_ifrs9_wire`.

## Status summary

| Status | Meaning | Sections |
|---|---|---|
| **Done** | Implemented in the system and visible in an approved output; the test in column 11 fails if the behaviour changes. | 6 |
| **Partially done** | The mechanics are in place, but a parameter, an input or a piece of evidence still differs from the requirement. | 6 |
| **Outstanding** | Required for MAIIC and not built. | 1 |
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
| 12 | Write off loss facilities in the following quarter | Partially done |  | Tests\Feature\Rbm\RbmReturnServiceTest::test_loss_facilities_are_listed_with_the_quarter_they_must_be_written_off_by |
| 13(1) | Non-accrual: cease accrual, reverse uncollected interest, recognise interest only when received in cash | Partially done | stage3_interest_basis | Tests\Feature\Rbm\RbmReturnServiceTest::test_cumulative_interest_in_suspense_is_measured_and_tied_to_gl_1320 |
| 13(2) | Non-accrual regardless of collateral | Done |  | Tests\Feature\Rbm\RbmReturnServiceTest::test_the_directive_bands_by_term |
| 13(3) | Restore to accrual only when all arrears are paid | Done |  | Tests\Feature\Rbm\RbmReturnServiceTest::test_paragraph_13_3_holds_a_facility_at_substandard_until_its_arrears_are_nil |
| 14 and Schedule | Minimum provisions by class | Done |  | Tests\Feature\Rbm\RbmReturnServiceTest::test_the_return_fills_the_sections_from_the_book |
| 15(1) | Excess of the regulatory provision over IFRS to a loan loss reserve | Partially done |  | Tests\Feature\Rbm\RbmReturnServiceTest::test_the_allowance_is_the_booked_discounted_allowance_and_a_gap_is_named |
| 15(2), 15(4) | The reserve is not capital; released only after recoveries or with the Registrar's approval | Not applicable, documented |  |  |
| 15(3) | Report loan loss reserves on the call report as prescribed | Partially done | dpd_basis | Tests\Feature\Rbm\RbmReturnServiceTest::test_the_return_fills_the_sections_from_the_book |
| 16(1) | No restructure, renegotiation, roll-over or modification without Board approval | Partially done | rate_change_classification; modification_threshold | Tests\Feature\Rbm\RbmReturnServiceTest::test_section_d_lists_the_confirmed_restructures_with_their_board_approval; Tests\Feature\Eir\RestructureRegisterTest::test_the_rbm_section_d_reports_only_confirmed_restructures_and_names_a_missing_board_approval; Tests\Feature\Eir\RestructureDetectionWiringTest::test_the_detection_step_finds_and_never_confirms_and_the_bootstrap_line_counts_the_candidates |
| 16(2) | Single borrower or group limit of 25 percent of core capital | Outstanding |  |  |
| 16(3) | No overdraft facilities | Not applicable, documented |  |  |
| 3 to 9 | Objectives; Board and executive duties; lending criteria; funding records; internal audit; independent risk function | Not applicable, documented |  |  |
| **PART IV - ENFORCEMENT** | | | | |
| 17, 18 | Monetary and administrative penalties; auditor qualification | Not applicable, documented |  |  |

## Baselines - the acceptance ties read from the system

Generated 2026-10-14 10:44:41 from maiic_ifrs9_wire.

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
| Pack inventory: ebanker-pack-2026-10-09: every file as committed and landed with its manifest rows and SHA-256; 13 received files not landed, each with its reason in the manifest | 12 extracts, 111,513 rows | 12 extracts, 111,513 rows | PASS |
| Pack inventory: ebanker-pack-2026-10-12: every file as committed and landed with its manifest rows and SHA-256; 2 received files not landed, each with its reason in the manifest | 39 extracts, 511,785 rows | 39 extracts, 511,785 rows | PASS |
| Pack inventory: ebanker-pack-2026-10-12-p5: every file as committed and landed with its manifest rows and SHA-256; 1 received files not landed, each with its reason in the manifest | 8 extracts, 470 rows | 8 extracts, 470 rows | PASS |
| Pack inventory: ebanker-pack-2026-10-12-p6: every file as committed and landed with its manifest rows and SHA-256; 0 received files not landed, each with its reason in the manifest | 3 extracts, 236 rows | 3 extracts, 236 rows | PASS |
| Pack inventory: ebanker-pack-2026-10-13-p7: every file as committed and landed with its manifest rows and SHA-256; 0 received files not landed, each with its reason in the manifest | 6 extracts, 32,244 rows | 6 extracts, 32,244 rows | PASS |
| Pack inventory: ebanker-pack-2026-10-13-p8: every file as committed and landed with its manifest rows and SHA-256; 6 received files not landed, each with its reason in the manifest | 4 extracts, 103 rows | 4 extracts, 103 rows | PASS |
| Pack inventory: ebanker-pack-2026-10-14: every file as committed and landed with its manifest rows and SHA-256; 13 received files not landed, each with its reason in the manifest | 2 extracts, 426 rows | 2 extracts, 426 rows | PASS |
| Pack inventory: manifest.json (packs 1 and 2, data dictionary, trial balances, take-on): every file as committed and landed with its manifest rows and SHA-256; 3 received files not landed, each with its reason in the manifest; 30 workbooks and scripts, 23 landed by the bootstrap | 54 extracts, 85,174 rows, 23 workbooks | 54 extracts, 85,174 rows, 23 workbooks | PASS |
| Take-on worksheet (CV_10) against the take-on postings of 31 Jul 2024: term loans on the worksheet with take-on postings | 77.00 | 77.00 | PASS |
| Take-on worksheet (CV_10) against the take-on postings of 31 Jul 2024: loans whose principal taken on (301) = NEWDISBURS_AMT, to the cent | 76.00 | 76.00 | PASS |
| Take-on worksheet (CV_10) against the take-on postings of 31 Jul 2024: loans whose opening interest (303) = TOTAL_INT_CHARG, to the cent | 76.00 | 76.00 | PASS |
| Take-on worksheet (CV_10) against the take-on postings of 31 Jul 2024: loans whose opening recoveries (305) = TOTA_REPAY_UPTO, to the cent | 72.00 | 72.00 | PASS |
| Ledger copy CUMVOUCH_BK240824 (CV_04a) to 31 Dec 2023 against the GL opening balances of 1 Jan 2024 (CV_14), 4 loan GLs: sum of the differences | 0.50 | 0.50 | PASS |
| Loan book 2025-12, MAIIC's loan GLs: carrying amount | no golden value committed (book_2025-12_maiic_amount) | 14,841,212,779.54 | FAIL |
| Loan book 2025-12, MAIIC's loan GLs: rows | no golden value committed (book_2025-12_maiic_loans) | 123.00 | FAIL |
| Loan book 2025-12, Mega Farm programme GLs: carrying amount | no golden value committed (book_2025-12_megafarm_amount) | 54,498,560,819.60 | FAIL |
| Loan book 2025-12, Mega Farm programme GLs: accounts with a balance | no golden value committed (book_2025-12_megafarm_accounts) | 3,671.00 | FAIL |
| Loan book 2026-09, MAIIC's loan GLs: carrying amount | no golden value committed (book_2026-09_maiic_amount) | 23,189,580,235.43 | FAIL |
| Loan book 2026-09, MAIIC's loan GLs: rows | no golden value committed (book_2026-09_maiic_loans) | 134.00 | FAIL |
| Loan book 2026-09, Mega Farm programme GLs: carrying amount | no golden value committed (book_2026-09_megafarm_amount) | 56,180,761,113.43 | FAIL |
| Loan book 2026-09, Mega Farm programme GLs: accounts with a balance | no golden value committed (book_2026-09_megafarm_accounts) | 3,618.00 | FAIL |
| Ledger write-offs on MAIIC loans (WriteOffClassifier): postings | no golden value committed (ledger_writeoffs_postings) | 22.00 | FAIL |
| Ledger write-offs on MAIIC loans (WriteOffClassifier): amount | no golden value committed (ledger_writeoffs_amount) | 60,778,466.86 | FAIL |
| ECL 2025-12, MAIIC's book: undiscounted | no golden value committed (ecl_2025-12_maiic_undiscounted) | 3,955,543,846.37 | FAIL |
| ECL 2025-12, MAIIC's book: discounted at the EIR (booked) | no golden value committed (ecl_2025-12_maiic_discounted) | 2,593,174,242.30 | FAIL |
| ECL 2025-12, MAIIC's book: loans discounted at the EIR | no golden value committed (ecl_2025-12_maiic_discounted_loans) | 56.00 | FAIL |
| ECL 2025-12, MAIIC's book, stage 1: discounted | no golden value committed (ecl_2025-12_maiic_stage1_discounted) | 61,318,291.61 | FAIL |
| ECL 2025-12, MAIIC's book, stage 2: discounted | no golden value committed (ecl_2025-12_maiic_stage2_discounted) | 315,409,106.41 | FAIL |
| ECL 2025-12, MAIIC's book, stage 3: discounted | no golden value committed (ecl_2025-12_maiic_stage3_discounted) | 2,216,446,844.28 | FAIL |
| ECL 2025-12, Mega Farm programme (latest run) | no golden value committed (ecl_2025-12_megafarm_programme) | none | FAIL |
| ECL 2025-12, Mega Farm: MAIIC's share | no golden value committed (ecl_2025-12_megafarm_maiic_share) | none | FAIL |
| ECL 2026-09, MAIIC's book: undiscounted | no golden value committed (ecl_2026-09_maiic_undiscounted) | 11,569,458,915.88 | FAIL |
| ECL 2026-09, MAIIC's book: discounted at the EIR (booked) | no golden value committed (ecl_2026-09_maiic_discounted) | 7,118,643,553.12 | FAIL |
| ECL 2026-09, MAIIC's book: loans discounted at the EIR | no golden value committed (ecl_2026-09_maiic_discounted_loans) | 62.00 | FAIL |
| ECL 2026-09, MAIIC's book, stage 1: discounted | no golden value committed (ecl_2026-09_maiic_stage1_discounted) | 219,546,033.90 | FAIL |
| ECL 2026-09, MAIIC's book, stage 2: discounted | no golden value committed (ecl_2026-09_maiic_stage2_discounted) | 183,786,390.69 | FAIL |
| ECL 2026-09, MAIIC's book, stage 3: discounted | no golden value committed (ecl_2026-09_maiic_stage3_discounted) | 6,715,311,128.53 | FAIL |
| ECL 2026-09, Mega Farm programme (latest run) | no golden value committed (ecl_2026-09_megafarm_programme) | 45,782,301,503.37 | FAIL |
| ECL 2026-09, Mega Farm: MAIIC's share | no golden value committed (ecl_2026-09_megafarm_maiic_share) | 2,289,115,075.17 | FAIL |
| EIR contracts locked | no golden value committed (eir_contracts_locked) | 14.00 | FAIL |
| EIR contracts blocked | no golden value committed (eir_contracts_blocked) | 33.00 | FAIL |
| EIR contracts solved (an EIR computed) | no golden value committed (eir_contracts_solved) | 14.00 | FAIL |
| EIR contracts in the contract master | no golden value committed (eir_contracts) | 144.00 | FAIL |
| Revenue 2024: EIR interest recognised | no golden value committed (revenue_2024_eir_interest) | 25,532,678.70 | FAIL |
| Revenue 2024: cash received | no golden value committed (revenue_2024_cash_received) | 34,200,000.00 | FAIL |
| Revenue 2024: roll-forward rows | no golden value committed (revenue_2024_rows) | 20.00 | FAIL |
| Revenue 2025: EIR interest recognised | no golden value committed (revenue_2025_eir_interest) | 663,970,866.88 | FAIL |
| Revenue 2025: cash received | no golden value committed (revenue_2025_cash_received) | 1,158,267,060.20 | FAIL |
| Revenue 2025: roll-forward rows | no golden value committed (revenue_2025_rows) | 126.00 | FAIL |
| Revenue 2026: EIR interest recognised | no golden value committed (revenue_2026_eir_interest) | 544,517,024.26 | FAIL |
| Revenue 2026: cash received | no golden value committed (revenue_2026_cash_received) | 2,381,610,987.63 | FAIL |
| Revenue 2026: roll-forward rows | no golden value committed (revenue_2026_rows) | 113.00 | FAIL |
| RBM return 2026-09: facilities classified loss (paragraph 12 list) | no golden value committed (rbm_2026-09_loss_facilities) | 10.00 | FAIL |
| RBM return 2026-09: balance of the loss list | no golden value committed (rbm_2026-09_loss_balance) | 1,510,956,083.45 | FAIL |

## Findings - items that need a decision or a fix

### F1. No restructure register (16(1)) - In progress

- **What was found:** E-Banker's reschedule history (RS_02a to RS_02g) landed on 12 October 2026 and was read only for the instalment chart in force; no register of restructures with their Board approval existed and section D of the Return was null. Status 14 October 2026: built and wired (uncommitted in the working tree): the register with maker-checker and the Board approval reference, the detector in the bootstrap and the month-end (detection only), and section D listing the confirmed restructures with their Board approval. On the scratch clone 70 candidates await classification.
- **Impact:** Until the candidates are classified the Return reports no restructured facility.
- **Recommended action:** Credit classifies the candidates; MAIIC records the Board approval of each restructure it confirms.
- **Owner:** Credit / MAIIC

### F2. The prescribed call report form is not in the repository (15(3)) - Open

- **What was found:** The provisional return fills every line in the directive's order; the Registrar's own form has not been supplied, the loan loss reserve has no line, and the headings cite wrong paragraphs (sections 9 to 11, 12, 15, 17). Status 14 October 2026: the citations are corrected (10 and 11, 12, 13, 14 and the Schedule, 15, 16(1), 15(3)) and the reserve line added; the form is still awaited.
- **Impact:** The return is still transcribed by hand.
- **Recommended action:** MAIIC Risk supplies the current form; Dupleix re-orders and re-words the lines to it.
- **Owner:** MAIIC Risk / Dupleix

### F3. The RBM Classification report's day bands differed from the directive's (10) - Closed

- **What was found:** The older report classified every facility on one ladder (90, 180, 365 days). On 9 October 2026 the report, the provision comparison and the arrears ageing were put on one rule with the RBM Return, and the medium- and long-term bands were corrected to the Gazette's paragraph 10 (90/180/365/746), with a test at every boundary on both terms.
- **Impact:** None remaining.
- **Recommended action:** Done.
- **Owner:** Dupleix

### F4. Loss-age write-off and restoration to accrual are not built (12, 13(3)) - Closed

- **What was found:** No loss-since date exists, so the paragraph 12 write-off deadline cannot be shown (10 loss facilities of 1,511.0m at September 2026 on the fresh bootstrap); a facility returns to accrual when its days fall, not when its arrears are paid. Status 14 October 2026: paragraph 12 is built (loss since, age, write-off quarter, status; 7 of the 10 overdue); 13(3) is not.
- **Status 16 October 2026:** 13(3) is now built (`RbmReturnService::priorClasses()`, the `held_13_3` hold at Substandard until days past due reach nil; see the 13(3) row above). Both halves of this finding are closed.
- **Impact:** None remaining in the Return. The day-count-only reports (RBM Classification, IFRS 9 vs RBM, Provision Comparison, Concentration) do not yet apply the 13(3) hold — a separate, named, bounded gap (see the 13(3) row above), not a reopening of this finding.
- **Recommended action:** None remaining for the Return. Credit and Finance still act on the 7 overdue loss facilities under paragraph 12.
- **Owner:** Dupleix / Credit

### F5. Interest in suspense is not the directive's measure (13(1)) - In progress

- **What was found:** Section C is one period's posted contractual interest less EIR interest; GL 1320 is not read and nothing is on the cash basis. Status 14 October 2026: the cumulative interest charged since each facility became non-performing is computed and set against GL 1320; the cash-basis split is a named gap and the trial balances carry no 1320 line from March 2026.
- **Impact:** The suspense on the Return does not yet tie to a posted suspense balance.
- **Recommended action:** Finance posts (or confirms) interest suspense on GL 1320; the cash received is split between principal and interest (allocation rule from Finance) so the cash-basis income can be deducted.
- **Owner:** Finance / Dupleix

### F6. Large exposures and the 25 percent limit need core capital (11(3), 16(2)) - Open

- **What was found:** The concentration report uses a typed threshold; core capital and related-borrower groups are not held.
- **Impact:** The 10 percent large-exposure review and the 25 percent limit cannot be shown.
- **Recommended action:** Finance supplies core capital quarterly; Dupleix adds the two tests and borrower grouping.
- **Owner:** Finance / Dupleix

### F7. Two bases for the minimum provision (14 and Schedule) - Closed

- **What was found:** The Return applies the rates to the gross carrying amount, the Provision Comparison report to EAD; an unused helper holds wrong rates. Status 14 October 2026: done, both on the gross carrying amount; the helper is deleted.
- **Impact:** None remaining.
- **Recommended action:** Done.
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

**12. Write off loss facilities in the following quarter** - *Partially done*

- **What it requires:** Write off all facilities classified as loss from the books in the quarter following the one in which the loss classification was made.
- **What the engine does:** Built 14 October 2026: the RBM Return's section 'Loss facilities to be written off' lists each loss facility with the month it became loss on the loan books (the unbroken run of loss classification by days past due and term; 'at or before' where the run reaches the first month held), its age in months, the quarter-end by which it must be written off and its status (overdue, due this quarter, due next quarter). On the fresh bootstrap at September 2026: 10 facilities of 1,510,956,083.45, 7 of them (1,030,047,803.06) past the write-off quarter. The write-off itself is a person's act in the write-off register; the system shows the deadline, it does not write off.
- **General comment:** Figures from the fresh bootstrap maiic_ifrs9_results2 (13 October 2026, before commit 216aee2), main book (the six MAIIC and FInES loan GLs) at September 2026. Until 14 October 2026 this row was Outstanding.
- **Compliance comment:** Partially done: the list and the deadline are built; the overdue facilities are for Credit and Finance to write off (or explain) in the register.
- **Where to see it:** Reports: RBM Return (provisional) /rbm-return (Loss facilities to be written off); /eir-write-offs
- **Test that proves it:** `Tests\Feature\Rbm\RbmReturnServiceTest::test_loss_facilities_are_listed_with_the_quarter_they_must_be_written_off_by`
- **Reviewer sign-off:** ____________________   Date: ____________

**13(1). Non-accrual: cease accrual, reverse uncollected interest, recognise interest only when received in cash** - *Partially done*

- **What it requires:** Place all non-performing facilities on non-accrual: cease accruing interest in income, reverse uncollected interest previously accrued, and recognise interest only when received in cash.
- **What the engine does:** Section C of the RBM Return reports the month's contractual interest posted less the EIR interest on the net basis and, since 14 October 2026, the cumulative measure: the contractual interest charged on each facility non-performing at the month-end since the month it became non-performing on the loan books, with the cash received in the same window beside it and GL 1320 from the trial balance as the tie (with the difference). At September 2026 on the fresh bootstrap: 821,916,269.33 charged since non-performing on 50 facilities, 760,304,079.21 received; the trial balances from March 2026 carry no 1320 line (the last held is 21,865,867.35 credit at February 2026, unchanged since January 2025, so E-Banker posts no suspense). Not measured: the part of the cash received that is interest, because the ledger does not split a repayment, so the cash-basis income the directive allows is shown as a named gap.
- **General comment:** Until 14 October 2026 section C was one period's figure only and GL 1320 was read nowhere.
- **Compliance comment:** Partially done: the cumulative suspense and the GL 1320 tie are built; the cash-basis split and Finance's posting of suspense are open.
- **Where to see it:** Reports: RBM Return (provisional) /rbm-return (section C); EIR Calculations, GL Reconciliation: /eir-reconciliation
- **Governance setting:** `stage3_interest_basis`
- **Test that proves it:** `Tests\Feature\Rbm\RbmReturnServiceTest::test_cumulative_interest_in_suspense_is_measured_and_tied_to_gl_1320`
- **Reviewer sign-off:** ____________________   Date: ____________

**13(2). Non-accrual regardless of collateral** - *Done*

- **What it requires:** A non-performing facility is placed on non-accrual whatever collateral is held against it.
- **What the engine does:** The classification reads only the days overdue and the term (RbmReturnService::classify takes no collateral), so security never keeps a facility out of the non-performing classes.
- **Compliance comment:** Compliant.
- **Where to see it:** Reports: RBM Return (provisional) /rbm-return
- **Test that proves it:** `Tests\Feature\Rbm\RbmReturnServiceTest::test_the_directive_bands_by_term`
- **Reviewer sign-off:** ____________________   Date: ____________

**13(3). Restore to accrual only when all arrears are paid** - *Done*

- **What it requires:** Restore a facility to accrual only when the borrower has paid all past-due principal and interest.
- **What the engine does, status 16 October 2026:** Built: `RbmReturnService::build()` reads each facility's class at the prior month-end (`priorClasses()`); a facility non-performing then (Substandard, Doubtful or Loss) is held at Substandard this month, whatever the day count alone would compute, for as long as its days past due is not nil (`held_13_3`). A catch-up posting on part of the arrears, a schedule correction, or a confirmed modification that moves the oldest overdue instalment forward can therefore never, by itself, restore accrual while any arrears remain; only reaching nil days past due releases it. The count held each month is named in the return's own notes and on the "Per facility" download.
- **General comment, until 16 October 2026 (superseded by the above):** "Not built: the Return reclassifies each period from the day count alone, so a facility leaves the non-performing classes as soon as its days overdue fall below the band, whether or not all arrears are paid. The IFRS 9 cure period (stage_cure_months) holds the stage, not the RBM accrual status." stage_cure_months still governs the IFRS 9 stage only, a deliberately separate control (Staging Module Specification v1, section 5); 13(3) is now its own, ungoverned rule in `RbmReturnService`, matching the directive's own classification bands, which are likewise not governed.
- **Known, bounded gap:** the day-count-only SQL behind the RBM Classification, IFRS 9 vs RBM, Provision Comparison and Concentration reports (`Ifrs9ReportsController::rbmClassCase()`) does not apply this hold, so a facility the Return holds at Substandard can show a lower class in those reports until they are extended the same way. The Return (`/rbm-return`) is the filing of record.
- **Compliance comment:** Compliant.
- **Where to see it:** Reports: RBM Return (provisional) /rbm-return
- **Test that proves it:** `Tests\Feature\Rbm\RbmReturnServiceTest::test_paragraph_13_3_holds_a_facility_at_substandard_until_its_arrears_are_nil`
- **Reviewer sign-off:** ____________________   Date: ____________

**14 and Schedule. Minimum provisions by class** - *Done*

- **What it requires:** Provide at least 0 percent on standard, 5 on special mention, 20 on substandard, 50 on doubtful and 100 on loss.
- **What the engine does:** The RBM Return (section B), the RBM Classification report and, since 14 October 2026, the Provision Comparison report apply the five rates to the gross carrying amount (the Provision Comparison had used EAD); the unused helper with wrong rates (Ifrs9ReportsController::rbmRate) is deleted. The IFRS 9 allowance set against the minimum is the booked (discounted) allowance, with any loan that has no discounted figure counted and named. The directive says nothing of netting security; the Return does not net it.
- **General comment:** Until 14 October 2026 this row was keyed '12' and said 'net of eligible security', which is not in the Gazette; and it was Partially done (two bases).
- **Compliance comment:** Compliant.
- **Where to see it:** Reports: RBM Return (provisional) /rbm-return (section B); Reports: RBM Classification /ifrs9-reports/rbm-classification; IFRS 9 vs RBM /ifrs9-reports/ifrs9-vs-rbm; Reports: Provision Comparison /ifrs9-reports/provision-comparison
- **Test that proves it:** `Tests\Feature\Rbm\RbmReturnServiceTest::test_the_return_fills_the_sections_from_the_book`
- **Reviewer sign-off:** ____________________   Date: ____________

**15(1). Excess of the regulatory provision over IFRS to a loan loss reserve** - *Partially done*

- **What it requires:** Where the directive's provisions exceed the IFRS impairment, the excess is an appropriation of retained earnings to a loan loss reserve.
- **What the engine does:** Section B's totals set the minimum provision against the IFRS 9 allowance, show the higher of the two and, since 14 October 2026, a line labelled loan loss reserve (paragraph 15(1)): the minimum over the allowance. On the fresh bootstrap at September 2026 the minimum is 3,618,045,560.09 against an IFRS 9 allowance of 11,579,201,589.52 (booked; 38 loans at their undiscounted ECL of 4,460,558,036.40 because they have no discounted figure), so the reserve is nil in total, although the loss class alone carries a 1.51bn minimum against 0.89bn of allowance. The reserve is computed in total, as the paragraph reads.
- **General comment:** Figures from the fresh bootstrap maiic_ifrs9_results2 (13 October 2026, before commit 216aee2), main book (the six MAIIC and FInES loan GLs) at September 2026. Until 14 October 2026 there was no reserve line.
- **Compliance comment:** Partially done: the reserve line is built; the appropriation is a Finance entry, and the allowance it rests on is not wholly discounted yet.
- **Where to see it:** Reports: RBM Return (provisional) /rbm-return (section B totals)
- **Test that proves it:** `Tests\Feature\Rbm\RbmReturnServiceTest::test_the_allowance_is_the_booked_discounted_allowance_and_a_gap_is_named`
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
- **What the engine does:** RbmReturnService fills a provisional return (A classification by class and term, B minimum provision against the IFRS 9 allowance, C interest in suspense, D restructured facilities, E security held) on the screen and as CSV, Excel and PDF. The prescribed call report form is not in the repository, the reserve is not a line of its own, section D lists only restructures confirmed on the register (none yet), and the section headings on the screen and the download cite the wrong paragraphs (RbmReturnController: 'sections 9 to 11', 'section 12', 'section 15', 'section 17'; they should be 10, 14 and 15, 16(1) and 15(3)).
- **General comment:** Until 14 October 2026 this row was keyed '17', which is the monetary penalties paragraph.
- **Compliance comment:** Partially done: the lines are filled; the prescribed form, the reserve line and the citations are owed.
- **Where to see it:** Reports: RBM Return (provisional) /rbm-return
- **Governance setting:** `dpd_basis`
- **Test that proves it:** `Tests\Feature\Rbm\RbmReturnServiceTest::test_the_return_fills_the_sections_from_the_book`
- **Reviewer sign-off:** ____________________   Date: ____________

**16(1). No restructure, renegotiation, roll-over or modification without Board approval** - *Partially done*

- **What it requires:** An institution shall not restructure, renegotiate, roll over or modify the terms of a credit facility unless approved by the Board.
- **What the engine does:** The restructure register (EIR Data, Restructures & Resets) holds the candidates the detector finds in E-Banker's reschedule history (RS_02a to RS_02f), the migration worksheet and the rate moves of fixed-rate loans; the bootstrap and the month-end run the detector only, and a candidate becomes a restructure only when one person classifies it with a reason and Board approval reference and another approves. Section D of the Return lists every facility with a confirmed restructure by the month-end with its Board approval, counts those restructured in the month and those without a Board approval recorded (flagged in red), and counts the candidates awaiting confirmation without reporting them; on the screen and in the CSV, Excel and PDF. On the scratch clone maiic_ifrs9_wire of 14 October 2026 (a copy of the restructure build's database, migrated): 70 candidates and 323 rate moves recorded, none confirmed, so section D lists no facility and says so.
- **General comment:** Until 14 October 2026 this row was Outstanding: no register existed and section D was null.
- **Compliance comment:** Partially done: built; Credit classifies the candidates and MAIIC records the Board approval of each restructure it confirms.
- **Where to see it:** Reports: RBM Return (provisional) /rbm-return (section D); EIR Data, Restructures & Resets: /eir-restructures
- **Governance setting:** `rate_change_classification; modification_threshold`
- **Test that proves it:** `Tests\Feature\Rbm\RbmReturnServiceTest::test_section_d_lists_the_confirmed_restructures_with_their_board_approval; Tests\Feature\Eir\RestructureRegisterTest::test_the_rbm_section_d_reports_only_confirmed_restructures_and_names_a_missing_board_approval; Tests\Feature\Eir\RestructureDetectionWiringTest::test_the_detection_step_finds_and_never_confirms_and_the_bootstrap_line_counts_the_candidates`
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
