<?php

namespace App\Enums;

enum UserRole: string
{
    case TRAINING_TEAM = 'HR';
    case TRAINER = 'SUPERVISOR';
    case TRAINEE = 'TRAINEE';
}
