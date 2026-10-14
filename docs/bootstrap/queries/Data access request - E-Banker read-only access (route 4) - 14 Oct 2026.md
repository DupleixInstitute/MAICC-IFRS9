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
5. **The same account, first, on E-Banker's test environment** (the UAT or test copy of the database, if MAIIC has one; otherwise a restored copy of a recent backup). Everything is proved there before the production account is switched on (section 9).

**Please plan this now.** The build is about a week, but approvals, the vendor's reply, a test copy of E-Banker and two month-ends of parallel running set the pace. To have the route proven on the November month-end and run in parallel on the December 2026 year-end, the request needs approval by **Friday 23 October 2026** (timetable in section 9.2).

## 2. Why

Since 7 October the system has been fed by packs that Barry runs in SQL Developer and exports by hand. That works, and it stays as the fallback, but each pack has needed a second run for one of three reasons: the date settings not applied in the worksheet, an export stopping early at SQL Developer's 100 MB grid, or a table too large to export at all (the overdue run, which writes one row per account per day). A scheduled read by the system removes all three: it sets its own session, has no grid, and reads in pages.

The system already records, for every file it lands, which query produced it, the row count, a hash, and the checks it passed. A direct read produces the same record, so the audit trail Deloitte will see is the same whether a month came by hand or by schedule.

## 3. Background: the five feed routes

The system takes E-Banker data through one door, the landing zone, whatever road it arrives by. Every route produces the same thing (a pack: one file per registered query plus a manifest of row counts and hashes) and every pack passes the same checks before anything is used. Moving from one route to another therefore changes nothing in the figures; it changes only who runs the queries and how the files travel. Which route is in force is a governed setting in the system, and the manual route always stays available for a missed month or a correction.

| Route | How it works | What it needs | Status |
|---|---|---|---|
| **1. Manual pack** | Barry runs the versioned queries in SQL Developer, exports one CSV per query and saves them to the shared OneDrive folder; the system's Feed screen lands them | Nothing new | **In use since 7 October.** Every pack to date has come this way. Exposed to the three problems in section 2 |
| **2. Scheduled export at MAIIC** | A script (Oracle SQLcl, written by Dupleix) runs the same queries on a MAIIC server by Windows Task Scheduler on the first working day after month-end, and writes the pack to a shared folder; the system polls the folder at 06:30 and lands it | A read-only Oracle account for the script; one shared folder; Barry installs the task | **Built on the system side** (the folder poller runs daily); waits for the account and the MAIIC-side task |
| **3. Push by API (the endpoint)** | The same script zips the pack and sends it to the system's endpoint, `POST /api/ebanker-feed/pack`, over HTTPS with a token, instead of through a folder; the system replies at once and lands the pack at its next poll (section 3.1) | A token issued by the system; outbound HTTPS from the MAIIC server to the system's server | **Built on the system side** (the endpoint exists and is tested); waits for the token and the script. The alternative to route 2 where a shared folder between the two servers is awkward |
| **4. Direct read over the VPN** (this request) | The system connects to E-Banker's Oracle database read-only over the existing VPN, runs the registered queries itself on schedule and lands the result; the pack and manifest are generated inside the system, so the audit trail is the same as for a manual pack | The read-only account and firewall rule of section 1; the Oracle client on the system server; the vendor's consent (section 7); Dr Thom's authorisation | **Not built yet**: about a week once the account exists. The destination: no person in the loop, no export limits |
| **5. Vendor view or API** | Virtual Galaxy provides a reporting schema of views, or an endpoint, carrying the same columns; route 4 reads that instead of the tables | A change request to the vendor, likely paid | **Not built.** Only if the vendor will not allow route 4 on the tables themselves |

Routes 2 and 4 use the **same read-only account**. That is why the sequence in section 9 starts with route 2: it proves the account, the queries and the month-end timing with no new software on either side, while the vendor's consent for route 4 is obtained.

### 3.1 Route 3 in detail: the endpoint

An **endpoint** is an address on the IFRS 9 system's web server that accepts a delivery from another computer, in the way a web page accepts a form. Route 3 uses one: `POST https://<system server>/api/ebanker-feed/pack`. The MAIIC script runs the same queries as under route 2; the only difference is that instead of leaving the pack in a shared folder, it sends it straight to this address. Figure 3 shows the exchange.

![Figure 3. Route 3: the MAIIC script reads E-Banker, then posts the zipped pack to the system's endpoint; the endpoint checks it on arrival and replies at once; the landing zone lands it at the next poll through the same gates as every route.](Route 3 - push by API endpoint - sequence diagram.png)

**What the endpoint does with each delivery, in order** (this is how it is built today):

| Step | What happens | If it fails |
|---|---|---|
| 1. The token | The request must carry `Authorization: Bearer <token>`. The token is compared with the one configured on the system's server (`EBANKER_FEED_TOKEN`), in a way that does not leak its length or content through timing | Refused with 401; nothing is stored |
| 2. The file | The body must carry one file, field name `pack`, which must be a readable zip | Refused with 422 |
| 3. The names inside the zip | Every entry name is checked before anything is written; a name that would write outside the inbox (for example `../../`) refuses the whole zip, and the attempt is logged with the sender's IP | Refused with 422; logged as a warning |
| 4. The manifest | The zip must hold `manifest.json` (at the top or in one sub-folder) | Refused with 422 |
| 5. Into the inbox | The zip is unpacked into its own inbox folder named by date, time and the first characters of the file's hash; an audit entry "E-Banker Pack Received by API" records the inbox and the sender's IP | |
| 6. The reply | `202 Accepted`, with the inbox name. 202 means **received, not yet landed** | |
| 7. The landing | At the next run of the poller (daily at 06:30, `eir:poll-feed-folder --also-route-3`), the pack goes through the landing zone exactly as under every other route: the gates, landed or quarantined, versioned rows, watermarks. The load is recorded as route `ROUTE_3_API` | Quarantined, with the failures named |

A pack sent twice is recognised by its manifest hash at step 7 and not landed twice (section 11).

**Push and pull: who opens the connection.** This is usually what decides between the routes, because it decides which firewall rule MAIIC's ICT has to open.

| Route | Who starts it | Connection opened | Firewall rule needed | Where the E-Banker password lives | What the system holds |
|---|---|---|---|---|---|
| 1. Manual | A person | Barry to E-Banker; then a browser upload to the system | None new | With Barry | Nothing |
| 2. Scheduled export | MAIIC's scheduled task | MAIIC server to E-Banker; MAIIC server to a shared folder the system can read | A share both servers can reach | Oracle Wallet on the MAIIC server | Read access to the folder |
| 3. Push by API (endpoint) | MAIIC's scheduled task | MAIIC server to E-Banker; MAIIC server **out** to the system over HTTPS | Outbound HTTPS (443) from the MAIIC server to the system's server | Oracle Wallet on the MAIIC server | The feed token only |
| 4. Direct read | The system's scheduler | System server **in** to E-Banker over the VPN | Inbound to the database listener (1521) from the system's server | The system server's configuration | The read-only database password |
| 5. Vendor view or API | The system's scheduler | System server to the vendor's views or the vendor's own endpoint | As route 4, or to the vendor's endpoint | The system server's configuration | The vendor's credentials |

Routes 2 and 3 keep every connection to E-Banker inside MAIIC: the IFRS 9 system never connects into MAIIC's network and never holds an E-Banker password. That makes them the natural choice if MAIIC's ICT policy does not allow an outside system to connect to the database. Route 4 is simpler to run (nothing to install at MAIIC) but needs the inbound rule.

**Route 5's endpoint is a different thing.** Under route 5 the endpoint would be the vendor's, not ours: Virtual Galaxy would publish an address the system calls to fetch the data (a pull), and the system would need a small connector written to their specification. That is only worth doing if the vendor refuses route 4 and MAIIC prefers not to run a script (routes 2 and 3).

**What would be added before route 3 goes live.** The endpoint works today, but four hardening items would be added if route 3 is chosen, about a day's work:

- **An IP allow-list**, so only the MAIIC server's address is accepted (today any address with the token is). Until then, the same can be done on the system server's firewall.
- **Two-token rotation**, so a new token can be issued and the old one retired without a gap (today there is one token in the configuration).
- **Landing on receipt**, so the reply can say "landed" or "quarantined" straight away instead of waiting for the 06:30 poll; or the poll run more often on the first working days of the month.
- **A size limit and a status address**, so oversize packs are refused clearly (today the limit is the web server's upload limit, which must be raised to at least 100 MB) and the script can ask for the result of its last delivery.

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

### 4.1 Inside the landing zone: the four tables, explained

This part is written so that anyone at MAIIC can follow it, not only ICT. If you have run a receiving office, you already know how the landing zone is organised: it keeps four record books.

1. **The register** (`ebanker_queries`) is the list of everything the system is allowed to ask E-Banker for: about sixty questions, each with a code (P1_01, P2_08, OD_01 …). For each one it records which E-Banker table it reads, which column is that table's own unique number for a record, which columns hold the account and the date, the version of the question, and whether to fetch everything each time or only what is new. If a question is not in the register, the system will not accept its answers.
2. **The deliveries book** (`ebanker_loads`) has one line for every pack that arrives: when, by which route, its fingerprint, whether it was accepted (LANDED) or set aside (QUARANTINED), the result of every check, and the **watermark**, meaning the last record received for each question so that the next delivery fetches only what is new.
3. **The files book** (`ebanker_pack_files`) has one line for every file inside a pack: its fingerprint, how many records the pack said it held, how many were actually stored, and how many of those were new, changed or already held.
4. **The records book** (`ebanker_raw_rows`) holds the records themselves, one line for each version of each E-Banker record. A few facts are copied into labelled columns so they can be found quickly: E-Banker's own record number, the account, the date, the version, and whether this version has been replaced. The full record, every column exactly as E-Banker sent it, is kept beside them unchanged.

**One real payment, followed through** (Figure 4, from the bootstrap database). MUSECO repaid K200,000,000 on 24 July 2025. In E-Banker that is ledger record 194427. It arrived in delivery 1 (by hand, accepted on 9 October 2026), in the file `P1_01_ledger_all.csv`. That file said it held 3,640 records and 3,640 were stored. In the records book it is line 20: account 000104420000005, dated 24 July 2025, version 1, not replaced, with all 83 of E-Banker's columns kept as they were (amount 200,000,000.00, transaction type 305, "By Trf Loan repayment", loan GL 1050101).

![Figure 4. The four record books of the landing zone, following MUSECO's K200,000,000 repayment of 24 July 2025 from E-Banker's ledger to line 20 of the records book, and what happens if E-Banker later corrects it.](Landing zone - the four tables, one payment followed through - diagram.png)

**What happens if E-Banker later changes that record.** Suppose the payment is corrected in E-Banker to K195,000,000. At the next delivery the system sees record 194427 again with a different fingerprint. It does **not** overwrite line 20. It marks line 20 with the date and time it was replaced, and adds a new line, version 2, holding the corrected amount. Figures already reported for October were calculated from version 1 and can be reproduced exactly; figures from November use version 2. If the record arrives again unchanged, its fingerprint matches and nothing is added. (So far no record has needed a second version; the figure shows the case as an illustration.)

**Questions MAIIC can answer from the four books**, without asking Dupleix:

| Question | Where the answer is |
|---|---|
| Did this month's pack arrive, and was it accepted? | The deliveries book: the pack's line, its status and its check results |
| Was any file short or altered? | The files book: records declared against records stored, and the fingerprint |
| Which E-Banker record is behind a figure in a report? | The records book: E-Banker's record number, the delivery it came in, its version |
| Has E-Banker changed a record we had already received? | The records book: any line with a version above 1, and the date its earlier version was replaced |
| What exactly did we ask E-Banker for, and has the question changed? | The register: the query, its version and the fingerprint of its SQL |

**Have the tables been created?** Yes, in the system's test databases, where they hold the data received so far: the bootstrap database holds 65 registered questions, 23 deliveries, 75 files and 88,977 records. In the production database they are created, empty, when the system is installed on MAIIC's server (the installation runs the same database set-up), ready for the first pack. Their full column-by-column definition is in the system's code.

## 5. What the account will do, and will not do

| Will | Will not |
|---|---|
| Run the registered SELECT statements (about sixty, all in MAIIC's copy of the repository) | Write, change or delete any row |
| Run once a month, on the first working day after month-end, between 06:00 and 07:00; the overdue table optionally daily | Run stored procedures, create objects or change settings |
| Read a few megabytes a month: the loan accounts of the 18 MAIIC schemes and the 8 Mega Farm schemes, their ledger postings, balances, instalment charts, overdue computations and the trial balance | Read customer accounts outside those schemes, or any table not in the annex |
| Connect from one named server over the existing VPN, with the password held by MAIIC ICT and rotated on MAIIC's schedule | Connect from anywhere else, or be used by a person |

MAIIC ICT administers the account, can see every session in the database's own audit, and can revoke it at any time. The manual route keeps working regardless.

## 6. Setting up the accounts: step by step for MAIIC ICT

This section is for MAIIC ICT. You do the same steps twice: first on **E-Banker's test environment** (stage B of section 9), then on **production or the replica** (stage C), only after the tests on the test environment have passed. Both use the same account name, with different passwords. Every statement below is standard Oracle and is run by your database administrator (a user with the DBA role or equivalent). None of it changes E-Banker's own tables, code or users. If you would rather the vendor ran the steps, this section is what to send them. Dupleix will join any of the steps on a call if that helps.

**Step 1. Agree the names and the server.** Account: `MAIIC_IFRS9_RO`. Role: `IFRS9_FEED_READ`. Profile: `IFRS9_FEED_PROFILE`. Dupleix provides the IP address of the system's test server now and of the production server at stage C.

**Step 2. Create a profile that limits the account.** The profile caps sessions, locks the account after failed logins and forces a password change every 90 days.

- `CREATE PROFILE ifrs9_feed_profile LIMIT SESSIONS_PER_USER 2 FAILED_LOGIN_ATTEMPTS 5 PASSWORD_LOCK_TIME 1 PASSWORD_LIFE_TIME 90 PASSWORD_GRACE_TIME 7 IDLE_TIME 30;`

(`SESSIONS_PER_USER` and `IDLE_TIME` take effect only when the database parameter `RESOURCE_LIMIT` is TRUE, which is the default from Oracle 12c. If it is FALSE, leave it as it is and tell us; the account works the same.)

**Step 3. Create the user.** It can log in and own nothing; it has no quota, so it cannot create anything.

- `CREATE USER maiic_ifrs9_ro IDENTIFIED BY "<a strong password>" PROFILE ifrs9_feed_profile DEFAULT TABLESPACE users TEMPORARY TABLESPACE temp QUOTA 0 ON users;`
- `GRANT CREATE SESSION TO maiic_ifrs9_ro;`

**Step 4. Create a read-only role and grant SELECT on the tables in the annex.** Granting through a role means the whole access can be removed with one statement.

- `CREATE ROLE ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.ACMASTER TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.ACLOANMASTER TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.SCHEME_MASTER TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.CUMVOUCH TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.CUMOVUCH_OLD TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.ACCOUNT_BALANCE TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.LOAN_BOOK_DETAILS_ALL TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.FIXLOAN_INST_CHART TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.MULTI_INST_DETAILS TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.LOAN_DISBURSMENT_SCHEDULE TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.ACCOUNT_STATUS_HISTORY TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.OVERDUE_DETAILS TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.INTEREST_SLAB_MASTER TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.INTEREST_SLAB_DETAILS TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.INTEREST_SUMMARY TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.DAILY_INTEREST_ACCRUAL TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.FORGIVE_INTEREST_DETAILS TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.PLR_MASTER TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.LOS_DISB_CHARGES_POST TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.LOS_REQUEST_DETAILS TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.CUST_SECURITY_DETAILS TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.GUARANTER_MASTER TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.LOAN_RESCHEDULE_DETAILS TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.LOAN_WRITEOFF_MASTER TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.GL_OPENING_BALANCE TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.GL_OPENING_BALANCE_BK240824 TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.TRIAL_BALANCE_HISTORY TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.OPENING_BAL TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.ACCOUNT_NO_MAPP TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.NEW_ACMAP TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.T_ACCOUNT TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.T_ACCOUNTBALANCE TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.TEMP_MASTER TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.G_ACMASTER TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.ACMASTER_MW TO ifrs9_feed_read;`
- `GRANT SELECT ON VGCBS.ACMASTER_NF TO ifrs9_feed_read;`
- `GRANT SELECT ON MAIIC_ORA_LIVE.CRM_ACMASTER_05082024 TO ifrs9_feed_read;`
- `GRANT ifrs9_feed_read TO maiic_ifrs9_ro;`

If a grant fails with ORA-00942 (table or view does not exist), that table sits in a different schema on MAIIC's installation: skip it and send us the error, and we will correct the schema. Nothing else is granted: no INSERT, UPDATE, DELETE, EXECUTE, CREATE TABLE, or any ANY privilege. The data dictionary views in the annex need no grant; they show only the tables the account can already see.

**Step 5. Switch on auditing for the account.** So that MAIIC can see every session and every statement it runs. On Oracle 12c and later with unified auditing:

- `CREATE AUDIT POLICY ifrs9_feed_audit ACTIONS LOGON, LOGOFF, SELECT;`
- `AUDIT POLICY ifrs9_feed_audit BY maiic_ifrs9_ro;`

On an older database using traditional auditing: `AUDIT SESSION BY maiic_ifrs9_ro;` and `AUDIT SELECT TABLE BY maiic_ifrs9_ro BY ACCESS;`. The records appear in `UNIFIED_AUDIT_TRAIL` (or `DBA_AUDIT_TRAIL`).

**Step 6. Open the network path.** On the firewall, allow the system's server (one IP, over the existing VPN) to reach the database listener on its port (1521 unless E-Banker uses another), and nothing else. No change is needed to the listener or to `sqlnet.ora`. Restricting by IP on the firewall rather than in the database keeps the change outside E-Banker.

**Step 6a. Test the connection.** Before anything else is tried, confirm that the account can reach the database from the right computer. Do this from the computer that will actually use the account: the IFRS 9 system's server for route 4 (with Dupleix on the call), or the MAIIC server for routes 2 and 3.

1. **Is the database reachable on the network?** In PowerShell on that computer: `Test-NetConnection <database host> -Port 1521`. It must show `TcpTestSucceeded : True`. If it shows False, the firewall rule of step 6 is not yet in place.
2. **Can the account log in?** With SQLcl: `sql maiic_ifrs9_ro@//<database host>:1521/<service name>`, enter the password when asked. (In SQL Developer, a new connection with the same details and **Test** must show "Status: Success".)
3. **Who, where and when: the test-connection query.** Once logged in, run:

`SELECT USER AS connected_as, SYS_CONTEXT('USERENV','DB_NAME') AS database_name, SYS_CONTEXT('USERENV','SERVICE_NAME') AS service_name, SYS_CONTEXT('USERENV','IP_ADDRESS') AS connected_from, TO_CHAR(SYSDATE, 'YYYY-MM-DD HH24:MI:SS') AS database_time FROM dual;`

It must return one row: `MAIIC_IFRS9_RO`, the name of the **test** database at stage B (the **production** or replica name at stage C, never the other way round), and as `connected_from` the IP address of the computer you are on. A wrong database name here is the one mistake that matters most, so check it before going further.
4. **Can it read what it should?** Run:

`SELECT COUNT(*) AS maiic_loan_accounts, MAX(a.account_open_date) AS latest_account_opened FROM vgcbs.acmaster a WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145);`

It must return a count above zero and a recent date. On the test environment the figures reflect the date the test copy was taken.

Save the four results in a text file named `Connection test - <test or production> - <date>.txt` in the E-Banker Integration folder, together with the output of step 7.

**Step 7. Prove the account is read-only, before handing it over.** Log in as `MAIIC_IFRS9_RO` in SQL Developer and run:

1. `SELECT * FROM session_privs;` must return only `CREATE SESSION`.
2. `SELECT owner, table_name FROM all_tables WHERE owner IN ('VGCBS','MAIIC_ORA_LIVE') ORDER BY 1, 2;` must list only the tables in the annex.
3. `SELECT COUNT(*) FROM vgcbs.acmaster;` must return a count.
4. `DELETE FROM vgcbs.acmaster WHERE 1 = 0;` must fail with ORA-01031 (insufficient privileges). It deletes nothing even if the test went wrong, because `WHERE 1 = 0` matches no row.
5. `CREATE TABLE ifrs9_probe (x NUMBER);` must fail with ORA-01031 or ORA-01950.

Save the output of these five as a text file in the E-Banker Integration folder: it is the evidence that the account is read-only, and it is acceptance test 1 in section 9.

**Step 8. Hand over the connection details, and the password separately.** Send Dupleix the host, port and service name (by email is fine). Do not email the password. Under route 4, Barry types it into the system server's configuration file himself, or reads it out on a call. Under route 2, it is stored in an **Oracle Wallet** on the MAIIC server (`mkstore`, or the SQLcl secure credential store), so it never appears in the script.

**Step 9. For route 2 only: the server, the folder and the task.** On a MAIIC Windows server that can reach the database: install Oracle SQLcl (a single folder, needs Java 11 or later); create a shared folder (for example `\\<server>\ifrs9_feed`) that the scheduled task can write to and the IFRS 9 system's server can read; create a Windows scheduled task, run by a service account, that runs Dupleix's export script on the first working day after month-end at 05:30. Dupleix supplies the script and joins the first run.

**Step 9a. For route 3 only: the outbound connection and the token.** Instead of a shared folder: allow the MAIIC server to make outbound HTTPS connections (port 443) to the IFRS 9 system's server, and nothing else new. Dupleix issues a token (a long random string) and gives it to Barry on a call, not by email; Barry stores it in the Windows Credential Manager of the service account that runs the task, not in the script. The script sends each pack with the built-in Windows command `curl.exe -f -X POST "https://<system server>/api/ebanker-feed/pack" -H "Authorization: Bearer <token>" -F "pack=@<pack>.zip"`, writes the reply to its log, and tries again up to three times if the reply is not 202. Test it once with a deliberately wrong token: the reply must be 401.

**Step 10. Look after the account.** Every 90 days: `ALTER USER maiic_ifrs9_ro IDENTIFIED BY "<new password>";` then update the system's configuration (or the wallet). To pause the feed at once: `ALTER USER maiic_ifrs9_ro ACCOUNT LOCK;` (`ACCOUNT UNLOCK` to resume). To end it: `DROP USER maiic_ifrs9_ro;` and `DROP ROLE ifrs9_feed_read;`. For route 3, the feed token is changed on the same 90-day cycle (a new token on the system's server and in the Credential Manager, the same day). When the system needs a new table, Dupleix sends one line, `GRANT SELECT ON <table> TO ifrs9_feed_read;`, for MAIIC to approve and run.

**Step 11. Repeat on production at stage C.** Steps 2 to 8 again on production (or the replica the vendor names), with a new password and the production server's IP. Run steps 6a and 7 again and save their output beside the test ones; check that step 6a now names the production (or replica) database.

## 7. What we need from Virtual Galaxy

The account itself is within MAIIC ICT's gift. We ask that the vendor confirms, in writing:

1. That a read-only account used in this way does not affect the support or warranty terms of the E-Banker agreement.
2. Whether access should be to production or to a standby or reporting replica, and if a replica exists, its refresh frequency.
3. Any audit or logging the vendor requires on such an account.
4. Whether any cost attaches.
5. If direct read access is not acceptable to the vendor: a quotation for the alternative, a reporting schema of views (or a scheduled export the vendor runs) carrying the columns of the tables in the annex. The system reads either in the same way.

Two further items are vendor questions in any case and can go in the same message: the **Reschedule Report** (restructure history: LOAN_RESCHEDULE_DETAILS is empty while OPENING_BAL shows eight restructures), and the **write-off record** (LOAN_WRITEOFF_MASTER is empty while the ledger carries type 300 write-off postings).

## 8. The bootstrap: how the integration is tested before production

**What the bootstrap is.** One command in the IFRS 9 system, `eir:bootstrap`, that rebuilds the whole system from scratch on a separate, throwaway database (`maiic_ifrs9_bootstrap`) and proves the result. It takes about two minutes and never touches the production database (`maiic_ifrs9`). It has been run after every change since 8 October; it is how the figures in every review and audit to date were produced.

**What it does, in order.**

1. **Wipe.** Empties the test database and creates it again from the system's own definitions.
2. **Land.** Takes every pack MAIIC has sent so far (kept, with their hashes, in the project repository) and the client inputs (Finance's fee template, the take-on workbook, the trial balances), and lands them through the same landing zone and the same gates as production (section 4).
3. **Build.** Builds the monthly loan books, the contract inputs, the fees and the EIR schedules from the landed rows.
4. **Run the engines.** Staging, PD and LGD, ECL, EIR and revenue, the forward-looking chain, sensitivity and stress, the reports, the compliance register and the GL reconciliation, month by month, in the same order as production.
5. **Verify.** Checks nine **golden ties**: figures that must agree exactly with an independent source. They are the interest posted in the ledger; the carrying amount against E-Banker's stored loan book report, for every account and month; the year-end interest contra entries on GL 4215 and 4216; and the loan book against the trial balance on the five loan GLs (1050101, 1050102, 1050201, 1050202, 1050401). Today all nine pass.

Anything approved inside the test database is labelled "System Bootstrap (automated data-readiness, not a MAIIC approval)". Only MAIIC's own approvals, in production, count.

![Figure 2. The bootstrap rebuilds the system on the test database and checks the nine golden ties; only when they hold does a change reach production.](Bootstrap - test harness and reference copy - diagram.png)

**Where it fits in this integration.**

| Role | What it means for the integration |
|---|---|
| **History load** | The packs received by hand since 7 October are the history up to go-live. The bootstrap is how that history is built, reproducibly; route 4 then only reads forward from the watermarks the history leaves. |
| **The test system** | In stage B (section 9), route 2 and route 4 land into the bootstrap database, not production. The test account on E-Banker's test environment is paired with this test database, so nothing live is read or written on either side. |
| **The baseline** | The bootstrap already holds each month as it arrived by hand. A month read by route 4 must land against it as "all unchanged": no new rows, no new versions. Any difference is named, row by row, and is either a query to correct or a genuine change in E-Banker to explain. |
| **The regression gate** | Before any change reaches production (a new query version, an engine change, a change of route), the bootstrap is re-run and the nine golden ties must still pass. |
| **Disaster-recovery rehearsal** | The quarterly restore test of section 13 uses the same mechanism: rebuild, run the ties, record the result. |

For stage B the bootstrap gains one option, to take a fresh read from E-Banker's test environment by route 4 in addition to the committed packs, so the whole test runs under one command. This is part of the route 4 build in section 9, not separate work.

## 9. Proposed sequence: test environment first, then production

Nothing touches production until it has worked end to end on the test environment. The system has its own test databases (separate from the live one, already used for every build and audit to date), so the test stage pairs E-Banker's test environment with the system's test database, the bootstrap database of section 8: no live data on either side is read or written until stage C.

| Stage | Step | Who | When |
|---|---|---|---|
| **A. Authorise** | Dr Thom authorises the request; Barry raises section 7 with the vendor | MAIIC | This week |
| **B. Test environment** | Read-only account created on E-Banker's test environment (UAT, or a restored recent backup) by steps 1 to 8 of section 6; firewall rule from the system's test server | MAIIC ICT | On authorisation |
| | Route 2 on test: the scheduled script run against the test database, the pack written to a test folder, landed into the bootstrap database | Dupleix writes the script, Barry installs the task | Within two weeks of the account |
| | Route 3 on test, if MAIIC prefers it to route 2 or 4: the same script posting to the test system's endpoint with a test token (section 6, step 9a); a wrong token must be refused | Dupleix and Barry | Alongside route 2 |
| | Route 4 on test: the Oracle client and connection installed on the system's test server; the system runs the queries itself against E-Banker's test environment and lands into the bootstrap database | Dupleix | On the vendor's confirmation; about a week |
| | **Acceptance on test**, signed off by Barry and Dupleix: (1) the connection test of section 6, step 6a passes from the system's test server, every registered query runs under the account, and nothing outside the annex can be read (the saved outputs of steps 6a and 7); (2) a month read by route 4 lands against the bootstrap's copy of the same month (received by hand) as all unchanged: the same rows and hashes, no new versions; (3) a re-read of the same month lands as all unchanged, with no duplicates; (4) a deliberately broken file is quarantined and nothing downstream moves; (5) the read takes under 15 minutes and the database shows no measurable load; (6) a backup is restored and re-read from its watermarks; (7) the bootstrap re-run with the route 4 data passes all nine golden ties | Dupleix and MAIIC ICT | At the end of stage B |
| **C. Production** | The same account created on production (or the replica, per the vendor) by section 6, step 11; the connection pointed at it; the route setting changed to route 4 under maker-checker | MAIIC ICT; Dupleix; approver in the Governance Centre | After acceptance on test |
| | **Parallel run**: two month-ends read by route 4 alongside the manual pack; each pair must tie row for row before the manual pack is stood down | Dupleix and Barry | Two month-ends |
| | Switch: route 4 in force; route 1 kept as the fallback | MAIIC | After the parallel run |

If the vendor declines route 4 (section 7, item 5), stages B and C run the same way on route 2, which needs no vendor change, or on the vendor's views under route 5.

### 9.1 Why this must be planned now, not after go-live

This route has to be planned early. The build itself is about a week; the time is taken by things outside the build, most of them outside Dupleix's control, and each one can only happen in a fixed order:

- **Approvals and the vendor take weeks, not days.** Dr Thom's authorisation, the vendor's written confirmation of support terms, and MAIIC's own change control for a new database account and a firewall rule all have to be in place before anything can be tested.
- **A test copy of E-Banker has to exist.** If MAIIC does not already have a UAT copy, one has to be restored from a backup; that is ICT and vendor time that needs booking.
- **Proof needs real month-ends, and there is only one a month.** The parallel run needs two month-ends read both ways and tying row for row. If the November month-end is missed, the earliest switch moves from January to February; every missed month-end moves it by a full month.
- **Year-end is the month that matters most.** The December 2026 year-end feeds the annual financial statements and Deloitte's audit. Having route 4 proven on November and run in parallel on December gives the auditors the strongest evidence: the same figures by two independent routes. Starting later means the year-end is read by hand only.
- **Year-end change freezes.** Many ICT teams freeze changes over December. Everything that changes MAIIC's systems (the accounts, the firewall rule, the production set-up) is therefore scheduled to finish by **27 November**.
- **The manual route carries risk every month it remains the only one.** Each pack so far has needed a re-run; the overdue table cannot be exported by hand at all. Until route 2 or 4 is live, those risks recur at every month-end.

### 9.2 Proposed build and test timetable

The dates assume Dr Thom approves by **Friday 23 October 2026**. Each week of delay moves the whole chain; a missed month-end moves it by a month. Month-end reading days are the first working day after month-end.

| Stage | What | Who | Dates (2026 to 2027) |
|---|---|---|---|
| **A** | Dr Thom approves the request; Barry sends the vendor letter (section 7) | MAIIC | Thu 15 to Fri 23 October |
| **A** | Vendor's written reply. **Decision point Fri 6 November**: if there is no reply or a refusal, the same timetable continues on route 2, which needs no vendor change | Virtual Galaxy | 23 October to 6 November |
| **B** | Test account on E-Banker's test copy, firewall rule, connection test and read-only proof (section 6, steps 1 to 8) | MAIIC ICT | Mon 26 October to Fri 6 November |
| **B** | Route 2 script written by Dupleix and installed on a MAIIC server against the test copy | Dupleix and MAIIC ICT | 26 October to 6 November |
| | *October month-end: read by hand as now (route 1)* | *Barry* | *Mon 2 November* |
| **B** | Route 4 build: Oracle client and connection on the system's test server, the query runner, the bootstrap option; route 3 hardening if route 3 is chosen | Dupleix | Mon 2 to Fri 13 November |
| **B** | Testing on the test copy into the bootstrap database; acceptance tests 1 to 7; **sign-off Fri 20 November** | Dupleix and MAIIC ICT | Mon 16 to Fri 20 November |
| **C** | Production (or replica) account by section 6, step 11; connection test against production; switch prepared in the Governance Centre | MAIIC ICT | Mon 23 to Fri 27 November |
| **C** | **Parallel run 1**: November month-end read by route 4 beside the hand pack; must tie row for row | Dupleix and Barry | Tue 1 to Fri 4 December |
| | *Possible ICT year-end change freeze: no changes, only the parallel run continues as planned* | | *14 December to 3 January* |
| **C** | **Parallel run 2**: December year-end read by route 4 beside the hand pack | Dupleix and Barry | Mon 4 to Fri 8 January 2027 |
| **C** | Switch: route 4 in force (maker-checker); route 1 kept as the fallback | MAIIC | Mon 18 to Fri 22 January 2027 |
| | **First month-end by route 4 alone: January** | | **Mon 1 February 2027** |

![Figure 5. Proposed timetable for route 4, from approval in October 2026 to the first month-end read by route 4 alone in February 2027, with the month-ends that anchor it.](Route 4 - proposed build and test timetable - gantt.png)

## 10. How the queries are refreshed

Every extract is a **registered query**: the system holds a register (`ebanker_queries`) with, for each query, its identifier (P1_01, P2_08, OD_01 and so on), the E-Banker table it reads, the table's own key column, the account and date columns, a version number, the SQL text and its hash, and whether the table is read incrementally or whole. The register is seeded from the repository, so a query is changed by changing the repository, not by editing a worksheet.

Two kinds of table, two refresh rules:

- **Master tables come whole each time** (account master, loan master, scheme master, instalment chart in force, security, guarantors, rate slabs, PLR history). The whole table is read at each run and compared row by row with what is held; only rows that are new or changed are stored (section 10). The size is small (hundreds of rows).
- **History tables are read incrementally** (ledger postings, balance history, loan-book runs, status history, interest rows, the overdue run). After every landing the system records a **watermark** per query: the highest source key loaded (the ledger's CUMVOUCH_DET_ID, the overdue run's OVERDUE_DET_ID). The next run reads only rows above the watermark. Under the manual route the watermark is printed on the pack sheet so the next query starts from it (the "ids ledger 439206 / balances 363004 / runs 808196" lines on the pack 3 sheet); under the scheduled and direct routes the system applies it itself. Nothing is ever re-read in full unless asked.

Timing: the month-end pack is read on the first working day after month-end (the loan-book run and the trial balance are produced by then); the overdue run can be read daily or at month-end only. A month can be re-read at any time (a correction, a late posting) and the same rules apply: new rows are added, changed rows become a new version, unchanged rows are left alone.

Changing a query (a column added, a filter corrected) means a **new version in the register**. Each pack's manifest states the version of every file in it; a file whose version is not the one the register holds is refused (the `query_version` gate), so an old script cannot feed the system unnoticed. The SQL hash lets anyone confirm that the script Barry ran is the one in the register.

## 11. How duplicates are handled

Three levels, all automatic, none of them overwriting anything:

| Level | Rule | What happens |
|---|---|---|
| **Pack** | Each pack's manifest is hashed (SHA-256) | A pack already landed is recognised and reported as ALREADY_LANDED; nothing is loaded twice. A pack refused earlier and offered again is a fresh attempt, recorded whole |
| **File** | Each file's hash and row count must match its manifest; the key column must be unique within the file | A short or altered file is refused with the row named; a key that repeats inside a file refuses the file (a sign the export doubled up) |
| **Row** | Every row is stored under the source table's own key (CUMVOUCH_DET_ID, OVERDUE_DET_ID, ACCOUNT_BAL_MST_ID …) with a hash of its content | Same key, same content: counted as unchanged and skipped. Same key, different content: stored as **version 2**, the earlier row marked superseded with the time, never deleted. New key: stored as version 1 |

So an overlap between two extracts of the same table (the month-end overdue rows in OD_01 and the daily rows in OD_02; a ledger re-run that re-pulls keys already held) lands once. Every landing records, per file, how many rows were new, versioned and unchanged, so a pack that was entirely duplicate shows as "0 new, 0 versioned, N unchanged" rather than as a silent double count. And because superseded rows are kept with their dates, any month can be re-derived exactly as it looked at the time it was first reported.

## 12. Governance around the feed

- **Gates before anything moves.** Every pack passes the checks in the manifest (file exists, hash, row count, key unique, dates parse, amounts parse, query version known) and the checks across files (every ledger account is in the account master; the balance history ties to the ledger; every in-scope account has its month-end loan-book row; the fee GL movement ties to the trial balance). An ERROR-level failure **quarantines the whole pack**: its rows are kept so the refused file can be opened and examined, but no reader of the system sees them and nothing downstream (loan book, staging, ECL, EIR) moves. WARNING-level results are recorded against the load, not enforced.
- **Only landed loads are read.** Every build reads from landed loads only, and records which loads it read, so a figure in a report traces to the pack and the file it came from.
- **Audit trail.** Each landing and each quarantine writes an audit entry with the route, who or what loaded it, the files, the gate results and the watermarks. The query register, the manifest and the file hashes let an auditor re-run the check that the file landed is the file E-Banker produced.
- **Period lock.** Once a reporting month is locked (maker-checker, Governance Centre), its engines do not re-run on new data; a late pack still lands, and its effect is taken in the month that is open, with the difference shown. Landing never bypasses the lock.
- **The route is a governed setting.** Which route is in force (manual, scheduled folder, API push, direct read, vendor view) is a Governance Centre setting changed under maker-checker with an effective date. The manual route stays available whichever is in force, so a missed month or a correction can always be loaded by hand.
- **Changes to the queries** go through the repository (version, hash, review) and the register, not through SQL Developer; the `query_version` gate enforces it.
- **The account** is MAIIC ICT's: created, held, rotated and revocable by MAIIC; used by one server; visible in Oracle's own session audit.

## 13. Data storage and backup

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

**If the system's database is lost**, it is restored from the last nightly dump; anything landed after it is re-read from E-Banker, because the watermarks in the restored database say where to start, and the duplicate rules (section 11) make a re-read safe.

## 14. Contact

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
