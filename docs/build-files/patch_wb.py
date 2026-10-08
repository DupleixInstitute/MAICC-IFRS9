s = open("build_takeon_workbook.py", encoding="utf-8").read()
def rep(old, new):
    global s
    assert s.count(old) == 1, old[:60]; s = s.replace(old, new)
rep("    rec = mp.loc[(num, typ)] if (num, typ) in mp.index else None",
    "    rec = mp.loc[[(num, typ)]].iloc[0] if (num, typ) in mp.index else None")
# purple additions on Tamanda's own sheets
rep("# ------------------------------------------------------------------ Instructions and TOC",
'''# ------------------------------------------------------------------ Dupleix columns on Tamanda's own sheets, white on purple
PURPLE = PatternFill("solid", fgColor="7030A0"); PFONT = Font(bold=True, color="FFFFFF"); PFONT2 = Font(color="FFFFFF")
def lookup_map(col_letter, lbrow_ref):
    return f'IFERROR(INDEX(\'{MAP}\'!${col_letter}$2:${col_letter}${MAPN},MATCH({lbrow_ref},\'{MAP}\'!$A$2:$A${MAPN},0)),"")'
c0 = ws_lb.max_column + 2
add = ["E-Banker account (Dupleix, proposed)", "Confidence", "E-Banker name", "E-Banker status", "Take-on posting 31 Jul 2024", "Opening interest charge 31 Jul 2024", "E-Banker balance 31 Oct 2024", "Carrying amount less E-Banker balance"]
ws_lb.cell(2, c0, "Added by Dupleix, 7 October 2026: linked to the 'Mapping' and 'E-Banker master' sheets. Your columns to the left are unchanged.").font = Font(bold=True, color="7030A0")
for j, h in enumerate(add):
    c = ws_lb.cell(3, c0 + j, h); c.fill = PURPLE; c.font = PFONT; c.alignment = Alignment(wrap_text=True, vertical="top")
    ws_lb.column_dimensions[get_column_letter(c0 + j)].width = 20
for (r, num, name, typ) in lb_rows:
    acc = f"${get_column_letter(c0)}{r}"
    vals = [f"={lookup_map('J', r)}", f"={lookup_map('K', r)}", f"={eb('Name', acc)}", f"={eb('Status', acc)}", f"={eb('Take-on posting 31 Jul 2024', acc)}", f"={eb('Opening interest 31 Jul 2024', acc)}", f"={eb('Balance 31 Oct 2024', acc)}",
            f'=IF(OR({acc}="",{get_column_letter(c0 + 6)}{r}=""),"",R{r}-{get_column_letter(c0 + 6)}{r})']
    for j, v in enumerate(vals):
        c = ws_lb.cell(r, c0 + j, v); c.fill = PURPLE; c.font = PFONT2
        if j >= 4: c.number_format = "#,##0.00"
ws_lb.row_dimensions[3].height = 45
am_c0 = 18    # column R onward on the amortisation sheet, clear of the rate-step columns
for i, b in blocks.iterrows():
    t = trow[i]; bno = int(b["block"])
    labels = ["E-Banker account (Dupleix)", "Facility (Loan Book)", "Take-on posting 31 Jul 2024", "E-Banker balance 31 Oct 2024"]
    accf = f"IFERROR(INDEX(\'{BL}\'!$I$2:$I${len(blocks) + 1},MATCH({bno},\'{BL}\'!$A$2:$A${len(blocks) + 1},0)),\\"\\")"
    vals = [f"={accf}", f"=IFERROR(INDEX(\'{BL}\'!$H$2:$H${len(blocks) + 1},MATCH({bno},\'{BL}\'!$A$2:$A${len(blocks) + 1},0)),\\"\\")",
            f"={eb('Take-on posting 31 Jul 2024', get_column_letter(am_c0 + 1) + str(t))}", f"={eb('Balance 31 Oct 2024', get_column_letter(am_c0 + 1) + str(t))}"]
    for j, (lab, v) in enumerate(zip(labels, vals)):
        c1 = ws_am.cell(t + j, am_c0, lab); c1.fill = PURPLE; c1.font = PFONT
        c2 = ws_am.cell(t + j, am_c0 + 1, v); c2.fill = PURPLE; c2.font = PFONT2
        if j >= 2: c2.number_format = "#,##0.00"
ws_am.column_dimensions[get_column_letter(am_c0)].width = 30; ws_am.column_dimensions[get_column_letter(am_c0 + 1)].width = 22
ws_am.cell(1, am_c0, "Purple cells were added by Dupleix on 7 October 2026 and are linked to the 'Blocks' and 'E-Banker master' sheets. Nothing else on this sheet was changed.").font = Font(bold=True, color="7030A0")

# ------------------------------------------------------------------ Instructions and TOC''')
rep('''    ("Three things are typed in rather than computed, and are labelled: the proposed account number and confidence on 'Mapping' (our matching), the name-similarity flag, and the statistics parsed from each block on 'Blocks' (restructured, rate steps, periods, dates, closing balance).", False),''',
'''    ("Three things are typed in rather than computed, and are labelled: the proposed account number and confidence on 'Mapping' (our matching), the name-similarity flag, and the statistics parsed from each block on 'Blocks' (restructured, rate steps, periods, dates, closing balance).", False),
    ("", False),
    ("The purple cells on your two sheets", True),
    ("On 'Loan Book', eight columns have been added to the right of your last column, in white on purple: the proposed E-Banker account, the confidence, the E-Banker name and status, the take-on posting and opening interest of 31 July 2024, E-Banker's balance at 31 October 2024, and the difference between your carrying amount and that balance. On 'Amortisation and Repayments', four purple cells sit beside each block title: the account, the facility, the take-on posting and the balance. All of them are formulas into the new sheets. Purple means 'added by Dupleix'; none of your own cells has been edited.", False),''')
open("build_takeon_workbook.py", "w", encoding="utf-8").write(s); print("patched")
