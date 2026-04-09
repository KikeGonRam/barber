#!/bin/bash
set -e

echo "→ Esperando base de datos..."
until php artisan db:show --json > /dev/null 2>&1; do
    sleep 2
done

echo "→ Ejecutando migraciones..."
php artisan migrate --force --no-interaction

echo "→ Optimizando Laravel..."
php artisan optimize

echo "→ Iniciando Apache..."
exec "$@"