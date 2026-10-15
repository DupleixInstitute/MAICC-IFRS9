import sys, openpyxl
wb = openpyxl.load_workbook(sys.argv[1], data_only=True, read_only=True)
print(wb.sheetnames)
for nm in sys.argv[2:]:
    ws = wb[nm]
    print('####', nm)
    for row in ws.iter_rows():
        vals = [(c.coordinate, c.value) for c in row if c.value not in (None, '')]
        if vals: print(vals)
