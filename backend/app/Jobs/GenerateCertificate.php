<?php

namespace App\Jobs;

use App\Services\Certificate\CertificateGeneration;
use App\Services\Certificate\CertificateLifecycle;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class GenerateCertificate implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $certificateId, public string $dispatchToken) {}

    public function backoff(): int
    {
        return config('certificates.job_timeout') + 1;
    }

    public function handle(CertificateLifecycle $lifecycle, CertificateGeneration $generation): void
    {
        $claim = $lifecycle->claim($this->certificateId, $this->dispatchToken);
        if ($claim === null) {
            return;
        }
        [$certificate, $previous] = $claim;
        $token = $certificate->attempt_token;
        $failure = $generation->generate($certificate, $token, $previous);
        if ($failure === null) {
            $lifecycle->publish($certificate, $token);

            return;
        }

        Log::warning('Certificate generation failed', [
            'certificate_id' => $certificate->id, 'attempt' => $certificate->generation_attempts, 'code' => $failure,
        ]);
        if ($failure === 'overflow' || $this->attempts() >= $this->tries) {
            $lifecycle->fail($certificate, $token, $failure);

            return;
        }
        $lifecycle->requeue($certificate, $token, $failure);
        $this->release($this->attempts() === 1 ? 5 : 15);
    }

    public function failed(Throwable $exception): void
    {
        app(CertificateLifecycle::class)->exhaust($this->certificateId, $this->dispatchToken);
    }
}
