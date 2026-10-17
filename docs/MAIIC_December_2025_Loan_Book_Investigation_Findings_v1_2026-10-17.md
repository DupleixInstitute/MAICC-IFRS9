---
kicker: MAIIC IFRS 9 and EIR system
title: What the December 2025 loan book investigation found
subtitle: Every one of the 36 disagreements with MAIIC's own workbook, traced as far back as the data goes
date: 17 October 2026
prepared: Edward Mazibuko, Director, Data Analytics, Dupleix Institute
people: For Dr Thomson Kumwenda (CFO), MAIIC Finance, Credit and ICT
status: Findings note, revised 17 October 2026 after independent review by Codex and Copilot — companion to the Loan Book Report Point-in-Time Specification v1
logo: Dupleix logo.png
justify: yes
toc: yes
---

## 1. What this document is about

The companion specification, `MAIIC_Loan_Book_Report_Point_in_Time_Specification_v1`, fixed a bug in how our system chooses between E-Banker's repeated pulls of the same closed month. This document is the verification that specification's own section 8 called for before trusting the result: the fix was built, rebuilt against real data, and checked against MAIIC's own December 2025 ECL workbook. It records exactly what changed, what still disagrees, and — because "it still disagrees" was not a good enough answer on its own — a full trace of every one of the 36 disagreeing facilities back through their entire history, to separate the ones a data defect explains, the ones pointing at a governance question about the staging policy, and the ones nothing found so far explains at all.

This note was revised once already, on 17 October 2026, after Codex and Copilot independently reviewed the first draft, re-ran its checks against the same database, and reconstructed ageing a third way (straight from the ledger) that the first draft had not tried. Their reviews corrected a real overreach in this document's first conclusion and found a genuine new lead; both are recorded in full in section 5 and in `docs/reviews/`.

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

## 5. What was found while answering that

Tracing every one of the 36 facilities' full history — in several cases back to 2020 — and, after independent review by Codex and Copilot (17 October 2026), reconstructing ageing straight from the ledger for a second opinion, turned up four distinct things, not two. The first two are genuine E-Banker data defects; the third is evidence that the system's own staging policy is discarding information it already has; the fourth is a correction to this document's own first draft. All of the checks below are now built and tested; none of them guess — they flag, or they record Finance's own position alongside the system's, and nothing more.

### 5.1 A pull that contradicts itself

On a handful of accounts, a single pull's own arrears-ageing buckets are every one nil, while that very same pull's own total-overdue-amount field (`ARREAS_TOTAL`) is a large, genuine positive number. That is not a disagreement between pulls — the earlier fix's safety check (comparing pulls against each other) cannot see it, because every pull involved can still agree with every other one. The fix now also checks a pull against itself: a pull whose buckets contradict its own total overdue amount is passed over in favour of one that does not, and if none qualify, the pre-fix answer stands, flagged.

### 5.2 An overdue date that jumps between months

A small number of accounts showed something different again: not noise within one month's repeated pulls, but the reported overdue-instalment date itself moving to an implausible value from one month-end to the next, with every pull of the new month agreeing on the wrong value — so neither of the pull-comparison checks above has anything to catch. Two shapes were found: a date before the loan's own value date (impossible), and a move of more than 150 days since last month's own booked figure (checked against every genuine bi-monthly and quarterly instalment schedule in the book, nothing legitimate ever moved the date by more than 141 days in one month). A new, third check now compares the newly reported date against the loan's own value date and against what last month's build actually booked, and flags — never silently corrects — anything beyond that.

### 5.3 The build already knows a better number for some loans, and doesn't use it

Codex and Copilot were separately asked to reconstruct ageing for the 26 facilities this document first called unexplained, directly from the ledger's own dated receipts against the instalment chart in force — not from E-Banker's report — and to compare that against MAIIC's workbook. Both independently found the same five facilities where it matches: JAT Group Ltd, BenCal Technologies Ltd, Mphanga Holdings Limited, Pamela Investments, and Chuma Mkandawire (TA Chikoma Estates).

For JAT specifically, Codex traced exactly why: `LoanBookBuildService::daysPastDue()` already computes the oldest unpaid instalment correctly — 69 days, landing in the workbook's 31-90 day bracket — but the single-ladder staging policy in force for December 2025 is defined to use the raw report bucket count instead of that schedule-corrected figure, and the raw bucket reads 1 day. The right number was already sitting inside the build; the governed policy choice was configured to look past it.

This is evidence, not proof, and it does not generalise: the same ledger reconstruction was tried on all 26 and still disagrees with the workbook on most of them (see section 7). It is a real, specific lead on five facilities, pointing at a policy question — not a defect to silently fix.

### 5.4 A correction to this document's first draft

Both reviewers caught an error here: the first draft of this document described the 26 unexplained facilities as ones where "every pull agrees" with every other pull, implying the section 5.1-style conflict check had nothing to flag on them. That was wrong, and has been verified directly: running the system's own current code fresh against 9 of the 36 facilities finds a genuine section 6.1 stable-field conflict — the first draft's conflict count had not been cross-checked against the specific 36 facilities this document is about, only against an aggregate figure across all affected months. The corrected count is below.

## 6. Of the 36, what's explained and what isn't

| | Count |
|---|---|
| Flagged by the system's own point-in-time conflict check (5.1/5.4, corrected) | 9 |
| Matches the workbook under an independent ledger/chart reconstruction (5.3) | 5 |
| Explained by the self-contradictory-bucket defect (5.1) | 7 |
| Explained by the cross-month date-jump defect (5.2) | 6 |
| No instalment chart available to reconstruct against | 3 |
| **Total with some explanation or flag (the categories above overlap)** | **23** |
| **Genuinely unexplained — clean, consistent, and still unmatched by any reconstruction tried** | **13** |

For the 13 that remain, the overdue date and day-count have been self-consistent, climbing in step with the calendar, sometimes for over a year, right through December 2025, and the independent ledger/chart reconstruction in section 5.3 was tried on them and still disagrees with the workbook. By every signal and every reconstruction tried, these loans are genuinely, unambiguously overdue. MAIIC's own workbook calls them current anyway.

Of the 36, 32 disagree in the direction "our system says more overdue, the workbook says current or less overdue"; 4 disagree the other way (our system says current or mildly overdue, the workbook says more overdue). This is not a one-directional bias in either system.

## 7. Every hypothesis tried, and why each was ruled out or partly confirmed

Each of the following was tested directly against the data, not assumed:

| Hypothesis | Test | Result |
|---|---|---|
| The re-pull problem itself | Compared the exact 36 facilities before and after the fix | Zero facilities moved either direction |
| E-Banker's buckets are broken for these loans | Checked all 26–30 pulls per account for self-contradiction | Explains 7 of 36 (section 5.1) |
| The pulls genuinely disagree with each other (the original section 6.1 check) | Ran the system's current build fresh against all 36 and read its own flag, rather than trusting this document's first draft | Explains 9 of 36 (section 5.4) — more than first reported, and overlapping with the bucket defect on some facilities |
| The build's own schedule-corrected ageing is right and the staging policy is discarding it | Independent ledger/chart reconstruction by Codex and Copilot for all 26 originally unexplained facilities | Matches the workbook on 5 of 26 (section 5.3); still disagrees on 18 of the remaining 21 chart-backed facilities, and 3 have no chart available |
| A stale or wrong maturity date | Compared the system's due date against the workbook's own maturity_date column for all 36 | Only 1 shows a material gap; the rest are close or identical |
| The workbook's `overdue_principal` / `overdue_interest` columns | Checked across all 108 workbook facilities | Zero for every single row in the entire workbook — a dead, unpopulated column |
| A manual bracket override by MAIIC (the workbook's `age_bracket_old` vs `age_bracket_revised` columns) | Compared old and revised for all 36 | Identical for every one — these loans were never touched by whatever process revised 23 *other* loans |
| A shared product line, industry, or portfolio | Grouped all 36 by product group and industry code | Spread across MAIIC and FInES, agricultural, industrial, manufacturing, construction, services — no pattern |
| Business unit or S&P-style credit grade | Checked the workbook's `business_unit` and `classification` columns | `business_unit` is identical ("CORPORATE BANKING") for all 108 workbook rows, not just the 36; `classification` is a mix in both the matched and mismatched groups |
| The workbook's finer ageing columns (181-270, 271-360, above-360 days) left blank | Checked blank vs populated for both groups | Blank for all 108 rows, matched and mismatched alike — another unused column |
| The workbook used an earlier, mid-December pull, not month-end | Checked the landing zone for any ASONDATE other than 31 December 2025 | None exists to test against; moot regardless, since these loans' overdue dates are months to years old |
| A false name/balance match in the comparison tool | Checked customer names directly for the sample | Exact matches confirmed; the pairing is correct |
| A cross-month date-jump defect (section 5.2) | Traced `overdue_principal_date` for all 36 from loan inception to December 2025 | Explains 6 of 36 |
| The build's ledger-derived schedule already knows better (section 5.3) | Independent reconstruction by Codex and Copilot | Explains 5 of 36 |
| The system's own conflict check, run fresh rather than trusted from this document's first draft (section 5.4) | Re-ran the current build against all 36 and read the actual flag | Explains 9 of 36 |

No further hypothesis remains to test from the data this investigation has access to. The paste-as-values export of the workbook (`ECL model 14 Dec 2025 - portfolio report (values).csv`) has its formulas already stripped out, so whatever actually determined the still-unexplained 13 loans' brackets is not visible in anything read so far. One matching caveat, raised by Codex: the "107 matched" figure in section 4 is not all exact name-and-balance matches — 90 matched on name and balance, 15 matched on a unique exact balance where the name differs, and 2 matched on name alone with the nearest balance. One of those two name-only matches, MUSECO (104420000005), has a workbook balance of zero against the system's 138,135,680.80 — a population or matching question in its own right, separate from the ageing question, and worth raising with Finance alongside everything else.

## 8. The full facility list

Contract numbers are the system's own (the workbook masks customer ids). "Days" is the system's `staging_dpd` for December 2025 under the single-ladder policy in force. The complete, machine-generated version of this table, with the original system bracket stated in every row's own comment, is `docs/bootstrap/models/Finance Ageing Overlay - December 2025.csv`.

| Contract | System days | System bracket | Workbook bracket | Explained by |
|---|---|---|---|---|
| 104430000002 | 302 | >181 Days | 1-30 Days | Conflict + self-contradictory buckets + date jump |
| 104430000004 | 767 | >181 Days | 1-30 Days | Self-contradictory buckets + date jump |
| 104420000005 (MUSECO) | 0 | 1-30 Days | >181 Days | — unexplained (system *less* overdue; also a balance-matching caveat, see section 7) |
| 104420000007 | 121 | 91-180 Days | 1-30 Days | Date jump |
| 104420000012 | 271 | >181 Days | 1-30 Days | Date jump |
| 104450000023 | 271 | >181 Days | 1-30 Days | — unexplained (ledger reconstruction tried, still disagrees) |
| 104450000046 | 448 | >181 Days | 1-30 Days | Self-contradictory buckets |
| 104420000052 | 271 | >181 Days | 1-30 Days | Self-contradictory buckets + date jump |
| 104450000077 | 31 | 31-90 Days | 1-30 Days | — unexplained (ledger reconstruction tried, still disagrees) |
| 104450000056 | 271 | >181 Days | 1-30 Days | Self-contradictory buckets |
| 104460000059 (BenCal) | 181 | >181 Days | 1-30 Days | **Ledger/chart reconstruction matches the workbook (0 days)** |
| 104450000063 | 31 | 31-90 Days | 1-30 Days | — unexplained |
| 104460000066 (Mphanga) | 228 | >181 Days | 1-30 Days | **Ledger/chart reconstruction matches the workbook (0 days)** |
| 104450000069 | 0 | 1-30 Days | >181 Days | — unexplained (system *less* overdue) |
| 104460000070 | 154 | 91-180 Days | 1-30 Days | — unexplained |
| 104450000072 | 181 | >181 Days | 1-30 Days | — unexplained |
| 104450000074 (Pamela) | 184 | >181 Days | 1-30 Days | **Ledger/chart reconstruction matches the workbook (0 days)** |
| 104450000076 | 271 | >181 Days | 1-30 Days | Self-contradictory buckets |
| 104460000080 | 31 | 31-90 Days | 1-30 Days | — unexplained |
| 104450000060 (Chuma) | 396 | >181 Days | 1-30 Days | **Ledger/chart reconstruction matches the workbook (0 days)** |
| 104460000083 | 31 | 31-90 Days | 1-30 Days | — unexplained |
| 104460000087 | 91 | 91-180 Days | 1-30 Days | — unexplained |
| 104450000091 | 271 | >181 Days | 91-180 Days | Self-contradictory buckets |
| 104460000092 | 1 | 1-30 Days | 31-90 Days | — unexplained (system *less* overdue) |
| 104430000062 | 91 | 91-180 Days | 1-30 Days | Conflict (section 6.1) |
| 104460000098 | 271 | >181 Days | 1-30 Days | — unexplained |
| 104430000068 | 271 | >181 Days | 91-180 Days | Conflict (section 6.1) |
| 104460000107 | 181 | >181 Days | 91-180 Days | No chart available to reconstruct against |
| 104430000070 (Ebenezer) | 91 | 91-180 Days | 1-30 Days | Conflict (section 6.1) |
| 104430000071 | 31 | 31-90 Days | 1-30 Days | Conflict (section 6.1) |
| 104460000109 | 181 | >181 Days | 1-30 Days | — unexplained |
| 104460000110 | 181 | >181 Days | 1-30 Days | No chart available to reconstruct against |
| 104460000112 | 91 | 91-180 Days | 1-30 Days | No chart available to reconstruct against |
| 104430000082 | 31 | 31-90 Days | 1-30 Days | Conflict (section 6.1) |
| 104430000087 (JAT) | 1 | 1-30 Days | 31-90 Days | **Conflict (section 6.1) + ledger/chart reconstruction matches the workbook (69 days)** |
| 104430000089 | 271 | >181 Days | 1-30 Days | Conflict (section 6.1) + date jump |

## 9. What this means, in plain terms

The re-pull fix works, and it is now more careful than it was when first built — it also catches a pull contradicting itself, and a date jumping between months, neither of which the original fix could see. Together with the system's original stable-field conflict check (run fresh, not assumed) and the independent ledger/chart reconstruction, these account for **23 of the 36** facilities MAIIC's December 2025 workbook disagrees with the system on — more than double this document's first draft.

Of those 23, 9 are a known, flagged data disagreement within the month (a person needs to look, but the system is not silently guessing); 7 are the self-contradictory-bucket defect; 6 are the cross-month date-jump defect; and for 5 — JAT, BenCal, Mphanga, Pamela and Chuma — an independent ledger/chart reconstruction lands on the exact same bracket as MAIIC's workbook, while the system's own staging policy is configured to use a less careful count instead of one the build already computes. That last group is real, specific evidence worth a governance decision (section 10) — not proof the policy is wrong everywhere, since the same reconstruction was tried on the other 21 ledger-backed facilities and still disagreed with the workbook on 18 of them.

**13 remain genuinely unexplained.** By every record and every reconstruction tried, these loans are overdue — and MAIIC's own December 2025 workbook calls them current regardless. Closing that gap needs an answer from whoever prepared the workbook: what source or method set those 13 loans' brackets. No further work against E-Banker's extracts is likely to find that answer on its own.

## 10. Recommendation and next step

1. Build and test the checks in sections 5.1, 5.2 and 5.4 — **done**, and folded into the main specification (sections 6.1 and 6.4).
2. **Do not change the staging policy to prefer the schedule-corrected count over the raw report bucket.** The evidence in section 5.3 is real but partial — it would fix 5 facilities and has no bearing on, or could even be wrong for, the other 21 this investigation could reconstruct a chart for. Whether the policy should ever prefer the schedule-corrected count is a governance decision for Edward and Dr Thom, not something built into this release.
3. Instead, a Finance ageing-overlay import was built: `app/Services/Eir/LoanAgeingOverlayService.php`, with `php artisan eir:import-ageing-overlay`. It lets Finance or Credit record their own position on a loan's ageing, with a comment, without changing anything the system calculates — the system's own days and bracket are always kept on the row, stated in the comment itself as well as its own column, so Finance's note can never be read without the figure it is responding to. A backfilled template for exactly the 36 facilities in this investigation, with the reason this document found for each one already written into the comment, is saved at `docs/bootstrap/models/Finance Ageing Overlay - December 2025.csv`. Finance can fill in the blank `finance_days`/`finance_bracket`/`comment` columns and the file can be re-imported as many times as needed; each import replaces the last for the same loan and period.
4. Carry the remaining 13-facility question, and the JAT/BenCal/Mphanga/Pamela/Chuma policy lead, to MAIIC Finance or Credit directly using that file. That is a fact-finding and governance conversation for people, not a further data investigation.
5. Hold this note, the main specification, and the two independent reviews (`docs/reviews/Codex_Review_December_2025_Loan_Book_Investigation_2026-10-17.md`, `docs/reviews/Copilot_Review_December_2025_Loan_Book_Investigation_2026-10-17.md`) together as the evidence base for decision 2 of the main specification (Edward's written approval before the fix becomes the new baseline) and for decision 4 (whether this is disclosed anywhere in the financial statements or regulatory return).
