#!/bin/bash
set -e

# Asegurar que Git no bloquee Composer por ownership del volumen montado.
git config --global --add safe.directory /var/www/html || true

# Instalar dependencias si el volumen vendor esta vacio o incompleto.
# "install" (no "update") respeta composer.lock exactamente -> build reproducible.
if [ ! -f /var/www/html/vendor/autoload.php ] || [ ! -d /var/www/html/vendor/mongodb ]; then
    echo "Instalando dependencias (incluye mongodb)..."
    composer install --no-interaction --prefer-dist --optimize-autoloader --no-progress
fi

# Solo corremos migraciones y optimizaciones si somos el contenedor "app"
if [ "$1" = "php-fpm" ]; then
    echo "Verificando enlace publico de storage..."
    php artisan storage:link --no-interaction || true

    echo "Registrando proveedores de paquetes..."
    php artisan package:discover --ansi || true

    # Laravel Pulse necesita el archivo SQLite creado de antemano -- a
    # diferencia de MongoDB, el driver sqlite de Laravel no lo crea solo.
    touch /var/www/html/database/pulse.sqlite

    # Las migraciones corren en background: con backfills grandes contra un
    # cluster Atlas M0 (lento) esto puede tardar minutos, y no debe bloquear
    # el arranque de php-fpm (nginx devuelve 502 mientras tanto).
    (
        echo "Conectando a MongoDB Atlas y aplicando migraciones..."
        for i in {1..20}; do
            if php artisan migrate --force --no-interaction; then
                echo "MongoDB Atlas conectada y migraciones aplicadas."
                break
            fi
            if [ $i -eq 20 ]; then
                echo "No se pudo conectar a MongoDB Atlas despues de 20 intentos, continuando..."
            fi
            echo "Reintentando conexion a Atlas... ($i/20)"
            sleep 3
        done

        echo "Optimizando aplicacion..."
        php artisan optimize
    ) &
fi

exec "$@"
