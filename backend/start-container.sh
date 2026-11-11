
#!/bin/bash
set -e

# Create the Firebase credentials file from Railway's variable.
if [ -z "${FIREBASE_SERVICE_ACCOUNT_JSON:-}" ]; then
    echo "ERROR: FIREBASE_SERVICE_ACCOUNT_JSON is missing."
    exit 1
fi

mkdir -p storage/app/firebase

printf '%s' "$FIREBASE_SERVICE_ACCOUNT_JSON" \
    > storage/app/firebase/firebase-service-account.json

chmod 600 storage/app/firebase/firebase-service-account.json

export FIREBASE_CREDENTIALS="$PWD/storage/app/firebase/firebase-service-account.json"

echo "Firebase credentials file created."

# Preserve Railpack's Laravel startup tasks.
if [ "${RAILPACK_SKIP_MIGRATIONS:-false}" != "true" ]; then
    php artisan migrate --force
fi

php artisan storage:link
php artisan optimize

# Start the normal Railpack PHP server.
exec docker-php-entrypoint --config /Caddyfile --adapter caddyfile 2>&1
