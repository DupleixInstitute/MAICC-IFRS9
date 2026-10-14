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

## 2. Why

Since 7 October the system has been fed by packs that Barry runs in SQL Developer and exports by hand. That works, and it stays as the fallback, but each pack has needed a second run for one of three reasons: the date settings not applied in the worksheet, an export stopping early at SQL Developer's 100 MB grid, or a table too large to export at all (the overdue run, which writes one row per account per day). A scheduled read by the system removes all three: it sets its own session, has no grid, and reads in pages.

The system already records, for every file it lands, which query produced it, the row count, a hash, and the checks it passed. A direct read produces the same record, so the audit trail Deloitte will see is the same whether a month came by hand or by schedule.

## 3. What the account will do, and will not do

| Will | Will not |
|---|---|
| Run the registered SELECT statements (about sixty, all in MAIIC's copy of the repository) | Write, change or delete any row |
| Run once a month, on the first working day after month-end, between 06:00 and 07:00; the overdue table optionally daily | Run stored procedures, create objects or change settings |
| Read a few megabytes a month: the loan accounts of the 18 MAIIC schemes and the 8 Mega Farm schemes, their ledger postings, balances, instalment charts, overdue computations and the trial balance | Read customer accounts outside those schemes, or any table not in the annex |
| Connect from one named server over the existing VPN, with the password held by MAIIC ICT and rotated on MAIIC's schedule | Connect from anywhere else, or be used by a person |

MAIIC ICT administers the account, can see every session in the database's own audit, and can revoke it at any time. The manual route keeps working regardless.

## 4. What we need from Virtual Galaxy

The account itself is within MAIIC ICT's gift. We ask that the vendor confirms, in writing:

1. That a read-only account used in this way does not affect the support or warranty terms of the E-Banker agreement.
2. Whether access should be to production or to a standby or reporting replica, and if a replica exists, its refresh frequency.
3. Any audit or logging the vendor requires on such an account.
4. Whether any cost attaches.
5. If direct read access is not acceptable to the vendor: a quotation for the alternative, a reporting schema of views (or a scheduled export the vendor runs) carrying the columns of the tables in the annex. The system reads either in the same way.

Two further items are vendor questions in any case and can go in the same message: the **Reschedule Report** (restructure history: LOAN_RESCHEDULE_DETAILS is empty while OPENING_BAL shows eight restructures), and the **write-off record** (LOAN_WRITEOFF_MASTER is empty while the ledger carries type 300 write-off postings).

## 5. Proposed sequence

| Step | Who | When |
|---|---|---|
| Dr Thom authorises the request | MAIIC | This week |
| Account created with the grants in the annex; firewall rule; connection details | MAIIC ICT | On authorisation |
| First use: the scheduled export at MAIIC (a script on a MAIIC server writing the pack to a shared folder, which the system already polls). No new software on either side; proves the account, the queries and the month-end timing | Dupleix writes the script, Barry installs the task | Within two weeks of the account |
| Vendor's written confirmation (section 4) | Barry raises it with the vendor; Dupleix supplies this note and joins the call | In parallel |
| Direct read by the system (Oracle client and connection on the system server; the system runs the queries itself) | Dupleix | On the vendor's confirmation; about a week of build, then two month-ends run alongside the manual pack before the switch |

## 6. Contact

Edward Mazibuko, Director, Data Analytics, Dupleix Institute. Technical questions from the vendor can come to us directly, copied to Barry.

## Annex: tables the account needs SELECT on

Schema **VGCBS** unless stated. The query identifiers are the ones in the system's query register and on every pack sent to date.

| Table | Used by | What it carries |
|---|---|---|
| ACMASTER | P1_02, MF_01 | Account master |
| ACLOANMASTER | P1_03, MF_02 | Loan master: sanction, rate, tenor, moratorium |
| SCHEME_MASTER | SM_01 | Scheme definitions |
| CUMVOUCH | P1_01, GL_01, GL_03, GL_05, M09_01, MF_03, ZF_01, ZF_02 | Ledger postings |
| CUMOVUCH_OLD | CV_01 | Older ledger copy (profile only) |
| ACCOUNT_BALANCE | P2_09, M09_02, MF_04, ZF_03 | Balance history at month-ends |
| LOAN_BOOK_DETAILS_ALL | P2_08, M09_03, MF_05 | Stored Loan Book Report runs |
| FIXLOAN_INST_CHART | P2_10 | Instalment chart in force |
| MULTI_INST_DETAILS | P3_11 | Instalment plans |
| LOAN_DISBURSMENT_SCHEDULE | P3_12 | Disbursement schedules |
| ACCOUNT_STATUS_HISTORY | P3_13, MF_06 | Status history |
| OVERDUE_DETAILS | OD_01, OD_02 | The overdue run per account and date |
| INTEREST_SLAB_MASTER, INTEREST_SLAB_DETAILS | P3_15, P1_04, MF_07 | Rate slabs |
| INTEREST_SUMMARY, DAILY_INTEREST_ACCRUAL, FORGIVE_INTEREST_DETAILS | P1_05, P3_14, P3_16 | Interest computed, accrued, waived |
| PLR_MASTER | P2_06 | Prime lending rate history |
| LOS_DISB_CHARGES_POST, LOS_REQUEST_DETAILS | P2_07, MF_08, P2_13 | Fees at disbursement; loan origination payloads |
| CUST_SECURITY_DETAILS, GUARANTER_MASTER | P2_11, P2_12 | Security and guarantors |
| LOAN_RESCHEDULE_DETAILS, LOAN_WRITEOFF_MASTER | RS_01, WO_01 | Restructures and write-offs (both empty today) |
| GL_OPENING_BALANCE, GL_OPENING_BALANCE_BK240824 | GL_02, CV_14 | GL opening balances |
| TRIAL_BALANCE_HISTORY | TB_02, TB_03 | Trial balance by month |
| OPENING_BAL, ACCOUNT_NO_MAPP, NEW_ACMAP, T_ACCOUNT, T_ACCOUNTBALANCE, TEMP_MASTER, G_ACMASTER, ACMASTER_MW, ACMASTER_NF | CV_09 to CV_13 | Migration tables of July 2024 (read once) |
| MAIIC_ORA_LIVE.CRM_ACMASTER_05082024 | CV_12d | Account master copy of 5 August 2024 (read once) |
| Data dictionary views: ALL_TABLES, ALL_TAB_COLUMNS, ALL_CONSTRAINTS | DD_01 to DD_03 | Structure, to detect vendor changes between months |

Row filters are applied in every query to the MAIIC and Mega Farm scheme identifiers (84 to 95, 140 to 145; 96 to 103), so no other customer's data is read.
