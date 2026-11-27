<?php

namespace App\Services\Certificate;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CertificateStorage
{
    public const DISK = 'private';

    public const SCRATCH = 'certificate-proof-work';

    public function discardAttempt(int $id, string $token): bool
    {
        $path = Storage::disk(self::DISK)->path($this->issuedPath($id, $token));

        return ((! file_exists($path.'.tmp') && ! is_link($path.'.tmp')) || @unlink($path.'.tmp'))
            && ((! file_exists($path) && ! is_link($path)) || @unlink($path));
    }

    public function backgroundPath(string $extension): string
    {
        if (! in_array($extension, ['pdf', 'png', 'jpg'], true)) {
            $this->invalidBackground();
        }

        return 'certificates/templates/'.Str::uuid().'.'.$extension;
    }

    public function backgroundHash(string $path): string
    {
        if (! preg_match('#\\Acertificates/templates/([a-f0-9-]{36})\\.(pdf|png|jpg)\\z#D', $path, $parts) || ! Str::isUuid($parts[1])) {
            $this->invalidBackground();
        }

        $disk = Storage::disk(self::DISK);
        if (! $disk->exists($path) || $disk->size($path) <= 0 || $disk->size($path) > 5 * 1024 * 1024) {
            $this->invalidBackground();
        }

        $stream = $disk->readStream($path);
        if (! is_resource($stream)) {
            $this->invalidBackground();
        }
        try {
            $header = fread($stream, 8);
            $signature = match ($parts[2]) {
                'pdf' => '%PDF-',
                'png' => "\x89PNG\r\n\x1a\n",
                'jpg' => "\xff\xd8\xff",
            };
            if ($header === false || ! str_starts_with($header, $signature)) {
                $this->invalidBackground();
            }
            $hash = hash_init('sha256');
            hash_update($hash, $header);
            hash_update_stream($hash, $stream);

            return hash_final($hash);
        } finally {
            fclose($stream);
        }
    }

    public function issuedPath(int $certificateId, string $attemptToken): string
    {
        if ($certificateId < 1 || ! Str::isUuid($attemptToken)) {
            throw new \InvalidArgumentException('Invalid certificate artifact identity.');
        }

        return 'certificates/issued/'.$certificateId.'/'.$attemptToken.'.pdf';
    }

    private function invalidBackground(): never
    {
        throw ValidationException::withMessages(['background' => __('certificates.invalid_background')]);
    }
}
