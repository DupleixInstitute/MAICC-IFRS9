"""Writes the 14 Oct 2026 results review (Markdown) from figures.py output.
usage: python -I report.py <fig_json> <od_json> <rv_dir> <commit> <out_md>"""
import sys, json, csv
from collections import defaultdict

R = json.load(open(sys.argv[1], encoding='utf-8'))
OD = json.load(open(sys.argv[2], encoding='utf-8'))
RV, COMMIT, OUT = sys.argv[3], sys.argv[4], sys.argv[5]
DB = R['db']


def m(v, d=1):
    if v is None:
        return '-'
    v = v / 1e6
    if abs(v) < 0.05 and d == 1:
        return '0.0' if v >= 0 else '-0.0'
    return f'{v:,.{d}f}'


def sm(v):
    """signed millions"""
    if v is None:
        return '-'
    return ('+' if v >= 0 else '') + m(v)


def pc(v, d=1):
    return '-' if v is None else f'{v * 100:.{d}f}%'


def nm(s):
    s = (s or '').strip()
    if s.isupper() or s.islower():
        s = s.title()
    return s.replace("'S ", "'s ").replace("Vnc ", "VNC ").replace("Museco", "MUSECO").replace("Sacco", "SACCO").replace(" And ", " and ")


def n(v):
    return '-' if v is None else f'{int(v):,}'


L = []
w = L.append
st = {p: {r['st']: r for r in R['stage_' + p]} for p in ('2025-12', '2026-09')}
bp = {r['p']: r for r in R['by_period']}
D, S = bp['2025-12'], bp['2026-09']
MS = R['maiic_model_stage']
mt = lambda k: sum(MS[s][k] for s in MS)
walk = R['walk']
E = walk['steps']
mfd, mfs = R['mf_by_period']['2025-12'], R['mf_by_period']['2026-09']
mfst = {p: {r['st']: r for r in R['mf_stage_' + p]} for p in ('2025-12', '2026-09')}
cz = R['stage_causes']
for k in ('same', 'ageing', 'trigger', 'maiic_higher'):
    cz.setdefault(k, [0, 0.0, 0.0, 0.0])
pdw = {p: R['pd_window_' + p] for p in ('2025-12', '2026-09')}


def pdsum(p, s):
    rs = [r for r in pdw[p] if r['start_stage'] == s]
    d = [r for r in rs if r['end'] == '3']
    return len(rs), sum(r['start'] for r in rs), len(d), sum(r['start'] for r in d)


book = {p: {(c["segment_key"], str(c["stage"])): c for c in R['pd_cells_' + p]} for p in ('2025-12', '2026-09')}
lgdb = {p: {x['code']: x for x in R['lgd_' + p]} for p in ('2025-12', '2026-09')}
ds = {p: R['discstatus_' + p] for p in ('2025-12', '2026-09')}
eir_counts = defaultdict(int)
for r in R['eir_status']:
    eir_counts[r['calculation_status']] += r['n']
locked_takeon = sum(r['n'] for r in R['eir_status'] if r['calculation_status'] == 'LOCKED' and r['schedule_source'] == 'TAKEON_WORKBOOK')
cats = R['eir_block_cats']
tk = {r['chk']: r for r in R['takeon']}
fees = R['fees']
fee_by = lambda src, sup=0: [f for f in fees if f['source_system'] == src and f['sup'] == sup]
fsum = lambda rows: (sum(f['n'] for f in rows), sum(f['amt'] for f in rows), sum(f['contracts'] for f in rows))
gl_fee = fsum(fee_by('E-Banker GL fee posting'))
los_fee = fsum(fee_by('E-Banker LOS_DISB_CHARGES_POST'))
los_sup = fsum(fee_by('E-Banker LOS_DISB_CHARGES_POST', 1))
tk_fee = fsum(fee_by('TAKEON_WORKBOOK'))
fgu = R['fee_gl_unassigned']
rev = R['rev_by_year']
ac = {p: R['ac_' + p] for p in ('2025-12', '2026-09')}
res_n = sum(r['n'] for r in R['restructures'])
res_loans = len({1})  # placeholder, loans counted below
res_mod = sum(r['n'] for r in R['restructures'] if r['suggested_class'] == 'MODIFICATION')
res_by_src = defaultdict(int)
for r in R['restructures']:
    res_by_src[r['source']] += r['n']
rs_reset = defaultdict(int)
for r in R['resets']:
    rs_reset[(r['event_type'], r['status'])] += r['n']
ties = R['ties']
mfsplit = R['mf_split']
mftie = {str(r['sign_err']): r['n'] for r in R['mf_tie']}

# ---------------------------------------------------------------- derived
d1, d2, d3 = st['2025-12']['1'], st['2025-12']['2'], st['2025-12']['3']
s1, s2, s3 = st['2026-09']['1'], st['2026-09']['2'], st['2026-09']['3']
undisc_dec = D['ecl']
booked_dec = D['disc']
shown_dec = D['shown']
undisc_sep, booked_sep, shown_sep = S['ecl'], S['disc'], S['shown']
disc_effect_dec = D['disc_undisc'] - D['disc']
disc_effect_sep = S['disc_undisc'] - S['disc']
pd_dec_1 = pdsum('2025-12', '1')
pd_dec_2 = pdsum('2025-12', '2')
pd_sep_1 = pdsum('2026-09', '1')
pd_sep_2 = pdsum('2026-09', '2')
lgd_dec, lgd_sep = lgdb['2025-12']['book'], lgdb['2026-09']['book']
maiic_undrawn = mt('undrawn')
our_undrawn_dec = D['undrawn_util']
loss_rd = [x for x in ds['2025-12'] if x['src'] == 'NONE_LOSS_AT_REPORTING_DATE']
loss_rd_n = sum(x['n'] for x in loss_rd)
loss_rd_amt = sum(x['ecl'] or 0 for x in loss_rd)
eir_disc_dec = [x for x in ds['2025-12'] if x['src'] in ('EIR_ORIGINAL', 'EIR_CURRENT_FLOATING')]
eir_disc_dec_n = sum(x['n'] for x in eir_disc_dec)
noexp_dec = sum(x['n'] for x in ds['2025-12'] if x['src'] in ('NO_EXPOSURE', 'NO_EXPOSURE_WRITTEN_OFF'))
eir_disc_sep = [x for x in ds['2026-09'] if x['src'] in ('EIR_ORIGINAL', 'EIR_CURRENT_FLOATING')]
eir_disc_sep_n = sum(x['n'] for x in eir_disc_sep)
loss_rd_sep_n = sum(x['n'] for x in ds['2026-09'] if x['src'] == 'NONE_LOSS_AT_REPORTING_DATE')

# Thom's 34 loans
thom = list(csv.DictReader(open(RV + '/eir_table.csv', encoding='utf-8')))
eall = {r['contract_id']: r for r in R['eir_all']}
fbc = defaultdict(lambda: defaultdict(float))
for f in R['fees_by_contract']:
    fbc[f['contract_id']][f['source_system']] += f['amt'] or 0
fl = lambda v: float(v) if v not in ('', None) else 0.0

w(f'# MAIIC ECL and EIR results review - 14 Oct 2026')
w('')
w(f'**Dupleix Institute, prepared for Edward Mazibuko CA(SA), for review before it is shared with MAIIC.** This report replaces the review of 13 October 2026 and its addendum of 14 October (kept as versions 1 and 2). It compares the IFRS 9 system\'s results with MAIIC\'s own December 2025 ECL models, the signed 2025 financial statements (note 8) and MAIIC\'s manual EIR workings, explains each difference, and lists what MAIIC has to decide or supply.')
w('')
w(f'**Basis of the figures.** Every system figure comes from a fresh bootstrap of the system at commit `{COMMIT}` of branch `eir_revenue_recognition` (`eir:bootstrap --fresh --with-client-inputs --build --run-engines --verify`, database `{DB}`): the committed E-Banker packs 1 to 10, the trial balances and the take-on workbook, run through the month-end chain for every month from July 2024 to September 2026. This is the result a clean install at MAIIC produces from the same data; nothing was adjusted by hand afterwards. The bootstrap approves only under its own label ("System Bootstrap, automated data-readiness, not a MAIIC approval"), so no fee has been reviewed, no restructure confirmed and no scenario set approved by MAIIC. Amounts are in MWK million unless stated.')
w('')
w('**Contents.** 1 Executive summary. 2 The differences, one by one (staging, PD, LGD, EAD, the walk from 488.5 million, discounting, Mega Farm). 3 The effective interest rate. 4 What changed since 13 October, what remains, and what needs MAIIC. Appendices A to G: figures by month, portfolio and stage, the PD and LGD evidence loan by loan, the staging differences, data ties, MAIIC\'s model, sources.')
w('')

# ================================================================ 1
w('## 1. Executive summary')
w('')
gap = undisc_dec - E[0]
w(f'**The system and MAIIC still differ by a large amount, and almost all of it is method and data, not arithmetic.** On MAIIC\'s own lending (MAIIC and FInES) the system measures an ECL of {m(undisc_dec)} million at 31 December 2025 (undiscounted), of which {m(booked_dec)} million is booked at the effective interest rate on {n(D["disc_n"])} loans and {m(D["undisc"])} million sits on {n(D["undisc_n"])} loans that cannot yet be discounted because their EIR is waiting for MAIIC\'s fee review. MAIIC\'s model gives {m(E[0])} million and the signed statements 475.7 million. The carrying amounts agree: {walk["matched"]} of the model\'s {walk["maiic_rows"]} rows are the same loans at the same balance to the cent, and the book ties to Finance\'s December 2025 listing to the cent. The Mega Farm programme also agrees to the cent ({m(ties["mf_2025_12"]["ca"])} million), but the system stages most of it as current at that date and cannot yet measure its December 2025 loss.')
w('')
w('**Table 1. MAIIC and FInES loans at 31 December 2025**')
w('')
w('| | MAIIC model (14 Jan 2026 version) | Signed statements, note 8 | System |')
w('|---|---:|---:|---:|')
w(f'| Gross carrying amount | {m(mt("gross"))} | 14,702.4 | {m(D["gross"])} |')
w(f'| EAD (gross plus undrawn) | {m(mt("ead"))} | - | {m(D["ead"])} |')
w(f'| Loans in Stage 1 / 2 / 3 | {n(MS["1"]["n"])} / {n(MS["2"]["n"])} / {n(MS["3"]["n"])} | - | {n(d1["n"])} / {n(d2["n"])} / {n(d3["n"])} |')
w(f'| Gross in Stage 1 / 2 / 3 | {m(MS["1"]["gross"])} / {m(MS["2"]["gross"])} / {m(MS["3"]["gross"])} | 12,622.9 / 1,138.2 / 941.3 | {m(d1["gross"])} / {m(d2["gross"])} / {m(d3["gross"])} |')
w(f'| EAD in Stage 1 / 2 / 3 | {m(MS["1"]["ead"])} / {m(MS["2"]["ead"])} / {m(MS["3"]["ead"])} | - | {m(d1["ead"])} / {m(d2["ead"])} / {m(d3["ead"])} |')
w(f'| 12-month PD, Stage 1 / 2 (EAD-weighted) | {pc(MS["1"]["ak"] / MS["1"]["ead"], 2)} / {pc(MS["2"]["ak"] / MS["2"]["ead"])} | - | {pc(d1["pd12"])} / {pc(d2["pd12"])} |')
w(f'| PD applied, Stage 1 / 2 / 3 (Stage 2 lifetime) | {pc(MS["1"]["ar"] / MS["1"]["ead"], 3)} / {pc(MS["2"]["ar"] / MS["2"]["ead"])} / 100% | - | {pc(d1["pd12"])} / {pc(d2["pdlt"])} / 100% |')
w(f'| LGD, Stage 1 / 2 / 3 (EAD-weighted) | {pc(MS["1"]["lgd"] / MS["1"]["ead"])} / {pc(MS["2"]["lgd"] / MS["2"]["ead"])} / {pc(MS["3"]["lgd"] / MS["3"]["ead"])} | - | {pc(d1["lgd"])} / {pc(d2["lgd"])} / {pc(d3["lgd"])} |')
w(f'| Typed recovery deducted from EAD before PD and LGD (present value of 47% of EAD) | {m(mt("pvfcl"))} | - | none |')
w(f'| ECL Stage 1 / 2 / 3, undiscounted | {m(MS["1"]["ecl"])} / {m(MS["2"]["ecl"])} / {m(MS["3"]["ecl"])} | 12.0 / 26.7 / 437.0 | {m(d1["ecl"])} / {m(d2["ecl"])} / {m(d3["ecl"])} |')
w(f'| **ECL total, undiscounted** | **{m(E[0])}** | **475.7** | **{m(undisc_dec)}** |')
w(f'| Booked: discounted at the EIR | not discounted | - | {m(booked_dec)} on {n(D["disc_n"])} loans |')
w(f'| Not yet discountable (no locked EIR), shown undiscounted | - | - | {m(D["undisc"])} on {n(D["undisc_n"])} loans |')
w(f'| **Allowance shown** | **{m(E[0])}** | **475.7** | **{m(shown_dec)}** |')
w(f'| Coverage of EAD | {pc(E[0] / mt("ead"))} | {pc(475.7e6 / 14702.4e6)} of gross | {pc(shown_dec / D["ead"])} |')
w('')
w('**Table 2. Mega Farm programme at 31 December 2025**')
w('')
mc = R['mf_compare']
w('| | MAIIC Mega Farm model | Signed statements, note 8 | System |')
w('|---|---:|---:|---:|')
w(f'| Accounts | {n(mc["rows"])} | - | {n(mfd["n"])} ({n(mfd["n_bal"])} with a balance) |')
w(f'| Gross carrying amount | {m(mc["model_ead"])} | 51,544.3 | {m(mfd["gross"])} |')
w(f'| Accounts in Stage 1 / 2 / 3 | {n(mc["model_stage"]["1"][0])} / 0 / {n(mc["model_stage"]["3"][0])} | - | {n(mfd["n1"])} / {n(mfd["n2"])} / {n(mfd["n3"])} |')
w(f'| Stage 3 gross | {m(mc["model_stage"]["3"][1])} | 48,744.9 | {m(mfd["g3"])} |')
w(f'| ECL | {m(mc["model_ecl"])} (PD and LGD 100%, 20% typed recovery) | 39,761.4 on the loans, plus 2,105.2 on MAIIC\'s Mega Farm interest receivable | not measured: no programme loan was in Stage 3 at December 2024 for the LGD workout to follow |')
w('')
mv = Sv = None
w('**Table 3. 30 September 2026 (system only; MAIIC has no 2026 model) and the movement since December 2025**')
w('')
w('| MAIIC and FInES loans | 31 Dec 2025 | 30 Sep 2026 | Movement |')
w('|---|---:|---:|---:|')
w(f'| Loans (with a balance) | {n(D["n"])} ({n(D["n_bal"])}) | {n(S["n"])} ({n(S["n_bal"])}) | {S["n"] - D["n"]:+d} |')
w(f'| Gross carrying amount | {m(D["gross"])} | {m(S["gross"])} | {sm(S["gross"] - D["gross"])} |')
w(f'| EAD | {m(D["ead"])} | {m(S["ead"])} | {sm(S["ead"] - D["ead"])} |')
w(f'| Loans in Stage 1 / 2 / 3 | {n(d1["n"])} / {n(d2["n"])} / {n(d3["n"])} | {n(s1["n"])} / {n(s2["n"])} / {n(s3["n"])} | |')
w(f'| EAD in Stage 3 | {m(d3["ead"])} | {m(s3["ead"])} | {sm(s3["ead"] - d3["ead"])} |')
w(f'| 12-month PD, Stage 1 / 2 (pooled book, observed) | {pc(book["2025-12"][("book", "1")]["observed_rate"])} / {pc(book["2025-12"][("book", "2")]["observed_rate"])} | {pc(book["2026-09"][("book", "1")]["observed_rate"])} / {pc(book["2026-09"][("book", "2")]["observed_rate"])} | |')
w(f'| LGD (pooled cohort) | {pc(lgd_dec["lgd"])} | {pc(lgd_sep["lgd"])} | |')
w(f'| ECL Stage 1 / 2 / 3, undiscounted | {m(d1["ecl"])} / {m(d2["ecl"])} / {m(d3["ecl"])} | {m(s1["ecl"])} / {m(s2["ecl"])} / {m(s3["ecl"])} | {sm(s1["ecl"] - d1["ecl"])} / {sm(s2["ecl"] - d2["ecl"])} / {sm(s3["ecl"] - d3["ecl"])} |')
w(f'| **ECL, undiscounted** | **{m(undisc_dec)}** | **{m(undisc_sep)}** | **{sm(undisc_sep - undisc_dec)}** |')
w(f'| Booked, discounted at the EIR (loans) | {m(booked_dec)} ({n(D["disc_n"])}) | {m(booked_sep)} ({n(S["disc_n"])}) | {sm(booked_sep - booked_dec)} |')
w(f'| Not yet discountable, shown undiscounted (loans) | {m(D["undisc"])} ({n(D["undisc_n"])}) | {m(S["undisc"])} ({n(S["undisc_n"])}) | {sm(S["undisc"] - D["undisc"])} |')
w(f'| **Allowance shown** | **{m(shown_dec)}** | **{m(shown_sep)}** | **{sm(shown_sep - shown_dec)}** |')
w(f'| Mega Farm: gross / Stage 3 gross | {m(mfd["gross"])} / {m(mfd["g3"])} | {m(mfs["gross"])} / {m(mfs["g3"])} | |')
w(f'| Mega Farm: programme ECL / MAIIC\'s share at the governed 5% | not measured | {m(mfs["programme_ecl"])} / {m(mfs["maiic_ecl"])} | |')
w('')
w(f'**Where the {m(gap)} million between MAIIC\'s model and the system comes from** (section 2.5; loan by loan, one cause at a time, on the {walk["matched"]} matched loans):')
w('')
w('| Step | ECL | Change |')
w('|---|---:|---:|')
labels = ["MAIIC's model, matched loans", 'Remove the typed recovery deduction (47% of EAD, counted on top of the collateral LGD)', 'Restage to the system\'s stages, keeping MAIIC\'s own PD rule',
          'The system\'s PDs (observed defaults; lifetime for Stage 2)', 'The system\'s LGD (cohort workout)', 'The system\'s EAD (undrawn at its utilisation, no guarantees)']
for i in range(6):
    w(f'| {labels[i]} | {m(E[i])} | {"" if i == 0 else sm(E[i] - E[i - 1])} |')
w(f'| Loans the model leaves out (MUSECO, carried by E-Banker, and small balances) | {m(E[5] + walk["only_ours_ecl"] - walk["only_theirs_ecl"])} | {sm(walk["only_ours_ecl"] - walk["only_theirs_ecl"])} |')
w(f'| **System ECL, undiscounted** | **{m(undisc_dec)}** | |')
w(f'| Discounting at the EIR on the loans that have one | {m(shown_dec)} | {sm(shown_dec - undisc_dec)} |')
w('')
w('**What matters most, in order.**')
w('')
w(f'1. **Staging (+{m(E[2] - E[1])} million).** MAIIC\'s model ages arrears from its own typed `Loan Book` sheet; the system counts days past due from E-Banker\'s instalment chart and the ledger receipts. For {n(cz["ageing"][0])} loans (MAIIC exposure {m(cz["ageing"][1])} million) E-Banker shows older arrears than the model, and where we rebuilt them from the chart and the ledger E-Banker is right on almost all of them (Chipala, Pinnacle, Infracon, Edge View, Chanje, Nyamunyamu, Chikomanyama, Kambewu). {n(cz["trigger"][0])} more are held in Stage 3 by the signed accounting policy\'s four-missed-instalments default trigger, which the model does not apply. The model and E-Banker cannot both be right: this is the first thing to settle with Dr Thom.')
w(f'2. **PD (+{m(E[3] - E[2])} million).** The model uses S&P emerging-market default tables mapped from the arrears bracket: 0.03 percent for every Stage 1 loan. The system measures what MAIIC\'s own book did: of {m(pd_dec_1[1])} million in Stage 1 at December 2024, {m(pd_dec_1[3])} million ({pc(book["2025-12"][("book", "1")]["observed_rate"])}) was in default a year later; Stage 2 {pc(book["2025-12"][("book", "2")]["observed_rate"])}. Each default was checked against the ledger. The rates are real but rest on one year of about 50 loans, so they are volatile; a credibility blend with an external benchmark while the history is short is a decision for Dr Thom, not something the system should assume.')
w(f'3. **LGD ({sm(E[4] - E[3])} million) and the 47% deduction (+{m(E[1] - E[0])} million).** The model\'s LGD is collateral only, and it then deducts a typed 46.98 percent of EAD as "recovered" from every loan, performing or not, which counts recoveries twice. The system measures the LGD from what defaulted loans actually returned ({pc(lgd_dec["lgd"])} pooled at December 2025: cure {pc(lgd_dec["cure_rate"])}, cash recovery {pc(lgd_dec["recovery_rate"])}), without collateral, since the collateral register covers too few loans.')
w(f'4. **Discounting and the EIR.** Only {n(R["eir_status"] and eir_counts["LOCKED"])} of {n(sum(eir_counts.values()))} contracts have a locked EIR on a clean install, because every loan with a fee on record waits for Finance to classify its fees ({n(cats.get("fee review only", 0))} contracts). Until then {n(D["undisc_n"])} loans ({m(D["undisc"])} million) stay undiscounted at December 2025 and {n(S["undisc_n"])} ({m(S["undisc"])} million) at September 2026, each named on the month record. This is the single largest action for MAIIC: the fee review unlocks the EIR, the booked allowance and the revenue.')
w(f'5. **Mega Farm.** E-Banker gives most of the 2025 season a maturity of 31 December 2025, so it is not past due at the year end; the statements treat 95 percent as in default. From March 2026 the system has {n(R["mf_by_period"]["2026-03"]["n3"])} accounts in Stage 3 and by September 2026 measures a programme ECL of {m(mfs["programme_ecl"])} million ({pc(mfs["programme_ecl"] / mfs["gross"])} of gross). Whether to record the 2025 evidence as a credit-impaired trigger at 31 December 2025, and what share of the loss is MAIIC\'s (5 percent in the governed setting; E-Banker splits the interest 33/67), are Dr Thom\'s decisions.')
w('')
w('**What needs MAIIC first** (section 4.3 has the full list): Finance to classify the fees on record (the EIR, the booked allowance and the revenue all wait on it); Dr Thom to decide the ageing basis, the PD credibility blend, the Mega Farm 2025 trigger and share, a materiality threshold and how refinancings are followed; Tamanda to confirm the take-on mapping; Barry to explain the E-Banker report defects and rebuild the stored trial balance.')
w('')

# ================================================================ 2
w('## 2. The differences, one by one')
w('')
w('### 2.1 Staging')
w('')
w('| How the two sides stage the same loan at 31 December 2025 | Loans | MAIIC exposure | MAIIC ECL | System ECL (undiscounted) |')
w('|---|---:|---:|---:|---:|')
w(f'| Same stage on both sides | {n(cz["same"][0])} | {m(cz["same"][1])} | {m(cz["same"][2])} | {m(cz["same"][3])} |')
w(f'| E-Banker\'s days past due are older than MAIIC\'s typed bracket (some then also held in Stage 3 by the instalment trigger) | {n(cz["ageing"][0])} | {m(cz["ageing"][1])} | {m(cz["ageing"][2])} | {m(cz["ageing"][3])} |')
w(f'| Same ageing bracket, but held higher by the four-missed-instalments trigger or the cure period | {n(cz["trigger"][0])} | {m(cz["trigger"][1])} | {m(cz["trigger"][2])} | {m(cz["trigger"][3])} |')
w(f'| MAIIC stages higher | {n(cz["maiic_higher"][0])} | {m(cz["maiic_higher"][1])} | {m(cz["maiic_higher"][2])} | {m(cz["maiic_higher"][3])} |')
w('')
mat = ', '.join(f'({r["their"]},{r["ours"]}) {r["n"]}' for r in R['stage_matrix'])
w(f'Stage matrix (MAIIC stage, system stage), loans: {mat}. The loans that move the ECL most are in Appendix D.')
w('')
w('- **Which arrears.** The model\'s ageing columns (`Loan Book!Z:AE`) are typed values; its stage comes from the bracket (1-30 days Stage 1, 31-180 Stage 2, over 181 Stage 3). The system counts from the oldest unpaid instalment on the instalment chart in force at the month-end (including E-Banker\'s superseded and dated copies of the chart), against the cash received to that date net of reversals and write-offs. It does not take E-Banker\'s Loan Book Report on trust: where the report ages a loan with nothing yet due, or dates a whole chart on one day, the chart and the ledger stand.')
w('- **Confirmed against the ledger.** For the loans that move most (Chipala 45 days, two instalments unpaid; Pinnacle 134 days; Infracon, Ebenezer Midian and Anchor Processors four to six instalments missed; Edge View, Chanje, Nyamunyamu, Kambewu), the chart and the ledger confirm E-Banker and show MAIIC\'s typed brackets too light. MAIIC\'s model was right where E-Banker\'s report was wrong: Udon Business (paid ahead, chart collapsed onto one date), Namalowe Eco Farm, Mchinji Civil Servants SACCO and Microloan Foundation (nothing yet due at December 2025); the system now agrees with the model on all four. Chisitu Tea Blenders is the one loan MAIIC holds higher; the 135 million receipt that made it look current in E-Banker was refunded in May 2026, which proves the model right on substance.')
w(f'- **E-Banker\'s own overdue run (pack 9, `OD_01`)**, an independent count, agrees with the days past due the system stages on to the day on {n(OD["agree"])} of {n(OD["compared"])} account-months ({pc(OD["agree"] / OD["compared"])}); {n(OD["differ_over_30"])} differ by more than 30 days, led by Udon Business (the run carries an overdue date of February 2023 on a loan that is paid ahead, the same chart defect as the report).')
w('- **Four missed instalments.** The signed accounting policy (note 22.8.1) makes four consecutive missed instalments a default. The model does not apply it; the system does, and only on a balance still owed, on the chart in force, net of reversed receipts.')
w('- **Tenor.** The model uses 181 days for every loan; the system uses 91 days for facilities of twelve months or less (RBM DFI directive), 181 otherwise.')
w('- **Refinancing.** Pack 10 (`RF_01`) shows The Food Empire\'s loans paid off from a new loan: 104420000043 into 104450000068 on 30 and 31 December 2025 ("Offsetting loan with a new loan"), and 104450000068 from the drawdown of 106050000003 on 24 November 2025 (239.1 million of the new loan\'s first-day drawdowns). Malawi Police SACCO\'s March 2026 repayments were reallocated from 104430000072 to 106050000006. E-Banker keeps no refinance-link column (`DD_20`), and JVD Agro\'s pay-off of 104460000109 on 31 March 2026 (273.0 million) has no leg on its new loan 104460000120 drawn the same day. The system follows contract numbers, so a distressed refinancing currently looks like a repayment of the old loan and a new Stage 1 loan; how to follow the exposure is question D6 for Dr Thom.')
w('')

w('### 2.2 Probability of default')
w('')
w('| | MAIIC model | System |')
w('|---|---|---|')
w(f'| Source | S&P average cumulative default rates for emerging-market corporates, 1981-2024 (`PDs-S&P`), mapped from the arrears bracket: 1-30 days grade 3 (A), 31-90 grade 6 (B), 91-180 grade 8 (CCC), over 181 grade 10 (default) | MAIIC\'s own defaults: each Stage 1 and Stage 2 loan at the start of a 12-month window followed to its end, weighted by the balance at the start; a default is Stage 3 at the end, leaving the book unpaid, or a write-off in the window |')
w(f'| Stage 1 | 0.03% for every loan, times 1.1575 = 0.035% | {pc(book["2025-12"][("book", "1")]["observed_rate"])} pooled ({pd_dec_1[2]} of {pd_dec_1[0]} loans; {m(pd_dec_1[3])} of {m(pd_dec_1[1])} million); FInES Industrial {pc(book["2025-12"][("portfolio:6", "1")]["applied_rate"])} and FInES Agricultural {pc(book["2025-12"][("portfolio:7", "1")]["applied_rate"])} on their own cohorts; EAD-weighted {pc(d1["pd12"])} |')
w(f'| Stage 2 | 12-month rate of grade B or CCC {pc(MS["2"]["ak"] / MS["2"]["ead"])}; cumulative to maturity {pc(MS["2"]["al"] / MS["2"]["ead"])}; applied with the macro factor {pc(MS["2"]["ar"] / MS["2"]["ead"])} (EAD-weighted) | 12-month {pc(book["2025-12"][("book", "2")]["observed_rate"])} ({pd_dec_2[2]} of {pd_dec_2[0]} loans; {m(pd_dec_2[3])} of {m(pd_dec_2[1])} million); lifetime over the remaining term {pc(d2["pdlt"])} (EAD-weighted) |')
w('| Stage 3 | 100% | 100% |')
w('| Forward-looking | PD x (1 + 15.75%): the factor is the forecast T-bill rate (`TBRateForecasts` row 20), not the "NPL change" row headed as the PD adjustment (27.2%) | No approved forward-looking model on a clean install: the bootstrap scenario set carries each scenario at the measured PD, so the adjustment is nil and the month record reconciles |')
w('')
w('**Four problems in the model\'s PD table.**')
w('')
w('- **Grade 1 sits on the wrong row.** In `PDs-S&P` the label of internal grade 1 ("1-Low to fair risk-AAA-Investment Grade") is on row 15, S&P\'s aggregate "Investment Grade" row (0.08% at one year, 1.44% at ten), while the AAA row (row 8) carries no grade and zeros. A loan graded 1, the best grade, would get a higher PD than one graded 2 or 3. No loan in the December 2025 model is graded 1 (every Stage 1 loan is mapped to grade 3), so it does not change 488.5 million, but the table cannot be used as it stands.')
w('- **The top rows are not S&P\'s cumulative rates.** AAA and AA are zero at every horizon and A is 0.03% at every horizon from one to ten years. A cumulative default rate rises with the horizon; a flat row is not one.')
w('- **The arrears bracket is the grade.** Every loan 1-30 days past due is grade A, whatever the borrower, so the PD says nothing about the borrower and everything about the (typed) bracket.')
w('- **The macro factor is the T-bill rate itself**, as noted above (question D3).')
w('')
w(f'**The system\'s rates, loan by loan (Appendix C).** December 2025 window: Stage 1 defaults include MMC (five instalments missed), Happie Foods (disputed: E-Banker holds two schedules, question C1), The Food Empire (refinanced in distress), Premium Meat, Nyamunyamu, Malasha and 7 Arts Holdings; five are small matured loans with residuals of 0.07 to 2.3 million that were written off in June 2026. Stage 2 defaults include Infraworks (403.6 million, 277 days), Kings Foundation Schools (196.7 million) and Sanwecka, both held by the instalment trigger. September 2026 window: Stage 1 {pc(book["2026-09"][("book", "1")]["observed_rate"])} and Stage 2 {pc(book["2026-09"][("book", "2")]["observed_rate"])}; Malawi Police SACCO (742.0 million at the start) counts as a Stage 1 default for a residual of 2.2 million left past maturity after it was refinanced, which alone is about 14 points of the Stage 1 rate.')
w('')
w(f'**What changed since the addendum of 14 October** (Stage 1 15.30%, Stage 2 45.35% then). The instalment chart is now read from E-Banker\'s dated chart copies as well (built with the restructure register). That removes BenCal Technologies (37.6 million) from the Stage 1 defaults (its chart, held only in the copies, shows it paid ahead) and adds 7 Arts Holdings (63.6 million; six instalments unpaid at December 2025); in Stage 2 it adds Kings Foundation Schools (four unpaid) and Sanwecka (six unpaid). Hence {pc(book["2025-12"][("book", "1")]["observed_rate"])} and {pc(book["2025-12"][("book", "2")]["observed_rate"])}.')
w('')
w('**Short history.** E-Banker\'s stored Loan Book Report begins in December 2024, so the first window the system can measure ends in December 2025. Every earlier month is refused with the reason (fewer than the governed minimum of Stage 2 observations), not filled. Each window holds 30 to 50 loans with a balance, and a few large defaults move the rate by many points. These are measured rates, not errors; how much weight one year of a small book should carry is the governance question D4.')
w('')

w('### 2.3 Loss given default')
w('')
w('| | MAIIC model | System |')
w('|---|---|---|')
w(f'| Method | 1 - (collateral execution value x haircut, discounted over its realisation period) / exposure, summed per customer; nil where collateral covers the exposure | Cohort workout: the Stage 3 loans of a year earlier followed for twelve months; LGD = (1 - cure rate) x (1 - discounted recovery rate), by portfolio where the cohort is large enough |')
w(f'| December 2025 | EAD-weighted {pc(MS["1"]["lgd"] / MS["1"]["ead"])} / {pc(MS["2"]["lgd"] / MS["2"]["ead"])} / {pc(MS["3"]["lgd"] / MS["3"]["ead"])} by stage | Pooled {pc(lgd_dec["lgd"])} ({len(R["lgd_cohort_2025-12"])} loans, {m(lgd_dec["s3"])} million; cure {pc(lgd_dec["cure_rate"])}, recovery {pc(lgd_dec["recovery_rate"])}); MAIIC Industrial {pc(lgdb["2025-12"]["portfolio:2"]["lgd"])}, MAIIC Agricultural {pc(lgdb["2025-12"]["portfolio:3"]["lgd"])}, FInES Agricultural {pc(lgdb["2025-12"]["portfolio:7"]["lgd"])} |')
w(f'| September 2026 | no model | Pooled {pc(lgd_sep["lgd"])} ({len(R["lgd_cohort_2026-09"])} loans, {m(lgd_sep["s3"])} million; cure {pc(lgd_sep["cure_rate"])}, recovery {pc(lgd_sep["recovery_rate"])}) |')
w('| Recoveries | Counted twice: in the collateral LGD, and again as 46.98% of EAD "recovered" a year out (`ECLComp!CB7`, a typed constant with no source), deducted from every loan including performing ones | Counted once: the cash defaulted loans actually returned, discounted |')
w('| Collateral | In the LGD | Not yet: the security register covers too few active loans to measure it (31 active accounts have no security on record; Credit owes the list) |')
w('')
w(f'**The 47% deduction.** `ECLComp!BC = MAX(PD x LGD x (EAD - PV(FCL)), 0)`, with PV(FCL) the present value of 46.98% of EAD received 360 days after the reporting date. The workbook\'s own methodology note says MAIIC has no recovery history ("No Historical Data Available (New Bank)"). Under IFRS 9 a measured recovery belongs in the LGD once (B5.5.55, B5.5.44). Removing it adds {m(E[1] - E[0])} million on MAIIC\'s own PD and LGD. The system does not copy it.')
w('')
cures = [r for r in R['lgd_cohort_2025-12'] if r['kind'] == 'cure']
w(f'**The December 2025 cohort, cure by cure.** {", ".join(nm(r["name"]) + " " + m(r["start"]) for r in sorted(cures, key=lambda r: -r["start"]))} million. MUSECO, VNC Bricks, African Honey and Nature\'s Gift are genuine cures on the ledger; Chisitu Tea Blenders is a cure only on the 135 million later refunded (without it the cure rate is about 25% and the LGD about 58%, question F3). Recoveries are ledger receipts net of reversals; written-off loans left at nil are losses, not cures.')
w('')

w('### 2.4 Exposure at default')
w('')
w('| | MAIIC model | System (one rule in every engine and report, `EclExposure`) |')
w('|---|---|---|')
w(f'| Drawn | Carrying amount | Carrying amount, the same to the cent |')
w(f'| Undrawn | {m(maiic_undrawn)} million added, which equals the guarantees of note 24, not undrawn loan commitments | Approved less drawn, weighted by the facility\'s utilisation, only while the facility can still be drawn (nil after maturity or expiry): {m(our_undrawn_dec)} million at December 2025 |')
w(f'| Written-off loans | Out of the model | Nil exposure, including any undrawn amount ({n(R["written_off_nil_2025-12"]["n"])} loans at December 2025, {n(R["written_off_nil_2026-09"]["n"])} at September 2026) |')
w(f'| Credit balances | - | Nil, never a negative exposure ({n(R["credit_2025-12"]["n"])} loans in credit by {m(-R["credit_2025-12"]["amt"])} million at December 2025) |')
w(f'| MUSECO | Written off at December 2025 (nil) | Carried at {m(next((o["gross"] for o in walk["only_ours"] if o["cid"] == "104420000005"), None))} million: E-Banker had not written it off and it repaid in full on 6 February 2026 (question F2) |')
w(f'| Mega Farm | Gross including the full 15% interest | The same gross; the government\'s 67% interest legs are interest, not drawings, and their contra on GL 2071 is left out; the 2024 season taken on in July 2024 counts as drawn; undrawn sanctions end at the loan master\'s expiry |')
w('')
w(f'Total EAD at December 2025: model {m(mt("ead"))} million, system {m(D["ead"])} million; the EAD step of the walk is {sm(E[5] - E[4])} million.')
w('')

w('### 2.5 The walk from 488.5 million, loan by loan')
w('')
w('Each step changes one input on every matched loan and keeps the rest. Stage 2 is taken at grade B in the restaging step, since the model\'s CCC grade cannot be inferred for a loan it held in Stage 1.')
w('')
w('| Step | Stage 1 | Stage 2 | Stage 3 | Total | Change |')
w('|---|---:|---:|---:|---:|---:|')
bsw = walk['by_stage']
for i in range(6):
    w(f'| {labels[i]} | {m(bsw.get("1", [0] * 7)[i])} | {m(bsw.get("2", [0] * 7)[i])} | {m(bsw.get("3", [0] * 7)[i])} | {m(E[i])} | {"" if i == 0 else sm(E[i] - E[i - 1])} |')
w(f'| Discounting at the EIR (matched loans) | {m(bsw.get("1", [0] * 7)[6])} | {m(bsw.get("2", [0] * 7)[6])} | {m(bsw.get("3", [0] * 7)[6])} | {m(E[6])} | {sm(E[6] - E[5])} |')
w('')
w(f'Stage columns are the system\'s stages. Outside the matched loans: the model\'s "Various closed accounts" row ({m(sum(u["exposure"] for u in walk["unmatched_theirs"]))} million, nine small balances held separately in the system) and the system\'s loans the model leaves out (ECL {m(walk["only_ours_ecl"])} million, mostly MUSECO). Compared with the walk of 13 October (staging +2,240.9, PD +1,506.5, LGD -432.6): the staging step is now {sm(E[2] - E[1])} million (four loans moved down to agree with MAIIC after the verification, partly offset by the instalment trigger read on E-Banker\'s dated chart copies), the PD step {sm(E[3] - E[2])} million, and the LGD step {sm(E[4] - E[3])} million, because the loans wrongly in Stage 3 at December 2024 (Microloan Foundation, Lenziemill, Nyamunyamu) and the false cures are out of the cohort.')
w('')

w('### 2.6 Discounting')
w('')
w(f'IFRS 9 measures the allowance as the present value of the cash shortfalls at the EIR (5.5.17(b), B5.5.44). MAIIC\'s model discounts only the typed recovery and the collateral; the loss itself is not time-phased. The system projects each loan\'s shortfalls month by month and discounts them at the locked EIR (the current EIR for a floating-rate loan); a Stage 3 loan with no recovery plan is measured at the reporting date and needs no rate.')
w('')
w('| Basis of the allowance | 31 Dec 2025 loans | ECL | 30 Sep 2026 loans | ECL |')
w('|---|---:|---:|---:|---:|')


def dsrow(label, srcs, p):
    rows = [x for x in ds[p] if x['src'] in srcs]
    return sum(x['n'] for x in rows), sum((x['disc'] if x['status'] == 'CALCULATED_TIME_PHASED' else x['ecl']) or 0 for x in rows)


for label, srcs in (('Discounted at the locked EIR (original, or current for a floating-rate loan)', ('EIR_ORIGINAL', 'EIR_CURRENT_FLOATING')),
                    ('Stage 3, loss measured at the reporting date (no rate needed)', ('NONE_LOSS_AT_REPORTING_DATE',)),
                    ('No exposure (nil, credit or written off)', ('NO_EXPOSURE', 'NO_EXPOSURE_WRITTEN_OFF')),
                    ('No locked EIR: not discountable, shown undiscounted', ('-',))):
    a, b = dsrow(label, srcs, '2025-12'), dsrow(label, srcs, '2026-09')
    w(f'| {label} | {n(a[0])} | {m(a[1])} | {n(b[0])} | {m(b[1])} |')
w(f'| **Allowance shown** | **{n(D["n"])}** | **{m(shown_dec)}** | **{n(S["n"])}** | **{m(shown_sep)}** |')
w('')
w(f'On the loans discounted at an EIR, discounting lowers the ECL by {m(disc_effect_dec)} million at December 2025 and {m(disc_effect_sep)} million at September 2026. The unresolved loans are Stage 1 and Stage 2 loans whose EIR is blocked by the fee review (section 3.2); each month record names them and the record reconciles the discounted allowance to the scenario build-up. ' + ('The December 2025 and September 2026 records are signed off.' if all(r['status'] == 'SIGNED_OFF' for r in R['fli_records'] if r['p'] in ('2025-12', '2026-09')) else 'Each record waits for sign-off by a second person with a commentary; the person who recorded it cannot sign it off.'))
w('')

w('### 2.7 Mega Farm')
w('')
mm = mc['matrix']
w(f'The programme\'s carrying amount agrees with MAIIC\'s model to the cent ({m(mfd["gross"])} million; all {n(mc["matched"])} of the model\'s accounts matched by account number) and, month by month, with E-Banker\'s own Loan Book Report: on 77,842 of the 78,023 account-months with a report run the ledger rebuilds the report to the cent once the government\'s 67 percent interest legs are kept and their contra on GL 2071 is left out; on {n(mftie.get("1", 0))} of the rest the report prints an overpaid account\'s credit balance as a debit (May to July 2026) and the ledger stands, flagged. To September 2026 the interest split is {m(mfsplit["own"])} million on MAIIC\'s own interest GLs (33 percent) and {m(mfsplit["gov"])} million on the government-portion GLs (67 percent).')
w('')
w(f'**Staging.** Of the model\'s {n(mc["model_stage"]["3"][0])} Stage 3 accounts, {n(mm.get("3/1", [0])[0])} ({m(mm.get("3/1", [0, 0])[1])} million) are Stage 1 in the system at December 2025, because E-Banker records their maturity as 31 December 2025: on the reporting date they are not yet past due. They become Stage 3 in March 2026, 91 days later (the short-term threshold). The statements treat the season as in default at the year end. The governed way to reflect that evidence is a qualitative credit-impaired trigger approved by two people in the SICR register, not an engine change (question D1).')
w('')
w(f'**Measurement.** December 2025 cannot be measured: the programme LGD is a cohort workout, and no programme loan was in Stage 3 in December 2024. The module says so rather than use a typed LGD. From January 2026 it measures: at September 2026 the programme PD is MAIIC\'s agricultural PD scaled to the programme\'s own default experience (scalar {mfs["scalar"]:.2f}; Stage 1 {pc(mfs["pd_by_stage"]["1"])}), the LGD {pc(mfs["lgd"])} ({mfs["lgd_basis"]}), and the programme ECL {m(mfs["programme_ecl"])} million. MAIIC\'s share follows the governed setting, 5 percent ({m(mfs["maiic_ecl"])} million); E-Banker splits the interest 33/67, and the setting is held for Dr Thom (question D2).')
w('')

# ================================================================ 3
w('## 3. The effective interest rate')
w('')
w('### 3.1 What MAIIC has computed by hand')
w('')
w('- **Dr Thom\'s EIR assessment at 31 December 2025** (34 loans disbursed in 2025, with their legal and arrangement fees) is the only MAIIC EIR working for MAIIC\'s own loans. Its EIR is the contractual rate x (1 + fees / principal): a 30 percent loan with fees of 4.4 percent of principal is given 31.3 percent whatever its term. That is not an effective interest rate. A one-off fee raises the yield by roughly the fee percentage divided by the life in years, so the uplift on a one-year loan is several points and on a five-year loan about one.')
w('- **Tamanda\'s take-on workbook** (returned 13 October 2026) carries the schedules and fees of the loans taken on at 31 July 2024, not EIRs. Its Mapping columns X and Y (Finance\'s confirmation of each mapping) are still blank.')
w('- **The 2025 accounts** carry no loan-level EIR. The EIR interest line for 4206 (2,387.7 million) is E-Banker\'s posted interest plus 259.3 million of arrangement fees moved in the TB-to-AFS mapping (an auditor\'s estimate from a sample) and the 21.9 million suspense reversal. The system\'s output is compared with that, not reconciled to it.')
w('')
w('### 3.2 Where the EIR stands on a clean install')
w('')
w('| Contracts | Count | Why |')
w('|---|---:|---|')
w(f'| Solved and locked under the bootstrap label | {n(eir_counts["LOCKED"])} | {n(eir_counts["LOCKED"] - locked_takeon)} generated schedules and {n(locked_takeon)} take-on schedules with no fee on record |')
w(f'| Blocked: fees on record not yet classified by Finance | {n(cats.get("fee review only", 0))} | The engine refuses to solve a loan with an unreviewed fee rather than ignore it; the bootstrap never reviews a fee (maker-checker) |')
w(f'| Blocked: take-on schedule refused, and fees | {n(cats.get("take-on check refused (and fee review)", 0))} | COMSIV 104430000004 (the block is an account history that leaves 9.2 million owed) and BenCal 104460000059 (36 instalments typed on one date) |')
w(f'| No schedule | {n(cats.get("no schedule", 0))} | Closed or never-drawn accounts, and Ecogen 104460000111 (86.8 million at September 2026), which has no contractual rate in the contract master |')
w(f'| **Total** | **{n(sum(eir_counts.values()))}** | |')
w('')
w(f'**Fees on record, all PENDING classification.** {n(gl_fee[0])} GL fee postings on {n(gl_fee[2])} loans ({m(gl_fee[1])} million) from the new GL fee route; {n(los_fee[0])} LOS charge lines on {n(los_fee[2])} loans ({m(los_fee[1])} million), with {n(los_sup[0])} more ({m(los_sup[1])} million) superseded by a GL posting so no fee counts twice; {n(tk_fee[0])} take-on workbook lines on {n(tk_fee[2])} loans ({m(tk_fee[1])} million). A further {n(fgu["n"])} GL fee postings ({m(fgu["amt"])} million) are held unassigned because no loan account is a leg of their batch; {n(fgu["cand_n"])} of them ({m(fgu["cand_amt"])} million) carry a candidate loan for a person to confirm. The fee GL gate ties the 2025 fee accounts to the trial balance to the cent (4871 legal 171,128,135.00; 4873 arrangement 311,342,792.00).')
w('')
w('### 3.3 Facilities drawn in tranches (E1, fixed)')
w('')
w('The 13 October review found that a generated schedule charged interest on the whole facility from the signing date, so a facility drawn over months solved to an EIR far above its rate. The schedule now follows the tranche register built from the ledger (one row per drawdown; a fee debited at drawdown is part of that drawdown, never a tranche of its own); the solve starts on the first drawdown. Rerun on a copy of the fresh bootstrap on 14 October 2026, with no fee and, for illustration only, with the fees on record treated as integral in a rolled-back transaction:')
w('')
w('| Facility | Tranches (ledger) | Rate | EIR, no fee, 13 Oct | EIR, no fee, now | With the fees on record (illustration) |')
w('|---|---|---:|---:|---:|---:|')
tr = {t['contract_id']: t for t in R['tranches']}
for cid, name, rate, b, a, fee in (('104430000085', 'Mphunzitsi SACCO', '29.00', '41.32', '29.07', '35.68'), ('104430000068', 'Nyamunyamu Processors', '29.20', '40.63', '29.14', '32.63'),
                                   ('104430000074', 'Sycamore Credit', '26.10', '34.28', '26.08', '31.85'), ('104430000086', 'Mchinji Civil Service SACCO', '27.20', '32.50', '28.64', '32.49'),
                                   ('104460000114', 'Namuleri Finance', '10.00', '10.72', '10.00', '12.00')):
    t = tr.get(cid)
    tt = f'{t["n"]}: {m(t["amt"])} m, {t["first"][:10]} to {t["last"][:10]}' if t else 'one drawdown with its fees'
    w(f'| {name} {cid} | {tt} | {rate} | {b} | {a} | {fee} |')
w('')
w(f'Every facility with a schedule now solves with no fee within about 0.2 point of its rate on a monthly loan. On the clean install these loans are blocked by the fee review, so their EIRs are not yet locked. {n(len(R["tranches"]))} facilities have more than one drawdown in the register.')
w('')
w('### 3.4 The take-on EIRs (E2, fixed)')
w('')
w(f'The take-on build reads each workbook block as a balance chain (a rise the line does not explain is money advanced, a fall outside the instalments is money received) and solves from the date the schedule starts charging interest. The governed check `takeon_eir_plausibility` asks the no-fee solve to give back the rates the lines charge (0.5 point either side) and refuses a block that does not retire the loan. Result: {n(tk.get("PASS", {}).get("n"))} pass ({pc(tk.get("PASS", {}).get("lo"))} to {pc(tk.get("PASS", {}).get("hi"))}), {n(tk.get("REFUSED", {}).get("n"))} refused (COMSIV, BenCal; for Tamanda), {n(tk.get("NONE", {}).get("n"))} kept on their take-on balance. On 13 October 21 of 73 were implausible (negative, or up to 317 percent); each cause is fixed or with Tamanda. Passing take-on EIRs now go through Schedule Review and the normal lock; {n(locked_takeon)} are locked, the rest wait on their take-on fees ({n(tk_fee[0])} lines).')
w('')
w('### 3.5 Dr Thom\'s 34 loans against the system')
w('')
w('| Account | Borrower | Rate (Thom) | EIR (Thom) | Fees (Thom) | Fees on record now | Source | System status |')
w('|---|---|---:|---:|---:|---:|---|---|')
srcname = {'E-Banker GL fee posting': 'GL', 'E-Banker LOS_DISB_CHARGES_POST': 'LOS', 'TAKEON_WORKBOOK': 'take-on'}
blocked_fee_thom = locked_thom = nosched_thom = 0
for t in thom:
    cid = t['acct'].lstrip('0')
    e = eall.get(cid, {})
    s = e.get('calculation_status') or '-'
    srcs = fbc.get(cid, {})
    tot = sum(srcs.values())
    src = ', '.join(srcname.get(k, k) for k in srcs) or '-'
    if s == 'LOCKED':
        stx = f'Locked at {pc(e["eir_nominal_annual"], 2)} (no fee on record)'
        locked_thom += 1
    elif s == 'BLOCKED':
        stx = 'Blocked: fee review'
        blocked_fee_thom += 1
    else:
        stx = 'No schedule'
        nosched_thom += 1
    w(f'| {t["acct"]} | {t["name"][:30]} | {fl(t["rate_thom"]) * 100:.2f} | {fl(t["eir_thom"]) * 100:.2f} | {m(fl(t["fees_thom"]))} | {m(tot)} | {src} | {stx} |')
w('')
w(f'Of the 34, {locked_thom} are locked with no fee on record, {blocked_fee_thom} blocked by the fee review and {nosched_thom} without a schedule. The fees that were missing from E-Banker\'s LOS charges on 13 October (Mphunzitsi 47.7 million, Pinnacle 46.1, Chipala 45.3, Ebenezer Midian 22.1 and others) now reach the loans through the GL fee route where the ledger ties them to the loan. Four of Dr Thom\'s fees are in no E-Banker fee source the system reads: Chipala (45.3 million), Ebenezer Midian 104430000070 (22.1), Doorbell (1.4) and Mthirakuwiri 104460000110 (7.7); the first three are therefore locked without a fee. Mthirakuwiri\'s second pair of fee postings (5.6 million) is held, because the narration names the customer and the customer has two loans. Dr Thom\'s two Mthirakuwiri rows also look transposed against E-Banker (rate 10 against 26.2 percent, and 33 against 10). Finance and Tamanda to confirm (F1, T1). With the fees treated as integral (illustration, 14 October), the system\'s fee effect on short loans is several points against Dr Thom\'s 0.2 to 2.8 (Mphunzitsi 35.68 against 29.07 with no fee).')
w('')
w('### 3.6 Amortised cost, revenue and Stage 3')
w('')
y25 = rev.get('2025', {})
y26 = rev.get('2026 (Jan-Sep)', {})
a25 = ac['2025-12']
a26 = ac['2026-09']
w('| Locked loans on the clean install | 2025 | Jan-Sep 2026 |')
w('|---|---:|---:|')
w(f'| Roll-forward rows | {n(y25.get("rows"))} | {n(y26.get("rows"))} |')
w(f'| EIR interest recognised (plus unwind on Stage 3) | {m(y25.get("eir"))} (+{m(y25.get("unwind"))}) | {m(y26.get("eir"))} (+{m(y26.get("unwind"))}) |')
w(f'| Interest posted by E-Banker on the same loans and months | {m(y25.get("posted"))} | {m(y26.get("posted"))} |')
w(f'| Difference | {sm(y25.get("eir", 0) + y25.get("unwind", 0) - y25.get("posted", 0))} | {sm(y26.get("eir", 0) + y26.get("unwind", 0) - y26.get("posted", 0))} |')
w(f'| Amortised cost against the ledger at the year or period end (loans) | {m(sum(x["ac"] for x in a25))} against {m(sum(x["ca"] for x in a25))} ({len(a25)}) | {m(sum(x["ac"] for x in a26))} against {m(sum(x["ca"] for x in a26))} ({len(a26)}) |')
w('')
stops = [s for s in R['rf_stops'] if s['next']]
w(f'**Stage 3 revenue on the booked allowance.** A Stage 3 loan accrues interest on its net carrying amount (IFRS 9 5.4.1(b)): gross less the booked allowance at the start of the month. The engine now reads only the booked (discounted) allowance and refuses a month without one, rather than fall back to an undiscounted figure or nil, and a month whose previous roll-forward row is missing is refused rather than opened from an older balance. Consequence: the booked allowance exists only from December 2025, so a locked loan that reached Stage 3 earlier has no revenue after that month. {n(len(stops))} locked loans stop this way: {", ".join(nm(s["name"]) + " (after " + s["last"] + ")" for s in stops)}. This is correct fail-closed behaviour, and it means the 2025 revenue comparison covers only the loans that stayed performing. The full comparison needs the fee review (so the 115 blocked contracts lock) and a decision on how to open Stage 3 revenue before the first measurable ECL month (question D8).')
w('')
w('**Illustration with the fees on record treated as integral** (copy of the bootstrap, 14 October, rolled back; spec section 3.11): 118 EIRs lock and 2 stay refused; amortised cost 13,334.3 million against a ledger of 13,284.3 million at December 2025 (103 loans) and 20,332.9 against 19,834.0 million at September 2026 (111 loans); EIR interest 2,463.7 million plus 43.2 million unwind for 2025 against 2,328.9 million posted, and 3,231.5 plus 563.8 million for January to September 2026 against 3,249.7 million. The September 2026 gap is led by The Food Empire\'s take-on accounts, whose balances were re-booked to other accounts while their roll-forward continued, and by Stage 3 loans E-Banker no longer charges. These are not booked figures; they show the order of magnitude once Finance has classified the fees.')
w('')
w('### 3.7 Restructures and rate resets')
w('')
w(f'- **Restructure register (built 14 October).** {n(res_n)} candidates from E-Banker\'s own records: chart regenerations ({n(res_by_src.get("CHART_REPLACED", 0))}), differing dated chart copies ({n(res_by_src.get("CHART_COPY", 0))}), accepted `MODIFICATION_LOG` edits ({n(res_by_src.get("MODIFICATION_LOG", 0))}), the migration worksheet\'s pre-take-on restructures ({n(res_by_src.get("OPENING_BAL", 0))}) and rate moves on fixed-rate loans ({n(res_by_src.get("RATE_CHANGE", 0))}). The engine suggests a modification for {n(res_mod)}, including 81.8 million capitalised on Pinnacle Financial Services on 30 June 2026. None is confirmed: one person classifies with a reason (and the Board approval of directive paragraph 16(1)), a different person approves, and only then does IFRS 9 5.4.3 modification accounting (the 10% test, derecognition) run.')
w(f'- **Rate resets.** One detector writes the prime-rate resets and rate periods ({n(R["reset_rows"]["n"])} rate periods); the register holds {n(rs_reset[("PLR_RESET", "DETECTED")])} detected resets, {n(rs_reset[("PLR_RESET", "BLOCKED")])} blocked (no locked EIR yet) and {n(rs_reset[("SPREAD_CHANGE", "DETECTED")])} spread changes, each for classification under maker-checker. A floating-rate loan\'s EIR moves with the rate E-Banker charged each month (B5.4.5), and its ECL is discounted at the current EIR.')
w('')

# ================================================================ 4
w('## 4. What changed, what remains, and what needs MAIIC')
w('')
w('### 4.1 Fixed since the review of 13 October 2026 (all committed, each with tests)')
w('')
fixes = [
    ('Mega Farm kept out of MAIIC\'s PD, LGD and ECL', 'the book is split into its programmes before the engines run; a blended book is refused (it had set MAIIC\'s Stage 1 PD at 92.7%)'),
    ('Mega Farm ledger tied to E-Banker', 'government 67% interest legs kept and their GL 2071 contra left out; sign-error months: the ledger stands; July 2024 take-on counted as drawn; expiry from the loan master'),
    ('Mega Farm without typed numbers', 'no 0.45 LGD and no scalar from the statements; a month that cannot be measured is declined with the reason; Stage 3 PD compared as a string (was understated by about 4.0 billion a month)'),
    ('Discounted month-end ECL every month', 'the bootstrap runs the whole month-end chain for every month; December 2025 is booked on 66 of 123 loans (18 before)'),
    ('Booked allowance in every report', 'one definition (`BookedEcl`): discounted at the EIR where it can be, the rest named and shown undiscounted, with the reconciliation'),
    ('Month record reconciles the discounted allowance', 'a loan missing a scenario PD makes the record refuse; sign-off needs a second person and a commentary'),
    ('One EAD rule (`EclExposure`)', 'credit balances nil; written-off loans nil including their undrawn amount; no undrawn amount after maturity'),
    ('Horizon-proxy discounting removed', 'no discount without a locked EIR; floating-rate loans at the current EIR; Stage 3 with no recovery plan needs no rate'),
    ('Staging reads the chart in force', 'including superseded and dated chart copies; collapsed charts dated by serial; report ageing with nothing due not accepted'),
    ('Instalment trigger on net cash', 'reversed receipts and write-offs are not payments; only balances still owed; charts that ask more than the ledger charged are not read'),
    ('False defaults removed from PD and LGD', 'nil and credit balances are not observations; write-offs are defaults; a written-off loan at nil is not a cure'),
    ('E1: tranche-timed schedules', 'Mphunzitsi 41.3% to 29.07% with no fee; partly-drawn interest basis read from E-Banker\'s own postings'),
    ('E2: take-on EIRs into the engine', 'balance-chain reading, plausibility check, Schedule Review and the normal lock'),
    ('E3: GL fee route and fee precedence', 'fees credited in the drawdown batch reach the loan; held lines never guessed; a fee never counts twice'),
    ('Stage 3 revenue on the booked allowance', 'no fallback to undiscounted or nil; a month with a missing prior roll-forward is refused'),
    ('Restructure register and modification accounting', '70 candidates under maker-checker; 5.4.3 accounting and the 10% test on confirmed events only'),
    ('Rate resets reconciled', 'one detector; the reset register and the classification on the same rows'),
    ('Write-offs from one source', 'ledger write-offs and the approved register through the note, the reconciliations and the RBM return'),
    ('Packs 9 and 10 landed', 'the migration worksheet (`CV_10`, ties to the take-on postings), E-Banker\'s overdue run (`OD_01`, `OD_02`) and the refinance batch legs (`RF_01`)'),
    ('Controls', 'schedule approval by a second person; period locks in every writer; staging thresholds and the take-on build under maker-checker'),
]
w('| Fix | What it does |')
w('|---|---|')
for a, b in fixes:
    w(f'| {a} | {b} |')
w('')
w('### 4.2 Genuine differences that remain (method or data, not errors)')
w('')
w('| Difference | Effect at December 2025 | Who settles it |')
w('|---|---|---|')
w(f'| Ageing: E-Banker\'s chart and ledger against MAIIC\'s typed brackets | +{m(E[2] - E[1])} m | Dr Thom (D5) |')
w(f'| PD: own observed defaults (one year, about 50 loans) against S&P tables | +{m(E[3] - E[2])} m | Dr Thom (D4) |')
w(f'| LGD: cohort workout without collateral against collateral plus a typed 47% | {sm(E[4] - E[3])} m, and +{m(E[1] - E[0])} m for the 47% | Dr Thom (D3, D7); Credit (collateral register) |')
w(f'| EAD: undrawn at utilisation against guarantees | {sm(E[5] - E[4])} m | Dr Thom (D7) |')
w(f'| Discounting waits on locked EIRs | {m(D["undisc"])} m on {n(D["undisc_n"])} loans not yet discounted | Finance (F1) |')
w(f'| Mega Farm 2025 season: E-Banker maturity 31 December 2025 against default in the statements | {m(mm.get("3/1", [0, 0])[1])} m staged 1 against 3; no programme ECL | Dr Thom (D1, D2) |')
w('| MUSECO: carried by E-Banker (repaid February 2026), written off in the statements | 138.1 m gross | Finance (F2) |')
w('')
w('### 4.3 What needs MAIIC')
w('')
w('| # | Who | What | Why it matters |')
w('|---|---|---|---|')
qs = [
    ('D1', 'Dr Thom', 'Mega Farm 2025 season: record the credit-impaired evidence at 31 December 2025 as a qualitative trigger (two approvals in the SICR register)?', f'{m(mm.get("3/1", [0, 0])[1])} million staged 1 by the system, 3 by the statements'),
    ('D2', 'Dr Thom', 'Mega Farm treatment (D30) and MAIIC\'s share: 5 percent in the governed setting or E-Banker\'s 33/67 interest split; the final models behind note 8 (475.7 million; Mega Farm 39,761.4 plus 2,105.2) and the 166.4 million unexplained', f'MAIIC\'s share of {m(mfs["programme_ecl"])} million at September 2026'),
    ('D3', 'Dr Thom', 'Where the 46.98% "Recovered / From NPL" comes from; whether the macro factor should be the T-bill rate (row 20) or the NPL change (row 23); the grade 1 row in `PDs-S&P`', f'+{m(E[1] - E[0])} million; the model\'s PD level'),
    ('D4', 'Dr Thom', 'A governed credibility blend of the measured PD with an external benchmark while the history is one year of about 50 loans', 'The level of every Stage 1 and 2 allowance'),
    ('D5', 'Dr Thom / Finance', 'The source of the ageing in the model\'s `Loan Book` sheet, given that the chart and the ledger confirm E-Banker on the loans that move most', f'+{m(E[2] - E[1])} million'),
    ('D6', 'Dr Thom', 'Distressed refinancings (The Food Empire, JVD Agro, Malawi Police SACCO): should the PD and LGD follow the exposure into the new account? A materiality threshold for residual arrears (Malawi Police SACCO\'s 2.2 million makes 742 million a default)', 'About 14 points of the September 2026 Stage 1 PD; JVD Agro would raise Stage 2'),
    ('D7', 'Dr Thom', 'Whether the model\'s off-balance-sheet 1,720.8 million is the guarantees of note 24, and whether guarantees belong in the loan ECL', 'EAD'),
    ('D8', 'Dr Thom', 'How Stage 3 revenue opens for loans that defaulted before December 2025, the first month with a booked allowance; the scenario set to approve in place of the bootstrap set', 'Revenue on Stage 3 loans; the forward-looking adjustment'),
    ('F1', 'Finance', f'Classify the fees on record: {n(gl_fee[0] + los_fee[0] + tk_fee[0])} lines ({m(gl_fee[1] + los_fee[1] + tk_fee[1])} million) and tie the {n(fgu["cand_n"])} held GL lines with a candidate loan', f'{n(cats.get("fee review only", 0))} EIRs blocked; {n(D["undisc_n"])} loans undiscounted; revenue'),
    ('F2', 'Finance', 'MUSECO (written off in the statements, repaid February 2026; the January 2026 entry of 138.1 million); confirmation that status H means written off; Imani\'s credit and Tac-Maz\'s residual', 'Write-offs, LGD and the 2025 comparison'),
    ('F3', 'Finance', 'Chisitu Tea Blenders: restate June 2025 to April 2026 as never cured, given the 135 million refund?', 'LGD cure rate (about 6 points)'),
    ('T1', 'Tamanda', 'Tick the take-on mapping (columns X and Y); the COMSIV and BenCal blocks; the Steel Base value date; reconcile the stated take-on fees with the 280.0 million landed', 'Take-on EIRs and fees'),
    ('C1', 'Credit / ICT', 'Happie Foods 104420000062: which of the two schedules in E-Banker is the contract; Ecogen\'s contractual rate; the 31 active accounts with no security; three follow-up offer letters', 'A disputed default; one EIR; collateral in the LGD'),
    ('B1', 'Barry / vendor', 'Why the as-at Loan Book Reports re-run in September 2026 age instalments from deleted definitions, why Udon\'s and BenCal\'s instalments are dated on one day, and what status D means; Loan Book Report runs before December 2024 if they can be re-run', 'The governed days-past-due basis; PD history before December 2025'),
    ('B2', 'Barry / vendor', 'Pack 10: rebuild the stored trial balance (88,000.00 out of balance on every day from 15 May 2026, posting 368352, `TB_06`) and the Zaithwa Farms balance rows (unchanged, `ZF_04`); confirm there is no refinance link anywhere in E-Banker (`DD_20` found no column) and which batch funded JVD Agro\'s pay-off of 31 March 2026', 'Trial balance tie; refinancing evidence'),
]
for q_ in qs:
    w(f'| {q_[0]} | {q_[1]} | {q_[2]} | {q_[3]} |')
w('')
w('Pack 10 also showed that the 5 August 2024 account master (`CV_15`) holds 102 vendor test accounts, so the old 00010075 loan numbers cannot be mapped from it, and that E-Banker\'s read access is in place for 32 of 35 tables (the three others are empty, not closed).')
w('')

# ================================================================ Appendices
w('## Appendix A. Month by month')
w('')
w('MAIIC and FInES loans (system stages after the qualitative triggers). Before December 2025 the ECL is refused, not filled: the PD needs a twelve-month window of E-Banker\'s Loan Book Report, which begins in December 2024.')
w('')
w('| Month | Loans | Gross | EAD | Stage 1 / 2 / 3 | ECL undiscounted | Booked (discounted) | Not discountable (loans) | Allowance shown | Mega Farm gross | Mega Farm Stage 3 gross | Programme ECL | MAIIC 5% share |')
w('|---|---:|---:|---:|---|---:|---:|---:|---:|---:|---:|---:|---:|')
for r in R['by_period']:
    f_ = R['mf_by_period'].get(r['p'], {})
    has = r['ecl'] is not None
    pe = f_.get('programme_ecl')
    declined = f_.get('declined')
    w(f'| {r["p"]} | {n(r["n"])} | {m(r["gross"])} | {m(r["ead"])} | {n(r["n1"])} / {n(r["n2"])} / {n(r["n3"])} | {m(r["ecl"]) if has else "refused"} | {m(r["disc"]) if has else "-"} | {(m(r["undisc"]) + " (" + n(r["undisc_n"]) + ")") if has else "-"} | {m(r["shown"]) if has else "-"} | {m(f_.get("gross"))} | {m(f_.get("g3") or 0.0)} | {"declined" if declined else m(pe)} | {"-" if declined else m(f_.get("maiic_ecl"))} |')
w('')

w('## Appendix B. By portfolio and stage')
w('')
for p in ('2025-12', '2026-09'):
    w(f'**{p}**')
    w('')
    w('| Portfolio | Stage | Loans | Gross | EAD | 12-month PD | LGD | ECL undiscounted | Booked (discounted) | Not discountable | Allowance shown |')
    w('|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|')
    for r in R['portfolio_' + p]:
        w(f'| {r["portfolio"]} | {r["st"]} | {n(r["n"])} | {m(r["gross"])} | {m(r["ead"])} | {pc(r["pd12"])} | {pc(r["lgd"])} | {m(r["ecl"])} | {m(r["disc"])} | {n(r["undisc_n"])} | {m(r["shown"])} |')
    w('')

w('## Appendix C. The PD and LGD evidence, loan by loan')
w('')
for p, lab in (('2025-12', 'December 2024 to December 2025'), ('2026-09', 'September 2025 to September 2026')):
    for s_ in ('1', '2'):
        a = pdsum(p, s_)
        w(f'**PD window {lab}, Stage {s_}: {a[0]} loans, {m(a[1])} million; {a[2]} defaults, {m(a[3])} million ({pc(a[3] / a[1] if a[1] else None)}).**')
        w('')
        w('| Loan | Borrower | Start balance | Days past due at start | Days past due at end | End balance | How it defaulted |')
        w('|---|---|---:|---:|---:|---:|---|')
        for r in sorted([r for r in pdw[p] if r['start_stage'] == s_ and r['end'] == '3'], key=lambda r: -r['start']):
            how = {'trigger': 'four-missed-instalments trigger or cure period', 'days past due': 'days past due', 'written off': 'written off in the window', 'left the book': 'left the book unpaid'}.get(r['how'], r['how'])
            w(f'| {r["cid"]} | {r["name"].strip()[:34]} | {m(r["start"])} | {n(r["start_dpd"])} | {n(r["end_dpd"])} | {m(r["end_bal"])} | {how} |')
        w('')
for p, lab in (('2025-12', 'December 2024 to December 2025'), ('2026-09', 'September 2025 to September 2026')):
    c = R['lgd_cohort_' + p]
    k = defaultdict(lambda: [0, 0.0, 0.0])
    for r in c:
        k[r['kind']][0] += 1; k[r['kind']][1] += r['start']; k[r['kind']][2] += r['receipts']
    w(f'**LGD cohort {lab}: {len(c)} loans, {m(sum(r["start"] for r in c))} million.** ' + '; '.join(f'{kk}: {v[0]} loans, {m(v[1])} million at the start, {m(v[2])} million received' for kk, v in sorted(k.items())) + '.')
    w('')
    w('| Loan | Borrower | Outcome | Start balance | End balance | Received | Written off |')
    w('|---|---|---|---:|---:|---:|---:|')
    for r in sorted([r for r in c if r['kind'] != 'still Stage 3'], key=lambda r: -r['start']):
        w(f'| {r["cid"]} | {r["name"].strip()[:34]} | {r["kind"]} | {m(r["start"])} | {m(r["end"])} | {m(r["receipts"])} | {m(r["written_off"])} |')
    w('')

w('## Appendix D. Staging differences that move the ECL most (31 December 2025)')
w('')
w('| Loan | Borrower | MAIIC bracket | MAIIC stage | System stage | Days past due | E-Banker arrears over 90 days | Exposure | MAIIC ECL | System ECL | Cause |')
w('|---|---|---|---:|---:|---:|---:|---:|---:|---:|---|')
cause_txt = {'ageing': 'E-Banker ageing older', 'trigger': 'instalment trigger or cure period', 'maiic_higher': 'MAIIC higher', 'same': 'same stage'}
for l in sorted(R['walk_loans'], key=lambda l: -(l['our_ecl'] - l['their_ecl']))[:20]:
    w(f'| {l["cid"]} | {l["name"].strip()[:30]} | {l["bracket"]} | {l["their_stage"]} | {l["our_stage"]} | {n(l["dpd"])} | {m(l["eb_over90"])} | {m(l["their_ead"])} | {m(l["their_ecl"])} | {m(l["our_ecl"])} | {cause_txt.get(l.get("cause"), "")} |')
w('')

w('## Appendix E. Data ties and controls')
w('')
w('| Check | System | Reference | Result |')
w('|---|---:|---:|---|')
w(f'| MAIIC and FInES loan book, 31 Dec 2025 (GLs 1050101 to 1050401) | {ties["maiic_2025_12"]["ca"]:,.2f} | 14,841,212,779.53 (Finance\'s listing) | Ties to the cent (0.01 rounding) |')
w(f'| Mega Farm loan book, 31 Dec 2025 (GLs 1050301 to 1050307) | {ties["mf_2025_12"]["ca"]:,.2f} | {mc["model_ead"]:,.2f} (MAIIC\'s Mega Farm model) | Ties within {ties["mf_2025_12"]["ca"] - mc["model_ead"]:,.2f} kwacha (one account in credit); {n(mc["matched"])} of {n(mc["rows"])} accounts by number |')
w(f'| MAIIC model rows matched to the book on carrying amount | {walk["matched"]} of {walk["maiic_rows"]} | | MUSECO (nil in the model) and "Various closed accounts" unmatched |')
w(f'| Mega Farm ledger against E-Banker\'s Loan Book Report, every account-month with a report run | 77,842 of 78,023 tie to the cent (rebuild check, 14 October) | {n(mftie.get("1", 0))} account-months where the report prints a credit balance as a debit: the ledger stands, flagged | The one remaining account-month has no ledger posting and keeps the report\'s figure, flagged |')
w(f'| E-Banker overdue run (`OD_01`) against the days past due staged | {n(OD["agree"])} agree | of {n(OD["compared"])} account-months | {n(OD["differ"])} differ ({n(OD["differ_over_30"])} by more than 30 days); reported, not asserted |')
w(f'| Loan book, 30 Sep 2026 | {ties["maiic_2026_09"]["ca"]:,.2f} ({n(ties["maiic_2026_09"]["n"])} rows) | Mega Farm {ties["mf_2026_09"]["ca"]:,.2f} | Bootstrap verify |')
fr = {r['p']: r for r in R['fli_records']}
for p in ('2025-12', '2026-09'):
    r = fr.get(p)
    if r:
        w(f'| Month record {p}: undiscounted build-up and discounted allowance reconcile | {m(r["booked_ecl"])} / {m(r["booked_ecl_discounted"])} | {m(r["weighted_ecl"])} / {m(r["weighted_ecl_discounted"])} | {"reconciles" if r["reconciles"] and r["discounted_reconciles"] else "does not reconcile"}; {n(r["discounted_loans"])} discounted, {n(r["discount_unresolved_loans"])} named as unresolved |')
w('| Fee GL gate, 2025 | 171,128,135.00 (4871); 311,342,792.00 (4873) | Trial balance | Ties to the cent |')
w('| No Mega Farm loan in a MAIIC portfolio, any month | 0 | | Control (D30) |')
w('')
w(f'The bootstrap\'s own verify step asserts these and the pack inventories (every file\'s rows and SHA-256), the take-on worksheet against the take-on postings, the write-offs and the ECL and revenue figures; on this run 67 checks: every data tie, pack inventory, loan book, undiscounted ECL, EIR count and 2024-2025 revenue figure passes. Eight report FAIL against golden numbers set on an earlier round (commit c4acec4) that predate intended changes: the December 2025 booked allowance (now discounted on 66 loans, 3,015.7 million, against 18 loans and 449.9 million then), the Mega Farm programme ECL (44,661.8 million against 45,097.0 million before the ledger tie) and its 5% share, and the 2026 revenue rows (41 against 35). The figures in this report are those of the run; the golden numbers are to be re-set from this run.')
w('')

w('## Appendix F. How MAIIC\'s December 2025 model computes the ECL')
w('')
w('`MAIIC & FInES ECL Model - 14th December 2025_Final.xlsx` (the version sent to Deloitte on 14 January 2026), one row per facility on `ECLComp`.')
w('')
w('| Element | How MAIIC computes it | Where |')
w('|---|---|---|')
w('| ECL | PD x LGD x (EAD - PV(FCL)), floored at nil | `ECLComp!BC` |')
w('| EAD | Carrying amount plus 1,720,830,931 off balance sheet | `ECLComp!AV = AT + AU` |')
w('| PV(FCL) | 46.98% of EAD received 360 days out, discounted at the loan\'s rate; typed constant, every stage | `ECLComp!CB7`, `BE`, `BB` |')
w('| Stage | Bracket of the typed ageing in `Loan Book!Z:AE`: 1-30 Stage 1, 31-180 Stage 2, over 181 Stage 3 | `PortfolioReport!AP`; `ECLComp!P:R` |')
w('| PD | S&P emerging-market cumulative rates by grade; Stage 1 12-month, Stage 2 to maturity, Stage 3 100%; times (1 + T-bill forecast) | `PDs-S&P!B7:M18`; `ECLComp!AK:AR`; `TBRateForecasts` |')
w('| LGD | 1 - discounted collateral after haircut / exposure, per customer | `ECLComp!AS`; `ASS4-Security` |')
w('| Scenario weights | Base 40, Upside 25, Downside 1 25, Downside 2 10 | `ASS2-Macro Forecasts` |')
w('')
w('The model\'s totals: exposure 14,703,077,099; EAD 16,423,908,030; ECL 488,480,445 (Stage 1 1,356,861; Stage 2 63,134,613; Stage 3 423,988,970). The statements carry 475,738 thousand, so they came from a later version or Deloitte\'s challenger, which we do not hold. The Mega Farm model uses the same template on 3,675 accounts with a 20% typed recovery; the statements\' 39,761,406 thousand plus 2,105,157 thousand on MAIIC\'s interest receivable is Deloitte\'s challenger figure of 41,866,109,050 split in two.')
w('')

w('## Appendix G. Sources and files')
w('')
w(f'- System: fresh bootstrap at commit `{COMMIT}`, database `{DB}` (read only for this report). The bootstrap log is in the session scratchpad.')
w('- MAIIC: the December 2025 ECL models (MAIIC and FInES; Mega Farms) sent by Dr Thom on 13 October 2026; the signed 2025 financial statements (note 8); Dr Thom\'s EIR assessment at 31 December 2025 (Tamanda, 15 September 2026); the take-on workbook with fees (13 October 2026).')
w('- E-Banker: packs 1 to 10 as committed in `docs/bootstrap/` (pack 10 of 14 October 2026: `OD_02`, `RF_01`; probes and rebuild checks read, not landed).')
w('- Working scripts (scratchpad `rv3/`): `figures.py` (every figure, read only), `odcmp.php` (the overdue-run comparison, read only), `report.py` (this text), `md2docx.py`.')
w('')
w('Prepared by Dupleix Institute. Nothing was changed in MAIIC\'s systems or in the client database `maiic_ifrs9`.')

open(OUT, 'w', encoding='utf-8').write('\n'.join(L) + '\n')
print('written', OUT, len(L), 'lines')
