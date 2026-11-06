<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserPasswordRequest;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private UserRepositoryInterface $users) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'estado' => ['sometimes', 'in:activos,inactivos,todos'],
            'filtro_rol' => ['nullable', 'in:admin,encargado_inventario,chofer'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);
        $estado = $filters['estado'] ?? 'activos';
        $rol = $filters['filtro_rol'] ?? null;
        $search = $filters['q'] ?? null;

        return view('usuarios.index', ['usuarios' => $this->users->getFiltered($estado, $rol, $search), 'estado' => $estado, 'filtro_rol' => $rol, 'search' => $search]);
    }

    public function create(): View { return view('usuarios.create', ['filters' => request()->only(['estado', 'filtro_rol', 'q'])]); }

    public function store(UserStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->users->create($data);

        return redirect()->route('usuarios.index', $this->listingFilters($request))->with('success', 'Usuario creado correctamente.');
    }

    public function edit(int $usuario): View
    {
        return view('usuarios.edit', ['usuario' => $this->users->findById($usuario), 'filters' => request()->only(['estado', 'filtro_rol', 'q'])]);
    }

    public function update(UserUpdateRequest $request, int $usuario): RedirectResponse
    {
        $target = $this->users->findById($usuario);
        $data = $request->validated();
        if ($target->id === $request->user()->id && $data['rol'] !== User::ROL_ADMIN) {
            return back()->withInput()->with('error', 'No puedes quitarte tu propio rol de administrador.');
        }
        if ($target->rol === User::ROL_ADMIN && $target->activo && $data['rol'] !== User::ROL_ADMIN && $this->users->countActiveAdmins() <= 1) {
            return back()->withInput()->with('error', 'No se puede dejar el sistema sin ningún administrador activo.');
        }
        $filters = $this->listingFilters($request);
        $this->users->update($usuario, $data);

        return redirect()->route('usuarios.index', $filters)->with('success', 'Usuario actualizado correctamente.');
    }

    public function resetPassword(UserPasswordRequest $request, int $usuario): RedirectResponse
    {
        $this->users->updatePassword($usuario, $request->validated('password'));

        return redirect()->route('usuarios.index', $this->listingFilters($request))->with('success', 'Contraseña restablecida correctamente.');
    }

    public function deactivate(Request $request, int $usuario): RedirectResponse
    {
        $target = $this->users->findById($usuario);
        if ($target->id === $request->user()->id) {
            return back()->with('error', 'No puedes desactivar tu propia cuenta.');
        }
        if ($target->activo && $target->rol === User::ROL_ADMIN && $this->users->countActiveAdmins() <= 1) {
            return back()->with('error', 'No se puede dejar el sistema sin ningún administrador activo.');
        }
        $this->users->update($usuario, ['activo' => false]);

        return redirect()->route('usuarios.index', $this->listingFilters($request))->with('success', 'Usuario desactivado correctamente.');
    }

    public function reactivate(Request $request, int $usuario): RedirectResponse
    {
        $this->users->update($usuario, ['activo' => true]);

        return redirect()->route('usuarios.index', $this->listingFilters($request))->with('success', 'Usuario reactivado correctamente.');
    }

    private function listingFilters(Request $request): array { return $request->only(['estado', 'filtro_rol', 'q']); }
}
