FROM php:8.2-cli

# System dependencies (libjpeg + libfreetype required before configuring gd)
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libpq-dev \
    libsqlite3-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libxml2-dev \
    libzip-dev \
    libonig-dev \
    libicu-dev \
    && rm -rf /var/lib/apt/lists/*

# gd must be configured before install to pick up jpeg/freetype
RUN docker-php-ext-configure gd --with-freetype --with-jpeg

# PHP extensions required by Laravel + this app
# opcache is already compiled in the base image — use enable, not install
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pdo_sqlite \
    mbstring \
    xml \
    bcmath \
    gd \
    zip \
    pcntl \
    intl \
    exif

RUN docker-php-ext-enable opcache

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP dependencies as a separate layer so it caches well
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --no-interaction

# Copy application source
COPY . .

# Finalise autoloader with full source present
RUN composer dump-autoload --optimize

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["sh", "-c", "php artisan serve --host=0.0.0.0 --port=${PORT:-8000}"]
