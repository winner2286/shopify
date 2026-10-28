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

# 2. Validation et génération de la clé d'application
case "$APP_KEY" in
    base64:*)
        echo "Clé APP_KEY valide détectée."
        ;;
    *)
        echo "Génération d'une clé APP_KEY Laravel valide..."
        NEW_KEY=$(php artisan key:generate --show 2>/dev/null || true)
        if [ -n "$NEW_KEY" ]; then
            export APP_KEY="$NEW_KEY"
            if [ -f /var/www/html/.env ]; then
                sed -i "s|^APP_KEY=.*|APP_KEY=${NEW_KEY}|g" /var/www/html/.env 2>/dev/null || echo "APP_KEY=${NEW_KEY}" >> /var/www/html/.env
            fi
        fi
        ;;
esac

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

# 6. Attente et migrations de base de données
if [ "$AUTO_MIGRATE" = "true" ] || [ "$1" = "start-web" ] || [ -n "$RENDER" ]; then
    if [ -n "$DATABASE_URL" ] || [ "$DB_CONNECTION" = "pgsql" ]; then
        echo "Vérification de la disponibilité de la base de données..."
        for i in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15; do
            if php artisan migrate:status >/dev/null 2>&1; then
                echo "Base de données connectée !"
                break
            fi
            echo "En attente de la base de données ($i/15)..."
            sleep 2
        done
    fi

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
