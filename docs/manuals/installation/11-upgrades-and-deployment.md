## 11. Upgrades and Deployment

Schedule 2 offers two deployment methods. Git-based deployment is recommended because fixes during UAT and warranty reach the server in minutes.

### 11.1 Git-based deployment

`.github/workflows/deploy.yml` runs on every push to `master` after the CI tests pass. It connects to the server over SSH and runs:

```bash
cd $DEPLOY_PATH && git fetch origin master && git checkout master && git pull --ff-only origin master && bash scripts/deploy.sh
```

Configure these GitHub environment secrets for the `production` environment: `DEPLOY_HOST`, `DEPLOY_PORT`, `DEPLOY_USER`, `DEPLOY_PATH`, `DEPLOY_SSH_KEY` (a private key whose public half is in the service user's `authorized_keys`) and `DEPLOY_KNOWN_HOSTS` (the server's host key line). Optionally set `HEALTHCHECK_URL` on the server so the script verifies the site after bringing it up.

`scripts/deploy.sh` then: takes a lock so two deployments cannot overlap; enables maintenance mode; runs `composer install --no-dev --optimize-autoloader`; runs `npm ci` and `npm run build`; runs `php artisan migrate --force`; rebuilds the config, route, view and event caches; restarts the queue workers; disables maintenance mode; and, if a health check URL is set, requests it and fails the deployment when it does not return success. If any step fails the script brings the site back up and exits non-zero, which the workflow reports.

### 11.2 Manual deployment

On the server, as the service user:

```bash
cd /var/www/maiic-ifrs9
git fetch origin master && git checkout master && git pull --ff-only origin master
bash scripts/deploy.sh
```

or, step by step, the same commands the script runs. Always take a database backup (chapter 10.2) before a release that contains migrations.

### 11.3 Rollback

Code: check out the previous tag or commit and rerun the deployment script.

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

PHP minor versions and Composer packages are updated by Dupleix on a branch, tested in CI, and deployed like any release. A PHP major version change (for example 8.2 to 8.4) is planned as a change with a test deployment first.
