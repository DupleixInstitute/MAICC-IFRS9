import sys, xlrd, os
for p in sys.argv[1:]:
    try:
        wb = xlrd.open_workbook(p, on_demand=True)
    except Exception as e:
        print(os.path.basename(p), 'ERR', e); continue
    for s in wb.sheets():
        hdr=None
        for r in range(min(s.nrows,15)):
            row=[str(c).strip() for c in s.row_values(r)]
            if any('arrying' in c or 'A/C' in c or 'Account' in c for c in row):
                hdr=(r,row[:30]); break
        print(os.path.basename(p), '|', s.name, s.nrows, s.ncols, '| hdr', hdr[0] if hdr else None, [c for c in (hdr[1] if hdr else []) if c][:14])
