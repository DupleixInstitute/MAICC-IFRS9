## 11. Upgrades and deployment

Schedule 2 offers two deployment methods. Git-based deployment is recommended because fixes during UAT and warranty reach the server in minutes.

### 11.1 Git-based deployment

`.github/workflows/deploy.yml` runs on every push to `master`, or by hand (workflow_dispatch), one deployment at a time. It first runs the CI workflow (`.github/workflows/ci.yml`: PHP 8.2, `composer install`, the EIR test suite `php artisan test tests/Unit/Eir tests/Unit/Support tests/Feature/Eir`, Node 22, `npm ci` and `npm run build`); only if that passes does it connect to the server over SSH and run:

```bash
cd -- "$DEPLOY_PATH" && git fetch origin master && git checkout master && git pull --ff-only origin master && bash scripts/deploy.sh
```

Configure these GitHub environment secrets for the `production` environment: `DEPLOY_HOST`, `DEPLOY_PORT` (22 if empty), `DEPLOY_USER`, `DEPLOY_PATH`, `DEPLOY_SSH_KEY` (a private key whose public half is in the service user's `authorized_keys`) and `DEPLOY_KNOWN_HOSTS` (the server's host key line).

`scripts/deploy.sh` takes no arguments. It reads five optional environment variables: `PHP_BIN`, `COMPOSER_BIN` and `NPM_BIN` (default `php`, `composer`, `npm`), `HEALTHCHECK_URL` (empty: no check) and `DEPLOY_LOCK_FILE` (default `/tmp/maiic-ifrs9-deploy.lock`). The workflow passes none of them, so for a GitHub deployment set any that are wanted (and `PUPPETEER_SKIP_DOWNLOAD=true`, chapter 3.3) in the deploy user's environment on the server. It runs with `set -Eeuo pipefail`, so the first failing command stops it, and does this, in order:

1. Changes to the repository root (the folder above `scripts/`).
2. If `flock` is installed, takes the lock file; if another deployment holds it, prints "Another MAIIC deployment is already running." and exits 1. Without `flock` there is no lock.
3. Prints the commit being deployed.
4. `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress`.
5. `npm ci` and `npm run build` (`public/build` is not committed). Steps 4 and 5 run while the site is still up.
6. `php artisan down --retry=60` (maintenance mode).
7. `php artisan migrate --force`, `optimize:clear`, `config:cache`, `route:cache`, `view:cache`, `storage:link --relative` (a failure here is ignored, as the link usually exists) and `queue:restart`.
8. `php artisan up`.
9. If `HEALTHCHECK_URL` is set, `curl --fail --silent --show-error --max-time 30` on it; a failure makes the deployment fail (the site is already up by then).

On any failure an exit trap runs: if maintenance mode is on it runs `php artisan up` so the site is not left down, then it prints "Deployment failed. The application was taken out of maintenance mode." and exits with the failing step's code, which the workflow reports. Note that `git pull` has already replaced the code before the script starts, and steps 4 and 5 run with the site up and no maintenance mode, so a failure there leaves the new code running on whatever dependencies and assets were installed; correct the cause and run the script again. The script does not run any seeder and does not rebuild the event cache: run the update job of chapter 11.6 after it.

### 11.2 Manual deployment

On the server, as the service user:

```bash
cd /var/www/maiic-ifrs9
git fetch origin master && git checkout master && git pull --ff-only origin master
HEALTHCHECK_URL=https://ifrs9.maiic.mw/login PUPPETEER_SKIP_DOWNLOAD=true bash scripts/deploy.sh
php artisan maiic:task update
```

or, step by step, the same commands the script runs. Always take a database backup (chapter 10.2) before a release that contains migrations.

### 11.3 Rollback

Code: check out the previous tag or commit and rerun the deployment script (production then sits on a detached commit; the next deployment checks out `master` again).

```bash
git log --oneline -5
git checkout <previous-commit>
bash scripts/deploy.sh
```

Database: migrations in this project are additive; a code rollback rarely needs a schema rollback. If it does, restore the pre-release backup (chapter 10.3) rather than running `migrate:rollback` on production data.

### 11.4 Release discipline

- Production tracks `master` only. Feature branches merge through pull requests after CI.
- Each release note lists the tickets delivered (the Support Tickets module holds the client-facing record).
- Changes to scope, timeline or fees follow the change request form in Schedule 6 of the agreement (clause 13).
- Post-warranty support is charged per hour under Schedule 8 unless a support arrangement is agreed; response targets are one business day for critical, two for high and five for medium and general issues.

### 11.5 Upgrading platform dependencies

PHP minor versions and Composer packages are updated by Dupleix on a branch, tested in CI, and deployed like any release. A PHP version change (for example 8.2 to 8.4) is planned as a change with a test deployment first.

### 11.6 After every release

`scripts/deploy.sh` applies the migrations but runs no seeder. After every deployment run the update job once, from Administration & support, Support & Maintenance, Maintenance, Install and update tab, "Update the system after a new release", or on the console:

```bash
php artisan maiic:task update
```

Its steps, in order, stopping at the first that fails: `migrate --force` (nothing to do after `deploy.sh`); `permission:cache-reset` (a stale cached permission list made the seeders below fail); `MaiicAdminPermissionsSeeder`; `TicketsPermissionsSeeder` (without it Support Tickets gives 403); `GovernanceSettingsSeeder` (new Governance Centre defaults; approved settings are left as they are); `SicrQualitativeFactorsSeeder`. Every step is additive.

**Manual content.** The User and Administrator Manuals are database content seeded from `database/seeders/data/help_user_content` and its admin counterpart; once a manual has content its seeder leaves it alone, so text edited in the application survives. A release that changes the shipped text reaches an installed manual in one of two ways:

- Refresh the shipped articles and add new ones, keeping articles a person wrote or edited in the application (only articles still marked as written by the seed are rewritten). `HELP_SEED_REFRESH` is read from the process environment, so set it on the command line rather than in `.env` (a cached configuration hides `.env`):

```bash
HELP_SEED_REFRESH=1 php artisan db:seed --class=HelpContentSeeder --force
HELP_SEED_REFRESH=1 php artisan db:seed --class=HelpAdminContentSeeder --force
```

- Replace a manual entirely with the shipped text, discarding every edit made in the application (chapters, articles, steps, figures and page mappings):

```bash
php artisan manual:reseed user --force
php artisan manual:reseed admin --force
```

**Screenshots.** The manual figures are the files in `public/manual/screenshots`, which are committed and arrive with the release. They are retaken on a workstation with `php artisan manual:screenshots` (chapter 4.5), never on the server.

**Reports.** After a release that changes the engines, run Maintenance, "Check the system" (`maiic:task health-check`): the environment, the migration status, the golden numbers (`eir:baselines`) and every report with its PDF.
