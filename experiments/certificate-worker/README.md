# Phase 0.2 certificate worker probe

This harness boots a separate Laravel console application and creates three synthetic database queues in a fresh private SQLite database. It uses the actual Laravel scheduler and database queue worker. It does not load SyriatelTMS service providers, application routes, configuration, or application data. Core and export lanes use their existing connection, table, and queue names with synthetic jobs. No production jobs are dispatched by the probe.

## Run

Use the backend's locked Composer dependencies, PHP CLI with PDO SQLite, PCNTL and POSIX on Linux, and Python 3. From the repository root:

```sh
python3 -B experiments/certificate-worker/run.py \
  --php "$(command -v php)" \
  --autoload "$PWD/backend/vendor/autoload.php" \
  --output /tmp/syriatel-certificate-worker-evidence
```

The runner prints the fresh test directory and writes `summary.json`, `events.jsonl`, and `commands.log` there. It only accepts a fresh isolated probe layout and supplies no development or deployment connection. Test leases and timeouts are shortened. Repeated test invocations of `schedule:run` accelerate the normal minute ticks.

## Deployment implications

A dedicated scheduler-triggered CLI command can check for runnable certificate work under a non-expiring process-owned lock. Laravel drains only the certificate queue and exits when empty. Delayed and abandoned reservations remain discoverable by subsequent ticks. Test startup timing excludes the wait for the next production scheduler tick. Reuse the existing scheduler and leave the core and export worker commands alone. Schedule a potentially long production drain in the background so it does not block other scheduled commands.

The lock in this experiment is local to a filesystem on one Linux host. Verify the private volume, lock and atomic rename semantics, including deploy overlap, before allowing multiple launch locations. A distributed deployment needs a proven shared singleton mechanism. A database session advisory lock would need a dedicated, non-reconnecting session with explicit failure handling. Keep durable attempt fencing regardless of lock choice. A private per-attempt file plus conditional database publication makes a crash between file rename and database commit recoverable. Production needs bounded reconciliation for terminal attempts and orphan files that never see a successful retry.

The no-PCNTL case disables Laravel's asynchronous signal path on Linux. It demonstrates that the built-in worker timeout is insufficient on Windows. Use a validated external watchdog capable of terminating the worker process, or run certificate processing on a supported Linux host. Validate the real Windows/Laragon process model and the actual production scheduler, MySQL, and storage before deployment. No external watchdog was implemented here.
