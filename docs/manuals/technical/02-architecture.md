## 2. Architecture

### 2.1 Request lifecycle

Every browser request follows the same path. Apache or Nginx hands the request to `public/index.php`. Laravel's router (`routes/web.php`) matches it to a controller action. The controller checks authentication and permission middleware, queries Eloquent models or services, and returns an Inertia response. Inertia renders a Vue page component from `resources/js/Pages` with the controller's data as props. Subsequent navigation is done by Inertia over XHR, so the shell layout stays mounted and only the page component and its props change.

Downloads (PDF, Excel, CSV) are ordinary HTTP responses, not Inertia responses. The navigation config marks such routes with a `download` flag so the sidebar renders a plain anchor rather than an Inertia link; an Inertia link would try to parse the binary as a page and hang.

Background work (imports, EIR calculation, revenue runs, LGD payment tracking) is dispatched to the queue and processed by `php artisan queue:work`. The long console jobs started from a screen (Administration & support, Maintenance, and the FLI Month-End Overview) are not queued: `app/Services/Maintenance/CommandRunner.php` launches the whitelisted artisan command as a detached background process, one at a time, and writes its output under `storage/app/command-runs`. See chapter 9.

### 2.2 Directory layout

| Path | Contents |
|---|---|
| `app/Http/Controllers` | One controller per screen family. IFRS 9 reports sit under `Reports/`. EIR and E-Banker feed controllers are prefixed `Eir`. |
| `app/Http/Middleware` | `HandleInertiaRequests` (shared props), `SecurityHeaders`, `CheckIfUserIsActive`, plus the Jetstream and Fortify stack |
| `app/Models` | Eloquent models. Legacy credit-scoring models still exist alongside the IFRS 9 ones (see ticket #009). |
| `app/Services` | Calculation and import services, one folder per area: `Ebanker/` (landing zone, packs, take-on, loan book build, write-off classifier, GL fee route), `Eir/`, `Ecl/`, `Pd/`, `Lgd/`, `Ifrs9/`, `Fli/`, `Scenario/`, `Macro/`, `MegaFarm/`, `Rbm/`, `Reports/`, `Stress/`, `Compliance/`, `Maintenance/`, `Imports/`, plus the transition matrix and regression services |
| `app/Jobs` | Queued jobs |
| `app/Console/Commands` | Artisan commands |
| `app/Exports`, `app/Imports` | maatwebsite/excel export and import classes |
| `app/Actions/Fortify` | Login pipeline actions including the CAPTCHA check |
| `app/Support` | Shared helpers: `Breadcrumbs` and `SectionTabCounts` (navigation), `ReportingPeriodLock` (the period lock every writer checks), `LedgerReadLimits`, `ReportDownload`, `Tenor`, and `Imports/` (`ImportSpecs`, `ImportSpecValidator`, `ImportTemplateBuilder`: the sample file, column guide and fail-closed check of every import) |
| `config/menu.php` | The navigation tree |
| `database/migrations`, `database/seeders` | Schema history and reference data |
| `resources/js/Pages` | Vue pages, one folder per screen family |
| `resources/js/Layouts/AppLayout.vue` | The authenticated shell: sidebar, header, notification bell, loading pill, global error modal |
| `resources/js/Shared`, `resources/js/Jetstream`, `resources/js/Components` | Shared components such as `RowActions.vue`, form primitives, the help button |
| `resources/css/app.css` | The design system layer (`maiic-*` classes) |
| `resources/views` | Blade templates for PDFs and emails only |
| `docs/` | Specifications, build logs and these manuals |
| `scripts/` | `deploy.sh` (production deployment), `manual-screenshots.cjs` (the headless capture behind `manual:screenshots`) and the EIR explainer document generator |
| `tests/` | PHPUnit feature and unit tests |

### 2.3 Navigation

The sidebar is generated from `config/menu.php`, consolidated on 9 October 2026 and regrouped on 13 October 2026. The file builds entries with four helpers: `$leaf` (one named route, optionally with a `download` flag and a permission), `$tab` (one tab of a section: a screen's own route, optionally with a permission and query parameters such as `tab=cashflows`, so links and bookmarks keep working), `$section` (one menu entry whose screens are a row of tabs; it opens the first tab) and `$group` (a folding entry of leaves, sections and further groups). A top-level entry may carry a `color` for its icon, a `match` list of further route patterns that light it up, and a `heading` that names the sidebar block it sits in.

Dashboard, Workspace and Reports carry no heading: they sit at the top of the sidebar and are always shown. Every other entry carries one of four block headings, and the sidebar prints each heading, with its icon and in the block's own soft colour, above the first entry of its block. The heading is on every entry, so a block keeps its heading when a permission hides its first entry. In order, the entries are:

| Block | Entry | Contents |
|---|---|---|
| Top, always shown | Dashboard, Workspace | Leaves |
| Top, always shown | Reports | One leaf, the IFRS 9 reports hub. Every report, reconciliation, export, stress testing, the RBM return and the auditor pack are reached from the hub and light this entry up (`match`: `ifrs9-reports.*`, `reports.*`, `stress-testing.*`, `rbm-return.*`, `auditor-pack.*`) |
| Data foundation | Portfolio Setup | Section: Loan Portfolios, Product Groups, Sector Types |
| Data foundation | Customer & Loan Data | Clients, Loan Book, and the section Imports & Feeds (Imports, E-Banker Loads, Loan Book Builds, Periods Built, Query Register, Take-on Blocks, Take-on Population) |
| Data foundation | Collateral Management | Section: Collateral Register, Collateral Allocation, Collateral Types |
| Financial modelling | EIR & Revenue Recognition | Section EIR Data (Contract Master, Cash Flows, Drawdowns, Reference Rates, Trial Balances, Write-offs, Restructures & Resets, Schedule Review), section EIR Rules (Accounting Rules, Fee Classification) and section EIR Calculations (Calculations, Blockers, Coverage by Portfolio, Largest Facilities, EIR as at a Date, GL Reconciliation) |
| Financial modelling | IFRS 9 Model Setup | Sections Staging & SICR Rules, PD Model (including Internal Grades) and LGD Model |
| Financial modelling | Forward-Looking Information | Sections FLI Month-End, Macro Statistics, Forward-Looking Model, Scenarios & Overlays (chapter 5.5) |
| Risk & regulatory | ECL Processing | ECL Calculation, Mega Farm Programme |
| Administration & support | Governance | Governance Centre (needs `eir.govern`), and the section Audit (Audit Trail, Audit Trace, Compliance Audits) |
| Administration & support | Access & Setup | Section Users & Roles (Users, Roles & Permissions), Financial Periods, Settings |
| Administration & support | Support & Maintenance | Support Tickets (needs `tickets.index`), Maintenance (needs `settings`), and the group System Documentation (User Manual, Administrator Manual, Technical Manual, Installation Guide) |

The sidebar is drawn by `resources/js/Jetstream/SidebarNav.vue` and `resources/js/Jetstream/DropdownMenu.vue`. Each block folds from its heading; the top entries cannot fold. A block or group that holds the page in view opens by itself, and the user can still fold it. The fold state of the blocks and of the groups is remembered per user in the browser's local storage (keys `maiic.sidebar.blocks.<user id>` and `maiic.sidebar.groups.<user id>`); when storage is unavailable a fold lasts for the page only. On the desktop the sidebar can be collapsed to a 72 pixel icon rail with the Collapse menu button at its foot (remembered per browser under `maiic.sidebar.collapsed` by `AppLayout.vue`). On the rail every entry shows its icon with its name as a tooltip, the blocks are drawn as thin rules, and a click on a group opens the full sidebar on that group. Which entry is lit is worked out by `resources/js/Jetstream/menuMatch.js`.

There is no Legacy section: the earlier forward-looking screens are back in their places as working steps of the governed chain, and the legacy `/report` page and the legacy one-click regression screen are not linked. The header comment in the file records the rules: Reports is one entry; screens that do one job together are one entry with tabs; the report-hub tiles (Early Warning, AI Commentary, Executive Summary, the `ifrs9-reports.*` views) are never menu items.

Rules that keep the menu honest are enforced by `tests/Feature/NavigationTest.php`: every route named in the file must be registered; the top-level entries stay in the contract order; Reports is one entry to the hub; the forward-looking entry keeps its month-end order; and one row of tabs carries seven tabs at most. An entry or tab that declares a permission is hidden from a user who lacks it, and that permission must be the one the route's controller enforces. `HandleInertiaRequests::visibleMenu()` drops such entries, drops a group left with no children, keeps only the tabs of a section the user may open and points the section at the first of them, so a user never sees a link that answers 403. A `download` entry is rendered as a plain anchor (2.1). `app/Support/SectionTabCounts.php` adds the record count shown on each tab of the section the user is in (each table counted at most once a minute; a tab whose screen is an analysis or a form has no count).

### 2.4 Breadcrumbs

Every page shows its place in the menu above its header. `app/Support/Breadcrumbs.php` builds the trail from the menu as the user sees it (after `visibleMenu()`): block heading, entry, sub-entry, then the tab. The page is matched the way the sidebar lights it up: a menu route named `x.index` also claims `x.*`, the longer pattern wins, and a tab with query parameters (`tab=cashflows`) wins when the address carries them. A page that no entry claims, but whose route is a sibling of a menu route (`internal-grading.grades` beside `internal-grading.profiles`), is placed under that entry. Each crumb links to its node: an entry's own route, a section's first tab, or a group's or heading's first entry. The trail also says whether its last crumb is the page itself (`exact`).

The trail is shared as `props.breadcrumbs` and drawn by `resources/js/Components/Maiic/Breadcrumbs.vue`, which `AppLayout.vue` places above every page header; every crumb but the last is a link. When the trail stops above the page (an edit screen under its list, a report under the hub) the page title closes it. A detail page adds its own crumbs through the layout's `extraCrumbs` prop (Internal Grades, then the profile). A page the menu does not hold reads Dashboard, then its title; the Dashboard itself shows no trail. At phone width the middle crumbs fold into an ellipsis. Hand-written path rows were removed from the page headers when the breadcrumbs came in, so a page should not draw its own.

### 2.5 Shared props

`app/Http/Middleware/HandleInertiaRequests.php` adds to every Inertia response: the signed-in user (id, name, email, photo, current role, theme preference and a flattened list of permission names), the organisation currency, cached settings such as company name, contact details and logo, flash messages (including `import_check`, the row-by-row result of a refused import), the menu as the user may see it with its tab counts, the breadcrumb trail, the current route name, the latest financial period and whether it is closed (the period chip in the top bar), and the unread notification count. Settings, the currency and the current period are cached for sixty seconds so the middleware does not query those tables on every request. The visible menu is worked out once per request and read by both the sidebar and the breadcrumbs. The user, flash messages, menu, breadcrumbs, current period and notification count are closures, resolved only when the response includes them; the notification list itself is fetched by the bell when it is opened.

### 2.6 Design system

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

### 2.7 Module map

| Menu group | Controllers | Principal models | Vue page folders |
|---|---|---|---|
| Dashboard, Workspace | `DashboardController`, `WorkspaceController` | `ExpectedCreditLoss`, `LoanBook`, `ReportingPeriods`, `PeriodWorkspaceTask`, `WorkspaceMessage` | `Dashboard.vue`, `Workspace/` |
| Reports | `Reports/Ifrs9ReportsController`, `Reports/StressTestingController`, `ReportsController`, `RbmReturnController`, `AuditorPackController` | `LoanBook`, `ExpectedCreditLoss`, `StressScenario`, `RegressionModel` | `Reports/`, `Reports/Ifrs9/` |
| Portfolio Setup | `LoanPortfoliosController`, `LoanProductCategoriesController` (Product Groups), `IndustryTypesController` | `LoanPortfolio`, `LoanProductCategory`, `IndustryType` | `Portfolios/`, `IndustryTypes/`, `LoanProductGroups/` |
| Customer & Loan Data | `ClientsController`, `LoanBookController`, `ImportsController`, `GeneralImportController`, `ImportTemplateController`, `ImportSampleController`, `EirFeedController`, `EirTakeonController` | `Client`, `LoanBook`, `Import`; the landing-zone tables are read and written by the services in `app/Services/Ebanker` | `Clients/`, `LoanBooks/`, `Imports/`, `Eir/Feed/`, `Eir/Takeon/` |
| Collateral Management | `CollateralController` | `Collateral`, `CollateralType`, `CollateralAllocation` | `Collateral/` |
| EIR & Revenue Recognition | `EirAccountingRuleController`, `EirDataController`, `EirIntakeController`, `EirFeeClassificationController`, `EirCalculationController`, `EirScheduleController`, `EirReconciliationController`, `EirCoverageController`, `EirDrawdownController`, `EirReferenceRateController`, `EirTrialBalanceController`, `EirWriteOffController`, `EirRestructureController`, `EirAsAtController` | `ContractEir`, `ContractCashflowSchedule`, `ContractRemainingCashflowSchedule`, `ContractFee`, `EirAccountingRule`, `EirAmortisation`, `GlInterestPosting`, `GlTrialBalanceLine`, `GlAccountScope`, `Scheme`; the write-off, restructure and rate-reset registers through their services in `app/Services/Eir` | `Eir/` |
| IFRS 9 Model Setup | `StageingRulesController`, `StagingThresholdController`, `SicrGroupController`, `SicrItemController`, `SicrTriggerController`, `TransitionProfileDefinitionController`, `TransitionMatrixController`, `TransitionMatrixCummulativeController`, `InternalGradingController`, `LossGiveDefaultController`, `LossGivenDefaultCummulativeController` | `SicrGroup`, `SicrItem`, `SicrTrigger`, `TransitionProfileDefinition`, `TransitionMatrix`, `LossGivenDefault` | `StageingRules/`, `TransitionProfileDefinitions/`, `TransitionMatrix/`, `InternalGrading/`, `LossGivenDefault/` |
| Forward-Looking Information | `FliModuleController`, `MacroStatisticsController`, `CreditLossDataController`, `FliCorrelationController`, `FliAdjustmentsController`, `ManualForecastController`, `MacroForecastWeightedController`, `ScenariosController`, `ScenarioSetController`, `FLI/ScenarioSetController`, `FliOverlayController`, `FLI/ExternalCalculationsController` | `MacroStatsDefinition`, `MacroStatsValue`, `CreditLossDefinition`, `CreditLossData`, `ScenarioProfiles`, `MacroForecastWeighted`; the governed tables are read and written by the services in `app/Services/Fli` and `app/Services/Scenario` through the query builder (chapter 5.5) | `FLI/`, `Macro/`, `Governance/ScenarioSets.vue`, `Governance/Overlays.vue` |
| ECL Processing | `ExpectedCreditLossController`, `EclProjectionController`, `MegaFarmController` | `ExpectedCreditLoss`, `LoanBook` | `ExpectedCreditLoss/`, `MegaFarm/` |
| System Documentation | `HelpController`, `HelpManagementController`, `SystemDocsController` | `HelpCategory`, `HelpArticle` and children | `Help/`, `Docs/` |
| Governance, Access & Setup, Support & Maintenance (Administration & support) | `EirGovernanceController`, `AuditTrailController`, `AuditTraceController`, `ComplianceAuditController`, `UsersController`, `RolesController`, `FinancialPeriodController`, `SettingsController`, `TicketsController`, `MaintenanceController`, `LicenseController` | `User`, Spatie `Role` and `Permission`, `FinancialPeriod`, `Ticket`, `TicketUpdate`, `Setting`, `Vital` (licence) | `Users/`, `Roles/`, `FinancialPeriods/`, `AuditTrail/`, `Audit/`, `Governance/Compliance/`, `Tickets/`, `Settings/`, `License/`, `Admin/Maintenance.vue` |

### 2.8 Conventions

- Controllers apply `auth` and `permission:<name>` middleware in their constructors; the permission name follows `module.action` (for example `tickets.update`).
- Money is stored as decimals in the organisation currency and presented in accounting format (thousands separators, two decimals, negatives in parentheses).
- Reporting periods are month-end snapshots identified as `YYYY-MM`; most screens open on the latest period that has data.
- Nothing is hardcoded that the database can supply: dropdowns are populated from tables and inputs are whitelisted against those values server-side.
- User-facing text avoids em and en dashes; the codebase is swept for them before release.
