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

    # Las migraciones ya NO corren solas al arrancar: este contenedor recibe el
    # .env real, que en desarrollo apunta a la misma barber_db de Atlas que usa
    # staging, y un "docker compose up" no debe poder escribir ahi sin que nadie
    # lo pida. Para migrar: RUN_MIGRATIONS=true en el entorno del contenedor, o a
    # mano con "docker compose exec app php artisan migrate" tras confirmar a que
    # base apunta .env. Corren en background: contra un cluster Atlas M0 (lento)
    # pueden tardar minutos, y no deben bloquear el arranque de php-fpm.
    (
        if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
            echo "RUN_MIGRATIONS=true: aplicando migraciones..."
            for i in {1..20}; do
                if php artisan migrate --force --no-interaction; then
                    echo "Migraciones aplicadas."
                    break
                fi
                if [ $i -eq 20 ]; then
                    echo "No se pudo conectar a MongoDB despues de 20 intentos, continuando..."
                fi
                echo "Reintentando conexion... ($i/20)"
                sleep 3
            done
        else
            echo "Migraciones omitidas (RUN_MIGRATIONS no es true)."
        fi

        echo "Optimizando aplicacion..."
        php artisan optimize
    ) &
fi

exec "$@"
