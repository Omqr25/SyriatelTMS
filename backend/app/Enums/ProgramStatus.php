<?php

namespace App\Enums;

enum ProgramStatus : string
{
    case UN_ASSIGNED = 'un_assigned';
    case IN_PROGRESS = 'in_progress';
    case FINISHED = 'finished';
}
