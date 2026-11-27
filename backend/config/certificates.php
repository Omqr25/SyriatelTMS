<?php

return [
    // Override when the web runtime and PHP CLI use different installation paths.
    'php_cli' => env('CERTIFICATE_PHP_CLI'),
    'job_timeout' => max(30, (int) env('CERTIFICATE_JOB_TIMEOUT', 120)),
];
