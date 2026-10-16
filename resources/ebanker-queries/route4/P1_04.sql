SELECT d.*
FROM   interest_slab_details d
WHERE  d.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                            WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
ORDER  BY d.new_ac_number, d.applicable_from_date, d.interest_rate_det_id
