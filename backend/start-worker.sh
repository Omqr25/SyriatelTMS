#!/bin/sh
set -e

cd "$(dirname "$0")"

case "${1:-queue}" in
    queue) exec php artisan queue:work --sleep=3 --tries=3 --timeout=60 ;;
    scheduler) exec php artisan schedule:work ;;
    *) echo "Usage: $0 [queue|scheduler]" >&2; exit 2 ;;
esac
