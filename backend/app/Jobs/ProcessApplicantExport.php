<?php

namespace App\Jobs;

use App\Services\Training\ApplicantExport\ExportQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessApplicantExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    public const DOCUMENT_QUEUE = 'applicant-exports';

    public const EXCEL_QUEUE = 'applicant-exports-excel';

    public function __construct(public int $taskId, string $kind = '')
    {
        $this->timeout = max(1, config('applicant_exports.timeout'));
        $this->onConnection('applicant_exports')->onQueue($kind === 'excel' ? self::EXCEL_QUEUE : self::DOCUMENT_QUEUE);
    }

    public function handle(ExportQueue $queue): void
    {
        $queue->process($this->taskId);
    }
}
