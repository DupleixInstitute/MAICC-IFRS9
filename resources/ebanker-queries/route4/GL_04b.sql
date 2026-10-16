SELECT cv.cumvouch_det_id, cv.cb_branch_mst_id, cv.t_branch_mst_id, cv.o_branch_mst_id, cv.cb_glcode, cv.ac_glcode,
       cv.new_ac_number, cv.sub_account_no, cv.transaction_date, cv.transamt, cv.dbcr, cv.trantype, cv.batch_type,
       cv.batch_number, cv.vscrno, cv.particulars, cv.pass_flag, cv.delete_flag, cv.modify_delete_flag
FROM   cumvouch cv
WHERE  (cv.transaction_date, cv.batch_type, cv.batch_number) IN (
         SELECT DISTINCT x.transaction_date, x.batch_type, x.batch_number FROM cumvouch x
         WHERE (x.cb_glcode IN ('4871','4873','4874','4875') OR x.ac_glcode IN ('4871','4873','4874','4875'))
         AND   (x.cumvouch_det_id > :watermark OR x.transaction_date >= ADD_MONTHS(TO_DATE(:period_start,'YYYY-MM-DD'), -2)))
ORDER  BY cv.cumvouch_det_id
