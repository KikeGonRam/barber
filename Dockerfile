# ─────────────────────────────────────────────────────────────
# STAGE 1: Asset Builder (Vite + Node.js)
# ─────────────────────────────────────────────────────────────
FROM node:22-alpine AS node_builder
WORKDIR /app
COPY package*.json ./
RUN npm ci --no-audit
COPY . .
RUN npm run build

# ─────────────────────────────────────────────────────────────
# STAGE 2: PHP Application (FPM + Alpine)
# ─────────────────────────────────────────────────────────────
FROM php:8.4-fpm-alpine

# Argumentos de compilación
ARG USER_ID=1000
ARG GROUP_ID=1000

# Instalador de extensiones PHP
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions

# Instalación de dependencias del sistema y PHP
RUN apk add --no-cache \
    bash \
    curl \
    git \
    libcap \
    unzip \
    zip \
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

# Composer oficial
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Configuración personalizada de PHP y FPM
COPY .docker/php/php.ini /usr/local/etc/php/conf.d/app-php.ini
COPY .docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# Creación de usuario profesional
RUN addgroup -g ${GROUP_ID} laravel && \
    adduser -u ${USER_ID} -G laravel -s /bin/sh -D laravel

# Pre-creamos la estructura de storage
RUN mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache && \
    chown -R laravel:laravel storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

# Copia de archivos
COPY --chown=laravel:laravel . .
COPY --from=node_builder --chown=laravel:laravel /app/public/build ./public/build

USER laravel

# Instalación de dependencias PHP
RUN composer install --optimize-autoloader --no-interaction

EXPOSE 9000

ENTRYPOINT [".docker/entrypoint.sh"]
CMD ["php-fpm"]