import sys, openpyxl
wb = openpyxl.load_workbook(sys.argv[1], data_only=True, read_only=True)
ws = wb[sys.argv[2]]
r0=int(sys.argv[3]); r1=int(sys.argv[4]); c1=int(sys.argv[5]) if len(sys.argv)>5 else 60
for i,row in enumerate(ws.iter_rows(min_row=r0,max_row=r1,max_col=c1,values_only=True),start=r0):
    vals=[(openpyxl.utils.get_column_letter(j+1),v) for j,v in enumerate(row) if v not in (None,'')]
    if vals: print(i, vals)
