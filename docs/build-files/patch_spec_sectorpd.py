"""Spec v4 16.8: the third option, MAIIC agricultural-sector PD scaled to the programme."""
p = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\specs\MAIIC_EIR_Engine_Specification_v4_2026-10-07.md"
s = open(p, encoding="utf-8").read()
def rep(old, new):
    global s
    assert old in s, old[:70]
    s = s.replace(old, new)
old = "**The governed setting**, `megafarm_pd_method`, with three options: *seasonal cohort default rate with a benchmark prior* (seeded); *transition matrix*, available once three seasons exist and declined until then; *expert judgement with overlay*, for a season the data cannot describe. The benchmark rate and the credibility weight are governed amounts beside it."
new = """**A third option: MAIIC's agricultural-sector PD, scaled to the programme.** MAIIC's own agricultural book has what the programme lacks, which is time: six years of migrations, a term structure (how the probability builds over 12, 24 and 36 months) and an observed sensitivity to the same drivers, rainfall, maize prices, the kwacha and input costs. What it does not have is the programme's level of default: a sector probability of a few percent applied to a book that lost a third of its 2024 cohort in a year, and 95 percent of its 2025 cohort, would be wrong exactly where it matters, on the Stage 1 loans and on every new season. The method therefore takes the shape from the sector and the level from the programme:

probability (Mega Farm segment, horizon) = probability (MAIIC agricultural sector, horizon) × programme scalar

- The sector probability comes from MAIIC's approved agricultural transition matrix, or from the external-rating mapping for the same sector, and brings the term structure and the macro sensitivity with it.
- The scalar is the ratio of what the programme actually did to what the sector did in the same year: the 2024 cohort's 33 percent against the sector's 12-month probability, the 2025 cohort's 95 percent likewise. It is calibrated per segment where the data supports it (seed against fertilizer, voucher against cash, by maize buyer), governed, re-estimated every season and back-tested. It is a measured ratio, not a judgement.
- The forward-looking adjustment of section 14 and the scenarios of section 15 act through the sector probability, so a drought scenario moves the programme's probability the way it moves the sector's, only more.
- Guardrail: the method is declined until the scalar can be calibrated on at least one completed season, and a scalar above a governed ceiling is flagged for a second approval, so that it cannot quietly be set to one.

This is the benchmark prior of step 3 with the prior chosen well: MAIIC's own sector, on the method the auditors already accept, rather than an outside statistic. The plain cohort rate is kept beside it as the check.

**The governed setting**, `megafarm_pd_method`, with four options: *MAIIC agricultural-sector PD scaled to the programme* (seeded); *seasonal cohort default rate with a benchmark prior*, the check against the first; *transition matrix on the programme's own history*, available once three seasons exist and declined until then; *expert judgement with overlay*, for a season the data cannot describe. The programme scalar per segment, its ceiling, the benchmark rate and the credibility weight are governed amounts beside it, and each option has its method card (14.7) with the preconditions checked live."""
rep(old, new)
rep("| Probability of default for the Mega Farm schemes (`megafarm_pd_method`) | Seasonal cohort default rate with a benchmark prior | Recommendation (section 16.8); the transition matrix is declined until three seasons exist; the method used for the 2025 provision to be confirmed by Finance | new |",
    "| Probability of default for the Mega Farm schemes (`megafarm_pd_method`) | MAIIC agricultural-sector PD scaled to the programme | Recommendation (section 16.8); the seasonal cohort rate is the check; the programme's own transition matrix is declined until three seasons exist; the method used for the 2025 provision to be confirmed by Finance | new |\n| Programme scalar ceiling for the Mega Farm PD (`megafarm_scalar_ceiling`) | 40 times the sector PD | A scalar above it needs a second approval (section 16.8); the 2025 cohort implies about 30 | new |")
rep("- **Benchmark prior**:", "- **Programme scalar**: the measured ratio of the Mega Farm programme's default rate to MAIIC's agricultural-sector probability for the same year and horizon; the level the sector's shape is scaled to.\n- **Benchmark prior**:")
open(p, "w", encoding="utf-8").write(s); print("third option added; seeded; scalar ceiling setting; glossary")
