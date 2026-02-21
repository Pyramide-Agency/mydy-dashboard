#!/bin/sh
set -e

# Print each command before running — helps trace where a failure occurred
set -x

cd /var/www/html

# Trap any error and print a clear message
trap 'echo "[entrypoint] ERROR: command failed at line $LINENO (exit code $?)" >&2' ERR

# ── Force Laravel to log to stderr (visible in Dokploy / docker logs) ─────────
export LOG_CHANNEL=stderr
export LOG_LEVEL=debug

# ── APP_KEY ───────────────────────────────────────────────────────────────────
if [ -z "$APP_KEY" ]; then
  APP_KEY="base64:$(openssl rand -base64 32)"
  export APP_KEY
  echo "[entrypoint] Generated APP_KEY. Add to env vars for stability:"
  echo "  APP_KEY=$APP_KEY"
fi

# ── Debug: print all relevant env vars ────────────────────────────────────────
set +x
echo "========== [entrypoint] ENV VARS =========="
echo "  APP_ENV         = ${APP_ENV:-NOT SET}"
echo "  APP_KEY         = ${APP_KEY:0:16}... (truncated)"
echo "  DB_CONNECTION   = ${DB_CONNECTION:-NOT SET}"
echo "  DB_HOST         = ${DB_HOST:-NOT SET}"
echo "  DB_PORT         = ${DB_PORT:-NOT SET}"
echo "  DB_DATABASE     = ${DB_DATABASE:-NOT SET}"
echo "  DB_USERNAME     = ${DB_USERNAME:-NOT SET}"
echo "  LOG_CHANNEL     = ${LOG_CHANNEL}"
echo "==========================================="
set -x

# ── Wait for PostgreSQL ───────────────────────────────────────────────────────
echo "[entrypoint] Waiting for PostgreSQL at ${DB_HOST}:${DB_PORT:-5432}..."
until pg_isready -h "${DB_HOST}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME}" -q; do
  sleep 2
done
echo "[entrypoint] PostgreSQL is ready."

# ── Laravel bootstrap ─────────────────────────────────────────────────────────
php artisan config:cache -v
php artisan route:cache -v
php artisan view:cache -v

# ── Migrations + Seed ─────────────────────────────────────────────────────────
php artisan migrate --force --no-interaction -v
php artisan db:seed --force --no-interaction -v

echo "[entrypoint] Starting Supervisor (php-fpm + nginx)..."
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
