---
kicker: MAIIC | IFRS 9 EFFECTIVE INTEREST RATE ENGINE
title: The 2025 fees by loan: Finance's workbook, the GL, and how they enter the engine
subtitle: Where the records are, why the fees look duplicated but are not, where duplication can really happen, and what the design does about it
date: 11 October 2026
prepared: Dupleix Institute, for the build team and MAIIC Finance
status: Research note. Feeds specification v4 sections 3.7, 7.4 and 6.10.
---

## 1. The records, and where each one is kept

| Record | What it is | From | Saved at |
|---|---|---|---|
| **MAIIC EIR Assessment as of 31st December 2025 with account numbers.xlsx** | **The latest file that maps the fees to the loans**: 34 loans of 2025, each with its E-Banker account number, value date, approved and disbursed amounts, and the columns LEGAL FEES, LEGAL COST, ARRANGEMENT FEES, APPRAISAL FEES, DEFAULT FEES | Tamanda, 15 Sep 2026 12:43 ("EIR calculation file for 2025 with account numbers") | OneDrive `2. Documents from clients\EIR Assesment\` and `3. Project Execution\Amortisation Analysis\`. **Not in the repository.** |
| MAIIC EIR Assessment as of 31st December 2025.xlsx | The earlier version, 67 loans by name (2023 to 2025), no account numbers | Tamanda, 19 Aug 2026 16:20, with the GL spools | OneDrive `EIR Assesment\` and `New Doc Received 19 Aug\Dupleix 2026\`. Not in the repository |
| GL 4871 and GL 4873 spools, 2025 and 2026 to July | The fee income ledgers line by line (date, narration, amount; no account number) | Tamanda, 19 Aug 2026 16:20: "For GL 4871 and 4873 I have attached a file that contains arrangement and legal fees for 2025" | OneDrive `New Doc Received 19 Aug\Dupleix 2026\Dupleix 2\`; the email as `.msg` beside it. Not in the repository |
| `P2_07` LOS_DISB_CHARGES_POST | The fees as the loan origination system captured them (agreement, legal, other), per account, no date, POST_FLAG N | Barry, 7 Oct 2026 (3,651 of 10,064 rows; the whole table is RUN 5 of pack 4) | Repository `docs/bootstrap/ebanker-pack-2026-10-07/`; loaded by the bootstrap into `contract_fees` |
| Batch Detail Report, Unpass Register | Samples of E-Banker's batch printout (every leg of a batch, with GL, account, narration, entered and passed by) | Tamanda, 28 Sep 2026 10:53 | OneDrive `New Doc Received 28 Sep\` (saved 11 Oct 2026 with the email) |

The statement that fees are deducted at disbursement came from Loti in the walkthrough and was put in writing in our email of 11 September: the fees are captured by Finance from the offer letter and deducted from the amount advanced, so a MWK 100 million facility with MWK 5 million of fees pays out MWK 95 million and is carried at MWK 100 million.

## 2. The workbook agrees with the GL

| 2025 | Workbook (34 loans) | GL before the closing voucher | Difference |
|---|---:|---:|---:|
| 4873 Arrangement fees | 311,342,792.00 | 311,342,792.00 | 0.00 |
| 4871 Legal fees | 171,127,335.00 | 171,128,135.00 | -800.00 |

The 800 is one cell: Chikwendeni General Dealers (000106050000002), legal fee 1,808,000 in the workbook, 1,808,800 in the GL (17 November 2025). Every GL fee line of 2025 traces to a loan in the workbook; the only lines that do not match one for one are groupings and corrections, each explained:

- 2 January 2025: 8,908,500 legal and 14,500,000 arrangement are two loans posted together, Ebenezer Midian (8,050,000 and 14,000,000) and Doorbell Services (858,500 and 500,000).
- Ebenezer Midian Limited (084): 5,900,900 is two GL lines, 5,900,000 and 900.
- Mchinji Civil Servants SACCO (086): 3,020,000 posted 27 August, reversed 24 October as over-deducted, 2,920,000 posted the same day.
- Focus Trading: 16,185,000 is two lines of 7 November, 5,685,000 and 10,500,000.
- Promenade Medical Centre: 21,753,650 to arrangement on 30 December, of which 7,629,800 moved to legal the same day.

**Why the workbook looks duplicated.** Row 39 subtotals the fee columns (`=SUM(S5:S38)`); row 46 then sums `S5:S39`, which adds the subtotal in again, giving 342,254,670 and 622,685,584, exactly twice the fees; row 50 subtracts the GL from that doubled figure, so the "difference" equals the whole GL. It is a formula range, not a second posting.

**The value date is not the posting date.** The workbook's Value Date is the loan's; the fee reached the GL on the drawdown, sometimes weeks later (JAT Investment: value 4 August, fee 30 September; Chikomanya: 12 June and 25 July). A monthly comparison has to use the GL posting date.

## 3. The posting: one fee, one entry

At drawdown E-Banker posts one balanced batch: the loan account is debited with the gross amount, the bank is credited with the cash paid out, and 4871 and 4873 are credited with the fees. The fee appears in two places because a double entry has two sides, not because it is recorded twice:

| Leg | Malawi Police SACCO, 7 May 2025 |
|---|---:|
| Loan account 000104430000072, debit (the amount the loan is carried at) | 1,046,210,000 |
| Bank, credit (cash paid out) | 1,000,000,000 |
| 4873 Arrangement fees, credit | 30,000,000 |
| 4871 Legal fees, credit (narrated "arrangement") | 16,210,000 |

E-Banker writes the loan side in one of two ways. About 56 percent of 2025 fee income has its own debit line on the loan account, type 301 with a fee narration ("To Trf Arrangement fees"), beside a net drawdown line; the rest is folded into one gross drawdown line, as above. Either way the loan is carried gross and the income is credited once.

The other records of the same fee are not postings: the LOS charges table is what was captured at application, the workbook is Finance's analysis, and the offer letter is the contract. Of the 21 loans that are in both the workbook and `P2_07`, 12 agree and 9 differ, and in each case the workbook agrees with the GL (Saile legal 5,050,000 posted against 8,100,000 captured; JVD 4,075,000 against 4,880,000; Kwithu's legal and arrangement swapped in the capture). The posted figure is the true one.

## 4. Where duplication can really happen: in the engine

The engine's initial net investment is the amount drawn less the integral fees received plus the costs paid (`EirContractInputService`). With the loan carried gross, gross less fees is the cash paid out, which is right, provided each fee is counted once. Three things can break that.

1. **The same fee from two sources.** The bootstrap loads `P2_07` into `contract_fees` (source `E-Banker LOS_DISB_CHARGES_POST`). If Finance's workbook is imported through EIR Data Intake as well, the 21 loans in both carry their fees twice, and the net investment is reduced twice: the EIR is overstated. `FeeImportService` only skips a repeat of the same source and external id, so it does not catch this. Extract A's fee columns and the take-on blocks are two more routes to the same kind of fee.
2. **A wrong source chosen.** For 9 of those 21 loans the captured figure is not what was posted.
3. **A fee debit treated as a later drawdown.** `ContractInputsBuildService::disbursements()` turns every type-301 debit on a loan account into a disbursement tranche. A fee debit on the drawdown date is harmless (it is part of the gross amount at origination), but one posted later (Mchinji's 27 August line, the October correction) becomes a fake second advance, and a fee reversal is skipped.

## 5. The design

1. **One source of record per fee: the posted fee.** The fee that counts is the one the GL carries, tied to its loan. Until `GL_04` arrives that is Finance's workbook for 2025 (it ties to the GL within the 800 above); once `GL_04a` and `GL_04b` land, it is the GL fee line tied to the loan by its batch (the loan leg and the fee leg share the date, batch type and batch number), with the posting date. The LOS capture, Extract A's fee columns and the offer letter are evidence, shown beside it, not added to it.
2. **Precedence in `contract_fees`, governed.** A new Governance Centre setting (`fee_source_precedence`, default GL-posted, then Finance workbook, then LOS capture, then Extract A) decides which source a contract's fees come from. When a higher source holds fees for a contract and fee type, the lower lines are marked `SUPERSEDED` (kept, visible, never used in the EIR) and any difference in amount is listed on Fee Classification as an exception for Finance.
3. **The workbook enters dated by the GL.** It is committed as received under `docs/bootstrap/fees/` with a mapped import file that carries, for each loan and fee type, the GL posting date and the GL code (4871 or 4873), source `MAIIC Finance EIR assessment 2025`, and the 800 on Chikwendeni noted against the GL figure. With that, the fee GL check built on 11 October agrees by month as well as by year.
4. **Fee debits are not drawdowns.** The disbursement builder recognises a type-301 debit whose batch also credits a fee GL (from `GL_04b`), or whose narration names a fee, as the fee being capitalised into the loan: it is added to the amount drawn at origination, not made a tranche of its own; a reversal reduces it.
5. **The check stays on.** The fee GL check (section 7.4) is the control that proves the result: once the sources are de-duplicated, the year and every month agree with 4871 and 4873.

## 6. Questions for Finance

- Chikwendeni General Dealers: is the legal fee 1,808,000 (workbook) or 1,808,800 (GL)?
- The 2026 fees: will the workbook be kept for 2026, or is the GL by loan (`GL_04`) enough?
- The LOS capture: when it differs from what was posted (9 of 21 loans in 2025), is the posting corrected, or the capture?
