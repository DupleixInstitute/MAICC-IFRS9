"""Build the run-order SQL file from MAIIC_EBanker_Data_Dictionary_Queries.sql (version 2, 27 queries)."""
import re

D = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC"
src = open(D + r"\MAIIC_EBanker_Data_Dictionary_Queries.sql", encoding="utf-8").read().replace("\r\n", "\n")
blocks = {}
cur = None
for line in src.split("\n"):
    m = re.match(r"^-- (DD_\d+[a-z]?)_(\S+)\.csv\s*$", line)
    if m:
        cur = m.group(1); blocks[cur] = dict(file=f"{m.group(1)}_{m.group(2)}.csv", lines=[]); continue
    if line.startswith("--====") or line.startswith("--------"):
        cur = None; continue
    if cur:
        blocks[cur]["lines"].append(line)
sql = {}
for k, b in blocks.items():
    body = [l for l in b["lines"] if not l.startswith("--")]
    sql[k] = "\n".join(body).strip()
    assert sql[k].upper().startswith("SELECT") and sql[k].endswith(";"), k
assert len(sql) == 27, len(sql)

ORDER = [
 ("DD_01", "Gate check. Confirms this login can see ACMASTER and the other tables the extracts read, and shows which schema owns them.",
  "If it returns no rows, stop: the catalogue queries below will also return nothing, and we need the schema owner's name. If it lists MORE THAN ONE owner, tell us which is the live schema before going on."),
 ("DD_17", "Shows who is logged in, which schema that login's table names point to, any synonyms standing in for the tables, and who owns the three extract procedures.",
  "This settles which schema the later queries, and the procedures themselves, actually read."),
 ("DD_18", "Tests that this login can read each table directly. Being able to run a procedure does not prove that.",
  "Run the thirteen lines one at a time. Note any that fail. LOAN_WRITEOFF_TRANS may simply not exist."),
 ("DD_10", "Finds Interest Policy. Returns every setting on the MAIIC and FInES schemes, which is where the manual says Interest Policy, Floating Flag, the interest calculation base, the instalment base and the EMI type are held.",
  "Look for a policy column with values such as P, F or M, and for a scheme code besides SCHEME_MST_ID. Compare FLOATING_FLAG with the Fixed/Variable labels in Extract A."),
 ("DD_03", "The data dictionary itself: every column of every table. Needed before any amended extract can be written, and it catches Interest Policy if it is held per account and not per scheme.",
  "Large result. Export it and carry on; we search it afterwards."),
 ("DD_12", "Transaction type codes and the sign of their amounts. Settles the direction of every row in Extract B and what the six interest codes in Extract C are.",
  "For each code, are the amounts all positive, all negative or mixed? Codes 301, 302, 303, 305, 120 and 308 to 311 matter most."),
 ("DD_13", "Shows every GL code the loan accounts post to. The extracts read only the loan's own GL, so this is how we find where fees, penalties and suspended interest go, and whether interest is posted anywhere the extracts do not look.",
  "Look for postings on the fee income codes 4871, 4872 and 4873 and on the interest income codes 4215 to 4219."),
 ("DD_15", "Row counts for the small loan tables. A zero on LOAN_RESCHEDULE_DETAILS explains the blank restructure date on every row of Extract A.",
  "If LOAN_WRITEOFF_TRANS does not exist, delete that line and run again."),
 ("DD_11", "Account status codes in use. Gives the counts behind the undocumented H and F codes.", "We still need the meaning of each code from the vendor."),
 ("DD_09", "Every scheme with its account count. Proves whether the MAIIC% / FINES% name filter leaves any loan scheme out of the extracts.",
  "Any scheme marked N with loan accounts on it is outside the extracts today."),
 ("DD_19", "Checks whether the dates the extracts filter on carry a time of day. If they do, postings made during the last day of a period can be left out.",
  "A zero in ROWS_WITH_TIME_OF_DAY means the date tests are safe for that column."),
 ("DD_16f", "Sample loan (Ebenezer Midian): the full ledger on every GL code. Shows the real posting pattern, the signs, and any fee postings for one loan we already know well.",
  "We expect five disbursement postings totalling 314,900,900."),
 ("DD_16d", "Sample loan: the stored instalment chart. Shows whether it has a row at the start date, whether it carries a balance column, and how the amount recovered is held against each instalment.", ""),
 ("DD_16c", "Sample loan: the disbursement schedule table. Extract A lists five entries dated 15 July 2025; the ledger shows the same amounts paid out on 8 August 2025.", ""),
 ("DD_16a", "Sample loan: the account master row, every column. Shows what else is held per account (rate, status, any policy or margin fields).", ""),
 ("DD_16b", "Sample loan: the loan master row, every column. Moratorium fields, sanction details and any link to the origination system.", ""),
 ("DD_16e", "Sample loan: the instalment plan rows (repayment frequency).", ""),
 ("DD_16g", "Sample loan: the balance history. Shows which balances are held besides principal, and gives an independent check on the running balance in Extract B.", ""),
 ("DD_16h", "Sample loan: reschedule rows, if any.", ""),
 ("DD_16i", "Sample loan: write-off rows, if any.", ""),
 ("DD_06", "Finds every table that carries the loan account key. This is how we discover tables nobody has mentioned: fees, charges, rate history.", ""),
 ("DD_07", "Finds tables by subject from their names (rate, PLR, charge, fee, audit, application, IFRS).", ""),
 ("DD_14", "Remaining code lists: repayment frequency and moratorium units.", ""),
 ("DD_04", "Declared keys and foreign keys: how the tables join.", "Vendor systems often declare few keys; a short result is normal."),
 ("DD_05", "Indexes. Where keys are not declared, the unique indexes show what identifies a row.", ""),
 ("DD_02", "The full table list with row counts.", ""),
 ("DD_08", "Existing views and procedures that read the loan tables, names only. Shows which standard reports sit on the same data.", ""),
]
assert sorted(k for k, _, _ in ORDER) == sorted(sql), set(sql) ^ {k for k, _, _ in ORDER}
out = ["""--------------------------------------------------------------------------------
-- MAIIC E-Banker: data dictionary queries IN RUN ORDER
-- Prepared by Dupleix Institute, 5 October 2026
-- Version 2, revised the same day after an independent review.
--
-- The same 27 read-only queries as MAIIC_EBanker_Data_Dictionary_Queries.sql,
-- re-ordered so that the ones that unblock the most come first. Nothing here
-- creates, changes or deletes anything. These queries were written without
-- access to the database and have not yet been run.
--
-- Run as the user that runs PROC_BUILD_MAIIC_FACILITY_REGISTER.
-- Run the two session settings first, then one query at a time.
--
-- HOW THE FILES SHOULD COME BACK (the export contract)
--   - CSV, comma separated, UTF-8, one header row, saved under the name shown.
--   - Dates as YYYY-MM-DD; dates with a time as YYYY-MM-DD HH:MM:SS (24 hour).
--   - Account numbers exactly as stored, as text, with their leading zeros.
--   - Amounts as plain numbers: dot for the decimal point, no thousands
--     separators, minus sign kept.
--   - Do not open and re-save the CSV in Excel. Excel swapped days and months
--     in the earlier extracts and drops leading zeros from account numbers.
--
-- TIER 1 (runs 1 to 11)   answers the open questions on Extracts A, B and C
-- TIER 2 (runs 12 to 20)  one sample loan, every column
-- TIER 3 (runs 21 to 27)  the rest of the dictionary
--------------------------------------------------------------------------------

-- RUN 0   |   SESSION SETTINGS: run these two lines first. They change only how
--             this session displays dates and numbers, nothing in the database.
ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS';
ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '.,';
"""]


def wrap(prefix, text, width=76):
    words, lines, cur = text.split(), [], ""
    for w in words:
        if len(cur) + len(w) + 1 > width:
            lines.append(cur); cur = w
        else:
            cur = (cur + " " + w).strip()
    lines.append(cur)
    pad = " " * len(prefix)
    return "\n".join(("-- " + (prefix if i == 0 else pad) + l) for i, l in enumerate(lines))


TIERS = {1: "TIER 1. ANSWERS THE OPEN QUESTIONS ON EXTRACTS A, B AND C",
         12: "TIER 2. ONE SAMPLE LOAN, EVERY COLUMN (Ebenezer Midian, 000104430000084)",
         21: "TIER 3. THE REST OF THE DICTIONARY"}
for i, (k, why, look) in enumerate(ORDER, 1):
    if i in TIERS:
        out.append("\n--" + "=" * 78 + "\n-- " + TIERS[i] + "\n--" + "=" * 78 + "\n")
    out.append(f"-- RUN {i} of {len(ORDER)}   |   save as {blocks[k]['file']}")
    out.append(wrap("WHY:  ", why))
    if look:
        out.append(wrap("NOTE: ", look))
    out.append(sql[k] + "\n\n")
open(D + r"\RUN ORDER - data dictionary queries by priority.sql", "w", encoding="utf-8", newline="\r\n").write("\n".join(out))
print("written; queries:", len(ORDER), "| run numbers:", {k: i for i, (k, _, _) in enumerate(ORDER, 1)})
