import sys, openpyxl
for p in sys.argv[1:]:
    wb = openpyxl.load_workbook(p, read_only=True, data_only=True)
    print("==", p.split('/')[-1])
    for ws in wb.worksheets:
        print("  ", ws.title, ws.max_row, ws.max_column)
