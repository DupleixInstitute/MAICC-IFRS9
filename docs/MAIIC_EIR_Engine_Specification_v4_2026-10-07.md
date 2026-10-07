---
kicker: MAIIC | IFRS 9 EFFECTIVE INTEREST RATE ENGINE
title: Specification, version 4
subtitle: What the follow-up extracts proved, what has been decided, how the data is loaded, the user interface, and what is left to build
date: 7 October 2026
prepared: Prepared by Dupleix Institute for the Malawi Agricultural and Industrial Investment Corporation plc (MAIIC)
people: Project sponsor Dr Thomson Kumwenda, CFO | Engagement lead Edward Mazibuko CA(SA) | Build lead Kundai Muriwo
status: Working document. Replaces version 3 of 24 September 2026 as the single point of reference; version 3 is kept in 9. Archive and still applies wherever this document is silent. Where the two differ, this one governs.
---

## 0. How to read this document

Version 3 was written before MAIIC's database could be read directly. It described the engine around three extracts (A, B and C) that MAIIC's ICT team produced with their own scripts, and it listed twenty-three choices that were still open. Between 5 and 7 October 2026 Barry Makumba ran twenty-seven data-dictionary queries and eighteen follow-up extracts for us, then five more on request. Those extracts are the whole loan ledger and every supporting table, and they changed the picture: the data gaps of version 3 are closed, the arithmetic of the core system is reproduced to the cent, and most of the open choices can now be settled on evidence rather than on argument.

This document records that. It is written, as before, for three readers at once: the CFO, who has to be confident that the numbers will stand up to Deloitte; the ICT manager, who supplies the data and hosts the system; and the developers, who build it. Every section starts in plain language. The same three words are used in the same way as in version 3:

- **Agreed** means a decision that has been taken, by whom and when.
- **Decided and seeded** is new. It means a choice that has been settled by Dupleix on the evidence, written into the Governance Centre as the default in force, and is waiting only for Dr Thom to confirm or change it. Section 4 lists every one, with its default.
- **Proven** means a fact tested against MAIIC's own data and reproduced, with the file and the count given so that anyone can repeat the test.

Where a file is named, it is one of the extracts in `2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC\Follow-Up Scripts Resutls\`. The scripts that produced every figure in this document are in `Build files\` beside them, with a README that says which script makes which number.

## 1. What has happened since version 3

| When | What |
|---|---|
| 25 Sep 2026 | Progress meeting. Phases P1 to P4 built and merged: the Governance Centre, reference rates and spreads, contractual interest and the reconciliation, schedules. |
| 5 Oct 2026 | MAIIC's three extract scripts analysed. The data-dictionary pack of 27 queries written and run by Barry the next morning. Codex reviewed the analysis; eight corrections adopted. |
| 6 Oct 2026 | 18 follow-up extracts specified, with a run order and session settings that make every date unambiguous. Barry ran all 18 by 10 am on 7 October. |
| 7 Oct 2026 | The 18 extracts audited in full. Five further queries run the same day to explain two reconciling differences. The take-on amortisation schedules mapped to E-Banker accounts. The open choices of version 3 written into the Governance Centre with seeded defaults. Two of them decided: which record is contractual (O19) and how a mid-month PLR change is charged (O2). |

The result in one sentence: **the engine's inputs are now complete, every reconciliation either ties to the cent or has a named cause and a named fix, and what remains is engineering plus a short list of answers that only people at MAIIC can give.**

## 2. The data the engine now runs on

### 2.1 What replaced Extracts A, B and C

Version 3 built the engine around a contract master (Extract A), a transaction file (Extract B) and an interest file (Extract C), each produced by a MAIIC script with its own faults: a fixed/floating label that was the wrong way round, amounts with their signs stripped, and a 2025 that was counted three times. None of the three is needed any more. The engine reads the tables behind them.

| Need | Version 3 source | Now | What is proven about it |
|---|---|---|---|
| Who the borrower is, the facility, its terms | Extract A | `P1_02` account master (184 accounts) and `P1_03` loan master, every column, with the Interest Policy and the Floating Flag read directly | 184 accounts in the 18 loan schemes: 89 active (A), 46 closed (C), 25 dormant (D), 21 code H, 3 frozen (F). 109 were taken on at migration, 75 opened since. |
| Every posting on every account | Extract B | `P1_01` the ledger (CUMVOUCH), signed amounts, every transaction type | Ties to the month-end balance history on 2,533 of 2,549 account-months; to the stored loan book's carrying amount on 2,264 of 2,264; to the trial balance on 1050101, 1050102 and 1050401 in every month. The 16 open balance-history rows are one account (section 3.5). |
| Interest charged each month | Extract C | The ledger's own interest postings (type 303, plus the year-end type 120) | Equal to Extract C run 41 on all 1,564 account-months in its window and in total, MWK 5,293,988,207.06, January 2025 to July 2026. Extract C is retired. |
| The rate on each account over time | Not available | `P1_04` the rate set-up per account (1,291 dated rows, 183 accounts) and the monthly loan book's rate column | The charged rate is the loan book's rate, not the set-up table's (section 3.2). The set-up table dates the changes. |
| The prime lending rate | A hand-typed file | `P2_06` the PLR master, 49 rows, March 2020 to October 2025, with the Reserve Bank effective dates | Replaces the repaired file of 24 September. |
| The monthly loan book | Excel reports, Dec 2025 to Aug 2026 only | `P2_08` the stored loan book, every month-end from December 2024 to August 2026 | 21 month-ends; 2,361 unique account-months after keeping the latest run of each month (section 6). Closes open choice O8. |
| Month-end balances | Not available | `P2_09` the balance history | Independent of the ledger and agrees with it, which is how the ledger was proven. |
| Fees | Not available (the largest gap in version 3) | `P2_07` the disbursement charges table: 3,651 rows, 52 in scope, with the income GL of each fee | Covers loans disbursed through E-Banker. Fees on the 109 take-on loans are not in any table and come from Tamanda's template (agreed O14). |
| Drawdowns | Not available | `P3_12` the disbursement schedules | 58 of 314 facility rows have between 2 and 10 tranches. |
| The repayment pattern | The instalment chart | `P2_10` the current chart and `P3_11` the instalment plans | The chart exists for 75 of 184 accounts, and actual repayments are within 5 percent of it on 23 of 69 testable accounts. The engine generates the expected pattern from the loan's terms (section 7.5) and keeps the chart as a reference. |
| Status changes | Not available | `P3_13` every status change with its reason | Explains codes D and F. Code H (21 accounts) is paid-up awaiting closure on the evidence; the vendor is asked to confirm. |
| Daily accrual | Not available | `P3_14` the daily interest accrual, 7,489 rows from 1 November 2024 | Held for 14 accounts only. For those it proves the month-end posting is the sum of the daily rows, to the cent. |
| GL control | The trial balance | The trial balance, every month, plus `GL_02` the keyed GL opening balances | Interest income on a year-to-date basis ties on 83 of 83 comparable account-months. |

### 2.2 Two tables that do not help, and why

`INTEREST_SUMMARY` (`P1_05`) looked like the calculation behind every interest posting. It is a working table that the month-end run rebuilds; it holds the latest month only (82 rows, all August 2026), confirmed by a second run on 7 October. The history of how each posting was worked out comes instead from the posting's own narration, which states the period and the rate (section 3.1), and from the daily accrual where it exists.

The instalment chart (`P2_10`) is current, not historical, and marks a missed instalment as paid. It is a reference, not a cash-flow source.

### 2.3 The take-on loans

The 109 loans that existed before E-Banker went live were taken on at 31 July 2024, 77 of them with a single posting on that day totalling MWK 8,297,388,309.25. Their history before that date is not in E-Banker. Tamanda's amortisation schedules at 31 October 2024 (100 blocks) have been mapped to the E-Banker accounts: 105 of 109 facilities matched, 104 of them with high confidence, the principal equal to the take-on posting on 77 and the value date equal to the first disbursement-schedule date on 82. The four not matched are Favoured Farms 2022 and three equity-type positions that the engine must refuse in any case (MMC, Wi Jays, Good Hope). The workbook is with Tamanda to tick; its Upload summary sheet is the take-on loader's input (section 7.6).

## 3. What is proven about E-Banker's arithmetic

Everything in this section was tested on the ledger. The engine's contractual-interest service reproduces it, and the reconciliation names a cause for any month that does not agree.

### 3.1 Interest is charged for the whole calendar month at one rate

Every month-end interest posting carries a narration of the form "To Trf IntDR Fm 01-JUN-25 To 30-JUN-25 @ 32.65%". Reading all 2,047 of them:

- The period runs from the 1st to the month-end in every ordinary month (299 of the 303 months that had a rate change inside them; the other four are drawdown months). The period is never split at the date the rate changed.
- Where the daily accrual exists, its daily amount is constant through the month and the month-end posting equals the sum of the daily rows to the cent.
- The amount is the previous month-end balance, including interest already capitalised, times the rate, times the days in the month, over 365. Rebuilt this way, 1,595 of 2,047 postings agree within 0.1 percent and 1,828 within 1 percent; the postings that agree within 1 percent are 91.6 percent of the value. The remainder are drawdown months, moratorium months and the 28 year-end adjustments of section 3.4.
- **When the rate changes inside a month, the whole month is charged at the new rate.** In the 229 account-months where the loan-book rate changed from the previous month-end, the narration carries the new rate in 217, the old rate in 2 and neither in 10. A PLR cut on the 20th reduces the whole month's interest. This decides open choice O2 (section 4.2).

### 3.2 The charged rate is the loan book's rate, not the set-up table's

The rate set-up table (`P1_04`) holds, for each account, a row for every PLR change with a `PLR_RATE`, a variation and an `INTEREST_RATE`. It is not the rate E-Banker charges. Account 000104420000005 is charged 32.45 percent through April 2025 and 32.65 percent from May while the set-up table says 30.35 falling to 30.15; the difference is constant for months at a time. The charged rate is the one on the loan master, which the monthly loan book reports: the narration equals the loan book's month-end rate on 1,453 of 1,600 testable account-months, and in every month where the two disagree the loan book was re-run later with a back-dated change.

On the accounts that behave as the product intends, the charged rate is exactly the PLR plus a fixed spread, applied in the month of the PLR change: account 000104420000010 is 25.2 + 6.0, then 24.7 + 6.0, 22.4 + 6.0, 20.8 + 6.0, 20.6 + 6.0 and 20.4 + 6.0 as the PLR fell through 2026. On others the spread moves (account 000104420000005: 7.05, 7.45, 7.55, 7.45). A moving spread is MAIIC re-pricing at its own option, which is the reset-or-modification question of open choice O17, and the engine reports every such move.

Consequence for the build: the rate-history importer takes the **monthly loan-book rate** as the rate charged, the **PLR master** as the reference rate, and the set-up table as the register of when a change was keyed. The spread is derived as charged rate less PLR, month by month, and an account whose spread drifts is flagged (the `SpreadDerivationService` of P2 already does this).

### 3.3 Signs, transaction types and the six GL codes

Debits are negative in the ledger. The transaction types that matter: 301 drawdown, 303 monthly interest, 305 repayment, 306 reversal of a repayment, 120 the year-end "Diff Int Credit by ROI" adjustment, 300 write-off, 343 reversal of a credit, 900 and 901 a fee posted and reversed, 400 and 401 the Nascomex equity entries. The loan accounts sit on six GL codes: 1050101 and 1050102 (MAIIC Agricultural and Industrial), 1050201 and 1050202 (FInES Agricultural and Industrial, the 10 percent concessional book), 1050401 (term loans) and 1050103.

### 3.4 The 28 year-end adjustments of 31 December 2025

On 31 December 2025 E-Banker posted 28 "Diff Int Credit by ROI" entries (type 120): 22 debits totalling MWK 96,390,096.16 and 6 credits totalling MWK 85,166,683.31. They are the system's own true-up of the year's interest to the rate on the account at year end, and the 6 credits are exactly the difference between Extracts B and C that version 3 could not explain. The engine treats them as interest of December 2025, shows them as their own line in the reconciliation, and the questions for Finance about them are in the note of 7 October (`Note to Barry - the 28 interest adjustments of 31 Dec 2025`).

### 3.5 Two reconciling differences, both explained

**The FInES GLs.** The trial balance for 1050201 is MWK 400,000.00 above the sum of its accounts in every month and 1050202 is MWK 1,000,000.00 below, while the other GLs agree to the cent. Barry's queries of 7 October showed there is no posting without an account (`GL_01` is empty) and that the yearly GL opening balances are keyed by hand (`GL_02`): the 2025 opening for the two FInES GLs was keyed 400,000 above and 1,000,000 below the accounts, and the 2026 opening was taken from the trial balance, carrying the difference forward. Fix: Finance confirms which figure agreed to the 2024 audited accounts and adjusts the two GL openings. Until then the engine carries the 600,000 net as one named line.

**Zaithwa Farms (000104450000015).** The month-end balance table shows the customer MWK 1,827,633.62 better off than the ledger from May 2025. The ledger is complete and right (24 postings, closing at 1,225,683.03 in the customer's favour) and so is the stored loan book. The balance table holds 30 April 2025 twice, and its May row restarted from nil and omitted both May receipts; every later row inherits the error. It is the only duplicate month-end row in the whole table. Fix: the vendor rebuilds the account's rows from May 2025; Finance checks any statement sent to the customer. No change to the engine, which reads the ledger.

## 4. Decisions, and the Governance Centre

### 4.1 Agreed this week

| # | Decision | By | Why |
|---|---|---|---|
| D20 | **The E-Banker record is the contractual record** (O19). The engine reads terms, rates, fees and cash flows from E-Banker. The signed offer letter is evidence; a difference between letter and system is reported to Credit as a control exception, never booked as a modification. | Dupleix, 7 Oct 2026; Dr Thom to confirm in writing | The signed terms were never keyed into E-Banker (F14), while the system now carries fees, margins and cash flows in full. It is the only record the engine can read completely. The twelve offer letters requested from Credit become test evidence, not a data source. |
| D21 | **Every open choice of version 3 is a governed setting** with Dupleix's recommendation seeded as the value in force from 1 January 2025. Dr Thom confirms or changes each one in the Governance Centre when he is ready, with maker-checker and an effective date, and the engine records which value every month was run under. | Dupleix, 7 Oct 2026, at Edward's request | Nothing waits on a meeting. A choice that is changed later applies forward only; a month already run keeps the settings it was run under. |
| D22 | **The month-end loan books are loaded from the stored loan book history**, not re-typed from Excel reports, by a bootstrap importer (section 6). | Dupleix, 7 Oct 2026 | 21 month-ends in one run, each tied to the ledger; the Excel reports carried scrambled dates. |
| D24 | **The Dupleix-suite layout is adopted for the user interface** (section 11): the six working groups with their colours, the icon rail, the page header with breadcrumb and the period chip, as built for FDH on the ZNBS pattern. Routes, permissions and calculations are unchanged; screens move to where the suite puts them. | Edward, 7 Oct 2026 | One shape of screen across every Dupleix system; the EIR screens of P8 are placed in it from the start |
| D23 | **The three MAIIC extract scripts are retired.** The engine's importers read the tables directly, through CSVs produced with the session settings of the 6 October request (ISO dates, point decimal). | Dupleix, 7 Oct 2026 | Removes the three script faults of section 2.1 at source. |

### 4.2 Decided and seeded: every setting, its default, and where it stands

The Governance Centre now holds 28 settings. The first 13 were there from P1 and P4; the 15 that follow were added on 7 October. "Status" says whether the value is agreed, decided on evidence, or Dupleix's recommendation awaiting Dr Thom.

| Setting | Value in force (seeded default) | Status | Item |
|---|---|---|---|
| PLR change inside a month | **Whole month at the month-end rate (E-Banker)** | Decided on evidence, 7 Oct (section 3.1) | O2 |
| When a floating loan's EIR is re-solved | At the PLR effective date | Agreed D8 | |
| How a "Both" moratorium compounds | Monthly at posting | Agreed D10 | |
| Which source decides whether a loan reprices | Interest Policy, then observed, then product family | Recommendation; the Interest Policy is now supplied | O3 |
| Where the spread over prime comes from | LIVE_AT_DRAWDOWN | Recommendation; the evidence of 3.2 supports it | O1 |
| Interest on Stage 3 loans | Net carrying amount | Agreed D11 | |
| Day count | ACT/365 | Agreed D9 | |
| Rate for a quarterly or annual period | Monthly compounded (E-Banker) | Proven 29 Sep | |
| Where actual cash received comes from | Loan book (change in Repayments) | Agreed D14; the ledger now gives the same figure exactly | |
| Loans with Interest Policy = Manual | Read the rate monthly, each change is a reset | Recommendation | O4 |
| The derecognition test | 10 percent | Recommendation | O16 |
| When a difference is an exception | 1 percent of the posted amount, floor MWK 1 | Recommendation, with Deloitte | O11 |
| A Repayments counter that falls | Discontinuity: take cash from Extract B (now the ledger) | Recommendation; the ledger makes it exact | O5 |
| Which record is the contractual one | **E-Banker system record governs** | Decided D20 | O19 |
| Interest basis on a partly drawn facility | Per-account flag, then the scheme, else refuse | Recommendation; the flags are now supplied | O6 |
| Which schedule is the expected cash flow | Core dates and rate; LOS schedule is reference only | Recommendation; follows D20 | O7 |
| Loan books before December 2025 | Stored loan book history, every month-end | Decided D22 | O8 |
| How the monthly loan book arrives | Monthly CSV of the loan book query | Recommendation; a vendor change needs Dr Thom | O9 |
| Which GL absorbs the EIR true-up | Dedicated EIR adjustment income account | Recommendation; Finance opens the account | O10 |
| Shape of the auditor export | Summary-tab shape | Recommendation, with Deloitte | O12 |
| Keyman insurance charged to the borrower | Not integral: pass-through, outside the EIR | Recommendation; a Phase 0 sign-off | O13 |
| Nascomex preference shares | Equity instrument (IAS 32), outside the EIR | Recommendation; a Phase 0 sign-off | O13 |
| Rebutting the 30-day Stage 2 presumption | Allowed with documented evidence, approved | Recommendation; a Phase 0 sign-off | O13 |
| Reset or modification | In-contract moves reset; negotiated changes modify | Recommendation; Deloitte in writing before the first reset is booked | O17 |
| Approving a version 1 schedule | Second person must approve | Recommendation | O18 |
| The historic materiality assessment | Reproduce it beside the detailed figure | Recommendation; Finance supplies the threshold | O20 |
| The year-end fee reclassification journal | Engine journal replaces the manual reclass | Recommendation; follows O10 | O21 |
| Mega Farms facilities | In scope, stage and interest basis confirmed first | Recommendation; the largest swing in the number | O22 |

Seven of these need a genuine choice from Dr Thom rather than a confirmation: the true-up account (O10), the derecognition threshold (O16), reset or modification (O17), the three Phase 0 sign-offs (O13), the fee journal (O21) and Mega Farms (O22). The rest are what the data shows.

Two things are not settings and are tracked elsewhere: the materiality threshold *amount* (O20), which becomes a governed amount once Finance gives the figure; and the fourteen open items that are facts for people to supply (section 5).

## 5. What is still owed by people at MAIIC

Nothing further is needed from the database to start building. These are the answers only people can give, with the document that asks for each.

| Who | What | Asked in | Why it matters |
|---|---|---|---|
| Tamanda | Tick the take-on mapping workbook (4 unmatched, 11 carrying-amount differences above 5 percent) | `Take-on schedules with mapping - for Tamanda to confirm - 7 Oct 2026.xlsx` | Loads the 109 take-on loans |
| Tamanda | The fee template for the take-on loans (O14) and the explanation of the 2024 arrangement fee of MWK 1.34 billion (O23) | Fee template of 25 Sep; the 2024 question in version 3 | Fees are the EIR; the 2024 figure decides how much of 2024 belongs in it |
| Credit | The twelve offer letters: the ten samples and the two over-sanction accounts (Mchinji 50m against 100m drawn; VNC Bricks 20m against 200m) | `Request to Credit - the twelve offer letters - 7 Oct 2026.pdf` | Test evidence under D20; the two sanction checks are a control finding |
| Finance | Which 2024 figure agreed to the audited accounts for 1050201 and 1050202, then the GL opening adjustment | `Outcome - GL differences and Zaithwa Farms explained - 7 Oct 2026.pdf` | The ledger-to-accounts bridge |
| Finance | The questions on the 28 year-end adjustments; the historic materiality threshold | `Note to Barry - the 28 interest adjustments of 31 Dec 2025.pdf`; O20 | December 2025 interest; the historic assessment |
| Barry / vendor | The meaning of status code H; the Zaithwa Farms balance rows rebuilt; the restructured-loan register and the Reschedule Report | The notes of 7 Oct; version 3 phase P7 | Which accounts are live; restructures are version-1 scope |
| Dr Thom | Mega Farms in or out (O22), the written confirmation of D20, and the seven choices of section 4.2 | This document | The headline number |

## 6. Loading the loan books: the bootstrap importer

### 6.1 What the stored history is

E-Banker keeps every run of the Loan Book Report in a table (`LOAN_BOOK_DETAILS_ALL`, extract `P2_08`). It holds 12,414 rows for 21 month-ends from December 2024 to August 2026, because a month is stored every time the report is run: December 2025 was run twelve times. Keeping the latest row for each account and month-end leaves 2,361 account-months, one per account per month, and that is the version already tied to the ledger. Seventy-one account-months differ between runs; in every case the later run reflects a back-dated posting, which is why the latest run is the right one.

The months from the take-on at 31 July 2024 to November 2024 are not stored. They are rebuilt from the balance history (`P2_09`) and the ledger and marked as derived.

### 6.2 The three routes, and the one chosen

| Route | How | Verdict |
|---|---|---|
| A back-end link, Oracle to the engine's database | A scheduled read-only query over the VPN | The permanent answer to O9, but it needs the vendor, ICT approval and credentials. Later. |
| **The bootstrap importer** | An artisan command, `eir:bootstrap-loan-books`, reads the CSVs and upserts `loan_books` on its existing key of account and reporting period | **Chosen (D22).** All 21 months in one run; repeatable; auditable. |
| The existing monthly Excel importer | Parses the printed report by column position | Kept for a month-end if MAIIC prefers to send the report; replaced for history. |

### 6.3 The procedure

1. **Freeze the inputs.** Copy `P2_08`, `P2_09`, `P1_03` and `DD_09` (scheme names) into `storage/app/bootstrap/2026-10-07/`. Record the SHA-256 of each. The files are never opened in Excel.
2. **Pre-checks, and the command refuses if any fails.** Every date parses as month/day/year throughout (the format Barry's tool wrote); after keeping the latest run there is exactly one row per account-month (2,361); the carrying amount per GL per month equals the trial balance, with the two FInES differences of section 3.5 listed as accepted exceptions until Finance corrects them.
3. **Map the columns once**, saved as an import template: account number, customer id, customer name (from the loan master), product group and code (from the scheme behind the GL code), reporting period (the as-on date), value date, maturity date, rate, principal, approved, disbursed, undisbursed (commitments), repayments, carrying amount, the five arrears buckets, overdue days, tenor, industry, month-end flag.
4. **Dry run on a copy of the production database.** December 2025 to August 2026 are already loaded from the Excel reports; the command prints every difference before it overwrites anything. The ECL columns on those rows (stage, LGD, forward-looking adjustments) are not touched.
5. **Load, then prove.** Run one ECL month and one EIR month on the copy and compare with the Excel-loaded results; only then run against production.
6. **Every month from now on.** Barry runs the same loan-book query at month-end with the session settings of the 6 October request and sends one CSV; the same command loads it. That is the integration until O9 is decided.

A side benefit: with 21 months in `loan_books`, the ECL module can be back-run month by month, which it could not before.

## 7. The importers, reworked

Version 3 section 5 described five input files. The five remain, but four of them are now read from E-Banker's own tables. This section says what each importer does and the rules it enforces. Every importer refuses an ambiguous date and names the row (D19), logs what it ignored, and is idempotent.

### 7.1 The ledger importer (replaces Extract B and Extract C)

Reads `P1_01`: every posting on the in-scope accounts with its signed amount, transaction type, value date and narration. Rules: debits negative as received, never ABS'd; a reversal (306, 343, 901) is kept as its own row and paired to the posting it reverses by amount and date; the year-end type 120 is interest of December; a deleted row (nil value, deleted flag) is loaded and marked, not dropped. The ledger's running balance per account is the carrying amount for any date, and the reconciliation reads interest (303 and 120) from it. The old `GlInterestImportService`, which skipped annual rows, is retired.

### 7.2 The rate-history importer (new)

Reads three things and keeps them apart: the PLR master (`P2_06`) as the reference-rate series with its Reserve Bank effective dates; the monthly loan-book rate as the rate charged; and the rate set-up table (`P1_04`) as the register of when each account's rate was keyed and under which Interest Policy. Rules: the rate type is P floating, S and M fixed, read from `INTEREST_POLICY`; the Floating Flag is recorded verbatim and a disagreement is noted, never used to derive the type (the old `RATE_BASIS` mapping, which was inverted, is removed); the spread is charged rate less PLR, derived monthly, and a change in it is an event for the reset-or-modification rule (O17). The PLR master has one row (id 2) whose effective date was blank in the second export but present in the first; the first export is used.

### 7.3 The contract-master importer (reworked)

Reads `P1_02` and `P1_03` and loads the verbatim E-Banker code columns that P2 introduced (Interest Policy, Floating Flag, calculation base, instalment basis, moratorium type, the EMI type, the sanction-versus-balance basis). `MARGIN_PERCENT` is zero on every loan master row and is not used. The sanction amount and date are loaded; an account whose drawn balance exceeds its sanction is flagged for Credit (six today, all take-on balances; two need the offer letter).

### 7.4 The fee importer (two routes)

Loans disbursed through E-Banker: `P2_07`, the charges table, with the fee name, amount, posting date and income GL; the standing fee rulebook (25 rules) classifies each as integral or not. Take-on loans: Tamanda's template. Both land in the same table with their source recorded. The `COLLECTION_TYPES` list in `EirRevenueService` wrongly includes 'Interest' and treats an interest charge as cash collected; it is corrected.

### 7.5 Expected cash flows (generated, not imported)

The chart exists for 75 of 184 accounts and is unreliable where it exists, so the version 1 schedule is generated from the loan's terms on actual dates (P4) and compared with the chart and with the LOS schedule as references, as D20 and O7 provide. Drawdowns come from `P3_12`, with the tranches.

### 7.6 The take-on loader (new)

Reads the Upload summary sheet of the mapping workbook once Tamanda has ticked it: account number, principal, take-on balance at 31 July 2024, rate, term, instalment, first instalment date, fees from the template. Every figure on the sheet is a formula back to Tamanda's original schedule, so the load is auditable to its source.

### 7.7 The trial-balance importer (unchanged) and the GL opening balances

The trial-balance corpus of September stands. `GL_02` is added as a small table of keyed GL opening balances so that the bridge can show the two FInES differences as what they are.

## 8. The build plan from here

Phases P1 to P4 are built. The order from today, with what each one waits for:

| Phase | Content | Waits for |
|---|---|---|
| **P4b Importer rework** (section 7) | Ledger, rate-history, contract-master, fee and take-on importers; the loan-book bootstrap; the four code corrections | Nothing |
| **P5 Floating resets** | Reset detector from the PLR series writing `rate_reset_events`; a spread change as a separate event; maker-checker intake; a reset inside a locked period refused; prospective re-estimation under B5.4.5 | Nothing to build; O17 confirmed by Deloitte before the first reset is booked |
| **P6 Arrears** | Cash receipts from the ledger (exact); re-estimation under B5.4.6; IRR on actual expected flows | P5 |
| **P7 Restructuring** | Version N+1 import; `contract_modifications`; the 10 percent test; lineage | The restructure register from MAIIC |
| **P8 Month-end run and screens** | Pipeline, period lock, Contract Profile, Rate Resets, Restructures, Drawdowns, Month-end Run; help articles; the journal proposal reading the true-up account (O10) | P5 to P7; O10 |
| **P9 Acceptance and UAT** | T1 to T8 on MAIIC's data; the revenue shift per year, 2024, 2025 and 2026 to date, which is the output Dr Thom asked for by name; UAT with Finance | The fee template; Mega Farms decided; the sign-offs |
| **P10 Deployment and training** | Install in MAIIC's environment; training; manuals; source code | P9 |

P4b and P5 start now. Fees and the Mega Farms decision are the two items that gate the number; everything else is engineering.

## 9. Acceptance baselines

The ties achieved this week become regression tests. A build that cannot reproduce them has broken something.

| Test | Expected |
|---|---|
| Ledger running balance against the balance history, every month-end | 2,533 of 2,549 account-months agree; the 16 that do not are Zaithwa Farms from May 2025 |
| Ledger running balance against the stored loan book's carrying amount | 2,264 of 2,264 agree |
| Ledger interest (303 and 120) against Extract C run 41 | All 1,564 account-months; total MWK 5,293,988,207.06 |
| Loan GLs against the trial balance, every month | 1050101, 1050102, 1050401 to the cent; 1050201 +400,000.00; 1050202 -1,000,000.00 |
| Interest income against the trial balance, year-to-date | 83 of 83 comparable account-months |
| Contractual interest rebuilt from the previous month-end balance, rate and days over 365 | 1,595 of 2,047 within 0.1 percent; 1,828 within 1 percent |
| The month of a rate change | Whole month at the new rate on 217 of 229 |
| Take-on postings of 31 July 2024 | 77 postings, MWK 8,297,388,309.25 |
| Year-end adjustments of 31 December 2025 | 28 postings: 22 debits 96,390,096.16; 6 credits 85,166,683.31 |
| Take-on mapping | 105 of 109 facilities; 98 of 100 schedule blocks linked |

## 10. Risks and how they are held

| Risk | Effect | Held by |
|---|---|---|
| An extract is re-saved through Excel on its way to us | Dates scrambled, as in September | ISO-only importers that refuse and name the row; the session settings in every request; the files never opened in Excel at our end |
| The loan book is re-run after we load a month | A back-dated posting changes a carrying amount | The bootstrap keeps the latest run and prints every difference before overwriting; a locked period is never restated |
| The FInES GL openings are not corrected | A 600,000 net line in every reconciliation | Carried as a named line until Finance adjusts |
| The fee template is late | The 2024 and 2025 shift cannot be computed for the take-on book | Post-migration loans proceed on `P2_07`; the take-on figure is marked incomplete rather than estimated |
| The daily accrual exists for 14 accounts only | The daily test cannot be run on the rest | The narration test covers all 2,047 postings |
| A governed default is confirmed late | Nothing stops; the month runs on the seeded value | The Governance Centre records which value each month ran under; a later change applies forward only |

## 11. The user interface: the Dupleix-suite layout

### 11.1 In plain language

Every system Dupleix now delivers (the ZNBS stress-testing suite, the BBS suite, the FDH IFRS 9 platform) uses the same shape of screen, so that a finance officer who has learned one of them can find their way around the next. MAIIC's system was built earlier, and its menu still follows the headings of the contract schedule ("Customer & Loan Data", "IFRS 9 Model Setup", "ECL Processing") rather than the way the work is actually done. Decision D24 adopts the suite layout for MAIIC.

The shape is simple. On the left is a dark navigation panel with six working groups, each with its own colour: **Data Foundation** (what comes in), **Governance Centre** (the rules and settings that govern the calculations), **Financial Modelling** (the engines), **Risk & Regulatory** (the regulatory views), **Monitoring** (the watch-lists and alerts) and the **Report Hub** (what goes out), followed by System Documentation and Administration. Across the top is a bar with the menu toggle, the financial period the system is working in, notifications and the user's menu. Every page opens with a header that says where you are (the group, then the page) and what the page is for. The panel can be folded to a narrow rail of icons when the screen is small or the user wants room for a wide table. Nothing about the calculations changes; the screens move to where a user would look for them.

### 11.2 What the layout is made of

| Element | What it does | Taken from |
|---|---|---|
| Navigation panel (sidebar) | The brand at the top, then the two top-level links (Dashboard, Workspace), then the groups. One group is open at a time; the group that contains the current page opens by itself. Each group has an icon tile in its own colour, and the page in use is marked with a bar in that colour. | FDH `Components/Shell/Sidebar.vue`; MAIIC keeps its own recursive renderer so that a group can hold sub-groups (the modelling group needs them) |
| Icon rail | The hamburger in the top bar folds the panel to 68 pixels of icons; the choice is remembered in the browser. On a phone the same button opens the panel as a drawer over the page. | FDH `AppLayout.vue` |
| Top bar | Menu toggle; a chip showing the financial period the system is working in and whether it is open or closed; the notification bell; the user menu (profile, API tokens, log out). The decorative search box that does nothing today is removed. | FDH `Components/Shell/Topbar.vue`, less the dark-mode switch |
| Page header | An icon tile, the breadcrumb (group, then page) derived from the navigation tree and the current route, the page title, a one-line description, and a slot on the right for the page's actions. A page that already supplies its own header keeps it, inside this frame. | FDH `AppLayout.vue` |
| Processing pill | The floating "Processing" and "Done" indicator while a request is in flight. Already in MAIIC; unchanged. | Both |
| Fail-loud error dialogue | The plain-language explanation of a 403, 419, 404 or server error. Already in MAIIC; unchanged. | MAIIC |
| Per-page help | MAIIC's help centre and the Help button on every page stay as they are. The guide text that names menu groups is re-worded to the new groups. | MAIIC |

Not adopted: the dark-mode switch. MAIIC's pages were not written with a dark variant and would render half-styled; it can follow in a later pass once every page carries the variant.

### 11.3 Where every screen lives

The tree is the single source of truth: the server holds it in `config/menu.php`, filters it by the user's permissions, and sends it to the browser, which renders the panel and derives the breadcrumb from it. Nothing is duplicated in the Vue code. Routes do not change; only the grouping, the group names and the order do. "Today" is the group each screen sits in now.

| Group (colour) | Screen | Route | Permission | Today |
|---|---|---|---|---|
| Top level | Dashboard | `dashboard` | | same |
| Top level | Workspace | `workspace.index` | | same |
| **Data Foundation** (teal) | Clients | `clients.index` | | Customer & Loan Data |
| | Loan Book | `loan_applications.loan-book` | | Customer & Loan Data |
| | Imports | `imports.index` | | Customer & Loan Data |
| | Loan Portfolios | `portfolios.index` | | Portfolio Setup |
| | Product Groups | `groups.index` | | Portfolio Setup |
| | Sector Types | `industry_types.index` | | Portfolio Setup |
| | Collateral Register, Types, Allocation | `collateral.register.index`, `collateral.types.index`, `collateral.allocations.index` | | Collateral Management |
| | EIR Data | `eir-data.index` | eir.view | EIR & Revenue Recognition |
| | Drawdowns | `eir-drawdowns.index` | eir.view | EIR & Revenue Recognition |
| | Reference Rates | `eir-reference-rates.index` | eir.view | EIR & Revenue Recognition |
| **Governance Centre** (amber) | Governance Centre (the 28 settings of section 4.2) | `eir-governance.index` | eir.govern | EIR & Revenue Recognition |
| | Accounting Rules | `eir-accounting-rules.index` | settings | EIR & Revenue Recognition |
| | Fee Classification | `eir-fee-classification.index` | settings | EIR & Revenue Recognition |
| | Staging & SICR Rules (sub-group): Quantitative Thresholds, SICR Groups Setup, SICR Alert Items | `stageing-rules.index`, `sicr-groups.index`, `sicr-items.index` | | IFRS 9 Model Setup |
| | Financial Periods | `accounting.financial_periods.index` | | Administration |
| | Audit Trail | `audit-trail.index` | | Administration |
| **Financial Modelling** (sky) | PD Model (sub-group): Transition Profiles, Monthly Probability, Cumulative Probability, Internal Grades | `transition-profiles.index`, `transition-matrices.index`, `transition-matrix-cummulative.index`, `internal-grading.profiles` | | IFRS 9 Model Setup |
| | LGD Model (sub-group): Monthly LGD, Cumulative LGD | `loss-given-default.index`, `lgd-cummulative.index` | | IFRS 9 Model Setup |
| | Forward-Looking Model (sub-group): Macro Elements, Scenario Profiles, Weighted Forecast, Credit Loss Data, Adjusted Forecast, Regression Analysis | `macro-statistics.index`, `scenarios.profiles`, `macro-forecast-weighted.index`, `credit-loss-data.index`, `forecasting.manual`, `regression.index` | | IFRS 9 Model Setup |
| | Management Overlays (sub-group): Economic Scenarios, External Calculations, Calculation History | `fli.scenarios.index`, `fli.external.index`, `fli.external.list` | | IFRS 9 Model Setup |
| | ECL Calculation | `expected-credit-loss.index` | | ECL Processing |
| | EIR Calculations | `eir-calculations.index` | settings | EIR & Revenue Recognition |
| | Coverage & Blockers | `eir-coverage.index` | eir.view | EIR & Revenue Recognition |
| **Risk & Regulatory** (indigo) | Stress Testing | `stress-testing.index` | | Reports |
| | Sensitivity | `ifrs9-reports.sensitivity` | | a tile in the hub |
| | IFRS 9 Disclosure | `ifrs9-reports.fs-disclosure` | | a tile in the hub |
| | RBM Classification | `ifrs9-reports.rbm-classification` | | a tile in the hub |
| | IFRS 9 vs RBM | `ifrs9-reports.ifrs9-vs-rbm` | | a tile in the hub |
| | Concentration | `ifrs9-reports.concentration` | | a tile in the hub |
| **Monitoring** (emerald) | SICR Trigger Alerts | `sicr-triggers.index` | | IFRS 9 Model Setup |
| | SICR Trigger Report | `ifrs9-reports.sicr-trigger` | | a tile in the hub |
| | Early Warning System | `ifrs9-reports.ews` | | a tile in the hub |
| | Data Quality | `ifrs9-reports.data-quality` | | a tile in the hub |
| **Report Hub** (violet) | IFRS 9 Reports (the full catalogue of 30) | `ifrs9-reports.index` | | Reports |
| | Executive Summary | `ifrs9-reports.executive` | | a tile in the hub |
| | AI Commentary | `ifrs9-reports.ai-narrative` | | a tile in the hub |
| | ECL Reconciliation | `reports.ecl-reconciliation` | | Reports |
| | GL Reconciliation (EIR) | `eir-reconciliation.index` | eir.view | EIR & Revenue Recognition |
| | Loan Book Reconciliation | `reports.loan-book-reconciliation` | | Reports |
| | Disbursements (Vintage) | `reports.disbursement-report` | | Reports |
| System Documentation (slate) | User Manual, Administrator Manual, Technical Manual, Installation Guide | `help.index`, `help.admin`, `docs.technical`, `docs.installation` | | same |
| Administration (rose) | User Management, Roles & Permissions, Support Tickets, Settings | `users.index`, `users.roles.index`, `tickets.index`, `settings.index` | | same, less the two that moved to Governance |

Three rules behind the placement. A screen that **captures or shows what came in** is Data Foundation, whichever module uses it, so the EIR's data, drawdowns and reference rates sit beside the loan book and the collateral. A screen that **sets a rule the engines obey** is Governance Centre: the EIR settings, the accounting and fee rules, the staging thresholds, the periods and the audit trail that proves who changed what. A screen that **produces a figure** is Financial Modelling; one that **re-presents figures for a regulator or a reader** is Risk & Regulatory or the Report Hub. The hub's catalogue of 30 reports is unchanged; the eleven listed above are also reachable from the panel because they are the ones used weekly.

### 11.4 Behaviour, stated precisely

- **Permissions.** The server drops any entry whose permission the user lacks, and any group left empty, before the tree is sent (`HandleInertiaRequests::visibleMenu`, unchanged). A user never sees a link that would answer 403.
- **Active state.** An entry is active when the current route name equals its route (or its `route_check`). The group containing the active entry is open on page load and its header carries the group colour; one group is open at a time; the user may open another, which closes the first.
- **Breadcrumb.** Derived on the client from the tree and `route_name`: top-level entries give one crumb; a grouped entry gives group, then page; a sub-grouped entry gives group, sub-group, then page. A page outside the tree (an edit form reached from a list, for example) shows the crumb of the nearest list by its `route_check`.
- **Collapse.** Stored in the browser as `maiic.sidebar.collapsed`; on a screen narrower than 768 pixels the toggle opens the drawer instead. In the rail, each group shows its icon tile and, under it, the icons of its leaves with the label as a tooltip; sub-groups are flattened into their parent's icons.
- **Period chip.** The top bar shows the latest financial period and "Open" or "Closed" from its `closed` flag, shared from the server as `currentPeriod`. It is a display; it does not change the period.
- **Page header.** `title` and `description` may be passed as props; otherwise the title is the active entry's name and the description is empty. The `#header` slot, where a page provides one, renders inside the header frame in place of the derived title; the `#actions` slot renders on the right.
- **Colours.** Group colours are Tailwind families (teal, amber, sky, indigo, emerald, violet, slate, rose) written in full in one accent map so that the build includes them. The brand remains MAIIC navy and gold on the dark gradient.

### 11.5 What changes in the code, and what does not

| Change | Where |
|---|---|
| The tree regrouped as in 11.3, with an `accent` key on every group | `config/menu.php` |
| Accent map and group colours in the renderer; icon-rail mode | `resources/js/Jetstream/DropdownMenu.vue`, `SidebarNav.vue`, a new `resources/js/navAccents.js` |
| Collapse state, drawer, page header with breadcrumb, period chip; the dummy search removed; the dead consultation-channel code removed | `resources/js/Layouts/AppLayout.vue` |
| `currentPeriod` shared with every page | `app/Http/Middleware/HandleInertiaRequests.php` |
| Help-centre guides that name a group (fifteen passages) re-worded | `database/seeders/data/help_user_content`, `help_admin_content` |
| User and Administrator manuals: navigation chapter re-written and screenshots retaken | `docs/manuals` |
| A test that every leaf in the tree names a registered route and that every group has an accent | `tests/Feature/NavigationTest.php` (new); `SystemDocsTest` unchanged |

Not changed: any route, controller, page component, permission or calculation. The three icons the groups need and MAIIC does not yet register (shield, bell, balance-scale) are added to the Font Awesome library.

### 11.6 Acceptance

1. Every entry in 11.3 is reachable from the panel by a user with the right permission, and absent for one without it.
2. The breadcrumb on each of the 60 screens reads group, then page, as 11.3 lists them.
3. The rail, the drawer and the open-one-group rule behave as 11.4 states, on a 1366-pixel laptop and a phone.
4. The help centre and the two manuals name no group that no longer exists.
5. `SystemDocsTest` and the new navigation test pass; the EIR suite is unaffected.

### 11.7 Order of work

UI-1 the tree and the accent map (half a day); UI-2 the shell: rail, header, period chip (half a day); UI-3 help text and the manuals' navigation chapter with new screenshots (half a day); UI-4 the walk-through of 11.6 on a copy of the database. It is done before P8, so that the new EIR screens of P8 are placed in the suite layout from the start, and after P4b, which does not touch the interface.

## 12. Glossary of the new terms

- **Narration**: the text E-Banker writes on each posting. On an interest posting it states the period and the rate, which is how section 3.1 was proven.
- **Stored loan book**: the table behind the printed Loan Book Report, one row per account per run.
- **Latest run**: of several stored runs of the same month, the one with the highest row id; the one the engine keeps.
- **Take-on**: the loading of the pre-existing loans into E-Banker on 31 July 2024, with one opening posting each.
- **Diff Int Credit by ROI**: E-Banker's year-end adjustment of a year's interest to the rate on the account at year end (type 120).
- **Seeded default**: the value Dupleix wrote into the Governance Centre as the first approved value, in force until MAIIC changes it.
- **Icon rail**: the navigation panel folded to a narrow column of icons; labels appear as tooltips.
- **Breadcrumb**: the line at the top of a page that reads group, then page, so the reader knows where in the system they are.
- **Control exception**: a difference between the signed offer letter and the system record, reported to Credit for action; not an accounting entry.
