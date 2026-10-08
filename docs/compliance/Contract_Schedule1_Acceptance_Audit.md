# Contract Schedule 1 - deliverables and acceptance - compliance audit

The MAIIC-Dupleix implementation agreement, Schedule 1: solution components and deliverables, each with the screen, document or test that discharges it.

Malawi Agricultural and Industrial Investment Company (MAIIC) - IFRS 9 EIR and ECL system - build of 8 October 2026 against specification v4. Prepared 9 October 2026 from the build of specification v4 and the system as it stands on the clean install. The clause numbers of the signed schedule are to be confirmed at acceptance (P9). Reviewer: Dr Thomson Kumwenda (CFO), at acceptance. The Excel workbook of the same name is the working copy (drop-down statuses, hyperlinked contents, reviewer sign-off column, a live Baselines sheet); this document is its printable twin.

Source: The implementation agreement between MAIIC and Dupleix Institute, Schedule 1 (solution components; deliverables 5, 6 and 7 are the manuals). Spec v4 section 8 (the build plan) and section 12.

## Status summary

| Status | Meaning | Sections |
|---|---|---|
| **Done** | Implemented in the system and visible in an approved output; the test in column 11 fails if the behaviour changes. | 9 |
| **Partially done** | The mechanics are in place, but a parameter, an input or a piece of evidence still differs from the requirement. | 4 |
| **Outstanding** | Required for MAIIC and not built. | 0 |
| **Not applicable, documented** | Does not apply to MAIIC by a recorded decision or fact (reason and reference given); revisit if the facts change. | 0 |
| **Evidence needed from MAIIC** | The system is ready; MAIIC must supply a document, minute or dataset before the row can be closed. | 0 |
| **Total** | | 13 |

## Contents

| Ref | Section | Status | Governance setting | Test |
|---|---|---|---|---|
| **SOLUTION COMPONENTS** | | | | |
| S1.1 | Data onboarding | Done | ebanker_feed_route; loan_book_build_method | Tests\Feature\Ebanker\PackLandingServiceTest; Tests\Feature\Ebanker\LoanBookBuildServiceTest |
| S1.2 | Collateral management | Partially done |  | Tests\Feature\Ebanker\PackLandingServiceTest::test_a_composite_key_lands_one_row_per_pair |
| S1.3 | The effective interest rate and revenue recognition | Done | (section 4.2) | Tests\Feature\Eir\EirCalculationWorkflowTest; Tests\Feature\Eir\EirAsAtServiceTest |
| S1.4 | IFRS 9 model set-up | Done | (section 4.2) | Tests\Feature\Eir\GovernanceServiceTest; Tests\Feature\Pd\PdAndLgdEngineTest; Tests\Feature\FLI\FliRouteServiceTest |
| S1.5 | The ECL engine | Done | scenario_weighting_method | Tests\Feature\Ecl\TimePhasedEclServiceTest; Tests\Feature\Scenario\ScenarioSetServiceTest |
| S1.6 | Reports | Done |  | Tests\Feature\Eir\EirAsAtServiceTest |
| S1.7 | The audit trail | Done |  | Tests\Feature\Eir\EirGovernanceControllerTest |
| S1.8 | The dashboard and the workspace | Done |  | Tests\Feature\NavigationTest |
| S1.9 | Administration | Done |  | Tests\Feature\NavigationTest::test_every_leaf_names_a_registered_route |
| **DOCUMENTATION** | | | | |
| D5 | User manual | Partially done |  | Tests\Feature\NavigationTest |
| D6 | Administrator manual | Partially done |  | Tests\Feature\NavigationTest |
| D7 | Technical manual and installation guide | Done |  | Tests\Feature\NavigationTest |
| **ACCEPTANCE** | | | | |
| A1 | The specification's acceptance tests | Partially done |  | Tests\Feature\Ebanker\LoanBookBuildServiceTest |

## Baselines - the acceptance ties read from the system

Generated 2026-10-09 00:03:09 from maiic_ifrs9_bootstrap.

| Tie | Expected | System figure | Result |
|---|---|---|---|
| Interest posted Jan 2025 to Jul 2026 (ledger, types 303 and 120) | 5,293,988,207.06 | 5,293,988,207.06 | PASS |
| Derived carrying amount against the stored report (over 2 cents) | 0 differences | 0 of 2361 account-months | PASS |
| Year-end interest batch contra on 4215 | 32,956,675.55 | 32,956,675.55 | PASS |
| Year-end interest batch contra on 4216 | -21,733,262.70 | -21,733,262.70 | PASS |
| Loan book 1050101 at 31 Dec 2025 against the trial balance | 1,368,737,810.68 | 1,368,737,810.68 | PASS |
| Loan book 1050102 at 31 Dec 2025 against the trial balance | 6,434,045,052.56 | 6,434,045,052.56 | PASS |
| Loan book 1050201 at 31 Dec 2025 against the trial balance (accepted exception, spec 3.5: -400,000.00) | 1,402,393,139.46 | 1,401,993,139.46 | PASS |
| Loan book 1050202 at 31 Dec 2025 against the trial balance (accepted exception, spec 3.5: 1,000,000.00) | 3,808,288,656.15 | 3,809,288,656.15 | PASS |
| Loan book 1050401 at 31 Dec 2025 against the trial balance | 1,827,148,120.69 | 1,827,148,120.69 | PASS |

## Findings - items that need a decision or a fix

### F1. The clause numbers of the signed schedule are not confirmed (S1.1 to A1) - Open

- **What was found:** The rows follow the solution components the navigation was built on; the signed Schedule 1's numbering is not in the repository.
- **Impact:** The workbook cannot be cited clause by clause at acceptance.
- **Recommended action:** Dr Thom supplies the signed schedule; the references are replaced by its clause numbers.
- **Owner:** Dr Thom

### F2. The manuals name the old menu groups (D5, D6) - Open

- **What was found:** Fifteen help-centre passages and the user manual's navigation chapter still refer to the groups the suite layout replaced.
- **Impact:** A reader is sent to a group that no longer exists.
- **Recommended action:** Re-word the passages and retake the screenshots (spec 11.6).
- **Owner:** Dupleix

## Section-by-section audit

### SOLUTION COMPONENTS

**S1.1. Data onboarding** - *Done*

- **What it requires:** Load the client's loan book, customer and transaction data into the system with validation.
- **What the engine does:** The E-Banker landing zone with its gates, the loan book built from the ledger by three methods, the take-on schedules landed with their cells, the trial balances; the bootstrap loads it all from the committed folder and proves it against the golden numbers.
- **Compliance comment:** Compliant.
- **Where to see it:** Data Foundation, E-Banker Feed: /eir-feed; Take-on Schedules: /eir-takeon
- **Governance setting:** `ebanker_feed_route; loan_book_build_method`
- **Test that proves it:** `Tests\Feature\Ebanker\PackLandingServiceTest; Tests\Feature\Ebanker\LoanBookBuildServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**S1.2. Collateral management** - *Partially done*

- **What it requires:** Register collateral, its types and its allocation to facilities.
- **What the engine does:** The collateral register, types and allocation screens exist; the security register from E-Banker (P2_11) is landed; 31 active accounts have no security recorded.
- **Compliance comment:** Partially done: the data is Credit's to complete.
- **Where to see it:** Data Foundation, Collateral Register: /collateral/register
- **Test that proves it:** `Tests\Feature\Ebanker\PackLandingServiceTest::test_a_composite_key_lands_one_row_per_pair`
- **Reviewer sign-off:** ____________________   Date: ____________

**S1.3. The effective interest rate and revenue recognition** - *Done*

- **What it requires:** Solve the EIR per facility from its contractual flows and fees, roll the amortised cost forward monthly, and reconcile to the general ledger.
- **What the engine does:** The EIR engine, the roll-forward with cash from the ledger, the GL reconciliation, the EIR as at any date; 31 EIRs solved on the clean install, 12 locked, 19 awaiting the fee rulebook's approval.
- **Compliance comment:** Compliant in mechanics; see the IFRS 9 EIR workbook's findings.
- **Where to see it:** Financial Modelling, EIR Calculations: /eir-calculations; Report Hub, EIR as at a Date: /eir-as-at
- **Governance setting:** `(section 4.2)`
- **Test that proves it:** `Tests\Feature\Eir\EirCalculationWorkflowTest; Tests\Feature\Eir\EirAsAtServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**S1.4. IFRS 9 model set-up** - *Done*

- **What it requires:** Staging rules, PD, LGD and the forward-looking model, configurable and governed.
- **What the engine does:** The staging thresholds per the directive, the PD transition matrix and the LGD cohort workout as services, the forward-looking chain (correlation, regression, guardrail), the transmission methods with their cards, the scenario sets, all governed in the Governance Centre with 48 settings.
- **Compliance comment:** Compliant.
- **Where to see it:** Governance Centre: /eir-governance; Financial Modelling: /expected-credit-loss
- **Governance setting:** `(section 4.2)`
- **Test that proves it:** `Tests\Feature\Eir\GovernanceServiceTest; Tests\Feature\Pd\PdAndLgdEngineTest; Tests\Feature\FLI\FliRouteServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**S1.5. The ECL engine** - *Done*

- **What it requires:** Compute the expected credit loss per facility and in total, discounted, with the forward-looking adjustment and scenario weighting.
- **What the engine does:** The time-phased ECL discounted at the EIR, on the post-FLI PD, with the sensitivity across scenarios and the stress runs; on the clean install 8.94bn at August 2026 under the bootstrap label.
- **Compliance comment:** Compliant; the figures await MAIIC's reviews (fee rulebook, matrix and LGD key-locks, the fit and the set).
- **Where to see it:** Financial Modelling, ECL Calculation: /expected-credit-loss
- **Governance setting:** `scenario_weighting_method`
- **Test that proves it:** `Tests\Feature\Ecl\TimePhasedEclServiceTest; Tests\Feature\Scenario\ScenarioSetServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**S1.6. Reports** - *Done*

- **What it requires:** The IFRS 9 disclosure, regulatory and management reports, exportable.
- **What the engine does:** Thirty reports in the Report Hub, every one rendered by ifrs9:smoke-reports on the clean install; the EIR as at a date with its CSV; the auditor's pack.
- **Compliance comment:** Compliant.
- **Where to see it:** Report Hub: /ifrs9-reports
- **Test that proves it:** `Tests\Feature\Eir\EirAsAtServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**S1.7. The audit trail** - *Done*

- **What it requires:** Every change recorded with who, when, the old and the new value.
- **What the engine does:** The audit log records every load, build, approval, staging, engine run, setting change and compliance sign-off with the old and new values; the Governance Centre's maker-checker is enforced in code.
- **Compliance comment:** Compliant.
- **Where to see it:** Governance Centre, Audit Trail: /audit-trail
- **Test that proves it:** `Tests\Feature\Eir\EirGovernanceControllerTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**S1.8. The dashboard and the workspace** - *Done*

- **What it requires:** A dashboard of the book and the allowance; a workspace of the period's tasks.
- **What the engine does:** The dashboard and the workspace exist under the suite layout with light and dark mode.
- **Compliance comment:** Compliant.
- **Where to see it:** Dashboard: /dashboard; Workspace: /workspace
- **Test that proves it:** `Tests\Feature\NavigationTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**S1.9. Administration** - *Done*

- **What it requires:** Users, roles and permissions, settings, support.
- **What the engine does:** User management, roles and permissions, settings and support tickets; the navigation is filtered by permission so no user sees a link that answers 403.
- **Compliance comment:** Compliant.
- **Where to see it:** Administration: /users
- **Test that proves it:** `Tests\Feature\NavigationTest::test_every_leaf_names_a_registered_route`
- **Reviewer sign-off:** ____________________   Date: ____________

### DOCUMENTATION

**D5. User manual** - *Partially done*

- **What it requires:** A user manual for the finance officer.
- **What the engine does:** The help centre carries the user manual with per-page help and a PDF; the navigation chapter and screenshots are to be redone for the suite layout.
- **Compliance comment:** Partially done.
- **Where to see it:** System Documentation, User Manual: /help
- **Test that proves it:** `Tests\Feature\NavigationTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**D6. Administrator manual** - *Partially done*

- **What it requires:** An administrator manual.
- **What the engine does:** The administrator manual exists in the help centre; the passages naming the old menu groups are to be re-worded.
- **Compliance comment:** Partially done.
- **Where to see it:** System Documentation, Administrator Manual: /help/admin
- **Test that proves it:** `Tests\Feature\NavigationTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**D7. Technical manual and installation guide** - *Done*

- **What it requires:** A technical manual and an installation guide.
- **What the engine does:** Both are repository Markdown under docs/manuals, rendered in the system; the installation guide's section 9.8 gives the bootstrap and the monthly feed as the procedure.
- **Compliance comment:** Compliant.
- **Where to see it:** System Documentation, Technical Manual: /docs/technical; Installation Guide: /docs/installation
- **Test that proves it:** `Tests\Feature\NavigationTest`
- **Reviewer sign-off:** ____________________   Date: ____________

### ACCEPTANCE

**A1. The specification's acceptance tests** - *Partially done*

- **What it requires:** The baselines of specification section 9 pass on the production copy.
- **What the engine does:** Nine golden checks pass on the clean install (the Baselines sheet of every workbook); the ECL at the year-ends and the revenue shift become golden numbers on the first run Dr Thom approves.
- **Compliance comment:** Partially done: the year-end golden numbers await MAIIC's decisions.
- **Where to see it:** eir:bootstrap --verify; eir:baselines
- **Test that proves it:** `Tests\Feature\Ebanker\LoanBookBuildServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________
