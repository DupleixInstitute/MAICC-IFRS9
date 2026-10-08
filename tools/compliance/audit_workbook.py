"""Compliance audit workbooks - one engine for every standard and directive (spec v4 section 12).

Ported from the Dupleix suite's tools/compliance/audit_workbook.py with the two MAIIC columns
(the governance setting that governs each row, the test that proves it) and the Baselines sheet
(the acceptance ties of spec section 9, read from the system at generation).

Each standard is a data module in tools/compliance/standards/ exposing META, ROWS, FINDINGS and
BASELINES. This module turns that data into the three deliverables that can never drift:
  * an Excel workbook: 'Contents' in the standard's own order of sections, each hyperlinked to
    its row on 'Audit'; 'Audit' with the eleven columns, a status drop-down, colour coding and a
    flag on a Done row with no test; 'Findings'; 'Baselines' with a live PASS/FAIL formula;
  * a Markdown twin generated from the same rows;
  * a PDF of the Markdown (tools/compliance/md_to_pdf.py).

ROWS:      (part, ref, name, requirement, status, engine_comment, general_comment,
            compliance_comment, where_to_see, governance_setting, test)
FINDINGS:  (no, ref, finding, what_was_found, impact, recommended_action, owner, status)
BASELINES: list of dicts from `php artisan eir:baselines --json`, or the module's own
           (what, expected, actual, ok)
"""
from openpyxl import Workbook
from openpyxl.styles import Font, Alignment, PatternFill, Border, Side
from openpyxl.utils import get_column_letter
from openpyxl.worksheet.datavalidation import DataValidation
from openpyxl.worksheet.hyperlink import Hyperlink
from openpyxl.formatting.rule import FormulaRule

DONE = "Done"
PART = "Partially done"
OUT_ = "Outstanding"
NA = "Not applicable, documented"
EVID = "Evidence needed from MAIIC"
STATUSES = [DONE, PART, OUT_, NA, EVID]
STATUS_FILL = {DONE: "C6EFCE", PART: "FFEB9C", OUT_: "FFC7CE", NA: "D9D9D9", EVID: "DDEBF7"}
STATUS_DEFS = {
    DONE: "Implemented in the system and visible in an approved output; the test in column 11 fails if the behaviour changes.",
    PART: "The mechanics are in place, but a parameter, an input or a piece of evidence still differs from the requirement.",
    OUT_: "Required for MAIIC and not built.",
    NA: "Does not apply to MAIIC by a recorded decision or fact (reason and reference given); revisit if the facts change.",
    EVID: "The system is ready; MAIIC must supply a document, minute or dataset before the row can be closed.",
}
CONTEXT = "Malawi Agricultural and Industrial Investment Company (MAIIC) - IFRS 9 EIR and ECL system - build of 8 October 2026 against specification v4."
FONT = "Arial"
GOLD = "B8860B"; PRIMARY = "0B2B1A"; CREAM = "FBF0E4"; GREY = "595959"; LINK = "0563C1"
_thin = Side(style="thin", color="BFBFBF")
BORDER = Border(left=_thin, right=_thin, top=_thin, bottom=_thin)
HEAD = ["Reference", "Section name", "What it requires", "Status", "What the engine does (evidence checked)",
        "General comment", "Compliance comment", "Where to see it (screen; report or export)",
        "Reviewer sign-off (initials / date)", "Governance setting", "Test that proves it"]
WIDTHS = [12, 30, 56, 22, 58, 36, 42, 46, 20, 30, 44]
NCOL = len(HEAD)


def _link(cell, sheet, target):
    cell.hyperlink = Hyperlink(ref=cell.coordinate, location=f"'{sheet}'!{target}", display=str(cell.value))
    cell.font = Font(name=FONT, size=9, color=LINK, underline="single")


def _header(ws, row, labels, widths):
    for i, (h, w) in enumerate(zip(labels, widths), start=1):
        c = ws.cell(row=row, column=i, value=h)
        c.font = Font(name=FONT, bold=True, size=10, color="FFFFFF")
        c.fill = PatternFill("solid", fgColor=PRIMARY)
        c.alignment = Alignment(wrap_text=True, vertical="center")
        c.border = BORDER
        ws.column_dimensions[get_column_letter(i)].width = w


def validate(meta, rows, findings, baselines):
    """Refuse to write a misleading workbook: eleven fields, a known status, no duplicate reference,
    a reason on every Not applicable, a test on every Done."""
    refs = set()
    for r in rows:
        assert len(r) == NCOL, f"row needs {NCOL} fields: {r[:3]}"
        assert r[4] in STATUSES, f"unknown status {r[4]!r} on {r[1]}"
        assert r[1] not in refs, f"duplicate ref {r[1]}"
        refs.add(r[1])
        if r[4] == NA:
            assert r[5].strip() or r[6].strip(), f"Not applicable without a reason on {r[1]}"
        if r[4] == DONE:
            assert r[10].strip(), f"Done without a test that proves it on {r[1]}"
    for f in findings:
        assert len(f) == 8, f"finding needs 8 fields: {f[:2]}"
    for b in baselines:
        for k in ("what", "expected", "actual"):
            assert k in b, f"baseline needs {k}: {b}"
    for k in ("file_stem", "short", "title", "basis", "source", "reviewer"):
        assert meta.get(k), f"META.{k} missing"


def build_xlsx(meta, rows, findings, baselines, out):
    validate(meta, rows, findings, baselines)
    wb = Workbook()

    # Audit
    ws = wb.active
    ws.title = "Audit"
    ws["A1"] = meta["title"] + " - compliance audit"
    ws["A1"].font = Font(name=FONT, size=13, bold=True, color=PRIMARY)
    ws["A2"] = f"{CONTEXT} {meta['basis']} Reviewer: {meta['reviewer']}. Status definitions: see 'Contents'."
    ws["A2"].font = Font(name=FONT, size=9, italic=True, color=GREY)
    ws["A2"].alignment = Alignment(wrap_text=True, vertical="top")
    ws.merge_cells(start_row=2, start_column=1, end_row=2, end_column=NCOL)
    ws.row_dimensions[2].height = 30
    _link(ws.cell(row=3, column=1, value="Back to Contents"), "Contents", "A1")
    hr = 4
    _header(ws, hr, HEAD, WIDTHS)
    ws.row_dimensions[hr].height = 34
    row = hr + 1
    part_rows, ref_rows = {}, {}
    for part, ref, name, req, status, engine, general, comp, where, setting, test in rows:
        if part not in part_rows:
            ws.cell(row=row, column=1, value=part).font = Font(name=FONT, bold=True, size=10, color=PRIMARY)
            for col in range(1, NCOL + 1):
                ws.cell(row=row, column=col).fill = PatternFill("solid", fgColor=CREAM)
            ws.merge_cells(start_row=row, start_column=1, end_row=row, end_column=NCOL)
            part_rows[part] = row
            row += 1
        for col, v in enumerate([ref, name, req, status, engine, general, comp, where, "", setting, test], start=1):
            c = ws.cell(row=row, column=col, value=v)
            c.font = Font(name=FONT, size=9, bold=(col == 4))
            c.alignment = Alignment(wrap_text=True, vertical="top")
            c.border = BORDER
        ref_rows[ref] = row
        row += 1
    last = row - 1
    ws.freeze_panes = "C5"
    ws.auto_filter.ref = f"A{hr}:{get_column_letter(NCOL)}{last}"
    dv = DataValidation(type="list", formula1='"' + ",".join(STATUSES) + '"', allow_blank=True)
    ws.add_data_validation(dv)
    dv.add(f"D{hr + 1}:D{last}")
    for st, colr in STATUS_FILL.items():
        ws.conditional_formatting.add(f"D{hr + 1}:D{last}", FormulaRule(formula=[f'$D{hr + 1}="{st}"'], fill=PatternFill("solid", fgColor=colr)))
    # a Done with no test named is flagged in red on the test column
    ws.conditional_formatting.add(f"K{hr + 1}:K{last}", FormulaRule(formula=[f'AND($D{hr + 1}="{DONE}",$K{hr + 1}="")'], fill=PatternFill("solid", fgColor="FFC7CE")))
    ws.sheet_properties.tabColor = PRIMARY

    # Contents
    toc = wb.create_sheet("Contents", 0)
    toc.sheet_properties.tabColor = GOLD
    toc["A1"] = meta["short"] + " - compliance audit"
    toc["A1"].font = Font(name=FONT, size=14, bold=True, color=PRIMARY)
    toc["A2"] = "Contents (the standard's own order of sections). Click a section to open its row on the Audit sheet."
    toc["A2"].font = Font(name=FONT, size=9, italic=True, color=GREY)
    toc["A4"] = "Status definitions"; toc["A4"].font = Font(name=FONT, bold=True, size=10, color=PRIMARY)
    toc["C4"] = "Sections"; toc["C4"].font = Font(name=FONT, bold=True, size=10, color=PRIMARY); toc["C4"].alignment = Alignment(horizontal="center")
    r = 5
    for st in STATUSES:
        c = toc.cell(row=r, column=1, value=st); c.font = Font(name=FONT, size=9, bold=True); c.fill = PatternFill("solid", fgColor=STATUS_FILL[st]); c.border = BORDER
        c = toc.cell(row=r, column=2, value=STATUS_DEFS[st]); c.font = Font(name=FONT, size=9); c.alignment = Alignment(wrap_text=True, vertical="top")
        c = toc.cell(row=r, column=3, value=f'=COUNTIF(Audit!$D:$D,"{st}")'); c.font = Font(name=FONT, size=9, bold=True); c.alignment = Alignment(horizontal="center", vertical="top")
        toc.row_dimensions[r].height = 26
        r += 1
    toc.cell(row=r, column=1, value="Sections audited").font = Font(name=FONT, size=9, bold=True)
    c = toc.cell(row=r, column=3, value=f"=COUNTA(Audit!$A$5:$A${last})-{len(part_rows)}"); c.font = Font(name=FONT, size=9, bold=True); c.alignment = Alignment(horizontal="center")
    r += 1
    toc.cell(row=r, column=1, value="Baselines passing").font = Font(name=FONT, size=9, bold=True)
    c = toc.cell(row=r, column=3, value='=COUNTIF(Baselines!$E:$E,"PASS")&" of "&COUNTA(Baselines!$A$5:$A$500)'); c.font = Font(name=FONT, size=9, bold=True); c.alignment = Alignment(horizontal="center")
    r += 2
    _header(toc, r, ["Ref", "Section", "Status", "Open in Audit"], [30, 62, 30, 16])
    first_toc = r + 1
    r += 1
    seen = set()
    for part, ref, name, *_ in rows:
        if part not in seen:
            seen.add(part)
            toc.cell(row=r, column=1, value=part).font = Font(name=FONT, bold=True, size=10, color=PRIMARY)
            for col in range(1, 5):
                toc.cell(row=r, column=col).fill = PatternFill("solid", fgColor=CREAM)
            toc.merge_cells(start_row=r, start_column=1, end_row=r, end_column=4)
            r += 1
        ar = ref_rows[ref]
        c = toc.cell(row=r, column=1, value=ref); c.font = Font(name=FONT, size=9); c.border = BORDER
        c = toc.cell(row=r, column=2, value=name); _link(c, "Audit", f"A{ar}"); c.border = BORDER
        c = toc.cell(row=r, column=3, value=f"=Audit!D{ar}"); c.font = Font(name=FONT, size=9); c.border = BORDER
        c = toc.cell(row=r, column=4, value=f"Audit row {ar}"); _link(c, "Audit", f"A{ar}"); c.border = BORDER
        r += 1
    for st, colr in STATUS_FILL.items():
        toc.conditional_formatting.add(f"C{first_toc}:C{r}", FormulaRule(formula=[f'$C{first_toc}="{st}"'], fill=PatternFill("solid", fgColor=colr)))
    toc.freeze_panes = "A3"
    r += 1
    _link(toc.cell(row=r, column=1, value="Findings that need a decision or a fix: see the 'Findings' sheet"), "Findings", "A1")
    r += 1
    _link(toc.cell(row=r, column=1, value="The acceptance ties read from the system: see the 'Baselines' sheet"), "Baselines", "A1")
    r += 1
    c = toc.cell(row=r, column=1, value="Source: " + meta["source"]); c.font = Font(name=FONT, size=8, italic=True, color=GREY)

    # Findings
    fs = wb.create_sheet("Findings")
    fs.sheet_properties.tabColor = "C00000"
    fs["A1"] = "Findings - items that need a decision or a fix before this standard can be signed off"
    fs["A1"].font = Font(name=FONT, size=13, bold=True, color=PRIMARY)
    _link(fs.cell(row=2, column=1, value="Back to Contents"), "Contents", "A1")
    _header(fs, 4, ["No.", "Reference", "Finding", "What was found", "Impact", "Recommended action", "Owner", "Status"], [6, 16, 30, 64, 40, 64, 26, 12])
    for j, f in enumerate(findings, start=5):
        for i, v in enumerate(f, start=1):
            c = fs.cell(row=j, column=i, value=v); c.font = Font(name=FONT, size=9); c.alignment = Alignment(wrap_text=True, vertical="top"); c.border = BORDER
            if i == 2:
                first = str(v).split(",")[0].split("(")[0].strip()
                if first in ref_rows:
                    _link(c, "Audit", f"A{ref_rows[first]}")
    fs.freeze_panes = "A5"
    if findings:
        dv2 = DataValidation(type="list", formula1='"Open,In progress,Closed,Deferred"', allow_blank=True)
        fs.add_data_validation(dv2)
        dv2.add(f"H5:H{4 + len(findings)}")

    # Baselines: expected, the system's figure at generation, a live PASS/FAIL formula
    bs = wb.create_sheet("Baselines")
    bs.sheet_properties.tabColor = "2E75B6"
    bs["A1"] = "Baselines - the acceptance ties of specification section 9, read from the system at generation"
    bs["A1"].font = Font(name=FONT, size=13, bold=True, color=PRIMARY)
    _link(bs.cell(row=2, column=1, value="Back to Contents"), "Contents", "A1")
    bs["A3"] = f"Generated {meta.get('generated_at', '')} from {meta.get('database', 'the database named in META')}. The PASS/FAIL in column E is a formula on columns B and C, so it recomputes if a figure is typed over."
    bs["A3"].font = Font(name=FONT, size=9, italic=True, color=GREY)
    _header(bs, 4, ["Tie", "Expected", "System figure at generation", "Tolerance", "Result", "Note"], [64, 22, 24, 12, 10, 50])
    for j, b in enumerate(baselines, start=5):
        exp = b["expected"]; act = b["actual"]
        tol = b.get("tolerance", 0.01)
        for i, v in enumerate([b["what"], exp, act, tol], start=1):
            c = bs.cell(row=j, column=i, value=v); c.font = Font(name=FONT, size=9); c.alignment = Alignment(wrap_text=True, vertical="top"); c.border = BORDER
            if i in (2, 3) and isinstance(v, (int, float)):
                c.number_format = "#,##0.00"
        if isinstance(exp, (int, float)) and isinstance(act, (int, float)):
            formula = f'=IF(ABS(B{j}-C{j})<=D{j},"PASS","FAIL")'
        else:
            formula = f'=IF(B{j}=C{j},"PASS","FAIL")'
        c = bs.cell(row=j, column=5, value=formula); c.font = Font(name=FONT, size=9, bold=True); c.border = BORDER
        c = bs.cell(row=j, column=6, value=b.get("note", "")); c.font = Font(name=FONT, size=9); c.alignment = Alignment(wrap_text=True, vertical="top"); c.border = BORDER
    lastb = 4 + len(baselines)
    if baselines:
        bs.conditional_formatting.add(f"E5:E{lastb}", FormulaRule(formula=['$E5="PASS"'], fill=PatternFill("solid", fgColor=STATUS_FILL[DONE])))
        bs.conditional_formatting.add(f"E5:E{lastb}", FormulaRule(formula=['$E5="FAIL"'], fill=PatternFill("solid", fgColor=STATUS_FILL[OUT_])))
    bs.freeze_panes = "A5"

    for sh in (toc, ws, fs, bs):
        sh.sheet_view.showGridLines = False
        sh.page_setup.orientation = "landscape"
        sh.page_setup.fitToWidth = 1
        sh.page_setup.fitToHeight = 0
        sh.sheet_properties.pageSetUpPr.fitToPage = True
    ws.print_title_rows = "4:4"
    fs.print_title_rows = "4:4"
    wb.calculation.fullCalcOnLoad = True
    wb.save(out)
    return ref_rows


def build_markdown(meta, rows, findings, baselines, out):
    counts = {st: sum(1 for r in rows if r[4] == st) for st in STATUSES}
    L = [f"# {meta['short']} - compliance audit", "",
         f"{meta['title']}.", "",
         f"{CONTEXT} {meta['basis']} Reviewer: {meta['reviewer']}. The Excel workbook of the same name is the working copy "
         "(drop-down statuses, hyperlinked contents, reviewer sign-off column, a live Baselines sheet); this document is its printable twin.", "",
         f"Source: {meta['source']}", "",
         "## Status summary", "",
         "| Status | Meaning | Sections |", "|---|---|---|"]
    for st in STATUSES:
        L.append(f"| **{st}** | {STATUS_DEFS[st]} | {counts[st]} |")
    L += [f"| **Total** | | {len(rows)} |", "", "## Contents", "", "| Ref | Section | Status | Governance setting | Test |", "|---|---|---|---|---|"]
    part = None
    for p, ref, name, req, status, engine, general, comp, where, setting, test in rows:
        if p != part:
            L.append(f"| **{p}** | | | | |")
            part = p
        L.append(f"| {ref} | {name} | {status} | {setting or ''} | {test or ''} |")
    L += ["", "## Baselines - the acceptance ties read from the system", "", f"Generated {meta.get('generated_at', '')} from {meta.get('database', 'the database')}.", "",
          "| Tie | Expected | System figure | Result |", "|---|---|---|---|"]
    for b in baselines:
        fmt = lambda v: f"{v:,.2f}" if isinstance(v, (int, float)) else str(v)
        L.append(f"| {b['what']} | {fmt(b['expected'])} | {fmt(b['actual'])} | {'PASS' if b.get('ok') else 'FAIL'} |")
    L += ["", "## Findings - items that need a decision or a fix", ""]
    if not findings:
        L += ["No findings.", ""]
    for no, ref, title, found, impact, action, owner, status in findings:
        L += [f"### {no}. {title} ({ref}) - {status}", "",
              f"- **What was found:** {found}", f"- **Impact:** {impact}",
              f"- **Recommended action:** {action}", f"- **Owner:** {owner}", ""]
    L.append("## Section-by-section audit")
    part = None
    for p, ref, name, req, status, engine, general, comp, where, setting, test in rows:
        if p != part:
            L += ["", f"### {p}"]
            part = p
        L += ["", f"**{ref}. {name}** - *{status}*", "",
              f"- **What it requires:** {req}", f"- **What the engine does:** {engine}"]
        if general:
            L.append(f"- **General comment:** {general}")
        L += [f"- **Compliance comment:** {comp}", f"- **Where to see it:** {where}"]
        if setting:
            L.append(f"- **Governance setting:** `{setting}`")
        if test:
            L.append(f"- **Test that proves it:** `{test}`")
        L.append("- **Reviewer sign-off:** ____________________   Date: ____________")
    L.append("")
    with open(out, "w", encoding="utf-8", newline="\n") as fh:
        fh.write("\n".join(L))
