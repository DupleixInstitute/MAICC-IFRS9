## 11. The user interface: the Dupleix-suite layout

### 11.1 In plain language

Every system Dupleix now delivers (the ZNBS stress-testing suite, the BBS suite, the FDH IFRS 9 platform) uses the same shape of screen, so that a finance officer who has learned one of them can find their way around the next. MAIIC's system was built earlier, and its menu still follows the headings of the contract schedule ("Customer & Loan Data", "IFRS 9 Model Setup", "ECL Processing") rather than the way the work is actually done. Decision D24 adopts the suite layout for MAIIC.

The shape is simple. On the left is a dark navigation panel with six working groups, each with its own colour: **Data Foundation** (what comes in), **Governance Centre** (the rules and settings that govern the calculations), **Financial Modelling** (the engines), **Risk & Regulatory** (the regulatory views), **Monitoring** (the watch-lists and alerts) and the **Report Hub** (what goes out), followed by System Documentation and Administration. Across the top is a bar with the menu toggle, the financial period the system is working in, notifications and the user's menu. Every page opens with a header that says where you are (the group, then the page) and what the page is for. The panel can be folded to a narrow rail of icons when the screen is small or the user wants room for a wide table. Nothing about the calculations changes; the screens move to where a user would look for them.

### 11.2 What the layout is made of

| Element | What it does | Taken from |
|---|---|---|
| Navigation panel (sidebar) | The brand at the top, then the two top-level links (Dashboard, Workspace), then the groups. One group is open at a time; the group that contains the current page opens by itself. Each group has an icon tile in its own colour, and the page in use is marked with a bar in that colour. | FDH `Components/Shell/Sidebar.vue`; MAIIC keeps its own recursive renderer so that a group can hold sub-groups (the modelling group needs them) |
| Icon rail | The hamburger in the top bar folds the panel to 68 pixels of icons; the choice is remembered in the browser. On a phone the same button opens the panel as a drawer over the page. | FDH `AppLayout.vue` |
| Top bar | Menu toggle; a chip showing the financial period the system is working in and whether it is open or closed; the notification bell; the user menu (profile, API tokens, log out). The decorative search box that does nothing today is removed. | FDH `Components/Shell/Topbar.vue`, less the dark-mode switch |
| Page header | An icon tile, the breadcrumb (group, then page) derived from the navigation tree and the current route, the page title, a one-line description, and a slot on the right for the page's actions. A page that already supplies its own header keeps it, inside this frame. | FDH `AppLayout.vue` |
| Processing pill | The floating "Processing" and "Done" indicator while a request is in flight. Already in MAIIC; unchanged. | Both |
| Fail-loud error dialogue | The plain-language explanation of a 403, 419, 404 or server error. Already in MAIIC; unchanged. | MAIIC |
| Per-page help | MAIIC's help centre and the Help button on every page stay as they are. The guide text that names menu groups is re-worded to the new groups. | MAIIC |

Not adopted: the dark-mode switch. MAIIC's pages were not written with a dark variant and would render half-styled; it can follow in a later pass once every page carries the variant.

### 11.3 Where every screen lives

The tree is the single source of truth: the server holds it in `config/menu.php`, filters it by the user's permissions, and sends it to the browser, which renders the panel and derives the breadcrumb from it. Nothing is duplicated in the Vue code. Routes do not change; only the grouping, the group names and the order do. "Today" is the group each screen sits in now.

| Group (colour) | Screen | Route | Permission | Today |
|---|---|---|---|---|
| Top level | Dashboard | `dashboard` | | same |
| Top level | Workspace | `workspace.index` | | same |
| **Data Foundation** (teal) | Clients | `clients.index` | | Customer & Loan Data |
| | Loan Book | `loan_applications.loan-book` | | Customer & Loan Data |
| | Imports | `imports.index` | | Customer & Loan Data |
| | Loan Portfolios | `portfolios.index` | | Portfolio Setup |
| | Product Groups | `groups.index` | | Portfolio Setup |
| | Sector Types | `industry_types.index` | | Portfolio Setup |
| | Collateral Register, Types, Allocation | `collateral.register.index`, `collateral.types.index`, `collateral.allocations.index` | | Collateral Management |
| | EIR Data | `eir-data.index` | eir.view | EIR & Revenue Recognition |
| | Drawdowns | `eir-drawdowns.index` | eir.view | EIR & Revenue Recognition |
| | Reference Rates | `eir-reference-rates.index` | eir.view | EIR & Revenue Recognition |
| **Governance Centre** (amber) | Governance Centre (the 28 settings of section 4.2) | `eir-governance.index` | eir.govern | EIR & Revenue Recognition |
| | Accounting Rules | `eir-accounting-rules.index` | settings | EIR & Revenue Recognition |
| | Fee Classification | `eir-fee-classification.index` | settings | EIR & Revenue Recognition |
| | Staging & SICR Rules (sub-group): Quantitative Thresholds, SICR Groups Setup, SICR Alert Items | `stageing-rules.index`, `sicr-groups.index`, `sicr-items.index` | | IFRS 9 Model Setup |
| | Financial Periods | `accounting.financial_periods.index` | | Administration |
| | Audit Trail | `audit-trail.index` | | Administration |
| **Financial Modelling** (sky) | PD Model (sub-group): Transition Profiles, Monthly Probability, Cumulative Probability, Internal Grades | `transition-profiles.index`, `transition-matrices.index`, `transition-matrix-cummulative.index`, `internal-grading.profiles` | | IFRS 9 Model Setup |
| | LGD Model (sub-group): Monthly LGD, Cumulative LGD | `loss-given-default.index`, `lgd-cummulative.index` | | IFRS 9 Model Setup |
| | Forward-Looking Model (sub-group): Macro Elements, Scenario Profiles, Weighted Forecast, Credit Loss Data, Adjusted Forecast, Regression Analysis | `macro-statistics.index`, `scenarios.profiles`, `macro-forecast-weighted.index`, `credit-loss-data.index`, `forecasting.manual`, `regression.index` | | IFRS 9 Model Setup |
| | Management Overlays (sub-group): Economic Scenarios, External Calculations, Calculation History | `fli.scenarios.index`, `fli.external.index`, `fli.external.list` | | IFRS 9 Model Setup |
| | ECL Calculation | `expected-credit-loss.index` | | ECL Processing |
| | EIR Calculations | `eir-calculations.index` | settings | EIR & Revenue Recognition |
| | Coverage & Blockers | `eir-coverage.index` | eir.view | EIR & Revenue Recognition |
| **Risk & Regulatory** (indigo) | Stress Testing | `stress-testing.index` | | Reports |
| | Sensitivity | `ifrs9-reports.sensitivity` | | a tile in the hub |
| | IFRS 9 Disclosure | `ifrs9-reports.fs-disclosure` | | a tile in the hub |
| | RBM Classification | `ifrs9-reports.rbm-classification` | | a tile in the hub |
| | IFRS 9 vs RBM | `ifrs9-reports.ifrs9-vs-rbm` | | a tile in the hub |
| | Concentration | `ifrs9-reports.concentration` | | a tile in the hub |
| **Monitoring** (emerald) | SICR Trigger Alerts | `sicr-triggers.index` | | IFRS 9 Model Setup |
| | SICR Trigger Report | `ifrs9-reports.sicr-trigger` | | a tile in the hub |
| | Early Warning System | `ifrs9-reports.ews` | | a tile in the hub |
| | Data Quality | `ifrs9-reports.data-quality` | | a tile in the hub |
| **Report Hub** (violet) | IFRS 9 Reports (the full catalogue of 30) | `ifrs9-reports.index` | | Reports |
| | Executive Summary | `ifrs9-reports.executive` | | a tile in the hub |
| | AI Commentary | `ifrs9-reports.ai-narrative` | | a tile in the hub |
| | ECL Reconciliation | `reports.ecl-reconciliation` | | Reports |
| | GL Reconciliation (EIR) | `eir-reconciliation.index` | eir.view | EIR & Revenue Recognition |
| | Loan Book Reconciliation | `reports.loan-book-reconciliation` | | Reports |
| | Disbursements (Vintage) | `reports.disbursement-report` | | Reports |
| System Documentation (slate) | User Manual, Administrator Manual, Technical Manual, Installation Guide | `help.index`, `help.admin`, `docs.technical`, `docs.installation` | | same |
| Administration (rose) | User Management, Roles & Permissions, Support Tickets, Settings | `users.index`, `users.roles.index`, `tickets.index`, `settings.index` | | same, less the two that moved to Governance |

Three rules behind the placement. A screen that **captures or shows what came in** is Data Foundation, whichever module uses it, so the EIR's data, drawdowns and reference rates sit beside the loan book and the collateral. A screen that **sets a rule the engines obey** is Governance Centre: the EIR settings, the accounting and fee rules, the staging thresholds, the periods and the audit trail that proves who changed what. A screen that **produces a figure** is Financial Modelling; one that **re-presents figures for a regulator or a reader** is Risk & Regulatory or the Report Hub. The hub's catalogue of 30 reports is unchanged; the eleven listed above are also reachable from the panel because they are the ones used weekly.

### 11.4 Behaviour, stated precisely

- **Permissions.** The server drops any entry whose permission the user lacks, and any group left empty, before the tree is sent (`HandleInertiaRequests::visibleMenu`, unchanged). A user never sees a link that would answer 403.
- **Active state.** An entry is active when the current route name equals its route (or its `route_check`). The group containing the active entry is open on page load and its header carries the group colour; one group is open at a time; the user may open another, which closes the first.
- **Breadcrumb.** Derived on the client from the tree and `route_name`: top-level entries give one crumb; a grouped entry gives group, then page; a sub-grouped entry gives group, sub-group, then page. A page outside the tree (an edit form reached from a list, for example) shows the crumb of the nearest list by its `route_check`.
- **Collapse.** Stored in the browser as `maiic.sidebar.collapsed`; on a screen narrower than 768 pixels the toggle opens the drawer instead. In the rail, each group shows its icon tile and, under it, the icons of its leaves with the label as a tooltip; sub-groups are flattened into their parent's icons.
- **Period chip.** The top bar shows the latest financial period and "Open" or "Closed" from its `closed` flag, shared from the server as `currentPeriod`. It is a display; it does not change the period.
- **Page header.** `title` and `description` may be passed as props; otherwise the title is the active entry's name and the description is empty. The `#header` slot, where a page provides one, renders inside the header frame in place of the derived title; the `#actions` slot renders on the right.
- **Colours.** Group colours are Tailwind families (teal, amber, sky, indigo, emerald, violet, slate, rose) written in full in one accent map so that the build includes them. The brand remains MAIIC navy and gold on the dark gradient.

### 11.5 What changes in the code, and what does not

| Change | Where |
|---|---|
| The tree regrouped as in 11.3, with an `accent` key on every group | `config/menu.php` |
| Accent map and group colours in the renderer; icon-rail mode | `resources/js/Jetstream/DropdownMenu.vue`, `SidebarNav.vue`, a new `resources/js/navAccents.js` |
| Collapse state, drawer, page header with breadcrumb, period chip; the dummy search removed; the dead consultation-channel code removed | `resources/js/Layouts/AppLayout.vue` |
| `currentPeriod` shared with every page | `app/Http/Middleware/HandleInertiaRequests.php` |
| Help-centre guides that name a group (fifteen passages) re-worded | `database/seeders/data/help_user_content`, `help_admin_content` |
| User and Administrator manuals: navigation chapter re-written and screenshots retaken | `docs/manuals` |
| A test that every leaf in the tree names a registered route and that every group has an accent | `tests/Feature/NavigationTest.php` (new); `SystemDocsTest` unchanged |

Not changed: any route, controller, page component, permission or calculation. The three icons the groups need and MAIIC does not yet register (shield, bell, balance-scale) are added to the Font Awesome library.

### 11.6 Acceptance

1. Every entry in 11.3 is reachable from the panel by a user with the right permission, and absent for one without it.
2. The breadcrumb on each of the 60 screens reads group, then page, as 11.3 lists them.
3. The rail, the drawer and the open-one-group rule behave as 11.4 states, on a 1366-pixel laptop and a phone.
4. The help centre and the two manuals name no group that no longer exists.
5. `SystemDocsTest` and the new navigation test pass; the EIR suite is unaffected.

### 11.7 Order of work

UI-1 the tree and the accent map (half a day); UI-2 the shell: rail, header, period chip (half a day); UI-3 help text and the manuals' navigation chapter with new screenshots (half a day); UI-4 the walk-through of 11.6 on a copy of the database. It is done before P8, so that the new EIR screens of P8 are placed in the suite layout from the start, and after P4b, which does not touch the interface.
