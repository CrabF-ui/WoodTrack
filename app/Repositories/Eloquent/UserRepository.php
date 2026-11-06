<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserRepository implements UserRepositoryInterface
{
    public function getFiltered(string $estado, ?string $rol = null, ?string $search = null): LengthAwarePaginator
    {
        $query = User::query();
        if ($estado === 'activos') {
            $query->where('activo', true);
        } elseif ($estado === 'inactivos') {
            $query->where('activo', false);
        }
        if ($rol) {
            $query->where('rol', $rol);
        }
        if ($search) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

        return $query->orderBy('name')->paginate(15)->withQueryString();
    }

    public function findById(int $id): User
    {
        return User::findOrFail($id);
    }

    public function create(array $data): User
    {
        $user = new User($data);
        $user->activo = true;
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    public function update(int $id, array $data): User
    {
        $user = $this->findById($id);
        $user->fill($data);
        $user->save();

        return $user->refresh();
    }

    public function updatePassword(int $id, string $password): User
    {
        $user = $this->findById($id);
        $user->password = $password;
        $user->save();

        return $user;
    }

    public function countActiveAdmins(): int
    {
        return User::where('rol', User::ROL_ADMIN)->where('activo', true)->count();
    }
}
