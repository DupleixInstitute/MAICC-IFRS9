"""Contract Schedule 1 - the deliverables of the MAIIC-Dupleix implementation agreement (spec v4 section 12.2, workbook 5).

Each deliverable with the screen, document or test that discharges it. The component list follows the
solution components the navigation was built on (data onboarding, collateral, EIR, IFRS 9 model set-up,
the ECL engine, reports, the audit trail, the dashboard, administration) and the documentation
deliverables (user, administrator, technical and installation manuals). The clause numbers are to be
confirmed against the signed schedule by Dr Thom at acceptance (finding F1); until then the references
are the component names.
"""
from tools.compliance.audit_workbook import DONE, PART, OUT_, NA, EVID  # noqa: F401

META = {
    "file_stem": "Contract_Schedule1_Acceptance_Audit",
    "short": "Contract Schedule 1 - deliverables and acceptance",
    "title": "The MAIIC-Dupleix implementation agreement, Schedule 1: solution components and deliverables, each with the screen, document or test that discharges it",
    "basis": "Prepared 9 October 2026 from the build of specification v4 and the system as it stands on the clean install. The clause numbers of the signed schedule are to be confirmed at acceptance (P9).",
    "source": "The implementation agreement between MAIIC and Dupleix Institute, Schedule 1 (solution components; deliverables 5, 6 and 7 are the manuals). Spec v4 section 8 (the build plan) and section 12.",
    "reviewer": "Dr Thomson Kumwenda (CFO), at acceptance",
}

ROWS = [
 ("SOLUTION COMPONENTS", "S1.1", "Data onboarding", "Load the client's loan book, customer and transaction data into the system with validation.", DONE,
  "The E-Banker landing zone with its gates, the loan book built from the ledger by three methods, the take-on schedules landed with their cells, the trial balances; the bootstrap loads it all from the committed folder and proves it against the golden numbers.", "", "Compliant.", "Data Foundation, E-Banker Feed: /eir-feed; Take-on Schedules: /eir-takeon", "ebanker_feed_route; loan_book_build_method", "Tests\\Feature\\Ebanker\\PackLandingServiceTest; Tests\\Feature\\Ebanker\\LoanBookBuildServiceTest"),
 ("SOLUTION COMPONENTS", "S1.2", "Collateral management", "Register collateral, its types and its allocation to facilities.", PART,
  "The collateral register, types and allocation screens exist; the security register from E-Banker (P2_11) is landed; 31 active accounts have no security recorded.", "", "Partially done: the data is Credit's to complete.", "Data Foundation, Collateral Register: /collateral/register", "", "Tests\\Feature\\Ebanker\\PackLandingServiceTest::test_a_composite_key_lands_one_row_per_pair"),
 ("SOLUTION COMPONENTS", "S1.3", "The effective interest rate and revenue recognition", "Solve the EIR per facility from its contractual flows and fees, roll the amortised cost forward monthly, and reconcile to the general ledger.", DONE,
  "The EIR engine, the roll-forward with cash from the ledger, the GL reconciliation, the EIR as at any date; 31 EIRs solved on the clean install, 12 locked, 19 awaiting the fee rulebook's approval.", "", "Compliant in mechanics; see the IFRS 9 EIR workbook's findings.", "Financial Modelling, EIR Calculations: /eir-calculations; Report Hub, EIR as at a Date: /eir-as-at", "(section 4.2)", "Tests\\Feature\\Eir\\EirCalculationWorkflowTest; Tests\\Feature\\Eir\\EirAsAtServiceTest"),
 ("SOLUTION COMPONENTS", "S1.4", "IFRS 9 model set-up", "Staging rules, PD, LGD and the forward-looking model, configurable and governed.", DONE,
  "The staging thresholds per the directive, the PD transition matrix and the LGD cohort workout as services, the forward-looking chain (correlation, regression, guardrail), the transmission methods with their cards, the scenario sets, all governed in the Governance Centre with 48 settings.", "", "Compliant.", "Governance Centre: /eir-governance; Financial Modelling: /expected-credit-loss", "(section 4.2)", "Tests\\Feature\\Eir\\GovernanceServiceTest; Tests\\Feature\\Pd\\PdAndLgdEngineTest; Tests\\Feature\\FLI\\FliRouteServiceTest"),
 ("SOLUTION COMPONENTS", "S1.5", "The ECL engine", "Compute the expected credit loss per facility and in total, discounted, with the forward-looking adjustment and scenario weighting.", DONE,
  "The time-phased ECL discounted at the EIR, on the post-FLI PD, with the sensitivity across scenarios and the stress runs; on the clean install 8.94bn at August 2026 under the bootstrap label.", "", "Compliant; the figures await MAIIC's reviews (fee rulebook, matrix and LGD key-locks, the fit and the set).", "Financial Modelling, ECL Calculation: /expected-credit-loss", "scenario_weighting_method", "Tests\\Feature\\Ecl\\TimePhasedEclServiceTest; Tests\\Feature\\Scenario\\ScenarioSetServiceTest"),
 ("SOLUTION COMPONENTS", "S1.6", "Reports", "The IFRS 9 disclosure, regulatory and management reports, exportable.", DONE,
  "Thirty reports in the Report Hub, every one rendered by ifrs9:smoke-reports on the clean install; the EIR as at a date with its CSV; the auditor's pack.", "", "Compliant.", "Report Hub: /ifrs9-reports", "", "Tests\\Feature\\Eir\\EirAsAtServiceTest"),
 ("SOLUTION COMPONENTS", "S1.7", "The audit trail", "Every change recorded with who, when, the old and the new value.", DONE,
  "The audit log records every load, build, approval, staging, engine run, setting change and compliance sign-off with the old and new values; the Governance Centre's maker-checker is enforced in code.", "", "Compliant.", "Governance Centre, Audit Trail: /audit-trail", "", "Tests\\Feature\\Eir\\EirGovernanceControllerTest"),
 ("SOLUTION COMPONENTS", "S1.8", "The dashboard and the workspace", "A dashboard of the book and the allowance; a workspace of the period's tasks.", DONE,
  "The dashboard and the workspace exist under the suite layout with light and dark mode.", "", "Compliant.", "Dashboard: /dashboard; Workspace: /workspace", "", "Tests\\Feature\\NavigationTest"),
 ("SOLUTION COMPONENTS", "S1.9", "Administration", "Users, roles and permissions, settings, support.", DONE,
  "User management, roles and permissions, settings and support tickets; the navigation is filtered by permission so no user sees a link that answers 403.", "", "Compliant.", "Administration: /users", "", "Tests\\Feature\\NavigationTest::test_every_leaf_names_a_registered_route"),
 ("DOCUMENTATION", "D5", "User manual", "A user manual for the finance officer.", PART,
  "The help centre carries the user manual with per-page help and a PDF; the navigation chapter and screenshots are to be redone for the suite layout.", "", "Partially done.", "System Documentation, User Manual: /help", "", "Tests\\Feature\\NavigationTest"),
 ("DOCUMENTATION", "D6", "Administrator manual", "An administrator manual.", PART,
  "The administrator manual exists in the help centre; the passages naming the old menu groups are to be re-worded.", "", "Partially done.", "System Documentation, Administrator Manual: /help/admin", "", "Tests\\Feature\\NavigationTest"),
 ("DOCUMENTATION", "D7", "Technical manual and installation guide", "A technical manual and an installation guide.", DONE,
  "Both are repository Markdown under docs/manuals, rendered in the system; the installation guide's section 9.8 gives the bootstrap and the monthly feed as the procedure.", "", "Compliant.", "System Documentation, Technical Manual: /docs/technical; Installation Guide: /docs/installation", "", "Tests\\Feature\\NavigationTest"),
 ("ACCEPTANCE", "A1", "The specification's acceptance tests", "The baselines of specification section 9 pass on the production copy.", PART,
  "Nine golden checks pass on the clean install (the Baselines sheet of every workbook); the ECL at the year-ends and the revenue shift become golden numbers on the first run Dr Thom approves.", "", "Partially done: the year-end golden numbers await MAIIC's decisions.", "eir:bootstrap --verify; eir:baselines", "", "Tests\\Feature\\Ebanker\\LoanBookBuildServiceTest"),
]

FINDINGS = [
 ("F1", "S1.1 to A1", "The clause numbers of the signed schedule are not confirmed", "The rows follow the solution components the navigation was built on; the signed Schedule 1's numbering is not in the repository.", "The workbook cannot be cited clause by clause at acceptance.", "Dr Thom supplies the signed schedule; the references are replaced by its clause numbers.", "Dr Thom", "Open"),
 ("F2", "D5, D6", "The manuals name the old menu groups", "Fifteen help-centre passages and the user manual's navigation chapter still refer to the groups the suite layout replaced.", "A reader is sent to a group that no longer exists.", "Re-word the passages and retake the screenshots (spec 11.6).", "Dupleix", "Open"),
]

BASELINES = []
