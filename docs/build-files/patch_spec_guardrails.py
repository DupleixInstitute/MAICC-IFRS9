"""Spec v4 section 14: adopt the guardrail, the structural-events register, the proxy deriver and the series profiler by name."""
p = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\specs\MAIIC_EIR_Engine_Specification_v4_2026-10-07.md"
s = open(p, encoding="utf-8").read()
def rep(old, new):
    global s
    assert old in s, old[:70]
    s = s.replace(old, new)
add = '''
**Four further pieces of the suite's forward-looking module are adopted by name**, because each closes a gap a reviewer would otherwise find:

- **The guardrail that declines.** A fitted relationship may feed an adjustment only when all four hold: enough observations (a governed minimum), the realised sign matches the expected sign, R-squared at or above the governed cut-off, and statistical significance (p-value at or below a governed alpha). Otherwise the verdict is *declined* with its reason, the relationship is quarantined, and the PD stays at its pre-FLI value until a person decides. This is the sign and cut-off test of the earlier calculator made complete: a weak or wrong-signed fit cannot reach a loan by accident, and the system says so rather than silently applying nothing.
- **The structural-events register.** A governed table of the dated events that break economic series: for Malawi the 2012 float, the 2016 drought, the 2023 devaluation, the 2024 to 2025 policy-rate steps, each with its source (RBM, IMF). The finder's diagnostics test for a break at those dates (a Chow test) before trusting a fit across them; the scenario set of section 15 uses the same events as its anchors; the SICR engine may use them as qualitative triggers. One register, three consumers, so the "why 2023" of a scenario and the "why this fit fails across 2023" of a regression cite the same row.
- **The credit-loss proxy deriver.** The Y series are derived from MAIIC's own data and never typed or invented: the NPL ratio (Stage 3 exposure over gross exposure) per period from the loan books, and the Stage 1 to default 12-month rate from the approved transition matrices. With fewer than two periods a proxy is recorded as not derivable, not interpolated.
- **The series profiler.** The distribution of every X and Y series is stored, not recomputed: observations, span, mean, standard deviation, skewness and kurtosis, a normality verdict against governed limits, and a unit-root verdict, with the correlation method the shape can support. The finder reads the profile to choose rank or linear methods, and the audit workbook cites it, so the choice of method is a recorded fact rather than a default.

These add three settings to the Governance Centre beside the two of 14.8: the minimum observations for a fit (seeded 12), the significance level (seeded 5 percent), and the skewness and kurtosis limits for a normality verdict (seeded 1.0 and 3.0). The register is seeded with the Malawi events above as proposals for Dr Thom to confirm.
'''
rep("### 14.5 The regression, repaired", add.strip("\n") + "\n\n### 14.5 The regression, repaired")
rep("| R-squared cut-off for an approvable model (`fli_r2_cutoff`) | 30 percent | Recommendation (section 14.4); MAIIC may tighten | new |",
    "| R-squared cut-off for an approvable model (`fli_r2_cutoff`) | 30 percent | Recommendation (section 14.4); MAIIC may tighten | new |\n"
    "| Minimum observations for a fit (`fli_min_observations`) | 12 | Recommendation (section 14.4, the guardrail) | new |\n"
    "| Significance level for a fit (`fli_alpha`) | 5 percent | Recommendation (section 14.4, the guardrail) | new |\n"
    "| Normality limits for a series profile (`fli_normality_limits`) | Skewness 1.0; excess kurtosis 3.0 | Recommendation (section 14.4, the profiler) | new |")
rep("FL-1 the finder (one day); FL-2 the regression repair with its tests (one day);",
    "FL-1 the finder with the guardrail, the register, the proxy deriver and the profiler (one and a half days); FL-2 the regression repair with its tests (one day);")
rep("- **Overlay**: a forward-looking adjustment applied by judgement",
    "- **Guardrail**: the four tests (observations, sign, strength, significance) a fitted relationship must pass before it may adjust a PD; a fit that fails is declined with its reason and quarantined.\n- **Structural event**: a dated break in the economy (a float, a drought, a devaluation) kept in a governed register and used by the diagnostics, the scenarios and the SICR triggers alike.\n- **Overlay**: a forward-looking adjustment applied by judgement")
open(p, "w", encoding="utf-8").write(s); print("guardrail, register, deriver, profiler adopted in 14.4; three settings; glossary")
