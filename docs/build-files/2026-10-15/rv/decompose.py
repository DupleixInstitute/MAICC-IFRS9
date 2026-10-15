"""Walk from MAIIC's December 2025 ECL to the system's, loan by loan, one cause at a
time, on the matched loans of cmp_dec2025.py output. Read-only.

  E0  MAIIC's ECL                          PD_t x LGD_t x (EAD_t - PV(FCL)_t)
  E1  without the typed recovery deduction PD_t x LGD_t x EAD_t
  E2  our stage, MAIIC's PD rule           PD_t(our stage) x LGD_t x EAD_t
  E3  our PD                                PD_o(horizon, our stage) x LGD_t x EAD_t
  E4  our LGD                               PD_o x LGD_o x EAD_t
  E5  our EAD (= our ECL, undiscounted)     PD_o x LGD_o x EAD_o
plus the loans only one side holds.

MAIIC's PD rule (ECLComp AK..AR): Stage 1 the S&P 'A' 12-month rate 0.03% x (1 + 15.75%);
Stage 2 the S&P 'B' cumulative rate for the years to maturity (1 to 10) x (1 + the macro
factor of that year); Stage 3 is 1. MAIIC grades a 91-180 day loan CCC, which the
substitution cannot know for our stages, so Stage 2 is taken at grade B throughout.
usage: python -I decompose.py <cmp_csv>"""
import sys, csv

B = [0.0298, 0.0609, 0.0846, 0.1045, 0.1196, 0.1311, 0.1414, 0.1501, 0.1575, 0.1652]
MACRO = {1: 0.13517635, 2: 0.153822359375, 3: 0.1575, 4: 0.15425, 5: 0.1375, 6: 0.137775}
for y in range(7, 11):
    MACRO[y] = 0.14247517739583335


def their_pd(stage, years):
    y = max(1, min(10, int(round(years or 1))))
    if stage == '3':
        return 1.0
    if stage == '2':
        return min(1.0, B[y - 1] * (1 + MACRO[y]))
    return 0.0003 * 1.1575


def our_pd(stage, pd12, months):
    pd12 = max(0.0, min(1.0, pd12 or 0.0))
    m = months if months and months >= 1 else 12
    if stage == '3':
        return 1.0
    if stage == '2':
        return 1 - (1 - pd12) ** (m / 12)
    return 1 - (1 - pd12) ** (min(m, 12) / 12)


f = lambda v: float(v) if v not in ('', None, 'None') else 0.0
rows = list(csv.DictReader(open(sys.argv[1], encoding='utf-8')))
E = [0.0] * 6
only_t = only_o = 0.0
for r in rows:
    if r['match'] == 'UNMATCHED':
        only_t += f(r['maiic_ecl'])
        continue
    if r['match'] == 'OURS_ONLY':
        only_o += f(r['our_ecl'])
        continue
    pdt, lgdt, eadt, fcl = f(r['maiic_pd']), f(r['maiic_lgd']), f(r['maiic_ead']), f(r['maiic_pvfcl'])
    years = f(r['maiic_years'])
    st = (r['our_stage'] or '1')
    pdo = our_pd(st, f(r['our_pd12']), f(r['our_rem_tenor']))
    lgdo = f(r['our_lgd'])
    eado = f(r['our_ead'])
    E[0] += f(r['maiic_ecl'])
    E[1] += pdt * lgdt * max(eadt, 0)
    E[2] += their_pd(st, years) * lgdt * max(eadt, 0)
    E[3] += pdo * lgdt * max(eadt, 0)
    E[4] += pdo * lgdo * max(eadt, 0)
    E[5] += f(r['our_ecl'])
labels = ["MAIIC's ECL on the matched loans", 'remove the typed recovery deduction (PV of 47% of EAD)', 'restage to our stages (MAIIC PD rule)',
          'our PD (observed transition rates, lifetime for Stage 2)', 'our LGD (cohort workout)', 'our EAD and residual (= our ECL)']
prev = None
for i, (l, v) in enumerate(zip(labels, E)):
    print(f"{l:60s} {v/1e6:12.1f}" + ('' if prev is None else f"   step {(v-prev)/1e6:+10.1f}"))
    prev = v
print(f"{'MAIIC rows we hold no loan for (Various closed accounts)':60s} {only_t/1e6:12.1f}")
print(f"{'our loans MAIIC does not hold (MUSECO etc.)':60s} {only_o/1e6:12.1f}")
