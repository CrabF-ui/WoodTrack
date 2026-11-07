<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function getFiltered(string $estado, ?string $rol = null, ?string $search = null): LengthAwarePaginator;

    public function findById(int $id): User;

    public function create(array $data): User;

    public function update(int $id, array $data): User;

    public function updatePassword(int $id, string $password): User;

    public function countActiveAdmins(): int;
}
