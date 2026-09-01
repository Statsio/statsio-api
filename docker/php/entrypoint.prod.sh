#!/bin/sh
set -e

git config --global --add safe.directory /var/www

# api, queue et scheduler partagent le même volume bind-mounté (le même code source sur l'hôte).
# Si chacun exécute "composer install" à son propre démarrage, trois installs concurrentes
# écrivent dans le même vendor/ en même temps — ça a déjà corrompu vendor/autoload_classmap.php
# en prod. Un seul conteneur (api, via RUN_SETUP=true) prépare le code partagé ; les autres se
# contentent d'attendre que vendor/ existe déjà et d'exécuter leur commande.
READY_MARKER=/var/www/storage/.ready

if [ "${RUN_SETUP:-false}" = "true" ]; then
  # Retiré en premier : le bind mount persiste entre recréations de conteneur, donc un marqueur
  # laissé par un déploiement précédent réussi resterait présent même si CE déploiement échoue
  # en cours de route (set -e arrête le script avant d'atteindre le "touch" plus bas).
  rm -f "$READY_MARKER"

  # Créé AVANT "composer install" : son hook post-autoload-dump lance
  # package:discover + filament:upgrade, qui compilent des vues Blade et
  # exigent que storage/framework/views existe déjà.
  echo "Preparing storage directories..."
  mkdir -p /var/www/storage/framework/{cache,sessions,views}
  chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
  chmod -R 775 /var/www/storage /var/www/bootstrap/cache

  echo "Installing dependencies..."
  export COMPOSER_HTTP2_DISABLE=1
  composer install --no-interaction --optimize-autoloader --no-dev

  echo "Running migrations..."
  php artisan migrate --force

  echo "Seeding admin user..."
  php artisan db:seed --class=Database\\Seeders\\AdminUserSeeder --force

  # Les assets Filament sont déjà (re)publiés par le hook composer `post-autoload-dump`
  # (`filament:upgrade`) juste au-dessus — pas de commande dédiée ici (`filament:assets:publish`
  # n'existe pas ; c'était la cause du crash au démarrage).

  echo "Caching config..."
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  # Cache des composants Filament : pure optimisation, ne doit jamais bloquer un déploiement.
  php artisan filament:optimize || echo "::warning::filament:optimize a échoué (non bloquant)"

  echo "Linking storage..."
  php artisan storage:link --force

  touch "$READY_MARKER"
else
  echo "Waiting for the api service to finish setup..."
  until [ -f "$READY_MARKER" ]; do
    sleep 1
  done
fi

exec "$@"
