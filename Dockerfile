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
    mariadb-client-compat \
    libmariadb-dev \
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
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli zip exif pcntl gd intl

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create Laravel project directory
WORKDIR /var/www/html

# Copy existing application directory
COPY . .

# Install dependencies
RUN composer install --no-interaction --no-progress --no-suggest 2>&1 || true

# Copy PHP configuration
COPY .docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# Create necessary directories with proper permissions
RUN mkdir -p storage/framework/views storage/framework/cache storage/logs \
    && chmod -R 777 storage bootstrap/cache

# Set ownership
RUN chown -R www-data:www-data /var/www/html

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
