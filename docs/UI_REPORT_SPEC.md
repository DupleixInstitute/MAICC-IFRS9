# MAIIC UI & Report Formatting Spec (Tickets #004 / #005 / #006)

Extracted from the two reference apps and mapped to MAIIC colours. This is the
implementation guide for the app-wide standardisation work.

## Reference sources

| Concern | Reference | Key files |
|---|---|---|
| Tables / forms / modals / tabs / badges | `C:\xampp\htdocs\EswatiniCreditScoring` | `resources/js/Pages/Clients/Index.vue` (esw table CSS), `Branches/Create.vue` (esf form CSS), `LoanApplications/Archived.vue` (esw-modal), `Reports/Show.vue` (rv underline tabs, KPI card) |
| Excel workbooks (branded, board-pack) | Eswatini | `app/Exports/Support/CreditWorkbookBuilder.php` (Cover / Contents / Summary-with-formulas / Data-as-Excel-Table) |
| Excel workbooks (audit/regulatory grade) | `C:\xampp\htdocs\Stress-Testing-App` | `app/Support/AuditWorkbook.php`, `app/Services/Reporting/WorkbookStyle.php` (RAG conditional formatting, colour-code legend, sheet protection, accounting formats) |
| PDF reports | Stress-Testing-App | `app/Services/Reporting/MpdfRenderer.php` (running header/footer, 2-pass TOC page numbers), `resources/views/icaap_report_centre/pdf.blade.php` (the branded CSS), `app/Support/ReportBranding.php` + `ReportPalette.php` (DB-governed tokens) |
| Notification bell | Eswatini | `resources/js/Jetstream/NotificationBell.vue` + `NotificationController` (already ported here, Aug 2026) |
| Workspace work-queue | Eswatini | `Dashboard/MyWorkspace.vue` (5 counted tabs + user-scoped KPI strip) |

## MAIIC colour mapping (replaces the reference palettes)

| Role | Token | Hex |
|---|---|---|
| Primary (headers, active nav, table header band) | maiic-700 | `#15803D` |
| Primary link / hover | maiic-600 | `#16a34a` |
| Gold accent (primary submit, active tab underline, totals rule) | maiicgold | `#d4a017` / `#f59e0b` |
| Success badge | green | bg `#DCFCE7` text `#166534` |
| Danger / delete | red | `#dc2626`, badge bg `#FEE2E2` text `#991B1B` |
| Pending / warning | amber | text `#92400E` bg `#FEF3C7` |
| Neutral | gray | `#64748b` / bg `#F1F5F9` |
| Table header tint (PDF) | | `#ECFDF5` |
| Zebra tint | | `#F7FBF8` (screen `#F9FAFB`) |
| Totals-row tint (PDF) | | `#FEF3C7` |

Rule: only green, gold, red and grey families. No blue/indigo/rose variants.

**Status 14 October 2026:** the green tokens are as above (tailwind.config.js:26-37). The gold tokens built are maiicgold-400 `#fbbf24`, -500 `#f59e0b` and -600 `#d97706` (tailwind.config.js:38-42); `#d4a017` is not a token, and gold is not the primary submit (see Buttons below). The PDF report (resources/views/reports/ifrs9/report.blade.php:58-65) uses a solid dark-green header band `#14532d` with white text, zebra `#f3faf5` and a totals tint `#fdf6e7`, not `#ECFDF5`, `#F7FBF8` and `#FEF3C7`. The colour rule is not yet met everywhere: blue, indigo, sky, purple or violet backgrounds remain on 10 pages and components (among them Eir/Data.vue, Eir/Governance.vue, FLI/Overview.vue, FLI/Impact.vue, Components/Maiic/SegmentCell.vue) and a `badge-blue` class is still defined (resources/css/app.css:203).

## Screen components (Ticket #004)

1. Extract ONE shared CSS layer (Tailwind `@layer components` in
   `resources/css/app.css`) with: `maiic-panel` (white card, 12px radius
   (`rounded-xl`), border, subtle shadow; corrected 14 October 2026; it said
   "14px radius"), `maiic-table` (solid green header band, uppercase
   11px letter-spaced headers, zebra even rows, hover row, `.num` right-aligned
   tabular numerals, gold-topped totals row), `maiic-action` icon buttons
   (32px (`h-8 w-8`), rounded-lg; view=green eye, edit=gold pencil, delete=red bin,
   archive=grey; corrected 14 October 2026; it said "30px"), `maiic-badge` pills,
   `maiic-filterbar`, `maiic-input/select`
   (40px, focus ring green), form `maiic-section-title` (uppercase, gold
   underline; corrected 14 October 2026; it said "`section-title`"),
   green primary submit button `primary-btn` (corrected 14 October 2026; it
   said "gold primary submit button"), `maiic-modal` (overlay 45% slate, 14px radius
   card), underline tabs (inactive muted, active green text + gold underline).
   **Status 14 October 2026:** the layer is built in resources/css/app.css:47-213
   (panel, table and compact table, filter bar, input and select, action
   buttons, badges, KPI tile, `primary-btn`, `secondary-btn`, `danger-btn`,
   `maiic-section-title`) with dark variants after it. Not built: `maiic-modal`
   (dialogs use Jetstream/Modal.vue, a grey 75 percent overlay and an 8px
   `rounded-lg` card); the 40px input height (`maiic-input` sets no height);
   the gold tab underline (the active tab has a green underline,
   Components/Maiic/PageTabs.vue:9 and Workspace/Index.vue:86).
2. Migrate pages progressively: Tickets/AuditTrail (done first), then the
   high-traffic index pages, then forms.
3. KPI card with `--accent` CSS variable + coloured left border is the standard
   stat tile.

## Report outputs (Ticket #005)

Excel (PhpSpreadsheet directly, not maatwebsite views):
- 4-sheet shape: Cover (logo, green ribbon, gold rule, meta card, red
  CONFIDENTIAL badge) / Contents (hyperlinked, zebra) / Summary (KPI cards as
  live formulas, native charts; `setIncludeCharts(true)`) / Data (title band,
  native Excel Table, freeze pane `B4`, per-column number formats, conditional
  status colours).
- Accounting format `_(* #,##0.00_);_(* (#,##0.00);_(* "-"??_);_(@_)`,
  percent `0.00%`.
- Cap auto-size on big sheets (fixed width when > 250 rows);
  `setPreCalculateFormulas(false)`; coerce header cells to unique non-empty
  strings before `addTable` (Excel corrupts otherwise).
- Tab colours by section; `getTabColor()->setARGB(...)`.

**Status 14 October 2026:** the 4-sheet workbook is not built (ticket #005 open). Every report's Excel is one branded sheet from Maatwebsite Excel, App\Exports\Ifrs9ReportExport: company and title band, KPI rows, the sections as tables, zebra, freeze pane `A6`, number formats `#,##0.00;(#,##0.00)` and `#,##0.00"%"` (not the accounting format above), auto-size on every sheet with no cap, no native Excel Table, no charts and no tab colours. A report with many sections can be split into several such sheets (App\Exports\Ifrs9MultiSheetExport, ReportDownload.php:123). There is no Cover, Contents or Summary-with-formulas sheet.

PDF (dompdf now; consider mPDF for running header/footer + real TOC):
- `@page` margins; fixed running header (company + confidentiality) and footer
  (page numbers "Page X of Y" stamped on the dompdf canvas with `page_text`,
  ReportDownload::stampPageNumbers and Support/PdfPageNumbers; corrected 14
  October 2026; it said "via `.pageno:after { content: counter(page) }`").
- `table-layout: fixed` for wide tables; `thead { display: table-header-group }`
  so the header repeats on each page (corrected 14 October 2026; it said
  "`table-row-group`", which would not repeat it);
  never a global `tr { page-break-inside: avoid }`.
- Tinted header band, zebra, `.total` row bold with green top rule; status
  pills as inline-styled spans; CSS bar-in-cell for mini charts.
- All values database-driven and reporting-period scoped. No placeholder,
  proxy or fallback data.

**Status 14 October 2026:** in resources/views/reports/ifrs9/report.blade.php the `@page` margins, the fixed running header and footer (:21-37), the repeating `thead` (:54), the zebra and the bold totals row with a dark-green top rule (:64-65) are built. Not as written: `table.grid tr { page-break-inside: avoid }` (:55) applies to every row of every report table, which is the rule this section forbids in all but name; `table-layout: fixed` is not used; the header band is solid dark green, not a tint; there are no status pills or bar-in-cell charts in the template.

## Notifications & workspace (Ticket #006)

- Bell is live (database notifications, shared unread count, fetch-on-open).
  Add dispatchers: ticket assigned/updated, import finished, ECL run finished,
  EIR approval requested/decided.
- Workspace: add a personal work-queue tab set (my items / awaiting my action)
  with counted tabs + user-scoped KPI strip alongside the period-close
  checklist.

**Status 14 October 2026:** the bell is live (NotificationController, routes/web.php:137-141). Dispatchers built: a ticket assigned or updated (TicketsController.php:196-205) and a Workspace checklist step completed or reopened (WorkspaceController.php:276-284), both through SystemEventNotification. Not built: import finished, ECL run finished, EIR approval requested or decided. The Workspace has counted tabs (Checklist, Messages and, uncommitted, Awaiting approval: WorkspaceController::awaitingApproval and Workspace/Index.vue:15-21, 145-157), but the approval list counts everything waiting system-wide, not "my items" or "awaiting my action", and the KPI strip is the period's close progress, not user-scoped.

## Conventions agreed in the UI audits of 13 October 2026

Two audits walked every screen from the imports, through the calculations, to
the reports. What they settled is the standard for every screen from now on;
the code that implements each point is named so it can be reused, not
re-invented.

### Screens

- **Page header.** `app-layout` with `title` and a one-line `description`;
  the actions sit at the top right (`#actions`). Back is a white secondary
  button (`secondary-btn`) in the header actions, never a second green
  button, and there is no blank gap above the first panel.
- **Buttons.** Sentence case everywhere (the Jetstream button is now
  `primary-btn`). Green primary, white secondary, red `danger-btn` for a
  delete, which always asks for confirmation naming what is deleted.
  **Status 14 October 2026:** the classes are built (resources/css/app.css:189-197; Jetstream/Button.vue:2 renders `primary-btn`). Sentence case is not yet everywhere: about 60 pages, mostly the older administration pages, still have title-case labels such as "Delete Record" (for example Banks/Index.vue:58).
- **Every list can be worked from the screen.** Create, edit, delete and
  refresh are on the screen itself. A delete that would leave the system
  wrong is refused with the reason: a role still held by a user, your own
  account, the last administrator, an active grading profile.
- **Tables fit the panel at 1440 px.** A table of more than six columns uses
  `maiic-table-compact`; long text columns are capped in width; a short
  first column (a period, a code) never wraps. Tab rows wrap onto a second
  line rather than scroll sideways. Footer totals line up with the body
  (`tfoot th` has the body's padding).
- **Paging.** 15 rows a page, with the standard pager (`Pagination.vue` for
  server pages, `ClientPager.vue` for lists the page holds in full).
- **Segments.** The segment a PD or LGD run was calculated for is shown with
  `Components/Maiic/SegmentCell.vue`: an icon chip, the name on one line and
  the level under it (Whole loan book / All portfolios pooled, Portfolio,
  Sector and code).
- **Numbers and dates.** Comma thousands and two decimals; PD and LGD as
  percentages; dates as `13 Oct 2026` and run times as `13 Oct, 11:59`, so
  two runs of the same window can be told apart.
- **Imports.** An Import button at the top right opens a modal with the
  instructions, the layout and a sample file; the file is previewed before it
  is committed; each import screen lists only its own history
  (`imports.kind`).
  **Status 14 October 2026:** commit 7a2a96f gave every import the sample file in the exact format, an XLSX column guide, the instructions panel and a fail-closed check before anything is written, with a Check file button that saves nothing (App\Support\Imports\ImportSpecs and ImportSpecValidator, Components/Imports/ImportSpecPanel.vue; ImportValidationTest, ImportTemplateDownloadTest). The import opens in a modal on Macro Statistics, Take-on, Trial Balances, the E-Banker feed, SICR groups and items and Internal Grades; the Clients, Collateral, Loan Book and Credit Loss Data imports are pages of their own. A preview of the rows before commit exists on Macro Statistics, Credit Loss Data, EIR Intake and Internal Grades; Clients, Collateral and Loan Book have the Check file dry run but no row preview. The per-kind history is on Clients, Collateral, Credit Loss Data and EIR Intake.
- **Source codes** of macro series read `WB: code` and `IMF: code`.
- **Help.** The floating help button opens the manual article for the screen;
  page content keeps clear of it (bottom padding), so it never covers a Save
  button.
  **Status 14 October 2026 (uncommitted):** HelpManual.vue gains an `inline` form, a small round button beside the page title, used on the Dashboard, Clients, Imports and the ECL screen so it never sits over a chart or a table.
- **Dialogs** take a width from `sm` to `6xl` (`Jetstream/Modal.vue`).
- **Light and dark mode** for every change.

### Reports

- **Hub tiles** list their formats as text ("Downloads as PDF, Excel, CSV"),
  not as badges that look like buttons.
- **PDF.** A section of up to 20 rows stays on one page together with its
  part banner, so no heading or table header is left at the foot of a page;
  `thead` repeats on each page of a long table.
- **Same basis on every screen.** The RBM classification and the RBM Return
  both apply the directive's minimum rates to the gross balance, so the NPL
  ratio and the minimum provision agree.
- **The forward-looking reports** read the month record (`fli_run_snapshots`)
  and the approved scenario set of the chosen month; a month with no record
  says so.
- **CSV** downloads are the branded report (the RBM Return included) and are
  served as `text/csv`.
- **Large PDFs** (the manuals) embed screenshots at 1400 pixels wide and are
  served from a file, so they arrive whole (the user manual went from 26 MB to
  10 MB).

### Maintenance

Seven jobs, as in the Stress Testing suite (`maiic:task`): bootstrap the system,
update after a release, build the loan book, run the month-end calculations,
recalibrate the forward-looking model, fetch new data and check the system
(MaintenanceController.php:37-87), with a run history. Only the bootstrap,
which wipes the database, needs a typed confirmation (`BOOTSTRAP`) (corrected
14 October 2026; it said "month-end, refresh data, health check, update and
bootstrap (behind a typed confirmation)").
