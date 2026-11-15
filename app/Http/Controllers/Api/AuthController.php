<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Middleware\EnsureApiUserIsActiveChofer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const HASH_FICTICIO = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    public function login(LoginRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $usuario = User::where('email', $datos['email'])->first();
        $contrasenaCorrecta = Hash::check($datos['password'], $usuario?->password ?? self::HASH_FICTICIO);

        if (! $usuario || ! $contrasenaCorrecta) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no son correctas.',
            ]);
        }

        if (! $usuario->activo) {
            return response()->json([
                'message' => EnsureApiUserIsActiveChofer::MENSAJE_CUENTA_DESACTIVADA,
            ], 403);
        }

        if ($usuario->rol !== User::ROL_CHOFER) {
            return response()->json([
                'message' => EnsureApiUserIsActiveChofer::MENSAJE_SIN_ACCESO,
            ], 403);
        }

        $nombreDispositivo = $datos['device_name'] ?? 'app';
        $expira = now()->addMinutes((int) config('sanctum.expiration'));
        $usuario->tokens()->where('name', $nombreDispositivo)->delete();
        $token = $usuario->createToken($nombreDispositivo, ['*'], $expira);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expira->toISOString(),
            'user' => [
                'id' => $usuario->id,
                'name' => $usuario->name,
                'email' => $usuario->email,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }
}
