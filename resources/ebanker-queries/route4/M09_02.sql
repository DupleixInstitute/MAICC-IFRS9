SELECT b.*
FROM   account_balance b
WHERE  b.new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                            WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,140,141,142,143,144,145))
AND    b.account_bal_mst_id > :watermark
ORDER  BY b.account_bal_mst_id
