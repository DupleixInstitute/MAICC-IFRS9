## 2. Architecture

### 2.1 Request lifecycle

Every browser request follows the same path. Apache or Nginx hands the request to `public/index.php`. Laravel's router (`routes/web.php`) matches it to a controller action. The controller checks authentication and permission middleware, queries Eloquent models or services, and returns an Inertia response. Inertia renders a Vue page component from `resources/js/Pages` with the controller's data as props. Subsequent navigation is done by Inertia over XHR, so the shell layout stays mounted and only the page component and its props change.

Downloads (PDF, Excel, CSV) are ordinary HTTP responses, not Inertia responses. The navigation config marks such routes with a `download` flag so the sidebar renders a plain anchor rather than an Inertia link; an Inertia link would try to parse the binary as a page and hang.

Background work (imports, EIR calculation, revenue runs, LGD payment tracking) is dispatched to the queue and processed by `php artisan queue:work`. See chapter 9.

### 2.2 Directory layout

| Path | Contents |
|---|---|
| `app/Http/Controllers` | One controller per screen family. IFRS 9 reports sit under `Reports/`. EIR controllers are prefixed `Eir`. |
| `app/Http/Middleware` | `HandleInertiaRequests` (shared props), `SecurityHeaders`, `CheckIfUserIsActive`, plus the Jetstream and Fortify stack |
| `app/Models` | Eloquent models. Legacy credit-scoring models still exist alongside the IFRS 9 ones (see ticket #009). |
| `app/Services` | Calculation and import services: `Ecl/`, `Eir/`, `Imports/`, `Reports/`, transition matrix and macro services |
| `app/Jobs` | Queued jobs |
| `app/Console/Commands` | Artisan commands |
| `app/Exports`, `app/Imports` | maatwebsite/excel export and import classes |
| `app/Actions/Fortify` | Login pipeline actions including the CAPTCHA check |
| `config/menu.php` | The navigation tree |
| `database/migrations`, `database/seeders` | Schema history and reference data |
| `resources/js/Pages` | Vue pages, one folder per screen family |
| `resources/js/Layouts/AppLayout.vue` | The authenticated shell: sidebar, header, notification bell, loading pill, global error modal |
| `resources/js/Shared`, `resources/js/Jetstream`, `resources/js/Components` | Shared components such as `RowActions.vue`, form primitives, the help button |
| `resources/css/app.css` | The design system layer (`maiic-*` classes) |
| `resources/views` | Blade templates for PDFs and emails only |
| `docs/` | Specifications, build logs and these manuals |
| `scripts/` | Deployment script and the puppeteer capture scripts |
| `tests/` | PHPUnit feature and unit tests |

### 2.3 Navigation

The sidebar is generated from `config/menu.php`, consolidated on 9 October 2026. The file builds entries with four helpers: `$leaf` (one named route), `$section` (one menu entry whose screens are a row of tabs, each tab `$tab` being a screen's own route, optionally with query parameters such as `tab=cashflows`, so links and bookmarks keep working), and `$group` (a dropdown of leaves and sections). A top-level entry may carry a `color` for its icon and a `match` list of further route patterns that light it up. In order, the entries are:

| Entry | Contents |
|---|---|
| Dashboard, Workspace | Leaves |
| Reports | One leaf, the IFRS 9 reports hub; every report, reconciliation, export, stress testing and the auditor pack are reached from the hub and light this entry up |
| Portfolio Setup | Section: Loan Portfolios, Product Groups, Sector Types |
| Customer & Loan Data | Clients, Loan Book, and the section Imports & Feeds (Imports, E-Banker Loads, Loan Book Builds, Periods Built, Query Register, Take-on Blocks, Take-on Population) |
| Collateral Management | Section: Collateral Register, Collateral Allocation, Collateral Types |
| EIR & Revenue Recognition | Sections EIR Data, EIR Rules and EIR Calculations, and the Governance Centre |
| IFRS 9 Model Setup | Sections Staging & SICR Rules, PD Model, LGD Model |
| Forward-Looking Information | Sections FLI Month-End, Macro Statistics, Forward-Looking Model, Scenarios & Overlays (chapter 5.5) |
| ECL Processing | ECL Calculation, Mega Farm Programme |
| System Documentation | User Manual, Administrator Manual, Technical Manual, Installation Guide |
| Administration | Users & Roles, Financial Periods, Audit (Audit Trail, Audit Trace, Compliance Audits), Support Tickets, Settings, Maintenance |

There is no Legacy section: the earlier forward-looking screens are back in their places as working steps of the governed chain, and the legacy `/report` page and the legacy one-click regression screen are not linked. The header comment in the file records the rules: Reports is one entry; screens that do one job together are one entry with tabs; the report-hub tiles (Early Warning, AI Commentary, Executive Summary, the `ifrs9-reports.*` views) are never menu items.

Rules that keep the menu honest: every route named in the file must be registered (`tests/Feature/NavigationTest.php`), and an entry or tab that declares a permission is hidden from a user who lacks it. `HandleInertiaRequests::visibleMenu()` drops such entries, drops a group left with no children, keeps only the tabs of a section the user may open and points the section at the first of them, so a user never sees a link that answers 403. A `download` entry is rendered as a plain anchor (2.1).

### 2.4 Shared props

`app/Http/Middleware/HandleInertiaRequests.php` adds to every Inertia response: the signed-in user with a flattened list of permission names, the organisation currency, cached settings such as company name and logo, flash messages, and the unread notification count. Settings are cached for sixty seconds so the middleware does not query the settings table on every request. The menu and the notification dropdown are loaded lazily.

### 2.5 Design system

`resources/css/app.css` defines the shared presentation classes on top of Tailwind. The colour system is deliberately narrow: MAIIC green (`maiic-*` tokens), gold (`maiicgold-*` and Tailwind amber), red and grey. The classes a page should use are:

| Class | Purpose |
|---|---|
| `maiic-panel` | White card with border and shadow |
| `maiic-table`, `maiic-table-wrap`, `.num` | Data table with green header band, zebra rows, hover, right-aligned tabular numerals |
| `maiic-filterbar`, `maiic-flabel`, `maiic-input`, `maiic-select` | Filter bar above tables |
| `maiic-action-view`, `maiic-action-edit`, `maiic-action-delete`, `maiic-action-neutral` | Icon action buttons: green eye, gold pencil, red bin, grey |
| `maiic-badge-*` | Status pills in green, gold, red or grey |
| `maiic-kpi`, `maiic-kpi-label`, `maiic-kpi-value` | Stat tiles with a coloured left accent set through the `--accent` variable |
| `maiic-section-title` | Form section heading with a gold underline |

Legacy button and label classes (`btn-*`, `label-*`) are recoloured at the source so older pages match without edits. `resources/js/Shared/RowActions.vue` renders the standard view, edit and delete actions with permission gating.

### 2.6 Module map

| Menu group | Controllers | Principal models | Vue page folders |
|---|---|---|---|
| Dashboard, Workspace | `DashboardController`, `WorkspaceController` | `ExpectedCreditLoss`, `LoanBook`, `ReportingPeriods`, `PeriodWorkspaceTask`, `WorkspaceMessage` | `Dashboard.vue`, `Workspace/` |
| Reports | `Reports/Ifrs9ReportsController`, `Reports/StressTestingController`, `ReportsController` | `LoanBook`, `ExpectedCreditLoss`, `StressScenario`, `RegressionModel` | `Reports/`, `Reports/Ifrs9/` |
| Portfolio Setup | `LoanPortfoliosController`, `IndustryTypesController`, `LoanProductGroupsController` | `LoanPortfolio`, `IndustryType`, `LoanProductGroup` | `Portfolios/`, `IndustryTypes/`, `LoanProductGroups/` |
| Customer & Loan Data | `ClientsController`, `LoanBookController`, `ImportsController`, `GeneralImportController`, `EirFeedController`, `EirTakeonController` | `Client`, `LoanBook`, `Import` | `Clients/`, `LoanBooks/`, `Imports/` |
| Collateral Management | `CollateralController` | `Collateral`, `CollateralType`, `CollateralAllocation` | `Collateral/` |
| EIR & Revenue Recognition | `EirAccountingRuleController`, `EirDataController`, `EirIntakeController`, `EirFeeClassificationController`, `EirCalculationController`, `EirScheduleController`, `EirReconciliationController`, `EirCoverageController`, `EirDrawdownController`, `EirReferenceRateController`, `EirAsAtController`, `EirGovernanceController` | `ContractEir`, `ContractCashflowSchedule`, `ContractRemainingCashflowSchedule`, `ContractFee`, `EirAccountingRule`, `EirAmortisation`, `GlInterestPosting`, `GlTrialBalanceLine`, `GlAccountScope` | `Eir/` |
| IFRS 9 Model Setup | `StageingRulesController`, `SicrGroupController`, `SicrItemController`, `SicrTriggerController`, `TransitionProfileDefinitionController`, `TransitionMatrixController`, `TransitionMatrixCummulativeController`, `InternalGradingController`, `LossGiveDefaultController`, `LossGivenDefaultCummulativeController` | `SicrGroup`, `SicrItem`, `SicrTrigger`, `TransitionProfileDefinition`, `TransitionMatrix`, `LossGivenDefault` | `StageingRules/`, `TransitionProfileDefinitions/`, `TransitionMatrix/`, `InternalGrading/`, `LossGivenDefault/` |
| Forward-Looking Information | `FliModuleController`, `MacroStatisticsController`, `CreditLossDataController`, `FliCorrelationController`, `FliAdjustmentsController`, `ManualForecastController`, `MacroForecastWeightedController`, `ScenariosController`, `ScenarioSetController`, `FLI/ScenarioSetController`, `FliOverlayController`, `FLI/ExternalCalculationsController` | `MacroStatsDefinition`, `MacroStatsValue`, `CreditLossDefinition`, `CreditLossData`, `ScenarioProfiles`, `MacroForecastWeighted`; the governed tables are read and written by the services in `app/Services/Fli` and `app/Services/Scenario` through the query builder (chapter 5.5) | `FLI/`, `Macro/`, `Governance/ScenarioSets.vue`, `Governance/Overlays.vue` |
| ECL Processing | `ExpectedCreditLossController`, `EclProjectionController`, `MegaFarmController` | `ExpectedCreditLoss`, `LoanBook` | `ExpectedCreditLoss/`, `MegaFarm/` |
| System Documentation | `HelpController`, `HelpManagementController`, `SystemDocsController` | `HelpCategory`, `HelpArticle` and children | `Help/`, `Docs/` |
| Administration | `UsersController`, `RolesController`, `FinancialPeriodController`, `AuditTrailController`, `AuditTraceController`, `ComplianceAuditController`, `TicketsController`, `SettingsController`, `LicenseController`, `MaintenanceController` | `User`, `Role`, `Permission`, `FinancialPeriod`, `Ticket`, `TicketUpdate`, `Setting`, `License` | `Users/`, `Roles/`, `FinancialPeriods/`, `AuditTrail/`, `Audit/`, `Governance/Compliance/`, `Tickets/`, `Settings/`, `License/`, `Admin/Maintenance.vue` |

### 2.7 Conventions

- Controllers apply `auth` and `permission:<name>` middleware in their constructors; the permission name follows `module.action` (for example `tickets.update`).
- Money is stored as decimals in the organisation currency and presented in accounting format (thousands separators, two decimals, negatives in parentheses).
- Reporting periods are month-end snapshots identified as `YYYY-MM`; most screens open on the latest period that has data.
- Nothing is hardcoded that the database can supply: dropdowns are populated from tables and inputs are whitelisted against those values server-side.
- User-facing text avoids em and en dashes; the codebase is swept for them before release.
