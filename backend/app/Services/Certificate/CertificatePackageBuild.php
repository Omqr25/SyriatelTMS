<?php

namespace App\Services\Certificate;

use App\Models\Certificate;
use App\Models\CertificatePackage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Normalizer;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

final class CertificatePackageBuild
{
    public function __construct(private CertificatePackages $packages) {}

    public function run(CertificatePackage $package): void
    {
        $started = hrtime(true);
        $requester = $package->requester;
        if (! $requester || ! Gate::forUser($requester)->allows('manage', Certificate::class)) {
            $this->packages->finish($package, 'authorization_revoked');

            return;
        }
        $directory = $this->packages->directory($package->id, $package->attempt);
        if (! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            $this->packages->finish($package, 'storage_failed');

            return;
        }
        foreach (File::directories($this->packages->directory($package->id)) as $attempt) {
            if (basename($attempt) !== $package->attempt && Str::isUuid(basename($attempt)) && ! is_link($attempt)) {
                File::deleteDirectory($attempt);
            }
        }
        $published = false;
        try {
            $manifest = $this->manifest($package);
            $result = isset($manifest['failure']) ? $manifest : $this->process($package, $directory, $manifest);
            if (! empty($result['ok'])) {
                $part = $directory.'/artifact.partial';
                clearstatcache(true, $part);
                $size = @filesize($part);
                $signature = $package->format === 'zip' ? "PK\x03\x04" : '%PDF-';
                if (! $size || $size > CertificatePackages::MAX_ARTIFACT_BYTES || ! str_starts_with((string) @file_get_contents($part, false, null, 0, 5), $signature)) {
                    $result = ['failure' => 'invalid_artifact'];
                } elseif (! @rename($part, $directory.'/artifact.'.$package->format)) {
                    $result = ['failure' => 'storage_failed'];
                } else {
                    // Preserve the staged file if the DB acknowledgement is lost. It is private and expiry-scoped.
                    $published = true;
                    $this->packages->finish($package);
                    Log::info('Certificate package created', [
                        'package_id' => $package->id, 'format' => $package->format, 'count' => $package->total,
                        'bytes' => $size, 'peak_bytes' => $result['peak_bytes'] ?? null,
                        'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
                    ]);

                    return;
                }
            }
            $this->packages->finish($package, $result['failure'], $result['certificate'] ?? null);
        } finally {
            if ($published) {
                @unlink($directory.'/manifest.json');
                File::deleteDirectory($directory.'/mpdf');
                File::deleteDirectory($directory.'/sources');
            } else {
                File::deleteDirectory($directory);
            }
        }
    }

    private function manifest(CertificatePackage $package): array
    {
        $query = Certificate::issued()->where('training_program_id', $package->training_program_id)
            ->whereKey($package->certificate_ids)->select(['id', 'path', 'certificate_batch_id']);
        if ($package->format === 'zip') {
            $query->addSelect(['snapshot', 'trainee_id'])->with('trainee.user:id,name');
        }
        $certificates = $query->orderBy('id')->get()->keyBy('id');
        $entries = $package->format === 'zip' ? $this->zipEntries($package, $certificates) : [];
        $files = [];
        foreach ($package->certificate_ids as $id) {
            $certificate = $certificates->get($id);
            $path = $certificate ? $this->sourcePath($certificate) : null;
            if ($path === null) {
                return ['failure' => 'missing_source', 'certificate' => $id];
            }
            $files[] = ['id' => $id, 'path' => $path, 'entry' => $entries[$id] ?? null];
        }

        return ['format' => $package->format, 'files' => $files];
    }

    private function zipEntries(CertificatePackage $package, iterable $certificates): array
    {
        $program = $package->training_program_id;
        $name = $package->getConnection()->table('training_programs')->where('id', $program)->value('name');
        $slug = substr(Str::slug($name ?? ''), 0, 65) ?: 'training-program';
        $folder = 'program-'.$program.'-'.$slug;
        $stems = $reserved = $used = $entries = [];
        foreach ($certificates as $certificate) {
            $stem = $this->safeStem($certificate->snapshot['trainee_name'] ?? '')
                ?: $this->safeStem($certificate->trainee?->user?->name ?? '') ?: 'Certificate';
            $stems[$certificate->id] = $stem;
            $reserved[mb_convert_case($stem, MB_CASE_FOLD, 'UTF-8')] = true;
        }

        foreach ($stems as $id => $stem) {
            $key = mb_convert_case($stem, MB_CASE_FOLD, 'UTF-8');
            if (isset($used[$key])) {
                $base = $stem.'-'.$id;
                $stem = $base;
                $key = mb_convert_case($stem, MB_CASE_FOLD, 'UTF-8');
                for ($suffix = 2; isset($reserved[$key]) || isset($used[$key]); $suffix++) {
                    $stem = $base.'-'.$suffix;
                    $key = mb_convert_case($stem, MB_CASE_FOLD, 'UTF-8');
                }
            }
            $used[$key] = true;
            $entries[$id] = $folder.'/'.$stem.'.pdf';
        }

        return $entries;
    }

    private function safeStem(string $name): string
    {
        $normalized = Normalizer::normalize($name, Normalizer::FORM_C) ?: '';
        $stem = trim(mb_strcut(preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '-', $normalized), 0, 120, 'UTF-8'), '-');

        // These device names cannot be extracted as regular files on Windows.
        return preg_match('/^(?:CON|PRN|AUX|NUL|COM[1-9¹²³]|LPT[1-9¹²³])$/iuD', $stem) ? $stem.'-certificate' : $stem;
    }

    private function sourcePath(Certificate $certificate): ?string
    {
        $legacy = $certificate->certificate_batch_id === null;
        $path = $certificate->path ?? '';
        $pattern = $legacy ? '#\Acertificates/[a-zA-Z0-9_-]+\.pdf\z#iD'
            : '#\Acertificates/issued/'.$certificate->id.'/[0-9a-f-]{36}\.pdf\z#D';
        if (! preg_match($pattern, $path)) {
            return null;
        }
        $disk = Storage::disk($legacy ? 'local' : CertificateStorage::DISK);
        $root = realpath($disk->path('certificates'));
        $source = realpath($disk->path($path));
        $expected = $root === false ? null : $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, substr($path, strlen('certificates/')));
        if ($source === false || $source !== $expected || ! is_file($source) || ! is_readable($source)) {
            return null;
        }

        return $source;
    }

    private function process(CertificatePackage $package, string $directory, array $manifest): array
    {
        if (@file_put_contents($directory.'/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR)) === false) {
            return ['failure' => 'storage_failed'];
        }
        $timeout = min(90, config('certificates.job_timeout') - 5);
        $process = CertificatePhpProcess::make('certificate-package.php', [$directory], $directory, $timeout, 256);
        if ($process === null) {
            return ['failure' => 'runtime_unavailable'];
        }
        $buffer = '';
        $completed = 0;
        $result = ['failure' => 'processing_failed'];
        try {
            $exit = $process->run(function (string $type, string $chunk) use ($package, &$buffer, &$completed, &$result) {
                if ($type !== 'out') {
                    return;
                }
                $lines = explode("\n", $buffer.$chunk);
                $buffer = array_pop($lines);
                foreach ($lines as $line) {
                    $message = json_decode($line, true);
                    if (isset($message['processed'])) {
                        $completed = (int) $message['processed'];
                        if ($completed % 5 === 0 || $completed === $package->total) {
                            $this->packages->progress($package, $completed);
                        }
                    } elseif (isset($message['ok']) || isset($message['failure'])) {
                        $result = $message;
                    }
                }
            });

            return $exit === 0 ? $result : ['failure' => 'processing_failed'];
        } catch (ProcessTimedOutException) {
            return ['failure' => 'timeout'];
        } catch (ExceptionInterface) {
            return ['failure' => 'runtime_unavailable'];
        } finally {
            if ($process->isRunning()) {
                $process->stop(0);
            }
            if ($completed % 5 !== 0 && $completed !== $package->total) {
                $this->packages->progress($package, $completed);
            }
        }
    }
}
