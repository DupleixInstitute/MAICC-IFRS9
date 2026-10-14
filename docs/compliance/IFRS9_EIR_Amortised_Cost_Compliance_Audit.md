# IFRS 9 - the EIR and amortised cost - compliance audit

IFRS 9 Financial Instruments - the effective interest rate, amortised cost and the gross carrying amount (Appendix A, 5.4, B5.4, 3.3.2 and B3.3.6).

Malawi Agricultural and Industrial Investment Company (MAIIC) - IFRS 9 EIR and ECL system - build of 14 October 2026 (branch eir_revenue_recognition, commit abe140d) against specification v4. As of 14 October 2026: the code at commit abe140d and the fresh bootstrap maiic_ifrs9_results2 of 13 October 2026 (made before commit 216aee2, so its EIR counts predate the take-on and tranche changes), read with SELECTs only; the Governance Centre catalogue of 61 settings (GovernanceService::catalogue); the PHPUnit suite as written (not run for this audit). Every approval on a bootstrap is the label 'System Bootstrap (automated data-readiness, not a MAIIC approval)'; MAIIC has approved nothing in the system. Reviewer: Dr Thomson Kumwenda (CFO), then Deloitte. The Excel workbook of the same name is the working copy (drop-down statuses, hyperlinked contents, reviewer sign-off column, a live Baselines sheet); this document is its printable twin.

Source: IFRS 9 Financial Instruments as issued by the IASB, Appendix A (defined terms), 5.4.1 to 5.4.4 (amortised cost, modification, write-off), B5.4.1 to B5.4.9 (effective interest method), 3.3.2 and B3.3.6. Specification: docs/MAIIC_EIR_Engine_Specification_v4_2026-10-07.md sections 3, 4.2, 6, 7.

Generated 2026-10-14 08:30:32; the Baselines were read from the database `maiic_ifrs9_results2`.

## Status summary

| Status | Meaning | Sections |
|---|---|---|
| **Done** | Implemented in the system and visible in an approved output; the test in column 11 fails if the behaviour changes. | 9 |
| **Partially done** | The mechanics are in place, but a parameter, an input or a piece of evidence still differs from the requirement. | 3 |
| **Outstanding** | Required for MAIIC and not built. | 4 |
| **Not applicable, documented** | Does not apply to MAIIC by a recorded decision or fact (reason and reference given); revisit if the facts change. | 1 |
| **Evidence needed from MAIIC** | The system is ready; MAIIC must supply a document, minute or dataset before the row can be closed. | 1 |
| **Total** | | 18 |

## Contents

| Ref | Section | Status | Governance setting | Test |
|---|---|---|---|---|
| **APPENDIX A - DEFINED TERMS** | | | | |
| A.EIR | Effective interest rate | Done | day_count | Tests\Feature\Eir\EirCalculationWorkflowTest::test_later_drawdowns_enter_the_eir_vector_at_their_own_dates; Tests\Feature\Eir\TrancheScheduleTest::test_the_schedule_retires_what_was_lent_and_solves_to_the_contractual_rate; Tests\Feature\Eir\ScheduleShapeGovernanceTest::test_the_day_count_is_read_from_the_governance_centre |
| A.AC | Amortised cost | Done | stage3_interest_basis; cash_source (not read: ledger first is fixed by D35) | Tests\Feature\Eir\EirAsAtServiceTest::test_a_month_end_reads_the_locked_roll_forward_row; Tests\Feature\Eir\EirRevenueServiceTest::test_the_first_month_opens_on_the_value_at_the_month_before |
| A.GCA | Gross carrying amount | Done | loan_book_build_method | Tests\Feature\Ebanker\LoanBookBuildServiceTest::test_method_b_derives_the_carrying_amount_from_the_ledger_and_keeps_the_stored_figure_beside_it |
| **5.4 AMORTISED COST MEASUREMENT** | | | | |
| 5.4.1 | Interest revenue at the effective interest rate | Done | stage3_interest_basis | Tests\Feature\Eir\EirRevenueServiceTest::test_stage_three_nets_the_opening_allowance_the_ecl_engine_wrote; Tests\Feature\Eir\EirRevenueServiceTest::test_stage_three_basis_is_the_governed_setting_in_force_for_the_period |
| 5.4.1(a), B5.4.7 | Purchased or originated credit-impaired assets | Not applicable, documented |  |  |
| 5.4.1(b) | Interest on an asset that became credit-impaired | Done | stage3_interest_basis; dpd_basis; stage3_missed_instalments | Tests\Feature\Eir\StagingServiceTest::test_four_unpaid_instalments_lift_a_current_loan_to_stage_3; Tests\Feature\Eir\EirRevenueServiceTest::test_stage_three_recognises_net_interest_and_discloses_unwind |
| 5.4.2 | Cure of a credit-impaired asset | Done | stage_cure_months; dpd_basis | Tests\Feature\Eir\StagingServiceTest::test_a_cured_loan_is_held_at_its_prior_stage_for_the_governed_months_and_a_move_up_is_immediate |
| 5.4.3 | Modification of contractual cash flows | Outstanding | modification_threshold (not read yet); rate_change_classification (not read yet) |  |
| 5.4.4, B5.4.9 | Write-off | Evidence needed from MAIIC |  | Tests\Feature\Eir\WriteOffTest::test_type_300_is_a_write_off_and_an_ordinary_repayment_is_not; Tests\Feature\Eir\EirRevenueServiceTest::test_a_write_off_reduces_the_gross_but_is_not_cash |
| **B5.4 EFFECTIVE INTEREST METHOD** | | | | |
| B5.4.1, B5.4.2 | Fees that are an integral part of the EIR | Partially done | fee_source_precedence; fee_gl_reconciliation; fee_reclass_journal (not read yet) | Tests\Feature\Eir\FeeRuleSweepTest::test_unapproved_rules_never_tag_anything; Tests\Feature\Eir\EirReadinessServiceTest::test_pending_fee_blocks_contract_with_named_reason; Tests\Feature\Ebanker\GlFeeRouteTest::test_one_fee_one_source_the_lower_source_is_superseded_and_the_difference_named |
| B5.4.3 | Fees that are not an integral part of the EIR | Done |  | Tests\Feature\Eir\FeeRuleSweepTest::test_priority_decides_when_two_rules_could_both_apply; Tests\Feature\Eir\TakeonAndTrancheEirInputTest::test_a_pending_fee_blocks_and_a_superseded_one_does_not |
| B5.4.4 | The period of amortisation: the expected life | Done | partly_drawn_interest_basis; takeon_eir_plausibility; expected_cashflow_basis (not read: contractual in code) | Tests\Feature\Eir\ScheduleComparisonTest; Tests\Feature\Eir\ScheduleShapeGovernanceTest::test_the_governed_balance_wise_option_takes_the_drawn_balance_without_reading_the_postings; Tests\Feature\Eir\TakeonAndTrancheEirInputTest::test_a_takeon_solve_the_check_refused_is_named_and_not_used |
| B5.4.5 | Floating-rate instruments | Partially done | plr_mid_period (not read yet); reset_trigger (not read yet); rate_change_classification (not read yet) | Tests\Feature\Eir\EirRevenueServiceTest::test_a_floating_rate_loan_accrues_at_the_eir_moved_with_the_rate_charged; Tests\Feature\Eir\EirRevenueServiceTest::test_a_fixed_rate_loan_keeps_its_locked_eir |
| B5.4.6 | Revised estimates of payments or receipts: the catch-up adjustment | Outstanding |  |  |
| **3.3 DERECOGNITION** | | | | |
| 3.3.2 | Extinguishment and substantial modification | Outstanding | modification_threshold (not read yet) |  |
| B3.3.6 | The 10 percent test | Outstanding | modification_threshold (not read yet) |  |
| **THE RECORD AND ITS PROOF** | | | | |
| S9 | The ledger as the primary record | Done | recon_tolerance; trueup_gl_account (not read yet) | Tests\Feature\Ebanker\PackLandingServiceTest; Tests\Feature\Eir\EirGlReconciliationServiceTest::test_the_tolerance_is_the_governed_setting_in_force_for_the_period; eir:bootstrap --verify |
| S4 | Governed conventions under maker-checker | Partially done | (all 61 catalogue settings) | Tests\Feature\Eir\GovernanceServiceTest::test_a_missing_setting_throws_a_named_exception_instead_of_defaulting; Tests\Feature\Eir\EirGovernanceControllerTest |

## Baselines - the acceptance ties read from the system

Generated 2026-10-14 08:30:32 from maiic_ifrs9_results2.

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

### F1. The fee rulebook is unapproved, so EIRs are blocked at the fee gate (B5.4.1, B5.4.2) - Open

- **What was found:** The 30 seeded rules carry no approver; every fee line stays PENDING and the fee gate blocks the contract. On the fresh bootstrap 33 contracts with an approved schedule are blocked for this reason and 14 are locked. (Until 14 October 2026 this finding said 19 of 31.)
- **Impact:** The blocked contracts have no locked EIR, so no EIR revenue or amortised cost.
- **Recommended action:** A MAIIC reviewer approves the rulebook; a second person reviews the classifications; the EIRs are then solved and locked.
- **Owner:** Dr Thom / the accounting-rules reviewer

### F2. The take-on loans' EIRs (B5.4.4) - Open

- **What was found:** The take-on fees arrived on 13 October 2026 (the returned mapping workbook, O14) and land as pending lines; commit 216aee2 solves each take-on loan's EIR on its workbook schedule after the plausibility check (takeon_eir_plausibility). On the fresh bootstrap, which predates 216aee2, the 73 take-on workbook schedules are not yet approved or solved.
- **Impact:** The take-on loans carry no locked EIR until the next bootstrap and the fee reviews.
- **Recommended action:** Re-run the bootstrap on 216aee2; review the take-on fee lines and the take-on schedules in Schedule Review.
- **Owner:** Dupleix / Tamanda Sitimawina

### F3. Modification, catch-up and the 10 percent test are not built (5.4.3, B5.4.6, 3.3.2) - Open

- **What was found:** E-Banker's reschedule history (RS_02a to RS_02g) landed on 12 October 2026 and is used only for the instalment chart in force; there is no modification record, no schedule version above 1, no catch-up and no 10 percent test. (Until 14 October 2026 this finding said Barry was to obtain the Reschedule Report.)
- **Impact:** Modification gains and losses, catch-up adjustments and any derecognition are not recognised; IFRS 7.20A and RBM 16(1) wait on the same work.
- **Recommended action:** Dupleix builds the restructure register from RS_02 (Credit confirms the candidates), version N+1 schedules at the original EIR, the B5.4.6 catch-up and the B3.3.6 test.
- **Owner:** Dupleix / Credit

### F4. Two trial-balance ties pass only as accepted exceptions (S9) - Open

- **What was found:** GL 1050201 is 400,000.00 above and 1050202 1,000,000.00 below the loan book at 31 December 2025, the keyed GL openings of section 3.5.
- **Impact:** The year-end balances by GL are explained, not equal.
- **Recommended action:** Finance posts the opening adjustment; the exception is then removed from the pack's manifest and the Baselines sheet.
- **Owner:** Finance

### F5. Inside a month the as-at report accrues at the contractual rate (A.AC) - Open

- **What was found:** EirAsAtService accrues the part-month at the contractual rate on actual days and reports it as EIR interest (review item R12); the test asserts that behaviour.
- **Impact:** An as-at date between month-ends is not at the EIR.
- **Recommended action:** Accrue the part-month at the month's EIR and label the contractual figure separately.
- **Owner:** Dupleix

### F6. Settings that change nothing; ties stated but not asserted (S4, S9) - Open

- **What was found:** 20 of the 61 catalogue settings are read by no engine and three readers fall back to a constant; spec section 9 lists 16 ties of which eir:baselines asserts the interest total, the method B tie, the two year-end contras and the December 2025 loan GLs.
- **Impact:** A reviewer may approve a setting that has no effect; the unasserted ties are not proven by --verify.
- **Recommended action:** Wire or retire each unread setting and mark it in the catalogue; make the fallbacks fail closed; add the remaining section 9 ties to BaselineService.
- **Owner:** Dupleix

## Section-by-section audit

### APPENDIX A - DEFINED TERMS

**A.EIR. Effective interest rate** - *Done*

- **What it requires:** The rate that exactly discounts estimated future cash payments or receipts through the expected life of the financial asset to the gross carrying amount, including all fees and points paid or received that are an integral part of the EIR, transaction costs and all other premiums or discounts.
- **What the engine does:** EirCalculationService solves the rate that discounts the approved schedule's dated cash flows, net of the reviewed integral fees, to the initial net investment, under the governed day count; later drawdowns enter the vector at their own dates and a tranche facility's schedule follows each drawdown (216aee2). The solver residual and iterations are stored on contract_eir. On the fresh bootstrap: 144 contracts; 14 EIRs solved and locked; 33 generated schedules approved but blocked at the fee gate (their fee lines are not independently reviewed); 73 take-on workbook schedules and 24 contracts with no schedule not yet solved.
- **General comment:** Fresh bootstrap maiic_ifrs9_results2 (13 October 2026, before commit 216aee2). Until 14 October 2026 the row said '31 EIRs solved, 12 locked, 19 blocked' from the 8 October install.
- **Compliance comment:** Compliant: the rate is solved from the flows, never typed; coverage depends on MAIIC's fee reviews.
- **Where to see it:** EIR Calculations, Calculations: /eir-calculations; Blockers: /eir-coverage; EIR Calculations, EIR as at a Date: /eir-as-at
- **Governance setting:** `day_count`
- **Test that proves it:** `Tests\Feature\Eir\EirCalculationWorkflowTest::test_later_drawdowns_enter_the_eir_vector_at_their_own_dates; Tests\Feature\Eir\TrancheScheduleTest::test_the_schedule_retires_what_was_lent_and_solves_to_the_contractual_rate; Tests\Feature\Eir\ScheduleShapeGovernanceTest::test_the_day_count_is_read_from_the_governance_centre`
- **Reviewer sign-off:** ____________________   Date: ____________

**A.AC. Amortised cost** - *Done*

- **What it requires:** The amount at initial recognition minus principal repayments, plus or minus the cumulative amortisation using the effective interest method of any difference between that initial amount and the maturity amount, adjusted for any loss allowance.
- **What the engine does:** eir:run-revenue rolls each locked contract forward monthly (eir_amortisation: opening, advances, EIR interest, cash received, closing), opening at the prior month-end (opening_basis), with the arrears at take-on (D39) and cash from the ledger wherever the feed covers the month. On the fresh bootstrap: 13 contracts, 259 monthly rows from July 2024 to September 2026, 255 of them on ledger cash and 4 on the schedule. EirAsAtService gives the amortised cost at any date; inside a month it accrues the part-month at the contractual rate, not the EIR (review item R12, finding F5).
- **General comment:** Fresh bootstrap maiic_ifrs9_results2 (13 October 2026, before commit 216aee2). Ledger cash first is decision D35 in code; the cash_source setting is not read.
- **Compliance comment:** Compliant at month-ends for the loans with a locked EIR; the loss allowance is applied in the ECL module.
- **Where to see it:** EIR Calculations, EIR as at a Date: /eir-as-at; EIR Calculations, GL Reconciliation: /eir-reconciliation
- **Governance setting:** `stage3_interest_basis; cash_source (not read: ledger first is fixed by D35)`
- **Test that proves it:** `Tests\Feature\Eir\EirAsAtServiceTest::test_a_month_end_reads_the_locked_roll_forward_row; Tests\Feature\Eir\EirRevenueServiceTest::test_the_first_month_opens_on_the_value_at_the_month_before`
- **Reviewer sign-off:** ____________________   Date: ____________

**A.GCA. Gross carrying amount** - *Done*

- **What it requires:** The amortised cost of a financial asset before adjusting for any loss allowance.
- **What the engine does:** The gross carrying amount at any month-end is the ledger's running balance (method B), equal to E-Banker's own loan-book report on every in-scope account-month: 0 differences over 2 cents in 2,495 account-months on the fresh bootstrap (Baselines sheet). Ledger write-offs reduce it and are not cash (WriteOffClassifier, 220a5a6).
- **General comment:** Fresh bootstrap maiic_ifrs9_results2 (13 October 2026, before commit 216aee2). Until 14 October 2026 the row said 2,361 account-months.
- **Compliance comment:** Compliant.
- **Where to see it:** EIR Calculations, EIR as at a Date: /eir-as-at; Customer & Loan Data, Loan Book
- **Governance setting:** `loan_book_build_method`
- **Test that proves it:** `Tests\Feature\Ebanker\LoanBookBuildServiceTest::test_method_b_derives_the_carrying_amount_from_the_ledger_and_keeps_the_stored_figure_beside_it`
- **Reviewer sign-off:** ____________________   Date: ____________

### 5.4 AMORTISED COST MEASUREMENT

**5.4.1. Interest revenue at the effective interest rate** - *Done*

- **What it requires:** Interest revenue is calculated by applying the EIR to the gross carrying amount, except for (a) purchased or originated credit-impaired assets and (b) assets that became credit-impaired after initial recognition, where it is applied to the amortised cost.
- **What the engine does:** The roll-forward applies the EIR to the opening gross carrying amount for Stage 1 and 2 and to the amount net of the opening allowance for Stage 3 (interest_basis on each row; the allowance is the ECL the engine wrote on the prior month's row); the bootstrap stages and runs the ECL before revenue for that reason. A floating-rate loan accrues at the EIR moved with the rate charged (D38, B5.4.5 row).
- **Compliance comment:** Compliant for the loans with a locked EIR.
- **Where to see it:** EIR Calculations, GL Reconciliation: /eir-reconciliation; EIR Calculations, EIR as at a Date: /eir-as-at
- **Governance setting:** `stage3_interest_basis`
- **Test that proves it:** `Tests\Feature\Eir\EirRevenueServiceTest::test_stage_three_nets_the_opening_allowance_the_ecl_engine_wrote; Tests\Feature\Eir\EirRevenueServiceTest::test_stage_three_basis_is_the_governed_setting_in_force_for_the_period`
- **Reviewer sign-off:** ____________________   Date: ____________

**5.4.1(a), B5.4.7. Purchased or originated credit-impaired assets** - *Not applicable, documented*

- **What it requires:** A credit-adjusted EIR is applied to the amortised cost from initial recognition.
- **What the engine does:** MAIIC originates its loans and has purchased none; no facility is credit-impaired at origination in the data held (the take-on loans entered E-Banker on 31 July 2024 at their existing balances, a migration, not a purchase).
- **General comment:** If a POCI asset ever arises it is an instrument_type judgement on the contract (the Nascomex memo), never a product code.
- **Compliance comment:** Not applicable, documented: no POCI asset; revisit on any purchase of a portfolio.
- **Where to see it:** EIR Data, Contract Master: /eir-data
- **Reviewer sign-off:** ____________________   Date: ____________

**5.4.1(b). Interest on an asset that became credit-impaired** - *Done*

- **What it requires:** When a financial asset becomes credit-impaired after initial recognition, interest revenue is calculated on the amortised cost in subsequent periods.
- **What the engine does:** Stage 3 is set by the directive's thresholds (91 days short-term and Mega Farm, 181 otherwise) and the four-missed-instalment trigger, which since 216aee2 reads E-Banker's instalment chart in force at the month-end and net cash; the next roll-forward row is on the net basis (interest_basis NET).
- **General comment:** Spec v4 section 3.6, decision D31. Until 14 October 2026 this row was labelled B5.4.7, which is the POCI paragraph.
- **Compliance comment:** Compliant.
- **Where to see it:** EIR Calculations, GL Reconciliation: /eir-reconciliation
- **Governance setting:** `stage3_interest_basis; dpd_basis; stage3_missed_instalments`
- **Test that proves it:** `Tests\Feature\Eir\StagingServiceTest::test_four_unpaid_instalments_lift_a_current_loan_to_stage_3; Tests\Feature\Eir\EirRevenueServiceTest::test_stage_three_recognises_net_interest_and_discloses_unwind`
- **Reviewer sign-off:** ____________________   Date: ____________

**5.4.2. Cure of a credit-impaired asset** - *Done*

- **What it requires:** When the asset is no longer credit-impaired, interest revenue reverts to the gross basis.
- **What the engine does:** The stage is re-assessed every period; a cured loan is held at its prior stage for the governed cure period (stage_cure_months) and then returns to Stage 1 or 2, and the roll-forward row of that month is on the gross basis again.
- **Compliance comment:** Compliant.
- **Where to see it:** EIR Calculations, GL Reconciliation: /eir-reconciliation; Settings, Staging basis: /setting?tab=staging
- **Governance setting:** `stage_cure_months; dpd_basis`
- **Test that proves it:** `Tests\Feature\Eir\StagingServiceTest::test_a_cured_loan_is_held_at_its_prior_stage_for_the_governed_months_and_a_move_up_is_immediate`
- **Reviewer sign-off:** ____________________   Date: ____________

**5.4.3. Modification of contractual cash flows** - *Outstanding*

- **What it requires:** When the contractual cash flows are renegotiated or modified and the asset is not derecognised, recalculate the gross carrying amount as the present value of the modified flows at the original EIR and recognise a modification gain or loss; costs and fees incurred adjust the carrying amount and are amortised over the remaining term.
- **What the engine does:** Not built: there is no contract_modifications record, no schedule version above 1 and no writer of rate_reset_events (it is only read, by EirAsAtService and the audit trace). The modification_gain_loss column carries only a derecognition without cash (EirRevenueService). E-Banker's reschedule history (RS_02a to RS_02g) landed on 12 October 2026 and is read only for the instalment chart in force used by staging.
- **General comment:** Until 14 October 2026 this row was Partially done and said the restructure history was awaited from the vendor; it has arrived and the build is Dupleix's (spec P7). The row labelled B5.4.6 repeated this requirement and is folded in here.
- **Compliance comment:** Outstanding (Dupleix).
- **Where to see it:** EIR Calculations, Calculations: /eir-calculations; Blockers: /eir-coverage; EIR Calculations, EIR as at a Date: /eir-as-at
- **Governance setting:** `modification_threshold (not read yet); rate_change_classification (not read yet)`
- **Reviewer sign-off:** ____________________   Date: ____________

**5.4.4, B5.4.9. Write-off** - *Evidence needed from MAIIC*

- **What it requires:** The gross carrying amount is reduced when there is no reasonable expectation of recovering a financial asset in its entirety or a portion of it.
- **What the engine does:** WriteOffClassifier (220a5a6) treats a credit to a loan account as a write-off when it is type 300, when its batch debits GL 1064, or when it is an ACCOUNT CLOSURE on a status-H account; the loan-book build, the contract inputs and the EIR's ledger cash all use it, so a write-off reduces the gross and is never cash. A write-off not on the loan account goes through the register (/eir-write-offs), proposed by one person and approved by another before it reaches the engines. On the fresh bootstrap: 22 ledger write-offs of 60,778,466.86; the register is empty.
- **General comment:** Fresh bootstrap maiic_ifrs9_results2 (13 October 2026, before commit 216aee2). Until 14 October 2026 the row said E-Banker's write-off table returned no rows.
- **Compliance comment:** Evidence needed: MAIIC's write-off policy, Finance's confirmation that status H means written off, and approval of any register entry.
- **Where to see it:** EIR Data, Write-offs: /eir-write-offs
- **Test that proves it:** `Tests\Feature\Eir\WriteOffTest::test_type_300_is_a_write_off_and_an_ordinary_repayment_is_not; Tests\Feature\Eir\EirRevenueServiceTest::test_a_write_off_reduces_the_gross_but_is_not_cash`
- **Reviewer sign-off:** ____________________   Date: ____________

### B5.4 EFFECTIVE INTEREST METHOD

**B5.4.1, B5.4.2. Fees that are an integral part of the EIR** - *Partially done*

- **What it requires:** Fees that are an integral part of the EIR are an adjustment to the EIR: origination fees for evaluating the borrower, guarantees and collateral, negotiating terms, preparing documents and closing; commitment fees where the loan is likely to be drawn.
- **What the engine does:** Fee lines land as PENDING from E-Banker's LOS charges, Finance's fee workbook, the take-on workbook and (since 216aee2) the GL fee postings tied to a loan through the drawdown batch (eir:build-gl-fees); fee_source_precedence takes a contract's fees from one source and marks the rest superseded so none counts twice, and the fee GL check sets the fees beside 4871 and 4873. The 30-rule rulebook proposes each line's treatment; a reviewer classifies and a second person reviews; the solver reads only reviewed integral fees. On the fresh bootstrap: 131 LOS fee lines of 1,036.6m, all PENDING; 0 of 30 rules approved; 33 contracts blocked at the fee gate.
- **General comment:** Fresh bootstrap maiic_ifrs9_results2 (13 October 2026, before commit 216aee2). The take-on fees arrived on 13 October 2026 (O14); the GL route did not run on the fresh bootstrap, which predates it.
- **Compliance comment:** Partially done: the mechanics, the source precedence and the gate are in place; the rulebook and the classifications await MAIIC's reviewers.
- **Where to see it:** EIR Rules, Accounting Rules: /eir-accounting-rules; Fee Classification: /eir-fee-classification; EIR Calculations, Calculations: /eir-calculations; Blockers: /eir-coverage
- **Governance setting:** `fee_source_precedence; fee_gl_reconciliation; fee_reclass_journal (not read yet)`
- **Test that proves it:** `Tests\Feature\Eir\FeeRuleSweepTest::test_unapproved_rules_never_tag_anything; Tests\Feature\Eir\EirReadinessServiceTest::test_pending_fee_blocks_contract_with_named_reason; Tests\Feature\Ebanker\GlFeeRouteTest::test_one_fee_one_source_the_lower_source_is_superseded_and_the_difference_named`
- **Reviewer sign-off:** ____________________   Date: ____________

**B5.4.3. Fees that are not an integral part of the EIR** - *Done*

- **What it requires:** Fees for servicing, commitment fees where draw-down is unlikely, and loan syndication fees are not part of the EIR.
- **What the engine does:** Only reviewed integral fees enter the EIR, so a fee that is not integral never reaches the rate; the rulebook's rules for internal administrative and staff cost, recovery and enforcement legal cost and penalty charges propose not-integral and rank above the origination rules; a reviewer may reverse any line with a reason.
- **General comment:** Until 14 October 2026 this row was labelled B5.4.2.
- **Compliance comment:** Compliant.
- **Where to see it:** EIR Rules, Accounting Rules: /eir-accounting-rules
- **Test that proves it:** `Tests\Feature\Eir\FeeRuleSweepTest::test_priority_decides_when_two_rules_could_both_apply; Tests\Feature\Eir\TakeonAndTrancheEirInputTest::test_a_pending_fee_blocks_and_a_superseded_one_does_not`
- **Reviewer sign-off:** ____________________   Date: ____________

**B5.4.4. The period of amortisation: the expected life** - *Done*

- **What it requires:** Fees, points, transaction costs and premiums or discounts are amortised over the expected life of the instrument, using contractual terms where the cash flows or life cannot be reliably estimated.
- **What the engine does:** The version 1 schedule is generated from the contractual terms (contract master, scheme settings, instalment chart, the tranche register for a facility still drawing down) and approved before the solve; a take-on loan's schedule is the take-on workbook's, checked against its own rates before use (takeon_eir_plausibility, 216aee2). No behavioural prepayment estimate is made: expected_cashflow_basis is in the catalogue but no engine reads it, so the basis is contractual in code.
- **General comment:** Until 14 October 2026 this row was labelled B5.4.3.
- **Compliance comment:** Compliant on the contractual basis, which the standard allows where estimates are unreliable.
- **Where to see it:** EIR Calculations, Calculations: /eir-calculations; Blockers: /eir-coverage; EIR Data, Schedule Review: /eir-data?tab=schedules
- **Governance setting:** `partly_drawn_interest_basis; takeon_eir_plausibility; expected_cashflow_basis (not read: contractual in code)`
- **Test that proves it:** `Tests\Feature\Eir\ScheduleComparisonTest; Tests\Feature\Eir\ScheduleShapeGovernanceTest::test_the_governed_balance_wise_option_takes_the_drawn_balance_without_reading_the_postings; Tests\Feature\Eir\TakeonAndTrancheEirInputTest::test_a_takeon_solve_the_check_refused_is_named_and_not_used`
- **Reviewer sign-off:** ____________________   Date: ____________

**B5.4.5. Floating-rate instruments** - *Partially done*

- **What it requires:** For floating-rate assets, periodic re-estimation of cash flows to reflect movements in market rates alters the effective interest rate.
- **What the engine does:** A floating-rate loan's EIR for each month is the locked nominal EIR moved by the change from the rate its schedule was built on to the rate E-Banker charged in the month (EirRevenueService::eirInForce, decision D38), stored as eir_annual on the roll-forward row; the PLR series is held. Not built: rate reset events (no writer of rate_reset_events) and a re-solved schedule at a reset; the mid-month PLR and reset settings are in the catalogue but not read.
- **General comment:** Until 14 October 2026 this row was labelled B5.4.4 and marked Done.
- **Compliance comment:** Partially done: the monthly re-estimation is built; reset events are not.
- **Where to see it:** EIR Data, Reference Rates: /eir-reference-rates; EIR Calculations, EIR as at a Date: /eir-as-at
- **Governance setting:** `plr_mid_period (not read yet); reset_trigger (not read yet); rate_change_classification (not read yet)`
- **Test that proves it:** `Tests\Feature\Eir\EirRevenueServiceTest::test_a_floating_rate_loan_accrues_at_the_eir_moved_with_the_rate_charged; Tests\Feature\Eir\EirRevenueServiceTest::test_a_fixed_rate_loan_keeps_its_locked_eir`
- **Reviewer sign-off:** ____________________   Date: ____________

**B5.4.6. Revised estimates of payments or receipts: the catch-up adjustment** - *Outstanding*

- **What it requires:** When an entity revises its estimates of payments or receipts (excluding modifications and changes in expected credit losses), it adjusts the gross carrying amount to the present value of the revised flows at the original EIR and recognises the adjustment in profit or loss.
- **What the engine does:** Not built: no schedule version above 1 is ever created for a fixed-rate loan and no catch-up adjustment is computed; modification_gain_loss carries only a derecognition without cash. (The floating-rate re-estimation of B5.4.5 is built.)
- **General comment:** Until 14 October 2026 this row was labelled B5.4.5 and marked Done on the basis of mechanics that do not exist.
- **Compliance comment:** Outstanding (Dupleix, spec P6).
- **Where to see it:** EIR Calculations, EIR as at a Date: /eir-as-at
- **Reviewer sign-off:** ____________________   Date: ____________

### 3.3 DERECOGNITION

**3.3.2. Extinguishment and substantial modification** - *Outstanding*

- **What it requires:** A financial liability (and by analogy an asset whose terms are substantially modified) is derecognised when the terms are substantially different; a modification that is not substantial is accounted for under 5.4.3.
- **What the engine does:** The 10 percent test of B3.3.6 is not built, and neither is 5.4.3.
- **General comment:** The reschedule history it needs (RS_02) landed on 12 October 2026; until 14 October 2026 this row waited on it.
- **Compliance comment:** Outstanding (Dupleix, spec P7).
- **Where to see it:** EIR Calculations, Calculations: /eir-calculations; Blockers: /eir-coverage
- **Governance setting:** `modification_threshold (not read yet)`
- **Reviewer sign-off:** ____________________   Date: ____________

**B3.3.6. The 10 percent test** - *Outstanding*

- **What it requires:** Terms are substantially different if the discounted present value of the cash flows under the new terms, including fees, at the original EIR, differs by at least 10 percent from the discounted present value of the remaining cash flows of the original.
- **What the engine does:** As 3.3.2.
- **Compliance comment:** Outstanding.
- **Where to see it:** EIR Calculations, Calculations: /eir-calculations; Blockers: /eir-coverage
- **Governance setting:** `modification_threshold (not read yet)`
- **Reviewer sign-off:** ____________________   Date: ____________

### THE RECORD AND ITS PROOF

**S9. The ledger as the primary record** - *Done*

- **What it requires:** The system's figures must reconcile to the entity's general ledger (IAS 1.15 fair presentation; the auditor's completeness and accuracy assertions).
- **What the engine does:** Read from the system at generation (Baselines sheet): interest posted January 2025 to July 2026 of 5,293,988,207.06 from the ledger; the derived carrying amount equal to the stored report on 2,495 of 2,495 in-scope account-months; the year-end interest batch contras on 4215 and 4216; the loan GLs at 31 December 2025 equal to the trial balance, with 1050201 and 1050202 off by the accepted exception of section 3.5. The GL reconciliation per period uses the governed tolerance in force for the period.
- **General comment:** Spec section 9 lists 16 ties; eir:baselines asserts the ones above (finding F6).
- **Compliance comment:** Compliant for the ties asserted; two pass as accepted exceptions until Finance corrects the keyed openings.
- **Where to see it:** EIR Calculations, GL Reconciliation: /eir-reconciliation; Imports & Feeds, E-Banker Loads: /eir-feed
- **Governance setting:** `recon_tolerance; trueup_gl_account (not read yet)`
- **Test that proves it:** `Tests\Feature\Ebanker\PackLandingServiceTest; Tests\Feature\Eir\EirGlReconciliationServiceTest::test_the_tolerance_is_the_governed_setting_in_force_for_the_period; eir:bootstrap --verify`
- **Reviewer sign-off:** ____________________   Date: ____________

**S4. Governed conventions under maker-checker** - *Partially done*

- **What it requires:** Every accounting judgement the engine makes must be a documented, approved policy choice (IAS 8.10 to 8.12), not a constant in code.
- **What the engine does:** 61 settings in the Governance Centre catalogue, each with options, a seeded default effective from inception, a proposer and an approver; a calculation that needs a setting with no approved value stops and says so; a locked period keeps the values it was run under. But 20 of the 61 are read by no engine (plr_mid_period, reset_trigger, rate_source_precedence, margin_basis, cash_source, manual_policy_handling, modification_threshold, counter_reset_handling, contractual_record, expected_cashflow_basis, history_before_dec_2025, trueup_gl_account, auditor_export_format, keyman_insurance_treatment, nascomex_preference_shares, staging_rebuttal, rate_change_classification, schedule_approval_control, historic_materiality_assessment, fee_reclass_journal), and some readers fall back to a constant when no value is approved (FliRouteService::setting; LoanBookBuildService::methodInForce falls back to method B; MegaFarmEclService takes a share of 1.0 when the scope text has none).
- **General comment:** Until 14 October 2026 the row said 48 settings and was marked Done.
- **Compliance comment:** Partially done: the mechanism is sound; 20 settings look governed but change nothing, and three readers fail open.
- **Where to see it:** Governance: Governance Centre /eir-governance
- **Governance setting:** `(all 61 catalogue settings)`
- **Test that proves it:** `Tests\Feature\Eir\GovernanceServiceTest::test_a_missing_setting_throws_a_named_exception_instead_of_defaulting; Tests\Feature\Eir\EirGovernanceControllerTest`
- **Reviewer sign-off:** ____________________   Date: ____________
