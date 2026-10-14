"""The register and SQL/Oracle explanation, for both documents."""

PLAIN = """### 4.2 What the register is, and how the questions work in Oracle

**The database and its language.** E-Banker keeps its records in an **Oracle** database: a very large, organised filing system made of **tables**. Each table is like a spreadsheet. Its rows are records (one loan, one payment) and its columns are facts about them (account number, date, amount). Information is taken out by asking questions in **SQL** (Structured Query Language), the standard language of databases. A question has three main parts: which columns are wanted (SELECT), from which table (FROM) and which rows (WHERE). For example:

`SELECT new_ac_number, transaction_date, transamt FROM vgcbs.cumvouch WHERE ac_glcode = '1050101' AND transaction_date >= DATE '2026-09-01';`

reads: "give me the account, the date and the amount of every posting on MAIIC's loan ledger account 1050101 since 1 September 2026". A question that starts with SELECT can only read. The read-only login is not allowed any other kind of statement, so even a mistyped question cannot change anything.

**What Oracle does with a question.** When a question arrives, Oracle first checks that the login is allowed to read that table (the permissions of section 6). It then works out the quickest way to find the rows, using its indexes as a reader uses the index of a book, reads them, and sends back the answer as rows and columns. It answers from the records **as they stood at the moment the question started**, so the answer is consistent even while staff are posting, and a reading question **locks nothing**: E-Banker's users carry on as normal. The questions run early in the morning and fetch only what is new, so they finish quickly.

**What the register is.** The register is the IFRS 9 system's controlled list of **every SQL question it is allowed to ask E-Banker**: 114 after the tenth batch. Each entry is like a library card for one question:

| On the card | Example (the ledger question) | Why it is there |
|---|---|---|
| Code and title | P1_01, "Ledger, every posting" | Names the question on every batch sheet, file and report |
| The table it reads | CUMVOUCH, E-Banker's ledger | Shows exactly what is being read |
| E-Banker's own record number (the key) | CUMVOUCH_DET_ID | Lets the system recognise a record when it arrives again, so it is stored once and later corrections become new versions (section 11) |
| The account and date columns | NEW_AC_NUMBER, TRANSACTION_DATE | Files each record under its loan and its month |
| Everything, or only what is new | Only what is new | Tells the system to ask only for records after the last one received (the watermark) |
| Version | 1 | Goes up whenever the question is changed |
| The SQL text and its fingerprint | The exact question, and a code calculated from it | Proves that the question run is the approved one |

**Why the register matters.** The same question is asked every month, so the answers can be compared. A file produced by a question that is not in the register, or by an out-of-date version, is refused at the checkpoint. The fingerprint lets anyone confirm that the SQL run at MAIIC is the SQL in the register. And the register is kept in the system's controlled code, so a question can only be changed through the release steps of section 2.2. Under the hand method, the register's questions are what is printed on each batch sheet for MAIIC ICT to run. Under the automatic read, the system will take each question from the register, add "only records after the watermark", send it to Oracle itself, and land the answer through the checkpoint.

"""

TECH = """### 4.2 The query register, and how the queries run in Oracle

**SQL and Oracle in one paragraph.** E-Banker's data sits in an Oracle database, in tables owned by the schemas VGCBS and MAIIC_ORA_LIVE. Every extract is a single SQL `SELECT` statement: the columns wanted, the table, and a `WHERE` clause that limits the rows to the MAIIC and Mega Farm schemes and, for history tables, to records after the last one received. For example, the incremental ledger read is in essence `SELECT cv.* FROM vgcbs.cumvouch cv WHERE cv.new_ac_number IN (SELECT a.new_ac_number FROM vgcbs.acmaster a WHERE a.scheme_mst_id IN (84, … , 145)) AND cv.cumvouch_det_id > :watermark ORDER BY cv.cumvouch_det_id`. A `SELECT` takes no locks on E-Banker's rows and is answered with Oracle's statement-level read consistency: every row comes back as it stood when the statement started, even while postings continue. The read-only account can run nothing but `SELECT`.

**How a query will be executed under route 4** (part of the build in section 9). For each registered query the system will: (1) open a session as `MAIIC_IFRS9_RO` and set the session itself (`NLS_DATE_FORMAT` to ISO and `NLS_NUMERIC_CHARACTERS`, the RUN 0 that is so often missed by hand); (2) bind the watermark as a parameter rather than pasting it into the text, so the statement text, and so its fingerprint, stays exactly as registered; (3) fetch the result in pages of a few thousand rows, so there is no grid limit and little memory on either side; (4) write the file and the manifest and land them through the gates. Oracle may refuse a very long read on a busy database with ORA-01555 ("snapshot too old"); running at 06:00, reading only rows above the watermark and splitting the largest tables (as the overdue run already is, by quarter) keeps every statement short enough to avoid it.

**What the register is.** The register (`ebanker_queries`) is the system's controlled catalogue of every statement it may run against E-Banker: **114 entries after pack 10**. It is seeded from the code repository, so it changes only through the release steps of section 2.2. Each entry records:

| Field | Example (P1_01) | What it is used for |
|---|---|---|
| `query_id`, `title` | P1_01, "Ledger, every posting" | The identifier on every pack sheet, file, manifest and load record |
| `source_table` | CUMVOUCH | The E-Banker table read; drives the grant list of section 6, step 4 |
| `key_column` | CUMVOUCH_DET_ID | E-Banker's own primary key; the source key under which each row is versioned and de-duplicated (section 11) and from which the watermark is taken |
| `account_column`, `date_column` | NEW_AC_NUMBER, TRANSACTION_DATE | Copied into the landing zone's typed columns, so builds can find a loan's rows for a month without reading the JSON |
| `incremental` | yes | Whole table each time, or only rows above the watermark |
| `version` | 1 | Raised on any change to the statement; the `query_version` gate refuses a file from any other version |
| `sql`, `sha256` | The statement and its SHA-256 | The statement route 4 will execute; the hash proves a hand-run file came from the registered text |

Under route 1 the register's statements are what the pack sheets print for MAIIC ICT to run in SQL Developer; under routes 2 and 3 the export script carries the same statements; under route 4 the system will execute them directly. In every case the same entry governs the same table, so a month read by any route is comparable with a month read by any other.

"""
