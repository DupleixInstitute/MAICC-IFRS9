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

The sidebar is generated from `config/menu.php`. Each entry is either a group (with children) or a leaf pointing at a named route. Groups mirror the contract's solution components: Reports, Portfolio Setup, Customer and Loan Data, Collateral Management, EIR and Revenue Recognition, IFRS 9 Model Setup (with nested Staging, PD, LGD, Forward-Looking and Management Overlay groups), ECL Processing, System Documentation and Administration. The header comment in the file records the deduplication rules applied during the August 2026 navigation audit, for example that Early Warning and AI Commentary are tiles inside the reports hub and not menu items.

Rules that keep the menu honest: every leaf must be a registered route, and items a user is not permitted to see are hidden by the sidebar's permission check rather than left to fail with a 403.

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
| Customer and Loan Data | `ClientsController`, `LoanBookController`, `ImportsController`, `GeneralImportController` | `Client`, `LoanBook`, `Import` | `Clients/`, `LoanBooks/`, `Imports/` |
| Collateral Management | `CollateralController` | `Collateral`, `CollateralType`, `CollateralAllocation` | `Collateral/` |
| EIR and Revenue Recognition | `EirAccountingRuleController`, `EirDataController`, `EirIntakeController`, `EirFeeClassificationController`, `EirCalculationController`, `EirScheduleController`, `EirReconciliationController`, `EirCoverageController` | `ContractEir`, `ContractCashflowSchedule`, `ContractRemainingCashflowSchedule`, `ContractFee`, `EirAccountingRule`, `EirAmortisation`, `GlInterestPosting`, `GlTrialBalanceLine`, `GlAccountScope` | `Eir/` |
| IFRS 9 Model Setup | `StageingRulesController`, `SicrGroupController`, `SicrItemController`, `SicrTriggerController`, `TransitionProfileController`, `TransitionMatrixController`, `TransitionMatrixCummulativeController`, `InternalGradingController`, `LossGiveDefaultController`, `LossGivenDefaultCummulativeController`, `MacroStatisticsController`, `ScenarioController`, `MacroForecastWeightedController`, `CreditLossDataController`, `ManualForecastController`, `RegressionController`, FLI controllers | `SicrGroup`, `SicrItem`, `SicrTrigger`, `TransitionProfile`, `TransitionMatrix`, `LossGivenDefault`, `MacroEconomicVariable`, `ScenarioSet`, `RegressionModel` | `StageingRules/`, `TransitionProfiles/`, `TransitionMatrix/`, `InternalGrading/`, `LossGivenDefault/`, `FLI/`, `Regression/` |
| ECL Processing | `ExpectedCreditLossController`, `EclProjectionController` | `ExpectedCreditLoss`, `LoanBook` | `ExpectedCreditLoss/` |
| System Documentation | `HelpController`, `HelpManagementController`, `SystemDocsController` | `HelpCategory`, `HelpArticle` and children | `Help/`, `Docs/` |
| Administration | `UsersController`, `RolesController`, `FinancialPeriodsController`, `AuditTrailController`, `TicketsController`, `SettingsController`, `LicenseController` | `User`, `Role`, `Permission`, `FinancialPeriod`, `Ticket`, `TicketUpdate`, `Setting`, `License` | `Users/`, `Roles/`, `FinancialPeriods/`, `AuditTrail/`, `Tickets/`, `Settings/`, `License/` |

### 2.7 Conventions

- Controllers apply `auth` and `permission:<name>` middleware in their constructors; the permission name follows `module.action` (for example `tickets.update`).
- Money is stored as decimals in the organisation currency and presented in accounting format (thousands separators, two decimals, negatives in parentheses).
- Reporting periods are month-end snapshots identified as `YYYY-MM`; most screens open on the latest period that has data.
- Nothing is hardcoded that the database can supply: dropdowns are populated from tables and inputs are whitelisted against those values server-side.
- User-facing text avoids em and en dashes; the codebase is swept for them before release.
