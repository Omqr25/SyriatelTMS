<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Interfaces\UserRepoInterface;

class UserRepo implements UserRepoInterface
{
    /**
     * Create a new class instance.
     */
    protected $model;
    public function __construct(User $model)
    {
        $this->model = $model;
    }

    public function create(array $data): ?User
    {
        return $this->model->create($data);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    public function findById(int $id): ?User
    {
        // TODO: Implement findById() method.
    }

    public function update(User $user, array $data): ?User
    {
        // TODO: Implement update() method.
    }

    public function delete(User $user): bool
    {
        // TODO: Implement delete() method.
    }
}
