<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiUserIsActiveChofer
{
    public const MENSAJE_CUENTA_DESACTIVADA = 'Tu cuenta está desactivada. Contacta a un administrador.';

    public const MENSAJE_SIN_ACCESO = 'Esta cuenta no tiene acceso a la app.';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user->activo) {
            return response()->json(['message' => self::MENSAJE_CUENTA_DESACTIVADA], 403);
        }

        if ($user->rol !== User::ROL_CHOFER) {
            return response()->json(['message' => self::MENSAJE_SIN_ACCESO], 403);
        }

        return $next($request);
    }
}
