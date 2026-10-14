"""Splice the split RUN 8 into the pack 9 all-runs sheet, update the README, copy to the repo, render the PDF."""
import re, shutil, subprocess, sys
from pathlib import Path
P9 = Path(r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC\Received 12 October 2026\Pack 4\Pack 9")
REPO = Path(r"C:\xampp\htdocs\MAICC-IFRS9")
run8 = (P9 / "RUN 8 - OD_01_overdue_details.sql").read_text(encoding="utf-8")
allruns = P9 / "FOLLOW-UP extracts for Barry - pack 9 - 13 Oct 2026 (all runs).sql"
txt = allruns.read_text(encoding="utf-8")

# the RUN 8 section of the sheet = everything from the RUN 8 banner to the end
i = txt.index("-- RUN 8  |")
head = txt[:i]
# the run-8 file minus its own 8-line preamble (banner, export note, ALTER SESSION lines)
body = run8.split("ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '.,';\n", 1)[1].lstrip("\n")
section = "-- RUN 8  |  E-Banker's own overdue computation per account  |  SPLIT into 8a, 8b, 8c (14 October, afternoon)\n" + body
txt = head + section
# the preamble's run list: add the RUN 8 lines after RUN 7 if not there
old = "--   RUN 7       GL_OPENING_BALANCE_BK240824, the GL at the migration\n"
new = old + ("--   RUN 8a      OVERDUE_DETAILS, a profile by month (added 14 October)\n"
             "--   RUN 8b      OVERDUE_DETAILS, the month-end rows, all 78 columns\n"
             "--   RUN 8c-1..6 OVERDUE_DETAILS, the daily rows since July 2025, one quarter per file\n")
if "RUN 8a      OVERDUE_DETAILS" not in txt:
    assert old in txt, "run list anchor"
    txt = txt.replace(old, new, 1)
txt = txt.replace("--   RUN 8       OVERDUE_DETAILS, the overdue run per account (added 14 October)\n", "")
allruns.write_text(txt, encoding="utf-8")
print("all-runs sheet:", len(txt.splitlines()), "lines")

# README
rd = P9 / "README.txt"
r = rd.read_text(encoding="utf-8")
r = re.sub(r"  RUN 8 - OD_01_overdue_details\.sql.*\n",
           "  RUN 8 - OD_01_overdue_details.sql   (added 14 Oct, SPLIT the same afternoon: 8a profile -> OD_00,\n"
           "                                       8b month-end rows -> OD_01, 8c-1..6 daily rows by quarter -> OD_02_..._<quarter>;\n"
           "                                       the single query exceeded SQL Developer's 100 MB limit, the 21-row\n"
           "                                       OD_01 file of 14 Oct 09:09 is a fragment and is superseded)\n", r)
if "SPLIT the same afternoon" not in r:
    r += "\n  RUN 8 (split 14 Oct afternoon): 8a profile -> OD_00, 8b month-end rows -> OD_01, 8c-1..6 daily rows by quarter -> OD_02_<quarter>.\n"
rd.write_text(r, encoding="utf-8")
print("README updated")

# repo copies
shutil.copy(P9 / "RUN 8 - OD_01_overdue_details.sql", REPO / "docs/bootstrap/queries/Pack 9 - RUN 8 - OD_01_overdue_details.sql")
dst = REPO / "docs/bootstrap/queries/Pack 9 - 13 Oct 2026 (all runs).sql"
shutil.copy(allruns, dst)
print("repo copies written")

# PDF
pdf = P9 / "FOLLOW-UP extracts for Barry - pack 9 - 13 Oct 2026 (all runs).pdf"
out = subprocess.run([sys.executable, str(REPO / "docs/build-files/sql_to_pdf.py"), str(allruns), str(pdf),
                      "MAIIC E-Banker: follow-up extracts, pack 9",
                      "Dupleix Institute  |  13 October 2026, RUN 8 added and split 14 October  |  for Barry Makumba"],
                     capture_output=True, text=True)
print(out.stdout, out.stderr)
