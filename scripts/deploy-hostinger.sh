#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_DIR"

PHP_BIN="${PHP_BIN:-php}"

if [[ -n "${COMPOSER_BIN:-}" ]]; then
    COMPOSER_COMMAND="$COMPOSER_BIN"
elif command -v composer2 >/dev/null 2>&1; then
    COMPOSER_COMMAND="composer2"
else
    COMPOSER_COMMAND="composer"
fi

COMPOSER_PATH="$(command -v "$COMPOSER_COMMAND" || true)"

if [[ -z "$COMPOSER_PATH" ]]; then
    echo "Composer 2 was not found. Set COMPOSER_BIN to its full path." >&2
    exit 1
fi

if [[ ! -f .env ]]; then
    echo "Missing .env. Copy .env.hostinger.example to .env and enter the production values." >&2
    exit 1
fi

if ! "$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);'; then
    echo "Davao Rent Zone requires PHP 8.3 or newer. Current CLI: $($PHP_BIN -r 'echo PHP_VERSION;')" >&2
    exit 1
fi

if ! grep -Eq '^APP_ENV=production([[:space:]]*)$' .env; then
    echo "APP_ENV must be production before deploying." >&2
    exit 1
fi

echo "Installing production PHP dependencies..."
"$PHP_BIN" "$COMPOSER_PATH" install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction

if ! grep -Eq '^APP_KEY=.+' .env; then
    echo "Generating the application key for this first deployment..."
    "$PHP_BIN" artisan key:generate --force
fi

mkdir -p \
    bootstrap/cache \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

chmod -R ug+rwX storage bootstrap/cache

echo "Refreshing Laravel caches and database..."
"$PHP_BIN" artisan config:clear
CACHE_STORE=array "$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan migrate --force

if [[ ! -e public/storage ]]; then
    "$PHP_BIN" artisan storage:link
fi

"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

echo "Deployment complete. Check your domain's /up endpoint and login page."
