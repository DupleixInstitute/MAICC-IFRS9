import pandas as pd, warnings
warnings.filterwarnings("ignore")
D = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Database extracts"
for f in ["Extract A.xlsx", "Extract B.xlsx", "ExtractC_Jan2025-July2026.xlsx"]:
    x = pd.ExcelFile(D + "\\" + f)
    for sh in x.sheet_names:
        df = x.parse(sh, header=None, nrows=4)
        print("==", f, "|", sh, "|", x.parse(sh).shape)
        print(df.iloc[:2].to_string(max_cols=40, max_colwidth=22))
