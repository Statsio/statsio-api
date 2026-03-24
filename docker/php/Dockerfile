FROM php:8.3-fpm

# Dépendances
RUN apt-get update && apt-get install -y \
    git curl zip unzip \
    libpng-dev libonig-dev libxml2-dev libzip-dev \
    default-mysql-client \
    nodejs npm \
    && docker-php-ext-install \
        pdo pdo_mysql mbstring zip exif pcntl \
    && docker-php-ext-enable pdo_mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Limites d'upload alignées avec Nginx pour laisser Laravel valider les fichiers
RUN { \
    echo "upload_max_filesize=120M"; \
    echo "post_max_size=120M"; \
} > /usr/local/etc/php/conf.d/uploads.ini

# Entrypoint
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

EXPOSE 9000
