#!/bin/bash
set -e

# Laravel resolves Firebase credentials from a mounted file or environment JSON.
if [ "${RAILPACK_SKIP_MIGRATIONS:-false}" != "true" ]; then
    php artisan migrate --force
fi

php artisan storage:link
php artisan optimize

exec docker-php-entrypoint --config /Caddyfile --adapter caddyfile 2>&1
