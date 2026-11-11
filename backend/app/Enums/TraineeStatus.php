<?php

namespace App\Enums;

enum TraineeStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case COMPLETED = 'completed';
    case FIRED = 'fired';

}
