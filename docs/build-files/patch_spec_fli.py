"""Spec v4: sections 14 (forward-looking adjustments) and 15 (scenarios); D28, D29; settings; screens; glossary becomes 16."""
p = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\specs\MAIIC_EIR_Engine_Specification_v4_2026-10-07.md"
s = open(p, encoding="utf-8").read()
sec = open(r"C:\Users\wadza\AppData\Local\Temp\claude\c--xampp-htdocs-Stress-Testing-App\ee984144-7b28-4df1-9a6e-560f112869e9\scratchpad\spec_fli_sections.md", encoding="utf-8").read()
def rep(old, new):
    global s
    assert old in s, old[:70]
    s = s.replace(old, new)
rep("## 14. Glossary of the new terms", sec.rstrip("\n") + "\n\n## 16. Glossary of the new terms")
rep("| D23 | **The three MAIIC extract scripts are retired.**",
    "| D28 | **The forward-looking adjustment gains a correlation finder, a repaired regression, a manual overlay route and lineage on the loan** (section 14); the arithmetic that applies the adjustment to PDs and feeds the ECL is unchanged. | Edward, 7 Oct 2026 | Which series explains MAIIC's losses is found, tested and approved by two people; judgement has a governed road; every post-FLI PD says where it came from |\n"
    "| D29 | **The scenario set is a governed object per period and the ECL is weighted across scenarios** (section 15): three or more scenarios with narratives, weights, source vintage, calibration to Malawi's own history, shocks on the base path, two approvals, a lock, back-tests and sensitivity; the weighting moves from the macro path to the loss. | Edward, 7 Oct 2026 | IFRS 9 B5.5.42 asks for the probability-weighted loss over a range of outcomes; IFRS 7.35G asks for the disclosure; the auditors ask for the governance |\n"
    "| D23 | **The three MAIIC extract scripts are retired.**")
rep("| How the loan book is built (`loan_book_build_method`) | Method B, derived from the ledger |",
    "| How the forward-looking adjustment is produced (`fli_adjustment_route`) | Regression | Recommendation (section 14.6); the manual overlay and the combined route are the alternatives | new |\n"
    "| Expected-sign test on a regression pair (`fli_expected_sign_test`) | Required | Recommendation (section 14.4) | new |\n"
    "| R-squared cut-off for an approvable model (`fli_r2_cutoff`) | 30 percent | Recommendation (section 14.4); MAIIC may tighten | new |\n"
    "| How scenarios are weighted (`scenario_weighting_method`) | Weight the ECL across scenarios | Recommendation (section 15.5); the macro-path method kept for reconciliation | new |\n"
    "| Minimum scenarios in a set (`scenario_minimum_count`) | 3 | Recommendation (section 15.6) | new |\n"
    "| Floor on the base weight and ceiling on any weight (`scenario_weight_bounds`) | Base at least 40 percent; none above 60 percent | Recommendation (section 15.6) | new |\n"
    "| Calibration note required on a downside (`scenario_calibration_note`) | Required | Recommendation (section 15.6) | new |\n"
    "| An overlay needs an approved scenario set (`overlay_requires_approved_set`) | Required | Recommendation (section 15.7) | new |\n"
    "| How the loan book is built (`loan_book_build_method`) | Method B, derived from the ledger |")
rep("| | Forward-Looking Model (sub-group): Scenario Profiles, Weighted Forecast, Credit Loss Data, Adjusted Forecast, Regression Analysis | `scenarios.profiles`, `macro-forecast-weighted.index`, `credit-loss-data.index`, `forecasting.manual`, `regression.index` | | IFRS 9 Model Setup (Macro Elements moves to Data Foundation as Macro Statistics) |",
    "| | Forward-Looking Model (sub-group): Correlation Finder (new, 14.4), Regression Analysis (repaired, 14.5), FLI Adjustments (new, 14.6), Weighted Forecast, Credit Loss Data, Adjusted Forecast | `fli-correlation.index`, `regression.index`, `fli-adjustments.index`, `macro-forecast-weighted.index`, `credit-loss-data.index`, `forecasting.manual` | | IFRS 9 Model Setup (Macro Elements moves to Data Foundation as Macro Statistics; Scenario Profiles becomes Scenario Sets under Governance) |")
rep("| | Financial Periods | `accounting.financial_periods.index` | | Administration |",
    "| | Scenario Sets (propose, approve, lock, versions, back-test, sensitivity; section 15) | `scenario-sets.index` (replaces `scenarios.profiles`) | | IFRS 9 Model Setup |\n| | Financial Periods | `accounting.financial_periods.index` | | Administration |")
rep("- **Landing zone**:",
    "- **Pre-FLI and post-FLI PD**: the probability of default measured from history, and the same probability after the forward-looking adjustment; the ECL is calculated on the second.\n"
    "- **Correlation finder**: the sweep of every macro series against every credit-loss proxy, over lags and transforms, that ranks which relationships are worth a model.\n"
    "- **Overlay**: a forward-looking adjustment applied by judgement rather than by a model, with its reason, owner, expiry and two approvals, shown as its own line.\n"
    "- **Scenario set**: the governed collection of economic scenarios for a reporting period, with their weights, paths, narratives, source vintage and approvals.\n"
    "- **Shock**: the recorded transformation that turns the base path into another scenario's path: a percentage change, an absolute change, a replacement or a multiplier, by series and year.\n"
    "- **Landing zone**:")
rep("the macro statistics, and what is left to build", "the macro statistics, the forward-looking adjustments and scenarios, and what is left to build")
open(p, "w", encoding="utf-8").write(s); print("sections 14 and 15 added; D28, D29; eight settings; screens; glossary 16")
