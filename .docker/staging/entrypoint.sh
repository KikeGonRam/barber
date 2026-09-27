#!/bin/bash
set -e

# A diferencia del entrypoint de desarrollo (.docker/entrypoint.sh), aqui NO
# se corre "composer install" en runtime -- vendor/ ya viene horneado en la
# imagen de produccion (ver .docker/staging/Dockerfile). Tampoco se dejan
# migraciones corriendo en segundo plano: en staging preferimos que el
# contenedor tarde un poco mas en arrancar a que reporte "sano" y acepte
# trafico antes de que config:cache/migrate hayan terminado.

echo "Verificando enlace publico de storage..."
php artisan storage:link --no-interaction || true

# Laravel Pulse necesita el archivo SQLite creado de antemano.
touch /var/www/html/database/pulse.sqlite
chown www-data:www-data /var/www/html/database/pulse.sqlite

echo "Conectando a MongoDB Atlas y aplicando migraciones..."
for i in {1..20}; do
    if php artisan migrate --force --no-interaction; then
        echo "MongoDB Atlas conectada y migraciones aplicadas."
        break
    fi
    if [ "$i" -eq 20 ]; then
        echo "ERROR: no se pudo conectar a MongoDB Atlas despues de 20 intentos." >&2
        exit 1
    fi
    echo "Reintentando conexion a Atlas... ($i/20)"
    sleep 3
done

# Push con Firebase (HU-02): ECS entrega la cuenta de servicio como variable de entorno
# (secreto urbanblade/staging/barber/FIREBASE_CREDENTIALS_JSON en Secrets Manager), pero
# FcmPushService lee un archivo. Se escribe a un archivo privado antes de cachear la
# configuracion y la variable se borra del entorno. Sin el secreto, el push queda apagado.
if [ -n "${FIREBASE_CREDENTIALS_JSON:-}" ]; then
    install -d -m 750 -o root -g www-data /run/app-secrets
    printf '%s' "$FIREBASE_CREDENTIALS_JSON" > /run/app-secrets/firebase.json
    chown root:www-data /run/app-secrets/firebase.json
    chmod 440 /run/app-secrets/firebase.json
    export FIREBASE_CREDENTIALS_PATH=/run/app-secrets/firebase.json
    unset FIREBASE_CREDENTIALS_JSON
    echo "Credenciales de Firebase listas."
fi

echo "Cacheando configuracion, rutas y vistas..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# php-fpm queda demonizado (-D) y Caddy toma el proceso principal (PID 1) del
# contenedor -- Docker/ECS lo detiene mandando la señal ahi. Al parar el
# contenedor completo, la plataforma mata todo el cgroup (no solo PID 1), asi
# que php-fpm no queda huerfano de forma permanente.
# Tareas programadas (routes/console.php: recordatorios de 24 h y 2 h, no-shows, pedidos
# vencidos, lealtad, campañas...). Staging no tiene un servicio "scheduler" aparte, asi que
# se corre aqui en segundo plano SOLO con RUN_SCHEDULER=true: activarlo escribe en la base
# real de Atlas. Con una sola tarea de barber no hay ejecuciones duplicadas; si se escala a
# mas de una, dejarlo en una sola o moverlo a un servicio propio.
if [ "${RUN_SCHEDULER:-false}" = "true" ]; then
    echo "Iniciando el scheduler de Laravel..."
    su -s /bin/sh www-data -c "php /var/www/html/artisan schedule:work" &
fi

echo "Iniciando PHP-FPM..."
php-fpm -D

echo "Iniciando Caddy..."
exec caddy run --config /etc/caddy/Caddyfile --adapter caddyfile
