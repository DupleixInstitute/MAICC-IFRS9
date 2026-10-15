p = 'docs/manuals/technical/14-glossary.md'
s = open(p, encoding='utf-8').read()


def rep(a, b):
    global s
    assert s.count(a) == 1, a[:70]
    s = s.replace(a, b)


rep("| CCF |", "| Booked ECL | The loss allowance the reports carry: the month-end ECL discounted at the EIR (IFRS 9 5.5.17(b)). The undiscounted engine build-up is shown beside it with a reconciling line (`app/Services/Reports/BookedEcl.php`) |\n| CCF |")
rep("| ECL | Expected credit loss: EAD times PD times LGD, discounted at the EIR |",
    "| E-Banker pack | A set of extract files from MAIIC's core banking system with a manifest of each file's rows and SHA-256, landed through gates into the landing zone (`eir:land-pack`). The committed packs live under `docs/bootstrap` |\n| ECL | Expected credit loss: EAD times PD times LGD, discounted at the EIR |")
rep("| FLI month record | The `fli_run_snapshots` row of a month-end: the ECL under each scenario, the weighted ECL reconciled to the booked ECL, what the month ran under, and the second person's sign-off |",
    "| FLI month record | The `fli_run_snapshots` row of a month-end: the ECL under each scenario, the weighted ECL reconciled to the booked ECL, the discounted allowance beside the undiscounted engine build-up, what the month ran under, and the second person's sign-off. The Mega Farm programme is not in it |")
rep("| Golden number | A fixed expected result that a test pins so a calculation change is noticed |",
    "| Golden number | A fixed expected result that a test or the bootstrap verify pins so a calculation change is noticed; the bootstrap's are in `docs/bootstrap/golden.json` and are checked by `eir:baselines` |\n| Held GL fee line | A fee posting from the GL fee route (GL_04) that cannot be tied to one loan; it waits on Fee Classification, with its reason, until a person ties it |")
rep("| LGD |", "| Landing zone | The tables that hold E-Banker packs, the take-on workbook and the trial balances as received (`ebanker_raw_rows` and its companions), from which the loan books, contract inputs and the GL side are built |\n| LGD |")
rep("| Maker and checker | A control where the person who records a decision cannot be the one who approves it |",
    "| Maker and checker | A control where the person who records a decision cannot be the one who approves it |\n| Mega Farm programme | The Mega Farm lending schemes, segmented as their own programme and measured by their own engine (`megafarm:ecl`), kept out of the main PD, LGD and ECL and out of the FLI month record |\n| Modification | A change to a loan's contractual cash flows that is not a derecognition; measured under IFRS 9 5.4.3 with the 10 percent test, from a confirmed event in the restructure register |")
rep("| PD |", "| Period lock | A row in `reporting_period_locks`: a locked month is never restated, and every writer refuses it until it is unlocked with a reason |\n| PD |")
rep("| Readiness gate |", "| Rate reset | A move in the rate of a floating-rate loan, recorded in the rate-reset register under maker and checker, with the B5.4.6 catch-up |\n| Readiness gate |")
rep("| Stage 1, 2, 3 |", "| Take-on | The opening population of loans already on the book when the platform went live, landed from the take-on workbook with their schedules and fees, and their take-on EIRs checked before lock |\n| Stage 1, 2, 3 |")
rep("| Transition matrix |", "| Tranche | One drawdown of a facility; each tranche's schedule and EIR follow the date it was drawn |\n| Transition matrix |")
rep("| Unwind | Interest recognised on the ECL allowance of a stage 3 exposure as time passes |",
    "| Unwind | Interest recognised on the ECL allowance of a stage 3 exposure as time passes |\n| Write-off register | The governed record of loans written off (`loan_write_offs`), proposed by one person and approved by another; the ledger's own write-offs are recognised by `WriteOffClassifier` |")
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
