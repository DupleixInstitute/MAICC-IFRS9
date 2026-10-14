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
| `ebanker-pack-2026-10-14-p10/` | Pack 10 of 14 October 2026: the overdue run's daily rows for 2026 Q2, Q3 and 1 to 12 October (`OD_02`, 21,582 rows, continuing pack 9's quarters) and the refinance batch legs (`RF_01`, 289 rows: The Food Empire and Malawi Police SACCO refinancings); the profile, the 5 August 2024 account master (vendor test accounts), the probes and the two rebuild checks are listed as not landed | step 3; `OD_02` read with `OD_01` by the days-past-due comparison; `RF_01` reference, outside the LEDGER family |
| `data-dictionary-2026-10-06/` | The 24 data-dictionary results of 6 October 2026 (tables, columns, keys, schemes, codes, row counts, samples) | reference; `DD_09` schemes read for product names |
| `takeon/` | Tamanda's take-on workbook as received (11 Sep 2026) and the mapping workbook returned by MAIIC on 13 Oct 2026 with the fees completed (101 facilities, 362,307,251.23); the 7 Oct copy sent for completion is kept under `takeon/superseded/` | `eir:bootstrap` step 4 (take-on build: the EIR recomputed from origination with the fees) |
| `trial-balances/monthly/` | The 20 monthly trial balances, January 2025 to August 2026, as received from Finance | `eir:bootstrap` step 3b (landed by `TrialBalanceLandingService` into `ebanker_raw_rows` under query TB_01, route TRIAL_BALANCE_FILE; `gl_trial_balance_lines`, the GL side of the reconciliation, is derived from them) (corrected 14 October 2026; it said "step 3 (landed as `ebanker_trial_balances`; ...)", a table that does not exist) |
| `trial-balances/afs-bridge-2025-12/` | The December 2025 AFS bridge in its two received versions (19 Aug 2026, amounts as text; 10 Sep 2026, amounts numeric, the one that is loaded): final AFS TB, initial TB to the auditor, TB December 2025, final E-Banker TB mapped | step 3b, with the monthly files; sheet 'Final E-Banker TB Dec 2025' (corrected 14 October 2026; it said "step 3") |
| `trial-balances/afs-mapping-2023-to-2026-08/` | Every GL line mapped to its audited-accounts category with year-end balances Dec 2023, Dec 2024, Dec 2025 and Aug 2026 | committed for the category bridge to the audited accounts (F17); no code reads it yet |
| `queries/` | The SQL that produced the pack, version 1; the run sheets of packs 3 to 9 (`Pack N - date.sql`, added 14 October 2026 for packs 5 to 9) | the E-Banker Feed's query register |
| `golden.json` | The golden numbers a clean install must reproduce, each recomputed from the database by `BaselineService::goldenActuals()` when it was set, with the date, commit and database | `eir:bootstrap --verify` (and `eir:baselines`) |
| `.gitattributes` | No line-ending conversion for the CSV, JSON and SQL files here, so a clone on any machine gets the bytes the manifests hash (added 14 October 2026) | git |
| `macro/` | `worldbank-MWI.json`, the World Bank macro series snapshot (added 14 October 2026; the folder was not listed) | `macro:import-worldbank --offline` |
| `models/` | The two S&P grading sheets of MAIIC's December 2025 ECL model ("ASS1-Internal Grades" and "PDs-S&P", taken as values from MAIIC & FInES ECL Model - 14th December 2025_Final.xlsx, received 13 Oct 2026) (added 14 October 2026) | `eir:bootstrap` step 3c (`SpGradesImportService`, the same import as Internal Grades: a draft profile, 8 grades, 80 tenor PDs, not active) |
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

**How long it takes.** On the development machine (Windows, XAMPP, MariaDB) a full run of commit bece21a took 68 minutes (A3 4,119 s and B3 4,096 s, the two run side by side). Earlier commits took 51 minutes with the machine otherwise quiet (467ef7f, A1 3,057 s) and up to 101 minutes while other builds ran beside them. Most of the time is the loan book build (27 months), the restructure detection and the month-end chain, which runs for every month with a PD: the scenario set, the forward-looking route, the discounted ECL, the month record and the Mega Farm programme.

**What it prints.** One line per step (the landing of each pack with its gates, the builds, every engine, one line per month of the month-end chain), then the verify table: one row per check with the expected figure, the figure recomputed from the database, and PASS or FAIL. The command exits non-zero on any FAIL. The same table is printed at any time by `php artisan eir:baselines`. The checks are:

- the section 9 baselines: the interest posted from January 2025 to July 2026 (5,293,988,207.06), the loan book derived from the ledger against E-Banker's stored Loan Book Report on every in-scope account-month, the year-end interest batch on 4215 and 4216, and each loan GL at 31 December 2025 against Finance's trial balance (the two keyed GL openings of spec 3.5 named as accepted exceptions); two rows are reported, not asserted: the Mega Farm account-months whose ledger still differs from the report, and E-Banker's overdue run against the days past due staging reads;
- the pack inventory, one row per manifest (nine manifests covering packs 1 to 10): every committed file's SHA-256 recomputed, every extract landed with exactly its manifest rows, no CSV in a pack folder missing from its manifest, the trial balances and take-on workbooks landed; each row also counts the received files deliberately not landed (their reasons are in the manifest);
- the golden numbers of `golden.json` below, each recomputed by `BaselineService::goldenActuals()` and compared to the cent (amounts) or exactly (counts). A golden with no committed value fails, and so does a committed golden the run no longer produces, so a value can never be taken from the run it tests and a figure cannot silently disappear.

**Proof that it reproduces.** Each commit was bootstrapped twice from a clean worktree (a fresh checkout, so the committed files are exactly what a client clones) into two empty databases: 467ef7f (A1, B1), c4acec4 (A2, B2) and bece21a (A3, B3, run in parallel). Each time the second run passed every verify check against goldens set from the first (bece21a: 98 checks, 0 FAIL), and every table of the two databases was compared over all columns except surrogate ids and run timestamps: the figures were identical. What differs between two runs is only what identifies a run, never a result: run UUIDs (`ecl_calculation_run_id`, `pd_segment_run_id`, the segment and projection run ids, and the transition-matrix comment that quotes the run id), the times stored inside `governed_scenario_sets.overlays_at_approval`, `fli_run_snapshots.inputs` and the audit log, and the seeded administrator's password hash (its salt is random) and remember token.

**The golden numbers** (set on 14 October 2026 from run A3 of commit bece21a, database `maiic_ifrs9_final_a3`; reproduced by run B3):

| Golden | Value |
|---|---|
| Take-on worksheet (CV_10): term loans with take-on postings on 31 Jul 2024 | 77 |
| ... whose principal taken on (301) = NEWDISBURS_AMT / opening interest (303) = TOTAL_INT_CHARG / opening recoveries (305) = TOTA_REPAY_UPTO, to the cent | 76 / 76 / 72 |
| Ledger copy CUMVOUCH_BK240824 (CV_04a) to 31 Dec 2023 against the GL openings of 1 Jan 2024 (CV_14), 4 loan GLs, sum of differences | 0.50 |
| Loan book 31 Dec 2025, MAIIC's loan GLs (123 rows); the Finance loan listing is 14,841,212,779.53 | 14,841,212,779.54 |
| Loan book 31 Dec 2025, Mega Farm programme (3,671 accounts with a balance) | 54,498,560,819.60 |
| Loan book 30 Sep 2026, MAIIC's loan GLs (134 rows) | 23,189,580,235.43 |
| Loan book 30 Sep 2026, Mega Farm programme (3,618 accounts with a balance) | 56,180,761,113.43 |
| Mega Farm ledger against the Loan Book Report, account-months: tie / the report prints a credit as a debit (the ledger stands) / the report stands | 77,842 / 180 / 1 |
| Ledger write-offs on MAIIC loans (WriteOffClassifier): 22 postings | 60,778,466.86 |
| ECL 31 Dec 2025, MAIIC's book: undiscounted / discounted at the EIR (booked) | 4,633,401,758.31 / 3,015,683,463.14 |
| ECL 31 Dec 2025, discounted by stage 1 / 2 / 3 (loans 15 / 2 / 49) | 5,533,881.06 / 444,343,201.51 / 2,565,806,380.57 |
| ECL 31 Dec 2025, loans not discounted: "A locked original EIR is unavailable." | 57 |
| ECL 31 Dec 2025, Mega Farm programme | declined by the engine (no PD on MAIIC's own agricultural book that month) |
| ECL 30 Sep 2026, MAIIC's book: undiscounted / discounted at the EIR (booked) | 10,654,569,637.48 / 6,473,181,447.94 |
| ECL 30 Sep 2026, discounted by stage 1 / 2 / 3 (loans 42 / 3 / 51) | 266,143,422.79 / 179,420,874.77 / 6,027,617,150.38 |
| ECL 30 Sep 2026, loans not discounted: locked original EIR unavailable / remaining contractual horizon unavailable | 37 / 1 |
| ECL 30 Sep 2026, Mega Farm programme / MAIIC's 5 percent share | 44,661,819,737.68 / 2,233,090,986.88 |
| Month records (FLI module), December 2025 to September 2026: recorded / whose scenario-weighted discounted allowance equals the booked | 10 / 10 |
| Month record 31 Dec 2025 and 30 Sep 2026: booked = weighted, discounted | 3,015,683,463.14 and 6,473,181,447.94 |
| EIR contracts: in the contract master / solved and locked / blocked | 144 / 12 / 115 |
| Revenue 2024: EIR interest / cash received / roll-forward rows | 12,056,553.50 / 55,983,930.53 / 14 |
| Revenue 2025: EIR interest / cash received / roll-forward rows | 361,071,124.37 / 432,472,897.80 / 64 |
| Revenue 2026 (to September): EIR interest / cash received / roll-forward rows | 246,326,793.41 / 1,098,182,610.86 / 41 |
| RBM return 30 Sep 2026, paragraph 12 loss list: facilities / balance | 10 / 1,510,956,083.45 |
| Compliance register rows loaded from the committed workbooks | 84 |
| Days past due, E-Banker's overdue run (OD_01, 2,259 month-end rows) against staging: account-months in both / agree / differ / by more than 30 days / not in the loan book | 2,241 / 1,782 / 459 / 229 / 18 |

`golden.json` holds 77 values (the table groups them). A revenue month that cannot be opened from a booked prior-month allowance is refused and writes no row (and the month after it is refused too), so the roll-forward row counts above are the golden for the refused months. The goldens are the figures of the committed data and code; they are not MAIIC approvals and they change only by a new commit that resets `golden.json` from a fresh run (the file names the date, commit and database it was set from).

**Options worth knowing (added 14 October 2026).**

- `--to` is the last month the bootstrap builds and runs. Left out, it is the last month-end in the packs: the latest date of E-Banker's stored Loan Book Report runs landed (queries P2_08, M09_03 and MF_05), or, with none, the month of the last ledger posting landed. A month after the last pack is never estimated. `--from` (default `2024-07`, the take-on) is the first.
- `--inputs` (default `docs/bootstrap`) is relative to the repository folder: the bootstrap reads `base_path(--inputs)`, so `--inputs=docs/bootstrap` and `--inputs=storage/app/my-inputs` work, and an absolute path such as `D:\packs` does not (it would be read as a folder inside the repository).
- The "N files" and "N files landed" lines count every file the step went through, including a file an earlier run had already landed: the door recognises it by its SHA-256 (or the pack by the hash of its manifest) and does not load it twice, but it is still counted. A pack already landed prints `ALREADY_LANDED` with the load that holds it; for a trial balance, "21 files landed, 0 quarantined" means 21 files are held, whether this run or an earlier one landed them.

## Adding next month's pack

Each month MAIIC's export produces one more E-Banker pack: the extract files plus a `manifest.json`. There are two ways to bring it in.

**On a running system** (the usual way), through the same door and gates as every pack:

1. Land the pack by the route in force on the Governance Centre ("How E-Banker data arrives"): route 1, upload the zipped pack on Imports & Feeds, E-Banker Feed; route 2, the scheduled export writes the pack (or its zip) to the folder named by `EBANKER_FEED_FOLDER` and `php artisan eir:poll-feed-folder` (scheduled daily at 06:30, and the Maintenance job "Refresh the data") lands it; route 3, the export script posts the zip to `POST /api/ebanker-feed/pack` with the feed token, and the poller lands it with `--also-route-3`. The poller refuses unless route 2 is in force and the API unless route 3 is (or the setting opens routes 1 to 3 together); the upload of route 1 is always open, for a missed month or a correction. The API inbox and the Maintenance run history are kept per database (`storage/app/ebanker-inbox/<database>`, `storage/app/command-runs/<database>`).
2. Build the month's loan book (Maintenance, "Build the loan book", or `php artisan eir:build-loan-books YYYY-MM`) and have a second person approve the build on Imports & Feeds, Loan Book Builds.
3. Run the month-end (Maintenance, "Run the month-end calculations": `php artisan maiic:task month-end YYYY-MM --user=<id>`). A month with no loan book is refused by name at the start, and so is staging it on its own (`eir:stage`).

**For the bootstrap** (so a clean install carries the month too): commit the pack as a folder `docs/bootstrap/ebanker-pack-YYYY-MM-DD/` (the date it was received; a second pack of the same day takes a suffix, as `ebanker-pack-2026-10-12-p5`) holding the CSV files exactly as received and a `manifest.json` with the fields of the existing manifests:

| Field | What |
|---|---|
| `pack` | The pack's name, e.g. "MAIIC E-Banker pack 11, received 4 November 2026" |
| `assembled` | The date the pack was put together (YYYY-MM-DD) |
| `period` | The month-end it carries (YYYY-MM) |
| `source` | Who ran which query file, from which E-Banker schema, when, and how dates and amounts were exported |
| `date_format` | The date format of the files, `m/d/Y` while RUN 0 does not take (D23), else `Y-m-d` |
| `scope_scheme_ids` | The schemes the pack is limited to; empty for the whole book |
| `accepted_exceptions` | Gate findings accepted for this pack, each with its reason |
| `not_landed` | Every received file that is deliberately not landed: `received_as` and `reason` |
| `classification` | How the data is to be handled, e.g. "MAIIC client data: borrower names and balances. Private repository; access as for the production database." |
| `files` | One entry per CSV: `file`, `query_id` (registered in `EbankerQuerySeeder`), `kind` (what it holds), `rows` (data rows, header excluded), `sha256` (of the file as received), `bytes`, `received_as` |

A pack too large to commit as files goes in `docs/bootstrap/packs/ebanker-pack-YYYY-MM-DD.zip` with its folder and manifest inside (as pack 4). The bootstrap lands the packs in the order of their folder names, after the first two; the pack inventory check then proves every committed file against its manifest (SHA-256 and rows), and every CSV in the folder must be in the manifest or in its `not_landed` list. A query id that is not registered quarantines the pack. With the pack committed, `--to` moves to the new month by itself; re-set `golden.json` from a fresh run only for the figures the new month adds or changes, and say why in its note.

**A query with no key column** (CV_07, GL_02, P3_12, DD_09, DD_10 and the other probes and data-dictionary queries) is landed by content since 14 October 2026: a re-sent file lands no new row, and the pack carries the query's whole extract, so a row the new pack no longer carries is superseded (never deleted). Until then a re-sent CV_07 added its 47 rows again as current rows; the migration of 14 October 2026 re-keyed the rows already landed and superseded such duplicates.
