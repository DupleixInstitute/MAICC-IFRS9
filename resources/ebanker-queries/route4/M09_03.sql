SELECT l.*
FROM   loan_book_details_all l
WHERE  l.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                            WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
AND    l.loan_book_det_id_a > :watermark
ORDER  BY l.loan_book_det_id_a
