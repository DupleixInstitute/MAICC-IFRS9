---
kicker: MAIIC IFRS 9 and EIR system
title: Request for read-only access to the E-Banker database
subtitle: For MAIIC ICT, and for Virtual Galaxy's consent
date: 14 October 2026
prepared: Edward Mazibuko, Director, Data Analytics, Dupleix Institute
people: To Barry Makumba (ICT, MAIIC); copy Dr Thomson Kumwenda (CFO, MAIIC)
status: Draft for discussion
---

## 1. What we are asking for

A **read-only database account** on the E-Banker Oracle database, usable from the IFRS 9 system's server over the VPN that is already in place, so that the monthly extracts now run by hand in SQL Developer are run by the system itself on a schedule. Nothing else changes: the same queries, the same tables, the same checks on arrival.

Specifically:

1. An Oracle user with `CREATE SESSION` and `SELECT` on the tables listed in the annex (schemas VGCBS and MAIIC_ORA_LIVE), and nothing else: no insert, update, delete, DDL, or execute on any package or procedure.
2. A firewall rule allowing that one server (IP to be given by Dupleix) to reach the database listener (port 1521 unless E-Banker uses another) over the VPN.
3. The connection details (host, service name, port), and a short test window with ICT present.
4. If E-Banker runs a standby or reporting replica, access to that rather than production. The queries are read-only either way; the replica simply removes any question of load on the live system.
5. **The same account, first, on E-Banker's test environment** (the UAT or test copy of the database, if MAIIC has one; otherwise a restored copy of a recent backup). Everything is proved there before the production account is switched on (section 7).

## 2. Why

Since 7 October the system has been fed by packs that Barry runs in SQL Developer and exports by hand. That works, and it stays as the fallback, but each pack has needed a second run for one of three reasons: the date settings not applied in the worksheet, an export stopping early at SQL Developer's 100 MB grid, or a table too large to export at all (the overdue run, which writes one row per account per day). A scheduled read by the system removes all three: it sets its own session, has no grid, and reads in pages.

The system already records, for every file it lands, which query produced it, the row count, a hash, and the checks it passed. A direct read produces the same record, so the audit trail Deloitte will see is the same whether a month came by hand or by schedule.

## 3. Background: the five feed routes

The system takes E-Banker data through one door, the landing zone, whatever road it arrives by. Every route produces the same thing (a pack: one file per registered query plus a manifest of row counts and hashes) and every pack passes the same checks before anything is used. Moving from one route to another therefore changes nothing in the figures; it changes only who runs the queries and how the files travel. Which route is in force is a governed setting in the system, and the manual route always stays available for a missed month or a correction.

| Route | How it works | What it needs | Status |
|---|---|---|---|
| **1. Manual pack** | Barry runs the versioned queries in SQL Developer, exports one CSV per query and saves them to the shared OneDrive folder; the system's Feed screen lands them | Nothing new | **In use since 7 October.** Every pack to date has come this way. Exposed to the three problems in section 2 |
| **2. Scheduled export at MAIIC** | A script (Oracle SQLcl, written by Dupleix) runs the same queries on a MAIIC server by Windows Task Scheduler on the first working day after month-end, and writes the pack to a shared folder; the system polls the folder at 06:30 and lands it | A read-only Oracle account for the script; one shared folder; Barry installs the task | **Built on the system side** (the folder poller runs daily); waits for the account and the MAIIC-side task |
| **3. Push by API** | The same script sends the pack to the system over HTTPS with a token, instead of through a folder | A token issued by the system; outbound HTTPS from the MAIIC server | **Built on the system side** (the endpoint exists); waits for the token and the script. The alternative to route 2 where a shared folder is awkward |
| **4. Direct read over the VPN** (this request) | The system connects to E-Banker's Oracle database read-only over the existing VPN, runs the registered queries itself on schedule and lands the result; the pack and manifest are generated inside the system, so the audit trail is the same as for a manual pack | The read-only account and firewall rule of section 1; the Oracle client on the system server; the vendor's consent (section 6); Dr Thom's authorisation | **Not built yet**: about a week once the account exists. The destination: no person in the loop, no export limits |
| **5. Vendor view or API** | Virtual Galaxy provides a reporting schema of views, or an endpoint, carrying the same columns; route 4 reads that instead of the tables | A change request to the vendor, likely paid | **Not built.** Only if the vendor will not allow route 4 on the tables themselves |

Routes 2 and 4 use the **same read-only account**. That is why the sequence in section 7 starts with route 2: it proves the account, the queries and the month-end timing with no new software on either side, while the vendor's consent for route 4 is obtained.

## 4. The landing zone technique

**What it is.** A landing zone is a holding area inside the system where data from a source system is stored exactly as it arrived, before anything is calculated from it. Nothing in the IFRS 9 system reads E-Banker's files directly: every engine reads only what has passed through the landing zone. It is the single door between E-Banker and the system's figures, whichever of the five routes delivered the data.

**Why it is built this way.**

- **One set of checks for every route.** The checks are written once and every pack passes them, so a month that came by hand and a month read directly are held to the same standard.
- **Nothing is ever overwritten.** E-Banker changes its own records (a posting reversed, an account re-graded). If the system overwrote what it held, a figure reported last quarter could not be reproduced. The landing zone keeps every version of every row with the time it was superseded, so any reported month can be re-derived exactly as it was.
- **Bad data stops at the door.** A pack that fails a check is quarantined whole. Its rows are kept so the problem can be seen, but no engine reads them, so a short export or a wrong date format can never reach the ECL or the revenue figures.
- **The audit trail is built in.** For each figure the system can say which pack, which file, which query version and which row it came from, and show the hash proving the file is the one E-Banker produced.
- **Changing the route changes nothing downstream.** Because every route ends in the same pack and the same door, moving from the manual route to the direct read is invisible to the engines; it only removes the manual steps.

**How it works, step by step.**

1. **The pack arrives.** One CSV per registered query and a manifest stating, for each file, the query identifier and version, the row count, the SHA-256 hash and the date format. Under the direct read the system writes the pack and the manifest itself.
2. **The pack is identified.** The manifest's hash is checked against every pack already loaded. A pack already landed is reported as such and nothing is loaded twice.
3. **The gates run.** Each file: present, hash and row count as declared, key unique, query version the one in the register, every date and amount parses. Across the files: every ledger account is in the account master, the balance history ties to the ledger, every account in scope has its month-end row, the fee GL movement ties to the trial balance.
4. **Landed or quarantined.** If every error-level gate passes, the pack is LANDED; otherwise it is QUARANTINED with each failure named by file and row. Warnings are recorded but do not stop the pack.
5. **The rows are stored, versioned.** Each row is stored verbatim under the E-Banker table's own key with a hash of its content: a new key becomes version 1, an unchanged row is skipped, a changed row becomes the next version and the earlier one is marked superseded, never deleted.
6. **The watermark is recorded.** For each history table, the highest key loaded. The next read starts above it.
7. **The builds read.** The monthly loan book, the contract inputs, the fees and the trial balance lines are built from landed rows only, and each build records which loads it read. The engines (staging, PD and LGD, ECL, EIR and revenue, the GL reconciliation, the regulatory return) run on the builds.

The landing zone is four tables in the system's database: the **query register** (each query's identifier, source table, key, version, SQL and its hash); the **loads** (one record per pack: its hash, route, status, gate results and watermarks); the **pack files** (one record per file: hash, rows declared, rows new, versioned and unchanged); and the **raw rows** (every row of every file, all versions).

![Figure 1. The five feed routes and the landing zone. Every route produces the same pack; the landing zone checks it, stores it versioned, and only then do the builds and engines read it.](Feed routes and landing zone - diagram.png)

**How each route works, end to end.** The routes differ only in who runs the queries and how the files travel. Once a pack reaches the landing zone, every route gets the same gates, the same versioned storage and the same audit trail, which is the point of the design.

| Route | Who runs the queries | Where and when | How the pack travels | What starts the landing | Recorded in the load as |
|---|---|---|---|---|---|
| **1. Manual pack** | Barry, in SQL Developer | His workstation, on request | CSVs saved to the shared OneDrive folder, then uploaded on the Feed screen | A person clicks Land | `ROUTE_1_MANUAL`, with the user's name |
| **2. Scheduled export** | A script, under the read-only account | A MAIIC server, first working day after month-end (Task Scheduler) | Written to a shared folder | The system polls the folder at 06:30 and lands what it finds | `ROUTE_2_FOLDER` |
| **3. Push by API** | The same script | A MAIIC server, same schedule | Sent over HTTPS with a token | Arrival at the endpoint | `ROUTE_3_API` |
| **4. Direct read (this request)** | The IFRS 9 system itself, under the read-only account | The system's server over the VPN; monthly, or daily for the overdue run | Never leaves the system: the pack is built in place | The system's scheduler | `ROUTE_4_DIRECT` |
| **5. Vendor view or API** | The IFRS 9 system, reading the vendor's views or endpoint | As route 4 | As route 4 | The system's scheduler | `ROUTE_5_VENDOR` |

## 5. What the account will do, and will not do

| Will | Will not |
|---|---|
| Run the registered SELECT statements (about sixty, all in MAIIC's copy of the repository) | Write, change or delete any row |
| Run once a month, on the first working day after month-end, between 06:00 and 07:00; the overdue table optionally daily | Run stored procedures, create objects or change settings |
| Read a few megabytes a month: the loan accounts of the 18 MAIIC schemes and the 8 Mega Farm schemes, their ledger postings, balances, instalment charts, overdue computations and the trial balance | Read customer accounts outside those schemes, or any table not in the annex |
| Connect from one named server over the existing VPN, with the password held by MAIIC ICT and rotated on MAIIC's schedule | Connect from anywhere else, or be used by a person |

MAIIC ICT administers the account, can see every session in the database's own audit, and can revoke it at any time. The manual route keeps working regardless.

## 6. What we need from Virtual Galaxy

The account itself is within MAIIC ICT's gift. We ask that the vendor confirms, in writing:

1. That a read-only account used in this way does not affect the support or warranty terms of the E-Banker agreement.
2. Whether access should be to production or to a standby or reporting replica, and if a replica exists, its refresh frequency.
3. Any audit or logging the vendor requires on such an account.
4. Whether any cost attaches.
5. If direct read access is not acceptable to the vendor: a quotation for the alternative, a reporting schema of views (or a scheduled export the vendor runs) carrying the columns of the tables in the annex. The system reads either in the same way.

Two further items are vendor questions in any case and can go in the same message: the **Reschedule Report** (restructure history: LOAN_RESCHEDULE_DETAILS is empty while OPENING_BAL shows eight restructures), and the **write-off record** (LOAN_WRITEOFF_MASTER is empty while the ledger carries type 300 write-off postings).

## 7. Proposed sequence: test environment first, then production

Nothing touches production until it has worked end to end on the test environment. The system has its own test databases (separate from the live one, already used for every build and audit to date), so the test stage pairs E-Banker's test environment with the system's test database: no live data on either side is read or written until stage C.

| Stage | Step | Who | When |
|---|---|---|---|
| **A. Authorise** | Dr Thom authorises the request; Barry raises section 6 with the vendor | MAIIC | This week |
| **B. Test environment** | Read-only account created on E-Banker's test environment (UAT, or a restored recent backup), with the grants in the annex; firewall rule from the system's test server | MAIIC ICT | On authorisation |
| | Route 2 on test: the scheduled script run against the test database, the pack written to a test folder, landed into the system's test database | Dupleix writes the script, Barry installs the task | Within two weeks of the account |
| | Route 4 on test: the Oracle client and connection installed on the system's test server; the system runs the queries itself against the test database | Dupleix | On the vendor's confirmation; about a week |
| | **Acceptance on test**, signed off by Barry and Dupleix: (1) every registered query runs under the account and nothing outside the annex can be read; (2) a month read by route 4 lands with the same rows, hashes and check results as the same month landed by hand (route 1); (3) a re-read of the same month lands as all unchanged, with no duplicates; (4) a deliberately broken file is quarantined and nothing downstream moves; (5) the read takes under 15 minutes and the database shows no measurable load; (6) a backup is restored and re-read from its watermarks | Dupleix and MAIIC ICT | At the end of stage B |
| **C. Production** | The same account created on production (or the replica, per the vendor); the connection pointed at it; the route setting changed to route 4 under maker-checker | MAIIC ICT; Dupleix; approver in the Governance Centre | After acceptance on test |
| | **Parallel run**: two month-ends read by route 4 alongside the manual pack; each pair must tie row for row before the manual pack is stood down | Dupleix and Barry | Two month-ends |
| | Switch: route 4 in force; route 1 kept as the fallback | MAIIC | After the parallel run |

If the vendor declines route 4 (section 6, item 5), stages B and C run the same way on route 2, which needs no vendor change, or on the vendor's views under route 5.

## 8. How the queries are refreshed

Every extract is a **registered query**: the system holds a register (`ebanker_queries`) with, for each query, its identifier (P1_01, P2_08, OD_01 and so on), the E-Banker table it reads, the table's own key column, the account and date columns, a version number, the SQL text and its hash, and whether the table is read incrementally or whole. The register is seeded from the repository, so a query is changed by changing the repository, not by editing a worksheet.

Two kinds of table, two refresh rules:

- **Master tables come whole each time** (account master, loan master, scheme master, instalment chart in force, security, guarantors, rate slabs, PLR history). The whole table is read at each run and compared row by row with what is held; only rows that are new or changed are stored (section 8). The size is small (hundreds of rows).
- **History tables are read incrementally** (ledger postings, balance history, loan-book runs, status history, interest rows, the overdue run). After every landing the system records a **watermark** per query: the highest source key loaded (the ledger's CUMVOUCH_DET_ID, the overdue run's OVERDUE_DET_ID). The next run reads only rows above the watermark. Under the manual route the watermark is printed on the pack sheet so the next query starts from it (the "ids ledger 439206 / balances 363004 / runs 808196" lines on the pack 3 sheet); under the scheduled and direct routes the system applies it itself. Nothing is ever re-read in full unless asked.

Timing: the month-end pack is read on the first working day after month-end (the loan-book run and the trial balance are produced by then); the overdue run can be read daily or at month-end only. A month can be re-read at any time (a correction, a late posting) and the same rules apply: new rows are added, changed rows become a new version, unchanged rows are left alone.

Changing a query (a column added, a filter corrected) means a **new version in the register**. Each pack's manifest states the version of every file in it; a file whose version is not the one the register holds is refused (the `query_version` gate), so an old script cannot feed the system unnoticed. The SQL hash lets anyone confirm that the script Barry ran is the one in the register.

## 9. How duplicates are handled

Three levels, all automatic, none of them overwriting anything:

| Level | Rule | What happens |
|---|---|---|
| **Pack** | Each pack's manifest is hashed (SHA-256) | A pack already landed is recognised and reported as ALREADY_LANDED; nothing is loaded twice. A pack refused earlier and offered again is a fresh attempt, recorded whole |
| **File** | Each file's hash and row count must match its manifest; the key column must be unique within the file | A short or altered file is refused with the row named; a key that repeats inside a file refuses the file (a sign the export doubled up) |
| **Row** | Every row is stored under the source table's own key (CUMVOUCH_DET_ID, OVERDUE_DET_ID, ACCOUNT_BAL_MST_ID …) with a hash of its content | Same key, same content: counted as unchanged and skipped. Same key, different content: stored as **version 2**, the earlier row marked superseded with the time, never deleted. New key: stored as version 1 |

So an overlap between two extracts of the same table (the month-end overdue rows in OD_01 and the daily rows in OD_02; a ledger re-run that re-pulls keys already held) lands once. Every landing records, per file, how many rows were new, versioned and unchanged, so a pack that was entirely duplicate shows as "0 new, 0 versioned, N unchanged" rather than as a silent double count. And because superseded rows are kept with their dates, any month can be re-derived exactly as it looked at the time it was first reported.

## 10. Governance around the feed

- **Gates before anything moves.** Every pack passes the checks in the manifest (file exists, hash, row count, key unique, dates parse, amounts parse, query version known) and the checks across files (every ledger account is in the account master; the balance history ties to the ledger; every in-scope account has its month-end loan-book row; the fee GL movement ties to the trial balance). An ERROR-level failure **quarantines the whole pack**: its rows are kept so the refused file can be opened and examined, but no reader of the system sees them and nothing downstream (loan book, staging, ECL, EIR) moves. WARNING-level results are recorded against the load, not enforced.
- **Only landed loads are read.** Every build reads from landed loads only, and records which loads it read, so a figure in a report traces to the pack and the file it came from.
- **Audit trail.** Each landing and each quarantine writes an audit entry with the route, who or what loaded it, the files, the gate results and the watermarks. The query register, the manifest and the file hashes let an auditor re-run the check that the file landed is the file E-Banker produced.
- **Period lock.** Once a reporting month is locked (maker-checker, Governance Centre), its engines do not re-run on new data; a late pack still lands, and its effect is taken in the month that is open, with the difference shown. Landing never bypasses the lock.
- **The route is a governed setting.** Which route is in force (manual, scheduled folder, API push, direct read, vendor view) is a Governance Centre setting changed under maker-checker with an effective date. The manual route stays available whichever is in force, so a missed month or a correction can always be loaded by hand.
- **Changes to the queries** go through the repository (version, hash, review) and the register, not through SQL Developer; the `query_version` gate enforces it.
- **The account** is MAIIC ICT's: created, held, rotated and revocable by MAIIC; used by one server; visible in Oracle's own session audit.

## 11. Data storage and backup

**Where the data sits.** Nothing is stored on the E-Banker side: the account only reads. What it reads is stored in the IFRS 9 system's own MySQL database, on the system's server, in three layers:

| Layer | What it holds | Size today (measured 14 Oct 2026) |
|---|---|---:|
| Landing zone (`ebanker_raw_rows`, `ebanker_loads`, `ebanker_pack_files`) | Every row of every extract, verbatim, with every superseded version kept; one record per pack with its hash, gates and watermarks | 85 MB for about two years of the MAIIC book (82,000 rows over 23 packs) |
| Built tables (loan books, EIR schedules, ECL, GL reconciliation) | Derived monthly from the landing zone; can be rebuilt from it | 24 MB |
| The pack files themselves (CSV and manifest, under the manual and folder routes) | The files as E-Banker produced them, kept for evidence | 87 MB received to date on OneDrive |

**Growth.** On the MAIIC book a month adds roughly 3 to 5 MB to the landing zone. Two items change the scale and are planned for:

- **The Mega Farm ledger** (about 300,000 postings, not yet landed in full) adds an estimated 300 to 400 MB once, then a few MB a month.
- **The daily overdue run** (OD_02) adds about 45,000 rows a year for the MAIIC schemes (an estimated 120 to 150 MB a year). The Mega Farm programme has 3,675 accounts, so its daily rows would be about 1.3 million a year; for Mega Farm the system reads the **month-end rows only**, and the daily read stays limited to the MAIIC schemes.

On that basis the database stays **under 2 GB for the first five years**. The server needs **at least 20 GB of free disk** for the database, its backups held locally and the pack archive, with an alert at 80 percent full.

**Retention.** Landed rows and pack records are kept for the life of the system and never deleted: the superseded versions are what let a reported month be re-derived exactly as it was, which the auditors rely on. This exceeds the seven-year minimum for accounting records. Pack files may be archived off the server after the month they belong to is locked, because the landing zone holds every row and every file's hash.

**Backup.**

| What | How often | Kept | Where |
|---|---|---|---|
| Full database dump (compressed; about 10 MB today) | Nightly, after the 06:30 feed run has finished | 30 daily, 12 month-end, 7 year-end copies | On the server, and copied off it to MAIIC's backup store (a second site or MAIIC's cloud) |
| Month-end snapshot, taken when the month is locked | Monthly | Permanently | Off the server, alongside the month's pack files |
| The pack files and manifests | As each pack lands | Permanently | MAIIC's document store (OneDrive today) |
| The query register and the system code | On every change | The full history | The private repository, which MAIIC holds a copy of |

Every backup is **encrypted at rest**: the data is customer loan data. A **restore test** is run each quarter: the latest dump restored to a separate database, the bootstrap's tie checks run against it, and the result recorded. The database password, the read-only E-Banker password and the backup encryption key are held by MAIIC ICT, not by Dupleix.

**If the system's database is lost**, it is restored from the last nightly dump; anything landed after it is re-read from E-Banker, because the watermarks in the restored database say where to start, and the duplicate rules (section 9) make a re-read safe.

## 12. Contact

Edward Mazibuko, Director, Data Analytics, Dupleix Institute. Technical questions from the vendor can come to us directly, copied to Barry.

## Annex: tables the account needs SELECT on

Schema **VGCBS** unless stated. The query identifiers are the ones in the system's query register and on every pack sent to date. "Read" is whole (W), incremental by key (I) or once.

| Table | Used by | Read | What it carries | How the system uses it |
|---|---|---|---|---|
| ACMASTER | P1_02, MF_01 | W | Account master | Defines the accounts in scope (by scheme and loan GL); the gate that every ledger posting belongs to a known account; customer name, status and open date on the loan book |
| ACLOANMASTER | P1_03, MF_02 | W | Loan master: sanction, rate, tenor, moratorium | Contract terms for the EIR (sanction amount, rate basis, tenor, moratorium months, instalment mode); facility class for staging thresholds |
| SCHEME_MASTER | SM_01 | W | Scheme definitions | Product group per scheme; the interest basis for partly drawn loans; the meaning of a blank moratorium type |
| CUMVOUCH | P1_01, GL_01, GL_03, GL_05, M09_01, MF_03, ZF_01, ZF_02 | I | Ledger postings | The ledger-derived loan book (method B): disbursements (type 301), interest (303, 120), receipts (305, 306, 343, 900, 901), write-offs (300), settlements in kind (401); the EIR's actual cash flows; the fee GL legs; the GL reconciliation |
| CUMOVUCH_OLD | CV_01 | once | Older ledger copy (profile only) | Pre-migration history, read once to attach older postings to today's loans when a map exists |
| ACCOUNT_BALANCE | P2_09, M09_02, MF_04, ZF_03 | I | Balance history at month-ends | The tie between the ledger's running balance and E-Banker's own month-end balance (gate, within 1.00); carrying amount check per account-month |
| LOAN_BOOK_DETAILS_ALL | P2_08, M09_03, MF_05 | I | Stored Loan Book Report runs | The stored loan book (method A); the arrears buckets and overdue date that stage every loan (days past due); the tie that every in-scope account has a month-end row |
| FIXLOAN_INST_CHART | P2_10 | W | Instalment chart in force | Contractual cash flows for the EIR schedule; the four-missed-instalments Stage 3 trigger (instalments due against receipts) |
| MULTI_INST_DETAILS | P3_11 | W | Instalment plans | Step-up and seasonal instalment patterns for the EIR schedule |
| LOAN_DISBURSMENT_SCHEDULE | P3_12 | W | Disbursement schedules | Tranche timing for part-drawn facilities: when undrawn commitments become exposure |
| ACCOUNT_STATUS_HISTORY | P3_13, MF_06 | I | Status history | Status at each month-end (active, closed, written off) for derecognition and for the cure period |
| OVERDUE_DETAILS | OD_01, OD_02 | I | The overdue run per account and date | The independent check on days past due and the instalment trigger; the RESCHEDULEMENT columns as restructure evidence; a per-account day count for the regulatory return's bands |
| INTEREST_SLAB_MASTER, INTEREST_SLAB_DETAILS | P3_15, P1_04, MF_07 | W | Rate slabs | The rate set-up per account in applicable-from order: the contractual rate and its resets for the EIR |
| INTEREST_SUMMARY, DAILY_INTEREST_ACCRUAL, FORGIVE_INTEREST_DETAILS | P1_05, P3_14, P3_16 | I | Interest computed, accrued, waived | E-Banker's own interest per period against the EIR interest; waived interest as a modification; the suspense on non-performing loans |
| PLR_MASTER | P2_06 | W | Prime lending rate history | Reset dates and rates for floating loans (B5.4.5 resets against modifications) |
| LOS_DISB_CHARGES_POST, LOS_REQUEST_DETAILS | P2_07, MF_08, P2_13 | W | Fees at disbursement; loan origination payloads | Fees in the EIR (integral or not, under the fee rulebook); the origination terms the offer letter is checked against |
| CUST_SECURITY_DETAILS, GUARANTER_MASTER | P2_11, P2_12 | W | Security and guarantors | LGD inputs (collateral type, value, realisation); the security line of the regulatory return |
| LOAN_RESCHEDULE_DETAILS, LOAN_WRITEOFF_MASTER | RS_01, WO_01 | I | Restructures and write-offs (both empty today) | The restructured-loan register and the 10 percent modification test; write-off derecognition, when E-Banker starts filling them |
| GL_OPENING_BALANCE, GL_OPENING_BALANCE_BK240824 | GL_02, CV_14 | W | GL opening balances | GL names and opening positions for the reconciliation; the anchor of the pre-migration ledger to the GL |
| TRIAL_BALANCE_HISTORY | TB_02, TB_03 | I | Trial balance by month | The loan GLs and fee GLs tied to the loan book and the fee legs each month (today Finance uploads the trial balance file; this would replace the upload) |
| OPENING_BAL, ACCOUNT_NO_MAPP, NEW_ACMAP, T_ACCOUNT, T_ACCOUNTBALANCE, TEMP_MASTER, G_ACMASTER, ACMASTER_MW, ACMASTER_NF | CV_09 to CV_13 | once | Migration tables of July 2024 | The take-on worksheet behind the 31 July 2024 opening postings (OPENING_BAL) and the old-to-new account link; read once, already received by hand |
| MAIIC_ORA_LIVE.CRM_ACMASTER_05082024 | CV_12d | once | Account master copy of 5 August 2024 | Account numbers as they stood at the migration, to map renumbered loans |
| Data dictionary views: ALL_TABLES, ALL_TAB_COLUMNS, ALL_CONSTRAINTS | DD_01 to DD_03 | W | Structure | Detects a vendor change to a table between months before it reaches a query (a column renamed or dropped refuses the pack, named) |

Row filters are applied in every query to the MAIIC and Mega Farm scheme identifiers (84 to 95, 140 to 145; 96 to 103), so no other customer's data is read.
