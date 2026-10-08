"""Shared loader for the follow-up result files (month-first dates, signed amounts)."""
import os, re
import pandas as pd

D = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC\Follow-Up Scripts Resutls"
X = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Database extracts"
FILES = {
    "ledger": "P1_01_ledger_all.csv", "acm": "P1_02_account_master_all.csv", "alm": "P1_03_loan_master_all.csv",
    "rate": "P1_04_rate_setup_per_account.csv", "isum": "P1_05_interest_posted_by_account.csv", "plr": "P2_06_plr_master.csv",
    "chg": "P2_07_disbursement_charges.csv", "lb": "P2_08_loan_book_history.csv", "bal": "P2_09_balance_history.csv",
    "chart": "P2_10_instalment_chart_current.csv", "plan": "P3_11_instalment_plans.csv", "disb": "P3_12_disbursement_schedules.csv",
    "status": "P3_13_status_history.csv", "accr": "P3_14_daily_interest_accrual.csv", "slab": "P3_15_slab_master.csv",
    "forg": "P3_16_forgiven_interest.csv", "auto": "P3_17_auto_charges.csv", "dtchk": "P3_18_date_time_check.csv",
}
MDY = re.compile(r"^\d{1,2}/\d{1,2}/\d{4}$")


def load(key, parse_dates=True):
    df = pd.read_csv(os.path.join(D, FILES[key]), dtype=str, keep_default_na=False)
    if df.columns[0].strip() == "":
        df = df.iloc[:, 1:]
    df.columns = [c.strip() for c in df.columns]
    for c in df.columns:
        s = df[c]
        nb = s[s.str.strip() != ""]
        if len(nb) and nb.str.match(MDY).all():
            if parse_dates:
                df[c] = pd.to_datetime(s.where(s.str.strip() != "", None), format="%m/%d/%Y")
        elif len(nb) and nb.str.match(r"^-?\d+(\.\d+)?$").all() and c not in ("NEW_AC_NUMBER", "OLD_AC_NUMBER", "GLCODE", "AC_GLCODE", "CB_GLCODE", "INCOME_GL", "DR_POSTING_GL", "CR_POSTING_GL", "INT_TRF_GLCODE"):
            df[c] = pd.to_numeric(s.where(s.str.strip() != "", None))
    return df


def load_all():
    return {k: load(k) for k in FILES}
