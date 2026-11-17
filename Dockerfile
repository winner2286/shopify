# Image de base PHP 8.4 FPM
FROM php:8.4-fpm-bookworm

# Répertoire de travail
WORKDIR /var/www/html

# Paquets système & bibliothèques requises pour Laravel, GD (WebP, JPEG, PNG), PostgreSQL, SQLite, MySQL et Nginx
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    git \
    curl \
    python3 \
    python3-venv \
    libgomp1 \
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

# CPU-only cutout runtime is built on Render, never installed on the developer PC.
COPY docker/rembg/requirements.txt /tmp/rembg-requirements.txt
RUN python3 -m venv /opt/rembg \
    && /opt/rembg/bin/pip install --no-cache-dir -r /tmp/rembg-requirements.txt \
    && mkdir -p /opt/rembg/models \
    && curl --fail --location --retry 3 --output /opt/rembg/models/u2netp.onnx \
        https://github.com/danielgatis/rembg/releases/download/v0.0.0/u2netp.onnx \
    && echo '8e83ca70e441ab06c318d82300c84806  /opt/rembg/models/u2netp.onnx' | md5sum -c - \
    && chmod -R a+rX /opt/rembg

ENV REMBG_ENABLED=true \
    REMBG_PYTHON=/opt/rembg/bin/python \
    REMBG_MODEL_PATH=/opt/rembg/models/u2netp.onnx \
    PYTHONDONTWRITEBYTECODE=1 \
    NUMBA_DISABLE_JIT=1 \
    OMP_NUM_THREADS=1 \
    OPENBLAS_NUM_THREADS=1

# Configuration PHP personnalisée
COPY docker/php/custom.ini /usr/local/etc/php/conf.d/custom.ini

# Copie des fichiers de dépendances d'abord pour optimiser le cache Docker
COPY composer.json composer.lock* /var/www/html/

# Installation des dépendances Composer de production (sans vendor dev)
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

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

# Fail the build before deployment if the bundled runtime or model cannot execute.
RUN php tests/rembg-runtime.php && rm -f storage/framework/cache/rembg.lock

# Ports exposés (Render écoute sur 10000 ou $PORT)
EXPOSE 10000 80 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["start-web"]
