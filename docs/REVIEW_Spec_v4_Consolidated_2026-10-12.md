# Consolidated review of spec v4: Codex and Copilot, with Dupleix's verdicts

12 October 2026. Sources: `docs/REVIEW_Spec_v4_Codex_2026-10-12.md` (24 findings, R01 to R24, priorities P1 and P2) and `docs/REVIEW_Spec_v4_Copilot_2026-10-12.md` (8 findings, here Cp1 to Cp8, in the order of its table). Each finding was checked against the repository and the client files; a verdict says whether it is accepted, accepted in part, or not accepted, and what is done about it.

Overlaps: Cp8 = R01 (the fee rule); Cp7 = R05 (the 44 percent evidence); Cp1 is part of Codex's closing note (evidence not committed); Cp2 = R07 (status H); Cp3 = R10 (reschedules); Cp6 is part of R21 (built against to build). Cp5 (7.1) and Cp4 (4.2) are Copilot's alone.

**Totals:** 27 distinct findings (Codex 24, Copilot 3 not raised by Codex). 24 accepted (R13, R14 and R15 as decisions for Dr Thom and Deloitte), 2 accepted in part (Cp1, R20), 1 not accepted (Cp4).

## A. Before pack 4 is sent

| ID | Finding | Verdict | Action |
|---|---|---|---|
| R22 | The `GL_04` and `GL_05` queries omit the branch fields and the posting, deletion and approval flags, so the batch key (date, type, number) may not be unique across branches and a deleted or unposted leg cannot be told apart | **Accepted, P1 for the batch link** | Add `cb_branch_mst_id`, `t_branch_mst_id`, `o_branch_mst_id`, `pass_flag`, `delete_flag` and `modify_delete_flag` to `GL_04a`, `GL_04b`, `GL_05` and `CH_02b` before sending; ask ICT whether a batch number is unique per branch. The rest of R22 (older rows changed after the two-month window) is a design item, section C |

## B. Specification corrections (wording, status, evidence)

| ID | Section | Finding | Verdict | Correction |
|---|---|---|---|---|
| R01, Cp8 | 7.8.4 | "A type-301 line in a batch that also credits a fee GL" also captures the drawdown itself (Malawi Police SACCO, 1,046,210,000, one gross line) | **Accepted, P1** | Only a separate fee debit is the fee capitalised: a type-301 line whose own voucher, amount and fee leg identify it as a fee (or whose narration names a fee). A gross drawdown stays a drawdown at its own date. Add worked examples for both layouts and a later genuine tranche |
| R05, Cp7 | 3.7, build-files | The script's match is not one-to-one, includes closing vouchers and one repayment (Ecogen, type 305); "about 44 percent" needs its method | **Accepted, P2** | State "about 44 percent, matching GL lines to type-301 fee debits on the loan account by date and amount, closing vouchers excluded"; repair `fee_gl_recon_2026-10-11.py` (debit type 301 only, one-to-one, flag the two ambiguous 855,000 lines); replace with the batch link once `GL_04` lands |
| Cp1 + Codex note | 3.7 | The GL spools and Finance's 2025 fee workbook are read from OneDrive, not committed | **Accepted in part** (the 4206 bridge itself is committed: `docs/bootstrap/trial-balances/afs-bridge-2025-12` and `afs-mapping-2023-to-2026-08`) | Commit the spools and the fee workbook as received under `docs/bootstrap/`, with hashes, and point the script at them |
| R06 | 3.7, 4.2 O21, 7.7 | The workbooks prove where the 259,317,000 was presented, not how it was measured; "no subset of whole lines" does not prove an estimate | **Accepted** | Mark the presentation bridge Proven; record "an audit estimate" as Finance's explanation; say that replacing the presentation entry needs a fee deferral roll-forward (opening deferred fees, amortisation, closing), designed with the journal (section D) |
| R07, Cp2 | 2.1, 3.8 | What is proven (H in the master, no H in the history, 20 closure credits of 38,492,032.54) is mixed with what is inferred (H means written off); the December dates are wrong | **Accepted** (Copilot's rewording "Finance has advised" is **not** accepted: Finance has not advised) | Split Proven from inferred; correct the dates: 13 accounts on 16 December, 3 on 11 December, 1 on 30 December 2025, 4 on 30 June 2026; date a write-off from its posting, never from the H status |
| R08 | 3.8 | Batch-level classification can catch an unrelated credit; partial write-offs; Imani's excess treated as a customer liability | **Accepted, P1** | Classify by the individual loan voucher allocated against a confirmed 1064 leg; a write-off reduces only the confirmed amount (whole or part), the remaining asset continues; Imani's 861,723.83 is held as a reconciliation exception for Finance, not booked as a liability |
| R10, Cp3 | 3.9 | An edit or a different chart copy is a candidate, not a confirmed contractual change; `FIXLOAN_INST_CHART1` has no date; the register is not built | **Accepted, P1** | Status line "Proposed; awaiting `RS_02`; register not built"; candidate detection separate from confirmed events; resolve each edit to its loan, use the accepted/passed flag, classify correction, contractual reset, modification or uncertain; fail closed without an effective date or the old terms |
| Cp5 | 7.1 | "`GlInterestImportService` ... is retired" is false: it still writes the ledger's interest (`ContractInputsBuildService::glInterest`) and the Extract C intake | **Accepted** | "Extract C is retired as the source; `GlInterestImportService` remains the writer, fed from the ledger's 303 and 120 postings" |
| Cp6, R21 | 6.5, 7.8, 8 | Built against to build is unclear: section 6.5 says all five routes are built while 7.8.3 says route 5 is not started; P4b mixes done and to do | **Accepted** | A status matrix per route (query registered, endpoint built, exporter complete, infrastructure configured, evidence received, proven end to end); a status line at the top of 7.8; split P4b into done and outstanding |
| R18 | 6.10.2, 9 | The baseline headed "2025" quotes the 19-month interest total; fixed exception counts will fail when the vendor fixes defects; old-model reproduction mixed with new-model results | **Accepted, P2** | Correct the label to January 2025 to July 2026; separate file-evidence baselines, historical-model reproduction and current results; say how an approved source correction retires an exception count |
| R20 | 4.1 D21, 4.2 | D21 says settings are seeded from 1 January 2025; the seeder uses 1900-01-01 | **Accepted in part** (the cited `fee_review_self_override` does not exist in the code, so that part is not accepted) | Correct D21 to the inception-effective technical default and say it is not a historical MAIIC approval; add a test that the 4.2 table matches `GovernanceService::catalogue()` |
| Cp4 | 4.2 | `fee_source_precedence` missing | **Not accepted** (4.2 already marks it To build) | Retitle "Decided and seeded" to cover to-build rows, or footnote them |
| R16 | 6.9 | "A blank means no such fee" conflicts with the gate that refuses to assume fee-free; reconstructed pre-migration cash is an estimate | **Accepted, P1** | Blank = unknown, zero = confirmed nil (the screen text and the controller comment change too); label an origination reconstruction as an approved estimate, separate from evidenced cash |
| R24 | 16.3 to 16.5 | The AFS page 34 citation is an amendments list, not a Mega Farm analysis; the net-interest rule is applied to Stage 1 too | **Accepted, P1** | Remove the page 34 claim; net basis only for credit-impaired assets, gross for performing ones; the programme agreement is needed (section D) |
| R23 | 14.7, 15.5 | Weighting ECL across scenarios does not create non-linearity a linear model lacks; the "higher than the loss at the average" claim is not guaranteed | **Accepted, P2** | Reword as preserving non-linearity where the model has it; add a linear equality example to acceptance |

## C. Design rules to add (build items, P4b and later)

| ID | Rule | Verdict |
|---|---|---|
| R02 | Every fee line keeps its posting date, effective date, known-at date and reversal link; a later fee or correction never silently changes a locked result | Accepted, P1 |
| R03 | Precedence resolves per fee event and coverage period, not per contract: a partial source never suppresses another year's or tranche's fee; a missing row is not a confirmed zero; overlaps are quarantined | Accepted, P1 |
| R04 | The fee gate excludes year-end closing vouchers and compares with the pre-closing trial balance (the December 2025 spools are nil only because of the closing voucher) | Accepted, P1 |
| R09 | One economic posting classifier (cash, write-off, fee, re-booking) used by methods A, B and C, the actual-transactions intake, the as-at report, LGD and the RBM return; raw repayments kept beside classified cash; acceptance: all three methods exclude the June 2026 batch and the 2024 type-300 write-offs from cash | Accepted, P1 |
| R11 | Locks pin the source-row versions, fee selection, rate and schedule versions and governance history; an as-at request reproduces the locked view unless a restatement is asked for | Accepted, P1 |
| R12 | The as-at report uses the contractual rate inside a month but labels it EIR (`EirAsAtService`, part-month branch); separate contractual accrual from EIR revenue and tie them at month-end | Accepted, P1 (a code defect) |
| R17 | A zero forward-looking overlay in the bootstrap is a demonstration, never an official result: official runs, locks and the auditor export are blocked until an approved model or overlay exists | Accepted, P1 |
| R19 | Method B for July to November 2024 has balances but not rates, arrears or status; state coverage per field and month, and refuse official runs for months without them | Accepted, P2 |
| R22 (rest) | A change feed or scheduled full reconciliation for older rows changed after the two-month window, with explicit deletions | Accepted, P2 |

## D. Accounting decisions for Dr Thom and Deloitte

| ID | Question | Note |
|---|---|---|
| R13 | The 181-day Stage 3 point (D31) rests on the RBM directive; IFRS 9 B5.5.37 needs reasonable and supportable evidence for a later default point; the AFS policy says 90 days and four missed payments | Keep prudential and IFRS views apart; reproduce 2025 under its own rules, then bridge |
| R14 | A manual (M) rate change is not automatically a market reset; the 10 percent test is the liability test, not an asset rule | Classify by contractual clause and cause; document the asset derecognition policy (O16, O17) |
| R15 | D20 makes E-Banker the contractual record even where the signed letter differs | E-Banker is the operational source subject to contractual validation; material differences held or corrected with lineage |
| R06 (journal) | Replacing the presentation entry needs a fee deferral roll-forward and separate entries for deferral, amortisation, prior-period differences and the suspense reversal | With the true-up account (O10) and the fee journal (O21) |
| R24 (programme) | The Mega Farm programme agreement and the accounting scope (loan interest, the fund's share, MAIIC's receivable and service income) | Needed before section 16 is final |

## E. Not accepted

- Cp2's rewording "Finance has advised that status H means written off": Finance has not advised it; the finding's substance is taken through R07.
- Cp4: the setting is already marked To build.
- The part of Cp1 saying the 4206 bridge is not in the repository: it is.
- The part of R20 citing `fee_review_self_override`: no such setting exists in the code.

## Order of work proposed

1. Section A now, before pack 4 is sent.
2. Section B in the specification, with each change recorded in section 1 against its finding ID.
3. Section C written into the specification as rules with their acceptance cases, and built in P4b.
4. Section D sent to Dr Thom as a short decision note.

## Status 14 October 2026

This section records each finding as it stands in the code at HEAD `abe140d` on `eir_revenue_recognition` (14 October 2026). Commit `216aee2` built the GL fee route (`GlFeeBuildService`, `FeeSourcePrecedence`, the fee GL gate), the tranche register and the take-on EIR check; commit `220a5a6` built the write-off classifier and register. The specification wording of sections B and C is being applied to spec v4 on 14 October. Status words: BUILT, PARTLY, NOT BUILT, WAITING ON MAIIC; N-A means a wording-only item.

| ID | Built or applied? | Evidence | Spec wording |
|---|---|---|---|
| R01, Cp8 | BUILT | A type-301 line is a fee debit only in a batch whose loan legs equal its fee-GL legs, or when its narration names a fee; a gross drawdown stays a drawdown (`GlFeeBuildService.php:155-176`, `ContractInputsBuildService.php:202-232`; `GlFeeRouteTest::test_fee_debits_join_their_drawdown_and_the_takeon_opening_is_not_a_tranche`) | being applied 14 Oct |
| R02 | PARTLY | Each fee line keeps its transaction date and source id, and a reversal is kept as its own negative line (`GlFeeBuildService.php:37-38,365-366`). A locked EIR keeps its fee lines in `input_snapshot` (`EirContractInputService.php:146-162`) and cannot be recomputed. There is no effective date, no known-at date and no reversal link | being applied 14 Oct |
| R03 | NOT BUILT | Precedence is applied per contract, not per fee event and coverage period (`FeeSourcePrecedence.php:88-110`). Differences between sources are listed as exceptions (`:131-140`) but not quarantined | being applied 14 Oct |
| R04 | BUILT | The fee GL gate leaves out year-end closing vouchers (batch YRCLO, types 800 and 801) and reads the trial balance pre-closing (`GlFeeBuildService.php:286-325`). It is reported on every `eir:build-gl-fees` and bootstrap run (`Bootstrap.php:251`) | being applied 14 Oct |
| R05, Cp7 | BUILT (batch link) | The batch link replaces the date-and-amount match (`GlFeeBuildService::assign`, `GlFeeRouteTest::test_fees_are_tied_to_their_loans_through_the_batch_and_the_rest_held_with_the_reason`). `docs/build-files/fee_gl_recon_2026-10-11.py` is not repaired (unchanged since `f395942`) | being applied 14 Oct |
| Cp1 | NOT BUILT | The GL spools and Finance's 2025 fee workbook are not under `docs/bootstrap/`. The script still reads them from OneDrive | N-A |
| R06 | NOT BUILT | No fee deferral roll-forward. Its journal waits on the true-up account (O10) and the fee journal (O21) | being applied 14 Oct |
| R07, Cp2 | N-A (wording) | The code dates a write-off from its posting, never from status H (`WriteOffClassifier.php:45-66`) | being applied 14 Oct |
| R08 | NOT BUILT | Classification is still batch-level: every credit in a batch that debits GL 1064 is a write-off (`WriteOffClassifier.php:55-58,73-92`). The amount is not limited to the confirmed 1064 leg, and partial write-offs and the Imani exception are not handled | being applied 14 Oct |
| R09 | PARTLY | One classifier is used by the loan-book build (method B), the contract inputs, the EIR ledger cash and the instalment chart (`WriteOffClassifier`, `InstalmentChartHistory`). The PD counts the write-offs the build records (`PdSegmentationService.php:340,413`). It is not used by the as-at report, the LGD (which infers write-off from absence), the RBM return or the intake | being applied 14 Oct |
| R10, Cp3 | NOT BUILT | No reschedule register and no classification of events. RS_02a is read only to know the chart in force at each month-end (`InstalmentChartHistory`) | being applied 14 Oct |
| Cp5 | N-A (wording) | `GlInterestImportService` remains the writer of the ledger's 303 and 120 interest | being applied 14 Oct |
| Cp6, R21 | N-A (wording) | A route status matrix and the split of P4b are wording | being applied 14 Oct |
| R11 | PARTLY | The EIR's `input_snapshot` pins the cash flows, the fee lines, schedule version 1 and the day count (`EirContractInputService.php:128-162`). Source-row versions and governance history are not pinned, and an as-at request does not reproduce the locked view | being applied 14 Oct |
| R12 | NOT BUILT | The part month is still accrued at the contractual rate and returned as `eir_period_to_date` (`EirAsAtService.php:123-127,188`). `EirAsAtServiceTest::test_a_date_inside_a_month_accrues_the_part_month_at_the_contractual_rate_on_actual_days_less_cash_received` asserts that behaviour. No change since 12 October | being applied 14 Oct |
| R13 | WAITING ON MAIIC | Dr Thom and Deloitte (the 181-day point) | being applied 14 Oct |
| R14 | WAITING ON MAIIC | Dr Thom (O16, O17); `rate_change_classification` and `modification_threshold` are read by no code | being applied 14 Oct |
| R15 | WAITING ON MAIIC | Dr Thom (D20); `contractual_record` is read by no code | being applied 14 Oct |
| R16 | PARTLY | A blank fee row is not taken as nil: it is flagged "fees not supplied" and is not evidenced (`TakeonLandingService.php:212-216`). No label marks an origination reconstruction as an approved estimate | being applied 14 Oct |
| R17 | PARTLY | `fli:month-end` refuses to run without an approved model and set (`FliMonthEndCommand.php:66-73`). The period lock and the auditor export do not check that the result is more than a bootstrap demonstration (`AuditorPackController`) | being applied 14 Oct |
| R18 | N-A (wording) | Baseline labels | being applied 14 Oct |
| R19 | NOT BUILT | A derived month carries a row flag (`LoanBookBuildService.php:353`), but coverage is not stated per field and month, and official runs are not refused for July to November 2024 | being applied 14 Oct |
| R20 | PARTLY | Seeder effective from 1900-01-01. No test checks that the spec 4.2 table matches `GovernanceService::catalogue()` (61 settings) | being applied 14 Oct |
| R22 | PARTLY | Section A was done: pack 4 queries carry `cb_branch_mst_id`, `pass_flag`, `delete_flag` and `modify_delete_flag` (`docs/bootstrap/queries/Pack 4 - 11 Oct 2026.sql:129-177`), and the GL fee route drops deleted and unposted legs (`GlFeeBuildService.php:347`). Neither batch key includes the branch (`WriteOffClassifier.php:96-104`, `GlFeeBuildService.php:359`). The change feed for older rows is not built | being applied 14 Oct |
| R23 | N-A (wording) | Non-linearity wording | being applied 14 Oct |
| R24 | WAITING ON MAIIC | The programme agreement and D30. The programme is kept out of the main PD, LGD and ECL (`MegaFarmProgrammeSeparationTest`), but its ECL is still EAD x PD x LGD x share (`MegaFarmEclService.php:166`) | being applied 14 Oct |
| Cp4 | N-A (not accepted) | `fee_source_precedence` has since been built and is in the catalogue (`GovernanceService.php:241`, `FeeSourcePrecedence`) | being applied 14 Oct |

Count (27 distinct findings):

| Status | Count | Findings |
|---|---|---|
| BUILT | 3 | R01, R04, R05 |
| PARTLY | 7 | R02, R09, R11, R16, R17, R20, R22 |
| NOT BUILT | 7 | R03, R06, R08, R10, R12, R19, Cp1 |
| WAITING ON MAIIC | 4 | R13, R14, R15, R24 |
| N-A (wording or not accepted) | 6 | R07, R18, R21, R23, Cp5, Cp4 |
