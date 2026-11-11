<?php

namespace App\Services\Organization;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\ErrorException;
use App\Jobs\SendAccountCreatedEmailJob;
use App\Mail\AccountCreatedMail;
use App\Models\Role;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TrainerService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }
    public function create(array $data): Trainer
    {

        return DB::transaction(function () use ($data) {

            $password = Str::random(8);
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone_number' => $data['phone_number'],
                'password' => $password,
                'status' => UserStatus::ACTIVE,
            ]);

            $role = Role::where('role', UserRole::TRAINER->value)->firstOrFail();

            $user->roles()->attach($role->id);

            $trainer = Trainer::create([
                'user_id' => $user->id,
                'department_id' => $data['department_id'],
            ]);

            SendAccountCreatedEmailJob::dispatch($user, $password, UserRole::TRAINER->value)->afterCommit();
            return $trainer->load(['user', 'department']);


        });
    }

    public function findAll(Request $request)
    {
        return Trainer::with(['user', 'department','user.roles'])->get();



    }

    public function find(int $id)
    {
        $trainer=Trainer::find($id);

        if (!$trainer) {
            throw new ErrorException(__('trainer.not_found'));
        }
        return $trainer;
    }

    public function update(int $trainer_id, array $data)
    {

        $trainer=$this->find($trainer_id);
        return DB::transaction(function () use ($trainer, $data) {

            $trainer->user->update([
                'name' => $data['name'] ?? $trainer->user->name,
                'email' => $data['email'] ?? $trainer->user->email,
                'phone_number' => $data['phone_number'] ?? $trainer->user->phone_number,
            ]);

            if (isset($data['department_id'])) {
                $trainer->update([
                    'department_id' => $data['department_id'],
                ]);
            }

            return $trainer->load(['user', 'department']);
        });
    }

    public function delete(trainer $trainer): void
    {
        DB::transaction(function () use ($trainer) {

            $user = $trainer->user;

            $trainer->delete();

            $user->delete();
        });
    }

    public function assignDepartment(
        trainer $trainer,
        int $departmentId
    ): trainer
    {

        $trainer->update([
            'department_id' => $departmentId,
        ]);

        return $trainer->load([
            'user',
            'department',
        ]);
    }
}
