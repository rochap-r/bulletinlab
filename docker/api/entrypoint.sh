#!/bin/sh
set -eu

cd /var/www/html

MAX_ATTEMPTS=30
RETRY_DELAY=2

wait_for_postgres() {
    echo "[entrypoint] Vérification de PostgreSQL..."
    attempt=1

    while [ "$attempt" -le "$MAX_ATTEMPTS" ]; do
        if php -r '
            try {
                new PDO(
                    "pgsql:host=".(getenv("DB_HOST") ?: "db").";port=".(getenv("DB_PORT") ?: "5432").";dbname=".getenv("DB_DATABASE"),
                    getenv("DB_USERNAME"),
                    getenv("DB_PASSWORD"),
                    [PDO::ATTR_TIMEOUT => 3]
                );
                exit(0);
            } catch (Throwable $exception) {
                exit(1);
            }
        '; then
            echo "[entrypoint] PostgreSQL disponible."
            return 0
        fi

        echo "[entrypoint] PostgreSQL indisponible ($attempt/$MAX_ATTEMPTS)."
        attempt=$((attempt + 1))
        sleep "$RETRY_DELAY"
    done

    echo "[entrypoint] ERREUR : PostgreSQL inaccessible après $MAX_ATTEMPTS tentatives." >&2
    return 1
}

wait_for_redis() {
    echo "[entrypoint] Vérification de Redis..."
    attempt=1

    while [ "$attempt" -le "$MAX_ATTEMPTS" ]; do
        if php -r '
            $host = getenv("REDIS_HOST") ?: "redis";
            $port = (int) (getenv("REDIS_PORT") ?: 6379);
            $socket = @fsockopen($host, $port, $errorNumber, $errorMessage, 3);

            if ($socket !== false) {
                fclose($socket);
                exit(0);
            }

            exit(1);
        '; then
            echo "[entrypoint] Redis disponible."
            return 0
        fi

        echo "[entrypoint] Redis indisponible ($attempt/$MAX_ATTEMPTS)."
        attempt=$((attempt + 1))
        sleep "$RETRY_DELAY"
    done

    echo "[entrypoint] ERREUR : Redis inaccessible après $MAX_ATTEMPTS tentatives." >&2
    return 1
}

wait_for_postgres
wait_for_redis

mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage bootstrap/cache
fi

echo "[entrypoint] Mise en cache de la configuration, des routes, des vues et des événements..."
php artisan config:cache --ansi
php artisan route:cache --ansi
php artisan view:cache --ansi
php artisan event:cache --ansi

exec "$@"
