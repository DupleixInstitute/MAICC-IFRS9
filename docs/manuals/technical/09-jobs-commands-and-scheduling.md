## 9. Jobs, Commands and Scheduling

### 9.1 Queue configuration

`config/queue.php` defaults to the `QUEUE_CONNECTION` environment variable, which `.env.example` sets to `database`; without it Laravel falls back to `sync`, which runs jobs inline in the web request and is unsuitable for imports. All jobs use the `default` queue; no job names a queue, chain or batch. A worker must run continuously in production:

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=7200
```

The worker timeout must be at least as long as the longest job timeout below. The Docker supervisor configuration shipped in `docker/supervisord.conf` uses 500 seconds, which is shorter than several jobs; the Installation Guide gives a systemd unit with the correct value. `scripts/deploy.sh` calls `queue:restart` after each deployment so workers pick up new code.

### 9.2 Jobs

| Job | Purpose | Dispatched from | Timeout and tries |
|---|---|---|---|
| `ProcessLoanImportJob` | Parse the E-Banker grouped CSV and upsert `loan_books` in batches of 500 | `LoanImportService` | defaults |
| `ProcessEirImportJob` | Read a mapped EIR extract and route rows to the matching EIR service; writes the exception CSV and audit log | `EirIntakeController::import()` | 600 s |
| `CalculateEirJob` | Solve the EIR for a batch of contracts; a blocked contract does not stop the batch | `EirCalculationController::calculate()` | 600 s |
| `RunEirRevenueJob` | Monthly amortised-cost and interest revenue roll-forward over locked contracts | `eir:run-revenue --queue` | 600 s |
| `CalculateDiscountingJob` | Discount LGD recovery payments at the resolved EIR | `LossGiveDefaultController` | 3600 s, 3 tries |
| `ProcessLGDPayments` | Build payment tracking for stage 3 contracts of an LGD run in chunks of 500 | `LGDCalculationController` | 7200 s, 3 tries |
| `CalculateLGDJob`, `ProcessLGDChunkJob` | Chunked LGD computation; the master dispatch is currently commented out in the controller | (inactive) | defaults |
| `ProcessCampaign` | Legacy communication campaign sender | legacy controllers and scheduler | defaults |

Failed jobs land in `failed_jobs`; inspect with `php artisan queue:failed` and retry with `php artisan queue:retry {id}`.

### 9.3 Commands

| Command | Purpose |
|---|---|
| `ifrs9:recalculate-ecl {period} [--level] [--portfolio] [--sector] [--pd] [--lgd] [--discounting]` | Run the ECL calculation from the command line with the same logic as the screen |
| `ifrs9:generate-2025 [--dry-run]` | Calibrate PD and LGD from the November 2025 basis and fill the 2025 months |
| `ifrs9:segment-portfolios [--dry-run] [--rollback]` | Derive portfolios from product groups and back-fill the sector tag |
| `ifrs9:seed-agri-risk [--dry-run]` | Model agricultural credit enhancements |
| `ifrs9:seed-report-fields [--dry-run]` | Derive empty report fields from trusted columns |
| `ifrs9:smoke-reports [period]` | Render every hub report and its PDF as a smoke test |
| `eir:generate-schedules [--dry-run]` | Draft version 1 schedules for contracts with Extract A terms |
| `eir:run-revenue {period} [--contract=*] [--queue] [--recalculate --reason=]` | Monthly EIR revenue run; recalculation requires a reason and voids later periods |
| `eir:import-trial-balances {directory} [--afs=] [--sheet=] [--period=]` | Import the monthly trial balances and the AFS pre-closing December sheet |
| `eir:make-test-data [--out] [--start] [--periods] [--load]` | Generate a synthetic loan book and Extracts A, B and C for end-to-end testing |
| `manual:screenshots [--base-url] [--email] [--password] [--only] [--edge]` | Capture the manual screenshots with headless Chromium |
| `permissions:reset` | Reset the permissions table from the seeders |
| `campaigns:process-recurring`, `campaigns:process-scheduled`, `reminders:loan-applications`, `expenses:process-recurring` | Legacy commands from the loan-origination era |
| `app:fix-script`, `app:playground`, `command:name` | Legacy stubs; `app:playground` calls an external API and should be deleted under ticket #009 |

### 9.4 Scheduler

`app/Console/Kernel.php` currently schedules only the three legacy commands (recurring campaigns daily, scheduled campaigns every five minutes, loan application reminders every two minutes). No IFRS 9 or EIR command is scheduled; the monthly revenue run is triggered by hand until MAIIC's extract cadence is fixed. The scheduler itself needs one cron entry on the server:

```
* * * * * cd /var/www/maiic-ifrs9 && php artisan schedule:run >> /dev/null 2>&1
```

When the monthly run is automated, add it here with `->monthlyOn(day, time)` after the extracts are confirmed loaded.
