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
