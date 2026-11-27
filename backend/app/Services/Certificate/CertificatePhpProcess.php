<?php

namespace App\Services\Certificate;

use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

final class CertificatePhpProcess
{
    public static function make(string $script, array $arguments, string $directory, int $timeout, int $memory, bool $limitCpu = true): ?Process
    {
        $php = config('certificates.php_cli') ?: (new PhpExecutableFinder)->find(false);
        if (! $php || ! function_exists('proc_open')) {
            return null;
        }

        return new Process([
            $php, '-d', 'memory_limit='.$memory.'M', '-d', 'allow_url_fopen=0', '-d', 'display_errors=0',
            '-d', 'opcache.enable_cli=0',
            ...($limitCpu ? ['-d', 'max_execution_time='.$timeout] : []),
            base_path('scripts/'.$script), ...$arguments,
        ], $directory, PHP_OS_FAMILY === 'Windows' ? self::windowsStartupEnvironment() : null, timeout: $timeout);
    }

    private static function windowsStartupEnvironment(): array
    {
        // Symfony may narrow the inherited environment to keys present in the request's
        // $_SERVER. Read startup paths directly from the OS, not from the request.
        $environment = [];
        foreach ([
            'SystemRoot', 'WINDIR', 'SystemDrive', 'ComSpec', 'PATH', 'PATHEXT',
            'TEMP', 'TMP', 'USERPROFILE', 'APPDATA', 'LOCALAPPDATA', 'PHPRC', 'PHP_INI_SCAN_DIR',
        ] as $name) {
            $value = getenv($name, true);
            if ($value !== false) {
                $environment[$name] = $value;
            }
        }

        return $environment;
    }
}
