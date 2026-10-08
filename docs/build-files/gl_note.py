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
s.append(Paragraph("Two small differences between the loan accounts and the general ledger", st["title"]))
s.append(Paragraph("A note for Barry Makumba from Dupleix Institute  ·  7 October 2026  ·  with the two queries that will settle it", st["meta"]))

H("1. First, thank you")
P("The eighteen files you sent this morning are the best data we have had on this project. We have been able to check them against each other and against "
  "the monthly trial balances, and almost everything ties to the cent. This note is about the one thing that does not, because it is small, it is interesting, "
  "and you are the only person who can see the cause.")

H("2. The idea behind the check, in one picture")
P("A general ledger code such as 1050201 (FInES Agricultural Loans) is a drawer. Inside the drawer are the individual loan accounts. The balance on the drawer "
  "should always equal the sum of the balances of the accounts inside it, because every posting that changes a loan account also changes the drawer by the "
  "same amount. Like this:")
TAB(["", "Loan accounts inside GL 1050201 (illustration)", "Balance"], [
    ["", "Account A", "1,000"], ["", "Account B", "2,500"], ["", "Account C", "500"],
    ["", "<b>Sum of the accounts</b>", "<b>4,000</b>"], ["", "<b>Balance of GL 1050201 in the trial balance</b>", "<b>4,000</b>"],
], [0.05, 0.70, 0.25])
P("We did exactly this with your files. We took every posting in the ledger file (P1_01), added them up account by account, then GL by GL, at each of the "
  "twenty month-ends from January 2025 to August 2026, and compared the total with the trial balance for that month.")

H("3. What we found")
TAB(["GL code", "Name", "Result over the 20 month-ends"], [
    ["1050101", "MAIIC Agricultural Loans", "Equal to the cent in every month"],
    ["1050102", "MAIIC Industrial Loans", "Equal to the cent in every month"],
    ["1050401", "MAIIC Term Loan", "Equal to the cent in every month"],
    ["1050201", "FInES Agricultural Loans", "The trial balance is <b>400,000.00 higher</b> than the accounts, in every month"],
    ["1050202", "FInES Industrial Loans", "The trial balance is <b>1,000,000.00 lower</b> than the accounts, in every month"],
], [0.14, 0.30, 0.56])
P("Three of the five drawers are perfect, which tells us the ledger file is complete for them. The two FInES drawers each carry a difference, and the "
  "difference is the same figure in January 2025 as in August 2026.")

H("4. Why \"the same every month\" is the clue")
P("If postings were missing from the file as the months went by, the difference would move: it would be one number in March and another in July. A "
  "difference that never moves means nothing has gone wrong since January 2025. Whatever caused it happened once, before January 2025, and has sat in "
  "the drawer ever since. The round amounts, 400,000 and 1,000,000, point the same way: they look like entries made by hand, not like interest or repayments.")
P("So we are not looking for a flaw in your extract. We are looking for one or two old entries that sit in the GL but not in any loan account.")

H("5. The three places it can be")
TAB(["Possibility", "What it would look like", "How the first query shows it"], [
    ["A journal posted straight to the GL code, with no loan account number", "A posting on 1050201 or 1050202 whose account number is blank", "It appears in the query result with an empty NEW_AC_NUMBER"],
    ["A posting on an account that is not one of the 184 loans we extract", "A loan on a scheme whose name does not start with MAIIC or FInES, but which sits on a FInES GL code", "It appears in the query result with an account number we do not have"],
    ["A difference from the July 2024 take-on", "The GL opening balance was loaded with one figure and the loan accounts with another", "The first query returns nothing; the second query shows the opening balance"],
], [0.30, 0.38, 0.32])

H("6. The two queries to run")
P("Both are plain SELECTs. They read, and change nothing. Run the session settings first as before, then export each result as CSV.")
P("<b>Query 1: postings on the two GL codes that do not belong to one of our 184 accounts.</b> Save as GL_01_fines_postings_without_account.csv.")
CODE("""SELECT cv.*
FROM   cumvouch cv
WHERE  cv.ac_glcode IN ('1050201', '1050202')
AND    cv.delete_flag = 'N'
AND   (cv.new_ac_number IS NULL
       OR cv.new_ac_number NOT IN (SELECT a.new_ac_number FROM acmaster a
                                   WHERE a.scheme_mst_id IN (84,85,86,87,88,89,90,91,92,93,94,95,
                                                             140,141,142,143,144,145)))
ORDER  BY cv.ac_glcode, cv.transaction_date, cv.cumvouch_det_id;""")
P("<b>Query 2: the GL opening balances loaded at take-on.</b> Save as GL_02_fines_opening_balances.csv. The table GL_OPENING_BALANCE appeared in your "
  "dictionary results; if its GL code column has a different name, the error message will say so.")
CODE("""SELECT *
FROM   gl_opening_balance
WHERE  glcode IN ('1050101', '1050102', '1050201', '1050202', '1050401')
ORDER  BY glcode;""")
P("If query 1 returns rows, the sum of their amounts on each GL code should be 400,000 and 1,000,000 (allowing for sign), and the question is answered. If it "
  "returns nothing, query 2 shows the opening balances, and the question becomes how those two figures were arrived at in July 2024.")

H("7. Why we care about 600,000 in a book of 15 billion")
P("The net difference is 600,000 on a loan book of about MWK 15 billion, so it changes no result. It matters for a different reason. The engine we are building "
  "has to reconcile to the trial balance to the cent so that the auditors can rely on it. A difference we can name is a reconciling item they will accept in a "
  "sentence. A difference nobody can explain is a question mark against the whole control. Two queries now save a long conversation later.")
s.append(Spacer(1, 6))
s.append(Paragraph("Thank you again. The results have moved the project further in a day than the previous two months.", st["p"]))
s.append(Paragraph("Edward Mazibuko and Wadzanai Rombe, Dupleix Institute", st["meta"]))

def footer(c, d):
    c.saveState(); c.setStrokeColor(LINE); c.setLineWidth(0.5); c.line(18 * mm, 14 * mm, A4[0] - 18 * mm, 14 * mm)
    c.setFont("Seg", 8); c.setFillColor(GREY); c.drawString(18 * mm, 9.5 * mm, "Note to Barry: the two FInES GL differences  ·  Dupleix Institute  ·  7 October 2026")
    c.drawRightString(A4[0] - 18 * mm, 9.5 * mm, f"Page {d.page}"); c.restoreState()
OUT = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC\Note to Barry - the two FInES GL differences - 7 Oct 2026.pdf"
doc = BaseDocTemplate(OUT, pagesize=A4, leftMargin=18 * mm, rightMargin=18 * mm, topMargin=16 * mm, bottomMargin=20 * mm, title="Note to Barry: the two FInES GL differences", author="Dupleix Institute")
doc.addPageTemplates([PageTemplate(id="p", frames=[Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id="f", leftPadding=0, rightPadding=0, topPadding=0, bottomPadding=0)], onPage=footer)])
doc.build(s); print("written", OUT)
