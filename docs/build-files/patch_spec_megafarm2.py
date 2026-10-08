"""Spec v4: replace section 16 with the plain-language version."""
p = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\specs\MAIIC_EIR_Engine_Specification_v4_2026-10-07.md"
s = open(p, encoding="utf-8").read()
sec = open(r"C:\Users\wadza\AppData\Local\Temp\claude\c--xampp-htdocs-Stress-Testing-App\ee984144-7b28-4df1-9a6e-560f112869e9\scratchpad\spec_megafarm_section.md", encoding="utf-8").read()
a = s.index("## 16. The Mega Farm loans"); b = s.index("## 17. Glossary of the new terms")
s = s[:a] + sec.rstrip("\n") + "\n\n" + s[b:]
old = "| D30 | **The Mega Farm programme loans are outside the EIR engine and inside the ECL module** (section 16): a Government fund that MAIIC administers at a contractual 15 percent split 5 to MAIIC and 10 to the fund, 95 percent credit-impaired, with the loss charged to the fund. The engine computes MAIIC's 5 percent share on the net carrying amount, the ECL module stages and provisions the loans against the fund, and the Report Hub produces the Mega Farms notes. |"
new = "| D30 | **The Mega Farm programme loans stay outside the EIR engine and go fully into the ECL module** (section 16): they are Government money that MAIIC runs on its behalf, at 15 percent of which 5 is MAIIC's and 10 the fund's; 95 percent are in default and the losses are the fund's. The engine works out MAIIC's 5 percent share on the amount expected to be recovered, the ECL module stages and provides for the loans against the fund, and the Report Hub produces the Mega Farms notes of the financial statements. |"
assert old in s; s = s.replace(old, new)
open(p, "w", encoding="utf-8").write(s); print("section 16 replaced with the plain-language version")
