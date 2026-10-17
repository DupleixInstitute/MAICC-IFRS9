---
kicker: MAIIC IFRS 9 and EIR system
title: The release of 16 October 2026: what was built without a spec
subtitle: An addendum describing nine changes merged on 16 October 2026 that no specification yet described
date: 17 October 2026
prepared: Edward Mazibuko, Director, Data Analytics, Dupleix Institute
people: For the development collaborators, MAIIC Finance, Credit and ICT
status: Describes behaviour already built, merged and tested on 16 October 2026; written from the code and tests on 17 October 2026
logo: Dupleix logo.png
justify: yes
toc: yes
---

## 1. Why this addendum exists

Most of what was merged on 16 October 2026 already has a specification:
- route 4: spec v4 and the data access request;
- the staging module's governance record: Staging Module Specification v1, sections 8.6 to 8.10;
- the fee workbook round trip: spec v4 7.8;
- the held fee export: its own spec;
- PD at origination;
- the IFRS 7 disclosures;
- the RBM 13(3) hold: the RBM compliance audit.

A check of every commit of the day found the nine changes below described only in commit messages and code comments. This addendum describes each as built, so the next collaborator does not have to read the code to learn the rule. Section 11 lists the documents left out of date by the release.

## 2. No automated approval of a schedule that differs from E-Banker's chart

**Rule.** A generated version 1 schedule is compared with E-Banker's own instalment chart before it can be approved. The bootstrap never approves one that differs. A person still can, with a reason.

**How it compares.**
- **Where the chart comes from:** `App\Services\Eir\ScheduleChartCheck` builds the chart from the P2_10 rows plus the IC_01 rows P2_10 lacks (`InstalmentChartHistory::LIVE_SUPPLEMENTS`), active and not deleted. Where two rows carry the same instalment, the later-created one stands.
- **What it compares:** only instalments with principal above nil. Three things must agree:
  1. the count;
  2. the first due date, within one instalment period;
  3. the total principal, within the larger of K1.00 and 1%.
- **The outcome:** AGREES, DIFFERS or NO_CHART, stored in `contract_eir.schedule_chart_check` when the schedule is generated and when approval is asked for.

**What follows.**
- **Under the bootstrap label:** `ScheduleWorkflowService::approve` leaves a DIFFERS schedule in DRAFT. It writes "Not approved by the bootstrap: …" to `schedule_review_notes` and logs "EIR Schedule Held".
- **On readiness:** the code `SCHEDULE_DIFFERS_FROM_CHART` shows until a person approves.
- **In the bootstrap report:** note 5h counts the drafts held.
- **Loans affected:** Kadava 041, Ebenezer 070 and Chipala 071 are no longer locked by the bootstrap.

Test: `ScheduleChartGuardTest`.

## 3. Controls read the landing zone as received

**Rule.** The account exclusion register (section 6) changes what the loan book is built from, never what the controls check. A control that ties the landed data to its source reads every row as received.

**How.**
- **The unfiltered read:** `LandingZoneReader::asReceived()`, `includingExcluded()` and `readsAsReceived()`. Only the book builds apply the exclusions.
- **Readers switched to it:** `BaselineService` (every baseline and golden tie, including the CV_04a ledger tie), `PackGates` (whatever reader it is handed), `PackLandingService`, `TrialBalanceLandingService`, the take-on gates, the GL fee gate, and the GL names in `EirGlReconciliationService`.
- **Fees of an excluded loan:** in `GlFeeBuildService` such a fee is set aside as out of scope, neither loaded nor held.

**New exclusion category.**
- `CLOSED_BEFORE_WINDOW`: "Real loan, closed; nothing held for it in the months the system covers". It applies to Global Seeds (104420000044) and Angwenda (104460000029), which were earlier mislabelled.
- An entry nobody has touched is relabelled from the file and logged as "Account Exclusion Relabelled".

Test: `AccountExclusionTest`. The CV_04a tie stays at 0.50 with exclusions approved.

## 4. Generated schedules keep the last instalment

**Rule.** E-Banker writes a loan's maturity as the anniversary less one day. A schedule generated to that date used to drop the final instalment, due on the anniversary itself. The generator now counts instalments to `termEnd(maturity)`, the maturity plus one day.

**How.** `ScheduleWorkflowService::instalmentsTo(first, maturity, interval)` counts the dates the generator writes (first due date plus n intervals, month-end safe) up to `termEnd`. A single bullet due on the anniversary is no longer refused. Locked schedules are not rewritten; they change only through a reopen.

Loans affected: Nature's Gift, Kadava, Udon 056 and Mtengo 033. Tests: `tests/Unit/ScheduleTermEndTest.php` and `ScheduleShapeGovernanceTest`.

## 5. A closed loan keeps the terms it was lent on

**Rule.** E-Banker's Loan Book Report prints a closed loan's terms blank. The contract master therefore reads the latest report run that still **states the facility**: a non-blank value date, or an amount disbursed above nil.

**How.** `ContractInputsBuildService::contractMaster` uses `statesTheFacility()`. Each of these now has its sanctioned amount, matching DS_01, and its version 1 schedule is generated as for any other loan:
- JVD Agro (109): K250,000,000;
- Nature's Gift (023): K24,600,000;
- Kadava (041): K12,000,000.

The take-on route of 023 and 041 stays TAKEON_BALANCE, because their chart dates from the 2024 migration. Pack 11's IC_01 chart is landed (`EbankerQuerySeeder`, pack 11 run sheet), and `RestructureCandidateService` reads the same rule.

Test: `ClosedLoanTermsTest`.

## 6. The account exclusion register

**What it is.** A governed list of E-Banker accounts that are not loans of the book, left out of every build. On 16 October 2026 MAIIC confirmed nine UAT test accounts and three duplicate approvals never drawn. Two closed 2022 loans were added under section 3's category.

**Categories and statuses.**
- Categories: `UAT_TEST_ACCOUNT`, `DUPLICATE_APPROVAL`, `CLOSED_BEFORE_WINDOW`.
- Statuses: PROPOSED, APPROVED, REJECTED, WITHDRAWN.

**Maker-checker.** One person proposes. A different person approves, with a supporting document (approvals workflow `account-exclusion`). Withdrawing an approved exclusion is requested by one person and approved by another.

**What approval does.**
- **Archive:** the account's rows in the tables the service lists move to `account_exclusion_archive` as JSON, and a withdrawal restores them with their ids.
- **Locked months:** approval or withdrawal is refused while any of the account's rows sits in a locked month.
- **Rows never moved:** run records, re-performance rows, the audit log and the raw landing rows.
- **In every build:** `LandingZoneReader` drops excluded accounts from every family, the file importers refuse their rows, and method C archives the report's rows.

**Bootstrap and decisions pack.** Step 3d (`landClientInput`) lands the committed file `docs/bootstrap/account_exclusions.json` as approved under the bootstrap label. The decisions pack exports and replays the register before the loan book is built.

**Screen and code.**
- Screen: Customer & Loan Data, then Account Exclusions (`account-exclusions.*`). Viewing needs `eir.view`; every action needs `eir.govern`.
- Code: `App\Services\Ebanker\AccountExclusionService`; `App\Support\AccountExclusions` (the approved set, cached as `maiic.account_exclusions.approved`).

Test: `AccountExclusionTest`.

## 7. The golden numbers of 16 October 2026

39 golden values were reset from a fresh bootstrap (`maiic_ifrs9_rel_c`) and reproduced by a second one (`rel_d`): `eir:baselines`, 108 checks, all passing. `docs/bootstrap/golden.json` holds the set under `release_set`. What moved:
- **The exclusions:** loan book rows 123 to 120 and 134 to 131; contracts 144 to 132; S&P basis loans 109 and 95.
- **Schedules no longer locked:** 070 and 071 (section 2), with revenue down K237.7m and K405.5m, and both leaving the discounted ECL.
- **Rounding:** a few kwacha on 056 and 033.

## 8. Every error opens as a modal

`resources/js/Jetstream/FlashMessages.vue` opens every flash error and every validation error as a modal (`role="alertdialog"`, dark mode included). It says "Nothing was changed by the part that failed", and keeps the inline box as a reminder. Blade error pages for 403, 404, 419, 429, 500 and 503 share `resources/views/errors/layout.blade.php`. The same release put four command-line steps on screens: recalculate, lock without a run record, lock-review detect, and the reset-schedule build. Those are described in technical manuals 06 and 12 and Staging Module Specification v1, section 8.8.

## 9. Self-service account deletion is off

`config/jetstream.php` no longer enables `Features::accountDeletion()`, for two reasons:
- Approvals, the decisions pack and the audit log name the people who decided, so deleting a person would break that record.
- The delete action also failed on a missing `personal_access_tokens` table.

A user who leaves is made inactive. `DeleteAccountTest` now asserts that no account can be deleted. The same commit fixed four template tests; the full suite then passed 1,055 tests with none failing.

## 10. One E-Banker voucher is one cash posting

**Rule.** A cash posting can reach the EIR engine by two routes: the ledger route (key `"<id>"`, source `EBANKER_LEDGER`) and Extract B (key `"CUMVOUCH:<id>"`). Whichever loads a voucher first owns it, and the other skips it.

**How.** `EirLedgerCashService::vouchersLoadedElsewhere($source)` maps one route's key to the other's. `EirLedgerCashService::load` and `ContractTransactionImportService` both skip a voucher the other route loaded. The ledger load reports how many it left to the other route.

This removed cash read twice on 071 (K36,976,412.25) and 056 (K4,000,000.00), found by the September 2026 re-performance. Golden values were unchanged.

Test: `LedgerCashOneVoucherTest`.

## 11. Documents the release left out of date

| Document | What is out of date |
|---|---|
| `docs/bootstrap/README.md`, golden table | Still shows the 14 October set (77 values; loans 123/134; contracts 144). The current set is in `golden.json` (`release_set`, 16 October). |
| Spec v4 section 17, S&P table | Shows 112/98 loans; the golden now holds 109/95. |
| `docs/bootstrap/README.md`, `account_exclusions.json` line | Lists two categories; there are three (section 3). |

These figures change again when the goldens are reset for the two PD pools and the December 2025 overrides (PD Pools Specification v1, section 7; Finance Overlay Importers Specification v2, section 9). That reset needs Edward's written approval. So the tables above are best corrected once, at that reset, rather than twice. The out-of-date status lines in the held fee spec, spec v4 7.8 and technical manual 10 were corrected on 17 October 2026.
