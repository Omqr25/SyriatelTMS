<?php

namespace App\Jobs;

use App\Services\Certificate\CertificatePackageBuild;
use App\Services\Certificate\CertificatePackages;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class PackageCertificates implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $packageId, public int $dispatch) {}

    public function backoff(): int
    {
        return config('certificates.job_timeout') + 1;
    }

    public function handle(CertificatePackages $packages, CertificatePackageBuild $build): void
    {
        if ($package = $packages->claim($this->packageId, $this->dispatch)) {
            $build->run($package);
        }
    }

    public function failed(Throwable $exception): void
    {
        app(CertificatePackages::class)->exhaust($this->packageId, $this->dispatch);
    }
}
