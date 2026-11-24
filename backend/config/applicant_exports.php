<?php

return [
    'root' => env('APPLICANT_EXPORT_ROOT', storage_path('app/private/.applicant-exports')),
    'part_bytes' => (int) env('APPLICANT_EXPORT_PART_BYTES', 524288000),
    'storage_bytes' => (int) env('APPLICANT_EXPORT_STORAGE_BYTES', 20 * 1024 ** 3),
    'min_free_bytes' => (int) env('APPLICANT_EXPORT_MIN_FREE_BYTES', 1024 ** 3),
    'max_applicants' => (int) env('APPLICANT_EXPORT_MAX_APPLICANTS', 5000),
    'concurrency' => (int) env('APPLICANT_EXPORT_CONCURRENCY', 1),
    'timeout' => (int) env('APPLICANT_EXPORT_TIMEOUT', 900),
    'attempts' => 3,
    'retention_hours' => (int) env('APPLICANT_EXPORT_RETENTION_HOURS', 24),
    'ticket_minutes' => 10,
    'origins' => array_filter(array_map('trim', explode(',', (string) env('APPLICANT_EXPORT_WEB_ORIGINS', '')))),
    // Enable only for a private Linux Nginx alias with the archive open-file semantics in the deployment guide.
    'accel_prefix' => env('APPLICANT_EXPORT_ACCEL_PREFIX'),
];
