#!/usr/bin/env bash
#
# deploy.sh — update the app on the VM: pull code, install deps, migrate, rebuild caches.
#
# Fetch/build steps run BEFORE maintenance mode so their failures leave the
# site up; only the migrate/cache window runs under `artisan down`, and a
# trap guarantees `artisan up` even if that window fails.

set -euo pipefail
cd "$(dirname "$0")"

git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
npm ci --silent
npm run build

php artisan down --retry=60 || true
trap 'php artisan up || true' EXIT

php artisan migrate --force
php artisan optimize
php artisan horizon:terminate || true

echo "✓ Deployed $(git rev-parse --short HEAD)"
