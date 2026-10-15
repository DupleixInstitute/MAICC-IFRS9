"""Inventory CSV files: size, sha256, encoding, headers, row counts (line-based like the PHP reader, and csv-parsed),
first date column range, distinct accounts. Read-only. Usage: python -I inventory.py <file> [<file> ...]"""
import sys, hashlib, csv, io, re, os, json
from datetime import datetime

DATE_COLS = ['TRANSACTION_DATE', 'ASONDATE', 'STATUS_EFF_DATE', 'APPLICABLE_FROM_DATE', 'ACCOUNT_OPEN_DATE', 'SANCTION_DATE',
             'INSTALLMENT_DATE', 'WRITEOFF_DATE', 'PERIOD', 'MONTH']


def parse_date(v):
    v = v.strip()
    for fmt in ('%m/%d/%Y', '%Y-%m-%d', '%Y-%m-%d %H:%M:%S', '%m/%d/%Y %I:%M:%S %p', '%d-%b-%y', '%d-%b-%Y'):
        try:
            return datetime.strptime(v, fmt).date().isoformat()
        except ValueError:
            pass
    return None


def main():
    for path in sys.argv[1:]:
        raw = open(path, 'rb').read()
        sha = hashlib.sha256(raw).hexdigest()
        bom = raw.startswith(b'\xef\xbb\xbf')
        try:
            text = raw.decode('utf-8-sig')
            enc = 'utf-8' + ('-bom' if bom else '')
        except UnicodeDecodeError:
            text = raw.decode('cp1252')
            enc = 'cp1252'
        lines = [l for l in re.split(r'\r\n|\n|\r', text) if l.strip() != '']
        line_rows = len(lines) - 1 if lines else 0
        rdr = list(csv.reader(io.StringIO(text, newline='')))
        rdr = [r for r in rdr if any(c.strip() for c in r)]
        hdr = [h.strip() for h in rdr[0]] if rdr else []
        drop = bool(hdr) and hdr[0] == ''
        if drop:
            hdr = hdr[1:]
        rows = [r[1:] if drop else r for r in rdr[1:]]
        bad = sum(1 for r in rows if len(r) != len(hdr))
        recs = [dict(zip(hdr, r)) for r in rows if len(r) == len(hdr)]
        info = {'file': os.path.basename(path), 'bytes': len(raw), 'sha256': sha, 'enc': enc, 'cols': len(hdr),
                'line_rows': line_rows, 'csv_rows': len(rows), 'bad_width': bad, 'rownum_col': drop}
        if 'NEW_AC_NUMBER' in hdr:
            info['accounts'] = len({r['NEW_AC_NUMBER'].strip() for r in recs if r['NEW_AC_NUMBER'].strip()})
        for dc in DATE_COLS:
            if dc in hdr:
                ds = [parse_date(r[dc]) for r in recs if r[dc].strip()]
                good = [d for d in ds if d]
                sample = next((r[dc] for r in recs if r[dc].strip()), '')
                info['date'] = {'col': dc, 'min': min(good) if good else None, 'max': max(good) if good else None,
                                'unparsed': len(ds) - len(good), 'sample': sample}
                break
        if 'DELETE_FLAG' in hdr:
            info['deleted'] = sum(1 for r in recs if r['DELETE_FLAG'].strip() == 'Y')
        for g in ('AC_GLCODE', 'GLCODE', 'CB_GLCODE'):
            if g in hdr:
                c = {}
                for r in recs:
                    c[r[g].strip()] = c.get(r[g].strip(), 0) + 1
                info['gl_' + g] = dict(sorted(c.items(), key=lambda x: -x[1])[:12])
                break
        info['headers'] = hdr if len(hdr) <= 40 else hdr[:40] + ['...']
        print(json.dumps(info))


main()
