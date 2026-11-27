<?php

namespace App\Services\Certificate;

use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use League\Flysystem\FilesystemException;

final class CertificateGeneration
{
    public function __construct(
        private CertificateBackgrounds $backgrounds,
        private CertificateDocumentProcess $process,
        private CertificateStorage $storage,
    ) {}

    public function generate(Certificate $certificate, string $token, ?string $previous): ?string
    {
        $disk = Storage::disk(CertificateStorage::DISK);
        if ($previous !== null) {
            // Claim fenced off the previous attempt before removing that attempt's private artifacts.
            $this->storage->discardAttempt($certificate->id, $previous);
        }
        $version = $certificate->templateVersion;
        try {
            $background = $this->backgrounds->stored($version);
        } catch (ValidationException|FilesystemException) {
            return 'background_unavailable';
        }
        try {
            $result = $this->process->run('issue', $disk->path($background['path']), [
                'extension' => $background['extension'], 'configuration' => $version->configuration,
                'values' => $certificate->snapshot['values'],
            ]);
        } catch (ValidationException) {
            return 'render_failed';
        }
        if (! empty($result['overflow'])) {
            return 'overflow';
        }

        if (! is_string($result['bytes'] ?? null) || ! str_starts_with($result['bytes'], '%PDF-')) {
            return 'render_failed';
        }

        $target = $disk->path($this->storage->issuedPath($certificate->id, $token));
        $directory = dirname($target);
        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            return 'storage_failed';
        }

        $temp = $target.'.tmp';
        $stream = @fopen($temp, 'xb');
        if ($stream === false) {
            return 'storage_failed';
        }
        try {
            @chmod($temp, 0600);
            $written = @fwrite($stream, $result['bytes']);
            $flushed = @fflush($stream);
        } finally {
            fclose($stream);
        }
        if ($written !== strlen($result['bytes']) || ! $flushed || ! @rename($temp, $target)) {
            @unlink($temp);

            return 'storage_failed';
        }

        return null;
    }
}
