## 8. Security and Access Control

### 8.1 Authentication

Login is handled by Laravel Fortify under Jetstream. The login pipeline is customised in `app/Providers/FortifyServiceProvider.php` through `Fortify::authenticateThrough`, which inserts `app/Actions/Fortify/VerifyLoginCaptcha.php` as a dedicated stage so the CAPTCHA is checked exactly once per attempt (Fortify calls the credential callback twice when two-factor authentication is enabled, which is why the check does not live there).

The CAPTCHA (`app/Http/Controllers/CaptchaController.php`, `config/captcha.php`) is self-hosted: a GD image with the code held in the session, single use, case-insensitive, with a freshness window and no-store headers. `CAPTCHA_ENABLED` switches it off. `MANUAL_SHOT_CAPTCHA` defines a bypass code that is honoured only in the local environment so the screenshot robot can sign in; it is inert in production.

Password rules are in `app/Actions/Fortify/PasswordValidationRules.php`. Two-factor authentication, browser session management and profile photo handling come from Jetstream. Inactive users are rejected by `app/Http/Middleware/CheckIfUserIsActive.php` even with valid credentials.

### 8.2 Authorisation

spatie/laravel-permission provides roles and permissions with the `web` guard. Permission names follow `module.action`, for example `users.roles.update`, `tickets.destroy`, `reports.ifrs9`, `settings`, `manual.view`. Controllers declare their gates in the constructor:

```php
$this->middleware(['permission:tickets.index'])->only(['index', 'show']);
```

The roles matrix groups permissions by their `module` column and labels them by `display_name`; seeders backfill both so no checkbox renders unlabelled. Permissions are owned by seeders: `PermissionsTableSeeder` (base set, inserts only when missing), `RolesTableSeeder` (the `admin` role, synced to every permission that exists when it runs), `TicketsPermissionsSeeder` (`tickets`, `tickets.index`, `tickets.create`, `tickets.update`, `tickets.destroy`), `MaiicAdminPermissionsSeeder` (financial periods, currencies, and the EIR and macro permissions below) and `LegacyPermissionsCleanupSeeder`, which removed the 56 credit-scoring permissions and the borrower `client` role in August 2026. `TicketsPermissionsSeeder` and `MaiicAdminPermissionsSeeder` are idempotent and grant their permissions to every role except the portal roles `client`, `member` and `patient`. Both run in `php artisan eir:bootstrap` and in `maiic:task update`, which clears the permission cache first (until 14 October 2026 a fresh install gave 403 on Support Tickets). Preparer, reviewer, approver and read-only IFRS 9 roles are planned under ticket #008 and not yet seeded.

EIR and forward-looking permissions are seeded by `MaiicAdminPermissionsSeeder`:

| Permission | What it opens |
|---|---|
| `eir.view` | The read-only EIR screens: EIR Data and its tabs, Imports & Feeds, Take-on, coverage, EIR as at a Date, GL Reconciliation; the Macro Statistics screen as an alternative to `macro.view` |
| `eir.govern` | The Governance Centre; landing packs, loan book builds, period lock and unlock, trial balance upload, take-on actions, write-offs, restructures and rate resets, tying a held GL fee line, staging thresholds, the compliance audit sign-off and the Mega Farm run; on the forward-looking screens proposing, approving, applying the route, running a month-end step, signing off and retiring a model version (the view permissions are listed in chapter 5.5); committing macro data as an alternative to `macro.manage` |
| `eir.export` | The GL reconciliation export and the auditor pack |
| `eir.run` | Running the forward-looking correlation analysis |
| `eir.fees.import` | Finance loading fees by loan without `settings`: EIR Data Intake for the fee type only, and reading Fee Classification and its fee GL check |
| `macro.view`, `macro.manage` | Macro Statistics: the screen, previews and export; a commit, a manual entry or a variable change |

The EIR rules, classification, schedule and calculation screens and the settings area still use `settings` (chapter 6.2). The IFRS 9 reports hub tiles and the RBM return use `reports.ifrs9`; the hub index also opens to `reports` or `eir.view`. The Maintenance console needs `settings` to open and the `admin` role to run a command (chapter 9.5). Legacy routes whose permissions were deleted now raise a permission-does-not-exist error rather than a clean 403 until ticket #009 removes them.

Maker and checker controls exist in code independent of roles: the person who proposes cannot approve. Where a governed administrator override is shown, a user with the `admin` role may approve their own work only while the Governance Centre setting `maker_checker_admin_override` is "Allowed for administrators" (`GovernanceService::adminOverrideAllowed`); its default is "Not allowed", and the override is recorded in the audit meta.

| Control | Rule | Override |
|---|---|---|
| Fee classification review | The reviewer is not the classifier | Governed administrator override |
| EIR lock | The approver is not the calculator, and the calculation has a named maker | Governed administrator override; the bootstrap label (`contract_eir.locked_label`) |
| EIR reopening | A reason of at least ten characters; no month the contract reached may be locked | Requires the governed administrator override |
| Governance setting change | The approver is not the proposer | Governed administrator override |
| Version 1 schedule approval | Under `schedule_approval_control` "Second person must approve" (default), the approver is named and is not the preparer | The bootstrap label |
| Loan book build | The approver is not the requester; locked periods are refused | The bootstrap label |
| Take-on build | Approved by a different named person from the proposer, for the workbook still landed last | The bootstrap label |
| Write-off register | The approver is not the proposer; the period lock is checked at proposal, approval and reversal | None |
| Restructure register and rate resets | The approver is not the proposer; only the proposer withdraws; the period must be open | Governed administrator override |
| Staging thresholds | Proposer and approver are named and different; a change takes effect after any locked period | None |
| Qualitative SICR trigger | The approver is not the person who raised it | Governed administrator override; the bootstrap label |
| Forward-looking chain (chapter 5.5) | A fit, an external model version, a scenario set and an overlay are not approved by their proposer; a month record is not signed off by the person who recorded it | None; the console's `--bootstrap` option on `fli:apply` and `scenario:sets` records the approval under the label |

The bootstrap label is "System Bootstrap (automated data-readiness, not a MAIIC approval)" (`LoanBookBuildService::BOOTSTRAP_LABEL`, `ScenarioSetService::BOOTSTRAP_LABEL`), stored on the approved row so that a demonstration approval is never mistaken for a MAIIC one; any other label is refused. EIR accounting rule approval has no maker and checker gate. The Workspace "Awaiting approval" panel counts what waits for a second person in most of these workflows and links to each screen; restructures, rate resets, take-on builds and staging thresholds are not yet listed there.

A locked reporting period (`eir:lock-period`, or Imports & Feeds, Periods Built, needing `eir.govern`) is never restated: every engine that writes a period calls `ReportingPeriodLock::assertOpen` first (chapter 6.16). Locking and unlocking each need a reason and are audit-logged; unlocking is one person's act with no second approval.

### 8.3 Transport security and headers

`app/Http/Middleware/SecurityHeaders.php` always sends `X-Content-Type-Options: nosniff`, `X-Frame-Options` and a referrer policy, and adds HSTS on secure requests when enabled. `config/security.php` holds the switches: `FORCE_HTTPS` (all generated URLs use https and http is redirected), `HSTS_ENABLED`, plus `SESSION_SECURE_COOKIE`. All are off by default so local http works; `docs/SSL_SETUP.md` and the Installation Guide describe the order in which to turn them on after a certificate is installed. `public/.htaccess` carries a commented http to https rewrite block.

### 8.4 Sessions and CSRF

Sessions use the database driver (`SESSION_DRIVER=database`) so they survive restarts and can be revoked. Laravel's CSRF token is included in every Inertia form post. When a session expires, the global error modal in `resources/js/Layouts/AppLayout.vue` explains the 419 response and offers the login page; the same modal explains 403, 404, 5xx and connection failures rather than failing silently.

### 8.5 Audit logging

Two stores exist. spatie/laravel-activitylog records model events and explicit `activity()->log()` calls in controllers (for example ticket creation and updates, loan book edits, internal grading). `app/Services/AuditLoggerService.php` writes to `audit_logs` with an action, entity type and id, user and JSON meta; imports, settings changes, EIR rule, fee, schedule, calculation and revenue actions use it, as do the governed registers (write-offs, restructure classifications and confirmations, restructures applied by the revenue run, rate resets), take-on and loan book build proposals and approvals, GL fee lines tied by hand, and period locks and unlocks. `AuditTrailController` unions the two into one filterable timeline (search, source, user, date range) with a JSON detail viewer. EIR append-only histories (`eir_fee_classification_events`, `eir_calculation_history`, `eir_amortisation_history`) add object-level traceability.

### 8.6 Notifications

`app/Notifications/SystemEventNotification.php` writes database notifications. Ticket creation, updates and comments notify the assignee and creator; workspace checklist changes notify other administrators. The bell in the layout shows the unread count from the shared props, fetches the recent list on open and marks one or all as read.

### 8.7 Data confidentiality

The database holds real, unanonymised MAIIC and FInES customer names and balances. Backups, extracts and screenshots carry the same sensitivity. Screenshots committed for the manuals are taken from the development database; regenerate them from anonymised or demonstration data before publishing a manual outside MAIIC. Never reuse client extracts in training material.
