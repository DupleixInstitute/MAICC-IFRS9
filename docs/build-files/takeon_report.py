"""Readiness report on the take-on schedule and the proposed account mapping (PDF)."""
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.lib import colors
from reportlab.lib.styles import ParagraphStyle
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import BaseDocTemplate, PageTemplate, Frame, Paragraph, Spacer, Table, TableStyle, Image, ListFlowable, ListItem, CondPageBreak

F = r"C:\Windows\Fonts"
pdfmetrics.registerFont(TTFont("Seg", F + r"\segoeui.ttf")); pdfmetrics.registerFont(TTFont("Seg-B", F + r"\segoeuib.ttf"))
pdfmetrics.registerFont(TTFont("Seg-I", F + r"\segoeuii.ttf")); pdfmetrics.registerFont(TTFont("Seg-BI", F + r"\segoeuiz.ttf"))
pdfmetrics.registerFontFamily("Seg", normal="Seg", bold="Seg-B", italic="Seg-I", boldItalic="Seg-BI")
NAVY = colors.HexColor("#1B2A41"); ORANGE = colors.HexColor("#E07B00"); GREY = colors.HexColor("#5B6470"); LIGHT = colors.HexColor("#F4F6F9"); LINE = colors.HexColor("#C9D1DB"); AMBER = colors.HexColor("#FFF4E0"); GREEN = colors.HexColor("#EAF6EC")
st = {"title": ParagraphStyle("t", fontName="Seg-B", fontSize=20, leading=25, textColor=NAVY, spaceAfter=4),
      "meta": ParagraphStyle("m", fontName="Seg", fontSize=9.5, leading=13, textColor=GREY, spaceAfter=10),
      "h1": ParagraphStyle("h1", fontName="Seg-B", fontSize=14, leading=18, textColor=NAVY, spaceBefore=12, spaceAfter=5, keepWithNext=1),
      "h": ParagraphStyle("h", fontName="Seg-B", fontSize=11.5, leading=15, textColor=ORANGE, spaceBefore=9, spaceAfter=4, keepWithNext=1),
      "p": ParagraphStyle("p", fontName="Seg", fontSize=10.3, leading=14.6, spaceAfter=6), "li": ParagraphStyle("li", fontName="Seg", fontSize=10.3, leading=14.6),
      "cell": ParagraphStyle("c", fontName="Seg", fontSize=8.8, leading=11.6), "head": ParagraphStyle("hd", fontName="Seg-B", fontSize=8.8, leading=11.6, textColor=colors.white),
      "box": ParagraphStyle("b", fontName="Seg", fontSize=10.3, leading=14.6, spaceAfter=3)}
W = A4[0] - 36 * mm
s = []
def P(t): s.append(Paragraph(t, st["p"]))
def H1(t): s.append(Paragraph(t, st["h1"]))
def H(t): s.append(Paragraph(t, st["h"]))
def BUL(items, numbered=False):
    s.append(ListFlowable([ListItem(Paragraph(i, st["li"]), leftIndent=14, spaceAfter=3) for i in items], bulletType="1" if numbered else "bullet", start=None if numbered else "•", leftIndent=14, bulletColor=NAVY if numbered else ORANGE, bulletFontSize=9, bulletFontName="Seg")); s.append(Spacer(1, 4))
def TAB(header, rows, widths):
    data = [[Paragraph(h, st["head"]) for h in header]] + [[Paragraph(str(c), st["cell"]) for c in r] for r in rows]
    t = Table(data, colWidths=[W * x for x in widths], repeatRows=1)
    sty = [("BACKGROUND", (0, 0), (-1, 0), NAVY), ("VALIGN", (0, 0), (-1, -1), "TOP"), ("LEFTPADDING", (0, 0), (-1, -1), 5), ("RIGHTPADDING", (0, 0), (-1, -1), 5),
           ("TOPPADDING", (0, 0), (-1, -1), 4), ("BOTTOMPADDING", (0, 0), (-1, -1), 5), ("LINEBELOW", (0, 0), (-1, -1), 0.4, LINE), ("BOX", (0, 0), (-1, -1), 0.6, LINE)]
    for i in range(2, len(data), 2): sty.append(("BACKGROUND", (0, i), (-1, i), LIGHT))
    t.setStyle(TableStyle(sty)); s.append(t); s.append(Spacer(1, 8))
def BOX(paras, colour=AMBER):
    t = Table([[[Paragraph(x, st["box"]) for x in paras]]], colWidths=[W])
    t.setStyle(TableStyle([("BACKGROUND", (0, 0), (-1, -1), colour), ("LINEBEFORE", (0, 0), (0, -1), 3, ORANGE), ("LEFTPADDING", (0, 0), (-1, -1), 10), ("RIGHTPADDING", (0, 0), (-1, -1), 10), ("TOPPADDING", (0, 0), (-1, -1), 8), ("BOTTOMPADDING", (0, 0), (-1, -1), 6)]))
    s.append(t); s.append(Spacer(1, 8))

LOGO = r"C:\Users\wadza\OneDrive\2026\Tenders\PFA\Excel & Data Analysis Training\Basic Excel Course\3 Source Files\img\dupleix-logo.png"
try:
    im = Image(LOGO); r = im.imageHeight / im.imageWidth; im.drawWidth = 36 * mm; im.drawHeight = 36 * mm * r; im.hAlign = "LEFT"; s.append(im); s.append(Spacer(1, 8))
except Exception: pass
s.append(Paragraph("The take-on schedule: is it ready to load, and what we ask Tamanda to confirm", st["title"]))
s.append(Paragraph("Dupleix Institute  ·  7 October 2026  ·  companion to the workbook \"Take-on schedules with mapping - for Tamanda to confirm - 7 Oct 2026.xlsx\"", st["meta"]))
BOX(["<b>In one paragraph.</b> The take-on workbook (\"Amortisation schedules as at 31 October 2024\") lists 109 facilities and carries 100 amortisation blocks. "
     "We have matched 105 of the 109 to E-Banker account numbers, 104 of them with high confidence, using the amounts, the dates and the names; for every one of "
     "the 77 loans E-Banker took on in July 2024, the workbook's principal equals the system's opening posting to the cent. The remaining four are one 2022 "
     "loan we cannot place and three equity holdings that are not loans. The upload sheet is built. What is still needed before the pre-migration history goes "
     "into the engine: Tamanda's tick on the mapping, five specific confirmations, the origination fees (the workbook has none), and a decision on whose balance "
     "at 31 October 2024 is right where the two differ."])

H1("1. What the workbook holds")
TAB(["Part", "What it is", "What we found"], [
    ["Sheet \"Loan Book\"", "One row per facility at 31 October 2024: name, type, value date, maturity, tenor, moratorium, rate, approved, disbursed, principal, interest to date, repayments, carrying amount, arrears, ageing, segment, industry", "109 rows: 103 debt, 6 equity. Rate present on 102, moratorium text on 101. No account numbers, no fees"],
    ["Sheet \"Amortisation and Repayments\"", "One block per loan: principal, rate, term, a period table with opening balance, payment, interest, principal, closing balance, amount repaid and arrears; rate steps and restructure columns above", "100 blocks, every one titled with the customer's name (our September note that only four had names was wrong). 4 marked restructured, 19 with dated rate steps, 3 fully repaid. Between 12 and 147 periods each; first periods from 28 July 2020 to 22 October 2024; last periods out to March 2034"],
], [0.20, 0.42, 0.38])

H1("2. How the matching was done")
P("Each workbook facility was compared with every E-Banker account on five tests, and the best overall match was taken, with no account used twice. The tests, strongest first:")
BUL(["<b>Principal equals the take-on posting.</b> E-Banker loaded every migrated loan with one \"Opening Account Balance Disbursement amount\" line on 31 July 2024. The workbook's Principal column equals that line to the cent on 77 loans, which is every loan that has such a line.",
     "<b>Carrying amount against E-Banker's balance at 31 October 2024,</b> the workbook's own date. Section 4 reports what this showed.",
     "<b>Approved amount against the sanction amount</b> in the loan master.",
     "<b>Value date against three E-Banker dates:</b> the account opening date, the sanction date, and the first date in the disbursement schedule. The value date equals the first disbursement-schedule date on 82 of the 105 matched facilities and is within a week of it on 85; it equals the sanction date on 78.",
     "<b>The name,</b> after removing words such as Limited and Enterprises."])
TAB(["Result", "Count", "Notes"], [
    ["Matched, high confidence", "104", "Principal ties to the take-on posting, or the name and an amount agree, or the name and a date agree"],
    ["Matched, medium confidence", "1", "Chuma Mkandawire TA Chikoma Estates, workbook #61: name agrees, amounts and dates do not; there is a second Chuma loan (#83) that matches cleanly, so #61 may be an earlier facility"],
    ["Not matched: a loan", "1", "Favoured Farms, workbook #38 (value date 13 July 2022, 10,000,000). E-Banker holds one Favoured Farms account, and it is the 2024 loan (#95). The 2022 loan may have been closed before migration without an account being created"],
    ["Not matched: equity holdings", "3", "MMC Limited, Wi Jays Enterprises, Good Hope. These are investments, not loans, and have no loan account. NASCOMEX, Natures Gift and Small Farm Cities, also equity, do have accounts and were matched"],
    ["Facilities booked after July 2024", "3", "Hortinet Foods (MAIIC), Chimwemwe Mafeni Transport and Agwenda Investment were originated in E-Banker and matched to their post-migration accounts by name, amount and date"],
    ["E-Banker pre-migration accounts with no workbook facility", "8", "All closed. Six are accounts opened in May and June 2024 for customers that also have a live account (NASCOMEX, Natures Gift, Mchinji twice, Infracon twice), which look like set-up duplicates; the other two are A.A Mirza General Traders (nil sanction) and a closed Tapempha Medical Care account"],
], [0.30, 0.08, 0.62])
H("Linking the amortisation blocks")
P("The blocks carry no account numbers either, but every block is titled with the customer's name. Matching the titles to the Loan Book names (with the principal "
  "amount as a tie-break) links 98 of the 100 blocks to a Loan Book row, and through it to an account. 99 of the facilities on the upload sheet therefore have a block.")

H1("3. The workbook: Tamanda's file with our sheets added, linked so it can be audited")
P("We did not build a separate spreadsheet. We took a copy of Tamanda's own workbook of 11 September and added our sheets to it, so that every figure we show "
  "can be traced to the cell it came from. Her two sheets, \"Loan Book\" and \"Amortisation and Repayments\", are unchanged apart from the purple additions "
  "described below.")
TAB(["Sheet", "What it holds", "How it is linked"], [
    ["TOC", "A clickable list of every sheet", "Hyperlinks"],
    ["Instructions", "What to do, in order, and how the links work", "Text"],
    ["Mapping", "One row per facility (109): the proposed account, the E-Banker fields beside it, five tests, and the Y/N column for Tamanda", "Facility terms are formulas into Loan Book; E-Banker fields are lookups into E-Banker master by account; the tests are formulas. Only the proposed account, the confidence and the name score are typed"],
    ["Not matched", "The four facilities with no proposed account, with the nearest candidates", "Values, with a column for Tamanda"],
    ["Accounts without a block", "The eight pre-migration E-Banker accounts that match no facility", "Lookups into E-Banker master"],
    ["Upload summary", "The facilities laid out for loading, one row each: Tamanda's terms beside the E-Banker terms, the block number and a link that opens the block", "Every term is a formula into Loan Book or a lookup into E-Banker master; the account comes from Mapping"],
    ["Blocks", "One row per amortisation block (100): title, principal and rate read from the block, a link that opens it, the facility and account it belongs to, and the statistics we parsed", "Title, principal and rate are formulas into the amortisation sheet; the parsed statistics are values and say so"],
    ["E-Banker master", "The system values for all 184 accounts, with the source files named", "Values from the 7 October extracts"],
], [0.20, 0.42, 0.38])
H("The purple cells on Tamanda's own sheets")
P("So that the mapping is visible where she works, eight columns were added to the right of the last column on \"Loan Book\", and four cells beside the title "
  "of every block on \"Amortisation and Repayments\": the proposed E-Banker account, the facility, the take-on posting of 31 July 2024, the opening interest "
  "charge, E-Banker's balance at 31 October 2024, and the difference between her carrying amount and that balance. They are formatted white on purple, which "
  "means \"added by Dupleix\". They are formulas into the new sheets, and none of her own cells has been edited. If she corrects a figure in her sheets, every "
  "linked cell follows.")
BUL(["Account numbers are text with their fifteen digits and leading zeros. Dates are real dates. Rates are shown as percentages: the workbook mixes fractions (0.3067) with percentages (10), and the formulas put both on the same footing.",
     "Rows with no account (the four not matched) and the equity rows stay on the sheets, flagged by Type and Confidence, so that nothing is silently dropped.",
     "The workbook is a proposal until Tamanda has ticked the mapping. The loader should refuse any row whose confirmation column is not Y."])

H1("4. Does the workbook agree with E-Banker at 31 October 2024?")
P("For the 78 facilities that have both a workbook carrying amount and an E-Banker balance at 31 October 2024:")
TAB(["Test", "Result"], [
    ["Principal equals the take-on posting of 31 July 2024", "77 of 77, to the cent"],
    ["Carrying amount equals E-Banker's balance at 31 October 2024, to the cent", "1 of 78"],
    ["Within 1%", "51 of 78"],
    ["Within 5%", "67 of 78"],
    ["Workbook shows nil, E-Banker shows a small residual", "6 facilities (Zikometso, Kaning'a, Mtengo wa Kumunda, Perisha, Steel Base, Allied Bricks); residuals from -29,678 to 349,021"],
    ["Largest differences", "Kadava Investment -8.65%, Mach Milk -5.86%, Kajikhomele Foundation -5.38%, Nsanama Women Cooperatives -5.31%; in each case E-Banker is the higher figure"],
    ["Workbook rate equals E-Banker's current rate", "79 of 105"],
], [0.50, 0.50])
P("What this means. The principal brought into E-Banker is exactly the workbook's principal, so the two systems started from the same capital. The interest "
  "component is where they part: E-Banker posted an \"opening interest charge\" at take-on and then accrued August to October 2024 at its own rates and day "
  "count, while the workbook accrued the same months by Finance's method. Three months of parallel accrual at thirty percent produce exactly the kind of "
  "one-to-nine-percent gaps seen here. That is an observation, not yet a finding: which balance is right at 31 October 2024 is a question for Finance "
  "(section 6). For the engine it settles the design: use the workbook for the history up to the take-on date, and E-Banker from 31 July 2024 onward.")

H1("5. Readiness, use by use")
TAB(["Use", "Ready?", "What it rests on", "What is still needed"], [
    ["Upload the take-on terms and link each facility to its account", "Yes, pending the tick", "105 of 109 mapped; principal ties on all 77 migrated loans; dates tie on 82 to 85", "Tamanda's confirmation of the mapping and the five rows in section 6"],
    ["Opening balance at migration", "Yes", "E-Banker's take-on posting, which equals the workbook principal", "Nothing"],
    ["Pre-migration cash flows (drawdowns, repayments, rate steps) for the EIR", "Partly", "98 blocks linked by name; each block carries the period table with amounts repaid and dated rate steps", "Parsing of the repayment and rate-step columns block by block, which we can do; Finance's confirmation of the four restructured blocks"],
    ["Effective interest rate at origination for the take-on loans", "No", "The workbook carries no fees", "The origination fee per take-on loan, from the 2024 and 2025 EIR assessments and Finance's records"],
    ["Carrying amount at 31 October 2024 and the months to July 2024", "Partly", "Workbook and E-Banker agree within 1% on 51 of 78", "Finance's view of which figure is right where they differ by more than 1%"],
    ["Equity holdings", "Not applicable", "Six rows are investments, not loans", "Exclude from the EIR engine; three have no loan account at all"],
], [0.28, 0.12, 0.32, 0.28])
BOX(["<b>Verdict.</b> The mapping is ready to be confirmed, and the upload sheet is ready to load once it is. The take-on principal can be relied on today. The "
     "pre-migration schedule can be used for cash-flow history after its blocks are parsed; it cannot yet be used for the EIR at origination because it carries "
     "no fees."], GREEN)

H1("6. What we ask Tamanda to confirm")
BUL(["<b>The mapping as a whole:</b> tick Y against each of the 105 proposed accounts on the \"Mapping\" sheet, or give the right account number where N. The purple columns on her own Loan Book show the same proposals in place.",
     "<b>Favoured Farms, workbook #38</b> (2022, 10,000,000): is this the same customer as the 2024 loan on account 000104450000037, and was the 2022 loan closed before the migration without an E-Banker account?",
     "<b>Chuma Mkandawire TA Chikoma Estates, workbook #61</b> (February 2023): we propose account 000104450000082 on the name alone; #83 \"Chuma Chikomanya\" (December 2023) matches account 000104450000060 cleanly. Are these two facilities for one customer, and is #61 on the right account?",
     "<b>The three equity holdings without an account</b> (MMC Limited, Wi Jays Enterprises, Good Hope): confirm they are investments outside the loan book and should be left out.",
     "<b>The eight closed E-Banker accounts with no workbook facility:</b> confirm that the six opened in May and June 2024 are set-up duplicates, and that A.A Mirza and the closed Tapempha account need no history.",
     "<b>Which balance is right at 31 October 2024</b> on the 27 facilities where the workbook and E-Banker differ by more than 1%, and in particular the four largest (Kadava, Mach Milk, Kajikhomele, Nsanama).",
     "<b>The origination fee</b> for each take-on loan: amount, type and date, as deducted at the original drawdown.",
     "<b>The date convention:</b> the workbook's value date equals E-Banker's first disbursement-schedule date on 82 facilities. Please confirm that the value date is the first drawdown date, not the approval date."], numbered=True)
P("Each of these is a tick or a one-line answer on the workbook. Nothing has to be built, and because every figure is linked to its source cell, each answer can be checked against the schedule it came from.")
s.append(Spacer(1, 6))
s.append(Paragraph("Edward Mazibuko and Wadzanai Rombe, Dupleix Institute. Figures are from the workbook as received on 11 September 2026 and the E-Banker extracts of 7 October 2026; the scripts are in the Build files folder.", st["meta"]))

def footer(c, d):
    c.saveState(); c.setStrokeColor(LINE); c.setLineWidth(0.5); c.line(18 * mm, 14 * mm, A4[0] - 18 * mm, 14 * mm)
    c.setFont("Seg", 8); c.setFillColor(GREY); c.drawString(18 * mm, 9.5 * mm, "The take-on schedule: readiness and the proposed mapping  ·  Dupleix Institute  ·  7 October 2026")
    c.drawRightString(A4[0] - 18 * mm, 9.5 * mm, f"Page {d.page}"); c.restoreState()
OUT = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC\Take-on schedule readiness and mapping - 7 Oct 2026.pdf"
doc = BaseDocTemplate(OUT, pagesize=A4, leftMargin=18 * mm, rightMargin=18 * mm, topMargin=16 * mm, bottomMargin=20 * mm, title="The take-on schedule: readiness and the proposed mapping", author="Dupleix Institute")
doc.addPageTemplates([PageTemplate(id="p", frames=[Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id="f", leftPadding=0, rightPadding=0, topPadding=0, bottomPadding=0)], onPage=footer)])
doc.build(s); print("written", OUT)
