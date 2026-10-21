#!/bin/sh
set -e

cd /var/www/html

echo "[entrypoint] Attente de PostgreSQL..."
until php -r '
    try {
        new PDO(
            "pgsql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT") ?: "5432").";dbname=".getenv("DB_DATABASE"),
            getenv("DB_USERNAME"),
            getenv("DB_PASSWORD")
        );
        exit(0);
    } catch (Throwable $e) {
        exit(1);
    }
'; do
    sleep 2
done

echo "[entrypoint] Attente de Redis..."
until php -r '
    $socket = @fsockopen(getenv("REDIS_HOST") ?: "redis", 6379, $errno, $errstr, 2);
    if ($socket) {
        fclose($socket);
        exit(0);
    }
    exit(1);
'; do
    sleep 2
done

mkdir -p storage/app storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

if [ "$(id -u)" = "0" ]; then
    php artisan storage:link 2>/dev/null || true
    chown -R www-data:www-data storage bootstrap/cache
fi

echo "[entrypoint] Mise en cache de la configuration, des routes, des vues et des événements..."
php artisan config:cache --ansi
php artisan route:cache --ansi
php artisan view:cache --ansi
php artisan event:cache --ansi

exec "$@"
