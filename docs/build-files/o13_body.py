s.append(Paragraph("Staging rebuttal (O13): the 90-day presumption and the directive", st["title"]))
s.append(Paragraph("A sign-off paper for Dr Thomson Kumwenda, CFO, from Dupleix Institute  ·  8 October 2026  ·  with a sentence drafted for the 2026 accounting policy note", st["meta"]))

H("1. What needs signing, in one paragraph")
P("MAIIC's expected-credit-loss model treats a loan as credit-impaired (Stage 3) when it is more than 181 days past due. The signed 2025 accounting policy says 90 days, with a door left open for \"a more lagging default criterion\" where there is reasonable and supportable information for it. The door was walked through in the model but the policy note does not say so. The reason exists and is strong: the Reserve Bank's own directive for development finance institutions classifies a medium- or long-term facility as non-performing from 181 days. This paper asks you to sign that rebuttal so it is on the file, and to approve a sentence for the 2026 policy note.")

H("2. The three rules today")
TAB(["", "Stage 3 (default)", "Stage 2 (significant increase in risk)"], [
    ["Accounting policy, 2025 statements (notes 3.8, 22.8.1)", "Four consecutive missed payments, or 90 days past due; a more lagging criterion allowed with reasonable and supportable information", "Changes in the probability of default against inception; qualitative events; the 30-day backstop"],
    ["November 2025 ECL model", "Over 181 days past due", "31 to 180 days past due (the probability-change test fired on no loan)"],
    ["RBM Financial Services (Credit Risk Management for DFIs) Directive, 2018, s.10", "Non-performing: 181 days for medium- and long-term facilities, 91 days for short-term (12 months or less)", "Not addressed; the directive's standard band runs to 90 days for medium and long term"],
], [0.3, 0.38, 0.32])
P("The directive is in the Malawi Gazette Supplement of 13 July 2018 (No. 18A, pages 42 to 47); a copy is filed with the specification. Section 13 places every non-performing facility on non-accrual, which is the rule behind the interest-suspense account.")

H("3. The rebuttal, as the engine records it")
P("IFRS 9 B5.5.37 presumes that default occurs no later than 90 days past due, and allows the presumption to be rebutted where the entity has reasonable and supportable information that a more lagging criterion is appropriate. For MAIIC's medium- and long-term facilities the information is the regulator's own classification of development-finance lending: the directive treats such a facility as standard to 90 days and special mention to 180, and as non-performing only from 181. Development-finance cash flows are seasonal and lumpy (moratoria, agricultural cycles), which is why the regulator set the line where it did. MAIIC therefore treats a medium- or long-term facility as credit-impaired at 181 days past due, a short-term facility (12 months or less) at 91 days as the directive classifies it, and keeps the second test of its own policy, four consecutive missed payments, as a trigger beside the day count. Stage 2 stays at 31 days past due; the 30-day presumption of B5.5.11 is not rebutted.")
P("This reproduces the basis of the 2025 figures; nothing in the signed statements moves.")

H("4. A sentence for the 2026 policy note (22.8.1)")
P("\"The Corporation considers a financial instrument defaulted, and therefore Stage 3, when the borrower has missed four consecutive contractual payments, or when the instrument is more than 181 days past due for a facility with a repayment period of more than twelve months and more than 90 days past due for a facility of twelve months or less. The 181-day criterion rebuts the 90-day presumption of IFRS 9 on the basis of the Reserve Bank of Malawi's Financial Services (Credit Risk Management for Development Finance Institutions) Directive, 2018, under which such facilities are classified non-performing from 181 days, and reflects the seasonal cash flows of development-finance lending.\"")

H("5. Two small things alongside")
P("Which days past due: the directive counts from the overdue instalment; the core system also ages the overdue amounts by bucket, and the two can differ on a partly cured account. The engine counts from the instalment, as the directive does, and shows the other beside it; that choice is governed and can be changed with a reason. And the November model carried 100 loans where the core system carried 125; we have asked Finance which 25 are left out and why, so the two stagings can be reconciled.")
s.append(Spacer(1, 6))
s.append(Paragraph("Edward Mazibuko and Wadzanai Rombe, Dupleix Institute. Sources: the signed 2025 financial statements; the MAIIC ECL Model, November 2025; the Malawi Gazette Supplement of 13 July 2018, No. 18A; specification v4 section 3.6, decision D31.", st["meta"]))

def footer(c, d):
    c.saveState(); c.setStrokeColor(LINE); c.setLineWidth(0.5); c.line(18 * mm, 14 * mm, A4[0] - 18 * mm, 14 * mm)
    c.setFont("Seg", 8); c.setFillColor(GREY); c.drawString(18 * mm, 9.5 * mm, "Staging rebuttal (O13)  ·  Dupleix Institute  ·  8 October 2026")
    c.drawRightString(A4[0] - 18 * mm, 9.5 * mm, f"Page {d.page}"); c.restoreState()
OUT = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\specs\O13 staging rebuttal - the 90-day presumption and the RBM DFI directive - 8 Oct 2026.pdf"
doc = BaseDocTemplate(OUT, pagesize=A4, leftMargin=18 * mm, rightMargin=18 * mm, topMargin=16 * mm, bottomMargin=20 * mm, title="Staging rebuttal (O13)", author="Dupleix Institute")
doc.addPageTemplates([PageTemplate(id="p", frames=[Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id="f", leftPadding=0, rightPadding=0, topPadding=0, bottomPadding=0)], onPage=footer)])
doc.build(s); print("written", OUT)
