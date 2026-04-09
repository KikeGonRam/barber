# ─────────────────────────────────────────────────────────────
# STAGE 1: Build assets con Node.js (Vite)
# ─────────────────────────────────────────────────────────────
FROM node:22-alpine AS node_builder

WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY . .
RUN npm run build


# ─────────────────────────────────────────────────────────────
# STAGE 2: Imagen final PHP 8.4 + Apache
# ─────────────────────────────────────────────────────────────
FROM php:8.4-apache

# phpoffice/phpspreadsheet (maatwebsite/excel) requiere xml, gd, bcmath, etc.
RUN apt-get update \
    && apt-get install -y \
        git \
        unzip \
        zip \
        curl \
        libzip-dev \
        libonig-dev \
        libxml2-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        zip \
        xml \
        xmlreader \
        xmlwriter \
        bcmath \
        gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Habilitar mod_rewrite de Apache
RUN a2enmod rewrite

# Instalar Composer desde imagen oficial
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copiar el proyecto
COPY . /var/www/html

# Copiar assets compilados por Vite
COPY --from=node_builder /app/public/build /var/www/html/public/build

# Copiar configuración de Apache
COPY .docker/vhost.conf /etc/apache2/sites-available/000-default.conf

# Instalar dependencias PHP incluyendo las de desarrollo
# (laravel/boost y otros paquetes dev se registran en providers)
# Se pasa APP_KEY temporal solo para que "php artisan package:discover" no falle
RUN APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
    composer install --optimize-autoloader --no-interaction

# Permisos correctos para Laravel
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

EXPOSE 80

CMD ["apache2-foreground"]