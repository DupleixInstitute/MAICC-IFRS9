## 4. Data Onboarding

Two import stacks coexist. The loan book, collateral and client imports use maatwebsite/excel importer classes with per-import exception files. The EIR extracts use a generic mapped reader and one queued job. Both record progress in the `imports` table and both are visible on the Imports page.

### 4.1 Loan book import

There are two loan book paths, both under `app/Http/Controllers/LoanBookController.php`.

The mapped CSV import (`import()`) accepts a CSV, a portfolio and an optional column mapping, creates an `imports` row with status `pending`, and runs `app/Imports/LoanBooksImport.php`. That importer reads in chunks of 500 rows, validates that `customer_id` and the value and maturity dates are present and parseable, writes each chunk with an upsert keyed on customer, portfolio, reporting period and contract, and appends rejected rows to `storage/app/public/failed_imports/loan_books_exception_{import_id}.csv` with the reason. The import events move the status from `pending` to `processing` to `completed` or `failed` and keep `records` and `failed_records` in step. The IFRS 9 stage of every imported row is set by `app/Services/Eir/StagingClassifier.php` (chapter 5).

The E-Banker grouped export import (`importGroup()`) accepts the positional core banking CSV, stores it under `temp-imports` and dispatches `app/Jobs/ProcessLoanImportJob.php` through `app/Services/LoanImportService.php`. The job parses the two E-Banker layouts, expands scientific-notation account numbers, creates or updates clients, upserts `loan_books` in batches of 500 and computes the stage inline from the arrears columns. It records `failed_records` as total rows minus imported rows and does not write a failed rows file.

### 4.2 Collateral and client imports

`CollateralController::importCollateralRegister()` mirrors the loan book path with `app/Imports/CollateralRegisterImport.php`, an exception file named `collateral_register_exception_{id}.csv`, and the same status transitions. Client imports use `ClientsImport` and set `failed_file_path` so the Imports page can offer the download.

### 4.3 The mapped file reader

`app/Services/Imports/MappedFileReader.php` is the reader for every EIR extract. Its behaviour matters for data quality:

- File type is detected from the first bytes (zip signature for xlsx, compound document signature for xls), never from the extension. CSV delimiters are sniffed from the first line.
- Headers are normalised to lowercase snake case. The mapping is a hard-coded alias table per import type (`app/Imports/ContractMasterImport.php`, `ContractTransactionImport.php`, `GlInterestImport.php`) overlaid by saved rows in `import_mappings`, where the saved rows win. Required fields that remain unmapped stop the import with a named error. Unmapped source headers are reported, not dropped.
- Contract ids are always canonicalised through `app/Support/ContractId.php`.
- Dates: a numeric value between 20,000 and 80,000 is treated as an Excel serial; otherwise the declared format is tried and only accepted if it round-trips; otherwise a general parse is attempted; otherwise the value becomes null and the row is reported. This exists because Extract B delivers actual rows as text dates and scheduled rows as serials, and Extract A mixes both in one column.
- Numbers: thousands separators, non-breaking and zero-width spaces are stripped, and accounting negatives in parentheses are honoured.
- Column profiling only suggests `date` or `number` types when at least ninety percent of sampled values agree and, for numbers, when at least one value carries a decimal or thousands mark, so account numbers stay text. Percent is never guessed.

Import types and their required fields: `contract_master` (contract id only, because the delivered Extract A column set is still being confirmed), `schedule` (contract id, due date, principal due, interest due), `fees` (contract id, fee type, amount), `contract_transactions` (customer id, contract id, sub account, date, type, principal and interest components, total, scheduled or actual flag, GL posting reference) and `gl_interest` (contract id, period year, period month, interest income posted).

### 4.4 EIR intake

`app/Http/Controllers/EirIntakeController.php` drives the upload screen. `analyze()` profiles the file and proposes a mapping. `saveTemplate()` stores the mapping in `import_mappings`. `import()` reads the file synchronously first so mapping errors fail immediately, then creates an `imports` row named `EIR {type}: {file}` and dispatches `app/Jobs/ProcessEirImportJob.php`. The job routes the rows to the matching service, writes `failed_imports/eir_exception_{import_id}.csv` on the public disk with contract, status and reason columns, records the outcome in `audit_logs` under the action `EIR Intake Import`, and deletes the temporary upload. Only held and skipped contracts count as failures; notices about rows that loaded (incomplete terms, restatements) go into the file but not into the failure count.

| Extract | Service | Destination | Notes |
|---|---|---|---|
| A, contract master | `app/Services/Eir/ContractMasterImportService.php` | `contract_eir`, fee lines into `contract_fees` | Never rewrites a locked contract. Never sets `instrument_type` from the file. Merges the duplicate rows the delivered file carries (each facility appears twice); two stated values that disagree reject the facility and name the field. An unrecognised repayment frequency is reported, not defaulted. Arrangement and legal fee columns become PENDING fee lines with a deterministic external id so monthly redelivery does not double-create them. |
| B, transactions | `app/Services/Eir/ContractTransactionImportService.php` | `SCHEDULED` rows to `contract_remaining_cashflow_schedule` through `RemainingScheduleImportService`; `ACTUAL` rows to `eir_actual_transactions`; non-zero fee components to `contract_fees` | Rows whose contract is not on the loan tape are held. Any other flag value is a conflict. |
| Original schedules | `app/Services/Eir/ScheduleImportService.php` | `contract_cashflow_schedule` version 1, source `IMPORTED` | Per-contract rejection: existing schedule, missing or duplicate due dates, or scheduled principal more than one percent away from the drawn amount. |
| C, GL interest | `app/Services/Eir/GlInterestImportService.php` | `gl_interest_postings` | Annual control rows (`PERIOD_MONTH = 0`) are dropped. A changed amount for an existing key is applied as a named restatement. Signs are stored as delivered. |
| Trial balances | `app/Services/Eir/TrialBalanceImportService.php` via `php artisan eir:import-trial-balances` | `gl_trial_balance_lines` | Only `code...title` rows are data; the file's grand total row is excluded from the sum and then used as a checksum; a file that does not tie within one cent is rejected. The December pre-closing sheet is imported from the AFS workbook with `--afs`. |

### 4.5 Known data hazards

These come from the client deliveries received between August and September 2026 and must be respected by anyone loading data.

- Extract A carries typed Excel dates whose day and month are swapped (a date entered as day-month was stored as month-day). Text dates in the same columns are correct. Check first repayment and value dates against the disbursement tranche text before trusting a typed date. The reader does not correct this automatically; it flags unparseable values only.
- The Extract C delivered on 26 August 2026 stacks three generation runs. Runs 7 and 25 are identical and run 41 repeats all of 2025, so summing the file triple-counts 2025. Load run 41 only. The import service records the run id as provenance but does not filter on it.
- Trial balance profit and loss balances are cumulative year to date and reset every January. A monthly figure is the difference between consecutive months, with January taken whole. `app/Services/Eir/TrialBalanceMovementService.php` applies this rule on read and never differences balance sheet accounts. The December 2025 monthly file is post-closing and carries no income statement; the pre-closing December balances come from the AFS mapping workbook.
- Twenty-two percent of Extract B rows carry a note that the interest and principal split was estimated by an amortisation formula rather than stored by the core banking system.
- Interest income has no customer grain in the general ledger: the interest GL spools are monthly aggregate journals. Per-loan interest can only come from the loan module extract, never from the ledger.

### 4.6 Reconciliation after a load

Contract Schedule 3 requires record counts to reconcile one hundred percent and aggregate values within 0.1 percent. After a loan book load, compare the `imports` row counts against the source file, then the Loan Book page totals for the period against the core banking control total. After EIR loads, use the EIR Data page tabs (contracts, cash flows, schedules, GL) and the Coverage and Blockers page to confirm that every tape contract has a profile and to see the named blockers.
