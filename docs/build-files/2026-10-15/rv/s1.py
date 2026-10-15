import sys, openpyxl
wb = openpyxl.load_workbook(sys.argv[1], data_only=True, read_only=True)
for ws in wb.worksheets:
    print(ws.title, ws.max_row, ws.max_column, ws.sheet_state)
