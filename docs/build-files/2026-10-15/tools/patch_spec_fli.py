P = 'C:/xampp/htdocs/MAICC-IFRS9/docs/MAIIC_FLI_Module_Specification_v1_2026-10-13.md'
s = open(P, encoding='utf-8').read()

def rep(a, b):
    global s
    assert a in s, a[:80]
    s = s.replace(a, b, 1)

rep("status: Draft for review. Builds on specification version 4,",
    "status: Draft for review, updated 13 October 2026 evening (decisions D-F13 to D-F16: the earlier screens kept and improved, the macro statistics screens, the reports that read the month record, the month-end job). Builds on specification version 4,")

# D-F13 to D-F16 after D-F12
rep("## 4. The screens", """### D-F13. Keep the earlier screens and improve them, not replace them (agreed 13 October 2026)

The proposal of section 4 drew a new set of tabs. Edward's direction on 13 October was not to remove what MAIIC already uses: every earlier forward-looking screen (Macro Statistics, Credit Loss Data, Correlation Finder, Regression, Adjusted Forecast, Weighted Forecast, Scenario Profiles, Scenario Sets, Economic Scenarios, Manual Overlays, External Calculations, Calculation History) comes back to its place, out of the old Legacy section, and the new pieces (Overview, Impact Analysis, Model Versions) join them. Each screen became a working step of the governed chain rather than a separate tool, and a user can start and finish the month from the screens alone. Section 4 is updated to the menu as built.

### D-F14. The macro statistics screens (agreed 13 October 2026)

- Variables, observations, credit loss data and assumptions are created, edited and deleted on screen, with field errors and a confirmation that names what is deleted.
- Source codes read **WB: code** and, below it, **IMF: code**, not the raw JSON.
- **One approved assumption set per period for MAIIC.** The Scenario Assumptions tab shows the set in force at a glance (the weights row and one tab per scenario), and a draft is created only from it; two approved sets for one period are refused.
- Actions sit on the right: Add shock and Save at the right of their panel; Import is a button at the top right that opens a modal with the instructions, the layout and a sample file, and previews before it commits.
- The scenario-set selector is wide enough to read a set's name and version.

### D-F15. The reports read the month record (agreed 13 October 2026)

The forward-looking report and the scenario-weighted ECL report read what the ECL actually used for the chosen month: the macro data the bridge held for it, the approved scenario set with its weights and shocks, and the month record (`fli_run_snapshots`): ECL per scenario, weighted against booked, and the sign-off status. A month with no record says so. The ECL Projection Audit lists contract-level discounted runs only; the portfolio PD projection of D-F4 is shown on FLI Month-End.

### D-F16. One month-end job (agreed 13 October 2026)

Maintenance is reduced to seven jobs (specification version 4, decision D37). The month-end job runs, in order: link clients to the loan book, stage, PD by segment, the forward-looking month-end of D-F12 (`fli:month-end --all`), the ledger cash, the EIR revenue run, and the Mega Farm ECL where there are such loans. The Overview's own Run buttons start the same steps one at a time in the background.

## 4. The screens""")

rep("""Every figure on the Overview and Impact Analysis tabs comes from the same stored results as the ECL and the dashboard build-up panel, so a headline can never disagree with the detail.""",
"""Every figure on the Overview and Impact Analysis tabs comes from the same stored results as the ECL and the dashboard build-up panel, so a headline can never disagree with the detail.

**As built (13 October 2026, D-F13).** The menu entry **Forward-Looking Information** has four groups, each a row of tabs:

| Group | Tabs |
|---|---|
| FLI Month-End | Overview, Impact Analysis |
| Macro Statistics | Dashboard, Variables, Data Entry, Scenario Assumptions, Import / Export |
| Forward-Looking Model | Credit Loss Data, Correlation Finder, Regression, Model Versions, Adjusted Forecast, Weighted Forecast |
| Scenarios and Overlays | Scenario Profiles, Scenario Sets, Economic Scenarios, Manual Overlays, External Calculations, Calculation History |

Economic Indicators of the proposal is Macro Statistics; Scenario Workbench is Scenario Sets; Model Calibration is Correlation Finder, Regression and Model Versions; Overlays is Manual Overlays; Approval and History is Model Versions and Calculation History.""")

rep("""| 4. Screens | Section 4: the Forward-Looking Information menu, Overview, Impact Analysis, Approval and History | Every headline figure equals the stored results; one tab row; the Legacy FLI tools stay under Administration |""",
"""| 4. Screens | Section 4: the Forward-Looking Information menu, Overview, Impact Analysis, Approval and History | Every headline figure equals the stored results; one tab row per group; the earlier screens kept in their places (D-F13) |""")

rep("""Each carries the reporting date and the run identifier, so it can be reproduced from the run snapshot.""",
"""Each carries the reporting date and the run identifier, so it can be reproduced from the run snapshot.

Built so far (D-F15): the forward-looking report (macro data held for the month, the scenario set, its weights and shocks) and the scenario-weighted ECL report (ECL per scenario, weighted against booked, sign-off status), both in PDF, Excel and CSV, read from the month record. The calibration workbook and the FLI line in the IFRS 9 note are still to build.""")

rep("""| D-F12 month-end gate |""", """| D-F13 earlier screens kept | Every earlier screen back in its place in the Forward-Looking Information menu, out of the Legacy section | The navigation test; a month started and finished from the screens on the demonstration database |
| D-F14 macro statistics screens | Variables, observations, credit loss data and assumptions with create, edit and delete; WB: and IMF: codes; one approved assumption set; actions on the right; import as a modal with a preview | Clicked through on the demonstration database; tests |
| D-F15 reports | Forward-looking and scenario-weighted ECL reports from `fli_run_snapshots` and the approved set; ECL Projection Audit limited to contract-level runs | August 2026: Base 11,052,982,549.26 at 50 percent, Upside 10,709,643,059.93 at 15, Downside 11,828,244,520.96 at 25, Severe at 10; weighted 11,314,579,806.37 against booked 11,314,579,806.97 |
| D-F16 month-end job | `maiic:task month-end` and the Overview's background steps | Run end to end from the console |
| D-F12 month-end gate |""")

open(P, 'w', encoding='utf-8').write(s)
print('ok')
