# After-build report: the demo build of 8 October 2026, against specification v4

**What this is.** On the evening of 8 October 2026 the E-Banker data of 7 and 8 October was loaded into a copy of the MAICC-IFRS9 database (`maiic_ifrs9_demo`, run with `php artisan --env=demo`) and taken as far through the existing engines as the system allows, for the meeting with Dr Thom on 9 October. The production copy (`maiic_ifrs9`) was not opened. This report says what was built, what the system itself refused and why, how that compares section by section with specification v4, and what the comparison teaches the build that starts on 13 October. No application code was changed; the demo used the system as it stands on `master` at `9376fe9` plus the data.

## 1. What stands on the demo database

| Step | What was done | Result |
|---|---|---|
| Copy | `maiic_ifrs9` dumped and restored as `maiic_ifrs9_demo`; `.env.demo` points at it; pending migrations applied | 161 tables; source untouched |
| Governance | `GovernanceSettingsSeeder` | 28 settings in force from 1 January 2025 |
| Loan books | The stored Loan Book Report runs (`P2_08`), latest run per account-month, mapped onto `loan_books` by a loader script (spec v4 method A) | 2,361 rows, 21 month-ends Dec 2024 to Aug 2026, carrying totals equal to the figures tied to the ledger on 7 October (Dec 2025: 14,841,212,780; Aug 2026: 23,323,504,654) |
| Trial balances | `eir:import-trial-balances` on the 20 monthly files and the AFS bridge | 21 files, 3,803 lines, 0 rejected |
| Schemes | DD_10 scheme settings loaded into `schemes` | 18 schemes: interest policy, floating flag, balance-wise interest, EMI type |
| Contract master | A file built from the account and loan masters with the headers the importer accepts, loaded through `MappedFileReader` and `ContractMasterImportService` | 144 contracts created; 40 held because no loan-book row exists for them since Dec 2024 (closed earlier); 36 floating by Interest Policy |
| Reference rates | `eir:import-reference-rates` on the corrected PLR series | 48 rows, 26 changes, current 21.20% |
| Spreads | `eir:derive-spreads` | Derived; drift flagged where the spread moved more than 0.15 points |
| Schedules | `eir:generate-schedules` | 31 drafts generated; 113 refused with named reasons; 31 approved under the label "System Bootstrap (automated data-readiness, not a MAIIC approval)" |
| EIR | `EirCalculationService::calculate` on the 31; locked with the administrator override under the same label | 31 solved: FInES 10.47% effective annual; MAIIC Agricultural 32.9%; MAIIC Industrial 31.4%; term loans 40.9% (nominal 35% compounded monthly) |
| Staging | `StagingClassifier` on the loaded rows, fed the five arrears buckets of the stored report under the tape's own header names, with days past due counted from `OVERDUE_PRINCI_DATE` (fallback ladder: Stage 2 from 31 days, Stage 3 from 181; no governed thresholds yet) | 2,361 rows staged: 1,300 Stage 1, 624 Stage 2, 437 Stage 3. Dec 2025: 78 / 21 / 24 loans (7.86 bn / 3.79 bn / 3.19 bn); Aug 2026: 72 / 31 / 32 (7.39 bn / 10.47 bn / 5.47 bn). A first pass staged every row Stage 1 because the buckets were passed under the wrong keys and `OVERDUE_PERIOD` (months) was stored as days; corrected the same evening |
| Revenue | `eir:run-revenue` for 2025-12 and 2026-08 | 28 and 31 contracts; cash taken from the schedule (no actual transactions loaded); August 2026 EIR interest 275.8 m on an opening gross of 11.44 bn for the 31 |
| GL interest | Extract C loaded through the mapped importer as `gl_interest` | 1,564 rows kept, 2,024 duplicate-run rows and 408 annual rows refused, total 5,293,988,207.06 (the section 9 golden number) |
| Reconciliation | `EirGlReconciliationService::forPeriod` | Dec 2025: 116 rows, posted 286.0 m, contractual 312.6 m, 28 agree, 47 explained, 41 unexplained. Jul 2026: 49 agree, 25 explained, 28 unexplained |
| Tests | `php artisan test tests/Feature/Eir` | 238 passed, 24 skipped |

## 2. What the system refused, and why that is right

Every refusal below is the fail-closed behaviour the specification asks for, and each one names what is missing:

- **113 schedules not generated**: payment frequency not stated (fixed by adding it from the account master); a moratorium stated without its type (DD_10 does not carry the type in a form that can be asserted); a partly drawn facility with no stated interest or instalment basis on contract or scheme. These are the scheme settings of spec v3 6.3 and v4 7.3; they need the vendor's confirmation of the DD_10 codes, not a workaround.
- **40 contracts held**: the importer will not create a contract it cannot see in a loan book. Right for production; for the take-on history those accounts come through section 6.9.
- **Revenue refused until the EIR is approved and locked**, and until a loan-book row with a stage exists for the period: maker-checker and the period's data, both enforced.
- **The reference-rate import refused without a user id**: every load is recorded against a person.

## 3. Section by section against specification v4

| Spec section | Specified | Demo state | Gap the build closes |
|---|---|---|---|
| 2 The data | Every extract landed and proven | All extracts held and proven outside the system (7 and 8 October); inside the system: loan books, TB, schemes, contract master, PLR, Extract C | The landing zone itself |
| 3 Arithmetic | Whole month at the month-end rate; daily closing balance × rate / 365 | The reconciliation's contractual engine still uses the loan-book rate and the prior month-end balance; 41 unexplained rows in Dec 2025 show the gap | P5 (resets, the month-end-rate rule in the contractual service) |
| 4 Decisions and settings | 43 governed settings on paper | 28 in code | The 15 settings of 7 and 8 October into the catalogue (P4b first task) |
| 6 Ingestion | Landing zone, pack gates, three build methods, five routes, bootstrap command | Method A done by a script outside the system; no raw tables, no gates, no feed screen, no `eir:bootstrap` | P4b in full; the script becomes the method-A code path |
| 6.9 Take-on | Workbook landed, gated, built under `takeon_history_basis` | Not loaded; the 40 held contracts are mostly these | 6.9 build, after Tamanda's fees |
| 6.10 Bootstrap | One command, engines in order, golden numbers | Done by hand in fourteen commands; the golden number 5,293,988,207.06 reproduced by the GL import | `eir:bootstrap --run-engines --verify` |
| 6.11 As at a date | Any date | Not available | P8 |
| 7 Importers | Ledger importer from CUMVOUCH, rate-history importer, fee routes | Ledger not loaded into the EIR transactions (cash came from schedules); rates from the PLR fixture; fees not loaded | P4b |
| 8 Build plan | P4b then P5 | P1 to P4 exercised end to end on real data for the first time | As planned |
| 9 Baselines | Ten ties | Three reproduced inside the system (loan-book carrying totals; Extract C total; TB files loaded); the rest proven outside | `--verify` |
| 11 UI | Suite layout, dark mode | Existing screens | UI-1 to UI-5 |
| 12 Audit workbooks | Five workbooks, register, pack | None | CA-1 to CA-4 |
| 13 Macro | World Bank and IMF feed | Macro Elements by hand | MS-1 to MS-3 |
| 14, 15 FLI and scenarios | Finder, repaired regression, routes, governed scenarios | Existing regression and scenario screens, unchanged | FL-1 to FL-4, SC-1 to SC-4 |
| 16 Mega Farm | Out of EIR, in ECL, 5 percent share | The Nov 2025 Mega Farm rows are in `loan_books` from the printed report (3,490 rows); nothing computed on them | After the MF extracts |

## 4. What the demo taught the build

1. **The importers work on E-Banker data without code change**, provided the file carries the headers they already know. The method-A loader and the contract-master builder of this evening are the two scripts P4b turns into commands.
2. **The scheme settings are the gate.** The single largest cause of refused schedules is the moratorium type and the instalment basis, which DD_10 holds as codes (`EMI_BASED_ON` 0 or 2, moratorium units) that need the vendor's reading before they can be asserted. Ask Barry for the code table.
3. **The stored report carries the arrears in full**: the five day buckets, the arrears total, the date the oldest unpaid instalment fell due (`OVERDUE_PRINCI_DATE`, from which days past due are counted exactly) and `OVERDUE_PERIOD` in months. Section 6.2's choice to take arrears from E-Banker's own run is confirmed. Two traps for the build: the classifier reads the tape's header names (`1_30_days` ... `271_360_days`), not the table's column names, and `OVERDUE_PERIOD` is months, not days. The Stage 3 threshold in force is the code's fallback of 181 days; MAIIC's own rule (90 days under the IFRS 9 presumption, or whatever the 2025 provision used) must be governed before any ECL is read.
4. **The reconciliation's contractual engine is the next thing to align** with section 3: the month-end-rate rule and the charged-rate source. The 41 unexplained December rows are the measure of that work.
5. **Everything the bootstrap command of 6.10 has to do was done tonight by hand**, in fourteen commands and two scripts; the command is a sequencing of what already runs.

## 5. Files

Demo scripts in `docs/build-files/`: `demo_load_loan_books.py` (method A), `demo_contract_master.py`, `demo_schemes.py`. The database dump used for the copy is not committed. The demo database is `maiic_ifrs9_demo` on the build machine; `php artisan --env=demo serve` runs the application against it.
