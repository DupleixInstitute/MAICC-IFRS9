"""Plain-language request to Credit for the signed offer letters: the ten sample loans and the two sanction checks."""
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
NAVY = colors.HexColor("#1B2A41"); ORANGE = colors.HexColor("#E07B00"); GREY = colors.HexColor("#5B6470"); LIGHT = colors.HexColor("#F4F6F9"); LINE = colors.HexColor("#C9D1DB"); AMBER = colors.HexColor("#FFF4E0")
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
def BUL(items):
    s.append(ListFlowable([ListItem(Paragraph(i, st["li"]), leftIndent=14, spaceAfter=3) for i in items], bulletType="bullet", start="•", leftIndent=14, bulletColor=ORANGE, bulletFontSize=9)); s.append(Spacer(1, 4))
def TAB(header, rows, widths):
    data = [[Paragraph(h, st["head"]) for h in header]] + [[Paragraph(str(c), st["cell"]) for c in r] for r in rows]
    t = Table(data, colWidths=[W * x for x in widths], repeatRows=1)
    sty = [("BACKGROUND", (0, 0), (-1, 0), NAVY), ("VALIGN", (0, 0), (-1, -1), "TOP"), ("LEFTPADDING", (0, 0), (-1, -1), 5), ("RIGHTPADDING", (0, 0), (-1, -1), 5),
           ("TOPPADDING", (0, 0), (-1, -1), 4), ("BOTTOMPADDING", (0, 0), (-1, -1), 5), ("LINEBELOW", (0, 0), (-1, -1), 0.4, LINE), ("BOX", (0, 0), (-1, -1), 0.6, LINE)]
    for i in range(2, len(data), 2): sty.append(("BACKGROUND", (0, i), (-1, i), LIGHT))
    t.setStyle(TableStyle(sty)); s.append(t); s.append(Spacer(1, 8))
def BOX(paras):
    t = Table([[[Paragraph(x, st["box"]) for x in paras]]], colWidths=[W])
    t.setStyle(TableStyle([("BACKGROUND", (0, 0), (-1, -1), AMBER), ("LINEBEFORE", (0, 0), (0, -1), 3, ORANGE), ("LEFTPADDING", (0, 0), (-1, -1), 10), ("RIGHTPADDING", (0, 0), (-1, -1), 10), ("TOPPADDING", (0, 0), (-1, -1), 8), ("BOTTOMPADDING", (0, 0), (-1, -1), 6)]))
    s.append(t); s.append(Spacer(1, 8))

LOGO = r"C:\Users\wadza\OneDrive\2026\Tenders\PFA\Excel & Data Analysis Training\Basic Excel Course\3 Source Files\img\dupleix-logo.png"
try:
    im = Image(LOGO); r = im.imageHeight / im.imageWidth; im.drawWidth = 36 * mm; im.drawHeight = 36 * mm * r; im.hAlign = "LEFT"; s.append(im); s.append(Spacer(1, 8))
except Exception: pass
s.append(Paragraph("The offer letters we are asking Credit for", st["title"]))
s.append(Paragraph("A note for Dibwa and the Credit team from Dupleix Institute  ·  7 October 2026  ·  twelve letters in two groups, and why each one matters", st["meta"]))

H("1. Where this request comes from")
P("This is not a new ask. On 11 September 2026 our consolidated information request listed the signed offer letters among the documents needed for the "
  "effective interest rate, because the fees and the margin are not held in the core system. At the EIR meeting on 24 September it was agreed that Credit "
  "would provide <b>the signed offer letters for the ten sample loans, showing the fees and the margin</b>, alongside the system walkthrough. Ten letters "
  "reached us on 17 September; they appear to be the issue versions rather than the signed copies, so the 24 September item is still open. This note repeats "
  "that ask with the reasons set out in full, and adds two further letters that this week's audit of the system data has made necessary.")
TAB(["Group", "What", "Where it was asked", "Status"], [
    ["A", "The signed offer letters for the ten sample loans, with fees and margin", "11 September consolidated request; agreed at the 24 September meeting (item 3 of the follow-up)", "Open: unsigned versions received 17 September"],
    ["B", "Two further letters, to settle the approved amount on two take-on loans", "New: from the audit of the 7 October extracts", "New"],
], [0.08, 0.37, 0.35, 0.20])
H("2. Why offer letters, when we now have the system data")
P("Since Monday we have received the full data dictionary of E-Banker and eighteen extracts from it, and we can now tie the loan ledger to the trial balance "
  "to the cent. That settles what the <b>system</b> holds. It does not settle what the <b>contract</b> says, and the effective interest rate (EIR) is "
  "calculated on the contract: the amount approved, the rate and how it is built up, the fees deducted, and the repayment terms the customer signed for.")
P("The system record and the signed letter are not always the same. We have already found two of the ten sample loans where the rate in the core differs "
  "from the rate typed on the letter (Ebenezer Midian 31% in the core against 25.1% on the letter; JAT Group 33% against 25.3%), and two take-on loans where "
  "the approved amount in the system looks mis-keyed. Only the signed letter can say which is right. That is why we ask for the letters themselves.")
BOX(["<b>What \"signed offer letter\" means here:</b> the final version accepted and signed by the customer, with every schedule and annex, as kept on the "
     "credit file. A draft or system-generated letter is useful but is not the contract. The ten letters we received on 17 September appear to be the "
     "unsigned issue versions; if any of them is in fact the signed version, please just say so and we will not ask again for that one."])

H("3. Group A: the signed letters for the ten sample loans, as agreed on 24 September")
P("These are the ten loans chosen in September as the sample for the whole engine: the same ten whose EMI charts Barry provided, whose interest we "
  "rebuilt from the ledger, and which the walkthrough followed from approval to posting. The reason the signed letter is needed for each is the same "
  "reason it was asked for in September: the fees deducted at drawdown and the margin over the reference rate are the two terms that make an effective "
  "interest rate differ from the rate on the system, and neither is held in the core. From each signed letter we will read:")
BUL(["the <b>approved amount</b> and the drawdown terms (single or in tranches);",
     "the <b>interest rate</b>, and how it is made up: the reference rate, the spread above it (the margin), and whether the rate is fixed or moves with the Reserve Bank rate;",
     "the <b>fees</b>: arrangement, legal, insurance and any other charge deducted from the advance, with the amount or percentage;",
     "the <b>repayment terms</b>: frequency, first instalment date, tenor, and any moratorium on principal or on interest;",
     "the <b>signature date</b>, which fixes the contract date for the EIR."])
TAB(["#", "Customer", "Account", "Product", "Why this one"], [
    ["1", "Ebenezer Midian Ltd", "000104430000084", "MAIIC Industrial", "Core rate 31% against 25.1% on the letter we hold; five tranches; the loan we have rebuilt in most detail"],
    ["2", "GOJET Investments", "000104450000098", "FInES Agricultural", "Originated after July 2026; no interest posted yet"],
    ["3", "JAT Group Ltd", "000104430000087", "MAIIC Industrial", "Core rate 33% against 25.3% on the letter; drawdowns dated before the application"],
    ["4", "JVD Agro Limited", "000104460000120", "FInES Industrial", "Capitalising moratorium; interest rebuilds to the cent from April 2026"],
    ["5", "Lake Malawi Aquaculture Limited", "000106050000009", "MAIIC Term Loan", "Approved 1,055m, drawn 297m: the part-drawn case"],
    ["6", "Malawi Police SACCO", "000106050000006", "MAIIC Term Loan", "Fees of 90m arrangement and 49.3m legal deducted at drawdown in January 2026"],
    ["7", "Microloan Foundation Limited", "000104460000116", "FInES Industrial", "First-month interest matches to the cent; clean test case"],
    ["8", "Milele Agroprocessing Limited", "000106050000007", "MAIIC Term Loan", "Drawn in several tranches inside the month"],
    ["9", "Mphunzitsi SACCO", "000106050000014", "MAIIC Term Loan", "Originated after July 2026; 30m arrangement fee at drawdown"],
    ["10", "Promenade Medical Centre", "000106050000004", "MAIIC Term Loan", "No interest posted for two months after drawdown, then a catch-up posting"],
], [0.04, 0.26, 0.17, 0.17, 0.36])
P("Where a loan was varied after signature (a rate change, a restructure, a top-up), please include the variation letter as well. Where fees were not on the "
  "letter but were deducted, a note of the amount and the authority for it is enough.")

H("4. Group B: two further letters, arising from this week's audit")
P("When E-Banker went live, each existing loan was loaded with one opening balance line dated 31 July 2024. For most loans that balance is a little above the "
  "approved amount, because it includes interest capitalised over the years before, and that is expected. Two loans are different: the opening balance is a "
  "round multiple of the approved amount in the system, which looks like either a facility increase that was never recorded or a mis-keyed figure.")
TAB(["Customer", "Account", "Product", "Approved amount in the system", "Balance loaded on 31 July 2024", "What the letter will tell us"], [
    ["Mchinji Civil Servants SACCO", "000104460000070", "FInES Industrial, sanctioned 20 July 2023", "50,000,000", "100,000,000", "Whether the facility was 50m or 100m, or was increased by a later letter"],
    ["VNC Bricks and Construction Group Ltd", "000104430000054", "MAIIC Industrial, sanctioned 19 October 2022", "20,000,000", "200,000,000", "Whether 20,000,000 in the system is a typing slip for 200,000,000"],
], [0.20, 0.15, 0.19, 0.14, 0.14, 0.18])
P("For each, please send the signed letter and any later letter that changed the amount. If the letter shows the system figure is wrong, Barry can correct "
  "the sanction amount in the loan master, and the engine will then agree with the contract. Both loans are no longer accruing (one dormant, one paid up), so "
  "this affects history and the audit trail rather than current income, but the record should say what the letter says.")

H("5. What we will do with them")
BUL(["Read every term into a table, loan by loan, next to the system's version of the same term, and list every difference.",
     "Use the letter's fees and rate build-up to calculate each loan's effective interest rate, and reconcile the interest that rate produces to what the ledger posted.",
     "Bring the differences to you and Dr Thom with a recommendation on which record the engine should treat as the contract, so that the choice is written down before the first results go out.",
     "Return the two sanction questions to Barry with the letter's figure, so that the loan master can be corrected if needed."])

H("6. How to send them")
P("PDF scans are fine; one file per loan, named with the customer's name, is ideal. If the file holds confidential personal details beyond the loan terms, "
  "those pages can be left out; we need the facility terms, the fee schedule and the signature page.")
P("Thank you. The ten sample loans are the thread that runs through the whole engine, and the letters are the one document the system cannot give us.")
s.append(Spacer(1, 6))
s.append(Paragraph("Edward Mazibuko and Wadzanai Rombe, Dupleix Institute", st["meta"]))

def footer(c, d):
    c.saveState(); c.setStrokeColor(LINE); c.setLineWidth(0.5); c.line(18 * mm, 14 * mm, A4[0] - 18 * mm, 14 * mm)
    c.setFont("Seg", 8); c.setFillColor(GREY); c.drawString(18 * mm, 9.5 * mm, "Request to Credit: the twelve offer letters  ·  Dupleix Institute  ·  7 October 2026")
    c.drawRightString(A4[0] - 18 * mm, 9.5 * mm, f"Page {d.page}"); c.restoreState()
OUT = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC\Request to Credit - the twelve offer letters - 7 Oct 2026.pdf"
doc = BaseDocTemplate(OUT, pagesize=A4, leftMargin=18 * mm, rightMargin=18 * mm, topMargin=16 * mm, bottomMargin=20 * mm, title="Request to Credit: the twelve offer letters", author="Dupleix Institute")
doc.addPageTemplates([PageTemplate(id="p", frames=[Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id="f", leftPadding=0, rightPadding=0, topPadding=0, bottomPadding=0)], onPage=footer)])
doc.build(s); print("written", OUT)
