"""Spec v4: section 16.8 PDs for a book with little history; the megafarm_pd_method setting; the question to Finance on the 2025 method."""
p = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\specs\MAIIC_EIR_Engine_Specification_v4_2026-10-07.md"
s = open(p, encoding="utf-8").read()
def rep(old, new):
    global s
    assert old in s, old[:70]
    s = s.replace(old, new)
sec = '''### 16.8 Probabilities of default for a book with little history

**The difficulty.** MAIIC measures its probabilities of default with transition matrices: how often, over the years observed, a loan in one grade moved to another, and in particular to default. That needs years. The Mega Farm programme has two: the 2024 loans (K10.6 billion, a third in default by December 2024) and the 2025 loans (K44.4 billion of new lending, almost all in default by December 2025). What it does have is breadth: about 7,000 accounts and up to 21 monthly loan-book runs each, roughly 150,000 account-months. The problem is not too few observations; it is that every observation comes from one or two seasons, one of them catastrophic. A matrix fitted to that would say the probability of default is 95 percent for ever, which describes 2025 and forecasts nothing.

**How the 2025 provision was actually arrived at is still to be confirmed.** The statements show the result (note 8: K39.76 billion against K51.54 billion gross) but not the method, and that method is the starting point for anything the system does. The question is with Finance (section 5); until it is answered, the treatment below is the proposal.

**The method, in six steps.**

1. **Measure by season, not by month.** A Mega Farm loan falls due once, after harvest, so "days past due" means nothing until the due date. The states are set at the due date plus a grace period (paid in full, partly paid, unpaid) and again at the next season's due date. A monthly matrix on a loan like this sees twelve months of "current" and then one jump, and learns nothing in between.
2. **Segment by what drives repayment.** Seed against fertilizer against equipment; voucher against cash; which maize buyer (ADMARC, NFRA, ACE, none); district; the cohort year. With 7,000 accounts a segment's default rate is well measured even from one season, and the differences between segments are real information that a single overall rate hides.
3. **Anchor the probability on cohort default rates, with a benchmark.** Each segment's observed season default rate is blended with a benchmark for a normal year (Malawi's farm-input programmes and their recovery record, microfinance agricultural lending, the 2024 cohort as the one ordinary season on record), weighted by how many seasons of own data exist. This is the standard treatment of a short-history book: the own experience gains weight each season, and the benchmark says what a normal year looks like until the book has seen one.
4. **Put the probability second and recovery first.** For the 95 percent already in default the probability is 100 percent by definition; the provision depends entirely on how much comes back: the maize delivered, its value, how much the buyers pay and when. The loss given default is built from the 2025 collection experience (K14.8 billion repaid on the fund, the maize write-down, the buyer receivables) by segment and season. That is where the K39.8 billion of provision comes from, and it is the part the data supports.
5. **Forward-looking by scenario, not by regression.** A regression on the economy needs years; two seasons give nothing. The scenario set of section 15 (a normal rainfall season, a drought season, a devaluation year) carries a default rate and a recovery rate per segment for the programme, weighted as section 15 describes, and the manual overlay route of 14.6 is used with the reason written down.
6. **Keep it separate and label it.** The MAIIC book keeps its transition-matrix probabilities. The Mega Farm schemes get their own method card in the system (section 14.7's cards), their own back-test every season, and a rule that the method is reviewed once three seasons exist. The audit workbook then says exactly what was done for which book and why.

**The governed setting**, `megafarm_pd_method`, with three options: *seasonal cohort default rate with a benchmark prior* (seeded); *transition matrix*, available once three seasons exist and declined until then; *expert judgement with overlay*, for a season the data cannot describe. The benchmark rate and the credibility weight are governed amounts beside it.

**What the two seasons already say.** The 2024 cohort, the only ordinary season on record, lost a third in its first year. The 2025 cohort was four times larger, mostly vouchers, and almost wholly defaulted. Those two points already bracket the normal and the bad scenario of step 5, and the cohort split is the first segment to run once the pack 2 extracts arrive.

'''
rep("### 16.6 Two checks the pack 2 data will settle", sec + "### 16.6 Two checks the pack 2 data will settle")
# order: 16.8 should follow 16.7; renumber so the new section is 16.8 at the end
s = s.replace("### 16.8 Probabilities of default for a book with little history", "### 16.X Probabilities of default for a book with little history", 1)
a = s.index("### 16.X Probabilities of default"); b = s.index("### 16.6 Two checks the pack 2 data will settle")
block = s[a:b]; s = s[:a] + s[b:]
end = s.index("## 17. Glossary of the new terms")
s = s[:end] + block.replace("### 16.X", "### 16.8") + s[end:]
rep("| Mega Farms facilities | **Out of the EIR engine; in the ECL module; MAIIC's 5 percent share on the net carrying amount** | Decided D30 on the 2025 financial statements (section 16); Dr Thom to confirm | O22 |",
    "| Mega Farms facilities | **Out of the EIR engine; in the ECL module; MAIIC's 5 percent share on the net carrying amount** | Decided D30 on the 2025 financial statements (section 16); Dr Thom to confirm | O22 |\n| Probability of default for the Mega Farm schemes (`megafarm_pd_method`) | Seasonal cohort default rate with a benchmark prior | Recommendation (section 16.8); the transition matrix is declined until three seasons exist; the method used for the 2025 provision to be confirmed by Finance | new |")
rep("| Finance | The questions on the 28 year-end adjustments; the historic materiality threshold | `Note to Barry - the 28 interest adjustments of 31 Dec 2025.pdf`; O20 | December 2025 interest; the historic assessment |",
    "| Finance | The questions on the 28 year-end adjustments; the historic materiality threshold; **how the 2025 Mega Farm provision of K39.76 billion was arrived at** (the staging rule, the probability and loss assumptions, the treatment of maize and the buyer receivables) | `Note to Barry - the 28 interest adjustments of 31 Dec 2025.pdf`; O20; section 16.8 | December 2025 interest; the historic assessment; the starting point for the Mega Farm provisioning method |")
rep("- **Fund share**:", "- **Cohort default rate**: the share of the loans made in one season that had defaulted by a set point after their due date; the probability measure for a book too young for a transition matrix.\n- **Benchmark prior**: the default rate assumed for a normal year from outside evidence, blended with the book's own experience until the book has enough seasons of its own.\n- **Fund share**:")
open(p, "w", encoding="utf-8").write(s); print("16.8 added at the end of section 16; setting; Finance question; glossary")
