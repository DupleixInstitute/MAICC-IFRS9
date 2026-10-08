"""Audit 5: rebuild every interest posting from daily balances (the way E-Banker's product method works), and the Dec-2025 TB."""
import re, glob
import pandas as pd, numpy as np
from fu_load import load
pd.set_option("display.width", 250); pd.set_option("display.max_colwidth", 60)
led = load("ledger"); led["TRANTYPE"] = led.TRANTYPE.astype(int)
key = ["NEW_AC_NUMBER", "SUB_ACCOUNT_NO"]
led = led.sort_values(key + ["TRANSACTION_DATE", "CUMVOUCH_DET_ID"])
pat = re.compile(r"Fm (\d{2}-[A-Za-z]{3}-\d{2}) To (\d{2}-[A-Za-z]{3}-\d{2}) @ ?([\d.]+)%", re.I)
res = []
for acct, g in led.groupby("NEW_AC_NUMBER"):
    # daily closing balance from all postings on the loan's own GL (interest capitalised included)
    daily = g.groupby("TRANSACTION_DATE").TRANSAMT.sum()
    idx = pd.date_range(daily.index.min(), "2026-09-30")
    close = daily.reindex(idx).fillna(0).cumsum()            # balance at end of each day
    for _, r in g[g.TRANTYPE == 303].iterrows():
        m = pat.search(str(r.PARTICULARS))
        if not m: res.append((acct, r.TRANSACTION_DATE, r.TRANSAMT, np.nan, np.nan, np.nan, "no narration")); continue
        d0 = pd.to_datetime(m.group(1), format="%d-%b-%y"); d1 = pd.to_datetime(m.group(2), format="%d-%b-%y"); rate = float(m.group(3))
        # balance before this interest posting on each day: the day's closing balance, with the interest row itself excluded on its own day
        win = close.loc[d0:d1].copy()
        if r.TRANSACTION_DATE in win.index: win.loc[r.TRANSACTION_DATE:] -= r.TRANSAMT
        # same-day interest postings by other rows on the posting date are rare; ignore
        prod_close = -win.sum()                                    # closing-balance convention
        open_ = close.shift(1).reindex(win.index).fillna(0.0)
        if r.TRANSACTION_DATE in open_.index: open_.loc[r.TRANSACTION_DATE + pd.Timedelta(days=1):] -= r.TRANSAMT
        prod_open = -open_.sum()                                   # opening-balance convention
        exp_c = prod_close * rate / 100 / 365; exp_o = prod_open * rate / 100 / 365
        res.append((acct, r.TRANSACTION_DATE, -r.TRANSAMT, exp_c, exp_o, rate, "ok"))
df = pd.DataFrame(res, columns=["acct", "date", "posted", "exp_close", "exp_open", "rate", "note"])
ok = df[df.note == "ok"].copy()
for c in ("exp_close", "exp_open"):
    r = ok.posted / ok[c].replace(0, np.nan)
    print(f"{c}: within 0.1% on {int(((r - 1).abs() < 0.001).sum()):,} | within 1% on {int(((r - 1).abs() < 0.01).sum()):,} | of {len(ok):,} postings with a narration")
ok["best"] = np.minimum((ok.posted / ok.exp_close.replace(0, np.nan) - 1).abs(), (ok.posted / ok.exp_open.replace(0, np.nan) - 1).abs())
print("either convention within 0.1%:", int((ok.best < 0.001).sum()), "| within 1%:", int((ok.best < 0.01).sum()), "| amount within 1% of total posted:", f"{ok[ok.best < 0.01].posted.sum() / ok.posted.sum():.1%}")
far = ok[ok.best >= 0.01]
print("postings not within 1%:", len(far), "on", far.acct.nunique(), "accounts | by year:", far.date.dt.year.value_counts().to_dict())
print("   their share of all interest posted:", f"{far.posted.sum() / ok.posted.sum():.1%}")
print(far.sort_values("posted", ascending=False)[["acct", "date", "posted", "exp_close", "exp_open", "rate"]].head(8).round(2).to_string(index=False))
print("no-narration postings:", int((df.note != "ok").sum()), "| total", f"{-df[df.note != 'ok'].posted.sum():,.2f}" if (df.note != "ok").any() else "")
df.to_csv("interest_rebuild.csv", index=False)

# Dec 2025 TB
T = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Trial Balances"
x = pd.read_excel(T + r"\Trial Balance_31 December 2025.xls", header=None)
print("\nDec-2025 TB shape", x.shape); print(x.head(6).to_string())
m = x.astype(str).apply(lambda r: r.str.contains("1050101|4215|4216|42019", regex=True).any(), axis=1)
print(x[m].to_string())
