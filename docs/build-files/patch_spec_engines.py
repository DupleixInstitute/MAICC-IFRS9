"""Spec v4 6.10: the bootstrap runs the engine chain (--run-engines) and verifies golden numbers, as the suite does."""
p = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\specs\MAIIC_EIR_Engine_Specification_v4_2026-10-07.md"
s = open(p, encoding="utf-8").read()
def rep(old, new):
    global s
    assert old in s, old[:70]
    s = s.replace(old, new)
rep("`php artisan eir:bootstrap --fresh --with-client-inputs --build --verify`",
    "`php artisan eir:bootstrap --fresh --with-client-inputs --build --run-engines --verify`")
rep("| 6 | On `--verify`: runs the baselines of section 9 against the database and prints the table, PASS or FAIL per row, and exits non-zero on any FAIL | Yes |",
    "| 6 | On `--run-engines`: runs the engine chain of 6.10.1 end to end for every period from the first full month to the last month in the pack, in the order the modules depend on each other, synchronously (the queue driver is set to `sync` for the run, so no worker is needed and nothing is left waiting) | Yes: a period already run and locked is skipped |\n| 7 | On `--verify`: runs the baselines of section 9 and the golden numbers of 6.10.2 against the database, prints the table, PASS or FAIL per row, and exits non-zero on any FAIL | Yes |")
chain = '''
#### 6.10.1 The engine chain the bootstrap runs

The suite's bootstrap runs its engines in dependency order and treats the result as the proof of the installation. MAIIC's chain, in the order the modules depend on each other, with what each needs and how the bootstrap supplies it where a person would normally act:

| Order | Engine | Entry point | Needs | How the bootstrap meets it |
|---|---|---|---|---|
| 1 | Macro statistics | `macro:import-worldbank`; the WEO snapshot | Internet, or the committed snapshot | Snapshot under `docs/bootstrap/macro/` when the fetch fails (13.3) |
| 2 | Scenario set | the seeded first set of 15.8 | An approved set | Seeded as *proposed*; the bootstrap approves it under the automated label so the chain can run; it is not a MAIIC approval and the screen says so |
| 3 | Staging and SICR | `StagingClassifier` on each month's loan book | Governed thresholds; the loan book | Thresholds seeded; loan books built in step 5 |
| 4 | PD: transition matrices | `TransitionMatrixService`, cumulative | Twelve or more months of graded loan books | The 26 months from the landing zone |
| 5 | LGD | `CalculateLGDJob` and its chunks | Payments and collateral | Payments from the ledger; collateral from the register; runs synchronously |
| 6 | Forward-looking adjustment | the route in force (14.6) | An approved model, or an overlay | With no approvable model on a clean install the bootstrap runs the **manual overlay route at zero** and marks every post-FLI PD "no adjustment: bootstrap"; the regression route is used once a model has been approved by two people |
| 7 | ECL | `ifrs9:recalculate-ecl --pd=pd_post_fli`, the time-phased service | PD, LGD, EAD, stage, the EIR for discounting | Steps 3 to 6 and 9 |
| 8 | EIR: schedules | `eir:generate-schedules` | Terms, drawdowns, the take-on basis | Steps of 6.9; version 1 approved under the automated label |
| 9 | EIR: solve and revenue | `CalculateEirJob`, `eir:run-revenue {period}` | Approved schedules, fees, rates, the governed conventions | Step 8; fees from the charges table and the take-on workbook |
| 10 | GL reconciliation | `EirGlReconciliationService` | The trial balances | Landed in step 3 of the bootstrap |
| 11 | Stress testing | `StressTestingController` logic as a service | The ECL of step 7 | Run for the seeded scenario set |
| 12 | Reports and the audit workbooks | the 30 reports; `tools/compliance` | Everything above | Generated for the last period; the Baselines sheet reads the live figures |

Two orderings matter and are enforced: the ECL is discounted at the EIR, so step 9 runs before step 7 for each period (the chain runs 8 and 9 first, then 3 to 7, then 10 to 12); and the forward-looking adjustment runs once per scenario under the seeded weighting method of 15.5, so step 6 is a loop over the set of step 2.

Three engines are queued jobs today (LGD, EIR solve, revenue). Under `--run-engines` they run synchronously; on a server they still run through the queue, and the installation guide carries the worker command with a memory ceiling, because the suite found that a worker left on its default limit stops itself part-way through a full chain and later jobs wait in silence.

#### 6.10.2 Golden numbers

`--verify` checks the section 9 baselines and, once the engines have run, these figures. They are MAIIC's own, so a bootstrap on any server either reproduces them or fails:

| Golden number | Expected | Source |
|---|---|---|
| ECL at 31 December 2025, total and by stage | The audited impairment allowance in the 2025 financial statements | The AFS mapping workbook (`TB_AFS_MAP`), the impairment lines; the signed statements |
| ECL at 31 December 2024 | The audited 2024 allowance | The same workbook's December 2024 column |
| Contractual interest 2025, by loan GL | MWK 5,293,988,207.06 for January 2025 to July 2026 (section 9), split by month | The ledger |
| Interest income 2025 against the trial balance | The year-to-date tie, 83 of 83 account-months | Section 9 |
| Loan balances by GL at each year-end | The audited figures: 2024 and 2025 by GL | The AFS mapping workbook |
| The revenue shift 2024 and 2025 | Recorded on the first run that Dr Thom approves, then held as the number later builds must reproduce | The engine, once approved |

The last row is how a golden number is born: the first approved run writes it, and from then on `--verify` fails any build that changes it without a governed reason.

'''
rep("Anything the bootstrap approves (a load, a build, a generated schedule) is stamped with the approver label",
    chain + "Anything the bootstrap approves (a load, a build, a generated schedule, the seeded scenario set) is stamped with the approver label")
rep("**What it is for.** The first installation on MAIIC's server; every UAT and acceptance round, which starts from a bootstrap so that the result is reproducible; Deloitte's copy; and the developers' own daily state, since a bootstrap with `--build --verify` is also the end-to-end test of the data foundation.",
    "**What it is for.** The first installation on MAIIC's server; every UAT and acceptance round, which starts from a bootstrap so that the result is reproducible; Deloitte's copy; and the developers' own daily state, since a bootstrap with `--build --run-engines --verify` is the end-to-end test of the data foundation and of every engine on it.")
rep("| D26 | **One-command bootstrap from committed inputs** (section 6.10): the E-Banker pack, the data-dictionary results, the take-on workbook and the queries are committed under `docs/bootstrap/` with a manifest, and `eir:bootstrap` builds a clean install to a proven state. |",
    "| D26 | **One-command bootstrap from committed inputs** (section 6.10): the E-Banker pack, the data-dictionary results, the trial balances, the take-on workbook and the queries are committed under `docs/bootstrap/` with a manifest; `eir:bootstrap` builds a clean install, runs every engine in dependency order and verifies the result against the baselines and MAIIC's golden numbers. |")
rep("- **Bootstrap (the command)**: `eir:bootstrap`, which builds a clean install from the inputs committed under `docs/bootstrap/` and verifies it against the baselines.",
    "- **Bootstrap (the command)**: `eir:bootstrap`, which builds a clean install from the inputs committed under `docs/bootstrap/`, runs the engine chain and verifies the result against the baselines and the golden numbers.\n- **Golden number**: a figure MAIIC has already accepted (an audited allowance, a tie proven on the ledger, an approved run's result) that every later build must reproduce.")
open(p, "w", encoding="utf-8").write(s); print("engine chain and golden numbers added to 6.10")
