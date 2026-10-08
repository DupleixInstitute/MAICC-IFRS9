"""Insert section 12 (compliance audit workbooks) and decision D25 into spec v4; glossary becomes 13."""
p = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\specs\MAIIC_EIR_Engine_Specification_v4_2026-10-07.md"
s = open(p, encoding="utf-8").read()
sec = open(r"C:\Users\wadza\AppData\Local\Temp\claude\c--xampp-htdocs-Stress-Testing-App\ee984144-7b28-4df1-9a6e-560f112869e9\scratchpad\spec_audit_section.md", encoding="utf-8").read()
old = "## 12. Glossary of the new terms"
assert old in s and "## 12. Compliance audit workbooks" not in s
s = s.replace(old, sec.rstrip("\n") + "\n\n## 13. Glossary of the new terms")
old_d = "| D23 | **The three MAIIC extract scripts are retired.**"
assert old_d in s
d25 = ("| D25 | **Compliance audit workbooks, as built for ZNBS, are adopted** (section 12): five workbooks (IFRS 9 EIR, IFRS 9 impairment, IFRS 7 and IAS 1, RBM classification, Contract Schedule 1), each row naming the governance setting that governs it and the test that proves it; a register in the Governance Centre where MAIIC signs; an auditor's pack per period in the Report Hub. | Edward, 7 Oct 2026 | Deloitte walks from paragraph to screen to test; the status counts are the project's status in one line |\n")
s = s.replace(old_d, d25 + old_d)
s = s.replace("- **Control exception**:",
              "- **Audit workbook**: one Excel file per standard or directive, one row per section, with what the system does, where to see it, a status and a sign-off; built from a data module together with its Markdown and PDF twins.\n"
              "- **Baselines sheet**: the sheet of the audit workbook that re-checks the acceptance ties of section 9 against the live database with a PASS or FAIL formula.\n"
              "- **Auditor's pack**: the period's export, the five workbooks and a manifest of checksums, bundled for Deloitte.\n"
              "- **Control exception**:")
s = s.replace("subtitle: What the follow-up extracts proved, what has been decided, how the data is loaded, the user interface, and what is left to build",
              "subtitle: What the follow-up extracts proved, what has been decided, how the data is loaded, the user interface, the audit workbooks, and what is left to build")
open(p, "w", encoding="utf-8").write(s); print("section 12 inserted; D25 added; glossary is 13")
