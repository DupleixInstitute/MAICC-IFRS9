"""Audit 3: trial balance against the ledger (balance sheet and income), interest summary, fees, chart, Extract A dates."""
import os, re, glob
import pandas as pd, numpy as np
from fu_load import load, X
pd.set_option("display.width", 250); pd.set_option("display.max_columns", 40); pd.set_option("display.max_colwidth", 60)
T = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Trial Balances"
led, acm, alm, chart, chg, isum = (load(k) for k in ("ledger", "acm", "alm", "chart", "chg", "isum"))
led["TRANTYPE"] = led.TRANTYPE.astype(int)
key = ["NEW_AC_NUMBER", "SUB_ACCOUNT_NO"]
led = led.sort_values(key + ["TRANSACTION_DATE", "CUMVOUCH_DET_ID"]); led["run"] = led.groupby(key).TRANSAMT.cumsum()
led["period"] = led.TRANSACTION_DATE.dt.to_period("M")

# ---- trial balances
def num(v):
    try: return float(str(v).replace(",", ""))
    except Exception: return np.nan
tb = {}
for f in glob.glob(T + r"\Trial Balance_*.xls"):
    m = re.search(r"_(\d{1,2} \w+ \d{4})", f); per = pd.to_datetime(m.group(1), format="%d %B %Y").to_period("M")
    df = pd.read_excel(f, header=None)
    rows = {}
    for _, r in df.iterrows():
        t = str(r[1]).strip()
        mm = re.match(r"^(\d{4,7})\.\.\.(.*)", t)
        if mm: rows[mm.group(1)] = (mm.group(2).strip(), num(r[2]), num(r[3]))
    tb[per] = rows
pers = sorted(tb)
print("TB months:", pers[0], "to", pers[-1], "| count", len(pers))
loan_gls = ["1050101", "1050102", "1050201", "1050202", "1050401"]
print("\n(g) BALANCE SHEET: TB debit balance per loan GL vs sum of ledger running balances at month-end")
gl_bal = []
for per in pers:
    for gl in loan_gls:
        sub = led[(led.AC_GLCODE == gl) & (led.period <= per)]
        ledger = -sub.groupby(key).run.last().sum() if len(sub) else 0.0
        tbv = tb[per].get(gl, (None, np.nan, np.nan))
        gl_bal.append((str(per), gl, tbv[1], ledger, (tbv[1] - ledger) if pd.notna(tbv[1]) else np.nan))
gb = pd.DataFrame(gl_bal, columns=["month", "gl", "tb_debit", "ledger", "diff"])
print("   months x GL with TB figure:", int(gb.tb_debit.notna().sum()), "| agree within 1:", int((gb["diff"].abs() < 1).sum()), "| differ:", int((gb["diff"].abs() >= 1).sum()))
print(gb[gb["diff"].abs() >= 1].round(2).to_string(index=False))
print("   total loan book at 2025-12:", f"{gb[gb.month == '2025-12'].tb_debit.sum():,.2f}", "TB vs ledger", f"{gb[gb.month == '2025-12'].ledger.sum():,.2f}")

print("\n(h) INCOME: TB interest income accounts and whether they are monthly or cumulative")
inc = {gl: t for gl, t in tb[pers[-1]].items() if gl.startswith("42")}
print("   42xx accounts in the latest TB:", {k: v[0] for k, v in inc.items()})
MAP = {"4215": "1050101", "4216": "1050102", "4218": "1050201", "4219": "1050202"}
for gl in tb[pers[-1]]:
    if gl.startswith("42") and "Term" in tb[pers[-1]][gl][0]: MAP[gl] = "1050401"
rows = []
for i, per in enumerate(pers):
    for igl, lgl in MAP.items():
        cr = tb[per].get(igl, (None, np.nan, np.nan))[2]
        prev = tb[pers[i - 1]].get(igl, (None, np.nan, 0.0))[2] if i > 0 and pers[i - 1].year == per.year else 0.0
        move = cr - (prev if pd.notna(prev) else 0.0) if pd.notna(cr) else np.nan
        ledger = -led[(led.AC_GLCODE == lgl) & (led.period == per) & led.TRANTYPE.isin([120, 303])].TRANSAMT.sum()
        rows.append((str(per), igl, lgl, cr, move, ledger))
ic = pd.DataFrame(rows, columns=["month", "income_gl", "loan_gl", "tb_credit_balance", "tb_movement_ytd_basis", "ledger_interest"])
ic["diff_vs_balance"] = ic.tb_credit_balance - ic.ledger_interest; ic["diff_vs_movement"] = ic.tb_movement_ytd_basis - ic.ledger_interest
print("   rows:", len(ic), "| ledger = TB balance (monthly basis):", int((ic.diff_vs_balance.abs() < 1).sum()), "| ledger = TB movement (YTD basis):", int((ic.diff_vs_movement.abs() < 1).sum()))
print(ic[ic.month.isin(["2025-01", "2025-02", "2025-12", "2026-01", "2026-08"])].round(2).to_string(index=False))
ic.to_csv("tb_income_recon.csv", index=False); gb.to_csv("tb_balance_recon.csv", index=False)
fees = {gl: t for gl, t in tb[pers[-1]].items() if gl in ("4871", "4872", "4873")}
print("   fee income accounts latest TB:", fees)

print("\n(i) INTEREST SUMMARY (Aug 2026) vs ledger 303 of 31 Aug 2026")
l8 = led[(led.TRANTYPE == 303) & (led.TRANSACTION_DATE == "2026-08-31")].groupby("NEW_AC_NUMBER").TRANSAMT.sum().mul(-1)
j = isum.set_index("NEW_AC_NUMBER")[["INTEREST_AMOUNT", "OVERDUE_INTEREST", "TOTAL_INTEREST", "INTEREST_RATE", "NO_OF_DAYS", "OVD_INT_RATE", "PENAL_RATE"]].join(l8.rename("ledger_303"), how="outer")
print("   accounts:", len(j), "| TOTAL_INTEREST = ledger:", int(((j.TOTAL_INTEREST - j.ledger_303).abs() < 1).sum()), "| INTEREST_AMOUNT = ledger:", int(((j.INTEREST_AMOUNT - j.ledger_303).abs() < 1).sum()), "| rows with overdue interest > 0:", int((j.OVERDUE_INTEREST > 0).sum()))
print("   overdue interest total Aug-2026:", f"{j.OVERDUE_INTEREST.sum():,.2f}", "of total", f"{j.TOTAL_INTEREST.sum():,.2f}", "| penal rates used:", j.PENAL_RATE.value_counts().head(4).to_dict())

print("\n(j) FEES: the largest fee-like 301 narrations")
fee301 = led[(led.TRANTYPE == 301) & led.PARTICULARS.str.contains("\bfees?\b|charge|legal|arrang|insur|commit", case=False, na=False)]
print(fee301.sort_values("TRANSAMT")[["NEW_AC_NUMBER", "TRANSACTION_DATE", "TRANSAMT", "PARTICULARS"]].head(8).to_string(index=False))
ins = fee301[fee301.PARTICULARS.str.contains("insur", case=False)]
print("   insurance rows:", len(ins), f"{-ins.TRANSAMT.sum():,.2f}", "| arrangement:", f"{-fee301[fee301.PARTICULARS.str.contains('arrang', case=False)].TRANSAMT.sum():,.2f}", "| legal:", f"{-fee301[fee301.PARTICULARS.str.contains('legal', case=False)].TRANSAMT.sum():,.2f}")
ch = chg[chg.NEW_AC_NUMBER.isin(acm.NEW_AC_NUMBER) & (chg.ACTUAL_CHARGES_AMT > 0)]
print("   charges table (in scope, amount > 0): by name", ch.groupby("DISB_CHARGES_NAME").ACTUAL_CHARGES_AMT.agg(["count", "sum"]).round(0).to_dict())
first301 = led[led.TRANTYPE == 301].groupby("NEW_AC_NUMBER").TRANSACTION_DATE.min()
ch_acc = ch.groupby("NEW_AC_NUMBER").ACTUAL_CHARGES_AMT.sum()
print("   charges-table accounts with first drawdown in ledger:", int(ch_acc.index.isin(first301.index).sum()), "of", len(ch_acc), "| first drawdown year of those:", first301[first301.index.isin(ch_acc.index)].dt.year.value_counts().to_dict())

print("\n(k) CHART: internal consistency and convention")
chart = chart.sort_values(["NEW_AC_NUMBER", "INSTALLMENT_DATE", "INST_SR_NO"])
g = chart.groupby("NEW_AC_NUMBER")
first = g.head(1); last = g.tail(1)
drawn = -led[led.TRANTYPE == 301].groupby("NEW_AC_NUMBER").TRANSAMT.sum()
print("   first row amount 0 on", int((first.INSTALLMENT_AMT == 0).sum()), "of", len(first), "| first EXP_BALANCE = drawn on", int((first.set_index("NEW_AC_NUMBER").EXP_BALANCE - drawn.reindex(first.NEW_AC_NUMBER).values).abs().lt(1).sum()), "| last EXP_BALANCE = 0 on", int((last.EXP_BALANCE.abs() < 1).sum()))
print("   sum of principal parts = first EXP_BALANCE on", int(((g.PRIN_INSTALLMENT_AMT.sum() - first.set_index('NEW_AC_NUMBER').EXP_BALANCE).abs() < 1).sum()), "| principal + interest = instalment on", int(((chart.PRIN_INSTALLMENT_AMT + chart.INTR_INSTALLMENT_AMT - chart.INSTALLMENT_AMT).abs() < 0.02).sum()), "of", len(chart))
chart["prev_bal"] = g.EXP_BALANCE.shift(1); chart["prev_date"] = g.INSTALLMENT_DATE.shift(1)
rt = acm.set_index("NEW_AC_NUMBER").INTEREST_RATE
c2 = chart[chart.prev_bal.notna() & (chart.INTR_INSTALLMENT_AMT > 0)].copy()
c2["rate"] = c2.NEW_AC_NUMBER.map(rt); c2["days"] = (c2.INSTALLMENT_DATE - c2.prev_date).dt.days
c2["monthly"] = c2.prev_bal * c2.rate / 100 / 12; c2["daily"] = c2.prev_bal * c2.rate / 100 * c2.days / 365
for name in ("monthly", "daily"):
    r = c2.INTR_INSTALLMENT_AMT / c2[name]
    print(f"   chart interest vs {name} convention at the current rate: within 0.5% on {int(((r - 1).abs() < 0.005).sum()):,} of {len(c2):,}")
print("   level instalment (same INSTALLMENT_AMT on all non-zero rows) on", int((chart[chart.INSTALLMENT_AMT > 0].groupby("NEW_AC_NUMBER").INSTALLMENT_AMT.nunique() == 1).sum()), "of", chart.NEW_AC_NUMBER.nunique(), "accounts")
# actual repayments vs chart due, by account, cumulative to Sep 2026
due = chart[chart.INSTALLMENT_DATE <= "2026-09-30"].groupby("NEW_AC_NUMBER").INSTALLMENT_AMT.sum()
paid = led[led.TRANTYPE.isin([305, 306])].groupby("NEW_AC_NUMBER").TRANSAMT.sum()
cmp = pd.concat([due.rename("due_to_date"), paid.rename("paid_to_date")], axis=1).dropna()
cmp["paid_over_due"] = cmp.paid_to_date / cmp.due_to_date.replace(0, np.nan)
print("   accounts with chart and repayments:", len(cmp), "| paid within 5% of due:", int(((cmp.paid_over_due - 1).abs() < 0.05).sum()), "| paid < 80% of due:", int((cmp.paid_over_due < 0.8).sum()), "| paid > 120%:", int((cmp.paid_over_due > 1.2).sum()))
print("   recovered per chart (INST_RECOVERED sum) vs paid:", int(((chart.groupby('NEW_AC_NUMBER').INST_RECOVERED.sum() - paid.reindex(chart.NEW_AC_NUMBER.unique())).abs() < 1).sum()), "agree")

print("\n(l) EXTRACT A: Excel-converted dates against the true dates now in hand")
A = pd.read_excel(X + r"\Extract A.xlsx", dtype={"LOAN_ACCOUNT_NUMBER": str})
A = A[A["AS_OF_DATE"].astype(str).str[:10] == "31-12-2025"]
real = acm.set_index("NEW_AC_NUMBER").ACCOUNT_OPEN_DATE
n_excel = n_match = n_swap = n_text = n_textmatch = 0
for k, v in zip(A.LOAN_ACCOUNT_NUMBER, A.LOAN_START_DATE):
    if k not in real.index: continue
    if hasattr(v, "year"):
        n_excel += 1; d = pd.Timestamp(v)
        if d == real[k]: n_match += 1
        else:
            try:
                if pd.Timestamp(year=d.year, month=d.day, day=d.month) == real[k]: n_swap += 1
            except Exception: pass
    elif isinstance(v, str):
        n_text += 1; n_textmatch += int(pd.to_datetime(v, format="%d-%m-%Y", errors="coerce") == real[k])
print(f"   text dates {n_text}: match {n_textmatch} | Excel-converted dates {n_excel}: match as read {n_match}, match after swapping day and month {n_swap}")
