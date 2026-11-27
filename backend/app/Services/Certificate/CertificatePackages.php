<?php

namespace App\Services\Certificate;

use App\Jobs\PackageCertificates;
use App\Models\CertificatePackage;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

final class CertificatePackages
{
    public const MAX_CERTIFICATES = 250;

    public const MAX_BYTES = 64 * 1024 * 1024;

    public const MAX_ARTIFACT_BYTES = 96 * 1024 * 1024;

    public function request(User $user, TrainingProgram $program, array $input): CertificatePackage
    {
        $id = strtolower($input['request_key']);
        $all = $input['selection'] === 'all';
        $ids = array_values(array_unique(array_map('intval', ($all ? ($input['except_ids'] ?? []) : $input['certificate_ids']))));
        sort($ids, SORT_NUMERIC);
        $hash = hash('sha256', json_encode([$program->id, $input['format'], $all, $ids], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($user, $program, $input, $all, $id, $ids, $hash) {
            User::lockForUpdate()->findOrFail($user->id);
            if ($existing = CertificatePackage::find($id)) {
                abort_unless($existing->requested_by === $user->id && hash_equals($existing->input_hash, $hash), 409);

                return $existing;
            }
            $this->guardCapacity($user);
            $issued = $program->certificates()->issued();
            if ($ids !== [] && (clone $issued)->whereIn('id', $ids)->count() !== count($ids)) {
                $this->reject('package_invalid_selection');
            }
            $selected = $issued->where(fn ($query) => $all ? $query->whereNotIn('id', $ids) : $query->whereIn('id', $ids))
                ->orderBy('id')->limit(self::MAX_CERTIFICATES + 1)->pluck('id')->all();
            if ($selected === []) {
                $this->reject('package_invalid_selection');
            }
            if (count($selected) > self::MAX_CERTIFICATES) {
                $this->reject('package_too_many');
            }

            $package = new CertificatePackage;
            $package->forceFill(['id' => $id, 'training_program_id' => $program->id, 'requested_by' => $user->id,
                'input_hash' => $hash, 'status' => 'queued', 'certificate_ids' => $selected, 'total' => count($selected),
                'format' => $input['format'], 'dispatch' => 1])->save();
            $this->enqueue($package);

            return $package;
        }, 3);
    }

    public function retry(User $user, CertificatePackage $package): CertificatePackage
    {
        return DB::transaction(function () use ($user, $package) {
            User::lockForUpdate()->findOrFail($user->id);
            $current = CertificatePackage::lockForUpdate()->findOrFail($package->id);
            if ($current->status !== 'failed' || $current->expires_at?->lte(now())) {
                $this->reject('package_retry_failed_only');
            }
            $this->guardCapacity($user, creating: false);
            $current->forceFill(['status' => 'queued', 'processed' => 0, 'dispatch' => $current->dispatch + 1,
                'started_at' => null, 'expires_at' => null, 'failure_code' => null, 'failed_certificate_id' => null])->save();
            $this->enqueue($current);

            return $current;
        }, 3);
    }

    public function claim(string $id, int $dispatch): ?CertificatePackage
    {
        return DB::transaction(function () use ($id, $dispatch) {
            $package = CertificatePackage::lockForUpdate()->find($id);
            if (! $package || $package->dispatch !== $dispatch) {
                return null;
            }
            $stale = $package->status === 'processing' && $package->started_at?->lte(now()->subSeconds(config('certificates.job_timeout')));
            if ($package->status !== 'queued' && ! $stale) {
                return null;
            }

            // The previous attempt was fenced before any replacement files are created.
            $package->forceFill(['status' => 'processing', 'processed' => 0, 'started_at' => now(),
                'attempt' => (string) Str::uuid()])->save();

            return $package;
        }, 3);
    }

    public function progress(CertificatePackage $package, int $count): void
    {
        $this->attempt($package)->update(['processed' => min($count, $package->total)]);
    }

    public function finish(CertificatePackage $package, ?string $failure = null, ?int $certificate = null): void
    {
        $this->attempt($package)->update([
            'status' => $failure ? 'failed' : 'ready', 'processed' => $failure ? DB::raw('processed') : $package->total,
            'failure_code' => $failure, 'failed_certificate_id' => $certificate, 'expires_at' => now()->addDay(),
        ]);
    }

    public function exhaust(string $id, int $dispatch): void
    {
        CertificatePackage::whereKey($id)->where('dispatch', $dispatch)->whereIn('status', CertificatePackage::ACTIVE)
            ->update(['status' => 'failed', 'failure_code' => 'interrupted', 'expires_at' => now()->addDay()]);
    }

    public function directory(string $id, ?string $attempt = null): string
    {
        if (! Str::isUuid($id) || ($attempt !== null && ! Str::isUuid($attempt))) {
            throw new LogicException('Invalid certificate package path.');
        }

        return Storage::disk(CertificateStorage::DISK)->path('certificates/packages/'.$id.($attempt ? '/'.$attempt : ''));
    }

    public function cleanup(): int
    {
        $cutoff = now()->subMinutes(10); // Let downloads that started before expiry finish.
        $removed = 0;
        foreach (CertificatePackage::where('expires_at', '<=', $cutoff)->orderBy('expires_at')->limit(100)->pluck('id') as $id) {
            $removed += DB::transaction(function () use ($id, $cutoff) {
                $package = CertificatePackage::whereKey($id)->where('expires_at', '<=', $cutoff)->lockForUpdate()->first();
                if (! $package) {
                    return 0;
                }
                $directory = $this->directory($id);
                if (is_link($directory) || (is_dir($directory) && ! File::deleteDirectory($directory)) || file_exists($directory)) {
                    return 0;
                }

                return (int) $package->delete();
            });
        }

        return $removed;
    }

    private function guardCapacity(User $user, bool $creating = true): void
    {
        $owned = CertificatePackage::where('requested_by', $user->id);
        if ((clone $owned)->whereIn('status', CertificatePackage::ACTIVE)->exists()) {
            $this->reject('package_already_active');
        }
        if ($creating && $owned->where('expires_at', '>', now())->count() >= 8) {
            $this->reject('package_capacity');
        }
    }

    private function attempt(CertificatePackage $package)
    {
        return CertificatePackage::whereKey($package->id)->where('status', 'processing')
            ->where('dispatch', $package->dispatch)->where('attempt', $package->attempt);
    }

    private function enqueue(CertificatePackage $package): void
    {
        if (DB::transactionLevel() < 1 || config('queue.connections.certificates.connection') !== null ||
            config('queue.connections.certificates.driver') !== 'database') {
            throw new LogicException('Certificate packaging requires the transactional database queue.');
        }
        Queue::connection('certificates')->push((new PackageCertificates($package->id, $package->dispatch))->beforeCommit(), '', 'certificates');
    }

    private function reject(string $key): never
    {
        throw ValidationException::withMessages(['package' => __('certificates.'.$key, ['max' => self::MAX_CERTIFICATES])]);
    }
}
