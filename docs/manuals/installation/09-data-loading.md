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

### 9.8 Loading from E-Banker: the landing zone and the bootstrap (from 8 October 2026)

From October 2026 the three extract scripts of 9.5 are no longer the source. The system is loaded from the E-Banker tables themselves, pulled by the queries kept under `docs/bootstrap/queries/` and committed as CSV under `docs/bootstrap/` with `manifest.json` (every file's query id, row count and SHA-256, and the accepted exceptions). Specification v4 section 6 describes the design; this section is the procedure.

**One command on a clean database.**

```
php artisan eir:bootstrap --fresh --with-client-inputs --build --run-engines --verify
```

| Step | What it does | Command on its own |
|---|---|---|
| 1 | `migrate:fresh`; refused if the database holds user data unless `--force-wipe` is passed | `php artisan migrate:fresh --force` |
| 2 | Seeds: roles and permissions, the Governance Centre defaults, the staging thresholds (the RBM directive's 91 and 181 days), the query register, the macro series, the help centre, the fee rulebook (unapproved), the compliance modules | `php artisan db:seed --class=<Seeder>` |
| 3 | Lands the committed pack through its gates (file present, hash, row count, query in the register, dates in the manifest's format, key present and unique) and the 20 trial balances with the AFS bridge | `php artisan eir:land-pack docs/bootstrap --user=1`; `php artisan eir:import-trial-balances docs/bootstrap/trial-balances/monthly --afs=<bridge>` |
| 4 | Lands the take-on workbook: the original as received and the mapping copy with the fee columns; 100 blocks, 3,741 schedule lines, each value with its sheet and cell | `php artisan eir:land-takeon --user=1` |
| 5 | Builds: the loan books for every month from July 2024 to the last month in the pack by the method in force (B, derived from the ledger, by default; A copies the stored report), the take-on population under its basis, the contract master from the masters, the PLR series, the origination fees from the LOS charges, the interest posted and the cash movements from the ledger, the spreads, the version 1 schedules approved under the bootstrap label | `php artisan eir:build-loan-books 2024-07 2026-08 --bootstrap`; `php artisan eir:land-takeon --build`; the rest inside the bootstrap |
| 6 | Engines, synchronously and in dependency order: macro statistics (live from the World Bank, else the committed snapshot), staging, EIR solved and locked, revenue for every period, ECL for the last period, GL reconciliation | `php artisan macro:import-worldbank`; `php artisan eir:stage 2024-07 2026-08`; `php artisan eir:run-revenue <YYYY-MM> --user=1`; `php artisan ifrs9:recalculate-ecl <YYYY-MM> --portfolio=1` |
| 7 | Verifies the golden numbers and exits non-zero on any FAIL: interest posted January 2025 to July 2026 of 5,293,988,207.06 from the ledger; the derived carrying amount equal to the stored report on every account-month; the year-end batch contras on 4215 and 4216; the loan GLs at 31 December 2025 against the trial balance, with 1050201 and 1050202 off by the accepted exception of specification section 3.5 | part of `eir:bootstrap --verify` |

On the build machine the whole run takes about 80 seconds. Anything the bootstrap approves (a load, a build, a schedule, a fee classification by rule) carries the label "System Bootstrap (automated data-readiness, not a MAIIC approval)". It never approves the fee rulebook itself: a MAIIC reviewer does, on the Accounting Rules screen, and until then a contract whose fees are unclassified stays blocked at the fee gate, which the Coverage & Blockers screen shows.

**Every month, by the feed.** Barry runs the versioned queries (Data Foundation, E-Banker Feed, "Download the queries"), zips the CSV files with `manifest.json`, and uploads the zip on the same screen (route 1); the gates run and the pack is landed or quarantined with the file and row named. Then a build is proposed for the month and a second person approves it; the differences from the previous build are shown first and a locked period (`php artisan eir:lock-period <YYYY-MM> --user=<id> --reason="..."`) is never restated. The engines run from their screens or by the commands above.

**The EIR at any date.** `php artisan eir:as-at <YYYY-MM-DD> [--contract=<id>]`, or Report Hub, EIR as at a Date: a month-end reads the locked roll-forward row; a date inside a month accrues the EIR on the actual days from the prior month-end less the cash received; a date after the last loaded posting is refused with that date named.

**Interim scripts.** The Python scripts under `docs/build-files/` (`demo_load_loan_books.py`, `demo_contract_master.py`, `demo_schemes.py`, `demo_stage.py`) were the interim load of the 8 October demo and are kept as a record; the bootstrap replaces them.
