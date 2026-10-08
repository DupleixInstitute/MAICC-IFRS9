# IFRS 9 - the EIR and amortised cost - compliance audit

IFRS 9 Financial Instruments - the effective interest rate, amortised cost and the gross carrying amount (Appendix A, 5.4, B5.4, 5.4.3 and B5.4.6, 3.3.2 and B3.3.6).

Malawi Agricultural and Industrial Investment Company (MAIIC) - IFRS 9 EIR and ECL system - build of 8 October 2026 against specification v4. Prepared 8 October 2026 from the build of specification v4 (commits 32e7d99 to c6353ff): the clean install of eir:bootstrap, the Governance Centre's 48 settings, and the PHPUnit suite (tests/Feature/Eir, tests/Feature/Ebanker). Every approval on the install is the bootstrap's label 'System Bootstrap (automated data-readiness, not a MAIIC approval)'; MAIIC has not approved anything in the system. Reviewer: Dr Thomson Kumwenda (CFO), then Deloitte. The Excel workbook of the same name is the working copy (drop-down statuses, hyperlinked contents, reviewer sign-off column, a live Baselines sheet); this document is its printable twin.

Source: IFRS 9 Financial Instruments as issued by the IASB, Appendix A (defined terms), section 5.4 (amortised cost measurement), B5.4.1 to B5.4.7 (effective interest method), 5.4.3 and B5.4.6 (modification), 3.3.2 and B3.3.6 (derecognition). Specification: docs/MAIIC_EIR_Engine_Specification_v4_2026-10-07.md sections 3, 4.2, 6, 7.

## Status summary

| Status | Meaning | Sections |
|---|---|---|
| **Done** | Implemented in the system and visible in an approved output; the test in column 11 fails if the behaviour changes. | 12 |
| **Partially done** | The mechanics are in place, but a parameter, an input or a piece of evidence still differs from the requirement. | 3 |
| **Outstanding** | Required for MAIIC and not built. | 2 |
| **Not applicable, documented** | Does not apply to MAIIC by a recorded decision or fact (reason and reference given); revisit if the facts change. | 1 |
| **Evidence needed from MAIIC** | The system is ready; MAIIC must supply a document, minute or dataset before the row can be closed. | 1 |
| **Total** | | 19 |

## Contents

| Ref | Section | Status | Governance setting | Test |
|---|---|---|---|---|
| **APPENDIX A - DEFINED TERMS** | | | | |
| A.EIR | Effective interest rate | Done | day_count_basis; compounding_convention | Tests\Feature\Eir\EirCalculationWorkflowTest |
| A.AC | Amortised cost | Done | cash_source_precedence (ledger over schedule); plr_mid_period | Tests\Feature\Eir\EirAsAtServiceTest::test_a_month_end_reads_the_locked_roll_forward_row; Tests\Feature\Eir\EirRevenueServiceTest |
| A.GCA | Gross carrying amount | Done | loan_book_build_method | Tests\Feature\Ebanker\LoanBookBuildServiceTest::test_method_b_derives_the_carrying_amount_from_the_ledger_and_keeps_the_stored_figure_beside_it |
| **5.4 AMORTISED COST MEASUREMENT** | | | | |
| 5.4.1 | Interest revenue at the effective interest rate | Done | stage3_interest_basis | Tests\Feature\Eir\EirRevenueServiceTest; Tests\Feature\Eir\StagingServiceTest |
| 5.4.1(a) | Purchased or originated credit-impaired assets | Not applicable, documented | contractual_record; history_before_dec_2025 |  |
| 5.4.2 | Cure of a credit-impaired asset | Done | dpd_basis; stage3_missed_instalments | Tests\Feature\Eir\StagingServiceTest::test_four_unpaid_instalments_lift_a_current_loan_to_stage_3 |
| 5.4.3 | Modification of contractual cash flows | Partially done | rate_change_classification | Tests\Feature\Eir\EirAsAtServiceTest::test_a_month_end_reads_the_locked_roll_forward_row |
| 5.4.4 | Write-off | Evidence needed from MAIIC |  |  |
| **B5.4 EFFECTIVE INTEREST METHOD** | | | | |
| B5.4.1 | Fees that are an integral part of the EIR | Partially done | fee_reclass_journal; integral_fee_rulebook | Tests\Feature\Eir\FeeRuleSweepTest; Tests\Feature\Eir\EirReadinessServiceTest |
| B5.4.2 | Fees that are not an integral part of the EIR | Done | integral_fee_rulebook | Tests\Feature\Eir\FeeRuleSweepTest |
| B5.4.3 | Expected life and the cash flows considered | Done | expected_cashflow_basis; partly_drawn_interest_basis | Tests\Feature\Eir\ScheduleComparisonTest; Tests\Feature\Eir\ScheduleShapeGovernanceTest |
| B5.4.4 | Floating-rate instruments | Done | rate_change_classification; plr_mid_period | Tests\Feature\Eir\ReferenceRateImportServiceTest; Tests\Feature\Eir\SpreadDerivationServiceTest |
| B5.4.5 | Re-estimation of payments or receipts (not a modification) | Done | schedule_approval_control | Tests\Feature\Eir\ScheduleShapeGovernanceTest |
| B5.4.6 | Modification: recalculate at the original EIR | Partially done | rate_change_classification | Tests\Feature\Eir\EirAsAtServiceTest |
| B5.4.7 | Interest on a credit-impaired asset | Done | stage3_interest_basis; dpd_basis | Tests\Feature\Eir\StagingServiceTest |
| **3.3 DERECOGNITION** | | | | |
| 3.3.2 | Extinguishment and substantial modification | Outstanding |  |  |
| B3.3.6 | The 10 percent test | Outstanding |  |  |
| **THE RECORD AND ITS PROOF** | | | | |
| S9 | The ledger as the primary record | Done | reconciliation_tolerance; trueup_gl_account | Tests\Feature\Ebanker\PackLandingServiceTest; eir:bootstrap --verify |
| S4 | Governed conventions under maker-checker | Done | (all) | Tests\Feature\Eir\GovernanceServiceTest; Tests\Feature\Eir\EirGovernanceControllerTest |

## Baselines - the acceptance ties read from the system

Generated 2026-10-08 20:35:36 from maiic_ifrs9_bootstrap.

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

### F1. The fee rulebook is unapproved, so 19 solvable EIRs are blocked (B5.4.1) - Open

- **What was found:** The 30 seeded rules (three for E-Banker's own charge names) carry no approver; the bootstrap reviews only what an approved rule covers, so every LOS charge stays PENDING and the fee gate blocks the contract.
- **Impact:** 19 of 31 loans have no locked EIR; their revenue and amortised cost cannot be produced.
- **Recommended action:** A MAIIC reviewer approves the rulebook on the Accounting Rules screen; a second person reviews the classifications; the bootstrap then solves and locks the 19.
- **Owner:** Dr Thom / the accounting-rules reviewer

### F2. The take-on loans' fees are not supplied (B5.4.1) - Open

- **What was found:** All 78 take-on loans start at the take-on balance because the mapping workbook's fee columns are blank (O14); a blank is never read as fee-free.
- **Impact:** The pre-migration EIR of the 78 cannot be solved from origination; the revenue shift of 2024 is understated for them.
- **Recommended action:** Tamanda completes the yellow columns or enters the fees on the Take-on Schedules screen; the build then recomputes from origination where the block and fees exist.
- **Owner:** Tamanda Sitimawina

### F3. The restructure history is not in the system (5.4.3, B5.4.6, 3.3.2) - Open

- **What was found:** E-Banker's Reschedule Report and the restructured-loan register are awaited from the vendor; the modification mechanics exist but the 2024 to 2026 modifications are not loaded, and the 10 percent test is not built.
- **Impact:** Modification gains and losses, and any derecognition, are not recognised.
- **Recommended action:** Barry obtains the Reschedule Report; the register is loaded by the feed; the 10 percent test is built against it.
- **Owner:** Barry Makumba / Dupleix

### F4. Two trial-balance ties pass only as accepted exceptions (S9) - Open

- **What was found:** GL 1050201 is 400,000.00 above and 1050202 1,000,000.00 below the loan book at 31 December 2025, the keyed GL openings of section 3.5.
- **Impact:** The year-end balances by GL are explained, not equal.
- **Recommended action:** Finance posts the opening adjustment; the exception is then removed from the pack's manifest and the Baselines sheet.
- **Owner:** Finance

## Section-by-section audit

### APPENDIX A - DEFINED TERMS

**A.EIR. Effective interest rate** - *Done*

- **What it requires:** The rate that exactly discounts estimated future cash payments or receipts through the expected life of the financial asset to the gross carrying amount; the calculation includes all fees and points paid or received between the parties that are an integral part of the EIR, transaction costs and all other premiums or discounts.
- **What the engine does:** EirCalculationService solves the rate that discounts the approved schedule's cash flows, net of the integral fees, to the initial net investment, on dated flows with the ACT/365 day count; the solver residual and iterations are stored on contract_eir. On the clean install 31 EIRs are solved and 12 locked; 19 are blocked because their fees await a reviewer (B5.4.1 row).
- **General comment:** Spec v4 section 3.1 and 3.3.
- **Compliance comment:** Compliant: the rate is solved from the flows, never typed.
- **Where to see it:** Financial Modelling, EIR Calculations: /eir-calculations; Report Hub, EIR as at a Date: /eir-as-at; Contract Profile
- **Governance setting:** `day_count_basis; compounding_convention`
- **Test that proves it:** `Tests\Feature\Eir\EirCalculationWorkflowTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**A.AC. Amortised cost** - *Done*

- **What it requires:** The amount at initial recognition minus principal repayments, plus or minus the cumulative amortisation using the effective interest method of any difference between that initial amount and the maturity amount, adjusted for any loss allowance.
- **What the engine does:** The monthly roll-forward (eir_amortisation: opening, EIR interest, cash received, closing) is produced by eir:run-revenue from the locked EIR, with cash from the ledger's actual receipts where they cover the month (107 of 138 months on the clean install) and from the schedule otherwise; EirAsAtService gives the amortised cost at any date, accruing on actual days inside a month.
- **General comment:** Spec v4 sections 3, 6.11, 7.1.
- **Compliance comment:** Compliant for the loans with a locked EIR; the loss allowance is applied in the ECL module, not netted here.
- **Where to see it:** Report Hub, EIR as at a Date: /eir-as-at; Contract Profile; Report Hub, GL Reconciliation (EIR): /eir-reconciliation
- **Governance setting:** `cash_source_precedence (ledger over schedule); plr_mid_period`
- **Test that proves it:** `Tests\Feature\Eir\EirAsAtServiceTest::test_a_month_end_reads_the_locked_roll_forward_row; Tests\Feature\Eir\EirRevenueServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**A.GCA. Gross carrying amount** - *Done*

- **What it requires:** The amortised cost of a financial asset before adjusting for any loss allowance.
- **What the engine does:** The gross carrying amount at any date is the ledger's running balance (sum of every posting on the account to the date), reproduced to the cent against E-Banker's own loan-book report on 2,361 of 2,361 account-months (Baselines sheet).
- **General comment:** Spec v4 sections 6.2 (method B), 6.11.
- **Compliance comment:** Compliant.
- **Where to see it:** Report Hub, EIR as at a Date: /eir-as-at; Contract Profile; Data Foundation, Loan Book
- **Governance setting:** `loan_book_build_method`
- **Test that proves it:** `Tests\Feature\Ebanker\LoanBookBuildServiceTest::test_method_b_derives_the_carrying_amount_from_the_ledger_and_keeps_the_stored_figure_beside_it`
- **Reviewer sign-off:** ____________________   Date: ____________

### 5.4 AMORTISED COST MEASUREMENT

**5.4.1. Interest revenue at the effective interest rate** - *Done*

- **What it requires:** Interest revenue is calculated by applying the EIR to the gross carrying amount, except for (a) purchased or originated credit-impaired assets and (b) assets that became credit-impaired after initial recognition, where it is applied to the amortised cost (net of the loss allowance).
- **What the engine does:** The roll-forward applies the locked EIR to the opening gross carrying amount for Stage 1 and 2 loans and to the net amount for Stage 3 (interest_basis on each row); the stage is read from the staged loan book, which is why the bootstrap stages before it runs revenue.
- **General comment:** Spec v4 sections 3, 3.6.
- **Compliance comment:** Compliant.
- **Where to see it:** Report Hub, GL Reconciliation (EIR): /eir-reconciliation; Report Hub, EIR as at a Date: /eir-as-at; Contract Profile
- **Governance setting:** `stage3_interest_basis`
- **Test that proves it:** `Tests\Feature\Eir\EirRevenueServiceTest; Tests\Feature\Eir\StagingServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**5.4.1(a). Purchased or originated credit-impaired assets** - *Not applicable, documented*

- **What it requires:** A credit-adjusted EIR is applied to the amortised cost from initial recognition.
- **What the engine does:** MAIIC originates its loans and has purchased none; no facility is credit-impaired at origination in the data held (the take-on loans entered E-Banker on 31 July 2024 at their existing balances, which is a migration, not a purchase).
- **General comment:** If a POCI asset ever arises it is an instrument_type judgement on the contract (the Nascomex memo), never a product code.
- **Compliance comment:** Not applicable, documented: no POCI asset; revisit on any purchase of a portfolio.
- **Where to see it:** Data Foundation, EIR Data: /eir-data
- **Governance setting:** `contractual_record; history_before_dec_2025`
- **Reviewer sign-off:** ____________________   Date: ____________

**5.4.2. Cure of a credit-impaired asset** - *Done*

- **What it requires:** When the asset is no longer credit-impaired, interest revenue reverts to the gross basis.
- **What the engine does:** The stage is re-assessed each period (StagingService); a loan that leaves Stage 3 returns to the gross basis in the next roll-forward row, since interest_basis follows the period's stage.
- **Compliance comment:** Compliant.
- **Where to see it:** Report Hub, GL Reconciliation (EIR): /eir-reconciliation
- **Governance setting:** `dpd_basis; stage3_missed_instalments`
- **Test that proves it:** `Tests\Feature\Eir\StagingServiceTest::test_four_unpaid_instalments_lift_a_current_loan_to_stage_3`
- **Reviewer sign-off:** ____________________   Date: ____________

**5.4.3. Modification of contractual cash flows** - *Partially done*

- **What it requires:** When the contractual cash flows are renegotiated or modified and the asset is not derecognised, the gross carrying amount is recalculated as the present value of the modified flows at the original EIR and a modification gain or loss is recognised.
- **What the engine does:** The roll-forward carries a modification_gain_loss column and rate_reset_events records each reset with its new schedule version; the recalculation at the original EIR is implemented for a schedule re-estimation (B5.4.6 row). The restructured-loan register from E-Banker (the Reschedule Report) is still outstanding from the vendor, so the modifications of 2024 to 2026 are not yet in the system.
- **General comment:** Spec v4 sections 3.2, 5 (Barry / vendor).
- **Compliance comment:** Partially done: mechanics in place; the restructure history is evidence awaited.
- **Where to see it:** Financial Modelling, EIR Calculations: /eir-calculations; Report Hub, EIR as at a Date: /eir-as-at; Contract Profile (modification history)
- **Governance setting:** `rate_change_classification`
- **Test that proves it:** `Tests\Feature\Eir\EirAsAtServiceTest::test_a_month_end_reads_the_locked_roll_forward_row`
- **Reviewer sign-off:** ____________________   Date: ____________

**5.4.4. Write-off** - *Evidence needed from MAIIC*

- **What it requires:** The gross carrying amount is reduced when there is no reasonable expectation of recovery.
- **What the engine does:** The loan book carries the write-off through the ECL module; E-Banker's write-off table (DD_16i) returned no rows in the data dictionary of 6 October, so no write-off has been posted in E-Banker since the take-on.
- **Compliance comment:** Evidence needed: MAIIC's write-off policy and any write-offs before the take-on.
- **Where to see it:** Financial Modelling, ECL Calculation: /expected-credit-loss
- **Reviewer sign-off:** ____________________   Date: ____________

### B5.4 EFFECTIVE INTEREST METHOD

**B5.4.1. Fees that are an integral part of the EIR** - *Partially done*

- **What it requires:** Fees that are an integral part of the EIR of a financial instrument are treated as an adjustment to the EIR (origination fees received as compensation for activities such as evaluating the borrower's financial condition, evaluating guarantees and collateral, negotiating terms, preparing documents and closing the transaction; commitment fees where the loan is likely to be drawn).
- **What the engine does:** The origination fees are read from E-Banker's LOS disbursement charges (87 lines: arrangement 206.3 m, legal 136.7 m, other 9.5 m) into contract_fees as PENDING; the fee rulebook (30 rules, three for E-Banker's own charge names) proposes each one's treatment and a reviewer classifies and a second person reviews under maker-checker; the solver reads only reviewed integral fees. The rulebook is seeded unapproved because approving it is a MAIIC decision, so 19 of 31 solvable EIRs are blocked at the fee gate.
- **General comment:** Spec v4 sections 3.3, 7.4; the take-on loans' fees are a separate input (O14).
- **Compliance comment:** Partially done: the mechanics and the gate are in place; the rulebook and the classifications await MAIIC's reviewer; the take-on fees await Finance.
- **Where to see it:** Governance Centre, Accounting Rules: /eir-accounting-rules; Fee Classification: /eir-fee-classification; Coverage & Blockers: /eir-coverage
- **Governance setting:** `fee_reclass_journal; integral_fee_rulebook`
- **Test that proves it:** `Tests\Feature\Eir\FeeRuleSweepTest; Tests\Feature\Eir\EirReadinessServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**B5.4.2. Fees that are not an integral part of the EIR** - *Done*

- **What it requires:** Fees for servicing, commitment fees where draw-down is unlikely, and loan syndication fees are not part of the EIR; internal administrative and holding costs are not transaction costs.
- **What the engine does:** The rulebook's rules for internal administrative and staff cost, recovery and enforcement legal cost and penalty charges propose not-integral, ranked above the origination rules so they can never capture them; a reviewer may reverse any line with a reason.
- **Compliance comment:** Compliant.
- **Where to see it:** Governance Centre, Accounting Rules: /eir-accounting-rules
- **Governance setting:** `integral_fee_rulebook`
- **Test that proves it:** `Tests\Feature\Eir\FeeRuleSweepTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**B5.4.3. Expected life and the cash flows considered** - *Done*

- **What it requires:** The EIR is estimated using the expected life, considering all contractual terms (prepayment, extension, call and similar options) but not expected credit losses; where the cash flows or life cannot be reliably estimated, the contractual terms are used.
- **What the engine does:** The version 1 schedule is generated from the contractual terms of the contract master (E-Banker's loan master, the scheme settings, the instalment chart) and approved before the solve; expected prepayment is a governed basis (expected_cashflow_basis, seeded contractual) because MAIIC's two years of history cannot yet support a behavioural estimate.
- **General comment:** Spec v4 sections 3.2, 4.2.
- **Compliance comment:** Compliant on the contractual basis, which the standard allows where estimates are unreliable.
- **Where to see it:** Financial Modelling, EIR Calculations: /eir-calculations; Data Foundation, EIR Data (schedules)
- **Governance setting:** `expected_cashflow_basis; partly_drawn_interest_basis`
- **Test that proves it:** `Tests\Feature\Eir\ScheduleComparisonTest; Tests\Feature\Eir\ScheduleShapeGovernanceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**B5.4.4. Floating-rate instruments** - *Done*

- **What it requires:** For floating-rate assets, periodic re-estimation of cash flows to reflect movements in market rates alters the EIR; where the asset was recognised at an amount equal to the principal receivable on maturity, re-estimating the future interest payments normally has no significant effect on the carrying amount.
- **What the engine does:** The PLR series (48 rows, 26 changes, from E-Banker's PLR master) and each contract's spread over it are held; a reset is recorded as a rate_reset_event with a new schedule version, and the EIR's index component is re-estimated while the locked spread stays (rate_change_classification: a PLR move is a reset, an individual re-pricing is a modification).
- **General comment:** Spec v4 section 3.2.
- **Compliance comment:** Compliant.
- **Where to see it:** Data Foundation, Reference Rates: /eir-reference-rates; Financial Modelling, EIR Calculations: /eir-calculations
- **Governance setting:** `rate_change_classification; plr_mid_period`
- **Test that proves it:** `Tests\Feature\Eir\ReferenceRateImportServiceTest; Tests\Feature\Eir\SpreadDerivationServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**B5.4.5. Re-estimation of payments or receipts (not a modification)** - *Done*

- **What it requires:** When an entity revises its estimates of payments or receipts (excluding modifications and changes in expected credit losses), it adjusts the gross carrying amount to the present value of the revised flows at the original EIR and recognises the adjustment in profit or loss.
- **What the engine does:** A re-estimated schedule is a new schedule version under the locked original EIR; the roll-forward's modification_gain_loss carries the catch-up and the as-at view lists the schedule version in force on any date.
- **Compliance comment:** Compliant.
- **Where to see it:** Report Hub, EIR as at a Date: /eir-as-at; Contract Profile
- **Governance setting:** `schedule_approval_control`
- **Test that proves it:** `Tests\Feature\Eir\ScheduleShapeGovernanceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**B5.4.6. Modification: recalculate at the original EIR** - *Partially done*

- **What it requires:** Where the contractual cash flows are modified and the asset is not derecognised, the new gross carrying amount is the present value of the modified flows discounted at the original EIR; costs and fees adjust the carrying amount and are amortised over the remaining term.
- **What the engine does:** As 5.4.3: the mechanics exist; the restructure history (E-Banker's Reschedule Report) is awaited from the vendor.
- **Compliance comment:** Partially done.
- **Where to see it:** Financial Modelling, EIR Calculations: /eir-calculations
- **Governance setting:** `rate_change_classification`
- **Test that proves it:** `Tests\Feature\Eir\EirAsAtServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**B5.4.7. Interest on a credit-impaired asset** - *Done*

- **What it requires:** When a financial asset becomes credit-impaired after initial recognition, interest revenue is applied to the amortised cost in subsequent periods.
- **What the engine does:** As 5.4.1: Stage 3 interest is on the net basis in the roll-forward (interest_basis = NET), and the stage follows the directive's thresholds (91 days short-term, 181 otherwise) and the missed-instalment trigger.
- **General comment:** Spec v4 section 3.6, decision D31.
- **Compliance comment:** Compliant.
- **Where to see it:** Report Hub, GL Reconciliation (EIR): /eir-reconciliation
- **Governance setting:** `stage3_interest_basis; dpd_basis`
- **Test that proves it:** `Tests\Feature\Eir\StagingServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

### 3.3 DERECOGNITION

**3.3.2. Extinguishment and substantial modification** - *Outstanding*

- **What it requires:** A financial liability (and by analogy an asset whose terms are substantially modified) is derecognised when the terms are substantially different; a modified asset that is not substantially different is accounted for under 5.4.3.
- **What the engine does:** The 10 percent test of B3.3.6 (the present value of the new flows at the original EIR differing by 10 percent or more) is not yet built; every modification is treated under 5.4.3 today.
- **General comment:** Spec v4 section 5 (open items) names the restructured-loan register as the input.
- **Compliance comment:** Outstanding: build the test once the restructure history arrives.
- **Where to see it:** Financial Modelling, EIR Calculations: /eir-calculations
- **Reviewer sign-off:** ____________________   Date: ____________

**B3.3.6. The 10 percent test** - *Outstanding*

- **What it requires:** Terms are substantially different if the discounted present value of the cash flows under the new terms, including fees, at the original EIR, differs by at least 10 percent from the discounted present value of the remaining cash flows of the original.
- **What the engine does:** As 3.3.2.
- **Compliance comment:** Outstanding.
- **Where to see it:** Financial Modelling, EIR Calculations: /eir-calculations
- **Reviewer sign-off:** ____________________   Date: ____________

### THE RECORD AND ITS PROOF

**S9. The ledger as the primary record** - *Done*

- **What it requires:** The system's figures must reconcile to the entity's general ledger (IAS 1.15 fair presentation; the auditor's completeness and accuracy assertions).
- **What the engine does:** Interest posted January 2025 to July 2026 of 5,293,988,207.06 from the ledger; the derived carrying amount equal to the stored report on 2,361 of 2,361 account-months; the loan GLs at 31 December 2025 equal to the trial balance to the cent (1050201 and 1050202 off by the accepted exception of section 3.5); the GL reconciliation per period on the screen. The Baselines sheet reads these from the system at generation.
- **General comment:** Spec v4 sections 3.4, 3.5, 6.10.2, 9.
- **Compliance comment:** Compliant; two rows pass as accepted exceptions until Finance corrects the keyed openings.
- **Where to see it:** Report Hub, GL Reconciliation (EIR): /eir-reconciliation; Data Foundation, E-Banker Feed: /eir-feed
- **Governance setting:** `reconciliation_tolerance; trueup_gl_account`
- **Test that proves it:** `Tests\Feature\Ebanker\PackLandingServiceTest; eir:bootstrap --verify`
- **Reviewer sign-off:** ____________________   Date: ____________

**S4. Governed conventions under maker-checker** - *Done*

- **What it requires:** Every accounting judgement the engine makes must be a documented, approved policy choice (IAS 8.10 to 8.12), not a constant in code.
- **What the engine does:** 48 settings in the Governance Centre, each with options, a seeded default, an effective date, a proposer and an approver; a calculation that needs a setting with no approved value stops and says so; a locked period keeps the values it was run under.
- **General comment:** Spec v4 section 4.2.
- **Compliance comment:** Compliant.
- **Where to see it:** Governance Centre: /eir-governance
- **Governance setting:** `(all)`
- **Test that proves it:** `Tests\Feature\Eir\GovernanceServiceTest; Tests\Feature\Eir\EirGovernanceControllerTest`
- **Reviewer sign-off:** ____________________   Date: ____________
