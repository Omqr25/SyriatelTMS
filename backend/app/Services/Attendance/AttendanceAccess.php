<?php

namespace App\Services\Attendance;

use App\Enums\UserRole;
use App\Models\Trainee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AttendanceAccess
{
    public static function isHr(User $user): bool
    {
        return $user->roles->pluck('role')->containsStrict(UserRole::TRAINING_TEAM);
    }

    public static function supervisorId(User $user): ?int
    {
        if (self::isHr($user) || ! $user->roles->pluck('role')->containsStrict(UserRole::TRAINER)) {
            return null;
        }

        return $user->trainer?->id;
    }

    public static function traineeId(User $user): ?int
    {
        if (self::isHr($user) || ! $user->roles->pluck('role')->containsStrict(UserRole::TRAINEE)) {
            return null;
        }

        return $user->trainee?->id;
    }

    public static function trainees(User $user): Builder
    {
        return match (true) {
            self::isHr($user) => Trainee::query(),
            ($supervisor = self::supervisorId($user)) !== null => Trainee::where('trainees.trainer_id', $supervisor),
            ($trainee = self::traineeId($user)) !== null => Trainee::whereKey($trainee),
            default => Trainee::whereRaw('1 = 0'),
        };
    }

    public static function supervises(User $user, Trainee $trainee): bool
    {
        $supervisor = self::supervisorId($user);

        return $supervisor !== null && $supervisor === $trainee->trainer_id;
    }
}
