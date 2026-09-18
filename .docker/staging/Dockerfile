# Imagen de PRODUCCION/STAGING para AWS -- separada a proposito del
# Dockerfile de la raiz (ese sigue siendo el usado por docker-compose.yml
# para desarrollo local, no lo toca este archivo). Combina PHP-FPM + Caddy
# (en vez de nginx) en una sola imagen, para desplegarse como un unico
# servicio/tarea (ej. App Runner o una sola tarea de ECS).
FROM php:8.4-fpm

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
    sqlite3 \
    g++ \
    tesseract-ocr \
    tesseract-ocr-spa \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_sqlite zip exif pcntl gd intl
RUN pecl install mongodb redis \
    && docker-php-ext-enable mongodb redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
# Caddy oficial, binario estatico -- no se compila nada aqui.
COPY --from=caddy:2-alpine /usr/bin/caddy /usr/bin/caddy

WORKDIR /var/www/html

COPY . .

RUN mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions \
        storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# SIN --no-dev: config/scribe.php referencia clases de Knuckles\Scribe
# directamente (no solo su ServiceProvider), asi que Laravel no puede ni
# cachear la configuracion sin el paquete instalado -- confirmado con un
# build real que fallaba con "Class Knuckles\Scribe\Config\AuthIn not
# found" incluso con bootstrap/cache limpio. Mismo criterio que ya usa el
# Dockerfile de desarrollo de la raiz (tampoco usa --no-dev).
# --no-scripts: los comandos artisan del post-install necesitan env vars que
# no existen en build time -- el entrypoint corre migrate/cache despues.
RUN composer install --no-interaction --no-progress --optimize-autoloader --no-scripts 2>&1

COPY .docker/php/php.ini /usr/local/etc/php/conf.d/app-php.ini
COPY .docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY .docker/caddy/Caddyfile /etc/caddy/Caddyfile

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 80

# Chequea el endpoint nativo de salud de Laravel (bootstrap/app.php: health
# => '/up') a traves de Caddy, no PHP-FPM directo -- confirma que las DOS
# piezas (Caddy + PHP-FPM) estan realmente sirviendo trafico real.
HEALTHCHECK --interval=15s --timeout=5s --retries=8 --start-period=90s \
    CMD curl -fsS http://localhost/up || exit 1

COPY .docker/staging/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
