# syntax=docker/dockerfile:1

############################################
# Composer dependencies
############################################
FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

############################################
# Runtime: PHP-FPM + Nginx (listens on 8080)
############################################
FROM serversideup/php:8.4-fpm-nginx

# Git commit the image was built from (set by the GitHub workflow).
ARG APP_VERSION=dev

ENV APP_VERSION=${APP_VERSION} \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/data/codenames.sqlite \
    SESSION_DRIVER=file \
    CACHE_STORE=file \
    PHP_OPCACHE_ENABLE=1 \
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=true

USER root
COPY --chmod=755 docker/entrypoint.d/ /etc/entrypoint.d/
RUN mkdir -p /data && chown www-data:www-data /data
USER www-data

COPY --chown=www-data:www-data --from=vendor /app /var/www/html

RUN php artisan package:discover --ansi

VOLUME ["/data"]
EXPOSE 8080
