<?php

namespace App\Services\Certificate;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

final class CertificateDocumentProcess
{
    /** The child only receives a bounded, private copy and a validated configuration. */
    public function run(string $mode, string $source, array $job): array
    {
        $scratch = storage_path('app/private/certificate-proof-work/'.Str::uuid());
        File::makeDirectory($scratch, 0700, true);
        try {
            if (! is_file($source) || filesize($source) > 5 * 1024 * 1024
                || ! copy($source, $scratch.'/source.'.$job['extension'])) {
                $this->reject();
            }
            if (file_put_contents($scratch.'/job.json', json_encode($job, JSON_THROW_ON_ERROR)) === false) {
                $this->reject();
            }
            $php = config('certificates.php_cli') ?: (new PhpExecutableFinder)->find(false);
            if (! $php || ! function_exists('proc_open')) {
                $this->reject('document_runtime_unavailable');
            }
            // Array arguments only. No shell script, user-selected executable or web-request path is executed.
            $process = new Process([
                $php, '-d', 'memory_limit=128M', '-d', 'allow_url_fopen=0', '-d', 'display_errors=0',
                // The short-lived CLI must not attach to the Windows web server's OPcache.
                '-d', 'opcache.enable_cli=0',
                ...($mode === 'issue' ? ['-d', 'max_execution_time=12'] : []),
                base_path('scripts/certificate-document.php'), $scratch, $mode,
            ], $scratch, PHP_OS_FAMILY === 'Windows' ? $this->windowsStartupEnvironment() : null, timeout: 12);
            if ($process->run() !== 0) {
                $this->logFailure($process, $mode, 'exit');
                $this->reject();
            }
            try {
                $result = json_decode($process->getOutput(), true, 16, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $this->logFailure($process, $mode, 'invalid_output');
                $this->reject();
            }
            if (! is_array($result)) {
                $this->logFailure($process, $mode, 'invalid_output');
                $this->reject();
            }
            if (empty($result['overflow'])) {
                $file = $scratch.'/result';
                if (! is_file($file) || filesize($file) > 5 * 1024 * 1024 || filesize($file) === 0) {
                    $this->logFailure($process, $mode, 'invalid_artifact');
                    $this->reject();
                }
                $result['bytes'] = file_get_contents($file);
            }

            return $result;
        } catch (ProcessTimedOutException) {
            $this->logFailure($process, $mode, 'timeout');
            $this->reject('document_timeout');
        } catch (ExceptionInterface) {
            $this->logFailure($process ?? null, $mode, 'process_error');
            $this->reject('document_runtime_unavailable');
        } finally {
            File::deleteDirectory($scratch);
        }
    }

    private function windowsStartupEnvironment(): array
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

    private function logFailure(?Process $process, string $mode, string $stage): void
    {
        $started = $process?->isStarted();
        Log::warning('Certificate document process failed.', [
            'mode' => $mode,
            'stage' => $stage,
            'platform' => PHP_OS_FAMILY,
            'parent_sapi' => PHP_SAPI,
            'windows_system_root_available' => PHP_OS_FAMILY === 'Windows' ? (bool) getenv('SystemRoot', true) : null,
            'exit_code' => $started ? $process->getExitCode() : null,
            'stdout_bytes' => $started ? strlen($process->getOutput()) : 0,
            'stderr_bytes' => $started ? strlen($process->getErrorOutput()) : 0,
        ]);
    }

    private function reject(string $key = 'document_rejected'): never
    {
        throw ValidationException::withMessages(['background' => __('certificates.'.$key)]);
    }
}
