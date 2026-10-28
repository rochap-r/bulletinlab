# syntax=docker/dockerfile:1

# Binaire composer seul ; le stage vendor utilise php:8.4-cli pour que la
# plateforme de résolution soit identique au runtime (php:8.4-fpm).
FROM composer:2 AS composer-bin

##########
# Stage 1 — PHP dependencies (composer)
##########
FROM php:8.4-cli AS vendor
COPY --from=composer-bin /usr/bin/composer /usr/bin/composer
# unzip/git : extraction des paquets dist par composer (ext-zip absent de ce stage).
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip git \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY composer.json composer.lock ./
# ext-gd et ext-zip sont exigés par phpoffice/phpspreadsheet mais absents de
# l'image CLI du stage vendor : ils sont installés dans l'image finale (stage
# runtime), seule leur installation est différée.
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --no-interaction \
    --prefer-dist \
    --ignore-platform-req=ext-gd \
    --ignore-platform-req=ext-zip
COPY . .
# Purge les caches Laravel de la machine dev : packages.php/services.php y
# référencent des dépendances dev (ex. Laravel\Boost) absentes de l'image
# --no-dev, ce qui casse le boot. L'entrypoint les régénère au démarrage.
RUN rm -f bootstrap/cache/*.php \
    && composer dump-autoload --optimize --no-scripts

##########
# Stage 2 — Laravel assets (vite: page Blade "welcome")
##########
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

##########
# Stage 3 — Runtime (php-fpm, sert aussi CLI queue/scheduler)
##########
FROM php:8.4-fpm

# pdo_pgsql (PostgreSQL), zip (maatwebsite/excel), bcmath (calculs décimaux),
# pcntl (workers queue: signaux), opcache (perf), redis (cache/queue/sessions),
# gd (phpoffice/phpspreadsheet : images dans les fichiers Excel), intl (i18n)
RUN apt-get update && apt-get install -y --no-install-recommends \
        $PHPIZE_DEPS \
        libzip-dev \
        libpq-dev \
        libpng-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql zip bcmath pcntl opcache gd intl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*

COPY docker/api/zz-app.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

# Les caches Laravel sont générés au démarrage (entrypoint), pas ici :
# config:cache fige les variables d'environnement, donc seulement en runtime.
RUN php artisan package:discover --ansi

COPY docker/api/entrypoint.sh /usr/local/bin/app-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/app-entrypoint \
    && chmod +x /usr/local/bin/app-entrypoint \
    && find /var/www/html/app /var/www/html/config /var/www/html/routes \
        /var/www/html/bootstrap /var/www/html/database /var/www/html/resources \
        /var/www/html/public /var/www/html/vendor \
        -type d -exec chmod 755 {} + \
    && find /var/www/html/app /var/www/html/config /var/www/html/routes \
        /var/www/html/bootstrap /var/www/html/database /var/www/html/resources \
        /var/www/html/public /var/www/html/vendor \
        -type f -exec chmod 644 {} + \
    && chmod 644 /var/www/html/artisan \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]
