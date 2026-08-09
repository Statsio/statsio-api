#!/bin/sh
set -e

git config --global --add safe.directory /var/www

# api, queue et scheduler partagent le même volume bind-mounté (le même code source sur l'hôte).
# Si chacun exécute "composer install" à son propre démarrage, trois installs concurrentes
# écrivent dans le même vendor/ en même temps — ça a déjà corrompu vendor/autoload_classmap.php
# en prod. Un seul conteneur (api, via RUN_SETUP=true) prépare le code partagé ; les autres se
# contentent d'attendre que vendor/ existe déjà et d'exécuter leur commande.
if [ "${RUN_SETUP:-false}" = "true" ]; then
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
else
  echo "Waiting for vendor/ to be ready (installed by the api service)..."
  until [ -f /var/www/vendor/autoload.php ]; do
    sleep 1
  done
fi

exec "$@"
