s.append(Paragraph("Query GL_03: where the 22 year-end interest debits were posted", st["title"]))
s.append(Paragraph("A note for Barry Makumba, with a question for Finance, from Dupleix Institute  ·  8 October 2026  ·  follows the note of 7 October on the 28 interest adjustments", st["meta"]))

H("1. What we found since the note of 7 October")
P("The 28 'Diff Int by ROI' adjustments of 31 December 2025 are all on the customer loan accounts, in the same interest-posting batches as December's ordinary interest: batch INTP 41 for the 7 on 1050101 (MAIIC Agricultural) and INTP 42 for the 21 on 1050102 (MAIIC Industrial). Nothing on FInES or term loans. They are two transaction types: the 6 credits (85,166,683.31 in the customers' favour) are type 120; the 22 debits (96,390,096.16 charged to customers) are type 303, the ordinary interest type.")
P("We then checked the other side of each posting against the trial balances, which is where the question arises.")
TAB(["Test", "Result"], [
    ["Loan GL side, December 2025: TB movement against the ledger's December postings on 1050101 and 1050102", "Agrees to the kwacha on both (-168,658,232.85 and -251,704,349.93), debits and credits included"],
    ["The 6 credits: December movement on interest income 4215 and 4216 against ordinary interest less the credits", "4215: 60,086,643.19 against 60,086,646.38 (MWK 3 apart). 4216: 151,902,197.02 against 151,902,220.31 (MWK 23 apart). The credits went through interest income"],
    ["The 22 debits: in December income?", "No. The December movement on 4215 and 4216 contains the ordinary interest and the credits only"],
    ["The 22 debits: in January 2026 income?", "No. January income on 4215 and 4216 equals January's ordinary interest exactly (22,052,068.05 and 167,288,651.29)"],
    ["Any single GL that moved by 96,390,096.16, 35,940,279.17 or 60,449,816.99 in December?", "None. The contra sits inside an account with other December movements"],
], [0.5, 0.5])
P("So the 96,390,096.16 charged to customers was credited to a balance-sheet account where other December movements hide it. The likeliest is <b>1040 Accrued Interest Income</b>, which fell by 1,060.6 million in December with the year-end reversal of accruals; a 96.4 million credit inside that cannot be seen from the trial balance. Only the postings themselves can say.")

H("2. Why it matters")
P("Economically the 22 debits are 2025 interest on those loans: E-Banker recomputed the year at the rate on the account at year end and charged the shortfall to the customer. If the other side went to accrued interest rather than to income, the trial balance's 2025 interest income on the MAIIC book is 96.4 million lower than the ledger's interest, and that is a reconciling line the engine will show until Finance says which treatment was intended. It also decides whether the 2025 figure Deloitte signed includes these amounts as income or not.")

H("3. The query")
P("Our ledger extract was filtered to the 184 loan accounts, so it never held the GL leg of these batches. One unfiltered pull of the two batches shows the contra codes directly. Run the two ALTER SESSION lines first (they last for the session only, as explained in the 6 October request), then the SELECT.")
P("<b>Query GL_03: every leg of the two year-end interest batches.</b> Save as GL_03_diffint_batch_legs.csv.")
CODE("""ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS';
ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '.,';

SELECT cumvouch_det_id, cb_glcode, ac_glcode, new_ac_number, sub_account_no,
       transaction_date, transamt, dbcr, trantype, batch_type, batch_number,
       vscrno, particulars
FROM   cumvouch
WHERE  transaction_date = DATE '2025-12-31'
AND    batch_type   = 'INTP'
AND    batch_number IN (41, 42)
ORDER  BY batch_number, vscrno, cumvouch_det_id;""")
P("What to look for: rows where <b>cb_glcode differs from ac_glcode</b>, or where new_ac_number is blank, are the GL legs; their cb_glcode is the account that took the other side of each adjustment. The 28 customer legs we already hold carry voucher numbers 14625 to 14652, so the GL legs should carry the same numbers.")
P("<b>If the GL legs are not in CUMVOUCH</b> (some cores keep GL vouchers in their own table), the second query finds them by voucher number in the general-ledger voucher table; the name of that table is in the data dictionary results of 6 October (DD_02, tables; DD_07, tables by subject, under general ledger). Save as GL_03b_diffint_gl_vouchers.csv.")
CODE("""SELECT *
FROM   <gl voucher table>
WHERE  transaction_date = DATE '2025-12-31'
AND    vscrno BETWEEN 14625 AND 14652
ORDER  BY vscrno;""")

H("4. The question for Finance, once the rows are in")
P("Was the shortfall charged to customers on 31 December 2025 intended as 2025 interest income, or as recovery of interest already accrued on 1040? The answer decides whether 96,390,096.16 belongs in the 2025 income line and how the engine reconciles the MAIIC book to the audited accounts.")
s.append(Spacer(1, 6))
s.append(Paragraph("Edward Mazibuko and Wadzanai Rombe, Dupleix Institute. Sources: the P1_01 ledger, the November 2025 and January 2026 trial balances, and the December 2025 E-Banker trial balance in the AFS bridge workbook of 10 September 2026.", st["meta"]))

def footer(c, d):
    c.saveState(); c.setStrokeColor(LINE); c.setLineWidth(0.5); c.line(18 * mm, 14 * mm, A4[0] - 18 * mm, 14 * mm)
    c.setFont("Seg", 8); c.setFillColor(GREY); c.drawString(18 * mm, 9.5 * mm, "Query GL_03: where the 22 year-end interest debits were posted  ·  Dupleix Institute  ·  8 October 2026")
    c.drawRightString(A4[0] - 18 * mm, 9.5 * mm, f"Page {d.page}"); c.restoreState()
Q = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC"
OUT = Q + r"\Query GL_03 - where the 22 year-end interest debits were posted - 8 Oct 2026.pdf"
doc = BaseDocTemplate(OUT, pagesize=A4, leftMargin=18 * mm, rightMargin=18 * mm, topMargin=16 * mm, bottomMargin=20 * mm, title="Query GL_03: where the 22 year-end interest debits were posted", author="Dupleix Institute")
doc.addPageTemplates([PageTemplate(id="p", frames=[Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id="f", leftPadding=0, rightPadding=0, topPadding=0, bottomPadding=0)], onPage=footer)])
doc.build(s); print("written", OUT)

sql = """-- GL_03  |  every leg of the two year-end interest batches of 31 December 2025  |  save as GL_03_diffint_batch_legs.csv
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
"""
open(Q + r"\Query GL_03 - where the 22 year-end interest debits were posted - 8 Oct 2026.sql", "w", encoding="utf-8").write(sql); print("sql written")
