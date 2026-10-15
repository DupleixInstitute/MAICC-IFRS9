import sys, openpyxl
p, sheet, r0, r1 = sys.argv[1], sys.argv[2], int(sys.argv[3]), int(sys.argv[4])
maxc = int(sys.argv[5]) if len(sys.argv)>5 else 40
wb = openpyxl.load_workbook(p, read_only=True, data_only=True)
ws = wb[sheet]
for i,row in enumerate(ws.iter_rows(min_row=r0, max_row=r1, max_col=maxc, values_only=True), start=r0):
    vals = [(openpyxl.utils.get_column_letter(j+1), v) for j,v in enumerate(row) if v not in (None,'')]
    if vals: print(i, vals)
