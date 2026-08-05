# The MAIIC EIR & Revenue Recognition Engine, Explained

## A plain-language companion to the consolidated technical specification

**Version:** 2.0  
**Date:** 5 August 2026  
**Status:** Aligned to the code consolidated on `master`

This guide explains what the EIR and revenue-recognition work is meant to do, what has already been built, and what remains. The authoritative implementation detail is in `MAIIC_EIR_Revenue_Recognition_Engine_Spec.md`; this document is the accessible companion for project, finance, audit, and technology stakeholders.

## 1. The business problem

A loan's contractual interest rate is not always the rate IFRS 9 uses to recognise interest income. Fees, transaction costs, premiums, discounts, staged disbursements, and the timing of repayments can change the economic yield. The effective interest rate (EIR) is the rate that discounts expected contractual cash flows back to the instrument's initial carrying amount.

The engine must therefore answer three related questions:

1. What is the loan's effective yield at origination?
2. How much interest income should be recognised in each reporting period?
3. When expected credit losses are calculated, which rate should discount the expected shortfalls?

The same EIR links all three questions, but the accounting treatment changes when a facility becomes credit-impaired: interest is generally calculated on the net carrying amount for Stage 3 assets rather than the gross amount.

## 2. The “three doors”

The consolidated design describes EIR as appearing through three doors:

- **Door 1 — origination:** determine and lock the original EIR from the contract, qualifying fees, and expected cash-flow schedule.
- **Door 2 — revenue recognition:** apply that locked rate to the appropriate opening amortised-cost balance to produce the period's IFRS 9 interest income.
- **Door 3 — impairment:** use the original EIR to discount expected cash shortfalls in the ECL engine.

These are not three different rates. They are three controlled uses of the same contractual economic yield.

## 3. Two supported data paths

The project contains two designs that have now been reconciled rather than allowed to compete.

### Path A — contract and assessment workbooks

This is the implemented primary path. Offer-letter terms, schedules, and fee assessments populate the `contract_eir` family of tables. The application already contains intake screens, schedule and fee imports, mapping support, fee classification, and the core solver foundation.

### Path B — VGCBS Extracts A, B, and C

MAIIC's production-oriented extracts remain a required operating path:

- **Extract A** supplies facility and origination details.
- **Extract B** supplies scheduled and actual cash-flow transactions.
- **Extract C** supplies ledger interest postings for reconciliation.

Extract B routing has been started. Full Extract A ingestion and Extract C reconciliation remain to be completed. Both paths must converge on the same canonical contract, cash-flow, EIR, and amortisation records so that there is one calculation engine and one audit trail.

## 4. What the engine calculates

For each facility, the solver builds dated cash flows using a consistent sign convention: amounts advanced to the customer and amounts received from the customer must be represented consistently, including fees that are integral to the yield. It then solves for the rate that makes the discounted value of those cash flows equal to the initial carrying amount.

The locked result drives an amortisation schedule containing, at minimum:

- opening gross or net carrying amount;
- cash received or paid in the period;
- EIR interest income;
- fee or discount amortisation embedded in that income;
- closing carrying amount; and
- the source data and calculation version needed to reproduce the result.

The solver must fail visibly when cash flows cannot produce a meaningful rate. It must not silently substitute the contractual rate.

## 5. Revenue recognition and Stage 3

For performing assets, EIR interest is normally applied to the gross carrying amount. For credit-impaired Stage 3 assets, the engine must apply the relevant IFRS 9 treatment to the net carrying amount after loss allowance. Stage information should come from the existing ECL engine; the revenue module must not create a competing staging calculation.

This dependency is why the EIR and ECL work belong in the same MAIIC platform and why `master` is now the single working line.

## 6. Reconciliation and audit output

Calculation alone is not the deliverable. The controlled monthly process must compare calculated EIR income with interest posted in the general ledger, by facility and period. Differences must roll up into:

- a detailed exception schedule;
- a misstatement summary;
- proposed debit/credit adjusting journals;
- evidence of source files, approvals, overrides, and reruns; and
- the agreed yearly and monthly audit-pack export.

The existing ECL reconciliation service provides a useful application pattern, but EIR-to-GL reconciliation and the Deloitte-style two-sheet export are still outstanding.

## 7. What is already built

The consolidated `master` branch contains the complete `feature/ifrs9-maiic-suite` history plus the EIR branch work. At the time of this update, the EIR implementation includes:

- the seven-table contract-centred EIR schema;
- generator and mapped-reader foundations;
- intake UI and schedule/fee import services;
- staging configuration and Extract B routing;
- fee-classification workflow;
- the pure `CalculateEirService` solver foundation and readiness gate; and
- automated golden-number coverage for the solver foundation.

This is meaningful implementation progress, but it is not yet the full production revenue-recognition cycle.

## 8. What remains

The principal remaining work is:

1. complete solver orchestration, batch processing, locking/approval UI, and representative fixtures;
2. rewire ECL discounting so Door 3 consistently uses the governed original EIR;
3. generate and persist the full revenue amortisation schedule;
4. complete Extract A and Extract C ingestion and validation;
5. implement EIR-versus-GL reconciliation and journal proposals;
6. produce the agreed audit workbook and disclosure outputs;
7. connect the outputs to the Reports Hub and routine month-end command; and
8. add actuals/CCF handling and the remaining operational controls.

Phase 0 accounting and data decisions in the consolidated specification must be signed off before production results are treated as authoritative.

## 9. The monthly operating cycle

A controlled month-end run should follow this sequence:

1. load or refresh facility, schedule, fee, transaction, and GL data;
2. validate mappings, completeness, duplicates, signs, dates, and opening balances;
3. calculate or retrieve the governed locked EIR;
4. generate the period's amortisation and interest-recognition result;
5. apply Stage 3 gross/net rules using the ECL stage result;
6. reconcile calculated income to the GL;
7. investigate and approve exceptions or controlled overrides;
8. publish reports, journal proposals, disclosures, and the audit pack; and
9. retain an immutable record of inputs, versions, users, approvals, and outputs.

Direct E-Banker/VGCBS integration is a sensible later efficiency improvement, but the extract-driven operating model can deliver the first controlled production cycle without waiting for that integration.

## 10. Governance points that must stay visible

- The original EIR should be locked and version-controlled; an uncontrolled recalculation changes accounting outcomes.
- Fee classification needs maker/checker evidence because it determines which cash flows enter the yield.
- Real MAIIC and FinES customer data must remain confidential and must not be reused in training or demonstrations without approval.
- Every calculated figure must be traceable to source data, configuration, code/calculation version, and approval history.
- Open accounting choices, tolerances, day-count rules, modification treatment, and GL mapping decisions belong in the specification's open-items register until formally resolved.

## 11. Source of truth

The project now uses the GitHub `master` branch as the working integration point. The consolidated technical specification and this explained companion are maintained in the repository under `docs/` and mirrored to the MAIIC OneDrive `3. Project Execution/specs/` folder. The OneDrive Claude session transcript records the history and the consolidation decision; it is context, not a competing specification.

