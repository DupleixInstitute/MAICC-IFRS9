"""IFRS 7 and IAS 1 - presentation and disclosure (spec v4 section 12.2, workbook 3).

Each row is mapped to the disclosure report that produces it. Statuses checked 9 October 2026
against the report catalogue (ifrs9:smoke-reports renders every report) and the build.
"""
from tools.compliance.audit_workbook import DONE, PART, OUT_, NA, EVID  # noqa: F401

META = {
    "file_stem": "IFRS7_IAS1_Disclosure_Compliance_Audit",
    "short": "IFRS 7 and IAS 1 - presentation and disclosure",
    "title": "IAS 1 Presentation of Financial Statements (1.82(a)) and IFRS 7 Financial Instruments: Disclosures (7.20, 7.35A to 7.35N) - the lines and disclosures the system produces",
    "basis": "Prepared 9 October 2026 from the report catalogue (Report Hub, thirty reports, every one rendered by ifrs9:smoke-reports on the clean install), the EIR as at a date, the scenario sets and the stress runs.",
    "source": "IAS 1.82(a) (interest revenue calculated using the effective interest method presented separately); IFRS 7.20 (income, expense, gains and losses), 7.35A to 7.35N (credit risk: practices, quantitative and qualitative information, the allowance reconciliation 7.35H, collateral 7.35K, write-off 7.35L, sensitivity 7.35G). Spec v4 sections 6.11, 12, 15.7.",
    "reviewer": "Deloitte",
}
HUB = "Report Hub, IFRS 9 Reports: /ifrs9-reports"

ROWS = [
 ("IAS 1 PRESENTATION", "IAS 1.82(a)", "Interest revenue at the effective interest method as its own line", "The statement of profit or loss presents interest revenue calculated using the effective interest method separately.", DONE,
  "The EIR as at a date gives the EIR interest, the contractual interest posted and the difference for the year to any date, by product, by GL and in total, with the CSV download; the GL reconciliation ties the contractual interest to the ledger.", "The revenue shift is the difference column.", "Compliant: the line is produced; the year-end figure becomes a golden number on the first approved run.", "Report Hub, EIR as at a Date: /eir-as-at; GL Reconciliation: /eir-reconciliation", "stage3_interest_basis", "Tests\\Feature\\Eir\\EirAsAtServiceTest::test_the_book_rolls_up_by_product_and_gl"),
 ("IFRS 7 INCOME AND EXPENSE", "7.20(b)", "Total interest revenue and interest expense (effective interest method)", "Disclose total interest revenue calculated using the effective interest method for financial assets at amortised cost.", DONE,
  "As IAS 1.82(a): the book view's EIR interest year to date.", "", "Compliant.", "/eir-as-at", "", "Tests\\Feature\\Eir\\EirAsAtServiceTest"),
 ("IFRS 7 INCOME AND EXPENSE", "7.20A", "Gain or loss on derecognition of amortised-cost assets", "Disclose the gain or loss on derecognition and the reasons.", OUT_,
  "The 10 percent test and derecognition are not built (IFRS 9 EIR workbook, 3.3.2).", "", "Outstanding with the restructure history.", "", "", ""),
 ("IFRS 7 CREDIT RISK", "7.35F", "Credit risk management practices", "Explain how the entity determines a significant increase in credit risk and default, how instruments were grouped, and the write-off policy.", PART,
  "The staging thresholds by facility class with their rebuttal basis, the missed-instalment trigger, the SICR groups and the 48 governed settings are in the system with their rationale; the write-off policy is MAIIC's to state.", "", "Partially done: the write-off policy is evidence awaited.", "Governance Centre: /eir-governance; Staging & SICR Rules: /stageing-rules", "staging_rebuttal; dpd_basis; stage3_missed_instalments", "Tests\\Feature\\Eir\\StagingThresholdSeederTest"),
 ("IFRS 7 CREDIT RISK", "7.35G", "Inputs, assumptions and estimation techniques; sensitivity", "Explain the basis of inputs and assumptions, how forward-looking information was incorporated, and changes from the previous period; sensitivity of the ECL to the inputs.", DONE,
  "The scenario set stores its sensitivity (the ECL under each scenario, weighted, ten points to the downside and to the upside) and its back-test; the stress runs save the ECL under each scenario; the method cards explain the transmission; the Sensitivity report renders.", "", "Compliant.", "Governance Centre, Scenario Sets: /scenario-sets; Risk & Regulatory, Stress Testing: /stress-testing; Sensitivity: /ifrs9-reports/sensitivity", "scenario_weighting_method; fli_transmission_method", "Tests\\Feature\\Scenario\\ScenarioSetServiceTest::test_approval_needs_a_second_person_and_a_lock_is_final"),
 ("IFRS 7 CREDIT RISK", "7.35H", "Reconciliation of the loss allowance", "Reconcile the opening to the closing loss allowance by class, showing changes from staging transfers, originations, derecognitions and remeasurement.", DONE,
  "The ECL Reconciliation report and the provision comparison render for every period from the staged loan books.", "", "Compliant.", "Report Hub, ECL Reconciliation: /reports/ecl-reconciliation", "", "Tests\\Feature\\Ecl\\TimePhasedEclServiceTest"),
 ("IFRS 7 CREDIT RISK", "7.35I", "Changes in the gross carrying amount that contributed to changes in the allowance", "Explain how significant changes in the gross carrying amount contributed to changes in the loss allowance.", DONE,
  "The loan book of every month from July 2024 holds the gross carrying amount derived from the ledger with its provenance; the Loan Book Reconciliation and the disbursements (vintage) report render.", "", "Compliant.", "Report Hub, Loan Book Reconciliation: /reports/loan-book-reconciliation; Disbursements: /reports/disbursement-report", "loan_book_build_method", "Tests\\Feature\\Ebanker\\LoanBookBuildServiceTest"),
 ("IFRS 7 CREDIT RISK", "7.35K", "Collateral and other credit enhancements", "Disclose the effect of collateral on the amount of the loss allowance and the nature of the collateral held.", EVID,
  "The security register (P2_11) is landed and the collateral register screens exist; 31 active accounts carry no security on the register and the valuations are not held.", "", "Evidence needed from Credit.", "Data Foundation, Collateral Register: /collateral/register", "", ""),
 ("IFRS 7 CREDIT RISK", "7.35L", "Written-off assets still subject to enforcement", "Disclose the contractual amount outstanding on written-off assets still under enforcement.", EVID,
  "No write-off has been posted since the take-on; the policy and any pre-migration write-offs are MAIIC's to supply.", "", "Evidence needed.", "", "", ""),
 ("IFRS 7 CREDIT RISK", "7.35M", "Credit risk exposure by credit-risk rating grade and stage", "Disclose the gross carrying amount by credit risk rating grade, separately for 12-month and lifetime ECL (Stage 1, 2, 3).", DONE,
  "The IFRS 9 disclosure report and the RBM classification report present the book by stage and by grade; the staging writes the stage every period under the governed thresholds.", "", "Compliant.", "Risk & Regulatory, IFRS 9 Disclosure: /ifrs9-reports/fs-disclosure; RBM Classification: /ifrs9-reports/rbm-classification", "dpd_basis", "Tests\\Feature\\Eir\\StagingServiceTest"),
 ("IFRS 7 CREDIT RISK", "7.35N", "Concentrations of credit risk", "Disclose concentrations of credit risk.", DONE,
  "The Concentration report renders by sector, product and borrower.", "", "Compliant.", "Risk & Regulatory, Concentration: /ifrs9-reports/concentration", "", "Tests\\Feature\\Eir\\EirAsAtServiceTest"),
 ("THE PACK", "12.5", "The auditor's pack", "The disclosures and the figures behind them handed to the auditor as one archive with a manifest.", DONE,
  "compliance:audits --pack={period} bundles the workbooks, the EIR as at the period end, the baselines and the ECL by stage with a SHA-256 per file in manifest.json.", "", "Compliant.", "compliance:audits --pack", "", "Tests\\Feature\\Eir\\EirAsAtServiceTest"),
]

FINDINGS = [
 ("F1", "7.35K", "Collateral evidence for 31 active accounts", "The register carries no security against 31 active accounts (the request to Credit of 7 October).", "The collateral disclosure and the LGD rest on an incomplete register.", "Credit supplies the security and valuations; the register is reloaded by the feed.", "Credit", "Open"),
 ("F2", "7.35F, 7.35L", "No write-off policy in the system", "No write-off has been posted in E-Banker since the take-on and the policy is not held.", "The practices disclosure is incomplete.", "Finance supplies the policy; the help centre carries it.", "Finance", "Open"),
]

BASELINES = []
