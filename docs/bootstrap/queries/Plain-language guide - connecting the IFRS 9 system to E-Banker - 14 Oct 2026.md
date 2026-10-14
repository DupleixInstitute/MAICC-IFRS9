---
kicker: MAIIC IFRS 9 and EIR system
title: Connecting the IFRS 9 system to E-Banker
date: 14 October 2026
prepared: Edward Mazibuko, Director, Data Analytics, Dupleix Institute
people: For Dr Thomson Kumwenda (CFO), MAIIC management, Finance and ICT; the technical version is the data-access request of the same date, and the two follow the same sections
status: Draft for discussion
logo: Dupleix logo.png
justify: yes
toc: yes
---

## 1. What we are asking for

Your IFRS 9 system works out expected credit losses, interest revenue and the regulatory figures from the loan data in E-Banker, MAIIC's banking system. At the moment that data is copied out of E-Banker by hand every month. We would like the IFRS 9 system to **read** it from E-Banker automatically instead. It would do this through a special login that can **look but never change anything**.

In practice we need five things from MAIIC:

1. A **read-only login** on E-Banker's database. It can read a fixed list of tables and nothing else.
2. A **firewall** opening for one computer to reach the database. That computer is the IFRS 9 system's server, on MAIIC's own network.
3. The **connection details**, meaning the database's address and name, plus a short session to test them with MAIIC ICT in the room.
4. If E-Banker keeps a **copy of the live database** for reporting, the login should go on that copy rather than the live one. That way the live system is never disturbed.
5. The same login set up **first on E-Banker's test copy**, so everything is proved there before it goes near real data.

**Please plan this now.** The building itself takes about a week. What takes the time is everything around it: approvals, the vendor's reply, a test copy of E-Banker, and two months of running the old and new methods side by side. The December 2026 year-end feeds the annual accounts and the audit, and we want the new method proven before then. For that we need approval by **Friday 23 October 2026**. The timetable is in section 9.2.

## 2. Why change

So far, every monthly batch of data has needed a second attempt. Sometimes the date format had not been set before the queries ran. Sometimes the export tool stopped part-way because a file was too big, and the result looked complete when it was not. And one table, E-Banker's daily overdue calculation, is simply too large to copy out by hand. An automatic read avoids all of these. It arrives on time each month, and it does not depend on someone remembering to run it.

The system already keeps a record of every delivery. It notes what was asked for, how many records came, a fingerprint of each file and the checks it passed. An automatic read keeps exactly the same record, so the auditors see the same trail however a month's data arrived.

### 2.1 Where things stand (14 October 2026)

- **How the data is copied today.** MAIIC ICT runs the queries on its own computer while Dupleix watches the screen over AnyDesk. Your team keeps its hands on its own database, and we can correct a setting on the spot. Ten batches have been run this way. The tenth was prepared on 14 October, while the system was being built.
- **The system is moving onto MAIIC's server.** MAIIC ICT now has access to the system's code and has already copied it onto a MAIIC server. The first thing to run there is the **bootstrap** (section 8). It rebuilds the system from every batch received so far and checks nine key figures, which proves the installation before anything else happens.
- **What that changes.** The system will sit on MAIIC's own network, so the automatic read happens entirely inside MAIIC. E-Banker data never leaves your network, and no outside party connects to the database. AnyDesk is used only for Dupleix's remote help.
- **What comes next.** The work this guide describes. That is the read-only login, tested first on E-Banker's test copy and then on the live system.

### 2.2 How each change reaches MAIIC's server

The system on MAIIC's server is a copy of the code. That means **nothing Dupleix builds reaches it until MAIIC ICT fetches it**, which is called a "pull". Each piece of new work goes through the same five steps before it is tested at MAIIC:

| Step | Who | What happens |
|---|---|---|
| 1. Build and prove | Dupleix | We build and check the new piece, and the bootstrap passes all nine key figures on Dupleix's copy |
| 2. Publish | Dupleix | We publish the new piece to the shared code with a note of what it does, and tell MAIIC ICT it is ready |
| 3. Fetch onto MAIIC's server | MAIIC ICT, Dupleix watching over AnyDesk | MAIIC ICT fetches the new code onto its server and lets it add any new database tables it needs |
| 4. Re-check on MAIIC's server | MAIIC ICT, with Dupleix | The bootstrap runs on MAIIC's server, and all nine key figures must agree there before any testing starts |
| 5. Test | MAIIC ICT and Dupleix | The tests for that step, as set out in section 9 |

If step 4 fails, nothing is tested. Dupleix fixes the problem and the steps start again. The live system is updated in exactly the same way, once the tests pass. Nobody ever copies files onto the server by hand. The history of the shared code shows exactly what was running on MAIIC's server on any given date.

## 3. The five ways data can reach the system

Data can travel from E-Banker to the IFRS 9 system in five ways. The only differences are **who** fetches it and **how** it travels. Whichever way is used, the data ends up in the same place and goes through the same checks. The hand method always stays available as a backup.

| Way | In everyday terms | Where it stands |
|---|---|---|
| **1. By hand, supervised** | MAIIC ICT runs the queries on its own computer while Dupleix watches over AnyDesk, then shares the files | This is what happens today |
| **2. Scheduled program** | A small program on a MAIIC computer fetches the data at a set time each month and drops the files into a shared folder | Ready on our side. It needs the login and the folder |
| **3. Sent to the system's "letterbox"** | The same program posts the files straight to a secure address on the IFRS 9 system, with a secret code, instead of using a folder | Ready on our side. It needs the secret code |
| **4. The system reads it directly** (what we are asking for) | The IFRS 9 system, on MAIIC's own server, connects to E-Banker and fetches the data itself | About a week to build once the login exists |
| **5. Through the vendor** | Virtual Galaxy provides a ready-made window onto the data, and the system reads from that | Only if the vendor will not allow way 4. It would probably be a paid job |

Ways 2 and 4 use the **same login**. So we would start with way 2, which needs no new software anywhere, while we wait for the vendor to agree to way 4.

![Figure 1. Five ways in, one checkpoint. However the data travels, it passes the same checks before the system uses it.](Feed routes and landing zone - diagram.png)

### 3.1 Way 3 explained: the "letterbox"

The IFRS 9 system has a secure address that accepts deliveries of data. Think of it as a locked letterbox. The technical name is an **endpoint**. Under way 3, MAIIC's program fetches the data and packs it into one compressed file. It then posts that file to the letterbox over an encrypted connection, together with a long secret code, called the **token**, which proves the delivery comes from MAIIC.

The moment a delivery arrives, the letterbox checks four things. Is the secret code right? Is the file a proper compressed file? Does anything inside it try to write somewhere it should not? And does it carry its list of contents? If any check fails, the delivery is refused on the spot and nothing is kept. If they all pass, the letterbox replies "received" straight away (technically "202 Accepted") and puts the delivery in a holding tray. Early the next morning the system takes it through the same checkpoint as every other delivery, described in section 4. Every delivery, accepted or refused, is recorded along with the address of the computer that sent it.

![Figure 3. Way 3 step by step. The MAIIC program reads E-Banker and posts the packed file to the system's letterbox, the letterbox checks it and replies at once, and the checkpoint takes it in the next morning.](Route 3 - push by API endpoint - sequence diagram.png)

Before way 3 is used for real, four safeguards would be added, which is about a day's work. The letterbox would accept deliveries only from MAIIC's own computer. The secret code could be changed without a gap. Deliveries would go through the checkpoint as soon as they arrive, rather than the next morning. And oversized deliveries would be refused with a clear message.

**Who opens the connection, and who holds the password.** Once the system is on MAIIC's server, every way keeps the connection inside your network. What differs is who holds the E-Banker password.

| Way | Who starts it | Who holds the E-Banker password | What the IFRS 9 system holds |
|---|---|---|---|
| 1. By hand | A person at MAIIC | MAIIC ICT | Nothing |
| 2. Scheduled program | MAIIC's scheduled task | A locked store on MAIIC's computer | Permission to read the shared folder |
| 3. Letterbox | MAIIC's scheduled task | A locked store on MAIIC's computer | Only the secret code for its letterbox |
| 4. Direct read | The IFRS 9 system's timetable | The IFRS 9 system's settings, on MAIIC's server | The read-only password |
| 5. Through the vendor | The IFRS 9 system's timetable | The IFRS 9 system's settings | The vendor's access details |

Way 4 is the simplest to run, because there is no program to look after at MAIIC. Ways 2 and 3 suit you if your policy is that the reporting system itself should not hold a database password.

## 4. The "landing zone": a checkpoint at the door

Before anything is calculated, all data from E-Banker passes through one checkpoint inside the system. We call it the **landing zone**. Picture the receiving bay of a warehouse. Every delivery is counted and inspected before it goes onto the shelves, and anything that fails is put to one side rather than used.

**Why it is built this way.** The same checks apply however the data arrives. Nothing is ever overwritten, so any month reported in the past can be reproduced exactly. Bad data is stopped at the door and never reaches the figures. Every figure can be traced back to the delivery, file and record it came from. And if the way data arrives changes, nothing changes in how the figures are calculated.

**What happens to each delivery, step by step.**

1. The delivery arrives with its list of contents. This says what each file is, how many records it holds and what its fingerprint is.
2. The system checks whether it has seen this exact delivery before. If it has, the delivery is not loaded twice.
3. Each file is checked on its own. Is it complete and unaltered, can its dates and amounts be read, and does it answer a known question? Then the files are checked against each other. Every payment must belong to a known loan, balances must agree with the ledger, every loan must have its month-end record, and fees must agree with the trial balance.
4. If a serious check fails, the whole delivery is **set aside** and the problem is named. It is kept so it can be examined, but no figure uses it.
5. If everything passes, each record is stored exactly as E-Banker sent it. A corrected record is added as a new version beside the old one, never on top of it.
6. The system notes how far it has got with each question. This marker is the **watermark**, and it means the next delivery fetches only what is new.
7. Only then are the monthly loan book and all the calculations built from the stored records.

### 4.1 Inside the checkpoint: four record books

The checkpoint keeps four record books in the system's own database.

- **The register** is the list of questions the system is allowed to ask E-Banker. There are 114 of them after the tenth batch (section 4.2). Any question not in the register is turned away.
- **The deliveries book** has one line for each delivery. It shows when the delivery came, which way it came, whether it was accepted, the result of every check, and how far it got.
- **The files book** has one line for each file in a delivery. It shows how many records the file promised, how many were stored, and how many were new, changed or already held.
- **The records book** holds the records themselves. Each is kept exactly as E-Banker sent it, and any later correction is added as a new version beside the old one.

Figure 4 follows one real payment through the four books. It is MUSECO's K200,000,000 repayment of 24 July 2025. In E-Banker it is ledger record 194427. It arrived in delivery 1, in a file that promised 3,640 records and delivered 3,640. It sits in the records book as version 1, with all 83 of E-Banker's columns just as they were. Suppose E-Banker later corrected it to K195,000,000. The original would be kept and marked as replaced, and the corrected one added as version 2. October's figures would still reproduce exactly, and November's would use the correction.

![Figure 4. One payment followed through the four record books of the checkpoint, and what happens if E-Banker later corrects it.](Landing zone - the four tables, one payment followed through - diagram.png)

**Questions your team can answer from the four books**, without asking Dupleix:

| Question | Where the answer is |
|---|---|
| Did this month's delivery arrive, and was it accepted? | The deliveries book |
| Was any file short or altered? | The files book. Compare records promised with records stored, and check the fingerprint |
| Which E-Banker record is behind a figure in a report? | The records book, which gives the E-Banker record number, its delivery and its version |
| Has E-Banker changed a record already received? | The records book. Look for any record with a second version, and when the first was replaced |
| What exactly did we ask E-Banker for? | The register, which shows each question with its version and fingerprint |

The four books already exist in the system's test copies and hold the data received so far. The test copy has 88,977 records from 23 deliveries. When the system is set up for real on MAIIC's server, the books start empty.

### 4.2 What the register is, and how the questions work in Oracle

**The database and its language.** E-Banker keeps its records in an **Oracle** database. You can think of it as a very large, well-organised filing system made up of **tables**. Each table is like a spreadsheet. Its rows are records, such as one loan or one payment, and its columns are facts about them, such as account number, date and amount. You get information out by asking questions in **SQL** (Structured Query Language), the standard language of databases. A question has three main parts. SELECT says which columns you want, FROM says which table, and WHERE says which rows. Here is an example.

`SELECT new_ac_number, transaction_date, transamt FROM vgcbs.cumvouch WHERE ac_glcode = '1050101' AND transaction_date >= DATE '2026-09-01';`

In plain words, this says: "give me the account, the date and the amount of every posting on MAIIC's loan ledger account 1050101 since 1 September 2026". A question that starts with SELECT can only read. The read-only login is not allowed to send any other kind of statement, so even a mistyped question cannot change anything.

**What Oracle does with a question.** When a question arrives, Oracle first checks that the login is allowed to read that table. Those are the permissions set up in section 6. Next it works out the quickest way to find the rows, using its indexes much as you would use the index of a book. Then it reads the rows and sends back the answer as rows and columns. Oracle answers from the records **as they stood at the moment the question started**. So the answer hangs together even while staff are posting. A reading question also **locks nothing**, which means E-Banker's users carry on as normal. The questions run early in the morning and fetch only what is new, so they finish quickly.

**What the register is.** The register is the IFRS 9 system's controlled list of **every SQL question it is allowed to ask E-Banker**. After the tenth batch there are 114. Each entry is like a library card for one question.

| On the card | Example (the ledger question) | Why it is there |
|---|---|---|
| Code and title | P1_01, "Ledger, every posting" | It names the question on every batch sheet, file and report |
| The table it reads | CUMVOUCH, E-Banker's ledger | It shows exactly what is being read |
| E-Banker's own record number (the key) | CUMVOUCH_DET_ID | It lets the system recognise a record when it arrives again. The record is stored once, and later corrections become new versions (section 11) |
| The account and date columns | NEW_AC_NUMBER, TRANSACTION_DATE | They file each record under its loan and its month |
| Everything, or only what is new | Only what is new | It tells the system to ask only for records after the last one received, which is the watermark |
| Version | 1 | The number goes up whenever the question is changed |
| The SQL text and its fingerprint | The exact question, and a code calculated from it | It proves that the question being run is the approved one |

**Why the register matters.** The same question is asked every month, so the answers can be compared. If a file comes from a question that is not in the register, or from an out-of-date version, the checkpoint refuses it. The fingerprint lets anyone confirm that the SQL run at MAIIC is the SQL in the register. The register also lives in the system's controlled code, so a question can only be changed through the release steps in section 2.2. Under the hand method, the register's questions are what gets printed on each batch sheet for MAIIC ICT to run. Under the automatic read, the system will take each question from the register and add "only records after the watermark". It will then send the question to Oracle itself and bring the answer in through the checkpoint.

## 5. What the login will and will not do

| It will | It will not |
|---|---|
| Ask only the 114 registered questions, all of which are kept in MAIIC's copy of the code | Change, add or delete anything |
| Run once a month, early in the morning on the first working day after month-end. The overdue table can also be read daily if you wish | Run any of E-Banker's own programs or change its settings |
| Read a few megabytes a month. That covers MAIIC's own loan schemes and the Mega Farm programme, with their payments, balances, repayment schedules, arrears and the trial balance | Read any other customer's accounts, or any table that is not on the agreed list |
| Connect from one named computer on MAIIC's network. MAIIC ICT holds the password and changes it every 90 days | Connect from anywhere else, or be used by a person |

MAIIC ICT can see everything the login does and can switch it off at any moment. Whatever happens, the hand method keeps working.

## 6. Setting up the login: the steps for MAIIC ICT

Your ICT team does the same steps twice. The first time is on E-Banker's **test copy**. The second is on the **live system**, or its reporting copy, and only once the tests on the test copy have passed. The technical document gives the exact commands. Here is what each step achieves.

1. **Agree the names.** These are the login, a "role" that holds its permissions, and a profile that limits it. The login connects from the MAIIC server the system is installed on.
2. **Create the limits.** The profile allows no more than two connections at a time. It locks the login after five wrong passwords and forces a new password every 90 days.
3. **Create the login.** It can sign in but owns nothing. It has no space of its own, so it cannot create anything.
4. **Give it read permission, table by table.** There is one line for each table on the agreed list, 51 tables in all, set out in the annex. They are all held in one role, so the whole access can be removed with a single command. If a table is not where we expect, MAIIC ICT tells us and we correct the list.
5. **Switch on monitoring.** E-Banker's database records every sign-in and every question the login asks. MAIIC can see everything it does.
6. **Open the network path.** This is one firewall rule. The system's server may reach the database, and nothing else changes.
7. **Test the connection.** This is done from the computer that will use the login. Can it reach the database over the network? Can the login sign in? Then a short test question asks "who am I, which database am I in, which computer am I calling from, and what time is it". The database name is the answer that matters most. It must say **test** at the test stage and **live** at the live stage, never the other way round. A first real question then counts MAIIC's loan accounts.
8. **Prove it is read-only.** The login lists its own permissions, which should be only "sign in". It must see only the agreed tables. A harmless attempt to delete must be refused, and so must an attempt to create a table. The two checks from the tenth batch must also pass. They show which tables it can read, and that every one of the 35 tables answers. The results are saved as evidence.
9. **Hand over safely.** The database address may be emailed, but the password never is. MAIIC ICT types it into the system's settings. Under ways 2 and 3 it is kept in a locked store on MAIIC's computer instead.
10. **For way 2 or 3 only.** Way 2 needs a small Oracle tool and a scheduled task on a MAIIC computer. Way 3 needs permission for that computer to post to the system's letterbox, and a secret code kept in Windows' locked store. It is tested once with a wrong code to prove that is refused.
11. **Look after it.** The password changes every 90 days. The login can be paused at once and resumed, or removed entirely. Any new table needs a one-line permission that MAIIC approves.
12. **Repeat on the live system** once the tests pass. Use a new password, and run the connection test and the read-only proof again.

### 6.1 How the system will hold the connection details safely

The system needs to know where E-Banker's database is and how to sign in to it. This is how those details are kept.

- **In one settings file on MAIIC's server, not in the code.** The address, database name, login name and password go in the system's private settings file on MAIIC's server. They are never written into the code. So when new code is fetched onto the server (section 2.2), no password ever travels with it. That file is already kept out of the shared code.
- **Only MAIIC ICT types the password.** Dupleix may watch the session but never sees or keeps the password. It is never sent by email or chat.
- **The file is locked down.** Only the system itself and the server's administrators can open it. If your policy requires it, the file can also be encrypted, with MAIIC ICT holding the key.
- **Test and live are kept apart.** The test copy of the system has its own settings file, which points only at E-Banker's test copy. The live system's file points only at the live database. The connection test in step 7 names the database each one reached, so any mix-up shows straight away.
- **The details are used for one job only.** Only the part of the system that fetches data uses the login, and no screen shows the password. The system asks only the questions in the register. It also opens every session as read-only, a second guard on top of the read-only login.
- **No passwords in records or backups.** The audit trail records what was read and when, never the password. The settings file is not part of the database backups. MAIIC ICT keeps the password in its own password store.
- **Encrypted on the network if you want it.** The connection already stays inside MAIIC's network. Even so, encryption can be switched on from the system's side without changing anything in E-Banker.
- **Easy to change or stop.** Every 90 days, when the password changes, MAIIC ICT updates the settings file and runs a one-line connection test. Locking the login in E-Banker stops the feed at once.

## 7. What we need from the vendor

The login is within MAIIC's own control. Even so, we would ask Virtual Galaxy to confirm a few things in writing. First, that a read-only login used this way does not affect their support agreement. Second, whether they would prefer us to read a copy of the database rather than the live one. Third, whether they need any extra monitoring, and whether there is any cost. If they will not allow it, we would ask them to quote for way 5. Two further questions go to them either way. One is their report of restructured loans, because the restructure table in E-Banker is empty although the migration records show eight. The other is where write-offs are recorded, because the write-off table is empty although the ledger shows write-off postings.

## 8. The bootstrap: our test copy and safety check

The **bootstrap** is a single command. In about two minutes it builds a complete copy of the IFRS 9 system from scratch, on a separate test database. It never touches the live system, and nothing approved inside it counts as a MAIIC approval. This is what it does, in order.

1. **Wipes** the test database and creates it afresh.
2. **Loads** every batch MAIIC has sent, along with Finance's fee template, take-on workbook and trial balances. All of it goes through the same checkpoint as the live system.
3. **Builds** the monthly loan books, contract details, fees and interest schedules.
4. **Runs every calculation.** That means staging, probability of default, loss given default, expected credit loss, interest revenue, forward-looking adjustments, stress tests, reports and the general ledger reconciliation.
5. **Checks nine key figures** that must agree exactly with an independent source. It compares the interest posted in the ledger. It compares loan balances with E-Banker's own loan book report, for every account and month. It checks the year-end interest entries on two income accounts. And it compares loan balances with the trial balance on each of the five loan accounts in the general ledger. Today all nine agree.

![Figure 2. The bootstrap builds the system on a test copy and checks nine key figures. A change reaches the live system only when they agree.](Bootstrap - test harness and reference copy - diagram.png)

The bootstrap plays five parts in this work.

| Part | What it means |
|---|---|
| **The history** | The batches copied by hand since 7 October are the history up to the switch. The bootstrap builds that history the same way every time, and the automatic read carries on from where it ends |
| **The test copy** | The automatic read is connected first to the bootstrap's database, never to the live one |
| **The yardstick** | The bootstrap already holds each month as it arrived by hand. The automatic read must reproduce it exactly, record for record |
| **The safety check** | Before any change reaches the live system, including each pull onto MAIIC's server (section 2.2), the bootstrap is run and all nine key figures must still agree |
| **The fire drill** | Every quarter, a backup is restored and checked in the same way |

## 9. Test first, then go live

Nothing touches real E-Banker data until the whole arrangement has worked on a copy.

1. **Install (stage 0).** The system is set up on MAIIC's server and the bootstrap is run there. All nine key figures must agree.
2. **Approve (stage A).** Dr Thom approves, and MAIIC writes to the vendor.
3. **Test on a copy (stage B).** MAIIC ICT creates the login on E-Banker's test copy. Each piece of new work is fetched onto MAIIC's server and re-checked, as in section 2.2. Way 2 runs first, then way 4. MAIIC ICT and Dupleix sign off seven tests. (1) The connection test passes, and nothing outside the agreed list can be read. (2) For the same month, the automatic read gives exactly the same records as the hand method. (3) Reading the same month again adds nothing. (4) A deliberately broken file is caught, and nothing downstream moves. (5) A monthly read takes under 15 minutes and puts no noticeable load on E-Banker. (6) A backup can be restored and carried on from. (7) When the bootstrap is re-run with the automatically read data, it still agrees on all nine key figures.
4. **Go live (stage C).** The login is created on the live system and the setting is switched to way 4. That switch needs two people to approve it.
5. **Run side by side.** For two month-ends both methods are used, and they must agree exactly before the hand method is retired. Even then it stays available as a backup.

If the vendor declines way 4, the same steps run on way 2, which needs nothing from the vendor.

### 9.1 Why this must be planned now

The building takes about a week. The time goes on things outside the build, and they have to happen in a fixed order.

- **Approvals and the vendor take weeks.** Dr Thom's approval, the vendor's written confirmation and MAIIC's own change control for a new login and a firewall rule all have to come first.
- **A test copy of E-Banker has to exist.** If there isn't one, it must be made from a backup, which takes ICT and vendor time.
- **Proof needs real month-ends, and there is only one a month.** If November is missed, the switch moves from January to February. Each missed month-end pushes it back by a month.
- **Year-end matters most.** The December 2026 year-end feeds the annual accounts and Deloitte's audit. Running both methods side by side on it gives the auditors the strongest evidence.
- **Year-end change freezes.** Many ICT teams freeze changes over December. So everything that changes MAIIC's systems is planned to finish by 27 November.
- **The hand method is a risk every month it remains the only one.** Every batch so far has needed a re-run, and the overdue table cannot be copied by hand at all.

### 9.2 Proposed timetable

This assumes approval by **Friday 23 October 2026**. Each week of delay pushes back every later date, and missing a month-end moves the switch by a whole month.

| When | What |
|---|---|
| 15 to 23 October | The system is set up on MAIIC's server and the bootstrap is run there. Dr Thom approves, and MAIIC writes to the vendor |
| 26 October to 6 November | MAIIC ICT sets up the test login and runs the connection test. The scheduled program is fetched onto MAIIC's server and installed on the test copy. We expect the vendor's reply by 6 November |
| 2 November | October month-end copied by hand, as now |
| 2 to 13 November | Dupleix builds the direct read (way 4) |
| 13 November | The new work is fetched onto MAIIC's server and the bootstrap is re-run there |
| 16 to 20 November | Everything is tested on the copy, with sign-off on 20 November |
| 23 to 27 November | The login is created on the live system, ahead of any year-end change freeze |
| 1 to 4 December | The November month-end is read both ways, side by side |
| 4 to 8 January 2027 | The December year-end is read both ways, side by side |
| 18 to 22 January 2027 | Switch to the direct read. The hand method stays as a backup |
| 1 February 2027 | The first month-end read by the direct read alone |

![Figure 5. The proposed timetable, from setting the system up on MAIIC's server in October 2026 to the first month-end read automatically in February 2027.](Route 4 - proposed build and test timetable - gantt.png)

## 10. How the data is kept up to date

Every question the system asks E-Banker is written down in the register (section 4.2), with a version number and a fingerprint. The register is kept in the system's code. To change a question you change the code, not a worksheet. There are two kinds of table.

- **Reference tables are read in full each time.** These are the list of accounts, the loan terms, the repayment schedules in force, security and rates. They are small, and only new or changed records are stored.
- **History tables are read from where the last delivery stopped.** These are payments, balances, the monthly loan book report, the overdue calculation and interest. The watermark records the last record received, so the next read fetches only what is new. Under the hand method the watermark is printed on the batch sheet. Under the automatic read the system applies it itself.

A month can be read again at any time, for example after a correction or a late posting, and the same rules apply. If a question is changed, it gets a new version number. Any file produced by an old version is refused, so an outdated query cannot feed the system without anyone noticing.

## 11. How duplicates are handled

Duplicates are caught automatically at three levels, and nothing is ever overwritten.

| Level | How it is recognised | What happens |
|---|---|---|
| **The whole delivery** | Its fingerprint | A delivery that has already been loaded is recognised and not loaded again |
| **Each file** | Its fingerprint, its record count, and no record number appearing twice inside it | A file that is short, altered or doubled is refused, and the problem is named |
| **Each record** | E-Banker's own record number and a fingerprint of its contents | If the number and contents are the same, it is skipped. If the number is the same but the contents are new, it is stored as a new version and the old one kept. A new number is simply stored |

So when two questions overlap, such as the month-end and the daily overdue records, each record is still stored only once. Every delivery shows how many records were new, changed or already held. A delivery that is a complete duplicate shows "nothing new" rather than being counted twice.

## 12. The controls around the data

- **Checks before anything moves.** A delivery that fails a serious check is set aside in full, and nothing downstream moves.
- **Only accepted deliveries are used.** Every calculation records which deliveries it used, so any figure can be traced to its source.
- **A full record.** Every delivery, accepted or set aside, is recorded with who or what delivered it. The fingerprints let an auditor confirm that a file is the one E-Banker produced.
- **Closed months stay closed.** Once a month is locked, a late delivery still lands, but its effect is taken in the open month and the difference is shown.
- **Two people to change the way data arrives**, with an effective date. The hand method always stays available.
- **Changes to the questions** happen only through the controlled code, and are checked on arrival.
- **Changes to the system** happen only through the release steps in section 2.2.
- **The login belongs to MAIIC.** MAIIC ICT creates it, holds it, changes it and can revoke it, and it shows up in E-Banker's own records.

## 13. Keeping the data safe: storage and backup

**Where it is kept.** Nothing is stored in E-Banker, because the login only reads. The data lives in the IFRS 9 system's own database on MAIIC's server. The checkpoint's records take 85 MB, covering about two years of MAIIC's loans today. The calculated tables take 24 MB and can be rebuilt from the records. The delivered files themselves take 87 MB so far. That is about 110 MB in all.

**Growth.** Each month adds 3 to 5 MB. The full Mega Farm ledger will add 300 to 400 MB, once. The daily overdue record adds 120 to 150 MB a year for MAIIC's own loans. For the Mega Farm programme (3,675 accounts) only the month-end records are read, because the daily ones would come to 1.3 million a year. The database should stay under 2 GB for five years. The server needs at least 20 GB free, with an alert when it is 80 percent full.

**How long it is kept.** The data is kept for the life of the system and never deleted, because past figures must stay reproducible. That is longer than the seven years required for accounting records.

| Backup | How often | How many kept | Where |
|---|---|---|---|
| A full copy of the database | Every night, after the morning delivery | 30 daily, 12 month-end and 7 year-end copies | On the server and off it, in MAIIC's backup store |
| A copy taken when each month is closed | Monthly | All, permanently | Off the server, with the month's delivered files |
| The delivered files | As each arrives | All, permanently | MAIIC's document store |
| The questions and the system's code | Every change | The full history | The shared code, which MAIIC holds a copy of |

Every backup is **encrypted**, because it holds customer loan data. A **test restore** is done every quarter. MAIIC ICT holds the passwords and the backup key, not Dupleix. If the database were ever lost, it would be restored from the previous night's copy, and anything since then would be read again from E-Banker. The watermarks show where to start, and the duplicate rules make the re-read safe.

## 14. Who does what, and who to contact

| Who | What |
|---|---|
| Dr Thom | Approve the request by 23 October 2026 |
| MAIIC ICT | Fetch each release onto MAIIC's server and re-run the bootstrap. Create the read-only login, first on the test copy and later on the live system. Open the firewall path, run the connection test and the read-only proof, and write to the vendor |
| Virtual Galaxy | Give written confirmation (section 7) |
| Dupleix | Build and publish each piece, watch each step over AnyDesk, run the tests with MAIIC ICT, and run the two month-ends side by side |

For questions, please contact Edward Mazibuko, Director, Data Analytics, Dupleix Institute. The vendor is welcome to send technical questions to Dupleix directly, with MAIIC ICT copied in.

## Annex: what the login reads, and why

The technical document's annex has the full list of 51 tables, with the questions that use each one. Here they are grouped by what they hold.

| Group | E-Banker tables | Why the system needs them |
|---|---|---|
| **Customers, accounts and loan terms** | Account master, loan master, customer master, scheme master | To know which loans belong to MAIIC and the Mega Farm programme, with their amounts, rates, terms and repayment holidays |
| **Money movements** | The ledger (every posting) | Disbursements, interest, repayments, write-offs and fees. These are the actual cash flows behind every figure, including loans paid off from new loans |
| **Balances and the monthly loan book** | Balance history, E-Banker's stored loan book report | Each loan's balance and arrears at every month-end, checked against the ledger |
| **Repayment schedules** | Instalment chart, instalment plans, disbursement schedules | What each borrower should pay and when, and when undrawn amounts become exposure |
| **Arrears** | E-Banker's daily overdue calculation | How many days each loan is overdue, checked separately from the loan book report |
| **Status and closures** | Status history, closures, change log | Whether a loan is active, closed or written off, and when its terms changed |
| **Rates and interest** | Rate set-up, interest products, prime rate history, interest calculated, accrued and waived | The contractual rate and how it changed, and E-Banker's own interest compared with the system's |
| **Fees** | Disbursement charges, charge postings, loan application records | Fees charged at the start of a loan, which change the effective interest rate |
| **Security** | Security details, guarantors | Collateral, which reduces the expected loss |
| **Restructures and write-offs** | Reschedule details, write-off records | Loans whose terms were changed, and loans written off |
| **The general ledger** | GL chart, GL opening balances, trial balance history | To tie the loan book to MAIIC's accounts each month |
| **History, read once** | Older ledger copies, earlier repayment schedules, the July 2024 migration tables, the account list of 5 August 2024 | The history before E-Banker's July 2024 migration, and how old account numbers match the new ones |

Every question is limited to MAIIC's own loan schemes and the Mega Farm programme. No other customer's data is read.

## Glossary

| Term | Meaning |
|---|---|
| **E-Banker** | MAIIC's core banking system, where loans, payments and the general ledger are recorded. Supplied and supported by Virtual Galaxy. |
| **IFRS 9 system** | The system Dupleix is building for MAIIC to calculate expected credit losses, interest revenue and the regulatory figures from E-Banker's data. |
| **Oracle** | The database software E-Banker stores its data in. |
| **SQL** | Structured Query Language, the standard language for asking a database questions. A question that starts with SELECT can only read. |
| **Database** | The organised store of records behind a system. E-Banker has one, and the IFRS 9 system has its own. |
| **Schema** | A named section of a database that owns a set of tables. E-Banker's tables are in the VGCBS and MAIIC_ORA_LIVE schemas. |
| **Server** | A computer that runs a system for others to use. The IFRS 9 system is being set up on a MAIIC server. |
| **Production (live)** | The real system and data used for MAIIC's reporting. |
| **Test copy (test environment, UAT)** | A separate copy of a system used for trying things out, so mistakes cannot affect the real data. |
| **Replica (reporting copy)** | A continuously updated copy of the live database, used for reporting so the live system is not disturbed. |
| **Code repository** | The shared, controlled store of the system's code. MAIIC ICT has access and has copied it onto MAIIC's server. |
| **Pull (fetch)** | Copying the latest published changes from the code repository onto MAIIC's server. Nothing new reaches MAIIC's server until it is pulled. |
| **AnyDesk** | Remote-desktop software that lets Dupleix watch, and help with, work on a MAIIC computer. |
| **Query (question)** | A precise request for data from a database, written in SQL, such as "all payments on MAIIC's loans since 1 September". |
| **Register** | The controlled list of every question the system may ask E-Banker, 114 after the tenth batch (section 4.2). |
| **Key (record number)** | The number E-Banker gives each record, unique within its table. It is how the system recognises the same record when it arrives again. |
| **Index** | A database's way of finding rows quickly, like the index of a book. |
| **Read-only login** | A username and password that can look at data but cannot change it. |
| **Role** | A named set of permissions given to a login, so all of them can be granted or removed at once. |
| **Grant** | Permission given to a login to read a particular table. |
| **Table** | One set of records in a database, such as the list of loan accounts or the list of payments. |
| **Firewall** | The network gatekeeper that decides which computers may connect to which. |
| **VPN** | A secure, encrypted connection between two organisations' networks, used for Dupleix's remote support. |
| **Feed route (way)** | The way data travels from E-Banker to the IFRS 9 system. Section 3 describes all five. |
| **Batch (pack)** | One delivery of data. It has one file per question, plus a list of what the files should contain. |
| **Manifest** | The list that comes with each batch, stating how many records each file holds and its fingerprint. |
| **Fingerprint (hash)** | A short code calculated from a file's exact contents. If even one character changes, the code changes, so it proves a file is complete and unaltered. |
| **API** | A way for one computer system to send requests or data to another automatically. |
| **Endpoint ("letterbox")** | A specific secure address on a system that accepts deliveries from another computer. |
| **Token (secret code)** | A long secret code sent with each delivery to prove who it comes from. Think of it as a password for a computer rather than a person. |
| **HTTPS** | The encrypted form of web traffic, so data cannot be read on its way. |
| **Push and pull** | In a push, the sender delivers the data (ways 2 and 3). In a pull, the receiver fetches it (ways 4 and 5). |
| **202 Accepted** | The letterbox's reply when a delivery has been received and is waiting to be checked. It does not yet confirm that the delivery passed. |
| **Landing zone (checkpoint)** | The place inside the IFRS 9 system where every delivery is checked and stored before any calculation uses it. |
| **Gates (checks)** | The tests every delivery must pass at the checkpoint. |
| **Quarantine (set aside)** | Keeping a delivery that failed a check for inspection, without ever using it. |
| **Version** | A record, or a question, as it stood at a point in time. When either changes, the old version is kept and the new one added. |
| **Watermark** | A marker of the last record already received, so the next delivery fetches only what is new. |
| **Bootstrap** | The command that rebuilds the whole IFRS 9 system from scratch on a test database and checks the key figures. |
| **Golden ties (key figures)** | The nine figures the bootstrap checks against an independent source. All of them must agree exactly. |
| **Maker-checker** | A control where one person proposes a change and a second person must approve it. |
| **Period lock** | Closing a reporting month so its figures cannot change afterwards. |
| **Change freeze** | A period, often over year-end, when ICT makes no changes to live systems. |
| **Audit trail** | The record of who or what did what, and when, kept for the auditors. |
| **Trial balance** | The list of every general ledger account's balance at a date. |
| **Mega Farm** | The farming programme whose loans MAIIC administers alongside its own loan book. |
| **Virtual Galaxy** | The company that supplies and supports E-Banker. |
| **Dupleix** | Dupleix Institute, the firm building the IFRS 9 system for MAIIC. |
