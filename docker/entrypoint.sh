#!/bin/sh
set -e

# Create .env from example on first run (file is bind-mounted so it appears on the host too)
if [ ! -f .env ]; then
    cp .env.example .env
fi

# Generate app key if the placeholder is still empty
if ! grep -q "^APP_KEY=.\+" .env; then
    php artisan key:generate --ansi
fi

# SQLite database file must exist before migrations
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ] && [ ! -f database/database.sqlite ]; then
    touch database/database.sqlite
fi

# Storage & cache directories must be writable
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Run migrations only when enabled (web should run them, worker should not)
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force
fi

exec "$@"
