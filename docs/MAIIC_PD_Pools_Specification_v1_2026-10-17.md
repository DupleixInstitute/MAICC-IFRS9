---
kicker: MAIIC IFRS 9 and EIR system
title: Two PD pools: MAIIC's book and the Mega Farm programme
subtitle: How the probability of default is measured from 17 October 2026, with the Mega Farm comparison method run beside it
date: 17 October 2026
prepared: Edward Mazibuko, Director, Data Analytics, Dupleix Institute
people: For the development collaborators, MAIIC Finance and Risk, and Dr Thomson Kumwenda (CFO)
status: Built and tested; seeded as the default for every fresh install and bootstrap. Not yet adopted in maiic_ifrs9 or the candidate database (section 6). Golden numbers to be reset on Edward's approval (section 7).
logo: Dupleix logo.png
justify: yes
toc: yes
---

## 1. The decision

On 17 October 2026 Edward Mazibuko decided that the probability of default (PD) is measured on **two pools**:

1. **MAIIC's own book**: every MAIIC loan, across all its lending portfolios, measured as one pool.
2. **The Mega Farm programme**: the programme's own loans, measured as a pool of their own.

Neither pool is ever measured with the other's loans. The ECL is still reported by portfolio; only the PD's source changes. He also asked that Mega Farm's former method keep running beside the new one in every bootstrap, for comparison.

## 2. What it replaced, and why

**MAIIC's book.** Until now the seeded basis was "By portfolio". Each of six portfolios (MAIIC Industrial, MAIIC Agricultural, MAIIC Term, MAIIC Loans GL 1050103, FInES Industrial, FInES Agricultural) had its own PD per stage, and a portfolio-stage below the governed minimum took the pooled book's PD instead.

In December 2025 the minimum was met only by FInES Industrial (stage 1) and FInES Agricultural (stages 1 and 2). Every MAIIC portfolio borrowed the pooled figure for stages 1 and 2. With about 34 stage 1 loans in the whole book, splitting it gave mostly borrowed numbers with a few thin own ones. One pool states that honestly.

**Mega Farm.** The seeded method was "Agricultural-sector PD scaled to the programme" (spec v4 section 16.8). It took MAIIC's agricultural PD and multiplied it by a scalar measured from the programme's cohort default rate, capped at 40 times. The programme's own transition matrix was listed as an option but never built: choosing it declined the run. It is now built and is the seeded method. This departs from spec v4 16.8's plan to wait for three seasons. That is a decision recorded here, not an oversight.

## 3. The governed settings

| Setting | Seeded default now | Was | Options |
|---|---|---|---|
| `pd_segmentation_basis` | Pooled book | By portfolio | By portfolio; Pooled book; By RBM sector; By portfolio and RBM sector |
| `megafarm_pd_method` | Transition matrix on the programme history | Agricultural-sector PD scaled to the programme | the same four as before |
| `pd_segment_min_observations` | 10 loans and 1 default per stage | unchanged | |
| `pd_segment_thin_rule` | Take the parent: portfolio, then the pooled book | unchanged | |

Defaults live in `GovernanceService::catalogue()` and are written by `GovernanceSettingsSeeder`. The seeder only adds a setting a database does not have yet. So a fresh install or bootstrap gets the new defaults, and an existing database keeps the value it already holds (section 6).

## 4. How each pool is measured

### 4.1 MAIIC's book

`PdSegmentationService` measures it on the basis "book":
- **Loans:** every loan in a MAIIC portfolio. `scope()` leaves out any portfolio holding only Mega Farm GLs, and stops the run if a portfolio mixes the two.
- **Window and observations:** a cohort per stage at the start of a twelve-month window, followed to its end. A loan with a nil or credit balance at the start is no observation.
- **What counts as a default:** a loan that reached stage 3, left the book unpaid, or was written off inside the window.
- **The PD:** the defaulted balance over the starting balance, annualised when the window is shorter than twelve months.
- **Stage 3** is 100%.

Every MAIIC loan carries `pd_segment_key` and `pd_applied_segment_key` both set to "book", with the run and matrix ids. A thin pooled stage has no parent to take. Under "Take the parent" the run stops and names the stage, as before.

### 4.2 The Mega Farm programme

`MegaFarmEclService::programmePdByStage($period)` measures the programme's own portfolio with the same engine, the same measure and the same window, on the basis "book":
- **Loans:** the programme's portfolio, as `programmePortfolios()` finds it after `ifrs9:segment-portfolios`.
- **Stages 1 and 2** take the pool's own annual rate.
- **Thin stages:** a stage below the governed minimum is **declined** with the counts and the window named. It never takes MAIIC's PD, since there is no pool above the programme's.
- **Stage 3** is 100%.
- **The recorded basis:** the run's `basis` holds the window, the portfolios, the minimum and each stage's cohort (loans, defaults, balances, rate).

The LGD (the programme's own stage 3 cohort workout), the EAD (`EclExposure`) and MAIIC's share (from `mega_farms_scope`, 5 percent on net) are unchanged.

### 4.3 The comparison method beside it

`MegaFarmEclService::run($period, $user, $label, $compareWith)` runs any other governed method beside the one in force:
- It measures the PD, the LGD and the ECL in the same way, and records them in `megafarm_ecl_runs` with `role` set to "comparison".
- It **writes nothing to the loans**.
- The booked run has `role` "booked".
- The golden checks (`BaselineService`), the bootstrap's summary line and the PD pool checks read only booked runs.
- The Mega Farm screen labels a comparison "(comparison only, not booked)".
- A method outside the governed list is refused.

Under the pooled basis, MAIIC's agricultural loans carry the pooled-book PD. So the comparison method now scales MAIIC's pooled-book PD rather than a separate agricultural one.

## 5. What the bootstrap does and checks

Seeding: the bootstrap runs `TransitionProfileDefinitionSeeder`, `TransitionProfileOptionSeeder`, `MaiicTransitionProfileSeeder` (the M101 profile the PD engine grades on) and `GovernanceSettingsSeeder` (the two defaults above).

For every month, in the order of `Bootstrap::periodChain()`:
1. PD and LGD on the pooled MAIIC book.
2. The scenario set.
3. The forward-looking route.
4. The projection.
5. The ECL.
6. The month record.
7. Mega Farm (`monthMegaFarm`). It books the programme's own pool, then runs the scaled agricultural-sector method as a comparison. A failed comparison is recorded and never stops the booked run. The month's report line names both figures.

The verify step adds five checks of its own (`Bootstrap::pdPoolChecks()`). Each failure counts as a failed bootstrap. They sit beside the golden numbers, so the golden count is unchanged.

| Check | Fails when |
|---|---|
| transition profile M101 seeded | the M101 profile, or its start or end stage options, are missing (the engine would otherwise grade silently on whichever profile came first) |
| MAIIC book PD measured as one pool | any applied monthly PD run used a basis other than the pooled book, or none ran |
| no Mega Farm loan in the MAIIC pool | a Mega Farm loan carries a MAIIC PD run id |
| Mega Farm PD booked on its own pool | a booked run used another method |
| scaled agricultural-sector method run beside it | a month with a booked Mega Farm run has no comparison run |

## 6. Adopting it in an existing database

`maiic_ifrs9` and `maiic_ifrs9_candidate_20261017` were seeded before this change. They still hold "By portfolio" and the scaled method. To adopt the two pools there:

1. Run the migration `2026_10_17_180000_add_role_to_megafarm_ecl_runs`. On `maiic_ifrs9` this is run by Edward from the console.
2. In the Governance Centre, propose `pd_segmentation_basis` = Pooled book and `megafarm_pd_method` = Transition matrix on the programme history, with an effective date. A second person approves each.
3. Rerun PD, LGD, ECL and Mega Farm for the months wanted. A locked month is never restated.

## 7. Effect on the golden numbers

Every month's MAIIC PD and ECL change, and Mega Farm's ECL changes. So the golden values for the ECL, the stage figures, the S&P comparison and revenue that read them will no longer hold. The before and after will be shown to Edward from a fresh bootstrap. `docs/bootstrap/golden.json` is reset only on his written approval, as its own note requires.

## 8. Files and tests

| What | Where |
|---|---|
| Defaults and descriptions | `app/Services/Eir/GovernanceService.php` |
| Mega Farm own pool and comparison | `app/Services/MegaFarm/MegaFarmEclService.php` (`programmePdByStage`, `run`, `ROLE_BOOKED`, `ROLE_COMPARISON`) |
| Run role column | `database/migrations/2026_10_17_180000_add_role_to_megafarm_ecl_runs.php` |
| Bootstrap step and checks | `app/Console/Commands/Bootstrap.php` (`monthMegaFarm`, `pdPoolChecks`, `monthLine`) |
| Readers of booked runs only | `app/Services/Eir/BaselineService.php`, `app/Http/Controllers/MegaFarmController.php` |
| Tests | `tests/Feature/Pd/PdSegmentationTest.php`: the seeded pooled basis; the programme as its own pool; a thin programme stage declined. `tests/Feature/Ecl/MegaFarmProgrammeSeparationTest.php`: a comparison run writes nothing. Tests of the old methods now set those methods explicitly. |
