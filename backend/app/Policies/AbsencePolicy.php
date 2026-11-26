<?php

namespace App\Policies;

use App\Models\Absence;
use App\Models\Trainee;
use App\Models\User;
use App\Services\Attendance\AttendanceAccess;
use Illuminate\Auth\Access\Response;

class AbsencePolicy
{
    public function record(User $user, Trainee $trainee): Response
    {
        return AttendanceAccess::supervises($user, $trainee)
            ? Response::allow()
            : Response::deny(__('absence.not_your_trainee'));
    }

    public function update(User $user, Absence $absence): Response
    {
        return $this->record($user, $absence->trainee);
    }

    public function delete(User $user, Absence $absence): Response
    {
        return $this->record($user, $absence->trainee);
    }
}
