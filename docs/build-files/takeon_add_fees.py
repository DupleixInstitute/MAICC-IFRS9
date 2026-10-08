"""Add blank fee columns for Tamanda to the Upload summary sheet of the take-on mapping workbook, with instructions."""
from openpyxl import load_workbook
from openpyxl.styles import PatternFill, Font, Alignment, Border, Side
from openpyxl.worksheet.datavalidation import DataValidation
from openpyxl.utils import get_column_letter

P = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC\Take-on schedules with mapping - for Tamanda to confirm - 7 Oct 2026.xlsx"
wb = load_workbook(P)
ws = wb["Upload summary"]
assert ws["AN1"].value == "Tamanda confirmed (from Mapping)", ws["AN1"].value
last = ws.max_row  # 112

NAVY = PatternFill("solid", fgColor="1B2A41"); YELLOW = PatternFill("solid", fgColor="FFF2CC"); GOLD = PatternFill("solid", fgColor="BF8F00")
WHITE_B = Font(name="Calibri", bold=True, color="FFFFFF", size=11)
thin = Side(style="thin", color="BFBFBF"); BOX = Border(left=thin, right=thin, top=thin, bottom=thin)

cols = [
    ("AO", "Arrangement fee (MK)", 20, "#,##0.00"),
    ("AP", "Legal fees (MK)", 18, "#,##0.00"),
    ("AQ", "Other integral fees (MK)", 22, "#,##0.00"),
    ("AR", "Fee date (dd/mm/yyyy)", 20, "dd/mm/yyyy"),
    ("AS", "Deducted from disbursement? (Y/N)", 24, "@"),
    ("AT", "Source (offer letter ref or receipt)", 30, "@"),
    ("AU", "Total fees (MK)", 18, "#,##0.00"),
]
for col, head, width, fmt in cols:
    c = ws[f"{col}1"]; c.value = head; c.fill = GOLD if col != "AU" else NAVY; c.font = WHITE_B
    c.alignment = Alignment(wrap_text=True, vertical="center"); c.border = BOX
    ws.column_dimensions[col].width = width
    for r in range(2, last + 1):
        cell = ws[f"{col}{r}"]; cell.border = BOX; cell.number_format = fmt
        if col == "AU":
            cell.value = f'=IF(COUNT(AO{r}:AQ{r})=0,"",SUM(AO{r}:AQ{r}))'
        else:
            cell.fill = YELLOW
ws.row_dimensions[1].height = max(ws.row_dimensions[1].height or 15, 45)

dv = DataValidation(type="list", formula1='"Y,N"', allow_blank=True, showErrorMessage=True, errorTitle="Y or N", error="Type Y or N")
ws.add_data_validation(dv); dv.add(f"AS2:AS{last}")
dv2 = DataValidation(type="decimal", operator="greaterThanOrEqual", formula1="0", allow_blank=True, showErrorMessage=True, errorTitle="Amount", error="A fee is a number of kwacha, zero or more")
ws.add_data_validation(dv2); dv2.add(f"AO2:AQ{last}")

# a note row under the headers is not possible without shifting data; use the header comment via the Instructions sheet instead.
ins = wb["Instructions"]
assert ins["A11"].value.startswith("4. Save the file"), ins["A11"].value
ins["A11"].value = ("4. Open the sheet 'Upload summary' and go to the yellow columns at the far right (AO to AT). For every facility, type the arrangement fee and the legal fees charged "
                    "when the loan was first granted (in kwacha), any other fee that was a condition of the loan (for example a commitment or valuation fee), the date the fees were charged, "
                    "whether they were deducted from the amount paid out (Y) or paid separately (N), and the reference of the offer letter or receipt you took the figures from. "
                    "Leave a cell blank if there was no such fee; write 0 only if you know it was nil. Column AU adds them up for you.")
ins["A12"].value = "5. Save the file and send it back. Nothing else needs to change."
from copy import copy
ins["A12"].font = copy(ins["A11"].font); ins["A12"].alignment = copy(ins["A11"].alignment)
# why the fees matter: append to the 'Why' paragraph
ins["A5"].value = (ins["A5"].value.rstrip() + " The fees charged at the start of each loan are the one thing neither your workbook nor E-Banker records against the loan, and the effective "
                   "interest rate cannot be worked out without them: under IFRS 9 the fee is spread over the life of the loan as part of its yield. That is why the yellow fee columns are asked for.")
wb.calculation.fullCalcOnLoad = True
OUT = P.replace("for Tamanda to confirm - 7 Oct 2026.xlsx", "with fee columns - for Tamanda to confirm - 7 Oct 2026.xlsx")
wb.save(OUT); print("written", OUT); print("fee columns AO:AU added on Upload summary; instructions steps 4 and 5 updated")
