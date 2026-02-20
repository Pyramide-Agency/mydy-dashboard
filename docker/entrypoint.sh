#!/bin/sh
set -e

cd /var/www/html

# ── Auto-generate APP_KEY if not provided ────────────────────────────────────
# The key is persisted to storage so it survives container restarts.
# storage/ should be on a Dokploy volume — if not, it regenerates each restart
# (harmless for this app since auth uses bearer tokens, not sessions).
if [ -z "$APP_KEY" ]; then
  KEY_FILE="storage/.app_key"
  if [ -f "$KEY_FILE" ]; then
    APP_KEY=$(cat "$KEY_FILE")
    echo "[entrypoint] Loaded APP_KEY from storage."
  else
    APP_KEY="base64:$(openssl rand -base64 32)"
    echo "$APP_KEY" > "$KEY_FILE"
    echo "[entrypoint] Generated APP_KEY and saved to storage."
    echo "[entrypoint] TIP: copy the line below to your Dokploy env vars for stable sessions:"
    echo "  APP_KEY=$APP_KEY"
  fi
  export APP_KEY
fi

# ── Wait for PostgreSQL ───────────────────────────────────────────────────────
echo "[entrypoint] Waiting for PostgreSQL at ${DB_HOST}:${DB_PORT:-5432}..."
until pg_isready -h "${DB_HOST}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME}" -q; do
  sleep 2
done
echo "[entrypoint] PostgreSQL is ready."

# ── Laravel bootstrap ─────────────────────────────────────────────────────────
echo "[entrypoint] Caching config / routes / views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[entrypoint] Running migrations..."
php artisan migrate --force --no-interaction

# ── Seed on first run ─────────────────────────────────────────────────────────
# Uses a marker file in storage/ so seeding only happens once.
SEED_MARKER="storage/.seeded"
if [ ! -f "$SEED_MARKER" ]; then
  echo "[entrypoint] First launch — seeding database (default password: secret)..."
  php artisan db:seed --force --no-interaction
  touch "$SEED_MARKER"
  echo "[entrypoint] Done. Change your password in Settings after first login."
fi

echo "[entrypoint] Starting Supervisor (php-fpm + nginx)..."
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
