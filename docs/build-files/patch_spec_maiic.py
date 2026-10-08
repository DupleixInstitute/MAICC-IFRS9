"""Spec v4: a MAIIC document names no other client. Replace ZNBS/FDH/BBS references with the Dupleix suite."""
p = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\specs\MAIIC_EIR_Engine_Specification_v4_2026-10-07.md"
s = open(p, encoding="utf-8").read()
R = [
 ("Every system Dupleix now delivers (the ZNBS stress-testing suite, the BBS suite, the FDH IFRS 9 platform) uses the same shape of screen, so that a finance officer who has learned one of them can find their way around the next.",
  "Every system Dupleix now delivers uses the same shape of screen, the Dupleix suite layout, so that a finance officer who has learned one of them can find their way around the next."),
 ("| FDH `Components/Shell/Sidebar.vue`; MAIIC keeps its own recursive renderer so that a group can hold sub-groups (the modelling group needs them) |",
  "| The suite's sidebar; MAIIC keeps its own recursive renderer so that a group can hold sub-groups (the modelling group needs them) |"),
 ("| FDH `AppLayout.vue` |\n| Top bar", "| The suite's shell |\n| Top bar"),
 ("| FDH `Components/Shell/Topbar.vue` |", "| The suite's top bar |"),
 ("| FDH `AppLayout.vue` |\n| Processing pill", "| The suite's shell |\n| Processing pill"),
 ("| Both |\n| Fail-loud error dialogue", "| Already in MAIIC |\n| Fail-loud error dialogue"),
 ("Everything in the FDH shell is adopted, including light and dark mode (11.5).", "Everything in the suite's shell is adopted, including light and dark mode (11.5)."),
 ("| Taken from |", "| Source |"),
 ("as built for FDH on the ZNBS pattern. Routes, permissions and calculations are unchanged",
  "as the Dupleix suite builds them. Routes, permissions and calculations are unchanged"),
 ("The ZNBS stress-testing suite answers that with one audit workbook per Bank of Zambia directive:",
  "Dupleix's compliance-audit engine, in production in the suite, answers that with one audit workbook per standard or directive:"),
 ("Decision D25 brings that to MAIIC, with two additions the EIR work makes possible:",
  "Decision D25 adopts it for MAIIC, with two additions the EIR work makes possible:"),
 ("The first three are the ZNBS shape; the fourth is new.", "The first three are the engine's standard shape; the fourth is added for MAIIC."),
 ("The MAIIC counterpart of the ZNBS golden-numbers check |", "The MAIIC counterpart of the engine's golden-numbers check |"),
 ("The nine ZNBS columns, then two MAIIC additions.", "The engine's nine standard columns, then two MAIIC additions."),
 ("The five statuses mean exactly what they mean at ZNBS:", "The five statuses are the engine's standard ones:"),
 ("the way the ZNBS regulatory return is archived.", "the way the suite archives a regulatory return."),
 ("| The per-record trace from the ZNBS suite:", "| The suite's per-record trace:"),
 ("`tools/compliance/` from the ZNBS repository (`audit_workbook.py`, `build_audit.py`, `md_to_pdf.py`) becomes `tools/compliance/` in MAICC-IFRS9,",
  "The suite's `tools/compliance/` (`audit_workbook.py`, `build_audit.py`, `md_to_pdf.py`) becomes `tools/compliance/` in MAICC-IFRS9,"),
 ("| D25 | **Compliance audit workbooks, as built for ZNBS, are adopted**", "| D25 | **Dupleix's compliance audit workbooks are adopted**"),
]
for old, new in R:
    assert old in s, old[:70]
    s = s.replace(old, new)
import re
left = re.findall(r"ZNBS|FDH|BBS", s)
open(p, "w", encoding="utf-8").write(s); print("genericised; remaining client names:", left)
