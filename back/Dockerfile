# ── Stage 1: Composer dependencies ───────────────────────────────────────────
FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --optimize --no-dev


# ── Stage 2: Runtime ──────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine

# System deps + PHP extensions
RUN apk add --no-cache \
        nginx \
        supervisor \
        postgresql-client \
        libpq-dev \
        libzip-dev \
        zip \
        unzip \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        zip \
        pcntl \
        bcmath \
        opcache

# Opcache tuning for production
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.save_comments=1'; \
} > /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

# Copy vendor + app from stage 1
COPY --from=vendor /app/vendor ./vendor
COPY . .

# Storage & cache directories must exist with correct permissions
RUN mkdir -p storage/logs \
             storage/framework/cache \
             storage/framework/sessions \
             storage/framework/views \
             bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Docker helpers
COPY docker/nginx.conf       /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh    /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
