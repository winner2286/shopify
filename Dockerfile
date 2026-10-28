# Image de base PHP 8.4 FPM
FROM php:8.4-fpm

# Répertoire de travail
WORKDIR /var/www/html

# Paquets système & bibliothèques requises pour Laravel, GD (WebP, JPEG, PNG), PostgreSQL, SQLite, MySQL et Nginx
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libwebp-dev \
    libzip-dev \
    libicu-dev \
    libsqlite3-dev \
    libpq-dev \
    sqlite3 \
    unzip \
    zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        gd \
        pdo \
        pdo_sqlite \
        pdo_pgsql \
        pdo_mysql \
        zip \
        intl \
        bcmath \
        opcache \
        exif \
        pcntl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Récupération de Composer officiel
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configuration PHP personnalisée
COPY docker/php/custom.ini /usr/local/etc/php/conf.d/custom.ini

# Copie des fichiers de dépendances d'abord pour optimiser le cache Docker
COPY composer.json composer.lock* /var/www/html/

# Installation des dépendances Composer de production (sans vendor dev)
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts || true

# Copie du code source complet
COPY . /var/www/html

# Finalisation de l'autoloader Composer
RUN composer dump-autoload --optimize --no-dev

# Script d'entrée et droits
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

# Permissions sur les dossiers de stockage et de cache
RUN mkdir -p /var/www/html/storage/framework/sessions \
             /var/www/html/storage/framework/views \
             /var/www/html/storage/framework/cache \
             /var/www/html/storage/app/public \
             /var/www/html/bootstrap/cache \
             /var/www/html/database \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

# Ports exposés (Render écoute sur 10000 ou $PORT)
EXPOSE 10000 80 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["start-web"]
