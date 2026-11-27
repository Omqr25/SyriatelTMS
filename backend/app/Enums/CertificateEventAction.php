<?php

namespace App\Enums;

enum CertificateEventAction: string
{
    case Excluded = 'excluded';
    case Restored = 'restored';
    case Overridden = 'overridden';
    case HrNameSaved = 'hr_name_saved';
}
