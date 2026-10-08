"""Audit 2: reconciliations between the ledger, the balance history, the loan book history, Extract C and the terms."""
import re
import pandas as pd, numpy as np
from fu_load import load, X
pd.set_option("display.width", 250); pd.set_option("display.max_columns", 40); pd.set_option("display.max_colwidth", 60)
led, bal, lb, acm, alm, chart, chg, rate = (load(k) for k in ("ledger", "bal", "lb", "acm", "alm", "chart", "chg", "rate"))
led["TRANTYPE"] = led.TRANTYPE.astype(int)
key = ["NEW_AC_NUMBER", "SUB_ACCOUNT_NO"]
led = led.sort_values(["NEW_AC_NUMBER", "SUB_ACCOUNT_NO", "TRANSACTION_DATE", "CUMVOUCH_DET_ID"])
own = led[led.AC_GLCODE == led.NEW_AC_NUMBER.map(acm.set_index("NEW_AC_NUMBER").GLCODE)]
print("ledger rows on the loan's own GL:", len(own), "of", len(led), "| other GL rows:", led[~led.index.isin(own.index)].AC_GLCODE.value_counts().to_dict())
own = own.copy(); own["run"] = own.groupby(key).TRANSAMT.cumsum()

# (a) ledger running balance vs balance history at month-end
me = own.groupby(key + [own.TRANSACTION_DATE.dt.to_period("M")]).run.last().reset_index().rename(columns={"TRANSACTION_DATE": "period"})
b = bal.copy(); b["period"] = b.TRANSACTION_DATE.dt.to_period("M"); b = b.rename(columns={"SUB_AC_NUMBER": "SUB_ACCOUNT_NO"})
# carry the ledger balance forward into months with no posting
allm = []
for k, g in me.groupby(key):
    g = g.set_index("period").run
    idx = pd.period_range(g.index.min(), "2026-09", freq="M")
    allm.append(pd.DataFrame({"NEW_AC_NUMBER": k[0], "SUB_ACCOUNT_NO": k[1], "period": idx, "ledger_bal": g.reindex(idx).ffill().values}))
allm = pd.concat(allm)
m = b.merge(allm, on=key + ["period"], how="left")
m["diff"] = m.PRINCIPAL_BALANCE - m.ledger_bal
ok = (m["diff"].abs() < 1)
print(f"\n(a) BALANCE HISTORY vs LEDGER running balance: {len(m):,} account-months | agree within 1: {int(ok.sum()):,} | differ: {int((~ok).sum()):,} | no ledger: {int(m.ledger_bal.isna().sum())}")
bad = m[~ok & m.ledger_bal.notna()]
print("   differing accounts:", bad.NEW_AC_NUMBER.nunique(), "| first differing months:", bad.groupby("NEW_AC_NUMBER").period.min().astype(str).value_counts().head(5).to_dict())
print(bad.sort_values("diff", key=abs, ascending=False)[["NEW_AC_NUMBER", "period", "PRINCIPAL_BALANCE", "ledger_bal", "diff"]].head(8).to_string(index=False))

# (b) loan book history (latest run per as-on) vs ledger
lbx = lb.sort_values("TRANSACTION_DATE").drop_duplicates(["ASONDATE", "NEW_AC_NUMBER", "SUB_AC_NUMBER"], keep="last").copy()
print(f"\n(b) LOAN BOOK HISTORY: {len(lb):,} rows -> {len(lbx):,} after keeping the latest run per as-on date | runs per as-on date:", lb.groupby("ASONDATE").TRANSACTION_DATE.nunique().describe()[["min", "max"]].to_dict())
lbx["period"] = lbx.ASONDATE.dt.to_period("M"); lbx = lbx.rename(columns={"SUB_AC_NUMBER": "SUB_ACCOUNT_NO"})
mb = lbx.merge(allm, on=key + ["period"], how="left")
for col in ("PRINCIPAL", "CARRYING_AMOUNT"):
    d = mb[col] + mb.ledger_bal     # ledger balance is negative (debit)
    print(f"   {col} vs -ledger: agree within 1 on {int((d.abs() < 1).sum()):,} of {int(mb.ledger_bal.notna().sum()):,} with a ledger balance")
mb["d_carry"] = mb.CARRYING_AMOUNT + mb.ledger_bal
print(mb[(mb.d_carry.abs() >= 1) & mb.ledger_bal.notna()].sort_values("d_carry", key=abs, ascending=False)[["NEW_AC_NUMBER", "ASONDATE", "PRINCIPAL", "CARRYING_AMOUNT", "ledger_bal", "d_carry"]].head(6).to_string(index=False))
print("   loan book vs balance history, same month, PRINCIPAL vs PRINCIPAL_BALANCE:", end=" ")
mm = lbx.merge(b[key + ["period", "PRINCIPAL_BALANCE"]], on=key + ["period"], how="inner")
print(f"{int(((mm.PRINCIPAL + mm.PRINCIPAL_BALANCE).abs() < 1).sum()):,} of {len(mm):,} agree")

# (c) ledger interest vs Extract C run 41
C = pd.read_csv(X + r"\ExtractC_Jan2025-July2026.xlsx".replace(".xlsx", ".csv"), dtype=str) if False else pd.read_excel(X + r"\ExtractC_Jan2025-July2026.xlsx", dtype={"LOAN_ACCOUNT_NUMBER": str})
C = C[(C.RUN_ID == 41) & (C.PERIOD_TYPE == "MONTH")]
C["period"] = pd.to_datetime(C.PERIOD_YEAR.astype(int).astype(str) + "-" + C.PERIOD_MONTH.astype(int).astype(str).str.zfill(2) + "-01").dt.to_period("M")
li = own[own.TRANTYPE.isin([120, 303, 308, 309, 310, 311])].groupby(["NEW_AC_NUMBER", own.TRANSACTION_DATE.dt.to_period("M")]).TRANSAMT.sum().mul(-1).rename("ledger_int").reset_index().rename(columns={"TRANSACTION_DATE": "period"})
mc = C.merge(li, left_on=["LOAN_ACCOUNT_NUMBER", "period"], right_on=["NEW_AC_NUMBER", "period"], how="outer", indicator=True)
mc["diff"] = mc.INTEREST_INCOME_POSTED.fillna(0) - mc.ledger_int.fillna(0)
print(f"\n(c) EXTRACT C (run 41) vs LEDGER types 120+303 by account-month: {len(mc):,} | both: {int((mc._merge == 'both').sum()):,} | only C: {int((mc._merge == 'left_only').sum())} | only ledger: {int((mc._merge == 'right_only').sum())} | agree within 1: {int((mc['diff'].abs() < 1).sum()):,}")
print("   totals Jan-2025 to Jul-2026: C", f"{C.INTEREST_INCOME_POSTED.sum():,.2f}", "| ledger", f"{li[(li.period >= '2025-01') & (li.period <= '2026-07')].ledger_int.sum():,.2f}")
print(mc[mc['diff'].abs() >= 1][["LOAN_ACCOUNT_NUMBER", "NEW_AC_NUMBER", "period", "INTEREST_INCOME_POSTED", "ledger_int", "diff", "_merge"]].head(6).to_string(index=False))
oth = own[own.TRANTYPE == 306]
print("   type 306 narrations:", oth.PARTICULARS.str[:60].value_counts().head(5).to_dict())

# (d) recompute each interest posting from the narration: balance before x rate x days / 365
pat = re.compile(r"Fm (\d{2}-[A-Za-z]{3}-\d{2}) To (\d{2}-[A-Za-z]{3}-\d{2}) @ ?([\d.]+)%", re.I)
own["prev"] = own.groupby(key).run.shift(1).fillna(0)
ii = own[own.TRANTYPE == 303].copy()
ex = ii.PARTICULARS.str.extract(pat)
ii["d_from"] = pd.to_datetime(ex[0], format="%d-%b-%y", errors="coerce"); ii["d_to"] = pd.to_datetime(ex[1], format="%d-%b-%y", errors="coerce"); ii["rate"] = pd.to_numeric(ex[2], errors="coerce")
ii["days"] = (ii.d_to - ii.d_from).dt.days + 1
ii["expected"] = -(-ii.prev) * ii.rate / 100 * ii.days / 365
ii["ratio"] = ii.TRANSAMT / ii.expected
parsed = ii.rate.notna()
close = parsed & ((ii.ratio - 1).abs() < 0.005)
print(f"\n(d) RECOMPUTE 303 POSTINGS from narration (balance before x rate x days/365): {len(ii):,} postings | narration parsed {int(parsed.sum()):,} | within 0.5%: {int(close.sum()):,} | within 2%: {int((parsed & ((ii.ratio - 1).abs() < 0.02)).sum()):,}")
print("   rate in narration vs rate set-up table in force (active, not deleted):", end=" ")
rs = rate[(rate.DELETE_FLAG == "N")].sort_values("APPLICABLE_FROM_DATE")
hits = 0; tested = 0
for _, r in ii[parsed].iterrows():
    cand = rs[(rs.NEW_AC_NUMBER == r.NEW_AC_NUMBER) & (rs.APPLICABLE_FROM_DATE <= r.d_to)]
    if len(cand):
        tested += 1; hits += int(abs(cand.iloc[-1].INTEREST_RATE - r.rate) < 0.051)
print(f"{hits} of {tested} agree")
far = ii[parsed & ~close]
print("   not within 0.5%: days used vs calendar month, sample:"); print(far[["NEW_AC_NUMBER", "TRANSACTION_DATE", "prev", "rate", "days", "TRANSAMT", "expected", "ratio"]].head(6).round(2).to_string(index=False))
print("   first-month postings (posting dated same month as first 301):", end=" ")
first301 = own[own.TRANTYPE == 301].groupby("NEW_AC_NUMBER").TRANSACTION_DATE.min()
fm = ii[ii.TRANSACTION_DATE.dt.to_period("M") == ii.NEW_AC_NUMBER.map(first301).dt.to_period("M")]
print(len(fm), "| within 0.5%:", int(((fm.ratio - 1).abs() < 0.005).sum()))

# (e) disbursements vs sanction, fees in narrations vs charges table
d301 = own[own.TRANTYPE == 301].groupby("NEW_AC_NUMBER").TRANSAMT.sum().mul(-1)
s = alm.set_index("NEW_AC_NUMBER").SANCTION_AMOUNT
cmp = pd.concat([d301.rename("drawn"), s.rename("sanction")], axis=1).dropna()
print(f"\n(e) DISBURSEMENTS: accounts with 301 postings {len(d301)} | drawn = sanction on {int(((cmp.drawn - cmp.sanction).abs() < 1).sum())} | drawn < sanction on {int((cmp.drawn < cmp.sanction - 1).sum())} | drawn > sanction on {int((cmp.drawn > cmp.sanction + 1).sum())}")
fee301 = own[(own.TRANTYPE == 301) & own.PARTICULARS.str.contains("fee|charge|legal|arrang|insur|commit|process", case=False, na=False)]
print("   301 rows whose narration names a fee:", len(fee301), "on", fee301.NEW_AC_NUMBER.nunique(), "accounts | total", f"{-fee301.TRANSAMT.sum():,.2f}")
print("   sample:", fee301.PARTICULARS.str[:50].head(6).tolist())
ch = chg[chg.NEW_AC_NUMBER.isin(acm.NEW_AC_NUMBER)]
print("   charges table in-scope: accounts", ch.NEW_AC_NUMBER.nunique(), "| ACTUAL_CHARGES_AMT total", f"{ch.ACTUAL_CHARGES_AMT.sum():,.2f}", "| APLLI total", f"{ch.APLLI_CHARGES_AMT.sum():,.2f}", "| rows with amount > 0:", int((ch.ACTUAL_CHARGES_AMT > 0).sum()))
both = set(fee301.NEW_AC_NUMBER) & set(ch[ch.ACTUAL_CHARGES_AMT > 0].NEW_AC_NUMBER)
print("   accounts with fee narration AND a charges-table amount:", len(both), "| narration only:", len(set(fee301.NEW_AC_NUMBER) - set(ch[ch.ACTUAL_CHARGES_AMT > 0].NEW_AC_NUMBER)), "| charges only:", len(set(ch[ch.ACTUAL_CHARGES_AMT > 0].NEW_AC_NUMBER) - set(fee301.NEW_AC_NUMBER)))
for a in list(both)[:3]:
    print("     ", a, "narration fees", f"{-fee301[fee301.NEW_AC_NUMBER == a].TRANSAMT.sum():,.2f}", "| charges", ch[ch.NEW_AC_NUMBER == a][["DISB_CHARGES_NAME", "ACTUAL_CHARGES_AMT", "APLLI_CHARGES_AMT"]].values.tolist())

# (f) Extract A dates vs the master now
A = pd.read_excel(X + r"\Extract A.xlsx", dtype={"LOAN_ACCOUNT_NUMBER": str})
A = A[A["AS_OF_DATE"].astype(str).str[:10] == "31-12-2025"].set_index("LOAN_ACCOUNT_NUMBER")
def parse_a(v):
    if isinstance(v, pd.Timestamp): return v, "excel"
    try: return pd.to_datetime(str(v)[:10], format="%d-%m-%Y"), "text"
    except Exception: return pd.NaT, "bad"
rows = []
for k, v in A.LOAN_START_DATE.items():
    d, kind = parse_a(v); real = acm.set_index("NEW_AC_NUMBER").ACCOUNT_OPEN_DATE.get(k)
    if real is None or pd.isna(d): continue
    swapped = pd.NaT
    if kind == "excel":
        try: swapped = pd.Timestamp(year=d.year, month=d.day, day=d.month)
        except Exception: pass
    rows.append((kind, d == real, swapped == real))
f = pd.DataFrame(rows, columns=["kind", "match", "match_after_swap"])
print("\n(f) EXTRACT A loan start date vs ACCOUNT_OPEN_DATE now:"); print(f.groupby("kind").agg(n=("match", "size"), match=("match", "sum"), match_after_swap=("match_after_swap", "sum")).to_string())
