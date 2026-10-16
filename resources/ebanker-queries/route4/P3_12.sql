SELECT d.*
FROM   loan_disbursment_schedule d
WHERE  d.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                            WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
ORDER  BY d.new_ac_number, d.disb_schedule_date, d.loan_disb_det_id
