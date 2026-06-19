#!/bin/sh
set -e

echo "🔧 Fixing permissions..."

mkdir -p /var/www/storage/framework/cache
mkdir -p /var/www/storage/framework/sessions
mkdir -p /var/www/storage/framework/views
mkdir -p /var/www/storage/logs
touch /var/www/storage/logs/laravel.log

chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R ug+rwX /var/www/storage /var/www/bootstrap/cache
chmod 664 /var/www/storage/logs/laravel.log

if [ "${DB_CONNECTION}" = "pgsql" ]; then
    echo "⏳ Waiting for PostgreSQL..."
    until pg_isready -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USERNAME}" -d "${DB_DATABASE}"; do
        sleep 2
    done
fi

echo "🗄️  Running migrations..."
php artisan migrate --force --no-interaction || echo "⚠️  Migration warning (tables may already exist), continuing..."

exec "$@"
