"""Spec v4: the RBM DFI credit-risk directive of 2018 and the governed staging thresholds (new 3.6); O13 row; 16.8 note; glossary; section 0 pointer."""
p = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\specs\MAIIC_EIR_Engine_Specification_v4_2026-10-07.md"
s = open(p, encoding="utf-8").read()
def rep(old, new):
    global s
    assert old in s, old[:70]
    s = s.replace(old, new)
sec = '''### 3.6 The Reserve Bank's directive for development finance institutions, and staging

MAIIC is licensed as a development finance institution, and the Reserve Bank of Malawi classifies its lending under the **Financial Services (Credit Risk Management for Development Finance Institutions) Directive, 2018** (Malawi Gazette Supplement of 13 July 2018, No. 18A, pages 42 to 47; a copy is under `docs/regulatory/` in the repository and beside this document). It is the authority behind MAIIC's staging, and the engine's governed thresholds follow it.

The directive sorts facilities by repayment period (section 2): short term, not more than 12 months; medium term, 12 to 60 months; long term, over 60 months. It then classifies each by how long instalments are overdue (section 10) and sets the provision for each class (the Schedule):

| Class | Short-term facility | Medium- and long-term facility | Provision |
|---|---|---|---|
| Standard | current, to 30 days overdue | 31 to 90 days | 0 percent |
| Special mention | 31 to 90 days | 91 to 180 days | 5 percent |
| Substandard | 91 to 180 days | 181 to 365 days | 20 percent |
| Doubtful | 181 to 365 days | 366 to 746 days | 50 percent |
| Loss | over 365 days | over 746 days | 100 percent |

Substandard, doubtful and loss are **non-performing**: the facility goes on non-accrual (section 13), interest accrued but not collected is reversed and interest is recognised only when received in cash, regardless of collateral; a loss facility is written off in the following quarter (section 12); where the directive's provision exceeds the IFRS one, the excess is an appropriation to a loan-loss reserve, not capital (section 15).

**What the engine takes from it.** Stage 3 (credit-impaired) is the directive's non-performing line: **181 days past due for a medium- or long-term facility, 91 days for a short-term one.** That is where MAIIC's long-standing rule of "over 181 days" comes from, and it rebuts the IFRS 9 presumption that default occurs no later than 90 days past due (B5.5.37) on the authority of the regulator's own classification of development-finance lending; the 2025 financial statements rest on it. Stage 2 is kept at 31 days past due for every tenor: the IFRS 9 presumption of a significant increase in credit risk at 30 days (B5.5.11) is not rebutted, because the directive's wider "standard" band for medium- and long-term facilities is a prudential classification, not evidence that credit risk has not risen. The rebuttal of that 30-day presumption stays available as a governed proposal, inactive until Dr Thom signs it (the staging rebuttal of O13).

The thresholds are seeded by tenor class into `staging_thresholds`, each row carrying the directive section it rests on: short term 31 and 91; medium and long term 31 and 181; the Mega Farm programme, which is seasonal input finance repaid after harvest and therefore short term whatever tenor the account carries, 31 and 91; and the long-term Stage 2 proposal at 91, future-dated. The classifier reads days past due from the stored report's overdue date and arrears buckets (section 6.2).

Two differences between the directive and IFRS 9 that the engine must show rather than resolve: the directive stops interest on a non-performing facility, where IFRS 9 5.4.1(b) continues it on the net carrying amount (the trial balance's interest-suspense account, 1320, is the directive's rule in practice, and the reconciliation carries the difference as its own line); and the directive's provision percentages are prudential floors, so the loan-loss reserve of section 15 is where any excess over the ECL sits.

'''
rep("## 4. Decisions, and the Governance Centre", sec + "## 4. Decisions, and the Governance Centre")
rep("| Rebutting the 30-day Stage 2 presumption | Allowed with documented evidence, approved | Recommendation; a Phase 0 sign-off | O13 |",
    "| Rebutting the 30-day Stage 2 presumption | Allowed with documented evidence, approved | Recommendation; a Phase 0 sign-off. The 90-day default presumption is already rebutted on the RBM DFI directive of 2018 (section 3.6): Stage 3 at 181 days for medium- and long-term facilities, 91 for short-term and Mega Farm | O13 |")
rep("6. **Keep it separate and label it.** The MAIIC book keeps its transition-matrix probabilities.",
    "6. **Keep it separate and label it.** Under the Reserve Bank's directive (section 3.6) the programme's seasonal facilities are short term and non-performing from 91 days past due, not the 181 that applies to MAIIC's medium- and long-term book; the governed thresholds carry a Mega Farm class for that. The MAIIC book keeps its transition-matrix probabilities.")
rep("- **Diff Int by ROI**:", "- **The DFI directive**: the Reserve Bank of Malawi's Financial Services (Credit Risk Management for Development Finance Institutions) Directive, 2018, which classifies MAIIC's facilities by days overdue and tenor; the source of Stage 3 at 181 days (section 3.6).\n- **Non-performing**: substandard, doubtful or loss under the directive; placed on non-accrual.\n- **Diff Int by ROI**:")
rep("That folder is code only.", "That folder is code only. The regulatory texts the document relies on are under `docs/regulatory/` (section 3.6).")
open(p, "w", encoding="utf-8").write(s); print("3.6 added; O13; 16.8; glossary; pointer")
