#!/bin/sh
set -e

# ── Wait for PostgreSQL ───────────────────────────────────────────────────────
echo "[entrypoint] Waiting for PostgreSQL at ${DB_HOST}:${DB_PORT:-5432}..."
until pg_isready -h "${DB_HOST}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME}" -q; do
  sleep 2
done
echo "[entrypoint] PostgreSQL is ready."

# ── Laravel bootstrap ─────────────────────────────────────────────────────────
cd /var/www/html

echo "[entrypoint] Caching config / routes / views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[entrypoint] Running migrations..."
php artisan migrate --force --no-interaction

echo "[entrypoint] Starting Supervisor (php-fpm + nginx)..."
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
