<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Trainee;
use App\Models\User;

class TraineePolicy
{
    public function viewContact(User $user, Trainee $trainee): bool
    {
        if ($user->id === $trainee->user_id) {
            return true;
        }

        $roles = $user->roles->pluck('role');

        return $roles->containsStrict(UserRole::TRAINING_TEAM)
            || ($roles->containsStrict(UserRole::TRAINER)
                && $user->trainer !== null
                && $user->trainer->id === $trainee->trainer_id);
    }
}
