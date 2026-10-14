## 3. Data Model

This chapter describes the tables by module and how they relate. It is a narrative guide; the column-level dictionary is generated at render time from the connected database and appears as Appendix A at the end of this manual, so it is always the schema actually installed. A table described here that is missing from Appendix A means the database has not run every migration (`php artisan migrate:status` lists the pending ones).

### 3.1 Identity, access and audit

| Table | Purpose | Key columns |
|---|---|---|
| `users` | Accounts. `active` gates login through the `CheckIfUserIsActive` middleware. Jetstream adds two-factor columns; `theme_preference` (light, dark or system, 8 October 2026) keeps each person's appearance choice. | `name`, `email`, `password`, `active`, `two_factor_secret`, `theme_preference` |
| `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` | spatie/laravel-permission tables. Permissions carry `module` and `display_name` for the roles matrix; the staging module's label reads "Staging and SICR Rules" (13 October 2026) while the permission names stay `stageing-rules.*`. | `name`, `guard_name`, `module`, `display_name` |
| `activity_log` | spatie/laravel-activitylog entries written by `activity()` calls in controllers. | `log_name`, `description`, `subject_type`, `subject_id`, `causer_id`, `properties` |
| `audit_logs` | The application's own audit table written through `AuditLoggerService`, used by imports, landings, builds, EIR actions, the registers and settings. | `action`, `entity_type`, `entity_id`, `user_id`, `reporting_period`, `rows_affected`, `old_values`, `new_values`, `meta` |
| `governance_settings`, `governance_setting_history` | The Governance Centre: each governed setting by `key` with its value, the date it is effective from, who set it and who approved it (maker and checker), and every superseded version. The engines read the value in force at the period end. | `key`, `value`, `effective_from`, `set_by`, `approved_by`, `status`, `reason` |
| `compliance_audits`, `compliance_audit_rows`, `compliance_findings` | The compliance audit register (9 October 2026): one row per workbook, its sections with the reviewer's status, sign-off and a second person's approval, and the findings. | `key`, `reference`, `status`, `proposed_status`, `signed_by`, `approved_by` |
| `notifications` | Laravel database notifications feeding the bell. | `type`, `notifiable_id`, `data`, `read_at` |
| `sessions`, `password_resets` | Framework tables. | |

The Audit Trail screen (Administration, Audit) reads `activity_log` and `audit_logs` through a normalised union so both sources appear in one timeline.

### 3.2 Portfolio and client master

| Table | Purpose | Key columns |
|---|---|---|
| `loan_portfolios` | Portfolio segments (MAIIC core, FInES, Mega Farm and the programme portfolios that `ifrs9:segment-portfolios` creates from the E-Banker product groups). | `name`, `active` |
| `industry_types` | Economic sectors used for RBM sector concentration and the sector PD. | `code`, `name` |
| `loan_product_categories` | The Product Groups screen (Portfolio Setup). The ECL by Product Group report groups `loan_books.product_group`, not this table. | `name` |
| `clients` | Customer master keyed by the core banking customer id. | `customer_id`, `name` |
| `currencies`, `settings` | Organisation currency and the general settings key-value store (the governed settings are in `governance_settings`, 3.1). | `setting_key`, `setting_value` |
| `financial_periods` | Accounting periods with a closed flag. | `start_date`, `end_date`, `closed` |
| `reporting_periods` | Month-end loan book snapshots and whether PD, LGD and ECL have been calculated for them. | `period`, `ecl_calculated` |

### 3.3 Loan book, landing zone and collateral

| Table | Purpose | Key columns |
|---|---|---|
| `loan_books` | One row per contract per reporting period. Carries balances, arrears buckets, staging results, PD, LGD and ECL values written by the engines, agricultural risk fields, the build provenance and the discounting audit fields. | `contract_id`, `customer_id`, `loan_portfolio_id`, `product_group`, `product_code`, `reporting_period`, `carrying_amount`, `commitments`, `facility_utilisation_rate`, `overdue_days`, `remaining_tenor`, `ifrs9stage_pre_qualitative`, `ifrs9stage_post_qualitative`, `calculated_ifrs9_stage`, `pd_prefli`, `pd_post_fli`, `customer_lgd`, `collection_lgd`, `lgd_value`, `ecl_value`, `ecl_value_discounted`, `ecl_discounting_effect`, `ecl_discount_rate`, `ecl_discount_rate_source`, `ecl_discount_status`, `ecl_by_scenario`, `internal_grade_code` |
| `imports` | Import job records with status, counts and the failed rows file path. `kind` (13 October 2026) records what each import loaded, so each import page lists only its own; the rows held before were classified from their file names. | `name`, `kind` (`loan_book`, `clients`, `collateral`, `credit_loss`, `eir_intake`), `status`, `records`, `failed_records`, `failed_file_path` |
| `collateral_registers`, `collateral_types`, `collateral_allocations` | Collateral register, type catalogue with haircuts and realisation periods, and allocation of collateral to exposures with the discounted collateral and coverage. `collateral_types.type_code` is text (PROPERTY, VEHICLE, LAND) since 14 October 2026; it was an integer column, so no type could be added from the screen. | `customer_id`, `collateral_type`, `market_value`, `execution_value`; `type_code`, `standard_haircut`; `collateral_register_id`, `contract_id`, `allocated_collateral`, `discounted_collateral` |

Each `loan_books` row built from the landing zone records how: `build_method` (A the stored E-Banker run, B derived from the ledger, C the printed report), `build_load_id` and `build_source_key` (the load and source row), `stored_carrying_amount` beside a derived `carrying_amount`, `build_flag` (a named difference, null when clean) and `build_basis` (the inputs, including `ledger.written_off`, the cumulative write-off the ECL exposure rule reads). The PD and LGD by segment add `pd_segment_key`, `pd_applied_segment_key`, `pd_segment_run_id`, `pd_matrix_id`, `lgd_applied_segment_key` and `lgd_source_id`. `ecl_by_scenario` (14 October 2026) holds the time-phased engine's shortfall per scenario, undiscounted and discounted, with the weight used. `lifetime_pd` is held to eight decimals (it was cut to two places before 14 October 2026).

Three stage columns exist on `loan_books`. `ifrs9stage_pre_qualitative` is the quantitative stage from days past due; `ifrs9stage_post_qualitative` is the stage after the missed-instalment trigger, the qualitative SICR triggers and the cure period; `eir:stage` writes the same post-trigger stage to `calculated_ifrs9_stage`. The file imports write the classifier's stage to the pre- and post-qualitative columns only. The ECL engine, the time-phased engine and the stress test read the post-qualitative stage, falling back to the calculated and then the pre-qualitative stage; the reports hub and the dashboard read the post-qualitative stage; the ECL movement reconciliation (`EclReconciliationService`) reads `calculated_ifrs9_stage`. On a book staged by `eir:stage` the post-qualitative and calculated stages agree.

The E-Banker landing zone (spec v4 section 6.3) holds every row received from the core banking system exactly as delivered, and the loan book is built from it (chapter 4.6):

| Table | Purpose | Key columns |
|---|---|---|
| `ebanker_queries` | The query register: each query id with its source table, key, account and date columns, version, SQL and hash. | `query_id`, `source_table`, `key_column`, `version`, `sha256` |
| `ebanker_loads` | One row per landed pack, trial balance file or take-on workbook: the route, the period, the manifest, the gate results and the watermark. A file or pack is recognised by `pack_hash` and never landed twice. | `pack_hash`, `pack_name`, `route`, `period`, `manifest`, `gates`, `status` (PENDING, LANDED, QUARANTINED), `watermarks`, `loaded_by` |
| `ebanker_pack_files` | One row per file of a load with its query id, SHA-256, rows declared and loaded, and the new, versioned and unchanged counts. | `load_id`, `file`, `query_id`, `sha256`, `rows_loaded`, `status` |
| `ebanker_raw_rows` | Every row of every file, verbatim as JSON, keyed by query id and the source table's own key. A row that arrives again with different content becomes a new version and the earlier one gets `superseded_at`; a quarantined load keeps its rows against itself and no reader sees them. Unique on load, query and source key. The covering index `ebanker_raw_rows_current_idx` (query id, superseded, load, row date; 13 October 2026) answers the "current rows of landed loads" filter every reader starts from. | `load_id`, `query_id`, `source_key`, `account`, `row_date`, `payload`, `row_hash`, `version`, `superseded_at` |
| `loan_book_builds` | A loan book build: method, period range, requester and a different approver (or the bootstrap label), the loads read and the per-period result. | `method`, `period_from`, `period_to`, `status` (PROPOSED, APPROVED, BUILT, REJECTED, FAILED), `requested_by`, `approved_by`, `approver_label` |
| `reporting_period_locks` | Locked reporting periods. Every writer checks them through `App\Support\ReportingPeriodLock`; a locked month is never restated. | `reporting_period`, `locked_by`, `locked_at`, `reason`, `settings_snapshot` |
| `takeon_blocks`, `takeon_schedule_lines` | The take-on workbook as landed: one row per amortisation block with its terms, the mapped E-Banker account, Finance's tick and the fees, each value with its sheet and cell; one row per instalment line. | `load_id`, `block_no`, `account`, `confirmed`, `total_fees`, `status`; `block_id`, `serial`, `period_end` |
| `contract_takeon` | The take-on build per account: origination, principal, rate, fees, E-Banker's take-on balance at 31 July 2024, the recomputed EIR and amortised cost and the difference, and the take-on solve check (14 October 2026). | `account`, `contract_id`, `basis`, `takeon_balance`, `recomputed_eir`, `recomputed_amortised_cost`, `solve_start_date`, `nofee_eir`, `eir_check` (PASS, REFUSED), `eir_check_reason` |
| `takeon_build_requests` | New 14 October 2026. The take-on build is proposed by one person and approved by another (or under the bootstrap label). | `load_id`, `status` (PROPOSED, APPROVED, SUPERSEDED), `proposed_by`, `approved_by`, `approver_label`, `result` |

The trial balances from Finance land in the same zone: each file is an `ebanker_loads` row with route `TRIAL_BALANCE_FILE`, and each GL line a raw row under query `TB_01` keyed by period, basis and GL code. `gl_trial_balance_lines` (3.9) is derived from those rows.

### 3.4 Staging and SICR

| Table | Purpose |
|---|---|
| `finance_stageing_rules` | The older quantitative thresholds screen (institution type, stage 1 and stage 3 thresholds). Staging does not read it. |
| `staging_thresholds` | Facility-class and tenor DPD thresholds with effective dates and the rebuttal basis, read by the staging classifier and `eir:stage`. Since 14 October 2026 a row is proposed and approved by a different person (`status`, `proposed_by`, `approved_by`, `approved_at`, `reason`); only APPROVED rows are read, and the rows held before the change were marked approved. The seeded rows are DEFAULT from 0 months (31 and 91 days), DEFAULT from 13 months (31 and 181), MEGA_FARM (31 and 91) and a future-dated LONG_TERM proposal (91 and 181). |
| `finance_sicr_groups`, `finance_sicr_items`, `finance_sicr_triggers` | Qualitative SICR register: groups of alert items, each item with the stage it implies (`stage_implied`, 2 or 3), and the triggers raised against a contract or a customer with evidence, the dates they are in force (`effective_from`, `effective_to`) and a maker-checker life (`status` PROPOSED, APPROVED, REJECTED, CLOSED, with the approver, rejecter and closer). |

### 3.5 Probability of default

| Table | Purpose |
|---|---|
| `transition_profile_definitions`, `transition_profile_options` | User-defined profiles: which tables, grading columns and count or balance basis drive a matrix. |
| `transition_matrices`, `transition_matrices_data` | Transition matrices (monthly, and the PD by segment at levels `book`, `portfolio`, `rbm_sector` and `portfolio_rbm_sector`, with the segment key in `pd_calculation_code`) and their cells. |
| `transition_matrix_cummulative`, `transition_matrix_cummulative_data` | Cumulative (multi-period) matrices built from monthly ones. The legacy spelling with a double m is preserved in table and route names. |
| `segment_parameter_runs`, `segment_parameter_results` | The lineage of every PD or LGD segment run: the run (measure, period, basis, window, the governed minimum and thin-segment rule read, status) and, per segment and stage, the cohort, the defaults, the observed and applied rate and where the rate came from (its own, its parent, failed). `expected_credit_loss.pd_segment_run_id` and `pd_segment_keys` record the run behind each ECL row. |
| `internal_grade_profiles`, `internal_grade_mappings`, `grade_tenor_pd` | Internal grading profiles (one active at a time), their grades with PD range, RBM class and S&P rating, and a PD per grade and tenor year. Since 13 October 2026 a grade code is unique within its profile (two profiles may each have a grade 1), the PD range is a probability (`lower_bound`, `upper_bound`) and `pd_probability` keeps eight decimals. |

### 3.6 Loss given default

| Table | Purpose |
|---|---|
| `loss_given_default` | Monthly LGD runs (system or manual, by portfolio or pooled book) with the Stage 3 cohort, cures, recoveries, write-offs and the discounting columns. |
| `loss_given_default_cummulative` | Cumulative LGD over several periods. |
| `lgd_calculation_logs`, `lgd_payment_tracking_long` | Run log and the long-format payment tracking built by the queued payment job. |
| `discounted_payments` | Recovery payments discounted by `CalculateDiscountingJob`, each with the rate source applied. |

### 3.7 Forward-looking information

The tables follow the chain of chapter 5.5: the economic data, the bridge, the calibration evidence, the model registry, the scenario sets and overlays, and the month record.

| Table | Purpose |
|---|---|
| `macro_statistics`, `macro_statistics_data` | The macro variable library and its observations, actual or forecast. `macro_statistics_data.published_at` (13 October 2026) is the publisher's release date, which the as-at reader honours. |
| `macro_source_import_batches` | Provenance of each macro load: source (World Bank, IMF WEO, RBM file, snapshot, manual), address, file hash, rows and series. |
| `macro_credit_loss_definitions`, `macro_credit_loss_data` | The Credit Loss Data register, including external series such as an industry NPL ratio. `portfolio_id` may be empty for a series that belongs to no MAIIC portfolio; `published_at` is the release date. |
| `macro_series` | The bridge's monthly macro series (`statistic_code`, `observation_period` as YYYYMM, `value`, `value_type` actual, estimate or forecast, `lag_months`, `vintage`), filled by `FliBridgeService` from `macro_statistics_data` and the PLR. |
| `credit_loss_series` | The bridge's credit-loss proxies by `proxy_code` and `observation_period`: those derived from the staged loan books, and the external series as `EXT_` proxies. `known_from` (YYYYMM, 13 October 2026) is the month an external figure was published, empty meaning the end of its own month; `source` names where it came from. |
| `governed_parameters`, `regression_definitions`, `structural_events` | The engines' parameters copied from the Governance Centre, the expected sign of each driver against each proxy, and the register of Malawi structural events. |
| `fli_series_distributions`, `analysis_runs`, `fli_suggestions` | The profile of every series, the run log, and the ranked candidates of each correlation sweep. |
| `fli_relationships`, `fli_fits` | Driver, proxy and lag relationships, and the fits estimated on them with slope, intercept, R-squared, p-value, observations and the guardrail `verdict` (applied or declined with `declined_reason`). `approval_status` (NONE, PROPOSED, APPROVED, REJECTED) with proposer and approver records the maker and checker. |
| `fli_models` | New 13 October 2026. The forward-looking models by `code` (today `PD-FLI-BOOK`, the book-level PD model), with `target_metric` and `segment_basis`. |
| `fli_model_versions` | New 13 October 2026. One row per published model version: `model_id`, `version`, `fli_fit_id` (empty for an external model), `statistic_code`, `proxy_code`, `lag_months`, `slope`, `intercept`, `r_squared`, `p_value`, `n_obs`, `calibrated_period`, `effective_from`, `status` (PROPOSED, PUBLISHED, SUPERSEDED, RETIRED, REJECTED), `source` (`fit` or `external`), `evidence`, and the proposer, publisher and retirement stamps. The migration published every fit already approved as a version effective from its own month. |
| `scenario_profiles` | Scenario profiles from which a governed set can be started. |
| `governed_scenario_sets`, `governed_scenarios`, `scenario_shocks` | The governed scenario set of each period and version (status DRAFT, PROPOSED, APPROVED, LOCKED, SUPERSEDED; narrative, source vintage, validation, back-test, sensitivity, `overlays_at_approval`), its scenarios (`weight`, `is_base`, narrative, calibration note) and the shocks that transform the base path (`statistic_code`, `year_offset`, `kind`, `value`). |
| `macro_forecast_weighted` | The probability-weighted path of each driver under a governed set (`governed_set_id`, `reporting_period`, `statistic_code`, `year_offset`, `scenario_values`), shown on Weighted Forecast. |
| `fli_overlays` | The manual-overlay register: period, scope and scope value, adjustment, reason, evidence, owner, expiry, status (PROPOSED, APPROVED, REJECTED, EXPIRED), the set it sits on, and the maker and checker stamps. `risk_driver` and `double_count_review` (13 October 2026) carry the double-counting check. |
| `fli_run_snapshots` | New 13 October 2026. One row per recorded forward-looking month-end: `reporting_period`, `model_version_id`, `fit_id`, `set_id`, `set_version`, `route`, `method`, `weighting`, `overlay_ids`, `macro_vintage`, `loans`, `pre_fli_ecl`, `model_effect`, `overlay_effect`, `booked_ecl`, `weighted_ecl`, `difference`, `reconciles`, `per_scenario`, `by_stage`, `by_portfolio`, `inputs`, `prior_booked_ecl`, `movement_pct`, `needs_commentary`, `commentary`, `status` (RECORDED, SIGNED_OFF, SUPERSEDED) and the recorder and sign-off stamps. Since 14 October 2026 it also carries the discounted allowance beside the engine measure: `booked_ecl_discounted`, `weighted_ecl_discounted`, `discounted_difference`, `discounted_reconciles`, `projection_effect`, `discounting_effect`, `discounted_loans`, `discount_unresolved_loans`, `unresolved_ecl`, `discount_detail`, and the Mega Farm programme left out of the record (`programme_loans`, `programme_ecl`). |
| `ecl_scenario_assumptions` | The legacy scenario weights and PD, LGD and EAD multipliers. The time-phased engine reads them only when no loan of the run carries the governed set the route wrote. |

Each loan in `loan_books` carries its forward-looking lineage: `pd_post_fli`, `fli_adj`, `fli_route`, `fli_method`, `fli_fit_id`, `fli_set_id`, `fli_model_version_id` (13 October 2026), `fli_by_scenario` (each scenario's weight and PD) and `fli_overlay_ids`.

The tables of the earlier forward-looking screens (`scenario_sets`, `scenario_probabilities`, `fli_reporting_periods_parameters`, `fli_adj`, `regression_models`) remain in the schema. The route, the ECL and the month record do not read them: Economic Scenarios still lists the older named sets of `scenario_sets` beneath the governed set, and `regression_models` still feeds the stress test's macro mode.

### 3.8 Expected credit loss

| Table | Purpose |
|---|---|
| `expected_credit_loss` | ECL summary rows per reporting period, stage, calculation level and calculation id (portfolio or sector). Columns include `total_ead`, `total_ecl`, `total_ecl_discounted`, `total_discounting_effect`, `discount_status`, `discount_unresolved_loans`, `pd_value_used`, `lgd_value_used`, `pd_segment_run_id` and `pd_segment_keys`. Composite indexes on period, level and stage were added for the dashboard and report hot paths. |
| `ecl_projection_runs` | One row per run of the time-phased engine (`methodology_version` TIME_PHASED_V1) with the contracts processed and unresolved, the undiscounted and discounted totals and the exceptions; also one row per month of the scenario projection (`methodology_version` = `fli-projection`, the model version, set and horizon in `input_snapshot`). |
| `ecl_cashflow_projections` | Month-by-month rows written by the time-phased engine per contract and scenario: opening and closing EAD, scheduled principal, conditional, marginal and cumulative PD, survival, LGD, undiscounted shortfall, discount rate, exponent and factor, discounted and weighted shortfall, and the rate, PD and LGD sources. |
| `ecl_pd_term_structures` | The PD term structure per contract (time-phased engine) or per portfolio (`contract_id` = `portfolio:<id>`, `source` = `fli-projection`), scenario and future period: conditional, opening survival, marginal and cumulative PD. A new projection supersedes the month's previous run. |
| `ecl_scenario_results` | New 13 October 2026. The ECL of each loan under each scenario of the set applied, written by the forward-looking month-end close: `snapshot_id` (the `fli_run_snapshots` row), `reporting_period`, `loan_book_id`, `contract_id`, `loan_portfolio_id`, `stage`, `scenario`, `weight`, `pd12`, `stage_pd`, `ecl` and, since 14 October 2026, `ecl_discounted` (the loan's discounted shortfall under the scenario). Their totals are held on the same record (`fli_run_snapshots.per_scenario`), which the FLI Month-End Overview and Impact Analysis and the Scenario-weighted ECL report read. A new record of the month deletes the rows of the record it supersedes. |
| `ecl_recovery_cashflows` | Reviewed recovery plans for stage 3 exposures (`status` APPROVED), used to place shortfalls on the dates the plan names. |
| `megafarm_ecl_runs` | Each run of the Mega Farm programme's ECL: scope, PD method, loans, gross, programme ECL, MAIIC's share and allowance, the reason a method was declined and the basis. |
| `stress_scenarios` | Saved stress test parameter sets and result snapshots. |

### 3.9 EIR and revenue recognition

The EIR module keeps its own tables and never adds a foreign key to `loan_books`; it reads the live stage from the tape and writes to its own schema.

| Table | Purpose |
|---|---|
| `contract_eir` | One row per contract: terms, payment frequency and its source, `rate_type`, schedule governance fields, solver outputs (`eir_period`, `eir_nominal_annual`, `eir_effective_annual`), `rate_source`, `calculation_status`, lock fields and the immutable `input_snapshot`. Since 14 October 2026: `locked_label` (an EIR locked under the bootstrap's automated label, never read as a MAIIC approval), `schedule_prepared_by` and `schedule_approver_label` (the version 1 schedule is approved by a second person), and `over_sanction_amount` (drawn above the sanction, flagged for Credit). |
| `contract_cashflow_schedule` | The contractual schedule by version: version 1 is the original, generated, imported or written from the take-on workbook (`schedule_source` GENERATED, IMPORTED or TAKEON_WORKBOOK); a confirmed modification writes a later version. Unique on contract, version and due date. |
| `contract_remaining_cashflow_schedule` | Remaining scheduled flows delivered in Extract B, kept apart as validation evidence. |
| `contract_disbursements` | One row per tranche drawn, from the drawdown import or the ledger. `capitalised_fees` (14 October 2026) is the part of a drawdown that is a fee debit capitalised into the loan. |
| `eir_actual_transactions` | Actual transactions from Extract B, the landed ledger and the approved write-off register. `reverses_external_id` and `reversal_status` (PAIRED, UNPAIRED, REVERSED; 14 October 2026) pair a reversal with the posting it undoes. |
| `contract_fees` | Fee and cost lines with classification status (PENDING, CLASSIFIED, REVIEWED, REJECTED), the integral flag, suggested rule and maker and checker stamps. `superseded_by_source` and `superseded_at` (14 October 2026) mark a line held by a higher source under `fee_source_precedence`; it is shown but never enters the EIR. |
| `fee_gl_unassigned` | New 14 October 2026. A GL_04 fee leg the GL fee route cannot place on one loan, held with the reason and, where the evidence points to one, a candidate contract for a person to tie. |
| `eir_accounting_rules` | The fee rulebook: match conditions, proposed treatment, priority, approval. |
| `eir_fee_classification_events` | Append-only history of classification decisions. |
| `eir_amortisation`, `eir_amortisation_history` | Monthly amortised-cost roll-forward per contract (unique on contract and period), and superseded rows with the reason. Added 13 and 14 October 2026: `advances`, `opening_basis` (SCHEDULE or LEDGER), `eir_annual`, `opening_arrears`, `modification_adjustment`, `adjustment_kind` and `schedule_version`. |
| `eir_calculation_history` | Archived solver results when a calculation is redone or reopened. |
| `reference_rate_series`, `schemes` | The dated PLR series floating loans reprice from, and the E-Banker scheme settings (`scheme_name` and `source`, SM_01 or DD_10, added 14 October 2026). |
| `rate_reset_events` | One row per rate event of a loan, shared by two owners (reconciled 14 October 2026). The reset pipeline (`ResetDetectionService`, `ResetScheduleService`, migration `2026_10_01_000000`) writes each prime-rate reset of a floating loan (`event_type` PLR_RESET, `reset_date` under `plr_mid_period`, `plr_effective_date`, the locked `spread_over_prime`, old and new contractual rate) and owns `status` (DETECTED, SCHEDULED, SOLVED, BLOCKED) and `new_schedule_version`. The register (`RateResetService`, migration `2026_10_14_230000`) adds the loan book's evidence (old and new rate charged and spread, `evidence`, `account`, `reporting_period`), writes each spread change as its own SPREAD_CHANGE row, and owns the maker-checker state `classification_status` (DETECTED, PROPOSED, APPROVED) with the suggested and confirmed classification (IN_CONTRACT or NEGOTIATED). Unique on contract, reset date and event type: a PLR reset and a spread change on one date are two events. |
| `eir_rate_periods` | The reset pipeline's rate periods: one row per loan per stretch at one rate (prime, locked spread, contract rate) and the schedule version built for it. Not a source of the EIR: a floating loan's EIR is decision D38's monthly re-estimation (`eir_amortisation.eir_annual`). |
| `restructure_events` | New 14 October 2026. Candidate restructures found in E-Banker's reschedule history and in the rate changes of fixed-rate loans, keyed by `detection_key`: old and new terms, maturity, instalments and balance, the engine's `suggested_class`, then the classification one person proposes and a second approves (`status` CANDIDATE, PROPOSED, CONFIRMED), with the board approval reference, fair value and modification fees. |
| `contract_modifications` | New 14 October 2026. One row per confirmed event that changes the accounting: the old and new schedule versions, the carrying amount before and after, the present value of the new flows, the 10 percent test (`change_pct`, `threshold_pct`), the `treatment` (MODIFICATION, DERECOGNITION, CATCH_UP), the gain or loss and the revised EIR; `status` PENDING until the revenue run of its month, then APPLIED or BLOCKED. |
| `loan_write_offs` | New 13 October 2026. The governed write-off register for write-offs that are not on the loan account in E-Banker: amount, date, reason, evidence, GL reference, `status` (PROPOSED, APPROVED, REJECTED, WITHDRAWN, REVERSED), proposer and a different approver; on approval `transaction_id` points to the `eir_actual_transactions` row written with source `WRITE_OFF_REGISTER`. |
| `gl_interest_postings` | Extract C: interest posted per contract, GL code and month. |
| `gl_trial_balance_lines` | Monthly trial balance balances by GL code and basis (pre or post closing). Balances only, never movements. Derived from the landed `TB_01` rows (3.3, chapter 4.7). |
| `gl_account_scope` | Which GL codes are in EIR scope, their statement, normal balance and chart (E-Banker or QuickBooks). |
| `import_mappings` | Saved header-to-field mappings per import type. |

### 3.10 Help centre, tickets and workspace

| Table | Purpose |
|---|---|
| `help_categories`, `help_articles`, `help_article_steps`, `help_article_images`, `help_article_routes` | The database-driven manuals. `help_categories.manual` separates the User Manual (`user`) from the Administrator Manual (`admin`). |
| `tickets`, `ticket_updates` | Support and change tickets with a three-digit reference and an activity trail. |
| `period_workspace_tasks`, `workspace_messages` | Period close checklist state and team messages per reporting period. |
| `manuals` | Legacy per-route manual entries kept for backward compatibility. |

### 3.11 Legacy tables

The application began as a credit-scoring and loan-origination platform. Tables for loan applications, approval stages, scoring attributes, client financial analysis, communication campaigns, locations and the member portal remain in the schema. Their permissions were removed in August 2026 and their code is scheduled for removal under ticket #009. They are listed in Appendix A because they exist in the installed database, but no IFRS 9 function depends on them.
