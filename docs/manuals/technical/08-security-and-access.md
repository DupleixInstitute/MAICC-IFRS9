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

The roles matrix groups permissions by their `module` column and labels them by `display_name`; seeders backfill both so no checkbox renders unlabelled. Permissions are owned by seeders: `PermissionsTableSeeder` (base set, inserts only when missing), `TicketsPermissionsSeeder`, `MaiicAdminPermissionsSeeder` (financial periods, currencies) and `LegacyPermissionsCleanupSeeder`, which removed the 56 credit-scoring permissions and the borrower `client` role in August 2026. The admin role is granted every remaining permission. Preparer, reviewer, approver and read-only IFRS 9 roles are planned under ticket #008 and not yet seeded.

Two maker and checker controls exist in code independent of roles: a fee classification cannot be reviewed by its classifier, and an EIR cannot be locked by its calculator. Both accept an override by a user holding the `admin` role, recorded in the audit meta. Reopening a locked EIR is admin-only.

The EIR screens and the settings area share the `settings` permission; dedicated EIR permissions are a recorded open item. Legacy routes whose permissions were deleted now raise a permission-does-not-exist error rather than a clean 403 until ticket #009 removes them.

### 8.3 Transport security and headers

`app/Http/Middleware/SecurityHeaders.php` always sends `X-Content-Type-Options: nosniff`, `X-Frame-Options` and a referrer policy, and adds HSTS on secure requests when enabled. `config/security.php` holds the switches: `FORCE_HTTPS` (all generated URLs use https and http is redirected), `HSTS_ENABLED`, plus `SESSION_SECURE_COOKIE`. All are off by default so local http works; `docs/SSL_SETUP.md` and the Installation Guide describe the order in which to turn them on after a certificate is installed. `public/.htaccess` carries a commented http to https rewrite block.

### 8.4 Sessions and CSRF

Sessions use the database driver (`SESSION_DRIVER=database`) so they survive restarts and can be revoked. Laravel's CSRF token is included in every Inertia form post. When a session expires, the global error modal in `resources/js/Layouts/AppLayout.vue` explains the 419 response and offers the login page; the same modal explains 403, 404, 5xx and connection failures rather than failing silently.

### 8.5 Audit logging

Two stores exist. spatie/laravel-activitylog records model events and explicit `activity()->log()` calls in controllers (for example ticket creation and updates, loan book edits, internal grading). `app/Services/AuditLoggerService.php` writes to `audit_logs` with an action, entity type and id, user and JSON meta; imports, settings changes, EIR rule, fee, schedule, calculation and revenue actions use it. `AuditTrailController` unions the two into one filterable timeline (search, source, user, date range) with a JSON detail viewer. EIR append-only histories (`eir_fee_classification_events`, `eir_calculation_history`, `eir_amortisation_history`) add object-level traceability.

### 8.6 Notifications

`app/Notifications/SystemEventNotification.php` writes database notifications. Ticket creation, updates and comments notify the assignee and creator; workspace checklist changes notify other administrators. The bell in the layout shows the unread count from the shared props, fetches the recent list on open and marks one or all as read.

### 8.7 Data confidentiality

The database holds real, unanonymised MAIIC and FInES customer names and balances. Backups, extracts and screenshots carry the same sensitivity. Screenshots committed for the manuals are taken from the development database; regenerate them from anonymised or demonstration data before publishing a manual outside MAIIC. Never reuse client extracts in training material.
