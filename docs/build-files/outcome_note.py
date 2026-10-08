"""Outcome of the GL and Zaithwa Farms queries: what they showed and what to fix (PDF for Barry and Finance)."""
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.lib import colors
from reportlab.lib.styles import ParagraphStyle
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import BaseDocTemplate, PageTemplate, Frame, Paragraph, Spacer, Table, TableStyle, Image, ListFlowable, ListItem

F = r"C:\Windows\Fonts"
pdfmetrics.registerFont(TTFont("Seg", F + r"\segoeui.ttf")); pdfmetrics.registerFont(TTFont("Seg-B", F + r"\segoeuib.ttf"))
pdfmetrics.registerFont(TTFont("Seg-I", F + r"\segoeuii.ttf")); pdfmetrics.registerFont(TTFont("Seg-BI", F + r"\segoeuiz.ttf"))
pdfmetrics.registerFontFamily("Seg", normal="Seg", bold="Seg-B", italic="Seg-I", boldItalic="Seg-BI")
NAVY = colors.HexColor("#1B2A41"); ORANGE = colors.HexColor("#E07B00"); GREY = colors.HexColor("#5B6470"); LIGHT = colors.HexColor("#F4F6F9"); LINE = colors.HexColor("#C9D1DB"); GREEN = colors.HexColor("#EAF6EC")
st = {"title": ParagraphStyle("t", fontName="Seg-B", fontSize=20, leading=25, textColor=NAVY, spaceAfter=4),
      "meta": ParagraphStyle("m", fontName="Seg", fontSize=9.5, leading=13, textColor=GREY, spaceAfter=10),
      "h": ParagraphStyle("h", fontName="Seg-B", fontSize=12, leading=15, textColor=ORANGE, spaceBefore=10, spaceAfter=4, keepWithNext=1),
      "p": ParagraphStyle("p", fontName="Seg", fontSize=10.5, leading=15, spaceAfter=7), "li": ParagraphStyle("li", fontName="Seg", fontSize=10.5, leading=15),
      "cell": ParagraphStyle("c", fontName="Seg", fontSize=9.2, leading=12), "head": ParagraphStyle("hd", fontName="Seg-B", fontSize=9.2, leading=12, textColor=colors.white),
      "box": ParagraphStyle("b", fontName="Seg", fontSize=10.5, leading=15, spaceAfter=3)}
W = A4[0] - 36 * mm
s = []
def P(t): s.append(Paragraph(t, st["p"]))
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
def BOX(paras, colour=GREEN):
    t = Table([[[Paragraph(x, st["box"]) for x in paras]]], colWidths=[W])
    t.setStyle(TableStyle([("BACKGROUND", (0, 0), (-1, -1), colour), ("LINEBEFORE", (0, 0), (0, -1), 3, ORANGE), ("LEFTPADDING", (0, 0), (-1, -1), 10), ("RIGHTPADDING", (0, 0), (-1, -1), 10), ("TOPPADDING", (0, 0), (-1, -1), 8), ("BOTTOMPADDING", (0, 0), (-1, -1), 6)]))
    s.append(t); s.append(Spacer(1, 8))

LOGO = r"C:\Users\wadza\OneDrive\2026\Tenders\PFA\Excel & Data Analysis Training\Basic Excel Course\3 Source Files\img\dupleix-logo.png"
try:
    im = Image(LOGO); r = im.imageHeight / im.imageWidth; im.drawWidth = 36 * mm; im.drawHeight = 36 * mm * r; im.hAlign = "LEFT"; s.append(im); s.append(Spacer(1, 8))
except Exception: pass
s.append(Paragraph("What the GL and Zaithwa Farms queries showed, and what to fix", st["title"]))
s.append(Paragraph("For Barry Makumba and Finance, from Dupleix Institute  ·  7 October 2026  ·  the answers to this morning's two notes", st["meta"]))
BOX(["<b>In short.</b> Both differences are now explained, neither is a gap in the data, and each needs a small correction on MAIIC's side. The two FInES GL "
     "differences come from the GL opening balances keyed on 1 January 2025. The Zaithwa Farms difference is a fault in the month-end balance table from "
     "May 2025; the ledger and the loan book are right."])

H("1. The two FInES GL differences")
P("<b>What we asked.</b> Why the trial balance for 1050201 (FInES Agricultural) is 400,000.00 higher than the sum of its loan accounts in every month, and "
  "1050202 (FInES Industrial) 1,000,000.00 lower, while the other three loan GLs agree to the cent.")
P("<b>What the queries showed.</b> Query GL_01 returned nothing: there is no posting on either GL code that lacks an account number or belongs to an account "
  "outside the 184. So the ledger postings are complete. Query GL_02 returned the GL opening balances E-Banker holds for each financial year. They are keyed "
  "by hand (the 2025 openings were entered on 30 July 2025, the 2026 openings on 12 January 2026), and this is what they show against the accounts' own "
  "balance at the previous year-end:")
TAB(["GL", "Year", "Keyed opening balance", "Accounts' balance at previous year-end", "Difference"], [
    ["1050101 MAIIC Agricultural", "2025", "1,695,546,952.15", "1,695,546,952.15", "nil"],
    ["", "2026", "1,368,737,810.68", "1,368,737,810.68", "nil"],
    ["1050102 MAIIC Industrial", "2025", "1,928,413,070.11", "1,928,413,070.11", "nil"],
    ["", "2026", "6,434,045,052.56", "6,434,045,052.56", "nil"],
    ["1050201 FInES Agricultural", "2025", "1,900,890,656.18", "1,900,490,656.18", "+400,000.00"],
    ["", "2026", "1,402,393,139.46", "1,401,993,139.46", "+400,000.00"],
    ["1050202 FInES Industrial", "2025", "2,663,537,112.44", "2,664,537,112.44", "-1,000,000.00"],
    ["", "2026", "3,808,288,656.15", "3,809,288,656.15", "-1,000,000.00"],
    ["1050401 MAIIC Term Loan", "2026", "1,827,148,120.69", "1,827,148,120.69", "nil"],
], [0.26, 0.08, 0.22, 0.26, 0.18])
P("<b>What it means.</b> For three GLs the person keying the opening balance used the accounts' total, and the GL has agreed with the accounts ever since. For "
  "the two FInES GLs the 2025 opening was keyed 400,000 above and 1,000,000 below the accounts, and the 2026 opening was then keyed from the trial balance "
  "closing figure, which carried the same differences forward. The GL and the accounts have disagreed by exactly these amounts since 1 January 2025.")
P("<b>What to do.</b> Two questions for Finance, then a correction:")
BUL(["Which figure agreed to the 2024 audited accounts at 31 December 2024: the keyed GL openings (FInES Agricultural 1,900,890,656.18; FInES Industrial 2,663,537,112.44) or the accounts' totals (1,900,490,656.18 and 2,664,537,112.44)? The 2024 audited loan book will say.",
     "If the accounts are right, adjust the 2025 and 2026 GL opening balances for the two codes (1050201 down by 400,000.00; 1050202 up by 1,000,000.00) so that the trial balance agrees with the loan accounts. If the audited figures are the keyed ones, the difference belongs to a loan account and we need to know which.",
     "Either way, the engine reconciles to the loan accounts, and the 600,000 net becomes one explained line in the reconciliation until the opening balances are corrected."], numbered=True)

H("2. Zaithwa Farms, account 000104450000015")
P("<b>What we asked.</b> Why the month-end balance table shows the customer 1,827,633.62 better off than the ledger from May 2025 onward, with no posting of that amount.")
P("<b>What the queries showed.</b>")
BUL(["ZF_01, every posting on the account: 24 rows, all of them already in our extract. One is a deleted nil-value row of 3 March 2025. The ledger closes at 1,225,683.03 in the customer's favour: the loan was taken on at 10,000,000 plus 1,104,919.31 opening interest, interest ran at 10%, and the customer repaid 13,094,782.32 net (16,094,782.32 was posted as receipts, of which one 3,000,000 receipt in February had been posted twice and was reversed once).",
     "ZF_02, any posting of 1,827,633.62 on any account: none.",
     "ZF_03, the balance table rows: 27 rows, and 30 April 2025 appears twice (row ids 256080 and 317562, the second added later). The May 2025 row then shows receipts of nil and a balance of -27,303.21, which is just that month's interest. The ledger says May started at -3,827,633.62 and took two receipts of 1,000,000. The May row in the balance table started from nil and missed the receipts, and every later month-end row inherits the error."])
TAB(["Month-end", "Balance table", "Ledger (the postings added up)", "Loan book carrying amount"], [
    ["30 April 2025", "-3,827,633.62 (twice)", "-3,827,633.62", "agrees with the ledger"],
    ["31 May 2025", "-27,303.21", "-1,854,936.83", "agrees with the ledger"],
    ["31 August 2025 onward", "+3,053,316.65", "+1,225,683.03", "+1,225,683.03"],
], [0.22, 0.24, 0.28, 0.26])
P("<b>What it means.</b> The ledger is right, and the stored loan book report (which we tied to the ledger on every account and every month) is right. The "
  "balance table is wrong for this one account from May 2025, and it is the only account with a duplicate month-end row. Whatever created the second "
  "30 April row also broke the May roll-forward.")
P("<b>What to do.</b>")
BUL(["Ask the vendor to remove the duplicate 30 April 2025 row and rebuild the account's month-end rows from May 2025, so that the balance table shows 1,225,683.03 in the customer's favour, not 3,053,316.65.",
     "Check any statement or refund calculation sent to Zaithwa Farms since May 2025 against the ledger figure.",
     "No change is needed in our engine: it reads the ledger."], numbered=True)

H("3. What this does to the readiness picture")
P("Every reconciliation we reported this morning now either ties to the cent or has a named cause and a named fix. Of the items that were open, these two are "
  "closed on the data side; the remaining items are the ones only people can answer: the 2024 audited figure for the two FInES GLs, the meaning of status "
  "code H, the December 2025 interest adjustments, the twelve offer letters, and Tamanda's tick on the take-on mapping.")
s.append(Spacer(1, 6))
s.append(Paragraph("Edward Mazibuko and Wadzanai Rombe, Dupleix Institute. Source files: GL_01, GL_02, ZF_01, ZF_02, ZF_03 as received on 7 October 2026; the scripts are in the Build files folder.", st["meta"]))

def footer(c, d):
    c.saveState(); c.setStrokeColor(LINE); c.setLineWidth(0.5); c.line(18 * mm, 14 * mm, A4[0] - 18 * mm, 14 * mm)
    c.setFont("Seg", 8); c.setFillColor(GREY); c.drawString(18 * mm, 9.5 * mm, "Outcome of the GL and Zaithwa Farms queries  ·  Dupleix Institute  ·  7 October 2026")
    c.drawRightString(A4[0] - 18 * mm, 9.5 * mm, f"Page {d.page}"); c.restoreState()
OUT = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC\Outcome - GL differences and Zaithwa Farms explained - 7 Oct 2026.pdf"
doc = BaseDocTemplate(OUT, pagesize=A4, leftMargin=18 * mm, rightMargin=18 * mm, topMargin=16 * mm, bottomMargin=20 * mm, title="Outcome of the GL and Zaithwa Farms queries", author="Dupleix Institute")
doc.addPageTemplates([PageTemplate(id="p", frames=[Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id="f", leftPadding=0, rightPadding=0, topPadding=0, bottomPadding=0)], onPage=footer)])
doc.build(s); print("written", OUT)
