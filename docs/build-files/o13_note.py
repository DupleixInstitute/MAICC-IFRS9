"""Plain-language note to Barry on the two FInES GL differences, with the queries to run."""
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.lib import colors
from reportlab.lib.styles import ParagraphStyle
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import BaseDocTemplate, PageTemplate, Frame, Paragraph, Spacer, Table, TableStyle, Preformatted, Image

F = r"C:\Windows\Fonts"
pdfmetrics.registerFont(TTFont("Seg", F + r"\segoeui.ttf")); pdfmetrics.registerFont(TTFont("Seg-B", F + r"\segoeuib.ttf"))
pdfmetrics.registerFont(TTFont("Seg-I", F + r"\segoeuii.ttf")); pdfmetrics.registerFont(TTFont("Seg-BI", F + r"\segoeuiz.ttf"))
pdfmetrics.registerFontFamily("Seg", normal="Seg", bold="Seg-B", italic="Seg-I", boldItalic="Seg-BI")
pdfmetrics.registerFont(TTFont("Mono", F + r"\consola.ttf"))
NAVY = colors.HexColor("#1B2A41"); ORANGE = colors.HexColor("#E07B00"); GREY = colors.HexColor("#5B6470"); LIGHT = colors.HexColor("#F4F6F9"); LINE = colors.HexColor("#C9D1DB"); AMBER = colors.HexColor("#FFF4E0")
st = {"title": ParagraphStyle("t", fontName="Seg-B", fontSize=20, leading=25, textColor=NAVY, spaceAfter=4),
      "meta": ParagraphStyle("m", fontName="Seg", fontSize=9.5, leading=13, textColor=GREY, spaceAfter=10),
      "h": ParagraphStyle("h", fontName="Seg-B", fontSize=12, leading=15, textColor=ORANGE, spaceBefore=10, spaceAfter=4, keepWithNext=1),
      "p": ParagraphStyle("p", fontName="Seg", fontSize=10.5, leading=15, spaceAfter=7),
      "cell": ParagraphStyle("c", fontName="Seg", fontSize=9.2, leading=12), "head": ParagraphStyle("hd", fontName="Seg-B", fontSize=9.2, leading=12, textColor=colors.white),
      "mono": ParagraphStyle("mo", fontName="Mono", fontSize=8.3, leading=10.6)}
W = A4[0] - 36 * mm
s = []
def P(t): s.append(Paragraph(t, st["p"]))
def H(t): s.append(Paragraph(t, st["h"]))
def TAB(header, rows, widths):
    data = [[Paragraph(h, st["head"]) for h in header]] + [[Paragraph(str(c), st["cell"]) for c in r] for r in rows]
    t = Table(data, colWidths=[W * x for x in widths], repeatRows=1)
    sty = [("BACKGROUND", (0, 0), (-1, 0), NAVY), ("VALIGN", (0, 0), (-1, -1), "TOP"), ("LEFTPADDING", (0, 0), (-1, -1), 5), ("RIGHTPADDING", (0, 0), (-1, -1), 5),
           ("TOPPADDING", (0, 0), (-1, -1), 4), ("BOTTOMPADDING", (0, 0), (-1, -1), 5), ("LINEBELOW", (0, 0), (-1, -1), 0.4, LINE), ("BOX", (0, 0), (-1, -1), 0.6, LINE)]
    for i in range(2, len(data), 2): sty.append(("BACKGROUND", (0, i), (-1, i), LIGHT))
    t.setStyle(TableStyle(sty)); s.append(t); s.append(Spacer(1, 8))
def CODE(t):
    tb = Table([[Preformatted(t, st["mono"])]], colWidths=[W])
    tb.setStyle(TableStyle([("BACKGROUND", (0, 0), (-1, -1), LIGHT), ("BOX", (0, 0), (-1, -1), 0.5, LINE), ("LEFTPADDING", (0, 0), (-1, -1), 8), ("RIGHTPADDING", (0, 0), (-1, -1), 8), ("TOPPADDING", (0, 0), (-1, -1), 6), ("BOTTOMPADDING", (0, 0), (-1, -1), 6)]))
    s.append(tb); s.append(Spacer(1, 8))

LOGO = r"C:\Users\wadza\OneDrive\2026\Tenders\PFA\Excel & Data Analysis Training\Basic Excel Course\3 Source Files\img\dupleix-logo.png"
try:
    im = Image(LOGO); r = im.imageHeight / im.imageWidth; im.drawWidth = 36 * mm; im.drawHeight = 36 * mm * r; im.hAlign = "LEFT"; s.append(im); s.append(Spacer(1, 8))
except Exception: pass
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
