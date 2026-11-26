# Attendance V1 rollout and rollback

Keep the attendance schema and its history during a code rollback. The release adds the `expired` leave status and uses `absences.deleted_at` to hide soft-deleted entries. Bare pre-V1 code cannot safely read either. It can return HTTP 500 for expired leave or show deleted absences. The migration refuses to run down while either kind of history exists. It never converts statuses, deletes history, or restores absences.

## Deploy

1. Take a database backup. Pause web traffic and stop or drain the scheduler and queue workers, including queued program completion jobs. Clear old PHP processes and route caches as part of switching releases.
2. Apply the V1 migration before starting the new web code. An old reader is compatible with the additive schema only until V1 starts writing expired or soft-deleted records.
3. Switch the API and frontend together. Start only V1 web instances, then resume the scheduler and queue workers using V1 code. Avoid serving bare pre-V1 code alongside V1 writers.

## Roll back the application

Prepare a fallback artifact in an isolated build directory from the audited pre-V1 commit `ea80e8b9927c08e9cd3926434a261e0ccfa0f722`. Apply [the rollback bridge](attendance-rollback-bridge.patch) with `git apply --check /path/to/attendance-rollback-bridge.patch` and then `git apply /path/to/attendance-rollback-bridge.patch`. Build that revision's frontend as well. Revalidate the patch if using any other baseline.

Pause traffic and drain V1 workers before switching. **Leave the V1 migration applied.** Deploy the bridged API and its matching frontend together, then resume the existing workers from the bridged release. The bridge keeps historical attendance reads, current-supervisor access, and HR’s accepted-only leave visibility. Attendance and leave mutations are unavailable through the bridge. Old screens can still show controls whose requests will fail. Pending requests remain pending until the V1 completion code returns. Smoke-test expired leave history, a soft-deleted absence, and a reassigned trainee before reopening traffic. Roll forward to V1 to resume the full feature.

A full schema downgrade is only safe before any expired leave or soft-deleted absence exists, with all writers stopped. Use the V1 artifact to roll back only the attendance migration and only if no later migrations depend on it. If either type of record exists, keep the schema and use the bridge or roll forward. Keep the fallback artifact available before deploying.

## Running destructive tests

The default PHPUnit connection is process-local SQLite `:memory:`. For MySQL, start a fresh, network-isolated MySQL 8 instance or container, with no development, staging, or production data or credentials. Use a dedicated non-root account with grants limited to the disposable schema and read-only performance schema access for lock-wait assertions. The application-side guard can verify server identity and schema, while the operator or CI must supply an actually disposable server and account.

Set `APP_ENV=testing`, `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_DATABASE` and `TMS_ISOLATED_MYSQL_DATABASE` to the same disposable `*_test` schema, and `TMS_ISOLATED_MYSQL_SERVER_UUID` to the UUID queried from that isolated instance during provisioning. Use the dedicated account. Leave `DB_URL` and `DB_SOCKET` empty. Before opting into `RUN_MYSQL_ATTENDANCE_CONCURRENCY=1`, migrate its separate disposable schema. Reuse the same authorization settings in the subprocesses.

The pre-existing notification migration down failure during MySQL interview-test teardown remains separate from attendance release safeguards.
