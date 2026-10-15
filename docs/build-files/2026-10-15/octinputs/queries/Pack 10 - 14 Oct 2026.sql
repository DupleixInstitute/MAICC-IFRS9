--==============================================================================
-- MAIIC E-Banker: FOLLOW-UP EXTRACTS, PACK 10  |  Dupleix Institute  |  14 October 2026
--==============================================================================
-- Read-only queries, numbered in run order, each with the file name to save
-- it under. Nothing here writes to the database.
--
-- Pack 9 came back complete, and the split overdue run worked: the month-end
-- rows (OD_01, 31 December 2024 to 30 September 2026) and the daily rows for
-- July 2025 to March 2026 (OD_02, three files) are in. Pack 10 asks for what
-- is still open:
--
--   RUN 1       the overdue run, a profile by month (pack 9's RUN 8a, run first)
--   RUN 2a-2c   the overdue run, daily rows from April 2026 to today, one quarter per file
--   RUN 3       the account master copy of 5 August 2024 (CRM_ACMASTER_05082024)
--   RUN 4       every leg of the batches that paid off a loan from a new loan
--   RUN 5       a names-only probe for a column that links a loan to its replacement
--   RUN 6       a check on the stored trial balance (the 88,000 on GL 6730)
--   RUN 7       a check on the Zaithwa Farms balance rows (the 1,827,633.62)
--   RUN 8a, 8b  DD_17 and DD_18 from the data dictionary set: what this login reads
--
-- Every table carries its owner (VGCBS or MAIIC_ORA_LIVE). If a query errors
-- because a table or column is not there, skip it and tell us; the rest still
-- help. Please save the results in the Pack 4 folder on OneDrive, in the
-- subfolder Pack 10\Export Results.
--
-- QUESTIONS THAT NEED NO QUERY (a short reply by email is fine):
--   1. The Loan Book Report before December 2024. The stored runs start at 31
--      December 2024. Can E-Banker produce the report as at the month-ends
--      from 31 July to 30 November 2024? If it can, please tell us before
--      running anything and we'll agree the dates with you. Without it, the
--      arrears for those months can't be measured.
--   2. Re-run reports and deleted instalment definitions. The as-at reports
--      re-run in September 2026 for earlier month-ends age instalments from
--      definitions that had already been deleted (Namalowe Eco Farm, Mchinji
--      Civil Servants SACCO, Microloan Foundation, Ifracon). Why does a
--      re-run read a deleted definition?
--   3. Instalments all on one date. Udon Business's instalments 35 to 73 are
--      all dated 1 December 2025, and BenCal Technologies has 36 instalments
--      on one date. Did the migration load them that way, and are the right
--      dates held anywhere?
--   4. Status codes D and F. What do they mean? D is on 46 accounts in
--      September 2026 (1,117.8 million), including Malawi Police SACCO
--      104430000072 with 2.2 million left; F is on The Food Empire's older
--      accounts.
--   5. Refinanced loans. When a loan is paid off from a new loan, does
--      E-Banker record the link anywhere? RUN 4 and RUN 5 look for it.
--   6. Ecogen 104460000111. The rate is 0 in the account master, the rate
--      set-up and the Loan Book Report, and no interest has been charged
--      since the 105.06 million drawdown in May 2025, although the 3.65
--      million monthly repayments look like a loan that carries interest. Was
--      the rate left out at set-up?
--   7. Renumbered loans. Please confirm that Ifracon's 000104460000101
--      (closed), 000104430000059 (closed) and 000104430000062 (live) are one
--      loan, and that Tapempha Medical Care's 000104460000058 became
--      000104430000061.
--   8. Happie Foods 104420000062. E-Banker holds two schedules: one with
--      value date 24 March 2023 (6.9 million a month to January 2027) and one
--      with value date 14 September 2022 (13.5 million a month to September
--      2026). Which one is the loan's schedule?
--==============================================================================


--==============================================================================
-- RUN 0  |  session settings  |  run once, first, in THIS worksheet
-- The pack 9 files still came out with dates as m/d/yyyy. They load as they
-- are, but please put the cursor on each ALTER SESSION line and press
-- Ctrl+Enter, in the same worksheet (same connection tab) as the queries;
-- each tab is its own session.
ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS';
ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '.,';

-- RUN 0 check  |  do not export, just look at the grid
-- It must show a date like 2026-10-15 09:15:00 and the number 1234567.89.
SELECT SYSDATE AS today_as_text, 1234567.89 AS a_number FROM dual;

-- If the grid is right but the CSV still shows 10/15/2026, the export is
-- re-formatting it: Tools > Preferences > Database > NLS > Date Format =
-- YYYY-MM-DD HH24:MI:SS (type NLS in the search box at the top left of the
-- Preferences window if Database is not visible). Set once, it sticks.


--==============================================================================
-- RUN 1  |  the overdue run, rows per calculation month  |  save as OD_00_overdue_profile.csv
-- Pack 9's RUN 8a, which hadn't been run. A few dozen rows: rows, accounts
-- and calculation days per month, so we can tell whether each daily file (the
-- three received and the three below) holds every day it should. Run first.
SELECT TO_CHAR(TRUNC(o.ovd_calc_date, 'MM'), 'YYYY-MM')  AS calc_month,
       COUNT(*)                                            AS rows_in_month,
       COUNT(DISTINCT o.new_ac_number)                     AS accounts,
       COUNT(DISTINCT TRUNC(o.ovd_calc_date))              AS calc_days,
       MIN(o.ovd_calc_date)                                AS first_calc_date,
       MAX(o.ovd_calc_date)                                AS last_calc_date,
       SUM(CASE WHEN o.reschedulement_date IS NOT NULL THEN 1 ELSE 0 END) AS rows_with_reschedule_date
FROM   vgcbs.overdue_details o
WHERE  o.new_ac_number IN (SELECT a.new_ac_number FROM vgcbs.acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
GROUP  BY TRUNC(o.ovd_calc_date, 'MM')
ORDER  BY 1;


--==============================================================================
-- RUN 2a  |  the overdue run, daily rows, April to June 2026  |  save as OD_02_overdue_daily_2026Q2.csv
-- The rest of pack 9's RUN 8c: E-Banker's own day-by-day arrears count per
-- account, the independent check on the ageing the system stages loans on.
-- Same 34 columns as the pack 9 daily files; about 11,000 rows (3.5 MB).
-- Run and export the three quarters one at a time.
SELECT o.overdue_det_id, o.op_branch_mst_id, o.transaction_date, o.ovd_calc_date, o.glcode,
       o.new_ac_number, o.sub_ac_number, o.scheme_mst_id, o.installment_amt, o.installment_start_date,
       o.reschedulement_date, o.reschedulement_amount, o.moratorium_period, o.rate_of_interest, o.expiry_date,
       o.balance, o.expected_no_inst, o.expected_rec_amt, o.actual_recovery_amt, o.total_recovery_amount,
       o.principle_recovery_amt, o.interest_recovery_amt, o.overdue_amount, o.overdue_principle, o.overdue_interest,
       o.overdue_princi_date, o.overdue_int_date, o.last_cr_date, o.last_cr_amount, o.overdue_installment,
       o.overdue_period, o.status_due_flag, o.delete_flag, o.operation_date
FROM   vgcbs.overdue_details o
WHERE  o.new_ac_number IN (SELECT a.new_ac_number FROM vgcbs.acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
AND    o.ovd_calc_date >= DATE '2026-04-01'
AND    o.ovd_calc_date <  DATE '2026-07-01'
ORDER  BY o.new_ac_number, o.ovd_calc_date, o.overdue_det_id;


--==============================================================================
-- RUN 2b  |  the overdue run, daily rows, July to September 2026  |  save as OD_02_overdue_daily_2026Q3.csv
-- As RUN 2a. About 10,000 rows.
SELECT o.overdue_det_id, o.op_branch_mst_id, o.transaction_date, o.ovd_calc_date, o.glcode,
       o.new_ac_number, o.sub_ac_number, o.scheme_mst_id, o.installment_amt, o.installment_start_date,
       o.reschedulement_date, o.reschedulement_amount, o.moratorium_period, o.rate_of_interest, o.expiry_date,
       o.balance, o.expected_no_inst, o.expected_rec_amt, o.actual_recovery_amt, o.total_recovery_amount,
       o.principle_recovery_amt, o.interest_recovery_amt, o.overdue_amount, o.overdue_principle, o.overdue_interest,
       o.overdue_princi_date, o.overdue_int_date, o.last_cr_date, o.last_cr_amount, o.overdue_installment,
       o.overdue_period, o.status_due_flag, o.delete_flag, o.operation_date
FROM   vgcbs.overdue_details o
WHERE  o.new_ac_number IN (SELECT a.new_ac_number FROM vgcbs.acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
AND    o.ovd_calc_date >= DATE '2026-07-01'
AND    o.ovd_calc_date <  DATE '2026-10-01'
ORDER  BY o.new_ac_number, o.ovd_calc_date, o.overdue_det_id;


--==============================================================================
-- RUN 2c  |  the overdue run, daily rows, 1 October 2026 to today  |  save as OD_02_overdue_daily_2026Q4.csv
-- As RUN 2a, from 1 October to the day you run it: about 1,500 rows. The
-- rest of the quarter will come with a later pack.
SELECT o.overdue_det_id, o.op_branch_mst_id, o.transaction_date, o.ovd_calc_date, o.glcode,
       o.new_ac_number, o.sub_ac_number, o.scheme_mst_id, o.installment_amt, o.installment_start_date,
       o.reschedulement_date, o.reschedulement_amount, o.moratorium_period, o.rate_of_interest, o.expiry_date,
       o.balance, o.expected_no_inst, o.expected_rec_amt, o.actual_recovery_amt, o.total_recovery_amount,
       o.principle_recovery_amt, o.interest_recovery_amt, o.overdue_amount, o.overdue_principle, o.overdue_interest,
       o.overdue_princi_date, o.overdue_int_date, o.last_cr_date, o.last_cr_amount, o.overdue_installment,
       o.overdue_period, o.status_due_flag, o.delete_flag, o.operation_date
FROM   vgcbs.overdue_details o
WHERE  o.new_ac_number IN (SELECT a.new_ac_number FROM vgcbs.acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
AND    o.ovd_calc_date >= DATE '2026-10-01'
ORDER  BY o.new_ac_number, o.ovd_calc_date, o.overdue_det_id;


--==============================================================================
-- RUN 3  |  the account master copy of 5 August 2024  |  save as CV_15_crm_acmaster_05082024.csv
-- Pack 9's RUN 5d found none of its 102 rows on the loan GLs or the old
-- 00010075 block. All 102 rows, the identifying columns only (no contact
-- details): it shows what this copy holds and whether its OLD_AC_NUMBER gives
-- the numbers loans had before the migration.
SELECT a.account_mst_id, a.glcode, SUBSTR(a.new_ac_number, 1, 8) AS account_block,
       a.new_ac_number, a.sub_ac_number, a.old_ac_number, a.customer_id, a.account_name,
       a.scheme_mst_id, a.status_code, a.account_open_date, a.transaction_date,
       a.operation_date, a.active_flag, a.delete_flag, a.pass_flag
FROM   maiic_ora_live.crm_acmaster_05082024 a
ORDER  BY a.glcode, a.new_ac_number, a.account_mst_id;


--==============================================================================
-- RUN 4  |  every leg of the batches that paid off a loan from a new one  |  save as RF_01_refinance_batch_legs.csv
-- JVD Agro (104460000109 paid off on 31 March 2026, 104460000120 drawn the
-- same day), The Food Empire (104420000043 closed into 104450000068 on 30
-- December 2025, that one into 106050000003 on 1 May 2026) and, it seems,
-- Malawi Police SACCO (104430000072 into 106050000006). Every leg of each
-- batch that drew down or paid off one of these seven accounts, 10 million and
-- above: it shows how each payoff was funded. A few hundred rows.
SELECT cv.cumvouch_det_id, cv.cb_branch_mst_id, cv.cb_glcode, cv.ac_glcode, cv.new_ac_number,
       cv.sub_account_no, cv.transaction_date, cv.transamt, cv.dbcr, cv.trantype,
       cv.batch_type, cv.batch_number, cv.vscrno, cv.particulars, cv.trf_glcode,
       cv.trf_new_ac_number, cv.pass_flag, cv.delete_flag, cv.modify_delete_flag,
       cv.back_dated_date, cv.operation_date
FROM   vgcbs.cumvouch cv
WHERE  (cv.cb_branch_mst_id, cv.transaction_date, cv.batch_type, cv.batch_number) IN (
         SELECT DISTINCT x.cb_branch_mst_id, x.transaction_date, x.batch_type, x.batch_number
         FROM   vgcbs.cumvouch x
         WHERE  x.new_ac_number IN ('000104460000109', '000104460000120', '000104420000043',
                                   '000104450000068', '000106050000003', '000104430000072',
                                   '000106050000006')
         AND    x.trantype IN (301, 305, 306)
         AND    ABS(x.transamt) >= 10000000)
ORDER  BY cv.transaction_date, cv.batch_type, cv.batch_number, cv.vscrno, cv.cumvouch_det_id;


--==============================================================================
-- RUN 5  |  any column that may link a loan to the loan that replaced it  |  save as DD_20_link_columns_probe.csv
-- Names and statistics only, no data. REC_FM_NEWACNO in ACMASTER and
-- ACLOANMASTER is the account repayments are recovered from (both JVD loans
-- point to the same current account, 000105910000476), so it isn't the link.
-- This lists any other column whose name suggests one: transfer, refinance,
-- top-up, renewal, closure, takeover or restructure. A few hundred rows.
SELECT c.owner, c.table_name, c.column_name, c.data_type,
       t.num_rows, c.num_distinct, c.num_nulls
FROM   all_tab_columns c
JOIN   all_tables t ON t.owner = c.owner AND t.table_name = c.table_name
WHERE  c.owner IN ('VGCBS', 'MAIIC_ORA_LIVE')
AND    (t.num_rows IS NULL OR t.num_rows > 0)
AND   (c.column_name LIKE '%TRF%AC%'
OR     c.column_name LIKE '%TRANSFER%AC%'
OR     c.column_name LIKE '%REFIN%'
OR     c.column_name LIKE '%TOP%UP%'
OR     c.column_name LIKE '%RENEW%AC%'
OR     c.column_name LIKE '%PARENT%AC%'
OR     c.column_name LIKE '%LINK%AC%'
OR     c.column_name LIKE '%PREV%LOAN%'
OR     c.column_name LIKE '%OLD%LOAN%'
OR     c.column_name LIKE '%NEW%LOAN%'
OR     c.column_name LIKE '%CLOSE%AC%'
OR     c.column_name LIKE '%TAKE%OVER%'
OR     c.column_name LIKE '%SETTLE%AC%'
OR     c.column_name LIKE '%RESTRUC%'
OR     c.column_name LIKE '%RESCH%')
ORDER  BY c.owner, c.table_name, c.column_name;


--==============================================================================
-- RUN 6  |  a check on the stored trial balance since 1 May 2026  |  save as TB_06_stored_tb_balance_check.csv
-- One row per day: the stored trial balance summed over the posting GLs
-- (it should be 0.00) and GL 6730 Fines Expenses. Pack 6 found it 88,000 out
-- from 15 May 2026: one voucher (Lloyd Banda, batch TRF 4, voucher 21) never
-- reached the stored history. If the vendor has rebuilt it, every day shows
-- 0.00. About 165 rows.
SELECT t.transaction_date,
       COUNT(*)                                                         AS gl_rows,
       SUM(NVL(t.closing_cr_bal, 0) + NVL(t.closing_dr_bal, 0))         AS net_closing_all_gls,
       SUM(CASE WHEN t.glcode = '6730'
                THEN NVL(t.closing_cr_bal, 0) + NVL(t.closing_dr_bal, 0)
                ELSE 0 END)                                             AS gl_6730_closing
FROM   vgcbs.trial_balance_history t
WHERE  t.parent_flag = 'N'
AND    t.transaction_date >= DATE '2026-05-01'
GROUP  BY t.transaction_date
ORDER  BY t.transaction_date;


--==============================================================================
-- RUN 7  |  a check on the Zaithwa Farms balance rows  |  save as ZF_04_zaithwa_balance_rows_recheck.csv
-- The month-end balance rows of Zaithwa Farms (000104450000015) from April
-- 2025. Since May 2025 they have been 1,827,633.62 away from the ledger (our
-- note of 7 October). If the vendor has rebuilt them, they now agree. About
-- 20 rows.
SELECT b.*
FROM   vgcbs.account_balance b
WHERE  b.new_ac_number = '000104450000015'
AND    b.transaction_date >= DATE '2025-04-01'
ORDER  BY b.transaction_date, b.account_bal_mst_id;


--==============================================================================
-- RUN 8a  |  who is logged in, and where the table names point  |  save as DD_17_login_and_synonyms.csv
-- From the data dictionary set of 5 October, not yet run. Catalogue only:
-- the login, the schema its table names point to, any synonyms for the tables
-- the packs read, and who owns the three extract procedures. A few rows.
SELECT 'LOGIN'                                  AS item,
       USER                                     AS name,
       SYS_CONTEXT('USERENV', 'CURRENT_SCHEMA') AS points_to_owner,
       CAST(NULL AS VARCHAR2(128))              AS points_to_table
FROM   dual
UNION ALL
SELECT 'SYNONYM OWNED BY ' || s.owner, s.synonym_name, s.table_owner, s.table_name
FROM   all_synonyms s
WHERE  s.synonym_name IN ('ACCOUNT_BALANCE', 'ACCOUNT_CLOSER_HISTORY', 'ACCOUNT_STATUS_HISTORY',
                          'ACLOANMASTER', 'ACMASTER', 'CRM_ACMASTER_05082024', 'CUMOVUCH_OLD',
                          'CUMVOUCH', 'CUSTOMER_MAST', 'CUST_SECURITY_DETAILS',
                          'DAILY_INTEREST_ACCRUAL', 'FIXLOAN_INST_CHART',
                          'FORGIVE_INTEREST_DETAILS', 'GLMASTER', 'GL_OPENING_BALANCE',
                          'GL_OPENING_BALANCE_BK240824', 'GUARANTER_MASTER', 'INTEREST_PRODUCT',
                          'INTEREST_SLAB_DETAILS', 'INTEREST_SLAB_MASTER', 'INTEREST_SUMMARY',
                          'LOAN_BOOK_DETAILS_ALL', 'LOAN_DISBURSMENT_SCHEDULE',
                          'LOAN_RESCHEDULE_DETAILS', 'LOAN_WRITEOFF_MASTER', 'LOAN_WRITEOFF_TRANS',
                          'LOS_DISB_CHARGES_POST', 'LOS_REQUEST_DETAILS', 'MODIFICATION_LOG',
                          'MULTI_INST_DETAILS', 'OPENING_BAL', 'OVERDUE_DETAILS', 'PLR_MASTER',
                          'SCHEME_MASTER', 'TRIAL_BALANCE_HISTORY')
UNION ALL
SELECT 'PROCEDURE', o.object_name, o.owner, CAST(NULL AS VARCHAR2(128))
FROM   all_objects o
WHERE  o.object_type = 'PROCEDURE'
AND    o.object_name IN ('PROC_BUILD_MAIIC_FACILITY_REGISTER', 'PROC_BUILD_MAIIC_CASHFLOW_REGISTER',
                         'PROC_BUILD_MAIIC_GL_INTEREST_RECON')
ORDER  BY 1, 2;


--==============================================================================
-- RUN 8b  |  can this login read each table directly  |  save as DD_18_read_access.csv
-- Also from 5 October. One row per table the packs read; each should show
-- 1. If a line errors with "table or view does not exist", delete that line,
-- run the rest, and tell us which one it was. 35 rows.
SELECT 'VGCBS.ACMASTER' AS table_name, COUNT(*) AS rows_read FROM vgcbs.acmaster WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.ACLOANMASTER', COUNT(*) FROM vgcbs.acloanmaster WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.CUSTOMER_MAST', COUNT(*) FROM vgcbs.customer_mast WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.SCHEME_MASTER', COUNT(*) FROM vgcbs.scheme_master WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.GLMASTER', COUNT(*) FROM vgcbs.glmaster WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.CUMVOUCH', COUNT(*) FROM vgcbs.cumvouch WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.CUMOVUCH_OLD', COUNT(*) FROM vgcbs.cumovuch_old WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.ACCOUNT_BALANCE', COUNT(*) FROM vgcbs.account_balance WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.LOAN_BOOK_DETAILS_ALL', COUNT(*) FROM vgcbs.loan_book_details_all WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.FIXLOAN_INST_CHART', COUNT(*) FROM vgcbs.fixloan_inst_chart WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.MULTI_INST_DETAILS', COUNT(*) FROM vgcbs.multi_inst_details WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.LOAN_DISBURSMENT_SCHEDULE', COUNT(*) FROM vgcbs.loan_disbursment_schedule WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.ACCOUNT_STATUS_HISTORY', COUNT(*) FROM vgcbs.account_status_history WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.OVERDUE_DETAILS', COUNT(*) FROM vgcbs.overdue_details WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.INTEREST_SLAB_MASTER', COUNT(*) FROM vgcbs.interest_slab_master WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.INTEREST_SLAB_DETAILS', COUNT(*) FROM vgcbs.interest_slab_details WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.INTEREST_SUMMARY', COUNT(*) FROM vgcbs.interest_summary WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.INTEREST_PRODUCT', COUNT(*) FROM vgcbs.interest_product WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.DAILY_INTEREST_ACCRUAL', COUNT(*) FROM vgcbs.daily_interest_accrual WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.FORGIVE_INTEREST_DETAILS', COUNT(*) FROM vgcbs.forgive_interest_details WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.PLR_MASTER', COUNT(*) FROM vgcbs.plr_master WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.LOS_DISB_CHARGES_POST', COUNT(*) FROM vgcbs.los_disb_charges_post WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.LOS_REQUEST_DETAILS', COUNT(*) FROM vgcbs.los_request_details WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.CUST_SECURITY_DETAILS', COUNT(*) FROM vgcbs.cust_security_details WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.GUARANTER_MASTER', COUNT(*) FROM vgcbs.guaranter_master WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.LOAN_RESCHEDULE_DETAILS', COUNT(*) FROM vgcbs.loan_reschedule_details WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.LOAN_WRITEOFF_MASTER', COUNT(*) FROM vgcbs.loan_writeoff_master WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.MODIFICATION_LOG', COUNT(*) FROM vgcbs.modification_log WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.ACCOUNT_CLOSER_HISTORY', COUNT(*) FROM vgcbs.account_closer_history WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.GL_OPENING_BALANCE', COUNT(*) FROM vgcbs.gl_opening_balance WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.GL_OPENING_BALANCE_BK240824', COUNT(*) FROM vgcbs.gl_opening_balance_bk240824 WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.TRIAL_BALANCE_HISTORY', COUNT(*) FROM vgcbs.trial_balance_history WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.OPENING_BAL', COUNT(*) FROM vgcbs.opening_bal WHERE ROWNUM <= 1
UNION ALL SELECT 'VGCBS.LOAN_WRITEOFF_TRANS', COUNT(*) FROM vgcbs.loan_writeoff_trans WHERE ROWNUM <= 1
UNION ALL SELECT 'MAIIC_ORA_LIVE.CRM_ACMASTER_05082024', COUNT(*) FROM maiic_ora_live.crm_acmaster_05082024 WHERE ROWNUM <= 1;

--==============================================================================
-- End of pack 10. Thank you, Barry.
--==============================================================================
