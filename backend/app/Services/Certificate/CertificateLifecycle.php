<?php

namespace App\Services\Certificate;

use App\Enums\CertificateState;
use App\Models\Certificate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;

final class CertificateLifecycle
{
    public function __construct(private CertificateStorage $storage) {}

    public function start(Certificate $certificate): Certificate
    {
        return $this->transition($certificate, CertificateState::Generating, fn (Certificate $current) => [
            'generation_attempts' => $current->generation_attempts + 1,
            'attempt_token' => (string) Str::uuid(), 'generation_started_at' => now(),
        ]);
    }

    public function fail(Certificate $certificate, string $token, string $code): Certificate
    {
        if (! preg_match('/\\A[a-z0-9_]{1,64}\\z/D', $code)) {
            throw new LogicException('Invalid certificate failure code.');
        }

        return $this->transition($certificate, CertificateState::Failed, fn () => [
            'failure_code' => $code, 'attempt_token' => null,
        ], $token);
    }

    /** The final immutable artifact must already be present on private storage. */
    public function publish(Certificate $certificate, string $token): Certificate
    {
        return $this->transition($certificate, CertificateState::Issued, function (Certificate $current) use ($token) {
            $path = $this->storage->issuedPath($current->id, $token);
            $disk = Storage::disk(CertificateStorage::DISK);
            if (! $disk->exists($path) || $disk->size($path) === 0) {
                throw new LogicException('Certificate artifact is missing.');
            }

            return ['path' => $path, 'published_at' => now(), 'attempt_token' => null];
        }, $token);
    }

    public function retry(User $actor, Certificate $certificate): Certificate
    {
        Gate::forUser($actor)->authorize('manage', Certificate::class);

        return $this->transition($certificate, CertificateState::Queued, fn () => [
            'failure_code' => null, 'generation_started_at' => null,
        ]);
    }

    private function transition(Certificate $certificate, CertificateState $state, callable $changes, ?string $token = null): Certificate
    {
        return DB::transaction(function () use ($certificate, $state, $changes, $token) {
            $current = Certificate::lockForUpdate()->findOrFail($certificate->id);
            if ($state === CertificateState::Issued && $current->state === CertificateState::Issued &&
                $current->path === $this->storage->issuedPath($current->id, $token)) {
                return $current;
            }
            if (! $current->state->canTransitionTo($state) ||
                ($token !== null && ! hash_equals($current->attempt_token ?? '', $token))) {
                throw new LogicException('Invalid certificate transition or stale attempt.');
            }
            $current->forceFill(['state' => $state] + $changes($current))->save();

            return $current;
        }, 3);
    }
}
