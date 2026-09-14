## 11. Testing and Quality

### 11.1 Layout

Tests live under `tests/Unit` and `tests/Feature` and run with PHPUnit (`vendor/bin/phpunit` or `php artisan test`). Feature tests boot the application, use `RefreshDatabase` and act as a factory-built user with the `admin` role. The `UserFactory` assigns a real role and sets `active`, because an inactive or roleless user would be rejected by the middleware before the assertion.

### 11.2 The database guard

`phpunit.xml` forces `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:`. This override must never be commented out. `RefreshDatabase` runs `migrate:fresh` against the default connection, and when the override was once disabled a scaffold test wiped the live development database. Five legacy migrations that used MySQL-only SQL are driver-guarded so the sqlite run completes. Engine SQL that differs by dialect (for example `LEAST` versus `MIN`) is selected at runtime so the stress and coverage tests run on sqlite.

### 11.3 Focused suites

| Suite | Covers |
|---|---|
| `tests/Unit/Eir` | Solver golden numbers (ACADES), zero-yield and invalid inputs, date-sensitive solving, schedule generator, mapped file reader (aliases, unrecognised frequency not guessed, spreadsheet detection by content, non-UTF8 CSV) |
| `tests/Unit/Support/ContractIdTest.php` | Contract id canonicalisation |
| `tests/Feature/Eir` | Intake services for Extracts A, B and C, readiness gate, calculation workflow (maker and checker, lock, reopen), revenue service (13 cases), GL reconciliation bridge, coverage profile agreement with the readiness gate, fee rule sweep, staging classifier ladder, trial balance import and movement rules |
| `tests/Feature/Ecl` | ECL golden number, discount rate resolution, time-phased engine (stage 1, stage 2 lifetime, stage 3 with and without a recovery plan, scenario weighting) |
| `tests/Feature/StressTestingReconciliationTest.php` | Driver arithmetic, caps, macro multiplier derivation, retired hub redirect |
| `tests/Feature/AuthenticationTest.php`, `CaptchaTest.php` | Login with CAPTCHA, wrong, expired, missing and replayed codes, kill switch, PNG output and headers |
| `tests/Feature/TicketsTest.php` | Guest redirect, permission 403, reference sequence, system trail, status changes, seeder idempotency |
| `tests/Feature/HelpManualTest.php`, `SystemDocsTest.php` | Manual reader, drafts hidden, per-route lookup, PDF from the same rows, authoring; technical and installation documents render with anchored sections and the live schema, export to PDF, menu lists all four deliverables |
| `tests/Feature/FinancialPeriodTest.php`, `CurrenciesTest.php`, `BranchesTest.php`, `ChartOfAccountsTest.php` | Reference data CRUD |

Run a focused set during development, for example:

```bash
php artisan test tests/Unit/Eir tests/Feature/Eir tests/Feature/Ecl
php artisan test --filter=HelpManualTest
```

### 11.4 Legacy scaffold tests

About a hundred tests inherited from the clinic and loan-origination template (vitals, patients, appointments, inventory, invoices) fail and predate this work. They are candidates for deletion under ticket #009. Until then the CI pipeline runs the focused EIR suites rather than the whole tree.

### 11.5 Continuous integration

`.github/workflows/ci.yml` runs on every push to a non-master branch and on pull requests to master: PHP 8.2 with the required extensions, Composer install, `.env` from the example with a generated key, the EIR test suites, Node 20 and a production asset build. `.github/workflows/deploy.yml` reuses the CI job and, on a push to master, connects to the production host over SSH, fast-forwards master and runs `scripts/deploy.sh`.

### 11.6 Code style and conventions

Laravel Pint is included for formatting (`vendor/bin/pint`). Commit messages carry an imperative title, a body grouped by area, and the verified test count. Every commit that touches user-facing text is swept for em and en dashes. New pages follow the design system (chapter 2.5) and the conventions in chapter 13.
