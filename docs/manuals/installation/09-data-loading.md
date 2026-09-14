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
