#!/bin/sh
set -e

echo "🔧 Fixing permissions..."

mkdir -p /var/www/storage/framework/{cache,sessions,views}

chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

php artisan migrate --force || true

exec "$@"
