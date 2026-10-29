FROM php:8.4-fpm

# Set environment variables
ENV DEBIAN_FRONTEND noninteractive
ENV COMPOSER_ALLOW_SUPERUSER 1
ENV COMPOSER_MEMORY_LIMIT=-1

# Install system dependencies
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
    wget \
    netcat-traditional \
    procps \
    tesseract-ocr \
    tesseract-ocr-spa \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions. pdo_sqlite es solo para Laravel Pulse (rol
# "ingeniero"): Pulse necesita una conexion SQL dedicada y este proyecto usa
# MongoDB como conexion por defecto, que Pulse no soporta -- ver config/pulse.php
# y la conexion 'sqlite' en config/database.php.
#
# docker-php-ext-configure gd antes de instalar: las libs del sistema (libjpeg62-turbo-dev,
# libwebp-dev) ya estaban arriba, pero sin este paso "docker-php-ext-install gd" las ignora y
# compila GD sin soporte de JPEG ni WebP -- confirmado con gd_info() (25-oct-2026): solo traia
# PNG/GIF/BMP. Con casi todas las fotos subidas en .jpg, UploadedImage::resizeAndStore() (misma
# fecha, App\Support\UploadedImage) nunca llegaba a redimensionarlas, solo guardaba el original.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_sqlite zip exif pcntl gd intl

# Install MongoDB and Redis extensions
RUN pecl install mongodb redis \
    && docker-php-ext-enable mongodb redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create Laravel project directory
WORKDIR /var/www/html

# Copy existing application directory
COPY . .

# Create necessary directories
RUN mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions \
        storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Install dependencies exactly as pinned in composer.lock -> reproducible build.
# --no-scripts skips post-install artisan commands (package:discover, etc.)
# that require DB env vars unavailable at build time. Entrypoint runs them instead.
RUN composer install --no-interaction --no-progress --optimize-autoloader --no-scripts 2>&1

# Copy PHP configuration
COPY .docker/php/php.ini /usr/local/etc/php/conf.d/app-php.ini
COPY .docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# Set ownership, then relax perms only where PHP-FPM (www-data) needs to write
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# Expose port for PHP-FPM
EXPOSE 9000

# Health check
HEALTHCHECK --interval=10s --timeout=5s --retries=12 --start-period=60s \
    CMD nc -z localhost 9000 || exit 1

# Copy and run entrypoint
COPY .docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Excepción de Semgrep documentada (docs/referencia/PRUEBAS.md, «Análisis estático»): el proceso maestro arranca
# como root para preparar permisos, pero los workers de PHP-FPM corren como www-data (.docker/php/www.conf).
# nosemgrep: dockerfile.security.missing-user-entrypoint.missing-user-entrypoint
ENTRYPOINT ["/entrypoint.sh"]
# nosemgrep: dockerfile.security.missing-user.missing-user
CMD ["php-fpm"]
