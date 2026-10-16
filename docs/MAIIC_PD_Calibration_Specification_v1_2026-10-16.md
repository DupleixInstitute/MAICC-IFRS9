---
kicker: MAIIC IFRS 9 and EIR system
title: PD Calibration Specification v1
subtitle: Making the probability of default steadier and more believable, before anyone relies on it more
date: 16 October 2026
prepared: Edward Mazibuko, Director, Data Analytics, Dupleix Institute
people: For Dr Thomson Kumwenda (CFO), MAIIC Finance, Credit and Risk; a companion to the PD at Origination Specification v1
status: Proposed: for review and decision
logo: Dupleix logo.png
justify: yes
toc: yes
---

## 1. The idea in one paragraph

A probability of default (PD) is a forecast built from history. To estimate how likely a Stage 1 loan is to default in the next year, you look back a year, take all the loans that were in Stage 1 then, and count how many of them have since defaulted. If 3 out of 30 did, the PD is about 10%. Like any estimate from a sample, it is only as good as the sample: a weather forecaster who has watched the sky for one month should not be too confident about next year's rainfall. This specification looks at how MAIIC's PDs are worked out today, explains why they move so much and sometimes in the wrong direction, and proposes six changes that make them steadier and more believable. It changes nothing until each change is approved.

## 2. Why this matters now

The PDs are not only a number for a future test. They drive the expected credit loss booked every month: a Stage 1 loan's allowance is its exposure times its 12-month PD times its loss given default. At September 2026, a Stage 1 loan in MAIIC's own book carries a 12-month PD of about 34%, which is high for a performing loan. And the PD-change test of the PD at Origination Specification (setting 9), running in shadow, would move 27 of MAIIC's 40 tested Stage 1 loans to Stage 2, nearly all because their segment's PD rose five- or six-fold. Before that test is ever adopted, and for the sake of the booked allowance now, the PDs themselves need to be sound.

## 3. How the PD is worked out today

| Step | What the system does |
|---|---|
| The group (segment) | Loans are grouped by portfolio (MAIIC Industrial, MAIIC Agricultural, MAIIC Term, FInES Industrial, FInES Agricultural) and by stage |
| The look-back (window) | One window of 12 months, ending at the reporting month: the loans in each group at its start |
| What counts as default | Reaching Stage 3 (over 180 days overdue under the staging policy in force), or being written off, by the window's end |
| The rate | The share of the group's **balance** at the start that defaulted. One large loan weighs as much as many small ones |
| Too few loans | A group with fewer than 10 loans, or no default, takes its portfolio's rate instead, and failing that the whole book's |
| Mega Farm | Measured separately, by the Mega Farm module, and not part of this specification |

## 4. What is wrong with it

All figures below are from a fresh bootstrap built from MAIIC's packs, for MAIIC's own book.

**1. The samples are very small.** At the start of the window ending September 2026, MAIIC's whole Stage 1 book is 32 loans, and the groups hold between 0 and 16. In a group of 10 loans, each default moves the PD by 10 points. Ten loans and one default is far too low a bar for a rate you then apply for a year.

**2. Counting by balance lets one loan decide.** FInES Industrial, Stage 1:

| Window ending | Loans at start | Defaulted | By number of loans | By balance (today's method) |
|---|---|---|---|---|
| December 2025 | 16 | 4 | 25% | 7.15% |
| September 2026 | 10 | 3 | 30% | 44.29% |

The share of borrowers who defaulted barely changed (25% to 30%). The balance-weighted PD went from 7% to 44% because, in the second window, one of the three defaulters was a large loan. That is most of the six-fold rise the shadow test flagged.

**3. One window makes the PD jumpy.** Because only the latest 12 months count, the PD follows last year's experience exactly. The whole book's Stage 1 PD went from 15.2% in December 2025 to 33.7% in September 2026.

**4. The stages are in the wrong order.** A loan in Stage 2 has, by definition, already shown a significant increase in risk, so its PD must be higher than a Stage 1 loan's. In September 2026 it is lower:

| Group | Stage 1 PD | Stage 2 PD |
|---|---|---|
| Whole book | 33.7% | 26.3% |
| MAIIC Industrial | 25.9% | 21.2% |
| FInES Industrial | 44.3% | 35.3% |

A calibrated model cannot do that; a sample this small and this jumpy can.

**5. The fallback is all or nothing.** A group with 9 loans takes its parent's rate entirely and ignores its own experience; a group with 10 loans uses its own rate entirely, however noisy. MAIIC Term has no Stage 1 loans at the window start and borrows the whole book's 33.7%.

**6. The meaning of "default" moves with the staging policy.** Default is Stage 3, and Stage 3 is whatever the staging policy says. If MAIIC adopts directive-aligned staging, a short-term loan reaches Stage 3 at 91 days instead of 181, so the history of defaults, and every PD, changes too, even though no borrower behaved differently.

## 5. What a better method gives, on the same data

Two of the changes below, counting loans instead of balance and averaging over all fifteen 12-month windows held (July 2025 to September 2026), applied to the same history:

| Group and stage | September 2026 as booked | Counted, all windows |
|---|---|---|
| Whole book, Stage 1 | 33.7% | 29.6% |
| Whole book, Stage 2 | 26.3% | 56.4% |
| FInES Industrial, Stage 1 | 44.3% | 21.2% |
| FInES Industrial, Stage 2 | 35.3% | 52.1% |
| MAIIC Industrial, Stage 1 | 25.9% | 10.9% |
| MAIIC Industrial, Stage 2 | 21.2% | 37.7% |

Stage 2 is back above Stage 1 in every group, and Stage 1 is steadier and, for most groups, lower. These are indications only; section 7 says how the full effect on the allowance is measured before anything is decided.

## 6. The six proposed changes

Each change becomes a governed setting in the Governance Centre, so that it takes effect only when proposed by one person and approved by another, from a month MAIIC chooses.

| # | Change | In plain words | Recommended | Why |
|---|---|---|---|---|
| 1 | **Count loans, not balance** | The PD is the share of borrowers who defaulted, not the share of money | Count loans | A PD is about how likely a borrower is to default. The size of the loan belongs in the exposure and the loss given default, where it already is |
| 2 | **Look back over every window held** | Average all the 12-month windows since the history began, not just the latest | All windows held, growing as history grows (today 15) | Steadier PDs; one bad or good year no longer swings the allowance |
| 3 | **Blend small groups with their parent** | A small group's PD is part its own experience, part its portfolio's, in proportion to its size | Blend by the number of loans, full weight on the group's own rate at 30 loans or more | Uses each group's own experience without trusting a tiny sample completely |
| 4 | **Keep the stages in order** | A Stage 2 PD is never below the Stage 1 PD of the same group | Stage 2 at least Stage 1; Stage 3 is 100% as now | A basic property any IFRS 9 PD must have |
| 5 | **Fix what "default" means for the PD** | Default for the PD is defined on its own, not by whichever staging policy is in force | Over 90 days overdue, or written off, the usual IFRS 9 and Basel definition (IFRS 9 B5.5.37) | The PD history no longer changes when the staging policy changes |
| 6 | **Check the PDs against the real world** | Each quarter, compare the defaults the PDs predicted with those that happened, and compare the PDs with an outside benchmark | Quarterly backtest; benchmark against the S&P external PDs being built in the system | Shows whether the PDs are believable, and gives the auditors evidence |

Changes 1 to 5 change the PDs; change 6 is a check that runs beside them. Mega Farm stays measured by its own module and is not touched.

## 7. How we test it before deciding

Nothing is switched on by this specification. The proposal is first run as a **PD calibration comparison**, in the same way as the staging comparison and the re-performance workspace already in the system:

1. a copy of the database is made into a throwaway workspace;
2. the PDs are worked out again there with changes 1 to 5, and the month-end engines are run on them;
3. every loan's PD and ECL in the workspace is compared with the booked figures, loan by loan, and summed by portfolio and stage;
4. the result is a report: the new PD of each group, the effect on the allowance, which loans change stage under the shadow PD-change test, and the backtest of change 6.

That report is what Dr Thom decides on. The booked figures do not move until a change is approved, and the bootstrap's golden values are set again only then.

## 8. Decisions owed

| # | Decision | Owner | Recommendation |
|---|---|---|---|
| C1 | Count loans or balance | Dr Thom, with Risk | Count loans |
| C2 | The look-back | Dr Thom, with Risk | Every window held |
| C3 | Small groups | Dr Thom, with Risk | Blend with the parent, full own weight at 30 loans |
| C4 | Stage ordering | Dr Thom | Stage 2 at least Stage 1 |
| C5 | The definition of default for the PD | Dr Thom, with Credit | Over 90 days, or written off |
| C6 | The backtest and benchmark | Risk | Quarterly, against the S&P external PDs |
| C7 | From which month to apply the approved changes | Dr Thom | The first month not yet locked after the comparison report is reviewed |

## 9. How we will know it works

1. With every change switched off, the PDs, the ECL and the golden values are exactly as today.
2. Each change, switched on alone in the workspace, changes the PDs only in the way it describes, and the comparison report explains every difference.
3. With changes 1 to 5 on, no group has a Stage 2 PD below its Stage 1 PD.
4. A PD worked out under change 5 is the same whichever staging policy is in force.
5. The backtest compares, for each quarter, the defaults predicted with those observed, by group, and the benchmark compares each group's PD with the S&P PD for the nearest grade.
6. No booked figure moves until a change is approved in the Governance Centre.

## 10. Build order

| Step | Work | Size |
|---|---|---|
| 1 | The five settings, off by default, and the PD engine reading them | About 1 day |
| 2 | The PD calibration comparison in a workspace, with its report and export | About 1 day |
| 3 | The quarterly backtest and the benchmark | About half a day |
| 4 | The first comparison report for December 2025 and September 2026, for Dr Thom | Half a day |

About three days in all, on its own branch, merged with the usual proof (the full test suite and two fresh bootstraps whose golden values are unchanged while every setting is off).
