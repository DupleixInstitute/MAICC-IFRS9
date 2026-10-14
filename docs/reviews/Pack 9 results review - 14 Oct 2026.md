# MAIIC E-Banker extracts, pack 9: results review

Dupleix Institute. Review of the 14 files in this Export Results folder, received 14 October 2026 at 09:09, against the RUN 1 counts, the 31 July 2024 take-on postings in the ledger, the July 2024 loan book and the pack 7 ledger copy.

## 1. Are they there, and do they tie

| Run | File | Rows | RUN 1 count | Verdict |
|---|---|---:|---:|---|
| RUN 1 | CV_08_table_counts.csv | 12 | | Complete. Seven of the twelve tables are empty (below) |
| RUN 2a | CV_09a_account_no_mapp.csv | 0 | 0 | Table empty: no old-to-new account map here |
| RUN 2b | CV_09b_new_acmap.csv | 0 | 0 | Table empty |
| RUN 3 | CV_10_opening_bal.csv | 95 | 95 | Complete. **This is the migration worksheet** (section 2) |
| RUN 4a | CV_11a_t_account.csv | 0 | 0 | Table empty |
| RUN 4b | CV_11b_t_accountbalance.csv | 0 | 0 | Table empty |
| RUN 5a | CV_12a_old_block_g_acmaster.csv | 0 | 0 | Table empty |
| RUN 5b | CV_12b_old_block_acmaster_mw.csv | 1 | 191 in table | The old block has one row here: 000100750000001 "Advertisng And Promotion", GL 6000, an expense account, not a loan |
| RUN 5c | CV_12c_old_block_acmaster_nf.csv | 0 | 0 | Table empty |
| RUN 5d | CV_12d_acmaster_05082024_loans.csv | 0 | 102 in table | No row matched the loan GLs or the old block; the 102 rows must sit under other GL codes or another number format. One follow-up query (section 6) |
| RUN 5e | CV_12e_temp_master.csv | 0 | 0 | Table empty |
| RUN 6 | CV_13_cumovuch_old_profile.csv | 363 | 13,359 | Complete: the 363 profile rows sum to 13,359 exactly (section 4) |
| RUN 7 | CV_14_gl_opening_balance_bk240824.csv | 331 | 331 | Complete, and the loan GLs **tie to the pack 7 ledger copy to the kwacha** (section 5) |
| RUN 8 | OD_01_overdue_details.csv (+ .png) | 21 | | A fragment: SQL Developer stopped at 100 MB and the export holds 21 rows of one account. RUN 8 has been split into 8a, 8b, 8c (section 7) |

RUN 0 did not take again: dates arrive as 8/8/2025 and 04/14/2020. Everything above loads as it is.

So: every file asked for is there, each has the rows RUN 1 says the table holds, and the two files with substance (CV_10 and CV_14) both tie to what the system already holds. The seven empty tables are an answer too: E-Banker keeps no old-to-new account map in any of them.

## 2. CV_10 is the migration worksheet, and it ties to the take-on postings

OPENING_BAL holds 95 rows: the **78 term loans** taken on at 31 July 2024, one row each on the loan GLs (1050101 x13, 1050102 x4, 1050201 x35, 1050202 x26), and 17 rows on GL 2000 (supplier balances: IAM Motors, Best Car Hire, Old Mutual, Protea Hotels Ryalls, Nissan Malawi, Sunbird, Club Makokola and others; principal only, no interest or repayments). Per loan it carries sanction date and amount, disbursement, restructure date and amount, rate, open and maturity dates, tenor, balance, total interest charged, principal and interest repaid, overdue principal and instalments, the instalment start date and amount, and NEWDISBURS_AMT.

Against the three opening postings per loan in the ledger (type 301 "Opening Account Balance Disbursement amount", 303 "Opening interest charge", 305 "Opening Recover Amount"), 77 loans are in both:

| Posting | Worksheet column | Loans agreeing (of 77) |
|---|---|---:|
| 301 principal taken on | NEWDISBURS_AMT (the restructured amount where restructured, else the disbursement) | 76 |
| 303 opening interest | TOTAL_INT_CHARG | 76 |
| 305 opening recoveries | TOTA_REPAY_UPTO | 68 |

This answers pack 7's first question: **the opening interest posted on every loan is the worksheet's own TOTAL_INT_CHARG figure**, so the 38 loans where it is lower than the old ledger's interest are not posting errors; the question becomes how Finance computed TOTAL_INT_CHARG (rate, period, basis) when it prepared the worksheet.

The eleven loans that differ anywhere:

| Account | Name | What differs |
|---|---|---|
| 000104450000020 | Kayola Processors | Restructured 1 March 2024. The posting carries the restructured loan (301 = 12,543,065; 303 = 470,669.82; 305 = 3,000,000); the worksheet's disbursement, interest and repayment columns still show the original loan (20,400,000; 3,030,316.70; 13,416,581.88). RESTRUCURE_AMT 12,543,063.04 agrees with the posting within K2 |
| 000104420000007 | Lenziemill Milling | 305 is 345,389,896.04 against TOTA_REPAY_UPTO 336,431,799.34: 8,958,096.70 more recovered than the worksheet |
| 000104450000022 | Mwachisosa Women Producers | 305 higher by 1,000,000 |
| 000104450000038 | Tac-Maz Investment | 305 higher by 800,000; balance taken on 400,000 lower than the worksheet |
| 000104450000056 | Udon Business | 305 higher by 4,000,000 |
| 000104430000061 | Tapempha Medical Care | 305 lower by 3,400,000; balance taken on 3,400,000 higher |
| 000104460000079 | Lilongwe Private Clinic | 305 higher by 4,247,552.90 |
| 000104460000080 | F&K Construction | 305 higher by 2,400,000; balance 2,400,000 lower |
| 000104460000096 | 7Arts Holdings | 305 higher by 541,666.67 |
| 000104430000002 | Edge View Academy | Ties in every posting; listed only because DISBURS_AMT (300,000,000, the 2020 loan) differs from the 331,395,845.48 restructured and taken on |
| 000104420000005 | MUSECO | Ties in every posting; DISBURS_AMT blank, NEWDISBURS_AMT 405,097,989.86 |

The round-sum 305 differences (1.0m, 0.8m, 4.0m, 3.4m, 2.4m) are receipts between the worksheet and the posting date, or re-allocations at the take-on; the three balances that moved by the same amounts (Tac-Maz, Tapempha, F&K) are the three accounts where the system's July 2024 carrying amount differs from BALANCE_AMT. **The other 74 loans' carrying amounts equal the worksheet's BALANCE_AMT exactly.**

**Ifracon has three account numbers.** The worksheet lists it as 000104460000101 (sanctioned 13 May 2024, disbursed 627,746,500); the take-on was posted under 000104430000062; pack 7 found its pre-migration history under 000104430000059. The amount and sanction date tie all three. This is the one loan in the worksheet with no posting under its worksheet number, and the one posting with no worksheet row.

## 3. The first restructure register

Eight loans carry a RESTRUCTURE_DATE and RESTRUCURE_AMT, the first restructure history we have from E-Banker:

| Account | Name | GL | Sanctioned | Restructured | Amount | New maturity | Tenor |
|---|---|---|---|---|---:|---|---:|
| 000104430000002 | Edge View Academy | 1050102 | 14 Apr 2020 | 1 Apr 2022 | 331,395,845.48 | 31 Mar 2034 | 12 |
| 000104420000005 | MUSECO | 1050101 | 23 Jul 2020 | 1 Aug 2021 | 405,097,989.87 | 31 Jul 2026 | 6 |
| 000104420000007 | Lenziemill Milling | 1050101 | 30 Oct 2020 | 1 Oct 2023 | 191,894,946.00 | 31 Oct 2026 | 3 |
| 000104450000020 | Kayola Processors | 1050201 | 17 Feb 2022 | 1 Mar 2024 | 12,543,063.04 | 28 Feb 2025 | 3 |
| 000104450000022 | Mwachisosa Women Producers | 1050201 | 24 Mar 2022 | 31 Oct 2023 | 13,270,993.89 | 31 Mar 2025 | 3 |
| 000104450000038 | Tac-Maz Investment | 1050201 | 13 Jul 2022 | 1 Feb 2024 | 8,447,404.79 | 28 Feb 2025 | 1 |
| 000104420000052 | Kambewu Organic Fertilizer | 1050101 | 18 Nov 2022 | 1 Mar 2024 | 139,300,890.90 | 30 Nov 2026 | 4 |
| 000104460000024 | Allied Bricks and Blocks | 1050202 | 3 Jun 2022 | 1 May 2024 | 6,748,705.43 | 31 May 2025 | 3 |

These are pre-migration restructures (the latest 1 May 2024), so they do not replace the Reschedule Report for anything after 31 July 2024, but they are the input for the restructured-loan line of the RBM return (directive s.16(1)) and the 10 percent modification test (spec 3.3.2) for these eight, and for the EIR of each, whose cash flows restart at the restructure date. The December 2025 ECL model's hand-built "Restructured" amortisation blocks name MUSECO, Chikho Hotel and Corran Farming; MUSECO is here, the other two are not in the worksheet.

## 4. CUMOVUCH_OLD is a third ledger under a third numbering

13,359 rows from 2018 to 2022 (1,339 / 862 / 2,527 / 3,505 / 5,126 by year), under account blocks 00010144 (2,558 rows), 00010007 (2,260), 00010029 (693), 00010163, 00010152, 00010132 and about 60 others. **None are in the 00010075 block of CUMVOUCH_BAK and none in today's loan blocks (0001044x).** Without a map it cannot be attached to a loan; the full table is not worth requesting yet.

## 5. CV_14 ties the pack 7 ledger copy to the GL

The 331 rows are the GL opening balances of financial year 2024 (1 January 2024, entered 31 March 2024). On the loan GLs they equal the net postings in the pack 7 ledger copy (CUMVOUCH_BK240824) up to 31 December 2023:

| GL | Opening balance 1 Jan 2024 (CV_14) | Net postings to 31 Dec 2023 (pack 7 copy) | Difference |
|---|---:|---:|---:|
| 1050101 MAIIC loans | 1,685,750,790.87 | 1,685,750,790.87 | 0.00 |
| 1050102 MAIIC loans | 980,597,705.76 | 980,597,705.26 | 0.50 |
| 1050201 FInES loans | 1,437,753,156.23 | 1,437,753,156.23 | 0.00 |
| 1050202 FInES loans | 1,497,539,282.18 | 1,497,539,282.18 | 0.00 |

(The 331 balances sum to -0.49, which is that 0.50.) So the pack 7 copy is complete on these GLs back to its first posting, and it is anchored to the GL at 1 January 2024. GL 1320 Interest Suspense opens at 21,865,867.35, the same figure the 2025 audit reversed to income; it had not moved since at least 1 January 2024.

## 6. What we need from MAIIC ICT and Finance

1. **RUN 8a, 8b and 8c** (the split RUN 8 in the pack 9 folder): the profile, the month-end rows, and the daily rows one quarter per file. The 21-row file of this morning is superseded; we will not load it.
2. **RUN 0, run first in the same worksheet** (cursor on each ALTER SESSION line, Ctrl+Enter), as before.
3. **One more small query (RUN 5d-2)**: `SELECT glcode, SUBSTR(new_ac_number,1,8) AS block, COUNT(*) FROM maiic_ora_live.crm_acmaster_05082024 GROUP BY glcode, SUBSTR(new_ac_number,1,8) ORDER BY 1,2;` so we can see what the 102 rows are.
4. **Finance**: how TOTAL_INT_CHARG in OPENING_BAL was computed (rate, period and basis), since that is the opening interest posted on every loan; and confirmation that the 17 GL 2000 rows are supplier balances taken on.
5. **Ifracon**: confirm 000104460000101 (worksheet) = 000104430000062 (posted) = 000104430000059 (pre-migration).

## 7. RUN 8 (OD_01) and why it was split

The overdue run writes one row per account per day since 14 July 2025 (month-end rows only before that, back-filled on 8 to 11 August 2025), so the whole table exceeds SQL Developer's 100 MB grid. RUN 8 now has three parts: 8a a profile by calculation month, 8b the month-end rows with all 78 columns (query id OD_01), 8c the daily rows one quarter per file with 34 columns (OD_02). On the 21 rows received (MUSECO, 31 Dec 2024 to 27 Jul 2025): OVERDUE_PERIOD is in months (4.97 at 31 Dec 2024 for an overdue date of 1 Aug 2024); RESCHEDULEMENT_DATE and RESCHEDULEMENT_AMOUNT are empty; a 200,000,000 receipt on 24 July 2025 cleared the overdue and reset OVERDUE_PRINCI_DATE to 1 August 2025.

---
Prepared by Dupleix Institute from the files in this folder. Nothing has been changed in MAIIC's systems or in these files.
