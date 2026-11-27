# Certificate foundation

Phase 1 extends the existing certificates table. The manual-upload API and issued PDF locations stay compatible. The foundation adds immutable template versions, confirmed reservations, audited exclusions and HR names, numbering and fenced lifecycle state. The new services have no public write routes or job dispatch. The dedicated `certificates` database connection uses `certificate_jobs`. Core and export configuration, worker commands and schedules are unchanged.

## Apply and roll back

1. Review a backup of the database and private certificate files on staging. Pause certificate upload writes while applying the schema.
2. From `backend`, run `composer install`. Run `php artisan migrate --force` once ready. The first migration checks for duplicate trainee/program pairs and orphaned certificate references before replacing the cascading trainee FK with a restrictive one. It reports a bounded sample and stops on a conflict. Retain all affected files and rows until a remediation decision is approved. Never deduplicate or backfill numbers during the upgrade.
3. The second migration adds the foundation and the queue table. Historical rows keep their IDs, paths, issue dates and files. They are marked issued by the column default and retain null numbers, snapshots and template references. Retain the private volume across deployments. MySQL DDL in the second migration is not transactional as a whole. On interruption, inspect the migration ledger and deployed schema before resuming under DBA supervision.
4. Roll back Phase 1 application code with the forward schema and private files in place. Dropping the foundation would erase data and restoring a cascading FK would risk deleting the archive. Both migration down methods fail closed. Code-only rollback to the pre-Phase 1 certificate controller is suitable while no new reservations exist. After the later issuance rollout, retain the issued-only reader and the duplicate guard in any rollback target.

The name window opens at midnight seven calendar days before the stored program end date and closes at midnight following its final day, in Asia/Damascus. Before a reservation it follows the current program schedule. Phase 1 does not persist a separate automatic closure latch. A reservation freezes the name, issue date, number, template version and used values. The fallback order is trainee submission, original application full name, current account name, then an independently audited HR entry. No translation or transliteration occurs. Program and department values are required in English only when referenced by the selected template.

New background files use immutable private paths with a recorded SHA-256. The Foundation verifies extension, signature and basic size when registering a reference. Full page and image validation, safe upload handling, and rendering remain later-phase work. New issued artifacts have attempt-specific private paths. Legacy files and issued artifacts stay outside any future temporary-file cleanup. The worker and its bounded retries and platform watchdog are deferred. The certificate-only queue visibility timeout is `CERTIFICATE_JOB_TIMEOUT` plus 60 seconds, which must be aligned with the eventual worker.

## Targeted verification

From `backend` with Composer dependencies installed:

```sh
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= \
  php vendor/bin/phpunit --do-not-cache-result \
  tests/Feature/CertificateArchiveMigrationTest.php \
  tests/Feature/CertificateFoundationTest.php \
  tests/Feature/CertificateTest.php
```

For MySQL concurrency, use a new disposable MySQL 8 database whose name ends in `_test`, a non-root user restricted to that database, and a PHP CLI with PDO MySQL and PCNTL. Set `APP_ENV=testing`, `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, and `DB_URL=`. Set `TMS_ISOLATED_MYSQL_DATABASE` to that database name and `TMS_ISOLATED_MYSQL_SERVER_UUID` to the result of `SELECT @@server_uuid` on the disposable instance. Run the same PHPUnit command without the SQLite environment overrides and append `tests/Feature/CertificateNumberConcurrencyTest.php`. The tests use forward migrations only. The concurrency test commits synthetic records, so use a dedicated database per run.
