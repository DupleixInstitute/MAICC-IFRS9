## 7. Reports and Exports

### 7.1 The reports hub

`app/Http/Controllers/Reports/Ifrs9ReportsController.php` is the one Reports menu entry (`ifrs9-reports.index`, open to `reports.ifrs9`, `reports` or `eir.view`). Its `hub()` lists every report screen of the system in six groups; each tile names its route, the permission that route enforces (the hub hides a tile the user cannot open), whether it takes the hub's period and the downloads it offers. Every hub report builds one normalised payload (title, subtitle, period, KPI tiles, sections of columns and rows with alignment) and hands it to `respond()`, which renders the Vue page or, with `?download=pdf|xlsx|csv`, passes it to `app/Support/ReportDownload.php`: a PDF (A4 landscape, `resources/views/reports/ifrs9/report.blade.php`), an Excel workbook (`app/Exports/Ifrs9ReportExport.php`) or CSV. Periods offered are only those with a calculated ECL; the period parameter is whitelisted against that list.

Several reports are consolidated: one report runs each part's own builder for the same period and shows the parts as tabs on screen, headed parts in the PDF and separate sheets in the workbook. The routes of the absorbed parts still answer on their own.

| Group | Reports (parts absorbed in brackets) |
|---|---|
| Month-end ECL | Executive summary (executive commentary); ECL by stage (stage allocation, PD report); ECL by segment (sector, PD by portfolio and sector, product group, internal grade, concentration and large exposures with HHI, cooperative and anchor links); Portfolio ECL trend; Account-level ECL calculation (loan by loan EAD x PD x LGD, top 200 by exposure); Stage movement and SICR (SICR triggers); Model inputs (LGD and collateral, agricultural risk cover, EAD and off-balance sheet); Forward-looking and scenarios (scenario-weighted ECL) |
| Annual report and audit | IFRS 9 note for the annual financial statements (7.7; absorbs the opening to closing ECL, gross carrying amount movement and ECL charge reports); Loan book reconciliation (7.4); Data quality and exceptions |
| Regulatory (RBM) | RBM return, provisional (7.8); RBM classification and provisioning (IFRS 9 stage against RBM class, provision comparison, PD by RBM class, NPL and arrears) |
| EIR | EIR as at a date; GL reconciliation (chapter 6.10) |
| Risk and analytics | Stress testing; Early warning signals |
| Exports | Loan book export; ECL export; Disbursements (vintage); Auditor pack (one zip per period, needing `eir.export`) |

The RBM classification uses the bands of the 2018 Credit Risk Management for Development Finance Institutions Directive by the term of the facility (short term is 12 months or less), the same bands and rates as the RBM return (`RbmReturnService::BANDS` and `MINIMUM`):

| Class | Short term (days past due) | Medium and long term | Minimum provision |
|---|---|---|---|
| Pass | 0 to 30 | 0 to 90 | 0 percent |
| Special mention | 31 to 90 | 91 to 180 | 5 percent |
| Substandard | 91 to 180 | 181 to 365 | 20 percent |
| Doubtful | 181 to 365 | 366 to 746 | 50 percent |
| Loss | over 365 | over 746 | 100 percent |

The NPL ratio is Substandard plus Doubtful plus Loss balance over the total. The provision comparison applies the minimum rates to the gross carrying amount by class against the booked IFRS 9 allowance.

The Early Warning System is rule-based SQL: stage 1 accounts in arrears, high utilisation (90 percent or more) and new stage 1 to stage 2 migrations, with a watchlist severity of HIGH from 60 days, MEDIUM from 30 and WATCH below. The executive commentary is rule-based templating, declared as such in its subtitle; it makes no external call and describes position, movement, NPL ratio (threshold 10 percent) and coverage (threshold 15 percent).

The former Sensitivity route redirects to the Stress Testing module (ticket #003).

The forward-looking report reads the forward-looking month-end (chapter 5.5): the macro data held in `macro_series` up to the month, the macro vintage of the month record, and the scenario set the record used (else the month's approved set) with its weights and shocks; its scenario-weighted part shows the ECL under each scenario, the probability-weighted ECL, the booked ECL, their difference and the close status from the month's `fli_run_snapshots` record. Until the month is recorded the report says so and points to Forward-Looking Information, FLI Month-End.

### 7.2 The booked allowance in every report

`app/Services/Reports/BookedEcl.php` defines the loss allowance every report carries: the booked allowance, which is the ECL discounted by the time-phased engine (`ecl_discount_status` CALCULATED_TIME_PHASED, `ecl_value_discounted`). Each loan falls on one basis: discounted; programme (a Mega Farm programme loan at the programme measure, decision D30); undiscounted (an ECL exists but no discounted figure, shown at `ecl_value` and counted, with the reason from its discount status); nil; or none. `BookedEcl::summary()` gives the count and amount on each basis by stage, a status (booked, mixed or none) and the reconciling lines from the reporting-date measure: undiscounted ECL (EAD x PD x LGD), plus the lifetime time-phased projection over that measure, less the effect of discounting, to the allowance shown.

Every hub report that carries an allowance (`Ifrs9ReportsController::ECL_REPORTS`) appends a section "Basis of the loss allowance" built by `BookedEcl::section()` (a part of its own in a consolidated report), and when the status is mixed the subtitle says how many loans are at their undiscounted ECL. The IFRS 9 note, the ECL reconciliation, the RBM return and the portfolio trend read the same figure.

`app/Services/Reports/WriteOffSource.php` is the one source of amounts written off between two month-ends: the ledger's write-offs by the `WriteOffClassifier` rule (chapter 6.14) and the approved entries of the write-off register, a register entry repeating a ledger write-off of the same contract, date and amount counted once. The IFRS 9 note (and the hub's opening to closing ECL and ECL charge, which are built from it), the ECL reconciliation and the loan book reconciliation read it; the gross carrying amount movement report does not show write-offs.

### 7.3 Excel export and PDF template

`Ifrs9ReportExport` consumes the same payload as the page and PDF, so every hub report exports without per-report code. The sheet carries the company, title, subtitle with period and generation line, a KPI row on a green band, then each section with a gold-ruled heading, a dark green column header band and zebra rows, with the pane frozen below the header; a consolidated report is written by `app/Exports/Ifrs9MultiSheetExport.php`, one sheet per part. Display strings are coerced back to typed numbers: parenthesised negatives, thousands-separated decimals and integers, and percentages, each with an accounting number format so figures remain summable and right-aligned.

The PDF template places the logo (the company logo uploaded in Settings, else `public/images/maiic-logo.png`, `ReportDownload::logoPath`) in a fixed running header with the company name, report title and period, a tricolour green, gold and red brand bar, KPI cards, one table per section with tinted header and zebra rows, and a fixed footer with the generating user, a confidentiality note and page numbers. DomPDF is configured in `config/dompdf.php`; the template overrides the default serif with DejaVu Sans so arrows and mathematical glyphs render.

### 7.4 Reconciliation and extract reports

`app/Http/Controllers/ReportsController.php` serves five pages under `/report`. Its constructor requires a login and gates only the old reports list (`reports.index`) with `reports`; the five pages themselves check no permission, although their hub tiles are shown only to holders of `reports`.

| Report | Built by | What it computes |
|---|---|---|
| ECL Reconciliation | `EclReconciliationService` | The IFRS 9 movement bridge between two periods for one portfolio: opening, transfers to stages 1, 2 and 3, net remeasurement, new assets originated, derecognised, amounts written off (from `WriteOffSource`), closing, on either the booked ECL allowance or the carrying amount; a detailed mode lists new loans, derecognised loans and stage transitions. On the allowance it returns the `BookedEcl` basis of both months. |
| Loan Book Reconciliation | `ReportsController::loanBookBridge` | Opening loan book, new loans, loans repaid or derecognised, net change on continuing loans, less amounts written off (ledger and approved register, listed), expected closing against the actual closing, with the difference; PDF (portrait), Excel and CSV through `ReportDownload`. `LoanBookReconciliationService` now supplies only the portfolio and period lists. |
| Disbursements (Vintage) | `DisbursementReportService` | Origination cohorts by month with balance at one, two and three months after origination. |
| Loan Book Export | `LoanBookExportService` | Stage balances and counts by period, or a detailed contract list. |
| ECL Export | `ECLExportService` | Stage summary with EAD, PD, LGD and ECL, or a full loan-level extract with selectable columns. |

The services keep their static `generate()` and `exportToCsv()`. The ECL reconciliation and the two exports read the stage from `calculated_ifrs9_stage`; the two exports carry the undiscounted `ecl_value`, not the booked allowance.

### 7.5 Dashboard and ECL page

`DashboardController::index()` accepts a reporting period (from `reporting_periods` where ECL is calculated), a portfolio and a compare-to period (defaulting to the closest earlier period), plus a trend range that defaults to January of the selected year. Two grouped queries supply exposure by stage from `loan_books` (post-qualitative stage) and ECL, average PD and average LGD by stage from `expected_credit_loss`. The ECL is the discounted `total_ecl_discounted` only when every run row of the selected period, and of the compare period, has `discount_status` CALCULATED; otherwise both are read on the undiscounted `total_ecl`, so a movement always compares like with like, and the page is told which basis it shows (`ecl_basis`, discounted or undiscounted) so the tile says so. KPIs: total exposure, total ECL with coverage, coverage ratio (ECL over exposure), stage 3 exposure with share of book, weighted PD (EAD-weighted across stages) and weighted LGD (stage 3 LGD weighted by stage 3 exposure). Money deltas are percentage changes and PD and LGD deltas are point changes, coloured by business meaning (exposure up is growth, ECL, PD and LGD up is risk). The coverage trend plots only periods that exist. The Portfolio Summary table shows current, compare, change and a traffic-light status per metric. No caching is applied; each request queries live.

The ECL Calculation page applies the same rule: it labels the discounted figure as booked only when every loan or run in view is discounted, and otherwise shows the undiscounted figure and says so.

### 7.6 Annual report alignment

Schedule 1 requires stage tables by segment matching Note 8 of MAIIC's audited statements (Mega Farm and the main book, each with gross, ECL and net by stage and a roll-forward of transfers, new assets and changes in PD, LGD and EAD), the sector concentration disclosure, interest income lines matching Note 15 (interest on loans, arrangement fees, legal fees) and the RBM prudential tables. The IFRS 9 note (7.7) and the EIR revenue outputs are the two places that feed these.

### 7.7 The IFRS 9 note

`app/Services/Reports/Ifrs9NoteService.php` builds the note between an opening and a closing month with a calculated ECL (IFRS 7 paragraphs 35H, 35I and 35M), from grouped queries on the booked allowance, `carrying_amount` and `ifrs9stage_post_qualitative`, optionally for one portfolio. `app/Services/Reports/Ifrs9NoteDocuments.php` turns it into one view model in a chosen unit (currency, thousands or millions) for the screen (`ifrs9-reports.fs-disclosure`) and its PDF, Word, Excel and CSV downloads; a note that does not tie is never issued (`assertUsable`). Its sections:

| Section | Content |
|---|---|
| (a) Reconciliation of the loss allowance | Movement in the ECL allowance by stage (IFRS 7.35H), with the amounts written off on their own line |
| (b) Reconciliation of the gross carrying amount | Movement in the gross carrying amount by stage (IFRS 7.35I) |
| (c) Exposure, loss allowance and coverage by stage | Gross, allowance, net, coverage and contract count at the closing month, with the opening for comparison (IFRS 7.35M) |
| (d) Impairment charge to profit or loss | The movement in the allowance other than amounts written off |
| (e) Basis of measurement | Paragraphs written from the system's own settings |
| (f) The loss allowance: undiscounted measure to the discounted allowance | The `BookedEcl` reconciliation by stage for both months |
| (g) Amounts written off | Contract by contract from `WriteOffSource`, with the IFRS 7.35L statement on enforcement activity |
| (h) Modified financial assets | IFRS 7.35J(a), 35J(b) and 20A and the B5.4.6 catch-up, from the confirmed and applied restructure register (`RestructureRegisterService::ifrs7Disclosures`); candidates are never disclosed, and the note says how many await confirmation |

### 7.8 The RBM return

`app/Services/Rbm/RbmReturnService.php` fills the classification and provisioning return under the 2018 directive in a provisional layout until MAIIC Risk supplies the prescribed form (`rbm-return.index`, `rbm-return.export` with `format=pdf|xlsx|csv`, needing `reports.ifrs9`). The Mega Farm programme is left out unless `mega_farm=1`. Sections:

- **A** facilities by class and term under the paragraph 10 bands (7.1).
- **B** the minimum provision per class on the gross balance (no eligible security is netted yet) against the booked IFRS 9 allowance, the higher of the two and the loan loss reserve, with the `BookedEcl` basis note; and the paragraph 12 list of facilities classified loss, with the month-end the unbroken run of loss classification began and the quarter by which the write-off is due, flagged when overdue.
- **C** interest in suspense: the contractual interest posted in the period on non-performing facilities less the EIR interest on the net basis; and, under paragraph 13(1), the cumulative contractual interest charged since each facility became non-performing, tied to GL 1320 Interest Suspense in the trial balance with the difference.
- **D** restructured facilities (paragraph 16(1)) from the restructure register only: loans with a confirmed modification by the period end, with the Board approval recorded for each; candidates awaiting classification are counted and not reported as restructured.
- **E** security held against classified facilities.

### 7.9 EIR reports

EIR as at a date (`eir-as-at.index`, `eir-as-at.export` as CSV, Excel or PDF, `eir.view`; `php artisan eir:as-at {date} --contract= --json`) is built by `app/Services/Eir/EirAsAtService.php`. A month-end reads the roll-forward row for that month. A date inside a month rolls forward from the last closing amortised cost and reports the part month twice, kept apart: the EIR interest (the monthly EIR on the prior month-end amortised cost for the days elapsed, which moves the amortised cost) and the contractual interest accrued and not yet posted (the contractual rate, simple, on the prior month-end ledger balance for the actual days over 365), each labelled as such. The year-to-date comparison sets the EIR interest against the contractual interest posted plus that accrual. The GL reconciliation and its Excel and PDF export are described in chapter 6.10.
