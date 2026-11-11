
#!/bin/sh

set -e

# Create the Firebase credentials directory
mkdir -p storage/app/firebase

# Generate the Firebase service-account JSON file
if [ -z "$FIREBASE_SERVICE_ACCOUNT_JSON" ]; then
    echo "ERROR: FIREBASE_SERVICE_ACCOUNT_JSON is not set"
    exit 1
fi

printf '%s' "$FIREBASE_SERVICE_ACCOUNT_JSON" \
    > storage/app/firebase/firebase-service-account.json

# Set the credentials path for Laravel/PHP
export FIREBASE_CREDENTIALS="$PWD/storage/app/firebase/firebase-service-account.json"

# Start the queue worker
exec php artisan queue:work --sleep=3 --tries=3 --timeout=60
