## 9. Initial Data Loading

Load in this order so each step can be reconciled before the next depends on it. Contract Schedule 3 requires record counts to reconcile one hundred percent (every source record imported or listed on an agreed exceptions report) and aggregate values within 0.1 percent.

### 9.1 Reference data

Portfolios (MAIIC core, FInES, Mega Farm and the derived agricultural portfolios), sector types, product groups and financial periods must exist before loan book rows can be attributed. `php artisan ifrs9:segment-portfolios --dry-run` shows how existing loan book rows would be assigned to portfolios from product groups.

### 9.2 Loan book snapshots

For each month-end, import the loan book through Customer and Loan Data, Loan Book, Import (custom mapping) or the E-Banker grouped export. After each period:

1. Compare the Imports page counts (records and failed records) with the source row count.
2. Compare the Loan Book page totals for the period with the core banking control total for gross loans.
3. Download the failed rows file, if any, and agree the exceptions with MAIIC.

### 9.3 Collateral

Import the collateral register for the same periods (Collateral Management, Register, Import), then allocate collateral to exposures. Reconcile allocated value against the register total.

### 9.4 Historical ECL

If MAIIC's existing database is restored (chapter 10.3), historical ECL results arrive with it. Otherwise run the ECL calculation for each period in order after PD, LGD and FLI setup, and compare the resulting stage totals with the reference IFRS 9 calculation agreed for UAT.

### 9.5 EIR extracts

Under EIR and Revenue Recognition:

1. Accounting Rules: review and approve the seeded fee rules that MAIIC's accounting owner agrees with.
2. EIR Data and Schedule Intake: load Extract A (contract master), then original schedules or generate them (`php artisan eir:generate-schedules --dry-run` first), then Extract B (transactions and remaining schedules), then Extract C (GL interest).
3. Fee Classification: classify and independently review the fee lines.
4. EIR Data, Schedules tab: approve the version 1 schedules.
5. Coverage and Blockers: every contract should read READY or carry a named blocker to resolve.
6. EIR Calculations: calculate, then approve and lock with a second user.

Trial balances are loaded from the command line:

```bash
php artisan eir:import-trial-balances "/data/maiic/trial-balances" \
  --afs="/data/maiic/AFS Final TB and Initial TB Mapped to E-Banker TB for MAIIC for December 2025.xlsx" \
  --sheet="Final E-Banker TB Dec 2025" --period=2025-12-01
```

The command prints one line per file with the GL line count and the total, rejects any file whose grand total does not tie, and warns if `--afs` is omitted because December income would then read as zero.

### 9.6 Known data hazards

- Extract A typed Excel dates have day and month swapped; text dates are correct. Verify first repayment and value dates before approving generated schedules.
- The Extract C delivered on 26 August 2026 stacks three runs (7, 25 and 41). Load run 41 only, otherwise 2025 is triple-counted.
- Trial balance profit and loss balances are cumulative year to date and reset each January; the application derives monthly movements itself and must not be fed pre-differenced figures.
- Extract A carries every facility twice; the importer merges the pairs and rejects any whose stated terms disagree, naming the field.
- Fee amounts per loan are not in the extracts delivered so far; arrangement and legal fees in Extract A columns become PENDING lines when present.

### 9.7 Reconciliation evidence

Keep the Imports page exception files, the Coverage and Blockers export, the EIR Data GL tab and the trial balance import output as the migration reconciliation report required by Schedule 1 deliverable 2.

### 9.8 Loading E-Banker directly (interim, from 8 October 2026)

From October 2026 the three extract scripts of 9.5 are no longer the source. The system is loaded from the E-Banker tables themselves, pulled by the queries kept under `docs/bootstrap/queries/` and committed as CSV under `docs/bootstrap/`. Until the landing zone and `eir:bootstrap` of specification v4 section 6 are built, the load is the sequence below, which is what was run on the demo database on 8 October 2026 (see `docs/After_Build_Report_Demo_2026-10-08.md`). Run it against a copy first.

1. **Migrate and seed.** `php artisan migrate --force`, then `php artisan db:seed --class=GovernanceSettingsSeeder`. The seeder adds only keys that do not exist, so an approved MAIIC change is never overwritten.
2. **Loan books from the stored report runs.** `docs/build-files/demo_load_loan_books.py` reads `P2_08_loan_book_history.csv` (and `M09_03` for the latest month when present), keeps the latest run per account and month-end, and upserts `loan_books` on `(contract_id, reporting_period)`, touching only the loan-book columns. Contract ids are stored without leading zeros, as the Excel importer has always stored them. Set the target database at the top of the script.
3. **Trial balances.** `php artisan eir:import-trial-balances "<folder of Trial Balance_*.xls>" --afs="<AFS bridge workbook>"`. The December 2025 income accounts come from the AFS bridge's sheet "Final E-Banker TB Dec 2025" (the 10 September 2026 version; the 19 August version holds its amounts as text).
4. **Scheme settings.** `docs/build-files/demo_schemes.py` loads the 18 schemes of `DD_10_scheme_settings.csv` into `schemes` (interest policy, floating flag, interest basis, EMI type) and adds the payment frequency to the contract-master file. The instalment basis and the moratorium type are not asserted: DD_10 holds them as codes that the vendor has not yet read for us, and the schedule generator refuses the contracts that need them rather than guessing.
5. **Contract master.** `docs/build-files/demo_contract_master.py` builds a file from `P1_02` and `P1_03` and the latest loan-book row with the headers the importer accepts; load it on the EIR Data intake screen as import type contract master, or through `MappedFileReader` and `ContractMasterImportService`. A contract with no loan-book row since December 2024 is held, by design.
6. **Reference rates and spreads.** `php artisan eir:import-reference-rates <PLR csv> --user=<id>` then `php artisan eir:derive-spreads`.
7. **Schedules and EIRs.** `php artisan eir:generate-schedules --dry-run` lists what is ready and why the rest is not; without `--dry-run` it writes the drafts. Approval and the EIR lock are maker-checker actions on the screens; a bootstrap on a clean copy may approve them under the label "System Bootstrap (automated data-readiness, not a MAIIC approval)", and must say so wherever the figures are shown.
8. **GL interest.** Load Extract C (or the ledger's type 303 and 120 postings in the same shape) as import type gl interest. The importer refuses duplicate runs and annual rows; the kept total for January 2025 to July 2026 is 5,293,988,207.06.
9. **Revenue and reconciliation.** `php artisan eir:run-revenue <YYYY-MM> --user=<id>` for each period, then the GL Reconciliation screen. A contract is refused until its EIR is locked and the period has a loan-book row with a stage.

What this interim sequence does not do, and the specification's section 6 build will: land the raw tables with their hashes, run the pack gates, read the arrears buckets and the overdue date from the stored report into the staging classifier under the header names it expects (`1_30_days` to `271_360_days`), counting days past due from `OVERDUE_PRINCI_DATE` rather than from `OVERDUE_PERIOD`, which is in months (`docs/build-files/demo_stage.py` does this for the interim load), load the ledger as actual transactions so that cash is not taken from the schedule, and run the whole sequence as one command with the baselines verified at the end.
