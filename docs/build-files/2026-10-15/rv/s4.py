import sys, openpyxl, csv
from openpyxl.utils import column_index_from_string as ci
wb = openpyxl.load_workbook(sys.argv[1], data_only=True, read_only=True)
ws = wb['ECLComp']
cols=['A','B','D','E','F','H','I','J','K','P','Q','R','S','U','V','W','X','Y','AG','AK','AL','AO','AP','AR','AS','AT','AU','AV','AW','AX','AY','AZ','BA','BB','BC']
out=csv.writer(open(sys.argv[2],'w',newline='',encoding='utf-8'))
out.writerow(cols)
for row in ws.iter_rows(min_row=11,max_row=124,values_only=True):
    if row[0] is None and row[4] is None: continue
    out.writerow([row[ci(c)-1] for c in cols])
