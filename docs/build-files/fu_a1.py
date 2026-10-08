"""Audit 1: date formats, coverage of each table, key distributions."""
import re
import pandas as pd, numpy as np
from fu_load import load, FILES, D, X, MDY
pd.set_option("display.width", 250); pd.set_option("display.max_columns", 40); pd.set_option("display.max_colwidth", 50)

print("=== 1. DATE FORMAT PROOF: first component > 12 (would mean day-first) vs second component > 12 (month-first)")
tot_first, tot_second, tot = 0, 0, 0
for k, f in FILES.items():
    raw = pd.read_csv(f"{D}\\{f}", dtype=str, keep_default_na=False)
    for c in raw.columns:
        s = raw[c][raw[c].str.strip() != ""]
        if len(s) and s.str.match(MDY).all():
            a = s.str.split("/").str[0].astype(int); b = s.str.split("/").str[1].astype(int)
            tot_first += int((a > 12).sum()); tot_second += int((b > 12).sum()); tot += len(s)
print(f"   date values checked {tot:,} | first part > 12: {tot_first} | second part > 12: {tot_second:,}")
print("   P3_18:", load("dtchk").to_dict("records"))

acm, alm, led, bal, lb, chart, plan, rate, chg, disb, status, accr, isum = (load(k) for k in
    ("acm", "alm", "ledger", "bal", "lb", "chart", "plan", "rate", "chg", "disb", "status", "accr", "isum"))
scope = set(acm.NEW_AC_NUMBER)
print("\n=== 2. SCOPE: accounts in account master", len(scope), "| by scheme:", acm.SCHEME_MST_ID.value_counts().to_dict())
print("   status:", acm.STATUS_CODE.value_counts().to_dict())
print("   INTEREST_POLICY per account:", acm.INTEREST_POLICY.value_counts().to_dict(), "| FLOATING_FLAG:", acm.FLOATING_FLAG.value_counts().to_dict())
print("   policy by scheme:"); print(pd.crosstab(acm.SCHEME_MST_ID, acm.INTEREST_POLICY).to_string())
print("   opened before 2024-07-01 (pre-migration):", int((acm.ACCOUNT_OPEN_DATE < "2024-07-01").sum()), "| on/after:", int((acm.ACCOUNT_OPEN_DATE >= "2024-07-01").sum()))
print("   open date range:", acm.ACCOUNT_OPEN_DATE.min().date(), "to", acm.ACCOUNT_OPEN_DATE.max().date())
print("   MARGIN_PERCENT:", alm.MARGIN_PERCENT.describe()[["count", "min", "max"]].to_dict(), "| FUNDED_FLAG:", alm.FUNDED_FLAG.value_counts().to_dict())
print("   loan master moratorium cols:", [c for c in alm.columns if "MOR" in c.upper()])

print("\n=== 3. COVERAGE per table (accounts of the 184)")
def cov(name, df):
    s = set(df.NEW_AC_NUMBER); print(f"   {name:<22} accounts {len(s & scope):3d} | outside scope {len(s - scope)} | rows {len(df):,}")
    return s
for name, df in (("ledger", led), ("balance history", bal), ("loan book history", lb), ("chart (current)", chart), ("instalment plans", plan), ("rate set-up", rate), ("charges", chg), ("disb schedule", disb), ("status history", status), ("daily accrual", accr), ("interest summary", isum)):
    cov(name, df)
noled = acm[~acm.NEW_AC_NUMBER.isin(led.NEW_AC_NUMBER)]
print("   accounts with NO ledger rows:", len(noled), "| status:", noled.STATUS_CODE.value_counts().to_dict(), "| opened:", noled.ACCOUNT_OPEN_DATE.min().date(), "to", noled.ACCOUNT_OPEN_DATE.max().date())
nochart = acm[~acm.NEW_AC_NUMBER.isin(chart.NEW_AC_NUMBER)]
print("   accounts with NO current chart:", len(nochart), "| status:", nochart.STATUS_CODE.value_counts().to_dict(), "| pre-migration among them:", int((nochart.ACCOUNT_OPEN_DATE < "2024-07-01").sum()))

print("\n=== 4. LEDGER: date range", led.TRANSACTION_DATE.min().date(), "to", led.TRANSACTION_DATE.max().date(), "| GL codes:", led.AC_GLCODE.value_counts().to_dict())
print("   trantype x sign:"); t = led.assign(sign=np.sign(led.TRANSAMT)); print(pd.crosstab(t.TRANTYPE, t.sign).to_string())
print("   DBCR values:", led.DBCR.value_counts().to_dict() if "DBCR" in led.columns else "n/a")
print("   TRANSACTION_DATE == CB_EFFECTIVE_DATE on", int((led.TRANSACTION_DATE == led.CB_EFFECTIVE_DATE).sum()), "of", len(led), "| == OPERATION_DATE on", int((led.TRANSACTION_DATE == led.OPERATION_DATE).sum()))
late = led[led.OPERATION_DATE.notna() & (led.OPERATION_DATE > led.TRANSACTION_DATE)]
print("   back-dated postings (operation date after transaction date):", len(late), "| by type:", late.TRANTYPE.value_counts().to_dict(), "| max days:", int((late.OPERATION_DATE - late.TRANSACTION_DATE).dt.days.max()))
for tt in ("300", "306", "343", "120", "400", "401", "900", "901"):
    sub = led[led.TRANTYPE == tt]
    if len(sub): print(f"   type {tt}: {len(sub)} rows, sample narrations:", sub.PARTICULARS.head(3).tolist())
print("   fee narrations among 301:", int(led[(led.TRANTYPE == "301")].PARTICULARS.str.contains("fee|charge|legal|arrang|insur|commit", case=False).sum()), "of", int((led.TRANTYPE == "301").sum()))

print("\n=== 5. LOAN BOOK HISTORY: as-on dates")
lbd = lb.groupby("ASONDATE").agg(accounts=("NEW_AC_NUMBER", "nunique"), rows=("NEW_AC_NUMBER", "size"))
print("   distinct as-on dates:", len(lbd), "| first", lbd.index.min().date(), "last", lbd.index.max().date())
print("   month-ends among them:", int((lbd.index == lbd.index + pd.offsets.MonthEnd(0)).sum()), "| by year-month (accounts):", {str(k)[:7]: int(v) for k, v in lb.groupby(lb.ASONDATE.dt.to_period("M")).NEW_AC_NUMBER.nunique().items()})
print("   duplicate account rows on one as-on date:", int(lb.duplicated(["ASONDATE", "NEW_AC_NUMBER", "SUB_AC_NUMBER"]).sum()))

print("\n=== 6. BALANCE HISTORY: dates")
bd = bal.groupby(bal.TRANSACTION_DATE.dt.to_period("M")).NEW_AC_NUMBER.nunique()
print("   months:", {str(k): int(v) for k, v in bd.items()})
print("   all month-end?", bool((bal.TRANSACTION_DATE == bal.TRANSACTION_DATE + pd.offsets.MonthEnd(0)).all()), "| dup account-date:", int(bal.duplicated(["NEW_AC_NUMBER", "SUB_AC_NUMBER", "TRANSACTION_DATE"]).sum()))

print("\n=== 7. RATE SET-UP: rows per account", rate.groupby("NEW_AC_NUMBER").size().describe()[["min", "50%", "max"]].to_dict(), "| APPLICABLE_FROM range", rate.APPLICABLE_FROM_DATE.min().date(), rate.APPLICABLE_FROM_DATE.max().date())
print("   DELETE_FLAG:", rate.DELETE_FLAG.value_counts().to_dict(), "| ACTIVE_FLAG:", rate.ACTIVE_FLAG.value_counts().to_dict(), "| policy:", rate.INTEREST_POLICY.value_counts().to_dict())
print("   sample Ebenezer:"); print(rate[rate.NEW_AC_NUMBER == "000104430000084"][["APPLICABLE_FROM_DATE", "INTEREST_POLICY", "PLR_RATE", "VERIATION_RATE", "INTEREST_RATE", "ACTIVE_FLAG", "DELETE_FLAG"]].to_string(index=False))

print("\n=== 8. CHART (current): columns with amounts:", [c for c in chart.columns if "AMT" in c or "BAL" in c or "RECOV" in c])
print("   rows per account", chart.groupby("NEW_AC_NUMBER").size().describe()[["min", "50%", "max"]].to_dict(), "| DELETE_FLAG:", chart.DELETE_FLAG.value_counts().to_dict(), "| ACTIVE_FLAG:", chart.ACTIVE_FLAG.value_counts().to_dict())
print("   zero-amount rows:", int((chart.INSTALLMENT_AMT == 0).sum()), "| rows with INST_RECOVERED>0:", int((chart.INST_RECOVERED > 0).sum()), "| partially recovered:", int(((chart.INST_RECOVERED > 0) & (chart.INST_RECOVERED < chart.INSTALLMENT_AMT)).sum()))

print("\n=== 9. INTEREST SUMMARY:", len(isum), "rows | dates", isum.TRANSACTION_DATE.min().date(), isum.TRANSACTION_DATE.max().date(), "| POSTED_FLAG:", isum.POSTED_FLAG.value_counts().to_dict() if "POSTED_FLAG" in isum else "")
print("=== 10. CHARGES: in-scope accounts", chg.NEW_AC_NUMBER.isin(scope).sum(), "rows |", chg[chg.NEW_AC_NUMBER.isin(scope)].DISB_CHARGES_NAME.value_counts().head(8).to_dict())
print("   POST_FLAG:", chg[chg.NEW_AC_NUMBER.isin(scope)].POST_FLAG.value_counts().to_dict(), "| INCOME_GL:", chg[chg.NEW_AC_NUMBER.isin(scope)].INCOME_GL.value_counts().head(6).to_dict())
print("=== 11. STATUS HISTORY: reasons for H and F:")
print(status[status.STATUS_CODE.isin(["H", "F", "D"])].groupby(["STATUS_CODE", "STATUS_CHANGE_REASON"]).size().to_string())
print("=== 12. DAILY ACCRUAL: accounts", accr.NEW_AC_NUMBER.nunique(), "| dates", accr.TRANSACTION_DATE.min().date(), accr.TRANSACTION_DATE.max().date(), "| DRCR:", accr.DRCR.value_counts().to_dict())
