"""Profile every follow-up result file: shape, columns, date formats, key counts."""
import re, os, sys, zipfile, json
import pandas as pd
pd.set_option("display.width", 250); pd.set_option("display.max_columns", 50); pd.set_option("display.max_colwidth", 40)
D = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC\Follow-Up Scripts Resutls"
PAT = {
    "iso_datetime": re.compile(r"^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$"),
    "iso_date": re.compile(r"^\d{4}-\d{2}-\d{2}$"),
    "m/d/yyyy": re.compile(r"^\d{1,2}/\d{1,2}/\d{4}$"),
    "dd-mon-yy": re.compile(r"^\d{2}-[A-Za-z]{3}-\d{2}$"),
    "m/d/yyyy h:mm": re.compile(r"^\d{1,2}/\d{1,2}/\d{4} \d{1,2}:\d{2}"),
}
with zipfile.ZipFile(os.path.join(D, "Dupleix_02.zip")) as z:
    print("ZIP contents:", [(i.filename, i.file_size) for i in z.infolist()][:30])
report = {}
for f in sorted(os.listdir(D)):
    if not f.endswith(".csv"): continue
    df = pd.read_csv(os.path.join(D, f), dtype=str, keep_default_na=False)
    if df.columns[0].strip() == "": df = df.iloc[:, 1:]
    info = {"rows": len(df), "cols": len(df.columns)}
    datecols = {}
    for c in df.columns:
        s = df[c][df[c].str.strip() != ""]
        if len(s) == 0: continue
        kinds = {}
        for k, p in PAT.items():
            n = int(s.str.match(p).sum())
            if n: kinds[k] = n
        if kinds and sum(kinds.values()) >= 0.5 * len(s):
            datecols[c] = dict(kinds, nonblank=len(s), sample=s.iloc[0])
            if "iso_datetime" in kinds:
                with_time = int((~s.str.endswith(" 00:00:00")).sum()); datecols[c]["with_time_of_day"] = with_time
    info["date_columns"] = datecols
    report[f] = info
    print(f"=== {f}: {len(df)} rows x {len(df.columns)} cols")
    print("   columns:", ", ".join(df.columns[:45]) + (" ..." if len(df.columns) > 45 else ""))
    for c, v in datecols.items():
        print(f"   DATE {c}: {v}")
    if "NEW_AC_NUMBER" in df.columns:
        print("   accounts:", df.NEW_AC_NUMBER.nunique(), "| sample:", df.NEW_AC_NUMBER.iloc[0], "| all 15 chars:", bool((df.NEW_AC_NUMBER.str.len() == 15).all()))
json.dump(report, open("fu_profile.json", "w"), indent=1, default=str)
