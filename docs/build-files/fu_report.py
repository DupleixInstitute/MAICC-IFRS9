"""Audit report on the 18 follow-up extracts received from MAIIC on 7 October 2026 (PDF, reportlab)."""
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.lib import colors
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.enums import TA_LEFT
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import (BaseDocTemplate, PageTemplate, Frame, Paragraph, Spacer, Table, TableStyle,
                                PageBreak, KeepTogether, Image, ListFlowable, ListItem, CondPageBreak)

F = r"C:\Windows\Fonts"
pdfmetrics.registerFont(TTFont("Seg", F + r"\segoeui.ttf")); pdfmetrics.registerFont(TTFont("Seg-B", F + r"\segoeuib.ttf"))
pdfmetrics.registerFont(TTFont("Seg-I", F + r"\segoeuii.ttf")); pdfmetrics.registerFont(TTFont("Seg-BI", F + r"\segoeuiz.ttf"))
pdfmetrics.registerFontFamily("Seg", normal="Seg", bold="Seg-B", italic="Seg-I", boldItalic="Seg-BI")
pdfmetrics.registerFont(TTFont("Mono", F + r"\consola.ttf"))
NAVY = colors.HexColor("#1B2A41"); ORANGE = colors.HexColor("#E07B00"); GREY = colors.HexColor("#5B6470")
LIGHT = colors.HexColor("#F4F6F9"); LINE = colors.HexColor("#C9D1DB"); AMBER = colors.HexColor("#FFF4E0"); GREEN = colors.HexColor("#EAF6EC")
S = {
    "title": ParagraphStyle("title", fontName="Seg-B", fontSize=24, leading=29, textColor=NAVY, spaceAfter=4),
    "sub": ParagraphStyle("sub", fontName="Seg", fontSize=13, leading=17, textColor=GREY, spaceAfter=10),
    "meta": ParagraphStyle("meta", fontName="Seg", fontSize=9.5, leading=13, textColor=GREY),
    "h1": ParagraphStyle("h1", fontName="Seg-B", fontSize=15, leading=19, textColor=NAVY, spaceBefore=14, spaceAfter=6, keepWithNext=1),
    "h2": ParagraphStyle("h2", fontName="Seg-B", fontSize=11.5, leading=15, textColor=ORANGE, spaceBefore=10, spaceAfter=4, keepWithNext=1),
    "h2t": ParagraphStyle("h2t", fontName="Seg-B", fontSize=11.5, leading=15, textColor=ORANGE, spaceBefore=10, spaceAfter=4),
    "p": ParagraphStyle("p", fontName="Seg", fontSize=10, leading=14.2, spaceAfter=6, alignment=TA_LEFT),
    "li": ParagraphStyle("li", fontName="Seg", fontSize=10, leading=14),
    "cell": ParagraphStyle("cell", fontName="Seg", fontSize=8.6, leading=11.4),
    "cellb": ParagraphStyle("cellb", fontName="Seg-B", fontSize=8.6, leading=11.4),
    "head": ParagraphStyle("head", fontName="Seg-B", fontSize=8.8, leading=11.4, textColor=colors.white),
    "box": ParagraphStyle("box", fontName="Seg", fontSize=10, leading=14.2, spaceAfter=3),
    "note": ParagraphStyle("note", fontName="Seg-I", fontSize=8.8, leading=12, textColor=GREY, spaceAfter=6),
}
W = A4[0] - 36 * mm
story = []
def H1(t): story.append(Paragraph(t, S["h1"]))
def H2(t): story.append(Paragraph(t, S["h2"]))
def H2T(t): story.append(CondPageBreak(62 * mm)); story.append(Paragraph(t, S["h2t"]))
def P(t): story.append(Paragraph(t, S["p"]))
def NOTE(t): story.append(Paragraph(t, S["note"]))
def GAP(h=4): story.append(Spacer(1, h))
def M(t): return f'<font name="Mono" size="8">{t}</font>'
def BUL(items, numbered=False):
    story.append(ListFlowable([ListItem(Paragraph(i, S["li"]), leftIndent=14, spaceAfter=3) for i in items],
                              bulletType="1" if numbered else "bullet", bulletFontName="Seg", bulletFontSize=9,
                              start=None if numbered else "•", leftIndent=14, bulletColor=ORANGE if not numbered else NAVY)); GAP(4)
def TAB(header, rows, widths, bold_first=False, keep=False):
    data = [[Paragraph(h, S["head"]) for h in header]]
    for r in rows:
        data.append([Paragraph(str(c), S["cellb"] if (bold_first and i == 0) else S["cell"]) for i, c in enumerate(r)])
    t = Table(data, colWidths=[W * x for x in widths], repeatRows=1)
    st = [("BACKGROUND", (0, 0), (-1, 0), NAVY), ("VALIGN", (0, 0), (-1, -1), "TOP"), ("LEFTPADDING", (0, 0), (-1, -1), 5), ("RIGHTPADDING", (0, 0), (-1, -1), 5),
          ("TOPPADDING", (0, 0), (-1, -1), 4), ("BOTTOMPADDING", (0, 0), (-1, -1), 5), ("LINEBELOW", (0, 0), (-1, -1), 0.4, LINE), ("BOX", (0, 0), (-1, -1), 0.6, LINE)]
    for i in range(1, len(data)):
        if i % 2 == 0: st.append(("BACKGROUND", (0, i), (-1, i), LIGHT))
    t.setStyle(TableStyle(st)); story.append(KeepTogether([t]) if keep else t); GAP(8)
def BOX(paras, colour=AMBER):
    t = Table([[[Paragraph(x, S["box"]) for x in paras]]], colWidths=[W])
    t.setStyle(TableStyle([("BACKGROUND", (0, 0), (-1, -1), colour), ("LINEBEFORE", (0, 0), (0, -1), 3, ORANGE), ("LEFTPADDING", (0, 0), (-1, -1), 10),
                           ("RIGHTPADDING", (0, 0), (-1, -1), 10), ("TOPPADDING", (0, 0), (-1, -1), 8), ("BOTTOMPADDING", (0, 0), (-1, -1), 6)]))
    story.append(t); GAP(8)

# ------------------------------------------------------------------ cover
LOGO = r"C:\Users\wadza\OneDrive\2026\Tenders\PFA\Excel & Data Analysis Training\Basic Excel Course\3 Source Files\img\dupleix-logo.png"
try:
    im = Image(LOGO); r = im.imageHeight / im.imageWidth; im.drawWidth = 42 * mm; im.drawHeight = 42 * mm * r; im.hAlign = "LEFT"; story.append(im); GAP(10)
except Exception: pass
story.append(Paragraph("MAIIC E-Banker follow-up extracts", S["title"]))
story.append(Paragraph("Audit of the eighteen result files, the reconciliations they allow, and whether the EIR build can start", S["sub"]))
story.append(Paragraph("Prepared by Dupleix Institute for MAIIC Finance and ICT and the Dupleix team  ·  7 October 2026  ·  <b>revised the same afternoon</b> with Barry's answers to the two follow-up queries (GL differences and Zaithwa Farms)", S["meta"]))
story.append(Paragraph("Based on: the eighteen CSV files received from Barry Makumba on 7 October 2026 (P1_01 to P3_18); the twenty monthly trial balances "
                       "January 2025 to August 2026; Extracts A and C as delivered in August 2026. Every figure below was produced by scripts kept in the Build files folder.", S["meta"]))
GAP(8)
BOX(["<b>What this report is.</b> An audit of the data as received: what each file holds, whether the dates can be trusted, how the files tie to one another and to the trial balance, "
     "and what that means for building the EIR engine. It is not yet the EIR calculation itself, and it does not change any code; section 12 lists the code changes it calls for."])

# ------------------------------------------------------------------ 1 summary
H1("1. Summary")
P("All eighteen files arrived, every one ran, and together they change the position of the project. For the first time we can tie the loan ledger to the "
  "balance history, to the stored loan book, to the interest extract and to the trial balance, and the ties are exact or explained. The answers to the "
  "questions asked of this audit are:")
TAB(["Question", "Answer"], [
    ["<b>Are the dates right, and what is the format?</b>", "Yes. Every one of the 100,504 date values in the eighteen files is in the same format, month first (7/15/2025), with no time of day. None is transposed. The session setting we asked for did not take effect, so the files are not year-first, but a consistent month-first format is safe to read once the reader is told the format. Section 3."],
    ["<b>Which gaps are now covered?</b>", "Interest Policy, the rate history per loan, the PLR history, signed postings with narrations, the fee table, the loan book history from December 2024, the balance history from July 2024, the instalment chart with recoveries, and the meaning of most transaction codes. Section 4."],
    ["<b>Do the extracts reconcile to the GL?</b>", "Largely yes. The ledger agrees with the balance history on 2,533 of 2,549 account-months; with the loan book's carrying amount on all 2,264 account-months; with Extract C to the cent (MWK 5,293,988,207.06); and with the trial balance exactly for three of the five loan GLs in every month. The other two differ by a constant +400,000 and -1,000,000, now traced to the GL opening balances keyed by hand on 1 January 2025 and carried into 2026: a two-line correction for MAIIC, not a gap in the data. The one account where the balance table disagreed with the ledger (Zaithwa Farms) is a fault in the balance table; the ledger and the loan book are right. Interest income in the trial balance agrees with the ledger in every month that can be compared. Section 5."],
    ["<b>Can we rebuild the interest the system charged?</b>", "Yes, to 0.1% on 1,595 of the 2,047 standard interest postings, and to 1% on 1,828 (91.6% of the value), from daily balances and the rate in the narration. The rest are months with a mid-month rate change, which the dated rate table resolves. Section 6."],
    ["<b>Can we rely on the system's repayment profiles?</b>", "No. Only 75 of 184 accounts have a current instalment chart, only 30 of those are level instalments, and actual repayments are within 5% of the chart on 23 of 69 accounts. The engine must generate the contractual profile from the loan terms, which are present for 183 of 184 accounts, and use the chart only as a check. Section 7."],
    ["<b>Are we ready to build?</b>", "Yes for the 75 loans originated in E-Banker. For the 109 loans taken on in July 2024, the system gives everything from take-on onward (balance, rates, cash flows) but not the original drawdowns or fees, which must come from the take-on workbook and Finance. Section 8."],
    ["<b>What should change in the code?</b>", "New importers for the signed ledger, rate history, PLR, balance history and loan book history; a strict month-first date reader; cash classified by transaction code; an interest-rebuild routine on daily balances and dated rates; and three automated reconciliations. Section 12."],
], [0.30, 0.70])

# ------------------------------------------------------------------ 2 received
H1("2. What was received")
TAB(["File", "Rows", "Accounts", "What it holds", "Period"], [
    ["P1_01 ledger", "3,640", "132", "Every posting on the loan accounts, signed, with narration. 2,412 debits, 1,228 credits", "Jul 2024 to Sep 2026 (one 2022 row)"],
    ["P1_02 account master", "184", "184", "Every column of the account master: policy, flag, rate, status, dates", "Current"],
    ["P1_03 loan master", "184", "184", "Sanction amount and date, tenor, expiry, moratorium fields", "Current"],
    ["P1_04 rate set-up", "1,291", "183", "Rate history per account: policy, PLR rate, rate, effective date", "Feb 2020 to Sep 2026"],
    ["P1_05 interest summary", "82", "82", "The calculation behind each interest posting, but for the latest month only (confirmed by the re-run of 7 Oct, 13:03: same month, 12 rows)", "Aug 2026 only"],
    ["P2_06 PLR master", "49", "", "The prime lending rate history", "Mar 2020 to Sep 2025"],
    ["P2_07 disbursement charges", "3,651", "52 in scope", "Fee lines by name and amount (whole table, 1,218 accounts)", "Current"],
    ["P2_08 loan book history", "12,414", "144", "The stored loan book report, several runs per month-end (2,361 unique rows)", "Dec 2024 to Aug 2026, 21 month-ends"],
    ["P2_09 balance history", "2,549", "131", "Month-end balance, receipts, payments, interest fields", "Jul 2024 onward, monthly"],
    ["P2_10 instalment chart", "2,742", "75", "Current chart: date, instalment, principal and interest parts, expected balance, recovered", "Current"],
    ["P3_11 instalment plans", "1,958", "160", "Instalment plan versions: type, frequency, start date, amount", "2021 onward"],
    ["P3_12 disbursement schedules", "251", "161", "Planned disbursements", ""],
    ["P3_13 status history", "283", "184", "Every status change with its reason", "2020 onward"],
    ["P3_14 daily accrual", "7,489", "14", "Daily accrual log, fourteen accounts only", "Nov 2024 to Sep 2026"],
    ["P3_15 slab master", "100", "", "Scheme-level rate slabs", "2010 onward"],
    ["P3_16 forgiven interest", "2", "2", "Interest forgiveness entries", "Jun 2026"],
    ["P3_17 auto charges", "1", "", "One charge rule", ""],
    ["P3_18 date-time check", "3", "", "No date carries a time of day: 0 of 409,552 ledger rows, 79,375 balance rows, 97,249 chart rows", ""],
], [0.19, 0.08, 0.10, 0.41, 0.22], bold_first=True)
P("Two files are thinner than hoped. The interest summary holds only the latest month, so it cannot replace Extract C for history; it is not needed, because the "
  "ledger's own interest postings carry the period and rate in their narration. The daily accrual log covers fourteen accounts only.")

# ------------------------------------------------------------------ 3 dates
H1("3. The dates: format, proof and what to do")
H2("What the files contain")
P("Every date column in every file is written the same way: month, day, year, with slashes and no time of day, for example 7/15/2025 and 8/8/2025. We tested "
  "all 100,504 non-blank date values: in none of them is the first number greater than 12, and in 66,762 the second number is greater than 12. That proves the "
  "order is month first throughout. Run 18 confirms that none of the dates in the three big tables carries a time of day, so the \"on or before\" and "
  "\"between\" tests in the extract scripts lose nothing at the end of a day.")
H2("Why this is different from the August extracts")
P("The August extracts were damaged on the way out: Excel re-read day-first text as month-first and silently swapped days and months on part of each column, "
  "leaving a mixture that could not be trusted. We can now prove that. Extract A's loan start dates come in two kinds: 108 that stayed as text, all 108 of "
  "which match the true opening date now in the account master; and 73 that Excel had converted, of which 2 match as read and 71 match only after the day and "
  "month are swapped back. The follow-up files have not been through Excel, so every column is one format and nothing is swapped.")
H2("Why the files are not year-first, and the fix")
P("The two session settings we asked Barry to run would have produced 2025-07-15. The files show that the export tool used its own date preference instead. "
  "In SQL Developer the grid and the CSV export follow Tools, Preferences, Database, NLS, Date Format, which overrides the session setting. That is harmless "
  "here, because month-first with a four-digit year is unambiguous and consistent. For future extracts Barry can set that preference to YYYY-MM-DD once, and "
  "the files will arrive year-first.")
BOX(["<b>The rule for reading these files:</b> parse every date column with the explicit format month/day/year. Never let a reader guess, and reject any "
     "file in which a date column mixes real dates with text, because that is the signature of an Excel round trip. Account numbers are text with leading "
     "zeros (fifteen characters, every row, every file) and must be read as text."])

# ------------------------------------------------------------------ 4 gaps
H1("4. The gaps these files close")
TAB(["Gap we had", "What closes it", "Evidence"], [
    ["Interest Policy per loan", "Account master and rate set-up", "P 42 accounts (all MAIIC Agricultural and Industrial), S 127 (FInES, Term and Investment), M 15 (manual). Matches the loan book behaviour"],
    ["The spread over the prime rate", "Rate set-up table", "MARGIN_PERCENT on the loan master is zero everywhere; the spread is the difference between INTEREST_RATE and PLR_RATE in the rate set-up (Ebenezer Midian: 31.2 - 25.3 = 5.9)"],
    ["Rate history per loan", "Rate set-up table", "1,291 rows, 183 accounts, effective dates from February 2020; 106 of 108 pre-migration accounts have a rate dated before July 2024; MAIIC accounts carry up to 54 dated steps"],
    ["Reference rate history", "PLR master", "49 rows, March 2020 to September 2025"],
    ["Direction of postings", "Ledger", "Signed amounts and a DBCR column. Disbursements, interest charges and reversals are debits (negative); repayments and adjustments are credits"],
    ["Meaning of transaction codes", "Ledger narrations", "See section 6: 300 write-off, 306 reversal of a repayment, 343 reversal of credits, 120 year-end rate-difference credit, 303 interest charge, 301 drawdown"],
    ["Fees", "Charges table and ledger narrations", "Charges table: 84 lines with an amount on 52 of our accounts, MWK 350.7m. Narrations name fees deducted at drawdown on 18 accounts. Section 9"],
    ["Loan book for the missing months", "Loan book history", "21 month-ends from December 2024; November 2024 and earlier are not in it, but the balance history covers July 2024 onward"],
    ["Month-end balances", "Balance history", "2,549 account-months from July 2024; ties to the ledger on 2,533"],
    ["The instalment chart with recoveries", "Chart extract", "75 accounts with a current chart, with principal and interest parts and an expected balance per row"],
    ["Status H and F", "Status history and balances", "Not in the status history as codes. The last recorded change on every H account is to D with the reason Paid Up or Foreclosure; 18 of 21 H accounts carry a nil balance and 17 have an interest stop date. H looks like \"paid up, awaiting closure\". Vendor to confirm"],
    ["Restructures and write-offs", "Ledger", "The two write-offs (Micholess Creameries 10,244,624.69 and Maluso Cooperative 12,041,809.63, August 2024) exist as type 300 ledger credits although the write-off tables are empty"],
], [0.22, 0.22, 0.56])
P("Still outstanding: the LOS application and process references, the meaning of code H from the vendor, the origination data for the take-on loans (section 8), "
  "and the two constant GL differences (section 5).")

# ------------------------------------------------------------------ 5 reconciliations
H1("5. Reconciliations")
P("Each test joins two sources that were produced independently. A tie means both are complete and consistent; a difference is a lead.")
TAB(["#", "Test", "Result", "What it tells us"], [
    ["a", "Ledger running balance against the balance history, every account-month", "2,549 account-months; 2,533 agree within MWK 1; the 16 that differ are one account, Zaithwa Farms (000104450000015), by 1,827,633.62 from May 2025", "<b>Resolved by Barry's ZF queries.</b> Every posting on the account is in the extract (24 rows, one deleted nil row; the ledger closes at 1,225,683.03 in the customer's favour). The balance table holds two rows for 30 April 2025, and its May 2025 row started from nil instead of April's -3,827,633.62 and omitted May's two receipts of 1,000,000; every later row inherits the error. The loan book carrying amount equals the ledger. The balance table is wrong for this account from May 2025; it is the only account with a duplicate month-end row"],
    ["b", "Ledger running balance against the loan book's carrying amount", "2,264 account-months; 2,264 agree. The loan book's PRINCIPAL column agrees on 15 only", "The stored loan book is the ledger balance. PRINCIPAL is a different measure (it excludes capitalised interest) and must not be used as the carrying amount"],
    ["c", "Ledger interest (types 303 and 120) against Extract C, run 41", "1,564 account-months, all agree; totals January 2025 to July 2026 equal to the cent, MWK 5,293,988,207.06. The ledger has 537 further account-months outside Extract C's window", "Extract C is exactly the ledger's interest postings on the loan's own GL. It is no longer needed"],
    ["d", "Trial balance loan GL balances against the sum of ledger balances, 20 month-ends", "1050101, 1050102 and 1050401 agree to the cent in every month. 1050201 is 400,000.00 higher in the TB in every month; 1050202 is 1,000,000.00 lower in every month", "<b>Resolved by Barry's GL queries.</b> There are no postings on either GL without an account (GL_01 is empty). E-Banker's GL opening balance is keyed by hand each year: the 1 January 2025 opening for 1050201 was keyed 400,000.00 above the accounts' total at 31 December 2024, and 1050202 1,000,000.00 below; the 1 January 2026 openings were keyed to the trial balance closing and carry the same differences. For the other three GLs the keyed opening equals the accounts to the cent. The fix is a two-line adjustment to the GL opening balances, after Finance confirms which figure agreed to the 2024 audited accounts"],
    ["e", "Trial balance interest income against ledger interest, by GL, by month", "The TB income accounts are year-to-date. On that basis the ledger agrees in every month that has a TB line: 83 rows. The 17 rows without a line are FInES Agricultural in January 2025 (nil), the Term Loan account before December 2025 (nil), and December 2025, whose TB carries no income lines", "Interest charged to the loans in the ledger is what the GL recognised as income. The reconciliation the engine must perform is EIR interest against these figures"],
    ["f", "Interest summary (August 2026) against the ledger's August interest", "82 accounts, all equal; no overdue or penal component in any of them", "The posting is the plain accrual; penalties are not mixed into it"],
    ["g", "Drawdowns against sanctioned amounts", "131 accounts with drawdowns: equal on 112, less on 13, more on 6", "The six over-drawn are worth a look; they may be fee or insurance add-ons posted as drawdowns"],
], [0.03, 0.22, 0.38, 0.37])
P("Total loan book at 31 December 2025: trial balance MWK 14,840,612,779.54; ledger MWK 14,841,212,779.54; difference 600,000.00, being the two constant items.")

# ------------------------------------------------------------------ 6 interest rebuild
H1("6. Rebuilding the interest the system charged")
P("Each monthly interest posting carries its own narration: \"IntDR Fm 08-AUG-25 To 31-AUG-25 @ 31%\". We rebuilt every posting from the ledger alone: the "
  "balance at the end of each day in the stated period, summed, times the stated rate, divided by 365.")
TAB(["Measure", "Result"], [
    ["Interest postings (type 303)", "2,159, totalling MWK 4,526,723,541.58"],
    ["With the standard narration", "2,047. The other 112 are 79 \"Opening interest charge\" rows at take-on (31 July 2024) and 22 year-end \"Diff Int. Debit ... By ROI\" rows (December 2025), together MWK 1,837,859,679.99"],
    ["Rebuilt within 0.1%", "1,595 of 2,047"],
    ["Rebuilt within 1%", "1,828 of 2,047, which is 91.6% of the value posted"],
    ["Not within 1%", "219 postings on 93 accounts, 8.4% of the value. These are months in which the rate changed part-way through (the rate set-up shows steps dated 5 and 9 March 2026 for the same loan, for example); the narration carries only the closing rate"],
    ["Narration rate against the rate set-up table", "1,659 of 2,047 agree with the rate in force on the posting date"],
    ["Convention", "Daily closing balance, actual days, 365-day year. The monthly rate/12 convention does not reproduce the postings"],
], [0.30, 0.70])
P("Conclusion: the engine can reproduce E-Banker's own accrual from the ledger and the dated rate table, which is what makes a reconciliation of EIR interest to "
  "GL interest explainable line by line. The remaining step is to apply the dated rate steps inside a month rather than one rate per month.")
H2("The transaction codes, now explained")
TAB(["Code", "Rows", "Sign", "Meaning, from the narrations"], [
    ["301", "214", "Debit", "Drawdown, including fee and insurance deductions posted as separate drawdown lines; the 78 take-on lines of 31 July 2024 are \"Opening Account Balance Disbursement amount\", MWK 8,297,388,309.25"],
    ["303", "2,159", "Debit", "Monthly interest charge, capitalised to the loan; plus the take-on opening interest and the year-end rate-difference debits"],
    ["305", "1,212", "Credit", "Repayment"],
    ["306", "37", "Debit", "Reversal of a repayment (\"reversal\", \"reversal for duplicate payments\", \"reverse of miss posting\")"],
    ["120", "6", "Credit", "\"Diff Int. Credit From 01/01/2025 To 31/12/2025 By ROI\": year-end credit correcting interest for a rate change; equals the Extract B to C difference"],
    ["300", "2", "Credit", "Write-off (Micholess Creameries, Maluso Cooperative), August 2024"],
    ["343", "2", "Credit", "\"Reversal of credit transactions to account\", 12 March 2026"],
    ["900 / 901", "1 / 1", "Debit / Credit", "A fee of 10,350,000 posted on 6 May 2025 and reversed on 12 May 2025"],
    ["400 / 401", "1 / 5", "", "Postings on GL 3065, NASCOMEX Limited, an equity-type instrument, not a loan"],
], [0.10, 0.08, 0.12, 0.70], keep=True)

# ------------------------------------------------------------------ 7 repayment profiles
H1("7. Repayment profiles: rebuild them, or rely on the system?")
P("The effective interest rate needs the contractual cash flows at origination, and the present value tests need the expected cash flows discounted at that rate. "
  "The question is whether E-Banker's stored instalment chart can serve as those cash flows.")
TAB(["Test on the stored chart", "Result"], [
    ["Accounts with a current chart", "75 of 184. Of the 89 active accounts, 53 have no current chart"],
    ["Shape of the chart", "56 of 75 begin with a zero row at the start date; 74 of 75 end with an expected balance of nil; principal plus interest equals the instalment on 2,240 of 2,742 rows"],
    ["Level instalments", "30 of 75 accounts; the rest step up or down"],
    ["Interest convention in the chart", "Expected balance times the current rate divided by 12 reproduces 1,094 of 2,635 interest parts within 0.5%; the actual-days convention reproduces 28. The chart is a monthly-rate schedule, the ledger accrues daily: the two will never agree exactly"],
    ["Actual repayments against the chart", "Of 69 accounts with both, 23 have paid within 5% of what the chart says was due to date; 21 have paid less than 80%; 8 have paid more than 120%"],
    ["Recovered-to-date field", "Does not agree with cash received on any account"],
], [0.32, 0.68])
H2("What this means for the engine")
BUL([
    "<b>Generate the contractual profile from the terms.</b> The terms are present: sanction amount, sanction date, expiry and tenor on 183 of 184 accounts; rate "
    "on all 184; principal and interest moratorium periods on all 184; an instalment plan (frequency, start date, type) on 159. The generator should follow the chart's "
    "own convention, level instalment at rate/12 on the expected balance, with the moratorium shapes the loan master and plan describe.",
    "<b>Use the stored chart as the check, not the source.</b> Where a chart exists, the generated schedule should reproduce it; where it does not, the two "
    "conventions (monthly in the chart, daily in the ledger) explain the gap.",
    "<b>Take actual cash flows from the ledger.</b> Drawdowns are type 301, repayments are type 305 net of 306. Repayment behaviour departs from the schedule on "
    "two accounts in three, so the amortised cost after origination must roll forward on actual cash, never on the schedule.",
    "<b>Discount expected cash flows at the EIR.</b> For the present value of future repayments (impairment and modification tests), the expected cash flows are "
    "the generated contractual schedule from the current balance, adjusted for known arrears and moratoria, discounted at the original EIR. The stored chart "
    "cannot be relied on for this because it is missing for most accounts and does not reflect what has been paid.",
])

# ------------------------------------------------------------------ 8 ready
H1("8. Are we ready to build: pre- and post-migration")
TAB(["Component", "Post-migration (75 accounts, opened from July 2024)", "Pre-migration (109 accounts, opened before July 2024)"], [
    ["Cash out, by drawdown", "In the ledger: 54 accounts with type 301 postings, dated, with fee lines named", "Not in E-Banker. 77 accounts were taken on with one \"opening balance\" line on 31 July 2024; 32 had already closed. Original drawdowns come from the take-on workbook"],
    ["Fees at origination", "Charges table (38 accounts with amounts) and the ledger narrations (18 accounts); section 9", "Not in E-Banker. From the 2024 and 2025 EIR assessments and Finance's records"],
    ["Rate at origination and changes", "Rate set-up table with effective dates; PLR history; the rate in every interest narration", "Rate set-up table carries a rate dated before July 2024 for 106 of 108; the take-on workbook carries the dated steps"],
    ["Contractual schedule", "Generated from the terms (section 7)", "Generated from the original terms, which the loan master holds (sanction date, tenor), from the original date"],
    ["Actual cash flows", "Ledger, complete", "Ledger from 31 July 2024; the take-on workbook before"],
    ["Carrying amount at month-end", "Balance history from July 2024; loan book from December 2024", "Same, from take-on; before that the take-on workbook"],
    ["Interest recognised, for the reconciliation", "Ledger type 303 and 120, tying to the TB", "Same, from July 2024"],
    ["Linking key", "Account number, present everywhere", "Account number for the E-Banker side; the take-on workbook has no account numbers and needs the mapping from Finance"],
], [0.20, 0.40, 0.40])
BOX(["<b>Verdict.</b> Post-migration: ready, subject to the three items in section 11. Pre-migration: ready from 31 July 2024 onward; the origination side "
     "(original drawdowns, fees, pre-2024 rate steps) needs the take-on workbook mapped to account numbers, and the fee records from Finance. The 46 closed "
     "accounts need no profile. The 21 H and 3 F accounts are paid up and need only closing entries."], GREEN)

# ------------------------------------------------------------------ 9 fees
H1("9. Fees: two sources that overlap but do not yet agree")
TAB(["Source", "What it shows"], [
    ["Charges table (P2_07), our accounts", "52 accounts, 155 lines, 84 with an amount: Agreement Fee MWK 205,417,295 on 38 accounts, Legal Fee 136,217,758 on 37, Other Fee 9,032,970 on 9. POST_FLAG is N and INCOME_GL is blank on every line, so this table records the fees agreed, not their posting"],
    ["Ledger narrations (P1_01), type 301", "43 drawdown lines on 18 accounts name a fee: arrangement fees 197,537,942, legal fees 73,922,725, insurance 4,419,598.84. These are the amounts actually deducted from the advance"],
    ["Overlap", "13 accounts appear in both; 5 have a fee narration but no charges line; 25 have a charges line but no fee narration (most of them are take-on loans whose drawdown predates the ledger)"],
    ["Trial balance fee income, August 2026 year-to-date", "4873 Arrangement Fees 414,947,879; 4871 Legal Fees 243,389,041"],
], [0.30, 0.70])
P("For the EIR the figure that matters is the fee actually deducted at drawdown, which is the ledger line. Where an account has a charges-table amount but no "
  "ledger line, the fee was either deducted before July 2024 (take-on loans) or not deducted through the loan account. The fee template remains the way to "
  "confirm the latter. A separate reconciliation of the fee income accounts to these two sources is still to be done.")

# ------------------------------------------------------------------ 10 status
H1("10. Status codes")
TAB(["Code", "Accounts", "What the data shows"], [
    ["A Active", "89", "6 have no ledger postings at all: approved, not yet drawn"],
    ["C Closed", "46", "No ledger rows for 46 of them; closed before July 2024 or settled"],
    ["D Dormant", "25", "23 of 25 carry a balance; 20 have an interest stop date. In E-Banker \"dormant\" appears to mean non-accruing, which is the staging population"],
    ["H", "21", "Not a code in the status history. The last recorded change on each is to D, reason Paid Up (17), Foreclosure (3) or Account closure (1); 18 of 21 have a nil balance; 17 have an interest stop date. Reads as paid up, awaiting closure. Vendor to confirm"],
    ["F", "3", "All three nil balance, reason Paid Up"],
], [0.12, 0.10, 0.78], keep=True)

# ------------------------------------------------------------------ 11 open
H1("11. What is still open, and what to ask")
TAB(["Item", "Ask", "Who"], [
    ["Constant GL differences: 1050201 +400,000.00, 1050202 -1,000,000.00", "<b>Answered.</b> Keyed in the 1 January 2025 GL opening balances. Finance to confirm which figure agreed to the 2024 audited accounts, then adjust the GL opening balances for 2025 and 2026", "Finance"],
    ["Zaithwa Farms balance table", "<b>Answered.</b> The ledger and loan book are right (1,225,683.03 in the customer's favour); the balance table is wrong from May 2025 after a duplicate 30 April row. Ask the vendor to rebuild the account's month-end rows, and check any statement sent to the customer", "Barry and the vendor"],
    ["Six over-drawn accounts (drawn exceeds sanction)", "Whether the excess is fee or insurance add-on", "Credit"],
    ["Code H", "Confirm \"paid up, awaiting closure\"", "Vendor"],
    ["Take-on loans: original drawdowns, fees, pre-2024 rate steps", "The take-on workbook blocks mapped to account numbers, and the origination fee per loan", "Tamanda"],
    ["Interest summary and daily accrual tables hold the latest month and 14 accounts only", "Confirm they are purged monthly; not needed if so", "Barry"],
    ["Year-end \"Diff Int ... By ROI\" debits (22) and credits (6) of December 2025", "What triggered them: a rate correction applied retrospectively?", "Finance"],
    ["Export date format", "Set SQL Developer's NLS date format preference to YYYY-MM-DD so future files arrive year-first", "Barry"],
    ["Loan book history runs", "Several runs are stored per month-end (up to 12); we take the latest. Confirm that is the right one", "Barry"],
], [0.44, 0.44, 0.12])

# ------------------------------------------------------------------ 12 code
H1("12. Suggested changes to the engine code")
P("These follow from the audit. They are for the Dupleix team; nothing has been changed yet. File names refer to the MAICC-IFRS9 repository.")
TAB(["#", "Change", "Why", "Priority"], [
    ["1", "<b>A strict date reader for MAIIC files:</b> parse with the explicit month/day/year format; refuse a column that mixes real dates and text; refuse ambiguous formats. Replace the flexible fallback in " + M("MappedFileReader.php") + " for these feeds.", "The August files were damaged by a guessing reader downstream of Excel. These files are safe only if read with the declared format.", "High"],
    ["2", "<b>A ledger importer</b> for P1_01: signed amount, transaction type code, narration, GL code, posting number. Classify cash by code: 301 out, 305 in, 306 reversal of 305, 303 accrual (not cash), 120 and year-end 303 adjustments, 300 write-off, 343 reversal. Replace " + M("ContractTransactionImportService.php") + " and the label-based list in " + M("EirRevenueService.php") + ".", "Direction and meaning now come from the data. The present code counts interest charges as cash collected.", "High"],
    ["3", "<b>Rate history importer</b> for P1_04 and P2_06: dated rate steps per account and the PLR series; derive the rate type from INTEREST_POLICY (P floating, S and M fixed); drop the derivation from Extract A's label.", "Interest Policy and the dated rates are now in hand; the old label was inverted.", "High"],
    ["4", "<b>Interest rebuild routine:</b> daily closing balance from the ledger, times the rate in force each day from the rate steps, over 365. Report each posting as rebuilt, within tolerance, or unexplained.", "Reproduces 91.6% of interest to 1% already; with dated steps it should reach the rest. This is the explainable half of the EIR-to-GL reconciliation.", "High"],
    ["5", "<b>Schedule generator aligned to the chart's convention:</b> level instalment at rate/12 on the expected balance, zero opening row, principal and interest moratorium periods from the loan master, frequency from the plan. Compare to the stored chart where one exists and report the variance.", "The engine must generate profiles for all accounts; only 75 have a chart.", "High"],
    ["6", "<b>Balance history and loan book importers</b> (P2_09, P2_08, latest run per month-end), used as independent month-end balances.", "Both tie to the ledger; they give the carrying amount for months with no posting and the missing-months loan book.", "Medium"],
    ["7", "<b>Three automated reconciliations</b> run on every load: ledger balance to balance history per account-month; ledger balance to the TB per GL per month-end; ledger interest to TB interest income (year-to-date) per GL per month.", "All three tie today; running them on each load catches a broken extract before it reaches a calculation.", "Medium"],
    ["8", "<b>Retire the Extract A, B and C importers</b> once the new feeds are loaded, or keep them read-only for lineage.", "The ledger reproduces Extract C to the cent; the account and loan masters replace Extract A; the signed ledger replaces Extract B.", "Medium"],
    ["9", "<b>Fee capture:</b> read fees from the type 301 lines whose narration names a fee, and from the charges table, and reconcile both to the fee income GLs; keep the fee template for fees handled outside the account.", "Two sources overlap on 13 accounts only.", "Medium"],
    ["10", "<b>Status handling:</b> store the raw code; treat D as non-accruing; treat H and F as paid-up pending vendor confirmation; use INTEREST_STOP_DATE as the non-accrual date.", "The data now explains the codes well enough to act on.", "Low"],
], [0.03, 0.40, 0.45, 0.12])

# ------------------------------------------------------------------ 13 next
H1("13. Next steps")
BUL([
    "Send Barry the four asks in section 11 that are his: the GL-only postings on 1050201 and 1050202, the Zaithwa Farms posting, the purge behaviour of the two small tables, and the date preference.",
    "Ask Tamanda for the take-on workbook mapping to account numbers and the origination fee per take-on loan.",
    "Make the code changes in section 12, items 1 to 5 first, and load the eighteen files.",
    "Run the EIR for the 75 post-migration loans, reconcile EIR interest to the trial balance by GL and month, and present that reconciliation as the first engine output.",
    "Extend to the take-on loans as the mapping and fees arrive.",
], numbered=True)

# ------------------------------------------------------------------ appendix
story.append(PageBreak())
H1("Appendix A. The tests behind this report")
TAB(["Test", "Result"], [
    ["Date values checked across all files", "100,504; first component never above 12; second component above 12 in 66,762"],
    ["Date columns with a time of day (run 18)", "0 of 409,552 ledger rows; 0 of 79,375 balance rows; 0 of 97,249 chart rows"],
    ["Extract A loan start dates against the account master", "Text dates 108: all match. Excel-converted 73: 2 match as read, 71 match after swapping day and month"],
    ["Scope", "184 accounts: A 89, C 46, D 25, H 21, F 3. Opened before July 2024: 109; from July 2024: 75"],
    ["Interest Policy per account", "S 127, P 42, M 15. Scheme 84: P 8, M 8; scheme 86: P 34, M 7; all FInES, Term and Investment schemes S"],
    ["Ledger", "3,640 rows, 132 accounts, 2,412 debits, 1,228 credits, all on the loan's own GL; 17 postings entered after their transaction date (13 interest, 4 drawdowns), up to 699 days"],
    ["Ledger against balance history", "2,549 account-months; 2,533 within MWK 1; 16 differ, all Zaithwa Farms, 1,827,633.62 from May 2025"],
    ["Ledger against loan book carrying amount", "2,264 of 2,264 agree; PRINCIPAL agrees on 15"],
    ["Loan book history runs", "12,414 rows, 2,361 unique account and month-end; up to 12 runs per month-end; latest run kept"],
    ["Ledger against Extract C run 41", "1,564 account-months, all agree; both total 5,293,988,207.06"],
    ["Trial balance loan GLs against ledger", "90 GL-months; 50 exact; 1050201 +400,000.00 and 1050202 -1,000,000.00 in all 20 months"],
    ["Trial balance interest income against ledger", "83 of 83 comparable GL-months agree on a year-to-date basis; 17 have no TB line"],
    ["Interest rebuild from daily balances and narration rate", "2,047 standard postings: 1,595 within 0.1%, 1,828 within 1% (91.6% of value); 219 not within 1% on 93 accounts"],
    ["Narration rate against rate set-up in force", "1,659 of 2,047 agree"],
    ["Interest summary August 2026 against ledger", "82 of 82 equal; no overdue interest"],
    ["Drawdowns against sanction", "131 accounts: equal 112, less 13, more 6"],
    ["Fees in ledger narrations", "43 lines, 18 accounts: arrangement 197,537,942; legal 73,922,725; insurance 4,419,598.84"],
    ["Charges table, our accounts", "52 accounts, 155 lines, 84 with amounts totalling 350,668,023; POST_FLAG N, INCOME_GL blank on all"],
    ["Chart", "75 accounts, 2,742 rows; zero first row 56; nil closing balance 74; parts add to instalment 2,240; monthly convention 1,094 of 2,635; level 30 of 75"],
    ["Repayments against chart due to date", "69 accounts: within 5% 23; below 80% 21; above 120% 8"],
    ["Terms available for generation", "Sanction amount, date, expiry, tenor 183 of 184; rate 184; moratorium fields 184; plan 159; chart 75"],
    ["Take-on", "78 opening-balance drawdown lines on 31 July 2024, 77 accounts, 8,297,388,309.25; 79 opening interest charge lines"],
    ["Rate history for pre-migration accounts", "108 accounts with rows; 106 with a rate dated before July 2024"],
    ["GL_01: postings on 1050201 and 1050202 without an in-scope account", "None"],
    ["GL_02: keyed GL opening balance against the ledger at the previous year-end", "1050101, 1050102, 1050401: equal to the cent (2025 and 2026 openings). 1050201: +400,000.00 in both years. 1050202: -1,000,000.00 in both years. 2025 openings entered 30 July 2025; 2026 openings entered 12 January 2026"],
    ["ZF_01: all postings on Zaithwa Farms", "24 rows, 1 deleted (nil amount); sum 1,225,683.03 credit; every row already in the extract"],
    ["ZF_02: any posting of 1,827,633.62, April to June 2025", "None"],
    ["ZF_03: balance table rows for Zaithwa Farms", "27 rows; 30 April 2025 appears twice (ids 256080 and 317562); the May row shows nil receipts and a balance of -27,303.21 against the ledger's -1,854,936.83"],
    ["Balance table: duplicate month-end rows across all accounts", "One account only, Zaithwa Farms, 30 April 2025"],
    ["P1_05 re-sent (13:03): interest summary", "12 rows, the first 12 of the morning's 82, August 2026 only. The table is a working table rebuilt each month-end; it holds no history. The history comes from P1_04 rates, P3_14 daily accrual and the ledger, which is what the rebuild used"],
    ["Year-end adjustments: the contra side (8 Oct)", "The 6 credits (85,166,683.31) went through interest income 4215 and 4216: December movements agree to MWK 3 and MWK 23. The 22 debits (96,390,096.16) are in neither December nor January income and no single GL moved by their amount; the contra sits in a balance-sheet account, most likely 1040 Accrued Interest Income. Query GL_03 (8 Oct) pulls both legs of batches INTP 41 and 42"],
    ["P2_06 re-sent (13:04): PLR master", "49 rows, identical to the morning file except that PLR id 2 (13.40% from 13 October 2020) lost its applicable-from date in the export; the row kept its sort position, so the date is still in the database. Same rate as the row before it, so no effect. The morning file is the one to use"],
], [0.40, 0.60])

def footer(canvas, doc):
    canvas.saveState(); canvas.setStrokeColor(LINE); canvas.setLineWidth(0.5)
    canvas.line(18 * mm, 14 * mm, A4[0] - 18 * mm, 14 * mm)
    canvas.setFont("Seg", 8); canvas.setFillColor(GREY)
    canvas.drawString(18 * mm, 9.5 * mm, "MAIIC E-Banker follow-up extracts: audit  ·  Dupleix Institute  ·  7 October 2026")
    canvas.drawRightString(A4[0] - 18 * mm, 9.5 * mm, f"Page {doc.page}"); canvas.restoreState()

OUT = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC\MAIIC_FollowUp_Extracts_Audit_2026-10-07.pdf"
doc = BaseDocTemplate(OUT, pagesize=A4, leftMargin=18 * mm, rightMargin=18 * mm, topMargin=16 * mm, bottomMargin=20 * mm,
                      title="MAIIC E-Banker follow-up extracts: audit", author="Dupleix Institute")
doc.addPageTemplates([PageTemplate(id="p", frames=[Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id="f", leftPadding=0, rightPadding=0, topPadding=0, bottomPadding=0)], onPage=footer)])
doc.build(story)
print("written", OUT)
