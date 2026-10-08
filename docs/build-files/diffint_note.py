"""Plain-language note to Barry (and Finance) on the 31 December 2025 interest-difference postings, with the queries."""
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.lib import colors
from reportlab.lib.styles import ParagraphStyle
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import BaseDocTemplate, PageTemplate, Frame, Paragraph, Spacer, Table, TableStyle, Preformatted, Image, ListFlowable, ListItem

F = r"C:\Windows\Fonts"
pdfmetrics.registerFont(TTFont("Seg", F + r"\segoeui.ttf")); pdfmetrics.registerFont(TTFont("Seg-B", F + r"\segoeuib.ttf"))
pdfmetrics.registerFont(TTFont("Seg-I", F + r"\segoeuii.ttf")); pdfmetrics.registerFont(TTFont("Seg-BI", F + r"\segoeuiz.ttf"))
pdfmetrics.registerFontFamily("Seg", normal="Seg", bold="Seg-B", italic="Seg-I", boldItalic="Seg-BI")
pdfmetrics.registerFont(TTFont("Mono", F + r"\consola.ttf"))
NAVY = colors.HexColor("#1B2A41"); ORANGE = colors.HexColor("#E07B00"); GREY = colors.HexColor("#5B6470"); LIGHT = colors.HexColor("#F4F6F9"); LINE = colors.HexColor("#C9D1DB"); AMBER = colors.HexColor("#FFF4E0")
st = {"title": ParagraphStyle("t", fontName="Seg-B", fontSize=20, leading=25, textColor=NAVY, spaceAfter=4),
      "meta": ParagraphStyle("m", fontName="Seg", fontSize=9.5, leading=13, textColor=GREY, spaceAfter=10),
      "h": ParagraphStyle("h", fontName="Seg-B", fontSize=12, leading=15, textColor=ORANGE, spaceBefore=10, spaceAfter=4, keepWithNext=1),
      "p": ParagraphStyle("p", fontName="Seg", fontSize=10.5, leading=15, spaceAfter=7), "li": ParagraphStyle("li", fontName="Seg", fontSize=10.5, leading=15),
      "cell": ParagraphStyle("c", fontName="Seg", fontSize=9.2, leading=12), "head": ParagraphStyle("hd", fontName="Seg-B", fontSize=9.2, leading=12, textColor=colors.white),
      "mono": ParagraphStyle("mo", fontName="Mono", fontSize=8.3, leading=10.6), "box": ParagraphStyle("b", fontName="Seg", fontSize=10.5, leading=15, spaceAfter=3)}
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
def CODE(t):
    tb = Table([[Preformatted(t, st["mono"])]], colWidths=[W])
    tb.setStyle(TableStyle([("BACKGROUND", (0, 0), (-1, -1), LIGHT), ("BOX", (0, 0), (-1, -1), 0.5, LINE), ("LEFTPADDING", (0, 0), (-1, -1), 8), ("RIGHTPADDING", (0, 0), (-1, -1), 8), ("TOPPADDING", (0, 0), (-1, -1), 6), ("BOTTOMPADDING", (0, 0), (-1, -1), 6)]))
    s.append(tb); s.append(Spacer(1, 8))
def BOX(paras):
    t = Table([[[Paragraph(x, st["box"]) for x in paras]]], colWidths=[W])
    t.setStyle(TableStyle([("BACKGROUND", (0, 0), (-1, -1), AMBER), ("LINEBEFORE", (0, 0), (0, -1), 3, ORANGE), ("LEFTPADDING", (0, 0), (-1, -1), 10), ("RIGHTPADDING", (0, 0), (-1, -1), 10), ("TOPPADDING", (0, 0), (-1, -1), 8), ("BOTTOMPADDING", (0, 0), (-1, -1), 6)]))
    s.append(t); s.append(Spacer(1, 8))

LOGO = r"C:\Users\wadza\OneDrive\2026\Tenders\PFA\Excel & Data Analysis Training\Basic Excel Course\3 Source Files\img\dupleix-logo.png"
try:
    im = Image(LOGO); r = im.imageHeight / im.imageWidth; im.drawWidth = 36 * mm; im.drawHeight = 36 * mm * r; im.hAlign = "LEFT"; s.append(im); s.append(Spacer(1, 8))
except Exception: pass
s.append(Paragraph("The 28 interest adjustments of 31 December 2025", st["title"]))
s.append(Paragraph("A note for Barry Makumba, with questions for Finance, from Dupleix Institute  ·  7 October 2026", st["meta"]))

H("1. What we found")
P("Every month, E-Banker charges each loan one line of interest, with a narration such as \"IntDR Fm 01-NOV-25 To 30-NOV-25 @ 31.3%\". We can rebuild those "
  "lines from the daily balance and the rate, and they come out right. On 31 December 2025 the system posted 28 lines of a different kind:")
TAB(["Kind", "Lines", "Narration", "Total"], [
    ["Debits (type 303)", "22", "To Trf Diff Int. Debit From 01/01/2025 To 31/12/2025 By ROI 31.3 %", "MWK 96,390,096.16"],
    ["Credits (type 120)", "6", "By Trf Diff Int. Credit From 01/01/2025 To 31/12/2025 By ROI", "MWK 85,166,683.31"],
    ["Net", "28", "", "MWK 11,223,412.85 added to interest income and to customers' balances"],
], [0.18, 0.08, 0.50, 0.24])
P("The narration tells us what they are: the interest for the whole of 2025 was recalculated at one rate (\"ROI\", the rate of interest the loan carried on "
  "31 December), and the difference between that and what had been charged month by month was posted in one line. No account got both a debit and a credit.")

H("2. Why it needs explaining")
P("For most of the 28 accounts the adjustment is small, under one percent of the year's interest, which is what a recalculation at a slightly higher closing "
  "rate would give. For eight accounts it is not small, and in those cases two different things seem to be mixed together:")
TAB(["Account", "Customer", "Adjustment", "As a share of the interest charged in 2025", "Rates charged during 2025"], [
    ["000104420000043", "The Food Empire", "debit 27,841,180.80", "79.8%", "33.3% to 34.2%"],
    ["000104430000087", "JAT Group", "debit 8,596,496.57", "48.3%", "33.0% to 33.3%"],
    ["000104420000064", "Namalowe Eco Farm", "debit 7,919,807.58", "39.4%", "7.9% to 33.3%"],
    ["000104430000064", "Agwenda Investments", "debit 2,903,082.81", "23.7%", "25.0% to 33.0%"],
    ["000104430000070", "Ebenezer Midian (older account)", "debit 19,186,605.60", "16.2%", "25.0% to 31.3%"],
    ["000104430000069", "Microloan Foundation", "debit 14,204,999.05", "13.7%", "25.0% to 29.8%"],
    ["000104430000068", "Nyamunyamu Processors", "credit 69,330,809.54", "52.6% back", "33.2% to 85.2%"],
    ["000104430000002", "Edge View Academy", "credit 10,457,152.33", "8.9% back", "31.1% to 34.2%"],
], [0.16, 0.24, 0.20, 0.20, 0.20])
BUL(["<b>Corrections of mistakes.</b> Nyamunyamu was charged one month at 85.2%; its credit gives the overcharge back. Namalowe was charged one month at 7.9%; its debit makes up the undercharge. These put right errors made during the year.",
     "<b>Something else.</b> The Food Empire was charged between 33.3% and 34.2% all year, and its closing rate is 33.3%. Recalculating the year at 33.3% cannot add 80% to its interest. Either the recalculation used a different balance from the one interest was charged on during the year, or it did something the narration does not describe. The same applies to JAT Group."])
P("We cannot tell these apart from the ledger alone. We can from the system's own working tables, which is what the queries fetch.")

H("3. Why it matters to the work")
BUL(["<b>The December reconciliation.</b> In every other month the ledger's interest equals balance times rate times days, and our engine will reconcile to it. December 2025 holds 11.2 million net that belongs to the whole year. Unless it is shown as its own line, December looks wrong when it is not.",
     "<b>The 2025 income figure.</b> The general ledger's interest income for 2025 includes these 28 lines. The engine's 2025 figure has to be compared with it on the same basis.",
     "<b>Customers' balances.</b> The lines were posted to the loan accounts, so from 1 January 2026 the affected customers owe more or less than their monthly interest alone would give. The balance history and the loan book carry the adjusted figure, and so must the engine.",
     "<b>The accounting.</b> A correction of an error belongs in 2025 interest. A new rate applied backwards to January is a different event: for a floating-rate loan the rate normally changes from the date it changes, not from the start of the year. Which of the two each line is decides how the engine treats it."])

H("4. The two queries, and what each one is for")
P("The data dictionary you sent on 6 October lists two tables that we believe are the routine's working tables: DIFF_INTEREST_SUMMARY, with 29 rows (one "
  "more than the 28 postings), and DIFF_INTEREST_PRODUCT, with 7,521 rows. Both are read with plain SELECTs; nothing is created, changed or deleted. Run "
  "the two session settings first, as before, and export each result as CSV under the name shown. Do not open the files in Excel before sending.")
P("<b>Query 1: the summary, one row per adjustment.</b> Save as DI_01_summary.csv.")
CODE("""SELECT * FROM diff_interest_summary
ORDER  BY new_ac_number, transaction_date, interest_mst_id;""")
P("What it holds (the dictionary gives its columns): for each account, the period recalculated (INTEREST_FROM_DATE to INTEREST_UPTO_DATE), the rate used "
  "(INTEREST_RATE), the balance-times-days it was applied to (PRINCIPAL_PRODUCT), the interest the routine computed for the year (INTEREST_AMOUNT and "
  "TOTAL_INTEREST), the interest already charged (PREVIOUS_INTEREST), and the difference it posted (DIFF_INTEREST). Laid next to the 28 ledger lines, this "
  "shows at once whether each adjustment came from a different rate, a different balance, or a different period than the monthly charges, and ties each "
  "one to the cent. That is the whole of the explanation for the twenty small ones, and the first half of it for the eight large ones.")
P("<b>Query 2: the detail under the summary.</b> Save as DI_02_product.csv.")
CODE("""SELECT * FROM diff_interest_product
ORDER  BY new_ac_number, product_from_date, interest_mst_id;""")
P("\"Product\" is E-Banker's word for balance multiplied by days, the quantity interest is worked out on. This table holds, for each account, one row per "
  "rate period of 2025 (PRODUCT_FROM_DATE to PRODUCT_UPTO_DATE) with the rate (INTEREST_RATE), the days (NO_OF_DAYS) and the product (PRINCIPAL_PRODUCT) the "
  "routine used. It resolves the eight large cases: set against the ledger's monthly "
  "charges, it shows the exact month and the exact balance where the routine's view of the year differs from what was charged. For The Food Empire it will "
  "show whether the routine used a bigger balance than the monthly interest was charged on, which is the only arithmetic that can produce an 80% addition.")
P("Both tables are small for our purpose. If the second is large because it covers every scheme, this version limits it to our 184 accounts; use it only "
  "if the plain version is slow:")
CODE("""SELECT * FROM diff_interest_product
WHERE  new_ac_number IN (SELECT a.new_ac_number FROM acmaster a
                         WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,
                                                   140,141,142,143,144,145))
ORDER  BY new_ac_number, product_from_date, interest_mst_id;""")

H("5. If something goes wrong")
TAB(["What you see", "What it means", "What to do"], [
    ["ORA-00942: table or view does not exist", "The login cannot see the table, or it sits in another schema", "Tell us; the dictionary listed it under VGCBS, so the owner may need to grant SELECT, or the name can be prefixed VGCBS.diff_interest_summary"],
    ["ORA-00904: invalid identifier", "A column name differs from the dictionary listing", "Run SELECT * with no ORDER BY or WHERE clause; it needs no column names"],
    ["The result is empty", "The tables were cleared after the run, or the routine wrote elsewhere", "Tell us; we will then ask the vendor where the year-end run keeps its working"],
    ["The result has far more than 29 rows in the summary", "The table holds more than one run (for example 2024 as well)", "Send it all; a run date or year column will separate them"],
], [0.30, 0.30, 0.40])
P("The column names in the queries come from the dictionary listing you sent on 6 October, so they should run as written. A screenshot of any error is "
  "enough for us to fix the query.")

H("6. The questions for Finance, once the tables are in")
P("The tables give the arithmetic. These questions give the intent, and they are for Finance rather than ICT:")
BUL(["Was the 31 December 2025 run E-Banker's \"interest difference\" routine? Who ran it, and is it part of every year-end?",
     "For the eight large cases, what was being corrected: a wrong rate in an earlier month, a wrong balance, or a rate change applied back to 1 January?",
     "Were the affected customers told, and do their statements for December 2025 show the adjustment?",
     "Was the same run done at 31 December 2024? The ledger shows no such lines for 2024, but E-Banker had only been live for five months by then.",
     "Should the engine treat each line as part of 2025 interest (an error put right) or as a change of terms from the date the new rate applied? We will recommend, but the policy is MAIIC's."], numbered=True)

H("7. What we will do in the meantime")
P("The engine will load the 28 lines as their own class, \"year-end rate-difference adjustment\", separate from monthly interest and from cash, and show them "
  "as a separate line in the December 2025 reconciliation and in each affected loan's roll-forward. Once the tables and the answers are in, each line will be "
  "either folded into 2025 interest or treated from the date the new rate applied. The amounts are already known to the cent; what is missing is the reason.")
s.append(Spacer(1, 6))
s.append(Paragraph("Edward Mazibuko and Wadzanai Rombe, Dupleix Institute", st["meta"]))

def footer(c, d):
    c.saveState(); c.setStrokeColor(LINE); c.setLineWidth(0.5); c.line(18 * mm, 14 * mm, A4[0] - 18 * mm, 14 * mm)
    c.setFont("Seg", 8); c.setFillColor(GREY); c.drawString(18 * mm, 9.5 * mm, "Note to Barry: the 31 December 2025 interest adjustments  ·  Dupleix Institute  ·  7 October 2026")
    c.drawRightString(A4[0] - 18 * mm, 9.5 * mm, f"Page {d.page}"); c.restoreState()
OUT = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC\Note to Barry - the 28 interest adjustments of 31 Dec 2025 - 7 Oct 2026.pdf"
doc = BaseDocTemplate(OUT, pagesize=A4, leftMargin=18 * mm, rightMargin=18 * mm, topMargin=16 * mm, bottomMargin=20 * mm, title="Note to Barry: the 28 interest adjustments of 31 December 2025", author="Dupleix Institute")
doc.addPageTemplates([PageTemplate(id="p", frames=[Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id="f", leftPadding=0, rightPadding=0, topPadding=0, bottomPadding=0)], onPage=footer)])
doc.build(s); print("written", OUT)
