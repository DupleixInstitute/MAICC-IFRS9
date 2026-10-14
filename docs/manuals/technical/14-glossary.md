## 14. Glossary

| Term | Meaning |
|---|---|
| Amortised cost | The carrying amount built up as opening balance plus effective interest minus cash received, less any allowance |
| Booked ECL | The loss allowance the reports carry: the month-end ECL discounted at the EIR (IFRS 9 5.5.17(b)). The undiscounted engine build-up is shown beside it with a reconciling line (`app/Services/Reports/BookedEcl.php`) |
| CCF | Credit conversion factor: the share of an undrawn commitment expected to be drawn at default; the platform uses the facility utilisation rate |
| CIR | Contractual interest rate: the rate stated in the offer letter, before fees |
| Control total | A period-end account balance from the trial balance that all detail must reconcile to |
| Cure rate | Share of stage 3 exposure restored to performing status |
| DPD | Days past due; drives the RBM classification and the quantitative stage |
| EAD | Exposure at default: carrying amount plus undrawn commitments times utilisation |
| E-Banker pack | A set of extract files from MAIIC's core banking system with a manifest of each file's rows and SHA-256, landed through gates into the landing zone (`eir:land-pack`). The committed packs live under `docs/bootstrap` |
| ECL | Expected credit loss: EAD times PD times LGD, discounted at the EIR |
| EIR | Effective interest rate: the rate that exactly discounts contractual cash flows including integral fees to the initial net investment |
| Extract A, B, C | The E-Banker extracts: contract master, transactions and remaining schedules, GL interest postings |
| Financial period | An accounting period that can be opened and closed |
| FLI | Forward-looking information: the governed chain that moves each loan's PD for the economy, from the macro series through an approved model version and scenario set to the post-FLI PD (chapter 5.5) |
| FLI month record | The `fli_run_snapshots` row of a month-end: the ECL under each scenario, the weighted ECL reconciled to the booked ECL, the discounted allowance beside the undiscounted engine build-up, what the month ran under, and the second person's sign-off. The Mega Farm programme is not in it |
| Golden number | A fixed expected result that a test or the bootstrap verify pins so a calculation change is noticed; the bootstrap's are in `docs/bootstrap/golden.json` and are checked by `eir:baselines` |
| Held GL fee line | A fee posting from the GL fee route (GL_04) that cannot be tied to one loan; it waits on Fee Classification, with its reason, until a person ties it |
| Integral fee | A fee or cost directly attributable to origination and therefore part of the EIR (IFRS 9 B5.4.1) |
| Landing zone | The tables that hold E-Banker packs, the take-on workbook and the trial balances as received (`ebanker_raw_rows` and its companions), from which the loan books, contract inputs and the GL side are built |
| LGD | Loss given default: share of exposure not recovered after collateral and recoveries |
| Maker and checker | A control where the person who records a decision cannot be the one who approves it |
| Mega Farm programme | The Mega Farm lending schemes, segmented as their own programme and measured by their own engine (`megafarm:ecl`), kept out of the main PD, LGD and ECL and out of the FLI month record |
| Model version | A forward-looking model published from an approved fit, or an approved external calibration, with its coefficients and an effective month; never edited after publication |
| Model version in force | The latest published model version whose effective month is on or before the reporting period |
| Modification | A change to a loan's contractual cash flows that is not a derecognition; measured under IFRS 9 5.4.3 with the 10 percent test, from a confirmed event in the restructure register |
| NPL | Non-performing loan: Substandard, Doubtful and Loss under the RBM directive, 90 or more days past due |
| PD | Probability of default over twelve months (stage 1) or lifetime (stages 2 and 3) |
| Period lock | A row in `reporting_period_locks`: a locked month is never restated, and every writer refuses it until it is unlocked with a reason |
| RBM classification | Reserve Bank of Malawi prudential classes: Pass, Special Mention, Substandard, Doubtful, Loss |
| Rate reset | A move in the rate of a floating-rate loan, recorded in the rate-reset register under maker and checker, with the B5.4.6 catch-up |
| Readiness gate | The set of named conditions a contract must meet before its EIR can be solved |
| Recovery rate | Share of defaulted exposure recovered |
| Reporting period | A month-end loan book snapshot identified as YYYY-MM |
| Scenario set | The governed set of economic scenarios of a period, with weights summing to 100 and shocks on the base path, proposed by one person and approved by another |
| SICR | Significant increase in credit risk: the trigger that moves an exposure from stage 1 to stage 2 |
| Spool | The individual ledger entries that make up a control total |
| Stage 1, 2, 3 | Performing (12-month ECL), underperforming after SICR (lifetime ECL), credit-impaired (lifetime ECL on the net carrying amount) |
| Take-on | The opening population of loans already on the book when the platform went live, landed from the take-on workbook with their schedules and fees, and their take-on EIRs checked before lock |
| Tranche | One drawdown of a facility; each tranche's schedule and EIR follow the date it was drawn |
| Transition matrix | Probabilities of moving between grades or stages over a horizon; the basis of PD |
| Transition profile | The user-defined configuration that decides which data and grading columns build a matrix |
| Unwind | Interest recognised on the ECL allowance of a stage 3 exposure as time passes |
| Write-off register | The governed record of loans written off (`loan_write_offs`), proposed by one person and approved by another; the ledger's own write-offs are recognised by `WriteOffClassifier` |
