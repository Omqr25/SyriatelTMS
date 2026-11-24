#!/bin/sh
set -e

cd "$(dirname "$0")"

case "${1:-queue}" in
    queue) exec php artisan queue:work --sleep=3 --tries=3 --timeout=60 ;;
    applicant-exports) exec php -d memory_limit="${APPLICANT_EXPORT_MEMORY_LIMIT:-512M}" artisan queue:work applicant_exports --queue=applicant-exports --sleep=3 --tries=1 --timeout="${APPLICANT_EXPORT_TIMEOUT:-900}" --max-jobs=20 ;;
    scheduler) exec php artisan schedule:work ;;
    *) echo "Usage: $0 [queue|scheduler|applicant-exports]" >&2; exit 2 ;;
esac
