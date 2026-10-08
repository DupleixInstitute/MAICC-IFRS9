"""Spec v4: replace section 6 with the landing-zone design, amend D22, add the feed screen, the raw tables and a baseline row."""
import re
p = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\specs\MAIIC_EIR_Engine_Specification_v4_2026-10-07.md"
s = open(p, encoding="utf-8").read()
new6 = open(r"C:\Users\wadza\AppData\Local\Temp\claude\c--xampp-htdocs-Stress-Testing-App\ee984144-7b28-4df1-9a6e-560f112869e9\scratchpad\spec_section6.md", encoding="utf-8").read()
start = s.index("## 6. Loading the loan books: the bootstrap importer"); end = s.index("## 7. The importers, reworked")
s = s[:start] + new6.rstrip("\n") + "\n\n" + s[end:]

def rep(old, new):
    global s
    assert old in s, old[:70]
    s = s.replace(old, new)

# D22 amended
rep("| D22 | **The month-end loan books are loaded from the stored loan book history**, not re-typed from Excel reports, by a bootstrap importer (section 6). | Dupleix, 7 Oct 2026 | 21 month-ends in one run, each tied to the ledger; the Excel reports carried scrambled dates. |",
    "| D22 | **E-Banker is ingested through a landing zone and the monthly loan book is derived from the ledger** (section 6): raw tables that mirror E-Banker, loaded append-only by whichever of five routes MAIIC uses, with gates that refuse a pack that does not tie, and one re-runnable derivation. The Excel report importer becomes a fallback. | Dupleix, 7 Oct 2026; amended the same day from a bootstrap of the stored report | The ledger is the primary record and ties to every other table; the report starts only in December 2024 and stores every re-run. Every derived figure traces to raw rows an auditor can open. |")
# 11.3: feed screen under Data Foundation
rep("| | Imports | `imports.index` | | Customer & Loan Data |",
    "| | Imports | `imports.index` | | Customer & Loan Data |\n| | E-Banker Feed (queries, loads, watermarks, derive) | `eir-feed.index` (new, section 6.8) | eir.view; derive needs eir.govern | new |")
# 7.1 ledger importer now lands in the zone
rep("### 7.1 The ledger importer (replaces Extract B and Extract C)\n\nReads `P1_01`:",
    "### 7.1 The ledger importer (replaces Extract B and Extract C)\n\nLands `P1_01` in `ebanker_ledger` (section 6.3) and reads it from there:")
# section 9 baseline row
rep("| Take-on mapping | 105 of 109 facilities; 98 of 100 schedule blocks linked |",
    "| Take-on mapping | 105 of 109 facilities; 98 of 100 schedule blocks linked |\n| Derived loan book against the stored loan book's carrying amount, every month-end from December 2024 | 2,264 of 2,264 agree; every difference is a flagged row with a named cause |")
# section 8 P4b mention
rep("| **P4b Importer rework** (section 7) | Ledger, rate-history, contract-master, fee and take-on importers; the loan-book bootstrap; the four code corrections | Nothing |",
    "| **P4b Ingestion and importer rework** (sections 6 and 7) | The landing zone, the pack contract and gates, route 1 and the feed screen, the derivation; the rate-history, contract-master, fee and take-on importers; the four code corrections; route 2's script once the read-only account exists | Nothing; route 4 waits for Dr Thom and ICT |")

# 4.2: the feed route is the governed setting that was 'loan_book_feed' (O9)
rep("| How the monthly loan book arrives | Monthly CSV of the loan book query | Recommendation; a vendor change needs Dr Thom | O9 |",
    "| How E-Banker data arrives: the feed route (`ebanker_feed_route`) | Route 1, manual pack | Decided D22: all five routes of section 6.5 are built; MAIIC switches the route in force at any time; routes 4 and 5 need Dr Thom and ICT | O9 |")
rep("| **Governance Centre** (amber) | Governance Centre (the 28 settings of section 4.2) |", "| **Governance Centre** (amber) | Governance Centre (the settings of section 4.2) |")
# glossary
rep("- **Seeded default**:", "- **Landing zone**: the raw tables that mirror E-Banker, loaded exactly as received and never edited; everything else is derived from them.\n- **Pack**: one month's set of extract files plus a manifest of what they are, which query version made them and their hashes; the one form in which data enters, whichever route delivers it.\n- **Watermark**: the last source key loaded for a table; the next pack starts after it.\n- **Seeded default**:")
# 10 risks: add
rep("| The daily accrual exists for 14 accounts only |",
    "| A pack arrives by a new route before its gates are proven | A month derived from a file that did not tie | The gates are route-independent and run on every pack; a route is accepted only after one pack from it has passed on a copy |\n| The daily accrual exists for 14 accounts only |")
# subtitle
rep("how the data is loaded, the user interface", "how E-Banker is ingested, the user interface")
# the README pointer in the "how to read" of section 0 mentions sections 6 to 8 - fine. section 1 result sentence ok.
left = re.findall(r"bootstrap importer", s)
open(p, "w", encoding="utf-8").write(s); print("section 6 replaced; D22 amended; feed screen, baseline, risk, glossary added; 'bootstrap importer' left:", len(left))
