#!/bin/bash
set -e

# Asegurar que Git no bloquee Composer por ownership del volumen montado.
git config --global --add safe.directory /var/www/html || true

# Instalar dependencias si el volumen vendor está vacío o incompleto.
if [ ! -f /var/www/html/vendor/autoload.php ] || [ ! -d /var/www/html/vendor/mongodb ]; then
    echo "📦 Instalando/actualizando dependencias (incluye mongodb)..."
    composer update --no-interaction --prefer-dist --optimize-autoloader --no-progress
fi

# Solo corremos migraciones y optimizaciones si somos el contenedor "app"
if [ "$1" = "php-fpm" ]; then
    echo "🔗 Verificando enlace público de storage..."
    php artisan storage:link --no-interaction || true

    echo "🔍 Conectando a MongoDB Atlas y aplicando migraciones..."
    for i in {1..20}; do
        if php artisan migrate --force --no-interaction; then
            echo "✅ MongoDB Atlas conectada y migraciones aplicadas."
            break
        fi
        if [ $i -eq 20 ]; then
            echo "⚠️  No se pudo conectar a MongoDB Atlas después de 20 intentos, continuando..."
        fi
        echo "⏳ Reintentando conexión a Atlas... ($i/20)"
        sleep 3
    done

    echo "🚀 Optimizando aplicación..."
    php artisan optimize
fi

exec "$@"