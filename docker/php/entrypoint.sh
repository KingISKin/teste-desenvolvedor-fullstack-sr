#!/bin/sh
# Container entrypoint shared by the "app" (php-fpm) and "worker" (queue) services.
#
#   CONTAINER_ROLE=app     generates the app key if needed, runs migrations and
#                          seeds the demo user (idempotent), then starts php-fpm.
#   CONTAINER_ROLE=worker  only waits for its dependencies; schema changes are
#                          owned by a single container to avoid migration races.
set -eu

cd /var/www/html

ROLE="${CONTAINER_ROLE:-app}"
# Lives on the storage volume shared by app and worker, never served over HTTP.
KEY_FILE="storage/app/.app-key"

log() {
    echo "[entrypoint:${ROLE}] $*"
}

resolve_app_key() {
    if [ -n "${APP_KEY:-}" ]; then
        return
    fi

    if [ ! -s "$KEY_FILE" ]; then
        if [ "$ROLE" = "app" ]; then
            log "APP_KEY not provided, generating one on the shared storage volume."
            (umask 077 && php -r 'echo "base64:".base64_encode(random_bytes(32));' > "$KEY_FILE")
        else
            attempts=0
            until [ -s "$KEY_FILE" ]; do
                attempts=$((attempts + 1))
                if [ "$attempts" -ge 60 ]; then
                    log "Timed out waiting for the app container to generate APP_KEY." >&2
                    exit 1
                fi
                sleep 1
            done
        fi
    fi

    APP_KEY="$(cat "$KEY_FILE")"
    export APP_KEY
}

wait_for_database() {
    attempts=0
    until php -r '
        try {
            new PDO(
                sprintf("mysql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT") ?: "3306", getenv("DB_DATABASE")),
                getenv("DB_USERNAME"),
                getenv("DB_PASSWORD"),
                [PDO::ATTR_TIMEOUT => 3],
            );
        } catch (Throwable $e) {
            exit(1);
        }
    '; do
        attempts=$((attempts + 1))
        if [ "$attempts" -ge 30 ]; then
            log "Database is unreachable, giving up." >&2
            exit 1
        fi
        log "Waiting for the database (${attempts}/30)..."
        sleep 2
    done
}

resolve_app_key
wait_for_database

php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan event:cache --no-interaction

if [ "$ROLE" = "app" ]; then
    log "Running migrations."
    php artisan migrate --force --no-interaction
    log "Seeding the demo user."
    php artisan db:seed --class=DemoUserSeeder --force --no-interaction
fi

exec "$@"
