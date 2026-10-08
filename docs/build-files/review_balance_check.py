"""Extract B closing running balance against Extract A's balance at 31 December 2025.

The close is taken as the actual row with the highest posting number for the account, because
sixteen rows in the file are out of posting order. Reported in the analysis as 110 of 111.
"""
import warnings
import pandas as pd

warnings.filterwarnings("ignore")
D = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Database extracts"
A = pd.read_excel(D + r"\Extract A.xlsx", dtype={"LOAN_ACCOUNT_NUMBER": str})
B = pd.read_excel(D + r"\Extract B.xlsx", sheet_name="Sheet1", dtype={"LOAN_ACCOUNT_NUMBER": str})
key = ["LOAN_ACCOUNT_NUMBER", "SUB_ACCOUNT_NO"]
year_end = A[A["AS_OF_DATE"].astype(str).str[:10] == "31-12-2025"].set_index(key)

actual = B[B.SCHEDULED_ACTUAL_FLAG == "Actual"].copy()
actual["posting_no"] = actual.GL_POSTING_REF.str.split("-").str[-1].astype(int)

rows = []
for k, g in actual.groupby(key):
    a_balance = year_end.OPENING_BALANCE.get(k)
    if pd.isna(a_balance):
        continue
    in_file_order = g.BALANCE_AFTER_TRANSACTION.iloc[-1]
    in_posting_order = g.sort_values("posting_no").BALANCE_AFTER_TRANSACTION.iloc[-1]
    rows.append((k[0], year_end.CUSTOMER_NAME[k], a_balance, in_file_order, in_posting_order))
df = pd.DataFrame(rows, columns=["account", "customer", "extract_a_balance", "b_close_file_order", "b_close_posting_order"])

print("accounts with both balances:", len(df))
print("agree within MWK 1, last row in file order:   ", int(((df.extract_a_balance - df.b_close_file_order).abs() < 1).sum()))
print("agree within MWK 1, highest posting number:   ", int(((df.extract_a_balance - df.b_close_posting_order).abs() < 1).sum()))
print("\nexceptions on the posting-order test:")
print(df[(df.extract_a_balance - df.b_close_posting_order).abs() >= 1].round(2).to_string(index=False))
