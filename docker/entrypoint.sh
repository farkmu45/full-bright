#!/bin/sh
set -e

cd /var/www/html

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is empty. Generate one with: docker compose run --rm app php artisan key:generate --show" >&2
    exit 1
fi

# wait for MySQL before migrating (checked through PDO, the same driver Laravel uses)
until php -r '
    try {
        new PDO(
            "mysql:host=" . getenv("DB_HOST") . ";port=" . (getenv("DB_PORT") ?: 3306),
            getenv("DB_USERNAME"),
            getenv("DB_PASSWORD"),
        );
    } catch (Throwable $e) {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        exit(1);
    }
'; do
    echo "waiting for database at $DB_HOST..."
    sleep 2
done

php artisan migrate --force
php artisan optimize

exec "$@"
