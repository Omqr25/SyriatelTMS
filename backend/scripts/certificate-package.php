<?php

use App\Services\Certificate\CertificatePackageRenderer;

if (PHP_SAPI !== 'cli' || $argc !== 2 || ! is_dir($argv[1])) {
    exit(2);
}

require dirname(__DIR__).'/vendor/autoload.php';

$directory = $argv[1];
try {
    $manifest = json_decode(file_get_contents($directory.'/manifest.json'), true, 8, JSON_THROW_ON_ERROR);
    $renderer = new CertificatePackageRenderer;
    $result = $renderer->build($directory, $manifest, function (int $count): void {
        echo json_encode(['processed' => $count], JSON_THROW_ON_ERROR)."\n";
        flush();
    });
} catch (Throwable) {
    $result = ['failure' => 'processing_failed'];
}
echo json_encode($result, JSON_THROW_ON_ERROR)."\n";
