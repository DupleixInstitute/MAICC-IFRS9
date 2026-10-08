## 6. Ingesting E-Banker: the landing zone and the feed

### 6.1 In plain language

Until now the system has been fed by hand: a report printed from E-Banker, saved through Excel, uploaded once a month. Version 3 proposed loading the stored Loan Book Report table instead. The follow-up extracts showed something better is possible: the ledger itself, the primary record of every posting, ties to every other table we hold, so the monthly loan book can be **derived from the ledger** rather than copied from a report. And because the extracts are now ordinary queries with known keys, they can arrive by whichever road MAIIC finds convenient, from a file Barry uploads to a scheduled read over the VPN, without changing anything downstream.

The design has three parts. A **landing zone**: raw tables that mirror E-Banker's, loaded exactly as received and never edited. A **derivation**: one command that builds the monthly loan book from the landing zone, re-runnable whenever a rule or a source row changes. And a **feed**: the one door through which every pack of extracts enters, whichever road it came by, with gates that refuse a pack that does not tie.

### 6.2 What is derived from what

For every account and every month-end from the take-on at 31 July 2024 to the latest month, the loan book row is built as follows.

| Loan book field | Source | Why |
|---|---|---|
| Carrying amount, principal, interest to date, cumulative repayments, disbursed | The ledger's running balance and its postings by type | The primary record; proven against the balance history (2,533 of 2,549) and the stored loan book (2,264 of 2,264) |
| Approved amount, value and maturity dates, tenor, product, GL, customer | The loan master and account master | The record of the facility |
| Rate | The rate charged, from the stored loan book's rate column, with the PLR and the rate set-up as the register of changes (section 3.2) | The rate actually posted |
| Arrears buckets, overdue days, status, segment | The stored loan book's latest run for that month-end | E-Banker's own arrears arithmetic depends on its instalment chart, which is unreliable to reproduce, and these are the figures MAIIC reports to RBM |
| Undisbursed commitment | Approved less disbursed, checked against the disbursement schedule | Both sources held |

Beside every derived carrying amount the stored loan book's figure is kept, and a difference is a flagged row, never a silent choice. The months before December 2024, which the stored report does not hold, are built from the same sources without exception; they are no longer "derived and marked", they are the same as every other month.

### 6.3 The landing zone

Raw tables that mirror E-Banker column for column, under their own names, loaded append-only.

| Table | Mirrors | Key | Loaded |
|---|---|---|---|
| `ebanker_ledger` | CUMVOUCH (`P1_01`) | `CUMVOUCH_DET_ID` | Incrementally by key, plus a re-pull of the last two months to catch back-dated postings and deletion flags |
| `ebanker_balance_history` | ACCOUNT_BALANCE (`P2_09`) | `ACCOUNT_BAL_MST_ID` | Incrementally by key |
| `ebanker_loan_book_runs` | LOAN_BOOK_DETAILS_ALL (`P2_08`) | `LOAN_BOOK_DET_ID_A` | Incrementally by key; every run kept, the latest per account-month used |
| `ebanker_account_master`, `ebanker_loan_master`, `ebanker_rate_setup`, `ebanker_plr_master`, `ebanker_charges`, `ebanker_disbursement_schedule`, `ebanker_status_history` | `P1_02`, `P1_03`, `P1_04`, `P2_06`, `P2_07`, `P3_12`, `P3_13` | Their own ids | Whole each time; they are small |
| `ebanker_loads` | The packs themselves | Pack hash | One row per pack: period, route, who, when, the manifest, the gate results, the watermark after loading |

Three rules. **Nothing is overwritten**: a source row that arrives again with different content (a back-dated change, a deleted flag set) is stored as a new version with its load date, so a month can be re-derived exactly as it looked at the time. **Every row carries its pack**: the hash of the file it came from and the load id, so every derived figure traces to raw rows an auditor can open. **The zone is read by the derivation only**: no screen edits it.

### 6.4 The pack: one contract for every route

A pack is a set of CSV files, one per query, plus a manifest. The manifest (`manifest.json`) records the period, the run timestamp, the session settings used (ISO dates, point decimal, the RUN 0 settings of the 6 October request) and, for each file, the query id, the query version, the row count and the SHA-256 hash.

- **The queries are versioned in the system**, not in an email. Data Foundation, E-Banker Feed, Queries holds the SQL text of each extract with a version number and a checksum, and hands Barry the file to run. A column added later is a new version; the manifest says which version produced each file.
- **Watermarks.** For each incremental table the system records the last key loaded; the query for the next pack is "where the key is greater than the watermark", plus the two-month re-pull. Masters come whole.
- **Gates, run before anything is derived.** Dates parse as ISO throughout; numerics parse; counts and hashes match the manifest; the query versions are ones the system knows; every ledger account exists in the account master; the balance history ties to the ledger running balance at every month-end in the pack; every in-scope account has a month-end row. A failure refuses the pack with the file and row named; the raw rows are kept in quarantine against the load; nothing downstream moves.

### 6.5 The five routes

Every route produces the same pack and enters by the same door; the ingester does not know which road a pack came by, and moving from one route to another changes nothing downstream. All five are built and supported, and **MAIIC chooses the route in force at any time**: it is a Governance Centre setting (`ebanker_feed_route`, section 4.2) with the five routes as its options, changed under maker-checker with an effective date like every other setting. The route in force is the one the scheduler runs; the others stay available, so a manual pack can always be loaded, for a missed month or a correction, even while the scheduled read is live. The order below is the order in which MAIIC can have them, not an order of preference.

| Route | How the pack is produced and delivered | What it needs | Place |
|---|---|---|---|
| **1. Manual pack** | Barry runs the versioned queries in SQL Developer with the RUN 0 settings, zips the CSVs, uploads them on the E-Banker Feed screen; the screen builds the manifest from the files and runs the gates | Nothing new: it is what happened on 7 October, made repeatable | Now. Loads the history (section 6.7) and serves any month until route 2 is in place |
| **2. Scheduled export at MAIIC** | A SQLcl or SQL\*Plus script Dupleix writes, parameterised by period and watermark, run by Windows Task Scheduler on a MAIIC server on the first working day after month-end; it writes the pack to an SFTP folder or a network share; the system's scheduler polls the folder and ingests | A read-only Oracle account for the script; one folder; Barry to install the task | The monthly mode. Weeks, not months |
| **3. Push by API** | The same script posts the pack to an endpoint on the system with an API token over HTTPS; no shared folder | A token, outbound HTTPS from the MAIIC server | The alternative to route 2 where a shared folder is awkward |
| **4. Direct read over the VPN** | The system connects to E-Banker's Oracle database read-only (Laravel's OCI8 driver with the Oracle Instant Client), runs the versioned queries itself on schedule and writes straight into the landing zone; the pack and manifest are generated internally, so the audit trail is identical | Read-only credentials over the VPN already in place (the dictionary query DD_18 confirmed what a read-only account can see); the vendor's consent; Dr Thom's authorisation | The destination, and the answer to O9. When ICT agrees |
| **5. Vendor view or API** | Virtual Galaxy exposes a database view or an endpoint carrying the same columns; route 4 reads it | A paid change request to the vendor | Only if route 4 is refused |

Whichever route is in use, the system never writes to E-Banker; the Oracle role is read-only; credentials live in the environment file, not in code; and the extracts carry customer data, so route 2's folder and route 3's endpoint are restricted to the two systems and logged.

### 6.6 The derivation

`eir:derive-loan-books {from} {to}` builds `loan_books` for the months asked, from the landing zone, as 6.2 states. It is idempotent on account and reporting period, re-runnable at any time, and run under maker-checker from the feed screen: one person asks for the derivation, a second approves it, and the audit log records the pack hashes it read. Re-deriving a month already run prints every difference first; a locked period is never restated. The ECL columns on `loan_books` (stage, LGD, forward-looking adjustments) are not touched by the derivation.

### 6.7 Loading the history, once

1. **Freeze the 7 October pack.** The eighteen follow-up extracts and the five of the afternoon, with their SHA-256 hashes, become pack 1 under route 1. They are never opened in Excel.
2. **Gates.** The pack passes 6.4 with two accepted exceptions recorded against the load: the FInES GL openings of section 3.5 until Finance corrects them, and the Zaithwa Farms balance rows from May 2025 until the vendor rebuilds them.
3. **Derive on a copy of the production database** for every month from July 2024 to August 2026. December 2025 to August 2026 are already loaded from the Excel reports; the derivation prints every difference before it overwrites.
4. **Prove.** Run one ECL month and one EIR month on the copy and compare with the Excel-loaded results; the Baselines sheet of section 12 must show PASS on every tie of section 9.
5. **Production**, then the same every month by route 1 until route 2 is installed.

A side benefit stands: with twenty-six months in `loan_books`, the ECL module can be back-run month by month.

### 6.8 The feed screen

Data Foundation, E-Banker Feed: the queries (versioned, downloadable as the file Barry runs); the load history (pack, route, period, the gate results or the refusal reason, who loaded, when); the watermarks; the quarantine; and the Derive action with its approval. Every derived loan-book row links back to its raw rows and its pack.
