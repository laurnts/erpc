#!/usr/bin/env bash
#
# deploy.sh — update the app on the VM: pull code, install deps, migrate, rebuild caches.

set -euo pipefail
cd "$(dirname "$0")"

php artisan down --retry=60 || true

git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
npm ci --silent
npm run build

php artisan migrate --force
php artisan optimize
php artisan horizon:terminate || true

php artisan up
echo "✓ Deployed $(git rev-parse --short HEAD)"
