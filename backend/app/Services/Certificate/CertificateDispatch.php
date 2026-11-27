<?php

namespace App\Services\Certificate;

use App\Enums\CertificateState;
use App\Jobs\GenerateCertificate;
use App\Models\Certificate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

final class CertificateDispatch
{
    public function __construct(private CertificateLifecycle $lifecycle) {}

    /** The database queue insert must commit on the same connection as the certificate. */
    public function enqueue(Certificate $certificate): void
    {
        if (DB::transactionLevel() < 1 || config('queue.connections.certificates.connection') !== null ||
            config('queue.connections.certificates.driver') !== 'database') {
            throw new LogicException('Certificate reservations require the transactional database queue.');
        }

        $token = (string) Str::uuid();
        $certificate->forceFill(['dispatch_token' => $token])->save();
        Queue::connection('certificates')->push((new GenerateCertificate($certificate->id, $token))->beforeCommit(), '', 'certificates');
    }

    public function retry(User $actor, int $programId, ?int $certificateId = null): int
    {
        Gate::forUser($actor)->authorize('manage', Certificate::class);

        return DB::transaction(function () use ($actor, $programId, $certificateId) {
            $failed = Certificate::where('training_program_id', $programId)->where('state', CertificateState::Failed)
                ->when($certificateId !== null, fn ($query) => $query->whereKey($certificateId))
                ->orderBy('id')->lockForUpdate()->get();
            if ($certificateId !== null && $failed->isEmpty()) {
                throw ValidationException::withMessages(['issuance' => __('certificates.retry_failed_only')]);
            }

            foreach ($failed as $certificate) {
                $this->enqueue($this->lifecycle->retry($actor, $certificate));
            }

            return $failed->count();
        }, 3);
    }
}
