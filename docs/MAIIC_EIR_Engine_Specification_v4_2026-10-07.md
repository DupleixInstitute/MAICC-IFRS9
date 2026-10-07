---
kicker: MAIIC | IFRS 9 EFFECTIVE INTEREST RATE ENGINE
title: Specification, version 4
subtitle: What the follow-up extracts proved, what has been decided, how E-Banker is ingested, the user interface, the audit workbooks, the macro statistics, the forward-looking adjustments and scenarios, and what is left to build
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

Where a file is named, it is one of the extracts committed under `docs/bootstrap/` in the repository (section 6.10), with a copy in `2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC\Follow-Up Scripts Resutls\`. The scripts that produced every figure in this document are in `Build files\` beside them, with a README that says which script makes which number.

Where a section says a design is taken from the Dupleix suite (sections 6, 11, 12, 13, 14 and 15), the code it was taken from is copied verbatim under `docs/reference/` in the repository, indexed by section in its README and pinned to the source commits in its manifest, so that the completeness of this document can be verified against working code and the build can port rather than reinvent. That folder is code only.

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
| D22 | **E-Banker is ingested through a landing zone and the monthly loan book is derived from the ledger** (section 6): raw tables that mirror E-Banker, loaded append-only by whichever of five routes MAIIC uses, with gates that refuse a pack that does not tie, and one re-runnable build by any of three methods, the bootstrap of the stored report, the derivation from the ledger, or the printed report importer MAIIC uses today, chosen at any time. | Dupleix, 7 Oct 2026; the landing zone and the derivation added the same day at Edward's direction, the bootstrap and the report importer kept as options | The ledger is the primary record and ties to every other table; the report starts only in December 2024 and stores every re-run. Every derived figure traces to raw rows an auditor can open. |
| D24 | **The Dupleix-suite layout is adopted for the user interface** (section 11): the six working groups with their colours, the icon rail, the page header with breadcrumb, the period chip, and light and dark mode as a per-user setting, as the Dupleix suite builds them. Routes, permissions and calculations are unchanged; screens move to where the suite puts them. | Edward, 7 Oct 2026 | One shape of screen across every Dupleix system; the EIR screens of P8 are placed in it from the start |
| D25 | **Dupleix's compliance audit workbooks are adopted** (section 12): five workbooks (IFRS 9 EIR, IFRS 9 impairment, IFRS 7 and IAS 1, RBM classification, Contract Schedule 1), each row naming the governance setting that governs it and the test that proves it; a register in the Governance Centre where MAIIC signs; an auditor's pack per period in the Report Hub. | Edward, 7 Oct 2026 | Deloitte walks from paragraph to screen to test; the status counts are the project's status in one line |
| D26 | **One-command bootstrap from committed inputs** (section 6.10): the E-Banker pack, the data-dictionary results, the trial balances, the take-on workbook and the queries are committed under `docs/bootstrap/` with a manifest; `eir:bootstrap` builds a clean install, runs every engine in dependency order and verifies the result against the baselines and MAIIC's golden numbers. | Edward, 7 Oct 2026 | Any server, including UAT and Deloitte's copy, is reproducible from nothing; the bootstrap is also the end-to-end test of the data foundation |
| D27 | **Macro statistics are ingested from the World Bank and the IMF the way the suite does it** (section 13): indicator codes on the series, a fetcher and a parser, preview before commit, a batch of provenance on every observation, a command for the bootstrap and the scheduler, the Reserve Bank series by file, and a governed rule for which source wins. The existing tables and the six forward-looking screens are kept. | Edward, 7 Oct 2026 | Forward-looking information with its source, address and time against every figure, which is what B5.5.49 to B5.5.54 and Deloitte ask for |
| D28 | **The forward-looking adjustment gains a correlation finder, a repaired regression, a manual overlay route and lineage on the loan** (section 14); the arithmetic that applies the adjustment to PDs and feeds the ECL is unchanged. | Edward, 7 Oct 2026 | Which series explains MAIIC's losses is found, tested and approved by two people; judgement has a governed road; every post-FLI PD says where it came from |
| D29 | **The scenario set is a governed object per period and the ECL is weighted across scenarios** (section 15): three or more scenarios with narratives, weights, source vintage, calibration to Malawi's own history, shocks on the base path, two approvals, a lock, back-tests and sensitivity; the weighting moves from the macro path to the loss. | Edward, 7 Oct 2026 | IFRS 9 B5.5.42 asks for the probability-weighted loss over a range of outcomes; IFRS 7.35G asks for the disclosure; the auditors ask for the governance |
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
| Loan books before December 2025 | Stored loan book history, every month-end | Decided D22; superseded by the build-method setting below, which covers every month | O8 |
| How E-Banker data arrives: the feed route (`ebanker_feed_route`) | Route 1, manual pack | Decided D22: all five routes of section 6.5 are built; MAIIC switches the route in force at any time; routes 4 and 5 need Dr Thom and ICT | O9 |
| Pre-migration history of the take-on loans (`takeon_history_basis`) | Recompute from origination where the block and fees exist, else start at the take-on balance | Recommendation (section 6.9); the loan carries the basis it was built on | new |
| Which macro source wins where two overlap (`macro_source_precedence`) | World Bank for actuals, IMF WEO for forecasts, RBM file for rates; manual overrides only with a reason | Recommendation (section 13.5) | new |
| How the forward-looking adjustment is produced (`fli_adjustment_route`) | Regression | Recommendation (section 14.6); the manual overlay and the combined route are the alternatives | new |
| Expected-sign test on a regression pair (`fli_expected_sign_test`) | Required | Recommendation (section 14.4) | new |
| R-squared cut-off for an approvable model (`fli_r2_cutoff`) | 30 percent | Recommendation (section 14.4); MAIIC may tighten | new |
| How scenarios are weighted (`scenario_weighting_method`) | Weight the ECL across scenarios | Recommendation (section 15.5); the macro-path method kept for reconciliation | new |
| Minimum scenarios in a set (`scenario_minimum_count`) | 3 | Recommendation (section 15.6) | new |
| Floor on the base weight and ceiling on any weight (`scenario_weight_bounds`) | Base at least 40 percent; none above 60 percent | Recommendation (section 15.6) | new |
| Calibration note required on a downside (`scenario_calibration_note`) | Required | Recommendation (section 15.6) | new |
| An overlay needs an approved scenario set (`overlay_requires_approved_set`) | Required | Recommendation (section 15.7) | new |
| How the loan book is built (`loan_book_build_method`) | Method B, derived from the ledger | Decided D22: all three methods of section 6.2 are built (bootstrap of the stored run, derivation from the ledger, the printed report importer); MAIIC switches at any time; the method used is recorded on every row | O8 |
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
| Tamanda | The fees of the take-on loans, in the yellow columns of the returned workbook (O14), and the explanation of the 2024 arrangement fee of MWK 1.34 billion (O23) | The workbook with fee columns of 7 Oct; the 2024 question in version 3 | Fees are the EIR; the 2024 figure decides how much of 2024 belongs in it |
| Credit | The twelve offer letters: the ten samples and the two over-sanction accounts (Mchinji 50m against 100m drawn; VNC Bricks 20m against 200m) | `Request to Credit - the twelve offer letters - 7 Oct 2026.pdf` | Test evidence under D20; the two sanction checks are a control finding |
| Finance | Which 2024 figure agreed to the audited accounts for 1050201 and 1050202, then the GL opening adjustment | `Outcome - GL differences and Zaithwa Farms explained - 7 Oct 2026.pdf` | The ledger-to-accounts bridge |
| Finance | The questions on the 28 year-end adjustments; the historic materiality threshold | `Note to Barry - the 28 interest adjustments of 31 Dec 2025.pdf`; O20 | December 2025 interest; the historic assessment |
| Barry / vendor | The meaning of status code H; the Zaithwa Farms balance rows rebuilt; the restructured-loan register and the Reschedule Report | The notes of 7 Oct; version 3 phase P7 | Which accounts are live; restructures are version-1 scope |
| Dr Thom | Mega Farms in or out (O22), the written confirmation of D20, and the seven choices of section 4.2 | This document | The headline number |

## 6. Ingesting E-Banker: the landing zone and the feed

### 6.1 In plain language

Until now the system has been fed by hand: a report printed from E-Banker, saved through Excel, uploaded once a month. Version 3 proposed loading the stored Loan Book Report table instead. The follow-up extracts showed something better is possible: the ledger itself, the primary record of every posting, ties to every other table we hold, so the monthly loan book can be **derived from the ledger** rather than copied from a report. And because the extracts are now ordinary queries with known keys, they can arrive by whichever road MAIIC finds convenient, from a file Barry uploads to a scheduled read over the VPN, without changing anything downstream.

The design has three parts. A **landing zone**: raw tables that mirror E-Banker's, loaded exactly as received and never edited. A **build** of the monthly loan book from the landing zone by one of three methods, all kept and all available at any time: the **bootstrap**, which takes the stored Loan Book Report's latest run for each month as it is; the **derivation**, which builds the row from the ledger; and the **report importer**, the existing upload of the printed Loan Book Report as an Excel file, which MAIIC has used every month until now; one command, re-runnable whenever a rule or a source row changes. And a **feed**: the one door through which every pack of extracts enters, whichever road it came by, with gates that refuse a pack that does not tie.

### 6.2 The three build methods

**Method A, the bootstrap.** The loan book row is the stored Loan Book Report's latest run for that account and month-end (`ebanker_loan_book_runs`, highest row id per account-month), column for column: E-Banker's own carrying amount, principal, interest to date, repayments, arrears, status and rate, mapped once to `loan_books` through a saved template. It is the simplest method, it is what MAIIC's own report says, and it is already tied to the ledger on 2,264 of 2,264 account-months. It covers December 2024 onward, which is where the stored report begins; the months from the take-on at 31 July 2024 to November 2024 are built from the balance history and the ledger and marked as derived.

**Method B, the derivation.** For every account and every month-end from the take-on to the latest month, the row is built from the primary records as follows.

| Loan book field | Source | Why |
|---|---|---|
| Carrying amount, principal, interest to date, cumulative repayments, disbursed | The ledger's running balance and its postings by type | The primary record; proven against the balance history (2,533 of 2,549) and the stored loan book (2,264 of 2,264) |
| Approved amount, value and maturity dates, tenor, product, GL, customer | The loan master and account master | The record of the facility |
| Rate | The rate charged, from the stored loan book's rate column, with the PLR and the rate set-up as the register of changes (section 3.2) | The rate actually posted |
| Arrears buckets, overdue days, status, segment | The stored loan book's latest run for that month-end | E-Banker's own arrears arithmetic depends on its instalment chart, which is unreliable to reproduce, and these are the figures MAIIC reports to RBM |
| Undisbursed commitment | Approved less disbursed, checked against the disbursement schedule | Both sources held |

Under method B every month from July 2024 is built the same way, and beside every derived carrying amount the stored loan book's figure is kept; a difference is a flagged row, never a silent choice.

**Method C, the report importer.** The printed Loan Book Report (Menu ID 3868), uploaded as the Excel file MAIIC produces today, through the importer that already exists (`ProcessLoanImportJob`), unchanged in what it reads. Two things are added so that it lives inside the same design: the parsed rows are landed in `ebanker_loan_book_reports` with the file's hash and load id before `loan_books` is written, so a month loaded this way traces to its file like any other; and the file passes the date gate of 6.4 (every date unambiguous, the row named on failure), because this is the route on which Excel scrambled dates in September. It needs no query and no access to the database, which is its value: a month can be loaded from the report alone when no pack exists, by whoever has the report. It gives the report's figures, like method A, for the months the report was run.

**Choosing between them.** The method in force is a Governance Centre setting (`loan_book_build_method`, section 4.2) with the three methods as its options, changed under maker-checker with an effective date; a month is built by the method in force on its period end, and the method used is recorded on every row. All three read from the landing zone and pass its gates, so switching changes no route and no screen. Dupleix's recommendation is method B, because it covers every month from the take-on on one basis and traces every figure to a posting; method A is the right choice while the derivation is being proven, for a quick reload of a single month, or whenever MAIIC prefers the report's own figures to stand; method C is the right choice for a month with no pack, or if the feed is ever interrupted, since it needs nothing but the report. Whichever is in force, the others remain available on the feed screen for a named month.

### 6.3 The landing zone

Raw tables that mirror E-Banker column for column, under their own names, loaded append-only.

| Table | Mirrors | Key | Loaded |
|---|---|---|---|
| `ebanker_ledger` | CUMVOUCH (`P1_01`) | `CUMVOUCH_DET_ID` | Incrementally by key, plus a re-pull of the last two months to catch back-dated postings and deletion flags |
| `ebanker_balance_history` | ACCOUNT_BALANCE (`P2_09`) | `ACCOUNT_BAL_MST_ID` | Incrementally by key |
| `ebanker_loan_book_runs` | LOAN_BOOK_DETAILS_ALL (`P2_08`) | `LOAN_BOOK_DET_ID_A` | Incrementally by key; every run kept, the latest per account-month used |
| `ebanker_account_master`, `ebanker_loan_master`, `ebanker_rate_setup`, `ebanker_plr_master`, `ebanker_charges`, `ebanker_disbursement_schedule`, `ebanker_status_history` | `P1_02`, `P1_03`, `P1_04`, `P2_06`, `P2_07`, `P3_12`, `P3_13` | Their own ids | Whole each time; they are small |
| `ebanker_trial_balances` | The monthly trial balance files as received from Finance (20 to date), the AFS bridge workbook and the mapping to the audited accounts | File hash and row number | One table per file; every GL line exactly as printed, with its cumulative year-to-date balance. `gl_trial_balance_lines`, the GL side of the reconciliation, is derived from it on build; the year-to-date rule of section 7.7 is applied on read, never stored |
| `ebanker_loan_book_reports` | The printed Loan Book Report as uploaded (method C) | File hash and row number | One table per upload; the parsed rows exactly as read |
| `ebanker_loads` | The packs themselves | Pack hash | One row per pack: period, route, who, when, the manifest, the gate results, the watermark after loading |

Three rules. **Nothing is overwritten**: a source row that arrives again with different content (a back-dated change, a deleted flag set) is stored as a new version with its load date, so a month can be re-derived exactly as it looked at the time. **Every row carries its pack**: the hash of the file it came from and the load id, so every derived figure traces to raw rows an auditor can open. **The zone is read by the build only**: no screen edits it.

### 6.4 The pack: one contract for every route

A pack is a set of files, one per query, plus the month's trial balance from Finance, plus a manifest. The manifest (`manifest.json`) records the period, the run timestamp, the session settings used (ISO dates, point decimal, the RUN 0 settings of the 6 October request) and, for each file, the query id, the query version, the row count and the SHA-256 hash.

- **The queries are versioned in the system**, not in an email. Data Foundation, E-Banker Feed, Queries holds the SQL text of each extract with a version number and a checksum, and hands Barry the file to run. A column added later is a new version; the manifest says which version produced each file.
- **Watermarks.** For each incremental table the system records the last key loaded; the query for the next pack is "where the key is greater than the watermark", plus the two-month re-pull. Masters come whole.
- **Gates, run before anything is derived.** Dates parse as ISO throughout; numerics parse; counts and hashes match the manifest; the query versions are ones the system knows; every ledger account exists in the account master; the balance history ties to the ledger running balance at every month-end in the pack; every in-scope account has a month-end row. A failure refuses the pack with the file and row named; the raw rows are kept in quarantine against the load; nothing downstream moves.

### 6.5 The five routes

The printed report is not a route: it enters by method C with no pack, and is the one way in that needs no query. Every route produces the same pack and enters by the same door; the ingester does not know which road a pack came by, and moving from one route to another changes nothing downstream. All five are built and supported, and **MAIIC chooses the route in force at any time**: it is a Governance Centre setting (`ebanker_feed_route`, section 4.2) with the five routes as its options, changed under maker-checker with an effective date like every other setting. The route in force is the one the scheduler runs; the others stay available, so a manual pack can always be loaded, for a missed month or a correction, even while the scheduled read is live. The order below is the order in which MAIIC can have them, not an order of preference.

| Route | How the pack is produced and delivered | What it needs | Place |
|---|---|---|---|
| **1. Manual pack** | Barry runs the versioned queries in SQL Developer with the RUN 0 settings, zips the CSVs, uploads them on the E-Banker Feed screen; the screen builds the manifest from the files and runs the gates | Nothing new: it is what happened on 7 October, made repeatable | Now. Loads the history (section 6.7) and serves any month until route 2 is in place |
| **2. Scheduled export at MAIIC** | A SQLcl or SQL\*Plus script Dupleix writes, parameterised by period and watermark, run by Windows Task Scheduler on a MAIIC server on the first working day after month-end; it writes the pack to an SFTP folder or a network share; the system's scheduler polls the folder and ingests | A read-only Oracle account for the script; one folder; Barry to install the task | The monthly mode. Weeks, not months |
| **3. Push by API** | The same script posts the pack to an endpoint on the system with an API token over HTTPS; no shared folder | A token, outbound HTTPS from the MAIIC server | The alternative to route 2 where a shared folder is awkward |
| **4. Direct read over the VPN** | The system connects to E-Banker's Oracle database read-only (Laravel's OCI8 driver with the Oracle Instant Client), runs the versioned queries itself on schedule and writes straight into the landing zone; the pack and manifest are generated internally, so the audit trail is identical | Read-only credentials over the VPN already in place (the dictionary query DD_18 confirmed what a read-only account can see); the vendor's consent; Dr Thom's authorisation | The destination, and the answer to O9. When ICT agrees |
| **5. Vendor view or API** | Virtual Galaxy exposes a database view or an endpoint carrying the same columns; route 4 reads it | A paid change request to the vendor | Only if route 4 is refused |

Whichever route is in use, the system never writes to E-Banker; the Oracle role is read-only; credentials live in the environment file, not in code; and the extracts carry customer data, so route 2's folder and route 3's endpoint are restricted to the two systems and logged.

### 6.6 Building the loan book

`eir:build-loan-books {from} {to} {--method=bootstrap|derive|report}` builds `loan_books` for the months asked, from the landing zone, by the method in force unless one is named (6.2). It is idempotent on account and reporting period, re-runnable at any time, and run under maker-checker from the feed screen: one person asks for the build, a second approves it, and the audit log records the method and the pack hashes it read. Rebuilding a month already run, by any method, prints every difference first; a locked period is never restated. The ECL columns on `loan_books` (stage, LGD, forward-looking adjustments) are not touched by any of the three methods.

### 6.7 Loading the history, once

The history is loaded by the bootstrap of section 6.10 from the committed inputs; the steps below are what it does and how it is proven.

1. **The 7 October pack is committed** under `docs/bootstrap/ebanker-pack-2026-10-07/` with its manifest and hashes; it is pack 1 under route 1. The files are never opened in Excel.
2. **Gates.** The pack passes 6.4 with two accepted exceptions recorded against the load: the FInES GL openings of section 3.5 until Finance corrects them, and the Zaithwa Farms balance rows from May 2025 until the vendor rebuilds them.
3. **Build on a copy of the production database** for every month from July 2024 to August 2026, by method A first (the stored report is the quickest proof) and then by method B, and compare the two: the carrying amounts must agree on every account-month except the flagged rows of section 3.5. December 2025 to August 2026 are already loaded from the Excel reports; the build prints every difference before it overwrites.
4. **Prove.** Run one ECL month and one EIR month on the copy and compare with the Excel-loaded results; the Baselines sheet of section 12 must show PASS on every tie of section 9.
5. **Production**, then the same every month by route 1 until route 2 is installed.

A side benefit stands: with twenty-six months in `loan_books`, the ECL module can be back-run month by month.

### 6.8 The feed screen

Data Foundation, E-Banker Feed: the queries (versioned, downloadable as the file Barry runs); the load history (pack, route, period, the gate results or the refusal reason, who loaded, when); the watermarks; the quarantine; and the Build action (method A, B or C, a month or a range; method C takes the report file) with its approval. Every derived loan-book row links back to its raw rows and its pack.

### 6.9 The take-on schedules

**What they are for.** E-Banker's history of the 109 take-on loans begins on 31 July 2024 with one opening posting each. The effective interest rate needs what happened before that: the origination date, the original principal, the contractual rate and instalment pattern, and the fees charged at the start. Tamanda's workbook of amortisation schedules at 31 October 2024 (100 blocks, mapped on 7 October to 105 of the 109 facilities) is the only record of that. The engine uses it for two things: to solve the EIR at origination and roll the amortised cost forward to 31 July 2024, so that the loan enters E-Banker at its true amortised cost rather than its take-on balance; and as the version 1 schedule of those loans (`schedule_source = TAKEON_WORKBOOK`) in place of a generated one.

**The fees.** Neither the workbook nor E-Banker records the fees charged when a take-on loan was granted, and the EIR cannot be solved without them. The mapping workbook returned to Tamanda on 7 October (`Take-on schedules with mapping - with fee columns - for Tamanda to confirm - 7 Oct 2026.xlsx`) carries, on its Upload summary sheet, seven columns for her to complete per facility: the arrangement fee, the legal fees, any other fee that was a condition of the loan, the date charged, whether the fees were deducted from the amount paid out, the offer letter or receipt the figures come from, and a total. A blank means no such fee; a zero means known to be nil. This replaces the separate fee template of 25 September for the take-on loans (O14 is unchanged in substance: Finance supplies the fees; the system reads them).

**Landed, not typed.** The workbook enters by the feed door as a pack of its own kind: its hash, then two raw tables. `takeon_blocks` holds one row per block: title, principal, rate, term, start date, the mapped account, the mapping confidence, Tamanda's tick, the fees, and the sheet and cell each value came from. `takeon_schedule_lines` holds one row per instalment line: due date, instalment, principal, interest, balance, serial, with its cell reference. Every figure traces to the cell in the workbook she signed; the Upload summary's formulas already point there.

**Gates.** Every block is mapped to one account that exists in the master, or is explicitly marked not matched or refused (the three equity positions); no account has two blocks; the block principal equals the take-on posting (exact on 77) or the difference is named; the schedule's balance at 31 July 2024 is compared with E-Banker's take-on balance and the difference flagged as arrears or prepayment at take-on; every date is unambiguous; serial numbers out of due-date order are sorted by date and the anomaly raised (F16); a mapped facility with no fee row is flagged, never assumed fee-free. A refusal names the block and the cell.

**The build** writes `contract_takeon` per account: origination date, original principal, contractual rate, fees, and the pre-migration cash flows. Those flows are contractual, not actual: nobody holds the receipts before E-Banker. How the engine treats them is a governed setting, `takeon_history_basis`:

| Option | Meaning |
|---|---|
| **Recompute from origination where the block and fees exist, else start at the take-on balance** (seeded) | Where a facility has a mapped block and its fees, solve the EIR on the original schedule and fees, assume instalments were paid as scheduled to 31 July 2024 except where the take-on balance says otherwise, and book that difference as arrears at take-on. Where it has not (the four unmatched facilities, the eight closed accounts without a block, any block still without fees), treat the 31 July 2024 carrying amount as the opening amortised cost with no day-one history, and flag the loan |
| Recompute from origination for every take-on loan | As above, and refuse a loan whose block or fees are missing until they are supplied |
| Start every take-on loan at its take-on balance | No pre-migration history for any of them; the EIR is solved from 31 July 2024 on E-Banker's flows |

The seeded option is the reasonable one: it uses the history wherever MAIIC can evidence it and is honest where it cannot, and every loan carries the basis it was built on so an auditor sees which. Changing the setting rebuilds the take-on population only.

**Where.** The workbook is committed under `docs/bootstrap/takeon/` and loaded by the bootstrap (6.10); the returned version with the fees replaces it as a new file. Data Foundation, Take-on Schedules: upload a workbook (the alternative to the committed copy), see each block with its mapping, confidence, tick and fees, the gate results, and the Build with approval. Tamanda may confirm a mapping or enter a fee in the screen instead of in Excel; the screen's entry is the one that counts and is audit-logged. Re-uploading creates a new version; the previous build is kept.

### 6.10 The bootstrap: a clean install that loads itself and proves itself

The Dupleix suite installs a client system with one command that wipes a clean database, seeds it, loads the client's own input files from a folder committed in the repository, runs the engines and checks the result against the golden numbers. MAIIC adopts the same method (decision D26), so that any server, including Deloitte's copy and the UAT copy, is built from nothing to a proven state without anyone sending a file.

**The committed inputs.** `docs/bootstrap/` in the repository holds, exactly as received and never re-saved: the E-Banker pack of 7 October 2026 (the 18 extracts and the 5 afternoon queries, 23 files), the data-dictionary results of 6 October (24 files), the 20 monthly trial balances of January 2025 to August 2026, the December 2025 AFS bridge in both received versions, and the mapping of every GL line to the audited accounts with the year-end balances of 2023 to 2025 and August 2026, the take-on workbook with its fee columns, the SQL that produced the pack, and `manifest.json` with every file's query id, row count and SHA-256 and the accepted exceptions the gates allow. The committed copy is read first; a OneDrive path is only a local fallback for development. A replacement, for example the workbook Finance returns with the fees filled in, is a new file beside the old one and a new manifest entry; nothing in the folder is edited in place. The files are MAIIC's data (borrower names and balances); the repository is private and access to it is governed as access to the production database is.

**The command.**

`php artisan eir:bootstrap --fresh --with-client-inputs --build --run-engines --verify`

| Step | What it does | Idempotent |
|---|---|---|
| 1 | `migrate:fresh` on `--fresh`, refused if user data exists unless `--force-wipe` is also passed | Safety, not a step |
| 2 | Seeds: roles and permissions, the Governance Centre defaults (section 4.2), the help centre, the fee rulebook, the compliance-audit modules (section 12) | Yes: a key that exists is left alone |
| 3 | Lands the committed pack and the trial balances into the landing zone by route 1 (section 6.4): the manifest is checked file by file, the gates run, the accepted exceptions are recorded against the load | Yes: a file whose hash is already loaded is skipped |
| 4 | Lands the take-on workbook (section 6.9) and the fees it carries | Yes |
| 5 | On `--build`: builds the loan books for every month from July 2024 to the last month in the pack by the method in force (section 6.2), builds the take-on population under its basis, generates the version 1 schedules | Yes: a locked period is never restated |
| 6 | On `--run-engines`: runs the engine chain of 6.10.1 end to end for every period from the first full month to the last month in the pack, in the order the modules depend on each other, synchronously (the queue driver is set to `sync` for the run, so no worker is needed and nothing is left waiting) | Yes: a period already run and locked is skipped |
| 7 | On `--verify`: runs the baselines of section 9 and the golden numbers of 6.10.2 against the database, prints the table, PASS or FAIL per row, and exits non-zero on any FAIL | Yes |


#### 6.10.1 The engine chain the bootstrap runs

The suite's bootstrap runs its engines in dependency order and treats the result as the proof of the installation. MAIIC's chain, in the order the modules depend on each other, with what each needs and how the bootstrap supplies it where a person would normally act:

| Order | Engine | Entry point | Needs | How the bootstrap meets it |
|---|---|---|---|---|
| 1 | Macro statistics | `macro:import-worldbank`; the WEO snapshot | Internet, or the committed snapshot | Snapshot under `docs/bootstrap/macro/` when the fetch fails (13.3) |
| 2 | Scenario set | the seeded first set of 15.8 | An approved set | Seeded as *proposed*; the bootstrap approves it under the automated label so the chain can run; it is not a MAIIC approval and the screen says so |
| 3 | Staging and SICR | `StagingClassifier` on each month's loan book | Governed thresholds; the loan book | Thresholds seeded; loan books built in step 5 |
| 4 | PD: transition matrices | `TransitionMatrixService`, cumulative | Twelve or more months of graded loan books | The 26 months from the landing zone |
| 5 | LGD | `CalculateLGDJob` and its chunks | Payments and collateral | Payments from the ledger; collateral from the register; runs synchronously |
| 6 | Forward-looking adjustment | the route in force (14.6) | An approved model, or an overlay | With no approvable model on a clean install the bootstrap runs the **manual overlay route at zero** and marks every post-FLI PD "no adjustment: bootstrap"; the regression route is used once a model has been approved by two people |
| 7 | ECL | `ifrs9:recalculate-ecl --pd=pd_post_fli`, the time-phased service | PD, LGD, EAD, stage, the EIR for discounting | Steps 3 to 6 and 9 |
| 8 | EIR: schedules | `eir:generate-schedules` | Terms, drawdowns, the take-on basis | Steps of 6.9; version 1 approved under the automated label |
| 9 | EIR: solve and revenue | `CalculateEirJob`, `eir:run-revenue {period}` | Approved schedules, fees, rates, the governed conventions | Step 8; fees from the charges table and the take-on workbook |
| 10 | GL reconciliation | `EirGlReconciliationService` | The trial balances | Landed in step 3 of the bootstrap |
| 11 | Stress testing | `StressTestingController` logic as a service | The ECL of step 7 | Run for the seeded scenario set |
| 12 | Reports and the audit workbooks | the 30 reports; `tools/compliance` | Everything above | Generated for the last period; the Baselines sheet reads the live figures |

Two orderings matter and are enforced: the ECL is discounted at the EIR, so step 9 runs before step 7 for each period (the chain runs 8 and 9 first, then 3 to 7, then 10 to 12); and the forward-looking adjustment runs once per scenario under the seeded weighting method of 15.5, so step 6 is a loop over the set of step 2.

Three engines are queued jobs today (LGD, EIR solve, revenue). Under `--run-engines` they run synchronously; on a server they still run through the queue, and the installation guide carries the worker command with a memory ceiling, because the suite found that a worker left on its default limit stops itself part-way through a full chain and later jobs wait in silence.

#### 6.10.2 Golden numbers

`--verify` checks the section 9 baselines and, once the engines have run, these figures. They are MAIIC's own, so a bootstrap on any server either reproduces them or fails:

| Golden number | Expected | Source |
|---|---|---|
| ECL at 31 December 2025, total and by stage | The audited impairment allowance in the 2025 financial statements | The AFS mapping workbook (`TB_AFS_MAP`), the impairment lines; the signed statements |
| ECL at 31 December 2024 | The audited 2024 allowance | The same workbook's December 2024 column |
| Contractual interest 2025, by loan GL | MWK 5,293,988,207.06 for January 2025 to July 2026 (section 9), split by month | The ledger |
| Interest income 2025 against the trial balance | The year-to-date tie, 83 of 83 account-months | Section 9 |
| Loan balances by GL at each year-end | The audited figures: 2024 and 2025 by GL | The AFS mapping workbook |
| The revenue shift 2024 and 2025 | Recorded on the first run that Dr Thom approves, then held as the number later builds must reproduce | The engine, once approved |

The last row is how a golden number is born: the first approved run writes it, and from then on `--verify` fails any build that changes it without a governed reason.

Anything the bootstrap approves (a load, a build, a generated schedule, the seeded scenario set) is stamped with the approver label "System Bootstrap (automated data-readiness, not a MAIIC approval)", so that no one can mistake it for a sign-off by MAIIC; the maker-checker approvals of the Governance Centre and the register are never given by the bootstrap.

**What it is for.** The first installation on MAIIC's server; every UAT and acceptance round, which starts from a bootstrap so that the result is reproducible; Deloitte's copy; and the developers' own daily state, since a bootstrap with `--build --run-engines --verify` is the end-to-end test of the data foundation and of every engine on it. The monthly packs of section 6.5 are loaded by the feed, not by the bootstrap; the bootstrap loads what is committed.

### 6.11 The EIR as at any date

A requirement in its own right: **the system shows the EIR computation for any loan as at any date the user names**, not only at month-ends and not only for the latest run.

- **What "as at a date" means.** The engine takes every posting in the landing zone dated on or before the date, the rate in force on the date, the governance values in force on the date, the schedule version in force on the date, and the take-on basis of 6.9, and produces for that date: the EIR in force and when it was last re-solved, the amortised cost, the gross carrying amount, the interest recognised to the date in the period and the year, the cumulative difference between the EIR interest and the contractual interest posted, the remaining expected cash flows, and the modification history. Each figure is shown with the inputs that produced it.
- **Any date, not only a period end.** A date inside a month uses the actual-day convention of section 3 (prior month-end balance, rate, days over 365) for the part-month. A date in a locked period reproduces the locked figures exactly, because a locked period keeps the settings and the schedule it was locked under; a date after the last loaded pack is refused with the last loaded date named, never estimated.
- **For the whole book.** The same view at portfolio level: the EIR interest, the contractual interest and the difference for the year to the date, by product, by GL and in total, which is the revenue shift Dr Thom asked for, at any date.
- **Where.** The Contract Profile carries a date picker ("View as at") beside the schedule; the Report Hub carries "EIR as at a date" for the book, with the Excel and PDF downloads; both read the same service (`EirAsAtService`), so a figure on the screen and in the download are the same computation.
- **The dates that matter now.** 31 December 2024, 31 December 2025 and 31 December 2026 are the year-ends Deloitte will ask for. Section 9 gains them as baselines once the first two are run.

## 7. The importers, reworked

Version 3 section 5 described five input files. The five remain, but four of them are now read from E-Banker's own tables. This section says what each importer does and the rules it enforces. Every importer refuses an ambiguous date and names the row (D19), logs what it ignored, and is idempotent.

### 7.1 The ledger importer (replaces Extract B and Extract C)

Lands `P1_01` in `ebanker_ledger` (section 6.3) and reads it from there: every posting on the in-scope accounts with its signed amount, transaction type, value date and narration. Rules: debits negative as received, never ABS'd; a reversal (306, 343, 901) is kept as its own row and paired to the posting it reverses by amount and date; the year-end type 120 is interest of December; a deleted row (nil value, deleted flag) is loaded and marked, not dropped. The ledger's running balance per account is the carrying amount for any date, and the reconciliation reads interest (303 and 120) from it. The old `GlInterestImportService`, which skipped annual rows, is retired.

### 7.2 The rate-history importer (new)

Reads three things and keeps them apart: the PLR master (`P2_06`) as the reference-rate series with its Reserve Bank effective dates; the monthly loan-book rate as the rate charged; and the rate set-up table (`P1_04`) as the register of when each account's rate was keyed and under which Interest Policy. Rules: the rate type is P floating, S and M fixed, read from `INTEREST_POLICY`; the Floating Flag is recorded verbatim and a disagreement is noted, never used to derive the type (the old `RATE_BASIS` mapping, which was inverted, is removed); the spread is charged rate less PLR, derived monthly, and a change in it is an event for the reset-or-modification rule (O17). The PLR master has one row (id 2) whose effective date was blank in the second export but present in the first; the first export is used.

### 7.3 The contract-master importer (reworked)

Reads `P1_02` and `P1_03` and loads the verbatim E-Banker code columns that P2 introduced (Interest Policy, Floating Flag, calculation base, instalment basis, moratorium type, the EMI type, the sanction-versus-balance basis). `MARGIN_PERCENT` is zero on every loan master row and is not used. The sanction amount and date are loaded; an account whose drawn balance exceeds its sanction is flagged for Credit (six today, all take-on balances; two need the offer letter).

### 7.4 The fee importer (two routes)

Loans disbursed through E-Banker: `P2_07`, the charges table, with the fee name, amount, posting date and income GL; the standing fee rulebook (25 rules) classifies each as integral or not. Take-on loans: Tamanda's template. Both land in the same table with their source recorded. The `COLLECTION_TYPES` list in `EirRevenueService` wrongly includes 'Interest' and treats an interest charge as cash collected; it is corrected.

### 7.5 Expected cash flows (generated, not imported)

The chart exists for 75 of 184 accounts and is unreliable where it exists, so the version 1 schedule is generated from the loan's terms on actual dates (P4) and compared with the chart and with the LOS schedule as references, as D20 and O7 provide. Drawdowns come from `P3_12`, with the tranches.

### 7.6 The take-on loader (new)

As section 6.9: the workbook landed as `takeon_blocks` and `takeon_schedule_lines`, the gates, the build of `contract_takeon` under the `takeon_history_basis` setting, and the Take-on Schedules screen.

### 7.7 The trial balances: landed with the rest, and the GL opening balances

The trial balance is the check on everything else, so it enters by the same door as everything else. The 20 monthly files, the December 2025 AFS bridge (the 10 September 2026 version is the one loaded; the 19 August version held its amounts as text) and the mapping of every GL line to the audited accounts are committed under `docs/bootstrap/trial-balances/` and landed as `ebanker_trial_balances` (section 6.3); each later month arrives as one more file in the monthly pack, with its hash in the manifest. `gl_trial_balance_lines`, which the reconciliation reads, is derived from the landed files by the build, and `gl_account_scope` still says what each GL code is. The two rules of the September importer are unchanged: a P&L balance is cumulative year-to-date and is turned into a month on read (January taken whole); a balance-sheet balance is never differenced. `GL_02` is landed as the small table of keyed GL opening balances so that the bridge shows the two FInES differences as what they are. The ties the trial balance gives (section 9: the loan GLs to the cent, the FInES differences, interest income year-to-date on 83 of 83) are run by `eir:bootstrap --verify` like every other baseline.

## 8. The build plan from here

Phases P1 to P4 are built. The order from today, with what each one waits for:

| Phase | Content | Waits for |
|---|---|---|
| **P4b Ingestion and importer rework** (sections 6 and 7) | The landing zone, the pack contract and gates, route 1 and the feed screen, the build by all three methods (the report importer landed and gated); the take-on schedules landed, gated and built (6.9); `EirAsAtService` (6.11); `eir:bootstrap` with the committed inputs and `--verify` (6.10); the rate-history, contract-master, fee and take-on importers; the four code corrections; route 2's script once the read-only account exists | Nothing; route 4 waits for Dr Thom and ICT |
| **P5 Floating resets** | Reset detector from the PLR series writing `rate_reset_events`; a spread change as a separate event; maker-checker intake; a reset inside a locked period refused; prospective re-estimation under B5.4.5 | Nothing to build; O17 confirmed by Deloitte before the first reset is booked |
| **P6 Arrears** | Cash receipts from the ledger (exact); re-estimation under B5.4.6; IRR on actual expected flows | P5 |
| **P7 Restructuring** | Version N+1 import; `contract_modifications`; the 10 percent test; lineage | The restructure register from MAIIC |
| **P8 Month-end run and screens** | Pipeline, period lock, Contract Profile with the as-at date picker, Rate Resets, Restructures, Drawdowns, Month-end Run, the EIR-as-at-a-date report; help articles; the journal proposal reading the true-up account (O10) | P5 to P7; O10 |
| **P9 Acceptance and UAT** | T1 to T8 on MAIIC's data; the revenue shift per year, 2024, 2025 and 2026 to date, which is the output Dr Thom asked for by name; UAT with Finance | The fee template; Mega Farms decided; the sign-offs |
| **P10 Deployment and training** | Install in MAIIC's environment; training; manuals; source code | P9 |

P4b and P5 start now. Fees and the Mega Farms decision are the two items that gate the number; everything else is engineering.

## 9. Acceptance baselines

The ties achieved this week become regression tests, run by `eir:bootstrap --verify` (section 6.10) and shown on the Baselines sheet of every audit workbook (section 12). A build that cannot reproduce them has broken something.

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
| Take-on mapping | 105 of 109 facilities; 98 of 100 schedule blocks linked; principal equals the take-on posting on 77 |
| Loan book built by method B against the same month built by method A, every month-end from December 2024 | 2,264 of 2,264 carrying amounts agree; every difference is a flagged row with a named cause |

## 10. Risks and how they are held

| Risk | Effect | Held by |
|---|---|---|
| An extract is re-saved through Excel on its way to us | Dates scrambled, as in September | ISO-only importers that refuse and name the row; the session settings in every request; the files never opened in Excel at our end |
| The loan book is re-run after we load a month | A back-dated posting changes a carrying amount | The bootstrap keeps the latest run and prints every difference before overwriting; a locked period is never restated |
| The FInES GL openings are not corrected | A 600,000 net line in every reconciliation | Carried as a named line until Finance adjusts |
| The fee template is late | The 2024 and 2025 shift cannot be computed for the take-on book | Post-migration loans proceed on `P2_07`; the take-on figure is marked incomplete rather than estimated |
| A pack arrives by a new route before its gates are proven | A month derived from a file that did not tie | The gates are route-independent and run on every pack; a route is accepted only after one pack from it has passed on a copy |
| The daily accrual exists for 14 accounts only | The daily test cannot be run on the rest | The narration test covers all 2,047 postings |
| A governed default is confirmed late | Nothing stops; the month runs on the seeded value | The Governance Centre records which value each month ran under; a later change applies forward only |

## 11. The user interface: the Dupleix-suite layout

### 11.1 In plain language

Every system Dupleix now delivers uses the same shape of screen, the Dupleix suite layout, so that a finance officer who has learned one of them can find their way around the next. MAIIC's system was built earlier, and its menu still follows the headings of the contract schedule ("Customer & Loan Data", "IFRS 9 Model Setup", "ECL Processing") rather than the way the work is actually done. Decision D24 adopts the suite layout for MAIIC.

The shape is simple. On the left is a dark navigation panel with six working groups, each with its own colour: **Data Foundation** (what comes in), **Governance Centre** (the rules and settings that govern the calculations), **Financial Modelling** (the engines), **Risk & Regulatory** (the regulatory views), **Monitoring** (the watch-lists and alerts) and the **Report Hub** (what goes out), followed by System Documentation and Administration. Across the top is a bar with the menu toggle, the financial period the system is working in, an appearance switch (light, dark, or follow the device), notifications and the user's menu. Every page opens with a header that says where you are (the group, then the page) and what the page is for. The panel can be folded to a narrow rail of icons when the screen is small or the user wants room for a wide table. Nothing about the calculations changes; the screens move to where a user would look for them.

### 11.2 What the layout is made of

| Element | What it does | Source |
|---|---|---|
| Navigation panel (sidebar) | The brand at the top, then the two top-level links (Dashboard, Workspace), then the groups. One group is open at a time; the group that contains the current page opens by itself. Each group has an icon tile in its own colour, and the page in use is marked with a bar in that colour. | The suite's sidebar; MAIIC keeps its own recursive renderer so that a group can hold sub-groups (the modelling group needs them) |
| Icon rail | The hamburger in the top bar folds the panel to 68 pixels of icons; the choice is remembered in the browser. On a phone the same button opens the panel as a drawer over the page. | The suite's shell |
| Top bar | Menu toggle; a chip showing the financial period the system is working in and whether it is open or closed; the appearance switch (11.5); the notification bell; the user menu (profile, API tokens, log out). The decorative search box that does nothing today is removed. | The suite's top bar |
| Page header | An icon tile, the breadcrumb (group, then page) derived from the navigation tree and the current route, the page title, a one-line description, and a slot on the right for the page's actions. A page that already supplies its own header keeps it, inside this frame. | The suite's shell |
| Processing pill | The floating "Processing" and "Done" indicator while a request is in flight. Already in MAIIC; unchanged. | Already in MAIIC |
| Fail-loud error dialogue | The plain-language explanation of a 403, 419, 404 or server error. Already in MAIIC; unchanged. | MAIIC |
| Per-page help | MAIIC's help centre and the Help button on every page stay as they are. The guide text that names menu groups is re-worded to the new groups. | MAIIC |

Everything in the suite's shell is adopted, including light and dark mode (11.5). MAIIC's pages were not written with a dark variant, so the dark mode is delivered in two steps: the shell and the shared styles first, then a sweep of the pages (11.9).

### 11.3 Where every screen lives

The tree is the single source of truth: the server holds it in `config/menu.php`, filters it by the user's permissions, and sends it to the browser, which renders the panel and derives the breadcrumb from it. Nothing is duplicated in the Vue code. Routes do not change; only the grouping, the group names and the order do. "Today" is the group each screen sits in now.

| Group (colour) | Screen | Route | Permission | Today |
|---|---|---|---|---|
| Top level | Dashboard | `dashboard` | | same |
| Top level | Workspace | `workspace.index` | | same |
| **Data Foundation** (teal) | Clients | `clients.index` | | Customer & Loan Data |
| | Loan Book | `loan_applications.loan-book` | | Customer & Loan Data |
| | Imports | `imports.index` | | Customer & Loan Data |
| | E-Banker Feed (queries, loads, watermarks, build) | `eir-feed.index` (new, section 6.8) | eir.view; derive needs eir.govern | new |
| | Take-on Schedules (workbook, mapping, fees, build) | `eir-takeon.index` (new, section 6.9) | eir.view; build needs eir.govern | new |
| | Loan Portfolios | `portfolios.index` | | Portfolio Setup |
| | Product Groups | `groups.index` | | Portfolio Setup |
| | Sector Types | `industry_types.index` | | Portfolio Setup |
| | Collateral Register, Types, Allocation | `collateral.register.index`, `collateral.types.index`, `collateral.allocations.index` | | Collateral Management |
| | EIR Data | `eir-data.index` | eir.view | EIR & Revenue Recognition |
| | Drawdowns | `eir-drawdowns.index` | eir.view | EIR & Revenue Recognition |
| | Reference Rates | `eir-reference-rates.index` | eir.view | EIR & Revenue Recognition |
| | Macro Statistics (variables, data, scenario assumptions, import from the World Bank, the IMF and the RBM) | `macro-statistics.index` (rebuilt, section 13) | macro.view; import needs macro.manage | IFRS 9 Model Setup (Macro Elements) |
| **Governance Centre** (amber) | Governance Centre (the settings of section 4.2) | `eir-governance.index` | eir.govern | EIR & Revenue Recognition |
| | Accounting Rules | `eir-accounting-rules.index` | settings | EIR & Revenue Recognition |
| | Fee Classification | `eir-fee-classification.index` | settings | EIR & Revenue Recognition |
| | Staging & SICR Rules (sub-group): Quantitative Thresholds, SICR Groups Setup, SICR Alert Items | `stageing-rules.index`, `sicr-groups.index`, `sicr-items.index` | | IFRS 9 Model Setup |
| | Scenario Sets (propose, approve, lock, versions, back-test, sensitivity; section 15) | `scenario-sets.index` (replaces `scenarios.profiles`) | | IFRS 9 Model Setup |
| | Financial Periods | `accounting.financial_periods.index` | | Administration |
| | Audit Trail | `audit-trail.index` | | Administration |
| **Financial Modelling** (sky) | PD Model (sub-group): Transition Profiles, Monthly Probability, Cumulative Probability, Internal Grades | `transition-profiles.index`, `transition-matrices.index`, `transition-matrix-cummulative.index`, `internal-grading.profiles` | | IFRS 9 Model Setup |
| | LGD Model (sub-group): Monthly LGD, Cumulative LGD | `loss-given-default.index`, `lgd-cummulative.index` | | IFRS 9 Model Setup |
| | Forward-Looking Model (sub-group): Correlation Finder (new, 14.4), Regression Analysis (repaired, 14.5), FLI Adjustments (new, 14.6), Weighted Forecast, Credit Loss Data, Adjusted Forecast | `fli-correlation.index`, `regression.index`, `fli-adjustments.index`, `macro-forecast-weighted.index`, `credit-loss-data.index`, `forecasting.manual` | | IFRS 9 Model Setup (Macro Elements moves to Data Foundation as Macro Statistics; Scenario Profiles becomes Scenario Sets under Governance) |
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
| | EIR as at a date (the book, with downloads) | `eir-as-at.index` (new, section 6.11) | eir.view | new |
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

### 11.5 Light and dark mode

Every user chooses how the system looks, and the choice follows them.

- **Three settings.** Light, Dark, and Follow the device. The switch is in the top bar and cycles through the three; the same choice is on the user's profile page. The default for a new user is Follow the device.
- **How it is applied.** Tailwind runs in class mode (`darkMode: 'class'`): the whole system is dark when the `dark` class is on the `<html>` element and light when it is not. Nothing else decides the theme, so there is one switch to test.
- **No flash of the wrong theme.** A four-line script in `app.blade.php` runs before the page paints: it reads the saved choice from the browser (`maiic.theme`), falls back to the device setting when the choice is Follow the device or absent, and sets the class. The Vue code then mirrors that state in one shared composable (`useTheme`) so the top-bar switch, the profile page and any other consumer stay in step.
- **Where the choice is kept.** In the browser, so that the first paint is right, and on the user's record (`users.theme_preference`, values `light`, `dark`, `system`), so that it follows the user to another machine: on login the server's value is written to the browser; when the user changes it, the browser value is written and the server is told. If the browser's storage is unavailable, the theme still applies for the session.
- **What dark mode must look like.** The sidebar keeps its dark gradient in both modes (it is dark by design). The page background, cards, tables, inputs, buttons, badges, modals, charts and the help panels carry a dark variant: slate backgrounds, light text, the group colours unchanged, MAIIC gold kept for the active marks. Contrast meets WCAG AA in both modes; no screen may show light text on a light ground or dark on dark.
- **How the pages get there without rewriting 281 components.** The shared classes the pages already use (`.card`, `.th`, `.td`, `.primary-btn`, `.secondary-btn`, the form inputs, the badges, the flash messages) are given their dark variant once in `app.css`, so most pages inherit the theme. The pages that style elements directly are then swept one group at a time (11.9), each page checked in both modes before it is signed off. Printed and exported outputs (PDF, Excel) are always rendered in the light palette, whatever the screen shows.

### 11.6 What changes in the code, and what does not

| Change | Where |
|---|---|
| The tree regrouped as in 11.3, with an `accent` key on every group | `config/menu.php` |
| Accent map and group colours in the renderer; icon-rail mode | `resources/js/Jetstream/DropdownMenu.vue`, `SidebarNav.vue`, a new `resources/js/navAccents.js` |
| Collapse state, drawer, page header with breadcrumb, period chip; the dummy search removed; the dead consultation-channel code removed | `resources/js/Layouts/AppLayout.vue` |
| `currentPeriod` and the user's `theme_preference` shared with every page | `app/Http/Middleware/HandleInertiaRequests.php` |
| Tailwind in class mode; the no-flash script; the `useTheme` composable; the top-bar switch; the profile setting and its endpoint; `users.theme_preference` | `tailwind.config.js`, `resources/views/app.blade.php`, `resources/js/composables/useTheme.js`, `AppLayout.vue`, the profile page, one migration and `ProfileController` |
| Dark variants of the shared classes, then of the pages that style directly, group by group | `resources/css/app.css`, then the page components under `resources/js/Pages` |
| Help-centre guides that name a group (fifteen passages) re-worded | `database/seeders/data/help_user_content`, `help_admin_content` |
| User and Administrator manuals: navigation chapter re-written and screenshots retaken | `docs/manuals` |
| A test that every leaf in the tree names a registered route and that every group has an accent | `tests/Feature/NavigationTest.php` (new); `SystemDocsTest` unchanged |

Not changed: any route, controller, page component, permission or calculation. The three icons the groups need and MAIIC does not yet register (shield, bell, balance-scale) are added to the Font Awesome library.

### 11.7 Acceptance

1. Every entry in 11.3 is reachable from the panel by a user with the right permission, and absent for one without it.
2. The breadcrumb on each of the 60 screens reads group, then page, as 11.3 lists them.
3. The rail, the drawer and the open-one-group rule behave as 11.4 states, on a 1366-pixel laptop and a phone.
4. The help centre and the two manuals name no group that no longer exists.
5. `SystemDocsTest` and the new navigation test pass; the EIR suite is unaffected.
6. The appearance switch cycles Light, Dark and Follow the device; the choice survives a reload, a new tab and a login on another browser; there is no flash of the wrong theme on load.
7. Every screen in 11.3 is checked in both modes: no unreadable text, no white panel on a dark page, charts and modals themed; PDF and Excel outputs unchanged.

### 11.8 Order of work

UI-1 the tree and the accent map (half a day); UI-2 the shell: rail, header, period chip, the appearance switch, the no-flash script and the shared dark classes (one day); UI-3 help text and the manuals' navigation chapter with new screenshots in both modes (half a day); UI-4 the walk-through of 11.7 items 1 to 6 on a copy of the database; UI-5 the page sweep for dark mode in the order of 11.9 (two days), with item 7 signed off group by group. UI-1 to UI-4 are done before P8, so that the new EIR screens of P8 are placed in the suite layout, and in both modes, from the start; UI-5 may run beside P5 to P7. All of it follows P4b, which does not touch the interface.

### 11.9 The dark-mode sweep, in order

The pages are swept in the order a user meets them, and each group is signed off in both modes before the next starts: (1) Dashboard, Workspace and the login pages; (2) the Report Hub and the IFRS 9 reports, which are what the CFO and the auditors open; (3) Data Foundation; (4) Governance Centre, including the Governance Centre screen itself; (5) Financial Modelling; (6) Risk & Regulatory and Monitoring; (7) System Documentation and Administration. The EIR screens are written with both variants from the start and are not part of the sweep.

## 12. Compliance audit workbooks

### 12.1 In plain language

An auditor's first question is not "what is the number" but "show me where the standard says so, and show me the system doing it". Dupleix's compliance-audit engine, in production in the suite, answers that with one audit workbook per standard or directive: every section of the directive on its own row, what the system does about it, where to see it, a status, and a place for the reviewer to sign. The same engine builds the Excel, a Markdown twin and a PDF from one data file, so the three can never say different things. Decision D25 adopts it for MAIIC, with two additions the EIR work makes possible: each row names the Governance Centre setting that governs it, and each row names the test that proves it.

The result is five workbooks, a register in the system where MAIIC signs each row, and an auditor's pack per period that bundles them with the figures. Deloitte gets a document they can walk from paragraph to screen to test; Dr Thom gets a count of what is done, partly done, outstanding, not applicable or waiting on evidence, which is the project's status in one line.

### 12.2 The five workbooks

| Workbook | What the rows are | Reviewer |
|---|---|---|
| **IFRS 9: the EIR and amortised cost** | The paragraphs the engine implements: the Appendix A definitions of effective interest rate, amortised cost and gross carrying amount; 5.4.1 to 5.4.4 (interest at the EIR; Stage 3 on the net amount, 5.4.1(b)); B5.4.1 to B5.4.7 (fees that are integral, the floating-rate reset, re-estimation of cash flows); 5.4.3 and B5.4.6 (modification gain or loss); 3.3.2 and B3.3.6 (derecognition and the 10 percent test) | Dr Thom, then Deloitte |
| **IFRS 9: impairment** | 5.5 and B5.5: staging, significant increase in credit risk, 12-month and lifetime losses, forward-looking information, write-off; the ECL module already built | Dr Thom, then Deloitte |
| **IFRS 7 and IAS 1: presentation and disclosure** | IAS 1.82(a), interest revenue calculated using the EIR shown as its own line; IFRS 7.20 and 7.35A to 7.35N, the credit-risk disclosures; each row mapped to the disclosure report that produces it | Deloitte |
| **RBM classification and provisioning** | The Reserve Bank directive behind the "IFRS 9 vs RBM" report, section by section, with the report line that answers each | MAIIC Risk |
| **Contract Schedule 1** | Each deliverable and acceptance item of the implementation agreement, with the screen, document or test that discharges it | Dr Thom, at acceptance (P9) |

### 12.3 What a workbook contains

Four sheets. The first three are the engine's standard shape; the fourth is added for MAIIC.

| Sheet | Content |
|---|---|
| **Contents** | The standard's own order of sections, each hyperlinked to its row on the Audit sheet; the status counts at the top |
| **Audit** | One row per section of the standard, eleven columns (12.4), a status drop-down with colour coding, and conditional formatting that flags a "Done" with no test named |
| **Findings** | What needs a decision or a fix: number, reference, finding, what was found, impact, recommended action, owner, status |
| **Baselines** | The acceptance ties of section 9, one per row: the test, the expected figure, the system's current figure read at generation, and a live PASS or FAIL formula. The MAIIC counterpart of the engine's golden-numbers check |

### 12.4 The eleven columns of the Audit sheet

The engine's nine standard columns, then two MAIIC additions.

| # | Column | What goes in it |
|---|---|---|
| 1 | Reference | The paragraph or section number of the standard |
| 2 | Section name | Its heading |
| 3 | What it requires | The requirement in one or two plain sentences |
| 4 | Status | One of: Done; Partially done; Outstanding; Not applicable, documented; Evidence needed from MAIIC |
| 5 | What the engine does | The behaviour, and the evidence checked when the status was set |
| 6 | General comment | Anything a reader needs that the other columns do not carry |
| 7 | Compliance comment | How the behaviour satisfies the requirement, or why it does not yet |
| 8 | Where to see it | The screen (as a route the register turns into a link) and the report or export |
| 9 | Reviewer sign-off | Initials and date; in the register this is captured under maker-checker, not typed |
| 10 | **Governance setting** | The key of the setting in section 4.2 that governs the behaviour (for example `rate_change_classification` on B5.4.5), so a reviewer sees which choice each paragraph turned on |
| 11 | **Test that proves it** | The PHPUnit test, by class and method, that fails if the behaviour changes; a row may be Done only if this column is filled |

The five statuses are the engine's standard ones: Done is implemented and visible in an approved output; Partially done is mechanics in place with a parameter, input or piece of evidence still differing from the requirement; Outstanding is required and not built; Not applicable carries its reason and reference; Evidence needed means the system is ready and MAIIC must supply a document, minute or dataset.

### 12.5 Where it lives in the system

| Place | What is there |
|---|---|
| **Governance Centre, Compliance Audits** | The register. One card per workbook with its status counts and the three downloads (Excel, PDF, Markdown). Opening a card shows the rows; a reviewer with the govern permission sets a row's status and signs it, a second person approves, and the audit log records both. Column 8 renders as a link to the screen. The workbook is generated from the register, so the signed state is the database, never a file someone edited |
| **Report Hub, Auditor Pack** | Per period: the Deloitte export (O12), the five workbooks as at that period, and the Baselines sheet, bundled in one archive with a checksum per file and a manifest, the way the suite archives a regulatory return. This is what is handed to Deloitte |
| **Governance Centre, Audit Trail, Audit Trace** | The suite's per-record trace: open any contract and see, oldest to newest, every change to its schedule, every reset and modification, and the governance values each of its months was run under. The workbook states the rule; the trace shows the rule applied to one loan |

### 12.6 How it is built

- **The engine is ported, not rewritten.** The suite's `tools/compliance/` (`audit_workbook.py`, `build_audit.py`, `md_to_pdf.py`) becomes `tools/compliance/` in MAICC-IFRS9, with the two columns and the Baselines sheet added. Each standard is a data module in `tools/compliance/standards/` exposing `META`, `ROWS`, `FINDINGS` and `BASELINES`; the builder validates them (eleven fields per row, a known status, no duplicate reference, a reason on every Not applicable, a test on every Done) and refuses to write a misleading workbook.
- **One source, three outputs, one register.** The data modules are the source. The builder writes the Excel, the Markdown and the PDF to `docs/compliance/`; a seeder loads the same rows into `compliance_audit_rows` for the register; the register's generation step reads the signed state back and rebuilds the three files. Nothing is typed twice.
- **The Baselines sheet reads the system.** At generation the builder runs the section 9 checks against the database and writes the current figure beside the expected one; the PASS or FAIL formula lives in the sheet so Deloitte can see it recompute.
- **Tables and code.** `compliance_audits` (one per workbook: key, title, standard, reviewer), `compliance_audit_rows` (the eleven columns, status, signed_by, signed_at, approved_by, approved_at), `compliance_findings`; `ComplianceAuditService`, `ComplianceAuditController`, pages under `Pages/Governance/Compliance`; the trace page `Pages/Audit/Trace.vue` ported with the EIR subjects (contract, schedule, reset, modification, setting); the pack builder `eir:build-auditor-pack {period}`.
- **The IFRS 9 EIR module is drafted first**, from this specification: every paragraph it cites already maps to a service and a test, so its rows can be written now and signed as the phases land. The impairment, disclosure and RBM modules follow from the existing reports; Schedule 1 from the contract.

### 12.7 Acceptance

1. Each of the five workbooks builds from its module in the three formats, and the Markdown and Excel carry identical rows.
2. The builder refuses a Done row with no test, a Not applicable with no reason, and a duplicate reference.
3. In the register, a status change and a sign-off need two people, and both appear in the audit log with the old and new values.
4. Every column 8 link opens the screen it names for a user with the permission; every column 11 test exists and passes.
5. The Baselines sheet shows PASS on every section 9 tie against the production copy.
6. The auditor's pack for a period contains the export, the five workbooks and the manifest, and every checksum in the manifest verifies.

### 12.8 Order of work

CA-1 port the engine and add the two columns and the Baselines sheet (one day); CA-2 the IFRS 9 EIR module, drafted from this document (one day, then kept current as P5 to P7 land); CA-3 the register, the trace and the pack (two days); CA-4 the four remaining modules (two days, Schedule 1 last, at P9). CA-1 and CA-2 start with P4b; CA-3 sits with P8, since the register and the pack are screens of the suite layout; CA-4 is finished before UAT.

## 13. Macro statistics: ingestion from the World Bank and the IMF

### 13.1 In plain language

The forward-looking part of IFRS 9 (B5.5.49 to B5.5.54) asks MAIIC to adjust its expected credit losses for what is reasonably expected to happen to the economy: growth, inflation, the exchange rate, interest rates, the harvest. Today those figures are typed into the system by hand from whatever source the analyst had open, with no record of where a number came from or when. The Dupleix suite does this differently: the system fetches the published series itself from the World Bank's open data service and the IMF's World Economic Outlook, shows the analyst what it found, and writes it only when the analyst says so, with the source, the address and the time kept against every figure. Decision D27 adopts that for MAIIC.

### 13.2 What MAIIC has today

The module exists and its tables are sound. `macro_statistics` holds the definition of each series (code, name, unit, frequency, how many historical and forecast periods, source, website link, active flag); `macro_statistics_data` holds one value per series, period and scenario, with an actual-or-forecast flag, an FLI flag, confidence bounds, assumptions and a free-text source, unique on series, period and scenario. Six screens use them under IFRS 9 Model Setup: Macro Elements (manual entry), Scenario Profiles, Weighted Forecast, Credit Loss Data, Adjusted Forecast and Regression Analysis, and the Management Overlays group holds the economic scenarios and external calculations.

What is missing is everything before the first screen: no external source, no preview before a write, no record of where a value came from, no command a bootstrap or a scheduler could run, and no rule for what happens when two sources disagree. The scenario-on-the-row design is in one respect ahead of the suite's and is kept.

### 13.3 What the suite does, and is adopted

| Element | What it does | MAIIC adoption |
|---|---|---|
| **Indicator codes on the series** | Each macro variable carries the external codes that identify it at each source (`external_codes`, for example `{"world_bank": "NY.GDP.MKTP.KD.ZG"}`), seeded once. A series with no code says so on the screen; it is not an error | Two columns added to `macro_statistics`: `external_codes` (JSON) and `country` (ISO3, default `MWI`); a seeder with the Malawi codes of 13.4 |
| **World Bank fetcher** | Pulls an annual series for a country from `api.worldbank.org/v2/country/{ISO3}/indicator/{code}`: public, no key, 60-second timeout, two retries, year range optional, country from configuration with a screen override. Returns a normalised preview; writes nothing | `WorldBankFetcherService` ported as it is; `services.worldbank.country = MWI` |
| **IMF WEO parser** | Reads the World Economic Outlook download (a tab-delimited UTF-16 text file despite its `.xls` name), filters to the country and indicator, and marks each year actual or forecast from the "Estimates Start After" column. This is where the forecast periods come from | `ImfWeoParserService` ported; the file is uploaded by hand from the IMF site, which has no key-free API |
| **Preview, then commit** | Two endpoints per source: a preview the viewer may call, and a commit that writes, for the manager only. Nothing reaches the table until a person has seen the rows | Routes `macro-statistics.preview-worldbank`, `.import-worldbank`, `.preview-imf-weo`, `.import-imf-weo`; permissions `macro.view` and `macro.manage` added to the role set |
| **Provenance per import** | Every commit is a batch: source, address, fetched-at, who, how many rows, the file's hash where there is one; every observation points to its batch | `macro_source_import_batches`; `source_import_batch_id` on `macro_statistics_data`; the free-text `source` kept for manual entries |
| **Upsert, never duplicate** | Observations are keyed on series, period and period type, so a refresh updates the value and keeps the key | MAIIC's unique key on series, period and scenario already does this; an API import writes to the base scenario |
| **A command** | `macro:import-worldbank {--code=}` imports every series with a code, non-fatal per series, for the bootstrap and the scheduler | The same command; run by `eir:bootstrap` and monthly by the scheduler; a committed JSON snapshot under `docs/bootstrap/macro/` is the offline fallback so a server without internet still bootstraps |
| **The screen** | One page with five tabs: Dashboard, Variables, Data Entry, governed assumptions, Import / Export (World Bank with country and year range, IMF upload, CSV template and export) | Data Foundation, Macro Statistics (section 11.3), five tabs; the governed-assumptions tab is **Scenario Assumptions**, the base, mild and severe paths that feed Scenario Profiles and the Weighted Forecast, with the same approval lineage |

Two things the suite does not have are added for MAIIC. **Reserve Bank of Malawi series by file**: the policy rate and the prime lending rate are not on the World Bank; the PLR is already in the landing zone (`P2_06`) and the policy rate arrives as a small CSV through the same preview-and-commit screen, one more source tab rather than a special case. **A rule for disagreement**: a Governance Centre setting, `macro_source_precedence`, says which source wins where two overlap.

### 13.4 The Malawi series, seeded

| Code | Series | World Bank indicator | Why MAIIC needs it |
|---|---|---|---|
| GDP_GROWTH | Real GDP growth, annual % | `NY.GDP.MKTP.KD.ZG` | The headline driver of default rates |
| CPI | Inflation, consumer prices, annual % | `FP.CPI.TOTL.ZG` | Real income of borrowers; the policy-rate path |
| MWK_USD | Official exchange rate, MWK per USD | `PA.NUS.FCRF` | Import-dependent borrowers; the industrial book |
| LENDING_RATE | Lending interest rate, % | `FR.INR.LEND` | Debt service burden; cross-check to the PLR series |
| REAL_RATE | Real interest rate, % | `FR.INR.RINR` | Affordability after inflation |
| PRIVATE_CREDIT | Domestic credit to private sector, % of GDP | `FS.AST.PRVT.GD.ZS` | Credit conditions |
| AGRI_GROWTH | Agriculture, forestry and fishing value added, annual growth % | `NV.AGR.TOTL.KD.ZG` | MAIIC's book is agricultural and agro-industrial; the harvest is its own driver |
| BROAD_MONEY | Broad money growth, annual % | `FM.LBL.BMNY.ZG` | Liquidity conditions |
| RESERVES | Total reserves in months of imports | `FI.RES.TOTL.MO` | Exchange-rate and import-cover stress |
| DEBT_GDP | Central government debt, % of GDP | `GC.DOD.TOTL.GD.ZS` | Sovereign stress and crowding out |
| CURRENT_ACCOUNT | Current account balance, % of GDP | `BN.CAB.XOKA.GD.ZS` | External balance |
| POLICY_RATE | RBM policy rate, % | by file (RBM) | The rate the PLR follows |
| PLR | Prime lending rate, % | the landing zone, `P2_06` | The floating-rate reference of the EIR engine, so the ECL and EIR modules read one series |

The IMF WEO gives the forecast years for GDP growth, inflation, the current account and government debt; the World Bank gives the actuals. A code that the World Bank later retires is a seeder change, not a code change.

### 13.5 Behaviour, stated precisely

- **Country.** `MWI` by default; the screen may fetch another country for comparison, and such rows carry their country and are never used by the FLI model.
- **Periods.** World Bank and WEO series are annual; MAIIC's FLI model runs on the frequency the series definition states. An annual value is held as the year's observation; the Weighted Forecast interpolates to quarters or months as it does today, and says so on the row.
- **Actual against forecast.** A World Bank value is an actual. A WEO value is an actual up to the estimates-start year and a forecast after it; a later WEO vintage replaces an earlier forecast and the earlier one is kept in the batch history. A forecast is never overwritten by hand without a reason.
- **Precedence** (`macro_source_precedence`, section 4.2): World Bank for actuals, IMF WEO for forecasts, the RBM file for rates; a manual entry overrides any of them only with a reason, and the override is shown on the row and in the audit log.
- **Failure.** A series the source cannot supply is reported on the preview and skipped on the command; the import never fails as a whole because one indicator is missing. A fetch that cannot reach the source after the retries says so and leaves the table as it was.
- **Provenance to the audit workbook.** The IFRS 9 impairment workbook of section 12 cites, for B5.5.49 to B5.5.54, the batch behind each series in use: source, address, fetched-at, who committed it.

### 13.6 What changes in the code

| Change | Where |
|---|---|
| `external_codes`, `country` on `macro_statistics`; `macro_source_import_batches`; `source_import_batch_id` on `macro_statistics_data` | Two migrations |
| `WorldBankFetcherService`, `ImfWeoParserService`, `SourceImportBatch`, the RBM file parser | `app/Services/Macro`, `app/Models` |
| `MacroExternalCodesSeeder` with the series of 13.4 | `database/seeders` |
| Preview and commit endpoints for the three sources; `macro.view` and `macro.manage` | `MacroStatsController`, `routes/web.php`, the permission seeder |
| `macro:import-worldbank`; a step in `eir:bootstrap`; a monthly schedule; the JSON snapshot | `app/Console/Commands`, `routes/console.php`, `docs/bootstrap/macro/` |
| The Macro Statistics screen with five tabs in the suite layout | `resources/js/Pages/FLI/MacroStats` |
| `macro_source_precedence` in the Governance Centre catalogue | `GovernanceService::catalogue()` |
| Tests: the fetcher against a recorded response, the WEO parser against a sample file, preview writes nothing, commit writes a batch, the command skips a missing series | `tests/Feature/Macro` |

Not changed: Scenario Profiles, Weighted Forecast, Credit Loss Data, Adjusted Forecast and Regression Analysis read the same tables as before; the FLI model's arithmetic is untouched.

### 13.7 Acceptance

1. Every series of 13.4 with a World Bank code previews and imports for Malawi, and the rows carry a batch with source, address and time.
2. A WEO file previews with each year marked actual or forecast, and commits with the forecast years flagged.
3. A preview writes nothing; a commit by a viewer is refused; a commit by a manager is audit-logged.
4. `macro:import-worldbank` with the network unavailable reports every series skipped and leaves the tables unchanged; `eir:bootstrap` falls back to the snapshot.
5. The Weighted Forecast and Regression screens show the same results on imported data as on the same values typed by hand.
6. The impairment workbook's forward-looking rows cite the batches in use.

### 13.8 Order of work

MS-1 the migrations, the ported services, the seeder and the command, with tests (one day); MS-2 the screen in the suite layout, with the RBM file tab (one day); MS-3 the bootstrap step, the snapshot, the schedule and the audit-workbook rows (half a day). MS-1 can start with P4b; MS-2 is the first Data Foundation screen rebuilt in the suite shape and sits with UI-2.

## 14. Forward-looking adjustments: the regression, the correlation finder and the manual route

### 14.1 Background: why the economy enters the loss estimate

An expected credit loss is, for each loan, the probability that the borrower defaults (PD) times the share of the exposure that would be lost if they did (LGD) times the exposure at that moment (EAD), discounted at the loan's effective interest rate. Of the three, the PD is the one the economy moves most. MAIIC measures its PDs from its own history through the transition matrices: how often, over the years observed, a loan in a given grade moved to default. That history is the honest starting point, but it is an average over the years that happened to be in the window, good and bad together: a "through-the-cycle" figure. IFRS 9 asks for the probability that applies to the twelve months, or the lifetime, that is now ahead: a "point-in-time" figure that reflects where the economy is and where it is expected to go (5.5.17(c), B5.5.49). The step from the first to the second is the forward-looking adjustment.

The link between the economy and defaults is made with a **regression**: a statistical fit that says how much a credit-loss measure (the share of loans defaulting in a quarter, the NPL ratio, the roll rate from one grade to the next) has moved, historically, for a given move in an economic series (growth, inflation, the exchange rate, the harvest). Three things matter in practice. The relationship is usually **lagged**: a bad harvest shows up in defaults two or three quarters later, not in the same quarter. It is often in **changes** rather than levels: it is the fall in growth, not the level of growth, that moves defaults. And it must make **economic sense**: a fit that says defaults fall as unemployment rises is a statistical accident, however high its R-squared, and must not be used. The regression produces a predicted loss measure for each future period under an assumed path of the economy; the ratio of that prediction to the base period's prediction is the adjustment applied to every loan's PD.

Where the statistics are thin, which they are for an institution of MAIIC's size with two years of core-banking history, the standard allows judgement, provided it is reasonable, supportable and documented (B5.5.52): a management overlay. The sections that follow give both roads and the governance that makes either one auditable.

### 14.2 In plain language

A probability of default measured from MAIIC's own history says how often borrowers like this one have defaulted in the past. IFRS 9 asks for something more: the probability that applies to the economy that is coming, not the one that has been. The "forward-looking adjustment" is the step that turns the historical probability (the pre-FLI PD) into the one the expected credit loss is calculated on (the post-FLI PD). MAIIC's system already does this, and does it in the right order: the adjustment is worked out from a regression of credit losses on the economy, applied to each loan's PD, and the ECL reads the adjusted figure. What is missing is the discipline around it: a way to find which economic series actually explains MAIIC's losses, a test that the relationship makes economic sense before it is used, a way for Finance to apply an adjustment by judgement when the statistics cannot, and a record on each loan of where its adjustment came from. Decision D28 adds those four things and changes none of the arithmetic that already works.

### 14.3 How the adjustment works today

The chain, as the code runs it:

| Step | What happens | Where |
|---|---|---|
| 1 | A **regression** relates a credit-loss proxy (an observed default rate, an NPL ratio) to one or more macro series over a training window; the model stores its coefficients, R-squared and window, and can be marked approved | `RegressionService`, `regression_models` |
| 2 | For a reporting period and a scenario set, a **parameter record** names the macro statistic, the PD proxy, the base period's values and a single slope and intercept | `fli_reporting_periods_parameters` |
| 3 | For each **forecast window** (0, 1, 2 … periods ahead) the scenario-weighted macro value is entered; the predicted proxy is slope × value + intercept; the **adjustment** is the predicted value divided by the base window's predicted value, less one: a relative uplift on the base period | `fli_adj` |
| 4 | **Applied to loans**: Stage 3 is set to 100 percent; Stage 1 takes the 12-month window; Stage 2 takes the window nearest its remaining life; post-FLI PD = pre-FLI PD × (1 + adjustment), floored at 0 and capped at 100 percent | `ExternalCalculationsController::applyToLoans` |
| 5 | The **ECL** reads the post-FLI PD; the ECL recalculation is forbidden from writing it back, because the FLI engine owns it | `TimePhasedEclService`, `RecalculateEcl --pd=pd_post_fli` |

Steps 4 and 5 are sound and tested and are kept exactly as they are. The weaknesses are in steps 1 to 3:

- **One variable is applied even when several were trained.** The parameter record holds one slope and one intercept, so the model that reaches the loans is univariate whatever the approved model was.
- **No test of sense.** A model can be approved whose coefficient has the wrong sign (losses falling as unemployment rises) or whose R-squared is negligible; nothing stops it.
- **No lags, no transforms.** The economy of this quarter is regressed on the losses of this quarter; the link is usually lagged, and often in changes rather than levels.
- **Only one road.** The only way to a post-FLI PD is the regression. There is no screen on which Finance can say "for this quarter, plus fifteen percent on Stage 2 agriculture, because of the drought, approved by two people", which is what a small institution actually needs and what B5.5.52 contemplates.
- **No finder.** The analyst must already know which series to regress on.
- **No lineage on the loan.** The loan row holds the number, not which model, parameter set and scenario produced it.

### 14.4 The correlation finder

The finder answers the first question an analyst has: which economic series, at what lag, in what form, explains MAIIC's losses, and how well. It is the Dupleix suite's "auto-correlate", ported.

- **The sweep.** Every active macro series (section 13) against every credit-loss proxy MAIIC holds (observed default rate by product group, NPL ratio, the roll rates behind the transition matrices), across a governed lag grid (0, 3, 6, 9 and 12 months) and three transforms (level, change, change in logarithm). For each combination it computes a rank correlation first (Spearman, which is robust to the odd year) and a robust slope (Theil-Sen), then Pearson's r and a one-variable least-squares fit.
- **The ranking.** Each pair is scored on strength, sign agreement with what economics expects, overlap length and whether the series can be forecast (a series no source projects cannot drive a forward-looking adjustment, and is ranked down for it). One suggestion per pair at its best lag, with a plain-language reason: "Agriculture value added, lagged two quarters, change: Spearman −0.71 over 28 quarters; sign as expected; forecastable from the IMF".
- **Fail-closed.** A pair with too little overlap is rejected with the reason, never fabricated; a sweep with no proxy data produces no suggestions and says so.
- **Immutable runs.** Every sweep is an analysis run with the hash of the inputs it read, so a suggestion can be reproduced from the data vintage; a later sweep is a new run, not an edit.
- **The tests, governed.** Two thresholds from the Governance Centre decide what may go forward: the **expected sign** per pair (positive, negative or not stated) and an **R-squared cut-off**. A pair that fails either is shown in red with the reason, as the earlier Dupleix calculator did it; it may still be studied, but it cannot be approved as a model.

### 14.5 The regression, repaired

- **The model that is trained is the model that is applied.** The parameter record stores the approved model's id and its full coefficient vector; the predicted proxy uses every variable in it, each at its own lag and transform.
- **Approval needs the tests and two people.** A model may be approved only if it passes the sign and cut-off tests of 14.4, and approval is maker-checker: the person who trained it cannot approve it. An approved model is versioned; a retrained model is a new version and the old one stays attached to the periods that used it.
- **Back-test on the row.** Each period, the model's predicted proxy for the period just ended is compared with the realised proxy and the difference stored, so a model that has stopped working is visible before it is reused.

### 14.6 Three routes to the adjustment, one governed choice

A Governance Centre setting, `fli_adjustment_route`, says how the adjustment is produced for a period. All three routes write the same adjustment rows, so steps 4 and 5 of 14.3 do not change.

| Route | What it is | When it is the right one |
|---|---|---|
| **Regression** (seeded default) | The chain of 14.2 with the repairs of 14.5: the approved model, the scenario paths of section 15, the predicted proxy per window, the relative uplift | When an approved model exists and passes its back-test |
| **Manual overlay** | A screen on which a reviewer enters the adjustment per scenario and window and, optionally, per product group or stage, with a reason and an attachment; a second person approves; the overlay has an owner and an expiry date | When no model is approvable, or when an event the statistics cannot see (a drought, a devaluation, a policy change) has to be reflected now |
| **Regression plus overlay** | The regression result, then a manual adjustment on top; each shown separately on the loan and in the ECL | When the model is sound but judgement says it is not enough |

The manual overlay is a register, not a free field: every entry carries its scope, reason, evidence, owner, expiry, proposer and approver, and the ECL shows the overlay as its own line, which is how the auditor and the Board see what judgement added.

### 14.7 Lineage on the loan

Four columns are added to the loan-book row beside the adjustment and the post-FLI PD: the route used, the parameter record, the model version and the scenario set. The ECL report and the impairment audit workbook can then say, for every loan, which model, which overlay, which scenario and which approval produced its post-FLI PD. The scenario-weighted macro value of step 3, typed today, becomes computed and shown: the row says which scenarios, at which weights, gave the figure, and the three values behind it.

### 14.8 Where it lives, and the settings

Financial Modelling › Forward-Looking Model: **Correlation Finder** (new), **Regression Analysis** (repaired), **FLI Adjustments** (new: the route, the regression result, the overlay register, the apply-to-loans step with its counts), **Weighted Forecast** (now computed from the scenario set). The three settings in the Governance Centre (section 4.2): `fli_adjustment_route` (seeded: Regression), `fli_expected_sign_test` (seeded: Required), `fli_r2_cutoff` (seeded: 30 percent, a common starting threshold for annual macro data; MAIIC may tighten it). The finder's runs, the approved models and the overlays are cited in the impairment audit workbook's rows for B5.5.49 to B5.5.54.

### 14.9 Acceptance and order of work

1. The finder sweeps every series and proxy and ranks suggestions with reasons; a pair with fewer than the governed minimum of overlapping periods is rejected with the reason.
2. A model that fails the sign or cut-off test cannot be approved; approval needs a second person; the applied prediction uses every coefficient of the approved model.
3. Each route produces adjustment rows; the loans' post-FLI PDs equal pre-FLI × (1 + adjustment), floored and capped, Stage 3 at 100 percent, as today.
4. Every loan row names its route, parameter record, model version and scenario set; the ECL shows the overlay as its own line.
5. The existing ECL tests pass unchanged on the regression route.

FL-1 the finder (one day); FL-2 the regression repair with its tests (one day); FL-3 the FLI Adjustments screen, the overlay register and the route setting (one day); FL-4 lineage, the computed weighting, the back-test and the workbook rows (one day). FL-1 needs the macro series of section 13; the rest can follow P4b.

## 15. Economic scenarios: governance and incorporation

### 15.1 Background: what a scenario is, and why one is not enough

A scenario is a coherent story about the economy over the next few years, written down as a path for each macro series: growth, inflation, the exchange rate, interest rates, the harvest. The forward-looking adjustment of section 14 needs such a path to predict from; a single "most likely" path would be the natural choice, and it is the wrong one. Credit losses are not symmetrical: a year that is one notch worse than expected costs far more in defaults than a year one notch better saves, because borrowers who were already stretched tip into default while those who were comfortable merely become more comfortable. Calculating the loss on the single most likely path therefore understates the expected loss. IFRS 9 recognises this and asks for an unbiased, probability-weighted estimate over a range of possible outcomes (5.5.17(a), B5.5.42): the loss under each of several scenarios, each weighted by how likely it is, and the weighted average reported.

Scenarios for the ECL are not the same thing as stress tests. A stress test asks what happens under a severe but plausible shock, with no weight attached, to see whether capital would hold; its scenarios are deliberately harsh. ECL scenarios are a probability-weighted view of what is actually expected, in which the severe case carries a small weight and the base case a large one. The two should share their economics (the same base path, the same understanding of what a devaluation does to the book) but they answer different questions, and a system that uses one set for both will be wrong for one of them.

The weights are the part that attracts the most scrutiny, because a small change in a weight can move the provision materially and nothing in the data pins them down. Good practice therefore does three things: anchors each scenario's severity to something that has actually happened, so that "a downside" is not an abstraction but "2023 again"; sets the weights by a documented judgement that two accountable people approve; and tests afterwards whether the realised year fell inside the range the scenarios spanned, so that weights that were too optimistic are seen and revised. For Malawi the anchors are not hard to find: the 2023 devaluation of 44 percent, the 2016 drought, the 2012 float, and the 2021 to 2022 recovery each give a path that the institution's own loan book has already been through.

### 15.2 In plain language

The forward-looking adjustment depends on what the economy is assumed to do. IFRS 9 does not let that be one guess: it asks for an unbiased, probability-weighted view across a range of possible outcomes (5.5.17(a), B5.5.42). In practice that means a base view, a better one and a worse one, each with a story, a set of figures and a weight, agreed by the people accountable for it and kept once the period's numbers are struck. MAIIC's system holds scenarios today, but in two unconnected places, without a period, a version, a narrative, a source, an approval or a lock, and it uses them at the wrong point: it averages the economy and calculates one loss, where the standard asks for the loss under each economy, then the average. Decision D29 makes the scenario set a governed object and moves the weighting to where it belongs.

### 15.3 What good practice asks for

| Principle | Where it comes from | What it means for MAIIC |
|---|---|---|
| Unbiased and probability-weighted, over a range of outcomes | IFRS 9 5.5.17(a), B5.5.42 | At least a base, an upside and a downside, with weights that sum to 100; the ECL is the weighted average of the ECL under each scenario, not the ECL at the average economy |
| Reasonable and supportable, without undue cost or effort | B5.5.49 to B5.5.54 | Paths from published sources (the IMF World Economic Outlook, the World Bank, the Reserve Bank of Malawi), with the vintage recorded; judgement written down where the sources stop |
| Governed | The Basel Committee's guidance on credit risk and accounting for expected credit losses, principles 2 and 5; the auditors' expectation | Approved by two people, one from Finance and one from Risk; locked with the period's ECL; changed only by a new version with a reason; reviewed at least once a year |
| Severity calibrated to history | Common practice | The downside is anchored to what Malawi has lived through: the 2023 devaluation, the 2016 drought, the 2012 float; the set says which history each scenario is calibrated to |
| One house view | Basel guidance; audit consistency | The same scenario set drives the ECL, the sensitivity disclosures, the stress test and the budget; a different set for each is a finding |
| Disclosed | IFRS 7.35G | The narratives, the weights, the key paths per scenario, the ECL under each scenario and with 100 percent weight on each, and the sensitivity to the weights |
| Overlays governed, not hidden | Basel guidance; B5.5.52 | A management overlay sits on an approved set, with a reason, an owner, an expiry and a second approval, and is shown separately |
| Back-tested | Model-risk practice | Each period the previous set's base path is compared with what happened, and the predicted default rate with the realised; a miss outside the range triggers a review of the weights |

### 15.4 The scenario set, as a governed object

The two existing structures (`scenario_profiles` with its child scenarios, and `scenario_sets` with `scenario_probabilities`) become one, and a migration maps what is there.

**The set**, one per reporting period: period; name; version; status (draft, proposed, approved, locked); the narrative; the source vintage ("IMF WEO April 2026; World Bank 2025 actuals; RBM Monetary Policy Committee, March 2026"); proposed by; approved by (two people); locked at. A set is locked when the period's ECL is locked; a change after that is a new version with a reason, and the locked version stays attached to the period.

**The scenarios** under it, three or more: name; probability weight (the weights must sum to 100); the narrative; the calibration note that says which history the scenario is anchored to; and the **paths**: for each macro series and each horizon, the base scenario's path comes from the sources of section 13, and every other scenario is expressed as a governed **shock** on the base, by series and year offset, of one of four kinds: a percentage change, an absolute change, a replacement value, or a multiplier. A downside is therefore an auditable transformation of the base, not a second set of typed numbers; change the base and the shocked paths follow.

### 15.5 Weighting the loss, not the economy

A Governance Centre setting, `scenario_weighting_method`, with two options:

| Option | Meaning |
|---|---|
| **Weight the ECL across scenarios** (seeded) | The forward-looking chain of section 14 runs once per scenario; each loan carries a PD and an ECL under each scenario; the reported ECL is the probability-weighted sum. This captures non-linearity: a downside hurts more than an upside helps, and the average of the losses is higher than the loss at the average |
| Weight the macro path, one ECL | Today's method: the scenario-weighted macro value, one regression, one ECL. Kept so that past periods can be reproduced and the two methods reconciled when the switch is made |

Switching is a governed change with an effective date; the first period run under the new method shows both figures and the difference, which is itself a disclosure the auditors will want.

### 15.6 The rules, as settings

In the Governance Centre: the minimum number of scenarios (seeded 3); a floor on the base scenario's weight (seeded 40 percent) and a ceiling on any single weight (seeded 60 percent); whether a calibration note is required on every downside (seeded Required); the annual review month; and the rule that an overlay needs an approved set (seeded Required). Each is changed under maker-checker with a reason, like every other setting.

### 15.7 Overlays, back-tests and sensitivity

- **The overlay register** of section 14.5 is tied to the scenario set: an overlay names the set it sits on, and a set cannot be locked with an unapproved overlay against it. Each overlay is shown as its own line in the ECL and in the disclosure.
- **Back-test, every period**: for each macro series, the previous set's base path against the actual now known; for each proxy, the predicted default rate against the realised. The results are stored with the set, and a miss outside the scenario range raises a flag that the weights are to be reviewed before the next set is proposed.
- **Sensitivity, every period**: the ECL under each scenario; the ECL with 100 percent weight on each; the ECL with ten points moved from the base to the downside and to the upside. Stored with the set, so the IFRS 7.35G tables are a report from the system, not a spreadsheet beside it.

### 15.8 The first set, proposed

For MAIIC the first set is seeded as a proposal for Dr Thom, every scenario anchored to a year Malawi has lived through:

| Scenario | Weight | Anchored to |
|---|---|---|
| Base | 50 | The IMF World Economic Outlook path, World Bank actuals, the RBM's stated policy path |
| Upside | 15 | The 2021 to 2022 recovery: growth and the harvest above the base, inflation below |
| Downside | 25 | The 2023 devaluation year: the kwacha down by 44 percent, inflation and lending rates up |
| Severe | 10 | The 2016 drought together with a devaluation: the harvest fails and the currency falls |

The weights are a starting point and are his to set; the anchors are the answer to the question "why these".

### 15.9 Where it lives

Governance Centre › **Scenario Sets** (create, propose, approve, lock, versions, history, the back-test and sensitivity stored with each); Data Foundation › Macro Statistics › **Scenario Assumptions** (the base paths and the shocks); Financial Modelling › Forward-Looking Model › **FLI Adjustments** (the chain run per scenario); Report Hub › **IFRS 9 Disclosure** (the 7.35G tables) and the impairment audit workbook, which carries one row per principle of 15.3 citing the set in force.

### 15.10 Acceptance and order of work

1. A set cannot be proposed with weights that do not sum to 100, fewer scenarios than the minimum, or a downside without its calibration note; it cannot be approved by its proposer; it cannot be changed after lock except as a new version with a reason.
2. Every non-base path equals the base path transformed by its recorded shocks, and changing the base re-derives them.
3. Under the seeded weighting method, every loan carries a PD and an ECL per scenario and the reported ECL is the weighted sum; under the other method the figure equals today's.
4. The back-test and the sensitivity tables are produced for a locked period and agree with a hand calculation on a sample.
5. The disclosure report prints the narratives, weights, paths, per-scenario ECL and weight sensitivity of the set in force.

SC-1 the set, the scenarios, the shocks, the migration from the two old structures and the governance rules (two days); SC-2 the ECL per scenario and the weighting method (one day); SC-3 the overlay tie, the back-test, the sensitivity and the disclosure tables (one day); SC-4 the first set seeded as a proposal (half a day). SC-1 follows section 13; SC-2 follows FL-3.

## 16. Glossary of the new terms

- **Narration**: the text E-Banker writes on each posting. On an interest posting it states the period and the rate, which is how section 3.1 was proven.
- **Stored loan book**: the table behind the printed Loan Book Report, one row per account per run.
- **Latest run**: of several stored runs of the same month, the one with the highest row id; the one the engine keeps.
- **Take-on**: the loading of the pre-existing loans into E-Banker on 31 July 2024, with one opening posting each.
- **Diff Int Credit by ROI**: E-Banker's year-end adjustment of a year's interest to the rate on the account at year end (type 120).
- **Bootstrap (method A)**: building a month's loan book from the stored Loan Book Report's latest run, as E-Banker printed it.
- **Derivation (method B)**: building it from the ledger and the masters, with E-Banker's own arrears fields.
- **Report importer (method C)**: building it from the printed Loan Book Report uploaded as Excel, the way MAIIC has loaded every month until now, with the file landed and date-checked.
- **As at a date**: the computation of a loan's EIR and amortised cost using only what was posted, in force and locked on that date; any date, not only a month-end.
- **Take-on basis**: whether a take-on loan's EIR is recomputed from its origination (schedule and fees) or started at its 31 July 2024 balance; recorded on every take-on loan.
- **Bootstrap (the command)**: `eir:bootstrap`, which builds a clean install from the inputs committed under `docs/bootstrap/`, runs the engine chain and verifies the result against the baselines and the golden numbers.
- **Golden number**: a figure MAIIC has already accepted (an audited allowance, a tie proven on the ledger, an approved run's result) that every later build must reproduce.
- **Batch (macro import)**: the record of one import of macro statistics: source, address, fetched-at, who committed it, rows; every observation points to its batch.
- **WEO**: the IMF World Economic Outlook database, the source of the forecast years.
- **Pre-FLI and post-FLI PD**: the probability of default measured from history, and the same probability after the forward-looking adjustment; the ECL is calculated on the second.
- **Correlation finder**: the sweep of every macro series against every credit-loss proxy, over lags and transforms, that ranks which relationships are worth a model.
- **Overlay**: a forward-looking adjustment applied by judgement rather than by a model, with its reason, owner, expiry and two approvals, shown as its own line.
- **Scenario set**: the governed collection of economic scenarios for a reporting period, with their weights, paths, narratives, source vintage and approvals.
- **Shock**: the recorded transformation that turns the base path into another scenario's path: a percentage change, an absolute change, a replacement or a multiplier, by series and year.
- **Landing zone**: the raw tables that mirror E-Banker, loaded exactly as received and never edited; everything else is derived from them.
- **Pack**: one month's set of extract files plus a manifest of what they are, which query version made them and their hashes; the one form in which data enters, whichever route delivers it.
- **Watermark**: the last source key loaded for a table; the next pack starts after it.
- **Seeded default**: the value Dupleix wrote into the Governance Centre as the first approved value, in force until MAIIC changes it.
- **Icon rail**: the navigation panel folded to a narrow column of icons; labels appear as tooltips.
- **Appearance (light, dark, follow the device)**: the per-user choice of how the screens are coloured; kept in the browser for the first paint and on the user's record so that it follows them.
- **Breadcrumb**: the line at the top of a page that reads group, then page, so the reader knows where in the system they are.
- **Audit workbook**: one Excel file per standard or directive, one row per section, with what the system does, where to see it, a status and a sign-off; built from a data module together with its Markdown and PDF twins.
- **Baselines sheet**: the sheet of the audit workbook that re-checks the acceptance ties of section 9 against the live database with a PASS or FAIL formula.
- **Auditor's pack**: the period's export, the five workbooks and a manifest of checksums, bundled for Deloitte.
- **Control exception**: a difference between the signed offer letter and the system record, reported to Credit for action; not an accounting entry.
