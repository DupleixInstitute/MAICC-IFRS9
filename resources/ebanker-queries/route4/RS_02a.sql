SELECT f.*
FROM   fixloan_inst_chart f
WHERE  (f.delete_flag = 'Y' OR f.active_flag = 'N')
AND    f.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                            WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
ORDER  BY f.new_ac_number, f.operation_date, f.inst_sr_no
