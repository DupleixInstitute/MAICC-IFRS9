## 1. Introduction

### 1.1 Scope

This guide explains how to install, configure and maintain the MAIIC IFRS 9 Expected Credit Loss and Effective Interest Rate platform in MAIIC's own environment. It covers the server, the software prerequisites, installation on Linux (the production target) and on Windows with XAMPP (for evaluation and support), the environment file, the web server and TLS, the queue worker and scheduler, the first-run checklist, initial data loading, backup and recovery, upgrades and troubleshooting.

It is contract Schedule 1 deliverable 7. The Technical Manual (deliverable 6) explains how the application is built; the Administrator Manual explains the administration screens; the User Manual explains the analyst workflow. All four are available under System Documentation inside the application.

### 1.2 Audience

MAIIC ICT staff responsible for the server, and Dupleix Institute engineers performing the deployment during implementation and the warranty period. Familiarity with Linux administration, MySQL or MariaDB and a web server is assumed. No Laravel knowledge is required to follow the steps.

### 1.3 Document control

| Item | Value |
|---|---|
| Document | MAIIC IFRS 9 Platform Installation and Configuration Guide |
| Contract reference | Implementation, Licence and Support Agreement, Schedule 1, deliverable 7; environment specification in Schedule 2 |
| Version | 1.0 |
| Status | Draft for MAIIC review |
| Prepared by | Dupleix Institute (Pty) Ltd |
| Document owner | MAIIC Head of ICT |
| Source | `docs/manuals/installation` in the application repository, rendered live in the application and exported to PDF from the same files |

### 1.4 Deployment topologies

| Topology | Use | Notes |
|---|---|---|
| Production, on-premises Linux | The contracted deployment at MAIIC | Ubuntu 22.04 LTS, MariaDB or MySQL, Nginx or Apache with PHP-FPM, a queue worker service, a scheduler cron entry, TLS certificate, Git-based deployment recommended |
| Windows with XAMPP | Evaluation, training and support laptops | Apache and MariaDB from XAMPP, `php artisan serve` for a quick start; not for production |
| Docker | Optional developer environment | `docker-compose.yml` and `docker/` provide PHP 8.2 FPM (with Node 22), Nginx, MySQL 8.0 and Mailhog containers. The supervisor there runs the Vite development server and a queue worker with `--timeout=500`, too short for the long jobs, so it is not a production setup |

Core banking integration is by file, not by a live connection to E-Banker. MAIIC ICT runs the versioned E-Banker queries and delivers the results as a pack (CSV files with a `manifest.json`) by one of three routes: uploaded on the E-Banker Loads screen (route 1), written to a shared folder that the scheduler polls (route 2), or posted over HTTPS with a token to `/api/ebanker-feed/pack` (route 3). Finance uploads the monthly trial balance on the Trial Balances screen. Routes 4 and 5 of specification v4 section 6.5 (a direct Oracle connection and a query runner) are not built, so the application server needs no network path to the core banking database. The earlier Extracts A, B and C are kept only as a record (chapter 9.5).

### 1.5 What the installation delivers

After the steps in this guide the server runs the application at MAIIC's chosen address over https, with the administrator account created, the reference data seeded (currency, permissions, Governance Centre defaults and manual content), the committed E-Banker data loaded and checked by the bootstrap (chapter 9.8), a queue worker processing imports and calculations, the scheduler running, nightly backups in place, and the four documentation deliverables available inside the application and as PDFs.
