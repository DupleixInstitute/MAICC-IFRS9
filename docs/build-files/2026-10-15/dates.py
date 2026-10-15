import sys, xlrd, re, os
for p in sys.argv[1:]:
    wb = xlrd.open_workbook(p, on_demand=True); sh = wb.sheet_by_index(0)
    found = []
    for i in range(min(sh.nrows, 15)):
        for c in sh.row_values(i):
            s = str(c)
            if re.search(r'(Date|As On|As at|From|Period|\d{2}/\d{2}/\d{4})', s) and len(s) < 120:
                found.append(s.strip())
    print(os.path.basename(p), sh.nrows, '|', ' ; '.join(dict.fromkeys(found))[:300])
