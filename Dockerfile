# --- PHP app: FrankenPHP serves requests on multiple threads (php artisan serve is single-threaded and dev-only) ---
FROM dunglas/frankenphp:1-php8.2-bookworm AS app

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && install-php-extensions pdo_pgsql pdo_sqlite gd zip intl bcmath pcntl exif opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# docker-compose passes an empty value to keep dev dependencies (phpunit) for local work.
ARG COMPOSER_FLAGS="--no-dev"

# Install PHP dependencies as a separate layer so it caches well
COPY composer.json composer.lock ./
RUN composer install $COMPOSER_FLAGS --no-scripts --no-autoloader --no-interaction --prefer-dist

# Copy application source
COPY . .

# Finalise autoloader with full source present
RUN composer dump-autoload --optimize $COMPOSER_FLAGS

# --- Frontend: Tailwind + flatpickr compiled by Vite ---
FROM node:20-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources
COPY app/Livewire ./app/Livewire
# Tailwind scans the pagination views for class names.
COPY --from=app /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
COPY --from=app /app/vendor/livewire/livewire/src/Features/SupportPagination/views ./vendor/livewire/livewire/src/Features/SupportPagination/views
RUN npm run build

# --- Final image ---
FROM app

COPY --from=assets /app/public/build ./public/build

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["sh", "-c", "exec frankenphp php-server --root public --listen :${PORT:-8000}"]
