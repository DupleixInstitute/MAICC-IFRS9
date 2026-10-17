# Copilot review: December 2025 loan-book ageing investigation

Date: 17 October 2026

## Scope and conclusion

Read, in the requested order, the point-in-time specification, the December findings note, and the AI background note. Then checked the database and code directly. No application data or code was changed; the temporary read-only probe and reconciliation output were removed after use.

The proposed explanation is **partly supported, not established for all 26 facilities**. On the 26 exact facilities listed as unexplained in the findings note, the current method-B DPD bracket matched MAIIC's workbook for 0. A separate reconstruction from the date-effective instalment chart and ledger receipts matched 5 of 26. Three facilities have no usable chart in the landed extracts; for the other 18 chart-backed facilities, this reconstruction still disagrees with the workbook. This is evidence that report-derived ageing is a real contributor for some facilities, but it does not close the 26-facility question.

The ledger reconstruction is a screening result, not an authoritative allocation of each receipt to a contractual instalment. Its limits matter: the existing helper nets receipt amounts against cumulative scheduled amounts FIFO, treats a partially uncovered instalment as wholly unpaid for DPD, and includes settlement-in-kind postings. Those assumptions need Finance validation before the calculation is adopted for production ageing or arrears buckets.

## Database and evidence checked

The `.env` default resolves to `maiic_ifrs9`; I confirmed `ebanker_raw_rows` is absent there. Explicitly selecting `maiic_ifrs9_bootstrap` resolves to that database, where `ebanker_raw_rows` has 795,100 rows and December 2025 has 3,812 `loan_books` rows. All investigation queries and calculations were read-only.

I inspected actual Loan Book Report JSON payloads from the `P2_08` / `M09_03` / `MF_05` family. The payload includes `ASONDATE`, `TRANSACTION_DATE`, `OVERDUE_PRINCI_DATE`, `ARREAS_TOTAL`, and the `DAY_*` ageing amounts; for example, an `MF_05` row had `ASONDATE = 12/31/2025` and `TRANSACTION_DATE = 3/18/2026`. Ledger payloads expose the dated `TRANTYPE`, `TRANSAMT` and `PARTICULARS` used by the existing reader. This supports the background note's distinction between the requested historical date and the date a report was run.

For the workbook side, I ran the existing `staging:december-check` reconciliation against the bootstrap database. It matched facilities by exact carrying balance first and otherwise by customer name and nearest balance, consistent with `StagingDecemberCheck`. The values-only workbook has no contract IDs; the 26 expected brackets below use the account-to-workbook matches recorded in the investigation findings and confirmed by that reconciliation output.

## Findings

### 1. High: report ageing is a real contributor, but does not explain all 26

For each of the 26 facilities, I compared the current method-B DPD bracket with a chart-based result as at 31 December 2025. The latter uses the chart in force on that date from `InstalmentChartHistory::at()`, ledger receipts through that date from the landed ledger families, and the same write-off/reversal classification used by the application. The oldest scheduled instalment not covered by cumulative receipts determines the reconstructed DPD; its bracket is compared with the workbook's revised bracket.

| Facility | Contract | Workbook | Current method B | Chart + ledger | Result |
|---|---:|---|---|---|---|
| BenCal Technologies Ltd | 104460000059 | 1-30 Days | >181 Days | 1-30 Days (0 DPD) | Chart result matches |
| Mphanga Hodings Limited | 104460000066 | 1-30 Days | >181 Days | 1-30 Days (0 DPD) | Chart result matches |
| Pamela Investments | 104450000074 | 1-30 Days | >181 Days | 1-30 Days (0 DPD) | Chart result matches |
| Chuma Mkandawire TA Chikoma Estates | 104450000060 | 1-30 Days | >181 Days | 1-30 Days (0 DPD) | Chart result matches |
| JAT Group Ltd | 104430000087 | 31-90 Days | 1-30 Days | 31-90 Days (69 DPD) | Chart result matches |

These are concrete cases where a ledger-and-schedule view is closer to MAIIC's workbook than the method-B DPD currently produced. For example, Chuma's chart shows MWK 31,649,719.40 due by the reporting date; the ledger has MWK 31,649,722.08 in receipt-type credits, including MWK 31,627,922.08 on 12 November 2024 and MWK 21,800 on 31 August 2025. The chart result is current while the booked DPD is 396 days. Pamela's dated credits likewise cover the chart total by the reporting date. JAT's chart result identifies the oldest uncovered due date as 23 October 2025 (69 days), matching the workbook's 31-90 bracket while the current method-B result is 1-30.

The count is 5/26 for the independent chart calculation and 0/26 for current method B. This supports the investigation's concern that the stored Loan Book Report's ageing can be wrong, but it does not support treating the whole residual group as an ageing-report defect.

### 2. High: the other chart-backed results do not corroborate the workbook

Twenty-three of the 26 facilities have a chart that the date-effective reader can return; the other three are discussed below. Of those 23, 18 chart-based results still disagree with the workbook. Examples include:

- Nature's Gift Limited (104450000023): MWK 28,636,478.33 scheduled by year-end versus MWK 28,629,918.41 in receipt credits. The FIFO calculation leaves the 1 April 2025 instalment uncovered (274 days), while the workbook says 1-30 days. The total shortfall is only MWK 6,559.92, so treating the whole old instalment as unpaid demonstrates the model's partial-payment limitation; it is not persuasive proof that the workbook is wrong.
- F&K Construction (104460000080): the calculation leaves 1 November 2025 as the oldest uncovered due date (60 days), while the workbook says 1-30 days.
- Ifracon Limited (104430000062): the oldest uncovered due date is 13 August 2025 (140 days), while the workbook says 1-30 days.
- Infraworks Development Limited (104460000098): the result remains >181 days (oldest uncovered due date 1 March 2025), while the workbook says 1-30 days.
- JVD Agro Limited (104460000109): the chart has seven instalments due, no receipt-type credits were found by the cutoff, and the calculation remains >181 days; the workbook says 1-30 days.

Thus the chart/ledger reconstruction is not a general reconciliation to the workbook. Its mixed outcomes are consistent with either remaining workbook methodology differences, schedule/receipt interpretation issues, or both; the landed data alone does not decide among them.

### 3. Medium: method B already has narrow chart-based DPD logic; the buckets remain report-sourced

The investigation's description that method B simply takes all ageing directly from the report needs precision. In `LoanBookBuildService::rowsB()`, carrying amount is derived from ledger postings, but the report is still the source of the arrears amounts via `arrears($p)` and the starting overdue date/day count via `daysPastDue()`. The latter calls `againstTheChart()`, which already recalculates DPD for a collapsed chart and suppresses reported ageing when the chart shows that no instalment is yet due. It does not generally recalculate ageing for an ordinary readable chart.

The arrears bucket amounts (`DAY_1_30` through `DAY_271_360`) are still copied from the report. So the main asymmetry is real for those buckets and for ordinary-chart DPD, but it is not accurate to say there is no chart-and-ledger ageing logic anywhere in method B. Any proposed change should extend the existing chart-aware path deliberately, not introduce a second, divergent DPD rule.

### 4. Medium: three facilities cannot be tested from the available chart extract

No date-effective chart was returned for:

- Premium Meat Suppliers (104460000107)
- Mthirakuwiri Farm (104460000110)
- Elmaw Lodge (104460000112)

Their report ageing and ledger records exist, but without a schedule in force at 31 December 2025 there is no sound chart-based reconstruction. Treat these as unresolved evidence gaps, not as confirmations for either the report or workbook.

### 5. High: FIFO coverage is not exact payment allocation or an arrears-amount reconstruction

`InstalmentChartHistory::daysPastDue()` compares the cumulative amount scheduled by the cutoff with cumulative qualifying ledger credits, in due-date order. It does not link a ledger posting to an instalment identifier or reconstruct a contractual principal/interest allocation. In particular, if receipts are short by a small amount, the first uncovered scheduled instalment supplies the DPD even if almost all of that instalment was paid. The Nature's Gift example above is a material warning against treating this as a validated arrears bracket.

The helper also counts transaction type 401 settlement-in-kind along with receipt types; that may settle an obligation but is not cash. Reversals, write-offs and special ledger cases have application-specific classification. A production ageing design must define which settlement types count as satisfying an instalment and validate the receipt classification and chart amounts against source evidence. This review did not derive the five report arrears-bucket amounts; a DPD match alone does not establish that bucket values can be safely reconstructed.

## Code and method references

- `app/Services/Ebanker/LoanBookBuildService.php`: `rowsB()`, `arrears()`, `daysPastDue()` and `againstTheChart()`.
- `app/Services/Ebanker/InstalmentChartHistory.php`: date-effective chart selection in `at()`, receipt collection in `receiptsTo()` / `ledgerOf()`, and FIFO DPD in `daysPastDue()`.
- `app/Services/Ebanker/LandingZoneReader.php`: `LEDGER`, `LOAN_BOOK_RUNS`, `ledgerByAccount()` and `latestRunsOn()`.
- `app/Console/Commands/StagingDecemberCheck.php`: existing workbook matching and bracket comparison.
- `docs/MAIIC_December_2025_Loan_Book_Investigation_Findings_v1_2026-10-17.md`: exact 26-facility list and recorded workbook brackets.
- `docs/reviews/Background_for_AI_Assistants_December_2025_Loan_Book_Investigation_2026-10-17.md`: query families, full-history caveat and prior anomaly correction.

## Recommendation

Revise the investigation conclusion from “26 unexplained by any E-Banker extract” to “26 remain unexplained by the report-history checks; a chart/ledger FIFO reconstruction aligns five with the workbook, leaves 18 chart-backed cases inconsistent, and cannot address three without charts.” This is a narrower, evidence-supported conclusion.

Before replacing report arrears buckets, agree with Finance on payment allocation, partial instalments, settlement-in-kind, write-offs, restructures and chart gaps. Then test the full historical receipt timeline and produce per-installment balances and ageing buckets, with an explicit reconciliation to ledger totals and to the existing method-B output. The five matching cases justify that next step; the other 21 and the FIFO limitations mean they do not justify a wholesale switch or a claim that the workbook is validated.