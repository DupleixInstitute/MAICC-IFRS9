## 11. Testing and Quality

### 11.1 Layout

Tests live under `tests/Unit` and `tests/Feature` (the two suites in `phpunit.xml`) and run with PHPUnit 10 (`vendor/bin/phpunit` or `php artisan test`). On 14 October 2026 (commit 467ef7f) `vendor/bin/phpunit --list-tests` lists 972 tests from 894 test methods in 151 files: 84 unit tests in 8 files and 888 feature tests in 143 files (data providers account for the difference). `tests/Browser` holds a single Laravel Dusk example and is not part of either suite.

`tests/TestCase.php` sets `$seed = true`, so a test that uses `RefreshDatabase` migrates the in-memory database and runs `DatabaseSeeder` first. It also calls `set_time_limit(0)`, because several legacy controllers lower the time limit to 300 seconds and that clock would otherwise run on for every later test in the same process. `tests/bootstrap.php` re-roots the `App\`, `Database\` and `Tests\` namespaces at the directory the tests live in, so a git worktree that shares `vendor/` with the main clone tests its own code.

About 60 feature test files use `RefreshDatabase` and act as a factory-built user. `UserFactory` sets `active` (an inactive user would be rejected with 403 by the middleware before the assertion) and assigns a random existing role, creating `admin` when no role exists; a test that needs a particular role or permission assigns it itself. The engine tests (most of `tests/Feature/Ebanker`, `Ecl`, `Eir` and `FLI`) do not migrate the whole schema: each builds only the tables it needs in `setUp()`, with `Schema::create` or by requiring single migrations, helped by the traits `tests/Concerns/SeedsGovernedDefaults.php` (the `governance_settings` table with every catalogue default approved from 1900-01-01, as a clean install has it), `tests/Feature/Eir/Concerns/CreatesGovernanceSchema.php`, `CreatesFeeSchema.php`, `CreatesSicrSchema.php` and `tests/Feature/Imports/Concerns/CreatesImportSpecSchema.php`. Fixture files are under `tests/fixtures`.

### 11.2 The database guard

`phpunit.xml` forces `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:`, with `APP_ENV=testing`, the `array` cache, mail and session drivers, `QUEUE_CONNECTION=sync` and `TELESCOPE_ENABLED=false`. Every application instance gets a fresh empty database, so no test reaches the `.env` database and nothing is left between tests. The override must never be commented out: `RefreshDatabase` runs `migrate:fresh` against the default connection, and when the override was once disabled a scaffold test wiped the live development database. Migrations that use MySQL-only SQL check the driver (about ten do) so the sqlite run completes. Engine SQL that sqlite lacks is supplied at runtime: `AppServiceProvider` registers a `POWER()` function on every sqlite connection for the lifetime PD.

### 11.3 Focused suites

| Suite | Covers |
|---|---|
| `tests/Unit/Eir` | Solver golden numbers (ACADES), zero-yield and invalid inputs, date-sensitive solving, schedule generator, mapped file reader (aliases, unrecognised frequency not guessed, spreadsheet detection by content, non-UTF8 CSV) |
| `tests/Unit/Support`, `tests/Unit/EclMovementAttributionTest.php` | Contract id canonicalisation, reporting period parsing, the ECL movement attribution by stage |
| `tests/Feature/Ebanker` | Pack landing and gates (hashes, dates, keys, the ledger against the master), the zip entry-name guard, the trial balance landing, the take-on workbook landing and build, the loan book build (method B, Mega Farm accounts, maturity and arrears rules), the instalment chart in force at each month-end (including the chart copies), and the GL fee route |
| `tests/Feature/Eir` | Intake services for Extracts A, B and C, readiness gate, calculation workflow (maker and checker, lock, reopen), revenue service (27 cases), GL and fee GL reconciliation, coverage, fee rule sweep, staging classifier and service, SICR triggers, trial balance import and movement rules, reference rates and spreads, tranche schedules and take-on EIRs, write-offs, the restructure register, rate resets and modification accounting, the go-live controls, and the period lock and fee classification screens |
| `tests/Feature/Ecl` | ECL golden number, discount rate resolution, build-up, time-phased engine (stage 1, stage 2 lifetime, stage 3 with and without a recovery plan, scenario weighting, floating-rate discounting, its invariants), nil ECL on loans written off to nil, and the Mega Farm programme kept out of MAIIC's book |
| `tests/Feature/FLI`, `tests/Feature/Scenario` | As-at reading (no fit or sweep sees an observation after its period), the expected-sign test modes, External Calculations, the bridge and its governed parameters, the route (one run per scenario, lineage on the loan, fail closed on a missing lagged base, ECL on the post-FLI PD equal to the weighted ECL, a published version in force from its month until a later one, an external model needing a second person, the overlay double-counting review), fit applicability, the discounted month record, the overlay register, the transmission method cards, and the scenario set rules, approval, lock and editing |
| `tests/Feature/Pd`, `tests/Feature/Rbm`, `tests/Feature/Reports` | PD and LGD by segment and their reports, the RBM return (bands, booked allowance, loss list, suspense tied to GL 1320), the booked allowance and write-offs in the note and reconciliations, and the modified-assets disclosure |
| `tests/Feature/Imports` | Every import's sample file, column guide and spec, and the fail-closed check that refuses a bad file before anything is written |
| `tests/Feature/Compliance`, `Macro`, `Regression`, `Security` | Compliance sign and approve by two people, the macro source precedence, legacy regression approval, and login required on every GET route |
| `tests/Feature/StressTestingReconciliationTest.php` | Driver arithmetic, caps, macro multiplier derivation, retired hub redirect |
| `tests/Feature/AuthenticationTest.php`, `CaptchaTest.php` | Login with CAPTCHA, wrong, expired, missing and replayed codes, kill switch, PNG output and headers |
| `tests/Feature/TicketsTest.php` | Guest redirect, permission 403, reference sequence, system trail, status changes, seeder idempotency |
| `tests/Feature/HelpManualTest.php`, `SystemDocsTest.php` | Manual reader, drafts hidden, per-route lookup, PDF from the same rows, authoring; technical and installation documents render with anchored sections and the live schema, export to PDF, and the menu lists all four deliverables under Support & Maintenance |
| `tests/Feature/NavigationTest.php`, `DashboardChartsTest.php`, `Ifrs9ReportDownloadTest.php`, `AuditFindingM16ScreensTest.php` | Every menu leaf names a registered route, the dashboard charts, report PDF and Excel downloads, and the audit finding M16 screens |
| `tests/Feature/FinancialPeriodTest.php`, `CurrenciesTest.php`, `BranchesTest.php`, `ChartOfAccountsTest.php` | Reference data CRUD |

The test files added on 13 and 14 October 2026 (`git log --since=2026-10-13 --diff-filter=A -- tests/`), all under `tests/`:

| Commit | Files |
|---|---|
| 220a5a6 | `Feature/Eir/WriteOffTest.php` |
| 7a2a96f | `Feature/Imports/ImportTemplateDownloadTest.php`, `Feature/Imports/ImportValidationTest.php`, `Feature/Imports/Concerns/CreatesImportSpecSchema.php` |
| 079a3b6 | `Feature/DashboardChartsTest.php` |
| 216aee2 | `Feature/Ebanker/GlFeeRouteTest.php`, `Feature/Ebanker/InstalmentChartHistoryTest.php`, `Feature/Ecl/MegaFarmProgrammeSeparationTest.php`, `Feature/Eir/TakeonAndTrancheEirInputTest.php`, `Feature/Eir/TrancheScheduleTest.php`, `Feature/Eir/Concerns/CreatesFeeSchema.php` |
| d95317b | `Feature/Eir/FeeClassificationUiTest.php`, `Feature/Eir/MonthEndUiTest.php` |
| 6b57002 | `Feature/Ebanker/InstalmentChartCopiesTest.php`, `Feature/Ecl/TimePhasedEclFloatingRateTest.php`, `Feature/Ecl/TimePhasedEclInvariantTest.php`, `Feature/Ecl/WrittenOffNilEclTest.php`, `Feature/Eir/GoLiveControlsTest.php`, `Feature/Eir/ModificationAccountingTest.php`, `Feature/Eir/RateResetTest.php`, `Feature/Eir/RestructureDetectionWiringTest.php`, `Feature/Eir/RestructureRegisterTest.php`, `Feature/Eir/RestructureUiTest.php`, `Feature/Eir/TrancheSolveStartAndFeeDirectionTest.php`, `Feature/FLI/FliCloseDiscountedRecordTest.php`, `Feature/FLI/FliFitApplicabilityTest.php`, `Feature/Reports/BookedAllowanceAndWriteOffsTest.php`, `Feature/Reports/ModifiedAssetsDisclosureTest.php`, `Concerns/SeedsGovernedDefaults.php` |

Run a focused set during development, for example:

```bash
php artisan test tests/Unit/Eir tests/Feature/Eir tests/Feature/Ecl tests/Feature/Ebanker
php artisan test --filter=HelpManualTest
```

### 11.4 Legacy scaffold tests

The tests inherited from the clinic and loan-origination template (83 tests in the appointments, claim batches, co-payers, expenses, inventory, invoice payments, patients, payment types, SMS gateways, tariffs, tax rates, vitals and loan application mail files) predate this work and are recorded as failing. They are candidates for deletion under ticket #009. Until then the CI pipeline runs the focused EIR suites rather than the whole tree.

### 11.5 Continuous integration

`.github/workflows/ci.yml` runs on every push to a non-master branch, on pull requests to master and when another workflow calls it: PHP 8.2 with the required extensions (including `pdo_sqlite`), Composer install, `.env` from the example with a generated key, `php artisan test tests/Unit/Eir tests/Unit/Support tests/Feature/Eir`, Node 22 and a production asset build. The Ebanker, Ecl, FLI, Imports and Reports suites are not in the CI run and must be run locally before a push. `.github/workflows/deploy.yml` reuses the CI job and, on a push to master, connects to the production host over SSH, fast-forwards master and runs `scripts/deploy.sh`.

### 11.6 Code style and conventions

Laravel Pint is included for formatting (`vendor/bin/pint`). Commit messages carry an imperative title, a body grouped by area, and the verified test count. Every commit that touches user-facing text is swept for em and en dashes. New pages follow the design system (chapter 2.6) and the conventions in chapter 13.
