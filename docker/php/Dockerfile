# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Stage 1: production Composer dependencies (no dev packages, no scripts).
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY backend/composer.json backend/composer.lock ./
# Extensions are provided by the runtime stage, not by the Composer image.
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --ignore-platform-reqs

COPY backend/ ./
RUN composer dump-autoload --no-dev --no-scripts --optimize --classmap-authoritative

# ---------------------------------------------------------------------------
# Stage 2: runtime (php-fpm), shared by the "app" and "worker" services.
# ---------------------------------------------------------------------------
FROM php:8.3-fpm-alpine AS runtime

# phpredis (C extension) is used instead of predis: faster and no userland
# dependency. pcntl lets queue:work enforce job timeouts and stop gracefully.
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql redis opcache pcntl \
    && apk add --no-cache fcgi \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php/conf.d/app.ini "$PHP_INI_DIR/conf.d/zz-app.ini"
COPY docker/php/php-fpm.d/zz-app.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY --chmod=0755 docker/php/entrypoint.sh /usr/local/bin/app-entrypoint

WORKDIR /var/www/html

COPY --from=vendor --chown=root:root /app /var/www/html

# Code stays root-owned (read-only for the process); only runtime state is writable.
RUN mkdir -p storage/app/private storage/framework/cache/data storage/framework/views storage/logs bootstrap/cache \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

USER www-data

EXPOSE 9000

HEALTHCHECK --interval=10s --timeout=5s --start-period=60s --retries=5 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong || exit 1

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]
