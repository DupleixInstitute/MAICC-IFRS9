SELECT h.*
FROM   account_status_history h
WHERE  h.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                            WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
ORDER  BY h.new_ac_number, h.status_eff_date, h.ac_status_mst_id
