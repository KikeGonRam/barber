# ─────────────────────────────────────────────
# STAGE 1: Frontend (Vite + Node)
# ─────────────────────────────────────────────
FROM node:24-alpine AS node_builder

WORKDIR /app

COPY package*.json ./
RUN npm ci --no-audit

COPY . .
RUN npm run build

# ─────────────────────────────────────────────
# STAGE 2: PHP (Laravel App)
# ─────────────────────────────────────────────
FROM php:8.4-fpm-alpine

# Copiar entrypoint al lugar correcto SOLO en la etapa final
COPY .docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Argumentos
ARG USER_ID=1000
ARG GROUP_ID=1000

# Instalador de extensiones PHP
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions

# Dependencias del sistema + PHP
RUN apk add --no-cache \
    bash \
    curl \
    git \
    mariadb-client \
    libcap \
    unzip \
    zip \
    netcat-openbsd \
    && install-php-extensions \
    bcmath \
    gd \
    intl \
    mbstring \
    opcache \
    pdo_mysql \
    redis \
    zip \
    xml \
    dom \
    curl

# Composer (oficial)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Configuración PHP/FPM
COPY .docker/php/php.ini /usr/local/etc/php/conf.d/app-php.ini
COPY .docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# Crear usuario (ANTES de copiar archivos)
RUN addgroup -g ${GROUP_ID} laravel && \
    adduser -u ${USER_ID} -G laravel -s /bin/sh -D laravel

###############################################
# Copiar solo archivos de dependencias primero #
###############################################
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts --no-progress || true

# Copiar solo archivos de npm primero
COPY package.json package-lock.json ./
RUN npm ci --no-audit || true


###############################################
# Copiar el resto del proyecto y assets        #
###############################################
# Copiar el resto del proyecto y assets
COPY . .
COPY --from=node_builder /app/public/build ./public/build
# Renombrar .env a .env.local para forzar que Laravel use variables de entorno
RUN if [ -f .env ]; then mv .env .env.local; fi
# Eliminar cache de configuración de Laravel
RUN rm -f bootstrap/cache/config.php

# Crear estructura Laravel
RUN mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

# Permisos
RUN chown -R laravel:laravel /var/www/html && \
    chmod -R 775 storage bootstrap/cache

# Instala supervisor (como root)
USER root
RUN apk add --no-cache supervisor

# Copia el archivo de configuración de supervisord
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Cambiar a usuario seguro
USER laravel

# ⚠️ IMPORTANTE: NO ejecutar composer aquí
# Se hará en entrypoint (para evitar problemas con volumes)

EXPOSE 9000

ENTRYPOINT ["/entrypoint.sh"]
# Ejecuta supervisord para correr php-fpm y el scheduler
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]