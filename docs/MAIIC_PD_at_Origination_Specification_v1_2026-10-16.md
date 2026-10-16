---
kicker: MAIIC IFRS 9 and EIR system
title: PD at Origination Specification v1
subtitle: Keeping each loan's starting risk, so the system can tell when a loan has become riskier
date: 16 October 2026
prepared: Edward Mazibuko, Director, Data Analytics, Dupleix Institute
people: For Dr Thomson Kumwenda (CFO), MAIIC Finance, Credit and ICT; a companion to the Staging Module Specification v1 (setting 9)
status: Decisions taken by Edward Mazibuko on Dr Thom's behalf, 16 October 2026, for ratification
logo: Dupleix logo.png
justify: yes
toc: yes
---

## 1. The idea in one paragraph

IFRS 9 asks one question of every loan at every month-end: **is this loan now much riskier than it was when we made it?** If it is, the loan moves from Stage 1 to Stage 2, and its expected credit loss is measured over its whole remaining life instead of the next twelve months. To answer the question, you need two readings: the risk now, and the risk when the loan began. A doctor comparing a patient's temperature with the reading taken on admission is doing the same thing: without the admission reading, a temperature of 38 degrees tells you little about whether the patient is getting worse. The system already takes the reading now, every month. What it has never done is keep the reading taken at the start. This specification makes the system keep it.

## 2. A few words first

- **PD (probability of default):** the chance, between 0% and 100%, that a borrower stops paying. The **12-month PD** is the chance within the next year; the **lifetime PD** is the chance over the rest of the loan.
- **Origination:** the day the loan was made.
- **SICR (significant increase in credit risk):** the IFRS 9 test that moves a loan to Stage 2 (paragraph 5.5.3).
- **Backstops:** two simpler signs IFRS 9 also accepts. A loan more than 30 days overdue is presumed to have had a significant increase in risk (paragraph 5.5.11), and a person can record a reason (the qualitative SICR register).
- **Segment PD:** MAIIC's PDs are worked out per group of similar loans (by portfolio and stage, from the transition matrices), not per borrower. Every loan in a group shares the group's PD.

## 3. Where the system stands today

Today the system stages loans on the backstops only: how many days a loan is overdue, and the triggers people record in the qualitative register. That is allowed, and it was the right first step. But the backstops react late: a loan has to be overdue, or someone has to notice, before it moves.

The test IFRS 9 puts first, comparing the risk now with the risk at the start, is listed in the Staging Module Specification as **setting 9, "SICR on the change in PD since origination"**. It is set to "Not applied", and the system refuses any other value, because the starting reading does not exist. There are three reasons:

1. **The history starts late.** The system calculates PDs from July 2024, the start of the E-Banker history it holds, and at first only for a handful of loans. MAIIC's own book (about 130 loans) has PDs from then on; the Mega Farm programme's loans (about 3,700) have them only from January 2026. Most loans were made before any of that.
2. **Each month's PD replaces the last.** The PD sits on the loan's row in the monthly loan book. Nobody set aside the first one.
3. **PDs are by group, not by borrower.** This one does not stop the test, but it limits it (section 8).

## 4. What we build now

**A "birth certificate" for each loan's risk.** The first time a loan has a PD, the system writes a record for it, once, and never changes it afterwards: like an EIR once it is locked. The record holds:

- the month the PD was taken, and the loan's origination date;
- the 12-month PD before and after the forward-looking adjustment, and the lifetime PD where the system calculated one;
- the loan's stage and segment that month, and the PD run and run record the figures came from;
- **a label saying how good a starting reading it is** (below).

**The four labels.** Not every first PD is a true starting reading, so every record says plainly which kind it is:

| Label | When it applies | Example |
|---|---|---|
| **At origination** | The PD was taken in the month the loan was made | A loan made in March 2026, first given a PD in March 2026 |
| **Shortly after origination** | The loan was made after the PD history began, but its first PD came some months later | A loan made in May 2025 whose first PD is from December 2025: "first PD held, 7 months after origination" |
| **Before the PD history** | The loan was made before the PD history began, so the record is the earliest PD the system holds, not the PD at origination | A loan made in 2022, first given a PD in January 2026 |
| **Unavailable** | The loan has never had a PD in any month | A loan that was approved but never drawn |

**When the record is written.** At the end of every month's run (the month-end job, the bootstrap, or an engine run from a screen), for every loan that has a PD that month and no record yet. The first time this runs on MAIIC's server, it works through every month already held, oldest first, so every existing loan gets its record at once.

**Where you see it.** A screen, IFRS 9 Model Setup, PD Model, PD at Origination, lists every loan's record with its label, filters by label, and exports to Excel. The console command `pd:origination` does the same work and shows a summary.

## 5. The decisions, and what we decided

Dr Thom would normally make the six decisions below. We took them on his behalf on 16 October 2026, with Edward's authority, so that the work can start; each is written so that Dr Thom can ratify it or change it. None of them changes a booked figure today.

| # | Decision | What we recommend and decided | Why |
|---|---|---|---|
| 1 | Keep each loan's PD at origination? | **Yes, from now on, once per loan, never changed** | It costs nothing, changes no figure, and the history only builds from the day we start |
| 2 | Which PD to compare? | **The 12-month PD after the forward-looking adjustment.** The lifetime PD is kept as well where the system calculates one | The system calculates a lifetime PD only for Stage 2 and Stage 3 loans, so for a Stage 1 loan, the one being tested, there is none. IFRS 9 allows the change in the 12-month PD to stand in for the change in the lifetime PD when it is a reasonable approximation (paragraph B5.5.13), which it is for loans with regular repayments |
| 3 | What to do about loans made before the PD history? | **Use the first PD the system holds, labelled "before the PD history", but only where the loan was in Stage 1 in that month.** A loan with no PD at all relies on the backstops | A starting reading taken when the loan was already in Stage 2 or 3 cannot show a further significant increase. Using the first reading held is the practical compromise: it catches any deterioration since then, and the label stops anyone mistaking it for a true origination reading |
| 4 | When is the increase "significant"? | **When the PD is at least double its starting reading and at least 10 percentage points higher.** Both numbers become governed settings in the Governance Centre | Doubling is the widely used relative test. MAIIC's PDs are high (a Stage 1 segment PD near 26%), so a relative test alone would move loans on small changes in absolute terms; the 10-point floor stops that |
| 5 | When does the test start moving loans? | **Not yet. It runs in shadow first**: each month a report lists the loans the test would move, with their readings, while staging stays exactly as it is. After at least two month-ends of shadow results, adopting it is an ordinary governed change: proposed by one person, approved by another, with the shadow reports as its supporting document | It lets Finance and Credit see which loans it would move, and the effect, before it changes any stage, any ECL or the RBM return |
| 6 | Borrower grades instead of segment PDs? | **Not decided here.** Recommended for MAIIC Credit: approve the S&P grading profile and grade each borrower | It is MAIIC's credit policy, not ours to set. It makes the test much stronger (section 8) |

**What Dr Thom ratifies.** Only decisions 1 to 5, which are exactly what this build does: keep the starting reading, compare the 12-month PD, treat the older loans as decided, set the two thresholds, and run the test in shadow. None of them changes a stage, an ECL, revenue or the RBM return. Two things are not part of the ratification and stay his to decide later: **adopting the test** (switching setting 9 on, so that loans actually move to Stage 2), which is a governed change of its own, proposed and approved by two people with the shadow reports as evidence; and **decision 6**, which is a recommendation to MAIIC Credit and is not built here.

**At adoption,** the wording of setting 9's option ("lifetime PD doubled") is brought into line with decisions 2 and 4, and its two thresholds are proposed together with it.

## 6. What changes, and what does not

**Changes:** a new record per loan, written once; a new screen and command; two new governed settings for the thresholds (the doubling, and the 10-point floor), seeded at the values of decision 4 and used only by the shadow report; the shadow report itself.

**Does not change:** no stage, no PD, no ECL, no revenue and no RBM figure. Setting 9 stays "Not applied", so staging never reads the new record. The bootstrap's golden values stay as they are.

## 7. The shadow report

For a chosen month, the report takes every loan in Stage 1 that has a usable starting reading, and shows its starting PD, its PD that month, the ratio and the increase in points. It marks the loans that pass both thresholds: **"would move to Stage 2 if setting 9 were adopted"**. Loans without a usable reading are counted, by reason, so nothing is silently left out. It exports to Excel, and the month's figures go to the auditor's pack once the test is adopted.

## 8. A limit to be clear about

Because MAIIC's PDs are by segment, every loan in a segment has the same PD. So a loan's PD changes only when its segment's PD changes, or when the loan moves segment. The test therefore sees risk rising across a group, not one borrower getting into trouble. That is still useful, and the backstops still catch individual loans that fall behind. But the test becomes really powerful only when each borrower has its own grade (decision 6).

## 9. How we will know it works

1. Every loan with a PD in any month has exactly one record, and a loan with none is marked unavailable.
2. A record never changes once written: a later month, a re-run or a rebuild leaves it as it was.
3. Every record carries the right label; a loan made before the PD history is never labelled "at origination".
4. Running the bootstrap gives the same golden values as before, and every loan has its record at the end.
5. The shadow report's count of loans that would move agrees with a recalculation by hand for a sample month, and staging is unchanged by it.
6. With setting 9 at "Not applied", no stage depends on the new record.

## 10. Build order

One section, on its own branch (`pd-at-origination`), merged when the tests pass and two fresh bootstraps give the same golden values as before:

1. the record and the capture at the end of every month's run, with the labels;
2. the first-time fill for every loan already held;
3. the two threshold settings and the shadow report;
4. the screen and the command.

About a day of build, including the tests and the two proving bootstraps.
