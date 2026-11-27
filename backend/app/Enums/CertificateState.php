<?php

namespace App\Enums;

enum CertificateState: string
{
    case Queued = 'queued';
    case Generating = 'generating';
    case Issued = 'issued';
    case Failed = 'failed';

    public function canTransitionTo(self $state): bool
    {
        return match ($this) {
            self::Queued => $state === self::Generating,
            self::Generating => in_array($state, [self::Issued, self::Failed], true),
            self::Failed => $state === self::Queued,
            self::Issued => false,
        };
    }
}
