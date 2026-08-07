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

## Screen components (Ticket #004)

1. Extract ONE shared CSS layer (Tailwind `@layer components` in
   `resources/css/app.css`) with: `maiic-panel` (white card, 14px radius,
   border, subtle shadow), `maiic-table` (solid green header band, uppercase
   11px letter-spaced headers, zebra even rows, hover row, `.num` right-aligned
   tabular numerals, gold-topped totals row), `maiic-action` icon buttons
   (30px, rounded-lg; view=green eye, edit=gold pencil, delete=red bin,
   archive=grey), `maiic-badge` pills, `maiic-filterbar`, `maiic-input/select`
   (40px, focus ring green), form `section-title` (uppercase, gold underline),
   gold primary submit button, `maiic-modal` (overlay 45% slate, 14px radius
   card), underline tabs (inactive muted, active green text + gold underline).
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

PDF (dompdf now; consider mPDF for running header/footer + real TOC):
- `@page` margins; fixed running header (company + confidentiality) and footer
  (page numbers via `.pageno:after { content: counter(page) }`).
- `table-layout: fixed` for wide tables; `thead { display: table-row-group }`;
  never a global `tr { page-break-inside: avoid }`.
- Tinted header band, zebra, `.total` row bold with green top rule; status
  pills as inline-styled spans; CSS bar-in-cell for mini charts.
- All values database-driven and reporting-period scoped. No placeholder,
  proxy or fallback data.

## Notifications & workspace (Ticket #006)

- Bell is live (database notifications, shared unread count, fetch-on-open).
  Add dispatchers: ticket assigned/updated, import finished, ECL run finished,
  EIR approval requested/decided.
- Workspace: add a personal work-queue tab set (my items / awaiting my action)
  with counted tabs + user-scoped KPI strip alongside the period-close
  checklist.
