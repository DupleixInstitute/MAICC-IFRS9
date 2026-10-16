SELECT cv.*
FROM   cumvouch cv
WHERE  cv.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                            WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
AND    (cv.cumvouch_det_id > :watermark OR cv.transaction_date >= ADD_MONTHS(TO_DATE(:period_start,'YYYY-MM-DD'), -2))
ORDER  BY cv.cumvouch_det_id
