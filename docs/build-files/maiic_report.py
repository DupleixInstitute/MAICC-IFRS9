"""Plain-language report on the three MAIIC raw query scripts (PDF, reportlab). Version 2, after the Codex review."""
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
pdfmetrics.registerFont(TTFont("Seg", F + r"\segoeui.ttf"))
pdfmetrics.registerFont(TTFont("Seg-B", F + r"\segoeuib.ttf"))
pdfmetrics.registerFont(TTFont("Seg-I", F + r"\segoeuii.ttf"))
pdfmetrics.registerFont(TTFont("Seg-BI", F + r"\segoeuiz.ttf"))
pdfmetrics.registerFontFamily("Seg", normal="Seg", bold="Seg-B", italic="Seg-I", boldItalic="Seg-BI")
pdfmetrics.registerFont(TTFont("Mono", F + r"\consola.ttf"))

NAVY = colors.HexColor("#1B2A41"); ORANGE = colors.HexColor("#E07B00"); GREY = colors.HexColor("#5B6470")
LIGHT = colors.HexColor("#F4F6F9"); LINE = colors.HexColor("#C9D1DB"); AMBER = colors.HexColor("#FFF4E0")

S = {
    "title": ParagraphStyle("title", fontName="Seg-B", fontSize=24, leading=29, textColor=NAVY, spaceAfter=4),
    "sub": ParagraphStyle("sub", fontName="Seg", fontSize=13, leading=17, textColor=GREY, spaceAfter=10),
    "meta": ParagraphStyle("meta", fontName="Seg", fontSize=9.5, leading=13, textColor=GREY),
    "h1": ParagraphStyle("h1", fontName="Seg-B", fontSize=15, leading=19, textColor=NAVY, spaceBefore=14, spaceAfter=6, keepWithNext=1),
    "h2": ParagraphStyle("h2", fontName="Seg-B", fontSize=11.5, leading=15, textColor=ORANGE, spaceBefore=10, spaceAfter=4, keepWithNext=1),
    "h2t": ParagraphStyle("h2t", fontName="Seg-B", fontSize=11.5, leading=15, textColor=ORANGE, spaceBefore=10, spaceAfter=4),
    "p": ParagraphStyle("p", fontName="Seg", fontSize=10, leading=14.2, textColor=colors.black, spaceAfter=6, alignment=TA_LEFT),
    "li": ParagraphStyle("li", fontName="Seg", fontSize=10, leading=14, textColor=colors.black),
    "cell": ParagraphStyle("cell", fontName="Seg", fontSize=8.6, leading=11.4),
    "cellb": ParagraphStyle("cellb", fontName="Seg-B", fontSize=8.6, leading=11.4),
    "head": ParagraphStyle("head", fontName="Seg-B", fontSize=8.8, leading=11.4, textColor=colors.white),
    "box": ParagraphStyle("box", fontName="Seg", fontSize=10, leading=14.2, textColor=colors.black, spaceAfter=3),
    "note": ParagraphStyle("note", fontName="Seg-I", fontSize=8.8, leading=12, textColor=GREY, spaceAfter=6),
}
W = A4[0] - 36 * mm
story = []


def H1(t): story.append(Paragraph(t, S["h1"]))
def H2(t): story.append(Paragraph(t, S["h2"]))
def P(t): story.append(Paragraph(t, S["p"]))
def NOTE(t): story.append(Paragraph(t, S["note"]))
def GAP(h=4): story.append(Spacer(1, h))


def H2T(t):
    """Heading that sits directly above a long table: break first if little room is left."""
    story.append(CondPageBreak(62 * mm)); story.append(Paragraph(t, S["h2t"]))


def BUL(items, numbered=False):
    story.append(ListFlowable([ListItem(Paragraph(i, S["li"]), leftIndent=14, spaceAfter=3) for i in items],
                              bulletType="1" if numbered else "bullet", bulletFontName="Seg", bulletFontSize=9,
                              start=None if numbered else "•", leftIndent=14, bulletColor=ORANGE if not numbered else NAVY))
    GAP(4)


def TAB(header, rows, widths, bold_first=False, keep=False):
    data = [[Paragraph(h, S["head"]) for h in header]]
    for r in rows:
        data.append([Paragraph(str(c), S["cellb"] if (bold_first and i == 0) else S["cell"]) for i, c in enumerate(r)])
    t = Table(data, colWidths=[W * x for x in widths], repeatRows=1)
    st = [("BACKGROUND", (0, 0), (-1, 0), NAVY), ("VALIGN", (0, 0), (-1, -1), "TOP"),
          ("LEFTPADDING", (0, 0), (-1, -1), 5), ("RIGHTPADDING", (0, 0), (-1, -1), 5),
          ("TOPPADDING", (0, 0), (-1, -1), 4), ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
          ("LINEBELOW", (0, 0), (-1, -1), 0.4, LINE), ("BOX", (0, 0), (-1, -1), 0.6, LINE)]
    for i in range(1, len(data)):
        if i % 2 == 0:
            st.append(("BACKGROUND", (0, i), (-1, i), LIGHT))
    t.setStyle(TableStyle(st))
    story.append(KeepTogether([t]) if keep else t)
    GAP(8)


def BOX(paras, colour=AMBER):
    t = Table([[[Paragraph(x, S["box"]) for x in paras]]], colWidths=[W])
    t.setStyle(TableStyle([("BACKGROUND", (0, 0), (-1, -1), colour), ("LINEBEFORE", (0, 0), (0, -1), 3, ORANGE),
                           ("LEFTPADDING", (0, 0), (-1, -1), 10), ("RIGHTPADDING", (0, 0), (-1, -1), 10),
                           ("TOPPADDING", (0, 0), (-1, -1), 8), ("BOTTOMPADDING", (0, 0), (-1, -1), 6)]))
    story.append(t); GAP(8)


def M(t):
    return f'<font name="Mono" size="8">{t}</font>'


# ------------------------------------------------------------------ cover block
LOGO = r"C:\Users\wadza\OneDrive\2026\Tenders\PFA\Excel & Data Analysis Training\Basic Excel Course\3 Source Files\img\dupleix-logo.png"
try:
    im = Image(LOGO); r = im.imageHeight / im.imageWidth; im.drawWidth = 42 * mm; im.drawHeight = 42 * mm * r; im.hAlign = "LEFT"
    story.append(im); GAP(10)
except Exception:
    pass
story.append(Paragraph("MAIIC E-Banker extract scripts", S["title"]))
story.append(Paragraph("What the three scripts do, where they fall short, and which queries to run next", S["sub"]))
story.append(Paragraph("Prepared by Dupleix Institute for MAIIC Finance and ICT  ·  5 October 2026  ·  <b>Version 2</b>", S["meta"]))
story.append(Paragraph("Version 2 was revised on the same day after an independent review of the first version. The review's comments, "
                       "our response to each, and what changed are in Appendix E.", S["meta"]))
story.append(Paragraph("Based on: the three script files received on 5 October 2026; Extracts A and B as delivered on 3 August 2026; "
                       "Extract C as re-run and delivered on 26 August 2026; the E-Banker user manual.", S["meta"]))
GAP(8)
BOX(["<b>What this report is, and is not.</b> It is an analysis of three extract scripts and of the files they produced. "
     "We read the scripts and tested them against those files. We have not seen the database.",
     "It is <b>not</b> a completed reconciliation of interest income, and it does <b>not</b> validate any contractual cash flow "
     "schedule. The 27 queries it asks for were written without access to the database and have not been run. "
     "Section 2 separates what we tested from what we infer and what is still waiting on MAIIC or the vendor."])

# ------------------------------------------------------------------ 1 summary
H1("1. Summary")
P("MAIIC has sent us the three database scripts that produce Extract A (the facility register), Extract B (the cash flow "
  "register) and Extract C (the interest posted on each loan). We read each script line by line and then tested what it "
  "implies against the extract files we already hold.")
P("The scripts are tidy, and they prove that the data we need is held in E-Banker. They also explain, for the first time, "
  "most of the puzzles we raised in September. But each script makes choices that hide or change information the effective "
  "interest rate (EIR) engine depends on, and some of the fields we need are not read by any of the three.")
H2("Three findings that affect figures already in use")
BUL([
    "<b>The Extract C file counts 2025 three times.</b> The script keeps every earlier run, and the file we received holds three "
    "runs. Added up as delivered, 2025 interest is MWK 7,663,172,090. The figure from the latest run alone is MWK 2,554,390,697.",
    "<b>The Fixed and Variable labels in Extract A appear to be the wrong way round.</b> The script labels the system's code F as "
    "\"Fixed\" and its code I as \"Variable\", while the manual gives F as Floating and I as Fixed. This would explain why the "
    "labels contradicted what the loan books show.",
    "<b>The \"Interest\" rows in Extract B are interest charged to the loan, not cash received.</b> 987 of the 994 interest rows "
    "we could test raise the loan balance, and they are the same postings Extract C reports. They must not be treated as "
    "money received.",
], numbered=True)
H2("What we are asking for")
P("Two short rounds of read-only queries, before any extract is rewritten:")
BUL([
    "<b>First, the data dictionary queries.</b> These list the tables and columns E-Banker holds. We need them first because the "
    "fields still missing, starting with Interest Policy, do not appear in any of the three scripts. We do not yet know what "
    "they are called or which table they sit in, so an amended extract cannot be written.",
    "<b>Second, the question queries.</b> These return codes, counts, checks and one sample loan. They answer specific open "
    "questions: which way each transaction type moves the balance, where the fees are posted, what the status codes are, "
    "whether dates carry a time of day, and why the restructure date is blank.",
    "<b>Then the amended extracts,</b> written once, against the real structure.",
], numbered=True)
P("All 27 queries are in the file <b>RUN ORDER - data dictionary queries by priority.sql</b>, in the order to run them. "
  "Runs 1 to 7 are enough for us to start drafting the amended extracts.")
P("This version also sets out suggested changes to the three scripts (section 12) and to our own engine's code (section 13).")

# ------------------------------------------------------------------ 2 how sure
H1("2. How sure we are")
P("Not every statement in this report rests on the same footing. This table separates the three kinds.")
TAB(["Footing", "Statements"], [
    ["<b>Tested on the delivered files.</b> Each can be re-run from the scripts in the Build files folder; the reviewer re-ran the main ones and got the same figures.",
     "The Extract C file holds three runs with identical 2025 amounts.<br/>"
     "The rate label by product: MAIIC Agricultural and Industrial all \"Fixed\", FInES Agricultural and Industrial all \"Variable\".<br/>"
     "Rate, status, sanctioned amount and tenor are identical in both Extract A snapshots.<br/>"
     "Extract B has no negative amounts, 539 estimated rows, and no balance on its scheduled rows.<br/>"
     "Extract B interest and Extract C agree on 101 of 107 accounts for 2025; the difference is MWK 85,166,683.31 on six accounts.<br/>"
     "987 of 994 interest rows raise the running balance.<br/>"
     "Extract B's closing balance agrees with Extract A's year-end balance on 110 of 111 accounts.<br/>"
     "Ebenezer Midian: 15 July 2025 in the disbursement list, 8 August 2025 in the ledger."],
    ["<b>Inferred.</b> Reasoned from the scripts and the files, but not confirmed against the database or by the vendor.",
     "That the rate labels are inverted. This rests on the manual's code list (F Floating, I Fixed) and on loan book behaviour; we have not seen the raw flag values.<br/>"
     "That interest rows are charges. This rests on how the balance moves; we have not seen the raw signed postings.<br/>"
     "That the instalment chart begins with a row at the loan's start date.<br/>"
     "That the reschedule table is empty.<br/>"
     "That the dates were scrambled by Excel on the way out.<br/>"
     "What the \"Other/Adjustment\" rows are."],
    ["<b>Waiting on MAIIC or the vendor.</b>",
     "Where Interest Policy is held.<br/>"
     "What transaction types 120 and 308 to 311 mean, and what status codes H and F mean.<br/>"
     "Where fees are posted against a loan.<br/>"
     "Whether posting dates carry a time of day.<br/>"
     "Which schema the table names point to, and whether the login can read the tables directly.<br/>"
     "Why the repayment frequency differs between the two snapshots.<br/>"
     "Why ten active accounts show no interest.<br/>"
     "The reconciliation of Extract C to the interest income codes in the trial balance."],
], [0.30, 0.70])

# ------------------------------------------------------------------ 3 what we received
H1("3. What we received")
P("Each file is a stored procedure: a routine saved inside the database that fills a results table when someone runs it. "
  "The results table is then copied out to Excel and sent to us.")
TAB(["File", "Builds", "What it produces"], [
    ["FACILITY_REGISTER.txt", "Extract A", "One row for each loan account as at a chosen date: who the customer is, the terms of the loan, and the balance."],
    ["CASHFLOW_REGISTER.txt", "Extract B", "One row for each posting on a loan in a date range, with a running balance, followed by write-offs and by scheduled instalments not yet fully recovered."],
    ["INTEREST_RECON.txt", "Extract C", "The interest posted on each loan, totalled for each month and for each year."],
], [0.26, 0.12, 0.62], bold_first=True)
P("Two things are missing from the files. The definitions of the results tables are not included, and neither is the step "
  "that copies the results out to Excel. The second one matters: it is where the dates were scrambled (section 7).")
H2("The E-Banker tables the scripts read")
P("This is the first time we have seen E-Banker's real table names. Between them the three scripts read thirteen tables.")
TAB(["Table", "What it holds, in plain language", "What the scripts take from it"], [
    ["ACMASTER", "The account master: one row for each account", "Account number, customer, GL code, interest rate, opening date, status, currency, scheme"],
    ["ACLOANMASTER", "The loan details for each account", "Sanctioned amount, tenor, expiry date, moratorium periods"],
    ["CUSTOMER_MAST", "The customer master", "Customer name"],
    ["SCHEME_MASTER", "The scheme (the product variant) each account is opened on", "Scheme name, Floating Flag, compound or simple, days in the year"],
    ["GLMASTER", "The list of GL codes", "GL title"],
    ["CUMVOUCH", "The ledger: every posting", "Date, transaction type code, amount, GL code"],
    ["ACCOUNT_BALANCE", "Balance history by date", "Principal balance"],
    ["FIXLOAN_INST_CHART", "The instalment chart (the EMI chart)", "Instalment date, amount, principal part, interest part, amount recovered"],
    ["MULTI_INST_DETAILS", "The instalment plan", "Repayment frequency"],
    ["LOAN_DISBURSMENT_SCHEDULE", "The disbursement schedule", "Date and amount"],
    ["LOAN_RESCHEDULE_DETAILS", "Reschedules (restructures)", "Latest reschedule date"],
    ["LOAN_WRITEOFF_MASTER", "The write-off register", "Write-off date and amount"],
    ["LOAN_WRITEOFF_TRANS", "Write-off postings", "Named in a comment only; not read"],
], [0.27, 0.36, 0.37], bold_first=True)
H2("Which loans are included")
P("All three scripts pick loans the same way: any account whose scheme name begins with \"MAIIC\" or \"FINES\". A loan on a "
  "scheme named differently would be left out, and the scripts cannot tell us whether any exist. The same rule also brings in "
  "accounts that are not loans: Extract A includes five Quasi Equity Investment accounts and one NASCOMEX Limited account.")

# ------------------------------------------------------------------ 4 Extract A
H1("4. Extract A: the facility register")
H2("What the script does")
P("For the date it is given, the script removes any earlier result for that date and builds it again. For each account it "
  "collects the loan's details from the account, loan, customer and scheme masters. It adds up the disbursement schedule, "
  "counts the rows in the instalment chart, picks up any write-off and the latest reschedule date, and looks up the most "
  "recent principal balance on or before the date.")
H2("What is good about it")
BUL([
    "The account number and sub-account number are carried through cleanly, so Extract A joins to B and C. There are no "
    "repeated rows for the same account, sub-account and date.",
    "Where a balance is missing, the script says why in a note: the loan opened after the date, it closed before the date, "
    "or no balance was found and it needs checking by hand.",
    "The day-count basis and whether interest is compound or simple come straight from the scheme, which settled our day-count decision.",
    "It can be run again for the same date without creating duplicates.",
])
H2T("Where it falls short")
TAB(["#", "What the script does", "Why it matters", "What the delivered file shows"], [
    ["1", "<b>Labels the rate type, apparently the wrong way round.</b> It reads the scheme's Floating Flag and labels code F as \"Fixed\" and code I as \"Variable\". The manual (pages 19 to 20) gives F as Floating and I as Fixed.",
     "The label says the opposite of how the loan behaves, so it cannot be used to decide which loans reprice.",
     "All 57 MAIIC Agricultural and Industrial accounts are labelled Fixed, and all 101 FInES Agricultural and Industrial accounts Variable. The loan books show the reverse. The 13 Term Loans still do not fit after the labels are swapped, so the flag is not the deciding field."],
    ["2", "<b>Leaves out Interest Policy and the scheme code.</b> It reads the scheme master but takes only four settings from it.",
     "Interest Policy is the setting that decides whether a loan follows the Reserve Bank rate. The scheme identifier is already in the script's hands and is simply not output.", "Not in the file."],
    ["3", "<b>Only the balance is taken as at the date.</b> The rate, status, amounts and dates are read as they stand on the day the script is run.",
     "The file looks like a picture of the book at an earlier date, but most of it is today's picture. It cannot show what a loan's rate was at 1 January 2025.",
     "Rate, status, sanctioned amount and tenor are identical in the two snapshots for all 181 accounts. Lake Malawi Aquaculture's 31 December 2025 row lists a drawdown dated 28 April 2026."],
    ["4", "<b>Takes disbursements from the disbursement schedule table,</b> not from the ledger.",
     "The schedule gives the planned dates. The date money actually went out, which is when interest starts to run, is in the ledger and can be different.",
     "Ebenezer Midian: five entries dated 15 July 2025 in Extract A; the ledger shows the same five amounts paid out on 8 August 2025. Of the 35 accounts with ledger disbursements in 2025, the totals agree on 30 and the number of entries on 28."],
    ["5", "<b>Counts the rows of the instalment chart</b> for the number of instalments, and takes the earliest chart date as the first repayment date.",
     "The chart appears to start with a row at the loan's start date. That row is counted as an instalment and reported as the first repayment.",
     "Where a chart exists, the count is the tenor in months plus one on the rows we checked. The first repayment date equals the loan start date on 63 of 128 rows. 53 accounts have no chart rows at all."],
    ["6", "<b>Reads the reschedule table for the restructure date.</b>", "If that table is empty, restructures are not being recorded in E-Banker's reschedule function, and the history lives only in Finance's spreadsheet.",
     "Blank on every row."],
    ["7", "<b>Translates only three status codes</b> (Active, Closed, Dormant). Any other code is passed through as it is.", "The meaning of the other codes still has to come from the vendor.",
     "H on 21 accounts and F on 3."],
    ["8", "<b>Removes the sign from the balance,</b> and reads the principal balance only.", "An overpaid loan would look the same as one that is owed. Any interest balance held separately is not shown. The column is also headed \"opening balance\" although it is the balance at the date.", ""],
    ["9", "<b>Tests the balance date as on or before the as-at date with the time of day removed.</b>", "If balance dates carry a time of day, a balance recorded during the as-at day itself is missed and the day before is used instead. We do not yet know whether the dates carry a time.", "Cannot be tested from the file. Run 11 answers it."],
    ["10", "<b>Does not limit write-offs and closures to the date.</b>", "A write-off made after the date appears in an earlier snapshot.", ""],
    ["11", "<b>Joins the moratorium period and its unit into text,</b> and leaves out the moratorium type.", "Values arrive as \"3 M\" or \"0\", and we cannot tell a principal-only moratorium from a full one.", "Both formats appear."],
    ["12", "<b>Reads no fee table at all.</b>", "Fees are the main reason an effective rate differs from the contract rate.", "No fee fields."],
], [0.04, 0.34, 0.30, 0.32])
H2("The repayment frequency in the two snapshots")
P("The two snapshots disagree on repayment frequency for 42 accounts. The 1 January 2025 snapshot shows Monthly on all 181 "
  "accounts. The 31 December 2025 snapshot shows Monthly on 139, Half-Yearly on 10, Quarterly on 4, Yearly on 3 and blank on 25.")
P("The script we were sent does not use the date to find the frequency, and it leaves an account with no instalment plan "
  "blank. On its own it would not produce Monthly for every account. We do not know the cause. A different version of the "
  "procedure, a change in the data between the two runs (they were about an hour apart), or an edit after export are all "
  "possible. We have asked MAIIC for the procedure's version history and a record of the two runs. Until that is settled, "
  "the frequency in the 1 January 2025 snapshot should not be relied on.")

# ------------------------------------------------------------------ 5 Extract B
H1("5. Extract B: the cash flow register")
H2("What the script does")
P("Each run is given a new run number. The script then gathers three kinds of row for the date range it is given:")
BUL([
    "<b>Actual rows:</b> every ledger posting made on the loan's own GL code, with a running balance. It names each row from "
    "its transaction type code: 301 Disbursement, 302 Principal, 303 Interest, 305 Principal+Interest, 340 and 341 Penalty, "
    "900 and 901 Fee, and anything else \"Other/Adjustment\".",
    "<b>Write-off rows:</b> taken from the write-off register.",
    "<b>Scheduled rows:</b> rows of the instalment chart that have not yet been fully recovered.",
])
H2("What is good about it")
BUL([
    "The running balance is built from the loan's ledger history on its own GL code before the date range is applied, so a "
    "one-year extract does not restart the balance at zero. At 31 December 2025 it agrees with the balance in Extract A, "
    "which comes from a different table, on 110 of 111 accounts. The exception is Zaithwa Farms (1,225,683.03 against "
    "3,053,316.65). That is one checkpoint, not proof. The balance is only right if the ledger history is complete, the "
    "opening position is in it, and every relevant posting sits on the loan's own GL code. None of those is yet confirmed.",
    "Every row carries a reference back to the ledger.",
    "The script is honest about its limits. It marks every estimated figure in a note and lists its own known limitations at the end.",
    "Its date range already runs to 31 December 2026 by default, so later periods need no rewrite.",
])
H2T("Where it falls short")
TAB(["#", "What the script does", "Why it matters", "What the delivered file shows"], [
    ["1", "<b>Removes the direction of every amount.</b> The ledger holds signed amounts; the script outputs each one without its sign.",
     "A disbursement and a repayment look the same. Direction has to be worked out from the type label or from how the balance moves.", "No negative value anywhere in 2,478 rows."],
    ["2", "<b>Presents interest charged as if it were a cash flow.</b> Type 303 appears to be the monthly interest charge added to the loan.",
     "For the effective interest rate, the cash flows are the money lent and the money repaid. Interest charged is not cash. Counting it as a receipt would count the interest twice.",
     "987 of the 994 interest rows we could test raise the running balance; 485 of 501 repayment rows reduce it. On Ifracon Limited, interest of 21,497,946.96 on 31 January 2025 takes the balance up to 788,531,241.57, and a repayment of 53,589,482.23 on 18 February takes it down to 734,941,759.34."],
    ["3", "<b>Estimates the split of each repayment</b> (type 305) between principal and interest with a formula: balance, times the loan's rate today, times the days since the previous posting.",
     "The formula uses today's rate, not the rate at the time, and counts days from the last posting of any kind. The interest has in any case already been charged separately. The split has no meaning, and the engine does not need it: it needs the amount, the date and the direction.",
     "539 rows carry the \"estimated\" note."],
    ["4", "<b>Puts every other transaction type under \"Other/Adjustment\"</b> with no sign and no type code.",
     "The label covers different things that cannot be told apart. Some rows reduce interest and are netted off in Extract C. Others raise the balance and are something else. What each one is needs the signed postings and the vendor's code list.",
     "31 rows totalling MWK 832,653,206. Of the 30 we could test, 18 raise the balance, 9 reduce it and 3 could not be placed. On five accounts these rows equal the B to C difference exactly; on Happie Foods only 2,018,700.44 of 9,018,700.44 does (Appendix B)."],
    ["5", "<b>Reads only postings on the loan's own GL code.</b>", "Fees, penalties or suspended interest posted to other GL codes for the same loan are never seen.", "2 fee rows in 2,478."],
    ["6", "<b>Includes only instalments not yet fully recovered,</b> inside the date range, at their full original amounts.",
     "These rows are neither the schedule nor the amount still owed. Fully paid instalments are left out. A part-paid instalment is shown in full, because the amount already recovered is not subtracted and not shown. They must not be used as remaining payments until the recovery against each instalment is supplied and checked.",
     "No balance on any of the 775 scheduled rows. On 14 of them the principal and interest parts do not add up to the total."],
    ["7", "<b>Selects postings between the start date and the end date.</b>", "If posting dates carry a time of day, postings made during the last day are left out. Extract C uses the same test.", "Cannot be tested from the file. Run 11 answers it."],
    ["8", "<b>Builds the reference from an internal row number.</b>", "It can be traced in the database, but it is not a voucher number a user can look up on screen. The script's own note says the voucher number field was blank.", ""],
    ["9", "<b>Takes write-offs from the register only.</b>", "There is no ledger posting to check them against, and a write-off that is also in the ledger would appear twice.", ""],
], [0.04, 0.34, 0.30, 0.32])

# ------------------------------------------------------------------ 6 Extract C
H1("6. Extract C: interest posted by loan")
H2("What the script does")
P("Each run is given a new run number, and earlier runs are kept. The script adds up ledger postings of six transaction "
  "types (120, 303, 308, 309, 310 and 311) made on the loan's own GL code. It reverses their sign so that interest charged "
  "shows as positive, and totals them for each loan for each month. It also writes a yearly total row for each loan into the "
  "same table.")
H2("What is good about it")
BUL([
    "It keeps the signs when adding, so reversals and adjustments are netted off.",
    "Each row lists the ledger references it was built from.",
    "For 2025 it agrees with the interest rows of Extract B on 101 of 107 accounts. Both extracts read the same ledger table, "
    "so this shows they are consistent with each other. It is not independent confirmation of the income figure.",
    "Within the latest run there are no repeated rows for the same loan, GL code and month.",
])
H2T("Where it falls short")
TAB(["#", "What the script does", "Why it matters", "What the delivered file shows"], [
    ["1", "<b>Keeps every earlier run in the same table.</b> The file sent to us was exported without choosing one run.",
     "Any total taken from the file as delivered counts 2025 three times.",
     "Three runs (see the table below). 1,012 loan-months appear three times with identical amounts."],
    ["2", "<b>Writes monthly rows and yearly total rows together.</b>", "Adding the whole column counts every amount twice.", "All rows add up to twice the monthly rows."],
    ["3", "<b>Measures one side only.</b> It adds up interest charged to loan accounts. It does not read the interest income GL codes.",
     "It is not a reconciliation, and this report does not perform one. Its total still has to be agreed to the movement on the income codes in the trial balance. Interest charged to a loan but credited to suspense would be counted here as income.", ""],
    ["4", "<b>Leaves out accounts with no matching posting</b> in the period.",
     "An absent account can mean three different things: no interest was posted; interest was posted where the script does not look (another GL code, another transaction type, a scheme outside the name rule); or the account was missed. The script cannot tell these apart, so an absent account is not a verified zero.",
     "61 of 181 accounts are absent: 46 closed, 10 active, 4 dormant and 1 with status H (Appendix C)."],
    ["5", "<b>Uses six type codes whose meanings we have not been given.</b>", "The balance movements suggest 303 is the monthly charge and that the others include reductions, but what each one is has to come from the vendor.", ""],
    ["6", "<b>Groups by the date of posting,</b> and selects postings between the start and end date.", "Interest posted late lands in the month it was posted, not the month it was earned. If dates carry a time of day, postings on the last day are left out.", "The catch-up postings we found in February 2026."],
], [0.04, 0.34, 0.30, 0.32])
TAB(["Run number", "Generated", "Rows", "Accounts", "Period covered", "2025 interest, MWK"], [
    ["7", "3 August 2026", "1,119", "107", "2025", "2,554,390,697"],
    ["25", "3 August 2026", "1,119", "107", "2025", "2,554,390,697"],
    ["41", "25 August 2026", "1,758", "120", "January 2025 to July 2026", "2,554,390,697"],
    ["<b>As delivered</b>", "", "<b>3,996</b>", "", "", "<b>7,663,172,090</b>"],
], [0.15, 0.17, 0.10, 0.12, 0.26, 0.20], keep=True)
P("Run 41 is the one to use. Its January to July 2026 total is MWK 2,739,597,510, which appears once and is not affected.")

# ------------------------------------------------------------------ 7 dates and export
H1("7. Where the dates were scrambled, and how files should come back")
P("In September we reported that dates were transposed in every extract, with the day and month swapped. The scripts hold "
  "proper database dates from start to finish, so the damage does not happen inside them. It happens afterwards, when the "
  "results are copied out. We believe Excel is the cause but have not seen the export step.")
P("\"Send it as CSV\" is not enough to prevent it. A CSV opened in Excel can still have its dates re-read and its account "
  "numbers stripped of their leading zeros. So every file should follow the same rules:")
BUL([
    "CSV, comma separated, UTF-8, with one header row.",
    "Dates written year first: 2026-10-05. Dates with a time: 2026-10-05 14:30:00.",
    "Account numbers exactly as stored, as text, with their leading zeros.",
    "Amounts as plain numbers: a dot for the decimal point, no thousands separators, and the minus sign kept.",
    "Not opened and re-saved in Excel at any point before it reaches us.",
])
P("The mixed dates in the files we hold remain a limit on the evidence in this report. We avoided tests that depend on "
  "dates where we could, and used the ledger's own posting order instead.")

# ------------------------------------------------------------------ 8 three findings
H1("8. The three findings, and what to do about each")
P("These are the three points from the summary, with the action each one calls for.")
TAB(["Finding", "What to do now"], [
    ["<b>Extract C counts 2025 three times</b> when the delivered file is added up.",
     "Use run 41 only, and monthly rows only. We have now read our engine's import: it skips the yearly rows and does not load a repeat of the same loan, month and GL code, so the three runs would not have been loaded three times. We have not inspected the loaded data itself. Any 2025 figure taken from the file by hand should still be checked."],
    ["<b>Fixed and Variable appear to be the wrong way round</b> in Extract A.",
     "Do not use the label. Our engine currently stores a loan's rate type from it, which needs changing (section 13). Until Interest Policy is extracted, rate behaviour should be taken from the monthly loan books."],
    ["<b>\"Interest\" rows in Extract B are charges, not receipts.</b>",
     "Our 24 September research report described these rows as cash received, and we will correct it. Our engine currently counts them as cash collected, which also needs changing (section 13). Cash out is the disbursements and cash in is the repayments."],
], [0.36, 0.64], keep=True)

# ------------------------------------------------------------------ 9 settled / open
H1("9. What the scripts settle, and what is still open")
P("The questions below are the ones we raised in September, plus those raised in review, with what the scripts tell us "
  "about each and the query that would close it. Run numbers refer to section 11.")
TAB(["Question", "What the scripts tell us", "Status", "Query that closes it"], [
    ["Is the instalment chart stored, or worked out on screen?", "Stored, in a table, with the principal and interest split. 53 accounts have no rows.", "Settled", "Run 13 confirms the columns"],
    ["Why is the number of instalments always the tenor plus one?", "It is a count of chart rows, and the chart appears to begin with a row at the start date.", "Explained, to confirm", "Run 13"],
    ["Do the disbursement tranches come through?", "Partly. 58 of the 314 populated rows list two to ten entries, so our September statement that every row had one was wrong. But the entries come from the disbursement schedule, with planned dates, not from the ledger.", "Corrected", "Runs 12 and 14"],
    ["Why is the restructure date blank everywhere?", "The script reads the reschedule table, which appears to be empty.", "Explained, to confirm", "Run 8"],
    ["Why is there no debit or credit direction in Extract B?", "The script strips the sign from every amount.", "Explained", "Run 6"],
    ["Why are 61 accounts missing from Extract C?", "Accounts with no posting that matches the script's filters produce no row. Whether each one is a true zero is not established.", "Partly explained", "Runs 7 and 10, then MAIIC"],
    ["Why were the dates scrambled?", "Not in the scripts; in the export.", "Explained, to confirm", "None needed"],
    ["Which scheme is each account on?", "The scheme identifier is already read by the script. It only needs to be output.", "Available", "Run 4"],
    ["Where is Interest Policy held?", "No script reads it.", "Open", "Runs 4 and 5"],
    ["Where are fees held against a loan?", "No script reads a fee table, and both ledger scripts look at the loan's own GL code only.", "Open", "Runs 7, 21 and 22"],
    ["What do transaction types 120 and 308 to 311 mean?", "The script names eight codes. These five are grouped as interest in Extract C and as \"Other\" in Extract B.", "Open", "Run 6, then the vendor"],
    ["What do status codes H and F mean?", "The script passes them through without a meaning.", "Open", "Run 9, then the vendor"],
    ["Is there a table of rate changes?", "No script reads one.", "Open", "Runs 21 and 22"],
    ["Does the scheme name rule leave any loans out?", "Cannot be told from the scripts.", "Open", "Run 10"],
    ["Do posting dates carry a time of day?", "The scripts' date tests would drop last-day postings if they do.", "Open", "Run 11"],
    ["Which schema do the table names point to, and can the login read the tables?", "Not shown by the scripts. More than one schema may hold a table of the same name.", "Open", "Runs 1 to 3"],
    ["Why does the repayment frequency differ between the two snapshots?", "The script we hold does not explain it.", "Open", "MAIIC: procedure versions and run record"],
], [0.27, 0.37, 0.12, 0.24])

# ------------------------------------------------------------------ 10 why dictionary first
H1("10. Why the data dictionary queries have to come first")
P("A data dictionary is the database's own list of what it holds: every table, every column in each table, the kind of data "
  "in each column, and how the tables link to one another. Oracle keeps this list automatically. Reading it does not touch "
  "any customer or transaction record.")
P("Everything we know about how E-Banker is built comes from the three scripts: thirteen table names, and only the columns "
  "those scripts happen to use. The fields we still need are, by definition, the ones the scripts do not use. The manual "
  "tells us that Interest Policy is on the Scheme Master screen, but the name on a screen is not the name of a column, and a "
  "query can only ask for a column by its real name.")
H2("Three reasons the order matters")
BUL([
    "<b>An amended extract cannot be written against a column we cannot name.</b> A query that guesses a column name fails when "
    "it is run. Each failed attempt costs a round trip between Dupleix, MAIIC and the vendor. The August extracts took two "
    "deliveries, three weeks apart, to reach their present state.",
    "<b>One pass answers many questions.</b> The same listing shows where Interest Policy sits, whether the instalment chart "
    "carries a balance, whether there is a fee table, whether there is a rate history table, and what links a loan back to "
    "the origination system. Asking for these one at a time is how the last two months were spent.",
    "<b>It is the lowest-risk request we can make.</b> The queries only read. The dictionary queries read Oracle's catalogue, "
    "not MAIIC's data, and they take minutes to run.",
], numbered=True)
H2("Why the question queries are still needed afterwards")
P("The dictionary gives structure, not meaning. It will tell us that a column called TRANTYPE exists and holds a number. It "
  "will not tell us what 308 means, or whether a repayment is stored as a positive or a negative amount. Vendor databases "
  "rarely carry written descriptions. So the second round asks the data itself: which codes are in use, how often, in which "
  "direction, and what a single well-understood loan looks like in every table.")
BOX(["<b>In short:</b> the dictionary tells us what can be asked for. The question queries tell us what the answers mean. "
     "Only then can the three extracts be rewritten once, correctly."])

# ------------------------------------------------------------------ 11 the queries
H1("11. The queries, in the order to run them")
P("The run numbers below match the file <b>RUN ORDER - data dictionary queries by priority.sql</b>. Each query in that file "
  "is headed with the reason for it and the file name to save its result under. The file begins with two session settings "
  "(run 0) that make dates and numbers display in the agreed format; they change nothing in the database.")
H2T("Tier 1: answers the open questions on Extracts A, B and C")
TAB(["Run", "Query", "What it returns, in plain language", "The question it answers"], [
    ["1", "DD_01", "Whether this login can see the thirteen tables, and who owns them", "The gate check. If it returns nothing, we need the owner's name. If it lists more than one owner, we need to know which is the live schema."],
    ["2", "DD_17", "Who is logged in, which schema that login's table names point to, and who owns the three extract procedures", "Which schema do the queries, and the procedures themselves, actually read?"],
    ["3", "DD_18", "A one-row read of each of the thirteen tables", "Can this login read the tables directly? Being able to run a procedure does not prove it."],
    ["4", "DD_10", "Every setting held on the MAIIC and FInES schemes", "Where is Interest Policy? What are the real Floating Flag values? Is there a scheme code?"],
    ["5", "DD_03", "Every column of every table: the dictionary itself", "What is each field called and where does it sit?"],
    ["6", "DD_12", "Each transaction type code, how often it is used, and whether its amounts are positive or negative", "Which way does each posting move the balance? What are the unexplained codes used for?"],
    ["7", "DD_13", "Every GL code the loan accounts post to, by transaction type", "Where do fees, penalties and suspended interest go? Is interest posted anywhere the extracts do not look?"],
    ["8", "DD_15", "The number of rows in the smaller loan tables", "Is the reschedule table empty?"],
    ["9", "DD_11", "The account status codes in use, with counts", "How many accounts carry H and F?"],
    ["10", "DD_09", "Every scheme, with the number of accounts on it", "Does the \"MAIIC or FINES\" name rule leave any loan scheme out?"],
    ["11", "DD_19", "How many ledger, balance, instalment and write-off dates carry a time of day", "Could postings on the last day of a period be dropped by the extracts' date tests?"],
], [0.07, 0.10, 0.42, 0.41])
H2("Tier 2: one sample loan, every column")
P("Runs 12 to 20 return every row held for one loan in each table: the ledger on all GL codes, the instalment chart, the "
  "disbursement schedule, the account and loan masters, the instalment plan, the balance history, and any reschedule or "
  "write-off. The loan is Ebenezer Midian, one of the ten sample loans. We chose it because we already know two things about "
  "it: the ledger shows five amounts totalling MWK 314,900,900 paid out on 8 August 2025, and its disbursement schedule "
  "shows the same five amounts dated 15 July 2025. The result can be checked the moment it arrives.")
H2("Tier 3: the rest of the dictionary")
P("Runs 21 to 27 find the tables nobody has mentioned (any table carrying the loan account number, and tables named for "
  "rates, fees, charges, audit or applications), and list the remaining code values, the keys and indexes that show how "
  "tables join, the full table list, and the existing reports that already read the loan tables. None of these holds up the "
  "amended extracts.")
H2("Practical notes for whoever runs them")
BUL([
    "Every query is read-only. Nothing is created, changed or deleted.",
    "Run them with the same login that runs the three extract procedures, and run the two session settings first.",
    "Every catalogue result carries the owner of each table, so that results from two schemas cannot be mixed up.",
    "Run one query at a time and return each result under the rules in section 7.",
    "Run 8 counts a table (LOAN_WRITEOFF_TRANS) that we know only from a comment in the cash flow script. If it does not "
    "exist, remove that line and run the query again.",
    "The vendor may prefer not to release the full listing of tables and columns (runs 5 and 26). The other queries still "
    "give us most of what we need.",
    "We wrote the queries without access to the database, so they have not been run. One or two may need a small correction "
    "the first time.",
])

# ------------------------------------------------------------------ 12 script changes
H1("12. Suggested changes to MAIIC's three extract scripts")
P("These are the changes we suggest to the scripts themselves, with the line of each file they apply to. They are small "
  "edits to code that already exists. Several depend on the Tier 1 results and are marked. We suggest the vendor or ICT "
  "makes them; we can draft the amended scripts once runs 1 to 7 are back.")
H2T("FACILITY_REGISTER.txt (Extract A)")
TAB(["Line", "What it does now", "Suggested change"], [
    ["44", "Translates the Floating Flag: F to \"Fixed\", I to \"Variable\"", "Output the raw flag value as stored. Add Interest Policy as its own column once run 4 or 5 gives its name."],
    ["30, 89", "Reads the scheme but outputs only its name", "Also output the scheme identifier (" + M("s.scheme_mst_id") + ") and the scheme's other settings."],
    ["34 to 42", "Sums and lists the disbursement schedule", "Keep it, headed as planned disbursements. Add the actual drawdowns from the ledger (type 301 postings), each with its date, amount and reference."],
    ["52 to 57", "Counts the chart rows and takes the earliest chart date", "Leave out the opening row once run 13 confirms it. Better, supply the whole chart as its own extract."],
    ["43, 59, 60 to 68", "Reads the rate, closure date and write-offs as they stand today", "Either limit them to the as-at date, or head the columns \"as at run date\" so the file cannot be misread."],
    ["69 to 70", "Translates status A, C and D only", "Output the raw status code. Add a meaning column once the code list is supplied."],
    ["77", "Removes the sign from the balance", "Keep the sign. Add any interest balance held separately."],
    ["99 to 102", "Finds the latest balance dated on or before the as-at date", "If run 11 shows dates carry a time, test for dates before the start of the next day, so the whole as-at day is included."],
    ["119", "Selects loans by scheme name beginning MAIIC or FINES", "Confirm with run 10 that no loan scheme is left out. Leave out the equity-type schemes, or flag them."],
], [0.12, 0.38, 0.50])
H2T("CASHFLOW_REGISTER.txt (Extract B)")
TAB(["Line", "What it does now", "Suggested change"], [
    ["55 to 80", "Outputs every amount and the balance without its sign", "Output the signed amount as stored, and a debit or credit column."],
    ["44 to 54", "Replaces the transaction type code with a label", "Output the raw code as well as the label."],
    ["58 to 62, 67 to 70", "Estimates the principal and interest split of type 305", "Remove the estimate. If E-Banker stores the real split anywhere, output that; if not, leave the columns empty."],
    ["34", "Reads only postings on the loan's own GL code", "Add the postings on every other GL code for the same loan accounts, or supply a separate fee and charge extract. Run 7 shows what exists."],
    ["85, 105, 126", "Selects dates between the start and end date", "If run 11 shows dates carry a time, test for dates from the start date up to, but not including, the day after the end date."],
    ["107 to 127", "Lists instalments not fully recovered, at full original amounts", "Replace with a separate instalment chart extract: every row, paid or not, with the amount recovered against each."],
    ["2 to 3", "Defaults to 1 January 2025 onward", "Run it from October 2024, when E-Banker went live."],
], [0.16, 0.36, 0.48])
H2T("INTEREST_RECON.txt (Extract C)")
TAB(["Line", "What it does now", "Suggested change"], [
    ["9 to 15", "Adds each run to the table and keeps the earlier ones", "Export the latest run only (" + M("WHERE run_id = ...") + "), or clear earlier runs for the same period first."],
    ["58 to 61", "Writes monthly rows and yearly total rows together", "Export monthly rows only, or put the yearly totals in a separate file."],
    ["37", "Adds six transaction types into one figure", "Show the amount for each type in its own column, so that charges and reductions can be seen separately."],
    ["35", "Reads only postings on the loan's own GL code", "Confirm with run 7 that no interest is posted elsewhere."],
    ["16 to 21, 33 to 34", "Produces no row for an account with no matching posting", "List every in-scope account, with a zero and a note where nothing was found."],
    ["38", "Selects dates between the start and end date", "As for Extract B, if dates carry a time."],
    ["2 to 3", "Defaults to 1 January 2025 onward", "Run it from October 2024 to date, and agree its total to the interest income codes in the trial balance."],
], [0.16, 0.36, 0.48])

# ------------------------------------------------------------------ 13 engine code
H1("13. Suggested changes to our own engine's code")
P("The review also looked at how our EIR engine loads these extracts. We then read the relevant files ourselves, in the "
  "MAICC-IFRS9 repository on the branch " + M("eir_revenue_recognition") + ", on 5 October 2026. We did not run the engine or "
  "inspect the data already loaded, and <b>nothing in the code has been changed</b>. These are suggestions for the Dupleix "
  "team, listed with the most important first.")
TAB(["#", "Where", "What the code does now", "Suggested change", "Priority"], [
    ["1", M("EirRevenueService.php") + " line 22 and the cash received routine",
     "Counts Extract B rows labelled Interest, Principal+Interest and Fee as cash collected in the month.",
     "Take Interest out of the list of collections. Those rows are interest charged to the loan; for 2025 they total MWK 2,639,557,380. Once Extract B carries signed amounts and type codes, classify cash by code and sign, not by label. Re-run any period whose cash figure came from the imported 2025 rows.",
     "High"],
    ["2", M("ContractMasterImport.php") + " line 72; " + M("ContractMasterImportService.php") + " rate type routine",
     "Loads Extract A's Fixed or Variable label as the loan's rate type (Variable becomes FLOATING). The ECL discount rate and time-phased ECL services read that field.",
     "Stop deriving the rate type from this label. Keep the delivered label in its own field as evidence. Derive the rate type from Interest Policy once it is extracted, and until then from observed repricing in the loan books. Correct the code comment that refers to 204 variable-rate facilities.",
     "High"],
    ["3", M("ContractTransactionImportService.php") + " lines 66 to 71; " + M("RemainingScheduleImportService.php") + "; " + M("ScheduleWorkflowService.php") + " comparison",
     "Loads Extract B's scheduled rows as a \"remaining schedule\" and compares their totals with our generated schedule from the earliest due date onward.",
     "Treat these rows as what they are: instalments not fully recovered inside the extract's date range, at full original amounts. Rename them accordingly. Limit the comparison to matching due dates inside that range, or switch it off until the full chart extract arrives. Never feed them into a calculation.",
     "Medium"],
    ["4", M("ContractTransactionImportService.php") + " lines 78 to 85",
     "Stores each actual row's unsigned total and the estimated principal and interest parts. We found no other code that reads those two parts.",
     "Add fields for the raw transaction type code, the signed amount, and a marker for an estimated split. Keep estimated parts out of every calculation.",
     "Medium"],
    ["5", M("GlInterestImportService.php"),
     "Skips yearly rows and repeats of the same loan, month and GL code, keeping the first one it meets. Its description calls the figure the interest income the ledger posted.",
     "Describe the figure as interest charged to loan accounts, not yet agreed to the income GL. Where a file holds several runs, keep the latest run and report how many runs were in the file.",
     "Medium"],
    ["6", M("MappedFileReader.php") + " date routine, lines 420 to 454",
     "Accepts Excel date serials, then the declared format, then falls back to flexible parsing.",
     "For MAIIC extracts, accept year-first dates only. Hold the file when a date column mixes real dates with text, which is exactly how the scrambled extracts arrive.",
     "Medium"],
    ["7", "GL reconciliation and coverage reporting",
     "Not traced in this review.",
     "Check that a loan with no row in the interest postings is reported as \"no posting found\" and not as zero interest. Keep three states apart: posted, verified zero, and not covered.",
     "Medium"],
    ["8", M("ContractMasterImportService.php") + " line 281",
     "Stores Extract A's disbursement list as text on the contract.",
     "Take drawdown dates and amounts from the ledger disbursement rows. Keep Extract A's list as the planned schedule only. On Ebenezer Midian the two differ by 24 days.",
     "Medium"],
    ["9", "Contract key in the import services",
     "Identifies a loan by account number. The sub-account is stored but is not part of the key.",
     "Key on account number and sub-account together. Every sub-account is 1 today, but the vendor says a restructure creates a new one.",
     "Low"],
    ["10", "Use of Extract A terms",
     "Loads rate, status and terms from a snapshot as if they were as at the snapshot date.",
     "Record them as at the run date. Do not load the repayment frequency from the 1 January 2025 snapshot.",
     "Low"],
], [0.04, 0.21, 0.26, 0.38, 0.11])
NOTE("File and line references are to the branch as it stood on 5 October 2026. Item 1 follows from the finding that interest "
     "rows are charges, which is inferred and still to be confirmed with signed postings (section 2).")

# ------------------------------------------------------------------ 14 sequence
H1("14. The sequence from here")
BUL([
    "MAIIC runs Tier 1 (runs 1 to 11) and sends the results. Runs 1 to 7 are enough for us to begin.",
    "MAIIC sends the procedure version history and the record of the two Extract A runs, and a line of explanation for each of the ten accounts in Appendix C.",
    "Dupleix drafts the amended extract scripts against the real table and column names (section 12), and makes the engine changes in section 13.",
    "MAIIC runs Tiers 2 and 3 when convenient. They refine the work but do not hold it up.",
    "MAIIC runs the amended extracts. Dupleix loads them and reconciles interest to the trial balance. That reconciliation has not yet been done.",
], numbered=True)

# ------------------------------------------------------------------ appendices
story.append(PageBreak())
H1("Appendix A. The tests behind this report")
P("Each statement about the delivered files was tested on the files themselves. These are the results. The scripts that "
  "produce them are in the Build files folder.")
TAB(["Test", "Result"], [
    ["Extract A: rows, accounts and snapshots", "362 rows; 181 accounts; two snapshots, 1 January 2025 and 31 December 2025. No repeated rows for the same account, sub-account and snapshot. Sub-account is 1 on every row"],
    ["Extract A: rate label by product (31 December 2025 snapshot)", "Fixed: MAIIC Agricultural 16, MAIIC Industrial 41, MAIIC Term Loan 13, Fines Investment Loan 4, Quasi Equity Investment 5. Variable: FInES Agricultural 50, FInES Industrial 51, NASCOMEX Limited 1"],
    ["Extract A: fields compared between the two snapshots", "Interest rate, account status, sanctioned amount, tenor, number of instalments, rate label, principal disbursed and tranche list: identical for all 181 accounts"],
    ["Extract A: repayment frequency in the two snapshots", "1 January 2025: Monthly 181. 31 December 2025: Monthly 139, Half-Yearly 10, Quarterly 4, Yearly 3, blank 25. 42 accounts differ"],
    ["Extract A: Lake Malawi Aquaculture, 31 December 2025 snapshot", "Sanctioned 1,055,473,655; one tranche of 297,161,905 dated 28 April 2026"],
    ["Extract A: principal disbursed against sanctioned amount", "Populated on 157 accounts; equal to the sanctioned amount on 138"],
    ["Extract A: entries in the tranche list", "Populated on 314 of 362 rows. One entry on 256 rows; two to ten entries on 58"],
    ["Extract A tranche list against ledger disbursements in Extract B", "35 accounts have ledger disbursements in 2025. Total amount agrees on 30; number of entries agrees on 28. Ebenezer Midian: 15 July 2025 in Extract A, 8 August 2025 in the ledger"],
    ["Extract A: first repayment date against loan start date", "The same on 63 of the 128 accounts with a first repayment date"],
    ["Extract A: accounts with no instalment chart rows", "53"],
    ["Extract A: account status (31 December 2025 snapshot)", "Active 86, Closed 46, Dormant 25, H 21, F 3"],
    ["Extract B: rows by kind", "2,478 rows, one run. Actual 1,703: Interest 1,034, Principal+Interest 539, Disbursement 97, Other/Adjustment 31, Fee 2. Scheduled 775, across 90 accounts"],
    ["Extract B: negative amounts", "None"],
    ["Extract B: direction of each row, from the change in the running balance", "Tested on every actual row after the first for its account. Interest: 987 raise the balance, 6 reduce it, 1 not placed. Principal+Interest: 485 reduce, 4 raise, 12 not placed. Disbursement: 63 raise, 1 not placed. Other/Adjustment: 18 raise, 9 reduce, 3 not placed. 16 rows in the file are out of posting order, which accounts for some of the rows not placed"],
    ["Extract B: Other/Adjustment rows", "31 rows totalling 832,653,206.42"],
    ["Extract B closing balance against Extract A balance at 31 December 2025", "111 accounts have both. 110 agree within MWK 1, taking the row with the highest posting number as the close. Exception: Zaithwa Farms, 1,225,683.03 against 3,053,316.65"],
    ["Extract B: scheduled rows", "0 of 775 carry a balance. Principal plus interest equals the total on 761 of 775"],
    ["Extract C: runs in the delivered file", "Run 7: 1,119 rows. Run 25: 1,119 rows. Run 41: 1,758 rows. No repeated loan, GL code and month within run 41"],
    ["Extract C: loan-months", "1,564 distinct loan-months; 1,012 appear three times and 552 once"],
    ["Extract C: run 7 against run 41 for 2025", "1,012 loan-months in both; amounts differ on none"],
    ["Extract C: 2025 interest", "All runs added together 7,663,172,090; run 41 alone 2,554,390,697"],
    ["Extract B interest rows against Extract C (run 41), 2025", "107 accounts in both. 101 agree exactly. Extract B total 2,639,557,380; difference 85,166,683.31"],
    ["Extract C: accounts in Extract A with no row in Extract C", "61: Closed 46, Active 10, Dormant 4, H 1"],
], [0.42, 0.58])

H1("Appendix B. The six accounts where Extract B and Extract C disagree")
P("For 2025, the interest rows in Extract B exceed the interest in Extract C on six accounts. On five of them the difference "
  "equals the account's \"Other/Adjustment\" rows in Extract B. On Happie Foods it equals one of the two such rows: the "
  "2,018,700.44 row reduces the balance, while the 7,000,000 row raises it. This agreement in amounts does not establish "
  "what the postings are. That needs the raw signed postings and the vendor's meaning for each transaction type.")
TAB(["Account", "Customer", "Extract B interest", "Extract B other", "Extract C interest", "Difference"], [
    ["000104430000068", "Nyamunyamu Processors Limited", "131,899,213.80", "69,330,809.54", "62,568,404.26", "69,330,809.54"],
    ["000104430000002", "Edge View Academy Limited", "117,296,781.00", "10,457,152.33", "106,839,628.67", "10,457,152.33"],
    ["000104430000073", "Saile Financial Services Limited Company", "90,522,529.35", "2,395,117.82", "88,127,411.53", "2,395,117.82"],
    ["000104420000062", "Happie Foods Limited", "38,421,398.75", "9,018,700.44", "36,402,698.31", "2,018,700.44"],
    ["000104420000052", "Kambewu Organic Fertilizer Investments", "42,329,779.82", "913,541.02", "41,416,238.80", "913,541.02"],
    ["000104420000032", "African Honey Products Industries", "29,893,579.18", "51,362.16", "29,842,217.02", "51,362.16"],
], [0.17, 0.27, 0.15, 0.13, 0.15, 0.13], keep=True)
NOTE("The six differences add up to MWK 85,166,683.31, which is the whole difference between the two extracts for 2025.")

H1("Appendix C. Active accounts with no interest posted in Extract C")
P("These ten accounts are marked Active in Extract A but have no interest row in Extract C between January 2025 and July "
  "2026. That is not the same as a verified zero. Some may not have been drawn yet, some are not loans, and for some the "
  "interest may be posted where the script does not look. Each one needs a line of explanation from MAIIC.")
TAB(["Account", "Customer", "Product", "Sanctioned amount, MWK"], [
    ["000104470000004", "NASCOMEX Limited", "NASCOMEX Limited", "2,500,000,000"],
    ["000104430000081", "Pinnacle Financial Services Limited", "MAIIC Industrial Loans", "1,000,000,000"],
    ["000104430000088", "Anchor Processors Limited", "MAIIC Industrial Loans", "300,000,000"],
    ["000104430000083", "JAT Group Ltd", "MAIIC Industrial Loans", "209,270,000"],
    ["000104460000111", "Ecogen Limited", "FInES Industrial Loans", "105,060,000"],
    ["000105810000004", "Ecogen Limited", "Fines Investment Loan", "105,060,000"],
    ["000105810000003", "Ecogen Limited", "Fines Investment Loan", "100,000,000"],
    ["000104440000005", "Belk Inc", "Quasi Equity Investment", "69,000,000"],
    ["000104420000003", "Micholess Creamery", "MAIIC Agricultural Loans", "20,000,000"],
    ["000104450000012", "Maluso Cooperative Union", "FInES Agricultural Loans", "11,511,500"],
], [0.20, 0.36, 0.24, 0.20], keep=True)

H1("Appendix D. Terms used in this report")
TAB(["Term", "Meaning"], [
    ["Script, stored procedure", "A set of database instructions saved inside the database and run on request."],
    ["Query", "A question put to the database. A read-only query returns data and changes nothing."],
    ["Table, column", "A table is a list of records of one kind, such as accounts. A column is one field in that list, such as the interest rate."],
    ["Data dictionary, catalogue", "The database's own list of its tables and columns."],
    ["Schema, owner", "A named area of the database that holds a set of tables. Two schemas can hold tables with the same name."],
    ["Scheme", "In E-Banker, the product variant an account is opened on. Settings such as Interest Policy are held on the scheme."],
    ["GL code", "The general ledger account a posting is made to. Each loan product has its own."],
    ["Transaction type code", "A number E-Banker gives each posting to say what kind it is, such as 301 for a disbursement."],
    ["Run number", "A number the script gives each time it is run, so that the results of different runs can be told apart."],
    ["Snapshot, as at date", "The position on a stated date, as opposed to the position today."],
    ["Instalment chart, EMI chart", "The loan's repayment schedule as held in E-Banker."],
    ["Effective interest rate (EIR)", "The rate that spreads all of a loan's cash flows, including fees, evenly over its life. It is what IFRS 9 requires interest income to be recognised at."],
    ["Engine", "The Dupleix software that calculates the effective interest rate and the related income for MAIIC."],
], [0.28, 0.72], bold_first=True)

H1("Appendix E. The independent review, and what changed in version 2")
P("The first version of this report, the query pack and the record of the working session were reviewed on 5 October 2026 "
  "by a second AI system (Codex). The reviewer re-ran our main tests on the delivered files and reproduced the figures. It "
  "raised eight corrections. We accept all eight. The review is filed in the engine's repository under "
  + M("docs/reviews") + ".")
TAB(["#", "The review's comment", "Our response", "What changed"], [
    ["1", "Scheduled rows are not the unpaid remainder. The script exports full original amounts and does not subtract the amount recovered.", "Accepted. Our wording was wrong.", "Section 5, row 6; section 12; section 13, item 3."],
    ["2", "The running balance is not proven correct. It depends on complete history, the opening position, posting scope and agreement with independent balances.", "Accepted. We tested it: 110 of 111 accounts agree with Extract A at year end.", "Section 5, first bullet; Appendix A."],
    ["3", "No interest rows is not a verified zero.", "Accepted.", "Section 6, row 4; section 9; Appendix C; section 13, item 7."],
    ["4", "Final-day date handling should be checked. Dates with a time of day could drop last-day postings.", "Accepted. A new query tests it.", "Sections 4 to 6; run 11 (DD_19); section 12."],
    ["5", "Agreement in total does not establish what the adjustments are. Happie Foods leaves MWK 7 million unexplained.", "Accepted, and it goes further: the \"Other/Adjustment\" rows total MWK 832.7 million and most do not reduce interest.", "Section 5, row 4; Appendix B."],
    ["6", "Schema and privilege assumptions. Several schemas may hold ACMASTER; running a procedure does not prove read access.", "Accepted.", "Owner added to every catalogue result; runs 2 and 3 (DD_17, DD_18)."],
    ["7", "The cause of the repayment-frequency differences is not established.", "Accepted. We now state the facts and three possible causes, and have asked for the run history.", "Section 4, closing paragraphs; section 9."],
    ["8", "Define the export contract. CSV alone does not protect dates or account numbers.", "Accepted.", "Section 7; the header of both SQL files; session settings at run 0."],
], [0.04, 0.40, 0.30, 0.26])
P("The review also made three points about our engine's code, and our own reading added more. These are in section 13. "
  "Two of our findings went beyond the review: the engine counts Extract B interest rows as cash collected, and it stores "
  "the rate type from the label that appears to be inverted.")
P("As the review asked, this version separates what was tested from what is inferred and what is pending (section 2), and "
  "states that it is not a completed income reconciliation or a validated cash flow schedule.")


# ------------------------------------------------------------------ build
def footer(canvas, doc):
    canvas.saveState()
    canvas.setStrokeColor(LINE); canvas.setLineWidth(0.5)
    canvas.line(18 * mm, 14 * mm, A4[0] - 18 * mm, 14 * mm)
    canvas.setFont("Seg", 8); canvas.setFillColor(GREY)
    canvas.drawString(18 * mm, 9.5 * mm, "MAIIC E-Banker extract scripts: analysis  ·  Dupleix Institute  ·  5 October 2026  ·  Version 2")
    canvas.drawRightString(A4[0] - 18 * mm, 9.5 * mm, f"Page {doc.page}")
    canvas.restoreState()


OUT = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC\MAIIC_Raw_Query_Scripts_Analysis_2026-10-05.pdf"
doc = BaseDocTemplate(OUT, pagesize=A4, leftMargin=18 * mm, rightMargin=18 * mm, topMargin=16 * mm, bottomMargin=20 * mm,
                      title="MAIIC E-Banker extract scripts: analysis (version 2)", author="Dupleix Institute",
                      subject="What the three raw query scripts do, where they fall short, and which queries to run next")
doc.addPageTemplates([PageTemplate(id="p", frames=[Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id="f",
                                                         leftPadding=0, rightPadding=0, topPadding=0, bottomPadding=0)], onPage=footer)])
doc.build(story)
print("written", OUT)
