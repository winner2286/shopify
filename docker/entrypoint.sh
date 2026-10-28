#!/bin/sh
set -e

echo "=== Initialisation de Boutiq ==="

# 1. Gestion du fichier .env
if [ ! -f /var/www/html/.env ]; then
    if [ -f /var/www/html/.env.example ]; then
        echo "Création de .env à partir de .env.example..."
        cp /var/www/html/.env.example /var/www/html/.env
    fi
fi

# 2. Génération de clé d'application si absente
if [ -z "$APP_KEY" ] && ! grep -q "^APP_KEY=base64:" /var/www/html/.env 2>/dev/null; then
    echo "Génération de la clé d'application APP_KEY..."
    php artisan key:generate --force || true
fi

# 3. Base SQLite si utilisée
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ] && [ -z "$DATABASE_URL" ]; then
    if [ ! -f /var/www/html/database/database.sqlite ]; then
        echo "Création du fichier SQLite database/database.sqlite..."
        touch /var/www/html/database/database.sqlite
    fi
fi

# 4. Dossiers de stockage et droits
mkdir -p /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/framework/cache \
         /var/www/html/storage/app/public \
         /var/www/html/bootstrap/cache \
         /var/www/html/database 2>/dev/null || true

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database 2>/dev/null || true

# 5. Lien symbolique public/storage
if [ ! -L /var/www/html/public/storage ]; then
    echo "Création du lien symbolique public/storage..."
    php artisan storage:link || true
fi

# 6. Migrations de base de données
if [ "$AUTO_MIGRATE" = "true" ] || [ "$1" = "start-web" ] || [ -n "$RENDER" ]; then
    echo "Exécution des migrations de base de données..."
    php artisan migrate --force || true

    echo "Initialisation des formules d'abonnement (PlanSeeder)..."
    php artisan db:seed --class=PlanSeeder --force || true
fi

# 7. Optimisation du cache pour production
if [ "$APP_ENV" = "production" ]; then
    echo "Mise en cache de la configuration et des routes..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

# 8. Démarrage du serveur Web (Render / Production) ou commande personnalisée (Local)
if [ "$1" = "start-web" ] || [ -z "$1" ] || [ -n "$PORT" -a "$1" != "php-fpm" ]; then
    PORT_VALUE=${PORT:-10000}
    echo "Configuration de Nginx pour écouter sur le port ${PORT_VALUE}..."
    
    mkdir -p /etc/nginx/sites-available /etc/nginx/sites-enabled
    cp /var/www/html/docker/nginx/render-nginx.conf /etc/nginx/sites-available/render.conf
    sed -i "s/PORT_NUMBER/${PORT_VALUE}/g" /etc/nginx/sites-available/render.conf
    
    ln -sf /etc/nginx/sites-available/render.conf /etc/nginx/sites-enabled/render.conf
    rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true
    
    # Test de configuration Nginx
    nginx -t || true

    echo "Démarrage de PHP-FPM 8.4..."
    php-fpm -D

    echo "Boutiq est en ligne et écoute sur le port ${PORT_VALUE} !"
    exec nginx -g "daemon off;"
fi

# Si une commande spécifique est passée (ex: php-fpm en local)
echo "Exécution de la commande : $@"
exec "$@"
