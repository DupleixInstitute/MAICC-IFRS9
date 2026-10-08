s.append(Paragraph("Open choice O22: the Mega Farm loans and the EIR engine", st["title"]))
s.append(Paragraph("A recommendation for Dr Thomson Kumwenda, CFO, from Dupleix Institute  ·  8 October 2026  ·  for confirmation in the Governance Centre", st["meta"]))

H("1. The question")
P("Should the Mega Farm programme loans be inside the engine that recalculates MAIIC's interest at the effective interest rate? They are schemes 96 to 103 in E-Banker, about 7,000 accounts, K51.5 billion owed at 31 December 2025, of which the seed loans are K7.6 billion. On the trial balance they are half of all loan interest in 2025, so this is the largest single thing that could move the revenue figure the engine produces.")

H("2. What the December 2025 financial statements say")
P("The statements keep the programme apart from MAIIC's own lending on every page: separate lines on the balance sheet for the loans, the cash, the maize and the fund; a separate table in note 8; its own note 11(c) for the fund. Three facts from those notes decide the question.")
TAB(["Fact", "Where", "What it says, in plain words"], [
    ["Whose money, whose loss", "Note 11(c)", "The Government set aside K20 billion; MAIIC 'agreed to manage and administer the said funds on behalf of Government'. When farmers do not repay, the loss is taken off the Government's fund: K37.4 billion of expected losses and a K1.2 billion maize write-down in 2025. The loss is the Government's"],
    ["What MAIIC earns", "Notes 11(c), 15a", "Farmers pay 15 percent, 'where 5 percent is payable to MAIIC and 10 percent is credited to Government Mega Farm funds account'; plus a 5 percent management fee on the fund and a K1 billion commission. MAIIC's return is a fixed slice set by the agreement, not a rate it earns on its own money"],
    ["The state of the book", "Note 8", "K48.7 billion of the K51.5 billion is in default (Stage 3), with K39.8 billion provided against it. MAIIC's own 5 percent interest share, K2.8 billion, sits in MAIIC's receivables with its own K2.1 billion provision (notes 9a and 19); that provision is the only Mega Farm loss MAIIC itself carries"],
], [0.2, 0.14, 0.66])

H("3. Why that keeps the loans out of the EIR engine")
P("The effective interest rate is the tool for spreading a loan's fees and discounts over its life, so that the return MAIIC reports is the return it really earns. On the Mega Farm loans there are no such fees charged to the farmer, the rate is fixed by agreement and split by contract, and the return MAIIC keeps is 5 percent whatever the loan does. Recalculating these loans at an effective rate would change nothing the statements report. What matters on this book is the provision: 95 percent of it is in default, and the rule for a loan in default is that interest is counted only on the amount expected to come back, not on the full balance. That is a provisioning question, and the system's provisioning module already handles the other book.")

H("4. The recommendation")
TAB(["", "Recommendation"], [
    ["EIR engine", "The Mega Farm loans stay outside it, and every engine report and audit workbook says so"],
    ["MAIIC's 5 percent share", "The engine works it out on the amount expected to be recovered from each loan and records it in MAIIC's receivable; the fund's 10 percent is worked out alongside and credited to the fund"],
    ["Provisioning (ECL) module", "The loans go in completely: staging, the chance of default, the loss if it happens, the provision for each scheme; the provision charged to the fund, and the write-down of MAIIC's own share charged to MAIIC"],
    ["Reporting", "The note 8 table, the note 11(c) fund roll-forward, the note 9a receivable and its provision, and the note 9c maize are produced by the system as one Mega Farms report, instead of being typed"],
    ["Data", "The pack 2 extracts of 8 October (MF_01 to MF_08) bring the eight schemes into the system; nothing else is needed"],
], [0.22, 0.78])
P("Section 16 of the specification sets this out in full, in plain language, with the step-by-step path of a seed loan through the system. It is recorded there as decision D30 and seeded in the Governance Centre against O22; it waits only for your confirmation.")

H("5. What it does to your number")
P("The figure you asked for, how much revenue moves between years when interest is recalculated at the effective rate, becomes a figure about MAIIC's own lending: K1.4 billion of loan interest in 2024 and K5.6 billion in 2025. The Mega Farm interest stays where the statements put it. The number gets smaller, cleaner and easier to defend to Deloitte, who have already considered the programme's principal-and-agent question in the statements themselves (page 34).")
s.append(Spacer(1, 6))
s.append(Paragraph("Edward Mazibuko and Wadzanai Rombe, Dupleix Institute. Sources: the signed 2025 financial statements (notes 8, 9a, 9c, 11(c), 15a, 19), the AFS bridge workbook of 10 September 2026, the monthly trial balances, the E-Banker data dictionary of 6 October 2026.", st["meta"]))

def footer(c, d):
    c.saveState(); c.setStrokeColor(LINE); c.setLineWidth(0.5); c.line(18 * mm, 14 * mm, A4[0] - 18 * mm, 14 * mm)
    c.setFont("Seg", 8); c.setFillColor(GREY); c.drawString(18 * mm, 9.5 * mm, "Open choice O22: the Mega Farm loans and the EIR engine  ·  Dupleix Institute  ·  8 October 2026")
    c.drawRightString(A4[0] - 18 * mm, 9.5 * mm, f"Page {d.page}"); c.restoreState()
OUT = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\specs\O22 recommendation - the Mega Farm loans and the EIR engine - 8 Oct 2026.pdf"
doc = BaseDocTemplate(OUT, pagesize=A4, leftMargin=18 * mm, rightMargin=18 * mm, topMargin=16 * mm, bottomMargin=20 * mm, title="Open choice O22: the Mega Farm loans and the EIR engine", author="Dupleix Institute")
doc.addPageTemplates([PageTemplate(id="p", frames=[Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id="f", leftPadding=0, rightPadding=0, topPadding=0, bottomPadding=0)], onPage=footer)])
doc.build(s); print("written", OUT)
