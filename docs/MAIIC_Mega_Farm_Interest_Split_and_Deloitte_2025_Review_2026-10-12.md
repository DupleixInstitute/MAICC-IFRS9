# Mega Farm interest split and the Deloitte 2025 ECL review: what Dr Thom sent, and what it changes in the spec

Dupleix Institute, 12 October 2026. A review of the email Dr Thom (Thomson Kumwenda, CFO) sent at 18:47 on 12 October 2026, and its effect on the specification v4 (`docs/MAIIC_EIR_Engine_Specification_v4_2026-10-07.md`). Nothing in the spec has been changed; section 4 lists the proposed changes for approval.

## 1. What came in

| Item | Saved at (OneDrive, MAIIC `2. Documents from clients\New Doc Received 12 Oct\`) |
|---|---|
| The email, with its full thread (50 messages, 27 Nov 2025 to 6 Mar 2026, between Dr Thom, Tamanda and Deloitte's audit and actuarial teams) | `FW Mega Farm Interest December 2025 (Dr Thom 2026-10-12 18h47 - ...).msg` and `_thread_text_Dr_Thom_2026-10-12.txt` |
| `Mega Farm GListing Interest Income 2025.xlsx` (5.7 MB): E-Banker's interest-split listing for 2025, one row per account per month, with Dr Thom's Stage column added for Deloitte | same folder |

Dr Thom's covering line: "the interest portion 5% for MAIIC for Dec 2025". The workbook is the file he sent Deloitte on 6 March 2026, when the auditors asked for "the K2.7 billion interest income per customer in line with how information was presented in the ECL model".

## 2. What the thread establishes

**The programme and the split, in the CFO's words (6 March 2026).** "The mega farm loan book is a managed loan book on behalf of the government of Malawi ... the interest rate on these loans will be 15% of which 10% will remain in the fund and 5% will go to MAIIC. So revenue earned on the book is split between the Fund and MAIIC in the ratio of 67% Govt and 33% MAIIC. The gross impairment ... the MK41.9 billion hits the fund direct by reducing the mega farms loan book on the asset side and also reducing the fund on the liabilities side." MAIIC's own share (K2.7 to 2.8 billion) "must also be subjected to impairments because the fund has taken large impairments".

**How the 2025 Mega Farm provision was arrived at** (spec 16.8 says this is "still to be confirmed"; the thread answers most of it):

- The loans are seasonal, with one bullet repayment at the end of the season (31 December 2025, and 31 December 2024 for the 2024 cohort). Seed, fertilizer and pesticide loans are unsecured; about five equipment loans carry collateral.
- At 31 December 2025 the seasonal loans were "in 100% default, as no repayments were received", so they are Stage 3.
- **Recovery rate 0 percent on Stage 3** (FY2024 used 33.04 percent). Recoveries are applied only to Stage 1 loans that are fully secured and performing (the mechanisation loans). Deloitte's audit team: "we find management's judgement to be reasonable given the nature of these loans" (17 February 2026).
- **MAIIC takes 80 percent of the exposure as EAD in all its models** (ECL = PD x LGD x (EAD - 20% of EAD), described as a 20 percent "NPL" or recovery allowance); Deloitte takes 100 percent. Deloitte's challenger first gave K51.59 billion against MAIIC's K43.22 billion (+19 percent); after re-running with bullet repayments and recoveries on Stage 1 only, K41.87 billion (-3 percent). Both sides closed on that (24 February 2026).

| Mega Farm, 31 Dec 2025 | Amount (K) |
|---|---:|
| Exposure in the ECL model | 54,498,560,819 |
| MAIIC's model ECL | 43,218,781,409 |
| Deloitte's challenger ECL (final) | 41,866,109,050 |
| AFS note 8 ECL on the loans | 39,761,406,000 |
| Deloitte total less AFS loans ECL | 2,104,703,050 |

The last line matches note 9a's "K2.1 billion" provision on MAIIC's interest share, so **the AFS appears to follow Deloitte's total, split into the fund's provision on the loans (K39.76 billion) and MAIIC's provision on its own interest share (K2.10 billion, 75.5 percent of K2.79 billion)**. The exposure does not bridge as neatly: 54.50 billion less the AFS gross of 51.54 billion is 2.95 billion, of which MAIIC's interest share explains 2.79 billion and 166,372,973 is unexplained. Both points are for Dr Thom to confirm.

**Other points in the thread** (MAIIC's own book, for the ECL module): Deloitte's challenger on the main model agreed within its threshold once MAIIC's formula covered the full data set (a K5 billion difference had come from a formula that missed part of the data); the money-market ECL rose from K6.9 million to K50.5 million while the exposure fell, which the audit team queried; staff-loan ECL used attrition of 1 leaver in 21 staff; six staff loans had a maturity date before the issue date.

## 3. What the workbook shows, and how it ties

- 42,508 rows, 3,629 accounts, January to December 2025, all at 15 percent, every row split **33 percent MAIIC ("bank portion") and 67 percent Government**.
- Totals: MAIIC 2,787,710,620.03; Government 5,659,897,364.13; total interest 8,447,607,984.16.
- Stage column (Dr Thom's): Stage 3 on 3,616 accounts carrying MAIIC interest of 2,630,541,810.17 (94.4 percent); Stage 1 on 13 accounts carrying 157,168,809.86. No Stage 2.

**Against E-Banker's income GLs for 2025** (pack 4, `TB_03`, 31 December 2025, before the closing entry):

| Product (MAIIC 33%) | Workbook | E-Banker GL | GL, 2025 | Difference |
|---|---:|---|---:|---:|
| Fertilizer | 2,012,035,873.98 | 4221 | 2,012,035,873.98 | 0.00 |
| Seed | 412,422,643.34 | 4222 | 412,422,643.34 | 0.00 |
| Working capital | 42,663,694.44 | 4223 | 42,663,694.44 | 0.00 |
| Equipment | 121,836,433.67 | 4224 | 121,836,433.67 | 0.00 |
| Irrigation | 91,950,845.53 | 4260 | 91,950,845.53 | 0.00 |
| Pesticides | 106,801,129.07 | 4262 | 107,021,353.81 | 220,224.74 |
| **Total** | **2,787,710,620.03** | | **2,787,930,844.77** | **220,224.74** |

The GL total is Deloitte's K2,787,930,846. The QuickBooks mapping workbook labels the fertilizer figure 4215 "Interest Income-Mega Farm Ferti": E-Banker's 4215 is a different account (MAIIC agricultural loans, 554,447,006.83), another case of the two charts of accounts (spec 3.3, 7.7).

**Against the ledger** (pack 4, `MF_03`): the Government's 67 percent is posted per loan to GL 2071 ("GOVT_INT 67%"), 5,660,344,487.15 for 2025, which is 447,123.02 more than the workbook. With the pesticide gap above (220,224.74 is 33/67 of 447,123.02), the workbook is short about 667,348 of pesticide interest in total, spread over 61 pesticide accounts (some higher, some lower, net 447,123.02 on the Government side). MAIIC's 33 percent is not posted against the loan account in the ledger; it reaches 4221 to 4262 in batch totals.

**Where the pesticide difference is captured (traced 13 October 2026).** The books are right; the listing is not.

- The Government's share is posted per loan to 2071 in the month-end interest batches (narration "By Trf GOVT_INT_PORTION 67% Interest Portion"). For January 2025 the pesticide interest was posted twice on 31 January: batch INTP 31 (102 postings, since deleted) and batch INTP 3169 (561 postings, live).
- MAIIC's share reaches 4262 in batch totals, not per loan. It agrees with the ledger: the ledger's pesticide 2071 postings for 2025 (217,285,791.40) times 33/67 give 107,021,359.60 against 4262's 107,021,353.81.
- On 61 pesticide accounts the listing gives a different amount from the ledger in every month of 2025 (427 account-months from March to September alone). Recomputing each account's interest from its own month-end balance (pack 3, `MF_04`) matches **the ledger** every time: 392 of the 427 within 2 percent, the rest still nearer the ledger. The listing shows nil on some accounts that carry a balance (000105530000043: 434,000, nil interest all year in the listing) and puts other accounts' amounts on others (000105530000060 and 000105530000057 swap 1,040,909.72 and 66,297.84 in January).
- So the listing sent to Deloitte on 6 March 2026 misallocates interest on 61 pesticide accounts and is 667,347.76 short overall (MAIIC share 220,224.74, Government 447,123.02). The GLs, the ledger and the audited K2,787,930,846 are right. The effect on Deloitte's per-customer allocation is immaterial, but the listing cannot be used as a source without this correction.

**Day count.** The Mega Farm interest is computed on a **360-day year**. Across all six Mega Farm account blocks (69,061 account-months, 2025 and 2026) the Government-share postings come to 365/360 of the ACT/365 amount (median ratio 1.01389; 92 to 94 percent of account-months within 0.2 percent of it, none at 1.0). MAIIC's own book uses ACT/365 (decision D9, proven on 2,047 postings).

So the listing is E-Banker's own split report and, apart from the pesticide misallocation, it ties to both the income GLs and the ledger.

## 4. Effect on the specification

| Spec section | What it says now | What the new evidence changes | Proposed change | Decision needed? |
|---|---|---|---|---|
| 16.1, 16.4 (scheme settings), glossary "Fund share" | Interest 15 percent "of which 5 percent belongs to MAIIC and 10 percent to the fund" | E-Banker splits each month's interest **33 / 67**, stored per row (`BANK_PORTION_PER` 33, `GOVT_PORTION_PER` 67). 33 percent of 15 is 4.95, not 5; a hard-coded one third would overstate MAIIC's 2025 share by 28,158,693 | The engine reads the split percentages from E-Banker per row and never assumes 5/10 or one third; the scheme settings show the split as E-Banker holds it; "5 and 10" stays only as the agreement's description | No (a correctness rule) |
| 16.1 | Income on 4214 to 4224 and 4260 to 4262; "we hold no postings ... for any Mega Farm account yet" | Product-to-GL map now proven: 4221 fertilizer, 4222 seed, 4223 working capital, 4224 equipment, 4260 irrigation, 4262 pesticides; the fund's share is posted per loan to 2071. Pack 4 holds the Mega Farm ledger and balances | Replace the GL sentence with the proven map; update the data-held sentence | No |
| 16.4 row 2, 16.5 step 4 | MAIIC's share computed by the engine on "what is actually expected to come back" (the net carrying amount) and compared with what E-Banker posted | E-Banker, the AFS and Deloitte all take MAIIC's share **on the gross balance**, then provide for it (K2.10 billion on K2.79 billion). 94.4 percent of the share sits on Stage 3 accounts. On a net basis for credit-impaired loans (IFRS 9 5.4.1(b), and review finding R24) most of the 2.63 billion would not be recognised as revenue at all, instead of being recognised and 75 percent provided | Keep both views and bridge them: (a) the 2025 reproduction, gross share less provision, as E-Banker, the AFS and Deloitte did; (b) the IFRS 9 view, net basis on Stage 3. The difference is shown, not hidden | **Yes, Dr Thom** (with R24 and O22) |
| 16.5 step 4, register of sources | The comparison is against "what E-Banker actually posted" | The interest-split listing is the E-Banker report that holds the split per account and month and ties to the GLs; it is the natural comparison source | Register it as a source query for the monthly pack (ask Barry which table the report reads, so it comes as an extract rather than a workbook); add the GL and 2071 ties as standing checks, with the 61-account pesticide difference as the first exception | No (Barry to name the table) |
| 16.8 "How the 2025 provision was actually arrived at is still to be confirmed" | Proposal pending Finance's answer | Answered in large part: season-end bullet loans, 100 percent default at 31 Dec 2025, Stage 3 recovery 0 percent (2024: 33.04 percent), recoveries only on secured performing Stage 1 loans, EAD at 80 percent of exposure in MAIIC's model, final figures as in section 2 | Replace the sentence with the method as audited; make it the 2025 reproduction card; keep 16.8's six-step method as the forward proposal | No for the record; the go-forward method stays a decision |
| 16.2 (note 8 table and note 9a) | Note 8 figures; K2.1 billion on MAIIC's interest | The Deloitte-to-AFS bridge in section 2 (41.87 = 39.76 + 2.10); 166.4 million of exposure unexplained | Add the bridge, marked "to confirm" | Yes, Dr Thom to confirm |
| 16.4 row 6, 16.5 step 4 (the engine's 15 percent split) | The engine works out 15 percent "for the month"; D9 sets ACT/365 for the engine | E-Banker computes Mega Farm interest on ACT/360; an ACT/365 recomputation would be 1.37 percent lower on every account and fail the comparison | The Mega Farm scheme settings carry their own day count, read and proven from the data (ACT/360), separate from D9 for MAIIC's book | No (a correctness rule) |
| 16.6 (the seed checks) | Two checks "the pack 2 data will settle" | The listing gives the seed split per account and month (MAIIC 412.4 million); with pack 4's ledger the K700 million / K379 million question can now be settled | Run the check and record the result | No |
| ECL module, EAD rule (sections 14 to 16, and the 2025 baseline) | EAD is the exposure | MAIIC's 2025 models take EAD at 80 percent of exposure; Deloitte at 100 percent. Reproducing 2025 needs the 80 percent; the IFRS view should use 100 percent unless the 20 percent is shown to be an expected recovery (then it belongs in LGD, not EAD) | Governed setting for the 2025 reproduction only, labelled as MAIIC's 2025 convention; the go-forward EAD at 100 percent with recoveries in LGD | **Yes, Dr Thom** |
| ECL module, Mega Farm LGD | 16.8 step 4: "put the probability second and recovery first" | The audited 2025 position is 0 percent recovery on Stage 3 and recoveries only on secured Stage 1 | Seed the Mega Farm LGD for the 2025 reproduction with those rules; the forward LGD from collection experience (16.8 step 4) remains the proposal | Partly: the forward LGD |
| Section 5 (open questions) | "How the 2025 Mega Farm provision of K39.76 billion was worked out" asked of Finance | Largely answered | Close it; replace with the two "to confirm" points (the bridge, the 166.4 million) | No |

## 5. Questions for Dr Thom arising

1. MAIIC's interest share on Stage 3 loans: recognise it gross and provide for it (as in 2025), or recognise it on the net carrying amount from now on (IFRS 9 5.4.1(b))? About K2.63 billion of the 2025 share was on Stage 3 accounts.
2. The 2025 AFS: does note 8's K39.76 billion plus note 9a's K2.10 billion come from Deloitte's K41.87 billion challenger rather than MAIIC's own K43.22 billion? And what is the 166.4 million between the ECL exposure (K54.50 billion) and note 8's gross plus MAIIC's share?
3. The 80 percent EAD: is the 20 percent meant as an expected recovery (so that it belongs in LGD) or as an expected repayment before default?
4. The listing sent to Deloitte misallocates the interest on 61 pesticide accounts (the ledger and GL 4262 are right; section 3). Is the listing produced by an E-Banker report that needs fixing by the vendor, and was it used for anything besides Deloitte's per-customer breakdown?

## 6. Caveats

- The thread quotes figures inside email tables that were reformatted when forwarded; the totals used here were re-read from the cells and agree across the messages that repeat them.
- The Stage column was added by Finance for Deloitte; it is not an E-Banker field.
- Nothing here changes MAIIC's own book (the EIR engine's scope) beyond the EAD point and the GL-map note.
