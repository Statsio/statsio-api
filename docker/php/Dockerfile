FROM php:8.3-fpm

# Dépendances
RUN apt-get update && apt-get install --no-install-recommends -y \
    libonig-dev libzip-dev libpq-dev \
    libpng-dev libjpeg-dev libfreetype6-dev \
    postgresql-client \
    wget unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_pgsql mbstring zip gd \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install DuckDB CLI
RUN wget https://github.com/duckdb/duckdb/releases/download/v1.1.3/duckdb_cli-linux-amd64.zip \
    && unzip duckdb_cli-linux-amd64.zip -d /usr/local/bin \
    && rm duckdb_cli-linux-amd64.zip \
    && chmod +x /usr/local/bin/duckdb

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Limites d'upload alignées avec Nginx pour laisser Laravel valider les fichiers
RUN { \
    echo "upload_max_filesize=120M"; \
    echo "post_max_size=120M"; \
    echo "memory_limit=4096M"; \
    echo "max_execution_time=600"; \
} > /usr/local/etc/php/conf.d/uploads.ini

# Entrypoint
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER www-data

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

EXPOSE 9000
