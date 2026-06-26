#!/bin/sh
set -e

git config --global --add safe.directory /var/www

echo "Installing dependencies..."
composer install --no-interaction --optimize-autoloader --no-dev

echo "Fixing permissions..."
mkdir -p /var/www/storage/framework/{cache,sessions,views}
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

echo "Running migrations..."
php artisan migrate --force

echo "Seeding admin user..."
php artisan db:seed --class=Database\\Seeders\\AdminUserSeeder --force

echo "Caching config..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Linking storage..."
php artisan storage:link --force

exec "$@"
