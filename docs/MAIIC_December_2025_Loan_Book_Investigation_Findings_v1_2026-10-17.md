---
kicker: MAIIC IFRS 9 and EIR system
title: What the December 2025 loan book investigation found
subtitle: Every one of the 36 disagreements with MAIIC's own workbook, traced as far back as the data goes
date: 17 October 2026
prepared: Edward Mazibuko, Director, Data Analytics, Dupleix Institute
people: For Dr Thomson Kumwenda (CFO), MAIIC Finance, Credit and ICT
status: Findings note — companion to the Loan Book Report Point-in-Time Specification v1
logo: Dupleix logo.png
justify: yes
toc: yes
---

## 1. What this document is about

The companion specification, `MAIIC_Loan_Book_Report_Point_in_Time_Specification_v1`, fixed a bug in how our system chooses between E-Banker's repeated pulls of the same closed month. This document is the verification that specification's own section 8 called for before trusting the result: the fix was built, rebuilt against real data, and checked against MAIIC's own December 2025 ECL workbook. It records exactly what changed, what still disagrees, and — because "it still disagrees" was not a good enough answer on its own — a full trace of every one of the 36 disagreeing facilities back through their entire history, to separate the ones a data defect explains from the ones nothing in our data explains.

## 2. What was checked, and how

Everything below was run against `maiic_ifrs9_bootstrap`, a real copy of MAIIC's landed E-Banker data (795,100 raw rows, loan books from July 2024 through September 2026), never against the live, relied-upon figures. Two separate throwaway database copies were used for the parts that write (rebuilding a month under the fix); every comparison against the live figures was read-only. Nothing in this document changed anything MAIIC currently relies on.

Three questions were asked, in order:

1. Does the fix do what the specification says it does, across every month, not just December 2025?
2. Does fixing the re-pull problem make the system agree with MAIIC's own December 2025 workbook?
3. For whatever still disagrees, is there a cause we can find in our own data, or not?

## 3. Does the fix do what it was supposed to do?

Every month from July 2024 to September 2026 was rebuilt under the fix and compared, loan by loan, against what is currently stored.

| | Result |
|---|---|
| Loan-month rows changed | 393 of roughly 100,000 |
| Rows where the fix found a disagreement it would not resolve silently (flagged, not guessed) | 73 |
| `overdue_days` (the RBM directive's own count) changed anywhere | 0 |
| `carrying_amount` changed anywhere | 0 |
| RBM classification changed anywhere | 0 |

The fix only ever touches `staging_dpd` and the arrears-ageing bucket columns. It never touches the carrying amount, the RBM directive's own overdue-day count, or the Reserve Bank return. That is exactly the scope the specification promised.

## 4. Does it make December 2025 agree with MAIIC's workbook?

MAIIC's own December 2025 ECL workbook names 108 facilities. 107 of them match a loan in our system by name and balance. Before any of this year's fixes, 36 of those 107 disagreed with the system on which arrears bracket the loan is in. After building the fix and re-running the same check: **still 36, and the same 36** — not one of them moved in either direction.

That is not a failure of the fix. The fix answers "which of several E-Banker pulls should we trust for one month's arrears ageing." For the specific 36 facilities MAIIC's workbook disagrees on, every pull already agreed with every other pull before the fix touched anything — so there was nothing for the fix to correct. The disagreement with the workbook was never caused by the re-pull problem; it was there independently, and stayed independently.

Given that, the honest next question is not "did the fix work" (it did, see section 3) but "why does the workbook still disagree, and is that something our data can explain."

## 5. Two real, distinct defects found while answering that

Tracing every one of the 36 facilities' full history — in several cases back to 2020 — turned up two further, genuine E-Banker data defects, separate from the re-pull problem the main fix addresses. Both are now built and tested; both only ever flag, never guess.

### 5.1 A pull that contradicts itself

On a handful of accounts, a single pull's own arrears-ageing buckets are every one nil, while that very same pull's own total-overdue-amount field (`ARREAS_TOTAL`) is a large, genuine positive number. That is not a disagreement between pulls — the earlier fix's safety check (comparing pulls against each other) cannot see it, because every pull involved can still agree with every other one. The fix now also checks a pull against itself: a pull whose buckets contradict its own total overdue amount is passed over in favour of one that does not, and if none qualify, the pre-fix answer stands, flagged.

### 5.2 An overdue date that jumps between months

A small number of accounts showed something different again: not noise within one month's repeated pulls, but the reported overdue-instalment date itself moving to an implausible value from one month-end to the next, with every pull of the new month agreeing on the wrong value — so neither of the pull-comparison checks above has anything to catch. Two shapes were found: a date before the loan's own value date (impossible), and a move of more than 150 days since last month's own booked figure (checked against every genuine bi-monthly and quarterly instalment schedule in the book, nothing legitimate ever moved the date by more than 141 days in one month). A new, third check now compares the newly reported date against the loan's own value date and against what last month's build actually booked, and flags — never silently corrects — anything beyond that.

## 6. Of the 36, what's explained and what isn't

| | Count |
|---|---|
| Explained by the self-contradictory-bucket defect (5.1) | 7 |
| Explained by the cross-month date-jump defect (5.2) | 6 |
| Explained by both (the two overlap on 3 facilities) | — |
| **Total explained by a real, fixable data defect** | **10** |
| **Unexplained — the data is clean, consistent, and has been for months or years** | **26** |

For the 26, the overdue date and day-count have been self-consistent, climbing in step with the calendar, sometimes for over a year, right through December 2025. There is no gap in the record, no implausible jump, no internal contradiction. By every signal E-Banker gives this system — the ledger, the instalment chart, the overdue-principal date, the total overdue amount — these loans are genuinely, unambiguously overdue. MAIIC's own workbook calls them current anyway.

Of the 36, 32 disagree in the direction "our system says more overdue, the workbook says current or less overdue"; 4 disagree the other way (our system says current or mildly overdue, the workbook says more overdue). This is not a one-directional bias in either system.

## 7. Every hypothesis tried, and why it was ruled out

Before concluding the 26 are genuinely unexplained, each of the following was tested directly against the data, not assumed:

| Hypothesis | Test | Result |
|---|---|---|
| The re-pull problem itself | Compared the exact 36 facilities before and after the fix | Zero facilities moved either direction |
| E-Banker's buckets are broken for these loans | Checked all 26–30 pulls per account for self-contradiction | Only explains 7 of 36 (section 5.1) |
| A stale or wrong maturity date | Compared the system's due date against the workbook's own maturity_date column for all 36 | Only 1 shows a material gap; the rest are close or identical |
| The workbook's `overdue_principal` / `overdue_interest` columns | Checked across all 108 workbook facilities | Zero for every single row in the entire workbook — a dead, unpopulated column |
| A manual bracket override by MAIIC (the workbook's `age_bracket_old` vs `age_bracket_revised` columns) | Compared old and revised for all 36 | Identical for every one — these loans were never touched by whatever process revised 23 *other* loans |
| A shared product line, industry, or portfolio | Grouped all 36 by product group and industry code | Spread across MAIIC and FInES, agricultural, industrial, manufacturing, construction, services — no pattern |
| Business unit or S&P-style credit grade | Checked the workbook's `business_unit` and `classification` columns | `business_unit` is identical ("CORPORATE BANKING") for all 108 workbook rows, not just the 36; `classification` is a mix in both the matched and mismatched groups |
| The workbook's finer ageing columns (181-270, 271-360, above-360 days) left blank | Checked blank vs populated for both groups | Blank for all 108 rows, matched and mismatched alike — another unused column |
| The workbook used an earlier, mid-December pull, not month-end | Checked the landing zone for any ASONDATE other than 31 December 2025 | None exists to test against; moot regardless, since these loans' overdue dates are months to years old |
| A false name/balance match in the comparison tool | Checked customer names directly for the sample | Exact matches confirmed; the pairing is correct |
| A cross-month date-jump defect (section 5.2) | Traced `overdue_principal_date` for all 36 from loan inception to December 2025 | Explains 6 of 36; the other 30 show no anomaly anywhere in their history |

No further hypothesis remains to test from the data available. The paste-as-values export of the workbook (`ECL model 14 Dec 2025 - portfolio report (values).csv`) has its formulas already stripped out, so whatever actually determined these 26 loans' brackets is not visible in anything this investigation can read.

## 8. The full facility list

Contract numbers are the system's own (the workbook masks customer ids; matching was by exact name and balance). "Days" is the system's `staging_dpd` for December 2025 under the single-ladder policy in force.

| Contract | System days | System bracket | Workbook bracket | Explained by |
|---|---|---|---|---|
| 104430000002 | 302 | >181 Days | 1-30 Days | Self-contradictory buckets + date jump |
| 104430000004 | 767 | >181 Days | 1-30 Days | Self-contradictory buckets + date jump |
| 104420000005 | 0 | 1-30 Days | >181 Days | — unexplained (system *less* overdue) |
| 104420000007 | 121 | 91-180 Days | 1-30 Days | Date jump |
| 104420000012 | 271 | >181 Days | 1-30 Days | Date jump |
| 104450000023 | 271 | >181 Days | 1-30 Days | — unexplained |
| 104450000046 | 448 | >181 Days | 1-30 Days | Self-contradictory buckets |
| 104420000052 | 271 | >181 Days | 1-30 Days | Self-contradictory buckets + date jump |
| 104450000077 | 31 | 31-90 Days | 1-30 Days | — unexplained |
| 104450000056 | 271 | >181 Days | 1-30 Days | Self-contradictory buckets |
| 104460000059 | 181 | >181 Days | 1-30 Days | — unexplained |
| 104450000063 | 31 | 31-90 Days | 1-30 Days | — unexplained |
| 104460000066 | 228 | >181 Days | 1-30 Days | — unexplained |
| 104450000069 | 0 | 1-30 Days | >181 Days | — unexplained (system *less* overdue) |
| 104460000070 | 154 | 91-180 Days | 1-30 Days | — unexplained |
| 104450000072 | 181 | >181 Days | 1-30 Days | — unexplained |
| 104450000074 | 184 | >181 Days | 1-30 Days | — unexplained |
| 104450000076 | 271 | >181 Days | 1-30 Days | Self-contradictory buckets |
| 104460000080 | 31 | 31-90 Days | 1-30 Days | — unexplained |
| 104450000060 | 396 | >181 Days | 1-30 Days | — unexplained |
| 104460000083 | 31 | 31-90 Days | 1-30 Days | — unexplained |
| 104460000087 | 91 | 91-180 Days | 1-30 Days | — unexplained |
| 104450000091 | 271 | >181 Days | 91-180 Days | Self-contradictory buckets |
| 104460000092 | 1 | 1-30 Days | 31-90 Days | — unexplained (system *less* overdue) |
| 104430000062 | 91 | 91-180 Days | 1-30 Days | — unexplained |
| 104460000098 | 271 | >181 Days | 1-30 Days | — unexplained |
| 104430000068 | 271 | >181 Days | 91-180 Days | — unexplained |
| 104460000107 | 181 | >181 Days | 91-180 Days | — unexplained |
| 104430000070 | 91 | 91-180 Days | 1-30 Days | — unexplained |
| 104430000071 | 31 | 31-90 Days | 1-30 Days | — unexplained |
| 104460000109 | 181 | >181 Days | 1-30 Days | — unexplained |
| 104460000110 | 181 | >181 Days | 1-30 Days | — unexplained |
| 104460000112 | 91 | 91-180 Days | 1-30 Days | — unexplained |
| 104430000082 | 31 | 31-90 Days | 1-30 Days | — unexplained |
| 104430000087 | 1 | 1-30 Days | 31-90 Days | — unexplained (system *less* overdue) |
| 104430000089 | 271 | >181 Days | 1-30 Days | Date jump |

## 9. What this means, in plain terms

The re-pull fix works, and it is now more careful than it was when first built — it also catches a pull contradicting itself, and a date jumping between months, neither of which the original fix could see. Together the three checks account for 10 of the 36 facilities MAIIC's December 2025 workbook disagrees with the system on.

The other 26 are not a system problem we can find and are not caused by anything this investigation turned up in E-Banker's own extracts. They are loans that, by every record our system holds, have been genuinely overdue for a long time, sometimes over a year, with no gap or inconsistency anywhere in that record — and MAIIC's own December 2025 workbook calls them current regardless. Closing that gap needs an answer from whoever prepared the workbook: what source or method set those 26 loans' brackets. No further work against E-Banker's extracts will find that answer, because the extracts do not disagree with themselves — they consistently say these loans are overdue.

## 10. Recommendation and next step

1. Build and test the two further checks in sections 5.1 and 5.2 — **done**, and folded into the main specification (sections 6.1 and 6.4).
2. Carry the remaining 26-facility question to MAIIC Finance or Credit directly: ask who prepared the December 2025 workbook's age brackets and from what source, for these 26 facilities by name. That is a fact-finding question for a person, not a further data investigation.
3. Hold this note, and the main specification, together as the evidence base for decision 2 of the main specification (Edward's written approval before the fix becomes the new baseline) and for decision 4 (whether this is disclosed anywhere in the financial statements or regulatory return).
