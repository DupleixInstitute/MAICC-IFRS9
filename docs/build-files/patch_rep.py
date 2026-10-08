p = "takeon_report.py"; s = open(p, encoding="utf-8").read()
def rep(old, new):
    global s
    assert s.count(old) == 1, old[:60]; s = s.replace(old, new)
rep('''companion to the workbook \\"Take-on mapping for Tamanda to confirm - 7 Oct 2026.xlsx\\"''',
    '''companion to the workbook \\"Take-on schedules with mapping - for Tamanda to confirm - 7 Oct 2026.xlsx\\"''')
rep('''H1("3. The upload sheet")
P("The sheet \\"Upload summary\\" in the workbook has one row per facility (109) with, in this order: the proposed E-Banker account and the confidence; the "
  "workbook's own terms (type, value date, maturity date, tenor, moratorium text, rate, approved, disbursed, not yet disbursed, principal, interest to date, "
  "repayments, carrying amount, arrears and ageing, segment, industry); the matching E-Banker fields (name, scheme, GL code, status, interest policy, rate now, "
  "sanction amount and date, expiry, principal and interest moratorium periods, the take-on posting and the balance at 31 October 2024); and the block number, "
  "its rate steps, periods and restructure flag.")
BUL(["Account numbers are text with their fifteen digits and leading zeros. Dates are real dates. Rates are shown as percentages: the workbook mixes fractions (0.3067) with percentages (10), and the sheet puts both on the same footing.",
     "Rows with no account (the four not matched) and the equity rows are kept on the sheet, flagged by the Type and Confidence columns, so that nothing is silently dropped.",
     "The sheet is a proposal until Tamanda has ticked the mapping. The loader should refuse any row whose confirmation column is not Y."])''',
'''H1("3. The workbook: Tamanda's file with our sheets added, linked so it can be audited")
P("We did not build a separate spreadsheet. We took a copy of Tamanda's own workbook of 11 September and added our sheets to it, so that every figure we show "
  "can be traced to the cell it came from. Her two sheets, \\"Loan Book\\" and \\"Amortisation and Repayments\\", are unchanged apart from the purple additions "
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
P("So that the mapping is visible where she works, eight columns were added to the right of the last column on \\"Loan Book\\", and four cells beside the title "
  "of every block on \\"Amortisation and Repayments\\": the proposed E-Banker account, the facility, the take-on posting of 31 July 2024, the opening interest "
  "charge, E-Banker's balance at 31 October 2024, and the difference between her carrying amount and that balance. They are formatted white on purple, which "
  "means \\"added by Dupleix\\". They are formulas into the new sheets, and none of her own cells has been edited. If she corrects a figure in her sheets, every "
  "linked cell follows.")
BUL(["Account numbers are text with their fifteen digits and leading zeros. Dates are real dates. Rates are shown as percentages: the workbook mixes fractions (0.3067) with percentages (10), and the formulas put both on the same footing.",
     "Rows with no account (the four not matched) and the equity rows stay on the sheets, flagged by Type and Confidence, so that nothing is silently dropped.",
     "The workbook is a proposal until Tamanda has ticked the mapping. The loader should refuse any row whose confirmation column is not Y."])''')
rep('''BUL(["<b>The mapping as a whole:</b> tick Y against each of the 105 proposed accounts on the \\"Proposed mapping\\" sheet, or give the right account number where N.",''',
    '''BUL(["<b>The mapping as a whole:</b> tick Y against each of the 105 proposed accounts on the \\"Mapping\\" sheet, or give the right account number where N. The purple columns on her own Loan Book show the same proposals in place.",''')
rep('''P("Each of these is a tick or a one-line answer on the workbook. Nothing has to be built.")''',
    '''P("Each of these is a tick or a one-line answer on the workbook. Nothing has to be built, and because every figure is linked to its source cell, each answer can be checked against the schedule it came from.")''')
open(p, "w", encoding="utf-8").write(s); print("patched")
