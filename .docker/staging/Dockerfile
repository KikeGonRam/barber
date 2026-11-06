# Imagen de PRODUCCION/STAGING para AWS -- separada a proposito del
# Dockerfile de la raiz (ese sigue siendo el usado por docker-compose.yml
# para desarrollo local, no lo toca este archivo). Combina PHP-FPM + Caddy
# (en vez de nginx) en una sola imagen, para desplegarse como un unico
# servicio/tarea (ej. App Runner o una sola tarea de ECS).
#
# Dos etapas: «build» compila las extensiones de PHP e instala las dependencias de Composer con
# g++, git y las librerias -dev; la imagen final solo recibe el resultado y las librerias de
# ejecucion. Asi el compilador y los encabezados (~200 MB) no viajan a ECR ni a Fargate.

# ---------- Etapa 1: compilar extensiones y dependencias ----------
FROM php:8.4-fpm AS build

ENV DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_MEMORY_LIMIT=-1

RUN apt-get update && apt-get install -y \
    git \
    curl \
    ca-certificates \
    libssl-dev \
    pkg-config \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libz-dev \
    libzip-dev \
    zlib1g-dev \
    libicu-dev \
    libsqlite3-dev \
    g++ \
    && rm -rf /var/lib/apt/lists/*

# Igual que el Dockerfile de desarrollo (25-oct-2026): sin configure, GD compila sin JPEG ni
# WebP aunque las libs ya estén arriba -- ver ese comentario para el detalle completo.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_sqlite zip exif pcntl gd intl
RUN pecl install mongodb redis \
    && docker-php-ext-enable mongodb redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions \
        storage/logs bootstrap/cache

# SIN --no-dev: config/scribe.php referencia clases de Knuckles\Scribe
# directamente (no solo su ServiceProvider), asi que Laravel no puede ni
# cachear la configuracion sin el paquete instalado -- confirmado con un
# build real que fallaba con "Class Knuckles\Scribe\Config\AuthIn not
# found" incluso con bootstrap/cache limpio. Mismo criterio que ya usa el
# Dockerfile de desarrollo de la raiz (tampoco usa --no-dev).
# --no-scripts: los comandos artisan del post-install necesitan env vars que
# no existen en build time -- el entrypoint corre migrate/cache despues.
RUN composer install --no-interaction --no-progress --optimize-autoloader --no-scripts 2>&1

# ---------- Etapa 2: imagen final ----------
FROM php:8.4-fpm

ENV DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_MEMORY_LIMIT=-1

# Solo librerias de ejecucion (las que enlazan las extensiones compiladas arriba), curl para el
# HEALTHCHECK y Tesseract para el OCR. Si cambia la base de Debian y un nombre de paquete
# desaparece, el build falla aqui o en la comprobacion de extensiones de mas abajo, no en ECS.
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    libfreetype6 \
    libicu76 \
    libjpeg62-turbo \
    libpng16-16t64 \
    libsqlite3-0 \
    libwebp7 \
    libzip5 \
    tesseract-ocr \
    tesseract-ocr-spa \
    && rm -rf /var/lib/apt/lists/*

COPY --from=build /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=build /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d
COPY --from=build /usr/bin/composer /usr/bin/composer
# Caddy oficial, binario estatico -- no se compila nada aqui.
COPY --from=caddy:2-alpine /usr/bin/caddy /usr/bin/caddy

# Falla el build si falta alguna extension o GD perdio JPEG/WebP (el mismo problema de arriba).
RUN test "$(php -m | grep -c -x -E 'gd|intl|zip|exif|pcntl|pdo_sqlite|mongodb|redis')" -eq 8 \
    && php -r 'exit((gd_info()["JPEG Support"] && gd_info()["WebP Support"]) ? 0 : 1);' \
    && tesseract --list-langs | grep -qx spa

WORKDIR /var/www/html

COPY --from=build /var/www/html /var/www/html

# Solo lo que PHP-FPM y el programador escriben (storage, caché de bootstrap y la carpeta de la base
# SQLite de Pulse, que necesita crear su journal). Un `chown -R` de todo /var/www/html duplicaba el
# árbol completo, vendor incluido, en una capa nueva de ~320 MB; el código queda de solo lectura.
RUN chown -R www-data:www-data storage bootstrap/cache database \
    && chmod -R 775 storage bootstrap/cache

# php.ini propio de staging (OPcache sin revalidar); el de .docker/php/ es el de desarrollo.
COPY .docker/staging/php.ini /usr/local/etc/php/conf.d/app-php.ini
COPY .docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY .docker/caddy/Caddyfile /etc/caddy/Caddyfile
# Un Caddyfile inválido (p. ej. al tocar las cabeceras de TT10/TT11) debe romper el build,
# no dejar la tarea de ECS reiniciándose sin servir tráfico.
RUN caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile

EXPOSE 80

# Chequea el endpoint nativo de salud de Laravel (bootstrap/app.php: health
# => '/up') a traves de Caddy, no PHP-FPM directo -- confirma que las DOS
# piezas (Caddy + PHP-FPM) estan realmente sirviendo trafico real.
HEALTHCHECK --interval=15s --timeout=5s --retries=8 --start-period=90s \
    CMD curl -fsS http://localhost/up || exit 1

COPY .docker/staging/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Excepción de Semgrep documentada (docs/referencia/PRUEBAS.md, «Análisis estático»): el entrypoint corre como root
# para preparar /run/app-secrets y arrancar Caddy; PHP-FPM atiende como www-data y el programador con su www-data.
# nosemgrep: dockerfile.security.missing-user-entrypoint.missing-user-entrypoint
ENTRYPOINT ["/entrypoint.sh"]
