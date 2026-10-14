## 10. Configuration Reference

### 10.1 Environment variables

The first table lists the variables in `.env.example` with their purpose and, where the code supplies one, the default used when the variable is unset. `.env.example` still carries values from the template the platform grew from (an `APP_NAME` of another bank, `DB_DATABASE=credit_scoring`, `BROADCAST_DRIVER=pusher` and a fixed `APP_KEY`); replace them all on a new install. Production values are given in the Installation Guide.

| Variable | Purpose |
|---|---|
| `APP_NAME` | Display name; the fallback company name when the `company_name` setting is empty |
| `APP_ENV` | `local` or `production` (default `production`). The manual-shot CAPTCHA bypass and Telescope work only when it is `local` |
| `APP_KEY` | Encryption key. Generate a new one with `php artisan key:generate` on every install (the value in `.env.example` is not secret) and never change it after data is encrypted |
| `APP_DEBUG` | Default `false`; must be `false` in production |
| `APP_URL` | Base URL used in generated links and by the screenshot and PDF tools |
| `TELESCOPE_ENABLED` | Laravel Telescope. `config/telescope.php` defaults it to `true`, but Telescope is excluded from package discovery and `AppServiceProvider` registers it only when `APP_ENV=local` and this is true; keep `false` |
| `LOG_CHANNEL`, `LOG_LEVEL` | Logging channel (default `stack`) and threshold (default `debug`) |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Database connection. The default connection is `mysql`; unset, its port defaults to 3308 and its database to `ifrs9_maiic` |
| `BROADCAST_DRIVER` | Not used (config default `null`); the Echo and Pusher initialisation in `resources/js/bootstrap.js` is commented out. The `PUSHER_*`, `LARAVEL_WEBSOCKETS_PORT` and `VITE_PUSHER_*` lines are left from the template |
| `CACHE_DRIVER` | Default `file`; `redis` if available |
| `QUEUE_CONNECTION` | `database` (required for imports and EIR jobs); unset, Laravel falls back to `sync` |
| `SESSION_DRIVER`, `SESSION_LIFETIME` | Default `database` and 120 minutes of inactivity before expiry |
| `SESSION_SECURE_COOKIE` | `true` once the site is served over https (commented out in `.env.example`) |
| `MEMCACHED_HOST`, `REDIS_*` | Only if those services are used |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Outbound mail for password resets and notifications; the SMTP values can also be set on the Settings, Email page |
| `AWS_*` | Only if S3 storage is configured |
| `FORCE_HTTPS` | Generate https URLs and redirect http (default `false`) |
| `HSTS_ENABLED` | Send the HSTS header on secure requests (default `false`) |
| `CAPTCHA_ENABLED` | Login CAPTCHA switch (default `true`) |
| `MANUAL_SHOT_EMAIL`, `MANUAL_SHOT_PASSWORD`, `MANUAL_SHOT_CAPTCHA` | Credentials and CAPTCHA bypass code for `manual:screenshots`; the bypass is honoured only when `APP_ENV=local` |
| `VITE_HOST`, `VITE_PORT` | `vite.config.js` reads `VITE_HOST` from the shell environment for the dev server host (default `localhost`); the port is fixed at 5173 and `VITE_PORT` is not read |

These variables are read by the code but are not in `.env.example`; add the ones a deployment needs. `FLI_SIDECAR_URL`, `HELP_SEED_REFRESH`, `MAIIC_BROWSER_PATH` and `MANUAL_SHOT_EMAIL`/`MANUAL_SHOT_PASSWORD` are read with `env()` outside a config file, so once `scripts/deploy.sh` has run `config:cache` a value in `.env` is not seen; set them in the process environment instead.

| Variable | Purpose |
|---|---|
| `EBANKER_FEED_FOLDER` | The shared folder `eir:poll-feed-folder` lands packs from (route 2; `config/services.php`, `services.ebanker_feed.folder`). Unset, the poller and the `refresh-data` job land nothing and say so |
| `EBANKER_FEED_TOKEN` | The bearer token the E-Banker export script must send to `POST /api/ebanker-feed/pack` (route 3; `services.ebanker_feed.api_token`). Unset, every post is refused with 401 |
| `WORLDBANK_COUNTRY` | The ISO3 country of the World Bank import (default `MWI`) |
| `FLI_SIDECAR_URL` | The URL of the optional statistics sidecar `StatisticsSidecar` calls |
| `HELP_SEED_REFRESH` | `true` lets `HelpContentSeeder` and `HelpAdminContentSeeder` bring the shipped articles already in the database up to date; an article a person authored is left alone (default `false`) |
| `CAPTCHA_LENGTH`, `CAPTCHA_TTL` | CAPTCHA characters (default 5) and seconds a code stays valid (default 300) |
| `HSTS_MAX_AGE`, `HSTS_INCLUDE_SUBDOMAINS`, `HSTS_PRELOAD` | HSTS header fields (defaults one year, `true`, `false`) |
| `FILESYSTEM_DRIVER` | The default disk (default `local`); `config/filesystems.php` reads this name, not `FILESYSTEM_DISK`. Uploaded manual figures go to the `public` disk |
| `MAIIC_BROWSER_PATH` | Optional path to Edge or Chrome for `manual:screenshots` when the bundled Chromium is unavailable |
| `DEBUGBAR_ENABLED` | Laravel Debugbar; unset, it follows `APP_DEBUG` |
| `ACTIVITY_LOGGER_ENABLED` | spatie activity log switch (default `true`) |

### 10.2 Application configuration files

| File | Contents |
|---|---|
| `config/menu.php` | The navigation tree (chapter 2.3); the breadcrumbs (chapter 2.4) are built from it |
| `config/services.php` | The E-Banker feed folder and API token, the World Bank country, and the framework's third-party service keys |
| `config/captcha.php` | CAPTCHA enable flag, length, character set, lifetime and the manual-shot bypass code read from the environment |
| `config/security.php` | HTTPS and HSTS switches |
| `config/permission.php` | spatie/laravel-permission tables and cache |
| `config/activitylog.php` | Activity log switch, table and retention (365 days) |
| `config/dompdf.php` | PDF defaults: A4, font directory and cache under `storage/fonts`, remote images enabled for local logo paths |
| `config/excel.php` | maatwebsite/excel reader and writer options |
| `config/fortify.php`, `config/jetstream.php` | Authentication features: registration, password reset, profile and password updates, two-factor authentication with password confirmation; terms and privacy, profile photos and account deletion. Registration is enabled, so `/register` is served |
| `config/queue.php`, `config/session.php`, `config/cache.php` | Framework drivers as above |
| `config/database.php` | Connections; tests use the sqlite connection forced by `phpunit.xml` (chapter 11.2) |
| `config/telescope.php`, `config/debugbar.php` | Development tools, off in production |
| `config/larecipe.php`, `config/websockets.php`, `config/bluedot-sms.php`, `config/widgets.php` | Left from the template: the LaRecipe `/docs` route, the websockets server, the Bluedot SMS gateway and the legacy dashboard widget list (read by `DashboardController`); none is used by the IFRS 9 or EIR modules |

Two settings are read through `config()` without a file to hold them: `maintenance.php_binary` (`CommandRunner`, 9.5) and `fli.sidecar.url` (`StatisticsSidecar`, which then reads `FLI_SIDECAR_URL`). Both fall back, so neither needs a file today.

### 10.3 In-application settings

The Settings area stores organisation-level values in the `settings` table, read through a settings map the Inertia middleware caches for 60 seconds:

| Setting key | Used by |
|---|---|
| `company_name`, `company_logo`, address and contact fields | Page header, PDF headers, manual covers |
| `currency` | The id of the organisation currency shown on the dashboard and reports; set by `MaiicCurrencySeeder` to MWK |
| Email settings | Notification mail |
| Legacy loan template settings (score bands, SMS gateways) | Not used by IFRS 9; consolidation is tracked under ticket #008 |

Reference data maintained under Settings and Portfolio Setup: currencies, chart of accounts, branches, sector types, product groups, loan portfolios and financial periods.

The engines' own parameters are not in `settings` or in the environment: they are governed settings in `governance_settings`, defined by `GovernanceService::catalogue()` (61 keys), seeded with their defaults by `GovernanceSettingsSeeder`, changed by maker-checker on Administration & support, Governance, Governance Centre, and read by date with `GovernanceService::get()`, which fails closed when no approved value applies. A locked period keeps a snapshot of them (`eir:lock-period`, 9.3).

### 10.4 PHP settings that matter

| Setting | Recommended | Why |
|---|---|---|
| `memory_limit` | 1G or more for the web | Large loan book imports and PDF rendering. The screens that read the whole ledger (`LedgerReadLimits::raise()`) raise it to 4 GB for their request, and the Maintenance console launches its commands with 4 GB, so the server needs that much free |
| `max_execution_time` | 300 | The grouped import raises it itself and the whole-ledger screens allow 1,800 seconds; long work belongs in the queue or the Maintenance console |
| `upload_max_filesize`, `post_max_size` | 64M | EIR extract uploads are capped at 20 MB and trial balance, import check and S&P grade uploads at 50 MB by validation; a zipped E-Banker pack has no cap of its own (pack 4 is 12.4 MB). Leave headroom |
| `opcache.enable` | 1 | Without it every request recompiled about 800 files, costing 1.4 seconds |
| Extensions | gd, zip, intl, mbstring, pdo_mysql, fileinfo, curl, openssl, bcmath, xml, dom | CAPTCHA, Excel, pack zips, locale formatting, database |

### 10.5 Database settings that matter

Raise `innodb_buffer_pool_size` to at least 512 MB (XAMPP ships 16 MB), and `max_allowed_packet` to 256 MB before importing a dump. A bootstrapped database holds the landed packs in `ebanker_raw_rows` (the Mega Farm ledger of pack 4 alone is some 300,000 rows), so size the buffer pool to the database rather than to a fixed figure. The hot-path composite indexes added in migration `2026_08_07_200000_add_hot_path_indexes.php` and the covering index on `ebanker_raw_rows` (`2026_10_13_180000_ebanker_raw_rows_covering_index.php`) are part of the schema and need no manual action.
