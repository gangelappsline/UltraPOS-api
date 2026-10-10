# ─────────────────────────────────────────────────────────────────────────────
# UltraPOS API — PHP 8.3 FPM image
#
# Multi-stage: Composer dependencies are installed in a builder stage so the
# runtime image needs no composer/phar downloads.
#
# NOTE: this repo has no committed composer.lock, so the builder runs
# `composer update` when the lock file is absent (first `docker compose build`
# resolves the versions). Committing a composer.lock makes builds reproducible
# and turns that into `composer install`.
# ─────────────────────────────────────────────────────────────────────────────

# ── Stage 1: vendor tree ─────────────────────────────────────────────────────
FROM composer:2 AS vendor

WORKDIR /app

# Only dependency manifests are copied, so application changes don't invalidate
# this (slow) layer.
COPY composer.json composer.lock* ./

RUN set -eux; \
    if [ -f composer.lock ]; then \
        composer install \
            --no-dev \
            --no-scripts \
            --no-interaction \
            --prefer-dist \
            --optimize-autoloader; \
    else \
        composer update \
            --no-dev \
            --no-scripts \
            --no-interaction \
            --prefer-dist \
            --optimize-autoloader; \
    fi

# ── Stage 2: runtime ─────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS runtime

# System + PHP extensions required by Laravel 12, Passport, MySQL & Redis.
RUN apk add --no-cache \
        bash \
        git \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        intl \
        opcache \
        pdo_mysql \
        zip \
        pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del --no-cache freetype-dev libjpeg-turbo-dev libpng-dev icu-dev libzip-dev oniguruma-dev

# Production PHP/OPcache tuning.
RUN { \
        echo 'memory_limit = 256M'; \
        echo 'upload_max_filesize = 16M'; \
        echo 'post_max_size = 20M'; \
        echo 'max_execution_time = 60'; \
        echo 'date.timezone = UTC'; \
        echo 'expose_php = off'; \
    } > /usr/local/etc/php/conf.d/ultrapos.ini \
    && { \
        echo 'opcache.enable = 1'; \
        echo 'opcache.memory_consumption = 192'; \
        echo 'opcache.interned_strings_buffer = 16'; \
        echo 'opcache.max_accelerated_files = 20000'; \
        echo 'opcache.validate_timestamps = 0'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

# Vendor from the builder stage first (better layer caching).
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY docker/entrypoint.sh /usr/local/bin/ultrapos-entrypoint

# Writable dirs for logs, cache and the Passport keys.
#
# Deliberately NO `php artisan config:cache|route:cache` and NO
# `passport:keys --force` here: docker-compose bind-mounts the project over
# /var/www/html, so anything cached at build time is invisible at runtime, and
# baking one RSA key pair into the image would ship the same OAuth signing key
# to every deployment. The entrypoint generates keys at runtime instead.
RUN mkdir -p bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache \
    && chmod +x /usr/local/bin/ultrapos-entrypoint

USER www-data

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/ultrapos-entrypoint"]
CMD ["php-fpm", "-F"]
