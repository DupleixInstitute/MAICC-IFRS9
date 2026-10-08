"""Insert section 11 (the user interface) and decision D24 into spec v4."""
p = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\specs\MAIIC_EIR_Engine_Specification_v4_2026-10-07.md"
s = open(p, encoding="utf-8").read()
ui = open(r"C:\Users\wadza\AppData\Local\Temp\claude\c--xampp-htdocs-Stress-Testing-App\ee984144-7b28-4df1-9a6e-560f112869e9\scratchpad\spec_ui_section.md", encoding="utf-8").read()
old = "## 11. Glossary of the new terms"
assert old in s and "## 11. The user interface" not in s
s = s.replace(old, ui.rstrip("\n") + "\n\n## 12. Glossary of the new terms")
old_d = "| D23 | **The three MAIIC extract scripts are retired.**"
assert old_d in s
d24 = ("| D24 | **The Dupleix-suite layout is adopted for the user interface** (section 11): the six working groups with their colours, the icon rail, the page header with breadcrumb and the period chip, as built for FDH on the ZNBS pattern. Routes, permissions and calculations are unchanged; screens move to where the suite puts them. | Edward, 7 Oct 2026 | One shape of screen across every Dupleix system; the EIR screens of P8 are placed in it from the start |\n")
s = s.replace(old_d, d24 + old_d)
s = s.replace("- **Control exception**:", "- **Icon rail**: the navigation panel folded to a narrow column of icons; labels appear as tooltips.\n- **Breadcrumb**: the line at the top of a page that reads group, then page, so the reader knows where in the system they are.\n- **Control exception**:")
s = s.replace("subtitle: What the follow-up extracts proved, what has been decided, how the data is loaded, and what is left to build",
              "subtitle: What the follow-up extracts proved, what has been decided, how the data is loaded, the user interface, and what is left to build")
open(p, "w", encoding="utf-8").write(s); print("section 11 inserted; D24 added")
