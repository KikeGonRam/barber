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
    g++ \
    wget \
    netcat-traditional \
    procps \
    tesseract-ocr \
    tesseract-ocr-spa \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo zip exif pcntl gd intl

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

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
