"""Audit 4: the income rows that did not tie, pre- and post-migration coverage, status codes, rate history depth, plans."""
import pandas as pd, numpy as np
from fu_load import load
pd.set_option("display.width", 250); pd.set_option("display.max_columns", 40); pd.set_option("display.max_colwidth", 60)
led, acm, alm, rate, chart, plan, status, lb, disb = (load(k) for k in ("ledger", "acm", "alm", "rate", "chart", "plan", "status", "lb", "disb"))
led["TRANTYPE"] = led.TRANTYPE.astype(int)
ic = pd.read_csv("tb_income_recon.csv", dtype={"income_gl": str, "loan_gl": str})
print("(h2) income rows that did not tie on the YTD basis:")
print(ic[~(ic.diff_vs_movement.abs() < 1)][["month", "income_gl", "loan_gl", "tb_credit_balance", "tb_movement_ytd_basis", "ledger_interest", "diff_vs_movement"]].round(2).to_string(index=False))
gb = pd.read_csv("tb_balance_recon.csv", dtype={"gl": str})
print("   balance differences by GL (distinct values):", gb[gb["diff"].abs() >= 1].groupby("gl")["diff"].unique().to_dict())

print("\n(m) PRE- AND POST-MIGRATION")
first301 = led[led.TRANTYPE == 301].groupby("NEW_AC_NUMBER").TRANSACTION_DATE.min()
a = acm.set_index("NEW_AC_NUMBER")[["ACCOUNT_OPEN_DATE", "STATUS_CODE", "SCHEME_MST_ID"]].copy()
a["first_301"] = first301; a["pre"] = a.ACCOUNT_OPEN_DATE < "2024-07-01"
a["takeon"] = a.first_301.dt.strftime("%Y-%m-%d").eq("2024-07-31")
print("   pre-migration accounts:", int(a.pre.sum()), "| of which first ledger posting is the 31-Jul-2024 take-on:", int((a.pre & a.takeon).sum()), "| with no ledger rows:", int((a.pre & a.first_301.isna()).sum()), "| status of pre-migration:", a[a.pre].STATUS_CODE.value_counts().to_dict())
print("   post-migration accounts:", int((~a.pre).sum()), "| with 301 postings:", int((~a.pre & a.first_301.notna()).sum()), "| status:", a[~a.pre].STATUS_CODE.value_counts().to_dict())
r = rate[rate.DELETE_FLAG == "N"]
hist = r.groupby("NEW_AC_NUMBER").agg(rows=("APPLICABLE_FROM_DATE", "size"), first=("APPLICABLE_FROM_DATE", "min"), last=("APPLICABLE_FROM_DATE", "max"))
pre_hist = hist[hist.index.isin(a[a.pre].index)]
print("   rate history for pre-migration accounts:", len(pre_hist), "accounts | with a rate dated before Jul-2024:", int((pre_hist["first"] < "2024-07-01").sum()), "| median rows:", pre_hist.rows.median(), "| MAIIC (policy P) accounts with >5 rate rows:", int((hist.rows > 5).sum()))
takeon = led[(led.TRANTYPE == 301) & (led.TRANSACTION_DATE == "2024-07-31")]
print("   take-on postings 31-Jul-2024:", len(takeon), "accounts", takeon.NEW_AC_NUMBER.nunique(), "| total", f"{-takeon.TRANSAMT.sum():,.2f}", "| narration sample:", takeon.PARTICULARS.str[:45].head(3).tolist())
t300 = led[led.TRANTYPE == 300]; print("   type 300 rows:", t300[["NEW_AC_NUMBER", "TRANSACTION_DATE", "TRANSAMT", "PARTICULARS"]].values.tolist())
t343 = led[led.TRANTYPE == 343]; print("   type 343 rows:", t343[["NEW_AC_NUMBER", "TRANSACTION_DATE", "TRANSAMT", "PARTICULARS"]].values.tolist())
t120 = led[led.TRANTYPE == 120]; print("   type 120 narrations:", t120.PARTICULARS.str[:60].tolist()[:3])

print("\n(n) STATUS: codes in status history:", status.STATUS_CODE.value_counts().to_dict())
cur = acm.set_index("NEW_AC_NUMBER").STATUS_CODE
h = status[status.NEW_AC_NUMBER.isin(cur[cur == "H"].index)].sort_values(["NEW_AC_NUMBER", "STATUS_EFF_DATE"])
print("   H accounts: latest status-history row per account, code and reason:")
print(h.groupby("NEW_AC_NUMBER").tail(1).groupby(["STATUS_CODE", "STATUS_CHANGE_REASON"]).size().to_string())
print("   H accounts with a balance in the ledger at Sep-2026:", end=" ")
led = led.sort_values(["NEW_AC_NUMBER", "TRANSACTION_DATE", "CUMVOUCH_DET_ID"]); lastbal = led.groupby("NEW_AC_NUMBER").TRANSAMT.sum()
print(int((lastbal.reindex(cur[cur == "H"].index).fillna(0).abs() > 1).sum()), "of", int((cur == "H").sum()), "| F:", int((lastbal.reindex(cur[cur == "F"].index).fillna(0).abs() > 1).sum()), "of", int((cur == "F").sum()), "| D:", int((lastbal.reindex(cur[cur == "D"].index).fillna(0).abs() > 1).sum()), "of", int((cur == "D").sum()))
print("   INTEREST_STOP_DATE set on", int(acm.INTEREST_STOP_DATE.notna().sum()), "accounts; by status:", acm[acm.INTEREST_STOP_DATE.notna()].STATUS_CODE.value_counts().to_dict())

print("\n(o) TERMS NEEDED TO GENERATE A SCHEDULE, per account")
alm2 = alm.set_index("NEW_AC_NUMBER")
cols = {"SANCTION_AMOUNT": alm2.SANCTION_AMOUNT.notna() & (alm2.SANCTION_AMOUNT > 0), "SANCTION_DATE": alm2.SANCTION_DATE.notna(), "EXPIRY_DATE": alm2.EXPIRY_DATE.notna(),
        "tenor (months)": (alm2.PERIOD_YEARS.fillna(0) * 12 + alm2.PERIOD_MONTHS.fillna(0)) > 0, "INTEREST_RATE (account)": acm.set_index("NEW_AC_NUMBER").INTEREST_RATE.notna(),
        "PMOROTORIUM_PERIOD": alm2.PMOROTORIUM_PERIOD.notna(), "instalment plan row": alm2.index.isin(plan[plan.DELETE_FLAG == "N"].NEW_AC_NUMBER), "current chart": alm2.index.isin(chart.NEW_AC_NUMBER)}
for k, v in cols.items(): print(f"   {k:<26} {int(v.sum())} of {len(alm2)}")
p = plan[plan.DELETE_FLAG == "N"]
print("   plan INSTALLMENT_TYPE:", p.INSTALLMENT_TYPE.value_counts().to_dict(), "| PAY_MODE:", p.INSTALLMENT_PAY_MODE.value_counts().to_dict(), "| rows per account:", p.groupby("NEW_AC_NUMBER").size().describe()[["min", "50%", "max"]].to_dict())
print("   MORT_PERIOD_SEP_INT values:", alm.MORT_PERIOD_SEP_INT.value_counts().to_dict())
print("   moratorium period (P) distribution:", alm.PMOROTORIUM_PERIOD.value_counts().head(6).to_dict(), "| I:", alm.IMOROTORIUM_PERIOD.value_counts().head(6).to_dict())
print("   active accounts with no current chart, by scheme:", acm[(acm.STATUS_CODE == "A") & ~acm.NEW_AC_NUMBER.isin(chart.NEW_AC_NUMBER)].SCHEME_MST_ID.value_counts().to_dict())
lbx = lb.sort_values("TRANSACTION_DATE").drop_duplicates(["ASONDATE", "NEW_AC_NUMBER"], keep="last")
print("   loan book MORAT_MONTHS values:", lbx.MORAT_MONTHS.value_counts().head(8).to_dict())
print("   loan book TENOR_YRS sample:", lbx.TENOR_YRS.value_counts().head(6).to_dict())
