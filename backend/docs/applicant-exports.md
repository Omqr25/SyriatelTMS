# HR applicant exports

Open **HR → Registration → Export** on the training program card. The first request captures all Accepted and Active applications in that program. Excel and documents are separate operations. Select an existing snapshot to download or request its other deliverable. **Create New Export** captures current values. There is one processing slot per program, even when HR selects an older snapshot. Closing the modal or the browser leaves the operation running.

## Data and security

- The snapshot uses one database statement to capture application data and eligibility. Workbook cells are explicit text, including IDs, numbers and user-entered strings starting with formula characters. Nulls are blank. The workbook identifies the snapshot and training program.
- The Excel exclusions are `cv`, `national_id`, `academic_qualification`, and `personal_photo`. These fields contain private server paths for attachments. The manifest identifies documents by application ID and archive entry. It omits storage paths. Records in separate tables, including custom form answers, fall outside the applications-table export.
- The current upload flow creates a new UUID path for each submission and leaves committed document bytes in place. The capture worker hard-links each eligible input on the same volume and records its SHA-256. Hard links preserve bytes through rename/unlink without copying them. Authorized changes to an application row cannot change an existing snapshot. Originals must remain write-once. If administrative tooling or future application code introduces in-place writes, it must switch to immutable/versioned storage before this export can be used with it. The archive worker verifies every pinned document hash and blocks publication if the bytes differ.
- Supported original files are PDF, PNG and JPG/JPEG. The worker copies the original bytes into a STORE ZIP and never renders or decodes them. Keep endpoint malware protection on the HR/transfer workstation. Missing, unreadable, and unsupported entries are recorded in the manifests, and transient processing failures use bounded retries.
- Use a short extraction base path on Windows, such as `C:\Exports`. Entry names use UTF-8 and bounded relative paths. Each ZIP is independently extractable. Oversized applicant groups get their own part and a manifest warning. A normal part estimate includes both ZIP entry headers, the manifest, and ZIP64 headroom.
- Every ticket request and artifact GET, including each Range request, checks current HR role and the authenticated API session. Tickets expire in ten minutes or when their JWT expires, whichever comes first. A signed URL requires the HttpOnly, artifact-path-scoped browser credential. The JWT travels in the native form POST body. Disable body capture for ticket requests and redact `Cookie` and signed query strings in logs. Production requires HTTPS. Log entries record export request, retry, cancellation, and authorized artifact access without applicant fields or document names. Treat the artifacts as HR-sensitive data after download.

## API

HR submits `POST /api/v1/training_program/{program}/applicant-exports` with a `kind` of `capture`, `excel`, or `documents`. A `GET` on that route lists current snapshots and their individual task states. The `/{snapshot}/retry`, `/{snapshot}/cancel`, and `/{snapshot}/manifest` endpoints manage an existing snapshot. The browser exchanges a native `POST /api/v1/applicant-export-downloads/{snapshot}/{artifact}/ticket` for an authenticated signed GET on the same artifact path.

## Installation and worker

Run `composer install`, then apply the new export migration with `php artisan migrate` as part of normal release deployment. It adds export tables, including a dedicated queue table. It does not backfill or change applicant data. No additional Composer packages, conversion binaries, or external services are required. Reuse the existing 64-bit PHP environment, PDO database driver and PhpSpreadsheet extensions, including `zip`, `mbstring`, XML, and GD. MySQL 8 is the project's production database. SQLite is suitable for local feature tests. Use PCNTL on Linux for hard job timeouts.

Leave existing worker and scheduler services running. Start one separate export worker under a process supervisor.

```sh
php -d memory_limit=512M artisan queue:work applicant_exports --queue=applicant-exports --sleep=3 --tries=1 --timeout=900 --max-jobs=20
```

Use the configured `APPLICANT_EXPORT_TIMEOUT` (seconds) for the worker timeout. Job-level timeout takes precedence, and the connection sets `retry_after` to 90 seconds above it. Laravel runs each queue invocation once. The durable task records allow up to three attempts with exponential backoff, and the once-per-minute reconciliation command recovers abandoned leases and queues cleanup. A retry retains valid completed parts. The scheduler enqueues only bounded work. On Windows, the PHP worker cannot forcibly interrupt blocking library calls without PCNTL. Use an external process watchdog in production or run the export worker on a supported Linux host. The filesystem lock prevents takeover while a live worker still holds it.

| Environment setting | Default | Notes |
| --- | --- | --- |
| `APPLICANT_EXPORT_ROOT` | `storage/app/private/.applicant-exports` | Private, persistent local directory on the **same filesystem** as original uploads. The web, worker, and scheduler must see the same path and run as the same private service user, or have equivalent explicitly provisioned access. The volume must support hard links and advisory locks. Never put it under a public storage symlink. |
| `APPLICANT_EXPORT_PART_BYTES` | `524288000` | Estimated 500 MiB target. |
| `APPLICANT_EXPORT_STORAGE_BYTES` | `21474836480` | 20 GiB reservation budget, including retained artifacts and incomplete attempts. Raise only after sizing disk capacity for originals plus multiple retained exports. |
| `APPLICANT_EXPORT_MIN_FREE_BYTES` | `1073741824` | Disk space reserved for other workloads. Leave additional headroom for filesystem metadata, uploads, and worker temporaries. |
| `APPLICANT_EXPORT_MAX_APPLICANTS` | `5000` | Hard cap on one cohort. PhpSpreadsheet keeps worksheet cells in memory. Benchmark the cap against the worker's memory limit before increasing it. |
| `APPLICANT_EXPORT_CONCURRENCY` | `1` | Global active export-task slots. Scale worker count and this cap together after measuring I/O and database contention. |
| `APPLICANT_EXPORT_MEMORY_LIMIT` | `512M` | PHP memory limit applied by `start-worker.sh applicant-exports`. Apply an equivalent limit when starting the worker directly. |
| `APPLICANT_EXPORT_TIMEOUT` | `900` | Seconds per attempt. Match the export worker timeout and give the process supervisor at least 90 seconds of additional shutdown grace. |
| `APPLICANT_EXPORT_RETENTION_HOURS` | `24` | A capture-to-expiry lifetime. Expired downloads are denied immediately. Cleanup waits for live processing and downloads to release their locks. |
| `APPLICANT_EXPORT_WEB_ORIGINS` | Same API origin | Comma-separated exact frontend origins for split frontend/API deployments. Specify schemes and ports, without paths. |
| `APPLICANT_EXPORT_ACCEL_PREFIX` | Off | Optional internal Nginx URI prefix for Linux full-file transfers. |

The worker's private files must reside alongside a local, hard-link-capable upload volume. For a single server this can be NTFS or a Linux local filesystem. Validate locks and POSIX/NTFS semantics in the deployment. A deployment with separate ephemeral volumes, object storage inputs, or unverified network-filesystem locking requires a storage design change. Failure to pin a document from an unsupported volume stops capture. Use dedicated worker CPU/RAM/I/O limits or lower I/O priority where available. With PHP file streaming, reserve sufficient web worker capacity separately for concurrent downloads. Queue isolation does not remove shared resource contention.

### Optional Linux Nginx download acceleration

Use only when Nginx and PHP see the same persistent archive path. Keep `APPLICANT_EXPORT_ACCEL_PREFIX` off until verified in staging. Set it to `/_private_applicant_exports` with an internal alias.

```nginx
location ^~ /_private_applicant_exports/ {
    internal;
    alias /path/to/private/.applicant-exports/;
    etag off;
    gzip off;
}
```

Check direct access returns 404, a full GET keeps the SHA-256 ETag from PHP, and resumed requests still reach the authorized PHP endpoint. Range and If-Range requests always use the PHP streaming path. Nginx supports keeping an already opened Linux file readable after unlink. The application additionally defers cleanup for 15 minutes during handoff. Keep the option disabled on Windows and on servers where the proxy cannot preserve headers or open-file semantics.

### Download resume

An authorized full download can continue after ticket expiry because authorization is checked when the request starts. New requests to an expired URL fail. Issue a new ticket from the same part’s Download button to get a fresh URL and cookie for the same filename, bytes, and ETag. A client that can set the new URL, cookie, byte offset, and prior ETag can resume using `Range` and `If-Range`. Some browsers only retry the original URL after interruption. Their native download manager may start a new download with a renewed ticket. It always reuses the existing archive.

## Staging load and compatibility rehearsal

Use a disposable database and private filesystem containing synthetic data. Run existing default workers, the scheduler, and an independent export worker on deployment-equivalent resources. Enable a safe mail transport and a test Firebase device or fake adapter. Keep real applicants and production secrets outside the rehearsal.

1. Seed synthetic cohorts of 500, 1,000 and 2,000+ applicants on separate programs. The following Laravel Tinker code generates two document slots per applicant by default. Run it again for each size, and also test four 5 MiB documents per applicant if the staging volume can hold both inputs and output. The synthetic bytes exercise I/O, with no real identity documents.

   ```php
   // php artisan tinker
   $count = 500;
   $documents = 2;
   $mebibytes = 1;
   $program = App\Models\TrainingProgram::factory()->create();
   $files = Illuminate\Support\Facades\Storage::disk('private');
   $chunk = random_bytes(1024 * 1024);
   $fields = ['cv' => 'cv', 'national_id' => 'national-id',
       'academic_qualification' => 'qualifications', 'personal_photo' => 'photo'];
   for ($i = 0; $i < $count; $i++) {
       $applicant = App\Models\Application::factory()->forProgram($program)->create([
           'status' => $i % 2 ? 'active' : 'accepted',
           'full_name' => "Synthetic Applicant $i",
       ]);
       $key = Illuminate\Support\Str::uuid();
       foreach (array_slice($fields, 0, $documents, true) as $field => $folder) {
           $path = "applications/$key/$folder/synthetic.".($field === 'personal_photo' ? 'png' : 'pdf');
           $fullPath = $files->path($path);
           Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($fullPath));
           $handle = fopen($fullPath, 'wb');
           for ($j = 0; $j < $mebibytes; $j++) fwrite($handle, $chunk);
           fclose($handle);
           $applicant->update([$field => $path]);
       }
   }
   $program->id;
   ```

2. Measure a no-export baseline for at least 10 minutes. Replay ordinary HR list requests, schedule synthetic interviews, emit inbox notifications, and check delayed workflow jobs and scheduler timings. Record p50/p95/p99 web latencies, queue enqueue-to-start and enqueue-to-finish times, job errors, database wait times, and scheduler lag. Record host CPU, worker RSS, disk I/O wait and free space, and network throughput.
3. Repeat the same workload while generating XLSX and ZIPs across multiple programs. Send two export requests at the same time for one program and check that both return the same snapshot. Download multiple independent parts while other programs are generating. Compare each run against the baseline and use the team's existing latency targets as acceptance criteria. Start with concurrency one, and raise it only after verifying the production quotas and targets under simultaneous archive generation and downloads.
4. Interrupt an export worker during a ZIP attempt, wait for its lease and reconciliation, and verify that only unfinished parts regenerate. Cancel a running operation and check completed files remain available. Lower the export quota to force a clean capacity failure then restore it and retry. Close and reopen the modal to verify persistent progress.
5. Validate each archive in an ordinary ZIP reader, compare its SHA-256 to the status endpoint, and compare original synthetic document hashes to the manifest. Check Arabic paths on Windows. Check an unauthorized user gets 401/403 for copied links, a stale If-Range validator never appends to a different file, and an expired URL fails. Reissue a ticket and use the same Range offset and ETag. Concatenate the earlier and remaining bytes and compare the resulting hash with the completed artifact. Repeat using supported HR browsers to measure their download-manager behavior.
6. Let an export expire while a transfer is still receiving bytes. The ongoing PHP stream should finish and its subsequent Range request should fail. Once the stream closes, confirm the expired artifact and snapshot rows are removed and the uploaded originals are still intact.
