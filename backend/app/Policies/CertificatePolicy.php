<?php

namespace App\Policies;

use App\Enums\CertificateState;
use App\Enums\TraineeStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Certificate;
use App\Models\Trainee;
use App\Models\User;

class CertificatePolicy
{
    public function manage(User $user): bool
    {
        return $this->hasActiveRole($user, UserRole::TRAINING_TEAM);
    }

    public function view(User $user, Certificate $certificate): bool
    {
        if ($certificate->state !== CertificateState::Issued) {
            return false;
        }

        return $this->manage($user) || $this->owns($user, $certificate->trainee);
    }

    public function submitName(User $user, Trainee $trainee): bool
    {
        return $this->owns($user, $trainee);
    }

    private function owns(User $user, Trainee $trainee): bool
    {
        return $trainee->user_id === $user->id
            && $trainee->status !== TraineeStatus::FIRED
            && $this->hasActiveRole($user, UserRole::TRAINEE);
    }

    private function hasActiveRole(User $user, UserRole $role): bool
    {
        return User::whereKey($user->id)->where('status', UserStatus::ACTIVE)
            ->whereHas('roles', fn ($query) => $query->where('role', $role))->exists();
    }
}
