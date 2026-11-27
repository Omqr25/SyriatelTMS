<?php

namespace App\Services\Certificate;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Exception\ExceptionInterface;
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
                base_path('scripts/certificate-document.php'), $scratch, $mode,
            ], $scratch, timeout: 12);
            if ($process->run() !== 0) {
                $this->reject();
            }
            $result = json_decode($process->getOutput(), true, 16, JSON_THROW_ON_ERROR);
            if (empty($result['overflow'])) {
                $file = $scratch.'/result';
                if (! is_file($file) || filesize($file) > 5 * 1024 * 1024 || filesize($file) === 0) {
                    $this->reject();
                }
                $result['bytes'] = file_get_contents($file);
            }

            return $result;
        } catch (ExceptionInterface) {
            $this->reject('document_timeout');
        } finally {
            File::deleteDirectory($scratch);
        }
    }

    private function reject(string $key = 'document_rejected'): never
    {
        throw ValidationException::withMessages(['background' => __('certificates.'.$key)]);
    }
}
