## 2. Prerequisites

### 2.1 Hardware

Schedule 2 of the agreement proposed the specification in the first column; MAIIC's counter-proposal is in the second and is acceptable for the current loan book of a few thousand contracts provided the tuning in chapter 7 is applied.

| Item | Dupleix proposal | MAIIC counter-proposal |
|---|---|---|
| CPU | 8 vCPU minimum, 16 recommended, base clock 3.5 GHz or higher | 8 vCPU |
| Memory | 64 GB minimum, 128 GB recommended | 32 GB |
| Storage | 2 TB SSD | 1 TB SSD |
| Operating system | Ubuntu 22.04 LTS recommended; Windows Server acceptable if mandated | Ubuntu 22.04 LTS |
| Database | MySQL 8.0 or later, or MariaDB 10.6 or later | As proposed |
| Web server | Nginx or Apache 2.4 or later with PHP-FPM and a valid TLS certificate | As proposed |

A database built by the bootstrap from the committed E-Banker packs (chapter 9.8) is about 1 GB. The application checkout is about 1 GB with its dependencies: the committed client inputs under `docs/bootstrap` (about 100 MB), the Git history (about 100 MB), `vendor` (about 150 MB) and `node_modules` (about 400 MB). The bootstrap also unzips pack 4 (143 MB of CSV files) into `storage/app/bootstrap-packs`. The storage figure is therefore mostly for backups, logs and growth in monthly packs and loan book snapshots.

### 2.2 Software

| Software | Version | Purpose |
|---|---|---|
| PHP | 8.2 or later (8.2 is what CI and the Docker image use). `composer.json` still says `^8.1`, but the locked packages (the Symfony 7 components in `composer.lock`) need 8.2 | Application runtime |
| PHP extensions | bcmath, ctype, curl, dom, fileinfo, gd, iconv, json, mbstring, openssl, pdo, pdo_mysql, simplexml, tokenizer, xml, xmlreader, xmlwriter, zip, zlib, plus opcache | The login security code image (gd), Excel and the zipped E-Banker packs (zip, xmlreader, xmlwriter, gd), PDF (dom, mbstring), performance (opcache). `intl` is not needed |
| Composer | 2.x | PHP dependency manager |
| Node.js and npm | Node 22.12 or later (22 is used in CI and in the Docker image) | Building the front-end assets with Vite. `npm ci` also installs puppeteer 25 (for the manual screenshots), which declares Node 22.12 or later |
| MariaDB or MySQL | as above | Database |
| Nginx or Apache | Nginx 1.18 or later, Apache 2.4 or later | Web server |
| Git | any recent | Cloning and Git-based deployment |
| Supervisor or systemd | | Keeping the queue worker running |
| A Chromium browser (optional) | Edge or Chrome, or the puppeteer bundled build | Only for regenerating manual screenshots on a workstation |

Check the extensions after installing PHP:

```bash
php -m | grep -Ei '^(bcmath|ctype|curl|dom|fileinfo|gd|iconv|json|mbstring|openssl|pdo|pdo_mysql|simplexml|tokenizer|xml|xmlreader|xmlwriter|zip|zlib)$'
php -i | grep -i 'opcache.enable '
```

Every listed extension must appear, and `opcache.enable` must read `On`.

### 2.3 Network and access

| Need | Detail |
|---|---|
| Inbound | TCP 443 (https) from MAIIC's network; TCP 80 only to redirect to https |
| Outbound during installation and deployment | HTTPS to github.com (repository), packagist.org (Composer), registry.npmjs.org (npm) and storage.googleapis.com (the Chrome build that puppeteer downloads during `npm ci`; set `PUPPETEER_SKIP_DOWNLOAD=true` on a server, which never takes screenshots); these can be closed once installed if manual deployment is chosen. `scripts/deploy.sh` runs `composer install` and `npm ci` on every deployment, so Git-based deployment needs them open |
| Outbound in operation | SMTP to MAIIC's mail relay for password resets and notifications; HTTPS to api.worldbank.org for the monthly macro series (optional: without it the committed snapshot in `docs/bootstrap/macro` is used) |
| Remote access for Dupleix | VPN access as requested on 5 August 2026 (clone, Git pull and UAT on the MAIIC host), for the implementation and warranty period |
| Inbound from MAIIC's E-Banker export (optional) | HTTPS to `/api/ebanker-feed/pack` if route 3 is used (chapter 5.1, `EBANKER_FEED_TOKEN`); route 2 instead needs the shared feed folder mounted on the server (`EBANKER_FEED_FOLDER`) |
| Not required | Any path to the E-Banker database; integration is by file (chapter 1.4) |

### 2.4 Accounts and credentials to prepare

- A Linux service account (for example `maiic`) that owns the application files and runs the queue worker.
- A database and database user with full rights on that database only (chapter 3).
- An SMTP account for outbound mail.
- The GitHub deploy key or a personal access token with read access to `DupleixInstitute/MAICC-IFRS9` if Git-based deployment is used.
- The TLS certificate and key for the chosen hostname (for example `ifrs9.maiic.mw`).
- The name and email of the first MAIIC administrator, who will replace the seeded account.
