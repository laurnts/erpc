#!/usr/bin/env bash
#
# start.sh — start the erpc project.
#
#   ./start.sh dev     development: Vite hot-reload (HMR)
#   ./start.sh prod    production-style: build assets, serve them
#
# The app is served by nginx -> PHP-FPM (always on). This script handles the
# container stack, node_modules perms, database migrations, and Vite assets.

set -euo pipefail
cd "$(dirname "$0")"

CONTAINERS=(php nginx postgres-erpc redis-erpc)

artisan() { docker exec -uapp -w /var/www/erpc php php artisan "$@"; }

prepare() {
  # 1. make sure containers are up
  for c in "${CONTAINERS[@]}"; do
    docker ps --format '{{.Names}}' | grep -qx "$c" || docker start "$c" >/dev/null 2>&1 || true
  done
  # 2. node deps + restore exec bits (bind mount strips them)
  [ -d node_modules ] || npm install
  find node_modules -type f -path '*/bin/*' -exec chmod u+x {} + 2>/dev/null || true
  # 3. run database migrations
  artisan migrate --force
}

case "${1:-}" in
  dev)
    prepare
    [ -f public/build/manifest.json ] || npm run build   # fallback assets
    trap 'rm -f public/hot' EXIT INT TERM                 # never leave a stale hot file
    echo "▶ Vite dev server — open http://erpc.test (Ctrl-C to stop)"
    npm run dev
    ;;

  prod)
    prepare
    npm run build
    rm -f public/hot
    echo "✓ http://erpc.test serving built assets"
    ;;

  *)
    echo "Usage: ./start.sh [dev|prod]" >&2
    exit 1
    ;;
esac
