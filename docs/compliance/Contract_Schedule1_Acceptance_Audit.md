# Contract Schedule 1 - deliverables and acceptance - compliance audit

The MAIIC-Dupleix implementation agreement, Schedule 1: solution components and deliverables, each with the screen, document or test that discharges it.

Malawi Agricultural and Industrial Investment Company (MAIIC) - IFRS 9 EIR and ECL system - build of 14 October 2026 (branch eir_revenue_recognition, commit abe140d) against specification v4. As of 14 October 2026: the code at commit abe140d (branch eir_revenue_recognition) and the fresh bootstrap maiic_ifrs9_results2 of 13 October 2026 (made before commit 216aee2), read with SELECTs only. The clause numbers of the signed schedule are to be confirmed at acceptance (P9); the deliverable numbers are those the manuals carry. Reviewer: Dr Thomson Kumwenda (CFO), at acceptance. The Excel workbook of the same name is the working copy (drop-down statuses, hyperlinked contents, reviewer sign-off column, a live Baselines sheet); this document is its printable twin.

Source: The implementation agreement between MAIIC and Dupleix Institute, Schedule 1 (solution components; deliverable 2 the migration reconciliation report; deliverables 5, 6 and 7 the manuals), as the manuals cite it. Spec v4 section 8 (the build plan, P4b to P10) and section 12.

Generated 2026-10-14 08:30:21; the Baselines were read from the database `maiic_ifrs9_results2`.

## Status summary

| Status | Meaning | Sections |
|---|---|---|
| **Done** | Implemented in the system and visible in an approved output; the test in column 11 fails if the behaviour changes. | 10 |
| **Partially done** | The mechanics are in place, but a parameter, an input or a piece of evidence still differs from the requirement. | 8 |
| **Outstanding** | Required for MAIIC and not built. | 1 |
| **Not applicable, documented** | Does not apply to MAIIC by a recorded decision or fact (reason and reference given); revisit if the facts change. | 0 |
| **Evidence needed from MAIIC** | The system is ready; MAIIC must supply a document, minute or dataset before the row can be closed. | 1 |
| **Total** | | 20 |

## Contents

| Ref | Section | Status | Governance setting | Test |
|---|---|---|---|---|
| **SOLUTION COMPONENTS** | | | | |
| S1.1 | Data import and onboarding | Done | ebanker_feed_route; loan_book_build_method | Tests\Feature\Ebanker\PackLandingServiceTest::test_a_tampered_hash_quarantines_the_pack_and_keeps_its_rows_where_no_reader_sees_them; Tests\Feature\Ebanker\TrialBalanceLandingServiceTest; Tests\Feature\Imports\ImportValidationTest::test_the_check_endpoint_writes_nothing_and_a_clean_file_passes |
| S1.2 | Collateral module | Partially done |  | Tests\Feature\Imports\ImportValidationTest::test_a_collateral_file_missing_a_required_column_is_refused_and_nothing_is_written |
| S1.3 | EIR module and revenue recognition | Partially done | day_count; stage3_interest_basis; fee_source_precedence; takeon_eir_plausibility; partly_drawn_interest_basis | Tests\Feature\Eir\EirCalculationWorkflowTest; Tests\Feature\Eir\EirAsAtServiceTest; Tests\Feature\Eir\TakeonAndTrancheEirInputTest |
| S1.4 | IFRS 9 model set-up: SICR and staging, PD, LGD, forward-looking information | Done | dpd_basis; stage3_missed_instalments; stage_cure_months; pd_segmentation_basis; lgd_segment_min_cohort; fli_transmission_method | Tests\Feature\Eir\GovernanceServiceTest; Tests\Feature\Pd\PdAndLgdEngineTest; Tests\Feature\Pd\PdSegmentationTest; Tests\Feature\FLI\FliRouteServiceTest |
| S1.5 | ECL engine | Partially done | scenario_weighting_method | Tests\Feature\Ecl\TimePhasedEclServiceTest; Tests\Feature\Ecl\EclGoldenNumberTest::discounted_mode_keeps_undiscounted_ecl_and_names_unresolved_contracts; Tests\Feature\Scenario\ScenarioSetServiceTest |
| S1.6 | Reports | Done |  | Tests\Feature\Ifrs9ReportDownloadTest::test_report_downloads_as_pdf; Tests\Feature\Pd\SegmentReportsRenderTest::test_the_reports_with_the_pd_parts_render_and_download |
| S1.7 | Audit trail | Done | maker_checker_admin_override | Tests\Feature\Eir\GovernanceServiceTest::test_every_proposal_and_approval_is_audit_logged_with_old_and_new_values; Tests\Feature\Compliance\ComplianceAuditServiceTest |
| S1.8 | Dashboard and workspace | Done |  | Tests\Feature\DashboardChartsTest::test_every_month_of_ecl_by_portfolio_adds_back_to_the_trend_total |
| S1.9 | User and access management; operations | Done |  | Tests\Feature\NavigationTest::test_every_leaf_names_a_registered_route; Tests\Feature\Security\RequireLoginTest::test_no_get_route_answers_a_guest_with_a_page |
| S1.10 | Internal grading | Partially done |  |  |
| S1.11 | EAD module | Done |  | Tests\Feature\Ebanker\LoanBookBuildServiceTest::test_an_undrawn_amount_past_maturity_is_no_exposure; Tests\Feature\Ecl\EclBuildUpServiceTest::test_a_credit_balance_has_no_exposure_and_no_negative_ecl |
| S1.12 | Support | Done |  | Tests\Feature\TicketsTest::test_status_change_is_recorded_in_the_activity_trail |
| S1.13 | The Mega Farm programme | Evidence needed from MAIIC | mega_farms_scope; megafarm_pd_method; megafarm_scalar_ceiling | Tests\Feature\Ecl\MegaFarmProgrammeSeparationTest |
| S1.14 | Annual report alignment | Partially done |  | Tests\Unit\EclMovementAttributionTest::test_every_column_ties |
| **DELIVERABLES** | | | | |
| D2 | Migration reconciliation report | Partially done |  |  |
| D5 | User manual | Done |  | Tests\Feature\HelpManualTest::test_pdf_renders_from_the_same_rows; Tests\Feature\AuditFindingM16ScreensTest::test_the_help_chapters_carry_the_suite_group_names |
| D6 | Administrator and Technical Manual | Partially done |  | Tests\Feature\HelpManualTest::test_admin_manual_reader_shows_only_admin_chapters_and_exports_pdf; Tests\Feature\SystemDocsTest::test_technical_manual_renders_chapters_with_anchored_sections_and_live_schema |
| D7 | Installation and Configuration Guide | Done |  | Tests\Feature\SystemDocsTest::test_installation_guide_renders_without_schema_appendix; Tests\Feature\SystemDocsTest::test_documentation_menu_lists_all_four_deliverables |
| P10 | Deployment, training and source code | Outstanding |  |  |
| **ACCEPTANCE** | | | | |
| A1 | The specification's acceptance tests | Partially done |  | Tests\Feature\Ebanker\LoanBookBuildServiceTest::test_method_b_derives_the_carrying_amount_from_the_ledger_and_keeps_the_stored_figure_beside_it |

## Baselines - the acceptance ties read from the system

Generated 2026-10-14 08:30:21 from maiic_ifrs9_results2.

| Tie | Expected | System figure | Result |
|---|---|---|---|
| Interest posted Jan 2025 to Jul 2026 (ledger, types 303 and 120) | 5,293,988,207.06 | 5,293,988,207.06 | PASS |
| Derived carrying amount against the stored report, in-scope loan GLs (over 2 cents) | 0 differences | 0 of 2495 account-months | PASS |
| Mega Farm: account-months whose MF_03 ledger differs from the Loan Book Report (reported, not a test) | reported | 77540 account-months | PASS |
| Year-end interest batch contra on 4215 | 32,956,675.55 | 32,956,675.55 | PASS |
| Year-end interest batch contra on 4216 | -21,733,262.70 | -21,733,262.70 | PASS |
| Loan book 1050101 at 31 Dec 2025 against the trial balance | 1,368,737,810.68 | 1,368,737,810.68 | PASS |
| Loan book 1050102 at 31 Dec 2025 against the trial balance | 6,434,045,052.56 | 6,434,045,052.56 | PASS |
| Loan book 1050201 at 31 Dec 2025 against the trial balance (accepted exception, spec 3.5: -400,000.00) | 1,402,393,139.46 | 1,401,993,139.46 | PASS |
| Loan book 1050202 at 31 Dec 2025 against the trial balance (accepted exception, spec 3.5: 1,000,000.00) | 3,808,288,656.15 | 3,809,288,656.15 | PASS |
| Loan book 1050401 at 31 Dec 2025 against the trial balance | 1,827,148,120.69 | 1,827,148,120.69 | PASS |

## Findings - items that need a decision or a fix

### F1. The clause numbers of the signed schedule are not confirmed (S1.1) - Open

- **What was found:** The rows follow the component list and deliverable numbers the manuals give for Schedule 1; the signed schedule is not in the repository.
- **Impact:** The workbook cannot be cited clause by clause at acceptance.
- **Recommended action:** Dr Thom supplies the signed schedule; the references are replaced by its clause numbers.
- **Owner:** Dr Thom

### F2. The technical manual lags the build (D6) - Open

- **What was found:** The user manual's chapters carry the suite group names (the earlier finding on old menu groups is closed), but the technical volume does not describe the services of 13 and 14 October, and the installation guide's 9.8 does not name the later packs.
- **Impact:** A maintainer reading the manual misses the write-off, trial balance, chart-in-force and GL fee code.
- **Recommended action:** Add the services to chapters 03, 04, 06 and 09 of the technical manual and the packs to installation 9.8.
- **Owner:** Dupleix

### F3. The migration reconciliation report is not assembled (D2) - Open

- **What was found:** The ties exist (Baselines sheet, Coverage and Blockers, Imports exceptions, trial balance output) but no single signed report.
- **Impact:** Deliverable 2 cannot be accepted as one document.
- **Recommended action:** Assemble the report from the existing exports with the take-on fee reconciliation and present it for sign-off.
- **Owner:** Dupleix / Finance

### F4. Deployment and training not started (P10) - Open

- **What was found:** The system is not installed at MAIIC; no user training has been delivered.
- **Impact:** Go-live on 31 October 2026 depends on it.
- **Recommended action:** Agree the installation date and training plan with MAIIC ICT and Finance.
- **Owner:** Dupleix / MAIIC ICT

## Section-by-section audit

### SOLUTION COMPONENTS

**S1.1. Data import and onboarding** - *Done*

- **What it requires:** Load the client's loan book, customer and transaction data into the system with validation.
- **What the engine does:** The E-Banker landing zone with its gates (hash, row count, query register, dates, keys); packs 2 to 4 land in the bootstrap (Bootstrap::landLaterPacks); the loan book built from the ledger for every month from July 2024; the take-on workbook landed cell by cell; the monthly trial balances through their own screen (EIR Data, Trial Balances; TrialBalanceLandingService); every import has a sample file and a check that refuses a bad file before anything is written. Feed routes 1 to 3 are built (manual upload, folder poll, API inbox); routes 4 and 5 are catalogue options only.
- **Compliance comment:** Compliant.
- **Where to see it:** Customer & Loan Data, Imports & Feeds: /imports, /eir-feed, /eir-takeon; EIR Data, Trial Balances: /eir-trial-balances
- **Governance setting:** `ebanker_feed_route; loan_book_build_method`
- **Test that proves it:** `Tests\Feature\Ebanker\PackLandingServiceTest::test_a_tampered_hash_quarantines_the_pack_and_keeps_its_rows_where_no_reader_sees_them; Tests\Feature\Ebanker\TrialBalanceLandingServiceTest; Tests\Feature\Imports\ImportValidationTest::test_the_check_endpoint_writes_nothing_and_a_clean_file_passes`
- **Reviewer sign-off:** ____________________   Date: ____________

**S1.2. Collateral module** - *Partially done*

- **What it requires:** Register collateral, its types and its allocation to facilities.
- **What the engine does:** The collateral register, types and allocation screens exist with a checked import; the E-Banker security register (P2_11, 185 rows) is landed. On the fresh bootstrap collateral_registers and collateral_allocations are empty, and the LGD engine reads no collateral. An uncommitted migration (collateral type code as text) is in the working tree.
- **General comment:** Fresh bootstrap maiic_ifrs9_results2 (13 October 2026, before commit 216aee2).
- **Compliance comment:** Partially done: Credit completes the data; Dupleix links collateral to the LGD.
- **Where to see it:** Collateral Management, Collateral Register: /collateral/register
- **Test that proves it:** `Tests\Feature\Imports\ImportValidationTest::test_a_collateral_file_missing_a_required_column_is_refused_and_nothing_is_written`
- **Reviewer sign-off:** ____________________   Date: ____________

**S1.3. EIR module and revenue recognition** - *Partially done*

- **What it requires:** Solve the EIR per facility from its contractual flows and fees, roll the amortised cost forward monthly, and reconcile to the general ledger.
- **What the engine does:** Built: the EIR solver on dated flows (tranche-timed and take-on EIRs, 216aee2), the fee routes with source precedence, the monthly roll-forward with ledger cash, the GL reconciliation, the EIR as at any date, write-offs classified. Not built: modification and restructuring (5.4.3, 3.3.2), the B5.4.6 catch-up, rate reset events, the journal proposal (D6). On the fresh bootstrap 14 of 144 EIRs are locked, 33 are blocked at the fee gate and 97 have no approved schedule.
- **General comment:** Fresh bootstrap maiic_ifrs9_results2 (13 October 2026, before commit 216aee2). Until 14 October 2026 this row was Done and said '31 solved, 12 locked, 19 awaiting'.
- **Compliance comment:** Partially done; see the IFRS 9 EIR workbook.
- **Where to see it:** EIR & Revenue Recognition: /eir-calculations; /eir-as-at; /eir-reconciliation
- **Governance setting:** `day_count; stage3_interest_basis; fee_source_precedence; takeon_eir_plausibility; partly_drawn_interest_basis`
- **Test that proves it:** `Tests\Feature\Eir\EirCalculationWorkflowTest; Tests\Feature\Eir\EirAsAtServiceTest; Tests\Feature\Eir\TakeonAndTrancheEirInputTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**S1.4. IFRS 9 model set-up: SICR and staging, PD, LGD, forward-looking information** - *Done*

- **What it requires:** Staging rules, PD, LGD and the forward-looking model, configurable and governed.
- **What the engine does:** Staging on the directive's thresholds (seeded rows, read-only on screen) with the governed day basis, missed-instalment trigger and cure period, reading E-Banker's instalment chart in force (216aee2); PD and LGD by segment on the governed basis, Mega Farm kept apart; the forward-looking chain (correlation, regression, guardrail), the transmission methods with their cards, the scenario sets; all governed in the Governance Centre, whose catalogue holds 61 settings (20 of which no engine reads; IFRS 9 EIR workbook S4).
- **General comment:** Until 14 October 2026 the row said 48 settings.
- **Compliance comment:** Compliant.
- **Where to see it:** IFRS 9 Model Setup; Forward-Looking Information; Governance Centre: /eir-governance
- **Governance setting:** `dpd_basis; stage3_missed_instalments; stage_cure_months; pd_segmentation_basis; lgd_segment_min_cohort; fli_transmission_method`
- **Test that proves it:** `Tests\Feature\Eir\GovernanceServiceTest; Tests\Feature\Pd\PdAndLgdEngineTest; Tests\Feature\Pd\PdSegmentationTest; Tests\Feature\FLI\FliRouteServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**S1.5. ECL engine** - *Partially done*

- **What it requires:** Compute the expected credit loss per facility and in total, discounted, with the forward-looking adjustment and scenario weighting.
- **What the engine does:** The ECL per loan on the post-FLI PD, weighted across the scenarios, for every period, failing closed on a missing input; since 216aee2 the month-end runs discounted at the EIR and stores the discounted allowance beside the undiscounted measure. The reports and the IFRS 9 note still read the undiscounted ecl_value. On the fresh bootstrap the main book at September 2026 carries 11.57bn undiscounted and 7.12bn discounted on the 62 loans resolved (72 unresolved, before 216aee2).
- **General comment:** Fresh bootstrap maiic_ifrs9_results2 (13 October 2026, before commit 216aee2). Until 14 October 2026 this row was Done and quoted 8.94bn.
- **Compliance comment:** Partially done: the reports must carry the discounted allowance; the figures await MAIIC's reviews.
- **Where to see it:** ECL Processing, ECL Calculation: /expected-credit-loss/list
- **Governance setting:** `scenario_weighting_method`
- **Test that proves it:** `Tests\Feature\Ecl\TimePhasedEclServiceTest; Tests\Feature\Ecl\EclGoldenNumberTest::discounted_mode_keeps_undiscounted_ecl_and_names_unresolved_contracts; Tests\Feature\Scenario\ScenarioSetServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**S1.6. Reports** - *Done*

- **What it requires:** The IFRS 9 disclosure, regulatory and management reports, exportable.
- **What the engine does:** 29 reports in the Reports hub, each rendered by ifrs9:smoke-reports and downloadable as PDF and Excel; the IFRS 9 note; the RBM Return (provisional); the EIR as at a date and the GL reconciliation with their exports; the loan book and ECL reconciliations; stress testing; the auditor's pack. Content gaps are audited in the IFRS 7 and RBM workbooks (write-offs nil, undiscounted allowance, provisional return form).
- **General comment:** Until 14 October 2026 the row said thirty reports and cited an unrelated test.
- **Compliance comment:** Compliant as a component.
- **Where to see it:** Reports: /ifrs9-reports; /rbm-return; /auditor-pack
- **Test that proves it:** `Tests\Feature\Ifrs9ReportDownloadTest::test_report_downloads_as_pdf; Tests\Feature\Pd\SegmentReportsRenderTest::test_the_reports_with_the_pd_parts_render_and_download`
- **Reviewer sign-off:** ____________________   Date: ____________

**S1.7. Audit trail** - *Done*

- **What it requires:** Every change recorded with who, when, the old and the new value.
- **What the engine does:** The audit log records every load, build, approval, staging, engine run, setting change, write-off and compliance sign-off with the old and new values; the Audit Trace follows one record; the Governance Centre's maker-checker is enforced in code.
- **Compliance comment:** Compliant.
- **Where to see it:** Governance, Audit: Audit Trail /audit-trail; Audit Trace /audit-trace; Compliance Audits /compliance-audits
- **Governance setting:** `maker_checker_admin_override`
- **Test that proves it:** `Tests\Feature\Eir\GovernanceServiceTest::test_every_proposal_and_approval_is_audit_logged_with_old_and_new_values; Tests\Feature\Compliance\ComplianceAuditServiceTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**S1.8. Dashboard and workspace** - *Done*

- **What it requires:** A dashboard of the book and the allowance; a workspace of the period's tasks.
- **What the engine does:** The dashboard (period, portfolio and compare-to filters; ECL by portfolio, scenarios by stage and observed default-rate charts, each with its own range) and the workspace of the period's tasks, in light and dark mode.
- **Compliance comment:** Compliant.
- **Where to see it:** Dashboard: /dashboard; Workspace: /workspace
- **Test that proves it:** `Tests\Feature\DashboardChartsTest::test_every_month_of_ecl_by_portfolio_adds_back_to_the_trend_total`
- **Reviewer sign-off:** ____________________   Date: ____________

**S1.9. User and access management; operations** - *Done*

- **What it requires:** Users, roles and permissions, settings, financial periods, maintenance.
- **What the engine does:** User management, roles and permissions, settings, financial periods and the Maintenance screen that runs the month-end jobs; every route requires a login and the navigation is filtered by permission so no user sees a link that answers 403.
- **General comment:** Until 14 October 2026 the path given was /users; the route is /user.
- **Compliance comment:** Compliant.
- **Where to see it:** Access & Setup, Users & Roles: /user, /user/role; Settings: /setting; Support & Maintenance, Maintenance: /maintenance
- **Test that proves it:** `Tests\Feature\NavigationTest::test_every_leaf_names_a_registered_route; Tests\Feature\Security\RequireLoginTest::test_no_get_route_answers_a_guest_with_a_page`
- **Reviewer sign-off:** ____________________   Date: ____________

**S1.10. Internal grading** - *Partially done*

- **What it requires:** Internal credit grades for the book, linked to the PD.
- **What the engine does:** The Internal Grades screens (profiles, the PD matrix with each grade's S&P rating) and the S&P grades import exist (SpGradesImportService). No engine reads the grades: on the fresh bootstrap there are 0 internal grade profiles and internal_grade_code is null on every main-book loan; staging and PD run on days past due and segments.
- **General comment:** Fresh bootstrap maiic_ifrs9_results2 (13 October 2026, before commit 216aee2). Row added 14 October 2026.
- **Compliance comment:** Partially done: MAIIC decides whether grades drive the PD; until then they are reference only.
- **Where to see it:** IFRS 9 Model Setup, PD Model, Internal Grades: /internal-grading
- **Reviewer sign-off:** ____________________   Date: ____________

**S1.11. EAD module** - *Done*

- **What it requires:** Exposure at default per facility.
- **What the engine does:** EAD is the carrying amount plus undrawn commitments times utilisation, floored at nil; an undrawn amount past maturity is no exposure (216aee2); the EAD report renders.
- **General comment:** Row added 14 October 2026.
- **Compliance comment:** Compliant.
- **Where to see it:** Reports: EAD /ifrs9-reports/ead-report
- **Test that proves it:** `Tests\Feature\Ebanker\LoanBookBuildServiceTest::test_an_undrawn_amount_past_maturity_is_no_exposure; Tests\Feature\Ecl\EclBuildUpServiceTest::test_a_credit_balance_has_no_exposure_and_no_negative_ecl`
- **Reviewer sign-off:** ____________________   Date: ____________

**S1.12. Support** - *Done*

- **What it requires:** Support tickets with a trail.
- **What the engine does:** Support tickets with references, status changes recorded in the activity trail and resolution dates.
- **General comment:** Row added 14 October 2026.
- **Compliance comment:** Compliant.
- **Where to see it:** Support & Maintenance, Support Tickets: /tickets
- **Test that proves it:** `Tests\Feature\TicketsTest::test_status_change_is_recorded_in_the_activity_trail`
- **Reviewer sign-off:** ____________________   Date: ____________

**S1.13. The Mega Farm programme** - *Evidence needed from MAIIC*

- **What it requires:** The Government's Mega Farm loans provided for on a documented basis, separately from MAIIC's own book.
- **What the engine does:** Since 216aee2 the programme is segmented as its own portfolio, kept out of MAIIC's PD, LGD and ECL, and provided by megafarm:ecl from its own LGD cohort under the governed PD method; its monthly books (MF_01, MF_03) are landed. Not built: the interest split, the maize receivable and the fund liability of spec 16.4 and 16.5, which wait on D30.
- **General comment:** Row added 14 October 2026. The IFRS 9 impairment workbook row 16 carries the detail.
- **Compliance comment:** Evidence needed: Dr Thom's confirmation of D30.
- **Where to see it:** ECL Processing, Mega Farm Programme: /megafarm
- **Governance setting:** `mega_farms_scope; megafarm_pd_method; megafarm_scalar_ceiling`
- **Test that proves it:** `Tests\Feature\Ecl\MegaFarmProgrammeSeparationTest`
- **Reviewer sign-off:** ____________________   Date: ____________

**S1.14. Annual report alignment** - *Partially done*

- **What it requires:** Stage tables by segment matching Note 8 of the audited statements (Mega Farm and the main book: gross, ECL and net by stage with the roll-forward), the sector concentration, interest income lines matching Note 15 and the RBM prudential tables (technical manual 7.6).
- **What the engine does:** The IFRS 9 note gives gross, ECL and net by stage with the roll-forward for a chosen portfolio and ties every column; the concentration report; the RBM Return. Gaps: write-offs nil in the roll-forward, the allowance undiscounted, EIR interest only on locked loans, the RBM Return provisional.
- **General comment:** Row added 14 October 2026.
- **Compliance comment:** Partially done; see the IFRS 7 and RBM workbooks.
- **Where to see it:** Reports: IFRS 9 note /ifrs9-reports/fs-disclosure; /rbm-return
- **Test that proves it:** `Tests\Unit\EclMovementAttributionTest::test_every_column_ties`
- **Reviewer sign-off:** ____________________   Date: ____________

### DELIVERABLES

**D2. Migration reconciliation report** - *Partially done*

- **What it requires:** A reconciliation of the migrated data to the source system and the ledger.
- **What the engine does:** The evidence exists in the system: the derived carrying amount equals E-Banker's own report on 2,495 of 2,495 in-scope account-months, the loan GLs tie to the December 2025 trial balance (two accepted exceptions), the interest posted ties to the ledger (Baselines sheet), and the Imports exception files, the Coverage and Blockers export and the trial balance output (installation guide 9.7). It is not assembled as one report, and the take-on fee totals stated by MAIIC and landed are not reconciled in it.
- **General comment:** Fresh bootstrap maiic_ifrs9_results2 (13 October 2026, before commit 216aee2). Row added 14 October 2026.
- **Compliance comment:** Partially done: assemble and sign the report.
- **Where to see it:** Baselines sheet; EIR Calculations, Blockers: /eir-coverage; Imports: /imports
- **Reviewer sign-off:** ____________________   Date: ____________

**D5. User manual** - *Done*

- **What it requires:** A user manual for the finance officer.
- **What the engine does:** The help centre carries the user manual with per-page help and a PDF, including the Trial Balances and Write-offs screens of 13 October 2026; its chapter titles follow the suite groups.
- **General comment:** Screenshots of the screens changed on 13 and 14 October were not checked for this audit. Until 14 October 2026 this row was Partially done.
- **Compliance comment:** Compliant.
- **Where to see it:** Support & Maintenance, System Documentation, User Manual: /help
- **Test that proves it:** `Tests\Feature\HelpManualTest::test_pdf_renders_from_the_same_rows; Tests\Feature\AuditFindingM16ScreensTest::test_the_help_chapters_carry_the_suite_group_names`
- **Reviewer sign-off:** ____________________   Date: ____________

**D6. Administrator and Technical Manual** - *Partially done*

- **What it requires:** The administrator manual and the technical manual (deliverable 6 in two volumes).
- **What the engine does:** The administrator volume is in the help centre with its own PDF; the technical volume is repository Markdown under docs/manuals/technical rendered at /system-docs/technical. The technical volume does not yet describe the services of 13 and 14 October (WriteOffClassifier and the write-off register, TrialBalanceLandingService, Bootstrap::landLaterPacks, InstalmentChartHistory, the GL fee route and fee_source_precedence).
- **General comment:** Until 14 October 2026 this workbook called D6 the administrator manual alone and D7 the technical manual with the installation guide, against DocumentFrontMatter.
- **Compliance comment:** Partially done: the technical volume needs the new services.
- **Where to see it:** System Documentation: Administrator Manual /help/admin; Technical Manual /system-docs/technical
- **Test that proves it:** `Tests\Feature\HelpManualTest::test_admin_manual_reader_shows_only_admin_chapters_and_exports_pdf; Tests\Feature\SystemDocsTest::test_technical_manual_renders_chapters_with_anchored_sections_and_live_schema`
- **Reviewer sign-off:** ____________________   Date: ____________

**D7. Installation and Configuration Guide** - *Done*

- **What it requires:** An installation and configuration guide.
- **What the engine does:** Repository Markdown under docs/manuals/installation, rendered at /system-docs/installation with a PDF; section 9.8 gives the bootstrap and the monthly close as the procedure.
- **General comment:** Section 9.8 step 3 does not yet name the later packs the bootstrap lands (packs 3 and 4). Until 14 October 2026 the paths given were /docs/technical and /docs/installation.
- **Compliance comment:** Compliant.
- **Where to see it:** System Documentation, Installation Guide: /system-docs/installation
- **Test that proves it:** `Tests\Feature\SystemDocsTest::test_installation_guide_renders_without_schema_appendix; Tests\Feature\SystemDocsTest::test_documentation_menu_lists_all_four_deliverables`
- **Reviewer sign-off:** ____________________   Date: ____________

**P10. Deployment, training and source code** - *Outstanding*

- **What it requires:** Install in MAIIC's environment; train MAIIC's users; hand over the source code (spec v4 section 8, P10).
- **What the engine does:** Not started: the system runs on Dupleix's environment; no MAIIC user training has been delivered (docs/training holds a developer course, uncommitted); the read-only E-Banker account from ICT is awaited.
- **General comment:** Row added 14 October 2026.
- **Compliance comment:** Outstanding (Dupleix, after P9).
- **Where to see it:** 
- **Reviewer sign-off:** ____________________   Date: ____________

### ACCEPTANCE

**A1. The specification's acceptance tests** - *Partially done*

- **What it requires:** The baselines of specification section 9 pass on the production copy.
- **What the engine does:** eir:baselines and eir:bootstrap --verify assert: the interest posted January 2025 to July 2026, the method B tie on every in-scope account-month, the two year-end batch contras and the December 2025 loan GLs against the trial balance; all pass on the fresh bootstrap (Baselines sheet). Spec section 9 lists 16 ties; the rest, and the ECL and revenue-shift golden numbers, are not asserted.
- **General comment:** Until 14 October 2026 the row said nine golden checks.
- **Compliance comment:** Partially done: the remaining ties and the golden numbers await MAIIC's first approved run.
- **Where to see it:** eir:bootstrap --verify; eir:baselines
- **Test that proves it:** `Tests\Feature\Ebanker\LoanBookBuildServiceTest::test_method_b_derives_the_carrying_amount_from_the_ledger_and_keeps_the_stored_figure_beside_it`
- **Reviewer sign-off:** ____________________   Date: ____________
