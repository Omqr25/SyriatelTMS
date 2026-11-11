<?php

namespace App\Enums;

enum TaskStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case ForReview = 'for review';
    case NeedFix = 'need fix';

    case OVERDUE = 'overdue';

}
