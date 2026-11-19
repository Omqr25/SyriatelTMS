<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

class TrainingProgramAdmin extends Command
{
    protected $signature = 'training-programs:admin {email : HR account email} {--revoke}';

    protected $description = 'Grant or revoke permission to edit and delete training programs';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user || (! $this->option('revoke') && ! $user->roles()->where('role', UserRole::TRAINING_TEAM)->exists())) {
            $this->error('Select an existing HR account.');

            return self::FAILURE;
        }

        $user->training_program_admin = ! $this->option('revoke');
        $user->save();
        $this->info($user->training_program_admin ? 'Program administration granted.' : 'Program administration revoked.');

        return self::SUCCESS;
    }
}
