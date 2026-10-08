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
s.append(Paragraph("Follow-up extracts, pack 2: what each group is for", st["title"]))
s.append(Paragraph("A note for Barry Makumba, Dr Thom and Tamanda, from Dupleix Institute  ·  8 October 2026  ·  goes with the file FOLLOW-UP extracts for Barry - pack 2 - 8 Oct 2026 (.sql, .txt, .pdf)", st["meta"]))

H("1. Thank you, and where we are")
P("The 7 October pack answered more than we asked. Every reconciliation we could run either ties to the cent or has a named cause, the specification for the engine is written, and the data you sent is committed as the bootstrap of the system so that any server can be built from it. This second pack has four groups. None is urgent in the way the first was; the September month-end group is the one we would run first, because it is the monthly routine from now on.")

H("2. The September month-end pack (RUN 1 to 3)")
P("From now on, each month-end needs three files from the database and the trial balance from Finance. The queries take only what is new since 7 October, by each table's own id, and re-pull August so that a posting back-dated into August after the 7th is seen. The ids in the queries (425764 for the ledger, 808196 for the loan-book runs) are the last ones we hold; next month we will send the new ids. Finance's file is Trial Balance_30 September 2026.xls, the same shape as the others.")
TAB(["File", "What it is"], [
    ["M09_01_ledger_since_august.csv", "Ledger rows after id 425764, plus every August row"],
    ["M09_02_balance_history_aug_sep.csv", "Month-end balance rows for 31 August and 30 September"],
    ["M09_03_loan_book_runs_sep.csv", "The Loan Book Report run(s) for 30 September, and any run with a later id"],
    ["Trial Balance_30 September 2026.xls", "From Finance, not a query"],
], [0.4, 0.6])

H("3. The interest arithmetic and the collateral (RUN 4 to 7)")
P("<b>P1_05b, INTEREST_PRODUCT.</b> The interest summary you sent twice (P1_05) holds the latest month only. INTEREST_PRODUCT carries the balance-times-days, the days, the rate and the amount for each calculation; if it keeps history, it is the exact arithmetic behind every interest posting since go-live and our rebuild becomes an exact reproduction rather than a 99.9 percent one.")
P("<b>P2_11 and P2_12, collateral and guarantors.</b> Nothing on security has been extracted yet; the collateral screens in the system are fed by hand. CUST_SECURITY_DETAILS has 1,197 rows and GUARANTER_MASTER 3,368; the queries take those for our customers. They feed the loss-given-default side of the expected credit loss.")
P("<b>P2_13, LOS_REQUEST_DETAILS.</b> A long shot with a large prize. The origination system's request and response payloads (202,184 rows) may carry the fees and the rate as captured at approval. If they do, part of what we have asked Tamanda to type into the take-on workbook can come from the system instead. The query searches the text of each payload for our account numbers, so it runs for a few minutes; that is normal.")

H("4. The year-end adjustments (RUN 8)")
P("Sent on 8 October as its own note (Query GL_03) and repeated here for completeness. It pulls both legs of the two interest batches of 31 December 2025 so that the account that took the other side of the 22 debits (96,390,096.16) is seen. The 6 credits are proven to have gone through interest income 4215 and 4216; the debits did not, and the trial balance cannot say where they went.")

H("5. The Mega Farm schemes (RUN 9 to 11)")
P("Schemes 96 to 103 (fertilizer, irrigation, seed, CAPEX, working capital, pesticides, equipment) hold about 7,000 accounts and were outside the 18 schemes of the first pack. From the trial balances alone we can see that they are three to four times the size of the MAIIC book, that interest accrues to a separate receivable rather than to the loan, that the balances do not move for half the year and are repaid through ADMARC in the harvest months, and that 350 million left the seed interest receivable in June 2026 without passing through income. Whether these schemes belong inside the engine is the largest open decision for Dr Thom, and it should be taken on the postings, not on totals. The queries are the same shape as the first pack's, for those schemes.")
P("MF_03, the ledger, will be the largest file in either pack. If SQL Developer struggles with it, export it one scheme at a time by replacing the IN list with a single id, and name the files MF_03_96.csv, MF_03_98.csv and so on.")

H("6. Two questions for Finance, with no query attached")
P("On the seed interest receivable (GL 1062): what were the debits of about 320 million in 2025 that did not pass through seed-loan interest income (the receivable rose by 700 million while income was 379 million); and what was the credit of about 350 million in June 2026 (1,107 million fell to 758 million) with no matching move in income? Both decide how the receivable is treated before any expected credit loss is calculated on it.")
s.append(Spacer(1, 6))
s.append(Paragraph("Edward Mazibuko and Wadzanai Rombe, Dupleix Institute. The data dictionary results of 6 October were used to choose every table named here; the scope scheme ids are those of the first pack.", st["meta"]))

def footer(c, d):
    c.saveState(); c.setStrokeColor(LINE); c.setLineWidth(0.5); c.line(18 * mm, 14 * mm, A4[0] - 18 * mm, 14 * mm)
    c.setFont("Seg", 8); c.setFillColor(GREY); c.drawString(18 * mm, 9.5 * mm, "Follow-up extracts, pack 2: what each group is for  ·  Dupleix Institute  ·  8 October 2026")
    c.drawRightString(A4[0] - 18 * mm, 9.5 * mm, f"Page {d.page}"); c.restoreState()
Q = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC"
OUT = Q + r"\Note to Barry - follow-up extracts pack 2, what each group is for - 8 Oct 2026.pdf"
doc = BaseDocTemplate(OUT, pagesize=A4, leftMargin=18 * mm, rightMargin=18 * mm, topMargin=16 * mm, bottomMargin=20 * mm, title="Follow-up extracts, pack 2: what each group is for", author="Dupleix Institute")
doc.addPageTemplates([PageTemplate(id="p", frames=[Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id="f", leftPadding=0, rightPadding=0, topPadding=0, bottomPadding=0)], onPage=footer)])
doc.build(s); print("written", OUT)
