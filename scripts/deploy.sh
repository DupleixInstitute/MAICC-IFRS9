#!/usr/bin/env bash

set -Eeuo pipefail

# This script is executed from the existing production Git checkout by
# .github/workflows/deploy.yml. The workflow pulls origin/master first so this
# script always matches the commit being deployed.

APP_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"
HEALTHCHECK_URL="${HEALTHCHECK_URL:-}"
LOCK_FILE="${DEPLOY_LOCK_FILE:-/tmp/maiic-ifrs9-deploy.lock}"
MAINTENANCE_ENABLED=0

cd "$APP_DIR"

if command -v flock >/dev/null 2>&1; then
    exec 9>"$LOCK_FILE"
    if ! flock -n 9; then
        echo "Another MAIIC deployment is already running."
        exit 1
    fi
fi

restore_application() {
    exit_code=$?

    if [[ "$MAINTENANCE_ENABLED" -eq 1 ]]; then
        "$PHP_BIN" artisan up || true
    fi

    if [[ "$exit_code" -ne 0 ]]; then
        echo "Deployment failed. The application was taken out of maintenance mode."
    fi

    exit "$exit_code"
}

trap restore_application EXIT

echo "Deploying $(git rev-parse --short HEAD) in $APP_DIR"

"$COMPOSER_BIN" install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction \
    --no-progress

# public/build is intentionally not committed, so production builds the Vite
# assets after each successful merge to master.
"$NPM_BIN" ci
"$NPM_BIN" run build

"$PHP_BIN" artisan down --retry=60
MAINTENANCE_ENABLED=1

"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan storage:link --relative || true
"$PHP_BIN" artisan queue:restart

"$PHP_BIN" artisan up
MAINTENANCE_ENABLED=0

if [[ -n "$HEALTHCHECK_URL" ]]; then
    curl --fail --silent --show-error --max-time 30 "$HEALTHCHECK_URL" >/dev/null
fi

echo "Deployment completed successfully."

