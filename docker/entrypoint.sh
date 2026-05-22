#!/bin/sh
set -e

# Create .env from example on first run (file is bind-mounted so it appears on the host too)
if [ ! -f .env ]; then
    cp .env.example .env
fi

# If APP_KEY is provided by the platform env vars, persist it into .env.
if [ -n "${APP_KEY:-}" ]; then
    awk -v key="$APP_KEY" '
        BEGIN { done = 0 }
        /^APP_KEY=/ { print "APP_KEY=" key; done = 1; next }
        { print }
        END { if (!done) print "APP_KEY=" key }
    ' .env > .env.tmp && mv .env.tmp .env
fi

# Generate app key only if still empty after env sync.
if ! grep -q "^APP_KEY=.\+" .env; then
    php artisan key:generate --ansi --force
fi

# Fail fast with a clear message if APP_KEY is still not set.
if ! grep -q "^APP_KEY=.\+" .env; then
    echo "ERROR: APP_KEY is missing. Set APP_KEY env var in hosting provider."
    exit 1
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
