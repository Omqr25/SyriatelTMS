<?php

namespace App\Repositories\Interfaces;

use App\Models\User;

interface UserRepoInterface
{
    public function create(array $data): ?User ;

    public function findByEmail(string $email): ?User ;

    public function findById(int $id): ?User;

    public function update(User $user,array $data): ?User;

    public function delete(User $user): bool;
}
