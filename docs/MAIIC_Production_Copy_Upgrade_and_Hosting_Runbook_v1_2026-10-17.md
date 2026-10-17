---
kicker: MAIIC IFRS 9 and EIR system
title: Upgrading the production copy, and serving the app
subtitle: What was done to maiic_ifrs9 on 17 October 2026, the fixes it needed, and how to repeat it
date: 17 October 2026
prepared: Edward Mazibuko, Director, Data Analytics, Dupleix Institute
people: For the development collaborators and MAIIC ICT
status: Done on maiic_ifrs9 on 17 October 2026. The app is served on this machine at http://localhost:8081. Still in development mode (APP_ENV=local); production mode is a separate decision (section 7).
logo: Dupleix logo.png
justify: yes
toc: yes
---

## 1. What this covers

This runbook covers four things done on 17 October 2026:
1. `maiic_ifrs9`, the production copy, was brought from its 24 September 2026 schema to the current one.
2. December 2025 was landed and built in it, with the 37 audited overrides.
3. Two fixes were needed on the way.
4. The app is now served on this machine.

It also states plainly what in `maiic_ifrs9` a bootstrap can and cannot rebuild.

## 2. The databases on this machine

| Database | Use |
|---|---|
| `maiic_ifrs9` | The production copy; the database `.env` names and the app serves |
| `maiic_ifrs9_candidate_20261017` | Fresh bootstrap of 17 October plus the 37 overrides; where the overrides were first built and proved |
| `maiic_ifrs9_bootstrap` | Throwaway, for clean-install proofs (`--env=bootstrap`, `.env.bootstrap`) |
| `maiic_ifrs9_demo` | Demo (`.env.demo`) |

On Windows `cmd.exe`, `set DB_DATABASE=...` points every following command in that window at another database. It stays in force until the window closes. Every script written for this work prints the database it is writing to before it writes. A script must never hard-code a database: one did, and silently wrote nothing to `maiic_ifrs9` while reporting the candidate's rows as "already approved".

## 3. Bringing maiic_ifrs9 up to date

1. **Back up.** `C:\xampp\mysql\bin\mysqldump.exe -u root --single-transaction --routines --triggers maiic_ifrs9 > C:\xampp\db_backups\maiic_ifrs9_before_migrate_20261017.sql` (8.9 MB). To restore, drop and recreate the database, then load the file with `mysql.exe`.
2. **Migrate.** `php -d memory_limit=2048M artisan migrate --force` ran about 70 pending migrations. The first attempt stopped at the 36th (section 4.1). After the fix, the rest ran.
3. **Check nothing moved.** The 5,460 loan-book rows and the K275,848,962,665 carrying-amount total for January 2024 to November 2025 were identical before and after, and so were the 27 + 162 legacy zero dates. The fix was first proved on a copy restored from the backup.

The platform's safety rules let only Edward run migrations on `maiic_ifrs9`, so each command was handed to him to run from the console.

## 4. The two fixes the upgrade needed

### 4.1 Legacy zero dates

`collateral_types` (27 rows), `loan_books` (162 rows, `old_ifrs9stage_post_qualitative`) and `legacy_loanbook` (1,722 rows) hold `0000-00-00` dates written before MySQL strict mode. A migration that changes a column's type copies the whole table, and strict mode's `NO_ZERO_DATE` refuses those rows (SQLSTATE 22007).

`App\Support\LegacyZeroDates::alter()` runs one such table rebuild with the zero-date checks switched off for that session only, then puts the session's mode back. The rows are copied exactly as they are and no stored value changes. It wraps the three migrations that rebuild those tables: `2026_10_14_010000_collateral_type_code_as_text`, `2026_10_14_220000_go_live_controls` and `2026_10_17_140000_widen_loan_books_build_flag`. SQLite runs the change directly. Any future migration that changes a column type on one of these three tables must use the same wrapper.

### 4.2 The project's root .htaccess

The root `.htaccess` held another project's (`surgerymanager`) `<VirtualHost>` blocks, there since the initial commit. Apache refuses those in an `.htaccess`, so every request under the project folder returned error 500. It now holds Laravel's usual rule sending every request into `public/`.

## 5. Landing and building December 2025 without a bootstrap

`eir:bootstrap` reseeds users and permissions and rebuilds every month from July 2024. In `maiic_ifrs9` that would have overwritten real accounts and the January 2024 to November 2025 loan books. So only the pieces December needed were run:

| Step | Command |
|---|---|
| Settings and landing | `php -d memory_limit=4096M land_ebanker_inputs.php` |
| December build | `php -d memory_limit=4096M artisan eir:build-loan-books 2025-12 2025-12 --user=1 --bootstrap` |
| Overrides | `php artisan governance:uat-mode on --user=1`, `php apply_staging_overrides.php`, `php apply_gross_amount_overrides.php` |
| Rebuild with the overrides | `php -d memory_limit=4096M artisan eir:build-loan-books 2025-12 2025-12 --user=1 --approve-by=1` |
| Check | `php -d memory_limit=2048M artisan staging:december-check` (expect 107 of 107 matched in the same stage) |
| UAT off | `php artisan governance:uat-mode off --user=1` |

`land_ebanker_inputs.php` (project root, committed):
- **Seeds:** runs only the seeders that add what is missing (governance settings, staging thresholds, E-Banker queries).
- **Lands:** puts every committed E-Banker pack through its gates in the bootstrap's order, then the 12 account exclusions MAIIC confirmed. A pack already landed is recognised by its hash.
- **Builds nothing.**

`--bootstrap` records the build's approval as "System Bootstrap (automated data-readiness, not a MAIIC approval)", because self-approval is off by default. Rebuilds need `-d memory_limit=4096M`: the ledger read exceeds PHP's 512 MB default.

Result in `maiic_ifrs9`:
- December 2025: 3,812 loans, K69,201,637,918.
- MUSECO at nil, with K138,135,680.80 kept as its stored amount.
- 36 staging overrides and 1 gross amount override approved.
- Earlier months untouched.

## 6. What a bootstrap can and cannot reproduce

- **Can:** a fresh bootstrap with the decisions pack `2026-10-17-december-2025-audited` reproduces the E-Banker-built months and the 37 overrides (Finance Overlay Importers Specification v2, section 7).
- **Cannot:** it does not reproduce `maiic_ifrs9` as a whole. January 2024 to November 2025 there are older hand imports with no build record, so a bootstrap builds July 2024 onward from the packs instead, with different figures. It cannot replay the order in which this database was migrated and seeded.
- **The reproducible one:** the database that can be rebuilt exactly is the pack-built one. Making `maiic_ifrs9` that database means replacing its older months, which is a separate decision.

## 7. Serving the app on this machine

The app's pages link to their files from the root (`/images/maiic-logo.png`, `/build/...`; 27 such links). Under `http://localhost/MAICC-IFRS9/public/` the pages load but those links break; the login logo was the first one noticed. So Apache serves the app at the root of its own port:
- **Change:** a `Listen 8081` and `<VirtualHost *:8081>` block, with its DocumentRoot at `C:/xampp/htdocs/MAICC-IFRS9/public`, was added to `C:\xampp\apache\conf\extra\httpd-vhosts.conf`.
- **Backup:** the file as it was is kept as `httpd-vhosts.conf.bak-20261017`.
- **Port 80:** the other XAMPP sites are unchanged.

Address: **http://localhost:8081**. Apache is started from the XAMPP Control Panel. The app builds its links from the request, so `APP_URL` (`http://localhost`) was left as it is. It is only used where no request exists, such as e-mailed links.

`maiic_ifrs9` has one user, `admin@localhost.com`, still on the seeded default password `password`. Change it before the database is used by anyone else.

**Production mode** (`APP_ENV=production`, `APP_DEBUG=false`) is not switched on. It would:
- hide error details;
- make UAT self-approval impossible, so every override, build approval and governance change would need a second person;
- ask for confirmation before any migration.

Other sessions on this machine depend on the current mode, so switching is Edward's call.

## 8. Files

| What | Where |
|---|---|
| Zero-date wrapper | `app/Support/LegacyZeroDates.php` and the three migrations in 4.1 |
| Landing without a bootstrap | `land_ebanker_inputs.php` |
| Override scripts | `apply_staging_overrides.php`, `apply_gross_amount_overrides.php` |
| Root rewrite | `.htaccess` |
| Apache port 8081 | `C:\xampp\apache\conf\extra\httpd-vhosts.conf` (machine configuration, not in the repository) |
| Backup | `C:\xampp\db_backups\maiic_ifrs9_before_migrate_20261017.sql` |
