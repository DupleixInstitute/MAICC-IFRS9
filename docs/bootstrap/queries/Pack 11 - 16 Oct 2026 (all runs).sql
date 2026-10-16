--==============================================================================
-- MAIIC E-Banker: FOLLOW-UP EXTRACTS, PACK 11  |  Dupleix Institute  |  16 October 2026
--==============================================================================
-- Read-only queries, numbered in run order, each with the file name to save
-- it under. Nothing here writes to the database.
--
-- Pack 10 came back complete, thank you. With it, 113 of the 144 loans now
-- have a locked EIR. Pack 11 is about 17 of the loans still open: the EIR
-- can't be worked out for them because the system holds no original
-- repayment schedule for any of the 17, and for 16 of them no drawn amount
-- either. The 17 accounts, with what we hold:
--
--   Drawn, with cash in the ledger (4):
--     000104450000023  Nature's Gift Limited      24.6m, value date 2022 (taken on 31 Jul 2024), written off Jun 2026
--     000104450000041  Kadava Investment Limited  12.0m, value date 2022 (taken on 31 Jul 2024), written off Jun 2026
--     000104460000109  JVD Agro Limited           250.0m drawn 12 May 2025, paid off 31 Mar 2026
--     000104460000111  Ecogen Limited             105.06m drawn 26 May 2025, live
--
--   Approved, but no drawdown or repayment in any ledger extract (13):
--     000104420000044  Global Seeds Limited FInES      103.2m approved, 2022
--     000104430000063  Infotech Private Limited          8.0m approved, Oct 2024
--     000104430000065  Wix Ltd                          74.0m approved, Nov 2024
--     000104430000066  Doorbell Services                60.0m approved, Nov 2024
--     000104430000067  Nyamnyam Processors Limited     200.00 (two hundred) approved, Nov 2024
--     000104430000081  Pinnacle Financial Services   1,000.0m approved, Jul 2025
--     000104430000083  JAT Group Ltd                   209.3m approved, Aug 2025
--     000104430000088  Anchor Processors Limited       300.0m approved, Oct 2025
--     000104440000005  Belk Inc                         69.0m approved, Oct 2024
--     000104450000096  Champion Enterprises Inc.        88.0m approved, Oct 2024
--     000104450000097  Neha Trader                      96.3m approved, Oct 2024
--     000104460000029  Angwenda Investments Limited     24.0m approved, 2022
--     000104460000103  Albertson's Inc.                 95.0m approved, Oct 2024
--
-- Each of the 13 shows a zero balance in every Loan Book Report month we
-- hold, and no voucher at all in the ledger. Nine of them carry LOS
-- disbursement charges (agreement, legal and "Other Fee (%)") with
-- POST_FLAG = N. So the question for the 13 is whether money ever went out,
-- perhaps from another account of the same customer. For the 4, it's the
-- schedule itself.
--
--   RUN 1   account master and loan master rows for the 17
--   RUN 2   sanction rows for the 17, including deleted ones
--   RUN 3   planned drawdowns and the disbursement charges for the 17
--   RUN 4   instalment plan definitions for the 17, including deleted ones
--   RUN 5   instalment chart rows for the 17, including deleted ones
--   RUN 6   every ledger posting on any loan account of the 17 customers (new ledger)
--   RUN 7   the same from the old ledger CUMOVUCH_OLD
--   RUN 8   status history, reschedules and closures for the 17
--   RUN 9   every "Other Fee (%)" charge in LOS_DISB_CHARGES_POST, all accounts
--
-- Every table carries its owner (VGCBS). If a query errors because a table or
-- column is not there, skip it and tell us; the rest still help. All nine
-- are small (a few rows to a few thousand). Please save the results in the
-- Pack 4 folder on OneDrive, in the subfolder Pack 11\Export Results.
--
-- QUESTIONS THAT NEED NO QUERY (a short reply by email is fine; Credit or
-- Finance may know some of these better than ICT):
--   1. The 13 approved loans with nothing drawn. Was any of them ever
--      disbursed? Nine carry account status C (Global Seeds 044, Infotech
--      063, Wix 065, Doorbell 066, Nyamnyam 067, Champion 096, Neha 097,
--      Angwenda 029, Albertson's 103). Does C mean closed or cancelled? If so,
--      why do they still appear in the Loan Book Report? Some look like test
--      or training accounts (Belk Inc, Champion Enterprises Inc., Albertson's
--      Inc., Wix Ltd, Infotech, and Nyamnyam 067 approved at 200.00). Are
--      they? The other four carry status A: Pinnacle Financial Services (1
--      billion approved, July 2025), JAT Group (209.3 million, August 2025),
--      Anchor Processors (300 million, October 2025) and Belk Inc (69 million,
--      October 2024). Are these undrawn facilities still available to the
--      customer, and until when?
--   2. LOS charges on loans never drawn. The agreement, legal and other fees
--      on the 13 sit in LOS_DISB_CHARGES_POST with POST_FLAG = N, no batch and
--      no income GL. Were any of them charged to the customer or collected
--      another way?
--   3. "Other Fee (%)". 17 loans carry an LOS charge of this name, from 820.00
--      to 30 million (account 105810000003). What is it for, is it charged at
--      drawdown and posted to an income GL (which one), and is it part of the
--      loan's pricing or a charge for a separate service?
--   4. Ecogen 104460000111 (pack 10, question 6). There's no instalment
--      chart for it at all, and the rate is 0 everywhere. How are the 3.65
--      million monthly repayments set up, and what rate was agreed?
--   5. JVD Agro 104460000109. No instalment chart row was in the extract.
--      When the loan was paid off on 31 March 2026 from 104460000120, were its
--      chart rows deleted? RUN 5 asks for deleted rows too.
--   6. Nature's Gift 104450000023, Kadava 104450000041, Angwenda 104460000029
--      and Global Seeds 104420000044 date from 2022, before the migration of
--      31 July 2024. Which old-ledger account was each one before the
--      migration? Packs 8 to 10 found no account map, so a name from the
--      migration team's own working, or from Finance, would do.
--==============================================================================


--==============================================================================
-- RUN 0  |  session settings  |  run once, first, in THIS worksheet
-- Put the cursor on each ALTER SESSION line and press Ctrl+Enter, in the same
-- worksheet (same connection tab) as the queries; each tab is its own session.
ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS';
ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '.,';

-- RUN 0 check  |  do not export, just look at the grid
-- It must show a date like 2026-10-16 09:15:00 and the number 1234567.89.
SELECT SYSDATE AS today_as_text, 1234567.89 AS a_number FROM dual;


--==============================================================================
-- RUN 1  |  account master and loan master rows for the 17  |  save as AM_01_account_and_loan_master.csv
-- One row per account (or more if the loan master holds more than one): the
-- status, open date, rate and customer from ACMASTER, and the sanction,
-- period, expiry and moratorium from ACLOANMASTER. About 17 rows.
SELECT a.account_mst_id, a.glcode, a.old_ac_number, a.new_ac_number, a.sub_ac_number,
       a.scheme_mst_id, a.customer_id, a.account_name, a.account_open_date,
       a.status_code, a.account_status_date, a.status_change_reason,
       a.interest_calc_flag, a.interest_stop_date, a.interest_stop_reason,
       a.interest_policy, a.interest_rate, a.floating_flag, a.repayment_flag,
       a.opening_balances_date,
       l.acloan_mst_id, l.sanction_amount, l.sanction_date, l.period_days, l.period_months,
       l.period_years, l.expiry_date, l.funded_flag, l.pmorotorium_period, l.pmorotorium_unit,
       l.imorotorium_period, l.imorotorium_unit, l.rec_fm_newacno, l.product_type_id,
       l.delete_flag AS loan_delete_flag, l.operation_date AS loan_operation_date,
       l.enter_desc AS loan_enter_desc, l.pass_desc AS loan_pass_desc, l.old_primary_key
FROM   vgcbs.acmaster a
LEFT   JOIN vgcbs.acloanmaster l ON l.new_ac_number = a.new_ac_number AND l.sub_account_no = a.sub_ac_number
WHERE  a.new_ac_number IN ('000104420000044', '000104430000063', '000104430000065', '000104430000066',
                           '000104430000067', '000104430000081', '000104430000083', '000104430000088',
                           '000104440000005', '000104450000023', '000104450000041', '000104450000096',
                           '000104450000097', '000104460000029', '000104460000103', '000104460000109',
                           '000104460000111')
ORDER  BY a.new_ac_number, a.sub_ac_number, l.acloan_mst_id;


--==============================================================================
-- RUN 2  |  sanction rows for the 17, deleted ones too  |  save as SD_01_sanction_details.csv
-- Every sanction (and additional sanction) recorded on the 17, with its
-- delete flag, so we can see whether a sanction was later withdrawn.
SELECT s.sanc_mst_id, s.new_ac_number, s.sub_account_no, s.transaction_date, s.sanction_amount,
       s.sanction_date, s.s_period_days, s.s_period_months, s.s_period_years, s.due_date,
       s.add_sanction_amt, s.add_sanction_date, s.additional_int_rate, s.sanction_type,
       s.sanction_sub_type, s.active_flag, s.delete_flag, s.operation_date, s.enter_desc,
       s.delete_desc, s.pass_desc, s.old_primary_key
FROM   vgcbs.sanction_details s
WHERE  s.new_ac_number IN ('000104420000044', '000104430000063', '000104430000065', '000104430000066',
                           '000104430000067', '000104430000081', '000104430000083', '000104430000088',
                           '000104440000005', '000104450000023', '000104450000041', '000104450000096',
                           '000104450000097', '000104460000029', '000104460000103', '000104460000109',
                           '000104460000111')
ORDER  BY s.new_ac_number, s.sanc_mst_id;


--==============================================================================
-- RUN 3  |  planned drawdowns and the disbursement charges for the 17  |  save as DS_01_disbursement_schedule_and_charges.csv
-- LOAN_DISBURSMENT_SCHEDULE says what was to be drawn and when, and
-- STATUS_FLAG whether it was; DISBURSE_CHARGES_DETAILS the charges taken at
-- each drawdown, with the batch. One grid, the source named in column 1.
SELECT 'LOAN_DISBURSMENT_SCHEDULE' AS source_table, d.loan_disb_det_id AS row_id, d.new_ac_number,
       d.sub_account_no, d.disb_schedule_date AS row_date, d.disb_schedule_amount AS amount,
       d.status_flag, NULL AS charges_type, NULL AS batch_number, d.remarks,
       d.delete_flag, d.operation_date, d.enter_desc, d.pass_desc, d.sanc_mst_id
FROM   vgcbs.loan_disbursment_schedule d
WHERE  d.new_ac_number IN ('000104420000044', '000104430000063', '000104430000065', '000104430000066',
                           '000104430000067', '000104430000081', '000104430000083', '000104430000088',
                           '000104440000005', '000104450000023', '000104450000041', '000104450000096',
                           '000104450000097', '000104460000029', '000104460000103', '000104460000109',
                           '000104460000111')
UNION ALL
SELECT 'DISBURSE_CHARGES_DETAILS', c.disb_chg_typ_id, c.new_ac_number,
       c.sub_account_no, c.transaction_date, c.charges_amount,
       NULL, TO_CHAR(c.charges_type), TO_CHAR(c.batch_number), TO_CHAR(c.loan_disb_det_id),
       c.delete_flag, c.operation_date, c.enter_desc, c.pass_desc, NULL
FROM   vgcbs.disburse_charges_details c
WHERE  c.new_ac_number IN ('000104420000044', '000104430000063', '000104430000065', '000104430000066',
                           '000104430000067', '000104430000081', '000104430000083', '000104430000088',
                           '000104440000005', '000104450000023', '000104450000041', '000104450000096',
                           '000104450000097', '000104460000029', '000104460000103', '000104460000109',
                           '000104460000111')
ORDER  BY 3, 1, 5;


--==============================================================================
-- RUN 4  |  instalment plan definitions for the 17, deleted ones too  |  save as IP_01_instalment_plans.csv
-- MULTI_INST_DETAILS holds the plan the chart is built from (start date,
-- amount, number of instalments). Deleted definitions are wanted too: a
-- loan's first plan is often the one that was later replaced.
SELECT m.inst_det_id, m.new_ac_number, m.sub_account_no, m.transaction_date, m.installment_type,
       m.installment_pay_type, m.installment_pay_mode, m.installment_start_date, m.installment_amt,
       m.installment_end_date, m.intt_inst_start_date, m.intt_inst_amt, m.no_of_installments,
       m.org_inst_amt, m.demand_start_date, m.ref_loan_disb_det_id, m.remark,
       m.delete_flag, m.operation_date, m.enter_desc, m.delete_desc, m.pass_desc, m.old_primary_key
FROM   vgcbs.multi_inst_details m
WHERE  m.new_ac_number IN ('000104420000044', '000104430000063', '000104430000065', '000104430000066',
                           '000104430000067', '000104430000081', '000104430000083', '000104430000088',
                           '000104440000005', '000104450000023', '000104450000041', '000104450000096',
                           '000104450000097', '000104460000029', '000104460000103', '000104460000109',
                           '000104460000111')
ORDER  BY m.new_ac_number, m.inst_det_id;


--==============================================================================
-- RUN 5  |  instalment chart rows for the 17, deleted ones too  |  save as IC_01_instalment_chart_17.csv
-- The live chart, every row whatever its ACTIVE_FLAG or DELETE_FLAG. The
-- 6 October extract held chart rows for only 3 of the 17. A few hundred rows.
SELECT f.inst_chart_det_id, f.new_ac_number, f.sub_account_no, f.transaction_date, f.inst_det_id,
       f.inst_sr_no, f.installment_date, f.installment_amt, f.prin_installment_amt,
       f.intr_installment_amt, f.exp_balance, f.inst_recovered, f.interest_recovered,
       f.active_flag, f.status_change_date, f.status_reason, f.delete_flag, f.verify_flag,
       f.operation_date, f.enter_desc, f.delete_desc, f.pass_desc, f.remark
FROM   vgcbs.fixloan_inst_chart f
WHERE  f.new_ac_number IN ('000104420000044', '000104430000063', '000104430000065', '000104430000066',
                           '000104430000067', '000104430000081', '000104430000083', '000104430000088',
                           '000104440000005', '000104450000023', '000104450000041', '000104450000096',
                           '000104450000097', '000104460000029', '000104460000103', '000104460000109',
                           '000104460000111')
ORDER  BY f.new_ac_number, f.inst_det_id, f.inst_sr_no, f.inst_chart_det_id;


--==============================================================================
-- RUN 6  |  every posting on any loan account of the 17 customers, new ledger  |  save as CV_16_customer_loan_postings.csv
-- Not just the 17 accounts: every loan account (schemes 84 to 95 and 140 to
-- 145) of the same customers, in case a drawdown went to another account.
-- Deleted and unpassed vouchers are included, with their flags. A few
-- thousand rows at most.
SELECT a.customer_id, a.account_name, cv.cumvouch_det_id, cv.cb_branch_mst_id, cv.cb_glcode,
       cv.ac_glcode, cv.new_ac_number, cv.sub_account_no, cv.transaction_date, cv.cb_effective_date,
       cv.transamt, cv.interest_amount, cv.dbcr, cv.trantype, cv.batch_type, cv.batch_number,
       cv.vscrno, cv.particulars, cv.trf_glcode, cv.trf_new_ac_number, cv.original_transamt,
       cv.pass_flag, cv.delete_flag, cv.modify_flag, cv.operation_date
FROM   vgcbs.cumvouch cv
JOIN   vgcbs.acmaster a ON a.new_ac_number = cv.new_ac_number AND a.sub_ac_number = cv.sub_account_no
WHERE  a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145)
AND    a.customer_id IN (SELECT x.customer_id FROM vgcbs.acmaster x
                         WHERE x.new_ac_number IN ('000104420000044', '000104430000063', '000104430000065', '000104430000066',
                                                   '000104430000067', '000104430000081', '000104430000083', '000104430000088',
                                                   '000104440000005', '000104450000023', '000104450000041', '000104450000096',
                                                   '000104450000097', '000104460000029', '000104460000103', '000104460000109',
                                                   '000104460000111'))
ORDER  BY a.customer_id, cv.new_ac_number, cv.transaction_date, cv.cumvouch_det_id;


--==============================================================================
-- RUN 7  |  the same from the old ledger  |  save as CV_17_customer_loan_postings_old.csv
-- As RUN 6, from CUMOVUCH_OLD, for the loans that date from before the
-- migration. If it returns nothing, export the empty grid anyway.
SELECT a.customer_id, a.account_name, cv.cumvouch_det_id, cv.cb_branch_mst_id, cv.cb_glcode,
       cv.ac_glcode, cv.new_ac_number, cv.sub_account_no, cv.transaction_date, cv.cb_effective_date,
       cv.transamt, cv.interest_amount, cv.dbcr, cv.trantype, cv.batch_type, cv.batch_number,
       cv.vscrno, cv.particulars, cv.trf_glcode, cv.trf_new_ac_number, cv.original_transamt,
       cv.pass_flag, cv.delete_flag, cv.modify_flag, cv.operation_date
FROM   vgcbs.cumovuch_old cv
JOIN   vgcbs.acmaster a ON a.new_ac_number = cv.new_ac_number AND a.sub_ac_number = cv.sub_account_no
WHERE  a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145)
AND    a.customer_id IN (SELECT x.customer_id FROM vgcbs.acmaster x
                         WHERE x.new_ac_number IN ('000104420000044', '000104430000063', '000104430000065', '000104430000066',
                                                   '000104430000067', '000104430000081', '000104430000083', '000104430000088',
                                                   '000104440000005', '000104450000023', '000104450000041', '000104450000096',
                                                   '000104450000097', '000104460000029', '000104460000103', '000104460000109',
                                                   '000104460000111'))
ORDER  BY a.customer_id, cv.new_ac_number, cv.transaction_date, cv.cumvouch_det_id;


--==============================================================================
-- RUN 8  |  status history, reschedules and closures for the 17  |  save as SH_01_status_reschedule_closure.csv
-- When each status was set and why, any reschedule, and any closure with the
-- account the balance went to (TRF_NEW_AC_NUMBER: JVD Agro's payoff).
SELECT 'ACCOUNT_STATUS_HISTORY' AS source_table, h.ac_status_mst_id AS row_id, h.new_ac_number,
       h.sub_ac_number, h.transaction_date, h.status_eff_date AS effective_date,
       h.prev_status_code || ' > ' || h.status_code AS change, h.status_change_reason AS detail,
       NULL AS amount, NULL AS trf_new_ac_number, h.active_flag, h.delete_flag, h.operation_date, h.enter_desc
FROM   vgcbs.account_status_history h
WHERE  h.new_ac_number IN ('000104420000044', '000104430000063', '000104430000065', '000104430000066',
                           '000104430000067', '000104430000081', '000104430000083', '000104430000088',
                           '000104440000005', '000104450000023', '000104450000041', '000104450000096',
                           '000104450000097', '000104460000029', '000104460000103', '000104460000109',
                           '000104460000111')
UNION ALL
SELECT 'LOAN_RESCHEDULE_DETAILS', r.loan_resch_det_id, r.new_ac_number,
       r.sub_account_no, r.transaction_date, NULL,
       TO_CHAR(r.reschedule_type), 'sanction ' || r.old_sanc_amt || ' > ' || r.sanction_amt || '; instalment ' || r.old_inst_amt || ' > ' || r.installment_amt,
       r.balance, NULL, r.pass_flag, r.delete_flag, r.operation_date, r.enter_desc
FROM   vgcbs.loan_reschedule_details r
WHERE  r.new_ac_number IN ('000104420000044', '000104430000063', '000104430000065', '000104430000066',
                           '000104430000067', '000104430000081', '000104430000083', '000104430000088',
                           '000104440000005', '000104450000023', '000104450000041', '000104450000096',
                           '000104450000097', '000104460000029', '000104460000103', '000104460000109',
                           '000104460000111')
UNION ALL
SELECT 'ACCOUNT_CLOSER_HISTORY', c.act_close_det_id, c.new_ac_number,
       c.sub_ac_number, c.transaction_date, c.maturity_date,
       c.batch_type || ' ' || c.batch_number, 'opened ' || TO_CHAR(c.ac_open_date, 'YYYY-MM-DD') || '; rate ' || c.interest_rate,
       c.account_balance, c.trf_new_ac_number, c.post_flag, NULL, NULL, NULL
FROM   vgcbs.account_closer_history c
WHERE  c.new_ac_number IN ('000104420000044', '000104430000063', '000104430000065', '000104430000066',
                           '000104430000067', '000104430000081', '000104430000083', '000104430000088',
                           '000104440000005', '000104450000023', '000104450000041', '000104450000096',
                           '000104450000097', '000104460000029', '000104460000103', '000104460000109',
                           '000104460000111')
ORDER  BY 3, 5, 1;


--==============================================================================
-- RUN 9  |  every "Other Fee (%)" charge, all accounts  |  save as CH_03_other_fee_charges.csv
-- For question 3. Every LOS charge named Other Fee, on any account, with
-- whether it was posted, the batch and the income GL. About 20 rows. If the
-- name is spelt differently, the LIKE catches it.
SELECT p.lo_disb_charg_det_id, p.disb_charges_name, p.new_ac_number, p.sub_ac_number,
       p.actual_charges_amt, p.aplli_charges_amt, p.stax_amount, p.post_flag, p.post_userid,
       p.batch_number, p.particular, p.income_gl, p.enter_desc
FROM   vgcbs.los_disb_charges_post p
WHERE  UPPER(p.disb_charges_name) LIKE '%OTHER%'
ORDER  BY p.new_ac_number, p.lo_disb_charg_det_id;
