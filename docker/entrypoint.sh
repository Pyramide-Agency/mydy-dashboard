#!/bin/sh
set -e

cd /var/www/html

# ── APP_KEY ───────────────────────────────────────────────────────────────────
# If not set via env vars, generate one on the fly.
# Auth uses bearer tokens (not sessions), so a fresh key per restart is fine.
# For extra stability, generate once and add APP_KEY to your env vars.
if [ -z "$APP_KEY" ]; then
  APP_KEY="base64:$(openssl rand -base64 32)"
  export APP_KEY
  echo "[entrypoint] Generated APP_KEY. To keep it stable across restarts, add to env vars:"
  echo "  APP_KEY=$APP_KEY"
fi

# ── Wait for PostgreSQL ───────────────────────────────────────────────────────
echo "[entrypoint] Waiting for PostgreSQL at ${DB_HOST}:${DB_PORT:-5432}..."
until pg_isready -h "${DB_HOST}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME}" -q; do
  sleep 2
done
echo "[entrypoint] PostgreSQL is ready."

# ── Laravel bootstrap ─────────────────────────────────────────────────────────
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ── Migrations + Seed ─────────────────────────────────────────────────────────
# Seeder is fully idempotent (firstOrCreate everywhere) — safe to run every boot.
php artisan migrate --force --no-interaction
php artisan db:seed --force --no-interaction

echo "[entrypoint] Starting Supervisor (php-fpm + nginx)..."
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
