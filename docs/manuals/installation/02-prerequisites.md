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

Disk usage today is small (the database is under 100 MB and the application with dependencies about 500 MB), so the storage figure is mostly for backups, logs and growth in loan book snapshots.

### 2.2 Software

| Software | Version | Purpose |
|---|---|---|
| PHP | 8.2 or later (8.1 is the composer minimum) | Application runtime |
| PHP extensions | bcmath, ctype, curl, dom, fileinfo, json, mbstring, openssl, pdo, pdo_mysql, tokenizer, xml, gd (or imagick), zip, intl, plus opcache | CAPTCHA (gd), Excel (zip), locale formatting (intl), performance (opcache) |
| Composer | 2.x | PHP dependency manager |
| Node.js and npm | Node 18 or later (20 used in CI, 24 works) | Building the front-end assets |
| MariaDB or MySQL | as above | Database |
| Nginx or Apache | Nginx 1.18 or later, Apache 2.4 or later | Web server |
| Git | any recent | Cloning and Git-based deployment |
| Supervisor or systemd | | Keeping the queue worker running |
| A Chromium browser (optional) | Edge or Chrome, or the puppeteer bundled build | Only for regenerating manual screenshots on a workstation |

Check the extensions after installing PHP:

```bash
php -m | grep -Ei '^(bcmath|ctype|curl|dom|fileinfo|gd|intl|json|mbstring|openssl|pdo|pdo_mysql|tokenizer|xml|zip)$'
php -i | grep -i 'opcache.enable '
```

Every listed extension must appear, and `opcache.enable` must read `On`.

### 2.3 Network and access

| Need | Detail |
|---|---|
| Inbound | TCP 443 (https) from MAIIC's network; TCP 80 only to redirect to https |
| Outbound during installation and deployment | HTTPS to github.com (repository), packagist.org (Composer), registry.npmjs.org (npm); these can be closed once installed if manual deployment is chosen |
| Outbound in operation | SMTP to MAIIC's mail relay for password resets and notifications |
| Remote access for Dupleix | VPN access as requested on 5 August 2026 (clone, Git pull and UAT on the MAIIC host), for the implementation and warranty period |
| Not required | Any path to the E-Banker database; integration is by file extract |

### 2.4 Accounts and credentials to prepare

- A Linux service account (for example `maiic`) that owns the application files and runs the queue worker.
- A database and database user with full rights on that database only (chapter 3).
- An SMTP account for outbound mail.
- The GitHub deploy key or a personal access token with read access to `DupleixInstitute/MAICC-IFRS9` if Git-based deployment is used.
- The TLS certificate and key for the chosen hostname (for example `ifrs9.maiic.mw`).
- The name and email of the first MAIIC administrator, who will replace the seeded account.
