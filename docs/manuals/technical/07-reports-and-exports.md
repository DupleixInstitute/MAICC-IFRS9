## 7. Reports and Exports

### 7.1 The IFRS 9 reports hub

`app/Http/Controllers/Reports/Ifrs9ReportsController.php` holds a catalogue of reports grouped into seven categories. Every report builds one normalised payload (title, subtitle, period, KPI tiles, sections of columns and rows with alignment) and hands it to a single `respond()` method that renders the Vue page, or a PDF (`?download=pdf`, A4 landscape, `resources/views/reports/ifrs9/report.blade.php`) or an Excel workbook (`?download=xlsx`, `app/Exports/Ifrs9ReportExport.php`). The Vue page also offers a client-side CSV of the visible rows. Periods offered are only those with a calculated ECL; the period parameter is whitelisted against that list.

| Category | Reports |
|---|---|
| Core ECL | Executive Summary; ECL Summary by Stage; Portfolio ECL Trend; ECL by Sector; ECL by Product Group; ECL by Internal Grade; Account-Level ECL Calculation (loan by loan EAD x PD x LGD trail, top 200 by EAD); Stage Allocation |
| Staging and Movement | SICR Trigger; Stage Migration (versus the prior period); Opening to Closing ECL Reconciliation; Gross Carrying Amount Movement; ECL Charge or Release |
| Model Components | PD Report; LGD and Collateral (net unsecured = max(0, EAD minus allocated discounted collateral)); Credit Risk Mitigation (Agri); EAD and Off-Balance Sheet |
| Forward-Looking | Macro Scenario and Forward-Looking; Scenario-Weighted ECL |
| RBM Prudential | RBM Asset Classification; IFRS 9 Stage versus RBM Mapping; NPL and Arrears; Provision Comparison; Concentration and Large Exposures (HHI, threshold control); Cooperative and Anchor Linkage |
| Disclosure and Audit | Financial Statement Disclosure (note tables in the annual report layout); Data Quality and Exceptions |
| Analytics | Early Warning System; AI Executive Commentary |

The RBM classification uses the 2018 directive bands: Pass 0 to 30 days at one percent, Special Mention 31 to 89 at one percent, Substandard 90 to 179 at twenty percent, Doubtful 180 to 364 at fifty percent, Loss 365 and over at one hundred percent. The NPL ratio is Substandard plus Doubtful plus Loss exposure over total exposure.

The Early Warning System is rule-based SQL: stage 1 accounts in arrears, high utilisation (90 percent or more) and new stage 1 to stage 2 migrations, with a watchlist severity of HIGH from 60 days, MEDIUM from 30 and WATCH below. The AI Executive Commentary is rule-based templating, declared as such in its subtitle and closing sentence; it makes no external call and describes position, movement, NPL ratio (threshold 10 percent) and coverage (threshold 15 percent).

The former Sensitivity tile redirects to the Stress Testing module (ticket #003).

### 7.2 Excel export

`Ifrs9ReportExport` consumes the same payload as the page and PDF, so every hub report exports without per-report code. The sheet carries the company, title, subtitle with period and generation line, a KPI row on a green band, then each section with a gold-ruled heading, a dark green column header band and zebra rows, with the pane frozen below the header. Display strings are coerced back to typed numbers: parenthesised negatives, thousands-separated decimals and integers, and percentages, each with an accounting number format so figures remain summable and right-aligned.

### 7.3 PDF template

The hub PDF template places the MAIIC logo (from `public/images/maiic-logo.png` when present) in a fixed running header with the company name, report title and period, a tricolour green, gold and red brand bar, KPI cards, one table per section with tinted header and zebra rows, and a fixed footer with the generating user, a confidentiality note and page numbers. DomPDF is configured in `config/dompdf.php`; the template overrides the default serif with DejaVu Sans so arrows and mathematical glyphs render.

### 7.4 Reconciliation and extract reports

`app/Http/Controllers/ReportsController.php` serves five pages backed by static services in `app/Services/Reports`, each with `generate()` and `exportToCsv()`:

| Report | Service | What it computes |
|---|---|---|
| ECL Reconciliation | `EclReconciliationService` | The IFRS 9 movement bridge between two periods for one portfolio: opening, transfers to stages 1, 2 and 3, net remeasurement, new assets originated, derecognised, written off, closing, on either the ECL allowance or the carrying amount; a detailed mode lists new loans, derecognised loans and stage transitions. Both feed queries hardcode the written-off flag to zero, so the written-off row never populates today. |
| Loan Book Reconciliation | `LoanBookReconciliationService` | Opening plus new disbursements minus repayments minus write-offs versus actual closing, with the variance. |
| Disbursements (Vintage) | `DisbursementReportService` | Origination cohorts by month with balance at one, two and three months after origination. |
| Loan Book Export | `LoanBookExportService` | Stage balances and counts by period, or a detailed contract list. |
| ECL Export | `ECLExportService` | Stage summary with EAD, PD, LGD and ECL, or a full loan-level extract with selectable columns. |

The last two are reached from the export buttons on the Loan Book and ECL Calculation pages rather than the menu. All five read the stage from `calculated_ifrs9_stage`.

### 7.5 Dashboard

`DashboardController::index()` accepts a reporting period (from `reporting_periods` where ECL is calculated), a portfolio and a compare-to period (defaulting to the closest earlier period), plus a trend range that defaults to January of the selected year. Two grouped queries supply exposure by stage from `loan_books` (post-qualitative stage) and ECL, average PD and average LGD by stage from `expected_credit_loss`. KPIs: total exposure, total ECL with coverage, coverage ratio (ECL over exposure), stage 3 exposure with share of book, weighted PD (EAD-weighted across stages) and weighted LGD (stage 3 LGD weighted by stage 3 exposure). The compare period is computed with the same formulas; money deltas are percentage changes and PD and LGD deltas are point changes, coloured by business meaning (exposure up is growth, ECL, PD and LGD up is risk). The coverage trend plots only periods that exist. The Portfolio Summary table shows current, compare, change and a traffic-light status per metric. No caching is applied; each request queries live.

### 7.6 Annual report alignment

Schedule 1 requires stage tables by segment matching Note 8 of MAIIC's audited statements (Mega Farm and the main book, each with gross, ECL and net by stage and a roll-forward of transfers, new assets and changes in PD, LGD and EAD), the sector concentration disclosure, interest income lines matching Note 15 (interest on loans, arrangement fees, legal fees) and the RBM prudential tables. The Financial Statement Disclosure report and the EIR revenue outputs are the two places that feed these.
