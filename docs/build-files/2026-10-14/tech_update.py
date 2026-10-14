import re
from pathlib import Path
F = Path(r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\E-Banker Integration\Data access request - E-Banker read-only access (route 4) - 14 Oct 2026.md")
t = F.read_text(encoding="utf-8")

def sub(a, b, count=1):
    global t
    assert a in t, "anchor missing: " + a[:90]
    t = t.replace(a, b, count)

# ------------------------------------------------ front matter: logo, justified text
sub("status: Draft for discussion\n---", "status: Draft for discussion\nlogo: Dupleix logo.png\njustify: yes\n---")
head_end = t.index("\n---", 4) + 4
head, body = t[:head_end], t[head_end:]

def bsub(a, b, count=1):
    global body
    assert a in body, "anchor missing: " + a[:90]
    body = body.replace(a, b, count)

# ------------------------------------------------ the system now sits on MAIIC's own server
bsub("usable from the IFRS 9 system's server over the VPN that is already in place, so that the monthly extracts now run by hand in SQL Developer are run by the system itself on a schedule.",
     "usable from the IFRS 9 system's server, which is being installed on MAIIC's own network, so that the monthly extracts now run by hand in SQL Developer are run by the system itself on a schedule.")
bsub("(port 1521 unless E-Banker uses another) over the VPN.", "(port 1521 unless E-Banker uses another) on MAIIC's network.")
bsub("Since 7 October the system has been fed by packs that Barry runs in SQL Developer and exports by hand.",
     "Since 7 October the system has been fed by packs that MAIIC ICT runs in SQL Developer on its own machine, with Dupleix watching over AnyDesk, and exports by hand.")
where = """### 2.1 Where things stand (14 October 2026)

- **How the queries are run today.** MAIIC ICT prefers to run every pack on its own machine, in SQL Developer, while Dupleix watches the session over AnyDesk. That is route 1 (section 3), supervised: MAIIC keeps hands on its own database, and Dupleix sees each query run and can correct a setting on the spot. Ten packs have been run this way; pack 10 was prepared on 14 October during the system build (section 10 and the annex).
- **The system is moving onto MAIIC's server.** The IFRS 9 system is about to be installed on a MAIIC server. The first thing run there is the bootstrap (section 8): it rebuilds the system from every pack received so far and checks the nine golden ties, which proves the installation before anything else is done. The production database is created at the same time, empty.
- **What that changes for this request.** Because the system's server sits on MAIIC's own network, every connection in routes 2, 3 and 4 stays inside MAIIC: E-Banker data never leaves MAIIC's network, and no outside party connects to the database. AnyDesk and the VPN remain only for Dupleix's remote support.
- **What comes next.** The integration work in this document: the read-only account, tested first against E-Banker's test copy and the bootstrap database, then production, on the timetable of section 9.2.

"""
bsub("## 3. Background: the five feed routes", where + "## 3. Background: the five feed routes")

bsub("| **1. Manual pack** | Barry runs the versioned queries in SQL Developer, exports one CSV per query and saves them to the shared OneDrive folder; the system's Feed screen lands them | Nothing new | **In use since 7 October.** Every pack to date has come this way. Exposed to the three problems in section 2 |",
     "| **1. Manual pack** | MAIIC ICT runs the versioned queries in SQL Developer on its own machine while Dupleix watches over AnyDesk, exports one CSV per query and saves them to the shared OneDrive folder; the system's Feed screen lands them | Nothing new | **In use since 7 October**, supervised over AnyDesk since pack 9. Every pack to date has come this way. Exposed to the three problems in section 2 |")
bsub("| **4. Direct read over the VPN** (this request) | The system connects to E-Banker's Oracle database read-only over the existing VPN,",
     "| **4. Direct read** (this request) | The system connects to E-Banker's Oracle database read-only from its own server on MAIIC's network,")
bsub("| 1. Manual | A person | Barry to E-Banker; then a browser upload to the system | None new | With Barry | Nothing |",
     "| 1. Manual (supervised) | A person at MAIIC, watched over AnyDesk | MAIIC ICT's machine to E-Banker; then a browser upload to the system | None new | With MAIIC ICT | Nothing |")
bsub("| 4. Direct read | The system's scheduler | System server **in** to E-Banker over the VPN |",
     "| 4. Direct read | The system's scheduler | System server (on MAIIC's network) to E-Banker |")
bsub("Routes 2 and 3 keep every connection to E-Banker inside MAIIC: the IFRS 9 system never connects into MAIIC's network and never holds an E-Banker password. That makes them the natural choice if MAIIC's ICT policy does not allow an outside system to connect to the database. Route 4 is simpler to run (nothing to install at MAIIC) but needs the inbound rule.",
     "Now that the IFRS 9 system is installed on MAIIC's own server, **every route keeps the connection to E-Banker inside MAIIC's network**: no outside party connects to the database under any of them. The difference is who holds the E-Banker password. Under routes 2 and 3 it stays on a MAIIC server in an Oracle Wallet and the IFRS 9 system never holds it; under route 4 it sits in the IFRS 9 system's configuration on MAIIC's server. Route 4 is the simplest to run (no script or scheduled task to maintain at MAIIC); routes 2 and 3 suit an ICT policy that the reporting system itself should not hold a database login.")
bsub("| **1. Manual pack** | Barry, in SQL Developer | His workstation, on request |",
     "| **1. Manual pack** | MAIIC ICT, in SQL Developer, Dupleix watching over AnyDesk | MAIIC ICT's own machine, on request |")
bsub("| The system's server over the VPN; monthly, or daily for the overdue run |", "| The system's server on MAIIC's network; monthly, or daily for the overdue run |")
bsub("| Run the registered SELECT statements (about sixty, all in MAIIC's copy of the repository) |",
     "| Run the registered SELECT statements (114 in the register after pack 10, all in MAIIC's copy of the repository) |")
bsub("| Connect from one named server over the existing VPN,", "| Connect from one named server on MAIIC's network,")
bsub("Dupleix provides the IP address of the system's test server now and of the production server at stage C.",
     "MAIIC ICT already knows the IFRS 9 system's server: it is the MAIIC server the system is being installed on, and its IP address is the one the firewall rule names. The bootstrap (test) and production databases both sit on it.")
bsub("allow the system's server (one IP, over the existing VPN) to reach the database listener", "allow the system's server (one IP on MAIIC's network) to reach the database listener")

# step 7: pack 10's DD_17 / DD_18 as the full read check
bsub("Save the output of these five as a text file in the E-Banker Integration folder:",
     "6. Run pack 10's **RUN 8a (DD_17)** and **RUN 8b (DD_18)** under the new account, exactly as written in the pack. DD_17 must show `MAIIC_IFRS9_RO` as the login and where each table name points; DD_18 must return **1 on every one of its 35 lines**, one per table the packs read. A line that errors means a grant in step 4 is missing for that table.\n\nSave the output of these six as a text file in the E-Banker Integration folder:")

# remaining Barry references (body only)
bsub("Under route 4, Barry types it into the system server's configuration file himself, or reads it out on a call.",
     "Under route 4, MAIIC ICT types it into the system server's configuration file itself, or reads it out on a call.")
bsub("gives it to Barry on a call, not by email; Barry stores it in", "gives it to MAIIC ICT on a call, not by email; MAIIC ICT stores it in")
bsub("Technical questions from the vendor can come to us directly, copied to Barry.", "Technical questions from the vendor can come to us directly, copied to MAIIC ICT.")
body = body.replace("signed off by Barry and Dupleix", "signed off by MAIIC ICT and Dupleix")
body = body.replace("the script Barry ran", "the script MAIIC ran")
body = body.replace("Dupleix writes the script, Barry installs the task", "Dupleix writes the script, MAIIC ICT installs the task")
body = re.sub(r"\bBarry\b", "MAIIC", body)

# test server -> the system's server (bootstrap database)
body = body.replace("firewall rule from the system's test server", "firewall rule from the system's server on MAIIC's network")
body = body.replace("installed on the system's test server", "installed on the system's server")
body = body.replace("passes from the system's test server", "passes from the system's server")
body = body.replace("Oracle client and connection on the system's test server", "Oracle client and connection on the system's server")

# stage 0 in the sequence and the timetable
bsub("| **A. Authorise** | Dr Thom authorises the request;",
     "| **0. Install** | The IFRS 9 system installed on MAIIC's server; the bootstrap run there and all nine golden ties pass; the production database created, empty | Dupleix with MAIIC ICT (over AnyDesk) | This week |\n| **A. Authorise** | Dr Thom authorises the request;")
bsub("| **A** | Dr Thom approves the request;",
     "| **0** | IFRS 9 system installed on MAIIC's server; bootstrap run there, nine golden ties checked; production database created, empty | Dupleix with MAIIC ICT (AnyDesk) | Thu 15 to Fri 23 October |\n| **A** | Dr Thom approves the request;")

# ------------------------------------------------ annex rebuilt from the register (114 queries after pack 10)
annex = """## Annex: tables the account needs SELECT on

This list is the system's query register, which after pack 10 (14 October 2026) holds **114 queries**. Pack 10 added CV_15 (the account master copy of 5 August 2024), RF_01 (refinance batch legs), TB_06 (a stored trial balance check), ZF_04 (a Zaithwa Farms balance re-check) and DD_17, DD_18 and DD_20 (the login, read-access and link-column checks), and took the overdue run's remaining quarters into OD_02 with OD_00 as its profile.

Schema **VGCBS** unless stated. "Read" is whole each time (W), incremental by key (I), or once.

**Read every month**

| Table | Used by | Read | What it carries | How the system uses it |
|---|---|---|---|---|
| ACMASTER | P1_02, MF_01, CV_05a | W | Account master | Defines the accounts in scope (by scheme and loan GL); the check that every ledger posting belongs to a known account; customer, status and open date on the loan book |
| ACLOANMASTER | P1_03, MF_02, CV_05b | W | Loan master: sanction, rate, tenor, moratorium | Contract terms for the EIR (sanction amount, rate basis, tenor, moratorium, instalment mode); facility class for the staging thresholds |
| CUSTOMER_MAST | DD_18 | W | Customer master | The customer behind each account, for customer-wide credit triggers; read check today |
| SCHEME_MASTER | SM_01 | W | Scheme definitions | Product group per scheme; interest basis for partly drawn loans; meaning of a blank moratorium type |
| GLMASTER | DD_18 | W | General ledger chart | GL names and types for the reconciliation; read check today |
| CUMVOUCH | P1_01, GL_01, GL_03, GL_04a, GL_04b, GL_04c, GL_05, GL_06a, GL_06b, GL_07, GL_08a, RF_01, CH_02b, ZF_01, ZF_02, M09_01, MF_03 | I | Ledger postings | The ledger-derived loan book: disbursements, interest, receipts, write-offs, settlements in kind; the EIR's actual cash flows; the fee GL legs; the GL reconciliation; the refinance batches that pay off one loan from another (RF_01) |
| ACCOUNT_BALANCE | P2_09, M09_02, MF_04, ZF_03, ZF_04 | I | Balance history at month-ends | Ties the ledger's running balance to E-Banker's own month-end balance (within 1.00); carrying amount check per account-month |
| LOAN_BOOK_DETAILS_ALL | P2_08, M09_03, MF_05 | I | Stored Loan Book Report runs | The stored loan book; the arrears buckets and overdue date that stage every loan; the check that every account in scope has a month-end row |
| FIXLOAN_INST_CHART | P2_10, RS_02a | W | Instalment chart in force | Contractual cash flows for the EIR schedule; the four-missed-instalments Stage 3 trigger |
| MULTI_INST_DETAILS | P3_11 | W | Instalment plans | Step-up and seasonal instalment patterns for the EIR schedule |
| LOAN_DISBURSMENT_SCHEDULE | P3_12 | W | Disbursement schedules | Tranche timing for part-drawn facilities: when undrawn commitments become exposure |
| ACCOUNT_STATUS_HISTORY | P3_13, MF_06 | I | Status history | Status at each month-end (active, closed, written off) for derecognition and the cure period |
| ACCOUNT_CLOSER_HISTORY | RS_02g | I | Account closures | Closure dates, to trace loans paid off and replaced (refinance and restructure) |
| OVERDUE_DETAILS | OD_00, OD_01, OD_02 | I | The overdue run per account and date | The independent check on days past due and the instalment trigger; reschedule columns as restructure evidence; a per-account day count for the regulatory bands |
| INTEREST_SLAB_MASTER, INTEREST_SLAB_DETAILS | P3_15, P1_04, MF_07 | W | Rate slabs | The rate set-up per account in date order: the contractual rate and its resets for the EIR |
| INTEREST_PRODUCT | P1_05b | I | Interest product history | Which interest product each account carried over time |
| INTEREST_SUMMARY, DAILY_INTEREST_ACCRUAL, FORGIVE_INTEREST_DETAILS | P1_05, P3_14, P3_16 | I | Interest computed, accrued, waived | E-Banker's own interest against the EIR interest; waived interest as a modification; suspense on non-performing loans |
| PLR_MASTER | P2_06 | W | Prime lending rate history | Reset dates and rates for floating loans |
| LOS_DISB_CHARGES_POST, LOS_REQUEST_DETAILS | P2_07, CH_02a, MF_08, P2_13, P2_13b | W | Fees at disbursement; loan origination payloads | Fees in the EIR (integral or not, under the fee rulebook); the origination terms the offer letters are checked against |
| CHARGES_POSTING_HISTORY, DISBURSE_CHARGES_DETAILS | CH_01a, CH_01b | W | Charges posted; disbursement charge set-up | Fees on term loans that the origination table does not carry |
| CUST_SECURITY_DETAILS, GUARANTER_MASTER | P2_11, P2_12 | W | Security and guarantors | LGD inputs (collateral type, value, realisation); the security line of the regulatory return |
| LOAN_RESCHEDULE_DETAILS, MODIFICATION_LOG | RS_01, RS_02f | I | Restructures; logged changes to account records | The restructured-loan register and the 10 percent modification test; the history of changes to terms |
| LOAN_WRITEOFF_MASTER, LOAN_WRITEOFF_TRANS | WO_01, DD_18 | I | Write-offs (empty today) | Write-off derecognition, when E-Banker starts filling them |
| GL_OPENING_BALANCE | GL_02, GL_06d | W | GL opening balances | Opening positions for the GL reconciliation |
| TRIAL_BALANCE_HISTORY | TB_02, TB_03, TB_04, TB_05, TB_06 | I | Trial balance by month and day | Loan and fee GLs tied to the loan book and fee legs each month (would replace Finance's upload); the stored trial balance check (TB_06) |

**Read once (history and migration)**

| Table | Used by | Read | What it carries | How the system uses it |
|---|---|---|---|---|
| CUMOVUCH_OLD, CUMVOUCH_BK240824, CUMVOUCH_BAK | CV_01, CV_04a, CV_04b | once | Older ledger copies | Pre-migration history, attached to today's loans where the account link exists |
| GL_OPENING_BALANCE_BK240824 | CV_14 | once | GL balances at the August 2024 backup | Anchors the older ledger copy to the GL (it ties to the kwacha at 1 January 2024) |
| FIXLOAN_INST_CHART1, FIXLOAN_INST_CHART_300824, FIXLOAN_INST_CHART_141124, FIX_INST_CHART28SEP | RS_02b, RS_02c, RS_02d, RS_02e | once | Earlier copies of the instalment chart | Instalment history, to trace reschedules made before the register existed |
| OPENING_BAL, ACCOUNT_NO_MAPP, NEW_ACMAP, T_ACCOUNT, T_ACCOUNTBALANCE, TEMP_MASTER, G_ACMASTER, ACMASTER_MW, ACMASTER_NF | CV_09 to CV_13 | once | Migration tables of July 2024 | The take-on worksheet behind the 31 July 2024 opening postings and the old-to-new account link; already received |
| MAIIC_ORA_LIVE.CRM_ACMASTER_05082024 | CV_12d, CV_15 | once | Account master copy of 5 August 2024 | Account numbers as they stood at the migration, to map renumbered loans |

**No grant needed**

| Views | Used by | Read | What they carry | How the system uses them |
|---|---|---|---|---|
| ALL_TABLES, ALL_TAB_COLUMNS, ALL_CONSTRAINTS, ALL_SYNONYMS, ALL_OBJECTS | DD_01 to DD_20, CV_02, CV_06, CV_07 | W | Structure of the tables the account can see | Detects a vendor change to a table before it reaches a query; the login and read-access checks of section 6, step 7 |

Row filters are applied in every query to the MAIIC and Mega Farm scheme identifiers (84 to 95, 140 to 145; 96 to 103), so no other customer's data is read.
"""
i = body.index("## Annex: tables the account needs SELECT on")
body = body[:i] + annex

# ------------------------------------------------ grant list rebuilt from the annex (excluding the views)
tables = []
for line in annex.split("**No grant needed**")[0].splitlines():
    if not line.startswith("| ") or line.startswith("| Table") or line.startswith("|---"):
        continue
    for name in [c.strip() for c in line.split("|")[1].split(",")]:
        if re.fullmatch(r"[A-Z0-9_.]+", name):
            tables.append(name if "." in name else f"VGCBS.{name}")
grants = "\n".join(f"- `GRANT SELECT ON {tb} TO ifrs9_feed_read;`" for tb in tables)
gs = body.index("- `CREATE ROLE ifrs9_feed_read;`")
ge = body.index("- `GRANT ifrs9_feed_read TO maiic_ifrs9_ro;`")
body = body[:gs] + "- `CREATE ROLE ifrs9_feed_read;`\n" + grants + "\n" + body[ge:]
print(len(tables), "grant lines;", len(set(tables)), "distinct")

t = head + body
assert "Barry" not in body
F.write_text(t, encoding="utf-8")
print("technical document updated")
