import sys, openpyxl, csv
wb = openpyxl.load_workbook(sys.argv[1], data_only=True, read_only=True)
ws = wb[sys.argv[2]]
w=csv.writer(open(sys.argv[3],'w',newline='',encoding='utf-8'))
for row in ws.iter_rows(min_row=int(sys.argv[4]),max_row=int(sys.argv[5]),max_col=int(sys.argv[6]),values_only=True):
    if any(v not in (None,'') for v in row): w.writerow(row)
