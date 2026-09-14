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

The Audit Trail page reads `activity_log` and `audit_logs` through a normalised union so both sources appear in one timeline.

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
| `imports` | Import job records with status, counts and the failed rows file path. | `name`, `status`, `records`, `failed_records`, `failed_file_path` |
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

| Table | Purpose |
|---|---|
| `macro_economic_variables` and data tables | Macro element library and their historical values. |
| `scenario_sets`, `scenario_probabilities` | Scenario profiles with base, upside and downside weights. |
| `macro_forecast_weighted` | Probability-weighted forecasts. |
| `credit_loss_data`, `credit_loss_definitions` | Historical credit loss series used for regression. |
| `regression_models` | Fitted models (coefficients, intercept) applied to convert macro shocks to PD adjustments. |
| `fli_reporting_periods_parameters`, `fli_adj` | Period parameters and management overlay adjustments. |
| `ecl_scenario_assumptions` | Approved scenario weights used by the time-phased ECL engine. |

### 3.8 Expected credit loss

| Table | Purpose |
|---|---|
| `expected_credit_loss` | ECL summary rows per reporting period, stage, calculation level and calculation id (portfolio, sector or total). Columns include `total_ead`, `total_ecl`, `pd_value_used`, `lgd_value_used`. Composite indexes on period, level and stage were added for the dashboard and report hot paths. |
| `time_phased_ecl` | Month-by-month projection rows written by the time-phased engine: PD, survival, EAD, LGD, discount factor and shortfall per scenario. |
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
