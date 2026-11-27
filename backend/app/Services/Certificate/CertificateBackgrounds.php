<?php

namespace App\Services\Certificate;

use App\Models\CertificateTemplateVersion;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class CertificateBackgrounds
{
    private const DRAFTS = 'certificates/template-drafts/';

    private const MIME = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg'];

    public function __construct(private CertificateStorage $storage, private CertificateDocumentProcess $process) {}

    public function upload(User $actor, UploadedFile $upload): array
    {
        $extension = array_search($upload->getMimeType(), self::MIME, true);
        if ($extension === false) {
            $this->invalid();
        }

        $this->pruneDrafts();
        $result = $this->process->run('normalize', $upload->getRealPath(), ['extension' => $extension]);
        $draft = [
            'name' => Str::uuid().'.'.$extension, 'owner' => $actor->id,
            'expires' => now()->addHour()->timestamp, 'page' => $result['page'], 'extension' => $extension,
        ];
        if (! Storage::disk(CertificateStorage::DISK)->put(self::DRAFTS.$draft['name'], $result['bytes'])) {
            $this->invalid();
        }

        return [
            'token' => Crypt::encryptString(json_encode($draft, JSON_THROW_ON_ERROR)),
            'page' => $result['page'], 'mime' => self::MIME[$extension],
        ];
    }

    public function draft(User $actor, string $token): array
    {
        try {
            $draft = json_decode(Crypt::decryptString($token), true, 8, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            $this->invalid();
        }
        if (($draft['owner'] ?? null) !== $actor->id || ($draft['expires'] ?? 0) <= now()->timestamp
            || ! preg_match('/\A[a-f0-9-]{36}\.(pdf|png|jpg)\z/D', $draft['name'] ?? '')) {
            $this->invalid();
        }
        $draft['path'] = self::DRAFTS.$draft['name'];
        $draft['mime'] = self::MIME[$draft['extension']];
        if (! Storage::disk(CertificateStorage::DISK)->exists($draft['path'])) {
            $this->invalid();
        }

        return $draft;
    }

    public function stored(CertificateTemplateVersion $version): array
    {
        if (! hash_equals($version->background_sha256, $this->storage->backgroundHash($version->background_path))) {
            $this->invalid();
        }
        $extension = pathinfo($version->background_path, PATHINFO_EXTENSION);

        return [
            'path' => $version->background_path, 'extension' => $extension,
            'page' => $version->configuration['page'], 'mime' => self::MIME[$extension],
        ];
    }

    public function read(array $background): Response
    {
        return Storage::disk(CertificateStorage::DISK)->response($background['path'], 'certificate-background', [
            'Content-Type' => $background['mime'], 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => 'sandbox',
        ], 'inline');
    }

    public function proof(array $background, array $config, string $sample): Response
    {
        $result = $this->process->run('proof', Storage::disk(CertificateStorage::DISK)->path($background['path']), [
            'configuration' => $config, 'extension' => $background['extension'], 'sample' => $sample,
        ]);
        if ($result['overflow'] !== []) {
            throw ValidationException::withMessages([
                'configuration' => __('certificates.proof_overflow', ['boxes' => implode(', ', $result['overflow'])]),
            ]);
        }

        return response($result['bytes'], 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="certificate-proof.pdf"',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function withPermanent(array $background, callable $save): mixed
    {
        if (! str_starts_with($background['path'], self::DRAFTS)) {
            return $save($background['path']);
        }
        $path = $this->storage->backgroundPath($background['extension']);
        $disk = Storage::disk(CertificateStorage::DISK);
        if (! $disk->copy($background['path'], $path)) {
            $this->invalid();
        }
        try {
            return $save($path);
        } catch (Throwable $error) {
            // A database commit may have succeeded even if its acknowledgement was lost.
            if (! CertificateTemplateVersion::where('background_path', $path)->exists()) {
                $disk->delete($path);
            }

            throw $error;
        }
    }

    private function pruneDrafts(): void
    {
        $disk = Storage::disk(CertificateStorage::DISK);
        $directory = $disk->path(self::DRAFTS);
        if (! is_dir($directory)) {
            return;
        }
        $checked = 0;
        foreach (new \FilesystemIterator($directory) as $file) {
            if (++$checked > 100) {
                break;
            }
            if (! $file->isLink() && $file->isFile() && $file->getMTime() < time() - 3900
                && preg_match('/\A[a-f0-9-]{36}\.(pdf|png|jpg)\z/D', $file->getFilename())) {
                $disk->delete(self::DRAFTS.$file->getFilename());
            }
        }
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['background' => __('certificates.invalid_background')]);
    }
}
