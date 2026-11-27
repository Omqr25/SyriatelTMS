<?php

namespace App\Services\Certificate;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

final class CertificateDocumentProcess
{
    /** The child only receives a bounded, private copy and a validated configuration. */
    public function run(string $mode, string $source, array $job): array
    {
        $scratch = Storage::disk(CertificateStorage::DISK)->path(CertificateStorage::SCRATCH.'/'.Str::uuid());
        File::makeDirectory($scratch, 0700, true);
        try {
            if (! is_file($source) || filesize($source) > 5 * 1024 * 1024
                || ! copy($source, $scratch.'/source.'.$job['extension'])) {
                $this->reject();
            }
            if (file_put_contents($scratch.'/job.json', json_encode($job, JSON_THROW_ON_ERROR)) === false) {
                $this->reject();
            }
            $process = CertificatePhpProcess::make('certificate-document.php', [$scratch, $mode], $scratch, 12, 128, $mode === 'issue');
            if ($process === null) {
                $this->reject('document_runtime_unavailable');
            }
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
