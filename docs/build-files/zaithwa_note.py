"""Plain-language note to Barry on the Zaithwa Farms balance difference, with the queries to run."""
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
NAVY = colors.HexColor("#1B2A41"); ORANGE = colors.HexColor("#E07B00"); GREY = colors.HexColor("#5B6470"); LIGHT = colors.HexColor("#F4F6F9"); LINE = colors.HexColor("#C9D1DB")
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
s.append(Paragraph("One account where the balance table and the ledger disagree: Zaithwa Farms", st["title"]))
s.append(Paragraph("A note for Barry Makumba from Dupleix Institute  ·  7 October 2026  ·  with the queries that will settle it", st["meta"]))

H("1. The check this came from")
P("E-Banker keeps two records of every loan. The first is the ledger (the table CUMVOUCH): every posting ever made, one row each. The second is the balance "
  "table (ACCOUNT_BALANCE): one row per account per month-end, with the balance on that day. The second should always be the sum of the first, because a "
  "month-end balance is nothing more than every posting up to that day added together.")
P("We tested this with the two files you sent, P1_01 (the ledger) and P2_09 (the balance table): 131 accounts, 2,549 account-months. For 130 accounts the "
  "two agree to the cent in every month. That is a strong result; it means the ledger file is complete and the balance table is faithful to it. "
  "One account is the exception.")

H("2. What the numbers show for Zaithwa Farms, account 000104450000015")
P("Up to April 2025 the two records agree. From May 2025 they differ by exactly 1,827,633.62, and they go on differing by that same amount every month "
  "after that. In the table below, \"owed\" means the customer owes MAIIC; \"in credit\" means the customer has paid more than was owed.")
TAB(["Month-end", "Balance table (P2_09)", "Sum of the ledger postings (P1_01)", "Difference"], [
    ["April 2025 and earlier", "agree", "agree", "nil"],
    ["May 2025", "27,303.21 owed", "1,854,936.83 owed", "1,827,633.62"],
    ["June and July 2025", "658,534.33 in credit", "1,169,099.29 owed", "1,827,633.62"],
    ["August 2025 onward", "3,053,316.65 in credit", "1,225,683.03 in credit", "1,827,633.62"],
], [0.22, 0.26, 0.30, 0.22])
P("In words: during May 2025 something reduced what the customer owed by 1,827,633.62 in the balance table, and no posting of that amount for that account "
  "is in the ledger file. Both records agree the customer has been in credit since August 2025; they disagree on by how much.")

H("3. What \"missing\" means, and the three places the entry can be")
P("We are not saying money is missing. We are saying a posting is missing from the file, or was never made. There are three ways that happens:")
TAB(["Possibility", "What it would look like", "Which query shows it"], [
    ["The posting exists but was left out of the extract", "A row on this account marked deleted (DELETE_FLAG = Y), which our extract excluded, or a row on a different sub-account number", "Query 1 lists every row on the account, deleted or not, on every sub-account"],
    ["The posting was made to a different account number", "A credit of 1,827,633.62 in May 2025 on another account, for example the old account number, while the balance table was updated for this one", "Query 2 finds any posting of that amount in April to June 2025, on any account"],
    ["The balance table was changed without a posting", "Queries 1 and 2 both return nothing of the amount", "Then the balance row itself is the only record. Query 3 shows who entered it and when"],
], [0.30, 0.42, 0.28])

H("4. Before the queries: the two date settings, and why")
P("Please run these two lines once, after logging in and before the queries. The files you sent on 7 October had their dates written month first (7/15/2025). "
  "That is readable, but year-first (2025-07-15) is the form that no program can misread, and it is what we have asked for in every request. The lines below "
  "ask your session to display dates that way and numbers with a dot for the decimal point.")
CODE("""ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS';
ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '.,';""")
P("What they do: they change how <b>your session</b> displays dates and numbers. What they do not do: they change nothing in the database, no table, no "
  "setting, no other user. They last only until you disconnect; the next login starts with the normal defaults, and there is nothing to put back.")
P("One more thing we learned from the 7 October files: the export still came out month-first even though the settings were run. That is because SQL "
  "Developer's export follows its own preference, under Tools, Preferences, Database, NLS, Date Format. If you set that preference to YYYY-MM-DD once, every "
  "export from then on will be year-first and the session lines will not be needed again.")

H("5. The three queries")
P("All three are plain SELECTs. They read and change nothing. Export each result as CSV under the name shown, and please do not open the files in Excel "
  "before sending: that is what swapped days and months in the August extracts.")
P("<b>Query 1: every posting on the account, including deleted rows and every sub-account.</b> Save as ZF_01_all_postings.csv.")
CODE("""SELECT cv.*
FROM   cumvouch cv
WHERE  cv.new_ac_number = '000104450000015'
ORDER  BY cv.transaction_date, cv.cumvouch_det_id;""")
P("<b>Query 2: any posting of 1,827,633.62 between April and June 2025, on any account.</b> Save as ZF_02_amount_search.csv.")
CODE("""SELECT cv.*
FROM   cumvouch cv
WHERE  ABS(cv.transamt) = 1827633.62
AND    cv.transaction_date BETWEEN DATE '2025-04-01' AND DATE '2025-06-30'
ORDER  BY cv.transaction_date, cv.cumvouch_det_id;""")
P("<b>Query 3: the balance table rows for the account, with the audit columns.</b> Save as ZF_03_balance_rows.csv. This shows who entered each month-end "
  "row and when, which tells us whether the May 2025 row was produced by the month-end run or keyed afterwards.")
CODE("""SELECT b.*
FROM   account_balance b
WHERE  b.new_ac_number = '000104450000015'
ORDER  BY b.transaction_date, b.account_bal_mst_id;""")

H("6. Why we need these run")
P("The amount is small: under two million kwacha on a book of fifteen billion. The reason is not the money.")
P("The balance table is the figure MAIIC reports. The ledger is the figure our engine will calculate from, posting by posting. For 130 accounts those are the "
  "same thing, and that is what lets the engine's results be reconciled to MAIIC's books to the cent. For this one account they are not the same thing, so "
  "before the engine runs we have to know which record is right: is the customer 1.2 million in credit, or 3.1 million? If a posting was deleted or sits "
  "elsewhere, the ledger is simply incomplete for this account and we add the row. If the balance table was changed by hand, that is something Finance "
  "and the auditors need to know about, and the engine will follow the ledger.")
P("Either way the answer is one line in our reconciliation instead of an unexplained difference, and the three queries take a few minutes.")
s.append(Spacer(1, 6))
s.append(Paragraph("Thank you again for the 7 October files. They have let us tie the ledger to the balance table, to the stored loan book and to the trial balance, and this account is the only one that did not tie.", st["p"]))
s.append(Paragraph("Edward Mazibuko and Wadzanai Rombe, Dupleix Institute", st["meta"]))

def footer(c, d):
    c.saveState(); c.setStrokeColor(LINE); c.setLineWidth(0.5); c.line(18 * mm, 14 * mm, A4[0] - 18 * mm, 14 * mm)
    c.setFont("Seg", 8); c.setFillColor(GREY); c.drawString(18 * mm, 9.5 * mm, "Note to Barry: Zaithwa Farms balance difference  ·  Dupleix Institute  ·  7 October 2026")
    c.drawRightString(A4[0] - 18 * mm, 9.5 * mm, f"Page {d.page}"); c.restoreState()
OUT = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC\Note to Barry - Zaithwa Farms balance difference - 7 Oct 2026.pdf"
doc = BaseDocTemplate(OUT, pagesize=A4, leftMargin=18 * mm, rightMargin=18 * mm, topMargin=16 * mm, bottomMargin=20 * mm, title="Note to Barry: Zaithwa Farms balance difference", author="Dupleix Institute")
doc.addPageTemplates([PageTemplate(id="p", frames=[Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id="f", leftPadding=0, rightPadding=0, topPadding=0, bottomPadding=0)], onPage=footer)])
doc.build(s); print("written", OUT)
