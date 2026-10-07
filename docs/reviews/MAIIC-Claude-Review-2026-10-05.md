# Codex review of Claude's MAIIC extract analysis

Date: 5 October 2026

## Scope and conclusion

Reviewed the supplied Claude session, the three Oracle extract procedures, the report builder and dictionary SQL pack. Re-ran maiic_check4.py, maiic_exact.py and maiic_tr.py against the delivered Excel extracts. Also inspected the transaction and interest import services in C:\xampp\htdocs\MAICC-IFRS9. This reviews the supplied analysis session, rather than the entire application. No Oracle queries were executed and no database records or application code were changed.

Claude's analysis is useful and its main figures reproduce, but several claims need revision before treating the report as final.

## Findings that reproduce

- Extract A largely reads current loan terms and status for nominal historical snapshots; only the balance lookup is explicitly dated.
- Extract B removes transaction signs and supplies estimated repayment splits. Interest-charge rows must not automatically be treated as cash receipts.
- Extract C contains overlapping runs and both monthly and annual totals, creating double-counting risk.
- The 2025 B-C difference reproduces exactly: MWK 85,166,683.31 across six accounts. The other 101 accounts agree exactly.
- Ebenezer has five entries in both extracts, with different dates: 15 July 2025 in the schedule and 8 August 2025 in the ledger. The corrected finding is supported.
- The report correctly acknowledges that loan-account postings still need reconciliation to income GL accounts.

## Requested corrections and additional checks

1. High: scheduled rows are not the unpaid remainder. CASHFLOW_REGISTER.txt, lines 115-126, selects instalments not fully recovered but outputs their full original principal, interest and instalment amounts. It does not subtract inst_recovered. Partially paid instalments can overstate remaining payments. Revise the wording and require verified recovery allocation before treating these amounts as remaining payments.

2. High: running-balance correctness is not established. Summing available ledger history before filtering dates is sensible, but correctness also requires complete history, a valid opening position, verified posting scope and agreement to independent balances. Replace the unconditional claim that the balance is right.

3. High: absence of interest rows is not automatically a verified zero. Confirm account scope and extraction completeness first. Distinguish no postings found, verified zero and unresolved coverage. Obtain explanations for the ten active accounts.

4. Medium: verify final-day date handling. B and C use inclusive BETWEEN end dates; A uses transaction_date <= TRUNC(p_as_of_date). If source dates contain times, later final-day postings can be excluded. Establish intended date semantics and test boundary cases.

5. Medium: aggregate agreement does not establish adjustment semantics. Five of the six differences match Other/Adjustment totals. Happie Foods has MWK 9,018,700.44 of Other/Adjustment against a difference of MWK 2,018,700.44, leaving MWK 7 million outside that explanation. Retain the appendix qualification and qualify the stronger main-report wording. Obtain raw signed postings and vendor transaction-code definitions.

6. Medium: clarify schema and privilege assumptions. Multiple accessible schemas can contain ACMASTER, while several catalogue outputs omit owner identifiers. Include owner identifiers and establish the intended schema. Verify direct SELECT access; permission to execute a procedure does not itself establish access to its underlying tables.

7. Qualify the cause of repayment-frequency differences. Forty-two accounts differ between snapshots, but this does not establish that the procedure changed between runs. Request procedure versions and run provenance.

8. Define the export contract. CSV alone does not establish unambiguous dates or preserve account numbers when opened in Excel. Specify date/timestamp formats, identifier handling, encoding and numeric conventions. Mixed dates remain an evidence limitation.

## Application implications

- app/Services/Eir/GlInterestImportService.php already skips annual totals and detects duplicate monthly entries. Its description still calls Extract C interest income; qualify that pending validation against income GL accounts and the trial balance.
- app/Services/Eir/ContractTransactionImportService.php stores the estimated principal/interest components delivered in B. Trace downstream consumption and distinguish estimates from verified cash movements before calculation use.
- Additional checks found one B run, no duplicate A facility/snapshot keys, no accounts with multiple subaccounts in A, and no duplicate run-41 C monthly keys at account/subaccount/GL/year/month grain. Reusable checks should nevertheless preserve composite facility keys.

## Evidence locations

- Project documents: C:\Users\wadza\OneDrive\2026\Projects\MAIIC
- Source procedures: 2. Documents from clients\Raw Query Scripts\FACILITY_REGISTER.txt, CASHFLOW_REGISTER.txt and INTEREST_RECON.txt
- Analysis deliverables: 2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC
- Final report source: Query Requests to MAIIC\Build files\maiic_report.py
- Application repository: C:\xampp\htdocs\MAICC-IFRS9

## Suggested response from Claude

Address each comment as accepted, qualified or disputed, with supporting source/data evidence. Update the report and query pack as appropriate. Separate reproduced observations from assumptions and pending vendor confirmations. The reproduced numbers support the diagnosis; they do not yet establish a completed income reconciliation or validated contractual cash-flow schedule.
