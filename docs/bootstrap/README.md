# Bootstrap inputs

The client input files a clean install of MAICC-IFRS9 is bootstrapped from, committed so that a
server builds the whole EIR data foundation without an external file (spec v4, section 6.10) (corrected 14 October 2026; it said "section 6.11").
The committed copy is read first; a OneDrive path is only a local fallback.

| Folder | What | Loaded by |
|---|---|---|
| `ebanker-pack-2026-10-07/` | The 18 follow-up extracts of 7 October 2026 and the 5 afternoon queries: the ledger, masters, rates, PLR, charges, loan-book history, balance history, charts, plans, schedules, status history, daily accrual | `eir:bootstrap` step 3 (route 1 pack into the landing zone) |
| `ebanker-pack-2026-10-08/` | Pack 2 of 8 October 2026: the ledger and balance history since August, the interest product, security, guarantors, LOS requests, the GL_03 batch legs | step 3, with the first pack (listed in the top-level manifest) |
| `ebanker-pack-2026-10-09/` | Pack 3 of 9 October 2026, with its own manifest: the Mega Farm loan master, month-end balances, status history, rate set-up and charges; the September 2026 Loan Book Report runs; the scheme master; table counts | step 3, landed after the first pack (`landLaterPacks`) |
| `packs/ebanker-pack-2026-10-12.zip` | Pack 4 of 12 October 2026, zipped because the CSVs are 143 MB (12.4 MB zipped): the full Mega Farm book (6,974 accounts), its ledger (298,259 rows) and month-ends December 2024 to September 2026; the full charges table; fee postings; write-offs; the reschedule history; E-Banker's own trial balance rows. Unzipped into `storage/app/bootstrap-packs/` | step 3, landed after pack 3 |
| `ebanker-pack-2026-10-12-p5/` | Pack 5 of 12 October 2026: the rest of the long LOS requests (`P2_13b`); the GL 6730 postings, 88,000 search, stored trial balance rows and year opening of the 88,000 question (`GL_06a`, `GL_06b`, `TB_04`, `GL_06d`); the fee GL legs with all columns (`GL_04c`); the GL 2010 batch legs (`GL_07`); the ledger-copy tables probe (`CV_02`) | step 3 (`landLaterPacks`); reference: each has its own query id outside the families the engines read |
| `ebanker-pack-2026-10-12-p6/` | Pack 6 of 12 October 2026: the GL 6730 postings and stored trial balance rows January to June 2026 that found posting 368352 (`GL_08a`, `TB_05`); the ledger copies counted by year (`CV_03`) | step 3; reference |
| `ebanker-pack-2026-10-13-p7/` | Pack 7 of 13 October 2026: the two ledger copies from before the July 2024 migration, `CUMVOUCH_BK240824` (`CV_04a`, 12,464 rows, 2018 to 15 Aug 2024) and `CUMVOUCH_BAK` (`CV_04b`, 19,780 rows, 2000 to 27 Mar 2024), the complete pack 7 copies | step 3; reference, outside the LEDGER family so no engine reads it; `CV_04a` is tied to `CV_14` by --verify |
| `ebanker-pack-2026-10-13-p8/` | Pack 8 of 13 October 2026: the account and loan master rows of the old 00010075 block (`CV_05a`, 3 control accounts; `CV_05b`, none) and the two name probes (`CV_06`, `CV_07`). The six CV_04 files in the pack 8 folder are not landed: four repeat pack 7 byte for byte, two are truncated copies (see its manifest) | step 3; reference |
| `ebanker-pack-2026-10-14/` | Pack 9 of 14 October 2026: the July 2024 migration worksheet `OPENING_BAL` (`CV_10`, 95 rows) and the GL opening balances of 1 January 2024 (`CV_14`, 331 rows); E-Banker's overdue run (`OVERDUE_DETAILS`): the month-end rows (`OD_01`, 2,259 rows on 131 accounts, December 2024 to September 2026) and the daily rows of 2025 Q3 to 2026 Q1 (`OD_02`, three files, 28,738 rows), received at 10:01 on 14 October and added the same day; the empty tables, probes and the screenshot are listed as not landed | step 3; `CV_10` read by the restructure register; both tied by --verify; `OD_01` read by the days-past-due comparison of the baselines (reported, not asserted); `OD_02` has no reader yet |
| `data-dictionary-2026-10-06/` | The 24 data-dictionary results of 6 October 2026 (tables, columns, keys, schemes, codes, row counts, samples) | reference; `DD_09` schemes read for product names |
| `takeon/` | Tamanda's take-on workbook as received (11 Sep 2026) and the mapping workbook returned by MAIIC on 13 Oct 2026 with the fees completed (101 facilities, 362,307,251.23); the 7 Oct copy sent for completion is kept under `takeon/superseded/` | `eir:bootstrap` step 4 (take-on build: the EIR recomputed from origination with the fees) |
| `trial-balances/monthly/` | The 20 monthly trial balances, January 2025 to August 2026, as received from Finance | `eir:bootstrap` step 3b (landed by `TrialBalanceLandingService` into `ebanker_raw_rows` under query TB_01, route TRIAL_BALANCE_FILE; `gl_trial_balance_lines`, the GL side of the reconciliation, is derived from them) (corrected 14 October 2026; it said "step 3 (landed as `ebanker_trial_balances`; ...)", a table that does not exist) |
| `trial-balances/afs-bridge-2025-12/` | The December 2025 AFS bridge in its two received versions (19 Aug 2026, amounts as text; 10 Sep 2026, amounts numeric, the one that is loaded): final AFS TB, initial TB to the auditor, TB December 2025, final E-Banker TB mapped | step 3b, with the monthly files; sheet 'Final E-Banker TB Dec 2025' (corrected 14 October 2026; it said "step 3") |
| `trial-balances/afs-mapping-2023-to-2026-08/` | Every GL line mapped to its audited-accounts category with year-end balances Dec 2023, Dec 2024, Dec 2025 and Aug 2026 | committed for the category bridge to the audited accounts (F17); no code reads it yet |
| `queries/` | The SQL that produced the pack, version 1; the run sheets of packs 3 to 9 (`Pack N - date.sql`, added 14 October 2026 for packs 5 to 9) | the E-Banker Feed's query register |
| `golden.json` | The golden numbers a clean install must reproduce, each recomputed from the database by `BaselineService::goldenActuals()` when it was set, with the date, commit and database | `eir:bootstrap --verify` (and `eir:baselines`) |
| `.gitattributes` | No line-ending conversion for the CSV, JSON and SQL files here, so a clone on any machine gets the bytes the manifests hash (added 14 October 2026) | git |
| `macro/` | `worldbank-MWI.json`, the World Bank macro series snapshot (added 14 October 2026; the folder was not listed) | `macro:import-worldbank --offline` |
| `manifest.json` | Every file with its query id, row count and SHA-256; the accepted exceptions the gates allow | the gates |

Every file is exactly as received. None is opened or re-saved in Excel. A replacement (for example the
workbook returned by Finance with the fees filled in) is a new file and a new manifest
entry, and the file it replaces moves to a `superseded/` folder beside it (as the 7 October take-on copy did, `takeon/superseded/`) (corrected 14 October 2026; it said "is a new file beside the old one"); nothing here is edited in place.

Packs 5 to 7 (12 October 2026) are not here yet: their query ids are not in the query register, and pack 7 (the ledgers from before the migration) must not join the live ledger family. Pack 8 has no results yet. **Status 14 October 2026:** the bootstrap lands packs 1 to 4 and no others. Packs 1 and 2 (`ebanker-pack-2026-10-07/`, `-10-08/`) are listed by the top-level `manifest.json`. Packs 3 and 4 (`ebanker-pack-2026-10-09/`, `packs/ebanker-pack-2026-10-12.zip`) are landed after them by `Bootstrap::landLaterPacks`, which also lands any further pack folder with its own manifest. Pack 8's results arrived on 13 October; the results of packs 5 to 8 are kept with MAIIC's files, and none of them is registered, committed here or landed. Pack 9 (13 October: CV_08 to CV_14, plus RUN 8 `OD_01` overdue details added on 14 October) has no results yet. Only `OD_01` is in the query register (`EbankerQuerySeeder`), with its SQL in `queries/Pack 9 - RUN 8 - OD_01_overdue_details.sql`.

**Status 14 October 2026 (later):** packs 5 to 9 are committed here, registered in `EbankerQuerySeeder` and landed by `Bootstrap::landLaterPacks` in the order of the folder names (pack 4 zip, then `-12-p5`, `-12-p6`, `-13-p7`, `-13-p8`, `-14`). Every pack 5 to 9 query has its own query id outside the families `LandingZoneReader` reads for the engines (LEDGER, TRIAL_BALANCE, the masters, CHARGES), so landing them moves no engine figure: on a copy of a full bootstrap the baselines were unchanged after the five packs landed. Every file received from MAIIC (the follow-up results of 7 October and packs 2 to 9) is either in a manifest's `files` (landed) or in its `not_landed` list with the reason (a byte-identical second download, a truncated or superseded copy, a check-count file, an empty table, a probe of table names only, a screenshot, a script). `eir:bootstrap --verify` proves it with one inventory row per manifest: each committed file's SHA-256 recomputed (inside the zip for pack 4), each extract landed in a LANDED load with exactly its manifest rows, no CSV in a pack folder missing from its manifest, and the trial balances and take-on workbooks landed. A pack 7 file (`CV_04a3`) holds a narrative with a line break inside its quotes; `PackLandingService::readCsv` now joins a line whose quotes do not balance to the next (every earlier file has balanced quotes on every line, so it reads them as before).

**Line endings (14 October 2026).** Until `docs/bootstrap/.gitattributes`, the repository's `* text=auto` stored every CSV here with LF and gave CRLF back only on a Windows checkout with `core.autocrlf=true`. The manifests hash the files as received (CRLF), so on a Linux server, or any clone without autocrlf, every pack would have been quarantined on its SHA-256 gate. The CSV, JSON and SQL files here are now stored byte for byte; when this change is committed the files must be re-added with `git add --renormalize docs/bootstrap` so that the repository holds the received bytes.

Dates in this first pack are m/d/yyyy, as the export tool wrote them; the gates parse that format for this
pack by its manifest, and ISO for every later pack produced with the RUN 0 session settings. **Status 14 October 2026:** the RUN 0 date settings did not take. Packs 3 and 4 also arrived as m/d/yyyy (both manifests declare `m/d/Y`), and so did the results of packs 5 to 8. The ISO-date gate only warns (`PackGates.php:144`), so these packs land with the format their manifest declares.

## Running the bootstrap (for MAIIC ICT and any reviewer)

The bootstrap builds the whole system from the files in this folder and nothing else: it lands every pack, the take-on workbooks and the trial balances, builds the loan books from July 2024, runs every engine, and then proves the result against the golden numbers in `golden.json`. A server that runs it on the committed code gets the same data, the same calculations and the same results as the development copy, to the cent.

**What is needed.** PHP 8.2 with the `zip`, `pdo_mysql`, `mbstring`, `gd`, `intl` and `bcmath` extensions; MySQL 8 or MariaDB 10.4 or later; a clone of the repository (any line-ending setting: `docs/bootstrap/.gitattributes` keeps these files byte for byte); `composer install`; a `.env` whose `DB_*` lines point at an empty database created for it (utf8mb4).

**The command**, from the repository folder:

    php -d memory_limit=-1 artisan eir:bootstrap --fresh --force-wipe --with-client-inputs --build --run-engines --verify

`--fresh` rebuilds the schema; it refuses a database that already holds loan books unless `--force-wipe` is given, so point `.env` at the right database first. The World Bank series are read from the committed snapshot (`macro/worldbank-MWI.json`); `--live-macro` fetches them live instead, after which the forward-looking statistics depend on the day the command runs. Nothing the bootstrap approves is a MAIIC approval: every load, build, schedule and scenario set it approves carries the label "System Bootstrap (automated data-readiness, not a MAIIC approval)".

**How long it takes.** On the development machine (Windows, XAMPP, MariaDB) a full run took 51 minutes with the machine otherwise quiet (run A1 of commit 467ef7f: 3,057 s) and 70 to 101 minutes while other builds ran beside it (B1 5,483 s; commit c4acec4: A2 6,060 s, B2 4,196 s). Most of the time is the loan book build (27 months) and the month-end ECL path (PD, LGD, forward-looking route and ECL for every month).

**What it prints.** One line per step (the landing of each pack with its gates, the builds, every engine), then the verify table: one row per check with the expected figure, the figure recomputed from the database, and PASS or FAIL. The command exits non-zero on any FAIL. The checks are:

- the section 9 baselines: the interest posted from January 2025 to July 2026 (5,293,988,207.06), the loan book derived from the ledger against E-Banker's stored Loan Book Report on every in-scope account-month, the year-end interest batch on 4215 and 4216, and each loan GL at 31 December 2025 against Finance's trial balance (the two keyed GL openings of spec 3.5 named as accepted exceptions);
- the pack inventory, one row per manifest: every committed file's SHA-256 recomputed, every extract landed with exactly its manifest rows, no CSV in a pack folder missing from its manifest, the trial balances and take-on workbooks landed; each row also counts the received files deliberately not landed (their reasons are in the manifest);
- the golden numbers of `golden.json` below, each recomputed by `BaselineService::goldenActuals()` and compared to the cent (amounts) or exactly (counts). A golden with no committed value fails, so a value can never be taken from the run it tests.

**Proof that it reproduces.** Each commit was bootstrapped twice from a clean worktree into two empty databases (commit 467ef7f: A1 and B1; commit c4acec4: A2 and B2), and the second run's verify passed against goldens set from the first. Every table of the two databases was then compared over all columns except surrogate ids and run timestamps: the figures were identical. What differs between two runs is only what identifies a run, never a result: run UUIDs (`ecl_calculation_run_id`, `pd_segment_run_id`, the segment and projection run ids, and the transition-matrix comment that quotes the run id), the approval time stored inside `governed_scenario_sets.overlays_at_approval`, timestamps inside the audit log, and the seeded administrator's password hash (its salt is random) and remember token. On c4acec4, B2's own `--verify` against the goldens set from A2 gave 64 checks, 0 FAIL.

**The golden numbers** (set on 14 October 2026 from run A2 of commit c4acec4, database `maiic_ifrs9_final_a2`; reproduced by run B2):

| Golden | Value |
|---|---|
| Take-on worksheet (CV_10): term loans with take-on postings on 31 Jul 2024 | 77 |
| ... whose principal taken on (301) = NEWDISBURS_AMT / opening interest (303) = TOTAL_INT_CHARG / opening recoveries (305) = TOTA_REPAY_UPTO, to the cent | 76 / 76 / 72 |
| Ledger copy CUMVOUCH_BK240824 (CV_04a) to 31 Dec 2023 against the GL openings of 1 Jan 2024 (CV_14), 4 loan GLs, sum of differences | 0.50 |
| Loan book 31 Dec 2025, MAIIC's loan GLs (123 rows); the Finance loan listing is 14,841,212,779.53 | 14,841,212,779.54 |
| Loan book 31 Dec 2025, Mega Farm programme (3,671 accounts with a balance) | 54,498,560,819.60 |
| Loan book 30 Sep 2026, MAIIC's loan GLs (134 rows) | 23,189,580,235.43 |
| Loan book 30 Sep 2026, Mega Farm programme (3,618 accounts with a balance) | 56,180,761,113.43 |
| Ledger write-offs on MAIIC loans (WriteOffClassifier): 22 postings | 60,778,466.86 |
| ECL 31 Dec 2025, MAIIC's book: undiscounted / discounted at the EIR (18 loans) | 4,633,401,758.31 / 449,877,082.57 |
| ECL 31 Dec 2025, discounted by stage 1 / 2 / 3 | 5,533,881.06 / 444,343,201.51 / 0.00 |
| ECL 31 Dec 2025, Mega Farm programme | declined by the engine (no PD on MAIIC's own agricultural book that month) |
| ECL 30 Sep 2026, MAIIC's book: undiscounted / discounted at the EIR (96 loans) | 10,654,569,637.48 / 6,473,181,447.94 |
| ECL 30 Sep 2026, discounted by stage 1 / 2 / 3 | 266,143,422.79 / 179,420,874.77 / 6,027,617,150.38 |
| ECL 30 Sep 2026, Mega Farm programme / MAIIC's 5 percent share | 45,096,989,583.07 / 2,254,849,479.15 |
| EIR contracts: in the contract master / solved and locked / blocked | 144 / 12 / 115 |
| Revenue 2024: EIR interest / cash received / roll-forward rows | 12,056,553.50 / 55,983,930.53 / 14 |
| Revenue 2025: EIR interest / cash received / roll-forward rows | 361,071,124.37 / 432,472,897.80 / 64 |
| Revenue 2026 (to September): EIR interest / cash received / roll-forward rows | 234,852,444.59 / 386,932,610.86 / 35 |
| RBM return 30 Sep 2026, paragraph 12 loss list: facilities / balance | 10 / 1,510,956,083.45 |

A revenue month that cannot be opened from a booked prior-month allowance is refused and writes no row (and the month after it is refused too), so the roll-forward row counts above are the golden for the refused months. The goldens are the figures of the committed data and code; they are not MAIIC approvals and they change only by a new commit that resets `golden.json` from a fresh run (the file names the date, commit and database it was set from).
