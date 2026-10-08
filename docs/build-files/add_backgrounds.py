"""Add a Background subsection to sections 14 and 15 of the FLI/scenario text, renumber, and fix the patch's cross references."""
p = "spec_fli_sections.md"; s = open(p, encoding="utf-8").read()
bg14 = """### 14.1 Background: why the economy enters the loss estimate

An expected credit loss is, for each loan, the probability that the borrower defaults (PD) times the share of the exposure that would be lost if they did (LGD) times the exposure at that moment (EAD), discounted at the loan's effective interest rate. Of the three, the PD is the one the economy moves most. MAIIC measures its PDs from its own history through the transition matrices: how often, over the years observed, a loan in a given grade moved to default. That history is the honest starting point, but it is an average over the years that happened to be in the window, good and bad together: a "through-the-cycle" figure. IFRS 9 asks for the probability that applies to the twelve months, or the lifetime, that is now ahead: a "point-in-time" figure that reflects where the economy is and where it is expected to go (5.5.17(c), B5.5.49). The step from the first to the second is the forward-looking adjustment.

The link between the economy and defaults is made with a **regression**: a statistical fit that says how much a credit-loss measure (the share of loans defaulting in a quarter, the NPL ratio, the roll rate from one grade to the next) has moved, historically, for a given move in an economic series (growth, inflation, the exchange rate, the harvest). Three things matter in practice. The relationship is usually **lagged**: a bad harvest shows up in defaults two or three quarters later, not in the same quarter. It is often in **changes** rather than levels: it is the fall in growth, not the level of growth, that moves defaults. And it must make **economic sense**: a fit that says defaults fall as unemployment rises is a statistical accident, however high its R-squared, and must not be used. The regression produces a predicted loss measure for each future period under an assumed path of the economy; the ratio of that prediction to the base period's prediction is the adjustment applied to every loan's PD.

Where the statistics are thin, which they are for an institution of MAIIC's size with two years of core-banking history, the standard allows judgement, provided it is reasonable, supportable and documented (B5.5.52): a management overlay. The sections that follow give both roads and the governance that makes either one auditable.

"""
bg15 = """### 15.1 Background: what a scenario is, and why one is not enough

A scenario is a coherent story about the economy over the next few years, written down as a path for each macro series: growth, inflation, the exchange rate, interest rates, the harvest. The forward-looking adjustment of section 14 needs such a path to predict from; a single "most likely" path would be the natural choice, and it is the wrong one. Credit losses are not symmetrical: a year that is one notch worse than expected costs far more in defaults than a year one notch better saves, because borrowers who were already stretched tip into default while those who were comfortable merely become more comfortable. Calculating the loss on the single most likely path therefore understates the expected loss. IFRS 9 recognises this and asks for an unbiased, probability-weighted estimate over a range of possible outcomes (5.5.17(a), B5.5.42): the loss under each of several scenarios, each weighted by how likely it is, and the weighted average reported.

Scenarios for the ECL are not the same thing as stress tests. A stress test asks what happens under a severe but plausible shock, with no weight attached, to see whether capital would hold; its scenarios are deliberately harsh. ECL scenarios are a probability-weighted view of what is actually expected, in which the severe case carries a small weight and the base case a large one. The two should share their economics (the same base path, the same understanding of what a devaluation does to the book) but they answer different questions, and a system that uses one set for both will be wrong for one of them.

The weights are the part that attracts the most scrutiny, because a small change in a weight can move the provision materially and nothing in the data pins them down. Good practice therefore does three things: anchors each scenario's severity to something that has actually happened, so that "a downside" is not an abstraction but "2023 again"; sets the weights by a documented judgement that two accountable people approve; and tests afterwards whether the realised year fell inside the range the scenarios spanned, so that weights that were too optimistic are seen and revised. For Malawi the anchors are not hard to find: the 2023 devaluation of 44 percent, the 2016 drought, the 2012 float, and the 2021 to 2022 recovery each give a path that the institution's own loan book has already been through.

"""
s = s.replace("### 14.1 In plain language", bg14 + "### 14.2 In plain language", 1)
s = s.replace("### 15.1 In plain language", bg15 + "### 15.2 In plain language", 1)
heads14 = ["How the adjustment works today", "The correlation finder", "The regression, repaired", "Three routes to the adjustment, one governed choice", "Lineage on the loan", "Where it lives, and the settings", "Acceptance and order of work"]
for i, h in enumerate(heads14):
    old = f"### 14.{i+2} {h}"; new = f"### 14.{i+3} {h}"
    assert old in s, old; s = s.replace(old, new, 1)
heads15 = ["What good practice asks for", "The scenario set, as a governed object", "Weighting the loss, not the economy", "The rules, as settings", "Overlays, back-tests and sensitivity", "The first set, proposed", "Where it lives", "Acceptance and order of work"]
for i, h in enumerate(heads15):
    old = f"### 15.{i+2} {h}"; new = f"### 15.{i+3} {h}"
    assert old in s, old; s = s.replace(old, new, 1)
for a, b in [("of 14.2 do not change", "of 14.3 do not change"), ("Steps 4 and 5 of 14.2", "Steps 4 and 5 of 14.3"), ("steps 4 and 5 of 14.2", "steps 4 and 5 of 14.3"),
             ("tests of 14.3", "tests of 14.4"), ("the repairs of 14.4", "the repairs of 14.5"), ("register of section 14.5", "register of section 14.6"),
             ("principle of 15.2 citing", "principle of 15.3 citing")]:
    s = s.replace(a, b)
open(p, "w", encoding="utf-8").write(s); print("backgrounds added; subsections renumbered")
q = "patch_spec_fli.py"; t = open(q, encoding="utf-8").read()
t = t.replace("(section 14.5); the manual overlay", "(section 14.6); the manual overlay")
t = t.replace("| Required | Recommendation (section 14.3) | new |", "| Required | Recommendation (section 14.4) | new |")
t = t.replace("(section 14.3); MAIIC may tighten", "(section 14.4); MAIIC may tighten")
t = t.replace("(section 15.4); the macro-path", "(section 15.5); the macro-path")
t = t.replace("| 3 | Recommendation (section 15.5) | new |", "| 3 | Recommendation (section 15.6) | new |")
t = t.replace("none above 60 percent | Recommendation (section 15.5) | new |", "none above 60 percent | Recommendation (section 15.6) | new |")
t = t.replace("| Required | Recommendation (section 15.5) | new |", "| Required | Recommendation (section 15.6) | new |")
t = t.replace("| Required | Recommendation (section 15.6) | new |\\n\"\n    \"| How the loan book", "| Required | Recommendation (section 15.7) | new |\\n\"\n    \"| How the loan book")
t = t.replace("Correlation Finder (new, 14.3), Regression Analysis (repaired, 14.4), FLI Adjustments (new, 14.5)", "Correlation Finder (new, 14.4), Regression Analysis (repaired, 14.5), FLI Adjustments (new, 14.6)")
open(q, "w", encoding="utf-8").write(t); print("patch references updated")
