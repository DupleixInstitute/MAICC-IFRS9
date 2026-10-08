"""Spec v4: section 13 macro statistics ingestion; D27; the precedence setting; the screen in 11.3; glossary becomes 14."""
p = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\specs\MAIIC_EIR_Engine_Specification_v4_2026-10-07.md"
s = open(p, encoding="utf-8").read()
sec = open(r"C:\Users\wadza\AppData\Local\Temp\claude\c--xampp-htdocs-Stress-Testing-App\ee984144-7b28-4df1-9a6e-560f112869e9\scratchpad\spec_macro_section.md", encoding="utf-8").read()
def rep(old, new):
    global s
    assert old in s, old[:70]
    s = s.replace(old, new)
rep("## 13. Glossary of the new terms", sec.rstrip("\n") + "\n\n## 14. Glossary of the new terms")
rep("| D23 | **The three MAIIC extract scripts are retired.**",
    "| D27 | **Macro statistics are ingested from the World Bank and the IMF the way the suite does it** (section 13): indicator codes on the series, a fetcher and a parser, preview before commit, a batch of provenance on every observation, a command for the bootstrap and the scheduler, the Reserve Bank series by file, and a governed rule for which source wins. The existing tables and the six forward-looking screens are kept. | Edward, 7 Oct 2026 | Forward-looking information with its source, address and time against every figure, which is what B5.5.49 to B5.5.54 and Deloitte ask for |\n| D23 | **The three MAIIC extract scripts are retired.**")
rep("| How the loan book is built (`loan_book_build_method`) | Method B, derived from the ledger |",
    "| Which macro source wins where two overlap (`macro_source_precedence`) | World Bank for actuals, IMF WEO for forecasts, RBM file for rates; manual overrides only with a reason | Recommendation (section 13.5) | new |\n| How the loan book is built (`loan_book_build_method`) | Method B, derived from the ledger |")
rep("| | Reference Rates | `eir-reference-rates.index` | eir.view | EIR & Revenue Recognition |",
    "| | Reference Rates | `eir-reference-rates.index` | eir.view | EIR & Revenue Recognition |\n| | Macro Statistics (variables, data, scenario assumptions, import from the World Bank, the IMF and the RBM) | `macro-statistics.index` (rebuilt, section 13) | macro.view; import needs macro.manage | IFRS 9 Model Setup (Macro Elements) |")
rep("| | Forward-Looking Model (sub-group): Macro Elements, Scenario Profiles, Weighted Forecast, Credit Loss Data, Adjusted Forecast, Regression Analysis | `macro-statistics.index`, `scenarios.profiles`, `macro-forecast-weighted.index`, `credit-loss-data.index`, `forecasting.manual`, `regression.index` | | IFRS 9 Model Setup |",
    "| | Forward-Looking Model (sub-group): Scenario Profiles, Weighted Forecast, Credit Loss Data, Adjusted Forecast, Regression Analysis | `scenarios.profiles`, `macro-forecast-weighted.index`, `credit-loss-data.index`, `forecasting.manual`, `regression.index` | | IFRS 9 Model Setup (Macro Elements moves to Data Foundation as Macro Statistics) |")
rep("- **Landing zone**:", "- **Batch (macro import)**: the record of one import of macro statistics: source, address, fetched-at, who committed it, rows; every observation points to its batch.\n- **WEO**: the IMF World Economic Outlook database, the source of the forecast years.\n- **Landing zone**:")
rep("the user interface, the audit workbooks, and what is left to build", "the user interface, the audit workbooks, the macro statistics, and what is left to build")
rep("| `trial-balances/afs-mapping-2023-to-2026-08/` |", "| `trial-balances/afs-mapping-2023-to-2026-08/` |") if False else None
open(p, "w", encoding="utf-8").write(s); print("section 13 added; D27; setting; screens; glossary 14")
