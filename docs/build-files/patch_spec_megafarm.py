"""Spec v4: section 16 the Mega Farm loans; D30; the O22 setting resolved; glossary becomes 17."""
p = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\specs\MAIIC_EIR_Engine_Specification_v4_2026-10-07.md"
s = open(p, encoding="utf-8").read()
sec = open(r"C:\Users\wadza\AppData\Local\Temp\claude\c--xampp-htdocs-Stress-Testing-App\ee984144-7b28-4df1-9a6e-560f112869e9\scratchpad\spec_megafarm_section.md", encoding="utf-8").read()
def rep(old, new):
    global s
    assert old in s, old[:70]
    s = s.replace(old, new)
rep("## 16. Glossary of the new terms", sec.rstrip("\n") + "\n\n## 17. Glossary of the new terms")
rep("| D23 | **The three MAIIC extract scripts are retired.**",
    "| D30 | **The Mega Farm programme loans are outside the EIR engine and inside the ECL module** (section 16): a Government fund that MAIIC administers at a contractual 15 percent split 5 to MAIIC and 10 to the fund, 95 percent credit-impaired, with the loss charged to the fund. The engine computes MAIIC's 5 percent share on the net carrying amount, the ECL module stages and provisions the loans against the fund, and the Report Hub produces the Mega Farms notes. | Dupleix, 8 Oct 2026, on the 2025 financial statements; Dr Thom to confirm (O22) | The statements present the programme ring-fenced on every page; an EIR on a programme split would restate nothing they report, and the revenue-shift number is about MAIIC's own lending |\n| D23 | **The three MAIIC extract scripts are retired.**")
rep("| Mega Farms facilities | In scope, stage and interest basis confirmed first | Recommendation; the largest swing in the number | O22 |",
    "| Mega Farms facilities | **Out of the EIR engine; in the ECL module; MAIIC's 5 percent share on the net carrying amount** | Decided D30 on the 2025 financial statements (section 16); Dr Thom to confirm | O22 |")
rep("'mega_farms_scope'", "'mega_farms_scope'") if False else None
rep("- **Landing zone**:", "- **Mega Farm programme**: the Government's K20 billion farm-input lending scheme that MAIIC administers; loans at 15 percent split 5 to MAIIC and 10 to the fund; losses charged to the fund; presented ring-fenced in the financial statements.\n- **Fund share**: the 10 percent of Mega Farm interest that belongs to the Government fund; MAIIC's share is the other 5 percent.\n- **Landing zone**:")
rep("the forward-looking adjustments and scenarios, and what is left to build", "the forward-looking adjustments and scenarios, the Mega Farm loans, and what is left to build")
# section 5: Dr Thom row
rep("| Dr Thom | Mega Farms in or out (O22), the written confirmation of D20, and the seven choices of section 4.2 | This document | The headline number |",
    "| Dr Thom | Confirmation of the Mega Farm treatment (D30, section 16), the written confirmation of D20, and the remaining choices of section 4.2 | This document; the O22 paper of 8 Oct | The headline number |")
rep("| **P9 Acceptance and UAT** | T1 to T8 on MAIIC's data; the revenue shift per year, 2024, 2025 and 2026 to date, which is the output Dr Thom asked for by name; UAT with Finance | The fee template; Mega Farms decided; the sign-offs |",
    "| **P9 Acceptance and UAT** | T1 to T8 on MAIIC's data; the revenue shift per year, 2024, 2025 and 2026 to date, which is the output Dr Thom asked for by name; UAT with Finance | The fee template; D30 confirmed; the sign-offs |")
open(p, "w", encoding="utf-8").write(s); print("section 16 added; D30; O22 row; glossary 17")
