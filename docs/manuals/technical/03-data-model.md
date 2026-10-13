## 3. Data Model

This chapter describes the tables by module and how they relate. It is a narrative guide; the column-level dictionary is generated at render time from the connected database and appears as Appendix A at the end of this manual, so it is always the schema actually installed.

### 3.1 Identity, access and audit

| Table | Purpose | Key columns |
|---|---|---|
| `users` | Accounts. `active` gates login through the `CheckIfUserIsActive` middleware. Jetstream adds two-factor columns. | `name`, `email`, `password`, `active`, `two_factor_secret` |
| `roles`, `permissions`, `model_has_roles`, `role_has_permissions` | spatie/laravel-permission tables. Permissions carry `module` and `display_name` for the roles matrix. | `name`, `guard_name`, `module`, `display_name` |
| `activity_log` | spatie/laravel-activitylog entries written by `activity()` calls in controllers. | `log_name`, `description`, `subject_type`, `subject_id`, `causer_id`, `properties` |
| `audit_logs` | The application's own audit table written through `AuditLoggerService`, used by imports, EIR actions and settings. | `action`, `entity_type`, `entity_id`, `user_id`, `data` |
| `notifications` | Laravel database notifications feeding the bell. | `type`, `notifiable_id`, `data`, `read_at` |
| `sessions`, `password_reset_tokens`, `personal_access_tokens` | Framework tables. | |

The Audit Trail screen (Administration, Audit) reads `activity_log` and `audit_logs` through a normalised union so both sources appear in one timeline.

### 3.2 Portfolio and client master

| Table | Purpose | Key columns |
|---|---|---|
| `loan_portfolios` | Portfolio segments (MAIIC core, FInES, Mega Farm and derived agricultural portfolios). | `name`, `active` |
| `industry_types` | Economic sectors used for RBM sector concentration. | `name` |
| `loan_product_groups` | Product groups used by the ECL by Product Group report. | `name` |
| `clients` | Customer master keyed by the core banking customer id. | `customer_id`, `name` |
| `currencies`, `settings` | Organisation currency and settings key-value store. | `setting_key`, `setting_value` |
| `financial_periods` | Accounting periods with an open or closed status. | `start_date`, `end_date`, `status` |
| `reporting_periods` | Month-end loan book snapshots and whether ECL has been calculated for them. | `period`, `ecl_calculated` |

### 3.3 Loan book and collateral

| Table | Purpose | Key columns |
|---|---|---|
| `loan_books` | One row per contract per reporting period. Carries balances, arrears buckets, staging results, PD, LGD and ECL values written by the engines, agricultural risk fields and the EIR discounting audit fields. | `contract_id`, `customer_id`, `loan_portfolio_id`, `reporting_period`, `carrying_amount`, `commitments`, `facility_utilisation_rate`, `overdue_days`, `ifrs9stage_pre_qualitative`, `ifrs9stage_post_qualitative`, `calculated_ifrs9_stage`, `pd_prefli`, `pd_post_fli`, `lgd_value`, `ecl_value`, `internal_grade_code`, `ecl_discount_rate`, `ecl_discount_status` |
| `imports` | Import job records with status, counts and the failed rows file path. `kind` (added 13 October 2026) records what each import loaded, so each import page lists only its own; the rows held before were classified from their file names. | `name`, `kind` (`loan_book`, `clients`, `collateral`, `credit_loss`, `eir_intake`), `status`, `records`, `failed_records`, `failed_file_path` |
| `collaterals`, `collateral_types`, `collateral_allocations` | Collateral register, type catalogue with forced-sale discounts, and allocation of collateral to exposures. | `client_id`, `collateral_type_id`, `value`, `allocated_value` |

Three stage columns exist on `loan_books`. `ifrs9stage_pre_qualitative` is the quantitative stage from the DPD ladder, `ifrs9stage_post_qualitative` is after SICR qualitative triggers, and `calculated_ifrs9_stage` is the stage written by the ECL run. The reports hub and stress testing read the pre-qualitative stage, the dashboard reads the post-qualitative stage, and the reconciliation services read the calculated stage. Chapter 12 lists this as an item to unify.

### 3.4 Staging and SICR

| Table | Purpose |
|---|---|
| `finance_stageing_rules` | Quantitative thresholds (days past due bands to stage). |
| `staging_thresholds` | Facility-class and tenor-specific DPD thresholds with effective dates, used by the staging classifier on import. A `DEFAULT` row gives the base ladder. |
| `sicr_groups`, `sicr_items`, `sicr_triggers` | Qualitative SICR configuration: groups of alert items and the triggers that move an exposure to stage 2. |

### 3.5 Probability of default

| Table | Purpose |
|---|---|
| `transition_profiles` and their definitions | User-defined profiles: which tables, grading columns and count or balance basis drive a matrix. |
| `transition_matrices`, `transition_matrix_data` | Monthly transition matrices with draft, calculated, locked and applied states. |
| `transition_matrix_cummulative`, `transition_matrix_cummulative_data` | Cumulative (multi-period) matrices built from monthly ones. The legacy spelling with a double m is preserved in table and route names. |
| `internal_grades`, `internal_grade_profiles` | The A to G master risk scale mapped from 12-month PD. |

### 3.6 Loss given default

| Table | Purpose |
|---|---|
| `loss_given_default`, `loss_given_default_data` | Monthly LGD runs (system or manual) with recovery, cure and closing balances by stage, plus discounting columns. |
| `lgd_cummulative` and data | Cumulative LGD over several periods. |
| `lgd_calculation_logs`, `lgd_payment_tracking_long` | Run log and the long-format payment tracking built by the queued payment job. |
| `discounted_payments` | Recovery payments discounted at the resolved EIR by `CalculateDiscountingJob`. |

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
| `fli_run_snapshots` | New 13 October 2026. One row per recorded forward-looking month-end: `reporting_period`, `model_version_id`, `fit_id`, `set_id`, `set_version`, `route`, `method`, `weighting`, `overlay_ids`, `macro_vintage`, `loans`, `pre_fli_ecl`, `model_effect`, `overlay_effect`, `booked_ecl`, `weighted_ecl`, `difference`, `reconciles`, `per_scenario`, `by_stage`, `by_portfolio`, `inputs`, `prior_booked_ecl`, `movement_pct`, `needs_commentary`, `commentary`, `status` (RECORDED, SIGNED_OFF, SUPERSEDED) and the recorder and sign-off stamps. |
| `ecl_scenario_assumptions` | Approved scenario weights used by the time-phased ECL engine. |

Each loan in `loan_books` carries its forward-looking lineage: `pd_post_fli`, `fli_adj`, `fli_route`, `fli_method`, `fli_fit_id`, `fli_set_id`, `fli_model_version_id` (13 October 2026), `fli_by_scenario` (each scenario's weight and PD) and `fli_overlay_ids`.

The tables of the earlier forward-looking screens (`scenario_sets`, `scenario_probabilities`, `fli_reporting_periods_parameters`, `fli_adj`, `regression_models`) remain in the schema. The route, the ECL and the month record do not read them: Economic Scenarios still lists the older named sets of `scenario_sets` beneath the governed set, and `regression_models` still feeds the stress test's macro mode.

### 3.8 Expected credit loss

| Table | Purpose |
|---|---|
| `expected_credit_loss` | ECL summary rows per reporting period, stage, calculation level and calculation id (portfolio, sector or total). Columns include `total_ead`, `total_ecl`, `pd_value_used`, `lgd_value_used`. Composite indexes on period, level and stage were added for the dashboard and report hot paths. |
| `time_phased_ecl` | Month-by-month projection rows written by the time-phased engine: PD, survival, EAD, LGD, discount factor and shortfall per scenario. |
| `ecl_scenario_results` | New 13 October 2026. The ECL of each loan under each scenario of the set applied, written by the forward-looking month-end close: `snapshot_id` (the `fli_run_snapshots` row), `reporting_period`, `loan_book_id`, `contract_id`, `loan_portfolio_id`, `stage`, `scenario`, `weight`, `pd12`, `stage_pd`, `ecl`. Their totals are held on the same record (`fli_run_snapshots.per_scenario`), which the FLI Month-End Overview and Impact Analysis and the Scenario-weighted ECL report read. A new record of the month deletes the rows of the record it supersedes. |
| `ecl_projection_runs`, `ecl_pd_term_structures` | The scenario projection: one run row per month (`methodology_version` = `fli-projection`, the model version, set and horizon in `input_snapshot`) and, per portfolio, scenario and future year, the conditional, opening survival, marginal and cumulative PD (`contract_id` = `portfolio:<id>`, `source` = `fli-projection`). A new projection supersedes the month's previous run. |
| `ecl_recovery_cashflows` | Reviewed recovery plans for stage 3 exposures, used to place shortfalls on the dates the plan names. |
| `stress_scenarios` | Saved stress test parameter sets and result snapshots. |

### 3.9 EIR and revenue recognition

The EIR module keeps its own tables and never adds a foreign key to `loan_books`; it reads the live stage from the tape and writes to its own schema.

| Table | Purpose |
|---|---|
| `contract_eir` | One row per contract: terms from Extract A, payment frequency and its source, schedule governance fields, solver outputs (`eir_period`, `eir_nominal_annual`, `eir_effective_annual`), `rate_source`, `calculation_status`, lock fields and the immutable `input_snapshot`. |
| `contract_cashflow_schedule` | The original contractual schedule, version 1, generated or imported. Unique on contract, version and due date. |
| `contract_remaining_cashflow_schedule` | Remaining scheduled flows delivered in Extract B, kept apart as validation evidence. |
| `eir_actual_transactions` | Actual transactions from Extract B. |
| `contract_fees` | Fee and cost lines with classification status (PENDING, CLASSIFIED, REVIEWED, REJECTED), the integral flag, suggested rule and maker and checker stamps. |
| `eir_accounting_rules` | The fee rulebook: match conditions, proposed treatment, priority, approval. |
| `eir_fee_classification_events` | Append-only history of classification decisions. |
| `eir_amortisation`, `eir_amortisation_history` | Monthly amortised-cost roll-forward per contract, and superseded rows with the reason. |
| `eir_calculation_history` | Archived solver results when a calculation is redone or reopened. |
| `gl_interest_postings` | Extract C: interest posted per contract, GL code and month. |
| `gl_trial_balance_lines` | Monthly trial balance balances by GL code and basis (pre or post closing). Balances only, never movements. |
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
