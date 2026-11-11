<?php

namespace App\Enums;

enum EvaluationRequestStatus : string

{
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case PENDING = 'pending';
}
