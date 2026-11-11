<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case PENDING = 'pending';
    case ASSIGNED = 'assigned';
    case QUALIFIED = 'qualified';
    case FOR_INTERVIEW = 'for interview';
    case INTERVIEWED = 'interviewed';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case FIRED = 'fired';

}
