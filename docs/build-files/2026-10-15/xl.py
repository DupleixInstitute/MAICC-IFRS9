import sys, openpyxl
wb = openpyxl.load_workbook(sys.argv[1], read_only=True, data_only=True)
for ws in wb.worksheets:
    print("##", ws.title, ws.max_row, ws.max_column)
    for row in ws.iter_rows(values_only=True):
        vals = [str(v)[:140] for v in row if v not in (None, "")]
        if vals: print(" | ".join(vals))
