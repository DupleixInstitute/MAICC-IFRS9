"""The Reserve Bank of Malawi DFI credit-risk directive (spec v4 section 12.2, workbook 4).

The directive behind the "IFRS 9 vs RBM" report, section by section, with the report line that answers
each. Statuses checked 9 October 2026 against the staging thresholds, the loan books and the reports.
"""
from tools.compliance.audit_workbook import DONE, PART, OUT_, NA, EVID  # noqa: F401

META = {
    "file_stem": "RBM_DFI_Directive_2018_Compliance_Audit",
    "short": "RBM Directive 2018 - credit risk management for DFIs",
    "title": "Financial Services (Credit Risk Management for Development Finance Institutions) Directive 2018, Reserve Bank of Malawi (Gazette 13 July 2018, No. 18A, pages 42 to 47)",
    "basis": "Prepared 9 October 2026 from the staging thresholds seeded per the directive, the staged loan books of July 2024 to August 2026, and the RBM Classification and IFRS 9 vs RBM reports.",
    "source": "docs/regulatory/RBM Financial Services (Credit Risk Management for DFIs) Directive 2018 pp42-47.pdf. Spec v4 section 3.6, decision D31; the O13 rebuttal paper of 8 October 2026.",
    "reviewer": "MAIIC Risk",
}
RBM = "Risk & Regulatory, RBM Classification: /ifrs9-reports/rbm-classification; IFRS 9 vs RBM: /ifrs9-reports/ifrs9-vs-rbm"

ROWS = [
 ("PART I - PRELIMINARY", "2", "Definitions: short-, medium- and long-term facilities", "A short-term facility has a repayment period of not more than 12 months; medium-term more than 12 and up to 60; long-term more than 60.", DONE,
  "The staging thresholds are keyed by facility class and the tenor in months: DEFAULT with min_tenor 0 (short-term, 91 days) and min_tenor 13 (medium and long, 181 days), each row citing the directive's section 2; the loan book carries the tenor from the loan master.", "", "Compliant.", "Governance Centre, Staging & SICR Rules: /stageing-rules", "dpd_basis", "Tests\\Feature\\Eir\\StagingThresholdSeederTest"),
 ("PART III - CLASSIFICATION", "9", "Classification of credit facilities", "Classify every facility as pass, special mention, substandard, doubtful or loss by the days in arrears and the qualitative factors.", DONE,
  "The RBM Classification report classifies each loan from the days past due the build counts from the oldest overdue instalment, with the bucket ageing beside it; the IFRS 9 stage is mapped to the RBM class in the IFRS 9 vs RBM report.", "", "Compliant.", RBM, "dpd_basis", "Tests\\Feature\\Eir\\StagingServiceTest"),
 ("PART III - CLASSIFICATION", "10", "Non-performing: substandard from 91 days (short-term) or 181 days (medium and long term)", "A facility is non-performing when principal or interest is due and unpaid for 90 days or more on a short-term facility, or 180 days or more on a medium- or long-term facility.", DONE,
  "Stage 3 from 91 days for a facility of 12 months or less and from 181 days otherwise; the November 2025 model's uniform 181 days and the accounting policy's 90 days are compared in spec section 3.6 and the thresholds carry the comparison as their rebuttal basis.", "The LONG_TERM proposal (91 days for every class) is seeded future-dated and inactive pending CFO sign-off.", "Compliant.", RBM, "dpd_basis; staging_rebuttal", "Tests\\Feature\\Eir\\StagingServiceTest::test_the_directive_thresholds_by_tenor_class_and_the_bucket_fallback"),
 ("PART III - CLASSIFICATION", "11", "Doubtful and loss", "Doubtful from 181 (short-term) or 361 days (medium and long term); loss from 361 or 721 days, or where recovery is not expected.", PART,
  "The RBM Classification report applies the day bands; the loan book's buckets end at 271 to 360 days and the build counts days from the overdue date without a ceiling, so the doubtful and loss bands are classified from the day count; the qualitative 'recovery not expected' flag is the write-off policy MAIIC has not stated.", "", "Partially done: the day bands are applied; the qualitative loss criterion awaits the policy.", RBM, "", "Tests\\Feature\\Eir\\StagingServiceTest"),
 ("PART IV - PROVISIONING", "12", "Minimum provisions by class", "Provide at least 0 percent on pass, 5 on special mention, 20 on substandard, 50 on doubtful and 100 on loss, net of eligible security.", DONE,
  "The IFRS 9 vs RBM report applies the five percentages to the classified book and sets the regulatory provision beside the IFRS 9 allowance; the higher of the two is the figure the regulatory return carries.", "", "Compliant in the report; the eligible-security netting rests on the collateral register (IFRS 7 workbook F1).", RBM, "", "Tests\\Feature\\Ecl\\TimePhasedEclServiceTest"),
 ("PART IV - PROVISIONING", "13", "Non-accrual of interest", "Interest on a non-performing facility is not recognised as income; it is held in suspense until received.", DONE,
  "Stage 3 interest in the EIR roll-forward is on the net basis; the contractual interest E-Banker continues to post on non-performing accounts is shown beside it in the GL reconciliation, so the suspense the directive requires is the difference the system reports.", "The year-end re-rating of December 2025 (28 postings) is explained in spec section 3.4.", "Compliant; the suspense is reported, and the posting practice is Finance's to align.", "Report Hub, GL Reconciliation: /eir-reconciliation", "stage3_interest_basis", "Tests\\Feature\\Eir\\EirRevenueServiceTest"),
 ("PART V - RESTRUCTURING", "15", "Restructured facilities", "A restructured facility keeps its classification until the borrower has performed under the new terms for a stated period.", EVID,
  "The restructured-loan register and E-Banker's Reschedule Report are awaited from the vendor; the loan's status history (P3_13) is landed.", "", "Evidence needed from the vendor.", "Data Foundation, E-Banker Feed: /eir-feed", "rate_change_classification", ""),
 ("PART VI - REPORTING", "17", "Returns to the Reserve Bank", "Submit the classification and provisioning return in the prescribed form and frequency.", PART,
  "The RBM Classification and IFRS 9 vs RBM reports render and export; the prescribed return form is not reproduced line for line.", "", "Partially done: the figures exist; the form is to be mapped.", RBM, "", "Tests\\Feature\\Eir\\StagingServiceTest"),
]

FINDINGS = [
 ("F1", "15", "The restructured-loan register is not in the system", "The Reschedule Report and the register are awaited from the vendor; restructured facilities cannot keep their classification as section 15 requires.", "Classification of restructured facilities may be too favourable.", "Barry obtains the Reschedule Report; the register is loaded by the feed and the classification holds it.", "Barry Makumba", "Open"),
 ("F2", "17", "The prescribed return form is not mapped", "The reports carry the figures; the Reserve Bank's form is not reproduced line for line.", "The return is prepared outside the system.", "MAIIC Risk supplies the current form; the mapping is added to the IFRS 9 vs RBM report.", "MAIIC Risk / Dupleix", "Open"),
]

BASELINES = []
