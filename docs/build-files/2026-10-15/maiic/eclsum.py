import sys, openpyxl, glob, os
for p in sys.argv[1:]:
    try:
        wb = openpyxl.load_workbook(p, read_only=True, data_only=True)
        ws = wb['Summary(OriginalMethod)']
        g = {i: list(r) for i, r in enumerate(ws.iter_rows(min_row=4, max_row=9, min_col=1, max_col=11, values_only=True), start=4)}
        def v(r,c): 
            x=g[r][c-1]; return x/1e6 if isinstance(x,(int,float)) else float('nan')
        print(f"{os.path.basename(p)[:48]:48s} onBS={v(4,3):9,.1f} S1g={v(4,6):9,.1f} S2g={v(5,6):8,.1f} S3g={v(6,6):8,.1f} | ECL S1={v(4,9):6,.1f} S2={v(5,9):6,.1f} S3={v(6,9):6,.1f} T={v(9,3):6,.1f}")
    except Exception as e:
        print(os.path.basename(p), 'ERR', e)
