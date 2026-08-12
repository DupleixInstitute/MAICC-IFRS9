# EIR Build — Existing vs Proposed

**Prepared:** 2026-08-07  
**Purpose:** Classify the proposed EIR and revenue-recognition build against the EIR code already implemented in `MAICC-IFRS9`.  
**Status:** Implementation mapping; this document does not change `EIR_Build.md`.

## 1. Sources reviewed

### Existing-system sources

- `docs/Development_of_EIR.md`
- `docs/EIR_Build.md`
- `docs/MAIIC_EIR_Revenue_Recognition_Engine_Spec.md`
- `docs/MAIIC_EIR_Revenue_Recognition_Engine_Spec_Explained.md`
- Current EIR migrations, models, services, jobs, controllers, routes, Vue pages, and focused tests

### Proposed-build sources supplied

- `MAIIC_EIR_Revenue_Recognition_Engine_Spec.md` from Downloads
- `MAIIC_EIR_Revenue_Recognition_Engine_Spec_Explained.md` from Downloads
- `MAIIC_EIR_Revenue_Recognition_Engine_Spec_Explained.pdf`
- `MAIIC_EIR_Revenue_Recognition_Engine_Spec_Explained.docx`
- `Claude Session Transcript - MAIIC Contract Review (2026-08-03).md`

The downloaded PDF, DOCX, and explained Markdown are alternate presentations of the same proposed design. They are reference documents, not application build files. The downloaded technical specification and session transcript contain the actionable proposed architecture.

## 2. Classification key

| Category | Meaning |
|---|---|
| **Existing — retain** | Already implemented and aligned with the proposal. Do not build a duplicate. |
| **Existing — extend** | A suitable implementation exists, but it must be expanded to meet the proposal. |
| **New — build** | No equivalent production implementation exists. |
| **Map/rename** | Proposed concept is valid, but must use the repository's established table or class name. |
| **Replace/deprecate** | Superseded approach should not be carried into the target build. |
| **Decision required** | Accounting, data, or governance approval is needed before implementation can be finalised. |
| **Reference only** | Background or explanatory material; no runtime component results from it. |

## 3. Executive conclusion

The repository already contains the EIR foundation: the core schema, contract schedule and fee intake, dynamic mapping, Extract B routing, fee/cost governance, staging configuration, a periodic IRR solver, readiness checks, and solver-input assembly.

The proposed build is therefore **not a greenfield EIR module**. It should be implemented as the next phases of the existing module:

1. Complete Extract A and Extract C ingestion.
2. Add persisted EIR run orchestration around the existing pure solver.
3. Generate and persist revenue/amortisation schedules.
4. Apply Stage 3 net-interest logic and modification/rate-reset behaviour.
5. Reconcile EIR revenue to Extract C/GL postings.
6. Generate proposed journals, reports, Excel workbooks, and audit evidence.
7. Add the run dashboard and facility drill-down UI.

Introducing the proposed `eir_facilities`, `loan_cash_flows`, `eir_runs`, and `eir_schedule_lines` tables unchanged would duplicate existing concepts and split the source of truth. The proposed names must be mapped onto the established model unless a missing grain genuinely requires a new table.

## 4. Proposed data model mapped to the existing system

| Proposed table/concept | Existing equivalent | Category | Goes into | Required treatment |
|---|---|---|---|---|
| `eir_facilities` | `contract_eir` plus joins to `loan_books`/client data | **Map/rename; existing — extend** | Existing EIR contract profile | Retain `contract_eir` as the single EIR profile. Add only verified Extract A fields that are not already available through stable joins. Do not create a second facility master. |
| `loan_cash_flows` | `contract_cashflow_schedule` for scheduled cash flows; `eir_actual_transactions` for actual Extract B transactions | **Map/rename; existing — retain/extend** | Existing scheduled/actual split | Preserve the split because scheduled lifetime cash flows and actual posted transactions have different audit meanings. Add a model for `eir_actual_transactions` if application-level relationships/query scopes require it. |
| `gl_interest_postings` | None | **New — build** | New Extract C persistence layer | Create a period-grain, facility-level GL posting table with source run IDs, posting references, import lineage, currency, and duplicate protection. |
| `eir_runs` | Run fields and solver snapshot currently centred on `contract_eir`; no complete batch/run entity | **New — build, with schema decision** | Phase 3 orchestration | Add an explicit batch/run header if one month-end run covers many facilities. Preserve per-contract solve diagnostics and immutable input snapshots. Do not lose existing lock/version semantics. |
| `eir_schedule_lines` | `eir_amortisation` | **Map/rename; existing — extend** | Existing monthly amortisation table | Use `eir_amortisation`. Extend it only for missing contractual-vs-EIR comparison, run/version linkage, misstatement lineage, and reporting fields. |
| `eir_reconciliations` | None | **New — build** | Phase 6 reconciliation | Add a persisted reconciliation table only if immutable period close evidence is required. Otherwise calculate then snapshot/export. It must distinguish EIR revenue variance from principal ECL and interest-income impairment. |
| Facility `related_party_flag`, `industry_code`, `region` | Likely available through existing client/loan-book relationships | **Existing — extend by join first** | Reporting/query layer | Prefer stable joins and snapshot the classification into a run only where audit reproducibility requires it. Avoid copying mutable master data without an as-of rule. |
| Dynamic import templates | `import_mappings`, `MappedFileReader`, `EirIntakeController`, `ProcessEirImportJob` | **Existing — extend** | Existing EIR intake | Add `extract_a` and `extract_c` types, canonical fields, aliases, validations, preview mappings, and queued import handlers. Do not build a separate `GeneralImportTemplate` EIR path. |

## 5. Proposed backend files: exact disposition

The proposed specification names conceptual files. The target below identifies whether each should be created or mapped to existing code.

| Proposed file/component | Category | Existing/target file | Decision |
|---|---|---|---|
| `App\Services\Eir\EirSolverService` | **Map/rename; existing — extend** | `app/Services/Eir/CalculateEirService.php` | Keep the existing tested Newton–Raphson solver with bisection fallback. Add orchestration and governed plausibility handling around it; do not create a competing solver. |
| Solver input assembly | **Existing — retain** | `app/Services/Eir/EirContractInputService.php` | Already assembles ordered original cash flows and reviewed integral fees/costs. Extend for verified Extract A/B semantics only. |
| Solver readiness gate | **Existing — retain/extend** | `app/Services/Eir/EirReadinessService.php` | Retain named blocking reasons. Add Extract A completeness, Extract C/run-period prerequisites where appropriate, and accounting convention checks. |
| `CalculateEirJob` / per-facility solve job | **New — build** | Suggested `app/Jobs/CalculateEirJob.php` | Call the existing input and solver services, persist result/diagnostics/input snapshot atomically, enforce idempotency and locks, and expose failures without silently defaulting rates. |
| Month-end batch EIR job | **New — build** | Suggested `app/Jobs/RunEirPeriodJob.php` | Coordinate a run across eligible facilities, record counts/statuses, and allow safe retry. Keep batch identity separate from an individual facility solve. |
| `php artisan maiic:run-eir {period}` | **New — build** | Suggested console command delegating to the batch job | Validate the period and dispatch the same application workflow used by the UI. |
| Extract A importer | **New — build using existing intake** | Suggested `app/Imports/ExtractAImport.php` and `app/Services/Eir/ExtractAImportService.php` | Map facility terms into `contract_eir` and linked master data. Hold unknown/ambiguous contracts and retain source lineage. |
| Extract B importer | **Existing — retain/extend** | `app/Imports/ExtractBImport.php`, `app/Services/Eir/ExtractBImportService.php`, `app/Jobs/ProcessEirImportJob.php` | Already splits scheduled, actual, and fee rows. Apply the pending migration and verify delivered file semantics, especially DR/CR signs and scheduled/actual values. |
| Extract C importer | **New — build using existing intake** | Suggested `app/Imports/ExtractCImport.php` and `app/Services/Eir/ExtractCImportService.php` | Persist facility/period GL interest totals and references. Validate uniqueness, period, currency, account ownership, and sign conventions. |
| Schedule generator | **Existing — retain** | `app/Services/Eir/ScheduleGeneratorService.php` | Remains the Tier-2 fallback where an authoritative lifetime schedule is unavailable. Imported original schedules continue to win. |
| Fee classification/matching | **Existing — retain** | `FeeImportService`, `FeeRuleMatcher`, accounting-rule and classification services/controllers | The proposed design understated this requirement; the repository's maker/checker treatment is stronger and must remain. |
| `App\Services\Reports\EirReconciliationService` | **New — build** | Same proposed location/name is suitable | Compare period EIR revenue with Extract C, identify differences using governed thresholds, roll up by portfolio, and retain facility-level evidence. |
| `App\Services\Reports\EirRevenueExportService` | **New — build** | Same proposed location/name is suitable | Produce recognised revenue by facility, period, stage, gross/net basis, and contractual-vs-EIR basis. |
| Proposed journal builder | **New — build** | Prefer a separate `EirJournalProposalService` used by reconciliation/export | Generate balanced, reviewable proposed entries. It must never post directly to E-Banker/the GL. Account mapping and debit/credit signs require Finance approval. |
| Revenue calculation job | **New — build** | Planned `RunEirRevenueJob` or a domain service plus job | Write versioned `eir_amortisation` rows, use gross carrying amount for Stages 1–2 and net carrying amount for Stage 3, and identify unwind separately. |
| Modification handling | **New — build** | Revenue/amortisation domain service | Implement modification gain/loss and schedule versioning; define treatment through an approved accounting memo first. |
| Floating-rate reset processing | **Existing schema — extend behaviour** | `rate_reset_events`, `RateResetEvent` | Schema exists, but event capture, schedule regeneration, approvals, and audit reporting remain to be built. |
| ECL discount rewiring | **Existing ECL code — extend** | Existing ECL controller/jobs/services | Replace hard-coded/fallback discount rates with governed original/current EIR rules. This is Phase 4 and must be regression-tested separately. |

## 6. Proposed UI, routes, and permissions

| Proposed UI/route | Category | Goes into | Required treatment |
|---|---|---|---|
| `EirImportController` and `/eir/imports/*` | **Replace proposal with existing route pattern** | `EirIntakeController`, `eir-intake.*`, `Eir/Intake.vue` | Extend the existing single mapped-intake workflow with Extract A and C. Do not create three rigid upload endpoints that bypass mapping/history. |
| `EirRunController` | **New — build** | New operational controller | Thin controller for run listing, readiness summary, dispatch, retry where safe, and run status. |
| `EirFacilityController` | **New — build** | New operational controller | Facility list/drill-down showing inputs, solved EIR, diagnostics, schedule versions, revenue rows, reconciliation, and exceptions. |
| `Eir/Index.vue` | **New — build** | EIR operational area | Run dashboard by reporting period with ready/blocked/processing/completed/failed counts. |
| `Eir/Facilities/Index.vue` | **New — build** | EIR operational area | Searchable/filterable facility results and exception queue. |
| `Eir/Facilities/Show.vue` | **New — build** | EIR operational area | Full audit drill-down; never show only a rate without its basis, frequency, source, version, and diagnostics. |
| `Eir/Intake.vue` | **Existing — extend** | Existing page | Add Extract A/C schemas and results. Extract B is already present. |
| Accounting rules page | **Existing — retain** | `Eir/AccountingRules.vue` | Preserve approval and activation workflow. |
| Fee classification page | **Existing — retain** | `Eir/FeeClassification.vue` | Preserve maker/checker history; this gates solver readiness. |
| Report routes in `ReportsController` | **New — build by extending existing hub** | Existing `report` route group/controller | Add revenue recognition, EIR reconciliation, and workbook export methods/routes. |
| Report Vue pages | **New — build** | `resources/js/Pages/Reports/...` | Add filters, reconciliation summaries, exception drill-down, and controlled exports. |
| Sidebar/report navigation | **Existing — extend** | Existing menu and Reports index | Add one EIR operational entry and report entries without duplicating intake/classification links. |
| `view-eir`, `run-eir`, `export-eir` | **New — build after role mapping decision** | Existing permission framework | Add explicit permissions and seed/assign them to agreed roles. Include separate approval permissions if journal/rule approval demands segregation. |

## 7. Existing files that remain authoritative

These files are already part of the chosen architecture and should be extended in place where necessary:

- `app/Services/Eir/CalculateEirService.php`
- `app/Services/Eir/EirContractInputService.php`
- `app/Services/Eir/EirReadinessService.php`
- `app/Services/Eir/ScheduleGeneratorService.php`
- `app/Services/Eir/ScheduleImportService.php`
- `app/Services/Eir/ExtractBImportService.php`
- `app/Services/Eir/FeeImportService.php`
- `app/Services/Eir/FeeRuleMatcher.php`
- `app/Services/Eir/StagingClassifier.php`
- `app/Services/Imports/MappedFileReader.php`
- `app/Jobs/ProcessEirImportJob.php`
- `app/Http/Controllers/EirIntakeController.php`
- `app/Http/Controllers/EirAccountingRuleController.php`
- `app/Http/Controllers/EirFeeClassificationController.php`
- `app/Models/ContractEir.php`
- `app/Models/ContractCashflowSchedule.php`
- `app/Models/ContractFee.php`
- `app/Models/EirAmortisation.php`
- `app/Models/RateResetEvent.php`
- `app/Models/ImportMapping.php`
- `app/Models/StagingThreshold.php`
- `resources/js/Pages/Eir/Intake.vue`
- `resources/js/Pages/Eir/AccountingRules.vue`
- `resources/js/Pages/Eir/FeeClassification.vue`

## 8. Proposed new files, grouped by category

Names below are recommended targets, not a requirement to create empty scaffolding before the related design is settled.

### 8.1 Data ingestion

- `app/Imports/ExtractAImport.php`
- `app/Imports/ExtractCImport.php`
- `app/Services/Eir/ExtractAImportService.php`
- `app/Services/Eir/ExtractCImportService.php`
- migrations/models for GL interest postings and any missing Extract A attributes
- focused mapping, validation, duplicate, sign, and ownership tests

### 8.2 Solver orchestration

- `app/Jobs/CalculateEirJob.php`
- `app/Jobs/RunEirPeriodJob.php`
- console command for `maiic:run-eir`
- run/batch model and migration if the explicit batch design is adopted
- persistence, locking, retry, idempotency, and failure-isolation tests

### 8.3 Revenue recognition

- revenue/amortisation calculation service
- `app/Jobs/RunEirRevenueJob.php`
- Stage 3 net-interest, cure, unwind, modification, and reset tests
- extensions to `eir_amortisation` rather than a duplicate schedule-lines table

### 8.4 Reconciliation and audit output

- `app/Services/Reports/EirReconciliationService.php`
- `app/Services/Reports/EirRevenueExportService.php`
- `app/Services/Eir/EirJournalProposalService.php`
- Extract C reconciliation and balanced-journal tests
- Excel workbook/export classes and golden workbook assertions
- optional immutable reconciliation snapshot migration/model

### 8.5 Operational UI

- `app/Http/Controllers/EirRunController.php`
- `app/Http/Controllers/EirFacilityController.php`
- `resources/js/Pages/Eir/Index.vue`
- `resources/js/Pages/Eir/Facilities/Index.vue`
- `resources/js/Pages/Eir/Facilities/Show.vue`
- report pages and additions to the existing Reports controller/routes

## 9. Items that must not be duplicated

| Do not introduce | Use instead | Reason |
|---|---|---|
| A second EIR facility master | `contract_eir` plus existing master-data joins | Prevents competing contract profiles and rates. |
| A single ambiguous `loan_cash_flows` table | `contract_cashflow_schedule` plus `eir_actual_transactions` | Preserves scheduled-vs-actual provenance. |
| A second IRR solver | `CalculateEirService` | Existing solver and golden result are already tested. |
| A bespoke rigid A/B/C uploader | `EirIntakeController` + `MappedFileReader` + queued import history | Existing mapping/retry/audit workflow is designed for changing client headers. |
| Automatic classification of imported fees as integral | Existing PENDING → classified → independently REVIEWED workflow | IFRS 9 treatment requires governed judgement and audit history. |
| Direct GL posting | Proposed journal export/review only | The agreed scope is recommendation, not E-Banker write access. |
| Silent zero/default EIR for unsolved facilities | Readiness block and named exception | A zero/default rate would create unauditable revenue and ECL results. |
| Hard-coded stage or materiality decisions | Governed configuration with effective dates and approvals | Maintains explainability and repeatability. |

## 10. Decisions and blockers

| Decision | Affects | Status/action |
|---|---|---|
| Day-count, solve period, compounding, and annualisation conventions | Solver persistence, revenue schedules, exports | **Approval required** before results are labelled final. |
| Extract B debit/credit and fee sign semantics | Actual cash flows and fee adjustments | Verify against delivered data and Barry's mapping. |
| Extract C fee/commission content and sign conventions | Reconciliation and journals | Confirm delivered columns and GL interpretation. |
| Original lifetime schedule availability versus partial 2025 history | True origination EIR | Partial actual history cannot be presented as the original lifetime schedule. |
| Stage 3 net carrying amount source and as-of stage/ECL snapshot | Revenue recognition | Define the exact existing ECL output and period join. |
| Journal account mapping and materiality threshold | Reconciliation output | Finance/auditor approval required; keep configurable and versioned. |
| Keyman insurance integral treatment | Solver input | Accounting decision required. |
| Nascomex preference-share classification | Instrument scope | IAS 32 memo required. |
| Floating-rate reset policy | Rate-reset behaviour | Define when the EIR stays fixed versus cash-flow estimates are revised. |
| Permissions and segregation of duties | UI and approvals | Map view/run/export/approve actions to existing roles. |

## 11. Recommended implementation order

| Order | Work package | Category | Completion gate |
|---:|---|---|---|
| 1 | Apply/verify the Extract B audit migration and run focused EIR tests | Existing completion | Extract B schedule/actual/fee persistence works on the target database. |
| 2 | Profile the actual Extract A/B/C files and approve field/sign mappings | Data decision | Required columns, keys, signs, periods, and duplicates documented. |
| 3 | Add Extract A and Extract C through the existing mapped intake | New + extend | Imports are idempotent, traceable, and produce named held/rejected rows. |
| 4 | Approve EIR conventions and required accounting decisions | Decision | Solver labels and inputs are governance-approved. |
| 5 | Build run/batch orchestration around the existing solver | New | Repeatable persisted results with snapshots, diagnostics, locks, and safe retry. |
| 6 | Build versioned revenue/amortisation processing | New + extend | Gross/net interest, cash, unwind, modifications, and closing balances reconcile. |
| 7 | Build Extract C reconciliation and journal proposals | New | Facility and portfolio totals tie; every proposed journal is balanced. |
| 8 | Build revenue/reconciliation exports and audit workbook | New | Full-book monthly output and auditor-comparable yearly output are reproducible. |
| 9 | Build dashboard, drill-down, reports, permissions, and navigation | New + extend | Users can operate and review the process without bypassing controls. |
| 10 | Rewire ECL discounting and complete regression/golden-number testing | Existing ECL — extend | No silent fallback discount rates; approved ECL results remain explainable. |

## 12. Definition of done

The proposed build is complete only when:

- every eligible facility has either a reproducible EIR result or a named blocking exception;
- the solved rate retains its input cash flows, fee decisions, frequency, convention, residual, iterations, source versions, and approval/lock history;
- monthly EIR revenue rolls opening carrying amount to closing carrying amount;
- Stage 3 interest uses the approved net-carrying-amount rule and is separately disclosed;
- Extract C is reconciled per facility and period to computed EIR revenue;
- all proposed journal entries balance and remain proposals for authorised review;
- full-book monthly and auditor-comparable yearly workbooks can be regenerated from stored data;
- imports, reruns, corrections, modifications, and rate resets are versioned rather than overwritten;
- UI permissions preserve segregation between preparation, classification, review, running, and export;
- focused unit/feature tests and agreed golden cases pass.

## 13. Source-document disposition

| Supplied document | Category | Use in the build |
|---|---|---|
| Downloaded technical specification Markdown | **Reference plus proposed requirements** | Primary source for proposed tables, services, routes, reports, and routine operation; reconciled through this document to the current implementation. |
| Downloaded explained Markdown | **Reference only** | Business explanation and stakeholder communication; not an independent architecture. |
| Downloaded explained PDF | **Reference only** | Portable rendering of the explainer. |
| Downloaded explained DOCX | **Reference only** | Editable rendering of the explainer. |
| Claude session transcript | **Reference and provenance** | Confirms delivered extracts, contractual commitments, proposed plan, and discovery context; not a source of runtime truth. |
| Repository consolidated technical specification | **Current target specification** | More current description of what is already built and what remains. |
| `Development_of_EIR.md` | **Implementation progress record** | Evidence of completed phases, verification, incidents, and pending work. |
| `EIR_Build.md` | **Original phased build plan** | Retained unchanged; this comparison supplements it. |

