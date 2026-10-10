# ─────────────────────────────────────────────────────────────────────────────
# UltraPOS API — PHP 8.3 FPM image
# Multi-stage: composer assets are installed in a builder stage to keep the
# final image lean and to avoid shipping credentials.
# ─────────────────────────────────────────────────────────────────────────────

# ── Stage 1: install PHP + JS-free dependencies via Composer ─────────────────
FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader

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

RUN chown -R www-data:www-data storage bootstrap/cache \
    && php artisan passport:keys --force 2>/dev/null || true \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan event:cache

# Non-root healthcheck.
USER www-data

EXPOSE 9000

CMD ["php-fpm", "-F"]
