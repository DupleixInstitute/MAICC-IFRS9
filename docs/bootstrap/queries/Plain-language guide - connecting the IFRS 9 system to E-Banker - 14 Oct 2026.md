---
kicker: MAIIC IFRS 9 and EIR system
title: Connecting the IFRS 9 system to E-Banker
subtitle: A plain-language guide to the data-access request
date: 14 October 2026
prepared: Edward Mazibuko, Director, Data Analytics, Dupleix Institute
people: For Dr Thomson Kumwenda (CFO), MAIIC management and Finance; the technical version is the data-access request of the same date
status: Draft for discussion
---

## 1. The short version

MAIIC's new IFRS 9 system calculates expected credit losses, interest revenue and the regulatory figures from the loan data held in E-Banker, MAIIC's banking system. Today that data is copied out of E-Banker **by hand**: Barry runs a set of queries every month, saves the results as files and shares them with us. It works, but it is slow, depends on one person, and has gone wrong several times in small ways that each cost a re-run.

We are asking MAIIC to let the IFRS 9 system **read** the data from E-Banker automatically, using a special login that can **look but never change anything**. We would set it up and test it on a copy of E-Banker first, and only switch it on for the real system once it has proved itself. The hand method stays available as a backup.

What we need: Dr Thom's approval, a login created by Barry's team, and written confirmation from Virtual Galaxy (the company that supplies E-Banker) that this does not affect their support.

## 2. Why change

Every monthly batch of data so far has needed a second attempt for one of three reasons:

- the date format was not set before the queries ran, so dates arrived in the wrong format;
- the export tool stopped part-way because a file was too big, and produced a file that looked complete but was not;
- one table (E-Banker's daily overdue calculation) is simply too large to copy out by hand.

An automatic read avoids all three. It also means the data arrives on time each month without anyone having to remember to run it.

## 3. The five ways data can reach the system

There are five ways the data can travel from E-Banker to the IFRS 9 system. They differ only in **who** fetches the data and **how** it travels. Whatever the way, the data ends up in the same place and is checked in the same way.

| Way | In everyday terms | Where it stands |
|---|---|---|
| **1. By hand** | Barry runs the queries and shares the files | What happens today |
| **2. Scheduled script** | A small program on a MAIIC computer fetches the data at a set time each month and drops the files in a shared folder | Ready on our side; needs the login and the folder |
| **3. Sent to the system's "letterbox"** (the endpoint) | The same program posts the files straight to a secure address on the IFRS 9 system, with a security key, instead of using a folder | Ready on our side; needs a security key |
| **4. The system reads it directly** (what we are asking for) | The IFRS 9 system connects to E-Banker itself, over the secure link that already exists, and fetches the data | About a week to build once the login exists |
| **5. Through the vendor** | Virtual Galaxy provides a ready-made window onto the data, and the system reads that | Only if the vendor will not allow way 4; likely a paid job |

Ways 2 and 4 use the **same login**, so we would start with way 2 (no new software anywhere) while waiting for the vendor's agreement to way 4.

![Figure 1. Five ways in, one checkpoint. Whatever way the data travels, it passes the same checks before the system uses it.](Feed routes and landing zone - diagram.png)

### Way 3 explained: the "letterbox"

The IFRS 9 system has a secure address on its web server that accepts deliveries of data, rather like a locked letterbox. In technical terms it is called an **endpoint**. Under way 3, MAIIC's small program fetches the data from E-Banker, packs it into a single compressed file, and posts it to this letterbox over an encrypted connection, together with a long secret code (the **token**) that proves it comes from MAIIC.

The letterbox checks four things the moment a delivery arrives: that the secret code is right, that the file is a proper compressed file, that nothing inside it is trying to write where it should not, and that it carries its list of contents. If any check fails, the delivery is refused on the spot. If all pass, the system replies "received" straight away and puts the delivery in a holding tray. Early the next morning the system takes it through the same checkpoint as every other delivery (section 4) before any figure uses it.

The main attraction of way 3 is that **MAIIC makes every connection itself**. The IFRS 9 system never connects into MAIIC's network and never holds an E-Banker password; it only holds the secret code for its own letterbox. If MAIIC's policy is that no outside system may connect to the banking database, ways 2 and 3 meet it; way 4 does not.

![Figure 3. Way 3 step by step: the MAIIC program reads E-Banker, posts the packed file to the system's letterbox, the letterbox checks it and replies at once, and the checkpoint takes it in the next morning.](Route 3 - push by API endpoint - sequence diagram.png)

Before way 3 is used for real, we would add four safeguards, about a day's work: accept deliveries only from MAIIC's server, allow the secret code to be changed without a gap, take deliveries through the checkpoint as soon as they arrive, and refuse oversized deliveries clearly.

## 4. The "landing zone": a checkpoint at the door

All data from E-Banker passes through one checkpoint inside the system before anything is calculated from it. We call it the **landing zone**. Think of it as a receiving bay in a warehouse: every delivery is counted and inspected before it goes onto the shelves, and a delivery that fails inspection is set aside, not used.

At the checkpoint the system:

- **counts and fingerprints every file**, to be sure it is complete and is the file E-Banker produced;
- **checks that the pieces fit together**, for example that every loan payment belongs to a loan the system knows about, and that the loan balances agree with the general ledger;
- **refuses a delivery that fails**, keeping it aside so the problem can be seen, but never letting it reach the figures;
- **keeps every version of every record**, so if E-Banker later changes a record, the old version is still there. Any month reported in the past can be reproduced exactly as it was.

This is what makes the change safe: switching from the hand method to the automatic read changes nothing about how the figures are calculated, only how the data arrives.

## 5. The login we are asking for

Barry's team would create one login on E-Banker's database with these limits:

- it can **read** a fixed list of tables (the loan accounts, their payments, balances and repayment schedules, and the trial balance) and **nothing else**;
- it **cannot change, add or delete** anything, and cannot run any of E-Banker's programs;
- it only reads the records of MAIIC's own loan schemes and the Mega Farm programme;
- it can only connect from one named computer, over the secure link MAIIC already has;
- MAIIC holds the password, changes it every 90 days, can see everything the login does, and can switch it off at any moment.

The technical document gives Barry's team the exact steps and a short test to prove the login really is read-only before it is handed over.

## 6. What we need from the vendor

The login is within MAIIC's own control, but we ask Virtual Galaxy to confirm in writing that it does not affect their support agreement; whether they prefer us to read a copy of the database rather than the live one; whether they need any extra logging; and whether there is any cost. If they will not allow it, we ask them to quote for way 5 instead.

## 7. Test first, then go live

Nothing touches the real E-Banker data until the whole arrangement has worked on a copy.

1. **Approve.** Dr Thom approves; Barry asks the vendor.
2. **Test on a copy.** Barry's team creates the login on E-Banker's test copy. We connect it to the IFRS 9 system's own test copy (the "bootstrap", below). We run way 2, then way 4, and check seven things together, including that nothing outside the agreed list can be read, that the automatic read gives exactly the same data as the hand method, and that a broken file is caught.
3. **Go live.** The login is created on the real E-Banker, and the setting in the system is switched to way 4, which needs two people to approve.
4. **Run side by side.** For two month-ends, both the hand method and the automatic read are used, and they must agree exactly before the hand method is retired. It remains available as a backup.

## 8. The bootstrap: our test copy and safety check

The **bootstrap** is a single command in the IFRS 9 system that builds a complete copy of the system from scratch, on a separate test database, in about two minutes. It loads every batch of data MAIIC has sent, runs every calculation, and then checks nine key figures that must agree exactly with an independent source (for example, that the loan balances agree with the trial balance on each loan account in the general ledger). Today all nine agree. It never touches the real system, and nothing approved inside it counts as a MAIIC approval.

It plays four parts in this project: it is the **test copy** the automatic read is first connected to; it is the **yardstick**, because the automatic read must reproduce exactly what arrived by hand; it is the **safety check** run before any change reaches the live system; and it is the **fire drill** for restoring the system from backup each quarter.

![Figure 2. The bootstrap builds the system on a test copy and checks nine key figures; only when they agree does a change reach the live system.](Bootstrap - test harness and reference copy - diagram.png)

## 9. Keeping the data safe

- **Storage.** The data is small: the whole system takes about 110 MB today and should stay under 2 GB for five years. It is kept permanently, because past figures must be reproducible; that is longer than the seven years required for accounting records.
- **Backups.** A full copy every night, kept off the server; a permanent copy each time a month is closed; a test restore every quarter. Backups are encrypted, and MAIIC holds the keys.
- **Duplicates.** If the same data arrives twice, it is recognised and not counted twice.
- **Control.** Which way the data arrives is a setting that needs two people to change. Changes to the queries go through a controlled process, and an out-of-date query is refused. Every delivery, accepted or refused, is recorded.

## 10. What we are asking MAIIC to do

| Who | What |
|---|---|
| Dr Thom | Approve the request |
| Barry and ICT | Create the read-only login, first on the test copy and later on the live system, following the steps in the technical document; open the network path from one computer |
| Barry | Ask Virtual Galaxy for written confirmation (section 6) |
| Dupleix | Build the automatic read, run the tests with Barry, and run the two month-ends side by side |

## Glossary

| Term | Meaning |
|---|---|
| **E-Banker** | MAIIC's core banking system, where loans, payments and the general ledger are recorded. Supplied and supported by Virtual Galaxy. |
| **IFRS 9 system** | The system Dupleix is building for MAIIC to calculate expected credit losses, interest revenue and the regulatory figures from E-Banker's data. |
| **Oracle** | The database software E-Banker stores its data in. |
| **Database** | The organised store of records behind a system. E-Banker has one; the IFRS 9 system has its own. |
| **Production (live)** | The real system and data used for MAIIC's reporting. |
| **Test environment (test copy, UAT)** | A separate copy of a system used for trying things out, so mistakes cannot affect the real data. |
| **Replica (standby)** | A continuously updated copy of the live database, often used for reporting so the live system is not disturbed. |
| **Query** | A precise request for data from a database, such as "all payments on MAIIC's loans since 1 September". |
| **Read-only login (account)** | A username and password that can look at data but cannot change it. |
| **Grant** | Permission given to a login to read a particular table. |
| **Table** | One set of records in a database, such as the list of loan accounts or the list of payments. |
| **VPN** | A secure, encrypted connection between two organisations' networks. MAIIC already has one with Dupleix. |
| **Firewall** | The network gatekeeper that decides which computers may connect to which. |
| **API** | A way for one computer system to send requests or data to another automatically, without a person in between. |
| **Endpoint ("letterbox")** | A specific secure address on a system that accepts deliveries or requests from another computer. The IFRS 9 system's endpoint for E-Banker data is `/api/ebanker-feed/pack`. |
| **Token** | A long secret code sent with each delivery to prove who it comes from, like a password for a computer rather than a person. |
| **HTTPS** | The encrypted form of web traffic (the padlock in a browser), so data cannot be read on the way. |
| **Push and pull** | Push: the sender delivers the data (ways 2 and 3, MAIIC sends). Pull: the receiver fetches it (ways 4 and 5, the IFRS 9 system fetches). It decides which side opens the connection. |
| **202 Accepted** | The reply the letterbox gives when a delivery is received and waiting to be checked; not yet a confirmation that it passed the checks. |
| **Feed route** | The way data travels from E-Banker to the IFRS 9 system (one of the five in section 3). |
| **Pack** | One delivery of data: one file per query, plus a list of what the files should contain. |
| **Manifest** | The list that comes with each pack, stating how many records each file holds and its fingerprint. |
| **Hash (fingerprint)** | A short code calculated from a file's exact contents. If even one character changes, the code changes, so it proves a file is complete and unaltered. |
| **Landing zone** | The checkpoint inside the IFRS 9 system where every delivery is checked and stored before any calculation uses it. |
| **Gates (checks)** | The tests every delivery must pass at the landing zone. |
| **Quarantine** | Setting aside a delivery that failed a check: kept for inspection, never used. |
| **Version** | A record as it stood at a point in time. When E-Banker changes a record, the system keeps the old version and adds the new one. |
| **Watermark** | A marker of the last record already received, so the next delivery only fetches what is new. |
| **Bootstrap** | The command that rebuilds the whole IFRS 9 system from scratch on a test database and checks the key figures. |
| **Golden ties** | The nine key figures the bootstrap checks against an independent source; all must agree exactly. |
| **Maker-checker** | A control where one person proposes a change and a second person must approve it. |
| **Period lock** | Closing a reporting month so its figures cannot change afterwards. |
| **Audit trail** | The record of who or what did what, and when, kept for the auditors. |
| **Trial balance** | The list of every general ledger account's balance at a date, produced by Finance from E-Banker. |
| **Mega Farm** | The farming programme whose loans MAIIC administers alongside its own loan book. |
| **Virtual Galaxy** | The company that supplies and supports E-Banker. |
| **Dupleix** | Dupleix Institute, the firm building the IFRS 9 system for MAIIC. |
