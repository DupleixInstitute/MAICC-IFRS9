"""PD calibration analysis (PD Calibration Specification v1): MAIIC's own book, from the bootstrap database maiic_ifrs9_base.
Rebuilds the segment PD from the loan-month history, checks it against the engine, then applies changes 1 to 5 and a backtest."""
import json
import subprocess
from collections import defaultdict

SQL = """select contract_id, reporting_period, loan_portfolio_id, ifrs9_stage, carrying_amount, coalesce(overdue_days,0),
       coalesce(json_extract(build_basis,'$.ledger.written_off'),0), coalesce(pd_post_fli,''), coalesce(ecl_value,''), coalesce(ead,''), coalesce(pd_prefli,'')
from loan_books where loan_portfolio_id <> 8"""
out = subprocess.run(['C:/xampp/mysql/bin/mysql', '-u', 'root', '-N', '-B', 'maiic_ifrs9_base', '-e', SQL], capture_output=True, text=True, check=True).stdout
NAMES = {1: 'Loans', 2: 'MAIIC Industrial', 3: 'MAIIC Agricultural', 4: 'MAIIC Term', 5: 'MAIIC Loans (GL 1050103)', 6: 'FInES Industrial', 7: 'FInES Agricultural'}
rows = defaultdict(dict)   # contract -> period -> record
for line in out.strip().splitlines():
    c, p, pf, st, bal, dpd, wo, pd, ecl, ead, pre = (line.split('\t') + [''] * 11)[:11]
    rows[c][p] = dict(pf=int(pf), stage=int(st) if st not in ('', 'NULL') else None, bal=float(bal or 0), dpd=int(float(dpd or 0)),
                      wo=float(wo or 0) if wo not in ('', 'NULL', 'null') else 0.0, pd=float(pd) if pd not in ('', 'NULL') else None,
                      ecl=float(ecl) if ecl not in ('', 'NULL') else None, ead=float(ead) if ead not in ('', 'NULL') else None, pre=float(pre) if pre not in ('', 'NULL') else None)
months = sorted({p for c in rows.values() for p in c})


def add(p, n):
    y, m = int(p[:4]), int(p[5:]) + n
    y += (m - 1) // 12
    m = (m - 1) % 12 + 1
    return f"{y:04d}-{m:02d}"


def cohorts(definition):
    """Every 12-month window: the loans by portfolio and stage at its start (positive balance), and whether each defaulted.
    definition 'stage3': in Stage 3 at the window end, or written off in the window (the engine's rule, as near as the data allows);
    'dpd90': over 90 days overdue at any month-end in the window, or Stage 3 at its end, or written off (change 5)."""
    out = []
    for s in months:
        e = add(s, 12)
        if e not in months:
            continue
        for c, hist in rows.items():
            r = hist.get(s)
            if not r or r['stage'] not in (1, 2) or r['bal'] <= 0.005:
                continue
            inside = [hist[p] for p in months if s < p <= e and p in hist]
            end = hist.get(e)
            written = any(x['wo'] > r['wo'] + 0.005 for x in inside)
            d = (end is not None and end['stage'] == 3) or written
            if definition == 'dpd90':
                d = d or any(x['dpd'] > 90 for x in inside)
            if definition == 'dpd90end':
                d = d or (end is not None and end['dpd'] > 90)
            out.append(dict(start=s, end=e, pf=r['pf'], stage=r['stage'], bal=r['bal'], default=d, contract=c))
    return out


def rate(obs, by='count'):
    if not obs:
        return None, 0
    if by == 'count':
        return sum(o['default'] for o in obs) / len(obs), len(obs)
    tb = sum(o['bal'] for o in obs)
    return (sum(o['bal'] for o in obs if o['default']) / tb if tb else None), len(obs)


def table(obs_all, window_end=None, by='count', blend_at=None, monotone=False):
    """PD by portfolio and stage: one window (window_end) or every window; optionally blended with the pooled book, and kept in stage order."""
    obs = [o for o in obs_all if window_end is None or o['end'] == window_end]
    res = {}
    for st in (1, 2):
        pool, pool_n = rate([o for o in obs if o['stage'] == st], by)
        res[('Pooled book', st)] = (pool, pool_n)
        for pf, name in NAMES.items():
            own, n = rate([o for o in obs if o['stage'] == st and o['pf'] == pf], by)
            if n == 0 and own is None:
                continue
            if blend_at:
                w = min(1.0, n / blend_at)
                own = (w * (own or 0) + (1 - w) * (pool or 0)) if pool is not None else own
            res[(name, st)] = (own, n)
    if monotone:
        for name in {k[0] for k in res}:
            if (name, 1) in res and (name, 2) in res and res[(name, 2)][0] is not None and res[(name, 1)][0] is not None and res[(name, 2)][0] < res[(name, 1)][0]:
                res[(name, 2)] = (res[(name, 1)][0], res[(name, 2)][1])
    return res


def pct(x):
    return '-' if x is None else f"{x * 100:.1f}%"


stage3 = cohorts('stage3')
dpd90 = cohorts('dpd90')
last = add(months[-1], 0)
groups = ['Pooled book', 'MAIIC Industrial', 'MAIIC Agricultural', 'MAIIC Term', 'FInES Industrial', 'FInES Agricultural']
steps = [
    ('Today (balance, one window, Stage 3)', table(stage3, last, 'balance')),
    ('1: count loans', table(stage3, last, 'count')),
    ('1+2: count, every window', table(stage3, None, 'count')),
    ('1+2+3: and blend at 30 loans', table(stage3, None, 'count', blend_at=30)),
    ('1+2+3+4: and stage order', table(stage3, None, 'count', blend_at=30, monotone=True)),
    ('1-5: default over 90 days, any month', table(dpd90, None, 'count', blend_at=30, monotone=True)),
    ('1-5: default over 90 days at window end', table(cohorts('dpd90end'), None, 'count', blend_at=30, monotone=True)),
]
print(f"History {months[0]}..{months[-1]}; 12-month windows ending {months[12]}..{last}: {len({o['end'] for o in stage3})}")
print(f"Observations (loan-windows) Stage 1/2: {sum(o['stage']==1 for o in stage3)}/{sum(o['stage']==2 for o in stage3)}; latest window Stage 1 loans: {sum(1 for o in stage3 if o['end']==last and o['stage']==1)}")
for st in (1, 2):
    print(f"\nStage {st} PD, window ending {last}")
    print('group'.ljust(22) + ''.join(s[0][:24].rjust(26) for s in steps))
    for g in groups:
        cells = []
        for _, t in steps:
            v = t.get((g, st))
            cells.append((f"{pct(v[0])} (n={v[1]})" if v else '-').rjust(26))
        print(g.ljust(22) + ''.join(cells))

# backtest: the PD booked at the start of each window against the share of that cohort that defaulted
print('\nBacktest: Stage 1 cohort at the window start, PD booked then (12-month, after FLI) against what happened (Stage 3 or written off)')
for s in months:
    e = add(s, 12)
    if e not in months:
        continue
    coh = [o for o in stage3 if o['start'] == s and o['stage'] == 1]
    booked = [rows[o['contract']][s]['pd'] if rows[o['contract']][s]['pd'] is not None else rows[o['contract']][s]['pre'] for o in coh if (rows[o['contract']][s]['pd'] is not None or rows[o['contract']][s]['pre'] is not None)]
    if coh and booked:
        print(f"  {s} -> {e}: {len(coh)} loans, predicted {sum(booked)/len(booked)*100:.1f}% on average ({sum(booked):.1f} defaults), happened {sum(o['default'] for o in coh)} ({sum(o['default'] for o in coh)/len(coh)*100:.1f}%)")

# the September allowance, Stage 1 only: the 12-month ECL is proportional to the 12-month PD, so the booked ECL is scaled by
# the segment PD under each step against today's segment PD (the forward-looking adjustment on top is kept). Stage 2 is lifetime and
# does not scale this way: it needs the workspace run of section 7.
print(f"Estimated effect on the {last} Stage 1 allowance, MAIIC's own book (an estimate from booked ECL, not an ECL run):")
today = steps[0][1]
for label, t_ in steps[1:]:
    b_tot = n_tot = 0.0
    for c, hist in rows.items():
        r = hist.get(last)
        if not r or r['stage'] != 1 or r['ecl'] is None:
            continue
        name = NAMES.get(r['pf'])
        old = (today.get((name, 1)) or today.get(('Pooled book', 1)))[0]
        new = (t_.get((name, 1)) or t_.get(('Pooled book', 1)))[0]
        if not old or new is None:
            continue
        b_tot += r['ecl']
        n_tot += r['ecl'] * new / old
    print(f"  {label:34} booked K{b_tot/1e6:8,.1f}m  estimated K{n_tot/1e6:8,.1f}m  change K{(n_tot-b_tot)/1e6:+8,.1f}m ({(n_tot/b_tot-1)*100:+.0f}%)")

json.dump({'steps': [[s[0], {f"{k[0]}|{k[1]}": v for k, v in s[1].items()}] for s in steps]},
          open(r'C:\Users\wadza\AppData\Local\Temp\claude\c--xampp-htdocs-Stress-Testing-App\ee984144-7b28-4df1-9a6e-560f112869e9\scratchpad\pd_calibration.json', 'w'), indent=1)
