from ed import edit
p='docs/bootstrap/queries/Route 2 - scheduled export (SQLcl) - template.sql'
edit(p, r"""-- the masters come whole each time (P1_02, P1_03, P1_04, P2_06, P2_07, P3_12, P3_13): copy the SELECTs of the
-- versioned queries in this folder here, one SPOOL each, unchanged.""", r"""-- The instalment charts come whole each month (added 14 October 2026). Staging reads E-Banker's chart in
-- force at each month-end (InstalmentChartHistory: the missed-instalment trigger and the cure period) from
-- the current chart (P2_10), the instalment plans (P3_11) and the superseded instalment rows (RS_02a). Without
-- them in the monthly pack the chart history stops at the last manual pack (12 October 2026) and every later
-- month-end is staged on a stale chart. The SELECTs are the registered queries, unchanged; RS_02a is one file
-- here because SQLcl has no grid limit (the manual pack split it in five by account block).

-- P2_10 the current instalment chart with the amount recovered against each instalment (live rows only)
SPOOL "\maiic-fs\ebanker-feed\pack-&period\P2_10_instalment_chart_current.csv"
SELECT f.*
FROM   fixloan_inst_chart f
WHERE  f.delete_flag = 'N'
AND    f.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
ORDER  BY f.new_ac_number, f.installment_date, f.inst_chart_det_id;
SPOOL OFF

-- P3_11 the instalment plans
SPOOL "\maiic-fs\ebanker-feed\pack-&period\P3_11_instalment_plans.csv"
SELECT m.*
FROM   multi_inst_details m
WHERE  m.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
ORDER  BY m.new_ac_number, m.inst_det_id;
SPOOL OFF

-- RS_02a the superseded instalment rows (deleted or inactive): the earlier versions of each schedule
SPOOL "\maiic-fs\ebanker-feed\pack-&period\RS_02a_superseded_instalments.csv"
SELECT f.*
FROM   fixloan_inst_chart f
WHERE  (f.delete_flag = 'Y' OR f.active_flag = 'N')
AND    f.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                           WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
ORDER  BY f.new_ac_number, f.operation_date, f.inst_sr_no;
SPOOL OFF

-- the masters come whole each time (P1_02, P1_03, P1_04, P2_06, P2_07, P3_12, P3_13): copy the SELECTs of the
-- versioned queries in this folder here, one SPOOL each, unchanged.""")
