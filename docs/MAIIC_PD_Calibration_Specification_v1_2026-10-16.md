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

## 5. What the changes give, on the same data

The analysis below rebuilds every 12-month window from MAIIC's loan-month history (27 months, July 2024 to September 2026; 15 complete windows; 663 Stage 1 and 319 Stage 2 loan-windows) and applies the changes one at a time. Rebuilt with today's method, it gives exactly the engine's own figures, so the starting point is sound.

**Stage 1 PD, for the window ending September 2026**

| Group | Today | 1: count loans | 1 and 2: every window | 1 to 4 | With default at 90 days at the window end |
|---|---|---|---|---|---|
| Whole book | 33.7% | 34.4% | 29.6% | 29.6% | 47.1% |
| MAIIC Industrial | 25.9% | 16.7% | 10.9% | 10.9% | 37.0% |
| FInES Industrial | 44.3% | 30.0% | 21.2% | 21.2% | 38.4% |
| FInES Agricultural | 64.6% | 50.0% | 43.9% | 43.9% | 59.2% |

**Stage 2 PD, for the same window**

| Group | Today | 1: count loans | 1 and 2: every window | 1 to 4 | With default at 90 days at the window end |
|---|---|---|---|---|---|
| Whole book | 26.3% | 45.7% | 56.4% | 56.4% | 72.4% |
| MAIIC Industrial | 21.2% | 20.0% | 37.7% | 37.7% | 73.6% |
| FInES Industrial | 35.3% | 50.0% | 52.1% | 52.1% | 70.6% |

Three things stand out:

- **Changes 1 and 2 do almost all the work.** Counting loans and using every window puts Stage 2 back above Stage 1 in every group, and makes the PDs far steadier.
- **Changes 3 and 4 change nothing today**, because, once every window is used, every group has enough loans and the stages are already in order. They stay as safety nets for a thin month, a new portfolio, or MAIIC Term, which has no Stage 1 loans of its own.
- **Change 5 is large on its own.** Moving the definition of default from over 180 days to over 90 days roughly halves again the Stage 1 PD (29.6% to 47.1% for the whole book), because many loans pass 90 days and stay in arrears without reaching 181. Counted at any month-end instead of at the window's end, it is larger still (58.7%). Section 6 therefore recommends deciding it separately.

**Did the PDs predict what happened? (a backtest)**

For the three months where both are known, the PD each group was given, against what that group's loans actually did over the next twelve months:

| Group and stage | PD given (July to September 2025) | What happened, counting loans | What happened, by balance |
|---|---|---|---|
| Whole book, Stage 1 | 20% to 21% | 17%, 19%, 34% | 6%, 8%, 34% |
| Whole book, Stage 2 | 0%, 57%, 100% | 58%, 50%, 46% | 47%, 34%, 26% |
| FInES Agricultural, Stage 1 | 17% to 18% | 43%, 43%, 50% | 58%, 59%, 65% |
| MAIIC Industrial, Stage 1 | 20% to 21% | 0%, 0%, 17% | 0%, 0%, 26% |

The Stage 2 PDs given were 0% or 100%: a group of a few loans had seen either no default or only defaults. What actually happened was about half. Counted by loans, outcomes are steady; by balance they swing with whichever large loan happened to default. The proposed figures (Stage 1 29.6%, Stage 2 56.4% for the whole book) sit close to what happened.

**The effect on the allowance (an estimate)**

A Stage 1 loan's allowance is proportional to its 12-month PD, so the effect on Stage 1 can be estimated by scaling each loan's booked allowance by the change in its group's PD (keeping the forward-looking adjustment on top). For MAIIC's own book at September 2026, against a booked Stage 1 allowance of K1,901.6 million:

| Changes | Estimated Stage 1 allowance | Change |
|---|---|---|
| 1: count loans | K1,718.6 million | K182.9 million lower (10%) |
| 1 and 2 (and 1 to 4) | K1,422.3 million | K479.3 million lower (25%) |
| 1 to 4, and default at 90 days at the window end | K2,483.5 million | K582.0 million higher (31%) |

Stage 2's allowance is over the loan's whole life and does not scale this way: it is measured properly only by the comparison run of section 7, which re-runs the engines. These estimates are a guide to direction and size, not booked figures.

## 6. The six proposed changes

Each change becomes a governed setting in the Governance Centre, so that it takes effect only when proposed by one person and approved by another, from a month MAIIC chooses.

| # | Change | In plain words | Recommended | Why |
|---|---|---|---|---|
| 1 | **Count loans, not balance** | The PD is the share of borrowers who defaulted, not the share of money | Count loans | A PD is about how likely a borrower is to default. The size of the loan belongs in the exposure and the loss given default, where it already is |
| 2 | **Look back over every window held** | Average all the 12-month windows since the history began, not just the latest | All windows held, growing as history grows (today 15) | Steadier PDs; one bad or good year no longer swings the allowance |
| 3 | **Blend small groups with their parent** | A small group's PD is part its own experience, part its portfolio's, in proportion to its size | Blend by the number of loans, full weight on the group's own rate at 30 loans or more | Uses each group's own experience without trusting a tiny sample completely |
| 4 | **Keep the stages in order** | A Stage 2 PD is never below the Stage 1 PD of the same group | Stage 2 at least Stage 1; Stage 3 is 100% as now | A basic property any IFRS 9 PD must have |
| 5 | **Fix what "default" means for the PD** | Default for the PD is defined on its own, not by whichever staging policy is in force | **Now: over 180 days overdue, or written off**, today's level, fixed so it no longer moves with the staging policy. **Separately: whether to move to over 90 days**, IFRS 9's rebuttable presumption (B5.5.37), decided as credit policy together with the staging decision (S1 of the Staging Module Specification) | Fixing the definition stops the PD history moving when the staging policy changes. Moving it to 90 days raises the Stage 1 allowance by about a third (section 5), so it is a decision of its own, not part of a calibration fix |
| 6 | **Check the PDs against the real world** | Each quarter, compare the defaults the PDs predicted with those that happened, and compare the PDs with an outside benchmark | Quarterly backtest; benchmark against the S&P external PDs being built in the system | Shows whether the PDs are believable, and gives the auditors evidence |

Changes 1 and 2 change the PDs today; changes 3 and 4 are safety nets that change nothing on today's data; change 5, as recommended, fixes the definition without moving it; change 6 is a check that runs beside them. Mega Farm stays measured by its own module and is not touched.

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
| C5 | The definition of default for the PD | Dr Thom, with Credit | Now: over 180 days, or written off, fixed apart from the staging policy. Whether to move to 90 days: decided with S1 |
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
