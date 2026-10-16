SELECT cumvouch_det_id, cb_branch_mst_id, t_branch_mst_id, o_branch_mst_id, cb_glcode, ac_glcode, new_ac_number,
       sub_account_no, transaction_date, transamt, dbcr, trantype, batch_type, batch_number, vscrno, particulars,
       pass_flag, delete_flag, modify_delete_flag
FROM   cumvouch
WHERE  (cb_glcode IN ('4871','4873','4874','4875') OR ac_glcode IN ('4871','4873','4874','4875'))
AND    (cumvouch_det_id > :watermark OR transaction_date >= ADD_MONTHS(TO_DATE(:period_start,'YYYY-MM-DD'), -2))
ORDER  BY cumvouch_det_id
