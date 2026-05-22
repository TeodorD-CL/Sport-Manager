#!/bin/sh
set -e

is_truthy() {
    case "$(echo "${1:-}" | tr '[:upper:]' '[:lower:]')" in
        1|true|yes|on) return 0 ;;
        *) return 1 ;;
    esac
}

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

# Sync critical runtime env vars into .env to avoid stale defaults.
set_env_var() {
    key="$1"
    value="$2"
    if [ -z "$value" ]; then
        return
    fi
    awk -v k="$key" -v v="$value" '
        BEGIN { done = 0 }
        index($0, k "=") == 1 { print k "=" v; done = 1; next }
        { print }
        END { if (!done) print k "=" v }
    ' .env > .env.tmp && mv .env.tmp .env
}

set_env_var "DB_CONNECTION" "${DB_CONNECTION:-}"
set_env_var "DB_HOST" "${DB_HOST:-}"
set_env_var "DB_PORT" "${DB_PORT:-}"
set_env_var "DB_DATABASE" "${DB_DATABASE:-}"
set_env_var "DB_USERNAME" "${DB_USERNAME:-}"
set_env_var "DB_PASSWORD" "${DB_PASSWORD:-}"
set_env_var "SESSION_DRIVER" "${SESSION_DRIVER:-}"
set_env_var "CACHE_STORE" "${CACHE_STORE:-}"
set_env_var "QUEUE_CONNECTION" "${QUEUE_CONNECTION:-}"

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
if is_truthy "${RUN_MIGRATIONS:-true}"; then
    php artisan migrate --force
fi

# Optional one-time seeding for environments without shell access (e.g. free Render).
# Keep RUN_SEED=false by default and enable only when needed.
if is_truthy "${RUN_SEED:-false}"; then
    php artisan db:seed --force
fi

exec "$@"
