# Reference logic for verifying spec v4

Verbatim copies of the files the specification (`docs/MAIIC_EIR_Engine_Specification_v4_2026-10-07.md`) cites as the source of a design, taken from Dupleix's own repositories at the commits in `manifest.json`. They are here so that a reviewer can check each section of the specification against the code it was written from, and so that the build can port rather than reinvent. Code only: no client data, no logos, no documents of another client.

| Source | Repository | Commit |
|---|---|---|
| `znbs-suite` | `C:\xampp\htdocs\Stress-Testing-App` | `5be6d97a3984` |
| `fdh-ifrs9-laravel` | `C:\xampp\htdocs\FDH_IFRS9_Laravel` | `98de97db4f56` |
| `fdh-ifrs9-plain-php` | `C:\xampp\htdocs\ifrs9` | `09e8e7268f2e` |

## By specification section

### Section 6.4

| File | What to verify |
|---|---|
| `znbs-suite/app/Models/SourceImportBatch.php` | Provenance of an import: source, address, fetched-at, who, rows, hash |
| `znbs-suite/database/migrations/2026_05_20_185622_create_source_import_manifest_tables.php` | Manifest and batch tables for landed files |

### Section 6.5 route 1

| File | What to verify |
|---|---|
| `znbs-suite/app/Console/Commands/ImportZnbsLoanListingCommand.php` | A committed client input landed by command, idempotent on the file |

### Section 6.10

| File | What to verify |
|---|---|
| `znbs-suite/app/Console/Commands/IcaapBootstrapCommand.php` | One-command bootstrap: --fresh safety, committed inputs read first, steps, automated-approver label, --verify golden table |

### Section 11

| File | What to verify |
|---|---|
| `fdh-ifrs9-laravel/docs/SCOPING_NOTES.md` | Scoping notes B4/B5: the suite grouping convention |

### Section 11.2

| File | What to verify |
|---|---|
| `fdh-ifrs9-laravel/resources/js/nav.js` | The navigation tree as single source of truth: groups, accents, items, routes |
| `fdh-ifrs9-laravel/resources/js/Layouts/AppLayout.vue` | Shell: sidebar collapse with localStorage, mobile drawer, page header with breadcrumb derived from the tree, busy pill, per-page help |
| `fdh-ifrs9-laravel/resources/js/Components/Shell/Sidebar.vue` | Accent-coloured groups, one open at a time, active bar, icon rail |
| `fdh-ifrs9-laravel/resources/js/Components/Shell/Topbar.vue` | Menu toggle, period chip, theme toggle, notifications, user menu |
| `fdh-ifrs9-laravel/resources/js/Components/Shell/Icon.vue` | Icon set used by the shell |
| `fdh-ifrs9-laravel/resources/js/Components/Shell/StatCard.vue` | Dashboard stat card |
| `fdh-ifrs9-laravel/resources/js/Components/Shell/EmptyState.vue` | Empty state |
| `fdh-ifrs9-laravel/resources/js/Components/CollapsibleHelp.vue` | Per-page collapsible help panel |
| `fdh-ifrs9-laravel/resources/js/Components/ManualHelpButton.vue` | Floating help button |

### Section 11.3

| File | What to verify |
|---|---|
| `fdh-ifrs9-laravel/resources/js/nav.js` | The navigation tree as single source of truth: groups, accents, items, routes |

### Section 11.4

| File | What to verify |
|---|---|
| `fdh-ifrs9-laravel/resources/js/Layouts/AppLayout.vue` | Shell: sidebar collapse with localStorage, mobile drawer, page header with breadcrumb derived from the tree, busy pill, per-page help |
| `fdh-ifrs9-laravel/resources/js/Components/Shell/Sidebar.vue` | Accent-coloured groups, one open at a time, active bar, icon rail |

### Section 11.5

| File | What to verify |
|---|---|
| `fdh-ifrs9-laravel/resources/js/Components/Shell/Topbar.vue` | Menu toggle, period chip, theme toggle, notifications, user menu |
| `fdh-ifrs9-laravel/resources/js/composables/useTheme.js` | Light/dark: class mode, localStorage, one shared ref |
| `fdh-ifrs9-laravel/resources/views/app.blade.php` | The no-flash inline script that sets the dark class before paint |
| `fdh-ifrs9-laravel/tailwind.config.js` | darkMode: 'class' |

### Section 12.3

| File | What to verify |
|---|---|
| `znbs-suite/tools/compliance/audit_workbook.py` | Contents / Audit / Findings sheets, nine columns, statuses, validation that refuses a misleading workbook |

### Section 12.4

| File | What to verify |
|---|---|
| `znbs-suite/tools/compliance/audit_workbook.py` | Contents / Audit / Findings sheets, nine columns, statuses, validation that refuses a misleading workbook |

### Section 12.5

| File | What to verify |
|---|---|
| `znbs-suite/resources/js/Pages/Audit/Trace.vue` | Per-record audit trace, oldest to newest |
| `znbs-suite/resources/js/Pages/Audit/Index.vue` | Audit index |

### Section 12.6

| File | What to verify |
|---|---|
| `znbs-suite/tools/compliance/build_audit.py` | One data module to three outputs |
| `znbs-suite/tools/compliance/md_to_pdf.py` | Markdown twin to PDF |
| `znbs-suite/tools/compliance/directives/__init__.py` | Directive module registry |
| `znbs-suite/tools/compliance/directives/notice_1198_icaap.py` | An example data module: META, ROWS, FINDINGS (content is BoZ-specific; the shape is what MAIIC reuses) |

### Section 13

| File | What to verify |
|---|---|
| `fdh-ifrs9-laravel/app/Services/Fli/MacroModule.php` | Module wiring |

### Section 13.3

| File | What to verify |
|---|---|
| `znbs-suite/app/Models/SourceImportBatch.php` | Provenance of an import: source, address, fetched-at, who, rows, hash |
| `znbs-suite/app/Services/Macro/WorldBankFetcherService.php` | World Bank fetch: endpoint, timeout, retries, country, year range, preview payload |
| `znbs-suite/app/Services/Macro/ImfWeoParserService.php` | WEO parser: UTF-16 tab file, country filter, estimates-start year to actual/forecast |
| `znbs-suite/app/Console/Commands/ImportWorldBankMacroCommand.php` | macro:import-worldbank, non-fatal per indicator |
| `znbs-suite/database/seeders/MacroExternalCodesSeeder.php` | Indicator codes on the variable |
| `znbs-suite/app/Http/Controllers/MacroController.php` | Preview and commit endpoints for both sources; CSV template and export |
| `znbs-suite/app/Models/MacroVariable.php` | external_codes |
| `znbs-suite/app/Models/MacroObservation.php` | Observation keyed on variable, period, period type |
| `znbs-suite/app/Models/MacroImportLog.php` | Import log |
| `znbs-suite/database/migrations/2026_03_30_100001_create_macro_variables_table.php` | macro_variables |
| `znbs-suite/database/migrations/2026_03_30_100002_create_macro_observations_table.php` | macro_observations |
| `znbs-suite/resources/js/Pages/Macro/Index.vue` | The five-tab screen; the governed assumptions tab with approval lineage |

### Section 13.4

| File | What to verify |
|---|---|
| `znbs-suite/database/seeders/MacroExternalCodesSeeder.php` | Indicator codes on the variable |

### Section 14

| File | What to verify |
|---|---|
| `fdh-ifrs9-laravel/app/Services/Fli/MacroModule.php` | Module wiring |
| `fdh-ifrs9-laravel/docs/SCOPING_NOTES.md` | Scoping notes B4/B5: the suite grouping convention |
| `fdh-ifrs9-plain-php/README.md` | The plain-PHP app's pipeline: PD, LGD, EAD, FLI, ECL |

### Section 14.4

| File | What to verify |
|---|---|
| `fdh-ifrs9-laravel/app/Services/Fli/CorrelationFinder.php` | Auto-correlate: X x Y x lag grid, Spearman, Theil-Sen, Pearson, OLS, ranking, reasons, immutable runs, fail-closed |
| `fdh-ifrs9-laravel/app/Services/Fli/Diagnostics.php` | Normality, break window, overlap diagnostics |
| `fdh-ifrs9-laravel/app/Services/Fli/Guardrail.php` | Guardrails on what may be approved |
| `fdh-ifrs9-laravel/app/Services/Fli/CreditLossProxyDeriver.php` | Deriving the credit-loss proxies (Y series) |
| `fdh-ifrs9-laravel/app/Services/Fli/FliSeriesProfiler.php` | Series profiling |
| `fdh-ifrs9-laravel/app/Services/Fli/StatisticsSidecar.php` | Statistics helpers |
| `fdh-ifrs9-plain-php/calculator/regression/regression_calculator.php` | The original correlation tests: expected sign, R2 cut-off, red/green, slope and intercept |
| `fdh-ifrs9-plain-php/calculator/regression/fetch_regression_results.php` | Results fetch |
| `fdh-ifrs9-plain-php/maintenance/regression_definitions/regression_definitions.php` | Regression definitions: statistic, credit-loss code, expected sign, R2 cut-off |
| `fdh-ifrs9-plain-php/maintenance/regression_definitions/fetch_regression_definitions.php` | Definitions fetch |
| `fdh-ifrs9-plain-php/MacroStatisticsRegressionDefinitions.htm` | Definitions page |

### Section 14.5

| File | What to verify |
|---|---|
| `fdh-ifrs9-laravel/app/Services/Fli/RegressionEngine.php` | Regression with the full coefficient vector |
| `fdh-ifrs9-laravel/app/Services/Fli/Guardrail.php` | Guardrails on what may be approved |

### Section 14.6

| File | What to verify |
|---|---|
| `fdh-ifrs9-laravel/app/Services/Fli/AdjustmentMethodEngine.php` | The adjustment methods |
| `fdh-ifrs9-laravel/app/Services/Fli/StructuralEventsRegister.php` | Register of structural events (devaluations, droughts) used as anchors |
| `fdh-ifrs9-laravel/resources/js/Pages/Governance/FliRegisters.vue` | The FLI registers screen (suggestions, models, overlays) |

### Section 14.8

| File | What to verify |
|---|---|
| `fdh-ifrs9-laravel/app/Http/Controllers/CalculatorController.php` | The FLI calculator endpoints |
| `fdh-ifrs9-laravel/resources/js/Pages/Calculators/Fli.vue` | The FLI / Correlation Finder screen |
| `fdh-ifrs9-laravel/resources/js/Pages/Governance/FliRegisters.vue` | The FLI registers screen (suggestions, models, overlays) |

### Section 15.4

| File | What to verify |
|---|---|
| `znbs-suite/resources/js/Pages/Macro/Index.vue` | The five-tab screen; the governed assumptions tab with approval lineage |
| `fdh-ifrs9-laravel/app/Services/Fli/StructuralEventsRegister.php` | Register of structural events (devaluations, droughts) used as anchors |
| `znbs-suite/database/migrations/2026_03_30_400001_create_scenarios_table.php` | scenarios |
| `znbs-suite/database/migrations/2026_03_30_400002_create_scenario_shocks_table.php` | Shocks: pct_change, absolute, replace, multiplier, by variable and year offset |
| `znbs-suite/database/migrations/2026_04_02_100001_extend_scenario_engine.php` | Scenario engine extension |
| `znbs-suite/database/migrations/2026_05_21_260000_step19_scenario_governance.php` | Scenario governance: approval, lock |
| `znbs-suite/database/migrations/2026_05_12_140000_create_scenario_parameter_library_table.php` | Parameter library |
| `znbs-suite/app/Http/Controllers/ScenarioController.php` | Scenario CRUD, propose, approve, lock |
| `znbs-suite/app/Http/Controllers/ScenarioSupportingDocumentController.php` | Evidence attachments on a scenario |
| `znbs-suite/app/Models/ApprovalAuditLog.php` | Approval audit log |

### Section 15.6

| File | What to verify |
|---|---|
| `fdh-ifrs9-laravel/app/Services/Fli/ScenarioWeightValidator.php` | Scenario weight validation |
| `znbs-suite/database/migrations/2026_05_21_260000_step19_scenario_governance.php` | Scenario governance: approval, lock |

### Section 15.9

| File | What to verify |
|---|---|
| `znbs-suite/app/Http/Controllers/ScenarioController.php` | Scenario CRUD, propose, approve, lock |
