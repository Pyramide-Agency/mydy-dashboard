# ─────────────────────────────────────────────────────────────────────────────
# Stage 1 — Build Nuxt SPA (static files)
# ─────────────────────────────────────────────────────────────────────────────
FROM node:20-alpine AS frontend

WORKDIR /frontend

COPY front/package.json front/package-lock.json ./
RUN npm ci

COPY front/ .

# NUXT_PUBLIC_API_BASE is embedded into the static bundle at build time.
# With a single-container setup both front and back share the same domain,
# so the API is always reachable at /api relative to the browser origin.
ARG NUXT_PUBLIC_API_BASE=/api
ENV NUXT_PUBLIC_API_BASE=$NUXT_PUBLIC_API_BASE

RUN npm run generate
# Output: /frontend/.output/public/


# ─────────────────────────────────────────────────────────────────────────────
# Stage 2 — Install Composer dependencies
# ─────────────────────────────────────────────────────────────────────────────
FROM composer:2 AS backend

WORKDIR /backend

COPY back/composer.json back/composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --ignore-platform-reqs

COPY back/ .
RUN composer dump-autoload --optimize --no-dev


# ─────────────────────────────────────────────────────────────────────────────
# Stage 3 — Runtime: PHP-FPM + Nginx (served by Supervisor)
# ─────────────────────────────────────────────────────────────────────────────
FROM php:8.4-fpm-alpine

# System packages
RUN apk add --no-cache \
        nginx \
        supervisor \
        postgresql-client \
        libpq-dev \
        libzip-dev \
        zip \
        unzip \
        curl \
        openssl \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        zip \
        pcntl \
        bcmath \
        opcache

# OPcache — production settings
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.save_comments=1'; \
} > /usr/local/etc/php/conf.d/opcache.ini

# ── Laravel ──────────────────────────────────────────────────────────────────
WORKDIR /var/www/html

COPY --from=backend /backend/vendor ./vendor
COPY back/ .

RUN mkdir -p \
        storage/logs \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# ── Nuxt static files ─────────────────────────────────────────────────────────
COPY --from=frontend /frontend/.output/public /var/www/frontend

# ── Config files ──────────────────────────────────────────────────────────────
COPY docker/nginx.conf       /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh    /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

# Dokploy waits for the container to become healthy before routing traffic.
# start-period covers Nuxt build time + migrations on first boot.
HEALTHCHECK --interval=15s --timeout=5s --start-period=90s --retries=3 \
  CMD curl -fsS http://localhost/api/health || exit 1

ENTRYPOINT ["/entrypoint.sh"]
