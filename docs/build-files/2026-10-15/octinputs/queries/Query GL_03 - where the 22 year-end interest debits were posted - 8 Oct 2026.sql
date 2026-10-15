-- GL_03  |  every leg of the two year-end interest batches of 31 December 2025  |  save as GL_03_diffint_batch_legs.csv
-- Dupleix Institute, 8 October 2026. Run the two ALTER SESSION lines first (they last for the session only).
ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS';
ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '.,';

SELECT cumvouch_det_id, cb_glcode, ac_glcode, new_ac_number, sub_account_no,
       transaction_date, transamt, dbcr, trantype, batch_type, batch_number,
       vscrno, particulars
FROM   cumvouch
WHERE  transaction_date = DATE '2025-12-31'
AND    batch_type   = 'INTP'
AND    batch_number IN (41, 42)
ORDER  BY batch_number, vscrno, cumvouch_det_id;

-- GL_03b  |  only if the GL legs are not in CUMVOUCH: the GL voucher table by voucher number  |  save as GL_03b_diffint_gl_vouchers.csv
-- SELECT * FROM <gl voucher table> WHERE transaction_date = DATE '2025-12-31' AND vscrno BETWEEN 14625 AND 14652 ORDER BY vscrno;
