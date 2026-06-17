# syntax=docker/dockerfile:1
# -----------------------------------------------------------------------------
# Madeena IAM - Production Dockerfile
# PHP 8.4 FPM + Nginx + Supervisor, with Composer and Vite builds inside Docker.
# -----------------------------------------------------------------------------

# Stage 0: Composer dependency builder
FROM php:8.4-cli AS composer-deps

RUN apt-get update -qq \
    && DEBIAN_FRONTEND=noninteractive apt-get install -yqq --no-install-recommends unzip git libzip-dev ca-certificates curl \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install zip > /dev/null 2>&1

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/root/.composer/cache,sharing=locked \
    composer install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --prefer-dist \
        --ignore-platform-reqs \
        --no-scripts \
        --quiet

# Stage 1: Node / Vite asset builder
FROM node:24-alpine AS node-builder

WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
COPY resources/ ./resources/
RUN --mount=type=cache,target=/root/.npm,sharing=locked \
    npm ci --no-audit --no-fund --loglevel=error \
    && npm run build

# Stage 2: Base image (system deps + PHP extensions)
FROM php:8.4-fpm AS base

RUN apt-get update -qq && \
    DEBIAN_FRONTEND=noninteractive apt-get install -yqq --no-install-recommends \
        nginx \
        supervisor \
        curl \
        ca-certificates \
        zip \
        unzip \
        git \
        > /dev/null 2>&1 \
    && rm -rf /var/lib/apt/lists/*

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions gd zip intl opcache pcntl pdo_mysql bcmath
# docker-php-ext-install


# Stage 3: Application image
FROM base AS app

LABEL maintainer="Madeena Software"
LABEL description="Madeena IAM - Laravel 12 + Filament v3"

ARG APP_VERSION=dev
ENV APP_VERSION=${APP_VERSION}

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/99-custom.ini"

COPY docker/nginx.conf /etc/nginx/sites-available/default
RUN ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default \
    && rm -f /etc/nginx/sites-enabled/000-default 2>/dev/null || true

RUN mkdir -p /var/log/supervisor
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --from=composer-deps --chown=www-data:www-data /app/vendor ./vendor
COPY --from=node-builder --chown=www-data:www-data /app/public/build ./public/build

RUN rm -f bootstrap/cache/*.php

RUN mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/framework/testing \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

RUN php artisan package:discover --ansi
RUN php artisan filament:assets --ansi
RUN if [ -n "${APP_VERSION}" ]; then printf '%s' "${APP_VERSION}" > /var/www/html/VERSION || true; fi

RUN rm -rf storage/framework/views/*.php storage/framework/cache/data/* bootstrap/cache/*.php

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

HEALTHCHECK --interval=30s --timeout=5s --start-period=150s --retries=3 \
    CMD php -r '$s=@fsockopen("127.0.0.1",9000);if(!$s)exit(1);fclose($s);' || exit 1

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
