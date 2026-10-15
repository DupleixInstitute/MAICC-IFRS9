import sys, openpyxl
wb = openpyxl.load_workbook(sys.argv[1], data_only=False, read_only=True)
ws = wb[sys.argv[2]]
r=int(sys.argv[3])
for row in ws.iter_rows(min_row=r,max_row=r,values_only=False):
    for c in row:
        if c.value not in (None,''): print(c.coordinate, repr(c.value)[:300])
