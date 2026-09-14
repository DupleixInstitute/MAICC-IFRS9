## 10. Configuration Reference

### 10.1 Environment variables

The table lists every variable in `.env.example` with its purpose. Production values are given in the Installation Guide.

| Variable | Purpose |
|---|---|
| `APP_NAME` | Display name and fallback company name |
| `APP_ENV` | `local` or `production`; several safety features (CAPTCHA bypass, debug pages) key off this |
| `APP_KEY` | Encryption key; generate once and never change after data is encrypted |
| `APP_DEBUG` | Must be `false` in production |
| `APP_URL` | Base URL used in generated links and by the screenshot and PDF tools |
| `TELESCOPE_ENABLED` | Laravel Telescope; keep `false`, it does not register when disabled |
| `LOG_CHANNEL`, `LOG_LEVEL` | Logging channel and threshold |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Database connection |
| `BROADCAST_DRIVER` | Not used; Echo and Pusher were removed from the bundle |
| `CACHE_DRIVER` | `file` by default; `redis` if available |
| `QUEUE_CONNECTION` | `database` (required for imports and EIR jobs) |
| `SESSION_DRIVER`, `SESSION_LIFETIME` | `database` and minutes of inactivity before expiry |
| `SESSION_SECURE_COOKIE` | `true` once the site is served over https |
| `MEMCACHED_HOST`, `REDIS_*` | Only if those services are used |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Outbound mail for password resets and notifications; the SMTP values can also be set on the Settings, Email page |
| `AWS_*` | Only if S3 storage is configured |
| `FILESYSTEM_DISK` | `local` in production; uploaded manual figures go to the `public` disk |
| `FORCE_HTTPS` | Generate https URLs and redirect http |
| `HSTS_ENABLED` | Send the HSTS header on secure requests |
| `CAPTCHA_ENABLED` | Login CAPTCHA switch |
| `MANUAL_SHOT_EMAIL`, `MANUAL_SHOT_PASSWORD`, `MANUAL_SHOT_CAPTCHA` | Credentials and CAPTCHA bypass code for `manual:screenshots`; the bypass is honoured only when `APP_ENV=local` |
| `MAIIC_BROWSER_PATH` | Optional path to Edge or Chrome for the screenshot tool when the bundled Chromium is unavailable |
| `VITE_*` | Front-end build variables |

### 10.2 Application configuration files

| File | Contents |
|---|---|
| `config/menu.php` | The navigation tree (chapter 2) |
| `config/captcha.php` | CAPTCHA enable flag, length, lifetime and the manual-shot bypass code read from the environment |
| `config/security.php` | HTTPS and HSTS switches |
| `config/permission.php` | spatie/laravel-permission tables and cache |
| `config/activitylog.php` | Activity log table and retention |
| `config/dompdf.php` | PDF defaults: A4, DejaVu font directory under `storage/fonts`, remote images enabled for local logo paths |
| `config/excel.php` | maatwebsite/excel reader and writer options |
| `config/fortify.php`, `config/jetstream.php` | Authentication features (registration off, two-factor on, profile photos) |
| `config/queue.php`, `config/session.php`, `config/cache.php` | Framework drivers as above |
| `config/database.php` | Connections; tests use the sqlite connection forced by `phpunit.xml` |

### 10.3 In-application settings

The Settings area stores organisation-level values in the `settings` table, read through the cached settings map in the Inertia middleware:

| Setting key | Used by |
|---|---|
| `company_name`, `company_logo`, address and contact fields | Page header, PDF headers, manual covers |
| `currency_id` | The organisation currency shown on the dashboard and reports; set by `MaiicCurrencySeeder` to MWK |
| Email settings | Notification mail |
| Legacy loan template settings (score bands, SMS gateways) | Not used by IFRS 9; consolidation is tracked under ticket #008 |

Reference data maintained under Settings and Portfolio Setup: currencies, chart of accounts, branches, sector types, product groups, loan portfolios and financial periods.

### 10.4 PHP settings that matter

| Setting | Recommended | Why |
|---|---|---|
| `memory_limit` | 1G or more | Large loan book imports and PDF rendering |
| `max_execution_time` | 300 | The grouped import raises it itself; long work belongs in the queue |
| `upload_max_filesize`, `post_max_size` | 64M | Extract uploads are capped at 20 MB by validation; leave headroom |
| `opcache.enable` | 1 | Without it every request recompiled about 800 files, costing 1.4 seconds |
| Extensions | gd, zip, intl, mbstring, pdo_mysql, fileinfo, curl, openssl, bcmath, xml, dom | CAPTCHA, Excel, locale formatting, database |

### 10.5 Database settings that matter

Raise `innodb_buffer_pool_size` to at least 512 MB for a 20 MB database with room to grow (XAMPP ships 16 MB), and `max_allowed_packet` to 256 MB before importing a dump. The hot-path composite indexes added in migration `2026_08_07_200000_add_hot_path_indexes.php` are part of the schema and need no manual action.
