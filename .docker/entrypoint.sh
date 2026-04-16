#!/bin/bash
set -e

# Asegurar que Git no bloquee Composer por ownership del volumen montado.
git config --global --add safe.directory /var/www/html || true

# Instalar dependencias si el volumen vendor está vacío o incompleto.
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "📦 vendor/autoload.php no existe, instalando dependencias..."
    composer install --no-interaction --prefer-dist --optimize-autoloader --no-progress
fi

# Solo corremos migraciones y optimizaciones si somos el contenedor "app"
if [ "$1" = "php-fpm" ]; then
    echo "🔗 Verificando enlace público de storage..."
    php artisan storage:link --no-interaction || true

    echo "🔍 Esperando a que la base de datos esté lista..."
    # Usamos un comando de artisan en lugar de nc para ser más nativos de Laravel
    for i in {1..60}; do
        if php artisan db:show > /dev/null 2>&1; then
            echo "✅ Base de datos conectada."
            break
        fi
        echo "⏳ Intentando conectar... ($i/60)"
        sleep 2
    done

    echo "🚀 Optimizando aplicación..."
    php artisan migrate --force --no-interaction
    php artisan optimize
fi

exec "$@"