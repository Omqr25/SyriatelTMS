<?php

namespace App\Services\Certificate;

use App\Enums\CertificateState;
use App\Models\Certificate;
use FilesystemIterator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class CertificateTemporaryCleanup
{
    public function __construct(private CertificatePackages $packages, private CertificateStorage $storage) {}

    public function run(): int
    {
        return $this->packages->cleanup()
            + $this->removeExpired(CertificateBackgrounds::DRAFTS, time() - 3900, directories: false)
            + $this->removeExpired(CertificateStorage::SCRATCH, time() - 7200, directories: true)
            + $this->removeFailedAttempts();
    }

    private function removeExpired(string $prefix, int $cutoff, bool $directories): int
    {
        $root = Storage::disk(CertificateStorage::DISK)->path($prefix);
        if (! is_dir($root) || is_link($root)) {
            return 0;
        }
        $removed = 0;
        foreach (new FilesystemIterator($root) as $item) {
            if ($item->isLink() || $item->getMTime() >= $cutoff) {
                continue;
            }
            $uuid = $directories ? $item->getFilename() : $item->getBasename('.'.$item->getExtension());
            if (! Str::isUuid($uuid)) {
                continue;
            }
            if ($directories && $item->isDir()) {
                $removed += (int) File::deleteDirectory($item->getPathname());
            } elseif (! $directories && $item->isFile() && in_array($item->getExtension(), ['pdf', 'png', 'jpg'], true)) {
                $removed += (int) @unlink($item->getPathname());
            }
            if ($removed >= 100) {
                break;
            }
        }

        return $removed;
    }

    private function removeFailedAttempts(): int
    {
        $candidates = Certificate::where('state', CertificateState::Failed)->whereNull('path')
            ->whereNotNull('attempt_token')->where('updated_at', '<', now()->subHours(2));
        $removed = 0;
        foreach ((clone $candidates)->orderBy('id')->limit(100)->pluck('id') as $id) {
            $removed += DB::transaction(function () use ($candidates, $id) {
                $certificate = (clone $candidates)->lockForUpdate()->find($id);
                if (! $certificate || ! Str::isUuid($certificate->attempt_token)
                    || ! $this->storage->discardAttempt($id, $certificate->attempt_token)) {
                    return 0;
                }
                $certificate->forceFill(['attempt_token' => null])->save();

                return 1;
            }, 3);
        }

        return $removed;
    }
}
