#!/usr/bin/env bash
set -Eeuo pipefail

PORT="${PORT:-10000}"
if ! [[ "$PORT" =~ ^[0-9]+$ ]]; then
    echo "PORT must be a numeric TCP port." >&2
    exit 1
fi

# Render terminates TLS at its proxy. Use its public URL for generated links
# and static assets when an explicit canonical URL has not been configured.
if [[ -z "${APP_URL:-}" && -n "${RENDER_EXTERNAL_URL:-}" ]]; then
    export APP_URL="$RENDER_EXTERNAL_URL"
fi
if [[ -z "${ASSET_URL:-}" && -n "${RENDER_EXTERNAL_URL:-}" ]]; then
    export ASSET_URL="$RENDER_EXTERNAL_URL"
fi

sed -i "s/__PORT__/$PORT/g" /etc/nginx/sites-available/default

mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache

# Wait for the configured PostgreSQL database before starting the web server.
# The retry window is bounded so a bad staging DB configuration fails clearly.
if [[ "${DB_CONNECTION:-pgsql}" == "pgsql" ]]; then
    echo "Waiting for PostgreSQL connectivity..."
    connected=false
    for attempt in $(seq 1 30); do
        if php artisan migrate:status --no-interaction >/dev/null 2>&1; then
            connected=true
            break
        fi
        sleep 2
    done
    if [[ "$connected" != "true" ]]; then
        echo "PostgreSQL was not reachable after 60 seconds; refusing to start." >&2
        exit 1
    fi
fi

# Migrations are deliberately opt-in. Render staging sets RUN_MIGRATIONS=true
# against its dedicated disposable database; production should use a reviewed
# pre-deploy migration step instead of enabling migrations on every boot.
if [[ "${RUN_MIGRATIONS:-false}" == "true" ]]; then
    php artisan migrate --force --no-interaction
fi

php artisan config:cache

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
