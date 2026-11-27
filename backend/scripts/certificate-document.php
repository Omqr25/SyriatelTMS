<?php

use App\Services\Certificate\CertificateDocumentRenderer;

if (PHP_SAPI !== 'cli' || $argc !== 3 || ! is_dir($argv[1])) {
    exit(2);
}

require dirname(__DIR__).'/vendor/autoload.php';

try {
    $scratch = $argv[1];
    $job = json_decode(file_get_contents($scratch.'/job.json'), true, 24, JSON_THROW_ON_ERROR);
    $source = $scratch.'/source.'.$job['extension'];
    $renderer = new CertificateDocumentRenderer($scratch.'/mpdf');
    $result = match ($argv[2]) {
        'normalize' => ['page' => $renderer->normalize($source, $job['extension'], $scratch.'/result')],
        'proof' => $renderer->proof($source, $job['extension'], $job['configuration'], $job['sample'], $scratch.'/result'),
    };
    echo json_encode($result, JSON_THROW_ON_ERROR);
} catch (Throwable) {
    // Input and parser details are intentionally confined to the child process.
    exit(1);
}
